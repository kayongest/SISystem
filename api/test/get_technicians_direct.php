<?php
// api/test/verify_technician_direct.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Don't start session if it's already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Get POST data
$input = file_get_contents('php://input');
$data = json_decode($input, true);

// Debug: Log the received data
error_log("Received data: " . print_r($data, true));

$technicianId = isset($data['technician_id']) ? $data['technician_id'] : '';
$password = isset($data['password']) ? $data['password'] : '';

// Also check for alternative field names
if (empty($technicianId) && isset($data['id'])) {
    $technicianId = $data['id'];
}
if (empty($password) && isset($data['password_input'])) {
    $password = $data['password_input'];
}

if (!$technicianId || !$password) {
    echo json_encode([
        'success' => false,
        'error' => 'Technician ID and password required',
        'received_data' => $data,
        'technician_id' => $technicianId,
        'has_password' => !empty($password)
    ]);
    exit;
}

// Database connection
require_once '../../config/database.php';
$conn = getConnection();

if (!$conn) {
    echo json_encode([
        'success' => false,
        'error' => 'Database connection failed'
    ]);
    exit;
}

try {
    // Get technician from database
    $sql = "SELECT id, username, full_name, email, department, role, password 
            FROM users 
            WHERE id = ? AND is_active = 1";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }

    $stmt->bind_param('i', $technicianId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        echo json_encode([
            'success' => false,
            'error' => 'Technician not found in database',
            'technician_id' => $technicianId
        ]);
        exit;
    }

    $technician = $result->fetch_assoc();

    // Debug: Check what's in the database
    error_log("Database technician: " . print_r($technician, true));

    // Verify password
    // Method 1: Check plain text (for testing)
    $plainTextMatch = ($password === $technician['password']);

    // Method 2: Check MD5 hash
    $md5Match = ($technician['password'] === md5($password));

    // Method 3: Check if password starts with $2y$ (bcrypt)
    $isBcrypt = (strpos($technician['password'], '$2y$') === 0);
    $bcryptMatch = false;
    if ($isBcrypt) {
        $bcryptMatch = password_verify($password, $technician['password']);
    }

    if ($plainTextMatch || $md5Match || $bcryptMatch) {
        // Remove password from response for security
        unset($technician['password']);

        echo json_encode([
            'success' => true,
            'message' => 'Authentication successful',
            'technician' => $technician,
            'authentication_method' => $plainTextMatch ? 'plain_text' : ($md5Match ? 'md5' : 'bcrypt'),
            'debug' => [
                'input_password' => $password,
                'db_password_length' => strlen($technician['password'] ?? ''),
                'password_type' => $isBcrypt ? 'bcrypt' : (strlen($technician['password'] ?? '') === 32 ? 'md5' : 'plain_text')
            ]
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'error' => 'Invalid password',
            'debug' => [
                'input_password' => $password,
                'db_password_sample' => substr($technician['password'] ?? '', 0, 10) . '...',
                'password_length' => strlen($technician['password'] ?? ''),
                'md5_hash_of_input' => md5($password)
            ]
        ]);
    }
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
} finally {
    if (isset($stmt)) $stmt->close();
    $conn->close();
}
