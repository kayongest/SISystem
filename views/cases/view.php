<?php
// views/cases/view.php - Fly Case Detail & Packing View
$current_page = 'cases.php';

if (!$id) {
    echo "<div class='alert alert-danger'>Invalid Fly Case ID.</div>";
    exit();
}

// Fetch case details
$case = null;
try {
    $stmt = $conn->prepare("SELECT * FROM items WHERE id = ? AND (category = 'Cases' OR item_name LIKE '%Case%')");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $case = $result->fetch_assoc();
    $stmt->close();
} catch (Exception $e) {
    error_log("Error fetching case: " . $e->getMessage());
}

if (!$case) {
    echo "<div class='alert alert-danger'>Fly Case not found in the database.</div>";
    exit();
}

// Fetch packed items list
$packedItems = [];
try {
    $caseName = $case['item_name'];
    $caseSerial = $case['serial_number'];
    
    $stmt = $conn->prepare("
        SELECT id, item_name, serial_number, category, `condition`, status, stock_location
        FROM items
        WHERE storage_location = ? OR storage_location = ?
        ORDER BY item_name ASC
    ");
    $stmt->bind_param('ss', $caseName, $caseSerial);
    $stmt->execute();
    $result = $stmt->get_result();
    $packedItems = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} catch (Exception $e) {
    error_log("Error fetching packed items: " . $e->getMessage());
}
?>

<div class="row mb-3" data-aos="fade-down">
    <div class="col-12">
        <a href="cases.php" class="btn btn-sm btn-light border rounded-pill px-3 mb-3">
            <i class="fas fa-arrow-left me-1"></i> Back to Cases
        </a>
        <h2 class="fw-bold text-dark mb-1">
            <i class="fas fa-box-open me-2 text-primary"></i> <?php echo htmlspecialchars($case['item_name']); ?>
        </h2>
        <p class="text-muted">Manage the equipment packed inside this case.</p>
    </div>
</div>

<div class="row g-4">
    <!-- Left Column: Case Metadata & QR Code -->
    <div class="col-lg-4" data-aos="fade-up">
        <div class="card p-4 border-0 mb-4 shadow-sm text-center">
            <h5 class="fw-bold text-secondary mb-3">Case QR Label</h5>
            
            <div class="bg-light p-3 rounded-4 mb-3 d-inline-block mx-auto border" style="max-width: 220px;">
                <?php if (!empty($case['qr_code']) && file_exists($case['qr_code'])): ?>
                    <img src="<?php echo htmlspecialchars($case['qr_code']); ?>" alt="Case QR Code" class="img-fluid rounded" style="max-height: 180px;">
                <?php else: ?>
                    <div class="d-flex flex-column align-items-center justify-content-center bg-white border border-dashed rounded p-4" style="height: 180px; width: 180px;">
                        <i class="fas fa-qrcode fa-3x text-muted mb-2"></i>
                        <span class="text-muted small">No QR Generated</span>
                    </div>
                <?php endif; ?>
            </div>
            
            <button class="btn btn-outline-dark rounded-pill px-4 btn-sm mb-4" onclick="downloadQRCode(<?php echo $case['id']; ?>, '<?php echo addslashes($case['item_name']); ?>')">
                <i class="fas fa-download me-2"></i> Download QR Label
            </button>

            <hr>

            <div class="text-start mt-3">
                <div class="mb-2">
                    <span class="text-muted small uppercase fw-bold d-block">Serial Number</span>
                    <code class="text-primary font-monospace fs-6"><?php echo htmlspecialchars($case['serial_number']); ?></code>
                </div>
                <div class="mb-2">
                    <span class="text-muted small uppercase fw-bold d-block">Warehouse Location</span>
                    <span class="text-dark"><i class="fas fa-warehouse text-muted me-1 small"></i> <?php echo htmlspecialchars($case['stock_location'] ?: 'Not Set'); ?></span>
                </div>
                <div class="mb-2">
                    <span class="text-muted small uppercase fw-bold d-block">Case Status</span>
                    <?php
                    $status = strtolower($case['status']);
                    $statusClass = 'bg-secondary';
                    if ($status === 'available') $statusClass = 'bg-success';
                    elseif ($status === 'in_use') $statusClass = 'bg-primary';
                    elseif ($status === 'maintenance') $statusClass = 'bg-warning text-dark';
                    ?>
                    <span class="badge badge-status <?php echo $statusClass; ?>"><?php echo ucfirst($case['status']); ?></span>
                </div>
                <div class="mb-2">
                    <span class="text-muted small uppercase fw-bold d-block">Condition</span>
                    <?php
                    $cond = strtolower($case['condition']);
                    $condClass = 'bg-secondary';
                    if ($cond === 'excellent' || $cond === 'new') $condClass = 'bg-success';
                    elseif ($cond === 'good') $condClass = 'bg-primary';
                    elseif ($cond === 'fair') $condClass = 'bg-warning text-dark';
                    ?>
                    <span class="badge badge-status <?php echo $condClass; ?>"><?php echo ucfirst($case['condition']); ?></span>
                </div>
                <?php if (!empty($case['description'])): ?>
                    <div class="mb-2">
                        <span class="text-muted small uppercase fw-bold d-block">Description</span>
                        <span class="text-dark small"><?php echo htmlspecialchars($case['description']); ?></span>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Right Column: Packing List & Actions -->
    <div class="col-lg-8" data-aos="fade-up" data-aos-delay="100">
        <!-- Interactive Packing Box -->
        <div class="card p-4 border-0 mb-4 shadow-sm">
            <h5 class="fw-bold text-dark mb-3"><i class="fas fa-scanner me-2 text-info"></i> Pack Equipment Inside</h5>
            
            <form id="packItemForm" class="row g-2 align-items-center">
                <input type="hidden" name="case_name" value="<?php echo htmlspecialchars($case['item_name']); ?>">
                <input type="hidden" name="case_serial" value="<?php echo htmlspecialchars($case['serial_number']); ?>">
                
                <div class="col-md-9 position-relative">
                    <div class="input-group">
                        <span class="input-group-text bg-light text-secondary rounded-start-pill border-end-0">
                            <i class="fas fa-barcode"></i>
                        </span>
                        <input type="text" class="form-control border-start-0 rounded-end-pill px-3" id="fixtureSerial" name="fixture_serial" placeholder="Scan QR/Barcode or enter Item Serial Number to pack..." required>
                    </div>
                </div>
                
                <div class="col-md-3">
                    <button type="submit" class="btn btn-primary w-100 rounded-pill" id="packBtn">
                        <span class="spinner-border spinner-border-sm me-2 d-none" role="status" aria-hidden="true"></span>
                        <i class="fas fa-plus me-1"></i> Pack Item
                    </button>
                </div>
            </form>
        </div>

        <!-- Packing List Table -->
        <div class="table-container shadow-sm">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="fw-bold text-dark mb-0">Packed Items (<?php echo count($packedItems); ?>)</h5>
                <?php if (count($packedItems) > 0): ?>
                    <button class="btn btn-sm btn-outline-danger rounded-pill" onclick="unpackAll()">
                        <i class="fas fa-box-open me-1"></i> Unpack All Items
                    </button>
                <?php endif; ?>
            </div>
            
            <table id="packedItemsTable" class="table table-hover align-middle w-100">
                <thead class="table-light">
                    <tr>
                        <th>Item Name</th>
                        <th>Serial Number</th>
                        <th>Category</th>
                        <th>Condition</th>
                        <th>Status</th>
                        <th class="text-end">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($packedItems) === 0): ?>
                        <tr id="emptyRow">
                            <td colspan="6" class="text-center py-5 text-muted">
                                <i class="fas fa-box-open fa-3x mb-3 text-opacity-20 text-dark d-block"></i>
                                This Fly Case is currently empty. Scan or enter a serial number above to pack items.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($packedItems as $item): ?>
                            <tr id="itemRow_<?php echo $item['id']; ?>">
                                <td>
                                    <span class="fw-bold text-dark"><?php echo htmlspecialchars($item['item_name']); ?></span>
                                </td>
                                <td><code class="text-primary font-monospace"><?php echo htmlspecialchars($item['serial_number']); ?></code></td>
                                <td><?php echo htmlspecialchars($item['category']); ?></td>
                                <td>
                                    <?php
                                    $icond = strtolower($item['condition']);
                                    $icondClass = 'bg-secondary';
                                    if ($icond === 'excellent' || $icond === 'new') $icondClass = 'bg-success';
                                    elseif ($icond === 'good') $icondClass = 'bg-primary';
                                    elseif ($icond === 'fair') $icondClass = 'bg-warning text-dark';
                                    ?>
                                    <span class="badge badge-status <?php echo $icondClass; ?>"><?php echo ucfirst($item['condition']); ?></span>
                                </td>
                                <td>
                                    <?php
                                    $istatus = strtolower($item['status']);
                                    $istatusClass = 'bg-secondary';
                                    if ($istatus === 'available') $istatusClass = 'bg-success';
                                    elseif ($istatus === 'in_use') $istatusClass = 'bg-primary';
                                    elseif ($istatus === 'maintenance') $istatusClass = 'bg-warning text-dark';
                                    ?>
                                    <span class="badge badge-status <?php echo $istatusClass; ?>"><?php echo ucfirst($item['status']); ?></span>
                                </td>
                                <td class="text-end">
                                    <button class="btn btn-sm btn-outline-danger rounded-pill" onclick="unpackItem(<?php echo $item['id']; ?>, '<?php echo addslashes($item['item_name']); ?>')">
                                        <i class="fas fa-times me-1"></i> Unpack
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
    $(document).ready(function() {
        // Initialize DataTables only if we have packed items
        let table = null;
        if (<?php echo count($packedItems) > 0 ? 'true' : 'false'; ?>) {
            table = $('#packedItemsTable').DataTable({
                responsive: true,
                order: [[0, 'asc']],
                columnDefs: [
                    { orderable: false, targets: [5] }
                ]
            });
        }

        // Focus search input
        $('#fixtureSerial').focus();

        // Pack Item Form Submit
        $('#packItemForm').on('submit', function(e) {
            e.preventDefault();
            const packBtn = $('#packBtn');
            const spinner = packBtn.find('.spinner-border');
            const inputField = $('#fixtureSerial');
            const serial = inputField.val().trim();

            if (!serial) return;

            packBtn.prop('disabled', true);
            spinner.removeClass('d-none');

            $.ajax({
                url: 'api/cases/pack_item.php',
                method: 'POST',
                data: $(this).serialize(),
                dataType: 'json',
                success: function(response) {
                    packBtn.prop('disabled', false);
                    spinner.addClass('d-none');
                    inputField.val(''); // Clear input
                    inputField.focus();

                    if (response.success) {
                        toastr.success(response.message || 'Item packed successfully!');
                        // Reload page to refresh list and stats
                        setTimeout(() => {
                            location.reload();
                        }, 800);
                    } else {
                        toastr.error(response.message || 'Failed to pack item');
                    }
                },
                error: function(xhr, status, error) {
                    packBtn.prop('disabled', false);
                    spinner.addClass('d-none');
                    console.error("AJAX Error:", status, error);
                    console.error("Response:", xhr.responseText);
                    
                    let errMsg = 'Server error occurred during packing.';
                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        errMsg = xhr.responseJSON.message;
                    } else if (xhr.responseText) {
                        try {
                            const parsed = JSON.parse(xhr.responseText);
                            if (parsed.message) errMsg = parsed.message;
                        } catch (e) {
                            errMsg = 'Server Error: ' + xhr.responseText.substring(0, 120);
                        }
                    }
                    toastr.error(errMsg);
                }
            });
        });
    });

    // Unpack Single Item Function
    function unpackItem(itemId, itemName) {
        if (!confirm('Are you sure you want to unpack "' + itemName + '" from this Fly Case?')) {
            return;
        }

        $.ajax({
            url: 'api/cases/unpack_item.php',
            method: 'POST',
            data: { item_id: itemId },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    toastr.success(itemName + ' unpacked successfully.');
                    // Reload page
                    setTimeout(() => {
                        location.reload();
                    }, 800);
                } else {
                    toastr.error(response.message || 'Failed to unpack item');
                }
            },
            error: function() {
                toastr.error('Error contacting server to unpack item.');
            }
        });
    }

    // Unpack All Items Function
    function unpackAll() {
        if (!confirm('Are you sure you want to unpack ALL items from this Fly Case?')) {
            return;
        }

        $.ajax({
            url: 'api/cases/unpack_item.php',
            method: 'POST',
            data: { 
                unpack_all: true,
                case_name: '<?php echo addslashes($case['item_name']); ?>',
                case_serial: '<?php echo addslashes($case['serial_number']); ?>'
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    toastr.success('All items unpacked successfully.');
                    setTimeout(() => {
                        location.reload();
                    }, 800);
                } else {
                    toastr.error(response.message || 'Failed to unpack all items');
                }
            },
            error: function() {
                toastr.error('Error contacting server to unpack all items.');
            }
        });
    }

    // Download QR Code Function
    function downloadQRCode(itemId, itemName) {
        const qrUrl = 'qrcodes/qr_' + itemId + '.png';
        const link = document.createElement('a');
        link.href = qrUrl;
        link.download = itemName.replace(/[^a-z0-9_-]/gi, '_').toLowerCase() + '_qr.png';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        toastr.info('Downloading QR Code label...');
    }
</script>
