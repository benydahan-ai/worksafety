<?php
/**
 * WorkSafety.io - Helper Functions - מתוקן
 * פונקציות עזר כלליות למערכת עם תיקוני שמות שדות
 */

/**
 * פורמט תאריך עברי
 */
function formatFullHebrewDate($date = null) {
    if (!$date) {
        $date = getCurrentDateTime();
    }
    
    $israel_time = new DateTime($date, new DateTimeZone('Asia/Jerusalem'));
    
    $months_hebrew = [
        1 => 'ינואר', 2 => 'פברואר', 3 => 'מרץ', 4 => 'אפריל',
        5 => 'מאי', 6 => 'יוני', 7 => 'יולי', 8 => 'אוגוסט',
        9 => 'ספטמבר', 10 => 'אוקטובר', 11 => 'נובמבר', 12 => 'דצמבר'
    ];
    
    $days_hebrew = [
        'Sunday' => 'ראשון',
        'Monday' => 'שני', 
        'Tuesday' => 'שלישי',
        'Wednesday' => 'רביעי',
        'Thursday' => 'חמישי',
        'Friday' => 'שישי',
        'Saturday' => 'שבת'
    ];
    
    $day_name = $days_hebrew[$israel_time->format('l')] ?? '';
    $day = $israel_time->format('j');
    $month = $months_hebrew[(int)$israel_time->format('n')];
    $year = $israel_time->format('Y');
    $time = $israel_time->format('H:i');
    
    return "יום {$day_name}, {$day} ב{$month} {$year}, שעה {$time}";
}

/**
 * פונקציות אבטחה וולידציה
 */
function sanitizeInput($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}



/**
 * פונקציות עזר למערכת
 */
function generateRandomString($length = 10) {
    return bin2hex(random_bytes($length / 2));
}

function createSlug($text) {
    // המרת טקסט לכתובת ידידותית
    $text = trim($text);
    $text = preg_replace('/[^a-zA-Z0-9\s\-_]/', '', $text);
    $text = preg_replace('/[\s\-_]+/', '-', $text);
    return strtolower($text);
}

/**
 * פונקציות פורמט
 */
function formatFileSize($bytes, $precision = 2) {
    $units = array('B', 'KB', 'MB', 'GB', 'TB');
    
    for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
        $bytes /= 1024;
    }
    
    return round($bytes, $precision) . ' ' . $units[$i];
}

function formatNumber($number, $decimals = 0) {
    return number_format($number, $decimals, '.', ',');
}

/**
 * פונקציות ניהול הודעות
 */
function setSuccessMessage($message) {
    $_SESSION['success_message'] = $message;
}

function setErrorMessage($message) {
    $_SESSION['error_message'] = $message;
}

function setWarningMessage($message) {
    $_SESSION['warning_message'] = $message;
}

function setInfoMessage($message) {
    $_SESSION['info_message'] = $message;
}

function getMessages() {
    $messages = [];
    
    if (isset($_SESSION['success_message'])) {
        $messages['success'] = $_SESSION['success_message'];
        unset($_SESSION['success_message']);
    }
    
    if (isset($_SESSION['error_message'])) {
        $messages['error'] = $_SESSION['error_message'];
        unset($_SESSION['error_message']);
    }
    
    if (isset($_SESSION['warning_message'])) {
        $messages['warning'] = $_SESSION['warning_message'];
        unset($_SESSION['warning_message']);
    }
    
    if (isset($_SESSION['info_message'])) {
        $messages['info'] = $_SESSION['info_message'];
        unset($_SESSION['info_message']);
    }
    
    return $messages;
}

/**
 * פונקציות ניהול קבצים
 */
function uploadFile($file, $uploadDir, $allowedTypes = []) {
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('שגיאה בהעלאת הקובץ');
    }
    
    $fileName = $file['name'];
    $fileSize = $file['size'];
    $fileTmp = $file['tmp_name'];
    $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    
    if (!empty($allowedTypes) && !in_array($fileExt, $allowedTypes)) {
        throw new Exception('סוג קובץ לא מורשה');
    }
    
    $newFileName = generateRandomString(20) . '.' . $fileExt;
    $uploadPath = $uploadDir . '/' . $newFileName;
    
    if (!move_uploaded_file($fileTmp, $uploadPath)) {
        throw new Exception('שגיאה בשמירת הקובץ');
    }
    
    return $newFileName;
}

