/**
 * STELA Global Action Handler
 * 
 * Handles global loading states and double-click prevention
 * Applies to forms and action buttons across all roles.
 */

document.addEventListener('DOMContentLoaded', function() {
    // 1. Override form.submit() for programmatic submissions
    const originalSubmit = HTMLFormElement.prototype.submit;
    HTMLFormElement.prototype.submit = function() {
        showLoadingStateOnButtons(this);
        originalSubmit.call(this);
    };

    // 2. Handle native form submit events
    document.addEventListener('submit', function(e) {
        if (e.target.tagName === 'FORM') {
            showLoadingStateOnButtons(e.target);
        }
    });

    // 3. Handle action links/buttons that don't use forms (e.g., links that redirect to perform an action)
    document.addEventListener('click', function(e) {
        // Find closest button or link with action-like classes
        const actionBtn = e.target.closest('.btn-action, .submit-btn, .resubmit-btn, .btn-accept, .btn-reject, .btn-verify, a[href*="action="]');
        
        if (actionBtn && actionBtn.tagName === 'A') {
            // Prevent double click on links
            if (actionBtn.classList.contains('processing')) {
                e.preventDefault();
                return;
            }
            
            // If it's a simple link without an onclick handler (meaning it just navigates)
            if (!actionBtn.hasAttribute('onclick') && !actionBtn.hasAttribute('data-bs-toggle') && !actionBtn.classList.contains('btn-action-emp') && !actionBtn.classList.contains('btn-action-positions')) {
                // Special check for action buttons that just open modals or tooltips, we don't want to show processing for them.
                // Action links that actually perform actions usually have specific hrefs.
                // We'll apply this to hrefs containing 'action=' or specific known buttons.
                if (actionBtn.href && (actionBtn.href.includes('action=') || actionBtn.classList.contains('resubmit-btn') || actionBtn.classList.contains('submit-btn'))) {
                    setButtonToLoadingState(actionBtn);
                }
            }
        }
    });

    /**
     * Finds submit buttons within a form and sets them to loading state
     */
    function showLoadingStateOnButtons(formElement) {
        // Find all buttons that could have triggered the submit
        // or just disable all primary action buttons in the form
        const submitButtons = formElement.querySelectorAll('button[type="submit"], button[onclick*="submit"], .btn-accept, .btn-reject, .btn-verify, .btn-primary, .btn-success, .btn-danger, .btn-action, .submit-btn');
        
        submitButtons.forEach(btn => {
            setButtonToLoadingState(btn);
        });
    }

    /**
     * Transforms a button into a loading state
     */
    function setButtonToLoadingState(btn) {
        // Store original content if not already stored
        if (!btn.dataset.originalContent) {
            btn.dataset.originalContent = btn.innerHTML;
        }
        
        // Add processing class
        btn.classList.add('processing');
        
        // Determine text based on language helper if available, otherwise default
        const processingText = (typeof window.getLanguageText === 'function') 
            ? window.getLanguageText('processing', 'Processing...') 
            : 'Processing...';

        // Set loading HTML
        btn.innerHTML = `<i class="fas fa-spinner fa-spin"></i> <span>${processingText}</span>`;
        
        // Disable the button shortly after to allow form data to be gathered
        setTimeout(() => {
            if (btn.tagName === 'BUTTON') {
                btn.disabled = true;
            }
            btn.style.pointerEvents = 'none';
            btn.style.opacity = '0.7';
        }, 50);
    }
});
