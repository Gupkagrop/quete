<?php
require_once __DIR__ . '/../config.php';

/**
 * @return PDO
 */
function getPDO()
{
    static $pdo;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', DB_HOST, DB_NAME, DB_CHARSET);

    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    } catch (PDOException $e) {
        // В продакшне лучше логировать, но не выводить на экран
        die('Ошибка подключения к БД: ' . $e->getMessage());
    }

    return $pdo;
}

/**
 * Генерирует и сохраняет CSRF-токен в сессии
 */
function generateCsrfToken()
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Получает текущий CSRF-токен из сессии
 */
function getCsrfToken()
{
    return $_SESSION['csrf_token'] ?? generateCsrfToken();
}

/**
 * Проверяет CSRF-токен
 */
function verifyCsrfToken($token)
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    return !empty($token) && hash_equals($_SESSION['csrf_token'] ?? '', $token);
}

function getUserById($userId)
{
    if (!$userId) {
        return null;
    }

    $pdo = getPDO();
    $stmt = $pdo->prepare('SELECT id, username, email, wins_count, total_answers, correct_answers, last_game_score FROM users WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $userId]);
    return $stmt->fetch();
}

function getUserByEmail($email)
{
    $pdo = getPDO();
    $stmt = $pdo->prepare('SELECT id, username, email, password_hash FROM users WHERE email = :email LIMIT 1');
    $stmt->execute(['email' => $email]);
    return $stmt->fetch();
}

function createUser($username, $email, $password)
{
    $pdo = getPDO();
    $stmt = $pdo->prepare('INSERT INTO users (username, email, password_hash) VALUES (:username, :email, :hash)');
    $stmt->execute([
        'username' => $username,
        'email' => $email,
        'hash' => password_hash($password, PASSWORD_DEFAULT),
    ]);

    return (int) $pdo->lastInsertId();
}

function emailExists($email)
{
    $pdo = getPDO();
    $stmt = $pdo->prepare('SELECT 1 FROM users WHERE email = :email LIMIT 1');
    $stmt->execute(['email' => $email]);
    return (bool) $stmt->fetchColumn();
}

function usernameExists($username)
{
    $pdo = getPDO();
    $stmt = $pdo->prepare('SELECT 1 FROM users WHERE username = :username LIMIT 1');
    $stmt->execute(['username' => $username]);
    return (bool) $stmt->fetchColumn();
}

function getUserByIdentity($identity)
{
    $pdo = getPDO();
    $stmt = $pdo->prepare('SELECT id, username, email, password_hash FROM users WHERE email = :email_identity OR username = :username_identity LIMIT 1');
    $stmt->execute([
        'email_identity' => $identity,
        'username_identity' => $identity
    ]);
    return $stmt->fetch();
}


// Функции для лобби
function createLobby($hostId, $name, $password, $maxPlayers, $fakeTime)
{
    $pdo = getPDO();
    $stmt = $pdo->prepare('INSERT INTO lobbies (host_id, lobby_name, password, max_players, fake_answer_time, responsible) VALUES (:hid, :name, :pass, :max, :time, :resp)');
    $stmt->execute([
        'hid' => $hostId,
        'name' => moderateChatMessage(strip_tags(trim($name))),
        'pass' => $password ?: null,
        'max' => $maxPlayers,
        'time' => $fakeTime,
        'resp' => $hostId, // По умолчанию responsible - хост
    ]);
    return (int) $pdo->lastInsertId();
}

/**
 * Очищает устаревшие лобби, которые были неактивны более 2 часов.
 * Безопасно удаляет само лобби и все связанные с ним каскадные данные в транзакции.
 *
 * @return int|bool Количество удаленных лобби или false в случае ошибки.
 */
function cleanupExpiredLobbies()
{
    $pdo = getPDO();

    try {
        // Убедимся, что колонка created_at существует в таблице lobbies.
        // Это необходимо для обратной совместимости с существующими БД без ручных миграций.
        $stmtCol = $pdo->query("SHOW COLUMNS FROM lobbies LIKE 'created_at'");
        if (!$stmtCol->fetch()) {
            $pdo->exec("ALTER TABLE lobbies ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
        }

        // Начинаем транзакцию для обеспечения целостности данных при удалении
        $pdo->beginTransaction();

        // Ищем все лобби, созданные более 2 часов назад, у которых не было
        // никакой активности за последние 2 часа (новых вопросов, чат-сообщений или ответов игроков).
        $query = "
            SELECT l.id 
            FROM lobbies l
            WHERE l.created_at < NOW() - INTERVAL 2 HOUR
              AND NOT EXISTS (
                  SELECT 1 FROM generated_questions gq 
                  WHERE gq.lobby_id = l.id AND gq.created_at >= NOW() - INTERVAL 2 HOUR
              )
              AND NOT EXISTS (
                  SELECT 1 FROM chat_messages cm 
                  WHERE cm.lobby_id = l.id AND cm.created_at >= NOW() - INTERVAL 2 HOUR
              )
              AND NOT EXISTS (
                  SELECT 1 FROM player_answers pa 
                  JOIN generated_questions gq ON pa.question_id = gq.id 
                  WHERE gq.lobby_id = l.id AND pa.created_at >= NOW() - INTERVAL 2 HOUR
              )
        ";
        
        $stmt = $pdo->query($query);
        $expiredLobbyIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

        if (!empty($expiredLobbyIds)) {
            // Для каждого просроченного лобби производим каскадную очистку
            foreach ($expiredLobbyIds as $lobbyId) {
                // 1. Удаляем голоса игроков (votes)
                $stmtVotes = $pdo->prepare('
                    DELETE v FROM votes v 
                    JOIN generated_questions gq ON v.question_id = gq.id 
                    WHERE gq.lobby_id = :lid
                ');
                $stmtVotes->execute(['lid' => $lobbyId]);

                // 2. Удаляем ответы игроков (player_answers)
                $stmtAnswers = $pdo->prepare('
                    DELETE pa FROM player_answers pa 
                    JOIN generated_questions gq ON pa.question_id = gq.id 
                    WHERE gq.lobby_id = :lid
                ');
                $stmtAnswers->execute(['lid' => $lobbyId]);

                // 3. Удаляем сгенерированные вопросы (generated_questions)
                $stmtQuestions = $pdo->prepare('DELETE FROM generated_questions WHERE lobby_id = :lid');
                $stmtQuestions->execute(['lid' => $lobbyId]);

                // 4. Удаляем сообщения чата (chat_messages)
                $stmtChat = $pdo->prepare('DELETE FROM chat_messages WHERE lobby_id = :lid');
                $stmtChat->execute(['lid' => $lobbyId]);

                // 5. Сбрасываем одноразовые билеты WebSocket у игроков удаляемого лобби для повышения безопасности
                $stmtClearTickets = $pdo->prepare('
                    UPDATE users u
                    JOIN lobby_players lp ON u.id = lp.user_id
                    SET u.ws_ticket = NULL
                    WHERE lp.lobby_id = :lid
                ');
                $stmtClearTickets->execute(['lid' => $lobbyId]);

                // 6. Удаляем игроков из лобби (lobby_players)
                $stmtPlayers = $pdo->prepare('DELETE FROM lobby_players WHERE lobby_id = :lid');
                $stmtPlayers->execute(['lid' => $lobbyId]);

                // 7. Удаляем само лобби (lobbies)
                $stmtLobby = $pdo->prepare('DELETE FROM lobbies WHERE id = :lid');
                $stmtLobby->execute(['lid' => $lobbyId]);
            }
        }

        // Подтверждаем транзакцию
        $pdo->commit();
        
        return count($expiredLobbyIds);
    } catch (Exception $e) {
        // Откат при возникновении сбоя
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        // Логируем ошибку, чтобы не мешать основному игровому процессу
        error_log('Ошибка при очистке устаревших лобби: ' . $e->getMessage());
        return false;
    }
}

function getLobbies()
{
    // Запускаем сборщик мусора неактивных лобби перед получением списка
    cleanupExpiredLobbies();

    $pdo = getPDO();
    // Возвращаем все лобби, сортируя: сначала те, куда можно войти (неактивные и незаполненные), а полные/активные - в конец
    $stmt = $pdo->query('SELECT l.id, l.lobby_name, l.max_players, l.is_active, COALESCE(lp.current_players, 0) as current_players, l.password IS NOT NULL as is_private FROM lobbies l LEFT JOIN (SELECT lobby_id, COUNT(*) as current_players FROM lobby_players GROUP BY lobby_id) lp ON l.id = lp.lobby_id ORDER BY (l.is_active = 0 AND COALESCE(lp.current_players, 0) < l.max_players) DESC, l.id DESC');
    return $stmt->fetchAll();
}

function getLobbyById($lobbyId)
{
    $pdo = getPDO();
    $stmt = $pdo->prepare('SELECT * FROM lobbies WHERE id = :id');
    $stmt->execute(['id' => $lobbyId]);
    return $stmt->fetch();
}

function getLobbyByUserId($userId)
{
    $pdo = getPDO();
    $stmt = $pdo->prepare('SELECT l.* FROM lobbies l JOIN lobby_players lp ON l.id = lp.lobby_id WHERE lp.user_id = :uid LIMIT 1');
    $stmt->execute(['uid' => $userId]);
    return $stmt->fetch();
}

function joinLobby($lobbyId, $userId)
{
    $pdo = getPDO();
    // Проверить, не в каком-либо лобби ли уже
    $stmt = $pdo->prepare('SELECT lobby_id FROM lobby_players WHERE user_id = :uid LIMIT 1');
    $stmt->execute(['uid' => $userId]);
    $existingLobbyId = $stmt->fetchColumn();
    if ($existingLobbyId) {
        return (int) $existingLobbyId === (int) $lobbyId;
    }

    // Проверить вместимость
    $stmt = $pdo->prepare('SELECT max_players FROM lobbies WHERE id = :id');
    $stmt->execute(['id' => $lobbyId]);
    $maxPlayers = $stmt->fetchColumn();

    $stmt = $pdo->prepare('SELECT COUNT(*) FROM lobby_players WHERE lobby_id = :id');
    $stmt->execute(['id' => $lobbyId]);
    $count = $stmt->fetchColumn();

    if ($count >= $maxPlayers) return false;

    $stmt = $pdo->prepare('SELECT host_id FROM lobbies WHERE id = :id');
    $stmt->execute(['id' => $lobbyId]);
    $hostId = $stmt->fetchColumn();
    $isHost = ($hostId == $userId);

    // Выбрать случайный свободный аватар (1-10)
    $stmt = $pdo->prepare('SELECT avatar_id FROM lobby_players WHERE lobby_id = :id');
    $stmt->execute(['id' => $lobbyId]);
    $usedAvatars = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $availableAvatars = array_diff(range(1, 10), $usedAvatars);
    
    if (empty($availableAvatars)) {
        $avatarId = rand(1, 10);
    } else {
        $avatarId = $availableAvatars[array_rand($availableAvatars)];
    }

    $stmt = $pdo->prepare('INSERT INTO lobby_players (lobby_id, user_id, is_ready, avatar_id) VALUES (:lid, :uid, :ready, :avatar)');
    $stmt->execute(['lid' => $lobbyId, 'uid' => $userId, 'ready' => $isHost ? 1 : 0, 'avatar' => $avatarId]);
    return true;
}

function leaveLobby($lobbyId, $userId)
{
    $pdo = getPDO();
    $stmt = $pdo->prepare('DELETE FROM lobby_players WHERE lobby_id = :lid AND user_id = :uid');
    $stmt->execute(['lid' => $lobbyId, 'uid' => $userId]);
}

function getLobbyPlayers($lobbyId)
{
    $pdo = getPDO();
    $stmt = $pdo->prepare('SELECT lp.user_id, u.username, lp.current_points, lp.is_ready, lp.avatar_id FROM lobby_players lp JOIN users u ON lp.user_id = u.id WHERE lp.lobby_id = :lid');
    $stmt->execute(['lid' => $lobbyId]);
    return $stmt->fetchAll();
}

function getPlayerScores($lobbyId)
{
    $pdo = getPDO();
    $stmt = $pdo->prepare('SELECT lp.user_id, u.username, lp.current_points FROM lobby_players lp JOIN users u ON lp.user_id = u.id WHERE lp.lobby_id = :lid ORDER BY lp.current_points DESC, u.username ASC');
    $stmt->execute(['lid' => $lobbyId]);
    return $stmt->fetchAll();
}

function startGame($lobbyId)
{
    $pdo = getPDO();
    setRandomResponsible($lobbyId);
    $stmt = $pdo->prepare('UPDATE lobbies SET is_active = 1 WHERE id = :id');
    $stmt->execute(['id' => $lobbyId]);
}

function setRandomResponsible($lobbyId)
{
    $pdo = getPDO();
    
    // Получить текущего ответственного (если есть)
    $stmt = $pdo->prepare('SELECT responsible FROM lobbies WHERE id = :id');
    $stmt->execute(['id' => $lobbyId]);
    $currentResp = $stmt->fetchColumn();

    $players = getLobbyPlayers($lobbyId);
    if (count($players) === 0) {
        return false;
    }

    // Если игроков больше одного, стараемся не выбирать того же самого
    if (count($players) > 1 && $currentResp) {
        $otherPlayers = array_filter($players, function($p) use ($currentResp) {
            return (int)$p['user_id'] !== (int)$currentResp;
        });
        if (!empty($otherPlayers)) {
            $randomPlayer = $otherPlayers[array_rand($otherPlayers)];
        } else {
            $randomPlayer = $players[array_rand($players)];
        }
    } else {
        $randomPlayer = $players[array_rand($players)];
    }

    setLobbyResponsible($lobbyId, $randomPlayer['user_id']);
    return true;
}

function updateLobby($lobbyId, $name, $password, $maxPlayers, $fakeTime)
{
    $pdo = getPDO();
    $stmt = $pdo->prepare('UPDATE lobbies SET lobby_name = :name, password = :pass, max_players = :max, fake_answer_time = :time WHERE id = :id');
    $stmt->execute([
        'id' => $lobbyId,
        'name' => moderateChatMessage(strip_tags(trim($name))),
        'pass' => $password ?: null,
        'max' => $maxPlayers,
        'time' => $fakeTime,
    ]);
}

function generateQuestion($lobbyId, $question, $correct, $fakes, $topic = 'Общее', $roundNumber = null, $questionNumber = 1, $isActive = true)
{
    $pdo = getPDO();
    if ($roundNumber === null) {
        $lobby = getLobbyById($lobbyId);
        $roundNumber = (int) ($lobby['current_round'] ?? 1);
    }

    $stmt = $pdo->prepare('INSERT INTO generated_questions (lobby_id, topic, question_text, correct_answer, fake_answers, round_number, question_number, is_active) VALUES (:lid, :t, :q, :c, :f, :round, :qnum, :active)');
    $stmt->execute([
        'lid' => $lobbyId,
        't' => strip_tags(trim($topic)),
        'q' => $question,
        'c' => $correct,
        'f' => json_encode($fakes),
        'round' => $roundNumber,
        'qnum' => $questionNumber,
        'active' => $isActive ? 1 : 0
    ]);
    return (int) $pdo->lastInsertId();
}

function getCurrentQuestion($lobbyId)
{
    $pdo = getPDO();
    $lobby = getLobbyById($lobbyId);
    if (!$lobby) {
        return null;
    }

    $stmt = $pdo->prepare('SELECT * FROM generated_questions WHERE lobby_id = :lid AND round_number = :round AND is_active = 1 ORDER BY id DESC LIMIT 1');
    $stmt->execute(['lid' => $lobbyId, 'round' => (int) $lobby['current_round']]);
    return $stmt->fetch();
}

function setPlayerReady($lobbyId, $userId, $isReady)
{
    $pdo = getPDO();
    $stmt = $pdo->prepare('UPDATE lobby_players SET is_ready = :ready WHERE lobby_id = :lid AND user_id = :uid');
    $stmt->execute(['ready' => $isReady ? 1 : 0, 'lid' => $lobbyId, 'uid' => $userId]);
}

function getLobbyResponsible($lobbyId)
{
    $pdo = getPDO();
    $stmt = $pdo->prepare('SELECT responsible FROM lobbies WHERE id = :id');
    $stmt->execute(['id' => $lobbyId]);
    return $stmt->fetchColumn();
}

function setLobbyResponsible($lobbyId, $userId)
{
    $pdo = getPDO();
    $stmt = $pdo->prepare('UPDATE lobbies SET responsible = :uid WHERE id = :id');
    $stmt->execute(['uid' => $userId, 'id' => $lobbyId]);
}

function areAllPlayersReady($lobbyId)
{
    $pdo = getPDO();
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM lobby_players WHERE lobby_id = :lid AND is_ready = 0');
    $stmt->execute(['lid' => $lobbyId]);
    $notReadyCount = (int) $stmt->fetchColumn();
    return $notReadyCount === 0;
}

function getPlayerCount($lobbyId)
{
    $pdo = getPDO();
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM lobby_players WHERE lobby_id = :lid');
    $stmt->execute(['lid' => $lobbyId]);
    return (int) $stmt->fetchColumn();
}

function submitFakeAnswer($questionId, $userId, $answer)
{
    $pdo = getPDO();
    $stmt = $pdo->prepare('INSERT INTO player_answers (question_id, user_id, answer_text) VALUES (:qid, :uid, :ans)');
    $stmt->execute(['qid' => $questionId, 'uid' => $userId, 'ans' => strip_tags(trim($answer))]);
}

function submitVote($questionId, $voterId, $answerText)
{
    $pdo = getPDO();
    $stmt = $pdo->prepare('INSERT INTO votes (question_id, voter_id, selected_answer_text) VALUES (:qid, :vid, :ans)');
    $stmt->execute(['qid' => $questionId, 'vid' => $voterId, 'ans' => $answerText]);
}

function getVotesForQuestion($questionId)
{
    $pdo = getPDO();
    $stmt = $pdo->prepare('SELECT selected_answer_text, COUNT(*) as count FROM votes WHERE question_id = :qid GROUP BY selected_answer_text');
    $stmt->execute(['qid' => $questionId]);
    return $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
}

function updatePlayerScore($lobbyId, $userId, $points)
{
    $pdo = getPDO();
    $stmt = $pdo->prepare('UPDATE lobby_players SET current_points = current_points + :pts WHERE lobby_id = :lid AND user_id = :uid');
    $stmt->execute(['pts' => $points, 'lid' => $lobbyId, 'uid' => $userId]);
}

function updateLobbyRound($lobbyId, $round)
{
    $pdo = getPDO();
    $stmt = $pdo->prepare('UPDATE lobbies SET current_round = :round WHERE id = :id');
    $stmt->execute(['round' => $round, 'id' => $lobbyId]);
}

function deleteLobby($lobbyId)
{
    $pdo = getPDO();
    // Удалить все голоса за вопросы в этом лобби
    $stmt = $pdo->prepare('DELETE v FROM votes v JOIN generated_questions gq ON v.question_id = gq.id WHERE gq.lobby_id = :lid');
    $stmt->execute(['lid' => $lobbyId]);
    
    // Удалить все ответы игроков за вопросы в этом лобби
    $stmt = $pdo->prepare('DELETE pa FROM player_answers pa JOIN generated_questions gq ON pa.question_id = gq.id WHERE gq.lobby_id = :lid');
    $stmt->execute(['lid' => $lobbyId]);
    
    // Удалить все вопросы в лобби
    $stmt = $pdo->prepare('DELETE FROM generated_questions WHERE lobby_id = :lid');
    $stmt->execute(['lid' => $lobbyId]);
    
    // Удалить всех игроков в лобби
    $stmt = $pdo->prepare('DELETE FROM lobby_players WHERE lobby_id = :lid');
    $stmt->execute(['lid' => $lobbyId]);
    
    // Удалить само лобби
    $stmt = $pdo->prepare('DELETE FROM lobbies WHERE id = :lid');
    $stmt->execute(['lid' => $lobbyId]);
}



function getLobbiesForUser($userId)
{
    $pdo = getPDO();
    $stmt = $pdo->prepare('SELECT l.id FROM lobbies l JOIN lobby_players lp ON l.id = lp.lobby_id WHERE lp.user_id = :uid');
    $stmt->execute(['uid' => $userId]);
    return $stmt->fetchAll();
}

/**
 * Завершить игру и сохранить статистику
 */
function finishGame($lobbyId, $finalScores)
{
    $pdo = getPDO();

    // Получить информацию о лобби
    $lobby = getLobbyById($lobbyId);
    if (!$lobby) return false;

    // Получить всех игроков
    $players = getLobbyPlayers($lobbyId);

    // Определить победителя (первое место)
    $winnerId = null;
    $maxScore = -1;
    foreach ($finalScores as $score) {
        if ($score['current_points'] > $maxScore) {
            $maxScore = $score['current_points'];
            $winnerId = $score['user_id'];
        }
    }

    // Обновить статистику для каждого игрока
    foreach ($finalScores as $playerScore) {
        $userId = $playerScore['user_id'];
        $points = $playerScore['current_points'];

        $user = getUserById($userId);

        // Обновить статистику
        $winWasAdded = 0;
        if ($userId === $winnerId) {
            $winWasAdded = 1;
        }

        // Получить количество правильных ответов игрока
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM votes v ' .
            'JOIN generated_questions gq ON v.question_id = gq.id ' .
            'WHERE gq.lobby_id = :lid AND v.voter_id = :uid AND LOWER(TRIM(v.selected_answer_text)) = LOWER(TRIM(gq.correct_answer))'
        );
        $stmt->execute(['lid' => $lobbyId, 'uid' => $userId]);
        $correctAnswers = (int) $stmt->fetchColumn();

        // Получить общее количество вопросов в этой игре
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM generated_questions WHERE lobby_id = :lid');
        $stmt->execute(['lid' => $lobbyId]);
        $totalQuestionsInGame = (int) $stmt->fetchColumn();

        // Обновить таблицу пользователей
        $stmt = $pdo->prepare(
            'UPDATE users ' .
            'SET wins_count = wins_count + :wins, ' .
            '    total_answers = total_answers + :total, ' .
            '    correct_answers = correct_answers + :correct, ' .
            '    last_game_score = :score ' .
            'WHERE id = :uid'
        );
        $stmt->execute([
            'wins' => $winWasAdded,
            'total' => $totalQuestionsInGame,
            'correct' => $correctAnswers,
            'score' => $points,
            'uid' => $userId,
        ]);
    }

    // Деактивировать лобби
    $stmt = $pdo->prepare('UPDATE lobbies SET is_active = FALSE WHERE id = :lid');
    $stmt->execute(['lid' => $lobbyId]);

    return true;
}

/**
 * Получить вопросы раунда с полной информацией
 */
function getRoundQuestions($lobbyId, $round)
{
    $pdo = getPDO();
    $stmt = $pdo->prepare(
        'SELECT gq.*, COUNT(v.id) as vote_count ' .
        'FROM generated_questions gq ' .
        'LEFT JOIN votes v ON gq.id = v.question_id ' .
        'WHERE gq.lobby_id = :lid AND gq.round_number = :round ' .
        'GROUP BY gq.id ' .
        'ORDER BY gq.id DESC'
    );
    $stmt->execute(['lid' => $lobbyId, 'round' => $round]);
    return $stmt->fetchAll();
}

function getPlayerAnswersForQuestion($questionId)
{
    $pdo = getPDO();
    $stmt = $pdo->prepare('SELECT user_id, answer_text FROM player_answers WHERE question_id = ?');
    $stmt->execute([$questionId]);
    $result = [];
    foreach ($stmt->fetchAll() as $row) {
        $result[$row['user_id']] = $row['answer_text'];
    }
    return $result;
}

/**
 * ========== ФУНКЦИИ ДЛЯ ОБРАБОТКИ ТАЙМАУТОВ ==========
 */

/**
 * Получить время, прошедшее с создания вопроса (в секундах)
 */
function getQuestionElapsedTime($questionId)
{
    $pdo = getPDO();
    $stmt = $pdo->prepare('SELECT UNIX_TIMESTAMP(NOW()) - UNIX_TIMESTAMP(created_at) as elapsed FROM generated_questions WHERE id = :qid');
    $stmt->execute(['qid' => $questionId]);
    $row = $stmt->fetch();
    return $row ? (int)$row['elapsed'] : 0;
}

/**
 * Проверить, превышен ли таймаут для выбора темы
 * Вовращает true, если время вышло и тема не была выбрана ИИ
 */
function isTopicSelectionTimedOut($questionId, $timeoutSeconds)
{
    if (getQuestionElapsedTime($questionId) >= $timeoutSeconds) {
        $pdo = getPDO();
        $stmt = $pdo->prepare('SELECT auto_topic_selected FROM generated_questions WHERE id = :qid');
        $stmt->execute(['qid' => $questionId]);
        $row = $stmt->fetch();
        return $row && !$row['auto_topic_selected'];
    }
    return false;
}

/**
 * Автоматически выбрать случайную тему для игры
 */
function autoSelectTopicForQuestion($questionId)
{
    $topics = ['История', 'Наука', 'Спорт', 'Искусство', 'Литература', 'География', 'Музыка', 'Кино', 'Животные', 'Технология'];
    $randomTopic = $topics[array_rand($topics)];
    
    $pdo = getPDO();
    $stmt = $pdo->prepare('UPDATE generated_questions SET auto_topic_selected = 1, topic = :topic WHERE id = :qid');
    $stmt->execute(['topic' => $randomTopic, 'qid' => $questionId]);
    
    return $randomTopic;
}

/**
 * Получить количество игроков, которые не отправили фейк для вопроса
 */
function getPlayersWithoutFakeAnswer($questionId, $lobbyId)
{
    $pdo = getPDO();
    
    // Получить всех игроков в лобби
    $stmt = $pdo->prepare('SELECT user_id FROM lobby_players WHERE lobby_id = :lid');
    $stmt->execute(['lid' => $lobbyId]);
    $allPlayers = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Получить игроков, отправивших ответ
    $stmt = $pdo->prepare('SELECT DISTINCT user_id FROM player_answers WHERE question_id = :qid');
    $stmt->execute(['qid' => $questionId]);
    $playersWithAnswers = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Вернуть разницу
    $playersWithoutAnswers = array_diff($allPlayers, $playersWithAnswers);
    return array_values($playersWithoutAnswers);
}

/**
 * Автоматически выбрать случайный фейк из 10 сгенерированных для игрока, который не успел отправить
 */
function autoSelectFakeAnswerForPlayer($questionId, $userId)
{
    $pdo = getPDO();
    
    // Получить вопрос с фейками
    $stmt = $pdo->prepare('SELECT fake_answers FROM generated_questions WHERE id = :qid');
    $stmt->execute(['qid' => $questionId]);
    $row = $stmt->fetch();
    
    if (!$row) return false;
    
    $fakes = json_decode($row['fake_answers'], true);
    if (!is_array($fakes) || empty($fakes)) return false;
    
    // Выбрать случайный фейк
    $randomFake = $fakes[array_rand($fakes)];
    
    // Проверить, нет ли уже ответа от этого игрока
    $stmt = $pdo->prepare('SELECT id FROM player_answers WHERE question_id = :qid AND user_id = :uid LIMIT 1');
    $stmt->execute(['qid' => $questionId, 'uid' => $userId]);
    if ($stmt->fetch()) {
        // Уже есть ответ, не добавляем
        return true;
    }
    
    // Добавить автоматически выбранный ответ
    $stmt = $pdo->prepare('INSERT INTO player_answers (question_id, user_id, answer_text, is_auto_selected) VALUES (:qid, :uid, :ans, 1)');
    $stmt->execute(['qid' => $questionId, 'uid' => $userId, 'ans' => $randomFake]);
    
    return true;
}

/**
 * Проверить, является ли ответ своим ответом игрока
 * Возвращает true если игрок голосует за свой собственный ответ
 */
function isVoteForOwnAnswer($questionId, $voterId, $answerText)
{
    $pdo = getPDO();
    $stmt = $pdo->prepare('SELECT 1 FROM player_answers WHERE question_id = :qid AND user_id = :uid AND answer_text = :ans LIMIT 1');
    $stmt->execute(['qid' => $questionId, 'uid' => $voterId, 'ans' => $answerText]);
    return (bool)$stmt->fetch();
}

/**
 * Получить собственный ответ игрока для вопроса (если есть)
 */
function getPlayerOwnAnswer($questionId, $userId)
{
    $pdo = getPDO();
    $stmt = $pdo->prepare('SELECT answer_text FROM player_answers WHERE question_id = :qid AND user_id = :uid LIMIT 1');
    $stmt->execute(['qid' => $questionId, 'uid' => $userId]);
    $row = $stmt->fetch();
    return $row ? $row['answer_text'] : null;
}

/**
 * Базовая модерация текста сообщений (фильтрация нецензурной лексики и потенциально противоправных слов)
 * для обеспечения соответствия законодательству РФ (ФЗ-149, ФЗ-436).
 * Заменяет нецензурные слова на забавные ретро-геймерские эвфемизмы или метки цензуры.
 *
 * @param string $message Исходный текст сообщения
 * @return string Отмодерированный текст
 */
function moderateChatMessage($message)
{
    if (empty($message)) {
        return $message;
    }

    // Список регулярных выражений для поиска обсценной лексики (русский мат с вариациями и приставками)
    // Используем флаг /ui для регистронезависимого поиска в UTF-8.
    // Паттерны защищены от Leetspeak обходов с использованием визуально похожих латинских букв или цифр.
    $badPatterns = [
        // Корень "хуй" с приставками (нахуй, похуй, охуеть, захуярить и т.д.)
        // Исключает ложные срабатывания на "сухую", "психую", "глухую".
        '/\b(?:[нh][аa]|[пp][оo]|[дd][оo]|[з3z][аa]|[оo]|[оo][б6b]|[вv]ы|[пp][еe][рp][еe]|[пp][рp][иi]|[нh][иi]|[сc]|[сc][оo]|[уy]|[вv]|[пp][оo][дd]|[рp][аa][з3z]|[оo][тt])?[хx][уy](?:[ййеeёёяяююиiоoаa]\w*|\b)/ui',
        '/\b[хx][уy][лl]и\b/ui',
        
        // Корень "пизд" с любыми приставками (пизда, пиздец, спиздить, запиздячить и т.д.)
        // Буква П может быть заменена на латинскую p или n. Буква И на i, u, y, o. З на z, 3, з. Д на d, д, g.
        '/\b\w*[пnрp][иiоoуyеeёё][з3z][дdтt]\w*/ui',
        
        // Корень "еб" / "ёб" с приставками (выебать, съебаться, заебать, долбоеб и т.д.)
        // Исключает ложные срабатывания на "себя", "тебя", "хлеб", "стебель", "гребля".
        '/\b(?:[рp][аa][з3z]|[рp][аa][з3z]ъ|[сc]ъ|[оo][б6b]|[оo][б6b]ъ|[вv]ы|[пp][оo]|[з3z][аa]|[уy]|[пp][рp][иi]|[пp][рp][оo]|[сc][оo]|[оo][тt]|[пp][оo][дd]|[пp][оo][дd]ъ|[нh][аa]|[пp][еe][рp][еe]|[дd][оo][лl][б6b][оo]|[пp][оo][лl][уy])?[еeёё][б6b](?:[аaлlуyтtьнhгgсcяяиiеeёёоo]\w*|\b)/ui',
        
        // Корень "бля" (блядь, блядство, бля и т.д.)
        // Исключает ложные срабатывания на "сабля", "влюбляться", "рублями".
        '/\b(?:[пp][оo][дd]|[пp][рp][иi]|[вv]ы)?[б6b][лl]я(?:[дd](?:[ьствд]\w*)?|\b)/ui',
        
        // Оскорбительные слова
        '/\b[мm][уy][дd][аa][кk]\w*/ui',
        '/\b[гg][оо][вv][нh][оо]\w*/ui',
        '/\b[сc][уy][кk][аио]\b/ui',
        '/\b[гg][аa][нh][дd][оo][нh]\w*/ui',
        '/\b[гg][оo][нh][дd][оo][нh]\w*/ui',
        '/\b[шsh][лl][юy][хx][аиуео]\w*/ui',
        '/\b[пp][рp][оo][сc][тt][иi][тt][уy][тt][кk]\w*/ui',
        '/\b[пp][еe][дd][оo][фf][иi][лl]\w*/ui',
        '/\b[уy][б6b][лl][юy][дd]\w*/ui',
        '/\b[чch][мm][оo]\w*/ui',
        
        // Потенциально противоправный контекст (наркотики, экстремизм, суицид)
        '/\b[гg][еe][рp][оo][иi][нh]\w*/ui',
        '/\b[кk][оo][кk][аa][иi][нh]\w*/ui',
        '/\b[мm][еe][фf](?:[аa]|[уy]|[оo][мm]|[еe])?\b/ui',
        '/\b[мm][еe][фf][еe][дd][рp][оo][нh]\w*/ui',
        '/\b[аa][мm][фf][еe][тt][аa][мm][иi][нh]\w*/ui',
        '/\b[мm][еe][тt][аa][мm][фf][еe][тt][аa][мm][иi][нh]\w*/ui',
        '/\b[эe][кk][сc][тt][аa][з3z][иi]\b/ui',
        '/\b[m][d][m][a]\b/ui',
        '/\b[лl][сc][дd]\b/ui',
        '/\b[мm][аa][рp][иi][хx][уy][аa][нh]\w*/ui',
        '/\b[гg][аa][шsh][иi][шsh]\w*/ui',
        '/\b[аa][нh][аa][шsh]\w*/ui',
        '/\b[сc][пp][аa][йй][сc]\w*/ui',
        '/\b[дd][еe][з3z][оo][мm][оo][рp][фf][иi][нh]\w*/ui',
        '/\b[нh][аa][рp][кk][оo][тt][иi][кk]\w*/ui',
        '/\b[нh][аa][рp][кk][оo][тt]\w*/ui',
        
        '/\b[иi][гg][иi][лl]\b/ui',
        '/\b[i][s][i][s]\b/ui',
        '/\b[тt][аa][лl][иi][б6b][аa][нh]\w*/ui',
        '/\b[аa][лl][ьb]-[кk][аa][иi][дd]\w*/ui',
        '/\b[тt][еe][рp][рp][оo][рp]\w*/ui',
        '/\b[эe][кk][сc][тt][рp][еe][мm][иi][з3z][мm]\w*/ui',
        '/\b[эe][кk][сc][тt][рp][еe][мm][иi][сc][t]\w*/ui',
        
        '/\b[хx][оo][хx][лl][ыи]\b/ui',
        '/\b[хx][оo][хx][оo][лl]\b/ui',
        '/\b[уy][кk][рp][оo][пp][ыи]\b/ui',
        '/\b[чch][уy][рp][кk]\w*/ui',
        '/\b[хx][аa][чc]\w*/ui',
        '/\b[жj][иi][дd][оoв]\w*/ui',
        
        '/\b[сc][уy][иi][цc][иi][дd]\w*/ui',
        '/\b[сc][аa][мm][оo][уy][б6b][иi][йй][сc][тt][вv]\w*/ui',
        '/\b[сc][аa][мm][оo][уy][б6b][иi][йй][цc]\w*/ui',
        '/\b[пp][оo][вv][еe][сc][ьb][сc][яя]\b/ui',
        '/\b[вv][сc][кk][рp][оo][йй]\s+[вv][еe][нh][ыи]\b/ui',
        '/\b[пp][оo][рp][еe][жj][ьb]\s+[вv][еe][нh][ыи]\b/ui',
    ];

    // Набор забавных геймерских эвфемизмов для замены мата в стиле ретро-игр
    $replacements = [
        '[ой!]',
        '*бип*',
        '[цензура]',
        '[капец]',
        '[ёшкин кот]',
        '[ололо]',
        '[милота]',
        '[хм...]',
        '[дружище]',
        '[нехорошее слово]',
        '[упс!]'
    ];

    // Выполняем модерацию по паттернам: заменяем две последние буквы на звёздочки
    foreach ($badPatterns as $pattern) {
        $message = preg_replace_callback($pattern, function($matches) {
            $word = $matches[0];
            $length = mb_strlen($word, 'UTF-8');
            if ($length <= 2) {
                return str_repeat('*', $length);
            } else {
                return mb_substr($word, 0, $length - 2, 'UTF-8') . '**';
            }
        }, $message);
    }

    return $message;
}

/**
 * Сохранить сообщение чата в БД
 */
function addChatMessage($lobbyId, $userId, $message)
{
    try {
        $pdo = getPDO();
        $stmt = $pdo->prepare('INSERT INTO chat_messages (lobby_id, user_id, message) VALUES (:lid, :uid, :msg)');
        $stmt->execute(['lid' => $lobbyId, 'uid' => $userId, 'msg' => $message]);
        return true;
    } catch (PDOException $e) {
        // Если таблицы нет, просто игнорируем
        return false;
    }
}

/**
 * Получить сообщения чата из БД
 */
function getChatMessages($lobbyId, $limit = 50)
{
    try {
        $pdo = getPDO();
        $stmt = $pdo->prepare('
            SELECT cm.*, u.username 
            FROM chat_messages cm 
            JOIN users u ON cm.user_id = u.id 
            WHERE cm.lobby_id = :lid 
            ORDER BY cm.id DESC 
            LIMIT :limit
        ');
        $stmt->bindValue(':lid', $lobbyId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return array_reverse($stmt->fetchAll());
    } catch (PDOException $e) {
        // Если таблицы нет, возвращаем пустой массив
        return [];
    }
}
/**
 * Проверить, запущен ли WebSocket сервер
 */
function isWebSocketServerRunning()
{
    // Проверяем локальный адрес и порт, на котором действительно слушает WebSocket сервер
    $host = (WS_HOST === '0.0.0.0' || WS_HOST === '') ? '127.0.0.1' : WS_HOST;
    $port = WS_PORT;
    $connection = @fsockopen($host, $port, $errno, $errstr, 1);
    if (is_resource($connection)) {
        fclose($connection);
        return true;
    }
    return false;
}
/**
 * Получить тексты всех вопросов, заданных в лобби
 */
function getPreviousQuestionTexts($lobbyId)
{
    $pdo = getPDO();
    $stmt = $pdo->prepare('SELECT question_text FROM generated_questions WHERE lobby_id = :lid');
    $stmt->execute(['lid' => $lobbyId]);
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

/**
 * Генерирует одноразовый вебсокет-билет для пользователя
 */
function generateWebSocketTicket($userId)
{
    $ticket = bin2hex(random_bytes(32));
    $pdo = getPDO();
    $stmt = $pdo->prepare('UPDATE users SET ws_ticket = :ticket WHERE id = :uid');
    $stmt->execute(['ticket' => $ticket, 'uid' => $userId]);
    return $ticket;
}

/**
 * Проверяет вебсокет-билет и возвращает user_id, если он валиден, иначе null
 */
function verifyWebSocketTicket($ticket)
{
    if (empty($ticket)) {
        return null;
    }
    $pdo = getPDO();
    $stmt = $pdo->prepare('SELECT id FROM users WHERE ws_ticket = :ticket LIMIT 1');
    $stmt->execute(['ticket' => $ticket]);
    $userId = $stmt->fetchColumn();
    if ($userId) {
        // Чтобы позволить WebSocket-клиенту автоматически переподключаться при кратковременном разрыве связи
        // (например, на мобильных устройствах), мы не сбрасываем билет в NULL сразу после первого подключения.
        // Он в любом случае перезапишется новым уникальным токеном при обновлении страницы или переходе в другую игру.
        // $stmt2 = $pdo->prepare('UPDATE users SET ws_ticket = NULL WHERE id = :uid');
        // $stmt2->execute(['uid' => $userId]);
        return (int) $userId;
    }
    return null;
}
