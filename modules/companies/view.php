<?php
/**
 * WorkSafety.io - צפייה בפרטי חברה
 * דף מפורט עם כל המידע על החברה וסטטיסטיקות
 */

// הגדרות דף
$pageTitle = 'פרטי חברה';
$pageDescription = 'צפייה מפורטת בנתוני החברה, סטטיסטיקות ופעילות';
$additionalCSS = ['/modules/companies/assets/companies.css'];
$additionalJS = ['/modules/companies/assets/companies.js'];

// כלילת קבצים נדרשים
require_once '../../includes/header.php';
require_once 'includes/functions.php';

// וידוא התחברות והרשאות
if (!isset($_SESSION['user_id'])) {
    header('Location: /login.php');
    exit;
}

// בדיקת הרשאות
$userRole = $currentUser['role'] ?? 'worker';
if (!in_array($userRole, ['super_admin', 'company_admin'])) {
    $_SESSION['flash_message']['danger'] = 'אין לך הרשאה לגשת לעמוד זה';
    header('Location: /dashboard.php');
    exit;
}

// קבלת ID החברה
$companyId = intval($_GET['id'] ?? 0);
if (!$companyId) {
    $_SESSION['flash_message']['danger'] = 'לא נמצאה חברה מתאימה';
    header('Location: index.php');
    exit;
}

