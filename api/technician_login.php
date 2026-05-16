<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set CORS headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Max-Age: 172800'); // 24 hours cache for preflight

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Now set the content type
header('Content-Type: application/json; charset=utf-8');

// Database connection
$host = 'localhost';
$dbname = 'ability_db';
$username = 'root';
$password = '';

try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database connection failed: ' . $e->getMessage()
    ]);
    exit();
}

// Get POST data - handle both JSON and FormData
$inputData = file_get_contents('php://input');

// Try to decode as JSON first
$data = json_decode($inputData, true);

// If JSON decode failed or empty, use $_POST
if (json_last_error() !== JSON_ERROR_NONE || empty($data)) {
    $data = $_POST;
}

// If still empty, check $_GET for debugging
if (empty($data) && !empty($_GET)) {
    $data = $_GET;
}

$username = isset($data['username']) ? trim($data['username']) : '';
$password = isset($data['password']) ? trim($data['password']) : '';

// Log received data for debugging (remove in production)
error_log("Login attempt - Username: $username");

// Validate input
if (empty($username) || empty($password)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Username and password are required',
        'received_data' => $data // For debugging only
    ]);
    exit();
}

try {
    // Check credentials
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? AND role = 'technician' AND is_active = 1");
    $stmt->execute([$username]);
    $technician = $stmt->fetch();

    if ($technician) {
        // Check password - support both plain text and hashed
        $passwordValid = false;
        
        // Debug: Log what we have
        error_log("Stored password hash: " . $technician['password']);
        error_log("Password length in DB: " . strlen($technician['password']));

        // First check plain text (for testing)
        if ($password === $technician['password']) {
            $passwordValid = true;
            error_log("Plain text password match");
        } 
        // Then check if it's a bcrypt hash (starts with $2y$)
        elseif (strlen($technician['password']) >= 60 && password_verify($password, $technician['password'])) {
            $passwordValid = true;
            error_log("Hashed password verified");
        }
        // Also check MD5 (if you have legacy hashes)
        elseif (strlen($technician['password']) == 32 && md5($password) === $technician['password']) {
            $passwordValid = true;
            error_log("MD5 hash verified");
        }

        if ($passwordValid) {
            // Update last login
            $updateStmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
            $updateStmt->execute([$technician['id']]);

            // Return success without sensitive data
            echo json_encode([
                'success' => true,
                'message' => 'Login successful',
                'technician' => [
                    'id' => $technician['id'],
                    'name' => $technician['full_name'],
                    'username' => $technician['username'],
                    'department' => $technician['department'] ?? 'N/A'
                ]
            ]);
            exit();
        } else {
            http_response_code(401);
            echo json_encode([
                'success' => false,
                'message' => 'Invalid password',
                'hint' => 'Check if password is correct'
            ]);
            exit();
        }
    } else {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'User not found or inactive'
        ]);
        exit();
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage()
    ]);
    exit();
}