<?php
/**
 * WorkSafety.io - צפייה במשתמש (גרסה בטוחה מאוד)
 * תעבוד עם כל מבנה של טבלאות, גם מינימלי
 */

// הגדרות דף
$pageTitle = 'פרטי משתמש';
$pageDescription = 'צפייה מפורטת בפרטי משתמש';

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

// קבלת ID המשתמש לצפייה
$userId = intval($_GET['id'] ?? 0);
if (!$userId) {
    echo "מזהה משתמש לא תקין";
    exit;
}

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
    
    // בניית שאילתה בסיסית בהתאם לשדות הקיימים
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
    $user['role'] = $user['role'] ?? 'משתמש';
    $user['status'] = $user['status'] ?? 'פעיל';
    $user['notes'] = $user['notes'] ?? '';
    $user['created_at'] = $user['created_at'] ?? date('Y-m-d H:i:s');
    $user['last_login'] = $user['last_login'] ?? null;
    
} catch (Exception $e) {
    $error = $e->getMessage();
    echo "<!DOCTYPE html><html lang='he' dir='rtl'><head><meta charset='UTF-8'><title>שגיאה</title></head><body>";
    echo "<h1>שגיאה בטעינת נתוני המשתמש</h1>";
    echo "<p>פרטים: " . htmlspecialchars($error) . "</p>";
    echo "<p><a href='index.php'>חזור לרשימת המשתמשים</a></p>";
    echo "</body></html>";
    exit;
}

