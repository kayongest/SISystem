<?php
// stock_locations.php - Stock Locations Management with Image Upload & Complete Working CRUD
require_once 'bootstrap.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit();
}

require_once 'includes/functions.php';
require_once __DIR__ . '/includes/badge_attendance_helper.php';

if (!function_exists('normalizeEventNumericId')) {
    function normalizeEventNumericId($rawId): int
    {
        $str = trim((string)$rawId);
        if ($str === '') {
            return 0;
        }
        if (ctype_digit($str)) {
            return (int)$str;
        }
        return (abs(crc32($str)) % 900000) + 10000;
    }
}

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

// Ensure venues schema exists and has all required columns
function checkAndFixVenuesTable($pdo)
{
    try {
        $sql = "CREATE TABLE IF NOT EXISTS venues (
            id INT(11) PRIMARY KEY AUTO_INCREMENT,
            name VARCHAR(255) NOT NULL,
            location VARCHAR(255) DEFAULT NULL,
            code VARCHAR(50) DEFAULT NULL,
            address TEXT DEFAULT NULL,
            contact_person VARCHAR(100) DEFAULT NULL,
            contact_phone VARCHAR(50) DEFAULT NULL,
            contact_email VARCHAR(100) DEFAULT NULL,
            open_time TIME DEFAULT '08:00:00',
            close_time TIME DEFAULT '23:00:00',
            description TEXT DEFAULT NULL,
            image VARCHAR(500) DEFAULT NULL,
            is_active TINYINT(1) DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        $pdo->exec($sql);

        // Seed default venues matching floor checkpoints if empty
        $cnt = $pdo->query("SELECT COUNT(*) FROM venues")->fetchColumn();
        if ($cnt == 0) {
            $defaultVenues = [
                ['name' => 'Marriot Hotel', 'code' => 'MAR-VNU', 'location' => 'Kigali Marriott Hotel — Grand Ballroom', 'address' => 'KN 3 Ave, Kigali', 'contact_person' => 'Events Concierge', 'contact_phone' => '+250 222 111 111'],
                ['name' => 'FourPoint Hotel', 'code' => 'FPT-VNU', 'location' => 'Four Points by Sheraton Kigali', 'address' => 'KN 3 Ave, Nyarugenge, Kigali', 'contact_person' => 'Banquet Lead', 'contact_phone' => '+250 788 123 456'],
                ['name' => 'MovenPick Hotel', 'code' => 'MVP-VNU', 'location' => 'Mövenpick Hotel Kigali — Conference Hall', 'address' => 'Boulevard de l\'Umuganda, Kigali', 'contact_person' => 'Hotel Operations', 'contact_phone' => '+250 788 654 321'],
                ['name' => 'BK Arena', 'code' => 'BKA-VNU', 'location' => 'BK Arena — Main Stage & Bowl', 'address' => 'KG 17 Ave, Remera, Kigali', 'contact_person' => 'Arena Tech Director', 'contact_phone' => '+250 788 999 888'],
                ['name' => 'Kigali Convention Centre', 'code' => 'KCC-VNU', 'location' => 'KCC — Auditorium & Dome', 'address' => 'KG 2 Roundabout, Kimihurura, Kigali', 'contact_person' => 'KCC Stage Manager', 'contact_phone' => '+250 252 555 555']
            ];
            $ins = $pdo->prepare("
                INSERT INTO venues 
                (name, code, location, address, contact_person, contact_phone, open_time, close_time, is_active)
                VALUES (?, ?, ?, ?, ?, ?, '07:00:00', '23:30:00', 1)
            ");
            foreach ($defaultVenues as $dv) {
                $ins->execute([$dv['name'], $dv['code'], $dv['location'], $dv['address'], $dv['contact_person'], $dv['contact_phone']]);
            }
        }
    } catch (Exception $e) {
        error_log("Error in checkAndFixVenuesTable: " . $e->getMessage());
    }
}
checkAndFixVenuesTable($pdo);

// Sync venue record to events table so gate scanner & terminal pick it up immediately
function syncVenueToEventsTable($pdo, $venueName, $locationStr, $openTimeStr = '08:00')
{
    try {
        $chk = $pdo->prepare("SELECT id FROM events WHERE LOWER(TRIM(title)) = LOWER(TRIM(?)) LIMIT 1");
        $chk->execute([$venueName]);
        $existing = $chk->fetch();
        if ($existing) {
            $upd = $pdo->prepare("UPDATE events SET location = ?, call_time = ? WHERE id = ?");
            $upd->execute([$locationStr, $openTimeStr, $existing['id']]);
        } else {
            $newId = 'vnu_' . substr(md5($venueName . time()), 0, 8);
            $ins = $pdo->prepare("INSERT INTO events (id, title, location, date, call_time, source) VALUES (?, ?, ?, CURDATE(), ?, 'venue')");
            $ins->execute([$newId, $venueName, $locationStr, $openTimeStr]);
        }
    } catch (Exception $e) {
        error_log("Error syncing venue to events: " . $e->getMessage());
    }
}

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

// Single Venue JSON API
if (isset($_GET['action']) && $_GET['action'] === 'get_venue') {
    header('Content-Type: application/json');
    $id = (int)($_GET['id'] ?? 0);
    $stmt = $pdo->prepare("SELECT * FROM venues WHERE id = ?");
    $stmt->execute([$id]);
    $v = $stmt->fetch();
    if ($v) {
        echo json_encode(['success' => true, 'venue' => $v]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Venue not found']);
    }
    exit();
}

// Single Event JSON API
if (isset($_GET['action']) && $_GET['action'] === 'get_event') {
    header('Content-Type: application/json');
    $id = trim($_GET['id'] ?? '');
    $stmt = $pdo->prepare("SELECT * FROM events WHERE id = ?");
    $stmt->execute([$id]);
    $ev = $stmt->fetch();
    if ($ev) {
        echo json_encode(['success' => true, 'event' => $ev]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Event not found']);
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
        } elseif ($action === 'add_venue') {
            if (empty($_POST['name']) || empty($_POST['location'])) {
                throw new Exception("Venue Name and Location / Hall are required fields.");
            }

            $name = trim($_POST['name']);
            $code = !empty($_POST['code']) ? trim($_POST['code']) : strtoupper(substr(preg_replace('/[^a-zA-Z]/', '', $name), 0, 3)) . '-VNU';
            $location = trim($_POST['location']);
            $address = trim($_POST['address'] ?? '');
            $contact_person = trim($_POST['contact_person'] ?? '');
            $contact_phone = trim($_POST['contact_phone'] ?? '');
            $contact_email = trim($_POST['contact_email'] ?? '');
            $open_time = !empty($_POST['open_time']) ? $_POST['open_time'] : '08:00:00';
            $close_time = !empty($_POST['close_time']) ? $_POST['close_time'] : '23:30:00';
            $description = trim($_POST['description'] ?? '');
            $is_active = isset($_POST['is_active']) ? 1 : 0;

            $imagePath = handleLocationUpload('image', null);

            $stmt = $pdo->prepare("
                INSERT INTO venues 
                (name, code, location, address, contact_person, contact_phone, contact_email, open_time, close_time, description, image, is_active)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$name, $code, $location, $address, $contact_person, $contact_phone, $contact_email, $open_time, $close_time, $description, $imagePath, $is_active]);

            syncVenueToEventsTable($pdo, $name, $location, substr($open_time, 0, 5));

            $response = [
                'success' => true,
                'message' => 'Venue added successfully!',
                'type' => 'success'
            ];
        } elseif ($action === 'edit_venue') {
            if (empty($_POST['id']) || empty($_POST['name']) || empty($_POST['location'])) {
                throw new Exception("Venue ID, Name, and Location are required.");
            }

            $id = (int)$_POST['id'];
            $curStmt = $pdo->prepare("SELECT * FROM venues WHERE id = ?");
            $curStmt->execute([$id]);
            $currentVenue = $curStmt->fetch();
            if (!$currentVenue) throw new Exception("Venue not found.");

            $name = trim($_POST['name']);
            $code = !empty($_POST['code']) ? trim($_POST['code']) : $currentVenue['code'];
            $location = trim($_POST['location']);
            $address = trim($_POST['address'] ?? '');
            $contact_person = trim($_POST['contact_person'] ?? '');
            $contact_phone = trim($_POST['contact_phone'] ?? '');
            $contact_email = trim($_POST['contact_email'] ?? '');
            $open_time = !empty($_POST['open_time']) ? $_POST['open_time'] : '08:00:00';
            $close_time = !empty($_POST['close_time']) ? $_POST['close_time'] : '23:30:00';
            $description = trim($_POST['description'] ?? '');
            $is_active = isset($_POST['is_active']) ? 1 : 0;

            $imagePath = $currentVenue['image'];
            if (!empty($_POST['remove_image']) && $_POST['remove_image'] == '1') {
                if (!empty($imagePath) && file_exists(__DIR__ . '/' . $imagePath) && strpos($imagePath, 'uploads/') === 0) {
                    @unlink(__DIR__ . '/' . $imagePath);
                }
                $imagePath = null;
            }
            if (isset($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
                $imagePath = handleLocationUpload('image', $imagePath);
            }

            $stmt = $pdo->prepare("
                UPDATE venues 
                SET name = ?, code = ?, location = ?, address = ?, contact_person = ?, contact_phone = ?,
                    contact_email = ?, open_time = ?, close_time = ?, description = ?, image = ?, is_active = ?
                WHERE id = ?
            ");
            $stmt->execute([$name, $code, $location, $address, $contact_person, $contact_phone, $contact_email, $open_time, $close_time, $description, $imagePath, $is_active, $id]);

            syncVenueToEventsTable($pdo, $name, $location, substr($open_time, 0, 5));

            $response = [
                'success' => true,
                'message' => 'Venue updated successfully!',
                'type' => 'success'
            ];
        } elseif ($action === 'delete_venue') {
            if (empty($_POST['id'])) throw new Exception("Venue ID required.");
            $id = (int)$_POST['id'];
            $curStmt = $pdo->prepare("SELECT * FROM venues WHERE id = ?");
            $curStmt->execute([$id]);
            $currentVenue = $curStmt->fetch();
            if ($currentVenue && !empty($currentVenue['image']) && file_exists(__DIR__ . '/' . $currentVenue['image']) && strpos($currentVenue['image'], 'uploads/') === 0) {
                @unlink(__DIR__ . '/' . $currentVenue['image']);
            }
            $del = $pdo->prepare("DELETE FROM venues WHERE id = ?");
            $del->execute([$id]);
            $response = ['success' => true, 'message' => 'Venue deleted successfully!', 'type' => 'success'];
        } elseif ($action === 'add_event') {
            if (empty($_POST['title']) || empty($_POST['location'])) {
                throw new Exception("Event Title and Venue / Location are required.");
            }

            $title = trim($_POST['title']);
            $location = trim($_POST['location']);
            $date = !empty($_POST['date']) ? trim($_POST['date']) : date('Y-m-d');
            $call_time = !empty($_POST['call_time']) ? trim($_POST['call_time']) : '08:00';
            $duration = !empty($_POST['duration']) ? (int)$_POST['duration'] : 1;
            $project_manager = trim($_POST['project_manager'] ?? '');
            $tech_lead = trim($_POST['tech_lead'] ?? '');
            $assigned_technician = trim($_POST['assigned_technician'] ?? '');
            $description = trim($_POST['description'] ?? '');

            $imagePath = handleLocationUpload('event_image', null);

            $id = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x', mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0x0fff) | 0x4000, mt_rand(0, 0x3fff) | 0x8000, mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff));

            $stmt = $pdo->prepare("
                INSERT INTO events 
                (id, title, date, call_time, duration, location, event_image, project_manager, tech_lead, assigned_technician, description, source)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'manual')
            ");
            $stmt->execute([$id, $title, $date, $call_time, $duration, $location, $imagePath, $project_manager, $tech_lead, $assigned_technician, $description]);

            $response = ['success' => true, 'message' => 'Event scheduled successfully!', 'type' => 'success'];
        } elseif ($action === 'edit_event') {
            if (empty($_POST['id']) || empty($_POST['title']) || empty($_POST['location'])) {
                throw new Exception("Event ID, Title, and Location are required.");
            }

            $id = trim($_POST['id']);
            $curStmt = $pdo->prepare("SELECT * FROM events WHERE id = ?");
            $curStmt->execute([$id]);
            $currentEvent = $curStmt->fetch();
            if (!$currentEvent) throw new Exception("Event not found.");

            $title = trim($_POST['title']);
            $location = trim($_POST['location']);
            $date = !empty($_POST['date']) ? trim($_POST['date']) : date('Y-m-d');
            $call_time = !empty($_POST['call_time']) ? trim($_POST['call_time']) : '08:00';
            $duration = !empty($_POST['duration']) ? (int)$_POST['duration'] : 1;
            $project_manager = trim($_POST['project_manager'] ?? '');
            $tech_lead = trim($_POST['tech_lead'] ?? '');
            $assigned_technician = trim($_POST['assigned_technician'] ?? '');
            $description = trim($_POST['description'] ?? '');

            $imagePath = $currentEvent['event_image'];
            if (!empty($_POST['remove_image']) && $_POST['remove_image'] == '1') {
                if (!empty($imagePath) && file_exists(__DIR__ . '/' . $imagePath) && strpos($imagePath, 'uploads/') === 0) {
                    @unlink(__DIR__ . '/' . $imagePath);
                }
                $imagePath = null;
            }
            if (isset($_FILES['event_image']) && $_FILES['event_image']['error'] !== UPLOAD_ERR_NO_FILE) {
                $imagePath = handleLocationUpload('event_image', $imagePath);
            }

            $stmt = $pdo->prepare("
                UPDATE events 
                SET title = ?, date = ?, call_time = ?, duration = ?, location = ?, event_image = ?,
                    project_manager = ?, tech_lead = ?, assigned_technician = ?, description = ?
                WHERE id = ?
            ");
            $stmt->execute([$title, $date, $call_time, $duration, $location, $imagePath, $project_manager, $tech_lead, $assigned_technician, $description, $id]);

            $response = ['success' => true, 'message' => 'Event updated successfully!', 'type' => 'success'];
        } elseif ($action === 'delete_event') {
            if (empty($_POST['id'])) throw new Exception("Event ID required.");
            $id = trim($_POST['id']);
            $curStmt = $pdo->prepare("SELECT * FROM events WHERE id = ?");
            $curStmt->execute([$id]);
            $currentEvent = $curStmt->fetch();
            if ($currentEvent && !empty($currentEvent['event_image']) && file_exists(__DIR__ . '/' . $currentEvent['event_image']) && strpos($currentEvent['event_image'], 'uploads/') === 0) {
                @unlink(__DIR__ . '/' . $currentEvent['event_image']);
            }
            $del = $pdo->prepare("DELETE FROM events WHERE id = ?");
            $del->execute([$id]);
            $response = ['success' => true, 'message' => 'Event deleted successfully!', 'type' => 'success'];
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

// Load Venues from venues table
$venuesList = [];
try {
    $vStmt = $pdo->query("SELECT * FROM venues ORDER BY is_active DESC, name ASC");
    $venuesList = $vStmt->fetchAll();
} catch (Exception $e) {
    $venuesList = [];
}

// Load Events from events table
$eventsList = [];
try {
    $eStmt = $pdo->query("
        SELECT id, title, location, date, call_time, duration, project_manager, tech_lead, assigned_technician, event_image, description, source
        FROM events
        ORDER BY date DESC, id DESC
        LIMIT 40
    ");
    $eventsList = $eStmt->fetchAll();
} catch (Exception $e) {
    $eventsList = [];
}

// Load Users for Event PM / Tech Lead assignment
$usersList = [];
try {
    $uStmt = $pdo->query("SELECT id, username, full_name, role FROM users WHERE is_active = 1 ORDER BY COALESCE(NULLIF(full_name, ''), username) ASC");
    $usersList = $uStmt->fetchAll();
} catch (Exception $e) {
    $usersList = [];
}

$pageTitle = "Stock Locations, Venues & Events - aBility";
$showBreadcrumb = true;
$breadcrumbItems = [
    'Dashboard' => 'dashboard_full.php',
    'Setup' => '#',
    'Stock Locations, Venues & Events' => ''
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
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-3">
        <div>
            <h1 class="h3 mb-1 text-gray-800 fw-bold">
                <i class="fas fa-warehouse me-2 text-primary"></i>Stock Locations, Venues &amp; Events
            </h1>
            <p class="text-muted small mb-0">Manage floor warehouses, hotel &amp; arena venues, production events, controllers, and working schedules.</p>
        </div>

        <div class="d-flex flex-wrap align-items-center gap-2">
            <form method="GET" action="stock_locations.php" class="d-flex align-items-center gap-2">
                <div class="input-group input-group-sm" style="max-width: 250px;">
                    <input type="text" name="search" class="form-control" placeholder="Search facilities..." value="<?php echo htmlspecialchars($search); ?>">
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

            <div class="dropdown">
                <button class="btn btn-sm btn-primary dropdown-toggle fw-bold" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="fas fa-plus me-1"></i>Add Checkpoint
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow border-0">
                    <li><a class="dropdown-item fw-semibold py-2" href="javascript:void(0)" onclick="openAddLocation()"><i class="fas fa-warehouse text-success me-2"></i>Add Stock Warehouse</a></li>
                    <li><a class="dropdown-item fw-semibold py-2" href="javascript:void(0)" onclick="openAddVenue()"><i class="fas fa-hotel text-info me-2"></i>Add Venue (Hotel / Arena)</a></li>
                    <li><a class="dropdown-item fw-semibold py-2" href="javascript:void(0)" onclick="openAddEvent()"><i class="fas fa-calendar-star text-warning me-2"></i>Add Scheduled Event</a></li>
                </ul>
            </div>
        </div>
    </div>

    <!-- KPI Metric Cards -->
    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm rounded-3 p-3 bg-white h-100 border-start border-4 border-success">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-muted small fw-bold text-uppercase">Stock Warehouses</div>
                        <div class="fs-4 fw-bold text-dark"><?php echo count($locations); ?> <span class="fs-6 text-muted fw-normal">Facilities</span></div>
                        <div class="small text-muted">Floor storage &amp; dispatch</div>
                    </div>
                    <div class="rounded-circle bg-success-subtle text-success d-flex align-items-center justify-content-center" style="width: 48px; height: 48px;">
                        <i class="fas fa-warehouse fs-5"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm rounded-3 p-3 bg-white h-100 border-start border-4 border-info">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-muted small fw-bold text-uppercase">Venues</div>
                        <div class="fs-4 fw-bold text-dark"><?php echo count($venuesList); ?> <span class="fs-6 text-muted fw-normal">Hotels &amp; Arenas</span></div>
                        <div class="small text-muted">Ballrooms, centers &amp; stages</div>
                    </div>
                    <div class="rounded-circle bg-info-subtle text-info d-flex align-items-center justify-content-center" style="width: 48px; height: 48px;">
                        <i class="fas fa-hotel fs-5"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm rounded-3 p-3 bg-white h-100 border-start border-4 border-warning">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-muted small fw-bold text-uppercase">Events &amp; Productions</div>
                        <div class="fs-4 fw-bold text-dark"><?php echo count($eventsList); ?> <span class="fs-6 text-muted fw-normal">Live Shows</span></div>
                        <div class="small text-muted">Scheduled client productions</div>
                    </div>
                    <div class="rounded-circle bg-warning-subtle text-warning d-flex align-items-center justify-content-center" style="width: 48px; height: 48px;">
                        <i class="fas fa-calendar-check fs-5"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm rounded-3 p-3 bg-white h-100 border-start border-4 border-primary">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-muted small fw-bold text-uppercase">Gate Terminals</div>
                        <div class="fs-4 fw-bold text-dark"><?php echo count($locations) + count($venuesList); ?> <span class="fs-6 text-muted fw-normal">Access Points</span></div>
                        <div class="small text-muted">Live scanner checkpoints</div>
                    </div>
                    <div class="rounded-circle bg-primary-subtle text-primary d-flex align-items-center justify-content-center" style="width: 48px; height: 48px;">
                        <i class="fas fa-tower-broadcast fs-5"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Navigation Filter Toolbar Strip -->
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-4 p-2 rounded-3 bg-white border shadow-sm">
        <div class="btn-group btn-group-sm" role="group" id="checkpointFilterGroup">
            <button type="button" class="btn btn-outline-dark active fw-bold px-3 py-2" onclick="filterCheckpointView('all', this)">
                <i class="fas fa-layer-group me-1"></i>All Checkpoints
            </button>
            <button type="button" class="btn btn-outline-success fw-bold px-3 py-2" onclick="filterCheckpointView('stock', this)">
                <i class="fas fa-warehouse me-1"></i>Stock Warehouses (<?php echo count($locations); ?>)
            </button>
            <button type="button" class="btn btn-outline-primary fw-bold px-3 py-2" onclick="filterCheckpointView('split', this)">
                <i class="fas fa-columns me-1"></i>Venues &amp; Events (col-lg-6 Split)
            </button>
            <button type="button" class="btn btn-outline-info fw-bold px-3 py-2" onclick="filterCheckpointView('venues', this)">
                <i class="fas fa-hotel me-1"></i>Venues Only (<?php echo count($venuesList); ?>)
            </button>
            <button type="button" class="btn btn-outline-warning text-dark fw-bold px-3 py-2" onclick="filterCheckpointView('events', this)">
                <i class="fas fa-calendar-star me-1"></i>Events Only (<?php echo count($eventsList); ?>)
            </button>
        </div>

        <div class="d-flex gap-2">
            <button type="button" class="btn btn-sm btn-outline-success fw-bold d-flex align-items-center gap-1" onclick="openAddLocation()">
                <i class="fas fa-plus"></i>
                <span>Add Warehouse</span>
            </button>
            <button type="button" class="btn btn-sm btn-outline-info text-dark fw-bold d-flex align-items-center gap-1" onclick="openAddVenue()">
                <i class="fas fa-plus"></i>
                <span>Add Venue</span>
            </button>
            <button type="button" class="btn btn-sm btn-outline-warning text-dark fw-bold d-flex align-items-center gap-1" onclick="openAddEvent()">
                <i class="fas fa-plus"></i>
                <span>Add Event</span>
            </button>
        </div>
    </div>

    <!-- Section 1: Stock Warehouses -->
    <div id="sectionStockWarehouses" class="checkpoint-section mb-5">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h4 class="h5 fw-bold text-dark mb-0">
                <i class="fas fa-warehouse text-success me-2"></i>Stock Warehouses &amp; Floor Depots
            </h4>
            <span class="badge bg-success-subtle text-success border border-success-subtle fw-semibold">
                <?php echo count($locations); ?> Facilities Configured
            </span>
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

    <!-- ==================== SECTION 2: VENUES (col-lg-6) & EVENTS (col-lg-6) ==================== -->
    <div id="sectionVenuesEvents" class="checkpoint-section mb-5">
        <div class="row g-4">
            <!-- ==================== VENUES SECTION (col-lg-6) ==================== -->
            <div class="col-lg-6" id="venuesColumn">
                <div class="card border-0 shadow-sm rounded-4 h-100 bg-white">
                    <div class="card-header bg-white py-3 px-4 border-bottom d-flex flex-wrap justify-content-between align-items-center gap-2">
                        <div>
                            <div class="d-flex align-items-center gap-2">
                                <h4 class="h5 fw-bold text-dark mb-0">
                                    <i class="fas fa-hotel text-info me-2"></i>Venues
                                </h4>
                                <span class="badge bg-info-subtle text-info border border-info-subtle small fw-semibold" id="venuesCountBadge">
                                    <?php echo count($venuesList); ?> Venues
                                </span>
                            </div>
                            <div class="small text-muted">Permanent hotel ballrooms, convention centers &amp; stages</div>
                        </div>

                        <div class="d-flex align-items-center gap-2">
                            <button type="button" class="btn btn-sm btn-info text-white fw-bold d-flex align-items-center gap-1" onclick="openAddVenue()">
                                <i class="fas fa-plus"></i>
                                <span>Add Venue</span>
                            </button>
                        </div>
                    </div>

                    <div class="card-body p-3">
                        <!-- Venue Search -->
                        <div class="input-group input-group-sm mb-3">
                            <span class="input-group-text bg-light text-muted"><i class="fas fa-search"></i></span>
                            <input type="text" id="venueSearchBox" class="form-control" placeholder="Filter venues by name, hall, address..." oninput="filterVenuesList()">
                            <button class="btn btn-outline-secondary" type="button" onclick="document.getElementById('venueSearchBox').value='';filterVenuesList();">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>

                        <!-- Venues List -->
                        <div class="overflow-auto pe-1" style="max-height: 680px;" id="venuesListContainer">
                            <?php if (empty($venuesList)): ?>
                                <div class="text-center py-4 text-muted">
                                    <i class="fas fa-hotel fa-3x mb-2 opacity-50"></i>
                                    <div>No venues configured yet. Click "Add Venue" to create one.</div>
                                </div>
                            <?php else: ?>
                                <?php foreach ($venuesList as $v):
                                    $vId = (int)$v['id'];
                                    $vHasImage = !empty($v['image']) && file_exists(__DIR__ . '/' . $v['image']);
                                    $vSearch = strtolower($v['name'] . ' ' . ($v['code'] ?? '') . ' ' . ($v['location'] ?? '') . ' ' . ($v['address'] ?? '') . ' ' . ($v['contact_person'] ?? ''));
                                ?>
                                    <div class="card location-card border mb-3 venue-item-card shadow-sm" data-search="<?php echo htmlspecialchars($vSearch); ?>">
                                        <div class="row g-0">
                                            <div class="col-sm-4 location-media-col text-white position-relative"
                                                 style="<?php echo !$vHasImage ? ($v['is_active'] ? 'background: linear-gradient(135deg, #0284c7 0%, #0369a1 100%);' : 'background: #64748b;') : 'background: #0f172a;'; ?> min-height: 160px;">
                                                <?php if ($vHasImage): ?>
                                                    <img src="<?php echo htmlspecialchars($v['image']); ?>" alt="<?php echo htmlspecialchars($v['name']); ?>" class="location-media-img" style="min-height: 160px;">
                                                    <button type="button" class="location-zoom-btn" onclick="previewLocationImage('<?php echo htmlspecialchars($v['image']); ?>', '<?php echo htmlspecialchars(addslashes($v['name'])); ?>')" title="Zoom photo">
                                                        <i class="fas fa-search-plus" style="font-size: 0.75rem;"></i>
                                                    </button>
                                                <?php else: ?>
                                                    <div class="text-center p-3">
                                                        <i class="fas fa-hotel fa-3x text-white opacity-75"></i>
                                                    </div>
                                                <?php endif; ?>
                                                <span class="badge position-absolute top-0 start-0 m-2 <?php echo $v['is_active'] ? 'bg-success' : 'bg-secondary'; ?>" style="font-size: 0.65rem;">
                                                    <?php echo $v['is_active'] ? 'ACTIVE' : 'INACTIVE'; ?>
                                                </span>
                                            </div>

                                            <div class="col-sm-8 p-3 d-flex flex-column justify-content-between">
                                                <div>
                                                    <div class="d-flex justify-content-between align-items-start mb-1">
                                                        <h6 class="fw-bold text-dark mb-0 text-truncate" title="<?php echo htmlspecialchars($v['name']); ?>">
                                                            <?php echo htmlspecialchars($v['name']); ?>
                                                        </h6>
                                                        <?php if (!empty($v['code'])): ?>
                                                            <span class="badge bg-dark font-monospace" style="font-size: 0.68rem;"><?php echo htmlspecialchars($v['code']); ?></span>
                                                        <?php endif; ?>
                                                    </div>

                                                    <?php if (!empty($v['location'])): ?>
                                                        <div class="small text-muted mb-1 text-truncate" title="<?php echo htmlspecialchars($v['location']); ?>">
                                                            <i class="fas fa-location-dot text-danger me-1"></i><?php echo htmlspecialchars($v['location']); ?>
                                                        </div>
                                                    <?php endif; ?>

                                                    <div class="small text-secondary mb-1">
                                                        <i class="fas fa-clock text-info me-1"></i>
                                                        <?php echo date('h:i A', strtotime($v['open_time'])); ?> – <?php echo date('h:i A', strtotime($v['close_time'])); ?>
                                                    </div>

                                                    <?php if (!empty($v['contact_person']) || !empty($v['contact_phone'])): ?>
                                                        <div class="small text-muted text-truncate" style="font-size: 0.78rem;">
                                                            <i class="fas fa-user-tie text-secondary me-1"></i>
                                                            <?php echo htmlspecialchars($v['contact_person'] ?: 'Concierge'); ?>
                                                            <?php if (!empty($v['contact_phone'])): ?>
                                                                (<?php echo htmlspecialchars($v['contact_phone']); ?>)
                                                            <?php endif; ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>

                                                <div class="mt-2 pt-2 border-top d-flex justify-content-between align-items-center">
                                                    <div class="d-flex gap-1">
                                                        <a href="venue_scanner.php?type=event_venue&id=<?php echo $vId; ?>" class="btn btn-xs btn-outline-success fw-bold px-2 py-1" style="font-size: 0.72rem;" title="Open Scanner Kiosk">
                                                            <i class="fas fa-barcode me-1"></i>Scanner
                                                        </a>
                                                        <a href="id_badges.php?tab=kiosk&checkpoint_type=event_venue&checkpoint_id=<?php echo $vId; ?>" class="btn btn-xs btn-outline-dark fw-bold px-2 py-1" style="font-size: 0.72rem;" title="Open Gate Terminal">
                                                            <i class="fas fa-id-badge me-1"></i>Terminal
                                                        </a>
                                                    </div>
                                                    <div class="d-flex gap-1">
                                                        <button type="button" class="btn btn-xs btn-outline-primary px-2 py-1" onclick="openEditVenue(<?php echo $vId; ?>)" title="Edit Venue">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                        <button type="button" class="btn btn-xs btn-outline-danger px-2 py-1" onclick="openDeleteVenue(<?php echo $vId; ?>, '<?php echo htmlspecialchars(addslashes($v['name'])); ?>')" title="Delete Venue">
                                                            <i class="fas fa-trash-alt"></i>
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                            <div id="noVenuesFoundMsg" class="text-center py-4 text-muted d-none">
                                <i class="fas fa-search fa-2x mb-2 opacity-50"></i>
                                <div class="small">No venues match your search.</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ==================== EVENTS SECTION (col-lg-6) ==================== -->
            <div class="col-lg-6" id="eventsColumn">
                <div class="card border-0 shadow-sm rounded-4 h-100 bg-white">
                    <div class="card-header bg-white py-3 px-4 border-bottom d-flex flex-wrap justify-content-between align-items-center gap-2">
                        <div>
                            <div class="d-flex align-items-center gap-2">
                                <h4 class="h5 fw-bold text-dark mb-0">
                                    <i class="fas fa-calendar-star text-warning me-2"></i>Events
                                </h4>
                                <span class="badge bg-warning-subtle text-warning border border-warning-subtle small fw-semibold" id="eventsCountBadge">
                                    <?php echo count($eventsList); ?> Productions
                                </span>
                            </div>
                            <div class="small text-muted">Scheduled client productions, calltimes &amp; assigned crew</div>
                        </div>

                        <div class="d-flex align-items-center gap-2">
                            <button type="button" class="btn btn-sm btn-warning text-dark fw-bold d-flex align-items-center gap-1" onclick="openAddEvent()">
                                <i class="fas fa-plus"></i>
                                <span>Add Event</span>
                            </button>
                        </div>
                    </div>

                    <div class="card-body p-3">
                        <!-- Event Search -->
                        <div class="input-group input-group-sm mb-3">
                            <span class="input-group-text bg-light text-muted"><i class="fas fa-search"></i></span>
                            <input type="text" id="eventSearchBox" class="form-control" placeholder="Filter events by title, venue, PM, tech lead..." oninput="filterEventsList()">
                            <button class="btn btn-outline-secondary" type="button" onclick="document.getElementById('eventSearchBox').value='';filterEventsList();">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>

                        <!-- Events List -->
                        <div class="overflow-auto pe-1" style="max-height: 680px;" id="eventsListContainer">
                            <?php if (empty($eventsList)): ?>
                                <div class="text-center py-4 text-muted">
                                    <i class="fas fa-calendar-check fa-3x mb-2 opacity-50"></i>
                                    <div>No events scheduled yet. Click "Add Event" to create one.</div>
                                </div>
                            <?php else: ?>
                                <?php foreach ($eventsList as $ev):
                                    $evId = $ev['id'];
                                    $evNumId = normalizeEventNumericId($evId);
                                    $evHasImage = !empty($ev['event_image']) && file_exists(__DIR__ . '/' . $ev['event_image']);
                                    $evSearch = strtolower($ev['title'] . ' ' . ($ev['location'] ?? '') . ' ' . ($ev['project_manager'] ?? '') . ' ' . ($ev['tech_lead'] ?? '') . ' ' . ($ev['assigned_technician'] ?? ''));
                                    $evDateStr = !empty($ev['date']) ? date('M d, Y', strtotime($ev['date'])) : 'Today';
                                ?>
                                    <div class="card location-card border mb-3 event-item-card shadow-sm" data-search="<?php echo htmlspecialchars($evSearch); ?>">
                                        <div class="row g-0">
                                            <div class="col-sm-4 location-media-col text-white position-relative"
                                                 style="<?php echo !$evHasImage ? 'background: linear-gradient(135deg, #d97706 0%, #b45309 100%);' : 'background: #0f172a;'; ?> min-height: 160px;">
                                                <?php if ($evHasImage): ?>
                                                    <img src="<?php echo htmlspecialchars($ev['event_image']); ?>" alt="<?php echo htmlspecialchars($ev['title']); ?>" class="location-media-img" style="min-height: 160px;">
                                                    <button type="button" class="location-zoom-btn" onclick="previewLocationImage('<?php echo htmlspecialchars($ev['event_image']); ?>', '<?php echo htmlspecialchars(addslashes($ev['title'])); ?>')" title="Zoom photo">
                                                        <i class="fas fa-search-plus" style="font-size: 0.75rem;"></i>
                                                    </button>
                                                <?php else: ?>
                                                    <div class="text-center p-3">
                                                        <i class="fas fa-calendar-check fa-3x text-white opacity-75"></i>
                                                    </div>
                                                <?php endif; ?>
                                                <span class="badge position-absolute top-0 start-0 m-2 bg-dark border border-secondary" style="font-size: 0.65rem;">
                                                    <?php echo htmlspecialchars($evDateStr); ?>
                                                </span>
                                            </div>

                                            <div class="col-sm-8 p-3 d-flex flex-column justify-content-between">
                                                <div>
                                                    <div class="d-flex justify-content-between align-items-start mb-1">
                                                        <h6 class="fw-bold text-dark mb-0 text-truncate" title="<?php echo htmlspecialchars($ev['title']); ?>">
                                                            <?php echo htmlspecialchars($ev['title']); ?>
                                                        </h6>
                                                        <span class="badge bg-warning-subtle text-dark border border-warning" style="font-size: 0.68rem;">
                                                            <?php echo htmlspecialchars($ev['call_time'] ?: '08:00'); ?>
                                                        </span>
                                                    </div>

                                                    <?php if (!empty($ev['location'])): ?>
                                                        <div class="small text-muted mb-1 text-truncate" title="<?php echo htmlspecialchars($ev['location']); ?>">
                                                            <i class="fas fa-map-pin text-primary me-1"></i><?php echo htmlspecialchars($ev['location']); ?>
                                                        </div>
                                                    <?php endif; ?>

                                                    <div class="small text-secondary mb-1" style="font-size: 0.78rem;">
                                                        <i class="fas fa-user-tie text-secondary me-1"></i>
                                                        PM: <strong><?php echo htmlspecialchars($ev['project_manager'] ?: 'Ops'); ?></strong>
                                                        <?php if (!empty($ev['tech_lead'])): ?>
                                                            • Tech: <strong><?php echo htmlspecialchars($ev['tech_lead']); ?></strong>
                                                        <?php endif; ?>
                                                    </div>

                                                    <?php if (!empty($ev['description'])): ?>
                                                        <div class="small text-muted text-truncate" style="font-size: 0.75rem;">
                                                            <?php echo htmlspecialchars(mb_strimwidth($ev['description'], 0, 50, '...')); ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>

                                                <div class="mt-2 pt-2 border-top d-flex justify-content-between align-items-center">
                                                    <div class="d-flex gap-1">
                                                        <a href="venue_scanner.php?type=event_venue&id=<?php echo $evNumId; ?>" class="btn btn-xs btn-outline-success fw-bold px-2 py-1" style="font-size: 0.72rem;" title="Open Scanner Kiosk">
                                                            <i class="fas fa-barcode me-1"></i>Scanner
                                                        </a>
                                                        <a href="id_badges.php?tab=kiosk&checkpoint_type=event_venue&checkpoint_id=<?php echo $evNumId; ?>" class="btn btn-xs btn-outline-dark fw-bold px-2 py-1" style="font-size: 0.72rem;" title="Open Gate Terminal">
                                                            <i class="fas fa-id-badge me-1"></i>Terminal
                                                        </a>
                                                    </div>
                                                    <div class="d-flex gap-1">
                                                        <button type="button" class="btn btn-xs btn-outline-primary px-2 py-1" onclick="openEditEvent('<?php echo $evId; ?>')" title="Edit Event">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                        <button type="button" class="btn btn-xs btn-outline-danger px-2 py-1" onclick="openDeleteEvent('<?php echo $evId; ?>', '<?php echo htmlspecialchars(addslashes($ev['title'])); ?>')" title="Delete Event">
                                                            <i class="fas fa-trash-alt"></i>
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                            <div id="noEventsFoundMsg" class="text-center py-4 text-muted d-none">
                                <i class="fas fa-search fa-2x mb-2 opacity-50"></i>
                                <div class="small">No events match your search.</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
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

<!-- ==================== ADD VENUE MODAL ==================== -->
<div class="modal fade" id="addVenueModal" tabindex="-1" aria-labelledby="addVenueModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <form id="addVenueForm" enctype="multipart/form-data">
                <input type="hidden" name="action" value="add_venue">

                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title fw-bold" id="addVenueModalLabel">
                        <i class="fas fa-hotel me-2"></i>Add Venue (Hotel / Arena)
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-md-7">
                            <label for="add_v_name" class="form-label fw-bold small">Venue Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control form-control-sm" id="add_v_name" name="name" placeholder="e.g. Kigali Marriott Hotel" required>
                        </div>
                        <div class="col-md-5">
                            <label for="add_v_code" class="form-label fw-bold small">Venue Code</label>
                            <input type="text" class="form-control form-control-sm font-monospace" id="add_v_code" name="code" placeholder="e.g. MAR-VNU">
                        </div>

                        <div class="col-md-6">
                            <label for="add_v_location" class="form-label fw-bold small">Hall / Stage / Specific Area <span class="text-danger">*</span></label>
                            <input type="text" class="form-control form-control-sm" id="add_v_location" name="location" placeholder="e.g. Grand Ballroom, 2nd Floor" required>
                        </div>
                        <div class="col-md-6">
                            <label for="add_v_address" class="form-label fw-bold small">Physical Address / City</label>
                            <input type="text" class="form-control form-control-sm" id="add_v_address" name="address" placeholder="e.g. KN 3 Ave, Nyarugenge, Kigali">
                        </div>

                        <div class="col-md-4">
                            <label for="add_v_contact_person" class="form-label fw-bold small">Contact Person</label>
                            <input type="text" class="form-control form-control-sm" id="add_v_contact_person" name="contact_person" placeholder="e.g. Events Director">
                        </div>
                        <div class="col-md-4">
                            <label for="add_v_contact_phone" class="form-label fw-bold small">Contact Phone</label>
                            <input type="tel" class="form-control form-control-sm" id="add_v_contact_phone" name="contact_phone" placeholder="e.g. +250 788 111 222">
                        </div>
                        <div class="col-md-4">
                            <label for="add_v_contact_email" class="form-label fw-bold small">Contact Email</label>
                            <input type="email" class="form-control form-control-sm" id="add_v_contact_email" name="contact_email" placeholder="e.g. events@hotel.com">
                        </div>

                        <div class="col-md-6">
                            <label for="add_v_open_time" class="form-label fw-bold small">Opening / Access Time</label>
                            <input type="time" class="form-control form-control-sm" id="add_v_open_time" name="open_time" value="08:00">
                        </div>
                        <div class="col-md-6">
                            <label for="add_v_close_time" class="form-label fw-bold small">Closing Time</label>
                            <input type="time" class="form-control form-control-sm" id="add_v_close_time" name="close_time" value="23:30">
                        </div>

                        <div class="col-12">
                            <label for="add_v_description" class="form-label fw-bold small">Description / Stage Access Notes</label>
                            <textarea class="form-control form-control-sm" id="add_v_description" name="description" rows="2" placeholder="Loading dock location, sound restrictions, gate badge requirements..."></textarea>
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-bold small">Venue Photo</label>
                            <div class="image-preview-zone" onclick="document.getElementById('add_v_image_input').click()">
                                <input type="file" id="add_v_image_input" name="image" accept="image/*" class="d-none" onchange="previewSelectedImage(this, 'add_v_image_preview', 'add_v_image_container')">
                                <div id="add_v_image_container" class="text-center">
                                    <i class="fas fa-cloud-upload-alt fa-2x text-muted mb-2"></i>
                                    <p class="small text-muted mb-1">Click to upload photo of this venue (PNG, JPG, WEBP - Max 5MB)</p>
                                </div>
                                <div id="add_v_image_preview" class="d-none text-center mt-2 position-relative">
                                    <img src="" alt="Preview" class="image-preview-thumbnail">
                                    <div class="mt-2">
                                        <button type="button" class="btn btn-xs btn-outline-danger" onclick="event.stopPropagation(); clearImageSelection('add_v_image_input', 'add_v_image_preview', 'add_v_image_container');">
                                            <i class="fas fa-times me-1"></i> Remove Selected Photo
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-12">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="add_v_is_active" name="is_active" checked>
                                <label class="form-check-label fw-bold small" for="add_v_is_active">Venue Active (Available for Gate Scans)</label>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer bg-light px-4 py-3">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-info text-white fw-bold">
                        <i class="fas fa-check me-1"></i> Save Venue
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ==================== EDIT VENUE MODAL ==================== -->
<div class="modal fade" id="editVenueModal" tabindex="-1" aria-labelledby="editVenueModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <form id="editVenueForm" enctype="multipart/form-data">
                <input type="hidden" name="action" value="edit_venue">
                <input type="hidden" name="id" id="edit_v_id">
                <input type="hidden" name="remove_image" id="edit_v_remove_image" value="0">

                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title fw-bold" id="editVenueModalLabel">
                        <i class="fas fa-edit me-2"></i>Edit Venue Details
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-md-7">
                            <label for="edit_v_name" class="form-label fw-bold small">Venue Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control form-control-sm" id="edit_v_name" name="name" required>
                        </div>
                        <div class="col-md-5">
                            <label for="edit_v_code" class="form-label fw-bold small">Venue Code</label>
                            <input type="text" class="form-control form-control-sm font-monospace" id="edit_v_code" name="code">
                        </div>

                        <div class="col-md-6">
                            <label for="edit_v_location" class="form-label fw-bold small">Hall / Stage / Area <span class="text-danger">*</span></label>
                            <input type="text" class="form-control form-control-sm" id="edit_v_location" name="location" required>
                        </div>
                        <div class="col-md-6">
                            <label for="edit_v_address" class="form-label fw-bold small">Physical Address / City</label>
                            <input type="text" class="form-control form-control-sm" id="edit_v_address" name="address">
                        </div>

                        <div class="col-md-4">
                            <label for="edit_v_contact_person" class="form-label fw-bold small">Contact Person</label>
                            <input type="text" class="form-control form-control-sm" id="edit_v_contact_person" name="contact_person">
                        </div>
                        <div class="col-md-4">
                            <label for="edit_v_contact_phone" class="form-label fw-bold small">Contact Phone</label>
                            <input type="tel" class="form-control form-control-sm" id="edit_v_contact_phone" name="contact_phone">
                        </div>
                        <div class="col-md-4">
                            <label for="edit_v_contact_email" class="form-label fw-bold small">Contact Email</label>
                            <input type="email" class="form-control form-control-sm" id="edit_v_contact_email" name="contact_email">
                        </div>

                        <div class="col-md-6">
                            <label for="edit_v_open_time" class="form-label fw-bold small">Opening Time</label>
                            <input type="time" class="form-control form-control-sm" id="edit_v_open_time" name="open_time">
                        </div>
                        <div class="col-md-6">
                            <label for="edit_v_close_time" class="form-label fw-bold small">Closing Time</label>
                            <input type="time" class="form-control form-control-sm" id="edit_v_close_time" name="close_time">
                        </div>

                        <div class="col-12">
                            <label for="edit_v_description" class="form-label fw-bold small">Description / Notes</label>
                            <textarea class="form-control form-control-sm" id="edit_v_description" name="description" rows="2"></textarea>
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-bold small">Venue Photo</label>
                            <div id="edit_v_current_image_section" class="mb-2 p-2 border rounded bg-light d-flex align-items-center justify-content-between d-none">
                                <div class="d-flex align-items-center gap-2">
                                    <img id="edit_v_current_image_thumb" src="" alt="Current Photo" style="width: 50px; height: 50px; object-fit: cover; border-radius: 6px;">
                                    <div>
                                        <div class="small fw-bold">Current Venue Photo</div>
                                        <div class="text-muted" style="font-size: 0.75rem;">Will be kept unless replaced or removed</div>
                                    </div>
                                </div>
                                <button type="button" class="btn btn-xs btn-outline-danger" onclick="markRemoveCurrentVenueImage()">
                                    <i class="fas fa-trash-alt me-1"></i> Remove Photo
                                </button>
                            </div>

                            <div class="image-preview-zone" onclick="document.getElementById('edit_v_image_input').click()">
                                <input type="file" id="edit_v_image_input" name="image" accept="image/*" class="d-none" onchange="previewSelectedImage(this, 'edit_v_image_preview', 'edit_v_image_container')">
                                <div id="edit_v_image_container" class="text-center">
                                    <i class="fas fa-cloud-upload-alt fa-2x text-muted mb-2"></i>
                                    <p class="small text-muted mb-1" id="edit_v_upload_label">Click to upload new photo</p>
                                </div>
                                <div id="edit_v_image_preview" class="d-none text-center mt-2 position-relative">
                                    <img src="" alt="Preview" class="image-preview-thumbnail">
                                    <div class="mt-2">
                                        <button type="button" class="btn btn-xs btn-outline-danger" onclick="event.stopPropagation(); clearImageSelection('edit_v_image_input', 'edit_v_image_preview', 'edit_v_image_container');">
                                            <i class="fas fa-times me-1"></i> Cancel New Photo
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-12">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="edit_v_is_active" name="is_active">
                                <label class="form-check-label fw-bold small" for="edit_v_is_active">Venue Active</label>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer bg-light px-4 py-3">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-info text-white fw-bold">
                        <i class="fas fa-save me-1"></i> Update Venue
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ==================== DELETE VENUE MODAL ==================== -->
<div class="modal fade" id="deleteVenueModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <form id="deleteVenueForm">
                <input type="hidden" name="action" value="delete_venue">
                <input type="hidden" name="id" id="delete_v_id">

                <div class="modal-header bg-danger text-white py-3">
                    <h5 class="modal-title fw-bold">
                        <i class="fas fa-exclamation-triangle me-2"></i>Delete Venue
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body p-4 text-center">
                    <i class="fas fa-hotel fa-3x text-danger mb-3"></i>
                    <h5 class="fw-bold mb-2">Confirm Venue Deletion</h5>
                    <p class="text-muted small mb-0">Are you sure you want to remove <strong id="delete_v_name" class="text-dark"></strong> from configured venues?</p>
                </div>

                <div class="modal-footer bg-light px-4 py-2">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-danger fw-bold">
                        <i class="fas fa-trash-alt me-1"></i> Delete Venue
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ==================== ADD EVENT MODAL ==================== -->
<div class="modal fade" id="addEventModal" tabindex="-1" aria-labelledby="addEventModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <form id="addEventForm" enctype="multipart/form-data">
                <input type="hidden" name="action" value="add_event">

                <div class="modal-header bg-warning text-dark">
                    <h5 class="modal-title fw-bold" id="addEventModalLabel">
                        <i class="fas fa-calendar-plus me-2"></i>Add Scheduled Event
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-md-7">
                            <label for="add_e_title" class="form-label fw-bold small">Event Title / Show Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control form-control-sm" id="add_e_title" name="title" placeholder="e.g. World Bank Annual Conference" required>
                        </div>
                        <div class="col-md-5">
                            <label for="add_e_location" class="form-label fw-bold small">Assigned Venue / Location <span class="text-danger">*</span></label>
                            <input type="text" class="form-control form-control-sm" id="add_e_location" name="location" list="venueSuggestionsList" placeholder="e.g. Marriot Hotel" required>
                            <datalist id="venueSuggestionsList">
                                <?php foreach ($venuesList as $vl): ?>
                                    <option value="<?php echo htmlspecialchars($vl['name']); ?>">
                                <?php endforeach; ?>
                            </datalist>
                        </div>

                        <div class="col-md-4">
                            <label for="add_e_date" class="form-label fw-bold small">Event Date</label>
                            <input type="date" class="form-control form-control-sm" id="add_e_date" name="date" value="<?php echo date('Y-m-d'); ?>">
                        </div>
                        <div class="col-md-4">
                            <label for="add_e_call_time" class="form-label fw-bold small">Call Time (Crew Check-in)</label>
                            <input type="time" class="form-control form-control-sm" id="add_e_call_time" name="call_time" value="08:00">
                        </div>
                        <div class="col-md-4">
                            <label for="add_e_duration" class="form-label fw-bold small">Duration (Days)</label>
                            <input type="number" class="form-control form-control-sm" id="add_e_duration" name="duration" value="1" min="1" max="30">
                        </div>

                        <div class="col-md-4">
                            <label for="add_e_project_manager" class="form-label fw-bold small">Project Manager</label>
                            <input type="text" class="form-control form-control-sm" id="add_e_project_manager" name="project_manager" placeholder="e.g. Jean Pierre">
                        </div>
                        <div class="col-md-4">
                            <label for="add_e_tech_lead" class="form-label fw-bold small">Technical Lead</label>
                            <input type="text" class="form-control form-control-sm" id="add_e_tech_lead" name="tech_lead" placeholder="e.g. Lucas Ishimwe">
                        </div>
                        <div class="col-md-4">
                            <label for="add_e_assigned_technician" class="form-label fw-bold small">Assigned Technician</label>
                            <input type="text" class="form-control form-control-sm" id="add_e_assigned_technician" name="assigned_technician" placeholder="e.g. Valentin B.">
                        </div>

                        <div class="col-12">
                            <label for="add_e_description" class="form-label fw-bold small">Description / Technical Scope</label>
                            <textarea class="form-control form-control-sm" id="add_e_description" name="description" rows="2" placeholder="Stage specifications, LED video wall, audio setup requirements..."></textarea>
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-bold small">Event Poster / Setup Image</label>
                            <div class="image-preview-zone" onclick="document.getElementById('add_e_image_input').click()">
                                <input type="file" id="add_e_image_input" name="event_image" accept="image/*" class="d-none" onchange="previewSelectedImage(this, 'add_e_image_preview', 'add_e_image_container')">
                                <div id="add_e_image_container" class="text-center">
                                    <i class="fas fa-cloud-upload-alt fa-2x text-muted mb-2"></i>
                                    <p class="small text-muted mb-1">Click to upload poster or setup photo (PNG, JPG, WEBP - Max 5MB)</p>
                                </div>
                                <div id="add_e_image_preview" class="d-none text-center mt-2 position-relative">
                                    <img src="" alt="Preview" class="image-preview-thumbnail">
                                    <div class="mt-2">
                                        <button type="button" class="btn btn-xs btn-outline-danger" onclick="event.stopPropagation(); clearImageSelection('add_e_image_input', 'add_e_image_preview', 'add_e_image_container');">
                                            <i class="fas fa-times me-1"></i> Remove Selected Image
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer bg-light px-4 py-3">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-warning text-dark fw-bold">
                        <i class="fas fa-check me-1"></i> Save Event
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ==================== EDIT EVENT MODAL ==================== -->
<div class="modal fade" id="editEventModal" tabindex="-1" aria-labelledby="editEventModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <form id="editEventForm" enctype="multipart/form-data">
                <input type="hidden" name="action" value="edit_event">
                <input type="hidden" name="id" id="edit_e_id">
                <input type="hidden" name="remove_image" id="edit_e_remove_image" value="0">

                <div class="modal-header bg-warning text-dark">
                    <h5 class="modal-title fw-bold" id="editEventModalLabel">
                        <i class="fas fa-edit me-2"></i>Edit Event Details
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-md-7">
                            <label for="edit_e_title" class="form-label fw-bold small">Event Title <span class="text-danger">*</span></label>
                            <input type="text" class="form-control form-control-sm" id="edit_e_title" name="title" required>
                        </div>
                        <div class="col-md-5">
                            <label for="edit_e_location" class="form-label fw-bold small">Venue / Location <span class="text-danger">*</span></label>
                            <input type="text" class="form-control form-control-sm" id="edit_e_location" name="location" list="venueSuggestionsList" required>
                        </div>

                        <div class="col-md-4">
                            <label for="edit_e_date" class="form-label fw-bold small">Event Date</label>
                            <input type="date" class="form-control form-control-sm" id="edit_e_date" name="date">
                        </div>
                        <div class="col-md-4">
                            <label for="edit_e_call_time" class="form-label fw-bold small">Call Time</label>
                            <input type="time" class="form-control form-control-sm" id="edit_e_call_time" name="call_time">
                        </div>
                        <div class="col-md-4">
                            <label for="edit_e_duration" class="form-label fw-bold small">Duration (Days)</label>
                            <input type="number" class="form-control form-control-sm" id="edit_e_duration" name="duration" min="1" max="30">
                        </div>

                        <div class="col-md-4">
                            <label for="edit_e_project_manager" class="form-label fw-bold small">Project Manager</label>
                            <input type="text" class="form-control form-control-sm" id="edit_e_project_manager" name="project_manager">
                        </div>
                        <div class="col-md-4">
                            <label for="edit_e_tech_lead" class="form-label fw-bold small">Technical Lead</label>
                            <input type="text" class="form-control form-control-sm" id="edit_e_tech_lead" name="tech_lead">
                        </div>
                        <div class="col-md-4">
                            <label for="edit_e_assigned_technician" class="form-label fw-bold small">Assigned Technician</label>
                            <input type="text" class="form-control form-control-sm" id="edit_e_assigned_technician" name="assigned_technician">
                        </div>

                        <div class="col-12">
                            <label for="edit_e_description" class="form-label fw-bold small">Description / Notes</label>
                            <textarea class="form-control form-control-sm" id="edit_e_description" name="description" rows="2"></textarea>
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-bold small">Event Poster Photo</label>
                            <div id="edit_e_current_image_section" class="mb-2 p-2 border rounded bg-light d-flex align-items-center justify-content-between d-none">
                                <div class="d-flex align-items-center gap-2">
                                    <img id="edit_e_current_image_thumb" src="" alt="Current Image" style="width: 50px; height: 50px; object-fit: cover; border-radius: 6px;">
                                    <div>
                                        <div class="small fw-bold">Current Poster Photo</div>
                                        <div class="text-muted" style="font-size: 0.75rem;">Will be kept unless replaced or removed</div>
                                    </div>
                                </div>
                                <button type="button" class="btn btn-xs btn-outline-danger" onclick="markRemoveCurrentEventImage()">
                                    <i class="fas fa-trash-alt me-1"></i> Remove Photo
                                </button>
                            </div>

                            <div class="image-preview-zone" onclick="document.getElementById('edit_e_image_input').click()">
                                <input type="file" id="edit_e_image_input" name="event_image" accept="image/*" class="d-none" onchange="previewSelectedImage(this, 'edit_e_image_preview', 'edit_e_image_container')">
                                <div id="edit_e_image_container" class="text-center">
                                    <i class="fas fa-cloud-upload-alt fa-2x text-muted mb-2"></i>
                                    <p class="small text-muted mb-1" id="edit_e_upload_label">Click to upload new poster</p>
                                </div>
                                <div id="edit_e_image_preview" class="d-none text-center mt-2 position-relative">
                                    <img src="" alt="Preview" class="image-preview-thumbnail">
                                    <div class="mt-2">
                                        <button type="button" class="btn btn-xs btn-outline-danger" onclick="event.stopPropagation(); clearImageSelection('edit_e_image_input', 'edit_e_image_preview', 'edit_e_image_container');">
                                            <i class="fas fa-times me-1"></i> Cancel New Photo
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer bg-light px-4 py-3">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-warning text-dark fw-bold">
                        <i class="fas fa-save me-1"></i> Update Event
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ==================== DELETE EVENT MODAL ==================== -->
<div class="modal fade" id="deleteEventModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <form id="deleteEventForm">
                <input type="hidden" name="action" value="delete_event">
                <input type="hidden" name="id" id="delete_e_id">

                <div class="modal-header bg-danger text-white py-3">
                    <h5 class="modal-title fw-bold">
                        <i class="fas fa-exclamation-triangle me-2"></i>Delete Event
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body p-4 text-center">
                    <i class="fas fa-calendar-times fa-3x text-danger mb-3"></i>
                    <h5 class="fw-bold mb-2">Confirm Event Deletion</h5>
                    <p class="text-muted small mb-0">Are you sure you want to delete <strong id="delete_e_title" class="text-dark"></strong>?</p>
                </div>

                <div class="modal-footer bg-light px-4 py-2">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-danger fw-bold">
                        <i class="fas fa-trash-alt me-1"></i> Delete Event
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

    function markRemoveCurrentVenueImage() {
        $('#edit_v_remove_image').val('1');
        $('#edit_v_current_image_section').addClass('d-none');
        toastr.info('Current venue photo will be removed upon saving.');
    }

    function markRemoveCurrentEventImage() {
        $('#edit_e_remove_image').val('1');
        $('#edit_e_current_image_section').addClass('d-none');
        toastr.info('Current event photo will be removed upon saving.');
    }

    // Venues Handlers
    function openAddVenue() {
        document.getElementById('addVenueForm').reset();
        clearImageSelection('add_v_image_input', 'add_v_image_preview', 'add_v_image_container');
        $('#add_v_is_active').prop('checked', true);
        new bootstrap.Modal(document.getElementById('addVenueModal')).show();
    }

    function openEditVenue(id) {
        document.getElementById('editVenueForm').reset();
        clearImageSelection('edit_v_image_input', 'edit_v_image_preview', 'edit_v_image_container');
        $('#edit_v_remove_image').val('0');

        $.ajax({
            url: 'stock_locations.php?action=get_venue&id=' + id,
            type: 'GET',
            dataType: 'json',
            success: function(response) {
                if (response.success && response.venue) {
                    const v = response.venue;
                    $('#edit_v_id').val(v.id);
                    $('#edit_v_name').val(v.name);
                    $('#edit_v_code').val(v.code || '');
                    $('#edit_v_location').val(v.location);
                    $('#edit_v_address').val(v.address || '');
                    $('#edit_v_contact_person').val(v.contact_person || '');
                    $('#edit_v_contact_phone').val(v.contact_phone || '');
                    $('#edit_v_contact_email').val(v.contact_email || '');
                    $('#edit_v_open_time').val(v.open_time || '08:00');
                    $('#edit_v_close_time').val(v.close_time || '23:30');
                    $('#edit_v_description').val(v.description || '');
                    $('#edit_v_is_active').prop('checked', v.is_active == 1);

                    if (v.image && v.image.trim() !== '') {
                        $('#edit_v_current_image_thumb').attr('src', v.image);
                        $('#edit_v_current_image_section').removeClass('d-none');
                        $('#edit_v_upload_label').text('Click to replace current photo with a new one');
                    } else {
                        $('#edit_v_current_image_section').addClass('d-none');
                        $('#edit_v_upload_label').text('Click to upload photo for this venue');
                    }

                    new bootstrap.Modal(document.getElementById('editVenueModal')).show();
                } else {
                    Swal.fire('Error', response.message || 'Unable to load venue details', 'error');
                }
            },
            error: function() {
                Swal.fire('Error', 'Communication error while loading venue details', 'error');
            }
        });
    }

    function openDeleteVenue(id, name) {
        $('#delete_v_id').val(id);
        $('#delete_v_name').text(name);
        new bootstrap.Modal(document.getElementById('deleteVenueModal')).show();
    }

    // Events Handlers
    function openAddEvent() {
        document.getElementById('addEventForm').reset();
        clearImageSelection('add_e_image_input', 'add_e_image_preview', 'add_e_image_container');
        new bootstrap.Modal(document.getElementById('addEventModal')).show();
    }

    function openEditEvent(id) {
        document.getElementById('editEventForm').reset();
        clearImageSelection('edit_e_image_input', 'edit_e_image_preview', 'edit_e_image_container');
        $('#edit_e_remove_image').val('0');

        $.ajax({
            url: 'stock_locations.php?action=get_event&id=' + encodeURIComponent(id),
            type: 'GET',
            dataType: 'json',
            success: function(response) {
                if (response.success && response.event) {
                    const ev = response.event;
                    $('#edit_e_id').val(ev.id);
                    $('#edit_e_title').val(ev.title);
                    $('#edit_e_location').val(ev.location || '');
                    $('#edit_e_date').val(ev.date || '');
                    $('#edit_e_call_time').val(ev.call_time || '08:00');
                    $('#edit_e_duration').val(ev.duration || 1);
                    $('#edit_e_project_manager').val(ev.project_manager || '');
                    $('#edit_e_tech_lead').val(ev.tech_lead || '');
                    $('#edit_e_assigned_technician').val(ev.assigned_technician || '');
                    $('#edit_e_description').val(ev.description || '');

                    if (ev.event_image && ev.event_image.trim() !== '') {
                        $('#edit_e_current_image_thumb').attr('src', ev.event_image);
                        $('#edit_e_current_image_section').removeClass('d-none');
                        $('#edit_e_upload_label').text('Click to replace current poster with a new one');
                    } else {
                        $('#edit_e_current_image_section').addClass('d-none');
                        $('#edit_e_upload_label').text('Click to upload poster for this event');
                    }

                    new bootstrap.Modal(document.getElementById('editEventModal')).show();
                } else {
                    Swal.fire('Error', response.message || 'Unable to load event details', 'error');
                }
            },
            error: function() {
                Swal.fire('Error', 'Communication error while loading event details', 'error');
            }
        });
    }

    function openDeleteEvent(id, title) {
        $('#delete_e_id').val(id);
        $('#delete_e_title').text(title);
        new bootstrap.Modal(document.getElementById('deleteEventModal')).show();
    }

    // Client-side filtering
    function filterVenuesList() {
        const q = ($('#venueSearchBox').val() || '').toLowerCase().trim();
        let matchCount = 0;
        $('.venue-item-card').each(function() {
            const hay = $(this).attr('data-search') || '';
            const matches = (q === '' || hay.indexOf(q) !== -1);
            $(this).toggle(matches);
            if (matches) matchCount++;
        });
        $('#noVenuesFoundMsg').toggleClass('d-none', matchCount > 0);
        $('#venuesCountBadge').text(matchCount + ' Venues');
    }

    function filterEventsList() {
        const q = ($('#eventSearchBox').val() || '').toLowerCase().trim();
        let matchCount = 0;
        $('.event-item-card').each(function() {
            const hay = $(this).attr('data-search') || '';
            const matches = (q === '' || hay.indexOf(q) !== -1);
            $(this).toggle(matches);
            if (matches) matchCount++;
        });
        $('#noEventsFoundMsg').toggleClass('d-none', matchCount > 0);
        $('#eventsCountBadge').text(matchCount + ' Productions');
    }

    // View toggle handler
    function filterCheckpointView(type, btn) {
        $('#checkpointFilterGroup button').removeClass('active');
        $(btn).addClass('active');

        if (type === 'all') {
            $('#sectionStockWarehouses').removeClass('d-none');
            $('#sectionVenuesEvents').removeClass('d-none');
            $('#venuesColumn').removeClass('d-none').removeClass('col-lg-12').addClass('col-lg-6');
            $('#eventsColumn').removeClass('d-none').removeClass('col-lg-12').addClass('col-lg-6');
        } else if (type === 'stock') {
            $('#sectionStockWarehouses').removeClass('d-none');
            $('#sectionVenuesEvents').addClass('d-none');
        } else if (type === 'split') {
            $('#sectionStockWarehouses').addClass('d-none');
            $('#sectionVenuesEvents').removeClass('d-none');
            $('#venuesColumn').removeClass('d-none').removeClass('col-lg-12').addClass('col-lg-6');
            $('#eventsColumn').removeClass('d-none').removeClass('col-lg-12').addClass('col-lg-6');
        } else if (type === 'venues') {
            $('#sectionStockWarehouses').addClass('d-none');
            $('#sectionVenuesEvents').removeClass('d-none');
            $('#venuesColumn').removeClass('d-none').removeClass('col-lg-6').addClass('col-lg-12');
            $('#eventsColumn').addClass('d-none');
        } else if (type === 'events') {
            $('#sectionStockWarehouses').addClass('d-none');
            $('#sectionVenuesEvents').removeClass('d-none');
            $('#venuesColumn').addClass('d-none');
            $('#eventsColumn').removeClass('d-none').removeClass('col-lg-6').addClass('col-lg-12');
        }
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
                            Swal.fire('Error', response.message || 'Failed to save', 'error');
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

        // Bind all Add / Edit forms
        handleLocationForm('#addLocationForm', '#addLocationModal');
        handleLocationForm('#editLocationForm', '#editLocationModal');
        handleLocationForm('#addVenueForm', '#addVenueModal');
        handleLocationForm('#editVenueForm', '#editVenueModal');
        handleLocationForm('#addEventForm', '#addEventModal');
        handleLocationForm('#editEventForm', '#editEventModal');

        // Delete Stock Location Handler
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

        // Delete Venue Handler
        $('#deleteVenueForm').on('submit', function(e) {
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
                        $('#deleteVenueModal').modal('hide');
                        setTimeout(function() {
                            location.reload();
                        }, 1200);
                    } else {
                        Swal.fire('Cannot Delete', response.message || 'Failed to delete venue', 'warning');
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

        // Delete Event Handler
        $('#deleteEventForm').on('submit', function(e) {
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
                        $('#deleteEventModal').modal('hide');
                        setTimeout(function() {
                            location.reload();
                        }, 1200);
                    } else {
                        Swal.fire('Cannot Delete', response.message || 'Failed to delete event', 'warning');
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
