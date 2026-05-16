<?php
// views/items/index.php - Equipment List View
$current_page = 'items.php';

// Get stats for cards
function getEquipmentStats($conn)
{
    $stats = [
        'total_items' => 0,
        'available' => 0,
        'in_use' => 0,
        'maintenance' => 0
    ];

    try {
        // Total items
        $result = $conn->query("SELECT COUNT(*) as count FROM items WHERE status NOT IN ('disposed', 'lost')");
        if ($result) {
            $row = $result->fetch_assoc();
            $stats['total_items'] = $row['count'] ?? 0;
        }

        // Available items
        $result = $conn->query("SELECT COUNT(*) as count FROM items WHERE status = 'available'");
        if ($result) {
            $row = $result->fetch_assoc();
            $stats['available'] = $row['count'] ?? 0;
        }

        // In use items
        $result = $conn->query("SELECT COUNT(*) as count FROM items WHERE status = 'in_use'");
        if ($result) {
            $row = $result->fetch_assoc();
            $stats['in_use'] = $row['count'] ?? 0;
        }

        // Maintenance items
        $result = $conn->query("SELECT COUNT(*) as count FROM items WHERE status = 'maintenance'");
        if ($result) {
            $row = $result->fetch_assoc();
            $stats['maintenance'] = $row['count'] ?? 0;
        }
    } catch (Exception $e) {
        error_log("Error getting equipment stats: " . $e->getMessage());
    }

    return $stats;
}

$stats = getEquipmentStats($conn);
?>

<style>
    /* Adjust font size for the entire table */
    #itemsTable,
    #itemsTable td,
    #itemsTable th {
        font-size: 0.9rem;
        /* Adjust this value as needed */
    }

    /* Or more specific control */
    #itemsTable tbody td {
        font-size: 0.85rem;
        /* For table body cells */
    }

    #itemsTable thead th {
        font-size: 0.8rem;
        /* For table header */
        font-weight: 600;
    }

    /* Adjust the DataTables control elements */
    .dataTables_wrapper .dataTables_length,
    .dataTables_wrapper .dataTables_filter,
    .dataTables_wrapper .dataTables_info,
    .dataTables_wrapper .dataTables_paginate {
        font-size: 0.85rem;
    }


    /* Search input styling */
    #globalSearch {
        font-size: 0.85rem;
        /* Adjust this value */
        padding: 0.5rem 0.75rem;
    }

    /* Search icon container */
    .input-group-text {
        font-size: 0.85rem;
        /* Match input font size */
    }

    /* Search placeholder text */
    #globalSearch::placeholder {
        font-size: 0.85rem;
        color: #6c757d;
        opacity: 0.8;
    }

    /* Info text on the right */
    .input-group-text.small {
        font-size: 0.75rem !important;
        /* Smaller for the hint text */
        white-space: nowrap;
    }

    /* For mobile responsiveness */
    @media (max-width: 768px) {
        #globalSearch {
            font-size: 16px;
            /* Prevents zoom on mobile */
        }

        .input-group-text.small {
            font-size: 0.7rem !important;
            white-space: normal;
            /* Allows wrapping on small screens */
        }
    }
</style>

<!-- Stats Cards -->
<div class="stats-row">
    <!-- <div class="row g-3">
        <div class="col-md-3 col-6">
            <div class="stat-card d-flex align-items-center">
                <div class="stat-icon me-3">
                    <i class="fas fa-boxes"></i>
                </div>
                <div>
                    <div class="stat-value"><?php echo $stats['total_items']; ?></div>
                    <div class="stat-label">Total Items</div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="stat-card d-flex align-items-center" style="border-left-color: #28a745;">
                <div class="stat-icon me-3" style="color: #28a745; background: rgba(40, 167, 69, 0.1);">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div>
                    <div class="stat-value"><?php echo $stats['available']; ?></div>
                    <div class="stat-label">Available</div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="stat-card d-flex align-items-center" style="border-left-color: #ffc107;">
                <div class="stat-icon me-3" style="color: #ffc107; background: rgba(255, 193, 7, 0.1);">
                    <i class="fas fa-clock"></i>
                </div>
                <div>
                    <div class="stat-value"><?php echo $stats['in_use']; ?></div>
                    <div class="stat-label">In Use</div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="stat-card d-flex align-items-center" style="border-left-color: #dc3545;">
                <div class="stat-icon me-3" style="color: #dc3545; background: rgba(220, 53, 69, 0.1);">
                    <i class="fas fa-tools"></i>
                </div>
                <div>
                    <div class="stat-value"><?php echo $stats['maintenance']; ?></div>
                    <div class="stat-label">Maintenance</div>
                </div>
            </div>
        </div>
    </div> -->
