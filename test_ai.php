<?php
/**
 * Панель досконального тестирования и диагностики ИИ (Groq Llama)
 * 
 * Данный скрипт является интерактивной SPA-панелью для тестирования всех 
 * функций ядра ai_handler.php. Он выполняет следующие проверки:
 * 1. Интерактивная проверка пинга и задержки API (Ping & Latency)
 * 2. Потоковая и пакетная валидация тем (с анализом ошибок)
 * 3. Полноценная генерация вопросов с визуализацией в игровой карточке
 * 4. Проверка алгоритмов схожести ответов и генерации альтернатив (Fakes)
 * 5. Комплексный стресс-тест и аудит качества генерации (QA Checklist)
 *
 * Файл снабжен подробным логированием в реальном времени в стиле хакерской консоли.
 */

// Инициализируем сессию и подключаем конфигурационные файлы
session_start();
require_once __DIR__ . '/config.php';

// === ЗАЩИТА И ПРОВЕРКА ДОСТУПА ===
if (!isset($_SESSION['user_id']) || $_SESSION['username'] !== 'admin') {
    // Возвращаем HTTP-код 403 Forbidden
    header('HTTP/1.1 403 Forbidden');
    ?>
    <!DOCTYPE html>
    <html lang="ru">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>403 Доступ запрещен | Стенд Тестирования ИИ</title>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;700;900&display=swap" rel="stylesheet">
        <style>
            body {
                background-color: #1A1F3B;
                color: #FFFFFF;
                font-family: 'Inter', sans-serif;
                display: flex;
                align-items: center;
                justify-content: center;
                min-height: 100vh;
                margin: 0;
            }
            .forbidden-card {
                text-align: center;
                background: #232B4B;
                border: 2px solid #E3342F;
                border-radius: 4px;
                padding: 40px 30px;
                max-width: 480px;
            }
            .forbidden-icon {
                font-size: 4em;
                margin-bottom: 20px;
            }
            h1 {
                color: #E3342F;
                font-size: 2.5em;
                margin: 0 0 15px 0;
                text-transform: uppercase;
                letter-spacing: 1px;
            }
            p {
                color: #8E9BC2;
                font-size: 1.1em;
                line-height: 1.6;
                margin-bottom: 30px;
            }
            .btn-back {
                background: #FF7A00;
                color: #000000;
                text-decoration: none;
                padding: 10px 20px;
                border-radius: 4px;
                font-weight: 700;
                text-transform: uppercase;
                font-size: 0.9em;
                transition: opacity 0.1s;
                display: inline-block;
            }
            .btn-back:hover {
                opacity: 0.9;
            }
        </style>
    </head>
    <body>
        <div class="forbidden-card">
            <div class="forbidden-icon">🛑</div>
            <h1>Доступ ограничен</h1>
            <p>Учетная запись <strong><?php echo isset($_SESSION['username']) ? htmlspecialchars($_SESSION['username']) : 'Гость'; ?></strong> не обладает правами администратора.</p>
            <a href="index.php" class="btn-back">Вернуться на главную</a>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// Включаем обработку и вывод ошибок для удобства отладки
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/core/ai_handler.php';

// === ОБРАБОТКА AJAX-ЗАПРОСОВ ===
if (isset($_GET['ajax']) || (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest')) {
    header('Content-Type: application/json; charset=utf-8');
    
    $action = $_POST['action'] ?? $_GET['action'] ?? '';
    $startTime = microtime(true);
    
    try {
        switch ($action) {
            case 'ping':
                // Быстрая проверка связи с Groq API
                $prompt = "Respond with exactly one word: PONG";
                $res = sendGroqRequest($prompt, ['temperature' => 0.1, 'max_tokens' => 10]);
                $endTime = microtime(true);
                $latency = round($endTime - $startTime, 3);
                
                if ($res['success']) {
                    echo json_encode([
                        'success' => true,
                        'latency' => $latency,
                        'text' => trim($res['text']),
                        'usage' => $res['usage'],
                        'model' => GROQ_MODEL,
                        'raw' => $res['data']
                    ], JSON_UNESCAPED_UNICODE);
                } else {
                    echo json_encode([
                        'success' => false,
                        'latency' => $latency,
                        'error' => $res['error'],
                        'code' => $res['code'] ?? 'UNKNOWN'
                    ], JSON_UNESCAPED_UNICODE);
                }
                break;
                
            case 'validate_topic':
                // Проверка валидности одной конкретной темы
                $topic = trim($_POST['topic'] ?? '');
                if (empty($topic)) {
                    throw new Exception('Не указана тема для проверки');
                }
                
                $res = validateTopicWithGroq($topic);
                $endTime = microtime(true);
                $latency = round($endTime - $startTime, 3);
                
                echo json_encode([
                    'success' => true,
                    'latency' => $latency,
                    'valid' => $res['valid'],
                    'reason' => $res['reason']
                ], JSON_UNESCAPED_UNICODE);
                break;
                
            case 'generate_question':
                // Генерация вопроса с исключениями
                $topic = trim($_POST['topic'] ?? '');
                $previousStr = trim($_POST['previous'] ?? '');
                
                if (empty($topic)) {
                    throw new Exception('Не указана тема для генерации');
                }
                
                $previous = [];
                if (!empty($previousStr)) {
                    $previous = array_filter(array_map('trim', explode(',', $previousStr)));
                }
                
                $res = generateQuestionWithGroq($topic, $previous);
                $endTime = microtime(true);
                $latency = round($endTime - $startTime, 3);
                
                if (isset($res['valid']) && $res['valid']) {
                    echo json_encode([
                        'success' => true,
                        'latency' => $latency,
                        'question' => $res['question'],
                        'correct' => $res['correct'],
                        'fakes' => $res['fakes']
                    ], JSON_UNESCAPED_UNICODE);
                } else {
                    echo json_encode([
                        'success' => false,
                        'latency' => $latency,
                        'error' => $res['error'] ?? 'Не удалось сгенерировать вопрос'
                    ], JSON_UNESCAPED_UNICODE);
                }
                break;
                
            case 'generate_fake':
                // Генерация одной убедительной альтернативы (фейка)
                $topic = trim($_POST['topic'] ?? '');
                $correct = trim($_POST['correct'] ?? '');
                
                if (empty($topic) || empty($correct)) {
                    throw new Exception('Укажите тему и правильный ответ');
                }
                
                $fake = generateFakeAnswerWithGroq($topic, $correct);
                $endTime = microtime(true);
                $latency = round($endTime - $startTime, 3);
                
                echo json_encode([
                    'success' => true,
                    'latency' => $latency,
                    'fake' => $fake
                ], JSON_UNESCAPED_UNICODE);
                break;
                
            case 'test_similarity':
                // Тестирование функции проверки близости ответов
                $user = trim($_POST['user'] ?? '');
                $correct = trim($_POST['correct'] ?? '');
                
                if (empty($user) || empty($correct)) {
                    throw new Exception('Укажите оба ответа для сравнения');
                }
                
                $isTooClose = isAnswerTooCloseToCorrect($user, $correct);
                
                // Рассчитываем точный процент совпадения по алгоритму аналогично core/ai_handler.php
                $similarity = 0;
                similar_text(
                    mb_strtolower($user),
                    mb_strtolower($correct),
                    $similarity
                );
                
                $endTime = microtime(true);
                $latency = round($endTime - $startTime, 3);
                
                echo json_encode([
                    'success' => true,
                    'latency' => $latency,
                    'is_too_close' => $isTooClose,
                    'similarity_percent' => round($similarity, 2),
                    'threshold' => 85
                ], JSON_UNESCAPED_UNICODE);
                break;
                
            case 'run_stress_test':
                // Полноценный комплексный стресс-тест и аудит качества
                $tests = [
                    ['type' => 'validate', 'param' => 'Космос и Галактики', 'label' => 'Проверка валидной сложной темы'],
                    ['type' => 'validate', 'param' => 'ывапролджывпр', 'label' => 'Проверка невалидной абракадабры'],
                    ['type' => 'generate', 'param' => 'Технологии будущего', 'label' => 'Генерация вопроса на новую тему']
                ];
                
                $results = [];
                $totalLatency = 0;
                $successCount = 0;
                $qaChecks = [
                    'json_format' => true,
                    'ten_fakes' => true,
                    'unique_fakes' => true,
                    'no_correct_in_fakes' => true,
                    'proper_lengths' => true
                ];
                
                foreach ($tests as $test) {
                    $tStart = microtime(true);
                    $testRes = null;
                    
                    try {
                        if ($test['type'] === 'validate') {
                            $testRes = validateTopicWithGroq($test['param']);
                        } else {
                            $testRes = generateQuestionWithGroq($test['param']);
                        }
                    } catch (Exception $e) {
                        $error = $e->getMessage();
                    }
                    
                    $tEnd = microtime(true);
                    $tLatency = round($tEnd - $tStart, 3);
                    $totalLatency += $tLatency;
                    
                    $isSuccess = false;
                    $details = '';
                    
                    if ($test['type'] === 'validate') {
                        if ($testRes && isset($testRes['valid'])) {
                            $isSuccess = true;
                            $details = "Результат: " . ($testRes['valid'] ? 'ВАЛИДНА' : 'НЕВАЛИДНА') . " (" . $testRes['reason'] . ")";
                        } else {
                            $details = "Ошибка: повреждена структура ответа валидатора";
                        }
                    } else {
                        if ($testRes && isset($testRes['valid']) && $testRes['valid']) {
                            $isSuccess = true;
                            
                            // Анализируем критерии качества генерации (QA Checklist)
                            $problems = [];
                            
                            if (empty($testRes['question']) || empty($testRes['correct'])) {
                                $problems[] = "Пустые поля текста или верного ответа";
                                $qaChecks['json_format'] = false;
                            }
                            
                            if (!is_array($testRes['fakes'])) {
                                $problems[] = "Список fakes не является массивом";
                                $qaChecks['json_format'] = false;
                                $qaChecks['ten_fakes'] = false;
                            } else {
                                $fakesCount = count($testRes['fakes']);
                                if ($fakesCount !== 10) {
                                    $problems[] = "Сгенерировано фейков: $fakesCount (ожидалось ровно 10)";
                                    $qaChecks['ten_fakes'] = false;
                                }
                                
                                $uniqueFakes = array_unique($testRes['fakes']);
                                if (count($uniqueFakes) !== $fakesCount) {
                                    $problems[] = "В списке фейков присутствуют дубликаты";
                                    $qaChecks['unique_fakes'] = false;
                                }
                                
                                foreach ($testRes['fakes'] as $fake) {
                                    if (mb_strtolower(trim($fake)) === mb_strtolower(trim($testRes['correct']))) {
                                        $problems[] = "Один из ложных ответов совпадает с правильным";
                                        $qaChecks['no_correct_in_fakes'] = false;
                                    }
                                    
                                    // Оценка длины фейков относительно верного ответа
                                    $correctLen = mb_strlen($testRes['correct']);
                                    $fakeLen = mb_strlen($fake);
                                    if ($correctLen > 5 && $fakeLen > $correctLen * 3) {
                                        $qaChecks['proper_lengths'] = false;
                                    }
                                }
                            }
                            
                            if (empty($problems)) {
                                $details = "Вопрос сгенерирован идеально! Пройдены все 5 критериев качества (10/10)";
                            } else {
                                $details = "Обнаружены проблемы качества: " . implode('; ', $problems);
                            }
                        } else {
                            $details = "Ошибка API при генерации: " . ($testRes['error'] ?? 'Неизвестно');
                        }
                    }
                    
                    if ($isSuccess) $successCount++;
                    
                    $results[] = [
                        'label' => $test['label'],
                        'success' => $isSuccess,
                        'latency' => $tLatency,
                        'details' => $details
                    ];
                }
                
                $avgLatency = round($totalLatency / count($tests), 3);
                
                echo json_encode([
                    'success' => true,
                    'results' => $results,
                    'qa_checks' => $qaChecks,
                    'summary' => [
                        'total_tests' => count($tests),
                        'success_tests' => $successCount,
                        'total_latency' => round($totalLatency, 3),
                        'avg_latency' => $avgLatency,
                        'status' => ($successCount === count($tests)) ? 'Perfect' : ($successCount > 0 ? 'Warning' : 'Failed')
                    ]
                ], JSON_UNESCAPED_UNICODE);
                break;
                
            default:
                throw new Exception('Запрошено неподдерживаемое действие');
        }
    } catch (Exception $e) {
        $endTime = microtime(true);
        echo json_encode([
            'success' => false,
            'latency' => round($endTime - $startTime, 3),
            'error' => $e->getMessage()
        ], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

// Проверяем наличие ключа API в системе
$apiKeyExists = defined('GROQ_API_KEY') && !empty(GROQ_API_KEY);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🧪 Стенд Тестирования ИИ | Куэте</title>
    <!-- Подключаем фирменный ретро-шрифт Inter -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800;900&family=Fira+Code:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        /* === ПЕРЕМЕННЫЕ ЦВЕТОВ (Куэте - упрощенная плоская ретро-тема) === */
        :root {
            --bg-dark: #333B65;          /* Основной темно-синий фон */
            --bg-deep: #1A1F3B;          /* Сверхтемный для блоков и консоли */
            --bg-card: #232B4B;          /* Простой плоский фон карточек */
            --accent-orange: #FF7A00;    /* Фирменный оранжевый */
            --border-color: #3C4573;     /* Простая плоская рамка */
            --pill-dark: #293563;        /* Заглушенный синий */
            --text-white: #FFFFFF;
            --text-muted: #8E9BC2;       /* Тусклый сине-серый */
            
            --success-green: #38C172;
            --error-red: #E3342F;
            --warning-yellow: #F6993F;
            --info-blue: #3490DC;
        }

        /* === БАЗОВЫЙ СБРОС И НАСТРОЙКИ === */
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--bg-dark);
            color: var(--text-white);
            min-height: 100vh;
            padding: 20px 15px;
        }

        .container {
            max-width: 960px;
            margin: 0 auto;
        }

        /* === УПРОЩЕННАЯ ШАПКА === */
        .app-header {
            text-align: center;
            margin-bottom: 25px;
            padding: 15px;
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 4px;
        }

        .app-logo {
            background: #000;
            color: var(--accent-orange);
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 6px 14px;
            border-radius: 4px;
            font-size: 1.1em;
            font-weight: 800;
            letter-spacing: 1px;
            text-transform: uppercase;
            border: 1px solid var(--accent-orange);
            margin-bottom: 10px;
        }

        .app-logo img {
            width: 20px;
            image-rendering: pixelated;
        }

        .app-title {
            font-size: 1.8em;
            font-weight: 800;
            text-transform: uppercase;
            color: var(--text-white);
            margin-bottom: 6px;
        }

        .app-title span {
            color: var(--accent-orange);
        }

        .app-subtitle {
            color: var(--text-muted);
            font-size: 0.9em;
        }

        /* === УПРОЩЕННЫЕ ТАБЫ === */
        .tabs-nav {
            display: flex;
            justify-content: center;
            gap: 5px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }

        .tab-btn {
            background: var(--pill-dark);
            color: var(--text-muted);
            border: 1px solid var(--border-color);
            padding: 8px 14px;
            border-radius: 4px;
            font-weight: 700;
            font-size: 0.85em;
            cursor: pointer;
            transition: background-color 0.1s, color 0.1s;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .tab-btn:hover {
            color: var(--text-white);
            background-color: #2e3b6e;
        }

        .tab-btn.active {
            background: var(--accent-orange);
            color: #000;
            border-color: var(--accent-orange);
        }

        /* === УПРОЩЕННЫЕ КАРТОЧКИ === */
        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        .card {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 4px;
            padding: 20px;
            margin-bottom: 15px;
        }

        .card h2 {
            font-size: 1.15em;
            font-weight: 800;
            color: var(--accent-orange);
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 8px;
            border-bottom: 1px solid var(--border-color);
            padding-bottom: 8px;
        }

        /* === УПРОЩЕННЫЕ ЭЛЕМЕНТЫ УПРАВЛЕНИЯ === */
        .form-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 12px;
            margin-bottom: 12px;
        }

        @media (min-width: 768px) {
            .form-grid.two-cols {
                grid-template-columns: 1fr 1fr;
            }
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .form-label {
            font-size: 0.75em;
            font-weight: 700;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .form-input {
            background: var(--bg-deep);
            border: 1px solid var(--border-color);
            border-radius: 4px;
            padding: 8px 12px;
            color: var(--text-white);
            font-size: 0.9em;
            font-weight: 500;
        }

        .form-input:focus {
            outline: none;
            border-color: var(--accent-orange);
        }

        .form-input::placeholder {
            color: rgba(255, 255, 255, 0.20);
        }

        .btn-action {
            background: var(--accent-orange);
            color: #000;
            border: none;
            padding: 8px 16px;
            border-radius: 4px;
            font-weight: 700;
            font-size: 0.85em;
            cursor: pointer;
            transition: opacity 0.1s;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
        }

        .btn-action:hover:not(:disabled) {
            opacity: 0.9;
        }

        .btn-action:disabled {
            background: #4A5568;
            color: #718096;
            cursor: not-allowed;
        }

        /* === УПРОЩЕННАЯ КОНСОЛЬ === */
        .console-card {
            background: var(--bg-deep) !important;
            border: 1px solid var(--border-color) !important;
            padding: 12px !important;
            font-family: 'Fira Code', 'Courier New', monospace;
            border-radius: 4px;
            margin-top: 15px;
        }

        .console-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid var(--border-color);
            padding-bottom: 6px;
            margin-bottom: 10px;
            font-size: 0.75em;
            color: var(--text-muted);
            font-weight: 700;
        }

        .console-title {
            display: flex;
            align-items: center;
            gap: 6px;
            color: var(--accent-orange);
        }

        .console-dot {
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: var(--accent-orange);
        }

        .console-body {
            max-height: 160px;
            overflow-y: auto;
            font-size: 0.8em;
            display: flex;
            flex-direction: column;
            gap: 4px;
            scroll-behavior: smooth;
        }

        .console-line {
            line-height: 1.3;
            word-break: break-all;
            display: flex;
            gap: 5px;
        }

        .log-time {
            color: #4A5568;
            flex-shrink: 0;
        }

        .log-label {
            font-weight: bold;
            flex-shrink: 0;
        }

        .log-info { color: #E2E8F0; }
        .log-info .log-label { color: #A0AEC0; }
        .log-success { color: #C6F6D5; }
        .log-success .log-label { color: var(--success-green); }
        .log-error { color: #FED7D7; }
        .log-error .log-label { color: var(--error-red); }
        .log-warn { color: #FEEBC8; }
        .log-warn .log-label { color: var(--warning-yellow); }
        .log-ajax { color: #EBF8FF; }
        .log-ajax .log-label { color: #63B3ED; }

        /* === УПРОЩЕННЫЕ МЕТРИКИ === */
        .system-metrics {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 10px;
            margin-bottom: 15px;
        }

        .metric-box {
            background: rgba(26, 31, 59, 0.5);
            border: 1px solid var(--border-color);
            border-radius: 4px;
            padding: 12px;
            text-align: center;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .metric-value {
            font-size: 1.3em;
            font-weight: 800;
            color: var(--accent-orange);
            margin-top: 4px;
        }

        .metric-label {
            font-size: 0.7em;
            font-weight: 700;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* === УПРОЩЕННЫЙ БАТЧ-ВАЛИДАТОР ТЕМ === */
        .batch-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 10px;
            margin-top: 12px;
        }

        .batch-item {
            background: rgba(26, 31, 59, 0.5);
            border-radius: 4px;
            padding: 12px;
            border: 1px solid var(--border-color);
            border-left: 4px solid #718096;
        }

        .batch-item.valid {
            border-left-color: var(--success-green);
        }

        .batch-item.invalid {
            border-left-color: var(--error-red);
        }

        .batch-title {
            font-weight: 700;
            font-size: 0.9em;
            margin-bottom: 2px;
            color: #FFF;
        }

        .batch-status {
            font-size: 0.7em;
            font-weight: 800;
            text-transform: uppercase;
            margin-bottom: 4px;
        }

        .batch-item.valid .batch-status { color: var(--success-green); }
        .batch-item.invalid .batch-status { color: var(--error-red); }

        .batch-reason {
            font-size: 0.75em;
            color: var(--text-muted);
            line-height: 1.2;
        }

        /* === УПРОЩЕННЫЙ СИМУЛЯТОР ИГРОВОЙ КАРТОЧКИ === */
        .game-card-wrapper {
            background: var(--bg-card);
            border: 1px solid var(--accent-orange);
            border-radius: 4px;
            padding: 20px;
            margin-top: 15px;
        }

        .game-card-header {
            font-size: 0.75em;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: var(--accent-orange);
            text-align: center;
            border-bottom: 1px solid var(--border-color);
            padding-bottom: 8px;
            margin-bottom: 15px;
            font-weight: 800;
        }

        .game-card-question {
            font-size: 1.1em;
            font-weight: 800;
            text-align: center;
            margin-bottom: 15px;
            color: var(--text-white);
            line-height: 1.3;
        }

        .game-card-choices {
            display: grid;
            grid-template-columns: 1fr;
            gap: 8px;
        }

        @media (min-width: 600px) {
            .game-card-choices {
                grid-template-columns: 1fr 1fr;
            }
        }

        .game-choice {
            background: var(--bg-deep);
            border: 1px solid var(--border-color);
            padding: 10px;
            border-radius: 4px;
            text-align: center;
            font-weight: 700;
            font-size: 0.85em;
            cursor: pointer;
            transition: border-color 0.1s, background-color 0.1s;
            user-select: none;
            color: var(--text-white);
        }

        .game-choice:hover {
            background: rgba(255, 122, 0, 0.08);
            border-color: var(--accent-orange);
        }

        .game-choice.revealed-correct {
            background: rgba(56, 193, 114, 0.1) !important;
            border-color: var(--success-green) !important;
            color: #C6F6D5 !important;
        }

        .game-choice.revealed-fake {
            background: rgba(227, 52, 47, 0.08) !important;
            border-color: var(--error-red) !important;
            color: #FED7D7 !important;
        }

        .game-card-controls {
            display: flex;
            justify-content: center;
            gap: 8px;
            margin-top: 15px;
            flex-wrap: wrap;
        }

        /* === УПРОЩЕННЫЙ ТЕСТЕР СХОЖЕСТИ === */
        .similarity-output {
            margin-top: 12px;
            background: rgba(26, 31, 59, 0.4);
            border-radius: 4px;
            padding: 12px;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .meter-container {
            width: 100%;
            height: 8px;
            background: rgba(0,0,0,0.25);
            border-radius: 4px;
            overflow: hidden;
            border: 1px solid var(--border-color);
        }

        .meter-fill {
            height: 100%;
            width: 0%;
            background: var(--accent-orange);
            transition: width 0.3s ease-out;
            border-radius: 4px;
        }

        .similarity-alert {
            display: flex;
            align-items: center;
            gap: 6px;
            padding: 8px 12px;
            border-radius: 4px;
            font-weight: 700;
            font-size: 0.85em;
        }

        .similarity-alert.danger {
            background: rgba(227, 52, 47, 0.12);
            color: #FED7D7;
            border-left: 3px solid var(--error-red);
        }

        .similarity-alert.safe {
            background: rgba(56, 193, 114, 0.12);
            color: #C6F6D5;
            border-left: 3px solid var(--success-green);
        }

        /* === УПРОЩЕННЫЙ QA ОТЧЕТ === */
        .stress-report {
            display: flex;
            flex-direction: column;
            gap: 15px;
            margin-top: 15px;
        }

        .stress-metrics {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
            gap: 10px;
        }

        .stress-checklists {
            display: grid;
            grid-template-columns: 1fr;
            gap: 12px;
        }

        @media (min-width: 768px) {
            .stress-checklists {
                grid-template-columns: 1fr 1.2fr;
            }
        }

        .qa-checklist-box {
            background: rgba(26, 31, 59, 0.5);
            border-radius: 4px;
            padding: 15px;
            border: 1px solid var(--border-color);
        }

        .qa-item {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        }

        .qa-item:last-child {
            border-bottom: none;
        }

        .qa-status {
            font-size: 1em;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .qa-status.passed { color: var(--success-green); }
        .qa-status.failed { color: var(--error-red); }

        .qa-label {
            font-weight: 600;
            font-size: 0.85em;
        }

        pre.json-view {
            background: #090B16;
            padding: 12px;
            border-radius: 4px;
            color: #00F0FF;
            font-family: 'Fira Code', 'Courier New', monospace;
            font-size: 0.75em;
            overflow: auto;
            max-height: 250px;
            border: 1px solid var(--border-color);
        }

        .spinner {
            display: inline-block;
            width: 14px;
            height: 14px;
            border: 2px solid rgba(255, 255, 255, 0.15);
            border-top-color: var(--accent-orange);
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }

        .config-warning-box {
            background: rgba(227, 52, 47, 0.08);
            border: 1px solid var(--error-red);
            border-radius: 4px;
            padding: 15px;
            margin-bottom: 15px;
            display: flex;
            gap: 12px;
            align-items: flex-start;
        }

        .warning-icon {
            font-size: 1.8em;
            line-height: 1;
        }

        .warning-details h3 {
            color: #FFF;
            font-size: 1.05em;
            font-weight: 800;
            margin-bottom: 6px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .warning-details p {
            color: var(--text-white);
            margin-bottom: 8px;
            font-size: 0.85em;
        }

        .warning-steps {
            margin-left: 15px;
            color: var(--text-muted);
            font-size: 0.8em;
            line-height: 1.5;
        }

        .warning-steps code {
            background: rgba(0,0,0,0.25);
            padding: 1px 4px;
            border-radius: 3px;
            color: var(--accent-orange);
            font-family: 'Fira Code', monospace;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Шапка Стенда -->
        <header class="app-header">
            <div class="app-logo">
                <img src="assets/img/retro-joystick.png" alt="" onerror="this.src='data:image/svg+xml;utf8,<svg xmlns=\'http://www.w3.org/2000/svg\' viewBox=\'0 0 24 24\' width=\'24\' height=\'24\' fill=\'%23FF7A00\'><path d=\'M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 17h-2v-2h2v2zm2.07-7.75l-.9.92C13.45 12.9 13 13.5 13 15h-2v-.5c0-1.1.45-2.1 1.17-2.83l1.24-1.26c.37-.36.59-.86.59-1.41 0-1.1-.9-2-2-2s-2 .9-2 2H7c0-2.76 2.24-5 5-5s5 2.24 5 5c0 1.04-.42 1.99-1.07 2.75z\'/></svg>'">
                Куэте ИИ
            </div>
            <h1 class="app-title">Стенд Диагностики <span>Groq API</span></h1>
            <p class="app-subtitle">Интерактивный SPA-контроллер для полной отладки игровой логики ИИ</p>
        </header>

        <?php if (!$apiKeyExists): ?>
        <!-- Предупреждение о конфигурации -->
        <div class="config-warning-box">
            <div class="warning-icon">⚠️</div>
            <div class="warning-details">
                <h3>Внимание! GROQ API ключ отсутствует или пуст</h3>
                <p>ИИ запущен в режиме автоматической симуляции (используются заглушки core/ai_handler.php). Для проверки реальных сетевых запросов настройте подключение:</p>
                <ol class="warning-steps">
                    <li>Перейдите в панель разработчика: <a href="https://console.groq.com/keys" target="_blank" rel="noopener" style="color: var(--accent-orange); font-weight:700;">https://console.groq.com/keys</a></li>
                    <li>Сгенерируйте секретный ключ (API Key)</li>
                    <li>Создайте файл <code>.env</code> в корне проекта и пропишите туда: <br><code>GROQ_API_KEY=ваш_секретный_ключ_gsk_xxx</code></li>
                    <li>Или добавьте строку в <code>config.php</code>: <br><code>define('GROQ_API_KEY', 'gsk_xxxx');</code></li>
                </ol>
            </div>
        </div>
        <?php endif; ?>

        <!-- Навигация -->
        <nav class="tabs-nav">
            <button class="tab-btn active" data-tab="dashboard">📊 Панель Приборов</button>
            <button class="tab-btn" data-tab="topic-validator">🏷️ Валидатор Тем</button>
            <button class="tab-btn" data-tab="question-gen">🎲 Генератор Вопросов</button>
            <button class="tab-btn" data-tab="similarity-tester">⚖️ Симулятор Ответов</button>
            <button class="tab-btn" data-tab="stress-testing">⚡ Комплексный QA Аудит</button>
        </nav>

        <!-- === ВКЛАДКА 1: ПАНЕЛЬ ПРИБОРОВ === -->
        <section id="dashboard" class="tab-content active">
            <div class="card">
                <h2>⚙️ Конфигурация и Параметры ИИ</h2>
                
                <div class="system-metrics">
                    <div class="metric-box">
                        <div class="metric-label">Выбранная Модель</div>
                        <div class="metric-value"><?php echo GROQ_MODEL; ?></div>
                    </div>
                    <div class="metric-box">
                        <div class="metric-label">Статус API-Ключа</div>
                        <div class="metric-value" style="color: <?php echo $apiKeyExists ? 'var(--success-green)' : 'var(--error-red)'; ?>">
                            <?php echo $apiKeyExists ? '🟢 ОБНАРУЖЕН' : '🔴 ОТСУТСТВУЕТ'; ?>
                        </div>
                    </div>
                    <div class="metric-box">
                        <div class="metric-label">Базовый Эндпоинт</div>
                        <div class="metric-value" style="font-size: 1.1em; color: var(--text-muted); word-break: break-all; margin-top: 10px;">
                            <?php echo GROQ_API_URL; ?>
                        </div>
                    </div>
                    <div class="metric-box">
                        <div class="metric-label">Среда PHP</div>
                        <div class="metric-value"><?php echo phpversion(); ?></div>
                    </div>
                </div>

                <div class="form-group">
                    <button class="btn-action" id="btn-ping">
                        ⚡ Проверить Сетевой Пинг к Groq
                    </button>
                </div>

                <div id="ping-result-box" style="display: none; margin-top: 20px;">
                    <h3>Результат сетевого запроса:</h3>
                    <div class="system-metrics" style="margin-top: 15px; margin-bottom: 15px;">
                        <div class="metric-box">
                            <div class="metric-label">Задержка (Latency)</div>
                            <div class="metric-value" id="ping-latency">0.000 сек</div>
                        </div>
                        <div class="metric-box">
                            <div class="metric-label">Всего Токенов</div>
                            <div class="metric-value" id="ping-tokens">0</div>
                        </div>
                        <div class="metric-box">
                            <div class="metric-label">Ответ ИИ</div>
                            <div class="metric-value" id="ping-text" style="color: var(--success-green);">PONG</div>
                        </div>
                    </div>
                    
                    <h4 style="margin-bottom: 10px; color: var(--accent-orange);">Сырой ответ сервера (JSON):</h4>
                    <pre class="json-view" id="ping-raw-json">...</pre>
                </div>
            </div>
        </section>

        <!-- === ВКЛАДКА 2: ВАЛИДАТОР ТЕМ === -->
        <section id="topic-validator" class="tab-content">
            <div class="card">
                <h2>🏷️ Интерактивная Валидация Тем</h2>
                <p style="color: var(--text-muted); margin-bottom: 20px;">
                    Игрок может ввести любую тему для раунда. ИИ оценивает ее пригодность для викторины, отсекает бессмысленные наборы символов и нецензурные слова.
                </p>

                <div class="form-grid two-cols">
                    <div class="form-group">
                        <label class="form-label" for="topic-single">Тема для одиночной проверки</label>
                        <input type="text" id="topic-single" class="form-input" placeholder="Например: Астрономия, Древний Египет, лывыдлап">
                    </div>
                    <div class="form-group" style="justify-content: flex-end;">
                        <button class="btn-action" id="btn-validate-single">🏷️ Проверить тему</button>
                    </div>
                </div>

                <div id="validate-single-result" style="display: none; margin-top: 15px;">
                    <!-- Рендерится динамически -->
                </div>
            </div>

            <div class="card">
                <h2>📚 Пакетное Тестирование Модерации (Batch Validate)</h2>
                <p style="color: var(--text-muted); margin-bottom: 20px;">
                    Проверить серию тем одновременно для оценки точности ложноположительных и ложноотрицательных срабатываний ИИ.
                </p>

                <div class="form-group">
                    <label class="form-label" for="topic-batch-input">Темы через запятую</label>
                    <input type="text" id="topic-batch-input" class="form-input" value="География, Мифология, 123456, фывпролдж, История искусства">
                </div>

                <div class="form-group" style="margin-top: 15px;">
                    <button class="btn-action" id="btn-validate-batch">🔋 Запустить пакетный тест</button>
                </div>

                <div class="batch-grid" id="batch-results-grid">
                    <!-- Заглушка до запуска -->
                </div>
            </div>
        </section>

        <!-- === ВКЛАДКА 3: ГЕНЕРАТОР ВОПРОСОВ === -->
        <section id="question-gen" class="tab-content">
            <div class="card">
                <h2>🎲 Генератор Вопросов Викторины</h2>
                <p style="color: var(--text-muted); margin-bottom: 20px;">
                    ИИ генерирует 1 вопрос по теме, 1 правильный ответ и 10 качественных и убедительных фейков (неправильных ответов).
                </p>

                <div class="form-grid two-cols">
                    <div class="form-group">
                        <label class="form-label" for="gen-topic">Тема для генерации</label>
                        <input type="text" id="gen-topic" class="form-input" value="Космос">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="gen-exclude">Исключить предыдущие вопросы (через запятую)</label>
                        <input type="text" id="gen-exclude" class="form-input" placeholder="Например: Какая планета самая большая?, В каком году...">
                    </div>
                </div>

                <div class="form-group" style="margin-top: 15px;">
                    <button class="btn-action" id="btn-generate-question">🎲 Сгенерировать Вопрос</button>
                </div>
            </div>

            <!-- Игровой Симулятор Карточки -->
            <div id="game-card-container" style="display: none;">
                <div class="game-card-wrapper">
                    <div class="game-card-header">🎮 Симулятор Игровой Карточки "Куэте" (Раунд 1)</div>
                    <div class="game-card-question" id="game-question-text">...</div>
                    <div class="game-card-choices" id="game-choices-grid">
                        <!-- Выборы рендерятся тут -->
                    </div>
                    
                    <div class="game-card-controls">
                        <button class="btn-action" id="btn-reveal-card" style="background:#FFF; color:#000; box-shadow:none;">👁️ Раскрыть правильный ответ</button>
                        <button class="btn-action" id="btn-reshuffle-choices" style="background:var(--pill-dark); color:#FFF; box-shadow:none; border: 1px solid rgba(255,255,255,0.1);">🔄 Перемешать ответы</button>
                    </div>
                </div>

                <!-- Детализационный JSON -->
                <div class="card" style="margin-top: 25px;">
                    <h2>📋 Детализация ответа генерации</h2>
                    <div class="system-metrics">
                        <div class="metric-box">
                            <div class="metric-label">Время обработки (Latency)</div>
                            <div class="metric-value" id="gen-latency">0.000 сек</div>
                        </div>
                        <div class="metric-box">
                            <div class="metric-label">Правильный ответ</div>
                            <div class="metric-value" id="gen-correct-ans" style="color:var(--success-green); font-size: 1.2em;">...</div>
                        </div>
                    </div>
                    <h4 style="margin-top: 15px; margin-bottom: 10px; color: var(--accent-orange);">Сгенерированный JSON:</h4>
                    <pre class="json-view" id="gen-raw-json">...</pre>
                </div>
            </div>
        </section>

        <!-- === ВКЛАДКА 4: СИМУЛЯТОР СХОЖЕСТИ И ФЕЙКОВ === -->
        <section id="similarity-tester" class="tab-content">
            <div class="form-grid two-cols">
                <!-- Сравнение ответов -->
                <div class="card">
                    <h2>⚖️ Проверка Схожести Ответов (similar_text)</h2>
                    <p style="color: var(--text-muted); margin-bottom: 20px;">
                        В "Куэте" пользователь не должен ввести ответ, слишком близкий к правильному (чтобы не облегчать игру другим). Порог отсечки: <strong>85%</strong> сходства.
                    </p>

                    <div class="form-group">
                        <label class="form-label" for="sim-user">Ввод пользователя (Fake игрока)</label>
                        <input type="text" id="sim-user" class="form-input" placeholder="Например: Джорж вашингтон">
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="sim-correct">Правильный ответ (из БД)</label>
                        <input type="text" id="sim-correct" class="form-input" placeholder="Например: Джордж Вашингтон">
                    </div>

                    <div class="form-group" style="margin-top: 15px;">
                        <button class="btn-action" id="btn-compare-answers">⚖️ Сравнить ответы</button>
                    </div>

                    <div class="similarity-output" id="sim-output-box" style="display: none;">
                        <div style="display: flex; justify-content: space-between; font-weight:800;">
                            <span>Коэффициент сходства:</span>
                            <span id="sim-percent-text" style="color: var(--accent-orange);">0%</span>
                        </div>
                        <div class="meter-container">
                            <div class="meter-fill" id="sim-meter"></div>
                        </div>
                        <div class="similarity-alert" id="sim-alert">
                            <!-- Рендерится динамически -->
                        </div>
                    </div>
                </div>

                <!-- Генератор фейков -->
                <div class="card">
                    <h2>🎲 Генератор Одиночного Фейка ИИ</h2>
                    <p style="color: var(--text-muted); margin-bottom: 20px;">
                        Если ответ игрока забракован из-за высокой схожести, система может автоматически сгенерировать для него одну качественную альтернативу.
                    </p>

                    <div class="form-group">
                        <label class="form-label" for="fake-topic">Тема вопроса</label>
                        <input type="text" id="fake-topic" class="form-input" placeholder="Например: Музыка">
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="fake-correct">Правильный ответ</label>
                        <input type="text" id="fake-correct" class="form-input" placeholder="Например: Моцарт">
                    </div>

                    <div class="form-group" style="margin-top: 15px;">
                        <button class="btn-action" id="btn-generate-fake-single">🎲 Создать альтернативу</button>
                    </div>

                    <div id="fake-single-result-box" class="similarity-output" style="display: none;">
                        <div style="font-weight: 800;">Предложенный ИИ вариант:</div>
                        <div style="background: rgba(0,0,0,0.2); padding: 15px; border-radius: 8px; border: 1px solid rgba(255,255,255,0.05); font-size:1.3em; font-weight:900; color:var(--accent-orange); text-align:center;" id="fake-single-value">
                            ...
                        </div>
                        <div style="font-size:0.85em; color:var(--text-muted);" id="fake-single-latency">
                            Время генерации: 0.000 сек
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- === ВКЛАДКА 5: СТРЕСС-ТЕСТИРОВАНИЕ И QA АУДИТ === -->
        <section id="stress-testing" class="tab-content">
            <div class="card">
                <h2>⚡ Комплексный QA Аудит и Стресс-Тест ИИ</h2>
                <p style="color: var(--text-muted); margin-bottom: 20px;">
                    Запуск серии автоматических последовательных запросов к Groq API. Система оценит общую задержку, процент успеха и проверит сгенерированные данные на соответствие строгим 5 критериям качества игровой логики.
                </p>

                <div class="form-group">
                    <button class="btn-action" id="btn-run-stress">
                        🚀 Запустить полный стресс-тест
                    </button>
                </div>

                <div id="stress-result-box" style="display: none; margin-top: 25px;">
                    <div class="stress-report">
                        <!-- Метрики стресс-теста -->
                        <div class="stress-metrics">
                            <div class="metric-box">
                                <div class="metric-label">Всего тестов</div>
                                <div class="metric-value" id="st-total">0</div>
                            </div>
                            <div class="metric-box">
                                <div class="metric-label">Успешно пройдено</div>
                                <div class="metric-value" id="st-success" style="color:var(--success-green);">0</div>
                            </div>
                            <div class="metric-box">
                                <div class="metric-label">Средний Latency</div>
                                <div class="metric-value" id="st-avg-latency">0.000 сек</div>
                            </div>
                            <div class="metric-box">
                                <div class="metric-label">Статус Аудита</div>
                                <div class="metric-value" id="st-audit-status">ОТЛИЧНО</div>
                            </div>
                        </div>

                        <!-- Чек-листы и список логов -->
                        <div class="stress-checklists">
                            <div class="qa-checklist-box">
                                <h3 style="color: var(--accent-orange); font-size:1.15em; font-weight:800; margin-bottom: 15px; border-bottom:1px solid rgba(255,255,255,0.05); padding-bottom:8px;">
                                    🏆 5 Критериев качества генерации (QA Checklist)
                                </h3>
                                <div class="qa-item">
                                    <div class="qa-status" id="chk-json-icon">❓</div>
                                    <div class="qa-label">JSON формат строго соблюден</div>
                                </div>
                                <div class="qa-item">
                                    <div class="qa-status" id="chk-ten-fakes-icon">❓</div>
                                    <div class="qa-label">Ровно 10 фейковых вариантов (fakes)</div>
                                </div>
                                <div class="qa-item">
                                    <div class="qa-status" id="chk-unique-fakes-icon">❓</div>
                                    <div class="qa-label">Все фейки уникальны (нет дубликатов)</div>
                                </div>
                                <div class="qa-item">
                                    <div class="qa-status" id="chk-no-correct-icon">❓</div>
                                    <div class="qa-label">Правильный ответ не дублируется в фейках</div>
                                </div>
                                <div class="qa-item">
                                    <div class="qa-status" id="chk-lengths-icon">❓</div>
                                    <div class="qa-label">Длины фейков соразмерны правильному</div>
                                </div>
                            </div>

                            <div class="qa-checklist-box">
                                <h3 style="color: var(--accent-orange); font-size:1.15em; font-weight:800; margin-bottom: 15px; border-bottom:1px solid rgba(255,255,255,0.05); padding-bottom:8px;">
                                    📝 Подробный протокол прогона тестов
                                </h3>
                                <div style="display:flex; flex-direction:column; gap: 10px;" id="stress-logs-container">
                                    <!-- Заполняется динамически -->
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- === ХАКЕРСКАЯ КОНСОЛЬ РАЗРАБОТЧИКА === -->
        <section class="card console-card">
            <div class="console-header">
                <div class="console-title">
                    <div class="console-dot"></div>
                    <span>ЛОГ ТЕСТИРОВАНИЯ ИИ В РЕАЛЬНОМ ВРЕМЕНИ (REAL-TIME DIAGNOSTIC)</span>
                </div>
                <div style="cursor: pointer; text-decoration: underline;" id="btn-clear-console">Очистить</div>
            </div>
            <div class="console-body" id="console-body">
                <div class="console-line log-success">
                    <span class="log-time">[<?php echo date('H:i:s'); ?>]</span>
                    <span class="log-label">[SYSTEM]</span>
                    Диагностический стенд успешно развернут. Готов к отправке AJAX запросов на Groq API.
                </div>
            </div>
        </section>
    </div>

    <!-- === ИНТЕРАКТИВНАЯ КЛИЕНТСКАЯ ЧАСТЬ (JS) === -->
    <script>
        // Глобальные переменные данных вопроса
        let currentQuestionData = null;

        // Вспомогательная функция для экранирования HTML
        function escapeHtml(string) {
            return String(string).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
        }

        // Логирование событий в консоль разработчика
        function logToConsole(message, type = 'info') {
            const consoleBody = document.getElementById('console-body');
            if (!consoleBody) return;
            
            const time = new Date().toLocaleTimeString();
            const typeLabel = `[${type.toUpperCase()}]`;
            let typeClass = 'log-info';
            if (type === 'error') typeClass = 'log-error';
            if (type === 'success') typeClass = 'log-success';
            if (type === 'warn') typeClass = 'log-warn';
            if (type === 'ajax') typeClass = 'log-ajax';
            
            const line = document.createElement('div');
            line.className = `console-line ${typeClass}`;
            line.innerHTML = `<span class="log-time">[${time}]</span> <span class="log-label">${typeLabel}</span> ${escapeHtml(message)}`;
            
            consoleBody.appendChild(line);
            // Плавная прокрутка консоли вниз
            consoleBody.scrollTop = consoleBody.scrollHeight;
        }

        // Переключение вкладок (Tabs)
        document.querySelectorAll('.tab-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
                document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
                
                btn.classList.add('active');
                const targetTab = btn.getAttribute('data-tab');
                document.getElementById(targetTab).classList.add('active');
                
                logToConsole(`Переключение на вкладку: ${btn.innerText.trim()}`, 'info');
            });
        });

        // Очистка консоли
        document.getElementById('btn-clear-console').addEventListener('click', () => {
            document.getElementById('console-body').innerHTML = '';
            logToConsole('Консоль очищена', 'info');
        });

        // 1. ТЕСТ СЕТЕВОГО ПИНГА (PING GROQ)
        document.getElementById('btn-ping').addEventListener('click', function() {
            const btn = this;
            btn.disabled = true;
            const originalText = btn.innerHTML;
            btn.innerHTML = `<span class="spinner"></span> Ожидание ответа Groq API...`;
            
            logToConsole('Отправка эхо-запроса (Ping) к Groq API...', 'ajax');
            const startTime = performance.now();
            
            fetch('test_ai.php?ajax=1&action=ping')
                .then(response => response.json())
                .then(data => {
                    const duration = ((performance.now() - startTime) / 1000).toFixed(3);
                    if (data.success) {
                        logToConsole(`Ping выполнен успешно за ${data.latency}с. Текст: "${data.text}"`, 'success');
                        
                        document.getElementById('ping-result-box').style.display = 'block';
                        document.getElementById('ping-latency').innerText = `${data.latency} сек`;
                        
                        const promptTokens = data.usage?.prompt_tokens ?? 'Н/Д';
                        const completionTokens = data.usage?.completion_tokens ?? 'Н/Д';
                        const totalTokens = data.usage?.total_tokens ?? 'Н/Д';
                        document.getElementById('ping-tokens').innerText = totalTokens;
                        
                        logToConsole(`Расход токенов: Ввод: ${promptTokens}, Вывод: ${completionTokens}, Всего: ${totalTokens}`, 'info');
                        
                        document.getElementById('ping-text').innerText = data.text;
                        document.getElementById('ping-raw-json').innerText = JSON.stringify(data.raw, null, 4);
                    } else {
                        logToConsole(`Не удалось выполнить Ping: ${data.error}`, 'error');
                        alert(`Ошибка API: ${data.error}`);
                    }
                })
                .catch(err => {
                    logToConsole(`Сетевая ошибка при Ping: ${err.message}`, 'error');
                })
                .finally(() => {
                    btn.disabled = false;
                    btn.innerHTML = originalText;
                });
        });

        // 2. ВАЛИДАТОР ОДИНОЧНОЙ ТЕМЫ
        document.getElementById('btn-validate-single').addEventListener('click', function() {
            const btn = this;
            const topicInput = document.getElementById('topic-single');
            const topic = topicInput.value.trim();
            
            if (!topic) {
                logToConsole('Ошибка: Введите тему для проверки!', 'warn');
                topicInput.focus();
                return;
            }
            
            btn.disabled = true;
            btn.innerHTML = `<span class="spinner"></span> Проверка...`;
            
            logToConsole(`Отправка темы "${topic}" на модерацию ИИ...`, 'ajax');
            
            const formData = new FormData();
            formData.append('action', 'validate_topic');
            formData.append('topic', topic);
            
            fetch('test_ai.php?ajax=1', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    const resultBox = document.getElementById('validate-single-result');
                    resultBox.style.display = 'block';
                    
                    const statusClass = data.valid ? 'success' : 'error';
                    const statusText = data.valid ? '🟢 ВАЛИДНА' : '🔴 НЕВАЛИДНА';
                    const icon = data.valid ? '✅' : '❌';
                    
                    resultBox.innerHTML = `
                        <div class="batch-item ${data.valid ? 'valid' : 'invalid'}" style="margin-top: 15px;">
                            <div class="batch-title">${escapeHtml(topic)}</div>
                            <div class="batch-status">${statusText}</div>
                            <div class="batch-reason"><strong>Пояснение ИИ:</strong> ${escapeHtml(data.reason)}</div>
                            <div style="font-size:0.8em; color:var(--text-muted); margin-top:8px;">Время ответа ИИ: ${data.latency} сек</div>
                        </div>
                    `;
                    
                    logToConsole(`Тема "${topic}" проверена: ${data.valid ? 'ВАЛИДНА' : 'НЕВАЛИДНА'}. (${data.reason})`, data.valid ? 'success' : 'warn');
                } else {
                    logToConsole(`Ошибка валидации темы: ${data.error}`, 'error');
                }
            })
            .catch(err => {
                logToConsole(`Сетевая ошибка при валидации темы: ${err.message}`, 'error');
            })
            .finally(() => {
                btn.disabled = false;
                btn.innerHTML = `🏷️ Проверить тему`;
            });
        });

        // 3. ПАКЕТНЫЙ ВАЛИДАТОР ТЕМ
        document.getElementById('btn-validate-batch').addEventListener('click', function() {
            const btn = this;
            const batchInput = document.getElementById('topic-batch-input');
            const topicsStr = batchInput.value.trim();
            
            if (!topicsStr) {
                logToConsole('Ошибка: Укажите список тем для проверки!', 'warn');
                batchInput.focus();
                return;
            }
            
            const topics = topicsStr.split(',').map(t => t.trim()).filter(t => t.length > 0);
            if (topics.length === 0) return;
            
            btn.disabled = true;
            btn.innerHTML = `<span class="spinner"></span> Выполняется пакетная проверка...`;
            
            const grid = document.getElementById('batch-results-grid');
            grid.innerHTML = '';
            
            logToConsole(`Запуск пакетной проверки для ${topics.length} тем...`, 'info');
            
            let completed = 0;
            
            topics.forEach((topic, index) => {
                // Создаем карточку-заглушку с лоадером
                const item = document.createElement('div');
                item.className = 'batch-item';
                item.id = `batch-item-${index}`;
                item.innerHTML = `
                    <div class="batch-title">${escapeHtml(topic)}</div>
                    <div style="display:flex; align-items:center; gap:8px; font-size:0.9em; margin-top:10px; color:var(--text-muted);">
                        <span class="spinner" style="width:14px; height:14px; border-width:2px;"></span> Ожидание ответа API...
                    </div>
                `;
                grid.appendChild(item);
                
                // Делаем AJAX-запрос для каждой темы независимо
                const formData = new FormData();
                formData.append('action', 'validate_topic');
                formData.append('topic', topic);
                
                fetch('test_ai.php?ajax=1', {
                    method: 'POST',
                    body: formData
                })
                .then(res => res.json())
                .then(data => {
                    const card = document.getElementById(`batch-item-${index}`);
                    if (data.success) {
                        card.className = `batch-item ${data.valid ? 'valid' : 'invalid'}`;
                        card.innerHTML = `
                            <div class="batch-title">${escapeHtml(topic)}</div>
                            <div class="batch-status">${data.valid ? '🟢 ВАЛИДНА' : '🔴 НЕВАЛИДНА'}</div>
                            <div class="batch-reason">${escapeHtml(data.reason)}</div>
                            <div style="font-size:0.78em; color:var(--text-muted); margin-top:10px;">Задержка: ${data.latency} сек</div>
                        `;
                        logToConsole(`[Пакет] Тема "${topic}" -> ${data.valid ? 'ВАЛИДНА' : 'НЕВАЛИДНА'} за ${data.latency}с.`, data.valid ? 'success' : 'warn');
                    } else {
                        card.innerHTML = `
                            <div class="batch-title">${escapeHtml(topic)}</div>
                            <div class="batch-status" style="color:var(--error-red);">🔴 ОШИБКА</div>
                            <div class="batch-reason" style="color:var(--error-red);">${escapeHtml(data.error)}</div>
                        `;
                        logToConsole(`[Пакет] Ошибка проверки "${topic}": ${data.error}`, 'error');
                    }
                })
                .catch(err => {
                    const card = document.getElementById(`batch-item-${index}`);
                    card.innerHTML = `
                        <div class="batch-title">${escapeHtml(topic)}</div>
                        <div class="batch-status" style="color:var(--error-red);">🔴 СЕТЕВАЯ ОШИБКА</div>
                        <div class="batch-reason">${escapeHtml(err.message)}</div>
                    `;
                })
                .finally(() => {
                    completed++;
                    if (completed === topics.length) {
                        btn.disabled = false;
                        btn.innerHTML = `🔋 Запустить пакетный тест`;
                        logToConsole('Пакетная проверка всех тем успешно завершена!', 'success');
                    }
                });
            });
        });

        // 4. ГЕНЕРАТОР ВОПРОСОВ И СИМУЛЯТОР КАРТОЧКИ
        document.getElementById('btn-generate-question').addEventListener('click', function() {
            const btn = this;
            const topicInput = document.getElementById('gen-topic');
            const excludeInput = document.getElementById('gen-exclude');
            const topic = topicInput.value.trim();
            const exclude = excludeInput.value.trim();
            
            if (!topic) {
                logToConsole('Ошибка: Укажите тему для генерации вопроса!', 'warn');
                topicInput.focus();
                return;
            }
            
            btn.disabled = true;
            btn.innerHTML = `<span class="spinner"></span> Генерация контента ИИ... (это занимает 3-6 секунд)`;
            
            logToConsole(`Запуск генерации вопроса по теме "${topic}"...`, 'ajax');
            const startTime = performance.now();
            
            const formData = new FormData();
            formData.append('action', 'generate_question');
            formData.append('topic', topic);
            formData.append('previous', exclude);
            
            fetch('test_ai.php?ajax=1', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    currentQuestionData = data;
                    const duration = ((performance.now() - startTime) / 1000).toFixed(3);
                    logToConsole(`Вопрос сгенерирован успешно за ${data.latency}с.`, 'success');
                    
                    // Показываем контейнер с симулятором
                    document.getElementById('game-card-container').style.display = 'block';
                    document.getElementById('game-question-text').innerText = data.question;
                    document.getElementById('gen-latency').innerText = `${data.latency} сек`;
                    document.getElementById('gen-correct-ans').innerText = data.correct;
                    document.getElementById('gen-raw-json').innerText = JSON.stringify(data, null, 4);
                    
                    // Отрисовываем интерактивные кнопки ответов
                    renderChoices();
                } else {
                    logToConsole(`Не удалось сгенерировать вопрос: ${data.error}`, 'error');
                    alert(`Ошибка генерации: ${data.error}`);
                }
            })
            .catch(err => {
                logToConsole(`Сетевая ошибка генерации: ${err.message}`, 'error');
            })
            .finally(() => {
                btn.disabled = false;
                btn.innerHTML = `🎲 Сгенерировать Вопрос`;
            });
        });

        // Функция перемешивания и рендера ответов в Симуляторе Игровой Карточки
        function renderChoices() {
            if (!currentQuestionData) return;
            
            const grid = document.getElementById('game-choices-grid');
            grid.innerHTML = '';
            
            // В игре игроку обычно предлагается правильный ответ и 3 случайных фейка
            // Для подробной демонстрации возьмем правильный ответ и первые 3 фейка из 10
            const choices = [
                { text: currentQuestionData.correct, isCorrect: true },
                { text: currentQuestionData.fakes[0], isCorrect: false },
                { text: currentQuestionData.fakes[1], isCorrect: false },
                { text: currentQuestionData.fakes[2], isCorrect: false }
            ];
            
            // Перемешиваем по алгоритму Фишера-Йетса
            for (let i = choices.length - 1; i > 0; i--) {
                const j = Math.floor(Math.random() * (i + 1));
                [choices[i], choices[j]] = [choices[j], choices[i]];
            }
            
            // Рендерим кнопки выбора в игровую сетку
            choices.forEach(choice => {
                const item = document.createElement('div');
                item.className = 'game-choice';
                if (choice.isCorrect) item.classList.add('correct-answer');
                item.innerText = choice.text;
                
                // Добавляем событие клика по ответу (угадывание)
                item.addEventListener('click', function() {
                    // Подсвечиваем все карточки
                    document.querySelectorAll('.game-choice').forEach(el => {
                        if (el.classList.contains('correct-answer')) {
                            el.classList.add('revealed-correct');
                        } else {
                            el.classList.add('revealed-fake');
                        }
                    });
                    
                    if (choice.isCorrect) {
                        logToConsole(`[Симулятор] Правильно! Вы угадали верный ответ ИИ: "${choice.text}"`, 'success');
                    } else {
                        logToConsole(`[Симулятор] Ловушка сработала! Вы выбрали фейковый ответ ИИ: "${choice.text}". Верный ответ: "${currentQuestionData.correct}"`, 'warn');
                    }
                });
                
                grid.appendChild(item);
            });
            
            logToConsole('[Симулятор] Варианты ответов перемешаны и выведены в игровой шаблон!', 'info');
        }

        // Кнопка Перемешать ответы в симуляторе
        document.getElementById('btn-reshuffle-choices').addEventListener('click', renderChoices);

        // Кнопка Раскрыть карту в симуляторе
        document.getElementById('btn-reveal-card').addEventListener('click', function() {
            document.querySelectorAll('.game-choice').forEach(el => {
                if (el.classList.contains('correct-answer')) {
                    el.classList.add('revealed-correct');
                } else {
                    el.classList.add('revealed-fake');
                }
            });
            logToConsole('[Симулятор] Правильные и фейковые ответы подсвечены на игровом стенде!', 'info');
        });

        // 5. ТЕСТЕР СХОЖЕСТИ (similar_text)
        document.getElementById('btn-compare-answers').addEventListener('click', function() {
            const btn = this;
            const userAnsInput = document.getElementById('sim-user');
            const correctAnsInput = document.getElementById('sim-correct');
            const user = userAnsInput.value.trim();
            const correct = correctAnsInput.value.trim();
            
            if (!user || !correct) {
                logToConsole('Ошибка: Укажите оба ответа для сравнения!', 'warn');
                return;
            }
            
            btn.disabled = true;
            
            logToConsole(`Сравнение схожести: "${user}" vs "${correct}"...`, 'ajax');
            
            const formData = new FormData();
            formData.append('action', 'test_similarity');
            formData.append('user', user);
            formData.append('correct', correct);
            
            fetch('test_ai.php?ajax=1', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    const outBox = document.getElementById('sim-output-box');
                    outBox.style.display = 'flex';
                    
                    // Обновляем визуальный прогресс-бар
                    document.getElementById('sim-percent-text').innerText = `${data.similarity_percent}%`;
                    document.getElementById('sim-meter').style.width = `${data.similarity_percent}%`;
                    
                    const alertEl = document.getElementById('sim-alert');
                    if (data.is_too_close) {
                        alertEl.className = 'similarity-alert danger';
                        alertEl.innerHTML = `⚠️ <strong>ОШИБКА БЕЗОПАСНОСТИ:</strong> Сходство ${data.similarity_percent}% &gt; ${data.threshold}%. Данный ответ БУДЕТ ЗАБРАКОВАН в игре! Игроку придется вводить другой вариант.`;
                        logToConsole(`Превышен лимит сходства! Коэффициент: ${data.similarity_percent}% (Порог: ${data.threshold}%). Ответ заблокирован.`, 'warn');
                    } else {
                        alertEl.className = 'similarity-alert safe';
                        alertEl.innerHTML = `✅ <strong>ОТЛИЧНО:</strong> Сходство ${data.similarity_percent}% &le; ${data.threshold}%. Ответ одобрен для публикации на игровой стол.`;
                        logToConsole(`Сходство в пределах нормы: ${data.similarity_percent}% (Одобрено).`, 'success');
                    }
                }
            })
            .catch(err => {
                logToConsole(`Сетевая ошибка при сравнении: ${err.message}`, 'error');
            })
            .finally(() => {
                btn.disabled = false;
            });
        });

        // 6. ГЕНЕРАТОР ОДИНОЧНОГО ФЕЙКА
        document.getElementById('btn-generate-fake-single').addEventListener('click', function() {
            const btn = this;
            const topicInput = document.getElementById('fake-topic');
            const correctInput = document.getElementById('fake-correct');
            const topic = topicInput.value.trim();
            const correct = correctInput.value.trim();
            
            if (!topic || !correct) {
                logToConsole('Ошибка: Укажите тему и правильный ответ для генерации альтернативы!', 'warn');
                return;
            }
            
            btn.disabled = true;
            btn.innerHTML = `<span class="spinner"></span> Создание...`;
            logToConsole(`Запрос на генерацию альтернативы к ответу "${correct}" по теме "${topic}"...`, 'ajax');
            
            const formData = new FormData();
            formData.append('action', 'generate_fake');
            formData.append('topic', topic);
            formData.append('correct', correct);
            
            fetch('test_ai.php?ajax=1', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    const box = document.getElementById('fake-single-result-box');
                    box.style.display = 'flex';
                    document.getElementById('fake-single-value').innerText = data.fake;
                    document.getElementById('fake-single-latency').innerText = `Время генерации ИИ: ${data.latency} сек`;
                    
                    logToConsole(`Альтернативный фейк успешно сгенерирован: "${data.fake}" за ${data.latency}с.`, 'success');
                } else {
                    logToConsole(`Ошибка генерации фейка: ${data.error}`, 'error');
                }
            })
            .catch(err => {
                logToConsole(`Сетевая ошибка при генерации фейка: ${err.message}`, 'error');
            })
            .finally(() => {
                btn.disabled = false;
                btn.innerHTML = `🎲 Создать альтернативу`;
            });
        });

        // 7. КОМПЛЕКСНЫЙ QA АУДИТ И СТРЕСС-ТЕСТ
        document.getElementById('btn-run-stress').addEventListener('click', function() {
            const btn = this;
            btn.disabled = true;
            btn.innerHTML = `<span class="spinner"></span> Исполнение стресс-теста... (может занять до 15-20 секунд)`;
            
            logToConsole('⚠️ Запуск комплексного автоматического QA-аудита ИИ. Выполняются последовательные нагрузки...', 'warn');
            
            fetch('test_ai.php?ajax=1&action=run_stress_test')
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        logToConsole('Комплексный стресс-тест успешно выполнен!', 'success');
                        
                        document.getElementById('stress-result-box').style.display = 'block';
                        
                        // Метрики
                        document.getElementById('st-total').innerText = data.summary.total_tests;
                        document.getElementById('st-success').innerText = `${data.summary.success_tests} / ${data.summary.total_tests}`;
                        document.getElementById('st-avg-latency').innerText = `${data.summary.avg_latency} сек`;
                        
                        const statusEl = document.getElementById('st-audit-status');
                        statusEl.innerText = data.summary.status.toUpperCase();
                        if (data.summary.status === 'Perfect') {
                            statusEl.style.color = 'var(--success-green)';
                        } else if (data.summary.status === 'Warning') {
                            statusEl.style.color = 'var(--warning-yellow)';
                        } else {
                            statusEl.style.color = 'var(--error-red)';
                        }
                        
                        // Проверяем чеклист 5 критериев
                        const chkJson = document.getElementById('chk-json-icon');
                        const chkTen = document.getElementById('chk-ten-fakes-icon');
                        const chkUnique = document.getElementById('chk-unique-fakes-icon');
                        const chkNoCorr = document.getElementById('chk-no-correct-icon');
                        const chkLengths = document.getElementById('chk-lengths-icon');
                        
                        chkJson.innerHTML = data.qa_checks.json_format ? '✅' : '❌';
                        chkJson.className = `qa-status ${data.qa_checks.json_format ? 'passed' : 'failed'}`;
                        
                        chkTen.innerHTML = data.qa_checks.ten_fakes ? '✅' : '❌';
                        chkTen.className = `qa-status ${data.qa_checks.ten_fakes ? 'passed' : 'failed'}`;
                        
                        chkUnique.innerHTML = data.qa_checks.unique_fakes ? '✅' : '❌';
                        chkUnique.className = `qa-status ${data.qa_checks.unique_fakes ? 'passed' : 'failed'}`;
                        
                        chkNoCorr.innerHTML = data.qa_checks.no_correct_in_fakes ? '✅' : '❌';
                        chkNoCorr.className = `qa-status ${data.qa_checks.no_correct_in_fakes ? 'passed' : 'failed'}`;
                        
                        chkLengths.innerHTML = data.qa_checks.proper_lengths ? '✅' : '❌';
                        chkLengths.className = `qa-status ${data.qa_checks.proper_lengths ? 'passed' : 'failed'}`;
                        
                        // Заполняем логи прогона тестов
                        const logBox = document.getElementById('stress-logs-container');
                        logBox.innerHTML = '';
                        
                        data.results.forEach(res => {
                            const row = document.createElement('div');
                            row.style.background = 'rgba(0,0,0,0.2)';
                            row.style.padding = '12px';
                            row.style.borderRadius = '8px';
                            row.style.borderLeft = `4px solid ${res.success ? 'var(--success-green)' : 'var(--error-red)'}`;
                            row.innerHTML = `
                                <div style="display:flex; justify-content:space-between; font-weight:800; font-size:0.95em;">
                                    <span>${escapeHtml(res.label)}</span>
                                    <span style="color:${res.success ? 'var(--success-green)' : 'var(--error-red)'}">
                                        ${res.success ? 'ПРОЙДЕН' : 'ОШИБКА'} (${res.latency}с)
                                    </span>
                                </div>
                                <div style="font-size:0.85em; color:var(--text-muted); margin-top:5px;">
                                    ${escapeHtml(res.details)}
                                </div>
                            `;
                            logBox.appendChild(row);
                        });
                        
                        logToConsole(`Сводка аудита: Время ${data.summary.total_latency}с. Успешно ${data.summary.success_tests}/${data.summary.total_tests}`, 'info');
                    } else {
                        logToConsole(`Ошибка стресс-теста: ${data.error}`, 'error');
                        alert(`Ошибка стресс-теста: ${data.error}`);
                    }
                })
                .catch(err => {
                    logToConsole(`Сетевая ошибка стресс-теста: ${err.message}`, 'error');
                })
                .finally(() => {
                    btn.disabled = false;
                    btn.innerHTML = `🚀 Запустить полный стресс-тест`;
                });
        });
    </script>
</body>
</html>
