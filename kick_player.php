<?php
/**
 * Скрипт для исключения игрока из комнаты ожидания создателем лобби.
 */

session_start();
require_once 'core/db.php';

// Проверка авторизации: если пользователь не вошел в систему, отправляем его на страницу авторизации
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Защита от CSRF-атак: проверяем, что запрос безопасности отправлен именно с нашего сайта
$csrfToken = $_POST['csrf_token'] ?? '';
if (!verifyCsrfToken($csrfToken)) {
    http_response_code(403);
    exit('Неверный CSRF-токен');
}

$username = trim($_POST['user_id'] ?? '');
$lobbyId = (int) $_POST['lobby_id'];

// Получаем информацию о лобби из базы данных
$lobby = getLobbyById($lobbyId);
if (!$lobby) {
    header('Location: hub.php');
    exit;
}

// Запрет на кик во время игры: выгонять игроков можно только во время ожидания в лобби
if ($lobby['is_active']) {
    http_response_code(403);
    exit('Нельзя выгонять игроков во время активной игры');
}

// Проверяем, является ли текущий пользователь создателем (хостом) этой комнаты
if ($lobby['host_id'] != $_SESSION['user_id']) {
    http_response_code(403);
    exit;
}

// Ищем ID игрока в базе данных по его никнейму
$pdo = getPDO();
$stmt = $pdo->prepare('SELECT id FROM users WHERE username = :un LIMIT 1');
$stmt->execute(['un' => $username]);
$targetUser = $stmt->fetch();

if (!$targetUser) {
    header('Location: lobby.php?lobby_id=' . $lobbyId);
    exit;
}

$targetUserId = $targetUser['id'];

// Запрет на самоисключение: создатель не может выгнать сам себя
if ($targetUserId == $_SESSION['user_id']) {
    http_response_code(400);
    exit;
}

// Исключаем игрока из базы данных
leaveLobby($lobbyId, $targetUserId);

// Перенаправляем создателя лобби обратно на страницу лобби
header('Location: lobby.php?lobby_id=' . $lobbyId);
exit;

