<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// locations.php - Locations Management
require_once 'bootstrap.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit();
}

require_once 'config/database.php';
require_once 'includes/functions.php';

// Get database connection
$conn = getConnection();

// Initialize variables
$message = '';
$error = '';
$locations = [];
$edit_location = null;

// CREATE - Add new location
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_location'])) {
    $name = trim($_POST['name']);
    $code = trim($_POST['code']);
    $description = trim($_POST['description']);
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    try {
        $stmt = $conn->prepare("INSERT INTO stock_locations (code, name, description, is_active) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("sssi", $code, $name, $description, $is_active);
        
        if ($stmt->execute()) {
            $message = "Location added successfully!";
            header("Location: locations.php?message=" . urlencode($message));
            exit();
        } else {
            $error = "Error adding location: " . $conn->error;
        }
        $stmt->close();
    } catch (Exception $e) {
        $error = "Error adding location: " . $e->getMessage();
    }
}

// UPDATE - Edit location
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_location'])) {
    $id = $_POST['id'];
    $name = trim($_POST['name']);
    $code = trim($_POST['code']);
    $description = trim($_POST['description']);
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    try {
        $stmt = $conn->prepare("UPDATE stock_locations SET code = ?, name = ?, description = ?, is_active = ? WHERE id = ?");
        $stmt->bind_param("sssii", $code, $name, $description, $is_active, $id);
        
        if ($stmt->execute()) {
            $message = "Location updated successfully!";
            header("Location: locations.php?message=" . urlencode($message));
            exit();
        } else {
            $error = "Error updating location: " . $conn->error;
        }
        $stmt->close();
    } catch (Exception $e) {
        $error = "Error updating location: " . $e->getMessage();
    }
}

// DELETE - Remove location
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);

    try {
        $stmt = $conn->prepare("DELETE FROM stock_locations WHERE id = ?");
        $stmt->bind_param("i", $id);
        
        if ($stmt->execute()) {
            $message = "Location deleted successfully!";
            header("Location: locations.php?message=" . urlencode($message));
            exit();
        } else {
            $error = "Error deleting location: " . $conn->error;
        }
        $stmt->close();
    } catch (Exception $e) {
        $error = "Error deleting location: " . $e->getMessage();
    }
}

// TOGGLE ACTIVE STATUS
if (isset($_GET['toggle'])) {
    $id = intval($_GET['toggle']);

    try {
        $stmt = $conn->prepare("UPDATE stock_locations SET is_active = NOT is_active WHERE id = ?");
        $stmt->bind_param("i", $id);
        
        if ($stmt->execute()) {
            $message = "Location status updated!";
            header("Location: locations.php?message=" . urlencode($message));
            exit();
        } else {
            $error = "Error updating status: " . $conn->error;
        }
        $stmt->close();
    } catch (Exception $e) {
        $error = "Error updating status: " . $e->getMessage();
    }
}

// Check for message in URL
if (isset($_GET['message'])) {
    $message = urldecode($_GET['message']);
}

// Fetch all locations
$result = $conn->query("SELECT * FROM stock_locations ORDER BY created_at DESC");
if ($result) {
    $locations = $result->fetch_all(MYSQLI_ASSOC);
    $result->free();
}