try {
    $db = getDB();
    
    // קבלת נתוני החברה עם סטטיסטיקות
    $company = getCompanyWithStats($companyId, $db);
    
    if (!$company) {
        $_SESSION['flash_message']['danger'] = 'החברה לא נמצאה';
        header('Location: index.php');
        exit;
    }
    
    // בדיקת הרשאות לצפייה בחברה
    if ($userRole === 'company_admin' && 
        $company['id'] != $currentUser['company_id'] && 
        $company['parent_company_id'] != $currentUser['company_id']) {
        $_SESSION['flash_message']['danger'] = 'אין לך הרשאה לצפות בחברה זו';
        header('Location: index.php');
        exit;
    }
    
    // בדיקת מגבלות
    $limits = checkCompanyLimits($companyId, $db);
    
    // קבלת משתמשים אחרונים
    $recentUsers = $db->fetchAll("
        SELECT u.*, DATE(u.last_login) as last_login_date
        FROM users u 
        WHERE u.company_id = ? 
        ORDER BY u.last_login DESC 
        LIMIT 5
    ", [$companyId]);
    
    // קבלת אתרי עבודה אחרונים
    $recentSites = $db->fetchAll("
        SELECT w.*, DATE(w.created_at) as creation_date
        FROM worksites w 
        WHERE w.company_id = ? 
        ORDER BY w.created_at DESC 
        LIMIT 5
    ", [$companyId]);
    
    // קבלת ליקויים פתוחים
    $openDeficiencies = $db->fetchAll("
        SELECT d.*, w.name as worksite_name
        FROM deficiencies d 
        LEFT JOIN worksites w ON d.worksite_id = w.id
        WHERE d.company_id = ? AND d.status = 'open'
        ORDER BY d.severity DESC, d.created_at DESC 
        LIMIT 10
    ", [$companyId]);
    
    // סטטיסטיקות חודשיות
    $monthlyStats = $db->fetchAll("
        SELECT 
            DATE_FORMAT(created_at, '%Y-%m') as month,
            COUNT(*) as count,
            'deficiencies' as type
        FROM deficiencies 
        WHERE company_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
        GROUP BY DATE_FORMAT(created_at, '%Y-%m')
        ORDER BY month DESC
        LIMIT 6
    ", [$companyId]);
    
} catch (Exception $e) {
    error_log("Company view error: " . $e->getMessage());
    $_SESSION['flash_message']['danger'] = 'שגיאה בטעינת נתוני החברה';
    header('Location: index.php');
    exit;
}

// עדכון כותרת הדף
$pageTitle = 'פרטי חברה: ' . $company['name'];
?>

<div class="page-wrapper">
    <!-- Page Header -->
    <header class="page-header">
        <div class="header-content">
            <div class="header-text">
                <div class="company-header">
                    <?php if (!empty($company['logo'])): ?>
                        <img src="<?php echo htmlspecialchars($company['logo']); ?>" 
                             alt="לוגו <?php echo htmlspecialchars($company['name']); ?>" 
                             class="company-logo-large">
                    <?php else: ?>
                        <div class="company-logo-placeholder-large">
                            <i class="fas fa-building"></i>
                        </div>
                    <?php endif; ?>
                    
                    <div class="company-title">
                        <h1>
                            <?php echo htmlspecialchars($company['name']); ?>
                            <?php
                            $typeClass = $company['company_type'] === 'main' ? 'badge-primary' : 'badge-info';
                            $typeText = $company['company_type'] === 'main' ? 'ראשית' : 'לקוח';
                            ?>
                            <span class="badge <?php echo $typeClass; ?>">
                                <?php echo $typeText; ?>
                            </span>
                        </h1>
                        
                        <p>
                            מידע מפורט על החברה, משתמשים, אתרי עבודה וסטטיסטיקות בטיחות
                            <span class="text-muted">
                                • נוצר: <?php echo date('d/m/Y', strtotime($company['created_at'])); ?>
                                • עודכן: <?php echo date('d/m/Y H:i', strtotime($company['updated_at'])); ?>
                            </span>
                        </p>
                    </div>
                </div>
            </div>
            <div class="header-actions">
                <?php if ($userRole === 'super_admin' || 
                          ($userRole === 'company_admin' && $company['id'] == $currentUser['company_id'])): ?>
                    <a href="edit.php?id=<?php echo $company['id']; ?>" class="btn btn-primary">
                        <i class="fas fa-edit"></i>
                        עריכת חברה
                    </a>
                <?php endif; ?>
                
                <a href="index.php" class="btn btn-outline">
                    <i class="fas fa-arrow-right"></i>
                    חזור לרשימה
                </a>
                
                <button class="btn btn-outline btn-sm" onclick="window.print()">
                    <i class="fas fa-print"></i>
                    הדפס
                </button>
            </div>
        </div>
    </header>

    <!-- Page Content -->
    <div class="page-content">
        
        <!-- Status Alerts -->
        <?php if ($company['status'] !== 'active'): ?>
        <div class="alert alert-warning">
            <i class="fas fa-exclamation-triangle"></i>
            החברה במצב "<?php echo $company['status']; ?>" - לא יכולה להשתמש במערכת
        </div>
        <?php endif; ?>
        
        <?php if ($company['subscription_status'] === 'expired'): ?>
        <div class="alert alert-danger">
            <i class="fas fa-times-circle"></i>
            המנוי של החברה פג - נדרש חידוש מנוי
        </div>
        <?php elseif ($company['subscription_status'] === 'expiring_soon'): ?>
        <div class="alert alert-warning">
            <i class="fas fa-clock"></i>
            המנוי של החברה יפוג בעוד <?php echo $company['days_to_expiry']; ?> ימים
        </div>
        <?php endif; ?>
        
        <?php if ($limits['users_exceeded'] || $limits['sites_exceeded']): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-triangle"></i>
            החברה חרגה מהמגבלות:
            <?php if ($limits['users_exceeded']): ?>
                משתמשים (<?php echo $limits['current_users']; ?>/<?php echo $limits['max_users']; ?>)
            <?php endif; ?>
            <?php if ($limits['sites_exceeded']): ?>
                אתרי עבודה (<?php echo $limits['current_sites']; ?>/<?php echo $limits['max_sites']; ?>)
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Main Statistics Grid -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon" style="color: #3b82f6;">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-value">
                        <?php echo number_format($company['active_users']); ?>
                        <?php if ($company['max_users'] > 0): ?>
                            <small>/<?php echo number_format($company['max_users']); ?></small>
                        <?php endif; ?>
                    </div>
                    <div class="stat-label">משתמשים פעילים</div>
                    <div class="stat-sublabel">
                        מתוך <?php echo number_format($company['total_users']); ?> סה"כ
                    </div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon" style="color: #10b981;">
                    <i class="fas fa-map-marker-alt"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-value">
                        <?php echo number_format($company['active_sites']); ?>
                        <?php if ($company['max_sites'] > 0): ?>
                            <small>/<?php echo number_format($company['max_sites']); ?></small>
                        <?php endif; ?>
                    </div>
                    <div class="stat-label">אתרי עבודה פעילים</div>
                    <div class="stat-sublabel">
                        מתוך <?php echo number_format($company['total_sites']); ?> סה"כ
                    </div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon" style="color: #8b5cf6;">
                    <i class="fas fa-hard-hat"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-value"><?php echo number_format($company['active_contractors']); ?></div>
                    <div class="stat-label">קבלנים פעילים</div>
                </div>
            </div>
            
            <?php if ($company['open_deficiencies'] > 0): ?>
            <div class="stat-card alert">
                <div class="stat-icon" style="color: #ef4444;">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-value"><?php echo number_format($company['open_deficiencies']); ?></div>
                    <div class="stat-label">ליקויים פתוחים</div>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <div class="content-grid">
            <!-- Company Details -->
            <div class="content-main">
                
                <!-- Basic Information -->
                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-info-circle"></i> פרטים כלליים</h3>
                    </div>
                    <div class="card-body">
                        <div class="detail-grid">
                            <div class="detail-item">
                                <label>שם החברה</label>
                                <value><?php echo htmlspecialchars($company['name']); ?></value>
                            </div>
                            
                            <div class="detail-item">
                                <label>כתובת אימייל</label>
                                <value>
                                    <a href="mailto:<?php echo htmlspecialchars($company['email']); ?>">
                                        <?php echo htmlspecialchars($company['email']); ?>
                                    </a>
                                </value>
                            </div>
                            
                            <?php if (!empty($company['phone'])): ?>
                            <div class="detail-item">
                                <label>טלפון</label>
                                <value>
                                    <a href="tel:<?php echo htmlspecialchars($company['phone']); ?>">
                                        <?php echo htmlspecialchars($company['phone']); ?>
                                    </a>
                                </value>
                            </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($company['contact_person'])): ?>
                            <div class="detail-item">
                                <label>איש קשר</label>
                                <value><?php echo htmlspecialchars($company['contact_person']); ?></value>
                            </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($company['website'])): ?>
                            <div class="detail-item">
                                <label>אתר אינטרנט</label>
                                <value>
                                    <a href="<?php echo htmlspecialchars($company['website']); ?>" target="_blank">
                                        <?php echo htmlspecialchars($company['website']); ?>
                                        <i class="fas fa-external-link-alt"></i>
                                    </a>
                                </value>
                            </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($company['address'])): ?>
                            <div class="detail-item full-width">
                                <label>כתובת</label>
                                <value><?php echo nl2br(htmlspecialchars($company['address'])); ?></value>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Legal Information -->
                <?php if (!empty($company['registration_number']) || !empty($company['tax_id'])): ?>
                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-file-alt"></i> פרטים משפטיים</h3>
                    </div>
                    <div class="card-body">
                        <div class="detail-grid">
                            <?php if (!empty($company['registration_number'])): ?>
                            <div class="detail-item">
                                <label>מספר רישום חברה</label>
                                <value><?php echo htmlspecialchars($company['registration_number']); ?></value>
                            </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($company['tax_id'])): ?>
                            <div class="detail-item">
                                <label>מספר עוסק</label>
                                <value><?php echo htmlspecialchars($company['tax_id']); ?></value>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Recent Users -->
                <?php if (!empty($recentUsers)): ?>
                <div class="card">
                    <div class="card-header">
                        <h3>
                            <i class="fas fa-users"></i> 
                            משתמשים אחרונים
                            <span class="text-muted">(<?php echo count($recentUsers); ?> מתוך <?php echo $company['total_users']; ?>)</span>
                        </h3>
                        <a href="/modules/users/index.php?company_id=<?php echo $company['id']; ?>" class="btn btn-outline btn-sm">
                            צפה בכל המשתמשים
                        </a>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>שם</th>
                                    <th>תפקיד</th>
                                    <th>התחברות אחרונה</th>
                                    <th>סטטוס</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentUsers as $user): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></strong>
                                        <br><small class="text-muted"><?php echo htmlspecialchars($user['email']); ?></small>
                                    </td>
                                    <td>
                                        <?php
                                        $roleNames = [
                                            'super_admin' => 'מנהל ראשי',
                                            'company_admin' => 'מנהל חברה',
                                            'site_manager' => 'מנהל אתר',
                                            'safety_officer' => 'קצין בטיחות',
                                            'inspector' => 'בודק',
                                            'worker' => 'עובד'
                                        ];
                                        echo $roleNames[$user['role']] ?? $user['role'];
                                        ?>
                                    </td>
                                    <td>
                                        <?php if ($user['last_login']): ?>
                                            <?php echo date('d/m/Y', strtotime($user['last_login'])); ?>
                                        <?php else: ?>
                                            <span class="text-muted">מעולם לא התחבר</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php
                                        $statusClass = [
                                            'active' => 'badge-success',
                                            'inactive' => 'badge-secondary'
                                        ][$user['status']] ?? 'badge-secondary';
                                        
                                        $statusText = [
                                            'active' => 'פעיל',
                                            'inactive' => 'לא פעיל'
                                        ][$user['status']] ?? $user['status'];
                                        ?>
                                        <span class="badge <?php echo $statusClass; ?>">
                                            <?php echo $statusText; ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Recent Work Sites -->
                <?php if (!empty($recentSites)): ?>
                <div class="card">
                    <div class="card-header">
                        <h3>
                            <i class="fas fa-map-marker-alt"></i> 
                            אתרי עבודה אחרונים
                            <span class="text-muted">(<?php echo count($recentSites); ?> מתוך <?php echo $company['total_sites']; ?>)</span>
                        </h3>
                        <a href="/modules/worksites/index.php?company_id=<?php echo $company['id']; ?>" class="btn btn-outline btn-sm">
                            צפה בכל האתרים
                        </a>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>שם האתר</th>
                                    <th>סוג</th>
                                    <th>תאריך יצירה</th>
                                    <th>סטטוס</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentSites as $site): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($site['name']); ?></strong>
                                        <?php if (!empty($site['location'])): ?>
                                            <br><small class="text-muted"><?php echo htmlspecialchars($site['location']); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($site['site_type'] ?? 'לא הוגדר'); ?></td>
                                    <td><?php echo date('d/m/Y', strtotime($site['created_at'])); ?></td>
                                    <td>
                                        <?php
                                        $statusClass = [
                                            'active' => 'badge-success',
                                            'inactive' => 'badge-secondary',
                                            'completed' => 'badge-info'
                                        ][$site['status']] ?? 'badge-secondary';
                                        
                                        $statusText = [
                                            'active' => 'פעיל',
                                            'inactive' => 'לא פעיל',
                                            'completed' => 'הושלם'
                                        ][$site['status']] ?? $site['status'];
                                        ?>
                                        <span class="badge <?php echo $statusClass; ?>">
                                            <?php echo $statusText; ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Open Deficiencies -->
                <?php if (!empty($openDeficiencies)): ?>
                <div class="card">
                    <div class="card-header">
                        <h3>
                            <i class="fas fa-exclamation-triangle text-warning"></i> 
                            ליקויים פתוחים
                            <span class="text-muted">(<?php echo count($openDeficiencies); ?> מתוך <?php echo $company['open_deficiencies']; ?>)</span>
                        </h3>
                        <a href="/modules/deficiencies/index.php?company_id=<?php echo $company['id']; ?>&status=open" class="btn btn-outline btn-sm">
                            צפה בכל הליקויים
                        </a>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>תיאור הליקוי</th>
                                    <th>אתר עבודה</th>
                                    <th>חומרה</th>
                                    <th>תאריך</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($openDeficiencies as $deficiency): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($deficiency['title']); ?></strong>
                                        <?php if (!empty($deficiency['description'])): ?>
                                            <br><small class="text-muted">
                                                <?php echo htmlspecialchars(mb_substr($deficiency['description'], 0, 100)); ?>
                                                <?php if (mb_strlen($deficiency['description']) > 100) echo '...'; ?>
                                            </small>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($deficiency['worksite_name'] ?? 'לא צוין'); ?></td>
                                    <td>
                                        <?php
                                        $severityClass = [
                                            'critical' => 'badge-danger',
                                            'high' => 'badge-warning', 
                                            'medium' => 'badge-info',
                                            'low' => 'badge-secondary'
                                        ][$deficiency['severity']] ?? 'badge-secondary';
                                        
                                        $severityText = [
                                            'critical' => 'קריטי',
                                            'high' => 'גבוה',
                                            'medium' => 'בינוני', 
                                            'low' => 'נמוך'
                                        ][$deficiency['severity']] ?? $deficiency['severity'];
                                        ?>
                                        <span class="badge <?php echo $severityClass; ?>">
                                            <?php echo $severityText; ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('d/m/Y', strtotime($deficiency['created_at'])); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php endif; ?>
                
            </div>

            <!-- Sidebar -->
            <div class="content-sidebar">
                
                <!-- Subscription Info -->
                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-crown"></i> מידע מנוי</h3>
                    </div>
                    <div class="card-body">
                        <div class="subscription-details">
                            <div class="detail-item">
                                <label>תוכנית מנוי</label>
                                <value>
                                    <span class="badge badge-outline">
                                        <?php echo htmlspecialchars($company['subscription_plan']); ?>
                                    </span>
                                </value>
                            </div>
                            
                            <div class="detail-item">
                                <label>סטטוס מנוי</label>
                                <value>
                                    <?php
                                    $statusClass = [
                                        'active' => 'text-success',
                                        'expired' => 'text-danger',
                                        'expiring_soon' => 'text-warning',
                                        'unlimited' => 'text-info'
                                    ][$company['subscription_status']] ?? 'text-secondary';
                                    
                                    $statusText = [
                                        'active' => 'פעיל',
                                        'expired' => 'פג תוקף',
                                        'expiring_soon' => 'יפוג בקרוב',
                                        'unlimited' => 'ללא הגבלה'
                                    ][$company['subscription_status']] ?? $company['subscription_status'];
                                    ?>
                                    <span class="<?php echo $statusClass; ?>">
                                        <i class="fas fa-circle"></i>
                                        <?php echo $statusText; ?>
                                    </span>
                                </value>
                            </div>
                            
                            <?php if ($company['expires_at']): ?>
                            <div class="detail-item">
                                <label>תאריך תפוגה</label>
                                <value><?php echo date('d/m/Y', strtotime($company['expires_at'])); ?></value>
                            </div>
                            
                            <?php if ($company['days_to_expiry'] !== null): ?>
                            <div class="detail-item">
                                <label>זמן עד תפוגה</label>
                                <value>
                                    <?php if ($company['days_to_expiry'] > 0): ?>
                                        <?php echo $company['days_to_expiry']; ?> ימים
                                    <?php else: ?>
                                        <span class="text-danger">פג תוקף</span>
                                    <?php endif; ?>
                                </value>
                            </div>
                            <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Usage Limits -->
                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-chart-bar"></i> מגבלות שימוש</h3>
                    </div>
                    <div class="card-body">
                        <div class="usage-meter">
                            <label>משתמשים</label>
                            <div class="meter">
                                <?php 
                                $userPercentage = $company['max_users'] > 0 ? 
                                    ($company['active_users'] / $company['max_users']) * 100 : 0;
                                $userPercentage = min(100, $userPercentage);
                                ?>
                                <div class="meter-bar">
                                    <div class="meter-fill" style="width: <?php echo $userPercentage; ?>%"></div>
                                </div>
                                <div class="meter-text">
                                    <?php echo number_format($company['active_users']); ?>
                                    <?php if ($company['max_users'] > 0): ?>
                                        / <?php echo number_format($company['max_users']); ?>
                                    <?php else: ?>
                                        / ללא הגבלה
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <div class="usage-meter">
                            <label>אתרי עבודה</label>
                            <div class="meter">
                                <?php 
                                $sitePercentage = $company['max_sites'] > 0 ? 
                                    ($company['active_sites'] / $company['max_sites']) * 100 : 0;
                                $sitePercentage = min(100, $sitePercentage);
                                ?>
                                <div class="meter-bar">
                                    <div class="meter-fill" style="width: <?php echo $sitePercentage; ?>%"></div>
                                </div>
                                <div class="meter-text">
                                    <?php echo number_format($company['active_sites']); ?>
                                    <?php if ($company['max_sites'] > 0): ?>
                                        / <?php echo number_format($company['max_sites']); ?>
                                    <?php else: ?>
                                        / ללא הגבלה
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Company Status -->
                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-info-circle"></i> סטטוס החברה</h3>
                    </div>
                    <div class="card-body">
                        <div class="status-info">
                            <?php
                            $statusClass = [
                                'active' => 'badge-success',
                                'inactive' => 'badge-secondary',
                                'suspended' => 'badge-danger'
                            ][$company['status']] ?? 'badge-secondary';
                            
                            $statusText = [
                                'active' => 'פעיל',
                                'inactive' => 'לא פעיל', 
                                'suspended' => 'מושעה'
                            ][$company['status']] ?? $company['status'];
                            ?>
                            <span class="badge <?php echo $statusClass; ?> badge-lg">
                                <?php echo $statusText; ?>
                            </span>
                            
                            <div class="status-dates">
                                <small class="text-muted">
                                    נוצר: <?php echo date('d/m/Y', strtotime($company['created_at'])); ?><br>
                                    עודכן: <?php echo date('d/m/Y H:i', strtotime($company['updated_at'])); ?>
                                </small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-bolt"></i> פעולות מהירות</h3>
                    </div>
                    <div class="card-body">
                        <div class="quick-actions">
                            <a href="/modules/users/add.php?company_id=<?php echo $company['id']; ?>" class="btn btn-outline btn-sm btn-block">
                                <i class="fas fa-user-plus"></i>
                                הוסף משתמש
                            </a>
                            
                            <a href="/modules/worksites/add.php?company_id=<?php echo $company['id']; ?>" class="btn btn-outline btn-sm btn-block">
                                <i class="fas fa-map-pin"></i>
                                הוסף אתר עבודה
                            </a>
                            
                            <a href="/modules/deficiencies/index.php?company_id=<?php echo $company['id']; ?>" class="btn btn-outline btn-sm btn-block">
                                <i class="fas fa-list"></i>
                                צפה בליקויים
                            </a>
                            
                            <a href="/modules/reports/company.php?id=<?php echo $company['id']; ?>" class="btn btn-outline btn-sm btn-block">
                                <i class="fas fa-chart-line"></i>
                                דוח מפורט
                            </a>
                        </div>
                    </div>
                </div>
                
            </div>
        </div>
        
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>
