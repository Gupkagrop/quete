<?php
/**
 * AJAX-скрипт для загрузки новых сообщений чата в игровой комнате.
 */

session_start();
header('Content-Type: application/json');

$lobbyId = (int) ($_GET['lobby_id'] ?? 0);

if (!$lobbyId) {
    http_response_code(400);
    echo json_encode([]);
    exit;
}

require_once '../core/db.php';

// Проверяем, авторизован ли пользователь в системе
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode([]);
    exit;
}

// Проверяем, существует ли указанная игровая комната
$lobby = getLobbyById($lobbyId);
if (!$lobby) {
    http_response_code(404);
    echo json_encode([]);
    exit;
}

// Проверяем, состоит ли запрашивающий пользователь в составе этой комнаты
$players = getLobbyPlayers($lobbyId);
$userInLobby = false;
foreach ($players as $p) {
    if ($p['user_id'] == $_SESSION['user_id']) {
        $userInLobby = true;
        break;
    }
}

// Если пользователя нет в комнате, доступ к её чату запрещен
if (!$userInLobby) {
    http_response_code(403);
    echo json_encode([]);
    exit;
}

// Запрашиваем из базы данных список последних сообщений чата
$messages = getChatMessages($lobbyId);

// Возвращаем сообщения в формате JSON
echo json_encode($messages);

