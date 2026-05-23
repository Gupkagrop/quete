<?php
session_start();
header('Content-Type: application/json');

$lobbyId = (int) ($_GET['lobby_id'] ?? 0);

if (!$lobbyId) {
    http_response_code(400);
    echo json_encode([]);
    exit;
}

require_once '../core/db.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode([]);
    exit;
}

$lobby = getLobbyById($lobbyId);
if (!$lobby) {
    http_response_code(404);
    echo json_encode([]);
    exit;
}

$players = getLobbyPlayers($lobbyId);
$userInLobby = false;
foreach ($players as $p) {
    if ($p['user_id'] == $_SESSION['user_id']) {
        $userInLobby = true;
        break;
    }
}

if (!$userInLobby) {
    http_response_code(403);
    echo json_encode([]);
    exit;
}

// Получить сообщения из БД
$messages = getChatMessages($lobbyId);

echo json_encode($messages);
