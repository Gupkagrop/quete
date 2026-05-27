<?php
/**
 * Кастомная страница ошибки 403 - Доступ запрещен.
 */
http_response_code(403);
$isErrorPage = true;
include 'views/header.php';
?>

<main class="error-page">
    <div class="error-container">
        <div class="error-code-glow">403</div>
        <img src="assets/img/icon_lock.png" alt="Замок" class="error-retro-img state-img" width="120" height="120">
        <h1 class="error-title">Доступ ограничен!</h1>
        <p class="error-description">У вас нет прав для просмотра этой секретной зоны. Вставьте еще монетку или вернитесь на главную.</p>
        <div class="error-actions">
            <a href="index.php" class="btn-pill" id="btn-home">Вставить монетку (На главную)</a>
            <button onclick="if(document.referrer) { history.back(); } else { window.location.href='index.php'; }" class="btn-pill btn-secondary" id="btn-back">Вернуться назад</button>
        </div>
    </div>
</main>

<?php include 'views/footer.php'; ?>