</div>

<div class="card shadow">
    <div class="card-header bg-white">
        <div class="row align-items-center">
            <div class="col-md-6">
                <h6 class="mb-0"><i class="fas fa-list me-2"></i>Equipment List</h6>
                <small class="text-muted">Total items: <span id="totalItemsCount"><?php echo $stats['total_items']; ?></span></small>
            </div>
            <div class="col-md-6 text-end">
                <div class="btn-group" role="group">
                    <a href="?action=create" class="btn text-white btn-sm btn-info" style="background-color: #456882; border-color: #456882;">
                        <i class="fas fa-plus me-2"></i>Add New Equipment
                    </a>
                    <button type="button" class="btn btn- text-white btn-sm" style="background-color: #1A3636;" data-bs-toggle="modal" data-bs-target="#filterModal">
                        <i class="fas fa-filter me-1"></i>Advanced Filters
                    </button>
                    <button type="button" class="btn btn- text-white btn-sm" style="background-color: #677D6A;" id="bulkCheckoutBtn" disabled>
                        <i class="fas fa-truck me-1"></i>Bulk Check-out
                    </button>
                    <button type="button" class="btn btn- text-white btn-sm" style="background-color: #481E14;" id="bulkDeleteBtn" disabled>
                        <i class="fas fa-trash me-1"></i>Delete Selected
                    </button>
                </div>
            </div>
        </div>
    </div>
    <div class="card-body">
        <!-- Search Bar -->
        <div class="row mb-3">
            <div class="col-md-12">
                <div class="input-group">
                    <span class="input-group-text bg-white search-icon">
                        <i class="fas fa-search text-muted"></i>
                    </span>
                    <input type="text" class="form-control border-start-0 search-input"
                        id="globalSearch"
                        placeholder="Search by #, ID, Item Name, Serial Number, Category, Status, or Location...">
                    <span class="input-group-text bg-white text-muted search-hint">
                        <i class="fas fa-info-circle me-1"></i>Search in all fields
                    </span>
                </div>
            </div>
        </div>

        <!-- Equipment Table -->
        <div class="table-responsive">
            <table class="table table-hover table-striped" id="itemsTable" style="width:100%">
                <thead class="table-light">
                    <tr>
                        <th width="30">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="selectAll">
                            </div>
                        </th>
                        <th>#</th>
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
                <tbody>
                    <!-- Data will be loaded via AJAX -->
                    <tr>
                        <td colspan="10" class="text-center py-4">
                            <div class="text-muted">
                                <i class="fas fa-circle-notch fa-spin fa-2x mb-2"></i>
                                <p>Loading items...</p>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Table Info -->
        <div class="row mt-3 align-items-center">
            <div class="col-md-6">
                <p class="text-muted small mb-0">
                    <i class="fas fa-info-circle me-1"></i>
                    Showing <span id="showingStart">0</span> to <span id="showingEnd">0</span> of <span id="totalRecords">0</span> items
                </p>
            </div>
            <div class="col-md-6">
                <nav aria-label="Table navigation">
                    <ul class="pagination justify-content-end mb-0" id="tablePagination">
                        <!-- Pagination will be generated by DataTables -->
                    </ul>
                </nav>
            </div>
        </div>
    </div>
</div>

