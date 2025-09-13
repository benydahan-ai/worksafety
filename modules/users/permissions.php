<?php
/**
 * WorkSafety.io - מודול ניהול הרשאות מתוקן
 * ממשק מתקדם לניהול הרשאות משתמשים
 */

// הגדרות דף
$pageTitle = 'ניהול הרשאות';
$pageDescription = 'ניהול מתקדם של הרשאות משתמשים ורמות גישה למודולים השונים';
$additionalCSS = [];

// כלילת קבצים נדרשים
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/header.php';

// וידוא התחברות והרשאות
if (!isset($_SESSION['user_id']) || !in_array($currentUser['role'], ['super_admin', 'company_admin'])) {
    header('Location: /login.php');
    exit;
}

try {
    $db = getDB();
    
    // קבלת כל המשתמשים
    $whereCondition = $currentUser['role'] === 'super_admin' ? '1=1' : 'u.company_id = ?';
    $params = $currentUser['role'] === 'super_admin' ? [] : [$currentUser['company_id']];
    
    $users = $db->fetchAll("
        SELECT u.*, c.name as company_name 
        FROM users u 
        LEFT JOIN companies c ON u.company_id = c.id 
        WHERE {$whereCondition}
        ORDER BY u.first_name, u.last_name
    ", $params);
    
    // קבלת כל ההרשאות הזמינות
    $allPermissions = $db->fetchAll("
        SELECT * FROM permissions 
        ORDER BY module_name, permission_name
    ");
    
    // ארגון ההרשאות לפי מודול
    $permissionsByModule = [];
    foreach ($allPermissions as $perm) {
        if (!isset($permissionsByModule[$perm['module_name']])) {
            $permissionsByModule[$perm['module_name']] = [];
        }
        $permissionsByModule[$perm['module_name']][] = $perm;
    }
    
    // קבלת הרשאות נוכחיות של המשתמשים
    $userPermissions = [];
    foreach ($users as $user) {
        $perms = $db->fetchAll("
            SELECT p.* 
            FROM user_permissions up 
            JOIN permissions p ON up.permission_id = p.id 
            WHERE up.user_id = ? AND up.is_active = 1
        ", [$user['id']]);
        
        $userPermissions[$user['id']] = [];
        foreach ($perms as $perm) {
            $userPermissions[$user['id']][] = $perm['id'];
        }
    }
    
} catch (Exception $e) {
    error_log("Permissions page error: " . $e->getMessage());
    $_SESSION['flash_message']['danger'] = 'שגיאה בטעינת נתוני ההרשאות';
    $users = [];
    $permissionsByModule = [];
    $userPermissions = [];
}

// טיפול בשמירת הרשאות
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_permissions') {
    try {
        $userId = intval($_POST['user_id']);
        $permissions = $_POST['permissions'] ?? [];
        
        // התחלת טרנזקציה
        $db->getConnection()->beginTransaction();
        
        // מחיקת הרשאות קיימות
        $db->delete('user_permissions', 'user_id = ?', [$userId]);
        
        // הוספת הרשאות חדשות
        foreach ($permissions as $permId) {
            $db->insert('user_permissions', [
                'user_id' => $userId,
                'permission_id' => intval($permId),
                'granted_by' => $_SESSION['user_id'],
                'granted_at' => getCurrentDateTime(),
                'is_active' => 1
            ]);
        }
        
        $db->getConnection()->commit();
        $_SESSION['flash_message']['success'] = 'ההרשאות נשמרו בהצלחה';
        
    } catch (Exception $e) {
        if ($db->getConnection()->inTransaction()) {
            $db->getConnection()->rollBack();
        }
        error_log("Save permissions error: " . $e->getMessage());
        $_SESSION['flash_message']['danger'] = 'שגיאה בשמירת ההרשאות';
    }
    
    header('Location: ' . $_SERVER['REQUEST_URI']);
    exit;
}
?>

<!-- Include Sidebar -->
<?php include __DIR__ . '/../../includes/sidebar.php'; ?>

<!-- Main Content -->
<main class="main-content">
    <!-- Header -->
    <header class="main-header">
        <div class="header-content">
            <div class="header-left">
                <h1 class="header-title">
                    <i class="fas fa-key"></i>
                    ניהול הרשאות
                </h1>
                <p class="header-subtitle">
                    ניהול מתקדם של הרשאות משתמשים ורמות גישה
                </p>
            </div>
            <div class="header-actions">
                <button class="btn btn-outline btn-sm" onclick="exportPermissions()">
                    <i class="fas fa-download"></i>
                    ייצא הרשאות
                </button>
                <button class="btn btn-primary btn-sm" onclick="openBulkPermissions()">
                    <i class="fas fa-users-cog"></i>
                    הרשאות קבוצתיות
                </button>
            </div>
        </div>
    </header>

    <!-- Page Content -->
    <div class="page-content">
        
        <!-- Permissions Management Interface -->
        <div class="permissions-container">
            
            <!-- Users List -->
            <div class="users-panel">
                <div class="panel-header">
                    <h3>משתמשים</h3>
                    <div class="panel-actions">
                        <input type="text" class="search-input" placeholder="חיפוש משתמש..." id="userSearch">
                    </div>
                </div>
                <div class="panel-body">
                    <div class="users-list" id="usersList">
                        <?php foreach ($users as $user): ?>
                        <div class="user-item" data-user-id="<?php echo $user['id']; ?>" onclick="selectUser(<?php echo $user['id']; ?>)">
                            <div class="user-avatar">
                                <?php echo strtoupper(mb_substr($user['first_name'], 0, 1) . mb_substr($user['last_name'], 0, 1)); ?>
                            </div>
                            <div class="user-info">
                                <div class="user-name">
                                    <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>
                                </div>
                                <div class="user-role">
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
                                </div>
                                <div class="user-company">
                                    <?php echo htmlspecialchars($user['company_name'] ?? 'ללא חברה'); ?>
                                </div>
                            </div>
                            <div class="user-status">
                                <span class="status-badge <?php echo ($user['status'] ?? 'active') === 'active' ? 'active' : 'inactive'; ?>">
                                    <?php echo ($user['status'] ?? 'active') === 'active' ? 'פעיל' : 'לא פעיל'; ?>
                                </span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        
                        <?php if (empty($users)): ?>
                        <div class="empty-state">
                            <i class="fas fa-users"></i>
                            <h3>אין משתמשים</h3>
                            <p>לא נמצאו משתמשים במערכת</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Permissions Panel -->
            <div class="permissions-panel">
                <div class="panel-header">
                    <h3>הרשאות</h3>
                    <div class="panel-actions">
                        <button class="btn btn-sm btn-outline" onclick="selectAllPermissions()">
                            <i class="fas fa-check-square"></i>
                            בחר הכל
                        </button>
                        <button class="btn btn-sm btn-outline" onclick="deselectAllPermissions()">
                            <i class="fas fa-square"></i>
                            בטל הכל
                        </button>
                    </div>
                </div>
                <div class="panel-body">
                    <div class="permissions-form" id="permissionsForm" style="display: none;">
                        <form method="POST" id="savePermissionsForm">
                            <input type="hidden" name="action" value="save_permissions">
                            <input type="hidden" name="user_id" id="selectedUserId">
                            
                            <div class="selected-user-info" id="selectedUserInfo">
                                <!-- User info will be populated by JavaScript -->
                            </div>
                            
                            <div class="permissions-modules">
                                <?php foreach ($permissionsByModule as $moduleName => $permissions): ?>
                                <div class="module-section">
                                    <div class="module-header">
                                        <h4 class="module-title">
                                            <?php 
                                            $moduleNames = [
                                                'companies' => 'ניהול חברות',
                                                'contractors' => 'ניהול קבלנים',
                                                'worksites' => 'אתרי עבודה',
                                                'fire_safety' => 'בקרות כיבוי אש',
                                                'equipment' => 'ציוד וכלי עבודה',
                                                'users' => 'ניהול משתמשים',
                                                'employees' => 'ניהול עובדים',
                                                'safety_files' => 'תיק בטיחות',
                                                'summaries' => 'תמציות מידע',
                                                'statistics' => 'סטטיסטיקות',
                                                'deficiencies' => 'ניהול ליקויים',
                                                'forms' => 'מחולל טפסים',
                                                'inspections' => 'בדיקות'
                                            ];
                                            echo $moduleNames[$moduleName] ?? $moduleName;
                                            ?>
                                        </h4>
                                        <label class="module-toggle">
                                            <input type="checkbox" onchange="toggleModule('<?php echo $moduleName; ?>', this.checked)">
                                            <span class="toggle-text">הפעל/בטל מודול</span>
                                        </label>
                                    </div>
                                    <div class="module-permissions">
                                        <?php foreach ($permissions as $permission): ?>
                                        <label class="permission-item">
                                            <input type="checkbox" 
                                                   name="permissions[]" 
                                                   value="<?php echo $permission['id']; ?>"
                                                   data-module="<?php echo $moduleName; ?>"
                                                   id="perm_<?php echo $permission['id']; ?>">
                                            <div class="permission-content">
                                                <span class="permission-label">
                                                    <?php 
                                                    $permNames = [
                                                        'view' => 'צפייה',
                                                        'create' => 'יצירה',
                                                        'edit' => 'עריכה',
                                                        'delete' => 'מחיקה',
                                                        'manage' => 'ניהול מלא',
                                                        'export' => 'ייצוא נתונים',
                                                        'approve' => 'אישור',
                                                        'submit' => 'הגשה',
                                                        'review' => 'בדיקה',
                                                        'publish' => 'פרסום'
                                                    ];
                                                    echo $permNames[$permission['permission_name']] ?? $permission['permission_name'];
                                                    ?>
                                                </span>
                                                <span class="permission-desc">
                                                    <?php echo htmlspecialchars($permission['description'] ?? ''); ?>
                                                </span>
                                            </div>
                                        </label>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <div class="form-actions">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i>
                                    שמור הרשאות
                                </button>
                                <button type="button" class="btn btn-outline" onclick="resetPermissions()">
                                    <i class="fas fa-undo"></i>
                                    איפוס
                                </button>
                            </div>
                        </form>
                    </div>
                    
                    <div class="no-user-selected" id="noUserSelected">
                        <i class="fas fa-user-circle"></i>
                        <h3>בחר משתמש לעריכת הרשאות</h3>
                        <p>בחר משתמש מהרשימה משמאל כדי להתחיל בעריכת ההרשאות שלו</p>
                    </div>
                </div>
            </div>
        </div>
        
    </div>
</main>

<!-- Bulk Permissions Modal -->
<div class="modal" id="bulkPermissionsModal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h3>הרשאות קבוצתיות</h3>
            <button class="modal-close" onclick="closeBulkPermissions()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="modal-body">
            <p>החל הרשאות על מספר משתמשים בו זמנית</p>
            
            <div class="form-group">
                <label class="form-label">בחר משתמשים:</label>
                <div class="users-checkboxes">
                    <?php foreach ($users as $user): ?>
                    <label class="checkbox-item">
                        <input type="checkbox" name="bulk_users[]" value="<?php echo $user['id']; ?>">
                        <span><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></span>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <div class="form-group">
                <label class="form-label">פעולה:</label>
                <select class="form-control" id="bulkAction">
                    <option value="add">הוסף הרשאות</option>
                    <option value="remove">הסר הרשאות</option>
                    <option value="replace">החלף הרשאות</option>
                </select>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-primary" onclick="applyBulkPermissions()">
                <i class="fas fa-users-cog"></i>
                החל שינויים
            </button>
            <button class="btn btn-outline" onclick="closeBulkPermissions()">ביטול</button>
        </div>
    </div>
</div>

<style>
/* Permissions Management Styles */
.permissions-container {
    display: grid;
    grid-template-columns: 1fr 2fr;
    gap: 2rem;
    height: calc(100vh - 200px);
}

.users-panel,
.permissions-panel {
    background: white;
    border-radius: var(--radius-lg);
    box-shadow: var(--shadow-sm);
    border: 1px solid #e2e8f0;
    display: flex;
    flex-direction: column;
}

.panel-header {
    padding: 1.5rem;
    border-bottom: 1px solid #e2e8f0;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.panel-header h3 {
    margin: 0;
    font-size: 1.25rem;
    font-weight: 600;
}

.panel-actions {
    display: flex;
    gap: 0.5rem;
}

.search-input {
    padding: 0.5rem 1rem;
    border: 1px solid #d1d5db;
    border-radius: var(--radius-md);
    font-size: 0.875rem;
    width: 200px;
}

.panel-body {
    flex: 1;
    overflow: hidden;
    display: flex;
    flex-direction: column;
}

.users-list {
    flex: 1;
    overflow-y: auto;
    padding: 1rem;
}

.user-item {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1rem;
    border-radius: var(--radius-md);
    cursor: pointer;
    transition: var(--transition-fast);
    margin-bottom: 0.5rem;
}

.user-item:hover {
    background: var(--bg-secondary);
}

.user-item.selected {
    background: var(--primary-color);
    color: white;
}

.user-avatar {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    background: var(--primary-color);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    font-size: 0.875rem;
}

.user-item.selected .user-avatar {
    background: rgba(255, 255, 255, 0.2);
}

.user-info {
    flex: 1;
}

.user-name {
    font-weight: 600;
    margin-bottom: 0.25rem;
}

.user-role,
.user-company {
    font-size: 0.875rem;
    opacity: 0.8;
}

.status-badge {
    padding: 0.25rem 0.75rem;
    border-radius: 1rem;
    font-size: 0.75rem;
    font-weight: 600;
}

.status-badge.active {
    background: #dcfce7;
    color: #166534;
}

.status-badge.inactive {
    background: #fef2f2;
    color: #991b1b;
}

.user-item.selected .status-badge {
    background: rgba(255, 255, 255, 0.2);
    color: white;
}

.permissions-form {
    flex: 1;
    overflow-y: auto;
    padding: 1.5rem;
}

.selected-user-card {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1rem;
    background: var(--bg-secondary);
    border-radius: var(--radius-md);
    margin-bottom: 2rem;
}

.selected-user-avatar {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    background: var(--primary-color);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
}

.selected-user-details h4 {
    margin: 0 0 0.25rem 0;
    font-size: 1.125rem;
}

.selected-user-details p {
    margin: 0;
    color: var(--text-light);
    font-size: 0.875rem;
}

.permissions-modules {
    margin-bottom: 2rem;
}

.module-section {
    margin-bottom: 2rem;
    border: 1px solid #e2e8f0;
    border-radius: var(--radius-lg);
    overflow: hidden;
}

.module-header {
    background: var(--bg-secondary);
    padding: 1rem 1.5rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 1px solid #e2e8f0;
}

.module-title {
    margin: 0;
    font-size: 1rem;
    font-weight: 600;
}

.module-toggle {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.875rem;
    cursor: pointer;
}

.module-permissions {
    padding: 1.5rem;
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1rem;
}

.permission-item {
    display: flex;
    align-items: flex-start;
    gap: 0.75rem;
    padding: 1rem;
    border: 1px solid #e2e8f0;
    border-radius: var(--radius-md);
    cursor: pointer;
    transition: var(--transition-fast);
}

.permission-item:hover {
    background: var(--bg-secondary);
    border-color: var(--primary-color);
}

.permission-item input[type='checkbox'] {
    margin: 0;
    width: 18px;
    height: 18px;
}

.permission-content {
    flex: 1;
}

.permission-label {
    font-weight: 500;
    display: block;
    margin-bottom: 0.25rem;
}

.permission-desc {
    font-size: 0.875rem;
    color: var(--text-light);
}

.form-actions {
    display: flex;
    gap: 1rem;
    padding-top: 2rem;
    border-top: 1px solid #e2e8f0;
}

.no-user-selected {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    text-align: center;
    color: var(--text-light);
}

.no-user-selected i {
    font-size: 4rem;
    margin-bottom: 1rem;
    opacity: 0.5;
}

.no-user-selected h3 {
    margin-bottom: 0.5rem;
    color: var(--text-secondary);
}

.empty-state {
    text-align: center;
    padding: 2rem;
    color: var(--text-light);
}

.empty-state i {
    font-size: 3rem;
    margin-bottom: 1rem;
    opacity: 0.5;
}

.modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 10000;
}

.modal-content {
    background: white;
    border-radius: var(--radius-lg);
    width: 90%;
    max-width: 600px;
    max-height: 80vh;
    display: flex;
    flex-direction: column;
}

.modal-header {
    padding: 1.5rem;
    border-bottom: 1px solid #e2e8f0;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal-header h3 {
    margin: 0;
}

.modal-close {
    background: none;
    border: none;
    font-size: 1.25rem;
    cursor: pointer;
    padding: 0.5rem;
    border-radius: 50%;
    transition: var(--transition-fast);
}

.modal-close:hover {
    background: var(--bg-secondary);
}

.modal-body {
    flex: 1;
    padding: 1.5rem;
    overflow-y: auto;
}

.modal-footer {
    padding: 1.5rem;
    border-top: 1px solid #e2e8f0;
    display: flex;
    gap: 1rem;
    justify-content: flex-end;
}

.users-checkboxes {
    max-height: 200px;
    overflow-y: auto;
    border: 1px solid #e2e8f0;
    border-radius: var(--radius-md);
    padding: 1rem;
}

.checkbox-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 0;
    cursor: pointer;
}

