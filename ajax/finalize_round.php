<?php
/**
 * AJAX-скрипт для перехода к следующему вопросу, раунду или завершения игры.
 * Проверяет результаты текущего этапа и при необходимости запрашивает у ИИ новый вопрос.
 */

session_start();
require_once '../core/db.php';
require_once '../core/ai_handler.php';

// Указываем браузеру, что сервер вернет ответ в формате JSON (структурированный текст)
header('Content-Type: application/json');

// Проверяем, авторизован ли пользователь (есть ли его ID в сессии)
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

// Получаем входные данные, отправленные в формате JSON (например, через fetch в JS)
$data = json_decode(file_get_contents('php://input'), true);
$lobbyId = (int) ($data['lobby_id'] ?? 0);

if (!$lobbyId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing lobby_id']);
    exit;
}

// === ПРОВЕРКА CSRF ===
// Защита от межсайтовой подделки запросов (проверяем специальный секретный токен пользователя)
$csrfToken = $data['csrf_token'] ?? '';
if (!verifyCsrfToken($csrfToken)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
    exit;
}

// Загружаем информацию о комнате (лобби) из базы данных
$lobby = getLobbyById($lobbyId);
if (!$lobby) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Lobby not found']);
    exit;
}

// Проверяем, находится ли текущий пользователь в составе этой комнаты
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
// Только создатель лобби (хост) имеет право переключать раунды и этапы игры для всех участников
if ((int)$lobby['host_id'] !== (int)$_SESSION['user_id']) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Only host can finalize round']);
    exit;
}

$pdo = getPDO();

// Запрашиваем из базы данных АКТИВНЫЙ (текущий) вопрос в этой комнате
$stmt = $pdo->prepare('SELECT * FROM generated_questions WHERE lobby_id = :lid AND is_active = 1');
$stmt->execute(['lid' => $lobbyId]);
$currentQuestion = $stmt->fetch();

if (!$currentQuestion) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'No active question']);
    exit;
}

// Получаем множитель очков для текущего раунда (в 1 раунде х1, во 2 — х2, в 3 — х3)
$multiplier = ROUND_MULTIPLIERS[(int) $lobby['current_round'] - 1] ?? 1;

try {
    // Начинаем транзакцию базы данных, чтобы изменения применились атомарно (все или ничего)
    $pdo->beginTransaction();
    
    // Блокируем запись комнаты от одновременных изменений другими процессами (защита от "гонки запросов")
    $stmt = $pdo->prepare('SELECT * FROM lobbies WHERE id = :lid FOR UPDATE');
    $stmt->execute(['lid' => $lobbyId]);
    $lockedLobby = $stmt->fetch();

    // Если игра уже неактивна (завершена), выходим
    if (!$lockedLobby || !$lockedLobby['is_active']) {
        $pdo->rollBack();
        echo json_encode(['status' => 'already_finished', 'message' => 'Lobby is inactive']);
        exit;
    }

    // Подсчитываем, сколько всего вопросов было сгенерировано в текущем раунде
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM generated_questions WHERE lobby_id = :lid AND round_number = :round');
    $stmt->execute(['lid' => $lobbyId, 'round' => (int) $lockedLobby['current_round']]);
    $totalQuestionsInRound = (int) $stmt->fetchColumn();

    // Получаем текущую таблицу очков игроков
    $scores = getPlayerScores($lobbyId);

    // В каждом раунде должно быть сыграно ровно 3 вопроса
    if ($totalQuestionsInRound < 3) {
        // Если кто-то пытается отправить запрос повторно, когда вопрос уже сгенерирован
        if ($totalQuestionsInRound > $currentQuestion['question_number']) {
            $pdo->rollBack();
            echo json_encode(['status' => 'already_finished']);
            exit;
        }

        // Вставляем временную запись-заглушку для следующего вопроса, чтобы другие запросы хоста не дублировали генерацию
        $nextQuestionId = generateQuestion(
            $lobbyId,
            'GENERATING_NEXT_QUESTION',
            '',
            [],
            $currentQuestion['topic'],
            (int) $lockedLobby['current_round'],
            $totalQuestionsInRound + 1,
            false // Вопрос пока неактивен, игроки его не увидят
        );

        $pdo->commit(); // Подтверждаем создание заглушки, отпуская блокировку строки

        // Генерируем следующий вопрос через искусственный интеллект (ИИ) Groq на основе темы раунда
        $topic = $currentQuestion['topic'];
        $previousQuestions = getPreviousQuestionTexts($lobbyId); // Получаем список уже заданных вопросов, чтобы не повторяться
        
        try {
            $questionData = generateQuestionWithGroq($topic, $previousQuestions);
            if (!$questionData['valid']) {
                $questionData = generateQuestionStub($topic); // Если ИИ вернул некорректные данные, берем оффлайн-заглушку
                $questionData['valid'] = true;
            }
        } catch (Exception $e) {
            $questionData = generateQuestionStub($topic); // Если возникла сетевая ошибка Groq, берем оффлайн-заглушку
            $questionData['valid'] = true;
        }

        // Заполняем временную запись-заглушку реальным сгенерированным вопросом и фальшивыми ответами
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
        // Если 3 вопроса в текущем раунде уже сыграны, переходим к следующему раунду
        if ($lockedLobby['current_round'] > $lobby['current_round']) {
             $pdo->rollBack();
             echo json_encode(['status' => 'already_finished']);
             exit;
        }

        $nextRound = (int) $lockedLobby['current_round'] + 1;
        
        // Если следующий раунд укладывается в лимит (всего раундов — ROUNDS_COUNT, т.е. 3)
        if ($nextRound <= ROUNDS_COUNT) {
            updateLobbyRound($lobbyId, $nextRound); // Обновляем номер раунда в базе
            setRandomResponsible($lobbyId); // Выбираем случайного игрока, который будет выбирать тему для нового раунда
            $pdo->commit();
            
            echo json_encode([
                'status' => 'next_round',
                'currentRound' => (int) $lockedLobby['current_round'],
                'nextRound' => $nextRound,
                'scores' => $scores
            ]);
        } else {
            // Если все 3 раунда сыграны, официально завершаем игру
            finishGame($lobbyId, $scores); // Рассчитываем победителя, обновляем глобальную статистику побед
            $pdo->commit();
            
            echo json_encode([
                'status' => 'game_finished',
                'scores' => $scores
            ]);
        }
    }

} catch (Exception $e) {
    // В случае любой ошибки откатываем транзакцию в БД к исходному состоянию
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    exit;
}

