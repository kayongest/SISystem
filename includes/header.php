<?php
// Check if user is logged in (redundant but safe)
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . 'login.php');
    exit();
}

// Get current page for active state
$current_page = basename($_SERVER['PHP_SELF']);
?>





<nav class="navbar navbar-expand-lg navbar-dark" style="background: linear-gradient(135deg, #234c6a 0%, #2c5a7a 100%);">
    <div class="container-fluid px-4">
        <a class="navbar-brand d-flex align-items-center" href="<?php echo BASE_URL; ?>dashboard_full.php">
            <i class="fas fa-cubes me-2 fa-lg"></i>
            <span class="fw-bold">aBility</span>
            <span class="badge bg-light text-primary ms-2 px-3 py-1 rounded-pill" style="font-size: 0.7rem;">Inventory Manager</span>
        </a>

        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav"
            aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navbarNav">
            <!-- Main Navigation - Left Side -->
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <!-- Dashboard - Everyone -->
                <li class="nav-item">
                    <a class="nav-link d-flex align-items-center <?php echo $current_page == 'dashboard_full.php' ? 'active' : ''; ?>"
                        href="<?php echo BASE_URL; ?>dashboard_full.php">
                        <i class="fas fa-tachometer-alt me-1"></i>Dashboard
                    </a>
                </li>

                <!-- Events - Check permission -->
                <?php if (hasPermission('view_events')): ?>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $current_page == 'events.php' ? 'active' : ''; ?>"
                            href="<?php echo BASE_URL; ?>events.php">
                            <i class="fas fa-calendar-alt me-1"></i>Events
                        </a>
                    </li>
                <?php endif; ?>

                <!-- Equipment - Check permission -->
                <?php if (hasPermission('view_equipment')): ?>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $current_page == 'items.php' ? 'active' : ''; ?>"
                            href="<?php echo BASE_URL; ?>items.php">
                            <i class="fas fa-boxes me-1"></i>Equipment
                        </a>
                    </li>
                <?php endif; ?>

                <!-- Import - Check permission -->
                <?php if (hasPermission('import_export')): ?>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $current_page == 'import_items.php' ? 'active' : ''; ?>"
                            href="<?php echo BASE_URL; ?>import_items.php">
                            <i class="fas fa-file-import me-1"></i>Import
                        </a>
                    </li>
                <?php endif; ?>

                <!-- Single Scan - Check permission -->
                <?php if (hasPermission('scan_single')): ?>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $current_page == 'scan.php' ? 'active' : ''; ?>"
                            href="<?php echo BASE_URL; ?>scan.php">
                            <i class="fas fa-qrcode me-1"></i>Scan
                        </a>
                    </li>
                <?php endif; ?>

                <!-- Bulk Scan - Check permission -->
                <?php if (hasPermission('scan_bulk')): ?>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $current_page == 'scan_2.php' ? 'active' : ''; ?>"
                            href="<?php echo BASE_URL; ?>scan_2.php">
                            <i class="fas fa-expand me-1"></i>Bulk Scan
                        </a>
                    </li>
                <?php endif; ?>

                <!-- Scan History - Check permission -->
                <?php if (hasPermission('view_scan_history')): ?>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $current_page == 'scan_logs.php' ? 'active' : ''; ?>"
                            href="<?php echo BASE_URL; ?>scan_logs.php">
                            <i class="fas fa-history me-1"></i>Scan History
                        </a>
                    </li>
                <?php endif; ?>

                <!-- Reports - Check permission -->
                <?php if (hasPermission('view_reports')): ?>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $current_page == 'reports.php' ? 'active' : ''; ?>"
                            href="<?php echo BASE_URL; ?>reports.php">
                            <i class="fas fa-chart-bar me-1"></i>Reports
                        </a>
                    </li>
                <?php endif; ?>

                <!-- Technicians - Check permission -->
                <?php if (hasPermission('manage_technicians')): ?>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $current_page == 'technicians.php' ? 'active' : ''; ?>"
                            href="<?php echo BASE_URL; ?>technicians.php">
                            <i class="fas fa-tools me-1"></i>Technicians
                        </a>
                    </li>
                <?php endif; ?>

                <!-- Stock Locations - Check permission -->
                <?php if (hasPermission('manage_stock_locations')): ?>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $current_page == 'stock_locations.php' ? 'active' : ''; ?>"
                            href="<?php echo BASE_URL; ?>stock_locations.php">
                            <i class="fas fa-warehouse me-1"></i>Stock Locations
                        </a>
                    </li>
                <?php endif; ?>

                <!-- Batch History - Check permission -->
                <?php if (hasPermission('view_batch_history')): ?>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $current_page == 'batch_history.php' ? 'active' : ''; ?>"
                            href="<?php echo BASE_URL; ?>batch_history.php">
                            <i class="fas fa-history me-1"></i>Batch History
                        </a>
                    </li>
                <?php endif; ?>

                <!-- Accessories - Check permission -->
                <?php if (hasPermission('view_accessories')): ?>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $current_page == 'accessories.php' ? 'active' : ''; ?>"
                            href="<?php echo BASE_URL; ?>accessories.php">
                            <i class="fas fa-puzzle-piece me-1"></i>Accessories
                        </a>
                    </li>
                <?php endif; ?>

                <!-- User Management - Admin only -->
                <?php if (isAdmin()): ?>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $current_page == 'users.php' ? 'active' : ''; ?>"
                            href="<?php echo BASE_URL; ?>users.php">
                            <i class="fas fa-users me-1"></i>User Management
                        </a>
                    </li>
                <?php endif; ?>

                <!-- Settings - Admin only -->
                <?php if (isAdmin()): ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="settingsDropdown" role="button"
                            data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-cog me-1"></i>Settings
                        </a>
                        <ul class="dropdown-menu dropdown-menu-dark" aria-labelledby="settingsDropdown"
                            style="background: #2c5a7a;">
                            <li><a class="dropdown-item <?php echo $current_page == 'categories.php' ? 'active' : ''; ?>"
                                    href="<?php echo BASE_URL; ?>categories.php">
                                    <i class="fas fa-tags me-2"></i>Categories
                                </a></li>
                            <li><a class="dropdown-item <?php echo $current_page == 'departments.php' ? 'active' : ''; ?>"
                                    href="<?php echo BASE_URL; ?>departments.php">
                                    <i class="fas fa-building me-2"></i>Departments
                                </a></li>
                            <li><a class="dropdown-item <?php echo $current_page == 'locations.php' ? 'active' : ''; ?>"
                                    href="<?php echo BASE_URL; ?>locations.php">
                                    <i class="fas fa-map-marker-alt me-2"></i>Locations
                                </a></li>
                            <li>
                                <hr class="dropdown-divider">
                            </li>
                            <li><a class="dropdown-item <?php echo $current_page == 'role_access_matrix.php' ? 'active' : ''; ?>"
                                    href="<?php echo BASE_URL; ?>role_access_matrix.php">
                                    <i class="fas fa-shield-alt me-2"></i>Permissions
                                </a></li>
                        </ul>
                    </li>
                <?php endif; ?>
            </ul>

            <!-- Right Side - User Menu (Always visible for all users) -->
            <ul class="navbar-nav ms-auto">
                <!-- User Dropdown - ALWAYS VISIBLE -->
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" id="userDropdown"
                        role="button" data-bs-toggle="dropdown" aria-expanded="false" style="cursor: pointer;">
                        <div class="rounded-circle bg-white d-flex align-items-center justify-content-center me-2"
                            style="width: 35px; height: 35px; color: #234c6a;">
                            <i class="fas fa-user"></i>
                        </div>
                        <span class="d-none d-md-inline"><?php echo htmlspecialchars($_SESSION['username'] ?? 'User'); ?></span>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end shadow" aria-labelledby="userDropdown" style="min-width: 250px;">
                        <li>
                            <div class="dropdown-header text-center">
                                <div class="rounded-circle bg-primary d-flex align-items-center justify-content-center mx-auto mb-2"
                                    style="width: 50px; height: 50px; color: white; background: #234c6a !important;">
                                    <i class="fas fa-user fa-lg"></i>
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
                        <li>
                            <a class="dropdown-item <?php echo $current_page == 'profile.php' ? 'active' : ''; ?>"
                                href="<?php echo BASE_URL; ?>profile.php">
                                <i class="fas fa-user-circle me-2"></i>My Profile
                            </a>
                        </li>
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
                        <li>
                            <a class="dropdown-item text-danger" href="<?php echo BASE_URL; ?>logout.php" onclick="return confirm('Are you sure you want to logout?')">
                                <i class="fas fa-sign-out-alt me-2"></i>Logout
                            </a>
                        </li>
                    </ul>
                </li>
            </ul>
        </div>
    </div>
</nav>