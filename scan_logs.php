<?php
// scan_logs.php - DataTable view of all scans
require_once 'bootstrap.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit();
}

require_once 'includes/db_connect.php';
require_once 'includes/functions.php';

// Get database connection directly
function getDBConnection()
{
    // Try to get connection from db_connect.php
    global $pdo;

    // If $pdo is not available globally, create it
    if ($pdo === null) {
        try {
            $host = 'localhost';
            $dbname = 'ability_db'; // Change to your database name
            $username = 'root'; // Change to your username
            $password = ''; // Change to your password

            $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            die("Connection failed: " . $e->getMessage());
        }
    }

    return $pdo;
}

// Define API functions
function handleAPIRequest()
{
    // Set JSON header
    header('Content-Type: application/json');

    // Check if user is logged in for API requests
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'message' => 'Not authenticated']);
        exit;
    }

    $action = $_GET['action'] ?? '';

    switch ($action) {
        case 'get_scans':
            getScansAPI();
            break;
        case 'get_scan_details':
            getScanDetailsAPI();
            break;
        case 'delete_scan':
            deleteScanAPI();
            break;
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
            break;
    }
}

function getScansAPI()
{
    $pdo = getDBConnection();

    try {
        // Debug: Check what columns exist in items table
        $itemsColumns = $pdo->query("DESC items")->fetchAll(PDO::FETCH_ASSOC);
        $itemColumnNames = array_column($itemsColumns, 'Field');

        // Find the correct column name for item name
        $itemNameColumn = 'name'; // Default guess
        $possibleNames = ['item_name', 'name', 'title', 'item_name', 'product_name'];
        foreach ($possibleNames as $possible) {
            if (in_array($possible, $itemColumnNames)) {
                $itemNameColumn = $possible;
                break;
            }
        }

        // Check if category column exists
        $hasCategory = in_array('category', $itemColumnNames);
        $hasDescription = in_array('description', $itemColumnNames);

        // Build query dynamically based on what columns exist
        // NOTE: Changed from "s" (scans) to "sl" (scan_logs)
        $selectFields = "sl.*, i.{$itemNameColumn} as item_name, i.serial_number";

        if ($hasCategory) {
            $selectFields .= ", i.category";
        }

        $selectFields .= ", i.status as item_status";

        if ($hasDescription) {
            $selectFields .= ", i.description as item_description";
        }

        $selectFields .= ", u.username, u.email, u.department as user_department";

        // UPDATED: Changed table name from "scans" to "scan_logs" and alias from "s" to "sl"
        $sql = "SELECT 
                    {$selectFields}
                FROM scan_logs sl
                LEFT JOIN items i ON sl.item_id = i.id
                LEFT JOIN users u ON sl.user_id = u.id
                ORDER BY sl.scan_timestamp DESC";

        error_log("Executing SQL: " . $sql);

        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        $scans = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'data' => $scans,
            'count' => count($scans),
            'message' => 'Scans retrieved successfully'
        ]);
    } catch (Exception $e) {
        error_log("Error in getScansAPI: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'message' => 'Error: ' . $e->getMessage(),
            'data' => []
        ]);
    }
}

