<?php
session_start();
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
error_reporting(E_ALL);
require_once '../core/db.php';
require_once '../core/ai_handler.php';

header('Content-Type: application/json; charset=utf-8');

set_error_handler(function ($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity)) {
        return false;
    }
    throw new ErrorException($message, 0, $severity, $file, $line);
});

register_shutdown_function(function () {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR], true)) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'error' => 'Internal server error',
            'details' => $error['message'] . ' in ' . $error['file'] . ' on line ' . $error['line']
        ]);
    }
});

try {
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Unauthorized']);
        exit;
    }

$lobbyId = (int) ($_GET['lobby_id'] ?? 0);
if (!$lobbyId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing lobby_id']);
    exit;
}

// === ПРОВЕРКА CSRF ===
$csrfToken = $_GET['csrf_token'] ?? '';
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
    if ((int) $p['user_id'] == $_SESSION['user_id']) {
        $inLobby = true;
        break;
    }
}
if (!$inLobby) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Forbidden']);
    exit;
}

$scores = getPlayerScores($lobbyId);
$pdo = getPDO();

// Получить АКТИВНЫЙ вопрос (is_active = 1)
$stmt = $pdo->prepare('SELECT * FROM generated_questions WHERE lobby_id = :lid AND is_active = 1 LIMIT 1');
$stmt->execute(['lid' => $lobbyId]);
$currentQuestion = $stmt->fetch();

// Проверить состояния
$questionsInRound = 0;
$allPlayersSubmittedFakes = false;
$allVoted = false;
$timeoutElapsed = 0;
$needsAutoTopic = false;
$needsAutoFakes = [];

if ($currentQuestion) {
    // === ОБРАБОТКА ТАЙМАУТОВ ===
    $timeoutElapsed = getQuestionElapsedTime($currentQuestion['id']);
    $fakeAnswerTimeout = (int)$lobby['fake_answer_time'];
    
    // Проверка таймаута на выбор темы (если вопрос только что создан и тема не выбрана ИИ)
    if ($timeoutElapsed >= $fakeAnswerTimeout && !$currentQuestion['auto_topic_selected']) {
        // Автоматически выбрать тему и регенерировать вопрос если нужно
        // На самом деле, если мы здесь, это значит что тема уже была выбрана (в select_topic.php)
        // Это более сложный сценарий для будущего расширения
    }
    
    // Подсчитать вопросы в раунде
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM generated_questions WHERE lobby_id = :lid AND round_number = :round');
    $stmt->execute(['lid' => $lobbyId, 'round' => (int) $lobby['current_round']]);
    $questionsInRound = (int) $stmt->fetchColumn();

    // Получить всех игроков
    $playerCount = count($players);

    // Проверить, отправили ли все игроки фейки
    $stmt = $pdo->prepare('SELECT COUNT(DISTINCT user_id) FROM player_answers WHERE question_id = :qid');
    $stmt->execute(['qid' => $currentQuestion['id']]);
    $fakesCount = (int) $stmt->fetchColumn();
    $allPlayersSubmittedFakes = ($fakesCount >= $playerCount);

    // === ОБРАБОТКА ТАЙМАУТА НА ФЕЙК ===
    if (!$allPlayersSubmittedFakes && $timeoutElapsed >= $fakeAnswerTimeout) {
        // Проверим, существует ли колонка auto_fakes_applied
        $columnExists = false;
        try {
            $checkCol = $pdo->query("SHOW COLUMNS FROM generated_questions LIKE 'auto_fakes_applied'");
            if ($checkCol && $checkCol->fetch()) {
                $columnExists = true;
            }
        } catch (Exception $e) {
            $columnExists = false;
        }

        $shouldApply = false;
        if ($columnExists) {
            // Транзакционный SELECT FOR UPDATE
            $pdo->beginTransaction();
            $stmt = $pdo->prepare('SELECT auto_fakes_applied FROM generated_questions WHERE id = :qid FOR UPDATE');
            $stmt->execute(['qid' => $currentQuestion['id']]);
            $qInfo = $stmt->fetch();
            if ($qInfo && !(int)$qInfo['auto_fakes_applied']) {
                $shouldApply = true;
                $stmt = $pdo->prepare('UPDATE generated_questions SET auto_fakes_applied = 1 WHERE id = :qid');
                $stmt->execute(['qid' => $currentQuestion['id']]);
            }
            $pdo->commit();
        } else {
            // Фолбэк: берем первого активного игрока из отсортированного списка
            $sortedPlayerIds = array_map(function($p) { return (int)$p['user_id']; }, $players);
            sort($sortedPlayerIds);
            if (!empty($sortedPlayerIds) && (int)$_SESSION['user_id'] === $sortedPlayerIds[0]) {
                $shouldApply = true;
            }
        }

        if ($shouldApply) {
            // Найти игроков, которые не отправили фейк
            $playersWithoutFakes = getPlayersWithoutFakeAnswer($currentQuestion['id'], $lobbyId);
            
            // Автоматически выбрать фейк для каждого
            foreach ($playersWithoutFakes as $userId) {
                autoSelectFakeAnswerForPlayer($currentQuestion['id'], $userId);
                $needsAutoFakes[] = $userId;
            }
            
            // Пересчитать количество отправивших фейки
            $stmt = $pdo->prepare('SELECT COUNT(DISTINCT user_id) FROM player_answers WHERE question_id = :qid');
            $stmt->execute(['qid' => $currentQuestion['id']]);
            $fakesCount = (int) $stmt->fetchColumn();
            $allPlayersSubmittedFakes = ($fakesCount >= $playerCount);
        } else if ($columnExists) {
            // Если транзакционно уже применилось другим процессом, просто пересчитаем
            $stmt = $pdo->prepare('SELECT COUNT(DISTINCT user_id) FROM player_answers WHERE question_id = :qid');
            $stmt->execute(['qid' => $currentQuestion['id']]);
            $fakesCount = (int) $stmt->fetchColumn();
            $allPlayersSubmittedFakes = ($fakesCount >= $playerCount);
        }
    }

    // Проверить, голосовали ли все игроки
    $stmt = $pdo->prepare('SELECT COUNT(DISTINCT voter_id) FROM votes WHERE question_id = :qid');
    $stmt->execute(['qid' => $currentQuestion['id']]);
    $votesCount = (int) $stmt->fetchColumn();
    $allVoted = ($votesCount >= $playerCount);
}

