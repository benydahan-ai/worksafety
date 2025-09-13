<?php
/**
 * WorkSafety.io - פונקציות עזר למודול החברות
 * קובץ המכיל פונקציות שימושיות לניהול חברות
 */

/**
 * ולידציה של נתוני חברה
 */
function validateCompanyData($data, $db, $companyId = null) {
    $errors = [];
    
    // בדיקת שם חברה
    if (empty($data['name'])) {
        $errors[] = 'שם החברה הוא שדה חובה';
    } elseif (strlen($data['name']) < 2) {
        $errors[] = 'שם החברה חייב להכיל לפחות 2 תווים';
    } elseif (strlen($data['name']) > 255) {
        $errors[] = 'שם החברה ארוך מדי (מקסימום 255 תווים)';
    } else {
        // בדיקת ייחודיות השם
        $whereClause = $companyId ? "AND id != {$companyId}" : "";
        $existing = $db->fetchOne("SELECT id FROM companies WHERE name = ? {$whereClause}", [$data['name']]);
        if ($existing) {
            $errors[] = 'שם החברה כבר קיים במערכת';
        }
    }
    
    // בדיקת אימייל
    if (empty($data['email'])) {
        $errors[] = 'כתובת אימייל היא שדה חובה';
    } elseif (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'כתובת אימייל לא תקינה';
    } else {
        // בדיקת ייחודיות האימייל
        $whereClause = $companyId ? "AND id != {$companyId}" : "";
        $existing = $db->fetchOne("SELECT id FROM companies WHERE email = ? {$whereClause}", [$data['email']]);
        if ($existing) {
            $errors[] = 'כתובת האימייל כבר קיימת במערכת';
        }
    }
    
    // בדיקת סוג חברה
    if (!in_array($data['company_type'], ['main', 'client'])) {
        $errors[] = 'סוג החברה לא תקין';
    }
    
    // בדיקת טלפון (אם הוזן)
    if (!empty($data['phone']) && !preg_match('/^[\d\-\+\(\)\s]{7,20}$/', $data['phone'])) {
        $errors[] = 'מספר טלפון לא תקין';
    }
    
    // בדיקת אתר אינטרנט (אם הוזן)
    if (!empty($data['website']) && !filter_var($data['website'], FILTER_VALIDATE_URL)) {
        $errors[] = 'כתובת אתר האינטרנט לא תקינה';
    }
    
    // בדיקת תוכנית מנוי
    if (!in_array($data['subscription_plan'], ['basic', 'standard', 'premium', 'enterprise'])) {
        $errors[] = 'תוכנית המנוי לא תקינה';
    }
    
    // בדיקת מגבלות
    if (!is_numeric($data['max_users']) || $data['max_users'] < 0 || $data['max_users'] > 1000) {
        $errors[] = 'מגבלת משתמשים חייבת להיות מספר בין 0 ל-1000';
    }
    
    if (!is_numeric($data['max_sites']) || $data['max_sites'] < 0 || $data['max_sites'] > 500) {
        $errors[] = 'מגבלת אתרי עבודה חייבת להיות מספר בין 0 ל-500';
    }
    
    // בדיקת תאריך תפוגה (אם הוזן)
    if (!empty($data['expires_at'])) {
        $expiryDate = DateTime::createFromFormat('Y-m-d', $data['expires_at']);
        if (!$expiryDate || $expiryDate->format('Y-m-d') !== $data['expires_at']) {
            $errors[] = 'תאריך תפוגת המנוי לא תקין';
        } elseif ($expiryDate < new DateTime()) {
            $errors[] = 'תאריך תפוגת המנוי לא יכול להיות בעבר';
        }
    }
    
    // בדיקת סטטוס
    if (!in_array($data['status'], ['active', 'inactive', 'suspended'])) {
        $errors[] = 'סטטוס החברה לא תקין';
    }
    
    // בדיקת מספר רישום (אם הוזן)
    if (!empty($data['registration_number'])) {
        // בדיקת ייחודיות מספר הרישום
        $whereClause = $companyId ? "AND id != {$companyId}" : "";
        $existing = $db->fetchOne("SELECT id FROM companies WHERE registration_number = ? {$whereClause}", [$data['registration_number']]);
        if ($existing) {
            $errors[] = 'מספר הרישום כבר קיים במערכת';
        }
    }
    
    return $errors;
}

/**
 * טיפול בהעלאת לוגו חברה
 */
