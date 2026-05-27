<?php
/**
 * AJAX-скрипт получения текущего состояния игры (игроки, очки, вопросы).
 * Также автоматически подставляет ИИ-ответы (авто-фейки) игрокам, которые не успели сделать ход вовремя.
 */

session_start();
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
error_reporting(E_ALL);
require_once '../core/db.php';
require_once '../core/ai_handler.php';

// Указываем браузеру, что сервер вернет ответ в формате JSON в кодировке UTF-8
header('Content-Type: application/json; charset=utf-8');

// Кастомный обработчик ошибок PHP: превращает предупреждения в исключения (Exceptions) для более удобного отлова
set_error_handler(function ($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity)) {
        return false;
    }
    throw new ErrorException($message, 0, $severity, $file, $line);
});

// Перехват критических ошибок (например, синтаксических сбоев) перед завершением работы скрипта
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
    // Проверяем авторизацию пользователя
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
// Защита от межсайтовой подделки запросов (проверяем токен сессии)
$csrfToken = $_GET['csrf_token'] ?? '';
if (!verifyCsrfToken($csrfToken)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
    exit;
}

// Загружаем данные лобби
$lobby = getLobbyById($lobbyId);
if (!$lobby) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Lobby not found']);
    exit;
}

// Проверяем, состоит ли этот пользователь в данной игровой комнате
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

// Запрашиваем актуальный счет игроков
$scores = getPlayerScores($lobbyId);
$pdo = getPDO();

// Находим текущий активный вопрос для этой комнаты
$stmt = $pdo->prepare('SELECT * FROM generated_questions WHERE lobby_id = :lid AND is_active = 1 LIMIT 1');
$stmt->execute(['lid' => $lobbyId]);
$currentQuestion = $stmt->fetch();

$questionsInRound = 0;
$allPlayersSubmittedFakes = false;
$allVoted = false;
$timeoutElapsed = 0;
$needsAutoTopic = false;
$needsAutoFakes = [];

if ($currentQuestion) {
    // === ОБРАБОТКА ТАЙМАУТОВ ===
    // Вычисляем, сколько секунд назад был задан вопрос
    $timeoutElapsed = getQuestionElapsedTime($currentQuestion['id']);
    $fakeAnswerTimeout = (int)$lobby['fake_answer_time'];
    
    // Проверка таймаута на выбор темы (зарезервировано для автоматического выбора, если игроки медлят)
    if ($timeoutElapsed >= $fakeAnswerTimeout && !$currentQuestion['auto_topic_selected']) {
        // Логика авто-выбора темы при необходимости
    }
    
    // Подсчитываем порядковый номер текущего вопроса в раунде
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM generated_questions WHERE lobby_id = :lid AND round_number = :round');
    $stmt->execute(['lid' => $lobbyId, 'round' => (int) $lobby['current_round']]);
    $questionsInRound = (int) $stmt->fetchColumn();

    $playerCount = count($players);

    // Проверяем, сколько игроков уже прислали свои фальшивые ответы
    $stmt = $pdo->prepare('SELECT COUNT(DISTINCT user_id) FROM player_answers WHERE question_id = :qid');
    $stmt->execute(['qid' => $currentQuestion['id']]);
    $fakesCount = (int) $stmt->fetchColumn();
    $allPlayersSubmittedFakes = ($fakesCount >= $playerCount);

    // === ОБРАБОТКА ТАЙМАУТА НА ВВОД ФЕЙКА ===
    // Если время вышло, но кто-то не отправил свой ложный ответ
    if (!$allPlayersSubmittedFakes && $timeoutElapsed >= $fakeAnswerTimeout) {
        // Проверяем, поддерживает ли база данных колонку блокировки авто-фейков,
        // чтобы избежать двойной обработки от разных игроков
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
            // Транзакционно проверяем и обновляем флаг применения авто-фейков
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
            // Фолбэк (если колонки нет в БД): обработку берет на себя первый игрок в списке
            $sortedPlayerIds = array_map(function($p) { return (int)$p['user_id']; }, $players);
            sort($sortedPlayerIds);
            if (!empty($sortedPlayerIds) && (int)$_SESSION['user_id'] === $sortedPlayerIds[0]) {
                $shouldApply = true;
            }
        }

        // Если текущий запрос отвечает за генерацию авто-фейков
        if ($shouldApply) {
            // Ищем список пользователей, которые еще не прислали ответ
            $playersWithoutFakes = getPlayersWithoutFakeAnswer($currentQuestion['id'], $lobbyId);
            
            // Каждому из них подставляем случайный ложный ответ из пула ИИ
            foreach ($playersWithoutFakes as $userId) {
                autoSelectFakeAnswerForPlayer($currentQuestion['id'], $userId);
                $needsAutoFakes[] = $userId; // Запоминаем, кому применили авто-фейк
            }
            
            // Пересчитываем количество готовых ответов после подстановки авто-фейков
            $stmt = $pdo->prepare('SELECT COUNT(DISTINCT user_id) FROM player_answers WHERE question_id = :qid');
            $stmt->execute(['qid' => $currentQuestion['id']]);
            $fakesCount = (int) $stmt->fetchColumn();
            $allPlayersSubmittedFakes = ($fakesCount >= $playerCount);
        } else if ($columnExists) {
            // Если транзакцию применил другой конкурентный запрос, просто пересчитываем состояние
            $stmt = $pdo->prepare('SELECT COUNT(DISTINCT user_id) FROM player_answers WHERE question_id = :qid');
            $stmt->execute(['qid' => $currentQuestion['id']]);
            $fakesCount = (int) $stmt->fetchColumn();
            $allPlayersSubmittedFakes = ($fakesCount >= $playerCount);
        }
    }

    // Проверяем, проголосовали ли все игроки за выбранные варианты
    $stmt = $pdo->prepare('SELECT COUNT(DISTINCT voter_id) FROM votes WHERE question_id = :qid');
    $stmt->execute(['qid' => $currentQuestion['id']]);
    $votesCount = (int) $stmt->fetchColumn();
    $allVoted = ($votesCount >= $playerCount);
}

