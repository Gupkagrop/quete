<?php
session_start();

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/db.php';

$action = $_POST['action'] ?? '';

// Определяет базовый путь от корня приложения (например, "/pre-alpha")
// Работает независимо от имени папки (pre-alpha, myapp и т.п.).
$baseUrl = preg_replace('#/core/.*$#', '', $_SERVER['SCRIPT_NAME']);
$baseUrl = rtrim($baseUrl, '/');

function redirectWithError($url, $message)
{
    global $baseUrl;
    $_SESSION['flash_error'] = $message;
    header('Location: ' . $baseUrl . '/' . ltrim($url, '/'));
    exit;
}

function redirectSuccess($url)
{
    global $baseUrl;
    header('Location: ' . $baseUrl . '/' . ltrim($url, '/'));
    exit;
}

if ($action === 'register') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        redirectWithError('register.php', 'Ошибка безопасности (CSRF). Пожалуйста, обновите страницу и попробуйте снова.');
    }

    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $passwordConfirm = $_POST['password_confirm'] ?? '';

    if ($username === '' || $email === '' || $password === '' || $passwordConfirm === '') {
        redirectWithError('register.php', 'Пожалуйста, заполните все поля.');
    }

    if (mb_strlen($username) < 3 || mb_strlen($username) > 50) {
        redirectWithError('register.php', 'Имя должно быть от 3 до 50 символов.');
    }

    if (!preg_match('/^[a-zA-Z0-9_а-яА-ЯёЁ.\-]+$/u', $username)) {
        redirectWithError('register.php', 'Имя может содержать только буквы, цифры и символы . _ -');
    }

    if (mb_strlen($password) < 4) {
        redirectWithError('register.php', 'Пароль должен содержать минимум 4 символа.');
    }

    if (preg_match('/\s/', $password)) {
        redirectWithError('register.php', 'Пароль не должен содержать пробелы.');
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        redirectWithError('register.php', 'Некорректный email.');
    }

    if ($password !== $passwordConfirm) {
        redirectWithError('register.php', 'Пароли не совпадают.');
    }

    if (usernameExists($username)) {
        redirectWithError('register.php', 'Пользователь с таким именем уже зарегистрирован.');
    }

    if (emailExists($email)) {
        redirectWithError('register.php', 'Пользователь с таким Email уже зарегистрирован.');
    }

    $userId = createUser($username, $email, $password);
    $_SESSION['user_id'] = $userId;
    $_SESSION['username'] = $username;
    session_regenerate_id(true);

    redirectSuccess('hub.php');
}

if ($action === 'login') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        redirectWithError('login.php', 'Ошибка безопасности (CSRF). Пожалуйста, обновите страницу и попробуйте снова.');
    }

    $identity = trim($_POST['identity'] ?? $_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($identity === '' || $password === '') {
        redirectWithError('login.php', 'Пожалуйста, заполните все поля.');
    }

    $user = getUserByIdentity($identity);
    if (!$user) {
        redirectWithError('login.php', 'Пользователь с таким никнеймом или Email не найден.');
    }

    if (!password_verify($password, $user['password_hash'])) {
        redirectWithError('login.php', 'Неверный пароль.');
    }

    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    session_regenerate_id(true);

    redirectSuccess('hub.php');
}

// Если действие неизвестно, просто вернуться на главную
header('Location: ' . $baseUrl . '/index.php');
exit;
