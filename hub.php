<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
require_once 'core/db.php';
$user = getUserById($_SESSION['user_id']);

$currentLobby = getLobbyByUserId($_SESSION['user_id']);
if ($currentLobby) {
    if ($currentLobby['is_active']) {
        header('Location: game.php?lobby_id=' . $currentLobby['id']);
        exit;
    }
    header('Location: lobby.php?lobby_id=' . $currentLobby['id']);
    exit;
}

// Обработка создания лобби
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['lobby_name'])) {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        die('Invalid CSRF token');
    }
    $name = trim($_POST['lobby_name']);
    $players = (int) $_POST['players'];
    $timer = (int) $_POST['timer'];
    $password = trim($_POST['password']);
    $mode = $_POST['mode'];

    if ($players < 2 || $players > 8) $players = 4;
    if ($timer < 20 || $timer > 120) $timer = 60;
    
    if ($mode === 'private') {
        if (empty($password) || mb_strlen($password) < 4) {
            $_SESSION['flash_error'] = 'Пароль приватного лобби должен состоять как минимум из 4 символов!';
            header('Location: hub.php');
            exit;
        }
    } else {
        $password = null;
    }

    $lobbyId = createLobby($_SESSION['user_id'], $name, $password, $players, $timer);
    joinLobby($lobbyId, $_SESSION['user_id']);
    header('Location: lobby.php?lobby_id=' . $lobbyId);
    exit;
}

$lobbies = getLobbies();
$flashError = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_error']);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Игровой хаб квиза Куэте. Выберите лобби для мультиплеера или запустите соло-режим игры с ИИ-вопросами.">
    <link rel="canonical" href="https://quete.ru/hub.php">
    <title>HUB</title>
    <link rel="stylesheet" href="assets/css/game.css">
    <script src="assets/js/websocket-client.js?v=<?php echo time(); ?>"></script>
</head>
<body>

