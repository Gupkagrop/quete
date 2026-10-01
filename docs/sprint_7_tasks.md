# Задачи Спринта 7: Отказоустойчивость — «Auto-Fallback и Слияние ответов» (День 43–49)

### 1. Сервис Auto-Fallback и защита от дублей (Backend)
- [ ] Backend: Добавить индекс `UNIQUE(round_id, player_id)` для таблицы `bluff_answers`
- [ ] Backend: Реализовать класс `FallbackService` в `services/fallback_service.py` с выбором из 7 `decoy_fallbacks` вопроса
- [ ] Backend: Реализовать идемпотентную обработку `IntegrityError` при одновременном ручном сабмите и таймауте

### 2. Алгоритм дедупликации и слияния (Backend)
- [ ] Backend: Реализовать алгоритм `AnswerMerger` (нормализация строк, объединение дублирующихся ответов)
- [ ] Backend: Запись сформированного массива опций с соавторами в `Round.voting_options`
- [ ] Backend: Реализовать разделение очков (+500 / количество авторов) и начисление в `SessionPlayer.score`

### 3. Реконнект и восстановление состояния (Backend & Client)
- [ ] Backend: Реализовать отдачу актуальных `voting_options` в эндпоинте `GET /rooms/{code}/state` при смене фаз
- [ ] Client: Реализовать виджет `ReconnectBanner` с индикатором процесса переподключения
- [ ] Client: Реализовать вызов `GET /rooms/{code}/state` сразу после восстановления WS соединения в `GameProvider`

### 4. Тестирование и верификация (Pre-Commit Gate)
- [ ] Backend: Написать pytest тест на предотвращение дублирования ответа одного игрока при таймауте (проверка `UNIQUE`)
- [ ] Backend: Написать pytest тесты на дедупликацию одинаковых ответов от 2 и 3 игроков с корректным сохранением в `voting_options`
- [ ] Client: Написать unit-тест восстановления стейта при получении ответа от `GET /rooms/{code}/state`
- [ ] Верификация: `uv run ruff check .`, `uv run pytest`, `flutter analyze`, `flutter test`
