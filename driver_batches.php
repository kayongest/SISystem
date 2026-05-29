<?php
// driver_batches.php
session_start();
require_once 'includes/db_connect.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'] ?? 'user';
$user_name = $_SESSION['full_name'] ?? $_SESSION['username'] ?? 'User';

// Initialize variables for filter
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : date('Y-m-d', strtotime('-30 days'));
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : date('Y-m-d');

$current_page = 'driver_batches.php';
// Include bootstrap and database connection
require_once 'bootstrap.php';
require_once 'includes/functions.php';

// RESTRICT ACCESS - Only drivers and admins can access this page
$allowed_roles = ['driver', 'admin'];
if (!in_array($user_role, $allowed_roles)) {
    header('Location: dashboard_full.php');
    exit();
}

// Technician ID logic is not needed, we use user_id
$driver_name = $_SESSION['full_name'] ?? $_SESSION['username'] ?? 'Unknown';

$pageTitle = "Driver Batches - aBility";
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Driver Transport Batches - EventTrack</title>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.bootstrap5.min.css">

    <style>
        @import url('https://fonts.googleapis.com/css2?family=Marvel:ital,wght@0,400;0,700;1,400;1,700&display=swap');

        * {
            font-family: "Marvel", sans-serif;
        }

        /* Checklist Button Styles */
        .btn-check:checked + .btn-outline-secondary.checklist-btn {
            background-color: #f0fdf4 !important;
            border-color: #198754 !important;
            color: #198754 !important;
        }
        .btn-check:checked + .btn-outline-secondary.checklist-btn i {
            color: #198754 !important;
        }
        .checklist-btn {
            border-width: 2px !important;
            border-radius: 8px !important;
            padding: 12px 15px !important;
            transition: all 0.2s ease;
        }
        .checklist-btn:hover {
            background-color: #f8f9fa;
            color: #495057;
        }


        /* Navigation Adjustment */
        .navbar {
            margin-bottom: 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            border-radius: 5px;
            padding: 10px;
            box-shadow: 0 2px 15px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease;
            text-align: start;
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
            margin: 0 auto 5px auto;
        }

        .stat-icon.blue {
            background: rgba(67, 97, 238, 0.1);
            color: #4361ee;
        }

        .stat-icon.green {
            background: rgba(40, 167, 69, 0.1);
            color: #28a745;
        }

        .stat-icon.orange {
            background: rgba(255, 193, 7, 0.1);
            color: #ffc107;
        }

        .stat-icon.red {
            background: rgba(220, 53, 69, 0.1);
            color: #dc3545;
        }

        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 5px;
        }

        .stat-label {
            color: #6c757d;
            font-size: 0.85rem;
        }

        /* Filter Section */
        .filter-section {
            background: white;
            border-radius: 10px;
            padding: 20px;
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

        /* Table Container */
        .table-container {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 15px rgba(0, 0, 0, 0.1);
        }

        .batch-badge {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
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

        /* Modal Styling */
        .modal-content {
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        }

        .modal-header {
            padding: 20px 24px;
            border-bottom: none;
        }

        .modal-header .modal-title {
            font-weight: 700;
            font-size: 1.3rem;
        }

        .modal-body {
            max-height: 70vh;
            overflow-y: auto;
            padding: 24px;
        }

        .modal-body::-webkit-scrollbar {
            width: 8px;
        }

        .modal-body::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 10px;
        }

        .modal-body::-webkit-scrollbar-thumb {
            background: #234c6a;
            border-radius: 10px;
        }

        .modal-body::-webkit-scrollbar-thumb:hover {
            background: #1a3a52;
        }

        .modal-footer {
            padding: 16px 24px;
            border-top: 1px solid #e9ecef;
            gap: 10px;
        }

        /* Batch Information Cards inside Modal */
        .batch-info-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
            border: 1px solid #e9ecef;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .batch-info-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }

        .batch-info-title {
            font-size: 16px;
            font-weight: 700;
            color: #234c6a;
            margin-bottom: 16px;
            padding-bottom: 10px;
            border-bottom: 2px solid #e9ecef;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .batch-info-title i {
            font-size: 18px;
            color: #234c6a;
        }

        /* Two Column Layout inside Modal */
        .info-two-column {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
        }

        .info-item {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .info-label {
            font-size: 09px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #6c757d;
        }

        .info-value {
            font-size: 12px;
            font-weight: 500;
            color: #1a2e3f;
            word-break: break-word;
        }

        .info-value code {
            background: #f8f9fa;
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 13px;
        }





        /* Global font size control for modal */
        .modal-body {
            font-size: 0.8rem;
        }

        /* Adjust specific elements to maintain hierarchy */
        .modal-body .batch-info-title {
            font-size: 1rem;
            /* Slightly larger for section headers */
        }

        .modal-body .info-label {
            font-size: 0.7rem;
            /* Labels slightly smaller */
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .modal-body .info-value {
            font-size: 0.8rem;
        }

        .modal-body .badge {
            font-size: 0.7rem;
            padding: 4px 8px;
        }

        .modal-body code {
            font-size: 0.75rem;
        }

        .modal-items-table th,
        .modal-items-table td {
            font-size: 0.75rem;
        }

        .modal-items-table th {
            font-size: 0.7rem;
        }

        .modal-alert {
            font-size: 0.75rem;
        }

        /* Override any larger text */
        .modal-body .modal-status-badge {
            font-size: 0.65rem;
        }

        .modal-body .batch-info-title i {
            font-size: 0.9rem;
        }








        /* Status Badge in Modal */
        .modal-status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
        }

        .modal-status-pending {
            background: rgba(255, 193, 7, 0.15);
            color: #ffc107;
        }

        .modal-status-approved {
            background: rgba(40, 167, 69, 0.15);
            color: #28a745;
        }

        .modal-status-rejected {
            background: rgba(220, 53, 69, 0.15);
            color: #dc3545;
        }

        .modal-status-completed {
            background: rgba(0, 123, 255, 0.15);
            color: #007bff;
        }

        /* Items Table inside Modal */
        .modal-items-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }

        .modal-items-table th {
            background: #f8f9fa;
            padding: 12px;
            text-align: left;
            font-weight: 600;
            color: #1a2e3f;
            border-bottom: 2px solid #e9ecef;
        }

        .modal-items-table td {
            padding: 10px 12px;
            border-bottom: 1px solid #e9ecef;
            vertical-align: middle;
        }

        .modal-items-table tr:hover td {
            background: #f8f9fa;
        }

        /* Alert styling inside modal */
        .modal-alert {
            padding: 12px 16px;
            border-radius: 10px;
            margin-top: 15px;
        }

        .modal-alert-info {
            background: #e8f4f8;
            border-left: 4px solid #17a2b8;
            color: #0c5460;
        }

        .modal-alert-danger {
            background: #f8d7da;
            border-left: 4px solid #dc3545;
            color: #721c24;
        }

        /* Action Buttons */
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

        /* Event Card */
        .event-info {
            background: #f8f9fa;
            padding: 5px 10px;
            border-radius: 6px;
            font-size: 12px;
            margin-top: 3px;
        }

        .event-info i {
            margin-right: 4px;
            color: #234c6a;
        }

        /* Destination Info */
        .destination-info {
            background: #e8f4f8;
            padding: 5px 10px;
            border-radius: 6px;
            font-size: 12px;
            margin-top: 3px;
            border-left: 3px solid #17a2b8;
        }

        .destination-info i {
            margin-right: 4px;
            color: #17a2b8;
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

        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 10px;
            }

            .stat-card {
                padding: 12px;
            }

            .stat-value {
                font-size: 1.5rem;
            }

            .modal-dialog {
                margin: 10px;
            }

            .info-two-column {
                grid-template-columns: 1fr;
                gap: 12px;
            }

            .modal-body {
                padding: 16px;
            }

            .batch-info-card {
                padding: 15px;
            }
        }

        @media (min-width: 768px) {
            .col-md-4 {
                flex: 0 0 auto;
                width: 15%;
            }
        }

        .form-label {
            font-size: smaller;
        }


        /* Stats Cards - Premium Responsive Grid Layout */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(130px, 1fr));
            gap: 12px;
            height: auto;
        }

        .stat-card {
            border-radius: 12px;
            overflow: hidden;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            border: none;
            color: #ffffff;
            padding: 16px 15px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
            display: flex;
            flex-direction: column;
            justify-content: center;
            min-height: 90px;
        }

        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(5, 105, 152, 0.15);
        }

        /* Filter Section - make it match height */
        .filter-section {
            background: white;
            border-radius: 16px;
            padding: 20px;
            box-shadow: 0 2px 15px rgba(0, 0, 0, 0.08);
            height: 100%;
            display: flex;
            flex-direction: column;
        }

        .filter-title {
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 20px;
            color: #495057;
            border-bottom: 2px solid #e9ecef;
            padding-bottom: 10px;
        }

        .filter-title i {
            color: #4361ee;
            margin-right: 8px;
        }

        /* Card Header Styles */
        .stat-card-header {
            padding: 15px;
            text-align: center;
            border-bottom: none;
        }

        .stat-card-header.blue {
            background: linear-gradient(135deg, #4361ee 0%, #3a56d4 100%);
            color: white;
        }

        .stat-card-header.green {
            background: linear-gradient(135deg, #28a745 0%, #20963d 100%);
            color: white;
        }

        .stat-card-header.orange {
            background: linear-gradient(135deg, #ffc107 0%, #e0a800 100%);
            color: white;
        }

        .stat-card-header.red {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
            color: white;
        }

        .stat-card-header i {
            font-size: 28px;
            margin-bottom: 8px;
        }

        .stat-card-header .stat-header-value {
            font-size: 28px;
            font-weight: 800;
            margin: 5px 0;
            line-height: 1.2;
        }

        .stat-card-header .stat-header-label {
            font-size: 11px;
            opacity: 0.9;
            letter-spacing: 0.5px;
        }

        /* List Group Items */
        .stat-list-group {
            list-style: none;
            margin: 0;
            padding: 0;
        }

        .stat-list-item {
            padding: 10px 15px;
            background: white;
            border-bottom: 1px solid #e9ecef;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: background 0.2s ease;
        }

        .stat-list-item:last-child {
            border-bottom: none;
        }

        .stat-list-item:hover {
            background: #f8f9fa;
        }

        .stat-list-label {
            font-size: 12px;
            font-weight: 600;
            color: #f5f7f8;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .stat-list-label i {
            margin-right: 6px;
            font-size: 11px;
        }

        .stat-list-value {
            font-size: 16px;
            font-weight: 700;
            color: #1a2e3f;
        }

        .stat-list-value small {
            font-size: 10px;
            font-weight: 400;
            color: #6c757d;
            margin-left: 4px;
        }

        /* Responsive */
        @media (max-width: 992px) {
            .stats-grid {
                gap: 12px;
            }

            .stat-card-header {
                padding: 12px;
            }

            .stat-card-header i {
                font-size: 24px;
            }

            .stat-card-header .stat-header-value {
                font-size: 24px;
            }

            .stat-list-item {
                padding: 8px 12px;
            }

            .stat-list-value {
                font-size: 14px;
            }
        }

        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 10px;
            }
        }

        @media (max-width: 576px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
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

    <!-- Main Content -->
    <div class="container-fluid px-4 py-4">
        <!-- Page Title -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <button class="btn btn-sm outline- text-white" style="background-color: #10914c;" onclick="refreshData()">
                    <i class="fas fa-sync-alt me-1"></i> Refresh
                </button>
                <button class="btn btn-sm text-white" style="background-color: #7952b3;">
                    <a href="mobile_app.php" style="color:#fff; text-decoration: none;">
                        <i class="fas fa-mobile-alt"></i> Mobile App View
                    </a>
                </button>
            </div>
        </div>

        <div class="row mb-4">
            <!-- Filter Section - Right Side (6 columns) -->
            <div class="col-lg-6">
                <div class="filter-section">
                    <div class="filter-title">
                        <i class="fas fa-filter"></i> Filter My Batches
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">From Date</label>
                            <input type="date" class="form-control" id="date_from" value="<?php echo $date_from; ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">To Date</label>
                            <input type="date" class="form-control" id="date_to" value="<?php echo $date_to; ?>">
                        </div>
                        <div class="col-md-12">
                            <label class="form-label">Status</label>
                            <select class="form-select" id="status">
                                <option value="">All Status</option>
                                <option value="pending">Pending</option>
                                <option value="approved">Approved</option>
                                <option value="rejected">Rejected</option>
                                <option value="completed">Completed</option>
                            </select>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label">Search</label>
                            <div class="input-group">
                                <input type="text" class="form-control" id="search" placeholder="Search by Batch ID, Event, Job Sheet...">
                                <button class="btn btn-sm text-white" style="background-color: #3c4f6f;" onclick="loadBatches()">
                                    <i class="fas fa-search"></i> Search
                                </button>
                                <button class="btn btn-outline-secondary" onclick="clearFilters()">
                                    <i class="fas fa-times"></i> Clear
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <!-- Stats Container - Left Side (6 columns) -->
            <div class="col-lg-6 mb-3 mb-lg-0">
                <div class="stats-grid" id="statsContainer">
                    <!-- Stats will be loaded dynamically -->
                </div>
            </div>

        </div>

        <!-- Batches Table -->
        <div class="table-container">
            <h5 class="mb-3"><i class="fas fa-truck me-2 text-primary"></i>My Transport Batches</h5>
            <table id="batchesTable" class="table table-hover" style="width:100%">
                <thead>
                    <tr>
                        <th>Batch ID</th>
                        <th>Date</th>
                        <th>Event Name</th>
                        <th>Job Sheet</th>
                        <th>Items</th>
                        <th>Destination</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- Data will be loaded dynamically -->
                </tbody>
            </table>
        </div>
    </div>

    <!-- Batch Details Modal (View Only) -->
    <div class="modal fade" id="batchDetailModal" tabindex="-1" aria-labelledby="batchDetailModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header bg-gradient-primary text-white" style="background: linear-gradient(135deg, #1a2e3f 0%, #234c6a 100%);">
                    <div>
                        <h5 class="modal-title" id="batchDetailModalLabel">
                            <i class="fas fa-clipboard-list me-2"></i>Batch Details
                        </h5>
                        <small class="d-block mt-1 opacity-75">Review complete batch information</small>
                    </div>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body p-4">
                    <div id="batchDetailContent">
                        <div class="text-center py-5">
                            <div class="spinner-border text-primary" style="width: 3rem; height: 3rem;" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                            <p class="mt-3 text-muted">Loading batch details...</p>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-sm btn- text-white" style="background-color: #85160e;" data-bs-dismiss="modal">
                        <i class="fas fa-times me-1"></i> Close
                    </button>
                    <button type="button" id="onboardTechBtn" class="btn btn-sm text-white d-none" style="background-color: #f39c12;" onclick="confirmTechOnboard()">
                        <i class="fas fa-user-check me-1"></i> Tech Onboard
                    </button>
                    <button type="button" id="verifyLoadBtn" class="btn btn-sm btn-success d-none" onclick="verifyDriverLoad()">
                        <i class="fas fa-check-circle me-1"></i> Verify Loaded
                    </button>
                    <button type="button" id="completeDeliveryBtn" class="btn btn-sm btn-info text-white d-none" onclick="completeDriverDelivery()">
                        <i class="fas fa-flag-checkered me-1"></i> Complete Delivery
                    </button>
                    <button type="button" class="btn btn-sm btn- text-white" style="background-color: #0f8094;" onclick="refreshBatchStatus()">
                        <i class="fas fa-sync-alt me-1"></i> Refresh Status
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>
    <script src="https://cdn.jsdelivr.net/momentjs/latest/moment.min.js"></script>

    <script>
        let batchesTable;
        let currentBatchId = null;
        let technicianId = null;

        // Logout functions
        function showLogoutToast(event) {
            event.preventDefault();
            document.getElementById('logoutToast').style.display = 'block';
            document.getElementById('toastOverlay').style.display = 'block';
            document.body.style.overflow = 'hidden';
        }

        function hideLogoutToast() {
            document.getElementById('logoutToast').style.display = 'none';
            document.getElementById('toastOverlay').style.display = 'none';
            document.body.style.overflow = '';
        }

        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') hideLogoutToast();
        });



        // Initialize
        $(document).ready(function() {
            loadBatches();
        });

        // Load Batches
        function loadBatches(isSilent = false) {
            const date_from = $('#date_from').val();
            const date_to = $('#date_to').val();
            const status = $('#status').val();
            const search = $('#search').val();

            if (!isSilent) showLoading();

            $.ajax({
                url: 'api/batches/driver_list.php',
                method: 'GET',
                data: {
                    date_from: date_from,
                    date_to: date_to,
                    status: status,
                    search: search
                },
                success: function(response) {
                    if (response.success && response.batches) {
                        updateBatchesTable(response.batches);
                        updateStatistics(response.stats);
                    } else {
                        updateBatchesTable([]);
                        updateStatistics({
                            total_batches: 0,
                            total_items: 0,
                            pending_batches: 0,
                            approved_batches: 0,
                            gate_passes: 0
                        });
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Error loading batches:', error);
                    updateBatchesTable([]);
                    showNotification('error', 'Failed to load batches');
                },
                complete: function() {
                    hideLoading();
                }
            });
        }

        function updateStatistics(stats) {
            const html = `
        <div class="stat-card" style="background-color: #393E46;">
            
            <div class="stat-content">
                <div class="stat-value">${stats.total_batches || 0}</div>
                <div class="stat-label text-white">Total Batches</div>
            </div>
        </div>
        <div class="stat-card" style="background-color: #a06f27;">
            <div class="stat-content">
                <div class="stat-value">${stats.pending_batches || 0}</div>
                <div class="stat-label text-white">Pending Approval</div>
            </div>
        </div>
        <div class="stat-card" style="background-color: #1f6f8b;">
            <div class="stat-content">
                <div class="stat-value">${stats.approved_batches || 0}</div>
                <div class="stat-label text-white">Approved</div>
            </div>
        </div>
        <div class="stat-card" style="background-color: #1e6f5c;">
            <div class="stat-content">
                <div class="stat-value">${stats.completed_batches || 0}</div>
                <div class="stat-label text-white">Completed</div>
            </div>
        </div>
        <div class="stat-card" style="background-color: #7952b3;">
            <div class="stat-content">
                <div class="stat-value">${stats.gate_passes || 0}</div>
                <div class="stat-label text-white">Gate Passes</div>
            </div>
        </div>
    `;
            $('#statsContainer').html(html);
        }


        function updateBatchesTable(batches) {
            if ($.fn.DataTable.isDataTable('#batchesTable')) {
                batchesTable.clear().rows.add(batches).draw(false);
                return;
            }

            batchesTable = $('#batchesTable').DataTable({
                data: batches,
                columns: [{
                        data: 'batch_id',
                        render: function(data) {
                            return `<code>${escapeHtml(data)}</code>`;
                        }
                    },
                    {
                        data: 'date',
                        render: function(data) {
                            return moment(data).format('MMM D, YYYY HH:mm');
                        }
                    },
                    {
                        data: 'event_name',
                        render: function(data) {
                            return data ? `<div class="event-info"><i class="fas fa-calendar-alt"></i> ${escapeHtml(data)}</div>` : '—';
                        }
                    },
                    {
                        data: 'job_sheet',
                        render: function(data) {
                            return data ? `<div class="event-info"><i class="fas fa-file-alt"></i> ${escapeHtml(data)}</div>` : '—';
                        }
                    },
                    {
                        data: 'item_count',
                        className: 'text-center'
                    },
                    {
                        data: 'destination',
                        render: function(data) {
                            return data ? `<div class="destination-info"><i class="fas fa-map-marker-alt"></i> ${escapeHtml(data)}</div>` : '—';
                        }
                    },
                    {
                        data: 'status',
                        render: function(data) {
                            const statusClass = {
                                'pending': 'badge-pending',
                                'approved': 'badge-approved',
                                'rejected': 'badge-rejected',
                                'completed': 'badge-completed'
                            } [data] || 'badge-secondary';
                            return `<span class="batch-badge ${statusClass}">${data}</span>`;
                        }
                    },
                    {
                        data: null,
                        render: function(data) {
                            return `
                        <div class="action-buttons">
                            <button class="btn btn-sm btn-icon btn-info" onclick="viewBatchDetails('${data.batch_id}')" title="View Details">
                                <i class="fas fa-eye"></i>
                            </button>
                            ${(data.status === 'approved' || data.status === 'completed') ? `
                            <button class="btn btn-sm btn-icon btn-success" onclick="viewFullReport('${data.batch_id}')" title="Download Report">
                                <i class="fas fa-file-pdf"></i>
                            </button>
                            ${['stock_to_stock', 'stock_to_venue_transport', 'transport'].includes(data.movement_type) ? `
                            <a href="gate_pass.php?batch_id=${encodeURIComponent(data.batch_id)}&download=1" class="btn btn-sm btn-icon btn-warning" title="Download Gate Pass" target="_blank">
                                <i class="fas fa-ticket-alt"></i>
                            </a>
                            ` : ''}
                            ` : ''}
                        </div>
                    `;
                        }
                    }
                ],
                order: [
                    [1, 'desc']
                ],
                pageLength: 5,
                responsive: true,
                dom: '<"d-flex justify-content-between align-items-center mb-3"lf>rt<"d-flex flex-column align-items-center"ip>',
                language: {
                    emptyTable: 'No batches found. Submit your first batch from the Scanner page!',
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
                    $(this).closest('.dataTables_wrapper').find('.pagination').addClass('pagination-sm justify-content-center');
                }
            });
        }

        // Formatting helper functions
        function formatMovementTypeWithData(batch) {
            const movementType = batch.movement_type;
            const sourceName = batch.source_name || 'Unknown';
            const destinationName = batch.destination_name || 'Unknown';
            const destinationRoom = batch.destination_room || '';
            const sourceRoom = batch.source_room || '';

            switch (movementType) {
                case 'stock_to_venue_room':
                    return `Stock (${sourceName}) → Venue Room (${destinationName}${destinationRoom ? ' - ' + destinationRoom : ''})`;
                case 'venue_room_to_stock':
                    return `Venue Room (${sourceName}${sourceRoom ? ' - ' + sourceRoom : ''}) → Stock (${destinationName})`;
                case 'stock_to_stock':
                    return `Stock (${sourceName}) → Stock (${destinationName})`;
                case 'stock_to_venue_transport':
                    return `Stock (${sourceName}) → Venue (${destinationName})`;
                default:
                    return movementType || '—';
            }
        }

        function formatSourceWithData(batch) {
            const movementType = batch.movement_type;
            const sourceName = batch.source_name || '—';
            const sourceRoom = batch.source_room || '';

            if (movementType === 'venue_room_to_stock') {
                return sourceRoom ? `${sourceName} (${sourceRoom})` : sourceName;
            }
            return sourceName;
        }

        function formatDestinationWithData(batch) {
            const movementType = batch.movement_type;
            const destinationName = batch.destination_name || '—';
            const destinationRoom = batch.destination_room || '';

            if (movementType === 'stock_to_venue_room') {
                return destinationRoom ? `${destinationName} (${destinationRoom})` : destinationName;
            }
            return destinationName;
        }

        // View Batch Details - Always fetch fresh data
        function viewBatchDetails(batchId) {
            currentBatchId = batchId;
            showLoading();

            const timestamp = new Date().getTime();

            $.ajax({
                url: 'api/batches/details.php',
                method: 'GET',
                data: {
                    batch_id: batchId,
                    _t: timestamp
                },
                success: function(response) {
                    if (response.success) {
                        displayBatchDetails(response.batch);
                    } else {
                        showNotification('error', 'Failed to load batch details');
                    }
                },
                error: function() {
                    showNotification('error', 'Failed to load batch details');
                },
                complete: function() {
                    hideLoading();
                }
            });

<<<<<<< HEAD
            const modal = new bootstrap.Modal(document.getElementById('batchDetailModal'));
=======
            const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('batchDetailModal'));
>>>>>>> addf346 (Latest Upload - Events cards, OverView, Items status..)
            modal.show();
        }

        function displayBatchDetails(batch) {
            // Format destination for location column
            const destinationDisplay = batch.destination_name || batch.destination || 'N/A';
            const destinationRoom = batch.destination_room ? ` (${batch.destination_room})` : '';
            const fullDestination = destinationDisplay + destinationRoom;

            let itemsHtml = '';
            if (batch.items && batch.items.length > 0) {
                batch.items.forEach(item => {
                    itemsHtml += `
                    <tr>
                        <td><strong>${escapeHtml(item.name)}</strong></td>
                        <td><code>${escapeHtml(item.serial || 'N/A')}</code></td>
                        <td class="text-center">${item.quantity || 1}</td>
                        <td><span class="badge bg-info">${escapeHtml(fullDestination)}</span></td>
                        <td class="text-center"><span class="badge bg-${item.status === 'available' ? 'success' : item.status === 'in_use' ? 'warning' : 'danger'}">${item.status || 'available'}</span></td>
                    </tr>
                `;
                });
            } else {
                itemsHtml = '<tr><td colspan="5" class="text-center py-4 text-muted">No items found</td></tr>';
            }

            const currentStatus = batch.approval_status || batch.status || 'pending';

            const statusClass = {
                'pending': 'badge-pending',
                'approved': 'badge-approved',
                'rejected': 'badge-rejected',
                'completed': 'badge-completed'
            } [currentStatus] || 'badge-secondary';

            const statusIcon = {
                'pending': '<i class="fas fa-clock me-1"></i>',
                'approved': '<i class="fas fa-check-circle me-1"></i>',
                'rejected': '<i class="fas fa-times-circle me-1"></i>',
                'completed': '<i class="fas fa-flag-checkered me-1"></i>'
            } [currentStatus] || '<i class="fas fa-question-circle me-1"></i>';

            const movementTypeDisplay = formatMovementTypeWithData(batch);
            const sourceDisplay = formatSourceWithData(batch);
            const destinationDisplayFormatted = formatDestinationWithData(batch);

            const html = `
            <div class="batch-info-card">
                <div class="batch-info-title">
                    <i class="fas fa-info-circle"></i>
                    <span>Batch Information</span>
                </div>
                <div class="info-two-column">
                    <div class="info-item">
                        <div class="info-label">Batch ID</div>
                        <div class="info-value"><code>${escapeHtml(batch.batch_id)}</code></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Date Submitted</div>
                        <div class="info-value">${moment(batch.date).format('MMM D, YYYY HH:mm')}</div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Status</div>
                        <div class="info-value"><span class="batch-badge ${statusClass}">${statusIcon} ${currentStatus}</span></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Total Items</div>
                        <div class="info-value">${batch.item_count || batch.items?.length || 0} (${batch.total_quantity || 0} units)</div>
                    </div>
                </div>
            </div>

            <div class="batch-info-card">
                <div class="batch-info-title">
                    <i class="fas fa-users"></i>
                    <span>Personnel</span>
                </div>
                <div class="info-two-column">
                    <div class="info-item">
                        <div class="info-label">Technician</div>
                        <div class="info-value">${escapeHtml(batch.technician)}</div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Requested By</div>
                        <div class="info-value">${escapeHtml(batch.submitted_by)}</div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Stock Controller</div>
                        <div class="info-value">${escapeHtml(batch.stock_controller_name || batch.submitted_by)}</div>
                    </div>
                </div>
            </div>

            <div class="batch-info-card">
                <div class="batch-info-title">
                    <i class="fas fa-clipboard-list"></i>
                    <span>Event & Job Details</span>
                </div>
                <div class="info-two-column">
                    <div class="info-item">
                        <div class="info-label">Event Name</div>
                        <div class="info-value">${escapeHtml(batch.event_name || '—')}</div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Job Sheet</div>
                        <div class="info-value">
                            ${escapeHtml(batch.job_sheet || '—')}
                            ${batch.jobsheet_file ? `
                                <div class="mt-1">
                                    <a href="${escapeHtml(batch.jobsheet_file)}" target="_blank" class="btn btn-xs btn-outline-primary py-0 px-2 fw-bold" style="font-size: 0.7rem; border-radius: 4px;">
                                        <i class="fas fa-file-download me-1"></i> View Uploaded Jobsheet
                                    </a>
                                </div>
                            ` : ''}
                        </div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Project Manager</div>
                        <div class="info-value">${escapeHtml(batch.project_manager || '—')}</div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Movement Type</div>
                        <div class="info-value"><span class="badge bg-info">${movementTypeDisplay}</span></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Stock Location</div>
                        <div class="info-value">${escapeHtml(batch.stock_location_name || batch.source_name || '—')}</div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Source</div>
                        <div class="info-value">${escapeHtml(sourceDisplay)}</div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Destination</div>
                        <div class="info-value">${escapeHtml(destinationDisplayFormatted)}</div>
                    </div>
                    ${batch.transport_vehicle_number ? `
                    <div class="info-item">
                        <div class="info-label">Vehicle Number</div>
                        <div class="info-value">${escapeHtml(batch.transport_vehicle_number)}</div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Driver</div>
                        <div class="info-value">${escapeHtml(batch.transport_driver)}</div>
                    </div>
                    ` : ''}
                    ${batch.transport_date ? `
                    <div class="info-item">
                        <div class="info-label">Transport Date</div>
                        <div class="info-value">${moment(batch.transport_date).format('MMM D, YYYY')}</div>
                    </div>
                    ` : ''}
                    ${batch.notes ? `
                    <div class="info-item">
                        <div class="info-label">Notes</div>
                        <div class="info-value">${escapeHtml(batch.notes)}</div>
                    </div>
                    ` : ''}
                </div>
            </div>

            <div class="batch-info-card">
                <div class="batch-info-title">
                    <i class="fas fa-boxes"></i>
                    <span>Items (${batch.items ? batch.items.length : 0})</span>
                </div>
                <div class="table-responsive">
                    <table class="modal-items-table">
                        <thead>
                            <tr>
                                <th>Item Name</th>
                                <th>Serial Number</th>
                                <th class="text-center">Qty</th>
                                <th>Location (Used At)</th>
                                <th class="text-center">Status</th>
                            </tr>
                        </thead>
                        <tbody>${itemsHtml}</tbody>
                    </table>
                </div>
            </div>

            ${batch.approval_notes ? `
            <div class="modal-alert modal-alert-info">
                <i class="fas fa-sticky-note me-2"></i>
                <strong>Approval Notes:</strong> ${escapeHtml(batch.approval_notes)}
            </div>
            ` : ''}
            
            ${batch.rejection_reason ? `
            <div class="modal-alert modal-alert-danger">
                <i class="fas fa-times-circle me-2"></i>
                <strong>Rejection Reason:</strong> ${escapeHtml(batch.rejection_reason)}
            </div>
            ` : ''}

            ${(currentStatus === 'approved' && !parseInt(batch.driver_verified)) ? `
            <div class="batch-info-card border-warning shadow-sm mt-3" style="background-color: #fffbeb; border-left: 4px solid #ffc107 !important;">
                <div class="batch-info-title text-warning-emphasis fw-bold mb-2">
                    <i class="fas fa-truck-loading me-2 text-warning"></i>
                    <span>Transport Acceptance & Compliance Checklist</span>
                </div>
                <div class="p-1">
                    <p class="text-muted small mb-3" style="font-size: 0.8rem;">To accept receipt of these items and authorize transport, please verify the following conditions:</p>
                    
                    <div class="form-check mb-3">
                        <input class="form-check-input driver-agreement" type="checkbox" id="agreeEquipment" style="cursor: pointer; width: 1.2rem; height: 1.2rem;">
                        <label class="form-check-label small fw-semibold text-dark ms-2" for="agreeEquipment" style="cursor: pointer; line-height: 1.4;">
                            i) I approve that all listed equipment is fully loaded and secured in my vehicle.
                        </label>
                    </div>
                    
                    <div class="form-check mb-3">
                        <input class="form-check-input driver-agreement" type="checkbox" id="agreeTechnician" style="cursor: pointer; width: 1.2rem; height: 1.2rem;">
                        <label class="form-check-label small fw-semibold text-dark ms-2" for="agreeTechnician" style="cursor: pointer; line-height: 1.4;">
                            ii) Technician is on board.
                        </label>
                    </div>
                    
                    <div class="form-check mb-3">
                        <input class="form-check-input driver-agreement" type="checkbox" id="agreeRequest" style="cursor: pointer; width: 1.2rem; height: 1.2rem;">
                        <label class="form-check-label small fw-semibold text-dark ms-2" for="agreeRequest" style="cursor: pointer; line-height: 1.4;">
                            iii) I accept the transport request and responsibility for these items.
                        </label>
                    </div>
                </div>
            </div>
            ` : ''}
        `;

            $('#batchDetailContent').html(html);

            // Hide/Show Buttons based on state
            $('#onboardTechBtn, #verifyLoadBtn, #completeDeliveryBtn').addClass('d-none');

            if (currentStatus === 'approved') {
                if (!parseInt(batch.tech_onboard) && !parseInt(batch.driver_verified)) {
                    $('#onboardTechBtn').removeClass('d-none');
                }

                if (!parseInt(batch.driver_verified)) {
                    $('#verifyLoadBtn').removeClass('d-none');

                    // Setup dynamic interlock logic
                    const verifyBtn = document.getElementById('verifyLoadBtn');
                    if (verifyBtn) {
                        verifyBtn.disabled = true;
                        verifyBtn.innerHTML = '<i class="fas fa-lock me-1"></i> Accept Receipt';
                        verifyBtn.className = 'btn btn-sm btn-secondary';

                        const checkboxes = document.querySelectorAll('.driver-agreement');
                        checkboxes.forEach(cb => {
                            cb.addEventListener('change', () => {
                                const allChecked = Array.from(checkboxes).every(c => c.checked);
                                if (allChecked) {
                                    verifyBtn.disabled = false;
                                    verifyBtn.innerHTML = '<i class="fas fa-check-circle me-1"></i> Confirm & Accept Receipt';
                                    verifyBtn.className = 'btn btn-sm btn-success';
                                } else {
                                    verifyBtn.disabled = true;
                                    verifyBtn.innerHTML = '<i class="fas fa-lock me-1"></i> Accept Receipt';
                                    verifyBtn.className = 'btn btn-sm btn-secondary';
                                }
                            });
                        });
                    }
                } else {
                    $('#completeDeliveryBtn').removeClass('d-none');
                }
            } else {
                $('#verifyLoadBtn').addClass('d-none');
                $('#completeDeliveryBtn').addClass('d-none');
            }

            // Update modal footer buttons based on status
            const footer = $('#batchDetailModal .modal-footer');
            footer.find('.download-report-btn').remove(); // Remove existing download button if any

            if (currentStatus === 'approved' || currentStatus === 'completed') {
                const downloadBtn = $(`
                    <button type="button" class="btn btn-sm btn-success download-report-btn" onclick="viewFullReport('${batch.batch_id}')">
                        <i class="fas fa-file-pdf me-1"></i> Download Report
                    </button>
                `);
                footer.prepend(downloadBtn);
                
                if (['stock_to_stock', 'stock_to_venue_transport', 'transport'].includes(batch.movement_type)) {
                    const gatePassBtn = $(`
                        <a href="gate_pass.php?batch_id=${encodeURIComponent(batch.batch_id)}" class="btn btn-sm btn-warning download-report-btn ms-2" target="_blank">
                            <i class="fas fa-ticket-alt me-1"></i> Gate Pass
                        </a>
                    `);
                    footer.prepend(gatePassBtn);
                }
            }

            // Show notification if status changed from pending
            if (currentStatus !== 'pending') {
                const notification = $('<div class="modal-alert modal-alert-info" style="margin-top: 15px;"><i class="fas fa-sync-alt me-2 fa-spin"></i><strong>Status Updated!</strong> This batch has been ' + currentStatus + '.</div>');
                $('#batchDetailContent').append(notification);
                setTimeout(() => notification.fadeOut(), 5000);
            }
        }

        // Refresh batch status without closing modal
        function refreshBatchStatus(isSilent = false) {
            if (!currentBatchId) return;

            // Check if user is currently interacting with the checklist
            let hasInteractions = false;
            if (isSilent) {
                $('.driver-agreement').each(function() {
                    if ($(this).is(':checked')) hasInteractions = true;
                });
                if (hasInteractions) return; // Don't interrupt user interaction
            }

            if (!isSilent) showLoading();
            const timestamp = new Date().getTime();

            $.ajax({
                url: 'api/batches/details.php',
                method: 'GET',
                data: {
                    batch_id: currentBatchId,
                    _t: timestamp
                },
                success: function(response) {
                    if (response.success) {
                        displayBatchDetails(response.batch);
                        if (!isSilent) showNotification('success', 'Status refreshed successfully!');
                        loadBatches(true); // Silently refresh the main table
                    } else {
                        showNotification('error', 'Failed to refresh status');
                    }
                },
                error: function() {
                    showNotification('error', 'Failed to refresh status');
                },
                complete: function() {
                    hideLoading();
                }
            });
        }

        // Confirm Tech Onboard
        function confirmTechOnboard() {
            if (!currentBatchId) return;
            showLoading();
            $.ajax({
                url: 'api/batches/driver_onboard_tech.php',
                method: 'POST',
                data: { batch_id: currentBatchId },
                success: function(response) {
                    if(response.success) {
                        showNotification('success', 'Technician onboard confirmed!');
                        refreshBatchStatus();
                    } else {
                        showNotification('error', response.message || 'Failed to confirm');
                    }
                },
                complete: function() {
                    hideLoading();
                }
            });
        }

        // Verify Driver Load
        function verifyDriverLoad() {
            if (!currentBatchId) return;
            showLoading();
            $.ajax({
                url: 'api/batches/driver_verify.php',
                method: 'POST',
                data: { batch_id: currentBatchId },
                success: function(response) {
                    if(response.success) {
                        showNotification('success', 'Equipment load verified successfully!');
                        refreshBatchStatus();
                    } else {
                        showNotification('error', response.message || 'Failed to verify');
                    }
                },
                complete: function() {
                    hideLoading();
                }
            });
        }

        // Complete Driver Delivery
        function completeDriverDelivery() {
            if (!currentBatchId) return;
            showLoading();
            $.ajax({
                url: 'api/batches/driver_complete.php',
                method: 'POST',
                data: { batch_id: currentBatchId },
                success: function(response) {
                    if(response.success) {
                        showNotification('success', 'Delivery completed successfully!');
                        refreshBatchStatus();
                    } else {
                        showNotification('error', response.message || 'Failed to complete delivery');
                    }
                },
                error: function() {
                    showNotification('error', 'Failed to complete delivery');
                },
                complete: function() {
                    hideLoading();
                }
            });
        }

        // View Full Report
        function viewFullReport(batchId) {
            const id = batchId || currentBatchId;
            if (id) {
                window.open(`batch_report.php?batch_id=${id}&download=1`, '_blank');
            }
        }

        // Filter Functions
        function clearFilters() {
            $('#date_from').val(moment().subtract(30, 'days').format('YYYY-MM-DD'));
            $('#date_to').val(moment().format('YYYY-MM-DD'));
            $('#status').val('');
            $('#search').val('');
            loadBatches();
        }

        function refreshData() {
            loadBatches();
            showNotification('success', 'Data refreshed');
        }

        // Utility Functions
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
            const notification = document.createElement('div');
            notification.className = `alert alert-${type} alert-dismissible fade show`;
            notification.style.cssText = 'position: fixed; top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
            notification.innerHTML = `${message}<button type="button" class="btn-close" onclick="this.parentElement.remove()"></button>`;
            document.body.appendChild(notification);
            setTimeout(() => notification.remove(), 3000);
        }

        // Real-time Auto-Refresh
        setInterval(() => {
            if (!$('#batchDetailModal').hasClass('show')) {
                loadBatches(true); // Silently refresh the list
            } else if (currentBatchId) {
                // If viewing a batch, silently refresh its status
                refreshBatchStatus(true);
            }
        }, 5000);
    </script>
</body>

</html>