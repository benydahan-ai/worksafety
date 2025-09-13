/**
 * WorkSafety.io - מערכת ניהול בטיחות תעשייתית
 * JavaScript גלובלי מודרני
 * 
 * @version 1.0
 * @date 2025
 */

class WorkSafetyApp {
    constructor() {
        this.sidebar = document.querySelector('.sidebar');
        this.mainContent = document.querySelector('.main-content');
        this.sidebarToggle = document.querySelector('.sidebar-toggle');
        this.isMobile = window.innerWidth <= 768;
        this.isRTL = document.documentElement.dir === 'rtl' || document.body.dir === 'rtl';
        
        this.init();
    }
    
    init() {
        this.setupEventListeners();
        this.initSidebar();
        this.initAnimations();
        this.initTooltips();
        this.initCharts();
        this.checkMobileView();
        
        // הוספת כלאס fade-in לכל הדף
        document.body.classList.add('fade-in');
        
        console.log('🚀 WorkSafety.io מערכת הופעלה בהצלחה');
    }
    
    setupEventListeners() {
        // טוגל sidebar
        if (this.sidebarToggle) {
            this.sidebarToggle.addEventListener('click', () => this.toggleSidebar());
        }
        
        // רספונסיביות
        window.addEventListener('resize', () => this.handleResize());
        
        // טיפול בלחיצות ESC
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                this.closeModals();
            }
        });
        
        // טיפול בטפסים
        this.setupForms();
        
        // טיפול בלינקים של ניווט
        this.setupNavigation();
    }
    
    // ניהול Sidebar
    toggleSidebar() {
        if (this.isMobile) {
            this.sidebar.classList.toggle('mobile-open');
            this.createOverlay();
        } else {
            this.sidebar.classList.toggle('collapsed');
            localStorage.setItem('sidebarCollapsed', this.sidebar.classList.contains('collapsed'));
        }
    }
    
    initSidebar() {
        // שחזור מצב sidebar מ-localStorage
        const isCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
        if (isCollapsed && !this.isMobile) {
            this.sidebar.classList.add('collapsed');
        }
        
        // הדגשת הלינק הפעיל
        this.highlightActiveNavItem();
    }
    
    highlightActiveNavItem() {
        const currentPath = window.location.pathname;
        const navLinks = document.querySelectorAll('.nav-link');
        
        navLinks.forEach(link => {
            link.classList.remove('active');
            if (link.getAttribute('href') === currentPath) {
                link.classList.add('active');
            }
        });
    }
    
    // יצירת overlay למובייל
    createOverlay() {
        let overlay = document.querySelector('.sidebar-overlay');
        
        if (!overlay && this.sidebar.classList.contains('mobile-open')) {
            overlay = document.createElement('div');
            overlay.className = 'sidebar-overlay';
            overlay.style.cssText = `
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0, 0, 0, 0.5);
                z-index: 999;
                backdrop-filter: blur(4px);
            `;
            
            overlay.addEventListener('click', () => {
                this.sidebar.classList.remove('mobile-open');
                overlay.remove();
            });
            
            document.body.appendChild(overlay);
        } else if (overlay && !this.sidebar.classList.contains('mobile-open')) {
            overlay.remove();
        }
    }
    
    // רספונסיביות
    handleResize() {
        const wasMobile = this.isMobile;
        this.isMobile = window.innerWidth <= 768;
        
        if (wasMobile !== this.isMobile) {
            this.checkMobileView();
        }
    }
    
    checkMobileView() {
        const overlay = document.querySelector('.sidebar-overlay');
        if (overlay) overlay.remove();
        
        if (this.isMobile) {
            this.sidebar.classList.remove('collapsed');
            this.sidebar.classList.remove('mobile-open');
        }
    }
    
    // אנימציות
    initAnimations() {
        // Intersection Observer לאנימציות
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('fade-in');
                }
            });
        }, { threshold: 0.1 });
        
        // מעקב אחר כרטיסים וטבלאות
        document.querySelectorAll('.card, .table-container, .stat-card').forEach(el => {
            observer.observe(el);
        });
    }
    
    // Tooltips
    initTooltips() {
        const elementsWithTooltip = document.querySelectorAll('[data-tooltip]');
        
        elementsWithTooltip.forEach(element => {
            element.addEventListener('mouseenter', (e) => {
                this.showTooltip(e.target);
            });
            
            element.addEventListener('mouseleave', () => {
                this.hideTooltip();
            });
        });
    }
    
    showTooltip(element) {
        const text = element.getAttribute('data-tooltip');
        if (!text) return;
        
        const tooltip = document.createElement('div');
        tooltip.className = 'tooltip';
        tooltip.textContent = text;
        tooltip.style.cssText = `
            position: absolute;
            background: #334155;
            color: white;
            padding: 8px 12px;
            border-radius: 6px;
            font-size: 14px;
            z-index: 10000;
            pointer-events: none;
            white-space: nowrap;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        `;
        
        document.body.appendChild(tooltip);
        
        const rect = element.getBoundingClientRect();
        const tooltipRect = tooltip.getBoundingClientRect();
        
        let top = rect.top - tooltipRect.height - 8;
        let left = rect.left + (rect.width - tooltipRect.width) / 2;
        
        // התאמה לגבולות המסך
        if (top < 0) {
            top = rect.bottom + 8;
        }
        if (left < 0) {
            left = 8;
        }
        if (left + tooltipRect.width > window.innerWidth) {
            left = window.innerWidth - tooltipRect.width - 8;
        }
        
        tooltip.style.top = `${top}px`;
        tooltip.style.left = `${left}px`;
    }
    
    hideTooltip() {
        const tooltip = document.querySelector('.tooltip');
        if (tooltip) {
            tooltip.remove();
        }
    }
    
    // ניהול טפסים
    setupForms() {
        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', (e) => {
                if (!this.validateForm(form)) {
                    e.preventDefault();
                }
            });
        });
        
        // התמקדות אוטומטית בשדה הראשון
        const firstInput = document.querySelector('input:not([type="hidden"]), textarea, select');
        if (firstInput) {
            firstInput.focus();
        }
    }
    
    validateForm(form) {
        let isValid = true;
        const inputs = form.querySelectorAll('input, textarea, select');
        
        inputs.forEach(input => {
            if (input.hasAttribute('required') && !input.value.trim()) {
                this.showFieldError(input, 'שדה זה הוא חובה');
                isValid = false;
            }
        });
        
        return isValid;
    }
    
    showFieldError(field, message) {
        // הסרת שגיאות קיימות
        const existingError = field.parentNode.querySelector('.field-error');
        if (existingError) {
            existingError.remove();
        }
        
        // יצירת הודעת שגיאה
        const error = document.createElement('div');
        error.className = 'field-error';
        error.textContent = message;
        error.style.cssText = `
            color: #ef4444;
            font-size: 14px;
            margin-top: 4px;
            display: block;
        `;
        
        field.parentNode.appendChild(error);
        field.style.borderColor = '#ef4444';
        
        // הסרת השגיאה כשהמשתמש מתחיל להקליד
        field.addEventListener('input', () => {
            error.remove();
            field.style.borderColor = '';
        }, { once: true });
    }
    
    // ניהול ניווט
    setupNavigation() {
        document.querySelectorAll('.nav-link').forEach(link => {
            link.addEventListener('click', (e) => {
                // הוספת אפקט loading
                const icon = link.querySelector('.nav-icon');
                if (icon && !icon.classList.contains('fa-spinner')) {
                    const originalClass = icon.className;
                    icon.className = 'nav-icon fas fa-spinner fa-spin';
                    
                    setTimeout(() => {
                        icon.className = originalClass;
                    }, 500);
                }
            });
        });
    }
    
    // ניהול modals
    closeModals() {
        document.querySelectorAll('.modal').forEach(modal => {
            modal.style.display = 'none';
        });
    }
    
    // ניהול גרפים
    initCharts() {
        // אם יש Chart.js זמין
        if (typeof Chart !== 'undefined') {
            this.setupCharts();
        }
    }
    
    setupCharts() {
        // הגדרות בסיסיות לגרפים
        Chart.defaults.font.family = 'Assistant';
        Chart.defaults.plugins.legend.rtl = true;
        Chart.defaults.plugins.legend.textDirection = 'rtl';
    }
    
    // פונקציות עזר ציבוריות
    showNotification(message, type = 'info', duration = 5000) {
        const notification = document.createElement('div');
        notification.className = `notification notification-${type}`;
        notification.innerHTML = `
            <div class="notification-content">
                <i class="fas ${this.getNotificationIcon(type)}"></i>
                <span>${message}</span>
            </div>
            <button class="notification-close">
                <i class="fas fa-times"></i>
            </button>
        `;
        
        notification.style.cssText = `
            position: fixed;
            top: 20px;
            left: 20px;
            right: 20px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
            padding: 16px;
            z-index: 10000;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-right: 4px solid ${this.getNotificationColor(type)};
            animation: slideInDown 0.3s ease;
        `;
        
        document.body.appendChild(notification);
        
        // סגירה אוטומטית
        setTimeout(() => {
            notification.style.animation = 'slideOutUp 0.3s ease';
            setTimeout(() => notification.remove(), 300);
        }, duration);
        
        // סגירה ידנית
        notification.querySelector('.notification-close').addEventListener('click', () => {
            notification.remove();
        });
    }
    
    getNotificationIcon(type) {
        const icons = {
            success: 'fa-check-circle',
            warning: 'fa-exclamation-triangle',
            error: 'fa-exclamation-circle',
            info: 'fa-info-circle'
        };
        return icons[type] || icons.info;
    }
    
    getNotificationColor(type) {
        const colors = {
            success: '#10b981',
            warning: '#f59e0b',
            error: '#ef4444',
            info: '#06b6d4'
        };
        return colors[type] || colors.info;
    }
    
    // פונקציה לטעינת תוכן דינמי
    async loadContent(url, targetElement) {
        try {
            const response = await fetch(url);
            const content = await response.text();
            
            if (targetElement) {
                targetElement.innerHTML = content;
                targetElement.classList.add('fade-in');
            }
            
            return content;
        } catch (error) {
            console.error('שגיאה בטעינת תוכן:', error);
            this.showNotification('שגיאה בטעינת התוכן', 'error');
        }
    }
    
    // פונקציה לשמירת מצב
    saveState(key, value) {
        localStorage.setItem(`worksafety_${key}`, JSON.stringify(value));
    }
    
    loadState(key, defaultValue = null) {
        const saved = localStorage.getItem(`worksafety_${key}`);
        return saved ? JSON.parse(saved) : defaultValue;
    }
    
    // פונקציה לפורמט תאריכים עבריים
    formatHebrewDate(date) {
        const options = {
            year: 'numeric',
            month: 'long',
            day: 'numeric',
            timeZone: 'Asia/Jerusalem'
        };
        return new Date(date).toLocaleDateString('he-IL', options);
    }
    
    formatHebrewDateTime(date) {
        const options = {
            year: 'numeric',
            month: 'long',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
            timeZone: 'Asia/Jerusalem'
        };
        return new Date(date).toLocaleDateString('he-IL', options);
    }
}

