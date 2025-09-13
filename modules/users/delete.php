<?php
/**
 * WorkSafety.io - מחיקת משתמש
 * דף אישור מחיקת משתמש עם פרטים מלאים
 */

// הגדרות דף
$pageTitle = 'מחיקת משתמש';
$pageDescription = 'אישור מחיקת משתמש מהמערכת';
$additionalCSS = ['modules/users/assets/users.css'];

// כלילת קבצים נדרשים
require_once '../../includes/header.php';

// וידוא התחברות והרשאות
if (!isset($_SESSION['user_id']) || !in_array($currentUser['role'], ['super_admin', 'company_admin'])) {
    header('Location: /login.php');
    exit;
}

// קבלת ID המשתמש למחיקה
$userId = intval($_GET['id'] ?? 0);
if (!$userId) {
    $_SESSION['flash_message']['danger'] = 'מזהה משתמש לא תקין';
    header('Location: index.php');
    exit;
}

// מניעת מחיקה עצמית
if ($userId == $_SESSION['user_id']) {
    $_SESSION['flash_message']['danger'] = 'לא ניתן למחוק את החשבון שלך';
    header('Location: index.php');
    exit;
}

// קבלת פרטי המשתמש
try {
    $db = getDB();
    
    // בדיקת הרשאה למחיקה
    $whereClause = "u.id = ?";
    $params = [$userId];
    
    if ($currentUser['role'] === 'company_admin') {
        $whereClause .= " AND u.company_id = ?";
        $params[] = $currentUser['company_id'];
    }
    
    $user = $db->fetch("
        SELECT 
            u.*,
            c.name as company_name,
            c.type as company_type,
            (SELECT COUNT(*) FROM deficiencies WHERE created_by = u.id) as deficiencies_count,
            (SELECT COUNT(*) FROM inspections WHERE inspector_id = u.id) as inspections_count,
            (SELECT COUNT(*) FROM user_permissions WHERE user_id = u.id AND is_active = 1) as permissions_count
        FROM users u 
        LEFT JOIN companies c ON u.company_id = c.id 
        WHERE {$whereClause}
    ", $params);
    
    if (!$user) {
        $_SESSION['flash_message']['danger'] = 'משתמש לא נמצא או אין לך הרשאה למחוק אותו';
        header('Location: index.php');
        exit;
    }
    
} catch (Exception $e) {
    error_log("Error fetching user for deletion: " . $e->getMessage());
    $_SESSION['flash_message']['danger'] = 'שגיאה בטעינת נתוני המשתמש';
    header('Location: index.php');
    exit;
}

// טיפול באישור המחיקה
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['confirm_delete']) && $_POST['confirm_delete'] === 'yes') {
        try {
            $db->getConnection()->beginTransaction();
            
            // מחיקת הרשאות המשתמש
            $db->delete('user_permissions', 'user_id = ?', [$userId]);
            
            // מחיקת היסטוריית כניסות
            $db->delete('user_login_history', 'user_id = ?', [$userId]);
            
            // עדכון רשומות שנוצרו על ידי המשתמש (במקום מחיקה)
            $db->update('deficiencies', ['created_by' => null], 'created_by = ?', [$userId]);
            $db->update('inspections', ['inspector_id' => null], 'inspector_id = ?', [$userId]);
            
            // מחיקת המשתמש עצמו
            $db->delete('users', 'id = ?', [$userId]);
            
            $db->getConnection()->commit();
            
            $_SESSION['flash_message']['success'] = 'המשתמש נמחק בהצלחה מהמערכת';
            header('Location: index.php');
            exit;
            
        } catch (Exception $e) {
            $db->getConnection()->rollBack();
            error_log("Delete user error: " . $e->getMessage());
            $error_message = 'שגיאה במחיקת המשתמש: ' . $e->getMessage();
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
                    <i class="fas fa-user-times"></i>
                    מחיקת משתמש
                </h1>
                <p class="header-subtitle">
                    אישור מחיקת המשתמש מהמערכת
                </p>
            </div>
            <div class="header-actions">
                <a href="view.php?id=<?php echo $user['id']; ?>" class="btn btn-outline">
                    <i class="fas fa-eye"></i>
                    צפייה
                </a>
                <a href="index.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-right"></i>
                    חזור לרשימה
                </a>
            </div>
        </div>
    </header>

    <!-- Page Content -->
    <div class="page-content">
        
        <!-- Error Message -->
        <?php if (!empty($error_message)): ?>
            <div class="alert alert-danger">
                <div class="alert-icon">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <div class="alert-content">
                    <div class="alert-title">שגיאה במחיקה</div>
                    <?php echo htmlspecialchars($error_message); ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Delete Confirmation -->
        <div class="delete-confirmation-container">
            
            <!-- Warning Card -->
            <div class="warning-card">
                <div class="warning-icon">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <div class="warning-content">
                    <h2>אזהרה - מחיקת משתמש</h2>
                    <p>אתה עומד למחוק את המשתמש מהמערכת. פעולה זו <strong>אינה ניתנת לביטול</strong>!</p>
                </div>
            </div>

            <!-- User Details -->
            <div class="user-details-card">
                <div class="card-header">
                    <h3>פרטי המשתמש שיימחק</h3>
                </div>
                <div class="card-body">
                    <div class="user-summary">
                        <div class="user-avatar-large">
                            <?php echo strtoupper(mb_substr($user['first_name'], 0, 1) . mb_substr($user['last_name'], 0, 1)); ?>
                        </div>
                        <div class="user-info">
                            <h3><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></h3>
                            <p class="user-email">
                                <i class="fas fa-envelope"></i>
                                <?php echo htmlspecialchars($user['email']); ?>
                            </p>
                            <p class="user-role">
                                <i class="fas fa-user-tag"></i>
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
                            </p>
                            <?php if ($user['company_name']): ?>
                            <p class="user-company">
                                <i class="fas fa-building"></i>
                                <?php echo htmlspecialchars($user['company_name']); ?>
                            </p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Impact Analysis -->
            <div class="impact-analysis-card">
                <div class="card-header">
                    <h3>השפעת המחיקה על המערכת</h3>
                </div>
                <div class="card-body">
                    <div class="impact-grid">
                        
                        <div class="impact-item">
                            <div class="impact-icon">
                                <i class="fas fa-exclamation-triangle"></i>
                            </div>
                            <div class="impact-details">
                                <div class="impact-number"><?php echo number_format($user['deficiencies_count']); ?></div>
                                <div class="impact-label">ליקויים שנוצרו</div>
                                <div class="impact-description">הליקויים יישארו במערכת ללא יוצר</div>
                            </div>
                        </div>
                        
                        <div class="impact-item">
                            <div class="impact-icon">
                                <i class="fas fa-clipboard-check"></i>
                            </div>
                            <div class="impact-details">
                                <div class="impact-number"><?php echo number_format($user['inspections_count']); ?></div>
                                <div class="impact-label">בדיקות שבוצעו</div>
                                <div class="impact-description">הבדיקות יישארו במערכת ללא מפקח</div>
                            </div>
                        </div>
                        
                        <div class="impact-item">
                            <div class="impact-icon">
                                <i class="fas fa-key"></i>
                            </div>
                            <div class="impact-details">
                                <div class="impact-number"><?php echo number_format($user['permissions_count']); ?></div>
                                <div class="impact-label">הרשאות פעילות</div>
                                <div class="impact-description">כל ההרשאות יימחקו</div>
                            </div>
                        </div>
                        
                        <div class="impact-item">
                            <div class="impact-icon">
                                <i class="fas fa-history"></i>
                            </div>
                            <div class="impact-details">
                                <div class="impact-number">∞</div>
                                <div class="impact-label">היסטוריית כניסות</div>
                                <div class="impact-description">כל ההיסטוריה תימחק</div>
                            </div>
                        </div>
                        
                    </div>
                    
                    <div class="impact-warning">
                        <i class="fas fa-info-circle"></i>
                        <strong>חשוב לדעת:</strong> 
                        הנתונים שיצר המשתמש (ליקויים, בדיקות) יישארו במערכת אך ללא קישור אליו. 
                        רק פרטי החשבון והרשאותיו יימחקו לחלוטין.
                    </div>
                </div>
            </div>

            <!-- Confirmation Form -->
            <div class="confirmation-form-card">
                <div class="card-header">
                    <h3>אישור המחיקה</h3>
                </div>
                <div class="card-body">
                    <form method="POST" id="deleteForm">
                        
                        <div class="confirmation-text">
                            <p>כדי לאשר את מחיקת המשתמש, אנא אשר שאתה מבין את ההשלכות:</p>
                        </div>
                        
                        <div class="confirmation-checkboxes">
                            <label class="checkbox-item">
                                <input type="checkbox" id="understand1" required>
                                <span>אני מבין שמחיקת המשתמש אינה ניתנת לביטול</span>
                            </label>
                            
                            <label class="checkbox-item">
                                <input type="checkbox" id="understand2" required>
                                <span>אני מבין שההרשאות וההיסטוריה יימחקו לחלוטין</span>
                            </label>
                            
                            <label class="checkbox-item">
                                <input type="checkbox" id="understand3" required>
                                <span>אישרתי שאין חלופה אחרת לפעולה זו</span>
                            </label>
                        </div>
                        
                        <div class="confirmation-input">
                            <label for="confirm_text">הקלד "מחק משתמש" כדי לאשר:</label>
                            <input type="text" 
                                   id="confirm_text" 
                                   class="form-control" 
                                   placeholder="מחק משתמש"
                                   required>
                        </div>
                        
                        <input type="hidden" name="confirm_delete" value="yes">
                        
                        <div class="form-actions">
                            <button type="submit" class="btn btn-danger btn-lg" id="deleteButton" disabled>
                                <i class="fas fa-trash"></i>
                                מחק משתמש סופית
                            </button>
                            
                            <a href="view.php?id=<?php echo $user['id']; ?>" class="btn btn-outline btn-lg">
                                <i class="fas fa-times"></i>
                                ביטול
                            </a>
                        </div>
                    </form>
                </div>
            </div>
            
        </div>
        
    </div>
</main>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const checkboxes = document.querySelectorAll('.confirmation-checkboxes input[type="checkbox"]');
    const confirmText = document.getElementById('confirm_text');
    const deleteButton = document.getElementById('deleteButton');
    const deleteForm = document.getElementById('deleteForm');
    
    function checkFormValidity() {
        const allChecked = Array.from(checkboxes).every(cb => cb.checked);
        const textCorrect = confirmText.value.trim() === 'מחק משתמש';
        
        deleteButton.disabled = !(allChecked && textCorrect);
        
        if (allChecked && textCorrect) {
            deleteButton.classList.add('enabled');
        } else {
            deleteButton.classList.remove('enabled');
        }
    }
    
    checkboxes.forEach(checkbox => {
        checkbox.addEventListener('change', checkFormValidity);
    });
    
    confirmText.addEventListener('input', checkFormValidity);
    
    deleteForm.addEventListener('submit', function(e) {
        if (!confirm('האם אתה בטוח לחלוטין שברצונך למחוק את המשתמש? פעולה זו אינה ניתנת לביטול!')) {
            e.preventDefault();
            return false;
        }
        
        deleteButton.disabled = true;
        deleteButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> מוחק...';
    });
});
</script>

