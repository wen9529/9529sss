<?php
require_once __DIR__ . '/../db.php';

function get_authenticated_user() {
    $headers = getallheaders();
    $auth_header = $headers['Authorization'] ?? '';

    if (preg_match('/Bearer\s(\S+)/', $auth_header, $matches)) {
        $token = $matches[1];
        global $conn;
        $sql = "SELECT id, username FROM users WHERE auth_token = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $token);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            return $result->fetch_assoc(); // Return user data
        }
    }

    return null; // No valid token, or user not found
}

function require_auth() {
    $user = get_authenticated_user();
    if ($user === null) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }
    return $user;
}
?>
