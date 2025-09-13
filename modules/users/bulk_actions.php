<?php
/**
 * WorkSafety.io - API לפעולות קבוצתיות על משתמשים
 * שינוי סטטוס, מחיקה, ועוד פעולות על מספר משתמשים
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
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'] ?? '', ['super_admin', 'company_admin'])) {
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
    $data = $input['data'] ?? [];
    
    if (empty($action)) {
        throw new Exception('Action not specified');
    }
    
    $db = getDB();
    $currentUserId = $_SESSION['user_id'];
    $userRole = $_SESSION['company_role'] ?? 'worker';
    $userCompanyId = $_SESSION['company_id'] ?? null;
    
    // בדיקת הרשאות לפי התפקיד
    if ($userRole === 'company_admin' && $userCompanyId) {
        // מנהל חברה יכול לפעול רק על משתמשי החברה שלו
        $userIds = $data['users'] ?? [];
        if (!empty($userIds)) {
            $placeholders = str_repeat('?,', count($userIds) - 1) . '?';
            $companyCheck = $db->fetchAll(
                "SELECT id FROM users WHERE id IN ({$placeholders}) AND company_id = ?",
                array_merge($userIds, [$userCompanyId])
            );
            
            if (count($companyCheck) !== count($userIds)) {
                throw new Exception('אין לך הרשאה לבצע פעולות על חלק מהמשתמשים');
            }
        }
    }
    
    $result = [];
    
    switch ($action) {
        case 'change_status':
            $result = handleChangeStatus($db, $data, $currentUserId);
            break;
            
        case 'delete':
            $result = handleBulkDelete($db, $data, $currentUserId);
            break;
            
        case 'assign_role':
            $result = handleAssignRole($db, $data, $currentUserId);
            break;
            
        case 'change_company':
            $result = handleChangeCompany($db, $data, $currentUserId);
            break;
            
        default:
            throw new Exception('פעולה לא מוכרת');
    }
    
    echo json_encode($result);
    
} catch (Exception $e) {
    error_log("Bulk actions API error: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

/**
 * שינוי סטטוס משתמשים
 */
function handleChangeStatus($db, $data, $currentUserId) {
    $userIds = $data['users'] ?? [];
    $status = $data['status'] ?? '';
    
    if (empty($userIds) || empty($status)) {
        throw new Exception('נתונים חסרים לשינוי סטטוס');
    }
    
    $allowedStatuses = ['active', 'inactive', 'suspended'];
    if (!in_array($status, $allowedStatuses)) {
        throw new Exception('סטטוס לא תקין');
    }
    
    // מניעת שינוי סטטוס עצמי
    if (in_array($currentUserId, $userIds)) {
        $userIds = array_filter($userIds, function($id) use ($currentUserId) {
            return $id != $currentUserId;
        });
    }
    
    if (empty($userIds)) {
        throw new Exception('לא ניתן לשנות את הסטטוס שלך');
    }
    
    $db->getConnection()->beginTransaction();
    
    try {
        $placeholders = str_repeat('?,', count($userIds) - 1) . '?';
        $params = array_merge([$status, getCurrentDateTime(), $currentUserId], $userIds);
        
        $affected = $db->query(
            "UPDATE users SET status = ?, updated_at = ?, updated_by = ? WHERE id IN ({$placeholders})",
            $params
        )->rowCount();
        
        $db->getConnection()->commit();
        
        $statusNames = [
            'active' => 'הופעלו',
            'inactive' => 'הושבתו',
            'suspended' => 'הושעו'
        ];
        
        return [
            'success' => true,
            'message' => "{$affected} משתמשים {$statusNames[$status]} בהצלחה",
            'affected_count' => $affected
        ];
        
    } catch (Exception $e) {
        $db->getConnection()->rollBack();
        throw $e;
    }
}

/**
 * מחיקת משתמשים
 */
