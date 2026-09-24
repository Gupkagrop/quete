"""Окружение для выполнения миграций Alembic с поддержкой SQLModel и AsyncEngine."""

import asyncio
from logging.config import fileConfig

from sqlalchemy import pool
from sqlalchemy.engine import Connection
from sqlalchemy.ext.asyncio import async_engine_from_config
from sqlmodel import SQLModel

from alembic import context
from app.db.database import DATABASE_URL

# Объект конфигурации Alembic из alembic.ini
config = context.config

# Настройка логирования на основе конфигурационного файла
if config.config_file_name is not None:
    fileConfig(config.config_file_name)

# Установка URL подключения из конфигурации приложения
if DATABASE_URL:
    config.set_main_option("sqlalchemy.url", DATABASE_URL)

# Метаданные моделей SQLModel для отслеживания схемы и автогенерации миграций
target_metadata = SQLModel.metadata


def run_migrations_offline() -> None:
    """Запуск миграций в offline-режиме (генерация SQL-скрипта без подключения к БД)."""
    url: str | None = config.get_main_option("sqlalchemy.url")
    context.configure(
        url=url,
        target_metadata=target_metadata,
        literal_binds=True,
        dialect_opts={"paramstyle": "named"},
    )

    with context.begin_transaction():
        context.run_migrations()


def do_run_migrations(connection: Connection) -> None:
    """Применение миграций с использованием переданного соединения."""
    context.configure(connection=connection, target_metadata=target_metadata)

    with context.begin_transaction():
        context.run_migrations()


async def run_async_migrations() -> None:
    """Создание асинхронного движка и выполнение миграций."""
    connectable = async_engine_from_config(
        config.get_section(config.config_ini_section, {}),
        prefix="sqlalchemy.",
        poolclass=pool.NullPool,
    )

    async with connectable.connect() as connection:
        await connection.run_sync(do_run_migrations)

    await connectable.dispose()


def run_migrations_online() -> None:
    """Запуск миграций в online-режиме с асинхронным подключением."""
    asyncio.run(run_async_migrations())


if context.is_offline_mode():
    run_migrations_offline()
else:
    run_migrations_online()
