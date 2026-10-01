---
request_feedback: true
user_facing: true
---

# Спринт 2: Фича «Авторизация и Сессии» (День 8–14)

## Goal Description
Цель второго спринта — реализовать надёжную гостевую авторизацию (JWT Guest Auth) с защитой от брутфорса, безопасное управление сессиями на базе Redis и PostgreSQL, а также создать стартовый пользовательский интерфейс Flutter с базовой 8-bit дизайн-системой, Riverpod стейт-менеджментом и маршрутизацией через `go_router`.

Спринт реализуется по принципу вертикального среза (Vertical Slice): бэкенд и фронтенд создаются согласованно и покрываются автоматическими тестами.

---

## 1. Архитектурные решения и Scoping

### 1.1 Механика гостевого входа и 6-значный PIN-код
- Гостевой вход не требует ввода email или пароля.
- Пользователь вводит только `nickname` (валидация по regex: `^[a-zA-Z0-9_-]{2,20}$`).
- Сервер генерирует случайный **6-значный PIN-код** (цифры `000000`–`999999`).
- **Безопасность хранения:** PIN-код строго хэшируется с использованием **bcrypt** (`pin_hash`), хранение в открытом виде запрещено.
- Игроку при первом входе возвращается сгенерированный PIN, служащий **кодом восстановления** для входа с другого устройства.

### 1.2 Защита от перебора PIN-кода (Rate Limiting)
- 6-значный PIN имеет $10^6$ комбинаций. Для предотвращения подбора внедряется **Rate Limiting на базе Redis**:
  - Не более 5 попыток ввода PIN-кода в минуту для комбинации `(IP, nickname)`.
  - При превышении — временная блокировка на 15 минут с HTTP-статусом `429 Too Many Requests`.

### 1.3 Токены, Redis и ротация с Grace Period
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
- **Refresh Token (с Grace Period):** Opaque UUID токен, TTL = 30 дней. Сохраняется в Redis по ключу `refresh_token:{token_str}` со значением `player_id`.
  - **Grace Period (30–60 сек):** При ротации старый токен не удаляется мгновенно, а помечается как ротированный на короткое время. Это предотвращает случайный разлогин игрока при повторных запросах со смартфона из-за нестабильного мобильного интернета.
- **Разделение ключей Redis:**
  - `session:{player_id}` — постоянные сессионные метаданные авторизации (TTL = 30 дней).
  - `online:{player_id}` — статус присутствия в реальном времени по WS Heartbeat (TTL = 30 секунд).

### 1.4 Базовая 8-bit Дизайн-система (Frontend)
- **Палитра:** Background `#0D0D0D`, Primary/Neon-Green `#00FF41`, Accent/Magenta `#FF00FF`, Card/Surface `#1A1A1A`.
- **Геометрия:** резкие границы (`BorderRadius.zero`), акцентированные пиксельные рамки толщиной 2–3px.
- **Типографика:** моноширинный/пиксельный стиль с контрастными заголовками.

---

## 2. Предлагаемые изменения по файлам

### Backend (`backend/`)

#### 1. Зависимости (`pyproject.toml`)
- Добавить `pyjwt>=2.9.0`, `cryptography>=43.0.0`, `bcrypt>=4.2.0`, `redis>=5.0.0`.

#### 2. Модель базы данных (`backend/app/models/player.py`)
- SQLModel сущность `Player`:
  - `id: UUID = Field(default_factory=uuid4, primary_key=True)`
  - `nickname: str = Field(index=True, max_length=50)`
  - `pin_hash: str = Field(max_length=255)` — хэш 6-значного кода bcrypt
  - `created_at: datetime = Field(default_factory=datetime.utcnow)`
  - `updated_at: datetime = Field(default_factory=datetime.utcnow)`

#### 3. Миграция Alembic (`backend/alembic/versions/001_create_player_table.py`)
- Создание таблицы `player` с индексом по `nickname`.

