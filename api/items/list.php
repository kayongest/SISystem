<?php
// api/items/list.php - ENHANCED SEARCH VERSION
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../../config/database.php';

try {
    // Get parameters
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 5;
    $sort = isset($_GET['sort']) ? $_GET['sort'] : 'id';
    $order = isset($_GET['order']) ? $_GET['order'] : 'desc';
    $search = isset($_GET['search']) ? $_GET['search'] : '';
    $category = isset($_GET['category']) ? $_GET['category'] : '';
    $status = isset($_GET['status']) ? $_GET['status'] : '';
    $location = isset($_GET['location']) ? $_GET['location'] : '';
    $condition = isset($_GET['condition']) ? $_GET['condition'] : '';

    // Validate parameters
    $page = max(1, $page);
    $limit = max(1, min(100, $limit));
    $offset = ($page - 1) * $limit;

    // Allowed sort columns - UPDATED to match your database
    $allowedSort = ['id', 'item_name', 'category', 'quantity', 'status', 'created_at', 'stock_location'];
    $sort = in_array($sort, $allowedSort) ? $sort : 'id';
    $order = strtoupper($order) === 'ASC' ? 'ASC' : 'DESC';

    // Get database connection
    $db = getConnection();

    if (!$db) {
        throw new Exception('Database connection failed');
    }

    // Build WHERE clause - ENHANCED to search across all fields
    $whereConditions = [];
    $params = [];
    $types = "";

    // Handle search across multiple fields
    if (!empty($search)) {
        // Check if search is a number (could be ID)
        if (is_numeric($search)) {
            // If it's a number, also search in ID
            $whereConditions[] = "(i.id = ? OR i.item_name LIKE ? OR i.serial_number LIKE ? OR i.description LIKE ? OR i.brand LIKE ? OR i.model LIKE ? OR i.category LIKE ? OR i.status LIKE ? OR i.stock_location LIKE ?)";
            $searchTermNumber = (int)$search;
            $searchTermWildcard = "%$search%";
            $params[] = $searchTermNumber;
            $params[] = $searchTermWildcard;
            $params[] = $searchTermWildcard;
            $params[] = $searchTermWildcard;
            $params[] = $searchTermWildcard;
            $params[] = $searchTermWildcard;
            $params[] = $searchTermWildcard;
            $params[] = $searchTermWildcard;
            $params[] = $searchTermWildcard;
            $types .= "issssssss";
        } else {
            // Text search across all fields
            $whereConditions[] = "(i.item_name LIKE ? OR i.serial_number LIKE ? OR i.description LIKE ? OR i.brand LIKE ? OR i.model LIKE ? OR i.category LIKE ? OR i.status LIKE ? OR i.stock_location LIKE ?)";
            $searchTerm = "%$search%";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $types .= "ssssssss";
        }
    }

    // Category filter
    if (!empty($category)) {
        $whereConditions[] = "i.category = ?";
        $params[] = $category;
        $types .= "s";
    }

    // Status filter
    if (!empty($status)) {
        $whereConditions[] = "i.status = ?";
        $params[] = $status;
        $types .= "s";
    }

    // Location filter
    if (!empty($location)) {
        $whereConditions[] = "i.stock_location = ?";
        $params[] = $location;
        $types .= "s";
    }

    // Condition filter
    if (!empty($condition)) {
        $whereConditions[] = "i.`condition` = ?";
        $params[] = $condition;
        $types .= "s";
    }

    $whereClause = empty($whereConditions) ? "" : "WHERE " . implode(" AND ", $whereConditions);

    // Get total count
    $countQuery = "SELECT COUNT(*) as total FROM items i $whereClause";
    $countStmt = $db->prepare($countQuery);

    if (!empty($params)) {
        $countStmt->bind_param($types, ...$params);
    }

    $countStmt->execute();
    $countResult = $countStmt->get_result();
    $totalItems = $countResult->fetch_assoc()['total'];
    $totalPages = $totalItems > 0 ? ceil($totalItems / $limit) : 1;

    // Get items for current page - WITH JOINS for categories, departments, and brands
    $query = "
    SELECT 
        i.*,
        c.name as category_name,
        d.name as department_name,
        b.name as brand_name,
        b.code as brand_code
    FROM items i
    LEFT JOIN categories c ON i.category = c.id
    LEFT JOIN departments d ON i.department = d.id
    LEFT JOIN brands b ON i.brand = b.id
    $whereClause 
    ORDER BY $sort $order 
    LIMIT ? OFFSET ?
    ";

    $stmt = $db->prepare($query);

    // Add limit and offset to params
    $paramsWithLimit = $params;
    $typesWithLimit = $types;
    $paramsWithLimit[] = $limit;
    $paramsWithLimit[] = $offset;
    $typesWithLimit .= "ii";

    if (!empty($paramsWithLimit)) {
        $stmt->bind_param($typesWithLimit, ...$paramsWithLimit);
    }

    $stmt->execute();
    $result = $stmt->get_result();

    $items = [];
    while ($row = $result->fetch_assoc()) {
        // Keep original values
        $row['category_id'] = $row['category'];  // Keep the ID
        $row['department_id'] = $row['department']; // Keep the ID
        $row['brand_id'] = $row['brand']; // Keep the brand ID

        // Use the joined names with fallbacks
        $row['category_name'] = $row['category_name'] ?: 'Uncategorized';
        $row['department_name'] = $row['department_name'] ?: 'Not Set';
        $row['brand_name'] = $row['brand_name'] ?: ($row['brand'] ? 'Brand ' . $row['brand'] : 'N/A');

        // Clean up "undefined" strings
        $fieldsToCheck = ['description', 'specifications', 'notes', 'model', 'tags'];
        foreach ($fieldsToCheck as $field) {
            if (isset($row[$field]) && ($row[$field] === 'undefined' || $row[$field] === 'null')) {
                $row[$field] = '';
            }
        }

        // Fetch accessories for this item
        $accStmt = $db->prepare("
            SELECT a.name 
            FROM accessories a
            JOIN item_accessories ia ON a.id = ia.accessory_id
            WHERE ia.item_id = ?
        ");
        $accStmt->bind_param("i", $row['id']);
        $accStmt->execute();
        $accResult = $accStmt->get_result();

        $accessories = [];
        while ($acc = $accResult->fetch_assoc()) {
            $accessories[] = $acc['name'];
        }

        // Add accessories to the row data
        $row['accessories'] = $accessories;
        $items[] = $row;
    }

    // Return response
    echo json_encode([
        'success' => true,
        'items' => $items,
        'pagination' => [
            'currentPage' => $page,
            'totalPages' => $totalPages,
            'totalItems' => $totalItems,
            'itemsPerPage' => $limit,
            'hasNext' => $page < $totalPages,
            'hasPrev' => $page > 1
        ],
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
} catch (Exception $e) {
    error_log("Items API Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
