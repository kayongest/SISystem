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
    $is_ajax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') || !empty($_POST['ajax']);

    // Handle Add Role
    if ($action === 'add_role') {
        $role_name = trim($_POST['role_name'] ?? '');
        $role_key = trim($_POST['role_key'] ?? '');
        $description = trim($_POST['description'] ?? '');

        if (empty($role_key) && !empty($role_name)) {
            $role_key = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '_', trim($role_name)));
            $role_key = trim($role_key, '_');
        }

        if (empty($role_name) || empty($role_key)) {
            if ($is_ajax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Role name and system key are required']);
                exit();
            }
            $_SESSION['toast_message'] = 'Role name and system key are required';
            $_SESSION['toast_type'] = 'error';
        } else {
            try {
                $stmt = $conn->prepare("INSERT INTO `roles` (`role_key`, `role_name`, `description`, `is_active`) VALUES (?, ?, ?, 1) ON DUPLICATE KEY UPDATE `role_name` = VALUES(`role_name`), `description` = VALUES(`description`), `is_active` = 1");
                $stmt->bind_param("sss", $role_key, $role_name, $description);
                if ($stmt->execute()) {
                    if ($is_ajax) {
                        header('Content-Type: application/json');
                        echo json_encode(['success' => true, 'message' => "Role '{$role_name}' added successfully!", 'role_key' => $role_key, 'role_name' => $role_name]);
                        exit();
                    }
                    $_SESSION['toast_message'] = "Role '{$role_name}' added successfully!";
                    $_SESSION['toast_type'] = 'success';
                } else {
                    if ($is_ajax) {
                        header('Content-Type: application/json');
                        echo json_encode(['success' => false, 'message' => 'Error saving role: ' . $stmt->error]);
                        exit();
                    }
                    $_SESSION['toast_message'] = 'Error saving role: ' . $stmt->error;
                    $_SESSION['toast_type'] = 'error';
                }
                $stmt->close();
            } catch (Exception $e) {
                if ($is_ajax) {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
                    exit();
                }
                $_SESSION['toast_message'] = 'Error: ' . $e->getMessage();
                $_SESSION['toast_type'] = 'error';
            }
        }
        header('Location: users.php' . (isset($_GET['id']) ? '?id=' . (int)$_GET['id'] : ''));
        exit();
    }

    // Handle Add Department
    if ($action === 'add_department') {
        $dept_name = trim($_POST['dept_name'] ?? '');
        $dept_code = strtoupper(trim($_POST['dept_code'] ?? ''));
        $description = trim($_POST['description'] ?? '');

        if (empty($dept_code) && !empty($dept_name)) {
            $dept_code = strtoupper(substr(preg_replace('/[^a-zA-Z0-9]/', '', $dept_name), 0, 5));
        }

        if (empty($dept_name) || empty($dept_code)) {
            if ($is_ajax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Department name and code are required']);
                exit();
            }
            $_SESSION['toast_message'] = 'Department name and code are required';
            $_SESSION['toast_type'] = 'error';
        } else {
            try {
                $stmt = $conn->prepare("INSERT INTO `departments` (`code`, `name`, `description`, `is_active`) VALUES (?, ?, ?, 1) ON DUPLICATE KEY UPDATE `name` = VALUES(`name`), `description` = VALUES(`description`), `is_active` = 1");
                $stmt->bind_param("sss", $dept_code, $dept_name, $description);
                if ($stmt->execute()) {
                    if ($is_ajax) {
                        header('Content-Type: application/json');
                        echo json_encode(['success' => true, 'message' => "Department '{$dept_name}' added successfully!", 'dept_key' => strtolower($dept_code), 'dept_name' => $dept_name]);
                        exit();
                    }
                    $_SESSION['toast_message'] = "Department '{$dept_name}' added successfully!";
                    $_SESSION['toast_type'] = 'success';
                } else {
                    if ($is_ajax) {
                        header('Content-Type: application/json');
                        echo json_encode(['success' => false, 'message' => 'Error saving department: ' . $stmt->error]);
                        exit();
                    }
                    $_SESSION['toast_message'] = 'Error saving department: ' . $stmt->error;
                    $_SESSION['toast_type'] = 'error';
                }
                $stmt->close();
            } catch (Exception $e) {
                if ($is_ajax) {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
                    exit();
                }
                $_SESSION['toast_message'] = 'Error: ' . $e->getMessage();
                $_SESSION['toast_type'] = 'error';
            }
        }
        header('Location: users.php' . (isset($_GET['id']) ? '?id=' . (int)$_GET['id'] : ''));
        exit();
    }

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
$roleListDetails = [];
try {
    $rRes = $conn->query("SELECT id, role_key, role_name, description FROM roles WHERE is_active = 1 ORDER BY id ASC");
    if ($rRes) {
        while ($rRow = $rRes->fetch_assoc()) {
            $roles[$rRow['role_key']] = $rRow['role_name'];
            $roleListDetails[] = $rRow;
        }
    }
} catch (Exception $e) {
    error_log("Error loading roles: " . $e->getMessage());
}

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
$deptListDetails = [];
try {
    $dRes = $conn->query("SELECT id, code, name, description FROM departments WHERE is_active = 1 ORDER BY name ASC");
    if ($dRes) {
        while ($dRow = $dRes->fetch_assoc()) {
            $dKey = strtolower($dRow['code']);
            $dName = ucwords(strtolower($dRow['name']));
            $departments[$dKey] = $dName;
            $departments[strtolower($dRow['name'])] = $dName;
            $deptListDetails[] = $dRow;
        }
    }
} catch (Exception $e) {
    error_log("Error loading departments: " . $e->getMessage());
}

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
               sm.status, sm.approval_status, sm.transport_driver, sm.created_at, sm.event_name, sm.job_sheet,
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

    // Fetch Assigned Events for this user (matching technician, project manager, or tech lead)
    $assigned_events = [];
    $tokens = array_filter(array_unique(array_merge(
        [$uName, $uFullName],
        explode(' ', $uFullName)
    )), function($t) { return strlen(trim($t)) >= 3; });

    if (!empty($tokens)) {
        $conditions = [];
        $params = [];
        $types = "";

        foreach ($tokens as $t) {
            $like = "%" . trim($t) . "%";
            $conditions[] = "e.assigned_technician LIKE ?";
            $params[] = $like;
            $types .= "s";

            $conditions[] = "e.tech_lead LIKE ?";
            $params[] = $like;
            $types .= "s";

            $conditions[] = "e.project_manager LIKE ?";
            $params[] = $like;
            $types .= "s";
        }

        $sql = "
            SELECT DISTINCT e.*, 
                   (SELECT COUNT(*) FROM stock_movements sm WHERE sm.event_name = e.title) as batch_count
            FROM events e 
            WHERE " . implode(" OR ", $conditions) . "
            ORDER BY COALESCE(e.date, e.created_at) DESC
            LIMIT 15
        ";
        $aeStmt = $conn->prepare($sql);
        if ($aeStmt) {
            $aeStmt->bind_param($types, ...$params);
            $aeStmt->execute();
            $assigned_events = $aeStmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $aeStmt->close();
        }
    }

    // Fetch Active Events & Movements for this user
    $active_events = [];
    $actStmt = $conn->prepare("
        SELECT sm.id, sm.batch_number, sm.movement_type, sm.source_name, sm.destination_name, 
               sm.destination_room, sm.technician_id, sm.submitted_by, sm.stock_controller_name, 
               sm.status, sm.approval_status, sm.transport_driver, sm.created_at, sm.event_name, sm.job_sheet,
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

    // Generate Structured Calltime Schedule for this user strictly from assigned events
    $calltime_schedules = [];
    $sampleTimes = ['07:30 AM', '09:00 AM', '11:15 AM', '14:00 PM', '17:30 PM'];
    $sampleRoles = ['Lead Audio Tech', 'Lighting Programmer', 'Stage Video Operator', 'Rigging Supervisor', 'Backline Specialist'];
    $venues = ['Kigali Convention Centre - Main Auditorium', 'BK Arena - Main Bowl', 'Intare Conference Arena', 'Serena Hotel - Ballroom'];
    
    // Combine strictly from real assigned events
    if (!empty($assigned_events)) {
        foreach ($assigned_events as $k => $ev) {
            $cTime = !empty($ev['call_time']) ? trim($ev['call_time']) : $sampleTimes[$k % count($sampleTimes)];
            if (preg_match('/^(\d{1,2}):(\d{2})$/', $cTime)) {
                $cTime = date('h:i A', strtotime($cTime));
            }

            // Determine role dynamically based on Tech Lead / Department
            $evRole = $sampleRoles[$k % count($sampleRoles)];
            if (!empty($ev['tech_lead']) && (($uFullName && stripos($ev['tech_lead'], $uFullName) !== false) || ($uName && stripos($ev['tech_lead'], $uName) !== false))) {
                $evRole = 'Tech Lead';
            } elseif (!empty($selected_user['department'])) {
                $evRole = ucwords($selected_user['department']) . ' Specialist';
            }

            $calltime_schedules[] = [
                'event_title' => $ev['title'] ?: 'Live Production Stage',
                'venue' => $ev['location'] ?: ($venues[$k % count($venues)]),
                'date' => !empty($ev['date']) ? date('M d, Y', strtotime($ev['date'])) : date('M d, Y', strtotime('+' . ($k + 1) . ' days')),
                'call_time' => $cTime,
                'show_time' => '18:00 PM',
                'role' => $evRole,
                'manager' => $ev['project_manager'] ?: 'Production Lead',
                'status' => ($k === 0) ? 'Confirmed' : (($k === 1) ? 'Scheduled' : 'On Site')
            ];
        }
    } else {
        $calltime_schedules = [];
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

// Initialize ID Badges & Attendance data for selected user
require_once 'includes/badge_attendance_helper.php';
ensureBadgesAndAttendanceTables($conn);
$clearanceMetaMap = getClearanceLevelsMeta();
$selected_user_badge = null;
$selected_user_attendance = [];
$userStockLocations = [];
$userStockMap = [];
$stLocRes = @$conn->query("SELECT id, name, code FROM stock_locations ORDER BY name ASC");
if ($stLocRes) {
    while ($slRow = $stLocRes->fetch_assoc()) {
        $userStockLocations[] = $slRow;
        $userStockMap[(int)$slRow['id']] = $slRow;
    }
}
if ($selected_user) {
    $bStmt = $conn->prepare("SELECT * FROM user_badges WHERE user_id = ? LIMIT 1");
    if ($bStmt) {
        $bStmt->bind_param("i", $selected_user['id']);
        $bStmt->execute();
        $selected_user_badge = $bStmt->get_result()->fetch_assoc();
        $bStmt->close();
    }
    $attStmt = $conn->prepare("SELECT * FROM attendance_logs WHERE user_id = ? ORDER BY scanned_at DESC, id DESC LIMIT 15");
    if ($attStmt) {
        $attStmt->bind_param("i", $selected_user['id']);
        $attStmt->execute();
        $selected_user_attendance = $attStmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $attStmt->close();
    }
}

// Query attendance logs with user details for User Activity Timeline (venues & stock)
$access_timeline_logs = [];
$attLogQuery = "
    SELECT l.id, l.user_id, l.badge_code, l.checkpoint_type, l.checkpoint_id,
           l.checkpoint_name, l.scan_action, l.access_status, l.denial_reason,
           l.punctuality_status, l.notes, l.scanned_at,
           COALESCE(NULLIF(u.full_name, ''), u.username, 'Unknown Individual') AS full_name,
           COALESCE(u.username, '') AS username,
           COALESCE(u.role, 'staff') AS role,
           COALESCE(u.department, 'Operations') AS department,
           u.profile_image
    FROM attendance_logs l
    LEFT JOIN users u ON l.user_id = u.id
    ORDER BY l.scanned_at DESC, l.id DESC
    LIMIT 600
";
$attLogRes = @$conn->query($attLogQuery);
if ($attLogRes) {
    while ($row = $attLogRes->fetch_assoc()) {
        $row['time_ago'] = timeAgoString($row['scanned_at']);
        $row['formatted_time'] = date('M d, Y h:i A', strtotime($row['scanned_at']));
        $access_timeline_logs[] = $row;
    }
}

require_once 'views/partials/header.php';
?>

<!-- DataTables Bootstrap 5, SweetAlert2 & QRCodeJS -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.bootstrap5.min.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>

<!-- Font Awesome 6 -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

<style>
    @import url('https://fonts.googleapis.com/css2?family=Titillium+Web:ital,wght@0,200;0,300;0,400;0,600;0,700;0,900;1,200;1,300;1,400;1,600;1,700&display=swap');

    /* Titillium Web Typography Classes */
    .titillium-web-extralight {
        font-family: "Titillium Web", sans-serif;
        font-weight: 200;
        font-style: normal;
    }

    .titillium-web-light {
        font-family: "Titillium Web", sans-serif;
        font-weight: 300;
        font-style: normal;
    }

    .titillium-web-regular {
        font-family: "Titillium Web", sans-serif;
        font-weight: 400;
        font-style: normal;
    }

    .titillium-web-semibold {
        font-family: "Titillium Web", sans-serif;
        font-weight: 600;
        font-style: normal;
    }

    .titillium-web-bold {
        font-family: "Titillium Web", sans-serif;
        font-weight: 700;
        font-style: normal;
    }

    .titillium-web-black {
        font-family: "Titillium Web", sans-serif;
        font-weight: 900;
        font-style: normal;
    }

    .titillium-web-extralight-italic {
        font-family: "Titillium Web", sans-serif;
        font-weight: 200;
        font-style: italic;
    }

    .titillium-web-light-italic {
        font-family: "Titillium Web", sans-serif;
        font-weight: 300;
        font-style: italic;
    }

    .titillium-web-regular-italic {
        font-family: "Titillium Web", sans-serif;
        font-weight: 400;
        font-style: italic;
    }

    .titillium-web-semibold-italic {
        font-family: "Titillium Web", sans-serif;
        font-weight: 600;
        font-style: italic;
    }

    .titillium-web-bold-italic {
        font-family: "Titillium Web", sans-serif;
        font-weight: 700;
        font-style: italic;
    }

    :root {
        --profile-bg: #f8fafc;
        --card-border: #eef2f6;
        --text-primary: #1e293b;
        --text-muted: #64748b;
        --primary-blue: #0d6efd;
    }

    body,
    .profile-page-wrapper,
    #addUserModal,
    #previewSigModal,
    input,
    button,
    select,
    textarea {
        font-family: "Titillium Web", sans-serif !important;
    }

    /* Protect all Font Awesome icon glyphs from font-family override */
    .fa,
    .fas,
    .far,
    .fal,
    .fab,
    .fad,
    .fa-solid,
    .fa-regular,
    .fa-brands,
    .fa-light,
    .fa-thin,
    .fa-duotone,
    [class^="fa-"],
    [class*=" fa-"] {
        font-family: "Font Awesome 6 Free" !important;
        font-style: normal !important;
        font-variant: normal !important;
        text-rendering: auto !important;
        line-height: 1 !important;
        -webkit-font-smoothing: antialiased;
        -moz-osx-font-smoothing: grayscale;
        display: inline-block;
    }

    .fab,
    .fa-brands {
        font-family: "Font Awesome 6 Brands" !important;
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

    /* User Live Search Dropdown */
    .user-live-search-wrapper {
        min-width: 250px;
        position: relative;
    }
    .user-search-dropdown {
        position: absolute;
        top: calc(100% + 6px);
        left: 0;
        width: 340px;
        max-height: 400px;
        overflow-y: auto;
        background: #ffffff;
        border: 1px solid #e2e8f0;
        z-index: 1060;
        box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.15), 0 8px 10px -6px rgba(0, 0, 0, 0.1);
    }
    .user-search-item {
        padding: 9px 12px;
        display: flex;
        align-items: center;
        gap: 10px;
        cursor: pointer;
        border-bottom: 1px solid #f1f5f9;
        transition: background 0.15s ease;
        text-decoration: none;
        color: inherit;
    }
    .user-search-item:hover, .user-search-item.active {
        background: #f8fafc;
        color: inherit;
    }
    .user-search-item:last-child {
        border-bottom: none;
    }
    .user-search-avatar {
        width: 34px;
        height: 34px;
        border-radius: 50%;
        object-fit: cover;
        flex-shrink: 0;
    }
    .user-search-avatar-fallback {
        width: 34px;
        height: 34px;
        border-radius: 50%;
        background: #e2e8f0;
        color: #1e293b;
        font-weight: 700;
        font-size: 0.85rem;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }
</style>

<div class="profile-page-wrapper">
    <!-- Top Selector Bar -->
    <div class="top-action-bar d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div class="d-flex align-items-center gap-3 flex-wrap">
            <h5 class="fw-bold mb-0 text-dark"><i class="fas fa-users-cog me-2 text-primary"></i>User Management</h5>
            <div class="vr mx-1 d-none d-sm-block" style="height: 24px;"></div>

            <!-- Live User Search Apart from Filter Dropdown -->
            <div class="user-live-search-wrapper">
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-white border-end-0 text-muted" style="border-radius: 8px 0 0 8px;">
                        <i class="fas fa-search text-muted"></i>
                    </span>
                    <input type="text" id="userLiveSearchInput" class="form-control form-control-sm border-start-0 ps-1" placeholder="🔍 Quick search user..." autocomplete="off" style="border-radius: 0 8px 8px 0; font-size: 0.82rem; font-weight: 500;">
                    <button type="button" id="clearUserSearchBtn" class="btn btn-sm btn-link text-muted position-absolute end-0 top-50 translate-middle-y me-1 p-0" style="display: none; z-index: 5;" onclick="clearUserSearch()">
                        <i class="fas fa-times-circle"></i>
                    </button>
                </div>
                <!-- Autocomplete Dropdown -->
                <div id="userSearchResultsDropdown" class="user-search-dropdown shadow-lg rounded-3" style="display: none;"></div>
            </div>

            <!-- Viewing User Filter -->
            <div class="d-flex align-items-center gap-2">
                <span class="small text-muted d-none d-lg-inline fw-semibold">Filter:</span>
                <select class="form-select form-select-sm" style="min-width: 190px; border-radius: 8px; font-weight: 600;" onchange="window.location.href='users.php?id=' + this.value">
                    <?php foreach ($all_users as $uItem): ?>
                        <option value="<?php echo $uItem['id']; ?>" <?php echo $uItem['id'] == $selected_user_id ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($uItem['full_name'] ?: $uItem['username']); ?> (<?php echo htmlspecialchars($roles[$uItem['role']] ?? $uItem['role']); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="d-flex align-items-center gap-2">
            <a href="id_badges.php?user_id=<?php echo (int)$selected_user_id; ?>" class="btn btn-sm btn-outline-success fw-semibold" title="Manage ID Badges, Stock & Venue Access and Attendance">
                <i class="fas fa-id-card-clip me-1"></i> ID Badges &amp; Attendance
            </a>
            <button type="button" class="btn btn-sm btn-outline-dark" onclick="openRolesDeptModal()" title="Manage Roles and Departments">
                <i class="fas fa-tags me-1 text-primary"></i> Roles &amp; Depts
            </button>
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
        $badgeCode = $selected_user_badge['badge_code'] ?? ('ABL-BDG-' . str_pad($selected_user['id'], 4, '0', STR_PAD_LEFT));
        $userClearanceKey = $selected_user_badge['clearance_level'] ?? 'L1_VENUE_BASIC';
        $userClearanceMeta = $clearanceMetaMap[$userClearanceKey] ?? $clearanceMetaMap['L1_VENUE_BASIC'];
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
                    <div class="mt-2">
                        <span class="badge rounded-pill px-2 py-1" style="background: <?php echo $userClearanceMeta['color']; ?>; font-size: 0.7rem; cursor: pointer;" onclick="document.getElementById('pills-id-badge-tab').click();" title="Click to view ID Badge & Access Level">
                            <i class="fas <?php echo $userClearanceMeta['icon']; ?> me-1"></i><?php echo htmlspecialchars($userClearanceMeta['code'] . ' • ' . $badgeCode); ?>
                        </span>
                    </div>
                </div>

                <!-- 2 Metric Stat Boxes -->
                <div class="stats-boxes-row">
                    <div class="stat-mini-box">
                        <div class="stat-mini-icon"><i class="far fa-check-square"></i></div>
                        <div class="stat-mini-number"><?php echo $user_batches_count; ?></div>
                        <div class="stat-mini-label">Batches / Tasks</div>
                    </div>
                    <div class="stat-mini-box">
                        <div class="stat-mini-icon"><i class="fas fa-calendar-check text-primary"></i></div>
                        <div class="stat-mini-number"><?php echo count($assigned_events); ?></div>
                        <div class="stat-mini-label"><?php echo count($assigned_events) === 1 ? 'Assigned Event' : 'Assigned Events'; ?></div>
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
                <div class="mt-2">
                    <button type="button" class="btn btn-sm btn-outline-success w-100 fw-bold" onclick="document.getElementById('pills-id-badge-tab').click();">
                        <i class="fas fa-id-card-clip me-1"></i> View ID Badge &amp; Stock/Venue Access
                    </button>
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
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="pills-id-badge-tab" data-bs-toggle="pill" data-bs-target="#pills-id-badge" type="button" role="tab" aria-selected="false">
                        <i class="fas fa-id-badge me-1 text-success"></i>ID Badge &amp; Access
                    </button>
                </li>
            </ul>

            <div class="tab-content" id="profileTabsContent">
                <!-- TAB 1: ACCOUNT (MAIN VIEW MATCHING ATTACHED IMAGE) -->
                <div class="tab-pane fade show active" id="pills-account" role="tabpanel">
                    
                    <!-- Top Card: Overview -->
                    <div class="vuexy-card">
                        <div class="vuexy-card-header">
                            <h5 class="vuexy-card-title">Overview</h5>
                            <div class="d-flex align-items-center gap-2">
                                <div class="input-group input-group-sm" style="max-width: 220px;">
                                    <input type="text" id="projectSearchInput" class="form-control" placeholder="Search event..." onkeyup="filterProjectsList()">
                                </div>
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-vuexy" id="projectsListTable">
                                <thead>
                                    <tr>
                                        <th>Event</th>
                                        <th>Project Manager</th>
                                        <th>Tech Lead</th>
                                        <th>Team</th>
                                        <th style="min-width: 140px;">Progress</th>
                                        <th style="width: 50px; text-align: center;">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $colorPalette = ['#0d9488', '#1e3a5f', '#f97316', '#ea580c', '#6366f1', '#0284c7'];
                                    $progressList = [85, 70, 55, 90, 45, 60];

                                    if (!empty($assigned_events)):
                                        foreach ($assigned_events as $idx => $ev):
                                            $barColor = $colorPalette[$idx % count($colorPalette)];
                                            $progress = $progressList[$idx % count($progressList)];
                                            $evTitle = !empty($ev['title']) ? $ev['title'] : 'Production Event';
                                            $evLocation = !empty($ev['location']) ? $ev['location'] : 'Kigali Convention Centre';
                                            $evDate = !empty($ev['date']) ? date('M d, Y', strtotime($ev['date'])) : date('M d, Y');
                                            $evPM = !empty($ev['project_manager']) ? $ev['project_manager'] : 'Joy (PM)';
                                            $evLead = !empty($ev['tech_lead']) ? $ev['tech_lead'] : (!empty($ev['assigned_technician']) ? explode(',', $ev['assigned_technician'])[0] : 'Lead Tech');

                                            // Build team roster for this specific event to display on hover
                                            $teamList = [];
                                            if (!empty($ev['project_manager'])) {
                                                $teamList[] = htmlspecialchars($ev['project_manager']) . ' <small class="text-muted">(PM)</small>';
                                            }
                                            if (!empty($ev['tech_lead'])) {
                                                $teamList[] = htmlspecialchars($ev['tech_lead']) . ' <small class="text-warning fw-bold">(Tech Lead)</small>';
                                            }
                                            if (!empty($ev['assigned_technician'])) {
                                                $techArr = array_filter(array_map('trim', explode(',', $ev['assigned_technician'])));
                                                foreach ($techArr as $tName) {
                                                    if (!empty($tName) && (empty($ev['tech_lead']) || stripos($ev['tech_lead'], $tName) === false)) {
                                                        $teamList[] = htmlspecialchars($tName) . ' <small class="text-muted">(Technician)</small>';
                                                    }
                                                }
                                            }
                                            if (empty($teamList)) {
                                                $teamList = [
                                                    (!empty($ev['project_manager']) ? htmlspecialchars($ev['project_manager']) : 'Joy') . ' <small class="text-muted">(PM)</small>',
                                                    (!empty($ev['tech_lead']) ? htmlspecialchars($ev['tech_lead']) : 'Lead Tech') . ' <small class="text-warning fw-bold">(Tech Lead)</small>',
                                                    'Patrick N <small class="text-muted">(Video Tech)</small>',
                                                    'Eric H <small class="text-muted">(Audio Tech)</small>',
                                                    'Appolinaire M <small class="text-muted">(Lighting)</small>'
                                                ];
                                            }

                                            $teamHoverHtml = '<div class="text-start py-1"><strong><i class="fas fa-users me-1 text-primary"></i>Event Team Roster:</strong><ul class="mb-0 ps-3 mt-1 small text-start">' . implode('', array_map(function($m) { return '<li>' . $m . '</li>'; }, $teamList)) . '</ul></div>';
                                            $teamHoverPlain = strip_tags(str_replace('</li>', "\n", implode('', array_map(function($m) { return '• ' . $m . '</li>'; }, $teamList))));
                                            $extraCrewCount = max(1, count($teamList) - 2);
                                    ?>
                                    <tr class="project-table-row">
                                        <td>
                                            <div class="d-flex align-items-center gap-3">
                                                <?php if (!empty($ev['event_image'])): ?>
                                                    <img src="<?php echo htmlspecialchars($ev['event_image']); ?>" style="width: 38px; height: 38px; object-fit: cover; border-radius: 8px; flex-shrink: 0;" onerror="this.outerHTML='<div class=\'project-icon-box bg-primary-subtle text-primary\'><i class=\'fas fa-calendar-alt\'></i></div>'">
                                                <?php else: ?>
                                                    <div class="project-icon-box" style="background-color: <?php echo $idx % 2 === 0 ? '#e0f2fe' : '#f0fdf4'; ?>; color: <?php echo $idx % 2 === 0 ? '#0284c7' : '#16a34a'; ?>;">
                                                        <i class="fas fa-calendar-day"></i>
                                                    </div>
                                                <?php endif; ?>
                                                <div>
                                                    <a href="events.php" class="fw-bold text-dark text-decoration-none fs-6 d-block" title="View <?php echo htmlspecialchars($evTitle); ?>">
                                                        <?php echo htmlspecialchars($evTitle); ?>
                                                    </a>
                                                    <div class="text-muted small">
                                                        <i class="fas fa-map-marker-alt text-danger me-1"></i><?php echo htmlspecialchars($evLocation); ?>
                                                        <span class="mx-1">•</span>
                                                        <span><?php echo $evDate; ?></span>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center gap-2">
                                                <div class="rounded-circle bg-light text-primary d-flex align-items-center justify-content-center fw-bold" style="width: 28px; height: 28px; font-size: 0.75rem; border: 1px solid #e2e8f0;">
                                                    <?php echo strtoupper(substr($evPM, 0, 1)); ?>
                                                </div>
                                                <span class="text-dark fw-semibold small"><?php echo htmlspecialchars($evPM); ?></span>
                                            </div>
                                        </td>
                                        <td>
                                            <?php if (!empty($ev['tech_lead'])): ?>
                                                <span class="badge bg-warning-subtle text-dark border border-warning-subtle px-2 py-1">
                                                    <i class="fas fa-crown text-warning me-1"></i><?php echo htmlspecialchars($ev['tech_lead']); ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-light text-muted border px-2 py-1">
                                                    <i class="fas fa-user-gear me-1"></i><?php echo htmlspecialchars($evLead); ?>
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="avatar-stack" data-bs-toggle="tooltip" data-bs-html="true" data-bs-placement="top" title="<?php echo htmlspecialchars($teamHoverHtml); ?>" style="cursor: pointer;">
                                                <img src="uploads/profiles/profile_2_1789409080.png" onerror="this.onerror=null;this.src='data:image/svg+xml;utf8,<svg xmlns=%22http://www.w3.org/2000/svg%22 width=%2240%22 height=%2240%22 viewBox=%220 0 40 40%22><rect width=%2240%22 height=%2240%22 fill=%22%23e2e8f0%22/><circle cx=%2220%22 cy=%2215%22 r=%227%22 fill=%22%2364748b%22/><path d=%22M8 36c0-6.6 5.4-12 12-12s12 5.4 12 12%22 fill=%22%2364748b%22/></svg>';" class="avatar-stack-item" title="Tech Lead: <?php echo htmlspecialchars($evLead); ?>">
                                                <img src="uploads/profiles/profile_3_1789409415.png" onerror="this.onerror=null;this.src='data:image/svg+xml;utf8,<svg xmlns=%22http://www.w3.org/2000/svg%22 width=%2240%22 height=%2240%22 viewBox=%220 0 40 40%22><rect width=%2240%22 height=%2240%22 fill=%22%23e2e8f0%22/><circle cx=%2220%22 cy=%2215%22 r=%227%22 fill=%22%2364748b%22/><path d=%22M8 36c0-6.6 5.4-12 12-12s12 5.4 12 12%22 fill=%22%2364748b%22/></svg>';" class="avatar-stack-item" title="PM: <?php echo htmlspecialchars($evPM); ?>">
                                                <span class="avatar-stack-badge" title="<?php echo htmlspecialchars($teamHoverPlain); ?>">+<?php echo $extraCrewCount; ?></span>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center gap-2">
                                                <div class="progress vuexy-progress flex-grow-1">
                                                    <div class="progress-bar" role="progressbar" style="width: <?php echo $progress; ?>%; background-color: <?php echo $barColor; ?>;" aria-valuenow="<?php echo $progress; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                                </div>
                                                <span class="text-muted small fw-bold" style="min-width: 32px;"><?php echo $progress; ?>%</span>
                                            </div>
                                        </td>
                                        <td class="text-center">
                                            <div class="dropdown">
                                                <button class="btn btn-sm btn-link text-muted p-0" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                                    <i class="fas fa-ellipsis-v"></i>
                                                </button>
                                                <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                                                    <li>
                                                        <a class="dropdown-item small" href="events.php">
                                                            <i class="fas fa-calendar-alt me-2 text-primary"></i> View Event Details
                                                        </a>
                                                    </li>
                                                    <li>
                                                        <button type="button" class="dropdown-item small" onclick="openOverviewEditEventModal(<?php echo htmlspecialchars(json_encode($ev), ENT_QUOTES, 'UTF-8'); ?>)">
                                                            <i class="fas fa-edit me-2 text-warning"></i> Edit Event
                                                        </button>
                                                    </li>
                                                    <li>
                                                        <a class="dropdown-item small" href="batch_history.php?search=<?php echo urlencode($evTitle); ?>">
                                                            <i class="fas fa-truck-loading me-2 text-info"></i> View Batch Movements
                                                        </a>
                                                    </li>
                                                </ul>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php 
                                        endforeach; 
                                    else: 
                                    ?>
                                    <tr>
                                        <td colspan="6" class="text-center py-5 text-muted">
                                            <div class="py-4">
                                                <i class="fas fa-calendar-times fa-3x text-secondary opacity-50 mb-3 d-block"></i>
                                                <h6 class="fw-bold text-dark mb-1">No events assigned to '<?php echo htmlspecialchars($userFullName); ?>'</h6>
                                                <p class="text-muted small mb-3">This user has not been assigned to any events or productions yet.</p>
                                                <a href="events.php" class="btn btn-sm btn-outline-primary">
                                                    <i class="fas fa-calendar-plus me-1"></i> Assign in Event Center
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination Footer -->
                        <div class="d-flex justify-content-between align-items-center mt-3 pt-2 text-muted small">
                            <span>Showing <?php echo !empty($assigned_events) ? '1 to ' . count($assigned_events) : '0'; ?> of <?php echo count($assigned_events); ?> entries</span>
                            <div class="d-flex align-items-center gap-1">
                                <button class="btn btn-xs btn-outline-secondary px-2 py-1 disabled"><i class="fas fa-chevron-left me-1"></i> Previous</button>
                                <span class="badge bg-dark px-2 py-1">1</span>
                                <button class="btn btn-xs btn-outline-secondary px-2 py-1 disabled">Next <i class="fas fa-chevron-right ms-1"></i></button>
                            </div>
                        </div>
                    </div>
                    <!-- Bottom Card: User Activity Timeline -->
                    <div class="vuexy-card">
                        <div class="vuexy-card-header d-flex flex-wrap justify-content-between align-items-center gap-2 pb-2">
                            <div>
                                <h5 class="vuexy-card-title mb-0">User Activity Timeline</h5>
                                <div class="text-muted small mt-1">Individual access logs for venues &amp; stock</div>
                            </div>
                            <div class="d-flex align-items-center gap-2 flex-wrap">
                                <!-- Checkpoint Scope Filter -->
                                <select id="timelineScopeFilter" class="form-select form-select-sm" style="width: auto; min-width: 175px; font-size: 0.8rem; border-color: #dbdade;">
                                    <option value="all">All Individuals (All Logs)</option>
                                    <?php if ($selected_user): ?>
                                    <option value="user_<?php echo $selected_user['id']; ?>">This User: <?php echo htmlspecialchars($userFullName ?: $selected_user['username']); ?></option>
                                    <?php endif; ?>
                                    <option value="event_venue">Venues Only</option>
                                    <option value="stock_warehouse">Stock Warehouses Only</option>
                                    <option value="granted">Access Granted</option>
                                    <option value="denied">Access Denied</option>
                                </select>
                                <!-- Search Input -->
                                <div class="input-group input-group-sm" style="width: 230px;">
                                    <span class="input-group-text bg-light border-end-0 text-muted"><i class="fas fa-search"></i></span>
                                    <input type="text" id="timelineSearchInput" class="form-control border-start-0 ps-0" placeholder="Search individual, venue, stock..." autocomplete="off">
                                    <button class="btn btn-outline-secondary border-start-0" type="button" id="timelineSearchClear" style="display: none;" title="Clear search"><i class="fas fa-times"></i></button>
                                </div>
                            </div>
                        </div>

                        <!-- Dynamic Timeline Container -->
                        <div class="timeline-container mt-2" id="timelineContainer">
                            <!-- Populated via JavaScript (5 items per page) -->
                        </div>

                        <!-- Pagination Footer (5 items per page) -->
                        <div class="d-flex flex-wrap justify-content-between align-items-center mt-3 pt-3 border-top text-muted small" id="timelinePaginationWrapper">
                            <span id="timelinePaginationInfo">Showing 1 to 5 entries</span>
                            <div class="d-flex align-items-center gap-1" id="timelinePaginationControls">
                                <!-- Injected by JS -->
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
                                            <td colspan="7" class="text-center py-5 text-muted">
                                                <div class="py-4">
                                                    <i class="fas fa-calendar-times fa-3x text-secondary opacity-50 mb-3 d-block"></i>
                                                    <h6 class="fw-bold text-dark mb-1">No events assigned to '<?php echo htmlspecialchars($userFullName); ?>'</h6>
                                                    <p class="text-muted small mb-3">No upcoming events or production setups assigned to this user.</p>
                                                    <a href="events.php" class="btn btn-sm btn-primary">
                                                        <i class="fas fa-plus-circle me-1"></i> Assign to an Event
                                                    </a>
                                                </div>
                                            </td>
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
                                                    <span class="text-muted fw-semibold"><?php echo htmlspecialchars(!empty($ab['transport_driver']) ? $ab['transport_driver'] : 'Assigned Driver'); ?></span>
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
                                            <td colspan="8" class="text-center py-5 text-muted">
                                                <div class="py-4">
                                                    <i class="far fa-calendar-times fa-3x text-secondary opacity-50 mb-3 d-block"></i>
                                                    <h6 class="fw-bold text-dark mb-1">No calltimes scheduled for '<?php echo htmlspecialchars($userFullName); ?>'</h6>
                                                    <p class="text-muted small mb-0">No active events or reporting shifts assigned to this user.</p>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- TAB: ID BADGE, STOCK & VENUE ACCESS & ATTENDANCE -->
                <div class="tab-pane fade" id="pills-id-badge" role="tabpanel">
                    <?php
                        $ubRow = $selected_user_badge ?: [];
                        $ubCode = $ubRow['badge_code'] ?? ('ABL-BDG-' . str_pad($selected_user['id'], 4, '0', STR_PAD_LEFT));
                        $ubRfid = $ubRow['rfid_uid'] ?? ('RFID-88' . str_pad($selected_user['id'], 2, '0', STR_PAD_LEFT));
                        $ubClearance = $ubRow['clearance_level'] ?? 'L1_VENUE_BASIC';
                        $ubClearanceInfo = $clearanceMetaMap[$ubClearance] ?? $clearanceMetaMap['L1_VENUE_BASIC'];
                        $ubRoleAccent = getRoleAccentMeta($userRoleKey);
                        $ubStockMode = $ubRow['stock_access_mode'] ?? 'none';
                        $ubAllowedStocks = json_decode($ubRow['allowed_stock_ids'] ?? '[]', true);
                        if (!is_array($ubAllowedStocks)) $ubAllowedStocks = [];
                        $ubAllowedStocks = array_map('intval', $ubAllowedStocks);
                        $ubVenueMode = $ubRow['venue_access_mode'] ?? 'assigned_only';
                        $ubStatus = $ubRow['badge_status'] ?? 'active';
                        $ubQrText = $ubCode . '|' . $staffCode . '|' . $selected_user['username'] . '|' . $ubClearanceInfo['code'];
                    ?>
                    <div class="row g-4">
                        <!-- Left: Interactive CR80 ID Card Preview -->
                        <div class="col-xl-5">
                            <div class="vuexy-card h-100">
                                <div class="vuexy-card-header">
                                    <h5 class="vuexy-card-title"><i class="fas fa-id-badge text-success me-2"></i>Staff CR80 ID Badge</h5>
                                    <div class="d-flex gap-1">
                                        <a href="id_badges.php?user_id=<?php echo (int)$selected_user['id']; ?>" class="btn btn-xs btn-outline-dark fw-semibold">
                                            <i class="fas fa-print me-1"></i>Print / Flip
                                        </a>
                                        <a href="id_badges.php?tab=kiosk&amp;user_id=<?php echo (int)$selected_user['id']; ?>" class="btn btn-xs btn-success fw-semibold">
                                            <i class="fas fa-qrcode me-1"></i>Gate Kiosk
                                        </a>
                                    </div>
                                </div>
                                <div class="p-4 d-flex flex-column align-items-center">
                                    <div style="width: 285px; border-radius: 16px; border: 1px solid #cbd5e1; overflow: hidden; box-shadow: 0 10px 24px rgba(15,23,42,0.1); background: #fff;">
                                        <div style="background: <?php echo $ubRoleAccent['header']; ?>; padding: 10px 16px 36px; color: #fff; text-align: center;">
                                            <div style="width: 50px; height: 7px; background: rgba(255,255,255,0.35); border-radius: 8px; margin: 0 auto 6px;"></div>
                                            <div class="d-flex justify-content-between align-items-center">
                                                <span class="fw-bold"><i class="fas fa-cubes-stacked text-warning me-1"></i>aBility</span>
                                                <span class="badge bg-light text-dark font-monospace" style="font-size: 0.7rem;"><?php echo htmlspecialchars($ubClearanceInfo['code']); ?> ACCESS</span>
                                            </div>
                                        </div>
                                        <div style="width: 82px; height: 82px; margin: -32px auto 8px; border-radius: 50%; background: #fff; padding: 4px; box-shadow: 0 4px 12px rgba(0,0,0,0.15);">
                                            <?php if ($hasProfilePhoto): ?>
                                                <img src="<?php echo htmlspecialchars($selected_user['profile_image']); ?>" style="width:100%;height:100%;border-radius:50%;object-fit:cover;">
                                            <?php else: ?>
                                                <div style="width:100%;height:100%;border-radius:50%;background:<?php echo $ubRoleAccent['color']; ?>;color:#fff;display:flex;align-items:center;justify-content:center;font-size:1.8rem;font-weight:800;">
                                                    <?php echo strtoupper(substr($userFullName, 0, 1)); ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="text-center px-3">
                                            <div class="fw-bold fs-5 text-dark text-truncate"><?php echo htmlspecialchars($userFullName); ?></div>
                                            <span class="badge rounded-pill text-white px-3 py-1 my-1" style="background: <?php echo $ubRoleAccent['color']; ?>; font-size: 0.68rem;">
                                                <?php echo htmlspecialchars($ubRoleAccent['label']); ?>
                                            </span>
                                            <div class="small text-muted fw-semibold"><?php echo htmlspecialchars($userDeptTitle); ?></div>
                                        </div>
                                        <div class="mx-3 my-2 p-2 rounded-3 bg-light border small">
                                            <div class="d-flex justify-content-between">
                                                <span class="text-muted">Clearance:</span>
                                                <strong style="color: <?php echo $ubClearanceInfo['color']; ?>;"><?php echo htmlspecialchars($ubClearanceInfo['short']); ?></strong>
                                            </div>
                                            <div class="d-flex justify-content-between mt-1">
                                                <span class="text-muted">Stock Mode:</span>
                                                <strong class="text-dark"><?php echo strtoupper($ubStockMode); ?></strong>
                                            </div>
                                            <div class="d-flex justify-content-between mt-1">
                                                <span class="text-muted">Venue Mode:</span>
                                                <strong class="text-dark"><?php echo strtoupper(str_replace('_', ' ', $ubVenueMode)); ?></strong>
                                            </div>
                                        </div>
                                        <div class="mx-3 mb-3 p-2 rounded-3 border d-flex align-items-center justify-content-between gap-2">
                                            <div id="userProfileBadgeQr" data-qr="<?php echo htmlspecialchars($ubQrText); ?>" style="width: 68px; height: 68px;"></div>
                                            <div class="text-start flex-grow-1">
                                                <div class="text-muted" style="font-size: 0.62rem; font-weight: 700;">BADGE ID</div>
                                                <div class="fw-bold text-dark font-monospace" style="font-size: 0.82rem;"><?php echo htmlspecialchars($ubCode); ?></div>
                                                <div class="text-muted mt-1" style="font-size: 0.62rem; font-weight: 700;">STAFF CODE</div>
                                                <div class="fw-semibold text-secondary font-monospace" style="font-size: 0.74rem;"><?php echo htmlspecialchars($staffCode); ?></div>
                                            </div>
                                        </div>
                                        <div class="px-3 py-2 text-white d-flex justify-content-between small fw-bold" style="background: <?php echo $ubClearanceInfo['badge_bg']; ?>; font-size: 0.7rem;">
                                            <span><?php echo htmlspecialchars($ubRfid); ?></span>
                                            <span><?php echo strtoupper($ubStatus); ?></span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Right: Quick Access Level Config + User Attendance Logs -->
                        <div class="col-xl-7">
                            <div class="vuexy-card mb-4">
                                <div class="vuexy-card-header">
                                    <h5 class="vuexy-card-title"><i class="fas fa-sliders text-primary me-2"></i>Stock &amp; Venue Access Level Configuration</h5>
                                </div>
                                <div class="p-4">
                                    <form onsubmit="saveUserProfileBadgeAccess(event, this)">
                                        <input type="hidden" name="ajax_action" value="save_badge_config">
                                        <input type="hidden" name="user_id" value="<?php echo (int)$selected_user['id']; ?>">
                                        <input type="hidden" name="rfid_uid" value="<?php echo htmlspecialchars($ubRfid); ?>">
                                        <input type="hidden" name="emergency_contact" value="<?php echo htmlspecialchars($ubRow['emergency_contact'] ?? '+250 788 000 112'); ?>">
                                        <input type="hidden" name="blood_group" value="<?php echo htmlspecialchars($ubRow['blood_group'] ?? 'O+'); ?>">
                                        <input type="hidden" name="expires_at" value="<?php echo htmlspecialchars($ubRow['expires_at'] ?? date('Y-12-31', strtotime('+1 year'))); ?>">

                                        <div class="row g-3">
                                            <div class="col-md-7">
                                                <label class="form-label small fw-bold">Access Clearance Level</label>
                                                <select name="clearance_level" class="form-select form-select-sm">
                                                    <?php foreach ($clearanceMetaMap as $cKey => $cm): ?>
                                                        <option value="<?php echo $cKey; ?>" <?php echo ($ubClearance === $cKey) ? 'selected' : ''; ?>>
                                                            <?php echo htmlspecialchars($cm['title']); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="col-md-5">
                                                <label class="form-label small fw-bold">Badge Status</label>
                                                <select name="badge_status" class="form-select form-select-sm">
                                                    <option value="active" <?php echo ($ubStatus === 'active') ? 'selected' : ''; ?>>Active</option>
                                                    <option value="suspended" <?php echo ($ubStatus === 'suspended') ? 'selected' : ''; ?>>Suspended</option>
                                                    <option value="revoked" <?php echo ($ubStatus === 'revoked') ? 'selected' : ''; ?>>Revoked</option>
                                                </select>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label small fw-bold text-success"><i class="fas fa-warehouse me-1"></i>Stock Warehouse Access</label>
                                                <select name="stock_access_mode" class="form-select form-select-sm mb-2">
                                                    <option value="all" <?php echo ($ubStockMode === 'all') ? 'selected' : ''; ?>>All Stock Warehouses</option>
                                                    <option value="selected" <?php echo ($ubStockMode === 'selected') ? 'selected' : ''; ?>>Selected Stock Locations</option>
                                                    <option value="none" <?php echo ($ubStockMode === 'none') ? 'selected' : ''; ?>>No Stock Warehouse Access</option>
                                                </select>
                                                <div class="border rounded p-2 bg-light overflow-auto" style="max-height: 95px;">
                                                    <?php foreach ($userStockLocations as $sl): ?>
                                                        <div class="form-check small">
                                                            <input class="form-check-input" type="checkbox" name="allowed_stock_ids[]" value="<?php echo (int)$sl['id']; ?>" <?php echo in_array((int)$sl['id'], $ubAllowedStocks, true) ? 'checked' : ''; ?>>
                                                            <label class="form-check-label"><?php echo htmlspecialchars($sl['name']); ?></label>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label small fw-bold text-primary"><i class="fas fa-location-dot me-1"></i>Event Venue Access</label>
                                                <select name="venue_access_mode" class="form-select form-select-sm mb-2">
                                                    <option value="all" <?php echo ($ubVenueMode === 'all') ? 'selected' : ''; ?>>All Event Venues &amp; Stages</option>
                                                    <option value="assigned_only" <?php echo ($ubVenueMode === 'assigned_only') ? 'selected' : ''; ?>>Assigned Event Venues Only</option>
                                                    <option value="selected" <?php echo ($ubVenueMode === 'selected') ? 'selected' : ''; ?>>Selected Venues</option>
                                                    <option value="none" <?php echo ($ubVenueMode === 'none') ? 'selected' : ''; ?>>No Venue Access</option>
                                                </select>
                                                <div class="small text-muted p-2 border rounded bg-light">
                                                    Controls gate verification when scanning <strong><?php echo htmlspecialchars($ubCode); ?></strong> at Event Venues and Calltimes.
                                                </div>
                                            </div>
                                        </div>
                                        <div class="mt-3 text-end">
                                            <button type="submit" class="btn btn-sm btn-primary fw-bold px-3">
                                                <i class="fas fa-save me-1"></i>Update Access Clearance
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>

                            <!-- Recent Stock & Venue Attendance History for this user -->
                            <div class="vuexy-card">
                                <div class="vuexy-card-header">
                                    <h5 class="vuexy-card-title"><i class="fas fa-clock-rotate-left text-info me-2"></i>Stock &amp; Venue Attendance Logs</h5>
                                    <a href="id_badges.php?tab=kiosk" class="btn btn-xs btn-outline-primary">Record Gate Scan</a>
                                </div>
                                <div class="table-responsive">
                                    <table class="table table-vuexy mb-0">
                                        <thead>
                                            <tr>
                                                <th>Time</th>
                                                <th>Checkpoint (Stock / Venue)</th>
                                                <th>Action</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (!empty($selected_user_attendance)): ?>
                                                <?php foreach ($selected_user_attendance as $att): ?>
                                                    <tr>
                                                        <td class="small">
                                                            <div class="fw-bold text-dark"><?php echo date('M d, Y', strtotime($att['scanned_at'])); ?></div>
                                                            <div class="text-muted"><?php echo date('h:i A', strtotime($att['scanned_at'])); ?></div>
                                                        </td>
                                                        <td>
                                                            <?php if ($att['checkpoint_type'] === 'stock_warehouse'): ?>
                                                                <span class="badge bg-success-subtle text-success me-1">Stock</span>
                                                            <?php else: ?>
                                                                <span class="badge bg-primary-subtle text-primary me-1">Venue</span>
                                                            <?php endif; ?>
                                                            <span class="fw-semibold"><?php echo htmlspecialchars($att['checkpoint_name']); ?></span>
                                                        </td>
                                                        <td>
                                                            <span class="badge bg-light text-dark border"><?php echo strtoupper(str_replace('_', ' ', $att['scan_action'])); ?></span>
                                                        </td>
                                                        <td>
                                                            <?php if ($att['access_status'] === 'granted'): ?>
                                                                <span class="badge bg-success">Granted</span>
                                                            <?php else: ?>
                                                                <span class="badge bg-danger">Denied</span>
                                                            <?php endif; ?>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <tr>
                                                    <td colspan="4" class="text-center py-3 text-muted small">No badge scans recorded yet for this user.</td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
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
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <label for="role" class="form-label fw-bold small mb-0">Role <span class="text-danger">*</span></label>
                                <a href="javascript:void(0)" onclick="openQuickAddRole()" class="text-primary text-decoration-none small fw-semibold" style="font-size: 0.75rem;">
                                    <i class="fas fa-plus-circle me-1"></i>New Role
                                </a>
                            </div>
                            <select class="form-select form-select-sm" id="role" name="role" required>
                                <?php foreach ($roles as $r_key => $r_label): ?>
                                    <option value="<?php echo $r_key; ?>"><?php echo $r_label; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <label for="department" class="form-label fw-bold small mb-0">Department</label>
                                <a href="javascript:void(0)" onclick="openQuickAddDept()" class="text-primary text-decoration-none small fw-semibold" style="font-size: 0.75rem;">
                                    <i class="fas fa-plus-circle me-1"></i>New Dept
                                </a>
                            </div>
                            <select class="form-select form-select-sm" id="department" name="department">
                                <option value="">Select Department</option>
                                <?php 
                                $renderedDepts = [];
                                foreach ($departments as $d_key => $d_label): 
                                    if (in_array(strtolower($d_label), $renderedDepts)) continue;
                                    $renderedDepts[] = strtolower($d_label);
                                ?>
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

<!-- ==================== MANAGE ROLES & DEPARTMENTS MODAL ==================== -->
<div class="modal fade" id="rolesDeptModal" tabindex="-1" aria-labelledby="rolesDeptModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-dark text-white py-3">
                <div class="d-flex align-items-center gap-2">
                    <i class="fas fa-tags text-warning fs-5"></i>
                    <div>
                        <h5 class="modal-title fw-bold mb-0" id="rolesDeptModalLabel">Manage Roles &amp; Departments</h5>
                        <div class="small text-white-50" style="font-size: 0.75rem;">Create, configure, and inspect organizational staff roles &amp; departments</div>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body p-0">
                <!-- Nav tabs -->
                <ul class="nav nav-tabs nav-fill bg-light px-3 pt-2 border-bottom" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active fw-bold text-dark" id="modal-dept-tab" data-bs-toggle="tab" data-bs-target="#modal-dept-pane" type="button" role="tab">
                            <i class="fas fa-building me-2 text-primary"></i>Departments (<?php echo count($deptListDetails); ?>)
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link fw-bold text-dark" id="modal-roles-tab" data-bs-toggle="tab" data-bs-target="#modal-roles-pane" type="button" role="tab">
                            <i class="fas fa-user-tag me-2 text-success"></i>Roles &amp; Positions (<?php echo count($roleListDetails); ?>)
                        </button>
                    </li>
                </ul>

                <div class="tab-content p-4">
                    <!-- TAB 1: DEPARTMENTS -->
                    <div class="tab-pane fade show active" id="modal-dept-pane" role="tabpanel">
                        <!-- Add Dept Card -->
                        <div class="card border mb-4 shadow-none bg-light">
                            <div class="card-body p-3">
                                <h6 class="fw-bold text-dark mb-2"><i class="fas fa-plus-circle text-primary me-1"></i> Add New Department</h6>
                                <form id="formAddDepartmentModal" onsubmit="submitDepartmentForm(event, this)">
                                    <input type="hidden" name="action" value="add_department">
                                    <div class="row g-2 align-items-end">
                                        <div class="col-md-5">
                                            <label class="form-label small fw-bold mb-1">Department Name <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control form-control-sm" name="dept_name" id="modalDeptName" required placeholder="e.g. Staging & Rigging">
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label small fw-bold mb-1">Code <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control form-control-sm text-uppercase" name="dept_code" id="modalDeptCode" required placeholder="e.g. STG" maxlength="10">
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label small fw-bold mb-1">Description</label>
                                            <input type="text" class="form-control form-control-sm" name="description" placeholder="Brief function...">
                                        </div>
                                        <div class="col-12 text-end mt-2">
                                            <button type="submit" class="btn btn-sm btn-primary px-3">
                                                <i class="fas fa-save me-1"></i> Save Department
                                            </button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <!-- Existing Departments Table -->
                        <h6 class="fw-bold text-dark mb-2">Existing Departments</h6>
                        <div class="table-responsive border rounded">
                            <table class="table table-sm table-hover mb-0 align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th style="width: 80px;">Code</th>
                                        <th>Department Name</th>
                                        <th>Description</th>
                                        <th style="width: 80px;" class="text-center">Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($deptListDetails)): ?>
                                        <?php foreach ($deptListDetails as $dItem): ?>
                                            <tr>
                                                <td><span class="badge bg-primary-subtle text-primary border font-monospace"><?php echo htmlspecialchars($dItem['code']); ?></span></td>
                                                <td class="fw-bold text-dark"><?php echo htmlspecialchars($dItem['name']); ?></td>
                                                <td class="text-muted small"><?php echo htmlspecialchars($dItem['description'] ?: '—'); ?></td>
                                                <td class="text-center"><span class="badge bg-success-subtle text-success">Active</span></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr><td colspan="4" class="text-center py-3 text-muted">No department records found.</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="text-end mt-2">
                            <a href="departments.php" class="small text-decoration-none text-primary fw-semibold">
                                Open Full Department Management Page <i class="fas fa-arrow-right ms-1"></i>
                            </a>
                        </div>
                    </div>

                    <!-- TAB 2: ROLES -->
                    <div class="tab-pane fade" id="modal-roles-pane" role="tabpanel">
                        <!-- Add Role Card -->
                        <div class="card border mb-4 shadow-none bg-light">
                            <div class="card-body p-3">
                                <h6 class="fw-bold text-dark mb-2"><i class="fas fa-plus-circle text-success me-1"></i> Add New Role / Position</h6>
                                <form id="formAddRoleModal" onsubmit="submitRoleForm(event, this)">
                                    <input type="hidden" name="action" value="add_role">
                                    <div class="row g-2 align-items-end">
                                        <div class="col-md-5">
                                            <label class="form-label small fw-bold mb-1">Role Title <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control form-control-sm" name="role_name" id="modalRoleName" required placeholder="e.g. Audio Engineer">
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label small fw-bold mb-1">System Key <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control form-control-sm text-lowercase" name="role_key" id="modalRoleKey" required placeholder="e.g. audio_engineer">
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label small fw-bold mb-1">Description</label>
                                            <input type="text" class="form-control form-control-sm" name="description" placeholder="Responsibilities...">
                                        </div>
                                        <div class="col-12 text-end mt-2">
                                            <button type="submit" class="btn btn-sm btn-success px-3">
                                                <i class="fas fa-save me-1"></i> Save Role
                                            </button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <!-- Existing Roles Table -->
                        <h6 class="fw-bold text-dark mb-2">Registered System Roles</h6>
                        <div class="table-responsive border rounded">
                            <table class="table table-sm table-hover mb-0 align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th style="width: 140px;">System Key</th>
                                        <th>Role Title</th>
                                        <th>Description</th>
                                        <th style="width: 80px;" class="text-center">Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($roleListDetails)): ?>
                                        <?php foreach ($roleListDetails as $rItem): ?>
                                            <tr>
                                                <td><span class="badge bg-secondary-subtle text-dark border font-monospace"><?php echo htmlspecialchars($rItem['role_key']); ?></span></td>
                                                <td class="fw-bold text-dark"><?php echo htmlspecialchars($rItem['role_name']); ?></td>
                                                <td class="text-muted small"><?php echo htmlspecialchars($rItem['description'] ?: '—'); ?></td>
                                                <td class="text-center"><span class="badge bg-success-subtle text-success">Active</span></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr><td colspan="4" class="text-center py-3 text-muted">No role records found.</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="text-end mt-2">
                            <a href="role_access_matrix.php" class="small text-decoration-none text-success fw-semibold">
                                Configure Role Permissions Matrix <i class="fas fa-arrow-right ms-1"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <div class="modal-footer bg-light py-2">
                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- ==================== QUICK ADD ROLE MODAL ==================== -->
<div class="modal fade" id="quickAddRoleModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-dark text-white py-2">
                <h6 class="modal-title fw-bold"><i class="fas fa-user-tag text-success me-2"></i>Add New Role</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="quickRoleForm" onsubmit="submitRoleForm(event, this, true)">
                <input type="hidden" name="action" value="add_role">
                <div class="modal-body p-3">
                    <div class="mb-2">
                        <label class="form-label small fw-bold">Role Title <span class="text-danger">*</span></label>
                        <input type="text" class="form-control form-control-sm" name="role_name" id="quickRoleName" required placeholder="e.g. Video Switcher">
                    </div>
                    <div class="mb-2">
                        <label class="form-label small fw-bold">System Key <span class="text-danger">*</span></label>
                        <input type="text" class="form-control form-control-sm text-lowercase" name="role_key" id="quickRoleKey" required placeholder="e.g. video_switcher">
                    </div>
                    <div class="mb-2">
                        <label class="form-label small fw-bold">Description</label>
                        <input type="text" class="form-control form-control-sm" name="description" placeholder="Brief duty details...">
                    </div>
                </div>
                <div class="modal-footer bg-light py-2">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-success"><i class="fas fa-save me-1"></i> Save &amp; Select</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ==================== QUICK ADD DEPARTMENT MODAL ==================== -->
<div class="modal fade" id="quickAddDeptModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-dark text-white py-2">
                <h6 class="modal-title fw-bold"><i class="fas fa-building text-primary me-2"></i>Add New Department</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="quickDeptForm" onsubmit="submitDepartmentForm(event, this, true)">
                <input type="hidden" name="action" value="add_department">
                <div class="modal-body p-3">
                    <div class="mb-2">
                        <label class="form-label small fw-bold">Department Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control form-control-sm" name="dept_name" id="quickDeptName" required placeholder="e.g. Broadcast & Streaming">
                    </div>
                    <div class="mb-2">
                        <label class="form-label small fw-bold">Department Code <span class="text-danger">*</span></label>
                        <input type="text" class="form-control form-control-sm text-uppercase" name="dept_code" id="quickDeptCode" required placeholder="e.g. BRD" maxlength="10">
                    </div>
                    <div class="mb-2">
                        <label class="form-label small fw-bold">Description</label>
                        <input type="text" class="form-control form-control-sm" name="description" placeholder="Brief department description...">
                    </div>
                </div>
                <div class="modal-footer bg-light py-2">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-primary"><i class="fas fa-save me-1"></i> Save &amp; Select</button>
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

<!-- ==================== OVERVIEW EDIT EVENT MODAL ==================== -->
<div class="modal fade" id="overviewEditEventModal" tabindex="-1" aria-labelledby="overviewEditEventModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content border-0 shadow">
            <form id="overviewEditEventForm" onsubmit="saveOverviewEvent(event)">
                <input type="hidden" id="editEvId" name="id">
                <input type="hidden" name="_method" value="PUT">
                <input type="hidden" id="editEvExistingImage" name="existing_image">

                <div class="modal-header border-bottom">
                    <h5 class="modal-title fw-bold text-dark" id="overviewEditEventModalLabel">
                        <i class="fas fa-edit me-2 text-warning"></i>Edit Event Details
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body p-4 text-dark">
                    <div class="row g-3">
                        <div class="col-md-12">
                            <label class="form-label small fw-bold text-dark">Event Title <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="editEvTitle" name="title" required placeholder="e.g. World Bank">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label small fw-bold text-dark">Date</label>
                            <input type="date" class="form-control" id="editEvDate" name="date">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label small fw-bold text-dark">Call Time</label>
                            <input type="time" class="form-control" id="editEvCallTime" name="call_time">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label small fw-bold text-dark">Duration (Days)</label>
                            <input type="number" class="form-control" id="editEvDuration" name="duration" min="1" value="1">
                        </div>

                        <div class="col-md-12">
                            <label class="form-label small fw-bold text-dark">Location / Venue</label>
                            <input type="text" class="form-control" id="editEvLocation" name="location" placeholder="e.g. Marriot Hotel">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-dark">Project Manager</label>
                            <select class="form-select" id="editEvManager" name="manager">
                                <option value="">-- Select Project Manager --</option>
                                <?php foreach ($all_users as $usr): 
                                    $uDisplay = !empty(trim($usr['full_name'] ?? '')) ? trim($usr['full_name']) : trim($usr['username']);
                                    $uLabel = !empty(trim($usr['full_name'] ?? '')) ? trim($usr['full_name']) . ' (@' . $usr['username'] . ')' : '@' . $usr['username'];
                                ?>
                                    <option value="<?php echo htmlspecialchars($uDisplay); ?>" data-username="<?php echo htmlspecialchars($usr['username']); ?>">
                                        <?php echo htmlspecialchars($uLabel); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-dark">Tech Lead</label>
                            <select class="form-select" id="editEvTechLead" name="tech_lead">
                                <option value="">-- Select Tech Lead --</option>
                                <?php foreach ($all_users as $usr): 
                                    $uDisplay = !empty(trim($usr['full_name'] ?? '')) ? trim($usr['full_name']) : trim($usr['username']);
                                    $uLabel = !empty(trim($usr['full_name'] ?? '')) ? trim($usr['full_name']) . ' (@' . $usr['username'] . ')' : '@' . $usr['username'];
                                ?>
                                    <option value="<?php echo htmlspecialchars($uDisplay); ?>" data-username="<?php echo htmlspecialchars($usr['username']); ?>">
                                        <?php echo htmlspecialchars($uLabel); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-12">
                            <label class="form-label small fw-bold text-dark">Assigned Technicians</label>
                            <select class="form-select" id="editEvTechnicians" name="technicians[]" multiple style="height: 120px;">
                                <?php foreach ($all_users as $usr): 
                                    $uDisplay = !empty(trim($usr['full_name'] ?? '')) ? trim($usr['full_name']) : trim($usr['username']);
                                    $uRoleDept = ucfirst($usr['department'] ?: $usr['role']);
                                    $uLabel = (!empty(trim($usr['full_name'] ?? '')) ? trim($usr['full_name']) . ' (@' . $usr['username'] . ')' : '@' . $usr['username']) . ' - ' . $uRoleDept;
                                ?>
                                    <option value="<?php echo htmlspecialchars($uDisplay); ?>" data-username="<?php echo htmlspecialchars($usr['username']); ?>">
                                        <?php echo htmlspecialchars($uLabel); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text small text-muted">Hold Ctrl (Windows) / Cmd (Mac) to select multiple technicians.</div>
                        </div>

                        <div class="col-md-12">
                            <label class="form-label small fw-bold text-dark">Description</label>
                            <textarea class="form-control" id="editEvDesc" name="description" rows="3" placeholder="Brief event summary or technical rider requirements..."></textarea>
                        </div>

                        <div class="col-md-12">
                            <label class="form-label small fw-bold text-dark">Event Banner / Image</label>
                            <input type="file" class="form-control" name="image" accept="image/*" onchange="previewOverviewEventImg(this)">
                            <div id="overviewImgPreview" class="mt-2" style="display:none;">
                                <img src="" id="overviewImgPreviewTag" style="max-height: 80px; border-radius: 6px; border: 1px solid #ddd;">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer border-top bg-light">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" id="saveOverviewEventBtn" class="btn btn-sm btn-primary">
                        <i class="fas fa-save me-1"></i> Update Event
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
// Buffer scripts so they execute cleanly AFTER footer loads jQuery, Bootstrap & DataTables
ob_start();
?>
<script>
    // Initialize tooltips with HTML support for team popovers
    $(function() {
        if (typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
            const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl, {
                    html: true,
                    boundary: 'window'
                });
            });
        }
    });

    function filterProjectsList() {
        const val = $('#projectSearchInput').val().toLowerCase();
        $('.project-table-row').each(function() {
            const text = $(this).text().toLowerCase();
            $(this).toggle(text.indexOf(val) > -1);
        });
    }

    function openOverviewEditEventModal(ev) {
        if (!ev) return;
        $('#overviewEditEventForm')[0].reset();
        $('#editEvId').val(ev.id || ev.event_id || '');
        $('#editEvTitle').val(ev.title || '');
        
        if (ev.date) {
            const rawDate = String(ev.date).trim();
            if (/^\d{4}-\d{2}-\d{2}$/.test(rawDate)) {
                $('#editEvDate').val(rawDate);
            } else {
                const d = new Date(rawDate);
                if (!isNaN(d.getTime())) {
                    const yr = d.getFullYear();
                    const mo = String(d.getMonth() + 1).padStart(2, '0');
                    const da = String(d.getDate()).padStart(2, '0');
                    $('#editEvDate').val(`${yr}-${mo}-${da}`);
                }
            }
        } else {
            $('#editEvDate').val('');
        }

        $('#editEvCallTime').val(ev.call_time || '');
        $('#editEvDuration').val(ev.duration || 1);
        $('#editEvLocation').val(ev.location && ev.location !== '0' ? ev.location : '');
        $('#editEvManager').val(ev.project_manager || '');
        $('#editEvTechLead').val(ev.tech_lead || '');

        const rawTech = ev.assigned_technician || ev.technician || '';
        if (rawTech) {
            const selectedTechs = rawTech.split(',').map(t => t.trim()).filter(t => t.length > 0);
            $('#editEvTechnicians').val(selectedTechs);
        } else {
            $('#editEvTechnicians').val([]);
        }

        $('#editEvDesc').val(ev.description || '');

        if (ev.event_image) {
            $('#editEvExistingImage').val(ev.event_image);
            $('#overviewImgPreviewTag').attr('src', ev.event_image);
            $('#overviewImgPreview').show();
        } else {
            $('#editEvExistingImage').val('');
            $('#overviewImgPreview').hide();
        }

        const modalEl = document.getElementById('overviewEditEventModal');
        if (modalEl) {
            const modal = bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl);
            modal.show();
        }
    }

    function previewOverviewEventImg(input) {
        if (input.files && input.files[0]) {
            const reader = new FileReader();
            reader.onload = function(e) {
                $('#overviewImgPreviewTag').attr('src', e.target.result);
                $('#overviewImgPreview').show();
            };
            reader.readAsDataURL(input.files[0]);
        }
    }

    function saveOverviewEvent(e) {
        e.preventDefault();
        const btn = $('#saveOverviewEventBtn');
        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i> Saving...');

        const formData = new FormData(document.getElementById('overviewEditEventForm'));

        $.ajax({
            url: 'api/events.php',
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(res) {
                btn.prop('disabled', false).html('<i class="fas fa-save me-1"></i> Update Event');
                if (res && res.success) {
                    $('#overviewEditEventModal').modal('hide');
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({
                            icon: 'success',
                            title: 'Event Updated',
                            text: 'The event details have been updated successfully.',
                            timer: 1500,
                            showConfirmButton: false
                        }).then(() => {
                            location.reload();
                        });
                    } else {
                        alert('Event updated successfully!');
                        location.reload();
                    }
                } else {
                    if (typeof Swal !== 'undefined') {
                        Swal.fire('Error', res.message || res.error || 'Failed to update event.', 'error');
                    } else {
                        alert(res.message || res.error || 'Failed to update event.');
                    }
                }
            },
            error: function(xhr, status, error) {
                btn.prop('disabled', false).html('<i class="fas fa-save me-1"></i> Update Event');
                console.error("Event update error:", xhr.responseText);
                if (typeof Swal !== 'undefined') {
                    Swal.fire('Error', 'Server error updating event.', 'error');
                } else {
                    alert('Server error updating event.');
                }
            }
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

    // ==================== LIVE USER SEARCH APART FROM FILTER ====================
    const usersDirectory = <?php echo json_encode(array_map(function($u) use ($roles, $departments) {
        return [
            'id' => (int)$u['id'],
            'username' => $u['username'] ?? '',
            'full_name' => $u['full_name'] ?: ($u['username'] ?? ''),
            'email' => $u['email'] ?? '',
            'phone' => $u['phone'] ?? '',
            'role' => $roles[$u['role']] ?? ucfirst($u['role'] ?? 'User'),
            'role_key' => $u['role'] ?? 'user',
            'department' => $departments[$u['department']] ?? ($u['department'] ? ucfirst($u['department']) : 'General'),
            'profile_image' => $u['profile_image'] ?? '',
            'is_active' => (int)($u['is_active'] ?? 1)
        ];
    }, $all_users)); ?>;

    let activeUserSearchIdx = -1;

    function renderUserSearchResults(results, query) {
        const $dropdown = $('#userSearchResultsDropdown');
        if (!results || results.length === 0) {
            $dropdown.html(`
                <div class="p-3 text-center text-muted small">
                    <i class="fas fa-user-slash fa-2x mb-2 text-secondary opacity-50 d-block"></i>
                    <div>No users found for "<strong>${escapeHtml(query)}</strong>"</div>
                    <button type="button" class="btn btn-xs btn-outline-primary mt-2" onclick="openAddUser(); $('#userSearchResultsDropdown').hide();">
                        <i class="fas fa-user-plus me-1"></i> Add New User
                    </button>
                </div>
            `).show();
            return;
        }

        let html = `<div class="p-2 border-bottom bg-light d-flex justify-content-between align-items-center">
            <span class="small fw-bold text-dark text-uppercase" style="font-size: 0.7rem; letter-spacing: 0.5px;">Users Found (${results.length})</span>
            <span class="badge bg-secondary-subtle text-secondary" style="font-size: 0.65rem;">Press Enter to view</span>
        </div>`;

        results.forEach((u, idx) => {
            const initial = (u.username || 'U').charAt(0).toUpperCase();
            const avatarHtml = u.profile_image 
                ? `<img src="${escapeHtml(u.profile_image)}" class="user-search-avatar" onerror="this.outerHTML='<div class=\\'user-search-avatar-fallback\\'>${initial}</div>'">`
                : `<div class="user-search-avatar-fallback">${initial}</div>`;
            
            const statusBadge = u.is_active 
                ? `<span class="badge bg-success-subtle text-success border border-success-subtle" style="font-size: 0.65rem;">Active</span>`
                : `<span class="badge bg-warning-subtle text-warning border border-warning-subtle" style="font-size: 0.65rem;">Pending</span>`;

            html += `
                <a href="users.php?id=${u.id}" class="user-search-item ${idx === 0 ? 'active' : ''}" data-index="${idx}">
                    ${avatarHtml}
                    <div class="user-search-info flex-grow-1 text-truncate">
                        <div class="d-flex align-items-center justify-content-between">
                            <span class="user-search-name fw-bold text-dark text-truncate">${escapeHtml(u.full_name)}</span>
                            ${statusBadge}
                        </div>
                        <div class="d-flex align-items-center gap-1 small text-muted" style="font-size: 0.72rem;">
                            <span>@${escapeHtml(u.username)}</span>
                            <span>•</span>
                            <span class="text-primary fw-semibold">${escapeHtml(u.role)}</span>
                            <span>•</span>
                            <span>${escapeHtml(u.department)}</span>
                        </div>
                    </div>
                </a>
            `;
        });

        html += `<div class="p-2 text-center bg-light border-top small text-muted" style="font-size: 0.72rem;">
            Click to open profile or navigate with <kbd class="bg-white text-dark border">↑</kbd> <kbd class="bg-white text-dark border">↓</kbd> + <kbd class="bg-white text-dark border">Enter</kbd>
        </div>`;

        $dropdown.html(html).show();
        activeUserSearchIdx = 0;
    }

    function clearUserSearch() {
        $('#userLiveSearchInput').val('');
        $('#clearUserSearchBtn').hide();
        $('#userSearchResultsDropdown').hide().empty();
        activeUserSearchIdx = -1;
    }

    function escapeHtml(text) {
        if (!text) return '';
        return String(text)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    $(document).on('input', '#userLiveSearchInput', function() {
        const q = $(this).val().toLowerCase().trim();
        if (!q) {
            clearUserSearch();
            return;
        }
        $('#clearUserSearchBtn').show();
        const matched = usersDirectory.filter(u => {
            return (u.full_name && u.full_name.toLowerCase().includes(q)) ||
                   (u.username && u.username.toLowerCase().includes(q)) ||
                   (u.email && u.email.toLowerCase().includes(q)) ||
                   (u.phone && u.phone.toLowerCase().includes(q)) ||
                   (u.role && u.role.toLowerCase().includes(q)) ||
                   (u.department && u.department.toLowerCase().includes(q));
        });
        renderUserSearchResults(matched.slice(0, 8), q);
    });

    $(document).on('keydown', '#userLiveSearchInput', function(e) {
        const $items = $('#userSearchResultsDropdown .user-search-item');
        if (!$items.length || !$('#userSearchResultsDropdown').is(':visible')) return;

        if (e.key === 'ArrowDown') {
            e.preventDefault();
            activeUserSearchIdx = (activeUserSearchIdx + 1) % $items.length;
            $items.removeClass('active').eq(activeUserSearchIdx).addClass('active');
            $items.eq(activeUserSearchIdx)[0].scrollIntoView({ block: 'nearest' });
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            activeUserSearchIdx = (activeUserSearchIdx - 1 + $items.length) % $items.length;
            $items.removeClass('active').eq(activeUserSearchIdx).addClass('active');
            $items.eq(activeUserSearchIdx)[0].scrollIntoView({ block: 'nearest' });
        } else if (e.key === 'Enter') {
            e.preventDefault();
            const $target = $items.eq(activeUserSearchIdx >= 0 ? activeUserSearchIdx : 0);
            if ($target.length && $target.attr('href')) {
                window.location.href = $target.attr('href');
            }
        } else if (e.key === 'Escape') {
            clearUserSearch();
        }
    });

    $(document).on('click', function(e) {
        if (!$(e.target).closest('.user-live-search-wrapper').length) {
            $('#userSearchResultsDropdown').hide();
        }
    });

    // ==================== ROLES & DEPARTMENTS MANAGEMENT ====================
    function openRolesDeptModal() {
        new bootstrap.Modal(document.getElementById('rolesDeptModal')).show();
    }

    function openQuickAddRole() {
        new bootstrap.Modal(document.getElementById('quickAddRoleModal')).show();
    }

    function openQuickAddDept() {
        new bootstrap.Modal(document.getElementById('quickAddDeptModal')).show();
    }

    // Auto-slugify role name to system key
    $(document).on('input', '#modalRoleName, #quickRoleName', function() {
        const val = $(this).val();
        const slug = val.toLowerCase().replace(/[^a-z0-9]+/g, '_').replace(/^_+|_+$/g, '');
        if ($(this).attr('id') === 'modalRoleName') {
            $('#modalRoleKey').val(slug);
        } else {
            $('#quickRoleKey').val(slug);
        }
    });

    // Auto-generate department code from name
    $(document).on('input', '#modalDeptName, #quickDeptName', function() {
        const val = $(this).val();
        const code = val.replace(/[^a-zA-Z0-9]/g, '').substring(0, 5).toUpperCase();
        if ($(this).attr('id') === 'modalDeptName') {
            $('#modalDeptCode').val(code);
        } else {
            $('#quickDeptCode').val(code);
        }
    });

    // Submit Role Form
    function submitRoleForm(e, form, isQuick = false) {
        e.preventDefault();
        const formData = new FormData(form);
        formData.append('ajax', '1');

        const btn = $(form).find('button[type="submit"]');
        const originalBtnHtml = btn.html();
        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i> Saving...');

        $.ajax({
            url: 'users.php',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',
            success: function(resp) {
                btn.prop('disabled', false).html(originalBtnHtml);
                if (resp && resp.success) {
                    const roleKey = resp.role_key;
                    const roleName = resp.role_name;

                    // Append to all #role dropdowns if not existing
                    $('select#role').each(function() {
                        if (!$(this).find(`option[value="${roleKey}"]`).length) {
                            $(this).append(new Option(roleName, roleKey));
                        }
                        if (isQuick) {
                            $(this).val(roleKey);
                        }
                    });

                    // Hide modals
                    if (isQuick) {
                        const quickModalEl = document.getElementById('quickAddRoleModal');
                        if (quickModalEl) {
                            const qm = bootstrap.Modal.getInstance(quickModalEl) || new bootstrap.Modal(quickModalEl);
                            qm.hide();
                        }
                    } else {
                        const mainModalEl = document.getElementById('rolesDeptModal');
                        if (mainModalEl) {
                            const mm = bootstrap.Modal.getInstance(mainModalEl) || new bootstrap.Modal(mainModalEl);
                            mm.hide();
                        }
                    }

                    form.reset();

                    Swal.fire({
                        icon: 'success',
                        title: 'Role Added',
                        text: resp.message || `Role "${roleName}" added successfully!`,
                        timer: 2000,
                        showConfirmButton: false
                    });
                } else {
                    Swal.fire('Error', resp.message || 'Failed to add role', 'error');
                }
            },
            error: function() {
                btn.prop('disabled', false).html(originalBtnHtml);
                Swal.fire('Error', 'Server connection failure while adding role', 'error');
            }
        });
    }

    // Submit Department Form
    function submitDepartmentForm(e, form, isQuick = false) {
        e.preventDefault();
        const formData = new FormData(form);
        formData.append('ajax', '1');

        const btn = $(form).find('button[type="submit"]');
        const originalBtnHtml = btn.html();
        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i> Saving...');

        $.ajax({
            url: 'users.php',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',
            success: function(resp) {
                btn.prop('disabled', false).html(originalBtnHtml);
                if (resp && resp.success) {
                    const deptKey = resp.dept_key;
                    const deptName = resp.dept_name;

                    // Append to all #department dropdowns if not existing
                    $('select#department').each(function() {
                        if (!$(this).find(`option[value="${deptKey}"]`).length) {
                            $(this).append(new Option(deptName, deptKey));
                        }
                        if (isQuick) {
                            $(this).val(deptKey);
                        }
                    });

                    // Hide modals
                    if (isQuick) {
                        const quickModalEl = document.getElementById('quickAddDeptModal');
                        if (quickModalEl) {
                            const qm = bootstrap.Modal.getInstance(quickModalEl) || new bootstrap.Modal(quickModalEl);
                            qm.hide();
                        }
                    } else {
                        const mainModalEl = document.getElementById('rolesDeptModal');
                        if (mainModalEl) {
                            const mm = bootstrap.Modal.getInstance(mainModalEl) || new bootstrap.Modal(mainModalEl);
                            mm.hide();
                        }
                    }

                    form.reset();

                    Swal.fire({
                        icon: 'success',
                        title: 'Department Added',
                        text: resp.message || `Department "${deptName}" added successfully!`,
                        timer: 2000,
                        showConfirmButton: false
                    });
                } else {
                    Swal.fire('Error', resp.message || 'Failed to add department', 'error');
                }
            },
            error: function() {
                btn.prop('disabled', false).html(originalBtnHtml);
                Swal.fire('Error', 'Server connection failure while adding department', 'error');
            }
        });
    }

    function saveUserProfileBadgeAccess(e, form) {
        e.preventDefault();
        const fd = new FormData(form);
        fetch('id_badges.php', {
            method: 'POST',
            body: fd
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                Swal.fire({
                    icon: 'success',
                    title: 'Access Level Updated',
                    text: data.message,
                    timer: 1400,
                    showConfirmButton: false
                }).then(() => window.location.reload());
            } else {
                Swal.fire('Error', data.message || 'Could not update badge access.', 'error');
            }
        });
    }

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

        // Render User Profile ID Badge QR Code
        const qrContainer = document.getElementById('userProfileBadgeQr');
        if (qrContainer && typeof QRCode !== 'undefined') {
            qrContainer.innerHTML = '';
            new QRCode(qrContainer, {
                text: qrContainer.getAttribute('data-qr') || 'ABL-BDG',
                width: 68,
                height: 68,
                colorDark: '#0f172a',
                colorLight: '#ffffff',
                correctLevel: QRCode.CorrectLevel.M
            });
        }

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

        // ==========================================
        // User Activity Timeline (Search & 5-items Pagination)
        // ==========================================
        const timelineAllData = <?php echo json_encode($access_timeline_logs, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?> || [];
        const timelinePageSize = 5;
        let timelineCurrentPage = 1;
        let timelineFilteredData = [...timelineAllData];

        function escapeTimelineHtml(str) {
            if (!str) return '';
            return String(str)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        function getTimelineRoleLabel(role) {
            if (!role) return 'Staff';
            const r = String(role).toLowerCase().trim();
            const map = {
                'admin': 'Administrator',
                'manager': 'Operations Manager',
                'stock_manager': 'Stock Manager',
                'stock_controller': 'Stock Controller',
                'tech_lead': 'Technical Lead',
                'technician': 'AV Technician',
                'driver': 'Logistics Driver',
                'user': 'Production Staff'
            };
            if (map[r]) return map[r];
            return r.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
        }

        function renderActivityTimeline() {
            const $container = $('#timelineContainer');
            const $info = $('#timelinePaginationInfo');
            const $controls = $('#timelinePaginationControls');

            if (!$container.length) return;

            const totalItems = timelineFilteredData.length;
            const totalPages = Math.ceil(totalItems / timelinePageSize) || 1;

            if (timelineCurrentPage > totalPages) timelineCurrentPage = totalPages;
            if (timelineCurrentPage < 1) timelineCurrentPage = 1;

            if (totalItems === 0) {
                $container.html(`
                    <div class="text-center py-4 text-muted">
                        <i class="fas fa-search fa-2x mb-2 text-secondary opacity-50 d-block"></i>
                        <div class="small fw-semibold">No matching access logs found</div>
                        <div class="small text-muted">Try adjusting your search terms or filter criteria.</div>
                    </div>
                `);
                $info.text('Showing 0 to 0 of 0 entries');
                $controls.html('');
                return;
            }

            const startIndex = (timelineCurrentPage - 1) * timelinePageSize;
            const endIndex = Math.min(startIndex + timelinePageSize, totalItems);
            const pageData = timelineFilteredData.slice(startIndex, endIndex);

            let html = '';
            pageData.forEach(item => {
                const isDenied = item.access_status === 'denied';
                const isVenue = item.checkpoint_type === 'event_venue';
                const cpName = item.checkpoint_name || (isVenue ? 'Assigned Venue' : 'Stock Warehouse');

                // Determine Timeline Title
                let title = '';
                if (isDenied) {
                    title = `Access Denied - ${cpName}`;
                } else if (item.scan_action === 'check_out') {
                    title = `Signed out from ${isVenue ? 'venue' : 'stock'} - ${cpName}`;
                } else if (item.scan_action === 'check_in') {
                    title = `Entered ${isVenue ? 'Venue' : 'Stock Warehouse'} - ${cpName}`;
                } else {
                    title = `${isVenue ? 'Venue' : 'Stock'} Access Verified - ${cpName}`;
                }

                // Point Color: #ea5455 if denied, #2f3349 default
                const pointColor = isDenied ? '#ea5455' : '#2f3349';

                // Description text
                let desc = '';
                if (isDenied) {
                    desc = item.denial_reason ? `Access denied: ${item.denial_reason}` : 'Access denied by security checkpoint policy.';
                } else if (item.punctuality_status === 'on_time') {
                    desc = `Arrived on time for ${cpName}`;
                } else if (item.punctuality_status === 'late') {
                    desc = `Arrived late for ${cpName}`;
                } else if (item.punctuality_status === 'early') {
                    desc = `Early arrival logged at ${cpName}`;
                } else {
                    desc = item.notes ? item.notes : `Individual clearance verified at ${cpName}`;
                }
                if (item.notes && !isDenied && desc !== item.notes) {
                    desc += ` &bull; ${item.notes}`;
                }

                // Profile Image SVG Fallback
                const defaultSvg = "data:image/svg+xml;utf8,<svg xmlns=%22http://www.w3.org/2000/svg%22 width=%2240%22 height=%2240%22 viewBox=%220 0 40 40%22><rect width=%2240%22 height=%2240%22 fill=%22%23e2e8f0%22/><circle cx=%2220%22 cy=%2215%22 r=%227%22 fill=%22%2364748b%22/><path d=%22M8 36c0-6.6 5.4-12 12-12s12 5.4 12 12%22 fill=%22%2364748b%22/></svg>";
                const profileImg = (item.profile_image && item.profile_image.trim() !== '') ? escapeTimelineHtml(item.profile_image) : defaultSvg;

                const roleName = getTimelineRoleLabel(item.role);
                const deptName = item.department ? (item.department.charAt(0).toUpperCase() + item.department.slice(1)) : 'Operations';
                const badgeCode = item.badge_code || 'N/A';
                const timeAgo = item.time_ago || 'Recently';

                // Badges
                const statusBadge = isDenied 
                    ? `<span class="badge border border-danger text-danger bg-transparent px-2 py-1" style="border-radius: 4px; font-weight: 600;"><i class="fas fa-times-circle me-1 text-danger"></i>Denied</span>`
                    : `<span class="badge border border-success text-success bg-transparent px-2 py-1" style="border-radius: 4px; font-weight: 600;"><i class="fas fa-check-circle me-1 text-success"></i>Granted</span>`;

                const typeBadge = isVenue
                    ? `<span class="badge border border-primary text-primary bg-transparent px-2 py-1 ms-1" style="border-radius: 4px; font-weight: 600;"><i class="fas fa-hotel me-1 text-primary"></i>Venue</span>`
                    : `<span class="badge border border-info text-info bg-transparent px-2 py-1 ms-1" style="border-radius: 4px; font-weight: 600;"><i class="fas fa-warehouse me-1 text-info"></i>Stock</span>`;

                html += `
                    <div class="timeline-item">
                        <div class="timeline-point" style="background: ${pointColor};"></div>
                        <div class="timeline-header">
                            <h6 class="timeline-title" style="color: #2f3349; font-weight: 600;">${escapeTimelineHtml(title)}</h6>
                            <span class="timeline-time">${escapeTimelineHtml(timeAgo)}</span>
                        </div>
                        <p class="timeline-text" style="color: #6f6b7d;">${escapeTimelineHtml(desc)}</p>
                        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mt-1">
                            <div class="client-card-preview" style="background: #f8f7fa; border: 1px solid #dbdade; border-radius: 6px; padding: 6px 12px; display: inline-flex; align-items: center; gap: 10px;">
                                <img src="${profileImg}" onerror="this.onerror=null;this.src='${defaultSvg}';" style="width: 34px; height: 34px; border-radius: 50%; object-fit: cover;">
                                <div>
                                    <div class="fw-bold small text-dark leading-tight">${escapeTimelineHtml(item.full_name)} <span class="text-muted fw-normal" style="font-size: 0.74rem;">(${escapeTimelineHtml(roleName)})</span></div>
                                    <div class="text-muted" style="font-size: 0.72rem;">${escapeTimelineHtml(deptName)} &bull; Badge: <span class="fw-semibold text-dark">${escapeTimelineHtml(badgeCode)}</span></div>
                                </div>
                            </div>
                            <div class="d-flex align-items-center">
                                ${statusBadge}
                                ${typeBadge}
                            </div>
                        </div>
                    </div>
                `;
            });

            $container.html(html);
            $info.text(`Showing ${startIndex + 1} to ${endIndex} of ${totalItems} entries`);

            // Build Pagination Controls
            let paginationHtml = '';
            // Previous Button
            paginationHtml += `
                <button type="button" class="btn btn-xs btn-outline-secondary px-2 py-1 ${timelineCurrentPage === 1 ? 'disabled' : ''}" id="timelinePrevBtn" style="font-size: 0.78rem;">
                    <i class="fas fa-chevron-left me-1"></i> Previous
                </button>
            `;

            // Numeric Buttons
            const maxVisiblePages = 5;
            let startPage = Math.max(1, timelineCurrentPage - Math.floor(maxVisiblePages / 2));
            let endPage = Math.min(totalPages, startPage + maxVisiblePages - 1);
            if (endPage - startPage + 1 < maxVisiblePages) {
                startPage = Math.max(1, endPage - maxVisiblePages + 1);
            }

            if (startPage > 1) {
                paginationHtml += `<button type="button" class="btn btn-xs btn-outline-secondary px-2 py-1 timeline-page-btn" data-page="1" style="font-size: 0.78rem;">1</button>`;
                if (startPage > 2) {
                    paginationHtml += `<span class="px-1 text-muted">...</span>`;
                }
            }

            for (let p = startPage; p <= endPage; p++) {
                if (p === timelineCurrentPage) {
                    paginationHtml += `<span class="badge bg-dark px-2 py-1" style="font-size: 0.78rem; line-height: 1.4;">${p}</span>`;
                } else {
                    paginationHtml += `<button type="button" class="btn btn-xs btn-outline-secondary px-2 py-1 timeline-page-btn" data-page="${p}" style="font-size: 0.78rem;">${p}</button>`;
                }
            }

            if (endPage < totalPages) {
                if (endPage < totalPages - 1) {
                    paginationHtml += `<span class="px-1 text-muted">...</span>`;
                }
                paginationHtml += `<button type="button" class="btn btn-xs btn-outline-secondary px-2 py-1 timeline-page-btn" data-page="${totalPages}" style="font-size: 0.78rem;">${totalPages}</button>`;
            }

            // Next Button
            paginationHtml += `
                <button type="button" class="btn btn-xs btn-outline-secondary px-2 py-1 ${timelineCurrentPage === totalPages ? 'disabled' : ''}" id="timelineNextBtn" style="font-size: 0.78rem;">
                    Next <i class="fas fa-chevron-right ms-1"></i>
                </button>
            `;

            $controls.html(paginationHtml);
        }

        function filterActivityTimeline() {
            const query = ($('#timelineSearchInput').val() || '').trim().toLowerCase();
            const scope = $('#timelineScopeFilter').val() || 'all';

            timelineFilteredData = timelineAllData.filter(item => {
                // Scope Filter
                if (scope === 'event_venue' && item.checkpoint_type !== 'event_venue') return false;
                if (scope === 'stock_warehouse' && item.checkpoint_type !== 'stock_warehouse') return false;
                if (scope === 'granted' && item.access_status !== 'granted') return false;
                if (scope === 'denied' && item.access_status !== 'denied') return false;
                if (scope.startsWith('user_')) {
                    const uId = parseInt(scope.replace('user_', ''), 10);
                    if (parseInt(item.user_id, 10) !== uId) return false;
                }

                // Query Text Filter
                if (!query) return true;
                const corpus = [
                    item.full_name,
                    item.username,
                    item.role,
                    item.department,
                    item.checkpoint_name,
                    item.checkpoint_type,
                    item.badge_code,
                    item.scan_action,
                    item.access_status,
                    item.notes,
                    item.denial_reason,
                    item.punctuality_status
                ].filter(Boolean).join(' ').toLowerCase();

                return corpus.includes(query);
            });

            timelineCurrentPage = 1;
            renderActivityTimeline();
        }

        // Search Input event
        $('#timelineSearchInput').on('input', function() {
            if ($(this).val().trim().length > 0) {
                $('#timelineSearchClear').show();
            } else {
                $('#timelineSearchClear').hide();
            }
            filterActivityTimeline();
        });

        // Search Clear button
        $('#timelineSearchClear').on('click', function() {
            $('#timelineSearchInput').val('').focus();
            $(this).hide();
            filterActivityTimeline();
        });

        // Scope Filter event
        $('#timelineScopeFilter').on('change', function() {
            filterActivityTimeline();
        });

        // Pagination controls click handlers (delegated)
        $(document).on('click', '#timelinePrevBtn', function() {
            if (timelineCurrentPage > 1) {
                timelineCurrentPage--;
                renderActivityTimeline();
            }
        });

        $(document).on('click', '#timelineNextBtn', function() {
            const totalPages = Math.ceil(timelineFilteredData.length / timelinePageSize) || 1;
            if (timelineCurrentPage < totalPages) {
                timelineCurrentPage++;
                renderActivityTimeline();
            }
        });

        $(document).on('click', '.timeline-page-btn', function() {
            const page = parseInt($(this).data('page'), 10);
            if (page && page !== timelineCurrentPage) {
                timelineCurrentPage = page;
                renderActivityTimeline();
            }
        });

        // Initial Render of Activity Timeline
        renderActivityTimeline();
    });
</script>
<?php
$extra_scripts = ob_get_clean();
require_once 'views/partials/footer.php';
?>