function getScanDetailsAPI()
{
    $pdo = getDBConnection();

    if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
        echo json_encode([
            'success' => false,
            'message' => 'Invalid scan ID'
        ]);
        exit;
    }

    $scanId = (int)$_GET['id'];

    try {
        // Debug: Check what columns exist in items table
        $itemsColumns = $pdo->query("DESC items")->fetchAll(PDO::FETCH_ASSOC);
        $itemColumnNames = array_column($itemsColumns, 'Field');

        // Find the correct column name for item name
        $itemNameColumn = 'name'; // Default guess
        $possibleNames = ['item_name', 'name', 'title', 'item_name', 'product_name'];
        foreach ($possibleNames as $possible) {
            if (in_array($possible, $itemColumnNames)) {
                $itemNameColumn = $possible;
                break;
            }
        }

        // Check if category column exists
        $hasCategory = in_array('category', $itemColumnNames);
        $hasDescription = in_array('description', $itemColumnNames);

        // Build query dynamically based on what columns exist
        // UPDATED: Changed from "s" to "sl" for scan_logs
        $selectFields = "sl.*, i.{$itemNameColumn} as item_name, i.serial_number";

        if ($hasCategory) {
            $selectFields .= ", i.category";
        }

        $selectFields .= ", i.status as item_status";

        if ($hasDescription) {
            $selectFields .= ", i.description as item_description";
        }

        $selectFields .= ", u.username, u.email, u.department as user_department";

        // UPDATED: Changed table name from "scans" to "scan_logs"
        $sql = "SELECT 
                    {$selectFields}
                FROM scan_logs sl
                LEFT JOIN items i ON sl.item_id = i.id
                LEFT JOIN users u ON sl.user_id = u.id
                WHERE sl.id = :id";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([':id' => $scanId]);
        $scan = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($scan) {
            echo json_encode([
                'success' => true,
                'data' => $scan,
                'message' => 'Scan details retrieved successfully'
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Scan not found'
            ]);
        }
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Error: ' . $e->getMessage()
        ]);
    }
}
function deleteScanAPI()
{
    $pdo = getDBConnection();

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode([
            'success' => false,
            'message' => 'Invalid request method'
        ]);
        exit;
    }

    if (!isset($_POST['id']) || !is_numeric($_POST['id'])) {
        echo json_encode([
            'success' => false,
            'message' => 'Invalid scan ID'
        ]);
        exit;
    }

    $scanId = (int)$_POST['id'];

    try {
        // Start transaction
        $pdo->beginTransaction();

        // Get scan details before deleting (for logging)
        // UPDATED: Changed table name from "scans" to "scan_logs"
        $stmt = $pdo->prepare("SELECT * FROM scan_logs WHERE id = :id");
        $stmt->execute([':id' => $scanId]);
        $scan = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$scan) {
            $pdo->rollBack();
            echo json_encode([
                'success' => false,
                'message' => 'Scan not found'
            ]);
            exit;
        }

        // Delete the scan
        // UPDATED: Changed table name from "scans" to "scan_logs"
        $stmt = $pdo->prepare("DELETE FROM scan_logs WHERE id = :id");
        $stmt->execute([':id' => $scanId]);

        // Log the deletion (if audit_logs table exists)
        try {
            $logSql = "INSERT INTO audit_logs 
                       (user_id, action, table_name, record_id, old_values, timestamp)
                       VALUES (:user_id, :action, :table_name, :record_id, :old_values, NOW())";

            $logStmt = $pdo->prepare($logSql);
            $logStmt->execute([
                ':user_id' => $_SESSION['user_id'] ?? null,
                ':action' => 'DELETE',
                ':table_name' => 'scan_logs', // UPDATED table name
                ':record_id' => $scanId,
                ':old_values' => json_encode($scan)
            ]);
        } catch (Exception $e) {
            // If audit_logs table doesn't exist, just continue
            error_log("Audit log error: " . $e->getMessage());
        }

        $pdo->commit();

        echo json_encode([
            'success' => true,
            'message' => 'Scan deleted successfully'
        ]);
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode([
            'success' => false,
            'message' => 'Error: ' . $e->getMessage()
        ]);
    }
}

// Handle API requests BEFORE any output
if (isset($_GET['action'])) {
    handleAPIRequest();
    exit; // Stop execution after API response
}

// Only run the normal page if not an API request
$pageTitle = "Scan Logs - aBility";
$showBreadcrumb = true;
$breadcrumbItems = [
    'Dashboard' => 'dashboard_full.php',
    'Scan Logs' => ''
];

require_once 'views/partials/header.php';
?>

<!-- Include DataTables CSS -->
<link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css">
<link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/buttons/2.3.6/css/buttons.bootstrap5.min.css">
<link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/responsive/2.4.1/css/responsive.bootstrap5.min.css">
<link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/select/1.6.2/css/select.bootstrap5.min.css">

<style>
    .dataTables_wrapper {
        padding: 20px 0;
    }

    .badge-check_in {
        background-color: #28a745;
    }

    .badge-check_out {
        background-color: #007bff;
    }

    .badge-maintenance {
        background-color: #ffc107;
        color: #000;
    }

    .badge-inventory {
        background-color: #17a2b8;
    }

    /* Loading overlay */
    .loading-overlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(255, 255, 255, 0.9);
        z-index: 9999;
        display: flex;
        justify-content: center;
        align-items: center;
    }

    .spinner-lg {
        width: 4rem;
        height: 4rem;
    }
