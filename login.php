<?php
/**
 * WorkSafety.io - דף התחברות מתוקן
 * גרסה 2.1 - תיקון SQL query בהתאם למבנה הטבלאות
 */

// הגדרת אזור זמן ישראל לפני הכל
date_default_timezone_set('Asia/Jerusalem');

// בדיקה אם session כבר פעיל לפני הפעלתו
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// כלילת קובץ הגדרות מסד הנתונים
require_once 'config/database.php';

// בדיקה אם המשתמש כבר מחובר
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

$error_message = '';
$login_attempts = 0;

// טיפול בהתחברות
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($email) || empty($password)) {
        $error_message = 'נא למלא את כל השדות הנדרשים';
    } else {
        try {
            $db = Database::getInstance();
            $pdo = $db->getConnection();
            
            // בדיקה שהטבלאות קיימות
            $stmt = $pdo->query("SHOW TABLES LIKE 'users'");
            if (!$stmt->fetch()) {
                $error_message = 'מסד הנתונים לא מוכן. נא להריץ את setup_complete.php תחילה';
            } else {
                // קודם בואו נבדוק איך נראית טבלת companies
                $company_columns = [];
                try {
                    $stmt = $pdo->query("SHOW COLUMNS FROM companies");
                    while ($column = $stmt->fetch()) {
                        $company_columns[] = $column['Field'];
                    }
                } catch (Exception $e) {
                    // אם טבלת companies לא קיימת, נמשיך בלי
                    $company_columns = [];
                }
                
                // בניית שאילתה דינמית בהתאם לעמודות הקיימות
                $selectFields = "u.id, u.full_name, u.email, u.password, u.company_id";
                
                // בדיקה אם שדה role קיים בטבלת users
                $user_columns = [];
                try {
                    $stmt = $pdo->query("SHOW COLUMNS FROM users");
                    while ($column = $stmt->fetch()) {
                        $user_columns[] = $column['Field'];
                    }
                    
                    if (in_array('role', $user_columns)) {
                        $selectFields .= ", u.role";
                    }
                    if (in_array('status', $user_columns)) {
                        $selectFields .= ", u.status";
                    }
                } catch (Exception $e) {
                    // במקרה של שגיאה, נמשיך עם השדות הבסיסיים
                }
                
                // הוספת שדות מטבלת companies בהתאם לקיום
                if (!empty($company_columns)) {
                    // נבדוק איזה שם עמודה קיים עבור שם החברה
                    if (in_array('company_name', $company_columns)) {
                        $selectFields .= ", COALESCE(c.company_name, 'החברה שלי') as company_name";
                    } elseif (in_array('name', $company_columns)) {
                        $selectFields .= ", COALESCE(c.name, 'החברה שלי') as company_name";
                    } else {
                        $selectFields .= ", 'החברה שלי' as company_name";
                    }
                    
                    // בדיקת company_type
                    if (in_array('company_type', $company_columns)) {
                        $selectFields .= ", COALESCE(c.company_type, 'client') as company_type";
                    } else {
                        $selectFields .= ", 'client' as company_type";
                    }
                    
                    // בדיקת סטטוס חברה
                    if (in_array('status', $company_columns)) {
                        $selectFields .= ", COALESCE(c.status, 'active') as company_status";
                    } else {
                        $selectFields .= ", 'active' as company_status";
                    }
                } else {
                    // אם טבלת companies לא קיימת
                    $selectFields .= ", 'החברה שלי' as company_name, 'client' as company_type, 'active' as company_status";
                }
                
                // בניית שאילתה עם או בלי JOIN לפי קיום טבלת companies
                if (!empty($company_columns)) {
                    $sql = "SELECT {$selectFields} FROM users u LEFT JOIN companies c ON u.company_id = c.id WHERE u.email = ?";
                } else {
                    $sql = "SELECT {$selectFields} FROM users u WHERE u.email = ?";
                }
                
                // הוספת תנאי status אם השדה קיים
                if (in_array('status', $user_columns)) {
                    $sql .= " AND u.status = 'active'";
                }
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$email]);
                $user = $stmt->fetch();
                
                if ($user && password_verify($password, $user['password'])) {
                    // התחברות מוצלחת
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['user_name'] = $user['full_name'];
                    $_SESSION['user_email'] = $user['email'];
                    $_SESSION['user_role'] = $user['role'] ?? 'worker';
                    $_SESSION['company_id'] = $user['company_id'];
                    $_SESSION['company_name'] = $user['company_name'] ?? 'החברה שלי';
                    $_SESSION['company_type'] = $user['company_type'] ?? 'client';
                    
                    // עדכון זמן התחברות אחרונה (אם השדה קיים)
                    try {
                        if (in_array('last_login', $user_columns)) {
                            $updateStmt = $pdo->prepare("UPDATE users SET last_login = ? WHERE id = ?");
                            $updateStmt->execute([getCurrentDateTime(), $user['id']]);
                        }
                    } catch (Exception $e) {
                        // שדה last_login לא קיים או שגיאה, נמשיך בלי עדכון
                    }
                    
                    header('Location: dashboard.php');
                    exit;
                } else {
                    $error_message = 'שם משתמש או סיסמה שגויים';
                    $login_attempts++;
                }
            }
        } catch (Exception $e) {
            $error_message = 'שגיאה בחיבור למערכת. אנא נסה שוב מאוחר יותר.';
            error_log("Login error: " . $e->getMessage());
        }
    }
}