<!-- Advanced Filters Modal -->
<div class="modal fade" id="filterModal" tabindex="-1" aria-labelledby="filterModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="filterModalLabel">
                    <i class="fas fa-sliders-h me-2"></i>Advanced Filters
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="filterForm">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Category</label>
                            <select class="form-select" id="filterCategory">
                                <option value="">All Categories</option>
                                <?php
                                $categories = getCategories();
                                foreach ($categories as $key => $value) {
                                    echo "<option value=\"" . htmlspecialchars($key) . "\">" . htmlspecialchars($value) . "</option>";
                                }
                                ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Status</label>
                            <select class="form-select" id="filterStatus">
                                <option value="">All Statuses</option>
                                <?php
                                $statuses = getStatuses();
                                foreach ($statuses as $key => $value) {
                                    echo "<option value=\"" . htmlspecialchars($key) . "\">" . htmlspecialchars($value) . "</option>";
                                }
                                ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Location</label>
                            <select class="form-select" id="filterLocation">
                                <option value="">All Locations</option>
                                <?php
                                $locations = getLocations();
                                foreach ($locations as $key => $value) {
                                    echo "<option value=\"" . htmlspecialchars($key) . "\">" . htmlspecialchars($value) . "</option>";
                                }
                                ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Department</label>
                            <select class="form-select" id="filterDepartment">
                                <option value="">All Departments</option>
                                <?php
                                $departments = getDepartments();
                                foreach ($departments as $key => $value) {
                                    echo "<option value=\"" . htmlspecialchars($key) . "\">" . htmlspecialchars($value) . "</option>";
                                }
                                ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Condition</label>
                            <select class="form-select" id="filterCondition">
                                <option value="">All Conditions</option>
                                <?php
                                $conditions = getConditions();
                                foreach ($conditions as $key => $value) {
                                    echo "<option value=\"" . htmlspecialchars($key) . "\">" . htmlspecialchars($value) . "</option>";
                                }
                                ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Date Range</label>
                            <div class="input-group">
                                <input type="date" class="form-control" id="filterDateFrom" placeholder="From">
                                <span class="input-group-text">to</span>
                                <input type="date" class="form-control" id="filterDateTo" placeholder="To">
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times me-1"></i>Close
                </button>
                <button type="button" class="btn btn-primary" onclick="applyFilters()">
                    <i class="fas fa-check me-1"></i>Apply Filters
                </button>
                <button type="button" class="btn btn-outline-secondary" onclick="resetFilters()">
                    <i class="fas fa-undo me-1"></i>Reset
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Bulk Delete Confirmation Modal -->
<div class="modal fade" id="bulkDeleteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title">
                    <i class="fas fa-exclamation-triangle me-2"></i>Confirm Bulk Delete
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete <span id="deleteCount">0</span> selected item(s)?</p>
                <p class="text-danger small">
                    <i class="fas fa-exclamation-circle me-1"></i>
                    This action cannot be undone.
                </p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times me-1"></i>Cancel
                </button>
                <button type="button" class="btn btn-danger" onclick="confirmBulkDelete()">
                    <i class="fas fa-trash me-1"></i>Delete Selected
                </button>
            </div>
        </div>
    </div>
</div>

