<?php
// api/scan/get_stats.php - UPDATED FOR YOUR TABLE STRUCTURE

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set JSON header
header('Content-Type: application/json');

// Try to include bootstrap from relative path
$bootstrapPath = __DIR__ . '/../../bootstrap.php';
if (!file_exists($bootstrapPath)) {
    echo json_encode([
        'success' => false,
        'message' => 'Bootstrap not found',
        'path' => $bootstrapPath
    ]);
    exit();
}

require_once $bootstrapPath;

// Check if user is logged in
if (!function_exists('isLoggedIn') || !isLoggedIn()) {
    echo json_encode([
        'success' => false, 
        'message' => 'Unauthorized access. Please login.'
    ]);
    exit();
}

// Try to get database connection
try {
    // Try to include db_connect
    $dbConnectPath = __DIR__ . '/../../includes/db_connect.php';
    if (!file_exists($dbConnectPath)) {
        echo json_encode([
            'success' => false,
            'message' => 'Database connection file not found'
        ]);
        exit();
    }
    
    require_once $dbConnectPath;
    
    // Get database instance
    if (!function_exists('getDatabase')) {
        echo json_encode([
            'success' => false,
            'message' => 'getDatabase function not found'
        ]);
        exit();
    }
    
    $db = getDatabase();
    if (!$db) {
        echo json_encode([
            'success' => false,
            'message' => 'Failed to get database instance'
        ]);
        exit();
    }
    
    $conn = $db->getConnection();
    if (!$conn) {
        echo json_encode([
            'success' => false,
            'message' => 'Failed to get database connection'
        ]);
        exit();
    }
    
    // Initialize all stats with 0
    $totalScans = 0;
    $todayScans = 0;
    $activeItems = 0;
    $uniqueUsers = 0;
    
    // Check if scan_logs table exists (YOUR ACTUAL TABLE NAME)
    $result = $conn->query("SHOW TABLES LIKE 'scan_logs'");
    if ($result && $result->num_rows > 0) {
        echo "<!-- Found scan_logs table -->\n";
        
        // Get total scans
        $result = $conn->query("SELECT COUNT(*) as total FROM scan_logs");
        if ($result) {
            $row = $result->fetch_assoc();
            $totalScans = (int)($row['total'] ?? 0);
            echo "<!-- Total scans: $totalScans -->\n";
        }
        
        // Get today's scans
        $result = $conn->query("SELECT COUNT(*) as today FROM scan_logs WHERE DATE(timestamp) = CURDATE()");
        if ($result) {
            $row = $result->fetch_assoc();
            $todayScans = (int)($row['today'] ?? 0);
            echo "<!-- Today's scans: $todayScans -->\n";
        }
        
        // Get unique users - check what user_id column is called in your table
        // First, let's check the structure of scan_logs table
        $result = $conn->query("DESCRIBE scan_logs");
        $hasUserId = false;
        $userIdColumn = 'user_id';
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                if ($row['Field'] == 'user_id' || $row['Field'] == 'scanned_by' || $row['Field'] == 'user') {
                    $hasUserId = true;
                    $userIdColumn = $row['Field'];
                    break;
                }
            }
        }
        
        if ($hasUserId) {
            $result = $conn->query("SELECT COUNT(DISTINCT $userIdColumn) as unique_users FROM scan_logs WHERE $userIdColumn IS NOT NULL");
            if ($result) {
                $row = $result->fetch_assoc();
                $uniqueUsers = (int)($row['unique_users'] ?? 0);
                echo "<!-- Unique users: $uniqueUsers (using column: $userIdColumn) -->\n";
            }
        } else {
            echo "<!-- No user_id column found in scan_logs table -->\n";
            $uniqueUsers = 0;
        }
    } else {
        echo "<!-- scan_logs table not found -->\n";
    }
    
    // Check if items table exists
    $result = $conn->query("SHOW TABLES LIKE 'items'");
    if ($result && $result->num_rows > 0) {
        echo "<!-- Found items table -->\n";
        
        // Get active items - based on your table structure
        $result = $conn->query("SELECT COUNT(*) as active FROM items WHERE status IN ('available', 'good', 'active', 'in_stock') OR status IS NULL");
        if ($result) {
            $row = $result->fetch_assoc();
            $activeItems = (int)($row['active'] ?? 0);
            echo "<!-- Active items: $activeItems -->\n";
        }
    } else {
        echo "<!-- items table not found -->\n";
    }
    
    // Clear any output before JSON
    if (ob_get_length()) {
        ob_clean();
    }
    
    // Return success
    echo json_encode([
        'success' => true,
        'data' => [
            'total_scans' => $totalScans,
            'today_scans' => $todayScans,
            'active_items' => $activeItems,
            'unique_users' => $uniqueUsers
        ],
        'debug' => [
            'table_scan_logs_exists' => true,
            'table_items_exists' => true
        ]
    ]);
    
} catch (Exception $e) {
    // Clear output buffer
    if (ob_get_length()) {
        ob_clean();
    }
    
    // Return error
    echo json_encode([
        'success' => false,
        'message' => 'Database error occurred',
        'error' => $e->getMessage()
    ]);
}
exit();
?>