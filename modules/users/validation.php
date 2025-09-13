<?php
/**
 * WorkSafety.io - User Validation Functions
 * פונקציות בדיקות תקינות ואימות נתונים למודול המשתמשים
 */

/**
 * אימות נתוני משתמש חדש או עדכון
 */
function validateUserData($data, $isUpdate = false, $userId = null) {
    $errors = [];
    
    // בדיקת שם פרטי
    if (empty($data['first_name'])) {
        $errors['first_name'] = 'שם פרטי הוא שדה חובה';
    } elseif (strlen($data['first_name']) < 2) {
        $errors['first_name'] = 'שם פרטי חייב להכיל לפחות 2 תווים';
    } elseif (strlen($data['first_name']) > 50) {
        $errors['first_name'] = 'שם פרטי לא יכול להכיל יותר מ-50 תווים';
    } elseif (!preg_match('/^[\p{Hebrew}\p{Latin}\s\-\'\.]+$/u', $data['first_name'])) {
        $errors['first_name'] = 'שם פרטי יכול להכיל רק אותיות, רווחים, מקפים ונקודות';
    }
    
    // בדיקת שם משפחה
    if (empty($data['last_name'])) {
        $errors['last_name'] = 'שם משפחה הוא שדה חובה';
    } elseif (strlen($data['last_name']) < 2) {
        $errors['last_name'] = 'שם משפחה חייב להכיל לפחות 2 תווים';
    } elseif (strlen($data['last_name']) > 50) {
        $errors['last_name'] = 'שם משפחה לא יכול להכיל יותר מ-50 תווים';
    } elseif (!preg_match('/^[\p{Hebrew}\p{Latin}\s\-\'\.]+$/u', $data['last_name'])) {
        $errors['last_name'] = 'שם משפחה יכול להכיל רק אותיות, רווחים, מקפים ונקודות';
    }
    
    // בדיקת אימייל
    if (empty($data['email'])) {
        $errors['email'] = 'כתובת אימייל היא שדה חובה';
    } elseif (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'כתובת אימייל לא תקינה';
    } elseif (strlen($data['email']) > 100) {
        $errors['email'] = 'כתובת אימייל לא יכולה להכיל יותר מ-100 תווים';
    } else {
        // בדיקת ייחודיות אימייל
        $emailExists = checkEmailExists($data['email'], $userId);
        if ($emailExists) {
            $errors['email'] = 'כתובת אימייל זו כבר קיימת במערכת';
        }
    }
    
    // בדיקת סיסמה (רק אם מדובר במשתמש חדש או אם סיסמה סופקה)
    if (!$isUpdate || !empty($data['password'])) {
        $passwordValidation = validateUserPassword($data['password'] ?? '');
        if (!$passwordValidation['valid']) {
            $errors['password'] = implode(', ', $passwordValidation['errors']);
        }
        
        // בדיקת אישור סיסמה
        if (isset($data['password_confirm']) && $data['password'] !== $data['password_confirm']) {
            $errors['password_confirm'] = 'אישור הסיסמה אינו תואם';
        }
    }
    
    // בדיקת טלפון (אופציונלי)
    if (!empty($data['phone'])) {
        $phoneValidation = validateUserPhone($data['phone']);
        if (!$phoneValidation['valid']) {
            $errors['phone'] = $phoneValidation['error'];
        }
    }
    
    // בדיקת תפקיד
    if (empty($data['role'])) {
        $errors['role'] = 'תפקיד הוא שדה חובה';
    } elseif (!isValidUserRole($data['role'])) {
        $errors['role'] = 'תפקיד לא תקין';
    }
    
    // בדיקת חברה (תלוי בתפקיד)
    if (isset($data['company_id'])) {
        $companyValidation = validateUserCompany($data['company_id'], $data['role']);
        if (!$companyValidation['valid']) {
            $errors['company_id'] = $companyValidation['error'];
        }
    }
    
    // בדיקת מחלקה (אופציונלי)
    if (!empty($data['department']) && strlen($data['department']) > 100) {
        $errors['department'] = 'שם המחלקה לא יכול להכיל יותר מ-100 תווים';
    }
    
    // בדיקת תואר (אופציונלי)
    if (!empty($data['job_title']) && strlen($data['job_title']) > 100) {
        $errors['job_title'] = 'התואר לא יכול להכיל יותר מ-100 תווים';
    }
    
    // בדיקת סטטוס
    if (isset($data['status']) && !isValidUserStatus($data['status'])) {
        $errors['status'] = 'סטטוס לא תקין';
    }
    
    // בדיקת הערות (אופציונלי)
    if (!empty($data['notes']) && strlen($data['notes']) > 1000) {
        $errors['notes'] = 'ההערות לא יכולות להכיל יותר מ-1000 תווים';
    }
    
    return [
        'valid' => empty($errors),
        'errors' => $errors,
        'message' => empty($errors) ? 'הנתונים תקינים' : 'נמצאו שגיאות בנתונים'
    ];
}

