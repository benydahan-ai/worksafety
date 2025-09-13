<?php
/**
 * WorkSafety.io - דף התנתקות
 * ניקוי session והפניה לדף התחברות
 */

session_start();

// ניקוי כל נתוני ה-session
$_SESSION = array();

// מחיקת cookie של ה-session אם קיים
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// הרס ה-session
session_destroy();

// הפניה לדף התחברות
header('Location: /login.php');
exit;
?>
