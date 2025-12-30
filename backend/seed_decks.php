<?php
// backend/seed_decks.php
require 'db.php';
require 'core/DeckGenerator.php';

echo "正在初始化牌局库...\n";

// 检查当前数量
$stmt = $pdo->query("SELECT count(*) FROM pre_decks");
$result = $stmt->fetchColumn();

// --- [修复点] 强制转为整数，防止 false 导致 Undefined variable ---
$count = $result ? intval($result) : 0;

if ($count >= 320) {
    echo "库存已满 ($count)，无需生成。\n";
} else {
    $needed = 320 - $count;
    echo "当前库存 {$count}，正在补充 {$needed} 局...\n";
    
    // 调用生成器
    DeckGenerator::fill($pdo, $needed);
    
    echo "生成完成！\n";
}
?>