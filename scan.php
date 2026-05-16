<?php
// scan.php - Main scanning interface

require_once 'includes/bootstrap.php';
require_once 'includes/database_fix.php';

// Authentication check
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: login.php');
    exit();
}

$pageTitle = "Scan Equipment - aBility";
$current_page = basename(__FILE__);

// Get database connection
$db = new DatabaseFix();
$conn = $db->getConnection();

// Get technicians for dropdown from unified users table
$technicians = [];
$technicianQuery = "SELECT id, full_name, username FROM users WHERE is_active = 1 AND (role = 'technician' OR role = 'tech_lead' OR role = 'senior_tech') ORDER BY full_name";
$techResult = $conn->query($technicianQuery);
if ($techResult) {
    $technicians = $techResult->fetch_all(MYSQLI_ASSOC);
}

// Get current user info
$user_id = $_SESSION['user_id'] ?? null;
$username = $_SESSION['username'] ?? 'Unknown';

require_once 'views/partials/header.php';
?>

<style>
    .scanner-container {
        max-width: 800px;
        margin: 0 auto;
    }

    .scan-area {
        background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
        border-radius: 20px;
        padding: 30px;
        text-align: center;
        cursor: pointer;
        transition: all 0.3s ease;
        border: 3px dashed #234C6A;
    }

    .scan-area:hover {
        transform: scale(1.02);
        background: linear-gradient(135deg, #e8edf5 0%, #b8c4da 100%);
    }

    .scan-result {
        margin-top: 30px;
        padding: 20px;
        border-radius: 15px;
        background: white;
        box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
    }

    .item-card {
        background: white;
        border-radius: 12px;
        padding: 20px;
        margin-bottom: 15px;
        border-left: 5px solid #234C6A;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    }

    .item-card h5 {
        color: #234C6A;
        margin-bottom: 10px;
    }

    .badge-status {
        font-size: 0.9rem;
        padding: 5px 12px;
        border-radius: 20px;
    }

    .scan-mode-buttons .btn {
        margin: 5px;
        padding: 12px 24px;
        font-weight: 500;
    }

    .bulk-items-list {
        max-height: 400px;
        overflow-y: auto;
    }

    .bulk-item {
        background: #f8f9fa;
        border-radius: 8px;
        padding: 12px;
        margin-bottom: 10px;
        border-left: 3px solid #28a745;
    }

    @keyframes scanFlash {
        0% {
            background-color: #fff3cd;
        }

        100% {
            background-color: transparent;
        }
    }

    .flash-highlight {
        animation: scanFlash 0.5s ease;
    }

    #video-preview {
        width: 100%;
        max-width: 640px;
        border-radius: 10px;
        margin: 20px auto;
        display: none;
    }

    .scanner-active {
        display: block;
    }
</style>

<div class="container-fluid scanner-container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1><i class="fas fa-qrcode me-2"></i>Scan Equipment</h1>
        <div class="btn-group">
            <button class="btn btn-outline-primary" onclick="window.location.href='dashboard_full.php'">
                <i class="fas fa-arrow-left me-1"></i> Back to Dashboard
            </button>
            <button class="btn btn-outline-info" onclick="viewScanHistory()">
                <i class="fas fa-history me-1"></i> Scan History
            </button>
        </div>
    </div>

    <!-- Scan Mode Selection -->
    <div class="card shadow mb-4">
        <div class="card-header bg-primary text-white">
            <h6 class="mb-0"><i class="fas fa-cog me-2"></i>Scan Mode</h6>
        </div>
        <div class="card-body">
            <div class="scan-mode-buttons text-center">
                <button class="btn btn-lg btn-success" onclick="setScanMode('checkout')" id="checkoutModeBtn">
                    <i class="fas fa-sign-out-alt me-2"></i> Check Out (Take from Stock)
                </button>
                <button class="btn btn-lg btn-info" onclick="setScanMode('checkin')" id="checkinModeBtn">
                    <i class="fas fa-sign-in-alt me-2"></i> Check In (Return to Stock)
                </button>
                <button class="btn btn-lg btn-warning" onclick="setScanMode('inventory')" id="inventoryModeBtn">
                    <i class="fas fa-clipboard-list me-2"></i> Inventory (Verify Stock)
                </button>
            </div>

            <!-- Mode Info -->
            <div id="modeInfo" class="alert alert-info mt-3" style="display: none;">
                <i class="fas fa-info-circle me-2"></i>
                <span id="modeInfoText"></span>
            </div>
        </div>
    </div>

    <!-- Single Scan Area -->
    <div id="singleScanArea">
        <div class="card shadow mb-4">
            <div class="card-header bg-primary text-white">
                <h6 class="mb-0"><i class="fas fa-camera me-2"></i>Single Scan</h6>
            </div>
            <div class="card-body">
                <div class="scan-area" onclick="startCamera()">
                    <i class="fas fa-camera fa-4x text-primary mb-3"></i>
                    <h5>Click to Scan QR Code</h5>
                    <p class="text-muted">Position the QR code within the camera frame</p>
                </div>

                <video id="video-preview" autoplay playsinline></video>
                <canvas id="canvas" style="display: none;"></canvas>

                <div class="mt-3">
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-barcode"></i></span>
                        <input type="text" class="form-control" id="manualScanInput"
                            placeholder="Or enter serial number / QR code manually">
                        <button class="btn btn-primary" onclick="manualScan()">
                            <i class="fas fa-search me-1"></i> Find
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bulk Scan Area -->
    <div id="bulkScanArea" style="display: none;">
        <div class="card shadow mb-4">
            <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                <h6 class="mb-0"><i class="fas fa-layer-group me-2"></i>Bulk Scan</h6>
                <span class="badge bg-light text-dark" id="bulkCount">0 items scanned</span>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <div class="scan-area" onclick="startBulkCamera()">
                            <i class="fas fa-camera fa-4x text-primary mb-3"></i>
                            <h5>Scan QR Codes</h5>
                            <p class="text-muted">Scan multiple items one after another</p>
                        </div>
                        <video id="bulk-video-preview" autoplay playsinline style="display: none;"></video>
                    </div>
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header bg-light">
                                <strong>Scanned Items</strong>
                                <button class="btn btn-sm btn-danger float-end" onclick="clearBulkList()">
                                    <i class="fas fa-trash me-1"></i> Clear All
                                </button>
                            </div>
                            <div class="card-body bulk-items-list" id="bulkItemsList">
                                <p class="text-muted text-center">No items scanned yet</p>
                            </div>
                            <div class="card-footer">
                                <button class="btn btn-success w-100" onclick="processBulkScan()" id="processBulkBtn" disabled>
                                    <i class="fas fa-check-circle me-1"></i> Process All (<span id="bulkProcessCount">0</span> items)
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Scan Result Display -->
    <div id="scanResult" class="scan-result" style="display: none;">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h4><i class="fas fa-info-circle me-2"></i>Item Information</h4>
            <button class="btn-close" onclick="$('#scanResult').hide(); clearResult();"></button>
        </div>
        <div id="itemDetails"></div>
        <div id="actionButtons" class="mt-3"></div>
    </div>
</div>

<!-- Checkout Form Modal -->
<div class="modal fade" id="checkoutModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title"><i class="fas fa-sign-out-alt me-2"></i>Check Out Equipment</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="checkoutItemInfo" class="alert alert-info"></div>
                <form id="checkoutForm">
                    <input type="hidden" id="checkoutItemId" name="item_id">
                    <input type="hidden" id="checkoutItemName" name="item_name">

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Technician <span class="text-danger">*</span></label>
                                <select class="form-select" id="technicianId" name="technician_id" required>
                                    <option value="">Select Technician</option>
                                    <?php foreach ($technicians as $tech): ?>
                                        <option value="<?php echo $tech['id']; ?>">
                                            <?php echo htmlspecialchars($tech['full_name'] . ' (' . $tech['username'] . ')'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Expected Return Date</label>
                                <input type="datetime-local" class="form-control" id="expectedReturnDate" name="expected_return_date">
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Destination Location</label>
                                <input type="text" class="form-control" id="destinationLocation" name="destination_location"
                                    placeholder="e.g., KCC, BK Arena, etc.">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Purpose</label>
                                <textarea class="form-control" id="purpose" name="purpose" rows="2"
                                    placeholder="Reason for checking out this equipment"></textarea>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Additional Notes</label>
                        <textarea class="form-control" id="checkoutNotes" name="notes" rows="2"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-success" onclick="submitCheckout()">
                    <i class="fas fa-check-circle me-1"></i> Confirm Check Out
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Check In Form Modal -->
<div class="modal fade" id="checkinModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title"><i class="fas fa-sign-in-alt me-2"></i>Check In Equipment</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="checkinItemInfo" class="alert alert-warning"></div>
                <form id="checkinForm">
                    <input type="hidden" id="checkinItemId" name="item_id">

                    <div class="mb-3">
                        <label class="form-label">Condition on Return</label>
                        <select class="form-select" id="returnCondition" name="return_condition">
                            <option value="excellent">Excellent</option>
                            <option value="good" selected>Good</option>
                            <option value="fair">Fair</option>
                            <option value="poor">Poor</option>
                            <option value="damaged">Damaged</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Return Notes</label>
                        <textarea class="form-control" id="returnNotes" name="notes" rows="2"
                            placeholder="Any issues or notes about the return"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-info" onclick="submitCheckin()">
                    <i class="fas fa-check-circle me-1"></i> Confirm Check In
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Scan History Modal -->
<div class="modal fade" id="scanHistoryModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="fas fa-history me-2"></i>Scan History</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <input type="text" class="form-control" id="historySearch" placeholder="Search history...">
                </div>
                <div class="table-responsive">
                    <table class="table table-bordered" id="historyTable">
                        <thead>
                            <tr>
                                <th>Date/Time</th>
                                <th>Item</th>
                                <th>Serial</th>
                                <th>Type</th>
                                <th>Status Change</th>
                                <th>Technician</th>
                                <th>Notes</th>
                            </tr>
                        </thead>
                        <tbody id="historyBody">
                            <tr>
                                <td colspan="7" class="text-center">Loading...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://unpkg.com/@zxing/library@0.18.6/umd/index.min.js"></script>
<script>
    let currentScanMode = 'checkout';
    let codeReader = null;
    let currentVideoElement = null;
    let currentItemData = null;
    let bulkScannedItems = [];
    let isBulkMode = false;
    let isScanning = false;

    // Set scan mode
    function setScanMode(mode) {
        currentScanMode = mode;

        // Update button styles
        $('#checkoutModeBtn').removeClass('btn-success').addClass('btn-outline-success');
        $('#checkinModeBtn').removeClass('btn-info').addClass('btn-outline-info');
        $('#inventoryModeBtn').removeClass('btn-warning').addClass('btn-outline-warning');

        if (mode === 'checkout') {
            $('#checkoutModeBtn').removeClass('btn-outline-success').addClass('btn-success');
            $('#modeInfoText').html('You are in <strong>CHECK OUT</strong> mode. Scanning an item will allow you to check it out to a technician.');
        } else if (mode === 'checkin') {
            $('#checkinModeBtn').removeClass('btn-outline-info').addClass('btn-info');
            $('#modeInfoText').html('You are in <strong>CHECK IN</strong> mode. Scanning an item will return it to stock.');
        } else {
            $('#inventoryModeBtn').removeClass('btn-outline-warning').addClass('btn-warning');
            $('#modeInfoText').html('You are in <strong>INVENTORY</strong> mode. Scanning will verify item presence and update last scanned timestamp.');
        }

        $('#modeInfo').show();

        // Reset scan area
        stopCamera();
        $('#scanResult').hide();
    }

    // Start camera for single scan
    async function startCamera() {
        if (isScanning) {
            stopCamera();
            return;
        }

        const videoElement = document.getElementById('video-preview');
        const scanArea = document.querySelector('.scan-area');

        scanArea.style.display = 'none';
        videoElement.style.display = 'block';

        try {
            codeReader = new ZXing.BrowserMultiFormatReader();
            const devices = await codeReader.listVideoInputDevices();

            if (devices.length === 0) {
                toastr.error('No camera found');
                return;
            }

            // Use back camera if available
            const selectedDeviceId = devices[0].deviceId;

            codeReader.decodeFromVideoDevice(selectedDeviceId, 'video-preview', (result, err) => {
                if (result) {
                    const qrCode = result.getText();
                    stopCamera();
                    processScan(qrCode);
                }
                if (err && !(err instanceof ZXing.NotFoundException)) {
                    console.error(err);
                }
            });

            isScanning = true;

        } catch (err) {
            console.error('Camera error:', err);
            toastr.error('Could not access camera. Please check permissions.');
            stopCamera();
        }
    }

    // Start camera for bulk scan
    async function startBulkCamera() {
        if (isScanning) {
            stopBulkCamera();
            return;
        }

        const videoElement = document.getElementById('bulk-video-preview');
        const scanArea = document.querySelector('#bulkScanArea .scan-area');

        scanArea.style.display = 'none';
        videoElement.style.display = 'block';

        try {
            codeReader = new ZXing.BrowserMultiFormatReader();
            const devices = await codeReader.listVideoInputDevices();

            if (devices.length === 0) {
                toastr.error('No camera found');
                return;
            }

            const selectedDeviceId = devices[0].deviceId;

            codeReader.decodeFromVideoDevice(selectedDeviceId, 'bulk-video-preview', (result, err) => {
                if (result) {
                    const qrCode = result.getText();
                    addToBulkList(qrCode);
                    // Play beep sound (optional)
                    playBeep();
                }
                if (err && !(err instanceof ZXing.NotFoundException)) {
                    console.error(err);
                }
            });

            isScanning = true;

        } catch (err) {
            console.error('Camera error:', err);
            toastr.error('Could not access camera');
            stopBulkCamera();
        }
    }

    function stopCamera() {
        if (codeReader) {
            codeReader.reset();
            codeReader = null;
        }
        const videoElement = document.getElementById('video-preview');
        videoElement.style.display = 'none';
        document.querySelector('.scan-area').style.display = 'block';
        isScanning = false;
    }

    function stopBulkCamera() {
        if (codeReader) {
            codeReader.reset();
            codeReader = null;
        }
        const videoElement = document.getElementById('bulk-video-preview');
        videoElement.style.display = 'none';
        document.querySelector('#bulkScanArea .scan-area').style.display = 'block';
        isScanning = false;
    }

    // Toggle between single and bulk scan
    $('#singleScanToggle').on('click', function() {
        isBulkMode = false;
        $('#singleScanArea').show();
        $('#bulkScanArea').hide();
        stopCamera();
        stopBulkCamera();
    });

    $('#bulkScanToggle').on('click', function() {
        isBulkMode = true;
        $('#singleScanArea').hide();
        $('#bulkScanArea').show();
    });

    // Manual scan input
    function manualScan() {
        const input = $('#manualScanInput').val().trim();
        if (input) {
            processScan(input);
            $('#manualScanInput').val('');
        }
    }

    // Process a single scan
    function processScan(scanData) {
        // Show loading
        $('#scanResult').show();
        $('#itemDetails').html('<div class="text-center"><i class="fas fa-spinner fa-spin fa-2x"></i><p>Loading item...</p></div>');
        $('#actionButtons').empty();

        $.ajax({
            url: 'api/get_item_by_scan.php',
            method: 'POST',
            data: {
                scan_data: scanData
            },
            dataType: 'json',
            success: function(response) {
                if (response.success && response.item) {
                    currentItemData = response.item;
                    displayItemDetails(response.item);

                    if (currentScanMode === 'checkout') {
                        showCheckoutActions(response.item);
                    } else if (currentScanMode === 'checkin') {
                        showCheckinActions(response.item);
                    } else {
                        showInventoryActions(response.item);
                    }
                } else {
                    $('#itemDetails').html(`
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            ${response.message || 'Item not found'}
                        </div>
                    `);
                }
            },
            error: function() {
                $('#itemDetails').html(`
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        Error processing scan. Please try again.
                    </div>
                `);
            }
        });
    }

    // Display item details
    function displayItemDetails(item) {
        const statusClass = getStatusClass(item.status);
        const conditionClass = getConditionClass(item.condition);

        $('#itemDetails').html(`
            <div class="item-card">
                <div class="row">
                    <div class="col-md-8">
                        <h5><i class="fas fa-box me-2"></i>${escapeHtml(item.item_name)}</h5>
                        <div class="row mt-3">
                            <div class="col-sm-6">
                                <small class="text-muted">Serial Number:</small>
                                <div><strong>${escapeHtml(item.serial_number || 'N/A')}</strong></div>
                            </div>
                            <div class="col-sm-6">
                                <small class="text-muted">Brand/Model:</small>
                                <div>${escapeHtml(item.brand || 'N/A')} ${escapeHtml(item.model || '')}</div>
                            </div>
                            <div class="col-sm-6">
                                <small class="text-muted">Location:</small>
                                <div>${escapeHtml(item.stock_location || 'N/A')}</div>
                            </div>
                            <div class="col-sm-6">
                                <small class="text-muted">Department:</small>
                                <div>${escapeHtml(item.department || 'N/A')}</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 text-end">
                        <span class="badge ${statusClass} mb-2">${item.status}</span><br>
                        <span class="badge ${conditionClass}">${item.condition}</span>
                    </div>
                </div>
                ${item.description ? `<hr><small class="text-muted">${escapeHtml(item.description)}</small>` : ''}
            </div>
        `);
    }

    // Show checkout actions
    function showCheckoutActions(item) {
        if (item.status !== 'available') {
            $('#actionButtons').html(`
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    This item is currently ${item.status} and cannot be checked out.
                </div>
                <button class="btn btn-secondary" onclick="$('#scanResult').hide(); clearResult();">
                    <i class="fas fa-times me-1"></i> Close
                </button>
            `);
            return;
        }

        $('#actionButtons').html(`
            <button class="btn btn-success btn-lg w-100" onclick="openCheckoutModal()">
                <i class="fas fa-sign-out-alt me-2"></i> Check Out Equipment
            </button>
        `);
    }

    // Show checkin actions
    function showCheckinActions(item) {
        if (item.status === 'available') {
            $('#actionButtons').html(`
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    This item is already available in stock.
                </div>
                <button class="btn btn-secondary" onclick="$('#scanResult').hide(); clearResult();">
                    <i class="fas fa-times me-1"></i> Close
                </button>
            `);
            return;
        }

        $('#actionButtons').html(`
            <button class="btn btn-info btn-lg w-100" onclick="openCheckinModal()">
                <i class="fas fa-sign-in-alt me-2"></i> Check In Equipment
            </button>
        `);
    }

    // Show inventory actions
    function showInventoryActions(item) {
        $('#actionButtons').html(`
            <button class="btn btn-warning btn-lg w-100" onclick="recordInventoryScan()">
                <i class="fas fa-clipboard-check me-2"></i> Verify Item
            </button>
        `);
    }

    // Open checkout modal
    function openCheckoutModal() {
        if (!currentItemData) return;

        $('#checkoutItemId').val(currentItemData.id);
        $('#checkoutItemName').val(currentItemData.item_name);
        $('#checkoutItemInfo').html(`
            <strong>${escapeHtml(currentItemData.item_name)}</strong><br>
            Serial: ${escapeHtml(currentItemData.serial_number)}
        `);

        // Set default expected return date (7 days from now)
        const defaultReturn = new Date();
        defaultReturn.setDate(defaultReturn.getDate() + 7);
        defaultReturn.setHours(17, 0, 0, 0);
        $('#expectedReturnDate').val(defaultReturn.toISOString().slice(0, 16));

        const modal = new bootstrap.Modal(document.getElementById('checkoutModal'));
        modal.show();
    }

    // Submit checkout
    function submitCheckout() {
        const technicianId = $('#technicianId').val();
        if (!technicianId) {
            toastr.error('Please select a technician');
            return;
        }

        const formData = {
            item_id: $('#checkoutItemId').val(),
            technician_id: technicianId,
            expected_return_date: $('#expectedReturnDate').val(),
            destination_location: $('#destinationLocation').val(),
            purpose: $('#purpose').val(),
            notes: $('#checkoutNotes').val()
        };

        $.ajax({
            url: 'api/checkout_item.php',
            method: 'POST',
            data: JSON.stringify(formData),
            contentType: 'application/json',
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    toastr.success(response.message);
                    bootstrap.Modal.getInstance(document.getElementById('checkoutModal')).hide();
                    $('#scanResult').hide();
                    clearResult();
                    resetCheckoutForm();

                    // Play success sound (optional)
                    playBeep();
                } else {
                    toastr.error(response.message || 'Checkout failed');
                }
            },
            error: function() {
                toastr.error('Error processing checkout');
            }
        });
    }

    // Open checkin modal
    function openCheckinModal() {
        if (!currentItemData) return;

        $('#checkinItemId').val(currentItemData.id);
        $('#checkinItemInfo').html(`
            <strong>${escapeHtml(currentItemData.item_name)}</strong><br>
            Serial: ${escapeHtml(currentItemData.serial_number)}<br>
            Current Status: <strong>${currentItemData.status}</strong>
        `);

        const modal = new bootstrap.Modal(document.getElementById('checkinModal'));
        modal.show();
    }

    // Submit checkin
    function submitCheckin() {
        const formData = {
            item_id: $('#checkinItemId').val(),
            return_condition: $('#returnCondition').val(),
            notes: $('#returnNotes').val()
        };

        $.ajax({
            url: 'api/checkin_item.php',
            method: 'POST',
            data: JSON.stringify(formData),
            contentType: 'application/json',
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    toastr.success(response.message);
                    bootstrap.Modal.getInstance(document.getElementById('checkinModal')).hide();
                    $('#scanResult').hide();
                    clearResult();
                    resetCheckinForm();
                    playBeep();
                } else {
                    toastr.error(response.message || 'Checkin failed');
                }
            },
            error: function() {
                toastr.error('Error processing checkin');
            }
        });
    }

    // Record inventory scan
    function recordInventoryScan() {
        $.ajax({
            url: 'api/inventory_scan.php',
            method: 'POST',
            data: JSON.stringify({
                item_id: currentItemData.id
            }),
            contentType: 'application/json',
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    toastr.success('Item verified in inventory');
                    $('#scanResult').hide();
                    clearResult();
                    playBeep();
                } else {
                    toastr.error(response.message || 'Verification failed');
                }
            },
            error: function() {
                toastr.error('Error recording inventory scan');
            }
        });
    }

    // Bulk scan functions
    function addToBulkList(scanData) {
        // Check if already scanned
        if (bulkScannedItems.find(item => item.scan_data === scanData)) {
            toastr.warning('Item already scanned in this batch');
            return;
        }

        // Fetch item details
        $.ajax({
            url: 'api/get_item_by_scan.php',
            method: 'POST',
            data: {
                scan_data: scanData
            },
            dataType: 'json',
            async: false,
            success: function(response) {
                if (response.success && response.item) {
                    bulkScannedItems.push({
                        scan_data: scanData,
                        item_id: response.item.id,
                        item_name: response.item.item_name,
                        serial_number: response.item.serial_number,
                        status: response.item.status
                    });
                    updateBulkListDisplay();
                } else {
                    toastr.error(`Item not found: ${scanData}`);
                }
            }
        });
    }

    function updateBulkListDisplay() {
        const container = $('#bulkItemsList');
        const count = bulkScannedItems.length;

        $('#bulkCount').text(`${count} item${count !== 1 ? 's' : ''} scanned`);
        $('#bulkProcessCount').text(count);
        $('#processBulkBtn').prop('disabled', count === 0);

        if (count === 0) {
            container.html('<p class="text-muted text-center">No items scanned yet</p>');
            return;
        }

        let html = '';
        bulkScannedItems.forEach((item, index) => {
            const canCheckout = item.status === 'available';
            html += `
                <div class="bulk-item">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <strong>${escapeHtml(item.item_name)}</strong><br>
                            <small class="text-muted">${escapeHtml(item.serial_number)}</small>
                            ${!canCheckout ? '<br><small class="text-warning">⚠️ Already checked out</small>' : ''}
                        </div>
                        <button class="btn btn-sm btn-danger" onclick="removeFromBulkList(${index})">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>
            `;
        });

        container.html(html);
    }

    function removeFromBulkList(index) {
        bulkScannedItems.splice(index, 1);
        updateBulkListDisplay();
    }

    function clearBulkList() {
        if (confirm('Clear all scanned items?')) {
            bulkScannedItems = [];
            updateBulkListDisplay();
        }
    }

    function processBulkScan() {
        if (bulkScannedItems.length === 0) {
            toastr.warning('No items to process');
            return;
        }

        if (currentScanMode === 'checkout') {
            processBulkCheckout();
        } else if (currentScanMode === 'checkin') {
            processBulkCheckin();
        } else {
            processBulkInventory();
        }
    }

    function processBulkCheckout() {
        // Filter only available items
        const availableItems = bulkScannedItems.filter(item => item.status === 'available');

        if (availableItems.length === 0) {
            toastr.warning('No available items to check out');
            return;
        }

        // Show technician selection modal for bulk checkout
        $('#bulkCheckoutItemCount').text(availableItems.length);
        const modal = new bootstrap.Modal(document.getElementById('bulkCheckoutModal'));
        modal.show();
    }

    function processBulkCheckin() {
        // Filter only checked out items
        const checkedOutItems = bulkScannedItems.filter(item => item.status !== 'available');

        if (checkedOutItems.length === 0) {
            toastr.warning('No checked out items to return');
            return;
        }

        if (confirm(`Return ${checkedOutItems.length} item(s) to stock?`)) {
            processBulkAction('checkin', checkedOutItems);
        }
    }

    function processBulkInventory() {
        if (confirm(`Record inventory scan for ${bulkScannedItems.length} item(s)?`)) {
            processBulkAction('inventory', bulkScannedItems);
        }
    }

    function processBulkAction(action, items) {
        const formData = {
            action: action,
            items: items.map(item => ({
                item_id: item.item_id,
                scan_data: item.scan_data
            }))
        };

        if (action === 'checkout') {
            formData.technician_id = $('#bulkTechnicianId').val();
            formData.destination_location = $('#bulkDestinationLocation').val();
            formData.expected_return_date = $('#bulkExpectedReturnDate').val();
        }

        $.ajax({
            url: 'api/bulk_scan_action.php',
            method: 'POST',
            data: JSON.stringify(formData),
            contentType: 'application/json',
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    toastr.success(response.message);
                    if (action === 'checkout') {
                        bootstrap.Modal.getInstance(document.getElementById('bulkCheckoutModal')).hide();
                    }
                    bulkScannedItems = [];
                    updateBulkListDisplay();
                    playBeep();
                } else {
                    toastr.error(response.message || 'Bulk action failed');
                }
            },
            error: function() {
                toastr.error('Error processing bulk action');
            }
        });
    }

    // View scan history
    function viewScanHistory() {
        const modal = new bootstrap.Modal(document.getElementById('scanHistoryModal'));
        modal.show();

        loadScanHistory();
    }

    function loadScanHistory(searchTerm = '') {
        $.ajax({
            url: 'api/scan_history.php',
            method: 'GET',
            data: {
                search: searchTerm
            },
            dataType: 'json',
            success: function(response) {
                if (response.success && response.history) {
                    displayHistory(response.history);
                } else {
                    $('#historyBody').html('<tr><td colspan="7" class="text-center">No history found</td></tr>');
                }
            },
            error: function() {
                $('#historyBody').html('<tr><td colspan="7" class="text-center text-danger">Error loading history</td></tr>');
            }
        });
    }

    function displayHistory(history) {
        if (history.length === 0) {
            $('#historyBody').html('<tr><td colspan="7" class="text-center">No history found</td></tr>');
            return;
        }

        let html = '';
        history.forEach(record => {
            const typeClass = record.scan_type === 'check_out' ? 'text-success' :
                (record.scan_type === 'check_in' ? 'text-info' : 'text-warning');
            html += `
                <tr>
                    <td>${formatDateTime(record.scanned_at)}</td>
                    <td>${escapeHtml(record.item_name)}</td>
                    <td><code>${escapeHtml(record.serial_number)}</code></td>
                    <td class="${typeClass}"><strong>${record.scan_type}</strong></td>
                    <td>${record.previous_status || '-'} → ${record.new_status || '-'}</td>
                    <td>${escapeHtml(record.technician_name || record.username || '-')}</td>
                    <td>${escapeHtml(record.notes || '-')}</td>
                </tr>
            `;
        });

        $('#historyBody').html(html);
    }

    // Helper functions
    function getStatusClass(status) {
        const classes = {
            'available': 'bg-success',
            'in_use': 'bg-primary',
            'maintenance': 'bg-warning',
            'reserved': 'bg-info',
            'disposed': 'bg-danger',
            'lost': 'bg-dark'
        };
        return classes[status] || 'bg-secondary';
    }

    function getConditionClass(condition) {
        const classes = {
            'new': 'bg-success',
            'excellent': 'bg-success',
            'good': 'bg-primary',
            'fair': 'bg-info',
            'poor': 'bg-warning',
            'damaged': 'bg-danger'
        };
        return classes[condition] || 'bg-secondary';
    }

    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function formatDateTime(dt) {
        if (!dt) return 'N/A';
        const date = new Date(dt);
        return date.toLocaleString();
    }

    function playBeep() {
        try {
            const audio = new Audio('https://www.soundjay.com/misc/sounds/beep-07.mp3');
            audio.play().catch(e => console.log('Audio play failed:', e));
        } catch (e) {
            console.log('Beep failed:', e);
        }
    }

    function clearResult() {
        currentItemData = null;
        $('#itemDetails').empty();
        $('#actionButtons').empty();
    }

    function resetCheckoutForm() {
        $('#technicianId').val('');
        $('#expectedReturnDate').val('');
        $('#destinationLocation').val('');
        $('#purpose').val('');
        $('#checkoutNotes').val('');
    }

    function resetCheckinForm() {
        $('#returnCondition').val('good');
        $('#returnNotes').val('');
    }

    // Search history on input
    $('#historySearch').on('input', function() {
        loadScanHistory($(this).val());
    });

    // Initialize
    $(document).ready(function() {
        setScanMode('checkout');

        // Enter key for manual scan
        $('#manualScanInput').on('keypress', function(e) {
            if (e.which === 13) {
                manualScan();
            }
        });
    });
