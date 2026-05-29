<?php
// accessories.php - Accessory Management Page
$current_page = basename(__FILE__);

// Include bootstrap which handles session, functions, database, and BASE_URL
require_once 'includes/bootstrap.php';
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
$stats = [
    'total' => 0,
    'in_stock' => 0,
    'low_stock' => 0,
    'out_of_stock' => 0
];

if ($tableExists) {
    try {
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
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addAccessoryModal" <?php echo !$tableExists ? 'disabled' : ''; ?>>
            <i class="fas fa-plus me-1"></i> Add New Accessory
        </button>
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
                    <div class="col-md-6">
                        <input type="text" class="form-control" name="search" placeholder="Search accessories..."
                            value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <div class="col-md-4">
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
                <div class="table-responsive">
                    <table class="table table-bordered table-hover" id="accessoriesTable" width="100%" cellspacing="0">
                        <thead>
                            <tr>
                                <th>Name</th>
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
                                            <small>
                                                <?php echo $acc['assigned_count'] ?? 0; ?> item(s)
                                                <?php if (!empty($acc['assigned_items'])): ?>
                                                    <br>
                                                    <span class="text-muted">
                                                        <?php echo htmlspecialchars(substr($acc['assigned_items'], 0, 60)); ?>
                                                        <?php echo strlen($acc['assigned_items']) > 60 ? '...' : ''; ?>
                                                    </span>
                                                <?php endif; ?>
                                            </small>
                                        <?php else: ?>
                                            <span class="text-muted">Not assigned</span>
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
                                            <button class="btn btn-info edit-accessory-btn"
                                                data-id="<?php echo $acc['id'] ?? 0; ?>"
                                                data-name="<?php echo htmlspecialchars($acc['name'] ?? ''); ?>"
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

<?php
// Close database connection
$db->close();
require_once 'views/partials/footer.php';
?>

<script>
    $(document).ready(function() {
        <?php if ($tableExists && !empty($accessories)): ?>
            // Initialize DataTable
            $('#accessoriesTable').DataTable({
                pageLength: 25,
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
                }
            });

            // Edit accessory button
            $('.edit-accessory-btn').click(function() {
                const id = $(this).data('id');
                const name = $(this).data('name');
                const description = $(this).data('description');
                const total = $(this).data('total');
                const available = $(this).data('available');
                const minimum = $(this).data('minimum');

                $('#edit_id').val(id);
                $('#edit_name').val(name);
                $('#edit_description').val(description);
                $('#edit_total_quantity').val(total);
                $('#edit_available_quantity').val(available);
                $('#edit_minimum_stock').val(minimum);

                $('#editAccessoryModal').modal('show');
            });

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

                if (available > total) {
                    $('#edit_available_quantity').val(total);
                    toastr.warning('Available quantity cannot exceed total quantity');
                }

                if (available < 0) {
                    $('#edit_available_quantity').val(0);
                    toastr.warning('Available quantity cannot be negative');
                }
            });

            // When editing, show assigned count
            $('.edit-accessory-btn').click(function() {
                const id = $(this).data('id');
                const name = $(this).data('name');
                const description = $(this).data('description');
                const total = $(this).data('total');
                const available = $(this).data('available');
                const minimum = $(this).data('minimum');
                const assigned = $(this).data('assigned') || 0; // Add this data attribute

                // Set form values
                $('#edit_id').val(id);
                $('#edit_name').val(name);
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
        <?php endif; ?>
    });
</script>