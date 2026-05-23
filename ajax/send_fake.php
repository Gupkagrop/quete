<?php
session_start();
require_once '../core/db.php';
require_once '../core/ai_handler.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    exit;
}

$lobbyId = (int) ($_POST['lobby_id'] ?? 0);
$questionId = (int) ($_POST['question_id'] ?? 0);
$fake = trim($_POST['fake_answer'] ?? '');

if (!$lobbyId || !$questionId || empty($fake)) {
    http_response_code(400);
    exit;
}

// === ПРОВЕРКА CSRF ===
$csrfToken = $_POST['csrf_token'] ?? '';
if (!verifyCsrfToken($csrfToken)) {
    http_response_code(403);
    exit;
}

$lobby = getLobbyById($lobbyId);
if (!$lobby) {
    http_response_code(404);
    exit;
}

$question = getCurrentQuestion($lobbyId);
if (!$question || $question['id'] != $questionId) {
    http_response_code(400);
    exit;
}

// Проверка похожести фейка с правильным ответом
$tooSimilar = isAnswerTooCloseToCorrect($fake, $question['correct_answer']);
if ($tooSimilar) {
    header('Content-Type: application/json');
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Ответ слишком похож на правильный. Пожалуйста, придумай другой фейк.',
        'reason' => 'Локальная проверка: ответ слишком близок к правильному'
    ]);
    exit;
}


$pdo = getPDO();

// Проверка: игрок уже отправил фейк?
$stmt = $pdo->prepare('SELECT 1 FROM player_answers WHERE question_id = :qid AND user_id = :uid LIMIT 1');
$stmt->execute(['qid' => $questionId, 'uid' => $_SESSION['user_id']]);
if ($stmt->fetch()) {
    header('Content-Type: application/json');
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Вы уже отправили свой вариант ответа']);
    exit;
}

// Проверка: не ввел ли кто-то уже такой же фейк?
$stmt = $pdo->prepare('SELECT 1 FROM player_answers WHERE question_id = :qid AND LOWER(answer_text) = LOWER(:ans) LIMIT 1');
$stmt->execute(['qid' => $questionId, 'ans' => $fake]);
if ($stmt->fetch()) {
    header('Content-Type: application/json');
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Такой вариант уже был предложен кем-то другим. Придумай новый!']);
    exit;
}

submitFakeAnswer($questionId, $_SESSION['user_id'], $fake);

header('Content-Type: application/json');
echo json_encode(['success' => true]);
?>