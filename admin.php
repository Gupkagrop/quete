<?php
/**
 * Панель администратора игры. Позволяет просматривать статистику, управлять комнатами и пользователями.
 */

session_start();
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/core/db.php';

// Блок проверки прав доступа: если пользователь не вошел в систему или его имя не "admin", 
// доступ запрещается, отображается страница с сообщением об ошибке 403.
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

$pdo = getPDO();
$csrfToken = getCsrfToken();
$message = '';

// Обработка административных действий:
// Этот блок обрабатывает запросы на очистку старых комнат, удаление конкретного лобби или
// удаление пользователя. Все запросы защищены от подделки с помощью CSRF-токена безопасности.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $message = 'Ошибка безопасности (CSRF).';
    } else {
        $action = $_POST['action'] ?? '';
        try {
            if ($action === 'cleanup_lobbies') {
                $count = cleanupExpiredLobbies();
                $message = "Устаревшие лобби успешно очищены. Удалено: $count.";
            } elseif ($action === 'delete_lobby') {
                $lobbyId = (int)($_POST['lobby_id'] ?? 0);
                if ($lobbyId > 0) {
                    deleteLobby($lobbyId);
                    $message = "Лобби #$lobbyId удалено.";
                }
            } elseif ($action === 'delete_user') {
                $userId = (int)($_POST['target_user_id'] ?? 0);
                if ($userId > 0 && $userId !== (int)$_SESSION['user_id']) {
                    $stmt = $pdo->prepare('DELETE FROM users WHERE id = :id');
                    $stmt->execute(['id' => $userId]);
                    $message = "Пользователь #$userId удален.";
                }
            }
        } catch (Exception $e) {
            $message = 'Ошибка: ' . $e->getMessage();
        }
    }
}

// Сбор статистики:
// Запросы к базе данных для подсчета общего числа пользователей, комнат (лобби) и сгенерированных ИИ вопросов.
$usersCount = $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
$lobbiesCount = $pdo->query('SELECT COUNT(*) FROM lobbies')->fetchColumn();
$questionsCount = $pdo->query('SELECT COUNT(*) FROM generated_questions')->fetchColumn();

