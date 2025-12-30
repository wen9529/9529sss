<?php

// Function to load .env variables
function loadEnv($path) {
    if (!file_exists($path)) return;
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        list($name, $value) = explode('=', $line, 2);
        putenv(trim($name) . "=" . trim($value));
    }
}

loadEnv(__DIR__ . '/.env');

$host = getenv('DB_HOST');
$dbname = getenv('DB_NAME');
$user = getenv('DB_USER');
$pass = getenv('DB_PASS');

try {
    $pdo = new PDO("mysql:host=$host", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Create database if not exists
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbname` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE `$dbname`;");

    echo "Database created or already exists.\n";

    // Create Tables
    $sql = "
    CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        auth_token VARCHAR(255) DEFAULT NULL,
        points INT DEFAULT 1000,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    );

    CREATE TABLE IF NOT EXISTS games (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        status ENUM('waiting', 'playing', 'finished') DEFAULT 'waiting',
        turn INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    );

    CREATE TABLE IF NOT EXISTS players (
        id INT AUTO_INCREMENT PRIMARY KEY,
        game_id INT,
        user_id INT,
        position INT,
        FOREIGN KEY (game_id) REFERENCES games(id) ON DELETE CASCADE
    );

    CREATE TABLE IF NOT EXISTS hands (
        id INT AUTO_INCREMENT PRIMARY KEY,
        player_id INT,
        card VARCHAR(10) NOT NULL,
        FOREIGN KEY (player_id) REFERENCES players(id) ON DELETE CASCADE
    );

    CREATE TABLE IF NOT EXISTS board (
        id INT AUTO_INCREMENT PRIMARY KEY,
        game_id INT,
        card VARCHAR(10) NOT NULL,
        FOREIGN KEY (game_id) REFERENCES games(id) ON DELETE CASCADE
    );
    ";

    $pdo->exec($sql);
    echo "Tables created successfully.\n";

} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}
