<?php
// drivers.php - Manage Drivers Dashboard
$current_page = 'drivers.php';
require_once 'bootstrap.php';

// Check authentication
if (!isLoggedIn()) {
    header('Location: login.php');
    exit();
}

require_once 'includes/functions.php';
require_once 'includes/db_connect.php';

// Only admins, managers or stock controllers should manage drivers
$user_role = getUserRole();
if (!isAdmin() && $user_role !== 'manager' && $user_role !== 'stock_controller') {
    header("Location: dashboard_full.php");
    exit();
}

$conn = getConnection();
if (!$conn) {
    die("Database connection failed.");
}

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
                $error = "Error deleting driver (might be in use by active batches). You can mark them inactive instead. " . $conn->error;
            }
        }
    }
}

// Fetch drivers and calculate metrics
$driversList = [];
$totalDrivers = 0;
$availableDrivers = 0;
$onTripDrivers = 0;
$maintenanceDrivers = 0;

$driversResult = $conn->query("SELECT * FROM drivers ORDER BY full_name ASC");
if ($driversResult) {
    $driversList = $driversResult->fetch_all(MYSQLI_ASSOC);
    $totalDrivers = count($driversList);
    foreach ($driversList as $d) {
        if ($d['status'] == 'available') $availableDrivers++;
        elseif ($d['status'] == 'on_trip') $onTripDrivers++;
        elseif ($d['status'] == 'maintenance') $maintenanceDrivers++;
    }
}

$pageTitle = "Manage Drivers - aBility";

// Include navigation layout header
require_once 'views/partials/header.php';
?>

<style>
    .glass-card {
        background: rgba(255, 255, 255, 0.85);
        backdrop-filter: blur(10px);
        -webkit-backdrop-filter: blur(10px);
        border: 1px solid rgba(255, 255, 255, 0.3);
        border-radius: 16px;
        box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.05);
    }

    .kpi-card {
        border-radius: 16px;
        border: none;
        box-shadow: 0 4px 20px rgba(0,0,0,0.02);
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }
    
    .kpi-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(0,0,0,0.05);
    }

    .status-dot-pulse {
        width: 8px;
        height: 8px;
        border-radius: 50%;
        display: inline-block;
        margin-right: 6px;
    }

    .badge-driver-status {
        font-size: 0.8rem;
        padding: 0.4em 0.8em;
        border-radius: 20px;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
    }

    .status-available {
        background-color: rgba(25, 135, 84, 0.12) !important;
        color: #198754 !important;
        border: 1px solid rgba(25, 135, 84, 0.2);
    }
    .status-available .status-dot-pulse {
        background-color: #198754;
        box-shadow: 0 0 8px #198754;
    }

    .status-ontrip {
        background-color: rgba(255, 193, 7, 0.15) !important;
        color: #b58100 !important;
        border: 1px solid rgba(255, 193, 7, 0.3);
    }
    .status-ontrip .status-dot-pulse {
        background-color: #ffc107;
        box-shadow: 0 0 8px #ffc107;
    }

    .status-maintenance {
        background-color: rgba(220, 53, 69, 0.12) !important;
        color: #dc3545 !important;
        border: 1px solid rgba(220, 53, 69, 0.2);
    }
    .status-maintenance .status-dot-pulse {
        background-color: #dc3545;
        box-shadow: 0 0 8px #dc3545;
    }

    .status-inactive {
        background-color: rgba(108, 117, 125, 0.12) !important;
        color: #6c757d !important;
        border: 1px solid rgba(108, 117, 125, 0.2);
    }
    .status-inactive .status-dot-pulse {
        background-color: #6c757d;
    }

    .table-container-drivers {
        border-radius: 16px;
        overflow: hidden;
        border: 1px solid rgba(0, 0, 0, 0.05);
    }
</style>

