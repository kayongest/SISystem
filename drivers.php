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

function handleDriverImageUpload($file) {
    $targetDir = "uploads/drivers/";
    if (!file_exists($targetDir)) {
        mkdir($targetDir, 0777, true);
    }
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
    if (!in_array($ext, $allowed)) {
        throw new Exception("Invalid image format. Allowed formats: JPG, PNG, WEBP, GIF.");
    }
    $filename = uniqid('driver_') . '.' . $ext;
    $targetPath = $targetDir . $filename;
    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
        $baseUrl = defined('BASE_URL') ? BASE_URL : '/ability_app_main/';
        return '/' . ltrim($baseUrl, '/') . $targetPath;
    }
    throw new Exception("Failed to upload driver profile image.");
}

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
            $profileImage = $_POST['existing_profile_image'] ?? '';

            if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
                try {
                    $profileImage = handleDriverImageUpload($_FILES['profile_image']);
                } catch (Exception $e) {
                    $error = $e->getMessage();
                }
            }

            if (empty($error)) {
                $profileImgEsc = $conn->real_escape_string($profileImage);
                if ($_POST['action'] === 'add') {
                    $sql = "INSERT INTO drivers (full_name, phone_number, email, profile_image, license_number, vehicle_type, vehicle_number, status) 
                            VALUES ('$fullName', '$phone', '$email', '$profileImgEsc', '$license', '$vehicleType', '$vehicleNum', '$status')";
                    if ($conn->query($sql)) {
                        $message = "Driver added successfully.";
                    } else {
                        $error = "Error adding driver: " . $conn->error;
                    }
                } else {
                    $id = (int)$_POST['id'];
                    $sql = "UPDATE drivers SET 
                            full_name='$fullName', phone_number='$phone', email='$email', profile_image='$profileImgEsc', 
                            license_number='$license', vehicle_type='$vehicleType', 
                            vehicle_number='$vehicleNum', status='$status' 
                            WHERE id=$id";
                    if ($conn->query($sql)) {
                        $message = "Driver updated successfully.";
                    } else {
                        $error = "Error updating driver: " . $conn->error;
                    }
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
                                        <?php if (!empty($d['profile_image'])): ?>
                                            <img src="<?php echo htmlspecialchars($d['profile_image']); ?>" class="rounded-circle me-3 border shadow-sm" style="width: 42px; height: 42px; object-fit: cover;" onerror="this.outerHTML='<div class=\'bg-light p-2 rounded-circle me-3 border d-flex align-items-center justify-content-center\' style=\'width: 42px; height: 42px;\'><i class=\'fas fa-user-tie text-secondary\'></i></div>'">
                                        <?php else: ?>
                                            <div class="bg-light p-2 rounded-circle me-3 border d-flex align-items-center justify-content-center" style="width: 42px; height: 42px;">
                                                <i class="fas fa-user-tie text-secondary"></i>
                                            </div>
                                        <?php endif; ?>
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
                                         <button class="btn btn-light view-driver-btn" 
                                                 data-id="<?php echo $d['id']; ?>"
                                                 title="View Driver Details">
                                             <i class="fas fa-eye text-info"></i>
                                         </button>
                                         <button class="btn btn-light edit-driver-btn" 
                                                 data-id="<?php echo $d['id']; ?>"
                                                 data-fullname="<?php echo htmlspecialchars($d['full_name']); ?>"
                                                 data-phone="<?php echo htmlspecialchars($d['phone_number']); ?>"
                                                 data-email="<?php echo htmlspecialchars($d['email']); ?>"
                                                 data-image="<?php echo htmlspecialchars($d['profile_image'] ?? ''); ?>"
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

<div class="modal fade" id="driverModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" enctype="multipart/form-data" class="modal-content border-0 rounded-4 shadow-lg">
            <div class="modal-header bg-dark text-white rounded-top-4 py-3">
                <h5 class="modal-title fw-bold" id="modalTitle">
                    <i class="fas fa-user-plus me-2"></i>Add Driver
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <input type="hidden" name="action" id="formAction" value="add">
                <input type="hidden" name="id" id="driverId" value="">
                <input type="hidden" name="existing_profile_image" id="existing_profile_image" value="">
                
                <div class="mb-3">
                    <label class="form-label small fw-bold text-uppercase text-muted">Driver Profile Photo</label>
                    <input type="file" name="profile_image" id="profile_image" class="form-control rounded-3" accept="image/*">
                    <div id="driverImagePreviewContainer" class="mt-2 text-center" style="display:none;">
                        <img id="driverImagePreview" src="" class="rounded-circle border shadow-sm" style="width: 70px; height: 70px; object-fit: cover;">
                    </div>
                </div>

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

<!-- View Driver Modal -->
<div class="modal fade" id="viewDriverModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content border-0 rounded-4 shadow-lg overflow-hidden">
            <div class="modal-header bg-primary text-white py-3">
                <h5 class="modal-title fw-bold d-flex align-items-center">
                    <i class="fas fa-id-card me-2"></i><span id="viewDriverName">Driver Profile</span>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4 bg-light">
                <div class="row g-3 mb-4">
                    <!-- Driver Header Card -->
                    <div class="col-md-6">
                        <div class="card border-0 shadow-sm p-3 rounded-3 h-100 bg-white">
                            <div class="d-flex align-items-center mb-3">
                                <div id="viewDriverAvatarContainer" class="me-3">
                                    <div class="bg-primary bg-opacity-10 text-primary p-3 rounded-circle fs-3 d-flex align-items-center justify-content-center" style="width: 56px; height: 56px;">
                                        <i class="fas fa-user-tie"></i>
                                    </div>
                                </div>
                                <div>
                                    <h5 class="fw-bold mb-0 text-dark" id="viewDriverTitle">--</h5>
                                    <small class="text-muted" id="viewDriverIdText">Driver ID: --</small>
                                </div>
                            </div>
                            <div class="d-flex align-items-center gap-2">
                                <span class="text-muted small">Status:</span>
                                <span id="viewDriverStatusBadge" class="badge bg-secondary">--</span>
                            </div>
                        </div>
                    </div>

                    <!-- Contact & License Card -->
                    <div class="col-md-6">
                        <div class="card border-0 shadow-sm p-3 rounded-3 h-100 bg-white">
                            <h6 class="fw-bold text-muted small text-uppercase mb-3"><i class="fas fa-address-book me-1 text-primary"></i> Contact & License</h6>
                            <div class="mb-2">
                                <span class="text-muted small d-block">Phone Number:</span>
                                <strong class="text-dark" id="viewDriverPhone">--</strong>
                            </div>
                            <div class="mb-2">
                                <span class="text-muted small d-block">Email Address:</span>
                                <span class="text-dark fw-semibold" id="viewDriverEmail">--</span>
                            </div>
                            <div>
                                <span class="text-muted small d-block">License Number:</span>
                                <code class="text-primary bg-light px-2 py-1 rounded" id="viewDriverLicense">--</code>
                            </div>
                        </div>
                    </div>

                    <!-- Vehicle Card -->
                    <div class="col-md-12">
                        <div class="card border-0 shadow-sm p-3 rounded-3 bg-white">
                            <h6 class="fw-bold text-muted small text-uppercase mb-2"><i class="fas fa-truck me-1 text-success"></i> Assigned Vehicle</h6>
                            <div class="d-flex align-items-center justify-content-between">
                                <div>
                                    <span class="text-muted small d-block">Vehicle Type:</span>
                                    <strong class="text-dark" id="viewDriverVehicleType">--</strong>
                                </div>
                                <div>
                                    <span class="text-muted small d-block">Plate Number:</span>
                                    <code class="fs-6 text-dark bg-light px-3 py-1 rounded border" id="viewDriverVehicleNumber">--</code>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Assigned Trips / Movements -->
                <div class="card border-0 shadow-sm rounded-3 overflow-hidden bg-white">
                    <div class="card-header bg-white border-0 py-3 d-flex align-items-center justify-content-between">
                        <h6 class="fw-bold text-dark m-0"><i class="fas fa-route text-primary me-2"></i> Recent Transport Activity / Movements</h6>
                        <span class="badge bg-primary bg-opacity-10 text-primary" id="viewTripCount">0 Trips</span>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0" id="driverTripsTable">
                            <thead class="bg-light text-muted small text-uppercase">
                                <tr>
                                    <th class="ps-3">Batch / Event</th>
                                    <th>Route</th>
                                    <th>Movement</th>
                                    <th>Status</th>
                                    <th class="pe-3 text-end">Date</th>
                                </tr>
                            </thead>
                            <tbody id="driverTripsBody">
                                <tr>
                                    <td colspan="5" class="text-center py-4 text-muted">Click View Driver to load activity</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-white border-top py-2">
                <button type="button" class="btn btn-secondary rounded-3" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
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
                document.getElementById('existing_profile_image').value = '';
                document.getElementById('profile_image').value = '';
                document.getElementById('driverImagePreviewContainer').style.display = 'none';
            });
        }

        // Live image preview on file input change
        $('#profile_image').on('change', function() {
            const file = this.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    $('#driverImagePreview').attr('src', e.target.result);
                    $('#driverImagePreviewContainer').show();
                };
                reader.readAsDataURL(file);
            }
        });

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
                
                const existingImg = this.dataset.image || '';
                document.getElementById('existing_profile_image').value = existingImg;
                document.getElementById('profile_image').value = '';
                
                if (existingImg) {
                    $('#driverImagePreview').attr('src', existingImg);
                    $('#driverImagePreviewContainer').show();
                } else {
                    $('#driverImagePreviewContainer').hide();
                }
                
                const driverModal = new bootstrap.Modal(document.getElementById('driverModal'));
                driverModal.show();
            });
        });

        // View Driver Handler
        $(document).on('click', '.view-driver-btn', function() {
            const driverId = $(this).data('id');
            
            $('#viewDriverName').text('Loading Driver...');
            $('#viewDriverTitle').text('Loading...');
            $('#viewDriverIdText').text('Driver ID: #' + driverId);
            $('#viewDriverPhone').text('Loading...');
            $('#viewDriverEmail').text('Loading...');
            $('#viewDriverLicense').text('Loading...');
            $('#viewDriverVehicleType').text('Loading...');
            $('#viewDriverVehicleNumber').text('Loading...');
            $('#viewTripCount').text('Loading...');
            $('#viewDriverAvatarContainer').html('<div class="bg-primary bg-opacity-10 text-primary p-3 rounded-circle fs-3 d-flex align-items-center justify-content-center" style="width: 56px; height: 56px;"><i class="fas fa-user-tie"></i></div>');
            $('#driverTripsBody').html('<tr><td colspan="5" class="text-center py-4 text-muted"><i class="fas fa-spinner fa-spin me-2"></i> Loading transport history...</td></tr>');
            
            const modal = new bootstrap.Modal(document.getElementById('viewDriverModal'));
            modal.show();

            $.ajax({
                url: 'api/drivers/get_details.php',
                method: 'GET',
                data: { id: driverId },
                dataType: 'json',
                success: function(res) {
                    if (res.success && res.driver) {
                        const d = res.driver;
                        $('#viewDriverName').text(d.full_name);
                        $('#viewDriverTitle').text(d.full_name);
                        $('#viewDriverIdText').text('Driver ID: #' + d.id);
                        $('#viewDriverPhone').text(d.phone_number || 'N/A');
                        $('#viewDriverEmail').text(d.email || 'N/A');
                        $('#viewDriverLicense').text(d.license_number || 'N/A');
                        $('#viewDriverVehicleType').text(d.vehicle_type || 'N/A');
                        $('#viewDriverVehicleNumber').text(d.vehicle_number || 'N/A');

                        if (d.profile_image) {
                            $('#viewDriverAvatarContainer').html(`<img src="${d.profile_image}" class="rounded-circle border shadow-sm" style="width: 56px; height: 56px; object-fit: cover;">`);
                        } else {
                            $('#viewDriverAvatarContainer').html('<div class="bg-primary bg-opacity-10 text-primary p-3 rounded-circle fs-3 d-flex align-items-center justify-content-center" style="width: 56px; height: 56px;"><i class="fas fa-user-tie"></i></div>');
                        }

                        // Status Badge
                        let statusBadge = '<span class="badge bg-secondary">Inactive</span>';
                        if (d.status === 'available') statusBadge = '<span class="badge bg-success">Available</span>';
                        else if (d.status === 'on_trip') statusBadge = '<span class="badge bg-primary">On Trip</span>';
                        else if (d.status === 'maintenance') statusBadge = '<span class="badge bg-warning text-dark">Maintenance</span>';
                        $('#viewDriverStatusBadge').html(statusBadge);

                        // Render Trips Table
                        if (res.trips && res.trips.length > 0) {
                            $('#viewTripCount').text(res.trips.length + ' Trips');
                            let rowsHtml = '';
                            res.trips.forEach(t => {
                                let statusClass = 'bg-secondary';
                                const st = (t.status || '').toLowerCase();
                                if (st === 'completed' || st === 'approved') statusClass = 'bg-success';
                                else if (st === 'pending' || st === 'in_transit') statusClass = 'bg-warning text-dark';
                                
                                const eventOrBatch = t.event_name ? t.event_name : t.batch_number;
                                const src = t.source_name ? t.source_name : 'Warehouse';
                                const dest = t.destination_name ? t.destination_name : 'Venue';
                                
                                rowsHtml += `
                                    <tr>
                                        <td class="ps-3">
                                            <div class="fw-bold text-dark">${escapeHtml(eventOrBatch)}</div>
                                            <small class="text-muted">${escapeHtml(t.batch_number)}</small>
                                        </td>
                                        <td>
                                            <small class="d-block text-dark"><i class="fas fa-map-marker-alt text-danger me-1"></i>${escapeHtml(src)} &rarr; ${escapeHtml(dest)}</small>
                                        </td>
                                        <td>
                                            <span class="badge bg-light text-dark border">${escapeHtml(t.movement_type || 'Transport')}</span>
                                        </td>
                                        <td>
                                            <span class="badge ${statusClass}">${escapeHtml(t.status || 'Active')}</span>
                                        </td>
                                        <td class="pe-3 text-end text-muted small">
                                            ${t.created_at ? t.created_at.substring(0, 10) : 'N/A'}
                                        </td>
                                    </tr>
                                `;
                            });
                            $('#driverTripsBody').html(rowsHtml);
                        } else {
                            $('#viewTripCount').text('0 Trips');
                            $('#driverTripsBody').html('<tr><td colspan="5" class="text-center py-4 text-muted"><i class="fas fa-info-circle me-1 text-info"></i> No recent transport movements found for this driver.</td></tr>');
                        }
                    } else {
                        alert(res.error || 'Failed to load driver details');
                    }
                },
                error: function() {
                    $('#driverTripsBody').html('<tr><td colspan="5" class="text-center text-danger py-4">Error loading transport history.</td></tr>');
                }
            });
        });

        function escapeHtml(unsafe) {
            if (!unsafe) return '';
            return unsafe
                .toString()
                .replace(/&/g, "&amp;")
                .replace(/</g, "&lt;")
                .replace(/>/g, "&gt;")
                .replace(/"/g, "&quot;")
                .replace(/'/g, "&#039;");
        }

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
