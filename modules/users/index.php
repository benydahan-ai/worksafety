<?php
/**
 * WorkSafety.io - מודול ניהול משתמשים
 * דף ראשי לניהול משתמשים עם עיצוב מקצועי ופונקציונליות מתקדמת
 */

// הגדרות דף
$pageTitle = 'ניהול משתמשים';
$pageDescription = 'ניהול משתמשים, הרשאות ופרמיטרי גישה למערכת';
$additionalCSS = ['/modules/users/assets/users.css'];
$additionalJS = ['/modules/users/assets/users.js'];

// כלילת קבצים נדרשים
require_once '../../includes/header.php';

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

try {
    $db = getDB();
    $companyId = $currentUser['company_id'] ?? 0;
    
    // בניית שאילתה בהתאם לתפקיד
    if ($userRole === 'super_admin') {
        // מנהל ראשי רואה את כל המשתמשים
        $whereClause = "1=1";
        $params = [];
    } else {
        // מנהל חברה רואה רק משתמשים מהחברה שלו
        $whereClause = "u.company_id = ?";
        $params = [$companyId];
    }
    
    // קבלת כל המשתמשים עם פרטי החברות
    $users = $db->fetchAll("
        SELECT 
            u.*,
            c.name as company_name,
            c.company_type,
            CASE 
                WHEN u.last_login IS NULL THEN 'מעולם לא התחבר'
                WHEN u.last_login >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 'פעיל'
                WHEN u.last_login >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 'לא פעיל לאחרונה'
                ELSE 'לא פעיל זמן רב'
            END as activity_status,
            DATE(u.created_at) as registration_date
        FROM users u 
        LEFT JOIN companies c ON u.company_id = c.id 
        WHERE {$whereClause}
        ORDER BY u.created_at DESC
    ", $params);
    
    // סטטיסטיקות מהירות
    $stats = [
        'total' => count($users),
        'active' => count(array_filter($users, fn($u) => $u['status'] === 'active')),
        'admins' => count(array_filter($users, fn($u) => in_array($u['role'], ['super_admin', 'company_admin']))),
        'recent_logins' => count(array_filter($users, fn($u) => $u['last_login'] && strtotime($u['last_login']) >= strtotime('-7 days')))
    ];
    
} catch (Exception $e) {
    error_log("Users page error: " . $e->getMessage());
    $_SESSION['flash_message']['danger'] = 'שגיאה בטעינת נתוני המשתמשים';
    $users = [];
    $stats = ['total' => 0, 'active' => 0, 'admins' => 0, 'recent_logins' => 0];
}
?>



<!-- Main Content -->
<main class="main-content">
    <!-- Header -->
    <header class="main-header">
        <div class="header-content">
            <div class="header-left">
                <h1 class="header-title">
                    <i class="fas fa-users"></i>
                    ניהול משתמשים
                </h1>
                <p class="header-subtitle">
                    ניהול משתמשים, הרשאות ופרמיטרי גישה למערכת
                </p>
            </div>
            <div class="header-actions">
                <button class="btn btn-outline btn-sm" onclick="exportUsers()">
                    <i class="fas fa-download"></i>
                    ייצא רשימה
                </button>
                <a href="/modules/users/permissions.php" class="btn btn-secondary btn-sm">
                    <i class="fas fa-key"></i>
                    ניהול הרשאות
                </a>
                <a href="/modules/users/add.php" class="btn btn-primary btn-sm">
                    <i class="fas fa-user-plus"></i>
                    הוסף משתמש
                </a>
            </div>
        </div>
    </header>
<!-- Include Sidebar -->
<?php include '../../includes/sidebar.php'; ?>
    <!-- Page Content -->
    <div class="page-content">
        
        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-value"><?php echo number_format($stats['total']); ?></div>
                    <div class="stat-label">סה"כ משתמשים</div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon" style="color: #10b981;">
                    <i class="fas fa-user-check"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-value"><?php echo number_format($stats['active']); ?></div>
                    <div class="stat-label">משתמשים פעילים</div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon" style="color: #8b5cf6;">
                    <i class="fas fa-user-shield"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-value"><?php echo number_format($stats['admins']); ?></div>
                    <div class="stat-label">מנהלים</div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon" style="color: #06b6d4;">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-value"><?php echo number_format($stats['recent_logins']); ?></div>
                    <div class="stat-label">התחברו השבוע</div>
                </div>
            </div>
        </div>

        <!-- Filters and Search -->
        <div class="card filters-card">
            <div class="card-body">
                <div class="filters-row">
                    <div class="filter-group">
                        <label class="filter-label">חיפוש:</label>
                        <div class="search-wrapper">
                            <i class="fas fa-search"></i>
                            <input type="text" class="form-control" id="searchUsers" placeholder="חפש לפי שם, אימייל או חברה...">
                        </div>
                    </div>
                    
                    <div class="filter-group">
                        <label class="filter-label">סטטוס:</label>
                        <select class="form-control" id="filterStatus">
                            <option value="">כל הסטטוסים</option>
                            <option value="active">פעיל</option>
                            <option value="inactive">לא פעיל</option>
                            <option value="suspended">מושהה</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label class="filter-label">תפקיד:</label>
                        <select class="form-control" id="filterRole">
                            <option value="">כל התפקידים</option>
                            <option value="super_admin">מנהל ראשי</option>
                            <option value="company_admin">מנהל חברה</option>
                            <option value="contractor">קבלן</option>
                            <option value="safety_manager">מנהל בטיחות</option>
                            <option value="inspector">מפקח</option>
                            <option value="worker">עובד</option>
                        </select>
                    </div>
                    
                    <?php if ($userRole === 'super_admin'): ?>
                    <div class="filter-group">
                        <label class="filter-label">חברה:</label>
                        <select class="form-control" id="filterCompany">
                            <option value="">כל החברות</option>
                            <?php
                            try {
                                $companies = $db->fetchAll("SELECT id, name FROM companies ORDER BY name");
                                foreach ($companies as $company) {
                                    echo "<option value='{$company['id']}'>" . htmlspecialchars($company['name']) . "</option>";
                                }
                            } catch (Exception $e) {
                                // שגיאה בטעינת חברות
                            }
                            ?>
                        </select>
                    </div>
                    <?php endif; ?>
                    
                    <div class="filter-group">
                        <button class="btn btn-outline btn-sm" onclick="resetFilters()">
                            <i class="fas fa-undo"></i>
                            איפוס
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Users Table -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">רשימת משתמשים</h3>
                <div class="card-actions">
                    <div class="view-options">
                        <button class="btn btn-sm btn-outline active" data-view="table">
                            <i class="fas fa-table"></i>
                        </button>
                        <button class="btn btn-sm btn-outline" data-view="grid">
                            <i class="fas fa-th"></i>
                        </button>
                    </div>
                    <span class="results-count">מציג <?php echo count($users); ?> משתמשים</span>
                </div>
            </div>
            <div class="card-body">
                
                <?php if (empty($users)): ?>
                    <div class="empty-state">
                        <i class="fas fa-users"></i>
                        <h3>אין משתמשים במערכת</h3>
                        <p>התחל בהוספת המשתמש הראשון למערכת</p>
                        <a href="/modules/users/add.php" class="btn btn-primary">
                            <i class="fas fa-user-plus"></i>
                            הוסף משתמש ראשון
                        </a>
                    </div>
                <?php else: ?>
                
                <!-- Table View -->
                <div id="tableView" class="table-container">
                    <table class="table" id="usersTable">
                        <thead>
                            <tr>
                                <th>
                                    <input type="checkbox" id="selectAll">
                                </th>
                                <th>משתמש</th>
                                <th>חברה</th>
                                <th>תפקיד</th>
                                <th>סטטוס</th>
                                <th>התחברות אחרונה</th>
                                <th>תאריך הצטרפות</th>
                                <th>פעולות</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user): ?>
                            <tr data-user-id="<?php echo $user['id']; ?>">
                                <td>
                                    <input type="checkbox" class="user-checkbox" value="<?php echo $user['id']; ?>">
                                </td>
                                <td>
                                    <div class="user-cell">
                                        <div class="user-avatar">
                                            <?php if (!empty($user['avatar'])): ?>
                                                <img src="/uploads/avatars/<?php echo htmlspecialchars($user['avatar']); ?>" alt="">
                                            <?php else: ?>
                                                <span><?php echo strtoupper(mb_substr($user['first_name'], 0, 1) . mb_substr($user['last_name'], 0, 1)); ?></span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="user-info">
                                            <div class="user-name">
                                                <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>
                                            </div>
                                            <div class="user-email">
                                                <?php echo htmlspecialchars($user['email']); ?>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="company-name">
                                        <?php echo htmlspecialchars($user['company_name'] ?? 'ללא חברה'); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="role-badge role-<?php echo $user['role']; ?>">
                                        <?php 
                                        $roleNames = [
                                            'super_admin' => 'מנהל ראשי',
                                            'company_admin' => 'מנהל חברה',
                                            'contractor' => 'קבלן',
                                            'safety_manager' => 'מנהל בטיחות',
                                            'inspector' => 'מפקח',
                                            'worker' => 'עובד'
                                        ];
                                        echo $roleNames[$user['role']] ?? $user['role'];
                                        ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="status-badge status-<?php echo $user['status']; ?>">
                                        <?php 
                                        $statusNames = [
                                            'active' => 'פעיל',
                                            'inactive' => 'לא פעיל',
                                            'suspended' => 'מושהה'
                                        ];
                                        echo $statusNames[$user['status']] ?? $user['status'];
                                        ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="last-login">
                                        <?php if ($user['last_login']): ?>
                                            <span class="login-date"><?php echo formatHebrewDateTime($user['last_login']); ?></span>
                                            <span class="activity-indicator activity-<?php echo strtolower(str_replace(' ', '-', $user['activity_status'])); ?>">
                                                <?php echo $user['activity_status']; ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="no-login">מעולם לא התחבר</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <span class="registration-date">
                                        <?php echo formatHebrewDate($user['registration_date']); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="table-actions">
                                        <a href="/modules/users/view.php?id=<?php echo $user['id']; ?>" 
                                           class="btn btn-sm btn-outline" 
                                           data-tooltip="צפה בפרטים">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="/modules/users/edit.php?id=<?php echo $user['id']; ?>" 
                                           class="btn btn-sm btn-primary" 
                                           data-tooltip="עריכה">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                        <button class="btn btn-sm btn-danger" 
                                                onclick="deleteUser(<?php echo $user['id']; ?>)" 
                                                data-tooltip="מחיקה">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Grid View -->
                <div id="gridView" class="users-grid" style="display: none;">
                    <?php foreach ($users as $user): ?>
                    <div class="user-card" data-user-id="<?php echo $user['id']; ?>">
                        <div class="user-card-header">
                            <div class="user-avatar-large">
                                <?php if (!empty($user['avatar'])): ?>
                                    <img src="/uploads/avatars/<?php echo htmlspecialchars($user['avatar']); ?>" alt="">
                                <?php else: ?>
                                    <span><?php echo strtoupper(mb_substr($user['first_name'], 0, 1) . mb_substr($user['last_name'], 0, 1)); ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="user-card-actions">
                                <div class="dropdown">
                                    <button class="btn btn-sm btn-outline dropdown-toggle">
                                        <i class="fas fa-ellipsis-v"></i>
                                    </button>
                                    <div class="dropdown-menu">
                                        <a href="/modules/users/view.php?id=<?php echo $user['id']; ?>" class="dropdown-item">
                                            <i class="fas fa-eye"></i> צפה בפרטים
                                        </a>
                                        <a href="/modules/users/edit.php?id=<?php echo $user['id']; ?>" class="dropdown-item">
                                            <i class="fas fa-edit"></i> עריכה
                                        </a>
                                        <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                        <button class="dropdown-item text-danger" onclick="deleteUser(<?php echo $user['id']; ?>)">
                                            <i class="fas fa-trash"></i> מחיקה
                                        </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="user-card-body">
                            <h4 class="user-card-name">
                                <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>
                            </h4>
                            <p class="user-card-email">
                                <?php echo htmlspecialchars($user['email']); ?>
                            </p>
                            <div class="user-card-meta">
                                <span class="role-badge role-<?php echo $user['role']; ?>">
                                    <?php echo $roleNames[$user['role']] ?? $user['role']; ?>
                                </span>
                                <span class="status-badge status-<?php echo $user['status']; ?>">
                                    <?php echo $statusNames[$user['status']] ?? $user['status']; ?>
                                </span>
                            </div>
                            <div class="user-card-details">
                                <div class="detail-item">
                                    <i class="fas fa-building"></i>
                                    <span><?php echo htmlspecialchars($user['company_name'] ?? 'ללא חברה'); ?></span>
                                </div>
                                <div class="detail-item">
                                    <i class="fas fa-clock"></i>
                                    <span>
                                        <?php if ($user['last_login']): ?>
                                            התחבר <?php echo formatHebrewDateTime($user['last_login']); ?>
                                        <?php else: ?>
                                            מעולם לא התחבר
                                        <?php endif; ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Bulk Actions -->
        <div class="bulk-actions" id="bulkActions" style="display: none;">
            <div class="bulk-actions-content">
                <span class="selected-count">נבחרו <span id="selectedCount">0</span> משתמשים</span>
                <div class="bulk-buttons">
                    <button class="btn btn-sm btn-secondary" onclick="bulkAction('activate')">
                        <i class="fas fa-check"></i>
                        הפעל
                    </button>
                    <button class="btn btn-sm btn-warning" onclick="bulkAction('deactivate')">
                        <i class="fas fa-pause"></i>
                        השהה
                    </button>
                    <button class="btn btn-sm btn-danger" onclick="bulkAction('delete')">
                        <i class="fas fa-trash"></i>
                        מחק
                    </button>
                </div>
                <button class="btn btn-sm btn-outline" onclick="clearSelection()">
                    <i class="fas fa-times"></i>
                    בטל בחירה
                </button>
            </div>
        </div>
        
    </div>
</main>

<!-- Include Footer -->
<?php include '../../includes/footer.php'; ?>
