<?php
// api/items/export_with_accessories.php
session_start();
require_once __DIR__ . '/../../includes/database_fix.php';
require_once __DIR__ . '/../../includes/functions.php';

if (!isLoggedIn()) {
    header('Location: ../../login.php');
    exit();
}

try {
    $db = new DatabaseFix();
    $conn = $db->getConnection();

    // Get items with accessories
    $sql = "
        SELECT 
            i.id,
            i.item_name,
            i.serial_number,
            i.category,
            i.department,
            i.brand,
            i.model,
            i.description,
            i.`condition`,
            i.stock_location,
            i.quantity,
            i.status,
            i.notes,
            i.created_at,
            i.updated_at,
            GROUP_CONCAT(DISTINCT a.name ORDER BY a.name SEPARATOR '; ') as accessories,
            GROUP_CONCAT(DISTINCT a.id ORDER BY a.id SEPARATOR ', ') as accessory_ids
        FROM items i
        LEFT JOIN item_accessories ia ON i.id = ia.item_id
        LEFT JOIN accessories a ON ia.accessory_id = a.id AND a.is_active = 1
        GROUP BY i.id
        ORDER BY i.item_name
    ";

    $result = $conn->query($sql);

    // Set headers for CSV download
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="equipment_with_accessories_export_' . date('Y-m-d_H-i-s') . '.csv"');

    // Create output stream
    $output = fopen('php://output', 'w');

    // Add UTF-8 BOM for Excel
    fwrite($output, "\xEF\xBB\xBF");

    // Write headers
    $headers = [
        'ID',
        'Item Name',
        'Serial Number',
        'Category',
        'Department',
        'Brand',
        'Model',
        'Description',
        'Condition',
        'Location',
        'Quantity',
        'Status',
        'Accessories',
        'Accessory IDs',
        'Notes',
        'Created Date',
        'Last Updated'
    ];
    fputcsv($output, $headers);

    // Write data
    while ($row = $result->fetch_assoc()) {
        $data = [
            $row['id'],
            $row['item_name'],
            $row['serial_number'],
            $row['category'],
            $row['department'],
            $row['brand'] ?? '',
            $row['model'] ?? '',
            $row['description'] ?? '',
            $row['condition'],
            $row['stock_location'] ?? '',
            $row['quantity'],
            $row['status'],
            $row['accessories'] ?? '',
            $row['accessory_ids'] ?? '',
            $row['notes'] ?? '',
            $row['created_at'],
            $row['updated_at']
        ];

        fputcsv($output, $data);
    }

    fclose($output);
    $db->close();
} catch (Exception $e) {
    die("Export error: " . $e->getMessage());
}
