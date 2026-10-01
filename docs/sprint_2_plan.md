---
request_feedback: true
user_facing: true
---

# Спринт 2: Фича «Авторизация и Сессии» (День 8–14)

## Goal Description
Цель второго спринта — реализовать надёжную гостевую авторизацию (JWT Guest Auth) и управление сессиями на базе Redis и PostgreSQL, а также создать стартовый пользовательский интерфейс Flutter с базовой 8-bit дизайн-системой, Riverpod стейт-менеджментом и маршрутизацией через `go_router`.

Спринт реализуется по принципу вертикального среза (Vertical Slice): бэкенд и фронтенд создаются согласованно и покрываются автоматическими тестами.

---

## 1. Архитектурные решения и Scoping

### 1.1 Механика гостевого входа и 6-значный PIN-код
- Гостевой вход не требует ввода email или пароля.
- Пользователь вводит только `nickname` (2–20 символов, буквы/цифры/дефис/подчёркивание).
- Сервер генерирует случайный **6-значный PIN-код** (цифры `000000`–`999999`), сохраняет хэш PIN-кода (или PIN с солью) и возвращает его игроку.
- Этот PIN служит **кодом восстановления**: если игрок очистит кэш браузера или захочет войти со второго устройства под тем же ником, он указывает `nickname` + `pin_code`.

### 1.2 Токены и Redis
- **Access Token:** JWT (HS256), TTL = 60 минут. Payload:
  ```json
  {
    "sub": "<player_uuid>",
    "nickname": "<nickname>",
    "exp": 1759000000,
    "iat": 1758996400,
    "type": "access"
  }
  ```
- **Refresh Token:** Opaque UUID токен, TTL = 30 дней. Сохраняется в Redis по ключу `refresh_token:{token_str}` со значением `player_id`. При обновлении токена старый refresh-токен удаляется (ротация токенов).
- **Redis Сессия:** Ключ `session:{player_id}` со значением JSON `{ "online": true, "last_seen": "<iso-timestamp>" }`. TTL = 24 часа для гостевой сессии.

### 1.3 Базовая 8-bit Дизайн-система (Frontend)
Чтобы избежать полной переверстки интерфейса в 9-м спринте, базовая дизайн-система внедряется прямо сейчас:
- Палитра: Background `#0D0D0D`, Primary/Neon-Green `#00FF41`, Accent/Magenta `#FF00FF`, Card/Surface `#1A1A1A`.
- Геометрия: резкие границы (`BorderRadius.zero`), пиксельные акцентированные рамки толщиной 2–3px.
- Типографика: моноширинный/пиксельный стиль с контрастными заголовками.

---

## 2. Предлагаемые изменения по файлам

### Backend (`backend/`)

#### 1. Зависимости (`pyproject.toml`)
- Добавить `pyjwt>=2.9.0` и `cryptography>=43.0.0` для генерации и верификации JWT.

#### 2. Модель базы данных (`backend/app/models/player.py`)
- SQLModel сущность `Player`:
  - `id: UUID = Field(default_factory=uuid4, primary_key=True)`
  - `nickname: str = Field(index=True, max_length=50)`
  - `pin_code: str = Field(max_length=6)` — 6-значный код восстановления
  - `created_at: datetime = Field(default_factory=datetime.utcnow)`
  - `updated_at: datetime = Field(default_factory=datetime.utcnow)`

#### 3. Миграция Alembic (`backend/alembic/versions/001_create_player_table.py`)
- Создание таблицы `player` с индексом по `nickname`.

#### 4. Ядро безопасности и настроек (`backend/app/core/`)
- `backend/app/core/config.py`: чтение `JWT_SECRET_KEY`, `JWT_ALGORITHM`, `DATABASE_URL`, `REDIS_URL`.
- `backend/app/core/security.py`: функции `create_access_token`, `create_refresh_token`, `decode_access_token`, `generate_pin_code`.

#### 5. Сервис сессий и Redis (`backend/app/services/session_service.py`)
- Функции управления refresh-токенами и сессией онлайна в Redis.

#### 6. REST API эндпоинты (`backend/app/api/auth.py`)
- `POST /auth/guest`: приём `nickname` (и опционального `pin_code` для повторного входа). Возвращает `access_token`, `refresh_token`, `player_id`, `nickname`, `pin_code`.
- `POST /auth/refresh`: приём `refresh_token`, выдача новой пары токенов.
- `GET /auth/me`: получение профиля текущего игрока по Bearer JWT.

---

### Frontend (`client/`)

#### 1. Зависимости (`client/pubspec.yaml`)
- Добавить `http: ^1.2.2` (или аналог для HTTP-запросов).
- Добавить `shared_preferences: ^2.3.2` для сохранения токенов на устройстве/в браузере.

#### 2. Дизайн-система (`client/lib/core/theme/`)
- `retro_theme.dart`: ThemeData с тёмным фоном, неоновыми цветами и стилизованными текстовыми темами.
- `retro_button.dart`: кастомная 8-bit кнопка с пиксельной рамкой и псевдо-тенями.

#### 3. Хранилище токенов (`client/lib/core/storage/`)
- `token_storage.dart`: безопасное сохранение, получение и удаление `access_token` и `refresh_token`.

#### 4. Стейт-менеджмент (`client/lib/state/`)
- `auth_provider.dart`: Riverpod `AsyncNotifierProvider` (`AuthState: unauthenticated, authenticating, authenticated, error`).
- Методы: `loginAsGuest(nickname, [pin])`, `checkAuth()`, `logout()`.

#### 5. Маршрутизация (`client/lib/core/router/`)
- `app_router.dart`: `go_router` с маршрутами `/` (Home/Lobby placeholder) и `/auth` (Welcome/Auth).
- Редирект-гард: если пользователь не авторизован -> переход на `/auth`.

#### 6. Экран авторизации (`client/lib/ui/screens/auth/`)
- `auth_screen.dart`: ретро-логотип "QUETE", поле ввода никнейма, поле опционального PIN-кода, кнопка «ВОЙТИ В ИГРУ», отображение полученного PIN-кода после успешного входа.

---

## 3. План верификации

### Автоматические тесты
1. **Backend (`pytest`):**
   - Успешная регистрация нового гостя (`POST /auth/guest`) с получением токенов и PIN.
   - Повторный вход существующего гостя по корректному нику и PIN.
   - Ошибка 401 при неверном PIN для существующего ника.
   - Ротация токена через `POST /auth/refresh`.
   - Защита эндпоинта `GET /auth/me` (200 при валидном токене, 401 при невалидном).
2. **Client (`flutter test`):**
   - Юнит-тест `TokenStorage` (запись, чтение, очистка токенов).
   - Тест состояния `AuthProvider` при успехе и ошибке авторизации.
   - Виджет-тест экрана `AuthScreen` (валидация ввода никнейма, нажатие кнопки входа).

### Линтинг и качество кода
- `uv run ruff check .` — 0 ошибок.
- `flutter analyze` — 0 warnings/errors.
