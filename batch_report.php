<?php
// batch_report.php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Get batch ID from URL
$batch_id = $_GET['batch_id'] ?? '';

if (empty($batch_id)) {
    $_SESSION['flash_messages']['error'] = 'No batch specified';
    header('Location: batch_history.php');
    exit();
}

// Include bootstrap and database connection
require_once 'includes/db_connect.php';
$conn = getConnection();

// Fetch batch details
$stmt = $conn->prepare("SELECT sm.*, 
                              t.full_name as tech_full_name, 
                              t.signature_image as technician_sig_file,
                              sc.full_name as controller_full_name,
                              sc.signature_image as controller_sig_file,
                              a.full_name as approved_by_user_name,
                              a.signature_image as approver_sig_file
                       FROM stock_movements sm
                       LEFT JOIN users t ON sm.technician_id = t.id
                       LEFT JOIN users sc ON sm.stock_controller_id = sc.id
                       LEFT JOIN users a ON sm.approved_by_id = a.id
                       WHERE sm.batch_number = ?");

if (!$stmt) {
    die("Query preparation failed: " . $conn->error);
}
$stmt->bind_param("s", $batch_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("Batch not found in stock_movements table for ID: " . htmlspecialchars($batch_id));
}

$batch = $result->fetch_assoc();

// Fetch batch items
$itemsSql = "SELECT * FROM batch_items WHERE batch_id = ?";
$stmt2 = $conn->prepare($itemsSql);
$stmt2->bind_param("i", $batch['id']);
$stmt2->execute();
$itemsResult = $stmt2->get_result();

$items = [];
while ($item = $itemsResult->fetch_assoc()) {
    $items[] = $item;
}

$stmt->close();
$stmt2->close();
$conn->close();

// Get current user session info for automatic setting if missing
$current_user_id = $_SESSION['user_id'] ?? null;
$current_user_name = $_SESSION['full_name'] ?? $_SESSION['username'] ?? '';
$current_user_role = $_SESSION['role'] ?? '';

// Get names for signatures
$technician_name = $batch['tech_full_name'] ?? $batch['submitted_by'] ?? '';
$stock_controller = $batch['controller_full_name'] ?? $batch['stock_controller_name'] ?? '';

// Automatic setting if authenticated but record is incomplete
if (empty($technician_name) && $current_user_role === 'technician') {
    $technician_name = $current_user_name;
}
if (empty($stock_controller) && $current_user_role === 'stock_controller') {
    $stock_controller = $current_user_name;
}

// Final fallback if still empty (though we prefer empty over wrong names)
if (empty($technician_name)) $technician_name = 'Not Assigned';
if (empty($stock_controller)) $stock_controller = 'Not Assigned';

// Helper function to find the latest signature for a user ID
function getLatestSignature($userId) {
    if (empty($userId)) return '';
    $sig_dir = 'uploads/signatures/';
    $pattern = $sig_dir . "signature_{$userId}_*.png";
    $files = glob($pattern);
    if (!$files) {
        // Try alternate pattern
        $pattern = $sig_dir . "sig_{$userId}_*.png";
        $files = glob($pattern);
    }
    
    if ($files) {
        usort($files, function($a, $b) {
            return filemtime($b) - filemtime($a);
        });
        return $files[0];
    }
    return '';
}

// Resolve signatures
$tech_sig_path = '';
if (!empty($batch['technician_sig_file']) && file_exists($batch['technician_sig_file'])) {
    $tech_sig_path = $batch['technician_sig_file'];
} else {
    // Try to get latest from technician_id
    $tech_sig_path = getLatestSignature($batch['technician_id']);
}

// If still empty and current user is technician, use their session signature
if (empty($tech_sig_path) && $current_user_role === 'technician' && !empty($_SESSION['signature_image'])) {
    $tech_sig_path = $_SESSION['signature_image'];
}

$approver_sig_path = '';
if (!empty($batch['approver_sig_file']) && file_exists($batch['approver_sig_file'])) {
    $approver_sig_path = $batch['approver_sig_file'];
} elseif (!empty($batch['controller_sig_file']) && file_exists($batch['controller_sig_file'])) {
    $approver_sig_path = $batch['controller_sig_file'];
} else {
    // Try to get latest from approved_by_id or stock_controller_id
    $approver_sig_path = getLatestSignature($batch['approved_by_id'] ?? $batch['stock_controller_id']);
}

// If still empty and current user is stock_controller, use their session signature
if (empty($approver_sig_path) && $current_user_role === 'stock_controller' && !empty($_SESSION['signature_image'])) {
    $approver_sig_path = $_SESSION['signature_image'];
}

// Determine if transport is required based on movement type
$movement_type = $batch['movement_type'] ?? '';
$transport_required = in_array($movement_type, ['stock_to_stock', 'stock_to_venue_transport']);

// Set vehicle number display value
if ($transport_required) {
    $vehicle_display = !empty($batch['transport_vehicle_number']) ? htmlspecialchars($batch['transport_vehicle_number']) : 'Not Provided';
} else {
    $vehicle_display = 'Not Required';
}

$pageTitle = "Material Requisition - " . $batch_id;
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- PDF Generation Library -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Hubot+Sans:ital,wght@0,200..900;1,200..900&family=Oswald:wght@200..700&display=swap');

        /* Print Settings */
        @page {
            size: A4 portrait;
            margin: 15mm;
        }

        @media print {
            body {
                background: white !important;
                padding: 0 !important;
            }

            .no-print {
                display: none !important;
            }

            .report-container {
                box-shadow: none !important;
                margin: 0 !important;
                width: 100% !important;
                max-width: none !important;
                border-radius: 0 !important;
                min-height: auto !important;
            }

            /* Repeating Header Magic */
            .print-table {
                width: 100%;
                border-collapse: collapse;
            }

            .print-table > thead > tr > td,
            .print-table > tbody > tr > td,
            .print-table > tfoot > tr > td {
                padding: 0 !important;
            }

            .print-header {
                display: table-header-group;
            }

            .print-footer {
                display: table-footer-group;
            }

            .print-content {
                display: table-row-group;
            }
        }

        /* Body Styles */
        body {
            background: #f0f2f5;
            font-family: "Hubot Sans", sans-serif;
            padding: 40px 0;
            min-height: 100vh;
            display: flex;
            justify-content: center;
        }

        /* Main Container */
        .report-container {
            width: 210mm;
            min-height: 297mm;
            margin: 0 auto;
            background: white;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
            position: relative;
            animation: slideInUp 0.5s ease-out;
        }

        @keyframes slideInUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Header with Logo */
        .header-with-logo {
            background: linear-gradient(135deg, #1a2e3f 0%, #234c6a 100%);
            padding: 20px 40px;
            width: 100%;
            display: flex;
            align-items: center;
            position: relative;
            overflow: hidden;
            color: white;
        }

        .header-with-logo::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255, 255, 255, 0.1) 0%, transparent 70%);
            pointer-events: none;
        }

        .logo-section img {
            width: 80px;
            height: auto;
            position: relative;
            z-index: 1;
            transition: transform 0.3s ease;
        }

        .report-title {
            font-size: 32px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 4px;
            margin: 0;
            font-family: "Oswald", sans-serif;
            text-align: center;
            flex-grow: 1;
        }

        .company-name {
            font-size: 14px;
            opacity: 0.9;
            margin-top: 5px;
            letter-spacing: 1px;
            text-align: center;
        }

        /* Info Section */
        .info-section {
            padding: 0;
            background: white;
        }

        .info-table-grid {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        .info-table-grid td {
            border: 1px solid #dee2e6;
            padding: 8px 9px;
            font-size: 13px;
            vertical-align: middle;
        }

        .info-label {
            background: #fcfcfc;
            font-weight: 700;
            color: #234c6a;
            text-transform: uppercase;
            width: 15%;
            letter-spacing: 0.5px;
        }

        .info-value {
            color: #1a2e3f;
            font-weight: 500;
            width: 35%;
        }

        /* Items Table */
        .table-wrapper {
            padding: 10px 0;
        }

        .items-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            font-size: 13px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        }

        .items-table thead tr {
            background: linear-gradient(135deg, #1a2e3f 0%, #234c6a 100%);
        }

        .items-table thead th {
            color: white;
            padding: 15px 12px;
            text-align: left;
            font-weight: 700;
            text-transform: uppercase;
            font-size: 12px;
            letter-spacing: 0.5px;
            border: none;
        }

        .items-table td {
            padding: 8px 12px;
            border-bottom: 1px solid #e9ecef;
        }

        .quantity-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: #1a2e3f;
            color: white;
            width: 28px;
            height: 28px;
            border-radius: 50%;
            font-weight: 700;
            font-size: 12px;
        }

        .serial-number-text {
            color: #d63384;
            font-weight: 600;
            font-family: 'Courier New', Courier, monospace;
        }

        .digital-signature {
            height: 60px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 5px;
        }

        .digital-signature img {
            max-height: 100%;
            max-width: 160px;
            filter: grayscale(1) contrast(2.5) brightness(0.8);
            mix-blend-mode: multiply;
        }

        /* Signature Section */
        .signature-section {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 30px;
            padding: 25px 40px;
            background: #fafafa;
            border-top: 1px solid #eee;
        }

        .signature-card {
            text-align: center;
        }

        .sig-line {
            border-top: 1px solid #333;
            margin-top: 30px;
            padding-top: 5px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
        }

        .sig-name {
            font-size: 14px;
            font-weight: 800;
            margin-top: 5px;
        }

        /* Action Buttons */
        .action-buttons {
            position: fixed;
            bottom: 30px;
            right: 30px;
            display: flex;
            gap: 12px;
        }

        .btn-action {
            padding: 12px 24px;
            border-radius: 50px;
            cursor: pointer;
            font-weight: 600;
            border: none;
            display: flex;
            align-items: center;
            gap: 8px;
            color: white;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
        }

        .btn-download { background: #28a745; }
        .btn-print { background: #234c6a; }
        .btn-back { background: #6c757d; }
    </style>
</head>

<body>
    <div id="report-to-export" class="report-container">
        <!-- We use a hidden table structure to facilitate repeating headers on print -->
        <table class="print-table">
            <thead class="print-header">
                <tr>
                    <td>
                        <!-- Premium Header content -->
                        <div class="header-with-logo">
                            <div class="logo-section">
                                <img src="assets/images/RE_logo.png" alt="Company Logo">
                            </div>
                             <div class="title-section" style="flex-grow: 1;">
                                <div class="report-title">MATERIAL REQUISITION</div>
                                <div class="company-name">AV Solutions | Professional Audio Visual Equipment</div>
                             </div>
                        </div>

                        <!-- Info Section content -->
                        <div class="info-section">
                            <table class="info-table-grid">
                                <tr>
                                    <td class="info-label">LOCATION</td>
                                    <td class="info-value"><?php echo htmlspecialchars($batch['source_name'] ?? 'Main Warehouse'); ?></td>
                                    <td class="info-label">DATE</td>
                                    <td class="info-value"><?php echo date('d/m/Y', strtotime($batch['created_at'])); ?></td>
                                </tr>
                                <tr>
                                    <td class="info-label">EVENT NAME</td>
                                    <td class="info-value"><?php echo htmlspecialchars($batch['event_name'] ?? 'N/A'); ?></td>
                                    <td class="info-label">PM</td>
                                    <td class="info-value"><?php echo htmlspecialchars($batch['project_manager'] ?? 'N/A'); ?></td>
                                </tr>
                                <tr>
                                    <td class="info-label">STOCK CONTROLLER</td>
                                    <td class="info-value"><?php echo htmlspecialchars($stock_controller); ?></td>
                                    <td class="info-label">VEHICLE NO</td>
                                    <td class="info-value"><?php echo $vehicle_display; ?></td>
                                </tr>
                                <tr>
                                    <td class="info-label">DESTINATION</td>
                                    <td class="info-value" colspan="3">
                                        <?php 
                                        $dest = [];
                                        if ($batch['destination_name']) $dest[] = $batch['destination_name'];
                                        if ($batch['destination_room']) $dest[] = $batch['destination_room'];
                                        echo htmlspecialchars(implode(' - ', $dest) ?: 'N/A');
                                        ?>
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </td>
                </tr>
            </thead>

            <tbody class="print-content">
                <tr>
                    <td>
                        <!-- Items content -->
                        <div class="table-wrapper">
                            <table class="items-table">
                                <thead>
                                    <tr>
                                        <th style="width: 5%">#</th>
                                        <th style="width: 65%">ITEMS</th>
                                        <th style="width: 30%">SERIAL NO</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $totalQuantity = 0;
                                    if (!empty($items)):
                                        foreach ($items as $index => $item):
                                            $totalQuantity += $item['quantity'];
                                    ?>
                                             <tr>
                                                <td><?php echo $index + 1; ?></td>
                                                <td><strong><?php echo htmlspecialchars($item['item_name']); ?></strong></td>
                                                <td><span class="serial-number-text"><?php echo htmlspecialchars($item['serial_number'] ?: '—'); ?></span></td>
                                             </tr>
                                        <?php endforeach; ?>
                                         <tr style="background: #f8f9fa; font-weight: 800;">
                                             <td colspan="2" style="text-align: right; padding-right: 20px; text-transform: uppercase; letter-spacing: 1px;">TOTAL QUANTITY:</td>
                                             <td style="text-align: left; padding-left: 20px; font-size: 16px;"><?php echo $totalQuantity; ?></td>
                                         </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Signature Section content -->
                        <div class="signature-section">
                            <div class="signature-card">
                                <?php if ($tech_sig_path): ?>
                                    <div class="digital-signature">
                                        <img src="<?php echo htmlspecialchars($tech_sig_path); ?>" alt="Technician Signature">
                                    </div>
                                <?php endif; ?>
                                <div class="sig-name"><?php echo htmlspecialchars($technician_name); ?></div>
                                <div class="sig-line">Requested By (Technician)</div>
                            </div>
                            <div class="signature-card">
                                <?php if ($approver_sig_path): ?>
                                    <div class="digital-signature">
                                        <img src="<?php echo htmlspecialchars($approver_sig_path); ?>" alt="Approver Signature">
                                    </div>
                                <?php endif; ?>
                                <div class="sig-name"><?php echo htmlspecialchars($stock_controller); ?></div>
                                <div class="sig-line">Authorized By (Stock Controller)</div>
                            </div>
                        </div>
                    </td>
                </tr>
            </tbody>

            <tfoot class="print-footer">
                <tr>
                    <td>
                        <div style="padding: 15px; font-size: 10px; text-align: center; border-top: 1px solid #eee; color: #777;">
                            Batch ID: <?php echo htmlspecialchars($batch_id); ?> | Generated: <?php echo date('d/m/Y H:i'); ?> | This is a case study <span class="badge bg-danger" style="font-size: 8px;">Stock Inventory System</span> conducted by Kayonga Raul
                        </div>
                    </td>
                </tr>
            </tfoot>
        </table>
    </div>

    <!-- Action Buttons -->
    <div class="action-buttons no-print">
        <button class="btn-action btn-back" onclick="window.location.href='batch_history.php'">
            <i class="fas fa-arrow-left"></i> Back
        </button>
        <button class="btn-action btn-download" onclick="downloadPDF()">
            <i class="fas fa-file-pdf"></i> Download PDF
        </button>
        <button class="btn-action btn-print" onclick="window.print()">
            <i class="fas fa-print"></i> Print Report
        </button>
    </div>

    <script>
        function downloadPDF() {
            const element = document.getElementById('report-to-export');
            const options = {
                margin: [10, 0, 10, 0], // Top, Left, Bottom, Right margins
                filename: 'Material_Requisition_<?php echo $batch_id; ?>.pdf',
                image: { type: 'jpeg', quality: 1.0 },
                html2canvas: { 
                    scale: 4, 
                    useCORS: true, 
                    letterRendering: true,
                    dpi: 300,
                    backgroundColor: '#ffffff'
                },
                jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait', compress: true },
                pagebreak: { mode: ['avoid-all', 'css', 'legacy'] }
            };
            html2pdf().set(options).from(element).save();
        }

        if (window.location.search.includes('download=1')) {
            window.onload = function() {
                // Wait for all assets to load including signatures
                setTimeout(() => {
                    downloadPDF();
                }, 1500);
            };
        }

        if (window.location.search.includes('print=1')) {
            window.onload = function() {
                setTimeout(() => window.print(), 500);
            };
        }
    </script>
</body>

</html>