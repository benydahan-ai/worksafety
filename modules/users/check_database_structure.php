<?php
/**
 * WorkSafety.io - בדיקת מבנה טבלאות מפורטת
 * קובץ זה יראה לנו בדיוק מה המבנה של הטבלאות במסד הנתונים
 */

// הגדרת סגנון
echo "<!DOCTYPE html>";
echo "<html lang='he' dir='rtl'>";
echo "<head>";
echo "<meta charset='UTF-8'>";
echo "<title>בדיקת מבנה טבלאות - WorkSafety.io</title>";
echo "<style>";
echo "body { font-family: Arial, sans-serif; margin: 2rem; line-height: 1.6; }";
echo "table { width: 100%; border-collapse: collapse; margin: 1rem 0; }";
echo "th, td { border: 1px solid #ddd; padding: 0.75rem; text-align: right; }";
echo "th { background: #f8f9fa; font-weight: bold; }";
echo ".success { color: #28a745; background: #d4edda; padding: 1rem; border-radius: 4px; margin: 0.5rem 0; }";
echo ".error { color: #dc3545; background: #f8d7da; padding: 1rem; border-radius: 4px; margin: 0.5rem 0; }";
echo ".info { color: #0c5460; background: #d1ecf1; padding: 1rem; border-radius: 4px; margin: 0.5rem 0; }";
echo ".code { background: #f8f9fa; padding: 0.5rem; border-radius: 4px; font-family: monospace; }";
echo "</style>";
echo "</head>";
echo "<body>";

echo "<h1>🔍 בדיקת מבנה טבלאות - WorkSafety.io</h1>";
echo "<p>תאריך הבדיקה: " . date('d/m/Y H:i:s') . "</p>";

