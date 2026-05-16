<?php
// api/items/export.php

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

require_once __DIR__ . '/../../config/database.php';

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

try {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    if ($conn->connect_error) {
        throw new Exception("Database connection failed: " . $conn->connect_error);
    }

    // Get parameters from request
    $search = $_GET['search'] ?? '';
    $category = $_GET['category'] ?? '';
    $status = $_GET['status'] ?? '';
    $location = $_GET['location'] ?? '';
    $condition = $_GET['condition'] ?? '';
    $sort = $_GET['sort'] ?? 'id';
    $order = $_GET['order'] ?? 'DESC';

    // Validate sort field
    $allowedSortFields = ['id', 'item_name', 'serial_number', 'category', 'status', 'stock_location', 'created_at', 'updated_at'];
    $sort = in_array($sort, $allowedSortFields) ? $sort : 'id';
    $order = strtoupper($order) === 'ASC' ? 'ASC' : 'DESC';

    // Build WHERE clause
    $whereConditions = [];
    $params = [];
    $types = '';

    if (!empty($search)) {
        $whereConditions[] = "(item_name LIKE ? OR serial_number LIKE ? OR description LIKE ? OR category LIKE ?)";
        $searchTerm = "%$search%";
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $types .= 'ssss';
    }

    if (!empty($category)) {
        $whereConditions[] = "category = ?";
        $params[] = $category;
        $types .= 's';
    }

    if (!empty($status)) {
        $whereConditions[] = "status = ?";
        $params[] = $status;
        $types .= 's';
    }

    if (!empty($location)) {
        $whereConditions[] = "stock_location = ?";
        $params[] = $location;
        $types .= 's';
    }

    if (!empty($condition)) {
        $whereConditions[] = "condition = ?";
        $params[] = $condition;
        $types .= 's';
    }

    $whereClause = '';
    if (!empty($whereConditions)) {
        $whereClause = 'WHERE ' . implode(' AND ', $whereConditions);
    }

    // Prepare and execute query
    $sql = "SELECT * FROM items $whereClause ORDER BY $sort $order";
    $stmt = $conn->prepare($sql);
    
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    $items = [];
    while ($row = $result->fetch_assoc()) {
        // Format the data
        $items[] = [
            'id' => (int)$row['id'],
            'name' => $row['item_name'] ?? '',
            'serial_number' => $row['serial_number'] ?? '',
            'category' => $row['category'] ?? '',
            'status' => $row['status'] ?? 'available',
            'stock_location' => $row['stock_location'] ?? '',
            'quantity' => (int)$row['quantity'] ?? 1,
            'condition' => $row['condition'] ?? '',
            'description' => $row['description'] ?? '',
            'brand_model' => $row['brand_model'] ?? '',
            'created_at' => $row['created_at'] ?? '',
            'updated_at' => $row['updated_at'] ?? '',
            'created_by' => $row['created_by'] ?? '',
            'modified_by' => $row['modified_by'] ?? '',
            'purchase_date' => $row['purchase_date'] ?? '',
            'purchase_price' => $row['purchase_price'] ?? '',
            'supplier' => $row['supplier'] ?? '',
            'warranty_info' => $row['warranty_info'] ?? '',
            'notes' => $row['notes'] ?? '',
            'qr_code' => $row['qr_code'] ?? ''
        ];
    }

    // Return successful response
    echo json_encode([
        'success' => true,
        'count' => count($items),
        'items' => $items,
        'filters' => [
            'search' => $search,
            'category' => $category,
            'status' => $status,
            'location' => $location,
            'condition' => $condition,
            'sort' => $sort,
            'order' => $order
        ]
    ]);

    $stmt->close();
    $conn->close();

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Export failed: ' . $e->getMessage()
    ]);
}
?>