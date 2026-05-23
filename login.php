<?php
session_start();
require_once 'core/db.php';

if (isset($_SESSION['user_id'])) {
    header('Location: hub.php');
    exit;
}
$flash_error = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_error']);
include 'views/header.php';
?>
<link rel="stylesheet" href="assets/css/auth.css">
<?php if ($flash_error): ?>
    <div class="error-msg"><?php echo htmlspecialchars($flash_error); ?></div>
<?php endif; ?>

<div class="auth-container">
    <div class="auth-wrapper">
        <div class="auth-card">
            <a href="index.php" class="auth-back">←</a>
            <img src="assets/img/login_insert_coin.jpeg" alt="Insert Coin" class="retro-insert-coin">
            <h2 class="auth-title">Вход</h2>
            
            <form class="auth-form" method="POST" action="core/auth_handler.php">
                <input type="hidden" name="action" value="login"> <!-- Скрытое поле для указания действия -->
                <input type="hidden" name="csrf_token" value="<?php echo getCsrfToken(); ?>">
                
                <input type="text" class="auth-input" name="identity" placeholder="Никнейм или Email" required>
                <input type="password" class="auth-input" name="password" placeholder="Пароль" required>
                
                <button type="submit" class="auth-btn">Войти</button>
            </form>
            
            <div class="auth-divider">
                <span>ИЛИ</span>
            </div>
            
            <a href="register.php" class="auth-btn auth-btn-secondary">Завести аккаунт</a>
        </div>
    </div>
</div>

<?php
include 'views/footer.php';
?>
