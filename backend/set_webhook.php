<?php
// backend/set_webhook.php

ini_set('display_errors', 1);
error_reporting(E_ALL);

// 包含了 db.php 会自动处理 .env 和数据库连接
require_once __DIR__ . '/db.php';

// 检查 $config 是否被成功加载
if (empty($config['BOT_TOKEN']) || empty($config['WEBAPP_URL'])) {
    die("错误: BOT_TOKEN 或 WEBAPP_URL 未在 .env 文件中定义。");
}

$botToken = $config['BOT_TOKEN'];
$webhookUrl = rtrim($config['WEBAPP_URL'], '/') . '/bot.php';

$apiUrl = "https://api.telegram.org/bot{$botToken}/setWebhook?url=" . urlencode($webhookUrl);

// 使用最基础的 file_get_contents 发送请求
$response = @file_get_contents($apiUrl);

// --- 显示结果给用户 ---
header('Content-Type: text/html; charset=utf-8');
echo "<!DOCTYPE html><html><head><title>Webhook Setter</title></head><body>";
echo "<h1>Telegram Webhook 设置工具</h1>";

if ($response === false) {
    echo "<p style='color:red;'><b>请求失败！</b>无法连接到 Telegram API。请检查服务器网络或 Bot Token 是否正确。</p>";
} else {
    echo "<p>已尝试将 Webhook 设置为:</p>";
    echo "<pre>" . htmlspecialchars($webhookUrl) . "</pre>";
    echo "<p>Telegram 服务器返回的结果:</p>";
    echo "<pre>" . htmlspecialchars($response) . "</pre>";

    $responseData = json_decode($response, true);
    if ($responseData && $responseData['ok']) {
        echo "<p style='color:green;'><b>成功！</b>你的机器人现在应该可以接收消息了。</p>";
    } else {
        echo "<p style='color:red;'><b>失败！</b>请检查上面的错误信息。</p>";
    }
}

echo "</body></html>";
