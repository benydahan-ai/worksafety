<?php
/**
 * WorkSafety.io - ייצוא נתוני חברות
 * ייצוא נתונים לפורמט Excel עם סינונים
 */

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
    $_SESSION['flash_message']['danger'] = 'אין לך הרשאה לבצע פעולה זו';
    header('Location: index.php');
    exit;
}

try {
    $db = getDB();
    $companyId = $currentUser['company_id'] ?? 0;
    
    // פרמטרי סינון (מהכתובת)
    $search = $_GET['search'] ?? '';
    $status = $_GET['status'] ?? '';
    $company_type = $_GET['company_type'] ?? '';
    $subscription_plan = $_GET['subscription_plan'] ?? '';
    
    // בניית שאילתה בהתאם לתפקיד
    $whereConditions = [];
    $params = [];
    
    if ($userRole === 'company_admin') {
        $whereConditions[] = "(c.id = ? OR c.parent_company_id = ?)";
        $params[] = $companyId;
        $params[] = $companyId;
    }
    
    // חיפוש טקסט
    if (!empty($search)) {
        $whereConditions[] = "(c.name LIKE ? OR c.email LIKE ? OR c.contact_person LIKE ?)";
        $searchTerm = "%{$search}%";
        $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm]);
    }
    
    // סינון לפי סטטוס
    if (!empty($status)) {
        $whereConditions[] = "c.status = ?";
        $params[] = $status;
    }
    
    // סינון לפי סוג חברה
    if (!empty($company_type)) {
        $whereConditions[] = "c.company_type = ?";
        $params[] = $company_type;
    }
    
    // סינון לפי תוכנית מנוי
    if (!empty($subscription_plan)) {
        $whereConditions[] = "c.subscription_plan = ?";
        $params[] = $subscription_plan;
    }
    
    $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
    
    // קבלת הנתונים לייצוא
    $companies = $db->fetchAll("
        SELECT 
            c.id,
            c.name,
            c.company_type,
            c.email,
            c.phone,
            c.address,
            c.contact_person,
            c.website,
            c.registration_number,
            c.tax_id,
            c.subscription_plan,
            c.max_users,
            c.max_sites,
            c.status,
            c.expires_at,
            c.created_at,
            c.updated_at,
            (SELECT COUNT(*) FROM users WHERE company_id = c.id AND status = 'active') as active_users,
            (SELECT COUNT(*) FROM worksites WHERE company_id = c.id AND status = 'active') as active_sites,
            (SELECT COUNT(*) FROM contractors WHERE company_id = c.id AND status = 'active') as active_contractors
        FROM companies c 
        {$whereClause}
        ORDER BY c.name ASC
    ", $params);
    
    // יצירת קובץ Excel
    createExcelFile($companies);
    
} catch (Exception $e) {
    error_log("Export companies error: " . $e->getMessage());
    $_SESSION['flash_message']['danger'] = 'שגיאה בייצוא הנתונים';
    header('Location: index.php');
    exit;
}

/**
 * יצירת קובץ Excel עם הנתונים
 */
function createExcelFile($companies) {
    // הגדרת headers לייצוא
    $filename = 'companies_export_' . date('Y-m-d_H-i-s') . '.csv';
    
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
    header('Expires: 0');
    header('Pragma: public');
    
    // פתיחת output stream
    $output = fopen('php://output', 'w');
    
    // הוספת BOM לתמיכה בעברית ב-Excel
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // כותרות עמודות
    $headers = [
        'מזהה',
        'שם החברה',
        'סוג חברה',
        'אימייל',
        'טלפון',
        'כתובת',
        'איש קשר',
        'אתר אינטרנט',
        'מספר רישום',
        'מספר עוסק',
        'תוכנית מנוי',
        'מגבלת משתמשים',
        'מגבלת אתרים',
        'משתמשים פעילים',
        'אתרים פעילים',
        'קבלנים פעילים',
        'סטטוס',
        'תפוגת מנוי',
        'תאריך יצירה',
        'עדכון אחרון'
    ];
    
    fputcsv($output, $headers);
    
    // כתיבת הנתונים
    foreach ($companies as $company) {
        $row = [
            $company['id'],
            $company['name'],
            $company['company_type'] === 'main' ? 'ראשית' : 'לקוח',
            $company['email'],
            $company['phone'] ?? '',
            $company['address'] ?? '',
            $company['contact_person'] ?? '',
            $company['website'] ?? '',
            $company['registration_number'] ?? '',
            $company['tax_id'] ?? '',
            translateSubscriptionPlan($company['subscription_plan']),
            $company['max_users'] == 0 ? 'ללא הגבלה' : $company['max_users'],
            $company['max_sites'] == 0 ? 'ללא הגבלה' : $company['max_sites'],
            $company['active_users'],
            $company['active_sites'],
            $company['active_contractors'],
            translateStatus($company['status']),
            $company['expires_at'] ? date('d/m/Y', strtotime($company['expires_at'])) : 'ללא הגבלה',
            date('d/m/Y H:i', strtotime($company['created_at'])),
            date('d/m/Y H:i', strtotime($company['updated_at']))
        ];
        
        fputcsv($output, $row);
    }
    
    fclose($output);
    exit;
}

/**
 * תרגום תוכנית מנוי לעברית
 */
function translateSubscriptionPlan($plan) {
    $translations = [
        'basic' => 'בסיסי',
        'standard' => 'סטנדרטי',
        'premium' => 'פרימיום',
        'enterprise' => 'ארגוני'
    ];
    
    return $translations[$plan] ?? $plan;
}

/**
 * תרגום סטטוס לעברית
 */
function translateStatus($status) {
    $translations = [
        'active' => 'פעיל',
        'inactive' => 'לא פעיל',
        'suspended' => 'מושעה'
    ];
    
    return $translations[$status] ?? $status;
}
