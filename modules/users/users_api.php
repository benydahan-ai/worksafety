<?php
/**
 * WorkSafety.io - Users API
 * API לטיפול בפעולות AJAX של מודול המשתמשים
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

// הפעלת session
session_start();

// כלילת קבצים נדרשים
require_once '../../config/database.php';
require_once 'user_functions.php';
require_once 'validation.php';

// בדיקת הרשאות
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'לא מחובר למערכת']);
    exit;
}

// קבלת הפרמטרים
$action = $_GET['action'] ?? $_POST['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

try {
    $db = getDB();
    $currentUser = getCurrentUser();
    
    switch ($action) {
        
        case 'check_email':
            handleCheckEmail($db);
            break;
            
        case 'get_user':
            handleGetUser($db);
            break;
            
        case 'save_user':
            handleSaveUser($db, $currentUser);
            break;
            
        case 'delete_user':
            handleDeleteUser($db, $currentUser);
            break;
            
        case 'toggle_status':
            handleToggleStatus($db, $currentUser);
            break;
            
        case 'bulk_action':
            handleBulkAction($db, $currentUser);
            break;
            
        case 'export_users':
            handleExportUsers($db, $currentUser);
            break;
            
        case 'search_users':
            handleSearchUsers($db, $currentUser);
            break;
            
        case 'get_permissions':
            handleGetPermissions($db);
            break;
            
        case 'save_permissions':
            handleSavePermissions($db, $currentUser);
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['error' => 'פעולה לא זוהתה']);
            break;
    }
    
} catch (Exception $e) {
    error_log("Users API Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'שגיאה בשרת: ' . $e->getMessage()]);
}

/**
 * בדיקת זמינות כתובת אימייל
 */
function handleCheckEmail($db) {
    $email = $_GET['email'] ?? '';
    $userId = $_GET['user_id'] ?? 0;
    
    if (empty($email)) {
        echo json_encode(['valid' => false, 'message' => 'כתובת אימייל נדרשה']);
        return;
    }
    
    $whereClause = $userId > 0 ? "AND id != ?" : "";
    $params = $userId > 0 ? [$email, $userId] : [$email];
    
    $user = $db->fetch("SELECT id FROM users WHERE email = ? {$whereClause}", $params);
    
    if ($user) {
        echo json_encode(['valid' => false, 'message' => 'כתובת האימייל כבר קיימת במערכת']);
    } else {
        echo json_encode(['valid' => true, 'message' => 'כתובת האימייל זמינה']);
    }
}

/**
 * קבלת פרטי משתמש
 */
