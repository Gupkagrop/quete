# Database & Storage Architecture — Quete

> [!NOTE]
> Схемы таблиц PostgreSQL, индексы, ограничения целостности, структура Redis и политика очистки устаревших данных.
> Актуализировано по результатам Adversarial Architecture Review (Спринт 1–10).

---

## 1. Схема реляционной базы данных (PostgreSQL 16)

### Таблицы и ограничения

#### 1. `players` (Гостевые профили игроков)
- `id`: `UUID` (Primary Key, default `gen_random_uuid()`)
- `nickname`: `VARCHAR(50)` (NOT NULL, Index)
- `pin_hash`: `VARCHAR(255)` (NOT NULL) — хэш 6-значного PIN-кода (bcrypt/argon2)
- `created_at`: `TIMESTAMPTZ` (NOT NULL, default `NOW()`)
- `updated_at`: `TIMESTAMPTZ` (NOT NULL, default `NOW()`)

#### 2. `game_rooms` (Лобби и комнаты)
- `id`: `UUID` (Primary Key)
- `room_code`: `VARCHAR(6)` (NOT NULL, UNIQUE, Index)
- `host_id`: `UUID` (NOT NULL, FK -> `players.id`, ON DELETE SET NULL)
- `status`: `VARCHAR(20)` (NOT NULL, `waiting` | `in_progress` | `finished`)
- `max_players`: `INT` (default 8)
- `created_at`: `TIMESTAMPTZ` (NOT NULL)
- `updated_at`: `TIMESTAMPTZ` (NOT NULL)

#### 3. `game_sessions` (Игровой матч)
- `id`: `UUID` (Primary Key)
- `room_id`: `UUID` (NOT NULL, FK -> `game_rooms.id`, ON DELETE CASCADE, Index)
- `total_rounds`: `INT` (default 5)
- `current_round_index`: `INT` (default 1)
- `status`: `VARCHAR(20)` (NOT NULL)
- `created_at`: `TIMESTAMPTZ` (NOT NULL)
- `updated_at`: `TIMESTAMPTZ` (NOT NULL)

#### 4. `rounds` (Раунд матча)
- `id`: `UUID` (Primary Key)
- `session_id`: `UUID` (NOT NULL, FK -> `game_sessions.id`, ON DELETE CASCADE, Index)
- `round_number`: `INT` (NOT NULL)
- `question_id`: `UUID` (NOT NULL, FK -> `questions.id`, ON DELETE RESTRICT)
- `phase`: `VARCHAR(30)` (NOT NULL: `QUESTION_READING`, `BLUFF_SUBMISSION`, `VOTING`, `ROUND_RESULTS`)
- `phase_ends_at`: `TIMESTAMPTZ` (NOT NULL)
- `created_at`: `TIMESTAMPTZ` (NOT NULL)

#### 5. `questions` (Пул сгенерированных вопросов)
- `id`: `UUID` (Primary Key)
- `topic`: `VARCHAR(100)` (NOT NULL, Index)
- `text`: `TEXT` (NOT NULL)
- `correct_answer`: `VARCHAR(255)` (NOT NULL)
- `decoy_fallbacks`: `JSONB` (NOT NULL) — массив из 7 ложных вариантов ответа от Gemini
- `explanation`: `TEXT` (NOT NULL)
- `created_at`: `TIMESTAMPTZ` (NOT NULL)

#### 6. `bluff_answers` (Ложные ответы игроков и ИИ)
- `id`: `UUID` (Primary Key)
- `round_id`: `UUID` (NOT NULL, FK -> `rounds.id`, ON DELETE CASCADE, Index)
- `player_id`: `UUID` (NOT NULL, FK -> `players.id`, ON DELETE CASCADE)
- `answer_text`: `VARCHAR(255)` (NOT NULL)
- `is_fallback`: `BOOLEAN` (NOT NULL, default FALSE)
- `created_at`: `TIMESTAMPTZ` (NOT NULL)
- **Constraint:** `UNIQUE (round_id, player_id)` — исключает дублирование ответа при гонке таймаута и позднего сабмита.

#### 7. `votes` (Голоса игроков)
- `id`: `UUID` (Primary Key)
- `round_id`: `UUID` (NOT NULL, FK -> `rounds.id`, ON DELETE CASCADE, Index)
- `voter_id`: `UUID` (NOT NULL, FK -> `players.id`, ON DELETE CASCADE)
- `option_id`: `UUID` (NOT NULL) — ссылка на выбранный вариант
- `created_at`: `TIMESTAMPTZ` (NOT NULL)
- **Constraint:** `UNIQUE (round_id, voter_id)` — предотвращает множественное голосование одним игроком.

---

## 2. Структура ключей Redis 7

| Шаблон ключа | Тип | TTL | Назначение |
|---|---|---|---|
| `session:{player_id}` | String / Hash | 30 дней | Данные refresh-сессии авторизации |
| `refresh_token:{token}` | String | 30 дней + 60 сек | `player_id` с Grace Period ротации |
| `online:{player_id}` | String | 30 сек | Статус присутствия в реальном времени (WS Heartbeat) |
| `room:{code}:players` | Set | 24 часа | Список ID участников комнаты |
| `room:{code}:lock` | String (Lock) | 30 сек | Distributed Lock на генерацию вопроса хостом |
| `room:{code}:channel` | Pub/Sub Channel | — | Широковещательная шина комнаты между воркерами |
| `ratelimit:auth:{ip}` | String / Counter | 1 мин / 15 мин | Rate Limiting попыток авторизации и ввода PIN |
| `question_cache:{topic}` | String (JSON) | 24 часа | Кэш сгенерированных вопросов по темам |

---

## 3. Политика очистки устаревших данных (Garbage Collection & Pruning)

Чтобы предотвратить неконтролируемый рост PostgreSQL и Redis от брошенных гостевых сессий:
1. **Каскадное удаление комнат:** при удалении записи `game_rooms` каскадно удаляются сессии, раунды, ответы и голоса (`ON DELETE CASCADE`).
2. **Фоновая очистка неактивных комнат (Cron/Celery/Asyncio Task):**
   - Комнаты со статусом `finished` или комнаты, где `updated_at < NOW() - INTERVAL '2 hours'`, удаляются каждые 30 минут.
3. **Pruning неактивных гостевых аккаунтов:**
   - Гостевые игроки без активности (`players.updated_at < NOW() - INTERVAL '30 days'`) удаляются ночной фоновой задачей.

---

## 4. Соглашения по миграциям (Alembic)

- Каждая миграция — строго изолированная атомарная ревизия.
- Создание: `uv run alembic revision --autogenerate -m "описание"`
- Применение: `uv run alembic upgrade head`
- Все индексы и внешние ограничения (`UNIQUE`, `ON DELETE CASCADE`) объявляются явно в кодовой базе моделей SQLModel.
