<?php
// import_items.php - Main Import Page with AJAX Interface
session_start();

// Define root directory
$rootDir = __DIR__;

// Check if includes directory exists
$includesDir = $rootDir . '/includes';
if (!is_dir($includesDir)) {
    if (!mkdir($includesDir, 0755, true)) {
        die("Error: Could not create includes directory at $includesDir");
    }
}

// Include required files
require_once $rootDir . '/includes/db_connect.php';
require_once $rootDir . '/includes/functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$title = 'Import Items from Excel';
$page = 'import';

// Check if PhpSpreadsheet is available
$phpspreadsheetLoaded = false;
$phpspreadsheetError = '';

try {
    $vendorPaths = [
        $rootDir . '/vendor/autoload.php',
        dirname($rootDir) . '/vendor/autoload.php',
        'vendor/autoload.php'
    ];

    foreach ($vendorPaths as $vendorPath) {
        if (file_exists($vendorPath)) {
            require_once $vendorPath;
            $phpspreadsheetLoaded = true;
            break;
        }
    }
} catch (Exception $e) {
    $phpspreadsheetError = $e->getMessage();
    $phpspreadsheetLoaded = false;
}

// Handle initial file upload and preview
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['excel_file'])) {
    try {
        $file = $_FILES['excel_file'];

        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errorMessages = [
                UPLOAD_ERR_INI_SIZE => 'File size exceeds server limit',
                UPLOAD_ERR_FORM_SIZE => 'File size exceeds form limit',
                UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
                UPLOAD_ERR_NO_FILE => 'No file was uploaded',
                UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
                UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
                UPLOAD_ERR_EXTENSION => 'File upload stopped by extension'
            ];
            $errorMsg = $errorMessages[$file['error']] ?? 'Unknown upload error';
            throw new Exception('File upload error: ' . $errorMsg);
        }

        $maxFileSize = 10 * 1024 * 1024;
        if ($file['size'] > $maxFileSize) {
            throw new Exception('File size exceeds 10MB limit');
        }

        $allowed_extensions = ['xlsx', 'xls', 'csv'];
        $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        if (!in_array($file_extension, $allowed_extensions)) {
            throw new Exception('Please upload Excel file (.xlsx, .xls, .csv)');
        }

        if (!$phpspreadsheetLoaded) {
            throw new Exception('PhpSpreadsheet library not found. Please install via Composer: composer require phpoffice/phpspreadsheet');
        }

        // Upload file
        $uploadDir = $rootDir . '/uploads/imports/';
        if (!is_dir($uploadDir)) {
            if (!mkdir($uploadDir, 0755, true)) {
                throw new Exception('Could not create uploads directory');
            }
        }

        $safeFilename = preg_replace('/[^a-zA-Z0-9\._-]/', '_', $file['name']);
        $tempFile = $uploadDir . 'temp_' . time() . '_' . $safeFilename;

        if (!move_uploaded_file($file['tmp_name'], $tempFile)) {
            throw new Exception('Failed to save uploaded file');
        }

        // Load spreadsheet
        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($tempFile);
        $worksheet = $spreadsheet->getActiveSheet();

        $highestRow = $worksheet->getHighestRow();
        $highestColumn = $worksheet->getHighestColumn();
        $highestColumnIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highestColumn);

        // Get headers
        $headers = [];
        for ($col = 1; $col <= $highestColumnIndex; ++$col) {
            $cellValue = $worksheet->getCellByColumnAndRow($col, 1)->getValue();
            $headers[] = $cellValue ? trim($cellValue) : "Column $col";
        }

        // Store file info in session
        $_SESSION['import_file'] = $tempFile;
        $_SESSION['import_headers'] = $headers;
        $_SESSION['total_rows'] = $highestRow - 1; // Exclude header
        $_SESSION['import_started'] = false;

        // Preview first few rows
        $previewData = [];
        $previewRows = min(5, $highestRow - 1);

        for ($row = 2; $row <= $previewRows + 1; ++$row) {
            $rowData = [];
            for ($col = 1; $col <= $highestColumnIndex; ++$col) {
                $cellValue = $worksheet->getCellByColumnAndRow($col, $row)->getValue();
                $rowData[] = $cellValue ? trim($cellValue) : '';
            }
            $previewData[] = $rowData;
        }

        // Return JSON for AJAX response
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'preview' => $previewData,
            'headers' => $headers,
            'total_rows' => $highestRow - 1
        ]);
        exit();
    } catch (Exception $e) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'Error: ' . $e->getMessage()
        ]);
        exit();
    }
}

