<?php
// api/test_db.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');

// Get the absolute path to the root directory
$rootDir = dirname(dirname(__FILE__));

echo "Root dir: " . $rootDir . "\n";

// Try to include bootstrap
if (file_exists($rootDir . '/bootstrap.php')) {
    require_once $rootDir . '/bootstrap.php';
    echo "Bootstrap included successfully\n";
} else {
    echo "Bootstrap not found at: " . $rootDir . '/bootstrap.php' . "\n";
}

// Try to include db_connect
if (file_exists($rootDir . '/includes/db_connect.php')) {
    require_once $rootDir . '/includes/db_connect.php';
    echo "db_connect included successfully\n";
} else {
    echo "db_connect not found at: " . $rootDir . '/includes/db_connect.php' . "\n";
}

// Check if $pdo exists
if (isset($pdo)) {
    echo "PDO object exists\n";
    
    // Test query
    try {
        $stmt = $pdo->query("SELECT 1 as test");
        $result = $stmt->fetch();
        echo "Database query successful\n";
        
        echo json_encode([
            'success' => true,
            'message' => 'Database connected successfully',
            'pdo_exists' => isset($pdo),
            'test_result' => $result
        ]);
    } catch (Exception $e) {
        echo "Database query failed: " . $e->getMessage() . "\n";
        
        echo json_encode([
            'success' => false,
            'message' => 'Database query failed',
            'error' => $e->getMessage()
        ]);
    }
} else {
    echo "PDO object does not exist\n";
    
    echo json_encode([
        'success' => false,
        'message' => 'PDO not initialized',
        'files_checked' => [
            'bootstrap' => file_exists($rootDir . '/bootstrap.php'),
            'db_connect' => file_exists($rootDir . '/includes/db_connect.php')
        ]
    ]);
}
?>