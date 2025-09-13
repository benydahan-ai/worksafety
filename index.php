<?php
/**
 * WorkSafety.io - דף הבית הראשי
 * ניתוב ראשוני למערכת
 */

// הפעלת session
session_start();

// בדיקה אם המשתמש מחובר
if (!isset($_SESSION['user_id'])) {
    // אם לא מחובר - הפניה לדף התחברות
    header('Location: /login.php');
    exit;
}

// אם מחובר - הצגת הדשבורד
require_once 'dashboard.php';
?>
