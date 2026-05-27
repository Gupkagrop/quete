<?php
/**
 * Кастомная страница ошибки 404 - Страница не найдена.
 */
http_response_code(404);
$isErrorPage = true;
include 'views/header.php';
?>

<main class="error-page">
    <div class="error-container">
        <div class="error-code-glow">404</div>
        <img src="assets/img/question_mark.png" alt="Знак вопроса" class="error-retro-img state-img" width="120" height="120">
        <h1 class="error-title">Упс! Страница потерялась...</h1>
        <p class="error-description">Похоже, эта страница была стерта из памяти приставки или никогда не существовала.</p>
        <div class="error-actions">
            <a href="index.php" class="btn-pill" id="btn-home">Вставить монетку (На главную)</a>
            <button onclick="if(document.referrer) { history.back(); } else { window.location.href='index.php'; }" class="btn-pill btn-secondary" id="btn-back">Вернуться назад</button>
        </div>
    </div>
</main>

<?php include 'views/footer.php'; ?>
