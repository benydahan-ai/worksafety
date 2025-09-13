<?php
/**
 * WorkSafety.io - עריכת משתמש (גרסה בטוחה מאוד)
 * תעבוד עם כל מבנה של טבלאות, גם מינימלי
 */

// הגדרות דף
$pageTitle = 'עריכת משתמש';
$pageDescription = 'עריכת פרטי משתמש במערכת';

// כלילת קבצים נדרשים - עם בדיקה
$headerPath = '../../includes/header.php';
if (file_exists($headerPath)) {
    require_once $headerPath;
} else {
    // אם אין header, נתחיל בסיסית
    session_start();
    if (!function_exists('getDB')) {
        require_once '../../config/database.php';
    }
}

// וידוא התחברות והרשאות  
if (!isset($_SESSION['user_id'])) {
    header('Location: /login.php');
    exit;
}

// קבלת ID המשתמש לעריכה
$userId = intval($_GET['id'] ?? 0);
if (!$userId) {
    echo "מזהה משתמש לא תקין";
    exit;
}

$errors = [];
$success = '';

// פונקציות עזר בסיסיות
if (!function_exists('formatHebrewDate')) {
    function formatHebrewDate($date) {
        if (!$date) return 'לא זמין';
        $timestamp = strtotime($date);
        return date('d/m/Y', $timestamp);
    }
}

if (!function_exists('formatHebrewDateTime')) {
    function formatHebrewDateTime($datetime) {
        if (!$datetime) return 'לא זמין';
        $timestamp = strtotime($datetime);
        return date('d/m/Y H:i', $timestamp);
    }
}

if (!function_exists('getCurrentDateTime')) {
    function getCurrentDateTime() {
        return date('Y-m-d H:i:s');
    }
}

// קבלת פרטי המשתמש - בדיקה בטוחה מאוד
try {
    $db = getDB();
    $pdo = $db->getConnection();
    
    // בדיקה בסיסית - קיום טבלת משתמשים
    $tablesCheck = $pdo->query("SHOW TABLES LIKE 'users'");
    if (!$tablesCheck || !$tablesCheck->fetch()) {
        throw new Exception('טבלת משתמשים לא קיימת');
    }
    
    // בדיקת שדות בטבלת משתמשים
    $userFieldsResult = $pdo->query("SHOW COLUMNS FROM users");
    $userFields = [];
    while ($field = $userFieldsResult->fetch(PDO::FETCH_ASSOC)) {
        $userFields[] = $field['Field'];
    }
    
    // בדיקת קיום טבלת חברות
    $companiesExists = false;
    $companyFields = [];
    $companiesCheck = $pdo->query("SHOW TABLES LIKE 'companies'");
    if ($companiesCheck && $companiesCheck->fetch()) {
        $companiesExists = true;
        $companyFieldsResult = $pdo->query("SHOW COLUMNS FROM companies");
        while ($field = $companyFieldsResult->fetch(PDO::FETCH_ASSOC)) {
            $companyFields[] = $field['Field'];
        }
    }
    
    // בניית שאילתה לקבלת נתוני המשתמש
    $selectFields = ['u.*'];
    $joinClauses = [];
    
    // הוספת שדות חברה אם הטבלה קיימת
    if ($companiesExists) {
        if (in_array('name', $companyFields)) {
            $selectFields[] = 'c.name as company_name';
        }
        if (in_array('company_type', $companyFields)) {
            $selectFields[] = 'c.company_type as company_type';
        } elseif (in_array('type', $companyFields)) {
            $selectFields[] = 'c.type as company_type';  
        }
        $joinClauses[] = 'LEFT JOIN companies c ON u.company_id = c.id';
    }
    
    // שאילתה מותאמת
    $sql = "SELECT " . implode(', ', $selectFields) . " FROM users u ";
    if (!empty($joinClauses)) {
        $sql .= implode(' ', $joinClauses) . " ";
    }
    $sql .= "WHERE u.id = ?";
    
    // הרצת השאילתה
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        throw new Exception('משתמש לא נמצא');
    }
    
    // הגדרת ערכי ברירת מחדל לשדות שיכולים להיות חסרים
    $user['company_name'] = $user['company_name'] ?? 'לא שויך לחברה';
    $user['company_type'] = $user['company_type'] ?? 'לא מוגדר';
    $user['first_name'] = $user['first_name'] ?? '';
    $user['last_name'] = $user['last_name'] ?? '';
    $user['email'] = $user['email'] ?? '';
    $user['phone'] = $user['phone'] ?? '';
    $user['role'] = $user['role'] ?? 'worker';
    $user['status'] = $user['status'] ?? 'active';
    $user['notes'] = $user['notes'] ?? '';
    $user['created_at'] = $user['created_at'] ?? date('Y-m-d H:i:s');
    $user['last_login'] = $user['last_login'] ?? null;
    
    // קבלת רשימת חברות (אם קיימת)
    $companies = [];
    if ($companiesExists && isset($currentUser['role']) && $currentUser['role'] === 'super_admin') {
        try {
            $companiesStmt = $pdo->query("SELECT id, name FROM companies ORDER BY name");
            while ($company = $companiesStmt->fetch(PDO::FETCH_ASSOC)) {
                $companies[] = $company;
            }
        } catch (Exception $e) {
            // שגיאה בקריאת חברות - נמשיך בלי
        }
    }
    
} catch (Exception $e) {
    $error = $e->getMessage();
    echo "<!DOCTYPE html><html lang='he' dir='rtl'><head><meta charset='UTF-8'><title>שגיאה</title></head><body>";
    echo "<h1>שגיאה בטעינת נתוני המשתמש</h1>";
    echo "<p>פרטים: " . htmlspecialchars($error) . "</p>";
    echo "<p><a href='index.php'>חזור לרשימת המשתמשים</a></p>";
    echo "</body></html>";
    exit;
}

