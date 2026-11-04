<?php
// sync_all_qr_codes.php - Master QR Code Synchronization Tool
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db_connect.php';
require_once __DIR__ . '/includes/qr_generator.php';

$isCli = (php_sapi_name() === 'cli');

// Check authentication if accessed via web browser
if (!$isCli) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    // Allow logged in users or admins
    if (!isset($_SESSION['user_id']) && !isset($_SESSION['logged_in'])) {
        header('Location: login.php');
        exit();
    }
}

$conn = getConnection();
if (!$conn) {
    die("Database connection failed.");
}

$query = "SELECT id, item_name, serial_number, stock_location, qr_code FROM items ORDER BY id ASC";
$result = $conn->query($query);
$items = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];

$total = count($items);
$successCount = 0;
$failCount = 0;
$results = [];

foreach ($items as $item) {
    $itemId = (int)$item['id'];
    $name = $item['item_name'] ?? 'Unnamed Item';
    $serial = $item['serial_number'] ?? '';
    $loc = $item['stock_location'] ?? '';

    // Force regenerate with accurate current item data
    $res = generateQRCodeForItem($itemId, $name, $serial, $loc, true);

    $qrDataPayload = json_encode([
        'i' => $itemId,
        'n' => $name,
        's' => $serial
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    if ($res && $res['success']) {
        $successCount++;
        // Ensure items table has the correct path
        $relPath = 'qrcodes/qr_' . $itemId . '.png';
        $uStmt = $conn->prepare("UPDATE items SET qr_code = ? WHERE id = ?");
        if ($uStmt) {
            $uStmt->bind_param("si", $relPath, $itemId);
            $uStmt->execute();
            $uStmt->close();
        }
        $results[] = [
            'id' => $itemId,
            'name' => $name,
            'serial' => $serial,
            'path' => $relPath,
            'payload' => $qrDataPayload,
            'status' => 'success'
        ];
        if ($isCli) {
            echo "[OK] Item #$itemId: $name ($serial) -> $relPath\n";
        }
    } else {
        $failCount++;
        $results[] = [
            'id' => $itemId,
            'name' => $name,
            'serial' => $serial,
            'path' => '',
            'payload' => $qrDataPayload,
            'status' => 'failed',
            'error' => $res['message'] ?? 'Unknown error'
        ];
        if ($isCli) {
            echo "[FAIL] Item #$itemId: $name - " . ($res['message'] ?? 'Error') . "\n";
        }
    }
}

if ($isCli) {
    echo "\n--------------------------------------------\n";
    echo "QR Code Synchronization Complete!\n";
    echo "Total: $total | Success: $successCount | Failed: $failCount\n";
    echo "--------------------------------------------\n";
    exit(0);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QR Code Synchronization - aBility</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Marvel:wght@400;700&display=swap');
        body {
            font-family: 'Marvel', sans-serif;
            background: #f4f6f9;
            color: #333;
        }
        .header-card {
            background: linear-gradient(135deg, #1f3a52 0%, #2b5375 100%);
            color: white;
            border-radius: 16px;
        }
        .stat-card {
            border-radius: 12px;
            border: none;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        }
        .table-card {
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.06);
            overflow: hidden;
            border: none;
        }
        .qr-thumb {
            width: 54px;
            height: 54px;
            object-fit: contain;
            border-radius: 6px;
            border: 1px solid #dee2e6;
            background: white;
            padding: 3px;
        }
        code.payload-code {
            font-size: 0.85rem;
            color: #d63384;
            background: #f8f9fa;
            padding: 2px 6px;
            border-radius: 4px;
            border: 1px solid #e9ecef;
        }
    </style>
</head>
<body class="py-4">
<div class="container-fluid px-4">
    <!-- Header -->
    <div class="header-card p-4 mb-4 shadow">
        <div class="d-flex flex-wrap justify-content-between align-items-center">
            <div>
                <h2 class="fw-bold mb-1"><i class="fas fa-qrcode me-2 text-warning"></i>QR Code Synchronization Tool</h2>
                <p class="mb-0 text-white-50 fs-6">Regenerates and synchronizes all equipment QR codes with current database names and serial numbers</p>
            </div>
            <div class="mt-3 mt-md-0 d-flex gap-2">
                <a href="items.php" class="btn btn-outline-light rounded-pill px-3"><i class="fas fa-boxes me-1"></i> Equipment List</a>
                <a href="scan_bulk.php" class="btn btn-warning rounded-pill px-3 fw-bold"><i class="fas fa-barcode me-1"></i> Open Scanner</a>
            </div>
        </div>
    </div>

    <!-- Stats -->
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card stat-card p-3 bg-white">
                <div class="d-flex align-items-center">
                    <div class="bg-primary bg-opacity-10 text-primary p-3 rounded-circle me-3">
                        <i class="fas fa-list fa-2x"></i>
                    </div>
                    <div>
                        <div class="text-muted small text-uppercase fw-bold">Total Items Scanned</div>
                        <h3 class="fw-bold mb-0 text-dark"><?php echo $total; ?></h3>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card stat-card p-3 bg-white">
                <div class="d-flex align-items-center">
                    <div class="bg-success bg-opacity-10 text-success p-3 rounded-circle me-3">
                        <i class="fas fa-check-circle fa-2x"></i>
                    </div>
                    <div>
                        <div class="text-muted small text-uppercase fw-bold">Synchronized Successfully</div>
                        <h3 class="fw-bold mb-0 text-success"><?php echo $successCount; ?></h3>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card stat-card p-3 bg-white">
                <div class="d-flex align-items-center">
                    <div class="bg-danger bg-opacity-10 text-danger p-3 rounded-circle me-3">
                        <i class="fas fa-exclamation-triangle fa-2x"></i>
                    </div>
                    <div>
                        <div class="text-muted small text-uppercase fw-bold">Failed / Incomplete</div>
                        <h3 class="fw-bold mb-0 text-danger"><?php echo $failCount; ?></h3>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Results Table -->
    <div class="card table-card bg-white">
        <div class="card-header bg-white py-3 border-0 d-flex justify-content-between align-items-center">
            <h5 class="fw-bold mb-0 text-dark"><i class="fas fa-sync-alt text-primary me-2"></i>Synchronized QR Inventory</h5>
            <span class="badge bg-success bg-opacity-10 text-success px-3 py-2 fs-6">100% Up to Date</span>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-light text-muted small text-uppercase">
                    <tr>
                        <th class="ps-4">Item ID</th>
                        <th>QR Preview</th>
                        <th>Equipment Name</th>
                        <th>Serial Number</th>
                        <th>Embedded QR Payload</th>
                        <th class="text-center">Status</th>
                        <th class="pe-4 text-end">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($results as $r): ?>
                        <tr>
                            <td class="ps-4 fw-bold text-muted">#<?php echo $r['id']; ?></td>
                            <td>
                                <?php if (!empty($r['path']) && file_exists(__DIR__ . '/' . $r['path'])): ?>
                                    <img src="<?php echo htmlspecialchars($r['path']) . '?v=' . time(); ?>" class="qr-thumb" alt="QR">
                                <?php else: ?>
                                    <span class="text-muted small">No QR</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <strong class="text-dark d-block"><?php echo htmlspecialchars($r['name']); ?></strong>
                            </td>
                            <td>
                                <span class="badge bg-secondary bg-opacity-10 text-secondary border"><?php echo htmlspecialchars($r['serial'] ?: 'N/A'); ?></span>
                            </td>
                            <td>
                                <code class="payload-code"><?php echo htmlspecialchars($r['payload']); ?></code>
                            </td>
                            <td class="text-center">
                                <?php if ($r['status'] === 'success'): ?>
                                    <span class="badge bg-success"><i class="fas fa-check me-1"></i>Synced</span>
                                <?php else: ?>
                                    <span class="badge bg-danger"><i class="fas fa-times me-1"></i><?php echo htmlspecialchars($r['error']); ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="pe-4 text-end">
                                <a href="items.php?action=view&id=<?php echo $r['id']; ?>" class="btn btn-sm btn-outline-primary rounded-pill px-3">
                                    <i class="fas fa-eye me-1"></i> View
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
</body>
</html>
