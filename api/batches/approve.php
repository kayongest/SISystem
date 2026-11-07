<?php
error_reporting(0);
ini_set('display_errors', 0);

header('Content-Type: application/json');
require_once __DIR__ . '/../../config.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../includes/db_connect.php';
$conn = getConnection();

if (!$conn) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit();
}

// Check authentication
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    $conn->close();
    exit();
}

$userId = (int)($_SESSION['user_id'] ?? 0);
$userRole = $_SESSION['role'] ?? ($_SESSION['user_role'] ?? '');
$userLoginName = trim($_SESSION['username'] ?? '');
$userFullName = trim($_SESSION['full_name'] ?? '');
$userName = !empty($userFullName) ? $userFullName : (!empty($userLoginName) ? $userLoginName : 'Approver');
$isAdmin = in_array($userRole, ['admin', 'superadmin']);
$isStockController = ($userRole === 'stock_controller' || strtolower($userLoginName) === 'mirene');

if (!$isAdmin && !$isStockController) {
    echo json_encode(['success' => false, 'message' => 'Access denied. Only Admins and Stock Controllers can manage batch approvals.']);
    $conn->close();
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);
$batch_id = $input['batch_id'] ?? '';
$action = $input['action'] ?? '';
$notes = trim($input['notes'] ?? '');

if (empty($batch_id)) {
    echo json_encode(['success' => false, 'message' => 'Batch ID required']);
    $conn->close();
    exit();
}