// Получить все ответы для текущего вопроса
$answers = [];
$playerAnswers = [];
$userOwnAnswer = null;

if ($currentQuestion) {
    // Получить количество голосов за правильный ответ
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM votes WHERE question_id = :qid AND LOWER(TRIM(selected_answer_text)) = LOWER(TRIM(:correct))');
    $stmt->execute(['qid' => $currentQuestion['id'], 'correct' => $currentQuestion['correct_answer']]);
    $correctAnswerVotes = (int) $stmt->fetchColumn();

    // Добавить правильный ответ
    $answers[$currentQuestion['correct_answer']] = [
        'text' => $currentQuestion['correct_answer'],
        'is_correct' => true,
        'author' => null,
        'votes' => $correctAnswerVotes
    ];

    // Получить собственный ответ текущего пользователя
    $userOwnAnswer = getPlayerOwnAnswer($currentQuestion['id'], $_SESSION['user_id']);

    // Добавить фейки игроков
    $stmt = $pdo->prepare(
        'SELECT pa.answer_text, pa.user_id, u.username, MAX(COALESCE(vote_counts.votes, 0)) as votes ' .
        'FROM player_answers pa ' .
        'JOIN users u ON pa.user_id = u.id ' .
        'LEFT JOIN (SELECT selected_answer_text, COUNT(*) as votes FROM votes WHERE question_id = :qid_votes GROUP BY selected_answer_text) vote_counts ' .
        'ON LOWER(TRIM(pa.answer_text)) = LOWER(TRIM(vote_counts.selected_answer_text)) ' .
        'WHERE pa.question_id = :qid_main ' .
        'GROUP BY pa.answer_text, pa.user_id, u.username'
    );
    $stmt->execute([
        'qid_votes' => $currentQuestion['id'],
        'qid_main' => $currentQuestion['id'],
    ]);
    $fakes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($fakes as $fake) {
        $answers[$fake['answer_text']] = [
            'text' => $fake['answer_text'],
            'is_correct' => false,
            'author' => $fake['username'],
            'votes' => (int) $fake['votes']
        ];
    }

    // Получить выбранный ответ текущего пользователя
    $stmt = $pdo->prepare('SELECT selected_answer_text FROM votes WHERE question_id = :qid AND voter_id = :uid');
    $stmt->execute(['qid' => $currentQuestion['id'], 'uid' => $_SESSION['user_id']]);
    $userVote = $stmt->fetchColumn();
}

// Декодировать JSON для fakes если нужно
if ($currentQuestion && isset($currentQuestion['fake_answers'])) {
    if (is_string($currentQuestion['fake_answers'])) {
        $currentQuestion['fake_answers'] = json_decode($currentQuestion['fake_answers'], true);
    }
}

$tempFile = sys_get_temp_dir() . '/quete_lobby_' . $lobbyId . '_gen.tmp';
$isGenerating = false;
if (file_exists($tempFile)) {
    if (time() - filemtime($tempFile) > 40) {
        @unlink($tempFile);
    } else {
        $isGenerating = true;
    }
}

echo json_encode([
    'lobby' => $lobby,
    'players' => $players,
    'scores' => $scores,
    'currentQuestion' => $currentQuestion,
    'answers' => $answers,
    'allPlayersSubmittedFakes' => $allPlayersSubmittedFakes,
    'allVoted' => $allVoted,
    'questionsInRound' => $questionsInRound,
    'userVote' => $userVote ?? null,
    'userOwnAnswer' => $userOwnAnswer,
    'currentRound' => (int) $lobby['current_round'],
    'totalRounds' => ROUNDS_COUNT,
    'timeoutElapsed' => $timeoutElapsed,
    'fakeAnswerTimeout' => (int)$lobby['fake_answer_time'],
    'autoFakesApplied' => $needsAutoFakes,
    'isGenerating' => $isGenerating,
]);
} catch (Throwable $e) {
    http_response_code(500);
    error_log('ajax/game_state_update.php error: ' . $e->getMessage());
    error_log($e->getTraceAsString());
    echo json_encode([
        'success' => false,
        'error' => 'Internal server error',
        'details' => $e->getMessage()
    ]);
    exit;
}
?>
