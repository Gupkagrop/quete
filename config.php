<?php
/**
 * Главный конфигурационный файл проекта. Настраивает базу данных, правила игры, WebSocket и параметры безопасности.
 */

/**
 * Загружает секретные параметры подключения (пароли, API-ключи) из локального файла .env в окружение сервера.
 */
function loadEnv($dir)
{
    $filePath = $dir . '/.env';
    if (!file_exists($filePath)) {
        return;
    }

    $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || strpos($line, '#') === 0) {
            continue;
        }

        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);

            // Удаляем кавычки, если они есть
            if (preg_match('/^"([^"]*)"$/', $value, $matches) || preg_match("/^'([^']*)'$/", $value, $matches)) {
                $value = $matches[1];
            }

            // Устанавливаем переменную, если она еще не установлена
            if (getenv($key) === false) {
                putenv(sprintf('%s=%s', $key, $value));
                $_ENV[$key] = $value;
                $_SERVER[$key] = $value;
            }
        }
    }
}

// Загружаем переменные окружения из корня проекта
loadEnv(__DIR__);

// Настройки подключения к базе данных:
// Здесь указываются параметры сервера базы данных, имя базы, имя пользователя и пароль.
// Если в секретном файле .env ничего не указано, используются стандартные локальные значения.
define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
define('DB_NAME', getenv('DB_NAME') ?: 'quete_db');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') !== false ? getenv('DB_PASS') : '');
define('DB_CHARSET', 'utf8mb4');

// Общие настройки
define('APP_NAME', 'Куэте');

// Настройки интеграции с искусственным интеллектом (API ключи нейросетей):
define('GROQ_API_KEY', getenv('GROQ_API_KEY') ?: '');
define('GEMINI_API_KEY', getenv('GEMINI_API_KEY') ?: '');
define('COHERE_API_KEY', getenv('COHERE_API_KEY') ?: '');

// Настройки прокси (нужны, если сервер должен делать запросы к ИИ через обход блокировок):
define('USE_PROXY', getenv('USE_PROXY') === 'true');
define('PROXY_ADDRESS', getenv('PROXY_ADDRESS') ?: '');
define('PROXY_TYPE', getenv('PROXY_TYPE') ?: 'socks5h');

// Внутренние правила игры:
define('ROUNDS_COUNT', 3); // Сколько раундов длится игра
define('ROUND_MULTIPLIERS', [1, 2, 3]); // Умножение очков в зависимости от раунда (х1, х2, х3)
define('BASE_POINTS', 10); // Базовые очки за один правильный ответ
define('FAKE_TIME_DEFAULT', 60); // Время, которое дается игрокам на придумывание ложного ответа (в секундах)
define('POLLING_INTERVAL', 2000); // Как часто сайт опрашивает сервер о состоянии игры в старом режиме (в миллисекундах)

// Определяем режим локального запуска:
// 1. Проверяем значение из .env
// 2. Если в .env не задано, автоматически определяем по текущему хосту (для localhost или локальных/частных IP)
$detectedHost = $_SERVER['HTTP_HOST'] ?? '127.0.0.1';
if (strpos($detectedHost, ':') !== false) {
    $detectedHost = explode(':', $detectedHost)[0];
}

$isLocalHost = in_array($detectedHost, ['localhost', '127.0.0.1', '::1']) || 
               filter_var($detectedHost, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false;

$localLaunchEnv = getenv('LOCAL_LAUNCH');
if ($localLaunchEnv !== false) {
    define('LOCAL_LAUNCH', $localLaunchEnv === 'true');
} else {
    define('LOCAL_LAUNCH', $isLocalHost);
}

// Настройки WebSocket-соединения:
// Позволяют игрокам мгновенно взаимодействовать друг с другом без задержек.
define('WS_PORT', getenv('WS_PORT') !== false ? (int)getenv('WS_PORT') : 8888);
define('WS_HOST', getenv('WS_HOST') ?: '0.0.0.0'); // Слушать на всех интерфейсах (для сервера)

// Определяем хост для подключения клиента:
// Если мы на локалке и в .env не задан конкретный хост (или задан как 127.0.0.1/localhost),
// автоматически используем текущий домен/IP, с которого зашли на сайт.
// Это позволяет беспрепятственно подключаться другим устройствам в локальной сети.
$envWsClientHost = getenv('WS_CLIENT_HOST');
if (LOCAL_LAUNCH && (empty($envWsClientHost) || $envWsClientHost === '127.0.0.1' || $envWsClientHost === 'localhost')) {
    $wsClientHost = $detectedHost;
} else {
    $wsClientHost = $envWsClientHost ?: '127.0.0.1';
}
define('WS_CLIENT_HOST', $wsClientHost);
define('WS_CLIENT_PORT', getenv('WS_CLIENT_PORT') !== false ? (int)getenv('WS_CLIENT_PORT') : 8888); // Порт для подключения клиента (в прод. через Nginx = 8888)

// Защита и заголовки безопасности (HTTP Security Headers):
// Этот блок защищает сайт от различных веб-угроз. В режиме локального запуска
// избыточные заголовки безопасности (CSP, HSTS) отключаются для удобства разработки и отладки.
if (!headers_sent() && php_sapi_name() !== 'cli') {
    // Включаем HSTS только при наличии безопасного соединения HTTPS и вне локального запуска
    $isHttps = (isset($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) === 'on') || 
               (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https');
               
    if ($isHttps && !LOCAL_LAUNCH) {
        header("Strict-Transport-Security: max-age=63072000; includeSubDomains; preload");
    }
    
    header("X-Content-Type-Options: nosniff");
    header("X-Frame-Options: SAMEORIGIN"); // Позволяет локально открывать во фреймах, если нужно (вместо DENY)
    header("Referrer-Policy: strict-origin-when-cross-origin");
    
    // Генерация одноразового ключа (Nonce) для безопасного выполнения встроенного JavaScript
    if (!defined('CSP_NONCE')) {
        define('CSP_NONCE', base64_encode(random_bytes(16)));
    }
    
    // В локальном режиме полностью отключаем строгую политику CSP, чтобы не блокировать локальные сокеты и отладку
    if (!LOCAL_LAUNCH) {
        $wsClientPort = getenv('WS_CLIENT_PORT') !== false ? (int)getenv('WS_CLIENT_PORT') : 8888;
        $wsScheme = $isHttps ? 'wss' : 'ws';
        
        $connectCsp = "connect-src 'self' {$wsScheme}://" . WS_CLIENT_HOST . ":{$wsClientPort}";
        $cspRules = [
            "default-src 'none'",
            "script-src 'self' 'unsafe-inline'",
            "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com",
            "font-src 'self' data: https://fonts.gstatic.com",
            $connectCsp,
            "img-src 'self' data:",
            "frame-ancestors 'none'",
            "base-uri 'self'",
            "form-action 'self'"
        ];
        
        header("Content-Security-Policy: " . implode('; ', $cspRules) . ';');
    }
}





