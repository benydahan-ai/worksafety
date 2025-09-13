<?php
/**
 * WorkSafety.io - API לפעולות על משתמש בודד
 * מחיקה, שינוי סטטוס, איפוס סיסמה ועוד
 */

session_start();
header('Content-Type: application/json; charset=utf-8');

// וידוא שזו בקשת POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// וידוא שזו בקשת AJAX
if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || $_SERVER['HTTP_X_REQUESTED_WITH'] !== 'XMLHttpRequest') {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid request']);
    exit;
}

// וידוא התחברות והרשאות
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['company_role'] ?? '', ['super_admin', 'company_admin'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

require_once '../../../config/database.php';

try {
    // קבלת נתוני הבקשה
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        throw new Exception('Invalid JSON data');
    }
    
    $action = $input['action'] ?? '';
    $userId = intval($input['user_id'] ?? 0);
    $currentUserId = $_SESSION['user_id'];
    $userRole = $_SESSION['company_role'] ?? 'worker';
    $userCompanyId = $_SESSION['company_id'] ?? null;
    
    if (empty($action) || !$userId) {
        throw new Exception('Action or user ID not specified');
    }
    
    // מניעת פעולות על עצמו
    if ($userId == $currentUserId) {
        throw new Exception('לא ניתן לבצע פעולה זו על החשבון שלך');
    }
    
    $db = getDB();
    
    // בדיקת קיום המשתמש והרשאות
    $whereClause = "id = ?";
    $params = [$userId];
    
    if ($userRole === 'company_admin' && $userCompanyId) {
        $whereClause .= " AND company_id = ?";
        $params[] = $userCompanyId;
    }
    
    $targetUser = $db->fetch("SELECT * FROM users WHERE {$whereClause}", $params);
    
    if (!$targetUser) {
        throw new Exception('משתמש לא נמצא או אין לך הרשאה לבצע פעולות עליו');
    }
    
    $result = [];
    
    switch ($action) {
        case 'delete':
            $result = handleDeleteUser($db, $userId, $targetUser);
            break;
            
        case 'toggle_status':
            $result = handleToggleStatus($db, $userId, $targetUser, $input, $currentUserId);
            break;
            
        case 'reset_password':
            $result = handleResetPassword($db, $userId, $targetUser, $currentUserId);
            break;
            
        case 'change_role':
            $result = handleChangeRole($db, $userId, $targetUser, $input, $currentUserId, $userRole);
            break;
            
        case 'suspend':
            $result = handleSuspendUser($db, $userId, $targetUser, $input, $currentUserId);
            break;
            
        case 'activate':
            $result = handleActivateUser($db, $userId, $targetUser, $currentUserId);
            break;
            
        default:
            throw new Exception('פעולה לא מוכרת');
    }
    
    echo json_encode($result);
    
} catch (Exception $e) {
    error_log("User actions API error: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

/**
 * מחיקת משתמש
 */
function handleDeleteUser($db, $userId, $targetUser) {
    $db->getConnection()->beginTransaction();
    
    try {
        // מחיקת הרשאות המשתמש
        $db->delete('user_permissions', 'user_id = ?', [$userId]);
        
        // מחיקת היסטוריית כניסות
        $db->delete('user_login_history', 'user_id = ?', [$userId]);
        
        // עדכון רשומות שנוצרו על ידי המשתמש (במקום מחיקה)
        $db->update('deficiencies', ['created_by' => null], 'created_by = ?', [$userId]);
        $db->update('inspections', ['inspector_id' => null], 'inspector_id = ?', [$userId]);
        $db->update('users', ['created_by' => null], 'created_by = ?', [$userId]);
        $db->update('users', ['updated_by' => null], 'updated_by = ?', [$userId]);
        
        // מחיקת המשתמש עצמו
        $db->delete('users', 'id = ?', [$userId]);
        
        $db->getConnection()->commit();
        
        return [
            'success' => true,
            'message' => 'המשתמש נמחק בהצלחה מהמערכת',
            'action' => 'delete'
        ];
        
    } catch (Exception $e) {
        $db->getConnection()->rollBack();
        throw new Exception('שגיאה במחיקת המשתמש: ' . $e->getMessage());
    }
}

/**
 * שינוי סטטוס משתמש
 */
function handleToggleStatus($db, $userId, $targetUser, $input, $currentUserId) {
    $newStatus = $input['status'] ?? '';
    $allowedStatuses = ['active', 'inactive', 'suspended'];
    
    if (!in_array($newStatus, $allowedStatuses)) {
        throw new Exception('סטטוס לא תקין');
    }
    
    $db->update('users', [
        'status' => $newStatus,
        'updated_at' => getCurrentDateTime(),
        'updated_by' => $currentUserId
    ], 'id = ?', [$userId]);
    
    $statusNames = [
        'active' => 'הופעל',
        'inactive' => 'הושבת',
        'suspended' => 'הושעה'
    ];
    
    return [
        'success' => true,
        'message' => "המשתמש {$statusNames[$newStatus]} בהצלחה",
        'action' => 'status_change',
        'new_status' => $newStatus
    ];
}

/**
 * איפוס סיסמה
 */
function handleResetPassword($db, $userId, $targetUser, $currentUserId) {
    // יצירת סיסמה זמנית
    $tempPassword = generateRandomPassword();
    $hashedPassword = password_hash($tempPassword, PASSWORD_DEFAULT);
    
    $db->update('users', [
        'password' => $hashedPassword,
        'password_changed_at' => getCurrentDateTime(),
        'password_reset_required' => 1,
        'updated_at' => getCurrentDateTime(),
        'updated_by' => $currentUserId
    ], 'id = ?', [$userId]);
    
    // TODO: שליחת אימייל עם הסיסמה החדשה
    
    return [
        'success' => true,
        'message' => 'הסיסמה אופסה בהצלחה. סיסמה זמנית נשלחה לאימייל המשתמש',
        'action' => 'password_reset',
        'temp_password' => $tempPassword // רק לצורך הדגמה - בייצור לא לחזיר
    ];
}

/**
 * שינוי תפקיד
 */
function handleChangeRole($db, $userId, $targetUser, $input, $currentUserId, $userRole) {
    $newRole = $input['role'] ?? '';
    $allowedRoles = ['contractor', 'safety_manager', 'inspector', 'worker'];
    
    // מנהל ראשי יכול לשנות לכל תפקיד
    if ($userRole === 'super_admin') {
        $allowedRoles[] = 'company_admin';
        $allowedRoles[] = 'super_admin';
    }
    
    if (!in_array($newRole, $allowedRoles)) {
        throw new Exception('אין לך הרשאה לשייך תפקיד זה');
    }
    
    $db->update('users', [
        'role' => $newRole,
        'updated_at' => getCurrentDateTime(),
        'updated_by' => $currentUserId
    ], 'id = ?', [$userId]);
    
    $roleNames = [
        'super_admin' => 'מנהל ראשי',
        'company_admin' => 'מנהל חברה',
        'contractor' => 'קבלן',
        'safety_manager' => 'מנהל בטיחות',
        'inspector' => 'מפקח',
        'worker' => 'עובד'
    ];
    
    return [
        'success' => true,
        'message' => "התפקיד שונה ל'{$roleNames[$newRole]}' בהצלחה",
        'action' => 'role_change',
        'new_role' => $newRole
    ];
}

/**
 * השעיית משתמש
 */
function handleSuspendUser($db, $userId, $targetUser, $input, $currentUserId) {
    $reason = trim($input['reason'] ?? '');
    $duration = intval($input['duration'] ?? 0); // ימים
    
    $suspendUntil = null;
    if ($duration > 0) {
        $suspendUntil = date('Y-m-d H:i:s', strtotime("+{$duration} days"));
    }
    
    $db->update('users', [
        'status' => 'suspended',
        'suspended_at' => getCurrentDateTime(),
        'suspended_by' => $currentUserId,
        'suspend_reason' => $reason,
        'suspend_until' => $suspendUntil,
        'updated_at' => getCurrentDateTime(),
        'updated_by' => $currentUserId
    ], 'id = ?', [$userId]);
    
    $message = 'המשתמש הושעה בהצלחה';
    if ($duration > 0) {
        $message .= " ל-{$duration} ימים";
    }
    
    return [
        'success' => true,
        'message' => $message,
        'action' => 'suspend',
        'suspended_until' => $suspendUntil
    ];
}

/**
 * הפעלת משתמש מושעה
 */
function handleActivateUser($db, $userId, $targetUser, $currentUserId) {
    $db->update('users', [
        'status' => 'active',
        'suspended_at' => null,
        'suspended_by' => null,
        'suspend_reason' => null,
        'suspend_until' => null,
        'updated_at' => getCurrentDateTime(),
        'updated_by' => $currentUserId
    ], 'id = ?', [$userId]);
    
    return [
        'success' => true,
        'message' => 'המשתמש הופעל בהצלחה והושעיה הוסרה',
        'action' => 'activate'
    ];
}

/**
 * יצירת סיסמה רנדומלית
 */
function generateRandomPassword($length = 12) {
    $characters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*';
    $password = '';
    
    for ($i = 0; $i < $length; $i++) {
        $password .= $characters[random_int(0, strlen($characters) - 1)];
    }
    
    return $password;
}
?>
