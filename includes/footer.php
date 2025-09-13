<?php
/**
 * WorkSafety.io - Footer גלובלי
 * סגירת תגים וטעינת JavaScript
 */
?>

    </div> <!-- סגירת app-container -->

    <!-- JavaScript Files -->
    <script src="/assets/js/app.js?v=<?php echo time(); ?>"></script>
    
    <!-- Additional JavaScript if specified -->
    <?php if (isset($additionalJS) && is_array($additionalJS)): ?>
        <?php foreach ($additionalJS as $js): ?>
            <script src="<?php echo $js; ?>?v=<?php echo time(); ?>"></script>
        <?php endforeach; ?>
    <?php endif; ?>
    
    <!-- Page-specific JavaScript -->
    <?php if (isset($additionalFooter)): ?>
        <?php echo $additionalFooter; ?>
    <?php endif; ?>
    
    <!-- Global JavaScript for all pages -->
    <script>
        // הגדרות גלובליות נוספות
        document.addEventListener('DOMContentLoaded', function() {
            
            // הפעלת tooltips לכל האלמנטים עם data-tooltip
            const tooltipElements = document.querySelectorAll('[data-tooltip]');
            tooltipElements.forEach(element => {
                element.addEventListener('mouseenter', function() {
                    if (window.WorkSafety) {
                        window.WorkSafety.showTooltip(this);
                    }
                });
                
                element.addEventListener('mouseleave', function() {
                    if (window.WorkSafety) {
                        window.WorkSafety.hideTooltip();
                    }
                });
            });
            
            // טיפול בטפסים - מניעת שליחה כפולה
            const forms = document.querySelectorAll('form');
            forms.forEach(form => {
                form.addEventListener('submit', function() {
                    const submitBtn = this.querySelector('button[type="submit"], input[type="submit"]');
                    if (submitBtn) {
                        submitBtn.disabled = true;
                        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> שולח...';
                        
                        // החזרת הכפתור למצב רגיל אחרי 5 שניות (למקרה של שגיאה)
                        setTimeout(() => {
                            submitBtn.disabled = false;
                            submitBtn.innerHTML = submitBtn.getAttribute('data-original-text') || 'שלח';
                        }, 5000);
                    }
                });
            });
            
            // שמירת הטקסט המקורי של כפתורים
            const submitButtons = document.querySelectorAll('button[type="submit"], input[type="submit"]');
            submitButtons.forEach(btn => {
                btn.setAttribute('data-original-text', btn.innerHTML || btn.value);
            });
            
            // הפעלת אנימציות עבור אלמנטים שנכנסים לתצוגה
            const animatedElements = document.querySelectorAll('.card, .stat-card, .table-container');
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.style.animation = 'fadeInUp 0.6s ease forwards';
                    }
                });
            }, { threshold: 0.1 });
            
            animatedElements.forEach(el => observer.observe(el));
            
            // הוספת אנימציה CSS
            const style = document.createElement('style');
            style.textContent = `
                @keyframes fadeInUp {
                    from {
                        opacity: 0;
                        transform: translateY(30px);
                    }
                    to {
                        opacity: 1;
                        transform: translateY(0);
                    }
                }
                
                .card, .stat-card, .table-container {
                    opacity: 0;
                }
            `;
            document.head.appendChild(style);
        });
        
        // פונקציה להצגת הודעת אישור לפני מחיקה
        function confirmDelete(message = 'האם אתה בטוח שברצונך למחוק פריט זה?') {
            return confirm(message);
        }
        
        // פונקציה להצגת הודעת אישור כללית
        function confirmAction(message = 'האם אתה בטוח שברצונך לבצע פעולה זו?') {
            return confirm(message);
        }
        
        // פונקציה לפורמט מספרים עבריים
        function formatHebrewNumber(num) {
            return new Intl.NumberFormat('he-IL').format(num);
        }
        
        // פונקציה לפורמט תאריכים עבריים
        function formatHebrewDate(date) {
            return new Date(date).toLocaleDateString('he-IL', {
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            });
        }
        
        // פונקציה להדפסת דף
        function printPage() {
            window.print();
        }
        
        // פונקציה לייצוא לאקסל (דורשת שרת)
        function exportToExcel(table, filename = 'export.xlsx') {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '/api/export.php';
            form.style.display = 'none';
            
            const input1 = document.createElement('input');
            input1.name = 'table_html';
            input1.value = table.outerHTML;
            
            const input2 = document.createElement('input');
            input2.name = 'filename';
            input2.value = filename;
            
            form.appendChild(input1);
            form.appendChild(input2);
            document.body.appendChild(form);
            form.submit();
            document.body.removeChild(form);
        }
        
        // מעקב אחר זמן פעילות המשתמש
        let userActivityTimer;
        function resetActivityTimer() {
            clearTimeout(userActivityTimer);
            userActivityTimer = setTimeout(() => {
                if (confirm('היית לא פעיל לזמן ממושך. האם תרצה להישאר מחובר?')) {
                    resetActivityTimer();
                } else {
                    window.location.href = '/logout.php';
                }
            }, 30 * 60 * 1000); // 30 דקות
        }
        
        // הפעלת מעקב פעילות
        <?php if (isset($_SESSION['user_id'])): ?>
        ['mousedown', 'mousemove', 'keypress', 'scroll', 'touchstart'].forEach(event => {
            document.addEventListener(event, resetActivityTimer, true);
        });
        resetActivityTimer();
        <?php endif; ?>
        
        // פונקציה לבדיקת חיבור אינטרנט
        function checkConnection() {
            if (!navigator.onLine) {
                if (window.WorkSafety) {
                    window.WorkSafety.showNotification('אין חיבור לאינטרנט', 'warning');
                }
                return false;
            }
            return true;
        }
        
        window.addEventListener('online', () => {
            if (window.WorkSafety) {
                window.WorkSafety.showNotification('חיבור לאינטרנט הוחזר', 'success');
            }
        });
        
        window.addEventListener('offline', () => {
            if (window.WorkSafety) {
                window.WorkSafety.showNotification('אין חיבור לאינטרנט', 'warning');
            }
        });
        
        console.log('✅ WorkSafety.io Footer נטען בהצלחה');
    </script>
    
    <!-- Google Analytics (לייצור בלבד) -->
    <?php if (defined('GOOGLE_ANALYTICS_ID') && !empty(GOOGLE_ANALYTICS_ID)): ?>
    <!-- Global site tag (gtag.js) - Google Analytics -->
    <script async src="https://www.googletagmanager.com/gtag/js?id=<?php echo GOOGLE_ANALYTICS_ID; ?>"></script>
    <script>
        window.dataLayer = window.dataLayer || [];
        function gtag(){dataLayer.push(arguments);}
        gtag('js', new Date());
        gtag('config', '<?php echo GOOGLE_ANALYTICS_ID; ?>');
    </script>
    <?php endif; ?>
    
</body>
</html>