/**
 * בדיקת תקינות סיסמה
 */
function validateUserPassword($password) {
    $errors = [];
    
    if (empty($password)) {
        $errors[] = 'סיסמה היא שדה חובה';
        return ['valid' => false, 'errors' => $errors];
    }
    
    // בדיקת אורך מינימלי
    if (strlen($password) < 8) {
        $errors[] = 'הסיסמה חייבת להכיל לפחות 8 תווים';
    }
    
    // בדיקת אורך מקסימלי
    if (strlen($password) > 128) {
        $errors[] = 'הסיסמה לא יכולה להכיל יותר מ-128 תווים';
    }
    
    // בדיקת אות גדולה
    if (!preg_match('/[A-Z]/', $password)) {
        $errors[] = 'הסיסמה חייבת להכיל לפחות אות גדולה אחת';
    }
    
    // בדיקת אות קטנה
    if (!preg_match('/[a-z]/', $password)) {
        $errors[] = 'הסיסמה חייבת להכיל לפחות אות קטנה אחת';
    }
    
    // בדיקת ספרה
    if (!preg_match('/[0-9]/', $password)) {
        $errors[] = 'הסיסמה חייבת להכיל לפחות ספרה אחת';
    }
    
    // בדיקת תו מיוחד
    if (!preg_match('/[^A-Za-z0-9]/', $password)) {
        $errors[] = 'הסיסמה חייבת להכיל לפחות תו מיוחד אחד';
    }
    
    // בדיקת סיסמאות נפוצות
    if (isCommonPassword($password)) {
        $errors[] = 'הסיסמה שבחרת נפוצה מדי, נא לבחור סיסמה אחרת';
    }
    
    // בדיקת תווים רצופים
    if (hasSequentialChars($password)) {
        $errors[] = 'הסיסמה לא יכולה להכיל תווים רצופים כמו 123 או abc';
    }
    
    // בדיקת תווים חוזרים
    if (hasRepeatingChars($password)) {
        $errors[] = 'הסיסמה לא יכולה להכיל יותר מדי תווים חוזרים';
    }
    
    return [
        'valid' => empty($errors),
        'errors' => $errors
    ];
}

/**
 * בדיקת תקינות מספר טלפון
 */
function validateUserPhone($phone) {
    if (empty($phone)) {
        return ['valid' => true]; // טלפון אופציונלי
    }
    
    // ניקוי הטלפון
    $cleanPhone = preg_replace('/[\s\-\(\)]/', '', $phone);
    
    // פורמטים תקינים לישראל
    $validPatterns = [
        '/^0[2-9]\d{7,8}$/',           // מספר רגיל
        '/^05[0-9]\d{7}$/',            // סלולרי
        '/^\+972[2-9]\d{7,8}$/',       // עם קידומת בינלאומית
        '/^972[2-9]\d{7,8}$/',         // בלי פלוס
        '/^1\-?800\-?\d{3}\-?\d{3,4}$/' // מספר חינם
    ];
    
    foreach ($validPatterns as $pattern) {
        if (preg_match($pattern, $cleanPhone)) {
            return ['valid' => true];
        }
    }
    
    return [
        'valid' => false,
        'error' => 'מספר הטלפון אינו תקין (נא להכניס מספר ישראלי תקין)'
    ];
}