// חיבור למסד הנתונים
try {
    require_once '../../config/database.php';
    $db = getDB();
    $pdo = $db->getConnection();
    
    echo "<div class='success'>✅ חיבור למסד הנתונים הצליח</div>";
    
    // קבלת רשימת כל הטבלאות
    $tablesResult = $pdo->query("SHOW TABLES");
    $tables = [];
    while ($table = $tablesResult->fetch(PDO::FETCH_NUM)) {
        $tables[] = $table[0];
    }
    
    echo "<h2>📋 טבלאות במסד הנתונים</h2>";
    echo "<div class='info'>נמצאו " . count($tables) . " טבלאות: " . implode(', ', $tables) . "</div>";
    
    // בדיקה מפורטת של טבלת משתמשים
    if (in_array('users', $tables)) {
        echo "<h2>👥 מבנה טבלת משתמשים (users)</h2>";
        
        try {
            $usersStructure = $pdo->query("DESCRIBE users");
            echo "<table>";
            echo "<tr><th>שם שדה</th><th>סוג</th><th>NULL</th><th>מפתח</th><th>ברירת מחדל</th><th>נוסף</th></tr>";
            
            $userFields = [];
            while ($field = $usersStructure->fetch(PDO::FETCH_ASSOC)) {
                $userFields[] = $field['Field'];
                echo "<tr>";
                echo "<td><strong>" . $field['Field'] . "</strong></td>";
                echo "<td>" . $field['Type'] . "</td>";
                echo "<td>" . $field['Null'] . "</td>";
                echo "<td>" . $field['Key'] . "</td>";
                echo "<td>" . ($field['Default'] ?? 'NULL') . "</td>";
                echo "<td>" . $field['Extra'] . "</td>";
                echo "</tr>";
            }
            echo "</table>";
            
            // בדיקת שדות חשובים
            $importantUserFields = ['id', 'first_name', 'last_name', 'email', 'password', 'role', 'status', 'company_id', 'created_at', 'updated_at', 'created_by', 'updated_by', 'last_login'];
            
            echo "<h3>🔍 בדיקת שדות חשובים בטבלת משתמשים:</h3>";
            foreach ($importantUserFields as $field) {
                if (in_array($field, $userFields)) {
                    echo "<div class='success'>✅ שדה $field - קיים</div>";
                } else {
                    echo "<div class='error'>❌ שדה $field - חסר</div>";
                }
            }
            
        } catch (Exception $e) {
            echo "<div class='error'>❌ שגיאה בבדיקת מבנה טבלת משתמשים: " . $e->getMessage() . "</div>";
        }
    } else {
        echo "<div class='error'>❌ טבלת משתמשים לא קיימת</div>";
    }
    
    // בדיקה מפורטת של טבלת חברות
    if (in_array('companies', $tables)) {
        echo "<h2>🏢 מבנה טבלת חברות (companies)</h2>";
        
        try {
            $companiesStructure = $pdo->query("DESCRIBE companies");
            echo "<table>";
            echo "<tr><th>שם שדה</th><th>סוג</th><th>NULL</th><th>מפתח</th><th>ברירת מחדל</th><th>נוסף</th></tr>";
            
            $companyFields = [];
            while ($field = $companiesStructure->fetch(PDO::FETCH_ASSOC)) {
                $companyFields[] = $field['Field'];
                echo "<tr>";
                echo "<td><strong>" . $field['Field'] . "</strong></td>";
                echo "<td>" . $field['Type'] . "</td>";
                echo "<td>" . $field['Null'] . "</td>";
                echo "<td>" . $field['Key'] . "</td>";
                echo "<td>" . ($field['Default'] ?? 'NULL') . "</td>";
                echo "<td>" . $field['Extra'] . "</td>";
                echo "</tr>";
            }
            echo "</table>";
            
            // בדיקת שדות חשובים
            $importantCompanyFields = ['id', 'name', 'company_type', 'type', 'address', 'phone', 'status', 'created_at'];
            
            echo "<h3>🔍 בדיקת שדות חשובים בטבלת חברות:</h3>";
            foreach ($importantCompanyFields as $field) {
                if (in_array($field, $companyFields)) {
                    echo "<div class='success'>✅ שדה $field - קיים</div>";
                } else {
                    echo "<div class='error'>❌ שדה $field - חסר</div>";
                }
            }
            
        } catch (Exception $e) {
            echo "<div class='error'>❌ שגיאה בבדיקת מבנה טבלת חברות: " . $e->getMessage() . "</div>";
        }
    } else {
        echo "<div class='error'>❌ טבלת חברות לא קיימת</div>";
    }
    
    // בדיקת טבלאות נוספות
    $additionalTables = ['permissions', 'user_permissions', 'user_login_history'];
    echo "<h2>📊 טבלאות נוספות</h2>";
    
    foreach ($additionalTables as $tableName) {
        if (in_array($tableName, $tables)) {
            echo "<div class='success'>✅ טבלת $tableName - קיימת</div>";
            
            try {
                $structure = $pdo->query("DESCRIBE $tableName");
                $fields = [];
                while ($field = $structure->fetch(PDO::FETCH_ASSOC)) {
                    $fields[] = $field['Field'];
                }
                echo "<div class='info'>שדות בטבלה: " . implode(', ', $fields) . "</div>";
            } catch (Exception $e) {
                echo "<div class='error'>לא ניתן לקרוא מבנה של $tableName</div>";
            }
        } else {
            echo "<div class='error'>❌ טבלת $tableName - לא קיימת</div>";
        }
    }
    
    // דוגמת נתונים מטבלת משתמשים
    if (in_array('users', $tables)) {
        echo "<h2>👤 דוגמת נתונים מטבלת משתמשים</h2>";
        
        try {
            $sampleUsers = $pdo->query("SELECT * FROM users LIMIT 3");
            if ($sampleUsers->rowCount() > 0) {
                echo "<table>";
                $headers = array_keys($sampleUsers->fetch(PDO::FETCH_ASSOC));
                $sampleUsers = $pdo->query("SELECT * FROM users LIMIT 3"); // חזרה לתחילה
                
                echo "<tr>";
                foreach ($headers as $header) {
                    echo "<th>$header</th>";
                }
                echo "</tr>";
                
                while ($user = $sampleUsers->fetch(PDO::FETCH_ASSOC)) {
                    echo "<tr>";
                    foreach ($user as $value) {
                        $displayValue = $value;
                        if (strlen($displayValue) > 50) {
                            $displayValue = substr($displayValue, 0, 50) . '...';
                        }
                        echo "<td>" . htmlspecialchars($displayValue) . "</td>";
                    }
                    echo "</tr>";
                }
                echo "</table>";
            } else {
                echo "<div class='info'>אין נתונים בטבלת משתמשים</div>";
            }
        } catch (Exception $e) {
            echo "<div class='error'>❌ שגיאה בקריאת נתונים מטבלת משתמשים: " . $e->getMessage() . "</div>";
        }
    }
    
    // יצירת שאילתה מותאמת
    echo "<h2>🔧 הצעת שאילתה מותאמת לטבלאות</h2>";
    
    if (in_array('users', $tables) && in_array('companies', $tables)) {
        // בניית שאילתה בהתבסס על השדות הקיימים
        $userFieldsExists = [];
        $companyFieldsExists = [];
        
        try {
            $usersStructure = $pdo->query("DESCRIBE users");
            while ($field = $usersStructure->fetch(PDO::FETCH_ASSOC)) {
                $userFieldsExists[] = $field['Field'];
            }
            
            $companiesStructure = $pdo->query("DESCRIBE companies");
            while ($field = $companiesStructure->fetch(PDO::FETCH_ASSOC)) {
                $companyFieldsExists[] = $field['Field'];
            }
            
            // בניית שאילתה
            $selectParts = ['u.*'];
            
            // שדות חברה
            if (in_array('name', $companyFieldsExists)) {
                $selectParts[] = 'c.name as company_name';
            }
            
            if (in_array('company_type', $companyFieldsExists)) {
                $selectParts[] = 'c.company_type';
            } elseif (in_array('type', $companyFieldsExists)) {
                $selectParts[] = 'c.type as company_type';
            }
            
            if (in_array('address', $companyFieldsExists)) {
                $selectParts[] = 'c.address as company_address';
            }
            
            if (in_array('phone', $companyFieldsExists)) {
                $selectParts[] = 'c.phone as company_phone';
            }
            
            // שדות יוצר ומעדכן
            if (in_array('created_by', $userFieldsExists)) {
                $selectParts[] = 'creator.first_name as creator_first_name';
                $selectParts[] = 'creator.last_name as creator_last_name';
            }
            
            if (in_array('updated_by', $userFieldsExists)) {
                $selectParts[] = 'updater.first_name as updater_first_name';
                $selectParts[] = 'updater.last_name as updater_last_name';
            }
            
            $selectClause = implode(",\n            ", $selectParts);
            
            $query = "SELECT \n            {$selectClause}\n        FROM users u \n        LEFT JOIN companies c ON u.company_id = c.id";
            
            if (in_array('created_by', $userFieldsExists)) {
                $query .= " \n        LEFT JOIN users creator ON u.created_by = creator.id";
            }
            
            if (in_array('updated_by', $userFieldsExists)) {
                $query .= " \n        LEFT JOIN users updater ON u.updated_by = updater.id";
            }
            
            $query .= " \n        WHERE u.id = ?";
            
            echo "<div class='code'>";
            echo "<h3>שאילתה מותאמת לטבלאות שלך:</h3>";
            echo "<pre>" . htmlspecialchars($query) . "</pre>";
            echo "</div>";
            
        } catch (Exception $e) {
            echo "<div class='error'>❌ שגיאה ביצירת שאילתה מותאמת: " . $e->getMessage() . "</div>";
        }
    }
    
} catch (Exception $e) {
    echo "<div class='error'>❌ שגיאה בחיבור למסד הנתונים: " . htmlspecialchars($e->getMessage()) . "</div>";
}

echo "<hr>";
echo "<p style='text-align: center; color: #6c757d;'>";
echo "בדיקה זו בוצעה ב-" . date('d/m/Y H:i:s') . "<br>";
echo "שמור את התוצאות ושלח אותן למפתח לקבלת תיקון מותאם";
echo "</p>";

echo "</body></html>";
?>
