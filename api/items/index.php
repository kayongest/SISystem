<?php
// items/index.php
require_once '/../../bootstrap.php';

// Check authentication
if (!isLoggedIn()) {
    header('Location: ../login.php');
    exit();
}

require_once '../includes/db_connect.php';
require_once '../includes/functions.php';

$pageTitle = "Equipment Management - aBility";
$showBreadcrumb = true;
$breadcrumbItems = [
    'Dashboard' => 'dashboard_full.php',
    'Equipment' => ''
];

require_once 'views/partials/header.php';

// Check for success/error messages
$success = $_GET['success'] ?? '';
$error = $_GET['error'] ?? '';
?>

<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">
            <i class="fas fa-tools me-2"></i>Equipment Management
        </h1>
        <div>
            <button class="btn btn-primary" onclick="exportToExcel()">
                <i class="fas fa-file-excel me-2"></i>Export
            </button>
            <a href="api/items/create.php" class="btn btn-success">
                <i class="fas fa-plus me-2"></i>Add New Equipment
            </a>
        </div>
    </div>

    <!-- Success/Error Messages -->
    <?php if ($success): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($success); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-triangle me-2"></i><?php echo htmlspecialchars($error); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <!-- Main Card -->
    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex justify-content-between align-items-center">
            <h6 class="m-0 font-weight-bold text-primary">
                <i class="fas fa-list me-2"></i>All Equipment
            </h6>
            <div class="btn-group" role="group">
                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="refreshTable()">
                    <i class="fas fa-sync-alt"></i>
                </button>
                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="toggleFilters()">
                    <i class="fas fa-filter"></i> Filters
                </button>
            </div>
        </div>

        <!-- Filter Row -->
        <div class="card-body border-bottom" id="filterRow" style="display: none;">
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label small">Category</label>
                    <select class="form-control form-control-sm" id="filterCategory">
                        <option value="">All Categories</option>
                        <option value="Electronics">Electronics</option>
                        <option value="Tools">Tools</option>
                        <option value="Vehicles">Vehicles</option>
                        <option value="Furniture">Furniture</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small">Status</label>
                    <select class="form-control form-control-sm" id="filterStatus">
                        <option value="">All Status</option>
                        <option value="available">Available</option>
                        <option value="in_use">In Use</option>
                        <option value="maintenance">Maintenance</option>
                        <option value="reserved">Reserved</option>
                        <option value="lost">Lost</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small">Location</label>
                    <select class="form-control form-control-sm" id="filterLocation">
                        <option value="">All Locations</option>
                        <option value="Warehouse A">Warehouse A</option>
                        <option value="Warehouse B">Warehouse B</option>
                        <option value="Site Office">Site Office</option>
                        <option value="Stock">Stock</option>
                    </select>
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <button class="btn btn-sm btn-primary me-2" onclick="applyFilters()">
                        <i class="fas fa-search me-1"></i> Apply
                    </button>
                    <button class="btn btn-sm btn-secondary" onclick="resetFilters()">
                        <i class="fas fa-times me-1"></i> Clear
                    </button>
                </div>
            </div>
        </div>

        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-hover" id="itemsTable" width="100%" cellspacing="0">
                    <thead class="table-light">
                        <tr>
                            <th width="40">
                                <input type="checkbox" id="selectAll" class="form-check-input">
                            </th>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Serial Number</th>
                            <th>Category</th>
                            <th>Status</th>
                            <th>Location</th>
                            <th>Value</th>
                            <th>Last Updated</th>
                            <th width="120">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="tableBody">
                        <!-- Data will be loaded via AJAX -->
                        <tr>
                            <td colspan="10" class="text-center py-5">
                                <div class="spinner-border text-primary" role="status">
                                    <span class="visually-hidden">Loading...</span>
                                </div>
                                <p class="mt-2">Loading equipment data...</p>
                            </td>
                        </tr>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="10">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div class="small text-muted">
                                        Showing <span id="currentCount">0</span> of <span id="totalCount">0</span> items
                                    </div>
                                    <nav>
                                        <ul class="pagination pagination-sm mb-0" id="pagination">
                                            <!-- Pagination will be added dynamically -->
                                        </ul>
                                    </nav>
                                </div>
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>

    <!-- Bulk Actions Modal -->
    <div class="modal fade" id="bulkActionModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Bulk Actions</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Action</label>
                        <select class="form-select" id="bulkAction">
                            <option value="">Select Action</option>
                            <option value="check_in">Check In</option>
                            <option value="check_out">Check Out</option>
                            <option value="maintenance">Mark for Maintenance</option>
                            <option value="delete">Delete Selected</option>
                        </select>
                    </div>
                    <div id="actionDetails" style="display: none;">
                        <!-- Additional fields will appear based on action -->
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="performBulkAction()">Confirm</button>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    /* Custom styles for the datatable */
    #itemsTable th {
        font-weight: 600;
        background-color: #f8f9fc;
        border-bottom: 2px solid #e3e6f0;
    }

    .status-badge {
        padding: 4px 8px;
        border-radius: 12px;
        font-size: 0.75rem;
        font-weight: 600;
    }

    .badge-available {
        background-color: #d1e7dd;
        color: #0f5132;
    }

    .badge-in_use {
        background-color: #cfe2ff;
        color: #084298;
    }

    .badge-maintenance {
        background-color: #fff3cd;
        color: #664d03;
    }

    .badge-reserved {
        background-color: #d8f2ff;
        color: #055160;
    }

    .badge-lost {
        background-color: #f8d7da;
        color: #842029;
    }

    .action-buttons {
        display: flex;
        gap: 5px;
    }

    .action-buttons .btn {
        padding: 4px 8px;
        font-size: 0.875rem;
    }

    /* Hover effect */
    .table-hover tbody tr:hover {
        background-color: #f8f9fa;
    }

    /* Fixed column widths */
    .table th:nth-child(1) {
        width: 40px;
    }

    .table th:nth-child(2) {
        width: 60px;
    }

    .table th:nth-child(10) {
        width: 120px;
    }

    /* Selected row style */
    .table-selected {
        background-color: #e8f4fd !important;
    }