// Fetch location for editing
if (isset($_GET['edit'])) {
    $id = intval($_GET['edit']);
    $stmt = $conn->prepare("SELECT * FROM stock_locations WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $edit_location = $result->fetch_assoc();
    $stmt->close();
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stock Locations - Ability System</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            background-color: #f8f9fa;
            padding-top: 20px;
        }

        .card {
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .card:hover {
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
        }

        .badge {
            font-size: 0.75em;
        }

        .action-buttons .btn {
            padding: 0.25rem 0.5rem;
            margin-right: 5px;
        }

        .table th {
            border-top: none;
        }

        .status-active {
            color: #28a745;
        }

        .status-inactive {
            color: #6c757d;
        }
    </style>
</head>

<body>
    <div class="container">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h3 mb-0">
                <i class="fas fa-map-marker-alt text-primary"></i> Stock Locations
            </h1>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addLocationModal">
                <i class="fas fa-plus"></i> Add Location
            </button>
        </div>

        <!-- Messages -->
        <?php if ($message): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($error); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Locations Table -->
        <div class="card">
            <div class="card-body">
                <?php if (empty($locations)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-map-marker-alt fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">No locations found</h5>
                        <p class="text-muted">Add your first location using the button above</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>Code</th>
                                    <th>Name</th>
                                    <th>Description</th>
                                    <th>Status</th>
                                    <th>Created</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($locations as $location): ?>
                                    <tr>
                                        <td><code><?php echo htmlspecialchars($location['code'] ?? 'N/A'); ?></code></td>
                                        <td><strong><?php echo htmlspecialchars($location['name']); ?></strong></td>
                                        <td><?php echo htmlspecialchars(substr($location['description'] ?? '', 0, 50)); ?><?php if (strlen($location['description'] ?? '') > 50) echo '...'; ?></td>
                                        <td>
                                            <span class="badge bg-<?php echo $location['is_active'] ? 'success' : 'secondary'; ?>">
                                                <i class="fas fa-circle me-1"></i>
                                                <?php echo $location['is_active'] ? 'Active' : 'Inactive'; ?>
                                            </span>
                                        </td>
                                        <td><?php echo date('M d, Y', strtotime($location['created_at'])); ?></td>
                                        <td class="action-buttons">
                                            <a href="?edit=<?php echo $location['id']; ?>"
                                                class="btn btn-sm btn-outline-primary"
                                                title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="?toggle=<?php echo $location['id']; ?>"
                                                class="btn btn-sm btn-outline-<?php echo $location['is_active'] ? 'warning' : 'success'; ?>"
                                                title="<?php echo $location['is_active'] ? 'Deactivate' : 'Activate'; ?>"
                                                onclick="return confirm('Change status of <?php echo htmlspecialchars($location['name']); ?>?')">
                                                <i class="fas fa-power-off"></i>
                                            </a>
                                            <a href="?delete=<?php echo $location['id']; ?>"
                                                class="btn btn-sm btn-outline-danger"
                                                title="Delete"
                                                onclick="return confirm('Are you sure you want to delete <?php echo htmlspecialchars($location['name']); ?>?')">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Location Count -->
        <?php if (!empty($locations)): ?>
            <div class="text-muted mt-3">
                <small>Total: <?php echo count($locations); ?> location(s)</small>
            </div>
        <?php endif; ?>
    </div>

    <!-- Add Location Modal -->
    <div class="modal fade" id="addLocationModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="">
                    <div class="modal-header">
                        <h5 class="modal-title">Add New Location</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="code" class="form-label">Location Code *</label>
                            <input type="text" class="form-control" id="code" name="code" required
                                placeholder="e.g., WH-001, OFFICE-01">
                            <small class="form-text text-muted">Unique identifier for the location</small>
                        </div>
                        <div class="mb-3">
                            <label for="name" class="form-label">Location Name *</label>
                            <input type="text" class="form-control" id="name" name="name" required
                                placeholder="e.g., Main Warehouse, Office Storage">
                        </div>
                        <div class="mb-3">
                            <label for="description" class="form-label">Description</label>
                            <textarea class="form-control" id="description" name="description" rows="3"
                                placeholder="Optional description of the location"></textarea>
                        </div>
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" id="is_active" name="is_active" checked>
                            <label class="form-check-label" for="is_active">
                                <i class="fas fa-check-circle text-success"></i> Active Location
                            </label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="add_location" class="btn btn-primary">
                            <i class="fas fa-save"></i> Save Location
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Location Modal (if editing) -->
    <?php if ($edit_location): ?>
        <div class="modal fade" id="editLocationModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form method="POST" action="">
                        <input type="hidden" name="id" value="<?php echo $edit_location['id']; ?>">
                        <div class="modal-header">
                            <h5 class="modal-title">Edit Location</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label for="edit_code" class="form-label">Location Code *</label>
                                <input type="text" class="form-control" id="edit_code" name="code"
                                    value="<?php echo htmlspecialchars($edit_location['code'] ?? ''); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label for="edit_name" class="form-label">Location Name *</label>
                                <input type="text" class="form-control" id="edit_name" name="name"
                                    value="<?php echo htmlspecialchars($edit_location['name']); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label for="edit_description" class="form-label">Description</label>
                                <textarea class="form-control" id="edit_description" name="description" rows="3"><?php echo htmlspecialchars($edit_location['description'] ?? ''); ?></textarea>
                            </div>
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" id="edit_is_active" name="is_active"
                                    <?php echo $edit_location['is_active'] ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="edit_is_active">
                                    <i class="fas fa-check-circle text-success"></i> Active Location
                                </label>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" name="update_location" class="btn btn-primary">
                                <i class="fas fa-save"></i> Update Location
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <script>
            // Auto-show edit modal when page loads with edit parameter
            document.addEventListener('DOMContentLoaded', function() {
                var editModal = new bootstrap.Modal(document.getElementById('editLocationModal'));
                editModal.show();
            });
        </script>
    <?php endif; ?>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-focus on first input in add modal
        document.addEventListener('DOMContentLoaded', function() {
            var addModal = document.getElementById('addLocationModal');
            addModal.addEventListener('shown.bs.modal', function() {
                document.getElementById('code').focus();
            });
        });
    </script>
</body>

</html>