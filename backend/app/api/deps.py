"""Зависимости внедрения для эндпоинтов FastAPI."""

import uuid

from fastapi import Depends, HTTPException, status
from fastapi.security import HTTPAuthorizationCredentials, HTTPBearer
from sqlmodel import select
from sqlmodel.ext.asyncio.session import AsyncSession

from app.core.security import decode_access_token
from app.db.database import get_session
from app.models.player import Player

# Схема извлечения Bearer-токена из заголовка Authorization
security_scheme = HTTPBearer(auto_error=False)


async def get_current_player(
    credentials: HTTPAuthorizationCredentials | None = Depends(security_scheme),
    session: AsyncSession = Depends(get_session),
) -> Player:
    """Извлекает и верифицирует текущего игрока по Bearer JWT токену."""
    if credentials is None:
        raise HTTPException(
            status_code=status.HTTP_401_UNAUTHORIZED,
            detail="Отсутствует заголовок авторизации Authorization: Bearer <token>",
            headers={"WWW-Authenticate": "Bearer"},
        )

    token: str = credentials.credentials
    payload = decode_access_token(token)
    if payload is None:
        raise HTTPException(
            status_code=status.HTTP_401_UNAUTHORIZED,
            detail="Недействительный или просроченный токен авторизации",
            headers={"WWW-Authenticate": "Bearer"},
        )

    sub: str | None = payload.get("sub")
    if not sub:
        raise HTTPException(
            status_code=status.HTTP_401_UNAUTHORIZED,
            detail="В токене отсутствует идентификатор субъекта (sub)",
            headers={"WWW-Authenticate": "Bearer"},
        )

    try:
        player_id = uuid.UUID(sub)
    except ValueError:
        raise HTTPException(
            status_code=status.HTTP_401_UNAUTHORIZED,
            detail="Некорректный формат UUID игрока в токене",
            headers={"WWW-Authenticate": "Bearer"},
        ) from None

    statement = select(Player).where(Player.id == player_id)
    result = await session.exec(statement)
    player = result.first()

    if player is None:
        raise HTTPException(
            status_code=status.HTTP_401_UNAUTHORIZED,
            detail="Игрок, связанный с данным токеном, не найден",
            headers={"WWW-Authenticate": "Bearer"},
        )

    return player
