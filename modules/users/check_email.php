<?php
/**
 * WorkSafety.io - API לבדיקת קיום אימייל
 * בדיקה אם כתובת אימייל כבר קיימת במערכת
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

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

require_once '../../../config/database.php';

try {
    // קבלת נתוני הבקשה
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        throw new Exception('Invalid JSON data');
    }
    
    $email = trim($input['email'] ?? '');
    $excludeUserId = intval($input['exclude_user_id'] ?? 0);
    
    // ולידציה בסיסית
    if (empty($email)) {
        echo json_encode([
            'exists' => false,
            'message' => 'כתובת אימייל ריקה'
        ]);
        exit;
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode([
            'exists' => false,
            'valid' => false,
            'message' => 'כתובת אימייל לא תקינה'
        ]);
        exit;
    }
    
    $db = getDB();
    
    // בנייה שאילתה
    $sql = "SELECT id, email FROM users WHERE email = ?";
    $params = [$email];
    
    // אם יש ID למשתמש לא לכלול אותו (לעריכה)
    if ($excludeUserId > 0) {
        $sql .= " AND id != ?";
        $params[] = $excludeUserId;
    }
    
    $existingUser = $db->fetch($sql, $params);
    
    if ($existingUser) {
        echo json_encode([
            'exists' => true,
            'valid' => true,
            'message' => 'כתובת האימייל כבר קיימת במערכת',
            'user_id' => $existingUser['id']
        ]);
    } else {
        echo json_encode([
            'exists' => false,
            'valid' => true,
            'message' => 'כתובת האימייל זמינה'
        ]);
    }
    
} catch (Exception $e) {
    error_log("Check email API error: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'error' => true,
        'message' => 'שגיאה בבדיקת האימייל'
    ]);
}
?>
