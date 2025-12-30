<?php

// All requests will be routed through this file.
// For example, a request to /api/game will be handled by requiring the game.php file.

$request_uri = $_SERVER['REQUEST_URI'];

if (strpos($request_uri, '/api/game') !== false) {
    require_once __DIR__ . '/api/game.php';
} elseif (strpos($request_uri, '/api/lobby') !== false) {
    require_once __DIR__ . '/api/lobby.php';
} elseif (strpos($request_uri, '/api/auth') !== false) {
    require_once __DIR__ . '/api/auth.php';
} else {
    http_response_code(404);
    echo json_encode(['error' => 'Not Found']);
}
