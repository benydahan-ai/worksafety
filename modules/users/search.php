<?php
/**
 * WorkSafety.io - חיפוש מתקדם במשתמשים
 * ממשק חיפוש מפורט עם סינונים ואפשרויות תצוגה מתקדמות
 */

// הגדרות דף
$pageTitle = 'חיפוש מתקדם במשתמשים';
$pageDescription = 'חיפוש מפורט וסינון משתמשים עם אפשרויות תצוגה מתקדמות';
$additionalCSS = ['modules/users/assets/search.css'];
$additionalJS = ['modules/users/assets/search.js'];

// כלילת קבצים נדרשים
require_once '../../includes/header.php';
require_once 'user_functions.php';

// וידוא התחברות והרשאות
if (!isset($_SESSION['user_id']) || !hasPermission('users', 'view')) {
    header('Location: /login.php');
    exit;
}

try {
    $db = getDB();
    
    // קבלת אפשרויות לסינונים
    $companies = [];
    if ($currentUser['role'] === 'super_admin') {
        $companies = $db->fetchAll("SELECT id, name FROM companies WHERE status = 'active' ORDER BY name");
    } else {
        $companies = $db->fetchAll("SELECT id, name FROM companies WHERE id = ? AND status = 'active'", [$currentUser['company_id']]);
    }
    
    // קבלת תפקידים זמינים
    $roles = [
        'super_admin' => 'מנהל ראשי',
        'company_admin' => 'מנהל חברה',
        'contractor' => 'קבלן',
        'safety_manager' => 'מנהל בטיחות',
        'inspector' => 'מפקח',
        'worker' => 'עובד'
    ];
    
    // קבלת מחלקות
    $departments = $db->fetchAll("
        SELECT DISTINCT department 
        FROM users 
        WHERE department IS NOT NULL AND department != '' 
        ORDER BY department
    ");
    
    // פרמטרי חיפוש
    $searchParams = [
        'search' => $_GET['search'] ?? '',
        'role' => $_GET['role'] ?? '',
        'status' => $_GET['status'] ?? '',
        'company' => $_GET['company'] ?? '',
        'department' => $_GET['department'] ?? '',
        'date_from' => $_GET['date_from'] ?? '',
        'date_to' => $_GET['date_to'] ?? '',
        'last_login' => $_GET['last_login'] ?? '',
        'sort' => $_GET['sort'] ?? 'name',
        'order' => $_GET['order'] ?? 'asc',
        'view' => $_GET['view'] ?? 'grid'
    ];
    
} catch (Exception $e) {
    error_log("Search page error: " . $e->getMessage());
    $companies = [];
    $departments = [];
    $searchParams = [];
}
?>

<!-- Include Sidebar -->
<?php include '../../includes/sidebar.php'; ?>

<!-- Main Content -->
<main class="main-content">
    <!-- Header -->
    <header class="main-header">
        <div class="header-content">
            <div class="header-left">
                <h1 class="header-title">
                    <i class="fas fa-search"></i>
                    חיפוש מתקדם במשתמשים
                </h1>
                <p class="header-subtitle">
                    חיפוש מפורט וסינון משתמשים עם אפשרויות תצוגה מתקדמות
                </p>
            </div>
            <div class="header-actions">
                <button class="btn btn-outline btn-sm" onclick="clearSearch()">
                    <i class="fas fa-times"></i>
                    נקה חיפוש
                </button>
                <button class="btn btn-primary btn-sm" onclick="exportResults()">
                    <i class="fas fa-download"></i>
                    ייצא תוצאות
                </button>
            </div>
        </div>
    </header>

    <!-- Page Content -->
    <div class="page-content">
        
        <!-- Search Form -->
        <div class="card search-form-card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-filter"></i>
                    סינון וחיפוש
                </h3>
                <button class="btn btn-sm btn-outline toggle-search" onclick="toggleSearchForm()">
                    <i class="fas fa-chevron-up"></i>
                    הסתר
                </button>
            </div>
            <div class="card-body search-form-body">
                <form id="searchForm" class="search-form">
                    
                    <!-- שורה ראשונה - חיפוש כללי -->
                    <div class="form-row">
                        <div class="form-group col-md-8">
                            <label for="search" class="form-label">חיפוש כללי</label>
                            <div class="input-group">
                                <input type="text" 
                                       id="search" 
                                       name="search" 
                                       class="form-control" 
                                       placeholder="חפש לפי שם, אימייל, טלפון..."
                                       value="<?php echo htmlspecialchars($searchParams['search']); ?>">
                                <div class="input-group-append">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-search"></i>
                                        חפש
                                    </button>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group col-md-4">
                            <label for="view" class="form-label">תצוגה</label>
                            <select id="view" name="view" class="form-control" onchange="changeView(this.value)">
                                <option value="grid" <?php echo $searchParams['view'] === 'grid' ? 'selected' : ''; ?>>רשת כרטיסים</option>
                                <option value="table" <?php echo $searchParams['view'] === 'table' ? 'selected' : ''; ?>>טבלה</option>
                                <option value="list" <?php echo $searchParams['view'] === 'list' ? 'selected' : ''; ?>>רשימה</option>
                            </select>
                        </div>
                    </div>
                    
                    <!-- שורה שנייה - סינונים בסיסיים -->
                    <div class="form-row">
                        <div class="form-group col-md-3">
                            <label for="role" class="form-label">תפקיד</label>
                            <select id="role" name="role" class="form-control">
                                <option value="">כל התפקידים</option>
                                <?php foreach ($roles as $roleKey => $roleName): ?>
                                    <option value="<?php echo $roleKey; ?>" <?php echo $searchParams['role'] === $roleKey ? 'selected' : ''; ?>>
                                        <?php echo $roleName; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group col-md-3">
                            <label for="status" class="form-label">סטטוס</label>
                            <select id="status" name="status" class="form-control">
                                <option value="">כל הסטטוסים</option>
                                <option value="active" <?php echo $searchParams['status'] === 'active' ? 'selected' : ''; ?>>פעיל</option>
                                <option value="inactive" <?php echo $searchParams['status'] === 'inactive' ? 'selected' : ''; ?>>לא פעיל</option>
                                <option value="pending" <?php echo $searchParams['status'] === 'pending' ? 'selected' : ''; ?>>ממתין לאישור</option>
                            </select>
                        </div>
                        
                        <?php if (count($companies) > 1): ?>
                        <div class="form-group col-md-3">
                            <label for="company" class="form-label">חברה</label>
                            <select id="company" name="company" class="form-control">
                                <option value="">כל החברות</option>
                                <?php foreach ($companies as $company): ?>
                                    <option value="<?php echo $company['id']; ?>" <?php echo $searchParams['company'] == $company['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($company['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php endif; ?>
                        
                        <div class="form-group col-md-3">
                            <label for="department" class="form-label">מחלקה</label>
                            <select id="department" name="department" class="form-control">
                                <option value="">כל המחלקות</option>
                                <?php foreach ($departments as $dept): ?>
                                    <option value="<?php echo htmlspecialchars($dept['department']); ?>" <?php echo $searchParams['department'] === $dept['department'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($dept['department']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <!-- שורה שלישית - סינוני תאריכים -->
                    <div class="form-row advanced-filters" style="display: none;">
                        <div class="form-group col-md-3">
                            <label for="date_from" class="form-label">תאריך יצירה מ-</label>
                            <input type="date" 
                                   id="date_from" 
                                   name="date_from" 
                                   class="form-control"
                                   value="<?php echo $searchParams['date_from']; ?>">
                        </div>
                        
                        <div class="form-group col-md-3">
                            <label for="date_to" class="form-label">תאריך יצירה עד</label>
                            <input type="date" 
                                   id="date_to" 
                                   name="date_to" 
                                   class="form-control"
                                   value="<?php echo $searchParams['date_to']; ?>">
                        </div>
                        
                        <div class="form-group col-md-3">
                            <label for="last_login" class="form-label">התחברות אחרונה</label>
                            <select id="last_login" name="last_login" class="form-control">
                                <option value="">הכל</option>
                                <option value="today" <?php echo $searchParams['last_login'] === 'today' ? 'selected' : ''; ?>>היום</option>
                                <option value="week" <?php echo $searchParams['last_login'] === 'week' ? 'selected' : ''; ?>>השבוע</option>
                                <option value="month" <?php echo $searchParams['last_login'] === 'month' ? 'selected' : ''; ?>>החודש</option>
                                <option value="never" <?php echo $searchParams['last_login'] === 'never' ? 'selected' : ''; ?>>מעולם לא</option>
                            </select>
                        </div>
                        
                        <div class="form-group col-md-3">
                            <label for="sort" class="form-label">מיון לפי</label>
                            <div class="input-group">
                                <select id="sort" name="sort" class="form-control">
                                    <option value="name" <?php echo $searchParams['sort'] === 'name' ? 'selected' : ''; ?>>שם</option>
                                    <option value="email" <?php echo $searchParams['sort'] === 'email' ? 'selected' : ''; ?>>אימייל</option>
                                    <option value="role" <?php echo $searchParams['sort'] === 'role' ? 'selected' : ''; ?>>תפקיד</option>
                                    <option value="created" <?php echo $searchParams['sort'] === 'created' ? 'selected' : ''; ?>>תאריך יצירה</option>
                                    <option value="login" <?php echo $searchParams['sort'] === 'login' ? 'selected' : ''; ?>>התחברות אחרונה</option>
                                </select>
                                <div class="input-group-append">
                                    <button type="button" class="btn btn-outline" onclick="toggleSortOrder()">
                                        <i class="fas <?php echo $searchParams['order'] === 'asc' ? 'fa-sort-up' : 'fa-sort-down'; ?>" id="sortIcon"></i>
                                    </button>
                                </div>
                            </div>
                            <input type="hidden" id="order" name="order" value="<?php echo $searchParams['order']; ?>">
                        </div>
                    </div>
                    
                    <!-- כפתורי פעולה -->
                    <div class="form-actions">
                        <button type="button" class="btn btn-outline" onclick="toggleAdvancedFilters()">
                            <i class="fas fa-sliders-h"></i>
                            סינונים מתקדמים
                        </button>
                        
                        <button type="button" class="btn btn-outline" onclick="saveSearchPreset()">
                            <i class="fas fa-save"></i>
                            שמור חיפוש
                        </button>
                        
                        <button type="button" class="btn btn-outline" onclick="loadSearchPreset()">
                            <i class="fas fa-folder-open"></i>
                            טען חיפוש שמור
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Search Results -->
        <div class="search-results">
            <div class="results-header">
                <div class="results-info">
                    <span id="resultsCount">טוען תוצאות...</span>
                </div>
                <div class="results-actions">
                    <button class="btn btn-sm btn-outline" onclick="selectAll()">
                        <i class="fas fa-check-square"></i>
                        בחר הכל
                    </button>
                    <div class="dropdown bulk-actions-dropdown">
                        <button class="btn btn-sm btn-outline dropdown-toggle" disabled>
                            <i class="fas fa-cogs"></i>
                            פעולות קבוצתיות
                        </button>
                        <div class="dropdown-menu">
                            <a class="dropdown-item" href="#" onclick="bulkAction('activate')">הפעל נבחרים</a>
                            <a class="dropdown-item" href="#" onclick="bulkAction('deactivate')">בטל נבחרים</a>
                            <div class="dropdown-divider"></div>
                            <a class="dropdown-item text-danger" href="#" onclick="bulkAction('delete')">מחק נבחרים</a>
                        </div>
                    </div>
                </div>
            </div>
            
            <div id="searchResultsContainer" class="results-container">
                <!-- התוצאות יטוענו כאן באמצעות AJAX -->
                <div class="text-center p-4">
                    <i class="fas fa-search fa-3x text-muted mb-3"></i>
                    <p class="text-muted">הכנס קריטריוני חיפוש ולחץ על "חפש"</p>
                </div>
            </div>
            
            <!-- Pagination -->
            <div id="paginationContainer" class="pagination-container" style="display: none;">
                <!-- עמוד pagination יבנה באמצעות JavaScript -->
            </div>
        </div>
        
        <!-- Saved Searches -->
        <div class="card saved-searches-card" style="display: none;">
            <div class="card-header">
                <h3 class="card-title">חיפושים שמורים</h3>
            </div>
            <div class="card-body">
                <div id="savedSearchesList" class="saved-searches-list">
                    <!-- רשימת חיפושים שמורים -->
                </div>
            </div>
        </div>
        
    </div>
</main>

<!-- Quick View Modal -->
<div class="modal" id="quickViewModal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h3>תצוגה מהירה</h3>
            <button class="modal-close" onclick="closeQuickView()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="modal-body" id="quickViewContent">
            <!-- תוכן המשתמש יטען כאן -->
        </div>
        <div class="modal-footer">
            <button class="btn btn-primary" id="editUserBtn">
                <i class="fas fa-edit"></i>
                ערוך משתמש
            </button>
            <button class="btn btn-outline" onclick="closeQuickView()">סגור</button>
        </div>
    </div>
</div>

<script>
// JavaScript for Advanced Search
let currentSearchResults = [];
let selectedUsers = [];
let currentPage = 1;
let searchParams = <?php echo json_encode($searchParams); ?>;

document.addEventListener('DOMContentLoaded', function() {
    initializeSearch();
    
    // אם יש פרמטרי חיפוש מהכתובת - חפש מיד
    if (hasSearchParams()) {
        performSearch();
    }
});

function initializeSearch() {
    // הגדרת טופס החיפוש
    const searchForm = document.getElementById('searchForm');
    searchForm.addEventListener('submit', function(e) {
        e.preventDefault();
        performSearch();
    });
    
    // חיפוש בזמן אמת לשדה החיפוש הראשי
    const searchInput = document.getElementById('search');
    let searchTimeout;
    searchInput.addEventListener('input', function() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            if (this.value.length >= 2 || this.value.length === 0) {
                performSearch();
            }
        }, 500);
    });
    
    // עדכון אוטומטי כשמשנים סינונים
    const filters = document.querySelectorAll('#searchForm select, #searchForm input[type="date"]');
    filters.forEach(filter => {
        filter.addEventListener('change', function() {
            currentPage = 1;
            performSearch();
        });
    });
}

function performSearch() {
    const formData = new FormData(document.getElementById('searchForm'));
    const params = new URLSearchParams();
    
    // הוספת פרמטרים מהטופס
    for (let [key, value] of formData.entries()) {
        if (value) {
            params.append(key, value);
        }
    }
    
    // הוספת עמוד נוכחי
    params.append('page', currentPage);
    params.append('limit', 20);
    
    // עדכון ההיסטוריה
    const newUrl = `${window.location.pathname}?${params.toString()}`;
    window.history.replaceState({}, '', newUrl);
    
    // שליחת בקשה לשרת
    showLoadingResults();
    
    fetch(`users_api.php?action=search_users&${params.toString()}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displayResults(data.users, data.pagination);
                currentSearchResults = data.users;
            } else {
                showError('שגיאה בחיפוש: ' + (data.error || 'שגיאה לא ידועה'));
            }
        })
        .catch(error => {
            console.error('Search error:', error);
            showError('שגיאה בתקשורת עם השרת');
        });
}

function displayResults(users, pagination) {
    const container = document.getElementById('searchResultsContainer');
    const view = document.getElementById('view').value;
    
    if (users.length === 0) {
        container.innerHTML = `
            <div class="text-center p-4">
                <i class="fas fa-search fa-3x text-muted mb-3"></i>
                <p class="text-muted">לא נמצאו תוצאות התואמות לחיפוש</p>
            </div>
        `;
        updateResultsCount(0, 0);
        hidePagination();
        return;
    }
    
    let html = '';
    
    if (view === 'grid') {
        html = '<div class="users-grid">';
        users.forEach(user => {
            html += createUserCard(user);
        });
        html += '</div>';
    } else if (view === 'table') {
        html = createUsersTable(users);
    } else {
        html = '<div class="users-list">';
        users.forEach(user => {
            html += createUserListItem(user);
        });
        html += '</div>';
    }
    
    container.innerHTML = html;
    updateResultsCount(users.length, pagination.total);
    updatePagination(pagination);
}

function createUserCard(user) {
    const statusClass = getStatusClass(user.status);
    const roleText = translateRole(user.role);
    const statusText = translateStatus(user.status);
    
    return `
        <div class="user-card" data-user-id="${user.id}">
            <div class="user-card-header">
                <div class="user-avatar">
                    ${getUserInitials(user.first_name, user.last_name)}
                </div>
                <div class="user-actions">
                    <input type="checkbox" class="user-select" value="${user.id}" onchange="updateSelection()">
                    <div class="dropdown">
                        <button class="btn btn-sm btn-outline dropdown-toggle">
                            <i class="fas fa-ellipsis-v"></i>
                        </button>
                        <div class="dropdown-menu">
                            <a class="dropdown-item" href="#" onclick="quickView(${user.id})">תצוגה מהירה</a>
                            <a class="dropdown-item" href="edit.php?id=${user.id}">ערוך</a>
                            <a class="dropdown-item" href="view.php?id=${user.id}">צפה בפרטים</a>
                            <div class="dropdown-divider"></div>
                            <a class="dropdown-item text-danger" href="#" onclick="deleteUser(${user.id})">מחק</a>
                        </div>
                    </div>
                </div>
            </div>
            <div class="user-card-body">
                <h4 class="user-name">${user.first_name} ${user.last_name}</h4>
                <p class="user-email">${user.email}</p>
                <div class="user-meta">
                    <span class="user-role">${roleText}</span>
                    <span class="user-company">${user.company_name || 'ללא חברה'}</span>
                </div>
                <div class="user-status">
                    <span class="status-badge ${statusClass}">${statusText}</span>
                </div>
            </div>
            <div class="user-card-footer">
                <small class="text-muted">
                    נוצר: ${formatDate(user.created_at)}
                    ${user.last_login ? `| התחברות: ${formatDate(user.last_login)}` : '| לא התחבר'}
                </small>
            </div>
        </div>
    `;
}

function createUsersTable(users) {
    let html = `
        <div class="table-container">
            <table class="table users-table">
                <thead>
                    <tr>
                        <th><input type="checkbox" onchange="selectAllVisible(this.checked)"></th>
                        <th>שם</th>
                        <th>אימייל</th>
                        <th>תפקיד</th>
                        <th>חברה</th>
                        <th>סטטוס</th>
                        <th>נוצר</th>
                        <th>התחברות אחרונה</th>
                        <th>פעולות</th>
                    </tr>
                </thead>
                <tbody>
    `;
    
    users.forEach(user => {
        const statusClass = getStatusClass(user.status);
        const roleText = translateRole(user.role);
        const statusText = translateStatus(user.status);
        
        html += `
            <tr data-user-id="${user.id}">
                <td><input type="checkbox" class="user-select" value="${user.id}" onchange="updateSelection()"></td>
                <td>
                    <div class="user-info">
                        <div class="user-avatar-sm">
                            ${getUserInitials(user.first_name, user.last_name)}
                        </div>
                        <div>
                            <div class="user-name">${user.first_name} ${user.last_name}</div>
                            <small class="text-muted">${user.department || ''}</small>
                        </div>
                    </div>
                </td>
                <td>${user.email}</td>
                <td>${roleText}</td>
                <td>${user.company_name || 'ללא חברה'}</td>
                <td><span class="status-badge ${statusClass}">${statusText}</span></td>
                <td>${formatDate(user.created_at)}</td>
                <td>${user.last_login ? formatDate(user.last_login) : 'לא התחבר'}</td>
                <td>
                    <div class="action-buttons">
                        <button class="btn btn-sm btn-outline" onclick="quickView(${user.id})" title="תצוגה מהירה">
                            <i class="fas fa-eye"></i>
                        </button>
                        <button class="btn btn-sm btn-outline" onclick="editUser(${user.id})" title="ערוך">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="btn btn-sm btn-outline text-danger" onclick="deleteUser(${user.id})" title="מחק">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </td>
            </tr>
        `;
    });
    
    html += `
                </tbody>
            </table>
        </div>
    `;
    
    return html;
}

function createUserListItem(user) {
    const statusClass = getStatusClass(user.status);
    const roleText = translateRole(user.role);
    const statusText = translateStatus(user.status);
    
    return `
        <div class="user-list-item" data-user-id="${user.id}">
            <div class="user-select-area">
                <input type="checkbox" class="user-select" value="${user.id}" onchange="updateSelection()">
            </div>
            <div class="user-avatar">
                ${getUserInitials(user.first_name, user.last_name)}
            </div>
            <div class="user-content">
                <div class="user-header">
                    <h4 class="user-name">${user.first_name} ${user.last_name}</h4>
                    <span class="status-badge ${statusClass}">${statusText}</span>
                </div>
                <div class="user-details">
                    <div class="user-email">${user.email}</div>
                    <div class="user-meta">
                        <span>${roleText}</span>
                        <span>•</span>
                        <span>${user.company_name || 'ללא חברה'}</span>
                        ${user.department ? `<span>•</span><span>${user.department}</span>` : ''}
                    </div>
                    <div class="user-dates">
                        <small>נוצר: ${formatDate(user.created_at)}</small>
                        ${user.last_login ? `<small>התחברות: ${formatDate(user.last_login)}</small>` : '<small>לא התחבר</small>'}
                    </div>
                </div>
            </div>
            <div class="user-actions">
                <button class="btn btn-sm btn-outline" onclick="quickView(${user.id})">
                    <i class="fas fa-eye"></i>
                </button>
                <button class="btn btn-sm btn-outline" onclick="editUser(${user.id})">
                    <i class="fas fa-edit"></i>
                </button>
                <button class="btn btn-sm btn-outline text-danger" onclick="deleteUser(${user.id})">
                    <i class="fas fa-trash"></i>
                </button>
            </div>
        </div>
    `;
}

// פונקציות עזר
function getUserInitials(firstName, lastName) {
    return (firstName.charAt(0) + lastName.charAt(0)).toUpperCase();
}

function getStatusClass(status) {
    const classes = {
        'active': 'status-active',
        'inactive': 'status-inactive',
        'pending': 'status-pending',
        'deleted': 'status-deleted'
    };
    return classes[status] || 'status-default';
}

function translateRole(role) {
    const roles = {
        'super_admin': 'מנהל ראשי',
        'company_admin': 'מנהל חברה',
        'contractor': 'קבלן',
        'safety_manager': 'מנהל בטיחות',
        'inspector': 'מפקח',
        'worker': 'עובד'
    };
    return roles[role] || role;
}

function translateStatus(status) {
    const statuses = {
        'active': 'פעיל',
        'inactive': 'לא פעיל',
        'pending': 'ממתין לאישור',
        'deleted': 'מחוק'
    };
    return statuses[status] || status;
}

function formatDate(dateString) {
    return new Date(dateString).toLocaleDateString('he-IL');
}

function hasSearchParams() {
    const urlParams = new URLSearchParams(window.location.search);
    return urlParams.toString() !== '';
}

function showLoadingResults() {
    document.getElementById('searchResultsContainer').innerHTML = `
        <div class="text-center p-4">
            <div class="spinner"></div>
            <p>מחפש...</p>
        </div>
    `;
}

function showError(message) {
    document.getElementById('searchResultsContainer').innerHTML = `
        <div class="text-center p-4">
            <i class="fas fa-exclamation-triangle fa-3x text-danger mb-3"></i>
            <p class="text-danger">${message}</p>
        </div>
    `;
}

function updateResultsCount(showing, total) {
    document.getElementById('resultsCount').textContent = 
        `מציג ${showing} מתוך ${total} תוצאות`;
}

function updatePagination(pagination) {
    const container = document.getElementById('paginationContainer');
    
    if (pagination.pages <= 1) {
        container.style.display = 'none';
        return;
    }
    
    container.style.display = 'block';
    
    let html = '<nav class="pagination-nav"><ul class="pagination">';
    
    // כפתור הקודם
    if (pagination.page > 1) {
        html += `<li><a href="#" onclick="goToPage(${pagination.page - 1})">הקודם</a></li>`;
    }
    
    // מספרי עמודים
    const startPage = Math.max(1, pagination.page - 2);
    const endPage = Math.min(pagination.pages, pagination.page + 2);
    
    for (let i = startPage; i <= endPage; i++) {
        html += `<li class="${i === pagination.page ? 'active' : ''}">
                    <a href="#" onclick="goToPage(${i})">${i}</a>
                 </li>`;
    }
    
    // כפתור הבא
    if (pagination.page < pagination.pages) {
        html += `<li><a href="#" onclick="goToPage(${pagination.page + 1})">הבא</a></li>`;
    }
    
    html += '</ul></nav>';
    container.innerHTML = html;
}

function hidePagination() {
    document.getElementById('paginationContainer').style.display = 'none';
}

function goToPage(page) {
    currentPage = page;
    performSearch();
}

// פונקציות ממשק
function toggleSearchForm() {
    const body = document.querySelector('.search-form-body');
    const button = document.querySelector('.toggle-search');
    const icon = button.querySelector('i');
    
    if (body.style.display === 'none') {
        body.style.display = 'block';
        icon.className = 'fas fa-chevron-up';
        button.innerHTML = '<i class="fas fa-chevron-up"></i> הסתר';
    } else {
        body.style.display = 'none';
        icon.className = 'fas fa-chevron-down';
        button.innerHTML = '<i class="fas fa-chevron-down"></i> הצג';
    }
}

function toggleAdvancedFilters() {
    const filters = document.querySelector('.advanced-filters');
    filters.style.display = filters.style.display === 'none' ? 'flex' : 'none';
}

function toggleSortOrder() {
    const orderInput = document.getElementById('order');
    const icon = document.getElementById('sortIcon');
    
    if (orderInput.value === 'asc') {
        orderInput.value = 'desc';
        icon.className = 'fas fa-sort-down';
    } else {
        orderInput.value = 'asc';
        icon.className = 'fas fa-sort-up';
    }
    
    performSearch();
}

function clearSearch() {
    document.getElementById('searchForm').reset();
    currentPage = 1;
    window.history.replaceState({}, '', window.location.pathname);
    performSearch();
}

function changeView(view) {
    currentPage = 1;
    performSearch();
}

// פונקציות פעולות על משתמשים
function quickView(userId) {
    fetch(`users_api.php?action=get_user&user_id=${userId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showUserQuickView(data.user);
            } else {
                showNotification('שגיאה בטעינת פרטי המשתמש', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification('שגיאה בתקשורת עם השרת', 'error');
        });
}

function showUserQuickView(user) {
    const modal = document.getElementById('quickViewModal');
    const content = document.getElementById('quickViewContent');
    
    content.innerHTML = `
        <div class="user-quick-view">
            <div class="user-header">
                <div class="user-avatar-lg">
                    ${getUserInitials(user.first_name, user.last_name)}
                </div>
                <div class="user-basic-info">
                    <h3>${user.first_name} ${user.last_name}</h3>
                    <p class="user-email">${user.email}</p>
                    <span class="status-badge ${getStatusClass(user.status)}">${translateStatus(user.status)}</span>
                </div>
            </div>
            <div class="user-details">
                <div class="detail-row">
                    <strong>תפקיד:</strong>
                    <span>${translateRole(user.role)}</span>
                </div>
                <div class="detail-row">
                    <strong>חברה:</strong>
                    <span>${user.company_name || 'ללא חברה'}</span>
                </div>
                <div class="detail-row">
                    <strong>מחלקה:</strong>
                    <span>${user.department || 'לא צוין'}</span>
                </div>
                <div class="detail-row">
                    <strong>תואר:</strong>
                    <span>${user.job_title || 'לא צוין'}</span>
                </div>
                <div class="detail-row">
                    <strong>טלפון:</strong>
                    <span>${user.phone || 'לא צוין'}</span>
                </div>
                <div class="detail-row">
                    <strong>תאריך יצירה:</strong>
                    <span>${formatDate(user.created_at)}</span>
                </div>
                <div class="detail-row">
                    <strong>התחברות אחרונה:</strong>
                    <span>${user.last_login ? formatDate(user.last_login) : 'לא התחבר'}</span>
                </div>
            </div>
        </div>
    `;
    
    document.getElementById('editUserBtn').onclick = () => editUser(user.id);
    modal.style.display = 'flex';
}

function closeQuickView() {
    document.getElementById('quickViewModal').style.display = 'none';
}

function editUser(userId) {
    window.location.href = `edit.php?id=${userId}`;
}

function deleteUser(userId) {
    if (!confirm('האם אתה בטוח שברצונך למחוק את המשתמש?')) {
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'delete_user');
    formData.append('user_id', userId);
    
    fetch('users_api.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification(data.message, 'success');
            performSearch(); // רענון התוצאות
        } else {
            showNotification(data.error, 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('שגיאה במחיקת המשתמש', 'error');
    });
}

// פונקציות בחירה ופעולות קבוצתיות
function updateSelection() {
    selectedUsers = Array.from(document.querySelectorAll('.user-select:checked')).map(cb => cb.value);
    
    const bulkButton = document.querySelector('.bulk-actions-dropdown button');
    bulkButton.disabled = selectedUsers.length === 0;
    
    if (selectedUsers.length > 0) {
        bulkButton.innerHTML = `<i class="fas fa-cogs"></i> פעולות קבוצתיות (${selectedUsers.length})`;
    } else {
        bulkButton.innerHTML = '<i class="fas fa-cogs"></i> פעולות קבוצתיות';
    }
}

function selectAll() {
    const checkboxes = document.querySelectorAll('.user-select');
    const allChecked = Array.from(checkboxes).every(cb => cb.checked);
    
    checkboxes.forEach(cb => cb.checked = !allChecked);
    updateSelection();
}

function selectAllVisible(checked) {
    document.querySelectorAll('.user-select').forEach(cb => cb.checked = checked);
    updateSelection();
}

function bulkAction(action) {
    if (selectedUsers.length === 0) {
        showNotification('נא לבחור משתמשים לפעולה', 'warning');
        return;
    }
    
    let message = '';
    switch (action) {
        case 'activate':
            message = `האם להפעיל ${selectedUsers.length} משתמשים?`;
            break;
        case 'deactivate':
            message = `האם לבטל ${selectedUsers.length} משתמשים?`;
            break;
        case 'delete':
            message = `האם למחוק ${selectedUsers.length} משתמשים? פעולה זו לא ניתנת לביטול.`;
            break;
    }
    
    if (!confirm(message)) {
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'bulk_action');
    formData.append('bulk_action', action);
    selectedUsers.forEach(userId => {
        formData.append('user_ids[]', userId);
    });
    
    fetch('users_api.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification(data.message, 'success');
            selectedUsers = [];
            performSearch();
        } else {
            showNotification(data.error, 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('שגיאה בביצוע הפעולה', 'error');
    });
}

function exportResults() {
    const params = new URLSearchParams(new FormData(document.getElementById('searchForm')));
    params.append('action', 'export_users');
    params.append('format', 'csv');
    
    window.open(`users_api.php?${params.toString()}`);
}

// פונקציות חיפושים שמורים
function saveSearchPreset() {
    const name = prompt('הכנס שם לחיפוש השמור:');
    if (!name) return;
    
    const formData = new FormData(document.getElementById('searchForm'));
    const preset = {
        name: name,
        params: Object.fromEntries(formData.entries()),
        created: new Date().toISOString()
    };
    
    // שמירה ב-localStorage
    let savedSearches = JSON.parse(localStorage.getItem('worksafety_saved_searches') || '[]');
    savedSearches.push(preset);
    localStorage.setItem('worksafety_saved_searches', JSON.stringify(savedSearches));
    
    showNotification('החיפוש נשמר בהצלחה', 'success');
}

function loadSearchPreset() {
    const savedSearches = JSON.parse(localStorage.getItem('worksafety_saved_searches') || '[]');
    
    if (savedSearches.length === 0) {
        showNotification('אין חיפושים שמורים', 'info');
        return;
    }
    
    // הצגת רשימת חיפושים שמורים
    const searchesCard = document.querySelector('.saved-searches-card');
    const searchesList = document.getElementById('savedSearchesList');
    
    let html = '';
    savedSearches.forEach((search, index) => {
        html += `
            <div class="saved-search-item">
                <div class="search-info">
                    <h5>${search.name}</h5>
                    <small>נשמר: ${formatDate(search.created)}</small>
                </div>
                <div class="search-actions">
                    <button class="btn btn-sm btn-primary" onclick="applySavedSearch(${index})">
                        <i class="fas fa-search"></i>
                        החל
                    </button>
                    <button class="btn btn-sm btn-outline text-danger" onclick="deleteSavedSearch(${index})">
                        <i class="fas fa-trash"></i>
                        מחק
                    </button>
                </div>
            </div>
        `;
    });
    
    searchesList.innerHTML = html;
    searchesCard.style.display = 'block';
}

function applySavedSearch(index) {
    const savedSearches = JSON.parse(localStorage.getItem('worksafety_saved_searches') || '[]');
    const search = savedSearches[index];
    
    if (!search) return;
    
    // מילוי הטופס עם הפרמטרים השמורים
    const form = document.getElementById('searchForm');
    Object.keys(search.params).forEach(key => {
        const field = form.elements[key];
        if (field) {
            field.value = search.params[key];
        }
    });
    
    // ביצוע החיפוש
    currentPage = 1;
    performSearch();
    
    // הסתרת החיפושים השמורים
    document.querySelector('.saved-searches-card').style.display = 'none';
}

function deleteSavedSearch(index) {
    if (!confirm('האם למחוק את החיפוש השמור?')) return;
    
    let savedSearches = JSON.parse(localStorage.getItem('worksafety_saved_searches') || '[]');
    savedSearches.splice(index, 1);
    localStorage.setItem('worksafety_saved_searches', JSON.stringify(savedSearches));
    
    loadSearchPreset(); // רענון הרשימה
    showNotification('החיפוש נמחק בהצלחה', 'success');
}

console.log('✅ Advanced search loaded');
</script>

<!-- Include Footer -->
<?php 
$additionalFooter = "
<style>
/* Advanced Search Styles */
.search-form-card .card-header {
    background: var(--bg-secondary);
    border-bottom: 1px solid #e2e8f0;
}

.search-form {
    margin: 0;
}

.form-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
    margin-bottom: 1rem;
}

.form-row .col-md-8 {
    grid-column: span 2;
}

.input-group {
    display: flex;
}

.input-group .form-control {
    border-top-left-radius: 0;
    border-bottom-left-radius: 0;
}

.input-group-append .btn {
    border-top-right-radius: 0;
    border-bottom-right-radius: 0;
    border-right: none;
}

.form-actions {
    margin-top: 1rem;
    padding-top: 1rem;
    border-top: 1px solid #e2e8f0;
    display: flex;
    gap: 1rem;
    flex-wrap: wrap;
}

.results-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
    padding: 1rem;
    background: var(--bg-secondary);
    border-radius: var(--radius-md);
}

