<?php
session_start();
require_once 'core/db.php';
require_once 'core/ai_handler.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user = getUserById($_SESSION['user_id']);

// Состояния соло-игры: topic, play, result
$state = $_GET['state'] ?? 'topic';
$topic = $_SESSION['solo_topic'] ?? '';
$currentQuestionNum = (int)($_SESSION['solo_q_num'] ?? 0);
$score = (int)($_SESSION['solo_score'] ?? 0);
$questions = $_SESSION['solo_questions'] ?? [];

// Обработка действий
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!verifyCsrfToken($csrfToken)) {
        die('Invalid CSRF token');
    }
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'start':
                $topic = trim($_POST['topic'] ?? 'Общее');
                $previousQuestions = [];
                $qData = generateQuestionWithGroq($topic, $previousQuestions);
                if (!$qData || !$qData['valid']) {
                    $qData = generateQuestionStub($topic);
                    $qData['valid'] = true;
                    $_SESSION['solo_ai_error'] = true;
                } else {
                    unset($_SESSION['solo_ai_error']);
                }
                if ($qData['valid']) {
                    $_SESSION['solo_topic'] = $topic;
                    $_SESSION['solo_q_num'] = 1;
                    $_SESSION['solo_score'] = 0;
                    
                    // Генерируем сразу 3 вопроса для стабильности или по одному?
                    // По заданию: "отвечает на три вопроса". Сгенерируем первый.
                    $q1 = $qData;
                    // Перемешиваем ответы: 1 правильный + 5 фейковых
                    $all_answers = array_merge([$q1['correct']], array_slice($q1['fakes'], 0, 5));
                    shuffle($all_answers);
                    $q1['shuffled_answers'] = $all_answers;
                    
                    $_SESSION['solo_questions'] = [1 => $q1];
                    header('Location: solo.php?state=play');
                    exit;
                }
                break;
                
            case 'answer':
                $selected = $_POST['answer'] ?? '';
                $currentQ = $questions[$currentQuestionNum];
                if ($selected === $currentQ['correct']) {
                    $_SESSION['solo_score']++;
                    $is_correct = true;
                } else {
                    $is_correct = false;
                }
                
                $_SESSION['last_answer_correct'] = $is_correct;
                $_SESSION['last_answer_selected'] = $selected;
                
                header('Location: solo.php?state=check');
                exit;
                
            case 'next':
                if ($currentQuestionNum < 3) {
                    $nextNum = $currentQuestionNum + 1;
                    $previousQuestions = [];
                    foreach ($questions as $q) {
                        if (isset($q['question'])) {
                            $previousQuestions[] = $q['question'];
                        }
                    }
                    $qData = generateQuestionWithGroq($topic, $previousQuestions);
                    if (!$qData || !$qData['valid']) {
                        $qData = generateQuestionStub($topic);
                        $qData['valid'] = true;
                        $_SESSION['solo_ai_error'] = true;
                    }
                    if ($qData['valid']) {
                        $all_answers = array_merge([$qData['correct']], array_slice($qData['fakes'], 0, 5));
                        shuffle($all_answers);
                        $qData['shuffled_answers'] = $all_answers;
                        
                        $_SESSION['solo_questions'][$nextNum] = $qData;
                        $_SESSION['solo_q_num'] = $nextNum;
                        header('Location: solo.php?state=play');
                        exit;
                    }
                } else {
                    header('Location: solo.php?state=result');
                    exit;
                }
                break;
                
            case 'restart':
                unset(
                    $_SESSION['solo_topic'],
                    $_SESSION['solo_q_num'],
                    $_SESSION['solo_score'],
                    $_SESSION['solo_questions'],
                    $_SESSION['solo_ai_error'],
                    $_SESSION['solo_round']
                );
                header('Location: solo.php?state=topic');
                exit;
        }
    }
}

include 'views/header.php';
?>
<link rel="stylesheet" href="assets/css/game.css">

<script nonce="<?php echo CSP_NONCE; ?>">
function showSoloGeneratingMessage() {
    // Немедленно отключаем кнопки для предотвращения двойных кликов
    const btns = document.querySelectorAll('.btn-game, .card-btn');
    btns.forEach(b => {
        b.disabled = true;
        b.style.opacity = '0.5';
        b.style.cursor = 'not-allowed';
    });

    const mainContent = document.querySelector('.main-content');
    if (mainContent) {
        // Задерживаем очистку DOM на 50мс, чтобы браузер успел отправить POST-запрос
        setTimeout(() => {
            mainContent.innerHTML = `
                <div class="panel" style="width: 100%; max-width: 500px; text-align: center; padding: 40px 20px; background: var(--panel-bg); border-radius: 20px; box-shadow: 0 10px 20px rgba(0,0,0,.2);">
                    <div class="panel-title" style="font-size: 24px; color: #ffeb3b; margin-bottom: 20px; text-align: center;">Пожалуйста, подождите</div>
                    <div style="font-size: 18px; color: #fff; line-height: 1.6; font-weight: bold; text-align: center;">
                        Генерируем вопрос. Пожалуйста, подождите
                    </div>
                    <div style="text-align: center; margin-top: 30px; font-size: 40px;">⏳</div>
                </div>
            `;
        }, 50);
    }
}

