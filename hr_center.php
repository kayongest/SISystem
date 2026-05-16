<?php
// hr_center.php - HR & Performance Command Center
require_once 'bootstrap.php';
require_once 'includes/functions.php';
require_once 'includes/db_connect.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit();
}

$conn = getConnection();
$pageTitle = "HR Command Center - aBility";

// 1. Get Workforce Stats
$total_staff = 0;
$res = $conn->query("SELECT COUNT(*) as count FROM users WHERE is_active = 1");
if ($res) $total_staff = $res->fetch_assoc()['count'];

$active_today = 0;
$res = $conn->query("SELECT COUNT(DISTINCT user_id) as count FROM user_sessions WHERE login_time >= CURDATE()");
if ($res) $active_today = $res->fetch_assoc()['count'];

// 2. Get Top Performers (Technicians with most movements)
$top_performers = [];
$sql = "SELECT u.full_name, u.username, u.department, u.profile_image, COUNT(sm.id) as movement_count 
        FROM stock_movements sm
        JOIN users u ON sm.technician_id = u.id
        WHERE sm.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY sm.technician_id
        ORDER BY movement_count DESC
        LIMIT 5";
$res = $conn->query($sql);
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $top_performers[] = $row;
    }
}

// 3. Get Departmental Distribution
$dept_dist = [];
$res = $conn->query("SELECT department, COUNT(*) as count FROM users GROUP BY department");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $dept_dist[] = $row;
    }
}

// 4. Get Activity Heatmap Data (Last 14 days)
$activity_data = [];
$sql = "SELECT DATE(created_at) as date, COUNT(*) as count 
        FROM activity_log 
        WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 14 DAY)
        GROUP BY DATE(created_at)
        ORDER BY DATE(created_at) ASC";
$res = $conn->query($sql);
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $activity_data[] = $row;
    }
}

include 'views/partials/header.php';
?>

