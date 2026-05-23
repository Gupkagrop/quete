<?php
session_start();
require_once '../core/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$lobbyId = (int) ($data['lobby_id'] ?? 0);

if (!$lobbyId) {
    http_response_code(400);
    exit;
}

$csrfToken = $data['csrf_token'] ?? '';
if (!verifyCsrfToken($csrfToken)) {
    http_response_code(403);
    exit;
}

$lobby = getLobbyById($lobbyId);
if (!$lobby || (int)$lobby['host_id'] !== (int)$_SESSION['user_id']) {
    http_response_code(403);
    exit;
}

$pdo = getPDO();

try {
    $pdo->beginTransaction();

    // 1. Деактивировать старый вопрос
    $pdo->prepare('UPDATE generated_questions SET is_active = 0 WHERE lobby_id = :lid AND is_active = 1')->execute(['lid' => $lobbyId]);

    // 2. Найти самый новый вопрос для этого лобби, который еще не активен и относится к текущему раунду лобби
    $stmt = $pdo->prepare('SELECT id FROM generated_questions WHERE lobby_id = :lid AND is_active = 0 AND round_number = :round ORDER BY id DESC LIMIT 1');
    $stmt->execute(['lid' => $lobbyId, 'round' => (int)$lobby['current_round']]);
    $nextId = $stmt->fetchColumn();

    if ($nextId) {
        // 3. Активировать его
        $pdo->prepare('UPDATE generated_questions SET is_active = 1 WHERE id = :id')->execute(['id' => $nextId]);
    }

    $pdo->commit();
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
