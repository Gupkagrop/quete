#!/usr/bin/env php
<?php
/**
 * Скрипт для запуска фоновой службы WebSocket-сервера Ratchet.
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../core/db.php';
require_once __DIR__ . '/GameWebSocket.php';

use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;
use Quete\GameWebSocket;

$port = WS_PORT;
$host = WS_HOST;

echo "Starting WebSocket server on {$host}:{$port}\n";

// Перед запуском сервера проверяем, отвечает ли база данных
try {
    getPDO();
    echo "Database connection: OK\n";
} catch (\Exception $e) {
    echo "Database connection: FAILED - " . $e->getMessage() . "\n";
    // Мы не останавливаем работу, так как сервер может попытаться запуститься (хотя функционал будет ограничен)
}

// Создаем экземпляр нашего игрового WebSocket-обработчика и оборачиваем его в стандартные сетевые протоколы
$app = new GameWebSocket();
$handler = new HttpServer(new WsServer($app));

// Инициализируем и запускаем сетевой сервер на указанном порту
try {
    $io = IoServer::factory($handler, $port, $host);
    echo "WebSocket server is running at ws://{$host}:{$port}\n";
    echo "Press Ctrl+C to stop\n";
    $io->run();
} catch (\Exception $e) {
    echo "FATAL ERROR: Could not start WebSocket server: " . $e->getMessage() . "\n";
    exit(1);
}
?>