<script>
    // Wait for jQuery to be fully loaded
    (function() {
        // Function to initialize DataTable
        function initDataTable() {
            if (typeof jQuery === 'undefined') {
                console.log('Waiting for jQuery to load...');
                setTimeout(initDataTable, 100);
                return;
            }

            if (typeof jQuery.fn.DataTable === 'undefined') {
                console.log('Waiting for DataTables to load...');
                setTimeout(initDataTable, 100);
                return;
            }

            console.log('Initializing DataTable...');

            $(document).ready(function() {
                // Initialize DataTable
                const table = $('#itemsTable').DataTable({
                    processing: true,
                    serverSide: true,
                    ajax: {
                        url: 'api/items/datatable.php',
                        type: 'POST',
                        data: function(d) {
                            // Add custom filters
                            d.category = $('#filterCategory').val();
                            d.status = $('#filterStatus').val();
                            d.location = $('#filterLocation').val();
                            d.department = $('#filterDepartment').val();
                            d.condition = $('#filterCondition').val();
                            d.date_from = $('#filterDateFrom').val();
                            d.date_to = $('#filterDateTo').val();
                        }
                    },
                    columns: [{
                            data: null,
                            render: function(data, type, row) {
                                return '<div class="form-check">' +
                                    '<input class="form-check-input item-checkbox" type="checkbox" value="' + row.id + '">' +
                                    '</div>';
                            },
                            orderable: false
                        },
                        {
                            data: 'row_number',
                            name: 'row_number'
                        },
                        {
                            data: 'id',
                            name: 'id'
                        },
                        {
                            data: 'item_name',
                            name: 'item_name'
                        },
                        {
                            data: 'serial_number',
                            name: 'serial_number'
                        },
                        {
                            data: 'category_name',
                            name: 'category_name',
                            render: function(data, type, row) {
                                return data || row.category || '<span class="text-muted">N/A</span>';
                            }
                        },
                        {
                            data: 'department_name',
                            name: 'department_name',
                            render: function(data, type, row) {
                                return data ? '<span class="badge bg-info text-dark">' + data + '</span>' : '<span class="text-muted">' + (row.department || 'N/A') + '</span>';
                            }
                        },
                        {
                            data: 'status',
                            name: 'status',
                            render: function(data) {
                                const statusColors = {
                                    'available': 'success',
                                    'in_use': 'primary',
                                    'maintenance': 'warning',
                                    'reserved': 'info',
                                    'disposed': 'secondary',
                                    'lost': 'danger',
                                    'retired': 'dark'
                                };
                                const color = statusColors[data] || 'secondary';
                                return '<span class="badge bg-' + color + '">' + data.replace('_', ' ') + '</span>';
                            }
                        },
                        {
                            data: 'stock_location',
                            name: 'stock_location'
                        },
                        {
                            data: 'quantity',
                            name: 'quantity'
                        },
                        {
                            data: null,
                            render: function(data, type, row) {
                                return '<div class="btn-group btn-group-sm" role="group">' +
                                    '<a href="items.php?action=view&id=' + row.id + '" class="btn btn- text-white" title="View" style="background: #435585;">' +
                                    '<i class="fas fa-eye"></i></a>' +
                                    '<a href="items.php?action=edit&id=' + row.id + '" class="btn btn- text-white" title="Edit" style="background: #5C8374;">' +
                                    '<i class="fas fa-edit"></i></a>' +
                                    '<button type="button" class="btn btn- text-white" onclick="deleteItem(' + row.id + ')" title="Delete" style="background: #750E21;">' +
                                    '<i class="fas fa-trash"></i></button>' +
                                    '</div>';
                            },
                            orderable: false
                        }
                    ],
                    order: [
                        [1, 'asc']
                    ],
                    pageLength: 5,
                    language: {
                        processing: '<div class="text-center"><i class="fas fa-circle-notch fa-spin fa-2x mb-2"></i><br>Loading...</div>',
                        search: "<i class='fas fa-search'></i> Search:",
                        lengthMenu: "Show _MENU_ entries per page",
                        info: "Showing _START_ to _END_ of _TOTAL_ items",
                        infoEmpty: "Showing 0 to 0 of 0 items",
                        infoFiltered: "(filtered from _MAX_ total items)",
                        paginate: {
                            first: '<i class="fas fa-angle-double-left"></i>',
                            last: '<i class="fas fa-angle-double-right"></i>',
                            next: '<i class="fas fa-chevron-right"></i>',
                            previous: '<i class="fas fa-chevron-left"></i>'
                        }
                    },
                    drawCallback: function(settings) {
                        // Update total items count
                        $('#totalItemsCount').text(settings.fnRecordsTotal());

                        // Update showing info
                        const info = this.api().page.info();
                        $('#showingStart').text(info.start + 1);
                        $('#showingEnd').text(info.end);
                        $('#totalRecords').text(info.recordsTotal);

                        // Update bulk action buttons state
                        updateBulkActions();
                    }
                });

                // Global search
                $('#globalSearch').on('keyup', function() {
                    table.search(this.value).draw();
                });

                // Select all checkbox
                $('#selectAll').on('change', function() {
                    const isChecked = $(this).prop('checked');
                    $('.item-checkbox').prop('checked', isChecked);
                    updateBulkActions();
                });

                // Individual checkbox change
                $(document).on('change', '.item-checkbox', function() {
                    const allChecked = $('.item-checkbox:checked').length === $('.item-checkbox').length;
                    $('#selectAll').prop('checked', allChecked);
                    updateBulkActions();
                });

                // Update bulk action buttons
                function updateBulkActions() {
                    const selectedCount = $('.item-checkbox:checked').length;
                    $('#bulkCheckoutBtn, #bulkDeleteBtn').prop('disabled', selectedCount === 0);
                    $('#deleteCount').text(selectedCount);
                }

                // Bulk checkout
                $('#bulkCheckoutBtn').on('click', function() {
                    const selectedIds = getSelectedIds();
                    if (selectedIds.length > 0) {
                        window.location.href = 'bulk_checkout.php?ids=' + selectedIds.join(',');
                    }
                });

                // Bulk delete
                $('#bulkDeleteBtn').on('click', function() {
                    const selectedIds = getSelectedIds();
                    if (selectedIds.length > 0) {
                        $('#bulkDeleteModal').modal('show');
                    }
                });

                // Get selected IDs
                function getSelectedIds() {
                    const ids = [];
                    $('.item-checkbox:checked').each(function() {
                        ids.push($(this).val());
                    });
                    return ids;
                }

                // Confirm bulk delete
                window.confirmBulkDelete = function() {
                    const selectedIds = getSelectedIds();
                    if (selectedIds.length === 0) return;

                    $.ajax({
                        url: 'api/items/bulk_delete.php',
                        method: 'POST',
                        data: {
                            ids: selectedIds
                        },
                        dataType: 'json',
                        success: function(response) {
                            $('#bulkDeleteModal').modal('hide');
                            if (response.success) {
                                if (typeof window.showToast === 'function') {
                                    window.showToast(response.message, 'success');
                                } else if (typeof showToast === 'function') {
                                    showToast(response.message, 'success');
                                } else {
                                    alert(response.message);
                                }
                                table.ajax.reload();
                            } else {
                                if (typeof window.showToast === 'function') {
                                    window.showToast(response.message, 'error');
                                } else if (typeof showToast === 'function') {
                                    showToast(response.message, 'error');
                                } else {
                                    alert(response.message);
                                }
                            }
                        },
                        error: function() {
                            $('#bulkDeleteModal').modal('hide');
                            if (typeof window.showToast === 'function') {
                                window.showToast('Error deleting items', 'error');
                            } else if (typeof showToast === 'function') {
                                showToast('Error deleting items', 'error');
                            } else {
                                alert('Error deleting items');
                            }
                        }
                    });
                };

                // Delete single item
                window.deleteItem = function(id) {
                    if (confirm('Are you sure you want to delete this item?')) {
                        $.ajax({
                            url: 'api/items/delete.php',
                            method: 'POST',
                            data: {
                                id: id
                            },
                            dataType: 'json',
                            success: function(response) {
                                if (response.success) {
                                    if (typeof window.showToast === 'function') {
                                        window.showToast(response.message, 'success');
                                    } else if (typeof showToast === 'function') {
                                        showToast(response.message, 'success');
                                    } else {
                                        alert(response.message);
                                    }
                                    table.ajax.reload();
                                } else {
                                    if (typeof window.showToast === 'function') {
                                        window.showToast(response.message, 'error');
                                    } else if (typeof showToast === 'function') {
                                        showToast(response.message, 'error');
                                    } else {
                                        alert(response.message);
                                    }
                                }
                            },
                            error: function() {
                                if (typeof window.showToast === 'function') {
                                    window.showToast('Error deleting item', 'error');
                                } else if (typeof showToast === 'function') {
                                    showToast('Error deleting item', 'error');
                                } else {
                                    alert('Error deleting item');
                                }
                            }
                        });
                    }
                };

                // Apply filters
                window.applyFilters = function() {
                    $('#filterModal').modal('hide');
                    table.ajax.reload();
                };

                // Reset filters
                window.resetFilters = function() {
                    $('#filterForm')[0].reset();
                    $('#filterModal').modal('hide');
                    table.ajax.reload();
                };
            });
        }

        // Start initialization
        initDataTable();
    })();
</script>