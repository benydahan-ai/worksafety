<?php
/**
 * WorkSafety.io - הוספת חברה חדשה
 * טופס מתקדם להוספת חברה עם כל השדות הנדרשים
 */

// הגדרות דף
$pageTitle = 'הוספת חברה חדשה';
$pageDescription = 'הוספת חברת לקוח חדשה למערכת';
$additionalCSS = ['/modules/companies/assets/companies.css'];
$additionalJS = ['/modules/companies/assets/companies.js'];

// כלילת קבצים נדרשים
require_once '../../includes/header.php';
require_once 'includes/functions.php';

// וידוא התחברות והרשאות
if (!isset($_SESSION['user_id'])) {
    header('Location: /login.php');
    exit;
}

// בדיקת הרשאות - רק מנהל ראשי יכול להוסיף חברות
$userRole = $currentUser['role'] ?? 'worker';
if ($userRole !== 'super_admin') {
    $_SESSION['flash_message']['danger'] = 'אין לך הרשאה לגשת לעמוד זה';
    header('Location: /index.php');
    exit;
}

// משתנים לטופס
$formData = [
    'name' => '',
    'company_type' => 'client',
    'email' => '',
    'phone' => '',
    'address' => '',
    'contact_person' => '',
    'website' => '',
    'registration_number' => '',
    'tax_id' => '',
    'subscription_plan' => 'basic',
    'max_users' => 10,
    'max_sites' => 5,
    'expires_at' => '',
    'status' => 'active'
];

$errors = [];

// טיפול בשליחת הטופס
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $db = getDB();
        
        // קבלת נתונים מהטופס
        $formData = [
            'name' => trim($_POST['name'] ?? ''),
            'company_type' => $_POST['company_type'] ?? 'client',
            'email' => trim($_POST['email'] ?? ''),
            'phone' => trim($_POST['phone'] ?? ''),
            'address' => trim($_POST['address'] ?? ''),
            'contact_person' => trim($_POST['contact_person'] ?? ''),
            'website' => trim($_POST['website'] ?? ''),
            'registration_number' => trim($_POST['registration_number'] ?? ''),
            'tax_id' => trim($_POST['tax_id'] ?? ''),
            'subscription_plan' => $_POST['subscription_plan'] ?? 'basic',
            'max_users' => intval($_POST['max_users'] ?? 10),
            'max_sites' => intval($_POST['max_sites'] ?? 5),
            'expires_at' => !empty($_POST['expires_at']) ? $_POST['expires_at'] : null,
            'status' => $_POST['status'] ?? 'active'
        ];
        
        // ולידציה
        $errors = validateCompanyData($formData, $db);
        
        // טיפול בהעלאת לוגו
        $logoPath = '';
        if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
            $uploadResult = handleLogoUpload($_FILES['logo']);
            if ($uploadResult['success']) {
                $logoPath = $uploadResult['path'];
            } else {
                $errors[] = $uploadResult['error'];
            }
        }
        
        // אם אין שגיאות - שמירה במסד הנתונים
        if (empty($errors)) {
            // הכנת נתונים לשמירה
            $insertData = $formData;
            if (!empty($logoPath)) {
                $insertData['logo'] = $logoPath;
            }
            
            // הגדרות ברירת מחדל
            $insertData['settings'] = json_encode([
                'notifications' => [
                    'email_reports' => true,
                    'sms_alerts' => false,
                    'deficiency_notifications' => true
                ],
                'features' => [
                    'advanced_reports' => $formData['subscription_plan'] !== 'basic',
                    'mobile_app_access' => true,
                    'api_access' => $formData['subscription_plan'] === 'enterprise'
                ]
            ]);
            
            // שמירה במסד הנתונים
            $companyId = $db->insert('companies', $insertData);
            
            if ($companyId) {
                $_SESSION['flash_message']['success'] = 'החברה נוספה בהצלחה!';
                header('Location: view.php?id=' . $companyId);
                exit;
            } else {
                $errors[] = 'שגיאה בשמירת הנתונים. אנא נסה שוב.';
            }
        }
        
    } catch (Exception $e) {
        error_log("Add company error: " . $e->getMessage());
        $errors[] = 'שגיאת מערכת: ' . $e->getMessage();
    }
}
?>

