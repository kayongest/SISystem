<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// categories.php - Categories Management
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

// Function to check if a category has children
function hasChildCategories($pdo, $categoryId)
{
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM categories WHERE parent_id = ?");
        $stmt->execute([$categoryId]);
        return $stmt->fetchColumn() > 0;
    } catch (Exception $e) {
        error_log("Error checking child categories: " . $e->getMessage());
        return false;
    }
}

$pageTitle = "Categories - aBility";
$showBreadcrumb = true;
$breadcrumbItems = [
    'Dashboard' => 'dashboard_full.php',
    'Settings' => '#',
    'Categories' => ''
];

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'add') {
            // Generate slug from name
            $name = trim($_POST['name']);
            $slug = strtolower(trim(preg_replace('/[^a-zA-Z0-9-]+/', '-', $name)));
            $description = trim($_POST['description'] ?? '');
            $parent_id = !empty($_POST['parent_id']) ? (int)$_POST['parent_id'] : null;

            $stmt = $pdo->prepare("INSERT INTO categories (name, slug, description, parent_id) VALUES (?, ?, ?, ?)");
            $stmt->execute([$name, $slug, $description, $parent_id]);

            $_SESSION['success'] = "Category added successfully!";
        } elseif ($action === 'edit') {
            $id = (int)$_POST['id'];
            $name = trim($_POST['name']);
            $slug = strtolower(trim(preg_replace('/[^a-zA-Z0-9-]+/', '-', $name)));
            $description = trim($_POST['description'] ?? '');
            $parent_id = !empty($_POST['parent_id']) ? (int)$_POST['parent_id'] : null;

            $stmt = $pdo->prepare("UPDATE categories SET name = ?, slug = ?, description = ?, parent_id = ? WHERE id = ?");
            $stmt->execute([$name, $slug, $description, $parent_id, $id]);

            $_SESSION['success'] = "Category updated successfully!";
        } elseif ($action === 'delete') {
            $id = (int)$_POST['id'];

            // Check if category has children
            $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM categories WHERE parent_id = ?");
            $checkStmt->execute([$id]);
            $childCount = $checkStmt->fetchColumn();

            if ($childCount > 0) {
                throw new Exception("Cannot delete category with sub-categories. Please reassign or delete sub-categories first.");
            }

            // Check if category is used in items
            // This depends on your items table structure - adjust as needed
            // $itemCheck = $pdo->prepare("SELECT COUNT(*) FROM items WHERE category_id = ?");
            // $itemCheck->execute([$id]);
            // $itemCount = $itemCheck->fetchColumn();
            // if ($itemCount > 0) {
            //     throw new Exception("Cannot delete category that is in use by items.");
            // }

            $stmt = $pdo->prepare("DELETE FROM categories WHERE id = ?");
            $stmt->execute([$id]);

            $_SESSION['success'] = "Category deleted successfully!";
        }

        // Redirect to refresh the page
        header('Location: categories.php');
        exit();
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// FIXED SECTION: Get all categories with parent names and child counts
$categories = [];
try {
    // Get all categories with their parent names
    $sql = "SELECT 
                c1.*,
                c2.name as parent_name,
                c2.slug as parent_slug
            FROM categories c1
            LEFT JOIN categories c2 ON c1.parent_id = c2.id
            ORDER BY c1.parent_id, c1.name";

    $stmt = $pdo->query($sql);
    $categories = $stmt->fetchAll();

    // Generate a code/slug for display purposes and check for children
    foreach ($categories as &$category) {
        // Use slug as code, or generate from name if slug is empty
        if (empty($category['slug'])) {
            $category['display_code'] = strtoupper(substr(preg_replace('/[^a-zA-Z0-9]+/', '', $category['name']), 0, 8));
        } else {
            $category['display_code'] = strtoupper($category['slug']);
        }

        // Check if this category has any children
        $category['has_children'] = hasChildCategories($pdo, $category['id']);
    }
    unset($category);
} catch (Exception $e) {
    $error = "Error loading categories: " . $e->getMessage();
    error_log($error);
}

