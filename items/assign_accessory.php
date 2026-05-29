<?php
// items/assign_accessory.php
session_start();
require_once '../includes/bootstrap.php';

if (!isLoggedIn()) {
    header('Location: ../login.php');
    exit();
}

$accessory_id = $_GET['accessory_id'] ?? 0;
$accessory_id = intval($accessory_id);

if ($accessory_id < 1) {
    header('Location: ../accessories.php');
    exit();
}

try {
    $db = new DatabaseFix();
    $conn = $db->getConnection();

    // Get accessory details
    $accStmt = $conn->prepare("
        SELECT id, name, description, available_quantity 
        FROM accessories 
        WHERE id = ? AND is_active = 1
    ");
    $accStmt->bind_param("i", $accessory_id);
    $accStmt->execute();
    $accResult = $accStmt->get_result();
    $accessory = $accResult->fetch_assoc();
    $accStmt->close();

    if (!$accessory) {
        $_SESSION['error_message'] = 'Accessory not found';
        header('Location: ../accessories.php');
        exit();
    }

    // Get items already assigned this accessory
    $assignedStmt = $conn->prepare("
        SELECT i.id, i.item_name, i.serial_number, i.category
        FROM items i
        INNER JOIN item_accessories ia ON i.id = ia.item_id
        WHERE ia.accessory_id = ?
        ORDER BY i.item_name
    ");
    $assignedStmt->bind_param("i", $accessory_id);
    $assignedStmt->execute();
    $assignedResult = $assignedStmt->get_result();
    $assigned_items = $assignedResult->fetch_all(MYSQLI_ASSOC);
    $assignedStmt->close();

    // Get all items
    $itemsStmt = $conn->prepare("
        SELECT i.id, i.item_name, i.serial_number, i.category,
               CASE WHEN ia.accessory_id IS NOT NULL THEN 1 ELSE 0 END as is_assigned
        FROM items i
        LEFT JOIN item_accessories ia ON i.id = ia.item_id AND ia.accessory_id = ?
        ORDER BY i.item_name
    ");
    $itemsStmt->bind_param("i", $accessory_id);
    $itemsStmt->execute();
    $itemsResult = $itemsStmt->get_result();
    $all_items = $itemsResult->fetch_all(MYSQLI_ASSOC);
    $itemsStmt->close();
} catch (Exception $e) {
    die("Database error: " . $e->getMessage());
}

$pageTitle = "Assign Accessory - aBility";
$showBreadcrumb = true;
$breadcrumbItems = [
    'Dashboard' => 'dashboard_full.php',
    'Accessories' => 'accessories.php',
    'Assign ' . $accessory['name'] => ''
];

require_once '../views/partials/header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0 text-gray-800">
            <i class="fas fa-link me-2"></i>
            Assign: <?php echo htmlspecialchars($accessory['name']); ?>
        </h1>
        <a href="../accessories.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left me-1"></i> Back to Accessories
        </a>
    </div>

    <div class="row">
        <div class="col-lg-4">
            <!-- Accessory Info Card -->
            <div class="card shadow mb-4">
                <div class="card-header bg-primary text-white py-3">
                    <h6 class="m-0 font-weight-bold">
                        <i class="fas fa-puzzle-piece me-2"></i>Accessory Information
                    </h6>
                </div>
                <div class="card-body">
                    <h5 class="card-title"><?php echo htmlspecialchars($accessory['name']); ?></h5>
                    <?php if (!empty($accessory['description'])): ?>
                        <p class="card-text"><?php echo htmlspecialchars($accessory['description']); ?></p>
                    <?php endif; ?>
                    <hr>
                    <div class="mb-3">
                        <strong>Available Quantity:</strong>
                        <span class="badge <?php echo $accessory['available_quantity'] > 5 ? 'bg-success' : ($accessory['available_quantity'] > 0 ? 'bg-warning' : 'bg-danger'); ?> ms-2">
                            <?php echo $accessory['available_quantity']; ?>
                        </span>
                    </div>
                    <div class="mb-3">
                        <strong>Currently Assigned to:</strong>
                        <span class="badge bg-info ms-2">
                            <?php echo count($assigned_items); ?> items
                        </span>
                    </div>
                </div>
            </div>

            <!-- Quick Stats -->
            <div class="card shadow">
                <div class="card-header bg-info text-white py-3">
                    <h6 class="m-0 font-weight-bold">
                        <i class="fas fa-chart-bar me-2"></i>Assignment Statistics
                    </h6>
                </div>
                <div class="card-body">
                    <div class="text-center">
                        <div class="display-4"><?php echo count($assigned_items); ?></div>
                        <p class="text-muted">Items with this accessory</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <!-- Assignment Form -->
            <div class="card shadow mb-4">
                <div class="card-header bg-success text-white py-3">
                    <h6 class="m-0 font-weight-bold">
                        <i class="fas fa-plus-circle me-2"></i>Assign to Equipment
                    </h6>
                </div>
                <div class="card-body">
                    <form id="assignForm" method="POST" action="../api/accessories/assign_single.php">
                        <input type="hidden" name="accessory_id" value="<?php echo $accessory_id; ?>">

                        <div class="mb-3">
                            <label class="form-label required">Select Equipment Items</label>
                            <select class="form-select" id="equipmentSelect" name="item_ids[]" multiple size="10" required>
                                <?php foreach ($all_items as $item): ?>
                                    <option value="<?php echo $item['id']; ?>"
                                        <?php echo $item['is_assigned'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($item['item_name']); ?>
                                        (<?php echo htmlspecialchars($item['serial_number']); ?>)
                                        - <?php echo htmlspecialchars($item['category']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text">
                                Hold Ctrl/Cmd to select/deselect multiple items. Selected items will have this accessory.
                            </div>
                        </div>

                        <div class="d-flex justify-content-between">
                            <button type="button" class="btn btn-outline-secondary" id="selectAllBtn">
                                <i class="fas fa-check-square me-1"></i> Select All
                            </button>
                            <button type="button" class="btn btn-outline-secondary" id="deselectAllBtn">
                                <i class="fas fa-square me-1"></i> Deselect All
                            </button>
                            <button type="submit" class="btn btn-success" id="assignBtn">
                                <i class="fas fa-save me-1"></i> Save Assignments
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Currently Assigned Items -->
            <div class="card shadow">
                <div class="card-header bg-warning text-white py-3">
                    <h6 class="m-0 font-weight-bold">
                        <i class="fas fa-list me-2"></i>Currently Assigned Items
                    </h6>
                </div>
                <div class="card-body">
                    <?php if (!empty($assigned_items)): ?>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Item Name</th>
                                        <th>Serial Number</th>
                                        <th>Category</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($assigned_items as $item): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($item['item_name']); ?></td>
                                            <td><code><?php echo htmlspecialchars($item['serial_number']); ?></code></td>
                                            <td><?php echo getCategoryBadge($item['category']); ?></td>
                                            <td>
                                                <a href="../items.php?action=view&id=<?php echo $item['id']; ?>"
                                                    class="btn btn-sm btn-info" title="View Item">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <i class="fas fa-unlink fa-3x text-muted mb-3"></i>
                            <h5>Not assigned to any equipment</h5>
                            <p class="text-muted">Select items above to assign this accessory</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$db->close();
require_once '../views/partials/footer.php';
?>

<script>
    $(document).ready(function() {
        // Select all items
        $('#selectAllBtn').click(function() {
            $('#equipmentSelect option').prop('selected', true);
        });

        // Deselect all items
        $('#deselectAllBtn').click(function() {
            $('#equipmentSelect option').prop('selected', false);
        });

        // Form submission
        $('#assignForm').on('submit', function(e) {
            e.preventDefault();

            const btn = $('#assignBtn');
            const originalText = btn.html();

            btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i> Saving...');

            $.ajax({
                url: $(this).attr('action'),
                type: 'POST',
                data: $(this).serialize(),
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        toastr.success(response.message);
                        setTimeout(() => {
                            window.location.reload();
                        }, 1500);
                    } else {
                        toastr.error(response.message);
                        btn.prop('disabled', false).html(originalText);
                    }
                },
                error: function() {
                    toastr.error('Failed to save assignments');
                    btn.prop('disabled', false).html(originalText);
                }
            });
        });
    });
</script>