<!-- Include Footer -->
<?php 
$additionalFooter = "
<style>
.delete-confirmation-container {
    max-width: 800px;
    margin: 0 auto;
    display: flex;
    flex-direction: column;
    gap: 2rem;
}

.warning-card {
    background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
    border: 2px solid #f59e0b;
    border-radius: var(--radius-lg);
    padding: 2rem;
    display: flex;
    align-items: center;
    gap: 2rem;
    text-align: center;
}

.warning-icon {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    background: #f59e0b;
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2.5rem;
    flex-shrink: 0;
    animation: pulse 2s infinite;
}

.warning-content h2 {
    color: #92400e;
    margin-bottom: 1rem;
    font-size: 1.75rem;
}

.warning-content p {
    color: #78350f;
    font-size: 1.1rem;
    margin: 0;
}

.user-details-card,
.impact-analysis-card,
.confirmation-form-card {
    background: white;
    border-radius: var(--radius-lg);
    box-shadow: var(--shadow-md);
    border: 1px solid #e2e8f0;
    overflow: hidden;
}

.card-header {
    background: var(--bg-secondary);
    padding: 1.5rem 2rem;
    border-bottom: 1px solid #e2e8f0;
}

.card-header h3 {
    margin: 0;
    color: var(--text-primary);
    font-size: 1.25rem;
    font-weight: 600;
}