/**
 * בדיקת תקינות תפקיד
 */
function isValidUserRole($role) {
    $validRoles = [
        'super_admin',
        'company_admin', 
        'contractor',
        'safety_manager',
        'inspector',
        'worker'
    ];
    
    return in_array($role, $validRoles);
}

/**
 * בדיקת תקינות סטטוס
 */
function isValidUserStatus($status) {
    $validStatuses = [
        'active',
        'inactive',
        'pending',
        'deleted'
    ];
    
    return in_array($status, $validStatuses);
}

/**
 * בדיקת תקינות שיוך לחברה
 */
function validateUserCompany($companyId, $role) {
    // מנהל ראשי לא חייב להיות משויך לחברה
    if ($role === 'super_admin') {
        return ['valid' => true];
    }
    
    if (empty($companyId)) {
        return [
            'valid' => false,
            'error' => 'חברה היא שדה חובה עבור תפקיד זה'
        ];
    }
    
    // בדיקה שהחברה קיימת ופעילה
    try {
        $db = getDB();
        $company = $db->fetch("SELECT id, status FROM companies WHERE id = ?", [$companyId]);
        
        if (!$company) {
            return [
                'valid' => false,
                'error' => 'החברה שנבחרה לא קיימת'
            ];
        }
        
        if ($company['status'] !== 'active') {
            return [
                'valid' => false,
                'error' => 'החברה שנבחרה אינה פעילה'
            ];
        }
        
        return ['valid' => true];
        
    } catch (Exception $e) {
        error_log("Error validating company: " . $e->getMessage());
        return [
            'valid' => false,
            'error' => 'שגיאה בבדיקת החברה'
        ];
    }
}

/**
 * בדיקת קיום אימייל במערכת
 */
function checkEmailExists($email, $excludeUserId = null) {
    try {
        $db = getDB();
        
        $sql = "SELECT id FROM users WHERE email = ? AND status != 'deleted'";
        $params = [$email];
        
        if ($excludeUserId) {
            $sql .= " AND id != ?";
            $params[] = $excludeUserId;
        }
        
        $result = $db->fetch($sql, $params);
        return !empty($result);
        
    } catch (Exception $e) {
        error_log("Error checking email exists: " . $e->getMessage());
        return false;
    }
}

/**
 * בדיקת סיסמאות נפוצות
 */
function isCommonPassword($password) {
    $commonPasswords = [
        'password', '123456', '123456789', 'qwerty', 'abc123',
        'password123', 'admin', '12345678', '1234567890',
        'letmein', 'welcome', 'monkey', 'dragon', 'pass',
        'mustang', 'master', 'hello', 'freedom', 'whatever',
        'qazwsx', 'trustno1', 'jordan23', 'harley', 'robert',
        'matthew', 'daniel', 'andrew', 'joshua', 'michelle',
        'סיסמה', '123456', 'שלום', 'ישראל', 'תלאביב'
    ];
    
    return in_array(strtolower($password), array_map('strtolower', $commonPasswords));
}

/**
 * בדיקת תווים רצופים בסיסמה
 */
function hasSequentialChars($password) {
    $sequences = [
        'abcdefghijklmnopqrstuvwxyz',
        'qwertyuiop',
        'asdfghjkl', 
        'zxcvbnm',
        '1234567890'
    ];
    
    $password = strtolower($password);
    
    foreach ($sequences as $sequence) {
        for ($i = 0; $i <= strlen($sequence) - 3; $i++) {
            $subseq = substr($sequence, $i, 3);
            if (strpos($password, $subseq) !== false) {
                return true;
            }
            // בדיקת רצף הפוך
            if (strpos($password, strrev($subseq)) !== false) {
                return true;
            }
        }
    }
    
    return false;
}