// Формируем список вариантов ответов для экрана голосования/результатов
$answers = [];
$playerAnswers = [];
$userOwnAnswer = null;

if ($currentQuestion) {
    // Считаем, сколько человек проголосовало за эталонный правильный ответ
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM votes WHERE question_id = :qid AND LOWER(TRIM(selected_answer_text)) = LOWER(TRIM(:correct))');
    $stmt->execute(['qid' => $currentQuestion['id'], 'correct' => $currentQuestion['correct_answer']]);
    $correctAnswerVotes = (int) $stmt->fetchColumn();

    // Добавляем правильный ответ в общий массив вариантов
    $answers[$currentQuestion['correct_answer']] = [
        'text' => $currentQuestion['correct_answer'],
        'is_correct' => true,
        'author' => null,
        'votes' => $correctAnswerVotes
    ];

    // Находим ответ, который текущий игрок ввел сам
    $userOwnAnswer = getPlayerOwnAnswer($currentQuestion['id'], $_SESSION['user_id']);

    // Запрашиваем ложные (фейковые) ответы, придуманные игроками, и число голосов за каждый из них
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

    // Добавляем фейковые ответы игроков в общий список
    foreach ($fakes as $fake) {
        $answers[$fake['answer_text']] = [
            'text' => $fake['answer_text'],
            'is_correct' => false,
            'author' => $fake['username'],
            'votes' => (int) $fake['votes']
        ];
    }

    // Проверяем, за какой именно вариант проголосовал текущий пользователь
    $stmt = $pdo->prepare('SELECT selected_answer_text FROM votes WHERE question_id = :qid AND voter_id = :uid');
    $stmt->execute(['qid' => $currentQuestion['id'], 'uid' => $_SESSION['user_id']]);
    $userVote = $stmt->fetchColumn();
}

// Декодируем массив ИИ-фейков из формата JSON
if ($currentQuestion && isset($currentQuestion['fake_answers'])) {
    if (is_string($currentQuestion['fake_answers'])) {
        $currentQuestion['fake_answers'] = json_decode($currentQuestion['fake_answers'], true);
    }
}

// Проверяем статус фоновой генерации вопроса ИИ через временный лок-файл.
// Если лок-файл существует и не устарел (>40 секунд), значит генерация ещё идёт.
$tempFile = sys_get_temp_dir() . '/quete_lobby_' . $lobbyId . '_gen.tmp';
$isGenerating = false;
if (file_exists($tempFile)) {
    if (time() - filemtime($tempFile) > 40) {
        @unlink($tempFile); // Удаляем зависший лок-файл
    } else {
        $isGenerating = true; // Блокировка активна
    }
}

// Возвращаем все собранные данные в формате JSON
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
    // В случае критического сбоя логируем ошибку и отдаем клиенту JSON с сообщением о сбое
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
