<?php
// api/accessories/create.php
session_start();
require_once __DIR__ . '/../../includes/database_fix.php';
require_once __DIR__ . '/../../includes/functions.php';

header('Content-Type: application/json');

// Check if user is logged in
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Authentication required'
    ]);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $db = new DatabaseFix();
        $conn = $db->getConnection();
        
        // Sanitize inputs
        $name = sanitizeInput($_POST['name'] ?? '');
        $description = sanitizeInput($_POST['description'] ?? '');
        $total_quantity = intval($_POST['total_quantity'] ?? 1);
        $available_quantity = intval($_POST['available_quantity'] ?? 1);
        $minimum_stock = intval($_POST['minimum_stock'] ?? 5);
        
        // Validate
        if (empty($name)) {
            throw new Exception('Accessory name is required');
        }
        
        if ($total_quantity < 1) {
            throw new Exception('Total quantity must be at least 1');
        }
        
        if ($available_quantity < 0 || $available_quantity > $total_quantity) {
            throw new Exception('Available quantity must be between 0 and total quantity');
        }
        
        // Check if accessory already exists
        $checkStmt = $conn->prepare("SELECT id FROM accessories WHERE name = ? AND is_active = 1");
        $checkStmt->bind_param("s", $name);
        $checkStmt->execute();
        $checkStmt->store_result();
        
        if ($checkStmt->num_rows > 0) {
            throw new Exception('Accessory with this name already exists');
        }
        $checkStmt->close();
        
        // Insert accessory
        $sql = "INSERT INTO accessories (
            name, description, total_quantity, available_quantity, 
            minimum_stock, is_active, created_at
        ) VALUES (?, ?, ?, ?, ?, 1, NOW())";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param(
            "ssiii",
            $name, $description, $total_quantity, $available_quantity, $minimum_stock
        );
        
        if (!$stmt->execute()) {
            throw new Exception('Failed to create accessory: ' . $stmt->error);
        }
        
        $accessory_id = $conn->insert_id;
        
        echo json_encode([
            'success' => true,
            'message' => 'Accessory created successfully',
            'accessory_id' => $accessory_id
        ]);
        
        $stmt->close();
        $db->close();
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method'
    ]);
}
?>