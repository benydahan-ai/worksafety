<?php
/**
 * WorkSafety.io - מערכת ניהול בטיחות תעשייתית
 * קובץ הגדרות מסד נתונים מתוקן - גרסה 1.2
 * 
 * @author WorkSafety.io Development Team
 * @version 1.2
 * @date 2025
 */

// הגדרת אזור זמן ישראל בתחילת הקובץ
date_default_timezone_set('Asia/Jerusalem');

// הגדרות מסד נתונים
define('DB_HOST', 'localhost');
define('DB_NAME', 'atarimst_worksafetydb');
define('DB_USER', 'atarimst_rootadmin');
define('DB_PASS', 'Shani@2025');
define('DB_CHARSET', 'utf8mb4');

// הגדרות מערכת
define('SITE_URL', 'https://worksafety.io');
define('SITE_NAME', 'WorkSafety.io');
define('SITE_DESCRIPTION', 'מערכת ניהול בטיחות תעשייתית מתקדמת');

// הגדרות אבטחה
define('SESSION_LIFETIME', 28800); // 8 שעות
define('PASSWORD_MIN_LENGTH', 8);
define('MAX_LOGIN_ATTEMPTS', 5);

// פונקציה לבדיקה וקביעת הגדרות session (רק אם session לא פעיל)
function initializeSessionSettings() {
    if (session_status() === PHP_SESSION_NONE) {
        // הגדרות session רק אם session עדיין לא פעיל
        ini_set('session.cookie_httponly', '1');
        ini_set('session.cookie_secure', '1');
        ini_set('session.use_strict_mode', '1');
        ini_set('session.cookie_samesite', 'Strict');
        ini_set('session.gc_maxlifetime', SESSION_LIFETIME);
        ini_set('session.cookie_lifetime', SESSION_LIFETIME);
        session_start();
    }
}

/**
 * יצירת חיבור למסד הנתונים עם PDO
 */
class Database {
    private static $instance = null;
    private $connection;
    
    private function __construct() {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
            ];
            
            $this->connection = new PDO($dsn, DB_USER, DB_PASS, $options);
            
            // הגדרת אזור זמן במסד הנתונים - עדכון מתקדם יותר
            try {
                // בדיקת אזור זמן נוכחי ישראל
                $now = new DateTime('now', new DateTimeZone('Asia/Jerusalem'));
                $offset = $now->format('P'); // מחזיר את ההפרש כמו +03:00 או +02:00
                
                // הגדרת אזור זמן בהתאם לעונה
                $this->connection->exec("SET time_zone = '{$offset}'");
                
            } catch (Exception $timezone_error) {
                // אם יש בעיה עם הגדרת אזור זמן, השתמש בברירת מחדל
                error_log("Warning: Could not set MySQL timezone: " . $timezone_error->getMessage());
                try {
                    $this->connection->exec("SET time_zone = '+03:00'"); // ברירת מחדל לישראל
                } catch (Exception $fallback_error) {
                    // אם גם זה לא עובד, נמשיך בלי הגדרת אזור זמן
                    error_log("Warning: Could not set fallback timezone: " . $fallback_error->getMessage());
                }
            }
            
        } catch (PDOException $e) {
            error_log("Database connection error: " . $e->getMessage());
            die("שגיאה בחיבור למסד הנתונים. אנא נסה שוב מאוחר יותר.");
        }
    }
    
    /**
     * קבלת instance יחיד של החיבור
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * קבלת החיבור למסד הנתונים
     */
    public function getConnection() {
        return $this->connection;
    }
    
    /**
     * בדיקת חיבור פשוטה
     */
    public function testConnection() {
        try {
            $stmt = $this->connection->query("SELECT VERSION() as version, DATABASE() as database_name, USER() as db_user, @@session.time_zone as timezone");
            return $stmt->fetch();
        } catch (PDOException $e) {
            throw new Exception("שגיאה בבדיקת החיבור: " . $e->getMessage());
        }
    }
    
    /**
     * ביצוע שאילתה פשוטה
     */
    public function query($sql, $params = []) {
        try {
            $stmt = $this->connection->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            error_log("Database query error: " . $e->getMessage());
            throw new Exception("שגיאה בביצוע השאילתה");
        }
    }
    
    /**
     * קבלת רשומה אחת
     */
    public function fetch($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->fetch();
    }
    
    /**
     * קבלת כל הרשומות
     */
    public function fetchAll($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->fetchAll();
    }
    
    /**
     * הכנסת רשומה חדשה
     */
    public function insert($table, $data) {
        $columns = implode(',', array_keys($data));
        $placeholders = ':' . implode(', :', array_keys($data));
        
        $sql = "INSERT INTO {$table} ({$columns}) VALUES ({$placeholders})";
        $this->query($sql, $data);
        
        return $this->connection->lastInsertId();
    }
    
    /**
     * עדכון רשומה
     */
    public function update($table, $data, $where, $whereParams = []) {
        $set = [];
        foreach ($data as $key => $value) {
            $set[] = "{$key} = :{$key}";
        }
        $setString = implode(', ', $set);
        
        $sql = "UPDATE {$table} SET {$setString} WHERE {$where}";
        $params = array_merge($data, $whereParams);
        
        return $this->query($sql, $params);
    }
    
    /**
     * מחיקת רשומה
     */
    public function delete($table, $where, $params = []) {
        $sql = "DELETE FROM {$table} WHERE {$where}";
        return $this->query($sql, $params);
    }
}

