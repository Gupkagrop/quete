<?php
/**
 * AJAX-скрипт выбора темы раунда. Отправляет тему ИИ-модели для создания первого вопроса.
 */

session_start();
require_once '../core/db.php';
require_once '../core/ai_handler.php';

header('Content-Type: application/json');

// Проверяем авторизацию пользователя
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
// Защита от подделки межсайтовых запросов
$csrfToken = $data['csrf_token'] ?? '';
if (!verifyCsrfToken($csrfToken)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
    exit;
}

// Проверяем, существует ли комната и имеет ли право данный игрок выбирать тему (является ли ответственным)
$lobby = getLobbyById($lobbyId);
if (!$lobby || $lobby['responsible'] != $_SESSION['user_id']) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Only responsible can select topic']);
    exit;
}

// Создаем временный лок-файл (файл-блокировку), чтобы показать остальным игрокам, что ИИ сейчас генерирует вопрос.
// Это предотвратит отправку повторных запросов, пока идет сетевое ожидание ответа от Groq API.
$tempFile = sys_get_temp_dir() . '/quete_lobby_' . $lobbyId . '_gen.tmp';
file_put_contents($tempFile, time());

$pdo = getPDO();
try {
    // Начинаем транзакцию для обеспечения целостности данных
    $pdo->beginTransaction();

    // Блокируем строку лобби от изменений другими параллельными процессами
    $stmt = $pdo->prepare('SELECT current_round FROM lobbies WHERE id = :lid FOR UPDATE');
    $stmt->execute(['lid' => $lobbyId]);
    $lockedLobby = $stmt->fetch();

    if (!$lockedLobby) {
        throw new Exception('Lobby not found during lock');
    }

    // Проверяем, не был ли уже выбран вопрос для этого раунда (защита от двойного клика)
    $stmt = $pdo->prepare('SELECT id FROM generated_questions WHERE lobby_id = :lid AND round_number = :round AND question_number = 1');
    $stmt->execute(['lid' => $lobbyId, 'round' => (int) $lockedLobby['current_round']]);
    if ($stmt->fetch()) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'error' => 'Topic already selected for this round']);
        exit;
    }

    // Получаем тексты ранее заданных вопросов в этой комнате, чтобы ИИ не сгенерировал повторы
    $previousQuestions = getPreviousQuestionTexts($lobbyId);

    // Генерируем вопрос через Groq API на основе выбранной темы
    try {
        $questionData = generateQuestionWithGroq($topic, $previousQuestions);
        if (!$questionData['valid']) {
            $questionData = generateQuestionStub($topic); // Если ответ ИИ невалидный, подставляем готовую оффлайн-заглушку
            $questionData['valid'] = true;
        }
    } catch (Exception $e) {
        // Если API недоступно или произошла ошибка сети — переключаемся на оффлайн-заглушку
        $questionData = generateQuestionStub($topic);
        $questionData['valid'] = true;
    }

    // Сохраняем сгенерированный вопрос в базу данных как первый вопрос в этом раунде
    $questionId = generateQuestion(
        $lobbyId,
        $questionData['question'],
        $questionData['correct'],
        $questionData['fakes'],
        $topic,
        (int) $lockedLobby['current_round'],
        1  // question_number = 1 (первый вопрос раунда)
    );

    // Подтверждаем транзакцию в БД
    $pdo->commit();
    echo json_encode(['success' => true, 'question_id' => $questionId]);
} catch (Exception $e) {
    // В случае ошибок откатываем все изменения
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Internal server error']);
} finally {
    // В любом случае (даже при ошибке) удаляем лок-файл генерации
    @unlink($tempFile);
}
?>