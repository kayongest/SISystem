<!-- views/partials/footer.php -->
</div> <!-- Close main content container -->

<!-- Footer -->
<footer class="footer mt-auto py-3 bg-light border-top">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-md-6">
                <span class="text-muted">
                    &copy; <?php echo date('Y'); ?> aBility v1.0
                </span>
            </div>
            <div class="col-md-6 text-md-end">
                <span class="text-muted">
                    <i class="fas fa-server me-1"></i> PHP <?php echo phpversion(); ?>
                </span>
            </div>
        </div>
    </div>
</footer>

<!-- jQuery FIRST -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<!-- Bootstrap Bundle with Popper -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<!-- Other JavaScript libraries AFTER Bootstrap -->
<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.28/jspdf.plugin.autotable.min.js"></script>

<!-- Simple, lightweight initialization -->
<script>
    (function() {
        'use strict';

        // Initialize tooltips (lightweight)
        function initTooltips() {
            if (typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
                try {
                    const elements = document.querySelectorAll('[data-bs-toggle="tooltip"]');
                    // Limit to prevent performance issues
                    if (elements.length > 50) return;

                    Array.from(elements).forEach(el => {
                        new bootstrap.Tooltip(el);
                    });
                } catch (e) {
                    // Silent fail
                }
            }
        }

        // Configure Toastr (minimal)
        function initToastr() {
            if (typeof toastr !== 'undefined') {
                toastr.options = {
                    "closeButton": true,
                    "progressBar": false,
                    "positionClass": "toast-top-right",
                    "timeOut": "3000",
                    "showMethod": "fadeIn",
                    "hideMethod": "fadeOut"
                };
            }
        }

        // Auto-hide alerts (optimized)
        function autoHideAlerts() {
            const alerts = document.querySelectorAll('.alert.autohide');
            if (!alerts.length) return;

            // Process in next tick
            setTimeout(() => {
                alerts.forEach((alert, index) => {
                    setTimeout(() => {
                        if (alert.parentNode) {
                            alert.style.opacity = '0';
                            alert.style.transition = 'opacity 0.3s';
                            setTimeout(() => {
                                if (alert.parentNode) {
                                    alert.remove();
                                }
                            }, 300);
                        }
                    }, 5000 + (index * 200));
                });
            }, 100);
        }

        // Initialize everything
        function init() {
            // Stagger initialization to avoid blocking
            setTimeout(initTooltips, 10);
            setTimeout(initToastr, 20);
            setTimeout(autoHideAlerts, 30);
        }

        // Start initialization
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', init);
        } else {
            setTimeout(init, 0);
        }

    })();
</script>
</body>

</html>