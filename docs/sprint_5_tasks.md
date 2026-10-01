# Задачи Спринта 5: Игровой цикл — «Core Game Loop MVP» (День 29–35)

### 1. Модели базы данных и миграции (Backend)
- [ ] Backend: Создать SQLModel-модели `GameSession`, `SessionPlayer`, `Round` (с полем `voting_options: JSONB`), `BluffAnswer`, `Vote` с каскадным удалением
- [ ] Backend: Сгенерировать и применить миграцию Alembic для таблиц игрового цикла

### 2. State Machine полного раунда и таймеры (Backend)
- [ ] Backend: Реализовать `GameEngine` в `services/game_engine.py` (последовательность 4 фаз: чтение, блеф, голосование, итоги)
- [ ] Backend: Реализовать таймеры фаз на базе меток времени `ends_at` в Redis
- [ ] Backend: Реализовать сохранение сгенерированных вариантов в `Round.voting_options` перед рассылкой `start_voting`
- [ ] Backend: Реализовать обработку входящих событий `submit_bluff` и `submit_vote`
- [ ] Backend: Реализовать `ScoreEngine` с транзакционной записью начисленных очков в `SessionPlayer.score`

### 3. Стейт игры и синхронизация таймеров (Client)
- [ ] Client: Реализовать `GameProvider` (Riverpod) с обработкой всех 4 фаз раунда
- [ ] Client: Создать процедурный виджет `PixelTimer` с динамической сменой цветов
- [ ] Client: Настроить отправку WS-событий `submit_bluff` и `submit_vote` через `WebSocketService`

### 4. Пользовательский интерфейс фаз раунда (Client)
- [ ] Client: Сверстать экран `GameContainerScreen` с динамическим переключением представлений по `GamePhase`
- [ ] Client: Сверстать компонент чтения вопроса `QuestionView`
- [ ] Client: Сверстать компонент ввода лжи `BluffInputView`
- [ ] Client: Сверстать сетку карточек голосования `VotingView` с блокировкой своего варианта
- [ ] Client: Сверстать экран результатов раунда `RoundResultsView`

### 5. Тестирование и верификация (Pre-Commit Gate)
- [ ] Backend: Написать pytest тесты для переходов State Machine и последовательной смены 4 фаз
- [ ] Backend: Написать pytest тест на подсчёт очков с сохранением в `session_players` и фиксацию `voting_options` в БД
- [ ] Client: Написать widget-тесты компонентов фаз раунда и таймера `PixelTimer`
- [ ] Верификация: `uv run ruff check .`, `uv run pytest`, `flutter analyze`, `flutter test`
