<?php
require_once 'core/ai_handler.php';
header('Content-Type: application/json');
echo json_encode(generateQuestionWithGroq('Кино'));