/**
 * בדיקת תווים חוזרים בסיסמה
 */
function hasRepeatingChars($password) {
    // בדיקת יותר מ-2 תווים זהים ברצף
    if (preg_match('/(.)\1{2,}/', $password)) {
        return true;
    }
    
    // בדיקת יותר מ-50% תווים זהים
    $chars = array_count_values(str_split(strtolower($password)));
    $maxCount = max($chars);
    $totalLength = strlen($password);
    
    return ($maxCount / $totalLength) > 0.5;
}

/**
 * בדיקת הרשאות לעריכת משתמש
 */
function canEditUser($targetUserId, $currentUser = null) {
    $currentUser = $currentUser ?? getCurrentUser();
    if (!$currentUser) {
        return false;
    }
    
    // מנהל ראשי יכול לערוך כל אחד
    if ($currentUser['role'] === 'super_admin') {
        return true;
    }
    
    // משתמש יכול לערוך את עצמו (מוגבל)
    if ($targetUserId == $currentUser['id']) {
        return true;
    }
    
    // מנהל חברה יכול לערוך משתמשים בחברה שלו
    if ($currentUser['role'] === 'company_admin' && $currentUser['company_id']) {
        $targetUser = getUserById($targetUserId);
        return $targetUser && $targetUser['company_id'] == $currentUser['company_id'];
    }
    
    return false;
}

/**
 * בדיקת הרשאות למחיקת משתמש
 */
function canDeleteUser($targetUserId, $currentUser = null) {
    $currentUser = $currentUser ?? getCurrentUser();
    if (!$currentUser) {
        return false;
    }
    
    // לא ניתן למחוק את עצמך
    if ($targetUserId == $currentUser['id']) {
        return false;
    }
    
    // מנהל ראשי יכול למחוק כל אחד (חוץ מעצמו)
    if ($currentUser['role'] === 'super_admin') {
        return true;
    }
    
    // מנהל חברה יכול למחוק משתמשים בחברה שלו (חוץ מעצמו)
    if ($currentUser['role'] === 'company_admin' && $currentUser['company_id']) {
        $targetUser = getUserById($targetUserId);
        return $targetUser && 
               $targetUser['company_id'] == $currentUser['company_id'] &&
               $targetUser['role'] !== 'super_admin';
    }
    
    return false;
}

/**
 * אימות נתוני התחברות
 */
function validateLoginData($data) {
    $errors = [];
    
    if (empty($data['email'])) {
        $errors['email'] = 'כתובת אימייל נדרשת';
    } elseif (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'כתובת אימייל לא תקינה';
    }
    
    if (empty($data['password'])) {
        $errors['password'] = 'סיסמה נדרשת';
    }
    
    return [
        'valid' => empty($errors),
        'errors' => $errors
    ];
}

/**
 * בדיקת מגבלות חברה
 */