.results-actions {
    display: flex;
    gap: 1rem;
    align-items: center;
}

.dropdown {
    position: relative;
    display: inline-block;
}

.dropdown-toggle::after {
    content: '';
    border: solid;
    border-width: 0 2px 2px 0;
    display: inline-block;
    padding: 2px;
    transform: rotate(45deg);
    margin-right: 5px;
}

.dropdown-menu {
    position: absolute;
    top: 100%;
    left: 0;
    z-index: 1000;
    display: none;
    min-width: 160px;
    background: white;
    border: 1px solid #e2e8f0;
    border-radius: var(--radius-md);
    box-shadow: var(--shadow-lg);
}

.dropdown:hover .dropdown-menu {
    display: block;
}

.dropdown-item {
    display: block;
    padding: 0.5rem 1rem;
    color: var(--text-primary);
    text-decoration: none;
    transition: var(--transition-fast);
}

.dropdown-item:hover {
    background: var(--bg-secondary);
    color: var(--text-primary);
}

.dropdown-divider {
    height: 0;
    margin: 0.5rem 0;
    overflow: hidden;
    border-top: 1px solid #e2e8f0;
}

/* User Cards Grid */
.users-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 1rem;
}

.user-card {
    background: white;
    border-radius: var(--radius-lg);
    box-shadow: var(--shadow-sm);
    border: 1px solid #e2e8f0;
    transition: var(--transition-fast);
    overflow: hidden;
}

