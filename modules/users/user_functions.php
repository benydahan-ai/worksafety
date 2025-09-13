<?php
/**
 * WorkSafety.io - User Functions
 * פונקציות עזר למודול המשתמשים
 */

/**
 * קבלת פרטי המשתמש הנוכחי
 */
function getCurrentUser() {
    if (!isset($_SESSION['user_id'])) {
        return null;
    }
    
    static $currentUser = null;
    
    if ($currentUser === null) {
        try {
            $db = getDB();
            $stmt = $db->query("
                SELECT u.*, c.name as company_name, c.logo as company_logo, c.type as company_type
                FROM users u 
                LEFT JOIN companies c ON u.company_id = c.id 
                WHERE u.id = ?
            ", [$_SESSION['user_id']]);
            
            $currentUser = $stmt->fetch();
        } catch (Exception $e) {
            error_log("Error fetching current user: " . $e->getMessage());
            return null;
        }
    }
    
    return $currentUser;
}

/**
 * בדיקת הרשאה למשתמש
 */
function hasPermission($module, $permission = 'view', $userId = null) {
    $currentUser = getCurrentUser();
    if (!$currentUser) {
        return false;
    }
    
    $checkUserId = $userId ?? $currentUser['id'];
    
    // מנהל ראשי רואה הכל
    if ($currentUser['role'] === 'super_admin') {
        return true;
    }
    
    // מנהל חברה רואה הכל בחברה שלו
    if ($currentUser['role'] === 'company_admin' && $currentUser['company_id']) {
        // בדיקה שהמשתמש שאליו מנסים לגשת שייך לאותה חברה
        if ($userId) {
            $targetUser = getUserById($userId);
            if (!$targetUser || $targetUser['company_id'] != $currentUser['company_id']) {
                return false;
            }
        }
        return true;
    }
    
    try {
        $db = getDB();
        $stmt = $db->query("
            SELECT COUNT(*) as has_permission
            FROM user_permissions up 
            JOIN permissions p ON up.permission_id = p.id 
            WHERE up.user_id = ? 
            AND p.module_name = ? 
            AND p.permission_name = ? 
            AND up.is_active = 1
        ", [$checkUserId, $module, $permission]);
        
        $result = $stmt->fetch();
        return $result['has_permission'] > 0;
        
    } catch (Exception $e) {
        error_log("Error checking permission: " . $e->getMessage());
        return false;
    }
}

/**
 * קבלת משתמש לפי ID
 */
function getUserById($userId) {
    try {
        $db = getDB();
        return $db->fetch("
            SELECT u.*, c.name as company_name 
            FROM users u 
            LEFT JOIN companies c ON u.company_id = c.id 
            WHERE u.id = ?
        ", [$userId]);
    } catch (Exception $e) {
        error_log("Error fetching user by ID: " . $e->getMessage());
        return null;
    }
}

/**
 * קבלת משתמש לפי אימייל
 */
function getUserByEmail($email) {
    try {
        $db = getDB();
        return $db->fetch("
            SELECT u.*, c.name as company_name 
            FROM users u 
            LEFT JOIN companies c ON u.company_id = c.id 
            WHERE u.email = ?
        ", [$email]);
    } catch (Exception $e) {
        error_log("Error fetching user by email: " . $e->getMessage());
        return null;
    }
}

/**
 * יצירת משתמש חדש
 */
function createUser($userData) {
    try {
        $db = getDB();
        
        // וידוא שהאימייל לא קיים
        if (getUserByEmail($userData['email'])) {
            throw new Exception('כתובת האימייל כבר קיימת במערכת');
        }
        
        // הכנת נתונים לשמירה
        $data = [
            'first_name' => $userData['first_name'],
            'last_name' => $userData['last_name'],
            'email' => $userData['email'],
            'password' => password_hash($userData['password'], PASSWORD_DEFAULT),
            'phone' => $userData['phone'] ?? '',
            'role' => $userData['role'] ?? 'worker',
            'company_id' => $userData['company_id'] ?? null,
            'department' => $userData['department'] ?? '',
            'job_title' => $userData['job_title'] ?? '',
            'status' => $userData['status'] ?? 'active',
            'notes' => $userData['notes'] ?? '',
            'created_at' => getCurrentDateTime(),
            'created_by' => $_SESSION['user_id'] ?? null
        ];
        
        $userId = $db->insert('users', $data);
        
        // יצירת הרשאות בסיסיות לפי תפקיד
        createDefaultPermissions($userId, $data['role']);
        
        return $userId;
        
    } catch (Exception $e) {
        error_log("Error creating user: " . $e->getMessage());
        throw $e;
    }
}

/**
 * עדכון משתמש
 */
function updateUser($userId, $userData) {
    try {
        $db = getDB();
        
        // וידוא שהמשתמש קיים
        $existingUser = getUserById($userId);
        if (!$existingUser) {
            throw new Exception('משתמש לא נמצא');
        }
        
        // בדיקת אימייל ייחודי (אם השתנה)
        if ($userData['email'] !== $existingUser['email'] && getUserByEmail($userData['email'])) {
            throw new Exception('כתובת האימייל כבר קיימת במערכת');
        }
        
        // הכנת נתונים לעדכון
        $data = [
            'first_name' => $userData['first_name'],
            'last_name' => $userData['last_name'],
            'email' => $userData['email'],
            'phone' => $userData['phone'] ?? '',
            'role' => $userData['role'] ?? $existingUser['role'],
            'company_id' => $userData['company_id'] ?? $existingUser['company_id'],
            'department' => $userData['department'] ?? '',
            'job_title' => $userData['job_title'] ?? '',
            'status' => $userData['status'] ?? $existingUser['status'],
            'notes' => $userData['notes'] ?? '',
            'updated_at' => getCurrentDateTime(),
            'updated_by' => $_SESSION['user_id'] ?? null
        ];
        
        // הוספת סיסמה אם סופקה
        if (!empty($userData['password'])) {
            $data['password'] = password_hash($userData['password'], PASSWORD_DEFAULT);
        }
        
        $db->update('users', $data, 'id = ?', [$userId]);
        
        // עדכון הרשאות אם התפקיד השתנה
        if ($userData['role'] !== $existingUser['role']) {
            updateUserPermissions($userId, $userData['role']);
        }
        
        return true;
        
    } catch (Exception $e) {
        error_log("Error updating user: " . $e->getMessage());
        throw $e;
    }
}

/**
 * מחיקת משתמש (מחיקה רכה)
 */
function deleteUser($userId) {
    try {
        $db = getDB();
        
        // וידוא שהמשתמש קיים
        $user = getUserById($userId);
        if (!$user) {
            throw new Exception('משתמש לא נמצא');
        }
        
        // מחיקה רכה - שינוי סטטוס
        $db->update('users', [
            'status' => 'deleted',
            'email' => 'deleted_' . time() . '_' . $userId . '@deleted.local',
            'updated_at' => getCurrentDateTime(),
            'updated_by' => $_SESSION['user_id'] ?? null
        ], 'id = ?', [$userId]);
        
        // מחיקת הרשאות
        $db->delete('user_permissions', 'user_id = ?', [$userId]);
        
        return true;
        
    } catch (Exception $e) {
        error_log("Error deleting user: " . $e->getMessage());
        throw $e;
    }
}

/**
 * שינוי סטטוס משתמש
 */
function updateUserStatus($userId, $status) {
    try {
        $db = getDB();
        
        $validStatuses = ['active', 'inactive', 'pending', 'deleted'];
        if (!in_array($status, $validStatuses)) {
            throw new Exception('סטטוס לא תקין');
        }
        
        $db->update('users', [
            'status' => $status,
            'updated_at' => getCurrentDateTime(),
            'updated_by' => $_SESSION['user_id'] ?? null
        ], 'id = ?', [$userId]);
        
        return true;
        
    } catch (Exception $e) {
        error_log("Error updating user status: " . $e->getMessage());
        throw $e;
    }
}

/**
 * יצירת הרשאות בסיסיות לפי תפקיד
 */
function createDefaultPermissions($userId, $role) {
    try {
        $db = getDB();
        
        // מפת הרשאות בסיסיות לכל תפקיד
        $rolePermissions = [
            'super_admin' => [], // מנהל ראשי מקבל הכל אוטומטית
            'company_admin' => [
                'users.view', 'users.create', 'users.edit',
                'employees.view', 'employees.create', 'employees.edit',
                'contractors.view', 'contractors.create', 'contractors.edit',
                'worksites.view', 'worksites.create', 'worksites.edit',
                'equipment.view', 'equipment.create', 'equipment.edit',
                'deficiencies.view', 'deficiencies.create', 'deficiencies.edit',
                'inspections.view', 'inspections.create', 'inspections.edit',
                'statistics.view', 'summaries.view'
            ],
            'safety_manager' => [
                'users.view',
                'employees.view', 'employees.create', 'employees.edit',
                'worksites.view', 'worksites.create', 'worksites.edit',
                'equipment.view', 'equipment.create', 'equipment.edit',
                'fire_safety.view', 'fire_safety.create', 'fire_safety.edit',
                'deficiencies.view', 'deficiencies.create', 'deficiencies.edit',
                'safety_files.view', 'safety_files.create', 'safety_files.edit',
                'statistics.view', 'summaries.view'
            ],
            'inspector' => [
                'worksites.view',
                'equipment.view',
                'fire_safety.view', 'fire_safety.create', 'fire_safety.edit',
                'deficiencies.view', 'deficiencies.create', 'deficiencies.edit',
                'safety_files.view'
            ],
            'contractor' => [
                'worksites.view',
                'equipment.view',
                'employees.view',
                'deficiencies.view', 'deficiencies.create',
                'safety_files.view'
            ],
            'worker' => [
                'deficiencies.view',
                'safety_files.view'
            ]
        ];
        
        if (!isset($rolePermissions[$role])) {
            return; // תפקיד לא מוכר
        }
        
        foreach ($rolePermissions[$role] as $permissionKey) {
            list($module, $permission) = explode('.', $permissionKey);
            
            // חיפוש ID ההרשאה
            $permissionData = $db->fetch("
                SELECT id FROM permissions 
                WHERE module_name = ? AND permission_name = ?
            ", [$module, $permission]);
            
            if ($permissionData) {
                $db->insert('user_permissions', [
                    'user_id' => $userId,
                    'permission_id' => $permissionData['id'],
                    'granted_by' => $_SESSION['user_id'] ?? 1,
                    'granted_at' => getCurrentDateTime(),
                    'is_active' => 1
                ]);
            }
        }
        
    } catch (Exception $e) {
        error_log("Error creating default permissions: " . $e->getMessage());
    }
}

/**
 * עדכון הרשאות משתמש לפי תפקיד חדש
 */
function updateUserPermissions($userId, $newRole) {
    try {
        $db = getDB();
        
        // מחיקת הרשאות קיימות
        $db->delete('user_permissions', 'user_id = ?', [$userId]);
        
        // יצירת הרשאות חדשות
        createDefaultPermissions($userId, $newRole);
        
    } catch (Exception $e) {
        error_log("Error updating user permissions: " . $e->getMessage());
    }
}

/**
 * קבלת הרשאות משתמש
 */
function getUserPermissions($userId) {
    try {
        $db = getDB();
        return $db->fetchAll("
            SELECT p.module_name, p.permission_name, p.description
            FROM user_permissions up 
            JOIN permissions p ON up.permission_id = p.id 
            WHERE up.user_id = ? AND up.is_active = 1
            ORDER BY p.module_name, p.permission_name
        ", [$userId]);
    } catch (Exception $e) {
        error_log("Error fetching user permissions: " . $e->getMessage());
        return [];
    }
}

/**
 * חיפוש משתמשים מתקדם
 */
function searchUsers($params = [], $currentUser = null) {
    try {
        $db = getDB();
        $currentUser = $currentUser ?? getCurrentUser();
        
        // בניית תנאי החיפוש
        $conditions = ["u.status != 'deleted'"];
        $queryParams = [];
        
        // הגבלה לפי הרשאות
        if ($currentUser['role'] !== 'super_admin') {
            $conditions[] = "u.company_id = ?";
            $queryParams[] = $currentUser['company_id'];
        }
        
        // חיפוש טקסט חופשי
        if (!empty($params['search'])) {
            $conditions[] = "(u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)";
            $searchTerm = "%{$params['search']}%";
            $queryParams = array_merge($queryParams, [$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
        }
        
        // סינון לפי תפקיד
        if (!empty($params['role'])) {
            $conditions[] = "u.role = ?";
            $queryParams[] = $params['role'];
        }
        
        // סינון לפי סטטוס
        if (!empty($params['status'])) {
            $conditions[] = "u.status = ?";
            $queryParams[] = $params['status'];
        }
        
        // סינון לפי חברה
        if (!empty($params['company'])) {
            $conditions[] = "u.company_id = ?";
            $queryParams[] = $params['company'];
        }
        
        // סינון לפי מחלקה
        if (!empty($params['department'])) {
            $conditions[] = "u.department = ?";
            $queryParams[] = $params['department'];
        }
        
        // סינון לפי תאריכים
        if (!empty($params['date_from'])) {
            $conditions[] = "DATE(u.created_at) >= ?";
            $queryParams[] = $params['date_from'];
        }
        
        if (!empty($params['date_to'])) {
            $conditions[] = "DATE(u.created_at) <= ?";
            $queryParams[] = $params['date_to'];
        }
        
        // סינון לפי התחברות אחרונה
        if (!empty($params['last_login'])) {
            switch ($params['last_login']) {
                case 'today':
                    $conditions[] = "DATE(u.last_login) = CURDATE()";
                    break;
                case 'week':
                    $conditions[] = "u.last_login >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
                    break;
                case 'month':
                    $conditions[] = "u.last_login >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
                    break;
                case 'never':
                    $conditions[] = "u.last_login IS NULL";
                    break;
            }
        }
        
        $whereClause = implode(' AND ', $conditions);
        
        // מיון
        $sortColumn = 'u.first_name';
        $sortOrder = 'ASC';
        
        if (!empty($params['sort'])) {
            switch ($params['sort']) {
                case 'name':
                    $sortColumn = 'u.first_name';
                    break;
                case 'email':
                    $sortColumn = 'u.email';
                    break;
                case 'role':
                    $sortColumn = 'u.role';
                    break;
                case 'created':
                    $sortColumn = 'u.created_at';
                    break;
                case 'login':
                    $sortColumn = 'u.last_login';
                    break;
            }
        }
        
        if (!empty($params['order']) && strtoupper($params['order']) === 'DESC') {
            $sortOrder = 'DESC';
        }
        
        // ספירת תוצאות
        $totalStmt = $db->query("
            SELECT COUNT(*) as total 
            FROM users u 
            LEFT JOIN companies c ON u.company_id = c.id 
            WHERE {$whereClause}
        ", $queryParams);
        $total = $totalStmt->fetch()['total'];
        
        // הגדרת pagination
        $page = max(1, intval($params['page'] ?? 1));
        $limit = min(100, max(10, intval($params['limit'] ?? 20)));
        $offset = ($page - 1) * $limit;
        
        // קבלת הנתונים
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
            ORDER BY {$sortColumn} {$sortOrder}
            LIMIT ? OFFSET ?
        ", array_merge($queryParams, [$limit, $offset]));
        
        return [
            'users' => $users,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'pages' => ceil($total / $limit)
            ]
        ];
        
    } catch (Exception $e) {
        error_log("Error searching users: " . $e->getMessage());
        return [
            'users' => [],
            'pagination' => ['page' => 1, 'limit' => 20, 'total' => 0, 'pages' => 0]
        ];
    }
}

/**
 * קבלת סטטיסטיקות משתמשים
 */
function getUserStats($companyId = null) {
    try {
        $db = getDB();
        
        $whereClause = $companyId ? "WHERE u.company_id = ?" : "WHERE u.status != 'deleted'";
        $params = $companyId ? [$companyId] : [];
        
        $stats = $db->fetch("
            SELECT 
                COUNT(*) as total_users,
                SUM(CASE WHEN u.status = 'active' THEN 1 ELSE 0 END) as active_users,
                SUM(CASE WHEN u.status = 'inactive' THEN 1 ELSE 0 END) as inactive_users,
                SUM(CASE WHEN u.status = 'pending' THEN 1 ELSE 0 END) as pending_users,
                SUM(CASE WHEN u.last_login >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) as active_monthly,
                SUM(CASE WHEN u.last_login IS NULL THEN 1 ELSE 0 END) as never_logged_in
            FROM users u
            {$whereClause}
        ", $params);
        
        // סטטיסטיקות לפי תפקיד
        $roleStats = $db->fetchAll("
            SELECT 
                u.role,
                COUNT(*) as count
            FROM users u
            {$whereClause}
            GROUP BY u.role
            ORDER BY count DESC
        ", $params);
        
        $stats['roles'] = $roleStats;
        
        return $stats;
        
    } catch (Exception $e) {
        error_log("Error fetching user stats: " . $e->getMessage());
        return [
            'total_users' => 0,
            'active_users' => 0,
            'inactive_users' => 0,
            'pending_users' => 0,
            'active_monthly' => 0,
            'never_logged_in' => 0,
            'roles' => []
        ];
    }
}

/**
 * רישום פעילות משתמש
 */
function logUserActivity($userId, $action, $description = '', $entityType = 'user', $entityId = null) {
    try {
        $db = getDB();
        
        $db->insert('user_activity', [
            'user_id' => $userId,
            'action' => $action,
            'description' => $description,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'created_at' => getCurrentDateTime()
        ]);
        
    } catch (Exception $e) {
        error_log("Error logging user activity: " . $e->getMessage());
    }
}

/**
 * קבלת היסטוריית פעילות משתמש
 */
function getUserActivity($userId, $limit = 50) {
    try {
        $db = getDB();
        
        return $db->fetchAll("
            SELECT 
                action,
                description,
                entity_type,
                entity_id,
                created_at
            FROM user_activity 
            WHERE user_id = ? 
            ORDER BY created_at DESC 
            LIMIT ?
        ", [$userId, $limit]);
        
    } catch (Exception $e) {
        error_log("Error fetching user activity: " . $e->getMessage());
        return [];
    }
}

/**
 * קבלת חברות זמינות למשתמש
 */
function getAvailableCompanies($currentUser = null) {
    try {
        $db = getDB();
        $currentUser = $currentUser ?? getCurrentUser();
        
        if ($currentUser['role'] === 'super_admin') {
            return $db->fetchAll("
                SELECT id, name, type 
                FROM companies 
                WHERE status = 'active' 
                ORDER BY name
            ");
        } else {
            return $db->fetchAll("
                SELECT id, name, type 
                FROM companies 
                WHERE id = ? AND status = 'active'
            ", [$currentUser['company_id']]);
        }
        
    } catch (Exception $e) {
        error_log("Error fetching companies: " . $e->getMessage());
        return [];
    }
}

/**
 * בדיקת חוזק סיסמה
 */
function validatePassword($password) {
    $errors = [];
    
    if (strlen($password) < 8) {
        $errors[] = 'הסיסמה חייבת להכיל לפחות 8 תווים';
    }
    
    if (!preg_match('/[A-Z]/', $password)) {
        $errors[] = 'הסיסמה חייבת להכיל לפחות אות גדולה אחת';
    }
    
    if (!preg_match('/[a-z]/', $password)) {
        $errors[] = 'הסיסמה חייבת להכיל לפחות אות קטנה אחת';
    }
    
    if (!preg_match('/[0-9]/', $password)) {
        $errors[] = 'הסיסמה חייבת להכיל לפחות ספרה אחת';
    }
    
    if (!preg_match('/[^A-Za-z0-9]/', $password)) {
        $errors[] = 'הסיסמה חייבת להכיל לפחות תו מיוחד אחד';
    }
    
    return [
        'valid' => empty($errors),
        'errors' => $errors
    ];
}

/**
 * יצירת סיסמה זמנית
 */
function generateTemporaryPassword($length = 12) {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*';
    $password = '';
    
    for ($i = 0; $i < $length; $i++) {
        $password .= $chars[random_int(0, strlen($chars) - 1)];
    }
    
    return $password;
}

/**
 * שליחת מייל לאיפוס סיסמה
 */
function sendPasswordResetEmail($userId, $email) {
    try {
        // יצירת טוקן איפוס
        $token = bin2hex(random_bytes(32));
        $expiry = date('Y-m-d H:i:s', strtotime('+1 hour'));
        
        $db = getDB();
        $db->insert('password_reset_tokens', [
            'user_id' => $userId,
            'token' => hash('sha256', $token),
            'expires_at' => $expiry,
            'created_at' => getCurrentDateTime()
        ]);
        
        // שליחת מייל (כאן תוכל להשתמש בשירות מייל כמו PHPMailer)
        $resetLink = SITE_URL . "/reset-password.php?token=" . $token;
        
        // לדוגמה - בפרודקשן תצטרך להחליף עם שירות מייל אמיתי
        $subject = "איפוס סיסמה - WorkSafety.io";
        $message = "לחץ על הקישור הבא לאיפוס הסיסמה: " . $resetLink;
        
        // mail($email, $subject, $message);
        
        return true;
        
    } catch (Exception $e) {
        error_log("Error sending password reset email: " . $e->getMessage());
        return false;
    }
}

/**
 * פורמט תפקיד בעברית
 */
function formatUserRole($role) {
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

/**
 * פורמט סטטוס משתמש בעברית
 */
function formatUserStatus($status) {
    $statuses = [
        'active' => 'פעיל',
        'inactive' => 'לא פעיל',
        'pending' => 'ממתין לאישור',
        'deleted' => 'מחוק'
    ];
    
    return $statuses[$status] ?? $status;
}

/**
 * קבלת צבע לפי סטטוס
 */
function getStatusColor($status) {
    $colors = [
        'active' => 'success',
        'inactive' => 'warning',
        'pending' => 'info',
        'deleted' => 'danger'
    ];
    
    return $colors[$status] ?? 'secondary';
}

/**
 * בדיקת חוקיות כתובת אימייל
 */
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * בדיקת חוקיות מספר טלפון ישראלי
 */
function isValidIsraeliPhone($phone) {
    // הסרת רווחים ומקפים
    $phone = preg_replace('/[\s\-]/', '', $phone);
    
    // בדיקת פורמטים נפוצים
    $patterns = [
        '/^0[2-9]\d{7,8}$/',      // מספר רגיל
        '/^05[0-9]\d{7}$/',       // סלולרי
        '/^\+972[2-9]\d{7,8}$/',  // עם קידומת בינלאומית
        '/^972[2-9]\d{7,8}$/'     // בלי +
    ];
    
    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $phone)) {
            return true;
        }
    }
    
    return false;
}

/**
 * ניקוי ופורמט מספר טלפון
 */
function formatIsraeliPhone($phone) {
    if (empty($phone)) return '';
    
    // הסרת תווים מיותרים
    $phone = preg_replace('/[^\d+]/', '', $phone);
    
    // המרה לפורמט ישראלי
    if (strpos($phone, '+972') === 0) {
        $phone = '0' . substr($phone, 4);
    } elseif (strpos($phone, '972') === 0) {
        $phone = '0' . substr($phone, 3);
    }
    
    return $phone;
}

/**
 * יצירת אווטר עם אותיות ראשיות
 */
function generateUserAvatar($firstName, $lastName, $size = 50) {
    $initials = strtoupper(mb_substr($firstName, 0, 1) . mb_substr($lastName, 0, 1));
    
    // צבעי רקע לפי האות הראשונה
    $colors = [
        'A' => '#f56565', 'B' => '#ed8936', 'C' => '#ecc94b', 'D' => '#48bb78',
        'E' => '#38b2ac', 'F' => '#4299e1', 'G' => '#667eea', 'H' => '#9f7aea',
        'I' => '#ed64a6', 'J' => '#f56565', 'K' => '#ed8936', 'L' => '#ecc94b',
        'M' => '#48bb78', 'N' => '#38b2ac', 'O' => '#4299e1', 'P' => '#667eea',
        'Q' => '#9f7aea', 'R' => '#ed64a6', 'S' => '#f56565', 'T' => '#ed8936',
        'U' => '#ecc94b', 'V' => '#48bb78', 'W' => '#38b2ac', 'X' => '#4299e1',
        'Y' => '#667eea', 'Z' => '#9f7aea'
    ];
    
    $firstLetter = mb_substr($initials, 0, 1);
    $color = $colors[$firstLetter] ?? '#667eea';
    
    return [
        'initials' => $initials,
        'color' => $color,
        'size' => $size
    ];
}
?>