<div class="page-wrapper">
    <!-- Page Header -->
    <header class="page-header">
        <div class="header-content">
            <div class="header-text">
                <h1>
                    <i class="fas fa-plus-circle"></i>
                    הוספת חברה חדשה
                </h1>
                <p>הוספת חברת לקוח חדשה למערכת עם הגדרת הרשאות ומגבלות שימוש</p>
            </div>
            <div class="header-actions">
                <a href="index.php" class="btn btn-outline">
                    <i class="fas fa-arrow-right"></i>
                    חזור לרשימת חברות
                </a>
            </div>
        </div>
    </header>

    <!-- Page Content -->
    <div class="page-content">
        
        <!-- Error Messages -->
        <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <h4><i class="fas fa-exclamation-triangle"></i> שגיאות בטופס:</h4>
            <ul>
                <?php foreach ($errors as $error): ?>
                    <li><?php echo htmlspecialchars($error); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data" class="company-form">
            
            <!-- Basic Information -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-info-circle"></i> פרטים כלליים</h3>
                </div>
                <div class="card-body">
                    <div class="form-grid">
                        <div class="form-group required">
                            <label for="name">שם החברה</label>
                            <input type="text" id="name" name="name" class="form-control" 
                                   value="<?php echo htmlspecialchars($formData['name']); ?>" 
                                   required maxlength="255">
                            <small class="form-help">השם המלא של החברה כפי שמופיע ברישומים הרשמיים</small>
                        </div>
                        
                        <div class="form-group required">
                            <label for="company_type">סוג החברה</label>
                            <select id="company_type" name="company_type" class="form-control" required>
                                <option value="client" <?php echo $formData['company_type'] === 'client' ? 'selected' : ''; ?>>
                                    חברת לקוח
                                </option>
                                <option value="main" <?php echo $formData['company_type'] === 'main' ? 'selected' : ''; ?>>
                                    חברה ראשית
                                </option>
                            </select>
                            <small class="form-help">חברת לקוח - מקבלת שירותים. חברה ראשית - מספקת שירותים.</small>
                        </div>
                        
                        <div class="form-group required">
                            <label for="email">כתובת אימייל</label>
                            <input type="email" id="email" name="email" class="form-control" 
                                   value="<?php echo htmlspecialchars($formData['email']); ?>" 
                                   required maxlength="255">
                            <small class="form-help">אימייל ראשי לתקשורת עם החברה</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="phone">מספר טלפון</label>
                            <input type="tel" id="phone" name="phone" class="form-control" 
                                   value="<?php echo htmlspecialchars($formData['phone']); ?>" 
                                   maxlength="50" placeholder="052-1234567">
                            <small class="form-help">מספר טלפון ליצירת קשר</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="contact_person">איש קשר</label>
                            <input type="text" id="contact_person" name="contact_person" class="form-control" 
                                   value="<?php echo htmlspecialchars($formData['contact_person']); ?>" 
                                   maxlength="255" placeholder="שם מלא">
                            <small class="form-help">שם איש הקשר הראשי בחברה</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="website">אתר אינטרנט</label>
                            <input type="url" id="website" name="website" class="form-control" 
                                   value="<?php echo htmlspecialchars($formData['website']); ?>" 
                                   maxlength="255" placeholder="https://example.com">
                            <small class="form-help">כתובת אתר האינטרנט של החברה</small>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="address">כתובת</label>
                        <textarea id="address" name="address" class="form-control" rows="3" 
                                  placeholder="כתובת מלאה של החברה"><?php echo htmlspecialchars($formData['address']); ?></textarea>
                        <small class="form-help">כתובת פיזית של החברה</small>
                    </div>
                </div>
            </div>

            <!-- Logo Upload -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-image"></i> לוגו החברה</h3>
                </div>
                <div class="card-body">
                    <div class="form-group">
                        <label for="logo">העלאת לוגו</label>
                        <div class="file-upload-area" onclick="document.getElementById('logo').click()">
                            <div class="file-upload-content">
                                <i class="fas fa-cloud-upload-alt"></i>
                                <p>לחץ כאן להעלאת לוגו או גרור קובץ</p>
                                <small>תמונות עד 2MB בפורמט JPG, PNG או GIF</small>
                            </div>
                            <input type="file" id="logo" name="logo" accept="image/*" style="display: none;" 
                                   onchange="previewLogo(this)">
                        </div>
                        <div id="logoPreview" style="display: none; margin-top: 15px;">
                            <img id="logoImage" src="" alt="תצוגה מקדימה" style="max-width: 200px; max-height: 100px;">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Legal Information -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-file-alt"></i> פרטים משפטיים ועסקיים</h3>
                </div>
                <div class="card-body">
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="registration_number">מספר רישום חברה</label>
                            <input type="text" id="registration_number" name="registration_number" class="form-control" 
                                   value="<?php echo htmlspecialchars($formData['registration_number']); ?>" 
                                   maxlength="100" placeholder="51-123456-7">
                            <small class="form-help">מספר רישום החברה ברשם החברות</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="tax_id">מספר עוסק מורשה</label>
                            <input type="text" id="tax_id" name="tax_id" class="form-control" 
                                   value="<?php echo htmlspecialchars($formData['tax_id']); ?>" 
                                   maxlength="50" placeholder="123456789">
                            <small class="form-help">מספר עוסק מורשה או ח.פ.</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Subscription Settings -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-cog"></i> הגדרות מנוי ומגבלות</h3>
                </div>
                <div class="card-body">
                    <div class="form-grid">
                        <div class="form-group required">
                            <label for="subscription_plan">תוכנית מנוי</label>
                            <select id="subscription_plan" name="subscription_plan" class="form-control" required>
                                <option value="basic" <?php echo $formData['subscription_plan'] === 'basic' ? 'selected' : ''; ?>>
                                    בסיסי (Basic)
                                </option>
                                <option value="standard" <?php echo $formData['subscription_plan'] === 'standard' ? 'selected' : ''; ?>>
                                    סטנדרטי (Standard)
                                </option>
                                <option value="premium" <?php echo $formData['subscription_plan'] === 'premium' ? 'selected' : ''; ?>>
                                    פרימיום (Premium)
                                </option>
                                <option value="enterprise" <?php echo $formData['subscription_plan'] === 'enterprise' ? 'selected' : ''; ?>>
                                    ארגוני (Enterprise)
                                </option>
                            </select>
                            <small class="form-help">תוכנית המנוי קובעת את התכונות הזמינות לחברה</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="max_users">מגבלת משתמשים</label>
                            <input type="number" id="max_users" name="max_users" class="form-control" 
                                   value="<?php echo $formData['max_users']; ?>" 
                                   min="1" max="1000">
                            <small class="form-help">מספר משתמשים מקסימלי. 0 = ללא הגבלה</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="max_sites">מגבלת אתרי עבודה</label>
                            <input type="number" id="max_sites" name="max_sites" class="form-control" 
                                   value="<?php echo $formData['max_sites']; ?>" 
                                   min="1" max="500">
                            <small class="form-help">מספר אתרי עבודה מקסימלי. 0 = ללא הגבלה</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="expires_at">תאריך תפוגת מנוי</label>
                            <input type="date" id="expires_at" name="expires_at" class="form-control" 
                                   value="<?php echo $formData['expires_at']; ?>" 
                                   min="<?php echo date('Y-m-d'); ?>">
                            <small class="form-help">תאריך תפוגת המנוי. השאר ריק למנוי ללא הגבלת זמן</small>
                        </div>
                        
                        <div class="form-group required">
                            <label for="status">סטטוס החברה</label>
                            <select id="status" name="status" class="form-control" required>
                                <option value="active" <?php echo $formData['status'] === 'active' ? 'selected' : ''; ?>>
                                    פעיל
                                </option>
                                <option value="inactive" <?php echo $formData['status'] === 'inactive' ? 'selected' : ''; ?>>
                                    לא פעיל
                                </option>
                                <option value="suspended" <?php echo $formData['status'] === 'suspended' ? 'selected' : ''; ?>>
                                    מושעה
                                </option>
                            </select>
                            <small class="form-help">סטטוס פעיל נדרש כדי שהחברה תוכל להשתמש במערכת</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Form Actions -->
            <div class="form-actions">
                <button type="submit" class="btn btn-primary btn-lg">
                    <i class="fas fa-save"></i>
                    שמור חברה חדשה
                </button>
                
                <a href="index.php" class="btn btn-outline btn-lg">
                    <i class="fas fa-times"></i>
                    ביטול
                </a>
                
                <button type="button" class="btn btn-secondary btn-lg" onclick="resetForm()">
                    <i class="fas fa-undo"></i>
                    איפוס טופס
                </button>
            </div>
        </form>
        
    </div>
