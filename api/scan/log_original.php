<?php
// api/scan/log.php - COMPLETE WORKING VERSION
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set JSON header FIRST
header('Content-Type: application/json');

// Define root path
define('ROOT_PATH', dirname(__DIR__, 2)); // Go up two levels from api/scan/

// Include required files
require_once ROOT_PATH . '/includes/db_connect.php';
require_once ROOT_PATH . '/includes/functions.php';

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check authentication
if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        'success' => false, 
        'message' => 'Unauthorized - Please log in first',
        'session_id' => session_id(),
        'debug' => ['session' => $_SESSION]
    ]);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

// If no JSON, try POST
if (!$input && !empty($_POST)) {
    $input = $_POST;
}

// Debug log
error_log("Scan API called by user {$_SESSION['user_id']}. Input: " . print_r($input, true));

// Check required fields
if (!isset($input['item_id']) || !isset($input['scan_type'])) {
    echo json_encode([
        'success' => false, 
        'message' => 'Missing required fields: item_id and scan_type',
        'received' => $input
    ]);
    exit;
}

// Get database connection
$db = getDatabase();
$conn = $db->getConnection();

if (!$conn) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

// Sanitize inputs
$item_id = (int)$input['item_id'];
$user_id = (int)$_SESSION['user_id'];
$scan_type = trim($input['scan_type']);
$location = isset($input['location']) ? trim($input['location']) : '';
$notes = isset($input['notes']) ? trim($input['notes']) : '';
$scanned_data = isset($input['scanned_data']) ? $input['scanned_data'] : '';

try {
    // 1. Check if item exists
    $stmt = $conn->prepare("SELECT * FROM items WHERE id = ?");
    $stmt->bind_param("i", $item_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $item = $result->fetch_assoc();
    
    if (!$item) {
        throw new Exception("Item ID {$item_id} not found in database");
    }
    
    // 2. Insert into scan_logs table
    $stmt = $conn->prepare("
        INSERT INTO scan_logs 
        (user_id, item_id, scan_type, scan_method, scanned_data, location, notes)
        VALUES (?, ?, ?, 'qrcode', ?, ?, ?)
    ");
    
    $stmt->bind_param("iissss", $user_id, $item_id, $scan_type, $scanned_data, $location, $notes);
    
    if (!$stmt->execute()) {
        throw new Exception("Failed to insert scan log: " . $stmt->error);
    }
    
    $scan_id = $conn->insert_id;
    
    // 3. Update item status based on scan type
    $status_map = [
        'check_in' => 'available',
        'check_out' => 'in_use',
        'maintenance' => 'maintenance'
    ];
    
    if (isset($status_map[$scan_type])) {
        $new_status = $status_map[$scan_type];
        
        // Update item status
        $stmt = $conn->prepare("UPDATE items SET status = ?, last_scanned = NOW() WHERE id = ?");
        $stmt->bind_param("si", $new_status, $item_id);
        
        if (!$stmt->execute()) {
            throw new Exception("Failed to update item status: " . $stmt->error);
        }
        
        // Update location if provided
        if (!empty($location)) {
            $stmt = $conn->prepare("UPDATE items SET stock_location = ? WHERE id = ?");
            $stmt->bind_param("si", $location, $item_id);
            $stmt->execute();
            $item['stock_location'] = $location;
        }
        
        $item['status'] = $new_status;
    }
    
    // 4. Log activity
    $activity_description = "Scanned item: {$item['item_name']} (Serial: {$item['serial_number']}) - " . 
                           ucfirst(str_replace('_', ' ', $scan_type));
    
    // Check if activity_logs table exists, create if not
    $table_check = $conn->query("SHOW TABLES LIKE 'activity_logs'");
    if ($table_check->num_rows == 0) {
        // Create activity_logs table
        $conn->query("
            CREATE TABLE IF NOT EXISTS activity_logs (
                id INT PRIMARY KEY AUTO_INCREMENT,
                user_id INT NOT NULL,
                activity_type VARCHAR(50),
                description TEXT,
                item_id INT,
                ip_address VARCHAR(45),
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id),
                FOREIGN KEY (item_id) REFERENCES items(id)
            )
        ");
    }
    
    $stmt = $conn->prepare("
        INSERT INTO activity_logs 
        (user_id, activity_type, description, item_id, ip_address)
        VALUES (?, ?, ?, ?, ?)
    ");
    
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    $activity_type = 'scan_' . $scan_type;
    $stmt->bind_param("issis", $user_id, $activity_type, $activity_description, $item_id, $ip_address);
    $stmt->execute();
    
    // 5. Return success response
    echo json_encode([
        'success' => true,
        'message' => 'Scan logged successfully!',
        'scan_id' => $scan_id,
        'item' => $item,
        'scan_details' => [
            'type' => $scan_type,
            'location' => $location,
            'timestamp' => date('Y-m-d H:i:s')
        ]
    ]);
    
} catch (Exception $e) {
    error_log("Scan API Error: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage(),
        'debug_info' => [
            'item_id' => $item_id,
            'user_id' => $user_id,
            'scan_type' => $scan_type
        ]
    ]);
}