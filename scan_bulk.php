<?php
// At the VERY TOP of scan_2.php
session_start();

// DEBUG SESSION
error_log("=== SCAN_2.PHP SESSION DEBUG ===");
error_log("Session ID: " . session_id());
error_log("User ID: " . ($_SESSION['user_id'] ?? 'NOT SET'));
error_log("Username: " . ($_SESSION['username'] ?? 'NOT SET'));

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Make sure user data is available
$_SESSION['username'] = $_SESSION['username'] ?? 'Stock Controller';
$_SESSION['full_name'] = $_SESSION['full_name'] ?? $_SESSION['username'];

// Then include bootstrap
require_once 'bootstrap.php';

// Check authentication
$isLoggedIn = isLoggedIn();

if (!$isLoggedIn) {
    $_SESSION['redirect_to'] = $_SERVER['REQUEST_URI'];
    $_SESSION['flash_messages']['warning'] = 'Please log in to access the scanner.';
    header('Location: login.php');
    exit();
}

// Get user's role
require_once 'includes/functions.php';
$user_role = getUserRole();

// RESTRICT ACCESS - Technicians and admins only
$allowed_roles = ['technician', 'admin'];
if (!in_array($user_role, $allowed_roles)) {
    if ($user_role === 'stock_controller') {
        header('Location: batch_history.php');
    } else {
        header('Location: dashboard_full.php');
    }
    exit();
}

// Try to include database connection, or create direct connection
$db_connected = false;
$conn = null;

// First try to include the db_connect file
if (file_exists('includes/db_connect.php')) {
    require_once 'includes/db_connect.php';
    if (isset($conn) && !$conn->connect_error) {
        $db_connected = true;
        error_log("Database connected via db_connect.php");
    }
}

// If db_connect didn't work, create direct connection
if (!$db_connected) {
    $host = 'localhost';
    $username = 'root';
    $password = '';
    $database = 'ability_db';

    $conn = new mysqli($host, $username, $password, $database);

    if ($conn->connect_error) {
        error_log("Direct database connection failed: " . $conn->connect_error);
        $conn = null;
    } else {
        $db_connected = true;
        error_log("Direct database connected successfully");
    }
}

// Include functions
require_once 'includes/functions.php';

// Define user role
$user_role = getUserRole();
$is_stock_controller = ($user_role === 'stock_controller');

// Stock Controller Details
$stock_controller_id = $_SESSION['user_id'] ?? 0;
$stock_controller_username = $_SESSION['username'] ?? 'Unknown';
$stock_controller_fullname = $_SESSION['full_name'] ?? $stock_controller_username;
$stock_controller_email = '';
$stock_controller_department = '';
$stock_controller_role = $user_role;

if ($is_stock_controller && $db_connected && $stock_controller_id > 0) {
    $query = "SELECT email, department FROM users WHERE id = ?";
    $stmt = $conn->prepare($query);
    if ($stmt) {
        $stmt->bind_param("i", $stock_controller_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $stock_controller_email = $row['email'] ?? '';
            $stock_controller_department = $row['department'] ?? '';
        }
        $stmt->close();
    }
}

$stock_controller_display = !empty($stock_controller_fullname) ? $stock_controller_fullname : $stock_controller_username;

// Load technicians from database
$technicians = [];
if ($db_connected) {
    $techQuery = "SELECT id, username, full_name, department FROM technicians WHERE is_active = 1 ORDER BY full_name";
    $techResult = $conn->query($techQuery);
    if ($techResult) {
        $technicians = $techResult->fetch_all(MYSQLI_ASSOC);
    }
}

