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

$user = require_auth(); // Auth required for all game actions

$game_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$game_id) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid or missing game ID']);
    exit;
}

// --- Main Logic ---
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Fetch all game state in a more optimized way
    $sql = "
        SELECT 
            g.id AS game_id, g.name AS game_name, g.status, g.turn,
            p.id AS player_id, p.user_id, p.position,
            u.username,
            h.card AS hand_card,
            b.card AS board_card
        FROM games g
        LEFT JOIN players p ON g.id = p.game_id
        LEFT JOIN users u ON p.user_id = u.id
        LEFT JOIN hands h ON p.id = h.player_id
        LEFT JOIN board b ON g.id = b.game_id
        WHERE g.id = ?
        ORDER BY p.position, h.id;
    ";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $game_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        http_response_code(404);
        echo json_encode(['error' => 'Game not found']);
        exit;
    }

    // Process the flat results into a structured object
    $game_state = [];
    $players = [];
    $board = [];
    
    while ($row = $result->fetch_assoc()) {
        if (empty($game_state)) {
            $game_state['game'] = [
                'id' => $row['game_id'],
                'name' => $row['game_name'],
                'status' => $row['status'],
                'turn' => $row['turn'],
            ];
        }

        if ($row['player_id'] && !isset($players[$row['player_id']])) {
            $players[$row['player_id']] = [
                'id' => $row['player_id'],
                'user_id' => $row['user_id'],
                'name' => $row['username'],
                'position' => $row['position'],
                'hand' => []
            ];
        }

        if ($row['player_id'] && $row['hand_card']) {
             // Only add card if it's not already in the hand (to avoid duplicates from JOIN)
            if (!in_array($row['hand_card'], $players[$row['player_id']]['hand'])) {
                $players[$row['player_id']]['hand'][] = $row['hand_card'];
            }
        }

        if ($row['board_card'] && !in_array($row['board_card'], $board)) {
            $board[] = $row['board_card'];
        }
    }

    $game_state['game']['players'] = array_values($players);
    $game_state['game']['board'] = $board;

    echo json_encode($game_state);

} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Placeholder for making a move
    // TODO: Implement game logic (e.g., playing a card)
    $data = json_decode(file_get_contents('php://input'), true);
    $move = $data['move'] ?? null;
    
    // Example validation
    if ($move) {
        // Here you would validate the move and update the database
        echo json_encode(['success' => true, 'message' => 'Move received', 'move' => $move]);
    } else {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid move data']);
    }

} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed']);
}
?>
