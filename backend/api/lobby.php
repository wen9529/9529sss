<?php

require_once __DIR__ . '/../db.php';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $sql = "SELECT * FROM games";
    $result = $conn->query($sql);

    $games = [];
    if ($result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $games[] = $row;
        }
    }
    echo json_encode($games);
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $name = $data['name'];

    $sql = "INSERT INTO games (name, players) VALUES (?, 1)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $name);
    $stmt->execute();

    $new_game_id = $stmt->insert_id;

    $sql = "SELECT * FROM games WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $new_game_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $new_game = $result->fetch_assoc();

    echo json_encode($new_game);
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed']);
}
