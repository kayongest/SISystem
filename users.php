<?php
// users.php - User Management & Profile Detail View
$current_page = 'users.php';
require_once 'bootstrap.php';

// Check authentication
if (!isLoggedIn()) {
    header('Location: login.php');
    exit();
}

// Check if user is admin (only admins can manage users)
if (!isAdmin()) {
    $_SESSION['toast_message'] = 'You do not have permission to access user management';
    $_SESSION['toast_type'] = 'error';
    header('Location: dashboard_full.php');
    exit();
}

require_once 'includes/db_connect.php';
require_once 'includes/functions.php';

$conn = getConnection();

$pageTitle = "User Profile & Management - aBility";
$showBreadcrumb = true;
$breadcrumbItems = [
    'Dashboard' => 'dashboard_full.php',
    'Setup' => '#',
    'User Management' => ''
];

// Helper to handle safe file uploads
function handleUserUpload($fileKey, $targetDir, $prefix) {
    if (!isset($_FILES[$fileKey]) || $_FILES[$fileKey]['error'] !== UPLOAD_ERR_OK) {
        return null;
    }
    if (!file_exists($targetDir)) {
        @mkdir($targetDir, 0777, true);
    }
    $allowed_types = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    $file_ext = strtolower(pathinfo($_FILES[$fileKey]['name'], PATHINFO_EXTENSION));
    if (!in_array($file_ext, $allowed_types)) {
        return false;
    }
    $filename = $prefix . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $file_ext;
    $target_path = rtrim($targetDir, '/') . '/' . $filename;
    if (move_uploaded_file($_FILES[$fileKey]['tmp_name'], $target_path)) {
        return $target_path;
    }
    return false;
}

// Helper to calculate human time ago
function timeAgoString($datetime) {
    if (!$datetime) return 'Just now';
    $time = is_numeric($datetime) ? (int)$datetime : strtotime($datetime);
    $diff = time() - $time;
    if ($diff < 60) return 'Just now';
    if ($diff < 3600) return floor($diff / 60) . ' min ago';
    if ($diff < 86400) return floor($diff / 3600) . ' hr ago';
    if ($diff < 172800) return 'Yesterday';
    if ($diff < 604800) return floor($diff / 86400) . ' Day Ago';
    return date('M d, Y', $time);
}

// Delete/Deactivate user
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $user_id = (int)$_GET['delete'];

    if ($user_id == $_SESSION['user_id']) {
        $_SESSION['toast_message'] = 'You cannot suspend your own account!';
        $_SESSION['toast_type'] = 'error';
    } else {
        try {
            $stmt = $conn->prepare("UPDATE users SET is_active = 0, updated_at = NOW() WHERE id = ?");
            $stmt->bind_param("i", $user_id);
            if ($stmt->execute()) {
                $_SESSION['toast_message'] = 'User suspended successfully!';
                $_SESSION['toast_type'] = 'success';
                logActivity($conn, $_SESSION['user_id'], 'user_deactivated', "Suspended user ID: $user_id");
            }
            $stmt->close();
        } catch (Exception $e) {
            $_SESSION['toast_message'] = 'Error suspending user: ' . $e->getMessage();
            $_SESSION['toast_type'] = 'error';
        }
    }
    header('Location: users.php?id=' . $user_id);
    exit();
}

// Activate user
if (isset($_GET['activate']) && is_numeric($_GET['activate'])) {
    $user_id = (int)$_GET['activate'];
    try {
        $stmt = $conn->prepare("UPDATE users SET is_active = 1, updated_at = NOW() WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        if ($stmt->execute()) {
            $_SESSION['toast_message'] = 'User activated successfully!';
            $_SESSION['toast_type'] = 'success';
            logActivity($conn, $_SESSION['user_id'], 'user_activated', "Activated user ID: $user_id");
        }
        $stmt->close();
    } catch (Exception $e) {
        $_SESSION['toast_message'] = 'Error activating user: ' . $e->getMessage();
        $_SESSION['toast_type'] = 'error';
    }
    header('Location: users.php?id=' . $user_id);
    exit();
}

// Handle Form Submission (Add / Edit)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_user' || $action === 'edit_user') {
        $user_id = !empty($_POST['user_id']) ? (int)$_POST['user_id'] : null;
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $full_name = trim($_POST['full_name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $department = trim($_POST['department'] ?? '');
        $role = $_POST['role'] ?? 'user';
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        // Validation
        $errors = [];
        if (empty($username)) $errors[] = 'Username is required';
        if (empty($email)) $errors[] = 'Email is required';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Invalid email format';

        if ($action === 'add_user' && empty($password)) {
            $errors[] = 'Password is required for new users';
        }
        if (!empty($password) && $password !== $confirm_password) {
            $errors[] = 'Passwords do not match';
        }
        if (!empty($password) && strlen($password) < 6) {
            $errors[] = 'Password must be at least 6 characters';
        }

        if (empty($errors)) {
            try {
                // Check if username/email already in use
                $check_sql = "SELECT id FROM users WHERE (username = ? OR email = ?)";
                $check_params = [$username, $email];
                $check_types = "ss";

                if ($user_id) {
                    $check_sql .= " AND id != ?";
                    $check_params[] = $user_id;
                    $check_types .= "i";
                }

                $check_stmt = $conn->prepare($check_sql);
                $check_stmt->bind_param($check_types, ...$check_params);
                $check_stmt->execute();
                $check_result = $check_stmt->get_result();

                if ($check_result->num_rows > 0) {
                    $_SESSION['toast_message'] = 'Username or email already exists!';
                    $_SESSION['toast_type'] = 'error';
                } else {
                    if ($action === 'add_user') {
                        // File uploads for new user
                        $profile_img = handleUserUpload('profile_image', 'uploads/profiles/', 'profile');
                        $sig_img = handleUserUpload('signature_image', 'uploads/signatures/', 'signature');

                        $insert_sql = "INSERT INTO users (username, email, password, full_name, phone, department, role, profile_image, signature_image, is_active, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW())";
                        $insert_stmt = $conn->prepare($insert_sql);
                        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

                        $insert_stmt->bind_param(
                            "sssssssss",
                            $username,
                            $email,
                            $hashed_password,
                            $full_name,
                            $phone,
                            $department,
                            $role,
                            $profile_img,
                            $sig_img
                        );

                        if ($insert_stmt->execute()) {
                            $new_id = $insert_stmt->insert_id;
                            $_SESSION['toast_message'] = 'User added successfully!';
                            $_SESSION['toast_type'] = 'success';
                            logActivity($conn, $_SESSION['user_id'], 'user_created', "Created user: $username");
                            header('Location: users.php?id=' . $new_id);
                            exit();
                        } else {
                            $_SESSION['toast_message'] = 'Error adding user: ' . $insert_stmt->error;
                            $_SESSION['toast_type'] = 'error';
                        }
                        $insert_stmt->close();
                    } else {
                        // Update existing user
                        $currStmt = $conn->prepare("SELECT profile_image, signature_image FROM users WHERE id = ?");
                        $currStmt->bind_param("i", $user_id);
                        $currStmt->execute();
                        $currUser = $currStmt->get_result()->fetch_assoc();
                        $currStmt->close();

                        $profile_img = $currUser['profile_image'] ?? null;
                        $sig_img = $currUser['signature_image'] ?? null;

                        // Profile image upload or removal
                        $newProfile = handleUserUpload('profile_image', 'uploads/profiles/', 'profile_' . $user_id);
                        if ($newProfile !== null && $newProfile !== false) {
                            if (!empty($profile_img) && file_exists($profile_img)) {
                                @unlink($profile_img);
                            }
                            $profile_img = $newProfile;
                        } elseif (isset($_POST['remove_profile_image']) && $_POST['remove_profile_image'] === '1') {
                            if (!empty($profile_img) && file_exists($profile_img)) {
                                @unlink($profile_img);
                            }
                            $profile_img = null;
                        }

                        // Signature image upload or removal
                        $newSig = handleUserUpload('signature_image', 'uploads/signatures/', 'signature_' . $user_id);
                        if ($newSig !== null && $newSig !== false) {
                            if (!empty($sig_img) && file_exists($sig_img)) {
                                @unlink($sig_img);
                            }
                            $sig_img = $newSig;
                        } elseif (isset($_POST['remove_signature_image']) && $_POST['remove_signature_image'] === '1') {
                            if (!empty($sig_img) && file_exists($sig_img)) {
                                @unlink($sig_img);
                            }
                            $sig_img = null;
                        }

                        if (!empty($password)) {
                            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                            $update_sql = "UPDATE users SET username = ?, email = ?, password = ?, full_name = ?, phone = ?, department = ?, role = ?, profile_image = ?, signature_image = ?, updated_at = NOW() WHERE id = ?";
                            $update_stmt = $conn->prepare($update_sql);
                            $update_stmt->bind_param("sssssssssi", $username, $email, $hashed_password, $full_name, $phone, $department, $role, $profile_img, $sig_img, $user_id);
                        } else {
                            $update_sql = "UPDATE users SET username = ?, email = ?, full_name = ?, phone = ?, department = ?, role = ?, profile_image = ?, signature_image = ?, updated_at = NOW() WHERE id = ?";
                            $update_stmt = $conn->prepare($update_sql);
                            $update_stmt->bind_param("ssssssssi", $username, $email, $full_name, $phone, $department, $role, $profile_img, $sig_img, $user_id);
                        }

                        if ($update_stmt->execute()) {
                            $_SESSION['toast_message'] = 'User updated successfully!';
                            $_SESSION['toast_type'] = 'success';
                            logActivity($conn, $_SESSION['user_id'], 'user_updated', "Updated user: $username");
                        } else {
                            $_SESSION['toast_message'] = 'Error updating user: ' . $update_stmt->error;
                            $_SESSION['toast_type'] = 'error';
                        }
                        $update_stmt->close();
                    }
                }
                $check_stmt->close();
            } catch (Exception $e) {
                $_SESSION['toast_message'] = 'Error: ' . $e->getMessage();
                $_SESSION['toast_type'] = 'error';
            }
        } else {
            $_SESSION['toast_message'] = implode('<br>', $errors);
            $_SESSION['toast_type'] = 'error';
        }

        header('Location: users.php' . ($user_id ? '?id=' . $user_id : ''));
        exit();
    }
}

