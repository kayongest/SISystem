<?php
// import_process.php - Background Import Processing
session_start();

// Set maximum execution time to 30 minutes
set_time_limit(1800);
ini_set('max_execution_time', 1800);
ini_set('memory_limit', '512M');

// Add error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include required files
require_once __DIR__ . '/includes/db_connect.php';
require_once __DIR__ . '/includes/functions.php';

// Check if PhpSpreadsheet is available
$phpspreadsheetLoaded = false;
try {
    $vendorPaths = [
        __DIR__ . '/vendor/autoload.php',
        dirname(__DIR__) . '/vendor/autoload.php',
        'vendor/autoload.php'
    ];
    
    foreach ($vendorPaths as $vendorPath) {
        if (file_exists($vendorPath)) {
            require_once $vendorPath;
            $phpspreadsheetLoaded = true;
            break;
        }
    }
} catch (Exception $e) {
    // Continue without PhpSpreadsheet
}

if (!$phpspreadsheetLoaded) {
    echo json_encode(['success' => false, 'message' => 'PhpSpreadsheet library not loaded']);
    exit();
}

// Function to generate QR codes (optimized version)
function generateQRCodeForItem($item_id, $item_name, $serial_number, $conn, $qrDir) {
    try {
        // Create qrcodes directory if it doesn't exist
        if (!is_dir($qrDir)) {
            if (!mkdir($qrDir, 0755, true)) {
                throw new Exception('Could not create qrcodes directory');
            }
        }
        
        // Check if QR already exists
        $qrFilename = 'qr_' . $item_id . '.png';
        $qrPath = $qrDir . $qrFilename;
        
        if (file_exists($qrPath)) {
            return 0; // QR already exists
        }
        
        // QR data - simplified for faster generation
        $qrData = "ID:$item_id|SN:$serial_number|N:" . substr($item_name, 0, 20);
        
        // Use external QR API (faster than local generation)
        $qrUrl = "https://api.qrserver.com/v1/create-qr-code/?" . http_build_query([
            'size' => '150x150',
            'data' => $qrData,
            'margin' => 5,
            'format' => 'png'
        ]);
        
        // Download QR with timeout
        $context = stream_context_create([
            'http' => ['timeout' => 2]
        ]);
        
        $qrImage = @file_get_contents($qrUrl, false, $context);
        
        if ($qrImage && strlen($qrImage) > 100) {
            if (file_put_contents($qrPath, $qrImage)) {
                $qrRelativePath = 'qrcodes/' . $qrFilename;
                $updateSql = "UPDATE items SET qr_code = ? WHERE id = ?";
                $updateStmt = $conn->prepare($updateSql);
                $updateStmt->bind_param('si', $qrRelativePath, $item_id);
                $updateStmt->execute();
                $updateStmt->close();
                return 1;
            }
        }
        
        // If QR generation fails, just return 0 (don't stop import)
        return 0;
    } catch (Exception $e) {
        // Don't throw error, just return 0
        error_log("QR generation error (non-fatal): " . $e->getMessage());
        return 0;
    }
}

