<?php
/**
 * WorkSafety.io - עריכת חברה
 * טופס מתקדם לעריכת נתוני חברה קיימת
 */

// הגדרות דף
$pageTitle = 'עריכת חברה';
$pageDescription = 'עריכת פרטי חברה, מגבלות שימוש והגדרות מנוי';
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

// בדיקת הרשאות
$userRole = $currentUser['role'] ?? 'worker';
if (!in_array($userRole, ['super_admin', 'company_admin'])) {
    $_SESSION['flash_message']['danger'] = 'אין לך הרשאה לגשת לעמוד זה';
    header('Location: /dashboard.php');
    exit;
}

// קבלת ID החברה
$companyId = intval($_GET['id'] ?? 0);
if (!$companyId) {
    $_SESSION['flash_message']['danger'] = 'לא נמצאה חברה מתאימה';
    header('Location: index.php');
    exit;
}

try {
    $db = getDB();
    
    // קבלת נתוני החברה הנוכחיים
    $company = $db->fetchOne("SELECT * FROM companies WHERE id = ?", [$companyId]);
    
    if (!$company) {
        $_SESSION['flash_message']['danger'] = 'החברה לא נמצאה';
        header('Location: index.php');
        exit;
    }
    
    // בדיקת הרשאות לעריכת החברה
    if ($userRole === 'company_admin' && $company['id'] != $currentUser['company_id']) {
        $_SESSION['flash_message']['danger'] = 'אין לך הרשאה לערוך חברה זו';
        header('Location: index.php');
        exit;
    }
    
} catch (Exception $e) {
    error_log("Edit company page error: " . $e->getMessage());
    $_SESSION['flash_message']['danger'] = 'שגיאה בטעינת נתוני החברה';
    header('Location: index.php');
    exit;
}

// הכנת נתונים לטופס
$formData = [
    'name' => $company['name'],
    'company_type' => $company['company_type'],
    'email' => $company['email'],
    'phone' => $company['phone'] ?? '',
    'address' => $company['address'] ?? '',
    'contact_person' => $company['contact_person'] ?? '',
    'website' => $company['website'] ?? '',
    'registration_number' => $company['registration_number'] ?? '',
    'tax_id' => $company['tax_id'] ?? '',
    'subscription_plan' => $company['subscription_plan'],
    'max_users' => $company['max_users'],
    'max_sites' => $company['max_sites'],
    'expires_at' => $company['expires_at'] ? date('Y-m-d', strtotime($company['expires_at'])) : '',
    'status' => $company['status']
];

$errors = [];

// טיפול בשליחת הטופס
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
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
        $errors = validateCompanyData($formData, $db, $companyId);
        
        // טיפול בהעלאת לוגו חדש
        $logoPath = $company['logo']; // שמירת הלוגו הנוכחי
        if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
            $uploadResult = handleLogoUpload($_FILES['logo']);
            if ($uploadResult['success']) {
                // מחיקת הלוגו הישן (אם קיים)
                if (!empty($company['logo'])) {
                    deleteCompanyLogo($company['logo']);
                }
                $logoPath = $uploadResult['path'];
            } else {
                $errors[] = $uploadResult['error'];
            }
        }
        
        // טיפול במחיקת לוגו
        if (isset($_POST['remove_logo']) && $_POST['remove_logo'] === '1') {
            if (!empty($company['logo'])) {
                deleteCompanyLogo($company['logo']);
            }
            $logoPath = '';
        }
        
        // אם אין שגיאות - עדכון במסד הנתונים
        if (empty($errors)) {
            // הכנת נתונים לעדכון
            $updateData = $formData;
            $updateData['logo'] = $logoPath;
            $updateData['updated_at'] = date('Y-m-d H:i:s');
            
            // עדכון במסד הנתונים
            $success = $db->update('companies', $updateData, 'id = ?', [$companyId]);
            
            if ($success) {
                $_SESSION['flash_message']['success'] = 'פרטי החברה עודכנו בהצלחה!';
                header('Location: view.php?id=' . $companyId);
                exit;
            } else {
                $errors[] = 'שגיאה בעדכון הנתונים. אנא נסה שוב.';
            }
        }
        
    } catch (Exception $e) {
        error_log("Update company error: " . $e->getMessage());
        $errors[] = 'שגיאת מערכת: ' . $e->getMessage();
    }
}

// עדכון כותרת הדף
$pageTitle = 'עריכת חברה: ' . $company['name'];
?>

