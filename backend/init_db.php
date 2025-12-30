<?php
// backend/init_db.php

function loadEnv($path) {
    if (!file_exists($path)) return;
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        list($name, $value) = explode('=', $line, 2);
        putenv(trim($name) . '=' . trim($value));
    }
}
loadEnv(__DIR__ . '/.env');

$host = getenv('DB_HOST');
$db   = getenv('DB_DATABASE');
$user = getenv('DB_USERNAME');
$pass = getenv('DB_PASSWORD');

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "数据库连接成功...\n";

    // 1. 用户表
    $pdo->exec("CREATE TABLE IF NOT EXISTS `users` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `mobile` VARCHAR(20) NOT NULL UNIQUE,
        `game_id` VARCHAR(10) NOT NULL UNIQUE,
        `password_hash` VARCHAR(255) NOT NULL,
        `points` INT DEFAULT 1000,
        `api_token` VARCHAR(64) NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // 2. Telegram 管理员表 (存储 Chat ID)
    $pdo->exec("CREATE TABLE IF NOT EXISTS `tg_admins` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `chat_id` VARCHAR(50) NOT NULL UNIQUE,
        `description` VARCHAR(50) DEFAULT 'Admin',
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // 3. 预设牌局库
    $pdo->exec("CREATE TABLE IF NOT EXISTS `pre_decks` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `cards_json` JSON NOT NULL, 
        `solutions_json` JSON NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // 4. 游戏车厢
    $pdo->exec("CREATE TABLE IF NOT EXISTS `game_sessions` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `score_level` INT NOT NULL,
        `status` ENUM('recruiting', 'active', 'finished') DEFAULT 'recruiting',
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // 5. 车厢玩家 (级联删除：如果用户被删，这里记录也删)
    $pdo->exec("CREATE TABLE IF NOT EXISTS `session_players` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `session_id` INT NOT NULL,
        `user_id` INT NOT NULL,
        `seat_index` TINYINT NOT NULL,
        `deck_order` JSON NULL, 
        `current_step` INT DEFAULT 1,
        `is_finished` TINYINT DEFAULT 0,
        UNIQUE KEY `idx_session_user` (`session_id`, `user_id`),
        FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // 6. 玩家操作记录 (级联删除)
    $pdo->exec("CREATE TABLE IF NOT EXISTS `game_actions` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `session_id` INT NOT NULL,
        `deck_id` INT NOT NULL,
        `user_id` INT NOT NULL,
        `hand_arranged` JSON NOT NULL,
        `score_result` INT DEFAULT 0,
        `is_settled` TINYINT DEFAULT 0,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY `idx_action` (`session_id`, `deck_id`, `user_id`),
        FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    echo "数据库表结构更新完毕！\n";

} catch (PDOException $e) {
    die("DB Error: " . $e->getMessage() . "\n");
}
?>