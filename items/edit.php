<?php
// items/edit.php - Enhanced with accessories editing
session_start();
require_once '../includes/bootstrap.php';

if (!isLoggedIn()) {
    header('Location: ../login.php');
    exit();
}

$item_id = $_GET['id'] ?? 0;
$item_id = intval($item_id);

if ($item_id < 1) {
    header('Location: ../dashboard_full.php');
    exit();
}

try {
    $db = new DatabaseFix();
    $conn = $db->getConnection();

    // Get item details
    $stmt = $conn->prepare("
        SELECT * FROM items WHERE id = ?
    ");
    $stmt->bind_param("i", $item_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $item = $result->fetch_assoc();
    $stmt->close();

    if (!$item) {
        $_SESSION['error_message'] = 'Equipment item not found';
        header('Location: ../dashboard_full.php');
        exit();
    }

    // Get current accessories
    $accStmt = $conn->prepare("
        SELECT a.id, a.name, a.description, ia.quantity
        FROM accessories a
        INNER JOIN item_accessories ia ON a.id = ia.accessory_id
        WHERE ia.item_id = ?
        ORDER BY a.name
    ");
    $accStmt->bind_param("i", $item_id);
    $accStmt->execute();
    $accResult = $accStmt->get_result();
    $current_accessories = $accResult->fetch_all(MYSQLI_ASSOC);
    $accStmt->close();

    // Get all accessories for dropdown
    $allAccStmt = $conn->prepare("
        SELECT id, name, description, available_quantity 
        FROM accessories 
        WHERE is_active = 1 
        ORDER BY name
    ");
    $allAccStmt->execute();
    $allAccResult = $allAccStmt->get_result();
    $all_accessories = $allAccResult->fetch_all(MYSQLI_ASSOC);
    $allAccStmt->close();
} catch (Exception $e) {
    die("Database error: " . $e->getMessage());
}

$pageTitle = "Edit Equipment - aBility";
$showBreadcrumb = true;
$breadcrumbItems = [
    'Dashboard' => '../dashboard_full.php',
    'View Item' => 'view.php?id=' . $item_id,
    'Edit' => ''
];

require_once '../views/partials/header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0 text-gray-800">
            <i class="fas fa-edit me-2"></i>
            Edit Equipment: <?php echo htmlspecialchars($item['item_name']); ?>
        </h1>
        <a href="view.php?id=<?php echo $item_id; ?>" class="btn btn-secondary">
            <i class="fas fa-arrow-left me-1"></i> Back to View
        </a>
    </div>

    <!-- Messages -->
    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?php echo $_SESSION['success_message']; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php unset($_SESSION['success_message']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['error_message'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?php echo $_SESSION['error_message']; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php unset($_SESSION['error_message']); ?>
    <?php endif; ?>

    <div class="row">
        <div class="col-lg-8">
            <!-- Basic Information Form -->
            <div class="card shadow mb-4">
                <div class="card-header bg-primary text-white py-3">
                    <h6 class="m-0 font-weight-bold">
                        <i class="fas fa-info-circle me-2"></i>Basic Information
                    </h6>
                </div>
                <div class="card-body">
                    <form id="editItemForm" method="POST" action="../api/items/update.php" enctype="multipart/form-data">
                        <input type="hidden" name="item_id" value="<?php echo $item_id; ?>">

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="item_name" class="form-label required">Equipment Name</label>
                                    <input type="text" class="form-control" id="item_name" name="item_name"
                                        value="<?php echo htmlspecialchars($item['item_name']); ?>" required>
                                </div>

                                <div class="mb-3">
                                    <label for="serial_number" class="form-label required">Serial Number</label>
                                    <input type="text" class="form-control" id="serial_number" name="serial_number"
                                        value="<?php echo htmlspecialchars($item['serial_number']); ?>" required>
                                </div>

                                <div class="mb-3">
                                    <label for="quantity" class="form-label">Quantity</label>
                                    <input type="number" class="form-control" id="quantity" name="quantity"
                                        value="<?php echo $item['quantity']; ?>" min="1">
                                </div>

                                <div class="mb-3">
                                    <label for="brand" class="form-label">Brand</label>
                                    <input type="text" class="form-control" id="brand" name="brand"
                                        value="<?php echo htmlspecialchars($item['brand'] ?? ''); ?>">
                                </div>

                                <div class="mb-3">
                                    <label for="model" class="form-label">Model</label>
                                    <input type="text" class="form-control" id="model" name="model"
                                        value="<?php echo htmlspecialchars($item['model'] ?? ''); ?>">
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="category" class="form-label required">Category</label>
                                    <select class="form-select" id="category" name="category" required>
                                        <option value="">Select Category</option>
                                        <?php
                                        $categories = getCategories();
                                        foreach ($categories as $key => $value):
                                        ?>
                                            <option value="<?php echo $key; ?>"
                                                <?php echo ($item['category'] ?? '') === $key ? 'selected' : ''; ?>>
                                                <?php echo $value; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="mb-3">
                                    <label for="department" class="form-label">Department</label>
                                    <select class="form-select" id="department" name="department">
                                        <option value="">Select Department</option>
                                        <?php
                                        $departments = getDepartments();
                                        foreach ($departments as $key => $value):
                                        ?>
                                            <option value="<?php echo $key; ?>"
                                                <?php echo ($item['department'] ?? '') === $key ? 'selected' : ''; ?>>
                                                <?php echo $value; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="mb-3">
                                    <label for="status" class="form-label">Status</label>
                                    <select class="form-select" id="status" name="status">
                                        <?php
                                        $statuses = getStatuses();
                                        foreach ($statuses as $key => $value):
                                        ?>
                                            <option value="<?php echo $key; ?>"
                                                <?php echo ($item['status'] ?? 'available') === $key ? 'selected' : ''; ?>>
                                                <?php echo $value; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="mb-3">
                                    <label for="condition" class="form-label">Condition</label>
                                    <select class="form-select" id="condition" name="condition">
                                        <?php
                                        $conditions = getConditions();
                                        foreach ($conditions as $key => $value):
                                        ?>
                                            <option value="<?php echo $key; ?>"
                                                <?php echo ($item['condition'] ?? 'good') === $key ? 'selected' : ''; ?>>
                                                <?php echo $value; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="mb-3">
                                    <label for="stock_location" class="form-label">Location</label>
                                    <select class="form-select" id="stock_location" name="stock_location">
                                        <option value="">Select Location</option>
                                        <?php
                                        $locations = getLocations();
                                        foreach ($locations as $key => $value):
                                        ?>
                                            <option value="<?php echo $key; ?>"
                                                <?php echo ($item['stock_location'] ?? '') === $key ? 'selected' : ''; ?>>
                                                <?php echo $value; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="description" class="form-label">Description</label>
                            <textarea class="form-control" id="description" name="description" rows="3"><?php echo htmlspecialchars($item['description'] ?? ''); ?></textarea>
                        </div>

                        <div class="mb-3">
                            <label for="notes" class="form-label">Notes</label>
                            <textarea class="form-control" id="notes" name="notes" rows="2"><?php echo htmlspecialchars($item['notes'] ?? ''); ?></textarea>
                        </div>

                        <div class="mb-3">
                            <label for="item_image" class="form-label">Equipment Image</label>
                            <?php if (!empty($item['image'])): ?>
                                <div class="mb-2">
                                    <img src="../<?php echo htmlspecialchars($item['image']); ?>"
                                        alt="Current Image" class="img-thumbnail" style="max-height: 150px;">
                                    <div class="form-check mt-2">
                                        <input class="form-check-input" type="checkbox" id="remove_image" name="remove_image" value="1">
                                        <label class="form-check-label" for="remove_image">
                                            Remove current image
                                        </label>
                                    </div>
                                </div>
                            <?php endif; ?>
                            <input type="file" class="form-control" id="item_image" name="item_image" accept="image/*">
                            <div class="form-text">Leave empty to keep current image</div>
                        </div>

                        <div class="d-flex justify-content-end">
                            <button type="submit" class="btn btn-primary" id="saveItemBtn">
                                <i class="fas fa-save me-1"></i> Save Changes
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <!-- Accessories Management -->
            <div class="card shadow mb-4">
                <div class="card-header bg-warning text-white py-3">
                    <h6 class="m-0 font-weight-bold">
                        <i class="fas fa-puzzle-piece me-2"></i>Accessories Management
                    </h6>
                </div>
                <div class="card-body">
                    <form id="editAccessoriesForm" method="POST" action="../api/accessories/update_item_accessories.php">
                        <input type="hidden" name="item_id" value="<?php echo $item_id; ?>">

                        <div class="mb-3">
                            <label class="form-label">Current Accessories</label>
                            <div id="currentAccessories" class="border rounded p-3 mb-3" style="min-height: 100px; max-height: 200px; overflow-y: auto;">
                                <?php if (!empty($current_accessories)): ?>
                                    <?php foreach ($current_accessories as $acc): ?>
                                        <div class="d-flex justify-content-between align-items-center mb-2 accessory-item" data-id="<?php echo $acc['id']; ?>">
                                            <div>
                                                <strong><?php echo htmlspecialchars($acc['name']); ?></strong>
                                                <?php if (!empty($acc['description'])): ?>
                                                    <br><small class="text-muted"><?php echo htmlspecialchars($acc['description']); ?></small>
                                                <?php endif; ?>
                                            </div>
                                            <button type="button" class="btn btn-sm btn-danger remove-accessory-btn">
                                                <i class="fas fa-times"></i>
                                            </button>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <p class="text-muted mb-0">No accessories assigned</p>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="addAccessory" class="form-label">Add Accessory</label>
                            <div class="input-group">
                                <select class="form-select" id="addAccessory">
                                    <option value="">Select accessory to add...</option>
                                    <?php foreach ($all_accessories as $acc): ?>
                                        <option value="<?php echo $acc['id']; ?>"
                                            data-name="<?php echo htmlspecialchars($acc['name']); ?>"
                                            data-description="<?php echo htmlspecialchars($acc['description'] ?? ''); ?>">
                                            <?php echo htmlspecialchars($acc['name']); ?>
                                            (<?php echo $acc['available_quantity']; ?> available)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="button" class="btn btn-success" id="addAccessoryBtn">
                                    <i class="fas fa-plus"></i>
                                </button>
                            </div>
                        </div>

                        <input type="hidden" id="accessoriesInput" name="accessories">

                        <div class="d-flex justify-content-end">
                            <button type="submit" class="btn btn-warning" id="saveAccessoriesBtn">
                                <i class="fas fa-save me-1"></i> Update Accessories
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="card shadow">
                <div class="card-header bg-info text-white py-3">
                    <h6 class="m-0 font-weight-bold">
                        <i class="fas fa-bolt me-2"></i>Quick Actions
                    </h6>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a href="view.php?id=<?php echo $item_id; ?>" class="btn btn-outline-info">
                            <i class="fas fa-eye me-2"></i> View Details
                        </a>
                        <?php if (empty($item['qr_code'])): ?>
                            <button class="btn btn-outline-success generate-qr-btn" data-item-id="<?php echo $item_id; ?>">
                                <i class="fas fa-qrcode me-2"></i> Generate QR Code
                            </button>
                        <?php else: ?>
                            <a href="../<?php echo $item['qr_code']; ?>" class="btn btn-outline-success" target="_blank">
                                <i class="fas fa-qrcode me-2"></i> View QR Code
                            </a>
                        <?php endif; ?>
                        <a href="../items/print_label.php?id=<?php echo $item_id; ?>" class="btn btn-outline-secondary" target="_blank">
                            <i class="fas fa-print me-2"></i> Print Label
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    $(document).ready(function() {
        let currentAccessories = <?php echo json_encode(array_column($current_accessories, 'id')); ?>;

        // Update hidden input with accessories list
        function updateAccessoriesInput() {
            $('#accessoriesInput').val(currentAccessories.join(','));
        }

        // Initialize
        updateAccessoriesInput();

        // Remove accessory button
        $(document).on('click', '.remove-accessory-btn', function() {
            const accessoryId = $(this).closest('.accessory-item').data('id');
            const index = currentAccessories.indexOf(accessoryId);

            if (index > -1) {
                currentAccessories.splice(index, 1);
                $(this).closest('.accessory-item').remove();
                updateAccessoriesInput();

                if (currentAccessories.length === 0) {
                    $('#currentAccessories').html('<p class="text-muted mb-0">No accessories assigned</p>');
                }
            }
        });

        // Add accessory button
        $('#addAccessoryBtn').click(function() {
            const select = $('#addAccessory');
            const selectedOption = select.find('option:selected');
            const accessoryId = selectedOption.val();

            if (!accessoryId) {
                toastr.error('Please select an accessory');
                return;
            }

            if (currentAccessories.includes(parseInt(accessoryId))) {
                toastr.error('This accessory is already added');
                return;
            }

            const accessoryName = selectedOption.data('name');
            const accessoryDesc = selectedOption.data('description');

            // Add to current accessories array
            currentAccessories.push(parseInt(accessoryId));

            // Add to display
            const accessoryHtml = `
            <div class="d-flex justify-content-between align-items-center mb-2 accessory-item" data-id="${accessoryId}">
                <div>
                    <strong>${accessoryName}</strong>
                    ${accessoryDesc ? '<br><small class="text-muted">' + accessoryDesc + '</small>' : ''}
                </div>
                <button type="button" class="btn btn-sm btn-danger remove-accessory-btn">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        `;

            if ($('#currentAccessories').find('.text-muted').length > 0) {
                $('#currentAccessories').html(accessoryHtml);
            } else {
                $('#currentAccessories').append(accessoryHtml);
            }

            // Update hidden input
            updateAccessoriesInput();

            // Reset select
            select.val('');
        });

        // Edit item form submission
        $('#editItemForm').on('submit', function(e) {
            e.preventDefault();

            const form = this;
            const formData = new FormData(form);
            const btn = $('#saveItemBtn');
            const originalText = btn.html();

            btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i> Saving...');

            $.ajax({
                url: form.action,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
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
                    toastr.error('Failed to save changes');
                    btn.prop('disabled', false).html(originalText);
                }
            });
        });

        // Edit accessories form submission
        $('#editAccessoriesForm').on('submit', function(e) {
            e.preventDefault();

            const btn = $('#saveAccessoriesBtn');
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
                    toastr.error('Failed to update accessories');
                    btn.prop('disabled', false).html(originalText);
                }
            });
        });

        // Generate QR code
        $('.generate-qr-btn').click(function() {
            const itemId = $(this).data('item-id');

            toastr.info('Generating QR code...');

            $.ajax({
                url: '../api/generate_qr.php',
                method: 'POST',
                data: {
                    item_id: itemId
                },
                success: function(response) {
                    if (response.success) {
                        toastr.success('QR code generated successfully');
                        setTimeout(() => {
                            window.location.reload();
                        }, 1000);
                    } else {
                        toastr.error(response.message || 'Failed to generate QR code');
                    }
                },
                error: function() {
                    toastr.error('Failed to generate QR code');
                }
            });
        });
    });
</script>

<?php
$db->close();
require_once '../views/partials/footer.php';
?>