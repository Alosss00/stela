<?php
// Fix SQL injection in superadmin/employees.php
$file = 'resources/superadmin/employees.php';
$content = file_get_contents($file);

// Fix 1: employee_code duplicate check
$old1 = "elseif (\$db->query(\"SELECT employee_code FROM employees WHERE deleted_at IS NULL AND employee_code = '\$employee_code' AND is_active = 1\")->num_rows > 0) {";
$new1 = "elseif (\$db->query(\"SELECT employee_code FROM employees WHERE deleted_at IS NULL AND employee_code = ? AND is_active = 1\", [\$employee_code])->num_rows > 0) {";
$content = str_replace($old1, $new1, $content);

// Fix 2: verified_by stats with current_user_id
$old2 = "\$verified_count = \$db->query(\"SELECT COUNT(*) as count FROM employees WHERE deleted_at IS NULL AND verification_status = 'verified' AND is_active = 1 AND verified_by = '\$current_user_id'\")->fetch_assoc()['count'];";
$new2 = "\$verified_count = \$db->query(\"SELECT COUNT(*) as count FROM employees WHERE deleted_at IS NULL AND verification_status = 'verified' AND is_active = 1 AND verified_by = ?\", [(int)\$current_user_id])->fetch_assoc()['count'];";
$content = str_replace($old2, $new2, $content);

// Fix 3: rejected_by stats with current_user_id
$old3 = "\$rejected_count = \$db->query(\"SELECT COUNT(*) as count FROM employees WHERE deleted_at IS NULL AND verification_status = 'rejected' AND is_active = 1 AND verified_by = '\$current_user_id'\")->fetch_assoc()['count'];";
$new3 = "\$rejected_count = \$db->query(\"SELECT COUNT(*) as count FROM employees WHERE deleted_at IS NULL AND verification_status = 'rejected' AND is_active = 1 AND verified_by = ?\", [(int)\$current_user_id])->fetch_assoc()['count'];";
$content = str_replace($old3, $new3, $content);

file_put_contents($file, $content);
echo "Done\n";

// Verify replacements
if (strpos($content, "'$employee_code'") !== false) { echo "WARNING: Fix 1 not applied\n"; } else { echo "Fix 1 OK\n"; }
if (strpos($content, "verified_by = '\$current_user_id'") !== false) { echo "WARNING: Fix 2/3 not applied\n"; } else { echo "Fix 2/3 OK\n"; }
?>
