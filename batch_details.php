<?php
// batch_details.php - View batch details in separate page
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

$current_page = basename(__FILE__);
require_once 'includes/bootstrap.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit();
}

// Check if batch ID is provided
if (!isset($_GET['batch_id'])) {
    die("Invalid batch ID.");
}

$batch_id = $_GET['batch_id'];

// Database connection
$conn = new mysqli('localhost', 'root', '', 'ability_db');
if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

// Get batch details
$sql = "
    SELECT bs.*, u.username as submitted_by_name,
           DATE_FORMAT(bs.submitted_at, '%Y-%m-%d %H:%i:%s') as formatted_date,
           DATE_FORMAT(bs.created_at, '%Y-%m-%d %H:%i:%s') as created_date
    FROM batch_scans bs 
    LEFT JOIN users u ON bs.submitted_by = u.id 
    WHERE bs.batch_id = ?
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $batch_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("Batch not found.");
}

$batch = $result->fetch_assoc();

// Get batch items if you have an items table (optional)
$items = [];
$items_sql = "SHOW TABLES LIKE 'batch_items'";
if ($conn->query($items_sql)->num_rows > 0) {
    $items_query = $conn->prepare("SELECT * FROM batch_items WHERE batch_id = ? ORDER BY scanned_at DESC");
    $items_query->bind_param("s", $batch_id);
    $items_query->execute();
    $items_result = $items_query->get_result();

    while ($item = $items_result->fetch_assoc()) {
        $items[] = $item;
    }
}

$conn->close();

$pageTitle = "Batch Details - " . htmlspecialchars($batch['batch_name']);
$breadcrumbItems = [
    'Dashboard' => 'dashboard_full.php',
    'Batch History' => 'batch_history.php',
    'Batch Details' => ''
];

require_once 'views/partials/header.php';
?>

<style>
    .batch-details-card {
        border: none;
        border-radius: 10px;
        box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
    }

    .info-label {
        font-weight: 600;
        color: #6c757d;
        min-width: 150px;
    }

    .status-badge-lg {
        padding: 8px 16px;
        border-radius: 20px;
        font-size: 0.9rem;
        font-weight: 500;
    }

    .status-completed {
        background-color: rgba(40, 167, 69, 0.15);
        color: #28a745;
    }

    .status-processing {
        background-color: rgba(255, 193, 7, 0.15);
        color: #ffc107;
    }

    .status-failed {
        background-color: rgba(220, 53, 69, 0.15);
        color: #dc3545;
    }

    .batch-id-display {
        font-family: 'Courier New', monospace;
        font-size: 1.1rem;
        background: #f8f9fa;
        padding: 8px 15px;
        border-radius: 6px;
        border: 1px solid #e9ecef;
    }
</style>

