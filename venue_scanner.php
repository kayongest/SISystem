<?php
/**
 * venue_scanner.php
 * Shareable Standalone Venue & Stock Camera / Scanner Check-In Kiosk
 * Supports:
 *  1. Logitech USB Webcam / Built-in Camera live QR Code scanning (via html5-qrcode)
 *  2. Hardware USB / Bluetooth QR & Barcode gun input
 *  3. Instant Search by Staff Name, Username, TAX-ID, or Badge Code (ABL-BDG-XXXX)
 */
require_once 'bootstrap.php';
require_once 'includes/functions.php';
require_once 'includes/db_connect.php';
require_once 'includes/badge_attendance_helper.php';

$conn = getConnection();
ensureBadgesAndAttendanceTables($conn);

$clearanceMeta = getClearanceLevelsMeta();
$operatorId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;

// Load Stock Locations
$stockLocations = [];
$stockMap = [];
$stRes = @$conn->query("SELECT id, name, code, location, open_time, close_time FROM stock_locations WHERE is_active = 1 ORDER BY name ASC");
if ($stRes) {
    while ($r = $stRes->fetch_assoc()) {
        $sid = (int)$r['id'];
        $stockLocations[] = $r;
        $stockMap[$sid] = $r;
    }
}
if (empty($stockLocations)) {
    $stockLocations[] = ['id' => 1, 'name' => 'Main Central Warehouse', 'code' => 'WH-MAIN', 'location' => 'Kigali', 'open_time' => '08:00:00', 'close_time' => '18:00:00'];
    $stockMap[1] = $stockLocations[0];
}

// Load Event Venues
$eventsList = [];
$eventMap = [];
$evRes = @$conn->query("SELECT id, title, location, date, call_time, project_manager, tech_lead, assigned_technician FROM events ORDER BY date DESC, id DESC LIMIT 30");
if ($evRes) {
    while ($r = $evRes->fetch_assoc()) {
        $eid = (int)$r['id'];
        $eventsList[] = $r;
        $eventMap[$eid] = $r;
    }
}
if (empty($eventsList)) {
    $eventsList[] = ['id' => 1, 'title' => 'Main Production Venue', 'location' => 'BK Arena, Kigali', 'date' => date('Y-m-d'), 'call_time' => '08:00', 'project_manager' => '', 'tech_lead' => '', 'assigned_technician' => ''];
    $eventMap[1] = $eventsList[0];
}

