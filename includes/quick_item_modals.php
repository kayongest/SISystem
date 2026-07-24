<!-- includes/quick_item_modals.php - Reusable Quick Action Modals for Equipment View & Edit -->

<!-- Quick View Equipment Modal -->
<div class="modal fade" id="quickViewItemModal" tabindex="-1" aria-hidden="true" style="z-index: 1055;">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 rounded-4 shadow-lg overflow-hidden">
            <div class="modal-header bg-dark text-white py-3">
                <h5 class="modal-title fw-bold d-flex align-items-center">
                    <i class="fas fa-box-open me-2 text-warning"></i><span id="quickViewItemTitle">Equipment Details</span>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4 bg-light">
                <div class="row g-3 mb-4">
                    <!-- Left: Image & Status -->
                    <div class="col-md-5 text-center">
                        <div class="card border-0 shadow-sm p-3 rounded-3 bg-white h-100 d-flex flex-column justify-content-center align-items-center">
                            <div id="quickViewImageContainer" class="mb-3 w-100">
                                <div class="bg-light p-4 rounded-3 border d-flex align-items-center justify-content-center" style="height: 160px;">
                                    <i class="fas fa-boxes fa-3x text-secondary opacity-50"></i>
                                </div>
                            </div>
                            <div class="mb-2">
                                <span id="quickViewStatusBadge" class="badge bg-success px-3 py-2 fs-6 rounded-pill text-uppercase">Available</span>
                            </div>
                            <small class="text-muted">Serial: <code id="quickViewSerial" class="text-primary fw-bold">--</code></small>
                        </div>
                    </div>

                    <!-- Right: Key Specs -->
                    <div class="col-md-7">
                        <div class="card border-0 shadow-sm p-3 rounded-3 bg-white h-100">
                            <h6 class="fw-bold text-muted small text-uppercase mb-3"><i class="fas fa-list me-1 text-primary"></i> Specifications</h6>
                            <div class="row g-2">
                                <div class="col-6">
                                    <span class="text-muted small d-block">Category:</span>
                                    <strong id="quickViewCategory" class="text-dark">--</strong>
                                </div>
                                <div class="col-6">
                                    <span class="text-muted small d-block">Department:</span>
                                    <strong id="quickViewDepartment" class="text-dark">--</strong>
                                </div>
                                <div class="col-6">
                                    <span class="text-muted small d-block">Stock Location:</span>
                                    <strong id="quickViewLocation" class="text-dark">--</strong>
                                </div>
                                <div class="col-6">
                                    <span class="text-muted small d-block">Condition:</span>
                                    <strong id="quickViewCondition" class="text-dark">--</strong>
                                </div>
                                <div class="col-6">
                                    <span class="text-muted small d-block">Quantity:</span>
                                    <span id="quickViewQuantity" class="badge bg-primary">1 Unit</span>
                                </div>
                                <div class="col-6">
                                    <span class="text-muted small d-block">Brand / Model:</span>
                                    <span id="quickViewBrandModel" class="text-dark fw-semibold">--</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Assigned Accessories Card -->
                <div class="card border-0 shadow-sm rounded-3 overflow-hidden bg-white mb-3">
                    <div class="card-header bg-white border-0 py-2 px-3 d-flex align-items-center justify-content-between">
                        <h6 class="fw-bold text-dark m-0"><i class="fas fa-plug text-warning me-2"></i> Attached Accessories</h6>
                        <span id="quickViewAccessoryCount" class="badge bg-warning bg-opacity-10 text-dark border">0 Items</span>
                    </div>
                    <div class="p-3 pt-0">
                        <ul id="quickViewAccessoriesList" class="list-group list-group-flush small">
                            <li class="list-group-item text-muted text-center py-2 border-0">No accessories assigned</li>
                        </ul>
                    </div>
                </div>

                <!-- QR Code Preview -->
                <div id="quickViewQRContainer" class="card border-0 shadow-sm rounded-3 p-3 bg-white text-center d-none">
                    <h6 class="fw-bold text-muted small text-uppercase mb-2"><i class="fas fa-qrcode me-1 text-info"></i> Identification QR</h6>
                    <img id="quickViewQRImg" src="" alt="QR Code" class="img-fluid border p-1 rounded d-inline-block" style="width: 100px; height: 100px;">
                </div>
            </div>
            <div class="modal-footer bg-white border-top py-2 justify-content-between">
                <div>
                    <button type="button" id="quickViewEditBtn" class="btn btn-primary rounded-pill px-3 btn-sm">
                        <i class="fas fa-edit me-1"></i> Quick Edit
                    </button>
                    <a id="quickViewFullViewBtn" href="#" class="btn btn-outline-secondary rounded-pill px-3 btn-sm">
                        <i class="fas fa-external-link-alt me-1"></i> Full Page
                    </a>
                </div>
                <button type="button" class="btn btn-secondary rounded-pill px-3 btn-sm" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Quick Edit Equipment Modal -->