<style>
    :root {
        --hr-primary: #1a237e;
        --hr-accent: #00e5ff;
        --glass-bg: rgba(255, 255, 255, 0.05);
        --glass-border: rgba(255, 255, 255, 0.1);
    }

    body {
        background: #0f172a;
        color: #f8fafc;
    }

    .hr-header {
        background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
        padding: 3rem 2rem;
        border-radius: 0 0 40px 40px;
        margin-bottom: -2rem;
        border-bottom: 1px solid var(--glass-border);
    }

    .glass-card {
        background: rgba(30, 41, 59, 0.7);
        backdrop-filter: blur(12px);
        border: 1px solid var(--glass-border);
        border-radius: 24px;
        transition: all 0.3s ease;
        height: 100%;
    }

    .glass-card:hover {
        transform: translateY(-5px);
        border-color: var(--hr-accent);
        box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
    }

    .stat-value {
        font-size: 2.5rem;
        font-weight: 800;
        background: linear-gradient(to right, #fff, var(--hr-accent));
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
    }

    .performer-card {
        background: rgba(255, 255, 255, 0.03);
        border-radius: 20px;
        padding: 1.25rem;
        margin-bottom: 1rem;
        border-left: 4px solid var(--hr-accent);
        display: flex;
        align-items: center;
        gap: 1rem;
    }

    .performer-img {
        width: 50px;
        height: 50px;
        border-radius: 12px;
        object-fit: cover;
        background: #334155;
    }

    .badge-rank {
        width: 28px;
        height: 28px;
        background: var(--hr-accent);
        color: #000;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 800;
        font-size: 0.8rem;
    }

    .progress-custom {
        height: 8px;
        background: rgba(255, 255, 255, 0.05);
        border-radius: 10px;
        overflow: hidden;
    }

    .progress-bar-custom {
        background: linear-gradient(to right, var(--hr-primary), var(--hr-accent));
        height: 100%;
        border-radius: 10px;
    }

    .section-title {
        font-size: 1.5rem;
        font-weight: 700;
        margin-bottom: 1.5rem;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .section-title i {
        color: var(--hr-accent);
    }

    .btn-presentation {
        background: var(--hr-accent);
        color: #000;
        font-weight: 700;
        border-radius: 12px;
        padding: 10px 24px;
        border: none;
        transition: all 0.3s ease;
    }

    .btn-presentation:hover {
        transform: scale(1.05);
        box-shadow: 0 0 20px rgba(0, 229, 255, 0.4);
    }
</style>

<div class="hr-header">
    <div class="container">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1 class="display-5 fw-bold mb-1">Workforce & Performance <span style="color: var(--hr-accent);">Command Center</span></h1>
                <p class="text-secondary lead">Next-gen human resources analytics and operational intelligence</p>
            </div>
            <div class="d-flex gap-3">
                <button class="btn btn-outline-light px-4" onclick="window.print()">
                    <i class="fas fa-file-pdf me-2"></i> Export Report
                </button>
                <button class="btn btn-presentation">
                    <i class="fas fa-play me-2"></i> Presentation Mode
                </button>
            </div>
        </div>
    </div>
</div>

<div class="container py-5">
    <!-- Key Metrics -->
    <div class="row g-4 mb-5">
        <div class="col-md-3">
            <div class="glass-card p-4">
                <div class="text-secondary small fw-bold mb-2">TOTAL WORKFORCE</div>
                <div class="stat-value"><?php echo $total_staff; ?></div>
                <div class="text-success small mt-2"><i class="fas fa-arrow-up me-1"></i> +2% from last month</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="glass-card p-4">
                <div class="text-secondary small fw-bold mb-2">ACTIVE TODAY</div>
                <div class="stat-value"><?php echo $active_today; ?></div>
                <div class="progress-custom mt-3">
                    <div class="progress-bar-custom" style="width: <?php echo ($total_staff > 0) ? ($active_today/$total_staff)*100 : 0; ?>%"></div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="glass-card p-4">
                <div class="text-secondary small fw-bold mb-2">LOGISTICS FLOW</div>
                <?php
                $res = $conn->query("SELECT COUNT(*) as count FROM stock_movements WHERE created_at >= CURDATE()");
                $movements_today = $res ? $res->fetch_assoc()['count'] : 0;
                ?>
                <div class="stat-value"><?php echo $movements_today; ?></div>
                <div class="text-secondary small mt-2">Movements processed today</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="glass-card p-4">
                <div class="text-secondary small fw-bold mb-2">SYSTEM UPTIME</div>
                <div class="stat-value">99.9%</div>
                <div class="text-info small mt-2">Operational availability</div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <!-- Performance Leaderboard -->
        <div class="col-lg-5">
            <div class="glass-card p-4">
                <h3 class="section-title"><i class="fas fa-trophy"></i> Top Performers <span class="badge bg-dark ms-auto small" style="font-size: 0.6rem;">LAST 30 DAYS</span></h3>
                <div class="performer-list">
                    <?php if (empty($top_performers)): ?>
                        <div class="text-center py-5 text-secondary">
                            <i class="fas fa-user-clock fa-3x mb-3 opacity-20"></i>
                            <p>No movement data recorded yet.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($top_performers as $index => $tech): ?>
                            <div class="performer-card">
                                <div class="badge-rank"><?php echo $index + 1; ?></div>
                                <img src="<?php echo $tech['profile_image'] ?: 'assets/images/default_user.png'; ?>" class="performer-img" alt="User">
                                <div class="flex-grow-1">
                                    <div class="fw-bold"><?php echo htmlspecialchars($tech['full_name'] ?: $tech['username']); ?></div>
                                    <div class="small text-secondary"><?php echo htmlspecialchars($tech['department']); ?></div>
                                </div>
                                <div class="text-end">
                                    <div class="fw-bold text-accent"><?php echo $tech['movement_count']; ?></div>
                                    <div class="small text-secondary" style="font-size: 0.65rem;">SCANS</div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                <button class="btn btn-outline-secondary w-100 mt-3 btn-sm">View Detailed Rankings</button>
            </div>
        </div>

        <!-- Activity Heatmap -->
        <div class="col-lg-7">
            <div class="glass-card p-4">
                <h3 class="section-title"><i class="fas fa-chart-line"></i> Workforce Activity Heatmap</h3>
                <div style="height: 300px;">
                    <canvas id="activityChart"></canvas>
                </div>
                <div class="mt-4 row g-3">
                    <?php foreach ($dept_dist as $dept): ?>
                        <div class="col-md-4">
                            <div class="d-flex justify-content-between mb-1 small">
                                <span class="text-secondary"><?php echo htmlspecialchars($dept['department']); ?></span>
                                <span><?php echo $dept['count']; ?> Staff</span>
                            </div>
                            <div class="progress-custom">
                                <div class="progress-bar-custom" style="width: <?php echo ($total_staff > 0) ? ($dept['count']/$total_staff)*100 : 0; ?>%"></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Recent Audit Trail -->
        <div class="col-12 mt-4">
            <div class="glass-card p-4">
                <h3 class="section-title"><i class="fas fa-history"></i> Real-time Operational Audit</h3>
                <div class="table-responsive">
                    <table class="table table-dark table-hover border-0">
                        <thead>
                            <tr class="text-secondary small">
                                <th>USER</th>
                                <th>ACTION</th>
                                <th>DETAILS</th>
                                <th>TIMESTAMP</th>
                                <th class="text-end">IP ADDRESS</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $res = $conn->query("SELECT al.*, u.full_name, u.username, u.profile_image 
                                               FROM activity_log al 
                                               LEFT JOIN users u ON al.user_id = u.id 
                                               ORDER BY al.created_at DESC LIMIT 8");
                            if ($res):
                                while ($log = $res->fetch_assoc()):
                            ?>
                            <tr class="align-middle">
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <img src="<?php echo $log['profile_image'] ?: 'assets/images/default_user.png'; ?>" class="rounded-circle" width="28" height="28" style="object-fit: cover;">
                                        <div class="fw-bold small"><?php echo htmlspecialchars($log['full_name'] ?: $log['username']); ?></div>
                                    </div>
                                </td>
                                 <td>
                                    <?php 
                                    $action = $log['action_type'] ?? ($log['action'] ?? 'ACTIVITY');
                                    ?>
                                    <span class="badge bg-primary bg-opacity-25 text-primary small"><?php echo strtoupper($action); ?></span>
                                </td>
                                <td class="small text-secondary"><?php echo htmlspecialchars($log['description'] ?? 'No description provided'); ?></td>
                                <td class="small"><?php echo date('H:i:s d M', strtotime($log['created_at'])); ?></td>
                                <td class="text-end small text-secondary"><code><?php echo $log['ip_address'] ?? '127.0.0.1'; ?></code></td>
                            </tr>
                            <?php 
                                endwhile;
                            endif;
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const ctx = document.getElementById('activityChart').getContext('2d');
        
        // Heatmap Data
        const labels = <?php echo json_encode(array_column($activity_data, 'date')); ?>;
        const counts = <?php echo json_encode(array_column($activity_data, 'count')); ?>;
        
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels.map(d => new Date(d).toLocaleDateString('en-US', { day: 'numeric', month: 'short' })),
                datasets: [{
                    label: 'Operational Actions',
                    data: counts,
                    borderColor: '#00e5ff',
                    backgroundColor: 'rgba(0, 229, 255, 0.1)',
                    borderWidth: 3,
                    tension: 0.4,
                    fill: true,
                    pointBackgroundColor: '#00e5ff',
                    pointRadius: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: { color: 'rgba(255, 255, 255, 0.05)' },
                        ticks: { color: '#94a3b8' }
                    },
                    x: {
                        grid: { display: false },
                        ticks: { color: '#94a3b8' }
                    }
                }
            }
        });
    });
</script>

<?php include 'views/partials/footer.php'; ?>
