<?php
// stock_locations.php - Stock Locations Management with Image Upload & Complete Working CRUD
require_once 'bootstrap.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit();
}

require_once 'includes/functions.php';

// Get database connection (PDO)
function getDBConnection()
{
    static $pdo = null;

    if ($pdo === null) {
        try {
            $host = defined('DB_HOST') ? DB_HOST : 'localhost';
            $dbname = defined('DB_NAME') ? DB_NAME : 'ability_db';
            $username = defined('DB_USER') ? DB_USER : 'root';
            $password = defined('DB_PASS') ? DB_PASS : '';

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

// Ensure stock_locations schema has all required columns including image
function checkAndFixStockLocationsTable($pdo)
{
    try {
        $sql = "CREATE TABLE IF NOT EXISTS stock_locations (
            id INT(11) PRIMARY KEY AUTO_INCREMENT,
            name VARCHAR(255) NOT NULL,
            location VARCHAR(255) DEFAULT NULL,
            location_code VARCHAR(50) DEFAULT NULL,
            code VARCHAR(50) DEFAULT NULL,
            address TEXT DEFAULT NULL,
            controller_name VARCHAR(100) DEFAULT NULL,
            controller_phone VARCHAR(50) DEFAULT NULL,
            controller_email VARCHAR(100) DEFAULT NULL,
            open_time TIME DEFAULT '09:00:00',
            close_time TIME DEFAULT '17:00:00',
            description TEXT DEFAULT NULL,
            image VARCHAR(500) DEFAULT NULL,
            is_active TINYINT(1) DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        $pdo->exec($sql);

        $cols = [
            'location' => "VARCHAR(255) DEFAULT NULL",
            'code' => "VARCHAR(50) DEFAULT NULL",
            'controller_name' => "VARCHAR(100) DEFAULT NULL",
            'controller_phone' => "VARCHAR(50) DEFAULT NULL",
            'controller_email' => "VARCHAR(100) DEFAULT NULL",
            'open_time' => "TIME DEFAULT '09:00:00'",
            'close_time' => "TIME DEFAULT '17:00:00'",
            'image' => "VARCHAR(500) DEFAULT NULL"
        ];

        foreach ($cols as $colName => $colDef) {
            try {
                $pdo->query("SELECT $colName FROM stock_locations LIMIT 1");
            } catch (Exception $e) {
                $pdo->exec("ALTER TABLE stock_locations ADD COLUMN $colName $colDef");
            }
        }
    } catch (Exception $e) {
        error_log("Error in checkAndFixStockLocationsTable: " . $e->getMessage());
    }
}
checkAndFixStockLocationsTable($pdo);

// Helper function for uploading location images
function handleLocationUpload($fileInputName, $existingPath = null)
{
    if (!isset($_FILES[$fileInputName]) || $_FILES[$fileInputName]['error'] === UPLOAD_ERR_NO_FILE) {
        return $existingPath;
    }

    $file = $_FILES[$fileInputName];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new Exception("Error during file upload: code " . $file['error']);
    }

    if ($file['size'] > 5 * 1024 * 1024) {
        throw new Exception("Image exceeds maximum allowed size of 5MB.");
    }

    $allowedExts = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExts)) {
        throw new Exception("Invalid image format. Allowed formats: JPG, PNG, WEBP, GIF.");
    }

    $targetDir = __DIR__ . '/uploads/locations/';
    if (!is_dir($targetDir)) {
        mkdir($targetDir, 0777, true);
    }

    $filename = 'location_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $destPath = $targetDir . $filename;

    if (!move_uploaded_file($file['tmp_name'], $destPath)) {
        throw new Exception("Failed to save uploaded image.");
    }

    // Delete old image if it exists and is stored in uploads/
    if (!empty($existingPath) && file_exists(__DIR__ . '/' . $existingPath) && strpos($existingPath, 'uploads/') === 0) {
        @unlink(__DIR__ . '/' . $existingPath);
    }

    return 'uploads/locations/' . $filename;
}

