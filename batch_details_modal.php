<?php
// batch_details_modal.php - Complete version for modal display
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once 'includes/bootstrap.php';

if (!isLoggedIn()) {
    echo '<div class="alert alert-danger">Please login to view details</div>';
    exit();
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo '<div class="alert alert-danger">Invalid batch ID</div>';
    exit();
}

$batch_id = intval($_GET['id']);

// Database connection
$conn = new mysqli('localhost', 'root', '', 'ability_db');
if ($conn->connect_error) {
    echo '<div class="alert alert-danger">Database connection failed</div>';
    exit();
}

// Get batch details
$sql = "
    SELECT 
        bs.*, 
        u1.username as submitted_by_name,
        u1.full_name as submitted_by_fullname,
        u1.role as submitted_by_role,
        u2.username as requested_by_name,
        u2.full_name as requested_by_fullname,
        u2.role as requested_by_role,
        u3.username as approved_by_name,
        u3.full_name as approved_by_fullname,
        u3.role as approved_by_role,
        DATE_FORMAT(bs.approved_at, '%Y-%m-%d %H:%i:%s') as approved_at_formatted,
        DATE_FORMAT(bs.submitted_at, '%M %d, %Y %h:%i %p') as formatted_submitted_at,
        DATE_FORMAT(bs.created_at, '%Y-%m-%d %H:%i:%s') as created_at_formatted,
        DATE_FORMAT(bs.updated_at, '%Y-%m-%d %H:%i:%s') as updated_at_formatted
    FROM batch_scans bs 
    LEFT JOIN users u1 ON bs.submitted_by = u1.id 
    LEFT JOIN users u2 ON bs.requested_by = u2.id 
    LEFT JOIN users u3 ON bs.approved_by = u3.id 
    WHERE bs.id = ?
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $batch_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo '<div class="alert alert-danger">Batch not found</div>';
    exit();
}

$batch = $result->fetch_assoc();
$conn->close();

// Helper functions (only define if they don't exist)
if (!function_exists('getStatusBadge')) {
    function getStatusBadge($status)
    {
        $badges = [
            'completed' => 'success',
            'processing' => 'warning',
            'pending' => 'secondary',
            'cancelled' => 'danger'
        ];
        $color = $badges[$status] ?? 'secondary';
        return '<span class="badge bg-' . $color . '">' . ucfirst($status) . '</span>';
    }
}

if (!function_exists('getApprovalBadge')) {
    function getApprovalBadge($status)
    {
        $badges = [
            'approved' => 'success',
            'pending' => 'warning',
            'rejected' => 'danger',
            'not_required' => 'secondary'
        ];
        $color = $badges[$status] ?? 'secondary';
        return '<span class="badge bg-' . $color . '">' . ucfirst(str_replace('_', ' ', $status)) . '</span>';
    }
}

// Get user role badge
function getUserRoleBadge($role)
{
    $badges = [
        'admin' => 'danger',
        'technician' => 'primary',
        'stock_controller' => 'success',
        'user' => 'secondary'
    ];
    $color = $badges[$role] ?? 'secondary';
    return '<span class="badge bg-' . $color . '">' . ucfirst(str_replace('_', ' ', $role)) . '</span>';
}
?>
<style>
    .modal-details-table th {
        width: 40%;
        font-weight: 600;
        color: #6c757d;
    }

    .modal-details-table td {
        padding: 8px 0;
    }

    .info-section {
        margin-bottom: 20px;
    }

    .info-section h6 {
        border-bottom: 2px solid #4361ee;
        padding-bottom: 8px;
        margin-bottom: 15px;
        color: #4361ee;
    }

    .notes-box {
        background: #f8f9fa;
        border-left: 4px solid #4361ee;
        padding: 15px;
        border-radius: 4px;
    }

    .badge {
        font-size: 0.85em;
        padding: 5px 10px;
    }
</style>

