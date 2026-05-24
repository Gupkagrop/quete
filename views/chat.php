<div class="chat-container">
    <div class="chat-messages" id="chat-messages">
        <!-- Messages will be loaded here via WebSocket -->
    </div>
    <form class="chat-form" id="chat-form" onsubmit="handleChatSubmit(event)">
        <input type="text" id="chat-input" placeholder="Введите сообщение..." required autocomplete="off">
        <button type="submit">Отправить</button>
    </form>
</div>

<script nonce="<?php echo CSP_NONCE; ?>">
function handleChatSubmit(event) {
    event.preventDefault();
    const input = document.getElementById('chat-input');
    const message = input.value.trim();
    
    if (message && socketClient && socketClient.isConnected()) {
        socketClient.sendChat(message, '<?php echo addslashes($_SESSION['username'] ?? 'Игрок'); ?>');
        input.value = '';
    }
}

// Загрузка истории чата
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
