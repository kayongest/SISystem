<?php
// api/get_status_chart_data.php
require_once '../includes/database_fix.php';
require_once '../includes/functions.php';

// Start session if not started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check authentication
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// Database connection
try {
    $db = new DatabaseFix();
    $conn = $db->getConnection();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit();
}

// Get equipment status distribution
$statusData = [];
$totalItems = 0;

try {
    // Get total count first
    $totalQuery = $conn->query("SELECT COUNT(*) as total FROM items");
    if ($totalQuery) {
        $totalRow = $totalQuery->fetch_assoc();
        $totalItems = $totalRow['total'] ?? 0;
    }

    // Get status distribution
    $statusQuery = $conn->query("
        SELECT 
            status,
            COUNT(*) as count,
            ROUND(COUNT(*) * 100.0 / GREATEST((SELECT COUNT(*) FROM items), 1), 1) as percentage
        FROM items 
        WHERE status IS NOT NULL
        GROUP BY status 
        ORDER BY 
            FIELD(status, 'available', 'in_use', 'maintenance', 'reserved', 'disposed', 'lost')
    ");
    
    if ($statusQuery) {
        $statusData = $statusQuery->fetch_all(MYSQLI_ASSOC);
    }
} catch (Exception $e) {
    error_log("Error getting status data: " . $e->getMessage());
}

// Prepare response data
$responseData = [
    'labels' => [],
    'counts' => [],
    'colors' => [],
    'percentages' => [],
    'total' => $totalItems,
    'table_html' => ''
];

foreach ($statusData as $status) {
    $responseData['labels'][] = ucfirst($status['status']);
    $responseData['counts'][] = (int)$status['count'];
    $responseData['percentages'][] = (float)$status['percentage'];
    
    // Assign colors
    switch (strtolower($status['status'])) {
        case 'available':
            $responseData['colors'][] = 'rgba(75, 192, 192, 0.8)';
            break;
        case 'in_use':
            $responseData['colors'][] = 'rgba(54, 162, 235, 0.8)';
            break;
        case 'maintenance':
            $responseData['colors'][] = 'rgba(255, 206, 86, 0.8)';
            break;
        case 'reserved':
            $responseData['colors'][] = 'rgba(153, 102, 255, 0.8)';
            break;
        case 'disposed':
            $responseData['colors'][] = 'rgba(255, 99, 132, 0.8)';
            break;
        case 'lost':
            $responseData['colors'][] = 'rgba(128, 128, 128, 0.8)';
            break;
        default:
            $responseData['colors'][] = 'rgba(201, 203, 207, 0.8)';
    }
}

// Generate table HTML
if (!empty($statusData)) {
    $tableHtml = '';
    foreach ($statusData as $status) {
        $tableHtml .= '<tr>';
        $tableHtml .= '<td>';
        $tableHtml .= '<span class="status-badge status-' . strtolower($status['status']) . '">';
        $tableHtml .= '<i class="fas fa-circle me-1"></i>';
        $tableHtml .= ucfirst($status['status']);
        $tableHtml .= '</span>';
        $tableHtml .= '</td>';
        $tableHtml .= '<td class="text-end fw-bold">' . $status['count'] . '</td>';
        $tableHtml .= '<td class="text-end">' . $status['percentage'] . '%</td>';
        $tableHtml .= '</tr>';
    }
    $responseData['table_html'] = $tableHtml;
} else {
    $responseData['table_html'] = '
        <tr>
            <td colspan="3" class="text-center text-muted">
                <i class="fas fa-chart-pie fa-2x mb-2 d-block"></i>
                No status data available
            </td>
        </tr>
    ';
}

// If no data, provide empty defaults
if (empty($statusData)) {
    $responseData['labels'] = ['No Data'];
    $responseData['counts'] = [1];
    $responseData['colors'] = ['rgba(201, 203, 207, 0.8)'];
    $responseData['percentages'] = [100];
}

header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'data' => $responseData,
    'timestamp' => date('Y-m-d H:i:s')
]);

$db->close();
?>