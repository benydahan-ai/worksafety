/*
 * WorkSafety.io - JavaScript למודול החברות
 * פונקציונליות אינטראקטיבית למודול ניהול החברות
 */

// Global variables
let companyToDelete = null;
let isFormSubmitting = false;

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    initializeCompanyModule();
});

/**
 * Initialize company module functionality
 */
function initializeCompanyModule() {
    initFormValidation();
    initFileUpload();
    initDataTables();
    initModalEvents();
    initAutoSave();
    initKeyboardShortcuts();
}

/**
 * Form validation and real-time feedback
 */
function initFormValidation() {
    const forms = document.querySelectorAll('.company-form');
    
    forms.forEach(form => {
        // Real-time validation
        const inputs = form.querySelectorAll('input, select, textarea');
        inputs.forEach(input => {
            input.addEventListener('blur', function() {
                validateField(this);
            });
            
            input.addEventListener('input', function() {
                if (this.classList.contains('error')) {
                    validateField(this);
                }
            });
        });
        
        // Form submission
        form.addEventListener('submit', function(e) {
            if (!validateForm(this)) {
                e.preventDefault();
                return false;
            }
            
            if (isFormSubmitting) {
                e.preventDefault();
                return false;
            }
            
            isFormSubmitting = true;
            showFormLoading(this);
        });
    });
}

/**
 * Validate individual field
 */
function validateField(field) {
    const value = field.value.trim();
    const type = field.type;
    const required = field.hasAttribute('required');
    let isValid = true;
    let errorMessage = '';
    
    // Remove previous error state
    field.classList.remove('error', 'success');
    removeFieldError(field);
    
    // Required field validation
    if (required && !value) {
        isValid = false;
        errorMessage = 'שדה זה הוא חובה';
    }
    
    // Type-specific validation
    if (isValid && value) {
        switch (type) {
            case 'email':
                if (!isValidEmail(value)) {
                    isValid = false;
                    errorMessage = 'כתובת אימייל לא תקינה';
                }
                break;
                
            case 'url':
                if (!isValidURL(value)) {
                    isValid = false;
                    errorMessage = 'כתובת אתר לא תקינה';
                }
                break;
                
            case 'tel':
                if (!isValidPhone(value)) {
                    isValid = false;
                    errorMessage = 'מספר טלפון לא תקין';
                }
                break;
                
            case 'number':
                const min = parseInt(field.getAttribute('min'));
                const max = parseInt(field.getAttribute('max'));
                const numValue = parseInt(value);
                
                if (isNaN(numValue)) {
                    isValid = false;
                    errorMessage = 'יש להזין מספר תקין';
                } else if (min !== null && numValue < min) {
                    isValid = false;
                    errorMessage = `הערך חייב להיות לפחות ${min}`;
                } else if (max !== null && numValue > max) {
                    isValid = false;
                    errorMessage = `הערך חייב להיות לכל היותר ${max}`;
                }
                break;
        }
    }
    
    // Custom validations
    if (isValid && field.name === 'name') {
        if (value.length < 2) {
            isValid = false;
            errorMessage = 'שם החברה חייב להכיל לפחות 2 תווים';
        }
    }
    
    // Apply validation result
    if (!isValid) {
        field.classList.add('error');
        showFieldError(field, errorMessage);
    } else if (value) {
        field.classList.add('success');
    }
    
    return isValid;
}

/**
 * Validate entire form
 */
function validateForm(form) {
    const inputs = form.querySelectorAll('input[required], select[required], textarea[required]');
    let isValid = true;
    
    inputs.forEach(input => {
        if (!validateField(input)) {
            isValid = false;
        }
    });
    
    // Scroll to first error
    if (!isValid) {
        const firstError = form.querySelector('.error');
        if (firstError) {
            firstError.scrollIntoView({ 
                behavior: 'smooth', 
                block: 'center' 
            });
            firstError.focus();
        }
    }
    
    return isValid;
}

/**
 * Show field error message
 */
function showFieldError(field, message) {
    removeFieldError(field);
    
    const errorDiv = document.createElement('div');
    errorDiv.className = 'field-error';
    errorDiv.textContent = message;
    
    field.parentNode.appendChild(errorDiv);
}

/**
 * Remove field error message
 */
function removeFieldError(field) {
    const existingError = field.parentNode.querySelector('.field-error');
    if (existingError) {
        existingError.remove();
    }
}

/**
 * File upload functionality
 */
