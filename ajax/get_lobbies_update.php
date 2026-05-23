<?php
session_start();
require_once '../core/db.php';

// === ПРОВЕРКА CSRF ===
$csrfToken = $_GET['csrf_token'] ?? '';
if (!verifyCsrfToken($csrfToken)) {
    http_response_code(403);
    exit;
}

$lobbies = getLobbies();

// Оптимизация: Избегаем N+1 запросов getPlayerCount(), используя уже полученные current_players
foreach ($lobbies as &$lobby) {
    $lobby['players_count'] = (int) $lobby['current_players'];
}

echo json_encode([
    'lobbies' => $lobbies
]);
?>