<?php
// print_labels.php - Printable Avery 5160 QR Code Labels View
session_start();
require_once 'bootstrap.php';

// Check authentication
if (!isLoggedIn()) {
    header('Location: login.php');
    exit();
}

$idsParam = $_GET['ids'] ?? '';
if (empty($idsParam)) {
    die("Error: No items selected for printing.");
}

// Sanitize and validate list of IDs
$idArray = array_filter(array_map('intval', explode(',', $idsParam)));
if (empty($idArray)) {
    die("Error: Invalid item IDs provided.");
}

$inClause = implode(',', $idArray);

// Database connection
$host = 'localhost';
$username = 'root';
$password = '';
$database = 'ability_db';
$conn = new mysqli($host, $username, $password, $database);

if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

$conn->set_charset("utf8mb4");

$query = "SELECT id, item_name, serial_number, brand, model, stock_location FROM items WHERE id IN ($inClause) ORDER BY item_name";
$result = $conn->query($query);

$items = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $items[] = $row;
    }
}
$conn->close();

// Get local server IP for QR code URL fallback
$server_ip = $_SERVER['SERVER_ADDR'] ?? '172.20.43.13';
$server_port = $_SERVER['SERVER_PORT'] == '80' ? '' : ':' . $_SERVER['SERVER_PORT'];
$base_url = "http://{$server_ip}{$server_port}" . rtrim(dirname($_SERVER['PHP_SELF']), '/\\') . '/';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Print QR Labels - aBility</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- QRCode JS Library -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>

    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');

        body {
            background-color: #f8fafc;
            font-family: 'Inter', sans-serif;
            margin: 0;
            padding: 0;
        }

        /* Control Panel (Sticky at top, hidden during print) */
        .print-control-panel {
            background: white;
            border-bottom: 1px solid #e2e8f0;
            padding: 15px 30px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
            position: sticky;
            top: 0;
            z-index: 1000;
        }

        /* Avery 5160 Layout Sheet Styling */
        .label-sheet-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 30px 0;
        }

        .label-page {
            background: white;
            box-shadow: 0 10px 25px rgba(0,0,0,0.05);
            border: 1px dashed #cbd5e1;
            width: 8.5in;
            height: 11in;
            box-sizing: border-box;
            padding: 0.5in 0.2197in; /* Margins: top/bottom 0.5in, left/right 0.2197in */
            margin-bottom: 40px;
            position: relative;
            overflow: hidden;
            display: grid;
            grid-template-columns: repeat(3, 2.625in); /* 3 columns of 2.625in */
            grid-auto-rows: 1.0in; /* 10 rows of 1.0in */
            column-gap: 0.135in; /* Gap between columns */
            row-gap: 0in; /* Gap between rows */
        }

        /* Individual Label Card styling */
        .label-card {
            width: 2.625in;
            height: 1.0in;
            padding: 0.08in 0.1in;
            box-sizing: border-box;
            border: 1px dotted #e2e8f0; /* Hidden or invisible in print if needed */
            display: flex;
            align-items: center;
            overflow: hidden;
            background: #fff;
            gap: 0.1in;
        }

        .label-qr-container {
            width: 0.8in;
            height: 0.8in;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .label-info {
            display: flex;
            flex-direction: column;
            justify-content: center;
            height: 100%;
            overflow: hidden;
            font-size: 8px; /* Compact label font size */
            line-height: 1.2;
            color: #334155;
            flex-grow: 1;
        }

        .label-title {
            font-weight: 700;
            font-size: 9px;
            color: #0f172a;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            margin-bottom: 2px;
        }

        .label-brand-model {
            font-weight: 500;
            color: #475569;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            margin-bottom: 2px;
        }

        .label-meta {
            font-family: monospace;
            font-size: 7.5px;
            margin-bottom: 1px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .label-id-badge {
            background: #e2e8f0;
            color: #0f172a;
            padding: 1px 4px;
            border-radius: 3px;
            font-weight: 700;
            display: inline-block;
            margin-top: 2px;
            font-size: 7px;
            text-transform: uppercase;
        }

        /* Print Media Styles */
        @media print {
            body {
                background: white !important;
                margin: 0 !important;
                padding: 0 !important;
            }

            .print-control-panel, .alert {
                display: none !important;
            }

            .label-sheet-container {
                padding: 0 !important;
            }

            .label-page {
                box-shadow: none !important;
                border: none !important;
                margin: 0 !important;
                page-break-after: always !important;
                /* Reset margins for printing */
                padding: 0.5in 0.2197in !important;
            }

            .label-card {
                border: none !important; /* Make borders disappear on actual labels */
                outline: none !important;
            }
        }
    </style>
</head>
<body>

    <!-- Print Control Panel -->
    <div class="print-control-panel no-print">
        <div class="d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center gap-3">
                <a href="items.php" class="btn btn-outline-secondary btn-sm">
                    <i class="fas fa-arrow-left me-2"></i>Back to Equipment
                </a>
                <h5 class="mb-0 fw-bold text-dark">
                    <i class="fas fa-print me-2 text-primary"></i>Print QR Labels
                </h5>
                <span class="badge bg-primary rounded-pill"><?php echo count($items); ?> Item(s) Selected</span>
            </div>
            
            <div class="d-flex gap-2">
                <button onclick="window.print()" class="btn btn-primary btn-sm px-4">
                    <i class="fas fa-print me-2"></i>Print Labels
                </button>
            </div>
        </div>
        
        <!-- Helpful Tips Alert -->
        <div class="alert alert-info mt-3 mb-0 py-2 px-3 small border-0 d-flex align-items-center gap-2">
            <i class="fas fa-info-circle text-primary"></i>
            <div>
                <strong>Printer settings tip:</strong> To align perfectly, in the print dialog set <strong>Margins</strong> to <strong>None</strong> (or Minimal) and <strong>Scale</strong> to <strong>100%</strong> (Default). Disable headers and footers.
            </div>
        </div>
    </div>

    <!-- Printable Grid Sheets Container -->
    <div class="label-sheet-container">
        <?php
        $totalItems = count($items);
        $labelsPerPage = 30; // Avery 5160 has 30 labels (3x10 grid)
        $pagesCount = ceil($totalItems / $labelsPerPage);
        
        for ($p = 0; $p < $pagesCount; $p++):
        ?>
            <div class="label-page">
                <?php
                for ($l = 0; $l < $labelsPerPage; $l++):
                    $index = ($p * $labelsPerPage) + $l;
                    if ($index < $totalItems):
                        $item = $items[$index];
                        $qrValue = $base_url . "mobile_scan.php?id=" . $item['id'];
                ?>
                        <div class="label-card">
                            <!-- Left: QR Code Container -->
                            <div class="label-qr-container" id="qr-<?php echo $item['id']; ?>" data-qr-val="<?php echo htmlspecialchars($qrValue); ?>"></div>
                            
                            <!-- Right: Item Text Info -->
                            <div class="label-info">
                                <div class="label-title"><?php echo htmlspecialchars($item['item_name']); ?></div>
                                <div class="label-brand-model">
                                    <?php 
                                    $brandModel = trim(($item['brand'] ?? '') . ' ' . ($item['model'] ?? ''));
                                    echo htmlspecialchars(!empty($brandModel) ? $brandModel : 'No Brand/Model'); 
                                    ?>
                                </div>
                                <div class="label-meta">SN: <?php echo htmlspecialchars(!empty($item['serial_number']) ? $item['serial_number'] : 'N/A'); ?></div>
                                <div class="label-meta">LOC: <?php echo htmlspecialchars(!empty($item['stock_location']) ? $item['stock_location'] : 'N/A'); ?></div>
                                <div>
                                    <span class="label-id-badge">ID: #<?php echo $item['id']; ?></span>
                                </div>
                            </div>
                        </div>
                <?php
                    else:
                        // Empty spacer card to maintain Avery grid alignment
                        echo '<div class="label-card" style="visibility: hidden;"></div>';
                    endif;
                endfor;
                ?>
            </div>
        <?php endfor; ?>
    </div>

    <!-- Client-side QR Code Generation Script -->
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            const qrContainers = document.querySelectorAll(".label-qr-container");
            qrContainers.forEach(container => {
                const qrValue = container.getAttribute("data-qr-val");
                if (qrValue) {
                    new QRCode(container, {
                        text: qrValue,
                        width: 68, // ~0.7in
                        height: 68,
                        colorDark: "#0f172a",
                        colorLight: "#ffffff",
                        correctLevel: QRCode.CorrectLevel.M
                    });
                }
            });
            
            // Auto open print dialog for convenience (brief timeout to allow QR library to finish drawing)
            setTimeout(() => {
                window.print();
            }, 1000);
        });
    </script>
</body>
</html>