<div class="page-wrapper">
    <div class="game-window">
        
        <div class="window-header">
            <div class="window-title">HUB</div>
            <a href="javascript:void(0)" class="user-icon" onclick="toggleStatsPopup()">
                <svg viewBox="0 0 24 24">
                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                    <circle cx="12" cy="7" r="4"></circle>
                </svg>
            </a>
            <?php $showLeaveButton = false; include 'views/user_stats.php'; ?>
        </div>

        <?php if ($flashError): ?>
            <div class="flash-error-banner" style="background: rgba(244, 67, 54, 0.15); border: 2px dashed #f44336; border-radius: 8px; padding: 12px; margin: 0 0 20px 0; color: #fff; text-align: center; box-sizing: border-box; width: 100%;">
                <span style="font-weight: bold; color: #ffeb3b;">⚠️ Ошибка:</span> <?php echo htmlspecialchars($flashError); ?>
            </div>
        <?php endif; ?>

        <div class="panels-grid">
            
            <!-- Левая панель: Список лобби -->
            <div class="panel">
                <div class="panel-title">Список лобби</div>
                
                <div class="white-box lobbies-list">
                    <?php foreach ($lobbies as $lobby): ?>
                        <?php 
                        $isFull = (int)$lobby['current_players'] >= (int)$lobby['max_players'];
                        $isInGame = (int)$lobby['is_active'] === 1;
                        $isPrivate = (bool)$lobby['is_private'];
                        $isDisabled = $isFull || $isInGame;

                        $badge = '';
                        if ($isInGame) {
                            $badge = ' <img src="assets/img/icon_sword.png" class="retro-icon" title="В игре">';
                        } elseif ($isFull) {
                            $badge = ' (Полная)';
                        } elseif ($isPrivate) {
                            $badge = ' <img src="assets/img/icon_lock.png" class="retro-icon" title="Закрытое">';
                        }
                        ?>
                        <div class="list-item" style="<?php echo $isDisabled ? 'opacity: 0.5;' : ''; ?>">
                            <label style="<?php echo $isDisabled ? 'cursor: not-allowed;' : ''; ?>">
                                <input type="radio" name="selected_lobby" value="<?php echo $lobby['id']; ?>" 
                                       data-private="<?php echo $isPrivate ? '1' : '0'; ?>"
                                       <?php echo $isDisabled ? 'disabled' : ''; ?>
                                       style="margin-right: 10px;">
                                <span class="name"><?php echo htmlspecialchars($lobby['lobby_name']) . $badge; ?></span>
                                <span class="value"><?php echo $lobby['current_players']; ?>/<?php echo $lobby['max_players']; ?></span>
                            </label>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="btn-row">
                    <button class="btn-game" onclick="updateLobbies()">Обновить</button>
                    <button class="btn-game" onclick="joinLobby()">Войти</button>
                </div>
            </div>

            <!-- Правая панель: Создание лобби -->
            <div class="panel">
                <div class="panel-title">Создание лобби</div>
                
                <form method="POST" action="" onsubmit="validateLobbyCreation(event)">
                    <input type="hidden" name="csrf_token" value="<?php echo getCsrfToken(); ?>">
                    <div class="form-group">
                        <span class="form-label">Название лобби</span>
                        <input type="text" name="lobby_name" class="form-input" placeholder="название" required>
                    </div>

                    <div class="form-group">
                        <span class="form-label">Количество игроков от 2 до 8</span>
                        <input type="number" name="players" class="form-input" placeholder="2-8" min="2" max="8" value="4" required>
                    </div>

                    <div class="form-group">
                        <span class="form-label">Время на ответ от 20 до 120 секунд</span>
                        <input type="number" name="timer" class="form-input" placeholder="20-120" min="20" max="120" value="60" required>
                    </div>

                    <div class="form-group" id="create-password-group" style="display: none;">
                        <span class="form-label">Пароль (если закрытое)</span>
                        <input type="password" name="password" id="create-password" class="form-input" placeholder="минимум 4 символа">
                    </div>

                    <div class="radio-container">
                        <label class="radio-label">
                            <input type="radio" name="mode" value="open" checked onchange="toggleCreatePasswordGroup()">
                            <span class="radio-custom"></span>
                            <span>Открытое</span>
                        </label>
                        <label class="radio-label">
                            <input type="radio" name="mode" value="private" onchange="toggleCreatePasswordGroup()">
                            <span class="radio-custom"></span>
                            <span>Закрытое</span>
                        </label>
                    </div>

                    <div class="btn-row">
                        <a href="solo.php" class="btn-game" style="text-decoration:none; text-align:center; line-height:30px; margin-right:10px;">СОЛО</a>
                        <button class="btn-game" type="submit">Создать</button>
                    </div>
                </form>
            </div>

        </div>
    </div>
</div>

<!-- Модальное окно ввода пароля приватного лобби -->
<div class="auth-popup-overlay" id="password-overlay">
    <div class="auth-popup-card">
        <button class="auth-popup-close" onclick="closePasswordOverlay()">×</button>
        <h2 class="auth-popup-title">Введите пароль</h2>
        <form id="password-join-form" method="POST" action="lobby.php">
            <input type="hidden" name="csrf_token" value="<?php echo getCsrfToken(); ?>">
            <div class="form-group">
                <span class="form-label" style="color: rgba(255,255,255,0.7); margin-bottom: 8px; display: inline-block;">Пароль от лобби</span>
                <div style="position: relative;">
                    <input type="password" name="lobby_password" id="join-password-input" class="form-input" style="background: rgba(255,255,255,0.1); border: 2px solid var(--accent-orange); color: #FFF; padding-right: 40px;" placeholder="Пароль" required>
                    <button type="button" onclick="togglePasswordVisibility('join-password-input')" style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); background: none; border: none; color: #FFF; cursor: pointer; font-size: 18px;">👁️</button>
                </div>
            </div>
            <button type="submit" class="btn-game btn-full" style="margin-top: 25px;">Войти</button>
        </form>
    </div>
</div>

<script nonce="<?php echo CSP_NONCE; ?>">
const CSRF_TOKEN = '<?php echo getCsrfToken(); ?>';

function joinLobby() {
    const selected = document.querySelector('input[name="selected_lobby"]:checked');
    if (!selected) {
        alert('Выберите лобби');
        return;
    }
    const lobbyId = selected.value;
    const isPrivate = selected.getAttribute('data-private') === '1';
    
    if (isPrivate) {
        openPasswordOverlay(lobbyId);
    } else {
        window.location.href = 'lobby.php?lobby_id=' + lobbyId;
    }
}