// יצירת instance גלובלי
$db = Database::getInstance();

// פונקציות עזר גלובליות
function getDB() {
    return Database::getInstance();
}

function getCurrentDateTime() {
    return date('Y-m-d H:i:s');
}

function getCurrentDate() {
    return date('Y-m-d');
}

function formatHebrewDate($date) {
    $timestamp = strtotime($date);
    return date('d/m/Y', $timestamp);
}

function formatHebrewDateTime($datetime) {
    $timestamp = strtotime($datetime);
    return date('d/m/Y H:i', $timestamp);
}

/**
 * פונקציית עזר לבדיקת זמן ישראל
 */
function getIsraelTime() {
    $israel_tz = new DateTimeZone('Asia/Jerusalem');
    $israel_time = new DateTime('now', $israel_tz);
    return $israel_time;
}

/**
 * פונקציית עזר לפורמט תאריך עברי מלא
 */
function formatFullHebrewDate($date = null) {
    if (!$date) {
        $date = getCurrentDateTime();
    }
    
    try {
        $israel_time = new DateTime($date, new DateTimeZone('Asia/Jerusalem'));
        
        $months_hebrew = [
            1 => 'ינואר', 2 => 'פברואר', 3 => 'מרץ', 4 => 'אפריל',
            5 => 'מאי', 6 => 'יוני', 7 => 'יולי', 8 => 'אוגוסט',
            9 => 'ספטמבר', 10 => 'אוקטובר', 11 => 'נובמבר', 12 => 'דצמבר'
        ];
        
        $day = $israel_time->format('j');
        $month = $months_hebrew[(int)$israel_time->format('n')];
        $year = $israel_time->format('Y');
        $time = $israel_time->format('H:i');
        
        return "{$day} ב{$month} {$year}, {$time}";
        
    } catch (Exception $e) {
        // במקרה של שגיאה, השתמש בפורמט פשוט
        return date('d/m/Y H:i');
    }
}

/**
 * פונקציית עזר לבדיקת שעון קיץ/חורף בישראל
 */
function isIsraelDST($date = null) {
    if (!$date) $date = 'now';
    
    $israel_tz = new DateTimeZone('Asia/Jerusalem');
    $israel_time = new DateTime($date, $israel_tz);
    
    // בישראל שעון קיץ מסתיים בדרך כלל באוקטובר ומתחיל במרץ
    return $israel_time->format('I') == '1'; // 1 = DST, 0 = Standard Time
}

/**
 * פונקציית עזר לקבלת offset נוכחי של ישראל
 */
function getIsraelTimezoneOffset() {
    $israel_tz = new DateTimeZone('Asia/Jerusalem');
    $israel_time = new DateTime('now', $israel_tz);
    return $israel_time->format('P'); // מחזיר +03:00 או +02:00
}

// בדיקה שהכל עובד תקין
try {
    $test_connection = getDB()->testConnection();
    // אם הגעת עד כאן - הכל עובד!
} catch (Exception $e) {
    error_log("Database configuration error: " . $e->getMessage());
}
