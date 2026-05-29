<?php

// dashboard_full.php - COMPLETE FIXED VERSION

// ========== SESSION AND AUTHENTICATION ==========
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (file_exists('includes/bootstrap.php')) {
    require_once 'includes/bootstrap.php';
} else {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    require_once 'includes/database_fix.php';
    require_once 'includes/functions.php';
}

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    $_SESSION['redirect_to'] = $_SERVER['REQUEST_URI'];
    header('Location: login.php');
    exit();
}

error_log("Dashboard accessed by: " . ($_SESSION['username'] ?? 'Unknown') . " (ID: " . ($_SESSION['user_id'] ?? 'None') . ")");

// Get user's role
require_once 'includes/functions.php';
$user_role = getUserRole();

// RESTRICT ACCESS - Only admins, managers, stock controllers, and technicians can access the main dashboard
$allowed_dashboard_roles = ['admin', 'manager', 'stock_controller', 'stock_manager', 'tech_lead', 'technician'];
if (!in_array($user_role, $allowed_dashboard_roles)) {
    if ($user_role === 'driver') {
        header('Location: driver_batches.php');
        exit();
    } else {
        header('Location: login.php');
        exit();
    }
}

$current_page = basename(__FILE__);
$pageTitle = "Dashboard - aBility";
$showBreadcrumb = true;
$breadcrumbItems = ['Dashboard' => ''];

// ========== DATABASE CONNECTION ==========
require_once 'includes/database_fix.php';
require_once 'includes/functions.php';

try {

    $db = new DatabaseFix();
    $conn = $db->getConnection();
} catch (Exception $e) {
    die("Database connection failed: " . $e->getMessage());
}

// ========== HELPER FUNCTIONS ==========
function getAccessoriesList($item_id)
{
    global $conn;
    $accessories = [];
    try {
        $stmt = $conn->prepare("
            SELECT a.name
            FROM accessories a
            INNER JOIN item_accessories ia ON a.id = ia.accessory_id
            WHERE ia.item_id = ?
        ");
        if ($stmt) {
            $stmt->bind_param("i", $item_id);
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $accessories[] = $row['name'];
            }
            $stmt->close();
        }
    } catch (Exception $e) {
        error_log("Error getting accessories list: " . $e->getMessage());
    }
    return !empty($accessories) ? implode(', ', $accessories) : 'None';
}

// ========== DATA FETCHING ==========
$allItems = [];
try {
    $itemsResult = $conn->query("
        SELECT DISTINCT item_name
        FROM items
        WHERE status NOT IN ('disposed', 'lost')
        ORDER BY item_name
        LIMIT 1000
    ");
    if ($itemsResult) {
        $allItems = $itemsResult->fetch_all(MYSQLI_ASSOC);
    }
} catch (Exception $e) {
    error_log("Error getting items for datalist: " . $e->getMessage());
}

$specific_item_count = 0;
$specific_item_name = "Mini Converter - Optical Fiber 12G";
try {
    $specificStmt = $conn->prepare("
        SELECT SUM(quantity) as total_quantity
        FROM items
        WHERE item_name LIKE ?
        AND status != 'disposed'
        AND status != 'lost'
    ");
    if ($specificStmt) {
        $searchTerm = "%" . $specific_item_name . "%";
        $specificStmt->bind_param("s", $searchTerm);
        $specificStmt->execute();
        $specificResult = $specificStmt->get_result();
        if ($row = $specificResult->fetch_assoc()) {
            $specific_item_count = $row['total_quantity'] ?? 0;
        }
        $specificStmt->close();
    }
} catch (Exception $e) {
    error_log("Error getting specific item count: " . $e->getMessage());
}

$stats = getDashboardStats($conn);

$category_stats = [];
try {
    $result = $conn->query("
        SELECT
            COALESCE(category, 'Uncategorized') as category_name,
            COUNT(*) as item_count,
            SUM(quantity) as total_quantity,
            ROUND((COUNT(*) * 100.0 / (SELECT COUNT(*) FROM items)), 2) as percentage
        FROM items
        GROUP BY category
        ORDER BY item_count DESC
    ");
    if ($result) {
        $category_stats = $result->fetch_all(MYSQLI_ASSOC);
    }
} catch (Exception $e) {
    error_log("Error getting category stats: " . $e->getMessage());
}

$recentItems = [];
try {
    $recentItems = getRecentItems($conn, 500);
    if (empty($recentItems)) {
        error_log("No recent items found - table might be empty or error occurred");
    }
} catch (Exception $e) {
    error_log("Error in getRecentItems: " . $e->getMessage());
    $recentItems = [];
}

$accessories = [];
try {
    $accResult = $conn->query("
        SELECT id, name, description, total_quantity, available_quantity, is_active
        FROM accessories
        WHERE is_active = 1
        ORDER BY name
    ");
    if ($accResult) {
        $accessories = $accResult->fetch_all(MYSQLI_ASSOC);
    }
} catch (Exception $e) {
    error_log("Error fetching accessories: " . $e->getMessage());
}

require_once 'views/partials/header.php';
require_once 'assets/css/chart.css';
?>

<style>
    @import url('https://fonts.googleapis.com/css2?family=Marvel:ital,wght@0,400;0,700;1,400;1,700&display=swap');

    * {
        font-family: "Marvel", sans-serif;
    }

    .dataTables_wrapper .dataTables_paginate .paginate_button {
        padding: 0;
        margin-left: 2px;
        border-radius: 3px;
        border: 1px solid #ddd;
        background-color: #f8f9fa;
        color: #333;
    }

    .active>.page-link,
    .page-link.active {
        background-color: #2c6792 !important;
        border-color: #234C6A !important;
        color: white !important;
    }

    .quick-view-btn {
        transition: all 0.3s ease;
        background: linear-gradient(135deg, #233643 0%, #2c4760 100%);
        border: none;
        color: white;
        padding: 0.25rem 0.75rem;
    }

    .quick-view-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(35, 54, 67, 0.3);
        background: linear-gradient(135deg, #2c4760 0%, #233643 100%);
    }

    .quick-view-btn:active {
        transform: translateY(0);
    }

    #digitalTime {
        letter-spacing: 1px;
        text-shadow: 0 1px 2px rgba(30, 62, 86, 0.77);
    }

    .bg-primary.rounded-circle {
        background-color: rgba(35, 54, 67, 1) !important;
        box-shadow: 0 2px 4px rgba(35, 54, 67, 0.3);
    }

    /* DataTables Pagination Fix - Premium Look */
    .dataTables_wrapper .dataTables_paginate {
        padding-top: 1.5rem;
        display: flex;
        justify-content: center;
    }

    .dataTables_wrapper .dataTables_paginate .paginate_button {
        border-radius: 6px !important;
        border: 1px solid #dee2e6 !important;
        background: white !important;
        color: #353f48ff !important;
        font-weight: 500 !important;
        transition: all 0.2s ease !important;
        cursor: pointer !important;
        text-decoration: none !important;
        font-size: 0.8rem !important;
    }

    .dataTables_wrapper .dataTables_paginate .paginate_button:hover {
        background: #f8f9fa !important;
        border-color: #2c6792 !important;
        color: #2c6792 !important;
        transform: translateY(-1px);
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
    }

    .dataTables_wrapper .dataTables_paginate .paginate_button.current,
    .dataTables_wrapper .dataTables_paginate .paginate_button.current:hover {
        background: linear-gradient(135deg, #1a2e3f 0%, #2c6792 100%) !important;
        color: white !important;
        border-color: #1a2e3f !important;
        box-shadow: 0 4px 10px rgba(44, 103, 146, 0.3);
    }

    .dataTables_wrapper .dataTables_paginate .paginate_button.disabled,
    .dataTables_wrapper .dataTables_paginate .paginate_button.disabled:hover {
        background: #f8f9fa !important;
        color: #adb5bd !important;
        border-color: #e9ecef !important;
        cursor: not-allowed !important;
        transform: none !important;
        box-shadow: none !important;
    }

    .dataTables_wrapper .dataTables_info {
        padding-top: 1rem;
        font-size: 0.8rem;
        color: #6c757d;
        text-align: center;
    }

    @media (max-width: 768px) {
        .border-end {
            border-right: none !important;
            border-bottom: 1px solid #dee2e6;
            margin-bottom: 1rem;
            padding-bottom: 1rem;
        }

        .pe-3 {
            padding-right: 0 !important;
        }

        .ps-3 {
            padding-left: 0 !important;
        }

        #digitalTime {
            font-size: 1.5rem !important;
        }

        #quickActionsModal .row.g-0>.col-md-8,
        #quickActionsModal .row.g-0>.col-md-4 {
            border: none !important;
        }
    }

    /* ===== MODAL FIX: Remove conflicting rules that caused backdrop/scroll lock bugs ===== */
    .modal-backdrop {
        z-index: 1040 !important;
    }

    .modal {
        z-index: 1050 !important;
    }

    /* Let Bootstrap manage body scroll — do NOT override with overflow:auto */
    /* Stacked modals */
    .modal+.modal-backdrop {
        z-index: 1055;
    }

    .modal:nth-of-type(even) {
        z-index: 1060 !important;
    }

    .dataTables_wrapper {
        width: 100%;
        overflow: hidden;
    }

    .dataTables_length,
    .dataTables_filter {
        margin-bottom: 1.5rem;
        padding: 10px 10px 0 10px;
    }

    .dataTables_length label,
    .dataTables_filter label {
        display: flex;
        align-items: center;
        gap: 8px;
        color: #64748b;
        font-weight: 600;
        font-size: 0.9rem;
        margin-bottom: 0;
    }

    .dataTables_filter input {
        border-radius: 20px !important;
        padding: 0.4rem 1rem !important;
        border: 1px solid #dfe1e5 !important;
        width: 250px !important;
    }

    .dataTables_length select {
        padding: 0.375rem 2rem 0.375rem 0.75rem !important;
        border-radius: 8px !important;
        border: 1px solid #dfe1e5 !important;
    }

    .dataTables_filter input:focus {
        border-color: #347b60 !important;
        box-shadow: 0 0 0 0.25rem rgba(52, 123, 96, 0.1) !important;
    }

    .dataTables_wrapper .row {
        align-items: center;
    }

    .table-responsive {
        border-radius: 12px;
        overflow: hidden;
        border: 1px solid #f1f3f4;
    }

    #recentItemsTable {
        border-collapse: separate !important;
        border-spacing: 0;
        margin-top: 0 !important;
        margin-bottom: 0 !important;
    }

    #recentItemsTable thead th {
        background-color: #f8fafc;
        color: #475569;
        font-weight: 700;
        text-transform: uppercase;
        font-size: 0.75rem;
        letter-spacing: 0.025em;
        padding: 1rem;
        border-bottom: 2px solid #e2e8f0;
    }

    #recentItemsTable tbody td {
        padding: 1rem;
        vertical-align: middle;
        border-bottom: 1px solid #f1f5f9;
    }

    .dataTables_info {
        font-family: inherit !important;
        font-weight: 500 !important;
    }

    #quickActionsModal .modal-content {
        border-radius: 0.5rem;
    }

    #quickActionsModal .modal-body {
        max-height: 70vh;
        overflow-y: auto;
    }

    /* Status Badges */
    .status-badge {
        display: inline-flex;
        align-items: center;
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 0.85rem;
        font-weight: 500;
    }

    .status-available {
        background-color: rgba(75, 192, 192, 0.15);
        color: #2e8b57;
        border: 1px solid rgba(75, 192, 192, 0.3);
    }

    .status-in_use {
        background-color: rgba(54, 162, 235, 0.15);
        color: #1e6b8a;
        border: 1px solid rgba(54, 162, 235, 0.3);
    }

    .status-maintenance {
        background-color: rgba(255, 206, 86, 0.15);
        color: #b8860b;
        border: 1px solid rgba(255, 206, 86, 0.3);
    }

    .status-reserved {
        background-color: rgba(153, 102, 255, 0.15);
        color: #6a5acd;
        border: 1px solid rgba(153, 102, 255, 0.3);
    }

    .status-disposed {
        background-color: rgba(255, 99, 132, 0.15);
        color: #780404;
        border: 1px solid rgba(199, 54, 85, 0.3);
    }

    .status-lost {
        background-color: rgba(128, 128, 128, 0.15);
        color: #696969;
        border: 1px solid rgba(128, 128, 128, 0.3);
    }

    .premium-modal {
        border: none;
        border-radius: 24px;
        overflow: hidden;
        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
    }

    .premium-modal .modal-header {
        background: linear-gradient(135deg, #347b60 0%, #1f5e4f 100%) !important;
        padding: 1.5rem 2rem;
        border: none;
    }

    .bg-light-soft {
        background-color: #f8fafc;
        border: 1px solid #e2e8f0;
    }

    .status-pill-mini {
        padding: 2px 8px;
        border-radius: 12px;
        font-size: 0.65rem;
        font-weight: 700;
        letter-spacing: 0.5px;
    }

    .rounded-4 {
        border-radius: 1rem !important;
    }

    .text-overline {
        font-size: 0.7rem;
        font-weight: 800;
        color: #64748b;
        letter-spacing: 1.5px;
        text-transform: uppercase;
    }

    .info-grid-premium {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 1rem;
    }

    .info-item-card {
        display: flex;
        align-items: center;
        padding: 1rem;
        border-radius: 16px;
        transition: all 0.2s ease;
    }

    .info-item-card:hover {
        transform: translateY(-2px);
    }

    .info-icon {
        width: 42px;
        height: 42px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.1rem;
        margin-right: 1rem;
        flex-shrink: 0;
    }

    .info-content label {
        display: block;
        font-size: 0.65rem;
        text-transform: uppercase;
        color: #94a3b8;
        margin-bottom: 2px;
        font-weight: 700;
        letter-spacing: 0.5px;
    }

    .info-content .info-value {
        color: #1e293b;
        font-size: 0.95rem;
        font-weight: 600;
    }

    .bg-soft-primary {
        background-color: rgba(35, 76, 106, 0.1) !important;
        color: #234C6A !important;
    }

    .bg-soft-success {
        background-color: rgba(52, 123, 96, 0.1) !important;
        color: #347b60 !important;
    }

    .bg-soft-warning {
        background-color: rgba(245, 158, 11, 0.1) !important;
        color: #d97706 !important;
    }

    .bg-soft-info {
        background-color: rgba(6, 182, 212, 0.1) !important;
        color: #0891b2 !important;
    }

    .bg-soft-danger {
        background-color: rgba(239, 68, 68, 0.1) !important;
        color: #dc2626 !important;
    }

    .premium-card {
        border-radius: 1rem;
        overflow: hidden;
        background: #fff;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
    }

    .btn-soft-primary {
        background-color: rgba(35, 76, 106, 0.1);
        color: #234C6A;
        font-weight: 700;
        border: none;
        transition: all 0.2s;
    }

    .btn-soft-primary:hover {
        background-color: #234C6A;
        color: #fff;
    }

    @keyframes pulse-subtle {
        0% {
            transform: scale(1);
            opacity: 1;
        }

        50% {
            transform: scale(1.05);
            opacity: 0.8;
        }

        100% {
            transform: scale(1);
            opacity: 1;
        }
    }

    .pulse-animation {
        animation: pulse-subtle 3s infinite ease-in-out;
    }

    .action-card-premium {
        display: flex;
        align-items: center;
        padding: 1.25rem;
        border-radius: 16px;
        text-decoration: none !important;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        border: 1px solid transparent;
        background: #fff;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
    }

    .action-card-premium:hover {
        transform: translateY(-3px) scale(1.02);
        box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
    }

    .action-card-premium .action-icon {
        width: 48px;
        height: 48px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.25rem;
        margin-right: 1.25rem;
        transition: all 0.3s ease;
    }

    .action-card-premium.view {
        border-left: 4px solid #234C6A;
    }

    .action-card-premium.view .action-icon {
        background: rgba(35, 76, 106, 0.1);
        color: #234C6A;
    }

    .action-card-premium.view:hover {
        background: #234C6A;
        color: #fff !important;
    }

    .action-card-premium.view:hover .text-dark,
    .action-card-premium.view:hover .text-muted,
    .action-card-premium.view:hover .action-icon {
        color: #fff !important;
        background: transparent;
    }

    .action-card-premium.edit {
        border-left: 4px solid #697565;
    }

    .action-card-premium.edit .action-icon {
        background: rgba(105, 117, 101, 0.1);
        color: #697565;
    }

    .action-card-premium.edit:hover {
        background: #697565;
        color: #fff !important;
    }

    .action-card-premium.edit:hover .text-dark,
    .action-card-premium.edit:hover .text-muted,
    .action-card-premium.edit:hover .action-icon {
        color: #fff !important;
        background: transparent;
    }

    /* Google Style Landing */
    .google-landing-container {
        min-height: 75vh;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        padding: 2rem;
        background: #fff;
    }

    .google-logo {
        font-size: 5rem;
        font-weight: 700;
        letter-spacing: -3px;
        margin-bottom: 2.5rem;
        color: #233643;
    }

    .google-logo span {
        color: #347b60;
    }

    .google-search-wrapper {
        width: 100%;
        max-width: 650px;
        position: relative;
        margin-bottom: 1rem;
        z-index: 10;
        display: flex;
    }

    .google-search-bar {
        width: 100%;
        height: 48px;
        background: #fff;
        border: 1px solid #dfe1e5;
        border-radius: 24px 0 0 24px;
        padding: 0 20px 0 52px;
        font-size: 16px;
        color: #202124;
    }

    .google-search-bar:focus {
        outline: none;
        border-color: #3b82f6;
    }

    .google-search-icon {
        position: absolute;
        left: 18px;
        top: 50%;
        transform: translateY(-50%);
        color: #9aa0a6;
        font-size: 18px;
    }

    .google-btn-group {
        display: flex;
        gap: 12px;
        justify-content: center;
        margin-top: 1rem;
    }

    .google-btn {
        background-color: #f8f9fa;
        border: 1px solid #dfe1e5;
        border-radius: 8px;
        color: #3c4043;
        font-size: 14px;
        padding: 8px 16px;
        transition: all 0.2s;
    }

    .google-btn:hover {
        background-color: #f1f3f4;
        border-color: #dadce0;
    }

    .search-result-card {
        border-bottom: 1px solid #f1f3f4;
        padding: 15px 20px;
        cursor: pointer;
        transition: all 0.2s;
        display: flex;
        align-items: center;
    }

    .search-result-card:hover {
        background-color: #f8f9fa;
    }

    .search-result-icon {
        width: 40px;
        height: 40px;
        background: #f1f3f4;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #5f6368;
        margin-right: 15px;
    }

    .status-pill-mini {
        padding: 2px 10px;
        border-radius: 12px;
        font-size: 0.65rem;
        font-weight: 700;
        text-transform: uppercase;
    }

    .results-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 10px 20px;
        border-bottom: 1px solid #f1f3f4;
        background: #fff;
        border-radius: 8px 8px 0 0;
    }

    .hidden-section {
        display: none;
    }
