"""Модуль криптографии, токенов и безопасности."""

import secrets
import uuid
from datetime import UTC, datetime, timedelta
from typing import Any

import bcrypt
import jwt

from app.core.config import settings


def get_password_hash(secret: str) -> str:
    """Генерирует криптографический хэш строки с солью через bcrypt."""
    salt = bcrypt.gensalt()
    return bcrypt.hashpw(secret.encode("utf-8"), salt).decode("utf-8")


def verify_password(plain_secret: str, hashed_secret: str) -> bool:
    """Проверяет соответствие открытой строки её bcrypt-хэшу."""
    return bcrypt.checkpw(
        plain_secret.encode("utf-8"),
        hashed_secret.encode("utf-8"),
    )


def generate_pin_code() -> str:
    """Генерирует случайный 6-значный PIN-код (от 000000 до 999999)."""
    random_int: int = secrets.randbelow(1_000_000)
    return f"{random_int:06d}"


def create_access_token(
    player_id: uuid.UUID | str,
    nickname: str,
    expires_delta: timedelta | None = None,
) -> str:
    """Создаёт подписанный JWT access-токен для игрока."""
    now: datetime = datetime.now(UTC)
    if expires_delta is not None:
        expire: datetime = now + expires_delta
    else:
        expire = now + timedelta(minutes=settings.ACCESS_TOKEN_EXPIRE_MINUTES)

    payload: dict[str, Any] = {
        "sub": str(player_id),
        "nickname": nickname,
        "type": "access",
        "iat": int(now.timestamp()),
        "exp": int(expire.timestamp()),
    }

    return jwt.encode(
        payload,
        settings.JWT_SECRET_KEY,
        algorithm=settings.JWT_ALGORITHM,
    )


def create_refresh_token() -> str:
    """Создаёт криптографически стойкий случайный refresh-токен (UUID4)."""
    return str(uuid.uuid4())


def decode_access_token(token: str) -> dict[str, Any] | None:
    """Декодирует и верифицирует JWT токен.

    Возвращает словарь claims или None в случае ошибки/истечения срока действия.
    """
    try:
        payload: dict[str, Any] = jwt.decode(
            token,
            settings.JWT_SECRET_KEY,
            algorithms=[settings.JWT_ALGORITHM],
        )
        if payload.get("type") != "access":
            return None
        return payload
    except (jwt.PyJWTError, ValueError):
        return None
