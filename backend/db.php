<?php
// backend/db.php

// 1. 错误报告设置
ini_set('display_errors', 0); // Web 环境下先关闭直接输出，防止 502
error_reporting(E_ALL);

// 2. 这里的路径必须绝对准确。既然文件在根目录或 backend 下，我们兼容处理
$envPath = __DIR__ . '/.env';
if (!file_exists($envPath)) {
    $envPath = $_SERVER['DOCUMENT_ROOT'] . '/.env';
}

$config = [];
if (file_exists($envPath)) {
    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        if (strpos($line, '=') !== false) {
            list($name, $value) = explode('=', $line, 2);
            $config[trim($name)] = trim($value, "\"' ");
        }
    }
}

// 3. 建立数据库连接
try {
    $host = $config['DB_HOST'] ?? '';
    $db   = $config['DB_DATABASE'] ?? '';
    $user = $config['DB_USERNAME'] ?? '';
    $pass = $config['DB_PASSWORD'] ?? '';

    if (empty($host) || empty($db) || empty($user)) {
        throw new Exception("Config missing");
    }

    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

} catch (Exception $e) {
    // 只有在命令行才打印，Web 环境直接记录日志
    if (php_sapi_name() === 'cli') {
        echo "DB Error: " . $e->getMessage() . "\n";
    }
    error_log("Database connection failed: " . $e->getMessage());
}

/**
 * 认证函数
 */
function authenticate($pdo) {
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
        $token = $matches[1];
        $stmt = $pdo->prepare("SELECT * FROM users WHERE api_token = ?");
        $stmt->execute([$token]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($user) return $user;
    }
    header('HTTP/1.1 401 Unauthorized');
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}
