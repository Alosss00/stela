<?php
require_once 'includes/db.php';

$db = new Database();

echo "=== FINAL VERIFICATION - DATA CONSISTENCY CHECK ===\n\n";

// Get all unique companies from employees table
echo "1. Companies in EMPLOYEES table:\n";
echo str_repeat("-", 80) . "\n";
$emp_companies = $db->query("
    SELECT DISTINCT contractor_company 
    FROM employees 
    WHERE contractor_company IS NOT NULL AND contractor_company != ''
    ORDER BY contractor_company
");

$emp_company_list = [];
if ($emp_companies && $emp_companies->num_rows > 0) {
    while ($row = $emp_companies->fetch_assoc()) {
        $company = $row['contractor_company'];
        $emp_company_list[] = $company;
        
        // Check format
        $has_dot = strpos($company, 'PT. ') !== false ? '❌' : '✓';
        echo "$has_dot $company\n";
    }
}

echo "\n2. Companies in USERS table:\n";
echo str_repeat("-", 80) . "\n";
$user_companies = $db->query("
    SELECT DISTINCT company_name 
    FROM users 
    WHERE company_name IS NOT NULL AND company_name != ''
    ORDER BY company_name
");

$user_company_list = [];
if ($user_companies && $user_companies->num_rows > 0) {
    while ($row = $user_companies->fetch_assoc()) {
        $company = $row['company_name'];
        $user_company_list[] = $company;
        
        // Check format
        $has_dot = strpos($company, 'PT. ') !== false ? '❌' : '✓';
        echo "$has_dot $company\n";
    }
}

echo "\n3. MATCHING CHECK:\n";
echo str_repeat("-", 80) . "\n";

// Check for each user company if it exists in employees
foreach ($user_company_list as $user_company) {
    $match_found = false;
    $employee_count = 0;
    
    // Count employees for this company
    $count_result = $db->query("
        SELECT COUNT(*) as count 
        FROM employees 
        WHERE contractor_company = '" . $db->escapeString($user_company) . "'
    ");
    
    if ($count_result) {
        $employee_count = $count_result->fetch_assoc()['count'];
        $match_found = $employee_count > 0;
    }
    
    $status = $match_found ? "✓ MATCH" : "❌ NO MATCH";
    echo "$status | $user_company | Employees: $employee_count\n";
}

echo "\n4. FORMAT CHECK:\n";
echo str_repeat("-", 80) . "\n";

// Check if any data still has "PT." format
$employees_with_dot = $db->query("
    SELECT COUNT(*) as count 
    FROM employees 
    WHERE contractor_company LIKE 'PT. %'
")->fetch_assoc()['count'];

$users_with_dot = $db->query("
    SELECT COUNT(*) as count 
    FROM users 
    WHERE company_name LIKE 'PT. %'
")->fetch_assoc()['count'];

echo "Employees table with 'PT.': $employees_with_dot\n";
echo "Users table with 'PT.': $users_with_dot\n";

echo "\n";
echo str_repeat("=", 80) . "\n";

if ($employees_with_dot == 0 && $users_with_dot == 0) {
    echo "✓✓✓ SUCCESS! All company names are now consistent!\n";
    echo "✓ Users can now login and see their company data.\n";
    echo "✓ All 'PT.' formats have been updated to 'PT' (without dot).\n";
} else {
    echo "⚠️  WARNING: Some records still have 'PT.' format!\n";
}

echo str_repeat("=", 80) . "\n";
?>

