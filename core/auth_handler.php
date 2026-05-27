<?php
/**
 * Обработчик сессий, авторизации, регистрации и защиты от некорректных никнеймов.
 */

session_start();

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/db.php';

$action = $_POST['action'] ?? '';

// Определяет базовый путь от корня приложения (например, "/pre-alpha")
// Работает независимо от имени папки (pre-alpha, myapp и т.п.).
$baseUrl = preg_replace('#/core/.*$#', '', $_SERVER['SCRIPT_NAME']);
$baseUrl = rtrim($baseUrl, '/');

/**
     * redirectWithError — Перенаправление пользователя на указанную страницу с выводом ошибки.
     */
function redirectWithError($url, $message)
{
    global $baseUrl;
    $_SESSION['flash_error'] = $message;
    header('Location: ' . $baseUrl . '/' . ltrim($url, '/'));
    exit;
}

/**
     * redirectSuccess — Успешное перенаправление пользователя на указанную страницу.
     */
function redirectSuccess($url)
{
    global $baseUrl;
    header('Location: ' . $baseUrl . '/' . ltrim($url, '/'));
    exit;
}

// Блок регистрации нового пользователя:
// Происходит, когда отправлена форма регистрации. Выполняется серия проверок на совпадение паролей,
// валидность адреса почты, уникальность имени и отсутствие нецензурных слов в никнейме.
if ($action === 'register') {
    // Проверка защитного ключа безопасности сайта (CSRF)
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        redirectWithError('register.php', 'Ошибка безопасности (CSRF). Пожалуйста, обновите страницу и попробуйте снова.');
    }

    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $passwordConfirm = $_POST['password_confirm'] ?? '';

    // Обязательное галочка согласия
    if (empty($_POST['legal_consent'])) {
        redirectWithError('register.php', 'Необходимо принять Пользовательское соглашение и Политику конфиденциальности.');
    }

    // Все ли поля заполнены
    if ($username === '' || $email === '' || $password === '' || $passwordConfirm === '') {
        redirectWithError('register.php', 'Пожалуйста, заполните все поля.');
    }

    // Проверка никнейма на цензуру с помощью лексического фильтра
    if (moderateChatMessage($username) !== $username) {
        redirectWithError('register.php', 'Имя пользователя содержит недопустимые/нецензурные слова.');
    }

    // Ограничение длины имени
    if (mb_strlen($username) < 3 || mb_strlen($username) > 50) {
        redirectWithError('register.php', 'Имя должно быть от 3 до 50 символов.');
    }

    // Разрешенные символы в имени
    if (!preg_match('/^[a-zA-Z0-9_а-яА-ЯёЁ.\-]+$/u', $username)) {
        redirectWithError('register.php', 'Имя может содержать только буквы, цифры и символы . _ -');
    }

    // Длина пароля
    if (mb_strlen($password) < 4) {
        redirectWithError('register.php', 'Пароль должен содержать минимум 4 символа.');
    }

    // Пробелы в пароле
    if (preg_match('/\s/', $password)) {
        redirectWithError('register.php', 'Пароль не должен содержать пробелы.');
    }

    // Валидность почты
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        redirectWithError('register.php', 'Некорректный email.');
    }

    // Совпадение паролей
    if ($password !== $passwordConfirm) {
        redirectWithError('register.php', 'Пароли не совпадают.');
    }

    // Проверка уникальности никнейма
    if (usernameExists($username)) {
        redirectWithError('register.php', 'Пользователь с таким именем уже зарегистрирован.');
    }

    // Проверка уникальности email
    if (emailExists($email)) {
        redirectWithError('register.php', 'Пользователь с таким Email уже зарегистрирован.');
    }

    // Создаем пользователя в БД, записываем ID и никнейм в сессию и перенаправляем в главное меню
    $userId = createUser($username, $email, $password);
    $_SESSION['user_id'] = $userId;
    $_SESSION['username'] = $username;
    session_regenerate_id(true);

    redirectSuccess('hub.php');
}

// Блок авторизации пользователя (входа в аккаунт):
// Срабатывает при отправке формы входа. Ищет аккаунт по почте или никнейму,
// сверяет зашифрованный пароль с сохраненным в базе и открывает сессию.
if ($action === 'login') {
    // Проверка защитного ключа безопасности сайта (CSRF)
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        redirectWithError('login.php', 'Ошибка безопасности (CSRF). Пожалуйста, обновите страницу и попробуйте снова.');
    }

    $identity = trim($_POST['identity'] ?? $_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($identity === '' || $password === '') {
        redirectWithError('login.php', 'Пожалуйста, заполните все поля.');
    }

    // Ищем пользователя в базе данных
    $user = getUserByIdentity($identity);
    if (!$user) {
        redirectWithError('login.php', 'Пользователь с таким никнеймом или Email не найден.');
    }

    // Сверяем пароль с помощью криптографической функции сверки хэшей
    if (!password_verify($password, $user['password_hash'])) {
        redirectWithError('login.php', 'Неверный пароль.');
    }

    // В случае успеха сохраняем информацию о сессии и перенаправляем в игровой хаб
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    session_regenerate_id(true);

    redirectSuccess('hub.php');
}

// Если действие неизвестно, просто вернуться на главную страницу
header('Location: ' . $baseUrl . '/index.php');
exit;

