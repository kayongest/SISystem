<?php
// content/dashboard_content.php
?>
<div class="container-fluid p-0">
    <div class="row mb-4">
        <div class="col-12">
            <h3>Dashboard Overview</h3>
            <p class="text-muted">Welcome to your dashboard! Here's what's happening today.</p>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="row g-4 mb-4">
        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <div class="rounded-circle p-3" style="background: <?php echo $current_role_category['bg_light']; ?>">
                                <i class="fas fa-boxes fa-2x" style="color: <?php echo $current_role_category['color']; ?>"></i>
                            </div>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h6 class="mb-1">Total Equipment</h6>
                            <h3 class="mb-0">156</h3>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <div class="rounded-circle p-3" style="background: <?php echo $current_role_category['bg_light']; ?>">
                                <i class="fas fa-qrcode fa-2x" style="color: <?php echo $current_role_category['color']; ?>"></i>
                            </div>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h6 class="mb-1">Scans Today</h6>
                            <h3 class="mb-0">42</h3>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <div class="rounded-circle p-3" style="background: <?php echo $current_role_category['bg_light']; ?>">
                                <i class="fas fa-users fa-2x" style="color: <?php echo $current_role_category['color']; ?>"></i>
                            </div>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h6 class="mb-1">Active Users</h6>
                            <h3 class="mb-0">24</h3>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <div class="rounded-circle p-3" style="background: <?php echo $current_role_category['bg_light']; ?>">
                                <i class="fas fa-calendar-check fa-2x" style="color: <?php echo $current_role_category['color']; ?>"></i>
                            </div>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h6 class="mb-1">Events</h6>
                            <h3 class="mb-0">8</h3>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Activity -->
    <div class="row">
        <div class="col-md-8">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-transparent border-0">
                    <h5 class="mb-0">Recent Activity</h5>
                </div>
                <div class="card-body">
                    <div class="list-group list-group-flush">
                        <div class="list-group-item d-flex align-items-center px-0">
                            <div class="me-3">
                                <span class="badge rounded-pill p-2" style="background: <?php echo $current_role_category['bg_light']; ?>">
                                    <i class="fas fa-plus" style="color: <?php echo $current_role_category['color']; ?>"></i>
                                </span>
                            </div>
                            <div class="flex-grow-1">
                                <h6 class="mb-0">New equipment added</h6>
                                <small class="text-muted">2 minutes ago</small>
                            </div>
                        </div>
                        <div class="list-group-item d-flex align-items-center px-0">
                            <div class="me-3">
                                <span class="badge rounded-pill p-2" style="background: <?php echo $current_role_category['bg_light']; ?>">
                                    <i class="fas fa-qrcode" style="color: <?php echo $current_role_category['color']; ?>"></i>
                                </span>
                            </div>
                            <div class="flex-grow-1">
                                <h6 class="mb-0">Bulk scan completed</h6>
                                <small class="text-muted">15 minutes ago</small>
                            </div>
                        </div>
                        <div class="list-group-item d-flex align-items-center px-0">
                            <div class="me-3">
                                <span class="badge rounded-pill p-2" style="background: <?php echo $current_role_category['bg_light']; ?>">
                                    <i class="fas fa-user-plus" style="color: <?php echo $current_role_category['color']; ?>"></i>
                                </span>
                            </div>
                            <div class="flex-grow-1">
                                <h6 class="mb-0">New user registered</h6>
                                <small class="text-muted">1 hour ago</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-transparent border-0">
                    <h5 class="mb-0">Quick Actions</h5>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <button class="btn" style="background: <?php echo $current_role_category['bg_light']; ?>; color: <?php echo $current_role_category['color']; ?>" onclick="loadContent('add-equipment')">
                            <i class="fas fa-plus-circle me-2"></i>Add Equipment
                        </button>
                        <button class="btn" style="background: <?php echo $current_role_category['bg_light']; ?>; color: <?php echo $current_role_category['color']; ?>" onclick="loadContent('scan')">
                            <i class="fas fa-qrcode me-2"></i>New Scan
                        </button>
                        <button class="btn" style="background: <?php echo $current_role_category['bg_light']; ?>; color: <?php echo $current_role_category['color']; ?>" onclick="loadContent('reports')">
                            <i class="fas fa-chart-bar me-2"></i>View Reports
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>