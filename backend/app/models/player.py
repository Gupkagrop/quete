"""Модель игрока (Player) для гостевой авторизации с PIN-кодом."""

import uuid
from datetime import UTC, datetime

from sqlalchemy import Column, DateTime, func
from sqlmodel import Field, SQLModel


def get_utc_now() -> datetime:
    """Возвращает текущую временную метку в UTC."""
    return datetime.now(UTC)


class Player(SQLModel, table=True):
    """Сущность игрока в реляционной базе данных."""

    __tablename__ = "player"

    id: uuid.UUID = Field(
        default_factory=uuid.uuid4,
        primary_key=True,
        index=True,
        nullable=False,
        description="Уникальный идентификатор игрока (UUID)",
    )
    nickname: str = Field(
        max_length=50,
        index=True,
        nullable=False,
        description="Публичный никнейм игрока",
    )
    pin_hash: str = Field(
        max_length=255,
        nullable=False,
        description="Хэш 6-значного PIN-кода для восстановления сессии (bcrypt)",
    )
    created_at: datetime = Field(
        default_factory=get_utc_now,
        sa_column=Column(
            DateTime(timezone=True),
            nullable=False,
            server_default=func.now(),
        ),
        description="Дата и время создания записи",
    )
    updated_at: datetime = Field(
        default_factory=get_utc_now,
        sa_column=Column(
            DateTime(timezone=True),
            nullable=False,
            server_default=func.now(),
            onupdate=func.now(),
        ),
        description="Дата и время последнего обновления записи",
    )
