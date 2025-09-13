<?php
/**
 * WorkSafety.io - Header גלובלי
 * תמיכה מלאה בעברית RTL ואזור זמן ישראל
 */

// וידוא הפעלת session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// כלילת קובץ הגדרות
require_once __DIR__ . '/../config/database.php';

// קבלת פרטי המשתמש הנוכחי
$currentUser = null;
if (isset($_SESSION['user_id'])) {
    try {
        $db = getDB();
        $stmt = $db->query("
            SELECT u.*, COALESCE(c.company_name, 'החברה שלי') as company_name, 
                   COALESCE(c.company_type, 'client') as company_type
            FROM users u 
            LEFT JOIN companies c ON u.company_id = c.id 
            WHERE u.id = ?
        ", [$_SESSION['user_id']]);
        
        $currentUser = $stmt->fetch();
        
        // אם לא נמצא משתמש - נקה session
        if (!$currentUser) {
            session_destroy();
            header('Location: /login.php');
            exit;
        }
    } catch (Exception $e) {
        // אם יש בעיה עם הטבלאות, השתמש בנתונים מה-session
        $currentUser = [
            'id' => $_SESSION['user_id'],
            'full_name' => $_SESSION['user_name'] ?? 'משתמש',
            'email' => $_SESSION['user_email'] ?? '',
            'role' => $_SESSION['user_role'] ?? 'worker',
            'company_id' => $_SESSION['company_id'] ?? null,
            'company_name' => $_SESSION['company_name'] ?? 'החברה שלי',
            'company_type' => $_SESSION['company_type'] ?? 'client'
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    
    <!-- SEO Meta Tags -->
    <title><?php echo isset($pageTitle) ? $pageTitle . ' | ' : ''; ?>WorkSafety.io - מערכת ניהול בטיחות תעשייתית</title>
    <meta name="description" content="<?php echo isset($pageDescription) ? $pageDescription : 'מערכת מתקדמת לניהול בטיחות תעשייתית, בנייה ותשתיות. מעקב אחר בקרות בטיחות, ניהול ציוד וכלי עבודה, דוחות מפורטים ועוד.'; ?>">
    <meta name="keywords" content="בטיחות, בטיחות עבודה, בנייה, תשתיות, ניהול בטיחות, בקרות בטיחות, כיבוי אש, ציוד הנדסי">
    <meta name="author" content="WorkSafety.io">
    <meta name="robots" content="noindex, nofollow"> <!-- רק לפיתוח -->
    
    <!-- Open Graph Meta Tags -->
    <meta property="og:type" content="website">
    <meta property="og:title" content="<?php echo isset($pageTitle) ? $pageTitle . ' | ' : ''; ?>WorkSafety.io">
    <meta property="og:description" content="<?php echo isset($pageDescription) ? $pageDescription : 'מערכת מתקדמת לניהול בטיחות תעשייתית'; ?>">
    <meta property="og:url" content="<?php echo SITE_URL . $_SERVER['REQUEST_URI']; ?>">
    <meta property="og:site_name" content="WorkSafety.io">
    <meta property="og:locale" content="he_IL">
    
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="/assets/images/favicon.ico">
    <link rel="apple-touch-icon" sizes="180x180" href="/assets/images/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="/assets/images/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/assets/images/favicon-16x16.png">
    
    <!-- CSS Files -->
    <link rel="stylesheet" href="/assets/css/global.css?v=<?php echo time(); ?>">
    
    <!-- Chart.js for Analytics -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-adapter-date-fns@3.0.0/dist/chartjs-adapter-date-fns.bundle.min.js"></script>
    
    <!-- Additional CSS if specified -->
    <?php if (isset($additionalCSS) && is_array($additionalCSS)): ?>
        <?php foreach ($additionalCSS as $css): ?>
            <link rel="stylesheet" href="<?php echo $css; ?>?v=<?php echo time(); ?>">
        <?php endforeach; ?>
    <?php endif; ?>
    
    <!-- Page-specific head content -->
    <?php if (isset($additionalHead)): ?>
        <?php echo $additionalHead; ?>
    <?php endif; ?>
    
    <style>
        /* CSS טעינה ראשונית - תוקן ושופר */
        .initial-loader {
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 10000;
            transition: opacity 0.5s ease-out;
            font-family: 'Assistant', -apple-system, BlinkMacSystemFont, sans-serif;
        }
        
        .initial-loader.hidden {
            opacity: 0;
            pointer-events: none;
        }
        
        .loader-content {
            text-align: center;
            color: white;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 2rem;
            max-width: 500px;
            width: 100%;
        }
        
        .loader-logo {
            width: 120px;
            height: 120px;
            margin-bottom: 30px;
            background: rgba(255, 255, 255, 0.15);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3.5rem;
            color: white;
            animation: pulse 2s infinite ease-in-out;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2);
            backdrop-filter: blur(10px);
            border: 2px solid rgba(255, 255, 255, 0.2);
        }
        
        .loader-text {
            font-size: 2.2rem;
            font-weight: 800;
            margin-bottom: 15px;
            text-shadow: 0 2px 10px rgba(0, 0, 0, 0.3);
            letter-spacing: 1px;
        }
        
        .loader-subtext {
            font-size: 1.2rem;
            opacity: 0.9;
            line-height: 1.5;
            text-shadow: 0 1px 5px rgba(0, 0, 0, 0.2);
            margin-bottom: 30px;
        }
        
        .loader-progress {
            width: 200px;
            height: 4px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 2px;
            overflow: hidden;
            margin-top: 20px;
        }
        
        .loader-progress-bar {
            width: 0%;
            height: 100%;
            background: white;
            border-radius: 2px;
            animation: loading 2s ease-in-out infinite;
        }
        
        @keyframes pulse {
            0%, 100% { 
                transform: scale(1);
                box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2);
            }
            50% { 
                transform: scale(1.1);
                box-shadow: 0 25px 50px rgba(0, 0, 0, 0.3);
            }
        }
        
        @keyframes loading {
            0% { width: 0%; }
            50% { width: 70%; }
            100% { width: 100%; }
        }
        
        /* רספונסיביות לדף טעינה */
        @media (max-width: 768px) {
            .loader-logo {
                width: 100px;
                height: 100px;
                font-size: 3rem;
                margin-bottom: 25px;
            }
            
            .loader-text {
                font-size: 1.8rem;
                margin-bottom: 12px;
            }
            
            .loader-subtext {
                font-size: 1rem;
                margin-bottom: 25px;
            }
            
            .loader-progress {
                width: 150px;
            }
        }
        
        @media (max-width: 480px) {
            .loader-content {
                padding: 1rem;
            }
            
            .loader-logo {
                width: 80px;
                height: 80px;
                font-size: 2.5rem;
                margin-bottom: 20px;
            }
            
            .loader-text {
                font-size: 1.5rem;
                margin-bottom: 10px;
            }
            
            .loader-subtext {
                font-size: 0.9rem;
                margin-bottom: 20px;
            }
        }
    </style>
</head>
<body>
    <!-- Initial Loading Screen -->
    <div class="initial-loader" id="initialLoader">
        <div class="loader-content">
            <div class="loader-logo">
                <i class="fas fa-shield-alt"></i>
            </div>
            <div class="loader-text">WorkSafety.io</div>
            <div class="loader-subtext">מערכת ניהול בטיחות תעשייתית מתקדמת</div>
            <div class="loader-progress">
                <div class="loader-progress-bar"></div>
            </div>
        </div>
    </div>

    <!-- Main App Container -->
    <div class="app-container" style="opacity: 0; visibility: hidden; transition: opacity 0.5s ease, visibility 0.5s ease;">
        
        <!-- Flash Messages -->
        <?php if (isset($_SESSION['flash_message'])): ?>
            <div class="flash-messages">
                <?php foreach ($_SESSION['flash_message'] as $type => $message): ?>
                    <div class="alert alert-<?php echo $type; ?>" role="alert">
                        <i class="alert-icon fas <?php echo $type === 'success' ? 'fa-check-circle' : ($type === 'warning' ? 'fa-exclamation-triangle' : ($type === 'danger' ? 'fa-exclamation-circle' : 'fa-info-circle')); ?>"></i>
                        <div class="alert-content">
                            <div class="alert-title">
                                <?php 
                                echo $type === 'success' ? 'הצלחה' : ($type === 'warning' ? 'אזהרה' : ($type === 'danger' ? 'שגיאה' : 'מידע'));
                                ?>
                            </div>
                            <?php echo htmlspecialchars($message); ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php unset($_SESSION['flash_message']); ?>
        <?php endif; ?>

        <!-- Global JavaScript Variables -->
        <script>
            window.WorkSafetyConfig = {
                siteUrl: '<?php echo SITE_URL; ?>',
                currentUser: <?php echo $currentUser ? json_encode($currentUser) : 'null'; ?>,
                isLoggedIn: <?php echo isset($_SESSION['user_id']) ? 'true' : 'false'; ?>,
                isRTL: true,
                locale: 'he-IL',
                timezone: 'Asia/Jerusalem',
                csrfToken: '<?php echo isset($_SESSION['csrf_token']) ? $_SESSION['csrf_token'] : ''; ?>'
            };
        </script>

        <script>
            // הסתרת loader לאחר טעינת הדף - תוקן ושופר
            document.addEventListener('DOMContentLoaded', function() {
                // וידוא שכל המשאבים נטענו
                window.addEventListener('load', function() {
                    setTimeout(function() {
                        const loader = document.getElementById('initialLoader');
                        const appContainer = document.querySelector('.app-container');
                        
                        if (loader && appContainer) {
                            // הצגת התוכן הראשי
                            appContainer.style.opacity = '1';
                            appContainer.style.visibility = 'visible';
                            
                            // הסתרת הloader
                            loader.classList.add('hidden');
                            
                            // הסרת הloader לחלוטין אחרי האנימציה
                            setTimeout(() => {
                                loader.remove();
                            }, 600);
                        } else {
                            // אם אין app-container (כמו בדף התחברות), פשוט הסתר את הloader
                            if (loader) {
                                loader.classList.add('hidden');
                                setTimeout(() => {
                                    loader.remove();
                                }, 600);
                            }
                            
                            // הצג את התוכן
                            document.body.style.opacity = '1';
                            document.body.style.visibility = 'visible';
                        }
                    }, 800); // זמן טעינה של 0.8 שניות
                });
                
                // אם הטעינה לוקחת יותר מ-5 שניות, הסתר בכל מקרה
                setTimeout(function() {
                    const loader = document.getElementById('initialLoader');
                    if (loader && !loader.classList.contains('hidden')) {
                        loader.classList.add('hidden');
                        document.body.style.opacity = '1';
                        document.body.style.visibility = 'visible';
                        
                        const appContainer = document.querySelector('.app-container');
                        if (appContainer) {
                            appContainer.style.opacity = '1';
                            appContainer.style.visibility = 'visible';
                        }
                    }
                }, 5000);
            });
            
            // פונקציה להצגת הודעות flash
            function showFlashMessage(message, type = 'info') {
                if (window.WorkSafety) {
                    window.WorkSafety.showNotification(message, type);
                }
            }
            
            // פונקציה לשליחת AJAX עם CSRF
            function ajaxRequest(url, data = {}, method = 'POST') {
                const csrfToken = window.WorkSafetyConfig.csrfToken;
                
                if (method === 'POST' && csrfToken) {
                    data.csrf_token = csrfToken;
                }
                
                return fetch(url, {
                    method: method,
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: method === 'POST' ? JSON.stringify(data) : null
                });
            }
            
            console.log('✅ WorkSafety.io Header loaded successfully');
        </script>