// CSS נוסף לאנימציות
const additionalStyles = `
@keyframes slideInDown {
    from {
        transform: translateY(-100%);
        opacity: 0;
    }
    to {
        transform: translateY(0);
        opacity: 1;
    }
}

@keyframes slideOutUp {
    from {
        transform: translateY(0);
        opacity: 1;
    }
    to {
        transform: translateY(-100%);
        opacity: 0;
    }
}

.notification {
    font-family: 'Assistant', sans-serif !important;
    direction: rtl;
}

.notification-content {
    display: flex;
    align-items: center;
    gap: 12px;
}

.notification-close {
    background: none;
    border: none;
    cursor: pointer;
    opacity: 0.6;
    transition: opacity 0.3s ease;
}

.notification-close:hover {
    opacity: 1;
}
`;

// הוספת הסגנונות לדף
const styleSheet = document.createElement('style');
styleSheet.textContent = additionalStyles;
document.head.appendChild(styleSheet);

// אתחול המערכת כשהדף נטען
document.addEventListener('DOMContentLoaded', () => {
    window.WorkSafety = new WorkSafetyApp();
});

// פונקציות גלובליות נוחות
window.showNotification = (message, type, duration) => {
    if (window.WorkSafety) {
        window.WorkSafety.showNotification(message, type, duration);
    }
};

