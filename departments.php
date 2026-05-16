<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// departments.php - Department Management
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

$pageTitle = "Departments - aBility";
$showBreadcrumb = true;
$breadcrumbItems = [
    'Dashboard' => 'dashboard_full.php',
    'Settings' => '#',
    'Departments' => ''
];

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id = $_POST['id'] ?? '';

    try {
        if ($action === 'add' || $action === 'edit') {
            $code = trim($_POST['code'] ?? '');
            $name = trim($_POST['name'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $is_active = isset($_POST['is_active']) ? 1 : 0;

            if (empty($code) || empty($name)) {
                throw new Exception('Code and Name are required');
            }

            if ($action === 'add') {
                // Check if code already exists
                $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM departments WHERE code = :code");
                $checkStmt->execute([':code' => strtoupper($code)]);
                if ($checkStmt->fetchColumn() > 0) {
                    throw new Exception('Department code already exists');
                }

                $sql = "INSERT INTO departments (code, name, description, is_active) 
                        VALUES (:code, :name, :description, :is_active)";
            } else {
                // Check if code already exists (excluding current department)
                $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM departments WHERE code = :code AND id != :id");
                $checkStmt->execute([':code' => strtoupper($code), ':id' => $id]);
                if ($checkStmt->fetchColumn() > 0) {
                    throw new Exception('Department code already exists');
                }

                $sql = "UPDATE departments SET 
                        code = :code, 
                        name = :name, 
                        description = :description,
                        is_active = :is_active,
                        updated_at = NOW()
                        WHERE id = :id";
            }

            $stmt = $pdo->prepare($sql);
            $params = [
                ':code' => strtoupper($code),
                ':name' => $name,
                ':description' => $description,
                ':is_active' => $is_active
            ];

            if ($action === 'edit') {
                $params[':id'] = $id;
            }

            $stmt->execute($params);

            $_SESSION['success'] = $action === 'add' ? 'Department added successfully!' : 'Department updated successfully!';
            header('Location: departments.php');
            exit();
        } elseif ($action === 'delete') {
            // Check if department has categories
            $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM categories WHERE department_code = (SELECT code FROM departments WHERE id = :id)");
            $checkStmt->execute([':id' => $id]);
            $hasCategories = $checkStmt->fetchColumn();

            if ($hasCategories > 0) {
                throw new Exception('Cannot delete department that has categories assigned. Reassign categories first.');
            }

            // Check if department has items
            $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM items WHERE department_code = (SELECT code FROM departments WHERE id = :id)");
            $checkStmt->execute([':id' => $id]);
            $hasItems = $checkStmt->fetchColumn();

            if ($hasItems > 0) {
                throw new Exception('Cannot delete department that has items assigned. Reassign items first.');
            }

            $sql = "DELETE FROM departments WHERE id = :id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':id' => $id]);

            $_SESSION['success'] = 'Department deleted successfully!';
            header('Location: departments.php');
            exit();
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Get all departments
$departments = [];
try {
    $stmt = $pdo->query("SELECT * FROM departments ORDER BY code");
    $departments = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $error = "Error loading departments: " . $e->getMessage();
    error_log($error);
}

// Get category counts for each department
$categoryCounts = [];
try {
    $countStmt = $pdo->query("SELECT department_code, COUNT(*) as count FROM categories WHERE department_code IS NOT NULL AND department_code != '' GROUP BY department_code");
    $countResults = $countStmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($countResults as $row) {
        $categoryCounts[$row['department_code']] = $row['count'];
    }
} catch (Exception $e) {
    error_log("Could not get category counts: " . $e->getMessage());
}

// Get item counts for each department
$itemCounts = [];
try {
    // Try different possible column names for department reference in items table
    $possibleColumns = ['department_code', 'department_id', 'dept_code', 'dept_id', 'department'];

    foreach ($possibleColumns as $col) {
        try {
            // Check if column exists
            $checkStmt = $pdo->prepare("SHOW COLUMNS FROM items LIKE ?");
            $checkStmt->execute([$col]);
            if ($checkStmt->rowCount() > 0) {
                $countStmt = $pdo->query("SELECT $col, COUNT(*) as count FROM items WHERE $col IS NOT NULL AND $col != '' GROUP BY $col");
                $countResults = $countStmt->fetchAll(PDO::FETCH_ASSOC);
                foreach ($countResults as $row) {
                    $itemCounts[$row[$col]] = $row['count'];
                }
                break;
            }
        } catch (Exception $e) {
            continue;
        }
    }
} catch (Exception $e) {
    error_log("Could not get item counts: " . $e->getMessage());
}

require_once 'views/partials/header.php';
?>

<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">
            <i class="fas fa-building me-2"></i>Departments
        </h1>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addDepartmentModal">
            <i class="fas fa-plus me-2"></i>Add Department
        </button>
    </div>

    <!-- Success/Error Messages -->
    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($_SESSION['success']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>

    <?php if (isset($error)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-triangle me-2"></i><?php echo htmlspecialchars($error); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Departments Table -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">
                <i class="fas fa-list me-2"></i>All Departments
            </h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-hover" id="departmentsTable">
                    <thead class="table-light">
                        <tr>
                            <th>Code</th>
                            <th>Department Name</th>
                            <th>Description</th>
                            <th>Categories</th>
                            <th>Items</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($departments)): ?>
                            <tr>
                                <td colspan="7" class="text-center py-4">
                                    <i class="fas fa-building fa-2x text-muted mb-3"></i>
                                    <h5>No Departments Found</h5>
                                    <p class="text-muted">Add your first department to get started</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($departments as $dept): ?>
                                <tr>
                                    <td>
                                        <span class="badge bg-primary"><?php echo htmlspecialchars($dept['code']); ?></span>
                                    </td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($dept['name']); ?></strong>
                                    </td>
                                    <td>
                                        <?php echo htmlspecialchars($dept['description'] ?? 'No description'); ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-info"><?php echo $categoryCounts[$dept['code']] ?? 0; ?> categories</span>
                                    </td>
                                    <td>
                                        <span class="badge bg-secondary"><?php echo $itemCounts[$dept['code']] ?? 0; ?> items</span>
                                    </td>
                                    <td>
                                        <?php if ($dept['is_active']): ?>
                                            <span class="badge bg-success">Active</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <button class="btn btn-warning edit-department"
                                                data-id="<?php echo $dept['id']; ?>"
                                                data-code="<?php echo htmlspecialchars($dept['code']); ?>"
                                                data-name="<?php echo htmlspecialchars($dept['name']); ?>"
                                                data-description="<?php echo htmlspecialchars($dept['description'] ?? ''); ?>"
                                                data-active="<?php echo $dept['is_active']; ?>">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn btn-danger delete-department"
                                                data-id="<?php echo $dept['id']; ?>"
                                                data-name="<?php echo htmlspecialchars($dept['name']); ?>">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Add Department Modal -->
<div class="modal fade" id="addDepartmentModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-plus me-2"></i>Add New Department
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="add">

                    <div class="mb-3">
                        <label for="code" class="form-label">Department Code *</label>
                        <input type="text" class="form-control" id="code" name="code"
                            required maxlength="10" placeholder="e.g., AUD">
                        <div class="form-text">Unique code (max 10 characters, will be converted to uppercase)</div>
                    </div>

                    <div class="mb-3">
                        <label for="name" class="form-label">Department Name *</label>
                        <input type="text" class="form-control" id="name" name="name"
                            required placeholder="e.g., AUDIO">
                    </div>

                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" id="description" name="description"
                            rows="3" placeholder="Brief description of this department"></textarea>
                    </div>

                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="is_active" name="is_active" checked>
                        <label class="form-check-label" for="is_active">Active Department</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i>Save Department
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Department Modal -->
<div class="modal fade" id="editDepartmentModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-edit me-2"></i>Edit Department
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="id" id="edit_id">

                    <div class="mb-3">
                        <label for="edit_code" class="form-label">Department Code *</label>
                        <input type="text" class="form-control" id="edit_code" name="code"
                            required maxlength="10">
                    </div>

                    <div class="mb-3">
                        <label for="edit_name" class="form-label">Department Name *</label>
                        <input type="text" class="form-control" id="edit_name" name="name" required>
                    </div>

                    <div class="mb-3">
                        <label for="edit_description" class="form-label">Description</label>
                        <textarea class="form-control" id="edit_description" name="description" rows="3"></textarea>
                    </div>

                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="edit_is_active" name="is_active">
                        <label class="form-check-label" for="edit_is_active">Active Department</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i>Update Department
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteDepartmentModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="">
                <div class="modal-header">
                    <h5 class="modal-title text-danger">
                        <i class="fas fa-exclamation-triangle me-2"></i>Confirm Delete
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" id="delete_id">

                    <p>Are you sure you want to delete the department: <strong id="delete_name"></strong>?</p>
                    <p class="text-danger">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        This action cannot be undone. Any categories or items assigned to this department will need to be reassigned.
                    </p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-trash me-2"></i>Delete Department
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Edit Department
        document.querySelectorAll('.edit-department').forEach(button => {
            button.addEventListener('click', function() {
                const id = this.dataset.id;
                const code = this.dataset.code;
                const name = this.dataset.name;
                const description = this.dataset.description;
                const isActive = this.dataset.active === '1';

                document.getElementById('edit_id').value = id;
                document.getElementById('edit_code').value = code;
                document.getElementById('edit_name').value = name;
                document.getElementById('edit_description').value = description;
                document.getElementById('edit_is_active').checked = isActive;

                const editModal = new bootstrap.Modal(document.getElementById('editDepartmentModal'));
                editModal.show();
            });
        });

        // Delete Department
        document.querySelectorAll('.delete-department').forEach(button => {
            button.addEventListener('click', function() {
                const id = this.dataset.id;
                const name = this.dataset.name;

                document.getElementById('delete_id').value = id;
                document.getElementById('delete_name').textContent = name;

                const deleteModal = new bootstrap.Modal(document.getElementById('deleteDepartmentModal'));
                deleteModal.show();
            });
        });

        // Initialize DataTable
        if ($.fn.DataTable) {
            $('#departmentsTable').DataTable({
                pageLength: 5,
                responsive: true,
                order: [
                    [0, 'asc']
                ]
            });
        }
    });
</script>

<style>
    #departmentsTable th {
        font-weight: 600;
    }

    .table-hover tbody tr:hover {
        background-color: #f8f9fa;
    }

    .badge {
        font-size: 0.85em;
        padding: 4px 8px;
    }
</style>

<?php require_once 'views/partials/footer.php'; ?>