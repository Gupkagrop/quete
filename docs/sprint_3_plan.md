---
request_feedback: true
user_facing: true
---

# Спринт 3: Фича «Лобби и WebSockets» (День 15–21)

## Goal Description
Цель третьего спринта — создать масштабируемую систему игровых комнат (лобби) и внедрить отказоустойчивую шину реального времени на базе WebSockets и Redis Pub/Sub. Игроки смогут создавать комнаты, присоединяться по 4-буквенному коду, безопасно аутентифицироваться в сокете, видеть участников и статус хоста в реальном времени, а также мгновенно восстанавливать стейт при обрывах связи.

---

## 1. Архитектурные решения

### 1.1 Модель комнаты, сборка мусора (Zombie Rooms) и передача хоста
- Код комнаты (`room_code`): уникальный 4–6 буквенный код в верхнем регистре (`GAME`, `QUET`).
- Создатель комнаты становится хостом (`host_id = player.id`).
- **Сборка мусора (Zombie Rooms):** Если все игроки покинули комнату или комната неактивна более 2 часов, фоновый процесс удаляет её из БД (каскадно) и очищает Redis.
- **Атомарная передача хоста:** При отключении хоста права автоматически передаются старейшему активному игроку с детерминированной сортировкой по `created_at` (микросекунды).

### 1.2 Безопасный WebSocket Handshake и Redis Pub/Sub
- **Безопасное подключение:** Сокет открывается по `ws://<host>/ws/{room_code}` (без токена в URL!).
- Первым сообщением клиент отправляет:
  ```json
  {
    "type": "auth",
    "payload": {"token": "<access_token>"}
  }
  ```
- **Redis Pub/Sub для мульти-воркерности:** `ConnectionManager` подписывается на канал `room:{room_code}:channel`. Сообщения рассылаются через Redis Pub/Sub, что обеспечивает доставку между несколькими процессами Uvicorn.
- **Heartbeat:** `ping`/`pong` каждые 15 сек. Сервер обновляет Redis-ключ `online:{player_id}` (TTL = 30 сек).

### 1.3 Синхронизация состояния (State Recovery)
- REST эндпоинт `GET /rooms/{code}/state`: возвращает полный снимок состояния комнаты и игры.
- При повторном подключении клиента после разрыва связи он вызывает `GET /rooms/{code}/state` перед обработкой потоковых событий, исключая рассинхронизацию.

---

## 2. Предлагаемые изменения по файлам

### Backend (`backend/`)
- `app/models/room.py`: SQLModel `GameRoom` (`id`, `room_code`, `host_id`, `status`, `max_players = 8`, timestamps).
- `app/websockets/connection_manager.py`: класс `ConnectionManager` с интеграцией Redis Pub/Sub для широковещательной рассылки.
- `app/api/rooms.py`: REST эндпоинты `POST /rooms`, `POST /rooms/{code}/join`, `GET /rooms/{code}`, `GET /rooms/{code}/state`.
- `app/websockets/router.py`: WebSocket эндпоинт `/ws/{room_code}` с обязательным шагом `auth` handshake.
- `app/services/room_cleaner.py`: фоновая очистка пустых комнат.

### Client (`client/`)
- `core/websocket/websocket_service.dart`: сервис управления сокетом с auth handshake и exponential backoff (1s, 2s, 4s... max 30s).
- `state/lobby_provider.dart`: Riverpod провайдер состояния лобби (`LobbyState: code, players, isHost, status`).
- `ui/screens/lobby/lobby_screen.dart`: 8-bit экран комнаты (крупный код комнаты, список игроков с индикатором хоста, кнопка «СТАРТ» у хоста).
- Маршрутизация: добавление пути `/lobby/:code` в `go_router`.

---

## 3. План верификации
- **Pytest:** проверка создания комнаты, лимита 8 игроков, передачи хоста, handshake авторизации сокета, рассылки через Redis Pub/Sub, эндпоинта `GET /rooms/{code}/state`.
- **Flutter test:** юнит-тесты `WebSocketService` и `LobbyProvider`, виджет-тест экрана лобби.