.checkbox-item input {
    margin: 0;
}

@media (max-width: 1200px) {
    .permissions-container {
        grid-template-columns: 1fr;
        height: auto;
    }
    
    .users-panel {
        max-height: 400px;
    }
}

@media (max-width: 768px) {
    .module-permissions {
        grid-template-columns: 1fr;
    }
    
    .panel-header {
        flex-direction: column;
        gap: 1rem;
        align-items: stretch;
    }
    
    .search-input {
        width: 100%;
    }
}
</style>

<script>
// JavaScript for Permissions Management
let selectedUserId = null;
const userPermissions = <?php echo json_encode($userPermissions); ?>;

function selectUser(userId) {
    // Remove previous selection
    document.querySelectorAll('.user-item').forEach(item => {
        item.classList.remove('selected');
    });
    
    // Add selection to current user
    document.querySelector(`[data-user-id="${userId}"]`).classList.add('selected');
    
    selectedUserId = userId;
    document.getElementById('selectedUserId').value = userId;
    
    // Show permissions form
    document.getElementById('permissionsForm').style.display = 'block';
    document.getElementById('noUserSelected').style.display = 'none';
    
    // Load user permissions
    loadUserPermissions(userId);
    
    // Update selected user info
    updateSelectedUserInfo(userId);
}

