<?php
header('Content-Type: application/json');
error_log("=== get_item_by_scan.php called ===");

// Database connection
$host = 'localhost';
$username = 'root';
$password = '';
$database = 'ability_db';

$conn = new mysqli($host, $username, $password, $database);

if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed: ' . $conn->connect_error]);
    exit();
}

$scanData = $_POST['scan_data'] ?? $_GET['scan_data'] ?? $_POST['id'] ?? $_GET['id'] ?? null;

error_log("Scan data received: " . $scanData);

if (!$scanData) {
    echo json_encode(['success' => false, 'message' => 'No scan data provided']);
    exit();
}

// Function to extract ID from various QR code formats
function extractItemId($data) {
    // Format 1: JSON {"i":10,"n":"TV Screen","s":"SN123"}
    $json = json_decode($data, true);
    if ($json) {
        if (isset($json['i'])) return $json['i'];
        if (isset($json['id'])) return $json['id'];
        if (isset($json['item_id'])) return $json['item_id'];
    }
    
    // Format 2: Pipe-delimited "ID:48|SN:xxx|N:xxx"
    if (preg_match('/ID:(\d+)/i', $data, $matches)) {
        return $matches[1];
    }
    
    // Format 3: Plain number
    if (is_numeric($data)) {
        return $data;
    }
    
    return null;
}

// Function to extract serial number
function extractSerialNumber($data) {
    $json = json_decode($data, true);
    if ($json) {
        if (isset($json['s'])) return $json['s'];
        if (isset($json['serial'])) return $json['serial'];
        if (isset($json['serial_number'])) return $json['serial_number'];
    }
    
    if (preg_match('/SN:([^|]+)/i', $data, $matches)) {
        return trim($matches[1]);
    }
    
    return null;
}

$itemId = extractItemId($scanData);
$serialNumber = extractSerialNumber($scanData);

error_log("Extracted ID: " . ($itemId ?? 'null'));
error_log("Extracted Serial: " . ($serialNumber ?? 'null'));

$item = null;

// Try to find by ID first
if ($itemId) {
    $stmt = $conn->prepare("SELECT id, item_name, serial_number, status, stock_location, image, (SELECT COUNT(*) FROM items WHERE item_name = i.item_name) as total_group_count FROM items i WHERE i.id = ?");
    if ($stmt) {
        $stmt->bind_param("i", $itemId);
        $stmt->execute();
        $result = $stmt->get_result();
        $item = $result->fetch_assoc();
        $stmt->close();
        error_log("Search by ID " . $itemId . ": " . ($item ? "FOUND" : "NOT FOUND"));
    }
}

// If not found by ID, try by serial number
if (!$item && $serialNumber) {
    $stmt = $conn->prepare("SELECT id, item_name, serial_number, status, stock_location, image, (SELECT COUNT(*) FROM items WHERE item_name = i.item_name) as total_group_count FROM items i WHERE i.serial_number = ?");
    if ($stmt) {
        $stmt->bind_param("s", $serialNumber);
        $stmt->execute();
        $result = $stmt->get_result();
        $item = $result->fetch_assoc();
        $stmt->close();
        error_log("Search by Serial " . $serialNumber . ": " . ($item ? "FOUND" : "NOT FOUND"));
    }
}

// If still not found, try by QR code exact match
if (!$item) {
    $stmt = $conn->prepare("SELECT id, item_name, serial_number, status, stock_location, image, (SELECT COUNT(*) FROM items WHERE item_name = i.item_name) as total_group_count FROM items i WHERE i.qr_code = ?");
    if ($stmt) {
        $stmt->bind_param("s", $scanData);
        $stmt->execute();
        $result = $stmt->get_result();
        $item = $result->fetch_assoc();
        $stmt->close();
        error_log("Search by QR code: " . ($item ? "FOUND" : "NOT FOUND"));
    }
}

if ($item) {
    $itemData = [
        'id' => $item['id'],
        'item_name' => $item['item_name'],
        'serial_number' => $item['serial_number'],
        'status' => $item['status'],
        'stock_location' => $item['stock_location'],
        'image' => $item['image'],
        'total_group_count' => $item['total_group_count']
    ];
    echo json_encode([
        'success' => true,
        'item' => $itemData,
        'data' => $itemData
    ]);
} else {
    // Return the parsed data to help debug
    echo json_encode([
        'success' => false,
        'message' => 'Item not found in database',
        'debug' => [
            'scanned' => $scanData,
            'extracted_id' => $itemId,
            'extracted_serial' => $serialNumber
        ]
    ]);
}

$conn->close();
?>