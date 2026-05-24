<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
require_once 'core/db.php';
$user = getUserById($_SESSION['user_id']);

$lobbyId = (int) ($_GET['lobby_id'] ?? 0);
$lobby = getLobbyById($lobbyId);
if (!$lobby) {
    header('Location: hub.php');
    exit;
}
if (!$lobby['is_active']) {
    header('Location: hub.php');
    exit;
}

$players = getLobbyPlayers($lobbyId);
$userInLobby = false;
$isHost = false;
$isResponsible = false;
foreach ($players as $p) {
    if ((int) $p['user_id'] == $_SESSION['user_id']) {
        $userInLobby = true;
    }
    if ((int) $lobby['host_id'] == $_SESSION['user_id']) {
        $isHost = true;
    }
    if ((int) $lobby['responsible'] == $_SESSION['user_id']) {
        $isResponsible = true;
    }
}
if (!$userInLobby) {
    header('Location: hub.php');
    exit;
}

$scores = getPlayerScores($lobbyId);

// Генерация одноразового билета для WebSocket
$wsTicket = generateWebSocketTicket($_SESSION['user_id']);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Игровой экран квиза Куэте. Отвечай на уникальные вопросы ИИ и обхитри соперников своими фейковыми ответами!">
    <link rel="canonical" href="https://quete.ru/game.php">
    <title>Куэте - Игра</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/game.css">
    <script src="assets/js/websocket-client.js?v=<?php echo time(); ?>"></script>
    <style>
        .timer-warning { color: #ff4444; }
        .btn-disabled { opacity: 0.5; cursor: not-allowed !important; background-color: #999; }
        .btn-disabled:hover { background-color: #999; }
        .result-correct { color: #4abb5f; font-weight: bold; font-size: 18px; }
        .result-item { padding: 10px; background: rgba(0,0,0,0.1); margin: 10px 0; border-radius: 5px; }
        .player-name { font-size: 12px; color: #999; margin-top: 5px; }
        .votes-badge { display: inline-block; font-size: 11px; background: rgba(255,140,45,0.3); padding: 3px 8px; border-radius: 3px; margin-top: 5px; }
        .podium-place { margin: 20px 0; padding: 15px; background: rgba(0,0,0,0.1); border-radius: 5px; text-align: center; }
        .medal { font-size: 40px; margin-bottom: 10px; }
        .podium-name { font-size: 18px; font-weight: bold; margin: 10px 0; }
        .podium-score { font-size: 16px; color: #ffcc00; font-weight: bold; }
    </style>
</head>
<body>

<!-- ТАБЛИЦА СЧЕТА -->
<div class="global-scoreboard" id="scoreboard">
    <!-- Заполняется через JavaScript -->
</div>

<div class="page-wrapper">
    <div class="chat-game-layout">
        
        <!-- ЧАТ (размещен слева от игрового окна на ПК) -->
        <?php include 'views/chat.php'; ?>

        <!-- ГЛАВНОЕ ОКНО -->
        <div class="game-window">
            <div class="window-header">

                <div class="window-title" id="dynamic-window-title">Загрузка...</div>
                <a href="javascript:void(0)" class="user-icon" onclick="toggleStatsPopup()" style="right: 10px;">
                    <svg viewBox="0 0 24 24">
                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                        <circle cx="12" cy="7" r="4"></circle>
                    </svg>
                </a>
                <?php $showLeaveButton = true; include 'views/user_stats.php'; ?>
            </div>
            
            <div class="status-strip">
                <div>
                    <span style="color: #ff8c2d;">Раунд:</span> <span id="current-round">1</span>/<span id="total-rounds">3</span>
                </div>
                <div>
                    <span style="color: #ffeb3b;">Вопрос:</span> <span id="current-question-num">1</span>/<span id="total-questions">3</span>
                </div>
                <div id="fake-timer-container" style="display: none; min-width: 100px; text-align: right;">
                    <span class="status-strip-timer">⏱ <span id="fake-timer-display"></span></span>
                </div>
            </div>


            <!-- STATE: ВЫБОР ТЕМЫ (только для ответственного) -->
            <div class="game-state active" id="state-topic">
                <div class="game-inner-panel" id="topic-input-container">
                    <div class="panel-title">Выбери тему вопроса</div>
                    <div style="padding: 20px;">
                        <input type="text" id="topic-input" class="auth-input" placeholder="Например: История, Наука, Спорт..." maxlength="50" required>
                        <button class="btn-game" id="topic-submit-btn" onclick="submitTopic()" style="margin-top: 20px; width: 100%;">Отправить тему</button>
                        <div id="topic-error" style="color: #ff4d4d; margin-top: 10px; text-align: center; min-height: 20px; font-size: 12px;"></div>
                    </div>
                </div>
                <div class="game-inner-panel" id="topic-generating-container" style="display: none;">
                    <div class="panel-title" style="text-align: center;">Пожалуйста, подождите</div>
                    <div style="font-size: 18px; color: #fff; line-height: 1.6; font-weight: bold; text-align: center; margin-top: 40px;">
                        Генерируем вопрос. Пожалуйста, подождите
                    </div>
                    <img src="assets/img/brain_gears.png" class="state-img" alt="Thinking">
                </div>
            </div>

            <!-- STATE: ОЖИДАНИЕ ТЕМЫ (для остальных) -->
            <div class="game-state" id="state-wait-topic">
                <div class="game-inner-panel">
                    <div class="panel-title" style="text-align: center;">Пожалуйста, подождите</div>
                    <div class="waiting-text" style="margin-top: 40px; font-size: 20px;" id="wait-topic-text">
                        <span id="responsible-player-name" style="font-weight: bold; color: #ff8c2d;">...</span> выбирает тему
                    </div>
                    <img src="assets/img/brain_gears.png" class="state-img" alt="Thinking">
                </div>
            </div>

            <!-- STATE: ВВОД ФЕЙКА -->
            <div class="game-state" id="state-fake">
                <div class="game-inner-panel">
                    <div class="question-box">
                        <div class="q-label">Вопрос:</div>
                        <div class="q-text" id="question-text">Загрузка...</div>
                    </div>
                    
                    <div style="margin-top: 30px;">
                        <img src="assets/img/spy_mask.png" class="state-img" alt="Fake">
                        <div class="q-label">Придумай фейковый ответ:</div>
                        <form id="fake-form" onsubmit="submitFake(event)">
                            <input type="text" id="fake-input" class="auth-input" placeholder="Введи свой фейковый ответ..." maxlength="200" required>
                            <button type="submit" class="btn-game" id="fake-submit-btn" style="margin-top: 15px; width: 100%;">Отправить фейк</button>
                        </form>
                        <div id="fake-error" style="color: #ff4d4d; margin-top: 10px; text-align: center; min-height: 20px; font-size: 12px;"></div>
                    </div>
                </div>
            </div>

            <!-- STATE: ОЖИДАНИЕ ФЕЙКОВ -->
            <div class="game-state" id="state-wait-fakes">
                <div class="game-inner-panel">
                    <div class="question-box">
                        <div class="q-label">Вопрос:</div>
                        <div class="q-text" id="question-text-wait">Загрузка...</div>
                    </div>
                    
                    <div class="waiting-text" style="margin-top: 40px; text-align: center; font-size: 18px;">
                        Все игроки предлагают свои фейковые ответы...
                        <img src="assets/img/spy_mask.png" class="state-img" alt="Waiting for fakes">
                    </div>
                </div>
            </div>

            <!-- STATE: ГОЛОСОВАНИЕ (выбор ответа) -->
            <div class="game-state" id="state-answer">
                <div class="game-inner-panel">
                    <div class="question-box">
                        <div class="q-label">Какой ответ правильный?</div>
                        <div class="q-text" id="question-text-vote">Загрузка...</div>
                    </div>
                    <img src="assets/img/question_mark.png" class="state-img" alt="Vote">
                    
                    <div class="game-grid game-grid-3" id="answers-grid" style="margin-top: 30px;">
                        <!-- Заполняется через JavaScript -->
                    </div>
                    
                    <button class="btn-game" id="vote-submit-btn" onclick="submitVote()" style="margin-top: 30px; width: 100%;">Голосовать</button>
                    <div id="vote-error" style="color: #ff4d4d; margin-top: 10px; text-align: center; min-height: 20px; font-size: 12px;"></div>
                </div>
            </div>

            <!-- STATE: РЕЗУЛЬТАТЫ РАУНДА -->
            <div class="game-state" id="state-results">
                <div class="game-inner-panel">
                    <div class="panel-title">Итоги раунда</div>
                    <img src="assets/img/judge_sign.png" class="state-img" alt="Results">
                    
                    <div class="result-item">
                        <div style="font-size: 12px; color: #999;">Правильный ответ:</div>
                        <div class="result-correct" id="correct-answer-display">--</div>
                    </div>
    
                    <div id="results-list" style="margin: 20px 0; max-height: 300px; overflow-y: auto;">
                        <!-- Заполняется через JavaScript -->
                    </div>
    
                    <div style="text-align: center; margin-top: 40px; color: #999; font-size: 14px;" id="results-countdown-text">
                        Автоматический переход через <span id="results-countdown-seconds" style="font-weight: bold; color: #ffeb3b;">10</span> сек...
                    </div>
                </div>
            </div>

            <!-- STATE: ФИНАЛ (подиум) -->
            <div class="game-state" id="state-podium">
                <div class="game-inner-panel">
                    <div class="panel-title">Игра завершена! 🎉</div>
                    <div id="podium-body" style="margin: 30px 0;">
                        <!-- Заполнится через JavaScript -->
                    </div>
                    <div style="text-align: center; margin-top: 40px; color: #999; font-size: 14px;">
                        Автоматический возврат в лобби через 10 сек...
                    </div>
                </div>
            </div>

            <!-- STATE: ГОТОВНОСТЬ К РЕСТАРТУ -->
            <div class="game-state" id="state-ready-to-restart">
                <div class="game-inner-panel">
                    <div class="panel-title">Игра завершена</div>
                    <div style="margin: 40px 0; text-align: center;">
                        <p style="font-size: 18px; margin-bottom: 20px;">Все готовы начать новую игру?</p>
                        <button class="btn-game" onclick="window.location.href='lobby.php?lobby_id=' + LOBBY_ID" style="width: 100%;">Вернуться в лобби</button>
                    </div>
                </div>
            </div>
        </div>
        
    </div>
</div>

<script>
const LOBBY_ID = <?php echo $lobbyId; ?>;
const USER_ID = <?php echo $_SESSION['user_id']; ?>;
const IS_HOST = <?php echo $isHost ? 'true' : 'false'; ?>;
const IS_RESPONSIBLE = <?php echo $isResponsible ? 'true' : 'false'; ?>;
const ROUNDS_COUNT = <?php echo ROUNDS_COUNT; ?>;
const POLLING_INTERVAL = 10000; // 10 секунд как fallback
const CSRF_TOKEN = '<?php echo getCsrfToken(); ?>';
const WS_PORT = <?php echo WS_CLIENT_PORT; ?>;
const WS_HOST = "<?php echo WS_CLIENT_HOST; ?>";
const WS_TICKET = "<?php echo $wsTicket; ?>";

let selectedAnswer = '';
let socketClient = null;
let pollInterval = null;
let currentQuestionId = 0;
let userOwnAnswer = null;
let hasVoted = false;
let currentState = null;
let answersCache = null;
let resultsStartTime = null;
let podiumStartTime = null;
let automaticTransitionTimer = null;
let isTransitioning = false;
let isGeneratingQuestion = false;
let resultsCountdownInterval = null;

function startConnection() {
    socketClient = new GameWebSocketClient(LOBBY_ID, USER_ID, {
        host: WS_HOST,
        port: WS_PORT,
        ticket: WS_TICKET,
        onMessage: handleSocketMessage,
        onConnect: () => {
            console.log('Game connected to WebSocket');
            updateGameState();
        }
    });
    socketClient.connect();
    
    // Fallback polling (срабатывает только при отключенном сокет-соединении)
    pollInterval = setInterval(() => {
        if (!socketClient || !socketClient.isConnected()) {
            updateGameState();
        }
    }, POLLING_INTERVAL);
}

function handleSocketMessage(msg) {
    console.log('WS Message:', msg);
    
    if (msg.type === 'player_action' && msg.action_type === 'lobby_deleted') {
        if (!IS_HOST) {
            alert('Создатель покинул лобби. Вы будете перенаправлены в хаб.');
            window.location.href = 'hub.php';
        }
        return;
    }
    
    if (msg.type === 'player_action' && msg.action_type === 'return_to_lobby') {
        window.location.href = `lobby.php?lobby_id=${LOBBY_ID}`;
        return;
    }
    
    if (msg.type === 'game_update' || msg.type === 'player_action' || msg.type === 'player_joined' || msg.type === 'player_left') {
        if (msg.type === 'player_action' && msg.action_type === 'generating_question') {
            isGeneratingQuestion = true;
            const waitText = document.getElementById('wait-topic-text');
            if (waitText) {
                waitText.innerHTML = `Генерируем вопрос. Пожалуйста, подождите`;
            }
        }
        updateGameState();
    } else if (msg.type === 'chat_message') {
        appendChatMessage(msg);
    }
}


function updateGameState() {
    if (!LOBBY_ID) return;
    
    fetch(`ajax/game_state_update.php?lobby_id=${LOBBY_ID}&csrf_token=${CSRF_TOKEN}`)
        .then(response => {
            if (!response.ok) {
                return response.json().then(data => { throw data; });
            }
            return response.json();
        })
        .then(data => {
            if (data.error) throw data;
            
            // Обновить интерфейс
            updateScoreboard(data.scores);
            document.getElementById('current-round').textContent = data.currentRound;
            document.getElementById('total-rounds').textContent = data.totalRounds;
            
            let questionNum = data.currentQuestion ? data.currentQuestion.question_number : (data.questionsInRound + 1);
            if (questionNum > 3) questionNum = 3;
            if (document.getElementById('current-question-num')) {
                document.getElementById('current-question-num').textContent = questionNum;
            }

            const timeLeft = Math.max(0, data.fakeAnswerTimeout - data.timeoutElapsed);
            startLocalTimer(timeLeft);

            if (data.currentQuestion) {
                if (data.currentQuestion.id !== currentQuestionId) {
                    hasVoted = false;
                    currentQuestionId = data.currentQuestion.id;
                }
                userOwnAnswer = data.userOwnAnswer;
                isGeneratingQuestion = false;
            }

            if (data.autoFakesApplied && data.autoFakesApplied.includes(USER_ID)) {
                // Можно добавить уведомление: "Время вышло! Был выбран случайный вариант."
                console.log("Auto-fake applied for you");
            }

            let newState = determineGameState(data);

            if (newState !== currentState) {
                answersCache = null;
                resultsStartTime = null;
                podiumStartTime = null;
                clearTimeout(automaticTransitionTimer);
                if (resultsCountdownInterval) {
                    clearInterval(resultsCountdownInterval);
                    resultsCountdownInterval = null;
                }
                
                const voteBtn = document.getElementById('vote-submit-btn');
                if (voteBtn) {
                    voteBtn.disabled = false;
                    voteBtn.classList.remove('btn-disabled');
                    voteBtn.textContent = 'Голосовать';
                }
                
                const fakeBtn = document.getElementById('fake-submit-btn');
                if (fakeBtn) {
                    fakeBtn.disabled = false;
                    fakeBtn.classList.remove('btn-disabled');
                    fakeBtn.textContent = 'Отправить фейк';
                }
                
                const fakeInput = document.getElementById('fake-input');
                if (fakeInput) {
                    fakeInput.disabled = false;
                    fakeInput.value = '';
                }
            }

            currentState = newState;

            if (newState === 'topic') {
                switchState('state-topic', 'Выбери тему вопроса');
                const tInputC = document.getElementById('topic-input-container');
                const tGenC = document.getElementById('topic-generating-container');
                if (tInputC) tInputC.style.display = 'block';
                if (tGenC) tGenC.style.display = 'none';
                
                const tBtn = document.getElementById('topic-submit-btn');
                if (tBtn) {
                    tBtn.disabled = false;
                    tBtn.classList.remove('btn-disabled');
                }
                const tInput = document.getElementById('topic-input');
                if (tInput && isTransitioning) {
                    tInput.value = ''; // очищаем только если только что перешли
                }
            } else if (newState === 'wait-topic') {
                switchState('state-wait-topic', 'Пожалуйста, подождите');
                const waitText = document.getElementById('wait-topic-text');
                const isGenerating = data.isGenerating || isGeneratingQuestion;
                
                if (isGenerating && waitText) {
                    waitText.innerHTML = `Генерируем вопрос. Пожалуйста, подождите`;
                } else {
                    const resp = data.players.find(p => p.user_id == data.lobby.responsible);
                    const respName = resp ? GameWebSocketClient.escapeHtml(resp.username) : 'Другой игрок';
                    if (waitText) {
                        waitText.innerHTML = `<span id="responsible-player-name" style="font-weight: bold; color: #ff8c2d;">${respName}</span> выбирает тему`;
                    }
                }
            } else if (newState === 'fake') {
                switchState('state-fake', `Раунд ${data.currentRound}/${ROUNDS_COUNT} - Введи фейк`);
                displayQuestion(data.currentQuestion, 'question-text');
            } else if (newState === 'wait-fakes') {
                switchState('state-wait-fakes', `Раунд ${data.currentRound}/${ROUNDS_COUNT}`);
                displayQuestion(data.currentQuestion, 'question-text-wait');
            } else if (newState === 'voting') {
                switchState('state-answer', `Раунд ${data.currentRound}/${ROUNDS_COUNT} - Выбери правильный`);
                displayQuestion(data.currentQuestion, 'question-text-vote');
                if (!answersCache) {
                    answersCache = cacheAndFilterAnswers(data);
                    renderAnswersFromCache();
                }
            } else if (newState === 'results') {
                switchState('state-results', `Итоги раунда ${data.currentRound}/${ROUNDS_COUNT}`);
                if (!resultsStartTime) {
                    resultsStartTime = Date.now();
                    displayResults(data);
                    
                    if (automaticTransitionTimer) clearTimeout(automaticTransitionTimer);
                    startResultsCountdown();
                    
                    if (IS_HOST) {
                        // Начинаем генерацию следующего вопроса сразу в фоновом режиме
                        transitionToNextQuestion();
                    }
                }
            } else if (newState === 'podium') {
                switchState('state-podium', 'Игра завершена! 🎉');
                if (!podiumStartTime) {
                    podiumStartTime = Date.now();
                    displayPodium(data.scores);
                    if (automaticTransitionTimer) clearTimeout(automaticTransitionTimer);
                    if (IS_HOST) {
                        automaticTransitionTimer = setTimeout(() => {
                            fetch('ajax/reset_lobby.php', {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/json' },
                                body: JSON.stringify({ lobby_id: LOBBY_ID, csrf_token: CSRF_TOKEN })
                            }).then(() => {
                                if (socketClient) socketClient.sendAction('return_to_lobby');
                                window.location.href = `lobby.php?lobby_id=${LOBBY_ID}`;
                            });
                        }, 10000);
                    }
                }
            }
        })
        .catch(error => {
            console.error('Update state error:', error);
            if (error.error === 'Lobby not found' || error.error === 'Forbidden') {
                if (pollInterval) clearInterval(pollInterval);
                if (automaticTransitionTimer) clearTimeout(automaticTransitionTimer);
                alert('Лобби закрыто или вы были исключены. Вы будете перенаправлены в хаб.');
                window.location.href = 'hub.php';
            }
        });
}

function determineGameState(data) {
    if (!data.lobby.is_active) {
        return 'podium';
    }
    if (!data.currentQuestion) {
        return (data.lobby.responsible == USER_ID) ? 'topic' : 'wait-topic';
    }

    const sentFake = data.userOwnAnswer !== null;
    if (!sentFake && !data.allPlayersSubmittedFakes) return 'fake';
    if (!data.allPlayersSubmittedFakes) return 'wait-fakes';
    if (!hasVoted && !data.allVoted) return 'voting';
    if (data.allVoted) return 'results';
    return 'wait-fakes';
}

function cacheAndFilterAnswers(data) {
    if (!data.answers) return [];
    let answers = Object.values(data.answers);
    answers = answers.filter(a => a.text !== data.userOwnAnswer);
    return shuffleArray(answers);
}

function renderAnswersFromCache() {
    const grid = document.getElementById('answers-grid');
    if (!grid || !answersCache) return;
    selectedAnswer = '';
    
    grid.innerHTML = ''; // Очищаем контейнер
    answersCache.forEach((a) => {
        const btn = document.createElement('button');
        btn.className = 'card-btn answer-btn';
        
        const div = document.createElement('div');
        div.textContent = a.text; // Безопасно выводим текст (XSS-safe)
        btn.appendChild(div);
        
        btn.addEventListener('click', function() {
            selectAnswer(btn, a.text);
        });
        
        grid.appendChild(btn);
    });
}

function switchState(stateId, title) {
    document.querySelectorAll('.game-state').forEach(s => s.classList.remove('active'));
    document.getElementById(stateId)?.classList.add('active');
    document.getElementById('dynamic-window-title').textContent = title;
}

function updateScoreboard(scores) {
    const board = document.getElementById('scoreboard');
    board.innerHTML = scores.map((s, i) => {
        const safeName = GameWebSocketClient.escapeHtml(s.username);
        return `<div class="score-item"><span>${i+1}. ${safeName}</span><span>${s.current_points} pts</span></div>`;
    }).join('');
}

let localTimerInterval = null;
let currentLocalTimeLeft = 0;

function startLocalTimer(timeLeft) {
    if (localTimerInterval) clearInterval(localTimerInterval);
    currentLocalTimeLeft = timeLeft;
    
    updateTimerDisplay(currentLocalTimeLeft);
    
    if (currentLocalTimeLeft > 0) {
        localTimerInterval = setInterval(() => {
            currentLocalTimeLeft--;
            updateTimerDisplay(currentLocalTimeLeft);
            
            if (currentLocalTimeLeft <= 0) {
                clearInterval(localTimerInterval);
                // Trigger an update slightly after timer expires to process server-side auto actions
                setTimeout(updateGameState, 1000);
            }
        }, 1000);
    }
}

function updateTimerDisplay(timeLeft) {
    const display = document.getElementById('fake-timer-display');
    const container = document.getElementById('fake-timer-container');
    if (display && container) {
        if (timeLeft > 0) {
            container.style.display = 'block';
            display.textContent = `${timeLeft} сек`;
            container.classList.toggle('timer-warning', timeLeft <= 10);
        } else {
            container.style.display = 'block';
            display.textContent = 'Истекло';
            container.classList.add('timer-warning');
        }
    }
}

function displayQuestion(question, elementId = 'question-text') {
    const elem = document.getElementById(elementId);
    if (elem && question) {
        elem.textContent = question.question_text || question.question || 'Вопрос загружается...';
    }
}

function selectAnswer(btn, answer) {
    document.querySelectorAll('.answer-btn').forEach(b => b.classList.remove('selected'));
    btn.classList.add('selected');
    selectedAnswer = answer;
}

async function transitionToNextQuestion() {
    if (!IS_HOST || isTransitioning) return; 
    isTransitioning = true;

    try {
        // 1. Начисляем очки (быстро)
        const respAward = await fetch('ajax/award_points.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ lobby_id: LOBBY_ID, csrf_token: CSRF_TOKEN })
        });
        const dataAward = await respAward.json();
        if (dataAward.success && socketClient) {
            socketClient.notifyUpdate(); // Уведомляем всех об обновлении очков
        }

        // 2. Готовим следующий вопрос (медленно - Groq AI)
        const respFinalize = await fetch('ajax/finalize_round.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ lobby_id: LOBBY_ID, csrf_token: CSRF_TOKEN })
        });
        const dataFinalize = await respFinalize.json();

        // 3. Ждем до конца 10 секундного окна результатов
        const elapsed = Date.now() - resultsStartTime;
        const minDisplayTime = 10000;
        const remainingDelay = Math.max(0, minDisplayTime - elapsed);

        setTimeout(async () => {
            // 4. Активируем новый вопрос
            await fetch('ajax/activate_next.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ lobby_id: LOBBY_ID, csrf_token: CSRF_TOKEN })
            });
            
            isTransitioning = false;
            hasVoted = false;
            selectedAnswer = '';
            resultsStartTime = null;
            podiumStartTime = null;
            
            if (socketClient) {
                socketClient.notifyUpdate({ status: dataFinalize.status });
            }
            setTimeout(() => updateGameState(), 500);
        }, remainingDelay);

    } catch (e) {
        console.error('Transition error:', e);
        isTransitioning = false;
        setTimeout(transitionToNextQuestion, 2000);
    }
}

function returnToLobby() {
    clearInterval(pollInterval);
    clearTimeout(automaticTransitionTimer);
    if (!IS_HOST) {
        location.href = `lobby.php?lobby_id=${LOBBY_ID}`;
        return;
    }

    fetch('ajax/reset_lobby.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ lobby_id: LOBBY_ID, csrf_token: CSRF_TOKEN })
    })
    .then(() => {
        location.href = `lobby.php?lobby_id=${LOBBY_ID}`;
    });
}

function startResultsCountdown() {
    if (resultsCountdownInterval) clearInterval(resultsCountdownInterval);
    
    const countText = document.getElementById('results-countdown-text');
    const countSeconds = document.getElementById('results-countdown-seconds');
    if (!countText || !countSeconds) return;
    
    countText.innerHTML = `Автоматический переход через <span id="results-countdown-seconds" style="font-weight: bold; color: #ffeb3b;">10</span> сек...`;
    
    let left = 10;
    resultsCountdownInterval = setInterval(() => {
        left--;
        const secondsSpan = document.getElementById('results-countdown-seconds');
        if (secondsSpan) {
            secondsSpan.textContent = left;
        }
        if (left <= 0) {
            clearInterval(resultsCountdownInterval);
            if (countText) {
                countText.innerHTML = `<span style="font-weight: bold; color: #ffeb3b;">Генерируем вопрос. Пожалуйста, подождите</span>`;
            }
        }
    }, 1000);
}

function submitTopic() {
    const topic = document.getElementById('topic-input').value.trim();
    const btn = document.getElementById('topic-submit-btn');
    const errorDiv = document.getElementById('topic-error');
    if (!topic) return;

    btn.disabled = true;
    btn.classList.add('btn-disabled');
    
    const tInputC = document.getElementById('topic-input-container');
    const tGenC = document.getElementById('topic-generating-container');
    if (tInputC) tInputC.style.display = 'none';
    if (tGenC) tGenC.style.display = 'block';

    isGeneratingQuestion = true;
    if (socketClient) {
        socketClient.sendAction('generating_question');
    }

    fetch('ajax/select_topic.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ lobby_id: LOBBY_ID, topic: topic, csrf_token: CSRF_TOKEN })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            isGeneratingQuestion = false;
            if (socketClient) socketClient.sendAction('topic_selected', { topic: topic });
        } else {
            isGeneratingQuestion = false;
            if (tInputC) tInputC.style.display = 'block';
            if (tGenC) tGenC.style.display = 'none';
            btn.disabled = false;
            btn.classList.remove('btn-disabled');
            if (errorDiv) errorDiv.textContent = data.error || 'Ошибка';
            
            if (socketClient) socketClient.notifyUpdate();
        }
    })
    .catch(() => {
        isGeneratingQuestion = false;
        if (tInputC) tInputC.style.display = 'block';
        if (tGenC) tGenC.style.display = 'none';
        btn.disabled = false;
        btn.classList.remove('btn-disabled');
        if (errorDiv) errorDiv.textContent = 'Ошибка сервера';
        
        if (socketClient) socketClient.notifyUpdate();
    });
}

