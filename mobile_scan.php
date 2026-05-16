<?php
// scan.php - Mobile-friendly QR Code Scanner using Instascan
$current_page = basename(__FILE__);
require_once 'bootstrap.php';

// Check authentication
if (!isLoggedIn()) {
    header('Location: login.php');
    exit();
}

require_once 'includes/functions.php';
require_once 'includes/db_connect.php';

// Get database connection
$conn = getConnection();

// Get user's role for the header
$user_role = getUserRole();

// Get local server IP for QR code
$server_ip = $_SERVER['SERVER_ADDR'] ?? '172.20.43.13';
$server_port = $_SERVER['SERVER_PORT'] == '80' ? '' : ':' . $_SERVER['SERVER_PORT'];
$base_url = "http://{$server_ip}{$server_port}" . rtrim(dirname($_SERVER['PHP_SELF']), '/\\') . '/';

$pageTitle = "Scan QR Code - aBility";
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes, viewport-fit=cover">
    <title><?php echo $pageTitle; ?></title>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- Instascan QR Scanner (working CDN) -->
    <script src="https://cdn.jsdelivr.net/npm/instascan@1.0.0/dist/instascan.min.js"></script>

    <!-- QRCode Library for generating page QR -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background-color: #f5f7fa;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
            padding-bottom: 70px;
        }

        .mobile-header {
            background: linear-gradient(135deg, #1a2e3f 0%, #234c6a 100%);
            padding: 0.75rem 1rem;
            position: sticky;
            top: 0;
            z-index: 1000;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .mobile-header-content {
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .mobile-header-title {
            color: white;
            font-weight: 600;
            font-size: 1.1rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .mobile-header-title i {
            font-size: 1.2rem;
        }

        .mobile-header-actions {
            display: flex;
            gap: 10px;
        }

        .mobile-header-actions a {
            color: rgba(255, 255, 255, 0.9);
            background: rgba(255, 255, 255, 0.1);
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            transition: all 0.2s ease;
        }

        .mobile-header-actions a:active {
            background: rgba(255, 255, 255, 0.2);
            transform: scale(0.95);
        }

        .mobile-content {
            padding: 1rem;
        }

        .scanner-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            overflow: hidden;
            margin-bottom: 1rem;
        }

        .scanner-header {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            padding: 1rem;
            border-bottom: 1px solid #dee2e6;
        }

        .scanner-header h2 {
            font-size: 1.2rem;
            font-weight: 600;
            margin: 0;
            color: #234c6a;
        }

        .scanner-header p {
            font-size: 0.85rem;
            color: #6c757d;
            margin: 4px 0 0 0;
        }

        #scanner-container {
            width: 100%;
            min-height: 300px;
            background: #f8f9fa;
            overflow: hidden;
        }

        #scanner-container video {
            width: 100% !important;
            height: auto !important;
            max-height: 400px;
            object-fit: cover;
        }

        .scanner-controls {
            display: flex;
            padding: 0.75rem;
            gap: 10px;
            background: white;
            border-top: 1px solid #dee2e6;
        }

        .scanner-controls .btn {
            flex: 1;
            border-radius: 30px;
            padding: 0.6rem;
            font-size: 0.9rem;
            font-weight: 500;
        }

        .manual-card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            padding: 1.25rem;
            margin-bottom: 1rem;
        }

        .manual-card h3 {
            font-size: 1rem;
            font-weight: 600;
            color: #234c6a;
            margin-bottom: 1rem;
        }

        .manual-card .input-group {
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        }

        .manual-card .form-control {
            border: 1px solid #dee2e6;
            padding: 0.75rem;
            font-size: 1rem;
        }

        .manual-card .btn-primary {
            background: #234c6a;
            border: none;
            padding: 0.75rem 1.5rem;
            font-weight: 500;
        }

        .result-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            margin-bottom: 1rem;
            animation: slideUp 0.3s ease;
        }

        @keyframes slideUp {
            from {
                transform: translateY(20px);
                opacity: 0;
            }

            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .result-header {
            background: #234c6a;
            color: white;
            padding: 1rem;
        }

        .result-header h4 {
            font-size: 1.1rem;
            font-weight: 600;
            margin: 0;
        }

        .result-body {
            padding: 1.25rem;
        }

        .result-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem 0;
            border-bottom: 1px solid #f0f0f0;
        }

        .result-item:last-child {
            border-bottom: none;
        }

        .result-label {
            color: #6c757d;
            font-size: 0.9rem;
        }

        .result-value {
            font-weight: 600;
            color: #234c6a;
            font-size: 1rem;
        }

        .status-badge {
            padding: 4px 12px;
            border-radius: 30px;
            font-size: 0.85rem;
            font-weight: 500;
        }

        .badge-available {
            background: #d4edda;
            color: #155724;
        }

        .badge-in_use {
            background: #cce5ff;
            color: #004085;
        }

        .badge-maintenance {
            background: #fff3cd;
            color: #856404;
        }

        .badge-disposed,
        .badge-lost {
            background: #f8d7da;
            color: #721c24;
        }

        .result-actions {
            display: flex;
            gap: 10px;
            margin-top: 1rem;
        }

        .result-actions .btn {
            flex: 1;
            border-radius: 30px;
            padding: 0.75rem;
            font-weight: 500;
        }

        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            backdrop-filter: blur(3px);
        }

        .loading-spinner {
            background: white;
            padding: 2rem;
            border-radius: 20px;
            text-align: center;
            max-width: 80%;
        }

        .loading-spinner i {
            font-size: 3rem;
            color: #234c6a;
            margin-bottom: 1rem;
        }

        .loading-spinner p {
            font-size: 1rem;
            color: #6c757d;
            margin: 0;
        }

        .bottom-nav {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: white;
            display: flex;
            justify-content: space-around;
            padding: 0.75rem 0.5rem;
            box-shadow: 0 -2px 10px rgba(0, 0, 0, 0.05);
            z-index: 1000;
            border-top: 1px solid #f0f0f0;
        }

        .nav-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            text-decoration: none;
            color: #6c757d;
            font-size: 0.7rem;
            transition: all 0.2s ease;
            padding: 0.25rem 0.75rem;
            border-radius: 30px;
        }

        .nav-item i {
            font-size: 1.3rem;
            margin-bottom: 2px;
        }

        .nav-item.active {
            color: #234c6a;
            background: #e9ecef;
        }

        .nav-item span {
            font-size: 0.7rem;
            font-weight: 500;
        }

        .mobile-toast {
            position: fixed;
            top: 20px;
            left: 50%;
            transform: translateX(-50%);
            background: #234c6a;
            color: white;
            padding: 0.75rem 1.5rem;
            border-radius: 50px;
            font-size: 0.9rem;
            font-weight: 500;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            z-index: 9999;
            animation: slideDown 0.3s ease;
            max-width: 90%;
            text-align: center;
        }

        .mobile-toast.error {
            background: #dc3545;
        }

        .mobile-toast.success {
            background: #28a745;
        }

        .mobile-toast.warning {
            background: #ffc107;
            color: #333;
        }

        @keyframes slideDown {
            from {
                transform: translate(-50%, -100%);
                opacity: 0;
            }

            to {
                transform: translate(-50%, 0);
                opacity: 1;
            }
        }

        .access-qr-card {
            background: linear-gradient(135deg, #1a2e3f 0%, #234c6a 100%);
            color: white;
            border-radius: 16px;
            padding: 1rem;
            margin-bottom: 1rem;
        }

        .access-qr-card .qr-container {
            background: white;
            padding: 1rem;
            border-radius: 12px;
            display: inline-block;
            margin: 0 auto 1rem;
        }

        .access-qr-card img {
            width: 150px;
            height: 150px;
        }

        .access-qr-card .url-text {
            background: rgba(255, 255, 255, 0.1);
            padding: 0.5rem;
            border-radius: 8px;
            font-size: 0.8rem;
            word-break: break-all;
        }

        @media (min-width: 768px) {
            .mobile-content {
                max-width: 500px;
                margin: 0 auto;
            }
        }
    </style>
</head>

<body>
    <!-- Mobile Header -->
    <div class="mobile-header">
        <div class="mobile-header-content">
            <div class="mobile-header-title">
                <i class="fas fa-qrcode"></i>
                <span>QR Scanner</span>
            </div>
            <div class="mobile-header-actions">
                <a href="dashboard_full.php">
                    <i class="fas fa-home"></i>
                </a>
                <a href="#" onclick="showLogoutConfirm()">
                    <i class="fas fa-sign-out-alt"></i>
                </a>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="mobile-content">
        <!-- Access QR Code -->
        <div class="access-qr-card text-center">
            <h5 style="font-size: 1rem; margin-bottom: 1rem;">
                <i class="fas fa-mobile-alt me-2"></i>Scan to access on mobile
            </h5>
            <div class="qr-container">
                <div id="pageQrCode"></div>
            </div>
            <div class="url-text mt-2">
                <small><?php echo $base_url . 'scan.php'; ?></small>
            </div>
            <p class="mt-2 small" style="opacity: 0.9;">
                Make sure your mobile is on the same WiFi network
            </p>
        </div>

        <!-- Scanner Card -->
        <div class="scanner-card">
            <div class="scanner-header">
                <h2><i class="fas fa-camera me-2"></i>Scan Equipment QR Code</h2>
                <p>Position QR code within the frame to scan</p>
            </div>
            <div id="scanner-container"></div>
            <div class="scanner-controls">
                <button class="btn btn-outline-primary" id="startScannerBtn" onclick="startScanner()">
                    <i class="fas fa-play me-2"></i>Start
                </button>
                <button class="btn btn-outline-secondary" id="stopScannerBtn" onclick="stopScanner()" disabled>
                    <i class="fas fa-stop me-2"></i>Stop
                </button>
            </div>
        </div>

        <!-- Manual Entry Card -->
        <div class="manual-card">
            <h3><i class="fas fa-keyboard me-2"></i>Manual Entry</h3>
            <div class="input-group">
                <input type="text" class="form-control" id="manualItemId" placeholder="Enter Item ID">
                <button class="btn btn-primary" type="button" onclick="lookupItem()">
                    <i class="fas fa-search"></i>
                </button>
            </div>
        </div>

        <!-- Result Container -->
        <div id="resultContainer" style="display: none;"></div>

        <!-- Recent Scans -->
        <div id="recentScansContainer" class="mt-3">
            <h6 class="fw-bold mb-2" style="color: #234c6a;">
                <i class="fas fa-history me-2"></i>Recent Scans
            </h6>
            <div id="recentScans" class="bg-white rounded-3 p-2">
                <p class="text-muted text-center py-3 mb-0">No recent scans</p>
            </div>
        </div>
    </div>

    <!-- Bottom Navigation -->
    <div class="bottom-nav">
        <a href="dashboard_full.php" class="nav-item">
            <i class="fas fa-home"></i>
            <span>Home</span>
        </a>
        <a href="items.php" class="nav-item">
            <i class="fas fa-boxes"></i>
            <span>Items</span>
        </a>
        <a href="scan.php" class="nav-item active">
            <i class="fas fa-qrcode"></i>
            <span>Scan</span>
        </a>
        <a href="#" class="nav-item" onclick="showLogoutConfirm()">
            <i class="fas fa-sign-out-alt"></i>
            <span>Logout</span>
        </a>
    </div>

    <!-- Logout Confirmation -->
    <div id="logoutOverlay" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 10000;" onclick="hideLogoutConfirm()">
        <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; border-radius: 20px; padding: 1.5rem; width: 80%; max-width: 300px; text-align: center;" onclick="event.stopPropagation()">
            <i class="fas fa-sign-out-alt fa-3x text-warning mb-3"></i>
            <h5 class="mb-2">Confirm Logout</h5>
            <p class="text-muted mb-3">Are you sure you want to logout?</p>
            <div class="d-flex gap-2">
                <button class="btn btn-secondary flex-fill" onclick="hideLogoutConfirm()">Cancel</button>
                <a href="logout.php" class="btn btn-primary flex-fill">Logout</a>
            </div>
        </div>
    </div>

    <script>
        // ========== GLOBAL VARIABLES ==========
        let scanner = null;
        let scannerRunning = false;
        let recentScans = JSON.parse(localStorage.getItem('recentScans') || '[]');
        const BASE_URL = '<?php echo $base_url; ?>';

        // ========== INITIALIZATION ==========
        document.addEventListener('DOMContentLoaded', function() {
            generatePageQRCode();
            displayRecentScans();

            // Check for item ID in URL
            const urlParams = new URLSearchParams(window.location.search);
            const itemId = urlParams.get('id');
            if (itemId) {
                lookupItemById(itemId);
            }

            // Check if Instascan is loaded
            setTimeout(function() {
                if (typeof Instascan === 'undefined') {
                    console.error('Instascan not loaded');
                    document.getElementById('scanner-container').innerHTML = `
                        <div class="alert alert-warning m-3">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            Scanner library not loaded. 
                            <button class="btn btn-sm btn-primary mt-2" onclick="location.reload()">
                                <i class="fas fa-sync-alt me-1"></i> Reload Page
                            </button>
                        </div>
                    `;
                } else {
                    console.log('Instascan loaded successfully');
                }
            }, 1000);
        });

        // ========== QR CODE GENERATION ==========
        function generatePageQRCode() {
            if (typeof QRCode !== 'undefined') {
                const pageUrl = window.location.href.split('?')[0];
                new QRCode(document.getElementById("pageQrCode"), {
                    text: pageUrl,
                    width: 150,
                    height: 150,
                    colorDark: "#234c6a",
                    colorLight: "#ffffff",
                    correctLevel: QRCode.CorrectLevel.H
                });
            } else {
                document.getElementById('pageQrCode').innerHTML = 'QR library not loaded';
            }
        }

        // ========== SCANNER FUNCTIONS ==========
        function startScanner() {
            if (scannerRunning || typeof Instascan === 'undefined') return;

            scanner = new Instascan.Scanner({
                video: document.getElementById('scanner-container'),
                scanPeriod: 5,
                mirror: false
            });

            scanner.addListener('scan', function(content) {
                console.log('Scanned:', content);
                handleScan(content);
                stopScanner();
            });

            Instascan.Camera.getCameras().then(function(cameras) {
                if (cameras.length > 0) {
                    // Use back camera (usually last in list)
                    const backCamera = cameras[cameras.length - 1];
                    scanner.start(backCamera).then(() => {
                        scannerRunning = true;
                        document.getElementById('startScannerBtn').disabled = true;
                        document.getElementById('stopScannerBtn').disabled = false;
                        showToast('Scanner started', 'success');
                    }).catch(err => {
                        console.error('Failed to start scanner:', err);
                        showToast('Failed to start camera', 'error');
                    });
                } else {
                    showToast('No cameras found', 'error');
                }
            }).catch(function(err) {
                console.error('Camera access error:', err);
                showToast('Camera access denied', 'error');
            });
        }

        function stopScanner() {
            if (scanner && scannerRunning) {
                scanner.stop().then(() => {
                    scannerRunning = false;
                    document.getElementById('startScannerBtn').disabled = false;
                    document.getElementById('stopScannerBtn').disabled = true;
                    showToast('Scanner stopped', 'info');
                }).catch(err => {
                    console.error('Failed to stop scanner:', err);
                });
            }
        }

        // ========== SCAN HANDLING ==========
        function handleScan(decodedText) {
            let itemId = null;

            // Check if it's a URL containing id parameter
            if (decodedText.includes('id=')) {
                const match = decodedText.match(/[?&]id=(\d+)/);
                if (match) itemId = match[1];
            }
            // Check if it's just a number
            else if (/^\d+$/.test(decodedText)) {
                itemId = decodedText;
            }

            if (itemId) {
                lookupItemById(itemId);
            } else {
                showToast('Invalid QR code format', 'error');
            }
        }

        function lookupItem() {
            const input = document.getElementById('manualItemId').value.trim();
            if (!input) {
                showToast('Please enter an Item ID', 'warning');
                return;
            }
            lookupItemById(input);
        }

        function lookupItemById(itemId) {
            showLoading('Loading item details...');

            fetch(`api/get_item.php?id=${itemId}`)
                .then(response => response.json())
                .then(data => {
                    hideLoading();
                    if (data.success && data.data) {
                        displayItemResult(data.data);
                        addToRecentScans(data.data);
                    } else {
                        showToast('Item not found', 'error');
                        document.getElementById('resultContainer').style.display = 'none';
                    }
                })
                .catch(error => {
                    hideLoading();
                    console.error('Error:', error);
                    showToast('Failed to load item', 'error');
                });
        }

        // ========== DISPLAY FUNCTIONS ==========
        function displayItemResult(item) {
            const container = document.getElementById('resultContainer');
            const statusClass = getStatusClass(item.status);

            const html = `
            <div class="result-card">
                <div class="result-header">
                    <h4><i class="fas fa-box me-2"></i>${escapeHtml(item.item_name || 'Item Details')}</h4>
                </div>
                <div class="result-body">
                    <div class="result-item">
                        <span class="result-label">Item ID</span>
                        <span class="result-value">#${item.id || 'N/A'}</span>
                    </div>
                    <div class="result-item">
                        <span class="result-label">Serial Number</span>
                        <span class="result-value">${escapeHtml(item.serial_number || 'N/A')}</span>
                    </div>
                    <div class="result-item">
                        <span class="result-label">Category</span>
                        <span class="result-value">${escapeHtml(item.category || 'N/A')}</span>
                    </div>
                    <div class="result-item">
                        <span class="result-label">Status</span>
                        <span class="result-value">
                            <span class="status-badge ${statusClass}">${escapeHtml(item.status || 'Unknown')}</span>
                        </span>
                    </div>
                    <div class="result-item">
                        <span class="result-label">Location</span>
                        <span class="result-value">${escapeHtml(item.stock_location || 'N/A')}</span>
                    </div>
                    <div class="result-item">
                        <span class="result-label">Quantity</span>
                        <span class="result-value">${item.quantity || 1}</span>
                    </div>
                </div>
                <div class="p-3 bg-light">
                    <div class="result-actions">
                        <a href="items.php?action=view&id=${item.id}" class="btn btn-outline-primary">
                            <i class="fas fa-eye me-2"></i>View Details
                        </a>
                        <button class="btn btn-primary" onclick="scanAgain()">
                            <i class="fas fa-qrcode me-2"></i>Scan Again
                        </button>
                    </div>
                </div>
            </div>
        `;

            container.innerHTML = html;
            container.style.display = 'block';
            document.getElementById('recentScansContainer').style.display = 'none';
        }

        function getStatusClass(status) {
            status = (status || '').toLowerCase();
            switch (status) {
                case 'available':
                    return 'badge-available';
                case 'in_use':
                    return 'badge-in_use';
                case 'maintenance':
                    return 'badge-maintenance';
                case 'disposed':
                case 'lost':
                    return 'badge-disposed';
                default:
                    return 'badge-available';
            }
        }

        function scanAgain() {
            document.getElementById('resultContainer').style.display = 'none';
            document.getElementById('recentScansContainer').style.display = 'block';
            startScanner();
        }

        // ========== RECENT SCANS ==========
        function addToRecentScans(item) {
            recentScans.unshift({
                id: item.id,
                name: item.item_name,
                serial: item.serial_number,
                timestamp: new Date().toISOString()
            });

            if (recentScans.length > 5) {
                recentScans = recentScans.slice(0, 5);
            }

            localStorage.setItem('recentScans', JSON.stringify(recentScans));
            displayRecentScans();
        }

        function displayRecentScans() {
            const container = document.getElementById('recentScans');

            if (!recentScans || recentScans.length === 0) {
                container.innerHTML = '<p class="text-muted text-center py-3 mb-0">No recent scans</p>';
                return;
            }

            let html = '';
            recentScans.forEach(scan => {
                const time = new Date(scan.timestamp).toLocaleTimeString();
                html += `
                <div class="d-flex align-items-center justify-content-between p-2 border-bottom">
                    <div>
                        <strong>${escapeHtml(scan.name)}</strong>
                        <br>
                        <small class="text-muted">#${scan.id}</small>
                    </div>
                    <button class="btn btn-sm btn-outline-primary" onclick="lookupItemById(${scan.id})">
                        <i class="fas fa-redo"></i>
                    </button>
                </div>
            `;
            });

            container.innerHTML = html;
        }

        // ========== UTILITY FUNCTIONS ==========
        function showToast(message, type = 'info') {
            const toast = document.createElement('div');
            toast.className = `mobile-toast ${type}`;
            toast.textContent = message;
            document.body.appendChild(toast);
            setTimeout(() => toast.remove(), 3000);
        }

        function showLoading(message = 'Loading...') {
            const overlay = document.createElement('div');
            overlay.className = 'loading-overlay';
            overlay.id = 'loadingOverlay';
            overlay.innerHTML = `
            <div class="loading-spinner">
                <i class="fas fa-circle-notch fa-spin"></i>
                <p>${message}</p>
            </div>`;
            document.body.appendChild(overlay);
        }

        function hideLoading() {
            const overlay = document.getElementById('loadingOverlay');
            if (overlay) overlay.remove();
        }

        function showLogoutConfirm() {
            document.getElementById('logoutOverlay').style.display = 'block';
        }

        function hideLogoutConfirm() {
            document.getElementById('logoutOverlay').style.display = 'none';
        }

        function escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    </script>
</body>

</html>