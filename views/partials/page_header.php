<?php
// views/partials/page_header.php
// This header is for internal pages (scan, items, etc.)

// Get user's role
$user_role = getUserRole();

// Define role categories (same as dashboard)
$role_categories = [
    'admin' => [
        'icon' => 'fa-crown',
        'color' => '#5C8374',
        'gradient' => 'linear-gradient(135deg, #5C8374 0%, #3A5F4E 100%)',
        'bg_light' => 'rgba(92, 131, 116, 0.1)'
    ],
    'manager' => [
        'icon' => 'fa-chart-line',
        'color' => '#4B0082',
        'gradient' => 'linear-gradient(135deg, #4B0082 0%, #360061 100%)',
        'bg_light' => 'rgba(75, 0, 130, 0.1)'
    ],
    'tech_lead' => [
        'icon' => 'fa-microchip',
        'color' => '#2E8B57',
        'gradient' => 'linear-gradient(135deg, #2E8B57 0%, #236B43 100%)',
        'bg_light' => 'rgba(46, 139, 86, 0.1)'
    ],
    'technician' => [
        'icon' => 'fa-tools',
        'color' => '#20B2AA',
        'gradient' => 'linear-gradient(135deg, #20B2AA 0%, #1A8F89 100%)',
        'bg_light' => 'rgba(32, 178, 170, 0.1)'
    ],
    'stock_manager' => [
        'icon' => 'fa-boxes-packing',
        'color' => '#CC5500',
        'gradient' => 'linear-gradient(135deg, #CC5500 0%, #A84400 100%)',
        'bg_light' => 'rgba(204, 85, 0, 0.1)'
    ],
    'stock_controller' => [
        'icon' => 'fa-clipboard-check',
        'color' => '#D4AF37',
        'gradient' => 'linear-gradient(135deg, #D4AF37 0%, #B8941F 100%)',
        'bg_light' => 'rgba(212, 175, 55, 0.1)'
    ],
    'user' => [
        'icon' => 'fa-user',
        'color' => '#708090',
        'gradient' => 'linear-gradient(135deg, #708090 0%, #5A6775 100%)',
        'bg_light' => 'rgba(112, 128, 144, 0.1)'
    ],
    'driver' => [
        'icon' => 'fa-truck',
        'color' => '#1E90FF',
        'gradient' => 'linear-gradient(135deg, #1E90FF 0%, #1873CC 100%)',
        'bg_light' => 'rgba(30, 144, 255, 0.1)'
    ]
];

$current_role = $role_categories[$user_role] ?? $role_categories['user'];

// Count accessible pages (same as dashboard)
$all_modules = [
    'view_dashboard',
    'view_events',
    'manage_events',
    'view_equipment',
    'add_equipment',
    'edit_equipment',
    'delete_equipment',
    'view_items',
    'manage_items',
    'import_export',
    'scan_single',
    'scan_bulk',
    'view_scan_history',
    'view_reports',
    'manage_technicians',
    'manage_stock_locations',
    'view_batch_history',
    'view_accessories',
    'manage_users',
    'view_users',
    'manage_permissions',
    'manage_settings'
];

$accessible_count = 0;
foreach ($all_modules as $permission) {
    if (hasPermission($permission)) {
        $accessible_count++;
    }
}
// Admin always has access
if ($user_role === 'admin') {
    $accessible_count = count($all_modules);
}
?>

<style>
    .page-header-compact {
        background: linear-gradient(135deg, #1a2e3f 0%, #234c6a 100%);
        padding: 1rem 2rem;
        margin-bottom: 2rem;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    }

    .user-info-compact {
        display: flex;
        align-items: center;
        gap: 1rem;
    }

    .user-avatar-compact {
        width: 45px;
        height: 45px;
        background: <?php echo $current_role['gradient']; ?>;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 1.2rem;
        box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2);
    }

    .user-details-compact h4 {
        color: white;
        font-size: 1.2rem;
        font-weight: 600;
        margin: 0;
        line-height: 1.3;
    }

    .user-details-compact .role-badge-compact {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        background: <?php echo $current_role['bg_light']; ?>;
        color: white;
        padding: 2px 10px;
        border-radius: 20px;
        font-size: 0.75rem;
        font-weight: 500;
    }

    .role-badge-compact i {
        font-size: 0.7rem;
    }

    .page-count-compact {
        background: rgba(255, 255, 255, 0.1);
        color: white;
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 0.8rem;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        margin-left: 1rem;
    }

    .page-count-compact i {
        color: #4CAF50;
    }

    .back-to-dashboard {
        color: rgba(255, 255, 255, 0.7);
        text-decoration: none;
        font-size: 0.9rem;
        display: flex;
        align-items: center;
        gap: 6px;
        transition: all 0.3s ease;
    }

    .back-to-dashboard:hover {
        color: white;
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
        padding: 6px 16px;
        border-radius: 20px;
        font-size: 0.85rem;
        text-decoration: none;
        transition: all 0.3s ease;
    }

    .logout-btn-compact:hover {
        background: rgba(255, 255, 255, 0.2);
        color: white;
    }

    @media (max-width: 768px) {
        .page-header-compact .d-flex.justify-content-between {
            flex-direction: column;
            gap: 15px;
            align-items: flex-start !important;
        }
        .header-actions-compact {
            width: 100%;
            justify-content: space-between;
        }
        .page-count-compact {
            margin-left: 0;
            margin-top: 5px;
            display: inline-flex;
        }
    }
</style>

<div class="page-header-compact">
    <div class="d-flex justify-content-between align-items-center">
        <div class="d-flex align-items-center gap-4">

            <div class="user-info-compact">
                <div class="user-avatar-compact">
                    <i class="fas <?php echo $current_role['icon']; ?>"></i>
                </div>
                <div class="user-details-compact">
                    <h4><?php echo htmlspecialchars(getUserFullName()); ?></h4>
                    <div>
                        <span class="role-badge-compact">
                            <i class="fas <?php echo $current_role['icon']; ?>"></i>
                            <?php echo ucfirst(str_replace('_', ' ', $user_role)); ?>
                        </span>
                        <span class="page-count-compact">
                            <i class="fas fa-file-alt"></i>
                            <?php echo $accessible_count; ?> Pages
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <div class="header-actions-compact">
            <a href="dashboard_full.php" class="back-to-dashboard">
                <i class="fas fa-arrow-left"></i> Dashboard
            </a>
            <a href="#" class="logout-btn-compact" onclick="showLogoutToast(event)">
                <i class="fas fa-sign-out-alt me-2"></i>Logout
            </a>
        </div>
    </div>
</div>

<!-- Include the same logout toast JavaScript -->
<script>
    function showLogoutToast(event) {
        event.preventDefault();
        // You can reuse the same logout toast from dashboard
        // or create a simple confirm
        if (confirm('Are you sure you want to logout?')) {
            window.location.href = 'logout.php';
        }
    }
</script>