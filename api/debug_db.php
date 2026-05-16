<?php
// api/debug_db.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');

// Get the absolute path to the root directory
$rootDir = dirname(dirname(dirname(__FILE__)));

// Include the bootstrap file
require_once $rootDir . '/bootstrap.php';
require_once $rootDir . '/includes/db_connect.php';

// Get database connection
$db = getDatabase();
$conn = $db->getConnection();

$debugData = [];

try {
    // 1. Check if tables exist
    $tables = ['scans', 'items', 'users'];
    foreach ($tables as $table) {
        $result = $conn->query("SHOW TABLES LIKE '$table'");
        $debugData['tables'][$table] = $result->num_rows > 0 ? 'EXISTS' : 'MISSING';
        
        if ($result->num_rows > 0) {
            // Count records
            $countResult = $conn->query("SELECT COUNT(*) as count FROM $table");
            $countRow = $countResult->fetch_assoc();
            $debugData['table_counts'][$table] = $countRow['count'];
            
            // Show columns
            $columnsResult = $conn->query("DESCRIBE $table");
            $debugData['table_columns'][$table] = [];
            while ($col = $columnsResult->fetch_assoc()) {
                $debugData['table_columns'][$table][] = $col['Field'] . ' (' . $col['Type'] . ')';
            }
        }
    }
    
    // 2. Test the exact query from get_all.php
    $debugData['query_test'] = [];
    $sql = "SELECT 
                s.id,
                s.scan_timestamp,
                s.scan_type,
                s.scan_method,
                s.location,
                s.notes,
                s.user_id,
                s.item_id,
                s.scanned_data,
                i.item_name,
                i.serial_number,
                i.category,
                i.status as item_status,
                u.username,
                u.full_name
            FROM scans s
            LEFT JOIN items i ON s.item_id = i.id
            LEFT JOIN users u ON s.user_id = u.id
            ORDER BY s.scan_timestamp DESC
            LIMIT 10";
    
    $result = $conn->query($sql);
    $debugData['query_execution'] = $result ? 'SUCCESS' : 'FAILED: ' . $conn->error;
    
    if ($result) {
        $debugData['query_row_count'] = $result->num_rows;
        $debugData['query_results'] = [];
        while ($row = $result->fetch_assoc()) {
            $debugData['query_results'][] = $row;
        }
    }
    
    // 3. Check if there are any scans at all (simple count)
    $result = $conn->query("SELECT COUNT(*) as scan_count FROM scans");
    $row = $result->fetch_assoc();
    $debugData['total_scans'] = $row['scan_count'];
    
    // 4. Check last few scans without JOIN
    $result = $conn->query("SELECT * FROM scans ORDER BY id DESC LIMIT 5");
    $debugData['raw_scans'] = [];
    while ($row = $result->fetch_assoc()) {
        $debugData['raw_scans'][] = $row;
    }
    
    // 5. Check items table
    $result = $conn->query("SELECT id, item_name, serial_number FROM items LIMIT 5");
    $debugData['sample_items'] = [];
    while ($row = $result->fetch_assoc()) {
        $debugData['sample_items'][] = $row;
    }
    
    // 6. Check users table
    $result = $conn->query("SELECT id, username, full_name FROM users LIMIT 5");
    $debugData['sample_users'] = [];
    while ($row = $result->fetch_assoc()) {
        $debugData['sample_users'][] = $row;
    }
    
    echo json_encode([
        'success' => true,
        'debug' => $debugData
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'debug' => $debugData
    ]);
}
?>