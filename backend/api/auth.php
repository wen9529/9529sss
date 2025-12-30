<?php
require_once __DIR__ . '/../db.php';

// --- CORS Headers ---
if (isset($_SERVER['HTTP_ORIGIN'])) {
    header("Access-Control-Allow-Origin: {$_SERVER['HTTP_ORIGIN']}");
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Max-Age: 86400'); // cache for 1 day
}
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD'])) {
        header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
    }
    if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS'])) {
        header("Access-Control-Allow-Headers: {$_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']}");
    }
    exit(0);
}

header('Content-Type: application/json');

// --- Main Logic ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);

    $username = $data['username'] ?? '';
    $password = $data['password'] ?? '';

    if (empty($username) || empty($password)) {
        http_response_code(400);
        echo json_encode(['error' => '用户名和密码是必需的']);
        exit;
    }

    $sql = "SELECT * FROM users WHERE username = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        // User exists -> LOGIN
        $user = $result->fetch_assoc();
        if (password_verify($password, $user['password'])) {
            // Password is correct, generate and save a new token
            $token = bin2hex(random_bytes(32));
            $sql_update_token = "UPDATE users SET auth_token = ? WHERE id = ?";
            $stmt_update_token = $conn->prepare($sql_update_token);
            $stmt_update_token->bind_param("si", $token, $user['id']);
            $stmt_update_token->execute();

            echo json_encode([
                'user' => ['id' => $user['id'], 'name' => $user['username']],
                'token' => $token
            ]);

        } else {
            // Invalid password
            http_response_code(401);
            echo json_encode(['error' => '用户名或密码错误']);
        }
    } else {
        // User does not exist -> REGISTER
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $token = bin2hex(random_bytes(32));
        
        $sql_insert = "INSERT INTO users (username, password, auth_token) VALUES (?, ?, ?)";
        $stmt_insert = $conn->prepare($sql_insert);
        $stmt_insert->bind_param("sss", $username, $hashed_password, $token);
        
        if ($stmt_insert->execute()) {
            $new_user_id = $stmt_insert->insert_id;
            echo json_encode([
                'user' => ['id' => $new_user_id, 'name' => $username],
                'token' => $token
            ]);
        } else {
            http_response_code(500);
            echo json_encode(['error' => '注册失败，请稍后重试']);
        }
    }
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed']);
}
?>
