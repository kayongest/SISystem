<?php
// includes/navbar_main.php - Unified Compact Navigation Bar (Header 2 Style with Header 1 Links)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$current_page = basename($_SERVER['PHP_SELF']);
$user_role = getUserRole();
$user_name = $_SESSION['full_name'] ?? ($_SESSION['username'] ?? 'User');
?>

<style>
    /* Compact Header Style (Header 2 Aesthetics) */
    .page-header-unified {
        background: linear-gradient(135deg, #1a2e3f 0%, #234c6a 100%);
        padding: 0.75rem 2rem;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        color: white;
        position: sticky;
        top: 0;
    }

    .user-info-compact {
        display: flex;
        align-items: center;
        gap: 1rem;
    }

    .user-avatar-compact {
        width: 42px;
        height: 42px;
        background: linear-gradient(135deg, #20B2AA 0%, #1A8F89 100%);
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 1.2rem;
        box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2);
    }

    .user-details-compact h5 {
        color: white;
        margin: 0;
        font-size: 1rem;
        font-weight: 700;
        line-height: 1.2;
    }

    .role-badge-compact {
        font-size: 0.7rem;
        color: rgba(255, 255, 255, 0.7);
        text-transform: uppercase;
        letter-spacing: 1px;
        display: block;
        margin-top: 2px;
    }

    .nav-links-unified {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        flex-wrap: wrap;
    }

    .nav-link-compact {
        color: rgba(255, 255, 255, 0.85);
        text-decoration: none;
        font-size: 0.85rem;
        font-weight: 500;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        transition: all 0.3s ease;
        padding: 6px 12px;
        border-radius: 8px;
        background: rgba(255, 255, 255, 0.05);
        border: 1px solid rgba(255, 255, 255, 0.1);
    }

    .nav-link-compact:hover,
    .nav-link-compact.active {
        background: rgba(255, 255, 255, 0.15);
        color: white;
        transform: translateY(-1px);
        border-color: rgba(255, 255, 255, 0.3);
    }

    .nav-link-compact.active {
        background: rgba(32, 178, 170, 0.25);
        border-color: #20B2AA;
        color: #fff;
    }

    .nav-link-compact i {
        font-size: 0.9rem;
        opacity: 0.9;
    }

    /* Dropdown customization */
    .dropdown-compact .dropdown-menu {
        background: #1a2e3f;
        border: 1px solid rgba(255, 255, 255, 0.1);
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
        padding: 0.5rem;
        border-radius: 10px;
    }

    .dropdown-compact .dropdown-item {
        color: rgba(255, 255, 255, 0.8);
        border-radius: 6px;
        padding: 8px 15px;
        font-size: 0.85rem;
        transition: all 0.2s;
    }

    .dropdown-compact .dropdown-item:hover {
        background: rgba(255, 255, 255, 0.1);
        color: white;
        padding-left: 18px;
    }

    .dropdown-compact .dropdown-divider {
        border-top: 1px solid rgba(255, 255, 255, 0.1);
    }

    @media (max-width: 992px) {
        .page-header-unified .d-flex {
            flex-direction: column;
            gap: 1rem;
            align-items: flex-start !important;
        }

        .nav-links-unified {
            width: 100%;
        }
    }
</style>

<div class="page-header-unified no-print">
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center">
            <!-- Left: Brand + User Profile (Combined Header 1 & 2) -->
            <div class="d-flex align-items-center gap-4">
                <div class="user-info-compact">
                    <div class="user-avatar-compact">
                        <?php
                        $icon = 'fa-user';
                        if (isAdmin()) $icon = 'fa-user-shield';
                        elseif ($user_role === 'technician') $icon = 'fa-tools';
                        elseif ($user_role === 'stock_controller') $icon = 'fa-check-circle';
                        ?>
                        <i class="fas <?php echo $icon; ?>"></i>
                    </div>
                    <div class="user-details-compact">
                        <h5><?php echo htmlspecialchars($user_name); ?></h5>
                        <span class="role-badge-compact">
                            <?php echo ucfirst(str_replace('_', ' ', $user_role)); ?>
                        </span>
                    </div>
                </div>
                <div class="vr" style="height: 50px; opacity: 0.2; background-color: white;"></div>
            </div>

            <!-- Right: Nav Links (Header 1 Items in Header 2 Style) -->
            <div class="nav-links-unified">
                <!-- Dashboard -->
                <?php if (isAdmin() || in_array($user_role, ['stock_manager', 'tech_lead'])): ?>
                    <a href="<?php echo BASE_URL; ?>dashboard_full.php" class="nav-link-compact <?php echo ($current_page == 'dashboard_full.php' || $current_page == 'dashboard_full.php') ? 'active' : ''; ?>">
                        <i class="fas fa-tachometer-alt"></i> Dashboard
                    </a>
                <?php endif; ?>

                <!-- Events -->
                <?php if (isAdmin()): ?>
                    <a href="<?php echo BASE_URL; ?>events.php" class="nav-link-compact <?php echo ($current_page == 'events.php') ? 'active' : ''; ?>">
                        <i class="fas fa-list"></i> Events
                    </a>
                <?php endif; ?>

                <!-- Equipment -->
                <?php if (isAdmin()): ?>
                    <a href="<?php echo BASE_URL; ?>items.php" class="nav-link-compact <?php echo (strpos($current_page, 'items') !== false) ? 'active' : ''; ?>">
                        <i class="fas fa-boxes"></i> Equipment
                    </a>
                <?php endif; ?>

                <!-- Accessories -->
                <?php if (isAdmin()): ?>
                    <a href="<?php echo BASE_URL; ?>accessories.php" class="nav-link-compact <?php echo ($current_page == 'accessories.php') ? 'active' : ''; ?>">
                        <i class="fas fa-plug"></i> Accessories
                    </a>
                <?php endif; ?>

                <!-- Import -->
                <?php if (isAdmin()): ?>
                    <a href="<?php echo BASE_URL; ?>import_items.php" class="nav-link-compact <?php echo ($current_page == 'import_items.php') ? 'active' : ''; ?>">
                        <i class="fas fa-upload"></i> Import
                    </a>
                <?php endif; ?>



                <?php if (isAdmin()): ?>
                    <a href="<?php echo BASE_URL; ?>scan_bulk.php" class="nav-link-compact <?php echo ($current_page == 'scan_bulk.php') ? 'active' : ''; ?>">
                        <i class="fas fa-qrcode"></i> Scanner
                    </a>
                <?php endif; ?>

                <!-- History Links based on role -->
                <?php if ($user_role === 'technician'): ?>
                    <a href="<?php echo BASE_URL; ?>technician_batch_history.php" class="nav-link-compact <?php echo ($current_page == 'technician_batch_history.php') ? 'active' : ''; ?>">
                        <i class="fas fa-history"></i> My Batches
                    </a>
                <?php endif; ?>

                <?php if (isAdmin() || $user_role === 'stock_controller'): ?>
                    <a href="<?php echo BASE_URL; ?>batch_history.php" class="nav-link-compact <?php echo ($current_page == 'batch_history.php') ? 'active' : ''; ?>">
                        <i class="fas fa-check-double"></i> Batch Approvals
                    </a>
                <?php endif; ?>

                <!-- Reports (Admin Only) -->
                <?php if (isAdmin() || $user_role === 'manager'): ?>
                    <a href="<?php echo BASE_URL; ?>reports.php" class="nav-link-compact <?php echo ($current_page == 'reports.php') ? 'active' : ''; ?>">
                        <i class="fas fa-chart-bar"></i> Reports
                    </a>
                    <a href="<?php echo BASE_URL; ?>hr_center.php" class="nav-link-compact <?php echo ($current_page == 'hr_center.php') ? 'active' : ''; ?>" style="background: rgba(0, 229, 255, 0.1); border-color: rgba(0, 229, 255, 0.3);">
                        <i class="fas fa-users-cog" style="color: #00e5ff;"></i> HR Center
                    </a>
                <?php endif; ?>

                <!-- Setup Dropdown (Admin Only) -->
                <?php if (isAdmin()): ?>
                    <div class="dropdown dropdown-compact">
                        <a href="#" class="nav-link-compact dropdown-toggle <?php echo (in_array($current_page, ['stock_locations.php', 'categories.php', 'departments.php', 'locations.php'])) ? 'active' : ''; ?>" data-bs-toggle="dropdown">
                            <i class="fas fa-cog"></i> Setup
                        </a>
                        <ul class="dropdown-menu shadow">
                            <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>stock_locations.php"><i class="fas fa-warehouse me-2"></i> Stock Locations</a></li>
                            <li>
                                <hr class="dropdown-divider">
                            </li>
                            <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>categories.php"><i class="fas fa-list me-2"></i> Categories</a></li>
                            <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>departments.php"><i class="fas fa-building me-2"></i> Departments</a></li>
                            <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>locations.php"><i class="fas fa-map-marker-alt me-2"></i> Locations</a></li>
                        </ul>
                    </div>
                <?php endif; ?>

                <!-- User Profile Dropdown -->
                <div class="dropdown dropdown-compact">
                    <a href="#" class="nav-link-compact dropdown-toggle" data-bs-toggle="dropdown">
                        <i class="fas fa-user-circle"></i> Account
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>profile.php"><i class="fas fa-user me-2"></i> My Profile</a></li>
                        <li>
                            <hr class="dropdown-divider">
                        </li>
                        <li><a class="dropdown-item text-danger" href="<?php echo BASE_URL; ?>logout.php" onclick="return confirm('Are you sure you want to logout?')">
                                <i class="fas fa-sign-out-alt me-2"></i> Logout
                            </a></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>