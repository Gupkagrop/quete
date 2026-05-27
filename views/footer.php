<!-- Общий подвал (footer) страниц с баннером согласия на обработку файлов Cookie. -->
<footer class="site-footer">
    <div class="footer-inner">

        <!-- Лого слева: пиксельный маркер + название + акцент -->
        <a href="index.php" class="footer-logo">
            <span class="logo-pixel"></span>
            Куэте<span class="logo-accent">:</span>
        </a>

        <!-- Слоган + маленький ретро-джойстик -->
        <div class="footer-center">
            <img src="assets/img/joystick.png"
                 alt="joystick"
                 class="footer-retro-icon"
                 aria-hidden="true"
                 width="513"
                 height="474">
            <p class="footer-tagline">Онлайн-квиз для компаний</p>
        </div>

        <!-- Иконки соцсетей справа -->
        <div class="footer-socials">

            <!-- Telegram -->
            <a href="https://t.me/Gupkagrop" target="_blank" rel="noopener" class="footer-icon" title="Telegram">
                <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 0C5.373 0 0 5.373 0 12s5.373 12 12 12 12-5.373 12-12S18.627 0 12 0zm5.894 8.221-1.97 9.28c-.145.658-.537.818-1.084.508l-3-2.21-1.447 1.394c-.16.16-.295.295-.605.295l.213-3.053 5.56-5.023c.242-.213-.054-.333-.373-.12L7.19 13.6l-2.963-.924c-.643-.204-.657-.643.136-.953l11.57-4.461c.537-.194 1.006.131.961.959z"/></svg>
            </a>

            <!-- ВКонтакте -->
            <a href="https://vk.com/gupkagrop" target="_blank" rel="noopener" class="footer-icon" title="ВКонтакте">
                <svg viewBox="0 0 24 24" fill="currentColor"><path d="M15.684 0H8.316C1.592 0 0 1.592 0 8.316v7.368C0 22.408 1.592 24 8.316 24h7.368C22.408 24 24 22.408 24 15.684V8.316C24 1.592 22.408 0 15.684 0zm3.692 17.123h-1.744c-.66 0-.862-.523-2.049-1.713-1.033-1-1.49-1.135-1.744-1.135-.356 0-.458.102-.458.593v1.575c0 .424-.135.678-1.253.678-1.846 0-3.896-1.118-5.335-3.202C4.624 10.857 4 8.408 4 7.932c0-.254.102-.491.593-.491h1.744c.44 0 .61.203.78.677.863 2.49 2.303 4.675 2.896 4.675.22 0 .322-.102.322-.66V9.721c-.068-1.186-.695-1.287-.695-1.71 0-.204.17-.407.44-.407h2.744c.373 0 .508.203.508.643v3.473c0 .372.17.508.271.508.22 0 .407-.136.813-.542 1.254-1.406 2.151-3.574 2.151-3.574.119-.254.322-.491.762-.491h1.744c.525 0 .644.271.525.643-.22 1.017-2.354 4.031-2.354 4.031-.186.305-.254.44 0 .78.186.254.796.779 1.203 1.253.745.847 1.32 1.558 1.473 2.05.17.49-.085.744-.576.744z"/></svg>
            </a>

            <!-- Email -->
            <a href="mailto:denislopuhov3@gmail.com" class="footer-icon" title="Написать на почту">
                <svg viewBox="0 0 24 24" fill="currentColor"><path d="M20 4H4C2.9 4 2.01 4.9 2.01 6L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4-8 5-8-5V6l8 5 8-5v2z"/></svg>
            </a>

        </div>
    </div>

    <!-- Разделитель -->
    <div class="footer-divider"></div>

    <!-- Нижняя строка подвала -->
    <div class="footer-bottom">
        <div class="footer-legal">
            <a href="terms.php">Соглашение</a>
            <span class="footer-dot">·</span>
            <a href="privacy.php">Конфиденциальность</a>
        </div>
        <p class="footer-copy">&copy; 2026 Куэте &mdash; Все права защищены. <span class="footer-age">18+</span></p>
    </div>
