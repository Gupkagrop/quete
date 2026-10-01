"""REST API эндпоинты гостевой авторизации и сессий."""

import uuid

from fastapi import APIRouter, Depends, HTTPException, Request, status
from sqlmodel import select
from sqlmodel.ext.asyncio.session import AsyncSession

from app.api.deps import get_current_player
from app.core.security import (
    create_access_token,
    create_refresh_token,
    generate_pin_code,
    get_password_hash,
    verify_password,
)
from app.db.database import get_session
from app.models.player import Player
from app.schemas.auth import (
    AuthResponse,
    GuestLoginRequest,
    PlayerRead,
    RefreshTokenRequest,
)
from app.services.session_service import session_service

router = APIRouter(prefix="/auth", tags=["auth"])


@router.post(
    "/guest",
    response_model=AuthResponse,
    summary="Гостевой вход или регистрация",
    description=(
        "Регистрирует нового игрока с генерацией PIN-кода или "
        "осуществляет вход существующего по никнейму и PIN-коду."
    ),
)
async def guest_login(
    request: Request,
    payload: GuestLoginRequest,
    session: AsyncSession = Depends(get_session),
) -> AuthResponse:
    """Обрабатывает гостевой вход и регистрацию с защитой от брутфорса."""
    client_ip: str = request.client.host if request.client else "127.0.0.1"

    # Проверка лимита попыток ввода PIN-кода (Rate Limiting)
    allowed, retry_after = await session_service.check_and_increment_pin_rate_limit(
        client_ip=client_ip,
        nickname=payload.nickname,
    )
    if not allowed:
        raise HTTPException(
            status_code=status.HTTP_429_TOO_MANY_REQUESTS,
            detail=(
                "Слишком много попыток ввода PIN-кода. "
                f"Повторите попытку через {retry_after} сек."
            ),
            headers={"Retry-After": str(retry_after)},
        )

    # Поиск существующего игрока по никнейму
    statement = select(Player).where(Player.nickname == payload.nickname)
    result = await session.exec(statement)
    player = result.first()

    if player is not None:
        # Игрок уже существует -> обязателен ввод корректного PIN-кода
        if not payload.pin_code:
            raise HTTPException(
                status_code=status.HTTP_400_BAD_REQUEST,
                detail=(
                    "Игрок с таким никнеймом уже зарегистрирован. "
                    "Введите PIN-код для входа."
                ),
            )

        if not verify_password(payload.pin_code, player.pin_hash):
            raise HTTPException(
                status_code=status.HTTP_401_UNAUTHORIZED,
                detail="Неверный PIN-код для указанного никнейма.",
            )

        # Успешный вход существующего игрока -> сбрасываем счетчик ошибок
        await session_service.reset_pin_rate_limit(client_ip, payload.nickname)

        access_token: str = create_access_token(player.id, player.nickname)
        refresh_token: str = create_refresh_token()
        await session_service.store_refresh_token(player.id, refresh_token)

        return AuthResponse(
            access_token=access_token,
            refresh_token=refresh_token,
            player=PlayerRead(
                id=player.id,
                nickname=player.nickname,
                pin_code=None,
                created_at=player.created_at,
            ),
        )

    # Игрок не найден -> регистрируем нового гостя
    plain_pin: str = payload.pin_code if payload.pin_code else generate_pin_code()
    pin_hash: str = get_password_hash(plain_pin)

    new_player = Player(
        nickname=payload.nickname,
        pin_hash=pin_hash,
    )
    session.add(new_player)
    await session.commit()
    await session.refresh(new_player)

    await session_service.reset_pin_rate_limit(client_ip, payload.nickname)

    access_token = create_access_token(new_player.id, new_player.nickname)
    refresh_token = create_refresh_token()
    await session_service.store_refresh_token(new_player.id, refresh_token)

    return AuthResponse(
        access_token=access_token,
        refresh_token=refresh_token,
        player=PlayerRead(
            id=new_player.id,
            nickname=new_player.nickname,
            pin_code=plain_pin,
            created_at=new_player.created_at,
        ),
    )


@router.post(
    "/refresh",
    response_model=AuthResponse,
    summary="Обновление пары токенов",
    description=(
        "Обновляет access и refresh токены с поддержкой Grace Period "
        "для защиты от сетевых сбоев."
    ),
)
async def refresh_tokens(
    payload: RefreshTokenRequest,
    session: AsyncSession = Depends(get_session),
) -> AuthResponse:
    """Выполняет ротацию пары токенов по действующему refresh-токену."""
    player_id_str = await session_service.get_player_id_by_refresh_token(
        payload.refresh_token
    )
    if player_id_str is None:
        raise HTTPException(
            status_code=status.HTTP_401_UNAUTHORIZED,
            detail="Недействительный или отозванный refresh-токен.",
        )

    try:
        player_id = uuid.UUID(player_id_str)
    except ValueError:
        raise HTTPException(
            status_code=status.HTTP_401_UNAUTHORIZED,
            detail="Некорректный идентификатор сессии.",
        ) from None

    statement = select(Player).where(Player.id == player_id)
    result = await session.exec(statement)
    player = result.first()

    if player is None:
        raise HTTPException(
            status_code=status.HTTP_401_UNAUTHORIZED,
            detail="Игрок сессии не найден.",
        )

    new_access_token = create_access_token(player.id, player.nickname)
    new_refresh_token = create_refresh_token()

    await session_service.rotate_refresh_token(
        old_token=payload.refresh_token,
        new_token=new_refresh_token,
        player_id=player.id,
    )

    return AuthResponse(
        access_token=new_access_token,
        refresh_token=new_refresh_token,
        player=PlayerRead(
            id=player.id,
            nickname=player.nickname,
            pin_code=None,
            created_at=player.created_at,
        ),
    )


@router.get(
    "/me",
    response_model=PlayerRead,
    summary="Получение профиля текущего игрока",
    description="Возвращает профиль игрока по Bearer JWT токену.",
)
async def get_me(
    current_player: Player = Depends(get_current_player),
) -> PlayerRead:
    """Возвращает профиль аутентифицированного игрока."""
    return PlayerRead(
        id=current_player.id,
        nickname=current_player.nickname,
        pin_code=None,
        created_at=current_player.created_at,
    )