// Single Location JSON API for AJAX queries
if (isset($_GET['action']) && $_GET['action'] === 'get') {
    header('Content-Type: application/json');
    $id = (int)($_GET['id'] ?? 0);
    $stmt = $pdo->prepare("SELECT * FROM stock_locations WHERE id = ?");
    $stmt->execute([$id]);
    $loc = $stmt->fetch();
    if ($loc) {
        echo json_encode(['success' => true, 'location' => $loc]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Location not found']);
    }
    exit();
}

// Handle form submissions
$response = ['success' => false, 'message' => '', 'type' => 'error'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'add') {
            if (empty($_POST['name']) || empty($_POST['location']) || empty($_POST['controller_name'])) {
                throw new Exception("Stock Name, Location Address, and Controller in Charge are required fields.");
            }

            $name = trim($_POST['name']);
            $location = trim($_POST['location']);
            $controller_name = trim($_POST['controller_name']);
            $controller_phone = trim($_POST['controller_phone'] ?? '');
            $controller_email = trim($_POST['controller_email'] ?? '');
            $open_time = !empty($_POST['open_time']) ? $_POST['open_time'] : '09:00:00';
            $close_time = !empty($_POST['close_time']) ? $_POST['close_time'] : '17:00:00';
            $description = trim($_POST['description'] ?? '');
            $is_active = isset($_POST['is_active']) ? 1 : 0;

            // Handle image upload
            $imagePath = handleLocationUpload('image', null);

            $stmt = $pdo->prepare("
                INSERT INTO stock_locations 
                (name, location, controller_name, controller_phone, controller_email, open_time, close_time, description, image, is_active) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$name, $location, $controller_name, $controller_phone, $controller_email, $open_time, $close_time, $description, $imagePath, $is_active]);

            $response = [
                'success' => true,
                'message' => 'Stock location added successfully!',
                'type' => 'success'
            ];
        } elseif ($action === 'edit') {
            if (empty($_POST['id']) || empty($_POST['name']) || empty($_POST['location']) || empty($_POST['controller_name'])) {
                throw new Exception("ID, Stock Name, Location Address, and Controller Name are required fields.");
            }

            $id = (int)$_POST['id'];

            // Fetch current record
            $curStmt = $pdo->prepare("SELECT * FROM stock_locations WHERE id = ?");
            $curStmt->execute([$id]);
            $currentLoc = $curStmt->fetch();
            if (!$currentLoc) {
                throw new Exception("Location record not found.");
            }

            $name = trim($_POST['name']);
            $location = trim($_POST['location']);
            $controller_name = trim($_POST['controller_name']);
            $controller_phone = trim($_POST['controller_phone'] ?? '');
            $controller_email = trim($_POST['controller_email'] ?? '');
            $open_time = !empty($_POST['open_time']) ? $_POST['open_time'] : '09:00:00';
            $close_time = !empty($_POST['close_time']) ? $_POST['close_time'] : '17:00:00';
            $description = trim($_POST['description'] ?? '');
            $is_active = isset($_POST['is_active']) ? 1 : 0;

            $imagePath = $currentLoc['image'];

            // Check if user requested to remove existing image
            if (!empty($_POST['remove_image']) && $_POST['remove_image'] == '1') {
                if (!empty($imagePath) && file_exists(__DIR__ . '/' . $imagePath) && strpos($imagePath, 'uploads/') === 0) {
                    @unlink(__DIR__ . '/' . $imagePath);
                }
                $imagePath = null;
            }

            // Handle new image upload
            if (isset($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
                $imagePath = handleLocationUpload('image', $imagePath);
            }

            $stmt = $pdo->prepare("
                UPDATE stock_locations 
                SET name = ?, location = ?, controller_name = ?, controller_phone = ?, 
                    controller_email = ?, open_time = ?, close_time = ?, description = ?, image = ?, is_active = ?
                WHERE id = ?
            ");
            $stmt->execute([$name, $location, $controller_name, $controller_phone, $controller_email, $open_time, $close_time, $description, $imagePath, $is_active, $id]);

            $response = [
                'success' => true,
                'message' => 'Stock location updated successfully!',
                'type' => 'success'
            ];
        } elseif ($action === 'delete') {
            if (empty($_POST['id'])) {
                throw new Exception("ID is required for deletion.");
            }

            $id = (int)$_POST['id'];

            // Fetch record to check and delete image
            $curStmt = $pdo->prepare("SELECT * FROM stock_locations WHERE id = ?");
            $curStmt->execute([$id]);
            $currentLoc = $curStmt->fetch();

            // Check if location is in use by items
            $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM items WHERE stock_location = ? OR storage_location = ?");
            $checkStmt->execute([$id, $id]);
            $itemCount = $checkStmt->fetchColumn();

            if ($itemCount > 0) {
                throw new Exception("Cannot delete this location because it is currently assigned to {$itemCount} item(s).");
            }

            // Delete image file if exists
            if ($currentLoc && !empty($currentLoc['image']) && file_exists(__DIR__ . '/' . $currentLoc['image']) && strpos($currentLoc['image'], 'uploads/') === 0) {
                @unlink(__DIR__ . '/' . $currentLoc['image']);
            }

            $stmt = $pdo->prepare("DELETE FROM stock_locations WHERE id = ?");
            $stmt->execute([$id]);

            $response = [
                'success' => true,
                'message' => 'Stock location deleted successfully!',
                'type' => 'success'
            ];
        }
    } catch (Exception $e) {
        $response = [
            'success' => false,
            'message' => $e->getMessage(),
            'type' => 'error'
        ];
    }

    // Return JSON response for AJAX
    header('Content-Type: application/json');
    echo json_encode($response);
    exit();
}

// Pagination settings
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$itemsPerPage = 6;

// Search query
$search = trim($_GET['search'] ?? '');

try {
    if (!empty($search)) {
        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM stock_locations WHERE name LIKE ? OR location LIKE ? OR controller_name LIKE ?");
        $term = "%$search%";
        $countStmt->execute([$term, $term, $term]);
    } else {
        $countStmt = $pdo->query("SELECT COUNT(*) FROM stock_locations");
    }
    $totalItems = $countStmt->fetchColumn();
    $totalPages = max(1, ceil($totalItems / $itemsPerPage));

    if ($page < 1) $page = 1;
    if ($page > $totalPages && $totalPages > 0) $page = $totalPages;

    $offset = ($page - 1) * $itemsPerPage;
    $offset = max(0, $offset);
} catch (Exception $e) {
    $totalItems = 0;
    $totalPages = 1;
    $offset = 0;
}

