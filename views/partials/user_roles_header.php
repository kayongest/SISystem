<?php
// user_roles_header.php - Dynamic header for all users
// Include this file in your pages instead of the old header

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . 'login.php');
    exit();
}

// Get current page for active state
$current_page = basename($_SERVER['PHP_SELF']);
$current_uri = $_SERVER['REQUEST_URI'];

// Define page groups for dropdown active states
$settings_pages = ['categories.php', 'departments.php', 'locations.php', 'role_access_matrix.php'];
$scan_pages = ['scan.php', 'scan_2.php', 'scan_logs.php'];
$equipment_pages = ['items.php', 'items_list.php', 'items_view.php', 'items_edit.php'];
$reports_pages = ['reports.php', 'report_generator.php', 'report_history.php'];
?>

<nav class="navbar navbar-expand-lg navbar-dark" style="background: linear-gradient(135deg, #234c6a 0%, #2c5a7a 100%); box-shadow: 0 4px 20px rgba(0,0,0,0.1);">
    <div class="container-fluid px-4">
        <!-- Brand -->
        <a class="navbar-brand d-flex align-items-center" href="<?php echo BASE_URL; ?>dashboard_full.php">
            <i class="fas fa-cubes me-2 fa-lg"></i>
            <span class="fw-bold">aBility</span>
            <span class="badge bg-light text-primary ms-2 px-3 py-1 rounded-pill" style="font-size: 0.7rem;">
                <?php echo getRoleDisplayName(getUserRole()); ?>
            </span>
        </a>

        <!-- Toggler -->
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav"
            aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <!-- Navigation Items -->
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">

                <!-- DASHBOARD - Everyone -->
                <li class="nav-item">
                    <a class="nav-link <?php echo $current_page == 'dashboard_full.php' ? 'active' : ''; ?>"
                        href="<?php echo BASE_URL; ?>dashboard_full.php">
                        <i class="fas fa-tachometer-alt me-1"></i>Dashboard
                    </a>
                </li>

                <!-- EVENTS -->
                <?php if (hasPermission('view_events')): ?>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $current_page == 'events.php' ? 'active' : ''; ?>"
                            href="<?php echo BASE_URL; ?>events.php">
                            <i class="fas fa-calendar-alt me-1"></i>Events
                        </a>
                    </li>
                <?php endif; ?>

                <!-- EQUIPMENT -->
                <?php if (hasPermission('view_equipment')): ?>
                    <li class="nav-item">
                        <a class="nav-link <?php echo in_array($current_page, $equipment_pages) ? 'active' : ''; ?>"
                            href="<?php echo BASE_URL; ?>items.php">
                            <i class="fas fa-boxes me-1"></i>Equipment
                        </a>
                    </li>
                <?php endif; ?>

                <!-- IMPORT -->
                <?php if (hasPermission('import_export')): ?>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $current_page == 'import_items.php' ? 'active' : ''; ?>"
                            href="<?php echo BASE_URL; ?>import_items.php">
                            <i class="fas fa-file-import me-1"></i>Import
                        </a>
                    </li>
                <?php endif; ?>

                <!-- SCAN DROPDOWN (combines all scan features) -->
                <?php if (hasPermission('scan_single') || hasPermission('scan_bulk') || hasPermission('view_scan_history')): ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle <?php echo in_array($current_page, $scan_pages) ? 'active' : ''; ?>"
                            href="#" id="scanDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-qrcode me-1"></i>Scan
                        </a>
                        <ul class="dropdown-menu dropdown-menu-dark" aria-labelledby="scanDropdown" style="background: #2c5a7a;">
                            <?php if (hasPermission('scan_single')): ?>
                                <li>
                                    <a class="dropdown-item <?php echo $current_page == 'scan.php' ? 'active' : ''; ?>"
                                        href="<?php echo BASE_URL; ?>scan.php">
                                        <i class="fas fa-qrcode me-2"></i>Single Scan
                                    </a>
                                </li>
                            <?php endif; ?>

                            <?php if (hasPermission('scan_bulk')): ?>
                                <li>
                                    <a class="dropdown-item <?php echo $current_page == 'scan_2.php' ? 'active' : ''; ?>"
                                        href="<?php echo BASE_URL; ?>scan_2.php">
                                        <i class="fas fa-expand me-2"></i>Bulk Scan
                                    </a>
                                </li>
                            <?php endif; ?>

                            <?php if (hasPermission('view_scan_history')): ?>
                                <li>
                                    <a class="dropdown-item <?php echo $current_page == 'scan_logs.php' ? 'active' : ''; ?>"
                                        href="<?php echo BASE_URL; ?>scan_logs.php">
                                        <i class="fas fa-history me-2"></i>Scan History
                                    </a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </li>
                <?php endif; ?>

                <!-- REPORTS -->
                <?php if (hasPermission('view_reports')): ?>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $current_page == 'reports.php' ? 'active' : ''; ?>"
                            href="<?php echo BASE_URL; ?>reports.php">
                            <i class="fas fa-chart-bar me-1"></i>Reports
                        </a>
                    </li>
                <?php endif; ?>

                <!-- TECHNICIANS -->
                <?php if (hasPermission('manage_technicians')): ?>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $current_page == 'technicians.php' ? 'active' : ''; ?>"
                            href="<?php echo BASE_URL; ?>technicians.php">
                            <i class="fas fa-tools me-1"></i>Technicians
                        </a>
                    </li>
                <?php endif; ?>

                <!-- STOCK LOCATIONS -->
                <?php if (hasPermission('manage_stock_locations')): ?>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $current_page == 'stock_locations.php' ? 'active' : ''; ?>"
                            href="<?php echo BASE_URL; ?>stock_locations.php">
                            <i class="fas fa-warehouse me-1"></i>Stock Locations
                        </a>
                    </li>
                <?php endif; ?>

                <!-- BATCH HISTORY -->
                <?php if (hasPermission('view_batch_history')): ?>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $current_page == 'batch_history.php' ? 'active' : ''; ?>"
                            href="<?php echo BASE_URL; ?>batch_history.php">
                            <i class="fas fa-history me-1"></i>Batch History
                        </a>
                    </li>
                <?php endif; ?>

                <!-- ACCESSORIES -->
                <?php if (hasPermission('view_accessories')): ?>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $current_page == 'accessories.php' ? 'active' : ''; ?>"
                            href="<?php echo BASE_URL; ?>accessories.php">
                            <i class="fas fa-puzzle-piece me-1"></i>Accessories
                        </a>
                    </li>
                <?php endif; ?>

                <!-- USER MANAGEMENT - Admin only -->
                <?php if (isAdmin()): ?>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $current_page == 'users.php' ? 'active' : ''; ?>"
                            href="<?php echo BASE_URL; ?>users.php">
                            <i class="fas fa-users me-1"></i>User Management
                        </a>
                    </li>
                <?php endif; ?>

                <!-- SETTINGS DROPDOWN - Admin only -->
                <?php if (isAdmin()): ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle <?php echo in_array($current_page, $settings_pages) ? 'active' : ''; ?>"
                            href="#" id="settingsDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-cog me-1"></i>Settings
                        </a>
                        <ul class="dropdown-menu dropdown-menu-dark" aria-labelledby="settingsDropdown" style="background: #2c5a7a;">
                            <li>
                                <a class="dropdown-item <?php echo $current_page == 'categories.php' ? 'active' : ''; ?>"
                                    href="<?php echo BASE_URL; ?>categories.php">
                                    <i class="fas fa-tags me-2"></i>Categories
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item <?php echo $current_page == 'departments.php' ? 'active' : ''; ?>"
                                    href="<?php echo BASE_URL; ?>departments.php">
                                    <i class="fas fa-building me-2"></i>Departments
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item <?php echo $current_page == 'locations.php' ? 'active' : ''; ?>"
                                    href="<?php echo BASE_URL; ?>locations.php">
                                    <i class="fas fa-map-marker-alt me-2"></i>Locations
                                </a>
                            </li>
                            <li>
                                <hr class="dropdown-divider">
                            </li>
                            <li>
                                <a class="dropdown-item <?php echo $current_page == 'role_access_matrix.php' ? 'active' : ''; ?>"
                                    href="<?php echo BASE_URL; ?>role_access_matrix.php">
                                    <i class="fas fa-shield-alt me-2"></i>Permissions
                                </a>
                            </li>
                        </ul>
                    </li>
                <?php endif; ?>
            </ul>

            <!-- Right Side - User Menu (Always visible) -->
            <ul class="navbar-nav ms-auto">
                <!-- User Dropdown -->
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" id="userDropdown"
                        role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <div class="rounded-circle bg-white d-flex align-items-center justify-content-center me-2"
                            style="width: 35px; height: 35px; color: #234c6a;">
                            <i class="fas fa-user"></i>
                        </div>
                        <span class="d-none d-md-inline"><?php echo htmlspecialchars($_SESSION['username'] ?? 'User'); ?></span>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end shadow" aria-labelledby="userDropdown" style="min-width: 250px;">
                        <!-- User Info Header -->
                        <li>
                            <div class="dropdown-header text-center">
                                <div class="rounded-circle bg-primary d-flex align-items-center justify-content-center mx-auto mb-2"
                                    style="width: 50px; height: 50px; background: #234c6a !important;">
                                    <i class="fas fa-user fa-lg text-white"></i>
                                </div>
                                <strong><?php echo htmlspecialchars($_SESSION['username'] ?? 'User'); ?></strong>
                                <br>
                                <span class="badge mt-1" style="background: #234c6a; color: white;">
                                    <?php echo getRoleDisplayName(getUserRole()); ?>
                                </span>
                                <br>
                                <small class="text-muted"><?php echo htmlspecialchars($_SESSION['email'] ?? ''); ?></small>
                            </div>
                        </li>
                        <li>
                            <hr class="dropdown-divider">
                        </li>

                        <!-- Profile Link (Everyone) -->
                        <li>
                            <a class="dropdown-item <?php echo $current_page == 'profile.php' ? 'active' : ''; ?>"
                                href="<?php echo BASE_URL; ?>profile.php">
                                <i class="fas fa-user-circle me-2"></i>My Profile
                            </a>
                        </li>

                        <!-- Account Settings (Admin only) -->
                        <?php if (isAdmin()): ?>
                            <li>
                                <a class="dropdown-item <?php echo $current_page == 'settings.php' ? 'active' : ''; ?>"
                                    href="<?php echo BASE_URL; ?>settings.php">
                                    <i class="fas fa-cog me-2"></i>Account Settings
                                </a>
                            </li>
                        <?php endif; ?>

                        <li>
                            <hr class="dropdown-divider">
                        </li>

                        <!-- Logout (Everyone) -->
                        <li>
                            <a class="dropdown-item text-danger" href="<?php echo BASE_URL; ?>logout.php"
                                onclick="return confirm('Are you sure you want to logout?')">
                                <i class="fas fa-sign-out-alt me-2"></i>Logout
                            </a>
                        </li>
                    </ul>
                </li>
            </ul>
        </div>
    </div>
