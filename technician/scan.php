<?php
require_once 'includes/auth.php';
requireTechnicianLogin();
require_once '../includes/config.php';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Scan Equipment - Technician Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #4361ee;
            --secondary-color: #3a0ca3;
            --success-color: #4CAF50;
            --warning-color: #FF9800;
            --danger-color: #F44336;
        }

        body {
            background: #f5f7fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .navbar-custom {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .scan-container {
            max-width: 800px;
            margin: 0 auto;
        }

        .scan-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
            overflow: hidden;
        }

        .scan-header {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 30px;
            text-align: center;
        }

        .scan-body {
            padding: 30px;
        }

        .scanner-area {
            border: 3px dashed #ddd;
            border-radius: 10px;
            padding: 40px;
            text-align: center;
            background: #f9f9f9;
            cursor: pointer;
            transition: all 0.3s;
            margin-bottom: 30px;
        }

        .scanner-area:hover {
            border-color: var(--primary-color);
            background: #f0f4ff;
        }

        .scanner-area.active {
            border-color: var(--success-color);
            background: #f0fff4;
        }

        .scanner-icon {
            font-size: 3rem;
            color: var(--primary-color);
            margin-bottom: 15px;
        }

        .item-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 15px;
            border-left: 4px solid var(--primary-color);
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
            display: none;
        }

        .item-card.show {
            display: block;
            animation: slideIn 0.3s ease;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .status-badge {
            padding: 5px 15px;
            border-radius: 20px;
            font-weight: 600;
        }

        .status-available {
            background: #e8f5e9;
            color: var(--success-color);
        }

        .status-in_use {
            background: #e3f2fd;
            color: #2196F3;
        }

        .status-maintenance {
            background: #fff3e0;
            color: var(--warning-color);
        }

        .status-disposed {
            background: #ffebee;
            color: var(--danger-color);
        }

        .action-btn {
            padding: 8px 20px;
            border-radius: 8px;
            border: none;
            font-weight: 600;
            transition: all 0.3s;
        }

        .action-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        .btn-check-in {
            background: var(--success-color);
            color: white;
        }

        .btn-check-out {
            background: var(--primary-color);
            color: white;
        }

        .btn-maintenance {
            background: var(--warning-color);
            color: white;
        }

        .recent-scans {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }

        .scan-item {
            display: flex;
            align-items: center;
            padding: 10px;
            border-bottom: 1px solid #eee;
        }

        .scan-item:last-child {
            border-bottom: none;
        }

        .qr-scanner-container {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.9);
            z-index: 9999;
            justify-content: center;
            align-items: center;
        }

        .qr-scanner {
            width: 90%;
            max-width: 500px;
            background: white;
            border-radius: 15px;
            overflow: hidden;
        }

        .scanner-video {
            width: 100%;
            height: 300px;
            background: #000;
        }
    </style>
</head>

