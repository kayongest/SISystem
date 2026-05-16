<?php
// user_management.php - Unified Premium User Management
require_once 'bootstrap.php';
require_once 'includes/functions.php';
require_once 'includes/db_connect.php';

// Check if user is admin
if (!isAdmin()) {
    $_SESSION['toast_message'] = 'You do not have permission to access user management';
    $_SESSION['toast_type'] = 'error';
    header('Location: dashboard_full.php');
    exit();
}

$conn = getConnection();
$pageTitle = "User Management - aBility";

// Handle user add/update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['add_user']) || isset($_POST['update_user']))) {
    $user_id = $_POST['user_id'] ?? null;
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $full_name = trim($_POST['full_name'] ?? '');
    $department = trim($_POST['department'] ?? '');
    $role = $_POST['role'] ?? 'user';
    $password = $_POST['password'] ?? '';
    $user_type = $_POST['user_type'] ?? 'users'; // 'users' or 'technicians'

    try {
        if (isset($_POST['add_user'])) {
            // Check for existing user
            $check_stmt = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
            $check_stmt->bind_param("ss", $username, $email);
            $check_stmt->execute();
            if ($check_stmt->get_result()->num_rows > 0) {
                throw new Exception("Username or email already exists.");
            }
            $check_stmt->close();

            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $sql = "INSERT INTO users (username, password, email, full_name, department, role, is_active, technician_id, created_at) VALUES (?, ?, ?, ?, ?, ?, 1, ?, NOW())";
            $stmt = $conn->prepare($sql);
            $tech_id = $_POST['technician_id'] ?? null;
            $stmt->bind_param("sssssss", $username, $hashed_password, $email, $full_name, $department, $role, $tech_id);

            if ($stmt->execute()) {
                $_SESSION['toast_message'] = "User $username added successfully";
                $_SESSION['toast_type'] = 'success';
            } else {
                throw new Exception($stmt->error);
            }
            $stmt->close();
        } else {
            // Update user
            $tech_id = $_POST['technician_id'] ?? null;
            if (!empty($password)) {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $sql = "UPDATE users SET email = ?, full_name = ?, department = ?, role = ?, password = ?, technician_id = ? WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ssssssi", $email, $full_name, $department, $role, $hashed_password, $tech_id, $user_id);
            } else {
                $sql = "UPDATE users SET email = ?, full_name = ?, department = ?, role = ?, technician_id = ? WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("sssssi", $email, $full_name, $department, $role, $tech_id, $user_id);
            }

            if ($stmt->execute()) {
                $_SESSION['toast_message'] = "User $username updated successfully";
                $_SESSION['toast_type'] = 'success';
            } else {
                throw new Exception($stmt->error);
            }
            $stmt->close();
        }
    } catch (Exception $e) {
        $_SESSION['toast_message'] = $e->getMessage();
        $_SESSION['toast_type'] = 'error';
    }

    header('Location: user_management.php');
    exit();
}

// Handle activation toggle
if (isset($_GET['toggle_status']) && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $status = (int)$_GET['status'];
    $new_status = $status ? 0 : 1;

    $stmt = $conn->prepare("UPDATE users SET is_active = ? WHERE id = ?");
    $stmt->bind_param("ii", $new_status, $id);
    if ($stmt->execute()) {
        $_SESSION['toast_message'] = "User status updated successfully";
        $_SESSION['toast_type'] = 'success';
    }
    header('Location: user_management.php');
    exit();
}

// Fetch all users from single source
$users = [];
$users_res = $conn->query("SELECT id, username, email, full_name, role, department, is_active, created_at, technician_id FROM users");
while ($row = $users_res->fetch_assoc()) $users[] = $row;

// Sort by date
usort($users, function ($a, $b) {
    return strtotime($b['created_at']) - strtotime($a['created_at']);
});

$roles = [
    'admin' => 'Administrator',
    'stock_manager' => 'Stock Manager',
    'stock_controller' => 'Stock Controller',
    'tech_lead' => 'Tech Lead',
    'technician' => 'Technician',
    'user' => 'General User'
];

