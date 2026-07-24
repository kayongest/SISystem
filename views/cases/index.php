<?php
// views/cases/index.php - Fly Cases List View
$current_page = 'cases.php';

// Fetch all active stock locations for dropdown
$locations = [];
try {
    $locResult = $conn->query("SELECT name FROM stock_locations WHERE is_active = 1 ORDER BY name ASC");
    if ($locResult) {
        while ($row = $locResult->fetch_assoc()) {
            $locations[] = $row['name'];
        }
    }
} catch (Exception $e) {
    error_log("Error fetching locations: " . $e->getMessage());
}

// Fetch stats for cards
$totalCases = 0;
$emptyCases = 0;
$totalPackedItems = 0;
$inUseCases = 0;

try {
    // Total cases
    $tcResult = $conn->query("SELECT COUNT(*) as count FROM items WHERE category = 'Cases' OR item_name LIKE '%Case%'");
    if ($tcResult) {
        $row = $tcResult->fetch_assoc();
        $totalCases = $row['count'] ?? 0;
    }

    // In-use cases
    $iucResult = $conn->query("SELECT COUNT(*) as count FROM items WHERE (category = 'Cases' OR item_name LIKE '%Case%') AND status = 'in_use'");
    if ($iucResult) {
        $row = $iucResult->fetch_assoc();
        $inUseCases = $row['count'] ?? 0;
    }

    // Detailed cases query to calculate empty cases and packed items count
    $casesQuery = "
        SELECT c.item_name, c.serial_number,
               (SELECT COUNT(*) FROM items i WHERE i.storage_location = c.item_name OR i.storage_location = c.serial_number) as packed_count
        FROM items c
        WHERE c.category = 'Cases' OR c.item_name LIKE '%Case%'
    ";
    $cResult = $conn->query($casesQuery);
    if ($cResult) {
        while ($row = $cResult->fetch_assoc()) {
            $packed = intval($row['packed_count'] ?? 0);
            $totalPackedItems += $packed;
            if ($packed === 0) {
                $emptyCases++;
            }
        }
    }
} catch (Exception $e) {
    error_log("Error calculating case stats: " . $e->getMessage());
}

// Fetch all cases for the table
$casesList = [];
try {
    $mainQuery = "
        SELECT c.id, c.item_name, c.serial_number, c.stock_location, c.condition, c.status, c.qr_code, c.description,
               (SELECT COUNT(*) FROM items i WHERE i.storage_location = c.item_name OR i.storage_location = c.serial_number) as packed_count
        FROM items c
        WHERE c.category = 'Cases' OR c.item_name LIKE '%Case%'
        ORDER BY c.item_name ASC
    ";
    $mResult = $conn->query($mainQuery);
    if ($mResult) {
        $casesList = $mResult->fetch_all(MYSQLI_ASSOC);
    }
} catch (Exception $e) {
    error_log("Error fetching cases list: " . $e->getMessage());
}
?>

<div class="row mb-4" data-aos="fade-down">
    <div class="col-12 d-flex justify-content-between align-items-center">
        <div>
            <h2 class="fw-bold text-dark mb-1">Fly Cases & Road Cases</h2>
            <p class="text-muted mb-0">Manage case inventory, packing lists, and auto-scanning groupings.</p>
        </div>
        <button class="btn btn-primary rounded-pill px-4" data-bs-toggle="modal" data-bs-target="#addCaseModal">
            <i class="fas fa-plus me-2"></i> Add Fly Case
        </button>
    </div>
</div>

<!-- Stats Cards -->
<div class="row g-4 mb-4" data-aos="fade-up">
    <!-- Card 1: Total Cases -->
    <div class="col-md-3">
        <div class="card p-3 border-start border-4 border-primary">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <h6 class="text-muted text-uppercase small fw-bold mb-1">Total Fly Cases</h6>
                    <h3 class="fw-bold text-dark mb-0"><?php echo $totalCases; ?></h3>
                </div>
                <div class="bg-primary bg-opacity-10 text-primary p-3 rounded-3">
                    <i class="fas fa-box fa-2x"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Card 2: Empty Cases -->
    <div class="col-md-3">
        <div class="card p-3 border-start border-4 border-warning">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <h6 class="text-muted text-uppercase small fw-bold mb-1">Empty Cases</h6>
                    <h3 class="fw-bold text-dark mb-0"><?php echo $emptyCases; ?></h3>
                </div>
                <div class="bg-warning bg-opacity-10 text-warning p-3 rounded-3">
                    <i class="fas fa-box-open fa-2x"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Card 3: Packed Fixtures -->
    <div class="col-md-3">
        <div class="card p-3 border-start border-4 border-success">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <h6 class="text-muted text-uppercase small fw-bold mb-1">Packed Fixtures</h6>
                    <h3 class="fw-bold text-dark mb-0"><?php echo $totalPackedItems; ?></h3>
                </div>
                <div class="bg-success bg-opacity-10 text-success p-3 rounded-3">
                    <i class="fas fa-plug fa-2x"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Card 4: In Use Cases -->
    <div class="col-md-3">
        <div class="card p-3 border-start border-4 border-info">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <h6 class="text-muted text-uppercase small fw-bold mb-1">Cases In Use/Transit</h6>
                    <h3 class="fw-bold text-dark mb-0"><?php echo $inUseCases; ?></h3>
                </div>
                <div class="bg-info bg-opacity-10 text-info p-3 rounded-3">
                    <i class="fas fa-truck-loading fa-2x"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Cases Table Container -->