.card-body {
    padding: 2rem;
}

.user-summary {
    display: flex;
    align-items: center;
    gap: 2rem;
}

.user-avatar-large {
    width: 100px;
    height: 100px;
    border-radius: 50%;
    background: var(--danger-color);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2.5rem;
    font-weight: 700;
    flex-shrink: 0;
}

.user-info h3 {
    margin: 0 0 1rem 0;
    font-size: 2rem;
    color: var(--text-primary);
}

.user-info p {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    margin: 0.5rem 0;
    color: var(--text-secondary);
    font-size: 1.1rem;
}

.user-info i {
    width: 20px;
    color: var(--text-light);
}

.impact-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.impact-item {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1.5rem;
    background: var(--bg-secondary);
    border-radius: var(--radius-md);
    border: 1px solid #e2e8f0;
}

.impact-icon {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    background: var(--warning-color);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    flex-shrink: 0;
}

.impact-details {
    flex: 1;
}

.impact-number {
    font-size: 2rem;
    font-weight: 800;
    color: var(--text-primary);
    line-height: 1;
}

.impact-label {
    font-weight: 600;
    color: var(--text-primary);
    margin: 0.25rem 0;
    font-size: 0.95rem;
}

.impact-description {
    font-size: 0.85rem;
    color: var(--text-light);
    line-height: 1.3;
}