</style>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0 text-gray-800">
            <i class="fas fa-list me-2"></i>Scan History
        </h1>
        <div>
            <a href="scan.php" class="btn btn-primary">
                <i class="fas fa-qrcode me-1"></i> New Scan
            </a>
            <a href="reports.php" class="btn btn-outline-secondary">
                <i class="fas fa-chart-bar me-1"></i> Reports
            </a>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="row mb-4">
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                Total Scans</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800" id="totalScans">Loading...</div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-qrcode fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                Today's Scans</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800" id="todayScans">Loading...</div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-calendar-day fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-warning shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                Active Items</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800" id="activeItems">Loading...</div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-boxes fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-info shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                Unique Users</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800" id="uniqueUsers">Loading...</div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-users fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Scan Logs Table -->
    <div class="card shadow">
        <div class="card-header py-3 d-flex justify-content-between align-items-center">
            <h6 class="m-0 font-weight-bold text-primary">
                <i class="fas fa-list me-2"></i>All Scan History
            </h6>
            <div class="d-flex align-items-center">
                <div class="me-3">
                    <span class="badge badge-check_in me-1">Check In</span>
                    <span class="badge badge-check_out me-1">Check Out</span>
                    <span class="badge badge-maintenance me-1">Maintenance</span>
                    <span class="badge badge-inventory">Inventory</span>
                </div>
                <div class="dropdown">
                    <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button"
                        data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="fas fa-filter me-1"></i> Filter
                    </button>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item filter-type" href="#" data-type="">All Types</a></li>
                        <li>
                            <hr class="dropdown-divider">
                        </li>
                        <li><a class="dropdown-item filter-type" href="#" data-type="check_in">Check In Only</a></li>
                        <li><a class="dropdown-item filter-type" href="#" data-type="check_out">Check Out Only</a></li>
                        <li><a class="dropdown-item filter-type" href="#" data-type="maintenance">Maintenance Only</a></li>
                        <li><a class="dropdown-item filter-type" href="#" data-type="inventory">Inventory Only</a></li>
                    </ul>
                </div>
            </div>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table id="scanLogsTable" class="table table-striped table-hover" style="width:100%">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Timestamp</th>
                            <th>Item</th>
                            <th>Serial</th>
                            <th>Type</th>
                            <th>Person Responsible</th>
                            <th>Location</th>
                            <th>Vehicle</th>
                            <th>Notes</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- Data will be loaded via DataTables -->
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Loading Overlay -->
<div id="loadingOverlay" class="loading-overlay" style="display: none;">
    <div class="text-center">
        <div class="spinner-border text-primary spinner-lg" role="status">
            <span class="visually-hidden">Loading...</span>
        </div>
        <h5 class="mt-3">Loading scan data...</h5>
        <p class="text-muted">Please wait while we fetch your scan logs</p>
    </div>
</div>

<!-- Include DataTables JS -->
<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.3.6/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.3.6/js/buttons.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.3.6/js/buttons.html5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.3.6/js/buttons.print.min.js"></script>
<script src="https://cdn.datatables.net/responsive/2.4.1/js/dataTables.responsive.min.js"></script>
<script src="https://cdn.datatables.net/responsive/2.4.1/js/responsive.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/select/1.6.2/js/dataTables.select.min.js"></script>

<script>
    // Show loading overlay
    function showLoading() {
        $('#loadingOverlay').fadeIn();
    }

    // Hide loading overlay
    function hideLoading() {
        $('#loadingOverlay').fadeOut();
    }

    // Get API URL based on configuration
    function getApiUrl() {
        return 'scan_logs.php?action=get_scans';
    }

    // Get scan details URL
    function getScanDetailsUrl(scanId) {
        return 'scan_logs.php?action=get_scan_details&id=' + scanId;
    }

    // Delete scan URL
    function getDeleteScanUrl() {
        return 'scan_logs.php?action=delete_scan';
    }
</script>