function handleLogoUpload($file) {
    $result = ['success' => false, 'path' => '', 'error' => ''];
    
    // בדיקת שגיאות העלאה
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $result['error'] = 'שגיאה בהעלאת הקובץ';
        return $result;
    }
    
    // בדיקת גודל קובץ (מקסימום 2MB)
    if ($file['size'] > 2 * 1024 * 1024) {
        $result['error'] = 'גודל הקובץ גדול מדי (מקסימום 2MB)';
        return $result;
    }
    
    // בדיקת סוג קובץ
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    if (!in_array($mimeType, $allowedTypes)) {
        $result['error'] = 'סוג הקובץ לא נתמך. רק JPG, PNG ו-GIF מותרים';
        return $result;
    }
    
    // יצירת תיקיית uploads אם לא קיימת
    $uploadDir = __DIR__ . '/../../../uploads/companies/logos/';
    if (!is_dir($uploadDir)) {
        if (!mkdir($uploadDir, 0755, true)) {
            $result['error'] = 'שגיאה ביצירת תיקיית העלאות';
            return $result;
        }
    }
    
    // יצירת שם קובץ ייחודי
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = 'logo_' . uniqid() . '.' . strtolower($extension);
    $targetPath = $uploadDir . $filename;
    $relativePath = '/uploads/companies/logos/' . $filename;
    
    // העלאת הקובץ
    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
        // שינוי גודל התמונה אם צריך
        $resizeResult = resizeCompanyLogo($targetPath);
        if (!$resizeResult) {
            // אם השינוי נכשל, נמחק את הקובץ
            unlink($targetPath);
            $result['error'] = 'שגיאה בעיבוד התמונה';
            return $result;
        }
        
        $result['success'] = true;
        $result['path'] = $relativePath;
    } else {
        $result['error'] = 'שגיאה בשמירת הקובץ';
    }
    
    return $result;
}

/**
 * שינוי גודל לוגו חברה
 */
function resizeCompanyLogo($imagePath, $maxWidth = 200, $maxHeight = 100) {
    try {
        // קבלת מידע על התמונה
        $imageInfo = getimagesize($imagePath);
        if (!$imageInfo) {
            return false;
        }
        
        $originalWidth = $imageInfo[0];
        $originalHeight = $imageInfo[1];
        $mimeType = $imageInfo['mime'];
        
        // אם התמונה כבר בגודל המתאים
        if ($originalWidth <= $maxWidth && $originalHeight <= $maxHeight) {
            return true;
        }
        
        // חישוב גודל חדש
        $ratio = min($maxWidth / $originalWidth, $maxHeight / $originalHeight);
        $newWidth = round($originalWidth * $ratio);
        $newHeight = round($originalHeight * $ratio);
        
        // יצירת תמונה חדשה
        $newImage = imagecreatetruecolor($newWidth, $newHeight);
        
        // טיפול בשקיפות עבור PNG ו-GIF
        if ($mimeType === 'image/png' || $mimeType === 'image/gif') {
            imagealphablending($newImage, false);
            imagesavealpha($newImage, true);
            $transparent = imagecolorallocatealpha($newImage, 255, 255, 255, 127);
            imagefill($newImage, 0, 0, $transparent);
        }
        
        // טעינת התמונה המקורית
        switch ($mimeType) {
            case 'image/jpeg':
                $sourceImage = imagecreatefromjpeg($imagePath);
                break;
            case 'image/png':
                $sourceImage = imagecreatefrompng($imagePath);
                break;
            case 'image/gif':
                $sourceImage = imagecreatefromgif($imagePath);
                break;
            default:
                return false;
        }
        
        if (!$sourceImage) {
            return false;
        }
        
        // שינוי גודל
        imagecopyresampled($newImage, $sourceImage, 0, 0, 0, 0, 
                          $newWidth, $newHeight, $originalWidth, $originalHeight);
        
        // שמירת התמונה החדשה
        $success = false;
        switch ($mimeType) {
            case 'image/jpeg':
                $success = imagejpeg($newImage, $imagePath, 90);
                break;
            case 'image/png':
                $success = imagepng($newImage, $imagePath, 9);
                break;
            case 'image/gif':
                $success = imagegif($newImage, $imagePath);
                break;
        }
        
        // ניקוי זיכרון
        imagedestroy($sourceImage);
        imagedestroy($newImage);
        
        return $success;
        
    } catch (Exception $e) {
        error_log("Logo resize error: " . $e->getMessage());
        return false;
    }
}

/**
 * קבלת נתוני חברה עם מידע נוסף
 */
