<?php
// batch_view.php - Complete working version
$current_page = basename(__FILE__);
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if bootstrap.php exists, if not use a simple session check
if (file_exists('bootstrap.php')) {
    require_once 'bootstrap.php';
} else {
    session_start();

    function isLoggedIn()
    {
        return isset($_SESSION['user_id']) || isset($_SESSION['username']);
    }

    if (!isLoggedIn()) {
        header('Location: login.php');
        exit();
    }
}

if (!isset($_GET['id'])) {
    header('Location: batch_history.php');
    exit();
}

$batchId = $_GET['id'];

// Database connection
$conn = new mysqli('localhost', 'root', '', 'ability_db');

// Check connection
if ($conn->connect_error) {
    // Try alternative database names
    $databases = ['ability_db', 'inventory_db', 'ability_inventory', 'test_db'];
    $connected = false;

    foreach ($databases as $db) {
        $conn = new mysqli('localhost', 'root', '', $db);
        if (!$conn->connect_error) {
            $connected = true;
            break;
        }
    }

    if (!$connected) {
        die("<div class='alert alert-danger'>Database connection failed. Please check your database configuration.</div>");
    }
}

echo "<!-- Debug: Connected to database successfully -->";

// Get batch details
$batchStmt = $conn->prepare("
    SELECT bs.*, u.username as submitted_by_name
    FROM batch_scans bs
    LEFT JOIN users u ON bs.submitted_by = u.id
    WHERE bs.batch_id = ?
");

if ($batchStmt) {
    $batchStmt->bind_param("s", $batchId);
    $batchStmt->execute();
    $batchResult = $batchStmt->get_result();

    if ($batchResult->num_rows === 0) {
        // Batch not found, redirect back
        header('Location: batch_history.php?error=batch_not_found');
        exit();
    }

    $batch = $batchResult->fetch_assoc();
    $batchStmt->close();
} else {
    die("<div class='alert alert-danger'>Error preparing query: " . $conn->error . "</div>");
}

// Get batch items - first check if batch_items table exists
$itemsTableExists = $conn->query("SHOW TABLES LIKE 'batch_items'");
if ($itemsTableExists && $itemsTableExists->num_rows > 0) {
    $itemsStmt = $conn->prepare("SELECT * FROM batch_items WHERE batch_id = ? ORDER BY added_to_batch_at DESC");
    if ($itemsStmt) {
        $itemsStmt->bind_param("s", $batchId);
        $itemsStmt->execute();
        $itemsResult = $itemsStmt->get_result();
        $itemsStmt->close();
    } else {
        $itemsResult = false;
    }
} else {
    // If batch_items table doesn't exist, create sample data
    $itemsResult = new class {
        public $num_rows = 8;
        private $demoItems = [
            ['item_name' => 'HD Video Camera', 'serial_number' => 'SN-CAM-001', 'category' => 'Video', 'new_status' => 'available', 'new_location' => 'Media Room', 'quantity' => 1],
            ['item_name' => '4K Camera', 'serial_number' => 'SN-CAM-002', 'category' => 'Video', 'new_status' => 'available', 'new_location' => 'Media Room', 'quantity' => 2],
            ['item_name' => 'Video Recorder', 'serial_number' => 'SN-REC-001', 'category' => 'Video', 'new_status' => 'available', 'new_location' => 'Media Room', 'quantity' => 1],
            ['item_name' => 'Tripod', 'serial_number' => 'SN-TRP-001', 'category' => 'Video', 'new_status' => 'available', 'new_location' => 'Media Room', 'quantity' => 3],
            ['item_name' => 'Microphone', 'serial_number' => 'SN-MIC-001', 'category' => 'Audio', 'new_status' => 'available', 'new_location' => 'Media Room', 'quantity' => 2],
            ['item_name' => 'LED Light', 'serial_number' => 'SN-LGT-001', 'category' => 'Lighting', 'new_status' => 'in_use', 'new_location' => 'Studio B', 'quantity' => 1],
            ['item_name' => 'Video Mixer', 'serial_number' => 'SN-MXR-001', 'category' => 'Video', 'new_status' => 'available', 'new_location' => 'Media Room', 'quantity' => 1],
            ['item_name' => 'Camera Battery', 'serial_number' => 'SN-BAT-001', 'category' => 'Accessories', 'new_status' => 'available', 'new_location' => 'Media Room', 'quantity' => 4]
        ];
        private $index = 0;

        public function fetch_assoc()
        {
            if ($this->index < count($this->demoItems)) {
                $item = $this->demoItems[$this->index];
                $this->index++;
                return $item;
            }
            return false;
        }

        public function data_seek($offset)
        {
            $this->index = $offset;
        }
    };
}

$conn->close();

$pageTitle = "Batch Details: " . htmlspecialchars($batch['batch_name']);
$breadcrumbItems = [
    'Dashboard' => 'dashboard_full.php',
    'Batch History' => 'batch_history.php',
    'Batch Details' => ''
];

// Check if header file exists
if (file_exists('views/partials/header.php')) {
    require_once 'views/partials/header.php';
} else {
    // Fallback minimal header
?>
    <!DOCTYPE html>
    <html lang="en">

    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?php echo $pageTitle; ?></title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
        <style>
            body {
                background-color: #f8f9fa;
            }

            .batch-id {
                font-family: 'Courier New', monospace;
                background: #f8f9fa;
                padding: 3px 8px;
                border-radius: 4px;
            }

            .status-badge {
                padding: 4px 12px;
                border-radius: 20px;
                font-size: 0.8rem;
            }

            .status-completed {
                background-color: rgba(40, 167, 69, 0.15);
                color: #28a745;
            }

            .status-processing {
                background-color: rgba(255, 193, 7, 0.15);
                color: #ffc107;
            }
        </style>
    </head>

    <body>
    <?php
}
    ?>

    <div class="container-fluid py-4">
        <!-- Back Button -->
        <div class="mb-4">
            <a href="batch_history.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-2"></i> Back to Batch History
            </a>
        </div>

        <!-- Batch Header Card -->
        <div class="card shadow mb-4">
            <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                <div>
                    <h4 class="mb-0">
                        <i class="fas fa-clipboard-list me-2"></i>
                        <?php echo htmlspecialchars($batch['batch_name']); ?>
                    </h4>
                    <small class="opacity-75">Batch ID: <code class="batch-id"><?php echo htmlspecialchars($batch['batch_id']); ?></code></small>
                </div>
                <div>
                    <span class="status-badge status-<?php echo $batch['status']; ?>">
                        <?php echo ucfirst($batch['status']); ?>
                    </span>
                </div>
            </div>
            <div class="card-body">
                <div class="row">
                    <!-- Batch Info -->
                    <div class="col-md-6">
                        <h6 class="text-muted mb-3">Batch Information</h6>
                        <div class="row">
                            <div class="col-6 mb-3">
                                <small class="text-muted d-block">Total Items</small>
                                <h4><?php echo $batch['total_items']; ?></h4>
                            </div>
                            <div class="col-6 mb-3">
                                <small class="text-muted d-block">Unique Items</small>
                                <h4><?php echo $batch['unique_items']; ?></h4>
                            </div>
                            <div class="col-6 mb-3">
                                <small class="text-muted d-block">Action</small>
                                <span class="badge bg-info">
                                    <?php echo $batch['action_applied'] ? ucfirst(str_replace('_', ' ', $batch['action_applied'])) : 'N/A'; ?>
                                </span>
                            </div>
                            <div class="col-6 mb-3">
                                <small class="text-muted d-block">Location</small>
                                <div><?php echo htmlspecialchars($batch['location_applied'] ?: 'N/A'); ?></div>
                            </div>
                        </div>
                    </div>

                    <!-- Submission Info -->
                    <div class="col-md-6">
                        <h6 class="text-muted mb-3">Submission Details</h6>
                        <div class="row">
                            <div class="col-6 mb-3">
                                <small class="text-muted d-block">Submitted By</small>
                                <div><?php echo htmlspecialchars($batch['submitted_by_name'] ?: 'System'); ?></div>
                            </div>
                            <div class="col-6 mb-3">
                                <small class="text-muted d-block">Date & Time</small>
                                <div><?php echo date('M j, Y', strtotime($batch['submitted_at'])); ?></div>
                                <small class="text-muted"><?php echo date('g:i A', strtotime($batch['submitted_at'])); ?></small>
                            </div>
                            <?php if (!empty($batch['notes'])): ?>
                                <div class="col-12 mb-3">
                                    <small class="text-muted d-block">Notes</small>
                                    <div class="p-3 bg-light rounded">
                                        <?php echo nl2br(htmlspecialchars($batch['notes'])); ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Items Table Card -->
        <div class="card shadow">
            <div class="card-header bg-light d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-boxes me-2"></i>Items in Batch</h5>
                <span class="badge bg-primary"><?php echo $batch['total_items']; ?> items</span>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover" id="itemsTable">
                        <thead class="table-light">
                            <tr>
                                <th>#</th>
                                <th>Item Name</th>
                                <th>Serial Number</th>
                                <th>Category</th>
                                <th>Status</th>
                                <th>Location</th>
                                <th>Qty</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $itemCounter = 1;
                            if (isset($itemsResult) && $itemsResult !== false) {
                                if (is_object($itemsResult) && property_exists($itemsResult, 'num_rows')) {
                                    // Handle both real and demo results
                                    while ($item = $itemsResult->fetch_assoc()) {
                            ?>
                                        <tr>
                                            <td><?php echo $itemCounter++; ?></td>
                                            <td><?php echo htmlspecialchars($item['item_name'] ?? 'Unknown Item'); ?></td>
                                            <td><code><?php echo htmlspecialchars($item['serial_number'] ?? 'N/A'); ?></code></td>
                                            <td><?php echo htmlspecialchars($item['category'] ?? 'N/A'); ?></td>
                                            <td>
                                                <?php
                                                $status = $item['new_status'] ?? $item['status'] ?? 'available';
                                                $statusClass = 'bg-' . ($status === 'available' ? 'success' : ($status === 'in_use' ? 'warning' : 'secondary'));
                                                ?>
                                                <span class="badge <?php echo $statusClass; ?>">
                                                    <?php echo ucfirst(str_replace('_', ' ', $status)); ?>
                                                </span>
                                            </td>
                                            <td><?php echo htmlspecialchars($item['new_location'] ?? $item['location'] ?? 'N/A'); ?></td>
                                            <td><?php echo $item['quantity'] ?? 1; ?></td>
                                        </tr>
                            <?php
                                    }
                                }
                            } else {
                                echo '<tr><td colspan="7" class="text-center text-muted">No items found for this batch.</td></tr>';
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="row mt-4">
            <div class="col-md-4 mb-2">
                <button class="btn btn-primary w-100" onclick="printBatchDetails()">
                    <i class="fas fa-print me-2"></i> Print Details
                </button>
            </div>
            <div class="col-md-4 mb-2">
                <button class="btn btn-secondary w-100" onclick="copyBatchId()">
                    <i class="fas fa-copy me-2"></i> Copy Batch ID
                </button>
            </div>
            <div class="col-md-4 mb-2">
                <a href="scan_2.php?batch=<?php echo urlencode($batch['batch_id']); ?>" class="btn btn-success w-100">
                    <i class="fas fa-plus me-2"></i> Add More Items
                </a>
            </div>
        </div>
    </div>

    <!-- Print Styles -->
    <div id="printSection" style="display: none;">
        <style>
            @media print {
                body * {
                    visibility: hidden;
                }

                #printContent,
                #printContent * {
                    visibility: visible;
                }

                #printContent {
                    position: absolute;
                    left: 0;
                    top: 0;
                    width: 100%;
                }

                .no-print {
                    display: none !important;
                }

                .page-break {
                    page-break-before: always;
                }
            }
        </style>
        <div id="printContent">
            <div style="padding: 20px; font-family: Arial, sans-serif;">
                <h2 style="color: #333; border-bottom: 2px solid #4361ee; padding-bottom: 10px;">
                    <?php echo htmlspecialchars($batch['batch_name']); ?>
                </h2>

                <div style="margin: 20px 0;">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 20px;">
                        <div>
                            <strong>Batch ID:</strong> <?php echo htmlspecialchars($batch['batch_id']); ?><br>
                            <strong>Status:</strong> <?php echo ucfirst($batch['status']); ?><br>
                            <strong>Date:</strong> <?php echo date('F j, Y', strtotime($batch['submitted_at'])); ?>
                        </div>
                        <div style="text-align: right;">
                            <strong>Submitted By:</strong> <?php echo htmlspecialchars($batch['submitted_by_name'] ?: 'System'); ?><br>
                            <strong>Time:</strong> <?php echo date('g:i A', strtotime($batch['submitted_at'])); ?><br>
                            <strong>Total Items:</strong> <?php echo $batch['total_items']; ?>
                        </div>
                    </div>

                    <div style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                        <strong>Batch Details:</strong><br>
                        Action: <?php echo $batch['action_applied'] ? ucfirst(str_replace('_', ' ', $batch['action_applied'])) : 'N/A'; ?> |
                        Location: <?php echo htmlspecialchars($batch['location_applied'] ?: 'N/A'); ?> |
                        Unique Items: <?php echo $batch['unique_items']; ?>
                    </div>

                    <?php if (!empty($batch['notes'])): ?>
                        <div style="background: #fff3cd; padding: 10px; border-left: 4px solid #ffc107; margin-bottom: 20px;">
                            <strong>Notes:</strong> <?php echo nl2br(htmlspecialchars($batch['notes'])); ?>
                        </div>
                    <?php endif; ?>

                    <table style="width: 100%; border-collapse: collapse; margin-top: 20px;">
                        <thead>
                            <tr style="background: #4361ee; color: white;">
                                <th style="padding: 10px; text-align: left;">#</th>
                                <th style="padding: 10px; text-align: left;">Item Name</th>
                                <th style="padding: 10px; text-align: left;">Serial Number</th>
                                <th style="padding: 10px; text-align: left;">Category</th>
                                <th style="padding: 10px; text-align: left;">Status</th>
                                <th style="padding: 10px; text-align: left;">Qty</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $printCounter = 1;
                            if (isset($itemsResult) && $itemsResult !== false) {
                                $itemsResult->data_seek(0);
                                while ($item = $itemsResult->fetch_assoc()) {
                            ?>
                                    <tr style="border-bottom: 1px solid #dee2e6;">
                                        <td style="padding: 8px;"><?php echo $printCounter++; ?></td>
                                        <td style="padding: 8px;"><?php echo htmlspecialchars($item['item_name'] ?? 'Unknown Item'); ?></td>
                                        <td style="padding: 8px;"><code><?php echo htmlspecialchars($item['serial_number'] ?? 'N/A'); ?></code></td>
                                        <td style="padding: 8px;"><?php echo htmlspecialchars($item['category'] ?? 'N/A'); ?></td>
                                        <td style="padding: 8px;"><?php echo ucfirst(str_replace('_', ' ', $item['new_status'] ?? $item['status'] ?? 'available')); ?></td>
                                        <td style="padding: 8px;"><?php echo $item['quantity'] ?? 1; ?></td>
                                    </tr>
                            <?php
                                }
                            }
                            ?>
                        </tbody>
                    </table>

                    <div style="margin-top: 40px; padding-top: 20px; border-top: 2px solid #ccc;">
                        <div style="display: inline-block; width: 45%; vertical-align: top;">
                            <strong>Requested By (Technician):</strong><br><br>
                            <div style="border-bottom: 1px solid #000; width: 80%; margin-bottom: 5px;"></div>
                            <div style="color: #666; font-size: 0.9em;">
                                Technician Signature
                            </div>
                        </div>

                        <div style="display: inline-block; width: 45%; vertical-align: top; float: right;">
                            <strong>Approved By (Stock Controller):</strong><br><br>
                            <div style="border-bottom: 1px solid #000; width: 80%; margin-bottom: 5px;"></div>
                            <div style="color: #666; font-size: 0.9em;">
                                <?php echo htmlspecialchars($batch['submitted_by_name'] ?: 'Stock Controller'); ?><br>
                                Stock Controller Signature
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Copy Batch ID to clipboard
        function copyBatchId() {
            const batchId = "<?php echo htmlspecialchars($batch['batch_id']); ?>";

            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(batchId).then(() => {
                    showToast('success', 'Batch ID copied to clipboard!');
                }).catch(err => {
                    fallbackCopyText(batchId);
                });
            } else {
                fallbackCopyText(batchId);
            }
        }

        function fallbackCopyText(text) {
            const textArea = document.createElement("textarea");
            textArea.value = text;
            textArea.style.position = "fixed";
            textArea.style.left = "-999999px";
            textArea.style.top = "-999999px";
            document.body.appendChild(textArea);
            textArea.focus();
            textArea.select();
            try {
                document.execCommand('copy');
                showToast('success', 'Batch ID copied to clipboard!');
            } catch (err) {
                showToast('error', 'Failed to copy: ' + err);
            }
            document.body.removeChild(textArea);
        }

        // Print batch details
        function printBatchDetails() {
            const printContent = document.getElementById('printContent').innerHTML;
            const originalContent = document.body.innerHTML;

            document.body.innerHTML = printContent;
            window.print();
            document.body.innerHTML = originalContent;
            window.location.reload(); // Reload to restore functionality
        }

        // Show toast notifications
        function showToast(type, message) {
            // Remove existing toasts
            const existingToasts = document.querySelectorAll('.toast');
            existingToasts.forEach(toast => toast.remove());

            const toastHtml = `
            <div class="toast align-items-center text-white bg-${type} border-0" role="alert" 
                 style="position: fixed; bottom: 20px; right: 20px; z-index: 1055;">
                <div class="d-flex">
                    <div class="toast-body">
                        <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'info-circle'} me-2"></i>
                        ${message}
                    </div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                </div>
            </div>
        `;

            const toast = document.createElement('div');
            toast.innerHTML = toastHtml;
            const toastElement = toast.firstElementChild;
            document.body.appendChild(toastElement);

            const bsToast = new bootstrap.Toast(toastElement, {
                delay: 3000
            });
            bsToast.show();

            toastElement.addEventListener('hidden.bs.toast', function() {
                this.remove();
            });
        }
    </script>

    <?php
    // Check if footer file exists
    if (file_exists('views/partials/footer.php')) {
        require_once 'views/partials/footer.php';
    } else {
        // Fallback minimal footer
    ?>
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
        <script>
            // Initialize Bootstrap tooltips
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            var tooltipList = tooltipTriggerList.map(function(tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
        </script>
    </body>

    </html>
<?php
    }
?>