<div class="modal fade" id="quickEditItemModal" tabindex="-1" aria-hidden="true" style="z-index: 1060;">
    <div class="modal-dialog modal-dialog-centered">
        <form id="quickEditItemForm" class="modal-content border-0 rounded-4 shadow-lg">
            <div class="modal-header bg-dark text-white rounded-top-4 py-3">
                <h5 class="modal-title fw-bold">
                    <i class="fas fa-edit me-2 text-success"></i>Quick Edit Equipment
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4 text-dark">
                <input type="hidden" name="id" id="quickEditId" value="">
                
                <div class="mb-3">
                    <label class="form-label small fw-bold text-uppercase text-muted">Item Name *</label>
                    <input type="text" name="item_name" id="quickEditItemName" class="form-control rounded-3" required>
                </div>

                <div class="row g-2 mb-3">
                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-uppercase text-muted">Serial Number</label>
                        <input type="text" name="serial_number" id="quickEditSerial" class="form-control rounded-3">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-uppercase text-muted">Quantity</label>
                        <input type="number" name="quantity" id="quickEditQuantity" class="form-control rounded-3" min="1" value="1">
                    </div>
                </div>

                <div class="row g-2 mb-3">
                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-uppercase text-muted">Status</label>
                        <select name="status" id="quickEditStatus" class="form-select rounded-3">
                            <option value="available">Available</option>
                            <option value="in_use">In Use / On Event</option>
                            <option value="maintenance">Maintenance</option>
                            <option value="reserved">Reserved</option>
                            <option value="retired">Retired</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-uppercase text-muted">Condition</label>
                        <select name="condition" id="quickEditCondition" class="form-select rounded-3">
                            <option value="excellent">Excellent</option>
                            <option value="good">Good</option>
                            <option value="fair">Fair</option>
                            <option value="needs_repair">Needs Repair</option>
                        </select>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label small fw-bold text-uppercase text-muted">Stock Location</label>
                    <input type="text" name="stock_location" id="quickEditLocation" class="form-control rounded-3" placeholder="e.g. Main Warehouse">
                </div>
            </div>
            <div class="modal-footer bg-light p-3 rounded-bottom-4 border-top">
                <button type="button" class="btn btn-secondary rounded-pill px-3 btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-success rounded-pill px-4 btn-sm" id="quickEditSubmitBtn">
                    <i class="fas fa-save me-1"></i> Save Changes
                </button>
            </div>
        </form>
    </div>
</div>