</style>

<!-- Dashboard Content -->
<div class="container-fluid p-0">
    <!-- Landing Page -->
    <div class="google-landing-container" id="landingSection">
        <div class="google-logo">Event<span>ORY</span></div>
        <div class="google-search-wrapper shadow-sm rounded-pill overflow-hidden border">
            <i class="fas fa-search google-search-icon"></i>
            <input type="text" class="google-search-bar border-0 shadow-none flex-grow-1" id="mainDashboardSearch" placeholder="Search by name, serial, brand, or location..." autocomplete="off">
            <button class="btn text-white px-4 rounded-0" style="background-color: #347b60;" id="mainSearchTriggerBtn" style="height: 48px;">Search</button>
        </div>

        <div id="mainSearchSuggestions" class="mt-3 shadow-lg rounded-4 overflow-hidden bg-white border" style="display:none; width: 100%; max-width: 650px; z-index: 100;"></div>

        <div class="google-btn-group mt-4">
            <button class="google-btn" onclick="location.href='scan_bulk.php'"><i class="fas fa-qrcode me-2"></i>Scan QR Code</button>
            <?php if (in_array($user_role, $allowed_dashboard_roles)): ?>
                <button class="google-btn" id="toggleDashboardOverview"><i class="fas fa-chart-pie me-2"></i>Show Overview</button>
            <?php endif; ?>
        </div>
    </div>

    <!-- Hidden Overview -->
    <?php if (in_array($user_role, $allowed_dashboard_roles)): ?>
        <div id="dashboardOverviewSection" class="hidden-section px-4 pb-5">
            <div class="d-flex justify-content-between align-items-center mb-4 pt-4 border-top">
                <h4 class="fw-bold">Inventory Overview</h4>
                <button class="btn btn-sm btn-outline-secondary rounded-pill" onclick="hideDashboardOverview()">Hide Overview</button>
            </div>
            <!-- Stats Row -->
            <div class="row mb-4">
                <div class="col-sm-3">
                    <div class="card text-white p-4 rounded-4 border-0" style="background-color: #062925 !important;">Total: <?php echo $stats['total_items']; ?></div>
                </div>
                <div class="col-sm-3">
                    <div class="card text-white p-4 rounded-4 border-0" style="background-color: #044A42 !important;">Available: <?php echo $stats['available']; ?></div>
                </div>
                <div class="col-sm-3">
                    <div class="card text-white p-4 rounded-4 border-0" style="background-color: #3A9188 !important;">In Use: <?php echo $stats['in_use']; ?></div>
                </div>
                <div class="col-sm-3">
                    <div class="card text-dark p-4 rounded-4 border-0" style="background-color: #B8E1DD !important; color: #062925 !important;">Categories: <?php echo $stats['categories']; ?></div>
                </div>
            </div>

            <!-- Equipment Table -->
            <div class="row">
                <div class="col-lg-12">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h1 class="h3 mb-0 text-gray-800">Equipments Table</h1>
                        <div class="btn-group">
                            <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addItemModal">
                                <i class="fas fa-plus me-1"></i> Add Equipment
                            </button>
                            <a href="scan.php" class="btn btn-sm text-white" style="background-color: #29843d;">
                                <i class="fas fa-qrcode me-1"></i> Scan QR
                            </a>
                            <button onclick="downloadAllQRCodes()" class="btn btn-sm text-white" style="background-color: #294084;">
                                <i class="fas fa-download me-1"></i> Download All QR Codes
                            </button>
                            <button onclick="generateAndDownloadQRZipWithProgress()" class="btn btn-sm text-white" style="background-color: #2e7ca7;">
                                <i class="fas fa-file-archive me-1"></i> Generate &amp; Download ZIP
                            </button>
                        </div>
                    </div>

                    <div class="card shadow mb-4">
                        <div class="card-header py-3 d-flex justify-content-between align-items-center text-white" style="background: linear-gradient(135deg, #233643 0%, #2c4a5e 100%); border:none;">
                            <h6 class="m-0 font-weight-bold text-white"><i class="fas fa-list me-2"></i>Live Inventory Database</h6>
                            <button class="btn btn-sm btn-light bg-opacity-20 text-white border-0" id="refreshItemsBtn" title="Refresh Table">
                                <i class="fas fa-sync-alt"></i> Refresh
                            </button>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-bordered table-hover" id="recentItemsTable" width="100%" cellspacing="0">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Created At</th>
                                            <th>Item Name</th>
                                            <th>Serial #</th>
                                            <th>Category</th>
                                            <th>Department</th>
                                            <th>Accessories</th>
                                            <th>Model</th>
                                            <th>Brand</th>
                                            <th>Location</th>
                                            <th>Condition</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (!empty($recentItems)): ?>
                                            <?php foreach ($recentItems as $item): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($item['id'] ?? ''); ?></td>
                                                    <td>
                                                        <?php if (!empty($item['created_at'])) {
                                                            $createdDate = new DateTime($item['created_at']);
                                                            echo '<span class="badge bg-secondary">' . $createdDate->format('M d, Y') . '</span><br><small class="text-muted">' . $createdDate->format('h:i A') . '</small>';
                                                        } else {
                                                            echo '<span class="text-muted">N/A</span>';
                                                        } ?>
                                                    </td>
                                                    <td>
                                                        <div class="fw-bold"><?php echo htmlspecialchars($item['item_name'] ?? ''); ?></div>
                                                        <?php if (!empty($item['description'])): ?>
                                                            <small class="text-muted d-block"><?php echo substr(htmlspecialchars($item['description'] ?? ''), 0, 50); ?>...</small>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td><code><?php echo htmlspecialchars($item['serial_number'] ?? 'N/A'); ?></code></td>
                                                    <td>
                                                        <?php
                                                        $category = $item['category_name'] ?? $item['category'] ?? 'Uncategorized';
                                                        echo '<span class="badge bg-secondary">' . htmlspecialchars($category) . '</span>';
                                                        ?>
                                                    </td>
                                                    <td>
                                                        <?php
                                                        if (isset($item['id'])) {
                                                            try {
                                                                $acc_list = getItemAccessories($item['id'], $conn);
                                                                if (!empty($acc_list)) {
                                                                    foreach ($acc_list as $acc) {
                                                                        echo '<span class="badge bg-info me-1 mb-1">' . htmlspecialchars($acc['name'] ?? 'Unknown') . '</span> ';
                                                                    }
                                                                } else {
                                                                    echo '<span class="text-muted">None</span>';
                                                                }
                                                            } catch (Exception $e) {
                                                                echo '<span class="text-muted">Error</span>';
                                                            }
                                                        } else {
                                                            echo '<span class="text-muted">None</span>';
                                                        }
                                                        ?>
                                                    </td>
                                                    <td><?php echo !empty($item['brand']) ? htmlspecialchars($item['brand']) : '<span class="text-muted">N/A</span>'; ?></td>
                                                    <td><?php echo !empty($item['model']) ? htmlspecialchars($item['model']) : '<span class="text-muted">N/A</span>'; ?></td>
                                                    <td><?php echo !empty($item['department_name']) ? htmlspecialchars($item['department_name']) : (!empty($item['department']) ? htmlspecialchars($item['department']) : '<span class="text-muted">N/A</span>'); ?></td>
                                                    <td><?php echo !empty($item['stock_location']) ? htmlspecialchars($item['stock_location']) : '<span class="text-muted">N/A</span>'; ?></td>
                                                    <td>
                                                        <?php
                                                        $condition = $item['condition'] ?? 'good';
                                                        $conditionClass = match (strtolower($condition)) {
                                                            'new'  => 'bg-success',
                                                            'good' => 'bg-primary',
                                                            'fair' => 'bg-warning',
                                                            'poor' => 'bg-danger',
                                                            default => 'bg-secondary'
                                                        };
                                                        echo '<span class="badge ' . $conditionClass . '">' . ucfirst($condition) . '</span>';
                                                        ?>
                                                    </td>
                                                    <td>
                                                        <?php
                                                        $status = $item['status'] ?? 'available';
                                                        $statusClass = match (strtolower($status)) {
                                                            'available'   => 'bg-success',
                                                            'in_use'      => 'bg-primary',
                                                            'maintenance' => 'bg-warning',
                                                            'reserved'    => 'bg-info',
                                                            'disposed'    => 'bg-danger',
                                                            'lost'        => 'bg-dark',
                                                            default       => 'bg-secondary'
                                                        };
                                                        echo '<span class="badge ' . $statusClass . '">' . ucfirst($status) . '</span>';
                                                        ?>
                                                    </td>
                                                    <td>
                                                        <!-- FIX: Removed data-bs-toggle/data-bs-target — jQuery handler manages the modal -->
                                                        <button type="button" class="btn btn-sm btn-primary quick-view-btn mb-1"
                                                            data-item-id="<?php echo htmlspecialchars($item['id'] ?? ''); ?>"
                                                            data-item-name="<?php echo htmlspecialchars($item['item_name'] ?? ''); ?>"
                                                            data-item-serial="<?php echo htmlspecialchars($item['serial_number'] ?? ''); ?>"
                                                            data-item-category="<?php echo htmlspecialchars($item['category_name'] ?? $item['category'] ?? 'N/A'); ?>"
                                                            data-item-quantity="<?php echo intval($item['quantity'] ?? 1); ?>"
                                                            data-item-status="<?php echo htmlspecialchars($item['status'] ?? 'available'); ?>"
                                                            data-item-condition="<?php echo htmlspecialchars($item['condition'] ?? 'good'); ?>"
                                                            data-item-location="<?php echo htmlspecialchars($item['stock_location'] ?? 'N/A'); ?>"
                                                            data-item-brand="<?php echo htmlspecialchars($item['brand'] ?? 'N/A'); ?>"
                                                            data-item-model="<?php echo htmlspecialchars($item['model'] ?? 'N/A'); ?>"
                                                            data-item-department="<?php echo htmlspecialchars($item['department_name'] ?? $item['department'] ?? 'N/A'); ?>"
                                                            data-qr-code="<?php echo htmlspecialchars($item['qr_code'] ?? ''); ?>"
                                                            title="Quick Actions">
                                                            <i class="fas fa-bolt"></i> Quick Actions
                                                        </button>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="13" class="text-center">No equipment found</td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div> <!-- End of dashboardOverviewSection -->
        <?php endif; ?>
        </div> <!-- End of container-fluid (from line 792) -->

        <!-- ==================== MODALS ==================== -->

        <!-- Quick Search Modal -->
        <div class="modal fade" id="quickSearchModal" tabindex="-1" aria-labelledby="quickSearchModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header text-white" style="background-color: #233643;">
                        <h5 class="modal-title" id="quickSearchModalLabel"><i class="fas fa-search me-2"></i>Quick Inventory Search</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="input-group mb-3">
                            <span class="input-group-text"><i class="fas fa-search"></i></span>
                            <input type="text" class="form-control form-control-lg" id="quickSearchInput"
                                placeholder="Search by name, serial, brand, location..."
                                autocomplete="off">
                            <button class="btn btn-primary" type="button" id="quickSearchBtn">Search</button>
                        </div>
                        <!-- Stats shown before search -->
                        <div id="quickStatsSection">
                            <div class="row text-center g-3">
                                <div class="col-4">
                                    <div class="border rounded p-3">
                                        <div class="h4 fw-bold text-primary"><?php echo $stats['total_items'] ?? 0; ?></div>
                                        <div class="small text-muted">Total Items</div>
                                    </div>
                                </div>
                                <div class="col-4">
                                    <div class="border rounded p-3">
                                        <div class="h4 fw-bold text-success"><?php echo $stats['available'] ?? 0; ?></div>
                                        <div class="small text-muted">Available</div>
                                    </div>
                                </div>
                                <div class="col-4">
                                    <div class="border rounded p-3">
                                        <div class="h4 fw-bold text-warning"><?php echo $stats['in_use'] ?? 0; ?></div>
                                        <div class="small text-muted">In Use</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- Search results -->
                        <div id="quickSearchResults" style="display:none;">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <small class="text-muted"><span id="searchResultCount">0</span> result(s) found</small>
                                <button class="btn btn-sm btn-outline-secondary" onclick="clearQuickSearch()">
                                    <i class="fas fa-times me-1"></i>Clear
                                </button>
                            </div>
                            <div id="searchResultsContainer"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>


        <!-- Datalist -->
        <datalist id="itemSuggestions">
            <?php foreach ($allItems as $item): ?>
                <option value="<?php echo htmlspecialchars($item['item_name']); ?>">
                <?php endforeach; ?>
        </datalist>

        <!-- Add Item Modal -->
        <div class="modal" id="addItemModal" tabindex="-1" aria-labelledby="addItemModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title" id="addItemModalLabel"><i class="fas fa-plus-circle me-2"></i>Add New Equipment</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form id="addItemForm" method="POST" enctype="multipart/form-data">
                        <div class="modal-body">
                            <div class="alert alert-info d-flex align-items-center mb-3">
                                <i class="fas fa-info-circle me-2"></i>
                                <div>Fields marked with <span class="text-danger">*</span> are required</div>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="item_name" class="form-label">Equipment Name <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="item_name" name="item_name" required placeholder="e.g., Mini Converter SDI to HDMI">
                                        <div class="form-text">Descriptive name for identification</div>
                                    </div>
                                    <div class="mb-3">
                                        <label for="serial_number" class="form-label">Serial Number <span class="text-danger">*</span></label>
                                        <div class="input-group">
                                            <input type="text" class="form-control" id="serial_number" name="serial_number" required placeholder="Enter unique serial number">
                                            <button class="btn btn-outline-secondary" type="button" id="generateSerialBtn" title="Generate Serial"><i class="fas fa-bolt"></i> Auto</button>
                                        </div>
                                        <div class="form-text">Each serial number must be unique</div>
                                    </div>
                                    <div class="mb-3">
                                        <label for="quantity" class="form-label">Quantity</label>
                                        <div class="input-group">
                                            <input type="number" class="form-control" id="quantity" name="quantity" value="1" min="1">
                                            <span class="input-group-text">units</span>
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <label for="brand" class="form-label">Brand</label>
                                        <input type="text" class="form-control" id="brand" name="brand" placeholder="e.g., Blackmagic Design">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="model" class="form-label">Model</label>
                                        <input type="text" class="form-control" id="model" name="model" placeholder="e.g., Mini Converter SDI to HDMI">
                                    </div>
                                    <div class="mb-3">
                                        <label for="category" class="form-label">Category <span class="text-danger">*</span></label>
                                        <select class="form-select" id="category" name="category" required>
                                            <option value="">Select Category</option>
                                            <?php $categories = getCategories();
                                            foreach ($categories as $key => $value): ?>
                                                <option value="<?php echo $key; ?>"><?php echo $value; ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="mb-3">
                                        <label for="department" class="form-label">Department</label>
                                        <select class="form-select" id="department" name="department">
                                            <option value="">Select Department</option>
                                            <?php $departments = getDepartments();
                                            foreach ($departments as $key => $value): ?>
                                                <option value="<?php echo $key; ?>"><?php echo $value; ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="mb-3">
                                        <label for="status" class="form-label">Status</label>
                                        <select class="form-select" id="status" name="status">
                                            <?php $statuses = getStatuses();
                                            foreach ($statuses as $key => $value): ?>
                                                <option value="<?php echo $key; ?>" <?php echo $key === 'available' ? 'selected' : ''; ?>><?php echo $value; ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="mb-3">
                                        <label for="condition" class="form-label">Condition</label>
                                        <select class="form-select" id="condition" name="condition">
                                            <?php $conditions = getConditions();
                                            foreach ($conditions as $key => $value): ?>
                                                <option value="<?php echo $key; ?>" <?php echo $key === 'good' ? 'selected' : ''; ?>><?php echo $value; ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="stock_location" class="form-label">Location</label>
                                        <select class="form-select" id="stock_location" name="stock_location">
                                            <option value="">Select Location</option>
                                            <?php $locations = getLocations();
                                            foreach ($locations as $key => $value): ?>
                                                <option value="<?php echo $key; ?>"><?php echo $value; ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="item_image" class="form-label">Equipment Image</label>
                                        <input type="file" class="form-control" id="item_image" name="item_image" accept="image/*">
                                        <div class="form-text">JPG, PNG, GIF, WebP (Max 5MB)</div>
                                        <div class="mt-2" id="imagePreview" style="display:none;">
                                            <img src="" alt="Preview" class="img-thumbnail" style="max-height:120px;">
                                            <button type="button" class="btn btn-sm btn-danger mt-1" onclick="clearImagePreview()"><i class="fas fa-times"></i> Remove</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="description" class="form-label">Description</label>
                                <textarea class="form-control" id="description" name="description" rows="3" placeholder="Additional details about this equipment"></textarea>
                            </div>
                            <div class="card border-info mb-4">
                                <div class="card-header bg-info text-white py-2"><i class="fas fa-puzzle-piece me-2"></i>Accessories</div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-8">
                                            <div class="mb-3">
                                                <label for="accessories" class="form-label">Select Accessories</label>
                                                <select class="form-select" id="accessories" name="accessories[]" multiple size="6">
                                                    <option value="">-- No Accessories --</option>
                                                    <?php foreach ($accessories as $accessory): ?>
                                                        <option value="<?php echo $accessory['id']; ?>"
                                                            data-description="<?php echo htmlspecialchars($accessory['description']); ?>">
                                                            <?php echo htmlspecialchars($accessory['name']); ?> (<?php echo $accessory['available_quantity']; ?> available)
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <div class="form-text">Hold Ctrl/Cmd to select multiple accessories</div>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="mb-3">
                                                <label class="form-label">Selected Accessories</label>
                                                <div id="selectedAccessories" class="border rounded p-2" style="min-height:150px; max-height:150px; overflow-y:auto;">
                                                    <p class="text-muted mb-0">No accessories selected</p>
                                                </div>
                                                <small class="text-muted">Click to remove</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="card border-info mb-3">
                                <div class="card-header bg-info text-white py-2"><i class="fas fa-qrcode me-2"></i>QR Code Settings</div>
                                <div class="card-body py-2">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="generate_qr" name="generate_qr" checked>
                                        <label class="form-check-label" for="generate_qr">Generate QR Code for this equipment</label>
                                    </div>
                                    <div class="form-text">QR codes make inventory scanning easier</div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i class="fas fa-times me-1"></i> Cancel</button>
                            <button type="reset" class="btn btn-outline-secondary" id="resetBtn"><i class="fas fa-redo me-1"></i> Reset</button>
                            <button type="submit" class="btn btn-primary" id="submitBtn"><i class="fas fa-save me-1"></i> Save Equipment</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Quick Actions Modal -->
        <div class="modal" id="quickActionsModal" tabindex="-1" aria-labelledby="quickActionsModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-xl modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header text-white" style="background-color: #234C6A;">
                        <h5 class="modal-title" id="quickActionsModalLabel"><i class="fas fa-bolt me-2"></i>Quick Actions</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-0">
                            <div class="col-md-8 border-end">
                                <div class="p-4">
                                    <div class="d-flex justify-content-between align-items-start mb-4">
                                        <div>
                                            <h4 id="qvItemName" class="fw-bold mb-1" style="color:#233643;"></h4>
                                            <div class="d-flex flex-wrap gap-2 align-items-center">
                                                <span class="badge bg-secondary" id="qvItemCategory"></span>
                                                <span class="badge" id="qvItemStatusBadge"></span>
                                                <span class="badge" id="qvItemConditionBadge"></span>
                                                <span class="text-muted small"><i class="fas fa-hashtag me-1"></i>ID: <span id="qvItemId"></span></span>
                                            </div>
                                        </div>
                                        <div id="qvQRCode" class="text-center" style="min-width:150px; min-height:180px; background:#f8f9fa; border-radius:8px; padding:10px;">
                                            <div class="text-muted"><i class="fas fa-qrcode fa-3x mb-2 d-block"></i><small>Loading QR Code...</small></div>
                                        </div>
                                    </div>
                                    <div class="row mb-4">
                                        <div class="col-6 col-md-3 mb-3">
                                            <div class="border rounded p-3 text-center">
                                                <div class="text-muted small">Serial</div>
                                                <div class="fw-bold mt-1" id="qvItemSerial"></div>
                                            </div>
                                        </div>
                                        <div class="col-6 col-md-3 mb-3">
                                            <div class="border rounded p-3 text-center">
                                                <div class="text-muted small">Quantity</div>
                                                <div class="fw-bold mt-1" id="qvItemQuantity"></div>
                                            </div>
                                        </div>
                                        <div class="col-6 col-md-3 mb-3">
                                            <div class="border rounded p-3 text-center">
                                                <div class="text-muted small">Location</div>
                                                <div class="fw-bold mt-1" id="qvItemLocation"></div>
                                            </div>
                                        </div>
                                        <div class="col-6 col-md-3 mb-3">
                                            <div class="border rounded p-3 text-center">
                                                <div class="text-muted small">Department</div>
                                                <div class="fw-bold mt-1" id="qvItemDepartment"></div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="border rounded p-3 mb-3">
                                                <div class="text-muted small">Brand</div>
                                                <div class="fw-bold" id="qvItemBrand"></div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="border rounded p-3 mb-3">
                                                <div class="text-muted small">Model</div>
                                                <div class="fw-bold" id="qvItemModel"></div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="border rounded p-3 mb-3">
                                                <div class="text-muted small">Storage Location</div>
                                                <div class="fw-bold" id="qvItemStorageLocation"></div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="border rounded p-3 mb-3">
                                                <div class="text-muted small">Accessories</div>
                                                <div class="fw-bold" id="qvItemAccessories"></div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="border rounded p-3 mb-3">
                                                <div class="text-muted small">Created At</div>
                                                <div class="fw-bold" id="qvItemCreatedAt"></div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="border rounded p-3 mb-3">
                                                <div class="text-muted small">Updated At</div>
                                                <div class="fw-bold" id="qvItemUpdatedAt"></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4 bg-light">
                                <div class="p-4 h-100">
                                    <h6 class="fw-bold mb-4">Actions</h6>
                                    <div class="mb-3">
                                        <a href="#" id="qvViewBtn" class="btn btn-primary w-100 py-3" style="background-color:#234C6A; color:#fff; text-decoration:none;">
                                            <div class="d-flex align-items-center justify-content-center">
                                                <i class="fas fa-eye fa-2x me-3"></i>
                                                <div class="text-start">
                                                    <div class="fw-bold">View Details</div><small class="opacity-75">Complete item information</small>
                                                </div>
                                            </div>
                                        </a>
                                    </div>
                                    <div class="mb-3">
                                        <a href="#" id="qvEditBtn" class="btn btn-secondary w-100 py-3" style="background-color:#697565; color:#fff; text-decoration:none;">
                                            <div class="d-flex align-items-center justify-content-center">
                                                <i class="fas fa-edit fa-2x me-3"></i>
                                                <div class="text-start">
                                                    <div class="fw-bold">Edit Item</div><small class="opacity-75">Modify item details</small>
                                                </div>
                                            </div>
                                        </a>
                                    </div>
                                    <div class="mb-3" id="qvQRActions"></div>
                                    <div class="border-top pt-3 mt-3">
                                        <div class="row g-2">
                                            <div class="col-6"><button class="btn btn-outline-info w-100" id="qvCopySerialBtn"><i class="fas fa-copy me-1"></i> Copy Serial</button></div>
                                            <div class="col-6"><button class="btn btn-outline-dark w-100" id="qvPrintBtn"><i class="fas fa-print me-1"></i> Print</button></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i class="fas fa-times me-1"></i> Close</button>
                        <button type="button" class="btn btn-outline-success" id="qvRefreshBtn"><i class="fas fa-sync-alt me-1"></i> Refresh</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- View Item Details Modal -->
        <div class="modal" id="viewItemModal" tabindex="-1" aria-labelledby="viewItemModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-xl">
                <div class="modal-content">
                    <div class="modal-header text-white" style="background:#347b60;">
                        <h5 class="modal-title" id="viewItemModalLabel"><i class="fas fa-eye me-2"></i>Item Details</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-4">
                            <div class="col-md-7">
                                <div class="mb-4">
                                    <label class="text-overline mb-1">Equipment Profile</label>
                                    <h3 id="viewItemName" class="fw-bold mb-2" style="color:#1e293b;"></h3>
                                    <div class="d-flex flex-wrap gap-2 align-items-center">
                                        <span class="badge bg-soft-primary text-primary border" id="viewCategory"></span>
                                        <span class="badge" id="viewItemStatusBadge"></span>
                                        <span class="badge bg-light text-dark border px-3 rounded-pill"><i class="fas fa-hashtag me-1 opacity-50"></i>ID: <span id="viewItemId"></span></span>
                                    </div>
                                </div>

