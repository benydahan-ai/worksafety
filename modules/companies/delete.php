<?php
/**
 * WorkSafety.io - מחיקת חברה
 * מחיקה בטוחה של חברה עם בדיקות תלויות
 */

// כלילת קבצים נדרשים
require_once '../../includes/header.php';
require_once 'includes/functions.php';

// וידוא התחברות והרשאות
if (!isset($_SESSION['user_id'])) {
    header('Location: /login.php');
    exit;
}

// בדיקת הרשאות - רק מנהל ראשי יכול למחוק חברות
$userRole = $currentUser['role'] ?? 'worker';
if ($userRole !== 'super_admin') {
    $_SESSION['flash_message']['danger'] = 'אין לך הרשאה לבצע פעולה זו';
    header('Location: index.php');
    exit;
}

// וידוא שהבקשה היא POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['flash_message']['danger'] = 'בקשה לא תקינה';
    header('Location: index.php');
    exit;
}

// קבלת ID החברה
$companyId = intval($_POST['company_id'] ?? 0);
if (!$companyId) {
    $_SESSION['flash_message']['danger'] = 'לא נמצאה חברה מתאימה';
    header('Location: index.php');
    exit;
}

try {
    $db = getDB();
    
    // קבלת נתוני החברה
    $company = $db->fetchOne("SELECT * FROM companies WHERE id = ?", [$companyId]);
    
    if (!$company) {
        $_SESSION['flash_message']['danger'] = 'החברה לא נמצאה';
        header('Location: index.php');
        exit;
    }
    
    // אין לאפשר מחיקה של חברה ראשית
    if ($company['company_type'] === 'main') {
        $_SESSION['flash_message']['danger'] = 'לא ניתן למחוק חברה ראשית';
        header('Location: index.php');
        exit;
    }
    
    // בדיקת תלויות לפני מחיקה
    $dependencies = [];
    
    // בדיקת משתמשים
    $userCount = $db->fetchOne("SELECT COUNT(*) as count FROM users WHERE company_id = ?", [$companyId]);
    if ($userCount['count'] > 0) {
        $dependencies[] = "משתמשים ({$userCount['count']})";
    }
    
    // בדיקת אתרי עבודה
    $siteCount = $db->fetchOne("SELECT COUNT(*) as count FROM worksites WHERE company_id = ?", [$companyId]);
    if ($siteCount['count'] > 0) {
        $dependencies[] = "אתרי עבודה ({$siteCount['count']})";
    }
    
    // בדיקת קבלנים
    $contractorCount = $db->fetchOne("SELECT COUNT(*) as count FROM contractors WHERE company_id = ?", [$companyId]);
    if ($contractorCount['count'] > 0) {
        $dependencies[] = "קבלנים ({$contractorCount['count']})";
    }
    
    // בדיקת ליקויים
    $deficiencyCount = $db->fetchOne("SELECT COUNT(*) as count FROM deficiencies WHERE company_id = ?", [$companyId]);
    if ($deficiencyCount['count'] > 0) {
        $dependencies[] = "ליקויים ({$deficiencyCount['count']})";
    }
    
    // בדיקת בדיקות
    $inspectionCount = $db->fetchOne("SELECT COUNT(*) as count FROM inspections WHERE company_id = ?", [$companyId]);
    if ($inspectionCount['count'] > 0) {
        $dependencies[] = "בדיקות ({$inspectionCount['count']})";
    }
    
    // אם יש תלויות - מנע מחיקה
    if (!empty($dependencies)) {
        $_SESSION['flash_message']['danger'] = 
            'לא ניתן למחוק את החברה מכיוון שיש נתונים קשורים אליה: ' . 
            implode(', ', $dependencies) . '. נא למחוק תחילה את כל הנתונים הקשורים.';
        header('Location: view.php?id=' . $companyId);
        exit;
    }
    
    // התחלת טרנזקציה
    $db->getConnection()->beginTransaction();
    
    try {
        // מחיקת הלוגו (אם קיים)
        if (!empty($company['logo'])) {
            deleteCompanyLogo($company['logo']);
        }
        
        // מחיקת החברה
        $deleted = $db->delete('companies', 'id = ?', [$companyId]);
        
        if ($deleted) {
            // אישור הטרנזקציה
            $db->getConnection()->commit();
            
            // רישום פעילות במערכת
            error_log("Company deleted: ID {$companyId}, Name: {$company['name']}, By user: {$_SESSION['user_id']}");
            
            $_SESSION['flash_message']['success'] = "החברה '{$company['name']}' נמחקה בהצלחה";
            header('Location: index.php');
            exit;
            
        } else {
            throw new Exception('שגיאה במחיקת החברה');
        }
        
    } catch (Exception $e) {
        // ביטול הטרנזקציה
        $db->getConnection()->rollBack();
        throw $e;
    }
    
} catch (Exception $e) {
    error_log("Delete company error: " . $e->getMessage());
    $_SESSION['flash_message']['danger'] = 'שגיאה במחיקת החברה: ' . $e->getMessage();
    header('Location: index.php');
    exit;
}