function handleBulkDelete($db, $data, $currentUserId) {
    $userIds = $data['users'] ?? [];
    
    if (empty($userIds)) {
        throw new Exception('לא נבחרו משתמשים למחיקה');
    }
    
    // מניעת מחיקה עצמית
    if (in_array($currentUserId, $userIds)) {
        $userIds = array_filter($userIds, function($id) use ($currentUserId) {
            return $id != $currentUserId;
        });
    }
    
    if (empty($userIds)) {
        throw new Exception('לא ניתן למחוק את החשבון שלך');
    }
    
    $db->getConnection()->beginTransaction();
    
    try {
        $placeholders = str_repeat('?,', count($userIds) - 1) . '?';
        
        // מחיקת הרשאות המשתמשים
        $db->query(
            "DELETE FROM user_permissions WHERE user_id IN ({$placeholders})",
            $userIds
        );
        
        // מחיקת היסטוריית כניסות
        $db->query(
            "DELETE FROM user_login_history WHERE user_id IN ({$placeholders})",
            $userIds
        );
        
        // מחיקת המשתמשים עצמם
        $affected = $db->query(
            "DELETE FROM users WHERE id IN ({$placeholders})",
            $userIds
        )->rowCount();
        
        $db->getConnection()->commit();
        
        return [
            'success' => true,
            'message' => "{$affected} משתמשים נמחקו בהצלחה",
            'affected_count' => $affected
        ];
        
    } catch (Exception $e) {
        $db->getConnection()->rollBack();
        throw new Exception('שגיאה במחיקת המשתמשים: ' . $e->getMessage());
    }
}

/**
 * שיוך תפקיד חדש
 */
function handleAssignRole($db, $data, $currentUserId) {
    $userIds = $data['users'] ?? [];
    $role = $data['role'] ?? '';
    
    if (empty($userIds) || empty($role)) {
        throw new Exception('נתונים חסרים לשיוך תפקיד');
    }
    
    $allowedRoles = ['contractor', 'safety_manager', 'inspector', 'worker'];
    
    // מנהל ראשי יכול לשייך כל תפקיד
    if ($_SESSION['company_role'] === 'super_admin') {
        $allowedRoles[] = 'company_admin';
        $allowedRoles[] = 'super_admin';
    }
    
    if (!in_array($role, $allowedRoles)) {
        throw new Exception('אין לך הרשאה לשייך תפקיד זה');
    }
    
    // מניעת שינוי תפקיד עצמי
    if (in_array($currentUserId, $userIds)) {
        $userIds = array_filter($userIds, function($id) use ($currentUserId) {
            return $id != $currentUserId;
        });
    }
    
    if (empty($userIds)) {
        throw new Exception('לא ניתן לשנות את התפקיד שלך');
    }
    
    $placeholders = str_repeat('?,', count($userIds) - 1) . '?';
    $params = array_merge([$role, getCurrentDateTime(), $currentUserId], $userIds);
    
    $affected = $db->query(
        "UPDATE users SET role = ?, updated_at = ?, updated_by = ? WHERE id IN ({$placeholders})",
        $params
    )->rowCount();
    
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
        'message' => "{$affected} משתמשים שויכו לתפקיד '{$roleNames[$role]}' בהצלחה",
        'affected_count' => $affected
    ];
}

/**
 * שינוי חברה
 */
function handleChangeCompany($db, $data, $currentUserId) {
    $userIds = $data['users'] ?? [];
    $companyId = $data['company_id'] ?? null;
    
    if (empty($userIds)) {
        throw new Exception('לא נבחרו משתמשים');
    }
    
    // רק מנהל ראשי יכול לשנות חברה
    if ($_SESSION['company_role'] !== 'super_admin') {
        throw new Exception('אין לך הרשאה לשנות חברה');
    }
    
    $placeholders = str_repeat('?,', count($userIds) - 1) . '?';
    $params = array_merge([$companyId, getCurrentDateTime(), $currentUserId], $userIds);
    
    $affected = $db->query(
        "UPDATE users SET company_id = ?, updated_at = ?, updated_by = ? WHERE id IN ({$placeholders})",
        $params
    )->rowCount();
    
    $companyName = 'ללא חברה';
    if ($companyId) {
        $company = $db->fetch("SELECT name FROM companies WHERE id = ?", [$companyId]);
        $companyName = $company['name'] ?? 'לא ידוע';
    }
    
    return [
        'success' => true,
        'message' => "{$affected} משתמשים הועברו לחברה '{$companyName}' בהצלחה",
        'affected_count' => $affected
    ];
}
?>
