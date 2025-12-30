<?php
// backend/api/game.php
require '../db.php';
require_once '../core/CardComparator.php';
require_once '../core/Logic.php';

$user = authenticate($pdo);
$action = $_GET['action'] ?? '';

if ($action === 'get_hand') {
    $stmt = $pdo->prepare("SELECT * FROM session_players WHERE user_id = ? AND is_finished = 0");
    $stmt->execute([$user['id']]);
    $playerParams = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$playerParams) {
        echo json_encode(['status' => 'finished']);
        exit;
    }

    $currentStep = intval($playerParams['current_step']);
    $deckOrder = json_decode($playerParams['deck_order'], true);

    if ($currentStep > 20 || !isset($deckOrder[$currentStep - 1])) {
        $pdo->prepare("UPDATE session_players SET is_finished = 1 WHERE id = ?")->execute([$playerParams['id']]);
        echo json_encode(['status' => 'finished']);
        exit;
    }

    $deckId = $deckOrder[$currentStep - 1];
    $seatIndex = $playerParams['seat_index'];

    $stmt = $pdo->prepare("SELECT cards_json, solutions_json FROM pre_decks WHERE id = ?");
    $stmt->execute([$deckId]);
    $deckData = $stmt->fetch(PDO::FETCH_ASSOC);

    $allHands = json_decode($deckData['cards_json'], true);
    $allSolutions = json_decode($deckData['solutions_json'], true);

    echo json_encode([
        'status' => 'success',
        'session_id' => $playerParams['session_id'],
        'round_info' => "第 {$currentStep} / 20 局",
        'deck_id' => $deckId,
        'cards' => $allHands[$seatIndex - 1],
        'solutions' => $allSolutions[$seatIndex - 1] ?? [] // 返回推荐摆法
    ]);
}

elseif ($action === 'submit_hand') {
    $input = json_decode(file_get_contents('php://input'), true);
    $deckId = $input['deck_id'];
    $arranged = $input['arranged']; 
    $sessionId = $input['session_id'];

    // --- 校验是否相公 ---
    if (CardComparator::isIllegal($arranged['front'], $arranged['mid'], $arranged['back'])) {
        // 十三水规则：如果相公，此局全赔（这里可以记录为特殊状态，或前端拦截）
        // 为了体验，我们先在 API 返回错误提示
        echo json_encode(['status' => 'error', 'message' => '摆法违规（相公）：后墩必须大于中墩，中墩必须大于头墩。']);
        exit;
    }

    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("SELECT id FROM game_actions WHERE session_id = ? AND deck_id = ? AND user_id = ?");
        $stmt->execute([$sessionId, $deckId, $user['id']]);
        if (!$stmt->fetch()) {
            $pdo->prepare("INSERT INTO game_actions (session_id, deck_id, user_id, hand_arranged) VALUES (?, ?, ?, ?)")
                ->execute([$sessionId, $deckId, $user['id'], json_encode($arranged)]);
            
            $pdo->prepare("UPDATE session_players SET current_step = current_step + 1 WHERE user_id = ? AND session_id = ?")
                ->execute([$user['id'], $sessionId]);
        }

        // 尝试触发全场结算
        $stmt = $pdo->prepare("SELECT a.* FROM game_actions a WHERE a.session_id = ? AND a.deck_id = ?");
        $stmt->execute([$sessionId, $deckId]);
        $allActions = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (count($allActions) == 4) {
            $playerHands = [];
            foreach ($allActions as $act) $playerHands[$act['user_id']] = json_decode($act['hand_arranged'], true);
            $scores = GameLogic::settleFourPlayers($playerHands);
            foreach ($scores as $uid => $diff) {
                $pdo->prepare("UPDATE game_actions SET score_result = ?, is_settled = 1 WHERE session_id = ? AND deck_id = ? AND user_id = ?")
                    ->execute([$diff, $sessionId, $deckId, $uid]);
                $pdo->prepare("UPDATE users SET points = points + ? WHERE id = ?")->execute([$diff, $uid]);
            }
        }
        $pdo->commit();
        echo json_encode(['status' => 'success']);
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
}
?>