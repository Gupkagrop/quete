# Frontend Architecture — Quete

> [!NOTE]
> Дизайн-система, роутинг, управление состоянием и протокол работы WebSocket-клиента Flutter.
> Актуализировано по результатам Adversarial Architecture Review (Спринт 1–10).

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
│   ├── websocket/    # WebSocket-сервис, auth handshake, reconnect логика
│   ├── storage/      # TokenStorage (access_token, refresh_token, player_id)
│   └── theme/        # Ретро 8-bit тема, пиксельные шрифты, цвета
├── models/           # Dart Data Models (PlayerProfile, RoomState, GameState, etc.)
├── state/            # Riverpod AsyncNotifier провайдеры (auth, room, game)
└── ui/
    ├── screens/
    │   ├── auth/     # Экран гостевой авторизации (Спринт 2)
    │   ├── menu/     # Главное меню (Создать / Войти)
    │   ├── lobby/    # Лобби комнаты и выбор темы (Спринты 3–4)
    │   ├── game/     # Игровой цикл: вопрос, блеф, голосование (Спринты 5–8)
    │   └── result/   # Итоги раунда и пьедестал победителей (Спринт 8)
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
  | `/game/:code` | `GameScreen` | Контейнер фаз матча (вопрос, блеф, голосование) |
  | `/result/:code` | `ResultScreen` | Итоговая таблица лидеров |

---

## 3. Правила работы WebSocket-клиента и State Recovery

1. **Безопасное подключение:** Сокет подключается по чистому URL `ws://<host>/ws/{room_code}` без токена в параметрах.
2. **Auth Handshake:** Сразу после открытия соединения клиент отправляет:
   ```json
   {
     "type": "auth",
     "payload": {"token": "<access_token>"}
   }
   ```
3. **Resilience & Exponential Backoff:**
   - При обрыве соединения (смена Wi-Fi ↔ LTE, звонок): повторы с задержкой 1с $\rightarrow$ 2с $\rightarrow$ 4с $\rightarrow$ 8с $\rightarrow$ max 30с.
   - Во время реконнекта отображается виджет `ReconnectBanner`.
4. **State Recovery (Защита от потери пакетов):**
   - **Строгое правило:** Сразу после успешного реконнекта сокета клиент выполняет REST-запрос `GET /rooms/{code}/state`.
   - Полученный полный снимок игры записывается в `gameProvider`, экран переключается на актуальную фазу, и только затем клиент начинает принимать инкрементальные WS-события.

---

## 4. Дизайн-система (8-bit Retro)

- **Палитра:**
  - Background: `#0D0D0D` (глубокий чёрный)
  - Surface/Card: `#1A1A1A`
  - Neon Green (Primary): `#00FF41`
  - Neon Magenta (Accent): `#FF00FF`
  - Danger/Warning: `#FF3131`
- **Геометрия:** `BorderRadius.zero`, акцентированные рамки 2–3px с псевдо-тенями.
- **Типографика:** Моноширинные пиксельные шрифты с контрастными заголовками.
- **Клиентская валидация никнейма:** Regex `^[a-zA-Z0-9_-]{2,20}$`.
