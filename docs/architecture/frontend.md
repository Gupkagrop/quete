# Frontend Architecture — Quete

> [!NOTE]
> Дизайн-система, роутинг и управление состоянием Flutter-клиента Quete.
> Актуален на Спринт 1 (скелет). Обновляется по мере добавления фич.

## Платформы
- **Web** (основной деплой, Mobile-First, вертикальная ориентация)
- **Android / iOS**
- **Windows** (Desktop)

## Tech Stack
| Инструмент | Версия | Назначение |
|---|---|---|
| Flutter | 3.x+ | UI фреймворк |
| `flutter_riverpod` | ^3.4.3 | Стейт-менеджмент |
| `go_router` | ^18.0.1 | Навигация |

## Структура директорий

```text
client/lib/
├── core/
│   ├── network/      # HTTP-клиент, перехватчики токенов
│   ├── websocket/    # WebSocket-сервис, reconnect логика
│   ├── storage/      # Хранилище токенов (shared_preferences)
│   └── theme/        # Ретро-тема, пиксельные шрифты, цвета
├── models/           # Dart data models (fromJson/toJson, строгая типизация)
├── state/            # Riverpod providers и AsyncNotifiers
└── ui/
    ├── screens/
    │   ├── auth/     # Welcome / Guest Auth экран (Спринт 2)
    │   ├── menu/     # Главное меню
    │   ├── lobby/    # Лобби + список игроков (Спринт 3)
    │   ├── game/     # Игровой цикл (Спринты 5–8)
    │   └── result/   # Таблица лидеров (Спринт 8)
    └── widgets/      # Переиспользуемые компоненты
        ├── retro_button.dart
        ├── pixel_timer.dart
        └── answer_card.dart
```

## Routing (`go_router`)

- **Guard:** Если нет сохранённого токена -> redirect на `/auth`.
- **Маршруты:**

| Путь | Экран |
|---|---|
| `/auth` | Экран гостевой авторизации |
| `/` | Главное меню |
| `/lobby/:code` | Лобби комнаты |
| `/game/:code` | Игровой экран |
| `/result/:code` | Результаты |

## State Management (Riverpod)

- **`authProvider`** — текущий игрок + JWT-токены. `AsyncNotifierProvider`.
- **`roomProvider`** — состояние лобби. Подписывается на WebSocket-стрим.
- **`gameProvider`** — State Machine игрового цикла. Обрабатывает события раунда.

## Design System (8-bit Retro)

- **Шрифты:** Пиксельные (Press Start 2P или аналог).
- **Цвета:** Тёмный фон (`#0D0D0D`) + неоновые акценты (зелёный `#00FF41`, пурпурный `#FF00FF`).
- **Компоненты:** Резкие границы без скруглений, пиксельные тени, ASCII-арт декор.
- **Анимация:** CustomPainter — CRT-сканлайны, мигающие курсоры.
- **Адаптивность:** `LayoutBuilder` + `MediaQuery`. Mobile-first breakpoint: 600px.

## WebSocket Client Rules

1. Подключение при входе в лобби (`/lobby/:code`).
2. Exponential backoff при реконнекте: 1s -> 2s -> 4s -> 8s -> max 30s.
3. Все входящие события типизированы через Dart-модели (никакого raw dynamic).
4. Отключение — через `dispose()` при unmount экрана.