/**
 * פונקציות לוג ודיבוג
 */
function logActivity($action, $details = null, $userId = null) {
    if (!$userId && isset($_SESSION['user_id'])) {
        $userId = $_SESSION['user_id'];
    }
    
    try {
        $db = getDB();
        $db->insert('activity_log', [
            'user_id' => $userId,
            'action' => $action,
            'details' => $details ? json_encode($details) : null,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            'created_at' => getCurrentDateTime()
        ]);
    } catch (Exception $e) {
        error_log("Error logging activity: " . $e->getMessage());
    }
}

function debugVar($var, $label = 'Debug') {
    if (defined('DEBUG_MODE') && DEBUG_MODE) {
        echo "<pre><strong>{$label}:</strong>\n";
        print_r($var);
        echo "</pre>";
    }
}

/**
 * פונקציות מערכת הרשאות
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function isSuperAdmin() {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'super_admin';
}

function isCompanyAdmin() {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'company_admin';
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: /login.php');
        exit;
    }
}

function requireSuperAdmin() {
    requireLogin();
    if (!isSuperAdmin()) {
        header('Location: /unauthorized.php');
        exit;
    }
}

/**
 * פונקציות עזר לממשק משתמש
 */
function getBreadcrumbs($items) {
    $html = '<nav aria-label="breadcrumb"><ol class="breadcrumb">';
    
    foreach ($items as $index => $item) {
        $isLast = $index === count($items) - 1;
        
        if ($isLast) {
            $html .= '<li class="breadcrumb-item active" aria-current="page">' . 
                     htmlspecialchars($item['title']) . '</li>';
        } else {
            $html .= '<li class="breadcrumb-item">';
            if (isset($item['url'])) {
                $html .= '<a href="' . htmlspecialchars($item['url']) . '">' . 
                         htmlspecialchars($item['title']) . '</a>';
            } else {
                $html .= htmlspecialchars($item['title']);
            }
            $html .= '</li>';
        }
    }
    
    $html .= '</ol></nav>';
    return $html;
}

function getPagination($page, $totalPages, $baseUrl) {
    $html = '<nav aria-label="ניווט דפים">';
    $html .= '<ul class="pagination">';
    
    // Previous page
    if ($page > 1) {
        $html .= '<li class="page-item">';
        $html .= '<a class="page-link" href="' . $baseUrl . '?page=' . ($page - 1) . '">';
        $html .= '<i class="fas fa-chevron-right"></i> הקודם</a></li>';
    }
    
    // Page numbers
    $start = max(1, $page - 2);
    $end = min($totalPages, $page + 2);
    
    for ($i = $start; $i <= $end; $i++) {
        $active = $i === $page ? ' active' : '';
        $html .= '<li class="page-item' . $active . '">';
        $html .= '<a class="page-link" href="' . $baseUrl . '?page=' . $i . '">' . $i . '</a>';
        $html .= '</li>';
    }
    
    // Next page
    if ($page < $totalPages) {
        $html .= '<li class="page-item">';
        $html .= '<a class="page-link" href="' . $baseUrl . '?page=' . ($page + 1) . '">';
        $html .= 'הבא <i class="fas fa-chevron-left"></i></a></li>';
    }
    
    $html .= '</ul></nav>';
    return $html;
}

/**
 * פונקציות מיוחדות לישראל
 */
function validateIsraeliID($id) {
    $id = preg_replace('/[^\d]/', '', $id);
    if (strlen($id) !== 9) return false;
    
    $sum = 0;
    for ($i = 0; $i < 9; $i++) {
        $digit = $id[$i];
        if ($i % 2 === 1) {
            $digit *= 2;
            if ($digit > 9) $digit -= 9;
        }
        $sum += $digit;
    }
    
    return $sum % 10 === 0;
}

