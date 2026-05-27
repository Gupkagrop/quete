<?php
/**
 * AJAX-скрипт для отправки голоса за выбранный ответ. Запрещает голосовать за себя или дважды.
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
$questionId = (int) ($data['question_id'] ?? 0);
$answer = $data['answer'] ?? '';

if (!$lobbyId || !$questionId || empty($answer)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid parameters']);
    exit;
}

// === ПРОВЕРКА CSRF ===
// Защита от подделки запросов со сторонних сайтов
$csrfToken = $data['csrf_token'] ?? '';
if (!verifyCsrfToken($csrfToken)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
    exit;
}

// Проверяем существование лобби
$lobby = getLobbyById($lobbyId);
if (!$lobby) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Lobby not found']);
    exit;
}

// Проверяем, совпадает ли вопрос с текущим активным вопросом
$question = getCurrentQuestion($lobbyId);
if (!$question || $question['id'] != $questionId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Question not found or inactive']);
    exit;
}

// === КРИТИЧЕСКАЯ ПРОВЕРКА: НЕЛЬЗЯ ГОЛОСОВАТЬ ЗА СОБСТВЕННЫЙ ОТВЕТ ===
// Чтобы игроки не накручивали очки за свои же фейки, голосование за свой ответ блокируется.
if (isVoteForOwnAnswer($questionId, $_SESSION['user_id'], $answer)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Вы не можете голосовать за свой собственный ответ']);
    exit;
}

// === Проверяем, не голосовал ли уже этот игрок ранее ===
$pdo = getPDO();
$stmt = $pdo->prepare('SELECT 1 FROM votes WHERE question_id = :qid AND voter_id = :uid LIMIT 1');
$stmt->execute(['qid' => $questionId, 'uid' => $_SESSION['user_id']]);
if ($stmt->fetch()) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'You already voted for this question']);
    exit;
}

// Записываем голос в базу данных
submitVote($questionId, $_SESSION['user_id'], $answer);

echo json_encode(['success' => true, 'message' => 'Vote submitted successfully']);
?>