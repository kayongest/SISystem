<?php
// api/technicians/verify_password.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Try multiple paths for the database connection
$possiblePaths = [
    __DIR__ . '/../../includes/db_connect.php',  // From api/technicians/
    __DIR__ . '/../includes/db_connect.php',     // From api/
    'includes/db_connect.php',
    '../includes/db_connect.php',
    '../../includes/db_connect.php'
];

$dbConnected = false;
foreach ($possiblePaths as $path) {
    if (file_exists($path)) {
        require_once $path;
        $dbConnected = true;
        break;
    }
}

if (!$dbConnected) {
    echo json_encode([
        'success' => false,
        'message' => 'Database connection file not found',
        'debug_paths' => $possiblePaths
    ]);
    exit;
}

// Create database instance
$database = new Database();
$conn = $database->getConnection();

// Get POST data
$input = file_get_contents('php://input');
if (empty($input)) {
    echo json_encode([
        'success' => false,
        'message' => 'No data received'
    ]);
    exit;
}

$data = json_decode($input, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid JSON data: ' . json_last_error_msg(),
        'received' => $input
    ]);
    exit;
}

if (!isset($data['technician_id']) || !isset($data['password'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Technician ID and password required',
        'received_data' => $data
    ]);
    exit;
}

$technicianId = trim($data['technician_id']);
$password = trim($data['password']);

try {
    // Get user from database using prepared statement
    $sql = "SELECT id, username, full_name, email, department, role, password 
            FROM users 
            WHERE id = ? AND is_active = 1 AND role IN ('technician', 'tech_lead', 'senior_tech', 'audio_specialist', 'video_specialist', 'lighting_specialist', 'rigging_specialist')";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }

    $stmt->bind_param("i", $technicianId);
    $stmt->execute();
    $result = $stmt->get_result();
    $technician = $result->fetch_assoc();

    if ($technician) {
        // Check if password needs verification
        if (isset($technician['password'])) {
            // Try password_verify first (for hashed passwords)
            if (password_verify($password, $technician['password'])) {
                // Password is correct (hashed)
                handleSuccessfulAuth($technician, $conn);
            }
            // If password_verify fails, check plain text (for demo/testing)
            elseif ($password === $technician['password']) {
                // Password is correct (plain text)
                handleSuccessfulAuth($technician, $conn);
            } else {
                sendError('Invalid password');
            }
        } else {
            // No password field in database
            sendError('Password field not found in database');
        }
    } else {
        sendError('Technician not found or not active');
    }

    $stmt->close();
    $conn->close();
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}

function handleSuccessfulAuth($technician, $conn)
{
    // Remove password from response
    unset($technician['password']);

    // Add additional info
    $technician['verified_at'] = date('Y-m-d H:i:s');

    // Update last login
    $updateSql = "UPDATE users SET last_login = NOW() WHERE id = ?";
    $updateStmt = $conn->prepare($updateSql);
    $updateStmt->bind_param("i", $technician['id']);
    $updateStmt->execute();
    $updateStmt->close();

    echo json_encode([
        'success' => true,
        'technician' => $technician,
        'message' => 'Authentication successful'
    ]);
}

function sendError($message)
{
    echo json_encode([
        'success' => false,
        'message' => $message
    ]);
    exit;
}
