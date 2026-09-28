# AGENTS.md — Паспорт проекта Quete

> [!NOTE]
> Этот документ — единая точка входа и паспорт проекта для любого ИИ-агента (стандарт сентября 2026 года).
> Содержит актуальный стек, команды сборки, правила чистого кода, архитектурные ограничения и запрещённые паттерны.
> Обязателен к ознакомлению перед выполнением любых задач в репозитории Quete.

## 1. Tech Stack

| Слой | Технология | Детали |
|---|---|---|
| **Backend** | Python 3.12+, FastAPI | Полностью асинхронный (async/await), Pydantic v2 |
| **ORM / DB** | SQLModel + PostgreSQL 16 | Асинхронный драйвер asyncpg, SQLAlchemy 2.0 async |
| **Кэш / Сессии** | Redis 7 | Онлайн игроков, heartbeat, временный кэш вопросов |
| **Миграции** | Alembic | Управление схемой реляционной БД |
| **Пакетный менеджер Backend** | uv | Использовать исключительно `uv` (НЕ pip, НЕ poetry) |
| **Frontend** | Flutter / Dart 3.x+ | Mobile-First, кроссплатформенность (Web, Android, iOS, Windows) |
| **State Management** | Riverpod (`flutter_riverpod ^3.4.3`) | AsyncNotifier, без setState в бизнес-логике |
| **Роутинг** | `go_router ^18.0.1` | Декларативный роутинг, auth guards |
| **ИИ Интеграция** | Google Gemini 1.5 Flash | Режим Structured Outputs (Pydantic-схемы) |
| **Авторизация** | Self-hosted JWT | Guest Auth (6-значный код комнаты/сессии) |
| **Realtime-связь** | WebSockets | FastAPI WebSockets + Flutter WebSocket channel |
| **Инфраструктура** | Docker Compose | Локальный запуск PostgreSQL 16 и Redis 7 |
| **CI/CD** | GitHub Actions | Автоматический линтинг и тесты для ветки `dev` |

## 2. Commands

### Backend (`backend/`)
```bash
# Установка и синхронизация зависимостей
uv sync

# Запуск локального dev-сервера FastAPI
uv run uvicorn app.main:app --reload --host 0.0.0.0 --port 8000

# Статический анализ и проверка форматирования
uv run ruff check .

# Запуск тестов pytest
uv run pytest

# Создание и применение миграций Alembic
uv run alembic revision --autogenerate -m "описание_миграции"
uv run alembic upgrade head
```

### Frontend (`client/`)
```bash
# Получение зависимостей
flutter pub get

# Статический анализ Dart кода
flutter analyze

# Запуск сьюта тестов виджетов и логики
flutter test

# Запуск Web-версии в режиме разработки
flutter run -d chrome
```

### Инфраструктура (Корень репозитория)
```bash
# Запуск контейнеров БД и Redis в фоне
docker compose up -d

# Остановка контейнеров с сохранением томов данных
docker compose down
```

### AST-карта кодовой базы (Уровень 2)
```bash
# Генерация файла repomix-output.xml через cmd.exe на Windows
cmd.exe /c npx repomix
```

## 3. Code Standards

- **Ранние возвраты (Early Returns):** Максимальная глубина вложенности блоков — не более 3 уровней. Плоские функции с быстрыми проверками краевых условий.
- **Размер функций:** Не более 30–40 строк кода. Если логика шире — декомпозировать на вспомогательные функции.
- **Строгая типизация:** Полный запрет на `any`, неявные `dynamic` и нетипизированные параметры. Все аргументы и возвращаемые значения строго аннотированы.
- **Запрет на заглушки:** Никаких `// TODO`, `pass` в качестве постоянной логики, нереализованных заглушек или временных моков в production-коде.
- **Язык идентификаторов:** Имена переменных, функций, классов, модулей, таблиц БД — строго на английском языке (`camelCase` / `snake_case` по стандарту языка).
- **Язык комментариев:** Docstrings, поясняющие комментарии и сообщения коммитов Git — строго на русском языке.
- **Git-коммиты:** Формат Conventional Commits на русском языке: `feat: ...`, `fix: ...`, `chore: ...`, `refactor: ...`, `test: ...`.

