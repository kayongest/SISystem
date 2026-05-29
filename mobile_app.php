<?php
// mobile_app.php - Premium Mobile responsive Web App interface
session_start();
require_once 'bootstrap.php';
require_once 'includes/functions.php';

// Force authentication
if (!isLoggedIn()) {
    header('Location: login.php');
    exit();
}

$user_role = getUserRole();
$user_name = getUserFullName();
$user_id = $_SESSION['user_id'] ?? 0;

// Determine primary workspace views based on roles
$is_driver = ($user_role === 'driver');
$is_technician = in_array($user_role, ['technician', 'tech_lead', 'senior_tech']);
$is_controller = in_array($user_role, ['stock_controller', 'stock_manager', 'admin', 'manager']);

// Fetch dynamic inventory metrics for manager views
require_once 'includes/database_fix.php';
$db_fix = new DatabaseFix();
$conn = $db_fix->getConnection();
$stats = getDashboardStats($conn);

$pageTitle = "aBility Mobile Portal";
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title><?php echo $pageTitle; ?></title>

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome 6 CDN -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <!-- Google Fonts (Outfit) -->
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        :root {
            --bg-dark: #0b0f19;
            --bg-card: rgba(255, 255, 255, 0.03);
            --bg-card-hover: rgba(255, 255, 255, 0.06);
            --border-glass: rgba(255, 255, 255, 0.08);
            --border-glass-focus: rgba(0, 210, 255, 0.4);
            --text-primary: #f8fafc;
            --text-secondary: #94a3b8;
            --accent: #00d2ff;
            --accent-gradient: linear-gradient(135deg, #00d2ff 0%, #4f46e5 100%);
            --success-color: #10b981;
            --warning-color: #f59e0b;
            --danger-color: #ef4444;
            --info-color: #3b82f6;
            --safe-area-bottom: env(safe-area-inset-bottom, 0px);
            --bg-header: rgba(11, 15, 25, 0.85);
            --bg-bottom-nav: rgba(11, 15, 25, 0.92);
            --bg-drawer: #101726;
            --bg-input: rgba(255, 255, 255, 0.05);
            --bg-input-focus: rgba(255, 255, 255, 0.08);
        }

        body.light-theme {
            --bg-dark: #f8fafc;
            --bg-card: rgba(15, 23, 42, 0.03);
            --bg-card-hover: rgba(15, 23, 42, 0.06);
            --border-glass: rgba(15, 23, 42, 0.08);
            --border-glass-focus: rgba(79, 70, 229, 0.4);
            --text-primary: #0f172a;
            --text-secondary: #64748b;
            --accent: #4f46e5;
            --accent-gradient: linear-gradient(135deg, #4f46e5 0%, #3b82f6 100%);
            --bg-header: rgba(248, 250, 252, 0.92);
            --bg-bottom-nav: rgba(248, 250, 252, 0.92);
            --bg-drawer: #ffffff;
            --bg-input: rgba(15, 23, 42, 0.05);
            --bg-input-focus: rgba(15, 23, 42, 0.08);
        }

        * {
            font-family: 'Outfit', sans-serif;
            -webkit-tap-highlight-color: transparent;
            box-sizing: border-box;
        }

        body {
            background-color: var(--bg-dark);
            color: var(--text-primary);
            overflow-x: hidden;
            margin: 0;
            padding: 0;
            min-height: 100vh;
            padding-bottom: calc(75px + var(--safe-area-bottom));
            transition: background-color 0.3s ease, color 0.3s ease;
        }

        /* Glassmorphic Cards & Layout */
        .glass-card {
            background: var(--bg-card);
            border: 1px solid var(--border-glass);
            border-radius: 20px;
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            padding: 1.25rem;
            margin-bottom: 1rem;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .glass-card:active {
            transform: scale(0.98);
            background: var(--bg-card-hover);
        }

        /* App Header */
        .app-header {
            background: var(--bg-header);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-bottom: 1px solid var(--border-glass);
            position: sticky;
            top: 0;
            z-index: 1050;
            padding: 0.75rem 1rem;
            transition: background 0.3s ease;
        }

        .header-content {
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .header-brand {
            font-size: 1.3rem;
            font-weight: 800;
            letter-spacing: -0.5px;
            background: var(--accent-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            text-decoration: none;
        }

        .header-user {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .user-avatar {
            width: 34px;
            height: 34px;
            border-radius: 50%;
            background: var(--accent-gradient);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 0.9rem;
            color: white;
            box-shadow: 0 4px 10px rgba(0, 210, 255, 0.2);
            cursor: pointer;
        }

        /* Select dropdowns Light Mode dynamic overrides */
        .form-select.bg-dark,
        select.bg-dark {
            background-color: var(--bg-drawer) !important;
            color: var(--text-primary) !important;
            border: 1px solid var(--border-glass) !important;
        }

        /* Bottom Tab Navigation */
        .bottom-nav {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: var(--bg-bottom-nav);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-top: 1px solid var(--border-glass);
            display: flex;
            justify-content: space-around;
            padding: 0.6rem 0.5rem calc(0.6rem + var(--safe-area-bottom));
            z-index: 1050;
            transition: background 0.3s ease;
        }

        .tab-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            text-decoration: none;
            color: var(--text-secondary);
            font-size: 0.75rem;
            font-weight: 500;
            transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
            padding: 0.25rem 0.8rem;
            border-radius: 12px;
            position: relative;
        }

        .tab-item i {
            font-size: 1.3rem;
            margin-bottom: 3px;
            transition: transform 0.25s ease;
        }

        .tab-item.active {
            color: var(--accent);
        }

        .tab-item.active i {
            transform: translateY(-2px);
        }

        .tab-item.active::after {
            content: '';
            position: absolute;
            bottom: -3px;
            width: 12px;
            height: 3px;
            background-color: var(--accent);
            border-radius: 10px;
            box-shadow: 0 0 10px var(--accent);
        }

        /* Badge Pills */
        .status-pill {
            padding: 4px 10px;
            border-radius: 30px;
            font-size: 0.75rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        .pill-pending {
            background: rgba(245, 158, 11, 0.12);
            color: var(--warning-color);
            border: 1px solid rgba(245, 158, 11, 0.2);
        }

        .pill-approved {
            background: rgba(16, 185, 129, 0.12);
            color: var(--success-color);
            border: 1px solid rgba(16, 185, 129, 0.2);
        }

        .pill-rejected {
            background: rgba(239, 68, 68, 0.12);
            color: var(--danger-color);
            border: 1px solid rgba(239, 68, 68, 0.2);
        }

        .pill-info {
            background: rgba(59, 130, 246, 0.12);
            color: var(--info-color);
            border: 1px solid rgba(59, 130, 246, 0.2);
        }

        /* Custom Views Display Toggle */
        .app-view {
            display: none;
            padding: 1rem;
            animation: viewFadeIn 0.35s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .app-view.active {
            display: block;
        }

        @keyframes viewFadeIn {
            from {
                opacity: 0;
                transform: translateY(8px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Floating Scan Button */
        .fab-scan {
            background: var(--accent-gradient);
            width: 54px;
            height: 54px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.4rem;
            box-shadow: 0 8px 25px rgba(0, 210, 255, 0.4);
            border: none;
            outline: none;
            transition: all 0.25s ease;
        }

        .fab-scan:active {
            transform: scale(0.9) rotate(45deg);
            box-shadow: 0 4px 10px rgba(0, 210, 255, 0.2);
        }

        /* Search input & general fields styling */
        .glass-input {
            background: var(--bg-input);
            border: 1px solid var(--border-glass);
            border-radius: 12px;
            color: var(--text-primary);
            padding: 0.75rem 1rem;
            width: 100%;
            font-size: 0.95rem;
            transition: all 0.3s;
        }

        .glass-input:focus {
            background: var(--bg-input-focus);
            border-color: var(--border-glass-focus);
            outline: none;
            box-shadow: 0 0 12px rgba(0, 210, 255, 0.15);
        }

        /* Camera Scanner Styles */
        #scanner-video-preview {
            width: 100%;
            border-radius: 16px;
            background: #000;
            overflow: hidden;
            display: none;
            border: 2px solid var(--accent);
            box-shadow: 0 0 20px rgba(0, 210, 255, 0.3);
        }

        .scanner-frame {
            position: relative;
            margin-bottom: 1rem;
        }

        .scanner-overlay {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 65%;
            height: 65%;
            border: 2px dashed var(--accent);
            border-radius: 12px;
            box-shadow: 0 0 0 9999px rgba(11, 15, 25, 0.5);
            pointer-events: none;
            display: none;
        }

        .scanner-laser {
            position: absolute;
            top: 50%;
            left: 5%;
            width: 90%;
            height: 2px;
            background-color: var(--accent);
            box-shadow: 0 0 8px var(--accent);
            animation: laserScan 2s infinite ease-in-out;
            pointer-events: none;
            display: none;
        }

        @keyframes laserScan {
            0% {
                top: 10%;
            }

            50% {
                top: 90%;
            }

            100% {
                top: 10%;
            }
        }

        /* Detail Drawer Modal */
        .drawer-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.6);
            backdrop-filter: blur(5px);
            z-index: 1100;
            display: none;
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .app-drawer {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: var(--bg-drawer);
            border-top: 1px solid var(--border-glass);
            border-top-left-radius: 30px;
            border-top-right-radius: 30px;
            padding: 1.5rem;
            z-index: 1200;
            transform: translateY(100%);
            transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            max-height: 85vh;
            overflow-y: auto;
        }

        .app-drawer.show {
            transform: translateY(0);
        }

        .drawer-handle {
            width: 40px;
            height: 5px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 10px;
            margin: 0 auto 1.5rem;
        }

        /* Toast Notifications */
        .app-toast-container {
            position: fixed;
            top: 20px;
            left: 50%;
            transform: translateX(-50%);
            z-index: 9999;
            width: 90%;
            max-width: 360px;
        }

        .app-toast {
            background: rgba(16, 23, 38, 0.95);
            border: 1px solid var(--border-glass);
            border-radius: 16px;
            padding: 1rem;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.3);
            backdrop-filter: blur(10px);
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 8px;
            transform: translateY(-20px);
            opacity: 0;
            transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }

        .app-toast.show {
            transform: translateY(0);
            opacity: 1;
        }

        .app-toast.success {
            border-color: var(--success-color);
        }

        .app-toast.error {
            border-color: var(--danger-color);
        }

        .app-toast.warning {
            border-color: var(--warning-color);
        }

        .app-toast-icon {
            font-size: 1.2rem;
        }

        .app-toast.success .app-toast-icon {
            color: var(--success-color);
        }

        .app-toast.error .app-toast-icon {
            color: var(--danger-color);
        }

        .app-toast.warning .app-toast-icon {
            color: var(--warning-color);
        }

        .app-toast-text {
            color: var(--text-primary);
            font-size: 0.9rem;
            font-weight: 500;
            flex-grow: 1;
        }
        /* Milestone Tracker styling for Mobile App */
        .milestone-tracker {
            display: flex;
            justify-content: space-between;
            position: relative;
            margin: 1.5rem 0 2rem 0;
            padding: 0 5px;
        }

        .milestone-tracker::before {
            content: '';
            position: absolute;
            top: 18px;
            left: 10%;
            right: 10%;
            height: 4px;
            background: var(--border-glass);
            z-index: 1;
        }

        .milestone-tracker-fill {
            position: absolute;
            top: 18px;
            left: 10%;
            height: 4px;
            background: var(--accent-gradient);
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
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: var(--bg-dark);
            border: 2px solid var(--text-secondary);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.9rem;
            color: var(--text-secondary);
            transition: all 0.3s ease;
        }

        .milestone-step.active .milestone-icon {
            border-color: var(--accent);
            color: #fff;
            background: var(--accent);
            box-shadow: 0 0 10px rgba(0, 210, 255, 0.4);
        }

        .milestone-step.completed .milestone-icon {
            border-color: var(--success-color);
            color: #fff;
            background: var(--success-color);
            box-shadow: 0 0 10px rgba(16, 185, 129, 0.4);
        }

        .milestone-step.rejected .milestone-icon {
            border-color: var(--danger-color);
            color: #fff;
            background: var(--danger-color);
        }

        .milestone-label {
            font-size: 0.65rem;
            font-weight: 600;
            margin-top: 8px;
            color: var(--text-secondary);
            text-align: center;
            line-height: 1.1;
        }

        .milestone-step.active .milestone-label {
            color: var(--accent);
        }

        .milestone-step.completed .milestone-label {
            color: var(--success-color);
        }

        .milestone-step.rejected .milestone-label {
<<<<<<< HEAD
            color: var(--danger-color);
=======
            color: #dc3545;
        }

        .milestone-step.greyed .milestone-icon {
            border-color: rgba(255, 255, 255, 0.1);
            color: rgba(255, 255, 255, 0.3);
            background: rgba(255, 255, 255, 0.05);
            box-shadow: none;
        }

        .milestone-step.greyed .milestone-label {
            color: rgba(255, 255, 255, 0.4);
        }

        .milestone-step.greyed .milestone-sublabel {
            color: rgba(255, 255, 255, 0.2);
>>>>>>> addf346 (Latest Upload - Events cards, OverView, Items status..)
        }

        .milestone-sublabel {
            font-size: 0.55rem;
            color: var(--text-secondary);
            text-align: center;
            margin-top: 2px;
            max-width: 70px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            opacity: 0.8;
        }
    </style>
</head>

<body>

    <!-- Toast Notifications Container -->
    <div class="app-toast-container" id="toastContainer"></div>

    <!-- Sticky Header -->
    <div class="app-header">
        <div class="header-content">
            <a href="#" class="header-brand">EventORY</a>
            <div class="header-user d-flex align-items-center gap-2">
                <!-- Theme Toggle Button -->
                <button class="btn btn-link text-secondary p-1 me-1" id="theme-toggle" onclick="toggleAppTheme()" style="box-shadow: none; border: none; font-size: 1.15rem;">
                    <i class="fas fa-moon" id="theme-icon"></i>
                </button>
                <span style="font-size: 0.85rem; font-weight: 600; color: var(--text-secondary);"><?php echo htmlspecialchars($user_name); ?></span>
                <div class="user-avatar" onclick="showLogoutConfirm()">
                    <?php echo strtoupper(substr($user_name, 0, 1)); ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Mobile Views Container -->
    <div class="container-fluid py-2">

        <!-- ==================== TECHNICIAN PORTAL ==================== -->
        <?php if ($is_technician): ?>
            <!-- View: Dashboard -->
            <div class="app-view active" id="view-dashboard">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h4 class="fw-bold mb-0">Tech Dashboard</h4>
                    <span class="badge bg-secondary">Technician</span>
                </div>

                <!-- Stats Carousel Cards -->
                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <div class="glass-card text-center py-3">
                            <i class="fas fa-boxes fa-2x text-info mb-2"></i>
                            <h3 class="fw-bold mb-0" id="tech-total-items">--</h3>
                            <small class="text-secondary">My Equipment</small>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="glass-card text-center py-3">
                            <i class="fas fa-route fa-2x text-warning mb-2"></i>
                            <h3 class="fw-bold mb-0" id="tech-active-batches">--</h3>
                            <small class="text-secondary">Active Batches</small>
                        </div>
                    </div>
                </div>

                <!-- Quick actions -->
                <div class="glass-card">
                    <h6 class="fw-bold mb-3"><i class="fas fa-bolt text-accent me-2"></i>Quick Actions</h6>
                    <div class="d-grid gap-2">
                        <button class="btn btn-primary btn-lg" style="background: var(--accent-gradient); border: none;" onclick="switchTab('tab-scan')">
                            <i class="fas fa-qrcode me-2"></i>Scan Equipment Now
                        </button>
                    </div>
                </div>

                <!-- Active Batches Tracker -->
                <div class="mb-2 d-flex justify-content-between align-items-center">
                    <h6 class="fw-bold mb-0">Active Batches</h6>
                    <a href="#" class="small text-decoration-none text-accent" onclick="switchTab('tab-history')">See All</a>
                </div>
                <div id="tech-active-batches-list">
                    <div class="text-center py-4 text-secondary">
                        <i class="fas fa-spinner fa-spin fa-2x mb-2"></i>
                        <p class="small mb-0">Loading batches...</p>
                    </div>
                </div>
            </div>

            <!-- View: Scan / Operations -->
            <div class="app-view" id="view-scan">
                <h4 class="fw-bold mb-3">Equipment Scan</h4>

                <!-- QR Scanner Card -->
                <div class="glass-card text-center">
                    <div class="scanner-frame">
                        <video id="scanner-video-preview" autoplay playsinline></video>
                        <div class="scanner-overlay" id="scanner-overlay"></div>
                        <div class="scanner-laser" id="scanner-laser"></div>
                    </div>

                    <div id="scanner-prompt" class="py-4">
                        <i class="fas fa-camera fa-3x mb-3 text-secondary"></i>
                        <h5>Access Mobile Camera</h5>
                        <p class="small text-secondary px-3">Start camera to read equipment QR Code labels instantly.</p>
                        <button class="btn btn-outline-info rounded-pill px-4 mt-2" onclick="startMobileScanner()">
                            <i class="fas fa-play me-2"></i>Start Camera
                        </button>
                    </div>

                    <div id="scanner-active-controls" style="display: none;" class="py-2">
                        <button class="btn btn-outline-danger rounded-pill px-4" onclick="stopMobileScanner()">
                            <i class="fas fa-stop me-2"></i>Stop Camera
                        </button>
                        <div class="px-3 mt-3" id="camera-select-container" style="display: none;">
                            <label class="small text-secondary mb-1 d-block" style="font-size: 0.8rem;"><i class="fas fa-camera me-1"></i>Select Active Camera:</label>
                            <select id="mobile-camera-select" class="form-select border-glass" style="background: var(--bg-input); color: var(--text-primary); font-size: 0.85rem; border-radius: 12px; border: 1px solid var(--border-glass);"></select>
                        </div>
                    </div>
                </div>

                <!-- Manual Entry Lookup -->
                <div class="glass-card">
                    <h6 class="fw-bold mb-3"><i class="fas fa-keyboard text-accent me-2"></i>Manual Lookup</h6>
                    <div class="input-group">
                        <input type="text" id="manualLookupInput" class="glass-input" placeholder="Enter Equipment ID / Serial">
                        <button class="btn btn-outline-info px-3" style="border-radius: 0 12px 12px 0;" onclick="lookupItemMobile()">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                </div>
            </div>

            <!-- View: Batches / History -->
            <div class="app-view" id="view-history">
                <h4 class="fw-bold mb-3">My Batches</h4>

                <!-- Filters -->
                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <div class="glass-card py-2 px-3 h-100">
                            <div class="d-flex align-items-center gap-2">
                                <i class="fas fa-search text-secondary"></i>
                                <input type="text" id="tech-batches-search" class="w-100" style="background: transparent; border: none; color: white; outline: none; font-size: 0.85rem;" placeholder="Search..." onkeyup="filterTechBatchesLocal()">
                            </div>
                        </div>
                    </div>
                    <div class="col-6">
                        <select id="tech-status-filter" class="form-select border-glass" style="background: var(--bg-input); color: var(--text-primary); font-size: 0.85rem; border-radius: 12px; height: 100%; border: 1px solid var(--border-glass);" onchange="filterTechBatchesLocal()">
                            <option value="">All Statuses</option>
                            <option value="pending">Pending</option>
                            <option value="approved">Approved</option>
                            <option value="rejected">Rejected</option>
                            <option value="completed">Completed</option>
                        </select>
                    </div>
                </div>

                <!-- Batches list container -->
                <div id="tech-batches-history-list">
                    <div class="text-center py-4 text-secondary">
                        <i class="fas fa-spinner fa-spin fa-2x mb-2"></i>
                        <p class="small mb-0">Loading batches...</p>
                    </div>
                </div>
            </div>

            <!-- ==================== DRIVER PORTAL ==================== -->
        <?php elseif ($is_driver): ?>
            <!-- View: Dashboard -->
            <div class="app-view active" id="view-dashboard">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h4 class="fw-bold mb-0">Driver Hub</h4>
                    <span class="badge bg-success">Driver</span>
                </div>

                <!-- Stats Dashboard -->
                <div class="row g-2 mb-3">
                    <div class="col-4">
                        <div class="glass-card text-center py-3 px-1">
                            <h3 class="fw-bold mb-0 text-warning" id="driver-pending-load">--</h3>
                            <small class="text-secondary" style="font-size: 0.72rem;">Pending Load</small>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="glass-card text-center py-3 px-1">
                            <h3 class="fw-bold mb-0 text-info" id="driver-in-transit">--</h3>
                            <small class="text-secondary" style="font-size: 0.72rem;">In Transit</small>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="glass-card text-center py-3 px-1">
                            <h3 class="fw-bold mb-0 text-success" id="driver-delivered">--</h3>
                            <small class="text-secondary" style="font-size: 0.72rem;">Delivered</small>
                        </div>
                    </div>
                </div>

                <!-- Active Deliveries -->
                <h6 class="fw-bold mb-3 mt-4">Active Deliveries</h6>
                <div id="driver-active-batches-list">
                    <div class="text-center py-4 text-secondary">
                        <i class="fas fa-spinner fa-spin fa-2x mb-2"></i>
                        <p class="small mb-0">Loading active transport batches...</p>
                    </div>
                </div>
            </div>

            <!-- View: History (Driver) -->
            <div class="app-view" id="view-history">
                <h4 class="fw-bold mb-3">My Deliveries</h4>

                <!-- Filters -->
                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <div class="glass-card py-2 px-3 h-100">
                            <div class="d-flex align-items-center gap-2">
                                <i class="fas fa-search text-secondary"></i>
                                <input type="text" id="driver-batches-search" class="w-100" style="background: transparent; border: none; color: white; outline: none; font-size: 0.85rem;" placeholder="Search..." onkeyup="filterDriverBatchesLocal()">
                            </div>
                        </div>
                    </div>
                    <div class="col-6">
                        <select id="driver-status-filter" class="form-select border-glass" style="background: var(--bg-input); color: var(--text-primary); font-size: 0.85rem; border-radius: 12px; height: 100%; border: 1px solid var(--border-glass);" onchange="filterDriverBatchesLocal()">
                            <option value="">All Statuses</option>
                            <option value="pending">Pending</option>
                            <option value="approved">Approved</option>
                            <option value="completed">Completed</option>
                        </select>
                    </div>
                </div>

                <div id="driver-completed-batches-list">
                    <div class="text-center py-4 text-secondary">
                        <i class="fas fa-spinner fa-spin fa-2x mb-2"></i>
                        <p class="small mb-0">Loading deliveries...</p>
                    </div>
                </div>
            </div>

            <!-- ==================== STOCK CONTROLLER PORTAL ==================== -->
        <?php else: ?>
            <!-- View: Dashboard -->
            <div class="app-view active" id="view-dashboard">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h4 class="fw-bold mb-0">Manager Hub</h4>
                    <span class="badge bg-primary"><?php echo ($user_role === 'admin') ? 'Administrator' : (($user_role === 'manager') ? 'Manager' : 'Stock Controller'); ?></span>
                </div>

                <!-- Core Inventory Stats (2 Squares per Row on Mobile) -->
                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <div class="card text-white p-3 rounded-4 border-0 text-center shadow-sm" style="background-color: #062925 !important;">
                            <div style="font-size: 0.85rem; opacity: 0.85;">Total</div>
                            <div class="fw-bold" style="font-size: 1.35rem;"><?php echo $stats['total_items'] ?? 0; ?></div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="card text-white p-3 rounded-4 border-0 text-center shadow-sm" style="background-color: #044A42 !important;">
                            <div style="font-size: 0.85rem; opacity: 0.85;">Available</div>
                            <div class="fw-bold" style="font-size: 1.35rem;"><?php echo $stats['available'] ?? 0; ?></div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="card text-white p-3 rounded-4 border-0 text-center shadow-sm" style="background-color: #3A9188 !important;">
                            <div style="font-size: 0.85rem; opacity: 0.85;">In Use</div>
                            <div class="fw-bold" style="font-size: 1.35rem;"><?php echo $stats['in_use'] ?? 0; ?></div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="card text-dark p-3 rounded-4 border-0 text-center shadow-sm" style="background-color: #B8E1DD !important; color: #062925 !important;">
                            <div style="font-size: 0.85rem; opacity: 0.85;">Categories</div>
                            <div class="fw-bold" style="font-size: 1.35rem;"><?php echo $stats['categories'] ?? 0; ?></div>
                        </div>
                    </div>
                </div>

                <!-- Mini Stats Dashboard -->
                <div class="row g-2 mb-3">
                    <div class="col-4">
                        <div class="glass-card text-center py-3 px-1">
                            <h3 class="fw-bold mb-0 text-warning" id="ctrl-pending-count">--</h3>
                            <small class="text-secondary" style="font-size: 0.75rem;">Pending</small>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="glass-card text-center py-3 px-1">
                            <h3 class="fw-bold mb-0 text-success" id="ctrl-approved-count">--</h3>
                            <small class="text-secondary" style="font-size: 0.75rem;">Approved</small>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="glass-card text-center py-3 px-1">
                            <h3 class="fw-bold mb-0 text-danger" id="ctrl-rejected-count">--</h3>
                            <small class="text-secondary" style="font-size: 0.75rem;">Rejected</small>
                        </div>
                    </div>
                </div>

                <!-- Pending Alerts Banner -->
                <div class="glass-card d-flex align-items-center justify-content-between" style="border-left: 4px solid var(--warning-color);">
                    <div>
                        <h6 class="fw-bold mb-1">Incoming Approvals</h6>
                        <p class="small text-secondary mb-0" id="ctrl-pending-msg">Checking for approvals...</p>
                    </div>
                    <button class="btn btn-sm btn-outline-warning rounded-pill px-3" onclick="switchTab('tab-pending')">Review</button>
                </div>

                <!-- Recent Activities -->
                <h6 class="fw-bold mb-3 mt-4">All Active Batches</h6>
                <div id="ctrl-all-batches-list">
                    <div class="text-center py-4 text-secondary">
                        <i class="fas fa-spinner fa-spin fa-2x mb-2"></i>
                        <p class="small mb-0">Loading batches...</p>
                    </div>
                </div>
            </div>

            <!-- View: Pending Approvals -->
            <div class="app-view" id="view-pending">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h4 class="fw-bold mb-0">Pending Approvals</h4>
                    <span class="status-pill pill-pending" id="pending-badge-count">0 Batches</span>
                </div>

                <div id="ctrl-pending-batches-list">
                    <div class="text-center py-4 text-secondary">
                        <i class="fas fa-spinner fa-spin fa-2x mb-2"></i>
                        <p class="small mb-0">Loading pending batches...</p>
                    </div>
                </div>
            </div>

            <!-- View: History (Stock Controller) -->
            <div class="app-view" id="view-history">
                <h4 class="fw-bold mb-3">All Batches</h4>

                <!-- Filters -->
                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <div class="glass-card py-2 px-3 h-100">
                            <div class="d-flex align-items-center gap-2">
                                <i class="fas fa-search text-secondary"></i>
                                <input type="text" id="ctrl-batches-search" class="w-100" style="background: transparent; border: none; color: white; outline: none; font-size: 0.85rem;" placeholder="Search..." onkeyup="filterCtrlBatchesLocal()">
                            </div>
                        </div>
                    </div>
                    <div class="col-6">
                        <select id="ctrl-status-filter" class="form-select border-glass" style="background: var(--bg-input); color: var(--text-primary); font-size: 0.85rem; border-radius: 12px; height: 100%; border: 1px solid var(--border-glass);" onchange="filterCtrlBatchesLocal()">
                            <option value="">All Statuses</option>
                            <option value="pending">Pending Approval</option>
                            <option value="approved">Approved</option>
                            <option value="rejected">Rejected</option>
                            <option value="completed">Completed</option>
                        </select>
                    </div>
                </div>

                <div id="ctrl-completed-batches-list">
                    <div class="text-center py-4 text-secondary">
                        <i class="fas fa-spinner fa-spin fa-2x mb-2"></i>
                        <p class="small mb-0">Loading batches...</p>
                    </div>
                </div>
            </div>
        <?php endif; ?>

    </div>

    <!-- Glass Bottom Nav Bar -->
    <div class="bottom-nav">
        <a href="#" class="tab-item active" id="tab-home" onclick="switchTab('tab-home')">
            <i class="fas fa-house"></i>
            <span>Home</span>
        </a>
        <?php if ($is_technician): ?>
            <a href="#" class="tab-item" id="tab-scan" onclick="switchTab('tab-scan')">
                <i class="fas fa-qrcode"></i>
                <span>Scan</span>
            </a>
        <?php elseif ($is_controller): ?>
            <a href="#" class="tab-item" id="tab-pending" onclick="switchTab('tab-pending')">
                <i class="fas fa-clock"></i>
                <span>Pending</span>
            </a>
        <?php endif; ?>
        <a href="#" class="tab-item" id="tab-history" onclick="switchTab('tab-history')">
            <i class="fas fa-boxes-stacked"></i>
            <span>Batches</span>
        </a>
        <?php if ($user_role === 'admin' || $user_role === 'manager'): ?>
            <a href="#" class="tab-item" id="tab-menu" onclick="openAdminMenu()">
                <i class="fas fa-bars"></i>
                <span>Menu</span>
            </a>
        <?php else: ?>
            <a href="#" class="tab-item" onclick="showLogoutConfirm()">
                <i class="fas fa-right-from-bracket"></i>
                <span>Logout</span>
            </a>
        <?php endif; ?>
    </div>

    <!-- ==================== INTERACTIVE APP DRAWER ==================== -->
    <div class="drawer-overlay" id="drawerOverlay" onclick="closeDrawer()"></div>
    <div class="app-drawer" id="appDrawer">
        <div class="drawer-handle" onclick="closeDrawer()"></div>
        <div id="drawerContent">
            <!-- Dynamic Drawer Content goes here -->
        </div>
    </div>

    <!-- Confirm Logout Drawer -->
    <div class="app-drawer" id="logoutDrawer" style="z-index: 1300;">
        <div class="drawer-handle" onclick="closeLogoutConfirm()"></div>
        <div class="text-center py-3">
            <i class="fas fa-sign-out-alt fa-3x text-warning mb-3"></i>
            <h5 class="fw-bold">Logout Confirmation</h5>
            <p class="text-secondary small mb-4">Are you sure you want to end your portal session? You will be returned to the login screen.</p>
            <div class="d-flex gap-3">
                <button class="btn btn-outline-secondary w-50 py-2 rounded-3" onclick="closeLogoutConfirm()">Cancel</button>
                <a href="logout.php" class="btn btn-danger w-50 py-2 rounded-3">Logout</a>
            </div>
        </div>
    </div>

    <!-- QR Code Scanner Scripts -->
    <script src="https://unpkg.com/@zxing/library@0.18.6/umd/index.min.js"></script>

    <!-- Custom High Fidelity JS Controller -->
    <script>
        // Global State Configuration
        const isTech = <?php echo $is_technician ? 'true' : 'false'; ?>;
        const isDriver = <?php echo $is_driver ? 'true' : 'false'; ?>;
        const isController = <?php echo $is_controller ? 'true' : 'false'; ?>;
        const currentUserId = <?php echo $user_id; ?>;
        let selectedBatchData = null;
        let activeVideoReader = null;
        let selectedCameraId = null;
        let allTechBatches = [];
        let allDriverBatches = [];
        let allCtrlBatches = [];

        function startDecodingWithCamera(cameraId) {
            if (!activeVideoReader) return;
            activeVideoReader.reset();
            activeVideoReader.decodeFromVideoDevice(cameraId, 'scanner-video-preview', (result, err) => {
                if (result) {
                    const scannedText = result.getText();
                    stopMobileScanner();
                    processScannedCode(scannedText);
                }
            });
        }

        document.addEventListener('DOMContentLoaded', () => {
            // Init theme preference
            const savedTheme = localStorage.getItem('app-theme') || 'dark';
            if (savedTheme === 'light') {
                document.body.classList.add('light-theme');
                const icon = document.getElementById('theme-icon');
                if (icon) icon.className = 'fas fa-sun';
            }

            // Camera select change listener
            const mobileCamSelect = document.getElementById('mobile-camera-select');
            if (mobileCamSelect) {
                mobileCamSelect.addEventListener('change', function() {
                    selectedCameraId = this.value;
                    startDecodingWithCamera(selectedCameraId);
                });
            }

            fetchDashboardMetrics();
            fetchBatchesData();

            // Set background loop to refresh dashboard every 30 seconds
            setInterval(() => {
                fetchDashboardMetrics();
                fetchBatchesData();
            }, 30000);
        });

        // Toggle Day and Night themes dynamically
        function toggleAppTheme() {
            const body = document.body;
            const icon = document.getElementById('theme-icon');
            if (body.classList.contains('light-theme')) {
                body.classList.remove('light-theme');
                if (icon) icon.className = 'fas fa-moon';
                localStorage.setItem('app-theme', 'dark');
                showToast('Switched to Night Mode 🌙', 'success');
            } else {
                body.classList.add('light-theme');
                if (icon) icon.className = 'fas fa-sun';
                localStorage.setItem('app-theme', 'light');
                showToast('Switched to Day Mode ☀️', 'success');
            }
        }

        // App Toast Message System
        function showToast(message, type = 'info') {
            const container = document.getElementById('toastContainer');
            const toastId = 'toast_' + Math.random().toString(36).substr(2, 9);

            let iconClass = 'fa-info-circle';
            if (type === 'success') iconClass = 'fa-circle-check';
            if (type === 'error') iconClass = 'fa-triangle-exclamation';
            if (type === 'warning') iconClass = 'fa-bell';

            const html = `
                <div class="app-toast ${type}" id="${toastId}">
                    <i class="fas ${iconClass} app-toast-icon"></i>
                    <div class="app-toast-text">${message}</div>
                </div>
            `;
            container.insertAdjacentHTML('beforeend', html);

            const toast = document.getElementById(toastId);
            setTimeout(() => toast.classList.add('show'), 50);

            // Auto dismiss toast after 3.5s
            setTimeout(() => {
                toast.classList.remove('show');
                setTimeout(() => toast.remove(), 300);
            }, 3500);
        }

        // Drawer System
        function openDrawer(htmlContent) {
            document.getElementById('drawerContent').innerHTML = htmlContent;
            document.getElementById('drawerOverlay').style.display = 'block';
            setTimeout(() => {
                document.getElementById('drawerOverlay').style.opacity = '1';
                document.getElementById('appDrawer').classList.add('show');
            }, 50);
        }

        // Close Drawer
        function closeDrawer() {
            document.getElementById('appDrawer').classList.remove('show');
            document.getElementById('drawerOverlay').style.opacity = '0';
            setTimeout(() => {
                document.getElementById('drawerOverlay').style.display = 'none';
            }, 300);
        }

        // Open Admin Menu Bottom Drawer Grid
        function openAdminMenu() {
            const menuHtml = `
                <div class="px-2">
                    <div class="text-center mb-4">
                        <h5 class="fw-bold mb-1" style="letter-spacing: -0.5px;"><i class="fas fa-th-large text-accent me-2"></i>Administration Portal</h5>
                        <p class="text-secondary small">Select a system module or setup action below.</p>
                    </div>
                    <div class="row g-3">
                        <div class="col-4">
                            <a href="dashboard_full.php" class="text-decoration-none d-flex flex-column align-items-center text-center p-3 glass-card h-100" style="border-radius: 16px;">
                                <div class="mb-2 d-flex align-items-center justify-content-center" style="width: 48px; height: 48px; border-radius: 12px; background: rgba(35, 76, 106, 0.15); color: #234C6A; font-size: 1.3rem;">
                                    <i class="fas fa-chart-pie"></i>
                                </div>
                                <span style="font-size: 0.78rem; font-weight: 700; color: var(--text-primary);">Dashboard</span>
                            </a>
                        </div>
                        <div class="col-4">
                            <a href="events.php" class="text-decoration-none d-flex flex-column align-items-center text-center p-3 glass-card h-100" style="border-radius: 16px;">
                                <div class="mb-2 d-flex align-items-center justify-content-center" style="width: 48px; height: 48px; border-radius: 12px; background: rgba(79, 70, 229, 0.15); color: #4f46e5; font-size: 1.3rem;">
                                    <i class="fas fa-calendar-days"></i>
                                </div>
                                <span style="font-size: 0.78rem; font-weight: 700; color: var(--text-primary);">Events</span>
                            </a>
                        </div>
                        <div class="col-4">
                            <a href="items.php" class="text-decoration-none d-flex flex-column align-items-center text-center p-3 glass-card h-100" style="border-radius: 16px;">
                                <div class="mb-2 d-flex align-items-center justify-content-center" style="width: 48px; height: 48px; border-radius: 12px; background: rgba(124, 58, 237, 0.15); color: #7c3aed; font-size: 1.3rem;">
                                    <i class="fas fa-boxes-stacked"></i>
                                </div>
                                <span style="font-size: 0.78rem; font-weight: 700; color: var(--text-primary);">Equipment</span>
                            </a>
                        </div>
                        <div class="col-4">
                            <a href="accessories.php" class="text-decoration-none d-flex flex-column align-items-center text-center p-3 glass-card h-100" style="border-radius: 16px;">
                                <div class="mb-2 d-flex align-items-center justify-content-center" style="width: 48px; height: 48px; border-radius: 12px; background: rgba(236, 72, 153, 0.15); color: #ec4899; font-size: 1.3rem;">
                                    <i class="fas fa-plug"></i>
                                </div>
                                <span style="font-size: 0.78rem; font-weight: 700; color: var(--text-primary);">Accessories</span>
                            </a>
                        </div>
                        <div class="col-4">
                            <a href="import_items.php" class="text-decoration-none d-flex flex-column align-items-center text-center p-3 glass-card h-100" style="border-radius: 16px;">
                                <div class="mb-2 d-flex align-items-center justify-content-center" style="width: 48px; height: 48px; border-radius: 12px; background: rgba(6, 182, 212, 0.15); color: #06b6d4; font-size: 1.3rem;">
                                    <i class="fas fa-cloud-arrow-up"></i>
                                </div>
                                <span style="font-size: 0.78rem; font-weight: 700; color: var(--text-primary);">Import</span>
                            </a>
                        </div>
                        <div class="col-4">
                            <a href="scan_bulk.php" class="text-decoration-none d-flex flex-column align-items-center text-center p-3 glass-card h-100" style="border-radius: 16px;">
                                <div class="mb-2 d-flex align-items-center justify-content-center" style="width: 48px; height: 48px; border-radius: 12px; background: rgba(16, 185, 129, 0.15); color: #10b981; font-size: 1.3rem;">
                                    <i class="fas fa-qrcode"></i>
                                </div>
                                <span style="font-size: 0.78rem; font-weight: 700; color: var(--text-primary);">Scanner</span>
                            </a>
                        </div>
                        <div class="col-4">
                            <a href="batch_history.php" class="text-decoration-none d-flex flex-column align-items-center text-center p-3 glass-card h-100" style="border-radius: 16px;">
                                <div class="mb-2 d-flex align-items-center justify-content-center" style="width: 48px; height: 48px; border-radius: 12px; background: rgba(245, 158, 11, 0.15); color: #f59e0b; font-size: 1.3rem;">
                                    <i class="fas fa-check-double"></i>
                                </div>
                                <span style="font-size: 0.78rem; font-weight: 700; color: var(--text-primary);">Approvals</span>
                            </a>
                        </div>
                        <div class="col-4">
                            <a href="reports.php" class="text-decoration-none d-flex flex-column align-items-center text-center p-3 glass-card h-100" style="border-radius: 16px;">
                                <div class="mb-2 d-flex align-items-center justify-content-center" style="width: 48px; height: 48px; border-radius: 12px; background: rgba(249, 115, 22, 0.15); color: #f97316; font-size: 1.3rem;">
                                    <i class="fas fa-chart-column"></i>
                                </div>
                                <span style="font-size: 0.78rem; font-weight: 700; color: var(--text-primary);">Reports</span>
                            </a>
                        </div>
                        <div class="col-4">
                            <a href="hr_center.php" class="text-decoration-none d-flex flex-column align-items-center text-center p-3 glass-card h-100" style="border-radius: 16px;">
                                <div class="mb-2 d-flex align-items-center justify-content-center" style="width: 48px; height: 48px; border-radius: 12px; background: rgba(20, 184, 166, 0.15); color: #14b8a6; font-size: 1.3rem;">
                                    <i class="fas fa-users-gear"></i>
                                </div>
                                <span style="font-size: 0.78rem; font-weight: 700; color: var(--text-primary);">HR Center</span>
                            </a>
                        </div>
                        <div class="col-4">
                            <a href="profile.php" class="text-decoration-none d-flex flex-column align-items-center text-center p-3 glass-card h-100" style="border-radius: 16px;">
                                <div class="mb-2 d-flex align-items-center justify-content-center" style="width: 48px; height: 48px; border-radius: 12px; background: rgba(168, 85, 247, 0.15); color: #a855f7; font-size: 1.3rem;">
                                    <i class="fas fa-user-circle"></i>
                                </div>
                                <span style="font-size: 0.78rem; font-weight: 700; color: var(--text-primary);">My Profile</span>
                            </a>
                        </div>
                        <div class="col-4">
                            <a href="stock_locations.php" class="text-decoration-none d-flex flex-column align-items-center text-center p-3 glass-card h-100" style="border-radius: 16px;">
                                <div class="mb-2 d-flex align-items-center justify-content-center" style="width: 48px; height: 48px; border-radius: 12px; background: rgba(14, 165, 233, 0.15); color: #0ea5e9; font-size: 1.3rem;">
                                    <i class="fas fa-warehouse"></i>
                                </div>
                                <span style="font-size: 0.78rem; font-weight: 700; color: var(--text-primary);">Stock Locs</span>
                            </a>
                        </div>
                        <div class="col-4">
                            <div class="dropdown h-100 w-100">
                                <button class="btn text-decoration-none d-flex flex-column align-items-center text-center p-3 glass-card h-100 w-100 dropdown-toggle border-0" type="button" data-bs-toggle="dropdown" aria-expanded="false" style="border-radius: 16px; background: var(--bg-card); color: var(--text-primary); box-shadow: none;">
                                    <div class="mb-2 d-flex align-items-center justify-content-center" style="width: 48px; height: 48px; border-radius: 12px; background: rgba(99, 102, 241, 0.15); color: #6366f1; font-size: 1.3rem;">
                                        <i class="fas fa-sliders"></i>
                                    </div>
                                    <span style="font-size: 0.78rem; font-weight: 700; color: var(--text-primary);">Setup More</span>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-dark border-glass p-2 shadow-lg" style="border-radius: 16px; background: rgba(16, 23, 38, 0.95); backdrop-filter: blur(10px); z-index: 2000;">
                                    <li><a class="dropdown-item py-2" href="categories.php" style="font-size: 0.85rem; border-radius: 8px;"><i class="fas fa-tags me-2 text-rose"></i> Categories</a></li>
                                    <li><a class="dropdown-item py-2" href="departments.php" style="font-size: 0.85rem; border-radius: 8px;"><i class="fas fa-building me-2 text-info"></i> Departments</a></li>
                                    <li><a class="dropdown-item py-2" href="locations.php" style="font-size: 0.85rem; border-radius: 8px;"><i class="fas fa-map-pin me-2 text-danger"></i> Locations</a></li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    <div class="mt-4 pt-3 border-top border-glass text-center">
                        <button class="btn btn-outline-danger rounded-pill px-4 py-2 w-100" onclick="closeDrawer(); showLogoutConfirm();" style="font-size: 0.9rem; font-weight: 700;">
                            <i class="fas fa-sign-out-alt me-2"></i>Logout Session
                        </button>
                    </div>
                </div>
            `;
            openDrawer(menuHtml);
        }

        // Logout Drawers
        function showLogoutConfirm() {
            document.getElementById('drawerOverlay').style.display = 'block';
            setTimeout(() => {
                document.getElementById('drawerOverlay').style.opacity = '1';
                document.getElementById('logoutDrawer').classList.add('show');
            }, 50);
        }

        function closeLogoutConfirm() {
            document.getElementById('logoutDrawer').classList.remove('show');
            document.getElementById('drawerOverlay').style.opacity = '0';
            setTimeout(() => {
                document.getElementById('drawerOverlay').style.display = 'none';
            }, 300);
        }

        // Tab Navigation
        function switchTab(tabId) {
            // Remove active nav highlight
            document.querySelectorAll('.tab-item').forEach(el => el.classList.remove('active'));
            const activeNav = document.getElementById(tabId);
            if (activeNav) activeNav.classList.add('active');

            // Hide all views
            document.querySelectorAll('.app-view').forEach(el => el.classList.remove('active'));

            // Display active view
            let targetViewId = 'view-dashboard';
            if (tabId === 'tab-scan') targetViewId = 'view-scan';
            if (tabId === 'tab-pending') targetViewId = 'view-pending';
            if (tabId === 'tab-history') targetViewId = 'view-history';

            const activeView = document.getElementById(targetViewId);
            if (activeView) activeView.classList.add('active');

            // Manage camera active state
            if (tabId !== 'tab-scan') {
                stopMobileScanner();
            }
        }

        // Fetch metrics dashboard data
        function fetchDashboardMetrics() {
            if (isTech) {
                // Fetch Technician specific totals
                fetch('api/batches/technician_list.php')
                    .then(r => r.json())
                    .then(d => {
                        if (d.success) {
                            // Find total items & active batches
                            const batches = d.batches || [];
                            const activeCount = batches.filter(b => b.status === 'pending' || b.status === 'in_transit').length;

                            // Let's count items in user's hands
                            let totalItems = 0;
                            batches.forEach(b => {
                                if (b.status === 'approved' || b.status === 'completed') {
                                    totalItems += parseInt(b.total_quantity || 0);
                                }
                            });

                            document.getElementById('tech-total-items').textContent = totalItems;
                            document.getElementById('tech-active-batches').textContent = activeCount;
                        }
                    })
                    .catch(() => showToast('Failed to sync metrics', 'error'));
            } else if (isDriver) {
                // Fetch Driver Dashboard metrics
                fetch('api/batches/driver_list.php')
                    .then(r => r.json())
                    .then(d => {
                        if (d.success) {
                            const batches = d.batches || [];
                            const pendingLoad = batches.filter(b => (b.status === 'approved' && parseInt(b.driver_verified) === 0) || b.status === 'pending').length;
                            const inTransit = batches.filter(b => b.status === 'approved' && parseInt(b.driver_verified) === 1).length;
                            const delivered = batches.filter(b => b.status === 'completed').length;

                            document.getElementById('driver-pending-load').textContent = pendingLoad;
                            document.getElementById('driver-in-transit').textContent = inTransit;
                            document.getElementById('driver-delivered').textContent = delivered;
                        }
                    })
                    .catch(() => showToast('Failed to sync metrics', 'error'));
            } else {
                // Fetch Stock Controller Dashboard metrics
                fetch('api/batches/list.php')
                    .then(r => r.json())
                    .then(d => {
                        if (d.success) {
                            const batches = (d.batches || []).filter(b => b.movement_type && ['transport', 'stock_to_venue_transport', 'stock_to_stock'].includes(b.movement_type));
                            const pending = batches.filter(b => b.status === 'pending').length;
                            const approved = batches.filter(b => b.status === 'approved').length;
                            const rejected = batches.filter(b => b.status === 'rejected').length;

                            document.getElementById('ctrl-pending-count').textContent = pending;
                            document.getElementById('ctrl-approved-count').textContent = approved;
                            document.getElementById('ctrl-rejected-count').textContent = rejected;

                            // Update Pending banner
                            const bannerMsg = document.getElementById('ctrl-pending-msg');
                            const pendingBadgeCount = document.getElementById('pending-badge-count');
                            if (pendingBadgeCount) pendingBadgeCount.textContent = pending + ' Batches';

                            if (pending > 0) {
                                bannerMsg.innerHTML = `<strong class="text-warning">${pending} batches</strong> require your immediate verification.`;
                            } else {
                                bannerMsg.textContent = 'All batches are completely verified! Excellent.';
                            }
                        }
                    })
                    .catch(() => showToast('Failed to sync stats', 'error'));
            }
        }

        // Fetch batches data lists
        function fetchBatchesData() {
            if (isTech) {
                // Tech Dashboard batches
                fetch('api/batches/technician_list.php')
                    .then(r => r.json())
                    .then(d => {
                        if (d.success) {
                            allTechBatches = d.batches || [];
                            renderTechDashboardBatches(allTechBatches);
                            filterTechBatchesLocal();
                        }
                    });
            } else if (isDriver) {
                // Driver batches
                fetch('api/batches/driver_list.php')
                    .then(r => r.json())
                    .then(d => {
                        if (d.success) {
                            allDriverBatches = d.batches || [];
                            renderDriverActiveBatches(allDriverBatches);
                            filterDriverBatchesLocal();
                        }
                    });
            } else {
                // Controller batches
                fetch('api/batches/list.php')
                    .then(r => r.json())
                    .then(d => {
                        if (d.success) {
                            allCtrlBatches = (d.batches || []).filter(b => b.movement_type && ['transport', 'stock_to_venue_transport', 'stock_to_stock'].includes(b.movement_type));
                            renderCtrlDashboardBatches(allCtrlBatches);
                            renderCtrlPendingBatches(allCtrlBatches);
                            filterCtrlBatchesLocal();
                        }
                    });
            }
        }

        // Render functions for Technicians
        function renderTechDashboardBatches(batches) {
            const list = document.getElementById('tech-active-batches-list');
            const active = batches.filter(b => b.status === 'pending' || b.status === 'in_transit');

            if (active.length === 0) {
                list.innerHTML = `
                    <div class="glass-card text-center text-secondary py-4">
                        <i class="fas fa-circle-check fa-2x mb-2 text-success"></i>
                        <p class="small mb-0">No active movements at this time.</p>
                    </div>
                `;
                return;
            }

            let html = '';
            active.forEach(b => {
                html += renderBatchCardMobile(b);
            });
            list.innerHTML = html;
        }

        function renderTechHistoryBatches(batches) {
            const list = document.getElementById('tech-batches-history-list');
            if (batches.length === 0) {
                list.innerHTML = `<div class="text-center py-4 text-secondary"><p>No history found</p></div>`;
                return;
            }

            let html = '';
            batches.forEach(b => {
                html += renderBatchCardMobile(b);
            });
            list.innerHTML = html;
        }

        // Render functions for Stock Controller
        function renderCtrlDashboardBatches(batches) {
            const list = document.getElementById('ctrl-all-batches-list');
            if (batches.length === 0) {
                list.innerHTML = `<div class="text-center py-4 text-secondary"><p>No batches found</p></div>`;
                return;
            }
            let html = '';
            batches.slice(0, 5).forEach(b => {
                html += renderBatchCardMobile(b);
            });
            list.innerHTML = html;
        }

        // Render pending approvals
        function renderCtrlPendingBatches(batches) {
            const list = document.getElementById('ctrl-pending-batches-list');
            const pending = batches.filter(b => b.status === 'pending');

            if (pending.length === 0) {
                list.innerHTML = `
                    <div class="glass-card text-center text-secondary py-4">
                        <i class="fas fa-circle-check fa-2x mb-2 text-success"></i>
                        <p class="small mb-0">Hooray! No pending approvals left.</p>
                    </div>
                `;
                return;
            }

            let html = '';
            pending.forEach(b => {
                html += renderPendingBatchCardMobile(b);
            });
            list.innerHTML = html;
        }

        function renderCtrlHistoryBatches(batches) {
            const list = document.getElementById('ctrl-completed-batches-list');
            const completed = batches.filter(b => b.status === 'approved' || b.status === 'rejected' || b.status === 'completed');

            if (completed.length === 0) {
                list.innerHTML = `<div class="text-center py-4 text-secondary"><p>No history found</p></div>`;
                return;
            }

            let html = '';
            completed.forEach(b => {
                html += renderBatchCardMobile(b);
            });
            list.innerHTML = html;
        }

        // Render functions for Driver
        function renderDriverActiveBatches(batches) {
            const list = document.getElementById('driver-active-batches-list');
            const active = batches.filter(b => b.status === 'approved' || b.status === 'pending');

            if (active.length === 0) {
                list.innerHTML = `
                    <div class="glass-card text-center text-secondary py-4">
                        <i class="fas fa-circle-check fa-2x mb-2 text-success"></i>
                        <p class="small mb-0">No active transport deliveries assigned.</p>
                    </div>
                `;
                return;
            }

            let html = '';
            active.forEach(b => {
                let statusPill = '';
                let actionBtn = '';

                if (b.status === 'pending') {
                    statusPill = '<span class="status-pill pill-warning">PENDING APPROVAL</span>';
                    actionBtn = `<button class="btn btn-outline-secondary btn-sm w-100 py-2 rounded-3" disabled>
                                    <i class="fas fa-hourglass-half me-1"></i> Awaiting Approval
                                 </button>`;
                } else {
                    const isLoaded = parseInt(b.driver_verified) === 1;
                    statusPill = isLoaded ? '<span class="status-pill pill-info">IN TRANSIT</span>' : '<span class="status-pill pill-warning">PENDING LOAD</span>';

                    actionBtn = isLoaded ?
                        `<button class="btn btn-info text-white btn-sm w-100 py-2 rounded-3" onclick="completeDriverDeliveryMobile('${b.batch_id || b.batch_number}')">
                                <i class="fas fa-flag-checkered me-1"></i> Complete Delivery
                           </button>` :
                        `<button class="btn btn-success btn-sm w-100 py-2 rounded-3" onclick="verifyDriverLoadMobile('${b.batch_id || b.batch_number}')">
                                <i class="fas fa-check-circle me-1"></i> Verify Loaded
                           </button>`;
                }

                html += `
                    <div class="glass-card">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="fw-bold" style="font-size: 0.95rem;">Batch #${b.batch_id || b.batch_number}</span>
                            ${statusPill}
                        </div>
                        <div class="text-secondary small mb-3" style="font-size: 0.85rem;" onclick="viewBatchDetailsMobile('${b.batch_id || b.batch_number}')">
                            <div><i class="fas fa-calendar-day me-1"></i> Date: ${b.date || 'N/A'}</div>
                            <div><i class="fas fa-route me-1"></i> ${b.source_name || 'Stock'} &rarr; ${b.destination || 'N/A'}</div>
                            <div><i class="fas fa-boxes me-1"></i> Total ${b.item_count || 0} pieces of equipment</div>
                        </div>
                        <div class="d-flex gap-2">
                            <button class="btn btn-outline-secondary btn-sm w-50 py-2 rounded-3" onclick="viewBatchDetailsMobile('${b.batch_id || b.batch_number}')">
                                <i class="fas fa-eye me-1"></i> Details
                            </button>
                            <div class="w-50">
                                ${actionBtn}
                            </div>
                        </div>
                    </div>
                `;
            });
            list.innerHTML = html;
        }

        function renderDriverHistoryBatches(batches) {
            const list = document.getElementById('driver-completed-batches-list');
            const completed = batches.filter(b => b.status === 'completed');

            if (completed.length === 0) {
                list.innerHTML = `<div class="text-center py-4 text-secondary"><p>No delivered history found</p></div>`;
                return;
            }

            let html = '';
            completed.forEach(b => {
                html += renderBatchCardMobile(b);
            });
            list.innerHTML = html;
        }

        function verifyDriverLoadMobile(batchId) {
            showToast('Verifying load...', 'info');

            const formData = new FormData();
            formData.append('batch_id', batchId);

            fetch('api/batches/driver_verify.php', {
                    method: 'POST',
                    body: formData
                })
                .then(r => r.json())
                .then(d => {
                    if (d.success) {
                        showToast(d.message || 'Equipment load verified!', 'success');
                        fetchDashboardMetrics();
                        fetchBatchesData();
                    } else {
                        showToast(d.message || 'Failed to verify load', 'error');
                    }
                })
                .catch(() => showToast('Network or server error', 'error'));
        }

        function completeDriverDeliveryMobile(batchId) {
            showToast('Completing delivery...', 'info');

            const formData = new FormData();
            formData.append('batch_id', batchId);

            fetch('api/batches/driver_complete.php', {
                    method: 'POST',
                    body: formData
                })
                .then(r => r.json())
                .then(d => {
                    if (d.success) {
                        showToast(d.message || 'Delivery completed!', 'success');
                        fetchDashboardMetrics();
                        fetchBatchesData();
                    } else {
                        showToast(d.message || 'Failed to complete delivery', 'error');
                    }
                })
                .catch(() => showToast('Network or server error', 'error'));
        }



        // Generic Mobile Batch Card Render Helper
        function renderBatchCardMobile(b) {
            const statusClass = getStatusPillClass(b.status || b.approval_status);
            const statusLabel = (b.status || b.approval_status || 'Pending').toUpperCase();

            return `
                <div class="glass-card" onclick="viewBatchDetailsMobile('${b.batch_id || b.batch_number}')">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="fw-bold" style="font-size: 0.95rem;">Batch #${b.batch_id || b.batch_number}</span>
                        <span class="status-pill ${statusClass}">${statusLabel}</span>
                    </div>
                    <div class="row g-1 text-secondary small mb-2" style="font-size: 0.85rem;">
                        <div class="col-6"><i class="fas fa-calendar-day me-1"></i> ${b.date || b.created_at || 'N/A'}</div>
                        <div class="col-6"><i class="fas fa-boxes me-1"></i> ${b.item_count || 0} items</div>
                        <div class="col-12 mt-1"><i class="fas fa-location-dot me-1"></i> To: ${b.destination || b.destination_name || 'N/A'}</div>
                    </div>
                    <div class="d-flex align-items-center justify-content-between pt-2" style="border-top: 1px solid rgba(255,255,255,0.05);">
                        <span class="small text-secondary">Tech: ${b.technician || 'Unknown'}</span>
                        <span class="small text-accent fw-500">Details <i class="fas fa-angle-right ms-1"></i></span>
                    </div>
                </div>
            `;
        }

        // Pending Approval Card Render Helper
        function renderPendingBatchCardMobile(b) {
            return `
                <div class="glass-card" style="border-left: 3px solid var(--warning-color);">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="fw-bold" style="font-size: 0.95rem;">Batch #${b.batch_id || b.batch_number}</span>
                        <span class="status-pill pill-pending">PENDING</span>
                    </div>
                    <div class="text-secondary small mb-3" style="font-size: 0.85rem;" onclick="viewBatchDetailsMobile('${b.batch_id || b.batch_number}')">
                        <div><i class="fas fa-user me-1"></i> Technician: ${b.technician || 'Unknown'}</div>
                        <div><i class="fas fa-route me-1"></i> ${b.source_name || 'Stock'} &rarr; ${b.destination || b.destination_name || 'N/A'}</div>
                        <div><i class="fas fa-boxes me-1"></i> Total ${b.item_count || 0} pieces of equipment</div>
                    </div>
                    <div class="d-flex gap-2">
                        <button class="btn btn-outline-danger btn-sm w-50 py-2" onclick="promptApprovalAction('${b.batch_id || b.batch_number}', 'reject')">
                            <i class="fas fa-times me-1"></i> Reject
                        </button>
                        <button class="btn btn-success btn-sm w-50 py-2" onclick="promptApprovalAction('${b.batch_id || b.batch_number}', 'approve')">
                            <i class="fas fa-check me-1"></i> Approve
                        </button>
                    </div>
                </div>
            `;
        }

        // Status Pill Class Matcher
        function getStatusPillClass(status) {
            status = (status || '').toLowerCase();
            if (status === 'approved' || status === 'completed' || status === 'delivered') return 'pill-approved';
            if (status === 'rejected' || status === 'lost' || status === 'damaged') return 'pill-rejected';
            if (status === 'pending') return 'pill-pending';
            return 'pill-info';
        }

        // View Single Batch Details in App Drawer
        function viewBatchDetailsMobile(batchId) {
            openDrawer(`
                <div class="text-center py-4">
                    <i class="fas fa-spinner fa-spin fa-2x text-accent mb-2"></i>
                    <p class="small text-secondary">Loading batch details...</p>
                </div>
            `);

            fetch(`api/batches/details.php?batch_id=${batchId}`)
                .then(r => r.json())
                .then(d => {
                    if (d.success && d.batch) {
                        const b = d.batch;
                        let itemsHtml = '';
                        (b.items || []).forEach(item => {
                            itemsHtml += `
                                <div class="p-2 mb-2 rounded" style="background: var(--bg-input); border: 1px solid var(--border-glass); font-size: 0.85rem;">
                                    <div class="d-flex justify-content-between fw-bold">
                                        <span>${item.name}</span>
                                        <span class="text-accent">x${item.quantity || 1}</span>
                                    </div>
                                    <div class="text-secondary small">Serial: ${item.serial || 'N/A'} | Status: ${item.status}</div>
                                </div>
                            `;
                        });

                        if (!itemsHtml) {
                            itemsHtml = `<p class="small text-secondary text-center py-2">No items listed in this batch.</p>`;
                        }

                        const statusClass = getStatusPillClass(b.status || b.approval_status);
                        const statusLabel = (b.status || b.approval_status || 'Pending').toUpperCase();

                        let actionHtml = '';
                        if (!isTech && b.status === 'pending') {
                            actionHtml = `
                                <div class="d-flex gap-2 mt-4 pt-3" style="border-top: 1px solid rgba(255,255,255,0.08);">
                                    <button class="btn btn-outline-danger w-50 py-2" onclick="promptApprovalAction('${b.batch_id}', 'reject'); closeDrawer();">
                                        <i class="fas fa-times me-1"></i> Reject
                                    </button>
                                    <button class="btn btn-success w-50 py-2" onclick="promptApprovalAction('${b.batch_id}', 'approve'); closeDrawer();">
                                        <i class="fas fa-check me-1"></i> Approve
                                    </button>
                                </div>
                            `;
                        }

                        // Milestone Tracker logic
                        let isApproved = false;
                        let isTechOnboard = false;
                        let isLoaded = false;
                        let isCompleted = false;
                        let isRejected = false;

                        const appStatus = (b.approval_status || '').toLowerCase();
                        const bStatus = (b.status || '').toLowerCase();

                        if (appStatus === 'approved' || appStatus === 'completed' || bStatus === 'approved' || bStatus === 'completed') {
                            isApproved = true;
                        }
                        if (appStatus === 'rejected' || bStatus === 'rejected') {
                            isRejected = true;
                            isApproved = false;
                        }
                        if (parseInt(b.tech_onboard) === 1) {
                            isTechOnboard = true;
                        }
                        if (parseInt(b.driver_verified) === 1) {
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

<<<<<<< HEAD
=======
                        if (isRejected) {
                            stepLoadedClass += ' greyed';
                            stepCompletedClass += ' greyed';
                        }

>>>>>>> addf346 (Latest Upload - Events cards, OverView, Items status..)
                        if (isRejected) fillPercent = 40;
                        else if (isCompleted) fillPercent = 100;
                        else if (isLoaded) fillPercent = 75;
                        else if (isTechOnboard) fillPercent = 65;
                        else if (isApproved) fillPercent = 40;
                        else fillPercent = 25;

                        let dName = b.transport_driver || '';
                        if (dName === 'Valetine' || dName === 'valentinb') dName = 'Valentin B.';
                        else if (dName.length > 10) dName = dName.substring(0, 10) + '...';
                        
                        let driverSublabel = isLoaded ? dName + ' (Loaded)' : (isTechOnboard ? 'Tech Onboard' : (isApproved && !isRejected ? 'Awaiting Dispatch' : '-'));

                        const milestoneHtml = `
                        <div class="milestone-tracker">
                            <div class="milestone-tracker-fill" style="width: ${fillPercent}%;"></div>
                            
                            <div class="milestone-step ${stepSubmittedClass}">
                                <div class="milestone-icon"><i class="fas fa-file-import"></i></div>
                                <div class="milestone-label">Submitted</div>
                                <div class="milestone-sublabel">${b.submitted_by ? (b.submitted_by.length > 10 ? b.submitted_by.substring(0,10)+'...' : b.submitted_by) : '-'}</div>
                            </div>
                            
                            <div class="milestone-step ${stepApprovedClass}">
                                <div class="milestone-icon"><i class="fas fa-clipboard-check"></i></div>
<<<<<<< HEAD
                                <div class="milestone-label">Approved</div>
                                <div class="milestone-sublabel">${isRejected ? 'Rejected' : (isApproved ? 'Approved' : 'Awaiting Approval')}</div>
=======
                                <div class="milestone-label">${isRejected ? 'Declined' : 'Approved'}</div>
                                <div class="milestone-sublabel">${isRejected ? 'Declined' : (isApproved ? 'Approved' : 'Awaiting Approval')}</div>
>>>>>>> addf346 (Latest Upload - Events cards, OverView, Items status..)
                            </div>
                            
                            <div class="milestone-step ${stepLoadedClass}">
                                <div class="milestone-icon"><i class="fas fa-truck-loading"></i></div>
                                <div class="milestone-label">Loaded</div>
                                <div class="milestone-sublabel">${driverSublabel}</div>
                            </div>
                            
                            <div class="milestone-step ${stepCompletedClass}">
                                <div class="milestone-icon"><i class="fas fa-flag-checkered"></i></div>
                                <div class="milestone-label">Done</div>
                                <div class="milestone-sublabel">${isCompleted ? 'Delivered' : '-'}</div>
                            </div>
                        </div>
                        <div class="mb-3"></div>
                        `;

                        const html = `
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h5 class="fw-bold mb-0">Batch #${b.batch_id}</h5>
                                <span class="status-pill ${statusClass}">${statusLabel}</span>
                            </div>
                            
                            ${milestoneHtml}
                            
                            <div class="glass-card p-3 mb-3" style="font-size: 0.88rem;">
                                <div class="row g-2">
                                    <div class="col-6"><span class="text-secondary">Type:</span> <strong>${b.movement_type || 'Stock Out'}</strong></div>
                                    <div class="col-6"><span class="text-secondary">Date:</span> <strong>${b.date || 'N/A'}</strong></div>
                                    <div class="col-12"><span class="text-secondary">Route:</span> <strong>${b.source_name || 'Stock'} &rarr; ${b.destination_name || 'Venue'}</strong></div>
                                    <div class="col-12"><span class="text-secondary">Tech Name:</span> <strong>${b.technician_name || 'N/A'}</strong></div>
                                    ${b.project_manager ? `<div class="col-12"><span class="text-secondary">PM Assigned:</span> <strong>${b.project_manager}</strong></div>` : ''}
                                    ${b.job_sheet ? `
                                        <div class="col-12">
                                            <span class="text-secondary">Job Sheet:</span> <strong>${b.job_sheet}</strong>
                                            ${b.jobsheet_file ? `
                                                <div class="mt-1">
                                                    <a href="${b.jobsheet_file}" target="_blank" class="btn btn-xs btn-outline-primary py-0 px-2 fw-bold" style="font-size: 0.7rem; border-radius: 4px;">
                                                        <i class="fas fa-file-download me-1"></i> View Uploaded Jobsheet
                                                    </a>
                                                </div>
                                            ` : ''}
                                        </div>` : ''}
                                    ${b.approval_notes ? `<div class="col-12 text-success"><span class="text-secondary">Approve Note:</span> <em>${b.approval_notes}</em></div>` : ''}
                                    ${b.rejection_reason ? `<div class="col-12 text-danger"><span class="text-secondary">Reject Reason:</span> <em>${b.rejection_reason}</em></div>` : ''}
                                </div>
                            </div>

                            <h6 class="fw-bold mb-2">Equipment List (${(b.items || []).length})</h6>
                            <div style="max-height: 250px; overflow-y: auto;" class="pe-1">
                                ${itemsHtml}
                            </div>
                            
                            ${actionHtml}
                        `;
                        openDrawer(html);
                    } else {
                        openDrawer(`<p class="text-center text-danger py-4">${d.message || 'Failed to fetch details'}</p>`);
                    }
                })
                .catch(() => openDrawer(`<p class="text-center text-danger py-4">Error loading details</p>`));
        }

        // Controller: Submit approval or rejection notes
        function promptApprovalAction(batchId, action) {
            const isApprove = action === 'approve';
            const actionLabel = isApprove ? 'Approve' : 'Reject';
            const buttonClass = isApprove ? 'btn-success' : 'btn-danger';
            const placeholder = isApprove ? 'Enter approval notes (optional)...' : 'Enter rejection reason (required)...';

            const html = `
                <h5 class="fw-bold mb-3">${actionLabel} Batch #${batchId}</h5>
                <p class="small text-secondary mb-3">Please specify notes/reasons before executing this transaction.</p>
                <div class="mb-3">
                    <textarea id="approvalActionNotes" class="glass-input" rows="3" placeholder="${placeholder}"></textarea>
                </div>
                <div class="d-flex gap-2">
                    <button class="btn btn-outline-secondary w-50" onclick="closeDrawer()">Cancel</button>
                    <button class="btn ${buttonClass} w-50" onclick="submitBatchApprovalMobile('${batchId}', '${action}')">${actionLabel}</button>
                </div>
            `;
            openDrawer(html);
        }

        function submitBatchApprovalMobile(batchId, action) {
            const notes = document.getElementById('approvalActionNotes').value.trim();
            if (action === 'reject' && !notes) {
                showToast('Rejection reason is required!', 'error');
                return;
            }

            // AJAX request to verify batch
            fetch('api/batches/approve.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        batch_id: batchId,
                        action: action,
                        notes: notes
                    })
                })
                .then(r => r.json())
                .then(d => {
                    if (d.success) {
                        showToast(d.message || `Batch successfully ${action}d!`, 'success');
                        closeDrawer();
                        fetchDashboardMetrics();
                        fetchBatchesData();
                    } else {
                        showToast(d.message || 'Failed to process request', 'error');
                    }
                })
                .catch(() => showToast('Connection or server error occurred', 'error'));
        }

        // Camera Scanner logic using ZXing
        function startMobileScanner() {
            const preview = document.getElementById('scanner-video-preview');
            const overlay = document.getElementById('scanner-overlay');
            const laser = document.getElementById('scanner-laser');
            const prompt = document.getElementById('scanner-prompt');
            const controls = document.getElementById('scanner-active-controls');
            const selectContainer = document.getElementById('camera-select-container');
            const select = document.getElementById('mobile-camera-select');

            prompt.style.display = 'none';
            controls.style.display = 'block';
            preview.style.display = 'block';
            overlay.style.display = 'block';
            laser.style.display = 'block';

            activeVideoReader = new ZXing.BrowserMultiFormatReader();
            activeVideoReader.listVideoInputDevices()
                .then(devices => {
                    if (devices.length === 0) {
                        showToast('No cameras detected!', 'error');
                        stopMobileScanner();
                        return;
                    }
                    
                    if (select) {
                        select.innerHTML = '';
                        devices.forEach(device => {
                            const option = document.createElement('option');
                            option.value = device.deviceId;
                            option.text = device.label || `Camera ${select.children.length + 1}`;
                            select.appendChild(option);
                        });
                        
                        if (devices.length > 1) {
                            if (selectContainer) selectContainer.style.display = 'block';
                        } else {
                            if (selectContainer) selectContainer.style.display = 'none';
                        }
                        
                        // Default selection: find back/rear camera, otherwise first camera
                        let backCamera = devices.find(d => d.label.toLowerCase().includes('back') || d.label.toLowerCase().includes('rear'));
                        let initialCameraId = backCamera ? backCamera.deviceId : devices[0].deviceId;
                        select.value = initialCameraId;
                        selectedCameraId = initialCameraId;
                    } else {
                        selectedCameraId = devices[0].deviceId;
                    }
                    
                    startDecodingWithCamera(selectedCameraId);
                    showToast('Mobile camera active', 'success');
                })
                .catch(err => {
                    console.error(err);
                    showToast('Camera access denied!', 'error');
                    stopMobileScanner();
                });
        }

        function stopMobileScanner() {
            if (activeVideoReader) {
                activeVideoReader.reset();
                activeVideoReader = null;
            }

            const preview = document.getElementById('scanner-video-preview');
            const overlay = document.getElementById('scanner-overlay');
            const laser = document.getElementById('scanner-laser');
            const prompt = document.getElementById('scanner-prompt');
            const controls = document.getElementById('scanner-active-controls');
            const selectContainer = document.getElementById('camera-select-container');

            if (preview) preview.style.display = 'none';
            if (overlay) overlay.style.display = 'none';
            if (laser) laser.style.display = 'none';
            if (prompt) prompt.style.display = 'block';
            if (controls) controls.style.display = 'none';
            if (selectContainer) selectContainer.style.display = 'none';
        }

        function lookupItemMobile() {
            const val = document.getElementById('manualLookupInput').value.trim();
            if (!val) {
                showToast('Please enter an Equipment ID/Serial number', 'warning');
                return;
            }
            processScannedCode(val);
        }

        function processScannedCode(code) {
            openDrawer(`
                <div class="text-center py-4">
                    <i class="fas fa-spinner fa-spin fa-2x text-accent mb-2"></i>
                    <p class="small text-secondary">Looking up equipment details...</p>
                </div>
            `);

            // Extract item ID from QR code
            let itemId = code;
            if (code.includes('id=')) {
                const match = code.match(/[?&]id=(\d+)/);
                if (match) itemId = match[1];
            } else if (code.includes('item_id=')) {
                const match = code.match(/item_id=(\d+)/);
                if (match) itemId = match[1];
            }

            fetch(`api/get_item.php?id=${itemId}`)
                .then(r => r.json())
                .then(d => {
                    if (d.success && d.data) {
                        const item = d.data;
                        const statusClass = item.status === 'available' ? 'pill-approved' : 'pill-pending';

                        const html = `
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h5 class="fw-bold mb-0">${item.item_name}</h5>
                                <span class="status-pill ${statusClass}">${(item.status || 'unknown').toUpperCase()}</span>
                            </div>
                            
                            <div class="glass-card p-3 mb-4" style="font-size: 0.88rem;">
                                <div class="row g-2">
                                    <div class="col-6"><span class="text-secondary">Serial:</span> <strong>${item.serial_number || 'N/A'}</strong></div>
                                    <div class="col-6"><span class="text-secondary">Category:</span> <strong>${item.category || 'N/A'}</strong></div>
                                    <div class="col-12"><span class="text-secondary">Current Location:</span> <strong>${item.stock_location || 'Storage'}</strong></div>
                                    <div class="col-12"><span class="text-secondary">Quantity:</span> <strong>${item.quantity || 1}</strong></div>
                                </div>
                            </div>

                            <div class="d-grid gap-2">
                                <a href="scan.php" class="btn btn-primary" onclick="closeDrawer()">
                                    <i class="fas fa-clipboard-list me-2"></i>Use Scanning Page (Create Batch)
                                </a>
                                <button class="btn btn-outline-secondary" onclick="closeDrawer()">Close Details</button>
                            </div>
                        `;
                        openDrawer(html);
                    } else {
                        // Try lookup by scan text directly (serial matching)
                        fetch(`api/get_item_by_scan.php?scan_data=${encodeURIComponent(code)}`)
                            .then(r => r.json())
                            .then(data => {
                                if (data.success && data.data) {
                                    const item = data.data;
                                    const statusClass = item.status === 'available' ? 'pill-approved' : 'pill-pending';
                                    const innerHtml = `
                                        <div class="d-flex justify-content-between align-items-center mb-3">
                                            <h5 class="fw-bold mb-0">${item.item_name}</h5>
                                            <span class="status-pill ${statusClass}">${(item.status || 'unknown').toUpperCase()}</span>
                                        </div>
                                        
                                        <div class="glass-card p-3 mb-4" style="font-size: 0.88rem;">
                                            <div class="row g-2">
                                                <div class="col-6"><span class="text-secondary">Serial:</span> <strong>${item.serial_number || 'N/A'}</strong></div>
                                                <div class="col-6"><span class="text-secondary">Category:</span> <strong>${item.category || 'N/A'}</strong></div>
                                                <div class="col-12"><span class="text-secondary">Current Location:</span> <strong>${item.stock_location || 'Storage'}</strong></div>
                                                <div class="col-12"><span class="text-secondary">Quantity:</span> <strong>${item.quantity || 1}</strong></div>
                                            </div>
                                        </div>

                                        <div class="d-grid gap-2">
                                            <a href="scan.php" class="btn btn-primary" onclick="closeDrawer()">
                                                <i class="fas fa-clipboard-list me-2"></i>Use Scanning Page (Create Batch)
                                            </a>
                                            <button class="btn btn-outline-secondary" onclick="closeDrawer()">Close Details</button>
                                        </div>
                                    `;
                                    openDrawer(innerHtml);
                                } else {
                                    openDrawer(`
                                        <div class="text-center py-4 text-danger">
                                            <i class="fas fa-exclamation-triangle fa-2x mb-2"></i>
                                            <p class="small mb-0">Equipment not found (#${code})</p>
                                            <button class="btn btn-sm btn-outline-danger mt-3" onclick="closeDrawer()">OK</button>
                                        </div>
                                    `);
                                }
                            })
                            .catch(() => {
                                openDrawer(`
                                    <div class="text-center py-4 text-danger">
                                        <i class="fas fa-exclamation-triangle fa-2x mb-2"></i>
                                        <p class="small mb-0">Equipment lookup error</p>
                                        <button class="btn btn-sm btn-outline-danger mt-3" onclick="closeDrawer()">OK</button>
                                    </div>
                                `);
                            });
                    }
                })
                .catch(() => showToast('Error during equipment query', 'error'));
        }

        // Advanced client-side filtering and local rendering for mobile
        function filterTechBatchesLocal() {
            const searchInput = document.getElementById('tech-batches-search');
            const statusFilter = document.getElementById('tech-status-filter');
            if (!searchInput || !statusFilter) return;

            const query = searchInput.value.toLowerCase().trim();
            const status = statusFilter.value;
            const list = document.getElementById('tech-batches-history-list');

            let filtered = allTechBatches.filter(b => {
                const matchesSearch = !query ||
                    (b.batch_id || b.batch_number || '').toLowerCase().includes(query) ||
                    (b.event_name || '').toLowerCase().includes(query) ||
                    (b.job_sheet || '').toLowerCase().includes(query) ||
                    (b.destination || b.destination_name || '').toLowerCase().includes(query);

                const matchesStatus = !status || (b.status || '').toLowerCase() === status;
                return matchesSearch && matchesStatus;
            });

            if (filtered.length === 0) {
                list.innerHTML = `<div class="text-center py-4 text-secondary"><p class="small mb-0">No batches match filters</p></div>`;
                return;
            }

            let html = '';
            filtered.forEach(b => {
                html += renderBatchCardMobile(b);
            });
            list.innerHTML = html;
        }

        function filterDriverBatchesLocal() {
            const searchInput = document.getElementById('driver-batches-search');
            const statusFilter = document.getElementById('driver-status-filter');
            if (!searchInput || !statusFilter) return;

            const query = searchInput.value.toLowerCase().trim();
            const status = statusFilter.value;
            const list = document.getElementById('driver-completed-batches-list');

            let filtered = allDriverBatches.filter(b => {
                const matchesSearch = !query ||
                    (b.batch_id || b.batch_number || '').toLowerCase().includes(query) ||
                    (b.event_name || '').toLowerCase().includes(query) ||
                    (b.job_sheet || '').toLowerCase().includes(query) ||
                    (b.destination || b.destination_name || '').toLowerCase().includes(query);

                const matchesStatus = !status || (b.status || '').toLowerCase() === status;
                return matchesSearch && matchesStatus;
            });

            if (filtered.length === 0) {
                list.innerHTML = `<div class="text-center py-4 text-secondary"><p class="small mb-0">No deliveries match filters</p></div>`;
                return;
            }

            let html = '';
            filtered.forEach(b => {
                html += renderBatchCardMobile(b);
            });
            list.innerHTML = html;
        }

        function filterCtrlBatchesLocal() {
            const searchInput = document.getElementById('ctrl-batches-search');
            const statusFilter = document.getElementById('ctrl-status-filter');
            if (!searchInput || !statusFilter) return;

            const query = searchInput.value.toLowerCase().trim();
            const status = statusFilter.value;
            const list = document.getElementById('ctrl-completed-batches-list');

            let filtered = allCtrlBatches.filter(b => {
                const matchesSearch = !query ||
                    (b.batch_id || b.batch_number || '').toLowerCase().includes(query) ||
                    (b.event_name || '').toLowerCase().includes(query) ||
                    (b.job_sheet || '').toLowerCase().includes(query) ||
                    (b.destination || b.destination_name || '').toLowerCase().includes(query) ||
                    (b.technician || '').toLowerCase().includes(query);

                const matchesStatus = !status || (b.status || '').toLowerCase() === status;
                return matchesSearch && matchesStatus;
            });

            if (filtered.length === 0) {
                list.innerHTML = `<div class="text-center py-4 text-secondary"><p class="small mb-0">No batches match filters</p></div>`;
                return;
            }

            let html = '';
            filtered.forEach(b => {
                if (b.status === 'pending') {
                    html += renderPendingBatchCardMobile(b);
                } else {
                    html += renderBatchCardMobile(b);
                }
            });
            list.innerHTML = html;
        }
    </script>
</body>

</html>