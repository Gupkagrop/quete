---
request_feedback: true
user_facing: true
---

# Спринт 1: Инфраструктура, Монорепозиторий и БД

## Goal Description
Цель этого плана — заложить прочный фундамент проекта Quete (Спринт 1). Мы обновим общую документацию (`ROADMAP.md`), чтобы она отражала утвержденный план на 10 спринтов. Затем мы создадим базовую инфраструктуру: `docker-compose` для баз данных (PostgreSQL, Redis), скелет бэкенда на FastAPI (с использованием `uv` и SQLModel) и базовое Flutter-приложение (с подключенным Riverpod). Также будет добавлен шаблон GitHub Actions для будущих проверок и деплоя.

## User Review Required
> [!IMPORTANT]
> - **Управление зависимостями Python:** Будет использоваться сверхбыстрый `uv` (потребуется установка `uv` на вашей машине, если его еще нет).
> - **Схема БД:** В рамках спринта мы только настроим подключение к БД и Alembic, сами модели предметной области (Users, Rooms и т.д.) будут создаваться в следующих спринтах по мере необходимости.
> - **CI/CD:** Шаг деплоя в GitHub Actions будет присутствовать, но закомментирован, чтобы пайплайн не падал без настроенных секретов сервера.

## Open Questions
Нет. Все вопросы прояснены в предыдущем обсуждении.

## Proposed Changes

### Документация проекта
Мы перенесем утвержденный план на 70 дней в основной файл `ROADMAP.md` репозитория.

#### [MODIFY] `c:\Users\denis\Documents\pet project for github\quete\docs\ROADMAP.md`
Обновление содержимого на финальный план по 10 спринтам, добавление принятых архитектурных решений (`uv`, PostgreSQL 16, Redis 7, локальная разработка + CI/CD шаблон).

---

### Инфраструктура и CI/CD
Настройка локального окружения через Docker Compose и пайплайна GitHub Actions.

#### [NEW] `c:\Users\denis\Documents\pet project for github\quete\docker-compose.yml`
```yaml
version: '3.8'
services:
  postgres:
    image: postgres:16-alpine
    environment:
      POSTGRES_USER: quete_user
      POSTGRES_PASSWORD: quete_password
      POSTGRES_DB: quete_db
    ports:
      - "5432:5432"
    volumes:
      - postgres_data:/var/lib/postgresql/data

  redis:
    image: redis:7-alpine
    ports:
      - "6379:6379"
    volumes:
      - redis_data:/data

volumes:
  postgres_data:
  redis_data:
```

#### [NEW] `c:\Users\denis\Documents\pet project for github\quete\.github\workflows\ci.yml`
Шаблон CI/CD с джобами для:
1. Линтинга и тестов Backend (`uv run pytest`)
2. Линтинга и тестов Flutter (`flutter analyze`, `flutter test`)
3. Деплоя (заглушка для будущего использования)

---

### Backend (Python/FastAPI)
Создание скелета бэкенда.

#### [NEW] `c:\Users\denis\Documents\pet project for github\quete\backend\pyproject.toml`
Конфигурация проекта с использованием `uv`, содержащая зависимости: `fastapi`, `uvicorn`, `sqlmodel`, `alembic`, `asyncpg`, `redis`, `pytest`.

#### [NEW] `c:\Users\denis\Documents\pet project for github\quete\backend\app\main.py`
Базовое приложение FastAPI с одним тестовым эндпоинтом `/health`.

#### [NEW] `c:\Users\denis\Documents\pet project for github\quete\backend\alembic.ini`
Файл конфигурации для миграций базы данных.

#### [NEW] `c:\Users\denis\Documents\pet project for github\quete\backend\app\db\database.py`
Настройка движка SQLModel для подключения к PostgreSQL из `docker-compose`.

---

### Client (Flutter)
Создание скелета клиентского приложения.

#### [NEW] `c:\Users\denis\Documents\pet project for github\quete\client\pubspec.yaml`
Мы выполним команду `flutter create --project-name quete --platforms web,android,ios,windows .` внутри папки `client`, а затем добавим зависимости `flutter_riverpod` и `go_router`.

#### [MODIFY] `c:\Users\denis\Documents\pet project for github\quete\client\lib\main.dart`
Базовый счетчик Flutter будет обернут в `ProviderScope` (для Riverpod).

---

## Verification Plan

### Automated Tests
После реализации мы запустим базовые тесты, чтобы убедиться, что скелеты работают.
```bash
# В папке backend
uv run pytest

# В папке client
flutter test
```

### Manual Verification
Инструкция для вас:
1. Запустить `docker-compose up -d` в корне проекта и убедиться, что контейнеры `postgres` и `redis` работают (`docker ps`).
2. В папке `backend` запустить `uv run fastapi dev app/main.py` и открыть `http://localhost:8000/docs`.
3. В папке `client` запустить `flutter run -d chrome` и убедиться, что приложение собирается и запускается.