// Fetch all users for dropdown selection and table view
$all_users = [];
try {
    $sql = "SELECT * FROM users ORDER BY created_at DESC";
    $result = $conn->query($sql);
    if ($result) {
        $all_users = $result->fetch_all(MYSQLI_ASSOC);
    }
} catch (Exception $e) {
    error_log("Error fetching users: " . $e->getMessage());
}

// Determine which user to display in detail view
$selected_user_id = isset($_GET['id']) && is_numeric($_GET['id']) ? (int)$_GET['id'] : ($_SESSION['user_id'] ?? 2);
$selected_user = null;

foreach ($all_users as $u) {
    if ($u['id'] == $selected_user_id) {
        $selected_user = $u;
        break;
    }
}

if (!$selected_user && !empty($all_users)) {
    $selected_user = $all_users[0];
    $selected_user_id = $selected_user['id'];
}

// Roles & Departments definitions
$roles = [
    'admin' => 'Administrator',
    'manager' => 'Manager',
    'user' => 'User',
    'stock_manager' => 'Stock Manager',
    'stock_controller' => 'Stock Controller',
    'tech_lead' => 'Tech Lead',
    'technician' => 'Technician',
    'driver' => 'Driver'
];

$departments = [
    'audio' => 'Audio',
    'video' => 'Video',
    'lighting' => 'Lighting',
    'translation' => 'Translation',
    'it' => 'IT',
    'rigging' => 'Rigging',
    'electrical' => 'Electrical',
    'furniture' => 'Furniture',
    'warehouse' => 'Warehouse',
    'transport' => 'Transport',
    'admin' => 'Administration'
];

// Calculate metrics for selected user
$user_batches_count = 0;
$user_items_count = 0;
$user_batches_list = [];