<script>
    $(document).ready(function() {
        showLoading();

        // Debug: Test the API endpoint first
        console.log('Testing API endpoint...');
        const apiUrl = getApiUrl();
        console.log('Using API URL:', apiUrl);

        // Test API endpoint
        $.ajax({
            url: apiUrl,
            type: 'GET',
            dataType: 'json',
            success: function(response) {
                console.log('API Response:', response);
                if (!response.success) {
                    console.error('API Error:', response.message);
                    showTableError('API Error: ' + response.message);
                }
                hideLoading();
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', status, error);
                console.log('Response Text:', xhr.responseText);

                // Try to parse error message
                let errorMsg = 'Cannot connect to API. ';
                if (xhr.responseText) {
                    // Check if it's HTML error
                    if (xhr.responseText.includes('<br />') || xhr.responseText.includes('<b>')) {
                        errorMsg += 'Server returned PHP error. Check console for details.';
                    } else if (xhr.responseText.includes('<!DOCTYPE')) {
                        errorMsg += 'Server returned HTML instead of JSON. API endpoint may be misconfigured.';
                    } else {
                        try {
                            const errorResponse = JSON.parse(xhr.responseText);
                            errorMsg += errorResponse.message || 'Unknown error';
                        } catch (e) {
                            errorMsg += 'Invalid response format.';
                        }
                    }
                }

                showTableError(errorMsg);
                hideLoading();
            }
        });

        // Function to show error in table
        function showTableError(message) {
            $('#scanLogsTable tbody').html(`
            <tr>
                <td colspan="10" class="text-center text-danger py-4">
                    <i class="fas fa-exclamation-triangle fa-2x mb-2"></i>
                    <h5>Error Loading Data</h5>
                    <p>${message}</p>
                    <div class="mt-3">
                        <button class="btn btn-primary me-2" onclick="location.reload()">
                            <i class="fas fa-sync-alt me-1"></i> Reload Page
                        </button>
                        <button class="btn btn-secondary" onclick="window.open(getApiUrl(), '_blank')">
                            <i class="fas fa-vial me-1"></i> Test API
                        </button>
                    </div>
                </td>
            </tr>
        `);

            // Also update stats with error
            $('#totalScans').text('Error');
            $('#todayScans').text('Error');
            $('#activeItems').text('Error');
            $('#uniqueUsers').text('Error');
        }

        // Initialize DataTable with error handling
        const scanLogsTable = $('#scanLogsTable').DataTable({
            processing: true,
            serverSide: false,
            ajax: {
                url: getApiUrl(),
                dataSrc: function(json) {
                    console.log('DataTable AJAX Response:', json);

                    // Check if response is valid
                    if (!json) {
                        console.error('Empty response from API');
                        showTableError('Empty response from server');
                        return [];
                    }

                    // Check for success flag
                    if (json.success === false) {
                        console.error('API reported error:', json.message);
                        showTableError('API Error: ' + (json.message || 'Unknown error'));
                        return [];
                    }

                    // Return data array
                    return json.data || [];
                },
                error: function(xhr, error, thrown) {
                    console.error('DataTable AJAX Error:', xhr, error, thrown);
                    hideLoading();

                    let errorMessage = 'Failed to load data. ';
                    let showReload = true;

                    // Try to parse error response
                    if (xhr.responseText) {
                        // Check if it's HTML (PHP errors or page HTML)
                        if (xhr.responseText.includes('<!DOCTYPE') || xhr.responseText.includes('<html') || xhr.responseText.includes('<!-- views')) {
                            errorMessage = 'Server returned HTML instead of JSON. API endpoint may be misconfigured.';
                            showReload = false;
                        } else if (xhr.responseText.includes('<br />') || xhr.responseText.includes('<b>Warning') || xhr.responseText.includes('<b>Fatal error')) {
                            errorMessage = 'Server returned PHP errors. This usually means the API file has syntax errors or missing database connections.';
                            showReload = false;
                        } else {
                            try {
                                const response = JSON.parse(xhr.responseText);
                                errorMessage += response.message || 'Unknown error';
                            } catch (e) {
                                errorMessage = 'Invalid JSON response from server. Status: ' + xhr.status;
                            }
                        }
                    } else {
                        errorMessage += 'No response from server. Check if the API file exists.';
                    }

                    showTableError(errorMessage);

                    // If it's a PHP error, show additional help
                    if (!showReload) {
                        $('.btn-secondary').hide();
                        $('.btn-primary').text('Fix API Issues').click(function() {
                            window.location.href = 'api_fix.php'; // You can create this helper page
                        });
                    }
                }
            },
            columns: [{
                    data: 'id',
                    className: 'fw-bold',
                    width: '50px',
                    defaultContent: ''
                },
                {
                    data: 'scan_timestamp',
                    render: function(data, type, row) {
                        if (!data) return 'N/A';
                        try {
                            // Handle different date formats
                            let dateStr = data;
                            if (data.includes(' ')) {
                                dateStr = data.replace(' ', 'T');
                            }
                            const date = new Date(dateStr);
                            if (isNaN(date.getTime())) return data;

                            return `<div class="text-nowrap">${date.toLocaleDateString()}</div>
                                <div class="text-muted small">${date.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'})}</div>`;
                        } catch (e) {
                            console.warn('Date parsing error:', e, 'for data:', data);
                            return data;
                        }
                    },
                    width: '120px'
                },
                {
                    data: 'item_name',
                    render: function(data, type, row) {
                        if (!data) return 'N/A';
                        const itemId = row.item_id || '';
                        return `<a href="items/view.php?id=${itemId}" class="text-primary">
                <i class="fas fa-box me-1"></i>${data}
            </a>`;
                    },
                    defaultContent: 'N/A'
                },
                {
                    data: 'serial_number',
                    render: function(data) {
                        if (!data) return 'N/A';
                        return `<code class="small">${data}</code>`;
                    },
                    width: '100px',
                    defaultContent: 'N/A'
                },
                {
                    data: 'scan_type',
                    render: function(data) {
                        if (!data) return 'N/A';
                        const badgeClass = 'badge-' + data;
                        const displayText = data.replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase());
                        return `<span class="badge ${badgeClass}">${displayText}</span>`;
                    },
                    width: '80px',
                    defaultContent: 'N/A'
                },
                {
                    data: 'transport_user',
                    render: function(data, type, row) {
                        if (!data && row.username) {
                            return row.username || row.email || 'N/A';
                        }
                        if (!data) return '<span class="text-muted">N/A</span>';

                        let html = `<div class="fw-bold">${data}</div>`;
                        if (row.user_department) {
                            html += `<div class="small text-muted">${row.user_department}</div>`;
                        }
                        if (row.user_id_number) {
                            html += `<div class="small"><code>${row.user_id_number}</code></div>`;
                        }
                        return html;
                    },
                    defaultContent: '<span class="text-muted">N/A</span>'
                },
                {
                    data: 'from_location',
                    render: function(data, type, row) {
                        if (!row.from_location && !row.to_location) {
                            return row.location || '<span class="text-muted">N/A</span>';
                        }

                        let html = '';
                        if (row.from_location) {
                            html += `<div class="small">
                                    <i class="fas fa-map-marker-alt text-danger me-1"></i>
                                    <span class="fw-bold">From:</span> ${row.from_location}
                                </div>`;
                        }
                        if (row.to_location) {
                            html += `<div class="small">
                                    <i class="fas fa-map-marker-alt text-success me-1"></i>
                                    <span class="fw-bold">To:</span> ${row.to_location}
                                </div>`;
                        }
                        if (row.destination_address) {
                            html += `<div class="small text-muted">${row.destination_address.substring(0, 30)}${row.destination_address.length > 30 ? '...' : ''}</div>`;
                        }
                        return html || '<span class="text-muted">N/A</span>';
                    },
                    defaultContent: '<span class="text-muted">N/A</span>'
                },
                {
                    data: 'vehicle_plate',
                    render: function(data, type, row) {
                        let html = '';
                        if (data) {
                            html += `<div class="fw-bold">
                                    <i class="fas fa-truck me-1"></i>
                                    ${data}
                                </div>`;
                            if (row.vehicle_type) {
                                html += `<div class="small text-muted">${row.vehicle_type.charAt(0).toUpperCase() + row.vehicle_type.slice(1)}</div>`;
                            }
                            if (row.vehicle_description) {
                                html += `<div class="small">${row.vehicle_description.substring(0, 20)}${row.vehicle_description.length > 20 ? '...' : ''}</div>`;
                            }
                        }
                        return html || '<span class="text-muted">-</span>';
                    },
                    width: '120px',
                    defaultContent: '<span class="text-muted">-</span>'
                },
                {
                    data: 'notes',
                    render: function(data, type, row) {
                        let notes = data || '';

                        // Check if scanned_data exists and add it to notes
                        if (row.scanned_data && row.scanned_data.trim() !== '') {
                            if (notes) notes += ' | ';
                            notes += 'Scanned: ' + row.scanned_data;
                        }

                        if (row.transport_notes) {
                            if (notes) notes += ' | ';
                            notes += row.transport_notes;
                        }

                        if (!notes || notes.trim() === '') return '<span class="text-muted">-</span>';

                        const safeData = String(notes).replace(/"/g, '&quot;').replace(/'/g, '&#39;');
                        return `<span title="${safeData}" data-bs-toggle="tooltip">
                ${String(notes).substring(0, 30)}${String(notes).length > 30 ? '...' : ''}
            </span>`;
                    },
                    width: '100px',
                    defaultContent: '<span class="text-muted">-</span>'
                },
                {
                    data: 'id',
                    orderable: false,
                    render: function(data, type, row) {
                        return `
                        <div class="btn-group btn-group-sm">
                            <button class="btn btn-outline-info view-scan" data-id="${data || ''}" title="View Full Details">
                                <i class="fas fa-eye"></i>
                            </button>
                            <button class="btn btn-outline-danger delete-scan" data-id="${data || ''}" title="Delete">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    `;
                    },
                    width: '80px'
                }
            ],
            order: [
                [1, 'asc']
            ],
            pageLength: 5,
            lengthMenu: [
                [10, 25, 50, 100, -1],
                [10, 25, 50, 100, "All"]
            ],
            dom: '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>' +
                '<"row"<"col-sm-12"tr>>' +
                '<"row"<"col-sm-12 col-md-5"i><"col-sm-12 col-md-7"p>>',
            buttons: [{
                    extend: 'copy',
                    className: 'btn btn-sm btn-outline-secondary',
                    text: '<i class="fas fa-copy me-1"></i> Copy'
                },
                {
                    extend: 'excel',
                    className: 'btn btn-outline-success',
                    text: '<i class="fas fa-file-excel me-1"></i> Excel',
                    title: 'Scan_Logs_' + new Date().toISOString().split('T')[0]
                },
                {
                    extend: 'pdf',
                    className: 'btn btn-outline-danger',
                    text: '<i class="fas fa-file-pdf me-1"></i> PDF',
                    title: 'Scan_Logs_' + new Date().toISOString().split('T')[0]
                },
                {
                    extend: 'print',
                    className: 'btn btn-outline-info',
                    text: '<i class="fas fa-print me-1"></i> Print',
                    title: 'Scan Logs'
                }
            ],
            language: {
                processing: '<div class="spinner-border text-primary" role="status"></div> Loading...',
                emptyTable: 'No scan logs found. Start scanning items!',
                info: 'Showing _START_ to _END_ of _TOTAL_ scans',
                infoEmpty: 'Showing 0 to 0 of 0 scans',
                lengthMenu: 'Show _MENU_ scans',
                search: 'Search:',
                zeroRecords: 'No matching scans found'
            },
            initComplete: function(settings, json) {
                console.log('DataTable initialization complete');
                hideLoading();

                // Check if we have data
                const data = this.api().rows().data();
                console.log('Loaded', data.count(), 'rows');

                if (data.count() === 0 && (!json || !json.data)) {
                    console.warn('No data loaded - showing empty state');
                    // Don't show error if it's legitimately empty
                } else {
                    // Load stats
                    loadStats();

                    // Initialize tooltips
                    $('[data-bs-toggle="tooltip"]').tooltip();

                    // Add refresh button to header
                    $('.dataTables_length').append('<button id="refreshBtn" class="btn btn-sm btn-outline-success ms-2"><i class="fas fa-sync-alt me-1"></i> Refresh</button>');

                    $('#refreshBtn').click(function() {
                        console.log('Refreshing table...');
                        showLoading();
                        scanLogsTable.ajax.reload(null, false); // Don't reset paging
                        loadStats();
                        showToast('success', 'Table refreshed successfully');
                        hideLoading();
                    });
                }
            }
        });

        // Load statistics
        function loadStats() {
            try {
                // Get all data from the table
                const tableData = scanLogsTable.rows().data().toArray();
                console.log('Calculating stats for', tableData.length, 'rows');

                // Calculate total scans
                $('#totalScans').text(tableData.length);

                // Calculate today's scans
                const today = new Date().toLocaleDateString();
                const todayScans = tableData.filter(row => {
                    try {
                        if (!row.scan_timestamp) return false;
                        let dateStr = row.scan_timestamp;
                        if (dateStr.includes(' ')) {
                            dateStr = dateStr.replace(' ', 'T');
                        }
                        const rowDate = new Date(dateStr).toLocaleDateString();
                        return rowDate === today;
                    } catch (e) {
                        console.warn('Error parsing date for stats:', e);
                        return false;
                    }
                }).length;
                $('#todayScans').text(todayScans);

                // Calculate active items (currently checked out)
                const checkOutItems = [];
                const checkInItems = [];

                tableData.forEach(row => {
                    if (row.scan_type === 'check_out') {
                        checkOutItems.push(row.item_id);
                    } else if (row.scan_type === 'check_in') {
                        checkInItems.push(row.item_id);
                    }
                });

                // Items that are checked out but not checked back in
                const activeItems = checkOutItems.filter(itemId =>
                    !checkInItems.some(inItemId => inItemId === itemId)
                );
                $('#activeItems').text([...new Set(activeItems)].length); // Unique items

                // Calculate unique users
                const uniqueUsers = new Set(tableData
                    .filter(row => row.transport_user)
                    .map(row => row.transport_user));
                $('#uniqueUsers').text(uniqueUsers.size);

                console.log('Stats calculated:', {
                    total: tableData.length,
                    today: todayScans,
                    active: [...new Set(activeItems)].length,
                    uniqueUsers: uniqueUsers.size
                });

            } catch (error) {
                console.error('Error loading stats:', error);
                // Set default values
                $('#totalScans').text('Error');
                $('#todayScans').text('Error');
                $('#activeItems').text('Error');
                $('#uniqueUsers').text('Error');
            }
        }

        // Filter by scan type
        $('.filter-type').click(function(e) {
            e.preventDefault();
            const type = $(this).data('type');

            if (type) {
                scanLogsTable.column(4).search(type).draw();
            } else {
                scanLogsTable.column(4).search('').draw();
            }
        });

        // View scan details
        $('#scanLogsTable').on('click', '.view-scan', function() {
            const scanId = $(this).data('id');
            if (!scanId) {
                showToast('error', 'Invalid scan ID');
                return;
            }
            viewScanDetails(scanId);
        });

        // Delete scan
        $('#scanLogsTable').on('click', '.delete-scan', function() {
            const scanId = $(this).data('id');
            if (!scanId) {
                showToast('error', 'Invalid scan ID');
                return;
            }
            deleteScan(scanId);
        });

        // View scan details function
        function viewScanDetails(scanId) {
            console.log('Viewing scan details for ID:', scanId);
            showLoading();

            $.ajax({
                url: getScanDetailsUrl(scanId),
                type: 'GET',
                dataType: 'json',
                success: function(response) {
                    hideLoading();
                    console.log('Scan details response:', response);
                    if (response.success) {
                        showScanModal(response.data);
                    } else {
                        showToast('error', response.message || 'Failed to load scan details');
                    }
                },
                error: function(xhr, status, error) {
                    hideLoading();
                    console.error('Error loading scan details:', status, error);
                    showToast('error', 'Error loading scan details. Check console.');
                }
            });
        }

        // Delete scan function
        function deleteScan(scanId) {
            if (confirm('Are you sure you want to delete this scan log? This action cannot be undone.')) {
                showLoading();
                console.log('Deleting scan ID:', scanId);
                $.ajax({
                    url: getDeleteScanUrl(),
                    type: 'POST',
                    data: {
                        id: scanId
                    },
                    dataType: 'json',
                    success: function(response) {
                        hideLoading();
                        console.log('Delete response:', response);
                        if (response.success) {
                            showToast('success', 'Scan log deleted successfully');
                            scanLogsTable.ajax.reload();
                            loadStats();
                        } else {
                            showToast('error', response.message || 'Failed to delete scan log');
                        }
                    },
                    error: function(xhr, status, error) {
                        hideLoading();
                        console.error('Error deleting scan:', status, error);
                        showToast('error', 'Error deleting scan log');
                    }
                });
            }
        }

        // Show scan details modal
        function showScanModal(scan) {
            console.log('Showing modal for scan:', scan);

            // Format dates
            let scanDate = 'N/A';
            let expectedReturn = 'Not specified';

            try {
                if (scan.scan_timestamp) {
                    let dateStr = scan.scan_timestamp;
                    if (dateStr.includes(' ')) {
                        dateStr = dateStr.replace(' ', 'T');
                    }
                    scanDate = new Date(dateStr).toLocaleString();
                }
                if (scan.expected_return) {
                    expectedReturn = new Date(scan.expected_return).toLocaleDateString();
                }
            } catch (e) {
                console.warn('Date parsing error in modal:', e);
            }

            // Build transport details HTML
            let transportHtml = '';
            if (scan.from_location || scan.to_location || scan.transport_user || scan.vehicle_plate) {
                transportHtml = `
                <div class="row mt-3">
                    <div class="col-12">
                        <h6><i class="fas fa-truck-loading me-2"></i>Transport Details</h6>
                        <div class="card">
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <table class="table table-sm table-borderless">
                                            ${scan.from_location ? `<tr><th width="120">From:</th><td>${scan.from_location}</td></tr>` : ''}
                                            ${scan.to_location ? `<tr><th>To:</th><td>${scan.to_location}</td></tr>` : ''}
                                            ${scan.destination_address ? `<tr><th>Address:</th><td>${scan.destination_address}</td></tr>` : ''}
                                        </table>
                                    </div>
                                    <div class="col-md-6">
                                        <table class="table table-sm table-borderless">
                                            ${scan.transport_user ? `<tr><th width="120">Person:</th><td>${scan.transport_user}</td></tr>` : ''}
                                            ${scan.user_contact ? `<tr><th>Contact:</th><td>${scan.user_contact}</td></tr>` : ''}
                                            ${scan.user_department ? `<tr><th>Dept:</th><td>${scan.user_department}</td></tr>` : ''}
                                        </table>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6">
                                        <table class="table table-sm table-borderless">
                                            ${scan.vehicle_plate ? `<tr><th width="120">Vehicle:</th><td><i class="fas fa-truck me-1"></i> ${scan.vehicle_plate}</td></tr>` : ''}
                                            ${scan.vehicle_type ? `<tr><th>Type:</th><td>${scan.vehicle_type}</td></tr>` : ''}
                                            ${scan.vehicle_description ? `<tr><th>Description:</th><td>${scan.vehicle_description}</td></tr>` : ''}
                                        </table>
                                    </div>
                                    <div class="col-md-6">
                                        <table class="table table-sm table-borderless">
                                            ${scan.scan_type === 'check_out' ? `<tr><th width="120">Expected Return:</th><td>${expectedReturn}</td></tr>` : ''}
                                            ${scan.priority && scan.priority !== 'normal' ? `<tr><th>Priority:</th><td><span class="badge bg-${scan.priority === 'urgent' ? 'danger' : 'warning'}">${scan.priority.toUpperCase()}</span></td></tr>` : ''}
                                        </table>
                                    </div>
                                </div>
                                ${scan.transport_notes ? `
                                <div class="row mt-2">
                                    <div class="col-12">
                                        <strong>Transport Notes:</strong>
                                        <div class="alert alert-light mt-1 mb-0">
                                            ${scan.transport_notes}
                                        </div>
                                    </div>
                                </div>` : ''}
                            </div>
                        </div>
                    </div>
                </div>
            `;
            }

            const modalHtml = `
            <div class="modal fade" id="scanDetailsModal" tabindex="-1">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">
                                <i class="fas fa-info-circle me-2"></i>
                                Scan Details #${scan.id || 'N/A'}
                            </h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <h6><i class="fas fa-qrcode me-2"></i>Scan Information</h6>
                                    <table class="table table-sm">
                                        <tr><th width="140">Scan ID:</th><td>#${scan.id || 'N/A'}</td></tr>
                                        <tr><th>Timestamp:</th><td>${scanDate}</td></tr>
                                        <tr><th>Type:</th><td><span class="badge badge-${scan.scan_type || ''}">${scan.scan_type || 'N/A'}</span></td></tr>
                                        <tr><th>User:</th><td>${scan.username || scan.transport_user || scan.email || 'N/A'}</td></tr>
                                    </table>
                                </div>
                                <div class="col-md-6">
                                    <h6><i class="fas fa-box me-2"></i>Item Information</h6>
                                    <table class="table table-sm">
                                        <tr><th width="140">Item:</th><td><a href="items/view.php?id=${scan.item_id || ''}" class="text-primary">${scan.item_name || 'N/A'}</a></td></tr>
                                        <tr><th>Serial:</th><td><code>${scan.serial_number || 'N/A'}</code></td></tr>
                                        <tr><th>Category:</th><td>${scan.category || 'N/A'}</td></tr>
                                        <tr><th>Status:</th><td>${scan.item_status || 'N/A'}</td></tr>
                                    </table>
                                </div>
                            </div>
                            ${transportHtml}
                            ${scan.notes ? `
                            <div class="row mt-3">
                                <div class="col-12">
                                    <h6><i class="fas fa-sticky-note me-2"></i>Notes</h6>
                                    <div class="card">
                                        <div class="card-body">
                                            ${scan.notes}
                                        </div>
                                    </div>
                                </div>
                            </div>` : ''}
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                <i class="fas fa-times me-1"></i> Close
                            </button>
                            ${scan.id ? `<button type="button" class="btn btn-danger" onclick="deleteScan(${scan.id})" data-bs-dismiss="modal">
                                <i class="fas fa-trash me-1"></i> Delete
                            </button>` : ''}
                        </div>
                    </div>
                </div>
            </div>
        `;

            // Remove existing modal if any
            $('#scanDetailsModal').remove();

            // Add modal to body
            $('body').append(modalHtml);

            // Show modal
            const modal = new bootstrap.Modal(document.getElementById('scanDetailsModal'));
            modal.show();
        }

        // Toast notification function
        function showToast(type, message) {
            const toastHtml = `
            <div class="toast align-items-center text-white bg-${type} border-0" role="alert">
                <div class="d-flex">
                    <div class="toast-body">
                        ${type === 'success' ? '<i class="fas fa-check-circle me-2"></i>' : '<i class="fas fa-exclamation-triangle me-2"></i>'}
                        ${message}
                    </div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                </div>
            </div>
        `;

            $('#toastContainer').append(toastHtml);
            $('.toast').toast('show');

            // Remove toast after it hides
            $('.toast').on('hidden.bs.toast', function() {
                $(this).remove();
            });
        }
    });
</script>

<!-- Toast container -->
<div id="toastContainer" class="toast-container position-fixed bottom-0 end-0 p-3"></div>

<?php
require_once 'views/partials/footer.php';
?>