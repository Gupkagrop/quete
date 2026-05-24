<?php

// Функция загрузки переменных из .env
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

// Настройки подключения к базе данных
define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
define('DB_NAME', getenv('DB_NAME') ?: 'quete_db');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') !== false ? getenv('DB_PASS') : '');
define('DB_CHARSET', 'utf8mb4');

// Общие настройки
define('APP_NAME', 'Куэте');

// === GROQ API ===
define('GROQ_API_KEY', getenv('GROQ_API_KEY') ?: '');

// === ДРУГИЕ API (Gemini/Cohere) ===
define('GEMINI_API_KEY', getenv('GEMINI_API_KEY') ?: '');
define('COHERE_API_KEY', getenv('COHERE_API_KEY') ?: '');

// === НАСТРОЙКИ ПРОКСИ (для VPN / VLESS) ===
define('USE_PROXY', getenv('USE_PROXY') === 'true');
define('PROXY_ADDRESS', getenv('PROXY_ADDRESS') ?: '');
define('PROXY_TYPE', getenv('PROXY_TYPE') ?: 'socks5h');

// Настройки игры
define('ROUNDS_COUNT', 3);
define('ROUND_MULTIPLIERS', [1, 2, 3]); // х1, х2, х3 за раунды
define('BASE_POINTS', 10); // Базовые очки за правильный ответ
define('FAKE_TIME_DEFAULT', 60); // Время на фейк по умолчанию (секунды)
define('POLLING_INTERVAL', 2000); // Интервал AJAX polling (мс)

// Настройки WebSocket
define('WS_PORT', getenv('WS_PORT') !== false ? (int)getenv('WS_PORT') : 8888);
define('WS_HOST', getenv('WS_HOST') ?: '0.0.0.0'); // Слушать на всех интерфейсах (для сервера)
define('WS_CLIENT_HOST', getenv('WS_CLIENT_HOST') ?: '127.0.0.1'); // Хост для подключения клиента (в прод. пишем домен quete.ru)
define('WS_CLIENT_PORT', getenv('WS_CLIENT_PORT') !== false ? (int)getenv('WS_CLIENT_PORT') : 8888); // Порт для подключения клиента (в прод. через Nginx = 8888)


// === ЗАГОЛОВКИ БЕЗОПАСНОСТИ (HTTP SECURITY HEADERS) ===
if (!headers_sent() && php_sapi_name() !== 'cli') {
    // Включаем HSTS только при наличии HTTPS
    $isHttps = (isset($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) === 'on') || 
               (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https');
               
    if ($isHttps) {
        header("Strict-Transport-Security: max-age=63072000; includeSubDomains; preload");
    }
    
    header("X-Content-Type-Options: nosniff");
    header("X-Frame-Options: DENY");
    header("Referrer-Policy: strict-origin-when-cross-origin");
    
    // Генерация криптографического Nonce для безопасного выполнения встроенного JS
    if (!defined('CSP_NONCE')) {
        define('CSP_NONCE', base64_encode(random_bytes(16)));
    }
    
    // Динамический Content-Security-Policy (CSP) для совместимости локальной и боевой среды
    $wsClientHost = getenv('WS_CLIENT_HOST') ?: '127.0.0.1';
    $wsClientPort = getenv('WS_CLIENT_PORT') !== false ? (int)getenv('WS_CLIENT_PORT') : 8888;
    $wsScheme = $isHttps ? 'wss' : 'ws';
    
    // Для локальной отладки разрешаем также подключение к localhost/127.0.0.1
    $connectCsp = "connect-src 'self' {$wsScheme}://{$wsClientHost}:{$wsClientPort}";
    if (!$isHttps) {
        $connectCsp .= " ws://localhost:{$wsClientPort} ws://127.0.0.1:{$wsClientPort}";
    }
    
    $cspRules = [
        "default-src 'none'",
        "script-src 'self' 'nonce-" . CSP_NONCE . "'",
        "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com 'nonce-" . CSP_NONCE . "'",
        "font-src 'self' data: https://fonts.gstatic.com",
        $connectCsp,
        "img-src 'self' data:",
        "frame-ancestors 'none'",
        "base-uri 'self'",
        "form-action 'self'"
    ];
    
    header("Content-Security-Policy: " . implode('; ', $cspRules) . ';');
}