// Get parent categories for dropdown (only root categories)
$parentCategories = [];
try {
    $parentStmt = $pdo->query("SELECT id, name, slug FROM categories WHERE parent_id IS NULL ORDER BY name");
    $parentCategories = $parentStmt->fetchAll();
} catch (Exception $e) {
    error_log("Error loading parent categories: " . $e->getMessage());
}

// Get item counts for each category
$itemCounts = [];
try {
    // Check if items table has a category_id or category field
    // Adjust this based on your actual items table structure
    $tableCheck = $pdo->query("SHOW TABLES LIKE 'items'");
    if ($tableCheck->rowCount() > 0) {
        // Try different possible column names
        $possibleColumns = ['category_id', 'category', 'cat_id'];

        foreach ($possibleColumns as $col) {
            try {
                $colCheck = $pdo->query("SHOW COLUMNS FROM items LIKE '$col'");
                if ($colCheck->rowCount() > 0) {
                    $countStmt = $pdo->query("SELECT $col, COUNT(*) as count FROM items WHERE $col IS NOT NULL GROUP BY $col");
                    $countResults = $countStmt->fetchAll();
                    foreach ($countResults as $row) {
                        $itemCounts[$row[$col]] = $row['count'];
                    }
                    break;
                }
            } catch (Exception $e) {
                continue;
            }
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
            <i class="fas fa-list me-2"></i>Categories
        </h1>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCategoryModal">
            <i class="fas fa-plus me-2"></i>Add Category
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

    <!-- Categories Table -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">
                <i class="fas fa-table me-2"></i>All Categories
            </h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-hover" id="categoriesTable">
                    <thead class="table-light">
                        <tr>
                            <th>Code/Slug</th>
                            <th>Category Name</th>
                            <th>Description</th>
                            <th>Parent Category</th>
                            <th>Items Count</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($categories)): ?>
                            <tr>
                                <td colspan="6" class="text-center py-4">
                                    <i class="fas fa-list fa-2x text-muted mb-3"></i>
                                    <h5>No Categories Found</h5>
                                    <p class="text-muted">Add your first category to get started</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($categories as $category): ?>
                                <tr>
                                    <td>
                                        <span class="badge bg-primary"><?php echo htmlspecialchars($category['display_code']); ?></span>
                                    </td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($category['name']); ?></strong>
                                    </td>
                                    <td>
                                        <?php echo htmlspecialchars($category['description'] ?? 'No description'); ?>
                                    </td>
                                    <td>
                                        <?php
                                        if ($category['parent_id']) {
                                            echo htmlspecialchars($category['parent_name'] ?? 'Unknown');
                                        } else {
                                            echo '<em class="text-muted">Root Category</em>';
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <?php
                                        $count = $itemCounts[$category['id']] ?? $itemCounts[$category['name']] ?? 0;
                                        ?>
                                        <span class="badge bg-info"><?php echo $count; ?> items</span>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <button class="btn btn-warning edit-category"
                                                data-id="<?php echo $category['id']; ?>"
                                                data-name="<?php echo htmlspecialchars($category['name']); ?>"
                                                data-description="<?php echo htmlspecialchars($category['description'] ?? ''); ?>"
                                                data-parent="<?php echo $category['parent_id'] ?? ''; ?>">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn btn-danger delete-category"
                                                data-id="<?php echo $category['id']; ?>"
                                                data-name="<?php echo htmlspecialchars($category['name']); ?>"
                                                <?php echo ($category['has_children']) ? 'disabled title="Cannot delete category with sub-categories"' : ''; ?>>
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

<!-- Add Category Modal -->
<div class="modal fade" id="addCategoryModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-plus me-2"></i>Add New Category
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="add">

                    <div class="mb-3">
                        <label for="name" class="form-label">Category Name *</label>
                        <input type="text" class="form-control" id="name" name="name"
                            required placeholder="e.g., IT Equipment">
                        <div class="form-text">This will automatically generate a URL-friendly slug</div>
                    </div>

                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" id="description" name="description"
                            rows="3" placeholder="Brief description of this category"></textarea>
                    </div>

                    <div class="mb-3">
                        <label for="parent_id" class="form-label">Parent Category</label>
                        <select class="form-control" id="parent_id" name="parent_id">
                            <option value="">-- Root Category (No Parent) --</option>
                            <?php foreach ($parentCategories as $parent): ?>
                                <option value="<?php echo $parent['id']; ?>">
                                    <?php echo htmlspecialchars($parent['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">Leave empty to create a top-level category</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i>Save Category
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Category Modal -->
<div class="modal fade" id="editCategoryModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-edit me-2"></i>Edit Category
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="id" id="edit_id">

                    <div class="mb-3">
                        <label for="edit_name" class="form-label">Category Name *</label>
                        <input type="text" class="form-control" id="edit_name" name="name" required>
                    </div>

                    <div class="mb-3">
                        <label for="edit_description" class="form-label">Description</label>
                        <textarea class="form-control" id="edit_description" name="description" rows="3"></textarea>
                    </div>

                    <div class="mb-3">
                        <label for="edit_parent_id" class="form-label">Parent Category</label>
                        <select class="form-control" id="edit_parent_id" name="parent_id">
                            <option value="">-- Root Category (No Parent) --</option>
                            <?php foreach ($parentCategories as $parent): ?>
                                <option value="<?php echo $parent['id']; ?>">
                                    <?php echo htmlspecialchars($parent['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">Leave empty for top-level category</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i>Update Category
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteCategoryModal" tabindex="-1">
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

                    <p>Are you sure you want to delete the category: <strong id="delete_name"></strong>?</p>
                    <p class="text-danger">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        This action cannot be undone. Items in this category may need to be reassigned.
                    </p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-trash me-2"></i>Delete Category
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Edit Category
        document.querySelectorAll('.edit-category').forEach(button => {
            button.addEventListener('click', function() {
                const id = this.dataset.id;
                const name = this.dataset.name;
                const description = this.dataset.description;
                const parent = this.dataset.parent;

                document.getElementById('edit_id').value = id;
                document.getElementById('edit_name').value = name;
                document.getElementById('edit_description').value = description;
                document.getElementById('edit_parent_id').value = parent;

                const editModal = new bootstrap.Modal(document.getElementById('editCategoryModal'));
                editModal.show();
            });
        });

        // Delete Category
        document.querySelectorAll('.delete-category').forEach(button => {
            button.addEventListener('click', function() {
                const id = this.dataset.id;
                const name = this.dataset.name;

                document.getElementById('delete_id').value = id;
                document.getElementById('delete_name').textContent = name;

                const deleteModal = new bootstrap.Modal(document.getElementById('deleteCategoryModal'));
                deleteModal.show();
            });
        });

        // Initialize DataTable
        if ($.fn.DataTable) {
            setTimeout(function() {
                try {
                    var table = $('#categoriesTable').DataTable({
                        "paging": true,
                        "searching": true,
                        "ordering": true,
                        "info": true,
                        "autoWidth": false,
                        "responsive": true,
                        "order": [
                            [1, 'asc']
                        ] // Sort by name by default
                    });
                } catch (e) {
                    console.error('DataTables error:', e);
                    $('#categoriesTable').removeAttr('id').addClass('table-striped');
                }
            }, 100);
        }
    });
</script>

<style>
    #categoriesTable th {
        font-weight: 600;
    }

    .table-hover tbody tr:hover {
        background-color: #f8f9fa;
    }

    .badge {
        font-size: 0.85em;
        padding: 4px 8px;
    }

    .btn-group-sm .btn {
        padding: 0.25rem 0.5rem;
    }
</style>

<?php require_once 'views/partials/footer.php'; ?>