// Get stock locations with pagination
$locations = [];
try {
    $limit = (int)$itemsPerPage;
    $offset = (int)$offset;
    if (!empty($search)) {
        $sql = "SELECT * FROM stock_locations WHERE name LIKE ? OR location LIKE ? OR controller_name LIKE ? ORDER BY name LIMIT $limit OFFSET $offset";
        $stmt = $pdo->prepare($sql);
        $term = "%$search%";
        $stmt->execute([$term, $term, $term]);
        $locations = $stmt->fetchAll();
    } else {
        $sql = "SELECT * FROM stock_locations ORDER BY name LIMIT $limit OFFSET $offset";
        $stmt = $pdo->query($sql);
        $locations = $stmt->fetchAll();
    }
} catch (Exception $e) {
    $error = "Error loading locations: " . $e->getMessage();
}

// Get item counts per stock location
$itemCounts = [];
try {
    $countStmt = $pdo->query("
        SELECT stock_location, COUNT(*) as count 
        FROM items 
        WHERE stock_location IS NOT NULL AND stock_location != ''
        GROUP BY stock_location
    ");
    $countResults = $countStmt->fetchAll();
    foreach ($countResults as $row) {
        $itemCounts[$row['stock_location']] = $row['count'];
    }
} catch (Exception $e) {
    // Ignore error
}

$pageTitle = "Stock Locations - aBility";
$showBreadcrumb = true;
$breadcrumbItems = [
    'Dashboard' => 'dashboard_full.php',
    'Setup' => '#',
    'Stock Locations' => ''
];

require_once 'views/partials/header.php';
?>

<!-- SweetAlert2 & Toastr CSS -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">

<style>
    .location-card {
        transition: transform 0.25s ease, box-shadow 0.25s ease;
        border: none;
        border-radius: 12px;
        overflow: hidden;
        background: #ffffff;
    }

    .location-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.12) !important;
    }

    .location-media-col {
        position: relative;
        min-height: 220px;
        display: flex;
        align-items: center;
        justify-content: center;
        overflow: hidden;
        background: #1f5e4f;
    }

    .location-media-img {
        width: 100%;
        height: 100%;
        min-height: 220px;
        object-fit: cover;
        transition: transform 0.3s ease;
    }

    .location-card:hover .location-media-img {
        transform: scale(1.05);
    }

    .location-zoom-btn {
        position: absolute;
        top: 10px;
        left: 10px;
        background: rgba(0, 0, 0, 0.55);
        color: #ffffff;
        border-radius: 50%;
        width: 32px;
        height: 32px;
        display: flex;
        align-items: center;
        justify-content: center;
        border: none;
        transition: background 0.2s, transform 0.2s;
        text-decoration: none;
        z-index: 2;
    }

    .location-zoom-btn:hover {
        background: rgba(0, 0, 0, 0.85);
        color: #ffffff;
        transform: scale(1.1);
    }

    .location-item-badge {
        position: absolute;
        bottom: 10px;
        left: 10px;
        z-index: 2;
        box-shadow: 0 2px 6px rgba(0, 0, 0, 0.3);
    }

    .badge-status {
        font-size: 0.7rem;
        padding: 4px 8px;
        letter-spacing: 0.5px;
        font-weight: 600;
    }

    .btn-action-icon {
        width: 32px;
        height: 32px;
        padding: 0;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 6px;
    }

    .image-preview-zone {
        border: 2px dashed #cbd5e1;
        border-radius: 10px;
        padding: 15px;
        text-align: center;
        background: #f8fafc;
        cursor: pointer;
        transition: border-color 0.2s;
    }

    .image-preview-zone:hover {
        border-color: #1f5e4f;
    }

    .image-preview-thumbnail {
        max-width: 100%;
        max-height: 160px;
        border-radius: 8px;
        object-fit: cover;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    }
</style>

