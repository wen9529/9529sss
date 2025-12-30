<?php
// backend/api/auth.php
require '../db.php';

$input = json_decode(file_get_contents('php://input'), true);
$action = $_GET['action'] ?? '';

// 1. 注册/登录
if ($action === 'login_or_register') {
    $mobile = $input['mobile'];
    $password = $input['password'];

    // 检查用户
    $stmt = $pdo->prepare("SELECT * FROM users WHERE mobile = ?");
    $stmt->execute([$mobile]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        if (password_verify($password, $user['password_hash'])) {
            $token = bin2hex(random_bytes(32));
            $pdo->prepare("UPDATE users SET api_token = ? WHERE id = ?")->execute([$token, $user['id']]);
            echo json_encode(['status' => 'success', 'token' => $token, 'user' => $user]);
        } else {
            echo json_encode(['status' => 'error', 'message' => '密码错误']);
        }
    } else {
        // 注册
        do {
            $game_id = strtoupper(substr(md5(uniqid()), 0, 4));
            $check = $pdo->prepare("SELECT id FROM users WHERE game_id = ?");
            $check->execute([$game_id]);
        } while ($check->fetch());

        $passHash = password_hash($password, PASSWORD_DEFAULT);
        $token = bin2hex(random_bytes(32));

        $sql = "INSERT INTO users (mobile, game_id, password_hash, api_token, points) VALUES (?, ?, ?, ?, 1000)";
        $pdo->prepare($sql)->execute([$mobile, $game_id, $passHash, $token]);
        
        // 获取新插入的完整用户数据
        $id = $pdo->lastInsertId();
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$id]);
        $fullUser = $stmt->fetch(PDO::FETCH_ASSOC);
        
        echo json_encode(['status' => 'success', 'token' => $token, 'user' => $fullUser]);
    }
}

// 2. 获取最新用户信息 (用于刷新积分)
elseif ($action === 'get_info') {
    $user = authenticate($pdo);
    // 重新查询数据库以获取最新积分
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user['id']]);
    $freshUser = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode(['status' => 'success', 'user' => $freshUser]);
}

// 3. 搜索用户
elseif ($action === 'search_user') {
    $user = authenticate($pdo);
    $targetMobile = $input['mobile'];
    $stmt = $pdo->prepare("SELECT game_id FROM users WHERE mobile = ?");
    $stmt->execute([$targetMobile]);
    $res = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($res) {
        echo json_encode(['status' => 'success', 'game_id' => $res['game_id']]);
    } else {
        echo json_encode(['status' => 'error', 'message' => '用户不存在']);
    }
}

// 4. 转账
elseif ($action === 'transfer') {
    $user = authenticate($pdo); 
    $targetGameId = $input['target_id'];
    $amount = intval($input['amount']);

    // 重新查余额防止透支
    $stmt = $pdo->prepare("SELECT points FROM users WHERE id = ?");
    $stmt->execute([$user['id']]);
    $currentPoints = $stmt->fetchColumn();

    if ($amount <= 0 || $currentPoints < $amount) {
        echo json_encode(['status' => 'error', 'message' => '积分不足或金额无效']);
        exit;
    }

    $pdo->beginTransaction();
    try {
        $pdo->prepare("UPDATE users SET points = points - ? WHERE id = ?")->execute([$amount, $user['id']]);
        $stmt = $pdo->prepare("UPDATE users SET points = points + ? WHERE game_id = ?");
        $stmt->execute([$amount, $targetGameId]);

        if ($stmt->rowCount() === 0) {
            throw new Exception("目标用户不存在");
        }

        $pdo->commit();
        echo json_encode(['status' => 'success', 'message' => '转账成功']);
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
}
?>