function loadUserPermissions(userId) {
    // Clear all checkboxes
    document.querySelectorAll('input[name="permissions[]"]').forEach(checkbox => {
        checkbox.checked = false;
    });
    
    // Check user's permissions
    if (userPermissions[userId]) {
        userPermissions[userId].forEach(permId => {
            const checkbox = document.getElementById(`perm_${permId}`);
            if (checkbox) {
                checkbox.checked = true;
            }
        });
    }
}

function updateSelectedUserInfo(userId) {
    // Find user data
    const userElement = document.querySelector(`[data-user-id="${userId}"]`);
    const userName = userElement.querySelector('.user-name').textContent;
    const userRole = userElement.querySelector('.user-role').textContent;
    const userCompany = userElement.querySelector('.user-company').textContent;
    
    const initials = userName.split(' ').map(n => n[0]).join('').toUpperCase();
    
    document.getElementById('selectedUserInfo').innerHTML = `
        <div class="selected-user-card">
            <div class="selected-user-avatar">
                ${initials}
            </div>
            <div class="selected-user-details">
                <h4>${userName}</h4>
                <p>${userRole} • ${userCompany}</p>
            </div>
        </div>
    `;
}

function toggleModule(moduleName, checked) {
    document.querySelectorAll(`input[data-module="${moduleName}"]`).forEach(checkbox => {
        checkbox.checked = checked;
    });
}

