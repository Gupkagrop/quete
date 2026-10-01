"""Фикстуры pytest для асинхронного тестирования бэкенда."""

import time
from collections.abc import AsyncGenerator

import pytest
from httpx import ASGITransport, AsyncClient
from sqlalchemy.ext.asyncio import (
    AsyncEngine,
    async_sessionmaker,
    create_async_engine,
)
from sqlmodel import SQLModel
from sqlmodel.ext.asyncio.session import AsyncSession

from app.db.database import get_session
from app.main import app
from app.services.session_service import session_service


class FakeRedis:
    """In-memory имитация Redis для изолированного тестирования."""

    def __init__(self) -> None:
        self.store: dict[str, str] = {}
        self.expires: dict[str, float] = {}

    def _cleanup_expired(self, key: str) -> None:
        if key in self.expires and time.time() > self.expires[key]:
            self.store.pop(key, None)
            self.expires.pop(key, None)

    async def get(self, key: str) -> str | None:
        self._cleanup_expired(key)
        return self.store.get(key)

    async def set(
        self,
        key: str,
        value: str,
        ex: int | None = None,
    ) -> bool:
        self.store[key] = str(value)
        if ex is not None:
            self.expires[key] = time.time() + ex
        else:
            self.expires.pop(key, None)
        return True

    async def delete(self, *keys: str) -> int:
        count = 0
        for k in keys:
            if k in self.store:
                del self.store[k]
                self.expires.pop(k, None)
                count += 1
        return count

    async def incr(self, key: str) -> int:
        self._cleanup_expired(key)
        val = int(self.store.get(key, "0")) + 1
        self.store[key] = str(val)
        return val

    async def expire(self, key: str, seconds: int) -> bool:
        if key in self.store:
            self.expires[key] = time.time() + seconds
            return True
        return False

    async def ttl(self, key: str) -> int:
        self._cleanup_expired(key)
        if key not in self.store:
            return -2
        if key not in self.expires:
            return -1
        rem = int(self.expires[key] - time.time())
        return max(rem, 0)


@pytest.fixture(name="fake_redis", autouse=True)
def fixture_fake_redis() -> FakeRedis:
    """Фикстура изолированного хранилища Redis для каждого теста."""
    fake = FakeRedis()
    session_service._redis = fake
    return fake


@pytest.fixture(name="async_engine")
async def fixture_async_engine() -> AsyncGenerator[AsyncEngine, None]:
    """Создаёт in-memory SQLite асинхронный движок для тестов."""
    test_engine = create_async_engine(
        "sqlite+aiosqlite:///:memory:",
        echo=False,
        future=True,
    )

    async with test_engine.begin() as conn:
        await conn.run_sync(SQLModel.metadata.create_all)

    yield test_engine

    async with test_engine.begin() as conn:
        await conn.run_sync(SQLModel.metadata.drop_all)

    await test_engine.dispose()


@pytest.fixture(name="db_session")
async def fixture_db_session(
    async_engine: AsyncEngine,
) -> AsyncGenerator[AsyncSession, None]:
    """Фикстура сессии базы данных с откатом после теста."""
    session_factory = async_sessionmaker(
        bind=async_engine,
        class_=AsyncSession,
        expire_on_commit=False,
        autoflush=False,
    )
    async with session_factory() as session:
        yield session


@pytest.fixture(name="client")
async def fixture_client(
    db_session: AsyncSession,
) -> AsyncGenerator[AsyncClient, None]:
    """Асинхронный клиент для тестирования HTTP-эндпоинтов FastAPI."""

    async def override_get_session() -> AsyncGenerator[AsyncSession, None]:
        yield db_session

    app.dependency_overrides[get_session] = override_get_session

    transport = ASGITransport(app=app)
    async with AsyncClient(
        transport=transport,
        base_url="http://testserver",
    ) as ac:
        yield ac

    app.dependency_overrides.clear()
