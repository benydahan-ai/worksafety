<?php
/**
 * WorkSafety.io - סקריפט בדיקה לתיקון דפי משתמשים
 * בודק שהקבצים המתוקנים עובדים כראוי
 */

echo "<!DOCTYPE html>";
echo "<html lang='he' dir='rtl'>";
echo "<head>";
echo "<meta charset='UTF-8'>";
echo "<meta name='viewport' content='width=device-width, initial-scale=1.0'>";
echo "<title>בדיקת תיקון דפי משתמשים</title>";
echo "<style>";
echo "body { font-family: Arial, sans-serif; margin: 2rem; line-height: 1.6; }";
echo ".success { color: #28a745; background: #d4edda; padding: 1rem; border-radius: 4px; margin: 0.5rem 0; }";
echo ".error { color: #dc3545; background: #f8d7da; padding: 1rem; border-radius: 4px; margin: 0.5rem 0; }";
echo ".warning { color: #856404; background: #fff3cd; padding: 1rem; border-radius: 4px; margin: 0.5rem 0; }";
echo ".info { color: #0c5460; background: #d1ecf1; padding: 1rem; border-radius: 4px; margin: 0.5rem 0; }";
echo "</style>";
echo "</head>";
echo "<body>";

echo "<h1>🔍 בדיקת תיקון דפי משתמשים - WorkSafety.io</h1>";

// בדיקות בסיסיות
$checks = [
    'database_connection' => false,
    'users_table_exists' => false,
    'companies_table_exists' => false,
    'companies_fields' => [],
    'view_file_exists' => false,
    'edit_file_exists' => false,
    'view_file_size' => 0,
    'edit_file_size' => 0
];

echo "<h2>📊 תוצאות הבדיקות</h2>";

// בדיקת חיבור למסד נתונים
try {
    require_once '../../config/database.php';
    $db = getDB();
    $checks['database_connection'] = true;
    echo "<div class='success'>✅ חיבור למסד הנתונים - תקין</div>";
    
    // בדיקת קיום טבלת משתמשים
    try {
        $result = $db->getConnection()->query("SHOW TABLES LIKE 'users'");
        if ($result && $result->fetch()) {
            $checks['users_table_exists'] = true;
            echo "<div class='success'>✅ טבלת משתמשים - קיימת</div>";
        } else {
            echo "<div class='error'>❌ טבלת משתמשים - לא קיימת</div>";
        }
    } catch (Exception $e) {
        echo "<div class='error'>❌ שגיאה בבדיקת טבלת משתמשים: " . htmlspecialchars($e->getMessage()) . "</div>";
    }
    
    // בדיקת קיום טבלת חברות ושדותיה
    try {
        $result = $db->getConnection()->query("SHOW TABLES LIKE 'companies'");
        if ($result && $result->fetch()) {
            $checks['companies_table_exists'] = true;
            echo "<div class='success'>✅ טבלת חברות - קיימת</div>";
            
            // בדיקת שדות בטבלת חברות
            $fieldsResult = $db->getConnection()->query("SHOW COLUMNS FROM companies");
            $fields = [];
            while ($field = $fieldsResult->fetch(PDO::FETCH_ASSOC)) {
                $fields[] = $field['Field'];
            }
            $checks['companies_fields'] = $fields;
            
            echo "<div class='info'>📋 שדות בטבלת חברות: " . implode(', ', $fields) . "</div>";
            
            // בדיקה ספציפית לשדות הבעייתיים
            if (in_array('company_type', $fields)) {
                echo "<div class='success'>✅ שדה company_type - קיים</div>";
            } elseif (in_array('type', $fields)) {
                echo "<div class='warning'>⚠️ שדה type קיים (ישן) - הקוד המתוקן יתמודד עם זה</div>";
            } else {
                echo "<div class='warning'>⚠️ אין שדה סוג חברה - הקוד המתוקן יתמודד עם זה</div>";
            }
            
        } else {
            echo "<div class='warning'>⚠️ טבלת חברות - לא קיימת (עדיין לא נוצרה)</div>";
        }
    } catch (Exception $e) {
        echo "<div class='error'>❌ שגיאה בבדיקת טבלת חברות: " . htmlspecialchars($e->getMessage()) . "</div>";
    }
    
} catch (Exception $e) {
    echo "<div class='error'>❌ חיבור למסד הנתונים נכשל: " . htmlspecialchars($e->getMessage()) . "</div>";
}

// בדיקת קיום קבצים
$viewFilePath = 'view.php';
$editFilePath = 'edit.php';

if (file_exists($viewFilePath)) {
    $checks['view_file_exists'] = true;
    $checks['view_file_size'] = filesize($viewFilePath);
    echo "<div class='success'>✅ קובץ view.php - קיים (" . number_format($checks['view_file_size']) . " בתים)</div>";
    
    if ($checks['view_file_size'] < 5000) {
        echo "<div class='warning'>⚠️ קובץ view.php קטן מהצפוי - יכול להיות שלא הותקן נכון</div>";
    }
} else {
    echo "<div class='error'>❌ קובץ view.php - לא קיים</div>";
}

