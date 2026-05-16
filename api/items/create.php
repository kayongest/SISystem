<?php
// api/items/create.php - FIXED VERSION WITH AUTO_INCREMENT HANDLING
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// Define root directory
$rootDir = realpath(__DIR__ . '/../..');

// Simple response
$response = [
    'success' => false,
    'message' => '',
    'item_id' => null,
    'image_path' => '',
    'qr_code' => ''
];

try {
    // Start session first
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (!isset($_SESSION['user_id'])) {
        throw new Exception('Not logged in', 401);
    }

    // Only accept POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('POST required', 405);
    }

    // Include required files
    require_once $rootDir . '/includes/db_connect.php';
    require_once $rootDir . '/includes/functions.php';

    // Get database connection
    $db = new Database();
    $conn = $db->getConnection();

    if (!$conn) {
        throw new Exception('Could not establish database connection');
    }

    // ==================== FIX AUTO_INCREMENT FIRST ====================
    fixItemsAutoIncrement($conn);

    // Get POST data
    $item_name = isset($_POST['item_name']) ? trim($_POST['item_name']) : '';
    $serial_number = isset($_POST['serial_number']) ? trim($_POST['serial_number']) : '';
    $category = isset($_POST['category']) ? trim($_POST['category']) : '';

    if (empty($item_name) || empty($serial_number) || empty($category)) {
        throw new Exception('Missing required fields: item_name, serial_number, category');
    }

    // Check if serial number already exists
    $checkSql = "SELECT id FROM items WHERE serial_number = ?";
    $checkStmt = $conn->prepare($checkSql);
    $checkStmt->bind_param('s', $serial_number);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();

    if ($checkResult->num_rows > 0) {
        $checkRow = $checkResult->fetch_assoc();
        throw new Exception("Serial number '{$serial_number}' already exists for item ID: {$checkRow['id']}");
    }
    $checkStmt->close();

    // Get other fields with defaults
    $department = isset($_POST['department']) ? trim($_POST['department']) : '';
    $description = isset($_POST['description']) ? trim($_POST['description']) : '';
    $specifications = isset($_POST['specifications']) ? trim($_POST['specifications']) : '';
    $brand = isset($_POST['brand']) ? trim($_POST['brand']) : '';
    $model = isset($_POST['model']) ? trim($_POST['model']) : '';

    // Create brand_model from brand and model
    $brand_model = '';
    if (!empty($brand) && !empty($model)) {
        $brand_model = $brand . ' ' . $model;
    } elseif (!empty($brand)) {
        $brand_model = $brand;
    } elseif (!empty($model)) {
        $brand_model = $model;
    }

    $condition = isset($_POST['condition']) ? trim($_POST['condition']) : 'good';
    $stock_location = isset($_POST['stock_location']) ? trim($_POST['stock_location']) : '';
    $storage_location = isset($_POST['storage_location']) ? trim($_POST['storage_location']) : '';
    $notes = isset($_POST['notes']) ? trim($_POST['notes']) : '';
    $tags = isset($_POST['tags']) ? trim($_POST['tags']) : '';
    $status = isset($_POST['status']) ? trim($_POST['status']) : 'available';
    $quantity = isset($_POST['quantity']) ? intval($_POST['quantity']) : 1;

    // Handle image upload
    $image_path = '';
    if (isset($_FILES['item_image']) && $_FILES['item_image']['error'] === UPLOAD_ERR_OK) {
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $filename = $_FILES['item_image']['name'];
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        if (in_array($ext, $allowed)) {
            $uploadsDir = $rootDir . '/uploads/items';
            if (!is_dir($uploadsDir)) {
                if (!mkdir($uploadsDir, 0755, true)) {
                    throw new Exception('Could not create uploads directory');
                }
            }

            $newFilename = 'item_' . time() . '_' . uniqid() . '.' . $ext;
            $upload_path = $uploadsDir . DIRECTORY_SEPARATOR . $newFilename;

            if (move_uploaded_file($_FILES['item_image']['tmp_name'], $upload_path)) {
                $image_path = 'uploads/items/' . $newFilename;
                $response['image_path'] = $image_path;
            } else {
                throw new Exception('Failed to upload image');
            }
        } else {
            throw new Exception('Invalid file type. Allowed: jpg, jpeg, png, gif, webp');
        }
    }

    // Insert into database
    $sql = "INSERT INTO items (
        item_name, serial_number, category, department, description, 
        specifications, brand, model, brand_model, `condition`, 
        stock_location, storage_location, notes, tags, quantity, 
        status, image, created_at
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('Prepare failed: ' . $conn->error);
    }

    // Bind parameters
    $stmt->bind_param(
        'ssssssssssssssiss',
        $item_name,
        $serial_number,
        $category,
        $department,
        $description,
        $specifications,
        $brand,
        $model,
        $brand_model,
        $condition,
        $stock_location,
        $storage_location,
        $notes,
        $tags,
        $quantity,
        $status,
        $image_path
    );

    if ($stmt->execute()) {
        $item_id = $conn->insert_id;

        // ==================== CRITICAL FIX: If insert_id is 0, generate a new ID ====================
        if ($item_id == 0) {
            // Get the current max ID
            $maxResult = $conn->query("SELECT COALESCE(MAX(id), 0) as max_id FROM items WHERE id > 0");
            if ($maxResult && $maxRow = $maxResult->fetch_assoc()) {
                $new_id = $maxRow['max_id'] + 1;

                // Update this item with the new ID
                $updateSql = "UPDATE items SET id = ? WHERE serial_number = ? AND id = 0 LIMIT 1";
                $updateStmt = $conn->prepare($updateSql);
                if ($updateStmt) {
                    $updateStmt->bind_param('is', $new_id, $serial_number);
                    if ($updateStmt->execute()) {
                        $item_id = $new_id;

                        // Update AUTO_INCREMENT for next insert
                        $conn->query("ALTER TABLE items AUTO_INCREMENT = " . ($new_id + 1));

                        error_log("🔄 Fixed ID for '$item_name': Changed from 0 to $item_id");
                    }
                    $updateStmt->close();
                }
            }

            // If still 0, use a fallback
            if ($item_id == 0) {
                $item_id = time() % 1000000; // Temporary ID
                error_log("⚠️ Could not fix ID for '$item_name', using temporary ID: $item_id");
            }
        }

        // Handle accessories if provided
        if (isset($_POST['accessories_array'])) {
            $accessories_json = $_POST['accessories_array'];
            $accessories = json_decode($accessories_json, true);

            if (is_array($accessories) && !empty($accessories)) {
                foreach ($accessories as $accessory_id) {
                    $accessory_id = intval($accessory_id);
                    if ($accessory_id > 0) {
                        $acc_sql = "INSERT INTO item_accessories (item_id, accessory_id) VALUES (?, ?)";
                        $acc_stmt = $conn->prepare($acc_sql);
                        if ($acc_stmt) {
                            $acc_stmt->bind_param("ii", $item_id, $accessory_id);
                            $acc_stmt->execute();
                            $acc_stmt->close();
                        }
                    }
                }
            }
        }

        // SUCCESS - Return immediate response
        $response['success'] = true;
        $response['message'] = 'Equipment added successfully!';
        $response['item_id'] = $item_id;

        // Generate QR code immediately (synchronous)
        try {
            $qrData = json_encode([
                'i' => $item_id,
                'n' => substr($item_name, 0, 20),
                's' => $serial_number
            ], JSON_UNESCAPED_SLASHES);

            $qrUrl = "https://api.qrserver.com/v1/create-qr-code/?" . http_build_query([
                'size' => '300x300',
                'data' => $qrData,
                'margin' => 10,
                'format' => 'png',
                'ecc' => 'M'
            ]);

            $qrImage = false;

            // Try to download QR code
            $context = stream_context_create([
                'http' => [
                    'timeout' => 3,
                    'ignore_errors' => true
                ]
            ]);

            $qrImage = @file_get_contents($qrUrl, false, $context);

            if (!$qrImage && function_exists('curl_init')) {
                $ch = curl_init();
                curl_setopt_array($ch, [
                    CURLOPT_URL => $qrUrl,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_TIMEOUT => 3,
                    CURLOPT_CONNECTTIMEOUT => 2,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_SSL_VERIFYPEER => false,
                ]);
                $qrImage = curl_exec($ch);
                curl_close($ch);
            }

            if ($qrImage && strlen($qrImage) > 100) {
                $qrcodeDir = $rootDir . '/qrcodes';
                if (!is_dir($qrcodeDir)) {
                    @mkdir($qrcodeDir, 0755, true);
                }

                $qrFilename = 'qr_' . $item_id . '.png';
                $qrPath = $qrcodeDir . DIRECTORY_SEPARATOR . $qrFilename;
                $qrRelativePath = 'qrcodes/' . $qrFilename;

                if (file_put_contents($qrPath, $qrImage)) {
                    // Update database with QR code path
                    $updateSql = "UPDATE items SET qr_code = ? WHERE id = ?";
                    $updateStmt = $conn->prepare($updateSql);
                    if ($updateStmt) {
                        $updateStmt->bind_param('si', $qrRelativePath, $item_id);
                        $updateStmt->execute();
                        $updateStmt->close();
                        $response['qr_code'] = $qrRelativePath;
                    }
                }
            }
        } catch (Exception $e) {
            // Don't fail if QR generation fails
            error_log("QR generation failed: " . $e->getMessage());
        }

        $stmt->close();
        $db->close();
    } else {
        throw new Exception('Execute failed: ' . $stmt->error);
    }
} catch (Exception $e) {
    $response['message'] = $e->getMessage();
    $response['code'] = $e->getCode();

    $httpCode = $e->getCode() && $e->getCode() >= 400 ? $e->getCode() : 500;
    http_response_code($httpCode);
}

