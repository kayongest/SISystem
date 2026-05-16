<?php
// ajax/get_batch_details.php - FIXED
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

// Debug info
$debug_info = [
    'batch_id' => $_GET['batch_id'] ?? 'not provided',
    'timestamp' => date('Y-m-d H:i:s')
];

try {
    // Database connection
    $conn = new mysqli('localhost', 'root', '', 'ability_db');
    if ($conn->connect_error) {
        throw new Exception("Database connection failed: " . $conn->connect_error);
    }

    // Get batch ID - could be numeric ID or batch_id string
    $batch_param = $conn->real_escape_string($_GET['batch_id'] ?? '');

    if (empty($batch_param)) {
        echo json_encode(['error' => 'Batch ID required', 'debug' => $debug_info]);
        exit;
    }

    // Check if batch_scans table exists
    $table_check = $conn->query("SHOW TABLES LIKE 'batch_scans'");
    if ($table_check->num_rows === 0) {
        throw new Exception("The 'batch_scans' table does not exist in the database.");
    }

    // Get batch details - search by both id and batch_id
    $sql = "
        SELECT 
            bs.*,
            u1.full_name as submitted_by_fullname,
            u1.username as submitted_by_username,
            u1.role as submitted_by_role,
            u3.full_name as approved_by_fullname,
            u3.username as approved_by_username,
            u3.role as approved_by_role
        FROM batch_scans bs
        LEFT JOIN users u1 ON bs.submitted_by = u1.id
        LEFT JOIN users u3 ON bs.approved_by = u3.id
        WHERE bs.id = '$batch_param' OR bs.batch_id = '$batch_param'
        LIMIT 1
    ";

    $result = $conn->query($sql);

    if (!$result) {
        throw new Exception("Query failed: " . $conn->error);
    }

    if ($result->num_rows === 0) {
        echo json_encode([
            'error' => 'Batch not found with ID: ' . htmlspecialchars($batch_param),
            'debug' => $debug_info
        ]);
        exit;
    }

    $batch = $result->fetch_assoc();

    // Get batch items
    $items = [];
    $items_table_check = $conn->query("SHOW TABLES LIKE 'batch_items'");

    if ($items_table_check->num_rows > 0) {
        // Use the batch_id string (e.g., 'BATCH-20260205153301-7fe34a80')
        $batch_id_str = $batch['batch_id'];
        $sql_items = "
            SELECT 
                bi.*,
                bi.item_name,
                bi.serial_number,
                bi.category,
                bi.new_status as status,
                bi.new_location as destination,
                bi.quantity
            FROM batch_items bi
            WHERE bi.batch_id = '$batch_id_str'
            ORDER BY bi.id
        ";

        $result_items = $conn->query($sql_items);

        if ($result_items) {
            while ($row = $result_items->fetch_assoc()) {
                $items[] = $row;
            }
        }
    }

    $conn->close();

    // Prepare response - map batch_scans columns to expected response format
    $response = [
        'id' => $batch['id'],
        'batch_id' => $batch['batch_id'],
        'batch_name' => $batch['batch_name'] ?? 'Batch ' . $batch['batch_id'],
        'submitted_by' => $batch['submitted_by'],
        'submitted_by_fullname' => $batch['submitted_by_fullname'] ?? 'System',
        'submitted_by_username' => $batch['submitted_by_username'] ?? 'system',
        'submitted_by_role' => $batch['submitted_by_role'] ?? 'stock_controller',
        'requested_by' => $batch['requested_by'],
        'requested_by_fullname' => $batch['technician_name'] ?? 'N/A', // Use technician_name from batch_scans
        'requested_by_name' => $batch['technician_name'] ?? 'N/A',
        'technician_name' => $batch['technician_name'] ?? 'N/A',
        'approved_by' => $batch['approved_by'],
        'approved_by_fullname' => $batch['approved_by_fullname'] ?? null,
        'approved_by_username' => $batch['approved_by_username'] ?? null,
        'submitted_at' => $batch['submitted_at'],
        'approved_at' => $batch['approved_at'],
        'approval_status' => $batch['approval_status'] ?? 'pending',
        'status' => $batch['status'] ?? 'completed',
        'action_applied' => $batch['action_applied'] ?? '',
        'location_applied' => $batch['location_applied'] ?? '',
        'notes' => $batch['notes'] ?? '',
        'total_items' => $batch['total_items'] ?? count($items),
        'unique_items' => $batch['unique_items'] ?? 0,
        'items' => $items,
        'debug' => array_merge($debug_info, [
            'batch_found' => 'yes',
            'items_count' => count($items)
        ])
    ];

    echo json_encode($response);
} catch (Exception $e) {
    echo json_encode([
        'error' => 'Database error',
        'message' => $e->getMessage(),
        'debug' => $debug_info
    ]);
}