.user-card:hover {
    box-shadow: var(--shadow-md);
    transform: translateY(-2px);
}

.user-card-header {
    padding: 1rem;
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    background: var(--bg-secondary);
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
    font-size: 1.1rem;
}

.user-avatar-sm {
    width: 35px;
    height: 35px;
    font-size: 0.9rem;
}

.user-avatar-lg {
    width: 80px;
    height: 80px;
    font-size: 1.5rem;
}

.user-actions {
    display: flex;
    gap: 0.5rem;
    align-items: center;
}

.user-card-body {
    padding: 1rem;
}

.user-name {
    margin: 0 0 0.5rem 0;
    font-size: 1.1rem;
    font-weight: 600;
}

.user-email {
    color: var(--text-light);
    margin-bottom: 1rem;
}

.user-meta {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
    margin-bottom: 1rem;
}

.user-role,
.user-company {
    font-size: 0.9rem;
    color: var(--text-secondary);
}

.user-status {
    margin-bottom: 1rem;
}

.status-badge {
    padding: 0.25rem 0.75rem;
    border-radius: 1rem;
    font-size: 0.75rem;
    font-weight: 600;
}

.status-active {
    background: #dcfce7;
    color: #166534;
}

.status-inactive {
    background: #fef2f2;
    color: #991b1b;
}

