<?php
session_start();
require_once '../core/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || !isset($_POST['lobby_id'])) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// === ПРОВЕРКА CSRF ===
$csrfToken = $_POST['csrf_token'] ?? '';
if (!verifyCsrfToken($csrfToken)) {
    echo json_encode(['error' => 'Invalid CSRF token']);
    exit;
}

$lobbyId = (int) $_POST['lobby_id'];
$lobby = getLobbyById($lobbyId);

if (!$lobby) {
    echo json_encode(['error' => 'Lobby not found']);
    exit;
}

// Проверить, что это хост
if ($lobby['host_id'] != $_SESSION['user_id']) {
    echo json_encode(['error' => 'Only host can start game']);
    exit;
}

// Получить игроков
$players = getLobbyPlayers($lobbyId);

// Проверить условия
if (count($players) < 2) {
    echo json_encode(['error' => 'Need at least 2 players']);
    exit;
}

if (!areAllPlayersReady($lobbyId)) {
    echo json_encode(['error' => 'Not all players are ready']);
    exit;
}

// Запустить игру
startGame($lobbyId);

echo json_encode(['success' => true, 'redirect' => 'game.php?lobby_id=' . $lobbyId]);
?>
