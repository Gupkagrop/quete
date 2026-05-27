<?php
/**
 * Скрипт выхода из комнаты ожидания или ее удаления создателем.
 */

session_start();
require_once 'core/db.php';

// Проверка авторизации: если пользователь не вошел в систему, отправляем его на страницу авторизации
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Запрещаем прямой доступ к скрипту через адресную строку (разрешен только метод отправки POST)
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

// Защита от CSRF-атак: проверяем, что запрос отправлен с нашего сайта
$csrfToken = $_POST['csrf_token'] ?? '';
if (!verifyCsrfToken($csrfToken)) {
    http_response_code(403);
    exit('Неверный CSRF-токен');
}

$userId = $_SESSION['user_id'];
$currentLobby = getLobbyByUserId($userId);

// Логика выхода: если пользователь действительно состоит в лобби
if ($currentLobby) {
    if ($currentLobby['is_active']) {
        // Если игра уже началась (активна):
        // 1. Создатель лобби (хост) при выходе удаляет всю игровую комнату
        if ($currentLobby['host_id'] == $userId) {
            deleteLobby($currentLobby['id']);
        } else {
            // 2. Обычные игроки не могут просто так выйти во время активной игры (возвращаем их обратно на игровой экран)
            header('Location: game.php?lobby_id=' . $currentLobby['id']);
            exit;
        }
    } else {
        // Если игра еще не началась (идет стадия ожидания в лобби):
        // 1. Создатель комнаты при выходе удаляет лобби полностью
        if ($currentLobby['host_id'] == $userId) {
            deleteLobby($currentLobby['id']);
        } else {
            // 2. Обычный игрок просто покидает комнату
            leaveLobby($currentLobby['id'], $userId);
        }
    }
}

// После выхода отправляем пользователя в главное меню (хаб)
header('Location: hub.php');
exit;

