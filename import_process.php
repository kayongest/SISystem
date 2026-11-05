<?php
// import_process.php - Background Import Processing
ob_start();
session_start();

// Set maximum execution time to 30 minutes
set_time_limit(1800);
ini_set('max_execution_time', 1800);
ini_set('memory_limit', '512M');

// Suppress HTML error output so JSON responses are never corrupted
error_reporting(E_ALL);
ini_set('display_errors', 0);

function sendImportJson($payload) {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload);
    exit();
}

// Include required files
require_once __DIR__ . '/includes/db_connect.php';
require_once __DIR__ . '/includes/functions.php';
if (file_exists(__DIR__ . '/includes/zip_polyfill.php')) {
    require_once __DIR__ . '/includes/zip_polyfill.php';
}

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
} catch (\Throwable $e) {
    // Continue without PhpSpreadsheet
}

if (!$phpspreadsheetLoaded) {
    sendImportJson(['success' => false, 'message' => 'PhpSpreadsheet library not loaded']);
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
        
        if (file_exists($qrPath) && filesize($qrPath) > 100) {
            return 0; // QR already exists
        }
        
        // QR data - simplified for faster generation
        $qrData = "ID:$item_id|SN:$serial_number|N:" . substr((string)$item_name, 0, 20);
        $qrImage = null;

        // Try local generation first if available
        if (class_exists('chillerlan\QRCode\QRCode')) {
            try {
                $options = new \chillerlan\QRCode\QROptions([
                    'version'      => \chillerlan\QRCode\QRCode::VERSION_AUTO,
                    'outputType'   => \chillerlan\QRCode\QRCode::OUTPUT_IMAGE_PNG,
                    'eccLevel'     => \chillerlan\QRCode\QRCode::ECC_M,
                    'scale'        => 6,
                    'imageBase64'  => false,
                ]);
                $qrcode = new \chillerlan\QRCode\QRCode($options);
                $qrImage = $qrcode->render($qrData);
            } catch (\Throwable $e) {
                $qrImage = null;
            }
        }

        // Fallback to external QR API if local generation didn't produce an image
        if (!$qrImage || strlen($qrImage) <= 100) {
            $qrUrl = "https://api.qrserver.com/v1/create-qr-code/?" . http_build_query([
                'size' => '150x150',
                'data' => $qrData,
                'margin' => 5,
                'format' => 'png'
            ]);
            
            $context = stream_context_create([
                'http' => ['timeout' => 1.5]
            ]);
            
            $qrImage = @file_get_contents($qrUrl, false, $context);
        }
        
        if ($qrImage && strlen($qrImage) > 100) {
            if (@file_put_contents($qrPath, $qrImage)) {
                $qrRelativePath = 'qrcodes/' . $qrFilename;
                $updateSql = "UPDATE items SET qr_code = ? WHERE id = ?";
                $updateStmt = $conn->prepare($updateSql);
                if ($updateStmt) {
                    $updateStmt->bind_param('si', $qrRelativePath, $item_id);
                    $updateStmt->execute();
                    $updateStmt->close();
                }
                return 1;
            }
        }
        
        // If QR generation fails, just return 0 (don't stop import)
        return 0;
    } catch (\Throwable $e) {
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
        
        $allowedFields = [
            'item_name',
            'serial_number',
            'category',
            'brand',
            'model',
            'department',
            'description',
            'specifications',
            'brand_model',
            'condition',
            'stock_location',
            'storage_location',
            'notes',
            'quantity',
            'status',
            'tags',
            'last_scanned',
            'current_location'
        ];

        for ($row = $startRow; $row <= $endRow; $row++) {
            try {
                $itemData = [];
                
                // Map each column
                foreach ($session['field_mapping'] as $dbField => $excelCol) {
                    if (!in_array($dbField, $allowedFields, true)) {
                        continue;
                    }
                    if ($excelCol && $excelCol !== 'none') {
                        $cell = $worksheet->getCell($excelCol . $row);
                        $cellValue = $cell->getValue();
                        if ($cellValue instanceof \PhpOffice\PhpSpreadsheet\RichText\RichText) {
                            $cellValue = $cellValue->getPlainText();
                        }
                        if ($dbField === 'last_scanned') {
                            $rawScan = $cellValue !== null ? trim((string)$cellValue) : '';
                            if ($rawScan === '') {
                                $itemData['last_scanned'] = null;
                            } elseif (is_numeric($rawScan) && \PhpOffice\PhpSpreadsheet\Shared\Date::isDateTime($cell)) {
                                $itemData['last_scanned'] = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((float)$rawScan)->format('Y-m-d H:i:s');
                            } else {
                                $ts = strtotime($rawScan);
                                $itemData['last_scanned'] = $ts !== false ? date('Y-m-d H:i:s', $ts) : null;
                            }
                        } else {
                            $itemData[$dbField] = $cellValue !== null ? trim((string)$cellValue) : '';
                        }
                    }
                }
                
                // Validate required fields
                if (empty($itemData['item_name']) || empty($itemData['serial_number'])) {
                    $session['skipped']++;
                    $session['log'][] = ['type' => 'warning', 'message' => "Row $row: Skipped - missing required fields"];
                    continue;
                }

                // Auto-fill brand_model if blank and brand/model are provided
                if (empty($itemData['brand_model']) && (!empty($itemData['brand']) || !empty($itemData['model']))) {
                    $itemData['brand_model'] = trim(($itemData['brand'] ?? '') . ' ' . ($itemData['model'] ?? ''));
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
                    
                    if (!$updateStmt) {
                        throw new Exception("Update prepare failed: " . $conn->error);
                    }

                    $updateStmt->bind_param($types, ...$updateValues);
                    
                    if ($updateStmt->execute()) {
                        $session['updated']++;
                        
                        // Generate QR if missing
                        if (empty($existing['qr_code'])) {
                            $session['qr_generated'] += generateQRCodeForItem($item_id, $itemData['item_name'], $itemData['serial_number'], $conn, $qrDir);
                        }
                        
                        $session['log'][] = ['type' => 'success', 'message' => "Row $row: Updated existing item"];
                    } else {
                        throw new Exception("Update failed: " . $updateStmt->error);
                    }
                    
                    $updateStmt->close();
                } else {
                    // Insert new
                    $fields = array_keys($itemData);
                    $placeholders = str_repeat('?,', count($fields) - 1) . '?';
                    $values = array_values($itemData);
                    
                    $types = '';
                    foreach ($itemData as $field => $value) {
                        $types .= ($field === 'quantity') ? 'i' : 's';
                    }
                    
                    $insertSql = "INSERT INTO items (`" . implode('`,`', $fields) . "`, created_at) VALUES ($placeholders, NOW())";
                    $insertStmt = $conn->prepare($insertSql);
                    
                    if (!$insertStmt) {
                        throw new Exception("Insert prepare failed: " . $conn->error . " SQL: " . $insertSql);
                    }

                    $insertStmt->bind_param($types, ...$values);
                    
                    if ($insertStmt->execute()) {
                        $session['success']++;
                        $item_id = $conn->insert_id;
                        
                        // Generate QR code (non-blocking)
                        $session['qr_generated'] += generateQRCodeForItem($item_id, $itemData['item_name'], $itemData['serial_number'], $conn, $qrDir);
                        
                        $session['log'][] = ['type' => 'success', 'message' => "Row $row: Added new item"];
                    } else {
                        throw new Exception("Insert failed: " . $insertStmt->error);
                    }
                    
                    $insertStmt->close();
                }
                
                $checkStmt->close();
                
            } catch (\Throwable $e) {
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
        
    } catch (\Throwable $e) {
        $session['errors']++;
        $session['log'][] = ['type' => 'error', 'message' => "Batch processing error: " . $e->getMessage()];
        $session['current_status'] = 'Error in batch processing';
        $session['completed'] = true;
        $session['final_errors'] = ["Batch processing error: " . $e->getMessage()];
    }
}

// Initialize import session
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (isset($input['action']) && $input['action'] === 'start_import') {
        if (!isset($_SESSION['import_file']) || !file_exists($_SESSION['import_file'])) {
            sendImportJson(['success' => false, 'message' => 'Uploaded file not found. Please upload again.']);
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
        
        sendImportJson([
            'success' => true,
            'session_id' => $sessionId,
            'total_rows' => $_SESSION['total_rows']
        ]);
    }
}

// Get import progress
if (isset($_GET['action']) && $_GET['action'] === 'get_progress') {
    if (!isset($_GET['session_id']) || !isset($_SESSION['import_session']) || $_SESSION['import_session']['id'] !== $_GET['session_id']) {
        sendImportJson([
            'completed' => true,
            'message' => 'Session expired or not found',
            'processed' => 0,
            'total_rows' => 0,
            'stats' => [
                'success' => 0,
                'updated' => 0,
                'qr_generated' => 0,
                'errors' => 0,
                'skipped' => 0,
                'total' => 0
            ],
            'errors' => []
        ]);
    }
    
    $session = &$_SESSION['import_session'];
    
    // If not completed and rows remain, process a batch
    if (!$session['completed'] && $session['processed'] < $session['total_rows']) {
        // Process a batch of rows (20 rows at a time for fast progress)
        $batchSize = min(20, $session['total_rows'] - $session['processed']);
        processImportBatch($session['id'], $batchSize);
    }
    
    $responsePayload = [
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
        ],
        'errors' => $session['final_errors'] ?? []
    ];
    
    // If completed, clean up session data before sending response
    if ($session['completed'] ?? false) {
        unset($_SESSION['import_file']);
        unset($_SESSION['import_headers']);
        unset($_SESSION['total_rows']);
    }
    
    sendImportJson($responsePayload);
}

// Cancel import
if (isset($_GET['action']) && $_GET['action'] === 'cancel_import') {
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
    
    sendImportJson(['success' => true]);
}

// Default response
sendImportJson(['success' => false, 'message' => 'Invalid request']);