<?php
// api/get_item.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');
require_once '../config/database.php';

try {
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

    if (!$id) {
        throw new Exception('Item ID required');
    }

    $db = getConnection();

    // Get item details with brand information
    $stmt = $db->prepare("
        SELECT 
            i.*,
            b.name as brand_name,
            b.code as brand_code,
            c.name as category_name,
            d.name as department_name
        FROM items i
        LEFT JOIN brands b ON i.brand = b.id
        LEFT JOIN categories c ON i.category = c.id
        LEFT JOIN departments d ON i.department = d.id OR i.department = d.code
        WHERE i.id = ?
    ");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        throw new Exception('Item not found');
    }

    $item = $result->fetch_assoc();

    // Get accessories for this item
    $accStmt = $db->prepare("
        SELECT a.id, a.name 
        FROM accessories a
        JOIN item_accessories ia ON a.id = ia.accessory_id
        WHERE ia.item_id = ?
    ");
    $accStmt->bind_param("i", $id);
    $accStmt->execute();
    $accResult = $accStmt->get_result();

    $accessories = [];
    $accessoryIds = [];
    while ($acc = $accResult->fetch_assoc()) {
        $accessories[] = $acc['name'];
        $accessoryIds[] = $acc['id'];
    }

    // ========== FIX QR CODE HANDLING ==========
    $qrCode = '';
    
    // Check if qr_code exists in the item array
    if (isset($item['qr_code'])) {
        $qrRaw = $item['qr_code'];
        
        // Handle different data types
        if (is_array($qrRaw)) {
            // If it's an array, try to extract the first element or a specific key
            error_log("QR code is an array for item ID: $id");
            if (isset($qrRaw['url'])) {
                $qrRaw = $qrRaw['url'];
            } elseif (isset($qrRaw['data'])) {
                $qrRaw = $qrRaw['data'];
            } elseif (isset($qrRaw[0])) {
                $qrRaw = $qrRaw[0];
            } else {
                $qrRaw = '';
            }
        } elseif (is_object($qrRaw)) {
            // If it's an object, convert to array first
            error_log("QR code is an object for item ID: $id");
            $qrRaw = (array)$qrRaw;
            if (isset($qrRaw['url'])) {
                $qrRaw = $qrRaw['url'];
            } elseif (isset($qrRaw['data'])) {
                $qrRaw = $qrRaw['data'];
            } elseif (isset($qrRaw[0])) {
                $qrRaw = $qrRaw[0];
            } else {
                $qrRaw = '';
            }
        } elseif (is_string($qrRaw)) {
            // Check if the string is actually a JSON encoded array/object
            $trimmed = trim($qrRaw);
            if (($trimmed[0] === '{' && $trimmed[strlen($trimmed)-1] === '}') || 
                ($trimmed[0] === '[' && $trimmed[strlen($trimmed)-1] === ']')) {
                $decoded = json_decode($qrRaw, true);
                if (json_last_error() === JSON_ERROR_NONE && $decoded !== null) {
                    error_log("QR code was JSON encoded string for item ID: $id");
                    if (isset($decoded['url'])) {
                        $qrRaw = $decoded['url'];
                    } elseif (isset($decoded['data'])) {
                        $qrRaw = $decoded['data'];
                    } elseif (isset($decoded[0])) {
                        $qrRaw = $decoded[0];
                    } else {
                        $qrRaw = '';
                    }
                }
            }
        }
        
        // Ensure it's a string
        $qrRaw = is_string($qrRaw) ? $qrRaw : '';
        $qrRaw = trim($qrRaw);
        
        // Validate the QR code value
        if (!empty($qrRaw) && 
            $qrRaw !== 'pending' && 
            $qrRaw !== 'null' && 
            $qrRaw !== 'Array' && 
            $qrRaw !== '[object Object]' &&
            $qrRaw !== '') {
            
            // If it's not a full URL or data URI, convert to full URL
            if (!preg_match('/^https?:\/\//i', $qrRaw) && !preg_match('/^data:/i', $qrRaw)) {
                // Check if it's a relative path
                $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
                $host = $_SERVER['HTTP_HOST'];
                $baseUrl = $protocol . $host;
                
                // Remove leading slash if present
                $qrRaw = ltrim($qrRaw, '/');
                
                // Build the full URL (adjust path as needed)
                $qrCode = $baseUrl . '/ability_app_main/' . $qrRaw;
            } else {
                $qrCode = $qrRaw;
            }
        } else {
            // No valid QR code, generate a default one based on item ID
            error_log("No valid QR code found for item ID: $id, generating default");
            $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
            $host = $_SERVER['HTTP_HOST'];
            $baseUrl = $protocol . $host;
            // Generate a QR code URL that will create a QR on the fly
            $qrCode = $baseUrl . '/ability_app_main/generate_qr.php?id=' . $id . '&type=item';
        }
    } else {
        // No qr_code field at all, generate one
        error_log("No qr_code field for item ID: $id, generating default");
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
        $host = $_SERVER['HTTP_HOST'];
        $baseUrl = $protocol . $host;
        $qrCode = $baseUrl . '/ability_app_main/generate_qr.php?id=' . $id . '&type=item';
    }
    
    // Final safety check - ensure qrCode is a string
    $qrCode = is_string($qrCode) ? $qrCode : '';
    
    // Log the final QR code value for debugging
    error_log("Final QR code for item ID $id: " . substr($qrCode, 0, 100));

    echo json_encode([
        'success' => true,
        'data' => [
            'id' => $item['id'],
            'item_name' => $item['item_name'],
            'serial_number' => $item['serial_number'],
            'category' => $item['category'],
            'status' => $item['status'],
            'condition' => $item['condition'],
            'brand' => $item['brand'],
            'brand_name' => $item['brand_name'],
            'brand_code' => $item['brand_code'],
            'model' => $item['model'],
            'brand_model' => $item['brand_model'],
            'stock_location' => $item['stock_location'],
            'storage_location' => $item['storage_location'],
            'department' => $item['department'],
            'department_name' => $item['department_name'],
            'category_name' => $item['category_name'],
            'quantity' => $item['quantity'],
            'description' => $item['description'],
            'specifications' => $item['specifications'],
            'notes' => $item['notes'],
            'tags' => $item['tags'],
            'image' => $item['image'],
            'qr_code' => $qrCode, // Now guaranteed to be a string
            'created_at' => $item['created_at'],
            'updated_at' => $item['updated_at'],
            'last_scanned' => $item['last_scanned'],
            'accessories' => $accessories,
            'accessory_ids' => $accessoryIds
        ]
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>