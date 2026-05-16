<?php
session_start();
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id']) && !isset($_SESSION['username'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// Get item ID
$itemId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($itemId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid item ID']);
    exit();
}

// Database connection
$conn = new mysqli('localhost', 'root', '', 'ability_db');
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit();
}

// Query to get item by ID using your actual table structure
$sql = "SELECT 
            id,
            item_name,
            serial_number,
            category,
            brand,
            model,
            brand_model,
            department,
            description,
            specifications,
            `condition`,
            stock_location,
            storage_location as location,
            quantity,
            status,
            image,
            qr_code,
            tags,
            current_location,
            created_at,
            updated_at,
            last_scanned
        FROM items 
        WHERE id = ? 
           OR serial_number = ? 
           OR item_name LIKE ? 
        LIMIT 1";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Prepare failed: ' . $conn->error]);
    exit();
}

$searchTerm = "%$itemId%";
$serialSearch = (string)$itemId;
$stmt->bind_param("iss", $itemId, $serialSearch, $searchTerm);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    // Determine the best location to use
    $location = $row['current_location'] ?? $row['stock_location'] ?? $row['storage_location'] ?? 'Unknown';

    // Build item name from available fields
    $itemName = $row['item_name'];
    if (!empty($row['brand']) || !empty($row['model'])) {
        $brandModel = trim($row['brand'] . ' ' . $row['model']);
        if (!empty($brandModel)) {
            $itemName .= ' (' . $brandModel . ')';
        }
    }

    // Determine status
    $status = $row['status'] ?? 'available';
    if ($row['condition'] == 'faulty' || $row['condition'] == 'damaged') {
        $status = 'maintenance';
    }

    echo json_encode([
        'success' => true,
        'item' => [
            'id' => (int)$row['id'],
            'item_id' => (int)$row['id'],
            'name' => $itemName,
            'item_name' => $row['item_name'],
            'serial_number' => $row['serial_number'] ?? '',
            'serial' => $row['serial_number'] ?? '',
            'category' => $row['category'] ?? 'Equipment',
            'category_name' => $row['category'] ?? 'Equipment',
            'brand' => $row['brand'] ?? '',
            'model' => $row['model'] ?? '',
            'brand_model' => $row['brand_model'] ?? '',
            'department' => $row['department'] ?? '',
            'description' => $row['description'] ?? '',
            'specifications' => $row['specifications'] ?? '',
            'condition' => $row['condition'] ?? 'good',
            'status' => $status,
            'stock_location' => $location,
            'location' => $location,
            'storage_location' => $row['storage_location'] ?? '',
            'current_location' => $row['current_location'] ?? '',
            'quantity' => (int)($row['quantity'] ?? 1),
            'image' => $row['image'] ?? '',
            'qr_code' => $row['qr_code'] ?? '',
            'tags' => $row['tags'] ?? '',
            'last_scanned' => $row['last_scanned'] ?? null
        ]
    ]);
} else {
    // Try searching by serial number only (more flexible)
    $sql2 = "SELECT * FROM items WHERE serial_number LIKE ? OR item_name LIKE ? LIMIT 1";
    $stmt2 = $conn->prepare($sql2);
    $searchWildcard = "%$itemId%";
    $stmt2->bind_param("ss", $searchWildcard, $searchWildcard);
    $stmt2->execute();
    $result2 = $stmt2->get_result();

    if ($row2 = $result2->fetch_assoc()) {
        $location = $row2['current_location'] ?? $row2['stock_location'] ?? $row2['storage_location'] ?? 'Unknown';
        $status = $row2['status'] ?? 'available';
        if ($row2['condition'] == 'faulty' || $row2['condition'] == 'damaged') {
            $status = 'maintenance';
        }

        echo json_encode([
            'success' => true,
            'item' => [
                'id' => (int)$row2['id'],
                'item_id' => (int)$row2['id'],
                'name' => $row2['item_name'],
                'item_name' => $row2['item_name'],
                'serial_number' => $row2['serial_number'] ?? '',
                'serial' => $row2['serial_number'] ?? '',
                'category' => $row2['category'] ?? 'Equipment',
                'category_name' => $row2['category'] ?? 'Equipment',
                'brand' => $row2['brand'] ?? '',
                'model' => $row2['model'] ?? '',
                'brand_model' => $row2['brand_model'] ?? '',
                'department' => $row2['department'] ?? '',
                'description' => $row2['description'] ?? '',
                'specifications' => $row2['specifications'] ?? '',
                'condition' => $row2['condition'] ?? 'good',
                'status' => $status,
                'stock_location' => $location,
                'location' => $location,
                'storage_location' => $row2['storage_location'] ?? '',
                'current_location' => $row2['current_location'] ?? '',
                'quantity' => (int)($row2['quantity'] ?? 1),
                'image' => $row2['image'] ?? '',
                'qr_code' => $row2['qr_code'] ?? '',
                'tags' => $row2['tags'] ?? '',
                'last_scanned' => $row2['last_scanned'] ?? null
            ]
        ]);
    } else {
        // No item found
        echo json_encode([
            'success' => false,
            'message' => 'Item not found with ID: ' . $itemId
        ]);
    }
    $stmt2->close();
}

$stmt->close();
$conn->close();
