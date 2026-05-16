<?php
// api/items/datatable.php - Server-side processing for DataTables
require_once '../../bootstrap.php';
require_once '../../includes/db_connect.php';
require_once '../../includes/functions.php';

// Check authentication
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$conn = getConnection();

// Get DataTables parameters
$draw = isset($_POST['draw']) ? intval($_POST['draw']) : 1;
$start = isset($_POST['start']) ? intval($_POST['start']) : 0;
$length = isset($_POST['length']) ? intval($_POST['length']) : 25;
$search = isset($_POST['search']['value']) ? $_POST['search']['value'] : '';

// Get ordering
$orderColumn = isset($_POST['order'][0]['column']) ? intval($_POST['order'][0]['column']) : 1;
$orderDir = isset($_POST['order'][0]['dir']) ? $_POST['order'][0]['dir'] : 'desc';

// Map column indexes to database columns
$columns = [
    0 => 'checkbox',
    1 => 'row_number',
    2 => 'id',
    3 => 'item_name',
    4 => 'serial_number',
    5 => 'category',
    6 => 'department',
    7 => 'status',
    8 => 'stock_location',
    9 => 'quantity',
    10 => 'actions'
];

$orderBy = $columns[$orderColumn] ?? 'id';

// Get custom filters
$category = isset($_POST['category']) && $_POST['category'] !== '' ? $_POST['category'] : null;
$status = isset($_POST['status']) && $_POST['status'] !== '' ? $_POST['status'] : null;
$location = isset($_POST['location']) && $_POST['location'] !== '' ? $_POST['location'] : null;
$department = isset($_POST['department']) && $_POST['department'] !== '' ? $_POST['department'] : null;
$condition = isset($_POST['condition']) && $_POST['condition'] !== '' ? $_POST['condition'] : null;
$date_from = isset($_POST['date_from']) && $_POST['date_from'] !== '' ? $_POST['date_from'] : null;
$date_to = isset($_POST['date_to']) && $_POST['date_to'] !== '' ? $_POST['date_to'] : null;

// Build the base query
$baseQuery = "FROM items WHERE 1=1";
$countQuery = "SELECT COUNT(*) as total FROM items WHERE 1=1";

// Arrays for prepared statements
$whereConditions = [];
$params = [];
$types = "";

// Add search condition
if (!empty($search)) {
    // Log the search activity
    if (function_exists('logActivity')) {
        logActivity($conn, $_SESSION['user_id'] ?? 0, 'search', "Global search: $search");
    }

    $whereConditions[] = "(id LIKE ? OR item_name LIKE ? OR serial_number LIKE ? OR category LIKE ? OR status LIKE ? OR stock_location LIKE ?)";
    $searchTerm = "%$search%";
    $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm]);
    $types .= "ssssss";
}

// Add filters
if ($category) {
    $whereConditions[] = "category = ?";
    $params[] = $category;
    $types .= "s";
}
if ($status) {
    $whereConditions[] = "status = ?";
    $params[] = $status;
    $types .= "s";
}
if ($location) {
    $whereConditions[] = "stock_location = ?";
    $params[] = $location;
    $types .= "s";
}
if ($department) {
    $whereConditions[] = "department = ?";
    $params[] = $department;
    $types .= "s";
}
if ($condition) {
    $whereConditions[] = "`condition` = ?";
    $params[] = $condition;
    $types .= "s";
}
if ($date_from) {
    $whereConditions[] = "DATE(created_at) >= ?";
    $params[] = $date_from;
    $types .= "s";
}
if ($date_to) {
    $whereConditions[] = "DATE(created_at) <= ?";
    $params[] = $date_to;
    $types .= "s";
}

// Add where conditions to queries
if (!empty($whereConditions)) {
    $whereClause = " AND " . implode(" AND ", $whereConditions);
    $baseQuery .= $whereClause;
    $countQuery .= $whereClause;
}

// Get total records count
$stmt = $conn->prepare($countQuery);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$totalResult = $stmt->get_result();
$totalRecords = $totalResult->fetch_assoc()['total'];
$stmt->close();

// Get filtered records with pagination
$dataQuery = "SELECT 
                i.id,
                i.item_name,
                i.serial_number,
                i.category,
                i.status,
                i.stock_location,
                i.quantity,
                i.created_at,
                i.department,
                c.name as category_name,
                d.name as department_name
              FROM items i
              LEFT JOIN categories c ON i.category = c.id
              LEFT JOIN departments d ON i.department = d.id OR i.department = d.code
              WHERE 1=1 $whereClause 
              ORDER BY i.$orderBy $orderDir 
              LIMIT ? OFFSET ?";

// Add pagination parameters
$stmt = $conn->prepare($dataQuery);
if (!empty($params)) {
    // Add pagination params
    $params[] = $length;
    $params[] = $start;
    $types .= "ii";
    $stmt->bind_param($types, ...$params);
} else {
    $stmt->bind_param("ii", $length, $start);
}
$stmt->execute();
$result = $stmt->get_result();

// Fetch data and add row numbers
$data = [];
$rowNumber = $start + 1;
while ($row = $result->fetch_assoc()) {
    $row['row_number'] = $rowNumber++;
    $data[] = $row;
}
$stmt->close();

// Prepare response
$response = [
    'draw' => $draw,
    'recordsTotal' => $totalRecords,
    'recordsFiltered' => $totalRecords,
    'data' => $data
];

header('Content-Type: application/json');
echo json_encode($response);