// Получение списка активных лобби:
// Запрос выбирает все игровые комнаты вместе с именами их создателей и количеством подключенных в данный момент игроков.
$lobbies = $pdo->query('
    SELECT l.*, u.username as host_name,
           (SELECT COUNT(*) FROM lobby_players lp WHERE lp.lobby_id = l.id) as current_players
    FROM lobbies l
    LEFT JOIN users u ON l.host_id = u.id
    ORDER BY l.id DESC
')->fetchAll();

// Поиск и вывод списка пользователей:
// Если администратор ввел запрос в строку поиска, то ищем пользователя по никнейму или почте. 
// Иначе выводим последние 20 зарегистрированных аккаунтов.
$searchTerm = trim($_GET['search_user'] ?? '');
if ($searchTerm !== '') {
    $stmt = $pdo->prepare('SELECT id, username, email, wins_count FROM users WHERE username LIKE :search OR email LIKE :search LIMIT 50');
    $stmt->execute(['search' => '%' . $searchTerm . '%']);
    $usersList = $stmt->fetchAll();
} else {
    $usersList = $pdo->query('SELECT id, username, email, wins_count FROM users ORDER BY id DESC LIMIT 20')->fetchAll();
}

// Получение последних сообщений чата:
// Выбирает 15 самых свежих сообщений из игровых комнат, чтобы администратор мог отслеживать переписку игроков.
$chatMessages = $pdo->query('
    SELECT cm.*, u.username, l.lobby_name
    FROM chat_messages cm
    JOIN users u ON cm.user_id = u.id
    JOIN lobbies l ON cm.lobby_id = l.id
    ORDER BY cm.id DESC
    LIMIT 15
')->fetchAll();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Панель Администратора</title>
    <style nonce="<?php echo CSP_NONCE; ?>">
        body { font-family: sans-serif; background: #2f3640; color: #f5f6fa; margin: 20px; }
        .container { max-width: 900px; margin: 0 auto; background: #353b48; padding: 20px; border-radius: 6px; }
        h1, h2 { color: #f5f6fa; margin-top: 0; }
        .nav { margin-bottom: 20px; }
        .nav a { color: #00a8ff; text-decoration: none; margin-right: 15px; font-weight: bold; }
        .stats { display: flex; gap: 15px; margin-bottom: 20px; }
        .stat-card { flex: 1; background: #2f3640; padding: 15px; border-radius: 4px; text-align: center; }
        .stat-val { font-size: 24px; font-weight: bold; color: #e1b12c; margin-top: 5px; }
        .msg { background: #4cd137; color: #fff; padding: 10px; border-radius: 4px; margin-bottom: 20px; font-weight: bold; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 25px; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #718093; }
        th { background-color: #2f3640; }
        .btn { background: #00a8ff; color: #fff; border: none; padding: 6px 12px; cursor: pointer; border-radius: 4px; font-weight: bold; }
        .btn-danger { background: #e84118; }
        .search-box { margin-bottom: 15px; }
        .input-text { padding: 6px; border: 1px solid #718093; background: #2f3640; color: #fff; border-radius: 4px; width: 250px; }
        .chat-container { background: #2f3640; padding: 12px; border-radius: 4px; max-height: 250px; overflow-y: auto; }
        .chat-line { padding: 4px 0; border-bottom: 1px solid #353b48; font-size: 14px; }
    </style>
</head>
<body>
<div class="container">
    <h1>Панель Администратора</h1>
    <div class="nav">
        <a href="hub.php">В игровой хаб</a>
        <a href="test_ai.php">Стенд ИИ</a>
        <a href="logout.php">Выйти</a>
    </div>

    <?php if ($message): ?>
        <div class="msg"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>

    <div class="stats">
        <div class="stat-card">
            <div>Пользователей</div>
            <div class="stat-val"><?php echo $usersCount; ?></div>
        </div>
        <div class="stat-card">
            <div>Активных лобби</div>
            <div class="stat-val"><?php echo count($lobbies); ?> / <?php echo $lobbiesCount; ?></div>
        </div>
        <div class="stat-card">
            <div>ИИ вопросов</div>
            <div class="stat-val"><?php echo $questionsCount; ?></div>
        </div>
    </div>

    <h2>Управление лобби</h2>
    <form method="POST" style="margin-bottom: 15px;">
        <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
        <input type="hidden" name="action" value="cleanup_lobbies">
        <button type="submit" class="btn">Очистить старые лобби (> 2 часов)</button>
    </form>

    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Название</th>
                <th>Создатель</th>
                <th>Игроки</th>
                <th>Действие</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($lobbies as $lobby): ?>
                <tr>
                    <td>#<?php echo $lobby['id']; ?></td>
                    <td><?php echo htmlspecialchars($lobby['lobby_name']); ?></td>
                    <td><?php echo htmlspecialchars($lobby['host_name'] ?? 'Неизвестно'); ?></td>
                    <td><?php echo $lobby['current_players']; ?> / <?php echo $lobby['max_players']; ?></td>
                    <td>
                        <form method="POST" style="display:inline;" onsubmit="return confirm('Закрыть это лобби?');">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                            <input type="hidden" name="action" value="delete_lobby">
                            <input type="hidden" name="lobby_id" value="<?php echo $lobby['id']; ?>">
                            <button type="submit" class="btn btn-danger">Закрыть</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <h2>База пользователей</h2>
    <form method="GET" class="search-box">
        <input type="text" name="search_user" class="input-text" placeholder="Никнейм или Email..." value="<?php echo htmlspecialchars($searchTerm); ?>">
        <button type="submit" class="btn">Найти</button>
        <?php if ($searchTerm !== ''): ?>
            <a href="admin.php" class="btn" style="background:#718093; text-decoration:none; padding: 6px 12px; display:inline-block;">Сбросить</a>
        <?php endif; ?>
    </form>

    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Никнейм</th>
                <th>Email</th>
                <th>Победы</th>
                <th>Действие</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($usersList as $usr): ?>
                <tr>
                    <td>#<?php echo $usr['id']; ?></td>
                    <td><?php echo htmlspecialchars($usr['username']); ?></td>
                    <td><?php echo htmlspecialchars($usr['email']); ?></td>
                    <td>🏆 <?php echo $usr['wins_count']; ?></td>
                    <td>
                        <?php if ($usr['username'] !== 'admin'): ?>
                            <form method="POST" style="display:inline;" onsubmit="return confirm('Удалить пользователя?');">
                                <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                                <input type="hidden" name="action" value="delete_user">
                                <input type="hidden" name="target_user_id" value="<?php echo $usr['id']; ?>">
                                <button type="submit" class="btn btn-danger">Удалить</button>
                            </form>
                        <?php else: ?>
                            -
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <h2>Мониторинг чата</h2>
    <div class="chat-container">
        <?php if (empty($chatMessages)): ?>
            <div style="color: #718093; font-style: italic;">Сообщений в чате нет</div>
        <?php else: ?>
            <?php foreach ($chatMessages as $msg): ?>
                <div class="chat-line">
                    <span style="color: #e1b12c;">[<?php echo htmlspecialchars($msg['lobby_name']); ?>]</span>
                    <strong><?php echo htmlspecialchars($msg['username']); ?>:</strong>
                    <span><?php echo htmlspecialchars($msg['message']); ?></span>
                    <span style="float: right; font-size: 11px; color: #718093;"><?php echo $msg['created_at']; ?></span>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
