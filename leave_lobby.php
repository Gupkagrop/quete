<?php
session_start();
require_once 'core/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

// Проверка CSRF-токена
$csrfToken = $_POST['csrf_token'] ?? '';
if (!verifyCsrfToken($csrfToken)) {
    http_response_code(403);
    exit('Неверный CSRF-токен');
}

$userId = $_SESSION['user_id'];
$currentLobby = getLobbyByUserId($userId);

if ($currentLobby) {
    if ($currentLobby['is_active']) {
        // Если игра началась, выйти может только создатель (удалив лобби)
        if ($currentLobby['host_id'] == $userId) {
            deleteLobby($currentLobby['id']);
        } else {
            // Игроки не могут выйти во время игры
            header('Location: game.php?lobby_id=' . $currentLobby['id']);
            exit;
        }
    } else {
        // Если игра еще не началась
        if ($currentLobby['host_id'] == $userId) {
            deleteLobby($currentLobby['id']);
        } else {
            leaveLobby($currentLobby['id'], $userId);
        }
    }
}

header('Location: hub.php');
exit;
