<?php
// id_badges.php - User ID Badges, Stock & Venue Access Levels, and Attendance Management
require_once 'bootstrap.php';
require_once 'includes/functions.php';
require_once 'includes/db_connect.php';
require_once 'includes/badge_attendance_helper.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit();
}

$conn = getConnection();
ensureBadgesAndAttendanceTables($conn);

$clearanceMeta = getClearanceLevelsMeta();
$currentUserId = (int)($_SESSION['user_id'] ?? 0);

// Load Stock Locations directly from stock_locations table (matching the warehouse floor)
$stockLocations = [];
$stockMap = [];
$stRes = @$conn->query("
    SELECT id, name, code, location_code, location, open_time, close_time, is_active
    FROM stock_locations
    WHERE is_active = 1
    ORDER BY id ASC
");
if ($stRes) {
    while ($row = $stRes->fetch_assoc()) {
        $sid = (int)$row['id'];
        $stockLocations[] = $row;
        $stockMap[$sid] = $row;
    }
}
if (empty($stockLocations)) {
    $stockLocations = [
        ['id' => 1, 'name' => 'Ndera Warehouse', 'code' => 'NDR-STK', 'location_code' => 'NDR', 'location' => 'Ndera Central Warehouse', 'open_time' => '08:00:00', 'close_time' => '18:00:00', 'is_active' => 1],
        ['id' => 2, 'name' => 'BK Arena Stock', 'code' => 'BKA-STK', 'location_code' => 'BKA', 'location' => 'BK Arena Stock Room', 'open_time' => '08:00:00', 'close_time' => '18:00:00', 'is_active' => 1],
        ['id' => 3, 'name' => 'KCC Stock', 'code' => 'KCC-STK', 'location_code' => 'KCC', 'location' => 'Kigali Convention Centre Stock', 'open_time' => '08:00:00', 'close_time' => '18:00:00', 'is_active' => 1]
    ];
    foreach ($stockLocations as $sl) {
        $stockMap[(int)$sl['id']] = $sl;
    }
}
$stockNames = array_map(function($s) { return $s['name']; }, $stockLocations);
$stockLabelSummary = !empty($stockNames) ? implode(', ', array_slice($stockNames, 0, 3)) . (count($stockNames) > 3 ? '..' : '') : 'Stock Warehouses';

// Load Event Venues
$eventsList = [];
$eventMap = [];
$evRes = @$conn->query("
    SELECT id, title, location, date, call_time, project_manager, tech_lead, assigned_technician
    FROM events
    ORDER BY date DESC, id DESC
    LIMIT 30
");
if ($evRes) {
    while ($row = $evRes->fetch_assoc()) {
        $eid = normalizeEventNumericId($row['id']);
        $row['id'] = $eid;
        $eventsList[] = $row;
        $eventMap[$eid] = $row;
    }
}
if (empty($eventsList)) {
    $eventsList = [
        ['id' => 1, 'title' => 'Marriot Hotel', 'location' => 'Kigali Marriott Hotel', 'date' => date('Y-m-d'), 'call_time' => '08:00', 'project_manager' => 'Operations PM', 'tech_lead' => 'Tech Lead', 'assigned_technician' => ''],
        ['id' => 2, 'title' => 'FourPoint Hotel', 'location' => 'Four Points by Sheraton', 'date' => date('Y-m-d'), 'call_time' => '08:30', 'project_manager' => 'Operations PM', 'tech_lead' => 'Tech Lead', 'assigned_technician' => ''],
        ['id' => 3, 'title' => 'MovenPick Hotel', 'location' => 'Mövenpick Hotel Kigali', 'date' => date('Y-m-d'), 'call_time' => '09:00', 'project_manager' => 'Operations PM', 'tech_lead' => 'Tech Lead', 'assigned_technician' => '']
    ];
    foreach ($eventsList as $ev) {
        $eventMap[(int)$ev['id']] = $ev;
    }
}
$eventTitles = array_map(function($e) { return $e['title']; }, $eventsList);
$venueLabelSummary = !empty($eventTitles) ? implode(', ', array_slice($eventTitles, 0, 3)) . (count($eventTitles) > 3 ? '..' : '') : 'Event Venues';

// ==================== AJAX & FORM POST HANDLERS ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_action'])) {
    while (ob_get_level()) {
        ob_end_clean();
    }
    header('Content-Type: application/json');

    $action = $_POST['ajax_action'];

    // 1. Save individual user's badge & access level configuration
    if ($action === 'save_badge_config') {
        $targetUserId = (int)($_POST['user_id'] ?? 0);
        $clearanceLevel = $_POST['clearance_level'] ?? 'L1_VENUE_BASIC';
        if (!isset($clearanceMeta[$clearanceLevel])) {
            $clearanceLevel = 'L1_VENUE_BASIC';
        }

        $stockAccessMode = $_POST['stock_access_mode'] ?? 'none';
        if (!in_array($stockAccessMode, ['none', 'selected', 'all'], true)) {
            $stockAccessMode = 'none';
        }

        $allowedStockIds = isset($_POST['allowed_stock_ids']) && is_array($_POST['allowed_stock_ids'])
            ? array_values(array_map('intval', $_POST['allowed_stock_ids']))
            : [];

        $venueAccessMode = $_POST['venue_access_mode'] ?? 'assigned_only';
        if (!in_array($venueAccessMode, ['none', 'assigned_only', 'selected', 'all'], true)) {
            $venueAccessMode = 'assigned_only';
        }

        $allowedEventIds = isset($_POST['allowed_event_ids']) && is_array($_POST['allowed_event_ids'])
            ? array_values(array_map('intval', $_POST['allowed_event_ids']))
            : [];

        $badgeStatus = $_POST['badge_status'] ?? 'active';
        if (!in_array($badgeStatus, ['active', 'suspended', 'revoked', 'expired'], true)) {
            $badgeStatus = 'active';
        }

        $rfidUid = trim($_POST['rfid_uid'] ?? '');
        $emergencyContact = trim($_POST['emergency_contact'] ?? '+250 788 000 112');
        $bloodGroup = trim($_POST['blood_group'] ?? 'O+');
        $expiresAt = !empty($_POST['expires_at']) ? $_POST['expires_at'] : date('Y-12-31', strtotime('+1 year'));

        $stockJson = json_encode($allowedStockIds);
        $eventJson = json_encode($allowedEventIds);

        $stmt = $conn->prepare("
            UPDATE user_badges
            SET clearance_level = ?,
                stock_access_mode = ?,
                allowed_stock_ids = ?,
                venue_access_mode = ?,
                allowed_event_ids = ?,
                badge_status = ?,
                rfid_uid = ?,
                emergency_contact = ?,
                blood_group = ?,
                expires_at = ?
            WHERE user_id = ?
        ");
        if ($stmt) {
            $stmt->bind_param(
                "ssssssssssi",
                $clearanceLevel,
                $stockAccessMode,
                $stockJson,
                $venueAccessMode,
                $eventJson,
                $badgeStatus,
                $rfidUid,
                $emergencyContact,
                $bloodGroup,
                $expiresAt,
                $targetUserId
            );
            $ok = $stmt->execute();
            $stmt->close();

            echo json_encode([
                'success' => $ok,
                'message' => $ok ? 'ID Badge & Stock/Venue access clearance updated successfully!' : 'Failed to update badge configuration.'
            ]);
            exit();
        }

        echo json_encode(['success' => false, 'message' => 'Database statement preparation error.']);
        exit();
    }

    // 2. Bulk sync all user badges to role-based default access levels
    if ($action === 'sync_role_defaults') {
        $allStockIds = array_keys($stockMap);
        $allEventIds = array_keys($eventMap);

        $uRes = $conn->query("SELECT id, role, is_active FROM users");
        $updatedCount = 0;
        if ($uRes) {
            $upStmt = $conn->prepare("
                UPDATE user_badges
                SET clearance_level = ?,
                    stock_access_mode = ?,
                    allowed_stock_ids = ?,
                    venue_access_mode = ?,
                    allowed_event_ids = ?,
                    badge_status = ?
                WHERE user_id = ?
            ");
            while ($u = $uRes->fetch_assoc()) {
                $uid = (int)$u['id'];
                $spec = getDefaultBadgeSpecForRole($u['role'], $allStockIds, $allEventIds);
                $bStatus = ((int)$u['is_active'] === 1) ? 'active' : 'suspended';
                if ($upStmt) {
                    $upStmt->bind_param(
                        "ssssssi",
                        $spec['clearance_level'],
                        $spec['stock_access_mode'],
                        $spec['allowed_stock_ids'],
                        $spec['venue_access_mode'],
                        $spec['allowed_event_ids'],
                        $bStatus,
                        $uid
                    );
                    if ($upStmt->execute()) {
                        $updatedCount++;
                    }
                }
            }
            if ($upStmt) {
                $upStmt->close();
            }
        }

        echo json_encode([
            'success' => true,
            'message' => "Synchronized default Stock & Venue access levels for {$updatedCount} user badges!"
        ]);
        exit();
    }

    // 2b. Save / Update Access Clearance Level (CRUD)
    if ($action === 'save_clearance_level') {
        $id = (int)($_POST['level_id'] ?? 0);
        $code = strtoupper(trim($_POST['code'] ?? ''));
        $title = trim($_POST['title'] ?? '');
        $shortName = trim($_POST['short_name'] ?? '');
        $levelKey = trim($_POST['level_key'] ?? '');
        $color = trim($_POST['color'] ?? '#0284c7');
        $icon = trim($_POST['icon'] ?? 'fa-shield-halved');
        $description = trim($_POST['description'] ?? '');
        $defaultStock = $_POST['default_stock_access'] ?? 'none';
        $defaultVenue = $_POST['default_venue_access'] ?? 'assigned_only';
        $sortOrder = (int)($_POST['sort_order'] ?? 0);

        if ($code === '' || $title === '') {
            echo json_encode(['success' => false, 'message' => 'Level Code (e.g. L5) and Level Title are required.']);
            exit();
        }

        if ($levelKey === '') {
            $slug = preg_replace('/[^A-Z0-9]+/', '_', strtoupper($code . '_' . $title));
            $levelKey = trim($slug, '_');
        } else {
            $levelKey = preg_replace('/[^A-Z0-9_]+/', '', strtoupper($levelKey));
        }

        $bg = '#f8fafc';
        $border = '#e2e8f0';
        $badgeBg = 'linear-gradient(135deg, ' . $color . ' 0%, #0f172a 100%)';

        if ($id > 0) {
            $chk = $conn->prepare("SELECT id, level_key FROM access_clearance_levels WHERE level_key = ? AND id != ?");
            $chk->bind_param("si", $levelKey, $id);
            $chk->execute();
            if ($chk->get_result()->fetch_assoc()) {
                echo json_encode(['success' => false, 'message' => 'Clearance level key "' . $levelKey . '" is already in use by another level.']);
                exit();
            }
            $chk->close();

            $origSt = $conn->prepare("SELECT level_key FROM access_clearance_levels WHERE id = ?");
            $origSt->bind_param("i", $id);
            $origSt->execute();
            $origRow = $origSt->get_result()->fetch_assoc();
            $origSt->close();
            $oldKey = $origRow ? $origRow['level_key'] : $levelKey;

            $up = $conn->prepare("
                UPDATE access_clearance_levels
                SET level_key = ?, code = ?, title = ?, short_name = ?, color = ?, bg = ?, border = ?, badge_bg = ?, icon = ?, description = ?, default_stock_access = ?, default_venue_access = ?, sort_order = ?
                WHERE id = ?
            ");
            $up->bind_param("ssssssssssssii", $levelKey, $code, $title, $shortName, $color, $bg, $border, $badgeBg, $icon, $description, $defaultStock, $defaultVenue, $sortOrder, $id);
            $success = $up->execute();
            $up->close();

            if ($success && $oldKey !== $levelKey) {
                $upBadge = $conn->prepare("UPDATE user_badges SET clearance_level = ? WHERE clearance_level = ?");
                $upBadge->bind_param("ss", $levelKey, $oldKey);
                $upBadge->execute();
                $upBadge->close();
            }

            echo json_encode(['success' => true, 'message' => 'Access clearance level updated successfully!']);
            exit();
        } else {
            $chk = $conn->prepare("SELECT id FROM access_clearance_levels WHERE level_key = ?");
            $chk->bind_param("s", $levelKey);
            $chk->execute();
            if ($chk->get_result()->fetch_assoc()) {
                echo json_encode(['success' => false, 'message' => 'Clearance level key "' . $levelKey . '" already exists.']);
                exit();
            }
            $chk->close();

            $ins = $conn->prepare("
                INSERT INTO access_clearance_levels (level_key, code, title, short_name, color, bg, border, badge_bg, icon, description, default_stock_access, default_venue_access, sort_order)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $ins->bind_param("ssssssssssssi", $levelKey, $code, $title, $shortName, $color, $bg, $border, $badgeBg, $icon, $description, $defaultStock, $defaultVenue, $sortOrder);
            $success = $ins->execute();
            $newId = $ins->insert_id;
            $ins->close();

            echo json_encode(['success' => true, 'id' => $newId, 'message' => 'New access clearance level added successfully!']);
            exit();
        }
    }

    // 2c. Get Access Clearance Level Details for Edit Modal
    if ($action === 'get_clearance_level') {
        $id = (int)($_POST['id'] ?? 0);
        $st = $conn->prepare("SELECT * FROM access_clearance_levels WHERE id = ?");
        $st->bind_param("i", $id);
        $st->execute();
        $row = $st->get_result()->fetch_assoc();
        $st->close();
        if ($row) {
            echo json_encode(['success' => true, 'level' => $row]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Clearance level not found.']);
        }
        exit();
    }

    // 2d. Delete Access Clearance Level (with Badge Reassignment)
    if ($action === 'delete_clearance_level') {
        $id = (int)($_POST['id'] ?? 0);
        $reassignKey = trim($_POST['reassign_to'] ?? 'L1_VENUE_BASIC');

        $st = $conn->prepare("SELECT * FROM access_clearance_levels WHERE id = ?");
        $st->bind_param("i", $id);
        $st->execute();
        $lvl = $st->get_result()->fetch_assoc();
        $st->close();

        if (!$lvl) {
            echo json_encode(['success' => false, 'message' => 'Level not found.']);
            exit();
        }

        $targetKey = $lvl['level_key'];

        // Reassign any users currently assigned to this level
        $reassignSt = $conn->prepare("UPDATE user_badges SET clearance_level = ? WHERE clearance_level = ?");
        $reassignSt->bind_param("ss", $reassignKey, $targetKey);
        $reassignSt->execute();
        $affectedUsers = $reassignSt->affected_rows;
        $reassignSt->close();

        // Delete the level
        $delSt = $conn->prepare("DELETE FROM access_clearance_levels WHERE id = ?");
        $delSt->bind_param("i", $id);
        $success = $delSt->execute();
        $delSt->close();

        echo json_encode([
            'success' => $success,
            'message' => 'Clearance level deleted.' . ($affectedUsers > 0 ? " {$affectedUsers} staff members were reassigned to {$reassignKey}." : '')
        ]);
        exit();
    }

    // 3. Scan Badge at Gate Kiosk (Stock Warehouse / Event Venue / Office)
    if ($action === 'scan_badge_attendance') {
        $rawInput = trim($_POST['scan_input'] ?? '');
        // If QR payload has pipe separator (e.g. ABL-BDG-0001|TAX-0001|username), extract first token
        if (strpos($rawInput, '|') !== false) {
            $parts = explode('|', $rawInput);
            $rawInput = trim($parts[0]);
        }

        $checkpointType = $_POST['checkpoint_type'] ?? 'stock_warehouse';
        if (!in_array($checkpointType, ['stock_warehouse', 'event_venue', 'main_office'], true)) {
            $checkpointType = 'stock_warehouse';
        }
        $checkpointId = (int)($_POST['checkpoint_id'] ?? 0);
        $scanMode = $_POST['scan_mode'] ?? 'auto';
        $notes = trim($_POST['notes'] ?? '');

        // Resolve checkpoint display name
        $checkpointName = 'Main Office Reception';
        if ($checkpointType === 'stock_warehouse') {
            $checkpointName = isset($stockMap[$checkpointId])
                ? ($stockMap[$checkpointId]['name'] . (!empty($stockMap[$checkpointId]['code']) ? ' (' . $stockMap[$checkpointId]['code'] . ')' : ''))
                : 'Central Stock Warehouse';
        } elseif ($checkpointType === 'event_venue') {
            $checkpointName = isset($eventMap[$checkpointId])
                ? ($eventMap[$checkpointId]['title'] . (!empty($eventMap[$checkpointId]['location']) ? ' — ' . $eventMap[$checkpointId]['location'] : ''))
                : 'Production Event Venue';
        }

        // Resolve user & badge from scan_input
        $matchedUser = null;
        $matchedBadge = null;

        // Check if input is TAX-XXXX
        $taxUserId = 0;
        if (preg_match('/^TAX-0*(\d+)$/i', $rawInput, $m)) {
            $taxUserId = (int)$m[1];
        } elseif (ctype_digit($rawInput)) {
            $taxUserId = (int)$rawInput;
        }

        $findStmt = $conn->prepare("
            SELECT u.*, b.badge_code, b.rfid_uid, b.clearance_level, b.stock_access_mode, b.allowed_stock_ids,
                   b.venue_access_mode, b.allowed_event_ids, b.badge_status, b.emergency_contact, b.blood_group,
                   b.issued_at, b.expires_at
            FROM users u
            INNER JOIN user_badges b ON u.id = b.user_id
            WHERE b.badge_code = ? OR b.rfid_uid = ? OR u.username = ? OR u.id = ?
            LIMIT 1
        ");
        if ($findStmt) {
            $findStmt->bind_param("sssi", $rawInput, $rawInput, $rawInput, $taxUserId);
            $findStmt->execute();
            $res = $findStmt->get_result();
            if ($row = $res->fetch_assoc()) {
                $matchedUser = $row;
                $matchedBadge = $row;
            }
            $findStmt->close();
        }

        if (!$matchedUser) {
            echo json_encode([
                'success' => false,
                'access_status' => 'denied',
                'message' => "Unrecognized Badge Code or Staff ID: \"" . htmlspecialchars($rawInput) . "\".",
                'checkpoint_name' => $checkpointName
            ]);
            exit();
        }

        $uid = (int)$matchedUser['id'];
        $badgeCode = $matchedBadge['badge_code'];

        // Determine scan_action (check_in, check_out, access_verify)
        $resolvedAction = $scanMode;
        if ($scanMode === 'auto') {
            $lastStmt = $conn->prepare("
                SELECT scan_action FROM attendance_logs
                WHERE user_id = ? AND checkpoint_type = ? AND checkpoint_id = ? AND access_status = 'granted'
                  AND DATE(scanned_at) = CURDATE() AND scan_action IN ('check_in', 'check_out')
                ORDER BY scanned_at DESC, id DESC LIMIT 1
            ");
            $resolvedAction = 'check_in';
            if ($lastStmt) {
                $lastStmt->bind_param("isi", $uid, $checkpointType, $checkpointId);
                $lastStmt->execute();
                $lRes = $lastStmt->get_result();
                if ($lRow = $lRes->fetch_assoc()) {
                    if ($lRow['scan_action'] === 'check_in') {
                        $resolvedAction = 'check_out';
                    }
                }
                $lastStmt->close();
            }
        }

        // Evaluate access clearance
        $eval = evaluateUserCheckpointAccess($conn, $matchedUser, $matchedBadge, $checkpointType, $checkpointId, $stockMap, $eventMap);
        $accessStatus = $eval['granted'] ? 'granted' : 'denied';
        $denialReason = $eval['granted'] ? null : $eval['reason'];
        $punctuality = $eval['punctuality'] ?? 'n/a';
        if ($resolvedAction === 'check_out') {
            $punctuality = 'n/a';
        }

        if (empty($notes)) {
            $notes = $eval['reason'];
        }

        $nowStr = date('Y-m-d H:i:s');
        $logStmt = $conn->prepare("
            INSERT INTO attendance_logs
            (user_id, badge_code, checkpoint_type, checkpoint_id, checkpoint_name, scan_action, access_status, denial_reason, punctuality_status, scanned_by, notes, scanned_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $logId = 0;
        if ($logStmt) {
            $logStmt->bind_param(
                "ississsssiss",
                $uid,
                $badgeCode,
                $checkpointType,
                $checkpointId,
                $checkpointName,
                $resolvedAction,
                $accessStatus,
                $denialReason,
                $punctuality,
                $currentUserId,
                $notes,
                $nowStr
            );
            $logStmt->execute();
            $logId = $logStmt->insert_id;
            $logStmt->close();
        }

        $cMeta = $clearanceMeta[$matchedBadge['clearance_level']] ?? $clearanceMeta['L1_VENUE_BASIC'];
        $hasPhoto = !empty($matchedUser['profile_image']) && file_exists(__DIR__ . '/' . $matchedUser['profile_image']);

        // Fetch today's check_in and check_out times for this user at this checkpoint
        $inTime = null;
        $outTime = null;
        $todayTimesStmt = $conn->prepare("
            SELECT scan_action, DATE_FORMAT(scanned_at, '%H:%i:%s %p') as formatted_time
            FROM attendance_logs
            WHERE user_id = ? AND checkpoint_type = ? AND checkpoint_id = ? AND access_status = 'granted'
              AND DATE(scanned_at) = CURDATE() AND scan_action IN ('check_in', 'check_out')
            ORDER BY scanned_at ASC, id ASC
        ");
        if ($todayTimesStmt) {
            $todayTimesStmt->bind_param("isi", $uid, $checkpointType, $checkpointId);
            $todayTimesStmt->execute();
            $ttRes = $todayTimesStmt->get_result();
            while ($ttRow = $ttRes->fetch_assoc()) {
                if ($ttRow['scan_action'] === 'check_in') {
                    $inTime = $ttRow['formatted_time'];
                } elseif ($ttRow['scan_action'] === 'check_out') {
                    $outTime = $ttRow['formatted_time'];
                }
            }
            $todayTimesStmt->close();
        }
        if (!$inTime && $resolvedAction === 'check_in' && $accessStatus === 'granted') {
            $inTime = date('H:i:s A', strtotime($nowStr));
        }
        if (!$outTime && $resolvedAction === 'check_out' && $accessStatus === 'granted') {
            $outTime = date('H:i:s A', strtotime($nowStr));
        }

        echo json_encode([
            'success' => true,
            'log_id' => $logId,
            'access_status' => $accessStatus,
            'scan_action' => $resolvedAction,
            'punctuality' => $punctuality,
            'reason' => $eval['reason'],
            'scanned_at' => date('H:i:s A', strtotime($nowStr)),
            'check_in_time' => $inTime,
            'check_out_time' => $outTime,
            'scanned_date' => date('M d, Y', strtotime($nowStr)),
            'checkpoint_type' => $checkpointType,
            'checkpoint_name' => $checkpointName,
            'user' => [
                'id' => $uid,
                'full_name' => $matchedUser['full_name'] ?: $matchedUser['username'],
                'username' => $matchedUser['username'],
                'role' => ucwords(str_replace('_', ' ', $matchedUser['role'])),
                'department' => $matchedUser['department'] ?: 'Operations',
                'staff_code' => 'TAX-' . str_pad((string)$uid, 4, '0', STR_PAD_LEFT),
                'badge_code' => $badgeCode,
                'rfid_uid' => $matchedBadge['rfid_uid'],
                'profile_image' => $hasPhoto ? $matchedUser['profile_image'] : '',
                'clearance_code' => $cMeta['code'],
                'clearance_title' => $cMeta['title'],
                'clearance_color' => $cMeta['color']
            ]
        ]);
        exit();
    }

    echo json_encode(['success' => false, 'message' => 'Unknown action.']);
    exit();
}

// ==================== FETCH ALL USERS + BADGES ====================
$usersWithBadges = [];
$qb = $conn->query("
    SELECT u.id, u.username, u.email, u.full_name, u.phone, u.role, u.department, u.profile_image, u.signature_image, u.is_active,
           b.badge_code, b.rfid_uid, b.clearance_level, b.stock_access_mode, b.allowed_stock_ids,
           b.venue_access_mode, b.allowed_event_ids, b.badge_status, b.emergency_contact, b.blood_group,
           b.issued_at, b.expires_at
    FROM users u
    LEFT JOIN user_badges b ON u.id = b.user_id
    ORDER BY u.id ASC
");
if ($qb) {
    while ($row = $qb->fetch_assoc()) {
        $uid = (int)$row['id'];
        $row['staff_code'] = 'TAX-' . str_pad((string)$uid, 4, '0', STR_PAD_LEFT);
        $row['has_photo'] = !empty($row['profile_image']) && file_exists(__DIR__ . '/' . $row['profile_image']);
        $row['has_sig'] = !empty($row['signature_image']) && file_exists(__DIR__ . '/' . $row['signature_image']);

        $allowedS = json_decode($row['allowed_stock_ids'] ?? '[]', true);
        $row['allowed_stock_array'] = is_array($allowedS) ? array_map('intval', $allowedS) : [];

        $allowedE = json_decode($row['allowed_event_ids'] ?? '[]', true);
        $row['allowed_event_array'] = is_array($allowedE) ? array_map('intval', $allowedE) : [];

        $usersWithBadges[$uid] = $row;
    }
}

// ==================== FETCH ATTENDANCE LOGS & CURRENT ON-SITE ROSTER ====================
$attendanceLogs = [];
$logQuery = $conn->query("
    SELECT l.*, u.full_name, u.username, u.role, u.department, u.profile_image, b.clearance_level
    FROM attendance_logs l
    LEFT JOIN users u ON l.user_id = u.id
    LEFT JOIN user_badges b ON l.user_id = b.user_id
    ORDER BY l.scanned_at DESC, l.id DESC
    LIMIT 100
");
if ($logQuery) {
    while ($l = $logQuery->fetch_assoc()) {
        $attendanceLogs[] = $l;
    }
}

// Compute who is currently checked in (latest granted check_in without subsequent check_out today)
$onSiteRoster = [];
$seenUserCheckpoint = [];
foreach ($attendanceLogs as $log) {
    if ($log['access_status'] !== 'granted') {
        continue;
    }
    if (!in_array($log['scan_action'], ['check_in', 'check_out'], true)) {
        continue;
    }
    $key = $log['user_id'] . '_' . $log['checkpoint_type'] . '_' . $log['checkpoint_id'];
    if (isset($seenUserCheckpoint[$key])) {
        continue;
    }
    $seenUserCheckpoint[$key] = true;
    if ($log['scan_action'] === 'check_in') {
        $onSiteRoster[] = $log;
    }
}

// KPI Counters & Today's Checkpoint States
$totalBadges = count($usersWithBadges);
$activeBadgesCount = 0;
$stockAuthorizedCount = 0;
$venueAuthorizedCount = 0;
foreach ($usersWithBadges as $ub) {
    if (($ub['badge_status'] ?? 'active') === 'active' && (int)$ub['is_active'] === 1) {
        $activeBadgesCount++;
    }
    if (($ub['clearance_level'] ?? '') === 'L4_ALL_ACCESS' || ($ub['stock_access_mode'] ?? 'none') !== 'none') {
        $stockAuthorizedCount++;
    }
    if (($ub['clearance_level'] ?? '') === 'L4_ALL_ACCESS' || ($ub['venue_access_mode'] ?? 'assigned_only') !== 'none') {
        $venueAuthorizedCount++;
    }
}
$onSiteCount = count($onSiteRoster);
$deniedTodayCount = 0;
foreach ($attendanceLogs as $al) {
    if ($al['access_status'] === 'denied') {
        $deniedTodayCount++;
    }
}

// Build today's attendance state map per checkpoint: "{checkpoint_type}_{checkpoint_id}_{user_id}" => ['state' => 'checked_in'|'checked_out', 'time' => 'HH:MM:SS AM', 'check_in_time' => ..., 'check_out_time' => ...]
$todayCheckpointStates = [];
$stateQ = $conn->query("
    SELECT user_id, checkpoint_type, checkpoint_id, scan_action, DATE_FORMAT(scanned_at, '%H:%i:%s %p') as scan_time
    FROM attendance_logs
    WHERE access_status = 'granted'
      AND DATE(scanned_at) = CURDATE()
      AND scan_action IN ('check_in', 'check_out')
    ORDER BY scanned_at ASC, id ASC
");
if ($stateQ) {
    while ($sr = $stateQ->fetch_assoc()) {
        $sKey = $sr['checkpoint_type'] . '_' . (int)$sr['checkpoint_id'] . '_' . (int)$sr['user_id'];
        if (!isset($todayCheckpointStates[$sKey])) {
            $todayCheckpointStates[$sKey] = [
                'state' => ($sr['scan_action'] === 'check_in') ? 'checked_in' : 'checked_out',
                'time' => $sr['scan_time'],
                'check_in_time' => ($sr['scan_action'] === 'check_in') ? $sr['scan_time'] : null,
                'check_out_time' => ($sr['scan_action'] === 'check_out') ? $sr['scan_time'] : null
            ];
        } else {
            $todayCheckpointStates[$sKey]['state'] = ($sr['scan_action'] === 'check_in') ? 'checked_in' : 'checked_out';
            $todayCheckpointStates[$sKey]['time'] = $sr['scan_time'];
            if ($sr['scan_action'] === 'check_in') {
                $todayCheckpointStates[$sKey]['check_in_time'] = $sr['scan_time'];
            } elseif ($sr['scan_action'] === 'check_out') {
                $todayCheckpointStates[$sKey]['check_out_time'] = $sr['scan_time'];
            }
        }
    }
}

// Resolve default / initial user for the Live Gate Decision card (Patrick, Eric, or latest scan)
$initialKioskUser = null;
if (isset($usersWithBadges[6])) {
    $initialKioskUser = $usersWithBadges[6];
} elseif (isset($usersWithBadges[9])) {
    $initialKioskUser = $usersWithBadges[9];
} else {
    foreach ($usersWithBadges as $u) {
        $uName = strtolower($u['full_name'] . ' ' . $u['username']);
        if (strpos($uName, 'patr') !== false || strpos($uName, 'eric') !== false) {
            $initialKioskUser = $u;
            break;
        }
    }
}
if (!$initialKioskUser && !empty($attendanceLogs)) {
    $initUid = (int)($attendanceLogs[0]['user_id'] ?? 0);
    $initialKioskUser = $usersWithBadges[$initUid] ?? null;
}
if (!$initialKioskUser && !empty($usersWithBadges)) {
    $initialKioskUser = reset($usersWithBadges);
}

// Pre-selected checkpoint type & venue / stock
$defaultType = isset($_GET['type']) && in_array($_GET['type'], ['event_venue', 'stock_warehouse'], true)
    ? $_GET['type']
    : 'event_venue';

$defaultEventId = !empty($eventsList) ? (int)$eventsList[0]['id'] : 1;
foreach ($eventsList as $ev) {
    if (stripos($ev['title'], 'movenpick') !== false) {
        $defaultEventId = (int)$ev['id'];
        break;
    }
}
$defaultStockId = !empty($stockLocations) ? (int)$stockLocations[0]['id'] : 1;

if ($defaultType === 'event_venue') {
    $matchedEvent = $eventMap[$defaultEventId] ?? (!empty($eventsList) ? $eventsList[0] : null);
    $initialCheckpointName = $matchedEvent
        ? ($matchedEvent['title'] . (!empty($matchedEvent['location']) ? ' — ' . $matchedEvent['location'] : ''))
        : 'Mövenpick Hotel Kigali — Conference Hall';
} else {
    $matchedStock = $stockMap[$defaultStockId] ?? (!empty($stockLocations) ? $stockLocations[0] : null);
    $initialCheckpointName = $matchedStock
        ? ($matchedStock['name'] . (!empty($matchedStock['location']) ? ' — ' . $matchedStock['location'] : ''))
        : 'Ndera Central Warehouse';
}

$initActiveId = ($defaultType === 'event_venue') ? $defaultEventId : $defaultStockId;
$initKey = $defaultType . '_' . $initActiveId . '_' . ($initialKioskUser ? (int)$initialKioskUser['id'] : 0);
$initInTime = $todayCheckpointStates[$initKey]['check_in_time'] ?? '10:46:36 AM';
$initOutTime = $todayCheckpointStates[$initKey]['check_out_time'] ?? '10:46:41 AM';

// Optional focus user if linked from users.php?focus_user=X
$focusUserId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
$initialTab = $_GET['tab'] ?? 'gallery';

require_once 'views/partials/header.php';
?>

<!-- DataTables & SweetAlert2 & QRCodeJS -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>

<style>
    @import url('https://fonts.googleapis.com/css2?family=Titillium+Web:wght@300;400;600;700;900&family=JetBrains+Mono:wght@500;700&display=swap');

    .badges-hub-wrapper {
        font-family: "Titillium Web", sans-serif;
        padding: 1.5rem 1.25rem 3.5rem;
        max-width: 1480px;
        margin: 0 auto;
    }

    /* Hero Banner */
    .badges-hero-banner {
        background: linear-gradient(135deg, #0f172a 0%, #1e3a5f 60%, #0d9488 100%);
        border-radius: 18px;
        padding: 1.75rem 2rem;
        color: #ffffff;
        box-shadow: 0 10px 28px rgba(15, 23, 42, 0.18);
        margin-bottom: 1.5rem;
        position: relative;
        overflow: hidden;
    }

    /* KPI Cards */
    .kpi-metric-card {
        background: #ffffff;
        border-radius: 14px;
        border: 1px solid #e2e8f0;
        padding: 1.15rem 1.25rem;
        box-shadow: 0 4px 14px rgba(0, 0, 0, 0.03);
        display: flex;
        align-items: center;
        gap: 1rem;
        transition: transform 0.2s ease;
    }
    .kpi-metric-card:hover {
        transform: translateY(-2px);
    }
    .kpi-icon-circle {
        width: 50px;
        height: 50px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.35rem;
        flex-shrink: 0;
    }

    /* Hub Navigation Pills */
    .hub-nav-pills {
        background: #ffffff;
        padding: 8px;
        border-radius: 14px;
        border: 1px solid #e2e8f0;
        display: inline-flex;
        flex-wrap: wrap;
        gap: 6px;
        margin-bottom: 1.5rem;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.03);
    }
    .hub-nav-pills .nav-link {
        color: #475569;
        font-weight: 700;
        font-size: 0.92rem;
        padding: 10px 20px;
        border-radius: 10px;
        border: none;
        transition: all 0.2s ease;
    }
    .hub-nav-pills .nav-link.active {
        background: #0f172a;
        color: #ffffff;
        box-shadow: 0 4px 12px rgba(15, 23, 42, 0.2);
    }

    /* ==================== CR80 ID BADGE DESIGN (VERTICAL & FLIPPABLE) ==================== */
    .badge-item-col {
        transition: all 0.25s ease;
    }

    .cr80-badge-pair {
        display: flex;
        justify-content: center;
        gap: 16px;
        flex-wrap: wrap;
    }

    .cr80-card-shell {
        width: 295px;
        min-height: 468px;
        background: #ffffff;
        border-radius: 16px;
        border: 1px solid #cbd5e1;
        box-shadow: 0 10px 25px rgba(15, 23, 42, 0.1);
        overflow: hidden;
        position: relative;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        transition: transform 0.25s ease, box-shadow 0.25s ease;
    }
    .cr80-card-shell:hover {
        transform: translateY(-4px);
        box-shadow: 0 16px 32px rgba(15, 23, 42, 0.15);
    }

    /* Lanyard Slot Hole at Top */
    .lanyard-slot {
        width: 54px;
        height: 8px;
        background: rgba(255, 255, 255, 0.35);
        border-radius: 10px;
        margin: 8px auto 4px;
        border: 1px solid rgba(255, 255, 255, 0.4);
    }

    .cr80-front-header {
        padding: 8px 16px 38px;
        color: #ffffff;
        text-align: center;
        position: relative;
    }

    .cr80-brand-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-top: 4px;
    }
    .cr80-brand-logo {
        font-weight: 900;
        font-size: 1.15rem;
        letter-spacing: 0.5px;
    }
    .cr80-clearance-chip {
        font-family: "JetBrains Mono", monospace;
        font-size: 0.72rem;
        font-weight: 700;
        padding: 3px 9px;
        border-radius: 6px;
        background: rgba(255, 255, 255, 0.2);
        border: 1px solid rgba(255, 255, 255, 0.4);
        color: #ffffff;
    }

    .cr80-avatar-holder {
        width: 86px;
        height: 86px;
        margin: -34px auto 8px;
        border-radius: 50%;
        background: #ffffff;
        padding: 4px;
        box-shadow: 0 6px 16px rgba(0, 0, 0, 0.15);
        position: relative;
        z-index: 2;
    }
    .cr80-avatar-img {
        width: 100%;
        height: 100%;
        border-radius: 50%;
        object-fit: cover;
    }
    .cr80-avatar-initials {
        width: 100%;
        height: 100%;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 2rem;
        font-weight: 800;
        color: #ffffff;
    }

    .cr80-status-dot {
        position: absolute;
        bottom: 4px;
        right: 4px;
        width: 16px;
        height: 16px;
        border-radius: 50%;
        border: 2px solid #ffffff;
    }

    .cr80-user-name {
        font-size: 1.15rem;
        font-weight: 800;
        color: #0f172a;
        text-align: center;
        margin: 2px 12px 2px;
        line-height: 1.2;
    }

    .cr80-role-banner {
        display: inline-block;
        font-size: 0.7rem;
        font-weight: 800;
        letter-spacing: 0.8px;
        padding: 3px 12px;
        border-radius: 20px;
        color: #ffffff;
        text-transform: uppercase;
        margin-bottom: 6px;
    }

    .cr80-zone-strip {
        margin: 6px 14px;
        padding: 8px 10px;
        border-radius: 10px;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
    }

    .cr80-qr-box {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        margin: 4px 14px 10px;
        padding: 8px 10px;
        background: #ffffff;
        border: 1px dashed #cbd5e1;
        border-radius: 10px;
    }
    .cr80-qr-render {
        width: 72px;
        height: 72px;
        flex-shrink: 0;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .cr80-qr-render img,
    .cr80-qr-render canvas {
        width: 72px !important;
        height: 72px !important;
    }

    .cr80-footer-bar {
        padding: 7px 14px;
        color: #ffffff;
        font-size: 0.72rem;
        font-weight: 700;
        display: flex;
        justify-content: space-between;
        align-items: center;
        letter-spacing: 0.4px;
    }

    /* Kiosk Verification Result Card */
    .kiosk-result-granted {
        border: 2px solid #10b981;
        background: linear-gradient(180deg, #ecfdf5 0%, #ffffff 100%);
        border-radius: 16px;
    }
    .kiosk-result-denied {
        border: 2px solid #ef4444;
        background: linear-gradient(180deg, #fef2f2 0%, #ffffff 100%);
        border-radius: 16px;
    }

    .gate-time-action {
        transition: all 0.18s ease-in-out;
        padding: 5px 10px;
        border-radius: 8px;
    }
    .gate-time-action:hover {
        background: rgba(15, 23, 42, 0.07);
        transform: translateY(-1px);
    }
    .gate-time-action:active {
        transform: translateY(0);
    }

    /* Print Media Rules for CR80 Badges */
    @media print {
        body * {
            visibility: hidden;
        }
        #printableBadgesArea,
        #printableBadgesArea * {
            visibility: visible;
        }
        #printableBadgesArea {
            position: absolute;
            left: 0;
            top: 0;
            width: 100%;
            display: flex !important;
            flex-wrap: wrap !important;
            gap: 14px !important;
            padding: 10px !important;
            background: #ffffff !important;
        }
        .no-print {
            display: none !important;
        }
        .cr80-card-shell {
            box-shadow: none !important;
            border: 1px solid #94a3b8 !important;
            page-break-inside: avoid !important;
            break-inside: avoid !important;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }
    }
</style>

<div class="badges-hub-wrapper">
    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb" class="mb-3 no-print">
        <ol class="breadcrumb mb-0 small">
            <li class="breadcrumb-item"><a href="dashboard_full.php" class="text-decoration-none"><i class="fas fa-home me-1"></i>Dashboard</a></li>
            <li class="breadcrumb-item"><a href="users.php" class="text-decoration-none">User Management</a></li>
            <li class="breadcrumb-item active fw-semibold" aria-current="page">ID Badges, Stock &amp; Venue Attendance</li>
        </ol>
    </nav>

    <!-- Hero Banner -->
    <div class="badges-hero-banner no-print">
        <div class="row align-items-center g-3">
            <div class="col-lg-7">
                <div class="d-flex align-items-center gap-3">
                    <div class="rounded-4 bg-white bg-opacity-10 p-3 d-flex align-items-center justify-content-center" style="width: 60px; height: 60px; border: 1px solid rgba(255,255,255,0.2);">
                        <i class="fas fa-id-card-clip fs-2 text-warning"></i>
                    </div>
                    <div>
                        <span class="badge bg-warning text-dark fw-bold mb-1 px-2 py-1"><i class="fas fa-qrcode me-1"></i>CR80 QR &amp; RFID ACCESS CONTROL</span>
                        <h3 class="fw-bold mb-1">Staff ID Badges &amp; Stock / Venue Attendance</h3>
                        <p class="mb-0 text-white-50 small">Generate printable staff ID badges, configure clearance tiers for Stock Warehouses &amp; Event Venues, and log real-time check-ins.</p>
                    </div>
                </div>
            </div>
            <div class="col-lg-5 text-lg-end">
                <div class="d-flex flex-wrap gap-2 justify-content-lg-end">
                    <a href="venue_scanner.php" target="_blank" class="btn btn-info fw-bold btn-sm px-3 py-2 text-dark">
                        <i class="fas fa-camera me-1"></i>Shareable Venue Camera Scanner
                    </a>
                    <button type="button" class="btn btn-light fw-bold btn-sm px-3 py-2" onclick="syncRoleDefaults()">
                        <i class="fas fa-wand-magic-sparkles text-primary me-1"></i>Sync Access by Role
                    </button>
                    <button type="button" class="btn btn-warning fw-bold btn-sm px-3 py-2 text-dark" onclick="printVisibleBadges()">
                        <i class="fas fa-print me-1"></i>Print All Visible Badges
                    </button>
                    <a href="users.php" class="btn btn-outline-light fw-bold btn-sm px-3 py-2">
                        <i class="fas fa-users me-1"></i>Users Directory
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- 4 KPI Metric Cards -->
    <div class="row g-3 mb-4 no-print">
        <div class="col-sm-6 col-xl-3">
            <div class="kpi-metric-card">
                <div class="kpi-icon-circle bg-primary-subtle text-primary">
                    <i class="fas fa-id-badge"></i>
                </div>
                <div>
                    <div class="text-muted small fw-semibold">Active ID Badges</div>
                    <div class="fs-4 fw-bold text-dark"><?php echo $activeBadgesCount; ?> <span class="fs-6 text-muted fw-normal">/ <?php echo $totalBadges; ?></span></div>
                    <div class="small text-success"><i class="fas fa-check-circle me-1"></i>QR &amp; RFID Encoded</div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="kpi-metric-card">
                <div class="kpi-icon-circle bg-success-subtle text-success">
                    <i class="fas fa-warehouse"></i>
                </div>
                <div class="flex-grow-1 overflow-hidden">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="text-muted small fw-semibold">Stock &amp; Venue Cleared</span>
                        <span class="badge bg-success-subtle text-success border border-success-subtle" style="font-size: 0.65rem;"><?php echo $stockAuthorizedCount; ?> Stock / <?php echo $venueAuthorizedCount; ?> Venue</span>
                    </div>
                    <div class="mt-1" style="font-size: 0.76rem; line-height: 1.35;">
                        <div class="text-truncate" title="Stock (<?php echo htmlspecialchars($stockLabelSummary); ?>)" style="cursor: pointer;" onclick="openTerminalWithToggle('stock_warehouse')">
                            <i class="fas fa-warehouse text-success me-1"></i><strong>Stock:</strong> <span class="text-dark fw-semibold"><?php echo htmlspecialchars($stockLabelSummary); ?></span>
                        </div>
                        <div class="text-truncate mt-1" title="Venue (<?php echo htmlspecialchars($venueLabelSummary); ?>)" style="cursor: pointer;" onclick="openTerminalWithToggle('event_venue')">
                            <i class="fas fa-hotel text-primary me-1"></i><strong>Venue:</strong> <span class="text-dark fw-semibold"><?php echo htmlspecialchars($venueLabelSummary); ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="kpi-metric-card">
                <div class="kpi-icon-circle bg-info-subtle text-info">
                    <i class="fas fa-user-check"></i>
                </div>
                <div>
                    <div class="text-muted small fw-semibold">Currently Checked-In</div>
                    <div class="fs-4 fw-bold text-dark" id="kpiOnSiteCount"><?php echo $onSiteCount; ?> <span class="fs-6 text-muted fw-normal">On-Site</span></div>
                    <div class="small text-primary"><i class="fas fa-broadcast-tower me-1"></i>Stock &amp; Event Venues</div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="kpi-metric-card">
                <div class="kpi-icon-circle bg-danger-subtle text-danger">
                    <i class="fas fa-shield-virus"></i>
                </div>
                <div>
                    <div class="text-muted small fw-semibold">Blocked / Denied Scans</div>
                    <div class="fs-4 fw-bold text-dark" id="kpiDeniedCount"><?php echo $deniedTodayCount; ?></div>
                    <div class="small text-danger"><i class="fas fa-lock me-1"></i>Clearance Enforced</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Navigation Tabs -->
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3 no-print">
        <ul class="nav hub-nav-pills mb-0" id="badgesHubTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link <?php echo ($initialTab === 'gallery') ? 'active' : ''; ?>" id="tab-gallery-btn" data-bs-toggle="pill" data-bs-target="#pane-gallery" type="button" role="tab">
                    <i class="fas fa-id-card me-2"></i>1. User ID Badges (<?php echo $totalBadges; ?>)
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link <?php echo ($initialTab === 'kiosk') ? 'active' : ''; ?>" id="tab-kiosk-btn" data-bs-toggle="pill" data-bs-target="#pane-kiosk" type="button" role="tab">
                    <i class="fas fa-qrcode me-2 text-warning"></i>2. Attendance &amp; Gate Access Terminal
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link <?php echo ($initialTab === 'roster') ? 'active' : ''; ?>" id="tab-roster-btn" data-bs-toggle="pill" data-bs-target="#pane-roster" type="button" role="tab">
                    <i class="fas fa-clipboard-user me-2 text-info"></i>3. Live Roster &amp; Attendance Logs
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link <?php echo ($initialTab === 'matrix') ? 'active' : ''; ?>" id="tab-matrix-btn" data-bs-toggle="pill" data-bs-target="#pane-matrix" type="button" role="tab">
                    <i class="fas fa-sliders me-2 text-success"></i>4. Access Levels &amp; Clearance Matrix
                </button>
            </li>
        </ul>
        <div class="d-flex align-items-center gap-2">
            <button type="button" class="btn btn-sm btn-outline-success fw-bold" onclick="document.getElementById('tab-matrix-btn').click(); openAddAccessLevelModal();">
                <i class="fas fa-plus-circle me-1"></i>New Access Level
            </button>
            <button type="button" class="btn btn-sm btn-outline-primary fw-bold" onclick="document.getElementById('tab-matrix-btn').click();">
                <i class="fas fa-layer-group me-1"></i>Access Levels (CRUD)
            </button>
        </div>
    </div>

    <div class="tab-content" id="badgesHubTabsContent">
        <!-- ==================== TAB 1: ID BADGES GALLERY & CR80 DESIGNER ==================== -->
        <div class="tab-pane fade <?php echo ($initialTab === 'gallery') ? 'show active' : ''; ?>" id="pane-gallery" role="tabpanel">
            <!-- Filter & View Mode Toolbar -->
            <div class="card border-0 shadow-sm rounded-4 mb-4 no-print">
                <div class="card-body p-3">
                    <div class="row g-2 align-items-center">
                        <div class="col-md-3">
                            <div class="input-group input-group-sm">
                                <span class="input-group-text bg-light border-end-0"><i class="fas fa-search text-muted"></i></span>
                                <input type="text" id="badgeSearchInput" class="form-control border-start-0" placeholder="Search name, TAX-ID, ABL-BDG..." oninput="filterBadgeGallery()" value="<?php echo ($focusUserId && isset($usersWithBadges[$focusUserId])) ? htmlspecialchars($usersWithBadges[$focusUserId]['username']) : ''; ?>">
                            </div>
                        </div>
                        <div class="col-md-2">
                            <select id="badgeRoleFilter" class="form-select form-select-sm" onchange="filterBadgeGallery()">
                                <option value="">All Staff Roles</option>
                                <option value="admin">Administrator</option>
                                <option value="manager">Manager</option>
                                <option value="stock_manager">Stock Manager</option>
                                <option value="stock_controller">Stock Controller</option>
                                <option value="tech_lead">Tech Lead</option>
                                <option value="technician">Technician</option>
                                <option value="driver">Driver</option>
                                <option value="user">User / Crew</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <select id="badgeClearanceFilter" class="form-select form-select-sm" onchange="filterBadgeGallery()">
                                <option value="">All Access Clearance Levels</option>
                                <?php foreach ($clearanceMeta as $cKey => $cm): ?>
                                    <option value="<?php echo $cKey; ?>"><?php echo htmlspecialchars($cm['title']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4 text-md-end">
                            <div class="btn-group btn-group-sm" role="group" aria-label="Badge View Mode">
                                <button type="button" class="btn btn-outline-dark active" id="viewBtnFront" onclick="setBadgeGallerySide('front')">
                                    <i class="fas fa-id-badge me-1"></i>Front Side
                                </button>
                                <button type="button" class="btn btn-outline-dark" id="viewBtnBack" onclick="setBadgeGallerySide('back')">
                                    <i class="fas fa-barcode me-1"></i>Back Side
                                </button>
                                <button type="button" class="btn btn-outline-dark" id="viewBtnBoth" onclick="setBadgeGallerySide('both')">
                                    <i class="fas fa-columns me-1"></i>Front &amp; Back
                                </button>
                            </div>
                            <?php if ($focusUserId): ?>
                                <button type="button" class="btn btn-sm btn-link text-decoration-none ms-1" onclick="document.getElementById('badgeSearchInput').value='';filterBadgeGallery();">
                                    Show All
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Printable Badges Grid -->
            <div class="row g-4" id="printableBadgesArea">
                <?php foreach ($usersWithBadges as $uid => $u):
                    $fullName = $u['full_name'] ?: $u['username'];
                    $cKey = $u['clearance_level'] ?: 'L1_VENUE_BASIC';
                    $cInfo = $clearanceMeta[$cKey] ?? $clearanceMeta['L1_VENUE_BASIC'];
                    $rInfo = getRoleAccentMeta($u['role']);
                    $badgeStatus = $u['badge_status'] ?: 'active';
                    $isActiveBadge = ($badgeStatus === 'active' && (int)$u['is_active'] === 1);

                    // Compute authorized Stock names summary (BKAr, KCC)
                    $stockSummary = 'No Warehouse Access';
                    if ($cKey === 'L4_ALL_ACCESS' || $u['stock_access_mode'] === 'all') {
                        $stockSummary = 'BKAr, KCC (All Stock)';
                    } elseif ($u['stock_access_mode'] === 'selected' && !empty($u['allowed_stock_array'])) {
                        $sNames = [];
                        foreach ($u['allowed_stock_array'] as $sid) {
                            if (isset($stockMap[$sid])) {
                                $sNames[] = $stockMap[$sid]['name'];
                            }
                        }
                        $stockSummary = !empty($sNames) ? implode(', ', array_slice($sNames, 0, 2)) : 'BKAr, KCC';
                    }

                    // Compute authorized Venue summary (Marriot Hotel, FourPoint Hotel, MovenPick Hotel..)
                    $venueSummary = 'Marriot, FourPoint, MovenPick..';
                    if ($cKey === 'L4_ALL_ACCESS' || $u['venue_access_mode'] === 'all') {
                        $venueSummary = 'Marriot, FourPoint, MovenPick (All)';
                    } elseif ($u['venue_access_mode'] === 'selected' && !empty($u['allowed_event_array'])) {
                        $eNames = [];
                        foreach ($u['allowed_event_array'] as $eid) {
                            if (isset($eventMap[$eid])) {
                                $eNames[] = $eventMap[$eid]['title'];
                            }
                        }
                        $venueSummary = !empty($eNames) ? implode(', ', array_slice($eNames, 0, 2)) : 'Marriot, FourPoint..';
                    } elseif ($u['venue_access_mode'] === 'none') {
                        $venueSummary = 'Restricted / Office Only';
                    }

                    $qrPayload = $u['badge_code'] . '|' . $u['staff_code'] . '|' . $u['username'] . '|' . $cInfo['code'];
                ?>
                <div class="col-md-6 col-xl-4 badge-item-col"
                     id="badge-card-col-<?php echo $uid; ?>"
                     data-uid="<?php echo $uid; ?>"
                     data-search="<?php echo strtolower(htmlspecialchars($fullName . ' ' . $u['username'] . ' ' . $u['staff_code'] . ' ' . $u['badge_code'] . ' ' . ($u['department'] ?? ''))); ?>"
                     data-role="<?php echo strtolower(htmlspecialchars($u['role'])); ?>"
                     data-clearance="<?php echo htmlspecialchars($cKey); ?>">

                    <div class="cr80-badge-pair">
                        <!-- ==================== FRONT OF CR80 ID BADGE ==================== -->
                        <div class="cr80-card-shell badge-side-front" id="badge-front-<?php echo $uid; ?>">
                            <div>
                                <!-- Top Header with Lanyard Slot & Brand -->
                                <div class="cr80-front-header" style="background: <?php echo $rInfo['header']; ?>;">
                                    <div class="lanyard-slot"></div>
                                    <div class="cr80-brand-row">
                                        <div class="cr80-brand-logo">
                                            <i class="fas fa-cubes-stacked text-warning me-1"></i>aBility
                                        </div>
                                        <span class="cr80-clearance-chip" title="<?php echo htmlspecialchars($cInfo['title']); ?>">
                                            <i class="fas <?php echo $cInfo['icon']; ?> me-1"></i><?php echo $cInfo['code']; ?> ACCESS
                                        </span>
                                    </div>
                                    <div class="text-white-50" style="font-size: 0.65rem; text-align: left; letter-spacing: 0.8px;">
                                        STOCK &amp; VENUE CREDENTIAL
                                    </div>
                                </div>

                                <!-- Avatar -->
                                <div class="cr80-avatar-holder">
                                    <?php if ($u['has_photo']): ?>
                                        <img src="<?php echo htmlspecialchars($u['profile_image']); ?>"
                                             alt="<?php echo htmlspecialchars($fullName); ?>"
                                             class="cr80-avatar-img"
                                             onerror="this.onerror=null;this.src='data:image/svg+xml;utf8,<svg xmlns=%22http://www.w3.org/2000/svg%22 width=%2280%22 height=%2280%22 viewBox=%220 0 80 80%22><rect width=%2280%22 height=%2280%22 fill=%22%231e293b%22/><circle cx=%2240%22 cy=%2230%22 r=%2214%22 fill=%22%2394a3b8%22/><path d=%22M16 72c0-13.3 10.7-24 24-24s24 10.7 24 24%22 fill=%22%2394a3b8%22/></svg>';">
                                    <?php else: ?>
                                        <div class="cr80-avatar-initials" style="background: <?php echo $rInfo['color']; ?>;">
                                            <?php echo strtoupper(substr($fullName, 0, 1)); ?>
                                        </div>
                                    <?php endif; ?>
                                    <span class="cr80-status-dot" style="background: <?php echo $isActiveBadge ? '#10b981' : '#ef4444'; ?>;" title="<?php echo $isActiveBadge ? 'Active Badge' : 'Suspended/Inactive'; ?>"></span>
                                </div>

                                <!-- User Name & Role Banner -->
                                <div class="cr80-user-name text-truncate" title="<?php echo htmlspecialchars($fullName); ?>">
                                    <?php echo htmlspecialchars($fullName); ?>
                                </div>
                                <div class="text-center">
                                    <span class="cr80-role-banner" style="background: <?php echo $rInfo['color']; ?>;">
                                        <?php echo htmlspecialchars($rInfo['label']); ?>
                                    </span>
                                    <div class="text-muted" style="font-size: 0.73rem; font-weight: 600; margin-top: -2px;">
                                        <i class="fas fa-building me-1"></i><?php echo htmlspecialchars(ucwords($u['department'] ?: 'Production & Operations')); ?>
                                    </div>
                                </div>

                                <!-- Access Zones Summary Strip -->
                                <div class="cr80-zone-strip">
                                    <div class="d-flex justify-content-between align-items-center mb-1 pb-1 border-bottom">
                                        <span class="small fw-bold text-uppercase" style="font-size: 0.65rem; color: #64748b;">Clearance Tier</span>
                                        <span class="badge rounded-pill" style="background: <?php echo $cInfo['color']; ?>; font-size: 0.66rem;">
                                            <?php echo htmlspecialchars($cInfo['short']); ?>
                                        </span>
                                    </div>
                                    <div class="d-flex align-items-center gap-2 mb-1" style="font-size: 0.72rem;">
                                        <i class="fas fa-warehouse <?php echo ($u['stock_access_mode'] !== 'none' || $cKey === 'L4_ALL_ACCESS') ? 'text-success' : 'text-danger'; ?>"></i>
                                        <span class="text-muted">Stock:</span>
                                        <span class="fw-bold text-dark text-truncate" style="max-width: 175px;" title="<?php echo htmlspecialchars($stockSummary); ?>">
                                            <?php echo htmlspecialchars($stockSummary); ?>
                                        </span>
                                    </div>
                                    <div class="d-flex align-items-center gap-2" style="font-size: 0.72rem;">
                                        <i class="fas fa-location-dot <?php echo ($u['venue_access_mode'] !== 'none') ? 'text-primary' : 'text-danger'; ?>"></i>
                                        <span class="text-muted">Venues:</span>
                                        <span class="fw-bold text-dark text-truncate" style="max-width: 175px;" title="<?php echo htmlspecialchars($venueSummary); ?>">
                                            <?php echo htmlspecialchars($venueSummary); ?>
                                        </span>
                                    </div>
                                </div>

                                <!-- QR Code + Staff Identifiers -->
                                <div class="cr80-qr-box">
                                    <div class="cr80-qr-render" id="qr-front-<?php echo $uid; ?>" data-qr="<?php echo htmlspecialchars($qrPayload); ?>"></div>
                                    <div class="flex-grow-1 text-start" style="line-height: 1.3;">
                                        <div class="text-muted" style="font-size: 0.63rem; font-weight: 700; text-transform: uppercase;">Badge ID</div>
                                        <div class="fw-bold text-dark" style="font-family: 'JetBrains Mono', monospace; font-size: 0.82rem;">
                                            <?php echo htmlspecialchars($u['badge_code']); ?>
                                        </div>
                                        <div class="text-muted mt-1" style="font-size: 0.63rem; font-weight: 700; text-transform: uppercase;">Staff / Tax Code</div>
                                        <div class="fw-semibold text-secondary" style="font-family: 'JetBrains Mono', monospace; font-size: 0.74rem;">
                                            <?php echo htmlspecialchars($u['staff_code']); ?>
                                        </div>
                                        <div class="mt-1">
                                            <?php if ($isActiveBadge): ?>
                                                <span class="badge bg-success-subtle text-success border border-success-subtle" style="font-size: 0.62rem;">
                                                    <i class="fas fa-check-circle me-1"></i>ACTIVE PASS
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-danger-subtle text-danger border border-danger-subtle" style="font-size: 0.62rem;">
                                                    <i class="fas fa-ban me-1"></i><?php echo strtoupper($badgeStatus); ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Bottom Accent Bar -->
                            <div class="cr80-footer-bar" style="background: <?php echo $cInfo['badge_bg']; ?>;">
                                <span><i class="fas fa-wifi me-1"></i><?php echo htmlspecialchars($u['rfid_uid'] ?: 'RFID-ENABLED'); ?></span>
                                <span>EXP: <?php echo date('m/Y', strtotime($u['expires_at'] ?: '+1 year')); ?></span>
                            </div>
                        </div>

                        <!-- ==================== BACK OF CR80 ID BADGE ==================== -->
                        <div class="cr80-card-shell badge-side-back d-none" id="badge-back-<?php echo $uid; ?>">
                            <div>
                                <div class="p-3 bg-dark text-white">
                                    <div class="lanyard-slot"></div>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span class="fw-bold small"><i class="fas fa-shield-halved text-warning me-1"></i>ACCESS MATRIX</span>
                                        <span class="badge bg-light text-dark" style="font-family: 'JetBrains Mono', monospace; font-size: 0.68rem;">
                                            <?php echo htmlspecialchars($u['badge_code']); ?>
                                        </span>
                                    </div>
                                </div>

                                <div class="p-3" style="font-size: 0.74rem;">
                                    <!-- Stock Warehouses Authorized -->
                                    <div class="mb-2">
                                        <div class="fw-bold text-uppercase text-muted mb-1" style="font-size: 0.65rem;">
                                            <i class="fas fa-warehouse text-success me-1"></i>Stock (BKAr, KCC)
                                        </div>
                                        <div class="p-2 rounded bg-light border">
                                            <?php if ($cKey === 'L4_ALL_ACCESS' || $u['stock_access_mode'] === 'all'): ?>
                                                <span class="badge bg-success-subtle text-success border border-success-subtle me-1">BKAr</span>
                                                <span class="badge bg-success-subtle text-success border border-success-subtle">KCC</span>
                                            <?php elseif ($u['stock_access_mode'] === 'selected' && !empty($u['allowed_stock_array'])): ?>
                                                <?php foreach ($u['allowed_stock_array'] as $sid):
                                                    if (!isset($stockMap[$sid])) continue;
                                                ?>
                                                    <span class="badge bg-white text-dark border me-1 mb-1"><?php echo htmlspecialchars($stockMap[$sid]['name']); ?></span>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <span class="text-danger fw-semibold"><i class="fas fa-lock me-1"></i>No Stock Warehouse Entry</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <!-- Event Venues Authorized -->
                                    <div class="mb-2">
                                        <div class="fw-bold text-uppercase text-muted mb-1" style="font-size: 0.65rem;">
                                            <i class="fas fa-location-dot text-primary me-1"></i>Venue (Marriot, FourPoint, MovenPick..)
                                        </div>
                                        <div class="p-2 rounded bg-light border">
                                            <?php if ($cKey === 'L4_ALL_ACCESS' || $u['venue_access_mode'] === 'all' || $u['venue_access_mode'] === 'assigned_only'): ?>
                                                <span class="badge bg-primary-subtle text-primary border border-primary-subtle me-1 mb-1">Marriot Hotel</span>
                                                <span class="badge bg-primary-subtle text-primary border border-primary-subtle me-1 mb-1">FourPoint Hotel</span>
                                                <span class="badge bg-primary-subtle text-primary border border-primary-subtle mb-1">MovenPick Hotel..</span>
                                            <?php elseif ($u['venue_access_mode'] === 'selected' && !empty($u['allowed_event_array'])): ?>
                                                <?php foreach (array_slice($u['allowed_event_array'], 0, 3) as $eid):
                                                    if (!isset($eventMap[$eid])) continue;
                                                ?>
                                                    <span class="badge bg-white text-dark border me-1 mb-1"><?php echo htmlspecialchars($eventMap[$eid]['title']); ?></span>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <span class="text-danger fw-semibold">No Venue Access</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <!-- Emergency & Vital Info -->
                                    <div class="row g-2 mb-2">
                                        <div class="col-8">
                                            <div class="p-2 rounded bg-light border">
                                                <div class="text-muted" style="font-size: 0.62rem;">EMERGENCY CONTACT</div>
                                                <div class="fw-bold text-dark"><?php echo htmlspecialchars($u['emergency_contact'] ?: '+250 788 000 112'); ?></div>
                                            </div>
                                        </div>
                                        <div class="col-4">
                                            <div class="p-2 rounded bg-light border text-center">
                                                <div class="text-muted" style="font-size: 0.62rem;">BLOOD</div>
                                                <div class="fw-bold text-danger"><?php echo htmlspecialchars($u['blood_group'] ?: 'O+'); ?></div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Holder Signature -->
                                    <div class="p-2 rounded border text-center mb-2" style="background: #fafafa;">
                                        <div class="text-muted mb-1" style="font-size: 0.6rem; text-transform: uppercase;">Authorized Holder Signature</div>
                                        <?php if ($u['has_sig']): ?>
                                            <img src="<?php echo htmlspecialchars($u['signature_image']); ?>" alt="Signature" style="max-height: 32px; max-width: 140px; object-fit: contain;">
                                        <?php else: ?>
                                            <div class="fst-italic text-secondary" style="font-family: Georgia, serif; font-size: 0.9rem;">
                                                <?php echo htmlspecialchars($fullName); ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>

                                    <!-- Return Policy -->
                                    <div class="text-muted text-center" style="font-size: 0.63rem; line-height: 1.3;">
                                        This badge is the property of <strong>aBility Production &amp; Stock System</strong>. Must be worn visibly at all Stock Warehouses and Event Venues.
                                    </div>
                                </div>
                            </div>

                            <div class="cr80-footer-bar bg-dark">
                                <span>ISSUED: <?php echo date('M Y', strtotime($u['issued_at'] ?: 'now')); ?></span>
                                <span>VALID THRU: <?php echo date('M Y', strtotime($u['expires_at'] ?: '+1 year')); ?></span>
                            </div>
                        </div>
                    </div>

                    <!-- Card Action Toolbar (Hidden when printing) -->
                    <div class="d-flex justify-content-center gap-1 mt-2 no-print">
                        <button type="button" class="btn btn-xs btn-outline-dark px-2 py-1" onclick="flipSingleBadge(<?php echo $uid; ?>)" title="Flip Front / Back">
                            <i class="fas fa-retweet me-1"></i>Flip
                        </button>
                        <button type="button" class="btn btn-xs btn-outline-primary px-2 py-1" onclick="openConfigureBadgeModal(<?php echo $uid; ?>)" title="Configure Stock & Venue Access">
                            <i class="fas fa-sliders me-1"></i>Access
                        </button>
                        <button type="button" class="btn btn-xs btn-outline-success px-2 py-1" onclick="simulateGateScan('<?php echo htmlspecialchars($u['badge_code']); ?>')" title="Test Scan in Gate Kiosk">
                            <i class="fas fa-qrcode me-1"></i>Scan
                        </button>
                        <button type="button" class="btn btn-xs btn-outline-secondary px-2 py-1" onclick="printSingleBadge(<?php echo $uid; ?>)" title="Print this Badge (Front & Back)">
                            <i class="fas fa-print"></i>
                        </button>
                        <a href="users.php?id=<?php echo $uid; ?>" class="btn btn-xs btn-outline-info px-2 py-1" title="Open User Profile">
                            <i class="fas fa-user"></i>
                        </a>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- ==================== TAB 2: ATTENDANCE & GATE ACCESS TERMINAL ==================== -->
        <div class="tab-pane fade <?php echo ($initialTab === 'kiosk') ? 'show active' : ''; ?> no-print" id="pane-kiosk" role="tabpanel">
            <!-- Top Terminal Control Card: 3-Step Checkpoint & Mode Selector -->
            <div class="card border-0 shadow-sm rounded-4 mb-4 overflow-hidden" style="background: #0f172a; color: #ffffff;">
                <div class="p-3 px-4">
                    <div class="row g-3 align-items-center">
                        <!-- Step 1: Toggle Checkpoint Type (Stock & Venue) -->
                        <div class="col-md-4">
                            <label class="form-label small text-info fw-bold text-uppercase mb-1">
                                <i class="fas fa-tower-broadcast me-1"></i>1. TOGGLE CHECKPOINT TYPE (STOCK &amp; VENUE)
                            </label>
                            <div class="btn-group w-100" role="group" aria-label="Stock and Venue Toggle">
                                <input type="radio" class="btn-check" name="checkpoint_type" id="cp_type_stock" value="stock_warehouse" <?php echo ($defaultType === 'stock_warehouse') ? 'checked' : ''; ?> onchange="toggleCheckpointDropdown()">
                                <label class="btn btn-outline-success fw-bold btn-sm py-2" for="cp_type_stock" id="btn_label_stock" style="<?php echo ($defaultType === 'stock_warehouse') ? 'background: #16a34a; color: #ffffff; border-color: #16a34a;' : ''; ?>">
                                    <i class="fas fa-warehouse me-1"></i>Stock (<?php echo htmlspecialchars($stockLabelSummary); ?>)
                                </label>

                                <input type="radio" class="btn-check" name="checkpoint_type" id="cp_type_venue" value="event_venue" <?php echo ($defaultType === 'event_venue') ? 'checked' : ''; ?> onchange="toggleCheckpointDropdown()">
                                <label class="btn btn-outline-info fw-bold btn-sm py-2" for="cp_type_venue" id="btn_label_venue" style="<?php echo ($defaultType === 'event_venue') ? 'background: #00d2ff; color: #000000; border-color: #00d2ff;' : ''; ?>">
                                    <i class="fas fa-location-dot me-1"></i>Venue (<?php echo htmlspecialchars($venueLabelSummary); ?>)
                                </label>
                            </div>
                        </div>

                        <!-- Step 2: Active Venue or Stock Warehouse Selector -->
                        <div class="col-md-4">
                            <!-- Event Venue Selector Wrap (Shown when Venue Toggle is active) -->
                            <div id="venueCheckpointSelectWrap" class="<?php echo ($defaultType === 'event_venue') ? '' : 'd-none'; ?>">
                                <label class="form-label small text-white-50 fw-bold text-uppercase mb-1">
                                    2. ACTIVE VENUE (<?php echo htmlspecialchars($venueLabelSummary); ?>)
                                </label>
                                <select class="form-select bg-dark text-white border-secondary fw-semibold" id="kiosk_event_id" onchange="syncKioskStaffButtonStates()">
                                    <?php foreach ($eventsList as $ev): ?>
                                        <option value="<?php echo (int)$ev['id']; ?>" <?php echo ($defaultEventId === (int)$ev['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($ev['title'] . (!empty($ev['location']) ? ' — ' . $ev['location'] : '') . (!empty($ev['venue_room']) ? ' (Room: ' . $ev['venue_room'] . ')' : '') . ' (Calltime: ' . ($ev['call_time'] ?: '08:00') . ')'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- Stock Location Selector Wrap (Shown when Stock Toggle is active) -->
                            <div id="stockCheckpointSelectWrap" class="<?php echo ($defaultType === 'stock_warehouse') ? '' : 'd-none'; ?>">
                                <label class="form-label small text-white-50 fw-bold text-uppercase mb-1">
                                    2. ACTIVE STOCK WAREHOUSE (<?php echo htmlspecialchars($stockLabelSummary); ?>)
                                </label>
                                <select class="form-select bg-dark text-white border-secondary fw-semibold" id="kiosk_stock_id" onchange="syncKioskStaffButtonStates()">
                                    <?php foreach ($stockLocations as $sl): ?>
                                        <option value="<?php echo (int)$sl['id']; ?>" <?php echo ($defaultStockId === (int)$sl['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($sl['name'] . (!empty($sl['code']) ? ' (' . $sl['code'] . ')' : '') . (!empty($sl['location']) ? ' — ' . $sl['location'] : '')); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <!-- Step 3: Scan Action Mode -->
                        <div class="col-md-4">
                            <label class="form-label small text-white-50 fw-bold text-uppercase mb-1">
                                3. SCAN ACTION MODE
                            </label>
                            <select id="kiosk_scan_mode_select" class="form-select bg-dark text-white border-secondary fw-semibold" onchange="syncScanActionMode()">
                                <option value="auto">Auto-Toggle (Check-In on Arrival / Check-Out on Exit)</option>
                                <option value="check_in">Check-In Only (Venue / Stock Arrival)</option>
                                <option value="check_out">Check-Out Only (Departure)</option>
                                <option value="access_verify">Verify Badge Access Only</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Two Columns: Left (col-lg-5) = Search Staff (Checkin -> Checkout) & Badge Scan | Right (col-lg-7) = Live Gate Decision & Feed -->
            <div class="row g-4">
                <!-- Left Column (col-lg-5): Search Staff by Name + Checkin/Checkout & Scanner Input -->
                <div class="col-lg-5">
                    <div class="card border-0 shadow-sm rounded-4 h-100">
                        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center border-bottom">
                            <div>
                                <h5 class="mb-0 fw-bold text-dark">
                                    <i class="fas fa-magnifying-glass text-primary me-2"></i>Search Staff by Name &amp; Badge
                                </h5>
                                <div class="small text-muted">Type name &rarr; Find &rarr; Click <strong>Checkin</strong> (disables) then <strong>Checkout</strong> (disables)</div>
                            </div>
                            <span class="badge bg-dark text-white" id="kioskStaffMatchBadge"><?php echo count($usersWithBadges); ?> Staff</span>
                        </div>

                        <div class="card-body p-3">
                            <!-- Search by Name Input + Find Button -->
                            <form onsubmit="event.preventDefault();filterKioskStaffByName();" class="mb-3">
                                <div class="input-group">
                                    <span class="input-group-text bg-light text-primary"><i class="fas fa-search"></i></span>
                                    <input type="text" id="kioskStaffSearchInput" class="form-control fw-semibold" placeholder="Type staff name, role, or badge (ABL-BDG-0001)..." oninput="filterKioskStaffByName()" autocomplete="off">
                                    <button type="submit" class="btn btn-primary fw-bold px-3">Find</button>
                                    <button type="button" class="btn btn-outline-secondary" onclick="document.getElementById('kioskStaffSearchInput').value='';filterKioskStaffByName();" title="Clear">
                                        <i class="fas fa-xmark"></i>
                                    </button>
                                </div>
                            </form>

                            <!-- Staff List with Stateful Checkin -> Checkout Buttons -->
                            <div class="overflow-auto pe-1" style="max-height: 390px;" id="kioskStaffListContainer">
                                <?php foreach ($usersWithBadges as $uid => $ub):
                                    $fullName = $ub['full_name'] ?: $ub['username'];
                                    $ubInitials = getUserInitials($fullName);
                                    $cKey = $ub['clearance_level'] ?: 'L1_VENUE_BASIC';
                                    $cInfo = $clearanceMeta[$cKey] ?? $clearanceMeta['L1_VENUE_BASIC'];
                                ?>
                                <div class="p-2 mb-2 rounded-3 border bg-white d-flex justify-content-between align-items-center gap-2 kiosk-staff-row"
                                     id="kiosk-staff-row-<?php echo $uid; ?>"
                                     data-uid="<?php echo $uid; ?>"
                                     data-search="<?php echo strtolower(htmlspecialchars($fullName . ' ' . $ub['username'] . ' ' . $ub['badge_code'] . ' ' . $ub['staff_code'] . ' ' . $ub['role'] . ' ' . ($ub['department'] ?? ''))); ?>"
                                     style="cursor: pointer; transition: all 0.2s;"
                                     onclick="handleKioskStaffRowClick(event, <?php echo $uid; ?>, '<?php echo htmlspecialchars($ub['badge_code']); ?>')">
                                    <div class="d-flex align-items-center gap-2 overflow-hidden">
                                        <?php if ($ub['has_photo']): ?>
                                            <img src="<?php echo htmlspecialchars($ub['profile_image']); ?>"
                                                 alt="<?php echo htmlspecialchars($fullName); ?>"
                                                 style="width: 40px; height: 40px; border-radius: 50%; object-fit: cover; flex-shrink: 0; border: 2px solid <?php echo $cInfo['color']; ?>;"
                                                 onerror="this.onerror=null;this.style.display='none';this.nextElementSibling.classList.remove('d-none');">
                                            <div class="rounded-circle d-none text-white d-flex align-items-center justify-content-center fw-bold"
                                                 style="width: 40px; height: 40px; background: <?php echo $cInfo['color']; ?>; flex-shrink: 0; font-size: 0.8rem;">
                                                <?php echo htmlspecialchars($ubInitials); ?>
                                            </div>
                                        <?php else: ?>
                                            <div class="rounded-circle text-white d-flex align-items-center justify-content-center fw-bold"
                                                 style="width: 40px; height: 40px; background: <?php echo $cInfo['color']; ?>; flex-shrink: 0; font-size: 0.8rem;">
                                                <?php echo htmlspecialchars($ubInitials); ?>
                                            </div>
                                        <?php endif; ?>
                                        <div class="text-truncate">
                                            <div class="d-flex align-items-center gap-2">
                                                <span class="fw-bold text-dark text-truncate"><?php echo htmlspecialchars($fullName); ?></span>
                                                <span id="kiosk-status-pill-<?php echo $uid; ?>" class="badge bg-secondary-subtle text-secondary" style="font-size: 0.62rem;">Ready</span>
                                            </div>
                                            <div class="d-flex align-items-center gap-1 mt-1" style="font-size: 0.72rem;">
                                                <span class="badge bg-dark font-monospace"><?php echo htmlspecialchars($ub['badge_code']); ?></span>
                                                <span class="badge" style="background: <?php echo $cInfo['color']; ?>;"><?php echo $cInfo['code']; ?></span>
                                                <span class="text-muted text-truncate"><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $ub['role']))); ?></span>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="d-flex gap-1 flex-shrink-0">
                                        <button type="button"
                                                id="kiosk-btn-checkin-<?php echo $uid; ?>"
                                                class="btn btn-sm btn-success fw-bold px-2 py-1"
                                                style="font-size: 0.76rem;"
                                                onclick="handleKioskStaffAction(<?php echo $uid; ?>, '<?php echo htmlspecialchars($ub['badge_code']); ?>', 'check_in')">
                                            <i class="fas fa-sign-in-alt me-1"></i>Checkin
                                        </button>
                                        <button type="button"
                                                id="kiosk-btn-checkout-<?php echo $uid; ?>"
                                                class="btn btn-sm btn-warning fw-bold text-dark px-2 py-1"
                                                style="font-size: 0.76rem;"
                                                disabled
                                                onclick="handleKioskStaffAction(<?php echo $uid; ?>, '<?php echo htmlspecialchars($ub['badge_code']); ?>', 'check_out')">
                                            <i class="fas fa-sign-out-alt me-1"></i>Checkout
                                        </button>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>

                            <!-- Direct QR / USB Scanner Input Bar -->
                            <form id="gateKioskForm" onsubmit="submitGateScan(event)" class="mt-3 pt-3 border-top">
                                <input type="hidden" id="kiosk_scan_mode" value="auto">
                                <input type="hidden" id="kiosk_scan_notes" value="">
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text bg-dark text-warning"><i class="fas fa-barcode me-1"></i>Scanner</span>
                                    <input type="text" class="form-control fw-bold font-monospace" id="kiosk_scan_input" placeholder="Or scan badge QR / barcode here (ABL-BDG-0001)..." autocomplete="off">
                                    <button type="submit" class="btn btn-dark fw-bold">
                                        <i class="fas fa-bolt me-1 text-warning"></i>Scan Badge
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Right Column (col-lg-7): Live Gate Decision & Recent Scans Feed -->
                <div class="col-lg-7">
                    <div class="card border-0 shadow-sm rounded-4 h-100">
                        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center border-bottom">
                            <div>
                                <h5 class="mb-0 fw-bold text-dark"><i class="fas fa-shield-check text-success me-2"></i>Live Gate Decision</h5>
                                <div class="small text-muted" id="activeCheckpointBannerLabel"><?php echo ($defaultType === 'event_venue' ? 'Active Venue: ' : 'Active Stock Gate: ') . htmlspecialchars($initialCheckpointName); ?></div>
                            </div>
                            <a href="venue_scanner.php" target="_blank" class="btn btn-xs btn-outline-primary fw-bold">
                                <i class="fas fa-up-right-from-square me-1"></i>Full-Screen Kiosk
                            </a>
                        </div>

                        <div class="card-body p-4">
                            <?php if ($initialKioskUser):
                                $initName = $initialKioskUser['full_name'] ?: $initialKioskUser['username'];
                                $initInitials = getUserInitials($initName);
                                $initRole = ucwords(str_replace('_', ' ', $initialKioskUser['role'] ?: 'Crew'));
                                $initDept = strtoupper($initialKioskUser['department'] ?: 'Video');
                                $initRoleDeptTag = strtoupper($initRole) . ($initDept ? ' - ' . $initDept : '');
                                $initCKey = $initialKioskUser['clearance_level'] ?: 'L2_TECH_STAGE';
                                $initCMeta = $clearanceMeta[$initCKey] ?? $clearanceMeta['L2_TECH_STAGE'];
                                $initCCode = $initCMeta['code'] ?? 'L2';
                                $initParts = preg_split('/[\x{2013}\x{2014}\-]/u', $initCMeta['title'] ?? '');
                                $initCTitle = !empty($initParts) ? trim(end($initParts)) : 'Technical & Stage';
                                $initHasPhoto = !empty($initialKioskUser['has_photo']);
                                $initPhotoUrl = $initialKioskUser['profile_image'] ?? '';
                                $initBadgeCode = $initialKioskUser['badge_code'] ?? 'ABL-BDG-0006';
                            ?>
                            <div id="kioskResultPlaceholder" class="p-4 rounded-4 border bg-light text-center mb-4 d-none">
                                <i class="fas fa-id-card-clip fa-2x text-secondary mb-2"></i>
                                <h6 class="fw-bold text-dark mb-1">Ready for Stock or Venue Checkin / Checkout</h6>
                                <p class="text-muted small mb-0">Select a checkpoint above, then click Checkin or Checkout next to any staff member.</p>
                            </div>

                            <div id="kioskResultActiveCard" class="rounded-4 overflow-hidden mb-3 shadow-lg border-0 text-start" style="background: #ffffff;">
                                <div class="row g-0">
                                    <!-- Left: Portrait Photo with Role Pill at Bottom -->
                                    <div class="col-md-5 position-relative" style="background: #e2e8f0; min-height: 290px;">
                                        <?php if ($initHasPhoto): ?>
                                            <img src="<?php echo htmlspecialchars($initPhotoUrl); ?>"
                                                 alt="<?php echo htmlspecialchars($initName); ?>"
                                                 style="width: 100%; height: 100%; min-height: 290px; max-height: 330px; object-fit: cover; object-position: center top; display: block;"
                                                 onerror="this.onerror=null;this.style.display='none';this.nextElementSibling.classList.remove('d-none');">
                                        <?php endif; ?>
                                        <div class="<?php echo $initHasPhoto ? 'd-none' : ''; ?> d-flex flex-column align-items-center justify-content-center w-100 h-100"
                                             style="min-height: 290px; background: linear-gradient(135deg, #0f172a 0%, #1e3a8a 100%);">
                                            <div class="rounded-circle d-inline-flex align-items-center justify-content-center fw-bold text-white shadow"
                                                 style="width: 96px; height: 96px; background: rgba(255,255,255,0.18); border: 3px solid rgba(255,255,255,0.4); font-size: 2.3rem; letter-spacing: 1px;">
                                                <?php echo htmlspecialchars($initInitials); ?>
                                            </div>
                                        </div>
                                        <!-- Bottom Cyan Role Pill -->
                                        <div class="position-absolute bottom-0 start-50 translate-middle-x mb-3 text-nowrap px-2" style="z-index: 3; max-width: 90%;">
                                            <span class="badge shadow text-truncate d-inline-block"
                                                  style="background: #2998d8; color: #ffffff; font-weight: 800; font-size: 0.82rem; padding: 7px 18px; border-radius: 20px; letter-spacing: 0.8px; max-width: 100%;">
                                                <?php echo htmlspecialchars($initRoleDeptTag); ?>
                                            </span>
                                        </div>
                                    </div>

                                    <!-- Right: Blue Header with Name + L2 Circle, Middle with Clearance Pill, Status Circle, Big Pill, In/Out Times & Footer -->
                                    <div class="col-md-7 d-flex flex-column justify-content-between" style="background: #ffffff;">
                                        <!-- Top Navy Blue Name Header with Clearance Code Circle Badge (Image 2) -->
                                        <div class="px-4 py-3 d-flex justify-content-between align-items-center" style="background: #17487a;">
                                            <h3 class="fw-bold mb-0 text-white" style="letter-spacing: 0.5px; font-size: 1.65rem; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;">
                                                <?php echo htmlspecialchars($initName); ?>
                                            </h3>
                                            <div class="rounded-circle d-flex align-items-center justify-content-center fw-bold shadow-sm"
                                                 style="width: 36px; height: 36px; background: #ffffff; color: #0f172a; font-size: 0.95rem; font-weight: 800; flex-shrink: 0;"
                                                 title="Clearance Level: <?php echo htmlspecialchars($initCCode); ?>">
                                                <?php echo htmlspecialchars($initCCode); ?>
                                            </div>
                                        </div>

                                        <!-- Middle Section: Clearance Pill + Circle Icon + ACCESS GRANTED + Timestamps -->
                                        <div class="p-3 px-4 position-relative d-flex flex-column align-items-center justify-content-center flex-grow-1">
                                            <!-- Top-Right Clearance Tag (Title only, Image 2 style) -->
                                            <div class="position-absolute top-0 end-0 p-3">
                                                <span class="badge" style="background: #8fa4b8; color: #0f172a; font-size: 0.84rem; font-weight: 800; border-radius: 14px; padding: 6px 16px; letter-spacing: 0.3px;">
                                                    <?php echo htmlspecialchars($initCTitle); ?>
                                                </span>
                                            </div>

                                            <!-- Big Status Ring Icon -->
                                            <div class="mt-2 mb-2">
                                                <div class="rounded-circle d-flex align-items-center justify-content-center shadow-sm"
                                                     style="width: 74px; height: 74px; border: 4.5px solid #22c55e; background: #ffffff;">
                                                    <i class="fas fa-check" style="font-size: 2.2rem; color: #22c55e;"></i>
                                                </div>
                                            </div>

                                            <!-- Big Status Action Button / Pill -->
                                            <div class="my-2">
                                                <span class="badge shadow-sm"
                                                      style="background: #16a34a; color: #ffffff; font-size: 1.18rem; font-weight: 800; border-radius: 10px; padding: 9px 28px; letter-spacing: 0.8px;">
                                                    ACCESS GRANTED
                                                </span>
                                            </div>

                                            <!-- Inbound (Check-in) and Outbound (Check-out) Timestamps (Image 2) -->
                                            <div class="w-100 px-3 mt-3 d-flex justify-content-between align-items-center">
                                                <!-- Check-in Time & Quick Action Trigger -->
                                                <div class="d-flex align-items-center gate-time-action gate-checkin-action"
                                                     onclick="handleKioskStaffAction(<?php echo (int)$initialKioskUser['id']; ?>, '<?php echo htmlspecialchars($initBadgeCode); ?>', 'check_in')"
                                                     style="cursor: pointer; user-select: none;"
                                                     title="Check In (<?php echo htmlspecialchars($initInTime); ?>) — Click to check in staff">
                                                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#22c55e" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round" class="me-2" style="flex-shrink: 0;">
                                                        <path d="M10 4.5A8 8 0 1 1 10 19.5"></path>
                                                        <polyline points="10 8 14 12 10 16"></polyline>
                                                        <line x1="2" y1="12" x2="14" y2="12"></line>
                                                    </svg>
                                                    <span class="fw-bold" style="color: #0f172a; font-size: 1.05rem; font-weight: 800; letter-spacing: 0.3px;">
                                                        <?php echo htmlspecialchars($initInTime); ?>
                                                    </span>
                                                </div>

                                                <!-- Check-out Time & Quick Action Trigger -->
                                                <div class="d-flex align-items-center gate-time-action gate-checkout-action"
                                                     onclick="handleKioskStaffAction(<?php echo (int)$initialKioskUser['id']; ?>, '<?php echo htmlspecialchars($initBadgeCode); ?>', 'check_out')"
                                                     style="cursor: pointer; user-select: none;"
                                                     title="Check Out (<?php echo htmlspecialchars($initOutTime); ?>) — Click to check out staff">
                                                    <span class="fw-bold me-2" style="color: #0f172a; font-size: 1.05rem; font-weight: 800; letter-spacing: 0.3px;">
                                                        <?php echo htmlspecialchars($initOutTime); ?>
                                                    </span>
                                                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#ef4444" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink: 0;">
                                                        <path d="M14 4.5A8 8 0 1 0 14 19.5"></path>
                                                        <polyline points="14 8 18 12 14 16"></polyline>
                                                        <line x1="6" y1="12" x2="18" y2="12"></line>
                                                    </svg>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Bottom Footer Bar: Badge # and Clearance Location (Image 2) -->
                                        <div class="px-4 py-3 d-flex flex-wrap justify-content-between align-items-center border-top"
                                             style="background: #edf2f7; border-color: #e2e8f0 !important; font-size: 0.88rem;">
                                            <div>
                                                <strong style="color: #0f2e5a; font-family: monospace; letter-spacing: 0.8px; font-size: 0.95rem;">
                                                    BADGE &nbsp;#:&nbsp; <?php echo htmlspecialchars($initBadgeCode); ?>
                                                </strong>
                                            </div>
                                            <div class="d-flex align-items-center gap-1">
                                                <i class="fas fa-map-marker-alt" style="color: #b45309;"></i>
                                                <span id="kioskCardLocationFooter" class="fst-italic text-secondary" style="font-size: 0.84rem;">
                                                    <?php echo htmlspecialchars($initialCheckpointName); ?> Clearance - Verified.
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php else: ?>
                            <div id="kioskResultPlaceholder" class="p-4 rounded-4 border bg-light text-center mb-4">
                                <i class="fas fa-id-card-clip fa-2x text-secondary mb-2"></i>
                                <h6 class="fw-bold text-dark mb-1">Ready for Stock or Venue Checkin / Checkout</h6>
                                <p class="text-muted small mb-0">Select a checkpoint above, then click Checkin or Checkout next to any staff member.</p>
                            </div>
                            <div id="kioskResultActiveCard" class="rounded-4 overflow-hidden shadow-sm mb-4 d-none"></div>
                            <?php endif; ?>

                            <h6 class="fw-bold text-uppercase small text-muted mb-2">
                                <i class="fas fa-clock-rotate-left me-1"></i>Recent Stock &amp; Venue Gate Scans
                            </h6>
                            <div id="kioskRecentScansFeed" class="overflow-auto" style="max-height: 230px;">
                                <?php foreach (array_slice($attendanceLogs, 0, 8) as $rLog):
                                    $rName = $rLog['full_name'] ?: ($rLog['username'] ?: 'Staff');
                                    $rInitials = getUserInitials($rName);
                                    $rUid = (int)($rLog['user_id'] ?? 0);
                                    $rUserObj = $usersWithBadges[$rUid] ?? null;
                                    $rHasPhoto = $rUserObj && !empty($rUserObj['has_photo']);
                                    $rPhotoUrl = $rHasPhoto ? $rUserObj['profile_image'] : '';
                                    $rColor = $rUserObj ? ($clearanceMeta[$rUserObj['clearance_level']]['color'] ?? '#3b82f6') : '#3b82f6';
                                ?>
                                    <div class="p-2 rounded-3 border mb-2 small d-flex justify-content-between align-items-center"
                                         style="border-left: 4px solid <?php echo ($rLog['access_status'] === 'granted') ? '#10b981' : '#ef4444'; ?> !important;">
                                        <div class="d-flex align-items-center gap-2 overflow-hidden">
                                            <?php if ($rHasPhoto): ?>
                                                <img src="<?php echo htmlspecialchars($rPhotoUrl); ?>"
                                                     alt="<?php echo htmlspecialchars($rName); ?>"
                                                     style="width: 34px; height: 34px; border-radius: 50%; object-fit: cover; flex-shrink: 0; border: 1.5px solid <?php echo $rColor; ?>;"
                                                     onerror="this.onerror=null;this.style.display='none';this.nextElementSibling.classList.remove('d-none');">
                                                <div class="rounded-circle d-none text-white d-flex align-items-center justify-content-center fw-bold"
                                                     style="width: 34px; height: 34px; background: <?php echo $rColor; ?>; flex-shrink: 0; font-size: 0.72rem;">
                                                    <?php echo htmlspecialchars($rInitials); ?>
                                                </div>
                                            <?php else: ?>
                                                <div class="rounded-circle text-white d-flex align-items-center justify-content-center fw-bold"
                                                     style="width: 34px; height: 34px; background: <?php echo $rColor; ?>; flex-shrink: 0; font-size: 0.72rem;">
                                                    <?php echo htmlspecialchars($rInitials); ?>
                                                </div>
                                            <?php endif; ?>
                                            <div class="text-truncate">
                                                <div class="fw-bold text-dark text-truncate"><?php echo htmlspecialchars($rName); ?></div>
                                                <div class="text-muted text-truncate" style="font-size: 0.72rem;">
                                                    <?php if ($rLog['checkpoint_type'] === 'stock_warehouse'): ?>
                                                        <span class="text-success fw-semibold">[Stock]</span>
                                                    <?php else: ?>
                                                        <span class="text-primary fw-semibold">[Venue]</span>
                                                    <?php endif; ?>
                                                    <?php echo htmlspecialchars($rLog['checkpoint_name']); ?>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="text-end flex-shrink-0 ms-2">
                                            <?php if ($rLog['access_status'] === 'granted'): ?>
                                                <span class="badge bg-success"><?php echo strtoupper(str_replace('_', ' ', $rLog['scan_action'])); ?></span>
                                            <?php else: ?>
                                                <span class="badge bg-danger">DENIED</span>
                                            <?php endif; ?>
                                            <div class="text-muted" style="font-size: 0.68rem;"><?php echo date('h:i A', strtotime($rLog['scanned_at'])); ?></div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ==================== TAB 3: LIVE ON-SITE ROSTER & ATTENDANCE LOGS ==================== -->
        <div class="tab-pane fade <?php echo ($initialTab === 'roster') ? 'show active' : ''; ?> no-print" id="pane-roster" role="tabpanel">
            <!-- Currently Checked-In Personnel -->
            <div class="card border-0 shadow-sm rounded-4 mb-4">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center border-bottom">
                    <div>
                        <h5 class="mb-0 fw-bold text-dark"><i class="fas fa-broadcast-tower text-success me-2"></i>Currently Checked-In Personnel (Live On-Site Roster)</h5>
                        <div class="small text-muted">Staff actively inside Stock Warehouses or Event Venues right now</div>
                    </div>
                    <span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-2">
                        <i class="fas fa-users me-1"></i><?php echo count($onSiteRoster); ?> Active On-Site
                    </span>
                </div>
                <div class="card-body p-3">
                    <?php if (!empty($onSiteRoster)): ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Staff Member</th>
                                        <th>Badge &amp; Clearance</th>
                                        <th>Active Location (Stock / Venue)</th>
                                        <th>Check-In Time</th>
                                        <th>Punctuality</th>
                                        <th class="text-end">Quick Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($onSiteRoster as $os):
                                        $cInfo = $clearanceMeta[$os['clearance_level'] ?? 'L1_VENUE_BASIC'] ?? $clearanceMeta['L1_VENUE_BASIC'];
                                    ?>
                                    <tr>
                                        <td>
                                            <div class="fw-bold text-dark"><?php echo htmlspecialchars($os['full_name'] ?: $os['username']); ?></div>
                                            <div class="small text-muted"><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $os['role'] ?? 'Staff'))); ?></div>
                                        </td>
                                        <td>
                                            <span class="badge bg-dark font-monospace me-1"><?php echo htmlspecialchars($os['badge_code']); ?></span>
                                            <span class="badge" style="background: <?php echo $cInfo['color']; ?>;"><?php echo $cInfo['code']; ?></span>
                                        </td>
                                        <td>
                                            <?php if ($os['checkpoint_type'] === 'stock_warehouse'): ?>
                                                <span class="badge bg-success-subtle text-success me-1"><i class="fas fa-warehouse me-1"></i>Stock</span>
                                            <?php else: ?>
                                                <span class="badge bg-primary-subtle text-primary me-1"><i class="fas fa-location-dot me-1"></i>Venue</span>
                                            <?php endif; ?>
                                            <span class="fw-semibold text-dark"><?php echo htmlspecialchars($os['checkpoint_name']); ?></span>
                                        </td>
                                        <td>
                                            <div class="fw-semibold text-dark"><?php echo date('h:i A', strtotime($os['scanned_at'])); ?></div>
                                            <div class="small text-muted"><?php echo date('M d, Y', strtotime($os['scanned_at'])); ?></div>
                                        </td>
                                        <td>
                                            <?php if ($os['punctuality_status'] === 'late'): ?>
                                                <span class="badge bg-warning-subtle text-warning border border-warning-subtle">Late Arrival</span>
                                            <?php else: ?>
                                                <span class="badge bg-success-subtle text-success border border-success-subtle">On Time</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end">
                                            <button type="button" class="btn btn-xs btn-outline-danger fw-bold px-2 py-1"
                                                    onclick="quickCheckOutUser('<?php echo htmlspecialchars($os['badge_code']); ?>', '<?php echo htmlspecialchars($os['checkpoint_type']); ?>', <?php echo (int)$os['checkpoint_id']; ?>)">
                                                <i class="fas fa-sign-out-alt me-1"></i>Check Out
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4 text-muted">
                            <i class="fas fa-door-open fa-2x mb-2 opacity-50"></i>
                            <p class="mb-0">No staff members are currently checked in. Use the Gate Scanner Kiosk to check staff in.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Complete Attendance & Access Audit Logs -->
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-header bg-white py-3 d-flex flex-wrap justify-content-between align-items-center gap-2 border-bottom">
                    <div>
                        <h5 class="mb-0 fw-bold text-dark"><i class="fas fa-list-check text-primary me-2"></i>Attendance &amp; Gate Access Audit Log</h5>
                        <div class="small text-muted">Historical record of all Stock Warehouse &amp; Event Venue badge scans</div>
                    </div>
                </div>
                <div class="card-body p-3">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle" id="attendanceLogsTable">
                            <thead class="table-light">
                                <tr>
                                    <th>Timestamp</th>
                                    <th>Staff Member</th>
                                    <th>Badge Code</th>
                                    <th>Checkpoint (Stock / Venue)</th>
                                    <th>Action</th>
                                    <th>Access Decision</th>
                                    <th>Notes / Verification Reason</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($attendanceLogs as $log): ?>
                                <tr>
                                    <td class="small">
                                        <div class="fw-bold text-dark"><?php echo date('M d, Y', strtotime($log['scanned_at'])); ?></div>
                                        <div class="text-muted"><?php echo date('h:i:s A', strtotime($log['scanned_at'])); ?></div>
                                    </td>
                                    <td>
                                        <div class="fw-bold text-dark"><?php echo htmlspecialchars($log['full_name'] ?: ($log['username'] ?: 'User #' . $log['user_id'])); ?></div>
                                        <div class="small text-muted"><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $log['role'] ?? ''))); ?></div>
                                    </td>
                                    <td>
                                        <span class="badge bg-light text-dark border font-monospace"><?php echo htmlspecialchars($log['badge_code']); ?></span>
                                    </td>
                                    <td>
                                        <?php if ($log['checkpoint_type'] === 'stock_warehouse'): ?>
                                            <span class="badge bg-success-subtle text-success me-1"><i class="fas fa-warehouse me-1"></i>Stock</span>
                                        <?php elseif ($log['checkpoint_type'] === 'event_venue'): ?>
                                            <span class="badge bg-primary-subtle text-primary me-1"><i class="fas fa-location-dot me-1"></i>Venue</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary-subtle text-secondary me-1"><i class="fas fa-building me-1"></i>Office</span>
                                        <?php endif; ?>
                                        <span class="fw-semibold"><?php echo htmlspecialchars($log['checkpoint_name']); ?></span>
                                    </td>
                                    <td>
                                        <?php if ($log['scan_action'] === 'check_in'): ?>
                                            <span class="badge bg-info-subtle text-info border border-info-subtle"><i class="fas fa-arrow-right-to-bracket me-1"></i>Check-In</span>
                                        <?php elseif ($log['scan_action'] === 'check_out'): ?>
                                            <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle"><i class="fas fa-arrow-right-from-bracket me-1"></i>Check-Out</span>
                                        <?php else: ?>
                                            <span class="badge bg-light text-dark border">Verify Only</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($log['access_status'] === 'granted'): ?>
                                            <span class="badge bg-success"><i class="fas fa-check me-1"></i>GRANTED</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger"><i class="fas fa-ban me-1"></i>DENIED</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="small text-muted">
                                        <?php echo htmlspecialchars($log['denial_reason'] ?: ($log['notes'] ?: 'Verified')); ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- ==================== TAB 4: ACCESS LEVELS & CLEARANCE MATRIX ==================== -->
        <div class="tab-pane fade <?php echo ($initialTab === 'matrix') ? 'show active' : ''; ?> no-print" id="pane-matrix" role="tabpanel">
            <!-- Access Clearance Levels Management Card (CRUD) -->
            <div class="card border-0 shadow-sm rounded-4 mb-4">
                <div class="card-header bg-white py-3 d-flex flex-wrap justify-content-between align-items-center gap-2 border-bottom">
                    <div>
                        <h5 class="mb-0 fw-bold text-dark"><i class="fas fa-layer-group text-primary me-2"></i>Access Clearance Levels Management</h5>
                        <div class="small text-muted">Create, edit, and configure clearance tiers, custom colors, badge icons, and default Stock &amp; Venue scopes</div>
                    </div>
                    <button type="button" class="btn btn-sm btn-success fw-bold px-3" onclick="openAddAccessLevelModal()">
                        <i class="fas fa-plus-circle me-1"></i>Add New Clearance Level
                    </button>
                </div>
                <div class="card-body p-3">
                    <div class="row g-3" id="clearanceLevelsGrid">
                        <?php foreach ($clearanceMeta as $cKey => $cm):
                            $levelId = (int)($cm['id'] ?? 0);
                            $staffCount = 0;
                            foreach ($usersWithBadges as $u) {
                                if (($u['clearance_level'] ?: 'L1_VENUE_BASIC') === $cKey) {
                                    $staffCount++;
                                }
                            }
                        ?>
                        <div class="col-md-6 col-lg-3" id="clearance-card-<?php echo $levelId; ?>">
                            <div class="card h-100 border shadow-sm rounded-4 overflow-hidden" style="border-top: 4px solid <?php echo $cm['color']; ?> !important;">
                                <div class="card-body p-3 d-flex flex-column justify-content-between">
                                    <div>
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <div class="rounded-circle d-flex align-items-center justify-content-center fw-bold shadow-sm"
                                                 style="width: 38px; height: 38px; background: <?php echo $cm['color']; ?>; color: #ffffff; font-size: 0.95rem; font-weight: 800;">
                                                <?php echo htmlspecialchars($cm['code']); ?>
                                            </div>
                                            <span class="badge font-monospace text-secondary bg-light border" style="font-size: 0.72rem;">
                                                <?php echo htmlspecialchars($cKey); ?>
                                            </span>
                                        </div>
                                        <h6 class="fw-bold text-dark mb-1"><?php echo htmlspecialchars($cm['title']); ?></h6>
                                        <div class="small fw-semibold text-muted mb-2">
                                            <i class="fas <?php echo $cm['icon'] ?: 'fa-shield-halved'; ?> me-1" style="color: <?php echo $cm['color']; ?>;"></i>
                                            <?php echo htmlspecialchars($cm['short'] ?? ''); ?>
                                        </div>
                                        <p class="small text-muted mb-3" style="font-size: 0.78rem; min-height: 42px;">
                                            <?php echo htmlspecialchars($cm['description'] ?? ''); ?>
                                        </p>

                                        <div class="d-flex flex-column gap-1 mb-3" style="font-size: 0.74rem;">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <span class="text-muted"><i class="fas fa-warehouse me-1 text-success"></i>Stock Scope:</span>
                                                <span class="badge bg-light text-dark border"><?php echo ucfirst($cm['default_stock_access'] ?? 'none'); ?></span>
                                            </div>
                                            <div class="d-flex justify-content-between align-items-center">
                                                <span class="text-muted"><i class="fas fa-location-dot me-1 text-primary"></i>Venue Scope:</span>
                                                <span class="badge bg-light text-dark border"><?php echo ucfirst(str_replace('_', ' ', $cm['default_venue_access'] ?? 'assigned_only')); ?></span>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="pt-2 border-top d-flex justify-content-between align-items-center">
                                        <span class="badge bg-secondary-subtle text-secondary" style="font-size: 0.72rem;">
                                            <i class="fas fa-users me-1"></i><?php echo $staffCount; ?> Staff
                                        </span>
                                        <div class="btn-group btn-group-sm">
                                            <button type="button" class="btn btn-outline-primary btn-xs px-2 py-1" onclick="openEditAccessLevelModal(<?php echo $levelId; ?>)" title="Edit Clearance Level">
                                                <i class="fas fa-edit me-1"></i>Edit
                                            </button>
                                            <button type="button" class="btn btn-outline-danger btn-xs px-2 py-1" onclick="confirmDeleteAccessLevel(<?php echo $levelId; ?>, '<?php echo htmlspecialchars($cKey); ?>', '<?php echo htmlspecialchars($cm['title']); ?>', <?php echo $staffCount; ?>)" title="Delete Clearance Level">
                                                <i class="fas fa-trash-alt"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Staff Access Clearance Matrix Table Card -->
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center border-bottom">
                    <div>
                        <h5 class="mb-0 fw-bold text-dark"><i class="fas fa-shield-halved text-success me-2"></i>Staff Access Clearance Matrix (Stock &amp; Venues)</h5>
                        <div class="small text-muted">Overview of every staff member's badge code, RFID tag, Stock Warehouse permissions, and Event Venue rules</div>
                    </div>
                    <button type="button" class="btn btn-sm btn-primary fw-bold" onclick="syncRoleDefaults()">
                        <i class="fas fa-sync-alt me-1"></i>Reset All to Role Defaults
                    </button>
                </div>
                <div class="card-body p-3">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>Staff Member</th>
                                    <th>Role &amp; Dept</th>
                                    <th>Badge Code / RFID</th>
                                    <th>Clearance Level</th>
                                    <th>Stock Warehouse Access</th>
                                    <th>Event Venue Access</th>
                                    <th>Badge Status</th>
                                    <th class="text-end">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($usersWithBadges as $uid => $u):
                                    $cKey = $u['clearance_level'] ?: 'L1_VENUE_BASIC';
                                    $cInfo = $clearanceMeta[$cKey] ?? $clearanceMeta['L1_VENUE_BASIC'];
                                ?>
                                <tr>
                                    <td>
                                        <div class="fw-bold text-dark"><?php echo htmlspecialchars($u['full_name'] ?: $u['username']); ?></div>
                                        <div class="small text-muted font-monospace"><?php echo htmlspecialchars($u['staff_code']); ?></div>
                                    </td>
                                    <td>
                                        <span class="badge bg-light text-dark border"><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $u['role']))); ?></span>
                                        <div class="small text-muted"><?php echo htmlspecialchars($u['department'] ?: 'Operations'); ?></div>
                                    </td>
                                    <td>
                                        <div class="fw-bold font-monospace text-dark"><?php echo htmlspecialchars($u['badge_code']); ?></div>
                                        <div class="small text-muted font-monospace"><?php echo htmlspecialchars($u['rfid_uid']); ?></div>
                                    </td>
                                    <td>
                                        <span class="badge px-2 py-1" style="background: <?php echo $cInfo['color']; ?>;">
                                            <i class="fas <?php echo $cInfo['icon']; ?> me-1"></i><?php echo htmlspecialchars($cInfo['title']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($cKey === 'L4_ALL_ACCESS' || $u['stock_access_mode'] === 'all'): ?>
                                            <span class="badge bg-success-subtle text-success border border-success-subtle">All Warehouses</span>
                                        <?php elseif ($u['stock_access_mode'] === 'selected'): ?>
                                            <span class="badge bg-info-subtle text-info border border-info-subtle"><?php echo count($u['allowed_stock_array']); ?> Selected Warehouse(s)</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger-subtle text-danger border border-danger-subtle">No Stock Access</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($cKey === 'L4_ALL_ACCESS' || $u['venue_access_mode'] === 'all'): ?>
                                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle">All Event Venues</span>
                                        <?php elseif ($u['venue_access_mode'] === 'assigned_only'): ?>
                                            <span class="badge bg-warning-subtle text-dark border border-warning-subtle">Assigned Events Only</span>
                                        <?php elseif ($u['venue_access_mode'] === 'selected'): ?>
                                            <span class="badge bg-info-subtle text-info border border-info-subtle"><?php echo count($u['allowed_event_array']); ?> Selected Venue(s)</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary-subtle text-secondary">None</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($u['badge_status'] === 'active' && (int)$u['is_active'] === 1): ?>
                                            <span class="badge bg-success">Active</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger"><?php echo ucfirst($u['badge_status']); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end">
                                        <button type="button" class="btn btn-xs btn-primary" onclick="openConfigureBadgeModal(<?php echo $uid; ?>)">
                                            <i class="fas fa-sliders me-1"></i>Configure
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ==================== MODAL: CONFIGURE USER BADGE & STOCK/VENUE ACCESS ==================== -->
<div class="modal fade no-print" id="configureBadgeModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title fw-bold">
                    <i class="fas fa-id-card-clip text-warning me-2"></i>Configure Badge &amp; Stock / Venue Clearance
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="configureBadgeForm" onsubmit="saveBadgeConfiguration(event)">
                <input type="hidden" name="ajax_action" value="save_badge_config">
                <input type="hidden" name="user_id" id="cfg_user_id" value="">

                <div class="modal-body p-4">
                    <div class="d-flex align-items-center justify-content-between p-3 rounded-3 bg-light border mb-3">
                        <div>
                            <div class="fw-bold fs-5 text-dark" id="cfg_user_title">Staff Member</div>
                            <div class="small text-muted" id="cfg_user_subtitle">Role &amp; Badge Code</div>
                        </div>
                        <span class="badge bg-dark fs-6 font-monospace" id="cfg_badge_code_pill">ABL-BDG-0001</span>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold small">Clearance Level Tier</label>
                            <select class="form-select" name="clearance_level" id="cfg_clearance_level">
                                <?php foreach ($clearanceMeta as $cKey => $cm): ?>
                                    <option value="<?php echo $cKey; ?>"><?php echo htmlspecialchars($cm['title']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small">Badge Status</label>
                            <select class="form-select" name="badge_status" id="cfg_badge_status">
                                <option value="active">Active (Authorized for Gate Scans)</option>
                                <option value="suspended">Suspended (Temporarily Blocked)</option>
                                <option value="revoked">Revoked (Lost / Terminated)</option>
                                <option value="expired">Expired</option>
                            </select>
                        </div>

                        <!-- Stock Warehouse Access Configuration -->
                        <div class="col-md-6">
                            <div class="p-3 rounded-3 border h-100">
                                <label class="form-label fw-bold small text-success"><i class="fas fa-warehouse me-1"></i>Stock Location Access Mode</label>
                                <select class="form-select form-select-sm mb-2" name="stock_access_mode" id="cfg_stock_access_mode">
                                    <option value="all">All Stock Warehouses</option>
                                    <option value="selected">Selected Stock Locations Only</option>
                                    <option value="none">No Stock Warehouse Access</option>
                                </select>
                                <div class="small text-muted mb-1">Authorized Stock Warehouses:</div>
                                <div class="overflow-auto" style="max-height: 130px;">
                                    <?php foreach ($stockLocations as $sl): ?>
                                        <div class="form-check small">
                                            <input class="form-check-input cfg-stock-chk" type="checkbox" name="allowed_stock_ids[]" value="<?php echo (int)$sl['id']; ?>" id="chk_stock_<?php echo (int)$sl['id']; ?>">
                                            <label class="form-check-label" for="chk_stock_<?php echo (int)$sl['id']; ?>">
                                                <?php echo htmlspecialchars($sl['name']); ?>
                                            </label>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Event Venue Access Configuration -->
                        <div class="col-md-6">
                            <div class="p-3 rounded-3 border h-100">
                                <label class="form-label fw-bold small text-primary"><i class="fas fa-location-dot me-1"></i>Event Venue Access Mode</label>
                                <select class="form-select form-select-sm mb-2" name="venue_access_mode" id="cfg_venue_access_mode">
                                    <option value="all">All Event Venues &amp; Stages</option>
                                    <option value="assigned_only">Assigned Event Venues &amp; Calltimes</option>
                                    <option value="selected">Explicitly Selected Venues</option>
                                    <option value="none">No Event Venue Access</option>
                                </select>
                                <div class="small text-muted mb-1">Authorized Event Venues:</div>
                                <div class="overflow-auto" style="max-height: 130px;">
                                    <?php foreach ($eventsList as $ev): ?>
                                        <div class="form-check small">
                                            <input class="form-check-input cfg-event-chk" type="checkbox" name="allowed_event_ids[]" value="<?php echo (int)$ev['id']; ?>" id="chk_ev_<?php echo (int)$ev['id']; ?>">
                                            <label class="form-check-label" for="chk_ev_<?php echo (int)$ev['id']; ?>">
                                                <?php echo htmlspecialchars($ev['title'] . (!empty($ev['location']) ? ' (' . $ev['location'] . ')' : '')); ?>
                                            </label>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-bold small">RFID / NFC Tag UID</label>
                            <input type="text" class="form-control form-control-sm font-monospace" name="rfid_uid" id="cfg_rfid_uid">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold small">Emergency Contact</label>
                            <input type="text" class="form-control form-control-sm" name="emergency_contact" id="cfg_emergency_contact">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label fw-bold small">Blood Group</label>
                            <input type="text" class="form-control form-control-sm" name="blood_group" id="cfg_blood_group">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label fw-bold small">Expiry Date</label>
                            <input type="date" class="form-control form-control-sm" name="expires_at" id="cfg_expires_at">
                        </div>
                    </div>
                </div>

                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-primary fw-bold px-3">
                        <i class="fas fa-save me-1"></i>Save Clearance &amp; Badge
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ==================== MODAL: ADD / EDIT ACCESS CLEARANCE LEVEL ==================== -->
<div class="modal fade no-print" id="modalAccessLevelForm" tabindex="-1" aria-labelledby="modalAccessLevelTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0 shadow-lg">
            <div class="modal-header bg-dark text-white py-3 px-4">
                <h5 class="modal-title fw-bold" id="modalAccessLevelTitle">
                    <i class="fas fa-shield-halved text-warning me-2"></i>Configure Access Clearance Level
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="accessLevelForm" onsubmit="submitAccessLevelForm(event)">
                <input type="hidden" name="ajax_action" value="save_clearance_level">
                <input type="hidden" name="level_id" id="lvl_id" value="0">
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label fw-bold small">Level Code <span class="text-danger">*</span></label>
                            <input type="text" class="form-control font-monospace fw-bold" name="code" id="lvl_code" placeholder="e.g. L5" maxlength="10" required>
                            <div class="form-text" style="font-size: 0.72rem;">Circular badge text (e.g. L1, L2, L5, VIP).</div>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label fw-bold small">Level Key <span class="text-muted">(Identifier)</span></label>
                            <input type="text" class="form-control font-monospace" name="level_key" id="lvl_key" placeholder="e.g. L5_VIP_STAGE">
                            <div class="form-text" style="font-size: 0.72rem;">Leave empty to auto-generate.</div>
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-bold small">Full Title <span class="text-danger">*</span></label>
                            <input type="text" class="form-control fw-semibold" name="title" id="lvl_title" placeholder="e.g. Level 5 — VIP & Production Head" required>
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-bold small">Short Name / Tagline</label>
                            <input type="text" class="form-control" name="short_name" id="lvl_short_name" placeholder="e.g. VIP Backstage & Stock Vault">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold small">Theme Color</label>
                            <div class="d-flex align-items-center gap-2">
                                <input type="color" class="form-control form-control-color" id="lvl_color_picker" value="#0284c7" oninput="document.getElementById('lvl_color').value=this.value;">
                                <input type="text" class="form-control font-monospace" name="color" id="lvl_color" value="#0284c7" oninput="document.getElementById('lvl_color_picker').value=this.value;">
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold small">Icon</label>
                            <select class="form-select" name="icon" id="lvl_icon">
                                <option value="fa-shield-halved">Shield (fa-shield-halved)</option>
                                <option value="fa-sliders">Sliders / Tech (fa-sliders)</option>
                                <option value="fa-warehouse">Warehouse (fa-warehouse)</option>
                                <option value="fa-location-dot">Location Dot (fa-location-dot)</option>
                                <option value="fa-crown">Crown / VIP (fa-crown)</option>
                                <option value="fa-star">Star / Premium (fa-star)</option>
                                <option value="fa-key">Key / Master (fa-key)</option>
                                <option value="fa-headphones">Audio / Stage (fa-headphones)</option>
                                <option value="fa-video">Video / Rigging (fa-video)</option>
                                <option value="fa-bolt">Power / Tech (fa-bolt)</option>
                                <option value="fa-truck">Transport / Dispatch (fa-truck)</option>
                            </select>
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-bold small">Description</label>
                            <textarea class="form-control" name="description" id="lvl_description" rows="2" placeholder="Describe the physical zones, access hours, or restrictions for this clearance tier..."></textarea>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-bold small">Default Stock Scope</label>
                            <select class="form-select" name="default_stock_access" id="lvl_default_stock">
                                <option value="none">None</option>
                                <option value="selected">Selected Warehouses</option>
                                <option value="all">All Warehouses</option>
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-bold small">Default Venue Scope</label>
                            <select class="form-select" name="default_venue_access" id="lvl_default_venue">
                                <option value="none">None</option>
                                <option value="assigned_only">Assigned Events Only</option>
                                <option value="selected">Selected Venues</option>
                                <option value="all">All Event Venues</option>
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-bold small">Sort Order</label>
                            <input type="number" class="form-control" name="sort_order" id="lvl_sort_order" value="1" min="0" max="99">
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light py-2 px-4 d-flex justify-content-between">
                    <button type="button" class="btn btn-secondary fw-semibold" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary fw-bold" id="btnSaveClearanceLevel">
                        <i class="fas fa-check me-1"></i>Save Clearance Level
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    const usersBadgeDirectory = <?php echo json_encode($usersWithBadges); ?>;
    const kioskCheckpointStateMap = <?php echo json_encode($todayCheckpointStates); ?>;

    function getKioskStateKey(uid, cpType, cpId) {
        return cpType + '_' + cpId + '_' + uid;
    }

    // Render all QR codes and initialize tabs on DOM ready
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('.cr80-qr-render').forEach(function(container) {
            const payload = container.getAttribute('data-qr') || 'ABL-BDG';
            container.innerHTML = '';
            new QRCode(container, {
                text: payload,
                width: 72,
                height: 72,
                colorDark: '#0f172a',
                colorLight: '#ffffff',
                correctLevel: QRCode.CorrectLevel.M
            });
        });

        // Tab switching and hash preservation
        const hash = (window.location.hash || '').replace('#', '').toLowerCase();
        if (hash === 'pane-matrix' || hash === 'matrix' || hash === 'access-levels') {
            const btn = document.getElementById('tab-matrix-btn');
            if (btn) btn.click();
        } else if (hash === 'pane-kiosk' || hash === 'kiosk' || hash === 'terminal') {
            const btn = document.getElementById('tab-kiosk-btn');
            if (btn) btn.click();
        } else if (hash === 'pane-roster' || hash === 'roster') {
            const btn = document.getElementById('tab-roster-btn');
            if (btn) btn.click();
        } else if (hash === 'pane-gallery' || hash === 'gallery') {
            const btn = document.getElementById('tab-gallery-btn');
            if (btn) btn.click();
        }

        // Keep hash in URL when tab changes
        document.querySelectorAll('#badgesHubTabs button[data-bs-toggle="pill"]').forEach(function(tabBtn) {
            tabBtn.addEventListener('shown.bs.tab', function(e) {
                const target = e.target.getAttribute('data-bs-target');
                if (target && history.replaceState) {
                    history.replaceState(null, '', target);
                }
            });
        });

        if (document.getElementById('badgeSearchInput') && document.getElementById('badgeSearchInput').value) {
            filterBadgeGallery();
        }
        syncKioskStaffButtonStates();
    });

    function openTerminalWithToggle(cpType) {
        const kioskTab = document.getElementById('tab-kiosk-btn');
        if (kioskTab) kioskTab.click();
        if (cpType === 'event_venue') {
            document.getElementById('cp_type_venue').checked = true;
        } else {
            document.getElementById('cp_type_stock').checked = true;
        }
        toggleCheckpointDropdown();
    }

    function selectQuickStockGate(stockId, btnEl) {
        document.getElementById('kiosk_stock_id').value = stockId;
        document.querySelectorAll('.stock-quick-pill').forEach(function(b) {
            b.classList.remove('btn-success');
            b.classList.add('btn-outline-success');
        });
        if (btnEl) {
            btnEl.classList.remove('btn-outline-success');
            btnEl.classList.add('btn-success');
        }
        syncKioskStaffButtonStates();
    }

    function selectQuickVenueGate(eventId, btnEl) {
        document.getElementById('kiosk_event_id').value = eventId;
        document.querySelectorAll('.venue-quick-pill').forEach(function(b) {
            b.classList.remove('btn-primary');
            b.classList.add('btn-outline-primary');
        });
        if (btnEl) {
            btnEl.classList.remove('btn-outline-primary');
            btnEl.classList.add('btn-primary');
        }
        syncKioskStaffButtonStates();
    }

    function filterKioskStaffByName() {
        const q = (document.getElementById('kioskStaffSearchInput').value || '').toLowerCase().trim();
        let count = 0;
        document.querySelectorAll('.kiosk-staff-row').forEach(function(row) {
            const hay = row.getAttribute('data-search') || '';
            const match = !q || hay.indexOf(q) !== -1;
            row.classList.toggle('d-none', !match);
            row.classList.toggle('d-flex', match);
            if (match) count++;
        });
        const badge = document.getElementById('kioskStaffMatchBadge');
        if (badge) badge.textContent = count + ' Found';
    }

    function applyKioskStaffButtonState(uid, stateObj) {
        const btnIn = document.getElementById('kiosk-btn-checkin-' + uid);
        const btnOut = document.getElementById('kiosk-btn-checkout-' + uid);
        const pill = document.getElementById('kiosk-status-pill-' + uid);
        if (!btnIn || !btnOut) return;

        const state = stateObj ? stateObj.state : 'ready';
        const timeStr = stateObj && stateObj.time ? stateObj.time : '';

        if (state === 'checked_in') {
            btnIn.disabled = true;
            btnIn.className = 'btn btn-sm btn-secondary fw-bold px-2 py-1 opacity-50';
            btnIn.innerHTML = '<i class="fas fa-check me-1"></i>Checkin';

            btnOut.disabled = false;
            btnOut.className = 'btn btn-sm btn-warning fw-bold text-dark px-2 py-1';
            btnOut.innerHTML = '<i class="fas fa-sign-out-alt me-1"></i>Checkout';

            if (pill) {
                pill.className = 'badge bg-success-subtle text-success border border-success-subtle';
                pill.textContent = 'Checked In' + (timeStr ? ' • ' + timeStr : '');
            }
        } else if (state === 'checked_out') {
            btnIn.disabled = true;
            btnIn.className = 'btn btn-sm btn-secondary fw-bold px-2 py-1 opacity-50';
            btnIn.innerHTML = '<i class="fas fa-check me-1"></i>Checkin';

            btnOut.disabled = true;
            btnOut.className = 'btn btn-sm btn-secondary fw-bold px-2 py-1 opacity-50';
            btnOut.innerHTML = '<i class="fas fa-check-double me-1"></i>Checkout';

            if (pill) {
                pill.className = 'badge bg-info-subtle text-info border border-info-subtle';
                pill.textContent = 'Checked Out' + (timeStr ? ' • ' + timeStr : '');
            }
        } else {
            btnIn.disabled = false;
            btnIn.className = 'btn btn-sm btn-success fw-bold px-2 py-1';
            btnIn.innerHTML = '<i class="fas fa-sign-in-alt me-1"></i>Checkin';

            btnOut.disabled = true;
            btnOut.className = 'btn btn-sm btn-outline-secondary fw-bold px-2 py-1 opacity-50';
            btnOut.innerHTML = '<i class="fas fa-sign-out-alt me-1"></i>Checkout';

            if (pill) {
                pill.className = 'badge bg-secondary-subtle text-secondary';
                pill.textContent = 'Ready';
            }
        }
    }

    function syncKioskStaffButtonStates() {
        const isVenue = document.getElementById('cp_type_venue') ? document.getElementById('cp_type_venue').checked : true;
        const isStock = !isVenue;
        const cpType = isVenue ? 'event_venue' : 'stock_warehouse';
        const selEl = isVenue ? document.getElementById('kiosk_event_id') : document.getElementById('kiosk_stock_id');
        const cpId = selEl ? selEl.value : '1';
        const prefix = cpType + '_' + cpId + '_';

        const bannerLabel = document.getElementById('activeCheckpointBannerLabel');
        if (bannerLabel && selEl && selEl.options[selEl.selectedIndex]) {
            bannerLabel.textContent = 'Active ' + (isVenue ? 'Venue: ' : 'Stock Gate: ') + selEl.options[selEl.selectedIndex].text;
        }

        const footerLoc = document.getElementById('kioskCardLocationFooter');
        if (footerLoc && selEl && selEl.options[selEl.selectedIndex]) {
            const cleanName = selEl.options[selEl.selectedIndex].text.split('(')[0].trim();
            footerLoc.textContent = cleanName + ' Clearance - Verified.';
        }

        document.querySelectorAll('.kiosk-staff-row').forEach(function(row) {
            const uid = row.getAttribute('data-uid');
            applyKioskStaffButtonState(uid, kioskCheckpointStateMap[prefix + uid] || null);
        });
    }

    function handleKioskStaffRowClick(event, uid, badgeCode) {
        if (event.target.closest('button')) {
            return;
        }
        document.querySelectorAll('.kiosk-staff-row').forEach(el => el.classList.remove('border-primary', 'bg-light'));
        const row = document.getElementById('kiosk-staff-row-' + uid);
        if (row) {
            row.classList.add('border-primary', 'bg-light');
        }
        document.getElementById('kiosk_scan_mode').value = 'verify_only';
        document.getElementById('kiosk_scan_input').value = badgeCode;
        submitGateScan();
        document.getElementById('kiosk_scan_mode').value = 'auto';
        document.getElementById('kiosk_scan_input').value = '';
    }

    function handleKioskStaffAction(uid, badgeCode, action) {
        const btnIn = document.getElementById('kiosk-btn-checkin-' + uid);
        const btnOut = document.getElementById('kiosk-btn-checkout-' + uid);
        if (action === 'check_in' && btnIn) btnIn.disabled = true;
        if (action === 'check_out' && btnOut) btnOut.disabled = true;

        document.getElementById('kiosk_scan_mode').value = action;
        document.getElementById('kiosk_scan_input').value = badgeCode;
        submitGateScan();
        document.getElementById('kiosk_scan_mode').value = 'auto';
        document.getElementById('kiosk_scan_input').value = '';
    }

    function filterBadgeGallery() {
        const q = (document.getElementById('badgeSearchInput').value || '').toLowerCase().trim();
        const role = (document.getElementById('badgeRoleFilter').value || '').toLowerCase().trim();
        const clearance = (document.getElementById('badgeClearanceFilter').value || '').trim();

        document.querySelectorAll('.badge-item-col').forEach(function(col) {
            const cSearch = col.getAttribute('data-search') || '';
            const cRole = col.getAttribute('data-role') || '';
            const cClearance = col.getAttribute('data-clearance') || '';

            const matchQ = !q || cSearch.indexOf(q) !== -1;
            const matchR = !role || cRole === role;
            const matchC = !clearance || cClearance === clearance;

            col.style.display = (matchQ && matchR && matchC) ? '' : 'none';
        });
    }

    function setBadgeGallerySide(mode) {
        document.getElementById('viewBtnFront').classList.toggle('active', mode === 'front');
        document.getElementById('viewBtnBack').classList.toggle('active', mode === 'back');
        document.getElementById('viewBtnBoth').classList.toggle('active', mode === 'both');

        document.querySelectorAll('.badge-item-col').forEach(function(col) {
            const front = col.querySelector('.badge-side-front');
            const back = col.querySelector('.badge-side-back');
            if (mode === 'front') {
                front.classList.remove('d-none');
                back.classList.add('d-none');
                col.className = 'col-md-6 col-xl-4 badge-item-col';
            } else if (mode === 'back') {
                front.classList.add('d-none');
                back.classList.remove('d-none');
                col.className = 'col-md-6 col-xl-4 badge-item-col';
            } else {
                front.classList.remove('d-none');
                back.classList.remove('d-none');
                col.className = 'col-12 col-xl-6 badge-item-col';
            }
        });
    }

    function flipSingleBadge(uid) {
        const front = document.getElementById('badge-front-' + uid);
        const back = document.getElementById('badge-back-' + uid);
        if (!front || !back) return;
        if (!front.classList.contains('d-none') && !back.classList.contains('d-none')) {
            back.classList.add('d-none');
            return;
        }
        front.classList.toggle('d-none');
        back.classList.toggle('d-none');
    }

    function printSingleBadge(uid) {
        document.querySelectorAll('.badge-item-col').forEach(function(col) {
            if (parseInt(col.getAttribute('data-uid'), 10) === uid) {
                col.style.display = '';
                col.querySelector('.badge-side-front').classList.remove('d-none');
                col.querySelector('.badge-side-back').classList.remove('d-none');
            } else {
                col.style.display = 'none';
            }
        });
        window.print();
        setTimeout(function() {
            setBadgeGallerySide('front');
            filterBadgeGallery();
        }, 400);
    }

    function printVisibleBadges() {
        const tabBtn = document.getElementById('tab-gallery-btn');
        if (tabBtn) tabBtn.click();
        setTimeout(function() {
            window.print();
        }, 250);
    }

    function openConfigureBadgeModal(uid) {
        const u = usersBadgeDirectory[uid];
        if (!u) return;

        document.getElementById('cfg_user_id').value = uid;
        document.getElementById('cfg_user_title').textContent = u.full_name || u.username;
        document.getElementById('cfg_user_subtitle').textContent = (u.role || 'Staff').toUpperCase() + ' • ' + u.staff_code;
        document.getElementById('cfg_badge_code_pill').textContent = u.badge_code;

        document.getElementById('cfg_clearance_level').value = u.clearance_level || 'L1_VENUE_BASIC';
        document.getElementById('cfg_badge_status').value = u.badge_status || 'active';
        document.getElementById('cfg_stock_access_mode').value = u.stock_access_mode || 'none';
        document.getElementById('cfg_venue_access_mode').value = u.venue_access_mode || 'assigned_only';
        document.getElementById('cfg_rfid_uid').value = u.rfid_uid || '';
        document.getElementById('cfg_emergency_contact').value = u.emergency_contact || '+250 788 000 112';
        document.getElementById('cfg_blood_group').value = u.blood_group || 'O+';
        document.getElementById('cfg_expires_at').value = u.expires_at || '';

        const allowedS = u.allowed_stock_array || [];
        document.querySelectorAll('.cfg-stock-chk').forEach(function(chk) {
            chk.checked = allowedS.indexOf(parseInt(chk.value, 10)) !== -1;
        });

        const allowedE = u.allowed_event_array || [];
        document.querySelectorAll('.cfg-event-chk').forEach(function(chk) {
            chk.checked = allowedE.indexOf(parseInt(chk.value, 10)) !== -1;
        });

        const modal = new bootstrap.Modal(document.getElementById('configureBadgeModal'));
        modal.show();
    }

    function saveBadgeConfiguration(e) {
        e.preventDefault();
        const form = document.getElementById('configureBadgeForm');
        const fd = new FormData(form);

        fetch('id_badges.php', {
            method: 'POST',
            body: fd
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                Swal.fire({
                    icon: 'success',
                    title: 'Clearance Saved',
                    text: data.message,
                    timer: 1400,
                    showConfirmButton: false
                }).then(() => window.location.reload());
            } else {
                Swal.fire('Error', data.message || 'Could not update badge.', 'error');
            }
        });
    }

    function syncRoleDefaults() {
        Swal.fire({
            title: 'Sync Badges with Role Defaults?',
            text: 'This will apply recommended Stock Warehouse & Event Venue clearance tiers to all users based on their assigned role.',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Yes, Sync All Badges'
        }).then(result => {
            if (!result.isConfirmed) return;
            const fd = new FormData();
            fd.append('ajax_action', 'sync_role_defaults');
            fetch('id_badges.php', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        Swal.fire('Synchronized!', data.message, 'success').then(() => window.location.reload());
                    }
                });
        });
    }

    function toggleCheckpointDropdown() {
        const isVenue = document.getElementById('cp_type_venue') ? document.getElementById('cp_type_venue').checked : true;
        const btnStock = document.getElementById('btn_label_stock');
        const btnVenue = document.getElementById('btn_label_venue');

        if (isVenue) {
            if (btnVenue) {
                btnVenue.style.background = '#00d2ff';
                btnVenue.style.color = '#000000';
                btnVenue.style.borderColor = '#00d2ff';
            }
            if (btnStock) {
                btnStock.style.background = '';
                btnStock.style.color = '';
                btnStock.style.borderColor = '';
            }
        } else {
            if (btnStock) {
                btnStock.style.background = '#16a34a';
                btnStock.style.color = '#ffffff';
                btnStock.style.borderColor = '#16a34a';
            }
            if (btnVenue) {
                btnVenue.style.background = '';
                btnVenue.style.color = '';
                btnVenue.style.borderColor = '';
            }
        }

        const venueWrap = document.getElementById('venueCheckpointSelectWrap');
        const stockWrap = document.getElementById('stockCheckpointSelectWrap');
        if (venueWrap) venueWrap.classList.toggle('d-none', !isVenue);
        if (stockWrap) stockWrap.classList.toggle('d-none', isVenue);

        syncKioskStaffButtonStates();
    }

    function syncScanActionMode() {
        const modeSel = document.getElementById('kiosk_scan_mode_select');
        if (modeSel) {
            document.getElementById('kiosk_scan_mode').value = modeSel.value;
        }
    }

    // ==================== ACCESS CLEARANCE LEVELS CRUD ====================
    function openAddAccessLevelModal() {
        document.getElementById('modalAccessLevelTitle').innerHTML = '<i class="fas fa-plus-circle text-success me-2"></i>Add New Access Clearance Level';
        document.getElementById('lvl_id').value = '0';
        document.getElementById('lvl_code').value = '';
        document.getElementById('lvl_key').value = '';
        document.getElementById('lvl_title').value = '';
        document.getElementById('lvl_short_name').value = '';
        document.getElementById('lvl_color').value = '#0284c7';
        document.getElementById('lvl_color_picker').value = '#0284c7';
        document.getElementById('lvl_icon').value = 'fa-shield-halved';
        document.getElementById('lvl_description').value = '';
        document.getElementById('lvl_default_stock').value = 'none';
        document.getElementById('lvl_default_venue').value = 'assigned_only';
        document.getElementById('lvl_sort_order').value = '10';

        const modal = new bootstrap.Modal(document.getElementById('modalAccessLevelForm'));
        modal.show();
    }

    function openEditAccessLevelModal(id) {
        const fd = new FormData();
        fd.append('ajax_action', 'get_clearance_level');
        fd.append('id', id);

        fetch('id_badges.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(data => {
                if (!data.success || !data.level) {
                    Swal.fire('Error', data.message || 'Could not load clearance level details.', 'error');
                    return;
                }
                const lvl = data.level;
                document.getElementById('modalAccessLevelTitle').innerHTML = '<i class="fas fa-edit text-warning me-2"></i>Edit Access Clearance Level';
                document.getElementById('lvl_id').value = lvl.id;
                document.getElementById('lvl_code').value = lvl.code;
                document.getElementById('lvl_key').value = lvl.level_key;
                document.getElementById('lvl_title').value = lvl.title;
                document.getElementById('lvl_short_name').value = lvl.short_name;
                document.getElementById('lvl_color').value = lvl.color;
                document.getElementById('lvl_color_picker').value = lvl.color;
                document.getElementById('lvl_icon').value = lvl.icon || 'fa-shield-halved';
                document.getElementById('lvl_description').value = lvl.description || '';
                document.getElementById('lvl_default_stock').value = lvl.default_stock_access || 'none';
                document.getElementById('lvl_default_venue').value = lvl.default_venue_access || 'assigned_only';
                document.getElementById('lvl_sort_order').value = lvl.sort_order || 0;

                const modal = new bootstrap.Modal(document.getElementById('modalAccessLevelForm'));
                modal.show();
            })
            .catch(err => {
                Swal.fire('Error', 'Network error fetching level details.', 'error');
            });
    }

    function submitAccessLevelForm(e) {
        e.preventDefault();
        const form = document.getElementById('accessLevelForm');
        const fd = new FormData(form);

        const btn = document.getElementById('btnSaveClearanceLevel');
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Saving...';

        fetch('id_badges.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(data => {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-check me-1"></i>Save Clearance Level';
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Saved!',
                        text: data.message,
                        timer: 1500,
                        showConfirmButton: false
                    }).then(() => {
                        window.location.href = 'id_badges.php?tab=matrix';
                    });
                } else {
                    Swal.fire('Error', data.message || 'Failed to save level.', 'error');
                }
            })
            .catch(err => {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-check me-1"></i>Save Clearance Level';
                Swal.fire('Error', 'Network error saving clearance level.', 'error');
            });
    }

    function confirmDeleteAccessLevel(id, key, title, staffCount) {
        let warningText = `Are you sure you want to delete "${title}" (${key})?`;
        if (staffCount > 0) {
            warningText += `\n\nNote: ${staffCount} staff member(s) currently hold this clearance level and will be automatically reassigned to Level 1 — Basic Venue Crew.`;
        }

        Swal.fire({
            title: 'Delete Clearance Level?',
            text: warningText,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc2626',
            cancelButtonColor: '#64748b',
            confirmButtonText: 'Yes, Delete & Reassign',
            cancelButtonText: 'Cancel'
        }).then(result => {
            if (result.isConfirmed) {
                const fd = new FormData();
                fd.append('ajax_action', 'delete_clearance_level');
                fd.append('id', id);
                fd.append('reassign_to', 'L1_VENUE_BASIC');

                fetch('id_badges.php', { method: 'POST', body: fd })
                    .then(r => r.json())
                    .then(data => {
                        if (data.success) {
                            Swal.fire({
                                icon: 'success',
                                title: 'Deleted!',
                                text: data.message,
                                timer: 1500,
                                showConfirmButton: false
                            }).then(() => {
                                window.location.href = 'id_badges.php?tab=matrix';
                            });
                        } else {
                            Swal.fire('Error', data.message || 'Failed to delete clearance level.', 'error');
                        }
                    })
                    .catch(err => {
                        Swal.fire('Error', 'Network error deleting clearance level.', 'error');
                    });
            }
        });
    }

    function simulateGateScan(badgeCode) {
        const kioskTab = document.getElementById('tab-kiosk-btn');
        if (kioskTab) kioskTab.click();
        document.getElementById('kiosk_scan_input').value = badgeCode;
        submitGateScan();
    }

    function quickCheckOutUser(badgeCode, cpType, cpId) {
        const fd = new FormData();
        fd.append('ajax_action', 'scan_badge_attendance');
        fd.append('scan_input', badgeCode);
        fd.append('checkpoint_type', cpType);
        fd.append('checkpoint_id', cpId);
        fd.append('scan_mode', 'check_out');
        fd.append('notes', 'Checked out from Live Roster');

        fetch('id_badges.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Checked Out',
                        text: data.user.full_name + ' checked out from ' + data.checkpoint_name,
                        timer: 1300,
                        showConfirmButton: false
                    }).then(() => window.location.reload());
                }
            });
    }

    function submitGateScan(e) {
        if (e) e.preventDefault();
        const isStock = document.getElementById('cp_type_stock').checked;
        const cpType = isStock ? 'stock_warehouse' : 'event_venue';
        const cpId = isStock ? document.getElementById('kiosk_stock_id').value : document.getElementById('kiosk_event_id').value;
        const scanMode = document.getElementById('kiosk_scan_mode').value;
        const scanInput = document.getElementById('kiosk_scan_input').value.trim();
        const notes = document.getElementById('kiosk_scan_notes').value.trim();

        if (!scanInput) return;

        const fd = new FormData();
        fd.append('ajax_action', 'scan_badge_attendance');
        fd.append('checkpoint_type', cpType);
        fd.append('checkpoint_id', cpId);
        fd.append('scan_mode', scanMode);
        fd.append('scan_input', scanInput);
        fd.append('notes', notes);

        fetch('id_badges.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(data => {
                document.getElementById('kioskResultPlaceholder').classList.add('d-none');
                const card = document.getElementById('kioskResultActiveCard');
                card.classList.remove('d-none', 'kiosk-result-granted', 'kiosk-result-denied');

                if (!data.success && !data.user) {
                    card.classList.add('kiosk-result-denied');
                    card.innerHTML = `
                        <div class="text-center py-4">
                            <span class="badge bg-danger fs-5 px-4 py-2 mb-3"><i class="fas fa-ban me-2"></i>ACCESS DENIED</span>
                            <h4 class="fw-bold text-dark">${data.message || 'Unrecognized Badge'}</h4>
                            <p class="text-muted mb-0">Checkpoint: <strong>${data.checkpoint_name || ''}</strong></p>
                        </div>
                    `;
                    return;
                }

                const granted = data.access_status === 'granted';
                card.classList.add(granted ? 'kiosk-result-granted' : 'kiosk-result-denied');

                const u = data.user;
                const actionLabel = data.scan_action === 'check_in' ? 'CHECKED IN (ARRIVAL)' :
                                   (data.scan_action === 'check_out' ? 'CHECKED OUT (DEPARTURE)' : 'CLEARANCE VERIFIED');

                const stateKey = getKioskStateKey(u.id, cpType, cpId);
                const existingState = kioskCheckpointStateMap[stateKey] || {};

                if (granted && u && u.id && (data.scan_action === 'check_in' || data.scan_action === 'check_out')) {
                    kioskCheckpointStateMap[stateKey] = {
                        state: data.scan_action === 'check_in' ? 'checked_in' : 'checked_out',
                        time: data.scanned_at,
                        check_in_time: data.check_in_time || (data.scan_action === 'check_in' ? data.scanned_at : (existingState.check_in_time || null)),
                        check_out_time: data.check_out_time || (data.scan_action === 'check_out' ? data.scanned_at : (existingState.check_out_time || null))
                    };
                    applyKioskStaffButtonState(u.id, kioskCheckpointStateMap[stateKey]);
                }

                const getInitials = (name) => {
                    const clean = (name || '').toString().trim();
                    if (!clean) return 'ST';
                    const parts = clean.split(/[\s_\-]+/).filter(Boolean);
                    return parts.length >= 2
                        ? (parts[0].charAt(0) + parts[1].charAt(0)).toUpperCase()
                        : parts[0].substring(0, 2).toUpperCase();
                };
                const uInitials = getInitials(u.full_name);
                const uColor = u.clearance_color || '#17487a';
                const hasProfileImg = u.profile_image && u.profile_image.trim() !== '';
                const roleDeptTag = (u.role ? u.role.replace(/_/g, ' ').toUpperCase() : 'CREW') + (u.department ? ' - ' + u.department.toUpperCase() : '');
                const timeActionText = data.scan_action === 'check_in' ? 'Arrived at' : (data.scan_action === 'check_out' ? 'Departed at' : 'Scanned at');

                const clearanceCode = u.clearance_code || 'L2';
                const clearanceCleanTitle = (u.clearance_title || 'Technical & Stage')
                    .replace(/^Level\s+\d+\s*[-—]\s*/i, '')
                    .trim();

                const curState = kioskCheckpointStateMap[stateKey] || {};
                const displayInTime = data.check_in_time || (data.scan_action === 'check_in' ? data.scanned_at : (curState.check_in_time || '--:--:--'));
                const displayOutTime = data.check_out_time || (data.scan_action === 'check_out' ? data.scanned_at : (curState.check_out_time || '--:--:--'));

                const smallAvatarHtml = hasProfileImg
                    ? `<img src="${u.profile_image}" alt="${u.full_name}" style="width: 34px; height: 34px; border-radius: 50%; object-fit: cover; flex-shrink: 0; border: 1.5px solid ${uColor};" onerror="this.onerror=null;this.style.display='none';this.nextElementSibling.classList.remove('d-none');">
                       <div class="rounded-circle d-none text-white d-flex align-items-center justify-content-center fw-bold" style="width: 34px; height: 34px; background: ${uColor}; flex-shrink: 0; font-size: 0.72rem;">${uInitials}</div>`
                    : `<div class="rounded-circle text-white d-flex align-items-center justify-content-center fw-bold" style="width: 34px; height: 34px; background: ${uColor}; flex-shrink: 0; font-size: 0.72rem;">${uInitials}</div>`;

                card.className = 'rounded-4 overflow-hidden mb-3 shadow-lg border-0 text-start';
                card.style.background = '#ffffff';

                card.innerHTML = `
                    <div class="row g-0">
                        <!-- Left: Portrait Photo with Role Pill at Bottom -->
                        <div class="col-md-5 position-relative" style="background: #e2e8f0; min-height: 290px;">
                            ${hasProfileImg ? `
                                <img src="${u.profile_image}"
                                     alt="${u.full_name}"
                                     style="width: 100%; height: 100%; min-height: 290px; max-height: 330px; object-fit: cover; object-position: center top; display: block;"
                                     onerror="this.onerror=null;this.style.display='none';this.nextElementSibling.classList.remove('d-none');">
                            ` : ''}
                            <div class="${hasProfileImg ? 'd-none' : ''} d-flex flex-column align-items-center justify-content-center w-100 h-100"
                                 style="min-height: 290px; background: linear-gradient(135deg, #0f172a 0%, #1e3a8a 100%);">
                                <div class="rounded-circle d-inline-flex align-items-center justify-content-center fw-bold text-white shadow"
                                     style="width: 96px; height: 96px; background: rgba(255,255,255,0.18); border: 3px solid rgba(255,255,255,0.4); font-size: 2.3rem; letter-spacing: 1px;">
                                    ${uInitials}
                                </div>
                            </div>
                            <!-- Bottom Cyan Role Pill -->
                            <div class="position-absolute bottom-0 start-50 translate-middle-x mb-3 text-nowrap px-2" style="z-index: 3; max-width: 90%;">
                                <span class="badge shadow text-truncate d-inline-block"
                                      style="background: #2998d8; color: #ffffff; font-weight: 800; font-size: 0.82rem; padding: 7px 18px; border-radius: 20px; letter-spacing: 0.8px; max-width: 100%;">
                                    ${roleDeptTag}
                                </span>
                            </div>
                        </div>

                        <!-- Right: Blue Header with Name + L2 Circle, Middle with Clearance Pill, Status Circle, Big Pill, In/Out Times & Footer -->
                        <div class="col-md-7 d-flex flex-column justify-content-between" style="background: #ffffff;">
                            <!-- Top Navy Blue Name Header with Clearance Code Circle Badge (Image 2) -->
                            <div class="px-4 py-3 d-flex justify-content-between align-items-center" style="background: #17487a;">
                                <h3 class="fw-bold mb-0 text-white" style="letter-spacing: 0.5px; font-size: 1.65rem; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;">
                                    ${u.full_name}
                                </h3>
                                <div class="rounded-circle d-flex align-items-center justify-content-center fw-bold shadow-sm"
                                     style="width: 36px; height: 36px; background: #ffffff; color: #0f172a; font-size: 0.95rem; font-weight: 800; flex-shrink: 0;"
                                     title="Clearance Level: ${clearanceCode}">
                                    ${clearanceCode}
                                </div>
                            </div>

                            <!-- Middle Section: Clearance Pill + Circle Icon + ACCESS GRANTED + Timestamps -->
                            <div class="p-3 px-4 position-relative d-flex flex-column align-items-center justify-content-center flex-grow-1">
                                <!-- Top-Right Clearance Tag (Title only, Image 2 style) -->
                                <div class="position-absolute top-0 end-0 p-3">
                                    <span class="badge" style="background: #8fa4b8; color: #0f172a; font-size: 0.84rem; font-weight: 800; border-radius: 14px; padding: 6px 16px; letter-spacing: 0.3px;">
                                        ${clearanceCleanTitle}
                                    </span>
                                </div>

                                <!-- Big Status Ring Icon -->
                                <div class="mt-2 mb-2">
                                    ${granted ? `
                                        <div class="rounded-circle d-flex align-items-center justify-content-center shadow-sm"
                                             style="width: 74px; height: 74px; border: 4.5px solid #22c55e; background: #ffffff;">
                                            <i class="fas fa-check" style="font-size: 2.2rem; color: #22c55e;"></i>
                                        </div>
                                    ` : `
                                        <div class="rounded-circle d-flex align-items-center justify-content-center shadow-sm"
                                             style="width: 74px; height: 74px; border: 4.5px solid #ef4444; background: #ffffff;">
                                            <i class="fas fa-times" style="font-size: 2.2rem; color: #ef4444;"></i>
                                        </div>
                                    `}
                                </div>

                                <!-- Big Status Action Button / Pill -->
                                <div class="my-2">
                                    <span class="badge shadow-sm"
                                          style="background: ${granted ? '#16a34a' : '#dc2626'}; color: #ffffff; font-size: 1.18rem; font-weight: 800; border-radius: 10px; padding: 9px 28px; letter-spacing: 0.8px;">
                                        ${granted ? 'ACCESS GRANTED' : 'ACCESS DENIED'}
                                    </span>
                                </div>

                                ${granted ? `
                                    <!-- Inbound (Check-in) and Outbound (Check-out) Timestamps (Image 2) -->
                                    <div class="w-100 px-3 mt-3 d-flex justify-content-between align-items-center">
                                        <!-- Check-in Time & Quick Action Trigger -->
                                        <div class="d-flex align-items-center gate-time-action gate-checkin-action"
                                             onclick="handleKioskStaffAction(${u.id}, '${u.badge_code}', 'check_in')"
                                             style="cursor: pointer; user-select: none;"
                                             title="Check In (${displayInTime}) — Click to check in staff">
                                            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#22c55e" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round" class="me-2" style="flex-shrink: 0;">
                                                <path d="M10 4.5A8 8 0 1 1 10 19.5"></path>
                                                <polyline points="10 8 14 12 10 16"></polyline>
                                                <line x1="2" y1="12" x2="14" y2="12"></line>
                                            </svg>
                                            <span class="fw-bold" style="color: #0f172a; font-size: 1.05rem; font-weight: 800; letter-spacing: 0.3px;">
                                                ${displayInTime}
                                            </span>
                                        </div>

                                        <!-- Check-out Time & Quick Action Trigger -->
                                        <div class="d-flex align-items-center gate-time-action gate-checkout-action"
                                             onclick="handleKioskStaffAction(${u.id}, '${u.badge_code}', 'check_out')"
                                             style="cursor: pointer; user-select: none;"
                                             title="Check Out (${displayOutTime}) — Click to check out staff">
                                            <span class="fw-bold me-2" style="color: #0f172a; font-size: 1.05rem; font-weight: 800; letter-spacing: 0.3px;">
                                                ${displayOutTime}
                                            </span>
                                            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#ef4444" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink: 0;">
                                                <path d="M14 4.5A8 8 0 1 0 14 19.5"></path>
                                                <polyline points="14 8 18 12 14 16"></polyline>
                                                <line x1="6" y1="12" x2="18" y2="12"></line>
                                            </svg>
                                        </div>
                                    </div>
                                ` : `
                                    <div class="mt-2 text-center">
                                        <span class="fw-bold text-danger" style="font-size: 0.95rem;">
                                            ${data.reason || 'Checkpoint Clearance Required'}
                                        </span>
                                    </div>
                                `}
                            </div>

                            <!-- Bottom Footer Bar: Badge # and Clearance Location (Image 2) -->
                            <div class="px-4 py-3 d-flex flex-wrap justify-content-between align-items-center border-top"
                                 style="background: #edf2f7; border-color: #e2e8f0 !important; font-size: 0.88rem;">
                                <div>
                                    <strong style="color: #0f2e5a; font-family: monospace; letter-spacing: 0.8px; font-size: 0.95rem;">
                                        BADGE &nbsp;#:&nbsp; ${u.badge_code}
                                    </strong>
                                </div>
                                <div class="d-flex align-items-center gap-1">
                                    <i class="fas fa-map-marker-alt" style="color: #b45309;"></i>
                                    <span class="fst-italic text-secondary" style="font-size: 0.84rem;">
                                        ${data.checkpoint_name} ${granted ? 'Clearance - Verified.' : '— Denied.'}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                `;

                const feed = document.getElementById('kioskRecentScansFeed');
                if (feed) {
                    const rowHtml = `
                        <div class="p-2 rounded-3 border mb-2 small d-flex justify-content-between align-items-center bg-light"
                             style="border-left: 4px solid ${granted ? '#10b981' : '#ef4444'} !important;">
                            <div class="d-flex align-items-center gap-2 overflow-hidden">
                                ${smallAvatarHtml}
                                <div class="text-truncate">
                                    <span class="fw-bold text-dark">${u.full_name}</span>
                                    <span class="text-muted ms-1">(${u.badge_code})</span>
                                    <div class="text-muted text-truncate" style="font-size: 0.72rem;">
                                        <i class="fas ${cpType === 'stock_warehouse' ? 'fa-warehouse text-success' : 'fa-location-dot text-primary'} me-1"></i>
                                        ${data.checkpoint_name}
                                    </div>
                                </div>
                            </div>
                            <div class="text-end flex-shrink-0 ms-2">
                                <span class="badge ${granted ? 'bg-success' : 'bg-danger'}">
                                    ${granted ? data.scan_action.replace('_', ' ').toUpperCase() : 'DENIED'}
                                </span>
                                <div class="text-muted" style="font-size: 0.68rem;">${data.scanned_at}</div>
                            </div>
                        </div>
                    `;
                    feed.insertAdjacentHTML('afterbegin', rowHtml);
                }
            });
    }
</script>

<?php require_once 'views/partials/footer.php'; ?>
