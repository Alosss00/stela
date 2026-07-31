// Modal functions
function openModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        // Force display to ensure modal opens
        modal.style.display = 'block';
        // Use setTimeout to ensure display is set before adding class
        setTimeout(() => {
            modal.classList.add('show');
        }, 10);
        // Prevent body scroll when modal is open
        document.body.style.overflow = 'hidden';
    }
}

function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.remove('show');
        // Wait for transition to complete before hiding
        setTimeout(() => {
            modal.style.display = 'none';
        }, 300);
        // Restore body scroll
        document.body.style.overflow = '';
    }
}

// Close modal when clicking outside
window.onclick = function(event) {
    if (event.target.classList.contains('modal')) {
        const modalId = event.target.id;
        if (modalId) {
            closeModal(modalId);
        }
    }
}

// Auto-hide alerts - DISABLED (alerts will stay permanently)
// Uncomment below to enable auto-hide after 5 seconds
/*
document.addEventListener('DOMContentLoaded', function() {
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.style.transition = 'opacity 0.5s';
            alert.style.opacity = '0';
            setTimeout(() => alert.remove(), 500);
        }, 5000);
    });
});
*/

// Confirmation for delete actions
document.querySelectorAll('a[onclick*="confirm"]').forEach(link => {
    link.addEventListener('click', function(e) {
        if (!confirm('Apakah Anda yakin?')) {
            e.preventDefault();
        }
    });
});

// Form validation
function validateForm(formId) {
    const form = document.getElementById(formId);
    if (!form) return true;
    
    const requiredFields = form.querySelectorAll('[required]');
    let isValid = true;
    
    requiredFields.forEach(field => {
        if (!field.value.trim()) {
            field.style.borderColor = 'red';
            isValid = false;
        } else {
            field.style.borderColor = '#ddd';
        }
    });
    
    return isValid;
}

// Search/Filter table
function filterTable(inputId, tableId) {
    const input = document.getElementById(inputId);
    const table = document.getElementById(tableId);
    
    if (!input || !table) return;
    
    input.addEventListener('keyup', function() {
        const filter = this.value.toLowerCase();
        const rows = table.getElementsByTagName('tr');
        
        for (let i = 1; i < rows.length; i++) {
            const cells = rows[i].getElementsByTagName('td');
            let found = false;
            
            for (let j = 0; j < cells.length; j++) {
                if (cells[j].textContent.toLowerCase().indexOf(filter) > -1) {
                    found = true;
                    break;
                }
            }
            
            rows[i].style.display = found ? '' : 'none';
        }
    });
}

// Format date input
function formatDate(date) {
    const d = new Date(date);
    let month = '' + (d.getMonth() + 1);
    let day = '' + d.getDate();
    const year = d.getFullYear();
    
    if (month.length < 2) month = '0' + month;
    if (day.length < 2) day = '0' + day;
    
    return [year, month, day].join('-');
}

// Print function
function printContent(elementId) {
    const content = document.getElementById(elementId);
    if (!content) return;
    
    const printWindow = window.open('', '', 'height=600,width=800');
    printWindow.document.write('<html><head><title>Print</title>');
    printWindow.document.write('<link rel="stylesheet" href="css/style.css">');
    printWindow.document.write('</head><body>');
    printWindow.document.write(content.innerHTML);
    printWindow.document.write('</body></html>');
    printWindow.document.close();
    printWindow.print();
}

