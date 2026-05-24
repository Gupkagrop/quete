<?php
session_start();
require_once 'core/db.php';
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
$user = getUserById($_SESSION['user_id']);

$lobbyId = (int) ($_GET['lobby_id'] ?? 0);
$lobby = getLobbyById($lobbyId);
if (!$lobby) {
    header('Location: hub.php');
    exit;
}

// Если игрок уже в другом лобби, отправим в него
$currentLobby = getLobbyByUserId($_SESSION['user_id']);
if ($currentLobby && $currentLobby['id'] !== $lobbyId) {
    if ($currentLobby['is_active']) {
        header('Location: game.php?lobby_id=' . $currentLobby['id']);
        exit;
    }
    header('Location: lobby.php?lobby_id=' . $currentLobby['id']);
    exit;
}

// Проверяем, состоит ли уже пользователь в лобби
$players = getLobbyPlayers($lobbyId);
$userInLobby = false;
foreach ($players as $p) {
    if ((int)$p['user_id'] === (int)$_SESSION['user_id']) {
        $userInLobby = true;
        break;
    }
}

// Если лобби закрытое и пользователь заходит впервые
if (!$userInLobby && $lobby['password'] !== null) {
    $postedPassword = $_POST['lobby_password'] ?? '';
    if ($postedPassword !== $lobby['password']) {
        $_SESSION['flash_error'] = 'Неверный пароль для входа в приватное лобби!';
        header('Location: hub.php');
        exit;
    }
}

// Присоединиться, если не в лобби
if (!$userInLobby) {
    $joined = joinLobby($lobbyId, $_SESSION['user_id']);
    if (!$joined) {
        $_SESSION['flash_error'] = 'Не удалось войти: лобби переполнено!';
        header('Location: hub.php');
        exit;
    }
}

if ($lobby['is_active']) {
    header('Location: game.php?lobby_id=' . $lobbyId);
    exit;
}

$players = getLobbyPlayers($lobbyId);
$isHost = $lobby['host_id'] == $_SESSION['user_id'];

// Найти текущего игрока
$currentPlayer = null;
foreach ($players as $p) {
    if ($p['user_id'] == $_SESSION['user_id']) {
        $currentPlayer = $p;
        break;
    }
}

// Обработка готовности
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ready'])) {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        http_response_code(403);
        exit('Invalid CSRF token');
    }
    
    $ready = (bool) $_POST['ready'];
    if (!$isHost) { // Хост всегда готов
        setPlayerReady($lobbyId, $_SESSION['user_id'], $ready);
    }
    header('Location: lobby.php?lobby_id=' . $lobbyId);
    exit;
}

// Обработка запуска игры
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['start'])) {
    if (!$isHost) {
        header('Location: lobby.php?lobby_id=' . $lobbyId);
        exit;
    }
    
    // Проверить условия
    if (count($players) >= 2 && areAllPlayersReady($lobbyId)) {
        startGame($lobbyId);
        header('Location: game.php?lobby_id=' . $lobbyId);
        exit;
    }
    
    header('Location: lobby.php?lobby_id=' . $lobbyId);
    exit;
}

// Обработка изменения настроек лобби хостом
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update'])) {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        http_response_code(403);
        exit('Invalid CSRF token');
    }
    if (!$isHost) {
        http_response_code(403);
        exit('Not authorized');
    }

    $name = trim($_POST['lobby_name'] ?? '');
    $maxPlayers = (int) ($_POST['players'] ?? 8);
    $timer = (int) ($_POST['timer'] ?? 60);
    $password = trim($_POST['password'] ?? '');
    $mode = $_POST['mode'] ?? 'open';

    if (empty($name)) {
        $name = $lobby['lobby_name'];
    }
    if ($maxPlayers < 2 || $maxPlayers > 8) {
        $maxPlayers = 4;
    }
    if ($timer < 20 || $timer > 120) {
        $timer = 60;
    }
    if ($mode !== 'private' || empty($password)) {
        $password = null;
    }

    // Серверная валидация длины пароля
    if ($mode === 'private' && $password !== null && strlen($password) < 4) {
        http_response_code(400);
        exit('Пароль должен быть не менее 4 символов');
    }

    updateLobby($lobbyId, $name, $password, $maxPlayers, $timer);
    
    // Если запрос пришел через fetch, вернем JSON
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
        echo json_encode(['success' => true]);
        exit;
    }
    header('Location: lobby.php?lobby_id=' . $lobbyId);
    exit;
}