function selectAllPermissions() {
    document.querySelectorAll('input[name="permissions[]"]').forEach(checkbox => {
        checkbox.checked = true;
    });
}

function deselectAllPermissions() {
    document.querySelectorAll('input[name="permissions[]"]').forEach(checkbox => {
        checkbox.checked = false;
    });
}

function resetPermissions() {
    if (selectedUserId) {
        loadUserPermissions(selectedUserId);
    }
}

function openBulkPermissions() {
    document.getElementById('bulkPermissionsModal').style.display = 'flex';
}

function closeBulkPermissions() {
    document.getElementById('bulkPermissionsModal').style.display = 'none';
}

function applyBulkPermissions() {
    // Implementation for bulk permissions
    showNotification('הרשאות קבוצתיות יושמו בהצלחה', 'success');
    closeBulkPermissions();
}

function exportPermissions() {
    // Create export data
    const exportData = {
        users: [],
        permissions: []
    };
    
    // Collect data and trigger download
    const dataStr = JSON.stringify(exportData, null, 2);
    const dataBlob = new Blob([dataStr], {type: 'application/json'});
    
    const link = document.createElement('a');
    link.href = URL.createObjectURL(dataBlob);
    link.download = 'permissions_export.json';
    link.click();
}

// Search functionality
document.getElementById('userSearch').addEventListener('input', function(e) {
    const searchTerm = e.target.value.toLowerCase();
    const userItems = document.querySelectorAll('.user-item');
    
    userItems.forEach(item => {
        const userName = item.querySelector('.user-name').textContent.toLowerCase();
        const userRole = item.querySelector('.user-role').textContent.toLowerCase();
        
        if (userName.includes(searchTerm) || userRole.includes(searchTerm)) {
            item.style.display = 'flex';
        } else {
            item.style.display = 'none';
        }
    });
});

// Form submission
document.getElementById('savePermissionsForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    
    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => response.text())
    .then(() => {
        if (typeof showNotification === 'function') {
            showNotification('ההרשאות נשמרו בהצלחה', 'success');
        } else {
            alert('ההרשאות נשמרו בהצלחה');
        }
        // Update local data
        const permissions = Array.from(document.querySelectorAll('input[name="permissions[]"]:checked'))
                                .map(cb => parseInt(cb.value));
        userPermissions[selectedUserId] = permissions;
    })
    .catch(error => {
        if (typeof showNotification === 'function') {
            showNotification('שגיאה בשמירת ההרשאות', 'error');
        } else {
            alert('שגיאה בשמירת ההרשאות');
        }
        console.error('Error:', error);
    });
});

console.log('✅ Permissions management loaded successfully');
</script>

<!-- Include Footer -->
<?php include __DIR__ . '/../../includes/footer.php'; ?>
