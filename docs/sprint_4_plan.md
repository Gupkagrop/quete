---
request_feedback: true
user_facing: true
---

# Спринт 4: Фича «Генерация вопросов ИИ» (День 22–28)

## Goal Description
Цель четвёртого спринта — внедрить интеграцию с Google Gemini 1.5 Flash для генерации вопросов викторины со строгой валидацией по JSON Schema (Structured Outputs), защитой от Prompt Injection, кэшированием, защитой от параллельного спама через Redis Distributed Lock и резервным fallback-словарём.

---

## 1. Архитектурные решения

### 1.1 Схема Structured Outputs (Pydantic) и 7 Fallback-ответов
Для поддержки комнат до 8 человек (в случае, если до 7 игроков отключатся или пропустят ввод) ИИ обязан сгенерировать ровно 7 правдоподобных ложных вариантов:
```python
class GeneratedQuestion(BaseModel):
    text: str = Field(description="Текст интересного и нетривиального вопроса")
    correct_answer: str = Field(description="Краткий и точный правильный ответ")
    decoy_fallbacks: list[str] = Field(
        description="Ровно 7 правдоподобных уникальных ложных ответов для механизма Auto-Fallback",
        min_length=7,
        max_length=7,
    )
    explanation: str = Field(description="Краткий исторический/научный факт с пояснением")
```

### 1.2 Защита от Prompt Injection («Своя тема»)
- Пользовательская тема валидируется: длина до 40 символов, разрешены буквы, цифры, дефис, пробелы (запрещены спецсимволы, кавычки, фигурные скобки).
- В системном промпте тема передается строго в изолированном блоке `<user_topic>...</user_topic>` с жесткой директивой игнорировать любые системные команды внутри этого тега.

### 1.3 Redis Distributed Lock и защита от дублирования вызовов
- Генерацию вопроса инициирует **только хост**.
- На уровне комнаты выставляется распределённый замок в Redis: `room:{code}:lock` (TTL 30 сек). Параллельные запросы отклоняются с кодом `409 Conflict`.

### 1.4 Отказоустойчивость: таймаут и локальный Fallback-словарь
- Жесткий таймаут к Gemini API: 4 секунды.
- При ошибке сети, превышении квоты (Rate Limit) или таймауте сервер мгновенно загружает готовый вопрос из локального пула в PostgreSQL. Игра не останавливается из-за сбоев внешнего сервиса.

---

## 2. Предлагаемые изменения по файлам

### Backend (`backend/`)
- Добавить `google-genai>=0.1.1` в `pyproject.toml`.
- `app/schemas/question.py`: Pydantic-схема `GeneratedQuestion` с 7 `decoy_fallbacks`.
- `app/services/ai_service.py`: класс `GeminiQuestionService` с изоляцией промптов, кэшированием в Redis/БД, таймаутом и fallback-словарём.
- `app/models/question.py`: SQLModel таблица `questions` (включая сидирование начального пула резервных вопросов).
- `app/api/questions.py`: эндпоинты `GET /topics/suggestions` (3 темы дня) и `POST /questions/generate` (генерация с проверкой хоста и Distributed Lock).

### Client (`client/`)
- `ui/screens/lobby/widgets/topic_picker_dialog.dart`: диалог выбора темы (3 карточки + поле ввода своей темы с валидацией).
- `ui/widgets/pixel_loading_indicator.dart`: 8-bit анимация ожидания генерации.
- `state/question_provider.dart`: Riverpod провайдер тем и генерации.

---

## 3. План верификации
- **Pytest:** моки Gemini API, проверка генерации ровно 7 фейков, тест защиты от Prompt Injection, тест срабатывания Distributed Lock при одновременных запросах, проверка переключения на локальный fallback-словарь при таймауте.
- **Flutter test:** виджет-тест диалога выбора темы и состояния ожидания.
