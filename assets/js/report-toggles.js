/**
 * STELA - Shared Report Toggle Functions
 */

function toggleSection(sectionId) {
    const section = document.getElementById(sectionId);
    if (!section) return;
    
    // Attempt to get the event target that triggered this if not passed
    const button = window.event ? window.event.currentTarget : null;
    
    if (section.style.display === 'none' || section.style.display === '') {
        // Show section
        section.style.display = 'block';
        
        // Handle transitions if they exist in CSS
        if (section.style.opacity !== undefined) {
            section.offsetHeight; // Trigger reflow
            section.style.opacity = '1';
            section.style.maxHeight = '10000px';
        }

        if (button) {
            const icon = button.querySelector('i');
            const textEl = button.querySelector('.btn-toggle-text') || button.querySelector('span');
            
            if (icon) {
                icon.classList.remove('fa-chevron-down');
                icon.classList.add('fa-chevron-up');
            }
            
            if (textEl) {
                if (textEl.classList.contains('btn-toggle-text')) {
                    textEl.setAttribute('data-lang', 'hide');
                } else {
                    textEl.textContent = 'Hide All';
                }
            } else if (!icon) {
                button.innerHTML = '<i class="fas fa-chevron-up me-1"></i> Hide All';
            }
        }
        
        if (window.changeLanguage && window.getCurrentLanguage) {
            window.changeLanguage(window.getCurrentLanguage());
        }
        
        setTimeout(function() {
            if (window.jQuery && window.jQuery.fn && window.jQuery.fn.dataTable) {
                window.jQuery.fn.dataTable.tables({ visible: true, api: true }).columns.adjust();
            }
        }, 150);
        
    } else {
        // Hide section
        if (section.style.opacity !== undefined) {
            section.style.opacity = '0';
            section.style.maxHeight = '0';
        }
        
        if (button) {
            const icon = button.querySelector('i');
            const textEl = button.querySelector('.btn-toggle-text') || button.querySelector('span');
            
            if (icon) {
                icon.classList.remove('fa-chevron-up');
                icon.classList.add('fa-chevron-down');
            }
            
            if (textEl) {
                if (textEl.classList.contains('btn-toggle-text')) {
                    textEl.setAttribute('data-lang', 'view-all');
                } else {
                    textEl.textContent = 'View All';
                }
            } else if (!icon) {
                button.innerHTML = '<i class="fas fa-chevron-down me-1"></i> View All';
            }
        }
        
        if (window.changeLanguage && window.getCurrentLanguage) {
            window.changeLanguage(window.getCurrentLanguage());
        }
        
        // Wait for transition before hiding if using transitions
        if (section.style.opacity !== undefined) {
            setTimeout(() => {
                section.style.display = 'none';
            }, 400);
        } else {
            section.style.display = 'none';
        }
    }
}