$pageTitle = "Scan & Batch Items - aBility";
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- QR Scanner Library -->
    <script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>

    <!-- AOS Animation Library -->
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">

    <style>
        @import url('https://fonts.googleapis.com/css2?family=Marvel:ital,wght@0,400;0,700;1,400;1,700&display=swap');

        * {
            font-family: "Marvel", sans-serif;
        }

        /* ==================== PAGE HEADER STYLES ==================== */
        .page-header-compact {
            background: linear-gradient(135deg, #1a2e3f 0%, #234c6a 100%);
            padding: 0.75rem 2rem;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .user-info-compact {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .user-avatar-compact {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, #20B2AA 0%, #1A8F89 100%);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.1rem;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2);
        }

        .user-details-compact h5 {
            color: white;
            font-size: 1rem;
            font-weight: 600;
            margin: 0;
            line-height: 1.3;
        }

        .user-details-compact .role-badge-compact {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            background: rgba(255, 255, 255, 0.1);
            color: white;
            padding: 2px 10px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 500;
        }

        .back-to-dashboard {
            color: rgba(255, 255, 255, 0.7);
            text-decoration: none;
            font-size: 0.85rem;
            display: flex;
            align-items: center;
            gap: 6px;
            transition: all 0.3s ease;
            padding: 4px 8px;
            border-radius: 6px;
        }

        .back-to-dashboard:hover {
            color: white;
            background: rgba(255, 255, 255, 0.1);
            transform: translateX(-3px);
        }

        .header-actions-compact {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .logout-btn-compact {
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: white;
            padding: 5px 14px;
            border-radius: 20px;
            font-size: 0.8rem;
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .logout-btn-compact:hover {
            background: rgba(255, 255, 255, 0.2);
            color: white;
        }

        /* Main Layout */
        .split-container {
            display: flex;
            height: calc(100vh - 130px);
            gap: 20px;
            margin-top: 20px;
            padding: 0 2rem;
        }

        .left-panel,
        .right-panel {
            flex: 1;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 15px rgba(0, 0, 0, 0.1);
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        .panel-header {
            background: linear-gradient(135deg, #234c6a 0%, #2c5a7a 100%);
            color: white;
            padding: 15px 20px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .panel-header h5,
        .panel-header h6 {
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .panel-body {
            flex: 1;
            overflow-y: auto;
            padding: 20px;
        }

        .panel-footer {
            padding: 15px 20px;
            background: #f8f9fa;
            border-top: 1px solid #dee2e6;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        /* Scanner Styles */
        .scanner-container {
            background: #000;
            border-radius: 10px;
            overflow: hidden;
            margin-bottom: 20px;
            position: relative;
        }

        #qr-reader {
            width: 100%;
            border: none;
        }

        #qr-reader__scan_region {
            background: #000;
        }

        .scanner-controls {
            display: flex;
            gap: 10px;
            margin-top: 10px;
            justify-content: center;
        }

        .camera-select {
            width: 100%;
            padding: 8px;
            border-radius: 5px;
            border: 1px solid #ddd;
            margin-bottom: 10px;
        }

        /* Batch Stats */
        .batch-stats {
            display: flex;
            justify-content: space-around;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .stat-item {
            flex: 1;
            text-align: center;
            padding: 0 8px;
        }

        .stat-value {
            font-size: 2rem;
            font-weight: bold;
            color: #4361ee;
            line-height: 1;
        }

        .stat-label {
            font-size: 0.7rem;
            color: #6c757d;
            margin-top: 5px;
        }

        .items-list {
            max-height: 400px;
            overflow-y: auto;
        }

        .item-card {
            background: white;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 10px;
            transition: all 0.3s ease;
        }

        .item-card:hover {
            border-color: #4361ee;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.1);
            transform: translateY(-2px);
        }

        .item-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }

        .item-name {
            font-weight: 600;
            font-size: 1.1rem;
            color: #333;
            flex: 1;
            margin-right: 10px;
        }

        .item-actions {
            display: flex;
            gap: 5px;
        }

        .btn-action {
            width: 30px;
            height: 30px;
            padding: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
        }

        .item-details {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 10px;
            font-size: 0.9rem;
        }

        .detail-item {
            display: flex;
            flex-direction: column;
        }

        .detail-label {
            font-size: 0.8rem;
            color: #6c757d;
            margin-bottom: 2px;
        }

        .detail-value {
            font-weight: 500;
            color: #333;
        }

        .badge-status {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
        }

        .status-available {
            background-color: rgba(40, 167, 69, 0.15);
            color: #28a745;
        }

        .status-in_use {
            background-color: rgba(0, 123, 255, 0.15);
            color: #007bff;
        }

        .status-maintenance {
            background-color: rgba(255, 193, 7, 0.15);
            color: #ffc107;
        }

        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #6c757d;
        }

        .empty-icon {
            font-size: 48px;
            color: #dee2e6;
            margin-bottom: 15px;
        }

        /* Manual Entry */
        .manual-entry-container {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-top: 20px;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            font-weight: 500;
            margin-bottom: 5px;
            display: block;
            color: #495057;
        }

        .search-results {
            position: absolute;
            z-index: 1000;
            background: white;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            max-height: 300px;
            overflow-y: auto;
            width: 100%;
            margin-top: 5px;
            display: none;
        }

        .search-result-item {
            padding: 10px 15px;
            border-bottom: 1px solid #f1f3f4;
            cursor: pointer;
            transition: background 0.2s;
        }

        .search-result-item:hover {
            background: #f8f9fa;
        }

        .nav-tabs-custom {
            border-bottom: 2px solid #dee2e6;
            margin-bottom: 20px;
        }

        .nav-tabs-custom .nav-link {
            border: none;
            color: #6c757d;
            padding: 10px 20px;
            font-weight: 500;
            border-radius: 8px 8px 0 0;
            margin-bottom: -2px;
        }

        .nav-tabs-custom .nav-link.active {
            color: #4361ee;
            background-color: white;
            border-bottom: 3px solid #4361ee;
        }

        @media (max-width: 992px) {
            .split-container {
                flex-direction: column;
                height: auto;
            }

            .left-panel,
            .right-panel {
                height: 500px;
            }
        }

        .new-item {
            animation: slideIn 0.3s ease;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateX(20px);
            }

            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        .flash-highlight {
            animation: flashHighlight 0.5s ease;
        }

        @keyframes flashHighlight {
            0% {
                background-color: #fff3cd;
            }

            100% {
                background-color: transparent;
            }
        }

        @keyframes slideInRight {
            from {
                transform: translateX(100%);
                opacity: 0;
            }

            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        @keyframes slideOutRight {
            from {
                transform: translateX(0);
                opacity: 1;
            }

            to {
                transform: translateX(100%);
                opacity: 0;
            }
        }

        @keyframes pulseGently {
            0% { transform: scale(1); }
            50% { transform: scale(1.1); }
            100% { transform: scale(1); }
        }
        .animate-pulse-slow {
            display: inline-block;
            animation: pulseGently 2s ease-in-out infinite;
        }
    </style>
</head>

<body>
    <!-- Header -->
    <div class="page-header-compact">
        <div class="d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center gap-4">
                <div class="user-info-compact">
                    <div class="user-avatar-compact">
                        <i class="fas <?php echo $is_stock_controller ? 'fa-user-shield' : 'fa-tools'; ?>"></i>
                    </div>
                    <div class="user-details-compact">
                        <h5><?php echo htmlspecialchars(getUserFullName()); ?></h5>
                        <div>
                            <span class="role-badge-compact">
                                <i class="fas fa-<?php echo $is_stock_controller ? 'check-circle' : 'user'; ?> me-1"></i>
                                <?php echo ucfirst(str_replace('_', ' ', $user_role)); ?>
                            </span>
                            <?php if ($is_stock_controller): ?>
                                <span class="badge bg-primary ms-2">
                                    <i class="fas fa-check-circle"></i> Verified
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="header-actions-compact">
                <a href="dashboard.php" class="back-to-dashboard">
                    <i class="fas fa-arrow-left me-1"></i> Dashboard
                </a>
                <a href="#" class="logout-btn-compact" onclick="showLogoutToast(event)">
                    <i class="fas fa-sign-out-alt me-2"></i>Logout
                </a>
            </div>
        </div>
    </div>

    <!-- Logout Toast -->
    <div id="logoutToast" class="logout-toast" style="display: none; position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; border-radius: 12px; padding: 20px; box-shadow: 0 5px 30px rgba(0,0,0,0.3); z-index: 10000; min-width: 300px; text-align: center;">
        <div class="mb-3">
            <div class="rounded-circle bg-warning bg-opacity-10 d-inline-flex align-items-center justify-content-center p-3">
                <i class="fas fa-sign-out-alt fa-2x text-warning"></i>
            </div>
        </div>
        <h5 class="mb-2">Confirm Logout</h5>
        <p class="text-muted mb-3">Are you sure you want to logout?</p>
        <div class="d-flex gap-2 justify-content-center">
            <button class="btn btn-secondary" onclick="hideLogoutToast()">Cancel</button>
            <a href="logout.php" class="btn btn-primary">Yes, Logout</a>
        </div>
    </div>
    <div id="toastOverlay" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 9999;" onclick="hideLogoutToast()"></div>

    <!-- Batch Submission Modal -->
    <div class="modal fade" id="batchSubmitModal" tabindex="-1" aria-labelledby="batchSubmitModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header bg- text-white" style="background-color: rgb(12 78 100);">
                    <h5 class="modal-title" id="batchSubmitModalLabel">
                        <i class="fas fa-paper-plane me-2"></i>Submit Batch Items - Review & Approval
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body">
                    <?php
                    // Fetch technicians from database for selection if not already defined or if admin/stock controller
                    $technicians = [];
                    $drivers = [];
                    if ($db_connected) {
                        $techQuery = "SELECT id, username, full_name, department FROM users WHERE role = 'technician' AND is_active = 1 ORDER BY full_name";
                        $techResult = $conn->query($techQuery);
                        if ($techResult) {
                            $technicians = $techResult->fetch_all(MYSQLI_ASSOC);
                        }

                        $driverQuery = "SELECT id, full_name, phone_number, vehicle_type, vehicle_number FROM drivers WHERE is_active = 1 ORDER BY full_name";
                        $driverResult = $conn->query($driverQuery);
                        if ($driverResult) {
                            $drivers = $driverResult->fetch_all(MYSQLI_ASSOC);
                        }
                    }
                    ?>
                    <!-- Authentication Section -->
                    <div id="authenticationSection" class="card mb-4 border-" style="border-color: #317e97;">
                        <div class="card-header bg- text-white" style="background-color: #317e97;">
                            <h6 class="mb-0"><i class="fas fa-user-lock me-2"></i>Technician Authentication Required</h6>
                        </div>
                        <div class="card-body">
                            <form id="technicianAuthForm" onsubmit="authenticateTechnician(event); return false;">
                                <h6 class="mb-3">
                                    <i class="fas fa-user-shield me-1"></i>Technician Authentication
                                    <small class="text-muted ms-2">(Verified by Stock Controller)</small>
                                </h6>

                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Select Technician</label>
                                        <select class="form-select" id="technicianSelect" required>
                                            <option value="">-- Select Technician --</option>
                                            <?php foreach ($technicians as $tech): ?>
                                                <option value="<?php echo $tech['id']; ?>"
                                                    data-fullname="<?php echo htmlspecialchars($tech['full_name']); ?>"
                                                    data-username="<?php echo htmlspecialchars($tech['username']); ?>"
                                                    data-department="<?php echo htmlspecialchars($tech['department']); ?>">
                                                    <?php echo htmlspecialchars($tech['full_name'] . ' (' . $tech['username'] . ') - ' . ($tech['department'] ?? 'No Department')); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <div class="col-md-6">
                                        <label class="form-label">Technician Password</label>
                                        <div class="input-group has-validation">
                                            <input type="password" id="technicianPassword" class="form-control"
                                                placeholder="Enter technician's password" required>
                                            <button class="btn btn-outline-secondary" type="button" onclick="togglePasswordVisibility()">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        </div>
                                        <div id="authStatus" class="mt-2"></div>
                                    </div>
                                </div>

                                <div class="text-center mt-3">
                                    <button type="submit" id="authenticateBtn" class="btn btn-sm btn- text-white" style="background-color: #317d97;">
                                        <i class="fas fa-user-check"></i> <small> Verify & Continue</small>
                                    </button>
                                    <button type="button" class="btn btn-sm btn- text-white ms-2" style="background-color: #b00707;" data-bs-dismiss="modal">
                                        <i class="fas fa-times me-1"></i><small>Cancel</small>
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Main Batch Form -->
                    <div id="batchFormSection" class="d-none">
                        <div id="authSuccessSection">
                            <!-- Top Info Section (Personnel Summary) -->
                            <div class="batch-info-card mb-4" id="personnelSummaryCard">
                                <div class="batch-info-title bg-dark text-white p-2 rounded-top">
                                    <i class="fas fa-users me-2"></i>
                                    <span>Personnel Details</span>
                                </div>
                                <div class="card-body border rounded-bottom bg-light">
                                    <div class="row text-center">
                                        <div class="col-md-4">
                                            <div class="info-label fw-bold text-muted small uppercase">Technician</div>
                                            <div class="info-value fw-bold" id="summaryTechnicianName">Not authenticated</div>
                                        </div>
                                        <div class="col-md-4 border-start">
                                            <div class="info-label fw-bold text-muted small uppercase">Requested By</div>
                                            <div class="info-value fw-bold" id="summaryRequestedBy">Not authenticated</div>
                                        </div>
                                        <div class="col-md-4 border-start">
                                            <div class="info-label fw-bold text-muted small uppercase">Stock Controller</div>
                                            <div class="info-value fw-bold" id="summaryStockControllerName"><?php echo htmlspecialchars($stock_controller_display); ?></div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Stock Movement Type Selection -->
                            <div class="card mb-4 ">
                                <div class="card-header bg- text-white" style="background-color: #244d71;">
                                    <h6 class="mb-0"><i class="fas fa-exchange-alt me-2"></i>Stock Movement Type</h6>
                                </div>
                                <div class="card-body">
                                    <div class="row mb-4">
                                        <div class="col-md-12">
                                            <div class="btn-group w-100" role="group">
                                                <input type="radio" class="btn-check" name="stockMovementType" id="movementStockToVenueRoom" value="stock_to_venue_room" autocomplete="off" checked>
                                                <label class="btn btn-outline-primary" for="movementStockToVenueRoom">
                                                    <i class="fas fa-arrow-right me-2"></i>Stock → Venue (Room)
                                                    <small class="d-block text-muted">Equipment to specific room - No transport</small>
                                                </label>

                                                <input type="radio" class="btn-check" name="stockMovementType" id="movementVenueRoomToStock" value="venue_room_to_stock" autocomplete="off">
                                                <label class="btn btn-outline-success" for="movementVenueRoomToStock">
                                                    <i class="fas fa-arrow-left me-2"></i>Venue (Room) → Stock
                                                    <small class="d-block text-muted">Equipment returning from room - No transport</small>
                                                </label>

                                                <input type="radio" class="btn-check" name="stockMovementType" id="movementStockToStock" value="stock_to_stock" autocomplete="off">
                                                <label class="btn btn-outline-warning" for="movementStockToStock">
                                                    <i class="fas fa-warehouse me-2"></i>Stock → Stock
                                                    <small class="d-block text-muted">Transfer between stocks - Transport needed</small>
                                                </label>

                                                <input type="radio" class="btn-check" name="stockMovementType" id="movementStockToVenueTransport" value="stock_to_venue_transport" autocomplete="off">
                                                <label class="btn btn-outline-info" for="movementStockToVenueTransport">
                                                    <i class="fas fa-truck me-2"></i>Stock → Venue (Transport)
                                                    <small class="d-block text-muted">Equipment to venue - Transport needed</small>
                                                </label>
                                            </div>
                                        </div>
                                    </div>

                                    <div id="sourceSection" class="row mb-3"></div>
                                    <div id="destinationSection" class="row mb-3"></div>

                                    <div id="transportSection" class="row mt-3" style="display: none;">
                                        <div class="col-md-12">
                                            <div class="card bg-light">
                                                <div class="card-header bg-secondary text-white py-2">
                                                    <h6 class="mb-0"><i class="fas fa-truck me-2"></i>Transport Details (Required)</h6>
                                                </div>
                                                <div class="card-body">
                                                    <div class="row">
                                                        <div class="col-md-3">
                                                            <label class="form-label">Vehicle Type *</label>
                                                            <select class="form-control" id="transportVehicleType">
                                                                <option value="">-- Select Vehicle Type --</option>
                                                                <option value="Van">Van</option>
                                                                <option value="Truck">Truck</option>
                                                                <option value="HiAce">HiAce</option>
                                                            </select>
                                                        </div>
                                                        <div class="col-md-3">
                                                            <label class="form-label">Vehicle Number *</label>
                                                            <input type="text" class="form-control" id="transportVehicleNumber" placeholder="e.g., RAH 847">
                                                        </div>
                                                        <div class="col-md-3">
                                                            <label class="form-label">Driver Name *</label>
                                                            <select class="form-control" id="transportDriver">
                                                                <option value="">-- Select Driver --</option>
                                                                <?php foreach ($drivers as $driver): ?>
                                                                    <option value="<?php echo htmlspecialchars($driver['id']); ?>" data-vehicle-type="<?php echo htmlspecialchars($driver['vehicle_type']); ?>" data-vehicle-number="<?php echo htmlspecialchars($driver['vehicle_number']); ?>">
                                                                        <?php echo htmlspecialchars($driver['full_name']); ?>
                                                                    </option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                        </div>
                                                        <div class="col-md-3">
                                                            <label class="form-label">Transport Date *</label>
                                                            <input type="date" class="form-control" id="transportDate">
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row mt-3">
                                        <div class="col-12">
                                            <div class="alert alert-info py-2 mb-0" id="movementSummary">
                                                <i class="fas fa-info-circle me-2"></i>
                                                <span id="movementSummaryText">Select movement type and locations</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Two Column Layout -->
                            <div class="row">
                                <div class="col-md-6">
                                    <!-- Job Details Card -->
                                    <div class="card mb-4">
                                        <div class="card-header bg-light">
                                            <h6 class="mb-0"><i class="fas fa-clipboard-list me-2"></i>Job & Event Details</h6>
                                        </div>
                                        <div class="card-body">
                                            <div class="row g-3">
                                                <div class="col-md-6">
                                                    <label class="form-label">Event Name</label>
                                                    <input type="text" class="form-control" id="eventName">
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label">Job Sheet Reference</label>
                                                    <input type="text" class="form-control" id="jobSheet" placeholder="e.g. JS-2601">
                                                </div>
                                                <div class="col-md-12">
                                                    <label class="form-label">Upload Job Sheet File</label>
                                                    <input type="file" class="form-control" id="jobSheetFile" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label">Project Manager</label>
                                                    <input type="text" class="form-control" id="projectManager">
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label">Movement Notes</label>
                                                    <input type="text" class="form-control" id="movementNotes">
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Stock Controller & Stock Location Selection Card -->
                                    <div class="card mb-4">
                                        <div class="card-header bg-light">
                                            <h6 class="mb-0"><i class="fas fa-warehouse me-2"></i>Stock & Controller Details</h6>
                                        </div>
                                        <div class="card-body">
                                            <div class="row g-3">
                                                <div class="col-md-6">
                                                    <label class="form-label">Stock Controller *</label>
                                                    <select class="form-select" id="stockControllerSelect" required>
                                                        <?php
                                                        // Fetch stock controllers from database
                                                        $stockControllers = [];
                                                        if ($db_connected) {
                                                            $scQuery = "SELECT id, username, full_name, department FROM users WHERE role = 'stock_controller' AND is_active = 1 ORDER BY full_name";
                                                            $scResult = $conn->query($scQuery);
                                                            if ($scResult) {
                                                                $stockControllers = $scResult->fetch_all(MYSQLI_ASSOC);
                                                            }
                                                        }
                                                        // Add current user if they are stock controller
                                                        if ($is_stock_controller) {
                                                            $currentUser = [
                                                                'id' => $stock_controller_id,
                                                                'full_name' => $stock_controller_display,
                                                                'username' => $stock_controller_username,
                                                                'department' => $stock_controller_department
                                                            ];
                                                            // Avoid duplicate
                                                            $exists = false;
                                                            foreach ($stockControllers as $sc) {
                                                                if ($sc['id'] == $currentUser['id']) {
                                                                    $exists = true;
                                                                    break;
                                                                }
                                                            }
                                                            if (!$exists) {
                                                                $stockControllers[] = $currentUser;
                                                            }
                                                        }
                                                        foreach ($stockControllers as $sc):
                                                            $displayDept = trim($sc['department'] ?? '');
                                                            if ($sc['username'] === 'irenem' && empty($displayDept)) {
                                                                $displayDept = 'BK Arena Stock';
                                                            } else if ($sc['username'] === 'princen' && $displayDept === 'warehouse') {
                                                                $displayDept = 'KCC Stock';
                                                            } else if (empty($displayDept)) {
                                                                $displayDept = 'Stock Controller';
                                                            }
                                                        ?>
                                                            <option value="<?php echo $sc['id']; ?>"
                                                                data-fullname="<?php echo htmlspecialchars($sc['full_name'] ?? $sc['username']); ?>"
                                                                data-username="<?php echo htmlspecialchars($sc['username']); ?>"
                                                                data-department="<?php echo htmlspecialchars($displayDept); ?>"
                                                                <?php echo ($sc['id'] == $stock_controller_id) ? 'selected' : ''; ?>>
                                                                <?php echo htmlspecialchars(($sc['full_name'] ?? $sc['username']) . ' (' . $displayDept . ')'); ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                    <script>
                                                        function syncPersonnelSummary() {
                                                            console.log('Syncing personnel summary...');
                                                            // Sync Technician (if authenticated)
                                                            if (typeof isTechnicianAuthenticated !== 'undefined' && isTechnicianAuthenticated && typeof authenticatedTechnician !== 'undefined') {
                                                                const summaryTech = document.getElementById('summaryTechnicianName');
                                                                const summaryReq = document.getElementById('summaryRequestedBy');
                                                                if (summaryTech) summaryTech.textContent = authenticatedTechnician.full_name;
                                                                if (summaryReq) summaryReq.textContent = authenticatedTechnician.full_name;
                                                                console.log('Syncing Tech:', authenticatedTechnician.full_name);
                                                            }

                                                            // Sync Stock Controller from dropdown
                                                            const scSelect = document.getElementById('stockControllerSelect');
                                                            if (scSelect && scSelect.options.length > 0) {
                                                                const selectedOption = scSelect.options[scSelect.selectedIndex];
                                                                const fullName = selectedOption.getAttribute('data-fullname') || selectedOption.text;
                                                                const summarySC = document.getElementById('summaryStockControllerName');
                                                                if (summarySC) {
                                                                    summarySC.textContent = fullName;
                                                                    console.log('Syncing SC:', fullName);
                                                                }
                                                            }
                                                        }

                                                        document.getElementById('stockControllerSelect').addEventListener('change', syncPersonnelSummary);
                                                        
                                                        // Run once immediately after definition
                                                        syncPersonnelSummary();
                                                    </script>
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label">Stock Location *</label>
                                                    <select class="form-select" id="stockLocationSelect" required>
                                                        <option value="">-- Select Stock Location --</option>
                                                        <option value="Main Warehouse">Ndera Warehouse</option>
                                                        <option value="KCC Stock">KCC Stock</option>
                                                        <option value="BK Arena Stock">BK Arena Stock</option>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="row mt-3">
                                                <div class="col-12">
                                                    <div class="alert alert-info py-2 mb-0">
                                                        <i class="fas fa-info-circle me-2"></i>
                                                        <small>Select the stock controller who will approve this request and the stock location where equipment will be taken from.</small>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Approval Details Card -->
                                    <div class="card mb-4">
                                        <div class="card-header bg-light">
                                            <h6 class="mb-0"><i class="fas fa-user-check me-2"></i>Approval Details</h6>
                                        </div>
                                        <div class="card-body">
                                            <div class="mb-3">
                                                <label class="form-label fw-bold">Requested By (Technician) *</label>
                                                <input type="text" class="form-control bg-light" id="requestedBy" value="<?php echo htmlspecialchars($stock_controller_display); ?>" readonly>
                                            </div>
                                            <input type="hidden" id="submittedById" value="<?php echo $stock_controller_id; ?>">
                                            <input type="hidden" id="submittedByUsername" value="<?php echo htmlspecialchars($stock_controller_username); ?>">
                                            <input type="hidden" id="submittedByFullName" value="<?php echo htmlspecialchars($stock_controller_display); ?>">
                                            <input type="hidden" id="submittedByRole" value="<?php echo htmlspecialchars($user_role); ?>">
                                        </div>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <!-- Items Preview Card -->
                                    <div class="card mb-4">
                                        <div class="card-header bg-light d-flex justify-content-between align-items-center">
                                            <h6 class="mb-0"><i class="fas fa-boxes me-2"></i>Items Summary
                                                <span class="badge bg-warning ms-2" id="batchItemCount">0</span>
                                            </h6>
                                        </div>
                                        <div class="card-body">
                                            <div class="row text-center mb-3">
                                                <div class="col-4">
                                                    <div class="bg-success bg-opacity-10 p-2 rounded">
                                                        <div class="h4 mb-1" id="summaryAvailable">0</div>
                                                        <small>Available</small>
                                                    </div>
                                                </div>
                                                <div class="col-4">
                                                    <div class="bg-warning bg-opacity-10 p-2 rounded">
                                                        <div class="h4 mb-1" id="summaryInUse">0</div>
                                                        <small>In Use</small>
                                                    </div>
                                                </div>
                                                <div class="col-4">
                                                    <div class="bg-danger bg-opacity-10 p-2 rounded">
                                                        <div class="h4 mb-1" id="summaryMaintenance">0</div>
                                                        <small>Maintenance</small>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="table-responsive">
                                                <table class="table table-sm table-hover">
                                                    <thead class="table-light">
                                                        <tr>
                                                            <th>Item</th>
                                                            <th>Serial</th>
                                                            <th>Qty</th>
                                                            <th>Location</th>
                                                            <th>Status</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody id="itemsPreviewTable"></tbody>
                                                    <tfoot>
                                                        <tr>
                                                            <td colspan="3" class="text-end"><strong>Total Items:</strong></td>
                                                            <td colspan="2"><strong id="totalItemsCount">0</strong></td>
                                                        </tr>
                                                    </tfoot>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Confirmation Section -->
                            <div class="card mt-4 border-primary">
                                <div class="card-body">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="confirmBatchSubmit" required>
                                        <label class="form-check-label" for="confirmBatchSubmit">
                                            <strong>I confirm that:</strong>
                                            <ul class="mb-0 mt-2">
                                                <li>Technician <strong id="confirmationTechnicianName"></strong> has been authenticated</li>
                                                <li>All <strong id="confirmItemCount">0</strong> items listed are accurate</li>
                                                <li>Movement: <span id="movementTypeConfirmation" class="fw-bold"></span></li>
                                                <li class="border-top pt-2 mt-2">
                                                    <strong>Submitted by:</strong> <?php echo htmlspecialchars($stock_controller_display); ?>
                                                    <span class="badge bg-<?php echo $is_stock_controller ? 'primary' : 'secondary'; ?> ms-2"><?php echo htmlspecialchars($user_role); ?></span>
                                                </li>
                                            </ul>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn- text-white" style="background-color: #b00707;" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-sm btn- text-white" style="background-color: #388ba0;" id="printPreviewBtn" disabled>Print Preview</button>
                    <button type="button" class="btn btn-sm btn- text-white" style="background-color: #07b056;" id="submitBatchBtn" disabled>Submit Batch</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Submission Success Modal -->
    <div class="modal fade" id="submissionSuccessModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg" style="border-radius: 15px; overflow: hidden;">
                <div class="modal-body p-0">
                    <div class="text-center p-5 bg-success text-white">
                        <div class="mb-4">
                            <div class="bg-white rounded-circle d-inline-flex align-items-center justify-content-center" style="width: 80px; height: 80px;">
                                <i class="fas fa-check text-success fa-3x"></i>
                            </div>
                        </div>
                        <h4 class="fw-bold mb-2">Submission Successful!</h4>
                        <p class="mb-0 opacity-75">Item(s) have been submitted - contact stock controller (<span id="successStockControllerName" class="fw-bold text-white"></span>) for approval</p>
                        <div class="mt-3 small opacity-75">Batch ID: <span id="successBatchIdDisplay" class="fw-bold text-white"></span></div>
                    </div>
                    <div class="p-4 bg-light">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6 class="text-uppercase fw-bold text-muted small mb-0 ls-1">Submitted Items</h6>
                            <span class="badge bg-success-soft text-success rounded-pill px-3 py-2" id="successItemCountBadge">0 Items</span>
                        </div>
                        <div id="successItemsList" class="list-group list-group-flush rounded-3 border overflow-auto" style="max-height: 200px;">
                            <!-- Items will be injected here -->
                        </div>
                    </div>
                    <div class="p-4 pt-0 bg-light text-center">
                        <!-- SLA Countdown Timer Card -->
                        <div class="card border-0 shadow-sm mb-4" style="border-radius: 12px; background: #ffffff;">
                            <div class="card-body p-3">
                                <div class="text-uppercase fw-bold text-muted small mb-2 tracking-wider" style="font-size: 0.75rem; letter-spacing: 0.05em;">Estimated Approval Time</div>
                                <div class="d-flex align-items-center justify-content-center mb-2">
                                    <div class="bg-warning bg-opacity-10 rounded-circle p-2 d-inline-flex align-items-center justify-content-center me-3 animate-pulse-slow" style="width: 44px; height: 44px; color: #ffb300; background-color: rgba(255, 193, 7, 0.1);">
                                        <i class="fas fa-hourglass-half"></i>
                                    </div>
                                    <div class="h3 mb-0 font-monospace fw-bold text-dark" id="approvalCountdown">15:00</div>
                                </div>
                                <div class="small text-muted" id="countdownInstructionText" style="font-size: 0.8rem; line-height: 1.4;">
                                    Approvals are typically completed within 15 minutes. Contact <span id="timerStockControllerName" class="fw-bold text-primary"></span>.
                                </div>
                            </div>
                        </div>
                        <hr class="mt-0 mb-4 opacity-10">
                        <div class="d-grid gap-2">
                            <button type="button" class="btn btn-success py-3 fw-bold shadow-sm" onclick="window.location.href = 'technician_batch_history.php'">
                                <i class="fas fa-history me-2"></i> View Batch History
                            </button>
                            <button type="button" class="btn btn-danger py-2 border-0" data-bs-dismiss="modal">
                                Close
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="container-fluid px-2 pt-4">
        <div class="d-flex justify-content-between align-items-center mb-4 px-3">
            <h5 class="mb-0"><i class="fas fa-qrcode me-2"></i>Scan & Batch Items</h5>
            <div class="btn-group">
                <button class="btn btn-sm btn-success" id="submitBatchModalBtn" disabled>
                    Submit Batch (<span id="batchCount">0</span>)
                </button>
            </div>
        </div>

        <!-- Split Screen Layout -->
        <div class="split-container">
            <div class="col-sm-4">
                <!-- Left Panel - Scanner & Add Items -->
                <div class="left-panel">
                    <div class="panel-header">
                        <h6><i class="fas fa-qrcode"></i> Scan / Add Items</h6>
                    </div>
                    <div class="panel-body">
                        <!-- Tab Navigation -->
                        <ul class="nav nav-tabs nav-tabs-custom" id="addMethodTab" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="scan-tab" data-bs-toggle="tab" data-bs-target="#scan" type="button" role="tab">
                                    <i class="fas fa-camera me-1"></i> Scan QR Code
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="manual-tab" data-bs-toggle="tab" data-bs-target="#manual" type="button" role="tab">
                                    <i class="fas fa-keyboard me-1"></i> Manual Entry
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="search-tab" data-bs-toggle="tab" data-bs-target="#search" type="button" role="tab">
                                    <i class="fas fa-search me-1"></i> Search Database
                                </button>
                            </li>
                        </ul>

                        <div class="tab-content mt-3">
                            <!-- Scan QR Code Tab -->
                            <div class="tab-pane fade show active" id="scan" role="tabpanel">
                                <div class="scanner-container">
                                    <div id="qr-reader" style="width: 100%;"></div>
                                    <div class="scanner-controls">
                                        <select id="camera-select" class="camera-select">
                                            <option value="">Select Camera</option>
                                        </select>
                                        <button class="btn btn-sm btn-danger" onclick="stopScanner()">
                                            <i class="fas fa-stop me-1"></i> Stop
                                        </button>
                                        <button class="btn btn-sm btn-primary" onclick="startScanner()">
                                            <i class="fas fa-play me-1"></i> Start
                                        </button>
                                    </div>
                                </div>
                                <div class="alert alert-info mt-2">
                                    <i class="fas fa-info-circle me-2"></i>
                                    Position the QR code in front of the camera. Scanned items will be added automatically.
                                </div>
                            </div>

                            <!-- Manual Entry Tab -->
                            <div class="tab-pane fade" id="manual" role="tabpanel">
                                <div class="manual-entry-container">
                                    <h6><i class="fas fa-plus-circle me-1"></i> Add Item Manually</h6>
                                    <div class="row">
                                        <div class="col-md-12">
                                            <div class="form-group">
                                                <label>Item Name <span class="text-danger">*</span></label>
                                                <input type="text" class="form-control" id="itemName" placeholder="Enter item name">
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label>Serial Number</label>
                                                <input type="text" class="form-control" id="serialNumber" placeholder="Serial number">
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label>Quantity</label>
                                                <input type="number" class="form-control" id="quantity" value="1" min="1">
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label>Status</label>
                                                <select class="form-select" id="status">
                                                    <option value="available">Available</option>
                                                    <option value="in_use">In Use</option>
                                                    <option value="maintenance">Maintenance</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label>Location</label>
                                                <input type="text" class="form-control" id="location" placeholder="e.g., Warehouse A">
                                            </div>
                                        </div>
                                    </div>
                                    <div class="d-flex gap-2 mt-4">
                                        <button class="btn btn-success flex-fill" onclick="addManualItem()">
                                            <i class="fas fa-plus me-1"></i> Add to Batch
                                        </button>
                                        <button class="btn btn-outline-secondary" onclick="clearManualForm()">
                                            <i class="fas fa-times me-1"></i> Clear
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- Search Database Tab -->
                            <div class="tab-pane fade" id="search" role="tabpanel">
                                <div class="manual-entry-container">
                                    <h6><i class="fas fa-search me-1"></i> Search Equipment from Database</h6>
                                    <div class="form-group">
                                        <label>Search by Item Name or Serial Number</label>
                                        <div class="input-group">
                                            <input type="text" class="form-control" id="itemSearchInput"
                                                placeholder="Type item name or serial number..."
                                                onkeyup="searchItemsDebounced()">
                                            <button class="btn btn-primary" onclick="searchItems()">
                                                <i class="fas fa-search"></i> Search
                                            </button>
                                        </div>
                                    </div>
                                    <div id="searchResults" class="mt-3" style="display: none;">
                                        <label>Search Results:</label>
                                        <div class="list-group" id="searchResultsList"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="panel-footer">
                        <small class="text-muted">Scan QR codes, search database, or manually add items</small>
                    </div>
                </div>
            </div>
            <div class="col-sm-8">
                <!-- Right Panel: Batch Items -->
                <div class="right-panel">
                    <div class="panel-header d-flex justify-content-between align-items-center">
                        <h6 class="mb-0">Batch Items <span class="badge bg-primary rounded-pill ms-2" id="batchCountRight">0</span></h6>
                        <button class="btn btn-sm btn-outline-warning" onclick="clearBatch()" id="clearBatchBtn" disabled>
                            <i class="fas fa-trash me-1"></i> Clear All
                        </button>
                    </div>
                    <div class="panel-body">
                        <div class="batch-stats">
                            <!-- <div class="stat-item">
                            <div class="stat-value" id="totalItems">0</div>
                            <div class="stat-label">Booked</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-value text-success" id="availableItems">0</div>
                            <div class="stat-label">In Stock</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-value text-warning" id="inUseItems">0</div>
                            <div class="stat-label">In Use</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-value text-danger" id="maintenanceItems">0</div>
                            <div class="stat-label">Faulty</div>
                        </div>
                    </div> -->
<<<<<<< HEAD

                            <!-- Live Group Breakdown / Tally -->
                            <div class="group-breakdown-card p-3 mb-3" style="background: rgba(67, 97, 238, 0.04); border: 1px solid rgba(67, 97, 238, 0.12); border-radius: 12px; display: none;" id="groupBreakdownSection">
                                <h6 class="fw-bold mb-2 text-primary" style="font-size: 0.85rem; letter-spacing: -0.1px;"><i class="fas fa-layer-group me-2"></i>Live Group Breakdown (Tally)</h6>
                                <div class="table-responsive">
                                    <table class="table table-sm table-borderless mb-0" style="font-size: 0.82rem;">
                                        <thead>
                                            <tr class="text-secondary" style="border-bottom: 1px solid rgba(0,0,0,0.06); font-weight: 600; font-size: 0.78rem;">
                                                <th>Item Group Name</th>
                                                <th class="text-center">Booked</th>
                                                <th class="text-center">Remaining</th>
                                                <th class="text-center">Total Stock</th>
                                            </tr>
                                        </thead>
                                        <tbody id="groupBreakdownBody">
                                            <!-- Dynamic rows -->
                                        </tbody>
                                    </table>
=======

                            <!-- Live Group Breakdown / Tally -->
                            <div class="group-breakdown-card p-3 mb-3" style="background: rgba(67, 97, 238, 0.04); border: 1px solid rgba(67, 97, 238, 0.12); border-radius: 12px; display: none;" id="groupBreakdownSection">
                                <h6 class="fw-bold mb-2 text-primary" style="font-size: 0.85rem; letter-spacing: -0.1px;"><i class="fas fa-layer-group me-2"></i>Live Group Breakdown (Tally)</h6>
                                <div class="table-responsive">
                                    <table class="table table-sm table-borderless mb-0" style="font-size: 0.82rem;">
                                        <thead>
                                            <tr class="text-secondary" style="border-bottom: 1px solid rgba(0,0,0,0.06); font-weight: 600; font-size: 0.78rem;">
                                                <th>Item Group Name</th>
                                                <th class="text-center">Booked</th>
                                                <th class="text-center">Remaining</th>
                                                <th class="text-center">Total Stock</th>
                                            </tr>
                                        </thead>
                                        <tbody id="groupBreakdownBody">
                                            <!-- Dynamic rows -->
                                        </tbody>
                                    </table>
                                </div>
                                <!-- Breakdown Pagination Controls -->
                                <div id="groupBreakdownPagination" class="d-flex justify-content-between align-items-center mt-2 pt-2 border-top" style="display: none; font-size: 0.76rem;">
                                    <span class="text-muted" id="groupBreakdownPaginationInfo">Showing 1-5 of 6</span>
                                    <div class="btn-group btn-group-xs">
                                        <button class="btn btn-outline-primary btn-xs py-0 px-2" style="font-size: 0.7rem; line-height: 1.5;" id="groupBreakdownPrevBtn" onclick="changeGroupBreakdownPage(-1)"><i class="fas fa-chevron-left"></i></button>
                                        <button class="btn btn-outline-primary btn-xs py-0 px-2" style="font-size: 0.7rem; line-height: 1.5;" id="groupBreakdownNextBtn" onclick="changeGroupBreakdownPage(1)"><i class="fas fa-chevron-right"></i></button>
                                    </div>
                                </div>
                            </div>

                            <div class="items-list" id="batchItemsList">
                                <div class="empty-state">
                                    <div class="empty-icon"><i class="fas fa-box-open"></i></div>
                                    <h6>No items in batch</h6>
                                    <p class="text-muted">Scan a QR code, search, or manually add items</p>
                                </div>
                            </div>
                        </div>
                        <div class="panel-footer">
                            <div class="text-end">
                                <button class="btn btn-sm btn-primary" id="openBatchModalBtn" disabled>
                                    <i class="fas fa-external-link-alt me-1"></i> Review & Submit
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>

        <!-- jQuery FIRST -->
        <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

        <script>
            // Prevent conflicts and ensure jQuery is fully loaded
            if (typeof jQuery !== 'undefined') {
                console.log('jQuery version:', jQuery.fn.jquery);
            }
        </script>

        <!-- Bootstrap JS -->
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

        <!-- QR Scanner Library -->
        <script src="https://cdn.jsdelivr.net/npm/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>

        <script>
            // ==================== GLOBAL VARIABLES ====================
            let batchItems = [];
            let isTechnicianAuthenticated = false;
            let authenticatedTechnician = null;
            let isUserTechnician = <?php echo $is_technician ? 'true' : 'false'; ?>;
            let loggedInUserId = '<?php echo $logged_in_user_id; ?>';
            let html5QrCode = null;
            let isScanning = false;
            let isScannerBusy = false;
            let lastScannedData = null;
            let lastScanTime = 0;
            const SCAN_DEBOUNCE_MS = 1000;
            let currentModal = null;
            let lastSearchResults = [];
            let groupBreakdownPage = 1;

            // ==================== SEARCH FUNCTIONS ====================
            let searchDebounceTimer;

            function searchItemsDebounced() {
                clearTimeout(searchDebounceTimer);
                searchDebounceTimer = setTimeout(() => {
                    searchItems();
                }, 300);
            }

            function searchItems() {
                const searchTerm = document.getElementById('itemSearchInput').value.trim();
                if (searchTerm.length < 2) {
                    document.getElementById('searchResults').style.display = 'none';
                    return;
                }

                $.ajax({
                    url: 'api/search_items.php',
                    method: 'GET',
                    data: {
                        q: searchTerm
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success && response.items && response.items.length > 0) {
                            displaySearchResults(response.items);
                        } else {
                            document.getElementById('searchResults').style.display = 'none';
                            showNotification('info', 'No items found');
                        }
                    },
                    error: function() {
                        showNotification('error', 'Error searching items');
                    }
                });
            }

            function displaySearchResults(items) {
                lastSearchResults = items;
                const resultsDiv = document.getElementById('searchResults');
                const resultsList = document.getElementById('searchResultsList');

                resultsList.innerHTML = '';
                items.forEach(item => {
                    const isBooked = batchItems.some(i => i.id == item.id);
                    const statusClass = item.status === 'available' ? 'success' : (item.status === 'in_use' ? 'warning' : 'danger');
                    const itemElement = document.createElement('div');

                    if (isBooked) {
                        itemElement.className = 'list-group-item list-group-item-action bg-light text-muted';
                        itemElement.style.opacity = '0.65';
                        itemElement.innerHTML = `
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <strong>${escapeHtml(item.item_name)}</strong> <span class="badge bg-secondary ms-1" style="font-size: 0.75rem; letter-spacing: 0.5px;">Booked</span><br>
                                <small class="text-muted">Serial: ${escapeHtml(item.serial_number)}</small><br>
                                <small>Status: <span class="badge bg-${statusClass}">${item.status}</span> | Location: ${escapeHtml(item.stock_location || 'N/A')}</small>
                            </div>
                            <button class="btn btn-sm btn-secondary fw-bold px-3 py-1" style="border-radius: 6px;" disabled>
                                <i class="fas fa-check me-1"></i> Booked
                            </button>
                        </div>
                    `;
                    } else {
                        itemElement.className = 'list-group-item list-group-item-action';
                        itemElement.innerHTML = `
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <strong>${escapeHtml(item.item_name)}</strong><br>
                                <small class="text-muted">Serial: ${escapeHtml(item.serial_number)}</small><br>
                                <small>Status: <span class="badge bg-${statusClass}">${item.status}</span> | Location: ${escapeHtml(item.stock_location || 'N/A')}</small>
                            </div>
                            <button class="btn btn-sm btn-success fw-bold px-3 py-1" style="border-radius: 6px; background-color: #2e7d32;" onclick='addSearchedItemToBatch(${JSON.stringify(item)})'>
                                <i class="fas fa-plus me-1"></i> Add
                            </button>
                        </div>
                    `;
                    }
                    resultsList.appendChild(itemElement);
                });
                resultsDiv.style.display = 'block';
            }

            function addSearchedItemToBatch(item) {
                const newItem = {
                    id: item.id,
                    name: item.item_name,
                    serial_number: item.serial_number,
                    quantity: 1,
                    status: item.status,
                    stock_location: item.stock_location || 'N/A',
                    image: item.image || null,
                    totalStock: item.total_group_count
                };
                const added = addToBatch(newItem);
                if (added) {
                    document.getElementById('searchResults').style.display = 'none';
                    document.getElementById('itemSearchInput').value = '';
                }
            }

            // ==================== QR SCANNER FUNCTIONS ====================
            async function startScanner() {
                if (isScannerBusy) {
                    console.log('Scanner is busy, ignoring start request');
                    return;
                }
                const hasPermission = await checkCameraPermissions();
                if (!hasPermission) return;

                try {
                    isScannerBusy = true;
                    const cameras = await Html5Qrcode.getCameras();
                    if (cameras && cameras.length) {
                        const cameraSelect = document.getElementById('camera-select');
                        cameraSelect.innerHTML = '<option value="">Select Camera</option>';
                        cameras.forEach(camera => {
                            const option = document.createElement('option');
                            option.value = camera.id;
                            option.text = camera.label || `Camera ${cameraSelect.children.length}`;
                            cameraSelect.appendChild(option);
                        });

                        let backCamera = cameras.find(c => c.label.toLowerCase().includes('back') || c.label.toLowerCase().includes('rear'));
                        let selectedCamera = backCamera || cameras[0];
                        cameraSelect.value = selectedCamera.id;

                        isScannerBusy = false; // Release lock before calling startWithCamera
                        await startScannerWithCamera(selectedCamera.id);
                    } else {
                        showNotification('error', 'No cameras found');
                        isScannerBusy = false;
                    }
                } catch (err) {
                    console.error('Camera enumeration error:', err);
                    showNotification('error', 'Could not access camera list');
                    isScannerBusy = false;
                }
            }

            async function startScannerWithCamera(cameraId) {
                if (isScannerBusy) return;
                isScannerBusy = true;

                try {
                    console.log('--- SCANNER STARTUP SEQUENCE START ---');
                    console.log('Target Camera ID:', cameraId);

                    const container = document.getElementById("qr-reader");
                    if (!container) {
                        console.error('CRITICAL: #qr-reader container not found in DOM');
                        showNotification('error', 'Scanner container missing');
                        return;
                    }

                    if (html5QrCode) {
                        console.log('Cleaning up existing scanner instance...');
                        try {
                            await html5QrCode.stop();
                        } catch (e) {
                            console.warn('Note: Could not stop existing scanner (might already be stopped):', e);
                        }
                        container.innerHTML = "";
                        html5QrCode = null;
                    }

                    console.log('Creating new Html5Qrcode instance...');
                    html5QrCode = new Html5Qrcode("qr-reader");
                    console.log('Html5Qrcode instance created successfully');

                    await new Promise(resolve => setTimeout(resolve, 300));

                    console.log('Attempting to call html5QrCode.start()...');
                    // Use more flexible constraints to avoid driver-level hangs
                    const config = {
                        fps: 10, // Lower FPS is more stable on older devices
                        qrbox: function(viewfinderWidth, viewfinderHeight) {
                            const minEdge = Math.min(viewfinderWidth, viewfinderHeight);
                            const fontSize = Math.floor(minEdge * 0.7);
                            return {
                                width: fontSize,
                                height: fontSize
                            };
                        },
                        aspectRatio: 1.0
                    };

                    await html5QrCode.start(cameraId, config,
                        (decodedText) => {
                            const now = Date.now();
                            if (lastScannedData === decodedText && (now - lastScanTime) < SCAN_DEBOUNCE_MS) return;
                            lastScannedData = decodedText;
                            lastScanTime = now;
                            processScannedItem(decodedText);
                        },
                        (errorMessage) => {
                            // Silent error - triggered on every frame where no QR is found
                        }
                    );

                    console.log('html5QrCode.start() resolved successfully');
                    isScanning = true;
                    showNotification('success', 'Scanner is now active');
                    console.log('--- SCANNER STARTUP SEQUENCE COMPLETE ---');
                } catch (err) {
                    console.error('--- SCANNER STARTUP CRASHED ---');
                    console.error('Error Details:', err);
                    showNotification('error', 'Scanner Error: ' + (err.message || 'Unknown error'));
                    isScanning = false;
                    html5QrCode = null;
                    if (container) container.innerHTML = "";
                } finally {
                    isScannerBusy = false;
                }
            }

            async function stopScanner(reason = 'requested') {
                if (isScannerBusy) return;
                if (html5QrCode && isScanning) {
                    isScannerBusy = true;
                    console.log(`Stopping scanner. Reason: ${reason}`);
                    try {
                        await html5QrCode.stop();
                        console.log('Scanner stopped successfully');
                    } catch (err) {
                        console.warn('Error stopping scanner (handled):', err);
                    } finally {
                        const container = document.getElementById("qr-reader");
                        if (container) container.innerHTML = "";
                        isScanning = false;
                        html5QrCode = null;
                        isScannerBusy = false;
                    }
                } else {
                    isScanning = false;
                    html5QrCode = null;
                    const container = document.getElementById("qr-reader");
                    if (container) container.innerHTML = "";
                }
            }

            document.getElementById('camera-select')?.addEventListener('change', function() {
                if (this.value) {
                    startScannerWithCamera(this.value);
                }
            });

            // Removed redundant playBeep definition

            function processScannedItem(scanData) {
                console.log('Processing scanned data:', scanData);

                // Parse the QR code data
                let extractedId = null;
                let extractedName = null;
                let extractedSerial = null;

                // Parse pipe-delimited format: ID:48|SN:N15615512224110495|N:TV Screen - Neiitec
                if (scanData.includes('|')) {
                    const parts = scanData.split('|');
                    for (let part of parts) {
                        if (part.startsWith('ID:')) {
                            extractedId = part.substring(3);
                        } else if (part.startsWith('N:')) {
                            extractedName = part.substring(2);
                        } else if (part.startsWith('SN:')) {
                            extractedSerial = part.substring(3);
                        }
                    }
                }

                // Parse JSON format: {"i":10,"n":"TV Screen - Neiitec","s":"N15115542147250267"}
                if (!extractedId) {
                    try {
                        const parsed = JSON.parse(scanData);
                        extractedId = parsed.i || parsed.id;
                        extractedName = parsed.n || parsed.name;
                        extractedSerial = parsed.s || parsed.serial;
                    } catch (e) {}
                }

                // If we successfully parsed the QR code, add directly to batch
                // WITHOUT checking database (for TV Screen items)
                if (extractedId && extractedName && extractedName.includes('TV Screen')) {
                    addToBatch({
                        id: extractedId,
                        name: extractedName,
                        serial_number: extractedSerial || 'N/A',
                        quantity: 1,
                        status: 'available',
                        stock_location: 'From QR Code'
                    });
                    return;
                }

                // For other items, try database lookup
                if (extractedId) {
                    fetchItemById(extractedId, extractedName, extractedSerial);
                } else {
                    fetchItemFromDatabase(scanData);
                }
            }


            function fetchItemById(itemId, fallbackName, fallbackSerial) {
                console.log('Fetching item by ID:', itemId);

                $.ajax({
                    url: 'api/get_item_by_scan.php',
                    method: 'POST',
                    data: {
                        scan_data: itemId
                    },
                    dataType: 'json',
                    success: function(response) {
                        console.log('API Response:', response);

                        if (response.success && response.item) {
                            addToBatch({
                                id: response.item.id,
                                name: response.item.item_name,
                                serial_number: response.item.serial_number,
                                quantity: 1,
                                status: response.item.status,
                                stock_location: response.item.stock_location || 'N/A',
                                image: response.item.image || null,
                                totalStock: response.item.total_group_count
                            });
                        } else if (fallbackName) {
                            // Item not in database, but we have data from QR code
                            if (confirm(`Item ID ${itemId} not found in database.\n\nName: ${fallbackName}\nSerial: ${fallbackSerial || 'N/A'}\n\nAdd as new item to batch?`)) {
                                addToBatch({
                                    id: itemId,
                                    name: fallbackName || `Item ${itemId}`,
                                    serial_number: fallbackSerial || 'N/A',
                                    quantity: 1,
                                    status: 'available',
                                    stock_location: 'From QR Code'
                                });
                            }
                        } else {
                            showNotification('error', `Item ID ${itemId} not found in database`);
                        }
                    },
                    error: function(xhr, error) {
                        console.error('API Error:', error);
                        showNotification('error', 'Error fetching item from database');
                    }
                });
            }

            function fetchItemFromDatabase(searchTerm) {
                console.log('Searching database for:', searchTerm);

                $.ajax({
                    url: 'api/get_item_by_scan.php',
                    method: 'POST',
                    data: {
                        scan_data: searchTerm
                    },
                    dataType: 'json',
                    success: function(response) {
                        console.log('Search response:', response);

                        if (response.success && response.item) {
                            addToBatch({
                                id: response.item.id,
                                name: response.item.item_name,
                                serial_number: response.item.serial_number,
                                quantity: 1,
                                status: response.item.status,
                                stock_location: response.item.stock_location || 'N/A',
                                image: response.item.image || null,
                                totalStock: response.item.total_group_count
                            });
                        } else {
                            const debugInfo = response.debug ?
                                `\n\nExtracted ID: ${response.debug.extracted_id || 'none'}\nExtracted Serial: ${response.debug.extracted_serial || 'none'}` : '';

                            if (confirm(`Item not found in database.\n\nScanned: "${searchTerm}"${debugInfo}\n\nAdd manually?`)) {
                                document.getElementById('itemName').value = searchTerm;
                                const manualTab = document.getElementById('manual-tab');
                                if (manualTab) new bootstrap.Tab(manualTab).show();
                            }
                        }
                    },
                    error: function(xhr, error) {
                        console.error('Search error:', error);
                        showNotification('error', 'Error searching for item');
                    }
                });
            }

            // Fixed playBeep function (no audio error)
            function playBeep() {
                try {
                    // Use Web Audio API - doesn't get blocked like Audio()
                    const AudioContext = window.AudioContext || window.webkitAudioContext;
                    if (AudioContext) {
                        const context = new AudioContext();
                        const oscillator = context.createOscillator();
                        const gain = context.createGain();
                        oscillator.connect(gain);
                        gain.connect(context.destination);
                        oscillator.frequency.value = 880;
                        gain.gain.value = 0.1;
                        oscillator.start();
                        gain.gain.exponentialRampToValueAtTime(0.00001, context.currentTime + 0.2);
                        oscillator.stop(context.currentTime + 0.2);
                        // Resume context if suspended (browser autoplay policy)
                        if (context.state === 'suspended') {
                            context.resume();
                        }
                    }
                } catch (e) {
                    console.log('Beep failed (non-critical):', e);
                }
            }

            // Play buzzer / warning sound for duplicate or errors
            function playWarningBeep() {
                try {
                    const AudioContext = window.AudioContext || window.webkitAudioContext;
                    if (AudioContext) {
                        const context = new AudioContext();
                        const oscillator = context.createOscillator();
                        const gain = context.createGain();
                        oscillator.connect(gain);
                        gain.connect(context.destination);

                        oscillator.type = 'sawtooth';
                        oscillator.frequency.value = 120;
                        gain.gain.value = 0.15;

                        oscillator.start();
                        gain.gain.exponentialRampToValueAtTime(0.00001, context.currentTime + 0.35);
                        oscillator.stop(context.currentTime + 0.35);

                        if (context.state === 'suspended') {
                            context.resume();
                        }
                    }
                } catch (e) {
                    console.log('Warning beep failed (non-critical):', e);
                }
            }

            // ==================== UTILITY FUNCTIONS ====================
            function showNotification(type, message) {
                const container = document.getElementById('centered-toast-container');
                if (!container) {
                    // Fallback to body append if container is missing
                    const notification = document.createElement('div');
                    const colors = {
                        success: '#28a745',
                        error: '#dc3545',
                        warning: '#ffc107',
                        info: '#17a2b8'
                    };
                    const icons = {
                        success: 'check-circle',
                        error: 'exclamation-triangle',
                        warning: 'exclamation-circle',
                        info: 'info-circle'
                    };
                    notification.style.cssText = `
                    position: fixed; top: 20px; right: 20px; z-index: 9999; min-width: 300px;
                    background: ${colors[type] || colors.info}; color: ${type === 'warning' ? '#212529' : 'white'}; padding: 15px 20px;
                    border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.15);
                `;
                    notification.innerHTML = `<i class="fas fa-${icons[type] || 'info-circle'} me-2"></i>${message}`;
                    document.body.appendChild(notification);
                    setTimeout(() => notification.remove(), 3000);
                    return;
                }

                const toastId = 'toast_' + Date.now();
                let bgClass = '';
                let textClass = 'text-white';
                let iconClass = '';

                if (type === 'success') {
                    bgClass = 'bg-success';
                    iconClass = 'fa-circle-check';
                } else if (type === 'warning') {
                    bgClass = 'bg-warning';
                    textClass = 'text-dark';
                    iconClass = 'fa-triangle-exclamation';
                } else if (type === 'error') {
                    bgClass = 'bg-danger';
                    iconClass = 'fa-circle-exclamation';
                } else {
                    bgClass = 'bg-info';
                    iconClass = 'fa-circle-info';
                }

                const html = `
                <div class="toast align-items-center ${textClass} ${bgClass} border-0 shadow-lg mb-2" id="${toastId}" role="alert" aria-live="assertive" aria-atomic="true" data-bs-delay="3000" style="border-radius: 12px; min-width: 300px;">
                    <div class="d-flex p-3 align-items-center">
                        <i class="fas ${iconClass} fa-lg me-3"></i>
                        <div class="toast-body fw-bold flex-grow-1" style="font-size: 1rem; letter-spacing: -0.2px;">
                            ${message}
                        </div>
                        <button type="button" class="btn-close ${type === 'warning' ? '' : 'btn-close-white'} ms-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                    </div>
                </div>
            `;

                container.insertAdjacentHTML('beforeend', html);
                const toastEl = document.getElementById(toastId);
                if (toastEl) {
                    const toast = new bootstrap.Toast(toastEl, {
                        delay: 3000
                    });
                    toast.show();

                    toastEl.addEventListener('hidden.bs.toast', () => {
                        toastEl.remove();
                    });
                }
            }

            function escapeHtml(text) {
                if (!text) return '';
                const div = document.createElement('div');
                div.textContent = text;
                return div.innerHTML;
            }

            function saveBatchToStorage() {
                try {
                    localStorage.setItem('batch_items', JSON.stringify(batchItems));
                } catch (e) {
                    console.warn('localStorage blocked by browser:', e);
                    // Fallback: save to sessionStorage instead
                    try {
                        sessionStorage.setItem('batch_items', JSON.stringify(batchItems));
                    } catch (e2) {
                        console.error('Both storage methods blocked');
                    }
                }
            }

            function loadBatchFromStorage() {
                try {
                    const saved = localStorage.getItem('batch_items') || sessionStorage.getItem('batch_items');
                    if (saved) {
                        batchItems = JSON.parse(saved);
                        updateBatchUI();
                    }
                } catch (e) {
                    console.warn('Storage access failed during load:', e);
                }
            }


            async function checkCameraPermissions() {
                try {
                    const stream = await navigator.mediaDevices.getUserMedia({
                        video: true
                    });
                    stream.getTracks().forEach(track => track.stop());
                    return true;
                } catch (err) {
                    if (err.name === 'NotAllowedError') {
                        showNotification('error', 'Camera permission denied. Please allow camera access.');
                    } else if (err.name === 'NotFoundError') {
                        showNotification('error', 'No camera found.');
                    }
                    return false;
                }
            }



            function clearManualForm() {
                ['itemName', 'serialNumber', 'location'].forEach(id => {
                    const el = document.getElementById(id);
                    if (el) el.value = '';
                });
                const qty = document.getElementById('quantity');
                if (qty) qty.value = 1;
                const status = document.getElementById('status');
                if (status) status.value = 'available';
                document.getElementById('itemSearchInput').value = '';
                document.getElementById('searchResults').style.display = 'none';
            }

            // ==================== BATCH MANAGEMENT ====================
            function addToBatch(item) {
                if (!item.id && !item.name) {
                    showNotification('error', 'Invalid item data');
                    return false;
                }

                const exists = batchItems.some(i => i.id == item.id || (i.name === item.name && i.serial_number === item.serial_number));
                if (exists) {
                    showNotification('warning', `Item "${item.name}" is already in this batch`);
                    playWarningBeep();
                    return false;
                }

                const newItem = {
                    id: item.id || Date.now(),
                    name: item.name || item.item_name || `Item ${Date.now()}`,
                    serial_number: item.serial_number || item.serial || 'N/A',
                    quantity: item.quantity || 1,
                    status: item.status || 'available',
                    stock_location: item.stock_location || item.location || 'N/A',
                    image: item.image || null,
                    totalStock: item.totalStock || null
                };

                batchItems.push(newItem);
                updateBatchUI();
                saveBatchToStorage();
                showNotification('success', `Added "${newItem.name}" to batch`);
                playBeep();

                // Re-render search results to update "Booked" status in real-time
                if (typeof lastSearchResults !== 'undefined' && lastSearchResults.length > 0) {
                    displaySearchResults(lastSearchResults);
                }

                return true;
            }

            function removeFromBatch(itemId) {
                const index = batchItems.findIndex(i => i.id == itemId);
                if (index !== -1) {
                    const itemName = batchItems[index].name;
                    batchItems.splice(index, 1);
                    updateBatchUI();
                    saveBatchToStorage();
                    showNotification('info', `Removed "${itemName}" from batch`);

                    // Re-render search results to update "Booked" status in real-time
                    if (typeof lastSearchResults !== 'undefined' && lastSearchResults.length > 0) {
                        displaySearchResults(lastSearchResults);
                    }
                }
            }

            function updateItemStatus(itemId, newStatus) {
                const index = batchItems.findIndex(i => i.id == itemId);
                if (index !== -1) {
                    batchItems[index].status = newStatus;
                    updateBatchUI();
                    saveBatchToStorage();
                    showNotification('success', `Status updated to "${newStatus}"`);
                }
            }

            function updateBatchUI() {
                const totalItems = batchItems.reduce((sum, item) => sum + (item.quantity || 1), 0);
                const available = batchItems.filter(i => i.status === 'available').length;
                const inUse = batchItems.filter(i => i.status === 'in_use').length;
                const maintenance = batchItems.filter(i => i.status === 'maintenance').length;
                const batchCount = batchItems.length;

                ['totalItems', 'totalItemsCount'].forEach(id => updateElementText(id, totalItems));
                updateElementText('availableItems', available);
                updateElementText('inUseItems', inUse);
                updateElementText('maintenanceItems', maintenance);
                ['batchCount', 'batchCountRight', 'batchItemCount'].forEach(id => updateElementText(id, batchCount));
                updateElementText('summaryAvailable', available);
                updateElementText('summaryInUse', inUse);
                updateElementText('summaryMaintenance', maintenance);
                updateElementText('confirmItemCount', batchCount);

                // ==================== LIVE GROUP BREAKDOWN TALLY ====================
                const breakdownSection = document.getElementById('groupBreakdownSection');
                const breakdownBody = document.getElementById('groupBreakdownBody');

                if (breakdownSection && breakdownBody) {
                    if (batchCount === 0) {
                        breakdownSection.style.display = 'none';
                    } else {
                        // Group batchItems by name
                        const groups = {};
                        batchItems.forEach(item => {
                            if (!groups[item.name]) {
                                groups[item.name] = {
                                    name: item.name,
                                    bookedCount: 0,
                                    totalStock: item.totalStock || 0
                                };
                            }
                            groups[item.name].bookedCount += (item.quantity || 1);
                            if (item.totalStock && !groups[item.name].totalStock) {
                                groups[item.name].totalStock = item.totalStock;
                            }
                        });

                        const groupList = Object.values(groups);
                        const totalGroupsCount = groupList.length;
                        const itemsPerPage = 5;
                        const maxPage = Math.ceil(totalGroupsCount / itemsPerPage) || 1;

                        // Clamp page
                        if (groupBreakdownPage > maxPage) {
                            groupBreakdownPage = maxPage;
                        }
                        if (groupBreakdownPage < 1) {
                            groupBreakdownPage = 1;
                        }

                        // Slice groups for current page
                        const startIndex = (groupBreakdownPage - 1) * itemsPerPage;
                        const endIndex = Math.min(startIndex + itemsPerPage, totalGroupsCount);
                        const pageGroups = groupList.slice(startIndex, endIndex);

                        // Build rows
                        let breakdownHtml = '';
                        pageGroups.forEach(g => {
                            const total = g.totalStock || g.bookedCount; // fallback if no database count
                            const remaining = Math.max(0, total - g.bookedCount);
                            breakdownHtml += `
                            <tr style="border-bottom: 1px solid rgba(0,0,0,0.02); vertical-align: middle;">
                                <td class="fw-bold text-dark py-2">${escapeHtml(g.name)}</td>
                                <td class="text-center py-2"><span class="badge bg-primary rounded-pill px-2" style="font-size: 0.85rem;">${g.bookedCount}</span></td>
                                <td class="text-center py-2"><span class="badge bg-success rounded-pill px-2" style="font-size: 0.85rem;">${remaining}</span></td>
                                <td class="text-center py-2 text-muted fw-bold">${total}</td>
                            </tr>
                            `;
                        });

                        breakdownBody.innerHTML = breakdownHtml;
                        breakdownSection.style.display = 'block';

                        // Show/hide pagination controls based on total counts
                        const paginationContainer = document.getElementById('groupBreakdownPagination');
                        if (paginationContainer) {
                            if (totalGroupsCount > itemsPerPage) {
                                paginationContainer.style.setProperty('display', 'flex', 'important');
                                const infoSpan = document.getElementById('groupBreakdownPaginationInfo');
                                if (infoSpan) {
                                    infoSpan.textContent = `Showing ${startIndex + 1}-${endIndex} of ${totalGroupsCount}`;
                                }
                                const prevBtn = document.getElementById('groupBreakdownPrevBtn');
                                const nextBtn = document.getElementById('groupBreakdownNextBtn');
                                if (prevBtn) prevBtn.disabled = groupBreakdownPage === 1;
                                if (nextBtn) nextBtn.disabled = groupBreakdownPage === maxPage;
                            } else {
                                paginationContainer.style.setProperty('display', 'none', 'important');
                            }
                        }
                    }
                }

                const submitBtn = document.getElementById('submitBatchModalBtn');
                if (submitBtn) {
                    submitBtn.disabled = batchCount === 0;
                    submitBtn.innerHTML = `Submit Batch (${batchCount})`;
                }
                const clearBtn = document.getElementById('clearBatchBtn');
                if (clearBtn) clearBtn.disabled = batchCount === 0;
                const openModalBtn = document.getElementById('openBatchModalBtn');
                if (openModalBtn) openModalBtn.disabled = batchCount === 0;

                const itemsList = document.getElementById('batchItemsList');
                if (itemsList) {
                    if (batchCount === 0) {
                        itemsList.innerHTML = `<div class="empty-state"><div class="empty-icon"><i class="fas fa-box-open"></i></div><h6>No items in batch</h6><p class="text-muted">Scan a QR code, search, or manually add items</p></div>`;
                    } else {
                        itemsList.innerHTML = batchItems.map(item => `
                        <div class="item-card" data-item-id="${item.id}">
                            <div class="d-flex position-relative">
                                <div class="flex-grow-1">
                                    <div class="d-flex align-items-center mb-3">
                                        <div class="item-name" style="font-size: 1.2rem; font-weight: 700;">${escapeHtml(item.name)}</div>
                                        ${item.quantity > 1 ? `<span class="badge bg-info ms-2">x${item.quantity}</span>` : ''}
                                    </div>
                                    <div class="item-details" style="display: block;">
                                        <div class="detail-item mb-3">
                                            <span class="detail-label d-block mb-1" style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.5px; color: #999;">Serial</span>
                                            <span class="detail-value"><code style="color: #d63384; font-size: 1rem; font-weight: 600;">${escapeHtml(item.serial_number)}</code></span>
                                        </div>
                                        <div class="detail-item">
                                            <span class="detail-label d-block mb-1" style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.5px; color: #999;">Location</span>
                                            <span class="detail-value text-muted" style="font-size: 0.95rem;">${escapeHtml(item.stock_location)}</span>
                                        </div>
                                    </div>
>>>>>>> addf346 (Latest Upload - Events cards, OverView, Items status..)
                                </div>
                                <!-- Breakdown Pagination Controls -->
                                <div id="groupBreakdownPagination" class="d-flex justify-content-between align-items-center mt-2 pt-2 border-top" style="display: none; font-size: 0.76rem;">
                                    <span class="text-muted" id="groupBreakdownPaginationInfo">Showing 1-5 of 6</span>
                                    <div class="btn-group btn-group-xs">
                                        <button class="btn btn-outline-primary btn-xs py-0 px-2" style="font-size: 0.7rem; line-height: 1.5;" id="groupBreakdownPrevBtn" onclick="changeGroupBreakdownPage(-1)"><i class="fas fa-chevron-left"></i></button>
                                        <button class="btn btn-outline-primary btn-xs py-0 px-2" style="font-size: 0.7rem; line-height: 1.5;" id="groupBreakdownNextBtn" onclick="changeGroupBreakdownPage(1)"><i class="fas fa-chevron-right"></i></button>
                                    </div>
                                </div>
                            </div>

                            <div class="items-list" id="batchItemsList">
                                <div class="empty-state">
                                    <div class="empty-icon"><i class="fas fa-box-open"></i></div>
                                    <h6>No items in batch</h6>
                                    <p class="text-muted">Scan a QR code, search, or manually add items</p>
                                </div>
                            </div>
                        </div>
<<<<<<< HEAD
                        <div class="panel-footer">
                            <div class="text-end">
                                <button class="btn btn-sm btn-primary" id="openBatchModalBtn" disabled>
                                    <i class="fas fa-external-link-alt me-1"></i> Review & Submit
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>

        <!-- jQuery FIRST -->
        <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

        <script>
            // Prevent conflicts and ensure jQuery is fully loaded
            if (typeof jQuery !== 'undefined') {
                console.log('jQuery version:', jQuery.fn.jquery);
            }
        </script>

        <!-- Bootstrap JS -->
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

        <!-- QR Scanner Library -->
        <script src="https://cdn.jsdelivr.net/npm/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>

        <script>
            // ==================== GLOBAL VARIABLES ====================
            let batchItems = [];
            let isTechnicianAuthenticated = false;
            let authenticatedTechnician = null;
            let isUserTechnician = <?php echo $is_technician ? 'true' : 'false'; ?>;
            let loggedInUserId = '<?php echo $logged_in_user_id; ?>';
            let html5QrCode = null;
            let isScanning = false;
            let isScannerBusy = false;
            let lastScannedData = null;
            let lastScanTime = 0;
            const SCAN_DEBOUNCE_MS = 1000;
            let currentModal = null;
            let lastSearchResults = [];
            let groupBreakdownPage = 1;

            // ==================== SEARCH FUNCTIONS ====================
            let searchDebounceTimer;

            function searchItemsDebounced() {
                clearTimeout(searchDebounceTimer);
                searchDebounceTimer = setTimeout(() => {
                    searchItems();
                }, 300);
            }

            function searchItems() {
                const searchTerm = document.getElementById('itemSearchInput').value.trim();
                if (searchTerm.length < 2) {
                    document.getElementById('searchResults').style.display = 'none';
                    return;
                }

                $.ajax({
                    url: 'api/search_items.php',
                    method: 'GET',
                    data: {
                        q: searchTerm
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success && response.items && response.items.length > 0) {
                            displaySearchResults(response.items);
                        } else {
                            document.getElementById('searchResults').style.display = 'none';
                            showNotification('info', 'No items found');
                        }
                    },
                    error: function() {
                        showNotification('error', 'Error searching items');
                    }
                });
            }

            function displaySearchResults(items) {
                lastSearchResults = items;
                const resultsDiv = document.getElementById('searchResults');
                const resultsList = document.getElementById('searchResultsList');

                resultsList.innerHTML = '';
                items.forEach(item => {
                    const isBooked = batchItems.some(i => i.id == item.id);
                    const statusClass = item.status === 'available' ? 'success' : (item.status === 'in_use' ? 'warning' : 'danger');
                    const itemElement = document.createElement('div');

                    if (isBooked) {
                        itemElement.className = 'list-group-item list-group-item-action bg-light text-muted';
                        itemElement.style.opacity = '0.65';
                        itemElement.innerHTML = `
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <strong>${escapeHtml(item.item_name)}</strong> <span class="badge bg-secondary ms-1" style="font-size: 0.75rem; letter-spacing: 0.5px;">Booked</span><br>
                                <small class="text-muted">Serial: ${escapeHtml(item.serial_number)}</small><br>
                                <small>Status: <span class="badge bg-${statusClass}">${item.status}</span> | Location: ${escapeHtml(item.stock_location || 'N/A')}</small>
                            </div>
                            <button class="btn btn-sm btn-secondary fw-bold px-3 py-1" style="border-radius: 6px;" disabled>
                                <i class="fas fa-check me-1"></i> Booked
                            </button>
                        </div>
                    `;
                    } else {
                        itemElement.className = 'list-group-item list-group-item-action';
                        itemElement.innerHTML = `
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <strong>${escapeHtml(item.item_name)}</strong><br>
                                <small class="text-muted">Serial: ${escapeHtml(item.serial_number)}</small><br>
                                <small>Status: <span class="badge bg-${statusClass}">${item.status}</span> | Location: ${escapeHtml(item.stock_location || 'N/A')}</small>
                            </div>
                            <button class="btn btn-sm btn-success fw-bold px-3 py-1" style="border-radius: 6px; background-color: #2e7d32;" onclick='addSearchedItemToBatch(${JSON.stringify(item)})'>
                                <i class="fas fa-plus me-1"></i> Add
                            </button>
                        </div>
                    `;
                    }
                    resultsList.appendChild(itemElement);
                });
                resultsDiv.style.display = 'block';
            }

            function addSearchedItemToBatch(item) {
                const newItem = {
                    id: item.id,
                    name: item.item_name,
                    serial_number: item.serial_number,
                    quantity: 1,
                    status: item.status,
                    stock_location: item.stock_location || 'N/A',
                    image: item.image || null,
                    totalStock: item.total_group_count
                };
                const added = addToBatch(newItem);
                if (added) {
                    document.getElementById('searchResults').style.display = 'none';
                    document.getElementById('itemSearchInput').value = '';
=======
                    `).join('');
                    }
>>>>>>> addf346 (Latest Upload - Events cards, OverView, Items status..)
                }

<<<<<<< HEAD
            // ==================== QR SCANNER FUNCTIONS ====================
            async function startScanner() {
                if (isScannerBusy) {
                    console.log('Scanner is busy, ignoring start request');
                    return;
                }
                const hasPermission = await checkCameraPermissions();
                if (!hasPermission) return;

                try {
                    isScannerBusy = true;
                    const cameras = await Html5Qrcode.getCameras();
                    if (cameras && cameras.length) {
                        const cameraSelect = document.getElementById('camera-select');
                        cameraSelect.innerHTML = '<option value="">Select Camera</option>';
                        cameras.forEach(camera => {
                            const option = document.createElement('option');
                            option.value = camera.id;
                            option.text = camera.label || `Camera ${cameraSelect.children.length}`;
                            cameraSelect.appendChild(option);
                        });

                        let backCamera = cameras.find(c => c.label.toLowerCase().includes('back') || c.label.toLowerCase().includes('rear'));
                        let selectedCamera = backCamera || cameras[0];
                        cameraSelect.value = selectedCamera.id;

                        isScannerBusy = false; // Release lock before calling startWithCamera
                        await startScannerWithCamera(selectedCamera.id);
                    } else {
                        showNotification('error', 'No cameras found');
                        isScannerBusy = false;
                    }
                } catch (err) {
                    console.error('Camera enumeration error:', err);
                    showNotification('error', 'Could not access camera list');
                    isScannerBusy = false;
                }
            }

            async function startScannerWithCamera(cameraId) {
                if (isScannerBusy) return;
                isScannerBusy = true;

                try {
                    console.log('--- SCANNER STARTUP SEQUENCE START ---');
                    console.log('Target Camera ID:', cameraId);

                    const container = document.getElementById("qr-reader");
                    if (!container) {
                        console.error('CRITICAL: #qr-reader container not found in DOM');
                        showNotification('error', 'Scanner container missing');
                        return;
                    }

                    if (html5QrCode) {
                        console.log('Cleaning up existing scanner instance...');
                        try {
                            await html5QrCode.stop();
                        } catch (e) {
                            console.warn('Note: Could not stop existing scanner (might already be stopped):', e);
                        }
                        container.innerHTML = "";
                        html5QrCode = null;
                    }

                    console.log('Creating new Html5Qrcode instance...');
                    html5QrCode = new Html5Qrcode("qr-reader");
                    console.log('Html5Qrcode instance created successfully');

                    await new Promise(resolve => setTimeout(resolve, 300));

                    console.log('Attempting to call html5QrCode.start()...');
                    // Use more flexible constraints to avoid driver-level hangs
                    const config = {
                        fps: 10, // Lower FPS is more stable on older devices
                        qrbox: function(viewfinderWidth, viewfinderHeight) {
                            const minEdge = Math.min(viewfinderWidth, viewfinderHeight);
                            const fontSize = Math.floor(minEdge * 0.7);
                            return {
                                width: fontSize,
                                height: fontSize
                            };
                        },
                        aspectRatio: 1.0
                    };

                    await html5QrCode.start(cameraId, config,
                        (decodedText) => {
                            const now = Date.now();
                            if (lastScannedData === decodedText && (now - lastScanTime) < SCAN_DEBOUNCE_MS) return;
                            lastScannedData = decodedText;
                            lastScanTime = now;
                            processScannedItem(decodedText);
                        },
                        (errorMessage) => {
                            // Silent error - triggered on every frame where no QR is found
                        }
                    );

                    console.log('html5QrCode.start() resolved successfully');
                    isScanning = true;
                    showNotification('success', 'Scanner is now active');
                    console.log('--- SCANNER STARTUP SEQUENCE COMPLETE ---');
                } catch (err) {
                    console.error('--- SCANNER STARTUP CRASHED ---');
                    console.error('Error Details:', err);
                    showNotification('error', 'Scanner Error: ' + (err.message || 'Unknown error'));
                    isScanning = false;
                    html5QrCode = null;
                    if (container) container.innerHTML = "";
                } finally {
                    isScannerBusy = false;
                }
            }

            async function stopScanner(reason = 'requested') {
                if (isScannerBusy) return;
                if (html5QrCode && isScanning) {
                    isScannerBusy = true;
                    console.log(`Stopping scanner. Reason: ${reason}`);
                    try {
                        await html5QrCode.stop();
                        console.log('Scanner stopped successfully');
                    } catch (err) {
                        console.warn('Error stopping scanner (handled):', err);
                    } finally {
                        const container = document.getElementById("qr-reader");
                        if (container) container.innerHTML = "";
                        isScanning = false;
                        html5QrCode = null;
                        isScannerBusy = false;
                    }
                } else {
                    isScanning = false;
                    html5QrCode = null;
                    const container = document.getElementById("qr-reader");
                    if (container) container.innerHTML = "";
                }
            }

            document.getElementById('camera-select')?.addEventListener('change', function() {
                if (this.value) {
                    startScannerWithCamera(this.value);
                }
            });

            // Removed redundant playBeep definition

            function processScannedItem(scanData) {
                console.log('Processing scanned data:', scanData);

                // Parse the QR code data
                let extractedId = null;
                let extractedName = null;
                let extractedSerial = null;

                // Parse pipe-delimited format: ID:48|SN:N15615512224110495|N:TV Screen - Neiitec
                if (scanData.includes('|')) {
                    const parts = scanData.split('|');
                    for (let part of parts) {
                        if (part.startsWith('ID:')) {
                            extractedId = part.substring(3);
                        } else if (part.startsWith('N:')) {
                            extractedName = part.substring(2);
                        } else if (part.startsWith('SN:')) {
                            extractedSerial = part.substring(3);
                        }
                    }
                }

                // Parse JSON format: {"i":10,"n":"TV Screen - Neiitec","s":"N15115542147250267"}
                if (!extractedId) {
                    try {
                        const parsed = JSON.parse(scanData);
                        extractedId = parsed.i || parsed.id;
                        extractedName = parsed.n || parsed.name;
                        extractedSerial = parsed.s || parsed.serial;
                    } catch (e) {}
                }

                // If we successfully parsed the QR code, add directly to batch
                // WITHOUT checking database (for TV Screen items)
                if (extractedId && extractedName && extractedName.includes('TV Screen')) {
                    addToBatch({
                        id: extractedId,
                        name: extractedName,
                        serial_number: extractedSerial || 'N/A',
                        quantity: 1,
                        status: 'available',
                        stock_location: 'From QR Code'
                    });
                    return;
                }

                // For other items, try database lookup
                if (extractedId) {
                    fetchItemById(extractedId, extractedName, extractedSerial);
                } else {
                    fetchItemFromDatabase(scanData);
                }
            }


            function fetchItemById(itemId, fallbackName, fallbackSerial) {
                console.log('Fetching item by ID:', itemId);

                $.ajax({
                    url: 'api/get_item_by_scan.php',
                    method: 'POST',
                    data: {
                        scan_data: itemId
                    },
                    dataType: 'json',
                    success: function(response) {
                        console.log('API Response:', response);

                        if (response.success && response.item) {
                            addToBatch({
                                id: response.item.id,
                                name: response.item.item_name,
                                serial_number: response.item.serial_number,
                                quantity: 1,
                                status: response.item.status,
                                stock_location: response.item.stock_location || 'N/A',
                                image: response.item.image || null,
                                totalStock: response.item.total_group_count
                            });
                        } else if (fallbackName) {
                            // Item not in database, but we have data from QR code
                            if (confirm(`Item ID ${itemId} not found in database.\n\nName: ${fallbackName}\nSerial: ${fallbackSerial || 'N/A'}\n\nAdd as new item to batch?`)) {
                                addToBatch({
                                    id: itemId,
                                    name: fallbackName || `Item ${itemId}`,
                                    serial_number: fallbackSerial || 'N/A',
                                    quantity: 1,
                                    status: 'available',
                                    stock_location: 'From QR Code'
                                });
                            }
                        } else {
                            showNotification('error', `Item ID ${itemId} not found in database`);
                        }
                    },
                    error: function(xhr, error) {
                        console.error('API Error:', error);
                        showNotification('error', 'Error fetching item from database');
                    }
                });
            }

            function fetchItemFromDatabase(searchTerm) {
                console.log('Searching database for:', searchTerm);

                $.ajax({
                    url: 'api/get_item_by_scan.php',
                    method: 'POST',
                    data: {
                        scan_data: searchTerm
                    },
                    dataType: 'json',
                    success: function(response) {
                        console.log('Search response:', response);

                        if (response.success && response.item) {
                            addToBatch({
                                id: response.item.id,
                                name: response.item.item_name,
                                serial_number: response.item.serial_number,
                                quantity: 1,
                                status: response.item.status,
                                stock_location: response.item.stock_location || 'N/A',
                                image: response.item.image || null,
                                totalStock: response.item.total_group_count
                            });
                        } else {
                            const debugInfo = response.debug ?
                                `\n\nExtracted ID: ${response.debug.extracted_id || 'none'}\nExtracted Serial: ${response.debug.extracted_serial || 'none'}` : '';

                            if (confirm(`Item not found in database.\n\nScanned: "${searchTerm}"${debugInfo}\n\nAdd manually?`)) {
                                document.getElementById('itemName').value = searchTerm;
                                const manualTab = document.getElementById('manual-tab');
                                if (manualTab) new bootstrap.Tab(manualTab).show();
                            }
                        }
                    },
                    error: function(xhr, error) {
                        console.error('Search error:', error);
                        showNotification('error', 'Error searching for item');
                    }
                });
            }

            // Fixed playBeep function (no audio error)
            function playBeep() {
                try {
                    // Use Web Audio API - doesn't get blocked like Audio()
                    const AudioContext = window.AudioContext || window.webkitAudioContext;
                    if (AudioContext) {
                        const context = new AudioContext();
                        const oscillator = context.createOscillator();
                        const gain = context.createGain();
                        oscillator.connect(gain);
                        gain.connect(context.destination);
                        oscillator.frequency.value = 880;
                        gain.gain.value = 0.1;
                        oscillator.start();
                        gain.gain.exponentialRampToValueAtTime(0.00001, context.currentTime + 0.2);
                        oscillator.stop(context.currentTime + 0.2);
                        // Resume context if suspended (browser autoplay policy)
                        if (context.state === 'suspended') {
                            context.resume();
                        }
                    }
                } catch (e) {
                    console.log('Beep failed (non-critical):', e);
                }
            }

            // Play buzzer / warning sound for duplicate or errors
            function playWarningBeep() {
                try {
                    const AudioContext = window.AudioContext || window.webkitAudioContext;
                    if (AudioContext) {
                        const context = new AudioContext();
                        const oscillator = context.createOscillator();
                        const gain = context.createGain();
                        oscillator.connect(gain);
                        gain.connect(context.destination);

                        oscillator.type = 'sawtooth';
                        oscillator.frequency.value = 120;
                        gain.gain.value = 0.15;

                        oscillator.start();
                        gain.gain.exponentialRampToValueAtTime(0.00001, context.currentTime + 0.35);
                        oscillator.stop(context.currentTime + 0.35);

                        if (context.state === 'suspended') {
                            context.resume();
                        }
                    }
                } catch (e) {
                    console.log('Warning beep failed (non-critical):', e);
                }
            }

            // ==================== UTILITY FUNCTIONS ====================
            function showNotification(type, message) {
                const container = document.getElementById('centered-toast-container');
                if (!container) {
                    // Fallback to body append if container is missing
                    const notification = document.createElement('div');
                    const colors = {
                        success: '#28a745',
                        error: '#dc3545',
                        warning: '#ffc107',
                        info: '#17a2b8'
                    };
                    const icons = {
                        success: 'check-circle',
                        error: 'exclamation-triangle',
                        warning: 'exclamation-circle',
                        info: 'info-circle'
                    };
                    notification.style.cssText = `
                    position: fixed; top: 20px; right: 20px; z-index: 9999; min-width: 300px;
                    background: ${colors[type] || colors.info}; color: ${type === 'warning' ? '#212529' : 'white'}; padding: 15px 20px;
                    border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.15);
                `;
                    notification.innerHTML = `<i class="fas fa-${icons[type] || 'info-circle'} me-2"></i>${message}`;
                    document.body.appendChild(notification);
                    setTimeout(() => notification.remove(), 3000);
                    return;
                }

                const toastId = 'toast_' + Date.now();
                let bgClass = '';
                let textClass = 'text-white';
                let iconClass = '';

                if (type === 'success') {
                    bgClass = 'bg-success';
                    iconClass = 'fa-circle-check';
                } else if (type === 'warning') {
                    bgClass = 'bg-warning';
                    textClass = 'text-dark';
                    iconClass = 'fa-triangle-exclamation';
                } else if (type === 'error') {
                    bgClass = 'bg-danger';
                    iconClass = 'fa-circle-exclamation';
                } else {
                    bgClass = 'bg-info';
                    iconClass = 'fa-circle-info';
                }

                const html = `
                <div class="toast align-items-center ${textClass} ${bgClass} border-0 shadow-lg mb-2" id="${toastId}" role="alert" aria-live="assertive" aria-atomic="true" data-bs-delay="3000" style="border-radius: 12px; min-width: 300px;">
                    <div class="d-flex p-3 align-items-center">
                        <i class="fas ${iconClass} fa-lg me-3"></i>
                        <div class="toast-body fw-bold flex-grow-1" style="font-size: 1rem; letter-spacing: -0.2px;">
                            ${message}
                        </div>
                        <button type="button" class="btn-close ${type === 'warning' ? '' : 'btn-close-white'} ms-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                    </div>
                </div>
            `;

                container.insertAdjacentHTML('beforeend', html);
                const toastEl = document.getElementById(toastId);
                if (toastEl) {
                    const toast = new bootstrap.Toast(toastEl, {
                        delay: 3000
                    });
                    toast.show();

                    toastEl.addEventListener('hidden.bs.toast', () => {
                        toastEl.remove();
                    });
                }
            }

            function escapeHtml(text) {
                if (!text) return '';
                const div = document.createElement('div');
                div.textContent = text;
                return div.innerHTML;
            }

            function saveBatchToStorage() {
                try {
                    localStorage.setItem('batch_items', JSON.stringify(batchItems));
                } catch (e) {
                    console.warn('localStorage blocked by browser:', e);
                    // Fallback: save to sessionStorage instead
                    try {
                        sessionStorage.setItem('batch_items', JSON.stringify(batchItems));
                    } catch (e2) {
                        console.error('Both storage methods blocked');
                    }
                }
            }

            function loadBatchFromStorage() {
                try {
                    const saved = localStorage.getItem('batch_items') || sessionStorage.getItem('batch_items');
                    if (saved) {
                        batchItems = JSON.parse(saved);
                        updateBatchUI();
                    }
                } catch (e) {
                    console.warn('Storage access failed during load:', e);
                }
            }


            async function checkCameraPermissions() {
                try {
                    const stream = await navigator.mediaDevices.getUserMedia({
                        video: true
                    });
                    stream.getTracks().forEach(track => track.stop());
                    return true;
                } catch (err) {
                    if (err.name === 'NotAllowedError') {
                        showNotification('error', 'Camera permission denied. Please allow camera access.');
                    } else if (err.name === 'NotFoundError') {
                        showNotification('error', 'No camera found.');
                    }
                    return false;
                }
            }



            function clearManualForm() {
                ['itemName', 'serialNumber', 'location'].forEach(id => {
                    const el = document.getElementById(id);
                    if (el) el.value = '';
                });
                const qty = document.getElementById('quantity');
                if (qty) qty.value = 1;
                const status = document.getElementById('status');
                if (status) status.value = 'available';
                document.getElementById('itemSearchInput').value = '';
                document.getElementById('searchResults').style.display = 'none';
            }

            // ==================== BATCH MANAGEMENT ====================
            function addToBatch(item) {
                if (!item.id && !item.name) {
                    showNotification('error', 'Invalid item data');
                    return false;
                }

                const exists = batchItems.some(i => i.id == item.id || (i.name === item.name && i.serial_number === item.serial_number));
                if (exists) {
                    showNotification('warning', `Item "${item.name}" is already in this batch`);
                    playWarningBeep();
                    return false;
                }

                const newItem = {
                    id: item.id || Date.now(),
                    name: item.name || item.item_name || `Item ${Date.now()}`,
                    serial_number: item.serial_number || item.serial || 'N/A',
                    quantity: item.quantity || 1,
                    status: item.status || 'available',
                    stock_location: item.stock_location || item.location || 'N/A',
                    image: item.image || null,
                    totalStock: item.totalStock || null
                };

                batchItems.push(newItem);
                updateBatchUI();
                saveBatchToStorage();
                showNotification('success', `Added "${newItem.name}" to batch`);
                playBeep();

                // Re-render search results to update "Booked" status in real-time
                if (typeof lastSearchResults !== 'undefined' && lastSearchResults.length > 0) {
                    displaySearchResults(lastSearchResults);
                }

                return true;
            }

            function removeFromBatch(itemId) {
                const index = batchItems.findIndex(i => i.id == itemId);
                if (index !== -1) {
                    const itemName = batchItems[index].name;
                    batchItems.splice(index, 1);
                    updateBatchUI();
                    saveBatchToStorage();
                    showNotification('info', `Removed "${itemName}" from batch`);

                    // Re-render search results to update "Booked" status in real-time
                    if (typeof lastSearchResults !== 'undefined' && lastSearchResults.length > 0) {
                        displaySearchResults(lastSearchResults);
                    }
                }
            }

            function updateItemStatus(itemId, newStatus) {
                const index = batchItems.findIndex(i => i.id == itemId);
                if (index !== -1) {
                    batchItems[index].status = newStatus;
                    updateBatchUI();
                    saveBatchToStorage();
                    showNotification('success', `Status updated to "${newStatus}"`);
                }
            }

            function updateBatchUI() {
                const totalItems = batchItems.reduce((sum, item) => sum + (item.quantity || 1), 0);
                const available = batchItems.filter(i => i.status === 'available').length;
                const inUse = batchItems.filter(i => i.status === 'in_use').length;
                const maintenance = batchItems.filter(i => i.status === 'maintenance').length;
                const batchCount = batchItems.length;

                ['totalItems', 'totalItemsCount'].forEach(id => updateElementText(id, totalItems));
                updateElementText('availableItems', available);
                updateElementText('inUseItems', inUse);
                updateElementText('maintenanceItems', maintenance);
                ['batchCount', 'batchCountRight', 'batchItemCount'].forEach(id => updateElementText(id, batchCount));
                updateElementText('summaryAvailable', available);
                updateElementText('summaryInUse', inUse);
                updateElementText('summaryMaintenance', maintenance);
                updateElementText('confirmItemCount', batchCount);

                // ==================== LIVE GROUP BREAKDOWN TALLY ====================
                const breakdownSection = document.getElementById('groupBreakdownSection');
                const breakdownBody = document.getElementById('groupBreakdownBody');

                if (breakdownSection && breakdownBody) {
                    if (batchCount === 0) {
                        breakdownSection.style.display = 'none';
                    } else {
                        // Group batchItems by name
                        const groups = {};
                        batchItems.forEach(item => {
                            if (!groups[item.name]) {
                                groups[item.name] = {
                                    name: item.name,
                                    bookedCount: 0,
                                    totalStock: item.totalStock || 0
                                };
                            }
                            groups[item.name].bookedCount += (item.quantity || 1);
                            if (item.totalStock && !groups[item.name].totalStock) {
                                groups[item.name].totalStock = item.totalStock;
                            }
                        });

                        const groupList = Object.values(groups);
                        const totalGroupsCount = groupList.length;
                        const itemsPerPage = 5;
                        const maxPage = Math.ceil(totalGroupsCount / itemsPerPage) || 1;

                        // Clamp page
                        if (groupBreakdownPage > maxPage) {
                            groupBreakdownPage = maxPage;
                        }
                        if (groupBreakdownPage < 1) {
                            groupBreakdownPage = 1;
                        }

                        // Slice groups for current page
                        const startIndex = (groupBreakdownPage - 1) * itemsPerPage;
                        const endIndex = Math.min(startIndex + itemsPerPage, totalGroupsCount);
                        const pageGroups = groupList.slice(startIndex, endIndex);

                        // Build rows
                        let breakdownHtml = '';
                        pageGroups.forEach(g => {
                            const total = g.totalStock || g.bookedCount; // fallback if no database count
                            const remaining = Math.max(0, total - g.bookedCount);
                            breakdownHtml += `
                            <tr style="border-bottom: 1px solid rgba(0,0,0,0.02); vertical-align: middle;">
                                <td class="fw-bold text-dark py-2">${escapeHtml(g.name)}</td>
                                <td class="text-center py-2"><span class="badge bg-primary rounded-pill px-2" style="font-size: 0.85rem;">${g.bookedCount}</span></td>
                                <td class="text-center py-2"><span class="badge bg-success rounded-pill px-2" style="font-size: 0.85rem;">${remaining}</span></td>
                                <td class="text-center py-2 text-muted fw-bold">${total}</td>
                            </tr>
                            `;
                        });

                        breakdownBody.innerHTML = breakdownHtml;
                        breakdownSection.style.display = 'block';

                        // Show/hide pagination controls based on total counts
                        const paginationContainer = document.getElementById('groupBreakdownPagination');
                        if (paginationContainer) {
                            if (totalGroupsCount > itemsPerPage) {
                                paginationContainer.style.setProperty('display', 'flex', 'important');
                                const infoSpan = document.getElementById('groupBreakdownPaginationInfo');
                                if (infoSpan) {
                                    infoSpan.textContent = `Showing ${startIndex + 1}-${endIndex} of ${totalGroupsCount}`;
                                }
                                const prevBtn = document.getElementById('groupBreakdownPrevBtn');
                                const nextBtn = document.getElementById('groupBreakdownNextBtn');
                                if (prevBtn) prevBtn.disabled = groupBreakdownPage === 1;
                                if (nextBtn) nextBtn.disabled = groupBreakdownPage === maxPage;
                            } else {
                                paginationContainer.style.setProperty('display', 'none', 'important');
                            }
                        }
                    }
                }

                const submitBtn = document.getElementById('submitBatchModalBtn');
                if (submitBtn) {
                    submitBtn.disabled = batchCount === 0;
                    submitBtn.innerHTML = `Submit Batch (${batchCount})`;
                }
                const clearBtn = document.getElementById('clearBatchBtn');
                if (clearBtn) clearBtn.disabled = batchCount === 0;
                const openModalBtn = document.getElementById('openBatchModalBtn');
                if (openModalBtn) openModalBtn.disabled = batchCount === 0;

                const itemsList = document.getElementById('batchItemsList');
                if (itemsList) {
                    if (batchCount === 0) {
                        itemsList.innerHTML = `<div class="empty-state"><div class="empty-icon"><i class="fas fa-box-open"></i></div><h6>No items in batch</h6><p class="text-muted">Scan a QR code, search, or manually add items</p></div>`;
                    } else {
                        itemsList.innerHTML = batchItems.map(item => `
                        <div class="item-card" data-item-id="${item.id}">
                            <div class="item-header">
                                <div class="item-name">${escapeHtml(item.name)}</div>
                                <div class="item-actions">
                                    ${item.quantity > 1 ? `<span class="badge bg-info me-2">x${item.quantity}</span>` : ''}
                                    <button class="btn btn-action btn-sm btn-success" onclick="updateItemStatus('${item.id}', 'available')" title="Available" ${item.status === 'available' ? 'disabled' : ''}>
                                        <i class="fas fa-check"></i>
                                    </button>
                                    <button class="btn btn-action btn-sm btn-warning" onclick="updateItemStatus('${item.id}', 'in_use')" title="In Use" ${item.status === 'in_use' ? 'disabled' : ''}>
                                        <i class="fas fa-wrench"></i>
                                    </button>
                                    <button class="btn btn-action btn-sm btn-danger" onclick="removeFromBatch('${item.id}')" title="Remove">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="item-details">
                                <div class="detail-item"><span class="detail-label">Serial</span><span class="detail-value"><code>${escapeHtml(item.serial_number)}</code></span></div>
                                <div class="detail-item"><span class="detail-label">Status</span><span class="detail-value"><span class="badge-status status-${item.status}">${item.status}</span></span></div>
                                <div class="detail-item"><span class="detail-label">Location</span><span class="detail-value">${escapeHtml(item.stock_location)}</span></div>
                            </div>
                        </div>
                    `).join('');
                    }
                }

=======
>>>>>>> addf346 (Latest Upload - Events cards, OverView, Items status..)
                const previewTable = document.getElementById('itemsPreviewTable');
                if (previewTable) {
                    if (batchCount === 0) {
                        previewTable.innerHTML = '<tr><td colspan="5" class="text-center text-muted">No items in batch</td></tr>';
                    } else {
                        previewTable.innerHTML = batchItems.map(item => `
                        <tr>
                            <td>${escapeHtml(item.name)}</td>
                            <td><code>${escapeHtml(item.serial_number)}</code></td>
                            <td class="text-center">${item.quantity || 1}</td>
                            <td>${escapeHtml(item.stock_location)}</td>
                            <td><span class="badge bg-${item.status === 'available' ? 'success' : item.status === 'in_use' ? 'warning' : 'danger'}">${item.status}</span></td>
                        </tr>
                    `).join('');
                    }
                }
            }

            function updateElementText(elementId, text) {
                const element = document.getElementById(elementId);
                if (element) element.textContent = text;
            }

            function clearBatch() {
                if (batchItems.length === 0) return;
                if (confirm(`Clear all ${batchItems.length} items?`)) {
                    batchItems = [];
                    updateBatchUI();
                    saveBatchToStorage();
                    showNotification('info', 'Batch cleared');

                    // Re-render search results to clear "Booked" status in real-time
                    if (typeof lastSearchResults !== 'undefined' && lastSearchResults.length > 0) {
                        displaySearchResults(lastSearchResults);
                    }
                }
            }

            // ==================== MANUAL ENTRY ====================
            function addManualItem() {
                const itemName = document.getElementById('itemName')?.value.trim();
                if (!itemName) {
                    showNotification('warning', 'Please enter item name');
                    return;
                }

                const added = addToBatch({
                    id: Date.now(),
                    name: itemName,
                    serial_number: document.getElementById('serialNumber')?.value.trim() || `MANUAL-${Date.now()}`,
                    quantity: parseInt(document.getElementById('quantity')?.value) || 1,
                    status: document.getElementById('status')?.value || 'available',
                    stock_location: document.getElementById('location')?.value.trim() || 'Manual Entry'
                });
                if (added) {
                    clearManualForm();
                }
            }

            // ==================== TECHNICIAN AUTHENTICATION ====================
            function authenticateTechnician(event) {
                if (event) {
                    event.preventDefault();
                    event.stopPropagation();
                }

                const select = document.getElementById('technicianSelect');
                const password = document.getElementById('technicianPassword').value;
                const technicianId = select.value;

                if (!technicianId) {
                    showNotification('warning', 'Please select a technician');
                    return false;
                }
                if (!password) {
                    showNotification('warning', 'Please enter technician password');
                    return false;
                }

                const authBtn = document.getElementById('authenticateBtn');
                const originalText = authBtn.innerHTML;
                authBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Verifying...';
                authBtn.disabled = true;

                $.ajax({
                    url: 'api/verify_technician.php',
                    method: 'POST',
                    data: {
                        id: technicianId,
                        password: password
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            isTechnicianAuthenticated = true;
                            authenticatedTechnician = response.technician;
                            if (document.getElementById('submittedByFullName')) {
                                document.getElementById('submittedByFullName').value = authenticatedTechnician.full_name;
                            }
                            const displayName = `${authenticatedTechnician.full_name} (ID: ${authenticatedTechnician.id})`;

                            // Sync personnel summary card
                            if (typeof syncPersonnelSummary === 'function') {
                                syncPersonnelSummary();
                            }

                            document.getElementById('authStatus').innerHTML = `<div class="alert alert-success">✓ Authenticated as ${displayName}</div>`;

                            document.getElementById('authenticationSection').style.display = 'none';
                            document.getElementById('batchFormSection').classList.remove('d-none');
                            document.getElementById('submitBatchBtn').disabled = false;
                            document.getElementById('printPreviewBtn').disabled = false;

                            showNotification('success', `Technician ${displayName} authenticated`);
                            initializeStockMovement();
                        } else {
                            showNotification('error', response.message || 'Authentication failed');
                            document.getElementById('authStatus').innerHTML = `<div class="alert alert-danger">${response.message || 'Invalid credentials'}</div>`;
                        }
                    },
                    error: function() {
                        showNotification('error', 'Error verifying technician');
                        document.getElementById('authStatus').innerHTML = `<div class="alert alert-danger">Network error. Please try again.</div>`;
                    },
                    complete: function() {
                        authBtn.innerHTML = originalText;
                        authBtn.disabled = false;
                    }
                });
                return false;
            }

            function resetTechnicianAuthentication() {
                isTechnicianAuthenticated = false;
                authenticatedTechnician = null;
                const select = document.getElementById('technicianSelect');
                if (select) {
                    if (isUserTechnician) {
                        select.value = loggedInUserId;
                    } else {
                        select.value = '';
                    }
                }
                const password = document.getElementById('technicianPassword');
                if (password) password.value = '';
                const authStatus = document.getElementById('authStatus');
                if (authStatus) authStatus.innerHTML = '';
                document.getElementById('authenticationSection').style.display = 'block';
                document.getElementById('batchFormSection').classList.add('d-none');
                const submitBtn = document.getElementById('submitBatchBtn');
                const printBtn = document.getElementById('printPreviewBtn');
                if (submitBtn) submitBtn.disabled = true;
                if (printBtn) printBtn.disabled = true;
            }

            // ==================== STOCK MOVEMENT ====================
            function initializeStockMovement() {
                const movementTypes = {
                    stockToVenueRoom: document.getElementById('movementStockToVenueRoom'),
                    venueRoomToStock: document.getElementById('movementVenueRoomToStock'),
                    stockToStock: document.getElementById('movementStockToStock'),
                    stockToVenueTransport: document.getElementById('movementStockToVenueTransport')
                };

                const sourceSection = document.getElementById('sourceSection');
                const destinationSection = document.getElementById('destinationSection');
                const transportSection = document.getElementById('transportSection');
                const movementSummary = document.getElementById('movementSummaryText');
                const movementConfirm = document.getElementById('movementTypeConfirmation');

                let stockLocations = ['Ndera Warehouse', 'KCC Stock', 'BK Arena'];
                let venues = ['Kigali Convention Centre', 'BK Arena', 'Marriot Hotel', 'Serena Hotel'];
                let venueRooms = {
                    'Kigali Convention Centre': ['Auditorium', 'MH1', 'MH2', 'MH3', 'MH4', 'MH5', 'AD1', 'AD2', 'AD3', 'AD4', 'AD5', 'AD6', 'AD7', 'AD8', 'AD9', 'AD10', 'AD11', 'AD12', 'No-MansLand', 'Meeting Room 2', 'VIP Lounge'],
                    'BK Arena': ['Court', 'CIP Lounge', 'CIP Lobby', 'Backstage', 'Press Conference Room', 'Press Conference Reception', 'Accreditation Room', ],
                    'Marriot Hotel': ['Grand Ballroom', 'Conference Room A', 'Conference Room B'],
                    'Serena Hotel': ['Conference Hall', 'Tent', 'Executive Room']
                };

                function getSelectedText(selectId) {
                    const el = document.getElementById(selectId);
                    return el?.options[el.selectedIndex]?.text || '';
                }

                function updateMovementFields() {
                    if (!sourceSection || !destinationSection) return;
                    sourceSection.innerHTML = '';
                    destinationSection.innerHTML = '';

                    let sourceHtml = '',
                        destHtml = '',
                        transportRequired = false;

                    if (movementTypes.stockToVenueRoom?.checked) {
                        sourceHtml = `<div class="col-md-12"><label class="form-label fw-bold">Source *</label><select id="sourceStock" class="form-select" required><option value="">-- Select Stock Location --</option>${stockLocations.map(loc => `<option value="${loc}">${loc}</option>`).join('')}</select></div>`;
                        destHtml = `<div class="col-md-12"><label class="form-label fw-bold">Destination *</label><select id="destinationVenue" class="form-select" required><option value="">-- Select Venue --</option>${venues.map(ven => `<option value="${ven}">${ven}</option>`).join('')}</select><div class="mt-2"><select id="destinationRoom" class="form-select" disabled><option value="">-- First select a venue --</option></select></div></div>`;
                        transportRequired = false;
                    } else if (movementTypes.venueRoomToStock?.checked) {
                        sourceHtml = `<div class="col-md-12"><label class="form-label fw-bold">Source *</label><select id="sourceVenue" class="form-select" required><option value="">-- Select Venue --</option>${venues.map(ven => `<option value="${ven}">${ven}</option>`).join('')}</select><div class="mt-2"><select id="sourceRoom" class="form-select" disabled><option value="">-- First select a venue --</option></select></div></div>`;
                        destHtml = `<div class="col-md-12"><label class="form-label fw-bold">Destination *</label><select id="destinationStock" class="form-select" required><option value="">-- Select Stock Location --</option>${stockLocations.map(loc => `<option value="${loc}">${loc}</option>`).join('')}</select></div>`;
                        transportRequired = false;
                    } else if (movementTypes.stockToStock?.checked) {
                        sourceHtml = `<div class="col-md-12"><label class="form-label fw-bold">Source *</label><select id="sourceStock" class="form-select" required><option value="">-- Select Source Stock --</option>${stockLocations.map(loc => `<option value="${loc}">${loc}</option>`).join('')}</select></div>`;
                        destHtml = `<div class="col-md-12"><label class="form-label fw-bold">Destination *</label><select id="destinationStock" class="form-select" required><option value="">-- Select Destination Stock --</option>${stockLocations.map(loc => `<option value="${loc}">${loc}</option>`).join('')}</select></div>`;
                        transportRequired = true;
                    } else if (movementTypes.stockToVenueTransport?.checked) {
                        sourceHtml = `<div class="col-md-12"><label class="form-label fw-bold">Source *</label><select id="sourceStock" class="form-select" required><option value="">-- Select Stock Location --</option>${stockLocations.map(loc => `<option value="${loc}">${loc}</option>`).join('')}</select></div>`;
                        destHtml = `<div class="col-md-12"><label class="form-label fw-bold">Destination *</label><select id="destinationVenue" class="form-select" required><option value="">-- Select Venue --</option>${venues.map(ven => `<option value="${ven}">${ven}</option>`).join('')}</select></div>`;
                        transportRequired = true;
                    }

                    sourceSection.innerHTML = sourceHtml;
                    destinationSection.innerHTML = destHtml;
                    if (transportSection) transportSection.style.display = transportRequired ? 'block' : 'none';

                    if (movementTypes.stockToVenueRoom?.checked) {
                        const venueSelect = document.getElementById('destinationVenue');
                        const roomSelect = document.getElementById('destinationRoom');
                        if (venueSelect && roomSelect) {
                            venueSelect.addEventListener('change', function() {
                                roomSelect.innerHTML = '<option value="">-- Select Room --</option>';
                                if (this.value && venueRooms[this.value]) {
                                    venueRooms[this.value].forEach(room => {
                                        const option = document.createElement('option');
                                        option.value = room;
                                        option.textContent = room;
                                        roomSelect.appendChild(option);
                                    });
                                    roomSelect.disabled = false;
                                } else {
                                    roomSelect.disabled = true;
                                }
                                updateSummary();
                            });
                            roomSelect.addEventListener('change', updateSummary);
                        }
                    }

                    if (movementTypes.venueRoomToStock?.checked) {
                        const venueSelect = document.getElementById('sourceVenue');
                        const roomSelect = document.getElementById('sourceRoom');
                        if (venueSelect && roomSelect) {
                            venueSelect.addEventListener('change', function() {
                                roomSelect.innerHTML = '<option value="">-- Select Room --</option>';
                                if (this.value && venueRooms[this.value]) {
                                    venueRooms[this.value].forEach(room => {
                                        const option = document.createElement('option');
                                        option.value = room;
                                        option.textContent = room;
                                        roomSelect.appendChild(option);
                                    });
                                    roomSelect.disabled = false;
                                } else {
                                    roomSelect.disabled = true;
                                }
                                updateSummary();
                            });
                            roomSelect.addEventListener('change', updateSummary);
                        }
                    }

                    ['sourceStock', 'sourceVenue', 'destinationStock', 'destinationVenue'].forEach(id => {
                        const el = document.getElementById(id);
                        if (el) el.addEventListener('change', updateSummary);
                    });
                    updateSummary();
                }

                function updateSummary() {
                    let typeText = '';
                    let locationDetail = '';

                    if (movementTypes.stockToVenueRoom?.checked) {
                        typeText = 'Stock → Venue (Room)';
                        locationDetail = `${getSelectedText('sourceStock') || '...'} → ${getSelectedText('destinationVenue') || '...'} (${getSelectedText('destinationRoom') || '...'})`;
                    } else if (movementTypes.venueRoomToStock?.checked) {
                        typeText = 'Venue (Room) → Stock';
                        locationDetail = `${getSelectedText('sourceVenue') || '...'} (${getSelectedText('sourceRoom') || '...'}) → ${getSelectedText('destinationStock') || '...'}`;
                    } else if (movementTypes.stockToStock?.checked) {
                        typeText = 'Stock → Stock (Transfer)';
                        locationDetail = `${getSelectedText('sourceStock') || '...'} → ${getSelectedText('destinationStock') || '...'}`;
                    } else if (movementTypes.stockToVenueTransport?.checked) {
                        typeText = 'Stock → Venue (Transport)';
                        locationDetail = `${getSelectedText('sourceStock') || '...'} → ${getSelectedText('destinationVenue') || '...'}`;
                    }

                    if (movementSummary) movementSummary.innerHTML = `<i class="fas fa-info-circle me-2"></i><strong>${typeText}:</strong> ${locationDetail}`;
                    if (movementConfirm) movementConfirm.textContent = typeText;

                    const locationConfirm = document.getElementById('movementLocationConfirmation');
                    if (locationConfirm) locationConfirm.textContent = locationDetail;
                }

                Object.values(movementTypes).forEach(radio => {
                    if (radio) radio.addEventListener('change', () => updateMovementFields());
                });

                if (movementTypes.stockToVenueRoom) {
                    movementTypes.stockToVenueRoom.checked = true;
                    updateMovementFields();
                }

                if (typeof syncPersonnelSummary === 'function') {
                    syncPersonnelSummary();
                }
            }

            // ==================== MODAL FUNCTIONS ====================
            function openBatchModal() {
                if (batchItems.length === 0) {
                    showNotification('warning', 'No items to submit');
                    return;
                }
                resetTechnicianAuthentication();
                initializeStockMovement();
                if (typeof window.syncPersonnelSummary === 'function') {
                    window.syncPersonnelSummary();
                }
                const modalElement = document.getElementById('batchSubmitModal');
                if (modalElement) {
                    if (currentModal) currentModal.dispose();
                    currentModal = new bootstrap.Modal(modalElement);
                    currentModal.show();
                }
            }

            function submitBatch() {
                if (!document.getElementById('confirmBatchSubmit')?.checked) {
                    showNotification('warning', 'Please confirm the submission');
                    return;
                }
                if (!isTechnicianAuthenticated || !authenticatedTechnician) {
                    showNotification('error', 'Technician not authenticated');
                    return;
                }
                if (batchItems.length === 0) {
                    showNotification('warning', 'No items to submit');
                    return;
                }

                const movementType = document.querySelector('input[name="stockMovementType"]:checked')?.value;
                let sourceData = {},
                    destinationData = {};

                if (movementType === 'stock_to_venue_room') {
                    sourceData = {
                        type: 'stock',
                        id: document.getElementById('sourceStock')?.value || '',
                        name: document.getElementById('sourceStock')?.options[document.getElementById('sourceStock')?.selectedIndex]?.text || ''
                    };
                    destinationData = {
                        type: 'venue',
                        id: document.getElementById('destinationVenue')?.value || '',
                        name: document.getElementById('destinationVenue')?.options[document.getElementById('destinationVenue')?.selectedIndex]?.text || '',
                        room: document.getElementById('destinationRoom')?.value || ''
                    };
                } else if (movementType === 'venue_room_to_stock') {
                    sourceData = {
                        type: 'venue',
                        id: document.getElementById('sourceVenue')?.value || '',
                        name: document.getElementById('sourceVenue')?.options[document.getElementById('sourceVenue')?.selectedIndex]?.text || '',
                        room: document.getElementById('sourceRoom')?.value || ''
                    };
                    destinationData = {
                        type: 'stock',
                        id: document.getElementById('destinationStock')?.value || '',
                        name: document.getElementById('destinationStock')?.options[document.getElementById('destinationStock')?.selectedIndex]?.text || ''
                    };
                } else if (movementType === 'stock_to_stock') {
                    sourceData = {
                        type: 'stock',
                        id: document.getElementById('sourceStock')?.value || '',
                        name: document.getElementById('sourceStock')?.options[document.getElementById('sourceStock')?.selectedIndex]?.text || ''
                    };
                    destinationData = {
                        type: 'stock',
                        id: document.getElementById('destinationStock')?.value || '',
                        name: document.getElementById('destinationStock')?.options[document.getElementById('destinationStock')?.selectedIndex]?.text || ''
                    };
                } else if (movementType === 'stock_to_venue_transport') {
                    sourceData = {
                        type: 'stock',
                        id: document.getElementById('sourceStock')?.value || '',
                        name: document.getElementById('sourceStock')?.options[document.getElementById('sourceStock')?.selectedIndex]?.text || ''
                    };
                    destinationData = {
                        type: 'venue',
                        id: document.getElementById('destinationVenue')?.value || '',
                        name: document.getElementById('destinationVenue')?.options[document.getElementById('destinationVenue')?.selectedIndex]?.text || ''
                    };
                }

                if (!sourceData.id) {
                    showNotification('error', 'Please select a source location');
                    return;
                }
                if (!destinationData.id) {
                    showNotification('error', 'Please select a destination');
                    return;
                }
                if ((movementType === 'stock_to_venue_room' || movementType === 'venue_room_to_stock') && !destinationData.room && !sourceData.room) {
                    showNotification('error', 'Please select a room');
                    return;
                }
                if (movementType === 'stock_to_stock' || movementType === 'stock_to_venue_transport') {
                    const vehicleType = document.getElementById('transportVehicleType')?.value;
                    const vehicleNumber = document.getElementById('transportVehicleNumber')?.value;
                    const driver = document.getElementById('transportDriver')?.value;
                    if (!vehicleType || !vehicleNumber || !driver) {
                        showNotification('error', 'Please fill in all transport details');
                        return;
                    }
                }

                const batchData = {
                    items: batchItems,
                    technician_id: authenticatedTechnician.id,
                    movement_type: movementType,
                    source: sourceData,
                    destination: destinationData,
                    transport: {
                        vehicle_type: document.getElementById('transportVehicleType')?.value || null,
                        vehicle_number: document.getElementById('transportVehicleNumber')?.value || null,
                        driver: document.getElementById('transportDriver')?.options[document.getElementById('transportDriver').selectedIndex]?.text || null,
                        driver_id: document.getElementById('transportDriver')?.value || null,
                        transport_date: document.getElementById('transportDate')?.value || null
                    },
                    event_name: document.getElementById('eventName')?.value || null,
                    job_sheet: document.getElementById('jobSheet')?.value || null,
                    project_manager: document.getElementById('projectManager')?.value || null,
                    notes: document.getElementById('movementNotes')?.value || null,
                    // NEW FIELDS
                    stock_controller_id: document.getElementById('stockControllerSelect')?.value || null,
                    stock_controller_name: (function() {
                        const sel = document.getElementById('stockControllerSelect');
                        if (!sel || sel.selectedIndex === -1) return null;
                        const opt = sel.options[sel.selectedIndex];
                        return opt.getAttribute('data-fullname') || opt.text.split('(')[0].trim() || null;
                    })(),
                    stock_location: document.getElementById('stockLocationSelect')?.value || null
                };

                const submitBtn = document.getElementById('submitBatchBtn');
                const originalText = submitBtn.innerHTML;
                submitBtn.disabled = true;

                const proceedWithSubmission = (jobsheetPath = null) => {
                    if (jobsheetPath) {
                        batchData.jobsheet_file = jobsheetPath;
                    }
                    console.log('Submitting batch data:', JSON.stringify(batchData, null, 2));
                    $.ajax({
                        url: 'api/submit_batch.php',
                        method: 'POST',
                        data: JSON.stringify(batchData),
                        contentType: 'application/json',
                        dataType: 'json',
                        success: function(response) {
                            if (response.success) {
                                // Capture data for success modal
                                const submittedItems = [...batchItems];
                                const controllerName = batchData.stock_controller_name || 'Stock Controller';

                                showNotification('success', response.message || 'Batch submitted successfully!');

                                // Hide submission modal robustly
                                const batchModalEl = document.getElementById('batchSubmitModal');
                                if (batchModalEl) {
                                    const bModal = bootstrap.Modal.getInstance(batchModalEl) || new bootstrap.Modal(batchModalEl);
                                    bModal.hide();
                                }

                                // Clear batch
                                batchItems = [];
                                if (typeof updateBatchUI === 'function') updateBatchUI();
                                if (typeof saveBatchToStorage === 'function') saveBatchToStorage();

                                // Delay showing the success modal to avoid transition conflicts
                                setTimeout(() => {
                                    const successModalElement = document.getElementById('submissionSuccessModal');
                                    if (successModalElement) {
                                        document.getElementById('successStockControllerName').textContent = controllerName;
                                        document.getElementById('successItemCountBadge').textContent = `${submittedItems.length} Item(s)`;

                                        // Display Batch ID
                                        if (response.batch_number) {
                                            document.getElementById('successBatchIdDisplay').textContent = response.batch_number;
                                        } else {
                                            document.getElementById('successBatchIdDisplay').textContent = 'N/A';
                                        }

                                        // Setup countdown timer
                                        let timeRemaining = 900; // 15 minutes in seconds
                                        const countdownEl = document.getElementById('approvalCountdown');
                                        if (countdownEl) {
                                            countdownEl.textContent = '15:00';
                                            countdownEl.classList.remove('text-danger');
                                            countdownEl.classList.add('text-dark');
                                        }

                                        // Reset description element in case it was modified in a previous run
                                        const infoEl = document.getElementById('countdownInstructionText');
                                        if (infoEl) {
                                            infoEl.innerHTML = `Approvals are typically completed within 15 minutes. Contact <span id="timerStockControllerName" class="fw-bold text-primary">${controllerName}</span>.`;
                                        }

                                        if (window.approvalInterval) {
                                            clearInterval(window.approvalInterval);
                                        }

                                        window.approvalInterval = setInterval(() => {
                                            timeRemaining--;
                                            if (timeRemaining <= 0) {
                                                clearInterval(window.approvalInterval);
                                                window.approvalInterval = null;
                                                if (countdownEl) {
                                                    countdownEl.textContent = '00:00';
                                                    countdownEl.classList.remove('text-dark');
                                                    countdownEl.classList.add('text-danger');
                                                }
                                                if (infoEl) {
                                                    infoEl.innerHTML = `SLA Exceeded. Please contact <span class="fw-bold text-danger">${controllerName}</span> urgently for approval.`;
                                                }
                                            } else {
                                                const minutes = Math.floor(timeRemaining / 60);
                                                const seconds = timeRemaining % 60;
                                                if (countdownEl) {
                                                    countdownEl.textContent = `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
                                                }
                                            }
                                        }, 1000);

                                        // Clean up timer on modal close
                                        if (!successModalElement.dataset.timerListenerAdded) {
                                            successModalElement.addEventListener('hidden.bs.modal', () => {
                                                if (window.approvalInterval) {
                                                    clearInterval(window.approvalInterval);
                                                    window.approvalInterval = null;
                                                }
                                            });
                                            successModalElement.dataset.timerListenerAdded = 'true';
                                        }

                                        const itemsList = document.getElementById('successItemsList');
                                        itemsList.innerHTML = submittedItems.map(item => `
                                        <div class="list-group-item bg-transparent border-0 px-0 py-3 d-flex justify-content-between align-items-center border-bottom">
                                            <div class="d-flex align-items-center">
                                                <div class="bg-success bg-opacity-10 text-success rounded-circle p-2 me-3 d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                                    <i class="fas fa-box small"></i>
                                                </div>
                                                <div>
                                                    <div class="fw-bold text-dark" style="font-size: 0.9rem;">${item.name}</div>
                                                    <div class="text-muted small">SN: <code>${item.serial_number}</code></div>
                                                </div>
                                            </div>
                                            <span class="badge bg-white text-success border border-success-subtle rounded-pill">x${item.quantity}</span>
                                        </div>
                                    `).join('');

                                        const successModal = new bootstrap.Modal(successModalElement);
                                        successModal.show();
                                    }
                                }, 500);
                            } else {
                                showNotification('error', response.message || 'Submission failed');
                            }
                        },
                        error: function(xhr, status, error) {
                            console.log('XHR Status:', status);
                            console.log('XHR Response Text:', xhr.responseText);
                            console.log('XHR Status Code:', xhr.status);

                            let errorMsg = 'Error submitting batch: ';
                            try {
                                const response = JSON.parse(xhr.responseText);
                                errorMsg += response.message || response.error || xhr.statusText;
                            } catch (e) {
                                // If response is not JSON, show the raw response (truncated)
                                const rawResponse = xhr.responseText.substring(0, 200);
                                errorMsg += rawResponse || xhr.statusText;
                            }
                            showNotification('error', errorMsg);
                        },
                        complete: function() {
                            submitBtn.innerHTML = originalText;
                            submitBtn.disabled = false;
                        }
                    });
                };

                // Check if a jobsheet file was chosen for upload
                const fileInput = document.getElementById('jobSheetFile');
                if (fileInput && fileInput.files && fileInput.files.length > 0) {
                    submitBtn.innerHTML = '<i class="fas fa-cloud-upload-alt fa-spin me-1"></i> Uploading Jobsheet...';
                    const formData = new FormData();
                    formData.append('jobsheet_file', fileInput.files[0]);

                    $.ajax({
                        url: 'api/upload_jobsheet.php',
                        method: 'POST',
                        data: formData,
                        processData: false,
                        contentType: false,
                        dataType: 'json',
                        success: function(uploadRes) {
                            if (uploadRes.success && uploadRes.file_path) {
                                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Submitting...';
                                proceedWithSubmission(uploadRes.file_path);
                            } else {
                                showNotification('error', 'Jobsheet upload failed: ' + (uploadRes.message || 'Unknown error'));
                                submitBtn.innerHTML = originalText;
                                submitBtn.disabled = false;
                            }
                        },
                        error: function(xhr, status, error) {
                            showNotification('error', 'Failed to upload jobsheet: ' + error);
                            submitBtn.innerHTML = originalText;
                            submitBtn.disabled = false;
                        }
                    });
                } else {
                    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Submitting...';
                    proceedWithSubmission(null);
                }
            }

            function printPreview() {
                const printWindow = window.open('', '_blank');
                printWindow.document.write(`
                <!DOCTYPE html>
                <html>
                <head><title>Batch Preview</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
                <style>body{padding:20px}@media print{.no-print{display:none}body{padding:0}}</style></head>
                <body><div class="container">
                    <h2>Equipment Movement Batch</h2><hr>
                    <div class="row"><div class="col-md-6"><strong>Technician:</strong> ${escapeHtml(authenticatedTechnician?.full_name || 'N/A')}<br><strong>Movement Type:</strong> ${escapeHtml(document.querySelector('input[name="stockMovementType"]:checked')?.parentElement?.querySelector('label')?.innerText || 'N/A')}</div>
                    <div class="col-md-6"><strong>Date:</strong> ${new Date().toLocaleString()}<br><strong>Submitted by:</strong> ${escapeHtml(document.getElementById('submittedByFullName')?.value || 'N/A')}</div></div><hr>
                    <h5>Items List</h5>
                    <table class="table table-bordered"><thead><tr><th>Item Name</th><th>Serial Number</th><th>Quantity</th><th>Status</th><th>Location</th></tr></thead>
                    <tbody>${batchItems.map(item => `<tr><td>${escapeHtml(item.name)}</td><td><code>${escapeHtml(item.serial_number)}</code></td><td class="text-center">${item.quantity || 1}</td><td>${escapeHtml(item.status)}</td><td>${escapeHtml(item.stock_location)}</td></tr>`).join('')}</tbody></table>
                    <p><small class="text-muted">Generated on ${new Date().toLocaleString()}</small></p>
                </div>
                <div class="text-center no-print mt-4"><button class="btn btn-primary" onclick="window.print()">Print</button><button class="btn btn-secondary" onclick="window.close()">Close</button></div>
                </body></html>
            `);
                printWindow.document.close();
            }

            function setupModalEvents() {
                const modal = document.getElementById('batchSubmitModal');
                if (modal) {
                    const newModal = modal.cloneNode(true);
                    modal.parentNode.replaceChild(newModal, modal);
                    newModal.addEventListener('show.bs.modal', () => {
                        resetTechnicianAuthentication();
                        initializeStockMovement();
                    });
                    newModal.addEventListener('hidden.bs.modal', () => {
                        resetTechnicianAuthentication();
                    });
                }
                document.getElementById('submitBatchBtn')?.addEventListener('click', submitBatch);
                document.getElementById('printPreviewBtn')?.addEventListener('click', printPreview);
                document.getElementById('openBatchModalBtn')?.addEventListener('click', openBatchModal);
                document.getElementById('submitBatchModalBtn')?.addEventListener('click', openBatchModal);
            }

            function togglePasswordVisibility() {
                const password = document.getElementById('technicianPassword');
                if (password) password.type = password.type === 'password' ? 'text' : 'password';
            }

            // Logout Confirmation
            window.confirmLogout = function(event) {
                if (event) event.preventDefault();

                let toastContainer = document.getElementById('centered-toast-container');
                if (!toastContainer) {
                    toastContainer = document.createElement('div');
                    toastContainer.id = 'centered-toast-container';
                    toastContainer.className = 'toast-container position-fixed top-50 start-50 translate-middle p-3';
                    toastContainer.style.zIndex = '1055';
                    document.body.appendChild(toastContainer);
                }

                const toastId = 'logout-toast-' + Date.now();
                const toastHtml = `
                <div id="${toastId}" class="toast align-items-center text-white bg-dark border-0 shadow-lg" role="alert" aria-live="assertive" aria-atomic="true" style="min-width: 320px;" data-bs-autohide="false">
                    <div class="p-4">
                        <div class="d-flex align-items-center mb-3">
                            <div class="bg-primary rounded-circle p-2 me-3">
                                <i class="fas fa-sign-out-alt text-white"></i>
                            </div>
                            <h5 class="mb-0 fs-5">Logout Confirmation</h5>
                        </div>
                        <p class="mb-4 opacity-75">Are you sure you want to end your session?</p>
                        <div class="d-flex gap-2">
                            <a href="logout.php" class="btn btn-primary flex-grow-1 py-2 rounded-3">
                                <i class="fas fa-check me-1"></i> Yes, Logout
                            </a>
                            <button type="button" class="btn btn-outline-light flex-grow-1 py-2 rounded-3" data-bs-dismiss="toast">
                                Cancel
                            </button>
                        </div>
                    </div>
                </div>
            `;

                toastContainer.insertAdjacentHTML('beforeend', toastHtml);
                const toastElement = document.getElementById(toastId);
                const toast = new bootstrap.Toast(toastElement);
                toast.show();
            };

            // Add animation styles
            const style = document.createElement('style');
            style.textContent = `
            @keyframes slideInRight { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }
            @keyframes slideOutRight { from { transform: translateX(0); opacity: 1; } to { transform: translateX(100%); opacity: 0; } }
            .flash-highlight { animation: flashHighlight 0.5s ease; }
            @keyframes flashHighlight { 0% { background-color: #fff3cd; } 100% { background-color: transparent; } }
        `;
            document.head.appendChild(style);

            window.addEventListener('beforeunload', () => {
                try {
                    if (html5QrCode && isScanning) html5QrCode.stop();
                } catch (e) {}
            });

            // Expose global functions
            window.changeGroupBreakdownPage = function(direction) {
                groupBreakdownPage += direction;
                updateBatchUI();
            };
            window.authenticateTechnician = authenticateTechnician;
            window.addManualItem = addManualItem;
            window.addSearchedItemToBatch = addSearchedItemToBatch;
            window.searchItems = searchItems;
            window.searchItemsDebounced = searchItemsDebounced;
            window.clearBatch = clearBatch;
            window.removeFromBatch = removeFromBatch;
            window.updateItemStatus = updateItemStatus;
            window.togglePasswordVisibility = togglePasswordVisibility;
            window.confirmLogout = confirmLogout;
            window.startScanner = startScanner;
            window.stopScanner = stopScanner;
            window.syncPersonnelSummary = function() {
                console.log('Syncing personnel summary...');
                const summaryTech = document.getElementById('summaryTechnicianName');
                const summaryReq = document.getElementById('summaryRequestedBy');

                // Sync Technician
                if (typeof isTechnicianAuthenticated !== 'undefined' && isTechnicianAuthenticated && typeof authenticatedTechnician !== 'undefined') {
                    if (summaryTech) summaryTech.textContent = authenticatedTechnician.full_name;
                    if (summaryReq) summaryReq.textContent = authenticatedTechnician.full_name;
                } else if (typeof isUserTechnician !== 'undefined' && isUserTechnician) {
                    // Pre-show for logged in technician
                    if (summaryTech) summaryTech.textContent = '<?php echo addslashes($logged_in_full_name); ?>';
                    if (summaryReq) summaryReq.textContent = '<?php echo addslashes($logged_in_full_name); ?>';
                }


                // Sync Stock Controller from dropdown
                const scSelect = document.getElementById('stockControllerSelect');
                const summarySC = document.getElementById('summaryStockControllerName');
                if (scSelect && summarySC) {
                    if (scSelect.value === "") {
                        summarySC.innerHTML = '<span class="text-muted italic">Not selected</span>';
                    } else {
                        const selectedOption = scSelect.options[scSelect.selectedIndex];
                        let fullName = selectedOption.getAttribute('data-fullname');
                        if (!fullName) {
                            fullName = selectedOption.text.split('(')[0].trim();
                        }
                        summarySC.textContent = fullName;
                        console.log('Syncing SC UI:', fullName);
                    }
                }
            };

             window.autoSelectLocation = function() {
                const scSelect = document.getElementById('stockControllerSelect');
                const locSelect = document.getElementById('stockLocationSelect');

                if (!scSelect || !locSelect || scSelect.value === "") return;

                const selectedOption = scSelect.options[scSelect.selectedIndex];
                const department = (selectedOption.getAttribute('data-department') || "").toLowerCase().trim();
                const fullName = (selectedOption.getAttribute('data-fullname') || selectedOption.text || "").toLowerCase();
                const username = (selectedOption.getAttribute('data-username') || "").toLowerCase();

                console.log('autoSelectLocation: Selected Controller Dept =', department, 'Name =', fullName, 'Username =', username);

                // 1. Direct Controller Name / Username mapping (highest priority for precise assignment)
                if (fullName.includes('schadrack') || username.includes('schadrack')) {
                    locSelect.value = "Ndera Warehouse";
                } else if (fullName.includes('prince') || username.includes('prince')) {
                    locSelect.value = "KCC Stock";
                } else if (fullName.includes('irene') || username.includes('irene') || fullName.includes('mudacumura') || username.includes('irenem')) {
                    locSelect.value = "BK Arena Stock";
                }
                // 2. Department fallback mapping
                else if (department) {
                    if (department.includes('ndera') || department.includes('warehouse')) {
                        locSelect.value = "Ndera Warehouse";
                    } else if (department.includes('bka') || department.includes('bk arena') || department.includes('arena') || department.includes('bk')) {
                        locSelect.value = "BK Arena Stock";
                    } else if (department.includes('kcc')) {
                        locSelect.value = "KCC Stock";
                    } else {
                        // Intelligent fallback: check if any option value/text matches or contains the department
                        for (let i = 0; i < locSelect.options.length; i++) {
                            const optText = locSelect.options[i].text.toLowerCase();
                            const optVal = locSelect.options[i].value;
                            if (optVal && (optText.includes(department) || department.includes(optText) || optVal.toLowerCase().includes(department))) {
                                locSelect.value = optVal;
                                break;
                            }
                        }
                    }
                }
            };

            document.addEventListener('DOMContentLoaded', function() {
                loadBatchFromStorage();
                setupModalEvents();
                updateBatchUI();
                document.getElementById('scan-tab')?.addEventListener('shown.bs.tab', () => setTimeout(() => startScanner(), 500));
                document.getElementById('manual-tab')?.addEventListener('shown.bs.tab', () => stopScanner('manual-tab-shown'));
                document.getElementById('search-tab')?.addEventListener('shown.bs.tab', () => stopScanner('search-tab-shown'));

                // Auto-fill vehicle info on driver selection
                document.getElementById('transportDriver')?.addEventListener('change', function() {
                    const selectedOption = this.options[this.selectedIndex];
                    if (selectedOption && selectedOption.value) {
                        const vehicleType = selectedOption.getAttribute('data-vehicle-type');
                        const vehicleNumber = selectedOption.getAttribute('data-vehicle-number');
                        if (vehicleType) {
                            const typeSelect = document.getElementById('transportVehicleType');
                            // Check if option exists, otherwise create it or just set value if text
                            let optionExists = Array.from(typeSelect.options).some(opt => opt.value === vehicleType);
                            if (!optionExists && vehicleType) {
                                typeSelect.add(new Option(vehicleType, vehicleType));
                            }
                            typeSelect.value = vehicleType;
                        }
                        if (vehicleNumber) {
                            document.getElementById('transportVehicleNumber').value = vehicleNumber;
                        }
                    } else {
                        document.getElementById('transportVehicleType').value = '';
                        document.getElementById('transportVehicleNumber').value = '';
                    }
                });

                // Sync personnel summary on controller change (using event delegation for better reliability)
                document.addEventListener('change', function(e) {
                    if (e.target && e.target.id === 'stockControllerSelect') {
                        window.syncPersonnelSummary();
                        window.autoSelectLocation();
                    }
                });

                // Initialize on load
                window.syncPersonnelSummary();
                window.autoSelectLocation();
            });
        </script>
</body>

</html>