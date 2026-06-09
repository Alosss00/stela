<?php
require_once 'includes/db.php';

$db = new Database();

echo "=== TESTING USER COMPANY DATA ACCESS ===\n\n";

// Get a sample company user
$sample_user = $db->query("
    SELECT id, username, full_name, company_name, role 
    FROM users 
    WHERE role = 'user' AND company_name IS NOT NULL AND company_name != ''
    LIMIT 1
")->fetch_assoc();

if (!$sample_user) {
    echo "❌ No company users found in database\n";
    exit;
}

echo "Testing with sample user:\n";
echo "Username: " . $sample_user['username'] . "\n";
echo "Company: " . $sample_user['company_name'] . "\n";
echo str_repeat("-", 80) . "\n\n";

$company_name = $sample_user['company_name'];

// Test 1: Count employees
echo "TEST 1: Employee Count Query\n";
$query1 = "SELECT COUNT(*) as count FROM employees WHERE contractor_company = '" . $db->escapeString($company_name) . "'";
echo "Query: $query1\n";
$result1 = $db->query($query1);
$count1 = $result1 ? $result1->fetch_assoc()['count'] : 0;
echo "Result: $count1 employees\n";
echo $count1 > 0 ? "✓ PASS\n" : "❌ FAIL - No employees found\n";
echo "\n";

// Test 2: Get employee details
if ($count1 > 0) {
    echo "TEST 2: Employee Details Query\n";
    $query2 = "SELECT id, employee_code, full_name, position, verification_status, contractor_company 
               FROM employees 
               WHERE contractor_company = '" . $db->escapeString($company_name) . "' 
               LIMIT 3";
    echo "Query: $query2\n";
    $result2 = $db->query($query2);
    echo "Results:\n";
    while ($row = $result2->fetch_assoc()) {
        echo "  - ID: " . $row['id'] . " | Code: " . $row['employee_code'] . " | Name: " . $row['full_name'] . "\n";
        echo "    Company: '" . $row['contractor_company'] . "' (length: " . strlen($row['contractor_company']) . ")\n";
        echo "    Session Company: '" . $company_name . "' (length: " . strlen($company_name) . ")\n";
        echo "    Match: " . ($row['contractor_company'] === $company_name ? "✓ YES" : "❌ NO") . "\n";
    }
    echo "✓ PASS\n\n";
}

// Test 3: Count appointments
echo "TEST 3: Appointments Count Query\n";
$query3 = "SELECT COUNT(*) as count 
           FROM appointments a
           JOIN employees e ON a.employee_id = e.id
           WHERE e.contractor_company = '" . $db->escapeString($company_name) . "'";
echo "Query: $query3\n";
$result3 = $db->query($query3);
$count3 = $result3 ? $result3->fetch_assoc()['count'] : 0;
echo "Result: $count3 appointments\n";
echo "✓ PASS\n\n";

// Test 4: Check all companies
echo "TEST 4: All Companies in Database\n";
echo "Companies in USERS table:\n";
$users_companies = $db->query("SELECT DISTINCT company_name FROM users WHERE company_name IS NOT NULL AND company_name != '' ORDER BY company_name");
while ($row = $users_companies->fetch_assoc()) {
    echo "  - '" . $row['company_name'] . "'\n";
}

echo "\nCompanies in EMPLOYEES table:\n";
$emp_companies = $db->query("SELECT DISTINCT contractor_company FROM employees ORDER BY contractor_company");
while ($row = $emp_companies->fetch_assoc()) {
    echo "  - '" . $row['contractor_company'] . "'\n";
}

echo "\n";
echo str_repeat("=", 80) . "\n";
echo "Testing completed!\n";
echo "\nRECOMMENDATION:\n";
if ($count1 == 0) {
    echo "❌ The test user has no employees in the database.\n";
    echo "This is expected if the company hasn't added any employees yet.\n";
} else {
    echo "✓ Everything looks correct! If users still can't see data:\n";
    echo "  1. Make sure they LOGOUT and LOGIN again (session needs refresh)\n";
    echo "  2. Clear browser cache/cookies\n";
    echo "  3. Check browser console for JavaScript errors\n";
}
?>

