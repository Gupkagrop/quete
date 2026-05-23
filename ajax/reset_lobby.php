<?php
session_start();
require_once '../core/db.php';

header('Content-Type: application/json');

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
$csrfToken = $data['csrf_token'] ?? '';
if (!verifyCsrfToken($csrfToken)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
    exit;
}

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

// 3. Удалить все вопросы этого лобби
$stmt = $pdo->prepare('DELETE FROM generated_questions WHERE lobby_id = :lid');
$stmt->execute(['lid' => $lobbyId]);

// 2. Установить статистику победителя
// Получить топ-3 игроков
$scores = getPlayerScores($lobbyId);
if (!empty($scores)) {
    $winner = $scores[0];
    
    // Обновить статистику победителя в таблице users
    $stmt = $pdo->prepare('UPDATE users SET wins_count = wins_count + 1 WHERE id = :uid');
    $stmt->execute(['uid' => $winner['user_id']]);
}

// 3. Обнулить очки всех игроков
$stmt = $pdo->prepare('UPDATE lobby_players SET current_points = 0 WHERE lobby_id = :lid');
$stmt->execute(['lid' => $lobbyId]);

// 4. Установить всех игроков в статус не готов (кроме хоста)
$stmt = $pdo->prepare('UPDATE lobby_players SET is_ready = 0 WHERE lobby_id = :lid AND user_id != :host_id');
$stmt->execute(['lid' => $lobbyId, 'host_id' => $lobby['host_id']]);

// 5. Хост остаётся готовым (на его усмотрение)
$stmt = $pdo->prepare('UPDATE lobby_players SET is_ready = 1 WHERE lobby_id = :lid AND user_id = :host_id');
$stmt->execute(['lid' => $lobbyId, 'host_id' => $lobby['host_id']]);

// 6. Установить лобби в неактивное состояние
$stmt = $pdo->prepare('UPDATE lobbies SET is_active = 0, current_round = 1 WHERE id = :lid');
$stmt->execute(['lid' => $lobbyId]);

echo json_encode(['success' => true, 'message' => 'Lobby reset successfully']);
?>
