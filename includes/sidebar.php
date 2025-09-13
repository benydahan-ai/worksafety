<?php
/**
 * WorkSafety.io - Sidebar Navigation
 * תפריט ניווט מודרני עם הרשאות דינמיות
 */

// וידוא שהמשתמש מחובר
if (!isset($_SESSION['user_id'])) {
    header('Location: /login.php');
    exit;
}

// קבלת פרטי המשתמש והחברה
$currentUser = $currentUser ?? null;
$userPermissions = [];

if ($currentUser) {
    // קבלת הרשאות המשתמש (אם הטבלה קיימת)
    try {
        $stmt = $db->query("
            SELECT p.permission_name, p.module_name 
            FROM user_permissions up 
            JOIN permissions p ON up.permission_id = p.id 
            WHERE up.user_id = ? AND up.is_active = 1
        ", [$currentUser['id']]);
        
        while ($perm = $stmt->fetch()) {
            if (!isset($userPermissions[$perm['module_name']])) {
                $userPermissions[$perm['module_name']] = [];
            }
            $userPermissions[$perm['module_name']][] = $perm['permission_name'];
        }
    } catch (Exception $e) {
        // אם הטבלאות עדיין לא קיימות, לא נעשה כלום
        error_log("Permissions error (normal during setup): " . $e->getMessage());
    }
}

// פונקציה לבדיקת הרשאה
function hasPermission($module, $permission = 'view') {
    global $userPermissions, $currentUser;
    
    // מנהל ראשי רואה הכל תמיד
    if ($currentUser && isset($currentUser['role']) && $currentUser['role'] === 'super_admin') {
        return true;
    }
    
    // אם אין מערכת הרשאות עדיין - הצג הכל למנהל ראשי
    if (!isset($userPermissions) || empty($userPermissions)) {
        if ($currentUser && isset($currentUser['role']) && $currentUser['role'] === 'super_admin') {
            return true;
        }
        // אם אין הרשאות וזה לא super_admin, תן גישה בסיסית
        return in_array($module, ['companies', 'contractors', 'worksites', 'users', 'employees']);
    }
    
    return isset($userPermissions[$module]) && in_array($permission, $userPermissions[$module]);
}

// קבלת סטטיסטיקות מהירות למטרת התצוגה
$quickStats = [
    'open_deficiencies' => 0,
    'pending_inspections' => 0,
    'overdue_tasks' => 0,
    'active_sites' => 0
];

try {
    // ניסיון לקבל סטטיסטיקות (אם הטבלאות קיימות)
    $companyFilter = ($currentUser['role'] ?? '') === 'super_admin' ? '' : ' AND company_id = ' . intval($currentUser['company_id'] ?? 0);
    
    // ליקויים פתוחים
    $tableExists = $db->query("SHOW TABLES LIKE 'deficiencies'")->fetch();
    if ($tableExists) {
        $stmt = $db->query("SELECT COUNT(*) as count FROM deficiencies WHERE status = 'open'" . $companyFilter);
        $quickStats['open_deficiencies'] = $stmt->fetch()['count'] ?? 0;
    }
    
    // בדיקות ממתינות
    $tableExists = $db->query("SHOW TABLES LIKE 'inspections'")->fetch();
    if ($tableExists) {
        $stmt = $db->query("SELECT COUNT(*) as count FROM inspections WHERE status = 'pending'" . $companyFilter);
        $quickStats['pending_inspections'] = $stmt->fetch()['count'] ?? 0;
    }
    
    // משימות שפג תוקפן
    $tableExists = $db->query("SHOW TABLES LIKE 'tasks'")->fetch();
    if ($tableExists) {
        $stmt = $db->query("SELECT COUNT(*) as count FROM tasks WHERE due_date < NOW() AND status != 'completed'" . $companyFilter);
        $quickStats['overdue_tasks'] = $stmt->fetch()['count'] ?? 0;
    }
    
    // אתרים פעילים
    $tableExists = $db->query("SHOW TABLES LIKE 'worksites'")->fetch();
    if ($tableExists) {
        $stmt = $db->query("SELECT COUNT(*) as count FROM worksites WHERE status = 'active'" . $companyFilter);
        $quickStats['active_sites'] = $stmt->fetch()['count'] ?? 0;
    }
    
} catch (Exception $e) {
    error_log("Quick stats error (normal during setup): " . $e->getMessage());
}
?>

<!-- Sidebar Navigation -->
<nav class="sidebar" id="sidebar">
    <!-- כפתור קיפול -->
    <button class="sidebar-toggle" id="sidebarToggle" data-tooltip="הצג/הסתר תפריט">
        <i class="fas fa-chevron-right"></i>
    </button>
    
    <!-- לוגו וכותרת החברה -->
    <div class="sidebar-logo">
        <?php if ($currentUser && !empty($currentUser['company_logo'])): ?>
            <img src="/uploads/logos/<?php echo htmlspecialchars($currentUser['company_logo']); ?>" 
                 alt="<?php echo htmlspecialchars($currentUser['company_name']); ?>" 
                 class="company-logo">
        <?php else: ?>
            <img src="/assets/images/worksafety-logo.png" 
                 alt="WorkSafety.io" 
                 class="company-logo"
                 onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
            <div class="default-logo" style="display: none; width: 80px; height: 80px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 50%; color: white; align-items: center; justify-content: center; font-size: 2rem; font-weight: bold;">
                <i class="fas fa-shield-alt"></i>
            </div>
        <?php endif; ?>
        
        <div class="company-name">
            <?php echo htmlspecialchars($currentUser['company_name'] ?? 'WorkSafety.io'); ?>
        </div>
        
        <?php if ($currentUser): ?>
            <div class="user-info">
                <small class="user-role">
                    <?php 
                    $roleNames = [
                        'super_admin' => 'מנהל ראשי',
                        'company_admin' => 'מנהל חברה', 
                        'contractor' => 'קבלן',
                        'safety_manager' => 'מנהל בטיחות',
                        'inspector' => 'מפקח',
                        'worker' => 'עובד'
                    ];
                    echo $roleNames[$currentUser['role'] ?? 'worker'] ?? ($currentUser['role'] ?? 'משתמש');
                    ?>
                </small>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- תפריט ניווט ראשי -->
    <div class="sidebar-nav">
        
        <!-- דשבורד ראשי -->
        <div class="nav-section">
            <div class="nav-item">
                <a href="/index.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'index.php' || basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? 'active' : ''; ?>">
                    <i class="nav-icon fas fa-tachometer-alt"></i>
                    <span class="nav-text">דשבורד ראשי</span>
                </a>
            </div>
        </div>
        
        <!-- ניהול הארגון -->
        <div class="nav-section">
            <h3 class="nav-section-title">ניהול הארגון</h3>
            
            <!-- ניהול חברות - רק למנהל ראשי -->
            <?php if (hasPermission('companies')): ?>
            <div class="nav-item">
                <a href="/modules/companies/" class="nav-link">
                    <i class="nav-icon fas fa-building"></i>
                    <span class="nav-text">ניהול חברות</span>
                </a>
            </div>
            <?php endif; ?>
            
            <!-- ניהול קבלנים -->
            <?php if (hasPermission('contractors')): ?>
            <div class="nav-item">
                <a href="/modules/contractors/" class="nav-link">
                    <i class="nav-icon fas fa-hard-hat"></i>
                    <span class="nav-text">ניהול קבלנים</span>
                </a>
            </div>
            <?php endif; ?>
            
            <!-- ניהול משתמשים -->
            <?php if (hasPermission('users')): ?>
            <div class="nav-item">
                <a href="/modules/users/" class="nav-link">
                    <i class="nav-icon fas fa-users"></i>
                    <span class="nav-text">ניהול משתמשים</span>
                </a>
            </div>
            <?php endif; ?>
            
            <!-- ניהול עובדים -->
            <?php if (hasPermission('employees')): ?>
            <div class="nav-item">
                <a href="/modules/employees/" class="nav-link">
                    <i class="nav-icon fas fa-id-card"></i>
                    <span class="nav-text">ניהול עובדים</span>
                </a>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- אתרים וציוד -->
        <div class="nav-section">
            <h3 class="nav-section-title">אתרים וציוד</h3>
            
            <!-- אתרי עבודה -->
            <?php if (hasPermission('worksites')): ?>
            <div class="nav-item">
                <a href="/modules/worksites/" class="nav-link">
                    <i class="nav-icon fas fa-map-marker-alt"></i>
                    <span class="nav-text">אתרי עבודה</span>
                    <?php if ($quickStats['active_sites'] > 0): ?>
                        <span class="nav-badge"><?php echo $quickStats['active_sites']; ?></span>
                    <?php endif; ?>
                </a>
            </div>
            <?php endif; ?>
            
            <!-- ציוד וכלי עבודה -->
            <?php if (hasPermission('equipment')): ?>
            <div class="nav-item">
                <a href="/modules/equipment/" class="nav-link">
                    <i class="nav-icon fas fa-tools"></i>
                    <span class="nav-text">ציוד וכלי עבודה</span>
                </a>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- בטיחות ובקרות -->
        <div class="nav-section">
            <h3 class="nav-section-title">בטיחות ובקרות</h3>
            
            <!-- בקרות כיבוי אש -->
            <?php if (hasPermission('fire_safety')): ?>
            <div class="nav-item">
                <a href="/modules/fire_safety/" class="nav-link">
                    <i class="nav-icon fas fa-fire-extinguisher"></i>
                    <span class="nav-text">בקרות כיבוי אש</span>
                </a>
            </div>
            <?php endif; ?>
            
            <!-- תיק בטיחות לאתר -->
            <?php if (hasPermission('safety_files')): ?>
            <div class="nav-item">
                <a href="/modules/safety_files/" class="nav-link">
                    <i class="nav-icon fas fa-folder-open"></i>
                    <span class="nav-text">תיק בטיחות לאתר</span>
                </a>
            </div>
            <?php endif; ?>
            
            <!-- ניהול ליקויים -->
            <?php if (hasPermission('deficiencies')): ?>
            <div class="nav-item">
                <a href="/modules/deficiencies/" class="nav-link">
                    <i class="nav-icon fas fa-exclamation-triangle"></i>
                    <span class="nav-text">ניהול ליקויים</span>
                    <?php if ($quickStats['open_deficiencies'] > 0): ?>
                        <span class="nav-badge nav-badge-danger"><?php echo $quickStats['open_deficiencies']; ?></span>
                    <?php endif; ?>
                </a>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- דוחות ותמציות -->
        <div class="nav-section">
            <h3 class="nav-section-title">דוחות וניתוח</h3>
            
            <!-- תמציות מידע -->
            <?php if (hasPermission('summaries')): ?>
            <div class="nav-item">
                <a href="/modules/summaries/" class="nav-link">
                    <i class="nav-icon fas fa-file-pdf"></i>
                    <span class="nav-text">תמציות מידע</span>
                </a>
            </div>
            <?php endif; ?>
            
            <!-- סטטיסטיקות -->
            <?php if (hasPermission('statistics')): ?>
            <div class="nav-item">
                <a href="/modules/statistics/" class="nav-link">
                    <i class="nav-icon fas fa-chart-bar"></i>
                    <span class="nav-text">סטטיסטיקות</span>
                </a>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- כלים מתקדמים -->
        <div class="nav-section">
            <h3 class="nav-section-title">כלים מתקדמים</h3>
            
            <!-- מחולל טפסים -->
            <?php if (hasPermission('forms')): ?>
            <div class="nav-item">
                <a href="/modules/forms/" class="nav-link">
                    <i class="nav-icon fas fa-edit"></i>
                    <span class="nav-text">מחולל טפסים</span>
                    <span class="nav-badge nav-badge-warning">חדש</span>
                </a>
            </div>
            <?php endif; ?>
            
            <!-- הרשאות מתקדמות -->
            <?php if (hasPermission('users', 'manage') || ($currentUser['role'] ?? '') === 'super_admin'): ?>
            <div class="nav-item">
                <a href="/modules/users/permissions.php" class="nav-link">
                    <i class="nav-icon fas fa-key"></i>
                    <span class="nav-text">ניהול הרשאות</span>
                </a>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- הגדרות ומידע -->
        <div class="nav-section">
            <h3 class="nav-section-title">מערכת</h3>
            
            <div class="nav-item">
                <a href="/profile.php" class="nav-link">
                    <i class="nav-icon fas fa-user-circle"></i>
                    <span class="nav-text">פרופיל אישי</span>
                </a>
            </div>
            
            <?php if ($currentUser && in_array($currentUser['role'] ?? '', ['super_admin', 'company_admin'])): ?>
            <div class="nav-item">
                <a href="/settings.php" class="nav-link">
                    <i class="nav-icon fas fa-cog"></i>
                    <span class="nav-text">הגדרות מערכת</span>
                </a>
            </div>
            <?php endif; ?>
            
            <div class="nav-item">
                <a href="/help.php" class="nav-link">
                    <i class="nav-icon fas fa-question-circle"></i>
                    <span class="nav-text">עזרה ותמיכה</span>
                </a>
            </div>
            
            <div class="nav-item">
                <a href="/logout.php" class="nav-link" onclick="return confirm('האם אתה בטוח שברצונך להתנתק?')">
                    <i class="nav-icon fas fa-sign-out-alt"></i>
                    <span class="nav-text">התנתקות</span>
                </a>
            </div>
        </div>
        
        <!-- מידע מהיר -->
        <?php if (!empty(array_filter($quickStats))): ?>
        <div class="nav-section">
            <div class="quick-stats">
                <?php if ($quickStats['pending_inspections'] > 0): ?>
                <div class="quick-stat-item">
                    <div class="quick-stat-icon">
                        <i class="fas fa-clipboard-list"></i>
                    </div>
                    <div class="quick-stat-info">
                        <div class="quick-stat-value"><?php echo $quickStats['pending_inspections']; ?></div>
                        <div class="quick-stat-label">בדיקות ממתינות</div>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if ($quickStats['overdue_tasks'] > 0): ?>
                <div class="quick-stat-item urgent">
                    <div class="quick-stat-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="quick-stat-info">
                        <div class="quick-stat-value"><?php echo $quickStats['overdue_tasks']; ?></div>
                        <div class="quick-stat-label">משימות בפיגור</div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</nav>

<style>
/* סגנונות נוספים לסייד-בר */
.user-info {
    margin-top: 8px;
    text-align: center;
}

.user-role {
    color: var(--text-light);
    font-size: 0.8rem;
    font-weight: 500;
}

.default-logo {
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto;
}

.quick-stats {
    padding: var(--space-md);
    margin-top: var(--space-lg);
}

.quick-stat-item {
    display: flex;
    align-items: center;
    gap: var(--space-sm);
    padding: var(--space-sm);
    margin-bottom: var(--space-sm);
    background: var(--bg-tertiary);
    border-radius: var(--radius-md);
    font-size: 0.85rem;
    transition: var(--transition-fast);
}

.quick-stat-item:hover {
    background: var(--bg-secondary);
}

.quick-stat-item.urgent {
    background: rgba(239, 68, 68, 0.1);
    border: 1px solid rgba(239, 68, 68, 0.2);
}

.quick-stat-icon {
    width: 24px;
    height: 24px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    background: var(--primary-color);
    color: white;
    font-size: 0.75rem;
}

.quick-stat-item.urgent .quick-stat-icon {
    background: var(--danger-color);
}

.quick-stat-value {
    font-weight: 600;
    color: var(--text-primary);
    line-height: 1;
}

.quick-stat-label {
    color: var(--text-light);
    font-size: 0.75rem;
    line-height: 1;
}

.sidebar.collapsed .quick-stats {
    display: none;
}

.sidebar.collapsed .user-info {
    display: none;
}

/* תגיות (badges) משופרות */
.nav-badge {
    background-color: var(--primary-color);
    color: var(--text-white);
    font-size: 0.7rem;
    padding: 0.2rem 0.5rem;
    border-radius: 1rem;
    margin-right: auto;
    min-width: 20px;
    text-align: center;
    font-weight: 600;
}

.nav-badge-danger {
    background-color: var(--danger-color);
}

.nav-badge-warning {
    background-color: var(--warning-color);
}

.sidebar.collapsed .nav-badge {
    display: none;
}

/* אפקט hover משופר לכפתורי ניווט */
.nav-link {
    border-radius: var(--radius-md);
    margin: 0 var(--space-sm);
}

.nav-link:hover {
    background: linear-gradient(90deg, var(--bg-tertiary), transparent);
}

.nav-link.active {
    background: linear-gradient(90deg, var(--primary-color), var(--primary-light));
    box-shadow: var(--shadow-md);
}

/* אנימציה לאייקונים */
.nav-icon {
    transition: var(--transition-fast);
}

.nav-link:hover .nav-icon {
    transform: scale(1.1);
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // הדגשת הלינק הפעיל בהתאם לכתובת הנוכחית
    const currentPath = window.location.pathname;
    const navLinks = document.querySelectorAll('.nav-link');
    
    navLinks.forEach(link => {
        const linkPath = link.getAttribute('href');
        if (linkPath && (currentPath === linkPath || (linkPath !== '/' && currentPath.startsWith(linkPath)))) {
            link.classList.add('active');
        }
    });
    
    // עדכון כיוון החץ בכפתור הקיפול
    const sidebarToggle = document.getElementById('sidebarToggle');
    const sidebar = document.getElementById('sidebar');
    
    if (sidebarToggle && sidebar) {
        sidebarToggle.addEventListener('click', function() {
            const icon = this.querySelector('i');
            const isCollapsed = sidebar.classList.contains('collapsed');
            
            if (isCollapsed) {
                icon.className = 'fas fa-chevron-right';
            } else {
                icon.className = 'fas fa-chevron-left';
            }
        });
    }
    
    console.log('✅ Sidebar navigation loaded successfully');
});
</script>
