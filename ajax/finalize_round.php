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
if (!$lobby) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Lobby not found']);
    exit;
}

// Проверить, что пользователь в лобби
$players = getLobbyPlayers($lobbyId);
$inLobby = false;
foreach ($players as $p) {
    if ((int)$p['user_id'] == $_SESSION['user_id']) {
        $inLobby = true;
        break;
    }
}

if (!$inLobby) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Forbidden']);
    exit;
}

// === ПРОВЕРКА НА ХОСТА ===
// Только хост может инициировать переход
if ((int)$lobby['host_id'] !== (int)$_SESSION['user_id']) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Only host can finalize round']);
    exit;
}

$pdo = getPDO();

// Получить АКТИВНЫЙ вопрос
$stmt = $pdo->prepare('SELECT * FROM generated_questions WHERE lobby_id = :lid AND is_active = 1');
$stmt->execute(['lid' => $lobbyId]);
$currentQuestion = $stmt->fetch();

if (!$currentQuestion) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'No active question']);
    exit;
}

$multiplier = ROUND_MULTIPLIERS[(int) $lobby['current_round'] - 1] ?? 1;

try {
    $pdo->beginTransaction();
    
    // Заблокировать лобби для обновления
    $stmt = $pdo->prepare('SELECT * FROM lobbies WHERE id = :lid FOR UPDATE');
    $stmt->execute(['lid' => $lobbyId]);
    $lockedLobby = $stmt->fetch();

    if (!$lockedLobby || !$lockedLobby['is_active']) {
        $pdo->rollBack();
        echo json_encode(['status' => 'already_finished', 'message' => 'Lobby is inactive']);
        exit;
    }

    $stmt = $pdo->prepare('SELECT COUNT(*) FROM generated_questions WHERE lobby_id = :lid AND round_number = :round');
    $stmt->execute(['lid' => $lobbyId, 'round' => (int) $lockedLobby['current_round']]);
    $totalQuestionsInRound = (int) $stmt->fetchColumn();

    $scores = getPlayerScores($lobbyId);

    if ($totalQuestionsInRound < 3) {
        if ($totalQuestionsInRound > $currentQuestion['question_number']) {
            $pdo->rollBack();
            echo json_encode(['status' => 'already_finished']);
            exit;
        }

        // Вставляем заглушку, чтобы заблокировать повторные вызовы от хоста
        $nextQuestionId = generateQuestion(
            $lobbyId,
            'GENERATING_NEXT_QUESTION',
            '',
            [],
            $currentQuestion['topic'],
            (int) $lockedLobby['current_round'],
            $totalQuestionsInRound + 1,
            false // is_active = false
        );

        $pdo->commit();

        // Генерируем следующий вопрос в том же раунде
        $topic = $currentQuestion['topic'];
        $previousQuestions = getPreviousQuestionTexts($lobbyId);
        
        try {
            $questionData = generateQuestionWithGroq($topic, $previousQuestions);
            if (!$questionData['valid']) {
                $questionData = generateQuestionStub($topic);
                $questionData['valid'] = true;
            }
        } catch (Exception $e) {
            $questionData = generateQuestionStub($topic);
            $questionData['valid'] = true;
        }

        // Обновляем заглушку настоящими данными
        $pdo2 = getPDO();
        $stmt = $pdo2->prepare('UPDATE generated_questions SET question_text = ?, correct_answer = ?, fake_answers = ? WHERE id = ?');
        $stmt->execute([
            $questionData['question'],
            $questionData['correct'],
            json_encode($questionData['fakes'], JSON_UNESCAPED_UNICODE),
            $nextQuestionId
        ]);

        echo json_encode([
            'status' => 'next_question',
            'currentRound' => (int) $lockedLobby['current_round'],
            'scores' => $scores
        ]);
    } else {
        // Переход к следующему раунду
        if ($lockedLobby['current_round'] > $lobby['current_round']) {
             $pdo->rollBack();
             echo json_encode(['status' => 'already_finished']);
             exit;
        }

        $nextRound = (int) $lockedLobby['current_round'] + 1;
        if ($nextRound <= ROUNDS_COUNT) {
            updateLobbyRound($lobbyId, $nextRound);
            setRandomResponsible($lobbyId); // Выбираем нового ответственного для нового раунда
            $pdo->commit();
            
            echo json_encode([
                'status' => 'next_round',
                'currentRound' => (int) $lockedLobby['current_round'],
                'nextRound' => $nextRound,
                'scores' => $scores
            ]);
        } else {
            finishGame($lobbyId, $scores);
            $pdo->commit();
            
            echo json_encode([
                'status' => 'game_finished',
                'scores' => $scores
            ]);
        }
    }

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    exit;
}