<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0">
                <i class="fas fa-clipboard-list me-2"></i>Batch Details
            </h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <?php foreach ($breadcrumbItems as $text => $link): ?>
                        <?php if (!empty($link)): ?>
                            <li class="breadcrumb-item"><a href="<?php echo $link; ?>"><?php echo $text; ?></a></li>
                        <?php else: ?>
                            <li class="breadcrumb-item active"><?php echo $text; ?></li>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </ol>
            </nav>
        </div>
        <div class="btn-group">
            <a href="batch_history.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left me-1"></i> Back to History
            </a>
            <button onclick="window.print()" class="btn btn-info">
                <i class="fas fa-print me-1"></i> Print
            </button>
        </div>
    </div>

    <!-- Batch Information Card -->
    <div class="row mb-4">
        <div class="col-lg-8">
            <div class="card batch-details-card">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>Batch Information</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <table class="table table-borderless">
                                <tr>
                                    <td class="info-label">Batch ID:</td>
                                    <td><code class="batch-id-display"><?php echo htmlspecialchars($batch['batch_id']); ?></code></td>
                                </tr>
                                <tr>
                                    <td class="info-label">Batch Name:</td>
                                    <td><strong><?php echo htmlspecialchars($batch['batch_name']); ?></strong></td>
                                </tr>
                                <tr>
                                    <td class="info-label">Status:</td>
                                    <td>
                                        <span class="status-badge-lg status-<?php echo $batch['status']; ?>">
                                            <?php echo ucfirst($batch['status']); ?>
                                        </span>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="info-label">Total Items:</td>
                                    <td><span class="badge bg-primary fs-6"><?php echo $batch['total_items']; ?></span></td>
                                </tr>
                                <tr>
                                    <td class="info-label">Unique Items:</td>
                                    <td><span class="badge bg-info fs-6"><?php echo $batch['unique_items']; ?></span></td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <table class="table table-borderless">
                                <tr>
                                    <td class="info-label">Action:</td>
                                    <td>
                                        <span class="badge bg-warning fs-6">
                                            <?php echo ucfirst(str_replace('_', ' ', $batch['action_applied'])); ?>
                                        </span>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="info-label">Location:</td>
                                    <td><?php echo htmlspecialchars($batch['location_applied']); ?></td>
                                </tr>
                                <tr>
                                    <td class="info-label">Submitted By:</td>
                                    <td><?php echo htmlspecialchars($batch['submitted_by_name']); ?></td>
                                </tr>
                                <tr>
                                    <td class="info-label">Submitted At:</td>
                                    <td><?php echo $batch['formatted_date']; ?></td>
                                </tr>
                                <tr>
                                    <td class="info-label">Created:</td>
                                    <td><?php echo $batch['created_date']; ?></td>
                                </tr>
                            </table>
                        </div>
                    </div>

                    <?php if (!empty($batch['notes'])): ?>
                        <div class="mt-4">
                            <h6><i class="fas fa-sticky-note me-2"></i>Notes</h6>
                            <div class="border rounded p-3 bg-light">
                                <?php echo nl2br(htmlspecialchars($batch['notes'])); ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card batch-details-card">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0"><i class="fas fa-chart-pie me-2"></i>Summary</h5>
                </div>
                <div class="card-body text-center">
                    <div class="mb-4">
                        <div class="d-inline-block position-relative">
                            <svg width="120" height="120" viewBox="0 0 120 120">
                                <!-- Background circle -->
                                <circle cx="60" cy="60" r="54" fill="none" stroke="#e9ecef" stroke-width="12" />
                                <!-- Progress circle -->
                                <?php
                                $percentage = $batch['unique_items'] > 0 ?
                                    ($batch['total_items'] / ($batch['unique_items'] * 1.5)) * 100 : 0;
                                $percentage = min(100, $percentage);
                                $circumference = 2 * pi() * 54;
                                $strokeDashoffset = $circumference - ($percentage / 100 * $circumference);
                                ?>
                                <circle cx="60" cy="60" r="54" fill="none" stroke="#4361ee" stroke-width="12"
                                    stroke-dasharray="<?php echo $circumference; ?>"
                                    stroke-dashoffset="<?php echo $strokeDashoffset; ?>"
                                    stroke-linecap="round" transform="rotate(-90 60 60)" />
                            </svg>
                            <div class="position-absolute top-50 start-50 translate-middle">
                                <h2 class="mb-0"><?php echo round($percentage); ?>%</h2>
                                <small class="text-muted">Efficiency</small>
                            </div>
                        </div>
                    </div>

                    <div class="row text-start">
                        <div class="col-6">
                            <small class="text-muted">Total Scanned</small>
                            <h5><?php echo $batch['total_items']; ?></h5>
                        </div>
                        <div class="col-6">
                            <small class="text-muted">Unique Items</small>
                            <h5><?php echo $batch['unique_items']; ?></h5>
                        </div>
                        <div class="col-6">
                            <small class="text-muted">Duplicates</small>
                            <h5><?php echo $batch['total_items'] - $batch['unique_items']; ?></h5>
                        </div>
                        <div class="col-6">
                            <small class="text-muted">Success Rate</small>
                            <h5><?php echo $batch['status'] === 'completed' ? '100%' : ($batch['status'] === 'failed' ? '0%' : '50%'); ?></h5>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="card batch-details-card mt-4">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0"><i class="fas fa-bolt me-2"></i>Quick Actions</h5>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a href="#" class="btn btn-warning">
                            <i class="fas fa-redo me-2"></i> Reprocess Batch
                        </a>
                        <a href="export_report.php?batch_id=<?php echo urlencode($batch['batch_id']); ?>" class="btn btn-success">
                            <i class="fas fa-download me-2"></i> Export Report
                        </a>
                        <button onclick="copyToClipboard('<?php echo htmlspecialchars($batch['batch_id']); ?>')"
                            class="btn btn-secondary">
                            <i class="fas fa-copy me-2"></i> Copy Batch ID
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Items Table (if available) -->
    <?php if (!empty($items)): ?>
        <div class="row">
            <div class="col-12">
                <div class="card batch-details-card">
                    <div class="card-header bg-secondary text-white">
                        <h5 class="mb-0"><i class="fas fa-boxes me-2"></i>Scanned Items (<?php echo count($items); ?>)</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Item ID</th>
                                        <th>Item Name</th>
                                        <th>Serial Number</th>
                                        <th>Category</th>
                                        <th>Status</th>
                                        <th>Scanned At</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($items as $index => $item): ?>
                                        <tr>
                                            <td><?php echo $index + 1; ?></td>
                                            <td><code><?php echo htmlspecialchars($item['item_id'] ?? 'N/A'); ?></code></td>
                                            <td><?php echo htmlspecialchars($item['item_name'] ?? 'Unknown'); ?></td>
                                            <td><?php echo htmlspecialchars($item['serial_number'] ?? 'N/A'); ?></td>
                                            <td><?php echo htmlspecialchars($item['category'] ?? 'Uncategorized'); ?></td>
                                            <td>
                                                <span class="badge bg-<?php echo ($item['status'] ?? 'success') === 'success' ? 'success' : 'danger'; ?>">
                                                    <?php echo ucfirst($item['status'] ?? 'Success'); ?>
                                                </span>
                                            </td>
                                            <td><?php echo $item['scanned_at'] ?? 'Unknown'; ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php else: ?>
        <div class="row">
            <div class="col-12">
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    Individual item records are not available for this batch.
                    Only batch summary information is shown.
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
    function copyToClipboard(text) {
        navigator.clipboard.writeText(text).then(function() {
            // Show success message
            alert('Batch ID copied to clipboard!');
        }).catch(function(err) {
            console.error('Failed to copy: ', err);
            // Fallback
            var textArea = document.createElement("textarea");
            textArea.value = text;
            document.body.appendChild(textArea);
            textArea.select();
            document.execCommand("copy");
            document.body.removeChild(textArea);
            alert('Batch ID copied to clipboard!');
        });
    }
</script>

<?php
if (file_exists('views/partials/footer.php')) {
    require_once 'views/partials/footer.php';
} else {
    echo '</div></body></html>';
}
?>