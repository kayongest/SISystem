<?php
// api/accessories/update.php
session_start();
require_once __DIR__ . '/../../includes/database_fix.php';
require_once __DIR__ . '/../../includes/functions.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
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
        $id = intval($_POST['id'] ?? 0);
        $name = sanitizeInput($_POST['name'] ?? '');
        $description = sanitizeInput($_POST['description'] ?? '');
        $total_quantity = intval($_POST['total_quantity'] ?? 1);
        $available_quantity = intval($_POST['available_quantity'] ?? 1);
        $minimum_stock = intval($_POST['minimum_stock'] ?? 5);
        
        if ($id < 1) {
            throw new Exception('Invalid accessory ID');
        }
        
        if (empty($name)) {
            throw new Exception('Accessory name is required');
        }
        
        // Check if accessory exists
        $checkStmt = $conn->prepare("SELECT id FROM accessories WHERE id = ? AND is_active = 1");
        $checkStmt->bind_param("i", $id);
        $checkStmt->execute();
        $checkStmt->store_result();
        
        if ($checkStmt->num_rows === 0) {
            throw new Exception('Accessory not found');
        }
        $checkStmt->close();
        
        // Check if name already exists (excluding current accessory)
        $nameCheck = $conn->prepare("SELECT id FROM accessories WHERE name = ? AND id != ? AND is_active = 1");
        $nameCheck->bind_param("si", $name, $id);
        $nameCheck->execute();
        $nameCheck->store_result();
        
        if ($nameCheck->num_rows > 0) {
            throw new Exception('Another accessory with this name already exists');
        }
        $nameCheck->close();
        
        // Update accessory
        $sql = "UPDATE accessories SET 
            name = ?, 
            description = ?, 
            total_quantity = ?, 
            available_quantity = ?, 
            minimum_stock = ?,
            updated_at = NOW()
            WHERE id = ?";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param(
            "ssiiii",
            $name, $description, $total_quantity, $available_quantity, $minimum_stock, $id
        );
        
        if (!$stmt->execute()) {
            throw new Exception('Failed to update accessory: ' . $stmt->error);
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Accessory updated successfully'
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