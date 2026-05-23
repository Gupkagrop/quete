<?php
/**
 * Groq AI Handler
 *
 * Использует модель llama-3.1-8b-instant через Groq API:
 * - Быстрая и экономичная модель с низкой задержкой
 * - Отличное качество ответов
 * - OpenAI-совместимый API
 * - Низкая стоимость обработки
 *
 * Документация: https://console.groq.com/docs/overview
 * Модели: https://console.groq.com/docs/models
 * API Endpoint: https://api.groq.com/openai/v1
 */

require_once __DIR__ . '/../config.php';

// Проверка API ключа
if (!defined('GROQ_API_KEY') || empty(GROQ_API_KEY)) {
    error_log('GROQ_API_KEY не установлен в config.php');
}

// Конфигурация Groq API
define('GROQ_MODEL', 'llama-3.1-8b-instant');
define('GROQ_API_URL', 'https://api.groq.com/openai/v1/chat/completions');
define('GROQ_BASE_URL', 'https://api.groq.com/openai/v1');

/**
 * Универсальная функция для отправки запросов к Groq API
 *
 * Использует OpenAI-совместимый формат Chat Completions API
 * Документация: https://console.groq.com/docs/text-chat
 *
 * @param string $prompt - Текст запроса
 * @param array $config - Дополнительная конфигурация генерации
 * @return array - ['success' => bool, 'data' => mixed, 'error' => string]
 */
function sendGroqRequest($prompt, $config = []) {
    // Проверка API ключа
    if (empty(GROQ_API_KEY)) {
        return [
            'success' => false,
            'error' => 'GROQ_API_KEY не установлен',
            'code' => 'NO_API_KEY'
        ];
    }

    // Формирование payload согласно OpenAI-совместимому API
    // Документация: https://platform.openai.com/docs/api-reference/chat/create
    
    $generationConfig = array_merge([
        'temperature' => 0.7,
        'max_tokens' => 2048,
        'top_p' => 0.9,
    ], $config);

    $payload = [
        'model' => GROQ_MODEL,
        'messages' => [
            [
                'role' => 'user',
                'content' => $prompt
            ]
        ],
        'temperature' => $generationConfig['temperature'],
        'max_tokens' => $generationConfig['max_tokens'],
        'top_p' => $generationConfig['top_p'],
    ];

    // Инициализация cURL
    $ch = curl_init(GROQ_API_URL);
    $curlOptions = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . GROQ_API_KEY,
            'User-Agent: Quete-Game/1.0'
        ],
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 3,
    ];

    // Настройка прокси для работы через VPN/VLESS (обход блокировок Groq в РФ)
    if (defined('USE_PROXY') && USE_PROXY) {
        if (defined('PROXY_ADDRESS') && !empty(PROXY_ADDRESS)) {
            $curlOptions[CURLOPT_PROXY] = PROXY_ADDRESS;
        }
        if (defined('PROXY_TYPE') && PROXY_TYPE === 'socks5h') {
            $curlOptions[CURLOPT_PROXYTYPE] = CURLPROXY_SOCKS5_HOSTNAME;
        }
    }

    curl_setopt_array($ch, $curlOptions);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    // Обработка ошибок cURL
    if ($curlError) {
        return [
            'success' => false,
            'error' => 'cURL ошибка: ' . $curlError,
            'code' => 'CURL_ERROR',
            'http_code' => $httpCode
        ];
    }

    // Обработка HTTP ошибок
    if ($httpCode !== 200) {
        $errorData = json_decode($response, true);
        $errorMessage = $errorData['error']['message'] ?? 'Неизвестная ошибка API';

        return [
            'success' => false,
            'error' => "HTTP $httpCode: $errorMessage",
            'code' => 'HTTP_ERROR',
            'http_code' => $httpCode,
            'api_error' => $errorData
        ];
    }

    // Парсинг успешного ответа
    $data = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return [
            'success' => false,
            'error' => 'Ошибка парсинга JSON ответа: ' . json_last_error_msg(),
            'code' => 'JSON_PARSE_ERROR',
            'raw_response' => $response
        ];
    }

    // Проверка структуры ответа OpenAI format
    if (!isset($data['choices'][0]['message']['content'])) {
        return [
            'success' => false,
            'error' => 'Некорректная структура ответа API',
            'code' => 'INVALID_RESPONSE_STRUCTURE',
            'response' => $data
        ];
    }

    return [
        'success' => true,
        'data' => $data,
        'text' => $data['choices'][0]['message']['content'],
        'usage' => $data['usage'] ?? null
    ];
}


/**
 * Проверяет валидность темы через ИИ
 *
 * @param string $topic - Тема для проверки
 * @return array - ['valid' => bool, 'reason' => string]
 */
