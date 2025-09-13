<?php
/**
 * WorkSafety.io - הוספת משתמש חדש
 * טופס הוספת משתמש חדש עם אימותים ואבטחה
 */

// הגדרות דף
$pageTitle = 'הוספת משתמש חדש';
$pageDescription = 'הוספת משתמש חדש למערכת עם הגדרת הרשאות ופרטים אישיים';
$additionalCSS = ['/modules/users/assets/users.css'];
$additionalJS = ['/modules/users/assets/user-form.js'];

// כלילת קבצים נדרשים
require_once '../../includes/header.php';

// וידוא התחברות והרשאות
if (!isset($_SESSION['user_id'])) {
    header('Location: /login.php');
    exit;
}

$userRole = $currentUser['role'] ?? 'worker';
if (!in_array($userRole, ['super_admin', 'company_admin'])) {
    $_SESSION['flash_message']['danger'] = 'אין לך הרשאה לגשת לעמוד זה';
    header('Location: /dashboard.php');
    exit;
}

$errors = [];
$formData = [];

try {
    $db = getDB();
    
    // קבלת רשימת החברות (למנהל ראשי בלבד)
    $companies = [];
    if ($userRole === 'super_admin') {
        $companies = $db->fetchAll("SELECT id, name FROM companies WHERE status = 'active' ORDER BY name");
    }
    
} catch (Exception $e) {
    error_log("Add user page error: " . $e->getMessage());
    $companies = [];
}

// טיפול בשליחת הטופס
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formData = $_POST;
    
    // אימותי שדות
    if (empty($_POST['first_name'])) {
        $errors['first_name'] = 'שם פרטי הוא שדה חובה';
    }
    
    if (empty($_POST['last_name'])) {
        $errors['last_name'] = 'שם משפחה הוא שדה חובה';
    }
    
    if (empty($_POST['email'])) {
        $errors['email'] = 'כתובת אימייל היא שדה חובה';
    } elseif (!filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'כתובת אימייל לא תקינה';
    } else {
        // בדיקה שהאימייל לא קיים במערכת
        try {
            $stmt = $db->query("SELECT id FROM users WHERE email = ?", [$_POST['email']]);
            if ($stmt->fetch()) {
                $errors['email'] = 'כתובת אימייל זו כבר קיימת במערכת';
            }
        } catch (Exception $e) {
            $errors['email'] = 'שגיאה בבדיקת האימייל';
        }
    }
    
    if (empty($_POST['password'])) {
        $errors['password'] = 'סיסמה היא שדה חובה';
    } elseif (strlen($_POST['password']) < 6) {
        $errors['password'] = 'הסיסמה חייבת להכיל לפחות 6 תווים';
    }
    
    if ($_POST['password'] !== $_POST['password_confirm']) {
        $errors['password_confirm'] = 'אימות הסיסמה אינו תואם';
    }
    
    if (empty($_POST['role'])) {
        $errors['role'] = 'תפקיד הוא שדה חובה';
    }
    
    // אם מנהל חברה - אז החברה היא החברה שלו
    if ($userRole === 'company_admin') {
        $_POST['company_id'] = $currentUser['company_id'];
    } elseif ($userRole === 'super_admin' && empty($_POST['company_id'])) {
        $errors['company_id'] = 'חברה היא שדה חובה';
    }
    
    // אם אין שגיאות - נשמור את המשתמש
    if (empty($errors)) {
        try {
            $userData = [
                'company_id' => $_POST['company_id'],
                'email' => $_POST['email'],
                'password' => password_hash($_POST['password'], PASSWORD_DEFAULT),
                'first_name' => $_POST['first_name'],
                'last_name' => $_POST['last_name'],
                'phone' => $_POST['phone'] ?? null,
                'mobile' => $_POST['mobile'] ?? null,
                'id_number' => $_POST['id_number'] ?? null,
                'role' => $_POST['role'],
                'department' => $_POST['department'] ?? null,
                'position' => $_POST['position'] ?? null,
                'status' => $_POST['status'] ?? 'active',
                'created_by' => $_SESSION['user_id'],
                'updated_by' => $_SESSION['user_id']
            ];
            
            $newUserId = $db->insert('users', $userData);
            
            if ($newUserId) {
                $_SESSION['flash_message']['success'] = 'המשתמש נוצר בהצלחה';
                header('Location: /modules/users/edit.php?id=' . $newUserId);
                exit;
            } else {
                $errors['general'] = 'שגיאה ביצירת המשתמש';
            }
            
        } catch (Exception $e) {
            error_log("Create user error: " . $e->getMessage());
            $errors['general'] = 'שגיאה ביצירת המשתמש במסד הנתונים';
        }
    }
}
?>

