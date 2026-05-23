CREATE DATABASE IF NOT EXISTS quete_db;
USE quete_db;

-- =========================================================================
-- СТРУКТУРА ТАБЛИЦ БАЗЫ ДАННЫХ ИГРЫ "QUETE"
-- Код полностью готов к копированию и выполнению в phpMyAdmin в любом месте.
-- =========================================================================

-- 1. Таблица пользователей (Профили и глобальная статистика)
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    wins_count INT DEFAULT 0,
    total_answers INT DEFAULT 0,
    correct_answers INT DEFAULT 0,
    last_game_score INT DEFAULT 0,
    ws_ticket VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 2. Таблица лобби (Игровые комнаты)
CREATE TABLE IF NOT EXISTS lobbies (
    id INT AUTO_INCREMENT PRIMARY KEY,
    host_id INT,
    lobby_name VARCHAR(100) NOT NULL,
    password VARCHAR(50) DEFAULT NULL,
    max_players INT DEFAULT 8,
    fake_answer_time INT DEFAULT 60,
    is_active BOOLEAN DEFAULT FALSE,
    current_round INT DEFAULT 1,
    responsible INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (host_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (responsible) REFERENCES users(id) ON DELETE SET NULL
);

-- 3. Таблица участников лобби (Текущая сессия игроков)
CREATE TABLE IF NOT EXISTS lobby_players (
    id INT AUTO_INCREMENT PRIMARY KEY,
    lobby_id INT,
    user_id INT,
    current_points INT DEFAULT 0,
    is_ready BOOLEAN DEFAULT FALSE,
    avatar_id INT DEFAULT 1,
    FOREIGN KEY (lobby_id) REFERENCES lobbies(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY idx_lobby_user (lobby_id, user_id)
);

-- 4. Таблица сгенерированных вопросов (С JSON полем для эталонных фейков ИИ)
CREATE TABLE IF NOT EXISTS generated_questions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    lobby_id INT,
    topic VARCHAR(50) NOT NULL,
    question_text TEXT NOT NULL,
    correct_answer TEXT NOT NULL,
    fake_answers JSON NOT NULL,
    round_number INT NOT NULL DEFAULT 1,
    question_number INT NOT NULL DEFAULT 1,
    is_active BOOLEAN DEFAULT FALSE,
    points_awarded TINYINT(1) NOT NULL DEFAULT 0,
    auto_fakes_applied TINYINT(1) NOT NULL DEFAULT 0,
    auto_topic_selected BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (lobby_id) REFERENCES lobbies(id) ON DELETE CASCADE,
    INDEX idx_lobby_active_round (lobby_id, is_active, round_number)
);

-- 5. Таблица фейковых ответов (Ложь, придуманная игроками)
CREATE TABLE IF NOT EXISTS player_answers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    question_id INT,
    user_id INT,
    answer_text TEXT NOT NULL,
    is_auto_selected BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (question_id) REFERENCES generated_questions(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- 6. Таблица голосования (Выборы ответов игроками)
CREATE TABLE IF NOT EXISTS votes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    question_id INT,
    voter_id INT,
    selected_answer_text TEXT NOT NULL,
    FOREIGN KEY (question_id) REFERENCES generated_questions(id) ON DELETE CASCADE,
    FOREIGN KEY (voter_id) REFERENCES users(id) ON DELETE CASCADE
);

-- 7. Таблица внутриигрового чата
CREATE TABLE IF NOT EXISTS chat_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    lobby_id INT,
    user_id INT,
    message TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (lobby_id) REFERENCES lobbies(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_lobby_created (lobby_id, created_at)
);

-- =========================================================================
-- ЗАПРОСЫ ДЛЯ ОБНОВЛЕНИЯ СУЩЕСТВУЮЩЕЙ БД (ЕСЛИ ТАБЛИЦЫ УЖЕ БЫЛИ СОЗДАНЫ):
-- Скопируйте и запустите в phpMyAdmin -> вкладка SQL при необходимости:
--
-- ALTER TABLE users ADD COLUMN ws_ticket VARCHAR(255) DEFAULT NULL;
-- ALTER TABLE lobbies ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP;
-- ALTER TABLE generated_questions ADD COLUMN points_awarded TINYINT(1) NOT NULL DEFAULT 0;
-- ALTER TABLE generated_questions ADD COLUMN auto_fakes_applied TINYINT(1) NOT NULL DEFAULT 0;
-- ALTER TABLE generated_questions ADD COLUMN auto_topic_selected BOOLEAN DEFAULT FALSE;
-- ALTER TABLE player_answers ADD COLUMN is_auto_selected BOOLEAN DEFAULT FALSE;
-- ALTER TABLE lobby_players ADD COLUMN avatar_id INT DEFAULT 1;
--
-- -- Индексы для оптимизации:
-- ALTER TABLE lobby_players ADD UNIQUE INDEX idx_lobby_user (lobby_id, user_id);
-- ALTER TABLE generated_questions ADD INDEX idx_lobby_active_round (lobby_id, is_active, round_number);
-- ALTER TABLE chat_messages ADD INDEX idx_lobby_created (lobby_id, created_at);
-- =========================================================================