.status-pending {
    background: #fef3c7;
    color: #92400e;
}

.status-deleted {
    background: #f3f4f6;
    color: #374151;
}

.user-card-footer {
    padding: 1rem;
    background: var(--bg-secondary);
    border-top: 1px solid #e2e8f0;
}

/* Users Table */
.users-table {
    width: 100%;
}

.user-info {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.action-buttons {
    display: flex;
    gap: 0.25rem;
}

/* Users List */
.users-list {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.user-list-item {
    background: white;
    border-radius: var(--radius-lg);
    box-shadow: var(--shadow-sm);
    border: 1px solid #e2e8f0;
    padding: 1rem;
    display: flex;
    align-items: center;
    gap: 1rem;
    transition: var(--transition-fast);
}

.user-list-item:hover {
    box-shadow: var(--shadow-md);
}

.user-content {
    flex: 1;
}

.user-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 0.5rem;
}

.user-details {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.user-meta {
    display: flex;
    gap: 0.5rem;
    font-size: 0.9rem;
    color: var(--text-secondary);
}

.user-dates {
    display: flex;
    gap: 1rem;
    font-size: 0.8rem;
    color: var(--text-light);
}

/* Modal Styles */
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

.user-quick-view .user-header {
    display: flex;
    align-items: center;
    gap: 1rem;
    margin-bottom: 2rem;
    padding-bottom: 1rem;
    border-bottom: 1px solid #e2e8f0;
}

.user-quick-view .user-basic-info h3 {
    margin: 0 0 0.5rem 0;
}

.user-quick-view .user-basic-info .user-email {
    margin-bottom: 0.5rem;
}

.user-details {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.detail-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.5rem 0;
    border-bottom: 1px solid #f1f5f9;
}

.detail-row:last-child {
    border-bottom: none;
}

/* Pagination */
.pagination-container {
    margin-top: 2rem;
    display: flex;
    justify-content: center;
}

.pagination-nav {
    display: flex;
}

.pagination {
    display: flex;
    list-style: none;
    margin: 0;
    padding: 0;
    gap: 0.25rem;
}

.pagination li {
    display: flex;
}

.pagination a {
    padding: 0.5rem 0.75rem;
    border: 1px solid #e2e8f0;
    border-radius: var(--radius-md);
    text-decoration: none;
    color: var(--text-secondary);
    transition: var(--transition-fast);
}

.pagination a:hover {
    background: var(--bg-secondary);
    color: var(--text-primary);
}

.pagination .active a {
    background: var(--primary-color);
    color: white;
    border-color: var(--primary-color);
}

/* Saved Searches */
.saved-searches-list {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.saved-search-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1rem;
    border: 1px solid #e2e8f0;
    border-radius: var(--radius-md);
    background: var(--bg-secondary);
}

.search-info h5 {
    margin: 0 0 0.25rem 0;
}

.search-actions {
    display: flex;
    gap: 0.5rem;
}

/* Responsive */
@media (max-width: 768px) {
    .form-row {
        grid-template-columns: 1fr;
    }
    
    .form-row .col-md-8 {
        grid-column: span 1;
    }
    
    .results-header {
        flex-direction: column;
        gap: 1rem;
    }
    
    .users-grid {
        grid-template-columns: 1fr;
    }
    
    .user-list-item {
        flex-direction: column;
        align-items: stretch;
    }
    
    .user-header {
        flex-direction: column;
        align-items: stretch;
    }
    
    .form-actions {
        flex-direction: column;
    }
}
</style>
";

include '../../includes/footer.php'; 
?>
