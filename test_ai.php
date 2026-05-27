<?php
/**
 * Диагностический стенд администратора для тестирования интеграции с Groq AI.
 */

session_start();
require_once __DIR__ . '/config.php';

// Блок проверки прав: доступ на стенд разрешен только вошедшему пользователю с именем "admin"
if (!isset($_SESSION['user_id']) || $_SESSION['username'] !== 'admin') {
    header('HTTP/1.1 403 Forbidden');
    ?>
    <!DOCTYPE html>
    <html lang="ru">
    <head>
        <meta charset="UTF-8">
        <title>403 Доступ запрещен</title>
        <style nonce="<?php echo CSP_NONCE; ?>">
            body { background: #1a1f3b; color: #fff; font-family: sans-serif; text-align: center; padding-top: 100px; }
            .card { background: #232b4b; border: 1px solid #e3342f; padding: 30px; display: inline-block; border-radius: 4px; }
            a { color: #ff7a00; text-decoration: none; font-weight: bold; }
        </style>
    </head>
    <body>
        <div class="card">
            <h1>Доступ ограничен</h1>
            <p>Эта страница доступна только администратору.</p>
            <p><a href="index.php">На главную</a></p>
        </div>
    </body>
    </html>
    <?php
    exit;
}

require_once __DIR__ . '/core/ai_handler.php';

$result = null;
$action = $_POST['action'] ?? '';

// Обработка отправки формы тестирования (выполнение пинга, валидации, генерации или сравнения)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if ($action === 'ping') {
            $prompt = "Respond with exactly one word: PONG";
            $start = microtime(true);
            $res = sendGroqRequest($prompt, ['temperature' => 0.1, 'max_tokens' => 10]);
            $latency = round(microtime(true) - $start, 3);
            if ($res['success']) {
                $result = "Успешный пинг! Ответ: " . htmlspecialchars($res['text']) . " (Задержка: {$latency}с)";
            } else {
                $result = "Ошибка пинга: " . htmlspecialchars($res['error']);
            }
        } elseif ($action === 'validate_topic') {
            $topic = trim($_POST['topic'] ?? '');
            if (empty($topic)) throw new Exception("Тема не указана");
            $res = validateTopicWithGroq($topic);
            $result = "Тема: <strong>" . htmlspecialchars($topic) . "</strong><br>"
                    . "Валидна: <strong>" . ($res['valid'] ? 'Да' : 'Нет') . "</strong><br>"
                    . "Причина/Комментарий: " . htmlspecialchars($res['reason'] ?? '-');
        } elseif ($action === 'generate') {
            $topic = trim($_POST['topic'] ?? '');
            if (empty($topic)) throw new Exception("Тема не указана");
            $res = generateQuestionWithGroq($topic);
            if (isset($res['valid']) && $res['valid']) {
                $result = "<strong>Тема:</strong> " . htmlspecialchars($topic) . "<br>"
                        . "<strong>Вопрос:</strong> " . htmlspecialchars($res['question']) . "<br>"
                        . "<strong>Правильный ответ:</strong> " . htmlspecialchars($res['correct']) . "<br>"
                        . "<strong>Фейки (альтернативы):</strong><ol style='margin: 5px 0 0 20px;'>";
                foreach ($res['fakes'] as $fake) {
                    $result .= "<li>" . htmlspecialchars($fake) . "</li>";
                }
                $result .= "</ol>";
            } else {
                $result = "Ошибка генерации: " . htmlspecialchars($res['error'] ?? 'Неизвестно');
            }
        } elseif ($action === 'compare') {
            $user = trim($_POST['user_answer'] ?? '');
            $correct = trim($_POST['correct_answer'] ?? '');
            if ($user === '' || $correct === '') throw new Exception("Заполните оба ответа");
            $isTooClose = isAnswerTooCloseToCorrect($user, $correct);
            similar_text(mb_strtolower($user), mb_strtolower($correct), $sim);
            $result = "Сравнение ответов:<br>"
                    . "Ответ игрока: <strong>" . htmlspecialchars($user) . "</strong><br>"
                    . "Правильный: <strong>" . htmlspecialchars($correct) . "</strong><br>"
                    . "Процент совпадения: <strong>" . round($sim, 2) . "%</strong><br>"
                    . "Слишком близко (запрещено): <strong>" . ($isTooClose ? 'Да' : 'Нет') . "</strong>";
        }
    } catch (Exception $e) {
        $result = "Ошибка: " . htmlspecialchars($e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Стенд Тестирования ИИ</title>
    <style nonce="<?php echo CSP_NONCE; ?>">
        body { font-family: sans-serif; background: #2f3640; color: #f5f6fa; margin: 20px; }
        .container { max-width: 800px; margin: 0 auto; background: #353b48; padding: 20px; border-radius: 6px; }
        h1, h2 { color: #f5f6fa; margin-top: 0; }
        .nav { margin-bottom: 20px; }
        .nav a { color: #00a8ff; text-decoration: none; margin-right: 15px; font-weight: bold; }
        .section { border: 1px solid #718093; padding: 15px; border-radius: 4px; margin-bottom: 15px; background: #2f3640; }
        .section h2 { margin-top: 0; color: #e1b12c; font-size: 18px; }
        input[type="text"] { width: 100%; padding: 8px; margin: 8px 0; border: 1px solid #718093; background: #353b48; color: #fff; border-radius: 4px; box-sizing: border-box; }
        .btn { background: #00a8ff; color: #fff; border: none; padding: 8px 16px; cursor: pointer; border-radius: 4px; font-weight: bold; }
        .result { background: #2f3640; border-left: 4px solid #e1b12c; padding: 15px; margin-bottom: 20px; font-size: 15px; border-radius: 0 4px 4px 0; }
    </style>
</head>
<body>
<div class="container">
    <h1>Тестирование Groq Llama</h1>
    <div class="nav">
        <a href="hub.php">В игровой хаб</a>
        <a href="admin.php">Панель администратора</a>
        <a href="logout.php">Выйти</a>
    </div>

    <?php if ($result !== null): ?>
        <div class="result">
            <h3 style="margin-top:0; color:#e1b12c;">Результат проверки:</h3>
            <div><?php echo $result; ?></div>
        </div>
    <?php endif; ?>

    <div class="section">
        <h2>1. Проверка API (Ping)</h2>
        <form method="POST">
            <input type="hidden" name="action" value="ping">
            <button type="submit" class="btn">Выполнить Ping</button>
        </form>
    </div>

    <div class="section">
        <h2>2. Валидация темы</h2>
        <form method="POST">
            <input type="hidden" name="action" value="validate_topic">
            <input type="text" name="topic" placeholder="Тема для валидации..." required>
            <button type="submit" class="btn">Проверить</button>
        </form>
    </div>

    <div class="section">
        <h2>3. Генерация вопроса</h2>
        <form method="POST">
            <input type="hidden" name="action" value="generate">
            <input type="text" name="topic" placeholder="Тема для генерации..." required>
            <button type="submit" class="btn">Сгенерировать</button>
        </form>
    </div>

    <div class="section">
        <h2>4. Близость ответов</h2>
        <form method="POST">
            <input type="hidden" name="action" value="compare">
            <input type="text" name="user_answer" placeholder="Ответ игрока..." required>
            <input type="text" name="correct_answer" placeholder="Правильный ответ..." required>
            <button type="submit" class="btn">Сравнить</button>
        </form>
    </div>
</div>
</body>
</html>
