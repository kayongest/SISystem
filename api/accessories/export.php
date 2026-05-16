<?php
// api/accessories/export.php
session_start();
require_once __DIR__ . '/../includes/database_fix.php';
require_once __DIR__ . '/../includes/functions.php';

if (!isLoggedIn()) {
    header('Location: ../login.php');
    exit();
}

try {
    $db = new DatabaseFix();
    $conn = $db->getConnection();
    
    // Get accessories with item assignments
    $sql = "
        SELECT 
            a.id,
            a.name as accessory_name,
            a.description as accessory_description,
            a.total_quantity,
            a.available_quantity,
            a.minimum_stock,
            a.created_at as accessory_created,
            a.updated_at as accessory_updated,
            COUNT(DISTINCT ia.item_id) as assigned_to_items,
            GROUP_CONCAT(DISTINCT i.item_name ORDER BY i.item_name SEPARATOR '; ') as assigned_item_names,
            GROUP_CONCAT(DISTINCT i.serial_number ORDER BY i.serial_number SEPARATOR '; ') as assigned_serial_numbers
        FROM accessories a
        LEFT JOIN item_accessories ia ON a.id = ia.accessory_id
        LEFT JOIN items i ON ia.item_id = i.id
        WHERE a.is_active = 1
        GROUP BY a.id
        ORDER BY a.name
    ";
    
    $result = $conn->query($sql);
    
    // Set headers for CSV download
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="accessories_export_' . date('Y-m-d_H-i-s') . '.csv"');
    
    // Create output stream
    $output = fopen('php://output', 'w');
    
    // Add UTF-8 BOM for Excel
    fwrite($output, "\xEF\xBB\xBF");
    
    // Write headers
    $headers = [
        'ID',
        'Accessory Name',
        'Description',
        'Total Quantity',
        'Available Quantity',
        'Minimum Stock Level',
        'Stock Status',
        'Assigned to Items',
        'Assigned Item Names',
        'Assigned Serial Numbers',
        'Created Date',
        'Last Updated'
    ];
    fputcsv($output, $headers);
    
    // Write data
    while ($row = $result->fetch_assoc()) {
        // Determine stock status
        if ($row['available_quantity'] == 0) {
            $stock_status = 'Out of Stock';
        } elseif ($row['available_quantity'] <= $row['minimum_stock']) {
            $stock_status = 'Low Stock';
        } else {
            $stock_status = 'In Stock';
        }
        
        $data = [
            $row['id'],
            $row['accessory_name'],
            $row['accessory_description'],
            $row['total_quantity'],
            $row['available_quantity'],
            $row['minimum_stock'],
            $stock_status,
            $row['assigned_to_items'],
            $row['assigned_item_names'] ?? '',
            $row['assigned_serial_numbers'] ?? '',
            $row['accessory_created'],
            $row['accessory_updated']
        ];
        
        fputcsv($output, $data);
    }
    
    fclose($output);
    $db->close();
    
} catch (Exception $e) {
    die("Export error: " . $e->getMessage());
}
?>