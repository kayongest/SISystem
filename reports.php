<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// reports.php - Reports Management
require_once 'bootstrap.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit();
}

require_once 'includes/functions.php';
require_once 'includes/db_connect.php';

// Get database connection
function getDBConnection()
{
    static $pdo = null;

    if ($pdo === null) {
        try {
            $host = 'localhost';
            $dbname = 'ability_db';
            $username = 'root';
            $password = '';

            $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            die("Connection failed: " . $e->getMessage());
        }
    }

    return $pdo;
}

// Initialize database connection
$pdo = getDBConnection();

// Get mysqli connection for helper functions
$conn = getConnection();

$pageTitle = "Reports & Analytics - aBility";
$showBreadcrumb = true;
$breadcrumbItems = [
    'Dashboard' => 'dashboard_full.php',
    'Reports' => ''
];

// Default date range (last 30 days)
$start_date = $_POST['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
$end_date = $_POST['end_date'] ?? date('Y-m-d');
$report_type = $_POST['report_type'] ?? 'summary';

// Get statistics
$statistics = [
    'dashboard' => ['available' => 0, 'in_use' => 0, 'maintenance' => 0],
    'total_items' => 0,
    'active_items' => 0,
    'total_batches' => 0,
    'recent_scans_count' => 0,
    'low_stock_accessories' => [],
    'stock_alerts' => ['low_stock_count' => 0, 'out_of_stock_count' => 0],
    'batch_status' => [],
    'movement_types' => [],
    'items_by_category' => [],
    'items_by_department' => [],
    'top_scanned_items' => [],
    'top_requested_items' => [],
    'top_search_terms' => [],
    'top_moved_items' => [],
    'demand_by_category' => [],
    'top_technicians' => []
];

try {
    // 1. DASHBOARD OVERVIEW (Using global function)
    $statistics['dashboard'] = getDashboardStats($conn);

    // 2. INVENTORY HEALTH
    // Total items count
    $stmt = $pdo->query("SELECT COUNT(*) as total_items FROM items");
    $statistics['total_items'] = $stmt->fetchColumn();

    // Active items count
    $stmt = $pdo->query("SELECT COUNT(*) as active_items FROM items WHERE status NOT IN ('retired', 'damaged', 'lost')");
    $statistics['active_items'] = $stmt->fetchColumn();

    // Low stock accessories (Using global function)
    $statistics['low_stock_accessories'] = getLowStockAccessories($conn, 5);
    $statistics['stock_alerts'] = getStockAlerts($conn);

    // 3. MOVEMENTS & BATCHES
    // Total batches in range
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM batches WHERE created_at BETWEEN :start AND :end");
    $stmt->execute([':start' => $start_date . ' 00:00:00', ':end' => $end_date . ' 23:59:59']);
    $statistics['total_batches'] = $stmt->fetchColumn();

    // Batch status distribution
    $stmt = $pdo->query("SELECT status, COUNT(*) as count FROM batches GROUP BY status");
    $statistics['batch_status'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Movement types distribution
    $stmt = $pdo->query("SELECT movement_type, COUNT(*) as count FROM stock_movements GROUP BY movement_type");
    $statistics['movement_types'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 4. CATEGORY & DEPARTMENT STATS
    // Items by category
    $stmt = $pdo->query("SELECT 
                         CASE WHEN c.name IS NOT NULL THEN c.name ELSE i.category END as category_name,
                         COUNT(*) as count,
                         SUM(CASE WHEN i.status = 'available' THEN 1 ELSE 0 END) as available_count,
                         SUM(CASE WHEN i.status = 'in_use' THEN 1 ELSE 0 END) as in_use_count
                         FROM items i 
                         LEFT JOIN categories c ON i.category = c.code OR i.category = c.name
                         GROUP BY category_name
                         ORDER BY count DESC LIMIT 10");
    $statistics['items_by_category'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Items by department
    $stmt = $pdo->query("SELECT d.name as department_name, COUNT(*) as count 
                         FROM items i 
                         LEFT JOIN departments d ON i.department = d.code 
                         WHERE d.name IS NOT NULL
                         GROUP BY i.department 
                         ORDER BY count DESC LIMIT 10");
    $statistics['items_by_department'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 5. ACTIVITY & TRENDS
    // Top Scanned/Requested Items (Fallback to batch_items if scan_logs is empty)
    $stmt = $pdo->prepare("SELECT i.item_name, i.serial_number as code, COUNT(bi.id) as scan_count 
                           FROM batch_items bi 
                           JOIN items i ON bi.item_id = i.id 
                           WHERE bi.created_at >= :start_date 
                           GROUP BY bi.item_id 
                           ORDER BY scan_count DESC LIMIT 10");
    $stmt->execute([':start_date' => $start_date]);
    $statistics['top_scanned_items'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // If still empty, try without date filter for demo purposes
    if (empty($statistics['top_scanned_items'])) {
        $stmt = $pdo->query("SELECT i.item_name, i.serial_number as code, COUNT(bi.id) as scan_count 
                             FROM batch_items bi 
                             JOIN items i ON bi.item_id = i.id 
                             GROUP BY bi.item_id 
                             ORDER BY scan_count DESC LIMIT 10");
        $statistics['top_scanned_items'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Top Technicians (By movement submissions)
    $stmt = $pdo->query("SELECT u.username, u.full_name, COUNT(sm.id) as movement_count 
                         FROM stock_movements sm
                         JOIN users u ON sm.technician_id = u.id
                         GROUP BY sm.technician_id
                         ORDER BY movement_count DESC LIMIT 5");
    $statistics['top_technicians'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Top Requested Items (Based on Batch Items)
    $stmt = $pdo->query("SELECT i.item_name, i.serial_number, COUNT(bi.id) as request_count 
                         FROM batch_items bi
                         JOIN items i ON bi.item_id = i.id
                         GROUP BY bi.item_id
                         ORDER BY request_count DESC LIMIT 10");
    $statistics['top_requested_items'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Top Search Terms (from activity_log)
    $stmt = $pdo->query("SELECT description, COUNT(*) as search_count 
                         FROM activity_log 
                         WHERE action_type = 'search' 
                         GROUP BY description 
                         ORDER BY search_count DESC LIMIT 10");
    $statistics['top_search_terms'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Most Frequently Moved Items
    $stmt = $pdo->query("SELECT i.item_name, i.serial_number, COUNT(bi.id) as movement_count 
                         FROM batch_items bi
                         JOIN items i ON bi.item_id = i.id
                         GROUP BY bi.item_id
                         ORDER BY movement_count DESC LIMIT 10");
    $statistics['top_moved_items'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Demand by Category (Requests per category)
    $stmt = $pdo->query("SELECT i.category, COUNT(bi.id) as request_count 
                         FROM batch_items bi
                         JOIN items i ON bi.item_id = i.id
                         GROUP BY i.category
                         ORDER BY request_count DESC");
    $statistics['demand_by_category'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Recent scans (last 30 days)
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM scan_logs WHERE scan_timestamp >= :start_date");
    $stmt->execute([':start_date' => $start_date]);
    $statistics['recent_scans_count'] = $stmt->fetchColumn();
} catch (Exception $e) {
    $error = "Error loading statistics: " . $e->getMessage();
    error_log($error);
}

require_once 'views/partials/header.php';
?>

<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h1 class="h3 mb-0 text-gray-800 fw-bold">
                <i class="fas fa-chart-line text-primary me-2"></i>Reports & Analytics
            </h1>
            <p class="text-muted small mb-0">Comprehensive overview of inventory, movements, and system activity</p>
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-outline-primary" onclick="exportToExcel()">
                <i class="fas fa-file-excel me-2"></i>Excel
            </button>
            <button class="btn btn-primary shadow-sm" onclick="exportToPDF()">
                <i class="fas fa-file-pdf me-2"></i>Export PDF
            </button>
        </div>
    </div>

    <!-- Quick Stats Cards -->
    <div class="row mb-4">
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-0 shadow-sm h-100 py-2" style="border-left: 4px solid #4e73df !important;">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Total Equipment</div>
                            <div class="h4 mb-0 font-weight-bold text-gray-800"><?php echo number_format($statistics['total_items'] ?? 0); ?></div>
                            <div class="text-xs text-muted mt-2">
                                <span class="text-success"><i class="fas fa-check-circle me-1"></i><?php echo $statistics['active_items'] ?? 0; ?></span> Active
                            </div>
                        </div>
                        <div class="col-auto">
                            <div class="bg-primary bg-opacity-10 p-3 rounded-circle">
                                <i class="fas fa-boxes fa-2x text-primary"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-0 shadow-sm h-100 py-2" style="border-left: 4px solid #1cc88a !important;">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Inventory Movements</div>
                            <div class="h4 mb-0 font-weight-bold text-gray-800"><?php echo number_format($statistics['total_batches'] ?? 0); ?></div>
                            <div class="text-xs text-muted mt-2">
                                Last 30 days
                            </div>
                        </div>
                        <div class="col-auto">
                            <div class="bg-success bg-opacity-10 p-3 rounded-circle">
                                <i class="fas fa-exchange-alt fa-2x text-success"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-0 shadow-sm h-100 py-2" style="border-left: 4px solid #e74a3b !important;">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">Low Stock Alerts</div>
                            <div class="h4 mb-0 font-weight-bold text-gray-800"><?php echo $statistics['stock_alerts']['low_stock_count'] ?? 0; ?></div>
                            <div class="text-xs text-muted mt-2">
                                <span class="text-danger"><?php echo $statistics['stock_alerts']['out_of_stock_count'] ?? 0; ?></span> Out of stock
                            </div>
                        </div>
                        <div class="col-auto">
                            <div class="bg-danger bg-opacity-10 p-3 rounded-circle">
                                <i class="fas fa-exclamation-triangle fa-2x text-danger"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-0 shadow-sm h-100 py-2" style="border-left: 4px solid #f6c23e !important;">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">System Scans</div>
                            <div class="h4 mb-0 font-weight-bold text-gray-800"><?php echo number_format($statistics['recent_scans_count'] ?? 0); ?></div>
                            <div class="text-xs text-muted mt-2">
                                Last 30 days
                            </div>
                        </div>
                        <div class="col-auto">
                            <div class="bg-warning bg-opacity-10 p-3 rounded-circle">
                                <i class="fas fa-qrcode fa-2x text-warning"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filter Form -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body py-3">
            <form method="POST" action="" class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label for="start_date" class="form-label small fw-bold text-muted uppercase">Start Date</label>
                    <input type="date" class="form-control form-control-sm border-0 bg-light" id="start_date" name="start_date"
                        value="<?php echo htmlspecialchars($start_date); ?>">
                </div>
                <div class="col-md-3">
                    <label for="end_date" class="form-label small fw-bold text-muted uppercase">End Date</label>
                    <input type="date" class="form-control form-control-sm border-0 bg-light" id="end_date" name="end_date"
                        value="<?php echo htmlspecialchars($end_date); ?>">
                </div>
                <div class="col-md-4">
                    <label for="report_type" class="form-label small fw-bold text-muted uppercase">Report Category</label>
                    <select class="form-select form-select-sm border-0 bg-light" id="report_type" name="report_type">
                        <option value="summary" <?php echo $report_type === 'summary' ? 'selected' : ''; ?>>Overall Summary</option>
                        <option value="inventory" <?php echo $report_type === 'inventory' ? 'selected' : ''; ?>>Inventory & Categories</option>
                        <option value="movements" <?php echo $report_type === 'movements' ? 'selected' : ''; ?>>Movements & Batches</option>
                        <option value="performance" <?php echo $report_type === 'performance' ? 'selected' : ''; ?>>User Performance</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary btn-sm w-100 shadow-sm">
                        <i class="fas fa-sync-alt me-2"></i>Update
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Report Sections -->
    <div class="row">
        <?php if ($report_type === 'summary'): ?>
            <!-- SUMMARY VIEW -->
            <div class="col-xl-8 col-lg-7">
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white border-0 py-3">
                        <h6 class="m-0 font-weight-bold text-primary">System Activity Trend</h6>
                    </div>
                    <div class="card-body">
                        <div class="chart-area" style="height: 320px;">
                            <canvas id="activityChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-4 col-lg-5">
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white border-0 py-3">
                        <h6 class="m-0 font-weight-bold text-primary">Inventory Status</h6>
                    </div>
                    <div class="card-body">
                        <div class="chart-pie pt-4 pb-2" style="height: 250px;">
                            <canvas id="statusPieChart"></canvas>
                        </div>
                        <div class="mt-4 text-center small">
                            <span class="mr-2"><i class="fas fa-circle text-success"></i> Available</span>
                            <span class="mr-2"><i class="fas fa-circle text-primary"></i> In Use</span>
                            <span class="mr-2"><i class="fas fa-circle text-warning"></i> Maintenance</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white border-0 py-3 d-flex align-items-center justify-content-between">
                        <h6 class="m-0 font-weight-bold text-primary">Low Stock Accessories</h6>
                        <a href="accessories.php" class="btn btn-sm btn-light text-primary fw-bold">View All</a>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="bg-light">
                                    <tr class="small text-muted">
                                        <th class="ps-4">Accessory</th>
                                        <th>In Stock</th>
                                        <th>Min. Stock</th>
                                        <th class="pe-4 text-end">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($statistics['low_stock_accessories'])): ?>
                                        <?php foreach ($statistics['low_stock_accessories'] as $acc): ?>
                                            <tr>
                                                <td class="ps-4">
                                                    <div class="fw-bold"><?php echo htmlspecialchars($acc['name']); ?></div>
                                                </td>
                                                <td><span class="badge bg-danger rounded-pill"><?php echo $acc['available_quantity']; ?></span></td>
                                                <td><?php echo $acc['minimum_stock']; ?></td>
                                                <td class="pe-4 text-end">
                                                    <a href="accessories.php?edit=<?php echo $acc['id']; ?>" class="btn btn-sm btn-outline-primary py-0 px-2">Update</a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="4" class="text-center py-4 text-muted small">No low stock alerts</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white border-0 py-3 d-flex align-items-center justify-content-between">
                        <h6 class="m-0 font-weight-bold text-primary">Top Performing Technicians</h6>
                        <span class="badge bg-primary-soft text-primary small">Top 5</span>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="bg-light">
                                    <tr class="small text-muted">
                                        <th class="ps-4">Technician</th>
                                        <th class="text-center">Movements</th>
                                        <th class="pe-4 text-end">Rating</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($statistics['top_technicians'])): ?>
                                        <?php foreach ($statistics['top_technicians'] as $tech): ?>
                                            <tr>
                                                <td class="ps-4">
                                                    <div class="d-flex align-items-center">
                                                        <div class="avatar-sm bg-light text-primary rounded-circle me-2 d-flex align-items-center justify-content-center" style="width: 32px; height: 32px;">
                                                            <?php echo strtoupper(substr($tech['username'], 0, 1)); ?>
                                                        </div>
                                                        <div class="fw-bold"><?php echo htmlspecialchars($tech['full_name'] ?: $tech['username']); ?></div>
                                                    </div>
                                                </td>
                                                <td class="text-center"><?php echo $tech['movement_count']; ?></td>
                                                <td class="pe-4 text-end">
                                                    <div class="text-warning">
                                                        <i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star-half-alt"></i>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="3" class="text-center py-4 text-muted small">No technician data</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

        <?php elseif ($report_type === 'inventory'): ?>
            <!-- INVENTORY VIEW -->
            <div class="col-12">
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white border-0 py-3">
                        <h6 class="m-0 font-weight-bold text-primary">Category Distribution</h6>
                    </div>
                    <div class="card-body">
                        <div class="chart-bar" style="height: 350px;">
                            <canvas id="categoryDistributionChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white border-0 py-3">
                        <h6 class="m-0 font-weight-bold text-primary">Items by Department</h6>
                    </div>
                    <div class="card-body">
                        <?php foreach ($statistics['items_by_department'] as $dept): ?>
                            <div class="mb-3">
                                <div class="d-flex justify-content-between mb-1 small">
                                    <span class="fw-bold"><?php echo htmlspecialchars($dept['department_name']); ?></span>
                                    <span><?php echo $dept['count']; ?> items</span>
                                </div>
                                <div class="progress" style="height: 8px;">
                                    <?php
                                    $percent = $statistics['total_items'] > 0 ? ($dept['count'] / $statistics['total_items'] * 100) : 0;
                                    ?>
                                    <div class="progress-bar bg-info" role="progressbar" style="width: <?php echo $percent; ?>%"></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white border-0 py-3">
                        <h6 class="m-0 font-weight-bold text-primary">Condition Report</h6>
                    </div>
                    <div class="card-body">
                        <div class="chart-pie pt-4 pb-2" style="height: 250px;">
                            <canvas id="conditionChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- New: Category Demand Heatmap -->
            <div class="col-12 mb-4">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white border-0 py-3 d-flex align-items-center justify-content-between">
                        <h6 class="m-0 font-weight-bold text-primary">Category Demand Heatmap (Requests per Category)</h6>
                        <span class="badge bg-info-soft text-info small">Movement Frequency</span>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="bg-light">
                                    <tr class="small text-muted text-uppercase">
                                        <th class="ps-4">Category</th>
                                        <th class="text-center">Total Requests</th>
                                        <th>Demand Level</th>
                                        <th class="pe-4 text-end">Trend</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($statistics['demand_by_category'])): ?>
                                        <tr>
                                            <td colspan="4" class="text-center py-5 text-muted">No request data available for categories</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php
                                        $max_requests = max(array_column($statistics['demand_by_category'], 'request_count'));
                                        foreach ($statistics['demand_by_category'] as $demand):
                                            $percentage = ($max_requests > 0) ? ($demand['request_count'] / $max_requests) * 100 : 0;
                                            $color = $percentage > 70 ? 'danger' : ($percentage > 30 ? 'warning' : 'success');
                                        ?>
                                            <tr>
                                                <td class="ps-4 font-weight-bold"><?php echo htmlspecialchars($demand['category'] ?: 'Uncategorized'); ?></td>
                                                <td class="text-center">
                                                    <span class="badge bg-light text-dark border"><?php echo $demand['request_count']; ?></span>
                                                </td>
                                                <td style="width: 30%;">
                                                    <div class="progress" style="height: 6px;">
                                                        <div class="progress-bar bg-<?php echo $color; ?>" role="progressbar" style="width: <?php echo $percentage; ?>%"></div>
                                                    </div>
                                                </td>
                                                <td class="pe-4 text-end">
                                                    <i class="fas fa-trending-up text-<?php echo $color; ?>"></i>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

        <?php elseif ($report_type === 'movements'): ?>
            <!-- MOVEMENTS VIEW -->
            <div class="col-md-7">
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white border-0 py-3">
                        <h6 class="m-0 font-weight-bold text-primary">Movement Type Distribution</h6>
                    </div>
                    <div class="card-body">
                        <div class="chart-pie pt-4 pb-2" style="height: 300px;">
                            <canvas id="movementTypeChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-5">
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white border-0 py-3">
                        <h6 class="m-0 font-weight-bold text-primary">Batch Approval Status</h6>
                    </div>
                    <div class="card-body">
                        <?php foreach ($statistics['batch_status'] as $status): ?>
                            <?php
                            $badgeClass = 'bg-secondary';
                            if ($status['status'] === 'approved') $badgeClass = 'bg-success';
                            if ($status['status'] === 'pending') $badgeClass = 'bg-warning text-dark';
                            if ($status['status'] === 'rejected') $badgeClass = 'bg-danger';
                            ?>
                            <div class="d-flex align-items-center justify-content-between mb-3 border-bottom pb-2">
                                <div class="d-flex align-items-center">
                                    <div class="p-2 rounded <?php echo $badgeClass; ?> bg-opacity-10 me-3">
                                        <i class="fas fa-file-invoice <?php echo str_replace('bg-', 'text-', $badgeClass); ?>"></i>
                                    </div>
                                    <span class="fw-bold text-capitalize"><?php echo $status['status']; ?></span>
                                </div>
                                <div class="h5 mb-0 fw-bold"><?php echo $status['count']; ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

        <?php elseif ($report_type === 'performance'): ?>
            <!-- PERFORMANCE VIEW -->
            <div class="col-12">
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white border-0 py-3">
                        <h6 class="m-0 font-weight-bold text-primary">Top 10 Most Scanned / Requested Items</h6>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="bg-light text-muted small">
                                    <tr>
                                        <th class="ps-4">Item Name</th>
                                        <th>Serial Number</th>
                                        <th class="text-center">Total Scans</th>
                                        <th class="pe-4 text-end">Last Scanned</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($statistics['top_scanned_items'] as $item): ?>
                                        <tr>
                                            <td class="ps-4 fw-bold"><?php echo htmlspecialchars($item['item_name']); ?></td>
                                            <td><code class="text-pink small"><?php echo htmlspecialchars($item['code']); ?></code></td>
                                            <td class="text-center">
                                                <span class="badge bg-primary rounded-pill px-3"><?php echo $item['scan_count']; ?></span>
                                            </td>
                                            <td class="pe-4 text-end text-muted small">Just now</td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <!-- Top Requested Items -->
                    <div class="col-lg-6 mb-4">
                        <div class="card shadow-sm border-0">
                            <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between bg-white">
                                <h6 class="m-0 font-weight-bold text-primary">Top Requested Items (Movements)</h6>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover mb-0">
                                        <thead class="bg-light">
                                            <tr>
                                                <th>Item Name</th>
                                                <th>Serial Number</th>
                                                <th class="text-center">Requests</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (empty($statistics['top_requested_items'])): ?>
                                                <tr>
                                                    <td colspan="3" class="text-center text-muted py-4">No request data found</td>
                                                </tr>
                                            <?php else: ?>
                                                <?php foreach ($statistics['top_requested_items'] as $item): ?>
                                                    <tr>
                                                        <td><?php echo htmlspecialchars($item['item_name']); ?></td>
                                                        <td><code class="text-primary"><?php echo htmlspecialchars($item['serial_number']); ?></code></td>
                                                        <td class="text-center">
                                                            <span class="badge bg-primary rounded-pill"><?php echo $item['request_count']; ?></span>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Top Search Terms -->
                    <div class="col-lg-12 mb-4">
                        <div class="card shadow-sm border-0">
                            <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between bg-white">
                                <h6 class="m-0 font-weight-bold text-primary">Search Behavior (Top Search Queries)</h6>
                            </div>
                            <div class="card-body">
                                <?php if (empty($statistics['top_search_terms'])): ?>
                                    <div class="text-center py-5">
                                        <i class="fas fa-search fa-3x text-gray-200 mb-3"></i>
                                        <p class="text-muted">No search data recorded yet. Searches are now being logged for analysis.</p>
                                    </div>
                                <?php else: ?>
                                    <div class="row">
                                        <?php foreach ($statistics['top_search_terms'] as $search): ?>
                                            <div class="col-md-4 mb-3">
                                                <div class="p-3 border rounded bg-light d-flex justify-content-between align-items-center">
                                                    <span class="text-dark font-weight-bold"><?php echo str_replace('Global search: ', '', htmlspecialchars($search['description'])); ?></span>
                                                    <span class="badge bg-info"><?php echo $search['search_count']; ?> searches</span>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <!-- Most Frequently Moved Items -->
                    <div class="col-lg-6 mb-4">
                        <div class="card shadow-sm border-0">
                            <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between bg-white">
                                <h6 class="m-0 font-weight-bold text-primary">Asset Circulation (Most Moved Items)</h6>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover mb-0">
                                        <thead class="bg-light">
                                            <tr>
                                                <th>Item Name</th>
                                                <th class="text-center">Movements</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (empty($statistics['top_moved_items'])): ?>
                                                <tr>
                                                    <td colspan="2" class="text-center text-muted py-4">No circulation data available</td>
                                                </tr>
                                            <?php else: ?>
                                                <?php foreach ($statistics['top_moved_items'] as $item): ?>
                                                    <tr>
                                                        <td>
                                                            <div class="font-weight-bold"><?php echo htmlspecialchars($item['item_name']); ?></div>
                                                            <small class="text-muted"><?php echo htmlspecialchars($item['serial_number']); ?></small>
                                                        </td>
                                                        <td class="text-center">
                                                            <span class="badge bg-success rounded-pill"><?php echo $item['movement_count']; ?></span>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>
</div>

<!-- Export Modal -->
<div class="modal fade" id="exportModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-download me-2"></i>Export Report
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    Your report is being prepared. This may take a moment...
                </div>
                <div class="progress">
                    <div class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 100%"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Shared Chart Configuration
        const chartOptions = {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    backgroundColor: 'rgba(255, 255, 255, 0.95)',
                    titleColor: '#1a2e3f',
                    bodyColor: '#4e73df',
                    borderColor: '#e3e6f0',
                    borderWidth: 1,
                    padding: 10,
                    displayColors: false,
                    callbacks: {
                        label: function(context) {
                            return 'Value: ' + (context.parsed.y !== undefined ? context.parsed.y : context.parsed);
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: {
                        color: 'rgba(0, 0, 0, 0.05)',
                        drawBorder: false
                    }
                },
                x: {
                    grid: {
                        display: false
                    }
                }
            }
        };

        <?php if ($report_type === 'summary'): ?>
            // Activity Trend Chart
            const activityCtx = document.getElementById('activityChart');
            if (activityCtx) {
                new Chart(activityCtx, {
                    type: 'line',
                    data: {
                        labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
                        datasets: [{
                            label: 'System Scans',
                            data: [12, 19, 3, 5, 2, 3, 15, 20, 10, 8, 12, <?php echo $statistics['recent_scans_count'] ?? 0; ?>],
                            borderColor: '#4e73df',
                            backgroundColor: 'rgba(78, 115, 223, 0.05)',
                            tension: 0.4,
                            fill: true,
                            pointRadius: 4,
                            pointBackgroundColor: '#4e73df'
                        }]
                    },
                    options: chartOptions
                });
            }

            // Status Pie Chart
            const statusCtx = document.getElementById('statusPieChart');
            if (statusCtx) {
                new Chart(statusCtx, {
                    type: 'doughnut',
                    data: {
                        labels: ['Available', 'In Use', 'Maintenance'],
                        datasets: [{
                            data: [
                                <?php echo $statistics['dashboard']['available'] ?? 0; ?>,
                                <?php echo $statistics['dashboard']['in_use'] ?? 0; ?>,
                                <?php echo $statistics['dashboard']['maintenance'] ?? 0; ?>
                            ],
                            backgroundColor: ['#1cc88a', '#4e73df', '#f6c23e'],
                            borderWidth: 0,
                            hoverOffset: 10
                        }]
                    },
                    options: {
                        ...chartOptions,
                        cutout: '75%',
                        plugins: {
                            legend: {
                                display: false
                            }
                        }
                    }
                });
            }
        <?php endif; ?>

        <?php if ($report_type === 'inventory'): ?>
            // Category Distribution Chart
            const categoryCtx = document.getElementById('categoryDistributionChart');
            if (categoryCtx) {
                new Chart(categoryCtx, {
                    type: 'bar',
                    data: {
                        labels: <?php echo json_encode(array_column($statistics['items_by_category'] ?? [], 'category_name')); ?>,
                        datasets: [{
                            label: 'Total Items',
                            data: <?php echo json_encode(array_column($statistics['items_by_category'] ?? [], 'count')); ?>,
                            backgroundColor: '#4e73df',
                            borderRadius: 8,
                            barThickness: 30
                        }]
                    },
                    options: chartOptions
                });
            }

            // Condition Chart
            const conditionCtx = document.getElementById('conditionChart');
            if (conditionCtx) {
                new Chart(conditionCtx, {
                    type: 'doughnut',
                    data: {
                        labels: ['Excellent', 'Good', 'Fair', 'Poor'],
                        datasets: [{
                            data: [45, 30, 15, 10],
                            backgroundColor: ['#1cc88a', '#36b9cc', '#f6c23e', '#e74a3b'],
                            borderWidth: 0
                        }]
                    },
                    options: {
                        ...chartOptions,
                        cutout: '70%'
                    }
                });
            }
        <?php endif; ?>

        <?php if ($report_type === 'movements'): ?>
            // Movement Type Distribution
            const movementCtx = document.getElementById('movementTypeChart');
            if (movementCtx) {
                new Chart(movementCtx, {
                    type: 'pie',
                    data: {
                        labels: <?php echo json_encode(array_column($statistics['movement_types'] ?? [], 'movement_type')); ?>,
                        datasets: [{
                            data: <?php echo json_encode(array_column($statistics['movement_types'] ?? [], 'count')); ?>,
                            backgroundColor: ['#4e73df', '#1cc88a', '#36b9cc', '#f6c23e', '#e74a3b', '#858796'],
                            borderWidth: 0,
                            hoverOffset: 15
                        }]
                    },
                    options: {
                        ...chartOptions,
                        plugins: {
                            legend: {
                                display: true,
                                position: 'bottom'
                            }
                        }
                    }
                });
            }
        <?php endif; ?>
    });

    // Export Functions
    function exportToExcel() {
        window.print();
    }

    function exportToPDF() {
        const element = document.querySelector('.container-fluid');
        const opt = {
            margin: [0.3, 0.3],
            filename: 'aBility_Analytics_Report_<?php echo date('Y-m-d'); ?>.pdf',
            image: {
                type: 'jpeg',
                quality: 0.98
            },
            html2canvas: {
                scale: 2,
                useCORS: true,
                logging: false
            },
            jsPDF: {
                unit: 'in',
                format: 'a4',
                orientation: 'landscape'
            }
        };

        // Add loading state
        const btn = event.currentTarget;
        const originalHtml = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Preparing Report...';
        btn.disabled = true;

        html2pdf().set(opt).from(element).save().then(() => {
            btn.innerHTML = originalHtml;
            btn.disabled = false;
        });
    }
</script>

<style>
    :root {
        --primary-soft: rgba(78, 115, 223, 0.1);
        --ability-dark: #1a2e3f;
    }

    body {
        background-color: #f8f9fc;
        font-family: 'Inter', system-ui, -apple-system, sans-serif;
    }

    .bg-primary-soft {
        background-color: var(--primary-soft);
    }

    .card {
        border-radius: 12px;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .card:hover {
        transform: translateY(-3px);
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.08) !important;
    }

    .uppercase {
        text-transform: uppercase;
        letter-spacing: 0.8px;
        font-size: 0.65rem;
    }

    .font-weight-bold {
        font-weight: 700;
    }

    .text-xs {
        font-size: .75rem;
    }

    .avatar-sm {
        width: 32px;
        height: 32px;
        font-size: 0.8rem;
        font-weight: 700;
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
    }

    .table {
        font-size: 0.85rem;
    }

    .table thead th {
        font-size: 0.7rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        border-bottom: 1px solid #eef2f7;
        color: #858796;
        background-color: #f8f9fc;
        padding: 12px 16px;
    }

    .table tbody td {
        padding: 12px 16px;
        border-bottom: 1px solid #f8f9fc;
    }

    .progress {
        background-color: #eaecf4;
        border-radius: 10px;
        overflow: hidden;
    }

    .progress-bar {
        background: linear-gradient(90deg, #4e73df, #224abe);
    }

    /* Form Styling */
    .form-control-sm,
    .form-select-sm {
        border-radius: 8px;
        padding: 0.5rem 0.75rem;
    }

    /* Print Optimization */
    @media print {

        .no-print,
        .btn,
        form,
        .vr,
        .page-header-unified {
            display: none !important;
        }

        .container-fluid {
            padding: 0;
            background: white;
        }

        .card {
            box-shadow: none !important;
            border: 1px solid #e3e6f0 !important;
            break-inside: avoid;
        }

        h1 {
            color: black !important;
        }
    }
</style>

<?php require_once 'views/partials/footer.php'; ?>