<div class="modal-details">
    <!-- Batch Information -->
    <div class="info-section">
        <h6><i class="fas fa-info-circle me-2"></i>Batch Information</h6>
        <table class="table modal-details-table">
            <tr>
                <th>Batch ID:</th>
                <td><code class="bg-light p-2 rounded"><?php echo htmlspecialchars($batch['batch_id']); ?></code></td>
            </tr>
            <tr>
                <th>Batch Name:</th>
                <td><strong><?php echo htmlspecialchars($batch['batch_name']); ?></strong></td>
            </tr>
            <tr>
                <th>Status:</th>
                <td>
                    <?php
                    // Inline status badge to avoid function conflicts
                    $statusColors = [
                        'completed' => 'success',
                        'processing' => 'warning',
                        'pending' => 'secondary',
                        'cancelled' => 'danger'
                    ];
                    $statusColor = $statusColors[$batch['status']] ?? 'secondary';
                    ?>
                    <span class="badge bg-<?php echo $statusColor; ?>">
                        <?php echo ucfirst($batch['status']); ?>
                    </span>
                </td>
            </tr>
            <tr>
                <th>Total Items:</th>
                <td><span class="badge bg-primary"><?php echo $batch['total_items']; ?> items</span></td>
            </tr>
            <tr>
                <th>Unique Items:</th>
                <td><span class="badge bg-info"><?php echo $batch['unique_items']; ?> unique</span></td>
            </tr>
            <tr>
                <th>Action:</th>
                <td><span class="badge bg-warning"><?php echo ucfirst(str_replace('_', ' ', $batch['action_applied'])); ?></span></td>
            </tr>
            <tr>
                <th>Location:</th>
                <td><i class="fas fa-map-marker-alt me-2 text-primary"></i><?php echo htmlspecialchars($batch['location_applied']); ?></td>
            </tr>
        </table>
    </div>

    <!-- Approval Information -->
    <div class="info-section">
        <h6><i class="fas fa-user-check me-2"></i>Approval Information</h6>
        <table class="table modal-details-table">
            <tr>
                <th>Requested By:</th>
                <td>
                    <?php if ($batch['requested_by_name']): ?>
                        <div class="d-flex align-items-center">
                            <div class="flex-shrink-0">
                                <i class="fas fa-user-tie text-primary me-2"></i>
                            </div>
                            <div class="flex-grow-1 ms-2">
                                <strong><?php echo htmlspecialchars($batch['requested_by_fullname'] ?? $batch['requested_by_name']); ?></strong>
                                <div class="text-muted small">
                                    <?php echo getUserRoleBadge($batch['requested_by_role'] ?? 'user'); ?>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <span class="text-muted">Not specified</span>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <th>Submitted By:</th>
                <td>
                    <?php if ($batch['submitted_by_name']): ?>
                        <div class="d-flex align-items-center">
                            <div class="flex-shrink-0">
                                <i class="fas fa-user text-info me-2"></i>
                            </div>
                            <div class="flex-grow-1 ms-2">
                                <strong><?php echo htmlspecialchars($batch['submitted_by_fullname'] ?? $batch['submitted_by_name']); ?></strong>
                                <div class="text-muted small">
                                    <?php echo getUserRoleBadge($batch['submitted_by_role'] ?? 'user'); ?>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <span class="text-muted">System</span>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <th>Approved By:</th>
                <td>
                    <?php if ($batch['approved_by_name']): ?>
                        <div class="d-flex align-items-center">
                            <div class="flex-shrink-0">
                                <i class="fas fa-user-check text-success me-2"></i>
                            </div>
                            <div class="flex-grow-1 ms-2">
                                <strong><?php echo htmlspecialchars($batch['approved_by_fullname'] ?? $batch['approved_by_name']); ?></strong>
                                <div class="text-muted small">
                                    <?php echo getUserRoleBadge($batch['approved_by_role'] ?? 'user'); ?>
                                </div>
                                <div class="text-muted small">
                                    <i class="far fa-clock me-1"></i>
                                    <?php echo $batch['approved_at_formatted']; ?>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <span class="text-warning">
                            <i class="fas fa-clock me-1"></i> Awaiting Approval
                        </span>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <th>Approval Status:</th>
                <td>
                    <?php
                    // Inline approval badge to avoid function conflicts
                    $approvalColors = [
                        'approved' => 'success',
                        'pending' => 'warning',
                        'rejected' => 'danger',
                        'not_required' => 'secondary'
                    ];
                    $approvalColor = $approvalColors[$batch['approval_status']] ?? 'secondary';
                    ?>
                    <span class="badge bg-<?php echo $approvalColor; ?>">
                        <?php echo ucfirst(str_replace('_', ' ', $batch['approval_status'])); ?>
                    </span>
                </td>
            </tr>
        </table>
    </div>

    <!-- Timestamps -->
    <div class="info-section">
        <h6><i class="fas fa-clock me-2"></i>Timestamps</h6>
        <table class="table modal-details-table">
            <tr>
                <th>Submitted At:</th>
                <td>
                    <i class="far fa-calendar me-2 text-primary"></i>
                    <?php echo $batch['formatted_submitted_at']; ?>
                </td>
            </tr>
            <tr>
                <th>Created At:</th>
                <td>
                    <i class="far fa-calendar-plus me-2 text-info"></i>
                    <?php echo $batch['created_at_formatted']; ?>
                </td>
            </tr>
            <tr>
                <th>Last Updated:</th>
                <td>
                    <i class="far fa-calendar-check me-2 text-success"></i>
                    <?php echo $batch['updated_at_formatted']; ?>
                </td>
            </tr>
        </table>
    </div>

    <!-- Notes -->
    <?php if (!empty($batch['notes'])): ?>
        <div class="info-section">
            <h6><i class="fas fa-sticky-note me-2"></i>Notes</h6>
            <div class="notes-box">
                <div class="notes-content">
                    <?php echo nl2br(htmlspecialchars($batch['notes'])); ?>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Quick Stats -->
    <div class="info-section">
        <h6><i class="fas fa-chart-bar me-2"></i>Quick Stats</h6>
        <div class="row g-3">
            <div class="col-6">
                <div class="card text-center">
                    <div class="card-body py-3">
                        <h5 class="mb-1"><?php echo $batch['total_items']; ?></h5>
                        <small class="text-muted">Total Items</small>
                    </div>
                </div>
            </div>
            <div class="col-6">
                <div class="card text-center">
                    <div class="card-body py-3">
                        <h5 class="mb-1"><?php echo $batch['unique_items']; ?></h5>
                        <small class="text-muted">Unique Items</small>
                    </div>
                </div>
            </div>
            <div class="col-6">
                <div class="card text-center">
                    <div class="card-body py-3">
                        <h5 class="mb-1"><?php echo $batch['total_items'] - $batch['unique_items']; ?></h5>
                        <small class="text-muted">Duplicates</small>
                    </div>
                </div>
            </div>
            <div class="col-6">
                <div class="card text-center">
                    <div class="card-body py-3">
                        <h5 class="mb-1">
                            <?php
                            $efficiency = $batch['total_items'] > 0
                                ? round(($batch['unique_items'] / $batch['total_items']) * 100, 1)
                                : 0;
                            echo $efficiency . '%';
                            ?>
                        </h5>
                        <small class="text-muted">Efficiency</small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Actions -->
    <div class="info-section mt-4">
        <div class="d-flex justify-content-center gap-2">
            <button class="btn btn-primary" onclick="copyToClipboard('<?php echo htmlspecialchars($batch['batch_id']); ?>')">
                <i class="fas fa-copy me-1"></i> Copy Batch ID
            </button>
            <?php if ($batch['approval_status'] == 'pending' && isLoggedIn()): ?>
                <button class="btn btn-success"
                    onclick="approveBatch(<?php echo $batch['id']; ?>, '<?php echo htmlspecialchars($batch['batch_name']); ?>')">
                    <i class="fas fa-check me-1"></i> Approve
                </button>
                <button class="btn btn-danger"
                    onclick="rejectBatch(<?php echo $batch['id']; ?>, '<?php echo htmlspecialchars($batch['batch_name']); ?>')">
                    <i class="fas fa-times me-1"></i> Reject
                </button>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
    // Copy batch ID to clipboard
    function copyToClipboard(text) {
        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(text).then(() => {
                showToast('success', 'Batch ID copied to clipboard!');
            });
        } else {
            // Fallback for older browsers
            const textArea = document.createElement('textarea');
            textArea.value = text;
            document.body.appendChild(textArea);
            textArea.focus();
            textArea.select();
            try {
                document.execCommand('copy');
                showToast('success', 'Batch ID copied to clipboard!');
            } catch (err) {
                showToast('error', 'Failed to copy: ' + err);
            }
            document.body.removeChild(textArea);
        }
    }

    // Approve batch
    function approveBatch(batchId, batchName) {
        if (confirm(`Are you sure you want to approve "${batchName}"?`)) {
            $.ajax({
                url: 'ajax/approve_batch.php',
                method: 'POST',
                data: {
                    batch_id: batchId,
                    notes: 'Approved via modal',
                    action: 'approve'
                },
                success: function(response) {
                    const result = JSON.parse(response);
                    if (result.success) {
                        showToast('success', result.message);
                        $('#batchDetailsModal').modal('hide');
                        setTimeout(() => location.reload(), 1000);
                    } else {
                        showToast('error', result.message);
                    }
                },
                error: function() {
                    showToast('error', 'Failed to approve batch');
                }
            });
        }
    }

    // Reject batch
    function rejectBatch(batchId, batchName) {
        const reason = prompt(`Please provide a reason for rejecting "${batchName}":`);
        if (reason !== null && reason.trim() !== '') {
            $.ajax({
                url: 'ajax/approve_batch.php',
                method: 'POST',
                data: {
                    batch_id: batchId,
                    notes: reason,
                    action: 'reject'
                },
                success: function(response) {
                    const result = JSON.parse(response);
                    if (result.success) {
                        showToast('success', result.message);
                        $('#batchDetailsModal').modal('hide');
                        setTimeout(() => location.reload(), 1000);
                    } else {
                        showToast('error', result.message);
                    }
                },
                error: function() {
                    showToast('error', 'Failed to reject batch');
                }
            });
        } else if (reason !== null) {
            showToast('warning', 'Please provide a rejection reason');
        }
    }

    // Toast notification function
    function showToast(type, message) {
        // Remove existing toasts
        $('.toast').remove();

        const toastHtml = `
        <div class="toast align-items-center text-white bg-${type} border-0" role="alert" style="position: fixed; bottom: 20px; right: 20px; z-index: 1055;">
            <div class="d-flex">
                <div class="toast-body">
                    <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : type === 'warning' ? 'exclamation-triangle' : 'info-circle'} me-2"></i>
                    ${message}
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        </div>
    `;

        const toast = $(toastHtml);
        $('body').append(toast);

        const bsToast = new bootstrap.Toast(toast[0], {
            delay: 3000
        });
        bsToast.show();

        toast.on('hidden.bs.toast', function() {
            $(this).remove();
        });
    }
</script>