</div>

<script>
function previewLogo(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        
        reader.onload = function(e) {
            document.getElementById('logoImage').src = e.target.result;
            document.getElementById('logoPreview').style.display = 'block';
        };
        
        reader.readAsDataURL(input.files[0]);
    }
}

function resetForm() {
    if (confirm('האם אתה בטוח שברצונך לאפס את הטופס? כל הנתונים שהוזנו יאבדו.')) {
        document.querySelector('.company-form').reset();
        document.getElementById('logoPreview').style.display = 'none';
    }
}

// Real-time validation
document.addEventListener('DOMContentLoaded', function() {
    // Subscription plan change handler
    document.getElementById('subscription_plan').addEventListener('change', function() {
        const plan = this.value;
        const maxUsersField = document.getElementById('max_users');
        const maxSitesField = document.getElementById('max_sites');
        
        // Set default values based on plan
        switch(plan) {
            case 'basic':
                maxUsersField.value = 10;
                maxSitesField.value = 5;
                break;
            case 'standard':
                maxUsersField.value = 25;
                maxSitesField.value = 15;
                break;
            case 'premium':
                maxUsersField.value = 50;
                maxSitesField.value = 30;
                break;
            case 'enterprise':
                maxUsersField.value = 0; // Unlimited
                maxSitesField.value = 0; // Unlimited
                break;
        }
    });
    
    // Phone number formatting
    document.getElementById('phone').addEventListener('input', function() {
        let value = this.value.replace(/\D/g, '');
        if (value.length >= 10) {
            value = value.substring(0, 10);
            this.value = value.replace(/(\d{3})(\d{3})(\d{4})/, '$1-$2-$3');
        }
    });
});
</script>

<?php require_once '../../includes/footer.php'; ?>
