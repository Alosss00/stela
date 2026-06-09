<?php
session_start();

echo "=== DEBUG USER SESSION ===\n\n";

if (!isset($_SESSION['user_id'])) {
    echo "❌ No active session - user is not logged in\n";
    echo "Please login first to check session data\n";
    exit;
}

echo "Session Data:\n";
echo str_repeat("-", 80) . "\n";
echo "User ID: " . ($_SESSION['user_id'] ?? 'NOT SET') . "\n";
echo "Username: " . ($_SESSION['username'] ?? 'NOT SET') . "\n";
echo "Full Name: " . ($_SESSION['full_name'] ?? 'NOT SET') . "\n";
echo "Role: " . ($_SESSION['role'] ?? 'NOT SET') . "\n";
echo "Company Name: " . ($_SESSION['company_name'] ?? 'NOT SET') . "\n";
echo "Department: " . ($_SESSION['department'] ?? 'NOT SET') . "\n";

if (isset($_SESSION['company_name']) && !empty($_SESSION['company_name'])) {
    echo "\n";
    echo str_repeat("-", 80) . "\n";
    echo "Checking data for company: " . $_SESSION['company_name'] . "\n";
    echo str_repeat("-", 80) . "\n";
    
    require_once 'includes/db.php';
    $db = new Database();
    $company_name = $_SESSION['company_name'];
    
    // Check employees
    $emp_result = $db->query("
        SELECT COUNT(*) as count 
        FROM employees 
        WHERE contractor_company = '" . $db->escapeString($company_name) . "'
    ");
    $emp_count = $emp_result ? $emp_result->fetch_assoc()['count'] : 0;
    
    echo "\nEmployees found: $emp_count\n";
    
    if ($emp_count > 0) {
        echo "✓ Data exists for this company\n";
        
        // Show sample employees
        $sample = $db->query("
            SELECT id, employee_code, full_name, position, verification_status
            FROM employees 
            WHERE contractor_company = '" . $db->escapeString($company_name) . "'
            LIMIT 5
        ");
        
        echo "\nSample employees:\n";
        while ($row = $sample->fetch_assoc()) {
            echo "  - " . $row['employee_code'] . " | " . $row['full_name'] . " | " . $row['position'] . " | Status: " . $row['verification_status'] . "\n";
        }
    } else {
        echo "❌ No employees found for this company\n";
        
        // Check if similar company names exist
        echo "\nChecking for similar company names in database...\n";
        $similar = $db->query("
            SELECT DISTINCT contractor_company 
            FROM employees 
            WHERE contractor_company LIKE '%" . $db->escapeString(str_replace('PT ', '', $company_name)) . "%'
            LIMIT 5
        ");
        
        if ($similar && $similar->num_rows > 0) {
            echo "Similar companies found:\n";
            while ($row = $similar->fetch_assoc()) {
                echo "  - " . $row['contractor_company'] . "\n";
            }
        }
    }
    
    // Check appointments
    $app_result = $db->query("
        SELECT COUNT(*) as count 
        FROM appointments a
        JOIN employees e ON a.employee_id = e.id
        WHERE e.contractor_company = '" . $db->escapeString($company_name) . "'
    ");
    $app_count = $app_result ? $app_result->fetch_assoc()['count'] : 0;
    
    echo "\nAppointments found: $app_count\n";
    
} else {
    echo "\n❌ No company name in session\n";
    echo "This user account may not be associated with a company\n";
}

echo "\n";
echo str_repeat("=", 80) . "\n";
echo "Debug completed\n";
?>