// If we get here, display the main page
$headers = $_SESSION['import_headers'] ?? [];
$previewData = $_SESSION['import_preview'] ?? [];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $title; ?> - Inventory Management System</title>
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="assets/images/upload_lg.png">


    <!-- Bootstrap 5 CSS from local fallback -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet"
        integrity="sha384-9ndCyUaIbzAi2FUVXJi0CjmCapSmO7SnpJef0486qhLnuZ2cdeRhO02iuK6FUUVM" crossorigin="anonymous">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Fixed Toastr CSS (specific stable version) -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/2.1.4/toastr.min.css" rel="stylesheet">

    <!-- Custom Styles -->
    <style>
        @import url("https://fonts.googleapis.com/css2?family=Titillium+Web:ital,wght@0,200;0,300;0,400;0,600;0,700;0,900;1,200;1,300;1,400;1,600;1,700&display=swap");

        /* Global Styles */
        body {
            background-color: #7ca4b7;
            font-family: "Titillium Web", sans-serif;
        }

        .header-container {
            background: #10314b;
            color: white;
            padding: 2rem 0;
            margin-bottom: 2rem;
            /* border-radius: 0 0 20px 20px; */
        }

        .card {
            border: none;
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
            margin-bottom: 1.5rem;
            border-radius: 15px;
        }

        .card-header {
            background-color: #f8f9fa;
            border-bottom: 1px solid rgba(0, 0, 0, .125);
            border-radius: 15px 15px 0 0 !important;
            padding: 1rem 1.5rem;
        }

        .btn-primary {
            background: #18527b;
            border: none;
            padding: 0.5rem 2rem;
        }

        .btn-primary:hover {
            background: #17a2b8;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }

        .preview-table {
            max-height: 300px;
            overflow-y: auto;
        }

        .required-field::after {
            content: " *";
            color: red;
        }

        .step-indicator {
            display: flex;
            justify-content: space-between;
            margin: 2rem 0;
        }

        .step {
            text-align: center;
            flex: 1;
            position: relative;
        }

        .step .circle {
            width: 40px;
            height: 40px;
            background-color: #e9ecef;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 10px;
            font-weight: bold;
        }

        .step.active .circle {
            background: #18527b;
            color: white;
        }

        .step.completed .circle {
            background-color: #28a745;
            color: white;
        }

        .step .line {
            position: absolute;
            top: 20px;
            left: 50%;
            right: -50%;
            height: 2px;
            background-color: #e9ecef;
            z-index: -1;
        }

        .step:last-child .line {
            display: none;
        }

        .import-log {
            max-height: 300px;
            overflow-y: auto;
            font-family: monospace;
            font-size: 0.9em;
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 0.25rem;
            padding: 0.5rem;
        }

        .log-success {
            color: #28a745;
        }

        .log-error {
            color: #dc3545;
        }

        .log-warning {
            color: #ffc107;
        }

        .log-info {
            color: #17a2b8;
        }

        .log-row {
            padding: 2px 0;
            border-bottom: 1px solid #eee;
        }

        .log-row:last-child {
            border-bottom: none;
        }

        #progressModal .modal-dialog {
            max-width: 600px;
        }

        .progress {
            height: 25px;
        }
    </style>
</head>

