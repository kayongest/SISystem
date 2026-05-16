// assets/js/main.js

// Global Variables
const BASE_URL = window.location.origin + '/';

// Show loading spinner
function showLoading() {
    if (!$('#spinner-overlay').length) {
        $('body').append(`
            <div id="spinner-overlay" class="spinner-overlay">
                <div class="spinner"></div>
            </div>
        `);
    }
    $('#spinner-overlay').fadeIn();
}

// Hide loading spinner
function hideLoading() {
    $('#spinner-overlay').fadeOut();
}

// Show toast notification
function showToast(type, message, title = '') {
    toastr.options = {
        "closeButton": true,
        "progressBar": true,
        "positionClass": "toast-top-right",
        "timeOut": "5000"
    };
    
    switch(type) {
        case 'success':
            toastr.success(message, title);
            break;
        case 'error':
            toastr.error(message, title);
            break;
        case 'warning':
            toastr.warning(message, title);
            break;
        case 'info':
            toastr.info(message, title);
            break;
    }
}

// Export data function
function exportData(format = 'excel') {
    showLoading();
    
    $.ajax({
        url: BASE_URL + 'api/items/export.php',
        method: 'POST',
        data: { format: format },
        xhrFields: {
            responseType: 'blob'
        },
        success: function(data) {
            hideLoading();
            const blob = new Blob([data]);
            const link = document.createElement('a');
            link.href = window.URL.createObjectURL(blob);
            link.download = `equipment_export_${new Date().toISOString().split('T')[0]}.${format === 'excel' ? 'xlsx' : 'pdf'}`;
            link.click();
            showToast('success', 'Export completed successfully');
        },
        error: function() {
            hideLoading();
            showToast('error', 'Failed to export data');
        }
    });
}

// Print QR labels
function printLabels() {
    window.open(BASE_URL + 'views/qr/labels.php', '_blank');
}

// Open QR scanner
function openScanner() {
    window.location.href = BASE_URL + 'scan.php';
}

// Initialize on document ready
$(document).ready(function() {
    // Initialize tooltips
    $('[data-bs-toggle="tooltip"]').tooltip();
    
    // Handle sidebar toggle on mobile
    $('.sidebar-toggle').click(function() {
        $('.sidebar').toggleClass('show');
    });
    
    // Auto-hide sidebar on mobile when clicking outside
    $(document).click(function(event) {
        if ($(window).width() <= 768) {
            if (!$(event.target).closest('.sidebar').length && !$(event.target).closest('.sidebar-toggle').length) {
                $('.sidebar').removeClass('show');
            }
        }
    });
});