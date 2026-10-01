# Задачи Спринта 3: Фича «Лобби и WebSockets» (День 15–21)

### 1. Модели базы данных и миграции (Backend)
- [ ] Backend: Создать SQLModel-модель `GameRoom` (`room_code`, `host_id`, `status`, `max_players`, timestamps)
- [ ] Backend: Сгенерировать и применить миграцию Alembic для таблицы `game_room`

### 2. REST API комнат и State Recovery (Backend)
- [ ] Backend: Реализовать эндпоинт `POST /rooms` (создание комнаты с генерацией уникального кода)
- [ ] Backend: Реализовать эндпоинт `POST /rooms/{code}/join` (проверка лимита 8 игроков, добавление в лобби)
- [ ] Backend: Реализовать эндпоинт `GET /rooms/{code}` (получение базовой информации о лобби)
- [ ] Backend: Реализовать эндпоинт `GET /rooms/{code}/state` (полный снимок состояния лобби/игры для State Recovery)
- [ ] Backend: Реализовать фоновый сервис очистки неактивных комнат (Zombie Rooms)

### 3. WebSocket инфраструктура и Redis Pub/Sub (Backend)
- [ ] Backend: Реализовать `ConnectionManager` с поддержкой распределённой рассылки через **Redis Pub/Sub**
- [ ] Backend: Реализовать WebSocket эндпоинт `/ws/{room_code}` без токена в URL
- [ ] Backend: Реализовать аутентификационный Handshake: приём первого сообщения `{"type": "auth", "payload": {"token": "..."}}`
- [ ] Backend: Реализовать обработку событий `player_joined`, `player_left`, `host_transferred` и `ping/pong` с обновлением `online:{player_id}` в Redis

### 4. WebSocket сервис и состояние (Client)
- [ ] Client: Реализовать `WebSocketService` с отправкой сообщения `auth` сразу после подключения и exponential backoff при сбоях
- [ ] Client: Создать модель данных `RoomState` и `LobbyPlayer` в `models/room.dart`
- [ ] Client: Реализовать `LobbyProvider` (Riverpod) для синхронизации сокета с UI
- [ ] Client: Реализовать вызов `GET /rooms/{code}/state` после успешного реконнекта сокета

### 5. UI лобби (Client)
- [ ] Client: Настроить маршрут `/lobby/:code` в `go_router`
- [ ] Client: Сверстать 8-bit экран `LobbyScreen` (код комнаты, кнопка копирования, список игроков, бейдж хоста)
- [ ] Client: Реализовать кнопку «СТАРТ ИГРЫ» с валидацией минимального количества игроков (от 2 чел)

### 6. Тестирование и верификация (Pre-Commit Gate)
- [ ] Backend: Написать pytest тесты для создания комнат, подключения и передачи хоста
- [ ] Backend: Написать тесты на WebSocket auth handshake и отказ при отсутствии/невалидности токена
- [ ] Backend: Написать асинхронный тест для WebSocket broadcast рассылки через Redis Pub/Sub
- [ ] Backend: Написать pytest тест для эндпоинта `GET /rooms/{code}/state`
- [ ] Client: Написать unit-тесты для `WebSocketService` и `LobbyProvider`
- [ ] Client: Написать widget-тест экрана `LobbyScreen`
- [ ] Верификация: `uv run ruff check .`, `uv run pytest`, `flutter analyze`, `flutter test`