function validateTopicWithGroq($topic) {
    // Базовые проверки
    if (empty($topic) || strlen($topic) < 2) {
        return ['valid' => false, 'reason' => 'Тема слишком короткая'];
    }

    if (strlen($topic) > 50) {
        return ['valid' => false, 'reason' => 'Тема слишком длинная'];
    }

    $prompt = <<<PROMPT
Проанализируй тему для викторины: "$topic"

Ответь ТОЛЬКО в формате JSON (никакого другого текста):
{
  "is_valid": true,
  "reason": "краткое объяснение"
}

или

{
  "is_valid": false,
  "reason": "причина невалидности"
}

Критерии валидности:
- Тема должна быть осмысленной (не рандомный набор букв)
- Тема должна подходить для создания вопроса викторины
- Тема не должна содержать оскорблений или запрещенного контента
- Тема должна быть на русском языке или иметь смысл

Примеры валидных тем: История, Наука, Спорт, Литература
Примеры невалидных тем: фывпролдж, 123456, qwerty
PROMPT;

    $response = sendGroqRequest($prompt);

    if (!$response['success']) {
        // При ошибке API считаем тему валидной (fail-open)
        return ['valid' => true, 'reason' => 'Не удалось проверить тему через API'];
    }

    $text = $response['text'];

    // Извлекаем JSON из ответа
    if (preg_match('/\{[\s\S]*?\}/m', $text, $matches)) {
        $validation = json_decode($matches[0], true);
        if (json_last_error() === JSON_ERROR_NONE &&
            isset($validation['is_valid'], $validation['reason'])) {
            return [
                'valid' => (bool) $validation['is_valid'],
                'reason' => trim($validation['reason'])
            ];
        }
    }

    // При ошибке парсинга считаем тему валидной
    return ['valid' => true, 'reason' => 'Не удалось распарсить ответ API'];
}


/**
 * Генерирует вопрос с множественным выбором
 *
 * @param string $topic - Тема вопроса
 * @param array $previousQuestions - Массив текстов предыдущих вопросов для исключения повторов
 * @return array - ['question', 'correct', 'fakes', 'valid'] или ['valid' => false] при ошибке
 */
function generateQuestionWithGroq($topic, $previousQuestions = []) {
    // Валидация темы через ИИ
    $validation = validateTopicWithGroq($topic);
    if (!$validation['valid']) {
        return ['valid' => false, 'error' => $validation['reason']];
    }

    $excludeText = !empty($previousQuestions) ? "\nНЕ ИСПОЛЬЗУЙ следующие вопросы (они уже были): " . implode(', ', $previousQuestions) : "";

    $prompt = <<<PROMPT
Создай вопрос с множественным выбором на русском языке на тему "$topic". $excludeText

Формат ответа (ТОЛЬКО JSON, без дополнительного текста):
{
  "question": "текст вопроса",
  "correct": "правильный ответ",
  "fakes": ["неправильный ответ 1", "неправильный ответ 2", "неправильный ответ 3", "неправильный ответ 4", "неправильный ответ 5", "неправильный ответ 6", "неправильный ответ 7", "неправильный ответ 8", "неправильный ответ 9", "неправильный ответ 10"]
}

Требования:
- Вопрос должен быть интересным и не слишком сложным
- Правильный ответ должен быть однозначным
- Неправильные ответы должны быть убедительными и похожими на правильный ответ
- Все тексты на русском языке
- РОВНО 10 неправильных ответов в массиве fakes
- Все ответы (включая правильный) должны быть одного типа (число, слово, фраза и т.д.)
- Неправильные ответы должны быть длины и структуры похожей на правильный ответ
PROMPT;

    $response = sendGroqRequest($prompt);

    if (!$response['success']) {
        error_log("generateQuestionWithGroq error: " . $response['error']);
        return ['valid' => false, 'error' => $response['error']];
    }

    $text = $response['text'];

    // Извлекаем JSON из ответа (поиск первого объекта JSON)
    if (preg_match('/\{[\s\S]*?\}/m', $text, $matches)) {
        $questionData = json_decode($matches[0], true);
        if (json_last_error() === JSON_ERROR_NONE &&
            isset($questionData['question'], $questionData['correct'], $questionData['fakes']) &&
            is_array($questionData['fakes']) && count($questionData['fakes']) >= 10) {
            // Убеждаемся, что у нас ровно 10 фейков
            $fakes = array_slice($questionData['fakes'], 0, 10);
            return [
                'question' => $questionData['question'],
                'correct' => $questionData['correct'],
                'fakes' => $fakes,
                'valid' => true
            ];
        }
    }

    error_log("Groq API: Не удалось распарсить JSON или неверная структура: $text");
    return ['valid' => false, 'error' => 'Ошибка при генерации вопроса'];
}