function submitFake(event) {
    event.preventDefault();
    const fake = document.getElementById('fake-input').value.trim();
    const btn = document.getElementById('fake-submit-btn');
    const errorDiv = document.getElementById('fake-error');
    if (!fake) return;

    btn.disabled = true;
    btn.classList.add('btn-disabled');
    errorDiv.textContent = 'Проверяем...';

    const formData = new FormData();
    formData.append('lobby_id', LOBBY_ID);
    formData.append('question_id', currentQuestionId);
    formData.append('fake_answer', fake);
    formData.append('csrf_token', CSRF_TOKEN);

    fetch('ajax/send_fake.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            errorDiv.textContent = '';
            document.getElementById('fake-input').disabled = true;
            btn.textContent = '✓ Принято';
            if (socketClient) socketClient.sendAction('fake_submitted');
        } else {
            errorDiv.textContent = data.error || 'Ошибка';
            btn.disabled = false;
            btn.classList.remove('btn-disabled');
        }
    })
    .catch(() => {
        errorDiv.textContent = 'Ошибка сервера';
        btn.disabled = false;
        btn.classList.remove('btn-disabled');
    });
}

function submitVote() {
    if (!selectedAnswer) return;
    const btn = document.getElementById('vote-submit-btn');
    const errorDiv = document.getElementById('vote-error');

    btn.disabled = true;
    btn.classList.add('btn-disabled');

    fetch('ajax/submit_vote.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ lobby_id: LOBBY_ID, question_id: currentQuestionId, answer: selectedAnswer, csrf_token: CSRF_TOKEN })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            hasVoted = true;
            btn.textContent = '✓ Голос учтен';
            if (socketClient) socketClient.sendAction('vote_submitted');
        } else {
            errorDiv.textContent = data.error || 'Ошибка';
            btn.disabled = false;
            btn.classList.remove('btn-disabled');
        }
    })
    .catch(() => {
        errorDiv.textContent = 'Ошибка сервера';
        btn.disabled = false;
        btn.classList.remove('btn-disabled');
    });
}