</nav>

<!-- Add this CSS to ensure dropdowns work -->
<style>
    /* Navbar customization */
    .navbar {
        padding: 0.5rem 0;
    }

    .navbar-brand {
        font-size: 1.4rem;
        letter-spacing: 0.5px;
    }

    .navbar-nav .nav-link {
        padding: 0.5rem 1rem;
        font-size: 0.95rem;
        transition: all 0.3s ease;
        border-radius: 6px;
        margin: 0 2px;
    }

    .navbar-nav .nav-link:hover {
        background: rgba(255, 255, 255, 0.1);
        transform: translateY(-1px);
    }

    .navbar-nav .nav-link.active {
        background: rgba(255, 255, 255, 0.2);
        font-weight: 500;
    }

    /* Dropdown customization */
    .dropdown-menu {
        border: none;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
        border-radius: 10px;
        margin-top: 10px;
        padding: 0.5rem 0;
    }

    .dropdown-menu-dark {
        background: #2c5a7a;
    }

    .dropdown-item {
        padding: 0.6rem 1.2rem;
        font-size: 0.9rem;
        transition: all 0.3s ease;
    }

    .dropdown-item:hover {
        background: rgba(255, 255, 255, 0.1);
        padding-left: 1.5rem;
    }

    .dropdown-item.active {
        background: #234c6a;
        color: white;
    }

    .dropdown-item.text-danger:hover {
        background: rgba(220, 53, 69, 0.1);
        color: #dc3545 !important;
    }

    .dropdown-header {
        padding: 1rem 1.2rem;
    }

    /* Responsive adjustments */
    @media (max-width: 991px) {
        .navbar-nav .nav-link {
            padding: 0.5rem 1rem;
            margin: 2px 0;
        }

        .dropdown-menu {
            background: white;
            margin-top: 0;
        }

        .dropdown-item {
            color: #333;
        }
    }
</style>

<!-- Ensure dropdowns work -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize all dropdowns
        var dropdownElementList = [].slice.call(document.querySelectorAll('.dropdown-toggle'))
        dropdownElementList.forEach(function(dropdownToggleEl) {
            new bootstrap.Dropdown(dropdownToggleEl);
        });
    });
</script>