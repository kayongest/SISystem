<?php
// api/scan/log.php - UPDATED FOR scan_logs TABLE

// Start session
session_start();

// Set JSON header
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

// Get JSON input
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data || json_last_error() !== JSON_ERROR_NONE) {
    echo json_encode(['success' => false, 'message' => 'Invalid JSON data']);
    exit();
}

// Validate required fields
$required = ['item_id', 'scan_type', 'from_location', 'to_location', 'transport_user', 'vehicle_plate'];
foreach ($required as $field) {
    if (empty($data[$field])) {
        echo json_encode(['success' => false, 'message' => ucfirst(str_replace('_', ' ', $field)) . ' is required']);
        exit();
    }
}

// Validate scan type
$validScanTypes = ['check_in', 'check_out', 'maintenance', 'inventory'];
if (!in_array($data['scan_type'], $validScanTypes)) {
    echo json_encode(['success' => false, 'message' => 'Invalid scan type']);
    exit();
}

try {
    // Database configuration
    $host = 'localhost';
    $dbname = 'ability_db';
    $username = 'root';
    $password = '';
    
    // Create PDO instance
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    
    // Get user ID from session
    $user_id = $_SESSION['user_id'];
    
    // Check if item exists
    $stmt = $pdo->prepare("SELECT id, item_name, status, stock_location FROM items WHERE id = ?");
    $stmt->execute([$data['item_id']]);
    $item = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$item) {
        echo json_encode(['success' => false, 'message' => 'Item not found']);
        exit();
    }
    
    // Create scanned_data JSON in the required format
    $scanned_data_json = json_encode([
        'timestamp' => date('Y-m-d\TH:i:s.v\Z'),
        'action' => $data['scan_type'],
        'scanned_at' => date('d/m/Y, H:i:s')
    ]);
    
    // Prepare scan data for scan_logs table
    $scan_data = [
        'user_id' => $user_id,
        'item_id' => (int)$data['item_id'],
        'scan_type' => $data['scan_type'],
        'scan_method' => 'qrcode',
        'scanned_data' => $scanned_data_json,
        'location' => $data['to_location'], // Using to_location as the location
        'notes' => $data['notes'] ?? '',
        'from_location' => $data['from_location'],
        'to_location' => $data['to_location'],
        'destination_address' => $data['destination_address'] ?? '',
        'transport_user' => $data['transport_user'],
        'user_contact' => $data['user_contact'] ?? '',
        'user_department' => $data['user_department'] ?? '',
        'user_id_number' => $data['user_id_number'] ?? '',
        'vehicle_plate' => $data['vehicle_plate'],
        'vehicle_type' => $data['vehicle_type'] ?? '',
        'vehicle_description' => $data['vehicle_description'] ?? '',
        'transport_notes' => $data['transport_notes'] ?? '',
        'expected_return' => !empty($data['expected_return']) ? $data['expected_return'] : null,
        'priority' => $data['priority'] ?? 'medium'
    ];
    
    // Start transaction
    $pdo->beginTransaction();
    
    try {
        // Insert into scan_logs table
        $sql = "INSERT INTO scan_logs (
            user_id, item_id, scan_type, scan_method, scanned_data, location, notes,
            from_location, to_location, destination_address, transport_user, user_contact,
            user_department, user_id_number, vehicle_plate, vehicle_type, vehicle_description,
            transport_notes, expected_return, priority, scan_timestamp, created_at
        ) VALUES (
            :user_id, :item_id, :scan_type, :scan_method, :scanned_data, :location, :notes,
            :from_location, :to_location, :destination_address, :transport_user, :user_contact,
            :user_department, :user_id_number, :vehicle_plate, :vehicle_type, :vehicle_description,
            :transport_notes, :expected_return, :priority, NOW(), NOW()
        )";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($scan_data);
        $scan_id = $pdo->lastInsertId();
        
        // Determine new status and location for item
        $new_status = $item['status'];
        $new_location = $item['stock_location'];
        
        switch ($data['scan_type']) {
            case 'check_out':
                $new_status = 'checked_out';
                $new_location = $data['to_location'];
                break;
            case 'check_in':
                $new_status = 'available';
                $new_location = $data['to_location'];
                break;
            case 'maintenance':
                $new_status = 'maintenance';
                break;
        }
        
        // Update item
        $update_sql = "UPDATE items SET status = ?, stock_location = ?, updated_at = NOW() WHERE id = ?";
        $update_stmt = $pdo->prepare($update_sql);
        $update_stmt->execute([$new_status, $new_location, $data['item_id']]);
        
        // Commit transaction
        $pdo->commit();
        
        // Response
        echo json_encode([
            'success' => true,
            'message' => ucfirst(str_replace('_', ' ', $data['scan_type'])) . ' logged successfully',
            'scan_id' => $scan_id,
            'scanned_data' => json_decode($scanned_data_json, true),
            'item' => [
                'id' => $item['id'],
                'name' => $item['item_name'],
                'status' => $new_status,
                'location' => $new_location
            ]
        ]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
?>