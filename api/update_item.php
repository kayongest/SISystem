<?php
// api/update_item.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$response = [
    'success' => false,
    'message' => ''
];

try {
    // Check authentication
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('Authentication required', 401);
    }

    // Check for POST request
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('POST method required', 405);
    }

    // Get root directory
    $rootDir = realpath(dirname(__FILE__) . '/..');

    // Include database connection
    $dbFile = $rootDir . '/includes/db_connect.php';
    if (!file_exists($dbFile)) {
        throw new Exception('Database configuration not found');
    }

    require_once $dbFile;

    // Create database connection
    $db = new Database();
    $conn = $db->getConnection();

    if (!$conn) {
        throw new Exception('Database connection failed');
    }

    // Get form data
    $item_id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    $item_name = isset($_POST['item_name']) ? trim($_POST['item_name']) : '';
    $serial_number = isset($_POST['serial_number']) ? trim($_POST['serial_number']) : '';
    $category = isset($_POST['category']) ? trim($_POST['category']) : '';
    $department = isset($_POST['department']) ? trim($_POST['department']) : '';
    $brand = isset($_POST['brand']) ? trim($_POST['brand']) : '';
    $model = isset($_POST['model']) ? trim($_POST['model']) : '';
    $brand_model = isset($_POST['brand_model']) ? trim($_POST['brand_model']) : '';
    $condition = isset($_POST['condition']) ? trim($_POST['condition']) : 'good';
    $stock_location = isset($_POST['stock_location']) ? trim($_POST['stock_location']) : '';
    $storage_location = isset($_POST['storage_location']) ? trim($_POST['storage_location']) : '';
    $current_location = isset($_POST['current_location']) ? trim($_POST['current_location']) : '';
    $quantity = isset($_POST['quantity']) ? intval($_POST['quantity']) : 1;
    $status = isset($_POST['status']) ? trim($_POST['status']) : 'available';
    $description = isset($_POST['description']) ? trim($_POST['description']) : '';
    $specifications = isset($_POST['specifications']) ? trim($_POST['specifications']) : '';
    $notes = isset($_POST['notes']) ? trim($_POST['notes']) : '';
    $tags = isset($_POST['tags']) ? trim($_POST['tags']) : '';

    // Validate required fields
    if ($item_id <= 0) {
        throw new Exception('Invalid item ID');
    }

    if (empty($item_name)) {
        throw new Exception('Item name is required');
    }

    if (empty($serial_number)) {
        throw new Exception('Serial number is required');
    }

    // ========== IMAGE HANDLING ==========
    $image_path = null;
    $remove_image = isset($_POST['remove_image']) && $_POST['remove_image'] == '1';
    
    // Check if we need to remove the image
    if ($remove_image) {
        // Get current image to delete file
        $imgStmt = $conn->prepare("SELECT image FROM items WHERE id = ?");
        $imgStmt->bind_param("i", $item_id);
        $imgStmt->execute();
        $imgResult = $imgStmt->get_result();
        $currentImg = $imgResult->fetch_assoc();
        
        if ($currentImg && !empty($currentImg['image'])) {
            $oldImagePath = $rootDir . '/' . $currentImg['image'];
            if (file_exists($oldImagePath)) {
                unlink($oldImagePath);
            }
        }
        $imgStmt->close();
        $image_path = null;
        
    } elseif (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        // Handle new image upload
        $upload_dir = $rootDir . '/uploads/';
        
        // Create directory if it doesn't exist
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        // Get file info
        $file = $_FILES['image'];
        $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        
        if (!in_array($file_extension, $allowed_extensions)) {
            throw new Exception('Invalid file type. Allowed: JPG, PNG, GIF, WebP');
        }
        
        // Generate unique filename
        $filename = 'item_' . $item_id . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $file_extension;
        $filepath = $upload_dir . $filename;
        $relative_path = 'uploads/' . $filename;
        
        // Move uploaded file
        if (move_uploaded_file($file['tmp_name'], $filepath)) {
            // Delete old image if exists
            $imgStmt = $conn->prepare("SELECT image FROM items WHERE id = ?");
            $imgStmt->bind_param("i", $item_id);
            $imgStmt->execute();
            $imgResult = $imgStmt->get_result();
            $currentImg = $imgResult->fetch_assoc();
            
            if ($currentImg && !empty($currentImg['image'])) {
                $oldImagePath = $rootDir . '/' . $currentImg['image'];
                if (file_exists($oldImagePath)) {
                    unlink($oldImagePath);
                }
            }
            $imgStmt->close();
            
            $image_path = $relative_path;
            $response['image_path'] = $image_path;
        } else {
            throw new Exception('Failed to upload image');
        }
    } else {
        // Keep existing image - get current image path
        $imgStmt = $conn->prepare("SELECT image FROM items WHERE id = ?");
        $imgStmt->bind_param("i", $item_id);
        $imgStmt->execute();
        $imgResult = $imgStmt->get_result();
        $currentImg = $imgResult->fetch_assoc();
        $image_path = $currentImg['image'] ?? null;
        $imgStmt->close();
    }

    // Update item with image field
    $sql = "UPDATE items SET 
                item_name = ?,
                serial_number = ?,
                category = ?,
                department = ?,
                brand = ?,
                model = ?,
                brand_model = ?,
                `condition` = ?,
                stock_location = ?,
                storage_location = ?,
                current_location = ?,
                quantity = ?,
                status = ?,
                description = ?,
                specifications = ?,
                notes = ?,
                tags = ?,
                image = ?
            WHERE id = ?";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('Prepare failed: ' . $conn->error);
    }

    $stmt->bind_param(
        "sssssssssssissssssi",
        $item_name,
        $serial_number,
        $category,
        $department,
        $brand,
        $model,
        $brand_model,
        $condition,
        $stock_location,
        $storage_location,
        $current_location,
        $quantity,
        $status,
        $description,
        $specifications,
        $notes,
        $tags,
        $image_path,
        $item_id
    );

    if (!$stmt->execute()) {
        throw new Exception('Update failed: ' . $stmt->error);
    }

    // ========== NEW: Propagation to similar items ==========
    $apply_to_similar = isset($_POST['apply_to_similar']) && $_POST['apply_to_similar'] == '1';
    if ($apply_to_similar && !empty($image_path)) {
        // Update all items with same item_name
        $updateSimilarSql = "UPDATE items SET image = ? WHERE item_name = ? AND id != ?";
        $similarStmt = $conn->prepare($updateSimilarSql);
        if ($similarStmt) {
            $similarStmt->bind_param("ssi", $image_path, $item_name, $item_id);
            $similarStmt->execute();
            $similarStmt->close();
            $response['similar_updated'] = true;
        }
    }

    // Handle accessories if provided
    if (isset($_POST['accessories']) && !empty($_POST['accessories'])) {
        // Delete existing accessories
        $delStmt = $conn->prepare("DELETE FROM item_accessories WHERE item_id = ?");
        $delStmt->bind_param("i", $item_id);
        $delStmt->execute();
        $delStmt->close();

        // Parse accessories
        $accessory_ids = json_decode($_POST['accessories'], true);

        if (is_array($accessory_ids) && count($accessory_ids) > 0) {
            // Insert new accessories
            $insStmt = $conn->prepare("INSERT INTO item_accessories (item_id, accessory_id) VALUES (?, ?)");
            foreach ($accessory_ids as $acc_id) {
                if (is_numeric($acc_id) && $acc_id > 0) {
                    $insStmt->bind_param("ii", $item_id, $acc_id);
                    $insStmt->execute();
                }
            }
            $insStmt->close();
        }
    }

    $response['success'] = true;
    $response['message'] = 'Item updated successfully';

    $stmt->close();
    $db->close();
    
} catch (Exception $e) {
    $response['message'] = $e->getMessage();
    http_response_code(500);
}

echo json_encode($response);
exit();