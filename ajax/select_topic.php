<?php
session_start();
require_once '../core/db.php';
require_once '../core/ai_handler.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$lobbyId = (int) ($data['lobby_id'] ?? 0);
$topic = trim($data['topic'] ?? '');

if (!$lobbyId || empty($topic)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid parameters']);
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
if (!$lobby || $lobby['responsible'] != $_SESSION['user_id']) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Only responsible can select topic']);
    exit;
}

$tempFile = sys_get_temp_dir() . '/quete_lobby_' . $lobbyId . '_gen.tmp';
file_put_contents($tempFile, time());

$pdo = getPDO();
try {
    $pdo->beginTransaction();

    // Заблокировать лобби для предотвращения гонки (Race Condition)
    $stmt = $pdo->prepare('SELECT current_round FROM lobbies WHERE id = :lid FOR UPDATE');
    $stmt->execute(['lid' => $lobbyId]);
    $lockedLobby = $stmt->fetch();

    if (!$lockedLobby) {
        throw new Exception('Lobby not found during lock');
    }

    // Проверить, не создан ли уже первый вопрос для текущего раунда
    $stmt = $pdo->prepare('SELECT id FROM generated_questions WHERE lobby_id = :lid AND round_number = :round AND question_number = 1');
    $stmt->execute(['lid' => $lobbyId, 'round' => (int) $lockedLobby['current_round']]);
    if ($stmt->fetch()) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'error' => 'Topic already selected for this round']);
        exit;
    }

    $previousQuestions = getPreviousQuestionTexts($lobbyId);

    // Генерируем вопрос и проверяем валидность
    try {
        $questionData = generateQuestionWithGroq($topic, $previousQuestions);
        if (!$questionData['valid']) {
            $questionData = generateQuestionStub($topic);
            $questionData['valid'] = true;
        }
    } catch (Exception $e) {
        // Фолбэк на заглушку при ошибке API
        $questionData = generateQuestionStub($topic);
        $questionData['valid'] = true;
    }

    // Сохраняем вопрос
    $questionId = generateQuestion(
        $lobbyId,
        $questionData['question'],
        $questionData['correct'],
        $questionData['fakes'],
        $topic,
        (int) $lockedLobby['current_round'],
        1  // question_number = 1 (первый вопрос в раунде)
    );

    $pdo->commit();
    echo json_encode(['success' => true, 'question_id' => $questionId]);
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Internal server error']);
} finally {
    @unlink($tempFile);
}
?>