<?php
// access_qr.php - Generate QR code for mobile access
require_once 'bootstrap.php';

// Check authentication
if (!isLoggedIn()) {
    header('Location: login.php');
    exit();
}

// Get local server IP
$server_ip = $_SERVER['SERVER_ADDR'] ?? '192.168.1.100'; // This will be your local IP
$server_port = $_SERVER['SERVER_PORT'] == '80' ? '' : ':' . $_SERVER['SERVER_PORT'];
$scan_url = "http://{$server_ip}{$server_port}" . rtrim(dirname($_SERVER['PHP_SELF']), '/\\') . '/scan.php';

$pageTitle = "Mobile Access QR - aBility";
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .qr-card {
            background: white;
            border-radius: 20px;
            padding: 2rem;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            max-width: 400px;
            width: 90%;
            margin: 0 auto;
        }

        .qr-title {
            color: #333;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .qr-subtitle {
            color: #666;
            font-size: 0.9rem;
            margin-bottom: 1.5rem;
        }

        #qrcode {
            display: flex;
            justify-content: center;
            margin: 1.5rem 0;
            padding: 1rem;
            background: #f8f9fa;
            border-radius: 10px;
        }

        .url-box {
            background: #f8f9fa;
            padding: 0.75rem;
            border-radius: 8px;
            font-size: 0.8rem;
            word-break: break-all;
            margin: 1rem 0;
            border: 1px dashed #dee2e6;
        }

        .ip-info {
            background: #e3f2fd;
            padding: 0.75rem;
            border-radius: 8px;
            font-size: 0.85rem;
            margin: 1rem 0;
        }

        .btn-scan {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 0.75rem 2rem;
            border-radius: 50px;
            font-weight: 500;
            width: 100%;
            margin-top: 1rem;
            text-decoration: none;
            display: inline-block;
            text-align: center;
        }

        .btn-scan:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
            color: white;
        }

        .steps {
            margin-top: 1.5rem;
            text-align: left;
            border-top: 1px solid #dee2e6;
            padding-top: 1.5rem;
        }

        .step-item {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 1rem;
        }

        .step-number {
            width: 25px;
            height: 25px;
            background: #667eea;
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.8rem;
            font-weight: bold;
        }
    </style>
</head>

<body>
    <div class="qr-card">
        <h3 class="qr-title text-center">
            <i class="fas fa-qrcode me-2"></i>Mobile Access QR
        </h3>
        <p class="qr-subtitle text-center">
            Scan this QR code with your mobile device to access the scanner
        </p>

        <div id="qrcode"></div>

        <div class="url-box">
            <strong>URL:</strong><br>
            <?php echo $scan_url; ?>
        </div>

        <div class="ip-info">
            <i class="fas fa-info-circle me-2 text-primary"></i>
            <strong>Make sure your mobile is on the same WiFi network</strong>
        </div>

        <a href="scan.php" class="btn-scan">
            <i class="fas fa-arrow-right me-2"></i>Open Scanner on This Device
        </a>

        <div class="steps">
            <h6><i class="fas fa-list-ol me-2"></i>Steps to use:</h6>
            <div class="step-item">
                <span class="step-number">1</span>
                <span>Open camera on your mobile phone</span>
            </div>
            <div class="step-item">
                <span class="step-number">2</span>
                <span>Scan the QR code above</span>
            </div>
            <div class="step-item">
                <span class="step-number">3</span>
                <span>Tap the link that appears</span>
            </div>
            <div class="step-item">
                <span class="step-number">4</span>
                <span>Start scanning equipment QR codes</span>
            </div>
        </div>
    </div>

    <script>
        // Generate QR code
        new QRCode(document.getElementById("qrcode"), {
            text: "<?php echo $scan_url; ?>",
            width: 250,
            height: 250,
            colorDark: "#667eea",
            colorLight: "#ffffff",
            correctLevel: QRCode.CorrectLevel.H
        });
    </script>
</body>

</html>