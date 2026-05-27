<?php
/**
 * AJAX-скрипт отправки ложного ответа игрока. Проверяет уникальность ответа и его отличие от правильного.
 */

session_start();
require_once '../core/db.php';
require_once '../core/ai_handler.php';

// Проверяем авторизацию пользователя
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
// Защита от межсайтовой подделки запросов
$csrfToken = $_POST['csrf_token'] ?? '';
if (!verifyCsrfToken($csrfToken)) {
    http_response_code(403);
    exit;
}

// Проверяем существование лобби
$lobby = getLobbyById($lobbyId);
if (!$lobby) {
    http_response_code(404);
    exit;
}

// Проверяем, совпадает ли вопрос с текущим активным вопросом лобби
$question = getCurrentQuestion($lobbyId);
if (!$question || $question['id'] != $questionId) {
    http_response_code(400);
    exit;
}

// === ПРОВЕРКА НА СХОЖЕСТЬ С ПРАВИЛЬНЫМ ОТВЕТОМ ===
// Если введенный игроком фейк слишком похож на правильный ответ (по метрике схожести текста),
// мы отклоняем его, чтобы игрок случайно не раскрыл правильный ответ остальным.
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

// === ПРОВЕРКА: игрок уже отправил фейк? ===
// Запрещаем одному и тому же игроку присылать несколько ответов на один вопрос.
$stmt = $pdo->prepare('SELECT 1 FROM player_answers WHERE question_id = :qid AND user_id = :uid LIMIT 1');
$stmt->execute(['qid' => $questionId, 'uid' => $_SESSION['user_id']]);
if ($stmt->fetch()) {
    header('Content-Type: application/json');
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Вы уже отправили свой вариант ответа']);
    exit;
}

// === ПРОВЕРКА: не ввел ли кто-то уже такой же фейк? ===
// Варианты ответов на экране голосования должны быть уникальными, чтобы игроки не путались.
$stmt = $pdo->prepare('SELECT 1 FROM player_answers WHERE question_id = :qid AND LOWER(answer_text) = LOWER(:ans) LIMIT 1');
$stmt->execute(['qid' => $questionId, 'ans' => $fake]);
if ($stmt->fetch()) {
    header('Content-Type: application/json');
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Такой вариант уже был предложен кем-то другим. Придумай новый!']);
    exit;
}

// Если все проверки пройдены, сохраняем ложный ответ игрока в базе данных
submitFakeAnswer($questionId, $_SESSION['user_id'], $fake);

header('Content-Type: application/json');
echo json_encode(['success' => true]);
?>