$role_badges = [
    'admin' => 'bg-danger',
    'stock_manager' => 'bg-success',
    'stock_controller' => 'bg-info',
    'tech_lead' => 'bg-primary',
    'technician' => 'bg-dark',
    'user' => 'bg-secondary'
];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management | aBility</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Marvel:ital,wght@0,400;0,700;1,400;1,700&display=swap');

        body {
            background: #f0f2f5;
            font-family: "Marvel", sans-serif;
            color: #1a2e3f;
        }

        .premium-header {
            background: linear-gradient(135deg, #1a2e3f 0%, #234c6a 100%);
            color: white;
            padding: 2rem;
            border-radius: 0 0 25px 25px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
            transition: transform 0.3s ease;
            height: 100%;
            border-left: 5px solid #234c6a;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .table-container {
            background: white;
            border-radius: 20px;
            padding: 2rem;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.05);
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            background: #e9ecef;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            color: #234c6a;
        }

        .badge-premium {
            padding: 6px 12px;
            border-radius: 50px;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .source-tag {
            font-size: 10px;
            padding: 2px 8px;
            border-radius: 4px;
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            color: #6c757d;
        }

        .btn-action {
            width: 32px;
            height: 32px;
            padding: 0;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
            transition: all 0.2s;
        }

        /* HIGH SPECIFICITY OVERRIDE FOR DATATABLES PAGINATION */
        body .dataTables_wrapper .dataTables_paginate .paginate_button,
        body .dataTables_wrapper .pagination .page-item .page-link {
            box-sizing: border-box !important;
            display: inline-block !important;
            min-width: 1.5em !important;
            padding: 4px 10px !important;
            margin-left: 2px !important;
            text-align: center !important;
            text-decoration: none !important;
            cursor: pointer !important;
            color: inherit !important;
            border: 1px solid transparent !important;
            border-radius: 4px !important;
            background: transparent !important;
            box-shadow: none !important;
        }

        body .dataTables_wrapper .dataTables_paginate .paginate_button.current,
        body .dataTables_wrapper .pagination .page-item.active .page-link {
            background: #234c6a !important;
            color: white !important;
            border-color: #234c6a !important;
        }

        body .dataTables_wrapper .dataTables_paginate .paginate_button.disabled,
        body .dataTables_wrapper .pagination .page-item.disabled .page-link {
            color: #adb5bd !important;
            background-color: transparent !important;
            border-color: #dee2e6 !important;
        }

        .dataTables_wrapper .dataTables_paginate .paginate_button.current {
            background: #234c6a !important;
            color: white !important;
            border-color: #234c6a !important;
        }

        .modal-content {
            border-radius: 20px;
            border: none;
            overflow: hidden;
        }

        .modal-header {
            background: #1a2e3f;
            color: white;
            border: none;
        }

        .form-control,
        .form-select {
            border-radius: 10px;
            padding: 10px 15px;
            border: 1px solid #e0e0e0;
        }

        .form-control:focus {
            border-color: #234c6a;
            box-shadow: 0 0 0 0.25rem rgba(35, 76, 106, 0.1);
        }
    </style>
</head>

<body>
    <div class="premium-header no-print">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="mb-1"><i class="fas fa-users-cog me-2"></i>User Management</h1>
                    <p class="mb-0 opacity-75">Unified portal for Technicians, Managers & Controllers</p>
                </div>
                <div class="d-flex gap-2">
                    <a href="dashboard_full.php" class="btn btn-outline-light rounded-pill px-4 shadow-sm">
                        <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                    </a>
                    <button class="btn btn-light rounded-pill px-4 shadow-sm" data-bs-toggle="modal" data-bs-target="#userModal" onclick="resetForm()">
                        <i class="fas fa-plus-circle me-2 text-primary"></i>Create New User
                    </button>
                    <a href="logout.php" class="btn btn-outline-light rounded-pill px-4 shadow-sm" onclick="showLogoutToast(event)">
                        <i class="fas fa-sign-out-alt me-2"></i>Logout
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="container mb-5">
        <!-- Stats Summary -->
        <div class="row g-4 mb-4">
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="text-muted small mb-1 uppercase">Total Staff</div>
                    <div class="h3 mb-0"><?php echo count($users); ?></div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card" style="border-left-color: #13376dff;">
                    <div class="text-muted small mb-1 uppercase">Technicians</div>
                    <div class="h3 mb-0"><?php
                                            echo count(array_filter($users, function ($u) {
                                                return $u['role'] === 'technician';
                                            }));
                                            ?></div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card" style="border-left-color: #452572ff;">
                    <div class="text-muted small mb-1 uppercase">Stock Managers</div>
                    <div class="h3 mb-0"><?php
                                            echo count(array_filter($users, function ($u) {
                                                return $u['role'] === 'stock_manager';
                                            }));
                                            ?></div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card" style="border-left-color: #10b04aff;">
                    <div class="text-muted small mb-1 uppercase">Active Status</div>
                    <div class="h3 mb-0"><?php
                                            echo count(array_filter($users, function ($u) {
                                                return $u['is_active'] == 1;
                                            }));
                                            ?></div>
                </div>
            </div>
        </div>

        <!-- Main Table -->
        <div class="table-container">
            <table id="usersTable" class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>Identity</th>
                        <th>Role</th>
                        <th>Department</th>
                        <th>Status</th>
                        <th>Created</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $u): ?>
                        <tr>
                            <td>
                                <div class="d-flex align-items-center">
                                    <div class="user-avatar me-3">
                                        <?php echo strtoupper(substr($u['full_name'] ?: $u['username'], 0, 1)); ?>
                                    </div>
                                    <div>
                                        <div class="fw-bold"><?php echo htmlspecialchars($u['full_name'] ?: 'N/A'); ?></div>
                                        <small class="text-muted">@<?php echo htmlspecialchars($u['username']); ?></small>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="badge badge-premium <?php echo $role_badges[$u['role']] ?? 'bg-secondary'; ?>">
                                    <?php echo $roles[$u['role']] ?? ucfirst($u['role']); ?>
                                </span>
                            </td>
                            <td><?php echo htmlspecialchars($u['department'] ?: 'N/A'); ?></td>
                            <td>
                                <span class="badge rounded-pill <?php echo $u['is_active'] ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger'; ?> px-3">
                                    <?php echo $u['is_active'] ? 'Active' : 'Inactive'; ?>
                                </span>
                            </td>
                            <td><small><?php echo date('d M Y', strtotime($u['created_at'])); ?></small></td>
                            <td class="text-end">
                                <button class="btn btn-action btn-light" onclick="editUser(<?php echo htmlspecialchars(json_encode($u)); ?>)" title="Edit User">
                                    <i class="fas fa-edit text-primary"></i>
                                </button>
                                <a href="?toggle_status=1&id=<?php echo $u['id']; ?>&status=<?php echo $u['is_active']; ?>"
                                    class="btn btn-action btn-light" title="<?php echo $u['is_active'] ? 'Deactivate' : 'Activate'; ?>">
                                    <i class="fas fa-power-off <?php echo $u['is_active'] ? 'text-warning' : 'text-success'; ?>"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- User Modal -->
    <div class="modal fade" id="userModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <form id="userForm" method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title" id="modalTitle">Create New User</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body p-4">
                        <input type="hidden" name="user_id" id="user_id">
                        <input type="hidden" name="user_type" id="user_type" value="users">

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Username</label>
                                <input type="text" name="username" id="username" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Full Name</label>
                                <input type="text" name="full_name" id="full_name" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Email Address</label>
                                <input type="email" name="email" id="email" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Department</label>
                                <input type="text" name="department" id="department" class="form-control" placeholder="e.g. Video, IT, Warehouse">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">System Role</label>
                                <select name="role" id="role" class="form-select" required>
                                    <?php foreach ($roles as $val => $label): ?>
                                        <option value="<?php echo $val; ?>"><?php echo $label; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6" id="techIdContainer">
                                <label class="form-label">Technician ID (Staff ID)</label>
                                <input type="text" name="technician_id" id="technician_id_field" class="form-control" placeholder="e.g. TECH001">
                            </div>
                            <div class="col-md-12">
                                <label class="form-label">Password <small id="passHelp" class="text-muted">(Leave empty to keep current during edit)</small></label>
                                <div class="input-group">
                                    <input type="password" name="password" id="password" class="form-control">
                                    <button class="btn btn-outline-secondary" type="button" onclick="generatePass()">
                                        <i class="fas fa-magic"></i> Generate
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer bg-light">
                        <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="add_user" id="submitBtn" class="btn btn-primary rounded-pill px-4">Create User</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        $(document).ready(function() {
            $('#usersTable').DataTable({
                pageLength: 5,
                order: [
                    [0, 'asc']
                ], // Sort first column (username) A to Z
                language: {
                    paginate: {
                        previous: '<i class="fas fa-chevron-left"></i>',
                        next: '<i class="fas fa-chevron-right"></i>'
                    }
                }
            });
        });

        function resetForm() {
            $('#userForm')[0].reset();
            $('#user_id').val('');
            $('#modalTitle').text('Create New User');
            $('#submitBtn').attr('name', 'add_user').text('Create User');
            $('#userTypeContainer').show();
            $('#username').prop('readonly', false);
            $('#passHelp').hide();
        }

        function editUser(user) {
            resetForm();
            $('#userModal').modal('show');
            $('#modalTitle').text('Edit User: ' + user.username);
            $('#submitBtn').attr('name', 'update_user').text('Save Changes');

            $('#user_id').val(user.id);
            $('#username').val(user.username).prop('readonly', true);
            $('#full_name').val(user.full_name);
            $('#email').val(user.email);
            $('#department').val(user.department);
            $('#role').val(user.role);
            $('#technician_id_field').val(user.technician_id);
            $('#passHelp').show();
        }

        function generatePass() {
            const pass = Math.random().toString(36).slice(-10);
            $('#password').val(pass).attr('type', 'text');
            Swal.fire({
                title: 'Generated Password',
                text: pass,
                icon: 'info',
                confirmButtonText: 'Copied to field'
            });
        }

        <?php if (isset($_SESSION['toast_message'])): ?>
            Swal.fire({
                icon: '<?php echo $_SESSION['toast_type']; ?>',
                title: '<?php echo $_SESSION['toast_message']; ?>',
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 3000
            });
            <?php unset($_SESSION['toast_message'], $_SESSION['toast_type']); ?>
        <?php endif; ?>
    </script>
</body>

</html>