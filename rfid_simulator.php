<?php
// rfid_simulator.php - Interactive RFID Hardware & Logistics Simulator
$current_page = 'rfid_simulator.php';
require_once 'bootstrap.php';

// Check authentication
if (!isLoggedIn()) {
    header('Location: login.php');
    exit();
}

require_once 'includes/functions.php';
require_once 'includes/db_connect.php';

$conn = getConnection();
$user_role = getUserRole();

if ($user_role === 'driver') {
    $_SESSION['toast_message'] = 'Drivers do not have permission to access the RFID Simulator.';
    $_SESSION['toast_type'] = 'error';
    header('Location: driver_batches.php');
    exit();
}

$pageTitle = "RFID Logistics Simulator - aBility";

// 1. Fetch cases from the database
$cases = [];
try {
    $result = $conn->query("SELECT id, item_name, serial_number, stock_location, status FROM items WHERE category = 'Cases' OR item_name LIKE '%Case%' ORDER BY item_name ASC");
    if ($result) {
        $cases = $result->fetch_all(MYSQLI_ASSOC);
    }
} catch (Exception $e) {
    error_log("Error fetching cases: " . $e->getMessage());
}

// 2. Fetch non-case items
$items = [];
try {
    $result = $conn->query("SELECT id, item_name, serial_number, category, stock_location, storage_location, `condition` FROM items WHERE category != 'Cases' AND item_name NOT LIKE '%Case%' ORDER BY id ASC");
    if ($result) {
        $items = $result->fetch_all(MYSQLI_ASSOC);
    }
} catch (Exception $e) {
    error_log("Error fetching items: " . $e->getMessage());
}

