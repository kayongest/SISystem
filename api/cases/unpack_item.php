<?php
// api/cases/unpack_item.php - Unpack item(s) from a case
header('Content-Type: application/json');

try {
    session_start();
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('Unauthorized access. Please log in.', 401);
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('POST request method required.', 405);
    }

    require_once '../../includes/db_connect.php';
    $conn = getConnection();

    if (!$conn) {
        throw new Exception('Database connection failed.');
    }

    // Handle Unpack All Items from a case
    if (isset($_POST['unpack_all']) && $_POST['unpack_all'] == true) {
        $case_name = isset($_POST['case_name']) ? trim($_POST['case_name']) : '';
        $case_serial = isset($_POST['case_serial']) ? trim($_POST['case_serial']) : '';

        if (empty($case_name) && empty($case_serial)) {
            throw new Exception('Missing case details to unpack all.');
        }

        $stmt = $conn->prepare("
            UPDATE items 
            SET storage_location = NULL 
            WHERE storage_location = ? OR storage_location = ?
        ");
        $stmt->bind_param("ss", $case_name, $case_serial);
        
        if (!$stmt->execute()) {
            throw new Exception("Failed to unpack all items: " . $conn->error);
        }
        $affectedRows = $stmt->affected_rows;
        $stmt->close();

        echo json_encode([
            'success' => true,
            'message' => "Successfully unpacked {$affectedRows} items."
        ]);
        exit();
    }

    // Handle Unpack Single Item
    $item_id = isset($_POST['item_id']) ? intval($_POST['item_id']) : 0;

    if ($item_id <= 0) {
        throw new Exception('Invalid or missing item_id.');
    }

    $stmt = $conn->prepare("UPDATE items SET storage_location = NULL WHERE id = ?");
    $stmt->bind_param("i", $item_id);
    
    if (!$stmt->execute()) {
        throw new Exception("Database update failed: " . $conn->error);
    }
    $stmt->close();

    echo json_encode([
        'success' => true,
        'message' => "Item unpacked successfully."
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