function handleGetUser($db) {
    $userId = $_GET['user_id'] ?? 0;
    
    if (!$userId) {
        echo json_encode(['error' => 'מזהה משתמש נדרש']);
        return;
    }
    
    $user = $db->fetch("
        SELECT u.*, c.name as company_name 
        FROM users u 
        LEFT JOIN companies c ON u.company_id = c.id 
        WHERE u.id = ?
    ", [$userId]);
    
    if (!$user) {
        echo json_encode(['error' => 'משתמש לא נמצא']);
        return;
    }
    
    // הסרת סיסמה מהמידע המוחזר
    unset($user['password']);
    
    echo json_encode(['success' => true, 'user' => $user]);
}

/**
 * שמירת משתמש (יצירה או עדכון)
 */
function handleSaveUser($db, $currentUser) {
    $data = json_decode(file_get_contents('php://input'), true);
    
    // אימות נתונים
    $validation = validateUserData($data);
    if (!$validation['valid']) {
        echo json_encode(['error' => $validation['message']]);
        return;
    }
    
    $userId = $data['id'] ?? 0;
    $isUpdate = $userId > 0;
    
    // הכנת נתוני המשתמש
    $userData = [
        'first_name' => $data['first_name'],
        'last_name' => $data['last_name'], 
        'email' => $data['email'],
        'phone' => $data['phone'] ?? '',
        'role' => $data['role'],
        'company_id' => $data['company_id'] ?? null,
        'department' => $data['department'] ?? '',
        'job_title' => $data['job_title'] ?? '',
        'status' => $data['status'] ?? 'active',
        'notes' => $data['notes'] ?? ''
    ];
    
    // אם יש סיסמה חדשה
    if (!empty($data['password'])) {
        $userData['password'] = password_hash($data['password'], PASSWORD_DEFAULT);
    }
    
    // עדכון זמני שינוי
    if ($isUpdate) {
        $userData['updated_at'] = getCurrentDateTime();
        $userData['updated_by'] = $currentUser['id'];
    } else {
        $userData['created_at'] = getCurrentDateTime();
        $userData['created_by'] = $currentUser['id'];
    }
    
    try {
        $db->getConnection()->beginTransaction();
        
        if ($isUpdate) {
            // עדכון משתמש קיים
            $db->update('users', $userData, 'id = ?', [$userId]);
            $message = 'המשתמש עודכן בהצלחה';
        } else {
            // יצירת משתמש חדש
            $userId = $db->insert('users', $userData);
            $message = 'המשתמש נוצר בהצלחה';
        }
        
        $db->getConnection()->commit();
        echo json_encode(['success' => true, 'message' => $message, 'user_id' => $userId]);
        
    } catch (Exception $e) {
        $db->getConnection()->rollBack();
        error_log("Save user error: " . $e->getMessage());
        echo json_encode(['error' => 'שגיאה בשמירת המשתמש']);
    }
}

/**
 * מחיקת משתמש
 */
function handleDeleteUser($db, $currentUser) {
    $userId = $_POST['user_id'] ?? 0;
    
    if (!$userId) {
        echo json_encode(['error' => 'מזהה משתמש נדרש']);
        return;
    }
    
    // בדיקה שלא מוחקים את עצמנו
    if ($userId == $currentUser['id']) {
        echo json_encode(['error' => 'לא ניתן למחוק את המשתמש הנוכחי']);
        return;
    }
    
    try {
        $db->getConnection()->beginTransaction();
        
        // העברה לסטטוס מחוק במקום מחיקה פיזית
        $db->update('users', [
            'status' => 'deleted',
            'email' => 'deleted_' . time() . '_' . $userId . '@deleted.local',
            'updated_at' => getCurrentDateTime(),
            'updated_by' => $currentUser['id']
        ], 'id = ?', [$userId]);
        
        $db->getConnection()->commit();
        echo json_encode(['success' => true, 'message' => 'המשתמש הוסר בהצלחה']);
        
    } catch (Exception $e) {
        $db->getConnection()->rollBack();
        error_log("Delete user error: " . $e->getMessage());
        echo json_encode(['error' => 'שגיאה במחיקת המשתמש']);
    }
}

/**
 * שינוי סטטוס משתמש
 */
function handleToggleStatus($db, $currentUser) {
    $userId = $_POST['user_id'] ?? 0;
    $newStatus = $_POST['status'] ?? '';
    
    if (!$userId || !$newStatus) {
        echo json_encode(['error' => 'פרמטרים חסרים']);
        return;
    }
    
    // בדיקה שלא משנים את הסטטוס שלנו
    if ($userId == $currentUser['id']) {
        echo json_encode(['error' => 'לא ניתן לשנות את הסטטוס של המשתמש הנוכחי']);
        return;
    }
    
    try {
        $db->update('users', [
            'status' => $newStatus,
            'updated_at' => getCurrentDateTime(),
            'updated_by' => $currentUser['id']
        ], 'id = ?', [$userId]);
        
        echo json_encode(['success' => true, 'message' => 'הסטטוס עודכן בהצלחה']);
        
    } catch (Exception $e) {
        error_log("Toggle status error: " . $e->getMessage());
        echo json_encode(['error' => 'שגיאה בעדכון הסטטוס']);
    }
}

/**
 * פעולות קבוצתיות
 */
function handleBulkAction($db, $currentUser) {
    $userIds = $_POST['user_ids'] ?? [];
    $action = $_POST['bulk_action'] ?? '';
    
    if (empty($userIds) || !$action) {
        echo json_encode(['error' => 'פרמטרים חסרים']);
        return;
    }
    
    // בדיקה שלא כוללים את המשתמש הנוכחי
    if (in_array($currentUser['id'], $userIds)) {
        echo json_encode(['error' => 'לא ניתן לבצע פעולות על המשתמש הנוכחי']);
        return;
    }
    
    try {
        $db->getConnection()->beginTransaction();
        
        $placeholders = str_repeat('?,', count($userIds) - 1) . '?';
        
        switch ($action) {
            case 'activate':
                $db->query("UPDATE users SET status = 'active', updated_at = ?, updated_by = ? WHERE id IN ({$placeholders})", 
                          array_merge([getCurrentDateTime(), $currentUser['id']], $userIds));
                $message = 'המשתמשים הופעלו בהצלחה';
                break;
                
            case 'deactivate':
                $db->query("UPDATE users SET status = 'inactive', updated_at = ?, updated_by = ? WHERE id IN ({$placeholders})", 
                          array_merge([getCurrentDateTime(), $currentUser['id']], $userIds));
                $message = 'המשתמשים בוטלו בהצלחה';
                break;
                
            case 'delete':
                $db->query("UPDATE users SET status = 'deleted', updated_at = ?, updated_by = ? WHERE id IN ({$placeholders})", 
                          array_merge([getCurrentDateTime(), $currentUser['id']], $userIds));
                $message = 'המשתמשים נמחקו בהצלחה';
                break;
                
            default:
                throw new Exception('פעולה לא זוהתה');
        }
        
        $db->getConnection()->commit();
        echo json_encode(['success' => true, 'message' => $message]);
        
    } catch (Exception $e) {
        $db->getConnection()->rollBack();
        error_log("Bulk action error: " . $e->getMessage());
        echo json_encode(['error' => 'שגיאה בביצוע הפעולה']);
    }
}

/**
 * ייצוא משתמשים
 */
function handleExportUsers($db, $currentUser) {
    $format = $_GET['format'] ?? 'excel';
    
    // קבלת נתוני המשתמשים
    $whereClause = $currentUser['role'] === 'super_admin' ? "u.status != 'deleted'" : "u.company_id = ? AND u.status != 'deleted'";
    $params = $currentUser['role'] === 'super_admin' ? [] : [$currentUser['company_id']];
    
    $users = $db->fetchAll("
        SELECT 
            u.id,
            u.first_name,
            u.last_name,
            u.email,
            u.phone,
            u.role,
            u.department,
            u.job_title,
            u.status,
            c.name as company_name,
            u.created_at,
            u.last_login
        FROM users u
        LEFT JOIN companies c ON u.company_id = c.id
        WHERE {$whereClause}
        ORDER BY u.first_name, u.last_name
    ", $params);
    
    // תרגום כותרות לעברית
    $headers = [
        'מזהה',
        'שם פרטי',
        'שם משפחה', 
        'אימייל',
        'טלפון',
        'תפקיד',
        'מחלקה',
        'תואר',
        'סטטוס',
        'חברה',
        'תאריך יצירה',
        'התחברות אחרונה'
    ];
    
    if ($format === 'csv') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="users_export_' . date('Y-m-d') . '.csv"');
        
        $output = fopen('php://output', 'w');
        
        // BOM לתמיכה בעברית
        fwrite($output, "\xEF\xBB\xBF");
        
        // כותרות
        fputcsv($output, $headers);
        
        // נתונים
        foreach ($users as $user) {
            $row = [
                $user['id'],
                $user['first_name'],
                $user['last_name'],
                $user['email'],
                $user['phone'],
                translateRole($user['role']),
                $user['department'],
                $user['job_title'],
                translateStatus($user['status']),
                $user['company_name'],
                formatHebrewDateTime($user['created_at']),
                $user['last_login'] ? formatHebrewDateTime($user['last_login']) : 'לא התחבר'
            ];
            fputcsv($output, $row);
        }
        
        fclose($output);
        
    } else {
        // ייצוא JSON
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="users_export_' . date('Y-m-d') . '.json"');
        
        echo json_encode([
            'export_date' => date('Y-m-d H:i:s'),
            'total_users' => count($users),
            'users' => $users
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }
}

/**
 * חיפוש משתמשים
 */
function handleSearchUsers($db, $currentUser) {
    $search = $_GET['search'] ?? '';
    $role = $_GET['role'] ?? '';
    $status = $_GET['status'] ?? '';
    $company = $_GET['company'] ?? '';
    $page = max(1, intval($_GET['page'] ?? 1));
    $limit = min(100, max(10, intval($_GET['limit'] ?? 20)));
    $offset = ($page - 1) * $limit;
    
    // בניית תנאי חיפוש
    $conditions = ["u.status != 'deleted'"];
    $params = [];
    
    // הגבלה לפי הרשאות
    if ($currentUser['role'] !== 'super_admin') {
        $conditions[] = "u.company_id = ?";
        $params[] = $currentUser['company_id'];
    }
    
    // חיפוש טקסט חופשי
    if (!empty($search)) {
        $conditions[] = "(u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)";
        $searchParam = "%{$search}%";
        $params = array_merge($params, [$searchParam, $searchParam, $searchParam, $searchParam]);
    }
    
    // סינון לפי תפקיד
    if (!empty($role)) {
        $conditions[] = "u.role = ?";
        $params[] = $role;
    }
    
    // סינון לפי סטטוס
    if (!empty($status)) {
        $conditions[] = "u.status = ?";
        $params[] = $status;
    }
    
    // סינון לפי חברה
    if (!empty($company)) {
        $conditions[] = "u.company_id = ?";
        $params[] = $company;
    }
    
    $whereClause = implode(' AND ', $conditions);
    
    // ספירת תוצאות
    $totalStmt = $db->query("
        SELECT COUNT(*) as total 
        FROM users u 
        LEFT JOIN companies c ON u.company_id = c.id 
        WHERE {$whereClause}
    ", $params);
    $total = $totalStmt->fetch()['total'];
    
    // קבלת נתונים
    $users = $db->fetchAll("
        SELECT 
            u.id,
            u.first_name,
            u.last_name,
            u.email,
            u.phone,
            u.role,
            u.department,
            u.job_title,
            u.status,
            c.name as company_name,
            u.created_at,
            u.last_login
        FROM users u
        LEFT JOIN companies c ON u.company_id = c.id
        WHERE {$whereClause}
        ORDER BY u.first_name, u.last_name
        LIMIT ? OFFSET ?
    ", array_merge($params, [$limit, $offset]));
    
    echo json_encode([
        'success' => true,
        'users' => $users,
        'pagination' => [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'pages' => ceil($total / $limit)
        ]
    ]);
}

/**
 * קבלת הרשאות זמינות
 */
function handleGetPermissions($db) {
    $permissions = $db->fetchAll("
        SELECT * FROM permissions 
        ORDER BY module_name, permission_name
    ");
    
    // ארגון לפי מודול
    $permissionsByModule = [];
    foreach ($permissions as $perm) {
        if (!isset($permissionsByModule[$perm['module_name']])) {
            $permissionsByModule[$perm['module_name']] = [];
        }
        $permissionsByModule[$perm['module_name']][] = $perm;
    }
    
    echo json_encode([
        'success' => true,
        'permissions' => $permissionsByModule
    ]);
}

/**
 * שמירת הרשאות משתמש
 */
function handleSavePermissions($db, $currentUser) {
    $userId = $_POST['user_id'] ?? 0;
    $permissions = $_POST['permissions'] ?? [];
    
    if (!$userId) {
        echo json_encode(['error' => 'מזהה משתמש נדרש']);
        return;
    }
    
    try {
        $db->getConnection()->beginTransaction();
        
        // מחיקת הרשאות קיימות
        $db->delete('user_permissions', 'user_id = ?', [$userId]);
        
        // הוספת הרשאות חדשות
        foreach ($permissions as $permId) {
            $db->insert('user_permissions', [
                'user_id' => $userId,
                'permission_id' => intval($permId),
                'granted_by' => $currentUser['id'],
                'granted_at' => getCurrentDateTime(),
                'is_active' => 1
            ]);
        }
        
        $db->getConnection()->commit();
        echo json_encode(['success' => true, 'message' => 'ההרשאות נשמרו בהצלחה']);
        
    } catch (Exception $e) {
        $db->getConnection()->rollBack();
        error_log("Save permissions error: " . $e->getMessage());
        echo json_encode(['error' => 'שגיאה בשמירת ההרשאות']);
    }
}

/**
 * פונקציות עזר
 */
function getCurrentUser() {
    global $currentUser;
    return $currentUser ?? [];
}

function translateRole($role) {
    $roles = [
        'super_admin' => 'מנהל ראשי',
        'company_admin' => 'מנהל חברה',
        'contractor' => 'קבלן',
        'safety_manager' => 'מנהל בטיחות',
        'inspector' => 'מפקח',
        'worker' => 'עובד'
    ];
    return $roles[$role] ?? $role;
}

function translateStatus($status) {
    $statuses = [
        'active' => 'פעיל',
        'inactive' => 'לא פעיל', 
        'pending' => 'ממתין לאישור',
        'deleted' => 'מחוק'
    ];
    return $statuses[$status] ?? $status;
}
?>
