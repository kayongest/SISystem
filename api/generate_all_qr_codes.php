<?php
// api/generate_all_qr_codes.php - COMPLETE VERSION
session_start();
require_once '../includes/database_fix.php';
require_once '../includes/functions.php';

// Check authentication
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// Set time limit for long process
set_time_limit(300); // 5 minutes
ini_set('memory_limit', '256M');

// Function to generate QR code with multiple fallbacks
function generateQRCodeForItem($item_id, $item_name, $serial_number)
{
    try {
        // Create data for QR code
        $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http");
        $host = $_SERVER['HTTP_HOST'];

        $data = json_encode([
            'id' => $item_id,
            'name' => $item_name,
            'serial' => $serial_number,
            'system' => 'aBility Inventory',
            'url' => $protocol . "://" . $host . '/items/view.php?id=' . $item_id,
            'timestamp' => date('Y-m-d H:i:s')
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        // Create QR directory if it doesn't exist
        $qrDir = '../uploads/qr_codes/';
        if (!file_exists($qrDir)) {
            if (!mkdir($qrDir, 0777, true)) {
                error_log("Failed to create QR directory: $qrDir");
                return null;
            }
        }

        // Generate filename
        $cleanName = preg_replace('/[^a-z0-9]/i', '_', $item_name);
        $cleanSerial = preg_replace('/[^a-z0-9]/i', '_', $serial_number);
        $filename = 'qr_' . $item_id . '_' . $cleanName . '_' . $cleanSerial . '.png';
        $filepath = $qrDir . $filename;
        $qrDbPath = 'uploads/qr_codes/' . $filename;

        // Check if QR code already exists
        if (file_exists($filepath)) {
            return $qrDbPath;
        }

        // Try multiple QR generation APIs
        $qrAPIs = [
            [
                'url' => 'https://api.qrserver.com/v1/create-qr-code/',
                'params' => [
                    'size' => '300x300',
                    'data' => urlencode($data),
                    'format' => 'png',
                    'margin' => 2,
                    'ecc' => 'L'
                ]
            ],
            [
                'url' => 'https://chart.googleapis.com/chart',
                'params' => [
                    'chs' => '300x300',
                    'cht' => 'qr',
                    'chl' => urlencode($data),
                    'choe' => 'UTF-8'
                ]
            ],
            [
                'url' => 'https://quickchart.io/qr',
                'params' => [
                    'size' => 300,
                    'text' => $data,
                    'margin' => 2
                ]
            ]
        ];

        $context = stream_context_create([
            'http' => [
                'timeout' => 10,
                'header' => "User-Agent: aBility-Inventory/1.0\r\n",
                'ignore_errors' => true
            ],
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
            ]
        ]);

        foreach ($qrAPIs as $api) {
            try {
                $url = $api['url'] . '?' . http_build_query($api['params']);

                $qrImage = @file_get_contents($url, false, $context);

                if ($qrImage !== false && strlen($qrImage) > 100) { // Minimum size check
                    // Verify it's a valid PNG
                    if (strpos($qrImage, "\x89PNG\x0d\x0a\x1a\x0a") === 0) {
                        if (file_put_contents($filepath, $qrImage) !== false) {
                            return $qrDbPath;
                        }
                    }
                }

                // Small delay between API calls
                usleep(500000); // 0.5 second

            } catch (Exception $apiError) {
                error_log("QR API error for item $item_id: " . $apiError->getMessage());
                continue;
            }
        }

        // If all APIs fail, create a placeholder
        return createQRPlaceholder($item_id, $item_name, $serial_number, $filepath, $qrDbPath);
    } catch (Exception $e) {
        error_log("QR Code generation error for item $item_id: " . $e->getMessage());
        return null;
    }
}

// Create a placeholder if QR generation fails
function createQRPlaceholder($item_id, $item_name, $serial_number, $filepath, $qrDbPath)
{
    try {
        if (!extension_loaded('gd')) {
            error_log("GD library not available for QR placeholder");
            return null;
        }

        $width = 300;
        $height = 300;

        // Create image
        $image = imagecreatetruecolor($width, $height);

        // Colors
        $white = imagecolorallocate($image, 255, 255, 255);
        $black = imagecolorallocate($image, 0, 0, 0);
        $gray = imagecolorallocate($image, 200, 200, 200);
        $blue = imagecolorallocate($image, 78, 115, 223);

        // Fill background
        imagefilledrectangle($image, 0, 0, $width, $height, $white);

        // Add border
        imagerectangle($image, 5, 5, $width - 5, $height - 5, $blue);

        // Add title
        $title = "aBility Inventory";
        imagestring($image, 5, 70, 30, $title, $blue);

        // Add item info
        $itemText = "Item: " . substr($item_name, 0, 25);
        imagestring($image, 3, 20, 100, $itemText, $black);

        $serialText = "Serial: " . substr($serial_number, 0, 20);
        imagestring($image, 3, 20, 120, $serialText, $black);

        $idText = "ID: " . $item_id;
        imagestring($image, 3, 20, 140, $idText, $black);

        $message = "QR Code Pending";
        imagestring($image, 4, 80, 200, $message, $black);

        $dateText = date('Y-m-d');
        imagestring($image, 2, 100, 220, $dateText, $gray);

        // Save image
        imagepng($image, $filepath);
        imagedestroy($image);

        return $qrDbPath;
    } catch (Exception $e) {
        error_log("QR placeholder error: " . $e->getMessage());
        return null;
    }
}

// Main processing
try {
    // Start output buffering
    ob_start();

    $db = new DatabaseFix();
    $conn = $db->getConnection();

    if (!$conn) {
        throw new Exception("Database connection failed");
    }

    // Get all items that need QR codes
    $query = "
        SELECT id, item_name, serial_number, qr_code 
        FROM items 
        WHERE status NOT IN ('disposed', 'lost')
        ORDER BY id
    ";

    $result = $conn->query($query);

    if (!$result) {
        throw new Exception("Database query failed: " . $conn->error);
    }

    $items = $result->fetch_all(MYSQLI_ASSOC);
    $totalItems = count($items);

    if ($totalItems === 0) {
        echo json_encode([
            'success' => false,
            'message' => 'No items found to process'
        ]);
        exit();
    }

    // Initialize counters
    $generated = 0;
    $existing = 0;
    $failed = 0;
    $qrFiles = [];

    // Process items
    foreach ($items as $index => $item) {
        $item_id = $item['id'];
        $item_name = $item['item_name'];
        $serial = $item['serial_number'];
        $current_qr = $item['qr_code'];

        // Check if QR already exists and is valid
        if (!empty($current_qr) && $current_qr !== 'pending') {
            $qrPath = '../' . $current_qr;
            if (file_exists($qrPath)) {
                // Add existing QR to files list
                $safeName = preg_replace('/[^a-z0-9]/i', '_', $item_name);
                $safeSerial = preg_replace('/[^a-z0-9]/i', '_', $serial);
                $filename = 'QR_' . $safeName . '_' . $safeSerial . '.png';

                $qrFiles[] = [
                    'path' => $qrPath,
                    'filename' => $filename,
                    'status' => 'existing'
                ];
                $existing++;
                continue;
            }
        }

        // Generate new QR code
        $qrPath = generateQRCodeForItem($item_id, $item_name, $serial);

        if ($qrPath) {
            // Update database
            $updateStmt = $conn->prepare("UPDATE items SET qr_code = ? WHERE id = ?");
            if ($updateStmt) {
                $updateStmt->bind_param("si", $qrPath, $item_id);
                $updateStmt->execute();
                $updateStmt->close();
            }

            // Add to files list
            $safeName = preg_replace('/[^a-z0-9]/i', '_', $item_name);
            $safeSerial = preg_replace('/[^a-z0-9]/i', '_', $serial);
            $filename = 'QR_' . $safeName . '_' . $safeSerial . '.png';

            $qrFiles[] = [
                'path' => '../' . $qrPath,
                'filename' => $filename,
                'status' => 'generated'
            ];
            $generated++;
        } else {
            $failed++;
        }

        // Small delay to prevent overloading server/API
        usleep(100000); // 0.1 second
    }

    // Create ZIP file if we have QR codes
    $zipCreated = false;
    $zipFileName = '';
    $downloadUrl = '';

    if (count($qrFiles) > 0) {
        // Create temporary directory for ZIP
        $tempDir = '../uploads/temp_qr_zip/';
        if (!file_exists($tempDir)) {
            mkdir($tempDir, 0777, true);
        }

        $tempSubDir = $tempDir . uniqid() . '/';
        mkdir($tempSubDir, 0777, true);

        // Copy QR codes to temp directory
        $copied = 0;
        foreach ($qrFiles as $qrFile) {
            if (file_exists($qrFile['path'])) {
                $destPath = $tempSubDir . $qrFile['filename'];
                if (copy($qrFile['path'], $destPath)) {
                    $copied++;
                }
            }
        }

        if ($copied > 0) {
            // Create ZIP file
            $zipFileName = '../uploads/qr_codes_all_' . date('Y-m-d_H-i-s') . '.zip';

            if (class_exists('ZipArchive')) {
                $zip = new ZipArchive();

                if ($zip->open($zipFileName, ZipArchive::CREATE) === TRUE) {
                    // Add files to zip
                    $files = glob($tempSubDir . '*');
                    foreach ($files as $file) {
                        if (is_file($file)) {
                            $zip->addFile($file, basename($file));
                        }
                    }

                    $zip->close();
                    $zipCreated = true;
                    $downloadUrl = str_replace('../', '', $zipFileName);

                    // Clean up temp directory
                    array_map('unlink', glob($tempSubDir . '*'));
                    rmdir($tempSubDir);
                } else {
                    throw new Exception("Failed to create ZIP file");
                }
            } else {
                // Fallback: Create tar.gz if ZipArchive not available
                $tarFileName = '../uploads/qr_codes_all_' . date('Y-m-d_H-i-s') . '.tar.gz';
                exec("tar -czf " . escapeshellarg($tarFileName) . " -C " . escapeshellarg($tempSubDir) . " . 2>&1", $output, $returnCode);

                if ($returnCode === 0 && file_exists($tarFileName)) {
                    $zipCreated = true;
                    $zipFileName = $tarFileName;
                    $downloadUrl = str_replace('../', '', $tarFileName);
                }

                // Clean up temp directory
                array_map('unlink', glob($tempSubDir . '*'));
                rmdir($tempSubDir);
            }
        } else {
            throw new Exception("No QR files could be copied for ZIP creation");
        }
    }

    // Close database connection
    $db->close();

    // Clear output buffer
    ob_end_clean();

    // Return final result
    header('Content-Type: application/json');

    if ($zipCreated) {
        echo json_encode([
            'success' => true,
            'message' => "QR codes processed successfully! Generated: $generated, Existing: $existing, Failed: $failed",
            'stats' => [
                'total_items' => $totalItems,
                'generated' => $generated,
                'existing' => $existing,
                'failed' => $failed,
                'in_zip' => count($qrFiles)
            ],
            'download_url' => $downloadUrl,
            'filename' => basename($zipFileName),
            'file_size' => file_exists($zipFileName) ? filesize($zipFileName) : 0
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Failed to create ZIP file',
            'stats' => [
                'total_items' => $totalItems,
                'generated' => $generated,
                'existing' => $existing,
                'failed' => $failed
            ]
        ]);
    }
} catch (Exception $e) {
    // Clean up any open buffers
    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
}
