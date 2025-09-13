<?php
/**
 * WorkSafety.io - דשבורד ראשי
 * דף הבית המרכזי של מערכת ניהול הבטיחות
 */

// הגדרות דף
$pageTitle = 'דשבורד ראשי';
$pageDescription = 'מעקב מקיף אחר מדדי הבטיחות, ליקויים פתוחים, בדיקות ממתינות ופעילות האתרים';
$additionalCSS = ['assets/css/dashboard.css'];
$additionalJS = ['assets/js/dashboard.js'];

// כלילת קבצים נדרשים
require_once 'includes/header.php';

// וידוא התחברות
if (!isset($_SESSION['user_id'])) {
    header('Location: /login.php');
    exit;
}

// קבלת נתונים לדשבורד
try {
    $db = getDB();
    $userId = $_SESSION['user_id'];
    $userCompanyId = $currentUser['company_id'] ?? 0;
    $userRole = $currentUser['role'] ?? 'worker';
    
    // סטטיסטיקות כלליות
    $totalStats = [];
    
    // חברות (רק למנהל ראשי)
    if ($userRole === 'super_admin') {
        $tableExists = $db->query("SHOW TABLES LIKE 'companies'")->fetch();
        if ($tableExists) {
            $stmt = $db->query("SELECT COUNT(*) as count FROM companies WHERE status = 'active'");
            $totalStats['companies'] = $stmt->fetch()['count'] ?? 0;
            
            $tableExists = $db->query("SHOW TABLES LIKE 'contractors'")->fetch();
            if ($tableExists) {
                $stmt = $db->query("SELECT COUNT(*) as count FROM contractors WHERE status = 'active'");
                $totalStats['contractors'] = $stmt->fetch()['count'] ?? 0;
            } else {
                $totalStats['contractors'] = 0;
            }
        } else {
            $totalStats['companies'] = 1; // החברה הראשית
            $totalStats['contractors'] = 0;
        }
    } else {
        $tableExists = $db->query("SHOW TABLES LIKE 'contractors'")->fetch();
        if ($tableExists) {
            $stmt = $db->query("SELECT COUNT(*) as count FROM contractors WHERE company_id = ? AND status = 'active'", [$userCompanyId]);
            $totalStats['contractors'] = $stmt->fetch()['count'] ?? 0;
        } else {
            $totalStats['contractors'] = 0;
        }
    }
    
    // יתר הנתונים - רק אם הטבלאות קיימות
    $whereClause = $userRole === 'super_admin' ? "1=1" : "company_id = ?";
    $params = $userRole === 'super_admin' ? [] : [$userCompanyId];
    
    // אתרי עבודה
    $tableExists = $db->query("SHOW TABLES LIKE 'worksites'")->fetch();
    if ($tableExists) {
        $stmt = $db->query("SELECT COUNT(*) as count FROM worksites WHERE {$whereClause} AND status = 'active'", $params);
        $totalStats['active_sites'] = $stmt->fetch()['count'] ?? 0;
        
        $stmt = $db->query("SELECT COUNT(*) as count FROM worksites WHERE {$whereClause}", $params);
        $totalStats['total_sites'] = $stmt->fetch()['count'] ?? 0;
    } else {
        $totalStats['active_sites'] = 0;
        $totalStats['total_sites'] = 0;
    }
    
    // עובדים
    $tableExists = $db->query("SHOW TABLES LIKE 'employees'")->fetch();
    if ($tableExists) {
        $stmt = $db->query("SELECT COUNT(*) as count FROM employees WHERE {$whereClause} AND status = 'active'", $params);
        $totalStats['employees'] = $stmt->fetch()['count'] ?? 0;
    } else {
        $totalStats['employees'] = 0;
    }
    
    // ליקויים
    $tableExists = $db->query("SHOW TABLES LIKE 'deficiencies'")->fetch();
    if ($tableExists) {
        $stmt = $db->query("SELECT COUNT(*) as count FROM deficiencies WHERE {$whereClause} AND status = 'open'", $params);
        $totalStats['open_deficiencies'] = $stmt->fetch()['count'] ?? 0;
        
        $stmt = $db->query("SELECT COUNT(*) as count FROM deficiencies WHERE {$whereClause} AND status = 'closed' AND DATE(closed_at) = CURDATE()", $params);
        $totalStats['closed_today'] = $stmt->fetch()['count'] ?? 0;
    } else {
        $totalStats['open_deficiencies'] = 0;
        $totalStats['closed_today'] = 0;
    }
    
    // בדיקות
    $tableExists = $db->query("SHOW TABLES LIKE 'inspections'")->fetch();
    if ($tableExists) {
        $stmt = $db->query("SELECT COUNT(*) as count FROM inspections WHERE {$whereClause} AND status = 'pending'", $params);
        $totalStats['pending_inspections'] = $stmt->fetch()['count'] ?? 0;
        
        $stmt = $db->query("SELECT COUNT(*) as count FROM inspections WHERE {$whereClause} AND DATE(created_at) = CURDATE()", $params);
        $totalStats['inspections_today'] = $stmt->fetch()['count'] ?? 0;
    } else {
        $totalStats['pending_inspections'] = 0;
        $totalStats['inspections_today'] = 0;
    }
    
    // ציוד
    $tableExists = $db->query("SHOW TABLES LIKE 'equipment'")->fetch();
    if ($tableExists) {
        $stmt = $db->query("SELECT COUNT(*) as count FROM equipment WHERE {$whereClause} AND status = 'active'", $params);
        $totalStats['equipment'] = $stmt->fetch()['count'] ?? 0;
        
        $stmt = $db->query("SELECT COUNT(*) as count FROM equipment WHERE {$whereClause} AND next_inspection_date < DATE_ADD(NOW(), INTERVAL 7 DAY)", $params);
        $totalStats['equipment_due'] = $stmt->fetch()['count'] ?? 0;
    } else {
        $totalStats['equipment'] = 0;
        $totalStats['equipment_due'] = 0;
    }
    
    // פעילות אחרונה - רק אם הטבלאות קיימות
    $recentActivity = [];
    if ($db->query("SHOW TABLES LIKE 'deficiencies'")->fetch() && $db->query("SHOW TABLES LIKE 'inspections'")->fetch()) {
        try {
            $recentActivity = $db->fetchAll("
                SELECT 
                    'deficiency' as type,
                    description as title,
                    'ליקוי חדש' as action,
                    created_at as timestamp,
                    created_by,
                    COALESCE(u.full_name, 'משתמש לא ידוע') as user_name
                FROM deficiencies d
                LEFT JOIN users u ON d.created_by = u.id
                WHERE d.{$whereClause}
                
                UNION ALL
                
                SELECT 
                    'inspection' as type,
                    title,
                    'בדיקה בוצעה' as action,
                    created_at as timestamp,
                    inspector_id as created_by,
                    COALESCE(u.full_name, 'משתמש לא ידוע') as user_name
                FROM inspections i
                LEFT JOIN users u ON i.inspector_id = u.id
                WHERE i.{$whereClause} AND i.status = 'completed'
                
                ORDER BY timestamp DESC
                LIMIT 10
            ", array_merge($params, $params));
        } catch (Exception $e) {
            // אם השדות לא קיימים עדיין
            $recentActivity = [];
        }
    }
    
    // נתונים לגרפים - רק אם הטבלאות קיימות
    $deficienciesChart = [];
    $severityChart = [];
    $contractorChart = [];
    
    if ($db->query("SHOW TABLES LIKE 'deficiencies'")->fetch()) {
        try {
            // ליקויים לפי חודש (12 חודשים אחרונים)
            $deficienciesChart = $db->fetchAll("
                SELECT 
                    DATE_FORMAT(created_at, '%Y-%m') as month,
                    COUNT(*) as count
                FROM deficiencies 
                WHERE {$whereClause} 
                AND created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
                GROUP BY DATE_FORMAT(created_at, '%Y-%m')
                ORDER BY month
            ", $params);
            
            // התפלגות ליקויים לפי חומרה
            $severityChart = $db->fetchAll("
                SELECT 
                    severity,
                    COUNT(*) as count
                FROM deficiencies 
                WHERE {$whereClause} 
                AND status = 'open'
                GROUP BY severity
            ", $params);
        } catch (Exception $e) {
            // שדות לא קיימים עדיין
        }
    }
    
    // ליקויים לפי קבלן (רק אם יש קבלנים)
    if (($totalStats['contractors'] ?? 0) > 0 && $db->query("SHOW TABLES LIKE 'contractors'")->fetch()) {
        try {
            $contractorChart = $db->fetchAll("
                SELECT 
                    c.name as contractor_name,
                    COUNT(d.id) as deficiency_count
                FROM contractors c
                LEFT JOIN deficiencies d ON c.id = d.contractor_id AND d.status = 'open'
                WHERE c.{$whereClause} AND c.status = 'active'
                GROUP BY c.id, c.name
                ORDER BY deficiency_count DESC
                LIMIT 10
            ", $params);
        } catch (Exception $e) {
            // שדות לא קיימים עדיין
        }
    }
    
    // אתרים פעילים עם מספר ליקויים
    $activeSites = [];
    if ($db->query("SHOW TABLES LIKE 'worksites'")->fetch()) {
        try {
            $activeSites = $db->fetchAll("
                SELECT 
                    w.*,
                    COUNT(d.id) as open_deficiencies,
                    DATE(w.created_at) as start_date
                FROM worksites w
                LEFT JOIN deficiencies d ON w.id = d.worksite_id AND d.status = 'open'
                WHERE w.{$whereClause} AND w.status = 'active'
                GROUP BY w.id
                ORDER BY open_deficiencies DESC, w.created_at DESC
                LIMIT 8
            ", $params);
        } catch (Exception $e) {
            // שדות לא קיימים עדיין
        }
    }
    
} catch (Exception $e) {
    error_log("Dashboard data error: " . $e->getMessage());
    $totalStats = [
        'companies' => 1,
        'contractors' => 0,
        'active_sites' => 0,
        'total_sites' => 0,
        'employees' => 0,
        'open_deficiencies' => 0,
        'closed_today' => 0,
        'pending_inspections' => 0,
        'inspections_today' => 0,
        'equipment' => 0,
        'equipment_due' => 0
    ];
    $recentActivity = [];
    $deficienciesChart = [];
    $severityChart = [];
    $contractorChart = [];
    $activeSites = [];
}

// תאריך עברי נוכחי
$currentHebrewDate = formatHebrewDateTime(date('Y-m-d H:i:s'));
?>

<!-- Include Sidebar -->
<?php include 'includes/sidebar.php'; ?>

<!-- Main Content -->
<main class="main-content">
    <!-- Header -->
    <header class="main-header">
        <div class="header-content">
            <div class="header-left">
                <h1 class="header-title">
                    <i class="fas fa-tachometer-alt"></i>
                    דשבורד ראשי
                </h1>
                <p class="header-subtitle">
                    <?php echo $currentHebrewDate; ?>
                    <span class="separator">•</span>
                    <span class="welcome-text">
                        שלום <?php echo htmlspecialchars($currentUser['full_name'] ?? 'משתמש'); ?>
                    </span>
                </p>
            </div>
            <div class="header-actions">
                <button class="btn btn-outline btn-sm" onclick="refreshDashboard()">
                    <i class="fas fa-sync-alt"></i>
                    רענן נתונים
                </button>
                <button class="btn btn-primary btn-sm" onclick="window.print()">
                    <i class="fas fa-print"></i>
                    הדפס דו"ח
                </button>
            </div>
        </div>
    </header>

    <!-- Page Content -->
    <div class="page-content">
        
        <!-- Welcome Section -->
        <div class="welcome-section">
            <div class="card welcome-card">
                <div class="card-body">
                    <div class="welcome-content">
                        <div class="welcome-text">
                            <h2>ברוכים הבאים ל-WorkSafety.io</h2>
                            <p>מערכת ניהול בטיחות תעשייתית מתקדמת המאפשרת מעקב מקיף אחר כל היבטי הבטיחות באתרי העבודה שלכם.</p>
                            <?php if ($userRole === 'super_admin'): ?>
                                <p><strong>אתם מחוברים כמנהל ראשי</strong> - יש לכם גישה לכל המודולים במערכת!</p>
                            <?php endif; ?>
                        </div>
                        <div class="welcome-actions">
                            <a href="/modules/deficiencies/add.php" class="btn btn-primary">
                                <i class="fas fa-plus"></i>
                                דווח על ליקוי
                            </a>
                            <a href="/modules/inspections/add.php" class="btn btn-primary">
                                <i class="fas fa-clipboard-check"></i>
                                בצע בדיקה
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Statistics Grid -->
        <div class="stats-grid">
            
            <?php if ($userRole === 'super_admin'): ?>
            <!-- חברות -->
            <div class="stat-card">
                <div class="stat-icon" style="color: #8b5cf6;">
                    <i class="fas fa-building"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-value"><?php echo number_format($totalStats['companies'] ?? 0); ?></div>
                    <div class="stat-label">חברות פעילות</div>
                    <div class="stat-change positive">
                        <i class="fas fa-arrow-up"></i>
                        מערכת מוכנה לעבודה
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- אתרי עבודה -->
            <div class="stat-card">
                <div class="stat-icon" style="color: #10b981;">
                    <i class="fas fa-map-marker-alt"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-value"><?php echo number_format($totalStats['active_sites'] ?? 0); ?></div>
                    <div class="stat-label">אתרים פעילים</div>
                    <div class="stat-change">
                        מתוך <?php echo number_format($totalStats['total_sites'] ?? 0); ?> סה"כ
                    </div>
                </div>
            </div>
            
            <!-- עובדים -->
            <div class="stat-card">
                <div class="stat-icon" style="color: #3b82f6;">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-value"><?php echo number_format($totalStats['employees'] ?? 0); ?></div>
                    <div class="stat-label">עובדים פעילים</div>
                    <div class="stat-change positive">
                        <i class="fas fa-check"></i>
                        מוכן להוספת עובדים
                    </div>
                </div>
            </div>
            
            <!-- ליקויים פתוחים -->
            <div class="stat-card <?php echo ($totalStats['open_deficiencies'] ?? 0) > 0 ? 'urgent' : ''; ?>">
                <div class="stat-icon" style="color: #ef4444;">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-value"><?php echo number_format($totalStats['open_deficiencies'] ?? 0); ?></div>
                    <div class="stat-label">ליקויים פתוחים</div>
                    <div class="stat-change">
                        <?php echo number_format($totalStats['closed_today'] ?? 0); ?> נסגרו היום
                    </div>
                </div>
            </div>
            
            <!-- בדיקות ממתינות -->
            <div class="stat-card">
                <div class="stat-icon" style="color: #f59e0b;">
                    <i class="fas fa-clipboard-list"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-value"><?php echo number_format($totalStats['pending_inspections'] ?? 0); ?></div>
                    <div class="stat-label">בדיקות ממתינות</div>
                    <div class="stat-change">
                        <?php echo number_format($totalStats['inspections_today'] ?? 0); ?> בוצעו היום
                    </div>
                </div>
            </div>
            
            <!-- ציוד -->
            <div class="stat-card">
                <div class="stat-icon" style="color: #06b6d4;">
                    <i class="fas fa-tools"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-value"><?php echo number_format($totalStats['equipment'] ?? 0); ?></div>
                    <div class="stat-label">יחידות ציוד</div>
                    <div class="stat-change <?php echo ($totalStats['equipment_due'] ?? 0) > 0 ? 'negative' : ''; ?>">
                        <?php if (($totalStats['equipment_due'] ?? 0) > 0): ?>
                            <i class="fas fa-exclamation-triangle"></i>
                            <?php echo $totalStats['equipment_due']; ?> דורשים בדיקה
                        <?php else: ?>
                            <i class="fas fa-check"></i>
                            הכל תקין
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
        </div>

        <!-- Quick Actions -->
        <div class="quick-actions">
            <h3>פעולות מהירות</h3>
            <div class="actions-grid">
                <a href="/modules/deficiencies/" class="action-card">
                    <div class="action-icon">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <div class="action-title">ניהול ליקויים</div>
                    <div class="action-desc">צפייה ותיעוד ליקויי בטיחות</div>
                </a>
                
                <a href="/modules/inspections/" class="action-card">
                    <div class="action-icon">
                        <i class="fas fa-clipboard-check"></i>
                    </div>
                    <div class="action-title">בדיקות בטיחות</div>
                    <div class="action-desc">ביצוע ומעקב בדיקות</div>
                </a>
                
                <a href="/modules/employees/" class="action-card">
                    <div class="action-icon">
                        <i class="fas fa-user-plus"></i>
                    </div>
                    <div class="action-title">ניהול עובדים</div>
                    <div class="action-desc">רישום ומעקב עובדים</div>
                </a>
                
                <a href="/modules/equipment/" class="action-card">
                    <div class="action-icon">
                        <i class="fas fa-tools"></i>
                    </div>
                    <div class="action-title">ניהול ציוד</div>
                    <div class="action-desc">רישום וצפייה בציוד</div>
                </a>
                
                <a href="/modules/summaries/" class="action-card">
                    <div class="action-icon">
                        <i class="fas fa-file-pdf"></i>
                    </div>
                    <div class="action-title">תמציות מידע</div>
                    <div class="action-desc">הפקת דוחות PDF</div>
                </a>
                
                <a href="/modules/statistics/" class="action-card">
                    <div class="action-icon">
                        <i class="fas fa-chart-bar"></i>
                    </div>
                    <div class="action-title">סטטיסטיקות</div>
                    <div class="action-desc">ניתוח נתונים מתקדם</div>
                </a>
            </div>
        </div>
        
        <!-- Setup Notice for Super Admin -->
        <?php if ($userRole === 'super_admin'): ?>
        <div class="setup-notice">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-info-circle"></i>
                        הודעה למנהל ראשי
                    </h3>
                </div>
                <div class="card-body">
                    <p><strong>ברוכים הבאים למערכת WorkSafety.io!</strong></p>
                    <p>המערכת הוגדרה בהצלחה ואתם מחוברים כמנהל ראשי עם גישה לכל המודולים.</p>
                    
                    <div class="setup-checklist">
                        <h4>שלבים מומלצים להתחלה:</h4>
                        <ul>
                            <li>✅ המערכת מוגדרת וזמינה לשימוש</li>
                            <li>🔧 כל המודולים זמינים בתפריט הצד</li>
                            <li>👥 ניתן להוסיף משתמשים ולהגדיר הרשאות</li>
                            <li>🏢 ניתן להוסיף חברות לקוחות וקבלנים</li>
                            <li>📍 ניתן להגדיר אתרי עבודה</li>
                        </ul>
                    </div>
                    
                    <div class="setup-actions">
                        <a href="/modules/users/" class="btn btn-primary">
                            <i class="fas fa-users"></i>
                            נהל משתמשים
                        </a>
                        <a href="/modules/companies/" class="btn btn-outline">
                            <i class="fas fa-building"></i>
                            נהל חברות
                        </a>
                        <a href="/modules/users/permissions.php" class="btn btn-outline">
                            <i class="fas fa-key"></i>
                            נהל הרשאות
                        </a>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
    </div>
</main>

<!-- JavaScript for Charts -->
<script>
// נתוני JavaScript לגרפים
const dashboardData = {
    deficiencies: <?php echo json_encode($deficienciesChart); ?>,
    severity: <?php echo json_encode($severityChart); ?>,
    contractors: <?php echo json_encode($contractorChart); ?>
};

document.addEventListener('DOMContentLoaded', function() {
    console.log('✅ Dashboard loaded successfully');
    console.log('User role:', '<?php echo $userRole; ?>');
    console.log('Total stats:', <?php echo json_encode($totalStats); ?>);
});

function refreshDashboard() {
    const btn = event.target.closest('button');
    const originalText = btn.innerHTML;
    
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> מרענן...';
    btn.disabled = true;
    
    setTimeout(() => {
        location.reload();
    }, 1000);
}
</script>

<!-- Include Footer -->
<?php 
$additionalFooter = "
<style>
/* Dashboard specific styles */
.welcome-section {
    margin-bottom: 2rem;
}

.welcome-card {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border: none;
}

.welcome-content {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 2rem;
}

.welcome-text h2 {
    margin-bottom: 0.5rem;
    color: white;
}

.welcome-text p {
    color: rgba(255, 255, 255, 0.9);
    margin: 0.5rem 0;
}

.welcome-actions {
    display: flex;
    gap: 1rem;
    flex-shrink: 0;
}

.header-subtitle {
    color: var(--text-light);
    font-size: 0.9rem;
    margin: 0;
}

.separator {
    margin: 0 0.5rem;
}

.quick-actions {
    margin-top: 2rem;
}

.actions-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1rem;
    margin-top: 1rem;
}

.action-card {
    display: block;
    padding: 1.5rem;
    background: white;
    border-radius: var(--radius-lg);
    box-shadow: var(--shadow-sm);
    border: 1px solid #e2e8f0;
    text-decoration: none;
    color: inherit;
    transition: var(--transition-fast);
}

.action-card:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-md);
    color: inherit;
}

.action-icon {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    background: var(--primary-color);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 1rem;
    font-size: 1.25rem;
}

.action-title {
    font-weight: 600;
    font-size: 1.1rem;
    margin-bottom: 0.5rem;
}

.action-desc {
    color: var(--text-light);
    font-size: 0.9rem;
}

.stat-card.urgent {
    border-right: 4px solid var(--danger-color);
}

.setup-notice {
    margin-top: 2rem;
}

.setup-checklist ul {
    list-style: none;
    padding: 0;
    margin: 1rem 0;
}

.setup-checklist li {
    padding: 0.5rem 0;
    border-bottom: 1px solid #e2e8f0;
}

.setup-checklist li:last-child {
    border-bottom: none;
}

.setup-actions {
    margin-top: 1rem;
    display: flex;
    gap: 1rem;
    flex-wrap: wrap;
}

@media (max-width: 768px) {
    .welcome-content {
        flex-direction: column;
        text-align: center;
    }
    
    .actions-grid {
        grid-template-columns: 1fr;
    }
    
    .header-content {
        flex-direction: column;
        gap: 1rem;
    }
    
    .setup-actions {
        flex-direction: column;
    }
}
</style>
";

include 'includes/footer.php'; 
?>