<div class="container-fluid py-3">
    <!-- Header Controls -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
        <div>
            <h1 class="h3 mb-1 text-gray-800 fw-bold">
                <i class="fas fa-warehouse me-2 text-primary"></i>Stock Locations
            </h1>
            <p class="text-muted small mb-0">Manage warehouses, stock facilities, controllers, and working schedules.</p>
        </div>

        <div class="d-flex flex-wrap align-items-center gap-2">
            <form method="GET" action="stock_locations.php" class="d-flex align-items-center gap-2">
                <div class="input-group input-group-sm" style="max-width: 250px;">
                    <input type="text" name="search" class="form-control" placeholder="Search locations..." value="<?php echo htmlspecialchars($search); ?>">
                    <?php if (!empty($search)): ?>
                        <a href="stock_locations.php" class="btn btn-outline-secondary" title="Clear search"><i class="fas fa-times"></i></a>
                    <?php endif; ?>
                    <button class="btn btn-outline-primary" type="submit"><i class="fas fa-search"></i></button>
                </div>
            </form>

            <a href="id_badges.php?tab=kiosk" class="btn btn-sm btn-outline-dark d-flex align-items-center gap-1" title="Open Attendance & Gate Terminal">
                <i class="fas fa-id-badge"></i>
                <span class="d-none d-md-inline">Gate Terminal</span>
            </a>

            <a href="venue_scanner.php?type=stock_warehouse" class="btn btn-sm btn-outline-info text-dark d-flex align-items-center gap-1" title="Open Live Scanner Kiosk">
                <i class="fas fa-camera"></i>
                <span class="d-none d-md-inline">Scanner Kiosk</span>
            </a>

            <button type="button" class="btn btn-sm btn-success d-flex align-items-center gap-2" onclick="openAddLocation()">
                <i class="fas fa-plus"></i>
                <span>Add Location</span>
            </button>
        </div>
    </div>

    <!-- Locations Grid -->
    <div class="row">
        <?php if (empty($locations)): ?>
            <div class="col-12">
                <div class="card shadow-sm mb-4 border-0">
                    <div class="card-body text-center py-5">
                        <i class="fas fa-warehouse fa-4x text-muted mb-3"></i>
                        <h5 class="fw-bold">No Stock Locations Found</h5>
                        <p class="text-muted small">
                            <?php if (!empty($search)): ?>
                                No stock locations match your search query "<strong><?php echo htmlspecialchars($search); ?></strong>".
                            <?php else: ?>
                                You haven't configured any stock locations yet. Add your first warehouse or stock room to get started.
                            <?php endif; ?>
                        </p>
                        <?php if (!empty($search)): ?>
                            <a href="stock_locations.php" class="btn btn-sm btn-outline-secondary me-2">Clear Search</a>
                        <?php endif; ?>
                        <button class="btn btn-sm btn-primary" onclick="openAddLocation()">
                            <i class="fas fa-plus me-1"></i> Add Location
                        </button>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <?php foreach ($locations as $location): 
                $locId = $location['id'];
                $hasImage = !empty($location['image']) && file_exists(__DIR__ . '/' . $location['image']);
                $itemCount = $itemCounts[$locId] ?? ($itemCounts[$location['name']] ?? 0);
            ?>
                <div class="col-md-6 col-xl-4 mb-4">
                    <div class="card location-card h-100 shadow-sm">
                        <div class="row g-0 h-100">
                            <!-- Left Column: Photo or Gradient Fallback -->
                            <div class="col-md-4 location-media-col text-white" 
                                 style="<?php echo !$hasImage ? ($location['is_active'] ? 'background: linear-gradient(135deg, #1f5e4f 0%, #2a7f6e 100%);' : 'background: linear-gradient(135deg, #64748b 0%, #475569 100%);') : 'background: #0f172a;'; ?>">
                                
                                <?php if ($hasImage): ?>
                                    <img src="<?php echo htmlspecialchars($location['image']); ?>" 
                                         alt="<?php echo htmlspecialchars($location['name']); ?>" 
                                         class="location-media-img"
                                         onclick="previewLocationImage('<?php echo htmlspecialchars($location['image']); ?>', '<?php echo htmlspecialchars(addslashes($location['name'])); ?>')"
                                         title="Click to zoom photo">
                                    <button type="button" class="location-zoom-btn" 
                                            onclick="previewLocationImage('<?php echo htmlspecialchars($location['image']); ?>', '<?php echo htmlspecialchars(addslashes($location['name'])); ?>')" 
                                            title="View full image">
                                        <i class="fas fa-search-plus" style="font-size: 0.75rem;"></i>
                                    </button>
                                <?php else: ?>
                                    <div class="text-center p-3">
                                        <i class="fas fa-warehouse fa-3x mb-2 text-white opacity-75"></i>
                                    </div>
                                <?php endif; ?>

                                <!-- Item Count Badge -->
                                <span class="badge bg-dark bg-opacity-75 text-white location-item-badge">
                                    <i class="fas fa-boxes me-1 text-warning"></i><?php echo $itemCount; ?> items
                                </span>
                            </div>

                            <!-- Right Column: Location Details -->
                            <div class="col-md-8">
                                <div class="card-body p-3 d-flex flex-column justify-content-between h-100">
                                    <div>
                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                            <h5 class="card-title fw-bold text-dark mb-0 fs-6"><?php echo htmlspecialchars($location['name']); ?></h5>
                                            <span class="badge rounded-pill badge-status <?php echo $location['is_active'] ? 'bg-success' : 'bg-secondary'; ?>">
                                                <?php echo $location['is_active'] ? 'ACTIVE' : 'INACTIVE'; ?>
                                            </span>
                                        </div>

                                        <div class="mb-2 small">
                                            <i class="fas fa-map-marker-alt text-danger me-2" style="width: 16px;"></i>
                                            <span class="text-muted"><?php echo htmlspecialchars($location['location']); ?></span>
                                        </div>

                                        <div class="mb-2 small">
                                            <i class="fas fa-user-shield text-success me-2" style="width: 16px;"></i>
                                            <span class="fw-semibold text-dark"><?php echo htmlspecialchars($location['controller_name']); ?></span>
                                        </div>

                                        <div class="mb-2 small">
                                            <i class="fas fa-clock text-info me-2" style="width: 16px;"></i>
                                            <span class="text-muted">
                                                <?php
                                                $open = !empty($location['open_time']) ? date('h:i A', strtotime($location['open_time'])) : '09:00 AM';
                                                $close = !empty($location['close_time']) ? date('h:i A', strtotime($location['close_time'])) : '05:00 PM';
                                                echo $open . ' - ' . $close;
                                                ?>
                                            </span>
                                        </div>

                                        <?php if (!empty($location['controller_phone'])): ?>
                                            <div class="mb-2 small">
                                                <i class="fas fa-phone text-warning me-2" style="width: 16px;"></i>
                                                <a href="tel:<?php echo htmlspecialchars($location['controller_phone']); ?>" class="text-decoration-none text-muted">
                                                    <?php echo htmlspecialchars($location['controller_phone']); ?>
                                                </a>
                                            </div>
                                        <?php endif; ?>

                                        <?php if (!empty($location['controller_email'])): ?>
                                            <div class="mb-2 small">
                                                <i class="fas fa-envelope text-secondary me-2" style="width: 16px;"></i>
                                                <a href="mailto:<?php echo htmlspecialchars($location['controller_email']); ?>" class="text-decoration-none text-muted text-truncate d-inline-block" style="max-width: 170px; vertical-align: bottom;">
                                                    <?php echo htmlspecialchars($location['controller_email']); ?>
                                                </a>
                                            </div>
                                        <?php endif; ?>

                                        <?php if (!empty($location['description'])): ?>
                                            <div class="mt-2 pt-2 border-top small text-muted">
                                                <i class="fas fa-info-circle me-1 text-primary"></i>
                                                <span><?php echo htmlspecialchars(mb_strimwidth($location['description'], 0, 70, '...')); ?></span>
                                            </div>
                                        <?php endif; ?>
                                    </div>

                                    <!-- Action Buttons -->
                                    <div class="mt-3 pt-2 border-top d-flex justify-content-between align-items-center">
                                        <div class="d-flex gap-1">
                                            <a href="venue_scanner.php?type=stock_warehouse&id=<?php echo $location['id']; ?>"
                                               class="btn btn-sm btn-outline-success btn-action-icon"
                                               title="Open Gate Scanner for <?php echo htmlspecialchars($location['name']); ?>">
                                                <i class="fas fa-barcode"></i>
                                            </a>
                                            <a href="id_badges.php?tab=kiosk&checkpoint_type=stock_warehouse&checkpoint_id=<?php echo $location['id']; ?>"
                                               class="btn btn-sm btn-outline-dark btn-action-icon"
                                               title="Open Attendance Terminal for <?php echo htmlspecialchars($location['name']); ?>">
                                                <i class="fas fa-id-badge"></i>
                                            </a>
                                        </div>
                                        <div class="d-flex gap-2">
                                            <button type="button" class="btn btn-sm btn-outline-primary btn-action-icon"
                                                    onclick="openEditLocation(<?php echo $location['id']; ?>)"
                                                    title="Edit Location">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button type="button" class="btn btn-sm btn-outline-danger btn-action-icon"
                                                    onclick="openDeleteLocation(<?php echo $location['id']; ?>, '<?php echo htmlspecialchars(addslashes($location['name'])); ?>')"
                                                    title="Delete Location">
                                                <i class="fas fa-trash-alt"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>

            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
                <div class="col-12">
                    <nav aria-label="Stock locations pagination" class="mt-4">
                        <ul class="pagination pagination-sm justify-content-center">
                            <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $page - 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>">
                                    <i class="fas fa-chevron-left me-1"></i> Prev
                                </a>
                            </li>

                            <?php
                            $startPage = max(1, $page - 2);
                            $endPage = min($totalPages, $startPage + 4);

                            if ($startPage > 1) {
                                echo '<li class="page-item"><a class="page-link" href="?page=1' . (!empty($search) ? '&search=' . urlencode($search) : '') . '">1</a></li>';
                                if ($startPage > 2) {
                                    echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                }
                            }

                            for ($i = $startPage; $i <= $endPage; $i++):
                            ?>
                                <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $i; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>"><?php echo $i; ?></a>
                                </li>
                            <?php endfor; ?>

                            <?php if ($endPage < $totalPages): ?>
                                <?php if ($endPage < $totalPages - 1): ?>
                                    <li class="page-item disabled"><span class="page-link">...</span></li>
                                <?php endif; ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo $totalPages; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>"><?php echo $totalPages; ?></a>
                                </li>
                            <?php endif; ?>

                            <li class="page-item <?php echo $page >= $totalPages ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $page + 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>">
                                    Next <i class="fas fa-chevron-right ms-1"></i>
                                </a>
                            </li>
                        </ul>
                    </nav>

                    <div class="text-center text-muted small mt-2">
                        Showing <?php echo $offset + 1; ?> - <?php echo min($offset + $itemsPerPage, $totalItems); ?> of <?php echo $totalItems; ?> locations
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<!-- ==================== ADD LOCATION MODAL ==================== -->
<div class="modal fade" id="addLocationModal" tabindex="-1" aria-labelledby="addLocationModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <form id="addLocationForm" enctype="multipart/form-data">
                <input type="hidden" name="action" value="add">

                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title fw-bold" id="addLocationModalLabel">
                        <i class="fas fa-plus-circle me-2"></i>Add Stock Location
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body p-4">
                    <div class="row g-3">
                        <!-- Location Name & Address -->
                        <div class="col-md-6">
                            <label for="add_name" class="form-label fw-bold small">Stock / Warehouse Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control form-control-sm" id="add_name" name="name" placeholder="e.g. Ndera Central Warehouse" required>
                        </div>
                        <div class="col-md-6">
                            <label for="add_location" class="form-label fw-bold small">Physical Address / City <span class="text-danger">*</span></label>
                            <input type="text" class="form-control form-control-sm" id="add_location" name="location" placeholder="e.g. Kigali, Gasabo" required>
                        </div>

                        <!-- Controller Information -->
                        <div class="col-md-4">
                            <label for="add_controller_name" class="form-label fw-bold small">Controller in Charge <span class="text-danger">*</span></label>
                            <input type="text" class="form-control form-control-sm" id="add_controller_name" name="controller_name" placeholder="e.g. John Doe" required>
                        </div>
                        <div class="col-md-4">
                            <label for="add_controller_phone" class="form-label fw-bold small">Controller Phone</label>
                            <input type="tel" class="form-control form-control-sm" id="add_controller_phone" name="controller_phone" placeholder="e.g. +250 788 000 000">
                        </div>
                        <div class="col-md-4">
                            <label for="add_controller_email" class="form-label fw-bold small">Controller Email</label>
                            <input type="email" class="form-control form-control-sm" id="add_controller_email" name="controller_email" placeholder="e.g. controller@domain.com">
                        </div>

                        <!-- Hours of Operation -->
                        <div class="col-md-6">
                            <label for="add_open_time" class="form-label fw-bold small">Open Time</label>
                            <input type="time" class="form-control form-control-sm" id="add_open_time" name="open_time" value="09:00">
                        </div>
                        <div class="col-md-6">
                            <label for="add_close_time" class="form-label fw-bold small">Close Time</label>
                            <input type="time" class="form-control form-control-sm" id="add_close_time" name="close_time" value="17:00">
                        </div>

                        <!-- Description Notes -->
                        <div class="col-12">
                            <label for="add_description" class="form-label fw-bold small">Description / Storage Notes</label>
                            <textarea class="form-control form-control-sm" id="add_description" name="description" rows="2" placeholder="Facility capacity, access guidelines, gate pass requirement..."></textarea>
                        </div>

                        <!-- Image Upload Section -->
                        <div class="col-12">
                            <label class="form-label fw-bold small">Location Photo</label>
                            <div class="image-preview-zone" onclick="document.getElementById('add_image_input').click()">
                                <input type="file" id="add_image_input" name="image" accept="image/png, image/jpeg, image/webp, image/gif" class="d-none" onchange="previewSelectedImage(this, 'add_image_preview', 'add_image_container')">
                                <div id="add_image_container" class="text-center">
                                    <i class="fas fa-cloud-upload-alt fa-2x text-muted mb-2"></i>
                                    <p class="small text-muted mb-1">Click to upload photo of this stock facility (PNG, JPG, WEBP - Max 5MB)</p>
                                </div>
                                <div id="add_image_preview" class="d-none text-center mt-2 position-relative">
                                    <img src="" alt="Preview" class="image-preview-thumbnail">
                                    <div class="mt-2">
                                        <button type="button" class="btn btn-xs btn-outline-danger" onclick="event.stopPropagation(); clearImageSelection('add_image_input', 'add_image_preview', 'add_image_container');">
                                            <i class="fas fa-times me-1"></i> Remove Selected Photo
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Active Switch -->
                        <div class="col-12">
                            <div class="form-check form-switch mt-1">
                                <input class="form-check-input" type="checkbox" id="add_is_active" name="is_active" checked>
                                <label class="form-check-label small fw-semibold" for="add_is_active">Active Location</label>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer bg-light px-4 py-3">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-primary">
                        <i class="fas fa-save me-1"></i> Save Location
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ==================== EDIT LOCATION MODAL ==================== -->
<div class="modal fade" id="editLocationModal" tabindex="-1" aria-labelledby="editLocationModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <form id="editLocationForm" enctype="multipart/form-data">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" id="edit_id">
                <input type="hidden" name="remove_image" id="edit_remove_image" value="0">

                <div class="modal-header bg-warning text-dark">
                    <h5 class="modal-title fw-bold" id="editLocationModalLabel">
                        <i class="fas fa-edit me-2"></i>Edit Stock Location
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body p-4">
                    <div class="row g-3">
                        <!-- Location Name & Address -->
                        <div class="col-md-6">
                            <label for="edit_name" class="form-label fw-bold small">Stock / Warehouse Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control form-control-sm" id="edit_name" name="name" required>
                        </div>
                        <div class="col-md-6">
                            <label for="edit_location" class="form-label fw-bold small">Physical Address / City <span class="text-danger">*</span></label>
                            <input type="text" class="form-control form-control-sm" id="edit_location" name="location" required>
                        </div>

                        <!-- Controller Information -->
                        <div class="col-md-4">
                            <label for="edit_controller_name" class="form-label fw-bold small">Controller in Charge <span class="text-danger">*</span></label>
                            <input type="text" class="form-control form-control-sm" id="edit_controller_name" name="controller_name" required>
                        </div>
                        <div class="col-md-4">
                            <label for="edit_controller_phone" class="form-label fw-bold small">Controller Phone</label>
                            <input type="tel" class="form-control form-control-sm" id="edit_controller_phone" name="controller_phone">
                        </div>
                        <div class="col-md-4">
                            <label for="edit_controller_email" class="form-label fw-bold small">Controller Email</label>
                            <input type="email" class="form-control form-control-sm" id="edit_controller_email" name="controller_email">
                        </div>

                        <!-- Hours of Operation -->
                        <div class="col-md-6">
                            <label for="edit_open_time" class="form-label fw-bold small">Open Time</label>
                            <input type="time" class="form-control form-control-sm" id="edit_open_time" name="open_time">
                        </div>
                        <div class="col-md-6">
                            <label for="edit_close_time" class="form-label fw-bold small">Close Time</label>
                            <input type="time" class="form-control form-control-sm" id="edit_close_time" name="close_time">
                        </div>

                        <!-- Description Notes -->
                        <div class="col-12">
                            <label for="edit_description" class="form-label fw-bold small">Description / Storage Notes</label>
                            <textarea class="form-control form-control-sm" id="edit_description" name="description" rows="2"></textarea>
                        </div>

                        <!-- Current Image & Image Upload -->
                        <div class="col-12">
                            <label class="form-label fw-bold small">Location Photo</label>

                            <!-- Existing Image Box -->
                            <div id="edit_current_image_section" class="mb-2 p-2 border rounded bg-light d-none">
                                <div class="d-flex align-items-center justify-content-between">
                                    <div class="d-flex align-items-center gap-3">
                                        <img id="edit_current_image_thumb" src="" alt="Location Photo" style="width: 60px; height: 60px; object-fit: cover; border-radius: 6px;">
                                        <div>
                                            <span class="badge bg-primary mb-1">Current Photo</span>
                                            <p class="small text-muted mb-0">Select a new image below to replace it, or remove current image.</p>
                                        </div>
                                    </div>
                                    <button type="button" class="btn btn-sm btn-outline-danger" onclick="markRemoveCurrentImage()">
                                        <i class="fas fa-trash-alt me-1"></i> Remove Photo
                                    </button>
                                </div>
                            </div>

                            <!-- Upload/Replace Zone -->
                            <div class="image-preview-zone" onclick="document.getElementById('edit_image_input').click()">
                                <input type="file" id="edit_image_input" name="image" accept="image/png, image/jpeg, image/webp, image/gif" class="d-none" onchange="previewSelectedImage(this, 'edit_image_preview', 'edit_image_container')">
                                <div id="edit_image_container" class="text-center">
                                    <i class="fas fa-cloud-upload-alt fa-2x text-muted mb-2"></i>
                                    <p class="small text-muted mb-1" id="edit_upload_label">Click to upload new location photo (PNG, JPG, WEBP - Max 5MB)</p>
                                </div>
                                <div id="edit_image_preview" class="d-none text-center mt-2 position-relative">
                                    <img src="" alt="Preview" class="image-preview-thumbnail">
                                    <div class="mt-2">
                                        <button type="button" class="btn btn-xs btn-outline-danger" onclick="event.stopPropagation(); clearImageSelection('edit_image_input', 'edit_image_preview', 'edit_image_container');">
                                            <i class="fas fa-times me-1"></i> Cancel New Photo Selection
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Active Switch -->
                        <div class="col-12">
                            <div class="form-check form-switch mt-1">
                                <input class="form-check-input" type="checkbox" id="edit_is_active" name="is_active">
                                <label class="form-check-label small fw-semibold" for="edit_is_active">Active Location</label>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer bg-light px-4 py-3">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-primary">
                        <i class="fas fa-save me-1"></i> Update Location
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ==================== DELETE MODAL ==================== -->
<div class="modal fade" id="deleteLocationModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <form id="deleteLocationForm">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" id="delete_id">

                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title fw-bold">
                        <i class="fas fa-exclamation-triangle me-2"></i>Confirm Deletion
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4 text-center">
                    <i class="fas fa-trash-alt fa-3x text-danger mb-3"></i>
                    <p class="mb-1">Are you sure you want to permanently delete stock location:</p>
                    <h5 class="fw-bold text-dark" id="delete_name"></h5>
                    <p class="text-muted small mt-2">Any associated image file will also be removed. This action cannot be undone.</p>
                </div>
                <div class="modal-footer bg-light px-4 py-3">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-danger">
                        <i class="fas fa-trash-alt me-1"></i> Delete Location
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ==================== IMAGE LIGHTBOX MODAL ==================== -->
<div class="modal fade" id="imagePreviewModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-dark text-white py-2">
                <h6 class="modal-title fw-bold" id="imagePreviewModalTitle"><i class="fas fa-image me-2"></i>Location Photo</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0 text-center bg-black">
                <img id="imagePreviewModalImg" src="" alt="Full Location Photo" style="max-width: 100%; max-height: 80vh; object-fit: contain;">
            </div>
        </div>
    </div>
