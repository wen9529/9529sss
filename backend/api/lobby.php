<?php
// backend/api/lobby.php
require '../db.php';
require_once '../core/Logic.php';

$user = authenticate($pdo);
$action = $_GET['action'] ?? '';

if ($action === 'join') {
    $scoreLevel = $_GET['level'] ?? 100;

    try {
        $pdo->beginTransaction();

        // 1. 寻找正在招募的车厢
        $stmt = $pdo->prepare("SELECT id FROM game_sessions WHERE score_level = ? AND status = 'recruiting' LIMIT 1 FOR UPDATE");
        $stmt->execute([$scoreLevel]);
        $session = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$session) {
            // 创建新车厢
            $stmt = $pdo->prepare("INSERT INTO game_sessions (score_level, status) VALUES (?, 'recruiting')");
            $stmt->execute([$scoreLevel]);
            $sessionId = $pdo->lastInsertId();
        } else {
            $sessionId = $session['id'];
        }

        // 2. 检查用户是否已经在车里
        $stmt = $pdo->prepare("SELECT id FROM session_players WHERE session_id = ? AND user_id = ?");
        $stmt->execute([$sessionId, $user['id']]);
        if ($stmt->fetch()) {
            $pdo->rollBack();
            echo json_encode(['status' => 'success', 'session_id' => $sessionId]);
            exit;
        }

        // 3. 计算当前人数
        $stmt = $pdo->prepare("SELECT COUNT(*) as cnt FROM session_players WHERE session_id = ?");
        $stmt->execute([$sessionId]);
        $count = $stmt->fetch(PDO::FETCH_ASSOC)['cnt'];

        if ($count >= 4) {
             $pdo->rollBack();
             echo json_encode(['status' => 'error', 'message' => '车厢已满']);
             exit;
        }

        // 4. 加入车厢
        $deckOrder = GameLogic::generateDeckOrder();
        $stmt = $pdo->prepare("INSERT INTO session_players (session_id, user_id, seat_index, deck_order) VALUES (?, ?, ?, ?)");
        $stmt->execute([$sessionId, $user['id'], $count + 1, json_encode($deckOrder)]);

        // 5. 如果满4人，开启车厢
        if ($count + 1 == 4) {
            $stmt = $pdo->prepare("UPDATE game_sessions SET status = 'active' WHERE id = ?");
            $stmt->execute([$sessionId]);
        }

        $pdo->commit();
        echo json_encode(['status' => 'success', 'session_id' => $sessionId]);

    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
}

elseif ($action === 'status') {
    // 检查玩家当前车厢状态
    $stmt = $pdo->prepare("
        SELECT s.status, s.id as session_id, 
               (SELECT COUNT(*) FROM session_players WHERE session_id = s.id) as player_count
        FROM session_players sp
        JOIN game_sessions s ON sp.session_id = s.id
        WHERE sp.user_id = ? AND sp.is_finished = 0
        ORDER BY s.created_at DESC LIMIT 1
    ");
    $stmt->execute([$user['id']]);
    $res = $stmt->fetch(PDO::FETCH_ASSOC);

    echo json_encode($res ?: ['status' => 'none']);
}
?>