function validateCompanyLimits($companyId, $action = 'add_user') {
    try {
        $db = getDB();
        
        // קבלת הגבלות החברה
        $company = $db->fetch("
            SELECT max_users, max_contractors, max_worksites 
            FROM companies 
            WHERE id = ?
        ", [$companyId]);
        
        if (!$company) {
            return [
                'valid' => false,
                'error' => 'חברה לא נמצאה'
            ];
        }
        
        switch ($action) {
            case 'add_user':
                if ($company['max_users'] > 0) {
                    $currentUsers = $db->fetch("
                        SELECT COUNT(*) as count 
                        FROM users 
                        WHERE company_id = ? AND status != 'deleted'
                    ", [$companyId]);
                    
                    if ($currentUsers['count'] >= $company['max_users']) {
                        return [
                            'valid' => false,
                            'error' => "הגעת למגבלת המשתמשים המקסימלית ({$company['max_users']})"
                        ];
                    }
                }
                break;
                
            case 'add_contractor':
                if ($company['max_contractors'] > 0) {
                    $currentContractors = $db->fetch("
                        SELECT COUNT(*) as count 
                        FROM contractors 
                        WHERE company_id = ? AND status != 'deleted'
                    ", [$companyId]);
                    
                    if ($currentContractors['count'] >= $company['max_contractors']) {
                        return [
                            'valid' => false,
                            'error' => "הגעת למגבלת הקבלנים המקסימלית ({$company['max_contractors']})"
                        ];
                    }
                }
                break;
                
            case 'add_worksite':
                if ($company['max_worksites'] > 0) {
                    $currentWorksites = $db->fetch("
                        SELECT COUNT(*) as count 
                        FROM worksites 
                        WHERE company_id = ? AND status != 'deleted'
                    ", [$companyId]);
                    
                    if ($currentWorksites['count'] >= $company['max_worksites']) {
                        return [
                            'valid' => false,
                            'error' => "הגעת למגבלת אתרי העבודה המקסימלית ({$company['max_worksites']})"
                        ];
                    }
                }
                break;
        }
        
        return ['valid' => true];
        
    } catch (Exception $e) {
        error_log("Error validating company limits: " . $e->getMessage());
        return [
            'valid' => false,
            'error' => 'שגיאה בבדיקת מגבלות החברה'
        ];
    }
}

/**
 * ניקוי וסניטציה של נתוני משתמש
 */
function sanitizeUserData($data) {
    $sanitized = [];
    
    // שדות טקסט רגילים
    $textFields = ['first_name', 'last_name', 'department', 'job_title'];
    foreach ($textFields as $field) {
        if (isset($data[$field])) {
            $sanitized[$field] = trim(strip_tags($data[$field]));
        }
    }
    
    // אימייל
    if (isset($data['email'])) {
        $sanitized['email'] = trim(strtolower(filter_var($data['email'], FILTER_SANITIZE_EMAIL)));
    }
    
    // טלפון
    if (isset($data['phone'])) {
        $sanitized['phone'] = preg_replace('/[^\d\+\-\(\)\s]/', '', trim($data['phone']));
    }
    
    // תפקיד וסטטוס - רק ערכים מותרים
    if (isset($data['role']) && isValidUserRole($data['role'])) {
        $sanitized['role'] = $data['role'];
    }
    
    if (isset($data['status']) && isValidUserStatus($data['status'])) {
        $sanitized['status'] = $data['status'];
    }
    
    // מזהי מספרים
    $numericFields = ['company_id'];
    foreach ($numericFields as $field) {
        if (isset($data[$field]) && is_numeric($data[$field])) {
            $sanitized[$field] = (int)$data[$field];
        }
    }
    
    // הערות - עם HTML מוגבל
    if (isset($data['notes'])) {
        $sanitized['notes'] = trim(strip_tags($data['notes'], '<br><p><strong><em>'));
    }
    
    // סיסמה - ללא סניטציה (רק אימות)
    if (isset($data['password'])) {
        $sanitized['password'] = $data['password'];
    }
    
    if (isset($data['password_confirm'])) {
        $sanitized['password_confirm'] = $data['password_confirm'];
    }
    
    return $sanitized;
}

/**
 * בדיקת חוזק סיסמה (ציון 0-100)
 */
function calculatePasswordStrength($password) {
    $score = 0;
    
    // אורך
    $length = strlen($password);
    if ($length >= 8) $score += 10;
    if ($length >= 12) $score += 10;
    if ($length >= 16) $score += 10;
    
    // סוגי תווים
    if (preg_match('/[a-z]/', $password)) $score += 10;
    if (preg_match('/[A-Z]/', $password)) $score += 10;
    if (preg_match('/[0-9]/', $password)) $score += 10;
    if (preg_match('/[^A-Za-z0-9]/', $password)) $score += 15;
    
    // מורכבות
    if (!hasRepeatingChars($password)) $score += 10;
    if (!hasSequentialChars($password)) $score += 10;
    if (!isCommonPassword($password)) $score += 15;
    
    return min(100, $score);
}

/**
 * קבלת הודעת חוזק סיסמה
 */
function getPasswordStrengthMessage($score) {
    if ($score < 30) {
        return ['level' => 'weak', 'message' => 'סיסמה חלשה', 'color' => '#ef4444'];
    } elseif ($score < 60) {
        return ['level' => 'medium', 'message' => 'סיסמה בינונית', 'color' => '#f59e0b'];
    } elseif ($score < 80) {
        return ['level' => 'strong', 'message' => 'סיסמה חזקה', 'color' => '#10b981'];
    } else {
        return ['level' => 'very_strong', 'message' => 'סיסמה חזקה מאוד', 'color' => '#059669'];
    }
}

/**
 * בדיקת תקינות קובץ העלאה (אווטר)
 */
function validateUserAvatar($file) {
    if (!isset($file['tmp_name']) || empty($file['tmp_name'])) {
        return ['valid' => true]; // אווטר אופציונלי
    }
    
    $errors = [];
    
    // בדיקת גודל קובץ (מקסימום 2MB)
    if ($file['size'] > 2 * 1024 * 1024) {
        $errors[] = 'גודל הקובץ לא יכול לעלות על 2MB';
    }
    
    // בדיקת סוג קובץ
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    if (!in_array($file['type'], $allowedTypes)) {
        $errors[] = 'סוג הקובץ לא נתמך. נא להעלות תמונה בפורמט JPG, PNG, GIF או WebP';
    }
    
    // בדיקת תקינות התמונה
    $imageInfo = getimagesize($file['tmp_name']);
    if (!$imageInfo) {
        $errors[] = 'הקובץ אינו תמונה תקינה';
    } else {
        // בדיקת רזולוציה
        list($width, $height) = $imageInfo;
        if ($width > 1000 || $height > 1000) {
            $errors[] = 'רזולוציית התמונה לא יכולה לעלות על 1000x1000 פיקסלים';
        }
        
        if ($width < 50 || $height < 50) {
            $errors[] = 'רזולוציית התמונה חייבת להיות לפחות 50x50 פיקסלים';
        }
    }
    
    return [
        'valid' => empty($errors),
        'errors' => $errors
    ];
}

/**
 * אימות נתוני יבוא משתמשים מקובץ
 */
function validateImportData($data) {
    $errors = [];
    $validRows = [];
    $invalidRows = [];
    
    foreach ($data as $rowIndex => $row) {
        $rowErrors = [];
        
        // בדיקת שדות חובה
        $requiredFields = ['first_name', 'last_name', 'email', 'role'];
        foreach ($requiredFields as $field) {
            if (empty($row[$field])) {
                $rowErrors[] = "שדה {$field} חסר";
            }
        }
        
        // אימות כל שדה
        if (!empty($row['email']) && !filter_var($row['email'], FILTER_VALIDATE_EMAIL)) {
            $rowErrors[] = 'כתובת אימייל לא תקינה';
        }
        
        if (!empty($row['role']) && !isValidUserRole($row['role'])) {
            $rowErrors[] = 'תפקיד לא תקין';
        }
        
        if (!empty($row['phone'])) {
            $phoneValidation = validateUserPhone($row['phone']);
            if (!$phoneValidation['valid']) {
                $rowErrors[] = $phoneValidation['error'];
            }
        }
        
        if (empty($rowErrors)) {
            $validRows[] = $row;
        } else {
            $invalidRows[] = [
                'row' => $rowIndex + 1,
                'data' => $row,
                'errors' => $rowErrors
            ];
        }
    }
    
    return [
        'valid' => empty($invalidRows),
        'total_rows' => count($data),
        'valid_rows' => $validRows,
        'invalid_rows' => $invalidRows,
        'summary' => [
            'total' => count($data),
            'valid' => count($validRows),
            'invalid' => count($invalidRows)
        ]
    ];
}
?>
