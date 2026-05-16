<?php
// stock_locations.php - Stock Locations Management with Working CRUD
require_once 'bootstrap.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit();
}

require_once 'includes/functions.php';

// Get database connection
function getDBConnection()
{
    static $pdo = null;

    if ($pdo === null) {
        try {
            $host = 'localhost';
            $dbname = 'ability_db';
            $username = 'root';
            $password = '';

            $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            die("Connection failed: " . $e->getMessage());
        }
    }

    return $pdo;
}

// Initialize database connection
$pdo = getDBConnection();

$pageTitle = "Stock Locations - aBility";
$showBreadcrumb = true;
$breadcrumbItems = [
    'Dashboard' => 'dashboard_full.php',
    'Settings' => '#',
    'Stock Locations' => ''
];

// Pagination settings
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$itemsPerPage = 6;

// Handle form submissions
$response = ['success' => false, 'message' => '', 'type' => 'error'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'add') {
            // Validate required fields
            if (empty($_POST['name']) || empty($_POST['location']) || empty($_POST['controller_name'])) {
                throw new Exception("Name, Location, and Controller Name are required fields.");
            }

            $name = trim($_POST['name']);
            $location = trim($_POST['location']);
            $controller_name = trim($_POST['controller_name']);
            $controller_phone = trim($_POST['controller_phone'] ?? '');
            $controller_email = trim($_POST['controller_email'] ?? '');
            $open_time = $_POST['open_time'] ?? '09:00:00';
            $close_time = $_POST['close_time'] ?? '17:00:00';
            $description = trim($_POST['description'] ?? '');
            $is_active = isset($_POST['is_active']) ? 1 : 0;

            $stmt = $pdo->prepare("
                INSERT INTO stock_locations 
                (name, location, controller_name, controller_phone, controller_email, open_time, close_time, description, is_active) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$name, $location, $controller_name, $controller_phone, $controller_email, $open_time, $close_time, $description, $is_active]);

            $response = [
                'success' => true,
                'message' => 'Stock location added successfully!',
                'type' => 'success'
            ];
        } elseif ($action === 'edit') {
            // Validate required fields
            if (empty($_POST['id']) || empty($_POST['name']) || empty($_POST['location']) || empty($_POST['controller_name'])) {
                throw new Exception("ID, Name, Location, and Controller Name are required fields.");
            }

            $id = (int)$_POST['id'];
            $name = trim($_POST['name']);
            $location = trim($_POST['location']);
            $controller_name = trim($_POST['controller_name']);
            $controller_phone = trim($_POST['controller_phone'] ?? '');
            $controller_email = trim($_POST['controller_email'] ?? '');
            $open_time = $_POST['open_time'] ?? '09:00:00';
            $close_time = $_POST['close_time'] ?? '17:00:00';
            $description = trim($_POST['description'] ?? '');
            $is_active = isset($_POST['is_active']) ? 1 : 0;

            $stmt = $pdo->prepare("
                UPDATE stock_locations 
                SET name = ?, location = ?, controller_name = ?, controller_phone = ?, 
                    controller_email = ?, open_time = ?, close_time = ?, description = ?, is_active = ?
                WHERE id = ?
            ");
            $stmt->execute([$name, $location, $controller_name, $controller_phone, $controller_email, $open_time, $close_time, $description, $is_active, $id]);

            $response = [
                'success' => true,
                'message' => 'Stock location updated successfully!',
                'type' => 'success'
            ];
        } elseif ($action === 'delete') {
            if (empty($_POST['id'])) {
                throw new Exception("ID is required for deletion.");
            }

            $id = (int)$_POST['id'];

            // Check if location is used in items
            $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM items WHERE stock_location = ? OR storage_location = ?");
            $checkStmt->execute([$id, $id]);
            $itemCount = $checkStmt->fetchColumn();

            if ($itemCount > 0) {
                throw new Exception("Cannot delete location that is in use by items.");
            }

            $stmt = $pdo->prepare("DELETE FROM stock_locations WHERE id = ?");
            $stmt->execute([$id]);

            $response = [
                'success' => true,
                'message' => 'Stock location deleted successfully!',
                'type' => 'success'
            ];
        }
    } catch (Exception $e) {
        $response = [
            'success' => false,
            'message' => $e->getMessage(),
            'type' => 'error'
        ];
    }

    // Return JSON response for AJAX
    header('Content-Type: application/json');
    echo json_encode($response);
    exit();
}

