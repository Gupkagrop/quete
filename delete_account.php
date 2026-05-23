<?php
session_start();
require_once 'core/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        die('Invalid CSRF token');
    }
    
    $userId = $_SESSION['user_id'];
    $pdo = getPDO();
    
    try {
        $pdo->beginTransaction();
        
        // Удаляем пользователя. Связанные записи (lobby_players, votes, chat_messages, player_answers)
        // удалятся каскадно, благодаря настройкам FOREIGN KEY.
        $stmt = $pdo->prepare('DELETE FROM users WHERE id = ?');
        $stmt->execute([$userId]);
        
        $pdo->commit();
        
        // Очищаем сессию и удаляем куку
        session_unset();
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        session_destroy();
        
        // Перенаправляем на главную
        header('Location: index.php');
        exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['flash_error'] = 'Ошибка при удалении аккаунта: ' . $e->getMessage();
        header('Location: hub.php');
        exit;
    }
}

// Если метод не POST, возвращаем в хаб
header('Location: hub.php');
exit;