// טיפול בשליחת הטופס
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // ולידציה בסיסית
        $firstName = trim($_POST['first_name'] ?? '');
        $lastName = trim($_POST['last_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        $role = $_POST['role'] ?? $user['role'];
        $companyId = $_POST['company_id'] ?? ($user['company_id'] ?? null);
        $status = $_POST['status'] ?? $user['status'];
        $notes = trim($_POST['notes'] ?? '');
        
        // בדיקות וולידציה
        if (empty($firstName)) {
            $errors[] = 'שם פרטי הוא שדה חובה';
        }
        
        if (empty($lastName)) {
            $errors[] = 'שם משפחה הוא שדה חובה';
        }
        
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'כתובת אימייל לא תקינה';
        }
        
        // בדיקת סיסמה (אם הוזנה)
        if (!empty($password)) {
            if (strlen($password) < 8) {
                $errors[] = 'הסיסמה חייבת להיות באורך 8 תווים לפחות';
            }
            if ($password !== $confirmPassword) {
                $errors[] = 'אישור הסיסמה אינו תואם';
            }
        }
        
        // בדיקת קיום אימייל (למעט המשתמש הנוכחי)
        if ($email !== $user['email']) {
            $checkEmailStmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $checkEmailStmt->execute([$email, $userId]);
            if ($checkEmailStmt->fetch()) {
                $errors[] = 'כתובת האימייל כבר קיימת במערכת';
            }
        }
        
        if (empty($errors)) {
            // הכנת נתונים לעדכון - רק שדות שקיימים
            $updateData = [];
            $updateFields = [];
            
            if (in_array('first_name', $userFields)) {
                $updateData[] = $firstName;
                $updateFields[] = 'first_name = ?';
            }
            
            if (in_array('last_name', $userFields)) {
                $updateData[] = $lastName;
                $updateFields[] = 'last_name = ?';
            }
            
            if (in_array('email', $userFields)) {
                $updateData[] = $email;
                $updateFields[] = 'email = ?';
            }
            
            if (in_array('phone', $userFields)) {
                $updateData[] = $phone;
                $updateFields[] = 'phone = ?';
            }
            
            if (in_array('role', $userFields)) {
                $updateData[] = $role;
                $updateFields[] = 'role = ?';
            }
            
            if (in_array('company_id', $userFields) && $companyId) {
                $updateData[] = $companyId;
                $updateFields[] = 'company_id = ?';
            }
            
            if (in_array('status', $userFields)) {
                $updateData[] = $status;
                $updateFields[] = 'status = ?';
            }
            
            if (in_array('notes', $userFields)) {
                $updateData[] = $notes;
                $updateFields[] = 'notes = ?';
            }
            
            // עדכון סיסמה (אם הוזנה ושדה קיים)
            if (!empty($password) && in_array('password', $userFields)) {
                $updateData[] = password_hash($password, PASSWORD_DEFAULT);
                $updateFields[] = 'password = ?';
            }
            
            // שדות מערכת
            if (in_array('updated_at', $userFields)) {
                $updateData[] = getCurrentDateTime();
                $updateFields[] = 'updated_at = ?';
            }
            
            if (in_array('updated_by', $userFields) && isset($_SESSION['user_id'])) {
                $updateData[] = $_SESSION['user_id'];
                $updateFields[] = 'updated_by = ?';
            }
            
            // הרצת עדכון
            if (!empty($updateFields)) {
                $updateData[] = $userId; // WHERE condition
                $updateSQL = "UPDATE users SET " . implode(', ', $updateFields) . " WHERE id = ?";
                $updateStmt = $pdo->prepare($updateSQL);
                $updateStmt->execute($updateData);
                
                $success = 'פרטי המשתמש עודכנו בהצלחה';
                
                // רענון נתוני המשתמש
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$userId]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
            }
        }
        
    } catch (Exception $e) {
        $errors[] = 'שגיאה בעדכון המשתמש: ' . $e->getMessage();
    }
}

