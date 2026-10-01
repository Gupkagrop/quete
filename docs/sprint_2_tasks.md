# Задачи Спринта 2: Фича «Авторизация и Сессии» (День 8–14)

### 1. Подготовка окружения и зависимостей
- [ ] Backend: Добавить зависимости `pyjwt` и `cryptography` через `uv add`
- [ ] Client: Добавить зависимости `http` и `shared_preferences` в `client/pubspec.yaml`

### 2. База данных и миграции (Backend)
- [ ] Backend: Создать SQLModel-модель `Player` (`id: UUID`, `nickname: str`, `pin_code: str`, timestamps) в `app/models/player.py`
- [ ] Backend: Создать и применить миграцию Alembic для таблицы `player`

### 3. Безопасность, токены и Redis (Backend)
- [ ] Backend: Реализовать модуль настроек `app/core/config.py` (JWT, Redis, DB параметры)
- [ ] Backend: Реализовать генерацию 6-значного PIN-кода и JWT access/refresh токенов в `app/core/security.py`
- [ ] Backend: Реализовать сервис управления refresh-токенами и сессиями онлайна в Redis `app/services/session_service.py`

### 4. REST API авторизации (Backend)
- [ ] Backend: Реализовать эндпоинт `POST /auth/guest` (создание нового гостя / вход по PIN)
- [ ] Backend: Реализовать эндпоинт `POST /auth/refresh` (ротация refresh-токена)
- [ ] Backend: Реализовать эндпоинт `GET /auth/me` и зависимость `get_current_player`
- [ ] Backend: Подключить auth-роутер в `app/main.py`

### 5. Дизайн-система и клиентские сервисы (Frontend)
- [ ] Client: Реализовать базовую 8-bit тему (цвета `#0D0D0D`, `#00FF41`, `#FF00FF`, шрифты) в `core/theme/`
- [ ] Client: Создать переиспользуемый компонент 8-bit кнопки `RetroButton`
- [ ] Client: Реализовать хранилище токенов `TokenStorage` (на базе `SharedPreferences`)

### 6. Стейт-менеджмент и UI (Frontend)
- [ ] Client: Создать модель данных `PlayerProfile` и `AuthResponse` в `models/auth.dart`
- [ ] Client: Реализовать `AuthProvider` на Riverpod (`AsyncNotifierProvider`)
- [ ] Client: Настроить декларативный роутинг `go_router` с auth-guard редиректом на `/auth`
- [ ] Client: Сверстать 8-bit экран входа `AuthScreen` (ввод никнейма, опциональный PIN, кнопка входа, показ полученного PIN-кода)

### 7. Тестирование и верификация (Pre-Commit Gate)
- [ ] Backend: Написать pytest тесты для `POST /auth/guest`, `POST /auth/refresh`, `GET /auth/me` и ошибок валидации
- [ ] Client: Написать unit-тесты для `TokenStorage` и `AuthProvider`
- [ ] Client: Написать widget-тест для экрана `AuthScreen`
- [ ] Верификация: `uv run ruff check .` (0 ошибок)
- [ ] Верификация: `uv run pytest` (100% green)
- [ ] Верификация: `flutter analyze` (0 ошибок)
- [ ] Верификация: `flutter test` (100% green)
