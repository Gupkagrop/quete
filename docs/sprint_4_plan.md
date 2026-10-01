---
request_feedback: true
user_facing: true
---

# Спринт 4: Фича «Генерация вопросов ИИ» (День 22–28)

## Goal Description
Цель четвёртого спринта — внедрить интеграцию с Google Gemini 1.5 Flash для генерации вопросов викторины со строгой валидацией по JSON Schema (Structured Outputs), кэшированием для экономии квот и интерфейсом выбора темы игры.

---

## 1. Архитектурные решения

### 1.1 Схема Structured Outputs (Pydantic)
Все вызовы Gemini API используют режим Structured Outputs с Pydantic-моделью:
```python
class GeneratedQuestion(BaseModel):
    text: str = Field(description="Текст интересного и нетривиального вопроса")
    correct_answer: str = Field(description="Краткий и точный правильный ответ")
    decoy_fallbacks: list[str] = Field(
        description="2-3 правдоподобных ложных ответа для Auto-Fallback механизма",
        min_length=2,
        max_length=3,
    )
    explanation: str = Field(description="Краткий исторический/научный факт с пояснением")
```

### 1.2 Двухуровневое кэширование
1. **Redis:** `question_cache:{normalized_topic}` (быстрая отдача, TTL 24 ч).
2. **PostgreSQL:** таблица `questions` (долговременное хранение пула сгенерированных вопросов).

### 1.3 Механика выбора темы
- Хосту (или игрокам по очереди) предлагаются 3 случайные готовые темы дня + опция «Своя тема» со свободным вводом (до 40 символов).

---

## 2. Предлагаемые изменения по файлам

### Backend (`backend/`)
- Добавить `google-genai>=0.1.1` в `pyproject.toml`.
- `app/services/ai_service.py`: класс `GeminiQuestionService` для отправки структурированных промптов и работы с кэшем.
- `app/models/question.py`: SQLModel таблица `questions`.
- `app/api/questions.py`: эндпоинты `GET /topics/suggestions` (3 темы дня) и `POST /questions/generate` (генерация по теме).

### Client (`client/`)
- `ui/screens/lobby/widgets/topic_picker_dialog.dart`: ретро-окно выбора темы с 3 карточками и полем для ввода своей темы.
- `ui/widgets/pixel_loading_indicator.dart`: процедурная 8-bit анимация загрузки («ИИ генерирует вопрос...»).
- `state/question_provider.dart`: Riverpod провайдер управления темами и генерацией.

---

## 3. План верификации
- **Pytest:** строгие моки `google-genai` через `unittest.mock`, проверка валидации Structured Outputs, проверка отдачи из кэша.
- **Flutter test:** виджет-тест диалога выбора темы и состояния индикатора загрузки.
