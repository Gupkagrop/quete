<?php
$showLeaveButton = $showLeaveButton ?? false;
$pdo = getPDO();
$stmt = $pdo->query('SELECT username, wins_count, correct_answers, total_answers FROM users ORDER BY wins_count DESC, correct_answers DESC LIMIT 5');
$leaderboard = $stmt->fetchAll();
?>
<div class="auth-popup-overlay" id="stats-overlay">
    <div class="auth-popup-card">
        <button class="auth-popup-close" onclick="toggleStatsPopup()" title="Закрыть">×</button>
        <h2 class="auth-popup-title">Статистика</h2>
        
        <div class="user-popup-row"><span>Пользователь:</span><strong><?php echo htmlspecialchars($user['username']); ?></strong></div>
        <div class="user-popup-row"><span>Победы:</span><strong><?php echo (int) ($user['wins_count'] ?? 0); ?></strong></div>
        <div class="user-popup-row"><span>Всего ответов:</span><strong><?php echo (int) ($user['total_answers'] ?? 0); ?></strong></div>
        <div class="user-popup-row"><span>Правильных ответов:</span><strong><?php echo (int) ($user['correct_answers'] ?? 0); ?></strong></div>
        <div class="user-popup-row"><span>Последний счёт:</span><strong><?php echo (int) ($user['last_game_score'] ?? 0); ?></strong></div>

        <div class="leaderboard-block">
            <div style="font-weight: 800; margin-bottom: 15px; text-align: center; color: var(--accent-orange);">Лидерборд</div>
            <?php if ($leaderboard): ?>
                <?php foreach ($leaderboard as $index => $leader): ?>
                    <div class="user-popup-row" style="display: flex; justify-content: space-between; gap: 12px;">
                        <span style="color: var(--accent-orange); font-weight: 700;">#<?php echo $index + 1; ?></span>
                        <strong style="flex: 1; text-align: left;"><?php echo htmlspecialchars($leader['username']); ?></strong>
                        <span style="min-width: 110px; text-align: right;"><?php echo (int) $leader['wins_count']; ?> побед</span>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div style="color: rgba(255,255,255,0.6); text-align: center;">Нет данных для лидера.</div>
            <?php endif; ?>
        </div>

        <?php if ($showLeaveButton): ?>
            <form action="leave_lobby.php" method="POST" id="leave-lobby-form" style="display:none;">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(getCsrfToken()); ?>">
            </form>
            <button class="btn-game" onclick="handleLeaveLobby()">Выйти из лобби</button>
            <script>
            function handleLeaveLobby() {
                if (typeof IS_HOST !== 'undefined' && IS_HOST && typeof socketClient !== 'undefined' && socketClient && socketClient.isConnected()) {
                    socketClient.sendAction('lobby_deleted');
                }
                setTimeout(() => {
                    document.getElementById('leave-lobby-form').submit();
                }, 200);
            }
            </script>
        <?php else: ?>
            <div style="display: flex; gap: 10px; width: 100%;">
                <button class="btn-game" onclick="window.location.href='logout.php'" style="flex: 1; padding: 10px 0;">Выйти</button>
                <form action="delete_account.php" method="POST" style="flex: 1; margin: 0;" onsubmit="return confirm('Вы уверены, что хотите навсегда удалить свой аккаунт и все данные? Это действие необратимо!');">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(getCsrfToken()); ?>">
                    <button type="submit" class="btn-game" style="background: rgba(255, 60, 60, 0.15); border-color: #ff3c3c; color: #ff3c3c; width: 100%; padding: 10px 0;">Удалить</button>
                </form>
            </div>
        <?php endif; ?>
    </div>
</div>
<script>
function toggleStatsPopup() {
    const overlay = document.getElementById('stats-overlay');
    if (overlay) {
        overlay.classList.toggle('active');
    }
}

document.addEventListener('click', function(event) {
    const overlay = document.getElementById('stats-overlay');
    const icon = document.querySelector('.user-icon');
    if (!overlay || !icon) return;
    if (overlay.classList.contains('active') && (event.target === overlay) && !icon.contains(event.target)) {
        overlay.classList.remove('active');
    }
});
</script>