function formatIsraeliPhone($phone) {
    $phone = preg_replace('/[^\d]/', '', $phone);
    
    if (substr($phone, 0, 3) === '972') {
        $phone = '0' . substr($phone, 3);
    } elseif (substr($phone, 0, 4) === '+972') {
        $phone = '0' . substr($phone, 4);
    }
    
    return $phone;
}







/**
 * פורמט זמן שעבר בעברית
 */
function formatTimeAgo($datetime) {
    if (!$datetime) return 'לא ידוע';
    
    $timestamp = is_string($datetime) ? strtotime($datetime) : $datetime;
    if (!$timestamp) return 'זמן לא תקין';
    
    $diff = time() - $timestamp;
    
    if ($diff < 60) {
        return 'לפני כמה שניות';
    } elseif ($diff < 3600) {
        $minutes = floor($diff / 60);
        return "לפני {$minutes} דקות";
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        return $hours == 1 ? 'לפני שעה' : "לפני {$hours} שעות";
    } elseif ($diff < 2592000) {
        $days = floor($diff / 86400);
        return $days == 1 ? 'אתמול' : "לפני {$days} ימים";
    } elseif ($diff < 31536000) {
        $months = floor($diff / 2592000);
        return $months == 1 ? 'לפני חודש' : "לפני {$months} חודשים";
    } else {
        $years = floor($diff / 31536000);
        return $years == 1 ? 'לפני שנה' : "לפני {$years} שנים";
    }
}

/**
 * קבלת מידע על המשתמש הנוכחי - מתוקן
 */