<script>
window.openQuickViewItemModal = function(itemId) {
    if (!itemId) return;
    
    $('#quickViewItemTitle').text('Loading Equipment...');
    $('#quickViewCategory').text('Loading...');
    $('#quickViewDepartment').text('Loading...');
    $('#quickViewLocation').text('Loading...');
    $('#quickViewCondition').text('Loading...');
    $('#quickViewQuantity').text('1 Unit');
    $('#quickViewBrandModel').text('Loading...');
    $('#quickViewSerial').text('#' + itemId);
    $('#quickViewAccessoriesList').html('<li class="list-group-item text-muted text-center py-2 border-0"><i class="fas fa-spinner fa-spin me-2"></i>Loading accessories...</li>');
    $('#quickViewQRContainer').addClass('d-none');
    $('#quickViewImageContainer').html('<div class="bg-light p-4 rounded-3 border d-flex align-items-center justify-content-center" style="height: 160px;"><i class="fas fa-spinner fa-spin fa-2x text-secondary"></i></div>');
    
    $('#quickViewFullViewBtn').attr('href', 'items.php?action=view&id=' + itemId);
    $('#quickViewEditBtn').off('click').on('click', function() {
        $('#quickViewItemModal').modal('hide');
        window.openQuickEditItemModal(itemId);
    });

    const modal = new bootstrap.Modal(document.getElementById('quickViewItemModal'));
    modal.show();

    $.ajax({
        url: 'api/get_item.php',
        method: 'GET',
        data: { id: itemId },
        dataType: 'json',
        success: function(res) {
            const item = res.item || res.data || res;
            if (item && (item.id || item.item_name)) {
                $('#quickViewItemTitle').text(item.item_name || 'Equipment #' + itemId);
                $('#quickViewCategory').text(item.category_name || item.category || 'General');
                $('#quickViewDepartment').text(item.department_name || item.department || 'N/A');
                $('#quickViewLocation').text(item.stock_location || 'Main Warehouse');
                $('#quickViewCondition').text(item.condition ? item.condition.toUpperCase() : 'GOOD');
                $('#quickViewQuantity').text((item.quantity || 1) + ' Unit(s)');
                $('#quickViewBrandModel').text(item.brand_model || item.brand_name || 'Standard');
                $('#quickViewSerial').text(item.serial_number || '#' + item.id);

                // Image Preview
                if (item.image) {
                    $('#quickViewImageContainer').html(`<img src="${item.image}" class="img-fluid rounded-3 border shadow-sm" style="max-height: 160px; object-fit: cover;" onerror="this.outerHTML='<div class=\\'bg-light p-4 rounded-3 border d-flex align-items-center justify-content-center\\' style=\\'height: 160px;\\'><i class=\\'fas fa-boxes fa-3x text-secondary opacity-50\\'></i></div>'">`);
                } else {
                    $('#quickViewImageContainer').html('<div class="bg-light p-4 rounded-3 border d-flex align-items-center justify-content-center" style="height: 160px;"><i class="fas fa-boxes fa-3x text-secondary opacity-50"></i></div>');
                }

                // Status Badge
                const status = (item.status || 'available').toLowerCase();
                let statusBadgeClass = 'bg-secondary';
                if (status === 'available') statusBadgeClass = 'bg-success';
                else if (status === 'in_use' || status === 'on_event') statusBadgeClass = 'bg-primary';
                else if (status === 'maintenance') statusBadgeClass = 'bg-warning text-dark';
                $('#quickViewStatusBadge').attr('class', 'badge ' + statusBadgeClass + ' px-3 py-2 fs-6 rounded-pill text-uppercase').text(status.replace('_', ' '));

                // Accessories
                if (res.accessories && res.accessories.length > 0) {
                    $('#quickViewAccessoryCount').text(res.accessories.length + ' Items');
                    let accHtml = '';
                    res.accessories.forEach(a => {
                        accHtml += `<li class="list-group-item d-flex justify-content-between align-items-center border-0 px-0 py-1"><span class="text-dark"><i class="fas fa-check text-success me-2 small"></i>${a}</span></li>`;
                    });
                    $('#quickViewAccessoriesList').html(accHtml);
                } else {
                    $('#quickViewAccessoryCount').text('0 Items');
                    $('#quickViewAccessoriesList').html('<li class="list-group-item text-muted text-center py-2 border-0">No accessories assigned</li>');
                }

                // QR Code
                if (res.qr_code || item.qr_code) {
                    const qrSrc = res.qr_code || item.qr_code;
                    $('#quickViewQRImg').attr('src', qrSrc);
                    $('#quickViewQRContainer').removeClass('d-none');
                }
            }
        },
        error: function() {
            $('#quickViewItemTitle').text('Error Loading Equipment');
        }
    });
};

window.openQuickEditItemModal = function(itemId) {
    if (!itemId) return;

    $('#quickEditId').val(itemId);
    $('#quickEditItemName').val('Loading...');
    $('#quickEditSerial').val('');
    $('#quickEditQuantity').val(1);
    
    const modal = new bootstrap.Modal(document.getElementById('quickEditItemModal'));
    modal.show();

    $.ajax({
        url: 'api/get_item.php',
        method: 'GET',
        data: { id: itemId },
        dataType: 'json',
        success: function(res) {
            const item = res.item || res.data || res;
            if (item) {
                $('#quickEditItemName').val(item.item_name || '');
                $('#quickEditSerial').val(item.serial_number || '');
                $('#quickEditQuantity').val(item.quantity || 1);
                $('#quickEditStatus').val((item.status || 'available').toLowerCase());
                $('#quickEditCondition').val((item.condition || 'good').toLowerCase());
                $('#quickEditLocation').val(item.stock_location || '');
            }
        }
    });
};

$(document).on('submit', '#quickEditItemForm', function(e) {
    e.preventDefault();
    const submitBtn = $('#quickEditSubmitBtn');
    submitBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i> Saving...');

    $.ajax({
        url: 'api/update_item.php',
        method: 'POST',
        data: $(this).serialize(),
        dataType: 'json',
        success: function(res) {
            submitBtn.prop('disabled', false).html('<i class="fas fa-save me-1"></i> Save Changes');
            if (res.success) {
                $('#quickEditItemModal').modal('hide');
                if (typeof window.showToast === 'function') {
                    window.showToast(res.message || 'Equipment updated successfully!', 'success');
                } else {
                    alert('Equipment updated successfully!');
                }
                
                // Refresh DataTables if present on current page
                if ($.fn.DataTable.isDataTable('#itemsTable')) {
                    $('#itemsTable').DataTable().ajax.reload(null, false);
                }
                if ($.fn.DataTable.isDataTable('#equipmentTable')) {
                    $('#equipmentTable').DataTable().ajax.reload(null, false);
                }
            } else {
                alert(res.message || 'Failed to update equipment.');
            }
        },
        error: function() {
            submitBtn.prop('disabled', false).html('<i class="fas fa-save me-1"></i> Save Changes');
            alert('An error occurred while saving.');
        }
    });
});
</script>
