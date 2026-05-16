// modal-fix.js - Fix for Bootstrap 5 modal issues
(function() {
    'use strict';
    
    // Fix for "Illegal invocation" error
    const originalShow = bootstrap.Modal.prototype.show;
    bootstrap.Modal.prototype.show = function() {
        try {
            // Ensure modal element exists
            if (!this._element) {
                console.warn('Modal element not found');
                return;
            }
            
            // Clean up any existing backdrops
            const existingBackdrops = document.querySelectorAll('.modal-backdrop');
            existingBackdrops.forEach(backdrop => backdrop.remove());
            document.body.classList.remove('modal-open');
            document.body.style.overflow = '';
            document.body.style.paddingRight = '';
            
            // Call original show method
            return originalShow.call(this);
        } catch (error) {
            console.error('Modal show error:', error);
            // Fallback: manually show modal
            this._element.classList.add('show');
            this._element.style.display = 'block';
            document.body.classList.add('modal-open');
        }
    };
    
    // Fix for hide method
    const originalHide = bootstrap.Modal.prototype.hide;
    bootstrap.Modal.prototype.hide = function() {
        try {
            const result = originalHide.call(this);
            // Clean up after hide
            setTimeout(() => {
                const backdrops = document.querySelectorAll('.modal-backdrop');
                backdrops.forEach(backdrop => backdrop.remove());
                document.body.classList.remove('modal-open');
                document.body.style.overflow = '';
                document.body.style.paddingRight = '';
            }, 100);
            return result;
        } catch (error) {
            console.error('Modal hide error:', error);
            // Fallback cleanup
            this._element.classList.remove('show');
            this._element.style.display = 'none';
            document.querySelectorAll('.modal-backdrop').forEach(b => b.remove());
            document.body.classList.remove('modal-open');
        }
    };
    
    // Fix for backdrop click handler
    const originalBackdropHandler = bootstrap.Modal.prototype._handleBackdropClick;
    bootstrap.Modal.prototype._handleBackdropClick = function(e) {
        if (e.target === this._element) {
            this.hide();
        }
    };
})();