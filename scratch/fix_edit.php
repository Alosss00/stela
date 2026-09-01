<?php
$file = 'resources/superadmin/edit_employee.php';
$content = file_get_contents($file);
$content = str_replace(
    '$check = $db->query("SELECT id FROM employees WHERE deleted_at IS NULL AND employee_code = ?");', 
    '$check = $db->query("SELECT id FROM employees WHERE deleted_at IS NULL AND employee_code = ?", [$employee_code]);', 
    $content
);
file_put_contents($file, $content);
echo "Fixed edit_employee.php\n";
?>