.impact-warning {
    background: #eff6ff;
    border: 1px solid #3b82f6;
    border-radius: var(--radius-md);
    padding: 1.5rem;
    display: flex;
    align-items: flex-start;
    gap: 1rem;
    color: #1e40af;
}

.impact-warning i {
    font-size: 1.25rem;
    margin-top: 0.125rem;
    flex-shrink: 0;
}

.confirmation-text {
    margin-bottom: 2rem;
}

.confirmation-text p {
    font-size: 1.1rem;
    color: var(--text-secondary);
    line-height: 1.6;
}

.confirmation-checkboxes {
    display: flex;
    flex-direction: column;
    gap: 1rem;
    margin-bottom: 2rem;
}

.checkbox-item {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1rem;
    background: var(--bg-secondary);
    border-radius: var(--radius-md);
    border: 2px solid #e2e8f0;
    cursor: pointer;
    transition: var(--transition-fast);
}

.checkbox-item:hover {
    border-color: var(--primary-color);
    background: #eff6ff;
}

.checkbox-item input[type='checkbox'] {
    transform: scale(1.3);
    accent-color: var(--primary-color);
}

.checkbox-item span {
    font-weight: 500;
    color: var(--text-primary);
}

.confirmation-input {
    margin-bottom: 2rem;
}

.confirmation-input label {
    display: block;
    margin-bottom: 0.75rem;
    font-weight: 600;
    color: var(--text-primary);
}

.form-actions {
    display: flex;
    gap: 1rem;
    justify-content: center;
    padding-top: 2rem;
    border-top: 1px solid #e2e8f0;
}

.btn-danger.enabled {
    animation: dangerPulse 2s infinite;
}

@keyframes dangerPulse {
    0%, 100% { 
        transform: scale(1);
        box-shadow: 0 4px 6px rgba(239, 68, 68, 0.1);
    }
    50% { 
        transform: scale(1.02);
        box-shadow: 0 8px 15px rgba(239, 68, 68, 0.3);
    }
}

@media (max-width: 768px) {
    .warning-card {
        flex-direction: column;
        text-align: center;
    }
    
    .warning-icon {
        width: 60px;
        height: 60px;
        font-size: 2rem;
    }
    
    .user-summary {
        flex-direction: column;
        text-align: center;
    }
    
    .user-avatar-large {
        width: 80px;
        height: 80px;
        font-size: 2rem;
    }
    
    .impact-grid {
        grid-template-columns: 1fr;
    }
    
    .impact-item {
        flex-direction: column;
        text-align: center;
    }
    
    .form-actions {
        flex-direction: column;
    }
}
</style>
";

include '../../includes/footer.php'; 
?>