</div>

<?php
// Buffer scripts so they execute after footer loads jQuery, Bootstrap, and DataTables
ob_start();
?>
<!-- SweetAlert2 JS -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>

<script>
    // Live Image Preview Helper
    function previewSelectedImage(input, previewId, containerId) {
        if (input.files && input.files[0]) {
            const reader = new FileReader();
            reader.onload = function(e) {
                $('#' + previewId + ' img').attr('src', e.target.result);
                $('#' + previewId).removeClass('d-none');
                $('#' + containerId).addClass('d-none');
            };
            reader.readAsDataURL(input.files[0]);
        }
    }

    function clearImageSelection(inputId, previewId, containerId) {
        $('#' + inputId).val('');
        $('#' + previewId + ' img').attr('src', '');
        $('#' + previewId).addClass('d-none');
        $('#' + containerId).removeClass('d-none');
    }

    function previewLocationImage(url, title) {
        $('#imagePreviewModalImg').attr('src', url);
        $('#imagePreviewModalTitle').html('<i class="fas fa-warehouse me-2"></i>' + title);
        new bootstrap.Modal(document.getElementById('imagePreviewModal')).show();
    }

    function markRemoveCurrentImage() {
        $('#edit_remove_image').val('1');
        $('#edit_current_image_section').addClass('d-none');
        toastr.info('Current photo will be removed upon saving.');
    }

    function openAddLocation() {
        document.getElementById('addLocationForm').reset();
        clearImageSelection('add_image_input', 'add_image_preview', 'add_image_container');
        $('#add_is_active').prop('checked', true);
        new bootstrap.Modal(document.getElementById('addLocationModal')).show();
    }

    function openEditLocation(id) {
        document.getElementById('editLocationForm').reset();
        clearImageSelection('edit_image_input', 'edit_image_preview', 'edit_image_container');
        $('#edit_remove_image').val('0');

        // Fetch location details via AJAX
        $.ajax({
            url: 'stock_locations.php?action=get&id=' + id,
            type: 'GET',
            dataType: 'json',
            success: function(response) {
                if (response.success && response.location) {
                    const loc = response.location;
                    $('#edit_id').val(loc.id);
                    $('#edit_name').val(loc.name);
                    $('#edit_location').val(loc.location);
                    $('#edit_controller_name').val(loc.controller_name);
                    $('#edit_controller_phone').val(loc.controller_phone || '');
                    $('#edit_controller_email').val(loc.controller_email || '');
                    $('#edit_open_time').val(loc.open_time || '09:00');
                    $('#edit_close_time').val(loc.close_time || '17:00');
                    $('#edit_description').val(loc.description || '');
                    $('#edit_is_active').prop('checked', loc.is_active == 1);

                    // Image handling
                    if (loc.image && loc.image.trim() !== '') {
                        $('#edit_current_image_thumb').attr('src', loc.image);
                        $('#edit_current_image_section').removeClass('d-none');
                        $('#edit_upload_label').text('Click to replace current photo with a new one');
                    } else {
                        $('#edit_current_image_section').addClass('d-none');
                        $('#edit_upload_label').text('Click to upload photo for this stock facility');
                    }

                    new bootstrap.Modal(document.getElementById('editLocationModal')).show();
                } else {
                    Swal.fire('Error', response.message || 'Unable to retrieve location details', 'error');
                }
            },
            error: function() {
                Swal.fire('Error', 'Communication error while loading location details', 'error');
            }
        });
    }

    function openDeleteLocation(id, name) {
        $('#delete_id').val(id);
        $('#delete_name').text(name);
        new bootstrap.Modal(document.getElementById('deleteLocationModal')).show();
    }

    $(document).ready(function() {
        toastr.options = {
            "closeButton": true,
            "progressBar": true,
            "positionClass": "toast-top-center",
            "timeOut": "3000"
        };

        // Form Submit Handler for Add / Edit
        function handleLocationForm(formId, modalId) {
            $(formId).on('submit', function(e) {
                e.preventDefault();

                var form = this;
                var formData = new FormData(form);
                var submitBtn = $(form).find('button[type="submit"]');
                var originalHtml = submitBtn.html();

                submitBtn.html('<span class="spinner-border spinner-border-sm me-1"></span> Saving...').prop('disabled', true);

                $.ajax({
                    url: 'stock_locations.php',
                    type: 'POST',
                    data: formData,
                    dataType: 'json',
                    processData: false,
                    contentType: false,
                    success: function(response) {
                        if (response.success) {
                            Swal.fire({
                                icon: 'success',
                                title: 'Success!',
                                text: response.message,
                                timer: 1500,
                                showConfirmButton: false
                            });
                            $(modalId).modal('hide');
                            setTimeout(function() {
                                location.reload();
                            }, 1200);
                        } else {
                            Swal.fire('Error', response.message || 'Failed to save location', 'error');
                            submitBtn.html(originalHtml).prop('disabled', false);
                        }
                    },
                    error: function(xhr, status, error) {
                        let msg = 'Failed to communicate with server.';
                        if (xhr.responseJSON && xhr.responseJSON.message) {
                            msg = xhr.responseJSON.message;
                        } else if (xhr.responseText) {
                            try {
                                const p = JSON.parse(xhr.responseText);
                                if (p && p.message) msg = p.message;
                            } catch(e) {}
                        }
                        Swal.fire('Error', msg, 'error');
                        submitBtn.html(originalHtml).prop('disabled', false);
                    }
                });
            });
        }

        handleLocationForm('#addLocationForm', '#addLocationModal');
        handleLocationForm('#editLocationForm', '#editLocationModal');

        // Delete Handler
        $('#deleteLocationForm').on('submit', function(e) {
            e.preventDefault();

            var form = this;
            var formData = new FormData(form);
            var submitBtn = $(form).find('button[type="submit"]');
            var originalHtml = submitBtn.html();

            submitBtn.html('<span class="spinner-border spinner-border-sm me-1"></span> Deleting...').prop('disabled', true);

            $.ajax({
                url: 'stock_locations.php',
                type: 'POST',
                data: formData,
                dataType: 'json',
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Deleted!',
                            text: response.message,
                            timer: 1500,
                            showConfirmButton: false
                        });
                        $('#deleteLocationModal').modal('hide');
                        setTimeout(function() {
                            location.reload();
                        }, 1200);
                    } else {
                        Swal.fire('Cannot Delete', response.message || 'Failed to delete location', 'warning');
                        submitBtn.html(originalHtml).prop('disabled', false);
                    }
                },
                error: function(xhr) {
                    let msg = 'Failed to communicate with server.';
                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        msg = xhr.responseJSON.message;
                    }
                    Swal.fire('Error', msg, 'error');
                    submitBtn.html(originalHtml).prop('disabled', false);
                }
            });
        });
    });
</script>
<?php
$extra_scripts = ob_get_clean();
require_once 'views/partials/footer.php';
?>
