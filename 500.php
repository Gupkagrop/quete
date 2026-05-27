<?php
/**
 * Кастомная страница ошибки 500 - Внутренняя ошибка сервера.
 */
http_response_code(500);
$isErrorPage = true;
include 'views/header.php';
?>

<main class="error-page">
    <div class="error-container">
        <div class="error-code-glow">500</div>
        <img src="assets/img/brain_gears.png" alt="Шестеренки мозга" class="error-retro-img state-img" width="120" height="120">
        <h1 class="error-title">Внутренний сбой системы</h1>
        <p class="error-description">Игровой сервер столкнулся с непредвиденной аномалией. Мы уже отправляем спасательную бригаду.</p>
        <div class="error-actions">
            <a href="index.php" class="btn-pill" id="btn-home">Вставить монетку (На главную)</a>
            <button onclick="if(document.referrer) { history.back(); } else { window.location.href='index.php'; }" class="btn-pill btn-secondary" id="btn-back">Вернуться назад</button>
        </div>
    </div>
</main>

<?php include 'views/footer.php'; ?>
