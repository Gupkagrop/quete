# Задачи Спринта 3: Фича «Лобби и WebSockets» (День 15–21)

### 1. Модели базы данных и миграции (Backend)
- [ ] Backend: Создать SQLModel-модель `GameRoom` (`room_code`, `host_id`, `status`, `max_players`)
- [ ] Backend: Сгенерировать и применить миграцию Alembic для таблицы `game_room`

### 2. REST API комнат (Backend)
- [ ] Backend: Реализовать эндпоинт `POST /rooms` (создание комнаты с генерацией уникального кода)
- [ ] Backend: Реализовать эндпоинт `POST /rooms/{code}/join` (проверка лимита 8 игроков, добавление в лобби)
- [ ] Backend: Реализовать эндпоинт `GET /rooms/{code}` (получение текущей информации о комнате)

### 3. WebSocket инфраструктура (Backend)
- [ ] Backend: Реализовать `ConnectionManager` для рассылки сообщений внутри комнаты
- [ ] Backend: Реализовать WebSocket эндпоинт `/ws/{room_code}` с проверкой JWT-токена
- [ ] Backend: Реализовать обработку событий `player_joined`, `player_left`, `host_transferred` и `ping/pong`

### 4. WebSocket сервис и состояние (Client)
- [ ] Client: Реализовать `WebSocketService` с exponential backoff при потере связи
- [ ] Client: Создать модель данных `RoomState` и `LobbyPlayer` в `models/room.dart`
- [ ] Client: Реализовать `LobbyProvider` (Riverpod) для синхронизации сокета с UI

### 5. UI лобби (Client)
- [ ] Client: Настроить маршрут `/lobby/:code` в `go_router`
- [ ] Client: Сверстать 8-bit экран `LobbyScreen` (код комнаты, кнопка копирования, список игроков, бейдж хоста)
- [ ] Client: Реализовать кнопку «СТАРТ ИГРЫ» с валидацией минимального количества игроков (от 2 чел)

### 6. Тестирование и верификация (Pre-Commit Gate)
- [ ] Backend: Написать pytest тесты для создания комнат, подключения и передачи хоста
- [ ] Backend: Написать асинхронный тест для WebSocket broadcast рассылки
- [ ] Client: Написать unit-тесты для `WebSocketService` и `LobbyProvider`
- [ ] Client: Написать widget-тест экрана `LobbyScreen`
- [ ] Верификация: `uv run ruff check .`, `uv run pytest`, `flutter analyze`, `flutter test`
