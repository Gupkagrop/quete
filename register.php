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
            <h2 class="auth-title">Регистрация</h2>
            
            <form class="auth-form" method="POST" action="core/auth_handler.php">
                <input type="hidden" name="action" value="register"> <!-- Скрытое поле для указания действия -->
                <input type="hidden" name="csrf_token" value="<?php echo getCsrfToken(); ?>">
                
                <input type="text" class="auth-input" name="username" placeholder="Имя" required>
                <input type="email" class="auth-input" name="email" placeholder="Email" required>
                <input type="password" class="auth-input" name="password" placeholder="Пароль" required>
                <input type="password" class="auth-input" name="password_confirm" placeholder="Повторить пароль" required>
                
                <div class="auth-checkbox-group" style="margin-top: 10px; margin-bottom: 20px; display: flex; align-items: flex-start; gap: 10px; text-align: left;">
                    <input type="checkbox" id="legal_consent" name="legal_consent" required style="margin-top: 4px;">
                    <label for="legal_consent" style="font-size: 12px; color: rgba(255,255,255,0.8); line-height: 1.4;">
                        Я подтверждаю, что мне исполнилось 18 лет, даю согласие на обработку персональных данных и принимаю условия <a href="terms.php" target="_blank" style="color: var(--accent-orange); text-decoration: underline;">Пользовательского соглашения</a> и <a href="privacy.php" target="_blank" style="color: var(--accent-orange); text-decoration: underline;">Политики конфиденциальности</a>.
                    </label>
                </div>                
                <button type="submit" class="auth-btn">Зарегистрироваться</button>
            </form>
            
            <div class="auth-divider">
                <span>ИЛИ</span>
            </div>
            
            <a href="login.php" class="auth-btn auth-btn-secondary">Войти в аккаунт</a>
        </div>
    </div>
</div>

<?php
include 'views/footer.php';
?>
