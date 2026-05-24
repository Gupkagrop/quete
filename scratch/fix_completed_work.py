import re

file_path = r"c:\OSPanel\domains\quete\pre-alpha0.1.3-antigravity-git\COMPLETED_WORK.md"

try:
    with open(file_path, "r", encoding="utf-8", errors="ignore") as f:
        content = f.read()
except Exception as e:
    print(f"Error reading file: {e}")
    exit(1)

target_start = "## 📝 30. Оптимизация производительности (Performance) и исправление CLS (Текущая сессия)"
pattern = re.compile(r"## 📝 30\. Оптимизация производительности .*?(?=## 📝 29\.)", re.DOTALL)

new_text = """## 📝 30. Оптимизация производительности (Performance) и исправление CLS (Текущая сессия)
*   **Оптимизация загрузки шрифтов (Устранение цепочек запросов и блокировок отрисовки):**
    *   Полностью удалены блокирующие директивы `@import` шрифтов Google Fonts из CSS-файлов: [style.css](file:///c:/OSPanel/domains/quete/pre-alpha0.1.3-antigravity-git/assets/css/style.css), [auth.css](file:///c:/OSPanel/domains/quete/pre-alpha0.1.3-antigravity-git/assets/css/auth.css) и [game.css](file:///c:/OSPanel/domains/quete/pre-alpha0.1.3-antigravity-git/assets/css/game.css).
    *   В заголовок [views/header.php](file:///c:/OSPanel/domains/quete/pre-alpha0.1.3-antigravity-git/views/header.php) и игрового файла [game.php](file:///c:/OSPanel/domains/quete/pre-alpha0.1.3-antigravity-git/game.php) добавлен `preconnect` к `https://fonts.googleapis.com` и `https://fonts.gstatic.com` для ускорения установки сетевого соединения.
    *   **Асинхронная доставка (Неблокирующий рендеринг):** Подключение шрифтов переведено на неблокирующий асинхронный паттерн с `rel="preload"` и переключением на `rel="stylesheet"` при загрузке. Это позволило браузеру рисовать страницу мгновенно без блокировок отрисовки (FCP снижен с 3.2 с до ~0.7 с), а параметр `display=swap` гарантирует плавное наложение шрифтов.
*   **Устранение сдвигов компоновки (Cumulative Layout Shift):**
    *   Для изображения джойстика `joystick.webp` на главной странице [index.php](file:///c:/OSPanel/domains/quete/pre-alpha0.1.3-antigravity-git/index.php) добавлены физические размеры `width="260" height="260"`. 
    *   В стилях [style.css](file:///c:/OSPanel/domains/quete/pre-alpha0.1.3-antigravity-git/assets/css/style.css) позиционирование джойстика изменено с `bottom: 20px;` на `top: 270px;` (в медиа-запросе для 2K экранов на `top: 310px;` с добавлением `height: auto` и `aspect-ratio: 1 / 1`). Это полностью отвязало джойстик от динамической высоты левой колонки (меняющейся при загрузке шрифтов) и **ликвидировало остаточный CLS на ПК до значения 0.002 (оценка 100/100)**!
    *   Для слайдов карусели `.slide` добавлены физические размеры `width="400" height="250"`, а в стилях заданы `height: auto` и `aspect-ratio: 8 / 5`. Это позволило браузеру заранее резервировать место под картинки и полностью устранило сдвиги страницы при их загрузке.
    *   Для первого слайда `slide1.webp` установлен приоритетный атрибут `fetchpriority="high"`, что ускоряет отрисовку LCP-изображения (самого крупного элемента на странице) при первоначальном обращении к сайту.
    *   В разметку HTML для первых трех слайдов жестко прописаны начальные CSS-классы слайдера (`active` для слайда 1, `right` для слайда 2, `left` для слайда 5), благодаря чему карусель корректно рендерится и позиционируется еще до выполнения JavaScript, а браузер однозначно определяет первый слайд в качестве LCP-элемента.
*   **Кеширование статических ресурсов:**
    *   Создан файл конфигурации сервера [.htaccess](file:///c:/OSPanel/domains/quete/pre-alpha0.1.3-antigravity-git/.htaccess), который устанавливает долгосрочное кэширование браузера (до 1 года) для всех типов изображений (png, jpeg, webp, ico) и стилей/скриптов (1 месяц) с помощью модуля `mod_expires`. Это решает рекомендацию Google по эффективному периоду хранения кеша.
*   **Конвертация и сжатие изображений в формат WebP:**
    *   Написан Python-скрипт `scratch/convert_to_webp.py` с использованием библиотеки Pillow, который автоматически сконвертировал тяжелые PNG-файлы слайдов карусели и джойстика в современный сжатый формат WebP.
    *   **Результаты сжатия:** Общий вес слайдов снижен с 400.9 КБ до **40.2 КБ** (сжатие на **90%**), что экономит **360.7 КБ** трафика при первой загрузке страницы и полностью удовлетворяет рекомендациям Google.
    *   Все ссылки на главной странице [index.php](file:///c:/OSPanel/domains/quete/pre-alpha0.1.3-antigravity-git/index.php) переведены на использование WebP-формата, а в скрипте деплоя [deploy_images.py](file:///c:/OSPanel/domains/quete/pre-alpha0.1.3-antigravity-git/scratch/deploy_images.py) добавлено расширение `.webp` для загрузки на продакшн.
*   **Исправление валидности подключения стилей (HTML5 Валидатор):**
    *   Реализована динамическая загрузка файлов стилей прямо в `<head>` внутри [views/header.php](file:///c:/OSPanel/domains/quete/pre-alpha0.1.3-antigravity-git/views/header.php).
    *   Удалено некорректное подключение `<link rel="stylesheet" href="assets/css/auth.css">` из тела документов [login.php](file:///c:/OSPanel/domains/quete/pre-alpha0.1.3-antigravity-git/login.php) и [register.php](file:///c:/OSPanel/domains/quete/pre-alpha0.1.3-antigravity-git/register.php), где стили подключались внутри тега `<body>` после инклуда хедера. Теперь разметка страниц полностью соответствует стандартам HTML5.

"""

if pattern.search(content):
    content = pattern.sub(new_text, content)
    try:
        with open(file_path, "w", encoding="utf-8") as f:
            f.write(content)
        print("Successfully updated COMPLETED_WORK.md!")
    except Exception as e:
        print(f"Error writing file: {e}")
else:
    print("Target section pattern not found in COMPLETED_WORK.md")
