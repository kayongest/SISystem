<?php
if (!defined('BASE_URL')) {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    $base_url = $protocol . '://' . $host . '/ability_app_main/';
    define('BASE_URL', $base_url);
}

// Determine current page for active state
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle ?? 'aBility Dashboard'); ?></title>

    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="<?php echo BASE_URL; ?>assets/images/warehouse_.png">

    <!-- ========== CSS FILES ========== -->
    <!-- Bootstrap 4 & Stack Admin Template CSS -->
    <link rel="stylesheet" type="text/css" href="https://stackpath.bootstrapcdn.com/bootstrap/4.4.1/css/bootstrap.min.css">
    <link rel="stylesheet" type="text/css" href="https://pixinvent.com/stack-responsive-bootstrap-4-admin-template/app-assets/css/bootstrap-extended.min.css">
    <link rel="stylesheet" type="text/css" href="https://pixinvent.com/stack-responsive-bootstrap-4-admin-template/app-assets/fonts/simple-line-icons/style.min.css">
    <link rel="stylesheet" type="text/css" href="https://pixinvent.com/stack-responsive-bootstrap-4-admin-template/app-assets/css/colors.min.css">
    <link rel="stylesheet" type="text/css" href="https://pixinvent.com/stack-responsive-bootstrap-4-admin-template/app-assets/css/bootstrap.min.css">
    <!-- <link href="https://fonts.googleapis.com/css?family=Montserrat&display=swap" rel="stylesheet"> -->

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- DataTables CSS -->
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.13.4/css/jquery.dataTables.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css">

    <!-- Toastr CSS -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">

    <!-- Your Custom CSS -->
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/styles.css">

    <!-- ========== JAVASCRIPT FILES ========== -->
    <!-- jQuery MUST BE FIRST -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

    <!-- Bootstrap 4 JS Bundle -->
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.4.1/js/bootstrap.bundle.min.js"></script>

    <!-- DataTables JS -->
    <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>

    <!-- Toastr JS -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>

    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>

    <!-- Export Libraries -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.28/jspdf.plugin.autotable.min.js"></script>

    <!-- Navigation Cards Custom CSS -->
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Titillium+Web:ital,wght@0,200;0,300;0,400;0,600;0,700;0,900;1,200;1,300;1,400;1,600;1,700&display=swap');

        * {
            font-family: 'Titillium Web', sans-serif;
        }

        /* Base Card Styling with Hover Effects */
        .grey-bg {
            background-image: url('<?php echo BASE_URL; ?>assets/images/bg/4.jpg');
            background-size: cover;
            background-attachment: fixed;
            background-position: center;
            min-height: 100vh;
            padding: 30px 20px;
        }

        .grey-bg>* {
            position: relative;
            z-index: 2;
        }

        /* Navigation Cards Container */
        .nav-cards-container {
            padding: 5px;
        }

        /* Navigation Cards */
        .nav-card {
            border: none;
            border-radius: 15px;
            background: rgba(255, 255, 255, 0.95);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            margin-bottom: 25px;
            height: 180px;
            position: relative;
            overflow: hidden;
            cursor: pointer;
        }

        .nav-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2);
        }

        /* Hover Effect 1: Elevate & Shadow */
        .nav-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.15);
        }

        /* Hover Effect 2: Gradient Overlay */
        .nav-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, transparent 0%, rgba(255, 255, 255, 0.1) 100%);
            opacity: 0;
            transition: opacity 0.3s ease;
            z-index: 1;
            pointer-events: none;
        }

        .nav-card:hover::before {
            opacity: 1;
        }

        /* Hover Effect 3: Icon Animation */
        .nav-card:hover .font-large-2 {
            transform: scale(1.2) rotate(5deg);
            transition: transform 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }

        /* Hover Effect 4: Number Animation */
        .nav-card:hover h3 {
            transform: scale(1.05);
            transition: transform 0.3s ease;
        }

        /* Hover Effect 5: Border Glow */
        .nav-card {
            position: relative;
            border: 2px solid transparent;
        }

        .nav-card:hover {
            border: 2px solid transparent;
            background-clip: padding-box, border-box;
            background-origin: padding-box, border-box;
            background-image: linear-gradient(white, white),
                linear-gradient(135deg, #4361ee, #34a853, #ffc107, #dc3545);
            background-clip: padding-box, border-box;
            animation: borderRotate 3s linear infinite;
        }

        @keyframes borderRotate {
            0% {
                background-image: linear-gradient(white, white),
                    linear-gradient(135deg, #4361ee, #34a853, #ffc107, #dc3545);
            }

            100% {
                background-image: linear-gradient(white, white),
                    linear-gradient(495deg, #4361ee, #34a853, #ffc107, #dc3545);
            }
        }

        /* Active State */
        .nav-card.active {
            background: linear-gradient(135deg, #4361ee 0%, #3a0ca3 100%);
            color: white;
        }

        .nav-card.active .card-content {
            color: white;
        }

        .nav-card.active .font-large-2,
        .nav-card.active h3,
        .nav-card.active span {
            color: white !important;
        }

        /* Card Content */
        .nav-card .card-content {
            padding: 25px 20px;
            height: 100%;
            display: flex;
            flex-direction: column;
        }

        .nav-card .media {
            display: flex;
            align-items: center;
            justify-content: space-between;
            height: 100%;
        }

        /* Icon Styling */
        .font-large-2 {
            font-size: 3.5rem !important;
            width: 80px;
            height: 80px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 15px;
        }

        /* Color-specific icons */
        .primary {
            color: #4361ee !important;
        }

        .success {
            color: #28a745 !important;
        }

        .warning {
            color: #ffc107 !important;
        }

        .danger {
            color: #dc3545 !important;
        }

        .nav-card:hover .primary {
            color: #4361ee !important;
        }

        .nav-card:hover .success {
            color: #28a745 !important;
        }

        .nav-card:hover .warning {
            color: #ffc107 !important;
        }

        .nav-card:hover .danger {
            color: #dc3545 !important;
        }

        /* Text Styling */
        .media-body {
            text-align: right;
        }

        .nav-card h3 {
            font-size: 1.5rem;
            font-weight: 700;
            line-height: 1;
            margin-bottom: 5px;
            font-family: 'Montserrat', sans-serif;
            color: #2c3e50;
        }

        .nav-card span {
            font-size: 1rem;
            color: #6c757d;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 600;
        }

        .nav-card:hover h3 {
            color: #4361ee;
        }

        .nav-card:hover span {
            color: #495057;
        }

        /* Progress Bar */
        .nav-progress {
            height: 5px;
            background: rgba(0, 0, 0, 0.05);
            border-radius: 10px;
            overflow: hidden;
            margin-top: 15px;
        }

        .nav-progress-bar {
            height: 100%;
            border-radius: 10px;
            transition: width 1.5s ease-in-out;
        }

        .nav-card:hover .nav-progress-bar {
            width: 100% !important;
        }

        /* Page Header */
        .page-header {
            /* background: rgba(255, 255, 255, 0.95); */
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }

        .page-header h1 {
            font-family: 'Montserrat', sans-serif;
            font-weight: 700;
            color: #ffffff;
            margin-bottom: 10px;
        }

        .page-header p {
            color: #fcfcfc;
            font-size: 1.1rem;
            margin-bottom: 0;
        }

        /* Breadcrumb */
        .breadcrumb-container {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 10px;
            padding: 15px 20px;
            margin-bottom: 30px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
        }

        .breadcrumb {
            background: transparent;
            margin-bottom: 0;
            padding: 0;
        }

        .breadcrumb-item a {
            color: #4361ee;
            text-decoration: none;
            font-weight: 500;
        }

        .breadcrumb-item.active {
            color: #6c757d;
        }

        /* User Info */
        .user-info {
            background: rgb(62 80 101 / 45%);
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 30px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
        }

        .user-avatar {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #929ed4 0%, #2a3e8c 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5rem;
            margin-right: 15px;
        }

        .user-name {
            font-size: 1.2rem;
            font-weight: 600;
            color: #ffffff;
            margin-bottom: 5px;
        }

        .user-role {
            font-size: 0.9rem;
            color: #6c757d;
        }

        /* Settings Dropdown */
        .settings-dropdown .dropdown-menu {
            min-width: 250px;
            border: none;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
            border-radius: 10px;
        }

        .dropdown-item {
            padding: 10px 15px;
            font-weight: 500;
        }

        .dropdown-item:hover {
            background-color: #f8f9fa;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .nav-card {
                height: 160px;
            }

            .font-large-2 {
                font-size: 2.5rem !important;
                width: 60px;
                height: 60px;
            }

            .nav-card h3 {
                font-size: 2rem;
            }

            .nav-card span {
                font-size: 0.9rem;
            }

            .page-header {
                padding: 20px;
            }
        }

        @media (max-width: 576px) {
            .nav-card {
                height: 140px;
            }

            .font-large-2 {
                font-size: 2rem !important;
                width: 50px;
                height: 50px;
            }

            .nav-card h3 {
                font-size: 1.5rem;
            }

            .nav-card span {
                font-size: 0.8rem;
            }

            .grey-bg {
                padding: 15px;
            }
        }

        /* Grid Layout */
        .row.g-4 {
            margin: 0 -10px;
        }

        .row.g-4>[class*="col-"] {
            padding: 0 10px;
        }

        /* Link Styling */
        a.nav-link {
            text-decoration: none !important;
        }

        /* Custom hover effects for different cards */
        .nav-card:hover .bg-primary {
            background-color: rgba(67, 97, 238, 0.1) !important;
        }

        .nav-card:hover .bg-success {
            background-color: rgba(40, 167, 69, 0.1) !important;
        }

        .nav-card:hover .bg-warning {
            background-color: rgba(255, 193, 7, 0.1) !important;
        }

        .nav-card:hover .bg-danger {
            background-color: rgba(220, 53, 69, 0.1) !important;
        }

        .nav-card:hover .bg-info {
            background-color: rgba(23, 162, 184, 0.1) !important;
        }
    </style>
</head>

<body>
    <!-- Background Container -->
    <div class="grey-bg">
        <!-- Page Header -->
        <div class="page-header">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1><?php echo htmlspecialchars($pageTitle ?? 'aBility Dashboard'); ?></h1>
                    <p>Equipment Management System</p>
                </div>
                <div class="col-md-4 text-right">
                    <!-- User Info -->
                    <div class="user-info d-inline-block">
                        <div class="d-flex align-items-center">
                            <div class="user-avatar">
                                <i class="fas fa-user-circle"></i>
                            </div>
                            <div>
                                <div class="user-name">
                                    <?php echo htmlspecialchars($_SESSION['username'] ?? 'User'); ?>
                                </div>
                                <div class="user-role">
                                    <?php echo htmlspecialchars($_SESSION['role'] ?? 'Administrator'); ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Breadcrumb -->
        <?php if (isset($showBreadcrumb) && $showBreadcrumb): ?>
            <div class="breadcrumb-container">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>index.php"><i class="fas fa-home"></i> Home</a></li>
                        <?php
                        if (isset($breadcrumbItems)) {
                            foreach ($breadcrumbItems as $text => $link) {
                                if ($link) {
                                    echo '<li class="breadcrumb-item"><a href="' . BASE_URL . $link . '">' . htmlspecialchars($text) . '</a></li>';
                                } else {
                                    echo '<li class="breadcrumb-item active" aria-current="page">' . htmlspecialchars($text) . '</li>';
                                }
                            }
                        }
                        ?>
                    </ol>
                </nav>
            </div>
        <?php endif; ?>

        <!-- Navigation Cards -->
        <section id="minimal-statistics" class="nav-cards-container">
            <div class="row g-4">
                <!-- Dashboard -->
                <div class="col-xl-3 col-lg-4 col-md-6 col-sm-6 col-12">
                    <a href="<?php echo BASE_URL; ?>index.php" class="nav-link">
                        <div class="card nav-card <?php echo ($current_page == 'index.php' || $current_page == 'dashboard_full.php') ? 'active' : ''; ?>">
                            <div class="card-content">
                                <div class="media d-flex">
                                    <div class="align-self-center">
                                        <i class="fas fa-tachometer-alt font-large-2 primary float-left"></i>
                                    </div>
                                    <div class="media-body text-right">
                                        <h3>Dashboard</h3>
                                        <span>Overview</span>
                                    </div>
                                </div>
                                <div class="nav-progress">
                                    <div class="nav-progress-bar bg-primary" style="width: 85%"></div>
                                </div>
                            </div>
                        </div>
                    </a>
                </div>

                <!-- Equipment -->
                <div class="col-xl-3 col-lg-4 col-md-6 col-sm-6 col-12">
                    <a href="<?php echo BASE_URL; ?>views/items/index.php" class="nav-link">
                        <div class="card nav-card <?php echo (strpos($current_page, 'items') !== false) ? 'active' : ''; ?>">
                            <div class="card-content">
                                <div class="media d-flex">
                                    <div class="align-self-center">
                                        <i class="fas fa-boxes font-large-2 success float-left"></i>
                                    </div>
                                    <div class="media-body text-right">
                                        <h3>Equipment</h3>
                                        <span>Manage Items</span>
                                    </div>
                                </div>
                                <div class="nav-progress">
                                    <div class="nav-progress-bar bg-success" style="width: 70%"></div>
                                </div>
                            </div>
                        </div>
                    </a>
                </div>

                <!-- Accessories -->
                <div class="col-xl-3 col-lg-4 col-md-6 col-sm-6 col-12">
                    <a href="<?php echo BASE_URL; ?>accessories.php" class="nav-link">
                        <div class="card nav-card <?php echo ($current_page == 'accessories.php') ? 'active' : ''; ?>">
                            <div class="card-content">
                                <div class="media d-flex">
                                    <div class="align-self-center">
                                        <i class="fas fa-plug font-large-2 warning float-left"></i>
                                    </div>
                                    <div class="media-body text-right">
                                        <h3>Accessories</h3>
                                        <span>Cables & Parts</span>
                                    </div>
                                </div>
                                <div class="nav-progress">
                                    <div class="nav-progress-bar bg-warning" style="width: 60%"></div>
                                </div>
                            </div>
                        </div>
                    </a>
                </div>

                <!-- Import -->
                <div class="col-xl-3 col-lg-4 col-md-6 col-sm-6 col-12">
                    <a href="<?php echo BASE_URL; ?>import_items.php" class="nav-link">
                        <div class="card nav-card <?php echo ($current_page == 'import_items.php') ? 'active' : ''; ?>">
                            <div class="card-content">
                                <div class="media d-flex">
                                    <div class="align-self-center">
                                        <i class="fas fa-upload font-large-2 info float-left"></i>
                                    </div>
                                    <div class="media-body text-right">
                                        <h3>Import</h3>
                                        <span>Excel/CSV Data</span>
                                    </div>
                                </div>
                                <div class="nav-progress">
                                    <div class="nav-progress-bar bg-info" style="width: 45%"></div>
                                </div>
                            </div>
                        </div>
                    </a>
                </div>

                <!-- Single Scan -->
                <div class="col-xl-3 col-lg-4 col-md-6 col-sm-6 col-12">
                    <a href="<?php echo BASE_URL; ?>scan.php" class="nav-link">
                        <div class="card nav-card <?php echo ($current_page == 'scan.php') ? 'active' : ''; ?>">
                            <div class="card-content">
                                <div class="media d-flex">
                                    <div class="align-self-center">
                                        <i class="fas fa-expand font-large-2 primary float-left"></i>
                                    </div>
                                    <div class="media-body text-right">
                                        <h3>Single Scan</h3>
                                        <span>Quick Scan</span>
                                    </div>
                                </div>
                                <div class="nav-progress">
                                    <div class="nav-progress-bar bg-primary" style="width: 55%"></div>
                                </div>
                            </div>
                        </div>
                    </a>
                </div>

                <!-- Bulk Scan -->
                <div class="col-xl-3 col-lg-4 col-md-6 col-sm-6 col-12">
                    <a href="<?php echo BASE_URL; ?>scan_2.php" class="nav-link">
                        <div class="card nav-card <?php echo ($current_page == 'scan_2.php') ? 'active' : ''; ?>">
                            <div class="card-content">
                                <div class="media d-flex">
                                    <div class="align-self-center">
                                        <i class="fas fa-qrcode font-large-2 danger float-left"></i>
                                    </div>
                                    <div class="media-body text-right">
                                        <h3>Bulk Scan</h3>
                                        <span>Batch Items</span>
                                    </div>
                                </div>
                                <div class="nav-progress">
                                    <div class="nav-progress-bar bg-danger" style="width: 75%"></div>
                                </div>
                            </div>
                        </div>
                    </a>
                </div>

                <!-- Scan History -->
                <div class="col-xl-3 col-lg-4 col-md-6 col-sm-6 col-12">
                    <a href="<?php echo BASE_URL; ?>scan_logs.php" class="nav-link">
                        <div class="card nav-card <?php echo ($current_page == 'scan_logs.php') ? 'active' : ''; ?>">
                            <div class="card-content">
                                <div class="media d-flex">
                                    <div class="align-self-center">
                                        <i class="fas fa-history font-large-2 warning float-left"></i>
                                    </div>
                                    <div class="media-body text-right">
                                        <h3>History</h3>
                                        <span>Scan Logs</span>
                                    </div>
                                </div>
                                <div class="nav-progress">
                                    <div class="nav-progress-bar bg-warning" style="width: 65%"></div>
                                </div>
                            </div>
                        </div>
                    </a>
                </div>

                <!-- Reports -->
                <div class="col-xl-3 col-lg-4 col-md-6 col-sm-6 col-12">
                    <a href="<?php echo BASE_URL; ?>reports.php" class="nav-link">
                        <div class="card nav-card <?php echo ($current_page == 'reports.php') ? 'active' : ''; ?>">
                            <div class="card-content">
                                <div class="media d-flex">
                                    <div class="align-self-center">
                                        <i class="fas fa-chart-bar font-large-2 success float-left"></i>
                                    </div>
                                    <div class="media-body text-right">
                                        <h3>Reports</h3>
                                        <span>Analytics</span>
                                    </div>
                                </div>
                                <div class="nav-progress">
                                    <div class="nav-progress-bar bg-success" style="width: 50%"></div>
                                </div>
                            </div>
                        </div>
                    </a>
                </div>

                <!-- Settings with Dropdown -->
                <div class="col-xl-3 col-lg-4 col-md-6 col-sm-6 col-12 settings-dropdown">
                    <div class="dropdown">
                        <a href="#" class="nav-link dropdown-toggle" data-toggle="dropdown">
                            <div class="card nav-card">
                                <div class="card-content">
                                    <div class="media d-flex">
                                        <div class="align-self-center">
                                            <i class="fas fa-cog font-large-2 info float-left"></i>
                                        </div>
                                        <div class="media-body text-right">
                                            <h3>Settings</h3>
                                            <span>System Config</span>
                                        </div>
                                    </div>
                                    <div class="nav-progress">
                                        <div class="nav-progress-bar bg-info" style="width: 40%"></div>
                                    </div>
                                </div>
                            </div>
                        </a>
                        <div class="dropdown-menu dropdown-menu-right">
                            <h6 class="dropdown-header">System Settings</h6>
                            <a class="dropdown-item <?php echo ($current_page == 'categories.php') ? 'active' : ''; ?>"
                                href="<?php echo BASE_URL; ?>categories.php">
                                <i class="fas fa-list me-2"></i> Categories
                            </a>
                            <a class="dropdown-item <?php echo ($current_page == 'departments.php') ? 'active' : ''; ?>"
                                href="<?php echo BASE_URL; ?>departments.php">
                                <i class="fas fa-building me-2"></i> Departments
                            </a>
                            <a class="dropdown-item <?php echo ($current_page == 'locations.php') ? 'active' : ''; ?>"
                                href="<?php echo BASE_URL; ?>locations.php">
                                <i class="fas fa-map-marker-alt me-2"></i> Locations
                            </a>
                            <div class="dropdown-divider"></div>
                            <h6 class="dropdown-header">User Settings</h6>
                            <a class="dropdown-item <?php echo ($current_page == 'profile.php') ? 'active' : ''; ?>"
                                href="<?php echo BASE_URL; ?>profile.php">
                                <i class="fas fa-user me-2"></i> Profile
                            </a>
                            <a class="dropdown-item <?php echo ($current_page == 'settings.php') ? 'active' : ''; ?>"
                                href="<?php echo BASE_URL; ?>settings.php">
                                <i class="fas fa-cog me-2"></i> Account Settings
                            </a>
                            <div class="dropdown-divider"></div>
                            <a class="dropdown-item text-danger" href="<?php echo BASE_URL; ?>logout.php">
                                <i class="fas fa-sign-out-alt me-2"></i> Logout
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <div class="container text-center">
            <div class="row">
                <div class="col">
                    1 of 2
                </div>
                <div class="col">
                    2 of 2
                </div>
            </div>
            <div class="row">
                <div class="col">
                    1 of 3
                </div>
                <div class="col">
                    2 of 3
                </div>
                <div class="col">
                    3 of 3
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content Area -->
    <div class="container-fluid mt-4">