---
request_feedback: true
user_facing: true
---

# Спринт 3: Фича «Лобби и WebSockets» (День 15–21)

## Goal Description
Цель третьего спринта — создать систему комнат (лобби) и внедрить двустороннюю связь в реальном времени через WebSockets. Игроки смогут создавать комнаты, присоединяться по 4-буквенному коду комнаты, видеть подключенных участников и статус хоста в реальном времени.

---

## 1. Архитектурные решения

### 1.1 Модель комнаты и передача хоста
- Код комнаты (`room_code`): уникальный 4–6 буквенный код в верхнем регистре (например, `GAME`, `QUET`).
- Создатель комнаты автоматически становится хостом (`host_id = player.id`).
- При отключении хоста права хоста автоматически делегируются следующему старейшему игроку в комнате (`host_transferred`).

### 1.2 WebSocket Протокол и Envelope
- Единый формат всех сообщений: `{"type": str, "payload": dict}`.
- События:
  - `player_joined`: оповещение о входе нового игрока.
  - `player_left`: оповещение об отключении игрока.
  - `host_transferred`: оповещение о смене хоста.
  - `ping` / `pong`: поддержание соединения (heartbeat каждые 15 сек).

---

## 2. Предлагаемые изменения по файлам

### Backend (`backend/`)
- `app/models/room.py`: SQLModel `GameRoom` (`id: UUID`, `room_code: str`, `host_id: UUID`, `status: RoomStatus`, `max_players: int = 8`).
- `app/websockets/connection_manager.py`: класс `ConnectionManager` для хранения активных сокетов по комнатам и рассылки broadcast-сообщений.
- `app/api/rooms.py`: REST эндпоинты `POST /rooms`, `POST /rooms/{code}/join`, `GET /rooms/{code}`.
- `app/websockets/router.py`: WebSocket эндпоинт `/ws/{room_code}` с аутентификацией по JWT токену.

### Client (`client/`)
- `core/websocket/websocket_service.dart`: синглтон/сервис управления сокетом с авто-реконнектом (exponential backoff: 1s, 2s, 4s... max 30s).
- `state/lobby_provider.dart`: Riverpod провайдер состояния лобби (`LobbyState: code, players, isHost, status`).
- `ui/screens/lobby/lobby_screen.dart`: 8-bit экран комнаты (крупный код комнаты, список игроков с индикатором хоста, кнопка «СТАРТ» у хоста).
- Маршрутизация: добавление пути `/lobby/:code` в `go_router`.

---

## 3. План верификации
- **Pytest:** проверка создания комнаты, валидация лимита в 8 игроков, асинхронный тест WebSocket с 3 клиентами.
- **Flutter test:** виджет-тест экрана лобби, проверка отображения бейджа хоста и списка игроков.
