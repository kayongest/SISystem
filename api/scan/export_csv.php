<?php
// api/scan/export_csv.php
require_once __DIR__ . '/../../includes/db_connect.php';
require_once __DIR__ . '/../../includes/functions.php';

// Check authentication
session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    exit('Unauthorized');
}

// Get filter parameters
$dateFrom = $_GET['dateFrom'] ?? '';
$dateTo = $_GET['dateTo'] ?? '';
$scanType = $_GET['scanType'] ?? '';
$scanMethod = $_GET['scanMethod'] ?? '';
$userId = $_GET['userId'] ?? '';

try {
    // Build query
    $sql = "SELECT 
                sl.id as 'Scan ID',
                sl.scan_timestamp as 'Timestamp',
                sl.scan_type as 'Type',
                sl.scan_method as 'Method',
                sl.location as 'Location',
                sl.notes as 'Notes',
                i.item_name as 'Item Name',
                i.serial_number as 'Serial Number',
                i.category as 'Category',
                i.status as 'Item Status',
                u.username as 'Username',
                u.full_name as 'Full Name',
                sl.created_at as 'Created At'
            FROM scan_logs sl
            LEFT JOIN items i ON sl.item_id = i.id
            LEFT JOIN users u ON sl.user_id = u.id
            WHERE 1=1";

    $params = [];

    if (!empty($dateFrom)) {
        $sql .= " AND DATE(sl.scan_timestamp) >= ?";
        $params[] = $dateFrom;
    }
    
    if (!empty($dateTo)) {
        $sql .= " AND DATE(sl.scan_timestamp) <= ?";
        $params[] = $dateTo;
    }
    
    if (!empty($scanType)) {
        $sql .= " AND sl.scan_type = ?";
        $params[] = $scanType;
    }
    
    if (!empty($scanMethod)) {
        $sql .= " AND sl.scan_method = ?";
        $params[] = $scanMethod;
    }
    
    if (!empty($userId)) {
        $sql .= " AND sl.user_id = ?";
        $params[] = $userId;
    }

    $sql .= " ORDER BY sl.scan_timestamp DESC";

    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Set headers for CSV download
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=scan_logs_' . date('Y-m-d_H-i-s') . '.csv');

    // Create output stream
    $output = fopen('php://output', 'w');

    // Add BOM for Excel compatibility
    fwrite($output, "\xEF\xBB\xBF");

    // Add headers
    if (!empty($data)) {
        fputcsv($output, array_keys($data[0]));
    }

    // Add data rows
    foreach ($data as $row) {
        fputcsv($output, $row);
    }

    fclose($output);

} catch (Exception $e) {
    http_response_code(500);
    echo 'Error generating CSV: ' . $e->getMessage();
}