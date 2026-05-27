<?php
/**
 * AJAX-скрипт сброса игры. Очищает вопросы, голоса и очки, готовя комнату к новому матчу.
 */

session_start();
require_once '../core/db.php';

header('Content-Type: application/json');

// Проверяем авторизацию пользователя
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$lobbyId = (int) ($data['lobby_id'] ?? 0);

if (!$lobbyId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing lobby_id']);
    exit;
}

// === ПРОВЕРКА CSRF ===
// Проверка защитного токена сессии
$csrfToken = $data['csrf_token'] ?? '';
if (!verifyCsrfToken($csrfToken)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
    exit;
}

// Получаем лобби и проверяем, является ли текущий пользователь хостом этой комнаты
$lobby = getLobbyById($lobbyId);
if (!$lobby || $lobby['host_id'] != $_SESSION['user_id']) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Forbidden']);
    exit;
}

$pdo = getPDO();

// 1. Удалить все голоса за вопросы этого лобби (очищает осиротевшие записи)
$stmt = $pdo->prepare('DELETE v FROM votes v INNER JOIN generated_questions gq ON v.question_id = gq.id WHERE gq.lobby_id = :lid');
$stmt->execute(['lid' => $lobbyId]);

// 2. Удалить все ответы игроков за вопросы этого лобби
$stmt = $pdo->prepare('DELETE pa FROM player_answers pa INNER JOIN generated_questions gq ON pa.question_id = gq.id WHERE gq.lobby_id = :lid');
$stmt->execute(['lid' => $lobbyId]);

// 3. Удалить все сгенерированные ИИ вопросы этого лобби
$stmt = $pdo->prepare('DELETE FROM generated_questions WHERE lobby_id = :lid');
$stmt->execute(['lid' => $lobbyId]);

// 4. Обнулить очки всех игроков в этой комнате
$stmt = $pdo->prepare('UPDATE lobby_players SET current_points = 0 WHERE lobby_id = :lid');
$stmt->execute(['lid' => $lobbyId]);

// 5. Установить всех игроков в статус «не готов» (кроме хоста комнаты)
$stmt = $pdo->prepare('UPDATE lobby_players SET is_ready = 0 WHERE lobby_id = :lid AND user_id != :host_id');
$stmt->execute(['lid' => $lobbyId, 'host_id' => $lobby['host_id']]);

// 6. Хост (создатель комнаты) автоматически остаётся в статусе «готов»
$stmt = $pdo->prepare('UPDATE lobby_players SET is_ready = 1 WHERE lobby_id = :lid AND user_id = :host_id');
$stmt->execute(['lid' => $lobbyId, 'host_id' => $lobby['host_id']]);

// 7. Переводим лобби в неактивное состояние (is_active = 0) и сбрасываем счетчик раундов на 1
$stmt = $pdo->prepare('UPDATE lobbies SET is_active = 0, current_round = 1 WHERE id = :lid');
$stmt->execute(['lid' => $lobbyId]);

// Возвращаем успешный статус сброса
echo json_encode(['success' => true, 'message' => 'Lobby reset successfully']);
?>
