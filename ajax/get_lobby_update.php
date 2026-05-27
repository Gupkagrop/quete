<?php
/**
 * AJAX-скрипт получения статуса комнаты ожидания (состав игроков и их готовность).
 */

session_start();
require_once '../core/db.php';

header('Content-Type: application/json');

// Проверяем авторизацию текущего пользователя
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
// Проверка секретного ключа сессии для защиты от подделки запросов
$csrfToken = $_GET['csrf_token'] ?? '';
if (!verifyCsrfToken($csrfToken)) {
    http_response_code(403);
    echo json_encode(['error' => 'Invalid CSRF token']);
    exit;
}

// Загружаем данные лобби
$lobby = getLobbyById($lobbyId);
if (!$lobby) {
    http_response_code(404);
    echo json_encode(['error' => 'Lobby not found']);
    exit;
}

// Проверяем, действительно ли запрашивающий игрок состоит в этом лобби
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

// Возвращаем данные о лобби и массив всех подключенных игроков с их статусами
echo json_encode([
    'lobby' => $lobby,
    'players' => $players
]);
?>