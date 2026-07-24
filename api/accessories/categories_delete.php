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
        
        $id = intval($_POST['id'] ?? 0);
        if ($id < 1) {
            throw new Exception('Invalid category ID');
        }
        
        $stmt = $conn->prepare("DELETE FROM accessory_categories WHERE id = ?");
        $stmt->bind_param("i", $id);
        
        if (!$stmt->execute()) {
            throw new Exception('Failed to delete category: ' . $stmt->error);
        }
        
        echo json_encode(['success' => true, 'message' => 'Category deleted successfully']);
        
        $stmt->close();
        $db->close();
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>