## 4. Architecture Rules

### Backend
- Все операции ввода-вывода (БД, Redis, HTTP, WebSocket) выполняются строго через `async/await`.
- Внедрение зависимостей сессий БД и пользователя только через `fastapi.Depends`.
- Все ответы Gemini парсятся строго через Structured Outputs с Pydantic; ручной строковый парсинг запрещён.
- Секреты и конфигурации считываются только из переменных окружения через `pydantic-settings` или `.env`.

### Frontend
- Бизнес-логика и хранение данных выносятся в Riverpod провайдеры (`NotifierProvider`, `AsyncNotifierProvider`).
- Навигация организована через `go_router` с централизованными guard-редиректами по статусу авторизации.
- Обработка сбоев сети и WebSocket должна включать стратегию повторов с экспоненциальной задержкой (Exponential Backoff).
- Ретро-дизайн 8-bit реализуется процедурно через CustomPainter, стилизованные шрифты и цветовую палитру без тяжелых растровых ассетов на ранних этапах.

### Структура директорий
```text
quete/
├── backend/app/
│   ├── api/          # REST-роутеры и эндпоинты
│   ├── core/         # settings, JWT, логгер
│   ├── db/           # engine, session, base models
│   ├── models/       # SQLModel сущности
│   ├── schemas/      # Pydantic DTO (запросы/ответы)
│   ├── services/     # Бизнес-логика (AI, лобби, очки)
│   ├── websockets/   # ConnectionManager, обработчики событий
│   └── main.py
└── client/lib/
    ├── core/         # HTTP-клиент, WS-сервис, токены, темы
    ├── models/       # Dart data models (fromJson/toJson)
    ├── state/        # Riverpod providers и notifiers
    └── ui/
        ├── screens/  # Auth, Menu, Lobby, Game, Leaderboard
        ├── widgets/  # Ретро-кнопки, таймеры, карточки
        └── theme/    # Цвета, шрифты, стили пиксель-арта
```

### Архитектурная документация (Уровень 3)
- [docs/architecture/database.md](docs/architecture/database.md) — схема сущностей PostgreSQL, связи, индексы и миграции.
- [docs/architecture/api.md](docs/architecture/api.md) — спецификация REST API, auth flow и WebSocket envelope протокол.
- [docs/architecture/frontend.md](docs/architecture/frontend.md) — архитектура слоёв Flutter, Riverpod стейт и дизайн-система.

## 5. Prohibited Patterns

| Запрещённый паттерн | Причина и регламент |
|---|---|
| Неконтролируемые деструктивные SQL-запросы (`DROP TABLE`, `TRUNCATE`, `DELETE` без фильтра) | Требует обязательного подтверждения пользователя |
| Форсированные операции Git (`git push --force`, `git reset --hard`) | Риск необратимой потери кода |
| Hardcoded секреты (API-ключи, JWT secrets, пароли БД) | Грубое нарушение безопасности |
| Использование `any` в TypeScript/Python или `dynamic` без нужды в Dart | Нарушение контракта строгой типизации |
| Незавершённые заглушки (`// TODO`, пустые заглушки) | Нарушение стандарта готового кода |
| Несогласованное добавление тяжёлых сторонних библиотек | Нарушение принципа YAGNI |

## 6. Testing Policy

- Каждая фича или багфикс сопровождается юнит-тестами (`pytest` для бэкенда, `flutter test` для клиента).
- Внешние вызовы к сторонним API (Gemini API) в тестах изолируются через моки.
- Тесты запускаются локально перед каждым коммитом и автоматически валидируются в GitHub Actions CI.
