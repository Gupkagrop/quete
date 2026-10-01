"""Создание таблицы player

Revision ID: 001
Revises:
Create Date: 2026-10-01 12:35:00.000000

"""

from collections.abc import Sequence

import sqlalchemy as sa
import sqlmodel

from alembic import op

# Идентификаторы ревизий Alembic
revision: str = "001"
down_revision: str | None = None
branch_labels: str | Sequence[str] | None = None
depends_on: str | Sequence[str] | None = None


def upgrade() -> None:
    """Применение миграции: создание таблицы player."""
    op.create_table(
        "player",
        sa.Column("id", sa.Uuid(), nullable=False),
        sa.Column(
            "nickname",
            sqlmodel.sql.sqltypes.AutoString(length=50),
            nullable=False,
        ),
        sa.Column(
            "pin_hash",
            sqlmodel.sql.sqltypes.AutoString(length=255),
            nullable=False,
        ),
        sa.Column(
            "created_at",
            sa.DateTime(timezone=True),
            server_default=sa.text("now()"),
            nullable=False,
        ),
        sa.Column(
            "updated_at",
            sa.DateTime(timezone=True),
            server_default=sa.text("now()"),
            nullable=False,
        ),
        sa.PrimaryKeyConstraint("id"),
    )
    op.create_index(op.f("ix_player_id"), "player", ["id"], unique=False)
    op.create_index(op.f("ix_player_nickname"), "player", ["nickname"], unique=False)


def downgrade() -> None:
    """Откат миграции: удаление таблицы player."""
    op.drop_index(op.f("ix_player_nickname"), table_name="player")
    op.drop_index(op.f("ix_player_id"), table_name="player")
    op.drop_table("player")
