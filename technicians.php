<?php
// technicians.php - Technicians Management Page
$current_page = 'technicians.php';
require_once 'bootstrap.php';

// Include functions.php FIRST - this defines isLoggedIn()
require_once 'includes/functions.php';



// Check authentication - NOW isLoggedIn() is defined
if (!isLoggedIn()) {
    error_log("User not logged in - redirecting to login.php");
    header('Location: login.php');
    exit();
}

// Check permissions - technicians page is accessible by multiple roles
$allowedRoles = ['admin', 'manager', 'tech_lead', 'technician'];

// Check if user has access to this page
$hasAccess = false;
if (isset($_SESSION['role'])) {
    $userRole = strtolower(trim($_SESSION['role']));
    $hasAccess = in_array($userRole, $allowedRoles);

    error_log("User role check: '" . $userRole . "' in allowed roles? " . ($hasAccess ? 'Yes' : 'No'));
} else {
    error_log("No role found in session!");
}

// If no access, redirect to dashboard with error
if (!$hasAccess) {
    $_SESSION['message'] = "You don't have permission to access the technicians page.";
    $_SESSION['message_type'] = 'danger';
    header('Location: dashboard_full.php');
    exit();
}

// Check if user has edit permissions (admin, manager, tech_lead can edit)
$editRoles = ['admin', 'manager', 'tech_lead'];
$canEdit = false;
if (isset($_SESSION['role'])) {
    $userRole = strtolower(trim($_SESSION['role']));
    $canEdit = in_array($userRole, $editRoles);
}

// Set access flag
$accessDenied = !$canEdit;
$accessDeniedMessage = 'You have view-only access to technician management. Edit permissions require Admin, Manager, or Tech Lead role.';

// Include database connection
require_once 'includes/database_fix.php';

// Get database connection
try {
    $db = new DatabaseFix();
    $conn = $db->getConnection();

    // Test the connection
    $test = $conn->query("SELECT 1");
    error_log("Database connection successful");
} catch (Exception $e) {
    error_log("Database connection failed: " . $e->getMessage());
    die("Database connection failed: " . $e->getMessage());
}

$pageTitle = "Technician Management - aBility";
$showBreadcrumb = true;
$breadcrumbItems = [
    'Dashboard' => 'dashboard_full.php',
    'Technicians' => ''
];

require_once 'views/partials/header.php';
?>

<!-- Add this HTML container for toasts -->
<div id="centeredToastContainer" class="toast-container-centered"></div>

<?php
// Handle actions
$action = $_GET['action'] ?? '';
$message = '';
$messageType = '';

// Handle technician deletion - only for users with edit permissions
if ($canEdit && $action === 'delete' && isset($_GET['id'])) {
    $techId = (int)$_GET['id'];

    try {
        // Soft delete by setting is_active to 0
        $stmt = $conn->prepare("UPDATE users SET is_active = 0 WHERE id = ?");
        $stmt->bind_param("i", $techId);
        $stmt->execute();

        if ($stmt->affected_rows > 0) {
            $message = 'Technician deactivated successfully.';
            $messageType = 'success';

            // Log activity
            $logStmt = $conn->prepare("INSERT INTO activity_log (user_id, action, description, ip_address) VALUES (?, 'technician_deactivated', ?, ?)");
            $description = "Deactivated technician ID: $techId";
            $ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
            $logStmt->bind_param("iss", $_SESSION['user_id'], $description, $ip);
            $logStmt->execute();
            $logStmt->close();
        } else {
            $message = 'Technician not found.';
            $messageType = 'warning';
        }
        $stmt->close();
    } catch (Exception $e) {
        $message = 'Error deactivating technician: ' . $e->getMessage();
        $messageType = 'danger';
    }
}