if (!in_array($action, ['approve', 'reject', 'complete', 'reset_pending'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
    $conn->close();
    exit();
}

// Check if batch exists
$checkSql = "SELECT id, batch_number, status, approval_status, stock_controller_id, stock_controller_name, movement_type, source_name, destination_name, destination_room, transport_driver FROM stock_movements WHERE batch_number = ?";
$checkStmt = $conn->prepare($checkSql);
$checkStmt->bind_param("s", $batch_id);
$checkStmt->execute();
$result = $checkStmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Batch not found']);
    $checkStmt->close();
    $conn->close();
    exit();
}

$batch = $result->fetch_assoc();
$checkStmt->close();

// Role-Based Restriction:
// Admins can manage ANY batch.
// Stock controllers can only manage batches where they were the selected stock controller.
if (!$isAdmin) {
    $isAssigned = false;
    if ($userId > 0 && !empty($batch['stock_controller_id']) && (int)$batch['stock_controller_id'] === (int)$userId) {
        $isAssigned = true;
    }
    if (!empty($userLoginName) && !empty($batch['stock_controller_name'])) {
        if (strcasecmp($batch['stock_controller_name'], $userLoginName) === 0 || stripos($batch['stock_controller_name'], $userLoginName . ' (') === 0) {
            $isAssigned = true;
        }
    }
    if (!empty($userFullName) && !empty($batch['stock_controller_name'])) {
        if (strcasecmp($batch['stock_controller_name'], $userFullName) === 0 || stripos($batch['stock_controller_name'], $userFullName . ' (') === 0) {
            $isAssigned = true;
        }
    }

    if (!$isAssigned) {
        echo json_encode(['success' => false, 'message' => 'Access denied. You can only manage batches where you are the selected stock controller.']);
        $conn->close();
        exit();
    }
}

$currentStatus = $batch['status'] ?? $batch['approval_status'];

if ($action === 'complete') {
    if ($currentStatus === 'completed') {
        echo json_encode(['success' => false, 'message' => 'Batch is already completed']);
        $conn->close();
        exit();
    }
} elseif ($action === 'reset_pending') {
    if (!$isAdmin) {
        echo json_encode(['success' => false, 'message' => 'Only administrators can reset a batch to pending']);
        $conn->close();
        exit();
    }
} else {
    // Action approve or reject
    if ($currentStatus !== 'pending') {
        echo json_encode(['success' => false, 'message' => 'Batch is already ' . $currentStatus]);
        $conn->close();
        exit();
    }
}

$batchDbId = $batch['id'];

// Determine if transport with a driver is required
$hasTransportDriver = in_array($batch['movement_type'], ['transport', 'stock_to_venue_transport', 'stock_to_stock']) 
                      && !empty($batch['transport_driver']) 
                      && $batch['transport_driver'] !== '-- Select Driver --';

if ($action === 'approve') {
    $finalStatus = $hasTransportDriver ? 'approved' : 'completed';
    $finalApprovalStatus = 'approved';

    $updateSql = "UPDATE stock_movements 
                  SET status = ?, 
                      approval_status = ?,
                      approved_by_id = ?, 
                      approved_by_name = ?,
                      approved_at = NOW(),
                      approval_notes = ?
                  WHERE id = ?";
    $stmt = $conn->prepare($updateSql);
    $stmt->bind_param("ssissi", $finalStatus, $finalApprovalStatus, $userId, $userName, $notes, $batchDbId);
    $execOk = $stmt->execute();
    $stmt->close();

    if ($execOk) {
        $itemStatusVal = $finalStatus;
        $biStmt = $conn->prepare("UPDATE batch_items SET status = ?, item_status = ? WHERE batch_id = ?");
        if ($biStmt) {
            $biStmt->bind_param("ssi", $itemStatusVal, $itemStatusVal, $batchDbId);
            $biStmt->execute();
            $biStmt->close();
        }

        $destWithRoom = $batch['destination_name'] . (!empty($batch['destination_room']) ? ' - ' . $batch['destination_room'] : '');

        if ($hasTransportDriver) {
            $uItems = $conn->prepare("UPDATE items SET status = 'in_transit', updated_at = NOW() WHERE id IN (SELECT item_id FROM batch_items WHERE batch_id = ? AND item_id IS NOT NULL)");
            if ($uItems) {
                $uItems->bind_param("i", $batchDbId);
                $uItems->execute();
                $uItems->close();
            }
        } else {
            if (in_array($batch['movement_type'], ['stock_to_venue_room', 'stock_to_venue_transport'])) {
                $uItems = $conn->prepare("UPDATE items SET status = 'in_use', current_location = ?, updated_at = NOW() WHERE id IN (SELECT item_id FROM batch_items WHERE batch_id = ? AND item_id IS NOT NULL)");
                if ($uItems) {
                    $uItems->bind_param("si", $destWithRoom, $batchDbId);
                    $uItems->execute();
                    $uItems->close();
                }
            } else {
                $uItems = $conn->prepare("UPDATE items SET status = 'available', current_location = ?, stock_location = ?, updated_at = NOW() WHERE id IN (SELECT item_id FROM batch_items WHERE batch_id = ? AND item_id IS NOT NULL)");
                if ($uItems) {
                    $uItems->bind_param("ssi", $batch['destination_name'], $batch['destination_name'], $batchDbId);
                    $uItems->execute();
                    $uItems->close();
                }
            }
        }

        // Log to activity_log
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $desc = "Batch {$batch_id} approved by {$userName} ({$userRole})";
        $logStmt = $conn->prepare("INSERT INTO activity_log (user_id, action_type, action, description, ip_address) VALUES (?, 'batch', 'approved', ?, ?)");
        if ($logStmt) {
            $logStmt->bind_param("iss", $userId, $desc, $ip);
            $logStmt->execute();
            $logStmt->close();
        }

        $successMsg = $hasTransportDriver 
            ? 'Batch approved successfully and authorized for transport dispatch!' 
            : 'Batch approved and equipment handover completed (100%)!';

        echo json_encode([
            'success' => true,
            'message' => $successMsg,
            'batch_id' => $batch_id,
            'status' => $finalStatus
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to approve batch: ' . $conn->error]);
    }
} elseif ($action === 'complete') {
    // Mark batch handover/delivery as 100% completed
    $finalStatus = 'completed';
    $finalApprovalStatus = 'completed';

    $updateSql = "UPDATE stock_movements 
                  SET status = 'completed', 
                      approval_status = 'completed',
                      approved_by_id = COALESCE(approved_by_id, ?), 
                      approved_by_name = COALESCE(approved_by_name, ?),
                      approved_at = COALESCE(approved_at, NOW()),
                      approval_notes = CONCAT(COALESCE(approval_notes, ''), '\nHandover Completed by {$userName}')
                  WHERE id = ?";
    $stmt = $conn->prepare($updateSql);
    $stmt->bind_param("isi", $userId, $userName, $batchDbId);
    $execOk = $stmt->execute();
    $stmt->close();

    if ($execOk) {
        $biStmt = $conn->prepare("UPDATE batch_items SET status = 'completed', item_status = 'completed' WHERE batch_id = ?");
        if ($biStmt) {
            $biStmt->bind_param("i", $batchDbId);
            $biStmt->execute();
            $biStmt->close();
        }

        $destWithRoom = $batch['destination_name'] . (!empty($batch['destination_room']) ? ' - ' . $batch['destination_room'] : '');

        if (in_array($batch['movement_type'], ['stock_to_venue_room', 'stock_to_venue_transport'])) {
            $uItems = $conn->prepare("UPDATE items SET status = 'in_use', current_location = ?, updated_at = NOW() WHERE id IN (SELECT item_id FROM batch_items WHERE batch_id = ? AND item_id IS NOT NULL)");
            if ($uItems) {
                $uItems->bind_param("si", $destWithRoom, $batchDbId);
                $uItems->execute();
                $uItems->close();
            }
        } else {
            $uItems = $conn->prepare("UPDATE items SET status = 'available', current_location = ?, stock_location = ?, updated_at = NOW() WHERE id IN (SELECT item_id FROM batch_items WHERE batch_id = ? AND item_id IS NOT NULL)");
            if ($uItems) {
                $uItems->bind_param("ssi", $batch['destination_name'], $batch['destination_name'], $batchDbId);
                $uItems->execute();
                $uItems->close();
            }
        }

        // Log to activity_log
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $desc = "Batch {$batch_id} marked as Completed (100%) by {$userName} ({$userRole})";
        $logStmt = $conn->prepare("INSERT INTO activity_log (user_id, action_type, action, description, ip_address) VALUES (?, 'batch', 'completed', ?, ?)");
        if ($logStmt) {
            $logStmt->bind_param("iss", $userId, $desc, $ip);
            $logStmt->execute();
            $logStmt->close();
        }

        echo json_encode([
            'success' => true,
            'message' => 'Batch marked as 100% completed successfully!',
            'batch_id' => $batch_id,
            'status' => 'completed'
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to complete batch: ' . $conn->error]);
    }
} elseif ($action === 'reset_pending') {
    // Reset batch to pending approval
    $updateSql = "UPDATE stock_movements 
                  SET status = 'pending', 
                      approval_status = 'pending',
                      approved_by_id = NULL, 
                      approved_by_name = NULL,
                      approved_at = NULL,
                      approval_notes = NULL,
                      rejection_reason = NULL,
                      driver_verified = 0,
                      tech_onboard = 0
                  WHERE id = ?";
    $stmt = $conn->prepare($updateSql);
    $stmt->bind_param("i", $batchDbId);
    $execOk = $stmt->execute();
    $stmt->close();

    if ($execOk) {
        $biStmt = $conn->prepare("UPDATE batch_items SET status = 'pending', item_status = 'pending' WHERE batch_id = ?");
        if ($biStmt) {
            $biStmt->bind_param("i", $batchDbId);
            $biStmt->execute();
            $biStmt->close();
        }

        // Revert items to available at source location
        $uItems = $conn->prepare("UPDATE items SET status = 'available', current_location = ?, updated_at = NOW() WHERE id IN (SELECT item_id FROM batch_items WHERE batch_id = ? AND item_id IS NOT NULL)");
        if ($uItems) {
            $uItems->bind_param("si", $batch['source_name'], $batchDbId);
            $uItems->execute();
            $uItems->close();
        }

        // Log to activity_log
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $desc = "Batch {$batch_id} reset to Pending by {$userName} ({$userRole})";
        $logStmt = $conn->prepare("INSERT INTO activity_log (user_id, action_type, action, description, ip_address) VALUES (?, 'batch', 'reset_pending', ?, ?)");
        if ($logStmt) {
            $logStmt->bind_param("iss", $userId, $desc, $ip);
            $logStmt->execute();
            $logStmt->close();
        }

        echo json_encode([
            'success' => true,
            'message' => 'Batch has been reset to Pending approval.',
            'batch_id' => $batch_id,
            'status' => 'pending'
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to reset batch: ' . $conn->error]);
    }
} else {
    // Action is reject
    $updateSql = "UPDATE stock_movements 
                  SET status = 'rejected', 
                      approval_status = 'rejected',
                      approved_by_id = ?, 
                      approved_by_name = ?,
                      approved_at = NOW(),
                      rejection_reason = ?
                  WHERE id = ?";
    $stmt = $conn->prepare($updateSql);
    $stmt->bind_param("issi", $userId, $userName, $notes, $batchDbId);
    $execOk = $stmt->execute();
    $stmt->close();

    if ($execOk) {
        $biStmt = $conn->prepare("UPDATE batch_items SET status = 'rejected', item_status = 'rejected' WHERE batch_id = ?");
        if ($biStmt) {
            $biStmt->bind_param("i", $batchDbId);
            $biStmt->execute();
            $biStmt->close();
        }

        // Log to activity_log
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $desc = "Batch {$batch_id} rejected by {$userName} ({$userRole}). Reason: {$notes}";
        $logStmt = $conn->prepare("INSERT INTO activity_log (user_id, action_type, action, description, ip_address) VALUES (?, 'batch', 'rejected', ?, ?)");
        if ($logStmt) {
            $logStmt->bind_param("iss", $userId, $desc, $ip);
            $logStmt->execute();
            $logStmt->close();
        }

        echo json_encode([
            'success' => true,
            'message' => 'Batch has been rejected.',
            'batch_id' => $batch_id,
            'status' => 'rejected'
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to reject batch: ' . $conn->error]);
    }
}

$conn->close();
?>
