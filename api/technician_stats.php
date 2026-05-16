<?php
// api/technician_stats.php
require_once '../bootstrap.php';

// Check authentication
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

// Include database connection
require_once '../includes/database_fix.php';

try {
    $db = new DatabaseFix();
    $conn = $db->getConnection();

    // Get technician ID
    $techId = (int)($_GET['id'] ?? 0);

    if (empty($techId)) {
        echo json_encode(['success' => false, 'error' => 'Technician ID required']);
        exit();
    }

    // Get activity count
    $activityStmt = $conn->prepare("
        SELECT COUNT(*) as activity_count 
        FROM activity_log 
        WHERE user_id = ? AND action LIKE '%technician%'
    ");
    $activityStmt->bind_param("i", $techId);
    $activityStmt->execute();
    $activityResult = $activityStmt->get_result();
    $activityData = $activityResult->fetch_assoc();
    $activityStmt->close();

    // Get items assigned count (if you have an items table with assigned_to field)
    $itemsStmt = $conn->prepare("
        SELECT COUNT(*) as items_count 
        FROM items 
        WHERE assigned_to = ? AND status != 'disposed'
    ");
    $itemsStmt->bind_param("i", $techId);
    $itemsStmt->execute();
    $itemsResult = $itemsStmt->get_result();
    $itemsData = $itemsResult->fetch_assoc();
    $itemsStmt->close();

    // Get user details including last login
    $techStmt = $conn->prepare("
        SELECT last_login, created_at 
        FROM users 
        WHERE id = ?
    ");
    $techStmt->bind_param("i", $techId);
    $techStmt->execute();
    $techResult = $techStmt->get_result();
    $techData = $techResult->fetch_assoc();
    $techStmt->close();

    echo json_encode([
        'success' => true,
        'activity_count' => $activityData['activity_count'] ?? 0,
        'items_count' => $itemsData['items_count'] ?? 0,
        'last_login' => $techData['last_login'] ?? null,
        'created_at' => $techData['created_at'] ?? null
    ]);

    $conn->close();

} catch (Exception $e) {
    error_log("Technician stats error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Server error occurred']);
}
?>