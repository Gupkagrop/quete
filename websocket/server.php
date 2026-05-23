#!/usr/bin/env php
<?php
/**
 * WebSocket сервер для игры Куэте
 * 
 * Запуск: php websocket/server.php
 * По умолчанию слушает на localhost:8080
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

// Проверка подключения к базе данных
try {
    getPDO();
    echo "Database connection: OK\n";
} catch (\Exception $e) {
    echo "Database connection: FAILED - " . $e->getMessage() . "\n";
    // Мы не выходим, так как сервер может работать без БД (но без чата)
}

$app = new GameWebSocket();
$handler = new HttpServer(new WsServer($app));

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
