<?php
// api/items/generate_qr.php
header('Content-Type: application/json');
require_once '../../bootstrap.php';
require_once '../../includes/db_connect.php';
require_once '../../includes/functions.php';

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Authentication required']);
    exit();
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid item ID']);
    exit();
}

$id = intval($_GET['id']);

try {
    $db = new Database();
    $conn = $db->getConnection();

    // Fetch item data
    $stmt = $conn->prepare("SELECT id, item_name, serial_number, stock_location FROM items WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $item = $result->fetch_assoc();
    $stmt->close();

    if (!$item) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Item not found']);
        exit();
    }

    // Generate QR data with item info
    $qr_data = generateQRDataForItem(
        $item['id'],
        $item['item_name'],
        $item['serial_number'],
        $item['stock_location']
    );

    // Generate QR code image
    $qr_image_path = generateQRCodeImage($qr_data, $item['id']);

    // Update database
    $updateStmt = $conn->prepare("UPDATE items SET qr_code = ?, updated_at = NOW() WHERE id = ?");
    $updateStmt->bind_param('si', $qr_data, $id);

    if ($updateStmt->execute()) {
        echo json_encode([
            'success' => true,
            'message' => 'QR code generated successfully',
            'qr_image' => $qr_image_path,
            'qr_data' => json_decode($qr_data, true)
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to save QR code']);
    }

    $updateStmt->close();
    $db->close();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
