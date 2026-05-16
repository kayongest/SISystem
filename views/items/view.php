<?php
// dashboard_full.php or index.php

// Include bootstrap first
require_once 'bootstrap.php';

// Check if redirect function exists before using it
if (!function_exists('redirect')) {
    function redirect($url, $statusCode = 303)
    {
        header('Location: ' . $url, true, $statusCode);
        exit();
    }
}

// Check authentication
if (!isLoggedIn()) {
    $_SESSION['redirect_to'] = $_SERVER['REQUEST_URI'];
    redirect('login.php');
}

// Include other required files
require_once 'includes/db_connect.php';
require_once 'includes/functions.php';

// Get database connection
$db = new Database();
$conn = $db->getConnection();

// Get dashboard stats
$stats = getDashboardStats($conn);

// Get detailed category statistics
$category_stats = $conn->query("
    SELECT 
        COALESCE(category, 'Uncategorized') as category_name,
        COUNT(*) as item_count,
        SUM(quantity) as total_quantity,
        ROUND((COUNT(*) * 100.0 / (SELECT COUNT(*) FROM items)), 2) as percentage
    FROM items
    GROUP BY category
    ORDER BY item_count DESC
")->fetch_all(MYSQLI_ASSOC);

// Get aggregated item totals
$itemTotals = $conn->query("
    SELECT 
        item_name,
        category,
        COUNT(*) as serial_count,
        SUM(quantity) as total_quantity,
        GROUP_CONCAT(DISTINCT serial_number ORDER BY serial_number SEPARATOR ', ') as serials
    FROM items 
    GROUP BY item_name, category
    HAVING COUNT(*) > 1 OR SUM(quantity) > 1
    ORDER BY total_quantity DESC
    LIMIT 10
")->fetch_all(MYSQLI_ASSOC);

// Get recent items - get more items for DataTables
$recentItems = getRecentItems($conn, 100); // Get more items for DataTables pagination

// Set page variables
$pageTitle = "Dashboard - aBility";
$showBreadcrumb = true;
$breadcrumbItems = [
    'Dashboard' => ''
];

// Include header
require_once 'views/partials/header.php';
?>




<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
</head>

<body>

    <!-- Add this section to your dashboard -->
    <div class="card mt-4">
        <div class="card-header bg-info text-white">
            <h5 class="mb-0"><i class="fas fa-boxes"></i> Item Quantities Summary</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-sm" id="itemQuantitiesTable">
                    <thead>
                        <tr>
                            <th>Item Name</th>
                            <th>Category</th>
                            <th>Unique Serials</th>
                            <th>Total Units</th>
                            <th>Serials</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($itemTotals)): ?>
                            <?php foreach ($itemTotals as $total): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($total['item_name']); ?></td>
                                    <td><?php echo getCategoryBadge($total['category']); ?></td>
                                    <td class="text-center">
                                        <span class="badge bg-primary"><?php echo $total['serial_count']; ?></span>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-success fs-6"><?php echo $total['total_quantity']; ?></span>
                                    </td>
                                    <td>
                                        <small class="text-muted" title="<?php echo htmlspecialchars($total['serials']); ?>">
                                            <?php
                                            $serials = explode(', ', $total['serials']);
                                            if (count($serials) > 3) {
                                                echo htmlspecialchars($serials[0]) . ', ' . htmlspecialchars($serials[1]) . '... (+' . (count($serials) - 2) . ' more)';
                                            } else {
                                                echo htmlspecialchars(substr($total['serials'], 0, 50));
                                                if (strlen($total['serials']) > 50) echo '...';
                                            }
                                            ?>
                                        </small>
                                    </td>
                                    <td>
                                        <a href="items.php?search=<?php echo urlencode($total['item_name']); ?>"
                                            class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-search"></i> View All
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center">No items with multiple units found</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>

</html>