// Get technicians with activity count - SHOW ALL regardless of is_active
try {
    $result = $conn->query("
        SELECT u.*, COUNT(a.id) as activity_count
        FROM users u
        LEFT JOIN activity_log a ON u.id = a.user_id AND a.action LIKE '%technician%'
        WHERE u.role IN ('technician', 'tech_lead', 'senior_tech', 'audio_specialist', 'video_specialist', 'lighting_specialist', 'rigging_specialist')
        GROUP BY u.id
        ORDER BY u.created_at DESC
    ");

    if ($result) {
        $technicians = $result->fetch_all(MYSQLI_ASSOC);
        $result->free();
        error_log("Total technicians found: " . count($technicians));
    } else {
        $technicians = [];
        throw new Exception($conn->error);
    }
} catch (Exception $e) {
    $technicians = [];
    $message = 'Error loading technicians: ' . $e->getMessage();
    $messageType = 'danger';
}

// Define technician roles
$techRoles = [
    'technician' => 'Technician',
    'senior_tech' => 'Senior Technician',
    'tech_lead' => 'Tech Lead',
    'audio_specialist' => 'Audio Specialist',
    'video_specialist' => 'Video Specialist',
    'lighting_specialist' => 'Lighting Specialist',
    'rigging_specialist' => 'Rigging Specialist',
    'admin' => 'Administrator',
    'stockcontroller' => 'Stock Controller'
];

// Define departments
$departments = ['IT', 'Audio', 'Video', 'Lighting', 'Electrical', 'Rigging', 'Stock', 'Operations', 'Warehouse', 'Sales'];
?>

<!-- Toast Notification System -->
<style>
    /* Centered Toast Container */
    .toast-container-centered {
        position: fixed;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        z-index: 9999;
        pointer-events: none;
    }

    .toast-centered {
        min-width: 300px;
        max-width: 450px;
        background: white;
        border-radius: 12px;
        box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2), 0 8px 16px rgba(0, 0, 0, 0.1);
        overflow: hidden;
        animation: slideInDown 0.5s ease-out;
        pointer-events: auto;
        border-left: 6px solid;
    }

    .toast-centered.toast-danger {
        border-left-color: #b50909;
    }

    .toast-centered.toast-success {
        border-left-color: #28a745;
    }

    .toast-centered.toast-warning {
        border-left-color: #ffc107;
    }

    .toast-centered.toast-info {
        border-left-color: #17a2b8;
    }

    .toast-content {
        display: flex;
        align-items: center;
        padding: 16px 20px;
    }

    .toast-icon {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-right: 15px;
        font-size: 20px;
    }

    .toast-danger .toast-icon {
        background: rgba(220, 53, 69, 0.15);
        color: #dc3545;
    }

    .toast-message {
        flex: 1;
        font-size: 15px;
        font-weight: 500;
        color: #333;
    }

    .toast-close {
        color: #999;
        cursor: pointer;
        font-size: 18px;
        padding: 0 5px;
        transition: color 0.2s;
    }

    .toast-close:hover {
        color: #333;
    }

    .toast-progress {
        height: 4px;
        background: rgba(220, 53, 69, 0.2);
        position: relative;
    }

    .toast-progress-bar {
        height: 100%;
        background: #dc3545;
        animation: progressShrink 10s linear forwards;
    }

    @keyframes slideInDown {
        from {
            opacity: 0;
            transform: translateY(-30px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    @keyframes progressShrink {
        from {
            width: 100%;
        }

        to {
            width: 0%;
        }
    }

    /* Pulse animation for attention */
    .toast-pulse {
        animation: pulse 0.5s ease-in-out 1;
    }

    @keyframes pulse {

        0%,
        100% {
            transform: scale(1);
        }

        50% {
            transform: scale(1.05);
        }
    }

    /* Disabled button styles */
    .btn.disabled,
    .btn:disabled {
        opacity: 0.5;
        cursor: not-allowed;
        pointer-events: none;
    }

    /* View-only indicator */
    .view-only-badge {
        background: #ffc107;
        color: #50361e;
        padding: 2px 8px;
        border-radius: 4px;
        font-size: 0.7rem;
        font-weight: 600;
        margin-left: 8px;
    }

    /* Technician avatar circle */
    .avatar-circle {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        background: #007bff;
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: bold;
    }

    .avatar-circle-lg {
        width: 80px;
        height: 80px;
        border-radius: 50%;
        background: linear-gradient(135deg, #007bff, #6610f2);
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 2rem;
        font-weight: bold;
    }

    .stat-number {
        font-size: 1.5rem;
        font-weight: bold;
        color: #233643;
    }

    .stat-label {
        font-size: 0.8rem;
        color: #6c757d;
    }

    .bg-gradient-primary {
        background: linear-gradient(135deg, #233643 0%, #2c4760 100%) !important;
    }

    /* Modal landscape styles */
    .modal-xl .modal-content {
        border-radius: 0.5rem;
        overflow: hidden;
    }

    .modal-xl .modal-body {
        max-height: 70vh;
        overflow-y: auto;
    }

    @media (max-width: 992px) {
        .modal-xl .row.g-0 {
            flex-direction: column;
        }

        .modal-xl .col-lg-5,
        .modal-xl .col-lg-7 {
            width: 100%;
        }

        .modal-xl .col-lg-5.bg-light {
            border-bottom: 1px solid #dee2e6;
        }
    }

    /* Status badge styles */
    .badge-available {
        background: #28a745;
        color: white;
    }

    .badge-busy {
        background: #ffc107;
        color: #333;
    }

    .badge-off {
        background: #dc3545;
        color: white;
    }

    .badge-onleave {
        background: #6c757d;
        color: white;
    }

    /* Export buttons styling */
    .dt-buttons {
        margin-bottom: 10px;
    }

    .dt-buttons .btn {
        margin-right: 5px;
        border-radius: 4px;
        padding: 0.375rem 0.75rem;
        font-size: 0.875rem;
        line-height: 1.5;
    }

    .dt-buttons .btn-success {
        background-color: #28a745;
        border-color: #28a745;
        color: white;
    }

    .dt-buttons .btn-info {
        background-color: #17a2b8;
        border-color: #17a2b8;
        color: white;
    }

    .dt-buttons .btn-danger {
        background-color: #dc3545;
        border-color: #dc3545;
        color: white;
    }

    .dt-buttons .btn-secondary {
        background-color: #6c757d;
        border-color: #6c757d;
        color: white;
    }

    .dt-buttons .btn:hover {
        transform: translateY(-1px);
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
    }

    /* Custom pagination styling */
    .dataTables_paginate {
        margin-top: 15px;
    }

    .dataTables_paginate .paginate_button {
        padding: 0.5rem 0.75rem;
        margin: 0 0.25rem;
        border: 1px solid #dee2e6;
        border-radius: 0.25rem;
        background: white;
        color: #234c6a;
        cursor: pointer;
    }

    .dataTables_paginate .paginate_button.current {
        background: #234c6a;
        color: white !important;
        border-color: #234c6a;
    }

    .dataTables_paginate .paginate_button:hover {
        background: #e9ecef;
    }

    .dataTables_paginate .paginate_button.disabled {
        opacity: 0.5;
        cursor: not-allowed;
    }

    .dataTables_info {
        margin-top: 15px;
        color: #6c757d;
    }

    /* Filter section styling */
    .filter-card {
        background: #f8f9fa;
        border: 1px solid #dee2e6;
        border-radius: 0.5rem;
        padding: 1rem;
        margin-bottom: 1.5rem;
    }
</style>

<script>
    // Centered Toast Notification System
    function showCenteredToast(message, type = 'danger', duration = 5000) {
        const container = document.getElementById('centeredToastContainer');
        if (!container) return;

        // Remove any existing toasts
        const existingToasts = container.querySelectorAll('.toast-centered');
        existingToasts.forEach(toast => toast.remove());

        // Create toast element
        const toast = document.createElement('div');
        toast.className = `toast-centered toast-${type}`;

        // Choose icon based on type
        let icon = '🔒';
        if (type === 'danger') icon = '⚠️';
        else if (type === 'success') icon = '✅';
        else if (type === 'warning') icon = '⚠️';
        else if (type === 'info') icon = 'ℹ️';

        // Toast HTML structure
        toast.innerHTML = `
        <div class="toast-content">
            <div class="toast-icon">${icon}</div>
            <div class="toast-message">${message}</div>
            <div class="toast-close" onclick="this.closest('.toast-centered').remove()">✕</div>
        </div>
        <div class="toast-progress">
            <div class="toast-progress-bar" style="background-color: ${type === 'danger' ? '#dc3545' : (type === 'success' ? '#28a745' : (type === 'warning' ? '#ffc107' : '#17a2b8'))}"></div>
        </div>
    `;

        // Add to container
        container.appendChild(toast);

        // Add pulse animation
        toast.classList.add('toast-pulse');
        setTimeout(() => toast.classList.remove('toast-pulse'), 1000);

        // Auto remove after duration
        setTimeout(() => {
            if (toast.parentNode) {
                toast.style.animation = 'slideInDown 0.3s reverse';
                setTimeout(() => toast.remove(), 300);
            }
        }, duration);

        return toast;
    }

    // Function to disable technician actions for view-only mode
    function disableTechnicianActions() {
        // Disable add technician button
        const addBtn = document.querySelector('[data-bs-target="#addTechnicianModal"]');
        if (addBtn) {
            addBtn.disabled = true;
            addBtn.classList.add('disabled');
            addBtn.title = 'Edit permissions required';
        }

        // Disable edit buttons
        document.querySelectorAll('.edit-tech-btn').forEach(btn => {
            btn.disabled = true;
            btn.classList.add('disabled');
            btn.style.opacity = '0.5';
            btn.style.pointerEvents = 'none';
            btn.title = 'Edit permissions required';
        });

        // Disable delete buttons
        document.querySelectorAll('.delete-tech-btn').forEach(btn => {
            btn.disabled = true;
            btn.classList.add('disabled');
            btn.style.opacity = '0.5';
            btn.style.pointerEvents = 'none';
            btn.title = 'Edit permissions required';
        });

        // Show toast when trying to open modals
        $('.edit-tech-btn, .delete-tech-btn, [data-bs-target="#addTechnicianModal"]').on('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            showCenteredToast('Edit permissions required to perform this action.', 'warning', 4000);
            return false;
        });
    }

    // Show toast immediately if access denied
    <?php if ($accessDenied): ?>
        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(function() {
                showCenteredToast('<?php echo $accessDeniedMessage; ?>', 'danger', 8000);
            }, 500);
            disableTechnicianActions();
        });
    <?php endif; ?>
</script>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">
            <i class="fas fa-tools me-2"></i>Technician Management
            <?php if ($accessDenied): ?>
                <span class="view-only-badge">VIEW ONLY</span>
            <?php endif; ?>
        </h1>
        <button type="button" class="btn btn-<?php echo $accessDenied ? 'secondary' : 'primary'; ?>"
            data-bs-toggle="modal" data-bs-target="#addTechnicianModal"
            <?php echo $accessDenied ? 'disabled' : ''; ?>
            title="<?php echo $accessDenied ? 'Edit permissions required' : ''; ?>">
            <i class="fas fa-<?php echo $accessDenied ? 'lock' : 'plus'; ?> me-1"></i>
            <?php echo $accessDenied ? 'Add Technician (Restricted)' : 'Add Technician'; ?>
        </button>
    </div>

    <?php if ($accessDenied): ?>
        <div class="alert alert-warning alert-dismissible fade show mb-4" role="alert">
            <i class="fas fa-exclamation-triangle me-2"></i>
            <strong>Limited Access:</strong> You are viewing this page in read-only mode. Edit permissions required to modify technicians.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if ($message): ?>
        <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
            <?php echo htmlspecialchars($message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- FILTER SECTION -->
    <div class="filter-card">
        <div class="row g-3">
            <div class="col-md-4">
                <label for="roleFilter" class="form-label fw-bold">Filter by Specialization</label>
                <select class="form-select" id="roleFilter">
                    <option value="">All Specializations</option>
                    <?php foreach ($techRoles as $key => $label): ?>
                        <option value="<?php echo $key; ?>"><?php echo $label; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label for="departmentFilter" class="form-label fw-bold">Filter by Department</label>
                <select class="form-select" id="departmentFilter">
                    <option value="">All Departments</option>
                    <?php foreach ($departments as $dept): ?>
                        <option value="<?php echo $dept; ?>"><?php echo $dept; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4 d-flex align-items-end">
                <button class="btn btn-secondary w-100" id="clearFilters">
                    <i class="fas fa-times me-2"></i>Clear Filters
                </button>
            </div>
        </div>
    </div>

    <!-- TECHNICIANS TABLE -->
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-hover" id="techniciansTable">
                    <thead>
                        <tr>
                            <th>Technician</th>
                            <th>Contact</th>
                            <th>Specialization</th>
                            <th>Department</th>
                            <th>Status</th>
                            <th>Last Login</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($technicians)): ?>
                            <tr>
                                <td colspan="7" class="text-center">No technicians found</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($technicians as $tech): ?>
                                <tr data-role="<?php echo $tech['role']; ?>" data-department="<?php echo $tech['department']; ?>">
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="avatar-circle me-2">
                                                <?php echo strtoupper(substr($tech['username'], 0, 1)); ?>
                                            </div>
                                            <div>
                                                <div class="fw-bold"><?php echo htmlspecialchars($tech['full_name']); ?></div>
                                                <small class="text-muted">@<?php echo htmlspecialchars($tech['username']); ?></small>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div>
                                            <div><i class="fas fa-envelope me-1 text-muted small"></i><?php echo htmlspecialchars($tech['email']); ?></div>
                                            <div><i class="fas fa-phone me-1 text-muted small"></i><?php echo htmlspecialchars($tech['phone'] ?? 'N/A'); ?></div>
                                        </div>
                                    </td>
                                    <td>
                                        <?php
                                        $roleClass = match ($tech['role']) {
                                            'tech_lead' => 'danger',
                                            'senior_tech' => 'warning',
                                            'technician' => 'info',
                                            'admin' => 'dark',
                                            default => 'secondary'
                                        };
                                        ?>
                                        <span class="badge bg-<?php echo $roleClass; ?>">
                                            <?php echo $techRoles[$tech['role']] ?? $tech['role']; ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($tech['department'] ?? 'N/A'); ?></td>
                                    <td>
                                        <?php
                                        if ($tech['is_active'] == 1) {
                                            $statusText = 'Available';
                                            $statusClass = 'success';
                                        } else {
                                            $statusText = 'Inactive';
                                            $statusClass = 'danger';
                                        }
                                        ?>
                                        <span class="badge bg-<?php echo $statusClass; ?>">
                                            <?php echo $statusText; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if (isset($tech['last_login']) && !empty($tech['last_login']) && $tech['last_login'] != '0000-00-00 00:00:00'): ?>
                                            <small><?php echo date('M j, Y g:i A', strtotime($tech['last_login'])); ?></small>
                                        <?php else: ?>
                                            <small class="text-muted">Never</small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="btn-group" role="group">
                                            <button type="button" class="btn btn-sm btn-outline-primary edit-tech-btn"
                                                data-id="<?php echo $tech['id']; ?>"
                                                data-username="<?php echo htmlspecialchars($tech['username']); ?>"
                                                data-fullname="<?php echo htmlspecialchars($tech['full_name']); ?>"
                                                data-email="<?php echo htmlspecialchars($tech['email']); ?>"
                                                data-phone="<?php echo htmlspecialchars($tech['phone'] ?? ''); ?>"
                                                data-role="<?php echo $tech['role']; ?>"
                                                data-department="<?php echo htmlspecialchars($tech['department'] ?? ''); ?>"
                                                <?php echo $accessDenied ? 'disabled' : ''; ?>
                                                title="<?php echo $accessDenied ? 'Edit permissions required' : ''; ?>">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button type="button" class="btn btn-sm btn-outline-danger delete-tech-btn"
                                                data-id="<?php echo $tech['id']; ?>"
                                                data-name="<?php echo htmlspecialchars($tech['full_name']); ?>"
                                                <?php echo $accessDenied ? 'disabled' : ''; ?>
                                                title="<?php echo $accessDenied ? 'Edit permissions required' : ''; ?>">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Add Technician Modal -->
<div class="modal fade" id="addTechnicianModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add New Technician</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="addTechnicianForm">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Username *</label>
                        <input type="text" class="form-control" name="username" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Full Name *</label>
                        <input type="text" class="form-control" name="full_name" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email *</label>
                        <input type="email" class="form-control" name="email" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Password *</label>
                        <input type="password" class="form-control" name="password" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Phone</label>
                        <input type="tel" class="form-control" name="phone">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Specialization *</label>
                        <select class="form-select" name="role" required>
                            <option value="">Select Specialization</option>
                            <?php foreach ($techRoles as $key => $label): ?>
                                <option value="<?php echo $key; ?>"><?php echo $label; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Department</label>
                        <select class="form-select" name="department">
                            <option value="">Select Department</option>
                            <?php foreach ($departments as $dept): ?>
                                <option value="<?php echo $dept; ?>"><?php echo $dept; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Technician</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Technician Actions Modal - Combined Edit & Details -->
<div class="modal fade" id="technicianActionsModal" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-gradient-primary text-white">
                <h5 class="modal-title">
                    <i class="fas fa-user-cog me-2"></i>Technician Details & Actions
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0">
                <div class="row g-0">
                    <!-- Left Side: Technician Information -->
                    <div class="col-lg-5 bg-light">
                        <div class="p-4 h-100">
                            <div class="text-center mb-4">
                                <div class="avatar-circle-lg mx-auto mb-3">
                                    <span id="action_tech_initials">T</span>
                                </div>
                                <h4 id="action_tech_name" class="fw-bold mb-1">Technician Name</h4>
                                <p class="text-muted mb-2" id="action_tech_email">email@example.com</p>
                                <p class="text-muted mb-2" id="action_tech_phone">Phone: N/A</p>
                                <div class="d-flex justify-content-center gap-2 mb-3">
                                    <span class="badge bg-primary" id="action_tech_role">Role</span>
                                    <span class="badge bg-secondary" id="action_tech_dept">Department</span>
                                    <span class="badge" id="action_tech_status">Status</span>
                                </div>
                            </div>

                            <div class="border-top pt-3">
                                <h6 class="fw-bold mb-3">Quick Actions</h6>
                                <div class="d-grid gap-2">
                                    <button type="button" class="btn btn-outline-primary" id="sendResetEmailBtn">
                                        <i class="fas fa-envelope me-2"></i> Send Password Reset
                                    </button>
                                    <button type="button" class="btn btn-outline-info" id="copyTechInfoBtn">
                                        <i class="fas fa-copy me-2"></i> Copy Technician Details
                                    </button>
                                    <button type="button" class="btn btn-outline-dark" id="viewScheduleBtn">
                                        <i class="fas fa-calendar me-2"></i> View Schedule
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Right Side: Edit Form -->
                    <div class="col-lg-7">
                        <div class="p-4">
                            <form id="technicianActionsForm">
                                <input type="hidden" name="tech_id" id="action_tech_id">

                                <!-- Basic Information Section -->
                                <div class="mb-4">
                                    <h6 class="border-bottom pb-2 mb-3 fw-bold">Edit Technician Information</h6>
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label class="form-label">Username *</label>
                                            <input type="text" class="form-control" name="username" id="action_username" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Full Name *</label>
                                            <input type="text" class="form-control" name="full_name" id="action_fullname" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Email *</label>
                                            <input type="email" class="form-control" name="email" id="action_email" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Phone</label>
                                            <input type="tel" class="form-control" name="phone" id="action_phone">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">New Password</label>
                                            <div class="input-group">
                                                <input type="password" class="form-control" name="password" id="action_password" autocomplete="new-password">
                                                <button class="btn btn-outline-secondary" type="button" id="action_generate_password">
                                                    <i class="fas fa-bolt"></i>
                                                </button>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Confirm Password</label>
                                            <input type="password" class="form-control" name="password_confirm" id="action_password_confirm" autocomplete="new-password">
                                        </div>
                                    </div>
                                </div>

                                <!-- Role & Department Section -->
                                <div class="mb-4">
                                    <h6 class="border-bottom pb-2 mb-3 fw-bold">Specialization & Department</h6>
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label class="form-label">Specialization *</label>
                                            <select class="form-select" name="role" id="action_role" required>
                                                <option value="">Select Specialization</option>
                                                <?php foreach ($techRoles as $key => $label): ?>
                                                    <option value="<?php echo $key; ?>"><?php echo $label; ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Department</label>
                                            <select class="form-select" name="department" id="action_department">
                                                <option value="">Select Department</option>
                                                <?php foreach ($departments as $dept): ?>
                                                    <option value="<?php echo $dept; ?>"><?php echo $dept; ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <!-- Account Status Section -->
                                <div class="mb-4">
                                    <h6 class="border-bottom pb-2 mb-3 fw-bold">Account Status</h6>
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <div class="form-check form-switch">
                                                <input class="form-check-input" type="checkbox" id="action_is_active" name="is_active" checked>
                                                <label class="form-check-label" for="action_is_active">
                                                    Account Active
                                                </label>
                                            </div>
                                            <small class="form-text text-muted">Toggle to enable/disable technician access</small>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" id="action_reset_login" name="reset_login">
                                                <label class="form-check-label" for="action_reset_login">
                                                    Force password reset on next login
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Danger Zone -->
                                <div class="border rounded p-3 bg-danger bg-opacity-10 border-danger">
                                    <h6 class="text-danger fw-bold mb-3">
                                        <i class="fas fa-exclamation-triangle me-2"></i>Danger Zone
                                    </h6>
                                    <p class="text-muted mb-3">These actions are irreversible. Use with caution.</p>
                                    <div class="d-grid gap-2">
                                        <button type="button" class="btn btn-outline-danger" id="disableTechBtn">
                                            <i class="fas fa-user-slash me-2"></i> Deactivate Technician
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times me-1"></i> Cancel
                </button>
                <button type="submit" form="technicianActionsForm" class="btn btn-primary">
                    <i class="fas fa-save me-1"></i> Save Changes
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteTechnicianModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Confirm Deactivation</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to deactivate <strong id="delete_technician_name"></strong>?</p>
                <p class="text-muted">This technician will no longer be able to access the system.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <a href="#" class="btn btn-danger" id="confirmDeleteBtn">Deactivate Technician</a>
            </div>
        </div>
    </div>
</div>

<!-- DataTables CSS and JS -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.bootstrap5.min.css">

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.html5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.print.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/pdfmake.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/vfs_fonts.js"></script>

<script>
    $(document).ready(function() {
        console.log('Document ready, initializing DataTable...');

        // Initialize DataTable
        const table = $('#techniciansTable').DataTable({
            pageLength: 5,
            lengthMenu: [
                [5, 10, 25, 50, -1],
                [5, 10, 25, 50, "All"]
            ],
            order: [
                [0, 'asc']
            ],
            columnDefs: [{
                targets: 6,
                orderable: false,
                searchable: false
            }],
            language: {
                search: "Search:",
                lengthMenu: "Show _MENU_ entries",
                info: "Showing _START_ to _END_ of _TOTAL_ technicians",
                infoEmpty: "Showing 0 to 0 of 0 technicians",
                infoFiltered: "(filtered from _MAX_ total entries)",
                paginate: {
                    first: '<i class="fas fa-angle-double-left"></i>',
                    previous: '<i class="fas fa-chevron-left"></i>',
                    next: '<i class="fas fa-chevron-right"></i>',
                    last: '<i class="fas fa-angle-double-right"></i>'
                }
            },
            buttons: [{
                    extend: 'excelHtml5',
                    text: '<i class="fas fa-file-excel me-2"></i>Excel',
                    className: 'btn btn-success btn-sm',
                    exportOptions: {
                        columns: [0, 1, 2, 3, 4, 5]
                    }
                },
                {
                    extend: 'csvHtml5',
                    text: '<i class="fas fa-file-csv me-2"></i>CSV',
                    className: 'btn btn-info btn-sm',
                    exportOptions: {
                        columns: [0, 1, 2, 3, 4, 5]
                    }
                },
                {
                    extend: 'pdfHtml5',
                    text: '<i class="fas fa-file-pdf me-2"></i>PDF',
                    className: 'btn btn-danger btn-sm',
                    exportOptions: {
                        columns: [0, 1, 2, 3, 4, 5]
                    }
                },
                {
                    extend: 'print',
                    text: '<i class="fas fa-print me-2"></i>Print',
                    className: 'btn btn-secondary btn-sm',
                    exportOptions: {
                        columns: [0, 1, 2, 3, 4, 5]
                    }
                }
            ],
            dom: "<'row'<'col-sm-12 col-md-6'B><'col-sm-12 col-md-6'f>>" +
                "<'row'<'col-sm-12'tr>>" +
                "<'row'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7'p>>"
        });

        console.log('DataTable initialized');

        // Custom column filtering
        $('#roleFilter').on('change', function() {
            const roleFilter = $(this).val();
            table.column(2).search(roleFilter).draw();
            console.log('Role filter applied:', roleFilter);
        });

        $('#departmentFilter').on('change', function() {
            const deptFilter = $(this).val();
            table.column(3).search(deptFilter).draw();
            console.log('Department filter applied:', deptFilter);
        });

        $('#clearFilters').on('click', function() {
            $('#roleFilter').val('');
            $('#departmentFilter').val('');
            table.columns([2, 3]).search('').draw();
            console.log('Filters cleared');
        });

        <?php if ($canEdit): ?>
            // =============== CREATE TECHNICIAN ===============
            $('#addTechnicianForm').on('submit', function(e) {
                e.preventDefault();

                const formData = $(this).serialize();

                $.ajax({
                    url: 'api/technician_create.php',
                    method: 'POST',
                    data: formData,
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            location.reload();
                        } else {
                            alert('Error: ' + (response.error || 'Failed to create technician'));
                        }
                    },
                    error: function() {
                        alert('Server error occurred while creating technician');
                    }
                });
            });

            // =============== OPEN EDIT MODAL ===============
            $('.edit-tech-btn').on('click', function() {
                const techId = $(this).data('id');
                const username = $(this).data('username');
                const fullname = $(this).data('fullname');
                const email = $(this).data('email');
                const phone = $(this).data('phone');
                const role = $(this).data('role');
                const department = $(this).data('department');

                $('#action_tech_id').val(techId);
                $('#action_username').val(username);
                $('#action_fullname').val(fullname);
                $('#action_email').val(email);
                $('#action_phone').val(phone || '');
                $('#action_role').val(role);
                $('#action_department').val(department || '');

                $('#action_tech_name').text(fullname);
                $('#action_tech_email').text(email);
                $('#action_tech_phone').text(phone ? `Phone: ${phone}` : 'Phone: N/A');
                $('#action_tech_role').text($('#action_role option:selected').text());
                $('#action_tech_dept').text(department || 'N/A');
                $('#action_tech_initials').text(fullname.charAt(0).toUpperCase());

                const status = 'available';
                $('#action_tech_status').text(status.charAt(0).toUpperCase() + status.slice(1));
                $('#action_tech_status').attr('class', `badge bg-${status === 'available' ? 'success' : 'warning'}`);

                $('#action_password').val('');
                $('#action_password_confirm').val('');

                const modal = new bootstrap.Modal(document.getElementById('technicianActionsModal'));
                modal.show();
            });

            // =============== UPDATE TECHNICIAN ===============
            $('#technicianActionsForm').on('submit', function(e) {
                e.preventDefault();

                const formData = $(this).serialize();
                const password = $('#action_password').val();
                const passwordConfirm = $('#action_password_confirm').val();

                if (password && password !== passwordConfirm) {
                    alert('Passwords do not match!');
                    return;
                }

                $.ajax({
                    url: 'api/technician_update.php',
                    method: 'POST',
                    data: formData,
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            alert('Technician updated successfully!');
                            location.reload();
                        } else {
                            alert('Error: ' + (response.error || 'Failed to update technician'));
                        }
                    },
                    error: function() {
                        alert('Server error occurred while updating technician');
                    }
                });
            });

            // =============== DELETE TECHNICIAN ===============
            $('.delete-tech-btn').on('click', function() {
                const techId = $(this).data('id');
                const techName = $(this).data('name');

                $('#delete_technician_name').text(techName);
                $('#confirmDeleteBtn').attr('href', 'technicians.php?action=delete&id=' + techId);

                const modal = new bootstrap.Modal(document.getElementById('deleteTechnicianModal'));
                modal.show();
            });

            // =============== ADDITIONAL FEATURES ===============
            $('#action_generate_password').on('click', function() {
                const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789!@#$%^&*';
                let password = '';
                for (let i = 0; i < 12; i++) {
                    password += chars.charAt(Math.floor(Math.random() * chars.length));
                }

                $('#action_password').val(password);
                $('#action_password_confirm').val(password);

                $('#action_password').attr('type', 'text');
                $('#action_password_confirm').attr('type', 'text');
                setTimeout(() => {
                    $('#action_password').attr('type', 'password');
                    $('#action_password_confirm').attr('type', 'password');
                }, 2000);
            });

            $('#sendResetEmailBtn').on('click', function() {
                const techId = $('#action_tech_id').val();

                $.ajax({
                    url: 'api/send_password_reset.php',
                    method: 'POST',
                    data: {
                        user_id: techId
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            alert('Password reset email sent successfully!');
                        } else {
                            alert('Error: ' + (response.error || 'Failed to send email'));
                        }
                    }
                });
            });

            $('#copyTechInfoBtn').on('click', function() {
                const techInfo = `Name: ${$('#action_fullname').val()}\nUsername: ${$('#action_username').val()}\nEmail: ${$('#action_email').val()}\nPhone: ${$('#action_phone').val() || 'N/A'}\nSpecialization: ${$('#action_role option:selected').text()}`;

                navigator.clipboard.writeText(techInfo).then(function() {
                    alert('Technician information copied to clipboard!');
                }, function(err) {
                    console.error('Could not copy text: ', err);
                });
            });

            $('#disableTechBtn').on('click', function() {
                if (confirm('Are you sure you want to deactivate this technician? They will not be able to login.')) {
                    const techId = $('#action_tech_id').val();

                    $.ajax({
                        url: 'api/technician_disable.php',
                        method: 'POST',
                        data: {
                            tech_id: techId
                        },
                        dataType: 'json',
                        success: function(response) {
                            if (response.success) {
                                alert('Technician deactivated successfully!');
                                location.reload();
                            } else {
                                alert('Error: ' + (response.error || 'Failed to deactivate technician'));
                            }
                        }
                    });
                }
            });

            $('#viewScheduleBtn').on('click', function() {
                const techId = $('#action_tech_id').val();
                window.open('technician_schedule.php?tech_id=' + techId, '_blank');
            });

        <?php endif; ?>
    });
</script>

<?php require_once 'views/partials/footer.php'; ?>