// הצגת הדף
?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle ?? 'עריכת משתמש'); ?></title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 2rem;
            background: #f8f9fa;
            color: #212529;
            line-height: 1.6;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        .header {
            background: linear-gradient(135deg, #007bff, #0056b3);
            color: white;
            padding: 2rem;
        }
        .header h1 {
            margin: 0;
            font-size: 2rem;
        }
        .breadcrumb {
            margin-top: 0.5rem;
            opacity: 0.8;
        }
        .breadcrumb a {
            color: white;
            text-decoration: none;
        }
        .breadcrumb a:hover {
            text-decoration: underline;
        }
        .content {
            padding: 2rem;
        }
        .alert {
            padding: 1rem;
            border-radius: 4px;
            margin-bottom: 1rem;
        }
        .alert-danger {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f1aeb5;
        }
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #a3cfbb;
        }
        .form-section {
            margin-bottom: 2rem;
        }
        .section-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: #495057;
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid #007bff;
        }
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
        }
        .form-group {
            margin-bottom: 1.5rem;
        }
        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: #495057;
        }
        .form-label.required::after {
            content: " *";
            color: #dc3545;
        }
        .form-control {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #ced4da;
            border-radius: 4px;
            font-size: 1rem;
            box-sizing: border-box;
        }
        .form-control:focus {
            outline: none;
            border-color: #007bff;
            box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
        }
        .form-help {
            font-size: 0.875rem;
            color: #6c757d;
            margin-top: 0.25rem;
        }
        .actions {
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
            padding: 2rem;
            background: #f8f9fa;
            border-top: 1px solid #e9ecef;
        }
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1.5rem;
            border-radius: 4px;
            text-decoration: none;
            font-size: 1rem;
            font-weight: 500;
            cursor: pointer;
            border: none;
        }
        .btn-primary {
            background: #007bff;
            color: white;
        }
        .btn-outline {
            background: white;
            color: #6c757d;
            border: 1px solid #ced4da;
        }
        .btn:hover {
            opacity: 0.8;
        }
        .user-summary {
            background: #f8f9fa;
            padding: 1.5rem;
            border-radius: 8px;
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        .user-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: #e9ecef;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            font-weight: bold;
            color: #495057;
        }
    </style>
</head>
<body>

