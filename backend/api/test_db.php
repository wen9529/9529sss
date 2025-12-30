<?php
// test_db.php
// Set headers to output plain text and prevent caching
header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');

// Enable detailed error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Attempting to connect to the database...\n";

try {
    // Use the exact same path as in your db.php
    $db_path = __DIR__ . '/../../database.sqlite';
    echo "Database path determined to be: " . realpath(__DIR__ . '/../..') . "/database.sqlite\n";

    // Check if the parent directory is writable
    $db_dir = dirname($db_path);
    if (is_writable($db_dir)) {
        echo "Directory $db_dir is writable.\n";
    } else {
        echo "WARNING: Directory $db_dir is NOT writable. This might prevent DB creation.\n";
    }

    // Check if the file exists and is writable
    if (file_exists($db_path)) {
        if (is_writable($db_path)) {
            echo "Database file $db_path exists and is writable.\n";
        } else {
            echo "ERROR: Database file $db_path exists but is NOT writable.\n";
        }
    } else {
        echo "INFO: Database file $db_path does not exist. PDO will attempt to create it.\n";
    }

    // Attempt to create a new PDO connection
    $pdo = new PDO('sqlite:' . $db_path);

    // Set attributes for error handling
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "SUCCESS: Database connection established.\n";

    // Check if the pdo_sqlite extension is loaded
    if (extension_loaded('pdo_sqlite')) {
        echo "INFO: pdo_sqlite extension is loaded.\n";
    } else {
        echo "ERROR: pdo_sqlite extension is NOT loaded.\n";
    }

    // Optional: Check if the users table exists
    $stmt = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='users'");
    if ($stmt->fetch()) {
        echo "INFO: 'users' table exists.\n";
    } else {
        echo "WARNING: 'users' table does not exist. The application might need to create it.\n";
    }

} catch (PDOException $e) {
    echo "CRITICAL ERROR: Database connection failed.\n";
    echo "PDOException: " . $e->getMessage() . "\n";
} catch (Exception $e) {
    echo "CRITICAL ERROR: A non-PDO exception occurred.\n";
    echo "Exception: " . $e->getMessage() . "\n";
}

?>