</style>

<script>
    // Global variables
    let currentPage = 1;
    const itemsPerPage = 10;
    let totalItems = 0;
    let currentFilters = {};
    let selectedItems = new Set();

    // Initialize when page loads
    document.addEventListener('DOMContentLoaded', function() {
        loadItems();
        setupEventListeners();
    });

    // Setup event listeners
    function setupEventListeners() {
        // Select all checkbox
        document.getElementById('selectAll').addEventListener('change', function(e) {
            const checkboxes = document.querySelectorAll('.item-checkbox');
            checkboxes.forEach(checkbox => {
                checkbox.checked = e.target.checked;
                const itemId = checkbox.value;
                if (e.target.checked) {
                    selectedItems.add(itemId);
                } else {
                    selectedItems.delete(itemId);
                }
            });
            updateBulkActionButton();
        });
    }

    // Load items from API
    async function loadItems(page = 1) {
        currentPage = page;

        // Show loading
        const tableBody = document.getElementById('tableBody');
        tableBody.innerHTML = `
        <tr>
            <td colspan="10" class="text-center py-5">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <p class="mt-2">Loading equipment data...</p>
            </td>
        </tr>
    `;

        try {
            // Build query string with filters
            const queryParams = new URLSearchParams({
                page: page,
                limit: itemsPerPage,
                ...currentFilters
            });

            const response = await fetch(`api/items/list.php?${queryParams}`);
            const data = await response.json();

            if (data.success) {
                renderItems(data.items);
                updatePagination(data.total, data.total_pages);
                updateCounts(data.count, data.total);
            } else {
                showError('Failed to load items: ' + (data.message || 'Unknown error'));
            }
        } catch (error) {
            console.error('Error loading items:', error);
            showError('Network error. Please check your connection.');
        }
    }

    // Render items in table
    function renderItems(items) {
        const tableBody = document.getElementById('tableBody');

        if (!items || items.length === 0) {
            tableBody.innerHTML = `
            <tr>
                <td colspan="10" class="text-center py-5">
                    <i class="fas fa-box-open fa-3x text-muted mb-3"></i>
                    <h5>No equipment found</h5>
                    <p class="text-muted">No equipment records match your criteria</p>
                    <a href="views/items/create.php" class="btn btn-primary mt-2">
                        <i class="fas fa-plus me-2"></i>Add New Equipment
                    </a>
                </td>
            </tr>
        `;
            return;
        }

        let html = '';

        items.forEach(item => {
            const statusClass = `badge-${item.status}`;
            const formattedValue = item.value ? `R${parseFloat(item.value).toFixed(2)}` : 'N/A';
            const lastUpdated = new Date(item.updated_at).toLocaleDateString();

            html += `
        <tr id="row-${item.id}" class="${selectedItems.has(item.id.toString()) ? 'table-selected' : ''}">
            <td>
                <input type="checkbox" class="form-check-input item-checkbox" 
                       value="${item.id}" 
                       onchange="toggleSelectItem(this)">
            </td>
            <td><span class="badge bg-secondary">${item.id}</span></td>
            <td>
                <strong>${escapeHtml(item.name)}</strong>
                ${item.description ? `<br><small class="text-muted">${escapeHtml(item.description.substring(0, 50))}...</small>` : ''}
            </td>
            <td><code>${escapeHtml(item.serial_number)}</code></td>
            <td>${escapeHtml(item.category)}</td>
            <td>
                <span class="status-badge ${statusClass}">
                    ${getStatusLabel(item.status)}
                </span>
            </td>
            <td>${escapeHtml(item.stock_location)}</td>
            <td><span class="fw-bold">${formattedValue}</span></td>
            <td><small class="text-muted">${lastUpdated}</small></td>
            <td>
                <div class="action-buttons">
                    <a href="items.php?action=view&id=${item.id}" class="btn btn-sm btn-info" 
                       title="View Details">
                        <i class="fas fa-eye"></i>
                    </a>
                    <a href="items/edit.php?id=${item.id}" class="btn btn-sm btn-warning" 
                       title="Edit">
                        <i class="fas fa-edit"></i>
                    </a>
                    <button class="btn btn-sm btn-danger" 
                            onclick="confirmDelete(${item.id}, '${escapeHtml(item.name)}')"
                            title="Delete">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </td>
        </tr>
        `;
        });

        tableBody.innerHTML = html;
    }

    // Toggle item selection
    function toggleSelectItem(checkbox) {
        const itemId = checkbox.value;

        if (checkbox.checked) {
            selectedItems.add(itemId);
            document.getElementById(`row-${itemId}`).classList.add('table-selected');
        } else {
            selectedItems.delete(itemId);
            document.getElementById(`row-${itemId}`).classList.remove('table-selected');
            document.getElementById('selectAll').checked = false;
        }

        updateBulkActionButton();
    }

    // Update bulk action button state
    function updateBulkActionButton() {
        const bulkBtn = document.getElementById('bulkActionBtn');
        if (bulkBtn) {
            bulkBtn.disabled = selectedItems.size === 0;
        }
    }

    // Update counts display
    function updateCounts(current, total) {
        document.getElementById('currentCount').textContent = current;
        document.getElementById('totalCount').textContent = total;
    }

    // Update pagination
    function updatePagination(totalItems, totalPages) {
        const pagination = document.getElementById('pagination');

        if (totalPages <= 1) {
            pagination.innerHTML = '';
            return;
        }

        let html = '';

        // Previous button
        html += `
    <li class="page-item ${currentPage === 1 ? 'disabled' : ''}">
        <a class="page-link" href="#" onclick="loadItems(${currentPage - 1})">
            <i class="fas fa-chevron-left"></i>
        </a>
    </li>
    `;

        // Page numbers
        for (let i = 1; i <= totalPages; i++) {
            if (i === currentPage) {
                html += `<li class="page-item active"><span class="page-link">${i}</span></li>`;
            } else {
                html += `<li class="page-item"><a class="page-link" href="#" onclick="loadItems(${i})">${i}</a></li>`;
            }
        }

        // Next button
        html += `
    <li class="page-item ${currentPage === totalPages ? 'disabled' : ''}">
        <a class="page-link" href="#" onclick="loadItems(${currentPage + 1})">
            <i class="fas fa-chevron-right"></i>
        </a>
    </li>
    `;

        pagination.innerHTML = html;
    }

    // Toggle filters visibility
    function toggleFilters() {
        const filterRow = document.getElementById('filterRow');
        filterRow.style.display = filterRow.style.display === 'none' ? 'block' : 'none';
    }

    // Apply filters
    function applyFilters() {
        currentFilters = {
            category: document.getElementById('filterCategory').value,
            status: document.getElementById('filterStatus').value,
            location: document.getElementById('filterLocation').value,
            search: document.getElementById('filterSearch')?.value || ''
        };

        // Remove empty filters
        Object.keys(currentFilters).forEach(key => {
            if (!currentFilters[key]) {
                delete currentFilters[key];
            }
        });

        loadItems(1);
    }

    // Reset filters
    function resetFilters() {
        document.getElementById('filterCategory').value = '';
        document.getElementById('filterStatus').value = '';
        document.getElementById('filterLocation').value = '';
        if (document.getElementById('filterSearch')) {
            document.getElementById('filterSearch').value = '';
        }

        currentFilters = {};
        loadItems(1);
    }

    // Refresh table
    function refreshTable() {
        loadItems(currentPage);
    }

    // Show error message
    function showError(message) {
        // Create or update error alert
        let errorAlert = document.getElementById('dataTableError');

        if (!errorAlert) {
            errorAlert = document.createElement('div');
            errorAlert.id = 'dataTableError';
            errorAlert.className = 'alert alert-danger alert-dismissible fade show';
            errorAlert.innerHTML = `
            <i class="fas fa-exclamation-triangle me-2"></i>
            <span id="errorMessage"></span>
            <button type="button" class="btn-close" onclick="this.parentElement.remove()"></button>
        `;

            const cardBody = document.querySelector('.card-body');
            cardBody.insertBefore(errorAlert, cardBody.firstChild);
        }

        document.getElementById('errorMessage').textContent = message;
    }

    // Confirm delete
    function confirmDelete(itemId, itemName) {
        if (confirm(`Are you sure you want to delete "${itemName}"?\n\nThis action cannot be undone.`)) {
            deleteItem(itemId);
        }
    }

    // Delete item
    async function deleteItem(itemId) {
        try {
            const response = await fetch(`api/items/delete.php`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    id: itemId
                })
            });

            const data = await response.json();

            if (data.success) {
                showNotification('success', 'Item deleted successfully');
                loadItems(currentPage);
            } else {
                showNotification('error', 'Failed to delete item: ' + (data.message || 'Unknown error'));
            }
        } catch (error) {
            console.error('Delete error:', error);
            showNotification('error', 'Network error. Please try again.');
        }
    }

    // Export to Excel
    function exportToExcel() {
        // Build export URL with current filters
        const exportParams = new URLSearchParams({
            export: 'excel',
            ...currentFilters
        });

        window.location.href = `api/items/export.php?${exportParams}`;
    }

    // Show notification
    function showNotification(type, message) {
        const notification = document.createElement('div');
        notification.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
        notification.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
        notification.innerHTML = `
        <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-triangle'} me-2"></i>
        ${escapeHtml(message)}
        <button type="button" class="btn-close" onclick="this.parentElement.remove()"></button>
    `;

        document.body.appendChild(notification);

        setTimeout(() => {
            if (notification.parentNode) {
                notification.remove();
            }
        }, 5000);
    }

    // Helper functions
    function getStatusLabel(status) {
        const statusMap = {
            'available': 'Available',
            'in_use': 'In Use',
            'maintenance': 'Maintenance',
            'reserved': 'Reserved',
            'lost': 'Lost',
            'damaged': 'Damaged'
        };
        return statusMap[status] || status;
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
</script>

<?php
require_once 'views/partials/footer.php';
?>