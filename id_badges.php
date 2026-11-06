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

// Load Stock Locations
$stockLocations = [];
$stockMap = [];
$stRes = @$conn->query("SELECT id, name, code, location_code, location, open_time, close_time, is_active FROM stock_locations ORDER BY name ASC");
if ($stRes) {
    while ($row = $stRes->fetch_assoc()) {
        $sid = (int)$row['id'];
        $stockLocations[] = $row;
        $stockMap[$sid] = $row;
    }
}
if (empty($stockLocations)) {
    // Fallback default stock location if table empty
    $stockLocations[] = [
        'id' => 1,
        'name' => 'Main Central Warehouse',
        'code' => 'WH-MAIN',
        'location_code' => 'WH-01',
        'location' => 'Kigali Industrial Zone',
        'open_time' => '08:00:00',
        'close_time' => '18:00:00',
        'is_active' => 1
    ];
    $stockMap[1] = $stockLocations[0];
}

// Load Event Venues
$eventsList = [];
$eventMap = [];
$evRes = @$conn->query("SELECT id, title, location, date, call_time, project_manager, tech_lead, assigned_technician FROM events ORDER BY date DESC, id DESC LIMIT 30");
if ($evRes) {
    while ($row = $evRes->fetch_assoc()) {
        $eid = (int)$row['id'];
        $eventsList[] = $row;
        $eventMap[$eid] = $row;
    }
}
if (empty($eventsList)) {
    $eventsList[] = [
        'id' => 1,
        'title' => 'Main Arena Production',
        'location' => 'BK Arena, Kigali',
        'date' => date('Y-m-d'),
        'call_time' => '09:00',
        'project_manager' => 'Operations PM',
        'tech_lead' => 'Tech Lead',
        'assigned_technician' => ''
    ];
    $eventMap[1] = $eventsList[0];
}

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

        echo json_encode([
            'success' => true,
            'log_id' => $logId,
            'access_status' => $accessStatus,
            'scan_action' => $resolvedAction,
            'punctuality' => $punctuality,
            'reason' => $eval['reason'],
            'scanned_at' => date('h:i:s A', strtotime($nowStr)),
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

// KPI Counters
$totalBadges = count($usersWithBadges);
$activeBadgesCount = 0;
$stockAuthorizedCount = 0;
foreach ($usersWithBadges as $ub) {
    if (($ub['badge_status'] ?? 'active') === 'active' && (int)$ub['is_active'] === 1) {
        $activeBadgesCount++;
    }
    if (($ub['clearance_level'] ?? '') === 'L4_ALL_ACCESS' || ($ub['stock_access_mode'] ?? 'none') !== 'none') {
        $stockAuthorizedCount++;
    }
}
$onSiteCount = count($onSiteRoster);
$deniedTodayCount = 0;
foreach ($attendanceLogs as $al) {
    if ($al['access_status'] === 'denied') {
        $deniedTodayCount++;
    }
}

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
                <div>
                    <div class="text-muted small fw-semibold">Stock Warehouse Cleared</div>
                    <div class="fs-4 fw-bold text-dark"><?php echo $stockAuthorizedCount; ?> <span class="fs-6 text-muted fw-normal">Staff</span></div>
                    <div class="small text-muted"><?php echo count($stockLocations); ?> Stock Locations Active</div>
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
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 no-print">
        <ul class="nav hub-nav-pills mb-0" id="badgesHubTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link <?php echo ($initialTab === 'gallery') ? 'active' : ''; ?>" id="tab-gallery-btn" data-bs-toggle="pill" data-bs-target="#pane-gallery" type="button" role="tab">
                    <i class="fas fa-id-card me-2"></i>1. User ID Badges (<?php echo $totalBadges; ?>)
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link <?php echo ($initialTab === 'kiosk') ? 'active' : ''; ?>" id="tab-kiosk-btn" data-bs-toggle="pill" data-bs-target="#pane-kiosk" type="button" role="tab">
                    <i class="fas fa-qrcode me-2 text-warning"></i>2. Gate Scanner &amp; Check-In Kiosk
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link <?php echo ($initialTab === 'roster') ? 'active' : ''; ?>" id="tab-roster-btn" data-bs-toggle="pill" data-bs-target="#pane-roster" type="button" role="tab">
                    <i class="fas fa-clipboard-user me-2 text-info"></i>3. Live Roster &amp; Attendance Logs
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link <?php echo ($initialTab === 'matrix') ? 'active' : ''; ?>" id="tab-matrix-btn" data-bs-toggle="pill" data-bs-target="#pane-matrix" type="button" role="tab">
                    <i class="fas fa-sliders me-2 text-success"></i>4. Stock &amp; Venue Clearance Matrix
                </button>
            </li>
        </ul>
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

                    // Compute authorized Stock names summary
                    $stockSummary = 'No Warehouse Access';
                    if ($cKey === 'L4_ALL_ACCESS' || $u['stock_access_mode'] === 'all') {
                        $stockSummary = 'All Stock Warehouses (' . count($stockLocations) . ')';
                    } elseif ($u['stock_access_mode'] === 'selected' && !empty($u['allowed_stock_array'])) {
                        $sNames = [];
                        foreach ($u['allowed_stock_array'] as $sid) {
                            if (isset($stockMap[$sid])) {
                                $sNames[] = $stockMap[$sid]['name'];
                            }
                        }
                        $stockSummary = !empty($sNames) ? implode(', ', array_slice($sNames, 0, 2)) : 'Selected Stock Only';
                    }

                    // Compute authorized Venue summary
                    $venueSummary = 'Assigned Event Venues';
                    if ($cKey === 'L4_ALL_ACCESS' || $u['venue_access_mode'] === 'all') {
                        $venueSummary = 'All Event Venues & Backstage';
                    } elseif ($u['venue_access_mode'] === 'selected' && !empty($u['allowed_event_array'])) {
                        $eNames = [];
                        foreach ($u['allowed_event_array'] as $eid) {
                            if (isset($eventMap[$eid])) {
                                $eNames[] = $eventMap[$eid]['title'];
                            }
                        }
                        $venueSummary = !empty($eNames) ? implode(', ', array_slice($eNames, 0, 2)) : 'Selected Venues';
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
                                            <i class="fas fa-warehouse text-success me-1"></i>Authorized Stock Locations
                                        </div>
                                        <div class="p-2 rounded bg-light border">
                                            <?php if ($cKey === 'L4_ALL_ACCESS' || $u['stock_access_mode'] === 'all'): ?>
                                                <span class="badge bg-success-subtle text-success border border-success-subtle">All Stock Warehouses &amp; Vaults</span>
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
                                            <i class="fas fa-location-dot text-primary me-1"></i>Authorized Event Venues
                                        </div>
                                        <div class="p-2 rounded bg-light border">
                                            <?php if ($cKey === 'L4_ALL_ACCESS' || $u['venue_access_mode'] === 'all'): ?>
                                                <span class="badge bg-primary-subtle text-primary border border-primary-subtle">All Production Venues &amp; Backstage</span>
                                            <?php elseif ($u['venue_access_mode'] === 'assigned_only'): ?>
                                                <span class="badge bg-info-subtle text-info border border-info-subtle">Assigned Event Venues &amp; Calltimes</span>
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

        <!-- ==================== TAB 2: GATE SCANNER & CHECK-IN KIOSK ==================== -->
        <div class="tab-pane fade <?php echo ($initialTab === 'kiosk') ? 'show active' : ''; ?> no-print" id="pane-kiosk" role="tabpanel">
            <div class="row g-4">
                <!-- Left Column: Kiosk Checkpoint & Scanner Input -->
                <div class="col-lg-5">
                    <div class="card border-0 shadow-sm rounded-4">
                        <div class="card-header bg-dark text-white py-3 rounded-top-4">
                            <h5 class="mb-0 fw-bold"><i class="fas fa-qrcode text-warning me-2"></i>Attendance &amp; Gate Access Terminal</h5>
                            <div class="small text-white-50">Verify badge clearance &amp; record attendance at Stock or Venues</div>
                        </div>
                        <div class="card-body p-4">
                            <form id="gateKioskForm" onsubmit="submitGateScan(event)">
                                <!-- Step 1: Select Checkpoint Type -->
                                <label class="form-label fw-bold small text-uppercase text-muted">1. Select Checkpoint Category</label>
                                <div class="row g-2 mb-3">
                                    <div class="col-6">
                                        <input type="radio" class="btn-check" name="checkpoint_type" id="cp_type_stock" value="stock_warehouse" checked onchange="toggleCheckpointDropdown()">
                                        <label class="btn btn-outline-success w-100 fw-bold py-2" for="cp_type_stock">
                                            <i class="fas fa-warehouse d-block fs-4 mb-1"></i>Stock Warehouse
                                        </label>
                                    </div>
                                    <div class="col-6">
                                        <input type="radio" class="btn-check" name="checkpoint_type" id="cp_type_venue" value="event_venue" onchange="toggleCheckpointDropdown()">
                                        <label class="btn btn-outline-primary w-100 fw-bold py-2" for="cp_type_venue">
                                            <i class="fas fa-location-dot d-block fs-4 mb-1"></i>Event Venue
                                        </label>
                                    </div>
                                </div>

                                <!-- Step 2: Specific Stock Location or Event Venue -->
                                <div class="mb-3" id="stockCheckpointSelectWrap">
                                    <label class="form-label fw-bold small">2. Active Stock Warehouse Gate</label>
                                    <select class="form-select" id="kiosk_stock_id">
                                        <?php foreach ($stockLocations as $sl): ?>
                                            <option value="<?php echo (int)$sl['id']; ?>">
                                                <?php echo htmlspecialchars($sl['name'] . (!empty($sl['code']) ? ' (' . $sl['code'] . ')' : '')); ?>
                                                — Hours: <?php echo substr($sl['open_time'] ?? '08:00', 0, 5); ?> to <?php echo substr($sl['close_time'] ?? '18:00', 0, 5); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="mb-3 d-none" id="venueCheckpointSelectWrap">
                                    <label class="form-label fw-bold small">2. Active Event Venue &amp; Calltime</label>
                                    <select class="form-select" id="kiosk_event_id">
                                        <?php foreach ($eventsList as $ev): ?>
                                            <option value="<?php echo (int)$ev['id']; ?>">
                                                <?php echo htmlspecialchars($ev['title'] . (!empty($ev['location']) ? ' @ ' . $ev['location'] : '')); ?>
                                                (Calltime: <?php echo htmlspecialchars($ev['call_time'] ?: '08:00'); ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <!-- Step 3: Scan Action Mode -->
                                <div class="mb-3">
                                    <label class="form-label fw-bold small">3. Attendance Action</label>
                                    <select class="form-select form-select-sm" id="kiosk_scan_mode">
                                        <option value="auto">Auto-Detect (Toggle Check-In / Check-Out)</option>
                                        <option value="check_in">Force Check-In (Arrival)</option>
                                        <option value="check_out">Force Check-Out (Departure)</option>
                                        <option value="access_verify">Verify Door Clearance Only</option>
                                    </select>
                                </div>

                                <!-- Step 4: Badge Code / QR Input -->
                                <div class="mb-3">
                                    <label class="form-label fw-bold small">4. Scan QR / RFID or Select Staff Badge</label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-dark text-warning"><i class="fas fa-barcode"></i></span>
                                        <input type="text" class="form-control form-control-lg fw-bold" id="kiosk_scan_input" placeholder="Scan ABL-BDG-0001 or TAX-0001..." required autocomplete="off">
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center mt-2">
                                        <span class="text-muted small">Or quick-pick staff member to simulate scan:</span>
                                        <select class="form-select form-select-sm w-auto" onchange="if(this.value){document.getElementById('kiosk_scan_input').value=this.value;submitGateScan();}">
                                            <option value="">— Quick Test Staff —</option>
                                            <?php foreach ($usersWithBadges as $ub): ?>
                                                <option value="<?php echo htmlspecialchars($ub['badge_code']); ?>">
                                                    <?php echo htmlspecialchars($ub[' badge_code'] ?? $ub['badge_code']); ?> — <?php echo htmlspecialchars($ub['full_name'] ?: $ub['username']); ?> (<?php echo htmlspecialchars($ub['role']); ?>)
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <input type="text" class="form-control form-control-sm" id="kiosk_scan_notes" placeholder="Optional gate note (e.g., Loading Bay 2, Shift handover)...">
                                </div>

                                <button type="submit" class="btn btn-primary w-100 fw-bold py-2 fs-6 shadow-sm">
                                    <i class="fas fa-bolt me-2"></i>Verify Clearance &amp; Record Scan
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Right Column: Live Verification Result Display -->
                <div class="col-lg-7">
                    <div id="kioskResultPlaceholder" class="card border-0 shadow-sm rounded-4 h-100 d-flex align-items-center justify-content-center p-5 text-center">
                        <div>
                            <div class="rounded-circle bg-light d-inline-flex align-items-center justify-content-center mb-3" style="width: 90px; height: 90px;">
                                <i class="fas fa-id-card-clip fa-3x text-secondary"></i>
                            </div>
                            <h5 class="fw-bold text-dark">Ready to Scan Staff ID Badge</h5>
                            <p class="text-muted small mx-auto" style="max-width: 420px;">
                                Present a staff CR80 QR Code, RFID tag, or select a staff member on the left to verify their clearance for the active <strong>Stock Warehouse</strong> or <strong>Event Venue</strong>.
                            </p>
                        </div>
                    </div>

                    <div id="kioskResultActiveCard" class="p-4 shadow-sm d-none">
                        <!-- Populated dynamically by JS on scan -->
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

        <!-- ==================== TAB 4: STOCK & VENUE CLEARANCE MATRIX ==================== -->
        <div class="tab-pane fade <?php echo ($initialTab === 'matrix') ? 'show active' : ''; ?> no-print" id="pane-matrix" role="tabpanel">
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

<script>
    const usersBadgeDirectory = <?php echo json_encode($usersWithBadges); ?>;

    // Render all QR codes on DOM ready
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

        if (document.getElementById('badgeSearchInput').value) {
            filterBadgeGallery();
        }
    });

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
        const isStock = document.getElementById('cp_type_stock').checked;
        document.getElementById('stockCheckpointSelectWrap').classList.toggle('d-none', !isStock);
        document.getElementById('venueCheckpointSelectWrap').classList.toggle('d-none', isStock);
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

                card.innerHTML = `
                    <div class="d-flex justify-content-between align-items-center border-bottom pb-3 mb-3">
                        <div>
                            <span class="badge ${granted ? 'bg-success' : 'bg-danger'} fs-6 px-3 py-2">
                                <i class="fas ${granted ? 'fa-check-circle' : 'fa-triangle-exclamation'} me-2"></i>
                                ${granted ? 'ACCESS GRANTED — ' + actionLabel : 'ACCESS DENIED — ENTRY BLOCKED'}
                            </span>
                        </div>
                        <div class="text-end small text-muted">
                            <div class="fw-bold text-dark">${data.scanned_at}</div>
                            <div>${data.scanned_date}</div>
                        </div>
                    </div>

                    <div class="d-flex align-items-center gap-3 mb-3">
                        <div class="rounded-circle bg-dark text-white d-flex align-items-center justify-content-center fw-bold fs-3" style="width: 72px; height: 72px; flex-shrink: 0;">
                            ${u.full_name.charAt(0).toUpperCase()}
                        </div>
                        <div>
                            <h4 class="fw-bold text-dark mb-1">${u.full_name}</h4>
                            <div class="d-flex flex-wrap gap-2 align-items-center">
                                <span class="badge bg-dark font-monospace">${u.badge_code}</span>
                                <span class="badge bg-secondary font-monospace">${u.staff_code}</span>
                                <span class="badge" style="background: ${u.clearance_color};">${u.clearance_title}</span>
                            </div>
                        </div>
                    </div>

                    <div class="p-3 rounded-3 bg-white border mb-3">
                        <div class="row g-2 small">
                            <div class="col-sm-6">
                                <span class="text-muted d-block">Checkpoint Gate:</span>
                                <strong class="text-dark">${data.checkpoint_name}</strong>
                            </div>
                            <div class="col-sm-6">
                                <span class="text-muted d-block">Role &amp; Department:</span>
                                <strong class="text-dark">${u.role} (${u.department})</strong>
                            </div>
                            <div class="col-12 mt-2 pt-2 border-top">
                                <span class="text-muted d-block">Verification Decision:</span>
                                <strong class="${granted ? 'text-success' : 'text-danger'}">${data.reason}</strong>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-between align-items-center">
                        <a href="id_badges.php?tab=roster" class="btn btn-sm btn-outline-dark fw-bold">
                            <i class="fas fa-clipboard-user me-1"></i>View Updated On-Site Roster
                        </a>
                        <button type="button" class="btn btn-sm btn-outline-primary fw-bold" onclick="openConfigureBadgeModal(${u.id})">
                            <i class="fas fa-sliders me-1"></i>Modify User Clearance
                        </button>
                    </div>
                `;
            });
    }
</script>

<?php require_once 'views/partials/footer.php'; ?>