function initFileUpload() {
    const fileInputs = document.querySelectorAll('input[type="file"]');
    
    fileInputs.forEach(input => {
        input.addEventListener('change', function() {
            handleFileUpload(this);
        });
    });
    
    // Drag and drop support
    const uploadAreas = document.querySelectorAll('.file-upload-area');
    uploadAreas.forEach(area => {
        area.addEventListener('dragover', function(e) {
            e.preventDefault();
            this.classList.add('drag-over');
        });
        
        area.addEventListener('dragleave', function(e) {
            e.preventDefault();
            this.classList.remove('drag-over');
        });
        
        area.addEventListener('drop', function(e) {
            e.preventDefault();
            this.classList.remove('drag-over');
            
            const files = e.dataTransfer.files;
            if (files.length > 0) {
                const fileInput = this.querySelector('input[type="file"]');
                if (fileInput) {
                    fileInput.files = files;
                    handleFileUpload(fileInput);
                }
            }
        });
    });
}

/**
 * Handle file upload and preview
 */
function handleFileUpload(input) {
    const file = input.files[0];
    if (!file) return;
    
    // Validate file
    if (!validateFile(file)) {
        input.value = '';
        return;
    }
    
    // Show preview
    if (file.type.startsWith('image/')) {
        previewImage(file, input);
    }
}

/**
 * Validate uploaded file
 */
function validateFile(file) {
    const maxSize = 2 * 1024 * 1024; // 2MB
    const allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
    
    if (file.size > maxSize) {
        showAlert('קובץ גדול מדי. הגודל המקסימלי הוא 2MB', 'error');
        return false;
    }
    
    if (!allowedTypes.includes(file.type)) {
        showAlert('סוג קובץ לא נתמך. רק JPG, PNG ו-GIF מותרים', 'error');
        return false;
    }
    
    return true;
}

/**
 * Preview uploaded image
 */
function previewImage(file, input) {
    const reader = new FileReader();
    
    reader.onload = function(e) {
        let preview = document.getElementById('logoPreview');
        let img = document.getElementById('logoImage');
        
        if (!preview || !img) {
            // Create preview elements
            preview = document.createElement('div');
            preview.id = 'logoPreview';
            preview.className = 'logo-preview';
            
            img = document.createElement('img');
            img.id = 'logoImage';
            img.className = 'preview-image';
            
            const removeBtn = document.createElement('button');
            removeBtn.type = 'button';
            removeBtn.className = 'btn btn-sm btn-danger';
            removeBtn.innerHTML = '<i class="fas fa-times"></i> הסר';
            removeBtn.onclick = function() {
                input.value = '';
                preview.style.display = 'none';
            };
            
            preview.appendChild(img);
            preview.appendChild(removeBtn);
            input.parentNode.appendChild(preview);
        }
        
        img.src = e.target.result;
        preview.style.display = 'block';
    };
    
    reader.readAsDataURL(file);
}

/**
 * Initialize data tables functionality
 */
function initDataTables() {
    // Search functionality
    const searchInput = document.querySelector('input[name="search"]');
    if (searchInput) {
        let searchTimeout;
        searchInput.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                performSearch();
            }, 500);
        });
    }
    
    // Sort functionality
    const sortHeaders = document.querySelectorAll('.sortable');
    sortHeaders.forEach(header => {
        header.addEventListener('click', function() {
            const column = this.dataset.column;
            const direction = this.classList.contains('asc') ? 'desc' : 'asc';
            sortTable(column, direction);
        });
    });
}

/**
 * Perform search with current filters
 */
function performSearch() {
    const form = document.querySelector('.search-form');
    if (form) {
        form.submit();
    }
}

/**
 * Sort table by column
 */
function sortTable(column, direction) {
    const url = new URL(window.location);
    url.searchParams.set('sort', column);
    url.searchParams.set('direction', direction);
    window.location.href = url.toString();
}

/**
 * Initialize modal events
 */
function initModalEvents() {
    // Close modal on background click
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('modal')) {
            closeModal(e.target);
        }
    });
    
    // Close modal on escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            const openModal = document.querySelector('.modal[style*="flex"]');
            if (openModal) {
                closeModal(openModal);
            }
        }
    });
}

/**
 * Close modal
 */
function closeModal(modal) {
    modal.style.display = 'none';
    if (modal.id === 'deleteModal') {
        companyToDelete = null;
    }
}

/**
 * Auto-save functionality for forms
 */
