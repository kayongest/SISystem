<?php
// api/items/check_qr.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once realpath(__DIR__ . '/../../includes/db_connect.php');

$response = ['has_qr' => false, 'qr_path' => '', 'item_id' => 0];

try {
    session_start();
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('Not logged in', 401);
    }
    
    $item_id = $_GET['item_id'] ?? 0;
    if (!$item_id) {
        throw new Exception('Item ID required');
    }
    
    $response['item_id'] = $item_id;
    
    $db = new Database();
    $conn = $db->getConnection();
    
    $sql = "SELECT qr_code FROM items WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $item_id);
    $stmt->execute();
    $stmt->bind_result($qr_code);
    
    if ($stmt->fetch()) {
        if ($qr_code && $qr_code !== 'pending' && $qr_code !== '') {
            $response['has_qr'] = true;
            $response['qr_path'] = $qr_code;
        } else if ($qr_code === 'pending') {
            $response['status'] = 'pending';
        }
    }
    
    $stmt->close();
    $db->close();
} catch (Exception $e) {
    $response['error'] = $e->getMessage();
}

echo json_encode($response);