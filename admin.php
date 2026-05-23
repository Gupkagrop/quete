<?php
/**
 * Панель Администратора игры "Куэте" (admin.php)
 * 
 * Предоставляет полный административный контроль над игровыми сессиями, лобби,
 * учетными записями пользователей и игровым чатом.
 * 
 * Доступ строго ограничен пользователем с никнеймом "admin".
 */

// Инициализируем сессию и подключаем ядро базы данных
session_start();
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/core/db.php';

// === ЗАЩИТА И ПРОВЕРКА ДОСТУПА ===
if (!isset($_SESSION['user_id']) || $_SESSION['username'] !== 'admin') {
    // Возвращаем HTTP-код 403 Forbidden
    header('HTTP/1.1 403 Forbidden');
    ?>
    <!DOCTYPE html>
    <html lang="ru">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>403 Доступ запрещен | Административный стенд</title>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;700;900&display=swap" rel="stylesheet">
        <style>
            body {
                background-color: #1A1F3B;
                color: #FFFFFF;
                font-family: 'Inter', sans-serif;
                display: flex;
                align-items: center;
                justify-content: center;
                min-height: 100vh;
                margin: 0;
            }
            .forbidden-card {
                text-align: center;
                background: #232B4B;
                border: 2px solid #E3342F;
                border-radius: 4px;
                padding: 40px 30px;
                max-width: 480px;
            }
            .forbidden-icon {
                font-size: 4em;
                margin-bottom: 20px;
            }
            h1 {
                color: #E3342F;
                font-size: 2.5em;
                margin: 0 0 15px 0;
                text-transform: uppercase;
                letter-spacing: 1px;
            }
            p {
                color: #8E9BC2;
                font-size: 1.1em;
                line-height: 1.6;
                margin-bottom: 30px;
            }
            .btn-back {
                background: #FF7A00;
                color: #000000;
                text-decoration: none;
                padding: 10px 20px;
                border-radius: 4px;
                font-weight: 700;
                text-transform: uppercase;
                font-size: 0.9em;
                transition: opacity 0.1s;
                display: inline-block;
            }
            .btn-back:hover {
                opacity: 0.9;
            }
        </style>
    </head>
    <body>
        <div class="forbidden-card">
            <div class="forbidden-icon">🛑</div>
            <h1>Доступ ограничен</h1>
            <p>Учетная запись <strong><?php echo isset($_SESSION['username']) ? htmlspecialchars($_SESSION['username']) : 'Гость'; ?></strong> не обладает правами суперадминистратора.</p>
            <a href="index.php" class="btn-back">Вернуться на главную</a>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// Получаем инстанс PDO для административных запросов
$pdo = getPDO();

// Генерируем CSRF-токен для защиты действий администратора
$csrfToken = getCsrfToken();

// === ОБРАБОТКА ДЕЙСТВИЙ АДМИНИСТРАТОРА (POST) ===
$successMessage = $_SESSION['admin_success'] ?? '';
$errorMessage = $_SESSION['admin_error'] ?? '';
unset($_SESSION['admin_success'], $_SESSION['admin_error']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    // Проверка CSRF-токена
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $_SESSION['admin_error'] = 'Ошибка безопасности (CSRF). Пожалуйста, обновите страницу.';
        header('Location: admin.php');
        exit;
    }

    try {
        switch ($action) {
            case 'cleanup_lobbies':
                // Вызываем встроенную в ядро функцию очистки устаревших комнат
                $removedCount = cleanupExpiredLobbies();
                if ($removedCount === false) {
                    throw new Exception('Не удалось выполнить очистку. Проверьте логи сервера.');
                }
                $_SESSION['admin_success'] = "Очистка успешно завершена! Удалено устаревших комнат: <strong>$removedCount</strong>.";
                break;

            case 'delete_lobby':
                // Принудительное удаление конкретной комнаты
                $lobbyId = (int)($_POST['lobby_id'] ?? 0);
                if ($lobbyId <= 0) {
                    throw new Exception('Некорректный ID игрового лобби.');
                }
                
                deleteLobby($lobbyId);
                $_SESSION['admin_success'] = "Игровое лобби <strong>ID #$lobbyId</strong> успешно удалено из базы со всеми каскадными данными.";
                break;

            case 'delete_user':
                // Принудительное удаление учетной записи игрока
                $targetUserId = (int)($_POST['target_user_id'] ?? 0);
                if ($targetUserId <= 0) {
                    throw new Exception('Некорректный ID пользователя.');
                }

                // Запрещаем удаление самого себя (администратора)
                if ($targetUserId === (int)$_SESSION['user_id']) {
                    throw new Exception('Вы не можете удалить собственную учетную запись администратора.');
                }

                $stmt = $pdo->prepare('DELETE FROM users WHERE id = :id');
                $stmt->execute(['id' => $targetUserId]);
                
                $_SESSION['admin_success'] = "Учетная запись пользователя <strong>ID #$targetUserId</strong> успешно удалена из системы.";
                break;

            default:
                throw new Exception('Запрошено неподдерживаемое административное действие.');
        }
    } catch (Exception $e) {
        $_SESSION['admin_error'] = 'Ошибка: ' . $e->getMessage();
    }

    header('Location: admin.php');
    exit;
}

