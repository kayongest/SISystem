<?php
// api/scan/get_all.php
require_once __DIR__ . '/../../includes/db_connect.php';
require_once __DIR__ . '/../../includes/functions.php';

header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

try {
    // Build the SQL query - updated to match your table structure
    $sql = "
        SELECT 
            s.*,
            i.item_name,
            i.serial_number,
            i.category,
            i.status as item_status,
            u.username,
            u.email,
            u.department as user_department
        FROM scans s
        LEFT JOIN items i ON s.item_id = i.id
        LEFT JOIN users u ON s.user_id = u.id
        ORDER BY s.scan_timestamp DESC
    ";

    $stmt = $pdo->query($sql);
    $scans = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Log for debugging
    error_log("Fetched " . count($scans) . " scans from database");

    // Return the data in proper DataTables format
    echo json_encode([
        'success' => true,
        'data' => $scans,
        'recordsTotal' => count($scans),
        'recordsFiltered' => count($scans)
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
} catch (PDOException $e) {
    error_log("Database error in get_all.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ], JSON_PRETTY_PRINT);
} catch (Exception $e) {
    error_log("General error in get_all.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ], JSON_PRETTY_PRINT);
}
