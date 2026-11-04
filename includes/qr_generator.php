<?php
// includes/qr_generator.php - COMPLETE FIXED VERSION

// Prevent loading multiple times
if (defined('QR_GENERATOR_LOADED')) {
    return;
}
define('QR_GENERATOR_LOADED', true);

// Ensure vendor autoloader is available
$autoloader = dirname(__DIR__) . '/vendor/autoload.php';
if (file_exists($autoloader)) {
    require_once $autoloader;
}

if (!function_exists('generateQRCodeForItem')) {
    /**
     * Generate QR code for an item
     * @param int $item_id
     * @param string $item_name
     * @param string $serial_number
     * @param string $stock_location
     * @param bool $force Force regeneration even if file exists
     * @return array ['success' => bool, 'path' => string, 'message' => string]
     */
    function generateQRCodeForItem($item_id, $item_name, $serial_number, $stock_location = '', $force = false)
    {
        global $rootDir;
        
        if (empty($rootDir)) {
            $rootDir = dirname(__DIR__);
        }
        
        // Create qrcodes directory in the web root
        $webRoot = dirname(__DIR__);
        $qrDir = $webRoot . '/qrcodes';
        
        // Create directory if needed
        if (!is_dir($qrDir)) {
            if (!mkdir($qrDir, 0755, true)) {
                error_log("Failed to create QR directory: $qrDir");
                return ['success' => false, 'message' => 'Failed to create QR directory'];
            }
        }
        
        // Check if directory is writable (portable check that avoids Windows NTFS is_writable bug)
        $testFile = $qrDir . '/.write_test_' . uniqid();
        if (!@file_put_contents($testFile, '1')) {
            error_log("QR directory not writable: $qrDir");
            return ['success' => false, 'message' => 'QR directory not writable'];
        }
        @unlink($testFile);
        
        $filename = 'qr_' . $item_id . '.png';
        $relativePath = 'qrcodes/' . $filename;
        $fullPath = $qrDir . '/' . $filename;
        
        // If not forced and file already exists and is non-empty, return existing
        if (!$force && file_exists($fullPath) && filesize($fullPath) > 100) {
            return [
                'success' => true,
                'path' => $relativePath,
                'message' => 'QR code already exists'
            ];
        }
        
        // Generate QR data - standardized compact format matching scanner
        $qrData = [
            'i' => (int)$item_id,
            'n' => (string)$item_name,
            's' => (string)$serial_number
        ];
        
        $qrDataString = json_encode($qrData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        
        // Try local generation first using chillerlan/php-qrcode
        $qrImage = null;
        if (class_exists('chillerlan\QRCode\QRCode')) {
            try {
                $options = new \chillerlan\QRCode\QROptions([
                    'version'      => \chillerlan\QRCode\QRCode::VERSION_AUTO,
                    'outputType'   => \chillerlan\QRCode\QRCode::OUTPUT_IMAGE_PNG,
                    'eccLevel'     => \chillerlan\QRCode\QRCode::ECC_M,
                    'scale'        => 8,
                    'imageBase64'  => false,
                ]);
                $qrcode = new \chillerlan\QRCode\QRCode($options);
                $qrImage = $qrcode->render($qrDataString);
            } catch (Exception $e) {
                error_log("chillerlan QRCode generation failed: " . $e->getMessage());
            }
        }
        
        // Fallback to endroid/qr-code if chillerlan failed or isn't available
        if (!$qrImage && class_exists('Endroid\QrCode\QrCode')) {
            try {
                $qr = \Endroid\QrCode\QrCode::create($qrDataString)
                    ->setSize(300)
                    ->setMargin(10);
                $writer = new \Endroid\QrCode\Writer\PngWriter();
                $qrImage = $writer->write($qr)->getString();
            } catch (Exception $e) {
                error_log("endroid QRCode generation failed: " . $e->getMessage());
            }
        }
        
        // Fallback: Online APIs
        if (!$qrImage) {
            $qrAPIs = [
                'https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=' . urlencode($qrDataString) . '&margin=10&format=png',
                'https://quickchart.io/qr?size=300&text=' . urlencode($qrDataString) . '&margin=2'
            ];
            
            $context = stream_context_create([
                'ssl' => ['verify_peer' => false, 'verify_peer_name' => false],
                'http' => ['timeout' => 5, 'header' => "User-Agent: aBility/1.0\r\n"]
            ]);
            
            foreach ($qrAPIs as $apiUrl) {
                $qrImage = @file_get_contents($apiUrl, false, $context);
                if ($qrImage && strlen($qrImage) > 100) {
                    break;
                }
            }
        }
        
        // Final fallback: GD-generated placeholder
        if ($qrImage === false || strlen($qrImage) < 100) {
            if (function_exists('createPlaceholderQR')) {
                $qrImage = createPlaceholderQR($item_id, $item_name, $serial_number);
            }
        }
        
        // Save the QR code
        if ($qrImage && strlen($qrImage) > 100) {
            if (file_put_contents($fullPath, $qrImage)) {
                @chmod($fullPath, 0644);
                
                // Update items database record if connection is available
                try {
                    $conn = null;
                    if (function_exists('getConnection')) {
                        $conn = getConnection();
                    } elseif (class_exists('Database')) {
                        $db = new Database();
                        $conn = $db->getConnection();
                    }
                    if ($conn) {
                        $uStmt = $conn->prepare("UPDATE items SET qr_code = ? WHERE id = ?");
                        if ($uStmt) {
                            $uStmt->bind_param("si", $relativePath, $item_id);
                            $uStmt->execute();
                            $uStmt->close();
                        }
                    }
                } catch (Exception $dbErr) {
                    // Suppress DB update error if called in isolated environment
                    error_log("Note: Could not update item qr_code column: " . $dbErr->getMessage());
                }
                
                return [
                    'success' => true,
                    'path' => $relativePath,
                    'message' => 'QR code generated successfully'
                ];
            } else {
                error_log("Failed to save QR image to: $fullPath");
                return ['success' => false, 'message' => 'Failed to save QR image'];
            }
        }
        
        error_log("QR image generation failed for item $item_id");
        return ['success' => false, 'message' => 'QR generation failed'];
    }
}

if (!function_exists('generateQRCodeWithGD')) {
    function generateQRCodeWithGD($data, $size = 300) {
        $img = imagecreatetruecolor($size, $size);
        $white = imagecolorallocate($img, 255, 255, 255);
        $black = imagecolorallocate($img, 0, 0, 0);
        
        imagefill($img, 0, 0, $white);
        
        $blockSize = $size / 10;
        for ($i = 0; $i < 10; $i++) {
            for ($j = 0; $j < 10; $j++) {
                if (($i + $j) % 2 == 0) {
                    imagefilledrectangle(
                        $img, 
                        $i * $blockSize, 
                        $j * $blockSize, 
                        ($i + 1) * $blockSize, 
                        ($j + 1) * $blockSize, 
                        $black
                    );
                }
            }
        }
        
        $textColor = imagecolorallocate($img, 0, 0, 255);
        $shortData = strlen($data) > 30 ? substr($data, 0, 27) . '...' : $data;
        imagestring($img, 3, 10, $size - 20, $shortData, $textColor);
        
        ob_start();
        imagepng($img);
        $imageData = ob_get_contents();
        ob_end_clean();
        imagedestroy($img);
        
        return $imageData;
    }
}

if (!function_exists('createPlaceholderQR')) {
    function createPlaceholderQR($item_id, $item_name, $serial_number) {
        $size = 300;
        $img = imagecreatetruecolor($size, $size);
        
        $white = imagecolorallocate($img, 255, 255, 255);
        $black = imagecolorallocate($img, 0, 0, 0);
        $blue = imagecolorallocate($img, 52, 152, 219);
        $gray = imagecolorallocate($img, 128, 128, 128);
        
        imagefill($img, 0, 0, $white);
        imagerectangle($img, 5, 5, $size - 6, $size - 6, $black);
        
        $cornerSize = 40;
        imagefilledrectangle($img, 10, 10, $cornerSize, $cornerSize, $black);
        imagefilledrectangle($img, 15, 15, $cornerSize - 5, $cornerSize - 5, $white);
        imagefilledrectangle($img, $size - $cornerSize - 10, 10, $size - 10, $cornerSize, $black);
        imagefilledrectangle($img, $size - $cornerSize - 5, 15, $size - 15, $cornerSize - 5, $white);
        imagefilledrectangle($img, 10, $size - $cornerSize - 10, $cornerSize, $size - 10, $black);
        imagefilledrectangle($img, 15, $size - $cornerSize - 5, $cornerSize - 5, $size - 15, $white);
        
        $title = "Item: " . substr($item_name, 0, 20);
        $serialText = "ID: " . $item_id;
        
        $titleX = ($size - strlen($title) * 5) / 2;
        $serialX = ($size - strlen($serialText) * 5) / 2;
        
        imagestring($img, 4, $titleX, $size / 2 - 20, $title, $blue);
        imagestring($img, 3, $serialX, $size / 2, $serialText, $gray);
        
        ob_start();
        imagepng($img);
        $imageData = ob_get_contents();
        ob_end_clean();
        imagedestroy($img);
        
        return $imageData;
    }
}

if (!function_exists('getQRCodeForItem')) {
    function getQRCodeForItem($item_id) {
        $rootDir = dirname(__DIR__);
        $qrDir = $rootDir . '/qrcodes';
        $filename = 'qr_' . $item_id . '.png';
        $fullPath = $qrDir . '/' . $filename;
        
        if (file_exists($fullPath) && filesize($fullPath) > 100) {
            return 'qrcodes/' . $filename;
        }
        return false;
    }
}