// Генерация одноразового билета для WebSocket
$wsTicket = generateWebSocketTicket($_SESSION['user_id']);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Лобби ожидания игры Куэте. Собирайтесь с друзьями, настраивайте правила и готовьтесь к началу викторины!">
    <link rel="canonical" href="https://quete.ru/lobby.php">
    <title>Лобби</title>
    <link rel="stylesheet" href="assets/css/game.css"<?php echo get_sri_attrs('assets/css/game.css'); ?>>
    <script src="assets/js/websocket-client.js?v=<?php echo time(); ?>"<?php echo get_sri_attrs('assets/js/websocket-client.js'); ?>></script>
</head>
<body>

<div class="page-wrapper">
    <div class="chat-game-layout">
        
        <!-- ЧАТ (размещен слева от игрового окна на ПК) -->
        <?php include 'views/chat.php'; ?>

        <!-- ГЛАВНОЕ ОКНО -->
        <div class="game-window">
            
            <div class="window-header">

                <div class="window-title"><?php echo htmlspecialchars($lobby['lobby_name']); ?></div>
                <a href="javascript:void(0)" class="user-icon" onclick="toggleStatsPopup()">
                    <svg viewBox="0 0 24 24">
                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                        <circle cx="12" cy="7" r="4"></circle>
                    </svg>
                </a>
                <?php $showLeaveButton = true; include 'views/user_stats.php'; ?>
            </div>

            <div class="panels-grid">
                
                <!-- Левая панель: Список игроков -->
                <div class="panel">
                    <div class="panel-title">Список игроков:</div>
                    
                    <div class="white-box" style="text-align: center;">
                        <div class="list-header-text"><?php echo count($players); ?> из <?php echo $lobby['max_players']; ?></div>
                        <div style="text-align: left;" id="players-list-container">
                            <?php foreach ($players as $index => $player): ?>
                                <?php $avatarId = $player['avatar_id'] ?? 1; ?>
                                <div class="list-item">
                                    <img src="assets/img/avatar<?php echo $avatarId; ?>.jpeg" class="retro-avatar" alt="A">
                                    <span class="name"><?php echo htmlspecialchars($player['username']); ?></span>
                                    <?php if ($player['is_ready']): ?>
                                        <span class="ready-status">✓ Готов</span>
                                    <?php endif; ?>
                                    <?php if ($isHost && $player['username'] != $user['username']): ?>
                                        <form method="POST" action="kick_player.php">
                                            <input type="hidden" name="csrf_token" value="<?php echo getCsrfToken(); ?>">
                                            <input type="hidden" name="lobby_id" value="<?php echo $lobbyId; ?>">
                                            <input type="hidden" name="user_id" value="<?php echo htmlspecialchars($player['username']); ?>">
                                            <button type="submit" style="background: #ff4d4d; color: white; border: none; padding: 3px 8px; border-radius: 4px; cursor: pointer; font-size: 12px;">✕</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="btn-row">
                        <?php if ($isHost): ?>
                            <button class="btn-game btn-full" disabled style="opacity: 0.6;">Готов (хост)</button>
                        <?php else: ?>
                            <form id="ready-form" onsubmit="toggleReady(event)" style="width: 100%;">
                                <button class="btn-game btn-full" type="submit" id="ready-btn"><?php echo $currentPlayer && $currentPlayer['is_ready'] ? 'Не готов' : 'Готов'; ?></button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Правая панель: Настройки лобби -->
                <div class="panel">
                    <div class="panel-title">Лобби</div>
                    

                    
                    <?php if ($isHost): ?>
                    <form id="lobby-settings-form" onsubmit="updateLobbySettings(event)">
                        <div class="form-group">
                            <span class="form-label">Название лобби</span>
                            <input type="text" name="lobby_name" id="settings-name" class="form-input" value="<?php echo htmlspecialchars($lobby['lobby_name']); ?>" required>
                        </div>

                        <div class="form-group">
                            <span class="form-label">Количество игроков от 2 до 8</span>
                            <input type="number" name="players" id="settings-players" class="form-input" min="2" max="8" value="<?php echo $lobby['max_players']; ?>" required>
                        </div>

                        <div class="form-group">
                            <span class="form-label">Время на ответ от 20 до 120 секунд</span>
                            <input type="number" name="timer" id="settings-timer" class="form-input" min="20" max="120" value="<?php echo $lobby['fake_answer_time']; ?>" required>
                        </div>

                        <div class="form-group" id="settings-password-group" style="<?php echo $lobby['password'] ? '' : 'display: none;'; ?>">
                            <span class="form-label">Пароль (если закрытое)</span>
                            <input type="text" name="password" id="settings-password" class="form-input" value="<?php echo htmlspecialchars($lobby['password'] ?? ''); ?>" placeholder="минимум 4 символа">
                        </div>

                        <div class="radio-container">
                            <label class="radio-label">
                                <input type="radio" name="mode" value="open" id="mode-open" <?php if (!$lobby['password']) echo 'checked'; ?>>
                                <span class="radio-custom"></span>
                                <span>Открытое</span>
                            </label>
                            <label class="radio-label">
                                <input type="radio" name="mode" value="private" id="mode-private" <?php if ($lobby['password']) echo 'checked'; ?>>
                                <span class="radio-custom"></span>
                                <span>Закрытое</span>
                            </label>
                        </div>

                        <div class="btn-row">
                            <button class="btn-game" type="submit">Изменить</button>
                            <button class="btn-game" type="button" id="start-btn" onclick="startGameHandler()" <?php echo (count($players) >= 2 && areAllPlayersReady($lobbyId)) ? '' : 'disabled style="opacity: 0.6;"'; ?>>Начать</button>
                        </div>
                    </form>
                    <?php else: ?>
                        <div style="text-align: center;">
                            <img src="assets/img/hourglass.png" class="retro-waiting-icon" alt="Waiting">
                            <p style="margin-top: 20px; font-weight: 700;">Ожидание начала игры хостом...</p>
                        </div>
                    <?php endif; ?>
                </div>

            </div>

        </div>
    </div>
