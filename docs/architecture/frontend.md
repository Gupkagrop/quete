# Frontend Architecture — Quete

> [!NOTE]
> Дизайн-система, роутинг, управление состоянием и протокол работы WebSocket-клиента Flutter.
> Актуализировано по результатам финального аудита (Спринт 1–10).

## Платформы
- **Web** (основной деплой, Mobile-First, вертикальная ориентация)
- **Android / iOS**
- **Windows** (Desktop)

## Tech Stack
| Инструмент | Версия | Назначение |
|---|---|---|
| Flutter | 3.x+ | UI фреймворк |
| `flutter_riverpod` | ^3.4.3 | Стейт-менеджмент (`AsyncNotifier`) |
| `go_router` | ^18.0.1 | Декларативная навигация |
| `shared_preferences` | ^2.3.2 | Персистентное хранилище токенов |
| `http` | ^1.2.2 | REST API клиент |

---

## 1. Архитектура директорий (`client/lib/`)

```text
client/lib/
├── core/
│   ├── network/      # HTTP-клиент, REST API вызовы, обработка 429 Rate Limit
│   ├── websocket/    # WebSocketService: отправка событий, reconnect логика
│   ├── storage/      # TokenStorage (access_token, refresh_token, player_id)
│   └── theme/        # Ретро 8-bit тема, пиксельные шрифты, цвета
├── models/           # Dart Data Models (PlayerProfile, RoomState, GameState, etc.)
├── state/            # Riverpod AsyncNotifier провайдеры (auth, room, game)
└── ui/
    ├── screens/
    │   ├── auth/     # Экран гостевой авторизации (Спринт 2)
    │   ├── menu/     # Главное меню (Создать / Войти)
    │   ├── lobby/    # Лобби комнаты и выбор темы (Спринты 3–4)
    │   ├── game/     # Игровой цикл: контейнер фаз матча (Спринты 5–8)
    │   └── result/   # Итоговая таблица лидеров и пьедестал (Спринт 8)
    └── widgets/      # Ретро-виджеты (RetroButton, PixelTimer, ReconnectBanner)
```

---

## 2. Маршрутизация и Guard (`go_router`)

- **Auth Guard:** Проверяет наличие валидного токена при старте приложения. Если токен отсутствует -> автоматический редирект на `/auth`.
- **Таблица маршрутов:**
  | Путь | Экран | Описание |
  |---|---|---|
  | `/auth` | `AuthScreen` | Ввод ника, опциональный PIN-код, показ выданного PIN |
  | `/` | `MainMenuScreen` | Создать комнату или войти по коду |
  | `/lobby/:code` | `LobbyScreen` | Ожидание игроков, передача хоста, выбор темы |
  | `/game/:code` | `GameContainerScreen` | Единый контейнер смены фаз (вопрос, блеф, голосование, результаты) |
  | `/result/:code` | `LeaderboardScreen` | Итоговый пьедестал лидеров |

---

## 3. WebSocket клиент и протокол обмена событиями

### 3.1 Handshake и безопасность
1. Подключение: `ws://<host>/ws/{room_code}` (чистый URL).
2. Первое сообщение клиента: `{"type": "auth", "payload": {"token": "<access_token>"}}`.

### 3.2 Отправка клиентских действий (Client -> Server)
Через метод `WebSocketService.send(type, payload)`:
- `select_topic`: `{"topic": "..."}`
- `start_game`: `{}`
- `submit_bluff`: `{"answer_text": "..."}`
- `submit_vote`: `{"option_id": "..."}`
- `rematch`: `{}`
- `ping`: `{}`

### 3.3 State Recovery (Защита от потери пакетов)
- При разрыве соединения: exponential backoff (1s $\rightarrow$ 2s $\rightarrow$ 4s $\rightarrow$ max 30s) и показ `ReconnectBanner`.
- **Сразу после восстановления сокета:**
  1. Вызов `GET /rooms/{code}/state`.
  2. Запись снимка игры в `gameProvider` (фаза, таймер, список `voting_options`, статус ответа).
  3. UI синхронизируется на нужную фазу, после чего включается приём инкрементальных событий.

---

## 4. Дизайн-система (8-bit Retro)

- **Палитра:** `#0D0D0D` (фон), `#1A1A1A` (карточки), `#00FF41` (зеленый неон), `#FF00FF` (магента неон), `#FF3131` (ошибка).
- **Геометрия:** `BorderRadius.zero`, резкие пиксельные тени и контрастные рамки 2–3px.
- **Типографика:** Моноширинный пиксельный шрифт.
- **Валидация на клиенте:** никнейм `^[a-zA-Z0-9_-]{2,20}$`, блеф 1–80 символов.