<div class="table-container shadow-sm" data-aos="fade-up" data-aos-delay="100">
    <table id="casesTable" class="table table-hover align-middle w-100" style="border-collapse: collapse;">
        <thead class="table-light">
            <tr>
                <th>Case Name</th>
                <th>Serial Number</th>
                <th>Stock Location</th>
                <th>Condition</th>
                <th>Status</th>
                <th class="text-center">Packed Items</th>
                <th class="text-end">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($casesList as $case): ?>
                <tr>
                    <td>
                        <div class="d-flex align-items-center">
                            <div class="bg-light p-2 rounded me-3 text-secondary">
                                <i class="fas fa-cube fa-lg"></i>
                            </div>
                            <div>
                                <span class="fw-bold text-dark"><?php echo htmlspecialchars($case['item_name']); ?></span>
                                <?php if (!empty($case['description'])): ?>
                                    <small class="d-block text-muted"><?php echo htmlspecialchars($case['description']); ?></small>
                                <?php endif; ?>
                            </div>
                        </div>
                    </td>
                    <td><code class="text-primary font-monospace"><?php echo htmlspecialchars($case['serial_number']); ?></code></td>
                    <td>
                        <i class="fas fa-warehouse text-muted me-1 small"></i>
                        <?php echo htmlspecialchars($case['stock_location'] ?: 'Not Set'); ?>
                    </td>
                    <td>
                        <?php
                        $cond = strtolower($case['condition']);
                        $condClass = 'bg-secondary';
                        if ($cond === 'excellent' || $cond === 'new') $condClass = 'bg-success';
                        elseif ($cond === 'good') $condClass = 'bg-primary';
                        elseif ($cond === 'fair') $condClass = 'bg-warning text-dark';
                        elseif ($cond === 'poor' || $cond === 'damaged') $condClass = 'bg-danger';
                        ?>
                        <span class="badge badge-status <?php echo $condClass; ?>"><?php echo ucfirst($case['condition']); ?></span>
                    </td>
                    <td>
                        <?php
                        $status = strtolower($case['status']);
                        $statusClass = 'bg-secondary';
                        if ($status === 'available') $statusClass = 'bg-success';
                        elseif ($status === 'in_use') $statusClass = 'bg-primary';
                        elseif ($status === 'maintenance') $statusClass = 'bg-warning text-dark';
                        elseif ($status === 'reserved') $statusClass = 'bg-info text-dark';
                        ?>
                        <span class="badge badge-status <?php echo $statusClass; ?>"><?php echo ucfirst($case['status']); ?></span>
                    </td>
                    <td class="text-center">
                        <?php if ($case['packed_count'] > 0): ?>
                            <span class="badge bg-dark rounded-pill px-3 py-2">
                                <i class="fas fa-link me-1 text-info"></i> <?php echo $case['packed_count']; ?> items
                            </span>
                        <?php else: ?>
                            <span class="badge bg-light text-muted border rounded-pill px-3 py-2">Empty</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-end">
                        <div class="btn-group btn-group-sm">
                            <a href="cases.php?action=view&id=<?php echo $case['id']; ?>" class="btn btn-outline-primary border rounded-pill px-3 me-2" title="Pack / View Contents">
                                <i class="fas fa-tasks me-1"></i> Pack List
                            </a>
                            <button class="btn btn-outline-secondary border rounded-circle me-1" onclick="downloadQRCode(<?php echo $case['id']; ?>, '<?php echo addslashes($case['item_name']); ?>')" title="Download QR Code">
                                <i class="fas fa-qrcode"></i>
                            </button>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- Modal: Add Fly Case -->
