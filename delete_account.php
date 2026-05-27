<?php
/**
 * Скрипт для удаления учетной записи пользователя и очистки его данных.
 */

session_start();
require_once 'core/db.php';

// Проверка авторизации: если пользователь не вошел в систему, отправляем его на страницу входа.
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Обработка удаления аккаунта: выполняется только при отправке формы через POST-запрос
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Защита от CSRF-атак: проверяем, что запрос отправлен именно с нашего сайта
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        die('Invalid CSRF token');
    }
    
    $userId = $_SESSION['user_id'];
    $pdo = getPDO();
    
    try {
        // Начинаем транзакцию в БД. Это нужно для надежности: либо все действия выполнятся успешно, либо база вернется в исходное состояние.
        $pdo->beginTransaction();
        
        // Удаляем пользователя. Связанные записи (lobby_players, votes, chat_messages, player_answers)
        // удалятся автоматически (каскадно) благодаря настройкам базы данных.
        $stmt = $pdo->prepare('DELETE FROM users WHERE id = ?');
        $stmt->execute([$userId]);
        
        // Подтверждаем изменения в базе данных
        $pdo->commit();
        
        // Очищаем сессию (стираем данные о том, кто авторизован на сайте) и удаляем куки сессии из браузера
        session_unset();
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        session_destroy();
        
        // Перенаправляем на главную страницу после успешного удаления
        header('Location: index.php');
        exit;
    } catch (Exception $e) {
        // В случае ошибки отменяем все изменения в базе данных и возвращаем пользователя в хаб с сообщением об ошибке
        $pdo->rollBack();
        $_SESSION['flash_error'] = 'Ошибка при удалении аккаунта: ' . $e->getMessage();
        header('Location: hub.php');
        exit;
    }
}

// Если кто-то попытался зайти на эту страницу напрямую через браузер (метод GET), отправляем его обратно в хаб
header('Location: hub.php');
exit;

