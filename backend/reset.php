<?php
// reset.php
require 'db.php';
$pdo->exec("TRUNCATE TABLE pre_decks"); // 清空牌局库
$pdo->exec("TRUNCATE TABLE session_players"); // 清空玩家进度，防止出错
$pdo->exec("TRUNCATE TABLE game_actions"); // 清空游戏记录
echo "数据已清空。\n";
?>