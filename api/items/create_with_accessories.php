<?php
// create_with_accessories.php - FIXED VERSION

session_start();

// Fix the require_once path - use absolute path
require_once __DIR__ . '/../../includes/database_fix.php';
require_once __DIR__ . '/../../includes/functions.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isLoggedIn()) {
    echo json_encode([
        'success' => false,
        'message' => 'Authentication required',
        'redirect' => BASE_URL . 'login.php'
    ]);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Get database connection
        $db = new DatabaseFix();
        $conn = $db->getConnection();

        // Begin transaction
        $conn->begin_transaction();

        // 1. Sanitize and get basic item data
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

        // Validate required fields
        if (empty($item_name) || empty($serial_number) || empty($category)) {
            throw new Exception('Required fields missing: Item Name, Serial Number, and Category are required');
        }

        // Check if serial number exists
        $checkStmt = $conn->prepare("SELECT COUNT(*) as count FROM items WHERE serial_number = ?");
        if (!$checkStmt) {
            throw new Exception('Database error: ' . $conn->error);
        }
        $checkStmt->bind_param("s", $serial_number);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();
        $row = $checkResult->fetch_assoc();
        $count = $row['count'] ?? 0;
        $checkStmt->close();

        if ($count > 0) {
            throw new Exception('Serial number "' . $serial_number . '" already exists. Please use a unique serial number.');
        }

        // 2. Insert the item
        $sql = "INSERT INTO items (
            item_name, serial_number, quantity, brand, model, 
            category, department, status, `condition`, stock_location, 
            description, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";

        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception('Database prepare error: ' . $conn->error);
        }

        $stmt->bind_param(
            "ssissssssss",
            $item_name,
            $serial_number,
            $quantity,
            $brand,
            $model,
            $category,
            $department,
            $status,
            $condition,
            $stock_location,
            $description
        );

        if (!$stmt->execute()) {
            throw new Exception('Failed to insert item: ' . $stmt->error);
        }

        $item_id = $conn->insert_id;
        $stmt->close();

        // 3. Handle image upload
        $image_path = null;
        if (isset($_FILES['item_image']) && $_FILES['item_image']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = __DIR__ . '/../../uploads/items/';

            // Create directory if it doesn't exist
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            $fileExtension = strtolower(pathinfo($_FILES['item_image']['name'], PATHINFO_EXTENSION));
            $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

            if (!in_array($fileExtension, $allowedExtensions)) {
                throw new Exception('Invalid file type. Allowed types: ' . implode(', ', $allowedExtensions));
            }

            $maxSize = 5 * 1024 * 1024; // 5MB
            if ($_FILES['item_image']['size'] > $maxSize) {
                throw new Exception('File size too large. Maximum size is 5MB.');
            }

            $fileName = 'item_' . $item_id . '_' . time() . '.' . $fileExtension;
            $filePath = $uploadDir . $fileName;

            if (move_uploaded_file($_FILES['item_image']['tmp_name'], $filePath)) {
                $image_path = 'uploads/items/' . $fileName;

                $updateStmt = $conn->prepare("UPDATE items SET image = ? WHERE id = ?");
                if ($updateStmt) {
                    $updateStmt->bind_param("si", $image_path, $item_id);
                    $updateStmt->execute();
                    $updateStmt->close();
                }
            }
        }

        // 4. Handle accessories - WITH ERROR CHECKING
        $accessories = $_POST['accessories'] ?? [];
        $accessoryCount = 0;

        if (!empty($accessories) && is_array($accessories)) {
            // First check if item_accessories table exists
            $tableCheck = $conn->query("SHOW TABLES LIKE 'item_accessories'");
            if ($tableCheck && $tableCheck->num_rows > 0) {
                foreach ($accessories as $accessory_id) {
                    $accessory_id = intval($accessory_id);
                    if ($accessory_id > 0) {
                        // Check if accessory exists and is active
                        $checkAcc = $conn->prepare("SELECT id, name FROM accessories WHERE id = ? AND is_active = 1");
                        if ($checkAcc) {
                            $checkAcc->bind_param("i", $accessory_id);
                            $checkAcc->execute();
                            $checkResult = $checkAcc->get_result();

                            if ($checkResult && $checkResult->num_rows > 0) {
                                $accessory = $checkResult->fetch_assoc();

                                // Link accessory to item
                                $linkStmt = $conn->prepare("
                                    INSERT INTO item_accessories (item_id, accessory_id, created_at) 
                                    VALUES (?, ?, NOW())
                                ");

                                if ($linkStmt) {
                                    $linkStmt->bind_param("ii", $item_id, $accessory_id);
                                    if ($linkStmt->execute()) {
                                        $accessoryCount++;
                                    }
                                    $linkStmt->close();
                                }

                                // Update accessory quantities if needed
                                $updateStmt = $conn->prepare("
                                    UPDATE accessories 
                                    SET 
                                        total_quantity = total_quantity + 1,
                                        available_quantity = GREATEST(0, available_quantity - 1)
                                    WHERE id = ?
                                ");

                                if ($updateStmt) {
                                    $updateStmt->bind_param("i", $accessory_id);
                                    $updateStmt->execute();
                                    $updateStmt->close();
                                }
                            }
                            $checkAcc->close();
                        }
                    }
                }
            } else {
                // Table doesn't exist - just log it but don't fail
                error_log("item_accessories table not found. Skipping accessory linking.");
            }
        }

        // 5. Generate QR code if requested
        // 5. Generate QR code if requested
        $qr_path = null;
        if (isset($_POST['generate_qr']) && $_POST['generate_qr'] == '1') {
            // Include QR generator
            require_once __DIR__ . '/../../includes/qr_generator.php';

            // Generate actual QR code image
            $qr_path = generateQRCodeForItem(
                $item_id,
                $item_name,
                $serial_number,
                $stock_location ?? ''
            );

            if ($qr_path) {
                // Update item with QR code path
                $updateStmt = $conn->prepare("UPDATE items SET qr_code = ? WHERE id = ?");
                if ($updateStmt) {
                    $updateStmt->bind_param("si", $qr_path, $item_id);
                    $updateStmt->execute();
                    $updateStmt->close();
                }
            } else {
                // Fallback: create a simple text file if QR generation fails
                $qrDir = __DIR__ . '/../../qrcodes/';
                if (!is_dir($qrDir)) {
                    mkdir($qrDir, 0777, true);
                }

                $qrFileName = 'qr_' . $item_id . '_' . md5($serial_number) . '.txt';
                $qrFilePath = $qrDir . $qrFileName;

                $qrContent = "ITEM INFORMATION:\n" .
                    "ID: $item_id\n" .
                    "Name: $item_name\n" .
                    "Serial: $serial_number\n" .
                    "Category: $category\n" .
                    "Brand/Model: $brand $model\n" .
                    "Status: $status\n" .
                    "Condition: $condition\n" .
                    "QR Generated: " . date('Y-m-d H:i:s') . "\n";

                if (file_put_contents($qrFilePath, $qrContent)) {
                    $qr_path = 'qrcodes/' . $qrFileName;

                    $updateStmt = $conn->prepare("UPDATE items SET qr_code = ? WHERE id = ?");
                    if ($updateStmt) {
                        $updateStmt->bind_param("si", $qr_path, $item_id);
                        $updateStmt->execute();
                        $updateStmt->close();
                    }
                }
            }
        }

        // Commit transaction
        $conn->commit();

        echo json_encode([
            'success' => true,
            'message' => 'Equipment added successfully' . ($accessoryCount > 0 ? ' with ' . $accessoryCount . ' accessories' : ''),
            'item_id' => $item_id,
            'item_name' => $item_name,
            'serial_number' => $serial_number,
            'accessories_count' => $accessoryCount,
            'image' => $image_path,
            'qr_code' => $qr_path
        ]);
    } catch (Exception $e) {
        // Rollback transaction if any error occurs
        if (isset($conn) && $conn instanceof mysqli) {
            $conn->rollback();
        }

        error_log("Error in create_with_accessories.php: " . $e->getMessage());

        echo json_encode([
            'success' => false,
            'message' => 'Error: ' . $e->getMessage()
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method. Please use POST.'
    ]);
}
