<?php
session_start();
require_once __DIR__ . '/../../includes/database_fix.php';
require_once __DIR__ . '/../../includes/functions.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Authentication required']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $db = new DatabaseFix();
        $conn = $db->getConnection();
        
        $name = sanitizeInput($_POST['name'] ?? '');
        if (empty($name)) {
            throw new Exception('Category name is required');
        }
        
        $stmt = $conn->prepare("INSERT INTO accessory_categories (name) VALUES (?)");
        $stmt->bind_param("s", $name);
        
        if (!$stmt->execute()) {
            if ($stmt->errno == 1062) { // Duplicate entry
                throw new Exception('Category already exists');
            }
            throw new Exception('Failed to add category: ' . $stmt->error);
        }
        
        echo json_encode(['success' => true, 'message' => 'Category added successfully']);
        
        $stmt->close();
        $db->close();
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>
