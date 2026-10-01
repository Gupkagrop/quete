"""Конфигурация настроек приложения Quete."""

import os
from dataclasses import dataclass


@dataclass(frozen=True)
class Settings:
    """Глобальные настройки приложения."""

    PROJECT_NAME: str = "Quete"
    API_V1_PREFIX: str = "/api/v1"

    # Безопасность и JWT
    JWT_SECRET_KEY: str = os.getenv(
        "JWT_SECRET_KEY",
        "quete_dev_secret_key_change_in_production_min_32_bytes_len!",
    )
    JWT_ALGORITHM: str = os.getenv("JWT_ALGORITHM", "HS256")
    ACCESS_TOKEN_EXPIRE_MINUTES: int = int(
        os.getenv("ACCESS_TOKEN_EXPIRE_MINUTES", "60")
    )
    REFRESH_TOKEN_EXPIRE_DAYS: int = int(
        os.getenv("REFRESH_TOKEN_EXPIRE_DAYS", "30")
    )
    REFRESH_TOKEN_GRACE_PERIOD_SECONDS: int = int(
        os.getenv("REFRESH_TOKEN_GRACE_PERIOD_SECONDS", "60")
    )

    # Защита от подбора PIN-кода (Rate Limiting)
    RATE_LIMIT_PIN_MAX_ATTEMPTS: int = int(
        os.getenv("RATE_LIMIT_PIN_MAX_ATTEMPTS", "5")
    )
    RATE_LIMIT_PIN_WINDOW_SECONDS: int = int(
        os.getenv("RATE_LIMIT_PIN_WINDOW_SECONDS", "60")
    )
    RATE_LIMIT_PIN_LOCKOUT_SECONDS: int = int(
        os.getenv("RATE_LIMIT_PIN_LOCKOUT_SECONDS", "900")
    )

    # База данных и кэш
    DATABASE_URL: str = os.getenv(
        "DATABASE_URL",
        "postgresql+asyncpg://quete_user:quete_password@localhost/quete_db",
    )
    REDIS_URL: str = os.getenv("REDIS_URL", "redis://localhost:6379/0")


settings = Settings()
