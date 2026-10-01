# Задачи Спринта 2: Фича «Авторизация и Сессии» (День 8–14) ✅ [ЗАВЕРШЁН]

### 1. Подготовка окружения и зависимостей
- [x] Backend: Добавить зависимости `pyjwt`, `cryptography`, `bcrypt` и `redis` через `uv add`
- [x] Client: Добавить зависимости `http` и `shared_preferences` в `client/pubspec.yaml`

### 2. База данных и миграции (Backend)
- [x] Backend: Создать SQLModel-модель `Player` (`id: UUID`, `nickname: str`, `pin_hash: str`, timestamps) в `app/models/player.py`
- [x] Backend: Сгенерировать и применить миграцию Alembic для таблицы `player` с индексом по `nickname`

### 3. Безопасность, токены и Redis (Backend)
- [x] Backend: Реализовать модуль настроек `app/core/config.py` (JWT параметры, Redis URL, DB параметры)
- [x] Backend: Реализовать функции хэширования/проверки bcrypt и генерации 6-значного PIN в `app/core/security.py`
- [x] Backend: Реализовать генерацию JWT access и refresh токенов
- [x] Backend: Реализовать сервис `app/services/session_service.py` с разделением ключей `session:{player_id}` и `online:{player_id}`
- [x] Backend: Реализовать механизм Grace Period (30–60 сек) для ротации refresh-токенов в Redis
- [x] Backend: Реализовать Rate Limiter на базе Redis для защиты от брутфорса PIN-кода (5 попыток/мин, бан 15 мин)

### 4. REST API авторизации (Backend)
- [x] Backend: Создать Pydantic-схемы запросов/ответов со строгой валидацией никнейма `^[a-zA-Z0-9_-]{2,20}$` в `app/schemas/auth.py`
- [x] Backend: Реализовать эндпоинт `POST /auth/guest` с проверкой Rate Limit и регистрацией/входом по PIN
- [x] Backend: Реализовать эндпоинт `POST /auth/refresh` с ротацией токена и поддержкой Grace Period
- [x] Backend: Реализовать эндпоинт `GET /auth/me` и DI-зависимость `get_current_player`
- [x] Backend: Подключить auth-роутер в `app/main.py`

### 5. Дизайн-система и клиентские сервисы (Frontend)
- [x] Client: Реализовать базовую 8-bit тему (цвета `#0D0D0D`, `#00FF41`, `#FF00FF`, `#1A1A1A`) в `core/theme/retro_theme.dart`
- [x] Client: Создать переиспользуемый компонент 8-bit кнопки `RetroButton` с пиксельной рамкой
- [x] Client: Реализовать хранилище токенов `TokenStorage` на базе `SharedPreferences`

### 6. Стейт-менеджмент и UI (Frontend)
- [x] Client: Создать модели `PlayerProfile` и `AuthResponse` в `models/auth.dart`
- [x] Client: Реализовать `AuthProvider` на Riverpod (`AsyncNotifierProvider`) с методами `loginAsGuest`, `checkAuth`, `logout`
- [x] Client: Настроить декларативный роутинг `go_router` с auth-guard редиректом на `/auth`
- [x] Client: Сверстать 8-bit экран входа `AuthScreen` (валидация никнейма, поле PIN-кода, кнопка входа, обработка 429 Rate Limit)
- [x] Client: Реализовать диалоговое окно показа сгенерированного PIN-кода с кнопкой быстрого копирования

### 7. Тестирование и верификация (Pre-Commit Gate)
- [x] Backend: Написать pytest тесты для `POST /auth/guest` (регистрация и вход по PIN)
- [x] Backend: Написать pytest тест на блокировку по Rate Limit (5 неудачных попыток -> 429 Too Many Requests)
- [x] Backend: Написать pytest тест на безопасную ротацию refresh-токенов с Grace Period
- [x] Backend: Написать pytest тест для `GET /auth/me` и зависимость аутентификации
- [x] Client: Написать unit-тесты для `TokenStorage` и `AuthProvider`
- [x] Client: Написать widget-тест для экрана `AuthScreen`
- [x] Верификация: `uv run ruff check .` (0 ошибок)
- [x] Верификация: `uv run pytest` (100% green)
- [x] Верификация: `flutter analyze` (0 ошибок)
- [x] Верификация: `flutter test` (100% green)
