<?php
// api/cases/pack_item.php - Pack item into a case
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

    $fixture_serial = isset($_POST['fixture_serial']) ? trim($_POST['fixture_serial']) : '';
    $case_name = isset($_POST['case_name']) ? trim($_POST['case_name']) : '';
    $case_serial = isset($_POST['case_serial']) ? trim($_POST['case_serial']) : '';

    if (empty($fixture_serial) || (empty($case_name) && empty($case_serial))) {
        throw new Exception('Missing required parameters: fixture_serial and case identification.');
    }

    // Helper functions to extract ID or serial from QR code strings
    function extractItemId($data) {
        $json = json_decode($data, true);
        if ($json) {
            if (isset($json['i'])) return $json['i'];
            if (isset($json['id'])) return $json['id'];
            if (isset($json['item_id'])) return $json['item_id'];
        }
        if (preg_match('/ID:(\d+)/i', $data, $matches)) {
            return $matches[1];
        }
        if (is_numeric($data)) {
            return $data;
        }
        return null;
    }

    function extractSerialNumber($data) {
        $json = json_decode($data, true);
        if ($json) {
            if (isset($json['s'])) return $json['s'];
            if (isset($json['serial'])) return $json['serial'];
            if (isset($json['serial_number'])) return $json['serial_number'];
        }
        if (preg_match('/SN:([^|]+)/i', $data, $matches)) {
            return trim($matches[1]);
        }
        return null;
    }

    // Extract potential values
    $scannedId = extractItemId($fixture_serial);
    $scannedSerial = extractSerialNumber($fixture_serial) ?: $fixture_serial;

    // Search for item
    $item = null;
    if ($scannedId) {
        $stmt = $conn->prepare("SELECT id, item_name, serial_number, category FROM items WHERE id = ?");
        $stmt->bind_param("i", $scannedId);
        $stmt->execute();
        $item = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    }

    if (!$item && $scannedSerial) {
        $stmt = $conn->prepare("SELECT id, item_name, serial_number, category FROM items WHERE serial_number = ?");
        $stmt->bind_param("s", $scannedSerial);
        $stmt->execute();
        $item = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    }

    if (!$item) {
        throw new Exception("Equipment item not found. Please verify the serial number or QR code.");
    }

    // Prevent packing a case inside itself or another case
    if ($item['serial_number'] === $case_serial || $item['item_name'] === $case_name) {
        throw new Exception("Cannot pack a Fly Case inside itself.");
    }

    // Update item's storage_location to be the Case Name (or Case Serial)
    // We will use Case Name as the primary storage location name
    $targetLocation = !empty($case_name) ? $case_name : $case_serial;

    $stmt = $conn->prepare("UPDATE items SET storage_location = ? WHERE id = ?");
    $stmt->bind_param("si", $targetLocation, $item['id']);
    
    if (!$stmt->execute()) {
        throw new Exception("Database update failed: " . $conn->error);
    }
    $stmt->close();

    echo json_encode([
        'success' => true,
        'message' => "'" . $item['item_name'] . "' (Serial: " . $item['serial_number'] . ") successfully packed."
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