// Process a batch of rows
function processImportBatch($sessionId, $batchSize) {
    $session = &$_SESSION['import_session'];
    
    // Load spreadsheet
    try {
        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($session['file']);
        $worksheet = $spreadsheet->getActiveSheet();
        
        $highestColumn = $worksheet->getHighestColumn();
        $highestColumnIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highestColumn);
        
        // Database connection
        $db = new Database();
        $conn = $db->getConnection();
        
        if (!$conn) {
            throw new Exception('Database connection failed');
        }
        
        // QR directory
        $qrDir = __DIR__ . '/qrcodes/';
        
        // Start row (2 for header, plus already processed rows)
        $startRow = 2 + $session['processed'];
        $endRow = min($startRow + $batchSize - 1, $session['total_rows'] + 1);
        
        for ($row = $startRow; $row <= $endRow; $row++) {
            try {
                $itemData = [];
                
                // Map each column
                foreach ($session['field_mapping'] as $dbField => $excelCol) {
                    if ($excelCol && $excelCol !== 'none') {
                        $colIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($excelCol);
                        $cellValue = $worksheet->getCellByColumnAndRow($colIndex, $row)->getValue();
                        $itemData[$dbField] = $cellValue ? trim($cellValue) : '';
                    }
                }
                
                // Validate required fields
                if (empty($itemData['item_name']) || empty($itemData['serial_number'])) {
                    $session['skipped']++;
                    $session['log'][] = ['type' => 'warning', 'message' => "Row $row: Skipped - missing required fields"];
                    continue;
                }
                
                // Set defaults
                $defaults = [
                    'quantity' => 1,
                    'condition' => 'good',
                    'status' => 'available'
                ];
                
                foreach ($defaults as $field => $defaultValue) {
                    if (empty($itemData[$field])) {
                        $itemData[$field] = $defaultValue;
                    }
                }
                
                // Process quantity
                if (isset($itemData['quantity'])) {
                    $itemData['quantity'] = intval($itemData['quantity']);
                    if ($itemData['quantity'] < 1) $itemData['quantity'] = 1;
                }
                
                // Check if exists
                $checkSql = "SELECT id, qr_code FROM items WHERE serial_number = ?";
                $checkStmt = $conn->prepare($checkSql);
                $checkStmt->bind_param('s', $itemData['serial_number']);
                $checkStmt->execute();
                $checkResult = $checkStmt->get_result();
                
                if ($checkResult->num_rows > 0) {
                    // Update existing
                    $existing = $checkResult->fetch_assoc();
                    $item_id = $existing['id'];
                    
                    // Build update query
                    $updateFields = [];
                    $updateValues = [];
                    $types = '';
                    
                    foreach ($itemData as $field => $value) {
                        if ($field === 'serial_number') continue;
                        
                        $updateFields[] = "`$field` = ?";
                        $updateValues[] = $value;
                        $types .= ($field === 'quantity') ? 'i' : 's';
                    }
                    
                    $updateValues[] = $itemData['serial_number'];
                    $types .= 's';
                    
                    $updateSql = "UPDATE items SET " . implode(', ', $updateFields) . " WHERE serial_number = ?";
                    $updateStmt = $conn->prepare($updateSql);
                    $updateStmt->bind_param($types, ...$updateValues);
                    
                    if ($updateStmt->execute()) {
                        $session['updated']++;
                        
                        // Generate QR if missing
                        if (empty($existing['qr_code'])) {
                            $session['qr_generated'] += generateQRCodeForItem($item_id, $itemData['item_name'], $itemData['serial_number'], $conn, $qrDir);
                        }
                        
                        $session['log'][] = ['type' => 'success', 'message' => "Row $row: Updated existing item"];
                    }
                    
                    $updateStmt->close();
                } else {
                    // Insert new
                    $fields = array_keys($itemData);
                    $placeholders = str_repeat('?,', count($fields) - 1) . '?';
                    $values = array_values($itemData);
                    
                    $types = '';
                    foreach ($itemData as $value) {
                        $types .= is_int($value) ? 'i' : 's';
                    }
                    
                    $insertSql = "INSERT INTO items (`" . implode('`,`', $fields) . "`, created_at) VALUES ($placeholders, NOW())";
                    $insertStmt = $conn->prepare($insertSql);
                    $insertStmt->bind_param($types, ...$values);
                    
                    if ($insertStmt->execute()) {
                        $session['success']++;
                        $item_id = $conn->insert_id;
                        
                        // Generate QR code (non-blocking)
                        $session['qr_generated'] += generateQRCodeForItem($item_id, $itemData['item_name'], $itemData['serial_number'], $conn, $qrDir);
                        
                        $session['log'][] = ['type' => 'success', 'message' => "Row $row: Added new item"];
                    }
                    
                    $insertStmt->close();
                }
                
                $checkStmt->close();
                
            } catch (Exception $e) {
                $session['errors']++;
                $session['log'][] = ['type' => 'error', 'message' => "Row $row: " . $e->getMessage()];
            }
            
            $session['processed']++;
            $session['current_status'] = "Processing row $row of " . $session['total_rows'];
        }
        
        $conn->close();
        
        // Check if completed
        if ($session['processed'] >= $session['total_rows']) {
            $session['completed'] = true;
            $session['current_status'] = 'Import completed!';
            
            // Clean up temp file
            if (file_exists($session['file'])) {
                @unlink($session['file']);
            }
            
            // Store final results
            $session['final_stats'] = [
                'success' => $session['success'],
                'updated' => $session['updated'],
                'qr_generated' => $session['qr_generated'],
                'errors' => $session['errors'],
                'skipped' => $session['skipped'],
                'total' => $session['success'] + $session['updated']
            ];
            
            // Store errors for display
            $errorLogs = array_filter($session['log'], function($log) {
                return $log['type'] === 'error';
            });
            $session['final_errors'] = array_slice(array_column($errorLogs, 'message'), 0, 20);
        }
        
    } catch (Exception $e) {
        $session['errors']++;
        $session['log'][] = ['type' => 'error', 'message' => "Batch processing error: " . $e->getMessage()];
        $session['current_status'] = 'Error in batch processing';
    }
}