// If database has no cases, insert a dummy case so the user can test the simulator
if (empty($cases)) {
    try {
        $dummy_qr = 'qrcodes/qr_dummy_case.png';
        $conn->query("INSERT INTO items (item_name, serial_number, category, brand, model, department, stock_location, storage_location, quantity, status, qr_code) 
                      VALUES ('Fly Case A', 'FC-A-2026-001', 'Cases', 'RoadReady', 'Medium Flight Case', 'LOG', 'Ndera', NULL, 1, 'available', '$dummy_qr')");
        // Re-fetch
        $result = $conn->query("SELECT id, item_name, serial_number, stock_location, status FROM items WHERE category = 'Cases' OR item_name LIKE '%Case%' ORDER BY item_name ASC");
        if ($result) {
            $cases = $result->fetch_all(MYSQLI_ASSOC);
        }
    } catch (Exception $e) {
        error_log("Error seeding dummy case: " . $e->getMessage());
    }
}

// Auto-select first case
$selected_case_id = isset($_GET['case_id']) ? intval($_GET['case_id']) : (count($cases) > 0 ? $cases[0]['id'] : 0);

// Fetch items currently registered to the selected case
$expected_items = [];
$selected_case = null;
if ($selected_case_id > 0) {
    foreach ($cases as $c) {
        if ($c['id'] == $selected_case_id) {
            $selected_case = $c;
            break;
        }
    }
    
    if ($selected_case) {
        try {
            $caseName = $selected_case['item_name'];
            $caseSerial = $selected_case['serial_number'];
            $stmt = $conn->prepare("SELECT id, item_name, serial_number, category, `condition` FROM items WHERE storage_location = ? OR storage_location = ? ORDER BY id ASC");
            $stmt->bind_param("ss", $caseName, $caseSerial);
            $stmt->execute();
            $expected_items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
        } catch (Exception $e) {
            error_log("Error fetching expected items: " . $e->getMessage());
        }
    }
}

// Load the layout header (which includes <!DOCTYPE html>, <html>, <head> and <body>)
require_once 'views/partials/header.php';
?>

<style>
    @import url('https://fonts.googleapis.com/css2?family=Titillium+Web:ital,wght@0,200;0,300;0,400;0,600;0,700;0,900;1,200;1,300;1,400;1,600;1,700&display=swap');

    .glass-card {
        background: rgba(255, 255, 255, 0.85);
        backdrop-filter: blur(10px);
        -webkit-backdrop-filter: blur(10px);
        border: 1px solid rgba(255, 255, 255, 0.3);
        border-radius: 16px;
        box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.05);
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }

    .glass-card:hover {
        box-shadow: 0 12px 40px 0 rgba(31, 38, 135, 0.08);
    }

    .hardware-badge {
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 1px;
        font-weight: 700;
        padding: 0.4em 0.8em;
        border-radius: 20px;
        display: inline-flex;
        align-items: center;
    }

    .badge-live-on {
        background-color: rgba(25, 135, 84, 0.15);
        color: #198754;
        border: 1px solid rgba(25, 135, 84, 0.3);
    }

    .status-dot {
        width: 8px;
        height: 8px;
        border-radius: 50%;
        margin-right: 6px;
        display: inline-block;
    }

    .dot-green {
        background-color: #198754;
        box-shadow: 0 0 8px #198754;
    }

    /* Radar Waves Sweep Animation */
    @keyframes radar-pulse {
        0% {
            transform: scale(0.95);
            box-shadow: 0 0 0 0 rgba(32, 178, 170, 0.6);
        }
        70% {
            transform: scale(1);
            box-shadow: 0 0 0 25px rgba(32, 178, 170, 0);
        }
        100% {
            transform: scale(0.95);
            box-shadow: 0 0 0 0 rgba(32, 178, 170, 0);
        }
    }

    .radar-box {
        position: relative;
        width: 130px;
        height: 130px;
        border-radius: 50%;
        background: radial-gradient(circle, #20B2AA 0%, #10314b 100%);
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 2rem auto;
        color: white;
        cursor: pointer;
        z-index: 10;
    }

    .radar-active {
        animation: radar-pulse 1.6s infinite ease-in-out;
    }

    /* Monospace terminal logs */
    .terminal-console {
        background-color: #0d1117;
        color: #39ff14;
        font-family: 'Courier New', Courier, monospace;
        padding: 1.25rem;
        border-radius: 12px;
        height: 280px;
        overflow-y: auto;
        border: 1px solid #30363d;
        box-shadow: inset 0 0 10px rgba(0,0,0,0.8);
        font-size: 0.85rem;
        line-height: 1.4;
    }

    .terminal-line {
        margin-bottom: 0.25rem;
        word-break: break-all;
    }

    .terminal-line .timestamp {
        color: #8b949e;
        margin-right: 6px;
    }

    .terminal-line .accent {
        color: #58a6ff;
    }

    .terminal-line .danger {
        color: #ff7b72;
    }

    /* Checkbox slider styling */
    .switch {
        position: relative;
        display: inline-block;
        width: 46px;
        height: 22px;
    }

    .switch input { 
        opacity: 0;
        width: 0;
        height: 0;
    }

    .slider {
        position: absolute;
        cursor: pointer;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background-color: #ccc;
        transition: .3s;
    }

    .slider:before {
        position: absolute;
        content: "";
        height: 16px;
        width: 16px;
        left: 3px;
        bottom: 3px;
        background-color: white;
        transition: .3s;
    }

    input:checked + .slider {
        background-color: #20B2AA;
    }

    input:focus + .slider {
        box-shadow: 0 0 1px #20B2AA;
    }

    input:checked + .slider:before {
        transform: translateX(24px);
    }

    .slider.round {
        border-radius: 34px;
    }

    .slider.round:before {
        border-radius: 50%;
    }

    .item-row-discrepancy {
        background-color: rgba(220, 53, 69, 0.05) !important;
        border-left: 4px solid #dc3545 !important;
    }
</style>

<div class="container-fluid mt-2">
    <!-- Dashboard Header -->
    <div class="row mb-4">
        <div class="col-12 d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <h2 class="fw-bold text-dark mb-1">
                    <i class="fas fa-broadcast-tower text-primary me-2"></i> UHF RFID Logistics Simulator
                </h2>
                <p class="text-muted mb-0">Understand and test UHF RFID scanning logic on flight cases without physical hardware.</p>
            </div>
            <div class="d-flex align-items-center gap-2">
                <span class="hardware-badge badge-live-on"><span class="status-dot dot-green"></span>Middleware: Online</span>
                <span class="hardware-badge badge-live-on"><span class="status-dot dot-green"></span>Reader: Ready</span>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <!-- LEFT PANEL: Simulator Settings & Hardware Configuration -->
        <div class="col-lg-4 d-flex flex-column gap-4">
            <!-- Card 1: Setup & Scenarios -->
            <div class="card glass-card p-4 border-0">
                <h5 class="fw-bold text-dark mb-3"><i class="fas fa-cog me-2 text-secondary"></i> 1. Setup & Scenarios</h5>
                
                <!-- Form inputs -->
                <div class="mb-3">
                    <label for="caseSelect" class="form-label small fw-bold text-uppercase text-muted mb-1">Select Flight Case</label>
                    <select class="form-select rounded-3" id="caseSelect" onchange="changeCase(this.value)">
                        <?php foreach ($cases as $c): ?>
                            <option value="<?php echo $c['id']; ?>" <?php echo $c['id'] == $selected_case_id ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($c['item_name']); ?> (<?php echo htmlspecialchars($c['serial_number']); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label small fw-bold text-uppercase text-muted mb-1 d-block">RFID Reader Type</label>
                    <div class="btn-group w-100" role="group">
                        <input type="radio" class="btn-check" name="readerType" id="readerHandheld" value="handheld" checked autocomplete="off">
                        <label class="btn btn-outline-dark btn-sm rounded-start-pill py-2" for="readerHandheld">
                            <i class="fas fa-mobile-alt me-1"></i> Handheld Sled
                        </label>

                        <input type="radio" class="btn-check" name="readerType" id="readerPortal" value="portal" autocomplete="off">
                        <label class="btn btn-outline-dark btn-sm rounded-end-pill py-2" for="readerPortal">
                            <i class="fas fa-door-open me-1"></i> Portal Gate
                        </label>
                    </div>
                </div>

                <hr class="my-4">

                <h6 class="fw-bold text-secondary mb-3"><i class="fas fa-magic me-2"></i> Quick Preset Scenarios</h6>
                <div class="d-flex flex-column gap-2 mb-3">
                    <button class="btn btn-sm btn-light border text-start rounded-pill py-2 px-3" onclick="loadPreset('empty')">
                        <i class="fas fa-box-open me-2 text-warning"></i> Preset A: Empty Case
                        <span class="d-block small text-muted text-truncate" style="font-size: 0.75rem;">Clear all items physically inside</span>
                    </button>
                    <button class="btn btn-sm btn-light border text-start rounded-pill py-2 px-3" onclick="loadPreset('correct')">
                        <i class="fas fa-check-circle me-2 text-success"></i> Preset B: Perfect Match
                        <span class="d-block small text-muted text-truncate" style="font-size: 0.75rem;">Put only registered items inside</span>
                    </button>
                    <button class="btn btn-sm btn-light border text-start rounded-pill py-2 px-3" onclick="loadPreset('discrepancy')">
                        <i class="fas fa-exclamation-triangle me-2 text-danger"></i> Preset C: Discrepancy Test
                        <span class="d-block small text-muted text-truncate" style="font-size: 0.75rem;">Add items 4, 9, 10 & 12 (mismatched)</span>
                    </button>
                </div>

                <div class="alert alert-info border-0 rounded-4 p-3 mb-0" style="font-size: 0.85rem;">
                    <i class="fas fa-info-circle me-2"></i>
                    <strong>Real-World Info:</strong> Flight cases and TV screens require specialized <strong>On-Metal UHF Tags</strong>. Standard stickers will detune and fail to read.
                </div>
            </div>

            <!-- Card 2: Camera QR Scanner -->
            <div class="card glass-card p-4 border-0">
                <h5 class="fw-bold text-dark mb-3"><i class="fas fa-camera me-2 text-primary"></i> Camera QR Scanner</h5>
                <p class="text-muted small">Scan the printed QR Code label of a flight case to automatically check-in, load its database records, and view its assigned cabinets.</p>
                
                <div class="text-center my-3">
                    <button class="btn btn-outline-primary rounded-pill px-4 btn-sm w-100 py-2" id="startCamBtn">
                        <i class="fas fa-qrcode me-2"></i> Start Camera Scanner
                    </button>
                    <button class="btn btn-outline-danger rounded-pill px-4 btn-sm w-100 py-2 mt-2" id="stopCamBtn" style="display:none;">
                        <i class="fas fa-stop me-2"></i> Stop Camera Scanner
                    </button>
                </div>
                
                <!-- Video preview frame -->
                <div class="position-relative overflow-hidden rounded-4 border bg-dark mb-3" id="cameraContainer" style="display:none; aspect-ratio: 4/3; max-height: 250px;">
                    <video id="webcamPreview" style="width: 100%; height: 100%; object-fit: cover;"></video>
                    <div class="position-absolute top-50 start-50 translate-middle border border-info border-3 rounded-3" style="width: 150px; height: 150px; opacity: 0.6; pointer-events: none; box-shadow: 0 0 0 9999px rgba(0,0,0,0.5);"></div>
                </div>
                
                <div class="alert alert-secondary border-0 rounded-4 p-2 text-center small mb-0" id="scannerStatus" style="font-size: 0.8rem;">
                    Camera status: Off
                </div>
            </div>
        </div>

        <!-- RIGHT PANEL: Virtual Scan Sandbox -->
        <div class="col-lg-8">
            <div class="card glass-card p-4 border-0 h-100">
                <div class="d-flex justify-content-between align-items-center border-bottom pb-3 mb-3">
                    <h5 class="fw-bold text-dark mb-0">
                        <i class="fas fa-shopping-basket me-2 text-primary"></i> 2. Virtual Scan Zone
                        <span class="text-muted fs-6 font-monospace" style="font-size: 0.85rem;"> - Physical State</span>
                    </h5>
                    <span class="badge bg-secondary rounded-pill small" id="checkedCountBadge">0 Items Checked Inside</span>
                </div>

                <p class="text-muted small">Check the items that are <strong>physically placed</strong> inside the flight case right now in the real world:</p>

                <!-- Items physical presence checklist table -->
                <div class="table-responsive mb-4" style="max-height: 380px;">
                    <table class="table table-hover align-middle" id="itemsSimulatorTable">
                        <thead class="table-light sticky-top" style="z-index: 5;">
                            <tr>
                                <th style="width: 80px;">In Case?</th>
                                <th>ID</th>
                                <th>Item Name</th>
                                <th>Serial Number</th>
                                <th>Category</th>
                                <th>DB Storage Location</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($items)): ?>
                                <tr>
                                    <td colspan="6" class="text-center py-4 text-muted">No equipment items found in database.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($items as $item): ?>
                                    <?php 
                                        // Identify key items requested by user (4, 9, 10, 12)
                                        $isTargetId = in_array($item['id'], [4, 9, 10, 12]);
                                        $itemRowClass = $isTargetId ? 'item-row-discrepancy' : '';
                                    ?>
                                    <tr id="itemRow_<?php echo $item['id']; ?>" class="<?php echo $itemRowClass; ?>">
                                        <td>
                                            <label class="switch">
                                                <input type="checkbox" class="physical-toggle" 
                                                       id="physical_<?php echo $item['id']; ?>" 
                                                       value="<?php echo $item['id']; ?>"
                                                       data-name="<?php echo htmlspecialchars($item['item_name']); ?>"
                                                       data-serial="<?php echo htmlspecialchars($item['serial_number']); ?>"
                                                       onchange="updateCheckedCount()">
                                                <span class="slider round"></span>
                                            </label>
                                        </td>
                                        <td><code class="text-secondary"><?php echo $item['id']; ?></code></td>
                                        <td>
                                            <span class="fw-bold text-dark"><?php echo htmlspecialchars($item['item_name']); ?></span>
                                            <?php if ($isTargetId): ?>
                                                <span class="badge bg-danger ms-2 small" style="font-size: 0.65rem;">Mismatched Target</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><code class="text-primary font-monospace"><?php echo htmlspecialchars($item['serial_number']); ?></code></td>
                                        <td><span class="badge bg-light text-dark border"><?php echo htmlspecialchars($item['category'] ?: 'Unassigned'); ?></span></td>
                                        <td class="storage-loc-cell" id="db_loc_<?php echo $item['id']; ?>">
                                            <?php if (!empty($item['storage_location'])): ?>
                                                <span class="badge bg-info text-white"><i class="fas fa-box me-1"></i> <?php echo htmlspecialchars($item['storage_location']); ?></span>
                                            <?php else: ?>
                                                <span class="text-muted small"><em>None (In Stock)</em></span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Trigger RF scan section -->
                <div class="row align-items-center bg-light rounded-4 p-3 border mx-1">
                    <div class="col-md-7 text-center text-md-start">
                        <h6 class="fw-bold text-dark mb-1"><i class="fas fa-wave-square text-info me-1"></i> Trigger RF Scan Sweep</h6>
                        <p class="text-muted small mb-md-0">Sweeps 915 MHz frequency to record tags in range without opening the case.</p>
                    </div>
                    <div class="col-md-5 text-center text-md-end">
                        <div class="d-flex justify-content-center justify-content-md-end align-items-center">
                            <div class="radar-box my-1" id="radarButton" onclick="triggerScan()">
                                <div class="text-center">
                                    <i class="fas fa-wifi fa-2x mb-1 d-block"></i>
                                    <span class="small fw-bold">RUN SWEEP</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ROW 3: Middleware console and Discrepancy Board -->
    <div class="row g-4 mt-2">
        <!-- Terminal console -->
        <div class="col-md-6">
            <div class="card glass-card p-4 border-0 h-100">
                <h5 class="fw-bold text-dark mb-3"><i class="fas fa-terminal me-2 text-success"></i> 3. RFID Middleware Stream Log</h5>
                <p class="text-muted small">Simulated console logging showing translation of electromagnetic sweeps to JSON API data:</p>
                <div class="terminal-console" id="terminalLog">
                    <div class="terminal-line"><span class="timestamp">[08:43:00]</span> <span class="accent">System</span> - Ready to read. Point device and click 'RUN SWEEP'.</div>
                </div>
            </div>
        </div>

        <!-- Discrepancy Board and DB Sync -->
        <div class="col-md-6">
            <div class="card glass-card p-4 border-0 h-100 d-flex flex-column">
                <h5 class="fw-bold text-dark mb-3"><i class="fas fa-heartbeat me-2 text-danger"></i> 4. Discrepancy Audit Board</h5>
                
                <div class="flex-grow-1">
                    <!-- Summary alerts -->
                    <div id="auditOverview" class="mb-3">
                        <div class="alert alert-secondary border-0 p-3 rounded-4 mb-0">
                            <i class="fas fa-pause-circle me-2"></i> Scan not yet initiated. Pull trigger to audit contents.
                        </div>
                    </div>

                    <!-- Discrepancies list -->
                    <div id="discrepancyList" style="max-height: 180px; overflow-y: auto;">
                        <!-- Will be populated by JS -->
                    </div>
                </div>

                <div class="mt-4 pt-3 border-top d-flex justify-content-between align-items-center gap-2">
                    <button class="btn btn-outline-secondary rounded-pill px-4 btn-sm" onclick="resetAudit()">
                        <i class="fas fa-redo me-1"></i> Reset
                    </button>
                    <button class="btn btn-primary rounded-pill px-4 btn-sm" id="syncDbBtn" disabled onclick="applyDatabaseSync()">
                        <i class="fas fa-cloud-upload-alt me-1"></i> Synchronize Database
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Scripts loaded at the end -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/2.1.4/toastr.min.js"></script>
<!-- ZXing Barcode/QR Code Library -->
<script src="https://unpkg.com/@zxing/library@0.18.6/umd/index.min.js"></script>

<script>
    // Database values fetched on load
    const selectedCase = <?php echo json_encode($selected_case); ?>;
    const expectedItemsIds = <?php echo json_encode(array_column($expected_items, 'id')); ?>;
    const casesList = <?php echo json_encode($cases); ?>;
    
    let scannedItemIds = [];
    let discrepanciesFound = false;
    let cameraReader = null;
    let isWebcamScanning = false;

    $(document).ready(function() {
        updateCheckedCount();
        
        // Check default checked boxes if any exist in preset
        loadPreset('correct');

        <?php if (isset($_GET['scanned']) && $_GET['scanned'] == 1 && $selected_case): ?>
        if (typeof toastr !== 'undefined') {
            toastr.success("QR Code scan successful! Case '<?php echo htmlspecialchars($selected_case['item_name']); ?>' loaded. It has <?php echo count($expected_items); ?> assigned cabinet(s) inside.");
        }
        
        // Add log to terminal
        setTimeout(() => {
            const logConsole = $('#terminalLog');
            logConsole.append(`
                <div class="terminal-line">
                    <span class="timestamp">[${new Date().toLocaleTimeString()}]</span>
                    <span class="accent">System</span> - Scanned flight case QR code. Resolved to Case ID: <span class="accent"><?php echo $selected_case['id']; ?></span> (Serial: <?php echo htmlspecialchars($selected_case['serial_number']); ?>).
                </div>
            `);
            logConsole.append(`
                <div class="terminal-line">
                    <span class="timestamp">[${new Date().toLocaleTimeString()}]</span>
                    <span class="accent">System</span> - Retrieved nested items list. Case contains <span class="accent"><?php echo count($expected_items); ?></span> assigned cabinets: [<?php echo htmlspecialchars(implode(', ', array_column($expected_items, 'serial_number'))); ?>].
                </div>
            `);
            logConsole.scrollTop(logConsole[0].scrollHeight);
        }, 300);
        <?php endif; ?>
    });

    // Change target case
    function changeCase(caseId) {
        window.location.href = 'rfid_simulator.php?case_id=' + caseId;
    }

    // Preset templates handler
    function loadPreset(presetType) {
        // Uncheck everything first
        $('.physical-toggle').prop('checked', false);

        if (presetType === 'correct') {
            // Check expected items
            expectedItemsIds.forEach(id => {
                $(`#physical_${id}`).prop('checked', true);
            });
        } else if (presetType === 'discrepancy') {
            // Check expected items + items 4, 9, 10, 12
            expectedItemsIds.forEach(id => {
                $(`#physical_${id}`).prop('checked', true);
            });
            
            // Add target IDs
            [4, 9, 10, 12].forEach(id => {
                if ($(`#physical_${id}`).length) {
                    $(`#physical_${id}`).prop('checked', true);
                }
            });
        }
        
        updateCheckedCount();
        if (typeof toastr !== 'undefined') {
            toastr.info(`Preset '${presetType.toUpperCase()}' loaded. Click 'RUN SWEEP' to scan.`);
        }
        resetAuditUI();
    }

    // UI Reset
    function resetAuditUI() {
        $('#radarButton').removeClass('radar-active');
        $('#syncDbBtn').prop('disabled', true);
        $('#auditOverview').html(`
            <div class="alert alert-secondary border-0 p-3 rounded-4 mb-0">
                <i class="fas fa-pause-circle me-2"></i> Scan not yet initiated. Pull trigger to audit contents.
            </div>
        `);
        $('#discrepancyList').empty();
    }

    function resetAudit() {
        $('.physical-toggle').prop('checked', false);
        updateCheckedCount();
        resetAuditUI();
        
        const logConsole = $('#terminalLog');
        logConsole.append(`
            <div class="terminal-line">
                <span class="timestamp">[${new Date().toLocaleTimeString()}]</span>
                <span class="accent">System</span> - Simulator reset. Logs cleared.
            </div>
        `);
        logConsole.scrollTop(logConsole[0].scrollHeight);
    }

    // Count items physically present
    function updateCheckedCount() {
        const checkedCount = $('.physical-toggle:checked').length;
        $('#checkedCountBadge').text(`${checkedCount} Items Checked Inside`);
    }

    // Run the RFID Sweep Simulation
    function triggerScan() {
        if (!selectedCase) {
            if (typeof toastr !== 'undefined') {
                toastr.error("Please select a Case first!");
            }
            return;
        }

        const radar = $('#radarButton');
        radar.addClass('radar-active');

        const logConsole = $('#terminalLog');
        logConsole.empty();

        // 1. Logs start
        logTerminal("System", "Initiating electromagnetic radio sweep (915 MHz)...");
        
        const readerType = $('input[name="readerType"]:checked').val();
        if (readerType === 'handheld') {
            logTerminal("Zebra-RFD40", "Power level set to 30 dBm (Standard Handheld sled). Sweep coverage: ~6m radius.");
        } else {
            logTerminal("Portal-Fixed", "Fixed choke-point antennas active. Beam angle: 60 degrees polarization.");
        }

        // Gather physical items checked in UI
        const checkedToggles = $('.physical-toggle:checked');
        scannedItemIds = [];
        
        checkedToggles.each(function() {
            scannedItemIds.push(parseInt($(this).val()));
        });

        // Simulate delay
        setTimeout(() => {
            logTerminal("RF-Sweep", `Sweep completed. Detected ${scannedItemIds.length + 1} active transponders in field.`);
            logTerminal("Middleware", `Decoded Case tag EPC: <span class="accent">EPC_CASE_${selectedCase.serial_number}</span>`);

            checkedToggles.each(function() {
                const id = $(this).val();
                const serial = $(this).data('serial');
                const name = $(this).data('name');
                logTerminal("Middleware", `Decoded Asset tag EPC: <span class="accent">EPC_ITEM_${serial}</span> (ID: ${id} - ${name})`);
            });

            // Generate Mock JSON payload
            const mockJson = {
                reader_id: readerType === 'handheld' ? "ZBRA_HND_092" : "PORTAL_GT_001",
                case_scanned: {
                    id: selectedCase.id,
                    name: selectedCase.item_name,
                    serial: selectedCase.serial_number
                },
                scanned_item_ids: scannedItemIds,
                timestamp: Math.floor(Date.now() / 1000)
            };

            logTerminal("Middleware", `Formatting JSON payload for database synchronization:`);
            logTerminal("JSON", `<pre style="color: #58a6ff; margin:0;">${JSON.stringify(mockJson, null, 2)}</pre>`);

            // Perform Audit
            runDiscrepancyAudit();

            radar.removeClass('radar-active');
        }, 1500);
    }

    // Log formatter helper
    function logTerminal(source, message) {
        const console = $('#terminalLog');
        const time = new Date().toLocaleTimeString();
        console.append(`
            <div class="terminal-line">
                <span class="timestamp">[${time}]</span>
                <span class="fw-bold text-light">${source}:</span> ${message}
            </div>
        `);
        console.scrollTop(console[0].scrollHeight);
    }

    // Run audit checks
    function runDiscrepancyAudit() {
        const list = $('#discrepancyList');
        list.empty();
        discrepanciesFound = false;

        let mismatchedItems = [];
        let missingItems = [];

        // Detect mismatched items (Checked physically in Case, but DB storage_location is NOT this Case)
        $('.physical-toggle:checked').each(function() {
            const id = parseInt($(this).val());
            const name = $(this).data('name');
            const serial = $(this).data('serial');
            
            if (!expectedItemsIds.includes(id)) {
                mismatchedItems.push({ id, name, serial });
                discrepanciesFound = true;
            }
        });

        // Detect missing items (Registered to Case in DB, but NOT checked physically in Case)
        expectedItemsIds.forEach(id => {
            if (!scannedItemIds.includes(id)) {
                // Fetch details from row
                const row = $(`#itemRow_${id}`);
                const name = row.find('td:nth-child(3) span.fw-bold').text();
                const serial = row.find('td:nth-child(4) code').text();
                missingItems.push({ id, name, serial });
                discrepanciesFound = true;
            }
        });

        // Update board
        const overview = $('#auditOverview');
        if (!discrepanciesFound) {
            overview.html(`
                <div class="alert alert-success border-0 p-3 rounded-4 mb-0">
                    <i class="fas fa-check-circle me-2"></i> <strong>Audit Match:</strong> All scanned items match Fly Case registry perfectly!
                </div>
            `);
            logTerminal("Audit", "Scan matches DB registry 100%. No action required.");
            $('#syncDbBtn').prop('disabled', true);
        } else {
            overview.html(`
                <div class="alert alert-danger border-0 p-3 rounded-4 mb-0">
                    <i class="fas fa-exclamation-triangle me-2"></i> <strong>Discrepancies Detected:</strong> Mismatches found between physical and database states.
                </div>
            `);
            
            // Print to terminal console
            logTerminal("Audit", `<span class="danger">ALERT: Mismatches found. ${mismatchedItems.length} unexpected items, ${missingItems.length} missing items.</span>`);

            // Append detailed alerts
            mismatchedItems.forEach(item => {
                list.append(`
                    <div class="alert alert-danger border-0 py-2 px-3 rounded-3 my-2 d-flex align-items-center justify-content-between" style="font-size: 0.85rem;">
                        <span>
                            <i class="fas fa-times-circle me-1"></i>
                            <strong>Doesn't belong:</strong> ${item.name} (SN: <code>${item.serial}</code>) is inside but registered to stock!
                        </span>
                        <span class="badge bg-danger">ID: ${item.id}</span>
                    </div>
                `);
            });

            missingItems.forEach(item => {
                list.append(`
                    <div class="alert alert-warning border-0 py-2 px-3 rounded-3 my-2 d-flex align-items-center justify-content-between" style="font-size: 0.85rem;">
                        <span>
                            <i class="fas fa-exclamation-circle me-1"></i>
                            <strong>Missing:</strong> registered to case but not physically scanned.
                        </span>
                        <span class="badge bg-warning text-dark">ID: ${item.id}</span>
                    </div>
                `);
            });

            // Enable sync DB button
            $('#syncDbBtn').prop('disabled', false);
        }
    }

    // Apply Database Sync via Ajax POST
    function applyDatabaseSync() {
        if (!selectedCase) return;

        const syncBtn = $('#syncDbBtn');
        syncBtn.prop('disabled', true);
        syncBtn.html('<span class="spinner-border spinner-border-sm me-2"></span>Syncing...');

        $.ajax({
            url: 'api/cases/rfid_sync.php',
            method: 'POST',
            data: {
                case_id: selectedCase.id,
                scanned_item_ids: scannedItemIds
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    if (typeof toastr !== 'undefined') {
                        toastr.success(response.message || 'Database synchronized successfully!');
                    }
                    logTerminal("Sync", "Commit transaction: database updated successfully.");
                    
                    setTimeout(() => {
                        location.reload();
                    }, 1200);
                } else {
                    if (typeof toastr !== 'undefined') {
                        toastr.error(response.message || 'Synchronization failed.');
                    }
                    syncBtn.prop('disabled', false);
                    syncBtn.html('<i class="fas fa-cloud-upload-alt me-1"></i> Synchronize Database');
                }
            },
            error: function(xhr, status, error) {
                console.error("AJAX Error:", error);
                if (typeof toastr !== 'undefined') {
                    toastr.error('Server error occurred during database sync.');
                }
                syncBtn.prop('disabled', false);
                syncBtn.html('<i class="fas fa-cloud-upload-alt me-1"></i> Synchronize Database');
            }
        });
    }

    // --- CAMERA SCANNER LOGIC (ZXING) ---
    $('#startCamBtn').on('click', function() {
        startCameraScanner();
    });

    $('#stopCamBtn').on('click', function() {
        stopCameraScanner();
    });

    async function startCameraScanner() {
        if (isWebcamScanning) return;
        
        $('#cameraContainer').slideDown();
        $('#startCamBtn').hide();
        $('#stopCamBtn').show();
        $('#scannerStatus').removeClass('alert-secondary alert-success alert-danger').addClass('alert-warning').text('Accessing camera device...');

        try {
            cameraReader = new ZXing.BrowserMultiFormatReader();
            const videoDevices = await cameraReader.listVideoInputDevices();

            if (videoDevices.length === 0) {
                throw new Error('No camera devices found.');
            }

            // Prefer back-facing camera if available
            let selectedDeviceId = videoDevices[0].deviceId;
            for (const device of videoDevices) {
                if (device.label.toLowerCase().includes('back') || device.label.toLowerCase().includes('rear')) {
                    selectedDeviceId = device.deviceId;
                    break;
                }
            }

            isWebcamScanning = true;
            $('#scannerStatus').text('Camera scanning active. Present QR code...');

            cameraReader.decodeFromVideoDevice(selectedDeviceId, 'webcamPreview', (result, err) => {
                if (result) {
                    const qrText = result.getText();
                    playBeepSound();
                    stopCameraScanner();
                    processScannedQR(qrText);
                }
                if (err && !(err instanceof ZXing.NotFoundException)) {
                    console.error('Scan error:', err);
                }
            });

        } catch (err) {
            console.error('Camera Scanner Error:', err);
            if (typeof toastr !== 'undefined') {
                toastr.error(err.message || 'Could not access camera. Please verify device permissions.');
            }
            stopCameraScanner();
        }
    }

    function stopCameraScanner() {
        if (cameraReader) {
            cameraReader.reset();
            cameraReader = null;
        }
        isWebcamScanning = false;
        $('#cameraContainer').slideUp();
        $('#startCamBtn').show();
        $('#stopCamBtn').hide();
        $('#scannerStatus').removeClass('alert-warning alert-success alert-danger').addClass('alert-secondary').text('Camera status: Off');
    }

    function playBeepSound() {
        try {
            const audioCtx = new (window.AudioContext || window.webkitAudioContext)();
            const oscillator = audioCtx.createOscillator();
            const gainNode = audioCtx.createGain();
            
            oscillator.connect(gainNode);
            gainNode.connect(audioCtx.destination);
            
            oscillator.type = 'sine';
            oscillator.frequency.setValueAtTime(880, audioCtx.currentTime); // A5 note
            gainNode.gain.setValueAtTime(0.1, audioCtx.currentTime);
            
            oscillator.start();
            oscillator.stop(audioCtx.currentTime + 0.1); // Play for 100ms
        } catch (e) {
            console.error('Audio beep failed:', e);
        }
    }

    function processScannedQR(qrText) {
        let foundCase = null;
        let searchStr = qrText.trim().toLowerCase();
        
        // 1. Try JSON parser (e.g. {"i":58,"n":"Fly Case - T"})
        try {
            const parsed = JSON.parse(qrText);
            if (parsed) {
                const idVal = parsed.i || parsed.id || parsed.item_id;
                if (idVal) {
                    foundCase = casesList.find(c => parseInt(c.id) === parseInt(idVal));
                }
                if (!foundCase) {
                    const snVal = parsed.s || parsed.serial || parsed.serial_number;
                    if (snVal) {
                        foundCase = casesList.find(c => c.serial_number.toLowerCase() === snVal.toString().toLowerCase());
                    }
                }
            }
        } catch (e) {
            // Not a valid JSON, ignore error and continue
        }

        // 2. Try Pipe-delimited ID match (e.g. ID:60|SN:xxx)
        if (!foundCase) {
            const idMatch = qrText.match(/ID:(\d+)/i);
            if (idMatch) {
                const idVal = parseInt(idMatch[1]);
                foundCase = casesList.find(c => parseInt(c.id) === idVal);
            }
        }

        // 3. Try Pipe-delimited SN match (e.g. ID:xxx|SN:xxx)
        if (!foundCase) {
            const snMatch = qrText.match(/SN:([^|]+)/i);
            if (snMatch) {
                const snVal = snMatch[1].trim().toLowerCase();
                foundCase = casesList.find(c => c.serial_number.toLowerCase() === snVal);
            }
        }

        // 4. Try URL parser (e.g. id=60)
        if (!foundCase && searchStr.includes('id=')) {
            const urlParams = new URLSearchParams(searchStr.split('?')[1]);
            const idVal = parseInt(urlParams.get('id'));
            foundCase = casesList.find(c => parseInt(c.id) === idVal);
        }
        
        // 5. Try raw ID match
        if (!foundCase && !isNaN(searchStr)) {
            const idVal = parseInt(searchStr);
            foundCase = casesList.find(c => parseInt(c.id) === idVal);
        }
        
        // 6. Try Serial Number or Name match
        if (!foundCase) {
            foundCase = casesList.find(c => 
                c.serial_number.toLowerCase() === searchStr || 
                c.item_name.toLowerCase() === searchStr
            );
        }

        if (foundCase) {
            $('#scannerStatus').removeClass('alert-secondary alert-warning').addClass('alert-success').text(`Scanned: ${foundCase.item_name}`);
            if (typeof toastr !== 'undefined') {
                toastr.success(`Scanned: ${foundCase.item_name} loaded!`);
            }
            
            // Add to middleware log terminal
            const logConsole = $('#terminalLog');
            logConsole.append(`
                <div class="terminal-line">
                    <span class="timestamp">[${new Date().toLocaleTimeString()}]</span>
                    <span class="accent">Scanner</span> - Camera scanned QR code successfully. Resolved to Case ID: <span class="accent">${foundCase.id}</span> (Serial: ${foundCase.serial_number}).
                </div>
            `);
            logConsole.scrollTop(logConsole[0].scrollHeight);
            
            // Redirect/change case (reloads the page to retrieve assigned cabinets)
            setTimeout(() => {
                window.location.href = 'rfid_simulator.php?case_id=' + foundCase.id + '&scanned=1';
            }, 1000);
        } else {
            $('#scannerStatus').removeClass('alert-secondary alert-warning').addClass('alert-danger').text(`Not Recognized: "${qrText.substring(0, 25)}"`);
            if (typeof toastr !== 'undefined') {
                toastr.error("QR Code does not match any flight case in the database.");
            }
            
            const logConsole = $('#terminalLog');
            logConsole.append(`
                <div class="terminal-line">
                    <span class="timestamp">[${new Date().toLocaleTimeString()}]</span>
                    <span class="danger">Scanner</span> - Scan failed. Could not match QR text: "${qrText}" to any registered case.
                </div>
            `);
            logConsole.scrollTop(logConsole[0].scrollHeight);
        }
    }
</script>

<?php
// Close the open main-content and container-fluid divs from header.php
if (!isset($skip_navbar) || !$skip_navbar) {
    echo '</div></div>';
}
?>
</body>
</html>
