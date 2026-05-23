<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../core/db.php';

try {
    $pdo = getPDO();
    echo "Connected to database successfully.\n";

    // 1. points_awarded
    $stmt = $pdo->query("SHOW COLUMNS FROM generated_questions LIKE 'points_awarded'");
    if (!$stmt->fetch()) {
        $pdo->exec("ALTER TABLE generated_questions ADD COLUMN points_awarded TINYINT(1) NOT NULL DEFAULT 0");
        echo "Column 'points_awarded' added.\n";
    } else {
        echo "Column 'points_awarded' already exists.\n";
    }

    // 2. ws_ticket
    $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'ws_ticket'");
    if (!$stmt->fetch()) {
        $pdo->exec("ALTER TABLE users ADD COLUMN ws_ticket VARCHAR(255) DEFAULT NULL");
        echo "Column 'ws_ticket' added.\n";
    } else {
        echo "Column 'ws_ticket' already exists.\n";
    }

    // 3. auto_fakes_applied
    $stmt = $pdo->query("SHOW COLUMNS FROM generated_questions LIKE 'auto_fakes_applied'");
    if (!$stmt->fetch()) {
        $pdo->exec("ALTER TABLE generated_questions ADD COLUMN auto_fakes_applied TINYINT(1) NOT NULL DEFAULT 0");
        echo "Column 'auto_fakes_applied' added.\n";
    } else {
        echo "Column 'auto_fakes_applied' already exists.\n";
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