// Initialize import session
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (isset($input['action']) && $input['action'] === 'start_import') {
        if (!isset($_SESSION['import_file']) || !file_exists($_SESSION['import_file'])) {
            echo json_encode(['success' => false, 'message' => 'Uploaded file not found. Please upload again.']);
            exit();
        }

        // Create unique session ID for this import
        $sessionId = 'import_' . time() . '_' . uniqid();
        
        // Initialize session data
        $_SESSION['import_session'] = [
            'id' => $sessionId,
            'file' => $_SESSION['import_file'],
            'headers' => $_SESSION['import_headers'],
            'total_rows' => $_SESSION['total_rows'],
            'field_mapping' => $input['field_mapping'],
            'processed' => 0,
            'success' => 0,
            'updated' => 0,
            'qr_generated' => 0,
            'errors' => 0,
            'skipped' => 0,
            'log' => [],
            'current_status' => 'Starting import...',
            'completed' => false,
            'start_time' => time()
        ];
        
        echo json_encode([
            'success' => true,
            'session_id' => $sessionId,
            'total_rows' => $_SESSION['total_rows']
        ]);
        exit();
    }
}

// Get import progress
if (isset($_GET['action']) && $_GET['action'] === 'get_progress') {
    header('Content-Type: application/json');
    
    if (!isset($_GET['session_id']) || !isset($_SESSION['import_session']) || $_SESSION['import_session']['id'] !== $_GET['session_id']) {
        echo json_encode(['completed' => true, 'message' => 'Session expired or not found']);
        exit();
    }
    
    $session = $_SESSION['import_session'];
    
    // If not completed and not started processing, start processing
    if (!$session['completed'] && $session['processed'] < $session['total_rows']) {
        // Process a batch of rows (10 rows at a time for smoother progress)
        $batchSize = min(10, $session['total_rows'] - $session['processed']);
        processImportBatch($session['id'], $batchSize);
    }
    
    // Return current progress
    echo json_encode([
        'completed' => $session['completed'] ?? false,
        'processed' => $session['processed'] ?? 0,
        'total_rows' => $session['total_rows'] ?? 0,
        'current_status' => $session['current_status'] ?? 'Processing...',
        'log' => array_slice($session['log'] ?? [], -10), // Last 10 log entries
        'stats' => [
            'success' => $session['success'] ?? 0,
            'updated' => $session['updated'] ?? 0,
            'qr_generated' => $session['qr_generated'] ?? 0,
            'errors' => $session['errors'] ?? 0,
            'skipped' => $session['skipped'] ?? 0,
            'total' => ($session['success'] ?? 0) + ($session['updated'] ?? 0)
        ]
    ]);
    
    // If completed, also send final results
    if ($session['completed'] ?? false) {
        // Clean up session data
        unset($_SESSION['import_file']);
        unset($_SESSION['import_headers']);
        unset($_SESSION['total_rows']);
        
        // Add final errors to response
        $_SESSION['import_session']['errors_list'] = $session['final_errors'] ?? [];
    }
    
    exit();
}

// Cancel import
if (isset($_GET['action']) && $_GET['action'] === 'cancel_import') {
    header('Content-Type: application/json');
    
    if (isset($_SESSION['import_session'])) {
        // Clean up temp file
        if (isset($_SESSION['import_file']) && file_exists($_SESSION['import_file'])) {
            @unlink($_SESSION['import_file']);
        }
        
        // Clear session data
        unset($_SESSION['import_file']);
        unset($_SESSION['import_headers']);
        unset($_SESSION['total_rows']);
        unset($_SESSION['import_session']);
    }
    
    echo json_encode(['success' => true]);
    exit();
}

// Default response
header('Content-Type: application/json');
echo json_encode(['success' => false, 'message' => 'Invalid request']);