<?php
// sidebar.php
$current_page = basename($_SERVER['PHP_SELF']);
?>
<nav id="sidebar" class="sidebar">
    <div class="sidebar-header">
        <a class="sidebar-brand d-flex align-items-center" href="<?php echo BASE_URL; ?>">
            <i class="fas fa-sync fa-lg me-2"></i>
            <span class="fw-bold fs-5">aBility</span>
        </a>
        <button type="button" id="sidebarCollapse" class="btn btn-sm btn-outline-light d-none d-lg-block">
            <i class="fas fa-chevron-left"></i>
        </button>
    </div>

    <div class="sidebar-content">
        <ul class="list-unstyled components">
            <li class="<?php echo ($current_page == 'index.php' || $current_page == 'dashboard_full.php') ? 'active' : ''; ?>">
                <a href="<?php echo BASE_URL; ?>index.php">
                    <i class="fas fa-tachometer-alt me-2"></i>
                    Dashboard
                </a>
            </li>

            <li class="<?php echo (strpos($current_page, 'items') !== false) ? 'active' : ''; ?>">
                <a href="<?php echo BASE_URL; ?>views/items/index.php">
                    <i class="fas fa-boxes me-2"></i>
                    Equipment
                </a>
            </li>

            <li class="<?php echo ($current_page == 'scan_logs.php') ? 'active' : ''; ?>">
                <a href="<?php echo BASE_URL; ?>scan_logs.php">
                    <i class="fas fa-history me-2"></i>
                    Scan Logs
                </a>
            </li>

            <li class="dropdown <?php echo (in_array($current_page, ['categories.php', 'departments.php', 'locations.php'])) ? 'active' : ''; ?>">
                <a href="#settingsSubmenu" data-bs-toggle="collapse" class="dropdown-toggle">
                    <i class="fas fa-cog me-2"></i>
                    Settings
                </a>
                <ul class="collapse list-unstyled" id="settingsSubmenu">
                    <li>
                        <a href="<?php echo BASE_URL; ?>categories.php" class="<?php echo ($current_page == 'categories.php') ? 'active' : ''; ?>">
                            <i class="fas fa-list me-2"></i> Categories
                        </a>
                    </li>
                    <li>
                        <a href="<?php echo BASE_URL; ?>departments.php" class="<?php echo ($current_page == 'departments.php') ? 'active' : ''; ?>">
                            <i class="fas fa-building me-2"></i> Departments
                        </a>
                    </li>
                    <li>
                        <a href="<?php echo BASE_URL; ?>locations.php" class="<?php echo ($current_page == 'locations.php') ? 'active' : ''; ?>">
                            <i class="fas fa-map-marker-alt me-2"></i> Locations
                        </a>
                    </li>
                </ul>
            </li>

            <li class="<?php echo ($current_page == 'scan.php') ? 'active' : ''; ?>">
                <a href="<?php echo BASE_URL; ?>scan.php">
                    <i class="fas fa-qrcode me-2"></i>
                    QR Scanner
                </a>
            </li>

            <li class="<?php echo ($current_page == 'reports.php') ? 'active' : ''; ?>">
                <a href="<?php echo BASE_URL; ?>reports.php">
                    <i class="fas fa-chart-bar me-2"></i>
                    Reports
                </a>
            </li>

            <?php if (isset($_SESSION['role']) && in_array($_SESSION['role'], ['admin', 'manager'])): ?>
                <li class="dropdown <?php echo (in_array($current_page, ['users.php', 'technicians.php'])) ? 'active' : ''; ?>">
                    <a href="#userManagementSubmenu" data-bs-toggle="collapse" class="dropdown-toggle">
                        <i class="fas fa-users-cog me-2"></i>
                        User Management
                    </a>
                    <ul class="collapse list-unstyled" id="userManagementSubmenu">
                        <li>
                            <a href="<?php echo BASE_URL; ?>users.php" class="<?php echo ($current_page == 'users.php') ? 'active' : ''; ?>">
                                <i class="fas fa-users me-2"></i> Users
                            </a>
                        </li>
                        <li>
                            <a href="<?php echo BASE_URL; ?>technicians.php" class="<?php echo ($current_page == 'technicians.php') ? 'active' : ''; ?>">
                                <i class="fas fa-user-hard-hat me-2"></i> Technicians
                            </a>
                        </li>
                    </ul>
                </li>
            <?php endif; ?>
        </ul>
    </div>

    <div class="sidebar-footer">
        <?php if (isset($_SESSION['user_id'])): ?>
            <div class="dropdown">
                <a href="#" class="d-flex align-items-center text-decoration-none dropdown-toggle"
                    data-bs-toggle="dropdown" aria-expanded="false">
                    <div class="sidebar-user-avatar">
                        <i class="fas fa-user-circle fa-2x"></i>
                    </div>
                    <div class="sidebar-user-info ms-2">
                        <div class="fw-bold"><?php echo htmlspecialchars($_SESSION['username'] ?? 'User'); ?></div>
                        <small class="text-muted">Administrator</small>
                    </div>
                </a>
                <ul class="dropdown-menu dropdown-menu-end shadow">
                    <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>profile.php">
                            <i class="fas fa-user me-2"></i> Profile
                        </a></li>
                    <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>settings.php">
                            <i class="fas fa-cog me-2"></i> Account Settings
                        </a></li>
                    <li>
                        <hr class="dropdown-divider">
                    </li>
                    <li><a class="dropdown-item text-danger" href="<?php echo BASE_URL; ?>logout.php">
                            <i class="fas fa-sign-out-alt me-2"></i> Logout
                        </a></li>
                </ul>
            </div>
        <?php else: ?>
            <a href="<?php echo BASE_URL; ?>login.php" class="btn btn-outline-light w-100">
                <i class="fas fa-sign-in-alt me-2"></i> Login
            </a>
        <?php endif; ?>
    </div>
</nav>