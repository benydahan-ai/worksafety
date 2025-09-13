<?php
/**
 * WorkSafety.io - מודול ניהול חברות מתוקן
 * דף ראשי לניהול חברות עם SQL queries דינמיים
 */

// הגדרות דף
$pageTitle = 'ניהול חברות';
$pageDescription = 'ניהול חברות לקוחות, הגבלות שימוש ופרמיטרי מנוי';
$additionalCSS = ['/modules/companies/assets/companies.css'];
$additionalJS = ['/modules/companies/assets/companies.js'];

// כלילת קבצים נדרשים
require_once '../../includes/header.php';

// וידוא התחברות והרשאות
if (!isset($_SESSION['user_id'])) {
    header('Location: /login.php');
    exit;
}

// בדיקת הרשאות - רק מנהל ראשי יכול לנהל חברות
$userRole = $currentUser['role'] ?? 'worker';
if (!in_array($userRole, ['super_admin', 'company_admin', 'admin'])) {
    $_SESSION['flash_message']['danger'] = 'אין לך הרשאה לגשת לעמוד זה';
    header('Location: /dashboard.php');
    exit;
}

try {
    $db = getDB();
    
    // בדיקת עמודות קיימות בטבלת companies
    $companyColumns = [];
    try {
        $stmt = $db->query("SHOW COLUMNS FROM companies");
        while ($column = $stmt->fetch()) {
            $companyColumns[] = $column['Field'];
        }
    } catch (Exception $e) {
        // טבלת companies לא קיימת
        $_SESSION['flash_message']['danger'] = 'טבלת החברות לא קיימת. נא להריץ את setup_complete.php תחילה';
        header('Location: /dashboard.php');
        exit;
    }
    
    // זיהוי שם העמודה הנכון לשם החברה
    $companyNameField = 'id'; // ברירת מחדל
    if (in_array('company_name', $companyColumns)) {
        $companyNameField = 'company_name';
    } elseif (in_array('name', $companyColumns)) {
        $companyNameField = 'name';
    }
    
    // בניית select fields בהתאם לעמודות קיימות
    $selectFields = ['c.id'];
    
    // הוספת שדות בהתאם לקיום
    if (in_array('company_name', $companyColumns)) {
        $selectFields[] = 'c.company_name';
    } elseif (in_array('name', $companyColumns)) {
        $selectFields[] = 'c.name as company_name';
    } else {
        $selectFields[] = "'לא מוגדר' as company_name";
    }
    
    if (in_array('company_type', $companyColumns)) {
        $selectFields[] = 'c.company_type';
    } else {
        $selectFields[] = "'client' as company_type";
    }
    
    if (in_array('status', $companyColumns)) {
        $selectFields[] = 'c.status';
    } else {
        $selectFields[] = "'active' as status";
    }
    
    if (in_array('email', $companyColumns)) {
        $selectFields[] = 'c.email';
    } else {
        $selectFields[] = "'לא מוגדר' as email";
    }
    
    if (in_array('phone', $companyColumns)) {
        $selectFields[] = 'c.phone';
    } else {
        $selectFields[] = "'לא מוגדר' as phone";
    }
    
    if (in_array('contact_person', $companyColumns)) {
        $selectFields[] = 'c.contact_person';
    } else {
        $selectFields[] = "'לא מוגדר' as contact_person";
    }
    
    if (in_array('created_at', $companyColumns)) {
        $selectFields[] = 'c.created_at';
    } else {
        $selectFields[] = 'NOW() as created_at';
    }
    
    if (in_array('updated_at', $companyColumns)) {
        $selectFields[] = 'c.updated_at';
    } else {
        $selectFields[] = 'NOW() as updated_at';
    }
    
    // שדות נוספים אופציונליים
    if (in_array('subscription_plan', $companyColumns)) {
        $selectFields[] = 'c.subscription_plan';
    }
    
    if (in_array('max_users', $companyColumns)) {
        $selectFields[] = 'c.max_users';
    }
    
    if (in_array('max_sites', $companyColumns)) {
        $selectFields[] = 'c.max_sites';
    }
    
    // פרמטרי חיפוש וסינון
    $search = $_GET['search'] ?? '';
    $status = $_GET['status'] ?? '';
    $company_type = $_GET['company_type'] ?? '';
    $subscription_plan = $_GET['subscription_plan'] ?? '';
    $page = intval($_GET['page'] ?? 1);
    $limit = 20; // מספר חברות בעמוד
    $offset = ($page - 1) * $limit;
    
    // בניית שאילתה בהתאם לתפקיד
    $whereConditions = [];
    $params = [];
    
    if ($userRole === 'company_admin') {
        // מנהל חברה רואה רק את החברה שלו ואת הקבלנים שלה
        if (in_array('parent_company_id', $companyColumns)) {
            $whereConditions[] = "(c.id = ? OR c.parent_company_id = ?)";
            $params[] = $currentUser['company_id'];
            $params[] = $currentUser['company_id'];
        } else {
            $whereConditions[] = "c.id = ?";
            $params[] = $currentUser['company_id'];
        }
    }
    
    // חיפוש טקסט
    if (!empty($search)) {
        $searchConditions = [];
        if (in_array($companyNameField, $companyColumns)) {
            $searchConditions[] = "c.{$companyNameField} LIKE ?";
        }
        if (in_array('email', $companyColumns)) {
            $searchConditions[] = "c.email LIKE ?";
        }
        if (in_array('contact_person', $companyColumns)) {
            $searchConditions[] = "c.contact_person LIKE ?";
        }
        if (in_array('phone', $companyColumns)) {
            $searchConditions[] = "c.phone LIKE ?";
        }
        
        if (!empty($searchConditions)) {
            $whereConditions[] = "(" . implode(" OR ", $searchConditions) . ")";
            $searchTerm = "%{$search}%";
            for ($i = 0; $i < count($searchConditions); $i++) {
                $params[] = $searchTerm;
            }
        }
    }
    
    // סינון לפי סטטוס
    if (!empty($status) && in_array('status', $companyColumns)) {
        $whereConditions[] = "c.status = ?";
        $params[] = $status;
    }
    
    // סינון לפי סוג חברה
    if (!empty($company_type) && in_array('company_type', $companyColumns)) {
        $whereConditions[] = "c.company_type = ?";
        $params[] = $company_type;
    }
    
    // סינון לפי תוכנית מנוי
    if (!empty($subscription_plan) && in_array('subscription_plan', $companyColumns)) {
        $whereConditions[] = "c.subscription_plan = ?";
        $params[] = $subscription_plan;
    }
    
    $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
    
    // קבלת מספר החברות הכולל (לקביעת מספר עמודים)
    $totalQuery = "SELECT COUNT(*) as total FROM companies c {$whereClause}";
    $totalResult = $db->fetchOne($totalQuery, $params);
    $totalCompanies = $totalResult['total'] ?? 0;
    $totalPages = ceil($totalCompanies / $limit);
    
    // קבלת החברות עם פגינציה
    $selectFieldsStr = implode(', ', $selectFields);
    
    // בניית שאילתת הראשית
    $mainQuery = "SELECT {$selectFieldsStr}";
    
    // הוספת sub-queries לספירת משתמשים ואתרים (אם הטבלאות קיימות)
    if ($db->tableExists('users')) {
        $mainQuery .= ", (SELECT COUNT(*) FROM users WHERE company_id = c.id";
        if ($db->columnExists('users', 'status')) {
            $mainQuery .= " AND status = 'active'";
        }
        $mainQuery .= ") as active_users";
    } else {
        $mainQuery .= ", 0 as active_users";
    }
    
    if ($db->tableExists('worksites') || $db->tableExists('work_sites')) {
        $sitesTable = $db->tableExists('worksites') ? 'worksites' : 'work_sites';
        $mainQuery .= ", (SELECT COUNT(*) FROM {$sitesTable} WHERE company_id = c.id";
        if ($db->columnExists($sitesTable, 'status')) {
            $mainQuery .= " AND status = 'active'";
        }
        $mainQuery .= ") as active_sites";
    } else {
        $mainQuery .= ", 0 as active_sites";
    }
    
    // הוספת מידע על סטטוס מנוי (אם יש שדה תפוגה)
    if (in_array('expires_at', $companyColumns)) {
        $mainQuery .= ", CASE 
            WHEN c.expires_at IS NULL THEN 'ללא הגבלה'
            WHEN c.expires_at < NOW() THEN 'פג תוקף'
            WHEN c.expires_at < DATE_ADD(NOW(), INTERVAL 30 DAY) THEN 'יפוג בקרוב'
            ELSE 'פעיל'
        END as subscription_status,
        DATEDIFF(c.expires_at, NOW()) as days_to_expiry";
    } else {
        $mainQuery .= ", 'פעיל' as subscription_status, NULL as days_to_expiry";
    }
    
    $mainQuery .= " FROM companies c {$whereClause} ORDER BY ";
    
    // מיון בהתאם לעמודות קיימות
    if (in_array('company_type', $companyColumns)) {
        $mainQuery .= "CASE c.company_type WHEN 'main' THEN 1 WHEN 'client' THEN 2 ELSE 3 END, ";
    }
    
    $mainQuery .= "c.{$companyNameField} ASC LIMIT {$limit} OFFSET {$offset}";
    
    $companies = $db->fetchAll($mainQuery, $params);
    
    // סטטיסטיקות מהירות
    $statsQuery = "SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN " . (in_array('status', $companyColumns) ? "status = 'active'" : "1=1") . " THEN 1 ELSE 0 END) as active,
        SUM(CASE WHEN " . (in_array('status', $companyColumns) ? "status = 'inactive'" : "1=0") . " THEN 1 ELSE 0 END) as inactive";
    
    if (in_array('company_type', $companyColumns)) {
        $statsQuery .= ",
        SUM(CASE WHEN company_type = 'main' THEN 1 ELSE 0 END) as main_companies,
        SUM(CASE WHEN company_type = 'client' THEN 1 ELSE 0 END) as client_companies";
    } else {
        $statsQuery .= ", 0 as main_companies, 0 as client_companies";
    }
    
    if (in_array('expires_at', $companyColumns)) {
        $statsQuery .= ", SUM(CASE WHEN expires_at < NOW() THEN 1 ELSE 0 END) as expired";
    } else {
        $statsQuery .= ", 0 as expired";
    }
    
    $statsQuery .= " FROM companies c";
    
    if ($userRole === 'company_admin') {
        if (in_array('parent_company_id', $companyColumns)) {
            $statsQuery .= " WHERE (c.id = {$currentUser['company_id']} OR c.parent_company_id = {$currentUser['company_id']})";
        } else {
            $statsQuery .= " WHERE c.id = {$currentUser['company_id']}";
        }
    }
    
    $stats = $db->fetchOne($statsQuery);
    
} catch (Exception $e) {
    error_log("Companies page error: " . $e->getMessage());
    $_SESSION['flash_message']['danger'] = 'שגיאה בטעינת נתוני החברות';
    $companies = [];
    $stats = [
        'total' => 0, 'active' => 0, 'inactive' => 0, 
        'main_companies' => 0, 'client_companies' => 0, 'expired' => 0
    ];
    $totalPages = 1;
    $totalCompanies = 0;
}
require_once '../../includes/sidebar.php';
?>

