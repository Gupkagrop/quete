<?php include 'views/header.php'; ?>

<!-- Разметка структурированных данных JSON-LD (Schema.org) для SEO -->
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "WebApplication",
  "name": "Куэте",
  "url": "https://quete.ru/",
  "description": "Куэте: онлайн-викторина с ИИ-генерацией вопросов. Сыграй в соло или с друзьями в ламповой ретро-атмосфере!",
  "applicationCategory": "GameApplication",
  "operatingSystem": "All",
  "genre": "Quiz, Multiplayer Game",
  "browserRequirements": "Requires HTML5, WebSockets, JavaScript",
  "featureList": [
    "Генерация вопросов с помощью ИИ (Groq API / Llama)",
    "Многопользовательские лобби до 8 человек в реальном времени через WebSockets",
    "Уникальный игровой процесс: придумывай фейковые ответы и обманывай соперников",
    "Соло-режим с быстрыми раундами по заданным темам",
    "Премиальный ретро-стиль интерфейса"
  ],
  "author": {
    "@type": "Organization",
    "name": "Gupkagrop"
  }
}
</script>

<main>
    <!-- Первая секция -->
    <section class="hero">
        <div class="hero-left">
            <h1>Онлайн квиз 🎲</h1>
            <div class="sub-pill">
                <p>Ответь на вопрос правильно и обмани своих друзей, чтобы выиграть</p>
            </div>
            <div class="hero-btns">
                <a href="login.php" class="btn-pill">Войти</a>
                <a href="register.php" class="btn-pill">Зарегистрироваться</a>
            </div>
        </div>

        <div class="hero-right">
            <!-- Декоративная карточка с вопросом -->
            <div class="quiz-card-decor">
                <div class="q-text">Самое бесстрашное животное?</div>
                <div class="a-grid">
                    <span>Медоед</span> <span>Росомаха</span>
                    <span>Чихуахуа</span> <span>Бобер</span>
                </div>
            </div>
            <!-- Джойстик -->
            <img src="assets/img/joystick.png" alt="Joystick" class="joystick-icon">
        </div>
    </section>

    <!-- Оранжевая секция -->
    <section class="features-section">
        <div class="features-grid">
            <div class="feature-item">Создай лобби или присоединись к нему</div>
            <div class="feature-item">Начни игру и выбери тему</div>
            <div class="feature-item">Предложи свой фейковый вариант ответа и обмани игрока</div>
        </div>

        <div class="slider-area">
            <div class="arrow-btn prev">←</div>
            <div class="carousel">
                <img src="assets/img/slide1.png" alt="Slide 1" class="slide">
                <img src="assets/img/slide2.png" alt="Slide 2" class="slide">
                <img src="assets/img/slide3.png" alt="Slide 3" class="slide">
                <img src="assets/img/slide4.png" alt="Slide 4" class="slide">
                <img src="assets/img/slide5.png" alt="Slide 5" class="slide">
            </div>
            <div class="arrow-btn next">→</div>
        </div>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const slides = document.querySelectorAll('.carousel .slide');
                let current = 0;

                function show(index) {
                    slides.forEach((s, i) => {
                        s.className = 'slide';
                        if (i === index) {
                            s.classList.add('active');
                        } else if (i === (index - 1 + slides.length) % slides.length) {
                            s.classList.add('left');
                        } else if (i === (index + 1) % slides.length) {
                            s.classList.add('right');
                        }
                    });
                }

                function next() { current = (current + 1) % slides.length; show(current); }
                function prev() { current = (current - 1 + slides.length) % slides.length; show(current); }

                document.querySelector('.arrow-btn.next').addEventListener('click', next);
                document.querySelector('.arrow-btn.prev').addEventListener('click', prev);

                show(current);
                setInterval(next, 10000);
            });
        </script>
    </section>
</main>

<?php include 'views/footer.php'; ?>