#### 4. Ядро безопасности и настроек (`backend/app/core/`)
- `backend/app/core/config.py`: чтение `JWT_SECRET_KEY`, `JWT_ALGORITHM`, `DATABASE_URL`, `REDIS_URL`.
- `backend/app/core/security.py`: функции `get_password_hash`, `verify_password`, `create_access_token`, `create_refresh_token`, `decode_access_token`, `generate_pin_code`.

#### 5. Сервис сессий и Rate Limiting (`backend/app/services/session_service.py`)
- Управление refresh-токенами с Grace Period в Redis.
- Проверка и инкремент счетчиков Rate Limiting на Redis.

#### 6. REST API эндпоинты (`backend/app/api/auth.py`)
- `POST /auth/guest`: приём `nickname` (валидация regex) и опционального `pin_code`. Применение Rate Limiter. Возвращает `access_token`, `refresh_token`, `player_id`, `nickname`, `pin_code` (при первой регистрации).
- `POST /auth/refresh`: приём `refresh_token`, выдача новой пары токенов с учетом Grace Period.
- `GET /auth/me`: получение профиля текущего игрока по Bearer JWT.

---

### Frontend (`client/`)

#### 1. Зависимости (`client/pubspec.yaml`)
- Добавить `http: ^1.2.2` и `shared_preferences: ^2.3.2`.

#### 2. Дизайн-система (`client/lib/core/theme/`)
- `retro_theme.dart`: ThemeData с тёмным фоном `#0D0D0D`, неоновыми акцентами `#00FF41` и `#FF00FF`.
- `retro_button.dart`: кастомная 8-bit кнопка с пиксельной рамкой и псевдо-тенями.

#### 3. Хранилище токенов (`client/lib/core/storage/`)
- `token_storage.dart`: безопасное сохранение, чтение и очистка `access_token`, `refresh_token`, `player_id`.

#### 4. Стейт-менеджмент (`client/lib/state/`)
- `auth_provider.dart`: Riverpod `AsyncNotifierProvider` (`AuthState: unauthenticated, authenticating, authenticated, error`).
- Методы: `loginAsGuest(nickname, [pin])`, `checkAuth()`, `logout()`.

#### 5. Маршрутизация (`client/lib/core/router/`)
- `app_router.dart`: `go_router` с маршрутами `/` и `/auth`.
- Редирект-гард: если токен отсутствует -> автоматический редирект на `/auth`.

#### 6. Экран авторизации (`client/lib/ui/screens/auth/`)
- `auth_screen.dart`: ретро-логотип "QUETE", поле ввода никнейма (валидация regex), поле PIN-кода, кнопка «ВОЙТИ», диалог отображения выданного PIN-кода с кнопкой копирования, обработка ошибки 429 (Rate Limit).

---

## 3. План верификации

### Автоматические тесты
1. **Backend (`pytest`):**
   - Успешная регистрация нового гостя (`POST /auth/guest`) с получением токенов и открытого PIN (в БД хэш!).
   - Повторный вход существующего гостя по корректному нику и PIN.
   - Ошибка 401 при неверном PIN для существующего ника.
   - Тест Rate Limiting: 6-й запрос ввода неверного PIN блокируется со статусом 429.
   - Ротация токена через `POST /auth/refresh` и проверка работы Grace Period (повторный запрос со старым токеном в течение 30 сек не возвращает 401).
   - Защита эндпоинта `GET /auth/me` (200 при валидном токене, 401 при истёкшем).
2. **Client (`flutter test`):**
   - Юнит-тест `TokenStorage` (запись, чтение, удаление).
   - Тест состояний `AuthProvider` при успехе, ошибке 401 и ошибке 429.
   - Виджет-тест экрана `AuthScreen` (валидация ввода, клик по кнопке, отображение PIN-кода).

### Линтинг и качество кода
- `uv run ruff check .` — 0 ошибок.
- `flutter analyze` — 0 warnings/errors.
