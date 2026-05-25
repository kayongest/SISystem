<?php
// Start session
session_start();

// Include bootstrap and database connection
require_once 'bootstrap.php';
require_once 'includes/db_connect.php';
require_once 'includes/functions.php';

// Check if user is logged in
if (!isLoggedIn()) {
    $_SESSION['flash_messages']['warning'] = 'Please log in to view batch history.';
    header('Location: login.php');
    exit();
}

// Get user's role for the header
$user_role = getUserRole();
$is_stock_controller = ($user_role === 'stock_controller');

// RESTRICT ACCESS - Only stock controllers can access batch reports directly
// RESTRICT ACCESS - Only stock controllers and admins can access this page
$allowed_roles = ['stock_controller', 'admin'];
if (!in_array($user_role, $allowed_roles)) {
    $_SESSION['flash_messages']['error'] = 'Access denied. Only Stock Controllers can view this page.';
    header('Location: technician_batch_history.php');
    exit();
}





// Get filter parameters
$date_from = $_GET['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
$date_to = $_GET['date_to'] ?? date('Y-m-d');
$status = $_GET['status'] ?? '';
$search = $_GET['search'] ?? '';
$technician_id = $_GET['technician_id'] ?? '';
$location = $_GET['location'] ?? '';

$pageTitle = "Batch History - aBility";
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

    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.bootstrap5.min.css">

    <!-- Date Range Picker -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.css">

    <!-- Chart.js -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.min.css">

    <!-- SweetAlert2 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        @import url('https://fonts.googleapis.com/css2?family=Marvel:ital,wght@0,400;0,700;1,400;1,700&display=swap');

        * {
            font-family: "Marvel", sans-serif;
        }

        /* Navigation Adjustment */
        .navbar {
            margin-bottom: 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        /* ==================== DASHBOARD STYLES ==================== */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 12px;
            margin-bottom: 20px;
        }

        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 15px rgb(30 58 80 / 26%);
            transition: transform 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            margin-bottom: 15px;
        }

        .stat-icon.blue {
            background: rgba(67, 97, 238, 0.1);
            color: #4361ee;
        }

        .stat-icon.green {
            background: rgba(76, 175, 80, 0.1);
            color: #4CAF50;
        }

        .stat-icon.orange {
            background: rgba(255, 193, 7, 0.1);
            color: #ffc107;
        }

        .stat-icon.red {
            background: rgba(220, 53, 69, 0.1);
            color: #dc3545;
        }

        .stat-icon.purple {
            background: rgba(111, 66, 193, 0.1);
            color: #6f42c1;
        }

        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 5px;
        }

        .stat-label {
            color: #6c757d;
            font-size: 0.9rem;
        }

        .stat-trend {
            margin-top: 10px;
            font-size: 0.85rem;
        }

        .trend-up {
            color: #4CAF50;
        }

        .trend-down {
            color: #dc3545;
        }

        /* ==================== FILTER SECTION ==================== */
        .filter-section {
            background: white;
            border-radius: 10px;
            padding: 30px;
            margin-bottom: 20px;
            box-shadow: 0 2px 15px rgba(0, 0, 0, 0.1);
        }

        .filter-title {
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 15px;
            color: #495057;
        }

        .filter-title i {
            color: #4361ee;
            margin-right: 8px;
        }

        /* ==================== TABLE STYLES ==================== */
        .table-container {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 15px rgba(0, 0, 0, 0.1);
        }

        .batch-badge {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
        }

        .badge-pending {
            background: rgba(255, 193, 7, 0.15);
            color: #ffc107;
        }

        .badge-approved {
            background: rgba(40, 167, 69, 0.15);
            color: #28a745;
        }

        .badge-rejected {
            background: rgba(220, 53, 69, 0.15);
            color: #dc3545;
        }

        .badge-completed {
            background: rgba(0, 123, 255, 0.15);
            color: #007bff;
        }

        .action-buttons {
            display: flex;
            gap: 5px;
            justify-content: center;
        }

        .btn-icon {
            width: 32px;
            height: 32px;
            padding: 0;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
        }

        /* ==================== DETAIL MODAL ==================== */
        .detail-section {
            margin-bottom: 20px;
        }

        .detail-title {
            font-size: 1rem;
            font-weight: 600;
            color: #495057;
            margin-bottom: 10px;
            padding-bottom: 5px;
            border-bottom: 2px solid #e9ecef;
        }

        .detail-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 10px;
        }

        .detail-item {
            padding: 8px;
            background: #f8f9fa;
            border-radius: 5px;
        }

        .detail-label {
            font-size: 0.8rem;
            color: #6c757d;
            margin-bottom: 2px;
        }

        .detail-value {
            font-weight: 600;
            color: #212529;
        }

        .items-table {
            font-size: 0.9rem;
        }

        .items-table th {
            background: #f8f9fa;
            font-weight: 600;
        }

        /* ==================== PRINT STYLES ==================== */
        @media print {
            .no-print {
                display: none !important;
            }

            .page-header-compact,
            .filter-section,
            .stats-grid,
            .btn,
            .dt-buttons,
            .dataTables_filter,
            .dataTables_length,
            .dataTables_paginate {
                display: none !important;
            }

            .table-container {
                box-shadow: none;
                padding: 0;
            }

            table {
                border-collapse: collapse;
                width: 100%;
            }

            th,
            td {
                border: 1px solid #ddd;
                padding: 8px;
            }
        }

        /* ==================== RESPONSIVE ==================== */
        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }

            .filter-section .row>div {
                margin-bottom: 10px;
            }

            .action-buttons {
                flex-wrap: wrap;
            }
        }

        /* Timeline View */
        .timeline {
            position: relative;
            padding: 20px 0;
        }

        .timeline::before {
            content: '';
            position: absolute;
            left: 20px;
            top: 0;
            bottom: 0;
            width: 2px;
            background: #e9ecef;
        }

        .timeline-item {
            position: relative;
            padding-left: 50px;
            margin-bottom: 20px;
        }

        .timeline-marker {
            position: absolute;
            left: 11px;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            background: white;
            border: 2px solid #4361ee;
            z-index: 1;
        }

        .timeline-content {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
        }

        .timeline-date {
            font-size: 0.8rem;
            color: #6c757d;
            margin-bottom: 5px;
        }

        .timeline-title {
            font-weight: 600;
            margin-bottom: 5px;
        }

        /* Chart Containers */
        .chart-container {
            height: 300px;
            margin-bottom: 20px;
        }

        /* Loading Spinner */
        .spinner-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(255, 255, 255, 0.8);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 9999;
        }

        .spinner-overlay.show {
            display: flex;
        }


        .px-4 {
            padding-right: 1.5rem !important;
            padding-left: 3.5rem !important;
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
            color: #495057 !important;
            font-weight: 500 !important;
            transition: all 0.2s ease !important;
            cursor: pointer !important;
            text-decoration: none !important;
            font-size: 0.8rem !important;
        }

        .dataTables_wrapper .dataTables_paginate .paginate_button:hover {
            background: #f8f9fa !important;
            border-color: #3A6D8C !important;
            color: #3A6D8C !important;
            transform: translateY(-1px);
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
        }

        .dataTables_wrapper .dataTables_paginate .paginate_button.current,
        .dataTables_wrapper .dataTables_paginate .paginate_button.current:hover {
            background: linear-gradient(135deg, #1a2e3f 0%, #3A6D8C 100%) !important;
            color: white !important;
            border-color: #1a2e3f !important;
            box-shadow: 0 4px 10px rgba(58, 109, 140, 0.3);
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
            font-size: 0.85rem;
            color: #6c757d;
            text-align: center;
        }

        .dataTables_wrapper .dataTables_length select {
            padding: 0.2rem 1.5rem 0.2rem 0.5rem;
            margin: 0 0.3rem;
        }

        /* Responsive table */
        @media (max-width: 768px) {

            .dataTables_wrapper .dataTables_length,
            .dataTables_wrapper .dataTables_filter {
                text-align: left;
                margin-bottom: 1rem;
            }

            .action-buttons {
                flex-wrap: wrap;
            }
        }
        /* Milestone Tracker styling */
        .milestone-tracker {
            display: flex;
            justify-content: space-between;
            position: relative;
            margin: 1.5rem 0 2.5rem 0;
            padding: 0 10px;
        }

        .milestone-tracker::before {
            content: '';
            position: absolute;
            top: 22px;
            left: 10%;
            right: 10%;
            height: 4px;
            background: #044A42; /* Dark Emerald Teal path line */
            z-index: 1;
        }

        .milestone-tracker-fill {
            position: absolute;
            top: 22px;
            left: 10%;
            height: 4px;
            background: linear-gradient(90deg, #3A9188, #B8E1DD);
            z-index: 1;
            transition: width 0.4s ease;
            width: 0%;
        }

        .milestone-step {
            position: relative;
            z-index: 2;
            display: flex;
            flex-direction: column;
            align-items: center;
            width: 25%;
        }

        .milestone-icon {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            background: #062925;
            border: 3px solid #044A42;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
            color: #3A9188;
            transition: all 0.3s ease;
            box-shadow: 0 4px 10px rgba(0,0,0,0.3);
        }

        .milestone-step.active .milestone-icon {
            border-color: #3A9188;
            color: white;
            background: #3A9188;
            box-shadow: 0 0 15px rgba(58, 145, 136, 0.5);
        }

        .milestone-step.completed .milestone-icon {
            border-color: #B8E1DD;
            color: #062925;
            background: #B8E1DD;
            box-shadow: 0 0 12px rgba(184, 225, 221, 0.4);
        }

        .milestone-step.rejected .milestone-icon {
            border-color: #dc3545;
            color: white;
            background: #dc3545;
        }

        .milestone-label {
            font-size: 0.8rem;
            font-weight: 700;
            margin-top: 10px;
            color: #3A9188;
            text-align: center;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }

        .milestone-step.active .milestone-label {
            color: white;
        }

        .milestone-step.completed .milestone-label {
            color: #B8E1DD;
        }

        .milestone-step.rejected .milestone-label {
            color: #dc3545;
        }

        .milestone-sublabel {
            font-size: 0.7rem;
            color: rgba(184, 225, 221, 0.6);
            text-align: center;
            margin-top: 3px;
            max-width: 130px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
    </style>
</head>

<body>
    <!-- Loading Spinner -->
    <div class="spinner-overlay" id="loadingSpinner">
        <div class="spinner-border text-primary" style="width: 3rem; height: 3rem;" role="status">
            <span class="visually-hidden">Loading...</span>
        </div>
    </div>

    <!-- Unified Navigation -->
    <?php include 'includes/navbar_main.php'; ?>

    <!-- Display flash messages if any -->
    <?php if (isset($_SESSION['flash_messages'])): ?>
        <?php foreach ($_SESSION['flash_messages'] as $type => $message): ?>
            <div class="alert alert-<?php echo htmlspecialchars($type); ?> alert-dismissible fade show m-3 no-print" role="alert">
                <?php echo htmlspecialchars($message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endforeach; ?>
        <?php unset($_SESSION['flash_messages']); ?>
    <?php endif; ?>

    <!-- Main Content -->
    <div class="container-fluid px-4 py-4">
        <!-- Page Title -->
        <div class="d-flex justify-content-between align-items-center mb-4 no-print">
            <h4 class="mb-0">
                Filter Batches
            </h4>
            <div>
                <button class="btn btn-outline btn-sm me-2 text-white" style="background-color: #3A6D8C;" onclick="refreshData()">
                    <i class="fas fa-sync-alt me-1"></i> Refresh
                </button>
                <button class="btn btn-outline text-white btn-sm btn- me-2" style="background-color: #c08552;" onclick="exportToExcel()">
                    <a href="scan_bulk.php" style="color: white; text-decoration: none;">
                        <i class="fas fa-qrcode"></i> Scanner
                    </a>
                </button>
            </div>
        </div>
        <div class="row">
            <div class="row">
                <div class="col-sm-5">
                    <!-- Filter Section -->
                    <div class="filter-section no-print">
                        <form id="filterForm" method="GET" class="row g-2">
                            <!-- Row 1 -->
                            <div class="col-12 col-md-5">
                                <label class="form-label small">Date Range</label>
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text"><i class="fas fa-calendar"></i></span>
                                    <input type="text" class="form-control" id="daterange" name="daterange" value="2026-03-16 - 2026-04-15">
                                    <input type="hidden" name="date_from" id="date_from" value="2026-04-01">
                                    <input type="hidden" name="date_to" id="date_to" value="2026-04-16">
                                </div>
                            </div>

                            <div class="col-4 col-md-2">
                                <label class="form-label small">Status</label>
                                <select class="form-select form-select-sm" name="status" id="status">
                                    <option value="">All</option>
                                    <option value="pending">Pending</option>
                                    <option value="approved">Approved</option>
                                    <option value="rejected">Rejected</option>
                                    <option value="completed">Completed</option>
                                </select>
                            </div>

                            <div class="col-4 col-md-2">
                                <label class="form-label small">Technician</label>
                                <select class="form-select form-select-sm" name="technician_id" id="technician_id">
                                    <option value="">All</option>
                                    <option value="3">Audrey</option>
                                    <option value="1">Lucas</option>
                                    <option value="2">Shukuru</option>
                                </select>
                            </div>

                            <div class="col-4 col-md-3">
                                <label class="form-label small">Location</label>
                                <select class="form-select form-select-sm" name="location" id="location">
                                    <option value="">All Locations</option>
                                    <option value="KCC_Stock">KCC Stock</option>
                                    <option value="Ndera_Stock">Ndera Stock</option>
                                    <option value="BK_Arena Stock">BK Arena</option>
                                    <option value="Storage_Room">Storage Room</option>
                                </select>
                            </div>

                            <!-- Row 2 - Search on new line for small screens -->
                            <div class="col-12">
                                <label class="form-label small">Search</label>
                                <div class="input-group input-group-sm">
                                    <input type="text" class="form-control" name="search" id="search" placeholder="Search by Batch ID, Item Name, or Serial Number..." value="">
                                    <button class="btn btn-sm btn- text-white" style="background-color: #3A6D8C;" type="submit">
                                        <i class="fas fa-search"></i> Search
                                    </button>
                                    <button class="btn btn-sm btn- text-white" style="background-color: #c08552;" type="button" onclick="clearFilters()">
                                        <i class="fas fa-eraser"></i> Clear
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
                <div class="col-sm-7">
                    <!-- Statistics Cards -->
                    <div class="stats-grid no-print" id="statsContainer">
                        <!-- Stats will be loaded dynamically -->
                        <div class="stat-card">
                            <div class="spinner-border spinner-border-sm text-primary" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                            <div class="stat-label mt-2">Loading statistics...</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>


        <!-- Batches Table -->
        <div class="table-container">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div class="btn-group">
                    <button class="btn btn-sm btn-outline- text-white" style="background-color: #3A6D8C;" onclick="toggleView('table')">
                        <i class="fas fa-table"></i>
                    </button>
                    <button class="btn btn-sm btn-outline- text-white" style="background-color: #6A9AB0;" onclick="toggleView('timeline')">
                        <i class="fas fa-stream"></i>
                    </button>
                    <button class="btn btn-sm btn-outline- text-white" style="background-color: #C08552;" onclick="toggleView('cards')">
                        <i class="fas fa-th-large"></i>
                    </button>
                </div>
            </div>

            <!-- Table View -->
            <div id="tableView">
                <table id="batchesTable" class="table table-hover" style="width:100%">
                    <thead>
                        <tr>
                            <th>Batch ID</th>
                            <th>Date</th>
                            <th>Technician</th>
                            <th>Submitted By</th>
                            <th>Controller</th>
                            <th>Items</th>
                            <th>Total Qty</th>
                            <th>Location</th>
                            <th>Progress</th>
                            <th>Event/Job</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- Data will be loaded dynamically -->
                    </tbody>
                </table>
            </div>

            <!-- Timeline View (Hidden by default) -->
            <div id="timelineView" style="display: none;">
                <div class="timeline" id="timelineContainer">
                    <!-- Timeline items will be loaded dynamically -->
                </div>
            </div>

            <!-- Cards View (Hidden by default) -->
            <div id="cardsView" style="display: none;">
                <div class="row" id="cardsContainer">
                    <!-- Cards will be loaded dynamically -->
                </div>
            </div>
        </div>
    </div>

    <!-- Batch Detail Modal -->
    <div class="modal fade" id="batchDetailModal" tabindex="-1" aria-labelledby="batchDetailModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="batchDetailModalLabel">
                        <i class="fas fa-info-circle me-2"></i>Batch Details
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="batchDetailContent">
                    <div class="text-center py-5">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-3">Loading batch details...</p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-1"></i>Close
                    </button>
                    <!-- <button type="button" class="btn btn-primary" onclick="printCurrentBatch()">
                        <i class="fas fa-print me-1"></i>Print
                    </button> -->
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/dataTables.buttons.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.bootstrap5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.html5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.print.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>
    <script src="https://cdn.jsdelivr.net/momentjs/latest/moment.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-adapter-date-fns@3.0.0/dist/chartjs-adapter-date-fns.bundle.min.js"></script>

    <script>
        // JavaScript code will go here
        // ==================== BATCH HISTORY PAGE ====================
console.log('Script loaded - starting initialization');

$(document).ready(function() {
    console.log('Document ready - initializing batch history page');

    if (typeof moment === 'undefined') {
        console.error('moment.js NOT loaded!');
        alert('Date library not loaded. Please refresh the page.');
        return;
    }
    console.log('✅ moment.js loaded');

    if (typeof $ === 'undefined') {
        console.error('jQuery NOT loaded!');
        return;
    }
    console.log('✅ jQuery loaded, version:', $.fn.jquery);

    if ($.fn.DataTable) {
        console.log('✅ DataTables loaded');
    } else {
        console.error('DataTables NOT loaded!');
    }

    // Initialize all components
    initializeDateRangePicker(); // Must come first — sets hidden date fields before load calls
    loadTechnicians();
    loadStatistics();
    loadBatches();
    loadTimelineData();
    loadCardsData();
    initializeCharts();

    console.log('Batch History page initialized successfully');
});

// ==================== GLOBAL VARIABLES ====================
let batchesTable;
let currentView = 'table';
let currentBatchId = null;

// ==================== UTILITY FUNCTIONS ====================
function showLoading() {
    $('#loadingSpinner').addClass('show');
}

function hideLoading() {
    $('#loadingSpinner').removeClass('show');
}

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function showNotification(type, message) {
    const notification = $(`
        <div class="alert alert-${type} alert-dismissible fade show position-fixed top-0 end-0 m-3" style="z-index: 9999; min-width: 300px;">
            ${type === 'success' ? '<i class="fas fa-check-circle me-2"></i>' : '<i class="fas fa-exclamation-triangle me-2"></i>'}
            ${escapeHtml(message)}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `);
    $('body').append(notification);
    setTimeout(() => notification.fadeOut(() => notification.remove()), 5000);
}

// ==================== DATE RANGE PICKER ====================
function initializeDateRangePicker() {
    console.log('Initializing date range picker...');

    // ✅ FIX: Set hidden fields immediately so load functions have values on first call
    $('#date_from').val(moment('2026-04-01').format('YYYY-MM-DD'));
    $('#date_to').val(moment().format('YYYY-MM-DD'));

    $('#daterange').daterangepicker({
        startDate: moment('2026-04-01'),
        endDate: moment(),
        ranges: {
            'Today': [moment(), moment()],
            'Yesterday': [moment().subtract(1, 'days'), moment().subtract(1, 'days')],
            'Last 7 Days': [moment().subtract(6, 'days'), moment()],
            'Last 30 Days': [moment().subtract(29, 'days'), moment()],
            'This Month': [moment().startOf('month'), moment().endOf('month')],
            'Last Month': [moment().subtract(1, 'month').startOf('month'), moment().subtract(1, 'month').endOf('month')]
        }
    }, function(start, end) {
        $('#date_from').val(start.format('YYYY-MM-DD'));
        $('#date_to').val(end.format('YYYY-MM-DD'));
        loadBatches();
        loadStatistics();
        loadTimelineData();
        loadCardsData();
    });

    console.log('Date range picker initialized');
}

// ==================== LOAD TECHNICIANS ====================
function loadTechnicians() {
    console.log('Loading technicians...');
    $.ajax({
        url: 'api/technicians/list.php',
        method: 'GET',
        success: function(response) {
            console.log('Technicians API response:', response);
            if (response.success && response.technicians && response.technicians.length > 0) {
                populateTechnicianDropdown(response.technicians);
            } else {
                console.log('No technicians from API, using sample data');
                loadSampleTechnicians();
            }
        },
        error: function(xhr, status, error) {
            console.error('Error loading technicians:', error);
            loadSampleTechnicians();
        }
    });
}

function loadSampleTechnicians() {
    const sampleTechs = [
        { id: 1, full_name: 'Lucas Technician', technician_id: 'TECH001' },
        { id: 2, full_name: 'Shukuru Uwimana', technician_id: 'TECH002' },
        { id: 3, full_name: 'Audrey Gihozo', technician_id: 'TECH003' }
    ];
    populateTechnicianDropdown(sampleTechs);
}

function populateTechnicianDropdown(techs) {
    const select = $('#technician_id');
    select.empty().append('<option value="">All Technicians</option>');
    techs.forEach(tech => {
        const displayName = tech.full_name || tech.username || 'Unknown';
        const techId = tech.technician_id ? ` (${tech.technician_id})` : '';
        select.append(`<option value="${tech.id}">${escapeHtml(displayName)}${techId}</option>`);
    });
    console.log('Technician dropdown populated with', techs.length, 'technicians');
}

// ==================== LOAD STATISTICS ====================
function loadStatistics() {
    console.log('Loading statistics...');
    const date_from = $('#date_from').val();
    const date_to = $('#date_to').val();

    $.ajax({
        url: 'api/batches/stats.php',
        method: 'GET',
        data: { date_from, date_to },
        success: function(response) {
            console.log('Statistics API response:', response);
            if (response.success && response.stats) {
                displayStatistics(response.stats);
            } else {
                displayStatistics({ total_batches: 0, total_items: 0, unique_technicians: 0, pending_batches: 0 });
            }
        },
        error: function(xhr, status, error) {
            console.error('Error loading statistics:', error);
            displayStatistics({ total_batches: 0, total_items: 0, unique_technicians: 0, pending_batches: 0 });
        }
    });
}

function displayStatistics(stats) {
    const html = `
        <div class="stat-card">
            <div class="stat-icon blue"><i class="fas fa-boxes"></i></div>
            <div class="stat-value">${stats.total_batches || 0}</div>
            <div class="stat-label">Total Batches</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon green"><i class="fas fa-cubes"></i></div>
            <div class="stat-value">${stats.total_items || 0}</div>
            <div class="stat-label">Total Items</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon orange"><i class="fas fa-users"></i></div>
            <div class="stat-value">${stats.unique_technicians || 0}</div>
            <div class="stat-label">Active Technicians</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon red"><i class="fas fa-clock"></i></div>
            <div class="stat-value">${stats.pending_batches || 0}</div>
            <div class="stat-label">Pending Approval</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon purple"><i class="fas fa-ticket-alt"></i></div>
            <div class="stat-value">${stats.gate_passes || 0}</div>
            <div class="stat-label">Gate Passes</div>
        </div>
    `;
    $('#statsContainer').html(html);
}

// ==================== LOAD BATCHES ====================
function loadBatches(isSilent = false) {
    if (!isSilent) console.log('Loading batches...');
    const date_from = $('#date_from').val();
    const date_to = $('#date_to').val();
    const status = $('#status').val();
    const technician_id = $('#technician_id').val();
    const search = $('#search').val();

    if (!isSilent) showLoading();

    $.ajax({
        url: 'api/batches/list.php',
        method: 'GET',
        data: { date_from, date_to, status, technician_id, search },
        dataType: 'json',
        success: function(response) {
            console.log('Batches API response:', response);
            if (response.success && response.batches && response.batches.length > 0) {
                console.log('Found', response.batches.length, 'batches');
                updateBatchesTable(response.batches);
            } else {
                console.log('No batches found');
                updateBatchesTable([]);
                if (response.message) {
                    showNotification('info', response.message);
                }
            }
        },
        error: function(xhr, status, error) {
            console.error('Error loading batches:', error);
            console.error('Response:', xhr.responseText);
            updateBatchesTable([]);
            showNotification('error', 'Failed to load batches');
        },
        complete: function() {
            hideLoading();
        }
    });
}

function updateBatchesTable(batches) {
    console.log('Updating table with', batches.length, 'batches');

    if ($.fn.DataTable.isDataTable('#batchesTable')) {
        $('#batchesTable').DataTable().destroy();
    }

    $('#batchesTable tbody').empty();

    if (batches.length === 0) {
        $('#batchesTable tbody').html('<tr><td colspan="10" class="text-center">No batches found</td></tr>');
        return;
    }

    batches.forEach(batch => {
        const statusClass = {
            'pending': 'badge-pending',
            'approved': 'badge-approved',
            'rejected': 'badge-rejected',
            'completed': 'badge-completed'
        }[batch.status] || 'badge-secondary';

        let isApproved = false;
        let isTechOnboard = false;
        let isLoaded = false;
        let isCompleted = false;
        let isRejected = false;

        const appStatus = (batch.approval_status || '').toLowerCase();
        const bStatus = (batch.status || '').toLowerCase();

        if (appStatus === 'approved' || appStatus === 'completed' || bStatus === 'approved' || bStatus === 'completed') isApproved = true;
        if (appStatus === 'rejected' || bStatus === 'rejected') { isRejected = true; isApproved = false; }
        if (parseInt(batch.tech_onboard) === 1) isTechOnboard = true;
        if (parseInt(batch.driver_verified) === 1) isLoaded = true;
        if (bStatus === 'completed') isCompleted = true;

        let fillPercent = 10;
        let bgClass = 'bg-secondary';
        let label = 'Submitted';

        if (isRejected) { fillPercent = 100; bgClass = 'bg-danger'; label = 'Rejected'; }
        else if (isCompleted) { fillPercent = 100; bgClass = 'bg-success'; label = 'Delivered'; }
        else if (isLoaded) { fillPercent = 75; bgClass = 'bg-info'; label = 'Loaded'; }
        else if (isTechOnboard) { fillPercent = 50; bgClass = 'bg-primary'; label = 'Tech Onboard'; }
        else if (isApproved) { fillPercent = 35; bgClass = 'bg-primary'; label = 'Approved'; }
        else { fillPercent = 25; bgClass = 'bg-warning'; label = 'Pending'; }

        const progressHtml = `
        <div style="min-width: 100px;">
            <div class="d-flex justify-content-between mb-1" style="font-size: 0.65rem; font-weight: 600;">
                <span class="text-secondary text-uppercase">${label}</span>
                <span class="text-secondary">${fillPercent}%</span>
            </div>
            <div class="progress" style="height: 5px; border-radius: 3px; background-color: #e2e8f0; box-shadow: inset 0 1px 2px rgba(0,0,0,.1);">
                <div class="progress-bar ${bgClass}" role="progressbar" style="width: ${fillPercent}%; border-radius: 3px;" aria-valuenow="${fillPercent}" aria-valuemin="0" aria-valuemax="100"></div>
            </div>
        </div>
        `;

        const row = `
            <tr>
                <td><code>${escapeHtml(batch.batch_id)}</code></td>
                <td>${batch.date ? moment(batch.date).format('MMM D, YYYY HH:mm') : '—'}</td>
                <td>${escapeHtml(batch.technician || '—')}</td>
                <td>${escapeHtml(batch.submitted_by || '—')}</td>
                <td>${escapeHtml(batch.stock_controller_name || '—')}</td>
                <td class="text-center">${batch.item_count || 0}</td>
                <td class="text-center">${batch.total_quantity || 0}</td>
                <td>${escapeHtml(batch.location || '—')}</td>
                <td>${progressHtml}</td>
                <td><small>${escapeHtml(batch.job_sheet || '-')}</small></td>
                <td><span class="batch-badge ${statusClass}">${batch.status}</span></td>
                <td>
                    <div class="action-buttons">
                        <button class="btn btn-sm btn-info btn-icon" onclick="viewBatchDetails('${batch.batch_id}')" title="View Details">
                            <i class="fas fa-eye"></i>
                        </button>
                        ${(batch.status === 'approved' || batch.status === 'completed') ? `
                            <a href="batch_report.php?batch_id=${encodeURIComponent(batch.batch_id)}&download=1" class="btn btn-sm btn-success btn-icon" title="Download Report" target="_blank">
                                <i class="fas fa-file-pdf"></i>
                            </a>
                            ${['stock_to_stock', 'stock_to_venue_transport'].includes(batch.movement_type) ? `
                            <a href="gate_pass.php?batch_id=${encodeURIComponent(batch.batch_id)}&download=1" class="btn btn-sm btn-warning btn-icon" title="Download Gate Pass" target="_blank">
                                <i class="fas fa-ticket-alt"></i>
                            </a>
                            ` : ''}
                        ` : ''}
                    </div>
                </td>
            </tr>
        `;
        $('#batchesTable tbody').append(row);
    });

    batchesTable = $('#batchesTable').DataTable({
        order: [[1, 'desc']],
        pageLength: 10,
        dom: '<"d-flex justify-content-between align-items-center mb-3"lf>rt<"d-flex flex-column align-items-center"ip>',
        language: {
            emptyTable: 'No batches found in the database',
            search: '_INPUT_',
            searchPlaceholder: 'Search table...',
            paginate: { 
                first: 'First', 
                previous: 'Previous', 
                next: 'Next', 
                last: 'Last' 
            }
        },
        drawCallback: function() {
            // Apply small pagination class to the generated list
            $(this).closest('.dataTables_wrapper').find('.pagination').addClass('pagination-sm justify-content-center');
        }
    });

    console.log('Table updated successfully');
}

// ==================== VIEW BATCH DETAILS ====================
function viewBatchDetails(batchId, isSilent = false) {
    if (!isSilent) console.log('Viewing batch details for:', batchId);

    if (!batchId) {
        showNotification('error', 'No batch ID provided');
        return;
    }

    currentBatchId = batchId;
    if (!isSilent) {
        showLoading();
        $('#batchDetailContent').html(`
            <div class="text-center py-5">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <p class="mt-3">Loading batch details for ${escapeHtml(batchId)}...</p>
            </div>
        `);
    }

    $.ajax({
        url: `api/batches/details.php?batch_id=${encodeURIComponent(batchId)}`,
        method: 'GET',
        dataType: 'json',
        timeout: 10000,
        success: function(response) {
            console.log('Batch details response:', response);
            if (response.success && response.batch) {
                displayBatchDetails(response.batch);
                const modal = new bootstrap.Modal(document.getElementById('batchDetailModal'));
                modal.show();
            } else {
                showNotification('error', response?.message || 'Failed to load batch details');
            }
        },
        error: function(xhr, status, error) {
            console.error('Error loading batch details:', error);
            showNotification('error', 'Failed to load batch details');
        },
        complete: function() {
            hideLoading();
        }
    });
}
function displayBatchDetails(batch) {
    console.log('Displaying batch details');

    // Construct destination string for consistent display
    const destName = batch.destination_name || batch.destination || 'N/A';
    const destRoom = batch.destination_room ? ` (${batch.destination_room})` : '';
    const fullDestination = destName + destRoom;

    let itemsHtml = '';
    if (batch.items && batch.items.length > 0) {
        batch.items.forEach(item => {
            itemsHtml += `
                <tr>
                    <td><strong>${escapeHtml(item.name || 'N/A')}</strong></td>
                    <td><code>${escapeHtml(item.serial || 'N/A')}</code></td>
                    <td class="text-center">${item.quantity || 1}</td>
                    <td><span class="badge bg-info-subtle text-info-emphasis border border-info-subtle rounded-pill px-3">${escapeHtml(fullDestination)}</span></td>
                    <td class="text-center"><span class="badge bg-secondary">${item.status || 'available'}</span></td>
                </tr>
            `;
        });
    } else {
        itemsHtml = '<tr><td colspan="5" class="text-center py-4 text-muted">No items found</td></tr>';
    }

    // Milestone Tracker logic
    let isApproved = false;
    let isTechOnboard = false;
    let isLoaded = false;
    let isCompleted = false;
    let isRejected = false;

    const appStatus = (batch.approval_status || '').toLowerCase();
    const bStatus = (batch.status || '').toLowerCase();

    if (appStatus === 'approved' || appStatus === 'completed' || bStatus === 'approved' || bStatus === 'completed') {
        isApproved = true;
    }
    if (appStatus === 'rejected' || bStatus === 'rejected') {
        isRejected = true;
        isApproved = false;
    }
    if (parseInt(batch.tech_onboard) === 1) {
        isTechOnboard = true;
    }
    if (parseInt(batch.driver_verified) === 1) {
        isLoaded = true;
    }
    if (bStatus === 'completed') {
        isCompleted = true;
    }

    let fillPercent = 10;
    let stepSubmittedClass = 'completed';
    let stepApprovedClass = isApproved ? (isLoaded ? 'completed' : 'active') : (isRejected ? 'rejected' : 'active');
    let stepLoadedClass = isLoaded ? (isCompleted ? 'completed' : 'active') : (isTechOnboard ? 'active' : (isApproved && !isRejected ? 'active' : ''));
    let stepCompletedClass = isCompleted ? 'completed' : (isLoaded ? 'active' : '');

    if (isRejected) fillPercent = 40;
    else if (isCompleted) fillPercent = 100;
    else if (isLoaded) fillPercent = 75;
    else if (isTechOnboard) fillPercent = 65;
    else if (isApproved) fillPercent = 40;
    else fillPercent = 25; // Awaiting approval

    let dName = batch.transport_driver || '';
    if (dName === 'Valetine' || dName === 'valentinb') dName = 'Bembeleza Valentin';
    
    let driverSublabel = isLoaded ? dName + ' (Loaded)' : (isTechOnboard ? 'Tech Onboard' : (isApproved && !isRejected ? 'Awaiting Dispatch' : '-'));

    const milestoneHtml = `
    <div class="row">
        <div class="col-12">
            <div class="milestone-tracker">
                <div class="milestone-tracker-fill" style="width: ${fillPercent}%;"></div>
                
                <div class="milestone-step ${stepSubmittedClass}">
                    <div class="milestone-icon"><i class="fas fa-file-import"></i></div>
                    <div class="milestone-label">Submitted</div>
                    <div class="milestone-sublabel">${escapeHtml(batch.submitted_by || '-')}</div>
                </div>
                
                <div class="milestone-step ${stepApprovedClass}">
                    <div class="milestone-icon"><i class="fas fa-clipboard-check"></i></div>
                    <div class="milestone-label">Approved</div>
                    <div class="milestone-sublabel">${isRejected ? 'Rejected' : (isApproved ? 'Approved' : 'Awaiting Approval')}</div>
                </div>
                
                <div class="milestone-step ${stepLoadedClass}">
                    <div class="milestone-icon"><i class="fas fa-truck-loading"></i></div>
                    <div class="milestone-label">Driver Loaded</div>
                    <div class="milestone-sublabel">${escapeHtml(driverSublabel)}</div>
                </div>
                
                <div class="milestone-step ${stepCompletedClass}">
                    <div class="milestone-icon"><i class="fas fa-flag-checkered"></i></div>
                    <div class="milestone-label">Completed</div>
                    <div class="milestone-sublabel">${isCompleted ? 'Delivered' : '-'}</div>
                </div>
            </div>
        </div>
    </div>
    <hr style="opacity: 0.1; margin-top: 0; margin-bottom: 1.5rem;">
    `;

    const html = `
        ${milestoneHtml}
        <div class="row">
            <div class="col-md-6">
                <div class="detail-section">
                    <div class="detail-title"><i class="fas fa-info-circle me-2"></i>Batch Information</div>
                    <div class="detail-grid">
                        <div class="detail-item">
                            <div class="detail-label">Batch ID</div>
                            <div class="detail-value"><code>${escapeHtml(batch.batch_id)}</code></div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Date Submitted</div>
                            <div class="detail-value">${batch.date ? moment(batch.date).format('MMM D, YYYY HH:mm') : 'N/A'}</div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Status</div>
                            <div class="detail-value">${escapeHtml(batch.status || 'N/A')}</div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Movement Type</div>
                            <div class="detail-value">${escapeHtml(batch.movement_type || 'N/A')}</div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Total Items</div>
                            <div class="detail-value">${batch.item_count || 0} items (${batch.total_quantity || 0} units)</div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Driver</div>
                            <div class="detail-value">${escapeHtml(batch.transport_driver || 'N/A')}</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="detail-section">
                    <div class="detail-title"><i class="fas fa-users me-2"></i>Personnel</div>
                    <div class="detail-grid">
                        <div class="detail-item">
                            <div class="detail-label">Technician</div>
                            <div class="detail-value">${escapeHtml(batch.technician_name || 'Unknown')}</div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Requested By</div>
                            <div class="detail-value">${escapeHtml(batch.submitted_by_name || 'Unknown')}</div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Project Manager</div>
                            <div class="detail-value">${escapeHtml(batch.project_manager || 'N/A')}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col-md-6">
                <div class="detail-section">
                    <div class="detail-title"><i class="fas fa-map-marker-alt me-2"></i>Location Details</div>
                    <div class="detail-grid">
                        <div class="detail-item">
                            <div class="detail-label">Source Location</div>
                            <div class="detail-value">${escapeHtml(batch.source_name || 'N/A')}</div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Destination</div>
                            <div class="detail-value">${escapeHtml(batch.destination_name || 'N/A')} ${batch.destination_room ? '(' + escapeHtml(batch.destination_room) + ')' : ''}</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="detail-section">
                    <div class="detail-title"><i class="fas fa-clipboard-list me-2"></i>Job Details</div>
                    <div class="detail-grid">
                        <div class="detail-item">
                            <div class="detail-label">Job Sheet</div>
                            <div class="detail-value">
                                ${escapeHtml(batch.job_sheet || 'N/A')}
                                ${batch.jobsheet_file ? `
                                    <div class="mt-2">
                                        <a href="${escapeHtml(batch.jobsheet_file)}" target="_blank" class="btn btn-xs btn-outline-primary py-1 px-2 fw-bold" style="font-size: 0.75rem; border-radius: 6px;">
                                            <i class="fas fa-file-download me-1"></i> View Uploaded Jobsheet
                                        </a>
                                    </div>
                                ` : ''}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="detail-section">
            <div class="detail-title"><i class="fas fa-boxes me-2"></i>Items (${batch.items ? batch.items.length : 0})</div>
            <div class="table-responsive">
                <table class="table table-sm table-hover items-table">
                    <thead>
                        <tr>
                            <th>Item Name</th>
                            <th>Serial Number</th>
                            <th class="text-center">Qty</th>
                            <th>Location</th>
                            <th class="text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody>${itemsHtml}</tbody>
                </table>
            </div>
        </div>
        ${batch.approval_notes ? `<div class="detail-section"><div class="alert alert-info"><strong>Approval Notes:</strong><br>${escapeHtml(batch.approval_notes)}</div></div>` : ''}
        ${batch.rejection_reason ? `<div class="detail-section"><div class="alert alert-danger"><strong>Rejection Reason:</strong><br>${escapeHtml(batch.rejection_reason)}</div></div>` : ''}
        
        ${(batch.status === 'pending' || batch.approval_status === 'pending') ? `
            <div class="detail-section mt-4 pt-3 border-top" id="reviewSection">
                <div class="detail-title text-primary"><i class="fas fa-edit me-2"></i>Review Decision</div>
                <div class="mb-3">
                    <label for="reviewNotes" class="form-label fw-bold">Approval Notes / Rejection Reason <span class="text-danger" id="rejectionRequiredLabel" style="display:none;">* Required for rejection</span></label>
                    <textarea class="form-control" id="reviewNotes" rows="3" placeholder="Type your notes or reason here..."></textarea>
                </div>
            </div>
        ` : ''}
    `;

    $('#batchDetailContent').html(html);
    
    // Update modal footer buttons based on status
    const footer = $('#batchDetailModal .modal-footer');
    footer.find('.action-btn').remove(); // Remove existing action buttons
    
    if (batch.status === 'pending' || batch.approval_status === 'pending') {
        const approveBtn = $(`
            <button type="button" class="btn btn-success action-btn" onclick="approveBatch('${batch.batch_id}')">
                <i class="fas fa-check me-1"></i>Approve
            </button>
        `);
        const rejectBtn = $(`
            <button type="button" class="btn btn-danger action-btn" onclick="rejectBatch('${batch.batch_id}')">
                <i class="fas fa-times me-1"></i>Reject
            </button>
        `);
        footer.prepend(rejectBtn);
        footer.prepend(approveBtn);
    } else if (batch.status === 'approved' || batch.status === 'completed') {
        const downloadBtn = $(`
            <a href="batch_report.php?batch_id=${encodeURIComponent(batch.batch_id)}" class="btn btn-success action-btn" target="_blank">
                <i class="fas fa-file-pdf me-1"></i>Download Report
            </a>
        `);
        footer.prepend(downloadBtn);
        
        if (['stock_to_stock', 'stock_to_venue_transport'].includes(batch.movement_type)) {
            const gatePassBtn = $(`
                <a href="gate_pass.php?batch_id=${encodeURIComponent(batch.batch_id)}" class="btn btn-warning action-btn" target="_blank">
                    <i class="fas fa-ticket-alt me-1"></i>Gate Pass
                </a>
            `);
            footer.prepend(gatePassBtn);
        }
    }
}

// ==================== TIMELINE & CARDS ====================
function loadTimelineData() {
    console.log('Loading timeline data...');
    const date_from = $('#date_from').val();
    const date_to = $('#date_to').val();

    $.ajax({
        url: 'api/batches/timeline.php',
        method: 'GET',
        data: { date_from, date_to },
        success: function(response) {
            console.log('Timeline API response:', response);
            if (response.success && response.timeline) {
                updateTimelineView(response.timeline);
            } else {
                updateTimelineView([]);
            }
        },
        error: function() {
            updateTimelineView([]);
        }
    });
}

function updateTimelineView(timelineData) {
    let html = '';
    if (timelineData.length === 0) {
        html = '<div class="text-center py-4">No timeline data available</div>';
    } else {
        timelineData.forEach(item => {
            const statusClass = {
                'pending': 'badge-pending',
                'approved': 'badge-approved',
                'rejected': 'badge-rejected',
                'completed': 'badge-completed'
            }[item.status] || 'badge-secondary';

            html += `
                <div class="timeline-item">
                    <div class="timeline-marker"></div>
                    <div class="timeline-content">
                        <div class="timeline-date">${moment(item.date).format('MMM D, YYYY HH:mm')}</div>
                        <div class="timeline-title">
                            <a href="#" onclick="viewBatchDetails('${item.batch_id}'); return false;">${escapeHtml(item.batch_id)}</a>
                        </div>
                        <div class="row">
                            <div class="col-md-6"><small class="text-muted">Technician:</small> ${escapeHtml(item.technician)}</div>
                            <div class="col-md-6"><small class="text-muted">Items:</small> ${item.item_count} (${item.total_quantity} qty)</div>
                            <div class="col-md-6"><small class="text-muted">Location:</small> ${escapeHtml(item.location)}</div>
                            <div class="col-md-6 d-flex align-items-center gap-2">
                                <small class="text-muted">Status:</small> 
                                <span class="batch-badge ${statusClass}">${item.status}</span>
                                ${(item.status === 'approved' || item.status === 'completed') ? `
                                    <a href="batch_report.php?batch_id=${encodeURIComponent(item.batch_id)}&download=1" class="btn btn-xs btn-outline-success py-0 px-2" title="Download Report" target="_blank" style="font-size: 0.7rem;">
                                        <i class="fas fa-file-pdf"></i> Report
                                    </a>
                                    ${['stock_to_stock', 'stock_to_venue_transport'].includes(item.movement_type) ? `
                                    <a href="gate_pass.php?batch_id=${encodeURIComponent(item.batch_id)}&download=1" class="btn btn-xs btn-outline-warning py-0 px-2" title="Download Gate Pass" target="_blank" style="font-size: 0.7rem;">
                                        <i class="fas fa-ticket-alt"></i> Gate Pass
                                    </a>
                                    ` : ''}
                                ` : ''}
                            </div>
                        </div>
                    </div>
                </div>
            `;
        });
    }
    $('#timelineContainer').html(html);
}

function loadCardsData() {
    console.log('Loading cards data...');
    const date_from = $('#date_from').val();
    const date_to = $('#date_to').val();

    $.ajax({
        url: 'api/batches/list.php',
        method: 'GET',
        data: { date_from, date_to },
        success: function(response) {
            if (response.success && response.batches) {
                updateCardsView(response.batches);
            } else {
                updateCardsView([]);
            }
        },
        error: function() {
            updateCardsView([]);
        }
    });
}

function updateCardsView(batches) {
    let html = '';
    if (batches.length === 0) {
        html = '<div class="col-12 text-center py-4">No batches found</div>';
    } else {
        batches.forEach(batch => {
            const statusClass = {
                'pending': 'badge-pending',
                'approved': 'badge-approved',
                'rejected': 'badge-rejected',
                'completed': 'badge-completed'
            }[batch.status] || 'badge-secondary';

            html += `
                <div class="col-md-6 col-lg-4 mb-3">
                    <div class="card h-100">
                        <div class="card-header bg-white d-flex justify-content-between align-items-center">
                            <code>${escapeHtml(batch.batch_id)}</code>
                            <span class="batch-badge ${statusClass}">${batch.status}</span>
                        </div>
                        <div class="card-body">
                            <div class="mb-2"><i class="fas fa-calendar me-2 text-muted"></i>${moment(batch.date).format('MMM D, YYYY HH:mm')}</div>
                            <div class="mb-2"><i class="fas fa-user me-2 text-muted"></i>${escapeHtml(batch.technician)}</div>
                            <div class="mb-2"><i class="fas fa-box me-2 text-muted"></i>${batch.item_count} items (${batch.total_quantity} total)</div>
                            <div class="mb-2"><i class="fas fa-map-marker-alt me-2 text-muted"></i>${escapeHtml(batch.location)}</div>
                            <div class="mb-2"><i class="fas fa-file-alt me-2 text-muted"></i>${escapeHtml(batch.job_sheet || '-')}</div>
                        </div>
                        <div class="card-footer bg-white d-flex justify-content-between align-items-center">
                            <button class="btn btn-sm btn-info" onclick="viewBatchDetails('${batch.batch_id}')">
                                <i class="fas fa-eye me-1"></i>View Details
                            </button>
                            ${(batch.status === 'approved' || batch.status === 'completed') ? `
                                <a href="batch_report.php?batch_id=${encodeURIComponent(batch.batch_id)}&download=1" class="btn btn-sm btn-success" target="_blank">
                                    <i class="fas fa-file-pdf me-1"></i>Report
                                </a>
                                ${['stock_to_stock', 'stock_to_venue_transport'].includes(batch.movement_type) ? `
                                <a href="gate_pass.php?batch_id=${encodeURIComponent(batch.batch_id)}&download=1" class="btn btn-sm btn-warning" target="_blank">
                                    <i class="fas fa-ticket-alt me-1"></i>Gate Pass
                                </a>
                                ` : ''}
                            ` : ''}
                        </div>
                    </div>
                </div>
            `;
        });
    }
    $('#cardsContainer').html(html);
}

// ==================== VIEW TOGGLING ====================
function toggleView(view) {
    currentView = view;
    $('#tableView, #timelineView, #cardsView').hide();
    $(`#${view}View`).show();
}

// ==================== FILTERS ====================
function clearFilters() {
    $('#date_from').val(moment().subtract(30, 'days').format('YYYY-MM-DD'));
    $('#date_to').val(moment().format('YYYY-MM-DD'));
    $('#status, #technician_id, #location, #search').val('');
    if ($('#daterange').data('daterangepicker')) {
        $('#daterange').data('daterangepicker').setStartDate(moment().subtract(30, 'days'));
        $('#daterange').data('daterangepicker').setEndDate(moment());
    }
    loadBatches();
    loadStatistics();
    loadTimelineData();
    loadCardsData();
    showNotification('success', 'Filters cleared');
}

function refreshData() {
    loadBatches();
    loadStatistics();
    loadTimelineData();
    loadCardsData();
    showNotification('success', 'Data refreshed');
}

function exportToExcel() {
    showNotification('info', 'Export feature coming soon');
}

function viewReport(batchId) { viewBatchDetails(batchId); }
function printBatch(batchId) { viewBatchDetails(batchId); }
function downloadBatchPDF(batchId) {
    window.open(`batch_report.php?batch_id=${encodeURIComponent(batchId)}&download=1`, '_blank');
}
function printCurrentBatch() { if (currentBatchId) viewBatchDetails(currentBatchId); }
function approveBatch(batchId) {
    const notes = $('#reviewNotes').val();
    
    Swal.fire({
        title: 'Approve Batch?',
        text: "Are you sure you want to approve this batch?",
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#28a745',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Yes, Approve'
    }).then((result) => {
        if (result.isConfirmed) {
            processBatchAction(batchId, 'approve', notes);
        }
    });
}

function rejectBatch(batchId) {
    const reason = $('#reviewNotes').val();
    
    if (!reason || reason.trim() === '') {
        $('#rejectionRequiredLabel').show();
        $('#reviewNotes').addClass('is-invalid').focus();
        showNotification('error', 'Please provide a reason for rejection in the notes field.');
        return;
    }

    Swal.fire({
        title: 'Reject Batch?',
        text: "Are you sure you want to reject this batch?",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Yes, Reject'
    }).then((result) => {
        if (result.isConfirmed) {
            processBatchAction(batchId, 'reject', reason);
        }
    });
}

function processBatchAction(batchId, action, notes) {
    showLoading();
    $.ajax({
        url: 'api/batches/approve.php',
        method: 'POST',
        contentType: 'application/json',
        data: JSON.stringify({
            batch_id: batchId,
            action: action,
            notes: notes
        }),
        success: function(response) {
            if (response.success) {
                Swal.fire({
                    icon: 'success',
                    title: 'Success!',
                    text: response.message,
                    timer: 2000
                });
                
                // Properly hide modal and clean up backdrops
                const modalEl = document.getElementById('batchDetailModal');
                const modal = bootstrap.Modal.getInstance(modalEl);
                if (modal) modal.hide();
                
                setTimeout(() => {
                    document.querySelectorAll('.modal-backdrop').forEach(el => el.remove());
                    document.body.classList.remove('modal-open');
                    document.body.style.paddingRight = '';
                    document.body.style.overflow = '';
                }, 300);
                
                refreshData();
            } else {
                Swal.fire('Error', response.message || 'Action failed', 'error');
            }
        },
        error: function() {
            Swal.fire('Error', 'Server communication error', 'error');
        },
        complete: function() {
            hideLoading();
        }
    });
}
function initializeCharts() { console.log('Charts initialized'); }

// ==================== LOGOUT FUNCTIONS ====================
function showLogoutToast(event) {
    event.preventDefault();
    $('#logoutToast').show();
    $('#toastOverlay').show();
    $('body').css('overflow', 'hidden');
}

function hideLogoutToast() {
    $('#logoutToast').hide();
    $('#toastOverlay').hide();
    $('body').css('overflow', '');
}

document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        hideLogoutToast();
    }
});

// Real-time Auto-Refresh
setInterval(() => {
    if (!$('#batchDetailModal').hasClass('show')) {
        loadBatches(true); // Silently refresh the list
    } else if (currentBatchId) {
        // If viewing a batch, silently refresh its status
        viewBatchDetails(currentBatchId, true);
    }
}, 10000);

    </script>
</body>

</html>