/**
 * Генерирует фейковый ответ если пользователь ввёл слишком похожий на правильный
 *
 * @param string $topic - Тема
 * @param string $correctAnswer - Правильный ответ
 * @return string - Фейковый ответ
 */
function generateFakeAnswerWithGroq($topic, $correctAnswer) {
    $prompt = <<<PROMPT
На тему "$topic" правильный ответ: "$correctAnswer"

Придумай ОДИН убедительный, но неправильный вариант ответа.
Ответ должен быть коротким (примерно такой же длины как правильный ответ) и на русском языке.
Это должен быть явно неправильный ответ, но выглядеть убедительно.

Ответь только текстом ответа, без объяснений и без кавычек.
PROMPT;

    $response = sendGroqRequest($prompt);

    if ($response['success']) {
        $fake = trim($response['text']);
        // Удаляем кавычки если присутствуют
        $fake = trim($fake, '"\'');
        if (!empty($fake) && strlen($fake) > 1) {
            return $fake;
        }
    }

    // Fallback к заглушке при ошибке
    error_log("generateFakeAnswerWithGroq error: " . ($response['error'] ?? 'Неизвестная ошибка'));
    return generateQuestionStub($topic)['fakes'][0];
}



/**
 * Проверяет похожесть ответов (использует PHP similar_text)
 *
 * @param string $userAnswer - Ответ пользователя
 * @param string $correctAnswer - Правильный ответ
 * @return bool - true если слишком похож (>70%)
 */
function isAnswerTooCloseToCorrect($userAnswer, $correctAnswer) {
    $similarity = 0;
    similar_text(
        mb_strtolower($userAnswer),
        mb_strtolower($correctAnswer),
        $similarity
    );
    return $similarity > 85;
}

/**
 * Заглушка для генерации вопросов (используется при ошибках API)
 *
 * @param string $topic - Тема
 * @return array
 */
function generateQuestionStub($topic) {
    $questions = [
        'История' => [
            'question' => 'Кто был первым президентом США?',
            'correct' => 'Джордж Вашингтон',
            'fakes' => ['Авраам Линкольн', 'Томас Джефферсон', 'Бенджамин Франклин', 'Джон Адамс', 'Джеймс Мадисон', 'Джеймс Монро', 'Джон Куинси Адамс', 'Эндрю Джексон', 'Мартин Ван Бюрен', 'Уильям Генри Харрисон']
        ],
        'Наука' => [
            'question' => 'Какой элемент имеет атомный номер 1?',
            'correct' => 'Водород',
            'fakes' => ['Гелий', 'Литий', 'Бериллий', 'Азот', 'Кислород', 'Углерод', 'Фтор', 'Неон', 'Натрий', 'Магний']
        ],
        'Спорт' => [
            'question' => 'В каком виде спорта используется мяч и ворота?',
            'correct' => 'Футбол',
            'fakes' => ['Баскетбол', 'Волейбол', 'Теннис', 'Хоккей', 'Крикет', 'Водное поло', 'Лакросс', 'Гандбол', 'Американский футбол', 'Регби']
        ],
        'Искусство' => [
            'question' => 'Кто написал "Мону Лизу"?',
            'correct' => 'Леонардо да Винчи',
            'fakes' => ['Микеланджело', 'Рафаэль', 'Винсент Ван Гог', 'Пабло Пикассо', 'Клод Моне', 'Петер Брейгель', 'Тициан', 'Боттичелли', 'Караваджо', 'Рубенс']
        ],
        'Кино' => [
            'question' => 'Какой фильм получил первый Оскар за лучший фильм в истории?',
            'correct' => 'Крылья',
            'fakes' => ['Унесённые ветром', 'Метрополис', 'Огни большого города', 'Певец джаза', 'Голова в облаках', 'Генерал', 'Касабланка', 'Гражданин Кейн', 'Новые времена', 'Алчность']
        ],
        'Литература' => [
            'question' => 'Кто написал роман «Война и мир»?',
            'correct' => 'Лев Толстой',
            'fakes' => ['Фёдор Достоевский', 'Антон Чехов', 'Иван Тургенев', 'Александр Пушкин', 'Михаил Лермонтов', 'Николай Гоголь', 'Иван Бунин', 'Максим Горький', 'Михаил Булгаков', 'Владимир Набоков']
        ],
        'География' => [
            'question' => 'Какая река является самой длинной в мире?',
            'correct' => 'Амазонка',
            'fakes' => ['Нил', 'Миссисипи', 'Янцзы', 'Енисей', 'Обь', 'Хуанхэ', 'Конго', 'Лена', 'Волга', 'Дунай']
        ],
        'Музыка' => [
            'question' => 'Сколько симфоний написал Людвиг ван Бетховен?',
            'correct' => '9',
            'fakes' => ['5', '7', '3', '10', '41', '104', '1', '12', '6', '8']
        ],
        'Технологии' => [
            'question' => 'В каком году был выпущен первый iPhone?',
            'correct' => '2007',
            'fakes' => ['2005', '2006', '2008', '2009', '2010', '2004', '2011', '2003', '2012', '2013']
        ],
        'Космос' => [
            'question' => 'Какая планета Солнечной системы является самой большой?',
            'correct' => 'Юпитер',
            'fakes' => ['Сатурн', 'Нептун', 'Уран', 'Земля', 'Марс', 'Венера', 'Меркурий', 'Плутон', 'Церера', 'Харон']
        ],
        'Еда' => [
            'question' => 'Какой фрукт является основным ингредиентом для традиционного соуса гуакамоле?',
            'correct' => 'Авокадо',
            'fakes' => ['Манго', 'Банан', 'Папайя', 'Лайм', 'Ананас', 'Киви', 'Грейпфрут', 'Апельсин', 'Томат', 'Груша']
        ],
        'Природа' => [
            'question' => 'Какое млекопитающее является самым крупным на Земле?',
            'correct' => 'Синий кит',
            'fakes' => ['Африканский слон', 'Кашалот', 'Белая акула', 'Гигантский кальмар', 'Косатка', 'Жираф', 'Бегемот', 'Гренландский кит', 'Южный гладкий кит', 'Слон']
        ]
    ];

    // Приведем тему к нижнему регистру и очистим от лишних пробелов для лучшего сопоставления
    $cleanTopic = mb_strtolower(trim($topic));

    if (!empty($cleanTopic)) {
        // Ищем точное или частичное совпадение
        foreach ($questions as $key => $data) {
            $keyLower = mb_strtolower($key);
            if ($cleanTopic === $keyLower || mb_strpos($cleanTopic, $keyLower) !== false || mb_strpos($keyLower, $cleanTopic) !== false) {
                return $data;
            }
        }
    }

    // Если совпадений нет, выберем случайную тему из списка для разнообразия
    $keys = array_keys($questions);
    $randomKey = $keys[array_rand($keys)];
    return $questions[$randomKey];
}



