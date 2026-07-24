<?php
// api/cases/rfid_sync.php - Sync database with RFID physical scan results
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

    $case_id = isset($_POST['case_id']) ? intval($_POST['case_id']) : 0;
    $scanned_item_ids = isset($_POST['scanned_item_ids']) ? $_POST['scanned_item_ids'] : [];

    if ($case_id <= 0) {
        throw new Exception('Invalid or missing Case ID.');
    }

    // 1. Fetch case details
    $stmt = $conn->prepare("SELECT id, item_name, serial_number FROM items WHERE id = ? AND (category = 'Cases' OR item_name LIKE '%Case%')");
    $stmt->bind_param("i", $case_id);
    $stmt->execute();
    $case = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$case) {
        throw new Exception('Fly Case not found in database.');
    }

    $caseName = $case['item_name'];
    $caseSerial = $case['serial_number'];

    // Start transaction
    $conn->begin_transaction();

    // 2. Unpack all items currently registered to this case
    $stmt1 = $conn->prepare("UPDATE items SET storage_location = NULL WHERE storage_location = ? OR storage_location = ?");
    $stmt1->bind_param("ss", $caseName, $caseSerial);
    if (!$stmt1->execute()) {
        throw new Exception("Failed to clear current case contents: " . $conn->error);
    }
    $stmt1->close();

    // 3. Pack only the physically scanned items (if any are selected)
    $packedCount = 0;
    if (!empty($scanned_item_ids) && is_array($scanned_item_ids)) {
        // Validate array values are integers
        $sanitized_ids = array_map('intval', $scanned_item_ids);
        
        // Exclude the case itself from being packed into itself
        $sanitized_ids = array_filter($sanitized_ids, function($id) use ($case_id) {
            return $id !== $case_id;
        });

        if (!empty($sanitized_ids)) {
            // Prepare update query
            $inClause = implode(',', $sanitized_ids);
            
            // Note: We use case name as the primary storage location name
            $updateQuery = "UPDATE items SET storage_location = ? WHERE id IN ($inClause)";
            $stmt2 = $conn->prepare($updateQuery);
            $stmt2->bind_param("s", $caseName);
            if (!$stmt2->execute()) {
                throw new Exception("Failed to pack scanned items: " . $conn->error);
            }
            $packedCount = $stmt2->affected_rows;
            $stmt2->close();
        }
    }

    $conn->commit();

    echo json_encode([
        'success' => true,
        'message' => "Database synchronized successfully! Case '{$caseName}' now contains {$packedCount} items."
    ]);

} catch (Exception $e) {
    if (isset($conn) && $conn->in_transaction) {
        $conn->rollback();
    }
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