// Export table to CSV
function exportTableToCSV(tableId, filename = 'export.csv') {
    const table = document.getElementById(tableId);
    if (!table) return;
    
    let csv = [];
    const rows = table.querySelectorAll('tr');
    
    for (let i = 0; i < rows.length; i++) {
        const row = [];
        const cols = rows[i].querySelectorAll('td, th');
        
        for (let j = 0; j < cols.length; j++) {
            row.push(cols[j].textContent);
        }
        
        csv.push(row.join(','));
    }
    
    const csvContent = csv.join('\n');
    const blob = new Blob([csvContent], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    
    a.setAttribute('hidden', '');
    a.setAttribute('href', url);
    a.setAttribute('download', filename);
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
}

// Initialize tooltips
document.addEventListener('DOMContentLoaded', function() {
    const tooltips = document.querySelectorAll('[data-tooltip]');
    tooltips.forEach(element => {
        element.addEventListener('mouseenter', function() {
            const tooltip = document.createElement('div');
            tooltip.className = 'tooltip';
            tooltip.textContent = this.getAttribute('data-tooltip');
            document.body.appendChild(tooltip);
            
            const rect = this.getBoundingClientRect();
            tooltip.style.top = rect.top - tooltip.offsetHeight - 5 + 'px';
            tooltip.style.left = rect.left + (rect.width - tooltip.offsetWidth) / 2 + 'px';
        });
        
        element.addEventListener('mouseleave', function() {
            const tooltips = document.querySelectorAll('.tooltip');
            tooltips.forEach(t => t.remove());
        });
    });
});

console.log('Sistem Surat Penunjukan loaded successfully');

// File upload validation
document.addEventListener('DOMContentLoaded', function() {
    const fileInputs = document.querySelectorAll('input[type="file"]');
    fileInputs.forEach(input => {
        input.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (!file) return;
            
            // Determine max size and allowed types based on input name/id
            let maxSize = 5 * 1024 * 1024; // 5MB default
            let allowedTypes = [];
            let errorMessage = '';
            
            // Check if this is a signature file input
            if (this.name === 'signature_file' || this.id === 'signature_file') {
                maxSize = 2 * 1024 * 1024; // 2MB for signatures
                allowedTypes = ['image/png', 'image/jpeg', 'image/jpg'];
                errorMessage = 'Tipe file tidak diizinkan! Hanya PNG, JPG, atau JPEG.';
            } else {
                // CV and other document files
                allowedTypes = ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
                errorMessage = 'Tipe file tidak diizinkan! Hanya PDF, DOC, atau DOCX.';
            }
            
            // Check file size
            if (file.size > maxSize) {
                const maxSizeMB = maxSize / (1024 * 1024);
                alert('Ukuran file terlalu besar! Maksimal ' + maxSizeMB + 'MB.');
                e.target.value = '';
                return;
            }
            
            // Check file type
            if (this.accept && !allowedTypes.includes(file.type)) {
                alert(errorMessage);
                e.target.value = '';
                return;
            }
            
            // Show file name
            const fileName = file.name;
            const fileInfo = document.createElement('small');
            fileInfo.className = 'text-muted d-block mt-1';
            fileInfo.textContent = 'File dipilih: ' + fileName;
            
            // Remove previous info if exists
            const existingInfo = this.parentElement.querySelector('.file-info');
            if (existingInfo) existingInfo.remove();
            
            fileInfo.className += ' file-info';
            this.parentElement.appendChild(fileInfo);
        });
    });
});

// Display-only cleanup for required markers.
// Keeps stars visible only on /pages/user/add_employee.php.
document.addEventListener('DOMContentLoaded', function() {
    const keepMarkerPath = /\/pages\/user\/add_employee\.php$/i;
    if (keepMarkerPath.test(window.location.pathname)) {
        return;
    }

    const cleanRequiredMarkers = function() {
        const starSpans = document.querySelectorAll('span.text-danger, span.required');
        starSpans.forEach(function(el) {
            if ((el.textContent || '').trim() === '*') {
                el.style.display = 'none';
            }
        });

        const textTargets = document.querySelectorAll('label, span, th, td, strong, small, p, option');
        textTargets.forEach(function(el) {
            const hasElementChildren = Array.prototype.some.call(el.childNodes, function(node) {
                return node.nodeType === Node.ELEMENT_NODE;
            });

            if (hasElementChildren) {
                return;
            }

            const text = el.textContent || '';
            const cleaned = text.replace(/\s+\*\s*$/, '');
            if (cleaned !== text) {
                el.textContent = cleaned;
            }
        });
    };

    cleanRequiredMarkers();
    setTimeout(cleanRequiredMarkers, 0);
    setTimeout(cleanRequiredMarkers, 250);
    setTimeout(cleanRequiredMarkers, 1000);

    const observer = new MutationObserver(function() {
        cleanRequiredMarkers();
    });

    observer.observe(document.body, {
        childList: true,
        subtree: true,
        characterData: true
    });
});
