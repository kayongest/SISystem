<?php
// manage_technicians.php
session_start();
require_once 'config/database.php';

// Check if user is admin
if ($_SESSION['role'] !== 'admin') {
    header('Location: index.php');
    exit;
}

$conn = getConnection();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_technician'])) {
        // Add new technician
        $username = $_POST['username'];
        $full_name = $_POST['full_name'];
        $email = $_POST['email'];
        $department = $_POST['department'];
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);

        $sql = "INSERT INTO users (username, full_name, email, department, password, role, is_active) 
                VALUES (?, ?, ?, ?, ?, 'technician', 1)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('sssss', $username, $full_name, $email, $department, $password);
        $stmt->execute();
    }
}

// Get all technicians
$sql = "SELECT id, username, full_name, email, department, role, is_active 
        FROM users 
        WHERE role IN ('technician', 'tech', 'user') 
        ORDER BY full_name";
$result = $conn->query($sql);
?>
<!DOCTYPE html>
<html>

<head>
    <title>Manage Technicians</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body>
    <div class="container mt-4">
        <h1>Manage Technicians</h1>

        <!-- Add Technician Form -->
        <div class="card mb-4">
            <div class="card-header">Add New Technician</div>
            <div class="card-body">
                <form method="POST">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label>Username</label>
                                <input type="text" name="username" class="form-control" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label>Full Name</label>
                                <input type="text" name="full_name" class="form-control">
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label>Email</label>
                                <input type="email" name="email" class="form-control">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label>Department</label>
                                <input type="text" name="department" class="form-control">
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label>Password</label>
                                <input type="password" name="password" class="form-control" required>
                            </div>
                        </div>
                    </div>
                    <button type="submit" name="add_technician" class="btn btn-primary">Add Technician</button>
                </form>
            </div>
        </div>

        <!-- Technicians List -->
        <div class="card">
            <div class="card-header">Existing Technicians</div>
            <div class="card-body">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Username</th>
                            <th>Full Name</th>
                            <th>Email</th>
                            <th>Department</th>
                            <th>Role</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($tech = $result->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo $tech['id']; ?></td>
                                <td><?php echo htmlspecialchars($tech['username']); ?></td>
                                <td><?php echo htmlspecialchars($tech['full_name'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($tech['email'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($tech['department'] ?? ''); ?></td>
                                <td><?php echo $tech['role']; ?></td>
                                <td>
                                    <?php if ($tech['is_active']): ?>
                                        <span class="badge bg-success">Active</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">Inactive</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>

</html>
<?php $conn->close(); ?>