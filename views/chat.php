<!-- Шаблон игрового чата для мгновенного общения между игроками. -->
<div class="chat-container">
    <div class="chat-messages" id="chat-messages">
        <!-- Сообщения будут динамически загружаться сюда -->
    </div>
    <form class="chat-form" id="chat-form" onsubmit="handleChatSubmit(event)">
        <input type="text" id="chat-input" placeholder="Введите сообщение..." required autocomplete="off">
        <button type="submit">Отправить</button>
    </form>
</div>

<script nonce="<?php echo CSP_NONCE; ?>">
// Функция: handleChatSubmit
// Обывателю: Срабатывает при отправке сообщения (нажатии кнопки "Отправить" или Enter). 
// Передаёт набранный текст WebSocket-клиенту для отправки на сервер и очищает поле ввода.
function handleChatSubmit(event) {
    event.preventDefault();
    const input = document.getElementById('chat-input');
    const message = input.value.trim();
    
    if (message && socketClient && socketClient.isConnected()) {
        socketClient.sendChat(message, '<?php echo addslashes($_SESSION['username'] ?? 'Игрок'); ?>');
        input.value = '';
    }
}

// Функция: loadChatHistory
// Обывателю: Делает фоновый запрос к серверу и загружает историю переписки в этой комнате, 
// чтобы игрок видел предыдущие сообщения при входе.
function loadChatHistory() {
    const lobbyId = typeof LOBBY_ID !== 'undefined' ? LOBBY_ID : null;
    if (!lobbyId) return;
    
    fetch(`ajax/get_chat.php?lobby_id=${lobbyId}`)
        .then(r => r.json())
        .then(messages => {
            const chatBox = document.getElementById('chat-messages');
            if (!chatBox) return;
            
            chatBox.innerHTML = '';
            messages.forEach(msg => {
                appendChatMessageToBox(msg);
            });
        })
        .catch(e => console.error('Error loading chat:', e));
}

// Функция: appendChatMessageToBox
// Обывателю: Безопасно (с защитой от вредоносного кода) оформляет сообщение и добавляет его 
// в конец списка сообщений на экране, после чего прокручивает чат вниз.
function appendChatMessageToBox(msg) {
    const chatBox = document.getElementById('chat-messages');
    if (!chatBox) return;
    
    const div = document.createElement('div');
    div.className = 'chat-msg';
    const safeName = GameWebSocketClient.escapeHtml(msg.username || 'Игрок ' + msg.user_id);
    const safeMsg = GameWebSocketClient.escapeHtml(msg.message);
    div.innerHTML = `<strong>${safeName}:</strong> ${safeMsg}`;
    chatBox.appendChild(div);
    chatBox.scrollTop = chatBox.scrollHeight;
}

// Переопределяем appendChatMessage в game.php и lobby.php если нужно, 
// но здесь мы просто добавляем общую функцию
if (typeof appendChatMessage === 'undefined') {
    window.appendChatMessage = appendChatMessageToBox;
}

document.addEventListener('DOMContentLoaded', loadChatHistory);
</script>