<<<<<<< HEAD
=======
                                <div class="mb-2 mt-4 checkout-info-container" style="display:none;"><label class="text-overline text-primary"><i class="fas fa-truck-loading me-2"></i>Active Checkout</label></div>
                                <hr class="mt-0 mb-4 checkout-info-container" style="border-color:#e2e8f0; display:none;">
                                <div class="info-grid-premium mb-4 checkout-info-container" style="display:none;">
                                    <div class="info-item-card border" style="background-color: #f0fdf4; border-color: #bbf7d0 !important;">
                                        <div class="info-icon" style="background-color: #dcfce7; color: #166534;"><i class="fas fa-user-check"></i></div>
                                        <div class="info-content">
                                            <label style="color: #166534;">Checked Out By</label>
                                            <div id="viewCheckedOutBy" class="info-value fw-bold" style="color: #14532d;"></div>
                                        </div>
                                    </div>
                                    <div class="info-item-card border" style="background-color: #f0fdf4; border-color: #bbf7d0 !important;">
                                        <div class="info-icon" style="background-color: #dcfce7; color: #166534;"><i class="fas fa-clock"></i></div>
                                        <div class="info-content">
                                            <label style="color: #166534;">Checked Out At</label>
                                            <div id="viewCheckedOutAt" class="info-value" style="color: #14532d;"></div>
                                        </div>
                                    </div>
                                </div>