function initAutoSave() {
    const forms = document.querySelectorAll('.company-form');
    
    forms.forEach(form => {
        const inputs = form.querySelectorAll('input, select, textarea');
        inputs.forEach(input => {
            if (input.type !== 'file' && input.type !== 'password') {
                input.addEventListener('input', function() {
                    debounce(saveFormData, 1000)(form);
                });
            }
        });
    });
    
    // Restore form data on page load
    restoreFormData();
}

/**
 * Save form data to localStorage
 */
function saveFormData(form) {
    const formData = new FormData(form);
    const data = {};
    
    for (let [key, value] of formData.entries()) {
        if (key !== 'logo') { // Don't save file inputs
            data[key] = value;
        }
    }
    
    const formId = form.id || 'company-form';
    localStorage.setItem(`worksafety_${formId}`, JSON.stringify(data));
}

/**
 * Restore form data from localStorage
 */
function restoreFormData() {
    const forms = document.querySelectorAll('.company-form');
    
    forms.forEach(form => {
        const formId = form.id || 'company-form';
        const savedData = localStorage.getItem(`worksafety_${formId}`);
        
        if (savedData) {
            try {
                const data = JSON.parse(savedData);
                
                Object.keys(data).forEach(key => {
                    const input = form.querySelector(`[name="${key}"]`);
                    if (input && !input.value) {
                        input.value = data[key];
                    }
                });
            } catch (e) {
                console.error('Error restoring form data:', e);
            }
        }
    });
}

/**
 * Clear saved form data
 */
function clearSavedFormData(formId = 'company-form') {
    localStorage.removeItem(`worksafety_${formId}`);
}

/**
 * Initialize keyboard shortcuts
 */
function initKeyboardShortcuts() {
    document.addEventListener('keydown', function(e) {
        // Ctrl/Cmd + S to save form
        if ((e.ctrlKey || e.metaKey) && e.key === 's') {
            e.preventDefault();
            const form = document.querySelector('.company-form');
            if (form) {
                form.submit();
            }
        }
        
        // Ctrl/Cmd + N to add new company (on index page)
        if ((e.ctrlKey || e.metaKey) && e.key === 'n') {
            const addBtn = document.querySelector('a[href="add.php"]');
            if (addBtn) {
                e.preventDefault();
                window.location.href = addBtn.href;
            }
        }
    });
}

/**
 * Show form loading state
 */
function showFormLoading(form) {
    form.classList.add('form-loading');
    
    const submitBtn = form.querySelector('button[type="submit"]');
    if (submitBtn) {
        submitBtn.disabled = true;
        const originalText = submitBtn.innerHTML;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> שומר...';
        
        setTimeout(() => {
            submitBtn.innerHTML = originalText;
            submitBtn.disabled = false;
            form.classList.remove('form-loading');
            isFormSubmitting = false;
        }, 2000);
    }
}

/**
 * Show alert message
 */
