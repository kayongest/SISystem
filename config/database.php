<?php
// config/database.php - UPDATED to support both MySQLi and PDO
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'ability_db'); // Changed from ability_app to ability_db based on your config

// Start session only if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// MySQLi connection function (existing)
function getConnection()
{
    try {
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

        if ($conn->connect_error) {
            throw new Exception("MySQLi Connection failed: " . $conn->connect_error);
        }

        return $conn;
    } catch (Exception $e) {
        die("MySQLi Database error: " . $e->getMessage());
    }
}

// PDO connection (add this for compatibility with your batch details code)
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);

    // Test PDO connection
    $pdo->query("SELECT 1");
} catch (PDOException $e) {
    // Don't die, just set to null so scripts can handle it
    $pdo = null;
    error_log("PDO Connection failed: " . $e->getMessage());
}
