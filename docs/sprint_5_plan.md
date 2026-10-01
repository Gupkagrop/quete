---
request_feedback: true
user_facing: true
---

# Спринт 5: Игровой цикл — «Core Game Loop MVP» (День 29–35)

## Goal Description
Цель пятого спринта — реализовать полноценный вертикальный срез игрового раунда (Core Game Loop MVP). По окончании спринта игра становится **100% играбельной**: от перехода из лобби к чтению вопроса, вводу блефа, голосованию и показу результатов раунда с очками. Все фазы раунда работают синхронно между сервером и всеми клиентами на базе временных меток в Redis и Redis Pub/Sub, а результаты и сгенерированные опции фиксируются в БД.

---

## 1. Архитектурные решения

### 1.1 Серверная State Machine полного раунда
Раунд состоит из 4 последовательных фаз:
1. `QUESTION_READING` (20 сек) — чтение вопроса. Текст вопроса рассылается клиентам, правильный ответ хранится строго на сервере.
2. `BLUFF_SUBMISSION` (45 сек) — приём ложных вариантов через WS `submit_bluff: {"answer_text": "..."}`.
3. `VOTING` (30 сек) — перед стартом сервер генерирует пул вариантов, сохраняет его в `rounds.voting_options` (JSONB) и рассылает клиентам `start_voting`. Приём голосов через WS `submit_vote: {"option_id": "..."}`. Запрет на выбор своего ответа.
4. `ROUND_RESULTS` (15 сек) — показ раскрытия карт, кто за кого проголосовал. Начисление очков (+1000 за истину, +500 за успешный обман) с сохранением в таблицу `session_players` в единой транзакции.

### 1.2 Персистентность вариантов и надежные таймеры
- **Фиксация опций:** массив `voting_options` в таблице `rounds` гарантирует, что `option_id` имеет жесткую привязку к авторам и истине, переживает перезапуск воркера и доступен при State Recovery.
- **Персистентность счета:** таблица `session_players` хранит актуальный баланс очков каждого игрока сессии.
- **Таймеры:** управляются дедлайнами `phase_ends_at` в Redis и БД (запрещено использовать `asyncio.sleep`).
- **Рассылка:** через Redis Pub/Sub канал `room:{code}:channel`.

---

## 2. Предлагаемые изменения по файлам

### Backend (`backend/`)
- `app/models/game.py`: сущности `GameSession`, `SessionPlayer` (состав и счет), `Round` (с полем `voting_options: JSONB`).
- `app/models/bluff.py`: таблица `bluff_answers` (`round_id`, `player_id`, `answer_text`).
- `app/models/vote.py`: таблица `votes` (`round_id`, `voter_id`, `option_id`).
- `app/services/game_engine.py`: State Machine переходов между 4 фазами раунда.
- `app/services/score_engine.py`: подсчет очков и транзакционное обновление `session_players.score`.
- WS обработчики: `start_game`, `submit_bluff`, `submit_vote`.

### Client (`client/`)
- `state/game_provider.dart`: Riverpod провайдер управления раундом и фазами.
- `ui/screens/game/game_container_screen.dart`: единый экран-контейнер со сменой представлений по `GamePhase`.
- `ui/screens/game/widgets/question_view.dart`: карточка вопроса с номером раунда.
- `ui/screens/game/widgets/bluff_input_view.dart`: ввод ложного ответа (1–80 симв).
- `ui/screens/game/widgets/voting_view.dart`: сетка карточек голосования с блокировкой своего варианта.
- `ui/screens/game/widgets/round_results_view.dart`: раскладка голосов и начисленных очков.
- `ui/widgets/pixel_timer.dart`: процедурный 8-bit таймер.

---

## 3. План верификации
- **Pytest:** сквозной тест прохождения полного цикла раунда (старт -> вопрос -> сабмит блефов -> голосование -> расчет очков в `session_players`), проверка сохранения `voting_options` в БД.
- **Flutter test:** виджет-тесты переключения представлений по фазам раунда, тест тиканья `PixelTimer`.
- **E2E тест в браузере:** ручной запуск матча с 2 локальными клиентами и прохождение полного раунда.
