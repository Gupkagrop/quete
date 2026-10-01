"""Сервис управления сессиями, refresh-токенами и Rate Limiting в Redis."""

import json
import logging
import uuid
from datetime import UTC, datetime
from typing import Any

import redis.asyncio as aioredis

from app.core.config import settings

logger = logging.getLogger(__name__)


class SessionService:
    """Асинхронный сервис для работы с сессиями и защитой в Redis."""

    def __init__(self, redis_client: aioredis.Redis | None = None) -> None:
        """Инициализация сервиса с клиентом Redis."""
        self._redis = redis_client

    @property
    def redis(self) -> aioredis.Redis:
        """Ленивая инициализация подключения к Redis."""
        if self._redis is None:
            self._redis = aioredis.from_url(
                settings.REDIS_URL,
                decode_responses=True,
            )
        return self._redis

    async def store_refresh_token(
        self,
        player_id: uuid.UUID | str,
        refresh_token: str,
    ) -> None:
        """Сохраняет refresh-токен и обновляет сессию игрока."""
        p_id_str: str = str(player_id)
        ttl_seconds: int = settings.REFRESH_TOKEN_EXPIRE_DAYS * 24 * 3600

        # Сохраняем токен с запасом времени на Grace Period
        token_key: str = f"refresh_token:{refresh_token}"
        await self.redis.set(
            token_key,
            p_id_str,
            ex=ttl_seconds + settings.REFRESH_TOKEN_GRACE_PERIOD_SECONDS,
        )

        # Сохраняем сессионные метаданные игрока
        session_key: str = f"session:{p_id_str}"
        session_data: dict[str, Any] = {
            "player_id": p_id_str,
            "last_login": datetime.now(UTC).isoformat(),
            "active_refresh_token": refresh_token,
        }
        await self.redis.set(
            session_key,
            json.dumps(session_data),
            ex=ttl_seconds,
        )

    async def get_player_id_by_refresh_token(
        self,
        refresh_token: str,
    ) -> str | None:
        """Возвращает player_id по переданному refresh-токену."""
        token_key: str = f"refresh_token:{refresh_token}"
        data = await self.redis.get(token_key)
        if not data:
            return None

        # Поддержка Grace Period формата JSON или строки
        if data.startswith("{"):
            try:
                parsed = json.loads(data)
                return str(parsed.get("player_id"))
            except (json.JSONDecodeError, AttributeError):
                return None
        return str(data)

    async def rotate_refresh_token(
        self,
        old_token: str,
        new_token: str,
        player_id: uuid.UUID | str,
    ) -> None:
        """Выполняет ротацию токена с обеспечением Grace Period для мобильной сети."""
        p_id_str: str = str(player_id)
        old_token_key: str = f"refresh_token:{old_token}"
        new_token_key: str = f"refresh_token:{new_token}"
        ttl_seconds: int = settings.REFRESH_TOKEN_EXPIRE_DAYS * 24 * 3600

        # Сохраняем новый токен
        await self.redis.set(
            new_token_key,
            p_id_str,
            ex=ttl_seconds + settings.REFRESH_TOKEN_GRACE_PERIOD_SECONDS,
        )

        # Старый токен помечаем как ротированный на время Grace Period
        grace_data = json.dumps({
            "status": "rotated",
            "new_token": new_token,
            "player_id": p_id_str,
        })
        await self.redis.set(
            old_token_key,
            grace_data,
            ex=settings.REFRESH_TOKEN_GRACE_PERIOD_SECONDS,
        )

        # Обновляем сессию
        session_key: str = f"session:{p_id_str}"
        session_data: dict[str, Any] = {
            "player_id": p_id_str,
            "last_login": datetime.now(UTC).isoformat(),
            "active_refresh_token": new_token,
        }
        await self.redis.set(session_key, json.dumps(session_data), ex=ttl_seconds)

    async def revoke_refresh_token(
        self,
        refresh_token: str,
        player_id: uuid.UUID | str | None = None,
    ) -> None:
        """Отказывает refresh-токен и очищает сессию."""
        token_key: str = f"refresh_token:{refresh_token}"
        await self.redis.delete(token_key)

        if player_id is not None:
            session_key: str = f"session:{player_id}"
            await self.redis.delete(session_key)

    async def check_and_increment_pin_rate_limit(
        self,
        client_ip: str,
        nickname: str,
    ) -> tuple[bool, int]:
        """Проверяет и обновляет лимит попыток ввода PIN-кода.

        Возвращает:
            tuple[bool, int]: (allowed, retry_after_seconds).
        """
        lockout_key: str = f"ratelimit:pin:lockout:{client_ip}:{nickname}"
        ttl_remaining: int = await self.redis.ttl(lockout_key)
        if ttl_remaining > 0:
            return False, ttl_remaining

        attempts_key: str = f"ratelimit:pin:attempts:{client_ip}:{nickname}"
        current_attempts: int = await self.redis.incr(attempts_key)

        # При первой попытке устанавливаем окно подсчёта
        if current_attempts == 1:
            await self.redis.expire(
                attempts_key,
                settings.RATE_LIMIT_PIN_WINDOW_SECONDS,
            )

        if current_attempts > settings.RATE_LIMIT_PIN_MAX_ATTEMPTS:
            # Превышен лимит -> блокируем на 15 минут
            await self.redis.set(
                lockout_key,
                "locked",
                ex=settings.RATE_LIMIT_PIN_LOCKOUT_SECONDS,
            )
            await self.redis.delete(attempts_key)
            return False, settings.RATE_LIMIT_PIN_LOCKOUT_SECONDS

        return True, 0

    async def reset_pin_rate_limit(
        self,
        client_ip: str,
        nickname: str,
    ) -> None:
        """Сбрасывает счетчики попыток после успешного входа."""
        attempts_key: str = f"ratelimit:pin:attempts:{client_ip}:{nickname}"
        lockout_key: str = f"ratelimit:pin:lockout:{client_ip}:{nickname}"
        await self.redis.delete(attempts_key, lockout_key)

    async def set_online(self, player_id: uuid.UUID | str) -> None:
        """Устанавливает статус присутствия игрока в реальном времени."""
        online_key: str = f"online:{player_id}"
        await self.redis.set(online_key, "true", ex=30)

    async def is_online(self, player_id: uuid.UUID | str) -> bool:
        """Проверяет, находится ли игрок сейчас в онлайне."""
        online_key: str = f"online:{player_id}"
        val = await self.redis.get(online_key)
        return val is not None


session_service = SessionService()
