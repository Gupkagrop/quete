<?php
/**
 * Класс-обработчик WebSocket-соединений (игровой сервер реального времени).
 */

namespace Quete;

use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;

// Класс GameWebSocket: обрабатывает сетевые события (открытие соединения, получение сообщений, отключение клиентов).
class GameWebSocket implements MessageComponentInterface {
    protected $clients;
    protected $lobbies = []; // Список активных комнат и подключенных к ним клиентов: lobby_id => [connections]
    
    public function __construct() {
        $this->clients = new \SplObjectStorage;
    }
    
    /**
     * onOpen — Обработка нового сетевого подключения.
     */
    public function onOpen(ConnectionInterface $conn) {
        $this->clients->attach($conn);
        echo "New connection: {$conn->resourceId}\n";
        
        // Одноразовая билетная авторизация WebSocket (Ticket-based auth)
        $queryParams = [];
        if (isset($conn->httpRequest)) {
            $query = $conn->httpRequest->getUri()->getQuery();
            parse_str($query, $queryParams);
        }
        $ticket = $queryParams['ticket'] ?? '';
        $userId = \verifyWebSocketTicket($ticket);
        
        if (!$userId) {
            echo "Connection {$conn->resourceId} closed: invalid ticket\n";
            $conn->send(json_encode([
                'type' => 'auth_error',
                'message' => 'Неверный или просроченный билет авторизации.'
            ]));
            $conn->close();
            return;
        }
        
        $conn->userId = $userId;
        $user = \getUserById($userId);
        $conn->username = $user ? $user['username'] : 'Игрок ' . $userId;
        echo "Connection {$conn->resourceId} authenticated as User ID: {$userId} ({$conn->username})\n";
    }
    
    /**
     * onMessage — Обработка входящего сообщения от игрока.
     */
    public function onMessage(ConnectionInterface $from, $msg) {
        try {
            $data = json_decode($msg, true);
            
            if (!$data || !isset($data['action'])) {
                return;
            }
            
            if (!isset($from->userId)) {
                echo "Unauthorized message from connection {$from->resourceId}\n";
                $from->close();
                return;
            }
            
            $action = $data['action'];
            $lobbyId = (int) ($data['lobby_id'] ?? 0);
            $userId = $from->userId; // Используем строго верифицированный ID пользователя из соединения
            
            // Инициализировать лобби если не существует
            if (!isset($this->lobbies[$lobbyId])) {
                $this->lobbies[$lobbyId] = [];
            }
            
            switch ($action) {
                case 'join':
                    $this->lobbies[$lobbyId][$userId] = $from;
                    $this->broadcast($lobbyId, json_encode([
                        'type' => 'player_joined',
                        'user_id' => $userId,
                        'timestamp' => time()
                    ]));
                    break;
                    
                case 'leave':
                    if (isset($this->lobbies[$lobbyId][$userId])) {
                        unset($this->lobbies[$lobbyId][$userId]);
                    }
                    $this->broadcast($lobbyId, json_encode([
                        'type' => 'player_left',
                        'user_id' => $userId,
                        'timestamp' => time()
                    ]));
                    break;
                    
                case 'update':
                    // Отправить обновление состояния игры всем в лобби
                    $this->broadcast($lobbyId, json_encode([
                        'type' => 'game_update',
                        'data' => $data['data'] ?? [],
                        'timestamp' => time()
                    ]));
                    break;
                    
                case 'action':
                    // Действие игрока (голосование, отправка фейка и т.д.)
                    $this->broadcast($lobbyId, json_encode([
                        'type' => 'player_action',
                        'user_id' => $userId,
                        'action_type' => $data['action_type'] ?? null,
                        'data' => $data['data'] ?? [],
                        'timestamp' => time()
                    ]));
                    break;

                case 'chat':
                    // Сохранить сообщение в БД
                    $msgText = strip_tags(trim($data['message'] ?? ''));
                    if ($msgText === '') {
                        break;
                    }
                    
                    // Применяем базовую модерацию сообщений чата (фильтрация нецензурной лексики по законам РФ)
                    $msgText = \moderateChatMessage($msgText);
                    
                    \addChatMessage($lobbyId, $userId, $msgText);
                    
                    // Разослать сообщение в чате
                    $this->broadcast($lobbyId, json_encode([
                        'type' => 'chat_message',
                        'user_id' => $userId,
                        'username' => $from->username ?? 'Игрок ' . $userId,
                        'message' => $msgText,
                        'timestamp' => time()
                    ]));
                    break;
            }
        } catch (\Exception $e) {
            echo "Error: {$e->getMessage()}\n";
        }
    }
    
    /**
     * onClose — Обработка отключения игрока.
     */
    public function onClose(ConnectionInterface $conn) {
        $this->clients->detach($conn);
        
        // Удалить из всех лобби
        foreach ($this->lobbies as $lobbyId => &$players) {
            foreach ($players as $userId => $connection) {
                if ($connection === $conn) {
                    unset($players[$userId]);
                    $this->broadcast($lobbyId, json_encode([
                        'type' => 'player_disconnected',
                        'user_id' => $userId,
                        'timestamp' => time()
                    ]));
                }
            }
        }
        
        echo "Connection {$conn->resourceId} disconnected\n";
    }
    
    /**
     * onError — Обработка возникших сетевых ошибок.
     */
    public function onError(ConnectionInterface $conn, \Exception $e) {
        echo "Error: {$e->getMessage()}\n";
        $conn->close();
    }
    
    /**
     * broadcast — Рассылка сообщения всем участникам комнаты (лобби).
     */
    private function broadcast($lobbyId, $msg) {
        if (!isset($this->lobbies[$lobbyId])) {
            return;
        }
        
        foreach ($this->lobbies[$lobbyId] as $userId => $connection) {
            try {
                $connection->send($msg);
            } catch (\Exception $e) {
                echo "Error sending to user $userId: {$e->getMessage()}\n";
            }
        }
    }
}
?>