<!-- Include Sidebar -->
<?php include '../../includes/sidebar.php'; ?>

<!-- Main Content -->
<main class="main-content">
    <!-- Header -->
    <header class="main-header">
        <div class="header-content">
            <div class="header-left">
                <h1 class="header-title">
                    <i class="fas fa-user-plus"></i>
                    הוספת משתמש חדש
                </h1>
                <p class="header-subtitle">
                    הוספת משתמש חדש למערכת עם הגדרת הרשאות ופרטים אישיים
                </p>
            </div>
            <div class="header-actions">
                <a href="/modules/users/" class="btn btn-outline btn-sm">
                    <i class="fas fa-arrow-right"></i>
                    חזרה לרשימה
                </a>
            </div>
        </div>
    </header>

    <!-- Page Content -->
    <div class="page-content">
        
        <?php if (!empty($errors['general'])): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-triangle"></i>
                <div class="alert-content">
                    <div class="alert-title">שגיאה</div>
                    <?php echo htmlspecialchars($errors['general']); ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- User Form -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">פרטי המשתמש החדש</h3>
            </div>
            <div class="card-body">
                <form method="POST" id="userForm" class="user-form">
                    
                    <!-- Personal Information Section -->
                    <div class="form-section">
                        <h4 class="section-title">
                            <i class="fas fa-user"></i>
                            פרטים אישיים
                        </h4>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label" for="first_name">שם פרטי *</label>
                                <input type="text" 
                                       id="first_name" 
                                       name="first_name" 
                                       class="form-control <?php echo isset($errors['first_name']) ? 'error' : ''; ?>" 
                                       value="<?php echo htmlspecialchars($formData['first_name'] ?? ''); ?>"
                                       required>
                                <?php if (isset($errors['first_name'])): ?>
                                    <div class="field-error"><?php echo $errors['first_name']; ?></div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label" for="last_name">שם משפחה *</label>
                                <input type="text" 
                                       id="last_name" 
                                       name="last_name" 
                                       class="form-control <?php echo isset($errors['last_name']) ? 'error' : ''; ?>" 
                                       value="<?php echo htmlspecialchars($formData['last_name'] ?? ''); ?>"
                                       required>
                                <?php if (isset($errors['last_name'])): ?>
                                    <div class="field-error"><?php echo $errors['last_name']; ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label" for="id_number">מספר זהות</label>
                                <input type="text" 
                                       id="id_number" 
                                       name="id_number" 
                                       class="form-control" 
                                       value="<?php echo htmlspecialchars($formData['id_number'] ?? ''); ?>"
                                       pattern="[0-9]{9}"
                                       title="מספר זהות חייב להכיל 9 ספרות">
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label" for="phone">טלפון</label>
                                <input type="tel" 
                                       id="phone" 
                                       name="phone" 
                                       class="form-control" 
                                       value="<?php echo htmlspecialchars($formData['phone'] ?? ''); ?>"
                                       placeholder="03-1234567">
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label" for="mobile">נייד</label>
                                <input type="tel" 
                                       id="mobile" 
                                       name="mobile" 
                                       class="form-control" 
                                       value="<?php echo htmlspecialchars($formData['mobile'] ?? ''); ?>"
                                       placeholder="050-1234567">
                            </div>
                        </div>
                    </div>
                    
                    <!-- Account Information Section -->
                    <div class="form-section">
                        <h4 class="section-title">
                            <i class="fas fa-envelope"></i>
                            פרטי חשבון
                        </h4>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label" for="email">כתובת אימייל *</label>
                                <input type="email" 
                                       id="email" 
                                       name="email" 
                                       class="form-control <?php echo isset($errors['email']) ? 'error' : ''; ?>" 
                                       value="<?php echo htmlspecialchars($formData['email'] ?? ''); ?>"
                                       required>
                                <?php if (isset($errors['email'])): ?>
                                    <div class="field-error"><?php echo $errors['email']; ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label" for="password">סיסמה *</label>
                                <div class="password-wrapper">
                                    <input type="password" 
                                           id="password" 
                                           name="password" 
                                           class="form-control <?php echo isset($errors['password']) ? 'error' : ''; ?>" 
                                           required
                                           minlength="6">
                                    <button type="button" class="password-toggle" onclick="togglePassword('password')">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                                <?php if (isset($errors['password'])): ?>
                                    <div class="field-error"><?php echo $errors['password']; ?></div>
                                <?php endif; ?>
                                <small class="form-hint">הסיסמה חייבת להכיל לפחות 6 תווים</small>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label" for="password_confirm">אימות סיסמה *</label>
                                <div class="password-wrapper">
                                    <input type="password" 
                                           id="password_confirm" 
                                           name="password_confirm" 
                                           class="form-control <?php echo isset($errors['password_confirm']) ? 'error' : ''; ?>" 
                                           required
                                           minlength="6">
                                    <button type="button" class="password-toggle" onclick="togglePassword('password_confirm')">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                                <?php if (isset($errors['password_confirm'])): ?>
                                    <div class="field-error"><?php echo $errors['password_confirm']; ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Work Information Section -->
                    <div class="form-section">
                        <h4 class="section-title">
                            <i class="fas fa-briefcase"></i>
                            פרטי עבודה
                        </h4>
                        
                        <?php if ($userRole === 'super_admin'): ?>
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label" for="company_id">חברה *</label>
                                <select id="company_id" 
                                        name="company_id" 
                                        class="form-control <?php echo isset($errors['company_id']) ? 'error' : ''; ?>" 
                                        required>
                                    <option value="">בחר חברה</option>
                                    <?php foreach ($companies as $company): ?>
                                        <option value="<?php echo $company['id']; ?>" 
                                                <?php echo ($formData['company_id'] ?? '') == $company['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($company['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <?php if (isset($errors['company_id'])): ?>
                                    <div class="field-error"><?php echo $errors['company_id']; ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label" for="role">תפקיד *</label>
                                <select id="role" 
                                        name="role" 
                                        class="form-control <?php echo isset($errors['role']) ? 'error' : ''; ?>" 
                                        required>
                                    <option value="">בחר תפקיד</option>
                                    <?php if ($userRole === 'super_admin'): ?>
                                        <option value="super_admin" <?php echo ($formData['role'] ?? '') === 'super_admin' ? 'selected' : ''; ?>>מנהל ראשי</option>
                                        <option value="company_admin" <?php echo ($formData['role'] ?? '') === 'company_admin' ? 'selected' : ''; ?>>מנהל חברה</option>
                                    <?php endif; ?>
                                    <option value="contractor" <?php echo ($formData['role'] ?? '') === 'contractor' ? 'selected' : ''; ?>>קבלן</option>
                                    <option value="safety_manager" <?php echo ($formData['role'] ?? '') === 'safety_manager' ? 'selected' : ''; ?>>מנהל בטיחות</option>
                                    <option value="inspector" <?php echo ($formData['role'] ?? '') === 'inspector' ? 'selected' : ''; ?>>מפקח</option>
                                    <option value="worker" <?php echo ($formData['role'] ?? '') === 'worker' ? 'selected' : ''; ?>>עובד</option>
                                </select>
                                <?php if (isset($errors['role'])): ?>
                                    <div class="field-error"><?php echo $errors['role']; ?></div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label" for="status">סטטוס</label>
                                <select id="status" name="status" class="form-control">
                                    <option value="active" <?php echo ($formData['status'] ?? 'active') === 'active' ? 'selected' : ''; ?>>פעיל</option>
                                    <option value="inactive" <?php echo ($formData['status'] ?? '') === 'inactive' ? 'selected' : ''; ?>>לא פעיל</option>
                                    <option value="suspended" <?php echo ($formData['status'] ?? '') === 'suspended' ? 'selected' : ''; ?>>מושהה</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label" for="department">מחלקה</label>
                                <input type="text" 
                                       id="department" 
                                       name="department" 
                                       class="form-control" 
                                       value="<?php echo htmlspecialchars($formData['department'] ?? ''); ?>"
                                       placeholder="למשל: בטיחות, תפעול, הנדסה">
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label" for="position">תפקיד מפורט</label>
                                <input type="text" 
                                       id="position" 
                                       name="position" 
                                       class="form-control" 
                                       value="<?php echo htmlspecialchars($formData['position'] ?? ''); ?>"
                                       placeholder="למשל: מהנדס בטיחות, מפקח עבודות">
                            </div>
                        </div>
                    </div>
                    
                    <!-- Form Actions -->
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i>
                            צור משתמש
                        </button>
                        <a href="/modules/users/" class="btn btn-outline">
                            <i class="fas fa-times"></i>
                            ביטול
                        </a>
                    </div>
                    
                </form>
            </div>
        </div>
        
    </div>
</main>

<!-- Include Footer -->
<?php include '../../includes/footer.php'; ?>

<style>
/* Additional styles for user form */
.user-form {
    max-width: 800px;
}

.form-section {
    margin-bottom: 3rem;
    padding-bottom: 2rem;
    border-bottom: 1px solid #e2e8f0;
}

.form-section:last-of-type {
    border-bottom: none;
    margin-bottom: 2rem;
}

.section-title {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    font-size: 1.25rem;
    font-weight: 600;
    color: var(--text-primary);
    margin-bottom: 1.5rem;
    padding-bottom: 0.75rem;
    border-bottom: 2px solid var(--primary-color);
}

.section-title i {
    color: var(--primary-color);
}

.form-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1.5rem;
    margin-bottom: 1.5rem;
}