// Get total count for pagination
try {
    $countStmt = $pdo->query("SELECT COUNT(*) FROM stock_locations");
    $totalItems = $countStmt->fetchColumn();
    $totalPages = ceil($totalItems / $itemsPerPage);

    if ($page < 1) $page = 1;
    if ($page > $totalPages && $totalPages > 0) $page = $totalPages;

    $offset = ($page - 1) * $itemsPerPage;
    $offset = max(0, $offset);
} catch (Exception $e) {
    $totalItems = 0;
    $totalPages = 1;
    $offset = 0;
}

// Get stock locations with pagination
$locations = [];
try {
    $limit = (int)$itemsPerPage;
    $offset = (int)$offset;
    $sql = "SELECT * FROM stock_locations ORDER BY name LIMIT $limit OFFSET $offset";
    $stmt = $pdo->query($sql);
    $locations = $stmt->fetchAll();
} catch (Exception $e) {
    $error = "Error loading locations: " . $e->getMessage();
}

// Get item counts
$itemCounts = [];
try {
    $countStmt = $pdo->query("
        SELECT stock_location, COUNT(*) as count 
        FROM items 
        WHERE stock_location IS NOT NULL 
        GROUP BY stock_location
    ");
    $countResults = $countStmt->fetchAll();
    foreach ($countResults as $row) {
        $itemCounts[$row['stock_location']] = $row['count'];
    }
} catch (Exception $e) {
    // Ignore errors
}

require_once 'views/partials/header.php';
?>

<!-- Load jQuery and Bootstrap -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>

<style>
    .card {
        transition: transform 0.2s, box-shadow 0.2s;
        border: none;
        overflow: hidden;
    }

    .card:hover {
        transform: translateY(-4px);
        box-shadow: 0 8px 24px rgba(0, 0, 0, 0.12) !important;
    }

    .card .col-md-4 {
        background: linear-gradient(135deg, #1f5e4f 0%, #2a7f6e 100%);
        min-height: 200px;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .card .col-md-4.inactive {
        background: linear-gradient(135deg, #6c757d 0%, #8f959e 100%);
    }

    .badge {
        font-size: 0.7rem;
        padding: 3px 6px;
    }

    .btn-sm {
        padding: 0.2rem 0.5rem;
        font-size: 0.75rem;
    }

    .pagination {
        gap: 5px;
    }

    .page-link {
        color: #1f5e4f;
        border-radius: 8px;
        border: 1px solid #dee2e6;
        padding: 0.5rem 1rem;
    }

    .page-link:hover {
        background-color: #ecf5f2;
        border-color: #1f5e4f;
        color: #1f5e4f;
    }

    .page-item.active .page-link {
        background-color: #1f5e4f;
        border-color: #1f5e4f;
        color: white;
    }
</style>

<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">
            <i class="fas fa-warehouse me-2"></i>Stock Locations
        </h1>
        <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addLocationModal">
            <i class="fas fa-plus me-2"></i>Add Location
        </button>
    </div>

    <!-- Locations Grid -->
    <div class="row">
        <?php if (empty($locations)): ?>
            <div class="col-12">
                <div class="card shadow mb-4">
                    <div class="card-body text-center py-5">
                        <i class="fas fa-warehouse fa-4x text-muted mb-3"></i>
                        <h5>No Stock Locations Found</h5>
                        <p class="text-muted">Add your first stock location to get started</p>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addLocationModal">
                            <i class="fas fa-plus me-2"></i>Add Location
                        </button>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <?php foreach ($locations as $location): ?>
                <div class="col-md-6 col-xl-4 mb-4">
                    <div class="card h-100 shadow-sm">
                        <div class="row g-0 h-100">
                            <!-- Left Column - Icon/Avatar -->
                            <div class="col-md-4 d-flex align-items-center justify-content-center text-white <?php echo $location['is_active'] ? '' : 'inactive'; ?>"
                                style="<?php echo $location['is_active'] ? 'background: linear-gradient(135deg, #1f5e4f 0%, #2a7f6e 100%);' : 'background: linear-gradient(135deg, #6c757d 0%, #8f959e 100%);'; ?>">
                                <div class="text-center p-3">
                                    <i class="fas fa-warehouse fa-3x mb-2"></i>
                                    <span class="badge bg-light text-dark mt-2">
                                        <?php echo $itemCounts[$location['id']] ?? 0; ?> items
                                    </span>
                                </div>
                            </div>

                            <!-- Right Column - Details -->
                            <div class="col-md-8">
                                <div class="card-body p-3">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <h5 class="card-title mb-0"><?php echo htmlspecialchars($location['name']); ?></h5>
                                        <span class="badge <?php echo $location['is_active'] ? 'bg-success' : 'bg-secondary'; ?>">
                                            <?php echo $location['is_active'] ? 'ACTIVE' : 'INACTIVE'; ?>
                                        </span>
                                    </div>

                                    <div class="mb-2 small">
                                        <i class="fas fa-map-marker-alt text-primary me-2" style="width: 16px;"></i>
                                        <span class="text-muted"><?php echo htmlspecialchars($location['location']); ?></span>
                                    </div>

                                    <div class="mb-2 small">
                                        <i class="fas fa-user-tie text-success me-2" style="width: 16px;"></i>
                                        <span class="fw-bold"><?php echo htmlspecialchars($location['controller_name']); ?></span>
                                    </div>

                                    <div class="mb-2 small">
                                        <i class="fas fa-clock text-info me-2" style="width: 16px;"></i>
                                        <span class="text-muted">
                                            <?php
                                            echo date('h:i A', strtotime($location['open_time'])) . ' - ' .
                                                date('h:i A', strtotime($location['close_time']));
                                            ?>
                                        </span>
                                    </div>

                                    <?php if (!empty($location['controller_phone'])): ?>
                                        <div class="mb-2 small">
                                            <i class="fas fa-phone text-warning me-2" style="width: 16px;"></i>
                                            <span class="text-muted"><?php echo htmlspecialchars($location['controller_phone']); ?></span>
                                        </div>
                                    <?php endif; ?>

                                    <?php if (!empty($location['controller_email'])): ?>
                                        <div class="mb-2 small">
                                            <i class="fas fa-envelope text-secondary me-2" style="width: 16px;"></i>
                                            <span class="text-muted"><?php echo htmlspecialchars($location['controller_email']); ?></span>
                                        </div>
                                    <?php endif; ?>

                                    <?php if (!empty($location['description'])): ?>
                                        <div class="mt-2 pt-2 border-top small text-muted">
                                            <i class="fas fa-sticky-note me-1"></i>
                                            <?php echo nl2br(htmlspecialchars(substr($location['description'], 0, 50))); ?>
                                            <?php if (strlen($location['description']) > 50): ?>...<?php endif; ?>
                                        </div>
                                    <?php endif; ?>

                                    <div class="mt-2 d-flex justify-content-end gap-2">
                                        <button class="btn btn-sm btn-outline-warning edit-location-btn"
                                            data-id="<?php echo $location['id']; ?>"
                                            data-name="<?php echo htmlspecialchars($location['name']); ?>"
                                            data-location="<?php echo htmlspecialchars($location['location']); ?>"
                                            data-controller="<?php echo htmlspecialchars($location['controller_name']); ?>"
                                            data-phone="<?php echo htmlspecialchars($location['controller_phone'] ?? ''); ?>"
                                            data-email="<?php echo htmlspecialchars($location['controller_email'] ?? ''); ?>"
                                            data-open="<?php echo $location['open_time']; ?>"
                                            data-close="<?php echo $location['close_time']; ?>"
                                            data-description="<?php echo htmlspecialchars($location['description'] ?? ''); ?>"
                                            data-active="<?php echo $location['is_active']; ?>">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="btn btn-sm btn-outline-danger delete-location-btn"
                                            data-id="<?php echo $location['id']; ?>"
                                            data-name="<?php echo htmlspecialchars($location['name']); ?>">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>

            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
                <div class="col-12">
                    <nav aria-label="Stock locations pagination" class="mt-4">
                        <ul class="pagination justify-content-center">
                            <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $page - 1; ?>">
                                    <i class="fas fa-chevron-left"></i> Previous
                                </a>
                            </li>

                            <?php
                            $startPage = max(1, $page - 2);
                            $endPage = min($totalPages, $startPage + 4);

                            if ($startPage > 1) {
                                echo '<li class="page-item"><a class="page-link" href="?page=1">1</a></li>';
                                if ($startPage > 2) {
                                    echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                }
                            }

                            for ($i = $startPage; $i <= $endPage; $i++):
                            ?>
                                <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                                </li>
                            <?php endfor; ?>

                            <?php if ($endPage < $totalPages): ?>
                                <?php if ($endPage < $totalPages - 1): ?>
                                    <li class="page-item disabled"><span class="page-link">...</span></li>
                                <?php endif; ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo $totalPages; ?>"><?php echo $totalPages; ?></a>
                                </li>
                            <?php endif; ?>

                            <li class="page-item <?php echo $page >= $totalPages ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $page + 1; ?>">
                                    Next <i class="fas fa-chevron-right"></i>
                                </a>
                            </li>
                        </ul>
                    </nav>

                    <div class="text-center text-muted small mt-2">
                        Showing <?php echo $offset + 1; ?> - <?php echo min($offset + $itemsPerPage, $totalItems); ?>
                        of <?php echo $totalItems; ?> locations
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Add Location Modal -->
<div class="modal fade" id="addLocationModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <form id="addLocationForm">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">
                        <i class="fas fa-plus me-2"></i>Add Stock Location
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="add">

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="name" class="form-label">Stock Name *</label>
                            <input type="text" class="form-control" id="name" name="name" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="location" class="form-label">Stock Location *</label>
                            <input type="text" class="form-control" id="location" name="location" required>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="controller_name" class="form-label">Controller in Charge *</label>
                            <input type="text" class="form-control" id="controller_name" name="controller_name" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="controller_phone" class="form-label">Controller Phone</label>
                            <input type="tel" class="form-control" id="controller_phone" name="controller_phone">
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="controller_email" class="form-label">Controller Email</label>
                            <input type="email" class="form-control" id="controller_email" name="controller_email">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label for="open_time" class="form-label">Open Time</label>
                            <input type="time" class="form-control" id="open_time" name="open_time" value="09:00">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label for="close_time" class="form-label">Close Time</label>
                            <input type="time" class="form-control" id="close_time" name="close_time" value="17:00">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                    </div>

                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="is_active" name="is_active" checked>
                        <label class="form-check-label" for="is_active">Active Location</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i>Save Location
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Location Modal -->
<div class="modal fade" id="editLocationModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <form id="editLocationForm">
                <div class="modal-header bg-warning text-white">
                    <h5 class="modal-title">
                        <i class="fas fa-edit me-2"></i>Edit Stock Location
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="id" id="edit_id">

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="edit_name" class="form-label">Stock Name *</label>
                            <input type="text" class="form-control" id="edit_name" name="name" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="edit_location" class="form-label">Stock Location *</label>
                            <input type="text" class="form-control" id="edit_location" name="location" required>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="edit_controller_name" class="form-label">Controller in Charge *</label>
                            <input type="text" class="form-control" id="edit_controller_name" name="controller_name" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="edit_controller_phone" class="form-label">Controller Phone</label>
                            <input type="tel" class="form-control" id="edit_controller_phone" name="controller_phone">
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="edit_controller_email" class="form-label">Controller Email</label>
                            <input type="email" class="form-control" id="edit_controller_email" name="controller_email">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label for="edit_open_time" class="form-label">Open Time</label>
                            <input type="time" class="form-control" id="edit_open_time" name="open_time">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label for="edit_close_time" class="form-label">Close Time</label>
                            <input type="time" class="form-control" id="edit_close_time" name="close_time">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="edit_description" class="form-label">Description</label>
                        <textarea class="form-control" id="edit_description" name="description" rows="3"></textarea>
                    </div>

                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="edit_is_active" name="is_active">
                        <label class="form-check-label" for="edit_is_active">Active Location</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i>Update Location
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteLocationModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form id="deleteLocationForm">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title">
                        <i class="fas fa-exclamation-triangle me-2"></i>Confirm Delete
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" id="delete_id">

                    <p>Are you sure you want to delete the location: <strong id="delete_name"></strong>?</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-trash me-2"></i>Delete Location
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    $(document).ready(function() {
        // Configure Toastr
        toastr.options = {
            "closeButton": true,
            "progressBar": true,
            "positionClass": "toast-top-center",
            "timeOut": "3000"
        };

        // Function to handle form submissions
        function handleFormSubmit(formId, modalId) {
            $(formId).on('submit', function(e) {
                e.preventDefault();

                var formData = $(this).serialize();
                var submitBtn = $(this).find('button[type="submit"]');
                var originalText = submitBtn.html();

                submitBtn.html('<span class="spinner-border spinner-border-sm me-1"></span> Saving...');
                submitBtn.prop('disabled', true);

                $.ajax({
                    url: 'stock_locations.php',
                    type: 'POST',
                    data: formData,
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            toastr.success(response.message, 'Success');
                            $(modalId).modal('hide');
                            setTimeout(function() {
                                location.reload();
                            }, 1500);
                        } else {
                            toastr.error(response.message, 'Error');
                            submitBtn.html(originalText);
                            submitBtn.prop('disabled', false);
                        }
                    },
                    error: function(xhr, status, error) {
                        toastr.error('An error occurred: ' + error, 'Error');
                        submitBtn.html(originalText);
                        submitBtn.prop('disabled', false);
                    }
                });
            });
        }

        // Initialize form handlers
        handleFormSubmit('#addLocationForm', '#addLocationModal');
        handleFormSubmit('#editLocationForm', '#editLocationModal');

        // Delete form handler
        $('#deleteLocationForm').on('submit', function(e) {
            e.preventDefault();

            var formData = $(this).serialize();
            var submitBtn = $(this).find('button[type="submit"]');
            var originalText = submitBtn.html();

            submitBtn.html('<span class="spinner-border spinner-border-sm me-1"></span> Deleting...');
            submitBtn.prop('disabled', true);

            $.ajax({
                url: 'stock_locations.php',
                type: 'POST',
                data: formData,
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        toastr.success(response.message, 'Success');
                        $('#deleteLocationModal').modal('hide');
                        setTimeout(function() {
                            location.reload();
                        }, 1500);
                    } else {
                        toastr.error(response.message, 'Error');
                        submitBtn.html(originalText);
                        submitBtn.prop('disabled', false);
                    }
                },
                error: function(xhr, status, error) {
                    toastr.error('An error occurred: ' + error, 'Error');
                    submitBtn.html(originalText);
                    submitBtn.prop('disabled', false);
                }
            });
        });

        // Edit button handler
        $('.edit-location-btn').on('click', function() {
            var btn = $(this);
            $('#edit_id').val(btn.data('id'));
            $('#edit_name').val(btn.data('name'));
            $('#edit_location').val(btn.data('location'));
            $('#edit_controller_name').val(btn.data('controller'));
            $('#edit_controller_phone').val(btn.data('phone'));
            $('#edit_controller_email').val(btn.data('email'));
            $('#edit_open_time').val(btn.data('open'));
            $('#edit_close_time').val(btn.data('close'));
            $('#edit_description').val(btn.data('description'));
            $('#edit_is_active').prop('checked', btn.data('active') == 1);

            $('#editLocationModal').modal('show');
        });

        // Delete button handler
        $('.delete-location-btn').on('click', function() {
            var btn = $(this);
            $('#delete_id').val(btn.data('id'));
            $('#delete_name').text(btn.data('name'));
            $('#deleteLocationModal').modal('show');
        });
    });
</script>

<?php require_once 'views/partials/footer.php'; ?>