<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-custom navbar-dark">
        <div class="container">
            <a class="navbar-brand" href="#">
                <i class="fas fa-tools me-2"></i>
                Technician Portal
            </a>
            <div class="d-flex align-items-center">
                <div class="me-3 text-white">
                    <i class="fas fa-user-circle me-1"></i>
                    <?php echo htmlspecialchars(getTechnicianName()); ?>
                </div>
                <a href="logout.php" class="btn btn-outline-light btn-sm">
                    <i class="fas fa-sign-out-alt me-1"></i> Logout
                </a>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="container mt-4">
        <div class="scan-container">
            <!-- Scanner Card -->
            <div class="scan-card mb-4">
                <div class="scan-header">
                    <h2 class="mb-3"><i class="fas fa-qrcode me-2"></i> Equipment Scanner</h2>
                    <p class="mb-0">Scan QR codes to view and manage equipment</p>
                </div>

                <div class="scan-body">
                    <!-- Scanner Area -->
                    <div class="scanner-area" id="scannerArea">
                        <div class="scanner-icon">
                            <i class="fas fa-camera"></i>
                        </div>
                        <h4>Click to Start Scanning</h4>
                        <p class="text-muted">Point your camera at an equipment QR code</p>
                        <button class="btn btn-primary" id="startScannerBtn">
                            <i class="fas fa-play me-2"></i> Start Scanner
                        </button>
                        <div class="mt-3">
                            <small class="text-muted">Or enter serial number manually:</small>
                            <div class="input-group mt-2" style="max-width: 300px; margin: 0 auto;">
                                <input type="text" class="form-control" id="manualSerial" placeholder="Enter serial number">
                                <button class="btn btn-outline-primary" id="manualLookup">
                                    <i class="fas fa-search"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Scanner Results -->
                    <div id="scanResults"></div>

                    <!-- Item Display Area -->
                    <div class="item-card" id="itemCard">
                        <div class="row">
                            <div class="col-md-8">
                                <h5 id="itemName">Equipment Name</h5>
                                <div class="mb-2">
                                    <span class="badge bg-secondary me-2" id="itemCategory">Category</span>
                                    <span class="badge" id="itemStatusBadge">Status</span>
                                </div>
                                <div class="row">
                                    <div class="col-6">
                                        <small class="text-muted d-block">Serial Number</small>
                                        <strong id="itemSerial">ABC-123</strong>
                                    </div>
                                    <div class="col-6">
                                        <small class="text-muted d-block">Location</small>
                                        <strong id="itemLocation">Storage A</strong>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4 text-end">
                                <div class="btn-group-vertical w-100">
                                    <button class="btn btn-check-in action-btn mb-2" id="checkInBtn">
                                        <i class="fas fa-sign-in-alt me-1"></i> Check In
                                    </button>
                                    <button class="btn btn-check-out action-btn mb-2" id="checkOutBtn">
                                        <i class="fas fa-sign-out-alt me-1"></i> Check Out
                                    </button>
                                    <button class="btn btn-maintenance action-btn" id="maintenanceBtn">
                                        <i class="fas fa-tools me-1"></i> Maintenance
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Scans -->
            <div class="recent-scans">
                <h5 class="mb-3"><i class="fas fa-history me-2"></i> Recent Scans</h5>
                <div id="recentScansList">
                    <div class="text-center text-muted py-4">
                        <i class="fas fa-qrcode fa-2x mb-3"></i>
                        <p>No recent scans</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- QR Scanner Modal -->
    <div class="qr-scanner-container" id="qrScannerContainer">
        <div class="qr-scanner">
            <div class="scanner-header bg-dark text-white p-3 d-flex justify-content-between">
                <h5 class="mb-0"><i class="fas fa-qrcode me-2"></i> QR Code Scanner</h5>
                <button class="btn btn-sm btn-outline-light" id="closeScanner">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <video id="scannerVideo" class="scanner-video"></video>
            <div class="scanner-footer p-3 bg-light">
                <div class="text-center">
                    <p class="mb-2">Position QR code within the frame</p>
                    <div class="btn-group">
                        <button class="btn btn-success" id="toggleCamera">
                            <i class="fas fa-camera-rotate me-1"></i> Switch Camera
                        </button>
                        <button class="btn btn-secondary" id="stopScanner">
                            <i class="fas fa-stop me-1"></i> Stop Scanner
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <!-- QR Scanner Library -->
    <script src="https://unpkg.com/html5-qrcode"></script>
    <script>
        // Global variables
        let scanner = null;
        let currentCameraId = null;
        let scannedItems = JSON.parse(localStorage.getItem('recentScans')) || [];

        // DOM Elements
        const scannerArea = document.getElementById('scannerArea');
        const qrScannerContainer = document.getElementById('qrScannerContainer');
        const scannerVideo = document.getElementById('scannerVideo');
        const itemCard = document.getElementById('itemCard');
        const recentScansList = document.getElementById('recentScansList');

        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            updateRecentScans();

            // Event Listeners
            document.getElementById('startScannerBtn').addEventListener('click', startScanner);
            document.getElementById('closeScanner').addEventListener('click', stopScanner);
            document.getElementById('stopScanner').addEventListener('click', stopScanner);
            document.getElementById('toggleCamera').addEventListener('click', toggleCamera);
            document.getElementById('manualLookup').addEventListener('click', manualLookup);
            document.getElementById('manualSerial').addEventListener('keypress', function(e) {
                if (e.key === 'Enter') manualLookup();
            });

            // Action buttons
            document.getElementById('checkInBtn').addEventListener('click', () => updateStatus('available'));
            document.getElementById('checkOutBtn').addEventListener('click', () => updateStatus('in_use'));
            document.getElementById('maintenanceBtn').addEventListener('click', () => updateStatus('maintenance'));
        });

        // Start QR Scanner
        function startScanner() {
            scannerArea.classList.add('active');
            qrScannerContainer.style.display = 'flex';

            // Initialize scanner
            scanner = new Html5Qrcode("scannerVideo");

            const config = {
                fps: 10,
                qrbox: {
                    width: 250,
                    height: 250
                }
            };

            Html5Qrcode.getCameras().then(devices => {
                if (devices && devices.length) {
                    currentCameraId = devices[0].id;
                    scanner.start(
                        currentCameraId,
                        config,
                        onScanSuccess,
                        onScanError
                    ).catch(err => {
                        console.error("Scanner error:", err);
                        alert("Failed to start scanner: " + err);
                        stopScanner();
                    });
                } else {
                    alert("No camera found");
                    stopScanner();
                }
            }).catch(err => {
                console.error("Camera error:", err);
                alert("Cannot access camera");
                stopScanner();
            });
        }

        // Stop Scanner
        function stopScanner() {
            if (scanner) {
                scanner.stop().then(() => {
                    scanner = null;
                }).catch(err => {
                    console.error("Stop error:", err);
                });
            }

            scannerArea.classList.remove('active');
            qrScannerContainer.style.display = 'none';
            currentCameraId = null;
        }

        // Toggle between front/back camera
        function toggleCamera() {
            Html5Qrcode.getCameras().then(devices => {
                if (devices.length < 2) {
                    alert("Only one camera available");
                    return;
                }

                stopScanner();
                setTimeout(() => {
                    currentCameraId = devices.find(device => device.id !== currentCameraId)?.id || devices[0].id;
                    startScanner();
                }, 500);
            });
        }

        // Handle successful scan
        function onScanSuccess(decodedText) {
            console.log("Scanned:", decodedText);

            // Extract item ID from QR code data
            let itemId = decodedText;
            if (decodedText.includes('item_id=')) {
                const match = decodedText.match(/item_id=(\d+)/);
                itemId = match ? match[1] : decodedText;
            }

            // Stop scanner and show results
            stopScanner();
            lookupItem(itemId);
        }

        // Handle scan error
        function onScanError(error) {
            // Suppress frequent error logs
            if (!error.includes("NotFoundException")) {
                console.warn("Scan error:", error);
            }
        }

        // Manual lookup by serial number
        function manualLookup() {
            const serial = document.getElementById('manualSerial').value.trim();
            if (!serial) {
                alert("Please enter a serial number");
                return;
            }

            lookupItem(serial);
            document.getElementById('manualSerial').value = '';
        }

        // Lookup item from server
        function lookupItem(identifier) {
            showLoading();

            fetch(`../api/get_item_by_scan.php?id=${encodeURIComponent(identifier)}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        displayItem(data.data);
                        addToRecentScans(data.data);
                    } else {
                        showError(data.message || "Item not found");
                    }
                })
                .catch(error => {
                    console.error("Lookup error:", error);
                    showError("Error looking up item");
                })
                .finally(() => {
                    hideLoading();
                });
        }

        // Display item information
        function displayItem(item) {
            // Update item card
            document.getElementById('itemName').textContent = item.item_name;
            document.getElementById('itemCategory').textContent = item.category;
            document.getElementById('itemSerial').textContent = item.serial_number;
            document.getElementById('itemLocation').textContent = item.stock_location;

            // Update status badge
            const statusBadge = document.getElementById('itemStatusBadge');
            statusBadge.className = 'badge status-' + item.status;
            statusBadge.textContent = formatStatus(item.status);

            // Store current item ID
            itemCard.dataset.itemId = item.id;

            // Show item card with animation
            itemCard.classList.add('show');

            // Scroll to item card
            itemCard.scrollIntoView({
                behavior: 'smooth',
                block: 'nearest'
            });
        }

        // Update item status
        function updateStatus(newStatus) {
            const itemId = itemCard.dataset.itemId;
            if (!itemId) return;

            fetch('../api/update_item_status.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        item_id: itemId,
                        status: newStatus,
                        technician_id: '<?php echo getTechnicianId(); ?>'
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Update status badge
                        const statusBadge = document.getElementById('itemStatusBadge');
                        statusBadge.className = 'badge status-' + newStatus;
                        statusBadge.textContent = formatStatus(newStatus);

                        // Update in recent scans
                        updateRecentScanStatus(itemId, newStatus);

                        // Show success message
                        showMessage('Status updated successfully!', 'success');
                    } else {
                        showError(data.message || "Failed to update status");
                    }
                })
                .catch(error => {
                    console.error("Update error:", error);
                    showError("Error updating status");
                });
        }

        // Add item to recent scans
        function addToRecentScans(item) {
            // Remove if already exists
            scannedItems = scannedItems.filter(scan => scan.id !== item.id);

            // Add to beginning
            scannedItems.unshift({
                id: item.id,
                name: item.item_name,
                serial: item.serial_number,
                status: item.status,
                time: new Date().toLocaleTimeString(),
                date: new Date().toLocaleDateString()
            });

            // Keep only last 10 scans
            if (scannedItems.length > 10) {
                scannedItems.pop();
            }

            // Save to localStorage
            localStorage.setItem('recentScans', JSON.stringify(scannedItems));

            // Update display
            updateRecentScans();
        }

        // Update recent scan status
        function updateRecentScanStatus(itemId, newStatus) {
            const index = scannedItems.findIndex(scan => scan.id == itemId);
            if (index !== -1) {
                scannedItems[index].status = newStatus;
                localStorage.setItem('recentScans', JSON.stringify(scannedItems));
                updateRecentScans();
            }
        }

        // Update recent scans display
        function updateRecentScans() {
            if (scannedItems.length === 0) {
                recentScansList.innerHTML = `
                    <div class="text-center text-muted py-4">
                        <i class="fas fa-qrcode fa-2x mb-3"></i>
                        <p>No recent scans</p>
                    </div>
                `;
                return;
            }

            let html = '';
            scannedItems.forEach(scan => {
                html += `
                    <div class="scan-item">
                        <div class="me-3">
                            <i class="fas fa-box text-primary"></i>
                        </div>
                        <div class="flex-grow-1">
                            <div class="d-flex justify-content-between">
                                <strong>${scan.name}</strong>
                                <small class="text-muted">${scan.time}</small>
                            </div>
                            <div class="d-flex justify-content-between align-items-center">
                                <small class="text-muted">${scan.serial}</small>
                                <span class="badge status-${scan.status}">
                                    ${formatStatus(scan.status)}
                                </span>
                            </div>
                        </div>
                    </div>
                `;
            });

            recentScansList.innerHTML = html;
        }

        // Helper functions
        function formatStatus(status) {
            const statusMap = {
                'available': 'Available',
                'in_use': 'In Use',
                'maintenance': 'Maintenance',
                'disposed': 'Disposed',
                'lost': 'Lost'
            };
            return statusMap[status] || status;
        }

        function showLoading() {
            scannerArea.innerHTML = `
                <div class="scanner-icon">
                    <i class="fas fa-spinner fa-spin"></i>
                </div>
                <h4>Looking up item...</h4>
            `;
        }

        function hideLoading() {
            scannerArea.innerHTML = `
                <div class="scanner-icon">
                    <i class="fas fa-camera"></i>
                </div>
                <h4>Click to Start Scanning</h4>
                <p class="text-muted">Point your camera at an equipment QR code</p>
                <button class="btn btn-primary" id="startScannerBtn">
                    <i class="fas fa-play me-2"></i> Start Scanner
                </button>
                <div class="mt-3">
                    <small class="text-muted">Or enter serial number manually:</small>
                    <div class="input-group mt-2" style="max-width: 300px; margin: 0 auto;">
                        <input type="text" class="form-control" id="manualSerial" placeholder="Enter serial number">
                        <button class="btn btn-outline-primary" id="manualLookup">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                </div>
            `;

            // Re-attach event listeners
            document.getElementById('startScannerBtn').addEventListener('click', startScanner);
            document.getElementById('manualLookup').addEventListener('click', manualLookup);
        }

        function showError(message) {
            scannerArea.innerHTML = `
                <div class="scanner-icon text-danger">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <h4>${message}</h4>
                <button class="btn btn-primary mt-2" onclick="hideLoading()">
                    <i class="fas fa-redo me-2"></i> Try Again
                </button>
            `;
        }

        function showMessage(message, type = 'success') {
            const alert = document.createElement('div');
            alert.className = `alert alert-${type} alert-dismissible fade show`;
            alert.innerHTML = `
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;

            document.querySelector('.scan-body').insertBefore(alert, itemCard);

            setTimeout(() => {
                alert.remove();
            }, 3000);
        }

        // Auto-focus on manual input
        document.getElementById('manualSerial').focus();
    </script>
</body>

</html>