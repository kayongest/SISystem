<?php
// process_scan.php with database connection
header('Content-Type: application/json');

// Database configuration
$host = 'localhost';
$dbname = 'ability_db';
$username = 'root';
$password = '';

try {
    // Create connection
    $conn = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        $barcode = $input['barcode'] ?? '';
        
        if (!empty($barcode)) {
            // Query database
            $stmt = $conn->prepare("SELECT * FROM products WHERE barcode = :barcode");
            $stmt->bindParam(':barcode', $barcode);
            $stmt->execute();
            
            $product = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($product) {
                // Log the scan
                $logStmt = $conn->prepare("INSERT INTO scan_log (barcode, scanned_at) VALUES (:barcode, NOW())");
                $logStmt->bindParam(':barcode', $barcode);
                $logStmt->execute();
                
                echo json_encode([
                    'status' => 'success',
                    'found' => true,
                    'product' => $product
                ]);
            } else {
                echo json_encode([
                    'status' => 'success',
                    'found' => false,
                    'message' => 'Product not found'
                ]);
            }
        } else {
            echo json_encode(['status' => 'error', 'message' => 'No barcode']);
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Invalid method']);
    }
    
} catch(PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
}

$conn = null;
?>