// ==================== AJAX HANDLER FOR CAMERA / NAME SCAN ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_action']) && $_POST['ajax_action'] === 'process_venue_scan') {
    while (ob_get_level()) {
        ob_end_clean();
    }
    header('Content-Type: application/json');

    $rawInput = trim($_POST['scan_input'] ?? '');
    if (strpos($rawInput, '|') !== false) {
        $parts = explode('|', $rawInput);
        $rawInput = trim($parts[0]);
    }

    $checkpointType = $_POST['checkpoint_type'] ?? 'event_venue';
    if (!in_array($checkpointType, ['event_venue', 'stock_warehouse'], true)) {
        $checkpointType = 'event_venue';
    }
    $checkpointId = (int)($_POST['checkpoint_id'] ?? 0);
    $scanMode = $_POST['scan_mode'] ?? 'auto';
    $scanSource = trim($_POST['scan_source'] ?? 'Logitech Camera / Scanner');

    $checkpointName = 'Event Venue Gate';
    if ($checkpointType === 'event_venue') {
        $checkpointName = isset($eventMap[$checkpointId])
            ? ($eventMap[$checkpointId]['title'] . (!empty($eventMap[$checkpointId]['location']) ? ' — ' . $eventMap[$checkpointId]['location'] : ''))
            : 'Production Event Venue';
    } else {
        $checkpointName = isset($stockMap[$checkpointId])
            ? ($stockMap[$checkpointId]['name'] . (!empty($stockMap[$checkpointId]['code']) ? ' (' . $stockMap[$checkpointId]['code'] . ')' : ''))
            : 'Stock Warehouse Gate';
    }

    $taxUserId = 0;
    if (preg_match('/^TAX-0*(\d+)$/i', $rawInput, $m)) {
        $taxUserId = (int)$m[1];
    } elseif (ctype_digit($rawInput)) {
        $taxUserId = (int)$rawInput;
    }

    $matchedUser = null;
    $findStmt = $conn->prepare("
        SELECT u.*, b.badge_code, b.rfid_uid, b.clearance_level, b.stock_access_mode, b.allowed_stock_ids,
               b.venue_access_mode, b.allowed_event_ids, b.badge_status, b.emergency_contact, b.blood_group,
               b.issued_at, b.expires_at
        FROM users u
        INNER JOIN user_badges b ON u.id = b.user_id
        WHERE b.badge_code = ? OR b.rfid_uid = ? OR u.username = ? OR u.full_name = ? OR u.id = ?
        LIMIT 1
    ");
    if ($findStmt) {
        $findStmt->bind_param("ssssi", $rawInput, $rawInput, $rawInput, $rawInput, $taxUserId);
        $findStmt->execute();
        $res = $findStmt->get_result();
        if ($row = $res->fetch_assoc()) {
            $matchedUser = $row;
        }
        $findStmt->close();
    }

    if (!$matchedUser) {
        echo json_encode([
            'success' => false,
            'access_status' => 'denied',
            'message' => 'Unrecognized Badge or Staff Name: "' . htmlspecialchars($rawInput) . '"',
            'checkpoint_name' => $checkpointName
        ]);
        exit();
    }

    $uid = (int)$matchedUser['id'];
    $badgeCode = $matchedUser['badge_code'];

    // Resolve auto check_in vs check_out
    $resolvedAction = $scanMode;
    if ($scanMode === 'auto') {
        $resolvedAction = 'check_in';
        $lastStmt = $conn->prepare("
            SELECT scan_action FROM attendance_logs
            WHERE user_id = ? AND checkpoint_type = ? AND checkpoint_id = ? AND access_status = 'granted'
              AND DATE(scanned_at) = CURDATE() AND scan_action IN ('check_in', 'check_out')
            ORDER BY scanned_at DESC, id DESC LIMIT 1
        ");
        if ($lastStmt) {
            $lastStmt->bind_param("isi", $uid, $checkpointType, $checkpointId);
            $lastStmt->execute();
            $lr = $lastStmt->get_result();
            if ($lRow = $lr->fetch_assoc()) {
                if ($lRow['scan_action'] === 'check_in') {
                    $resolvedAction = 'check_out';
                }
            }
            $lastStmt->close();
        }
    }

    $eval = evaluateUserCheckpointAccess($conn, $matchedUser, $matchedUser, $checkpointType, $checkpointId, $stockMap, $eventMap);
    $accessStatus = $eval['granted'] ? 'granted' : 'denied';
    $denialReason = $eval['granted'] ? null : $eval['reason'];
    $punctuality = ($resolvedAction === 'check_out') ? 'n/a' : ($eval['punctuality'] ?? 'on_time');
    $notes = $eval['reason'] . ' [' . $scanSource . ']';
    $nowStr = date('Y-m-d H:i:s');

    $insStmt = $conn->prepare("
        INSERT INTO attendance_logs
        (user_id, badge_code, checkpoint_type, checkpoint_id, checkpoint_name, scan_action, access_status, denial_reason, punctuality_status, scanned_by, notes, scanned_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    if ($insStmt) {
        $insStmt->bind_param(
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
            $operatorId,
            $notes,
            $nowStr
        );
        $insStmt->execute();
        $insStmt->close();
    }

    $cInfo = $clearanceMeta[$matchedUser['clearance_level']] ?? $clearanceMeta['L1_VENUE_BASIC'];
    $hasPhoto = !empty($matchedUser['profile_image']) && file_exists(__DIR__ . '/' . $matchedUser['profile_image']);

    echo json_encode([
        'success' => true,
        'access_status' => $accessStatus,
        'scan_action' => $resolvedAction,
        'punctuality' => $punctuality,
        'reason' => $eval['reason'],
        'scanned_at' => date('h:i:s A', strtotime($nowStr)),
        'checkpoint_name' => $checkpointName,
        'user' => [
            'id' => $uid,
            'full_name' => $matchedUser['full_name'] ?: $matchedUser['username'],
            'username' => $matchedUser['username'],
            'role' => ucwords(str_replace('_', ' ', $matchedUser['role'])),
            'department' => ucwords($matchedUser['department'] ?: 'Production Crew'),
            'staff_code' => 'TAX-' . str_pad((string)$uid, 4, '0', STR_PAD_LEFT),
            'badge_code' => $badgeCode,
            'profile_image' => $hasPhoto ? $matchedUser['profile_image'] : '',
            'clearance_code' => $cInfo['code'],
            'clearance_title' => $cInfo['title'],
            'clearance_color' => $cInfo['color']
        ]
    ]);
    exit();
}

// Fetch all active staff for instant client-side Name Search
$staffDirectory = [];
$uRes = $conn->query("
    SELECT u.id, u.username, u.full_name, u.role, u.department, u.phone, u.profile_image, u.is_active,
           b.badge_code, b.rfid_uid, b.clearance_level, b.badge_status
    FROM users u
    INNER JOIN user_badges b ON u.id = b.user_id
    ORDER BY COALESCE(NULLIF(u.full_name, ''), u.username) ASC
");
if ($uRes) {
    while ($u = $uRes->fetch_assoc()) {
        $uid = (int)$u['id'];
        $cInfo = $clearanceMeta[$u['clearance_level']] ?? $clearanceMeta['L1_VENUE_BASIC'];
        $hasPhoto = !empty($u['profile_image']) && file_exists(__DIR__ . '/' . $u['profile_image']);
        $staffDirectory[] = [
            'id' => $uid,
            'full_name' => $u['full_name'] ?: $u['username'],
            'username' => $u['username'],
            'role' => ucwords(str_replace('_', ' ', $u['role'])),
            'department' => ucwords($u['department'] ?: 'Operations'),
            'staff_code' => 'TAX-' . str_pad((string)$uid, 4, '0', STR_PAD_LEFT),
            'badge_code' => $u['badge_code'],
            'rfid_uid' => $u['rfid_uid'] ?: '',
            'profile_image' => $hasPhoto ? $u['profile_image'] : '',
            'clearance_code' => $cInfo['code'],
            'clearance_title' => $cInfo['short'],
            'clearance_color' => $cInfo['color'],
            'is_active' => ((int)$u['is_active'] === 1 && $u['badge_status'] === 'active')
        ];
    }
}

// Pre-selected URL parameters for sharing with a specific venue or stock gate
$defaultType = isset($_GET['type']) && in_array($_GET['type'], ['event_venue', 'stock_warehouse'], true)
    ? $_GET['type']
    : 'event_venue';
$defaultId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Build today's attendance state map per checkpoint: key = "{checkpoint_type}_{checkpoint_id}_{user_id}" => 'checked_in' | 'checked_out'
$todayCheckpointStates = [];
$stateQ = $conn->query("
    SELECT user_id, checkpoint_type, checkpoint_id, scan_action, DATE_FORMAT(scanned_at, '%h:%i %p') as scan_time
    FROM attendance_logs
    WHERE access_status = 'granted'
      AND DATE(scanned_at) = CURDATE()
      AND scan_action IN ('check_in', 'check_out')
    ORDER BY scanned_at ASC, id ASC
");
if ($stateQ) {
    while ($sr = $stateQ->fetch_assoc()) {
        $sKey = $sr['checkpoint_type'] . '_' . (int)$sr['checkpoint_id'] . '_' . (int)$sr['user_id'];
        $todayCheckpointStates[$sKey] = [
            'state' => ($sr['scan_action'] === 'check_in') ? 'checked_in' : 'checked_out',
            'time' => $sr['scan_time']
        ];
    }
}

// Recent scans for initial feed
$recentScans = [];
$recQ = $conn->query("
    SELECT l.*, u.full_name, u.username, u.role
    FROM attendance_logs l
    LEFT JOIN users u ON l.user_id = u.id
    ORDER BY l.scanned_at DESC, l.id DESC
    LIMIT 12
");
if ($recQ) {
    while ($r = $recQ->fetch_assoc()) {
        $recentScans[] = $r;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Venue &amp; Stock Badge Scanner — aBility Gate Kiosk</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <!-- HTML5-QRCode library for Logitech USB Webcams & Camera scanning -->
    <script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Titillium+Web:wght@400;600;700;900&family=JetBrains+Mono:wght@500;700&display=swap');

        body {
            font-family: "Titillium Web", sans-serif;
            background: #0f172a;
            color: #f8fafc;
            min-height: 100vh;
        }

        .kiosk-topbar {
            background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            padding: 0.85rem 1.5rem;
        }

        .kiosk-card {
            background: #1e293b;
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 18px;
            box-shadow: 0 12px 30px rgba(0, 0, 0, 0.35);
        }

        /* Camera Viewport Styling */
        .camera-stage-wrap {
            position: relative;
            background: #020617;
            border-radius: 14px;
            overflow: hidden;
            min-height: 290px;
            border: 2px solid #334155;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        #logitechCameraReader {
            width: 100%;
        }
        #logitechCameraReader video {
            border-radius: 12px;
            object-fit: cover;
        }
        .camera-mirror video {
            transform: scaleX(-1);
        }

        /* Laser Scan Overlay */
        .scan-laser-line {
            position: absolute;
            left: 12%;
            right: 12%;
            height: 2px;
            background: linear-gradient(90deg, transparent, #10b981, #34d399, #10b981, transparent);
            box-shadow: 0 0 12px #10b981;
            animation: scanSweep 2.4s infinite ease-in-out;
            pointer-events: none;
            z-index: 5;
        }
        @keyframes scanSweep {
            0% { top: 18%; opacity: 0.6; }
            50% { top: 82%; opacity: 1; }
            100% { top: 18%; opacity: 0.6; }
        }

        /* Name Search Results List */
        .name-search-results {
            max-height: 330px;
            overflow-y: auto;
        }
        .staff-result-item {
            background: rgba(15, 23, 42, 0.65);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 12px;
            padding: 10px 14px;
            margin-bottom: 8px;
            transition: all 0.18s ease;
        }
        .staff-result-item:hover {
            background: rgba(30, 41, 59, 0.95);
            border-color: #38bdf8;
            transform: translateX(2px);
        }

        /* Result Status Banner */
        .result-banner-granted {
            background: linear-gradient(135deg, rgba(16, 185, 129, 0.22) 0%, rgba(15, 23, 42, 0.9) 100%);
            border: 2px solid #10b981;
            border-radius: 16px;
        }
        .result-banner-denied {
            background: linear-gradient(135deg, rgba(239, 68, 68, 0.22) 0%, rgba(15, 23, 42, 0.9) 100%);
            border: 2px solid #ef4444;
            border-radius: 16px;
        }
    </style>
</head>
<body>

<!-- Top Kiosk Bar -->
<header class="kiosk-topbar d-flex flex-wrap justify-content-between align-items-center gap-3">
    <div class="d-flex align-items-center gap-3">
        <div class="rounded-3 bg-warning text-dark d-flex align-items-center justify-content-center fw-bold" style="width: 42px; height: 42px;">
            <i class="fas fa-camera-retro fs-5"></i>
        </div>
        <div>
            <div class="d-flex align-items-center gap-2">
                <h5 class="mb-0 fw-bold text-white">aBility Venue &amp; Stock Scanner</h5>
                <span class="badge bg-success-subtle text-success border border-success-subtle small">
                    <i class="fas fa-video me-1"></i>Logitech Camera &amp; USB Scanner Ready
                </span>
            </div>
            <div class="small text-white-50">Scan staff ID badge QR code with camera/scanner or search by staff name</div>
        </div>
    </div>

    <div class="d-flex flex-wrap align-items-center gap-2">
        <button type="button" class="btn btn-sm btn-warning fw-bold text-dark" onclick="copyShareableVenueLink()">
            <i class="fas fa-share-nodes me-1"></i>Copy Shareable Venue Link
        </button>
        <?php if (isLoggedIn()): ?>
            <a href="id_badges.php" class="btn btn-sm btn-outline-light">
                <i class="fas fa-id-card me-1"></i>All Badges
            </a>
            <a href="users.php" class="btn btn-sm btn-outline-info">
                <i class="fas fa-users me-1"></i>Users
            </a>
        <?php endif; ?>
    </div>
</header>

<div class="container-fluid py-4 px-3 px-lg-4" style="max-width: 1460px;">
    <!-- Active Venue / Stock Checkpoint Selector Strip -->
    <div class="kiosk-card p-3 mb-4">
        <div class="row g-3 align-items-center">
            <div class="col-md-3">
                <label class="form-label small text-info fw-bold text-uppercase mb-1">
                    <i class="fas fa-Broadcast-tower me-1"></i>1. Checkpoint Type
                </label>
                <div class="btn-group w-100" role="group">
                    <input type="radio" class="btn-check" name="kiosk_cp_type" id="type_venue" value="event_venue" <?php echo ($defaultType === 'event_venue') ? 'checked' : ''; ?> onchange="switchCheckpointType()">
                    <label class="btn btn-outline-info fw-bold btn-sm py-2" for="type_venue">
                        <i class="fas fa-location-dot me-1"></i>Event Venue
                    </label>
                    <input type="radio" class="btn-check" name="kiosk_cp_type" id="type_stock" value="stock_warehouse" <?php echo ($defaultType === 'stock_warehouse') ? 'checked' : ''; ?> onchange="switchCheckpointType()">
                    <label class="btn btn-outline-success fw-bold btn-sm py-2" for="type_stock">
                        <i class="fas fa-warehouse me-1"></i>Stock Warehouse
                    </label>
                </div>
            </div>

            <div class="col-md-5">
                <div id="venueSelectBox" class="<?php echo ($defaultType === 'event_venue') ? '' : 'd-none'; ?>">
                    <label class="form-label small text-white-50 fw-bold text-uppercase mb-1">2. Active Event Venue</label>
                    <select id="active_venue_id" class="form-select bg-dark text-white border-secondary fw-semibold" onchange="syncAllStaffButtonStates()">
                        <?php foreach ($eventsList as $ev): ?>
                            <option value="<?php echo (int)$ev['id']; ?>" <?php echo ($defaultId === (int)$ev['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($ev['title'] . (!empty($ev['location']) ? ' — ' . $ev['location'] : '') . ' (Calltime: ' . ($ev['call_time'] ?: '08:00') . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div id="stockSelectBox" class="<?php echo ($defaultType === 'stock_warehouse') ? '' : 'd-none'; ?>">
                    <label class="form-label small text-white-50 fw-bold text-uppercase mb-1">2. Active Stock Warehouse</label>
                    <select id="active_stock_id" class="form-select bg-dark text-white border-secondary fw-semibold" onchange="syncAllStaffButtonStates()">
                        <?php foreach ($stockLocations as $sl): ?>
                            <option value="<?php echo (int)$sl['id']; ?>" <?php echo ($defaultId === (int)$sl['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($sl['name'] . (!empty($sl['code']) ? ' (' . $sl['code'] . ')' : '')); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="col-md-4">
                <label class="form-label small text-white-50 fw-bold text-uppercase mb-1">3. Scan Action Mode</label>
                <select id="active_scan_mode" class="form-select bg-dark text-white border-secondary fw-semibold">
                    <option value="auto">Auto-Toggle (Check-In on Arrival / Check-Out on Exit)</option>
                    <option value="check_in">Check-In Only (Venue / Stock Arrival)</option>
                    <option value="check_out">Check-Out Only (Departure)</option>
                    <option value="access_verify">Verify Badge Access Only</option>
                </select>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <!-- ==================== LEFT: LOGITECH CAMERA & HARDWARE SCANNER ==================== -->
        <div class="col-lg-5">
            <div class="kiosk-card p-4 h-100">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <h5 class="fw-bold mb-0 text-white">
                            <i class="fas fa-camera text-warning me-2"></i>Option A: Scan Badge with Camera / Scanner
                        </h5>
                        <div class="small text-white-50">Hold ID badge QR code in front of the Logitech camera or scan with USB gun</div>
                    </div>
                    <button type="button" class="btn btn-xs btn-outline-light" onclick="toggleMirrorCamera()" title="Flip / Mirror Camera">
                        <i class="fas fa-arrows-left-right"></i> Mirror
                    </button>
                </div>

                <!-- Camera Device Dropdown + Start/Stop Buttons -->
                <div class="row g-2 mb-3">
                    <div class="col-sm-7">
                        <select id="cameraDeviceSelect" class="form-select form-select-sm bg-dark text-white border-secondary" onchange="restartWithSelectedCamera()">
                            <option value="">Detecting Logitech / USB Cameras...</option>
                        </select>
                    </div>
                    <div class="col-sm-5 d-flex gap-2">
                        <button type="button" id="btnStartCam" class="btn btn-sm btn-success fw-bold flex-grow-1" onclick="startLogitechCamera()">
                            <i class="fas fa-play me-1"></i>Start Camera
                        </button>
                        <button type="button" id="btnStopCam" class="btn btn-sm btn-outline-danger fw-bold d-none" onclick="stopLogitechCamera()">
                            <i class="fas fa-stop me-1"></i>Stop
                        </button>
                    </div>
                </div>

                <!-- Live Camera Viewport -->
                <div class="camera-stage-wrap camera-mirror mb-3" id="cameraStageWrap">
                    <div class="scan-laser-line d-none" id="scanLaserLine"></div>
                    <div id="logitechCameraReader"></div>
                    <div id="cameraPlaceholderMsg" class="text-center p-4">
                        <i class="fas fa-video fa-3x text-info mb-3 opacity-75"></i>
                        <h6 class="fw-bold text-white mb-1">Logitech Camera Ready</h6>
                        <p class="small text-white-50 mb-3">Click <strong>Start Camera</strong> above to activate live QR scanning on your Logitech webcam.</p>
                        <button type="button" class="btn btn-sm btn-info fw-bold text-dark px-3" onclick="startLogitechCamera()">
                            <i class="fas fa-camera me-1"></i>Activate Logitech Camera
                        </button>
                    </div>
                </div>

                <!-- Hardware USB Barcode / QR Scanner Input Box -->
                <div class="p-3 rounded-3" style="background: rgba(15, 23, 42, 0.7); border: 1px solid rgba(255, 255, 255, 0.1);">
                    <label class="form-label small fw-bold text-warning mb-1">
                        <i class="fas fa-barcode me-1"></i>USB / Handheld Scanner Input (Auto-Submit on Scan)
                    </label>
                    <form onsubmit="handleManualBarcodeSubmit(event)">
                        <div class="input-group">
                            <input type="text" id="hardwareScannerInput" class="form-control bg-dark text-white border-secondary font-monospace fw-bold" placeholder="Click here &amp; scan badge (ABL-BDG-0001)..." autocomplete="off">
                            <button type="submit" class="btn btn-warning fw-bold text-dark">
                                <i class="fas fa-bolt me-1"></i>Scan
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- ==================== MIDDLE: SEARCH BY STAFF NAME ==================== -->
        <div class="col-lg-4">
            <div class="kiosk-card p-4 h-100">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div>
                        <h5 class="fw-bold mb-0 text-white">
                            <i class="fas fa-magnifying-glass text-info me-2"></i>Option B: Search Staff by Name
                        </h5>
                        <div class="small text-white-50">Type name &rarr; Find &rarr; Click <strong>Checkin</strong> then <strong>Checkout</strong></div>
                    </div>
                    <span class="badge bg-dark border border-secondary text-info" id="staffMatchCountBadge"><?php echo count($staffDirectory); ?> Staff</span>
                </div>

                <!-- Instant Name Search Input -->
                <form onsubmit="event.preventDefault();filterStaffByName();" class="mb-3">
                    <div class="input-group">
                        <span class="input-group-text bg-dark border-secondary text-info"><i class="fas fa-search"></i></span>
                        <input type="text" id="staffNameSearchInput" class="form-control bg-dark text-white border-secondary fw-semibold" placeholder="Type staff name to find..." oninput="filterStaffByName()" autocomplete="off">
                        <button type="submit" class="btn btn-info fw-bold text-dark px-3">
                            Find
                        </button>
                        <button type="button" class="btn btn-outline-secondary text-white" onclick="document.getElementById('staffNameSearchInput').value='';filterStaffByName();" title="Clear search">
                            <i class="fas fa-xmark"></i>
                        </button>
                    </div>
                </form>

                <!-- Filtered Staff List -->
                <div class="name-search-results" id="staffNameResultsList">
                    <?php foreach ($staffDirectory as $st):
                        $uid = (int)$st['id'];
                    ?>
                        <div class="staff-result-item"
                             id="staff-row-<?php echo $uid; ?>"
                             data-uid="<?php echo $uid; ?>"
                             data-badge="<?php echo htmlspecialchars($st['badge_code']); ?>"
                             data-search="<?php echo strtolower(htmlspecialchars($st['full_name'] . ' ' . $st['username'] . ' ' . $st['badge_code'] . ' ' . $st['staff_code'] . ' ' . $st['role'] . ' ' . $st['department'])); ?>">
                            <div class="d-flex justify-content-between align-items-center gap-2">
                                <div class="d-flex align-items-center gap-2 overflow-hidden">
                                    <?php if (!empty($st['profile_image'])): ?>
                                        <img src="<?php echo htmlspecialchars($st['profile_image']); ?>"
                                             style="width: 42px; height: 42px; border-radius: 50%; object-fit: cover; flex-shrink: 0;"
                                             onerror="this.onerror=null;this.src='data:image/svg+xml;utf8,<svg xmlns=%22http://www.w3.org/2000/svg%22 width=%2242%22 height=%2242%22 viewBox=%220 0 42 42%22><rect width=%2242%22 height=%2242%22 fill=%22%23334155%22/><circle cx=%2221%22 cy=%2216%22 r=%227%22 fill=%22%2394a3b8%22/><path d=%22M8 38c0-7 6-13 13-13s13 6 13 13%22 fill=%22%2394a3b8%22/></svg>';">
                                    <?php else: ?>
                                        <div class="rounded-circle d-flex align-items-center justify-content-center fw-bold text-white"
                                             style="width: 42px; height: 42px; background: <?php echo $st['clearance_color']; ?>; flex-shrink: 0;">
                                            <?php echo strtoupper(substr($st['full_name'], 0, 1)); ?>
                                        </div>
                                    <?php endif; ?>
                                    <div class="text-truncate">
                                        <div class="d-flex align-items-center gap-2">
                                            <span class="fw-bold text-white text-truncate"><?php echo htmlspecialchars($st['full_name']); ?></span>
                                            <span id="staff-status-pill-<?php echo $uid; ?>" class="badge bg-secondary-subtle text-secondary" style="font-size: 0.62rem;">Ready</span>
                                        </div>
                                        <div class="d-flex align-items-center gap-1 mt-1" style="font-size: 0.72rem;">
                                            <span class="badge font-monospace bg-dark border border-secondary"><?php echo htmlspecialchars($st['badge_code']); ?></span>
                                            <span class="badge" style="background: <?php echo $st['clearance_color']; ?>;"><?php echo htmlspecialchars($st['clearance_code']); ?></span>
                                            <span class="text-white-50 text-truncate"><?php echo htmlspecialchars($st['role']); ?></span>
                                        </div>
                                    </div>
                                </div>

                                <div class="d-flex gap-1 flex-shrink-0">
                                    <button type="button"
                                            id="btn-checkin-<?php echo $uid; ?>"
                                            class="btn btn-sm btn-success fw-bold px-2 py-1"
                                            style="font-size: 0.76rem;"
                                            onclick="handleStaffButtonAction(<?php echo $uid; ?>, '<?php echo htmlspecialchars($st['badge_code']); ?>', 'check_in')">
                                        <i class="fas fa-sign-in-alt me-1"></i>Checkin
                                    </button>
                                    <button type="button"
                                            id="btn-checkout-<?php echo $uid; ?>"
                                            class="btn btn-sm btn-warning fw-bold text-dark px-2 py-1"
                                            style="font-size: 0.76rem;"
                                            disabled
                                            onclick="handleStaffButtonAction(<?php echo $uid; ?>, '<?php echo htmlspecialchars($st['badge_code']); ?>', 'check_out')">
                                        <i class="fas fa-sign-out-alt me-1"></i>Checkout
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <div id="noStaffFoundMsg" class="text-center py-4 text-white-50 d-none">
                        <i class="fas fa-user-slash fa-2x mb-2 opacity-50"></i>
                        <div class="small">No staff member matches your search.</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ==================== RIGHT: LIVE SCAN VERIFICATION & FEED ==================== -->
        <div class="col-lg-3">
            <div class="kiosk-card p-4 h-100 d-flex flex-column justify-content-between">
                <div>
                    <h5 class="fw-bold mb-3 text-white">
                        <i class="fas fa-shield-check text-success me-2"></i>Live Gate Decision
                    </h5>

                    <!-- Verification Result Box -->
                    <div id="liveScanDecisionBox" class="p-3 rounded-4 text-center mb-3" style="background: rgba(15, 23, 42, 0.75); border: 1px dashed #475569;">
                        <i class="fas fa-id-badge fa-2x text-white-50 mb-2"></i>
                        <div class="fw-bold text-white">Waiting for Badge Scan...</div>
                        <div class="small text-white-50">Scan via Logitech camera, USB scanner, or click a name</div>
                    </div>

                    <!-- Recent Scans Feed -->
                    <h6 class="fw-bold text-uppercase small text-white-50 mb-2">
                        <i class="fas fa-clock-rotate-left me-1"></i>Recent Gate Scans
                    </h6>
                    <div id="recentVenueScansFeed" class="overflow-auto" style="max-height: 250px;">
                        <?php foreach ($recentScans as $rs): ?>
                            <div class="p-2 rounded-3 mb-2 small" style="background: rgba(15, 23, 42, 0.6); border-left: 3px solid <?php echo ($rs['access_status'] === 'granted') ? '#10b981' : '#ef4444'; ?>;">
                                <div class="d-flex justify-content-between">
                                    <strong class="text-white"><?php echo htmlspecialchars($rs['full_name'] ?: $rs['username']); ?></strong>
                                    <span class="text-white-50" style="font-size: 0.7rem;"><?php echo date('h:i A', strtotime($rs['scanned_at'])); ?></span>
                                </div>
                                <div class="d-flex justify-content-between align-items-center mt-1">
                                    <span class="text-white-50 text-truncate" style="max-width: 150px; font-size: 0.72rem;"><?php echo htmlspecialchars($rs['checkpoint_name']); ?></span>
                                    <?php if ($rs['access_status'] === 'granted'): ?>
                                        <span class="badge bg-success" style="font-size: 0.65rem;"><?php echo strtoupper(str_replace('_', ' ', $rs['scan_action'])); ?></span>
                                    <?php else: ?>
                                        <span class="badge bg-danger" style="font-size: 0.65rem;">DENIED</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    let html5QrScanner = null;
    let isCameraRunning = false;
    let lastScannedCode = '';
    let lastScanTimestamp = 0;

    // Today's checkpoint attendance state map: "{cpType}_{cpId}_{userId}" => { state: 'checked_in'|'checked_out', time: '03:15 PM' }
    const checkpointStateMap = <?php echo json_encode($todayCheckpointStates); ?>;

    function getActiveCheckpointKeyPrefix() {
        const isVenue = document.getElementById('type_venue').checked;
        const cpType = isVenue ? 'event_venue' : 'stock_warehouse';
        const cpId = isVenue ? document.getElementById('active_venue_id').value : document.getElementById('active_stock_id').value;
        return cpType + '_' + cpId + '_';
    }

    function applyStaffButtonState(uid, stateObj) {
        const btnIn = document.getElementById('btn-checkin-' + uid);
        const btnOut = document.getElementById('btn-checkout-' + uid);
        const pill = document.getElementById('staff-status-pill-' + uid);
        if (!btnIn || !btnOut) return;

        const state = stateObj ? stateObj.state : 'ready';
        const timeStr = stateObj && stateObj.time ? stateObj.time : '';

        if (state === 'checked_in') {
            // Checkin is disabled; Checkout is enabled
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
            // Both Checkin and Checkout are disabled after Checkout completes
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
            // Initial Ready state: Checkin is enabled; Checkout is disabled
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

    function syncAllStaffButtonStates() {
        const prefix = getActiveCheckpointKeyPrefix();
        document.querySelectorAll('.staff-result-item').forEach(function(row) {
            const uid = row.getAttribute('data-uid');
            const st = checkpointStateMap[prefix + uid] || null;
            applyStaffButtonState(uid, st);
        });
    }

    function handleStaffButtonAction(uid, badgeCode, action) {
        const btnIn = document.getElementById('btn-checkin-' + uid);
        const btnOut = document.getElementById('btn-checkout-' + uid);
        if (action === 'check_in' && btnIn) {
            btnIn.disabled = true;
        } else if (action === 'check_out' && btnOut) {
            btnOut.disabled = true;
        }
        triggerVenueScan(badgeCode, action, 'Name Search', uid);
    }

    // Web Audio API feedback beep for Logitech Camera / USB Scanner
    function playScanTone(granted) {
        try {
            const ctx = new (window.AudioContext || window.webkitAudioContext)();
            const osc = ctx.createOscillator();
            const gain = ctx.createGain();
            osc.connect(gain);
            gain.connect(ctx.destination);

            if (granted) {
                osc.type = 'sine';
                osc.frequency.setValueAtTime(880, ctx.currentTime);
                osc.frequency.setValueAtTime(1320, ctx.currentTime + 0.09);
                gain.gain.setValueAtTime(0.18, ctx.currentTime);
                gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.25);
                osc.start();
                osc.stop(ctx.currentTime + 0.25);
            } else {
                osc.type = 'sawtooth';
                osc.frequency.setValueAtTime(220, ctx.currentTime);
                gain.gain.setValueAtTime(0.22, ctx.currentTime);
                gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.38);
                osc.start();
                osc.stop(ctx.currentTime + 0.38);
            }
        } catch (e) {}
    }

    function switchCheckpointType() {
        const isVenue = document.getElementById('type_venue').checked;
        document.getElementById('venueSelectBox').classList.toggle('d-none', !isVenue);
        document.getElementById('stockSelectBox').classList.toggle('d-none', isVenue);
        syncAllStaffButtonStates();
    }

    function filterStaffByName() {
        const q = (document.getElementById('staffNameSearchInput').value || '').toLowerCase().trim();
        let matchCount = 0;
        document.querySelectorAll('.staff-result-item').forEach(function(item) {
            const hay = item.getAttribute('data-search') || '';
            const matches = (q === '' || hay.indexOf(q) !== -1);
            item.classList.toggle('d-none', !matches);
            if (matches) matchCount++;
        });
        const badge = document.getElementById('staffMatchCountBadge');
        if (badge) badge.textContent = matchCount + ' Found';
        const noMsg = document.getElementById('noStaffFoundMsg');
        if (noMsg) noMsg.classList.toggle('d-none', matchCount > 0);
    }

    function toggleMirrorCamera() {
        document.getElementById('cameraStageWrap').classList.toggle('camera-mirror');
    }

    // Enumerate cameras and prioritize Logitech webcams if connected
    document.addEventListener('DOMContentLoaded', function() {
        syncAllStaffButtonStates();
        if (typeof Html5Qrcode !== 'undefined') {
            Html5Qrcode.getCameras().then(function(devices) {
                const select = document.getElementById('cameraDeviceSelect');
                select.innerHTML = '';
                if (!devices || devices.length === 0) {
                    select.innerHTML = '<option value="">No webcam detected (Plug in Logitech camera)</option>';
                    return;
                }
                let preferredIdx = 0;
                devices.forEach(function(dev, idx) {
                    const label = dev.label || ('Camera #' + (idx + 1));
                    if (label.toLowerCase().indexOf('logi') !== -1 || label.toLowerCase().indexOf('webcam') !== -1 || label.toLowerCase().indexOf('usb') !== -1) {
                        preferredIdx = idx;
                    }
                    const opt = document.createElement('option');
                    opt.value = dev.id;
                    opt.textContent = label;
                    select.appendChild(opt);
                });
                select.selectedIndex = preferredIdx;
            }).catch(function() {
                document.getElementById('cameraDeviceSelect').innerHTML = '<option value="">Click Start Camera to grant webcam permission</option>';
            });
        }
    });

    function startLogitechCamera() {
        if (typeof Html5Qrcode === 'undefined') {
            Swal.fire('Camera Library Unavailable', 'Please check your internet connection or use the USB scanner / Name search.', 'warning');
            return;
        }
        if (isCameraRunning) return;

        document.getElementById('cameraPlaceholderMsg').classList.add('d-none');
        document.getElementById('scanLaserLine').classList.remove('d-none');
        document.getElementById('btnStartCam').classList.add('d-none');
        document.getElementById('btnStopCam').classList.remove('d-none');

        if (!html5QrScanner) {
            html5QrScanner = new Html5Qrcode('logitechCameraReader');
        }

        const selectedCamId = document.getElementById('cameraDeviceSelect').value;
        const cameraConfig = selectedCamId ? { deviceId: { exact: selectedCamId } } : { facingMode: 'environment' };

        html5QrScanner.start(
            cameraConfig,
            { fps: 12, qrbox: { width: 220, height: 220 } },
            function(decodedText) {
                const now = Date.now();
                // 3-second cooldown for identical badge code so holding card doesn't spam
                if (decodedText === lastScannedCode && (now - lastScanTimestamp) < 3000) {
                    return;
                }
                lastScannedCode = decodedText;
                lastScanTimestamp = now;
                triggerVenueScan(decodedText, null, 'Logitech Camera');
            },
            function() {}
        ).then(function() {
            isCameraRunning = true;
        }).catch(function(err) {
            document.getElementById('cameraPlaceholderMsg').classList.remove('d-none');
            document.getElementById('scanLaserLine').classList.add('d-none');
            document.getElementById('btnStartCam').classList.remove('d-none');
            document.getElementById('btnStopCam').classList.add('d-none');
            Swal.fire('Camera Access Required', 'Could not start webcam (' + err + '). Ensure your Logitech camera is plugged in and browser camera permission is allowed.', 'warning');
        });
    }

    function stopLogitechCamera() {
        if (!html5QrScanner || !isCameraRunning) return;
        html5QrScanner.stop().then(function() {
            isCameraRunning = false;
            document.getElementById('cameraPlaceholderMsg').classList.remove('d-none');
            document.getElementById('scanLaserLine').classList.add('d-none');
            document.getElementById('btnStartCam').classList.remove('d-none');
            document.getElementById('btnStopCam').classList.add('d-none');
        });
    }

    function restartWithSelectedCamera() {
        if (isCameraRunning) {
            html5QrScanner.stop().then(function() {
                isCameraRunning = false;
                startLogitechCamera();
            });
        }
    }

    function handleManualBarcodeSubmit(e) {
        e.preventDefault();
        const inp = document.getElementById('hardwareScannerInput');
        const code = inp.value.trim();
        if (!code) return;
        inp.value = '';
        triggerVenueScan(code, null, 'USB Barcode/QR Scanner');
    }

    function triggerVenueScan(scanInput, overrideMode, sourceLabel, fallbackUid) {
        const isVenue = document.getElementById('type_venue').checked;
        const cpType = isVenue ? 'event_venue' : 'stock_warehouse';
        const cpId = isVenue ? document.getElementById('active_venue_id').value : document.getElementById('active_stock_id').value;
        const scanMode = overrideMode || document.getElementById('active_scan_mode').value;

        const fd = new FormData();
        fd.append('ajax_action', 'process_venue_scan');
        fd.append('scan_input', scanInput);
        fd.append('checkpoint_type', cpType);
        fd.append('checkpoint_id', cpId);
        fd.append('scan_mode', scanMode);
        fd.append('scan_source', sourceLabel || 'Gate Kiosk');

        fetch('venue_scanner.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(data => {
                const box = document.getElementById('liveScanDecisionBox');
                box.className = 'p-3 rounded-4 text-center mb-3';

                if (!data.success && !data.user) {
                    playScanTone(false);
                    syncAllStaffButtonStates();
                    box.classList.add('result-banner-denied');
                    box.innerHTML = `
                        <span class="badge bg-danger px-3 py-2 mb-2"><i class="fas fa-ban me-1"></i>ACCESS DENIED</span>
                        <div class="fw-bold text-white">${data.message || 'Unrecognized Badge'}</div>
                        <div class="small text-white-50 mt-1">${data.checkpoint_name || ''}</div>
                    `;
                    return;
                }

                const granted = data.access_status === 'granted';
                playScanTone(granted);
                box.classList.add(granted ? 'result-banner-granted' : 'result-banner-denied');

                const u = data.user;
                const stateKey = cpType + '_' + cpId + '_' + u.id;

                if (granted && (data.scan_action === 'check_in' || data.scan_action === 'check_out')) {
                    checkpointStateMap[stateKey] = {
                        state: data.scan_action === 'check_in' ? 'checked_in' : 'checked_out',
                        time: data.scanned_at
                    };
                    applyStaffButtonState(u.id, checkpointStateMap[stateKey]);
                } else {
                    syncAllStaffButtonStates();
                }

                const actionBadge = data.scan_action === 'check_in' ? 'CHECKED IN' :
                                   (data.scan_action === 'check_out' ? 'CHECKED OUT' : 'VERIFIED');

                box.innerHTML = `
                    <span class="badge ${granted ? 'bg-success' : 'bg-danger'} fs-6 px-3 py-2 mb-2">
                        <i class="fas ${granted ? 'fa-circle-check' : 'fa-triangle-exclamation'} me-1"></i>
                        ${granted ? 'ACCESS GRANTED • ' + actionBadge : 'ACCESS DENIED'}
                    </span>
                    <h5 class="fw-bold text-white mb-1">${u.full_name}</h5>
                    <div class="mb-2">
                        <span class="badge bg-dark border border-secondary font-monospace me-1">${u.badge_code}</span>
                        <span class="badge" style="background: ${u.clearance_color};">${u.clearance_code}</span>
                    </div>
                    <div class="small text-white-50 mb-1">${u.role} • ${u.department}</div>
                    <div class="small fw-semibold ${granted ? 'text-success' : 'text-danger'}">${data.reason}</div>
                    <div class="text-white-50 mt-1" style="font-size: 0.7rem;">${data.checkpoint_name} • ${data.scanned_at}</div>
                `;

                // Prepend to recent feed
                const feed = document.getElementById('recentVenueScansFeed');
                const item = document.createElement('div');
                item.className = 'p-2 rounded-3 mb-2 small';
                item.style.background = 'rgba(15, 23, 42, 0.85)';
                item.style.borderLeft = '3px solid ' + (granted ? '#10b981' : '#ef4444');
                item.innerHTML = `
                    <div class="d-flex justify-content-between">
                        <strong class="text-white">${u.full_name}</strong>
                        <span class="text-white-50" style="font-size: 0.7rem;">${data.scanned_at}</span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mt-1">
                        <span class="text-white-50 text-truncate" style="max-width: 150px; font-size: 0.72rem;">${data.checkpoint_name}</span>
                        <span class="badge ${granted ? 'bg-success' : 'bg-danger'}" style="font-size: 0.65rem;">
                            ${granted ? actionBadge : 'DENIED'}
                        </span>
                    </div>
                `;
                feed.prepend(item);
            });
    }

    function copyShareableVenueLink() {
        const isVenue = document.getElementById('type_venue').checked;
        const cpType = isVenue ? 'event_venue' : 'stock_warehouse';
        const cpId = isVenue ? document.getElementById('active_venue_id').value : document.getElementById('active_stock_id').value;
        const shareUrl = window.location.origin + window.location.pathname + '?type=' + encodeURIComponent(cpType) + '&id=' + encodeURIComponent(cpId);

        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(shareUrl).then(function() {
                Swal.fire({
                    icon: 'success',
                    title: 'Venue Scanner Link Copied!',
                    html: 'Share this URL with the venue/stock check-in desk:<br><code class="d-block mt-2 p-2 bg-light text-dark rounded">' + shareUrl + '</code>',
                    confirmButtonText: 'Great!'
                });
            });
        } else {
            prompt('Copy this Shareable Venue Scanner URL:', shareUrl);
        }
    }
</script>
</body>
</html>
