<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (isset($_SESSION['user_id'])) {
    require_once __DIR__ . '/../core/db.php';
    $currentLobby = getLobbyByUserId($_SESSION['user_id']);
    $currentPage = basename($_SERVER['PHP_SELF']);
    
    if ($currentLobby) {
        if ($currentLobby['is_active']) {
            header('Location: game.php?lobby_id=' . $currentLobby['id']);
            exit;
        }
        // Проверить, что пользователь действительно в лобби (не выгнан)
        $players = getLobbyPlayers($currentLobby['id']);
        $stillInLobby = false;
        foreach ($players as $p) {
            if ($p['username'] == $_SESSION['username']) {
                $stillInLobby = true;
                break;
            }
        }
        
        if (!$stillInLobby && !in_array($currentPage, ['hub.php', 'login.php', 'register.php', 'leave_lobby.php'])) {
            // Пользователь был выгнан из лобби, перенаправить на hub
            header('Location: hub.php');
            exit;
        }
        
        if ($stillInLobby && !in_array($currentPage, ['lobby.php', 'game.php', 'leave_lobby.php', 'login.php', 'register.php', 'kick_player.php'])) {
            header('Location: lobby.php?lobby_id=' . $currentLobby['id']);
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Куэте: онлайн-викторина с ИИ-генерацией вопросов. Сыграй в соло или с друзьями в ламповой ретро-атмосфере!">
    
    <!-- Канонический URL для предотвращения дублей -->
    <?php
    $requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    if ($requestUri === '/index.php' || $requestUri === '') {
        $requestUri = '/';
    }
    $canonicalUrl = 'https://quete.ru' . $requestUri;
    ?>
    <link rel="canonical" href="<?php echo htmlspecialchars($canonicalUrl); ?>">

    <!-- Open Graph (VK, Telegram, Facebook) -->
    <meta property="og:type" content="website">
    <meta property="og:title" content="Куэте - Онлайн квиз с ИИ-вопросами">
    <meta property="og:description" content="Ламповая онлайн-викторина с генерацией вопросов искусственным интеллектом в реальном времени. Сыграй с друзьями или в соло!">
    <meta property="og:url" content="<?php echo htmlspecialchars($canonicalUrl); ?>">
    <meta property="og:image" content="https://quete.ru/assets/img/login_insert_coin.jpeg">
    <meta property="og:site_name" content="Куэте">

    <!-- Twitter Cards -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="Куэте - Онлайн квиз с ИИ-вопросами">
    <meta name="twitter:description" content="Ламповая онлайн-викторина с генерацией вопросов искусственным интеллектом в реальном времени. Сыграй с друзьями или в соло!">
    <meta name="twitter:image" content="https://quete.ru/assets/img/login_insert_coin.jpeg">

    <!-- Место для тегов верификации вебмастеров (раскомментируйте и вставьте ваши ключи) -->
    <!-- <meta name="google-site-verification" content="ВАШ_КОД_GOOGLE" /> -->
    <!-- <meta name="yandex-verification" content="ВАШ_КОД_YANDEX" /> -->

    <link rel="icon" type="image/x-icon" href="favicon.ico">
    <title>Куэте - Онлайн квиз</title>
    <link href="https://fonts.googleapis.com/css2?family=Yanone+Kaffeesatz:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <header class="header-wrapper">
        <!-- Логотип: пиксельный маркер + название + акцент -->
        <a href="index.php" class="logo-box">
            <span class="logo-pixel"></span>
            Куэте<span class="logo-accent">:</span>
        </a>
    </header>