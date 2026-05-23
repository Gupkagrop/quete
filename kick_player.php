<?php
session_start();
require_once 'core/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Проверка CSRF-токена хоста
$csrfToken = $_POST['csrf_token'] ?? '';
if (!verifyCsrfToken($csrfToken)) {
    http_response_code(403);
    exit('Неверный CSRF-токен');
}

$username = trim($_POST['user_id'] ?? '');
$lobbyId = (int) $_POST['lobby_id'];

// Получить информацию о лобби
$lobby = getLobbyById($lobbyId);
if (!$lobby) {
    header('Location: hub.php');
    exit;
}

if ($lobby['is_active']) {
    http_response_code(403);
    exit('Нельзя выгонять игроков во время активной игры');
}

// Проверить, что пользователь - создатель лобби
if ($lobby['host_id'] != $_SESSION['user_id']) {
    http_response_code(403);
    exit;
}

// Найти user_id по username
$pdo = getPDO();
$stmt = $pdo->prepare('SELECT id FROM users WHERE username = :un LIMIT 1');
$stmt->execute(['un' => $username]);
$targetUser = $stmt->fetch();

if (!$targetUser) {
    header('Location: lobby.php?lobby_id=' . $lobbyId);
    exit;
}

$targetUserId = $targetUser['id'];

// Проверить, что выгоняемый пользователь - не создатель
if ($targetUserId == $_SESSION['user_id']) {
    http_response_code(400);
    exit;
}

// Выгнать игрока
leaveLobby($lobbyId, $targetUserId);

// Перенаправить обратно в лобби
header('Location: lobby.php?lobby_id=' . $lobbyId);
exit;
