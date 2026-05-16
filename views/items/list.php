<?php
// views/items/list.php
$pageTitle = "Items List - aBility";
$showBreadcrumb = true;
$breadcrumbItems = [
    'Dashboard' => 'dashboard_full.php',
    'Items' => '',
    'List' => ''
];

require_once '../partials/header.php';
?>

<!-- DataTables CSS -->
<link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet" />
<link href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.bootstrap5.min.css" rel="stylesheet" />
<link href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.bootstrap5.min.css" rel="stylesheet" />

<div class="container-fluid">
    <!-- Page Header -->
    <div class="export-btn-group">
        <div class="dropdown">
            <button class="btn btn-primary dropdown-toggle" type="button" id="exportDropdown" data-bs-toggle="dropdown">
                <i class="fas fa-download me-2"></i>Export
            </button>
            <ul class="dropdown-menu">
                <li><a class="dropdown-item" href="#" onclick="exportToExcel()"><i class="fas fa-file-excel text-success me-2"></i>Export to Excel</a></li>
                <li><a class="dropdown-item" href="#" onclick="exportToPDF()"><i class="fas fa-file-pdf text-danger me-2"></i>Export to PDF</a></li>
                <li><a class="dropdown-item" href="#" onclick="exportToCSV()"><i class="fas fa-file-csv text-info me-2"></i>Export to CSV</a></li>
                <li>
                    <hr class="dropdown-divider">
                </li>
                <li><a class="dropdown-item" href="#" onclick="printTable()"><i class="fas fa-print me-2"></i>Print Table</a></li>
            </ul>
        </div>
        <a href="items.php?action=create" class="btn btn-success">
            <i class="fas fa-plus me-2"></i>Add New Equipment
        </a>
    </div>

    <!-- Success/Error Messages -->
    <?php if (isset($_GET['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($_GET['success']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-triangle me-2"></i><?php echo htmlspecialchars($_GET['error']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <!-- Search and Controls Bar -->
    <div class="card shadow mb-4">
        <div class="card-body">
            <div class="row align-items-center">
                <div class="col-md-4 mb-3 mb-md-0">
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-search"></i></span>
                        <input type="text" class="form-control" id="globalSearch" placeholder="Search by name, serial, category...">
                        <button class="btn btn-primary" type="button" onclick="applyFilters()">Search</button>
                    </div>
                </div>
                <div class="col-md-3 mb-3 mb-md-0">
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-list-ol"></i></span>
                        <select class="form-select" id="pageLength" onchange="changePageLength()">
                            <option value="10">10 per page</option>
                            <option value="25">25 per page</option>
                            <option value="50">50 per page</option>
                            <option value="100">100 per page</option>
                            <option value="-1">All items</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-5">
                    <div class="d-flex justify-content-end gap-2">
                        <button type="button" class="btn btn-outline-secondary" onclick="refreshTable()" title="Refresh">
                            <i class="fas fa-sync-alt"></i>
                        </button>
                        <button type="button" class="btn btn-outline-primary" onclick="toggleFilters()">
                            <i class="fas fa-filter"></i> Advanced Filters
                        </button>
                        <div class="btn-group" role="group">
                            <button type="button" class="btn btn-outline-info" onclick="showQuickStats()" title="Quick Stats">
                                <i class="fas fa-chart-bar"></i>
                            </button>
                            <button type="button" class="btn btn-outline-success" onclick="showQRScanner()" title="Scan QR Code">
                                <i class="fas fa-qrcode"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Advanced Filter Row -->
    <div class="card shadow mb-4" id="advancedFilters" style="display: none;">
        <div class="card-header">
            <h6 class="m-0 font-weight-bold text-primary">
                <i class="fas fa-sliders-h me-2"></i>Advanced Filters
            </h6>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label small">Category</label>
                    <select class="form-select" id="filterCategory">
                        <option value="">All Categories</option>
                        <?php
                        $conn = new mysqli("localhost", "root", "", "ability_db");
                        if (!$conn->connect_error) {
                            $result = $conn->query("SELECT DISTINCT category FROM items WHERE category IS NOT NULL AND category != '' ORDER BY category");
                            while ($row = $result->fetch_assoc()) {
                                echo '<option value="' . htmlspecialchars($row['category']) . '">' . htmlspecialchars($row['category']) . '</option>';
                            }
                            $conn->close();
                        }
                        ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small">Status</label>
                    <select class="form-select" id="filterStatus">
                        <option value="">All Status</option>
                        <option value="available">Available</option>
                        <option value="in_use">In Use</option>
                        <option value="maintenance">Maintenance</option>
                        <option value="reserved">Reserved</option>
                        <option value="lost">Lost</option>
                        <option value="damaged">Damaged</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small">Location</label>
                    <select class="form-select" id="filterLocation">
                        <option value="">All Locations</option>
                        <?php
                        $conn2 = new mysqli("localhost", "root", "", "ability_db");
                        if (!$conn2->connect_error) {
                            $result = $conn2->query("SELECT DISTINCT stock_location FROM items WHERE stock_location IS NOT NULL AND stock_location != '' ORDER BY stock_location");
                            while ($row = $result->fetch_assoc()) {
                                echo '<option value="' . htmlspecialchars($row['stock_location']) . '">' . htmlspecialchars($row['stock_location']) . '</option>';
                            }
                            $conn2->close();
                        }
                        ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small">Condition</label>
                    <select class="form-select" id="filterCondition">
                        <option value="">All Conditions</option>
                        <option value="new">New</option>
                        <option value="good">Good</option>
                        <option value="fair">Fair</option>
                        <option value="poor">Poor</option>
                        <option value="repair">Needs Repair</option>
                    </select>
                </div>
                <div class="col-12">
                    <div class="d-flex justify-content-end gap-2">
                        <button class="btn btn-sm btn-primary" onclick="applyFilters()">
                            <i class="fas fa-check me-1"></i> Apply Filters
                        </button>
                        <button class="btn btn-sm btn-secondary" onclick="resetFilters()">
                            <i class="fas fa-times me-1"></i> Clear All
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Card -->
    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex justify-content-between align-items-center">
            <h6 class="m-0 font-weight-bold text-primary">
                <i class="fas fa-list me-2"></i>Equipment List
                <span class="badge bg-primary ms-2" id="totalCountText">Loading...</span>
            </h6>
            <div class="btn-group" role="group">
                <button type="button" class="btn btn-sm btn-outline-success" onclick="checkOutMultiple()" id="bulkCheckoutBtn" disabled>
                    <i class="fas fa-sign-out-alt me-1"></i> Bulk Check-out
                </button>
                <button type="button" class="btn btn-sm btn-outline-danger" onclick="deleteMultiple()" id="bulkDeleteBtn" disabled>
                    <i class="fas fa-trash me-1"></i> Delete Selected
                </button>
            </div>
        </div>

        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-bordered align-middle w-100" id="itemsTable">
                    <thead>
                        <tr>
                            <th width="40" class="text-center">
                                <input type="checkbox" id="selectAll" class="form-check-input" onchange="toggleSelectAll(this)">
                            </th>
                            <th width="60">#</th>
                            <th>ID</th>
                            <th>Item Name</th>
                            <th>Serial Number</th>
                            <th>Category</th>
                            <th>Department</th>
                            <th>Status</th>
                            <th>Location</th>
                            <th>Quantity</th>
                            <th width="120">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="tableBody">
                        <tr>
                            <td colspan="10" class="text-center py-4">
                                <div class="spinner-border text-primary" role="status">
                                    <span class="visually-hidden">Loading...</span>
                                </div>
                                <p class="mt-2">Loading equipment data...</p>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Stats Modal -->
<div class="modal fade" id="statsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-chart-bar me-2"></i>Equipment Statistics</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="statsContent">
                Loading statistics...
            </div>
        </div>
    </div>
</div>

<!-- Required Scripts -->
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>
<script src="https://cdn.datatables.net/responsive/2.5.0/js/responsive.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.bootstrap5.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/pdfmake.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/vfs_fonts.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.print.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.25/jspdf.plugin.autotable.min.js"></script>

<style>
    /* Custom styles */
    .export-btn-group {
        display: flex;
        gap: 10px;
        margin-bottom: 20px;
        align-items: center;
        justify-content: space-between;
    }

    .status-badge {
        padding: 4px 8px;
        border-radius: 12px;
        font-size: 0.75rem;
        font-weight: 600;
        text-transform: capitalize;
        display: inline-block;
    }

    .status-available {
        background: #d4edda;
        color: #155724;
    }

    .status-in_use {
        background: #cce5ff;
        color: #004085;
    }

    .status-maintenance {
        background: #fff3cd;
        color: #856404;
    }

    .status-reserved {
        background: #e2d5f1;
        color: #563d7c;
    }

    .status-lost,
    .status-damaged {
        background: #f8d7da;
        color: #721c24;
    }

    .selected-row {
        background-color: #e8f4fd !important;
    }

    .action-buttons {
        display: flex;
        gap: 5px;
        flex-wrap: nowrap;
    }

    .action-buttons .btn {
        padding: 4px 8px;
        font-size: 0.875rem;
        min-width: 32px;
    }

    /* DataTables customizations */
    .dataTables_wrapper .dataTables_length,
    .dataTables_wrapper .dataTables_filter {
        margin-bottom: 1rem;
    }

    .dataTables_wrapper .dataTables_info {
        padding-top: 0.75rem;
    }

    .dataTables_wrapper .dataTables_paginate {
        padding-top: 0.5rem;
    }

    .table th {
        background-color: #f8f9fc;
        font-weight: 600;
    }

    /* Responsive */
    @media (max-width: 768px) {
        .action-buttons {
            flex-direction: column;
        }

        .action-buttons .btn {
            width: 100%;
        }

        .export-btn-group {
            flex-direction: column;
            align-items: flex-start;
        }

        .export-btn-group .dropdown,
        .export-btn-group .btn-success {
            width: 100%;
        }
    }
</style>

<script>
    const BASE_URL = '<?php echo BASE_URL ?? "/ability_app_main/"; ?>';
    const API_BASE = BASE_URL + 'api/';

    // State
    let currentPage = 1;
    let itemsPerPage = 10;
    let totalItems = 0;
    let currentFilters = {};
    let selectedItems = new Set();
    let statsModal = null;
    let sortField = 'id';
    let sortDirection = 'desc';
    let isLoading = false;

    // Initialize DataTable
    let dataTable;

    document.addEventListener('DOMContentLoaded', function() {
        // Initialize Bootstrap modal
        const modalElement = document.getElementById('statsModal');
        if (modalElement) {
            statsModal = new bootstrap.Modal(modalElement);
        }

        // Initialize DataTable
        dataTable = $('#itemsTable').DataTable({
            processing: true,
            serverSide: true,
            responsive: true,
            pageLength: 10,
            lengthMenu: [
                [10, 25, 50, 100, -1],
                [10, 25, 50, 100, "All"]
            ],
            ajax: {
                url: BASE_URL + 'api/items/list.php',
                type: 'GET',
                data: function(d) {
                    // Add custom filters
                    d.page = Math.ceil(d.start / d.length) + 1;
                    d.limit = d.length;
                    d.sort = d.columns[d.order[0].column].data;
                    d.order = d.order[0].dir;
                    d.search = d.search.value;

                    // Add advanced filters
                    Object.assign(d, currentFilters);

                    return d;
                },
                dataSrc: function(json) {
                    totalItems = json.pagination.totalItems;
                    $('#totalCountText').text(`(${totalItems} items)`);
                    return json.items;
                }
            },
            columns: [{
                    data: null,
                    orderable: false,
                    searchable: false,
                    render: function(data, type, row) {
                        const checked = selectedItems.has(row.id.toString()) ? 'checked' : '';
                        return `<input type="checkbox" class="form-check-input item-checkbox" 
                        value="${row.id}" onchange="toggleSelectItem(this, ${row.id})" ${checked}>`;
                    }
                },
                {
                    data: null,
                    orderable: false,
                    searchable: false,
                    render: function(data, type, row, meta) {
                        return `<span class="badge bg-secondary">#${meta.row + meta.settings._iDisplayStart + 1}</span>`;
                    }
                },
                {
                    data: 'id',
                    render: function(data) {
                        return `<span class="badge bg-dark">${data}</span>`;
                    }
                },
                {
                    data: 'item_name',
                    render: function(data, type, row) {
                        let html = `<strong>${escapeHTML(data || 'Unnamed')}</strong>`;

                        // Only show description if it exists and is not null/undefined
                        if (row.description && row.description !== 'null' && row.description !== 'undefined' && row.description.trim() !== '') {
                            html += `<br><small class="text-muted">${escapeHTML(row.description.substring(0, 50))}${row.description.length > 50 ? '...' : ''}</small>`;
                        }

                        if (row.brand || row.model) {
                            html += `<br><small class="text-muted">${escapeHTML(row.brand || '')} ${escapeHTML(row.model || '')}</small>`;
                        }
                        if (row.brand_model) {
                            html += `<br><small class="text-muted">${escapeHTML(row.brand_model)}</small>`;
                        }
                        return html;
                    }
                },
                {
                    data: 'serial_number',
                    render: function(data) {
                        return `<code>${escapeHTML(data || 'N/A')}</code>`;
                    }
                },
                {
                    data: 'category_name',
                    render: function(data, type, row) {
                        return escapeHTML(data || row.category || 'N/A');
                    }
                },
                {
                    data: 'department_name',
                    render: function(data, type, row) {
                        return `<span class="badge bg-info text-dark">${escapeHTML(data || row.department || 'N/A')}</span>`;
                    }
                },
                {
                    data: 'status',
                    render: function(data) {
                        const statusClass = `status-${(data || 'available').toLowerCase().replace('_', '-')}`;
                        return `<span class="status-badge ${statusClass}">${escapeHTML(getStatusLabel(data))}</span>`;
                    }
                },
                {
                    data: 'stock_location',
                    render: function(data, type, row) {
                        let html = escapeHTML(data || 'Not Set');
                        if (row.current_location) {
                            html += `<br><small class="text-muted">Current: ${escapeHTML(row.current_location)}</small>`;
                        }
                        return html;
                    }
                },
                {
                    data: 'quantity',
                    render: function(data, type, row) {
                        let html = `<span class="badge bg-info">${data || 1}</span>`;
                        if (row.condition) {
                            html += `<br><small class="text-muted">${escapeHTML(row.condition)}</small>`;
                        }
                        return html;
                    }
                },
                {
                    data: null,
                    orderable: false,
                    searchable: false,
                    render: function(data, type, row) {
                        return `
                <div class="action-buttons">
                    <a href="items.php?action=view&id=${row.id}" class="btn btn-sm btn-info" title="View Details">
                        <i class="fas fa-eye"></i>
                    </a>
                    <a href="items.php?action=edit&id=${row.id}" class="btn btn-sm btn-warning" title="Edit">
                        <i class="fas fa-edit"></i>
                    </a>
                    <button class="btn btn-sm btn-danger" onclick="confirmDelete(${row.id}, '${escapeHTML(row.item_name || 'this item')}')" title="Delete">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            `;
                    }
                }
            ],
            order: [
                [2, 'desc']
            ],
            language: {
                processing: '<i class="fas fa-spinner fa-spin fa-2x"></i>',
                zeroRecords: 'No matching records found',
                info: 'Showing _START_ to _END_ of _TOTAL_ items',
                infoEmpty: 'Showing 0 to 0 of 0 items',
                infoFiltered: '(filtered from _MAX_ total items)'
            },
            drawCallback: function(settings) {
                // Update selected rows styling
                selectedItems.forEach(id => {
                    $(`#row-${id}`).addClass('selected-row');
                });
            }
        });

        // Set up event listeners
        setupEventListeners();
    });

    function setupEventListeners() {
        // Enter key in global search
        document.getElementById('globalSearch').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') applyFilters();
        });

        // Filter changes
        ['filterCategory', 'filterStatus', 'filterLocation', 'filterCondition'].forEach(id => {
            document.getElementById(id).addEventListener('change', function() {
                if (this.value) applyFilters();
            });
        });
    }

    function applyFilters() {
        currentFilters = {
            search: document.getElementById('globalSearch').value,
            category: document.getElementById('filterCategory').value,
            status: document.getElementById('filterStatus').value,
            location: document.getElementById('filterLocation').value,
            condition: document.getElementById('filterCondition').value
        };

        // Remove empty filters
        Object.keys(currentFilters).forEach(key => {
            if (!currentFilters[key]) delete currentFilters[key];
        });

        dataTable.ajax.reload();
        showNotification('info', 'Filters applied', 2000);
    }

    function resetFilters() {
        document.getElementById('globalSearch').value = '';
        document.getElementById('filterCategory').value = '';
        document.getElementById('filterStatus').value = '';
        document.getElementById('filterLocation').value = '';
        document.getElementById('filterCondition').value = '';

        currentFilters = {};
        dataTable.ajax.reload();
        showNotification('info', 'All filters cleared', 2000);
    }

    function toggleFilters() {
        const filterRow = document.getElementById('advancedFilters');
        const button = document.querySelector('button[onclick="toggleFilters()"]');

        if (filterRow.style.display === 'none' || filterRow.style.display === '') {
            filterRow.style.display = 'block';
            button.innerHTML = '<i class="fas fa-filter"></i> Hide Filters';
        } else {
            filterRow.style.display = 'none';
            button.innerHTML = '<i class="fas fa-filter"></i> Advanced Filters';
        }
    }

    function changePageLength() {
        const length = parseInt(document.getElementById('pageLength').value);
        dataTable.page.len(length).draw();
    }

    function refreshTable() {
        dataTable.ajax.reload();
        showNotification('info', 'Table refreshed', 2000);
    }

    function toggleSelectAll(checkbox) {
        const checkboxes = document.querySelectorAll('.item-checkbox');
        checkboxes.forEach(cb => {
            cb.checked = checkbox.checked;
            const itemId = cb.value;
            if (checkbox.checked) {
                selectedItems.add(itemId);
                $(`#row-${itemId}`).addClass('selected-row');
            } else {
                selectedItems.delete(itemId);
                $(`#row-${itemId}`).removeClass('selected-row');
            }
        });
        updateBulkButtons();
    }

    function toggleSelectItem(checkbox, itemId) {
        if (checkbox.checked) {
            selectedItems.add(itemId.toString());
            $(`#row-${itemId}`).addClass('selected-row');
        } else {
            selectedItems.delete(itemId.toString());
            $(`#row-${itemId}`).removeClass('selected-row');
            document.getElementById('selectAll').checked = false;
        }
        updateBulkButtons();
    }

    function updateBulkButtons() {
        const count = selectedItems.size;
        $('#bulkCheckoutBtn, #bulkDeleteBtn').prop('disabled', count === 0);
    }

    async function checkOutMultiple() {
        if (selectedItems.size === 0) return;

        if (confirm(`Check out ${selectedItems.size} selected item(s)?`)) {
            try {
                const response = await fetch(API_BASE + 'items/bulk_update.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        ids: Array.from(selectedItems),
                        status: 'in_use'
                    })
                });

                const data = await response.json();

                if (data.success) {
                    showNotification('success', `Successfully checked out ${selectedItems.size} item(s)`);
                    selectedItems.clear();
                    $('#selectAll').prop('checked', false);
                    dataTable.ajax.reload();
                } else {
                    showNotification('error', data.message || 'Failed to check out items');
                }
            } catch (error) {
                showNotification('error', 'Network error');
            }
        }
    }

    async function deleteMultiple() {
        if (selectedItems.size === 0) return;

        if (confirm(`Delete ${selectedItems.size} selected item(s)?\n\nThis action cannot be undone.`)) {
            try {
                const response = await fetch(API_BASE + 'items/bulk_delete.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        ids: Array.from(selectedItems)
                    })
                });

                const data = await response.json();

                if (data.success) {
                    showNotification('success', `Successfully deleted ${selectedItems.size} item(s)`);
                    selectedItems.clear();
                    $('#selectAll').prop('checked', false);
                    dataTable.ajax.reload();
                } else {
                    showNotification('error', data.message || 'Failed to delete items');
                }
            } catch (error) {
                showNotification('error', 'Network error');
            }
        }
    }

    function confirmDelete(id, name) {
        if (confirm(`Delete "${name}"?`)) {
            deleteItem(id);
        }
    }

    async function deleteItem(id) {
        try {
            const response = await fetch(API_BASE + 'items/delete.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    id
                })
            });

            const data = await response.json();

            if (data.success) {
                showNotification('success', 'Item deleted successfully');
                selectedItems.delete(id.toString());
                dataTable.ajax.reload();
            } else {
                showNotification('error', data.message || 'Failed to delete');
            }
        } catch (error) {
            showNotification('error', 'Network error');
        }
    }

    // Export functions
    function exportToExcel() {
        window.open(API_BASE + 'items/export.php?format=excel&' + $.param(currentFilters), '_blank');
    }

    function exportToPDF() {
        window.open(API_BASE + 'items/export.php?format=pdf&' + $.param(currentFilters), '_blank');
    }

    function exportToCSV() {
        window.open(API_BASE + 'items/export.php?format=csv&' + $.param(currentFilters), '_blank');
    }

    function printTable() {
        window.print();
    }

    function showQuickStats() {
        showNotification('info', 'Loading statistics...');

        fetch(API_BASE + 'items/stats.php')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    let html = '<div class="row">';
                    const stats = data.stats;

                    html += `
                        <div class="col-md-3">
                            <div class="stat-card stat-total">
                                <h5>${stats.total || 0}</h5>
                                <p>Total Items</p>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stat-card stat-available">
                                <h5>${stats.available || 0}</h5>
                                <p>Available</p>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stat-card stat-inuse">
                                <h5>${stats.in_use || 0}</h5>
                                <p>In Use</p>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stat-card stat-maintenance">
                                <h5>${stats.maintenance || 0}</h5>
                                <p>Maintenance</p>
                            </div>
                        </div>
                    `;

                    if (stats.categories && stats.categories.length) {
                        html += '<div class="col-12 mt-4"><h6>Category Breakdown</h6><table class="table table-sm">';
                        stats.categories.forEach(cat => {
                            html += `<tr><td>${escapeHTML(cat.category)}</td><td>${cat.count}</td></tr>`;
                        });
                        html += '</table></div>';
                    }

                    html += '</div>';
                    $('#statsContent').html(html);
                    statsModal.show();
                }
            })
            .catch(() => showNotification('error', 'Failed to load statistics'));
    }

    function showQRScanner() {
        window.location.href = BASE_URL + 'scan.php';
    }

    function getStatusLabel(status) {
        const map = {
            'available': 'Available',
            'in_use': 'In Use',
            'maintenance': 'Maintenance',
            'reserved': 'Reserved',
            'lost': 'Lost',
            'damaged': 'Damaged'
        };
        return map[status] || status || 'Available';
    }

    function escapeHTML(str) {
        if (!str) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    let activeNotification = null;

    function showNotification(type, message, duration = 5000) {
        if (activeNotification) {
            activeNotification.remove();
        }

        const notification = document.createElement('div');
        notification.className = `alert alert-${type} alert-dismissible fade show alert-notification`;
        notification.style.cssText = 'position: fixed; top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
        notification.innerHTML = `
            <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-triangle' : 'info-circle'} me-2"></i>
            ${escapeHTML(message)}
            <button type="button" class="btn-close" onclick="this.parentElement.remove()"></button>
        `;

        document.body.appendChild(notification);
        activeNotification = notification;

        if (duration > 0) {
            setTimeout(() => {
                if (activeNotification) {
                    activeNotification.remove();
                    activeNotification = null;
                }
            }, duration);
        }
    }
</script>

<?php require_once '../partials/footer.php'; ?>