<?php
// api/generate_qr.php
require_once '../bootstrap.php';
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

// Check authentication
if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Authentication required']);
    exit;
}

// Include QR generator
require_once '../includes/qr_generator.php';

// Get POST data
$item_id = $_POST['item_id'] ?? $_POST['id'] ?? 0;

if (!$item_id) {
    echo json_encode(['success' => false, 'message' => 'Item ID is required']);
    exit;
}

try {
    // Get database connection
    $db = new Database();
    $conn = $db->getConnection();
    
    // Fetch item details
    $stmt = $conn->prepare("SELECT * FROM items WHERE id = ?");
    $stmt->bind_param("i", $item_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $item = $result->fetch_assoc();
    
    if (!$item) {
        echo json_encode(['success' => false, 'message' => 'Item not found']);
        exit;
    }
    
    // Generate QR code
    $qr_result = generateQRCodeForItem(
        $item['id'],
        $item['item_name'],
        $item['serial_number'],
        $item['stock_location'] ?? ''
    );
    
    if ($qr_result && $qr_result['success']) {
        // Get the full URL for the QR code
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
        $host = $_SERVER['HTTP_HOST'];
        $baseUrl = $protocol . $host;
        $fullQrUrl = $baseUrl . '/ability_app_main/' . ltrim($qr_result['path'], '/');
        
        echo json_encode([
            'success' => true,
            'message' => 'QR code generated successfully',
            'qr_code' => $fullQrUrl,
            'qr_path' => $qr_result['path']
        ]);
    } else {
        echo json_encode([
            'success' => false, 
            'message' => $qr_result['message'] ?? 'Failed to generate QR code'
        ]);
    }
    
    $db->close();
    
} catch (Exception $e) {
    error_log("QR Generation Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error generating QR code: ' . $e->getMessage()]);
}
?>