function getCompanyWithStats($companyId, $db) {
    try {
        $company = $db->fetchOne("
            SELECT 
                c.*,
                (SELECT COUNT(*) FROM users WHERE company_id = c.id AND status = 'active') as active_users,
                (SELECT COUNT(*) FROM users WHERE company_id = c.id) as total_users,
                (SELECT COUNT(*) FROM worksites WHERE company_id = c.id AND status = 'active') as active_sites,
                (SELECT COUNT(*) FROM worksites WHERE company_id = c.id) as total_sites,
                (SELECT COUNT(*) FROM contractors WHERE company_id = c.id AND status = 'active') as active_contractors,
                (SELECT COUNT(*) FROM deficiencies WHERE company_id = c.id AND status = 'open') as open_deficiencies,
                CASE 
                    WHEN c.expires_at IS NULL THEN 'unlimited'
                    WHEN c.expires_at < NOW() THEN 'expired'
                    WHEN c.expires_at < DATE_ADD(NOW(), INTERVAL 30 DAY) THEN 'expiring_soon'
                    ELSE 'active'
                END as subscription_status,
                DATEDIFF(c.expires_at, NOW()) as days_to_expiry
            FROM companies c 
            WHERE c.id = ?
        ", [$companyId]);
        
        if ($company) {
            // פענוח הגדרות JSON
            $company['settings_decoded'] = !empty($company['settings']) ? 
                json_decode($company['settings'], true) : [];
        }
        
        return $company;
        
    } catch (Exception $e) {
        error_log("Get company stats error: " . $e->getMessage());
        return null;
    }
}

/**
 * עדכון הגדרות חברה
 */
function updateCompanySettings($companyId, $settings, $db) {
    try {
        $settingsJson = json_encode($settings);
        
        $result = $db->update('companies', 
            ['settings' => $settingsJson], 
            'id = ?', 
            [$companyId]
        );
        
        return $result;
        
    } catch (Exception $e) {
        error_log("Update company settings error: " . $e->getMessage());
        return false;
    }
}

/**
 * בדיקה האם חברה חרגה ממגבלות השימוש
 */
function checkCompanyLimits($companyId, $db) {
    try {
        $company = $db->fetchOne("
            SELECT 
                max_users, 
                max_sites,
                (SELECT COUNT(*) FROM users WHERE company_id = ? AND status = 'active') as current_users,
                (SELECT COUNT(*) FROM worksites WHERE company_id = ? AND status = 'active') as current_sites
            FROM companies 
            WHERE id = ?
        ", [$companyId, $companyId, $companyId]);
        
        if (!$company) {
            return ['users_exceeded' => false, 'sites_exceeded' => false];
        }
        
        $limits = [
            'users_exceeded' => $company['max_users'] > 0 && $company['current_users'] > $company['max_users'],
            'sites_exceeded' => $company['max_sites'] > 0 && $company['current_sites'] > $company['max_sites'],
            'current_users' => $company['current_users'],
            'max_users' => $company['max_users'],
            'current_sites' => $company['current_sites'], 
            'max_sites' => $company['max_sites']
        ];
        
        return $limits;
        
    } catch (Exception $e) {
        error_log("Check company limits error: " . $e->getMessage());
        return ['users_exceeded' => false, 'sites_exceeded' => false];
    }
}

/**
 * מחיקת לוגו חברה
 */
function deleteCompanyLogo($logoPath) {
    if (!empty($logoPath)) {
        $fullPath = __DIR__ . '/../../../' . ltrim($logoPath, '/');
        if (file_exists($fullPath)) {
            return unlink($fullPath);
        }
    }
    return true;
}

/**
 * יצירת קובץ הגדרות ברירת מחדל לחברה
 */
function getDefaultCompanySettings($subscriptionPlan = 'basic') {
    $settings = [
        'notifications' => [
            'email_reports' => true,
            'sms_alerts' => false,
            'deficiency_notifications' => true,
            'inspection_reminders' => true,
            'expiry_alerts' => true
        ],
        'features' => [
            'advanced_reports' => in_array($subscriptionPlan, ['premium', 'enterprise']),
            'mobile_app_access' => true,
            'api_access' => $subscriptionPlan === 'enterprise',
            'custom_forms' => in_array($subscriptionPlan, ['standard', 'premium', 'enterprise']),
            'bulk_operations' => in_array($subscriptionPlan, ['premium', 'enterprise']),
            'data_export' => true
        ],
        'branding' => [
            'custom_logo' => in_array($subscriptionPlan, ['premium', 'enterprise']),
            'custom_colors' => $subscriptionPlan === 'enterprise',
            'white_label' => $subscriptionPlan === 'enterprise'
        ],
        'security' => [
            'two_factor_required' => false,
            'password_complexity' => 'medium',
            'session_timeout' => 480, // 8 hours
            'ip_restrictions' => []
        ]
    ];
    
    return $settings;
}

/**
 * קבלת רשימת חברות לרשימה נפתחת
 */
function getCompaniesForSelect($db, $userRole = 'worker', $companyId = null) {
    try {
        $whereClause = '';
        $params = [];
        
        if ($userRole !== 'super_admin' && $companyId) {
            $whereClause = 'WHERE (id = ? OR parent_company_id = ?) AND status = "active"';
            $params = [$companyId, $companyId];
        } else {
            $whereClause = 'WHERE status = "active"';
        }
        
        $companies = $db->fetchAll("
            SELECT id, name, company_type 
            FROM companies 
            {$whereClause}
            ORDER BY 
                CASE company_type 
                    WHEN 'main' THEN 1 
                    ELSE 2 
                END,
                name ASC
        ", $params);
        
        return $companies;
        
    } catch (Exception $e) {
        error_log("Get companies for select error: " . $e->getMessage());
        return [];
    }
}
