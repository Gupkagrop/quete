<?php
/**
 * AJAX-скрипт для запуска матча. Проверяет готовность игроков и переводит лобби в активное состояние.
 */

session_start();
require_once '../core/db.php';

header('Content-Type: application/json');

// Проверяем авторизацию пользователя и наличие идентификатора лобби
if (!isset($_SESSION['user_id']) || !isset($_POST['lobby_id'])) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// === ПРОВЕРКА CSRF ===
// Защита от межсайтовой подделки запросов
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

// Проверить, действительно ли этот игрок является создателем (хостом) комнаты
if ($lobby['host_id'] != $_SESSION['user_id']) {
    echo json_encode(['error' => 'Only host can start game']);
    exit;
}

// Получаем список игроков в комнате
$players = getLobbyPlayers($lobbyId);

// Проверить условия: для игры требуется как минимум 2 игрока
if (count($players) < 2) {
    echo json_encode(['error' => 'Need at least 2 players']);
    exit;
}

// Проверить, что все подключившиеся игроки нажали кнопку «Готов»
if (!areAllPlayersReady($lobbyId)) {
    echo json_encode(['error' => 'Not all players are ready']);
    exit;
}

// Запустить игру: перевести статус лобби в активный, сбросить очки и выбрать ответственного за выбор темы
startGame($lobbyId);

// Возвращаем успешный ответ и адрес страницы игрового процесса
echo json_encode(['success' => true, 'redirect' => 'game.php?lobby_id=' . $lobbyId]);
?>
