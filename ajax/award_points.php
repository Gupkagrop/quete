<?php
/**
 * AJAX-скрипт для начисления игрокам очков за ответы по окончании раунда.
 */

session_start();
require_once '../core/db.php';

header('Content-Type: application/json');

// Проверка авторизации
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

// Защита от CSRF-атак
$csrfToken = $data['csrf_token'] ?? '';
if (!verifyCsrfToken($csrfToken)) {
    http_response_code(403);
    exit;
}

// Проверяем, что запрос делает именно создатель лобби (хост)
$lobby = getLobbyById($lobbyId);
if (!$lobby || (int)$lobby['host_id'] !== (int)$_SESSION['user_id']) {
    http_response_code(403);
    exit;
}

$pdo = getPDO();
// Ищем активный вопрос лобби, за который очки еще не начислили
$stmt = $pdo->prepare('SELECT * FROM generated_questions WHERE lobby_id = :lid AND is_active = 1 AND points_awarded = 0');
$stmt->execute(['lid' => $lobbyId]);
$currentQuestion = $stmt->fetch();

if (!$currentQuestion) {
    echo json_encode(['success' => true, 'message' => 'Points already awarded or no active question']);
    exit;
}

// Высчитываем множитель очков для текущего раунда (умножение очков на х1, х2, х3 в поздних раундах)
$multiplier = ROUND_MULTIPLIERS[(int) $lobby['current_round'] - 1] ?? 1;

try {
    // Начинаем транзакцию для безопасности записи данных
    $pdo->beginTransaction();
    
    // Шаг 1: Ищем игроков, проголосовавших за правильный ответ ИИ, и начисляем им очки
    $stmt = $pdo->prepare('SELECT voter_id FROM votes WHERE question_id = :qid AND LOWER(TRIM(selected_answer_text)) = LOWER(TRIM(:correct))');
    $stmt->execute(['qid' => $currentQuestion['id'], 'correct' => $currentQuestion['correct_answer']]);
    $correctVotes = $stmt->fetchAll();

    foreach ($correctVotes as $vote) {
        $points = BASE_POINTS * $multiplier;
        updatePlayerScore($lobbyId, $vote['voter_id'], $points);
    }

    // Шаг 2: Ищем игроков, чьи ложные ответы обманули других игроков, и начисляем им по 5 очков за каждый чужой голос
    $stmt = $pdo->prepare(
        'SELECT pa.user_id, pa.answer_text, COUNT(v.id) as votes ' .
        'FROM player_answers pa ' .
        'LEFT JOIN votes v ON LOWER(TRIM(v.selected_answer_text)) = LOWER(TRIM(pa.answer_text)) AND v.question_id = :qid1 ' .
        'WHERE pa.question_id = :qid2 ' .
        'GROUP BY pa.user_id, pa.answer_text'
    );
    $stmt->execute(['qid1' => $currentQuestion['id'], 'qid2' => $currentQuestion['id']]);
    $fakes = $stmt->fetchAll();

    foreach ($fakes as $fake) {
        if ((int) $fake['votes'] > 0) {
            $points = 5 * $multiplier * (int) $fake['votes'];
            updatePlayerScore($lobbyId, (int) $fake['user_id'], $points);
        }
    }

    // Помечаем в базе данных, что очки за этот вопрос успешно распределены
    $stmt = $pdo->prepare('UPDATE generated_questions SET points_awarded = 1 WHERE id = :qid');
    $stmt->execute(['qid' => $currentQuestion['id']]);

    $pdo->commit();
    
    echo json_encode(['success' => true, 'scores' => getPlayerScores($lobbyId)]);
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

