# Задачи Спринта 4: Фича «Генерация вопросов ИИ» (День 22–28)

### 1. Зависимости и интеграция с Gemini (Backend)
- [ ] Backend: Добавить зависимость `google-genai` через `uv add`
- [ ] Backend: Создать Pydantic-схему `GeneratedQuestion` для Structured Outputs в `schemas/question.py`
- [ ] Backend: Реализовать `GeminiQuestionService` в `services/ai_service.py` с системным промптом генератора квизов

### 2. Кэширование и база данных (Backend)
- [ ] Backend: Создать SQLModel-модель `Question` (`id`, `topic`, `text`, `correct_answer`, `decoy_fallbacks`, `explanation`)
- [ ] Backend: Сгенерировать и применить миграцию Alembic для таблицы `question`
- [ ] Backend: Реализовать логику кэширования: поиск по нормализованной теме перед вызовом API

### 3. API тем и генерации (Backend)
- [ ] Backend: Реализовать эндпоинт `GET /topics/suggestions` (3 темы дня)
- [ ] Backend: Реализовать эндпоинт `POST /questions/generate` (генерация по теме)
- [ ] Backend: Интегрировать выбор темы в WebSocket-события лобби (`topic_selected`, `generating_question`)

### 4. Клиентский интерфейс выбора темы (Client)
- [ ] Client: Сверстать диалог выбора темы `TopicPickerDialog` (3 ретро-кнопки + поле «Своя тема»)
- [ ] Client: Создать процедурный 8-bit прогресс-бар `PixelLoadingIndicator`
- [ ] Client: Реализовать `QuestionProvider` (Riverpod) для обработки состояний `loading`, `ready`, `error`

### 5. Тестирование и верификация (Pre-Commit Gate)
- [ ] Backend: Написать pytest тесты с моками Gemini API (успешная генерация, обработка ошибок API, отдача из кэша)
- [ ] Client: Написать widget-тесты диалога выбора темы и анимации ожидания ИИ
- [ ] Верификация: `uv run ruff check .`, `uv run pytest`, `flutter analyze`, `flutter test`
