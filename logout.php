<?php
/**
 * Скрипт выхода пользователя из профиля и завершения текущей сессии.
 */

session_start();
session_unset();

// Полностью удаляем cookies сессии, чтобы браузер «забыл» авторизационные данные пользователя
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

session_destroy();
header('Location: index.php');
exit;

