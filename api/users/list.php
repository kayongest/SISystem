<?php
// api/users/list.php - Get users list with filtering
header('Content-Type: application/json');
require_once '../../config/database.php';

try {
    $conn = getConnection();

    // Check permissions
    $currentUserRole = $_SESSION['role'] ?? 'user';
    if ($currentUserRole !== 'admin' && $currentUserRole !== 'manager') {
        echo json_encode(['success' => false, 'message' => 'Access denied']);
        exit();
    }

    $where = [];
    $params = [];
    $types = '';

    // Build filters
    if (!empty($_GET['search'])) {
        $search = '%' . $_GET['search'] . '%';
        $where[] = "(username LIKE ? OR email LIKE ?)";
        $params[] = $search;
        $params[] = $search;
        $types .= 'ss';
    }

    if (!empty($_GET['role'])) {
        $where[] = "role = ?";
        $params[] = $_GET['role'];
        $types .= 's';
    }

    if (!empty($_GET['department'])) {
        $where[] = "department = ?";
        $params[] = $_GET['department'];
        $types .= 's';
    }

    if (isset($_GET['status']) && $_GET['status'] !== '') {
        $where[] = "is_active = ?";
        $params[] = (int)$_GET['status'];
        $types .= 'i';
    }

    // Department restriction for managers
    if ($currentUserRole === 'manager') {
        $currentUserDept = $_SESSION['department'] ?? '';
        if (!empty($currentUserDept)) {
            $where[] = "department = ?";
            $params[] = $currentUserDept;
            $types .= 's';
        }
    }

    $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

    // Get total count
    $countSql = "SELECT COUNT(*) as total FROM users $whereClause";
    $countStmt = $conn->prepare($countSql);
    if (!empty($params)) {
        $countStmt->bind_param($types, ...$params);
    }
    $countStmt->execute();
    $countResult = $countStmt->get_result();
    $total = $countResult->fetch_assoc()['total'];
    $countStmt->close();

    // Get users with pagination
    $page = (int)($_GET['page'] ?? 1);
    $limit = (int)($_GET['limit'] ?? 50);
    $offset = ($page - 1) * $limit;

    $sql = "SELECT id, username, email, role, department, is_active, created_at
            FROM users $whereClause ORDER BY created_at DESC LIMIT ? OFFSET ?";

    $stmt = $conn->prepare($sql);
    $allParams = array_merge($params, [$limit, $offset]);
    $allTypes = $types . 'ii';
    $stmt->bind_param($allTypes, ...$allParams);
    $stmt->execute();
    $result = $stmt->get_result();

    $users = [];
    while ($row = $result->fetch_assoc()) {
        $users[] = $row;
    }

    echo json_encode([
        'success' => true,
        'users' => $users,
        'total' => $total,
        'page' => $page,
        'limit' => $limit,
        'pages' => ceil($total / $limit)
    ]);

    $stmt->close();
    $conn->close();

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