<div class="container">
    <!-- Header -->
    <div class="header">
        <h1>✏️ עריכת משתמש</h1>
        <div class="breadcrumb">
            <a href="/dashboard.php">דשבורד</a> > 
            <a href="index.php">משתמשים</a> > 
            <a href="view.php?id=<?php echo $userId; ?>">פרטי משתמש</a> >
            עריכה
        </div>
    </div>

    <div class="content">
        <!-- Alerts -->
        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <strong>שגיאות:</strong>
                <ul style="margin: 0.5rem 0 0 1rem;">
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success">
                <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>

        <!-- User Summary -->
        <div class="user-summary">
            <div class="user-avatar">
                <?php 
                $initials = '';
                if ($user['first_name']) $initials .= mb_substr($user['first_name'], 0, 1);
                if ($user['last_name']) $initials .= mb_substr($user['last_name'], 0, 1);
                if (!$initials) $initials = '👤';
                echo strtoupper($initials);
                ?>
            </div>
            <div>
                <h3 style="margin: 0;"><?php echo htmlspecialchars(trim($user['first_name'] . ' ' . $user['last_name'])) ?: 'משתמש'; ?></h3>
                <p style="margin: 0.25rem 0; color: #6c757d;">
                    <?php echo $user['email']; ?> • <?php echo $user['role']; ?>
                    <?php if ($user['company_name'] && $user['company_name'] !== 'לא שויך לחברה'): ?>
                        • <?php echo $user['company_name']; ?>
                    <?php endif; ?>
                </p>
            </div>
        </div>

        <!-- Edit Form -->
        <form method="POST" id="editUserForm">
            
            <!-- Personal Information -->
            <div class="form-section">
                <h3 class="section-title">פרטים אישיים</h3>
                <div class="form-grid">
                    <div class="form-group">
                        <label for="first_name" class="form-label required">שם פרטי</label>
                        <input type="text" id="first_name" name="first_name" class="form-control" 
                               value="<?php echo htmlspecialchars($_POST['first_name'] ?? $user['first_name']); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="last_name" class="form-label required">שם משפחה</label>
                        <input type="text" id="last_name" name="last_name" class="form-control" 
                               value="<?php echo htmlspecialchars($_POST['last_name'] ?? $user['last_name']); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="email" class="form-label required">כתובת אימייל</label>
                        <input type="email" id="email" name="email" class="form-control" 
                               value="<?php echo htmlspecialchars($_POST['email'] ?? $user['email']); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="phone" class="form-label">טלפון</label>
                        <input type="tel" id="phone" name="phone" class="form-control" 
                               value="<?php echo htmlspecialchars($_POST['phone'] ?? $user['phone']); ?>">
                    </div>
                </div>
            </div>

            <!-- System Settings -->
            <div class="form-section">
                <h3 class="section-title">הגדרות מערכת</h3>
                <div class="form-grid">
                    <div class="form-group">
                        <label for="role" class="form-label required">תפקיד</label>
                        <select id="role" name="role" class="form-control" required>
                            <?php
                            $availableRoles = [
                                'worker' => 'עובד',
                                'inspector' => 'מפקח',
                                'safety_manager' => 'מנהל בטיחות',
                                'contractor' => 'קבלן',
                                'company_admin' => 'מנהל חברה',
                                'super_admin' => 'מנהל ראשי'
                            ];
                            
                            foreach ($availableRoles as $roleValue => $roleName):
                                $selected = ($roleValue === ($_POST['role'] ?? $user['role'])) ? 'selected' : '';
                            ?>
                                <option value="<?php echo $roleValue; ?>" <?php echo $selected; ?>>
                                    <?php echo $roleName; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <?php if (!empty($companies)): ?>
                    <div class="form-group">
                        <label for="company_id" class="form-label">חברה</label>
                        <select id="company_id" name="company_id" class="form-control">
                            <option value="">בחר חברה</option>
                            <?php foreach ($companies as $company): ?>
                                <option value="<?php echo $company['id']; ?>" 
                                        <?php echo ($company['id'] == ($_POST['company_id'] ?? $user['company_id'])) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($company['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>
                    
                    <div class="form-group">
                        <label for="status" class="form-label required">סטטוס</label>
                        <select id="status" name="status" class="form-control" required>
                            <option value="active" <?php echo (($_POST['status'] ?? $user['status']) === 'active') ? 'selected' : ''; ?>>פעיל</option>
                            <option value="inactive" <?php echo (($_POST['status'] ?? $user['status']) === 'inactive') ? 'selected' : ''; ?>>לא פעיל</option>
                            <option value="suspended" <?php echo (($_POST['status'] ?? $user['status']) === 'suspended') ? 'selected' : ''; ?>>מושעה</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Password Change -->
            <div class="form-section">
                <h3 class="section-title">שינוי סיסמה</h3>
                <div class="form-grid">
                    <div class="form-group">
                        <label for="password" class="form-label">סיסמה חדשה</label>
                        <input type="password" id="password" name="password" class="form-control" minlength="8">
                        <div class="form-help">השאר ריק אם אינך רוצה לשנות. לפחות 8 תווים</div>
                    </div>
                    
                    <div class="form-group">
                        <label for="confirm_password" class="form-label">אישור סיסמה</label>
                        <input type="password" id="confirm_password" name="confirm_password" class="form-control">
                    </div>
                </div>
            </div>

            <!-- Notes -->
            <div class="form-section">
                <h3 class="section-title">הערות</h3>
                <div class="form-group">
                    <label for="notes" class="form-label">הערות והתייחסויות</label>
                    <textarea id="notes" name="notes" class="form-control" rows="4" 
                              style="resize: vertical;"><?php echo htmlspecialchars($_POST['notes'] ?? $user['notes']); ?></textarea>
                </div>
            </div>

        </form>
    </div>

    <!-- Actions -->
    <div class="actions">
        <button type="submit" form="editUserForm" class="btn btn-primary">
            💾 שמור שינויים
        </button>
        <a href="view.php?id=<?php echo $userId; ?>" class="btn btn-outline">
            ❌ בטל
        </a>
    </div>
</div>

<script>
// Form validation
document.getElementById('editUserForm').addEventListener('submit', function(e) {
    const password = document.getElementById('password').value;
    const confirmPassword = document.getElementById('confirm_password').value;
    
    if (password && password !== confirmPassword) {
        e.preventDefault();
        alert('אישור הסיסמה אינו תואם את הסיסמה החדשה');
        return false;
    }
});
</script>

</body>
</html>