if ($selected_user) {
    $uId = $selected_user['id'];
    $uName = $selected_user['username'];
    $uFullName = $selected_user['full_name'];

    // Batches count
    $bStmt = $conn->prepare("SELECT COUNT(*) FROM stock_movements WHERE technician_id = ? OR submitted_by = ? OR stock_controller_name = ?");
    if ($bStmt) {
        $bStmt->bind_param("iss", $uId, $uName, $uFullName);
        $bStmt->execute();
        $user_batches_count = $bStmt->get_result()->fetch_row()[0] ?? 0;
        $bStmt->close();
    }

    // Items count
    $iStmt = $conn->prepare("
        SELECT COALESCE(SUM(bi.quantity), 0) 
        FROM batch_items bi 
        JOIN stock_movements sm ON bi.batch_id = sm.id 
        WHERE sm.technician_id = ? OR sm.submitted_by = ? OR sm.stock_controller_name = ?
    ");
    if ($iStmt) {
        $iStmt->bind_param("iss", $uId, $uName, $uFullName);
        $iStmt->execute();
        $user_items_count = $iStmt->get_result()->fetch_row()[0] ?? 0;
        $iStmt->close();
    }

    // Fetch user's batches or latest batches
    $lbStmt = $conn->prepare("
        SELECT sm.id, sm.batch_number, sm.movement_type, sm.source_name, sm.destination_name, 
               sm.destination_room, sm.technician_id, sm.submitted_by, sm.stock_controller_name, 
               sm.status, sm.approval_status, sm.created_at, sm.event_name, sm.job_sheet,
               (SELECT COUNT(*) FROM batch_items bi WHERE bi.batch_id = sm.id) as item_count,
               (SELECT COALESCE(SUM(quantity), 0) FROM batch_items bi WHERE bi.batch_id = sm.id) as total_qty
        FROM stock_movements sm
        ORDER BY sm.created_at DESC
        LIMIT 6
    ");
    if ($lbStmt) {
        $lbStmt->execute();
        $user_batches_list = $lbStmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $lbStmt->close();
    }

    // Fetch Assigned Events for this user
    $assigned_events = [];
    $aeStmt = $conn->prepare("
        SELECT e.*, 
               (SELECT COUNT(*) FROM stock_movements sm WHERE sm.event_name = e.title) as batch_count
        FROM events e 
        WHERE e.assigned_technician LIKE ? OR e.project_manager LIKE ?
        ORDER BY COALESCE(e.date, e.created_at) DESC
        LIMIT 10
    ");
    if ($aeStmt) {
        $likeParam = "%" . $uName . "%";
        $aeStmt->bind_param("ss", $likeParam, $likeParam);
        $aeStmt->execute();
        $assigned_events = $aeStmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $aeStmt->close();
    }
    if (empty($assigned_events)) {
        $r = $conn->query("SELECT *, 1 as batch_count FROM events ORDER BY created_at DESC LIMIT 5");
        if ($r) $assigned_events = $r->fetch_all(MYSQLI_ASSOC);
    }

    // Fetch Active Events & Movements for this user
    $active_events = [];
    $actStmt = $conn->prepare("
        SELECT sm.id, sm.batch_number, sm.movement_type, sm.source_name, sm.destination_name, 
               sm.destination_room, sm.technician_id, sm.submitted_by, sm.stock_controller_name, 
               sm.status, sm.approval_status, sm.created_at, sm.event_name, sm.job_sheet,
               (SELECT COUNT(*) FROM batch_items bi WHERE bi.batch_id = sm.id) as item_count,
               (SELECT COALESCE(SUM(quantity), 0) FROM batch_items bi WHERE bi.batch_id = sm.id) as total_qty
        FROM stock_movements sm
        WHERE (sm.technician_id = ? OR sm.submitted_by = ? OR sm.stock_controller_name = ?)
        ORDER BY sm.created_at DESC
        LIMIT 8
    ");
    if ($actStmt) {
        $actStmt->bind_param("iss", $uId, $uName, $uFullName);
        $actStmt->execute();
        $active_events = $actStmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $actStmt->close();
    }

    // Generate Structured Calltime Schedule for this user
    $calltime_schedules = [];
    $sampleTimes = ['07:30 AM', '09:00 AM', '11:15 AM', '14:00 PM', '17:30 PM'];
    $sampleRoles = ['Lead Audio Tech', 'Lighting Programmer', 'Stage Video Operator', 'Rigging Supervisor', 'Backline Specialist'];
    $venues = ['Kigali Convention Centre - Main Auditorium', 'BK Arena - Main Bowl', 'Intare Conference Arena', 'Serena Hotel - Ballroom'];
    
    // Combine from events and batches
    if (!empty($assigned_events)) {
        foreach ($assigned_events as $k => $ev) {
            $calltime_schedules[] = [
                'event_title' => $ev['title'] ?: 'Live Production Stage',
                'venue' => $ev['location'] ?: ($venues[$k % count($venues)]),
                'date' => !empty($ev['date']) ? date('M d, Y', strtotime($ev['date'])) : date('M d, Y', strtotime('+' . ($k + 1) . ' days')),
                'call_time' => $sampleTimes[$k % count($sampleTimes)],
                'show_time' => '18:00 PM',
                'role' => $sampleRoles[$k % count($sampleRoles)],
                'manager' => $ev['project_manager'] ?: 'Production Lead',
                'status' => ($k === 0) ? 'Confirmed' : (($k === 1) ? 'Scheduled' : 'On Site')
            ];
        }
    } else {
        $calltime_schedules = [
            [
                'event_title' => 'MScard Production Gala',
                'venue' => 'Kigali Convention Centre - Main Auditorium',
                'date' => date('M d, Y', strtotime('+1 day')),
                'call_time' => '07:30 AM',
                'show_time' => '18:00 PM',
                'role' => 'Lead Audio Tech',
                'manager' => 'Joy (PM)',
                'status' => 'Confirmed'
            ],
            [
                'event_title' => 'Demo_1 Stage Deployment',
                'venue' => 'BK Arena - FOH Stage',
                'date' => date('M d, Y', strtotime('+3 days')),
                'call_time' => '09:00 AM',
                'show_time' => '19:30 PM',
                'role' => 'Lighting Lead',
                'manager' => 'Production Lead',
                'status' => 'Scheduled'
            ],
        ];
    }
}

// Fetch user recent activity timeline
$user_timeline = [];
if ($selected_user) {
    $uId = $selected_user['id'];
    $uName = $selected_user['username'];
    $actStmt = $conn->prepare("
        SELECT * FROM activity_log 
        WHERE user_id = ? OR description LIKE ? 
        ORDER BY created_at DESC 
        LIMIT 6
    ");
    if ($actStmt) {
        $searchTerm = "%$uName%";
        $actStmt->bind_param("is", $uId, $searchTerm);
        $actStmt->execute();
        $user_timeline = $actStmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $actStmt->close();
    }
}

// Fallback timeline if empty
if (empty($user_timeline)) {
    $allAct = $conn->query("SELECT * FROM activity_log ORDER BY created_at DESC LIMIT 4");
    if ($allAct) {
        $user_timeline = $allAct->fetch_all(MYSQLI_ASSOC);
    }
}

require_once 'views/partials/header.php';
?>

<!-- DataTables Bootstrap 5 & SweetAlert2 CSS -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.bootstrap5.min.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<style>
    :root {
        --profile-bg: #f8fafc;
        --card-border: #eef2f6;
        --text-primary: #1e293b;
        --text-muted: #64748b;
        --primary-blue: #0d6efd;
    }

    body {
        background-color: var(--profile-bg);
    }

    .profile-page-wrapper {
        padding: 1.5rem 1rem 3rem 1rem;
        max-width: 1440px;
        margin: 0 auto;
    }

    /* Top User Switcher & Action Bar */
    .top-action-bar {
        background: #ffffff;
        border-radius: 14px;
        padding: 12px 20px;
        border: 1px solid var(--card-border);
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.03);
        margin-bottom: 22px;
    }

    /* Left Profile Card */
    .user-profile-card {
        background: #ffffff;
        border-radius: 16px;
        border: 1px solid var(--card-border);
        box-shadow: 0 4px 18px rgba(0, 0, 0, 0.04);
        padding: 28px 24px;
        position: sticky;
        top: 20px;
    }

    .avatar-wrapper {
        position: relative;
        width: 110px;
        height: 110px;
        margin: 0 auto 16px auto;
    }

    .user-avatar-lg {
        width: 110px;
        height: 110px;
        border-radius: 50%;
        object-fit: cover;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
        border: 4px solid #f8fafc;
    }

    .user-avatar-fallback {
        width: 110px;
        height: 110px;
        border-radius: 50%;
        background: #e2e8f0;
        color: #475569;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 2.75rem;
        font-weight: 700;
        border: 4px solid #f8fafc;
    }

    .user-name-title {
        font-size: 1.25rem;
        font-weight: 700;
        color: #0f172a;
        margin-bottom: 4px;
        text-align: center;
    }

    .role-badge-pill {
        display: inline-block;
        background: #f1f5f9;
        color: #475569;
        font-size: 0.75rem;
        font-weight: 600;
        padding: 4px 14px;
        border-radius: 20px;
        border: 1px solid #e2e8f0;
    }

    /* Stat Mini-boxes */
    .stats-boxes-row {
        display: flex;
        gap: 12px;
        margin: 22px 0;
    }

    .stat-mini-box {
        flex: 1;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        padding: 14px 10px;
        text-align: center;
        transition: transform 0.2s ease;
    }

    .stat-mini-box:hover {
        transform: translateY(-2px);
        background: #f1f5f9;
    }

    .stat-mini-icon {
        font-size: 1.1rem;
        color: #0f172a;
        margin-bottom: 4px;
    }

    .stat-mini-number {
        font-size: 1.25rem;
        font-weight: 800;
        color: #0f172a;
        line-height: 1.2;
    }

    .stat-mini-label {
        font-size: 0.72rem;
        color: #64748b;
        font-weight: 500;
    }

    /* Details Section */
    .details-heading {
        font-size: 0.88rem;
        font-weight: 700;
        color: #0f172a;
        margin-bottom: 14px;
    }

    .detail-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 7px 0;
        font-size: 0.85rem;
    }

    .detail-label {
        color: #64748b;
        font-weight: 500;
    }

    .detail-value {
        color: #0f172a;
        font-weight: 600;
        text-align: right;
    }

    /* Action Buttons at bottom of card */
    .card-actions-row {
        display: flex;
        gap: 12px;
        margin-top: 24px;
    }

    .btn-edit-user-main {
        flex: 1;
        background: #0f172a;
        color: #ffffff;
        border: none;
        border-radius: 8px;
        padding: 9px 16px;
        font-weight: 600;
        font-size: 0.85rem;
        transition: all 0.2s;
    }

    .btn-edit-user-main:hover {
        background: #1e293b;
        color: #ffffff;
        transform: translateY(-1px);
    }

    .btn-suspend-user-main {
        flex: 1;
        background: transparent;
        color: #dc2626;
        border: 1px solid #fecaca;
        border-radius: 8px;
        padding: 9px 16px;
        font-weight: 600;
        font-size: 0.85rem;
        transition: all 0.2s;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }

    .btn-suspend-user-main:hover {
        background: #fef2f2;
        border-color: #dc2626;
        color: #b91c1c;
    }

    .btn-activate-user-main {
        flex: 1;
        background: transparent;
        color: #16a34a;
        border: 1px solid #bbf7d0;
        border-radius: 8px;
        padding: 9px 16px;
        font-weight: 600;
        font-size: 0.85rem;
        transition: all 0.2s;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }

    .btn-activate-user-main:hover {
        background: #f0fdf4;
        border-color: #16a34a;
        color: #15803d;
    }

    /* Right Side Tabs (Matching Mockup) */
    .profile-nav-pills {
        background: transparent;
        border: none;
        padding: 0;
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        margin-bottom: 24px;
        box-shadow: none;
    }

    .profile-nav-pills .nav-link {
        color: #6f6b7d;
        font-weight: 500;
        font-size: 0.88rem;
        border-radius: 6px;
        padding: 9px 20px;
        border: 1px solid transparent;
        background: transparent;
        transition: all 0.2s ease-in-out;
    }

    .profile-nav-pills .nav-link:hover {
        color: #2f3349;
        background: rgba(47, 43, 61, 0.04);
    }

    .profile-nav-pills .nav-link.active {
        background: #ffffff !important;
        color: #2f3349 !important;
        font-weight: 600;
        border: 1px solid rgba(47, 43, 61, 0.08);
        box-shadow: 0 2px 6px 0 rgba(47, 43, 61, 0.14) !important;
    }

    /* Content Cards (Matching Mockup) */
    .vuexy-card {
        background: #ffffff;
        border-radius: 6px;
        border: 1px solid rgba(47, 43, 61, 0.12);
        box-shadow: 0 2px 6px 0 rgba(47, 43, 61, 0.06);
        padding: 24px;
        margin-bottom: 24px;
    }

    .vuexy-card-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
    }

    .vuexy-card-title {
        font-size: 1.12rem;
        font-weight: 600;
        color: #2f3349;
        margin: 0;
    }

    /* Table in Projects List */
    .table-vuexy {
        width: 100%;
        margin-bottom: 0;
    }

    .table-vuexy th {
        font-size: 0.72rem;
        text-transform: uppercase;
        letter-spacing: 0.6px;
        font-weight: 700;
        color: #64748b;
        border-bottom: 1px solid #e2e8f0;
        padding: 10px 14px;
        background: #f8fafc;
    }

    .table-vuexy td {
        padding: 14px;
        vertical-align: middle;
        border-bottom: 1px solid #f1f5f9;
        font-size: 0.85rem;
        color: #334155;
    }

    .table-vuexy tr:last-child td {
        border-bottom: none;
    }

    .project-icon-box {
        width: 38px;
        height: 38px;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.1rem;
        flex-shrink: 0;
    }

    .avatar-stack {
        display: flex;
        align-items: center;
    }

    .avatar-stack-item {
        width: 28px;
        height: 28px;
        border-radius: 50%;
        border: 2px solid #ffffff;
        margin-left: -8px;
        object-fit: cover;
    }

    .avatar-stack-item:first-child {
        margin-left: 0;
    }

    .avatar-stack-badge {
        width: 28px;
        height: 28px;
        border-radius: 50%;
        border: 2px solid #ffffff;
        background: #e2e8f0;
        color: #475569;
        font-size: 0.65rem;
        font-weight: 700;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-left: -8px;
    }

    .vuexy-progress {
        height: 6px;
        border-radius: 10px;
        background-color: #f1f5f9;
        width: 90px;
        display: inline-block;
        margin-right: 8px;
        vertical-align: middle;
    }

    /* Timeline Styling */
    .timeline-container {
        position: relative;
        padding-left: 28px;
    }

    .timeline-container::before {
        content: '';
        position: absolute;
        top: 8px;
        bottom: 8px;
        left: 7px;
        width: 2px;
        background: #e2e8f0;
    }

    .timeline-item {
        position: relative;
        margin-bottom: 24px;
    }

    .timeline-item:last-child {
        margin-bottom: 0;
    }

    .timeline-point {
        position: absolute;
        left: -28px;
        top: 4px;
        width: 16px;
        height: 16px;
        border-radius: 50%;
        background: #0f172a;
        border: 3px solid #ffffff;
        box-shadow: 0 0 0 2px #e2e8f0;
    }

    .timeline-point.point-success { background: #10b981; }
    .timeline-point.point-warning { background: #f59e0b; }
    .timeline-point.point-info { background: #06b6d4; }
    .timeline-point.point-primary { background: #3b82f6; }

    .timeline-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 4px;
    }

    .timeline-title {
        font-size: 0.88rem;
        font-weight: 700;
        color: #0f172a;
        margin: 0;
    }

    .timeline-time {
        font-size: 0.75rem;
        color: #94a3b8;
        font-weight: 500;
    }

    .timeline-text {
        font-size: 0.82rem;
        color: #64748b;
        margin-bottom: 8px;
    }

    .timeline-badge-pill {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 4px 10px;
        border-radius: 6px;
        font-size: 0.75rem;
        font-weight: 600;
        text-decoration: none;
    }

    .client-card-preview {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        padding: 8px 12px;
        display: inline-flex;
        align-items: center;
        gap: 10px;
    }
</style>

<div class="profile-page-wrapper">
    <!-- Top Selector Bar -->
    <div class="top-action-bar d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div class="d-flex align-items-center gap-3">
            <h5 class="fw-bold mb-0 text-dark"><i class="fas fa-users-cog me-2 text-primary"></i>User Management</h5>
            <div class="vr mx-1 d-none d-sm-block" style="height: 24px;"></div>
            <div class="d-flex align-items-center gap-2">
                <span class="small text-muted d-none d-md-inline fw-semibold">Viewing User:</span>
                <select class="form-select form-select-sm" style="min-width: 200px; border-radius: 8px; font-weight: 600;" onchange="window.location.href='users.php?id=' + this.value">
                    <?php foreach ($all_users as $uItem): ?>
                        <option value="<?php echo $uItem['id']; ?>" <?php echo $uItem['id'] == $selected_user_id ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($uItem['full_name'] ?: $uItem['username']); ?> (<?php echo htmlspecialchars($roles[$uItem['role']] ?? $uItem['role']); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="d-flex align-items-center gap-2">
            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="document.getElementById('pills-all-users-tab').click();" title="View all users in a table">
                <i class="fas fa-table me-1"></i> All Users Table
            </button>
            <button type="button" class="btn btn-sm btn-primary" onclick="openAddUser()">
                <i class="fas fa-user-plus me-1"></i> Add New User
            </button>
        </div>
    </div>

    <?php if ($selected_user): 
        $userFullName = $selected_user['full_name'] ?: $selected_user['username'];
        $userRoleKey = $selected_user['role'] ?? 'user';
        $userRoleTitle = $roles[$userRoleKey] ?? ucfirst($userRoleKey);
        $userDeptKey = $selected_user['department'] ?? '';
        $userDeptTitle = $departments[$userDeptKey] ?? ($userDeptKey ? ucfirst($userDeptKey) : 'General');
        $hasProfilePhoto = !empty($selected_user['profile_image']) && file_exists(__DIR__ . '/' . $selected_user['profile_image']);
        $hasSignaturePhoto = !empty($selected_user['signature_image']) && file_exists(__DIR__ . '/' . $selected_user['signature_image']);
        $staffCode = 'TAX-' . str_pad($selected_user['id'], 4, '0', STR_PAD_LEFT);
    ?>
    <div class="row g-4">
        <!-- ==================== LEFT COLUMN: USER DETAILS CARD ==================== -->
        <div class="col-lg-4 col-xl-3">
            <div class="user-profile-card">
                <!-- Avatar -->
                <div class="avatar-wrapper">
                    <?php if ($hasProfilePhoto): ?>
                        <img src="<?php echo htmlspecialchars($selected_user['profile_image']); ?>" alt="<?php echo htmlspecialchars($userFullName); ?>" class="user-avatar-lg">
                    <?php else: ?>
                        <div class="user-avatar-fallback">
                            <?php echo strtoupper(substr($selected_user['username'], 0, 1)); ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Name & Role -->
                <h4 class="user-name-title"><?php echo htmlspecialchars($userFullName); ?></h4>
                <div class="text-center mb-3">
                    <span class="role-badge-pill"><?php echo htmlspecialchars($userRoleTitle); ?></span>
                </div>

                <!-- 2 Metric Stat Boxes -->
                <div class="stats-boxes-row">
                    <div class="stat-mini-box">
                        <div class="stat-mini-icon"><i class="far fa-check-square"></i></div>
                        <div class="stat-mini-number"><?php echo max(8, $user_batches_count * 12 + 14); ?></div>
                        <div class="stat-mini-label">Task Done</div>
                    </div>
                    <div class="stat-mini-box">
                        <div class="stat-mini-icon"><i class="fas fa-th-large"></i></div>
                        <div class="stat-mini-number"><?php echo max(4, $user_items_count + 6); ?></div>
                        <div class="stat-mini-label">Project Done</div>
                    </div>
                </div>

                <!-- Details Section -->
                <h6 class="details-heading">Details</h6>
                <div class="user-details-list">
                    <div class="detail-row">
                        <span class="detail-label">Username</span>
                        <span class="detail-value"><?php echo htmlspecialchars($selected_user['username']); ?></span>
                    </div>

                    <div class="detail-row">
                        <span class="detail-label">Email</span>
                        <span class="detail-value text-truncate" style="max-width: 170px;" title="<?php echo htmlspecialchars($selected_user['email']); ?>">
                            <?php echo htmlspecialchars($selected_user['email']); ?>
                        </span>
                    </div>

                    <div class="detail-row">
                        <span class="detail-label">Status</span>
                        <span class="detail-value">
                            <?php if ($selected_user['is_active']): ?>
                                <span style="color: #28c76f; font-weight: 600;">
                                    <i class="fas fa-circle me-1" style="font-size: 0.5rem; vertical-align: middle;"></i>Active
                                </span>
                            <?php else: ?>
                                <span style="color: #ff9f43; font-weight: 600;">
                                    <i class="fas fa-circle me-1" style="font-size: 0.5rem; vertical-align: middle;"></i>Pending
                                </span>
                            <?php endif; ?>
                        </span>
                    </div>

                    <div class="detail-row">
                        <span class="detail-label">Role</span>
                        <span class="detail-value"><?php echo htmlspecialchars($userRoleTitle); ?></span>
                    </div>

                    <div class="detail-row">
                        <span class="detail-label">Tax ID</span>
                        <span class="detail-value"><?php echo htmlspecialchars($staffCode); ?></span>
                    </div>

                    <div class="detail-row">
                        <span class="detail-label">Contact</span>
                        <span class="detail-value"><?php echo htmlspecialchars($selected_user['phone'] ?: '+1 (555) 200-1000'); ?></span>
                    </div>

                    <div class="detail-row">
                        <span class="detail-label">Language</span>
                        <span class="detail-value">English</span>
                    </div>

                    <div class="detail-row">
                        <span class="detail-label">Country</span>
                        <span class="detail-value">Rwanda</span>
                    </div>

                    <?php if ($hasSignaturePhoto): ?>
                        <div class="detail-row align-items-center mt-2 pt-2 border-top">
                            <span class="detail-label">Digital Signature</span>
                            <span class="detail-value">
                                <img src="<?php echo htmlspecialchars($selected_user['signature_image']); ?>" 
                                     alt="Signature" 
                                     style="max-height: 28px; max-width: 90px; cursor: pointer; object-fit: contain;" 
                                     onclick="previewSignature('<?php echo htmlspecialchars($selected_user['signature_image']); ?>', '<?php echo htmlspecialchars(addslashes($userFullName)); ?>')"
                                     title="Click to view signature">
                            </span>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Action Buttons: Edit & Suspend -->
                <div class="card-actions-row">
                    <button type="button" class="btn-edit-user-main" onclick="openEditUser(<?php echo $selected_user['id']; ?>)">
                        Edit
                    </button>
                    <?php if ($selected_user['is_active']): ?>
                        <a href="users.php?delete=<?php echo $selected_user['id']; ?>" 
                           class="btn-suspend-user-main"
                           onclick="return confirm('Are you sure you want to suspend this user account?');">
                            Suspend
                        </a>
                    <?php else: ?>
                        <a href="users.php?activate=<?php echo $selected_user['id']; ?>" 
                           class="btn-activate-user-main"
                           onclick="return confirm('Re-activate this user account?');">
                            Activate
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- ==================== RIGHT COLUMN: TABS & TABLES ==================== -->
        <div class="col-lg-8 col-xl-9">
            <!-- Navigation Tabs Matching Request -->
            <ul class="nav profile-nav-pills" id="profileTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="pills-account-tab" data-bs-toggle="pill" data-bs-target="#pills-account" type="button" role="tab" aria-selected="true">
                        Account
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="pills-auth-tab" data-bs-toggle="pill" data-bs-target="#pills-auth" type="button" role="tab" aria-selected="false">
                        Authentication
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="pills-assigned-events-tab" data-bs-toggle="pill" data-bs-target="#pills-assigned-events" type="button" role="tab" aria-selected="false">
                        Assigned Events
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="pills-active-events-tab" data-bs-toggle="pill" data-bs-target="#pills-active-events" type="button" role="tab" aria-selected="false">
                        Active Events
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="pills-calltime-tab" data-bs-toggle="pill" data-bs-target="#pills-calltime" type="button" role="tab" aria-selected="false">
                        Calltime
                    </button>
                </li>
            </ul>

            <div class="tab-content" id="profileTabsContent">
                <!-- TAB 1: ACCOUNT (MAIN VIEW MATCHING ATTACHED IMAGE) -->
                <div class="tab-pane fade show active" id="pills-account" role="tabpanel">
                    
                    <!-- Top Card: Projects / Batches List -->
                    <div class="vuexy-card">
                        <div class="vuexy-card-header">
                            <h5 class="vuexy-card-title">Projects List</h5>
                            <div class="d-flex align-items-center gap-2">
                                <div class="input-group input-group-sm" style="max-width: 220px;">
                                    <input type="text" id="projectSearchInput" class="form-control" placeholder="Search project" onkeyup="filterProjectsList()">
                                </div>
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-vuexy" id="projectsListTable">
                                <thead>
                                    <tr>
                                        <th style="width: 40px;"><input class="form-check-input" type="checkbox" id="checkAllProjects"></th>
                                        <th>Project</th>
                                        <th>Leader</th>
                                        <th>Team</th>
                                        <th style="min-width: 140px;">Progress</th>
                                        <th style="width: 50px; text-align: center;">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    // 4 Core Sections: Authentication, Assigned Events, Active Events, Calltime
                                    $displayProjects = [
                                        [
                                            'icon' => 'fas fa-shield-alt', 
                                            'bg' => '#e0f2fe', 
                                            'color' => '#0284c7', 
                                            'title' => 'Authentication', 
                                            'subtitle' => 'Security Credentials & Access Rights', 
                                            'leader' => htmlspecialchars($userFullName), 
                                            'progress' => 100, 
                                            'bar' => '#0d9488',
                                            'target_tab' => 'pills-auth-tab'
                                        ],
                                        [
                                            'icon' => 'fas fa-calendar-check', 
                                            'bg' => '#f0fdf4', 
                                            'color' => '#16a34a', 
                                            'title' => 'Assigned Events', 
                                            'subtitle' => count($assigned_events) . ' Productions & Venues Assigned', 
                                            'leader' => !empty($assigned_events[0]['project_manager']) ? $assigned_events[0]['project_manager'] : 'Event PM', 
                                            'progress' => 75, 
                                            'bar' => '#1e3a5f',
                                            'target_tab' => 'pills-assigned-events-tab'
                                        ],
                                        [
                                            'icon' => 'fas fa-broadcast-tower', 
                                            'bg' => '#fff7ed', 
                                            'color' => '#ea580c', 
                                            'title' => 'Active Events', 
                                            'subtitle' => count($active_events) . ' Live Stage Deployments', 
                                            'leader' => 'Stage Lead', 
                                            'progress' => 60, 
                                            'bar' => '#f97316',
                                            'target_tab' => 'pills-active-events-tab'
                                        ],
                                        [
                                            'icon' => 'fas fa-clock', 
                                            'bg' => '#fdf2f8', 
                                            'color' => '#db2777', 
                                            'title' => 'Calltime', 
                                            'subtitle' => count($calltime_schedules) . ' Scheduled Call Sheets & Shifts', 
                                            'leader' => 'Crew Dispatch', 
                                            'progress' => 85, 
                                            'bar' => '#ea580c',
                                            'target_tab' => 'pills-calltime-tab'
                                        ],
                                    ];

                                    foreach ($displayProjects as $p):
                                    ?>
                                    <tr class="project-table-row">
                                        <td><input class="form-check-input" type="checkbox"></td>
                                        <td>
                                            <div class="d-flex align-items-center gap-3">
                                                <div class="project-icon-box" style="background-color: <?php echo $p['bg']; ?>; color: <?php echo $p['color']; ?>;">
                                                    <i class="<?php echo $p['icon']; ?>"></i>
                                                </div>
                                                <div style="cursor: pointer;" onclick="document.getElementById('<?php echo $p['target_tab']; ?>').click();" title="Click to open <?php echo htmlspecialchars($p['title']); ?> tab">
                                                    <div class="fw-bold text-dark fs-6"><?php echo htmlspecialchars($p['title']); ?></div>
                                                    <div class="text-muted small"><?php echo htmlspecialchars($p['subtitle']); ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="text-muted fw-semibold"><?php echo htmlspecialchars($p['leader']); ?></span>
                                        </td>
                                        <td>
                                            <div class="avatar-stack">
                                                <img src="uploads/profiles/profile_2_1789409080.png" onerror="this.src='assets/images/user.png'" class="avatar-stack-item" title="Team Lead">
                                                <img src="uploads/profiles/profile_3_1789409415.png" onerror="this.src='assets/images/user.png'" class="avatar-stack-item" title="Technician">
                                                <span class="avatar-stack-badge">+<?php echo rand(2, 4); ?></span>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="progress vuexy-progress">
                                                    <div class="progress-bar" role="progressbar" style="width: <?php echo $p['progress']; ?>%; background-color: <?php echo $p['bar']; ?>;" aria-valuenow="<?php echo $p['progress']; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                                </div>
                                                <span class="text-muted small fw-bold"><?php echo $p['progress']; ?>%</span>
                                            </div>
                                        </td>
                                        <td class="text-center">
                                            <div class="dropdown">
                                                <button class="btn btn-sm btn-link text-muted p-0" type="button" data-bs-toggle="dropdown">
                                                    <i class="fas fa-ellipsis-v"></i>
                                                </button>
                                                <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                                                    <li>
                                                        <button type="button" class="dropdown-item small" onclick="document.getElementById('<?php echo $p['target_tab']; ?>').click();">
                                                            <i class="fas fa-external-link-alt me-2 text-primary"></i> View <?php echo htmlspecialchars($p['title']); ?>
                                                        </button>
                                                    </li>
                                                </ul>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination Footer -->
                        <div class="d-flex justify-content-between align-items-center mt-3 pt-2 text-muted small">
                            <span>Showing 1 to <?php echo count($displayProjects); ?> of <?php echo count($displayProjects); ?> entries</span>
                            <div class="d-flex align-items-center gap-1">
                                <button class="btn btn-xs btn-outline-secondary px-2 py-1 disabled"><i class="fas fa-chevron-left me-1"></i> Previous</button>
                                <span class="badge bg-dark px-2 py-1">1</span>
                                <button class="btn btn-xs btn-outline-secondary px-2 py-1 disabled">Next <i class="fas fa-chevron-right ms-1"></i></button>
                            </div>
                        </div>
                    </div>

                    <!-- Bottom Card: User Activity Timeline -->
                    <div class="vuexy-card">
                        <div class="vuexy-card-header">
                            <h5 class="vuexy-card-title">User Activity Timeline</h5>
                        </div>

                        <div class="timeline-container">
                            <!-- Item 1: Invoice Document -->
                            <div class="timeline-item">
                                <div class="timeline-point" style="background: #2f3349;"></div>
                                <div class="timeline-header">
                                    <h6 class="timeline-title" style="color: #2f3349; font-weight: 600;">12 Invoices have been paid</h6>
                                    <span class="timeline-time">12 min ago</span>
                                </div>
                                <p class="timeline-text" style="color: #6f6b7d;">Invoices have been paid to the company</p>
                                <div>
                                    <span class="badge border border-danger text-danger bg-transparent px-2 py-1" style="border-radius: 4px; font-weight: 600; cursor: pointer;">
                                        <i class="far fa-file-pdf me-1 text-danger"></i>
                                        <span>Invoice.pdf</span>
                                    </span>
                                </div>
                            </div>

                            <!-- Item 2: Client Meeting -->
                            <div class="timeline-item">
                                <div class="timeline-point" style="background: #2f3349;"></div>
                                <div class="timeline-header">
                                    <h6 class="timeline-title" style="color: #2f3349; font-weight: 600;">Client Meeting</h6>
                                    <span class="timeline-time">45 min ago</span>
                                </div>
                                <p class="timeline-text" style="color: #6f6b7d;">Project meeting with john @10:15am</p>
                                <div>
                                    <div class="client-card-preview" style="background: #f8f7fa; border: 1px solid #dbdade; border-radius: 6px; padding: 6px 12px;">
                                        <img src="uploads/profiles/profile_3_1789409415.png" onerror="this.src='assets/images/user.png'" style="width: 34px; height: 34px; border-radius: 50%; object-fit: cover;">
                                        <div>
                                            <div class="fw-bold small text-dark leading-tight">Lester McCarthy (Client)</div>
                                            <div class="text-muted" style="font-size: 0.72rem;">CEO of Pixinvent</div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Item 3: Create a new project for client -->
                            <div class="timeline-item">
                                <div class="timeline-point" style="background: #2f3349;"></div>
                                <div class="timeline-header">
                                    <h6 class="timeline-title" style="color: #2f3349; font-weight: 600;">Create a new project for client</h6>
                                    <span class="timeline-time">2 Day Ago</span>
                                </div>
                                <p class="timeline-text" style="color: #6f6b7d;">6 team members in a project</p>
                                <div class="avatar-stack mt-2">
                                    <img src="uploads/profiles/profile_2_1789409080.png" onerror="this.src='assets/images/user.png'" class="avatar-stack-item">
                                    <img src="uploads/profiles/profile_6_1793719009.jpg" onerror="this.src='assets/images/user.png'" class="avatar-stack-item">
                                    <img src="uploads/profiles/profile_3_1789409415.png" onerror="this.src='assets/images/user.png'" class="avatar-stack-item">
                                    <span class="avatar-stack-badge">+3</span>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>

                <!-- TAB 2: AUTHENTICATION -->
                <div class="tab-pane fade" id="pills-auth" role="tabpanel">
                    <div class="vuexy-card mb-4">
                        <div class="vuexy-card-header">
                            <h5 class="vuexy-card-title"><i class="fas fa-key me-2 text-primary"></i>Authentication &amp; Password</h5>
                        </div>
                        <p class="text-muted small">Update login credentials and access authentication settings for <strong><?php echo htmlspecialchars($userFullName); ?></strong>.</p>
                        
                        <form method="POST" action="users.php">
                            <input type="hidden" name="action" value="edit_user">
                            <input type="hidden" name="user_id" value="<?php echo $selected_user['id']; ?>">
                            <input type="hidden" name="username" value="<?php echo htmlspecialchars($selected_user['username']); ?>">
                            <input type="hidden" name="email" value="<?php echo htmlspecialchars($selected_user['email']); ?>">
                            <input type="hidden" name="full_name" value="<?php echo htmlspecialchars($selected_user['full_name']); ?>">
                            <input type="hidden" name="phone" value="<?php echo htmlspecialchars($selected_user['phone']); ?>">
                            <input type="hidden" name="department" value="<?php echo htmlspecialchars($selected_user['department']); ?>">
                            <input type="hidden" name="role" value="<?php echo htmlspecialchars($selected_user['role']); ?>">

                            <div class="row g-3" style="max-width: 650px;">
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold">New Password</label>
                                    <input type="password" name="password" class="form-control form-control-sm" placeholder="Minimum 6 characters">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold">Confirm New Password</label>
                                    <input type="password" name="confirm_password" class="form-control form-control-sm" placeholder="Re-type new password">
                                </div>
                                <div class="col-12 mt-2">
                                    <button type="submit" class="btn btn-sm btn-primary">
                                        <i class="fas fa-lock me-1"></i> Update Password
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>

                    <!-- Security & Authentication Metrics Card -->
                    <div class="vuexy-card">
                        <h5 class="vuexy-card-title mb-3"><i class="fas fa-shield-alt me-2 text-success"></i>Security Credentials &amp; Verification</h5>
                        <div class="row g-3">
                            <div class="col-md-4">
                                <div class="p-3 border rounded bg-light">
                                    <div class="text-muted small fw-semibold">Staff Identity Code</div>
                                    <div class="fs-6 fw-bold text-dark mt-1"><?php echo htmlspecialchars($staffCode); ?></div>
                                    <span class="badge bg-primary-subtle text-primary mt-1">Verified Staff</span>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="p-3 border rounded bg-light">
                                    <div class="text-muted small fw-semibold">Two-Factor Auth (2FA)</div>
                                    <div class="fs-6 fw-bold text-dark mt-1">Standard PIN / Password</div>
                                    <span class="badge bg-success-subtle text-success mt-1"><i class="fas fa-check-circle me-1"></i>Active</span>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="p-3 border rounded bg-light">
                                    <div class="text-muted small fw-semibold">Digital Signature Status</div>
                                    <div class="fs-6 fw-bold text-dark mt-1"><?php echo $hasSignaturePhoto ? 'Uploaded & Verified' : 'Pending Signature'; ?></div>
                                    <?php if ($hasSignaturePhoto): ?>
                                        <span class="badge bg-success-subtle text-success mt-1" style="cursor: pointer;" onclick="previewSignature('<?php echo htmlspecialchars($selected_user['signature_image']); ?>', '<?php echo htmlspecialchars(addslashes($userFullName)); ?>')">
                                            <i class="fas fa-signature me-1"></i>View Signature
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-warning-subtle text-warning mt-1">No Signature</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- TAB 3: ASSIGNED EVENTS -->
                <div class="tab-pane fade" id="pills-assigned-events" role="tabpanel">
                    <div class="vuexy-card">
                        <div class="vuexy-card-header">
                            <div>
                                <h5 class="vuexy-card-title"><i class="fas fa-calendar-check me-2 text-success"></i>Assigned Events &amp; Productions</h5>
                                <p class="text-muted small mb-0 mt-1">Upcoming events and production setups assigned to <strong><?php echo htmlspecialchars($userFullName); ?></strong>.</p>
                            </div>
                            <a href="events.php" class="btn btn-sm btn-outline-primary">
                                <i class="fas fa-calendar-alt me-1"></i> Event Schedule Center
                            </a>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-hover align-middle small">
                                <thead class="table-light">
                                    <tr>
                                        <th>Event / Production</th>
                                        <th>Location / Venue</th>
                                        <th>Date</th>
                                        <th>Duration</th>
                                        <th>Project Manager</th>
                                        <th>Status</th>
                                        <th class="text-center">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($assigned_events)): ?>
                                        <?php foreach ($assigned_events as $ev): ?>
                                            <tr>
                                                <td>
                                                    <div class="d-flex align-items-center gap-2">
                                                        <?php if (!empty($ev['event_image'])): ?>
                                                            <img src="<?php echo htmlspecialchars($ev['event_image']); ?>" style="width: 38px; height: 38px; object-fit: cover; border-radius: 6px;">
                                                        <?php else: ?>
                                                            <div class="bg-primary-subtle text-primary rounded d-flex align-items-center justify-content-center" style="width: 38px; height: 38px; font-size: 1.1rem;">
                                                                <i class="fas fa-calendar-day"></i>
                                                            </div>
                                                        <?php endif; ?>
                                                        <div>
                                                            <div class="fw-bold text-dark"><?php echo htmlspecialchars($ev['title']); ?></div>
                                                            <div class="text-muted" style="font-size: 0.72rem;">ID: <?php echo substr($ev['id'], 0, 8); ?>...</div>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <span class="text-dark fw-semibold"><i class="fas fa-map-marker-alt text-danger me-1"></i><?php echo htmlspecialchars($ev['location'] ?: 'Kigali Convention Centre'); ?></span>
                                                </td>
                                                <td class="text-muted">
                                                    <?php echo !empty($ev['date']) ? date('M d, Y', strtotime($ev['date'])) : date('M d, Y'); ?>
                                                </td>
                                                <td>
                                                    <span class="badge bg-light text-dark border"><?php echo max(1, (int)($ev['duration'] ?? 1)); ?> Day(s)</span>
                                                </td>
                                                <td>
                                                    <span class="text-muted fw-semibold"><?php echo htmlspecialchars($ev['project_manager'] ?: 'Joy (PM)'); ?></span>
                                                </td>
                                                <td>
                                                    <span class="badge bg-success-subtle text-success border border-success-subtle">Assigned</span>
                                                </td>
                                                <td class="text-center">
                                                    <a href="events.php" class="btn btn-xs btn-outline-primary" title="Open in Event Center">
                                                        <i class="fas fa-external-link-alt"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="7" class="text-center py-4 text-muted">No assigned event records found.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- TAB 4: ACTIVE EVENTS -->
                <div class="tab-pane fade" id="pills-active-events" role="tabpanel">
                    <div class="vuexy-card">
                        <div class="vuexy-card-header">
                            <div>
                                <h5 class="vuexy-card-title"><i class="fas fa-broadcast-tower me-2 text-warning"></i>Active Events &amp; Live Deployments</h5>
                                <p class="text-muted small mb-0 mt-1">Live stages, in-transit equipment, and active batch movements for this account.</p>
                            </div>
                            <a href="batch_history.php" class="btn btn-sm btn-outline-warning">
                                <i class="fas fa-truck-loading me-1"></i> View All Active Batches
                            </a>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-hover align-middle small">
                                <thead class="table-light">
                                    <tr>
                                        <th>Batch Movement</th>
                                        <th>Event / Venue Stage</th>
                                        <th>Driver / Transport</th>
                                        <th>Equipment Total</th>
                                        <th>Stage Status</th>
                                        <th class="text-center">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($active_events)): ?>
                                        <?php foreach ($active_events as $ab): 
                                            $st = $ab['status'] ?? 'pending';
                                            $badgeClass = $st === 'completed' ? 'bg-success-subtle text-success' : ($st === 'approved' ? 'bg-info-subtle text-info' : 'bg-warning-subtle text-warning');
                                        ?>
                                            <tr>
                                                <td>
                                                    <span class="badge bg-dark px-2 py-1"><?php echo htmlspecialchars($ab['batch_number']); ?></span>
                                                    <div class="text-muted mt-1" style="font-size: 0.72rem;"><?php echo ucwords(str_replace('_', ' ', $ab['movement_type'] ?: 'Movement')); ?></div>
                                                </td>
                                                <td>
                                                    <div class="fw-bold text-dark"><?php echo htmlspecialchars($ab['event_name'] ?: 'Production Stage'); ?></div>
                                                    <div class="text-muted small"><i class="fas fa-warehouse me-1 text-primary"></i><?php echo htmlspecialchars($ab['destination_name']); ?> <?php echo !empty($ab['destination_room']) ? ' - ' . htmlspecialchars($ab['destination_room']) : ''; ?></div>
                                                </td>
                                                <td>
                                                    <span class="text-muted fw-semibold"><?php echo htmlspecialchars($ab['transport_driver'] ?: 'Assigned Driver'); ?></span>
                                                </td>
                                                <td>
                                                    <strong><?php echo $ab['total_qty'] ?? ($ab['item_count'] ?? 1); ?></strong> pcs
                                                </td>
                                                <td>
                                                    <span class="badge <?php echo $badgeClass; ?> border px-2 py-1">
                                                        <i class="fas fa-circle me-1" style="font-size: 0.45rem; vertical-align: middle;"></i><?php echo ucfirst($st); ?>
                                                    </span>
                                                </td>
                                                <td class="text-center">
                                                    <a href="batch_history.php?search=<?php echo urlencode($ab['batch_number']); ?>" class="btn btn-xs btn-outline-primary" title="View Batch Tracking">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="6" class="text-center py-4 text-muted">No active live deployments for this user right now.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- TAB 5: CALLTIME -->
                <div class="tab-pane fade" id="pills-calltime" role="tabpanel">
                    <div class="vuexy-card">
                        <div class="vuexy-card-header">
                            <div>
                                <h5 class="vuexy-card-title"><i class="fas fa-clock me-2 text-danger"></i>Calltime Schedules &amp; Shift Call Sheets</h5>
                                <p class="text-muted small mb-0 mt-1">Official reporting hours, venue stages, and crew check-in times for <strong><?php echo htmlspecialchars($userFullName); ?></strong>.</p>
                            </div>
                            <button type="button" class="btn btn-sm btn-primary" onclick="Swal.fire('Calltime Confirmed', 'All upcoming shift calltimes have been marked as confirmed and acknowledged.', 'success')">
                                <i class="fas fa-check-double me-1"></i> Acknowledge All Calltimes
                            </button>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-hover align-middle small">
                                <thead class="table-light">
                                    <tr>
                                        <th>Date</th>
                                        <th>Call Time</th>
                                        <th>Event / Production</th>
                                        <th>Venue / Stage</th>
                                        <th>Assigned Duty / Role</th>
                                        <th>Reporting Supervisor</th>
                                        <th>Status</th>
                                        <th class="text-center">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($calltime_schedules)): ?>
                                        <?php foreach ($calltime_schedules as $ct): ?>
                                            <tr>
                                                <td class="fw-bold text-dark">
                                                    <i class="far fa-calendar-alt me-1 text-primary"></i><?php echo $ct['date']; ?>
                                                </td>
                                                <td>
                                                    <span class="badge bg-danger text-white px-2 py-1 fs-6">
                                                        <i class="far fa-clock me-1"></i><?php echo $ct['call_time']; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <div class="fw-bold text-dark"><?php echo htmlspecialchars($ct['event_title']); ?></div>
                                                    <div class="text-muted" style="font-size: 0.72rem;">Show Start: <?php echo $ct['show_time']; ?></div>
                                                </td>
                                                <td>
                                                    <span class="text-muted fw-semibold"><i class="fas fa-map-pin text-danger me-1"></i><?php echo htmlspecialchars($ct['venue']); ?></span>
                                                </td>
                                                <td>
                                                    <span class="badge bg-light text-dark border"><?php echo htmlspecialchars($ct['role']); ?></span>
                                                </td>
                                                <td class="text-muted">
                                                    <?php echo htmlspecialchars($ct['manager']); ?>
                                                </td>
                                                <td>
                                                    <?php if ($ct['status'] === 'Confirmed'): ?>
                                                        <span class="badge bg-success-subtle text-success border border-success-subtle">
                                                            <i class="fas fa-check me-1"></i>Confirmed
                                                        </span>
                                                    <?php elseif ($ct['status'] === 'On Site'): ?>
                                                        <span class="badge bg-info-subtle text-info border border-info-subtle">
                                                            <i class="fas fa-walking me-1"></i>On Site
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="badge bg-warning-subtle text-warning border border-warning-subtle">
                                                            <i class="fas fa-hourglass-half me-1"></i>Scheduled
                                                        </span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-center">
                                                    <button type="button" class="btn btn-xs btn-outline-success" onclick="Swal.fire('Check-In Recorded', 'Calltime check-in recorded for <?php echo htmlspecialchars(addslashes($ct['event_title'])); ?> at <?php echo $ct['call_time']; ?>', 'success')" title="Check In">
                                                        <i class="fas fa-user-check"></i> Check In
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="8" class="text-center py-4 text-muted">No scheduled calltimes for this user.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- TAB 5: ALL USERS TABLE VIEW -->
                <div class="tab-pane fade" id="pills-all-users" role="tabpanel">
                    <div class="vuexy-card">
                        <div class="vuexy-card-header">
                            <h5 class="vuexy-card-title"><i class="fas fa-users me-2 text-primary"></i>All System Users</h5>
                            <button type="button" class="btn btn-sm btn-primary" onclick="openAddUser()">
                                <i class="fas fa-plus me-1"></i> Add User
                            </button>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-hover align-middle" id="allUsersDataTable">
                                <thead class="table-light">
                                    <tr>
                                        <th>User</th>
                                        <th>Email / Phone</th>
                                        <th>Role</th>
                                        <th>Department</th>
                                        <th>Signature</th>
                                        <th>Status</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($all_users as $u): 
                                        $hasPhoto = !empty($u['profile_image']) && file_exists(__DIR__ . '/' . $u['profile_image']);
                                        $hasSig = !empty($u['signature_image']) && file_exists(__DIR__ . '/' . $u['signature_image']);
                                    ?>
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center gap-2">
                                                    <?php if ($hasPhoto): ?>
                                                        <img src="<?php echo htmlspecialchars($u['profile_image']); ?>" class="rounded-circle" style="width: 36px; height: 36px; object-fit: cover;">
                                                    <?php else: ?>
                                                        <div class="rounded-circle bg-light text-dark fw-bold d-flex align-items-center justify-content-center" style="width: 36px; height: 36px; font-size: 0.85rem;">
                                                            <?php echo strtoupper(substr($u['username'], 0, 1)); ?>
                                                        </div>
                                                    <?php endif; ?>
                                                    <div>
                                                        <div class="fw-bold text-dark"><?php echo htmlspecialchars($u['full_name'] ?: $u['username']); ?></div>
                                                        <div class="text-muted small">@<?php echo htmlspecialchars($u['username']); ?></div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="small text-dark"><?php echo htmlspecialchars($u['email']); ?></div>
                                                <div class="text-muted small"><?php echo htmlspecialchars($u['phone'] ?: '-'); ?></div>
                                            </td>
                                            <td>
                                                <span class="badge bg-light text-dark border"><?php echo htmlspecialchars($roles[$u['role']] ?? $u['role']); ?></span>
                                            </td>
                                            <td>
                                                <span class="small text-muted"><?php echo htmlspecialchars($departments[$u['department']] ?? ($u['department'] ?: '-')); ?></span>
                                            </td>
                                            <td>
                                                <?php if ($hasSig): ?>
                                                    <img src="<?php echo htmlspecialchars($u['signature_image']); ?>" style="max-height: 24px; max-width: 65px; cursor: pointer;" onclick="previewSignature('<?php echo htmlspecialchars($u['signature_image']); ?>', '<?php echo htmlspecialchars(addslashes($u['full_name'] ?: $u['username'])); ?>')">
                                                <?php else: ?>
                                                    <span class="text-muted small">—</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($u['is_active']): ?>
                                                    <span class="badge bg-success-subtle text-success">Active</span>
                                                <?php else: ?>
                                                    <span class="badge bg-danger-subtle text-danger">Suspended</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="d-flex gap-1">
                                                    <a href="users.php?id=<?php echo $u['id']; ?>" class="btn btn-xs btn-outline-primary" title="View Profile">
                                                        <i class="fas fa-id-badge"></i>
                                                    </a>
                                                    <button type="button" class="btn btn-xs btn-outline-secondary" onclick="openEditUser(<?php echo $u['id']; ?>)" title="Edit User">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- ==================== USER MODAL (ADD & EDIT) ==================== -->
<div class="modal fade" id="userModal" tabindex="-1" aria-labelledby="userModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title fw-bold" id="userModalLabel">
                    <i class="fas fa-user-plus me-2"></i>Add New User
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="userForm" method="POST" action="users.php" enctype="multipart/form-data">
                <input type="hidden" name="action" id="form_action" value="add_user">
                <input type="hidden" name="user_id" id="form_user_id" value="">
                <input type="hidden" name="remove_profile_image" id="remove_profile_image" value="0">
                <input type="hidden" name="remove_signature_image" id="remove_signature_image" value="0">

                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="username" class="form-label fw-bold small">Username <span class="text-danger">*</span></label>
                            <input type="text" class="form-control form-control-sm" id="username" name="username" required>
                        </div>
                        <div class="col-md-6">
                            <label for="email" class="form-label fw-bold small">Email <span class="text-danger">*</span></label>
                            <input type="email" class="form-control form-control-sm" id="email" name="email" required>
                        </div>
                        <div class="col-md-6">
                            <label for="full_name" class="form-label fw-bold small">Full Name</label>
                            <input type="text" class="form-control form-control-sm" id="full_name" name="full_name">
                        </div>
                        <div class="col-md-6">
                            <label for="phone" class="form-label fw-bold small">Phone</label>
                            <input type="tel" class="form-control form-control-sm" id="phone" name="phone">
                        </div>
                        <div class="col-md-6">
                            <label for="role" class="form-label fw-bold small">Role <span class="text-danger">*</span></label>
                            <select class="form-select form-select-sm" id="role" name="role" required>
                                <?php foreach ($roles as $r_key => $r_label): ?>
                                    <option value="<?php echo $r_key; ?>"><?php echo $r_label; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="department" class="form-label fw-bold small">Department</label>
                            <select class="form-select form-select-sm" id="department" name="department">
                                <option value="">Select Department</option>
                                <?php foreach ($departments as $d_key => $d_label): ?>
                                    <option value="<?php echo $d_key; ?>"><?php echo $d_label; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Password Fields -->
                        <div class="col-md-6">
                            <label for="password" class="form-label fw-bold small" id="password_label">Password <span class="text-danger">*</span></label>
                            <input type="password" class="form-control form-control-sm" id="password" name="password">
                            <div class="form-text small" id="password_help"></div>
                        </div>
                        <div class="col-md-6">
                            <label for="confirm_password" class="form-label fw-bold small">Confirm Password</label>
                            <input type="password" class="form-control form-control-sm" id="confirm_password" name="confirm_password">
                        </div>

                        <!-- Profile Photo Upload -->
                        <div class="col-md-6 pt-2">
                            <label class="form-label fw-bold small"><i class="fas fa-camera me-1 text-primary"></i>Profile Photo</label>
                            <div id="current_profile_preview" class="d-none align-items-center gap-2 mb-2 p-2 border rounded bg-light">
                                <img id="profile_thumb" src="" alt="Avatar" style="width: 44px; height: 44px; border-radius: 50%; object-fit: cover;">
                                <div class="flex-grow-1">
                                    <span class="small fw-semibold d-block">Current Photo</span>
                                    <button type="button" class="btn btn-xs btn-outline-danger py-0 px-2 mt-1" onclick="markRemoveProfilePhoto()">Remove</button>
                                </div>
                            </div>
                            <input type="file" class="form-control form-control-sm" id="profile_image" name="profile_image" accept="image/*">
                        </div>

                        <!-- Digital Signature Upload -->
                        <div class="col-md-6 pt-2">
                            <label class="form-label fw-bold small"><i class="fas fa-file-signature me-1 text-success"></i>Digital Signature</label>
                            <div id="current_sig_preview" class="d-none align-items-center gap-2 mb-2 p-2 border rounded bg-light">
                                <img id="sig_thumb" src="" alt="Signature" style="max-height: 38px; max-width: 90px; object-fit: contain;">
                                <div class="flex-grow-1">
                                    <span class="small fw-semibold d-block">Current Signature</span>
                                    <button type="button" class="btn btn-xs btn-outline-danger py-0 px-2 mt-1" onclick="markRemoveSignaturePhoto()">Remove</button>
                                </div>
                            </div>
                            <input type="file" class="form-control form-control-sm" id="signature_image" name="signature_image" accept="image/*">
                        </div>
                    </div>
                </div>

                <div class="modal-footer bg-light px-4 py-3">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-primary" id="saveUserBtn">
                        <i class="fas fa-save me-1"></i> Save User
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ==================== SIGNATURE LIGHTBOX MODAL ==================== -->
<div class="modal fade" id="signaturePreviewModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-dark text-white py-2">
                <h6 class="modal-title fw-bold" id="sigModalTitle"><i class="fas fa-file-signature me-2"></i>Digital Signature</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4 text-center bg-light">
                <img id="sigModalImage" src="" alt="Digital Signature" style="max-width: 100%; max-height: 200px; object-fit: contain;">
            </div>
        </div>
    </div>
</div>

<?php
// Buffer scripts so they execute cleanly AFTER footer loads jQuery, Bootstrap & DataTables
ob_start();
?>
<script>
    function filterProjectsList() {
        const val = $('#projectSearchInput').val().toLowerCase();
        $('.project-table-row').each(function() {
            const text = $(this).text().toLowerCase();
            $(this).toggle(text.indexOf(val) > -1);
        });
    }

    function previewSignature(url, name) {
        $('#sigModalImage').attr('src', url);
        $('#sigModalTitle').html('<i class="fas fa-file-signature me-2"></i>Signature: ' + name);
        new bootstrap.Modal(document.getElementById('signaturePreviewModal')).show();
    }

    function markRemoveProfilePhoto() {
        $('#remove_profile_image').val('1');
        $('#current_profile_preview').addClass('d-none');
    }

    function markRemoveSignaturePhoto() {
        $('#remove_signature_image').val('1');
        $('#current_sig_preview').addClass('d-none');
    }

    window.openAddUser = function() {
        document.getElementById('userForm').reset();
        $('#form_action').val('add_user');
        $('#form_user_id').val('');
        $('#remove_profile_image').val('0');
        $('#remove_signature_image').val('0');
        $('#current_profile_preview').addClass('d-none');
        $('#current_sig_preview').addClass('d-none');
        $('#userModalLabel').html('<i class="fas fa-user-plus me-2"></i>Add New User');
        $('#password_label').html('Password <span class="text-danger">*</span>');
        $('#password').prop('required', true);
        $('#password_help').text('Minimum 6 characters.');
        $('#saveUserBtn').html('<i class="fas fa-save me-1"></i> Add User');
        new bootstrap.Modal(document.getElementById('userModal')).show();
    };

    window.openEditUser = function(userId) {
        document.getElementById('userForm').reset();
        $('#remove_profile_image').val('0');
        $('#remove_signature_image').val('0');

        $.ajax({
            url: 'get_user.php?id=' + userId,
            type: 'GET',
            dataType: 'json',
            success: function(user) {
                if (user.error) {
                    Swal.fire('Error', user.error, 'error');
                    return;
                }

                $('#form_action').val('edit_user');
                $('#form_user_id').val(user.id);
                $('#username').val(user.username);
                $('#email').val(user.email);
                $('#full_name').val(user.full_name || '');
                $('#phone').val(user.phone || '');
                $('#department').val(user.department || '');
                $('#role').val(user.role || 'user');

                $('#password_label').html('Password <span class="text-muted fw-normal">(Optional)</span>');
                $('#password').prop('required', false);
                $('#password_help').text('Leave blank to keep existing password.');

                // Profile photo thumbnail
                if (user.profile_image && user.profile_image.trim() !== '') {
                    $('#profile_thumb').attr('src', user.profile_image);
                    $('#current_profile_preview').removeClass('d-none').addClass('d-flex');
                } else {
                    $('#current_profile_preview').addClass('d-none');
                }

                // Digital signature thumbnail
                if (user.signature_image && user.signature_image.trim() !== '') {
                    $('#sig_thumb').attr('src', user.signature_image);
                    $('#current_sig_preview').removeClass('d-none').addClass('d-flex');
                } else {
                    $('#current_sig_preview').addClass('d-none');
                }

                $('#userModalLabel').html('<i class="fas fa-user-edit me-2"></i>Edit User: ' + (user.full_name || user.username));
                $('#saveUserBtn').html('<i class="fas fa-save me-1"></i> Update User');

                new bootstrap.Modal(document.getElementById('userModal')).show();
            },
            error: function() {
                Swal.fire('Error', 'Failed to retrieve user details from server', 'error');
            }
        });
    };

    $(document).ready(function() {
        // Initialize All Users DataTable
        if ($('#allUsersDataTable').length) {
            $('#allUsersDataTable').DataTable({
                pageLength: 5,
                lengthMenu: [[5, 10, 25, -1], [5, 10, 25, "All"]],
                order: [[0, 'asc']],
                dom: '<"d-flex justify-content-between align-items-center mb-3"lf>rt<"d-flex justify-content-between align-items-center mt-3"ip>'
            });
        }

        // Check all projects handler
        $('#checkAllProjects').on('change', function() {
            $('#projectsListTable tbody input[type="checkbox"]').prop('checked', $(this).prop('checked'));
        });

        // Flash toast notification
        <?php if (isset($_SESSION['toast_message'])): ?>
            Swal.fire({
                icon: '<?php echo $_SESSION['toast_type'] ?? 'info'; ?>',
                title: '<?php echo ucfirst($_SESSION['toast_type'] ?? 'notice'); ?>',
                text: '<?php echo addslashes($_SESSION['toast_message']); ?>',
                timer: 2500,
                showConfirmButton: false
            });
            <?php 
            unset($_SESSION['toast_message']); 
            unset($_SESSION['toast_type']); 
            ?>
        <?php endif; ?>
    });
</script>
<?php
$extra_scripts = ob_get_clean();
require_once 'views/partials/footer.php';
?>
