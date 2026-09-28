# API Architecture — Quete

> [!NOTE]
> Контракты REST-эндпоинтов и WebSocket-протокол проекта Quete.
> Актуален на Спринт 1 (скелет). Обновляется по мере добавления фич.

## Base URL
```text
http://localhost:8000   # local dev
https://api.quete.app  # production (планируется)
```

## REST Endpoints

### Health (реализован)
| Метод | Путь | Описание |
|---|---|---|
| GET | `/health` | Проверка доступности сервиса |

**Response 200:**
```json
{
  "status": "ok"
}
```

### Auth (Спринт 2 — планируется)
| Метод | Путь | Описание |
|---|---|---|
| POST | `/auth/guest` | Создать гостевую сессию (генерирует 6-значный код) |
| POST | `/auth/refresh` | Обновить access-токен по refresh-токену |

### Lobby (Спринт 3 — планируется)
| Метод | Путь | Описание |
|---|---|---|
| POST | `/rooms` | Создать комнату |
| POST | `/rooms/{code}/join` | Присоединиться к комнате |
| GET | `/rooms/{code}` | Получить состояние комнаты |

## Auth Flow

```text
Client -> POST /auth/guest
Server -> {access_token, refresh_token, player_id, nickname, room_code}
Client -> Authorization: Bearer <access_token>
```

- `access_token`: JWT, TTL = 60 мин.
- `refresh_token`: Opaque token, TTL = 30 дней, хранится в Redis.

## WebSocket Protocol

**Подключение:** `ws://localhost:8000/ws/{room_code}?token={access_token}`

### Envelope формат (строго соблюдать)
```json
{
  "type": "<event_type>",
  "payload": {}
}
```

### Типы событий (планируются)
| Type | Направление | Описание |
|---|---|---|
| `player_joined` | Server->Client | Новый игрок вошёл в лобби |
| `player_left` | Server->Client | Игрок отключился |
| `game_started` | Server->Client | Хост запустил игру |
| `question` | Server->Client | Новый вопрос раунда |
| `submit_bluff` | Client->Server | Игрок отправил фейковый ответ |
| `vote` | Client->Server | Игрок проголосовал |
| `round_result` | Server->Client | Итоги раунда + очки |

## Error Responses

Все ошибки возвращают стандартный формат:
```json
{
  "detail": "Описание ошибки"
}
```
HTTP-коды: 400 (валидация), 401 (не авторизован), 403 (нет доступа), 404, 422, 500.
