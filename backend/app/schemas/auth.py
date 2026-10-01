"""Pydantic-схемы для запросов и ответов авторизации."""

import uuid
from datetime import datetime

from pydantic import BaseModel, Field


class GuestLoginRequest(BaseModel):
    """Схема запроса гостевого входа или регистрации."""

    nickname: str = Field(
        ...,
        min_length=2,
        max_length=20,
        pattern=r"^[a-zA-Z0-9_-]{2,20}$",
        description=(
            "Никнейм игрока (2-20 символов, латиница, цифры, дефис, подчеркивание)"
        ),
        examples=["RetroPlayer99"],
    )
    pin_code: str | None = Field(
        default=None,
        pattern=r"^[0-9]{6}$",
        description="6-значный PIN-код для повторного входа существующего игрока",
        examples=["123456"],
    )


class RefreshTokenRequest(BaseModel):
    """Схема запроса на обновление пары токенов."""

    refresh_token: str = Field(
        ...,
        min_length=10,
        description="Refresh-токен для ротации",
    )


class PlayerRead(BaseModel):
    """Схема профиля игрока для возврата клиенту."""

    id: uuid.UUID = Field(description="UUID игрока")
    nickname: str = Field(description="Никнейм игрока")
    pin_code: str | None = Field(
        default=None,
        description="Открытый PIN-код (передаётся только при первой генерации)",
    )
    created_at: datetime = Field(description="Дата создания аккаунта")


class AuthResponse(BaseModel):
    """Схема успешного ответа авторизации с токенами и профилем."""

    access_token: str = Field(description="JWT access-токен (60 мин)")
    refresh_token: str = Field(description="Opaque refresh-токен (30 дней)")
    token_type: str = Field(default="bearer", description="Тип токена")
    player: PlayerRead = Field(description="Данные игрока")