>>>>>>> addf346 (Latest Upload - Events cards, OverView, Items status..)
                                <div class="mb-2 mt-4"><label class="text-overline">Basic Information</label></div>
                                <hr class="mt-0 mb-4" style="border-color:#e2e8f0;">
                                <div class="info-grid-premium mb-4">
                                    <div class="info-item-card bg-white border">
                                        <div class="info-icon bg-soft-primary"><i class="fas fa-barcode"></i></div>
                                        <div class="info-content">
                                            <label>Serial Number</label>
                                            <div id="viewSerialNumber" class="info-value font-monospace"></div>
                                        </div>
                                    </div>
                                    <div class="info-item-card bg-white border">
                                        <div class="info-icon bg-soft-primary"><i class="fas fa-heartbeat"></i></div>
                                        <div class="info-content">
                                            <label>Condition</label>
                                            <div id="viewCondition" class="info-value text-uppercase"></div>
                                        </div>
                                    </div>
                                    <div class="info-item-card bg-white border">
                                        <div class="info-icon bg-soft-primary"><i class="fas fa-layer-group"></i></div>
                                        <div class="info-content">
                                            <label>Quantity</label>
                                            <div id="viewQuantity" class="info-value"></div>
                                        </div>
                                    </div>
                                </div>

                                <div class="mb-2 mt-4"><label class="text-overline">Brand & Location</label></div>
                                <hr class="mt-0 mb-4" style="border-color:#e2e8f0;">
                                <div class="info-grid-premium mb-4">
                                    <div class="info-item-card bg-white border">
                                        <div class="info-icon bg-soft-success"><i class="fas fa-industry"></i></div>
                                        <div class="info-content">
                                            <label>Brand</label>
                                            <div id="viewBrand" class="info-value"></div>
                                        </div>
                                    </div>
                                    <div class="info-item-card bg-white border">
                                        <div class="info-icon bg-soft-success"><i class="fas fa-cube"></i></div>
                                        <div class="info-content">
                                            <label>Model</label>
                                            <div id="viewModel" class="info-value"></div>
                                        </div>
                                    </div>
                                    <div class="info-item-card bg-white border">
                                        <div class="info-icon bg-soft-warning"><i class="fas fa-building"></i></div>
                                        <div class="info-content">
                                            <label>Department</label>
                                            <div id="viewDepartment" class="info-value"></div>
                                        </div>
                                    </div>
                                    <div class="info-item-card bg-white border">
                                        <div class="info-icon bg-soft-warning"><i class="fas fa-map-marker-alt"></i></div>
                                        <div class="info-content">
                                            <label>Stock Location</label>
                                            <div id="viewStockLocation" class="info-value text-truncate"></div>
                                        </div>
                                    </div>
                                    <div class="info-item-card bg-white border">
                                        <div class="info-icon bg-soft-warning"><i class="fas fa-archive"></i></div>
                                        <div class="info-content">
                                            <label>Storage Location</label>
                                            <div id="viewStorageLocation" class="info-value"></div>
                                        </div>
                                    </div>
                                </div>

                                <div class="premium-card border mb-4">
                                    <div class="card-header bg-light py-2">
                                        <span class="text-overline"><i class="fas fa-puzzle-piece me-2"></i>Included Accessories</span>
                                    </div>
                                    <div class="card-body p-3" id="viewAccessoriesList"></div>
                                </div>
                            </div>

                            <div class="col-md-5">
                                <div class="bg-light-soft rounded-4 p-3 border shadow-sm mb-4">
                                    <div id="viewItemImage" class="text-center mb-0" style="display:none;">
                                        <img src="" alt="Item Image" class="img-fluid rounded-4 shadow-sm" style="max-height:350px; width: 100%; object-fit: contain;">
                                    </div>
                                    <div id="viewNoImage" class="text-center mb-0">
                                        <div class="image-placeholder bg-white rounded-4 p-5 shadow-sm border" style="min-height:250px; display:flex; align-items:center; justify-content:center;">
                                            <div class="text-center">
                                                <i class="fas fa-camera fa-4x text-muted mb-3 opacity-25 pulse-animation"></i>
                                                <h5 class="text-muted mb-2">No Image Available</h5>
                                                <button type="button" class="btn btn-sm btn-soft-primary mt-3" onclick="openEditItemModalFromNoImage()"><i class="fas fa-upload me-1"></i> Add Image</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="premium-card border mb-4">
                                    <div class="card-header bg-soft-primary py-2 d-flex justify-content-between align-items-center">
                                        <span class="fw-bold small text-primary"><i class="fas fa-qrcode me-2"></i>QR IDENTIFIER</span>
                                    </div>
                                    <div class="card-body text-center p-4" id="viewQRCode"></div>
                                    <div class="card-footer bg-light border-top-0 d-flex gap-2 p-3">
                                        <button type="button" class="btn btn-sm btn-light border flex-grow-1" id="viewDownloadQRBtn"><i class="fas fa-download me-1"></i> Download</button>
                                        <button type="button" class="btn btn-sm btn-light border flex-grow-1" id="viewPrintQRBtn"><i class="fas fa-print me-1"></i> Print</button>
                                    </div>
                                </div>

                                <div class="p-3 bg-light rounded-4 border">
                                    <div class="row g-2">
                                        <div class="col-6 text-center border-end">
                                            <label class="text-overline d-block mb-1" style="font-size: 0.6rem;">Registered</label>
                                            <div id="viewCreatedAt" class="small fw-bold"></div>
                                        </div>
                                        <div class="col-6 text-center">
                                            <label class="text-overline d-block mb-1" style="font-size: 0.6rem;">Last Modified</label>
                                            <div id="viewUpdatedAt" class="small fw-bold"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn" style="background:rgba(201,58,53,0.4)" data-bs-dismiss="modal"><i class="fas fa-times me-1"></i> Close</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Edit Item Modal -->
        <div class="modal" id="editItemModal" tabindex="-1" aria-labelledby="editItemModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-xl">
                <div class="modal-content">
                    <div class="modal-header text-white" style="background-color:#1f4d5e;">
                        <h5 class="modal-title" id="editItemModalLabel"><i class="fas fa-edit me-2"></i>Edit Equipment</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form id="editItemForm" method="POST" enctype="multipart/form-data">
                        <div class="modal-body">
                            <input type="hidden" id="editItemId" name="id">
                            <div class="alert alert-info py-2 mb-3"><i class="fas fa-info-circle me-2"></i><small>Fields marked with <span class="fw-bold text-danger">*</span> are required</small></div>
                            <div class="row">
                                <div class="col-md-7">
                                    <h6 class="fw-bold mb-2" style="color:#1f5e4f;">BASIC INFORMATION</h6>
                                    <hr class="mt-0 mb-3" style="border-color:#1f5e4f; opacity:0.3;">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3"><label for="editItemName" class="form-label small fw-bold">Equipment Name <span class="text-danger">*</span></label><input type="text" class="form-control form-control-sm" id="editItemName" name="item_name" required></div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3"><label for="editSerialNumber" class="form-label small fw-bold">Serial Number <span class="text-danger">*</span></label><input type="text" class="form-control form-control-sm" id="editSerialNumber" name="serial_number" required></div>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-4">
                                            <div class="mb-3"><label for="editCategory" class="form-label small fw-bold">Category <span class="text-danger">*</span></label>
                                                <select class="form-select form-select-sm" id="editCategory" name="category" required>
                                                    <option value="">Select Category</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="mb-3"><label for="editStatus" class="form-label small fw-bold">Status</label>
                                                <select class="form-select form-select-sm" id="editStatus" name="status">
                                                    <option value="available">Available</option>
                                                    <option value="in_use">In Use</option>
                                                    <option value="maintenance">Maintenance</option>
                                                    <option value="reserved">Reserved</option>
                                                    <option value="disposed">Disposed</option>
                                                    <option value="lost">Lost</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="mb-3"><label for="editCondition" class="form-label small fw-bold">Condition</label>
                                                <select class="form-select form-select-sm" id="editCondition" name="condition">
                                                    <option value="new">New</option>
                                                    <option value="excellent">Excellent</option>
                                                    <option value="good">Good</option>
                                                    <option value="fair">Fair</option>
                                                    <option value="poor">Poor</option>
                                                    <option value="damaged">Damaged</option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                    <h6 class="fw-bold mt-4 mb-2" style="color:#1f5e4f;">BRAND &amp; LOCATION</h6>
                                    <hr class="mt-0 mb-3" style="border-color:#1f5e4f; opacity:0.3;">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3"><label for="editBrand" class="form-label small fw-bold">Brand</label><input type="text" class="form-control" id="editBrand" name="brand"></div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3"><label for="editModel" class="form-label small fw-bold">Model</label><input type="text" class="form-control form-control-sm" id="editModel" name="model"></div>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-4">
                                            <div class="mb-3"><label for="editStockLocation" class="form-label small fw-bold">Stock Location</label><input type="text" class="form-control form-control-sm" id="editStockLocation" name="stock_location"></div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="mb-3"><label for="editStorageLocation" class="form-label small fw-bold">Storage Location</label><input type="text" class="form-control form-control-sm" id="editStorageLocation" name="storage_location"></div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="mb-3"><label for="editDepartment" class="form-label small fw-bold">Department</label>
                                                <select class="form-select form-select-sm" id="editDepartment" name="department">
                                                    <option value="">Select Department</option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="mb-2"><label class="text-overline">Equipment Image</label></div>
                                    <hr class="mt-0 mb-4" style="border-color:#e2e8f0;">
                                    <div class="bg-light-soft rounded-4 p-4 border shadow-sm">
                                        <div class="row align-items-center">
                                            <div class="col-md-3 text-center">
                                                <div id="editCurrentImage" class="bg-white rounded-4 p-2 shadow-sm border mx-auto" style="height:100px; width:100px; display:flex; align-items:center; justify-content:center;">
                                                    <img src="" alt="Current" class="img-fluid rounded-3" style="max-height:85px; object-fit: contain; display:none;">
                                                    <span class="no-image-text text-muted small opacity-50">No Image</span>
                                                </div>
                                            </div>
                                            <div class="col-md-9">
                                                <div class="form-check mb-3">
                                                    <input class="form-check-input" type="checkbox" id="editChangeImage" name="change_image">
                                                    <label class="form-check-label fw-bold text-dark small" for="editChangeImage">Update Asset Photograph</label>
                                                </div>
                                                <div id="editImageUploadSection" style="display:none;">
                                                    <input type="file" class="form-control form-control-sm mb-2" id="editItemImage" name="item_image" accept="image/*">
                                                    <div class="d-flex justify-content-between align-items-center">
                                                        <small class="text-muted"><i class="fas fa-info-circle me-1"></i> Max 5MB (JPG/PNG)</small>
                                                        <div id="editImagePreview" style="display:none;"><img src="" alt="Preview" class="rounded border shadow-sm" style="max-height:40px;"></div>
                                                    </div>
                                                    <div class="form-check mt-3 pt-2 border-top" id="applySimilarSection" style="display:none;">
                                                        <input class="form-check-input" type="checkbox" id="editApplyToSimilar" name="apply_to_similar">
                                                        <label class="form-check-label small fw-bold text-primary" for="editApplyToSimilar">
                                                            <i class="fas fa-magic me-1"></i> Sync image to identical assets
                                                        </label>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-md-5">
                                    <div class="premium-card border mb-3">
                                        <div class="card-header bg-soft-primary py-2 d-flex justify-content-between align-items-center">
                                            <span class="fw-bold small text-primary"><i class="fas fa-qrcode me-2"></i>QR IDENTIFIER</span>
                                        </div>
                                        <div class="card-body text-center p-4">
                                            <div id="editQRCode" class="mb-3">
                                                <div class="text-center text-muted opacity-50">
                                                    <i class="fas fa-qrcode fa-4x mb-2"></i>
                                                    <p class="small">Fetching QR...</p>
                                                </div>
                                            </div>
                                            <button type="button" class="btn btn-sm btn-soft-primary w-100 py-2" id="editRegenerateQRBtn">
                                                <i class="fas fa-sync-alt me-1"></i> Regenerate QR
                                            </button>
                                        </div>
                                        <div class="card-footer bg-light border-top-0 text-center py-2">
                                            <div class="d-flex justify-content-between px-2">
                                                <span class="text-muted small">Asset ID:</span>
                                                <span class="fw-bold text-dark small" id="editItemIdDisplay">-</span>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="premium-card border">
                                        <div class="card-header bg-light py-2">
                                            <span class="text-overline"><i class="fas fa-puzzle-piece me-2"></i>Management Accessories</span>
                                        </div>
                                        <div class="card-body p-3">
                                            <label class="form-label small text-muted mb-2">Available Components</label>
                                            <select class="form-select form-select-sm mb-3" id="editAccessories" name="accessories[]" multiple size="5" style="border-radius: 10px;">
                                                <option value="">-- No Accessories --</option>
                                            </select>

                                            <label class="text-overline small mb-2 d-block">Currently Selected</label>
                                            <div id="editSelectedAccessories" class="bg-light-soft rounded-3 p-3 border" style="min-height:100px; max-height:120px; overflow-y:auto;">
                                                <p class="text-muted small mb-0">No selection made</p>
                                            </div>
                                            <small class="text-muted mt-2 d-block" style="font-size: 0.7rem;">Hold <kbd class="bg-secondary">Ctrl</kbd> for multiple selection</small>
                                        </div>
                                    </div>

                                    <div class="mt-4 p-3 bg-light rounded-4 border">
                                        <div class="row g-2">
                                            <div class="col-6">
                                                <label class="text-overline d-block mb-1" style="font-size: 0.6rem;">Created</label>
                                                <div id="editCreatedAt" class="small fw-bold text-truncate" style="font-size: 0.75rem;">N/A</div>
                                            </div>
                                            <div class="col-6">
                                                <label class="text-overline d-block mb-1" style="font-size: 0.6rem;">Modified</label>
                                                <div id="editUpdatedAt" class="small fw-bold text-truncate" style="font-size: 0.75rem;">N/A</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer py-2">
                            <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal"><i class="fas fa-times me-1"></i> Cancel</button>
                            <button type="submit" class="btn btn-sm btn-success" id="editSubmitBtn"><i class="fas fa-save me-1"></i> Save Changes</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <?php
        $db->close();
        require_once 'views/partials/footer.php';
        ?>

        <!-- DataTables JS -->
        <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
        <script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>

        <script>
            // ========== GLOBAL VARIABLES ==========
            let originalEditModalHtml = '';
            let searchTimeout = null;
            const BASE_URL = '<?php echo rtrim((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']), '/\\'); ?>/';

            // ========== MODAL BACKDROP CLEANUP ==========
            $(document).ready(function() {
                $('.modal-backdrop').remove();
                $('body').removeClass('modal-open').css({
                    overflow: '',
                    'padding-right': ''
                });
            });

            $(document).on('hidden.bs.modal', '.modal', function() {
                if ($('.modal.show').length === 0) {
                    $('.modal-backdrop').remove();
                    $('body').removeClass('modal-open').css({
                        overflow: '',
                        'padding-right': ''
                    });
                }
            });

            // ========== AJAX ERROR HANDLER ==========
            $(document).ajaxError(function(event, jqxhr, settings, thrownError) {
                console.error('AJAX Error:', {
                    url: settings.url,
                    status: jqxhr.status,
                    error: thrownError
                });
                if (!settings.url.includes('list.php')) {
                    toastr.error('Error loading data from ' + settings.url);
                }
            });

            // ========== UTILITY FUNCTIONS ==========
            function escapeHtml(text) {
                if (!text) return '';
                const div = document.createElement('div');
                div.textContent = String(text);
                return div.innerHTML;
            }

            function formatDateTime(dt) {
                if (!dt) return 'N/A';
                try {
                    const date = new Date(dt);
                    if (isNaN(date.getTime())) return dt;
                    return date.toLocaleDateString('en-US', {
                        year: 'numeric',
                        month: 'short',
                        day: 'numeric',
                        hour: '2-digit',
                        minute: '2-digit'
                    });
                } catch {
                    return dt;
                }
            }

            function getImageUrl(imagePath) {
                if (!imagePath || imagePath === '' || imagePath === 'null' || imagePath === 'undefined') return null;
                if (imagePath.startsWith('http://') || imagePath.startsWith('https://')) return imagePath;
                if (imagePath.startsWith('/')) return window.location.origin + imagePath;
                if (imagePath.startsWith('uploads/') || imagePath.startsWith('images/') || imagePath.startsWith('img/'))
                    return BASE_URL.replace(/\/$/, '') + '/' + imagePath;
                return BASE_URL.replace(/\/$/, '') + '/uploads/' + imagePath.replace(/^\/?(uploads\/)?/, '');
            }

            // ========== CLOCK & CALENDAR ==========
            function updateDateTime() {
                const now = new Date();
                const tz = Intl.DateTimeFormat().resolvedOptions().timeZone;
                $('#timezone').text(tz.split('/').pop().replace(/_/g, ' '));
                $('#digitalDay').text(now.toLocaleDateString('en-US', {
                    weekday: 'long'
                }));
                $('#digitalDate').text(now.toLocaleDateString('en-US', {
                    month: 'short',
                    day: 'numeric',
                    year: 'numeric'
                }));
                $('#todayDate').text(now.toLocaleDateString('en-US', {
                    month: 'long',
                    day: 'numeric',
                    year: 'numeric'
                }));
                let h = now.getHours(),
                    m = now.getMinutes(),
                    s = now.getSeconds();
                const ampm = h >= 12 ? 'PM' : 'AM';
                h = h % 12 || 12;
                $('#digitalTime').text(h + ':' + String(m).padStart(2, '0') + ':' + String(s).padStart(2, '0'));
                $('#amPm').text(ampm);
                updateMonthCalendar(now);
            }

            function updateMonthCalendar(date) {
                const mNames = ['JANUARY', 'FEBRUARY', 'MARCH', 'APRIL', 'MAY', 'JUNE', 'JULY', 'AUGUST', 'SEPTEMBER', 'OCTOBER', 'NOVEMBER', 'DECEMBER'];
                const dNames = ['S', 'M', 'T', 'W', 'T', 'F', 'S'];
                const m = date.getMonth(),
                    y = date.getFullYear(),
                    today = date.getDate();
                $('#currentMonth').text(mNames[m] + ' ' + y);
                const firstDay = new Date(y, m, 1).getDay();
                const daysInMonth = new Date(y, m + 1, 0).getDate();
                let html = '<table class="table table-sm table-borderless mb-0" style="margin:0 auto;width:100%;"><thead><tr>';
                dNames.forEach(d => {
                    html += '<th class="text-center small text-muted p-1 pb-2">' + d + '</th>';
                });
                html += '</tr></thead><tbody><tr>';
                for (let i = 0; i < firstDay; i++) html += '<td class="p-1"></td>';
                for (let day = 1; day <= daysInMonth; day++) {
                    const cls = day === today ? 'bg-primary text-white rounded-circle' : '';
                    html += '<td class="text-center p-1"><span class="' + cls + '" style="display:inline-block;width:24px;height:24px;line-height:24px;border-radius:50%;">' + day + '</span></td>';
                    if ((day + firstDay) % 7 === 0 && day < daysInMonth) html += '<tr>';
                }
                html += '</tr></tbody></table>';
                $('#monthCalendar').html(html);
            }

            function startClock() {
                updateDateTime();
                setInterval(updateDateTime, 1000);
            }

            // ========== FETCH ITEM DATA ==========
            function fetchItemData(itemId) {
                return new Promise(function(resolve, reject) {
                    if (!itemId || isNaN(parseInt(itemId))) {
                        reject(new Error('Invalid item ID'));
                        return;
                    }
                    $.ajax({
                        url: 'api/get_item.php',
                        method: 'GET',
                        data: {
                            id: parseInt(itemId)
                        },
                        dataType: 'json',
                        timeout: 10000,
                        success: function(response) {
                            if (response && response.success === true && response.data) {
                                const data = response.data;
                                if (data.qr_code) {
                                    if (Array.isArray(data.qr_code)) data.qr_code = data.qr_code[0] || '';
                                    else if (typeof data.qr_code === 'object' && data.qr_code !== null)
                                        data.qr_code = data.qr_code.url || data.qr_code.data || data.qr_code.code || data.qr_code.qr_code || '';
                                    data.qr_code = String(data.qr_code || '');
                                }
                                Object.keys(data).forEach(key => {
                                    if (data[key] === 'undefined' || data[key] === undefined || data[key] === null) data[key] = '';
                                });
                                resolve(data);
                            } else {
                                reject(new Error(response?.message || 'Failed to load item'));
                            }
                        },
                        error: (xhr, status, error) => reject(new Error('Network error: ' + error))
                    });
                });
            }

            // ========== DROPDOWN LOADERS ==========
            function loadCategoriesDropdown() {
                return new Promise(resolve => {
                    $.ajax({
                        url: 'api/get_categories.php',
                        method: 'GET',
                        success: function(r) {
                            const s = $('#editCategory').empty().append('<option value="">Select Category</option>');
                            if (r.success && r.categories) r.categories.forEach(c => s.append('<option value="' + c.id + '">' + escapeHtml(c.name) + '</option>'));
                            resolve();
                        },
                        error: () => resolve()
                    });
                });
            }

            function loadDepartmentsDropdown() {
                return new Promise(resolve => {
                    $.ajax({
                        url: 'api/get_departments.php',
                        method: 'GET',
                        success: function(r) {
                            const s = $('#editDepartment').empty().append('<option value="">Select Department</option>');
                            if (r.success && r.departments) r.departments.forEach(d => s.append('<option value="' + d.id + '">' + escapeHtml(d.name) + '</option>'));
                            resolve();
                        },
                        error: () => resolve()
                    });
                });
            }

            function loadAccessoriesDropdown() {
                return new Promise(resolve => {
                    $.ajax({
                        url: 'api/get_accessories.php',
                        method: 'GET',
                        success: function(r) {
                            const s = $('#editAccessories').empty().append('<option value="">-- No Accessories --</option>');
                            if (r.success && r.accessories) r.accessories.forEach(a => s.append('<option value="' + a.id + '">' + escapeHtml(a.name) + '</option>'));
                            resolve();
                        },
                        error: () => resolve()
                    });
                });
            }

            // ========== ACCESSORY DISPLAY ==========
            function updateEditSelectedAccessories() {
                const selected = $('#editAccessories option:selected');
                const container = $('#editSelectedAccessories');
                if (selected.length === 0 || !selected.val()) {
                    container.html('<p class="text-muted small mb-0">None selected</p>');
                    return;
                }
                let html = '';
                selected.each(function() {
                    if ($(this).val()) html += '<span class="badge bg-info me-1 mb-1">' + escapeHtml($(this).text()) + '</span>';
                });
                container.html(html || '<p class="text-muted small mb-0">None selected</p>');
            }

            function updateSelectedAccessories() {
                const c = $('#selectedAccessories'),
                    selected = $('#accessories option:selected');
                if (selected.length === 0 || (selected.length === 1 && !selected.val())) {
                    c.html('<p class="text-muted mb-0">No accessories selected</p>');
                    return;
                }
                let html = '<div class="d-flex flex-wrap gap-1">';
                selected.each(function() {
                    if ($(this).val()) html += '<span class="badge bg-info cursor-pointer me-1 mb-1 accessory-badge" data-value="' + $(this).val() + '">' + escapeHtml($(this).text().split(' (')[0]) + '<i class="fas fa-times ms-1"></i></span>';
                });
                c.html(html + '</div>');
            }

            // ========== EDIT MODAL ==========
            function saveOriginalEditModalHtml() {
                originalEditModalHtml = $('#editItemModal .modal-body').html();
            }

            function resetEditModal() {
                delete window.currentEditItemData;
                if ($('#editItemForm').length) $('#editItemForm')[0].reset();
                resetEditImageSection();
                $('#editCurrentImage img').hide().attr('src', '');
                const ci = $('#editCurrentImage');
                if (ci.find('.no-image-text').length === 0) ci.append('<span class="no-image-text text-muted small">No image</span>');
                else ci.find('.no-image-text').show();
                $('#editSelectedAccessories').html('<p class="text-muted mb-0">No accessories selected</p>');
                $('#editQRCode').html('<div class="text-center text-muted"><i class="fas fa-qrcode fa-4x mb-2"></i><p class="small">QR code will appear here</p></div>');
            }

            function resetEditImageSection() {
                $('#editChangeImage').prop('checked', false);
                $('#editImageUploadSection, #editImagePreview, #applySimilarSection').hide();
                $('#editImagePreview img').attr('src', '');
                $('#editItemImage').val('');
                $('#editApplyToSimilar').prop('checked', false);
            }

            function setupEditFormHandlers(data) {
                $('#editChangeImage').off('change').on('change', function() {
                    $(this).is(':checked') ? $('#editImageUploadSection').slideDown() : ($('#editImageUploadSection').slideUp(), $('#editImagePreview').hide(), $('#editItemImage').val(''));
                });
                $('#editItemImage').off('change').on('change', function(e) {
                    const file = e.target.files[0];
                    if (!file) return;
                    if (file.size > 5 * 1024 * 1024) {
                        toastr.error('File size must be less than 5MB');
                        $(this).val('');
                        $('#editImagePreview').hide();
                        return;
                    }
                    if (!['image/jpeg', 'image/png', 'image/gif', 'image/webp'].includes(file.type)) {
                        toastr.error('Invalid image type (JPG, PNG, GIF, WebP only)');
                        $(this).val('');
                        return;
                    }
                    const reader = new FileReader();
                    reader.onload = e => {
                        $('#editImagePreview img').attr('src', e.target.result);
                        $('#editImagePreview').show();
                        $('#applySimilarSection').fadeIn();
                    };
                    reader.readAsDataURL(file);
                });
                $('#editAccessories').off('change').on('change', updateEditSelectedAccessories);
                $('#editRegenerateQRBtn').off('click').on('click', () => regenerateQRCode(data.id, data.item_name, data.serial_number, data.stock_location));
            }

            function populateEditItemModal(data, focusOnImage = false) {
                window.currentEditItemData = data;
                $('#editItemModalLabel').html('<i class="fas fa-edit me-2"></i>Edit: ' + escapeHtml(data.item_name || 'Item'));
                $('#editItemId').val(data.id || '');
                $('#editItemIdDisplay').text(data.id || '-');

                const ci = $('#editCurrentImage'),
                    ciImg = ci.find('img');
                if (data.image && data.image !== 'null' && data.image !== '') {
                    const imgUrl = getImageUrl(data.image);
                    ciImg.attr('src', imgUrl).show();
                    ci.find('.no-image-text').remove();
                } else {
                    ciImg.hide().attr('src', '');
                    if (ci.find('.no-image-text').length === 0) ci.append('<span class="no-image-text text-muted small">No image</span>');
                    else ci.find('.no-image-text').show();
                }

                $('#editChangeImage').prop('checked', false);
                $('#editImageUploadSection, #editImagePreview').hide();
                $('#editItemImage').val('');
                $('#editItemName').val(data.item_name || '');
                $('#editSerialNumber').val(data.serial_number || '');
                $('#editStatus').val(data.status || 'available');
                $('#editCondition').val(data.condition || 'good');
                $('#editBrand').val(data.brand || '');
                $('#editModel').val(data.model || '');
                $('#editStockLocation').val(data.stock_location || '');
                $('#editStorageLocation').val(data.storage_location || '');
                $('#editCreatedAt').text(formatDateTime(data.created_at) || 'N/A');
                $('#editUpdatedAt').text(formatDateTime(data.updated_at) || 'N/A');

                // QR code display - check for existing QR
                const qrPath = getQRCodePath(data.id);
                if (qrPath) {
                    updateEditQRCode(qrPath);
                } else if (data.qr_code && data.qr_code !== '' && data.qr_code !== 'pending' && data.qr_code !== 'null') {
                    updateEditQRCode(data.qr_code);
                } else {
                    $('#editQRCode').html('<div class="text-center text-muted">' +
                        '<i class="fas fa-qrcode fa-4x mb-2"></i>' +
                        '<p class="small">No QR Code available</p>' +
                        '<button class="btn btn-sm btn-primary" id="generateQRFromEditBtn">' +
                        '<i class="fas fa-sync-alt me-1"></i> Generate QR Code</button>' +
                        '</div>');
                    $('#generateQRFromEditBtn').off('click').on('click', () => {
                        const id = $('#editItemId').val();
                        const name = $('#editItemName').val();
                        const serial = $('#editSerialNumber').val();
                        const location = $('#editStockLocation').val();
                        if (id) regenerateQRCode(id, name, serial, location);
                    });
                }

                loadCategoriesDropdown().then(() => {
                    const id = data.category_id || data.category;
                    if (id && parseInt(id) > 0) $('#editCategory').val(id);
                });
                loadDepartmentsDropdown().then(() => {
                    const id = data.department_id || data.department;
                    if (id && parseInt(id) > 0) $('#editDepartment').val(id);
                });
                loadAccessoriesDropdown().then(() => {
                    if (data.accessory_ids && data.accessory_ids.length) {
                        $('#editAccessories').val(data.accessory_ids.filter(id => parseInt(id) > 0));
                    }
                    updateEditSelectedAccessories();
                });

                setupEditFormHandlers(data);

                if (focusOnImage) {
                    $('#editChangeImage').prop('checked', true);
                    $('#editImageUploadSection').slideDown();
                    $('html,body').animate({
                        scrollTop: $('#editImageUploadSection').offset().top - 100
                    }, 500);
                    setTimeout(() => {
                        $('#editImageUploadSection').css('border', '2px solid #ffc107').addClass('bg-light');
                        setTimeout(() => $('#editImageUploadSection').css('border', '').removeClass('bg-light'), 3000);
                    }, 100);
                    $('#editItemImage').focus();
                    toastr.info('Please select an image to upload', 'Add Image', {
                        timeOut: 5000
                    });
                }
            }

            function getQRCodePath(itemId) {
                // Check if QR code file exists via AJAX
                const qrUrl = BASE_URL.replace(/\/$/, '') + '/qrcodes/qr_' + itemId + '.png';
                let exists = false;
                $.ajax({
                    url: qrUrl,
                    method: 'HEAD',
                    async: false,
                    success: function() {
                        exists = true;
                    }
                });
                return exists ? qrUrl : null;
            }

            $(document).on('click', '#editRegenerateQRBtn', function(e) {
                e.preventDefault();
                const itemId = $('#editItemId').val();
                const itemName = $('#editItemName').val();
                const serialNumber = $('#editSerialNumber').val();
                const stockLocation = $('#editStockLocation').val();
                if (itemId) {
                    regenerateQRCode(itemId, itemName, serialNumber, stockLocation);
                } else {
                    toastr.error('Cannot regenerate: No item loaded');
                }
            });

            function openEditItemModal(itemId, focusOnImage = false) {
                if (!itemId) {
                    toastr.error('Invalid item ID');
                    return;
                }
                openEditItemModalInternal(itemId, focusOnImage);
            }

            function openEditItemModalInternal(itemId, focusOnImage = false) {
                const modalEl = document.getElementById('editItemModal');
                if (!modalEl) return;

                $('#editItemModal .modal-body').html('<div class="text-center py-5"><div class="spinner-border text-warning mb-3" role="status"></div><p class="text-muted">Loading asset details...</p></div>');

                let modal = bootstrap.Modal.getInstance(modalEl);
                if (!modal) modal = new bootstrap.Modal(modalEl);
                modal.show();
                fetchItemData(itemId).then(data => {
                    if (!data) {
                        toastr.error('No data received');
                        return;
                    }
                    if (originalEditModalHtml) $('#editItemModal .modal-body').html(originalEditModalHtml);
                    populateEditItemModal(data, focusOnImage);
                }).catch(error => {
                    console.error('Failed to load item:', error);
                    $('#editItemModal .modal-body').html('<div class="text-center py-5"><i class="fas fa-exclamation-triangle text-danger fa-3x mb-3"></i><h5>Error Loading Item</h5><p class="text-muted">' + escapeHtml(error.message) + '</p><button class="btn btn-primary" onclick="openEditItemModal(' + itemId + ')"><i class="fas fa-sync-alt me-1"></i> Retry</button> <button class="btn btn-secondary" data-bs-dismiss="modal">Close</button></div>');
                });
            }

            // ========== QR CODE FUNCTIONS - IMPROVED FOR qr_generator.php ==========
            function updateEditQRCode(qrCode) {
                const c = $('#editQRCode');
                if (!c.length) return;

                if (qrCode && qrCode !== '' && qrCode !== 'null' && qrCode !== 'undefined' && qrCode !== 'pending') {
                    let qrUrl = qrCode;
                    if (!qrUrl.startsWith('http') && !qrUrl.startsWith('data:image')) {
                        if (qrUrl.startsWith('/')) {
                            qrUrl = window.location.origin + qrUrl;
                        } else {
                            qrUrl = BASE_URL.replace(/\/$/, '') + '/' + qrUrl.replace(/^\.?\//, '');
                        }
                    }

                    c.html('<div class="text-center">' +
                        '<img src="' + qrUrl + '?t=' + Date.now() + '" alt="QR Code" ' +
                        'class="img-fluid" style="max-width:150px;border:1px solid #ddd;border-radius:5px;padding:5px;cursor:pointer;" ' +
                        'onerror="this.onerror=null;this.src=\'' + BASE_URL + '/qrcodes/qr_' + $('#editItemId').val() + '.png?t=' + Date.now() + '\'" ' +
                        'onclick="window.open(\'' + escapeHtml(qrUrl) + '\',\'_blank\')">' +
                        '<div class="mt-2">' +
                        '<button class="btn btn-sm btn-success me-1" onclick="downloadSingleQRCode(\'' + escapeHtml(qrUrl) + '\',\'' + escapeHtml($('#editItemName').val() || 'item') + '\',\'' + escapeHtml($('#editSerialNumber').val() || '') + '\')"><i class="fas fa-download me-1"></i> Download</button>' +
                        '<button class="btn btn-sm btn-primary" id="editRegenerateQRBtn"><i class="fas fa-sync-alt me-1"></i> Regenerate</button>' +
                        '</div>' +
                        '<div class="mt-1"><small class="text-muted">Click QR to enlarge</small></div>' +
                        '</div>');
                    $('#editRegenerateQRBtn').off('click').on('click', () => {
                        const id = $('#editItemId').val();
                        const nm = $('#editItemName').val();
                        const sn = $('#editSerialNumber').val();
                        const loc = $('#editStockLocation').val();
                        if (id) regenerateQRCode(id, nm, sn, loc);
                    });
                } else {
                    c.html('<div class="text-center text-muted">' +
                        '<i class="fas fa-qrcode fa-4x mb-2"></i>' +
                        '<p class="small">No QR Code available</p>' +
                        '<button class="btn btn-sm btn-primary" id="generateQRFromEditBtn">' +
                        '<i class="fas fa-sync-alt me-1"></i> Generate QR Code</button>' +
                        '</div>');
                    $('#generateQRFromEditBtn').off('click').on('click', function() {
                        const itemId = $('#editItemId').val();
                        const itemName = $('#editItemName').val();
                        const serialNumber = $('#editSerialNumber').val();
                        const stockLocation = $('#editStockLocation').val();
                        if (itemId) regenerateQRCode(itemId, itemName, serialNumber, stockLocation);
                        else toastr.error('Cannot generate QR: No item ID found');
                    });
                }
            }

            function regenerateQRCode(itemId, itemName, serialNumber, stockLocation) {
                if (!itemId) {
                    toastr.error('Invalid item ID');
                    return;
                }

                if (!confirm('Regenerate QR code for "' + itemName + '"? This will replace the existing QR code.')) {
                    return;
                }

                const btn = $('#editRegenerateQRBtn');
                const originalText = btn.html();
                if (btn.length) {
                    btn.html('<span class="spinner-border spinner-border-sm me-1"></span> Generating...').prop('disabled', true);
                }

                $('#editQRCode').html('<div class="text-center">' +
                    '<div class="spinner-border text-primary" role="status"></div>' +
                    '<div class="mt-2 small">Generating QR Code...</div>' +
                    '</div>');

                // Send all required parameters for QR generation
                $.ajax({
                    url: 'api/generate_qr.php',
                    method: 'POST',
                    data: {
                        item_id: itemId,
                        item_name: itemName || '',
                        serial_number: serialNumber || '',
                        stock_location: stockLocation || '',
                        regenerate: 1
                    },
                    dataType: 'json',
                    timeout: 30000,
                    success: function(response) {
                        if (response.success) {
                            toastr.success('QR code generated successfully');

                            let qrUrl = response.qr_code || response.data?.qr_code;
                            if (qrUrl) {
                                updateEditQRCode(qrUrl);
                                if (window.currentEditItemData) {
                                    window.currentEditItemData.qr_code = qrUrl;
                                }
                            } else {
                                // Try the standard QR path
                                const standardUrl = BASE_URL.replace(/\/$/, '') + '/qrcodes/qr_' + itemId + '.png';
                                updateEditQRCode(standardUrl);
                            }
                        } else {
                            toastr.error(response.message || 'Failed to generate QR code');
                            // Try standard path as fallback
                            const standardUrl = BASE_URL.replace(/\/$/, '') + '/qrcodes/qr_' + itemId + '.png';
                            updateEditQRCode(standardUrl);
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('QR generation error:', error);
                        let errorMsg = 'Error generating QR code';
                        try {
                            const response = JSON.parse(xhr.responseText);
                            errorMsg = response.message || errorMsg;
                        } catch (e) {}
                        toastr.error(errorMsg);

                        // Try standard path as fallback
                        const standardUrl = BASE_URL.replace(/\/$/, '') + '/qrcodes/qr_' + itemId + '.png';
                        updateEditQRCode(standardUrl);
                    },
                    complete: function() {
                        if (btn.length) {
                            btn.html(originalText).prop('disabled', false);
                        }
                    }
                });
            }

            // ========== VIEW MODAL ==========
            function openEditItemModalFromNoImage() {
                const itemId = $('#viewItemId').text();
                if (itemId) {
                    const vm = bootstrap.Modal.getInstance(document.getElementById('viewItemModal'));
                    if (vm) vm.hide();
                    setTimeout(() => openEditItemModal(itemId, true), 350);
                }
            }

            function openViewItemModal(itemId) {
                if (!itemId) {
                    toastr.error('Invalid item ID');
                    return;
                }
                openViewItemModalInternal(itemId);
            }

            function openViewItemModalInternal(itemId) {
                const el = document.getElementById('viewItemModal');
                if (!el) return;

                // Immediate loading feedback
                ['#viewItemName', '#viewSerialNumber', '#viewCategory', '#viewBrand', '#viewModel',
                    '#viewStockLocation', '#viewStorageLocation', '#viewDepartment', '#viewCreatedAt', '#viewUpdatedAt', '#viewCondition'
                ].forEach(id => $(id).text('Loading...'));
                $('#viewQuantity').text('-');
                $('#viewItemStatusBadge').text('Loading...').removeClass().addClass('badge bg-secondary');
                $('#viewItemImage').hide();
                $('#viewNoImage').hide();
                $('#viewQRCode').html('<div class="text-center py-3"><div class="spinner-border text-primary" role="status"></div><div class="mt-2">Loading data...</div></div>');

                let modal = bootstrap.Modal.getInstance(el);
                if (!modal) modal = new bootstrap.Modal(el);
                modal.show();

                fetchItemData(itemId).then(data => {
                    data ? populateViewItemModal(data) : showViewItemError(itemId, new Error('No data received'));
                }).catch(error => {
                    console.error('Failed to load item:', error);
                    showViewItemError(itemId, error);
                });
            }

            function populateViewItemModal(data) {
                $('#viewItemModalLabel').html('<i class="fas fa-eye me-2"></i>View: ' + escapeHtml(data.item_name || 'Item'));
                $('#viewItemId').text(data.id || '');
                if (data.image && data.image !== 'null') {
                    $('#viewItemImage img').attr('src', getImageUrl(data.image)).on('error', function() {
                        $(this).hide();
                        $('#viewItemImage').hide();
                        $('#viewNoImage').show();
                        $('#noImageItemId').text(data.id || 'N/A');
                        $('#noImageItemName').text(data.item_name || '');
                        $('#noImageItemSerial').text(data.serial_number ? 'Serial: ' + data.serial_number : '');
                    }).show();
                    $('#viewItemImage').show();
                    $('#viewNoImage').hide();
                } else {
                    $('#viewItemImage').hide();
                    $('#viewNoImage').show();
                    $('#noImageItemId').text(data.id || 'N/A');
                    $('#noImageItemName').text(data.item_name || '');
                    $('#noImageItemSerial').text(data.serial_number ? 'Serial: ' + data.serial_number : '');
                }

                // Check for QR code - try standard path first
                let qrUrl = null;
                const standardPath = BASE_URL.replace(/\/$/, '') + '/qrcodes/qr_' + data.id + '.png';

                if (data.qr_code && data.qr_code !== '' && data.qr_code !== 'pending' && data.qr_code !== 'null') {
                    qrUrl = data.qr_code.startsWith('http') ? data.qr_code :
                        (data.qr_code.startsWith('/') ? window.location.origin + data.qr_code :
                            BASE_URL + data.qr_code.replace(/^\.?\//, ''));
                }

                const qrC = $('#viewQRCode');
                const displayQR = function(url) {
                    if (url) {
                        qrC.html('<div class="text-center"><img src="' + url + '?t=' + Date.now() + '" alt="QR Code" style="width:150px;height:150px;" class="img-fluid border rounded" onerror="this.onerror=null;this.src=\'' + standardPath + '?t=' + Date.now() + '\'"><div class="mt-2 small">' + escapeHtml(data.item_name || 'QR Code') + '</div><div class="mt-2"><button class="btn btn-sm btn-success me-1" onclick="downloadSingleQRCode(\'' + escapeHtml(url) + '\',\'' + escapeHtml(data.item_name || '') + '\',\'' + escapeHtml(data.serial_number || '') + '\')"><i class="fas fa-download me-1"></i> Download</button><button class="btn btn-sm btn-primary" onclick="regenerateQRCodeForView()"><i class="fas fa-sync-alt me-1"></i> Regenerate</button></div></div>');
                        $('#viewDownloadQRBtn, #viewPrintQRBtn').off('click');
                    } else {
                        qrC.html('<div class="text-center"><i class="fas fa-qrcode fa-3x text-muted mb-2"></i><div class="small text-muted mb-2">No QR Code Available</div><button class="btn btn-sm btn-primary" onclick="generateQRCodeForItemFromView()"><i class="fas fa-sync-alt me-1"></i> Generate QR Code</button></div>');
                        $('#viewDownloadQRBtn, #viewPrintQRBtn').off('click').on('click', () => toastr.warning('No QR code available'));
                    }
                };

                // Test if standard path works
                $.ajax({
                    url: standardPath,
                    method: 'HEAD',
                    success: function() {
                        displayQR(standardPath);
                    },
                    error: function() {
                        displayQR(qrUrl);
                    }
                });

                $('#viewItemName').text(data.item_name || 'N/A');
                $('#viewSerialNumber').text(data.serial_number || 'N/A');
                $('#viewCategory').text(data.category_name || data.category || 'N/A');
                $('#viewBrand').text(data.brand || 'N/A');
                $('#viewModel').text(data.model || 'N/A');
                $('#viewQuantity').text(data.quantity || 1);
                $('#viewStockLocation').text(data.stock_location || 'Not Set');
                $('#viewStorageLocation').text(data.storage_location || 'Not Set');
                $('#viewDepartment').text(data.department_name || data.department || 'Not Set');
                $('#viewCreatedAt').text(formatDateTime(data.created_at) || 'N/A');
                $('#viewUpdatedAt').text(formatDateTime(data.updated_at) || 'N/A');
<<<<<<< HEAD
=======

                // Show checkout info if in_use and we have the data
                const statusNormalized = (data.status || '').toString().toLowerCase().trim().replace(/ /g, '_');
                if (statusNormalized === 'in_use' && data.checked_out_by) {
                    $('#viewCheckedOutBy').text(data.checked_out_by);
                    
                    // Format date nicely if possible
                    let dateStr = data.checked_out_at || 'Unknown';
                    if (dateStr !== 'Unknown') {
                        try {
                            const d = new Date(dateStr);
                            if (!isNaN(d.getTime())) {
                                dateStr = d.toLocaleString();
                            }
                        } catch(e) {}
                    }
                    $('#viewCheckedOutAt').text(dateStr);
                    $('.checkout-info-container').show();
                } else {
                    $('.checkout-info-container').hide();
                }
>>>>>>> addf346 (Latest Upload - Events cards, OverView, Items status..)
                const condMap = {
                    new: 'Excellent',
                    excellent: 'Excellent',
                    good: 'Good',
                    fair: 'Fair',
                    poor: 'Poor',
                    damaged: 'Damaged',
                    broken: 'Damaged'
                };
<<<<<<< HEAD
                $('#viewCondition').text(condMap[(data.condition || 'good').toLowerCase()] || data.condition || 'Unknown');
=======
                const condKey = (data.condition || 'good').toLowerCase();
                $('#viewCondition').text(condMap[condKey] || data.condition || 'Unknown');
                
                const $condCard = $('#viewCondition').closest('.info-item-card');
                const $condIcon = $condCard.find('.info-icon');
                const $condLabel = $condCard.find('label');
                if (condKey === 'damaged' || condKey === 'broken') {
                    $condCard.removeClass('bg-white border').addClass('bg-danger text-white border-danger');
                    $condIcon.removeClass('bg-soft-primary').addClass('bg-white text-danger');
                    $condLabel.addClass('text-white-50');
                } else {
                    $condCard.removeClass('bg-danger text-white border-danger').addClass('bg-white border');
                    $condIcon.removeClass('bg-white text-danger').addClass('bg-soft-primary');
                    $condLabel.removeClass('text-white-50');
                }
>>>>>>> addf346 (Latest Upload - Events cards, OverView, Items status..)
                const stCls = {
                    available: 'bg-success',
                    in_use: 'bg-primary',
                    maintenance: 'bg-warning',
                    reserved: 'bg-info',
                    disposed: 'bg-danger',
                    lost: 'bg-dark'
                };
                const stTxt = {
                    available: 'Available',
                    in_use: 'In Use',
                    maintenance: 'Maintenance',
                    reserved: 'Reserved',
                    disposed: 'Disposed',
                    lost: 'Lost'
                };
                const s = (data.status || 'available').toLowerCase();
                $('#viewItemStatusBadge').removeClass().addClass('badge ' + (stCls[s] || 'bg-secondary')).text(stTxt[s] || data.status || 'Unknown');
                if (data.accessories && Array.isArray(data.accessories) && data.accessories.length > 0) {
                    $('#viewAccessoriesList').html('<div class="d-flex flex-wrap gap-2">' + data.accessories.map(a => a ? '<span class="badge bg-soft-primary text-primary border-0 p-2"><i class="fas fa-check-circle me-1 small"></i>' + escapeHtml(a) + '</span>' : '').join('') + '</div>');
                } else {
                    $('#viewAccessoriesList').html('<div class="text-center py-2 text-muted small"><i class="fas fa-info-circle me-2"></i>No accessories assigned to this asset</div>');
                }
                window.currentViewItemData = data;
            }

            function regenerateQRCodeForView() {
                const itemId = $('#viewItemId').text();
                const itemName = $('#viewItemName').text();
                const serialNumber = $('#viewSerialNumber').text();
                const stockLocation = $('#viewStockLocation').text();
                if (itemId && itemId !== 'Loading...') {
                    regenerateQRCode(itemId, itemName, serialNumber, stockLocation);
                    setTimeout(() => {
                        fetchItemData(itemId).then(data => {
                            if (data) populateViewItemModal(data);
                        }).catch(console.warn);
                    }, 1500);
                } else {
                    toastr.error('Cannot regenerate: No item loaded');
                }
            }

            function showViewItemError(itemId, error) {
                ['#viewItemName', '#viewSerialNumber', '#viewCategory', '#viewBrand', '#viewModel',
                    '#viewStockLocation', '#viewStorageLocation', '#viewDepartment', '#viewCreatedAt', '#viewUpdatedAt'
                ].forEach(id => $(id).text('N/A'));
                $('#viewQuantity').text('-');
                $('#viewCondition').text('Unknown');
                $('#viewItemStatusBadge').text('Unknown').removeClass().addClass('badge bg-secondary');
                $('#viewQRCode').html('<div class="text-center py-3"><i class="fas fa-exclamation-triangle text-danger fa-3x mb-2"></i><div class="text-danger">Failed to load</div><button class="btn btn-sm btn-primary mt-2" onclick="openViewItemModal(' + itemId + ')"><i class="fas fa-sync-alt me-1"></i> Retry</button></div>');
                toastr.error('Failed to load item details for viewing');
            }

            function printQRCode(qrUrl, itemName, serialNumber) {
                if (!qrUrl) {
                    toastr.error('No QR code available to print');
                    return;
                }
                const w = window.open('', '_blank');
                w.document.write('<!DOCTYPE html><html><head><title>QR Code - ' + escapeHtml(itemName) + '</title><style>body{font-family:Arial,sans-serif;text-align:center;padding:20px}.qr-container{max-width:400px;margin:0 auto}img{max-width:300px;height:auto}@media print{.no-print{display:none}}</style></head><body><div class="qr-container"><h2>' + escapeHtml(itemName || 'Item QR Code') + '</h2>' + (serialNumber ? '<p><strong>Serial:</strong> ' + escapeHtml(serialNumber) + '</p>' : '') + '<img src="' + qrUrl + '" alt="QR Code"><p class="no-print" style="margin-top:20px;"><button onclick="window.print()">Print QR Code</button> <button onclick="window.close()">Close</button></p></div></body></html>');
                w.document.close();
            }

            function generateQRCodeForItemFromView() {
                const itemId = $('#viewItemId').text(),
                    itemName = $('#viewItemName').text(),
                    serialNumber = $('#viewSerialNumber').text(),
                    stockLocation = $('#viewStockLocation').text();
                if (!itemId || itemId === 'Loading...') {
                    toastr.error('Invalid item ID');
                    return;
                }
                if (!confirm('Generate QR Code for "' + itemName + '"?')) return;
                $('#viewQRCode').html('<div class="text-center py-3"><div class="spinner-border text-primary" role="status"></div><div class="mt-2">Generating QR Code...</div></div>');
                $.ajax({
                    url: 'api/generate_qr.php',
                    method: 'POST',
                    data: {
                        item_id: itemId,
                        item_name: itemName,
                        serial_number: serialNumber,
                        stock_location: stockLocation
                    },
                    dataType: 'json',
                    success: function(r) {
                        if (r.success) {
                            toastr.success('QR Code generated successfully!');
                            fetchItemData(itemId).then(data => {
                                if (data && data.qr_code) updateViewQRCode(data.qr_code, data.item_name, data.serial_number);
                                else location.reload();
                            }).catch(() => location.reload());
                        } else {
                            toastr.error(r.message || 'Failed to generate QR code');
                            location.reload();
                        }
                    },
                    error: () => {
                        toastr.error('Error generating QR code');
                        $('#viewQRCode').html('<div class="text-center"><i class="fas fa-exclamation-triangle text-danger fa-3x mb-2"></i><div class="small text-danger">Generation failed</div><button class="btn btn-sm btn-primary mt-2" onclick="generateQRCodeForItemFromView()">Retry</button></div>');
                    }
                });
            }

            function updateViewQRCode(qrCode, itemName, serialNumber) {
                const c = $('#viewQRCode').empty();
                if (qrCode && qrCode !== '' && qrCode !== 'pending') {
                    let qrUrl = qrCode;
                    if (!qrUrl.startsWith('http')) {
                        qrUrl = qrUrl.startsWith('/') ? window.location.origin + qrUrl : BASE_URL + qrUrl.replace(/^\.?\//, '');
                    }
                    c.html('<div class="text-center"><img src="' + qrUrl + '" alt="QR Code" style="width:150px;height:150px;" class="img-fluid border rounded"><div class="mt-2 small">' + escapeHtml(itemName || 'QR Code') + '</div><div class="mt-2"><button class="btn btn-sm btn-success me-1" onclick="downloadSingleQRCode(\'' + escapeHtml(qrUrl) + '\',\'' + escapeHtml(itemName || '') + '\',\'' + escapeHtml(serialNumber || '') + '\')"><i class="fas fa-download me-1"></i> Download</button><button class="btn btn-sm btn-primary" onclick="regenerateQRCodeForView()"><i class="fas fa-sync-alt me-1"></i> Regenerate</button></div></div>');
                } else {
                    c.html('<div class="text-center"><i class="fas fa-qrcode fa-3x text-muted mb-2"></i><div class="small text-muted">No QR Code</div><button class="btn btn-sm btn-primary mt-2" onclick="generateQRCodeForItemFromView()"><i class="fas fa-sync-alt me-1"></i> Generate QR Code</button></div>');
                }
            }

            // ========== QUICK ACTIONS MODAL TRIGGER ==========
            $(document).on('click', '.quick-view-btn', function(e) {
                e.preventDefault();
                e.stopPropagation();
                const itemId = $(this).data('item-id');
                if (itemId) {
                    openQuickViewModal(itemId);
                } else {
                    toastr.error('Invalid item ID');
                }
            });


            function populateQuickViewModal(data) {
                window.currentItemData = data;
                const status = (data.status || 'available').toLowerCase();
                const condition = (data.condition || 'good').toLowerCase();

                // Header
                $('#quickActionsModal .modal-header').html(`
                    <div class="d-flex align-items-center w-100">
                        <div class="bg-white bg-opacity-20 rounded-3 p-2 me-3">
                            <i class="fas fa-bolt text-warning"></i>
                        </div>
                        <div>
                            <h5 class="modal-title text-white mb-0">Quick Actions: ${escapeHtml(data.item_name)}</h5>
                            <div class="text-white-50 small">Asset ID: #${data.id} • ${escapeHtml(data.serial_number)}</div>
                        </div>
                        <button type="button" class="btn-close btn-close-white ms-auto" data-bs-dismiss="modal"></button>
                    </div>
                `);

                // Body
                let html = `
                    <div class="row g-4">
                        <div class="col-md-8">
                            <div class="bg-light-soft rounded-4 p-4 h-100 border shadow-sm">
                                <label class="text-overline mb-3 d-block">Asset Overview</label>
                                <div class="info-grid-premium">
                                    <div class="info-item-card bg-white border">
                                        <div class="info-icon bg-soft-primary"><i class="fas fa-tag"></i></div>
                                        <div class="info-content">
                                            <label>Category</label>
                                            <div class="info-value">${escapeHtml(data.category_name || data.category || 'N/A')}</div>
                                        </div>
                                    </div>
                                    <div class="info-item-card bg-white border">
                                        <div class="info-icon bg-soft-success"><i class="fas fa-map-marker-alt"></i></div>
                                        <div class="info-content">
                                            <label>Location</label>
                                            <div class="info-value text-truncate">${escapeHtml(data.stock_location || 'N/A')}</div>
                                        </div>
                                    </div>
                                    <div class="info-item-card bg-white border">
                                        <div class="info-icon bg-soft-primary"><i class="fas fa-info-circle"></i></div>
                                        <div class="info-content">
                                            <label>Status</label>
                                            <div class="info-value"><span class="status-pill-mini ${status === 'available' ? 'bg-soft-success text-success' : 'bg-soft-primary text-primary'}">${status.toUpperCase()}</span></div>
                                        </div>
                                    </div>
                                    <div class="info-item-card ${condition === 'damaged' || condition === 'broken' ? 'bg-danger text-white border-danger' : 'bg-white border'}">
                                        <div class="info-icon ${condition === 'damaged' || condition === 'broken' ? 'bg-white text-danger' : 'bg-soft-primary'}"><i class="fas fa-heartbeat"></i></div>
                                        <div class="info-content">
                                            <label class="${condition === 'damaged' || condition === 'broken' ? 'text-white-50' : ''}">Condition</label>
                                            <div class="info-value">${condition.toUpperCase()}</div>
                                        </div>
                                    </div>
                                </div>

                                <div class="mt-4 p-3 bg-white rounded-4 border shadow-sm">
                                    <label class="text-overline mb-2 d-block">Attached Accessories</label>
                                    <div class="d-flex flex-wrap gap-2">
                                        ${data.accessories && Array.isArray(data.accessories) && data.accessories.length > 0 ? data.accessories.map(a => `<span class="badge bg-soft-primary text-primary border-0 p-2">${escapeHtml(a)}</span>`).join('') : (data.accessory_names ? data.accessory_names.split(',').map(a => `<span class="badge bg-soft-primary text-primary border-0 p-2">${escapeHtml(a.trim())}</span>`).join('') : '<span class="text-muted small">No accessories attached</span>')}
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="d-flex flex-column gap-3 h-100">
                                <label class="text-overline">Management Actions</label>
                                <a href="#" class="action-card-premium view" id="qvViewBtn" style="text-decoration: none;">
                                    <div class="action-icon"><i class="fas fa-eye"></i></div>
                                    <div>
                                        <div class="fw-bold text-dark">Full View</div>
                                        <div class="text-muted small">Detailed specifications & history</div>
                                    </div>
                                </a>
                                <a href="#" class="action-card-premium edit" id="qvEditBtn" style="text-decoration: none;">
                                    <div class="action-icon"><i class="fas fa-edit"></i></div>
                                    <div>
                                        <div class="fw-bold text-dark">Quick Edit</div>
                                        <div class="text-muted small">Update status, location or info</div>
                                    </div>
                                </a>
                                
                                <div class="mt-auto p-3 bg-light rounded-4 border text-center shadow-sm">
                                    <div id="qvQRCode" class="mb-2"></div>
                                    <div class="d-flex justify-content-center gap-2 mt-2">
                                        <button class="btn btn-sm btn-light border rounded-pill px-3" onclick="downloadSingleQRCode('${BASE_URL}qrcodes/qr_${data.id}.png', '${escapeHtml(data.item_name)}', '${escapeHtml(data.serial_number)}')">
                                            <i class="fas fa-download me-1"></i> QR
                                        </button>
                                        <button class="btn btn-sm btn-light border rounded-pill px-3" onclick="regenerateQRCodeForQuickView(${data.id}, '${escapeHtml(data.item_name)}', '${escapeHtml(data.serial_number)}', '${escapeHtml(data.stock_location)}')">
                                            <i class="fas fa-sync-alt me-1"></i> Reset
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                `;

                $('#quickActionsModal .modal-body').html(html);

                // Standard QR code path using BASE_URL
                const qrUrl = BASE_URL + 'qrcodes/qr_' + data.id + '.png';
                $('#qvQRCode').html(`<img src="${qrUrl}?t=${Date.now()}" style="width:110px; height:110px; padding:8px; background:#fff; border:1px solid #eee; border-radius:12px;" onerror="this.src='assets/img/qr_placeholder.png'">`);

                // Action Handlers
                $('#qvViewBtn').off('click').on('click', e => {
                    e.preventDefault();
                    const qm = bootstrap.Modal.getInstance(document.getElementById('quickActionsModal'));
                    if (qm) qm.hide();
                    setTimeout(() => openViewItemModal(data.id), 350);
                });
                $('#qvEditBtn').off('click').on('click', e => {
                    e.preventDefault();
                    const qm = bootstrap.Modal.getInstance(document.getElementById('quickActionsModal'));
                    if (qm) qm.hide();
                    setTimeout(() => openEditItemModal(data.id), 350);
                });
            }

            function regenerateQRCodeForQuickView(itemId, itemName, serialNumber, stockLocation) {
                if (!itemId) {
                    toastr.error('Invalid item ID');
                    return;
                }
                const qrC = $('#qvQRCode');
                qrC.html('<div class="text-center"><div class="spinner-border text-primary" role="status"></div><div class="mt-2 small">Generating QR Code...</div></div>');
                $.ajax({
                    url: 'api/generate_qr.php',
                    method: 'POST',
                    data: {
                        item_id: itemId,
                        item_name: itemName || '',
                        serial_number: serialNumber || '',
                        stock_location: stockLocation || ''
                    },
                    dataType: 'json',
                    timeout: 30000,
                    success: function(r) {
                        if (r.success) {
                            toastr.success('QR Code generated successfully!');
                            fetchItemData(itemId).then(d => {
                                if (d) populateQuickViewModal(d);
                                else setTimeout(() => location.reload(), 1500);
                            }).catch(() => setTimeout(() => location.reload(), 1500));
                        } else {
                            toastr.error(r.message || 'Failed to generate QR code');
                            const standardUrl = BASE_URL.replace(/\/$/, '') + '/qrcodes/qr_' + itemId + '.png';
                            qrC.html('<div class="text-center"><img src="' + standardUrl + '?t=' + Date.now() + '" alt="QR Code" style="width:120px;height:120px;" onerror="this.parentElement.innerHTML=\'<div class=\\\'text-center\\\'><i class=\\\'fas fa-exclamation-triangle text-danger fa-3x mb-2\\\'></i><div class=\\\'small text-danger\\\'>' + escapeHtml(r.message || 'Generation failed') + '</div><button class=\\\'btn btn-sm btn-primary mt-2\\\' onclick=\\\'regenerateQRCodeForQuickView(' + itemId + ',\\\'' + escapeHtml(itemName) + '\\\',\\\'' + escapeHtml(serialNumber) + '\\\',\\\'' + escapeHtml(stockLocation) + '\\\')\\\'>Try Again</button></div>\'"><div class="mt-2 small">QR Code</div></div>');
                        }
                    },
                    error: function() {
                        toastr.error('Error generating QR code');
                        const standardUrl = BASE_URL.replace(/\/$/, '') + '/qrcodes/qr_' + itemId + '.png';
                        qrC.html('<div class="text-center"><img src="' + standardUrl + '?t=' + Date.now() + '" alt="QR Code" style="width:120px;height:120px;" onerror="this.parentElement.innerHTML=\'<div class=\\\'text-center\\\'><i class=\\\'fas fa-exclamation-triangle text-danger fa-3x mb-2\\\'></i><div class=\\\'small text-danger\\\'>Network error</div><button class=\\\'btn btn-sm btn-primary mt-2\\\' onclick=\\\'regenerateQRCodeForQuickView(' + itemId + ',\\\'' + escapeHtml(itemName) + '\\\',\\\'' + escapeHtml(serialNumber) + '\\\',\\\'' + escapeHtml(stockLocation) + '\\\')\\\'>Retry</button></div>\'"><div class="mt-2 small">QR Code</div></div>');
                    }
                });
            }

            function quickAddImage(itemId) {
                const m = bootstrap.Modal.getInstance(document.getElementById('quickActionsModal'));
                if (m) m.hide();
                setTimeout(() => openEditItemModal(itemId, true), 350);
            }

            function openQuickViewModal(itemId) {
                if (!itemId) return;
                fetchItemData(itemId).then(data => {
                    if (data) populateQuickViewModal(data);
                    const modalEl = document.getElementById('quickActionsModal');
                    const existing = bootstrap.Modal.getInstance(modalEl);
                    if (existing) existing.dispose();
                    new bootstrap.Modal(modalEl).show();
                }).catch(err => {
                    console.error('Failed to open quick view:', err);
                    toastr.error('Failed to load item details');
                });
            }

            // ========== EDIT FORM SUBMIT ==========
            function submitEditItemForm() {
                const itemName = $('#editItemName').val().trim();
                const serialNumber = $('#editSerialNumber').val().trim();
                const category = $('#editCategory').val();
                if (!itemName || !serialNumber || !category) {
                    toastr.error('Please fill all required fields (Item Name, Serial Number, Category)');
                    return;
                }
                const formData = new FormData();
                formData.append('id', $('#editItemId').val());
                formData.append('item_name', itemName);
                formData.append('serial_number', serialNumber);
                formData.append('category', category);
                formData.append('status', $('#editStatus').val());
                formData.append('condition', $('#editCondition').val());
                formData.append('stock_location', $('#editStockLocation').val());
                formData.append('storage_location', $('#editStorageLocation').val());
                formData.append('department', $('#editDepartment').val());
                formData.append('brand', $('#editBrand').val());
                formData.append('model', $('#editModel').val());
                const acc = $('#editAccessories').val();
                formData.append('accessories', acc && acc.length > 0 ? JSON.stringify(acc) : JSON.stringify([]));
                if ($('#editChangeImage').is(':checked')) {
                    const file = $('#editItemImage')[0].files[0];
                    if (file) {
                        if (file.size > 5 * 1024 * 1024) {
                            toastr.error('File size must be less than 5MB');
                            return;
                        }
                        if (!['image/jpeg', 'image/png', 'image/gif', 'image/webp'].includes(file.type)) {
                            toastr.error('Invalid image type');
                            return;
                        }
                        formData.append('image', file);
                        if ($('#editApplyToSimilar').is(':checked')) {
                            formData.append('apply_to_similar', '1');
                        }
                    } else {
                        formData.append('remove_image', '1');
                    }
                }
                const btn = $('#editSubmitBtn'),
                    orig = btn.html();
                btn.html('<span class="spinner-border spinner-border-sm me-1"></span> Saving...').prop('disabled', true);
                $.ajax({
                    url: 'api/update_item.php',
                    method: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    dataType: 'json',
                    timeout: 30000,
                    success: function(r) {
                        if (r.success) {
                            toastr.success(r.message || 'Item updated successfully!');
                            const m = bootstrap.Modal.getInstance(document.getElementById('editItemModal'));
                            if (m) m.hide();
                            setTimeout(() => location.reload(), 1000);
                        } else {
                            toastr.error(r.message || 'Failed to update item');
                            btn.html(orig).prop('disabled', false);
                        }
                    },
                    error: function(xhr, status, error) {
                        let msg = 'Error updating item';
                        try {
                            msg = JSON.parse(xhr.responseText).message || msg;
                        } catch (e) {
                            msg = xhr.statusText || error;
                        }
                        toastr.error(msg);
                        btn.html(orig).prop('disabled', false);
                    }
                });
            }

            // ========== DATATABLE ==========
            function initializeDataTable() {
                if ($('#recentItemsTable').length === 0) return;
                if ($.fn.DataTable.isDataTable('#recentItemsTable')) $('#recentItemsTable').DataTable().destroy();
                try {
                    return $('#recentItemsTable').DataTable({
                        dom: "<'row mb-4 px-2'<'col-sm-12 col-md-6'l><'col-sm-12 col-md-6 d-flex justify-content-md-end'f>>" +
                            "<'row'<'col-sm-12'tr>>" +
                            "<'row mt-4 px-2'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7'p>>",
                        paging: true,
                        pageLength: 5,
                        lengthChange: true,
                        lengthMenu: [
                            [5, 10, 25, 50, 100],
                            [5, 10, 25, 50, 100]
                        ],
                        searching: true,
                        ordering: true,
                        info: true,
                        autoWidth: false,
                        responsive: true,
                        serverSide: true,
                        processing: true,
                        order: [
                            [1, 'asc']
                        ],
                        columns: [{
                                data: 'id',
                                orderable: false
                            },
                            {
                                data: 'created_at',
                                render: d => d ? '<span class="badge bg-secondary">' + new Date(d).toLocaleDateString() + '</span><br><small>' + new Date(d).toLocaleTimeString() + '</small>' : '<span class="text-muted">N/A</span>'
                            },
                            {
                                data: 'item_name',
                                render: (d, t, row) => '<div class="fw-bold">' + escapeHtml(d || '') + '</div>' + (row.description ? '<small class="text-muted">' + escapeHtml(row.description.substring(0, 50)) + '...</small>' : '')
                            },
                            {
                                data: 'serial_number',
                                render: d => '<code>' + escapeHtml(d || 'N/A') + '</code>'
                            },
                            {
                                data: 'category_name',
                                render: d => '<span class="badge bg-" style="background: #3a6487">' + escapeHtml(d || 'Uncategorized') + '</span>'
                            },
                            {
                                data: 'department_name',
                                render: d => escapeHtml(d || 'N/A')
                            },
                            {
                                data: 'accessories',
                                render: d => d && d.length ? '<div class="d-flex flex-wrap gap-1">' + d.map(a => '<span class="badge bg-" style="background: #3a8750">' + escapeHtml(a) + '</span>').join('') + '</div>' : '<span class="text-muted">None</span>'
                            },
                            {
                                data: 'model',
                                render: d => escapeHtml(d || 'N/A')
                            },
                            {
                                data: 'brand',
                                render: d => escapeHtml(d || 'N/A')
                            },
                            {
                                data: 'stock_location',
                                render: d => escapeHtml(d || 'N/A')
                            },
                            {
                                data: 'condition',
                                render: d => {
                                    const c = (d || 'good').toLowerCase();
                                    const cls = ['new', 'excellent'].includes(c) ? 'bg-success' : c === 'good' ? 'bg-primary' : c === 'fair' ? 'bg-warning' : c === 'poor' ? 'bg-danger' : 'bg-secondary';
                                    return '<span class="badge ' + cls + '">' + escapeHtml(d || 'Good') + '</span>';
                                }
                            },
                            {
                                data: 'status',
                                render: d => {
                                    const s = (d || 'available').toLowerCase();
                                    const cls = {
                                        available: 'bg-success',
                                        in_use: 'bg-primary',
                                        maintenance: 'bg-warning',
                                        reserved: 'bg-info',
                                        disposed: 'bg-danger',
                                        lost: 'bg-dark'
                                    };
                                    return '<span class="badge ' + (cls[s] || 'bg-secondary') + '">' + escapeHtml(d || 'Available') + '</span>';
                                }
                            },
                            {
                                data: null,
                                orderable: false,
                                searchable: false,
                                render: (d, t, row) => '<button type="button" class="btn btn-sm btn-primary quick-view-btn"' +
                                    ' data-item-id="' + row.id + '"' +
                                    ' data-item-name="' + escapeHtml(row.item_name || '') + '"' +
                                    ' data-item-serial="' + escapeHtml(row.serial_number || '') + '"' +
                                    ' data-item-category="' + escapeHtml(row.category || 'N/A') + '"' +
                                    ' data-item-quantity="' + (row.quantity || 1) + '"' +
                                    ' data-item-status="' + escapeHtml(row.status || 'available') + '"' +
                                    ' data-item-condition="' + escapeHtml(row.condition || 'good') + '"' +
                                    ' data-item-location="' + escapeHtml(row.stock_location || 'N/A') + '"' +
                                    ' data-item-brand="' + escapeHtml(row.brand || 'N/A') + '"' +
                                    ' data-item-model="' + escapeHtml(row.model || 'N/A') + '"' +
                                    ' data-item-department="' + escapeHtml(row.department || 'N/A') + '"' +
                                    ' data-qr-code="' + escapeHtml(row.qr_code || '') + '"' +
                                    ' title="Quick Actions"><i class="fas fa-bolt"></i></button>'
                            }
                        ],
                        language: {
                            emptyTable: 'No equipment found',
                            zeroRecords: 'No matching records found',
                            info: 'Showing _START_ to _END_ of _TOTAL_ items',
                            infoEmpty: 'Showing 0 to 0 of 0 items',
                            infoFiltered: '(filtered from _MAX_ total items)',
                            lengthMenu: 'Show _MENU_ entries',
                            search: 'Search:',
                            processing: 'Loading...',
                            paginate: {
                                first: 'First',
                                last: 'Last',
                                next: 'Next',
                                previous: 'Previous'
                            }
                        },
                        ajax: {
                            url: 'api/items/list.php',
                            type: 'GET',
                            data: d => ({
                                page: Math.floor(d.start / d.length) + 1,
                                limit: d.length,
                                search: d.search.value,
                                sort: d.columns[d.order[0].column].data,
                                order: d.order[0].dir
                            }),
                            dataSrc: function(json) {
                                if (json.success) {
                                    const total = json.pagination?.totalItems ??
                                        json.pagination?.total ??
                                        json.total ??
                                        (json.items?.length || 0);
                                    json.recordsTotal = total;
                                    json.recordsFiltered = json.pagination?.filteredItems ??
                                        json.pagination?.totalItems ??
                                        total;
                                }
                                return json.items || [];
                            }
                        },
                        drawCallback: function() {
                            $(this).closest('.dataTables_wrapper').find('.pagination').addClass('pagination-sm justify-content-center');
                        }
                    });
                } catch (err) {
                    console.error('DataTable initialization error:', err);
                    return null;
                }
            }

            function refreshDataTable() {
                if ($.fn.DataTable.isDataTable('#recentItemsTable')) {
                    $('#recentItemsTable').DataTable().ajax.reload(null, false);
                    toastr.info('Table data refreshed');
                } else {
                    setTimeout(() => location.reload(), 500);
                }
            }

            // ========== QR DOWNLOAD ==========
            function downloadSingleQRCode(qrUrl, itemName, serialNumber) {
                if (!qrUrl) {
                    toastr.error('No QR code available to download');
                    return;
                }
                const link = document.createElement('a');
                link.href = qrUrl.split('?')[0] + '?download=' + Date.now();
                link.download = 'QR_' + (itemName || 'item').replace(/[<>:"/\\|?*]/g, '_').replace(/\s+/g, '_').substring(0, 50) + '_' + (serialNumber || 'unknown').replace(/[^a-z0-9]/gi, '_') + '.png';
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
                toastr.success('QR Code download started!');
            }

            function downloadAllQRCodes() {
                toastr.info('Fetching items...', 'Processing');
                const button = document.querySelector('button[onclick="downloadAllQRCodes()"]');
                if (button) {
                    button.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Fetching...';
                    button.disabled = true;
                }
                const resetButton = () => {
                    if (button) {
                        button.innerHTML = '<i class="fas fa-download me-1"></i> Download All QR Codes';
                        button.disabled = false;
                    }
                };
                fetch('api/items/list.php?limit=1000&page=1').then(r => r.json()).then(data => {
                    if (!data.success || !data.items || data.items.length === 0) {
                        toastr.error('No items found');
                        resetButton();
                        return;
                    }
                    const withQR = data.items.filter(i => i.qr_code && i.qr_code !== '' && i.qr_code !== 'pending');
                    if (!withQR.length) {
                        toastr.warning('No QR codes found');
                        resetButton();
                        return;
                    }
                    toastr.info('Downloading ' + withQR.length + ' QR codes...');
                    let downloaded = 0;
                    withQR.forEach((item, idx) => {
                        setTimeout(() => {
                            try {
                                let qrUrl = item.qr_code;
                                if (qrUrl && !qrUrl.startsWith('http')) qrUrl = window.location.origin + '/ability_app_main/' + qrUrl.replace(/^\//, '');
                                const link = document.createElement('a');
                                link.href = qrUrl;
                                link.download = 'QR_' + (item.item_name || 'item').replace(/[^a-z0-9]/gi, '_').substring(0, 50) + '_' + (item.serial_number || 'item').replace(/[^a-z0-9]/gi, '_') + '.png';
                                link.target = '_blank';
                                document.body.appendChild(link);
                                link.click();
                                document.body.removeChild(link);
                                downloaded++;
                            } catch (err) {
                                console.error('Download error:', err);
                            }
                            if (downloaded === withQR.length) {
                                toastr.success('Downloaded ' + downloaded + ' QR codes!');
                                resetButton();
                            }
                        }, idx * 300);
                    });
                }).catch(err => {
                    console.error(err);
                    toastr.error('Failed to fetch items');
                    resetButton();
                });
            }

            function generateAndDownloadQRZipWithProgress() {
                const button = document.querySelector('button[onclick*="generateAndDownloadQRZipWithProgress"]');
                if (button) {
                    button.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Processing...';
                    button.disabled = true;
                }
                toastr.info('Starting QR code generation...');
                const modalHtml = '<div class="modal fade" id="qrProcessingModal" tabindex="-1" data-bs-backdrop="static"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><div class="modal-header bg-primary text-white"><h5 class="modal-title">Generating QR Codes</h5></div><div class="modal-body text-center"><div class="spinner-border text-primary mb-3" role="status"></div><p>Generating QR codes for all items...</p></div></div></div></div>';
                $('#qrProcessingModal').remove();
                $('body').append(modalHtml);
                const modal = new bootstrap.Modal(document.getElementById('qrProcessingModal'));
                modal.show();
                $.ajax({
                    url: 'api/quick_qr_zip.php',
                    method: 'GET',
                    dataType: 'json',
                    success: function(r) {
                        modal.hide();
                        $('#qrProcessingModal').remove();
                        if (r.success && r.download_url) {
                            toastr.success(r.message);
                            const link = document.createElement('a');
                            link.href = r.download_url;
                            link.download = r.filename || 'qr_codes.zip';
                            document.body.appendChild(link);
                            link.click();
                            document.body.removeChild(link);
                        } else {
                            toastr.error(r.message || 'Failed to generate QR codes');
                        }
                    },
                    error: () => {
                        modal.hide();
                        $('#qrProcessingModal').remove();
                        toastr.error('Error generating QR codes');
                    },
                    complete: () => {
                        if (button) {
                            button.innerHTML = '<i class="fas fa-file-archive me-1"></i> Generate & Download ZIP';
                            button.disabled = false;
                        }
                    }
                });
            }

            // ========== ADD ITEM FORM ==========
            function handleImagePreview(e) {
                const file = e.target.files[0],
                    preview = $('#imagePreview');
                if (!file) {
                    preview.hide();
                    return;
                }
                if (file.size > 5 * 1024 * 1024) {
                    toastr.error('File size must be less than 5MB');
                    $(e.target).val('');
                    preview.hide();
                    return;
                }
                if (!['image/jpeg', 'image/png', 'image/gif', 'image/webp'].includes(file.type)) {
                    toastr.error('Please select a valid image file');
                    $(e.target).val('');
                    preview.hide();
                    return;
                }
                const reader = new FileReader();
                reader.onload = ev => {
                    preview.find('img').attr('src', ev.target.result);
                    preview.show();
                };
                reader.readAsDataURL(file);
            }

            function clearImagePreview() {
                $('#item_image').val('');
                $('#imagePreview').hide();
            }

            function generateSerialNumber() {
                const name = $('#item_name').val().trim();
                const prefix = name ? name.substring(0, 3).toUpperCase().replace(/\s/g, '') : 'EQP';
                $('#serial_number').val(prefix + '-' + Date.now().toString().substr(-8) + '-' + Math.floor(Math.random() * 1000).toString().padStart(3, '0'));
            }

            function handleAddItemSubmit(e) {
                e.preventDefault();
                const itemName = $('#item_name').val().trim(),
                    serial = $('#serial_number').val().trim(),
                    cat = $('#category').val();
                if (!itemName || !serial || !cat) {
                    toastr.error('Please fill all required fields');
                    return;
                }
                const formData = new FormData(this);
                const selAcc = [];
                $('#accessories option:selected').each(function() {
                    if ($(this).val()) selAcc.push($(this).val());
                });
                formData.append('accessories_array', JSON.stringify(selAcc));
                const btn = $('#submitBtn'),
                    orig = btn.html();
                btn.html('<i class="fas fa-spinner fa-spin me-1"></i> Saving...').prop('disabled', true);
                $.ajax({
                    url: 'api/items/create.php',
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    dataType: 'json',
                    success: function(r) {
                        if (r.success) {
                            toastr.success(r.message || 'Item added successfully!');
                            $('#addItemForm')[0].reset();
                            $('#imagePreview').hide();
                            $('#selectedAccessories').html('<p class="text-muted mb-0">No accessories selected</p>');
                            $('#accessories').val('').trigger('change');
                            setTimeout(() => {
                                const m = bootstrap.Modal.getInstance(document.getElementById('addItemModal'));
                                if (m) m.hide();
                                location.reload();
                            }, 1500);
                        } else {
                            toastr.error(r.message || 'Failed to add item');
                        }
                    },
                    error: function(xhr) {
                        let msg = 'Error adding item';
                        try {
                            msg = JSON.parse(xhr.responseText).message || msg;
                        } catch (e) {
                            msg = 'Server error';
                        }
                        toastr.error(msg);
                    },
                    complete: () => btn.html(orig).prop('disabled', false)
                });
            }

            // ========== QUICK SEARCH ==========
            function quickViewItem(itemId) {
                const m = bootstrap.Modal.getInstance(document.getElementById('quickSearchModal'));
                if (m) m.hide();
                setTimeout(() => {
                    openQuickViewModal(itemId);
                }, 350);
            }

            function performQuickSearch() {
                const term = $('#quickSearchInput').val().trim();
                if (term.length < 2) return;
                $('#quickStatsSection').hide();
                $('#quickSearchResults').show();
                $('#searchResultsContainer').html('<div class="text-center py-5"><div class="spinner-border text-primary mb-3" role="status"></div><p class="text-muted">Searching...</p></div>');
                $.ajax({
                    url: 'api/quick_search.php',
                    method: 'GET',
                    data: {
                        q: term
                    },
                    dataType: 'json',
                    success: function(r) {
                        if (r.success && r.items && r.items.length > 0) {
                            displaySearchResults(r.items);
                            $('#searchResultCount').text(r.items.length);
                        } else {
                            $('#searchResultCount').text('0');
                            $('#searchResultsContainer').html('<div class="text-center py-5 text-muted"><i class="fas fa-search fa-3x mb-3"></i><h6>No items found</h6><p class="small">Try different keywords</p></div>');
                        }
                    },
                    error: () => $('#searchResultsContainer').html('<div class="text-center py-5 text-danger"><i class="fas fa-exclamation-triangle fa-3x mb-3"></i><p>Search failed. Please try again.</p></div>')
                });
            }

            function displaySearchResults(items) {
                let html = '<div class="list-group">';
                items.forEach(item => {
                    const sc = 'status-' + ((item.status || 'unknown').toLowerCase());
                    html += '<div class="list-group-item list-group-item-action" onclick="quickViewItem(' + item.id + ')" style="cursor:pointer;">' +
                        '<div class="row align-items-center"><div class="col-md-8"><div class="d-flex align-items-center">' +
                        (item.image ? '<img src="' + escapeHtml(getImageUrl(item.image) || '') + '" class="rounded me-2" style="width:40px;height:40px;object-fit:cover;" onerror="this.style.display=\'none\';">' :
                            '<div class="bg-light rounded me-2 d-flex align-items-center justify-content-center" style="width:40px;height:40px;"><i class="fas fa-box text-muted"></i></div>') +
                        '<div><strong class="d-block">' + escapeHtml(item.item_name) + '</strong><small class="text-muted"><i class="fas fa-barcode me-1"></i>' + escapeHtml(item.serial_number || 'N/A') + '</small></div>' +
                        '</div></div><div class="col-md-4"><div class="d-flex justify-content-end align-items-center">' +
                        '<div class="text-end me-3"><span class="status-badge ' + sc + '">' + escapeHtml(item.status || 'Unknown') + '</span><br><small class="text-muted"><i class="fas fa-map-marker-alt me-1"></i>' + escapeHtml(item.stock_location || 'N/A') + '</small></div>' +
                        '<i class="fas fa-chevron-right text-muted"></i></div></div></div>' +
                        (item.description ? '<small class="text-muted d-block mt-2">' + escapeHtml(item.description.substring(0, 100)) + '...</small>' : '') +
                        '</div>';
                });
                $('#searchResultsContainer').html(html + '</div>');
            }

            function clearQuickSearch() {
                $('#quickSearchInput').val('');
                $('#quickSearchResults').hide();
                $('#quickStatsSection').show();
                if (searchTimeout) clearTimeout(searchTimeout);
            }
            $(document).ready(function() {
                saveOriginalEditModalHtml();
                window.manualOverviewMode = false;

                // Form Submissions
                $('#addItemForm').on('submit', handleAddItemSubmit);
                $('#editItemForm').on('submit', function(e) {
                    e.preventDefault();
                    submitEditItemForm();
                });

                // Interactive Elements
                $('#generateSerialBtn').on('click', generateSerialNumber);
                $('#item_image').on('change', handleImagePreview);
                $('#accessories').on('change', updateSelectedAccessories);

                $('#toggleDashboardOverview').on('click', function() {
                    window.manualOverviewMode = true;
                    $('#landingSection').hide();
                    $('#dashboardOverviewSection').removeClass('hidden-section').fadeIn();
                    initializeDataTable();
                });

                window.hideDashboardOverview = function() {
                    window.manualOverviewMode = false;
                    $('#dashboardOverviewSection').hide();
                    $('#landingSection').fadeIn();
                };

                $('#mainDashboardSearch').on('keyup', function() {
                    const query = $(this).val().trim();
                    const suggestions = $('#mainSearchSuggestions');
                    if (query.length < 1) {
                        suggestions.hide();
                        return;
                    }

                    $.get('api/search_items.php', {
                        q: query
                    }, function(r) {
                        if (r.success && r.items) {
                            const count = r.items.length;
                            let html = `<div class="results-header">
                                    <div class="text-muted small">${count} result(s) found</div>
                                    <button class="btn btn-sm btn-outline-secondary border-0 py-0" onclick="$('#mainDashboardSearch').val('').trigger('keyup');">
                                        <i class="fas fa-times me-1"></i> Clear
                                    </button>
                                </div>`;
                            if (count > 0) {
                                r.items.slice(0, 10).forEach(item => {
                                    const status = (item.status || 'available').toLowerCase();
                                    const sClass = status === 'available' ? 'bg-soft-success text-success' : 'bg-soft-primary text-primary';
                                    html += `
                                <div class="search-result-card" onclick="openQuickActionFromSearch(${item.id})">
                                    <div class="search-result-icon"><i class="fas fa-box"></i></div>
                                    <div class="flex-grow-1">
                                        <div class="fw-bold text-dark mb-0">${escapeHtml(item.item_name)}</div>
                                        <div class="text-muted small"><i class="fas fa-barcode me-1"></i>${escapeHtml(item.serial_number)}</div>
                                    </div>
                                    <div class="text-end me-3">
                                        <span class="status-pill-mini ${sClass} mb-1 d-inline-block">${status.toUpperCase()}</span>
                                        <div class="text-muted" style="font-size:0.7rem;"><i class="fas fa-map-marker-alt me-1"></i>${escapeHtml(item.stock_location || 'N/A')}</div>
                                    </div>
                                    <div class="text-muted opacity-50"><i class="fas fa-chevron-right"></i></div>
                                </div>`;
                                });
                                suggestions.html(html).show();
                            } else {
                                suggestions.html('<div class="p-4 text-center text-muted">No items found</div>').show();
                            }
                        } else {
                            suggestions.hide();
                        }
                    }, 'json');
                });

                $('#mainSearchTriggerBtn').on('click', function() {
                    $('#mainDashboardSearch').trigger('keyup');
                });

                window.openQuickActionFromSearch = function(id) {
                    console.log("[Search] Opening item ID:", id);
                    $('#mainSearchSuggestions').hide();
                    openQuickViewModal(id);
                };

                // Clear any leftover backdrops on page load
                setTimeout(() => {
                    $('.modal-backdrop').remove();
                    $('body').removeClass('modal-open');
                }, 500);
            });

            function openQuickViewModal(itemId) {
                if (!itemId) return;
                console.log("[QuickView] Loading ID:", itemId);

                const modalEl = document.getElementById('quickActionsModal');
                if (!modalEl) {
                    toastr.error('System Error: Modal container missing');
                    return;
                }

                // Initial loading state
                $('#quickActionsModal .modal-header').html(`
                    <div class="d-flex align-items-center w-100">
                        <div class="bg-white bg-opacity-20 rounded-3 p-2 me-3">
                            <i class="fas fa-spinner fa-spin text-white"></i>
                        </div>
                        <div>
                            <h5 class="modal-title text-white mb-0">Loading Details...</h5>
                            <div class="text-white-50 small">Please wait while we fetch asset information</div>
                        </div>
                        <button type="button" class="btn-close btn-close-white ms-auto" data-bs-dismiss="modal"></button>
                    </div>
                `);
                $('#quickActionsModal .modal-body').html(`
                    <div class="text-center py-5">
                        <div class="spinner-border text-primary mb-3" role="status"></div>
                        <p class="text-muted">Fetching asset data from server...</p>
                    </div>
                `);

                // Use stable bootstrap modal instance
                let modal = bootstrap.Modal.getInstance(modalEl);
                if (!modal) modal = new bootstrap.Modal(modalEl, {
                    keyboard: true
                });
                modal.show();

                fetchItemData(itemId).then(data => {
                    console.log("[QuickView] Data received:", data.item_name);
                    if (data) {
                        populateQuickViewModal(data);
                    }
                }).catch(err => {
                    console.error("[QuickView] Fetch failed:", err);
                    $('#quickActionsModal .modal-body').html(`
                        <div class="text-center py-5">
                            <i class="fas fa-exclamation-triangle text-danger fa-3x mb-3"></i>
                            <h5>Failed to Load Data</h5>
                            <p class="text-muted">${escapeHtml(err.message)}</p>
                            <button class="btn btn-primary" onclick="openQuickViewModal(${itemId})">Retry</button>
                        </div>
                    `);
                });
            }
        </script>