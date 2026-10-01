# Задачи Спринта 6: Игровой цикл — «Блеф» (День 36–42)

### 1. Модель ответов и миграции (Backend)
- [ ] Backend: Добавить зависимость `rapidfuzz` через `uv add` для эффективного нечеткого сравнения
- [ ] Backend: Создать SQLModel-модель `BluffAnswer` (`round_id`, `player_id`, `answer_text`, `is_fallback`)
- [ ] Backend: Сгенерировать и применить миграцию Alembic для таблицы `bluff_answer`

### 2. Сервис валидации и WebSocket обработка (Backend)
- [ ] Backend: Реализовать алгоритм Fuzzy Matching в `services/bluff_validator.py` (порог сходства >80% возвращает ошибку `BLUFF_MATCHES_TRUTH`)
- [ ] Backend: Реализовать обработку входящего WS-события `submit_bluff`
- [ ] Backend: Исключить текст ответа из широковещательного события `player_submitted` (передавать только `player_id`)
- [ ] Backend: Реализовать атомарный счетчик сданных ответов в Redis для исключения гонок при досрочном переходе

### 3. Пользовательский интерфейс блефа (Client)
- [ ] Client: Сверстать экран `BluffInputScreen` (поле ввода, счётчик символов 0/80, кнопка подтверждения)
- [ ] Client: Реализовать отображение серверных ошибок валидации (включая `BLUFF_MATCHES_TRUTH`)
- [ ] Client: Сверстать экран ожидания `WaitingPlayersWidget` со статусами готовности каждого игрока

### 4. Тестирование и верификация (Pre-Commit Gate)
- [ ] Backend: Написать pytest тесты для `BluffValidator` (проверка опечаток, артиклей, расстояния Левенштейна)
- [ ] Backend: Написать pytest тест на сокрытие текста ответа в WS payload
- [ ] Backend: Написать pytest тест на атомарный досрочный переход при параллельной отправке 8 ответов
- [ ] Client: Написать widget-тест экрана ввода блефа и отображения статуса готовности
- [ ] Верификация: `uv run ruff check .`, `uv run pytest`, `flutter analyze`, `flutter test`