function displayResults(data) {
    document.getElementById('correct-answer-display').textContent = data.currentQuestion.correct_answer;
    const resultsList = document.getElementById('results-list');
    if (data.answers) {
        resultsList.innerHTML = Object.values(data.answers).map(a => {
            const safeText = GameWebSocketClient.escapeHtml(a.text);
            const safeAuthor = a.author ? GameWebSocketClient.escapeHtml(a.author) : 'Официальный ответ';
            return `
                <div class="result-item">
                    <div class="${a.is_correct ? 'result-correct' : ''}">${safeText}</div>
                    <div class="player-name">${safeAuthor}</div>
                    <div class="votes-badge">${a.votes} голос${a.votes > 1 ? 'ов' : ''}</div>
                </div>
            `;
        }).join('');
    }
}

function displayPodium(scores) {
    const podium = document.getElementById('podium-body');
    const top3 = scores.slice(0, 3);
    const medals = [
        '<img src="assets/img/medal_1.jpeg" class="medal-img" alt="1st">',
        '<img src="assets/img/medal_2.jpeg" class="medal-img" alt="2nd">',
        '<img src="assets/img/medal_3.jpeg" class="medal-img" alt="3rd">'
    ];
    podium.innerHTML = top3.map((player, idx) => {
        const safeName = GameWebSocketClient.escapeHtml(player.username);
        return `
            <div class="podium-place">
                ${medals[idx] || ''}
                <div class="podium-name">${idx + 1}. ${safeName}</div>
                <div class="podium-score">${player.current_points} очков</div>
            </div>
        `;
    }).join('');
}

function shuffleArray(array) {
    const arr = [...array];
    for (let i = arr.length - 1; i > 0; i--) {
        const j = Math.floor(Math.random() * (i + 1));
        [arr[i], arr[j]] = [arr[j], arr[i]];
    }
    return arr;
}

document.addEventListener('DOMContentLoaded', startConnection);
window.addEventListener('beforeunload', () => { 
    if (pollInterval) clearInterval(pollInterval);
    if (socketClient) socketClient.disconnect();
});
</script>

<?php include 'views/footer.php'; ?>