// הצגת הדף
?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle ?? 'פרטי משתמש'); ?></title>
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
            max-width: 1200px;
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
        .user-profile {
            display: flex;
            gap: 2rem;
            padding: 2rem;
            border-bottom: 1px solid #e9ecef;
        }
        .user-avatar {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: #e9ecef;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            font-weight: bold;
            color: #495057;
            flex-shrink: 0;
        }
        .user-info h2 {
            margin: 0 0 0.5rem 0;
            color: #212529;
            font-size: 1.5rem;
        }
        .user-meta {
            color: #6c757d;
            margin: 0.25rem 0;
        }
        .status-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.875rem;
            font-weight: 500;
        }
        .status-active {
            background: #d4edda;
            color: #155724;
        }
        .status-inactive {
            background: #d1ecf1;
            color: #0c5460;
        }
        .status-suspended {
            background: #f8d7da;
            color: #721c24;
        }
        .content {
            padding: 2rem;
        }
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
            margin-top: 2rem;
        }
        .info-section {
            background: #f8f9fa;
            padding: 1.5rem;
            border-radius: 8px;
        }
        .info-section h3 {
            margin: 0 0 1rem 0;
            color: #495057;
            font-size: 1.25rem;
            border-bottom: 2px solid #007bff;
            padding-bottom: 0.5rem;
        }
        .info-item {
            margin-bottom: 1rem;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }
        .info-item strong {
            color: #495057;
            margin-left: 1rem;
            min-width: 120px;
        }
        .info-item span {
            flex: 1;
            text-align: left;
        }
        .notes {
            grid-column: 1 / -1;
            background: #fff;
            border: 1px solid #e9ecef;
            padding: 1.5rem;
            border-radius: 8px;
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
        .empty-value {
            color: #6c757d;
            font-style: italic;
        }
    </style>
</head>
<body>

<div class="container">
    <!-- Header -->
    <div class="header">
        <h1>👤 פרטי משתמש</h1>
        <div class="breadcrumb">
            <a href="/dashboard.php">דשבורד</a> > 
            <a href="index.php">משתמשים</a> > 
            פרטי משתמש
        </div>
    </div>

    <!-- User Profile Section -->
    <div class="user-profile">
        <div class="user-avatar">
            <?php 
            $initials = '';
            if ($user['first_name']) $initials .= mb_substr($user['first_name'], 0, 1);
            if ($user['last_name']) $initials .= mb_substr($user['last_name'], 0, 1);
            if (!$initials) $initials = '👤';
            echo strtoupper($initials);
            ?>
        </div>
        <div class="user-info">
            <h2><?php echo htmlspecialchars(trim($user['first_name'] . ' ' . $user['last_name'])) ?: 'משתמש'; ?></h2>
            <div class="user-meta">
                <?php
                $roleNames = [
                    'super_admin' => 'מנהל ראשי',
                    'company_admin' => 'מנהל חברה', 
                    'contractor' => 'קבלן',
                    'safety_manager' => 'מנהל בטיחות',
                    'inspector' => 'מפקח',
                    'worker' => 'עובד'
                ];
                echo $roleNames[$user['role']] ?? $user['role'];
                ?>
                <?php if ($user['company_name'] && $user['company_name'] !== 'לא שויך לחברה'): ?>
                    • <?php echo htmlspecialchars($user['company_name']); ?>
                <?php endif; ?>
            </div>
            <div class="user-meta">
                <span class="status-badge status-<?php echo $user['status']; ?>">
                    <?php
                    $statusNames = [
                        'active' => 'פעיל',
                        'inactive' => 'לא פעיל', 
                        'suspended' => 'מושעה'
                    ];
                    echo $statusNames[$user['status']] ?? $user['status'];
                    ?>
                </span>
            </div>
            <div class="user-meta">
                נוצר ב-<?php echo formatHebrewDate($user['created_at']); ?>
                <?php if ($user['last_login']): ?>
                    • כניסה אחרונה: <?php echo formatHebrewDateTime($user['last_login']); ?>
                <?php else: ?>
                    • לא נכנס מעולם
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Content -->
    <div class="content">
        <div class="info-grid">
            <!-- Contact Information -->
            <div class="info-section">
                <h3>📞 פרטי יצירת קשר</h3>
                <div class="info-item">
                    <strong>אימייל:</strong>
                    <span><?php echo $user['email'] ? htmlspecialchars($user['email']) : '<span class="empty-value">לא הוזן</span>'; ?></span>
                </div>
                <div class="info-item">
                    <strong>טלפון:</strong>
                    <span><?php echo $user['phone'] ? htmlspecialchars($user['phone']) : '<span class="empty-value">לא הוזן</span>'; ?></span>
                </div>
            </div>

            <!-- Company Information -->
            <?php if ($companiesExists): ?>
            <div class="info-section">
                <h3>🏢 פרטי החברה</h3>
                <div class="info-item">
                    <strong>שם החברה:</strong>
                    <span><?php echo htmlspecialchars($user['company_name']); ?></span>
                </div>
                <?php if (isset($user['company_type']) && $user['company_type'] !== 'לא מוגדר'): ?>
                <div class="info-item">
                    <strong>סוג החברה:</strong>
                    <span>
                        <?php
                        $companyTypes = [
                            'main' => 'חברה ראשית',
                            'client' => 'חברה לקוחה',
                            'contractor' => 'חברת קבלנות'
                        ];
                        echo $companyTypes[$user['company_type']] ?? $user['company_type'];
                        ?>
                    </span>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- System Information -->
            <div class="info-section">
                <h3>⚙️ מידע מערכת</h3>
                <div class="info-item">
                    <strong>מזהה משתמש:</strong>
                    <span><?php echo $user['id']; ?></span>
                </div>
                <div class="info-item">
                    <strong>תאריך יצירה:</strong>
                    <span><?php echo formatHebrewDateTime($user['created_at']); ?></span>
                </div>
                <?php if (isset($user['updated_at']) && $user['updated_at'] && $user['updated_at'] !== $user['created_at']): ?>
                <div class="info-item">
                    <strong>עודכן לאחרונה:</strong>
                    <span><?php echo formatHebrewDateTime($user['updated_at']); ?></span>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Notes -->
        <?php if ($user['notes']): ?>
        <div class="notes">
            <h3>📝 הערות</h3>
            <div><?php echo nl2br(htmlspecialchars($user['notes'])); ?></div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Actions -->
    <div class="actions">
        <?php if (isset($currentUser['role']) && in_array($currentUser['role'], ['super_admin', 'company_admin'])): ?>
            <a href="edit.php?id=<?php echo $userId; ?>" class="btn btn-primary">
                ✏️ ערוך משתמש
            </a>
        <?php endif; ?>
        <a href="index.php" class="btn btn-outline">
            ⬅️ חזור לרשימה
        </a>
    </div>
</div>

</body>
</html>