// פונקציה לפורמט תאריך עברי (fallback)
function formatHebrewDateSafe($date = null) {
    if (!$date) $date = date('Y-m-d H:i:s');
    
    try {
        if (function_exists('formatFullHebrewDate')) {
            return formatFullHebrewDate($date);
        } else {
            $israel_time = new DateTime($date, new DateTimeZone('Asia/Jerusalem'));
            return $israel_time->format('d/m/Y H:i') . ' (שעון ישראל)';
        }
    } catch (Exception $e) {
        return date('d/m/Y H:i') . ' (שעון ישראל)';
    }
}
?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>כניסה למערכת - WorkSafety.io</title>
    
    <!-- CSS -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Heebo:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Heebo', Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            direction: rtl;
        }

        .login-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            width: 100%;
            max-width: 900px;
            min-height: 600px;
            display: flex;
        }

        .login-left {
            flex: 1;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            color: white;
            padding: 40px;
            text-align: center;
        }

        .logo {
            width: 100px;
            height: 100px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 30px;
            font-size: 40px;
        }

        .login-left h1 {
            font-size: 2.5rem;
            margin-bottom: 20px;
            font-weight: 700;
        }

        .login-left p {
            font-size: 1.1rem;
            line-height: 1.6;
            opacity: 0.9;
        }

        .login-right {
            flex: 1;
            padding: 60px 50px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .login-form h2 {
            color: #333;
            font-size: 2rem;
            margin-bottom: 10px;
            font-weight: 600;
        }

        .subtitle {
            color: #666;
            margin-bottom: 30px;
            font-size: 1rem;
        }

        .timezone-info {
            background: linear-gradient(135deg, #f8f9ff 0%, #e8eeff 100%);
            border: 1px solid #e0e6ff;
            border-radius: 8px;
            padding: 12px 16px;
            margin-bottom: 25px;
            color: #4c5aa0;
            font-size: 0.9rem;
            text-align: center;
            direction: rtl;
        }

        .form-group {
            margin-bottom: 25px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 500;
        }

        .input-wrapper {
            position: relative;
        }

        .input-wrapper i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #999;
            z-index: 2;
        }

        .form-control {
            width: 100%;
            padding: 15px 45px 15px 15px;
            border: 2px solid #e1e5e9;
            border-radius: 10px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: #fff;
            direction: rtl;
        }

        .form-control:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .form-options {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }

        .remember-me {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #666;
            cursor: pointer;
        }

        .remember-me input {
            accent-color: #667eea;
        }

        .forgot-password {
            color: #667eea;
            text-decoration: none;
            font-size: 0.9rem;
        }

        .forgot-password:hover {
            text-decoration: underline;
        }

        .btn-login {
            width: 100%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 10px;
            padding: 15px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-bottom: 20px;
        }

        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.3);
        }

        .error-message {
            background: #fff5f5;
            border: 1px solid #fed7d7;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
            color: #c53030;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .success-message {
            background: #f0fff4;
            border: 1px solid #9ae6b4;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
            color: #276749;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .security-info {
            background: #f0f8ff;
            border: 1px solid #bee3f8;
            border-radius: 8px;
            padding: 15px;
            margin-top: 20px;
            color: #2b6cb0;
            font-size: 0.9rem;
            text-align: center;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .login-container {
                flex-direction: column;
                margin: 10px;
            }

            .login-right {
                padding: 30px 20px;
            }

            .form-control {
                padding: 12px 40px 12px 12px;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <!-- צד שמאל - מידע על המערכת -->
        <div class="login-left">
            <div class="logo">
                <i class="fas fa-shield-alt"></i>
            </div>
            <h1>WorkSafety.io</h1>
            <p>מערכת ניהול בטיחות תעשייתית מתקדמת<br>לבנייה, תשתיות ותעשייה</p>
        </div>

        <!-- צד ימין - טופס התחברות -->
        <div class="login-right">
            <form class="login-form" method="POST" action="">
                <h2>ברוכים הבאים</h2>
                <p class="subtitle">התחבר כדי להמשיך</p>

                <!-- הצגת זמן נוכחי -->
                <div class="timezone-info">
                    ⏰ <?php echo formatHebrewDateSafe(); ?>
                </div>

                <?php if ($error_message): ?>
                    <div class="error-message">
                        <i class="fas fa-exclamation-triangle"></i>
                        <?php echo htmlspecialchars($error_message); ?>
                    </div>
                <?php endif; ?>

                <div class="form-group">
                    <label for="email">כתובת אימייל</label>
                    <div class="input-wrapper">
                        <i class="fas fa-envelope"></i>
                        <input type="email" 
                               id="email" 
                               name="email" 
                               class="form-control" 
                               placeholder="הכנס את הכתובת שלך"
                               value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                               required>
                    </div>
                </div>

                <div class="form-group">
                    <label for="password">סיסמה</label>
                    <div class="input-wrapper">
                        <i class="fas fa-lock"></i>
                        <input type="password" 
                               id="password" 
                               name="password" 
                               class="form-control" 
                               placeholder="הכנס את הסיסמה שלך"
                               required>
                    </div>
                </div>

                <div class="form-options">
                    <label class="remember-me">
                        <input type="checkbox" name="remember_me" id="remember_me">
                        זכור אותי
                    </label>
                    <a href="forgot-password.php" class="forgot-password">שכחת סיסמה?</a>
                </div>

                <button type="submit" class="btn-login">
                    <i class="fas fa-sign-in-alt"></i> כניסה למערכת
                </button>

                <div class="security-info">
                    <i class="fas fa-shield-alt"></i>
                    החיבור שלך מוצפן ומאובטח
                </div>
            </form>
        </div>
    </div>

    <script>
        // JavaScript לשיפור חוויית המשתמש
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.querySelector('.login-form');
            const submitBtn = document.querySelector('.btn-login');
            
            form.addEventListener('submit', function(e) {
                const originalText = submitBtn.innerHTML;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> מתחבר...';
                submitBtn.disabled = true;
                
                // אם יש שגיאה, החזר את הכפתור למצבו הרגיל אחרי 3 שניות
                setTimeout(function() {
                    if (submitBtn.disabled) {
                        submitBtn.innerHTML = originalText;
                        submitBtn.disabled = false;
                    }
                }, 3000);
            });
            
            // עדכון זמן בזמן אמת
            const timezoneInfo = document.querySelector('.timezone-info');
            if (timezoneInfo) {
                setInterval(function() {
                    const now = new Date();
                    const israelTime = new Date(now.toLocaleString("en-US", {timeZone: "Asia/Jerusalem"}));
                    const timeString = israelTime.toLocaleString('he-IL', {
                        year: 'numeric',
                        month: 'long',
                        day: 'numeric',
                        hour: '2-digit',
                        minute: '2-digit'
                    });
                    timezoneInfo.innerHTML = '⏰ ' + timeString;
                }, 60000); // עדכון כל דקה
            }
        });
    </script>
</body>
</html>