/*
 * ИНСТРУКЦИИ ПО НАСТРОЙКЕ GROQ API:
 *
 * 1. ПОЛУЧИТЬ КЛЮЧ GROQ API:
 *    - Перейти: https://console.groq.com/keys
 *    - Войти с аккаунтом Google или GitHub
 *    - Создать новый API ключ (Create API Key)
 *    - Скопировать ключ
 *
 * 2. ДОБАВИТЬ КЛЮЧ В config.php:
 *    define('GROQ_API_KEY', 'ваш_ключ_здесь');
 *    
 *    Или установить переменную окружения:
 *    export GROQ_API_KEY='ваш_ключ_здесь'
 *
 * 3. ПРОВЕРИТЬ РАБОТОСПОСОБНОСТЬ:
 *    - Открыть страницу test_ai.php в браузере
 *    - При ошибках смотреть результаты тестов на странице
 *
 * 4. ИНФОРМАЦИЯ О МОДЕЛИ:
 *    - Модель: Llama 3.3 70B Versatile
 *    - API версия: OpenAI Compatible (v1)
 *    - Эндпоинт: https://api.groq.com/openai/v1/chat/completions
 *    - Документация: https://console.groq.com/docs/models
 *    - Скорость: ~280 tokens/sec
 *
 * 5. ОГРАНИЧЕНИЯ И ЦЕНЫ:
 *    - Входные токены: $0.59 за 1M
 *    - Выходные токены: $0.79 за 1M
 *    - TPM (Tokens Per Minute): 300,000
 *    - RPM (Requests Per Minute): 1,000
 *    - Context window: 131,072 tokens
 *    - Max completion: 32,768 tokens
 *    - Проверить актуальные цены: https://console.groq.com/docs/models
 *
 * 6. ПОДДЕРЖИВАЕМЫЕ ПАРАМЕТРЫ:
 *    - temperature: 0.0 - 2.0 (по умолчанию 0.7)
 *    - max_tokens: максимум токенов в ответе
 *    - top_p: 0.0 - 1.0 (ядро выборки)
 *
 * 7. ОБРАБОТКА ОШИБОК:
 *    - HTTP 400: неверный запрос (проверить JSON структуру)
 *    - HTTP 401: неверный API ключ (проверить GROQ_API_KEY)
 *    - HTTP 429: превышен лимит запросов
 *    - HTTP 500: ошибка сервера Groq
 */
?>