</footer>

<!-- === Cookie Banner === -->
<!-- Баннер Cookie: всплывающее уведомление для согласия пользователя с использованием cookie -->
<div id="cookie-banner" class="cookie-banner">
    <div class="cookie-text">
        🍪 Мы используем <b>cookie</b> для работы сайта и авторизации.
        Продолжая, вы соглашаетесь с <a href="privacy.php">Политикой конфиденциальности</a>.
    </div>
    <button id="accept-cookies" class="cookie-accept-btn">Понятно</button>
</div>

<!-- Стили оформления подвала сайта и адаптивная верстка под мобильные экраны -->
<style nonce="<?php echo CSP_NONCE; ?>">
.site-footer {
    background: var(--section-orange);
    font-family: 'Inter', sans-serif;
    padding: 36px 48px 0;
}

/* --- Верхняя строка: лого / центр / иконки --- */
.footer-inner {
    display: flex;
    align-items: center;
    justify-content: space-between;
    max-width: 1100px;
    margin: 0 auto;
    padding-bottom: 28px;
    gap: 24px;
}

/* ---- Общий стиль логотипа (header + footer) ---- */
.logo-box,
.footer-logo {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: #000;
    color: var(--accent-orange);
    text-decoration: none;
    padding: 8px 18px 8px 12px;
    border-radius: 10px;
    font: 900 20px 'Inter', sans-serif;
    letter-spacing: -0.5px;
    flex-shrink: 0;
    transition: opacity 0.2s ease, transform 0.2s ease;
    position: relative;
    overflow: hidden;
}
.logo-box:hover,
.footer-logo:hover {
    opacity: 0.88;
    transform: translateY(-1px);
}

/* Пиксельный квадрат-маркер перед названием */
.logo-pixel {
    display: inline-block;
    width: 10px;
    height: 10px;
    background: var(--accent-orange);
    border-radius: 2px;
    flex-shrink: 0;
    /* Имитация пиксельности через box-shadow */
    box-shadow:
        -3px -3px 0 0 rgba(255,255,255,0.15),
         3px  3px 0 0 rgba(0,0,0,0.35);
}

/* Акцентное двоеточие */
.logo-accent {
    color: rgba(255,255,255,0.5);
    font-weight: 400;
    margin-left: 1px;
}

/* --- Центральный блок: джойстик + слоган --- */
.footer-center {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    flex: 1;
}

/* Маленькая ретро-деталь — джойстик */
.footer-retro-icon {
    width: 28px;
    height: 28px;
    image-rendering: pixelated;
    opacity: 0.75;
    filter: drop-shadow(1px 2px 0 rgba(0,0,0,0.3));
    animation: footer-icon-bob 3s ease-in-out infinite;
    flex-shrink: 0;
}
@keyframes footer-icon-bob {
    0%, 100% { transform: translateY(0) rotate(-4deg); }
    50%       { transform: translateY(-3px) rotate(4deg); }
}

/* Слоган */
.footer-tagline {
    font: 500 14px 'Inter', sans-serif;
    color: rgba(255,255,255,0.6);
    letter-spacing: 0.2px;
}

/* Иконки соцсетей */
.footer-socials {
    display: flex;
    align-items: center;
    gap: 6px;
    flex-shrink: 0;
}

.footer-icon {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 38px;
    height: 38px;
    border-radius: 10px;
    background: rgba(0,0,0,0.18);
    color: rgba(255,255,255,0.85);
    text-decoration: none;
    transition: background 0.18s ease, color 0.18s ease, transform 0.18s ease;
}
.footer-icon svg {
    width: 18px;
    height: 18px;
    flex-shrink: 0;
}
.footer-icon:hover {
    background: rgba(0,0,0,0.38);
    color: #fff;
    transform: translateY(-2px);
}

/* --- Разделитель --- */
.footer-divider {
    height: 1px;
    background: rgba(255,255,255,0.18);
    max-width: 1100px;
    margin: 0 auto;
}