function openPasswordOverlay(lobbyId) {
    const overlay = document.getElementById('password-overlay');
    const form = document.getElementById('password-join-form');
    if (overlay && form) {
        form.action = 'lobby.php?lobby_id=' + lobbyId;
        overlay.classList.add('active');
        document.getElementById('join-password-input').value = '';
        setTimeout(() => document.getElementById('join-password-input').focus(), 100);
    }
}

function closePasswordOverlay() {
    const overlay = document.getElementById('password-overlay');
    if (overlay) {
        overlay.classList.remove('active');
    }
}

function togglePasswordVisibility(inputId) {
    const input = document.getElementById(inputId);
    if (input) {
        input.type = input.type === 'password' ? 'text' : 'password';
    }
}

function toggleCreatePasswordGroup() {
    const isPrivate = document.querySelector('input[name="mode"]:checked').value === 'private';
    const group = document.getElementById('create-password-group');
    const input = document.getElementById('create-password');
    if (group && input) {
        group.style.display = isPrivate ? 'block' : 'none';
        if (isPrivate) {
            input.required = true;
            input.focus();
        } else {
            input.required = false;
            input.value = '';
        }
    }
}

function validateLobbyCreation(event) {
    const isPrivate = document.querySelector('input[name="mode"]:checked').value === 'private';
    const password = document.getElementById('create-password').value.trim();
    if (isPrivate && password.length < 4) {
        event.preventDefault();
        alert('Пароль для приватного лобби должен состоять как минимум из 4 символов!');
    }
}

document.addEventListener('click', function(event) {
    const overlay = document.getElementById('password-overlay');
    if (overlay && overlay.classList.contains('active') && event.target === overlay) {
        closePasswordOverlay();
    }
});

function updateLobbies() {
    fetch(`ajax/get_lobbies_update.php?csrf_token=${CSRF_TOKEN}`)
        .then(response => response.json())
        .then(data => {
            if (data.error) return;

            const lobbiesContainer = document.querySelector('.lobbies-list');
            if (lobbiesContainer) {
                // Сохраняем текущий выбор
                const selected = document.querySelector('input[name="selected_lobby"]:checked');
                const selectedId = selected ? selected.value : null;

                let html = '';
                data.lobbies.forEach(lobby => {
                    const isFull = lobby.players_count >= lobby.max_players;
                    const isInGame = lobby.is_active == 1;
                    const isPrivate = lobby.is_private == 1;
                    const isDisabled = isFull || isInGame;
                    
                    let badge = '';
                    if (isInGame) {
                        badge = ' <img src="assets/img/icon_sword.png" class="retro-icon" title="В игре">';
                    } else if (isFull) {
                        badge = ' (Полная)';
                    } else if (isPrivate) {
                        badge = ' <img src="assets/img/icon_lock.png" class="retro-icon" title="Закрытое">';
                    }
                    
                    const safeName = GameWebSocketClient.escapeHtml(lobby.lobby_name);
                    const isChecked = selectedId && selectedId == lobby.id ? 'checked' : '';
                    
                    html += `<div class="list-item" style="${isDisabled ? 'opacity: 0.5;' : ''}">`;
                    html += `  <label style="${isDisabled ? 'cursor: not-allowed;' : ''}">`;
                    html += `    <input type="radio" name="selected_lobby" value="${lobby.id}" `;
                    html += `           data-private="${isPrivate ? '1' : '0'}"`;
                    html += `           ${isDisabled ? 'disabled' : ''} ${isChecked} style="margin-right: 10px;">`;
                    html += `    <span class="name">${safeName}${badge}</span>`;
                    html += `    <span class="value">${lobby.players_count}/${lobby.max_players}</span>`;
                    html += `  </label>`;
                    html += `</div>`;
                });
                lobbiesContainer.innerHTML = html;
            }
        })
        .catch(error => console.error('Polling error:', error));
}

// Список обновляется вручную по кнопке "Обновить"
</script>

<?php include 'views/footer.php'; ?>