window.loadContent = (url, targetElement) => {
    if (window.WorkSafety) {
        return window.WorkSafety.loadContent(url, targetElement);
    }
};

window.formatHebrewDate = (date) => {
    if (window.WorkSafety) {
        return window.WorkSafety.formatHebrewDate(date);
    }
};

// פונקציות עזר נוספות
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

function throttle(func, limit) {
    let inThrottle;
    return function(...args) {
        if (!inThrottle) {
            func.apply(this, args);
            inThrottle = true;
            setTimeout(() => inThrottle = false, limit);
        }
    };
}

// פונקציות לטיפול בנתונים
function formatNumber(num) {
    return new Intl.NumberFormat('he-IL').format(num);
}

function formatCurrency(amount) {
    return new Intl.NumberFormat('he-IL', {
        style: 'currency',
        currency: 'ILS'
    }).format(amount);
}

// פונקצייות חיפוש גלובליות
function globalSearch(query, container = document) {
    const elements = container.querySelectorAll('[data-searchable]');
    const results = [];
    
    elements.forEach(element => {
        const text = element.textContent.toLowerCase();
        if (text.includes(query.toLowerCase())) {
            results.push(element);
        }
    });
    
    return results;
}

console.log('✅ WorkSafety.io JavaScript נטען בהצלחה');