<body>
    <!-- Navigation Bar -->

    <div class="header-container">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h3><i class="fas fa-file-import me-2"></i><?php echo $title; ?></h3>
                    <small>Import items from Excel files with automatic QR code generation</small>
                </div>
                <div class="col-md-4 text-end">
                    <a href="dashboard_full.php" class="btn btn-md btn-outline-light">
                        <i class="fas fa-arrow-left"></i> Back to Dashboard
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="container">
        <!-- Step Indicator -->
        <div class="step-indicator">
            <div class="step active" id="step1">
                <div class="circle">1</div>
                <div>Upload File</div>
                <div class="line"></div>
            </div>
            <div class="step" id="step2">
                <div class="circle">2</div>
                <div>Map Fields</div>
                <div class="line"></div>
            </div>
            <div class="step" id="step3">
                <div class="circle">3</div>
                <div>Import Results</div>
            </div>
        </div>

        <!-- Step 1: Upload Form -->
        <div class="card" id="step1Content">
            <div class="card-header" style="background-color: #18527b; color: white;">
                <h5 class="mb-0">Step 1: Upload Excel/CSV File</h5>
            </div>
            <div class="card-body">
                <?php if (!$phpspreadsheetLoaded): ?>
                    <div class="alert alert-warning">
                        <h5><i class="fas fa-exclamation-triangle me-2"></i>PhpSpreadsheet Library Required</h5>
                        <p>To import Excel files, you need to install the PhpSpreadsheet library.</p>
                        <ol>
                            <li>Make sure you have Composer installed</li>
                            <li>Run: <code>composer require phpoffice/phpspreadsheet</code></li>
                            <li>Refresh this page after installation</li>
                        </ol>
                        <?php if ($phpspreadsheetError): ?>
                            <div class="alert alert-danger mt-2">
                                <strong>Error:</strong> <?php echo htmlspecialchars($phpspreadsheetError); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <form id="uploadForm" enctype="multipart/form-data">
                    <div class="mb-3">
                        <label for="excel_file" class="form-label">
                            <i class="fas fa-file-excel me-1"></i>Select Excel/CSV File
                        </label>
                        <input type="file" class="form-control" id="excel_file" name="excel_file" accept=".xlsx,.xls,.csv" required>
                        <div class="form-text">
                            Supported formats: .xlsx, .xls, .csv (Max: 10MB). First row should contain column headers.
                        </div>
                    </div>

                    <div class="alert alert-info">
                        <h6><i class="fas fa-info-circle me-2"></i>File Requirements:</h6>
                        <ul class="mb-0">
                            <li>First row must contain column headers</li>
                            <li>Required fields: <strong>Item Name</strong> and <strong>Serial Number</strong></li>
                            <li>Optional fields will use default values if missing</li>
                            <li>Duplicate serial numbers will be updated (not duplicated)</li>
                        </ul>
                    </div>

                    <button type="submit" class="btn btn-primary" id="uploadBtn" <?php echo !$phpspreadsheetLoaded ? 'disabled' : ''; ?>>
                        <i class="fas fa-upload me-1"></i> Upload and Preview
                    </button>
                </form>
            </div>
        </div>

        <!-- Step 2: Field Mapping (Initially Hidden) -->
        <div class="card" id="step2Content" style="display: none;">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-map me-2"></i>Step 2: Map Excel Columns to Database Fields</h5>
            </div>
            <div class="card-body">
                <div id="previewContainer">
                    <!-- Preview will be loaded here via AJAX -->
                </div>

                <form id="mappingForm">
                    <div id="mappingFields">
                        <!-- Mapping fields will be loaded here via AJAX -->
                    </div>

                    <div class="alert alert-warning">
                        <h6><i class="fas fa-exclamation-triangle me-2"></i>Important Notes:</h6>
                        <ul class="mb-0">
                            <li>Required fields marked with * must be mapped</li>
                            <li>Unmapped fields will use default values</li>
                            <li>Items with existing serial numbers will be updated (not duplicated)</li>
                            <li>QR codes will be automatically generated for new items</li>
                            <li>Maximum 1000 rows will be processed</li>
                        </ul>
                    </div>

                    <div class="d-flex justify-content-between">
                        <button type="button" class="btn btn-outline-secondary" id="backToStep1">
                            <i class="fas fa-arrow-left me-1"></i> Back to Upload
                        </button>
                        <button type="submit" class="btn btn-primary" id="startImportBtn">
                            <i class="fas fa-play me-1"></i> Start Import
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Step 3: Results (Initially Hidden) -->
        <div class="card" id="step3Content" style="display: none;">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0"><i class="fas fa-check-circle me-2"></i>Import Completed</h5>
            </div>
            <div class="card-body">
                <div id="resultsContainer">
                    <!-- Results will be displayed here -->
                </div>

                <div class="mt-4">
                    <a href="import_items.php" class="btn btn-outline-primary">
                        <i class="fas fa-file-import me-1"></i> Import Another File
                    </a>
                    <a href="items.php" class="btn btn-outline-success ms-2">
                        <i class="fas fa-list me-1"></i> View All Items
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Progress Modal -->
    <div class="modal fade" id="progressModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-spinner fa-spin me-2"></i>Importing Items...</h5>
                </div>
                <div class="modal-body">
                    <div class="progress mb-3">
                        <div class="progress-bar progress-bar-striped progress-bar-animated" id="importProgress"
                            role="progressbar" style="width: 0%" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100">
                            0%
                        </div>
                    </div>

                    <div class="mb-3">
                        <div class="row">
                            <div class="col-4">
                                <div class="card bg-light">
                                    <div class="card-body text-center p-2">
                                        <h6 class="mb-0" id="processedCount">0</h6>
                                        <small class="text-muted">Processed</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="card bg-light">
                                    <div class="card-body text-center p-2">
                                        <h6 class="mb-0" id="successCount">0</h6>
                                        <small class="text-muted">Success</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="card bg-light">
                                    <div class="card-body text-center p-2">
                                        <h6 class="mb-0" id="totalCount">0</h6>
                                        <small class="text-muted">Total Rows</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <h6>Status:</h6>
                        <div class="alert alert-info mb-2 p-2" id="currentStatus">
                            Initializing import...
                        </div>
                    </div>

                    <div>
                        <h6>Live Log:</h6>
                        <div class="import-log" id="importLog">
                            <div class="log-info log-row">Import started...</div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" id="cancelImportBtn">
                        <i class="fas fa-times me-1"></i> Cancel Import
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-geWF76RCwLtnZ8qwWowPQNguL3RmwHVBC9FhGdlKrxdiJJigb/j/68SIy3Te4Bkz"
        crossorigin="anonymous"></script>

    <!-- jQuery FIRST -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

    <!-- Fixed Toastr JS (specific stable version) -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/2.1.4/toastr.min.js"></script>

    <script>
        // Simple HTML escaping function
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        document.addEventListener('DOMContentLoaded', function() {
            const uploadForm = document.getElementById('uploadForm');
            const mappingForm = document.getElementById('mappingForm');
            const progressModal = new bootstrap.Modal(document.getElementById('progressModal'));
            let importInterval;
            let importSessionId = null;

            // Initialize toastr with safe options
            if (typeof toastr !== 'undefined') {
                toastr.options = {
                    "closeButton": true,
                    "debug": false,
                    "newestOnTop": true,
                    "progressBar": true,
                    "positionClass": "toast-top-right",
                    "preventDuplicates": false,
                    "onclick": null,
                    "showDuration": "300",
                    "hideDuration": "1000",
                    "timeOut": "5000",
                    "extendedTimeOut": "1000",
                    "showEasing": "swing",
                    "hideEasing": "linear",
                    "showMethod": "fadeIn",
                    "hideMethod": "fadeOut"
                };
            }

            // Step 1: Upload File
            uploadForm.addEventListener('submit', function(e) {
                e.preventDefault();

                const fileInput = document.getElementById('excel_file');
                if (!fileInput.files.length) {
                    alert('Please select a file first');
                    return;
                }

                const formData = new FormData();
                formData.append('excel_file', fileInput.files[0]);

                const uploadBtn = document.getElementById('uploadBtn');
                const originalText = uploadBtn.innerHTML;
                uploadBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Uploading...';
                uploadBtn.disabled = true;

                fetch('import_items.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => {
                        if (!response.ok) {
                            throw new Error('Network response was not ok');
                        }
                        return response.json();
                    })
                    .then(data => {
                        if (data.success) {
                            // Store data in sessionStorage for mapping
                            sessionStorage.setItem('importData', JSON.stringify({
                                headers: data.headers,
                                preview: data.preview,
                                totalRows: data.total_rows
                            }));

                            // Show preview
                            showPreview(data.preview, data.headers);

                            // Generate mapping fields
                            generateMappingFields(data.headers);

                            // Update step indicator
                            document.getElementById('step1').classList.remove('active');
                            document.getElementById('step1').classList.add('completed');
                            document.getElementById('step2').classList.add('active');

                            // Show step 2 content
                            document.getElementById('step1Content').style.display = 'none';
                            document.getElementById('step2Content').style.display = 'block';

                            if (typeof toastr !== 'undefined') {
                                toastr.success('File uploaded successfully!', 'Success');
                            } else {
                                alert('File uploaded successfully!');
                            }
                        } else {
                            if (typeof toastr !== 'undefined') {
                                toastr.error(data.message, 'Upload Failed');
                            } else {
                                alert('Error: ' + data.message);
                            }
                        }
                    })
                    .catch(error => {
                        console.error('Upload error:', error);
                        if (typeof toastr !== 'undefined') {
                            toastr.error('Network error: ' + error.message, 'Error');
                        } else {
                            alert('Network error: ' + error.message);
                        }
                    })
                    .finally(() => {
                        uploadBtn.innerHTML = originalText;
                        uploadBtn.disabled = false;
                    });
            });

            // Back to step 1
            document.getElementById('backToStep1').addEventListener('click', function() {
                document.getElementById('step1').classList.add('active');
                document.getElementById('step1').classList.remove('completed');
                document.getElementById('step2').classList.remove('active');

                document.getElementById('step1Content').style.display = 'block';
                document.getElementById('step2Content').style.display = 'none';
            });

            // Step 2: Start Import
            mappingForm.addEventListener('submit', function(e) {
                e.preventDefault();

                // Validate required fields
                const requiredFields = ['item_name', 'serial_number'];
                let missingFields = [];

                requiredFields.forEach(field => {
                    const select = document.querySelector(`select[name="field_mapping[${field}]"]`);
                    if (!select || !select.value) {
                        missingFields.push(field.replace('_', ' '));
                    }
                });

                if (missingFields.length > 0) {
                    const errorMsg = 'Please map all required fields: ' + missingFields.join(', ');
                    if (typeof toastr !== 'undefined') {
                        toastr.error(errorMsg, 'Validation Error');
                    } else {
                        alert(errorMsg);
                    }
                    return;
                }

                // Get mapping data
                const formData = new FormData(mappingForm);
                const mapping = {};
                formData.forEach((value, key) => {
                    if (key.startsWith('field_mapping[')) {
                        const field = key.match(/field_mapping\[(.*?)\]/)[1];
                        mapping[field] = value;
                    }
                });

                // Show progress modal
                showProgressModal();

                // Start import via AJAX
                startImport(mapping);
            });

            function showPreview(previewData, headers) {
                let html = `
                    <div class="mb-4">
                        <h6><i class="fas fa-table me-1"></i>File Preview (First ${previewData.length} rows)</h6>
                        <div class="preview-table">
                            <table class="table table-sm table-bordered">
                                <thead class="table-light">
                                    <tr>
                                        <th>#</th>`;

                headers.forEach((header, index) => {
                    html += `<th>${escapeHtml(header)} (${String.fromCharCode(65 + index)})</th>`;
                });

                html += `</tr></thead><tbody>`;

                previewData.forEach((row, rowIndex) => {
                    html += `<tr><td class="text-muted">${rowIndex + 1}</td>`;
                    row.forEach(cell => {
                        html += `<td>${escapeHtml(cell)}</td>`;
                    });
                    html += `</tr>`;
                });

                html += `</tbody></table></div></div>`;

                document.getElementById('previewContainer').innerHTML = html;
            }

            function generateMappingFields(headers) {
                const fieldDefinitions = {
                    'item_name': {
                        label: 'Item Name',
                        required: true
                    },
                    'serial_number': {
                        label: 'Serial Number',
                        required: true
                    },
                    'category': {
                        label: 'Category',
                        required: false
                    },
                    'brand': {
                        label: 'Brand',
                        required: false
                    },
                    'model': {
                        label: 'Model',
                        required: false
                    },
                    'description': {
                        label: 'Description',
                        required: false
                    },
                    'quantity': {
                        label: 'Quantity',
                        required: false
                    },
                    'condition': {
                        label: 'Condition',
                        required: false
                    },
                    'status': {
                        label: 'Status',
                        required: false
                    },
                    'department': {
                        label: 'Department',
                        required: false
                    },
                    'stock_location': {
                        label: 'Stock Location',
                        required: false
                    },
                    'notes': {
                        label: 'Notes',
                        required: false
                    }
                };

                let html = `
                    <h6><i class="fas fa-cogs me-1"></i>Column Mapping</h6>
                    <p>Select which Excel column corresponds to each database field:</p>
                    <div class="row">`;

                Object.entries(fieldDefinitions).forEach(([field, definition]) => {
                    html += `
                        <div class="col-md-6 mb-3">
                            <label for="field_${field}" class="form-label ${definition.required ? 'required-field' : ''}">
                                ${definition.label}
                                ${definition.required ? '<span class="text-danger">*</span>' : ''}
                            </label>
                            <select class="form-select" id="field_${field}" name="field_mapping[${field}]">
                                <option value="">-- Select Column --</option>`;

                    headers.forEach((header, index) => {
                        const colLetter = String.fromCharCode(65 + index);
                        html += `<option value="${colLetter}">${colLetter}: ${escapeHtml(header)}</option>`;
                    });

                    html += `</select></div>`;
                });

                html += `</div>`;

                document.getElementById('mappingFields').innerHTML = html;

                // Auto-map columns based on header names
                autoMapColumns(headers);
            }

            function autoMapColumns(headers) {
                const columnMappings = {
                    'item_name': ['item', 'name', 'item name', 'equipment', 'product', 'title'],
                    'serial_number': ['serial', 'serial number', 'sn', 'serial no', 'serial#', 's/n'],
                    'category': ['category', 'type', 'classification', 'group', 'class'],
                    'brand': ['brand', 'manufacturer', 'maker', 'company', 'vendor'],
                    'model': ['model', 'model number', 'version', 'type', 'model no'],
                    'department': ['department', 'dept', 'division', 'team', 'section'],
                    'description': ['description', 'desc', 'details', 'info', 'specification'],
                    'condition': ['condition', 'state', 'status', 'quality', 'grade'],
                    'stock_location': ['location', 'stock location', 'storage', 'warehouse', 'place'],
                    'quantity': ['quantity', 'qty', 'amount', 'count', 'number', 'total'],
                    'status': ['status', 'availability', 'state', 'condition'],
                    'notes': ['notes', 'comments', 'remarks', 'info', 'additional']
                };

                headers.forEach((header, index) => {
                    const colLetter = String.fromCharCode(65 + index);
                    const headerLower = header.toLowerCase().trim();

                    Object.entries(columnMappings).forEach(([field, keywords]) => {
                        keywords.forEach(keyword => {
                            if (headerLower.includes(keyword) || keyword.includes(headerLower)) {
                                const select = document.getElementById(`field_${field}`);
                                if (select) {
                                    select.value = colLetter;
                                }
                            }
                        });
                    });
                });
            }

            function showProgressModal() {
                // Reset progress
                document.getElementById('importProgress').style.width = '0%';
                document.getElementById('importProgress').textContent = '0%';
                document.getElementById('importLog').innerHTML = '<div class="log-info log-row">Import started...</div>';
                document.getElementById('currentStatus').textContent = 'Initializing import...';
                document.getElementById('processedCount').textContent = '0';
                document.getElementById('successCount').textContent = '0';
                document.getElementById('totalCount').textContent = '0';

                // Show modal
                progressModal.show();
            }

            function startImport(mapping) {
                // Start import process
                fetch('import_process.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            action: 'start_import',
                            field_mapping: mapping
                        })
                    })
                    .then(response => {
                        if (!response.ok) {
                            throw new Error('Network response was not ok');
                        }
                        return response.json();
                    })
                    .then(data => {
                        if (data.success) {
                            importSessionId = data.session_id;
                            document.getElementById('totalCount').textContent = data.total_rows;

                            // Start polling for progress
                            pollImportProgress();
                        } else {
                            const errorMsg = data.message || 'Import failed to start';
                            if (typeof toastr !== 'undefined') {
                                toastr.error(errorMsg, 'Import Failed');
                            } else {
                                alert(errorMsg);
                            }
                            progressModal.hide();
                        }
                    })
                    .catch(error => {
                        console.error('Start import error:', error);
                        if (typeof toastr !== 'undefined') {
                            toastr.error('Network error: ' + error.message, 'Error');
                        } else {
                            alert('Network error: ' + error.message);
                        }
                        progressModal.hide();
                    });
            }

            function pollImportProgress() {
                importInterval = setInterval(() => {
                    fetch('import_process.php?action=get_progress&session_id=' + importSessionId)
                        .then(response => {
                            if (!response.ok) {
                                throw new Error('Network response was not ok');
                            }
                            return response.json();
                        })
                        .then(data => {
                            if (data.completed) {
                                // Import finished
                                clearInterval(importInterval);
                                showResults(data);
                            } else {
                                // Update progress
                                updateProgress(data);
                            }
                        })
                        .catch(error => {
                            console.error('Polling error:', error);
                            // Don't stop polling on network errors, just log
                        });
                }, 2000); // Poll every 2 seconds
            }

            function updateProgress(data) {
                const progress = (data.processed / data.total_rows) * 100;
                const progressBar = document.getElementById('importProgress');
                progressBar.style.width = `${progress}%`;
                progressBar.textContent = `${Math.round(progress)}%`;

                document.getElementById('processedCount').textContent = data.processed;
                document.getElementById('successCount').textContent = data.stats.success;
                document.getElementById('totalCount').textContent = data.total_rows;
                document.getElementById('currentStatus').textContent = data.current_status;

                // Update log
                if (data.log && data.log.length > 0) {
                    const logContainer = document.getElementById('importLog');
                    data.log.forEach(logEntry => {
                        const logClass = `log-${logEntry.type}`;
                        const logRow = document.createElement('div');
                        logRow.className = `${logClass} log-row`;
                        logRow.textContent = logEntry.message;
                        logContainer.appendChild(logRow);
                    });
                    // Keep only last 20 log entries
                    while (logContainer.children.length > 20) {
                        logContainer.removeChild(logContainer.firstChild);
                    }
                    logContainer.scrollTop = logContainer.scrollHeight;
                }
            }

            function showResults(data) {
                // Hide progress modal
                progressModal.hide();

                // Update step indicator
                document.getElementById('step2').classList.remove('active');
                document.getElementById('step2').classList.add('completed');
                document.getElementById('step3').classList.add('active');

                // Show results
                document.getElementById('step2Content').style.display = 'none';
                document.getElementById('step3Content').style.display = 'block';

                // Display results
                let resultsHtml = `
                    <div class="alert alert-success">
                        <h5><i class="fas fa-check-circle me-2"></i>Import completed successfully!</h5>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-body">
                                    <h6 class="card-title">Import Statistics</h6>
                                    <table class="table table-sm">
                                        <tr>
                                            <td><strong>New items added:</strong></td>
                                            <td>${data.stats.success}</td>
                                        </tr>
                                        <tr>
                                            <td><strong>Existing items updated:</strong></td>
                                            <td>${data.stats.updated}</td>
                                        </tr>
                                        <tr>
                                            <td><strong>QR codes generated:</strong></td>
                                            <td>${data.stats.qr_generated}</td>
                                        </tr>
                                        <tr>
                                            <td><strong>Total processed:</strong></td>
                                            <td>${data.stats.total}</td>
                                        </tr>
                                        <tr>
                                            <td><strong>Errors:</strong></td>
                                            <td>${data.stats.errors} rows</td>
                                        </tr>
                                        <tr>
                                            <td><strong>Skipped:</strong></td>
                                            <td>${data.stats.skipped} rows</td>
                                        </tr>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>`;

                if (data.stats.errors > 0 && data.errors && data.errors.length > 0) {
                    resultsHtml += `
                        <div class="mt-3">
                            <h6>Error Details (First 20 errors):</h6>
                            <div class="alert alert-danger small">
                                <ul class="mb-0">`;

                    data.errors.slice(0, 20).forEach(error => {
                        resultsHtml += `<li>${escapeHtml(error)}</li>`;
                    });

                    resultsHtml += `</ul></div></div>`;
                }

                document.getElementById('resultsContainer').innerHTML = resultsHtml;

                // Show success message
                if (typeof toastr !== 'undefined') {
                    toastr.success('Import completed successfully!', 'Success');
                } else {
                    alert('Import completed successfully!');
                }
            }

            // Cancel import
            document.getElementById('cancelImportBtn').addEventListener('click', function() {
                if (confirm('Are you sure you want to cancel the import?')) {
                    clearInterval(importInterval);

                    fetch('import_process.php?action=cancel_import&session_id=' + importSessionId)
                        .then(() => {
                            progressModal.hide();
                            if (typeof toastr !== 'undefined') {
                                toastr.warning('Import cancelled', 'Cancelled');
                            } else {
                                alert('Import cancelled');
                            }
                        })
                        .catch(error => {
                            console.error('Cancel error:', error);
                        });
                }
            });

            // Load data from sessionStorage if returning to step 2
            const storedData = sessionStorage.getItem('importData');
            if (storedData) {
                const data = JSON.parse(storedData);
                showPreview(data.preview, data.headers);
                generateMappingFields(data.headers);
            }
        });
    </script>
</body>

</html>