function getCurrentUser() {
    if (!isset($_SESSION['user_id'])) {
        return null;
    }
    
    static $currentUser = null;
    
    if ($currentUser === null) {
        try {
            $db = getDB();
            // תיקון שם השדה מ company_name ל name
            $stmt = $db->query("
                SELECT u.*, c.name as company_name, c.logo as company_logo, c.company_type as company_type
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
 * בדיקת הרשאות משתמש
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
        if ($userId) {
            try {
                $db = getDB();
                $targetUser = $db->fetch("SELECT company_id FROM users WHERE id = ?", [$userId]);
                if (!$targetUser || $targetUser['company_id'] != $currentUser['company_id']) {
                    return false;
                }
            } catch (Exception $e) {
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
 * פונקציה מתוקנת לקבלת חברות
 */
function getCompanies($filters = []) {
    try {
        $db = getDB();
        
        $whereConditions = ['1=1'];
        $params = [];
        
        if (!empty($filters['search'])) {
            $whereConditions[] = "(name LIKE ? OR email LIKE ? OR contact_person LIKE ?)";
            $searchTerm = "%{$filters['search']}%";
            $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm]);
        }
        
        if (!empty($filters['status'])) {
            $whereConditions[] = "status = ?";
            $params[] = $filters['status'];
        }
        
        if (!empty($filters['type'])) {
            $whereConditions[] = "company_type = ?";
            $params[] = $filters['type'];
        }
        
        $whereClause = implode(' AND ', $whereConditions);
        $orderBy = $filters['order_by'] ?? 'name ASC';
        $limit = isset($filters['limit']) ? "LIMIT " . intval($filters['limit']) : '';
        
        // שימוש בשמות השדות הנכונים
        return $db->fetchAll("
            SELECT 
                id,
                name,
                company_type,
                email,
                phone,
                contact_person,
                status,
                subscription_plan,
                max_users,
                max_sites,
                expires_at,
                created_at,
                updated_at
            FROM companies 
            WHERE {$whereClause}
            ORDER BY {$orderBy}
            {$limit}
        ", $params);
        
    } catch (Exception $e) {
        error_log("Error fetching companies: " . $e->getMessage());
        return [];
    }
}

/**
 * פונקציה לבדיקת תוקף מנוי
 */
function checkSubscriptionStatus($companyId) {
    try {
        $db = getDB();
        // תיקון שם השדה מ subscription_end_date ל expires_at
        $company = $db->fetch("
            SELECT expires_at, status, subscription_plan 
            FROM companies 
            WHERE id = ?
        ", [$companyId]);
        
        if (!$company) {
            return ['status' => 'not_found', 'message' => 'חברה לא נמצאה'];
        }
        
        if ($company['status'] !== 'active') {
            return ['status' => 'inactive', 'message' => 'חברה לא פעילה'];
        }
        
        if (!$company['expires_at']) {
            return ['status' => 'unlimited', 'message' => 'מנוי ללא הגבלת זמן'];
        }
        
        $expiryDate = new DateTime($company['expires_at']);
        $today = new DateTime();
        
        if ($expiryDate < $today) {
            return ['status' => 'expired', 'message' => 'המנוי פג תוקף'];
        }
        
        $diff = $today->diff($expiryDate);
        if ($diff->days <= 30) {
            return [
                'status' => 'expiring_soon', 
                'message' => "המנוי פוגה בעוד {$diff->days} ימים",
                'days_left' => $diff->days
            ];
        }
        
        return [
            'status' => 'active', 
            'message' => 'המנוי פעיל',
            'expires_at' => $company['expires_at']
        ];
        
    } catch (Exception $e) {
        error_log("Error checking subscription status: " . $e->getMessage());
        return ['status' => 'error', 'message' => 'שגיאה בבדיקת המנוי'];
    }
}

/**
 * פונקציה לעדכון סטטוס חברה
 */
function updateCompanyStatus($companyId, $status, $userId = null) {
    try {
        $db = getDB();
        $userId = $userId ?? $_SESSION['user_id'] ?? null;
        
        $allowedStatuses = ['active', 'inactive', 'suspended'];
        if (!in_array($status, $allowedStatuses)) {
            throw new Exception('סטטוס לא תקין');
        }
        
        // בדיקה שהחברה קיימת
        $company = $db->fetch("SELECT id, name, status FROM companies WHERE id = ?", [$companyId]);
        if (!$company) {
            throw new Exception('חברה לא נמצאה');
        }
        
        // עדכון החברה
        $updated = $db->update(
            'companies',
            [
                'status' => $status,
                'updated_at' => date('Y-m-d H:i:s'),
                'updated_by' => $userId
            ],
            'id = ?',
            [$companyId]
        );
        
        if (!$updated) {
            throw new Exception('שגיאה בעדכון החברה');
        }
        
        // רישום פעילות
        if ($userId) {
            $statusNames = ['active' => 'הופעלה', 'inactive' => 'הופסקה', 'suspended' => 'הושעתה'];
            $description = "החברה {$company['name']} {$statusNames[$status]}";
            
            $db->insert('activity_logs', [
                'user_id' => $userId,
                'company_id' => $companyId,
                'action' => 'company_status_update',
                'entity_type' => 'company',
                'entity_id' => $companyId,
                'description' => $description,
                'old_values' => json_encode(['status' => $company['status']]),
                'new_values' => json_encode(['status' => $status]),
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                'created_at' => date('Y-m-d H:i:s')
            ]);
        }
        
        return true;
        
    } catch (Exception $e) {
        error_log("Error updating company status: " . $e->getMessage());
        return false;
    }
}

/**
 * פונקציה לקבלת סטטיסטיקות מהירות
 */
function getQuickStats($companyId = null) {
    try {
        $db = getDB();
        
        $whereCondition = $companyId ? "company_id = ?" : "1=1";
        $params = $companyId ? [$companyId] : [];
        
        $stats = [];
        
        // חברות (רק למנהל ראשי)
        if (!$companyId) {
            $stats['companies'] = $db->fetch("SELECT COUNT(*) as count FROM companies WHERE status = 'active'")['count'] ?? 0;
        }
        
        // קבלנים
        $tableExists = $db->query("SHOW TABLES LIKE 'contractors'")->fetch();
        if ($tableExists) {
            $stats['contractors'] = $db->fetch("SELECT COUNT(*) as count FROM contractors WHERE {$whereCondition} AND status = 'active'", $params)['count'] ?? 0;
        } else {
            $stats['contractors'] = 0;
        }
        
        // אתרי עבודה
        $tableExists = $db->query("SHOW TABLES LIKE 'worksites'")->fetch();
        if ($tableExists) {
            $stats['active_sites'] = $db->fetch("SELECT COUNT(*) as count FROM worksites WHERE {$whereCondition} AND status = 'active'", $params)['count'] ?? 0;
        } else {
            $stats['active_sites'] = 0;
        }
        
        // עובדים
        $tableExists = $db->query("SHOW TABLES LIKE 'employees'")->fetch();
        if ($tableExists) {
            $stats['employees'] = $db->fetch("SELECT COUNT(*) as count FROM employees WHERE {$whereCondition} AND status = 'active'", $params)['count'] ?? 0;
        } else {
            $stats['employees'] = 0;
        }
        
        // ליקויים פתוחים
        $tableExists = $db->query("SHOW TABLES LIKE 'deficiencies'")->fetch();
        if ($tableExists) {
            $stats['open_deficiencies'] = $db->fetch("SELECT COUNT(*) as count FROM deficiencies WHERE {$whereCondition} AND status = 'open'", $params)['count'] ?? 0;
        } else {
            $stats['open_deficiencies'] = 0;
        }
        
        return $stats;
        
    } catch (Exception $e) {
        error_log("Error fetching quick stats: " . $e->getMessage());
        return array_fill_keys(['companies', 'contractors', 'active_sites', 'employees', 'open_deficiencies'], 0);
    }
}

/**
 * פונקציה לבדיקת בריאות המערכת
 */
function checkSystemHealth() {
    $health = [
        'status' => 'healthy',
        'issues' => [],
        'warnings' => []
    ];
    
    try {
        $db = getDB();
        
        // בדיקת חיבור למסד הנתונים
        $db->query("SELECT 1");
        
        // בדיקת טבלאות חיוניות
        $requiredTables = ['users', 'companies'];
        foreach ($requiredTables as $table) {
            $exists = $db->query("SHOW TABLES LIKE '{$table}'")->fetch();
            if (!$exists) {
                $health['issues'][] = "טבלה חסרה: {$table}";
                $health['status'] = 'unhealthy';
            }
        }
        
        // בדיקת חברות שפג תוקפן
        $expiredCompanies = $db->fetchAll("
            SELECT id, name FROM companies 
            WHERE expires_at < NOW() AND expires_at IS NOT NULL AND status = 'active'
        ");
        
        if (!empty($expiredCompanies)) {
            $health['warnings'][] = count($expiredCompanies) . " חברות עם מנוי שפג תוקף";
        }
        
        // בדיקת חברות שפוגות בקרוב
        $expiringSoon = $db->fetchAll("
            SELECT id, name FROM companies 
            WHERE expires_at BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 30 DAY) 
            AND status = 'active'
        ");
        
        if (!empty($expiringSoon)) {
            $health['warnings'][] = count($expiringSoon) . " חברות שפוגות בחודש הקרוב";
        }
        
    } catch (Exception $e) {
        $health['status'] = 'unhealthy';
        $health['issues'][] = 'שגיאה בחיבור למסד הנתונים: ' . $e->getMessage();
    }
    
    return $health;
}

/**
 * פונקציית עזר לניקוי HTML
 */
function sanitizeHtml($html) {
    return htmlspecialchars($html, ENT_QUOTES, 'UTF-8');
}

/**
 * פונקציית עזר לולידציה של אימייל
 */
function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * פונקציית עזר לולידציה של טלפון ישראלי
 */
function validateIsraeliPhone($phone) {
    $pattern = '/^0[2-9]\d{7,8}$/';
    return preg_match($pattern, $phone);
}

/**
 * פונקציית עזר לפורמט טלפון
 */
function formatPhone($phone) {
    $phone = preg_replace('/[^\d]/', '', $phone);
    if (strlen($phone) == 10 && $phone[0] == '0') {
        return preg_replace('/(\d{3})(\d{3})(\d{4})/', '$1-$2-$3', $phone);
    }
    return $phone;
}
?>
