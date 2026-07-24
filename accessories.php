<?php
// accessories.php - Accessory Management Page
$current_page = basename(__FILE__);
session_start();

// Include required files directly
require_once 'includes/database_fix.php';
require_once 'includes/functions.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check authentication using function from functions.php
if (!isLoggedIn()) {
    $_SESSION['redirect_to'] = $_SERVER['REQUEST_URI'];
    header('Location: login.php');
    exit();
}

$pageTitle = "Accessory Management - aBility";
$showBreadcrumb = true;
$breadcrumbItems = [
    'Dashboard' => 'dashboard_full.php',
    'Accessories' => ''
];

require_once 'views/partials/header.php';
?>

<style>
    /* DataTables Pagination Fix - Premium Look */
    .dataTables_wrapper .dataTables_paginate {
        padding-top: 1.5rem;
        display: flex;
        justify-content: flex-end;
    }

    .dataTables_wrapper .dataTables_paginate .paginate_button {
        border-radius: 6px !important;
        border: 1px solid #dee2e6 !important;
        background: white !important;
        color: #353f48ff !important;
        font-weight: 500 !important;
        transition: all 0.2s ease !important;
        cursor: pointer !important;
        text-decoration: none !important;
        font-size: 0.8rem !important;
        padding: 5px 10px !important;
        margin-left: 5px;
    }

    .dataTables_wrapper .dataTables_paginate .paginate_button:hover {
        background: #f8f9fa !important;
        border-color: #2c6792 !important;
        color: #2c6792 !important;
        transform: translateY(-1px);
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
    }

    .dataTables_wrapper .dataTables_paginate .paginate_button.current,
    .dataTables_wrapper .dataTables_paginate .paginate_button.current:hover,
    .active>.page-link,
    .page-link.active {
        background: linear-gradient(135deg, #1a2e3f 0%, #2c6792 100%) !important;
        color: white !important;
        border-color: #1a2e3f !important;
        box-shadow: 0 4px 10px rgba(44, 103, 146, 0.3);
    }

    .dataTables_wrapper .dataTables_paginate .paginate_button.disabled,
    .dataTables_wrapper .dataTables_paginate .paginate_button.disabled:hover {
        background: #f8f9fa !important;
        color: #adb5bd !important;
        border-color: #e9ecef !important;
        cursor: not-allowed !important;
        transform: none !important;
        box-shadow: none !important;
    }
</style>

<?php
// Database connection
try {
    $db = new DatabaseFix();
    $conn = $db->getConnection();
} catch (Exception $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Check if accessories table exists
$tableExists = false;
$checkTable = $conn->query("SHOW TABLES LIKE 'accessories'");
if ($checkTable && $checkTable->num_rows > 0) {
    $tableExists = true;
}

// Handle actions only if table exists
$action = $_GET['action'] ?? '';
$accessory_id = $_GET['id'] ?? 0;

if ($action === 'delete' && $accessory_id && $tableExists) {
    try {
        // Check if accessory is in use
        $checkStmt = $conn->prepare("
            SELECT COUNT(*) as count 
            FROM item_accessories 
            WHERE accessory_id = ?
        ");
        $checkStmt->bind_param("i", $accessory_id);
        $checkStmt->execute();
        $result = $checkStmt->get_result();
        $row = $result->fetch_assoc();

        if ($row['count'] > 0) {
            $_SESSION['error_message'] = "Cannot delete accessory: It is assigned to " . $row['count'] . " equipment items.";
        } else {
            // Soft delete (mark as inactive)
            $updateStmt = $conn->prepare("
                UPDATE accessories 
                SET is_active = 0 
                WHERE id = ?
            ");
            $updateStmt->bind_param("i", $accessory_id);
            $updateStmt->execute();

            if ($updateStmt->affected_rows > 0) {
                $_SESSION['success_message'] = "Accessory deleted successfully.";
            }
            $updateStmt->close();
        }
        $checkStmt->close();

        header('Location: accessories.php');
        exit();
    } catch (Exception $e) {
        $_SESSION['error_message'] = "Error deleting accessory: " . $e->getMessage();
        header('Location: accessories.php');
        exit();
    }
}

// Get all accessories
$accessories = [];
$search = $_GET['search'] ?? '';
$status = $_GET['status'] ?? '';
$category_filter = $_GET['category'] ?? '';
$stats = [
    'total' => 0,
    'in_stock' => 0,
    'low_stock' => 0,
    'out_of_stock' => 0
];

if ($tableExists) {
    try {
        // Fetch accessory categories
        $accessory_categories_list = [];
        $catResult = $conn->query("SELECT * FROM accessory_categories ORDER BY name");
        if ($catResult) {
            while ($row = $catResult->fetch_assoc()) {
                $accessory_categories_list[] = $row;
            }
        }

        // First check if minimum_stock column exists
        $columnCheck = $conn->query("SHOW COLUMNS FROM accessories LIKE 'minimum_stock'");
        $hasMinimumStock = $columnCheck && $columnCheck->num_rows > 0;

        $sql = "
            SELECT 
                a.*,
                " . ($hasMinimumStock ? "a.minimum_stock" : "5 as minimum_stock") . ",
                COALESCE(COUNT(ia.item_id), 0) as assigned_count,
                GROUP_CONCAT(DISTINCT i.item_name ORDER BY i.item_name SEPARATOR ', ') as assigned_items
            FROM accessories a
            LEFT JOIN item_accessories ia ON a.id = ia.accessory_id
            LEFT JOIN items i ON ia.item_id = i.id
            WHERE a.is_active = 1
        ";

        // Add search conditions
        $whereConditions = [];
        $params = [];
        $types = "";

        if ($search) {
            $whereConditions[] = "(a.name LIKE ? OR a.description LIKE ?)";
            $searchTerm = "%$search%";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $types .= "ss";
        }

        if ($status === 'low') {
            $whereConditions[] = "a.available_quantity <= " . ($hasMinimumStock ? "a.minimum_stock" : "5");
        } elseif ($status === 'out') {
            $whereConditions[] = "a.available_quantity = 0";
        } elseif ($status === 'in_stock') {
            $whereConditions[] = "a.available_quantity > 0";
        }

        if ($category_filter) {
            $whereConditions[] = "a.category = ?";
            $params[] = $category_filter;
            $types .= "s";
        }

        if (!empty($whereConditions)) {
            $sql .= " AND " . implode(" AND ", $whereConditions);
        }

        $sql .= " GROUP BY a.id ORDER BY a.name";

        // Prepare and execute with error handling
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception("SQL prepare failed: " . $conn->error);
        }

        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }

        if (!$stmt->execute()) {
            throw new Exception("SQL execute failed: " . $stmt->error);
        }

        $result = $stmt->get_result();
        $accessories = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        // Get statistics with fallback
        $statsQuery = "
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN available_quantity = 0 THEN 1 ELSE 0 END) as out_of_stock,
                SUM(CASE WHEN available_quantity <= " . ($hasMinimumStock ? "minimum_stock" : "5") . " AND available_quantity > 0 THEN 1 ELSE 0 END) as low_stock,
                SUM(CASE WHEN available_quantity > " . ($hasMinimumStock ? "minimum_stock" : "5") . " THEN 1 ELSE 0 END) as in_stock
            FROM accessories 
            WHERE is_active = 1
        ";

        $statsResult = $conn->query($statsQuery);
        if ($statsResult && $statsResult instanceof mysqli_result) {
            $stats = $statsResult->fetch_assoc();
            $stats = array_merge([
                'total' => 0,
                'out_of_stock' => 0,
                'low_stock' => 0,
                'in_stock' => 0
            ], $stats ?: []);
            $statsResult->close();
        }
    } catch (Exception $e) {
        error_log("Error fetching accessories: " . $e->getMessage());
        $_SESSION['error_message'] = "Error loading accessories: " . htmlspecialchars($e->getMessage());
    }
}
?>

<div class="container-fluid">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0 text-gray-800">
            <i class="fas fa-puzzle-piece me-2"></i>Accessory Management
        </h1>
        <div>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addAccessoryModal" <?php echo !$tableExists ? 'disabled' : ''; ?>>
                <i class="fas fa-plus me-1"></i> Add Accessory
            </button>
            <button type="button" class="btn btn-info text-white ms-2" data-bs-toggle="modal" data-bs-target="#manageCategoriesModal" <?php echo !$tableExists ? 'disabled' : ''; ?>>
                <i class="fas fa-tags me-1"></i> Manage Categories
            </button>
        </div>
    </div>

    <?php if (!$tableExists): ?>
        <div class="alert alert-warning">
            <i class="fas fa-exclamation-triangle me-2"></i>
            <strong>Accessories table not found!</strong> Please run the setup script to create the necessary database tables.
            <div class="mt-2">
                <a href="create_tables.php" class="btn btn-sm btn-warning">
                    <i class="fas fa-database me-1"></i> Create Database Tables
                </a>
            </div>
        </div>
    <?php endif; ?>

    <!-- Statistics Cards -->
    <?php if ($tableExists): ?>
        <div class="row mb-4">
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card border-left-primary shadow h-100 py-2 text-white" style="background-color: #44444E;">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-white text-uppercase mb-1">
                                    Total Accessories
                                </div>
                                <div class="h5 mb-0 font-weight-bold"><?php echo $stats['total'] ?? 0; ?></div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-puzzle-piece fa-2x text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card border-left-success shadow h-100 py-2 text-white" style="background-color: #234C6A;">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-white text-uppercase mb-1">
                                    In Stock
                                </div>
                                <div class="h5 mb-0 font-weight-bold"><?php echo $stats['in_stock'] ?? 0; ?></div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-check-circle fa-2x text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card border-left-warning shadow h-100 py-2 text-white" style="background-color: #FE7F2D;">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-white text-uppercase mb-1">
                                    Low Stock
                                </div>
                                <div class="h5 mb-0 font-weight-bold"><?php echo $stats['low_stock'] ?? 0; ?></div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-exclamation-triangle fa-2x text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card border-left-danger shadow h-100 py-2 text-white" style="background-color: #9E2A3A;">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-white text-uppercase mb-1">
                                    Out of Stock
                                </div>
                                <div class="h5 mb-0 font-weight-bold"><?php echo $stats['out_of_stock'] ?? 0; ?></div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-times-circle fa-2x text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="card shadow mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-4">
                        <input type="text" class="form-control" name="search" placeholder="Search accessories..."
                            value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <div class="col-md-3">
                        <select class="form-select" name="category">
                            <option value="">All Categories</option>
                            <?php 
                            foreach ($accessory_categories_list ?? [] as $catRow): 
                                $cat = $catRow['name'];
                            ?>
                                <option value="<?php echo htmlspecialchars($cat); ?>" <?php echo $category_filter === $cat ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($cat); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <select class="form-select" name="status">
                            <option value="">All Status</option>
                            <option value="in_stock" <?php echo $status === 'in_stock' ? 'selected' : ''; ?>>In Stock</option>
                            <option value="low" <?php echo $status === 'low' ? 'selected' : ''; ?>>Low Stock</option>
                            <option value="out" <?php echo $status === 'out' ? 'selected' : ''; ?>>Out of Stock</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-search me-1"></i> Filter
                        </button>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>

    <!-- Accessories Table -->
    <div class="card shadow">
        <div class="card-header py-3 d-flex justify-content-between align-items-center text-white"
            style="background-color: rgba(35, 54, 67, 1);">
            <h6 class="m-0 font-weight-bold">
                <i class="fas fa-list me-2"></i>Accessory List
            </h6>
            <?php if ($tableExists): ?>
                <div>
                    <button class="btn btn-sm btn-light" id="exportBtn" <?php echo empty($accessories) ? 'disabled' : ''; ?>>
                        <i class="fas fa-download me-1"></i> Export
                    </button>
                    <button class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#bulkAssignModal" <?php echo empty($accessories) ? 'disabled' : ''; ?>>
                        <i class="fas fa-copy me-1"></i> Bulk Assign
                    </button>
                </div>
            <?php endif; ?>
        </div>
        <div class="card-body">
            <?php if (!$tableExists): ?>
                <div class="text-center py-5">
                    <i class="fas fa-database fa-4x text-muted mb-3"></i>
                    <h4>Accessories Database Not Set Up</h4>
                    <p class="text-muted">The accessories table needs to be created before you can manage accessories.</p>
                    <a href="create_tables.php" class="btn btn-primary">
                        <i class="fas fa-database me-1"></i> Create Database Tables
                    </a>
                </div>
            <?php elseif (empty($accessories)): ?>
                <div class="text-center py-4">
                    <i class="fas fa-puzzle-piece fa-3x text-muted mb-3"></i>
                    <h5>No accessories found</h5>
                    <p class="text-muted">Start by adding your first accessory</p>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addAccessoryModal">
                        <i class="fas fa-plus me-1"></i> Add Accessory
                    </button>
                </div>
            <?php else: ?>
                <div>
                    <table class="table table-bordered table-hover" id="accessoriesTable" width="100%" cellspacing="0">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Category</th>
                                <th>Description</th>
                                <th>Total Qty</th>
                                <th>Available</th>
                                <th>Min Stock</th>
                                <th>Assigned To</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($accessories as $acc): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($acc['name'] ?? ''); ?></strong>
                                    </td>
                                    <td>
                                        <span class="badge bg-secondary"><?php echo htmlspecialchars($acc['category'] ?? 'Uncategorized'); ?></span>
                                    </td>
                                    <td>
                                        <?php echo !empty($acc['description']) ?
                                            htmlspecialchars(substr($acc['description'], 0, 80)) .
                                            (strlen($acc['description']) > 80 ? '...' : '') :
                                            '<span class="text-muted">No description</span>'; ?>
                                    </td>
                                    <td class="text-center"><?php echo $acc['total_quantity'] ?? 0; ?></td>
                                    <td class="text-center">
                                        <?php
                                        $available = $acc['available_quantity'] ?? 0;
                                        $total = $acc['total_quantity'] ?? 0;
                                        $min_stock = $acc['minimum_stock'] ?? 5;
                                        $assigned_count = $acc['assigned_count'] ?? 0;

                                        // Show calculation: Available = Total - Assigned
                                        echo '<span class="badge ' .
                                            ($available == 0 ? 'bg-danger' : ($available <= $min_stock ? 'bg-warning' : 'bg-success')) .
                                            '">' . $available . '</span>';

                                        // Show the calculation on hover or as a tooltip
                                        echo '<br><small class="text-muted">' .
                                            'Total: ' . $total . ' - Assigned: ' . $assigned_count .
                                            '</small>';
                                        ?>
                                    </td>
                                    <td class="text-center"><?php echo $acc['minimum_stock'] ?? 5; ?></td>
                                    <td>
                                        <?php if (($acc['assigned_count'] ?? 0) > 0): ?>
                                            <button type="button" class="btn btn-sm btn-outline-info view-assigned-items-btn"
                                                data-id="<?php echo $acc['id'] ?? 0; ?>"
                                                data-name="<?php echo htmlspecialchars($acc['name'] ?? ''); ?>"
                                                title="Click to view assigned equipment items">
                                                <i class="fas fa-eye me-1"></i> <?php echo $acc['assigned_count'] ?? 0; ?> item(s)
                                            </button>
                                            <?php if (!empty($acc['assigned_items'])): ?>
                                                <br>
                                                <small class="text-muted" title="<?php echo htmlspecialchars($acc['assigned_items']); ?>">
                                                    <?php echo htmlspecialchars(substr($acc['assigned_items'], 0, 50)); ?>
                                                    <?php echo strlen($acc['assigned_items']) > 50 ? '...' : ''; ?>
                                                </small>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="badge bg-light text-muted border">0 items</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php
                                        $available_qty = $acc['available_quantity'] ?? 0;
                                        $min_stock_qty = $acc['minimum_stock'] ?? 5;

                                        if ($available_qty == 0) {
                                            echo '<span class="badge bg-danger">Out of Stock</span>';
                                        } elseif ($available_qty <= $min_stock_qty) {
                                            echo '<span class="badge bg-warning">Low Stock</span>';
                                        } else {
                                            echo '<span class="badge bg-success">In Stock</span>';
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <button class="btn btn-outline-info view-assigned-items-btn"
                                                data-id="<?php echo $acc['id'] ?? 0; ?>"
                                                data-name="<?php echo htmlspecialchars($acc['name'] ?? ''); ?>"
                                                title="View Assigned Equipment Items">
                                                <i class="fas fa-list-check"></i>
                                            </button>
                                            <button class="btn btn-info edit-accessory-btn"
                                                data-id="<?php echo $acc['id'] ?? 0; ?>"
                                                data-name="<?php echo htmlspecialchars($acc['name'] ?? ''); ?>"
                                                data-category="<?php echo htmlspecialchars($acc['category'] ?? ''); ?>"
                                                data-description="<?php echo htmlspecialchars($acc['description'] ?? ''); ?>"
                                                data-total="<?php echo $acc['total_quantity'] ?? 0; ?>"
                                                data-available="<?php echo $acc['available_quantity'] ?? 0; ?>"
                                                data-minimum="<?php echo $acc['minimum_stock'] ?? 5; ?>"
                                                data-assigned="<?php echo $acc['assigned_count'] ?? 0; ?>"
                                                title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <a href="items/assign_accessory.php?accessory_id=<?php echo $acc['id'] ?? 0; ?>"
                                                class="btn btn-primary" title="Assign to Equipment">
                                                <i class="fas fa-link"></i>
                                            </a>
                                            <a href="?action=delete&id=<?php echo $acc['id'] ?? 0; ?>"
                                                class="btn btn-danger"
                                                onclick="return confirm('Are you sure you want to delete this accessory?')"
                                                title="Delete">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Manage Categories Modal -->
<div class="modal fade" id="manageCategoriesModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title"><i class="fas fa-tags me-2"></i>Manage Categories</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="addCategoryForm" class="mb-4">
                    <div class="input-group">
                        <input type="text" class="form-control" id="newCategoryName" name="name" placeholder="New Category Name" required>
                        <button class="btn btn-primary" type="submit"><i class="fas fa-plus"></i> Add</button>
                    </div>
                </form>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered">
                        <thead>
                            <tr>
                                <th>Category Name</th>
                                <th width="80" class="text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($accessory_categories_list ?? [] as $catRow): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($catRow['name']); ?></td>
                                <td class="text-center">
                                    <button class="btn btn-sm btn-danger delete-category-btn" data-id="<?php echo $catRow['id']; ?>" title="Delete">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if(empty($accessory_categories_list)): ?>
                            <tr><td colspan="2" class="text-center">No categories found.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add Accessory Modal -->
<div class="modal fade" id="addAccessoryModal" tabindex="-1" aria-labelledby="addAccessoryModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="addAccessoryModalLabel">
                    <i class="fas fa-plus-circle me-2"></i>Add New Accessory
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="addAccessoryForm" method="POST" action="api/accessories/create.php">
                <div class="modal-body">
                    <?php if (!$tableExists): ?>
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-circle me-2"></i>
                            Cannot add accessories - database tables not set up. Please run the setup script first.
                        </div>
                    <?php endif; ?>
                    <div class="mb-3">
                        <label for="name" class="form-label required">Accessory Name</label>
                        <input type="text" class="form-control" id="name" name="name" required
                            placeholder="e.g., HDMI Cable, Power Adapter" <?php echo !$tableExists ? 'disabled' : ''; ?>>
                    </div>
                    <div class="mb-3">
                        <label for="category" class="form-label required">Category</label>
                        <select class="form-select" id="category" name="category" required <?php echo !$tableExists ? 'disabled' : ''; ?>>
                            <option value="">Select Category...</option>
                            <?php foreach ($accessory_categories_list ?? [] as $catRow): ?>
                                <option value="<?php echo htmlspecialchars($catRow['name']); ?>"><?php echo htmlspecialchars($catRow['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="3"
                            placeholder="Optional description..." <?php echo !$tableExists ? 'disabled' : ''; ?>></textarea>
                    </div>

                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="total_quantity" class="form-label required">Total Quantity</label>
                                <input type="number" class="form-control" id="total_quantity" name="total_quantity"
                                    value="1" min="1" required <?php echo !$tableExists ? 'disabled' : ''; ?>>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="available_quantity" class="form-label required">Available Quantity</label>
                                <input type="number" class="form-control" id="available_quantity" name="available_quantity"
                                    value="1" min="0" required <?php echo !$tableExists ? 'disabled' : ''; ?>>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="minimum_stock" class="form-label">Minimum Stock Level</label>
                                <input type="number" class="form-control" id="minimum_stock" name="minimum_stock"
                                    value="5" min="1" <?php echo !$tableExists ? 'disabled' : ''; ?>>
                                <div class="form-text">Low stock warning threshold</div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="saveAccessoryBtn" <?php echo !$tableExists ? 'disabled' : ''; ?>>
                        <i class="fas fa-save me-1"></i> Save Accessory
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Accessory Modal -->
<div class="modal fade" id="editAccessoryModal" tabindex="-1" aria-labelledby="editAccessoryModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-warning text-white">
                <h5 class="modal-title" id="editAccessoryModalLabel">
                    <i class="fas fa-edit me-2"></i>Edit Accessory
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="editAccessoryForm" method="POST" action="api/accessories/update.php">
                <input type="hidden" id="edit_id" name="id">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="edit_name" class="form-label required">Accessory Name</label>
                        <input type="text" class="form-control" id="edit_name" name="name" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_category" class="form-label required">Category</label>
                        <select class="form-select" id="edit_category" name="category" required>
                            <option value="">Select Category...</option>
                            <?php foreach ($accessory_categories_list ?? [] as $catRow): ?>
                                <option value="<?php echo htmlspecialchars($catRow['name']); ?>"><?php echo htmlspecialchars($catRow['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="edit_description" class="form-label">Description</label>
                        <textarea class="form-control" id="edit_description" name="description" rows="3"></textarea>
                    </div>

                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="edit_total_quantity" class="form-label required">Total Quantity</label>
                                <input type="number" class="form-control" id="edit_total_quantity" name="total_quantity"
                                    min="1" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="edit_available_quantity" class="form-label required">Available Quantity</label>
                                <input type="number" class="form-control" id="edit_available_quantity" name="available_quantity"
                                    min="0" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="edit_minimum_stock" class="form-label">Minimum Stock Level</label>
                                <input type="number" class="form-control" id="edit_minimum_stock" name="minimum_stock"
                                    min="1">
                            </div>
                        </div>
                    </div>

                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        <small>
                            Note: Changing total quantity will affect available quantity.
                            Use stock adjustments for inventory changes.
                        </small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning" id="updateAccessoryBtn">
                        <i class="fas fa-save me-1"></i> Update Accessory
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Bulk Assign Modal -->
<div class="modal fade" id="bulkAssignModal" tabindex="-1" aria-labelledby="bulkAssignModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="bulkAssignModalLabel">
                    <i class="fas fa-copy me-2"></i>Bulk Assign Accessories
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="bulkAssignForm" method="POST" action="api/accessories/bulk_assign.php">
                <div class="modal-body">
                    <div class="alert alert-info mb-3">
                        <i class="fas fa-info-circle me-2"></i>
                        Assign the same accessories to multiple equipment items at once.
                    </div>

                    <div class="mb-3">
                        <label class="form-label required">Select Accessories</label>
                        <div class="accessories-list border rounded p-3" style="max-height: 200px; overflow-y: auto;">
                            <?php
                            if ($tableExists) {
                                $accStmt = $conn->prepare("SELECT id, name FROM accessories WHERE is_active = 1 ORDER BY name");
                                if ($accStmt) {
                                    $accStmt->execute();
                                    $accResult = $accStmt->get_result();

                                    while ($row = $accResult->fetch_assoc()):
                            ?>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox"
                                                name="accessories[]" value="<?php echo $row['id']; ?>"
                                                id="acc_<?php echo $row['id']; ?>">
                                            <label class="form-check-label" for="acc_<?php echo $row['id']; ?>">
                                                <?php echo htmlspecialchars($row['name']); ?>
                                            </label>
                                        </div>
                            <?php
                                    endwhile;
                                    $accStmt->close();
                                }
                            }
                            ?>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label required">Select Equipment Items</label>
                        <select class="form-select" id="bulkItems" name="items[]" multiple size="8" required>
                            <option value="">-- Select Items --</option>
                            <?php
                            $itemsStmt = $conn->prepare("SELECT id, item_name, serial_number FROM items ORDER BY item_name");
                            if ($itemsStmt) {
                                $itemsStmt->execute();
                                $itemsResult = $itemsStmt->get_result();

                                while ($item = $itemsResult->fetch_assoc()):
                            ?>
                                    <option value="<?php echo $item['id']; ?>">
                                        <?php echo htmlspecialchars($item['item_name']); ?>
                                        (<?php echo htmlspecialchars($item['serial_number']); ?>)
                                    </option>
                            <?php
                                endwhile;
                                $itemsStmt->close();
                            }
                            ?>
                        </select>
                        <div class="form-text">Hold Ctrl/Cmd to select multiple items</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Assignment Mode</label>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="mode" id="mode_add" value="add" checked>
                            <label class="form-check-label" for="mode_add">
                                Add to existing accessories (Don't remove current accessories)
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="mode" id="mode_replace" value="replace">
                            <label class="form-check-label" for="mode_replace">
                                Replace all accessories (Remove current accessories first)
                            </label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="bulkAssignBtn">
                        <i class="fas fa-link me-1"></i> Assign Accessories
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- View Assigned Items Modal -->
<div class="modal fade" id="viewAssignedItemsModal" tabindex="-1" aria-labelledby="viewAssignedItemsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title" id="viewAssignedItemsModalLabel">
                    <i class="fas fa-list-check me-2"></i>Assigned Equipment Items
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="assignedItemsContainer">
                <div class="text-center py-4">
                    <i class="fas fa-spinner fa-spin fa-2x text-info"></i>
                    <p class="mt-2 text-muted">Loading assigned equipment items...</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <a href="#" id="manageAssignmentsBtn" class="btn btn-primary">
                    <i class="fas fa-link me-1"></i> Manage Assignments
                </a>
            </div>
        </div>
    </div>
</div>

<?php
// Close database connection
$db->close();
require_once 'views/partials/footer.php';
?>

<script>
    $(document).ready(function() {
        <?php if ($tableExists): ?>
            // View Assigned Items Modal Handler
            $(document).on('click', '.view-assigned-items-btn', function(e) {
                e.preventDefault();
                e.stopPropagation();

                const id = $(this).data('id');
                const name = $(this).data('name');

                function escapeHtml(str) {
                    if (!str) return '';
                    return $('<div>').text(str).html();
                }

                $('#viewAssignedItemsModalLabel').html('<i class="fas fa-list-check me-2"></i>Items assigned to: <strong>' + escapeHtml(name) + '</strong>');
                $('#manageAssignmentsBtn').attr('href', 'items/assign_accessory.php?accessory_id=' + id);
                $('#assignedItemsContainer').html(`
                    <div class="text-center py-4">
                        <i class="fas fa-spinner fa-spin fa-2x text-info mb-2"></i>
                        <p class="text-muted mb-0">Loading assigned equipment items...</p>
                    </div>
                `);

                const modalEl = document.getElementById('viewAssignedItemsModal');
                if (modalEl && typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                    const bsModal = bootstrap.Modal.getOrCreateInstance(modalEl);
                    bsModal.show();
                } else {
                    $('#viewAssignedItemsModal').modal('show');
                }

                $.ajax({
                    url: 'api/accessories/get_assigned_items.php',
                    type: 'GET',
                    data: { accessory_id: id },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success && response.items && response.items.length > 0) {
                            if ($.fn.DataTable && $.fn.DataTable.isDataTable('#modalAssignedItemsTable')) {
                                $('#modalAssignedItemsTable').DataTable().destroy();
                            }

                            let tableHtml = `
                                <div class="table-responsive p-1">
                                    <table class="table table-bordered table-hover align-middle mb-0" id="modalAssignedItemsTable" width="100%">
                                        <thead class="table-light">
                                            <tr>
                                                <th style="width: 50px;">#</th>
                                                <th>Item Name</th>
                                                <th>Serial Number</th>
                                                <th>Category</th>
                                                <th>Assigned Date</th>
                                                <th style="width: 110px;">Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                            `;
                            response.items.forEach(function(item, idx) {
                                tableHtml += `
                                    <tr>
                                        <td>${idx + 1}</td>
                                        <td><strong>${escapeHtml(item.item_name)}</strong></td>
                                        <td><code>${escapeHtml(item.serial_number)}</code></td>
                                        <td><span class="badge bg-secondary">${escapeHtml(item.category)}</span></td>
                                        <td><small class="text-muted">${item.assigned_at || 'N/A'}</small></td>
                                        <td>
                                            <a href="items/edit.php?id=${item.id}" class="btn btn-sm btn-outline-primary" target="_blank">
                                                <i class="fas fa-edit me-1"></i> View Item
                                            </a>
                                        </td>
                                    </tr>
                                `;
                            });
                            tableHtml += `
                                        </tbody>
                                    </table>
                                </div>
                            `;
                            $('#assignedItemsContainer').html(tableHtml);

                            // Initialize DataTable for assigned items
                            if ($.fn.DataTable) {
                                $('#modalAssignedItemsTable').DataTable({
                                    pageLength: 5,
                                    lengthMenu: [
                                        [5, 10, 25, 50, -1],
                                        [5, 10, 25, 50, "All"]
                                    ],
                                    responsive: true,
                                    autoWidth: false,
                                    order: [[1, 'asc']],
                                    language: {
                                        search: "Search items:",
                                        lengthMenu: "Show _MENU_ items",
                                        info: "Showing _START_ to _END_ of _TOTAL_ items",
                                        infoEmpty: "Showing 0 to 0 of 0 items",
                                        infoFiltered: "(filtered from _MAX_ total items)",
                                        paginate: {
                                            first: "First",
                                            last: "Last",
                                            next: "Next",
                                            previous: "Previous"
                                        }
                                    }
                                });
                            }
                        } else if (response.success === false) {
                            $('#assignedItemsContainer').html(`
                                <div class="alert alert-warning mb-0">
                                    <i class="fas fa-exclamation-triangle me-2"></i>${escapeHtml(response.message || 'Error loading assigned items.')}
                                </div>
                            `);
                        } else {
                            $('#assignedItemsContainer').html(`
                                <div class="text-center py-4 text-muted">
                                    <i class="fas fa-info-circle fa-3x mb-3 text-secondary"></i>
                                    <h5>No equipment items currently assigned</h5>
                                    <p class="mb-0">This accessory is not linked to any equipment items yet.</p>
                                </div>
                            `);
                        }
                    },
                    error: function() {
                        $('#assignedItemsContainer').html(`
                            <div class="alert alert-danger mb-0">
                                <i class="fas fa-exclamation-triangle me-2"></i>Failed to load assigned equipment items.
                            </div>
                        `);
                    }
                });
            });

            // Cleanup DataTable when assigned items modal is closed
            $('#viewAssignedItemsModal').on('hidden.bs.modal', function() {
                if ($.fn.DataTable && $.fn.DataTable.isDataTable('#modalAssignedItemsTable')) {
                    $('#modalAssignedItemsTable').DataTable().destroy();
                }
            });
            <?php if (!empty($accessories)): ?>
            // Initialize DataTable
            if ($('#accessoriesTable').length) {
                $('#accessoriesTable').DataTable({
                    dom: "<'row mb-4 px-2'<'col-sm-12 col-md-6'l><'col-sm-12 col-md-6 d-flex justify-content-md-end'f>>" +
                         "<'row'<'col-sm-12'tr>>" +
                         "<'row mt-4 px-2'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7'p>>",
                    pageLength: 5,
                    lengthMenu: [
                        [5, 10, 25, 50, 100],
                        [5, 10, 25, 50, 100]
                    ],
                    responsive: true,
                    order: [
                        [0, 'asc']
                    ],
                    language: {
                        emptyTable: "No accessories found",
                        info: "Showing _START_ to _END_ of _TOTAL_ accessories",
                        infoEmpty: "Showing 0 to 0 of 0 accessories",
                        infoFiltered: "(filtered from _MAX_ total accessories)",
                        search: "Search accessories:",
                        paginate: {
                            first: "First",
                            last: "Last",
                            next: "Next",
                            previous: "Previous"
                        }
                    },
                    drawCallback: function() {
                        $(this).closest('.dataTables_wrapper').find('.pagination').addClass('pagination-sm justify-content-end');
                    }
                });
            }
            <?php endif; ?>



            // Add accessory form submission
            $('#addAccessoryForm').on('submit', function(e) {
                e.preventDefault();
                const form = this;
                const btn = $('#saveAccessoryBtn');
                const originalText = btn.html();

                btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i> Saving...');

                $.ajax({
                    url: form.action,
                    type: 'POST',
                    data: $(form).serialize(),
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            toastr.success(response.message);
                            setTimeout(() => {
                                $('#addAccessoryModal').modal('hide');
                                window.location.reload();
                            }, 1500);
                        } else {
                            toastr.error(response.message);
                            btn.prop('disabled', false).html(originalText);
                        }
                    },
                    error: function() {
                        toastr.error('Failed to save accessory');
                        btn.prop('disabled', false).html(originalText);
                    }
                });
            });

            // Edit accessory form submission
            $('#editAccessoryForm').on('submit', function(e) {
                e.preventDefault();
                const form = this;
                const btn = $('#updateAccessoryBtn');
                const originalText = btn.html();

                btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i> Updating...');

                $.ajax({
                    url: form.action,
                    type: 'POST',
                    data: $(form).serialize(),
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            toastr.success(response.message);
                            setTimeout(() => {
                                $('#editAccessoryModal').modal('hide');
                                window.location.reload();
                            }, 1500);
                        } else {
                            toastr.error(response.message);
                            btn.prop('disabled', false).html(originalText);
                        }
                    },
                    error: function() {
                        toastr.error('Failed to update accessory');
                        btn.prop('disabled', false).html(originalText);
                    }
                });
            });

            // Bulk assign form submission
            // Find this section in your accessories.php JavaScript and update it:
            $('#bulkAssignForm').on('submit', function(e) {
                e.preventDefault();

                const accessories = $('input[name="accessories[]"]:checked').length;
                const items = $('#bulkItems').val();

                if (accessories === 0) {
                    toastr.error('Please select at least one accessory');
                    return;
                }

                if (!items || items.length === 0) {
                    toastr.error('Please select at least one equipment item');
                    return;
                }

                const btn = $('#bulkAssignBtn');
                const originalText = btn.html();

                btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i> Assigning...');

                $.ajax({
                    url: $(this).attr('action'),
                    type: 'POST',
                    data: $(this).serialize(),
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            toastr.success(response.message);
                            setTimeout(() => {
                                $('#bulkAssignModal').modal('hide');
                                window.location.reload();
                            }, 1500);
                        } else {
                            toastr.error(response.message || 'Failed to assign accessories');
                            btn.prop('disabled', false).html(originalText);
                        }
                    },
                    error: function(xhr, status, error) {
                        // Show detailed error information
                        console.error('AJAX Error:', xhr.responseText);
                        let errorMessage = 'Failed to save assignment';

                        try {
                            // Try to parse JSON response
                            const response = JSON.parse(xhr.responseText);
                            errorMessage = response.message || errorMessage;
                        } catch (e) {
                            // If not JSON, show raw response
                            if (xhr.responseText) {
                                errorMessage += ': ' + xhr.responseText.substring(0, 100);
                            }
                        }

                        toastr.error(errorMessage);
                        btn.prop('disabled', false).html(originalText);
                    }
                });
            });

            // Add accessory form validation
            $('#total_quantity, #available_quantity').on('change', function() {
                const total = parseInt($('#total_quantity').val()) || 0;
                const available = parseInt($('#available_quantity').val()) || 0;

                if (available > total) {
                    $('#available_quantity').val(total);
                    toastr.warning('Available quantity cannot exceed total quantity');
                }

                if (available < 0) {
                    $('#available_quantity').val(0);
                    toastr.warning('Available quantity cannot be negative');
                }
            });

            // Edit accessory form validation
            $('#edit_total_quantity, #edit_available_quantity').on('change', function() {
                const total = parseInt($('#edit_total_quantity').val()) || 0;
                const available = parseInt($('#edit_available_quantity').val()) || 0;
                const assigned = parseInt($('#editAccessoryForm').data('assigned')) || 0;

                if (available > total) {
                    $('#edit_available_quantity').val(total);
                    toastr.warning('Available quantity cannot exceed total quantity');
                }

                if (available < assigned) {
                    $('#edit_available_quantity').val(assigned);
                    toastr.warning('Available quantity cannot be less than assigned quantity (' + assigned + ')');
                }

                if (available < 0) {
                    $('#edit_available_quantity').val(0);
                    toastr.warning('Available quantity cannot be negative');
                }
            });

            // When editing, show assigned count
            $(document).on('click', '.edit-accessory-btn', function(e) {
                e.preventDefault();
                const id = $(this).data('id');
                const name = $(this).data('name');
                const category = $(this).data('category');
                const description = $(this).data('description');
                const total = $(this).data('total');
                const available = $(this).data('available');
                const minimum = $(this).data('minimum');
                const assigned = $(this).data('assigned') || 0; // Add this data attribute

                // Set form values
                $('#edit_id').val(id);
                $('#edit_name').val(name);
                $('#edit_category').val(category);
                $('#edit_description').val(description);
                $('#edit_total_quantity').val(total);
                $('#edit_available_quantity').val(available);
                $('#edit_minimum_stock').val(minimum);

                // Show warning if trying to set available less than assigned
                if (available < assigned) {
                    $('#editAccessoryModal .modal-body').prepend(
                        '<div class="alert alert-warning">' +
                        '<i class="fas fa-exclamation-triangle me-2"></i>' +
                        'This accessory is assigned to ' + assigned + ' item(s). ' +
                        'Available quantity cannot be less than ' + assigned + '.' +
                        '</div>'
                    );
                }

                $('#editAccessoryModal').modal('show');
            });

            // Export functionality
            $('#exportBtn').click(function() {
                window.location.href = 'api/accessories/export.php';
            });
            // Add Category Form
            $('#addCategoryForm').on('submit', function(e) {
                e.preventDefault();
                const form = this;
                const btn = $(this).find('button[type="submit"]');
                const originalText = btn.html();

                btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');

                $.ajax({
                    url: 'api/accessories/categories_add.php',
                    type: 'POST',
                    data: $(form).serialize(),
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            toastr.success(response.message);
                            setTimeout(() => window.location.reload(), 1000);
                        } else {
                            toastr.error(response.message);
                            btn.prop('disabled', false).html(originalText);
                        }
                    },
                    error: function() {
                        toastr.error('Failed to add category');
                        btn.prop('disabled', false).html(originalText);
                    }
                });
            });

            // Delete Category Button
            $('.delete-category-btn').click(function() {
                if (!confirm('Are you sure you want to delete this category?')) return;
                
                const btn = $(this);
                const id = btn.data('id');
                btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');

                $.ajax({
                    url: 'api/accessories/categories_delete.php',
                    type: 'POST',
                    data: { id: id },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            toastr.success(response.message);
                            setTimeout(() => window.location.reload(), 1000);
                        } else {
                            toastr.error(response.message);
                            btn.prop('disabled', false).html('<i class="fas fa-trash"></i>');
                        }
                    },
                    error: function() {
                        toastr.error('Failed to delete category');
                        btn.prop('disabled', false).html('<i class="fas fa-trash"></i>');
                    }
                });
            });
        <?php endif; ?>
    });
</script>