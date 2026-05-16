<?php
// api/get_technicians.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Debug: Log request
error_log("API: get_technicians.php called from " . $_SERVER['REQUEST_URI']);

// Calculate the correct path to includes directory
$baseDir = dirname(__DIR__); // Goes up one level from api/ to ability_app_main/
$dbConnectPath = $baseDir . '/includes/db_connect.php';

error_log("Looking for db_connect.php at: " . $dbConnectPath);

// Check if file exists
if (!file_exists($dbConnectPath)) {
    error_log("ERROR: db_connect.php not found at: " . $dbConnectPath);

    // Try alternative paths
    $alternativePaths = [
        __DIR__ . '/../includes/db_connect.php',
        __DIR__ . '/../../includes/db_connect.php',
        'C:/xampp/htdocs/ability_app_main/includes/db_connect.php'
    ];

    foreach ($alternativePaths as $altPath) {
        if (file_exists($altPath)) {
            $dbConnectPath = $altPath;
            error_log("Found db_connect.php at alternative path: " . $altPath);
            break;
        }
    }
}

// Include database connection
require_once $dbConnectPath;

// Check if $pdo exists
if (!isset($pdo)) {
    error_log("ERROR: \$pdo variable not set after including db_connect.php");

    // Try to create a connection directly
    try {
        $host = 'localhost';
        $dbname = 'ability_db';
        $username = 'root'; // Change if different
        $password = ''; // Change if different

        $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        error_log("Created direct PDO connection");
    } catch (PDOException $e) {
        error_log("Failed to create direct PDO connection: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'error' => 'Database connection failed: ' . $e->getMessage(),
            'debug' => [
                'dbConnectPath' => $dbConnectPath,
                'fileExists' => file_exists($dbConnectPath)
            ],
            'technicians' => []
        ]);
        exit;
    }
}

try {
    // Query to get all active technicians from unified users table
    $sql = "SELECT 
                id,
                username,
                full_name,
                email,
                phone,
                department,
                role,
                is_active,
                created_at
            FROM users 
            WHERE is_active = 1 
            AND (role = 'technician' OR role = 'tech_lead' OR role = 'admin')
            ORDER BY full_name ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $technicians = $stmt->fetchAll(PDO::FETCH_ASSOC);

    error_log("API: Found " . count($technicians) . " technicians");

    if (count($technicians) > 0) {
        echo json_encode([
            'success' => true,
            'technicians' => $technicians,
            'count' => count($technicians),
            'message' => 'Technicians loaded successfully'
        ], JSON_PRETTY_PRINT);
    } else {
        echo json_encode([
            'success' => true,
            'technicians' => [],
            'count' => 0,
            'message' => 'No technicians found in database'
        ], JSON_PRETTY_PRINT);
    }
} catch (PDOException $e) {
    error_log("API Error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage(),
        'query_error' => $e->getMessage(),
        'technicians' => []
    ], JSON_PRETTY_PRINT);
}