</script>

<!-- Bulk Checkout Modal -->
<div class="modal fade" id="bulkCheckoutModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title"><i class="fas fa-layer-group me-2"></i>Bulk Check Out</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Checking out <strong id="bulkCheckoutItemCount">0</strong> item(s)</p>

                <div class="mb-3">
                    <label class="form-label">Technician <span class="text-danger">*</span></label>
                    <select class="form-select" id="bulkTechnicianId">
                        <option value="">Select Technician</option>
                        <?php foreach ($technicians as $tech): ?>
                            <option value="<?php echo $tech['id']; ?>">
                                <?php echo htmlspecialchars($tech['full_name'] . ' (' . $tech['username'] . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label">Destination Location</label>
                    <input type="text" class="form-control" id="bulkDestinationLocation" placeholder="e.g., KCC, BK Arena">
                </div>

                <div class="mb-3">
                    <label class="form-label">Expected Return Date</label>
                    <input type="datetime-local" class="form-control" id="bulkExpectedReturnDate">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-success" onclick="processBulkAction('checkout', bulkScannedItems.filter(i => i.status === 'available'))">
                    <i class="fas fa-check-circle me-1"></i> Check Out All
                </button>
            </div>
        </div>
    </div>
</div>

<?php require_once 'views/partials/footer.php'; ?>