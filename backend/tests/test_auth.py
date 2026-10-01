"""Тесты функционала авторизации, сессий и Rate Limiting (Спринт 2)."""

import pytest
from httpx import AsyncClient


@pytest.mark.asyncio
async def test_guest_registration_success(client: AsyncClient) -> None:
    """Проверка успешной регистрации нового гостевого игрока."""
    response = await client.post(
        "/auth/guest",
        json={"nickname": "PixelKnight"},
    )
    assert response.status_code == 200
    data = response.json()

    assert "access_token" in data
    assert "refresh_token" in data
    assert data["token_type"] == "bearer"
    assert data["player"]["nickname"] == "PixelKnight"
    assert data["player"]["pin_code"] is not None
    assert len(data["player"]["pin_code"]) == 6
    assert data["player"]["pin_code"].isdigit()


@pytest.mark.asyncio
async def test_guest_login_existing_player_success(client: AsyncClient) -> None:
    """Проверка повторного входа существующего игрока по PIN-коду."""
    # Регистрация
    reg_res = await client.post("/auth/guest", json={"nickname": "ArcadeHero"})
    assert reg_res.status_code == 200
    pin_code = reg_res.json()["player"]["pin_code"]

    # Вход с корректным PIN
    login_res = await client.post(
        "/auth/guest",
        json={"nickname": "ArcadeHero", "pin_code": pin_code},
    )
    assert login_res.status_code == 200
    login_data = login_res.json()
    assert login_data["player"]["nickname"] == "ArcadeHero"
    assert login_data["player"]["pin_code"] is None  # Не выдаётся повторно


@pytest.mark.asyncio
async def test_guest_login_wrong_pin_fails(client: AsyncClient) -> None:
    """Проверка ошибки 401 при неверном PIN-коде для существующего игрока."""
    await client.post("/auth/guest", json={"nickname": "CyberWizard"})

    # Попытка входа с неверным PIN
    res = await client.post(
        "/auth/guest",
        json={"nickname": "CyberWizard", "pin_code": "000000"},
    )
    assert res.status_code == 401
    assert "Неверный PIN-код" in res.json()["detail"]


@pytest.mark.asyncio
async def test_guest_login_missing_pin_fails(client: AsyncClient) -> None:
    """Проверка ошибки 400 при отсутствии PIN-кода для зарегистрированного ника."""
    await client.post("/auth/guest", json={"nickname": "RetroGamer"})

    res = await client.post("/auth/guest", json={"nickname": "RetroGamer"})
    assert res.status_code == 400
    assert "уже зарегистрирован" in res.json()["detail"]


@pytest.mark.asyncio
async def test_pin_rate_limiting_lockout(client: AsyncClient) -> None:
    """Проверка срабатывания Rate Limiting (429) после 5 неверных попыток ввода PIN."""
    await client.post("/auth/guest", json={"nickname": "TargetAccount"})

    # Совершаем 5 неудачных попыток ввода PIN
    for _ in range(5):
        res = await client.post(
            "/auth/guest",
            json={"nickname": "TargetAccount", "pin_code": "999999"},
        )
        assert res.status_code == 401

    # 6-я попытка должна быть заблокирована по Rate Limit (429 Too Many Requests)
    lockout_res = await client.post(
        "/auth/guest",
        json={"nickname": "TargetAccount", "pin_code": "999999"},
    )
    assert lockout_res.status_code == 429
    assert "Слишком много попыток" in lockout_res.json()["detail"]
    assert "Retry-After" in lockout_res.headers


@pytest.mark.asyncio
async def test_token_rotation_and_grace_period(client: AsyncClient) -> None:
    """Проверка ротации refresh-токена и работы Grace Period для мобильной сети."""
    reg = await client.post("/auth/guest", json={"nickname": "SpeedRunner"})
    refresh_token = reg.json()["refresh_token"]

    # 1. Ротация токена
    refresh_res = await client.post(
        "/auth/refresh",
        json={"refresh_token": refresh_token},
    )
    assert refresh_res.status_code == 200
    new_data = refresh_res.json()
    new_refresh = new_data["refresh_token"]
    assert new_refresh != refresh_token

    # 2. Повторный запрос со старым токеном в пределах Grace Period
    # должен также успешно отработать
    grace_res = await client.post(
        "/auth/refresh",
        json={"refresh_token": refresh_token},
    )
    assert grace_res.status_code == 200


@pytest.mark.asyncio
async def test_get_me_profile(client: AsyncClient) -> None:
    """Проверка защищённого эндпоинта /auth/me."""
    reg = await client.post("/auth/guest", json={"nickname": "LordHelmet"})
    access_token = reg.json()["access_token"]

    # Успешный запрос с валидным Bearer токеном
    me_res = await client.get(
        "/auth/me",
        headers={"Authorization": f"Bearer {access_token}"},
    )
    assert me_res.status_code == 200
    assert me_res.json()["nickname"] == "LordHelmet"

    # Запрос без токена
    unauth_res = await client.get("/auth/me")
    assert unauth_res.status_code == 401

    # Запрос с битым токеном
    bad_token_res = await client.get(
        "/auth/me",
        headers={"Authorization": "Bearer invalid.jwt.token"},
    )
    assert bad_token_res.status_code == 401


@pytest.mark.asyncio
async def test_nickname_validation(client: AsyncClient) -> None:
    """Проверка отклонения невалидных никнеймов по регулярному выражению."""
    # Никнейм со скриптом / спецсимволами
    res_xss = await client.post(
        "/auth/guest",
        json={"nickname": "<script>alert(1)</script>"},
    )
    assert res_xss.status_code == 422

    # Никнейм с пробелами
    res_spaces = await client.post(
        "/auth/guest",
        json={"nickname": "Bad Nick"},
    )
    assert res_spaces.status_code == 422

    # Слишком короткий никнейм
    res_short = await client.post(
        "/auth/guest",
        json={"nickname": "A"},
    )
    assert res_short.status_code == 422