/* --- Нижняя строка --- */
.footer-bottom {
    display: flex;
    align-items: center;
    justify-content: space-between;
    max-width: 1100px;
    margin: 0 auto;
    padding: 14px 0 20px;
    gap: 16px;
    flex-wrap: wrap;
}

/* Юридические ссылки */
.footer-legal {
    display: flex;
    align-items: center;
    gap: 8px;
}
.footer-legal a {
    font: 500 12px 'Inter', sans-serif;
    color: rgba(255,255,255,0.55);
    text-decoration: none;
    transition: color 0.15s;
}
.footer-legal a:hover { color: rgba(255,255,255,0.9); }
.footer-dot {
    color: rgba(255,255,255,0.3);
    font-size: 14px;
    line-height: 1;
}

/* Копирайт + 18+ */
.footer-copy {
    font: 500 12px 'Inter', sans-serif;
    color: rgba(255,255,255,0.45);
    display: flex;
    align-items: center;
    gap: 8px;
}
.footer-age {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 28px;
    height: 28px;
    border-radius: 50%;
    border: 1.5px solid rgba(255,255,255,0.35);
    font: 700 11px 'Inter', sans-serif;
    color: rgba(255,255,255,0.6);
    flex-shrink: 0;
}

/* ================================================================
   COOKIE BANNER
   ================================================================ */
.cookie-banner {
    display: none;
    position: fixed;
    bottom: 0; left: 0;
    width: 100%;
    background: #1c1c2e;
    border-top: 2px solid var(--accent-orange);
    padding: 14px 32px;
    z-index: 1000;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 12px;
    box-sizing: border-box;
    box-shadow: 0 -6px 24px rgba(0,0,0,0.45);
}
.cookie-text {
    color: rgba(255,255,255,0.8);
    font: 400 14px 'Inter', sans-serif;
    line-height: 1.55;
    max-width: 820px;
}
.cookie-text a {
    color: var(--accent-orange);
    text-decoration: underline;
}
.cookie-accept-btn {
    background: var(--accent-orange);
    color: #000;
    border: none;
    padding: 9px 26px;
    border-radius: 30px;
    font: 800 13px 'Inter', sans-serif;
    cursor: pointer;
    transition: transform 0.15s, opacity 0.15s;
    white-space: nowrap;
}
.cookie-accept-btn:hover { transform: scale(1.04); opacity: 0.9; }

/* ================================================================
   АДАПТИВНОСТЬ FOOTER
   ================================================================ */
@media (max-width: 768px) {
    .site-footer { padding: 30px 24px 0; }

    .footer-inner {
        flex-direction: column;
        align-items: flex-start;
        gap: 18px;
        padding-bottom: 22px;
    }
    .footer-center {
        justify-content: flex-start;
    }
    .footer-bottom {
        flex-direction: column;
        align-items: flex-start;
        gap: 10px;
        padding-bottom: 18px;
    }
}

@media (max-width: 480px) {
    .site-footer { padding: 24px 16px 0; }
    .footer-icon { width: 36px; height: 36px; }
    .footer-icon svg { width: 16px; height: 16px; }
    .footer-logo,
    .logo-box { font-size: 16px; padding: 7px 14px 7px 10px; }
    .footer-retro-icon { width: 22px; height: 22px; }
}
</style>

<!-- Скрипт проверки согласия с Cookies:
     Если пользователь еще не нажимал кнопку "Понятно" (нет записи в памяти браузера),
     то показываем баннер, иначе скрываем его. При клике сохраняем выбор в память. -->
<script nonce="<?php echo CSP_NONCE; ?>">
    document.addEventListener('DOMContentLoaded', function () {
        // Показываем баннер только если пользователь ещё не принял cookie
        if (!localStorage.getItem('cookie_accepted')) {
            document.getElementById('cookie-banner').style.display = 'flex';
        }
        document.getElementById('accept-cookies').addEventListener('click', function () {
            localStorage.setItem('cookie_accepted', '1');
            document.getElementById('cookie-banner').style.display = 'none';
        });
    });
</script>

</body>
</html>