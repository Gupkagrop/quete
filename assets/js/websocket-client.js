/**
 * WebSocket клиент для игры Куэте
 */
class GameWebSocketClient {
    constructor(lobbyId, userId, options = {}) {
        this.lobbyId = lobbyId;
        this.userId = userId;
        this.ticket = options.ticket || ''; // Одноразовый билет авторизации
        this.host = options.host || window.location.hostname;
        this.port = options.port || 8888;
        this.protocol = options.protocol || (window.location.protocol === 'https:' ? 'wss' : 'ws');
        this.onMessageCallback = options.onMessage || null;
        this.onConnectCallback = options.onConnect || null;
        this.onDisconnectCallback = options.onDisconnect || null;
        
        this.socket = null;
        this.reconnectAttempts = 0;
        this.maxReconnectAttempts = 5;
        this.reconnectInterval = 3000;
        this.manuallyClosed = false;
    }

    /**
     * Подключиться к WebSocket серверу
     */
    connect() {
        this.updateStatusIndicator('connecting');
        let url = `${this.protocol}://${this.host}:${this.port}`;
        // Добавляем билет авторизации в GET параметры WebSocket рукопожатия
        if (this.ticket) {
            url += `?ticket=${encodeURIComponent(this.ticket)}`;
        }
        console.log(`Connecting to WebSocket: ${url}`);
        
        try {
            this.socket = new WebSocket(url);
            this.manuallyClosed = false;

            this.socket.onopen = (event) => {
                console.log('WebSocket connected');
                this.updateStatusIndicator('online');
                this.reconnectAttempts = 0;
                this.joinLobby();
                if (this.onConnectCallback) this.onConnectCallback(event);
            };

            this.socket.onmessage = (event) => {
                try {
                    const data = JSON.parse(event.data);
                    if (this.onMessageCallback) this.onMessageCallback(data);
                } catch (e) {
                    console.error('Error parsing WebSocket message:', e);
                }
            };

            this.socket.onclose = (event) => {
                console.log('WebSocket closed:', event.code, event.reason);
                this.updateStatusIndicator('offline');
                if (!this.manuallyClosed) {
                    this.attemptReconnect();
                }
                if (this.onDisconnectCallback) this.onDisconnectCallback(event);
            };

            this.socket.onerror = (error) => {
                console.error('WebSocket error:', error);
            };

        } catch (e) {
            console.error('WebSocket connection failed:', e);
            this.attemptReconnect();
        }
    }

    /**
     * Попытка переподключения
     */
    attemptReconnect() {
        if (this.reconnectAttempts < this.maxReconnectAttempts) {
            this.reconnectAttempts++;
            console.log(`Attempting to reconnect (${this.reconnectAttempts}/${this.maxReconnectAttempts}) in ${this.reconnectInterval}ms...`);
            setTimeout(() => this.connect(), this.reconnectInterval);
        } else {
            console.error('Max reconnect attempts reached');
        }
    }

    /**
     * Присоединиться к лобби
     */
    joinLobby() {
        this.send({
            action: 'join',
            lobby_id: this.lobbyId,
            user_id: this.userId
        });
    }

    /**
     * Отправить действие
     */
    sendAction(actionType, data = {}) {
        this.send({
            action: 'action',
            lobby_id: this.lobbyId,
            user_id: this.userId,
            action_type: actionType,
            data: data
        });
    }

    /**
     * Отправить уведомление об обновлении
     */
    notifyUpdate(data = {}) {
        this.send({
            action: 'update',
            lobby_id: this.lobbyId,
            user_id: this.userId,
            data: data
        });
    }

    /**
     * Низкоуровневая отправка сообщения
     */
    send(data) {
        if (this.socket && this.socket.readyState === WebSocket.OPEN) {
            this.socket.send(JSON.stringify(data));
        } else {
            console.warn('Cannot send message: WebSocket is not open');
        }
    }

    /**
     * Закрыть соединение
     */
    disconnect() {
        this.manuallyClosed = true;
        if (this.socket) {
            this.socket.close();
        }
    }

    /**
     * Послать сообщение в чат
     */
    sendChat(message, username = '') {
        this.send({
            action: 'chat',
            lobby_id: this.lobbyId,
            user_id: this.userId,
            username: username,
            message: message
        });
    }

    /**
     * Экранирование HTML для защиты от XSS
     */
    static escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    /**
     * Проверяет, подключен ли сокет в данный момент
     */
    isConnected() {
        return this.socket && this.socket.readyState === WebSocket.OPEN;
    }

    /**
     * Обновить визуальный индикатор статуса
     */
    updateStatusIndicator(status) {
        if (status === 'online') {
            console.log('%c[WebSocket Server Status]: ONLINE / CONNECTED', 'color: #4dff4d; font-weight: bold; font-size: 11px;');
        } else if (status === 'connecting') {
            console.log('%c[WebSocket Server Status]: CONNECTING...', 'color: #ffff4d; font-weight: bold; font-size: 11px;');
        } else {
            console.warn('%c[WebSocket Server Status]: OFFLINE / DISCONNECTED', 'color: #ff4d4d; font-weight: bold; font-size: 11px;');
        }
    }
}
