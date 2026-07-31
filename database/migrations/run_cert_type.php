<?php
/**
 * Migration Runner: Add cert_type column to employee_certifications table
 * 
 * This script will:
 * 1. Check if cert_type column already exists
 * 2. If not exists, add the column
 * 3. Update existing records with data from certifications master table
 */

require_once 'includes/db.php';

$db = new Database();

echo "<!DOCTYPE html>";
echo "<html lang='en'>";
echo "<head>";
echo "<meta charset='UTF-8'>";
echo "<meta name='viewport' content='width=device-width, initial-scale=1.0'>";
echo "<title>Migration: Add cert_type Column</title>";
echo "<style>";
echo "body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; padding: 40px; background: #F9FAFB; }";
echo ".container { max-width: 800px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }";
echo "h2 { color: #37474F; margin-top: 0; }";
echo ".success { color: #2E7D32; background: #E8F5E9; padding: 12px 20px; border-radius: 6px; margin: 15px 0; border-left: 4px solid #2E7D32; }";
echo ".warning { color: #f59e0b; background: #fef3c7; padding: 12px 20px; border-radius: 6px; margin: 15px 0; border-left: 4px solid #f59e0b; }";
echo ".error { color: #ef4444; background: #fee2e2; padding: 12px 20px; border-radius: 6px; margin: 15px 0; border-left: 4px solid #ef4444; }";
echo ".info { color: #37474F; background: #ECEFF1; padding: 12px 20px; border-radius: 6px; margin: 15px 0; border-left: 4px solid #37474F; }";
echo ".btn { display: inline-block; padding: 12px 24px; background: #37474F; color: white; text-decoration: none; border-radius: 6px; margin-top: 20px; transition: all 0.3s; }";
echo ".btn:hover { background: #37474F; transform: translateY(-2px); }";
echo "pre { background: #f8f9fa; padding: 15px; border-radius: 6px; overflow-x: auto; }";
echo "</style>";
echo "</head>";
echo "<body>";
echo "<div class='container'>";

echo "<h2>?? Migration: Add cert_type Column</h2>";

// Check if column already exists
$check = $db->query("SHOW COLUMNS FROM employee_certifications LIKE 'cert_type'");

if ($check && $check->num_rows > 0) {
    echo "<div class='warning'>";
    echo "<strong>?? Column Already Exists</strong><br>";
    echo "The 'cert_type' column already exists in employee_certifications table. No action needed.";
    echo "</div>";
} else {
    echo "<div class='info'>";
    echo "<strong>?? Starting Migration...</strong><br>";
    echo "Adding 'cert_type' column to employee_certifications table...";
    echo "</div>";
    
    // Add column
    $sql_add_column = "ALTER TABLE employee_certifications 
                       ADD COLUMN cert_type VARCHAR(100) DEFAULT NULL 
                       AFTER certification_id";
    
    if ($db->query($sql_add_column)) {
        echo "<div class='success'>";
        echo "<strong>? Column Added Successfully!</strong><br>";
        echo "Column 'cert_type' has been added to employee_certifications table.";
        echo "</div>";
        
        // Update existing data from master certifications table
        echo "<div class='info'>";
        echo "<strong>?? Updating Existing Records...</strong><br>";
        echo "Copying cert_type from certifications master table to existing employee certifications...";
        echo "</div>";
        
        $update_sql = "UPDATE employee_certifications ec
                      INNER JOIN certifications c ON ec.certification_id = c.id
                      SET ec.cert_type = c.cert_type
                      WHERE ec.cert_type IS NULL AND c.cert_type IS NOT NULL";
        
        if ($db->query($update_sql)) {
            $affected_rows = $db->getConnection()->affected_rows;
            echo "<div class='success'>";
            echo "<strong>? Update Complete!</strong><br>";
            echo "Updated {$affected_rows} existing certification records with cert_type from master data.";
            echo "</div>";
        } else {
            echo "<div class='warning'>";
            echo "<strong>?? Update Warning</strong><br>";
            echo "Could not update existing records. This is not critical. Error: " . $db->getConnection()->error;
            echo "</div>";
        }
        
        echo "<div class='success'>";
        echo "<strong>?? Migration Completed Successfully!</strong><br>";
        echo "<ul>";
        echo "<li>Column 'cert_type' has been added to employee_certifications table</li>";
        echo "<li>Existing records have been updated (if applicable)</li>";
        echo "<li>New employee data will now save Certificate Type from user input</li>";
        echo "<li>Certificate Type will be displayed in verify_employee.php</li>";
        echo "</ul>";
        echo "</div>";
        
    } else {
        echo "<div class='error'>";
        echo "<strong>? Migration Failed!</strong><br>";
        echo "Error adding column: " . $db->getConnection()->error;
        echo "<br><br><strong>Possible Solutions:</strong>";
        echo "<ul>";
        echo "<li>Check if you have ALTER permission on the database</li>";
        echo "<li>Verify database connection is working</li>";
        echo "<li>Try running the SQL manually in phpMyAdmin</li>";
        echo "</ul>";
        echo "</div>";
        
        echo "<div class='info'>";
        echo "<strong>Manual SQL Query:</strong>";
        echo "<pre>ALTER TABLE employee_certifications \nADD COLUMN cert_type VARCHAR(100) DEFAULT NULL \nAFTER certification_id;</pre>";
        echo "</div>";
    }
}

echo "<div class='info'>";
echo "<strong>?? What's Next?</strong><br>";
echo "<ul>";
echo "<li>Go to <strong>user_add_employee.php</strong> and add a new employee</li>";
echo "<li>Fill in the Certificate Type field in the certification form</li>";
echo "<li>Submit the form and verify in <strong>verify_employee.php</strong></li>";
echo "<li>Certificate Type should now be displayed in the verification page</li>";
echo "</ul>";
echo "</div>";

echo "<a href='user_add_employee.php' class='btn'>? Go to Add Employee</a> ";
echo "<a href='employees.php' class='btn'>? Go to Employees List</a>";

echo "</div>";
echo "</body>";
echo "</html>";
?>

