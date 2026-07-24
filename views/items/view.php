<?php
// views/items/view.php - Modern Equipment Details View

$item_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$item = null;
$accessories = [];
$movements = [];

if ($item_id > 0) {
    // Fetch item details with joined names
    $stmt = $conn->prepare("
        SELECT i.*, 
               COALESCE(c.category_name, i.category) as category_label,
               COALESCE(d.department_name, i.department) as department_label,
               COALESCE(sl.location_name, i.stock_location) as location_label
        FROM items i
        LEFT JOIN categories c ON (i.category = c.id OR i.category = c.category_name)
        LEFT JOIN departments d ON (i.department = d.id OR i.department = d.department_name)
        LEFT JOIN stock_locations sl ON (i.stock_location = sl.id OR i.stock_location = sl.location_name)
        WHERE i.id = ?
    ");
    if ($stmt) {
        $stmt->bind_param("i", $item_id);
        $stmt->execute();
        $item = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    }

    // Fetch assigned accessories
    $accStmt = $conn->prepare("
        SELECT a.id, a.name, a.description, COALESCE(ia.quantity, 1) as quantity
        FROM accessories a
        INNER JOIN item_accessories ia ON a.id = ia.accessory_id
        WHERE ia.item_id = ?
        ORDER BY a.name
    ");
    if ($accStmt) {
        $accStmt->bind_param("i", $item_id);
        $accStmt->execute();
        $accessories = $accStmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $accStmt->close();
    }

    // Fetch recent movements if item serial exists
    if ($item && !empty($item['serial_number'])) {
        $movStmt = $conn->prepare("
            SELECT DISTINCT sm.id, sm.batch_number, sm.event_name, sm.source_name, sm.destination_name, 
                   sm.movement_type, sm.status, sm.created_at, sm.transport_driver
            FROM stock_movements sm
            LEFT JOIN batch_items bi ON sm.id = bi.movement_id
            WHERE bi.serial_number = ? OR bi.item_id = ?
            ORDER BY sm.created_at DESC
            LIMIT 10
        ");
        if ($movStmt) {
            $movStmt->bind_param("si", $item['serial_number'], $item_id);
            $movStmt->execute();
            $movements = $movStmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $movStmt->close();
        }
    }
}

if (!$item): ?>
    <div class="card border-0 shadow-sm p-5 text-center rounded-4 my-4">
        <div class="mb-3 text-warning">
            <i class="fas fa-exclamation-circle fa-4x"></i>
        </div>
        <h4 class="fw-bold text-dark">Equipment Not Found</h4>
        <p class="text-muted mb-4">The requested equipment item does not exist or has been removed.</p>
        <div>
            <a href="items.php" class="btn btn-primary rounded-pill px-4">
                <i class="fas fa-arrow-left me-2"></i>Back to Equipment List
            </a>
        </div>
    </div>
<?php return; endif; ?>

<!-- Header Navigation Bar -->
<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
    <div class="d-flex align-items-center gap-3">
        <a href="items.php" class="btn btn-light rounded-circle shadow-sm border p-2 d-flex align-items-center justify-content-center" style="width: 42px; height: 42px;" title="Back to List">
            <i class="fas fa-arrow-left text-dark"></i>
        </a>
        <div>
            <h4 class="fw-bold text-dark mb-0"><?php echo htmlspecialchars($item['item_name']); ?></h4>
            <span class="text-muted small">Serial: <code class="text-primary fw-bold"><?php echo htmlspecialchars($item['serial_number'] ?: 'N/A'); ?></code></span>
        </div>
    </div>
    <div class="d-flex align-items-center gap-2">
        <a href="items/edit.php?id=<?php echo $item['id']; ?>" class="btn btn-primary rounded-pill px-3">
            <i class="fas fa-edit me-1"></i> Edit Equipment
        </a>
        <button onclick="window.print();" class="btn btn-outline-secondary rounded-pill px-3">
            <i class="fas fa-print me-1"></i> Print Details
        </button>
    </div>
</div>

<div class="row g-4">
    <!-- Left Column: Item Image & Quick Status -->
    <div class="col-lg-4">
        <!-- Image Card -->
        <div class="card border-0 shadow-sm rounded-4 p-3 text-center bg-white mb-4">
            <div class="position-relative d-inline-block mx-auto mb-3">
                <?php if (!empty($item['image'])): ?>
                    <img src="<?php echo htmlspecialchars($item['image']); ?>" class="img-fluid rounded-3 border shadow-sm" style="max-height: 250px; object-fit: cover;" onerror="this.outerHTML='<div class=\'bg-light p-5 rounded-3 border d-flex align-items-center justify-content-center\' style=\'height: 200px;\'><i class=\'fas fa-boxes fa-4x text-secondary\'></i></div>'">
                <?php else: ?>
                    <div class="bg-light p-5 rounded-3 border d-flex align-items-center justify-content-center" style="height: 200px; width: 100%;">
                        <i class="fas fa-boxes fa-4x text-secondary opacity-50"></i>
                    </div>
                <?php endif; ?>
            </div>

            <div class="d-flex align-items-center justify-content-center gap-2 mb-2">
                <?php
                $status = strtolower($item['status'] ?? 'available');
                $badgeBg = 'bg-secondary';
                if ($status === 'available') $badgeBg = 'bg-success';
                elseif ($status === 'in_use' || $status === 'on_event') $badgeBg = 'bg-primary';
                elseif ($status === 'maintenance') $badgeBg = 'bg-warning text-dark';
                ?>
                <span class="badge <?php echo $badgeBg; ?> px-3 py-2 fs-6 rounded-pill text-uppercase">
                    <?php echo htmlspecialchars(ucfirst($item['status'])); ?>
                </span>
            </div>

            <p class="text-muted small mb-0">Condition: <strong class="text-dark"><?php echo htmlspecialchars(ucfirst($item['condition'] ?? 'Good')); ?></strong></p>
        </div>

        <!-- QR Code Card -->
        <div class="card border-0 shadow-sm rounded-4 p-3 text-center bg-white">
            <h6 class="fw-bold text-muted small text-uppercase mb-3"><i class="fas fa-qrcode me-1 text-primary"></i> QR Code Identification</h6>
            <div class="mb-3">
                <?php if (!empty($item['qr_code'])): ?>
                    <img src="<?php echo htmlspecialchars($item['qr_code']); ?>" alt="QR Code" class="img-fluid border p-2 rounded bg-white shadow-sm" style="width: 140px; height: 140px;">
                <?php else: ?>
                    <div class="bg-light p-4 rounded border text-muted small d-inline-block">
                        <i class="fas fa-qrcode fa-3x mb-2 text-secondary opacity-50"></i><br>
                        No QR Code Generated
                    </div>
                <?php endif; ?>
            </div>
            <?php if (!empty($item['qr_code'])): ?>
                <a href="<?php echo htmlspecialchars($item['qr_code']); ?>" download="QR_<?php echo htmlspecialchars($item['serial_number']); ?>.png" class="btn btn-sm btn-outline-primary rounded-pill px-3">
                    <i class="fas fa-download me-1"></i> Download QR
                </a>
            <?php else: ?>
                <button class="btn btn-sm btn-primary rounded-pill px-3" onclick="generateQRCode(<?php echo $item['id']; ?>)">
                    <i class="fas fa-magic me-1"></i> Generate QR Code
                </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- Right Column: Specs, Accessories & Activity -->
    <div class="col-lg-8">
        <!-- Overview Specs Card -->
        <div class="card border-0 shadow-sm rounded-4 p-4 bg-white mb-4">
            <h5 class="fw-bold text-dark mb-3"><i class="fas fa-info-circle text-primary me-2"></i> Equipment Specifications</h5>
            
            <div class="row g-3">
                <div class="col-md-6">
                    <div class="p-3 bg-light rounded-3 border">
                        <span class="text-muted small d-block text-uppercase fw-bold">Brand & Model</span>
                        <strong class="text-dark fs-6"><?php echo htmlspecialchars($item['brand_model'] ?: 'N/A'); ?></strong>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="p-3 bg-light rounded-3 border">
                        <span class="text-muted small d-block text-uppercase fw-bold">Category</span>
                        <strong class="text-dark fs-6"><?php echo htmlspecialchars($item['category_label'] ?: 'Uncategorized'); ?></strong>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="p-3 bg-light rounded-3 border">
                        <span class="text-muted small d-block text-uppercase fw-bold">Department</span>
                        <strong class="text-dark fs-6"><?php echo htmlspecialchars($item['department_label'] ?: 'N/A'); ?></strong>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="p-3 bg-light rounded-3 border">
                        <span class="text-muted small d-block text-uppercase fw-bold">Current Stock Location</span>
                        <strong class="text-dark fs-6"><i class="fas fa-warehouse text-warning me-1"></i><?php echo htmlspecialchars($item['location_label'] ?: 'Main Warehouse'); ?></strong>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="p-3 bg-light rounded-3 border">
                        <span class="text-muted small d-block text-uppercase fw-bold">Quantity Units</span>
                        <span class="badge bg-primary fs-6"><?php echo (int)($item['quantity'] ?? 1); ?> Units</span>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="p-3 bg-light rounded-3 border">
                        <span class="text-muted small d-block text-uppercase fw-bold">System ID</span>
                        <code class="text-dark fw-bold">#<?php echo $item['id']; ?></code>
                    </div>
                </div>
            </div>

            <?php if (!empty($item['description'])): ?>
                <div class="mt-3 pt-3 border-top">
                    <span class="text-muted small d-block text-uppercase fw-bold mb-1">Description / Notes</span>
                    <p class="text-dark mb-0"><?php echo nl2br(htmlspecialchars($item['description'])); ?></p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Assigned Accessories Card -->
        <div class="card border-0 shadow-sm rounded-4 overflow-hidden bg-white mb-4">
            <div class="card-header bg-white border-0 py-3 d-flex align-items-center justify-content-between">
                <h5 class="fw-bold text-dark m-0"><i class="fas fa-plug text-warning me-2"></i> Assigned Accessories</h5>
                <span class="badge bg-warning bg-opacity-10 text-dark border fw-bold"><?php echo count($accessories); ?> Items</span>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light text-muted small text-uppercase">
                        <tr>
                            <th class="ps-4">Accessory Name</th>
                            <th>Description</th>
                            <th class="pe-4 text-end">Quantity</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($accessories)): ?>
                            <?php foreach ($accessories as $acc): ?>
                                <tr>
                                    <td class="ps-4 fw-bold text-dark"><?php echo htmlspecialchars($acc['name']); ?></td>
                                    <td class="text-muted small"><?php echo htmlspecialchars($acc['description'] ?: 'N/A'); ?></td>
                                    <td class="pe-4 text-end"><span class="badge bg-secondary"><?php echo (int)$acc['quantity']; ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="3" class="text-center py-4 text-muted"><i class="fas fa-info-circle me-1"></i> No accessories assigned to this equipment.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Recent Movements Card -->
        <div class="card border-0 shadow-sm rounded-4 overflow-hidden bg-white">
            <div class="card-header bg-white border-0 py-3 d-flex align-items-center justify-content-between">
                <h5 class="fw-bold text-dark m-0"><i class="fas fa-history text-info me-2"></i> Recent Movement History</h5>
                <span class="badge bg-info bg-opacity-10 text-info fw-bold"><?php echo count($movements); ?> Movements</span>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light text-muted small text-uppercase">
                        <tr>
                            <th class="ps-4">Batch / Event</th>
                            <th>Route</th>
                            <th>Driver</th>
                            <th>Status</th>
                            <th class="pe-4 text-end">Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($movements)): ?>
                            <?php foreach ($movements as $m): ?>
                                <tr>
                                    <td class="ps-4">
                                        <div class="fw-bold text-dark"><?php echo htmlspecialchars($m['event_name'] ?: $m['batch_number']); ?></div>
                                        <small class="text-muted"><?php echo htmlspecialchars($m['batch_number']); ?></small>
                                    </td>
                                    <td>
                                        <small class="d-block text-dark"><i class="fas fa-map-marker-alt text-danger me-1"></i><?php echo htmlspecialchars($m['source_name'] ?: 'Warehouse'); ?> &rarr; <?php echo htmlspecialchars($m['destination_name'] ?: 'Venue'); ?></small>
                                    </td>
                                    <td>
                                        <span class="small text-muted"><i class="fas fa-truck text-secondary me-1"></i><?php echo htmlspecialchars($m['transport_driver'] ?: 'N/A'); ?></span>
                                    </td>
                                    <td>
                                        <?php
                                        $st = strtolower($m['status'] ?? '');
                                        $stClass = 'bg-secondary';
                                        if ($st === 'completed' || $st === 'approved') $stClass = 'bg-success';
                                        elseif ($st === 'pending' || $st === 'in_transit') $stClass = 'bg-warning text-dark';
                                        ?>
                                        <span class="badge <?php echo $stClass; ?>"><?php echo htmlspecialchars(ucfirst($m['status'])); ?></span>
                                    </td>
                                    <td class="pe-4 text-end text-muted small">
                                        <?php echo substr($m['created_at'], 0, 10); ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="text-center py-4 text-muted"><i class="fas fa-info-circle me-1"></i> No movement records found for this equipment.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>