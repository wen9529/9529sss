<?php
// backend/bot.php

ini_set('display_errors', 0);
error_reporting(E_ALL);

// 1. 包含核心文件并加载配置
require_once __DIR__ . '/db.php';

// 2. 日志记录 (非常重要!)
function bot_log($message) {
    $log_message = date('[Y-m-d H:i:s]') . " " . $message . "\n";
    // LOG_FILE_PATH 应该在你的 .env 中定义，例如 /path/to/your/logs/bot.log
    $log_file = $GLOBALS['config']['LOG_FILE_PATH'] ?? __DIR__ . '/bot.log';
    error_log($log_message, 3, $log_file);
}

// 3. 发送消息函数
function sendTgMessage($chatId, $text, $botToken, $replyMarkup = null) {
    $url = "https://api.telegram.org/bot{$botToken}/sendMessage";
    $postFields = ['chat_id' => $chatId, 'text' => $text, 'parse_mode' => 'Markdown'];
    if ($replyMarkup) {
        $postFields['reply_markup'] = json_encode($replyMarkup);
    }
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $result = curl_exec($ch);
    curl_close($ch);
    bot_log("Sent message to {$chatId}. Response: {$result}");
    return $result;
}

// --- 程序开始 ---

$botToken = $config['BOT_TOKEN'];
$update = json_decode(file_get_contents('php://input'), true);

if (!$update) {
    // 如果没有 webhook 调用，则不执行任何操作
    exit('This is a webhook handler.');
}

bot_log("Received update: " . json_encode($update));

$message = $update['message'] ?? null;
$chatId = $message['chat']['id'] ?? null;
$text = $message['text'] ?? '';

if (!$chatId) {
    bot_log("No chatId found.");
    exit;
}

// 简单的状态管理 (使用临时文件)
$sessionFile = sys_get_temp_dir() . "/tg_sess_" . $chatId . ".json";
$session = file_exists($sessionFile) ? json_decode(file_get_contents($sessionFile), true) : ['step' => 'idle'];

// 键盘定义
$mainKeyboard = [
    'keyboard' => [[['text' => '📦 库存检查'], ['text' => '👥 用户查询']], [['text' => '➕ 增加积分'], ['text' => '➖ 扣除积分']]],
    'resize_keyboard' => true
];
$cancelKeyboard = ['keyboard' => [[['text' => '🔙 取消/返回']]], 'resize_keyboard' => true];

// --- 核心逻辑 ---

// 重置操作
if ($text === '/start' || $text === '🔙 取消/返回') {
    @unlink($sessionFile);
    sendTgMessage($chatId, "👋 您好！请选择管理操作：", $botToken, $mainKeyboard);
    exit;
}

// 根据会话状态处理
$step = $session['step'];
switch ($step) {
    case 'awaiting_recharge_phone':
        // ... (省略具体实现，保持框架)
        sendTgMessage($chatId, "功能开发中...", $botToken, $mainKeyboard);
        @unlink($sessionFile);
        break;

    case 'awaiting_deduct_phone':
        // ...
        sendTgMessage($chatId, "功能开发中...", $botToken, $mainKeyboard);
        @unlink($sessionFile);
        break;
        
    // ... 其他 case

    default: // idle 状态
        switch ($text) {
            case '📦 库存检查':
                if (isset($pdo)) {
                    $stmt = $pdo->query("SELECT game_level, COUNT(*) as count FROM rooms WHERE is_used = 0 GROUP BY game_level");
                    $stocks = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    $responseText = "📊 *库存统计:*\n";
                    if (empty($stocks)) {
                        $responseText .= "所有等级的库存均为 0。";
                    } else {
                        foreach ($stocks as $stock) {
                            $responseText .= "- 等级 `" . htmlspecialchars($stock['game_level']) . "`: 剩余 `" . htmlspecialchars($stock['count']) . "` 局\n";
                        }
                    }
                    sendTgMessage($chatId, $responseText, $botToken, $mainKeyboard);
                } else {
                    sendTgMessage($chatId, "数据库连接失败，无法查询库存。", $botToken, $mainKeyboard);
                }
                break;

            case '👥 用户查询':
                $session['step'] = 'awaiting_user_phone';
                file_put_contents($sessionFile, json_encode($session));
                sendTgMessage($chatId, "请输入要查询的手机号：", $botToken, $cancelKeyboard);
                break;
            
            case '➕ 增加积分':
                $session['step'] = 'awaiting_recharge_phone';
                file_put_contents($sessionFile, json_encode($session));
                sendTgMessage($chatId, "请输入要充值的用户手机号：", $botToken, $cancelKeyboard);
                break;

            default:
                sendTgMessage($chatId, "请使用下方菜单进行操作。", $botToken, $mainKeyboard);
                break;
        }
        break;
}
