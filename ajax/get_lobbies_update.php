<?php
/**
 * AJAX-скрипт для получения списка всех активных игровых комнат в главном меню.
 */

session_start();
require_once '../core/db.php';

// === ПРОВЕРКА CSRF ===
// Защита от подделки запросов со сторонних сайтов
$csrfToken = $_GET['csrf_token'] ?? '';
if (!verifyCsrfToken($csrfToken)) {
    http_response_code(403);
    exit;
}

// Запрашиваем из базы данных список активных лобби
$lobbies = getLobbies();

// Оптимизация производительности: избегаем повторных тяжелых запросов к БД в цикле (проблема N+1).
// Берем уже сохраненное в кэше лобби количество игроков (current_players).
foreach ($lobbies as &$lobby) {
    $lobby['players_count'] = (int) $lobby['current_players'];
}

// Возвращаем список лобби в формате JSON
echo json_encode([
    'lobbies' => $lobbies
]);
?>