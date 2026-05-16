<?php
// api/quick_qr_zip.php - Fixed version

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start output buffering
ob_start();

// Set JSON header
header('Content-Type: application/json');

// Check session
session_start();
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

try {
    // Include required files
    $rootDir = dirname(__DIR__);
    $configFile = $rootDir . '/config/database.php';
    $functionsFile = $rootDir . '/includes/functions.php';
    $qrGeneratorFile = $rootDir . '/includes/qr_generator.php';
    
    if (!file_exists($configFile)) {
        throw new Exception("Database config file not found: " . $configFile);
    }
    
    if (!file_exists($functionsFile)) {
        throw new Exception("Functions file not found: " . $functionsFile);
    }
    
    if (!file_exists($qrGeneratorFile)) {
        throw new Exception("QR generator file not found: " . $qrGeneratorFile);
    }
    
    require_once $configFile;
    require_once $functionsFile;
    require_once $qrGeneratorFile;
    
    // Try different database connection methods
    $conn = null;
    
    // Method 1: Check if DatabaseFix exists
    if (class_exists('DatabaseFix')) {
        $db = new DatabaseFix();
        $conn = $db->getConnection();
    }
    // Method 2: Check if there's a global $conn or $db
    elseif (isset($GLOBALS['conn']) && $GLOBALS['conn']) {
        $conn = $GLOBALS['conn'];
    }
    // Method 3: Try to create connection directly
    else {
        // Check if database credentials are defined
        if (defined('DB_HOST') && defined('DB_USER') && defined('DB_PASS') && defined('DB_NAME')) {
            $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
            if ($conn->connect_error) {
                throw new Exception("Connection failed: " . $conn->connect_error);
            }
        } else {
            throw new Exception("Database credentials not defined");
        }
    }
    
    if (!$conn) {
        throw new Exception("Could not establish database connection");
    }
    
    // Count total items
    $query = "SELECT COUNT(*) as total FROM items WHERE status NOT IN ('disposed', 'lost')";
    $result = $conn->query($query);
    
    if (!$result) {
        throw new Exception("Database query failed: " . $conn->error);
    }
    
    $row = $result->fetch_assoc();
    $totalItems = (int)$row['total'];
    
    if ($totalItems === 0) {
        echo json_encode(['success' => false, 'message' => 'No items found to generate QR codes for']);
        exit;
    }
    
    // Create ZIP file
    $zipFilename = 'qr_codes_all_' . date('Y-m-d_H-i') . '.zip';
    $zipPath = dirname(__DIR__) . '/uploads/' . $zipFilename;
    
    // Check if ZipArchive is available
    if (!class_exists('ZipArchive')) {
        throw new Exception("ZipArchive extension not available. Please enable php_zip.");
    }
    
    $zip = new ZipArchive();
    if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== TRUE) {
        throw new Exception("Cannot create ZIP file: " . $zipPath);
    }
    
    // Get all items
    $query = "SELECT id, item_name, serial_number, qr_code 
              FROM items 
              WHERE status NOT IN ('disposed', 'lost')
              ORDER BY id";
    
    $result = $conn->query($query);
    
    if (!$result) {
        throw new Exception("Database error: " . $conn->error);
    }
    
    $counter = 0;
    $successCount = 0;
    $failedCount = 0;
    
    // Create a progress file to track progress
    $progressFile = dirname(__DIR__) . '/uploads/temp/qr_progress.json';
    $tempDir = dirname($progressFile);
    if (!is_dir($tempDir)) {
        @mkdir($tempDir, 0755, true);
    }
    
    while ($row = $result->fetch_assoc()) {
        $counter++;
        
        // Update progress
        $progress = round(($counter / $totalItems) * 100);
        $progressData = [
            'current' => $counter,
            'total' => $totalItems,
            'percentage' => $progress,
            'status' => "Processing item $counter of $totalItems",
            'current_item' => $row['item_name']
        ];
        @file_put_contents($progressFile, json_encode($progressData));
        
        // Determine QR file path
        $qrPath = null;
        
        if (!empty($row['qr_code']) && $row['qr_code'] !== 'pending') {
            // Use existing QR
            $qrPath = dirname(__DIR__) . '/' . $row['qr_code'];
        }
        
        // If QR doesn't exist or file is missing, generate new one
        if (!$qrPath || !file_exists($qrPath)) {
            // Use the generateQRCodeForItem function from qr_generator.php
            $newQrPath = generateQRCodeForItem(
                $row['id'], 
                $row['item_name'], 
                $row['serial_number'], 
                '' // stock_location - not critical for QR generation
            );
            
            if ($newQrPath) {
                $qrPath = dirname(__DIR__) . '/' . $newQrPath;
                
                // Update database
                $updateStmt = $conn->prepare("UPDATE items SET qr_code = ? WHERE id = ?");
                if ($updateStmt) {
                    $updateStmt->bind_param("si", $newQrPath, $row['id']);
                    $updateStmt->execute();
                    $updateStmt->close();
                }
            }
        }
        
        // Add to ZIP if QR exists
        if ($qrPath && file_exists($qrPath)) {
            $cleanName = preg_replace('/[^a-z0-9]/i', '_', $row['item_name']);
            $cleanSerial = preg_replace('/[^a-z0-9]/i', '_', $row['serial_number']);
            $filenameInZip = 'QR_' . $row['id'] . '_' . $cleanName . '_' . $cleanSerial . '.png';
            
            if ($zip->addFile($qrPath, $filenameInZip)) {
                $successCount++;
            } else {
                $failedCount++;
            }
        } else {
            $failedCount++;
        }
    }
    
    // Clean up progress file
    @unlink($progressFile);
    
    $zip->close();
    
    // Clear any output
    if (ob_get_level() > 0) {
        ob_clean();
    }
    
    // Return JSON response
    echo json_encode([
        'success' => true,
        'message' => "Generated $successCount QR codes ($failedCount failed)",
        'total_items' => $totalItems,
        'success_count' => $successCount,
        'failed_count' => $failedCount,
        'download_url' => 'uploads/' . $zipFilename,
        'filename' => $zipFilename
    ]);
    
} catch (Exception $e) {
    // Clean output
    if (ob_get_level() > 0) {
        ob_clean();
    }
    
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage(),
        'error' => $e->getFile() . ':' . $e->getLine()
    ]);
}

// End output buffering
if (ob_get_level() > 0) {
    ob_end_flush();
}