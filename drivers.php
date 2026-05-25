<?php
require_once 'bootstrap.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit();
}

require_once 'includes/functions.php';

// Only admins or managers should manage drivers
if (!isAdmin() && getUserRole() !== 'manager' && getUserRole() !== 'stock_controller') {
    header("Location: dashboard_full.php");
    exit();
}

$conn = new mysqli('localhost', 'root', '', 'ability_db');
$conn->set_charset("utf8mb4");

$message = '';
$error = '';

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'add' || $_POST['action'] === 'edit') {
            $fullName = $conn->real_escape_string($_POST['full_name']);
            $phone = $conn->real_escape_string($_POST['phone_number']);
            $email = $conn->real_escape_string($_POST['email']);
            $license = $conn->real_escape_string($_POST['license_number']);
            $vehicleType = $conn->real_escape_string($_POST['vehicle_type']);
            $vehicleNum = $conn->real_escape_string($_POST['vehicle_number']);
            $status = $conn->real_escape_string($_POST['status']);

            if ($_POST['action'] === 'add') {
                $sql = "INSERT INTO drivers (full_name, phone_number, email, license_number, vehicle_type, vehicle_number, status) 
                        VALUES ('$fullName', '$phone', '$email', '$license', '$vehicleType', '$vehicleNum', '$status')";
                if ($conn->query($sql)) {
                    $message = "Driver added successfully.";
                } else {
                    $error = "Error adding driver: " . $conn->error;
                }
            } else {
                $id = (int)$_POST['id'];
                $sql = "UPDATE drivers SET 
                        full_name='$fullName', phone_number='$phone', email='$email', 
                        license_number='$license', vehicle_type='$vehicleType', 
                        vehicle_number='$vehicleNum', status='$status' 
                        WHERE id=$id";
                if ($conn->query($sql)) {
                    $message = "Driver updated successfully.";
                } else {
                    $error = "Error updating driver: " . $conn->error;
                }
            }
        } elseif ($_POST['action'] === 'delete') {
            $id = (int)$_POST['id'];
            $sql = "DELETE FROM drivers WHERE id=$id";
            if ($conn->query($sql)) {
                $message = "Driver deleted successfully.";
            } else {
                $error = "Error deleting driver (might be in use by batches). You can mark them inactive instead. " . $conn->error;
            }
        }
    }
}

$drivers = $conn->query("SELECT * FROM drivers ORDER BY full_name ASC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Drivers - Ability Inventory</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body class="bg-light">
    <?php include 'includes/navbar_main.php'; ?>

    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="fas fa-truck me-2"></i>Manage Drivers</h2>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#driverModal" onclick="resetForm()">
                <i class="fas fa-plus me-1"></i> Add Driver
            </button>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-success alert-dismissible fade show"><?php echo $message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show"><?php echo $error; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="card shadow-sm">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover table-bordered align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Name</th>
                                <th>Phone</th>
                                <th>Vehicle</th>
                                <th>License</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($d = $drivers->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($d['full_name']); ?></td>
                                <td><?php echo htmlspecialchars($d['phone_number']); ?></td>
                                <td>
                                    <?php echo htmlspecialchars($d['vehicle_type']); ?><br>
                                    <small class="text-muted"><?php echo htmlspecialchars($d['vehicle_number']); ?></small>
                                </td>
                                <td><?php echo htmlspecialchars($d['license_number']); ?></td>
                                <td>
                                    <?php 
                                    $badgeClass = 'bg-secondary';
                                    if ($d['status'] == 'available') $badgeClass = 'bg-success';
                                    elseif ($d['status'] == 'on_trip') $badgeClass = 'bg-warning text-dark';
                                    elseif ($d['status'] == 'maintenance') $badgeClass = 'bg-danger';
                                    ?>
                                    <span class="badge <?php echo $badgeClass; ?>"><?php echo ucfirst($d['status']); ?></span>
                                </td>
                                <td>
                                    <button class="btn btn-sm btn-outline-primary" onclick='editDriver(<?php echo json_encode($d); ?>)'>
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <form method="POST" class="d-inline" onsubmit="return confirm('Delete this driver?');">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?php echo $d['id']; ?>">
                                        <button class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button>
                                    </form>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Add/Edit Modal -->
    <div class="modal fade" id="driverModal" tabindex="-1">
        <div class="modal-dialog">
            <form method="POST" class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle">Add Driver</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" id="formAction" value="add">
                    <input type="hidden" name="id" id="driverId" value="">
                    
                    <div class="mb-3">
                        <label>Full Name *</label>
                        <input type="text" name="full_name" id="full_name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label>Phone Number *</label>
                        <input type="text" name="phone_number" id="phone_number" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label>Email</label>
                        <input type="email" name="email" id="email" class="form-control">
                    </div>
                    <div class="row mb-3">
                        <div class="col">
                            <label>License Number</label>
                            <input type="text" name="license_number" id="license_number" class="form-control">
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col">
                            <label>Vehicle Type</label>
                            <input type="text" name="vehicle_type" id="vehicle_type" class="form-control" placeholder="e.g. Truck, Van">
                        </div>
                        <div class="col">
                            <label>Plate Number</label>
                            <input type="text" name="vehicle_number" id="vehicle_number" class="form-control">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label>Status</label>
                        <select name="status" id="status" class="form-select">
                            <option value="available">Available</option>
                            <option value="on_trip">On Trip</option>
                            <option value="maintenance">Maintenance</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Driver</button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function resetForm() {
            document.getElementById('modalTitle').innerText = 'Add Driver';
            document.getElementById('formAction').value = 'add';
            document.getElementById('driverId').value = '';
            document.getElementById('full_name').value = '';
            document.getElementById('phone_number').value = '';
            document.getElementById('email').value = '';
            document.getElementById('license_number').value = '';
            document.getElementById('vehicle_type').value = '';
            document.getElementById('vehicle_number').value = '';
            document.getElementById('status').value = 'available';
        }

        function editDriver(d) {
            document.getElementById('modalTitle').innerText = 'Edit Driver';
            document.getElementById('formAction').value = 'edit';
            document.getElementById('driverId').value = d.id;
            document.getElementById('full_name').value = d.full_name;
            document.getElementById('phone_number').value = d.phone_number;
            document.getElementById('email').value = d.email;
            document.getElementById('license_number').value = d.license_number;
            document.getElementById('vehicle_type').value = d.vehicle_type;
            document.getElementById('vehicle_number').value = d.vehicle_number;
            document.getElementById('status').value = d.status;
            
            var modal = new bootstrap.Modal(document.getElementById('driverModal'));
            modal.show();
        }
    </script>
</body>
</html>
