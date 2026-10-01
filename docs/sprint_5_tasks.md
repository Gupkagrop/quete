# Задачи Спринта 5: Игровой цикл — «Вопрос и Таймеры» (День 29–35)

### 1. Модели игровых сессий и раундов (Backend)
- [ ] Backend: Создать SQLModel-модели `GameSession` и `Round` в `models/game.py`
- [ ] Backend: Сгенерировать и применить миграцию Alembic для таблиц `game_session` и `round`

### 2. State Machine и игровой движок (Backend)
- [ ] Backend: Реализовать класс `GameEngine` в `services/game_engine.py` (управление фазами матча)
- [ ] Backend: Реализовать серверный таймер фазы с рассылкой `phase_started` и меткой `ends_at`
- [ ] Backend: Исключить утечку `correct_answer` в исходящих WS-сообщениях клиентам
- [ ] Backend: Реализовать обработчик команды `start_game` от хоста

### 3. Стейт игры и синхронизация таймеров (Client)
- [ ] Client: Реализовать `GameProvider` (Riverpod) с подпиской на события фаз раунда
- [ ] Client: Создать процедурный виджет `PixelTimer` с динамической сменой цветов (зелёный/жёлтый/красный)
- [ ] Client: Добавить звуковой/визуальный сигнал при переходе таймера в критическую зону (<5 сек)

### 4. Экран вопроса (Client)
- [ ] Client: Настроить маршрут `/game/:code` в `go_router`
- [ ] Client: Сверстать экран `GameQuestionScreen` с 8-bit карточкой вопроса и индикатором номера раунда
- [ ] Client: Реализовать автоматический переход на экран блефа по истечении таймера

### 5. Тестирование и верификация (Pre-Commit Gate)
- [ ] Backend: Написать pytest тесты для переходов State Machine и валидации таймингов
- [ ] Client: Написать widget-тест таймера `PixelTimer` и экрана `GameQuestionScreen`
- [ ] Верификация: `uv run ruff check .`, `uv run pytest`, `flutter analyze`, `flutter test`
