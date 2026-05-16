<?php
// items/view.php
require_once '../../bootstrap.php';
require_once '../../includes/db_connect.php';
require_once '../../includes/functions.php';

// Check if user is logged in
if (!isLoggedIn()) {
    header('Location: ../../login.php');
    exit();
}

// Check if ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: ../index.php');
    exit();
}

$id = intval($_GET['id']);

try {
    $db = new Database();
    $conn = $db->getConnection();

    // Fetch item with all fields - ENHANCED with joins
    $stmt = $conn->prepare("SELECT 
        i.*, 
        c.name as category_name,
        d.name as department_name,
        DATE_FORMAT(i.created_at, '%Y-%m-%d %H:%i:%s') as created_at_fmt,
        DATE_FORMAT(i.updated_at, '%Y-%m-%d %H:%i:%s') as updated_at_fmt
        FROM items i
        LEFT JOIN categories c ON i.category = c.id
        LEFT JOIN departments d ON i.department = d.id OR i.department = d.code
        WHERE i.id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $item = $result->fetch_assoc();
    $stmt->close();

    // Use formatted dates
    if ($item) {
        $item['created_at'] = $item['created_at_fmt'];
        $item['updated_at'] = $item['updated_at_fmt'];
    }

    if (!$item) {
        $_SESSION['error_message'] = 'Equipment not found';
        header('Location: ../index.php');
        exit();
    }
} catch (Exception $e) {
    $_SESSION['error_message'] = 'Error loading equipment details';
    header('Location: ../index.php');
    exit();
}

$page_title = "View: " . htmlspecialchars($item['item_name']);
include '../../includes/header.php';
?>

<div class="container-fluid">
    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="../index.php">Dashboard</a></li>
            <li class="breadcrumb-item"><a href="index.php">Equipment</a></li>
            <li class="breadcrumb-item active">View Equipment</li>
        </ol>
    </nav>

    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">
            <i class="fas fa-eye me-2"></i><?php echo htmlspecialchars($item['item_name']); ?>
        </h1>
        <div>
            <a href="edit.php?id=<?php echo $item['id']; ?>" class="btn btn-warning">
                <i class="fas fa-edit me-1"></i> Edit
            </a>
            <a href="index.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left me-1"></i> Back to List
            </a>
        </div>
    </div>

    <div class="row">
        <!-- Left Column: Item Details -->
        <div class="col-lg-8">
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>Equipment Details</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <table class="table table-sm">
                                <tr>
                                    <th width="40%">ID:</th>
                                    <td>#<?php echo $item['id']; ?></td>
                                </tr>
                                <tr>
                                    <th>Equipment Name:</th>
                                    <td class="fw-bold"><?php echo htmlspecialchars($item['item_name']); ?></td>
                                </tr>
                                <tr>
                                    <th>Serial Number:</th>
                                    <td><code><?php echo htmlspecialchars($item['serial_number']); ?></code></td>
                                </tr>
                                <tr>
                                    <th>Category:</th>
                                    <td><?php echo !empty($item['category_name']) ? htmlspecialchars($item['category_name']) : getCategoryBadge($item['category']); ?></td>
                                </tr>
                                <tr>
                                    <th>Department:</th>
                                    <td>
                                        <?php if (!empty($item['department_name'])): ?>
                                            <span class="badge bg-info text-dark">
                                                <i class="fas fa-building me-1"></i>
                                                <?php echo htmlspecialchars($item['department_name']); ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted">Not specified</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <tr>
                                    <th>Brand/Model:</th>
                                    <td><?php echo !empty($item['brand_model']) ? htmlspecialchars($item['brand_model']) : '<span class="text-muted">Not specified</span>'; ?></td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <table class="table table-sm">
                                <tr>
                                    <th width="40%">Status:</th>
                                    <td><?php echo getStatusBadge($item['status']); ?></td>
                                </tr>
                                <tr>
                                    <th>Condition:</th>
                                    <td><?php echo getConditionBadge($item['condition']); ?></td>
                                </tr>
                                <tr>
                                    <th>Stock Location:</th>
                                    <td><?php echo !empty($item['stock_location']) ? htmlspecialchars($item['stock_location']) : '<span class="text-muted">Not specified</span>'; ?></td>
                                </tr>
                                <tr>
                                    <th>Quantity:</th>
                                    <td>
                                        <span class="badge bg-secondary rounded-pill fs-6">
                                            <?php echo $item['quantity']; ?>
                                        </span>
                                    </td>
                                </tr>
                                <tr>
                                    <th>Created:</th>
                                    <td><?php echo $item['created_at']; ?></td>
                                </tr>
                                <tr>
                                    <th>Last Updated:</th>
                                    <td><?php echo $item['updated_at']; ?></td>
                                </tr>
                            </table>
                        </div>
                    </div>

                    <!-- Description -->
                    <?php if (!empty($item['description'])): ?>
                        <div class="mt-4">
                            <h6 class="border-bottom pb-2"><i class="fas fa-align-left me-2"></i>Description</h6>
                            <p class="mb-0"><?php echo nl2br(htmlspecialchars($item['description'])); ?></p>
                        </div>
                    <?php endif; ?>

                    <!-- Notes -->
                    <?php if (!empty($item['notes'])): ?>
                        <div class="mt-4">
                            <h6 class="border-bottom pb-2"><i class="fas fa-sticky-note me-2"></i>Notes</h6>
                            <p class="mb-0"><?php echo nl2br(htmlspecialchars($item['notes'])); ?></p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Right Column: Image and QR Code -->
        <div class="col-lg-4">
            <!-- Image -->
            <div class="card shadow-sm mb-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-image me-2"></i>Equipment Image</h5>
                </div>
                <div class="card-body text-center">
                    <?php if (!empty($item['image']) && file_exists('../../' . $item['image'])): ?>
                        <img src="../../<?php echo htmlspecialchars($item['image']); ?>"
                            class="img-fluid rounded"
                            alt="<?php echo htmlspecialchars($item['item_name']); ?>"
                            style="max-height: 300px;">
                    <?php else: ?>
                        <div class="py-5 text-muted">
                            <i class="fas fa-image fa-4x mb-3"></i>
                            <p class="mb-0">No image available</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- QR Code -->
            <!-- QR Code -->
            <div class="card shadow-sm">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-qrcode me-2"></i>QR Code</h5>
                </div>
                <div class="card-body text-center">
                    <?php if (!empty($item['qr_code'])):
                        $qr_data = json_decode($item['qr_code'], true);
                    ?>
                        <div class="mb-3">
                            <!-- Show QR code that links to display page -->
                            <a href="<?php echo $qr_data['url']; ?>" target="_blank">
                                <?php
                                // Generate QR code image on the fly
                                require_once '../../vendor/phpqrcode/qrlib.php';

                                $tempFile = tempnam(sys_get_temp_dir(), 'qr_');
                                QRcode::png($qr_data['url'], $tempFile, QR_ECLEVEL_L, 6, 2);

                                echo '<img src="data:image/png;base64,' . base64_encode(file_get_contents($tempFile)) . '" 
                         class="img-fluid" style="max-width: 200px;">';

                                unlink($tempFile);
                                ?>
                            </a>
                        </div>
                        <div class="small text-muted mb-2">
                            Scan to view equipment details
                        </div>
                        <div class="qr-info">
                            <div><strong>Name:</strong> <?php echo htmlspecialchars($qr_data['name']); ?></div>
                            <div><strong>Serial:</strong> <?php echo htmlspecialchars($qr_data['serial']); ?></div>
                            <div><strong>Location:</strong> <?php echo htmlspecialchars($qr_data['location']); ?></div>
                        </div>
                    <?php else: ?>
                        <div class="mb-3">
                            <i class="fas fa-qrcode fa-3x text-muted mb-3"></i>
                            <p class="text-muted">No QR code generated yet</p>
                        </div>
                        <button id="generateQRBtn" class="btn btn-outline-primary">
                            <i class="fas fa-qrcode me-1"></i> Generate QR Code
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ... existing code ... -->

<!-- Include QR Code library -->
<script src="https://cdn.jsdelivr.net/npm/qrcode@1.5.3/build/qrcode.min.js"></script>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        <?php if (!empty($item['qr_code'])): ?>
            // Generate QR code if it exists - REMOVE THIS IF YOU'RE USING IMAGE INSTEAD
            // const qrData = <?php echo json_encode($item['qr_code']); ?>;
            // QRCode.toCanvas(document.createElement('canvas'), qrData, function(error, canvas) {
            //     if (error) console.error(error);
            //     document.getElementById('qrcode').appendChild(canvas);
            // });
        <?php endif; ?>

        // Generate QR code button
        document.getElementById('generateQRBtn')?.addEventListener('click', function() {
            const btn = this;
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Generating...';

            fetch('../../api/items/generate_qr.php?id=<?php echo $item['id']; ?>')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert('Failed to generate QR code: ' + data.message);
                        btn.disabled = false;
                        btn.innerHTML = '<i class="fas fa-qrcode me-1"></i> Generate QR Code';
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-qrcode me-1"></i> Generate QR Code';
                });
        });
    });
</script>

<?php include '../../includes/footer.php'; ?>