// === СБОР СТАТИСТИКИ И ДАННЫХ ДЛЯ ОТОБРАЖЕНИЯ ===
// 1. Системные метрики
$usersCount = $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
$lobbiesCount = $pdo->query('SELECT COUNT(*) FROM lobbies')->fetchColumn();
$activeLobbiesCount = $pdo->query('SELECT COUNT(*) FROM lobbies WHERE is_active = 1')->fetchColumn();
$questionsCount = $pdo->query('SELECT COUNT(*) FROM generated_questions')->fetchColumn();

// 2. Список игровых лобби
$lobbies = $pdo->query('
    SELECT l.*, u.username as host_name, 
           (SELECT COUNT(*) FROM lobby_players lp WHERE lp.lobby_id = l.id) as current_players
    FROM lobbies l
    LEFT JOIN users u ON l.host_id = u.id
    ORDER BY l.id DESC
')->fetchAll();

// 3. Список пользователей (с поиском)
$searchTerm = trim($_GET['search_user'] ?? '');
if ($searchTerm !== '') {
    $stmtUsers = $pdo->prepare('
        SELECT id, username, email, wins_count, total_answers, correct_answers, created_at 
        FROM users 
        WHERE username LIKE :search OR email LIKE :search 
        ORDER BY id DESC 
        LIMIT 100
    ');
    $stmtUsers->execute(['search' => '%' . $searchTerm . '%']);
    $usersList = $stmtUsers->fetchAll();
} else {
    $usersList = $pdo->query('
        SELECT id, username, email, wins_count, total_answers, correct_answers, created_at 
        FROM users 
        ORDER BY id DESC 
        LIMIT 50
    ')->fetchAll();
}

// 4. Последние сообщения в глобальном чате лобби (30 штук)
$chatMessages = $pdo->query('
    SELECT cm.*, u.username, l.lobby_name
    FROM chat_messages cm
    JOIN users u ON cm.user_id = u.id
    JOIN lobbies l ON cm.lobby_id = l.id
    ORDER BY cm.id DESC
    LIMIT 30
')->fetchAll();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>⚙️ Панель Администратора | Куэте</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        /* === ПЕРЕМЕННЫЕ ЦВЕТОВ (Куэте - упрощенная плоская ретро-тема) === */
        :root {
            --bg-dark: #333B65;          /* Основной темно-синий фон */
            --bg-deep: #1A1F3B;          /* Сверхтемный для блоков и консоли */
            --bg-card: #232B4B;          /* Простой плоский фон карточек */
            --accent-orange: #FF7A00;    /* Фирменный оранжевый */
            --border-color: #3C4573;     /* Простая плоская рамка */
            --pill-dark: #293563;        /* Заглушенный синий */
            --text-white: #FFFFFF;
            --text-muted: #8E9BC2;       /* Тусклый сине-серый */
            
            --success-green: #38C172;
            --error-red: #E3342F;
            --warning-yellow: #F6993F;
            --info-blue: #3490DC;
        }

        /* === БАЗОВЫЙ СБРОС И НАСТРОЙКИ === */
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--bg-dark);
            color: var(--text-white);
            min-height: 100vh;
            padding: 20px 15px;
        }

        .container {
            max-width: 1100px;
            margin: 0 auto;
        }

        /* === УПРОЩЕННАЯ ШАПКА === */
        .admin-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 4px;
            padding: 15px 20px;
            margin-bottom: 25px;
            flex-wrap: wrap;
            gap: 15px;
        }

        .header-left h1 {
            font-size: 1.6em;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .header-left h1 span {
            color: var(--accent-orange);
        }

        .header-left p {
            color: var(--text-muted);
            font-size: 0.85em;
            margin-top: 4px;
        }

        .header-right {
            display: flex;
            gap: 8px;
            align-items: center;
            flex-wrap: wrap;
        }

        .btn-utility {
            background: var(--pill-dark);
            color: var(--text-white);
            border: 1px solid var(--border-color);
            padding: 8px 14px;
            border-radius: 4px;
            font-weight: 700;
            font-size: 0.85em;
            text-decoration: none;
            cursor: pointer;
            transition: background-color 0.1s, color 0.1s;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .btn-utility:hover {
            color: var(--text-white);
            background-color: #2e3b6e;
        }

        /* === УПРОЩЕННЫЕ УВЕДОМЛЕНИЯ === */
        .alert {
            padding: 12px 18px;
            border-radius: 4px;
            margin-bottom: 20px;
            font-weight: 600;
            font-size: 0.9em;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border: 1px solid var(--border-color);
        }

        .alert-success {
            background: rgba(56, 193, 114, 0.1);
            border-color: var(--success-green);
            color: #C6F6D5;
        }

        .alert-error {
            background: rgba(227, 52, 47, 0.1);
            border-color: var(--error-red);
            color: #FED7D7;
        }

        .alert-close {
            cursor: pointer;
            font-weight: bold;
            font-size: 1.2em;
            margin-left: 15px;
            opacity: 0.7;
        }

        .alert-close:hover {
            opacity: 1;
        }

        /* === УПРОЩЕННЫЕ МЕТРИКИ СИСТЕМЫ === */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 15px;
            margin-bottom: 25px;
        }

        .stat-card {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 4px;
            padding: 15px;
            text-align: center;
        }

        .stat-val {
            font-size: 1.8em;
            font-weight: 800;
            color: var(--accent-orange);
            margin-top: 4px;
        }

        .stat-label {
            font-size: 0.75em;
            font-weight: 700;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* === УПРОЩЕННЫЕ ТАБЫ === */
        .admin-nav {
            display: flex;
            gap: 5px;
            margin-bottom: 20px;
            border-bottom: 1px solid var(--border-color);
            padding-bottom: 8px;
            flex-wrap: wrap;
        }

        .nav-link {
            background: var(--pill-dark);
            color: var(--text-muted);
            border: 1px solid var(--border-color);
            font-size: 0.85em;
            font-weight: 700;
            padding: 8px 14px;
            cursor: pointer;
            border-radius: 4px;
            transition: background-color 0.1s, color 0.1s;
        }

        .nav-link:hover {
            color: var(--text-white);
            background-color: #2e3b6e;
        }

        .nav-link.active {
            color: #000;
            background-color: var(--accent-orange);
            border-color: var(--accent-orange);
        }

        /* === УПРОЩЕННЫЕ КАРТОЧКИ ПАНЕЛЕЙ === */
        .panel-card {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 4px;
            padding: 20px;
            margin-bottom: 25px;
        }

        .panel-card h2 {
            font-size: 1.2em;
            font-weight: 800;
            color: var(--accent-orange);
            margin-bottom: 15px;
            border-bottom: 1px solid var(--border-color);
            padding-bottom: 8px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
        }

        /* === УПРОЩЕННЫЕ ТАБЛИЦЫ === */
        .table-responsive {
            width: 100%;
            overflow-x: auto;
            margin-top: 15px;
            border-radius: 4px;
            border: 1px solid var(--border-color);
        }

        .admin-table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
            font-size: 0.85em;
            background: var(--bg-deep);
        }

        .admin-table th {
            background: var(--pill-dark);
            color: var(--text-white);
            font-weight: 700;
            padding: 10px 12px;
            border-bottom: 1px solid var(--border-color);
        }

        .admin-table td {
            padding: 10px 12px;
            border-bottom: 1px solid var(--border-color);
            color: #E2E8F0;
            vertical-align: middle;
        }

        .admin-table tr:last-child td {
            border-bottom: none;
        }

        .admin-table tr:hover td {
            background-color: rgba(255, 255, 255, 0.02);
        }

        /* === УПРОЩЕННЫЕ ФОРМЫ === */
        .search-row {
            display: flex;
            gap: 10px;
            margin-bottom: 15px;
            flex-wrap: wrap;
        }

        .form-input {
            background: var(--bg-deep);
            border: 1px solid var(--border-color);
            border-radius: 4px;
            padding: 8px 12px;
            color: var(--text-white);
            font-size: 0.9em;
            font-weight: 500;
            flex-grow: 1;
            max-width: 400px;
        }

        .form-input:focus {
            outline: none;
            border-color: var(--accent-orange);
        }

        .form-input::placeholder {
            color: rgba(255, 255, 255, 0.2);
        }

        /* === УПРОЩЕННЫЕ КНОПКИ ДЕЙСТВИЙ === */
        .btn-action {
            background: var(--accent-orange);
            color: #000;
            border: none;
            padding: 8px 14px;
            border-radius: 4px;
            font-weight: 700;
            font-size: 0.85em;
            cursor: pointer;
            transition: opacity 0.1s;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
        }

        .btn-action:hover {
            opacity: 0.9;
        }

        .btn-danger {
            background-color: var(--error-red);
            color: var(--text-white);
        }

        .btn-danger:hover {
            background-color: #c52d27;
        }

        /* === УПРОЩЕННЫЕ БЕДЖИ === */
        .badge {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 0.75em;
            font-weight: 700;
            text-transform: uppercase;
        }

        .badge-active {
            background-color: rgba(56, 193, 114, 0.15);
            color: var(--success-green);
            border: 1px solid rgba(56, 193, 114, 0.3);
        }

        .badge-waiting {
            background-color: rgba(246, 153, 63, 0.15);
            color: var(--warning-yellow);
            border: 1px solid rgba(246, 153, 63, 0.3);
        }

        .badge-private {
            background-color: rgba(52, 144, 220, 0.15);
            color: var(--info-blue);
            border: 1px solid rgba(52, 144, 220, 0.3);
        }

        /* === УПРОЩЕННЫЙ ЧАТ МОНИТОР === */
        .chat-monitor {
            display: flex;
            flex-direction: column;
            gap: 10px;
            max-height: 400px;
            overflow-y: auto;
            border: 1px solid var(--border-color);
            background: var(--bg-deep);
            padding: 12px;
            border-radius: 4px;
        }

        .chat-line {
            display: flex;
            flex-direction: column;
            gap: 4px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
            padding-bottom: 6px;
        }

        .chat-line:last-child {
            border-bottom: none;
            padding-bottom: 0;
        }

        .chat-meta {
            font-size: 0.75em;
            color: var(--text-muted);
            display: flex;
            gap: 8px;
            font-weight: 600;
        }

        .chat-room {
            color: var(--accent-orange);
        }

        .chat-user {
            color: #FFF;
            font-weight: 700;
        }

        .chat-msg {
            font-size: 0.88em;
            color: #E2E8F0;
            word-break: break-all;
        }

        .chat-empty {
            text-align: center;
            color: var(--text-muted);
            padding: 15px;
            font-style: italic;
            font-size: 0.85em;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Верхняя Шапка -->
        <header class="admin-header">
            <div class="header-left">
                <h1>⚙️ ПАНЕЛЬ <span>АДМИНИСТРАТОРА</span></h1>
                <p>Единый центр управления игровыми сессиями, пользователями и логами проекта "Куэте"</p>
            </div>
            <div class="header-right">
                <a href="hub.php" class="btn-utility">🎮 В Игровой Хаб</a>
                <a href="test_ai.php" class="btn-utility">🧪 Стенд ИИ</a>
                <a href="logout.php" class="btn-utility" style="border-color: var(--error-red); color: var(--error-red);">Выйти</a>
            </div>
        </header>

        <!-- Системные уведомления -->
        <?php if (!empty($successMessage)): ?>
            <div class="alert alert-success">
                <span><?php echo $successMessage; ?></span>
                <span class="alert-close" onclick="this.parentElement.style.display='none';">×</span>
            </div>
        <?php endif; ?>

        <?php if (!empty($errorMessage)): ?>
            <div class="alert alert-error">
                <span><?php echo $errorMessage; ?></span>
                <span class="alert-close" onclick="this.parentElement.style.display='none';">×</span>
            </div>
        <?php endif; ?>

        <!-- Блок Ключевых Метрик -->
        <section class="stats-grid">
            <div class="stat-card">
                <div class="stat-label">Всего Пользователей</div>
                <div class="stat-val"><?php echo $usersCount; ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Активных Комнат</div>
                <div class="stat-val"><?php echo $activeLobbiesCount; ?> / <?php echo $lobbiesCount; ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Сгенерировано ИИ Вопросов</div>
                <div class="stat-val"><?php echo $questionsCount; ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Режим WebSocket</div>
                <div class="stat-val" style="color: var(--success-green); font-size: 1.5em; margin-top: 12px;">🟢 RATChET ALIVE</div>
            </div>
        </section>

        <!-- Навигационные Вкладки -->
        <nav class="admin-nav">
            <button class="nav-link active" data-tab="lobbies-panel">🎮 Управление Лобби</button>
            <button class="nav-link" data-tab="users-panel">👥 База Пользователей</button>
            <button class="nav-link" data-tab="chat-panel">💬 Логи Внутриигрового Чата</button>
        </nav>

        <!-- === ВКЛАДКА 1: УПРАВЛЕНИЕ ЛОББИ === -->
        <section id="lobbies-panel" class="tab-content-panel">
            <div class="panel-card">
                <h2>
                    <span>🎮 Активные Игровые Комнаты (Sessions)</span>
                    <form method="POST" style="margin: 0;" onsubmit="return confirm('Вы действительно хотите принудительно очистить все неактивные лобби старше 2 часов?');">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                        <input type="hidden" name="action" value="cleanup_lobbies">
                        <button type="submit" class="btn-action">🧹 Очистить старые лобби</button>
                    </form>
                </h2>
                <p style="color: var(--text-muted); font-size: 0.9em; margin-bottom: 15px;">
                    Очистка комнат безопасно удаляет неактивные более 2 часов сессии вместе со всеми связанными сообщениями чата, ответами игроков и голосами в базе данных.
                </p>

                <?php if (empty($lobbies)): ?>
                    <div class="chat-empty">На данный момент нет запущенных игровых лобби.</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Название Комнаты</th>
                                    <th>Создатель (Host)</th>
                                    <th>Игроки</th>
                                    <th>Таймаут фейка</th>
                                    <th>Статус комнаты</th>
                                    <th>Действие</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($lobbies as $lobby): ?>
                                    <tr>
                                        <td><strong>#<?php echo $lobby['id']; ?></strong></td>
                                        <td><?php echo htmlspecialchars($lobby['lobby_name']); ?></td>
                                        <td><?php echo htmlspecialchars($lobby['host_name'] ?? 'Неизвестно'); ?></td>
                                        <td>
                                            <strong><?php echo $lobby['current_players']; ?></strong> / <?php echo $lobby['max_players']; ?>
                                        </td>
                                        <td><?php echo $lobby['fake_answer_time']; ?> сек</td>
                                        <td>
                                            <?php if ($lobby['is_active']): ?>
                                                <span class="badge badge-active">Идёт Игра</span>
                                            <?php else: ?>
                                                <span class="badge badge-waiting">В ожидании</span>
                                            <?php endif; ?>
                                            <?php if ($lobby['password'] !== null): ?>
                                                <span class="badge badge-private" title="Вход под паролем">🔒 Private</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <form method="POST" style="margin: 0;" onsubmit="return confirm('Вы уверены, что хотите жестко закрыть лобби #<?php echo $lobby['id']; ?>? Это вызовет выход всех игроков.');">
                                                <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                                                <input type="hidden" name="lobby_id" value="<?php echo $lobby['id']; ?>">
                                                <input type="hidden" name="action" value="delete_lobby">
                                                <button type="submit" class="btn-action btn-danger">Закрыть</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <!-- === ВКЛАДКА 2: БАЗА ПОЛЬЗОВАТЕЛЕЙ === -->
        <section id="users-panel" class="tab-content-panel" style="display: none;">
            <div class="panel-card">
                <h2>👥 Реестр Участников Системы</h2>
                
                <!-- Поиск игроков -->
                <form method="GET" action="admin.php" class="search-row">
                    <input type="hidden" name="tab" value="users-panel">
                    <input type="text" name="search_user" class="form-input" placeholder="Введите никнейм или Email пользователя..." value="<?php echo htmlspecialchars($searchTerm); ?>">
                    <button type="submit" class="btn-action">🔍 Поиск игрока</button>
                    <?php if ($searchTerm !== ''): ?>
                        <a href="admin.php?tab=users-panel" class="btn-utility">Сбросить</a>
                    <?php endif; ?>
                </form>

                <div class="table-responsive">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Никнейм</th>
                                <th>Email</th>
                                <th>Победы</th>
                                <th>Всего ответов</th>
                                <th>Верных ответов</th>
                                <th>Точность %</th>
                                <th>Регистрация</th>
                                <th>Действие</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($usersList as $usr): ?>
                                <tr>
                                    <td><strong>#<?php echo $usr['id']; ?></strong></td>
                                    <td>
                                        <span style="font-weight: 700; color: <?php echo $usr['username'] === 'admin' ? 'var(--accent-orange)' : '#FFF'; ?>">
                                            <?php echo htmlspecialchars($usr['username']); ?>
                                        </span>
                                        <?php if ($usr['username'] === 'admin'): ?>
                                            <span class="badge badge-active" style="padding: 2px 4px; font-size:0.7em;">ADMIN</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($usr['email']); ?></td>
                                    <td style="color: var(--warning-yellow);">🏆 <?php echo $usr['wins_count']; ?></td>
                                    <td><?php echo $usr['total_answers']; ?></td>
                                    <td style="color: var(--success-green);"><?php echo $usr['correct_answers']; ?></td>
                                    <td>
                                        <?php 
                                        $percent = 0;
                                        if ($usr['total_answers'] > 0) {
                                            $percent = round(($usr['correct_answers'] / $usr['total_answers']) * 100, 1);
                                        }
                                        echo $percent . '%';
                                        ?>
                                    </td>
                                    <td style="color: var(--text-muted); font-size: 0.85em;">
                                        <?php echo date('d.m.Y H:i', strtotime($usr['created_at'])); ?>
                                    </td>
                                    <td>
                                        <?php if ($usr['username'] !== 'admin'): ?>
                                            <form method="POST" style="margin: 0;" onsubmit="return confirm('Вы действительно хотите НАВСЕГДА удалить учетную запись <?php echo htmlspecialchars($usr['username']); ?> и очистить все его связанные игровые данные?');">
                                                <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                                                <input type="hidden" name="target_user_id" value="<?php echo $usr['id']; ?>">
                                                <input type="hidden" name="action" value="delete_user">
                                                <button type="submit" class="btn-action btn-danger">Удалить</button>
                                            </form>
                                        <?php else: ?>
                                            <span style="color: var(--text-muted); font-style:italic;">Неприкасаемый</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <!-- === ВКЛАДКА 3: ЛОГИ ВНУТРИИГРОВОГО ЧАТА === -->
        <section id="chat-panel" class="tab-content-panel" style="display: none;">
            <div class="panel-card">
                <h2>💬 Аудит Сообщений Внутриигрового Чата (Стрим в реальном времени)</h2>
                <p style="color: var(--text-muted); font-size: 0.9em; margin-bottom: 20px;">
                    Отображает последние 30 сообщений, отправленных во всех активных игровых лобби. Выводит промодерированный по законам РФ (ФЗ-149) текст.
                </p>

                <div class="chat-monitor">
                    <?php if (empty($chatMessages)): ?>
                        <div class="chat-empty">История игровых чатов пуста или лобби еще не общались.</div>
                    <?php else: ?>
                        <?php foreach ($chatMessages as $msg): ?>
                            <div class="chat-line">
                                <div class="chat-meta">
                                    <span class="chat-room">📍 Комната: <?php echo htmlspecialchars($msg['lobby_name']); ?></span>
                                    <span class="chat-user">👤 <?php echo htmlspecialchars($msg['username']); ?></span>
                                    <span class="chat-time" style="margin-left: auto;">
                                        <?php echo date('H:i:s (d.m)', strtotime($msg['created_at'])); ?>
                                    </span>
                                </div>
                                <div class="chat-msg">
                                    <?php echo htmlspecialchars(moderateChatMessage($msg['message'])); ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </section>
    </div>

    <!-- === КЛИЕНТСКИЙ СЦЕНАРИЙ ДЛЯ ТАБОВ === -->
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const tabs = document.querySelectorAll('.nav-link');
            const contents = document.querySelectorAll('.tab-content-panel');

            // Автоматическое восстановление открытой вкладки по GET параметру 'tab'
            const urlParams = new URLSearchParams(window.location.search);
            const activeTabParam = urlParams.get('tab');
            if (activeTabParam) {
                const targetTab = document.querySelector(`[data-tab="${activeTabParam}"]`);
                if (targetTab) {
                    tabs.forEach(t => t.classList.remove('active'));
                    targetTab.classList.add('active');
                    contents.forEach(c => c.style.display = 'none');
                    document.getElementById(activeTabParam).style.display = 'block';
                }
            }

            // Обработчик переключения табов
            tabs.forEach(tab => {
                tab.addEventListener('click', () => {
                    tabs.forEach(t => t.classList.remove('active'));
                    tab.classList.add('active');

                    const target = tab.getAttribute('data-tab');
                    contents.forEach(c => c.style.display = 'none');
                    document.getElementById(target).style.display = 'block';
                });
            });
        });
    </script>
</body>
</html>