function handleSoloCheckSubmit(event, isNextQuestion) {
    if (isNextQuestion) {
        showSoloGeneratingMessage();
    }
}

document.addEventListener('DOMContentLoaded', () => {
    const checkSeconds = document.getElementById('solo-check-seconds');
    if (checkSeconds) {
        let left = 10;
        const interval = setInterval(() => {
            left--;
            checkSeconds.textContent = left;
            if (left <= 0) {
                clearInterval(interval);
                const section = checkSeconds.closest('.result-section');
                if (section) {
                    const form = section.querySelector('form');
                    if (form) {
                        const isNextQuestion = <?php echo ($currentQuestionNum < 3) ? 'true' : 'false'; ?>;
                        if (isNextQuestion) {
                            showSoloGeneratingMessage();
                        }
                        form.submit();
                    }
                }
            }
        }, 1000);
    }
});
</script>

<div class="page-wrapper">
    <div class="game-window">
        <div class="window-header">
            <div class="window-title">СОЛО ИГРА<?php echo $topic ? ' - ' . htmlspecialchars($topic) : ''; ?></div>
            <div style="position: absolute; right: 60px; top: 13px; font-size: 12px; color: #fff; opacity: 0.8;">
                Вопрос: <?php echo $currentQuestionNum; ?>/3 | Счёт: <?php echo $score; ?>/3
            </div>
            <a href="javascript:void(0)" class="user-icon" onclick="toggleStatsPopup()" style="right: 10px;">
                <svg viewBox="0 0 24 24">
                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                    <circle cx="12" cy="7" r="4"></circle>
                </svg>
            </a>
            <?php $showLeaveButton = false; include 'views/user_stats.php'; ?>
        </div>
        <div class="main-content">
            <?php if (!empty($_SESSION['solo_ai_error'])): ?>
                <div class="ai-warning-banner" style="background: rgba(244, 67, 54, 0.15); border: 2px dashed #f44336; border-radius: 8px; padding: 15px; margin-bottom: 20px; color: #fff; text-align: center; width: 100%; max-width: 600px; box-sizing: border-box;">
                    <div style="font-weight: bold; font-size: 16px; margin-bottom: 5px; color: #ffeb3b;">⚠️ Проблема с ИИ</div>
                    ИИ временно недоступен. Игра переведена в автономный режим. Пожалуйста, подождите или выйдите в хаб.
                </div>
            <?php endif; ?>
            <?php if ($state === 'topic'): ?>
                <div class="panel">
                    <div class="panel-title">Выберите тему для викторины</div>
                    <form method="POST" class="auth-form" onsubmit="showSoloGeneratingMessage()">
                        <input type="hidden" name="action" value="start">
                        <input type="hidden" name="csrf_token" value="<?php echo getCsrfToken(); ?>">
                        <input type="text" name="topic" class="form-input" placeholder="Введите тему (например: Космос)" required autocomplete="off">
                        <div class="btn-row">
                            <button type="submit" class="btn-game">Начать</button>
                            <a href="hub.php" class="btn-game" style="text-decoration:none; text-align:center; line-height:30px;">В хаб</a>
                        </div>
                    </form>
                </div>

            <?php elseif ($state === 'play'): ?>
                <?php $q = $questions[$currentQuestionNum]; ?>
                <div class="question-section">
                    <div class="question-text"><?php echo htmlspecialchars($q['question']); ?></div>
                </div>
                <div class="answers-grid">
                    <form method="POST" style="display:contents;">
                        <input type="hidden" name="action" value="answer">
                        <input type="hidden" name="csrf_token" value="<?php echo getCsrfToken(); ?>">
                        <?php foreach ($q['shuffled_answers'] as $ans): ?>
                            <button type="submit" name="answer" value="<?php echo htmlspecialchars($ans); ?>" class="card-btn">
                                <?php echo htmlspecialchars($ans); ?>
                            </button>
                        <?php endforeach; ?>
                    </form>
                </div>

            <?php elseif ($state === 'check'): ?>
                <?php 
                $q = $questions[$currentQuestionNum]; 
                $was_correct = $_SESSION['last_answer_correct'];
                $selected = $_SESSION['last_answer_selected'];
                $is_next_question = ($currentQuestionNum < 3);
                ?>
                <div class="result-section">
                    <?php if ($was_correct): ?>
                        <div class="status-msg success">Правильно!</div>
                    <?php else: ?>
                        <div class="status-msg error">Неверно!</div>
                        <div class="sub-text">Ваш ответ: <span class="wrong-text"><?php echo htmlspecialchars($selected); ?></span></div>
                    <?php endif; ?>
                    <div class="correct-reveal">Правильный ответ: <span class="correct-text"><?php echo htmlspecialchars($q['correct']); ?></span></div>
                    
                    <form method="POST" style="margin-top: 30px;" onsubmit="handleSoloCheckSubmit(event, <?php echo $is_next_question ? 'true' : 'false'; ?>)">
                        <input type="hidden" name="action" value="next">
                        <input type="hidden" name="csrf_token" value="<?php echo getCsrfToken(); ?>">
                        <button type="submit" class="btn-game">
                            <?php 
                            if ($is_next_question) {
                                echo 'Следующий вопрос';
                            } else {
                                echo 'Посмотреть итог';
                            }
                            ?>
                        </button>
                    </form>
                    
                    <div style="text-align: center; margin-top: 20px; color: #999; font-size: 14px;" id="solo-check-countdown-text">
                        Автоматический переход через <span id="solo-check-seconds" style="font-weight: bold; color: #ffeb3b;">10</span> сек...
                    </div>
                </div>

            <?php elseif ($state === 'result'): ?>
                <div class="podium-section">
                    <div class="panel-title">Игра окончена!</div>
                    <div class="final-score">Ваш результат: <?php echo $score; ?> из 3</div>
                    <div class="btn-row" style="margin-top: 40px;">
                        <form method="POST" style="display:contents;">
                            <input type="hidden" name="action" value="restart">
                            <input type="hidden" name="csrf_token" value="<?php echo getCsrfToken(); ?>">
                            <button type="submit" class="btn-game">Продолжить (Новая тема)</button>
                        </form>
                        <a href="hub.php" class="btn-game" style="text-decoration:none; text-align:center; line-height:30px;">Выйти в хаб</a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style nonce="<?php echo CSP_NONCE; ?>">