<div class="container-fluid mt-2">
    <!-- Header -->
    <div class="row mb-4 align-items-center">
        <div class="col-sm-6">
            <h2 class="fw-bold text-dark mb-1">
                <i class="fas fa-truck text-primary me-2"></i> Manage Drivers
            </h2>
            <p class="text-muted mb-0">Monitor driver statuses, license compliance, and transport vehicle assignments.</p>
        </div>
        <div class="col-sm-6 text-sm-end mt-2 mt-sm-0">
            <button class="btn btn-primary rounded-pill px-4" id="addDriverBtn" data-bs-toggle="modal" data-bs-target="#driverModal">
                <i class="fas fa-plus me-1"></i> Add Driver
            </button>
        </div>
    </div>

    <!-- Metrics Cards Row -->
    <div class="row g-4 mb-4">
        <!-- Card 1: Total Drivers -->
        <div class="col-md-3 col-sm-6">
            <div class="card kpi-card p-3 border-start border-4 border-primary bg-white">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <h6 class="text-muted text-uppercase small fw-bold mb-1">Total Drivers</h6>
                        <h3 class="fw-bold text-dark mb-0"><?php echo $totalDrivers; ?></h3>
                    </div>
                    <div class="bg-primary bg-opacity-10 text-primary p-3 rounded-3">
                        <i class="fas fa-users fa-lg"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Card 2: Available -->
        <div class="col-md-3 col-sm-6">
            <div class="card kpi-card p-3 border-start border-4 border-success bg-white">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <h6 class="text-muted text-uppercase small fw-bold mb-1">Available</h6>
                        <h3 class="fw-bold text-dark mb-0"><?php echo $availableDrivers; ?></h3>
                    </div>
                    <div class="bg-success bg-opacity-10 text-success p-3 rounded-3">
                        <i class="fas fa-check-circle fa-lg"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Card 3: On Trip -->
        <div class="col-md-3 col-sm-6">
            <div class="card kpi-card p-3 border-start border-4 border-warning bg-white">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <h6 class="text-muted text-uppercase small fw-bold mb-1">On Trip</h6>
                        <h3 class="fw-bold text-dark mb-0"><?php echo $onTripDrivers; ?></h3>
                    </div>
                    <div class="bg-warning bg-opacity-10 text-warning p-3 rounded-3">
                        <i class="fas fa-route fa-lg"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Card 4: In Maintenance -->
        <div class="col-md-3 col-sm-6">
            <div class="card kpi-card p-3 border-start border-4 border-danger bg-white">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <h6 class="text-muted text-uppercase small fw-bold mb-1">In Maintenance</h6>
                        <h3 class="fw-bold text-dark mb-0"><?php echo $maintenanceDrivers; ?></h3>
                    </div>
                    <div class="bg-danger bg-opacity-10 text-danger p-3 rounded-3">
                        <i class="fas fa-tools fa-lg"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Notifications -->
    <?php if ($message): ?>
        <div class="alert alert-success alert-dismissible border-0 shadow-sm rounded-4 p-3 mb-4 fade show">
            <i class="fas fa-check-circle text-success me-2"></i> <?php echo htmlspecialchars($message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible border-0 shadow-sm rounded-4 p-3 mb-4 fade show">
            <i class="fas fa-exclamation-triangle text-danger me-2"></i> <?php echo htmlspecialchars($error); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Main List Card -->
    <div class="card glass-card p-4 border-0">
        <div class="table-container-drivers">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 w-100" id="driversTable">
                    <thead class="table-light text-uppercase" style="font-size: 0.75rem; letter-spacing: 0.5px;">
                        <tr>
                            <th class="ps-4">Driver Name</th>
                            <th>Contact Information</th>
                            <th>Assigned Vehicle</th>
                            <th>License Number</th>
                            <th>Status Badge</th>
                            <th class="text-end pe-4" data-orderable="false">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($driversList as $d): ?>
                            <tr>
                                <td class="ps-4">
                                    <div class="d-flex align-items-center">
                                        <div class="bg-light p-2 rounded-circle me-3 border d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                            <i class="fas fa-user-tie text-secondary"></i>
                                        </div>
                                        <div>
                                            <span class="fw-bold text-dark d-block"><?php echo htmlspecialchars($d['full_name']); ?></span>
                                            <span class="text-muted small">Driver ID: #<?php echo $d['id']; ?></span>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="d-flex flex-column">
                                        <span class="text-dark small"><i class="fas fa-phone text-muted me-1 small"></i> <?php echo htmlspecialchars($d['phone_number']); ?></span>
                                        <?php if (!empty($d['email'])): ?>
                                            <span class="text-muted small"><i class="fas fa-envelope text-muted me-1 small"></i> <?php echo htmlspecialchars($d['email']); ?></span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <?php
                                            $vType = strtolower($d['vehicle_type']);
                                            $vIcon = 'fa-truck';
                                            if (strpos($vType, 'van') !== false) $vIcon = 'fa-shuttle-van';
                                            elseif (strpos($vType, 'car') !== false) $vIcon = 'fa-car';
                                            elseif (strpos($vType, 'pickup') !== false) $vIcon = 'fa-truck-pickup';
                                        ?>
                                        <div class="bg-light p-1 px-2 rounded border me-2" style="font-size: 0.85rem;">
                                            <i class="fas <?php echo $vIcon; ?> text-secondary me-1"></i>
                                            <?php echo htmlspecialchars($d['vehicle_type'] ?: 'N/A'); ?>
                                        </div>
                                        <code class="text-primary font-monospace"><?php echo htmlspecialchars($d['vehicle_number'] ?: 'N/A'); ?></code>
                                    </div>
                                </td>
                                <td>
                                    <code class="text-secondary bg-light p-1 rounded font-monospace small"><?php echo htmlspecialchars($d['license_number'] ?: 'N/A'); ?></code>
                                </td>
                                <td>
                                    <?php 
                                        $status = strtolower($d['status']);
                                        $badgeClass = 'status-inactive';
                                        if ($status === 'available') $badgeClass = 'status-available';
                                        elseif ($status === 'on_trip') $badgeClass = 'status-ontrip';
                                        elseif ($status === 'maintenance') $badgeClass = 'status-maintenance';
                                    ?>
                                    <span class="badge-driver-status <?php echo $badgeClass; ?>">
                                        <span class="status-dot-pulse"></span>
                                        <?php echo ucfirst($d['status']); ?>
                                    </span>
                                </td>
                                <td class="text-end pe-4">
                                    <div class="btn-group btn-group-sm pill overflow-hidden border">
                                        <button class="btn btn-light edit-driver-btn" 
                                                data-id="<?php echo $d['id']; ?>"
                                                data-fullname="<?php echo htmlspecialchars($d['full_name']); ?>"
                                                data-phone="<?php echo htmlspecialchars($d['phone_number']); ?>"
                                                data-email="<?php echo htmlspecialchars($d['email']); ?>"
                                                data-license="<?php echo htmlspecialchars($d['license_number']); ?>"
                                                data-vehicletype="<?php echo htmlspecialchars($d['vehicle_type']); ?>"
                                                data-vehiclenumber="<?php echo htmlspecialchars($d['vehicle_number']); ?>"
                                                data-status="<?php echo htmlspecialchars($d['status']); ?>"
                                                title="Edit Profile">
                                            <i class="fas fa-edit text-primary"></i>
                                        </button>
                                        <form method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this driver?');">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?php echo $d['id']; ?>">
                                            <button class="btn btn-light" title="Delete Driver"><i class="fas fa-trash text-danger"></i></button>
                                        </form>
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

<!-- Add/Edit Modal (Modern Glassmorphism Modal Style) -->
<div class="modal fade" id="driverModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" class="modal-content border-0 rounded-4 shadow-lg">
            <div class="modal-header bg-dark text-white rounded-top-4 py-3">
                <h5 class="modal-title fw-bold" id="modalTitle">
                    <i class="fas fa-user-plus me-2"></i>Add Driver
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <input type="hidden" name="action" id="formAction" value="add">
                <input type="hidden" name="id" id="driverId" value="">
                
                <div class="mb-3">
                    <label class="form-label small fw-bold text-uppercase text-muted">Full Name *</label>
                    <input type="text" name="full_name" id="full_name" class="form-control rounded-3" placeholder="e.g. Jean Damascene" required>
                </div>
                
                <div class="mb-3">
                    <label class="form-label small fw-bold text-uppercase text-muted">Phone Number *</label>
                    <input type="text" name="phone_number" id="phone_number" class="form-control rounded-3" placeholder="e.g. +250 788 000 000" required>
                </div>
                
                <div class="mb-3">
                    <label class="form-label small fw-bold text-uppercase text-muted">Email Address</label>
                    <input type="email" name="email" id="email" class="form-control rounded-3" placeholder="name@domain.com">
                </div>
                
                <div class="mb-3">
                    <label class="form-label small fw-bold text-uppercase text-muted">License Number</label>
                    <input type="text" name="license_number" id="license_number" class="form-control rounded-3" placeholder="License code / class">
                </div>
                
                <div class="row g-2 mb-3">
                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-uppercase text-muted">Vehicle Type</label>
                        <input type="text" name="vehicle_type" id="vehicle_type" class="form-control rounded-3" placeholder="e.g. Truck, Van, Car">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-uppercase text-muted">Plate Number</label>
                        <input type="text" name="vehicle_number" id="vehicle_number" class="form-control rounded-3" placeholder="e.g. RAD 123A">
                    </div>
                </div>
                
                <div class="mb-2">
                    <label class="form-label small fw-bold text-uppercase text-muted">Driver Status</label>
                    <select name="status" id="status" class="form-select rounded-3">
                        <option value="available">Available (In Depot)</option>
                        <option value="on_trip">On Active Trip (Transit)</option>
                        <option value="maintenance">Vehicle In Maintenance</option>
                        <option value="inactive">Inactive / Resigned</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer bg-light p-3 rounded-bottom-4 border-top">
                <button type="button" class="btn btn-secondary rounded-pill px-3 btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary rounded-pill px-4 btn-sm">
                    <i class="fas fa-save me-1"></i> Save Driver
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Reset Form for Add
        const addBtn = document.getElementById('addDriverBtn');
        if (addBtn) {
            addBtn.addEventListener('click', function() {
                document.getElementById('modalTitle').innerHTML = '<i class="fas fa-user-plus me-2"></i>Add Driver';
                document.getElementById('formAction').value = 'add';
                document.getElementById('driverId').value = '';
                document.getElementById('full_name').value = '';
                document.getElementById('phone_number').value = '';
                document.getElementById('email').value = '';
                document.getElementById('license_number').value = '';
                document.getElementById('vehicle_type').value = '';
                document.getElementById('vehicle_number').value = '';
                document.getElementById('status').value = 'available';
            });
        }

        // Populate Form for Edit
        document.querySelectorAll('.edit-driver-btn').forEach(button => {
            button.addEventListener('click', function() {
                document.getElementById('modalTitle').innerHTML = '<i class="fas fa-edit me-2"></i>Edit Driver Details';
                document.getElementById('formAction').value = 'edit';
                document.getElementById('driverId').value = this.dataset.id;
                document.getElementById('full_name').value = this.dataset.fullname;
                document.getElementById('phone_number').value = this.dataset.phone;
                document.getElementById('email').value = this.dataset.email;
                document.getElementById('license_number').value = this.dataset.license;
                document.getElementById('vehicle_type').value = this.dataset.vehicletype;
                document.getElementById('vehicle_number').value = this.dataset.vehiclenumber;
                document.getElementById('status').value = this.dataset.status;
                
                const driverModal = new bootstrap.Modal(document.getElementById('driverModal'));
                driverModal.show();
            });
        });

        // Initialize DataTable with 5 items per page
        if (typeof $.fn.DataTable !== 'undefined') {
            $('#driversTable').DataTable({
                "paging": true,
                "lengthChange": true,
                "searching": true,
                "ordering": true,
                "info": true,
                "autoWidth": false,
                "responsive": true,
                "pageLength": 5,
                "lengthMenu": [5, 10, 25, 50],
                "order": [[0, 'asc']], // Sort by name by default
                "language": {
                    "info": "Showing _START_ to _END_ of _TOTAL_ items",
                    "infoEmpty": "Showing 0 to 0 of 0 items",
                    "infoFiltered": "(filtered from _MAX_ total items)",
                    "lengthMenu": "Show _MENU_ items",
                    "search": "Search:"
                }
            });
        }
    });
</script>

<?php
// Include unified footer (which closes divs, footer markup, and imports jQuery/Bootstrap/DataTables JS)
require_once 'views/partials/footer.php';
?>
