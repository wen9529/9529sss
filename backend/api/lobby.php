<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/auth_middleware.php';

// --- CORS Headers ---
if (isset($_SERVER['HTTP_ORIGIN'])) {
    header("Access-Control-Allow-Origin: {$_SERVER['HTTP_ORIGIN']}");
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Max-Age: 86400');
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

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // No auth needed to view games
    $sql = "SELECT id, name, status FROM games WHERE status = 'waiting' ORDER BY created_at DESC";
    $result = $conn->query($sql);

    $games = [];
    if ($result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $games[] = $row;
        }
    }
    echo json_encode($games);

} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = require_auth(); // Auth required to create a game

    $data = json_decode(file_get_contents('php://input'), true);
    $name = $data['name'] ?? 'New Game';

    // Create the game
    $sql_create_game = "INSERT INTO games (name) VALUES (?)";
    $stmt_create_game = $conn->prepare($sql_create_game);
    $stmt_create_game->bind_param("s", $name);
    $stmt_create_game->execute();
    $new_game_id = $stmt_create_game->insert_id;

    // Add the creator as the first player
    $sql_add_player = "INSERT INTO players (game_id, user_id, position) VALUES (?, ?, 1)";
    $stmt_add_player = $conn->prepare($sql_add_player);
    $stmt_add_player->bind_param("ii", $new_game_id, $user['id']);
    $stmt_add_player->execute();

    // Return the newly created game
    $sql_get_game = "SELECT * FROM games WHERE id = ?";
    $stmt_get_game = $conn->prepare($sql_get_game);
    $stmt_get_game->bind_param("i", $new_game_id);
    $stmt_get_game->execute();
    $result = $stmt_get_game->get_result();
    $new_game = $result->fetch_assoc();

    echo json_encode($new_game);

} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed']);
}
?>
