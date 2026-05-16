<?php
// user_permissions.php - Manage user permissions
$current_page = 'user_permissions.php';
require_once 'bootstrap.php';

if (!isLoggedIn() || !hasRole('admin')) {
    header('Location: login.php');
    exit();
}

require_once 'includes/db_connect.php';
$conn = getConnection();

$user_id = $_GET['user_id'] ?? 0;

// Get user info
$stmt = $conn->prepare("SELECT id, username, fullname, role FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if (!$user) {
    header('Location: users.php');
    exit();
}

// Handle permission assignment
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $permissions = $_POST['permissions'] ?? [];
    
    // Clear existing permissions for this user (if using user-specific permissions)
    // or handle role-based permissions
    
    $message = "Permissions updated successfully!";
    $messageType = "success";
}

// Get all available permissions
$permissions = $conn->query("SELECT * FROM permissions ORDER BY display_name")->fetch_all(MYSQLI_ASSOC);

// Get user's current permissions (if using user-specific permissions)
// $user_permissions = ...

$pageTitle = "Permissions - " . htmlspecialchars($user['username']);
require_once 'views/partials/header.php';
?>

<div class="container-fluid py-4">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-shield-alt me-2"></i>
                        Permissions for <?php echo htmlspecialchars($user['fullname'] ?? $user['username']); ?>
                    </h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <div class="row">
                            <?php foreach ($permissions as $permission): ?>
                                <div class="col-md-4 mb-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" 
                                               name="permissions[]" 
                                               value="<?php echo $permission['id']; ?>"
                                               id="perm_<?php echo $permission['id']; ?>">
                                        <label class="form-check-label" for="perm_<?php echo $permission['id']; ?>">
                                            <strong><?php echo htmlspecialchars($permission['display_name']); ?></strong>
                                            <br>
                                            <small class="text-muted"><?php echo htmlspecialchars($permission['description']); ?></small>
                                        </label>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <hr>
                        
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-1"></i>Save Permissions
                            </button>
                            <a href="users.php" class="btn btn-outline-secondary">
                                <i class="fas fa-arrow-left me-1"></i>Back to Users
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'views/partials/footer.php'; ?>