<div class="page-wrapper">
    <!-- Page Header -->
    <header class="page-header">
        <div class="header-content">
            <div class="header-text">
                <h1>
                    <i class="fas fa-edit"></i>
                    עריכת חברה: <?php echo htmlspecialchars($company['name']); ?>
                </h1>
                <p>עדכון פרטי החברה, מגבלות שימוש והגדרות מנוי</p>
            </div>
            <div class="header-actions">
                <a href="view.php?id=<?php echo $companyId; ?>" class="btn btn-outline">
                    <i class="fas fa-eye"></i>
                    צפייה בחברה
                </a>
                <a href="index.php" class="btn btn-outline">
                    <i class="fas fa-arrow-right"></i>
                    חזור לרשימה
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
                        
                        <?php if ($userRole === 'super_admin'): ?>
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
                        <?php else: ?>
                        <input type="hidden" name="company_type" value="<?php echo htmlspecialchars($formData['company_type']); ?>">
                        <?php endif; ?>
                        
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

            <!-- Logo Management -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-image"></i> לוגו החברה</h3>
                </div>
                <div class="card-body">
                    
                    <?php if (!empty($company['logo'])): ?>
                    <!-- Current Logo -->
                    <div class="current-logo">
                        <label>לוגו נוכחי:</label>
                        <div class="logo-container">
                            <img src="<?php echo htmlspecialchars($company['logo']); ?>" 
                                 alt="לוגו <?php echo htmlspecialchars($company['name']); ?>" 
                                 class="current-logo-image">
                            <div class="logo-actions">
                                <label class="checkbox-label">
                                    <input type="checkbox" name="remove_logo" value="1">
                                    מחק לוגו נוכחי
                                </label>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Upload New Logo -->
                    <div class="form-group">
                        <label for="logo">
                            <?php echo !empty($company['logo']) ? 'החלף לוגו' : 'העלה לוגו'; ?>
                        </label>
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

            <?php if ($userRole === 'super_admin'): ?>
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
                                   min="0" max="1000">
                            <small class="form-help">מספר משתמשים מקסימלי. 0 = ללא הגבלה</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="max_sites">מגבלת אתרי עבודה</label>
                            <input type="number" id="max_sites" name="max_sites" class="form-control" 
                                   value="<?php echo $formData['max_sites']; ?>" 
                                   min="0" max="500">
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
            <?php else: ?>
            <!-- Hidden fields for non-super-admin users -->
            <input type="hidden" name="subscription_plan" value="<?php echo htmlspecialchars($formData['subscription_plan']); ?>">
            <input type="hidden" name="max_users" value="<?php echo $formData['max_users']; ?>">
            <input type="hidden" name="max_sites" value="<?php echo $formData['max_sites']; ?>">
            <input type="hidden" name="expires_at" value="<?php echo $formData['expires_at']; ?>">
            <input type="hidden" name="status" value="<?php echo htmlspecialchars($formData['status']); ?>">
            <?php endif; ?>

            <!-- Current Usage Info -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-chart-bar"></i> שימוש נוכחי</h3>
                </div>
                <div class="card-body">
                    <?php
                    // קבלת נתוני שימוש נוכחיים
                    $currentUsage = $db->fetchOne("
                        SELECT 
                            (SELECT COUNT(*) FROM users WHERE company_id = ? AND status = 'active') as current_users,
                            (SELECT COUNT(*) FROM worksites WHERE company_id = ? AND status = 'active') as current_sites
                    ", [$companyId, $companyId]);
                    ?>
                    
                    <div class="usage-display">
                        <div class="usage-item">
                            <div class="usage-label">משתמשים פעילים</div>
                            <div class="usage-value">
                                <?php echo number_format($currentUsage['current_users'] ?? 0); ?>
                                <?php if ($formData['max_users'] > 0): ?>
                                    <span class="usage-limit">/ <?php echo number_format($formData['max_users']); ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="usage-item">
                            <div class="usage-label">אתרי עבודה פעילים</div>
                            <div class="usage-value">
                                <?php echo number_format($currentUsage['current_sites'] ?? 0); ?>
                                <?php if ($formData['max_sites'] > 0): ?>
                                    <span class="usage-limit">/ <?php echo number_format($formData['max_sites']); ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i>
                        שינוי במגבלות יכול להשפיע על משתמשים קיימים. וודא שהמגבלות החדשות מתאימות לשימוש הנוכחי.
                    </div>
                </div>
            </div>

            <!-- Form Actions -->
            <div class="form-actions">
                <button type="submit" class="btn btn-primary btn-lg">
                    <i class="fas fa-save"></i>
                    שמור שינויים
                </button>
                
                <a href="view.php?id=<?php echo $companyId; ?>" class="btn btn-outline btn-lg">
                    <i class="fas fa-times"></i>
                    ביטול
                </a>
                
                <button type="button" class="btn btn-secondary btn-lg" onclick="resetForm()">
                    <i class="fas fa-undo"></i>
                    איפוס לנתונים המקוריים
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
    if (confirm('האם אתה בטוח שברצונך לאפס את הטופס לנתונים המקוריים?')) {
        location.reload();
    }
}

// Real-time validation and updates
document.addEventListener('DOMContentLoaded', function() {
    // Check for conflicts when changing limits
    const maxUsersField = document.getElementById('max_users');
    const maxSitesField = document.getElementById('max_sites');
    const currentUsers = <?php echo $currentUsage['current_users'] ?? 0; ?>;
    const currentSites = <?php echo $currentUsage['current_sites'] ?? 0; ?>;
    
    if (maxUsersField) {
        maxUsersField.addEventListener('change', function() {
            const newLimit = parseInt(this.value);
            if (newLimit > 0 && newLimit < currentUsers) {
                alert(`אזהרה: המגבלה החדשה (${newLimit}) נמוכה מהמספר הנוכחי של משתמשים פעילים (${currentUsers})`);
            }
        });
    }
    
    if (maxSitesField) {
        maxSitesField.addEventListener('change', function() {
            const newLimit = parseInt(this.value);
            if (newLimit > 0 && newLimit < currentSites) {
                alert(`אזהרה: המגבלה החדשה (${newLimit}) נמוכה מהמספר הנוכחי של אתרי עבודה פעילים (${currentSites})`);
            }
        });
    }
    
    // Phone number formatting
    const phoneField = document.getElementById('phone');
    if (phoneField) {
        phoneField.addEventListener('input', function() {
            let value = this.value.replace(/\D/g, '');
            if (value.length >= 10) {
                value = value.substring(0, 10);
                this.value = value.replace(/(\d{3})(\d{3})(\d{4})/, '$1-$2-$3');
            }
        });
    }
});
</script>

<?php require_once '../../includes/footer.php'; ?>