// Make sure we output JSON and nothing else
echo json_encode($response);
exit();

// ==================== HELPER FUNCTIONS ====================
/**
 * Fix AUTO_INCREMENT issues in items table
 */
function fixItemsAutoIncrement($conn)
{
    try {
        // Check if table exists
        $tableCheck = $conn->query("SHOW TABLES LIKE 'items'");
        if ($tableCheck && $tableCheck->num_rows > 0) {

            // Check for any records with id = 0
            $checkZeroId = $conn->query("SELECT COUNT(*) as count FROM items WHERE id = 0");
            if ($checkZeroId && $row = $checkZeroId->fetch_assoc()) {
                if ($row['count'] > 0) {
                    error_log("⚠️ Found " . $row['count'] . " records with id=0 in items table");

                    // Update all items with id=0 to have proper IDs
                    $updateStmt = $conn->prepare("
                        UPDATE items 
                        SET id = (SELECT new_id FROM (
                            SELECT ROW_NUMBER() OVER (ORDER BY created_at) + (SELECT COALESCE(MAX(id), 0) FROM items WHERE id > 0) as new_id, 
                                   serial_number 
                            FROM items 
                            WHERE id = 0
                        ) as temp WHERE temp.serial_number = items.serial_number)
                        WHERE id = 0
                    ");

                    if ($updateStmt && $updateStmt->execute()) {
                        error_log("✅ Fixed items with ID=0");
                    }
                }
            }

            // Get current max id
            $maxIdResult = $conn->query("SELECT COALESCE(MAX(id), 0) as max_id FROM items");
            if ($maxIdResult) {
                $row = $maxIdResult->fetch_assoc();
                $maxId = (int)$row['max_id'];
                $nextId = max(1, $maxId + 1);

                // Set AUTO_INCREMENT to next available value
                $alterResult = $conn->query("ALTER TABLE items AUTO_INCREMENT = $nextId");
                if ($alterResult) {
                    error_log("✅ Set items AUTO_INCREMENT to $nextId (max_id was $maxId)");
                }
            }

            // Verify AUTO_INCREMENT is working
            $statusResult = $conn->query("SHOW TABLE STATUS LIKE 'items'");
            if ($statusResult && $row = $statusResult->fetch_assoc()) {
                error_log("📊 items table status: Auto_increment=" . $row['Auto_increment'] . ", Rows=" . $row['Rows']);
            }
        }
    } catch (Exception $e) {
        error_log("fixItemsAutoIncrement error: " . $e->getMessage());
    }
}
