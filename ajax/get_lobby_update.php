<?php
session_start();
require_once '../core/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    exit;
}

$lobbyId = (int) ($_GET['lobby_id'] ?? 0);
if (!$lobbyId) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing lobby_id']);
    exit;
}

// === ПРОВЕРКА CSRF ===
$csrfToken = $_GET['csrf_token'] ?? '';
if (!verifyCsrfToken($csrfToken)) {
    http_response_code(403);
    echo json_encode(['error' => 'Invalid CSRF token']);
    exit;
}

$lobby = getLobbyById($lobbyId);
if (!$lobby) {
    http_response_code(404);
    echo json_encode(['error' => 'Lobby not found']);
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
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

echo json_encode([
    'lobby' => $lobby,
    'players' => $players
]);
?>