<?php
// api/add_item.php
session_start();
require_once '../includes/database_fix.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Check if request is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

try {
    $db = new DatabaseFix();
    $conn = $db->getConnection();
    
    // Get POST data with sanitization
    $item_name = sanitizeInput($_POST['item_name'] ?? '');
    $serial_number = sanitizeInput($_POST['serial_number'] ?? '');
    $quantity = intval($_POST['quantity'] ?? 1);
    $brand = sanitizeInput($_POST['brand'] ?? '');
    $model = sanitizeInput($_POST['model'] ?? '');
    $category = sanitizeInput($_POST['category'] ?? '');
    $department = sanitizeInput($_POST['department'] ?? '');
    $status = sanitizeInput($_POST['status'] ?? 'available');
    $condition = sanitizeInput($_POST['condition'] ?? 'good');
    $stock_location = sanitizeInput($_POST['stock_location'] ?? '');
    $description = sanitizeInput($_POST['description'] ?? '');
    $generate_qr = isset($_POST['generate_qr']) && $_POST['generate_qr'] === 'on';
    
    // Validate required fields
    if (empty($item_name) || empty($serial_number) || empty($category)) {
        echo json_encode(['success' => false, 'message' => 'Required fields are missing']);
        exit;
    }
    
    if ($quantity < 1) {
        $quantity = 1;
    }
    
    // Check if serial number already exists
    $checkStmt = $conn->prepare("SELECT id FROM items WHERE serial_number = ?");
    $checkStmt->bind_param("s", $serial_number);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    
    if ($checkResult->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'Serial number already exists']);
        exit;
    }
    $checkStmt->close();
    
    // Handle image upload
    $image_path = null;
    if (isset($_FILES['item_image']) && $_FILES['item_image']['error'] === UPLOAD_ERR_OK) {
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $max_size = 5 * 1024 * 1024; // 5MB
        
        $file_type = $_FILES['item_image']['type'];
        $file_size = $_FILES['item_image']['size'];
        
        if (!in_array($file_type, $allowed_types)) {
            echo json_encode(['success' => false, 'message' => 'Invalid image type']);
            exit;
        }
        
        if ($file_size > $max_size) {
            echo json_encode(['success' => false, 'message' => 'Image too large (max 5MB)']);
            exit;
        }
        
        // Generate unique filename
        $ext = strtolower(pathinfo($_FILES['item_image']['name'], PATHINFO_EXTENSION));
        $filename = 'item_' . time() . '_' . uniqid() . '.' . $ext;
        $upload_dir = '../uploads/items/';
        
        // Create directory if it doesn't exist
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $target_path = $upload_dir . $filename;
        
        if (move_uploaded_file($_FILES['item_image']['tmp_name'], $target_path)) {
            $image_path = 'uploads/items/' . $filename;
        }
    }
    
    // Insert item
    $stmt = $conn->prepare("
        INSERT INTO items (
            item_name, serial_number, quantity, brand, model, 
            category, department, status, `condition`, stock_location, 
            description, image_path, qr_code, created_at, updated_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NULL, NOW(), NOW())
    ");
    
    $stmt->bind_param(
        "ssisssssssss",
        $item_name, $serial_number, $quantity, $brand, $model,
        $category, $department, $status, $condition, $stock_location,
        $description, $image_path
    );
    
    if ($stmt->execute()) {
        $item_id = $stmt->insert_id;
        
        // Handle accessories
        if (isset($_POST['accessories_array'])) {
            $accessories = json_decode($_POST['accessories_array'], true);
            
            if (is_array($accessories) && !empty($accessories)) {
                foreach ($accessories as $accessory_id) {
                    $accessory_id = intval($accessory_id);
                    if ($accessory_id > 0) {
                        // Insert into item_accessories table
                        $accStmt = $conn->prepare("
                            INSERT INTO item_accessories (item_id, accessory_id, created_at) 
                            VALUES (?, ?, NOW())
                        ");
                        $accStmt->bind_param("ii", $item_id, $accessory_id);
                        $accStmt->execute();
                        $accStmt->close();
                    }
                }
            }
        }
        
        // Generate QR code if requested
        if ($generate_qr) {
            // You can implement QR code generation here
            // $qr_code = generateQRCode($item_id, $item_name, $serial_number);
            // Update the item with QR code path
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Item added successfully',
            'item_id' => $item_id
        ]);
        
        $stmt->close();
        $conn->close();
        
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to add item: ' . $stmt->error]);
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>