/* Дополнительные стили для соло-режима, не ломающие общую сетку */
.main-content { padding: 20px; display: flex; flex-direction: column; align-items: center; justify-content: center; min-height: 300px; }
.question-section { margin-bottom: 30px; text-align: center; width: 100%; max-width: 600px; }
.question-text { font-size: 24px; color: #fff; text-shadow: 2px 2px 4px rgba(0,0,0,0.5); word-wrap: break-word; overflow-wrap: break-word; }
.answers-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 15px; width: 100%; max-width: 600px; }
.card-btn { background: rgba(255,255,255,0.1); border: 2px solid rgba(255,255,255,0.2); padding: 20px; border-radius: 12px; color: #fff; cursor: pointer; transition: all 0.3s; font-size: 18px; word-wrap: break-word; overflow-wrap: break-word; hyphens: auto; max-width: 100%; }
.card-btn:hover { background: rgba(255,255,255,0.2); transform: translateY(-3px); border-color: #fff; }
.status-msg { font-size: 32px; font-weight: bold; margin-bottom: 10px; word-wrap: break-word; overflow-wrap: break-word; }
.status-msg.success { color: #4CAF50; }
.status-msg.error { color: #f44336; }
.correct-reveal { font-size: 20px; color: #fff; margin-top: 10px; word-wrap: break-word; overflow-wrap: break-word; }
.correct-text { color: #4CAF50; font-weight: bold; }
.wrong-text { color: #f44336; text-decoration: line-through; }
.final-score { font-size: 28px; color: #ffeb3b; margin-top: 20px; word-wrap: break-word; overflow-wrap: break-word; }
.header-stats { color: #fff; font-size: 14px; opacity: 0.8; }

/* Адаптивный мобильный дизайн соло-режима */
@media (max-width: 600px) {
    .question-text { font-size: 18px; }
    .answers-grid { grid-template-columns: 1fr; }
    .card-btn { font-size: 15px; padding: 15px 10px; }
    .status-msg { font-size: 24px; }
    .correct-reveal { font-size: 16px; }
    .final-score { font-size: 22px; }
}
</style>

<?php include 'views/footer.php'; ?>