<div class="modal fade" id="addCaseModal" tabindex="-1" aria-labelledby="addCaseModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content rounded-4 border-0 shadow-lg">
            <div class="modal-header bg-dark text-white border-0 py-3">
                <h5 class="modal-title fw-bold" id="addCaseModalLabel">
                    <i class="fas fa-box me-2 text-info"></i> Create New Fly Case
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <form id="addCaseForm">
                    <input type="hidden" name="category" value="Cases">
                    <input type="hidden" name="brand" value="Custom">
                    <input type="hidden" name="model" value="Fly Case">
                    <input type="hidden" name="quantity" value="1">
                    <input type="hidden" name="status" value="available">

                    <div class="mb-3">
                        <label for="caseName" class="form-label fw-bold small text-secondary">Case Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control rounded-3" id="caseName" name="item_name" placeholder="e.g. Fly Case - C" required>
                    </div>

                    <div class="mb-3">
                        <label for="caseSerial" class="form-label fw-bold small text-secondary">Unique Serial Number <span class="text-danger">*</span></label>
                        <input type="text" class="form-control rounded-3 font-monospace" id="caseSerial" name="serial_number" placeholder="e.g. FC-SN-003" required>
                        <small class="text-muted">Must be completely unique in the system.</small>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="caseLocation" class="form-label fw-bold small text-secondary">Stock Location</label>
                            <select class="form-select rounded-3" id="caseLocation" name="stock_location">
                                <option value="">-- Select Warehouse --</option>
                                <?php foreach ($locations as $loc): ?>
                                    <option value="<?php echo htmlspecialchars($loc); ?>"><?php echo htmlspecialchars($loc); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="caseCondition" class="form-label fw-bold small text-secondary">Condition</label>
                            <select class="form-select rounded-3" id="caseCondition" name="condition">
                                <option value="excellent">Excellent</option>
                                <option value="good" selected>Good</option>
                                <option value="fair">Fair</option>
                                <option value="poor">Poor</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="caseDesc" class="form-label fw-bold small text-secondary">Description / Notes</label>
                        <textarea class="form-control rounded-3" id="caseDesc" name="description" rows="3" placeholder="e.g. 6-Way lighting road case with blue foam lining"></textarea>
                    </div>

                    <div class="d-flex justify-content-end gap-2 mt-4">
                        <button type="button" class="btn btn-light rounded-pill px-3" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary rounded-pill px-4" id="submitBtn">
                            <span class="spinner-border spinner-border-sm me-2 d-none" role="status" aria-hidden="true"></span>
                            Create Case
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    $(document).ready(function() {
        // Initialize DataTable
        $('#casesTable').DataTable({
            responsive: true,
            order: [[0, 'asc']],
            columnDefs: [
                { orderable: false, targets: [6] }
            ]
        });

        // Form Submit Handler
        $('#addCaseForm').on('submit', function(e) {
            e.preventDefault();
            const submitBtn = $('#submitBtn');
            const spinner = submitBtn.find('.spinner-border');

            // Disable buttons and show loading state
            submitBtn.prop('disabled', true);
            spinner.removeClass('d-none');

            // Send ajax request
            $.ajax({
                url: 'api/items/create.php',
                method: 'POST',
                data: $(this).serialize(),
                dataType: 'json',
                success: function(response) {
                    submitBtn.prop('disabled', false);
                    spinner.addClass('d-none');

                    if (response.success) {
                        $('#addCaseModal').modal('hide');
                        toastr.success('Fly Case created successfully!');
                        // Reload page after short delay
                        setTimeout(() => {
                            location.reload();
                        }, 1000);
                    } else {
                        toastr.error(response.message || 'Failed to create Fly Case');
                    }
                },
                error: function(xhr, status, error) {
                    submitBtn.prop('disabled', false);
                    spinner.addClass('d-none');
                    console.error("AJAX Error:", status, error);
                    console.error("Response:", xhr.responseText);
                    
                    let errMsg = 'Error connecting to the server. Please try again.';
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

    // Download QR Code Function
    function downloadQRCode(itemId, itemName) {
        // Build QR URL
        const qrUrl = 'qrcodes/qr_' + itemId + '.png';
        
        // Create virtual anchor
        const link = document.createElement('a');
        link.href = qrUrl;
        link.download = itemName.replace(/[^a-z0-9_-]/gi, '_').toLowerCase() + '_qr.png';
        document.body.appendChild(link);
        
        // Trigger download
        link.click();
        
        // Clean up
        document.body.removeChild(link);
        toastr.info('Downloading QR Code label...');
    }
</script>