</div>

<script nonce="<?php echo CSP_NONCE; ?>">
const LOBBY_ID = <?php echo $lobbyId; ?>;
const USER_ID = <?php echo $_SESSION['user_id']; ?>;
const IS_HOST = <?php echo $isHost ? 'true' : 'false'; ?>;
const WS_TICKET = "<?php echo $wsTicket; ?>";
let socketClient = null;
let pollInterval = null;
let isReady = <?php echo $currentPlayer && $currentPlayer['is_ready'] ? 'true' : 'false'; ?>;
const CSRF_TOKEN = '<?php echo getCsrfToken(); ?>';
const WS_PORT = <?php echo WS_CLIENT_PORT; ?>;
const WS_HOST = "<?php echo WS_CLIENT_HOST; ?>";

function startConnection() {
    socketClient = new GameWebSocketClient(LOBBY_ID, USER_ID, {
        host: WS_HOST,
        port: WS_PORT,
        ticket: WS_TICKET,
        onMessage: (msg) => {
            console.log('Lobby WS Message:', msg);
            if (msg.type === 'player_action' && msg.action_type === 'lobby_deleted') {
                if (!IS_HOST) {
                    alert('Создатель покинул лобби. Вы будете перенаправлены в хаб.');
                    window.location.href = 'hub.php';
                }
                return;
            }
            if (msg.type === 'chat_message') {
                appendChatMessage(msg);
            } else {
                updateLobbyState();
            }
        },
        onConnect: () => {
            console.log('Lobby connected to WebSocket');
            updateLobbyState();
        }
    });
    socketClient.connect();
    
    // Медленный polling как fallback (срабатывает только при отключенном сокет-соединении)
    pollInterval = setInterval(() => {
        if (!socketClient || !socketClient.isConnected()) {
            updateLobbyState();
        }
    }, 10000);
}

function updateLobbyState() {
    fetch(`ajax/get_lobby_update.php?lobby_id=${LOBBY_ID}&csrf_token=${CSRF_TOKEN}`)
        .then(response => {
            if (!response.ok) {
                return response.json().then(data => { throw data; });
            }
            return response.json();
        })
        .then(data => {
            if (data.error) throw data;

            // Обновить список игроков
            const playersListContainer = document.getElementById('players-list-container');
            const playersCountHeader = document.querySelector('.list-header-text');
            
            if (playersCountHeader) {
                playersCountHeader.textContent = data.players.length + ' из ' + data.lobby.max_players;
            }

            if (playersListContainer) {
                let html = '';
                data.players.forEach((player, index) => {
                    const safeUsername = GameWebSocketClient.escapeHtml(player.username);
                    const avatarId = player.avatar_id || 1;
                    html += '<div class="list-item">';
                    html += `<img src="assets/img/avatar${avatarId}.jpeg" class="retro-avatar" alt="A">`;
                    html += '<span class="name">' + safeUsername + '</span>';
                    if (player.is_ready) {
                        html += '<span class="ready-status">✓ Готов</span>';
                    }
                    
                    if (IS_HOST && player.username !== '<?php echo addslashes($user['username']); ?>') {
                        html += '<form method="POST" action="kick_player.php">';
                        html += '<input type="hidden" name="csrf_token" value="' + CSRF_TOKEN + '">';
                        html += '<input type="hidden" name="lobby_id" value="' + LOBBY_ID + '">';
                        html += '<input type="hidden" name="user_id" value="' + safeUsername + '">';
                        html += '<button type="submit" style="background: #ff4d4d; color: white; border: none; padding: 3px 8px; border-radius: 4px; cursor: pointer; font-size: 12px;">✕</button>';
                        html += '</form>';
                    }
                    html += '</div>';
                    
                    if (player.user_id == USER_ID) {
                        isReady = !!player.is_ready;
                        const readyBtn = document.getElementById('ready-btn');
                        if (readyBtn) readyBtn.textContent = isReady ? 'Не готов' : 'Готов';
                    }
                });
                playersListContainer.innerHTML = html;
            }

            // Если игра началась, перенаправить
            if (data.lobby.is_active) {
                window.location.href = 'game.php?lobby_id=' + LOBBY_ID;
            }
            
            // Обновить состояние кнопки "Начать"
            const startBtn = document.getElementById('start-btn');
            if (startBtn && IS_HOST) {
                const allReady = data.players.every(p => p.is_ready);
                const hasEnoughPlayers = data.players.length >= 2;
                if (allReady && hasEnoughPlayers) {
                    startBtn.disabled = false;
                    startBtn.style.opacity = '1';
                } else {
                    startBtn.disabled = true;
                    startBtn.style.opacity = '0.6';
                }
            }
        })
        .catch(error => {
            console.error('Polling error:', error);
            if (error.error === 'Lobby not found' || error.error === 'Forbidden') {
                if (pollInterval) clearInterval(pollInterval);
                alert('Лобби закрыто или вы были исключены. Вы будете перенаправлены в хаб.');
                window.location.href = 'hub.php';
            }
        });
}

