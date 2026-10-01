# Задачи Спринта 4: Фича «Генерация вопросов ИИ» (День 22–28)

### 1. Зависимости и интеграция с Gemini (Backend)
- [ ] Backend: Добавить зависимость `google-genai` через `uv add`
- [ ] Backend: Создать Pydantic-схему `GeneratedQuestion` со строгим лимитом ровно 7 `decoy_fallbacks` в `schemas/question.py`
- [ ] Backend: Реализовать `GeminiQuestionService` в `services/ai_service.py` с изоляцией контекста и защитой от Prompt Injection
- [ ] Backend: Внедрить жесткий таймаут (4 сек) на обращение к Gemini API и локальный Fallback-словарь

### 2. Кэширование, блокировки и база данных (Backend)
- [ ] Backend: Создать SQLModel-модель `Question` (`id`, `topic`, `text`, `correct_answer`, `decoy_fallbacks: JSONB`, `explanation`)
- [ ] Backend: Сгенерировать и применить миграцию Alembic для таблицы `question`
- [ ] Backend: Создать сид-скрипт для заполнения начального пула резервных вопросов (Fallback-словарь)
- [ ] Backend: Реализовать двухфакторное кэширование: Redis (`question_cache:{topic}`) + БД
- [ ] Backend: Реализовать Redis Distributed Lock `room:{code}:lock` для защиты от параллельного спама генерацией

### 3. API тем и генерации (Backend)
- [ ] Backend: Реализовать эндпоинт `GET /topics/suggestions` (3 темы дня)
- [ ] Backend: Реализовать эндпоинт `POST /questions/generate` (только для хоста с валидацией пользовательской темы)
- [ ] Backend: Интегрировать выбор темы в WebSocket-события лобби (`topic_selected`, `generating_question`)

### 4. Клиентский интерфейс выбора темы (Client)
- [ ] Client: Сверстать диалог выбора темы `TopicPickerDialog` (3 ретро-кнопки + поле «Своя тема» с валидацией спецсимволов)
- [ ] Client: Создать процедурный 8-bit прогресс-бар `PixelLoadingIndicator`
- [ ] Client: Реализовать `QuestionProvider` (Riverpod) для обработки состояний `loading`, `ready`, `error`

### 5. Тестирование и верификация (Pre-Commit Gate)
- [ ] Backend: Написать pytest тесты с моками Gemini API (успешная генерация 7 фейков, обработка ошибок, кэширование)
- [ ] Backend: Написать тест на защиту от Prompt Injection в поле темы
- [ ] Backend: Написать тест на Redis Distributed Lock при параллельных запросах генерации
- [ ] Backend: Написать тест на мгновенное переключение на локальный словарь при таймауте LLM
- [ ] Client: Написать widget-тесты диалога выбора темы и анимации ожидания ИИ
- [ ] Верификация: `uv run ruff check .`, `uv run pytest`, `flutter analyze`, `flutter test`