<div class="companies-module">
    <!-- כותרת ופעולות -->
    <div class="module-header">
        <div class="header-content">
            <div class="title-section">
                <h1>
                    <i class="fas fa-building"></i>
                    ניהול חברות
                </h1>
                <p class="subtitle">ניהול חברות לקוחות, הגבלות שימוש ומידע על המנויים</p>
            </div>
            <div class="actions-section">
                <?php if (in_array($userRole, ['super_admin'])): ?>
                    <button class="btn btn-primary" onclick="openAddCompanyModal()">
                        <i class="fas fa-plus"></i> הוסף חברה חדשה
                    </button>
                <?php endif; ?>
                <button class="btn btn-secondary" onclick="refreshCompanies()">
                    <i class="fas fa-sync-alt"></i> רענן
                </button>
            </div>
        </div>
    </div>

    <!-- סטטיסטיקות מהירות -->
    <div class="stats-cards">
        <div class="stat-card primary">
            <div class="stat-icon">
                <i class="fas fa-building"></i>
            </div>
            <div class="stat-content">
                <div class="stat-number"><?php echo number_format($stats['total'] ?? 0); ?></div>
                <div class="stat-label">סה"כ חברות</div>
            </div>
        </div>

        <div class="stat-card success">
            <div class="stat-icon">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="stat-content">
                <div class="stat-number"><?php echo number_format($stats['active'] ?? 0); ?></div>
                <div class="stat-label">חברות פעילות</div>
            </div>
        </div>

        <div class="stat-card info">
            <div class="stat-icon">
                <i class="fas fa-crown"></i>
            </div>
            <div class="stat-content">
                <div class="stat-number"><?php echo number_format($stats['main_companies'] ?? 0); ?></div>
                <div class="stat-label">חברות ראשיות</div>
            </div>
        </div>

        <div class="stat-card warning">
            <div class="stat-icon">
                <i class="fas fa-clock"></i>
            </div>
            <div class="stat-content">
                <div class="stat-number"><?php echo number_format($stats['expired'] ?? 0); ?></div>
                <div class="stat-label">מנויים שפג תוקפם</div>
            </div>
        </div>
    </div>

    <!-- כלי חיפוש וסינון -->
    <div class="search-filters">
        <form method="GET" class="filters-form">
            <div class="search-group">
                <div class="search-input-wrapper">
                    <i class="fas fa-search"></i>
                    <input type="text" 
                           name="search" 
                           placeholder="חיפוש לפי שם, אימייל או איש קשר..." 
                           value="<?php echo htmlspecialchars($search); ?>"
                           class="search-input">
                </div>
            </div>

            <div class="filters-group">
                <?php if (in_array('status', $companyColumns)): ?>
                <select name="status" class="filter-select">
                    <option value="">כל הסטטוסים</option>
                    <option value="active" <?php echo $status === 'active' ? 'selected' : ''; ?>>פעיל</option>
                    <option value="inactive" <?php echo $status === 'inactive' ? 'selected' : ''; ?>>לא פעיל</option>
                    <option value="suspended" <?php echo $status === 'suspended' ? 'selected' : ''; ?>>מושעה</option>
                </select>
                <?php endif; ?>

                <?php if (in_array('company_type', $companyColumns)): ?>
                <select name="company_type" class="filter-select">
                    <option value="">כל הסוגים</option>
                    <option value="main" <?php echo $company_type === 'main' ? 'selected' : ''; ?>>חברה ראשית</option>
                    <option value="client" <?php echo $company_type === 'client' ? 'selected' : ''; ?>>חברה לקוחה</option>
                    <option value="contractor" <?php echo $company_type === 'contractor' ? 'selected' : ''; ?>>קבלן</option>
                </select>
                <?php endif; ?>

                <?php if (in_array('subscription_plan', $companyColumns)): ?>
                <select name="subscription_plan" class="filter-select">
                    <option value="">כל התוכניות</option>
                    <option value="basic" <?php echo $subscription_plan === 'basic' ? 'selected' : ''; ?>>בסיסי</option>
                    <option value="standard" <?php echo $subscription_plan === 'standard' ? 'selected' : ''; ?>>סטנדרט</option>
                    <option value="premium" <?php echo $subscription_plan === 'premium' ? 'selected' : ''; ?>>פרמיום</option>
                    <option value="enterprise" <?php echo $subscription_plan === 'enterprise' ? 'selected' : ''; ?>>ארגוני</option>
                </select>
                <?php endif; ?>
            </div>

            <div class="actions-group">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-search"></i> חפש
                </button>
                <a href="?" class="btn btn-secondary">
                    <i class="fas fa-times"></i> נקה
                </a>
            </div>
        </form>
    </div>

    <!-- טבלת חברות -->
    <div class="companies-table-container">
        <table class="companies-table">
            <thead>
                <tr>
                    <th>שם החברה</th>
                    <th>סוג</th>
                    <th>איש קשר</th>
                    <th>אימייל</th>
                    <th>טלפון</th>
                    <th>סטטוס</th>
                    <th>משתמשים</th>
                    <th>אתרים</th>
                    <th>נוצר בתאריך</th>
                    <th>פעולות</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($companies)): ?>
                    <tr>
                        <td colspan="10" class="no-data">
                            <div class="no-data-content">
                                <i class="fas fa-building"></i>
                                <p>לא נמצאו חברות התואמות לקריטריונים</p>
                                <?php if (empty($search) && empty($status) && empty($company_type)): ?>
                                    <p class="sub-text">התחל בהוספת חברה ראשונה</p>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($companies as $company): ?>
                        <tr class="company-row" data-company-id="<?php echo $company['id']; ?>">
                            <td class="company-name">
                                <div class="company-info">
                                    <span class="name"><?php echo htmlspecialchars($company['company_name'] ?? 'לא מוגדר'); ?></span>
                                    <?php if ($company['company_type'] ?? '' === 'main'): ?>
                                        <span class="badge badge-main">ראשית</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td class="company-type">
                                <span class="type-badge type-<?php echo $company['company_type'] ?? 'client'; ?>">
                                    <?php 
                                    $types = [
                                        'main' => 'ראשית',
                                        'client' => 'לקוח',
                                        'contractor' => 'קבלן'
                                    ];
                                    echo $types[$company['company_type'] ?? 'client'] ?? 'לא מוגדר';
                                    ?>
                                </span>
                            </td>
                            <td><?php echo htmlspecialchars($company['contact_person'] ?? 'לא מוגדר'); ?></td>
                            <td>
                                <?php if (!empty($company['email'])): ?>
                                    <a href="mailto:<?php echo htmlspecialchars($company['email']); ?>" class="email-link">
                                        <?php echo htmlspecialchars($company['email']); ?>
                                    </a>
                                <?php else: ?>
                                    <span class="text-muted">לא מוגדר</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($company['phone'])): ?>
                                    <a href="tel:<?php echo htmlspecialchars($company['phone']); ?>" class="phone-link">
                                        <?php echo htmlspecialchars($company['phone']); ?>
                                    </a>
                                <?php else: ?>
                                    <span class="text-muted">לא מוגדר</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="status-badge status-<?php echo $company['status'] ?? 'active'; ?>">
                                    <?php 
                                    $statuses = [
                                        'active' => 'פעיל',
                                        'inactive' => 'לא פעיל',
                                        'suspended' => 'מושעה'
                                    ];
                                    echo $statuses[$company['status'] ?? 'active'] ?? 'לא מוגדר';
                                    ?>
                                </span>
                            </td>
                            <td class="users-count"><?php echo number_format($company['active_users'] ?? 0); ?></td>
                            <td class="sites-count"><?php echo number_format($company['active_sites'] ?? 0); ?></td>
                            <td class="created-date">
                                <?php 
                                if (!empty($company['created_at'])) {
                                    echo formatHebrewDate($company['created_at']);
                                } else {
                                    echo 'לא מוגדר';
                                }
                                ?>
                            </td>
                            <td class="actions">
                                <div class="action-buttons">
                                    <button class="btn-icon btn-view" onclick="viewCompany(<?php echo $company['id']; ?>)" title="צפייה">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <?php if (in_array($userRole, ['super_admin', 'company_admin'])): ?>
                                        <button class="btn-icon btn-edit" onclick="editCompany(<?php echo $company['id']; ?>)" title="עריכה">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                    <?php endif; ?>
                                    <?php if ($userRole === 'super_admin'): ?>
                                        <button class="btn-icon btn-delete" onclick="deleteCompany(<?php echo $company['id']; ?>)" title="מחיקה">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- עמוד nav -->
    <?php if ($totalPages > 1): ?>
        <div class="pagination">
            <?php if ($page > 1): ?>
                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>" class="page-btn prev">
                    <i class="fas fa-chevron-right"></i> הקודם
                </a>
            <?php endif; ?>

            <div class="page-numbers">
                <?php
                $start = max(1, $page - 2);
                $end = min($totalPages, $page + 2);
                
                for ($i = $start; $i <= $end; $i++):
                ?>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>" 
                       class="page-number <?php echo $i === $page ? 'active' : ''; ?>">
                        <?php echo $i; ?>
                    </a>
                <?php endfor; ?>
            </div>

            <?php if ($page < $totalPages): ?>
                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>" class="page-btn next">
                    הבא <i class="fas fa-chevron-left"></i>
                </a>
            <?php endif; ?>
        </div>
        
        <div class="pagination-info">
            מציג <?php echo number_format(($page - 1) * $limit + 1); ?>-<?php echo number_format(min($page * $limit, $totalCompanies)); ?> 
            מתוך <?php echo number_format($totalCompanies); ?> חברות
        </div>
    <?php endif; ?>
</div>

<!-- JavaScript Functions -->
<script>
// פונקציות JavaScript למודול החברות
function openAddCompanyModal() {
    window.location.href = 'add.php';
}

function viewCompany(companyId) {
    window.location.href = 'view.php?id=' + companyId;
}

function editCompany(companyId) {
    window.location.href = 'edit.php?id=' + companyId;
}

function deleteCompany(companyId) {
    if (confirm('האם אתה בטוח שברצונך למחוק את החברה? פעולה זו אינה ניתנת לביטול.')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'delete.php';
        
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'company_id';
        input.value = companyId;
        
        form.appendChild(input);
        document.body.appendChild(form);
        form.submit();
    }
}

function refreshCompanies() {
    window.location.reload();
}

// מטפל בטעינת הדף
document.addEventListener('DOMContentLoaded', function() {
    // הוסף כאן קוד JavaScript נוסף לפי הצורך
});
</script>

<?php
// כלילת footer
require_once '../../includes/footer.php';
?>
