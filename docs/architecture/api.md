# API Architecture — Quete

> [!NOTE]
> Контракты REST-эндпоинтов, полный WebSocket-протокол (Client<->Server) и политики безопасности проекта Quete.
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
*Валидация:*
- `nickname`: regex `^[a-zA-Z0-9_-]{2,20}$`.
- `pin_code`: строка из 6 цифр `^[0-9]{6}$` (опционально при первой регистрации).
- *Rate Limiting:* максимум 5 попыток ввода PIN в минуту на пару (IP, nickname), временная блокировка на 15 минут (429 Too Many Requests).

**Response 200:**
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
  "session_id": "8f3b6c2a-9e12-4f1a-b32c-123456789abc",
  "current_round": 2,
  "total_rounds": 5,
  "phase": "VOTING",
  "phase_ends_at": "2026-10-01T12:00:45Z",
  "server_time": "2026-10-01T12:00:10Z",
  "question": {
    "text": "Какое необычное блюдо подавали на пирах в Древнем Риме?"
  },
  "voting_options": [
    {"id": "c1a2b3d4-0001-...", "text": "Фламинго в меду"},
    {"id": "c1a2b3d4-0002-...", "text": "Жареные мыши"}
  ],
  "player_status": {
    "has_submitted": true,
    "has_voted": false,
    "is_host": false,
    "score": 1000
  },
  "players": [
    {"id": "...", "nickname": "Player1", "score": 1000, "is_host": true, "online": true}
  ]
}
```

---

## 2. WebSocket Protocol (Двусторонний контракт)

> [!CAUTION]
> **Передача JWT в URL-параметрах (`?token=...`) строго запрещена!**
> Токены в query-параметрах логируются веб-серверами, прокси и балансировщиками.

### 2.1 Подключение и Handshake
**URL:** `ws://localhost:8000/ws/{room_code}` (чистый URL).

Сразу после открытия соединения клиент **обязан** отправить аутентификационный payload:
```json
{
  "type": "auth",
  "payload": {
    "token": "<access_token>"
  }
}
```

- При успешной проверке сервер отвечает:
  ```json
  {
    "type": "auth_ok",
    "payload": {
      "player_id": "3fa85f64-5717-4562-b3fc-2c963f66afa6",
      "nickname": "RetroPlayer99"
    }
  }
  ```
- При ошибке сокет закрывается с кодом `4001 (Unauthorized)`.

---

### 2.2 Client -> Server События (Мутации)

Все исходящие сообщения от клиента используют единый Envelope-формат `{"type": str, "payload": dict}`.

#### 1. Выбор темы игры (хост)
```json
{
  "type": "select_topic",
  "payload": {
    "topic": "История Древнего Рима"
  }
}
```

#### 2. Старт игры (хост)
```json
{
  "type": "start_game",
  "payload": {}
}
```

#### 3. Отправка блефа (игрок)
```json
{
  "type": "submit_bluff",
  "payload": {
    "answer_text": "Павлиньи языки"
  }
}
```

#### 4. Голосование за вариант (игрок)
```json
{
  "type": "submit_vote",
  "payload": {
    "option_id": "c1a2b3d4-0001-4f1a-b32c-123456789abc"
  }
}
```

#### 5. Перезапуск матча / Реванш (хост)
```json
{
  "type": "rematch",
  "payload": {}
}
```

#### 6. Сервисный Heartbeat Ping
```json
{
  "type": "ping",
  "payload": {}
}
```

---

### 2.3 Server -> Client События (Широковещательные и сервисные)

| Type | Описание | Payload структура |
|---|---|---|
| `auth_ok` | Подтверждение авторизации сокета | `{"player_id": UUID, "nickname": str}` |
| `player_joined` | Новый игрок вошёл в комнату | `{"player": {"id": UUID, "nickname": str, "is_host": bool, "score": int}}` |
| `player_left` | Игрок отключился | `{"player_id": UUID}` |
| `host_transferred` | Смена хоста лобби | `{"new_host_id": UUID}` |
| `topic_selected` | Выбрана тема игры | `{"topic": str, "is_custom": bool}` |
| `phase_started` | Старт новой фазы раунда | `{"phase": str, "round_number": int, "ends_at": str, "duration_seconds": int, "question": {"text": str}}` |
| `player_submitted` | Игрок ввёл ложный ответ (текст скрыт!) | `{"player_id": UUID}` |
| `start_voting` | Старт фазы голосования | `{"options": [{"id": UUID, "text": str}], "ends_at": str, "duration_seconds": 30}` |
| `round_results` | Итоги раунда с раскрытием авторов | `{"correct_option_id": UUID, "breakdown": [...], "scores": [{"player_id": UUID, "score": int, "round_delta": int}]}` |
| `game_finished` | Завершение 5 раундов и итоговый пьедестал | `{"podium": [{"place": 1, "nickname": str, "score": int}, ...]}` |
| `error` | Ошибка валидации действия клиента | `{"code": str, "detail": str}` |
| `pong` | Ответ на Heartbeat Ping | `{}` |

---

## 3. Обработка ошибок (Error Codes)
- `BLUFF_MATCHES_TRUTH`: Введённый ответ совпадает с правдой или слишком близок к ней (Fuzzy Matching >80%).
- `CANNOT_VOTE_OWN_BLUFF`: Попытка проголосовать за свой собственный блеф (или объединённый ответ, где игрок соавтор).
- `ALREADY_VOTED`: Попытка проголосовать повторно в рамках одного раунда.
- `NOT_HOST`: Попытка не-хоста запустить игру, выбрать тему или начать реванш.
- `RATE_LIMIT_EXCEEDED`: Превышение частоты запросов к API.
