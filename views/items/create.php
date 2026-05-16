<?php
// api/items/create.php - CORRECTED VERSION
header('Content-Type: application/json');

// Turn off HTML output for JSON API
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/php_errors.log');

// Define root directory
$rootDir = realpath(__DIR__ . '/../..');

$response = [
    'success' => false,
    'message' => '',
    'errors' => [],
    'item_id' => null,
    'debug' => [
        'rootDir' => $rootDir,
        'currentDir' => __DIR__,
        'request_method' => $_SERVER['REQUEST_METHOD']
    ]
];

try {
    // Include required files ONCE
    require_once $rootDir . '/bootstrap.php';
    require_once $rootDir . '/includes/db_connect.php';
    require_once $rootDir . '/includes/functions.php';

    // Check login
    if (!function_exists('isLoggedIn') || !isLoggedIn()) {
        throw new Exception('Authentication required', 401);
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Method not allowed', 405);
    }

    // Database connection
    $conn = getConnection();

    // Validate required fields
    $required = ['item_name', 'serial_number', 'category'];
    $errors = [];
    foreach ($required as $field) {
        if (empty(trim($_POST[$field] ?? ''))) {
            $errors[$field] = 'This field is required';
        }
    }

    if (!empty($errors)) {
        $response['errors'] = $errors;
        $response['message'] = 'Please fix the errors below';
        ob_end_clean();
        echo json_encode($response);
        exit();
    }

    // Process data
    $item_name = trim($_POST['item_name']);
    $serial_number = trim($_POST['serial_number']);
    $category = trim($_POST['category']);
    $department = trim($_POST['department'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $specifications = trim($_POST['specifications'] ?? '');
    $brand = trim($_POST['brand'] ?? '');
    $model = trim($_POST['model'] ?? '');

    // Create brand_model from brand and model
    $brand_model = '';
    if (!empty($brand) && !empty($model)) {
        $brand_model = $brand . ' ' . $model;
    } elseif (!empty($brand)) {
        $brand_model = $brand;
    } elseif (!empty($model)) {
        $brand_model = $model;
    }

    $condition = trim($_POST['condition'] ?? 'good');
    $stock_location = trim($_POST['stock_location'] ?? '');
    $storage_location = trim($_POST['storage_location'] ?? '');
    $notes = trim($_POST['notes'] ?? '');
    $tags = trim($_POST['tags'] ?? '');
    $quantity = intval($_POST['quantity'] ?? 1);
    $status = trim($_POST['status'] ?? 'available');

    // Handle image upload
    $image_path = '';
    if (isset($_FILES['item_image']) && $_FILES['item_image']['error'] == 0) {
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $filename = $_FILES['item_image']['name'];
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        if (in_array($ext, $allowed)) {
            $uploadsDir = $rootDir . '/uploads/items';
            if (!is_dir($uploadsDir)) {
                mkdir($uploadsDir, 0755, true);
            }

            $newFilename = 'item_' . time() . '_' . rand(1000, 9999) . '.' . $ext;
            $upload_path = $uploadsDir . '/' . $newFilename;

            if (move_uploaded_file($_FILES['item_image']['tmp_name'], $upload_path)) {
                $image_path = 'uploads/items/' . $newFilename;
                $response['debug']['image_uploaded'] = true;
                $response['debug']['image_path'] = $image_path;
            }
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
        throw new Exception('Failed to prepare statement: ' . $conn->error);
    }

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
        $response['success'] = true;
        $response['message'] = 'Equipment added successfully!';
        $response['item_id'] = $item_id;
        $stmt->close();

        // QR CODE GENERATION
        try {
            $qrcodeDir = $rootDir . '/qrcodes';

            if (!is_dir($qrcodeDir)) {
                mkdir($qrcodeDir, 0755, true);
            }

            // Generate QR data
            $qrData = [
                'id' => $item_id,
                'name' => $item_name,
                'serial' => $serial_number,
                'location' => $stock_location ?: $storage_location,
                'url' => 'http://' . $_SERVER['HTTP_HOST'] . '/ability_app_main/items/view.php?id=' . $item_id,
                'type' => 'equipment',
                'timestamp' => time(),
                'system' => 'aBility Manager'
            ];

            $qrDataString = json_encode($qrData, JSON_UNESCAPED_SLASHES);
            $qrFilename = 'qr_' . $item_id . '.png';
            $qrPath = $qrcodeDir . '/' . $qrFilename;
            $qrRelativePath = 'qrcodes/' . $qrFilename;

            $googleUrl = "https://chart.googleapis.com/chart?cht=qr&chs=300x300&chl=" . urlencode($qrDataString) . "&choe=UTF-8";

            $qrImage = @file_get_contents($googleUrl);

            if ($qrImage === false) {
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $googleUrl);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($ch, CURLOPT_TIMEOUT, 10);
                $qrImage = curl_exec($ch);
                curl_close($ch);
            }

            if ($qrImage && file_put_contents($qrPath, $qrImage)) {
                // Update database with QR code path
                $updateSql = "UPDATE items SET qr_code = ? WHERE id = ?";
                $updateStmt = $conn->prepare($updateSql);
                if ($updateStmt) {
                    $updateStmt->bind_param('si', $qrRelativePath, $item_id);
                    $updateStmt->execute();
                    $updateStmt->close();

                    $response['qr_code'] = $qrRelativePath;
                    $response['qr_url'] = 'http://' . $_SERVER['HTTP_HOST'] . '/ability_app_main/' . $qrRelativePath;
                }
            }
        } catch (Exception $qrError) {
            $response['debug']['qr_warning'] = $qrError->getMessage();
        }
    } else {
        throw new Exception('Failed to execute query: ' . $stmt->error);
    }
} catch (Exception $e) {
    $response['message'] = $e->getMessage();
    http_response_code(500);
}

// Clean up
if (isset($stmt)) $stmt->close();
if (isset($conn)) $conn->close();

echo json_encode($response, JSON_PRETTY_PRINT);
exit();