.form-group {
    display: flex;
    flex-direction: column;
}

.form-label {
    margin-bottom: 0.5rem;
    font-weight: 500;
    color: var(--text-primary);
}

.form-control {
    padding: 0.75rem;
    border: 2px solid #e2e8f0;
    border-radius: var(--radius-md);
    font-size: 1rem;
    transition: var(--transition-fast);
}

.form-control:focus {
    outline: none;
    border-color: var(--primary-color);
    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
}

.form-control.error {
    border-color: var(--danger-color);
    box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.1);
}

.field-error {
    color: var(--danger-color);
    font-size: 0.875rem;
    margin-top: 0.25rem;
}

.form-hint {
    color: var(--text-light);
    font-size: 0.875rem;
    margin-top: 0.25rem;
}

.password-wrapper {
    position: relative;
}

.password-toggle {
    position: absolute;
    left: 12px;
    top: 50%;
    transform: translateY(-50%);
    background: none;
    border: none;
    color: var(--text-light);
    cursor: pointer;
    padding: 0.25rem;
}

.password-toggle:hover {
    color: var(--primary-color);
}

.form-actions {
    display: flex;
    gap: 1rem;
    padding-top: 2rem;
    border-top: 1px solid #e2e8f0;
}

@media (max-width: 768px) {
    .form-row {
        grid-template-columns: 1fr;
        gap: 1rem;
    }
    
    .form-actions {
        flex-direction: column;
    }
}
</style>

<script>
function togglePassword(fieldId) {
    const field = document.getElementById(fieldId);
    const toggle = field.nextElementSibling.querySelector('i');
    
    if (field.type === 'password') {
        field.type = 'text';
        toggle.className = 'fas fa-eye-slash';
    } else {
        field.type = 'password';
        toggle.className = 'fas fa-eye';
    }
}

// Password match validation
document.getElementById('password_confirm').addEventListener('input', function() {
    const password = document.getElementById('password').value;
    const confirm = this.value;
    
    if (confirm && password !== confirm) {
        this.setCustomValidity('הסיסמאות אינן תואמות');
    } else {
        this.setCustomValidity('');
    }
});

console.log('✅ Add User page loaded');
</script>
