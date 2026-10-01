# API Architecture — Quete

> [!NOTE]
> Контракты REST-эндпоинтов, WebSocket-протокол и политики безопасности проекта Quete.
> Актуализировано по результатам Adversarial Architecture Review (Спринт 1–10).

## Base URL
```text
http://localhost:8000   # local dev
https://api.quete.app  # production
```

---

## 1. REST Endpoints

### 1.1 Health & Service
| Метод | Путь | Описание |
|---|---|---|
| GET | `/health` | Проверка доступности сервиса и подключения к БД/Redis |

**Response 200:**
```json
{
  "status": "ok",
  "database": "connected",
  "redis": "connected"
}
```

### 1.2 Auth & Sessions (Спринт 2)
| Метод | Путь | Описание |
|---|---|---|
| POST | `/auth/guest` | Гостевой вход / регистрация с 6-значным PIN-кодом |
| POST | `/auth/refresh` | Обновление пары access/refresh токенов (с Grace Period) |
| GET | `/auth/me` | Получение профиля текущего аутентифицированного игрока |

#### POST `/auth/guest`
**Request Body:**
```json
{
  "nickname": "RetroPlayer99",
  "pin_code": "123456" 
}
```
*Правила валидации:*
- `nickname`: regex `^[a-zA-Z0-9_-]{2,20}$` (санитария от XSS и управляющих символов).
- `pin_code`: строка ровно из 6 цифр `^[0-9]{6}$` (опционально при первой регистрации; обязателен для повторного входа).

*Rate Limiting:*
- Ограничение: максимум 5 попыток ввода PIN в минуту на IP и nickname.
- Блокировка на 15 минут при превышении лимита (HTTP 429 Too Many Requests).

**Response 200 (Success):**
```json
{
  "access_token": "eyJhbGciOi...",
  "refresh_token": "a1b2c3d4-e5f6-...",
  "token_type": "bearer",
  "player": {
    "id": "3fa85f64-5717-4562-b3fc-2c963f66afa6",
    "nickname": "RetroPlayer99",
    "pin_code": "123456"
  }
}
```

#### POST `/auth/refresh`
**Request Body:**
```json
{
  "refresh_token": "a1b2c3d4-e5f6-..."
}
```
*Grace Period (30–60 сек):* Позволяет обработать параллельные/повторные запросы с мобильных устройств при потере пакетов без ложного разлогина.

---

### 1.3 Lobby & Rooms (Спринт 3)
| Метод | Путь | Описание |
|---|---|---|
| POST | `/rooms` | Создать новую комнату (хост) |
| POST | `/rooms/{code}/join` | Присоединиться к существующей комнате |
| GET | `/rooms/{code}` | Получить базовую информацию о лобби |
| GET | `/rooms/{code}/state` | Получить полный снимок состояния комнаты и игры (State Recovery при реконнекте) |

#### GET `/rooms/{code}/state` (State Recovery)
**Headers:** `Authorization: Bearer <access_token>`
**Response 200:**
```json
{
  "room_code": "GAME",
  "status": "in_progress",
  "current_round": 2,
  "total_rounds": 5,
  "phase": "BLUFF_SUBMISSION",
  "phase_ends_at": "2026-10-01T12:00:45Z",
  "server_time": "2026-10-01T12:00:10Z",
  "question": {
    "text": "Какое необычное блюдо подавали на пирах в Древнем Риме?"
  },
  "player_status": {
    "has_submitted": true,
    "has_voted": false
  },
  "players": [
    {"id": "...", "nickname": "Player1", "score": 1000, "is_host": true, "online": true}
  ]
}
```

---

## 2. WebSocket Protocol & Handshake

> [!CAUTION]
> **Передача JWT в URL-параметрах (`?token=...`) строго запрещена!**
> Токены в query-параметрах логируются веб-серверами, прокси и балансировщиками.

### 2.1 Подключение и Handshake
**URL:** `ws://localhost:8000/ws/{room_code}` (без query-параметров авторизации).

Сразу после открытия соединения клиент **обязан** в течение 5 секунд отправить аутентификационный payload:
```json
{
  "type": "auth",
  "payload": {
    "token": "<access_token>"
  }
}
```

- Если токен валиден, сервер отвечает:
  ```json
  {
    "type": "auth_ok",
    "payload": {
      "player_id": "3fa85f64-5717-4562-b3fc-2c963f66afa6",
      "nickname": "RetroPlayer99"
    }
  }
  ```
- Если токен невалиден или истёк, сокет закрывается с кодом `4001 (Unauthorized)`.

### 2.2 Архитектура рассылки (Redis Pub/Sub)
Для поддержки нескольких Uvicorn-воркеров и масштабируемости:
1. Сервер подписан на канал Redis `room:{room_code}:channel`.
2. Любое сообщение для комнаты публикуется в Redis Pub/Sub и доставляется клиентам на всех воркерах.

### 2.3 Envelope Формат
Все сообщения между клиентом и сервером используют строгий JSON-контракт:
```json
{
  "type": "<event_type>",
  "payload": {}
}
```

### 2.4 Спецификация событий
| Type | Направление | Описание | Защита от читерства / правила |
|---|---|---|---|
| `auth` | Client->Server | Передача JWT при подключении | Обязателен первым сообщением |
| `auth_ok` | Server->Client | Успешная аутентификация сокета | Подтверждение подключения |
| `player_joined` | Server->Client | Новый игрок вошёл в комнату | Список участников обновляется |
| `player_left` | Server->Client | Игрок отключился | При уходе хоста хост передаётся |
| `host_transferred`| Server->Client | Смена хоста | Передача прав старейшему игроку |
| `topic_selected` | Server->Client | Выбрана тема игры | Инициируется только хостом |
| `game_started` | Server->Client | Старт матча | Переход в `QUESTION_READING` |
| `phase_started` | Server->Client | Старт новой фазы с `ends_at` | Клиенты синхронизируют таймер |
| `player_submitted`| Server->Client | Игрок ввёл ложный ответ | **Payload содержит ТОЛЬКО `player_id`** (текст ответа не раскрывается!) |
| `start_voting` | Server->Client | Варианты для голосования | Варианты перемешаны, авторы скрыты |
| `round_results` | Server->Client | Итоги раунда, раскрытие карт | Авторы, голоса, дельта очков |
| `ping` / `pong` | Двустороннее | Heartbeat каждые 15 сек | Обновляет `online:{player_id}` |

---

## 3. Обработка ошибок (Error Responses)
Формат ошибок REST API:
```json
{
  "detail": "Описание ошибки",
  "code": "ERROR_CODE"
}
```
Коды: `400` (Validation), `401` (Unauthorized), `403` (Forbidden/Host only), `404` (Not Found), `429` (Rate Limited), `500` (Internal Error).