function toggleReady(event) {
    event.preventDefault();
    const newReadyStatus = isReady ? 0 : 1;
    
    const formData = new FormData();
    formData.append('ready', newReadyStatus);
    formData.append('csrf_token', CSRF_TOKEN);
    
    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(() => {
        if (socketClient) socketClient.sendAction('player_ready', { ready: !!newReadyStatus });
        updateLobbyState();
    });
}

function updateLobbySettings(event) {
    event.preventDefault();
    const form = event.target;
    const formData = new FormData(form);
    
    // Клиентская валидация пароля приватного лобби
    const mode = formData.get('mode');
    const password = formData.get('password') ? formData.get('password').trim() : '';
    
    if (mode === 'private' && password.length < 4) {
        alert('Пароль для приватного лобби должен состоять как минимум из 4 символов!');
        return;
    }
    
    formData.append('update', '1');
    formData.append('csrf_token', CSRF_TOKEN);
    
    fetch(window.location.href, {
        method: 'POST',
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: formData
    })
    .then(response => {
        if (!response.ok) {
            return response.text().then(errText => { throw new Error(errText); });
        }
        return response.json();
    })
    .then(() => {
        if (socketClient) socketClient.sendAction('settings_updated');
        updateLobbyState();
        alert('Настройки успешно обновлены!');
    })
    .catch(error => {
        alert('Ошибка при обновлении настроек: ' + error.message);
    });
}

function startGameHandler() {
    const startBtn = document.getElementById('start-btn');
    if (startBtn.disabled) return;
    
    const formData = new FormData();
    formData.append('lobby_id', LOBBY_ID);
    formData.append('csrf_token', CSRF_TOKEN);
    
    fetch('ajax/start_game.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.error) {
            alert('Ошибка: ' + data.error);
        } else if (data.success) {
            if (socketClient) socketClient.sendAction('game_started');
            window.location.href = data.redirect;
        }
    })
    .catch(error => console.error('Start game error:', error));
}

function toggleSettingsPasswordGroup() {
    const passwordGroup = document.getElementById('settings-password-group');
    const isPrivate = document.getElementById('mode-private').checked;
    if (passwordGroup) {
        passwordGroup.style.display = isPrivate ? 'block' : 'none';
        const passwordInput = document.getElementById('settings-password');
        if (passwordInput) {
            if (isPrivate) {
                passwordInput.required = true;
            } else {
                passwordInput.required = false;
                passwordInput.value = '';
            }
        }
    }
}

document.addEventListener('DOMContentLoaded', () => {
    startConnection();
    
    // Привязать события изменения режима для пароля
    const modeOpen = document.getElementById('mode-open');
    const modePrivate = document.getElementById('mode-private');
    if (modeOpen && modePrivate) {
        modeOpen.addEventListener('change', toggleSettingsPasswordGroup);
        modePrivate.addEventListener('change', toggleSettingsPasswordGroup);
    }
});

window.addEventListener('beforeunload', () => {
    if (pollInterval) clearInterval(pollInterval);
    if (socketClient) socketClient.disconnect();
});
</script>

<?php include 'views/footer.php'; ?>