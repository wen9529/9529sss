<?php

require_once __DIR__ . '/../db.php';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $game_id = (int)basename($_SERVER['REQUEST_URI']);

    // Get game data
    $sql = "SELECT * FROM games WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $game_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $game = $result->fetch_assoc();

    // Get players
    $sql = "SELECT id, name FROM players WHERE game_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $game_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $players = [];
    if ($result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            // Get player hand
            $sql_hand = "SELECT card FROM hands WHERE player_id = ?";
            $stmt_hand = $conn->prepare($sql_hand);
            $stmt_hand->bind_param("i", $row['id']);
            $stmt_hand->execute();
            $result_hand = $stmt_hand->get_result();
            $hand = [];
            if ($result_hand->num_rows > 0) {
                while($row_hand = $result_hand->fetch_assoc()) {
                    $hand[] = $row_hand['card'];
                }
            }
            $row['hand'] = $hand;
            $players[] = $row;
        }
    }

    // Get board
    $sql = "SELECT card FROM board WHERE game_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $game_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $board = [];
    if ($result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $board[] = $row['card'];
        }
    }

    $game_state = [
        'game' => [
            'id' => $game['id'],
            'players' => $players,
            'board' => $board,
            'turn' => $game['turn'],
        ]
    ];

    echo json_encode($game_state);

} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Make a move
    echo json_encode(['success' => true]);
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed']);
}