if (file_exists($editFilePath)) {
    $checks['edit_file_exists'] = true;
    $checks['edit_file_size'] = filesize($editFilePath);
    echo "<div class='success'>✅ קובץ edit.php - קיים (" . number_format($checks['edit_file_size']) . " בתים)</div>";
    
    if ($checks['edit_file_size'] < 5000) {
        echo "<div class='warning'>⚠️ קובץ edit.php קטן מהצפוי - יכול להיות שלא הותקן נכון</div>";
    }
} else {
    echo "<div class='error'>❌ קובץ edit.php - לא קיים</div>";
}

// בדיקת תוכן הקבצים (חיפוש אחר הקוד הדינמי)
if ($checks['view_file_exists']) {
    $viewContent = file_get_contents($viewFilePath);
    if (strpos($viewContent, 'SHOW COLUMNS FROM companies') !== false) {
        echo "<div class='success'>✅ קובץ view.php מכיל בדיקות דינמיות מתוקנות</div>";
    } else {
        echo "<div class='warning'>⚠️ קובץ view.php לא מכיל את הקוד המתוקן - יכול להיות גרסה ישנה</div>";
    }
}

if ($checks['edit_file_exists']) {
    $editContent = file_get_contents($editFilePath);
    if (strpos($editContent, 'SHOW COLUMNS FROM companies') !== false) {
        echo "<div class='success'>✅ קובץ edit.php מכיל בדיקות דינמיות מתוקנות</div>";
    } else {
        echo "<div class='warning'>⚠️ קובץ edit.php לא מכיל את הקוד המתוקן - יכול להיות גרסה ישנה</div>";
    }
}

// בדיקת הרשאות קבצים
if ($checks['view_file_exists']) {
    $perms = substr(sprintf('%o', fileperms($viewFilePath)), -4);
    echo "<div class='info'>📁 הרשאות view.php: $perms</div>";
}

if ($checks['edit_file_exists']) {
    $perms = substr(sprintf('%o', fileperms($editFilePath)), -4);
    echo "<div class='info'>📁 הרשאות edit.php: $perms</div>";
}

// סיכום
echo "<h2>📊 סיכום הבדיקה</h2>";

$totalChecks = 0;
$passedChecks = 0;

if ($checks['database_connection']) $passedChecks++;
$totalChecks++;

if ($checks['users_table_exists']) $passedChecks++;
$totalChecks++;

if ($checks['view_file_exists'] && $checks['view_file_size'] > 5000) $passedChecks++;
$totalChecks++;

if ($checks['edit_file_exists'] && $checks['edit_file_size'] > 5000) $passedChecks++;
$totalChecks++;

$percentage = ($passedChecks / $totalChecks) * 100;

if ($percentage >= 90) {
    echo "<div class='success'>";
    echo "<h3>🎉 מצוין! התיקון הושלם בהצלחה</h3>";
    echo "<p>✅ $passedChecks מתוך $totalChecks בדיקות עברו בהצלחה ($percentage%)</p>";
    echo "<p>הדפים אמורים לעבוד כראוי כעת.</p>";
    echo "</div>";
} elseif ($percentage >= 75) {
    echo "<div class='warning'>";
    echo "<h3>⚠️ התיקון הותקן חלקית</h3>";
    echo "<p>⚠️ $passedChecks מתוך $totalChecks בדיקות עברו ($percentage%)</p>";
    echo "<p>יכול להיות שיש בעיות קלות. בדוק את הדפים ידנית.</p>";
    echo "</div>";
} else {
    echo "<div class='error'>";
    echo "<h3>❌ יש בעיות בהתקנת התיקון</h3>";
    echo "<p>❌ רק $passedChecks מתוך $totalChecks בדיקות עברו ($percentage%)</p>";
    echo "<p>אנא בדוק את השלבים שוב או פנה לתמיכה.</p>";
    echo "</div>";
}

echo "<h2>🔗 בדיקה ידנית</h2>";
echo "<p>לבדיקה סופית, נסה לגשת לדפים הבאים:</p>";
echo "<ul>";
echo "<li><a href='view.php?id=1' target='_blank'>view.php?id=1</a> - צפייה במשתמש</li>";
echo "<li><a href='edit.php?id=1' target='_blank'>edit.php?id=1</a> - עריכת משתמש</li>";
echo "</ul>";

echo "<h2>🐛 פתרון בעיות</h2>";
echo "<p>אם עדיין יש בעיות:</p>";
echo "<ul>";
echo "<li>בדוק את error_log של השרת</li>";
echo "<li>וודא שהקבצים המתוקנים הועתקו נכון</li>";
echo "<li>בדוק הרשאות קבצים (צריך להיות 644)</li>";
echo "<li>נקה cache של הדפדפן</li>";
echo "</ul>";

echo "<hr>";
echo "<p style='text-align: center; color: #6c757d; font-size: 0.9rem;'>";
echo "בדיקה זו בוצעה ב-" . date('d/m/Y H:i:s') . " | WorkSafety.io System Check";
echo "</p>";

echo "</body></html>";
?>