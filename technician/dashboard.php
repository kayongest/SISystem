<?php
require_once 'includes/auth.php';
requireTechnicianLogin();
require_once '../includes/config.php';

// Get technician details
$technician_id = getTechnicianId();
$technician_name = getTechnicianName();

// Get real-time equipment status distribution
$statusQuery = $pdo->query("
    SELECT 
        status,
        COUNT(*) as count
    FROM equipment 
    GROUP BY status 
    ORDER BY 
        FIELD(status, 'available', 'in_use', 'maintenance', 'reserved', 'damaged', 'lost')
");
$statusData = $statusQuery->fetchAll();

// Calculate total equipment and percentages
$totalEquipment = 0;
foreach ($statusData as $row) {
    $totalEquipment += $row['count'];
}

// Calculate percentages
foreach ($statusData as &$row) {
    $row['percentage'] = $totalEquipment > 0 ? round(($row['count'] / $totalEquipment) * 100, 1) : 0;
}

// Get recent equipment
$recentQuery = $pdo->query("
    SELECT id, name, serial_number, status, updated_at 
    FROM equipment 
    ORDER BY updated_at DESC 
    LIMIT 10
");
$recentEquipment = $recentQuery->fetchAll();

// Get total equipment statistics
$statsQuery = $pdo->query("
    SELECT 
        COUNT(*) as total_equipment,
        SUM(CASE WHEN status = 'available' THEN 1 ELSE 0 END) as available_count,
        SUM(CASE WHEN status = 'in_use' THEN 1 ELSE 0 END) as in_use_count,
        SUM(CASE WHEN status = 'maintenance' THEN 1 ELSE 0 END) as maintenance_count,
        SUM(CASE WHEN status = 'reserved' THEN 1 ELSE 0 END) as reserved_count,
        SUM(CASE WHEN status = 'damaged' THEN 1 ELSE 0 END) as damaged_count,
        SUM(CASE WHEN status = 'lost' THEN 1 ELSE 0 END) as lost_count,
        COALESCE(SUM(value), 0) as total_value,
        COALESCE(ROUND(AVG(value), 2), 0) as avg_value,
        COUNT(DISTINCT category) as categories_count,
        COUNT(DISTINCT stock_location) as locations_count
    FROM equipment
");
$stats = $statsQuery->fetch();

// Default values if no data
if (!$stats) {
    $stats = [
        'total_equipment' => 0,
        'available_count' => 0,
        'in_use_count' => 0,
        'maintenance_count' => 0,
        'reserved_count' => 0,
        'damaged_count' => 0,
        'lost_count' => 0,
        'total_value' => 0,
        'avg_value' => 0,
        'categories_count' => 0,
        'locations_count' => 0
    ];
}

// Get category distribution
$categoryQuery = $pdo->query("
    SELECT 
        category,
        COUNT(*) as count
    FROM equipment 
    WHERE category IS NOT NULL AND category != ''
    GROUP BY category 
    ORDER BY count DESC
    LIMIT 10
");
$categories = $categoryQuery->fetchAll();

// Get maintenance needed equipment
$maintenanceQuery = $pdo->query("
    SELECT id, name, serial_number, status, warranty_until 
    FROM equipment 
    WHERE status IN ('maintenance', 'damaged')
    OR (warranty_until IS NOT NULL AND warranty_until < CURDATE())
    ORDER BY warranty_until ASC
    LIMIT 5
");
$maintenanceItems = $maintenanceQuery->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Equipment Dashboard - Technician Portal</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #4361ee;
            --secondary-color: #3a0ca3;
            --success-color: #28a745;
            --info-color: #17a2b8;
            --warning-color: #ffc107;
            --danger-color: #dc3545;
        }

        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #e4e8f0 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .dashboard-container {
            max-width: 1400px;
            margin: 0 auto;
        }

        .navbar-custom {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            box-shadow: 0 2px 15px rgba(0, 0, 0, 0.1);
        }

        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
            transition: transform 0.3s, box-shadow 0.3s;
            border-top: 4px solid var(--primary-color);
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.12);
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            margin-bottom: 15px;
        }

        .stat-value {
            font-size: 2.5rem;
            font-weight: 700;
            line-height: 1;
            margin-bottom: 5px;
        }

        .stat-label {
            font-size: 1rem;
            color: #6c757d;
            font-weight: 500;
            margin-bottom: 8px;
        }

        .stat-change {
            font-size: 0.9rem;
            font-weight: 500;
        }

        .change-positive {
            color: var(--success-color);
        }

        .change-negative {
            color: var(--danger-color);
        }

        .chart-container {
            background: white;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
            height: 100%;
        }

        .chart-title {
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 20px;
            color: #333;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .table-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
            height: 100%;
        }

        .status-badge {
            padding: 6px 15px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .badge-available {
            background-color: rgba(40, 167, 69, 0.15);
            color: var(--success-color);
        }

        .badge-in_use {
            background-color: rgba(0, 123, 255, 0.15);
            color: #007bff;
        }

        .badge-maintenance {
            background-color: rgba(255, 193, 7, 0.15);
            color: var(--warning-color);
        }

        .badge-reserved {
            background-color: rgba(111, 66, 193, 0.15);
            color: #6f42c1;
        }

        .badge-damaged {
            background-color: rgba(253, 126, 20, 0.15);
            color: #fd7e14;
        }

        .badge-lost {
            background-color: rgba(220, 53, 69, 0.15);
            color: var(--danger-color);
        }

        .page-title {
            color: white;
            font-weight: 700;
            margin: 0;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
            color: white;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            background: rgba(255, 255, 255, 0.2);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 1.1rem;
        }

        .quick-actions {
            margin-top: 30px;
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }

        .quick-action-btn {
            padding: 12px 25px;
            border-radius: 10px;
            border: none;
            font-weight: 600;
            font-size: 1rem;
            display: flex;
            align-items: center;
            gap: 10px;
            transition: all 0.3s;
            text-decoration: none !important;
        }

        .quick-action-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 7px 15px rgba(0, 0, 0, 0.1);
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn-success {
            background: var(--success-color);
            color: white;
        }

        .btn-warning {
            background: var(--warning-color);
            color: white;
        }

        .table th {
            border-top: none;
            font-weight: 600;
            color: #6c757d;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 2px solid #dee2e6;
        }

        .table td {
            vertical-align: middle;
            padding: 15px 10px;
        }

        .progress {
            height: 8px;
            border-radius: 4px;
        }

        .refresh-btn {
            position: absolute;
            top: 20px;
            right: 20px;
            background: var(--primary-color);
            color: white;
            border: none;
            width: 40px;
            height: 40px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s;
        }

        .refresh-btn:hover {
            background: var(--secondary-color);
            transform: rotate(90deg);
        }

        @media (max-width: 768px) {
            .stat-value {
                font-size: 2rem;
            }

            .quick-actions {
                flex-direction: column;
            }

            .quick-action-btn {
                justify-content: center;
            }
        }
    </style>
</head>

<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-custom navbar-dark mb-4">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center" href="#">
                <i class="fas fa-tachometer-alt me-2"></i>
                <span class="page-title">Equipment Dashboard</span>
            </a>
            <div class="d-flex align-items-center">
                <div class="user-info">
                    <div class="user-avatar">
                        <?php echo strtoupper(substr($technician_name, 0, 1)); ?>
                    </div>
                    <div>
                        <div style="font-weight: 600;"><?php echo htmlspecialchars($technician_name); ?></div>
                        <small style="opacity: 0.8;">Technician</small>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="container dashboard-container">
        <!-- Quick Actions -->
        <div class="quick-actions">
            <a href="scanner.php" class="quick-action-btn btn-primary">
                <i class="fas fa-qrcode"></i> Scan Equipment
            </a>
            <a href="equipment_list.php" class="quick-action-btn btn-secondary">
                <i class="fas fa-list"></i> View All Equipment
            </a>
            <a href="reports.php" class="quick-action-btn btn-secondary">
                <i class="fas fa-chart-bar"></i> Generate Reports
            </a>
            <button class="quick-action-btn btn-success" onclick="refreshDashboard()">
                <i class="fas fa-sync-alt"></i> Refresh Dashboard
            </button>
            <a href="logout.php" class="quick-action-btn btn-warning ms-auto">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </div>

        <!-- Key Statistics -->
        <div class="row mt-4">
            <div class="col-xl-3 col-lg-6">
                <div class="stat-card">
                    <div class="stat-icon" style="background: rgba(67, 97, 238, 0.1); color: var(--primary-color);">
                        <i class="fas fa-tools"></i>
                    </div>
                    <div class="stat-value"><?php echo $stats['total_equipment']; ?></div>
                    <div class="stat-label">Total Equipment</div>
                    <div class="stat-change change-positive">
                        <i class="fas fa-layer-group me-1"></i> <?php echo $stats['categories_count']; ?> categories
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-lg-6">
                <div class="stat-card">
                    <div class="stat-icon" style="background: rgba(40, 167, 69, 0.1); color: var(--success-color);">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-value"><?php echo $stats['available_count']; ?></div>
                    <div class="stat-label">Available Now</div>
                    <div class="stat-change change-positive">
                        <?php
                        if ($stats['total_equipment'] > 0) {
                            echo round(($stats['available_count'] / $stats['total_equipment']) * 100, 1);
                        } else {
                            echo '0';
                        }
                        ?>% of total
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-lg-6">
                <div class="stat-card">
                    <div class="stat-icon" style="background: rgba(255, 193, 7, 0.1); color: var(--warning-color);">
                        <i class="fas fa-wrench"></i>
                    </div>
                    <div class="stat-value"><?php echo $stats['maintenance_count'] + $stats['damaged_count']; ?></div>
                    <div class="stat-label">Needs Attention</div>
                    <div class="stat-change change-negative">
                        <?php
                        $attentionCount = $stats['maintenance_count'] + $stats['damaged_count'];
                        if ($stats['total_equipment'] > 0) {
                            echo round(($attentionCount / $stats['total_equipment']) * 100, 1);
                        } else {
                            echo '0';
                        }
                        ?>% of total
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-lg-6">
                <div class="stat-card">
                    <div class="stat-icon" style="background: rgba(111, 66, 193, 0.1); color: #6f42c1;">
                        <i class="fas fa-dollar-sign"></i>
                    </div>
                    <div class="stat-value">$<?php echo number_format($stats['total_value'], 2); ?></div>
                    <div class="stat-label">Total Value</div>
                    <div class="stat-change change-positive">
                        Avg: $<?php echo number_format($stats['avg_value'], 2); ?> per item
                    </div>
                </div>
            </div>
        </div>

        <div class="row mt-4">
            <!-- Equipment Status Distribution Chart -->
            <div class="col-xl-8">
                <div class="chart-container">
                    <div class="chart-title">
                        <i class="fas fa-chart-pie"></i> Equipment Status Distribution
                        <button class="refresh-btn" onclick="refreshChart()">
                            <i class="fas fa-sync-alt"></i>
                        </button>
                    </div>
                    <div style="position: relative; height: 300px;">
                        <canvas id="statusChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Status Breakdown -->
            <div class="col-xl-4">
                <div class="table-card">
                    <div class="chart-title">
                        <i class="fas fa-list-ol"></i> Status Breakdown
                    </div>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Status</th>
                                    <th>Count</th>
                                    <th>%</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($statusData)): ?>
                                    <tr>
                                        <td colspan="3" class="text-center text-muted py-4">
                                            <i class="fas fa-box-open fa-2x mb-3"></i>
                                            <div>No equipment found</div>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($statusData as $row):
                                        $statusClass = 'badge-' . $row['status'];
                                        $statusLabel = ucfirst(str_replace('_', ' ', $row['status']));
                                    ?>
                                        <tr>
                                            <td>
                                                <span class="status-badge <?php echo $statusClass; ?>">
                                                    <?php
                                                    $icons = [
                                                        'available' => 'fa-check-circle',
                                                        'in_use' => 'fa-user-check',
                                                        'maintenance' => 'fa-wrench',
                                                        'reserved' => 'fa-clock',
                                                        'damaged' => 'fa-exclamation-triangle',
                                                        'lost' => 'fa-search'
                                                    ];
                                                    $icon = isset($icons[$row['status']]) ? $icons[$row['status']] : 'fa-box';
                                                    ?>
                                                    <i class="fas <?php echo $icon; ?>"></i>
                                                    <?php echo $statusLabel; ?>
                                                </span>
                                            </td>
                                            <td><strong><?php echo $row['count']; ?></strong></td>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="progress flex-grow-1 me-2">
                                                        <div class="progress-bar"
                                                            role="progressbar"
                                                            style="width: <?php echo $row['percentage']; ?>%; 
                                                            background-color: <?php echo getStatusColor($row['status']); ?>">
                                                        </div>
                                                    </div>
                                                    <span style="min-width: 40px;"><?php echo $row['percentage']; ?>%</span>
                                                </div>
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

        <div class="row mt-4">
            <!-- Recent Equipment Activity -->
            <div class="col-xl-6">
                <div class="table-card">
                    <div class="chart-title">
                        <i class="fas fa-history"></i> Recent Equipment Activity
                    </div>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Equipment</th>
                                    <th>Serial</th>
                                    <th>Status</th>
                                    <th>Updated</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($recentEquipment)): ?>
                                    <tr>
                                        <td colspan="4" class="text-center text-muted py-4">
                                            <i class="fas fa-clock fa-2x mb-3"></i>
                                            <div>No recent activity</div>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($recentEquipment as $item):
                                        $statusClass = 'badge-' . $item['status'];
                                        $statusLabel = ucfirst(str_replace('_', ' ', $item['status']));
                                    ?>
                                        <tr>
                                            <td>
                                                <div class="fw-bold"><?php echo htmlspecialchars($item['name']); ?></div>
                                                <small class="text-muted">ID: <?php echo $item['id']; ?></small>
                                            </td>
                                            <td><code><?php echo htmlspecialchars($item['serial_number']); ?></code></td>
                                            <td>
                                                <span class="status-badge <?php echo $statusClass; ?>">
                                                    <?php echo $statusLabel; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php echo date('M d, H:i', strtotime($item['updated_at'])); ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Maintenance Required -->
            <div class="col-xl-6">
                <div class="table-card">
                    <div class="chart-title">
                        <i class="fas fa-exclamation-triangle text-warning"></i> Maintenance Required
                    </div>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Equipment</th>
                                    <th>Status</th>
                                    <th>Warranty</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($maintenanceItems)): ?>
                                    <tr>
                                        <td colspan="4" class="text-center text-muted py-4">
                                            <i class="fas fa-check-circle fa-2x mb-3 text-success"></i>
                                            <div>No maintenance required</div>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($maintenanceItems as $item):
                                        $statusClass = 'badge-' . $item['status'];
                                        $statusLabel = ucfirst(str_replace('_', ' ', $item['status']));
                                        $warrantyStatus = $item['warranty_until'] && strtotime($item['warranty_until']) < time() ? 'expired' : 'active';
                                    ?>
                                        <tr>
                                            <td>
                                                <div class="fw-bold"><?php echo htmlspecialchars($item['name']); ?></div>
                                                <small class="text-muted"><?php echo htmlspecialchars($item['serial_number']); ?></small>
                                            </td>
                                            <td>
                                                <span class="status-badge <?php echo $statusClass; ?>">
                                                    <?php echo $statusLabel; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if ($item['warranty_until']):
                                                    $warrantyDate = date('M d, Y', strtotime($item['warranty_until']));
                                                    if ($warrantyStatus == 'expired'): ?>
                                                        <span class="badge bg-danger">
                                                            <i class="fas fa-calendar-times me-1"></i> Expired
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="badge bg-success">
                                                            <i class="fas fa-calendar-check me-1"></i> <?php echo $warrantyDate; ?>
                                                        </span>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">No warranty</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <button class="btn btn-sm btn-outline-primary" onclick="viewItem(<?php echo $item['id']; ?>)">
                                                    <i class="fas fa-eye"></i> View
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
        </div>
    </div>

    <script>
        // Get status colors function (PHP equivalent)
        function getStatusColor(status) {
            const colors = {
                'available': '#28a745',
                'in_use': '#007bff',
                'maintenance': '#ffc107',
                'reserved': '#6f42c1',
                'damaged': '#fd7e14',
                'lost': '#dc3545'
            };
            return colors[status] || '#6c757d';
        }

        // Initialize Status Chart
        const statusChartCtx = document.getElementById('statusChart').getContext('2d');

        <?php if (!empty($statusData)): ?>
            const statusChart = new Chart(statusChartCtx, {
                type: 'doughnut',
                data: {
                    labels: [
                        <?php foreach ($statusData as $row): ?> '<?php echo ucfirst(str_replace('_', ' ', $row['status'])); ?>',
                        <?php endforeach; ?>
                    ],
                    datasets: [{
                        data: [
                            <?php foreach ($statusData as $row): ?>
                                <?php echo $row['count']; ?>,
                            <?php endforeach; ?>
                        ],
                        backgroundColor: [
                            <?php foreach ($statusData as $row): ?>
                                getStatusColor('<?php echo $row['status']; ?>'),
                            <?php endforeach; ?>
                        ],
                        borderWidth: 2,
                        borderColor: '#fff'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'right',
                            labels: {
                                padding: 15,
                                usePointStyle: true,
                                font: {
                                    size: 11
                                }
                            }
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const label = context.label || '';
                                    const value = context.raw || 0;
                                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    const percentage = Math.round((value / total) * 100);
                                    return `${label}: ${value} (${percentage}%)`;
                                }
                            }
                        }
                    },
                    cutout: '60%'
                }
            });
        <?php else: ?>
            // Show empty state for chart
            const emptyChartText = "No equipment data available";
            statusChartCtx.fillStyle = '#6c757d';
            statusChartCtx.font = '16px Arial';
            statusChartCtx.textAlign = 'center';
            statusChartCtx.fillText(emptyChartText, statusChartCtx.canvas.width / 2, statusChartCtx.canvas.height / 2);
        <?php endif; ?>

        // Refresh Functions
        function refreshDashboard() {
            location.reload();
        }

        function refreshChart() {
            const btn = event.target.closest('.refresh-btn');
            if (btn) {
                btn.style.transform = 'rotate(180deg)';
            }
            setTimeout(() => {
                location.reload();
            }, 300);
        }

        function viewItem(itemId) {
            window.location.href = 'equipment_view.php?id=' + itemId;
        }

        // Auto-refresh every 5 minutes
        setTimeout(() => {
            refreshDashboard();
        }, 5 * 60 * 1000);

        // Add dynamic progress bar colors
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.progress-bar').forEach(bar => {
                const status = bar.closest('tr').querySelector('.status-badge').className.match(/badge-(\w+)/)[1];
                bar.style.backgroundColor = getStatusColor(status);
            });
        });
    </script>
</body>

</html>

<?php
// Helper function to get status color
function getStatusColor($status)
{
    $colors = [
        'available' => '#28a745',
        'in_use' => '#007bff',
        'maintenance' => '#ffc107',
        'reserved' => '#6f42c1',
        'damaged' => '#fd7e14',
        'lost' => '#dc3545'
    ];
    return $colors[$status] ?? '#6c757d';
}
?>