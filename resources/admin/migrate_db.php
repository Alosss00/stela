<?php
require_once dirname(__DIR__, 2) . '/bootstrap/app.php';
session_start();
require_once dirname(__DIR__, 2) . '/app/Helpers/auth_helper.php';
require_once dirname(__DIR__, 2) . '/bootstrap/app.php';
$db = new Database();

echo "<h2>Database Migration</h2>";

$sql = "ALTER TABLE employees MODIFY verification_status ENUM('draft','pending','verified','rejected') DEFAULT 'pending'";
if ($db->query($sql)) {
    echo "<p style='color:green;'>SUCCESS: verification_status column modified to include 'draft'.</p>";
} else {
    echo "<p style='color:red;'>FAILED to modify verification_status column: " . $db->getConnection()->error . "</p>";
}

echo "<br><a href='employees.php'>Go back to Employees list</a>";
?>