function showAlert(message, type = 'info') {
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type} alert-dismissible`;
    alertDiv.innerHTML = `
        <i class="fas fa-${type === 'error' ? 'exclamation-triangle' : 'info-circle'}"></i>
        ${message}
        <button type="button" class="alert-close" onclick="this.parentElement.remove()">
            <i class="fas fa-times"></i>
        </button>
    `;
    
    const container = document.querySelector('.page-content') || document.body;
    container.insertBefore(alertDiv, container.firstChild);
    
    // Auto remove after 5 seconds
    setTimeout(() => {
        if (alertDiv.parentNode) {
            alertDiv.remove();
        }
    }, 5000);
}

/**
 * Export companies data
 */
function exportCompanies() {
    const params = new URLSearchParams(window.location.search);
    params.set('export', 'xlsx');
    
    const exportUrl = `export.php?${params.toString()}`;
    
    // Create temporary link and click it
    const link = document.createElement('a');
    link.href = exportUrl;
    link.download = `companies_${new Date().toISOString().split('T')[0]}.xlsx`;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    
    showAlert('הייצוא החל. הקובץ יורד בקרוב...', 'success');
}

/**
 * Delete company with confirmation
 */
function deleteCompany(id, name) {
    companyToDelete = id;
    document.getElementById('companyNameToDelete').textContent = name;
    document.getElementById('deleteModal').style.display = 'flex';
}

/**
 * Close delete modal
 */
function closeDeleteModal() {
    document.getElementById('deleteModal').style.display = 'none';
    companyToDelete = null;
}

/**
 * Confirm company deletion
 */
function confirmDelete() {
    if (companyToDelete) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'delete.php';
        
        const idInput = document.createElement('input');
        idInput.type = 'hidden';
        idInput.name = 'company_id';
        idInput.value = companyToDelete;
        
        form.appendChild(idInput);
        document.body.appendChild(form);
        form.submit();
    }
}

/**
 * Reset form to original values
 */
function resetForm() {
    if (confirm('האם אתה בטוח שברצונך לאפס את הטופס? כל השינויים שלא נשמרו יאבדו.')) {
        const form = document.querySelector('.company-form');
        if (form) {
            form.reset();
            
            // Clear validation states
            const inputs = form.querySelectorAll('.error, .success');
            inputs.forEach(input => {
                input.classList.remove('error', 'success');
            });
            
            // Clear error messages
            const errors = form.querySelectorAll('.field-error');
            errors.forEach(error => error.remove());
            
            // Hide logo preview
            const preview = document.getElementById('logoPreview');
            if (preview) {
                preview.style.display = 'none';
            }
            
            // Clear saved form data
            clearSavedFormData();
        }
    }
}

/**
 * Utility functions
 */

// Email validation
function isValidEmail(email) {
    const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return re.test(email);
}

// URL validation
function isValidURL(url) {
    try {
        new URL(url);
        return true;
    } catch {
        return false;
    }
}

// Phone validation (Israeli format)
function isValidPhone(phone) {
    const re = /^[\d\-\+\(\)\s]{7,20}$/;
    return re.test(phone);
}

// Debounce function
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

// Format phone number
function formatPhoneNumber(phone) {
    const cleaned = phone.replace(/\D/g, '');
    if (cleaned.length === 10) {
        return cleaned.replace(/(\d{3})(\d{3})(\d{4})/, '$1-$2-$3');
    }
    return phone;
}

// Format currency
function formatCurrency(amount) {
    return new Intl.NumberFormat('he-IL', {
        style: 'currency',
        currency: 'ILS'
    }).format(amount);
}

// Format date
function formatDate(date) {
    return new Intl.DateTimeFormat('he-IL').format(new Date(date));
}

// Show/hide elements based on subscription plan
function toggleFeaturesByPlan(plan) {
    const features = {
        basic: ['basic_features'],
        standard: ['basic_features', 'standard_features'],
        premium: ['basic_features', 'standard_features', 'premium_features'],
        enterprise: ['basic_features', 'standard_features', 'premium_features', 'enterprise_features']
    };
    
    // Hide all features
    document.querySelectorAll('[data-feature]').forEach(el => {
        el.style.display = 'none';
    });
    
    // Show relevant features
    if (features[plan]) {
        features[plan].forEach(feature => {
            document.querySelectorAll(`[data-feature="${feature}"]`).forEach(el => {
                el.style.display = 'block';
            });
        });
    }
}

// Initialize tooltips
function initTooltips() {
    const tooltips = document.querySelectorAll('[data-tooltip]');
    tooltips.forEach(element => {
        element.addEventListener('mouseenter', showTooltip);
        element.addEventListener('mouseleave', hideTooltip);
    });
}

function showTooltip(e) {
    const text = e.target.getAttribute('data-tooltip');
    const tooltip = document.createElement('div');
    tooltip.className = 'tooltip';
    tooltip.textContent = text;
    document.body.appendChild(tooltip);
    
    const rect = e.target.getBoundingClientRect();
    tooltip.style.left = rect.left + (rect.width / 2) - (tooltip.offsetWidth / 2) + 'px';
    tooltip.style.top = rect.top - tooltip.offsetHeight - 5 + 'px';
}

function hideTooltip() {
    const tooltip = document.querySelector('.tooltip');
    if (tooltip) {
        tooltip.remove();
    }
}

// Performance monitoring
function trackUserInteraction(action, element) {
    if (typeof gtag !== 'undefined') {
        gtag('event', action, {
            event_category: 'companies',
            event_label: element
        });
    }
}

// Initialize performance tracking
document.addEventListener('click', function(e) {
    if (e.target.matches('.btn, .card, .stat-card')) {
        trackUserInteraction('click', e.target.className);
    }
});

// Error handling
window.addEventListener('error', function(e) {
    console.error('JavaScript error in companies module:', e.error);
    showAlert('אירעה שגיאה לא צפויה. אנא רענן את הדף ונסה שוב.', 'error');
});

// Initialize tooltips on load
document.addEventListener('DOMContentLoaded', initTooltips);
