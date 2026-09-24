"""Модуль подключения к базе данных с использованием SQLModel и AsyncEngine."""

import os
from collections.abc import AsyncGenerator
from sqlalchemy.ext.asyncio import AsyncEngine, async_sessionmaker, create_async_engine
from sqlmodel import SQLModel
from sqlmodel.ext.asyncio.session import AsyncSession

# Конфигурация строки подключения по умолчанию
DEFAULT_DATABASE_URL: str = (
    "postgresql+asyncpg://quete_user:quete_password@localhost/quete_db"
)

# Чтение строки подключения из переменных окружения
DATABASE_URL: str = os.getenv("DATABASE_URL", DEFAULT_DATABASE_URL)

# Инициализация асинхронного движка базы данных
engine: AsyncEngine = create_async_engine(
    DATABASE_URL,
    echo=False,
    future=True,
)

# Фабрика асинхронных сессий
async_session_maker: async_sessionmaker[AsyncSession] = async_sessionmaker(
    bind=engine,
    class_=AsyncSession,
    expire_on_commit=False,
    autoflush=False,
)


async def get_session() -> AsyncGenerator[AsyncSession, None]:
    """Генератор асинхронных сессий для внедрения зависимостей в FastAPI."""
    async with async_session_maker() as session:
        yield session
