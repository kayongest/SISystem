<?php
// export_reports.php
require_once 'bootstrap.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit();
}

require_once 'includes/db_connect.php';

function getDBConnection()
{
    static $pdo = null;

    if ($pdo === null) {
        try {
            $host = 'localhost';
            $dbname = 'ability_db';
            $username = 'root';
            $password = '';

            $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            die("Connection failed: " . $e->getMessage());
        }
    }

    return $pdo;
}

$pdo = getDBConnection();

$report_type = $_GET['report_type'] ?? 'inventory';
$start_date = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
$end_date = $_GET['end_date'] ?? date('Y-m-d');

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="report_' . $report_type . '_' . date('Y-m-d') . '.csv"');

$output = fopen('php://output', 'w');

if ($report_type === 'inventory') {
    fputcsv($output, ['Item ID', 'Item Name', 'Serial Number', 'Category', 'Department', 'Condition', 'Status']);
    
    $stmt = $pdo->query("SELECT i.id, i.item_name, i.serial_number, 
                         COALESCE(c.name, i.category) as category_name, 
                         COALESCE(d.name, i.department) as department_name, 
                         i.`condition`, i.status 
                         FROM items i 
                         LEFT JOIN categories c ON i.category = CAST(c.id AS CHAR) OR i.category = c.name
                         LEFT JOIN departments d ON COALESCE(NULLIF(i.department, ''), i.category) = CAST(d.id AS CHAR) OR COALESCE(NULLIF(i.department, ''), i.category) = d.code OR COALESCE(NULLIF(i.department, ''), i.category) = d.name");
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($output, $row);
    }
} elseif ($report_type === 'movements') {
    fputcsv($output, ['Batch ID', 'Type', 'Status', 'Technician', 'Created At']);
    
    $stmt = $pdo->prepare("SELECT b.id, b.type, b.status, u.username, b.created_at 
                           FROM batches b 
                           LEFT JOIN users u ON b.created_by = u.id 
                           WHERE b.created_at BETWEEN :start AND :end");
    $stmt->execute([':start' => $start_date . ' 00:00:00', ':end' => $end_date . ' 23:59:59']);
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($output, $row);
    }
} elseif ($report_type === 'performance') {
    fputcsv($output, ['Technician', 'Total Movements']);
    
    $stmt = $pdo->query("SELECT u.username, COUNT(sm.id) as total_movements 
                         FROM stock_movements sm 
                         JOIN users u ON sm.technician_id = u.id 
                         GROUP BY sm.technician_id");
                         
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($output, $row);
    }
} elseif ($report_type === 'utilization') {
    fputcsv($output, ['Item ID', 'Item Name', 'Serial Number', 'Current Status', 'Utilization Count (Selected Period)']);
    
    $stmt = $pdo->prepare("SELECT i.id, i.item_name, i.serial_number, i.status, COUNT(bi.id) as utilization_count 
                           FROM items i
                           LEFT JOIN batch_items bi ON i.id = bi.item_id AND bi.created_at BETWEEN :start AND :end
                           WHERE i.status NOT IN ('retired', 'damaged', 'lost')
                           GROUP BY i.id
                           ORDER BY utilization_count DESC");
    $stmt->execute([':start' => $start_date . ' 00:00:00', ':end' => $end_date . ' 23:59:59']);
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($output, $row);
    }
} elseif ($report_type === 'locations') {
    fputcsv($output, ['Destination Name', 'Movement Count (Selected Period)']);
    
    $stmt = $pdo->prepare("SELECT destination_name, COUNT(*) as movement_count 
                           FROM stock_movements 
                           WHERE destination_name IS NOT NULL AND destination_name != '' AND created_at BETWEEN :start AND :end
                           GROUP BY destination_name
                           ORDER BY movement_count DESC");
    $stmt->execute([':start' => $start_date . ' 00:00:00', ':end' => $end_date . ' 23:59:59']);
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($output, $row);
    }
} elseif ($report_type === 'logistics') {
    fputcsv($output, ['Transport Driver', 'Total Deliveries', 'Top Vehicle Used']);
    
    $stmt = $pdo->prepare("SELECT transport_driver, COUNT(*) as delivery_count, MAX(transport_vehicle_number) as top_vehicle 
                           FROM stock_movements 
                           WHERE transport_driver IS NOT NULL AND transport_driver != '' AND created_at BETWEEN :start AND :end
                           GROUP BY transport_driver
                           ORDER BY delivery_count DESC");
    $stmt->execute([':start' => $start_date . ' 00:00:00', ':end' => $end_date . ' 23:59:59']);
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($output, $row);
    }
} elseif ($report_type === 'alerts') {
    fputcsv($output, ['Item ID', 'Item Name', 'Serial Number', 'Location', 'With User', 'Expected Return', 'Days Late']);
    
    $stmt = $pdo->prepare("SELECT i.id, i.item_name, i.serial_number, s.expected_return, s.to_location, s.transport_user 
                           FROM items i 
                           JOIN scan_logs s ON i.id = s.item_id 
                           WHERE i.status = 'in_use' AND s.scan_type = 'check_out' 
                           AND s.expected_return IS NOT NULL AND s.expected_return < NOW() 
                           AND s.id = (SELECT MAX(id) FROM scan_logs WHERE item_id = i.id)
                           ORDER BY s.expected_return ASC");
    $stmt->execute();
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $expected = new DateTime($row['expected_return']);
        $now = new DateTime();
        $diff = $now->diff($expected);
        $row['Days Late'] = $diff->days;
        
        fputcsv($output, [
            $row['id'], 
            $row['item_name'], 
            $row['serial_number'], 
            $row['to_location'], 
            $row['transport_user'], 
            $row['expected_return'], 
            $row['Days Late']
        ]);
    }
} elseif ($report_type === 'maintenance_report') {
    fputcsv($output, ['Item ID', 'Item Name', 'Serial Number', 'Category', 'Condition', 'Total Maintenance Scans']);
    
    $stmt = $pdo->prepare("SELECT i.id, i.item_name, i.serial_number, i.category, i.condition, COUNT(s.id) as repair_count 
                           FROM items i 
                           JOIN scan_logs s ON i.id = s.item_id 
                           WHERE s.scan_type = 'maintenance' AND s.scan_date BETWEEN :start_date AND :end_date
                           GROUP BY i.id, i.item_name, i.serial_number, i.category, i.condition
                           ORDER BY repair_count DESC LIMIT 50");
    $stmt->execute([':start_date' => $start_date . ' 00:00:00', ':end_date' => $end_date . ' 23:59:59']);
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($output, $row);
    }
} else { // summary (comprehensive data dump for analysis)
    fputcsv($output, ['Item ID', 'Item Name', 'Serial Number', 'Category', 'Department', 'Condition', 'Status', 'Total Movements']);
    
    $stmt = $pdo->query("
        SELECT 
            i.id, 
            i.item_name, 
            i.serial_number, 
            COALESCE(c.name, i.category) as category_name, 
            COALESCE(d.name, i.department) as department_name, 
            i.`condition`, 
            i.status,
            (SELECT COUNT(*) FROM batch_items bi WHERE bi.item_id = i.id) as total_movements
        FROM items i 
        LEFT JOIN categories c ON i.category = CAST(c.id AS CHAR) OR i.category = c.name
        LEFT JOIN departments d ON COALESCE(NULLIF(i.department, ''), i.category) = CAST(d.id AS CHAR) OR COALESCE(NULLIF(i.department, ''), i.category) = d.code OR COALESCE(NULLIF(i.department, ''), i.category) = d.name
    ");
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($output, $row);
    }
}

fclose($output);
exit();
?>
