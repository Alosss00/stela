<?php
$fixes = [
    // 1. employees.php
    'resources/superadmin/employees.php' => [
        [
            "old" => "elseif (\$db->query(\"SELECT employee_code FROM employees WHERE deleted_at IS NULL AND employee_code = '\$employee_code' AND is_active = 1\")->num_rows > 0) {",
            "new" => "elseif (\$db->query(\"SELECT employee_code FROM employees WHERE deleted_at IS NULL AND employee_code = ? AND is_active = 1\", [\$employee_code])->num_rows > 0) {"
        ]
    ],
    // 2. edit_employee.php
    'resources/superadmin/edit_employee.php' => [
        [
            "old" => "\$check = \$db->query(\"SELECT id FROM employees WHERE deleted_at IS NULL AND employee_code = '\$employee_code'\");",
            "new" => "\$check = \$db->query(\"SELECT id FROM employees WHERE deleted_at IS NULL AND employee_code = ?\", [\$employee_code]);"
        ]
    ],
    // 3. positions.php
    'resources/superadmin/positions.php' => [
        [
            "old" => "\$check_comp = \$db->query(\"SELECT id FROM competencies WHERE deleted_at IS NULL AND competency_name = '\$competency_name' AND position_type = '\$position_type'\");",
            "new" => "\$check_comp = \$db->query(\"SELECT id FROM competencies WHERE deleted_at IS NULL AND competency_name = ? AND position_type = ?\", [\$competency_name, \$position_type]);"
        ],
        [
            "old" => "\$check_comp = \$db->query(\"SELECT id FROM competencies WHERE deleted_at IS NULL AND competency_name = '\$competency_name' AND position_type = '\$position_type' AND id != \$id\");",
            "new" => "\$check_comp = \$db->query(\"SELECT id FROM competencies WHERE deleted_at IS NULL AND competency_name = ? AND position_type = ? AND id != ?\", [\$competency_name, \$position_type, \$id]);"
        ],
        [
            "old" => "\$db->query(\"DELETE FROM competency_sub_competencies WHERE competency_id = \$id\");",
            "new" => "\$db->query(\"DELETE FROM competency_sub_competencies WHERE competency_id = ?\", [\$id]);"
        ],
        [
            "old" => "\$positions_unlinked = \$db->query(\"UPDATE positions SET competency_id = NULL WHERE competency_id = \$id\");",
            "new" => "\$positions_unlinked = \$db->query(\"UPDATE positions SET competency_id = NULL WHERE competency_id = ?\", [\$id]);"
        ],
        [
            "old" => "\$sub_competencies_deleted = \$db->query(\"DELETE FROM competency_sub_competencies WHERE competency_id = \$id\");",
            "new" => "\$sub_competencies_deleted = \$db->query(\"DELETE FROM competency_sub_competencies WHERE competency_id = ?\", [\$id]);"
        ],
        [
            "old" => "\$subs = \$db->query(\"SELECT id, sub_competency_name FROM competency_sub_competencies WHERE competency_id = \$comp_id AND is_active = 1 ORDER BY id\");",
            "new" => "\$subs = \$db->query(\"SELECT id, sub_competency_name FROM competency_sub_competencies WHERE competency_id = ? AND is_active = 1 ORDER BY id\", [\$comp_id]);"
        ]
    ],
    // 4. supervision_areas.php
    'resources/superadmin/supervision_areas.php' => [
        [
            "old" => "\$check = \$db->query(\"SELECT id FROM supervision_areas WHERE deleted_at IS NULL AND area_name = '\$area_name'\");",
            "new" => "\$check = \$db->query(\"SELECT id FROM supervision_areas WHERE deleted_at IS NULL AND area_name = ?\", [\$area_name]);"
        ],
        [
            "old" => "\$check = \$db->query(\"SELECT id FROM supervision_areas WHERE deleted_at IS NULL AND area_name = '\$area_name' AND id != \$id\");",
            "new" => "\$check = \$db->query(\"SELECT id FROM supervision_areas WHERE deleted_at IS NULL AND area_name = ? AND id != ?\", [\$area_name, \$id]);"
        ]
    ],
    // 5. verify_employee.php
    'resources/superadmin/verify_employee.php' => [
        [
            "old" => "\$db->query(\"UPDATE appointments SET \" . implode(', ', \$update_parts) . \" WHERE id = \$appointment_id\");",
            "new" => "\$db->query(\"UPDATE appointments SET \" . implode(', ', \$update_parts) . \" WHERE id = ?\", [\$appointment_id]);"
        ]
    ],
    // 6. dept/reports.php
    'resources/dept/reports.php' => [
        [
            "old" => "AND e.department = '\" . \$db->escapeString(\$department) . \"'",
            "new" => "AND e.department = ?\""
        ]
    ],
    // 7. user/employee_detail.php
    'resources/user/employee_detail.php' => [
        [
            "old" => "\$emp_check_result = \$db->query(\"SELECT id FROM employees WHERE deleted_at IS NULL AND id = \$employee_id AND contractor_company = '\" . \$db->escapeString(\$company_name) . \"'\");",
            "new" => "\$emp_check_result = \$db->query(\"SELECT id FROM employees WHERE deleted_at IS NULL AND id = ? AND contractor_company = ?\", [\$employee_id, \$company_name]);"
        ]
    ]
];

foreach ($fixes as $file => $replacements) {
    if (!file_exists($file)) continue;
    $content = file_get_contents($file);
    $changed = false;
    foreach ($replacements as $fix) {
        if (strpos($content, $fix['old']) !== false) {
            $content = str_replace($fix['old'], $fix['new'], $content);
            echo "Fixed in $file: " . substr(trim($fix['old']), 0, 50) . "...\n";
            $changed = true;
        }
    }
    
    // special handling for dept/reports.php (the multiline query is tricky with str_replace exact match)
    if ($file === 'resources/dept/reports.php' && $changed === false) {
        // use regex to fix all occurrences of: AND e.department = '" . $db->escapeString($department) . "'
        $pattern = "/AND e\.department = '\" \. \\\$db->escapeString\(\\\$department\) \. \"'/";
        $new_content = preg_replace($pattern, "AND e.department = ?\"", $content);
        if ($new_content !== $content) {
            
            // Now we need to pass [$department] to all $db->query() in that file
            $new_content = preg_replace('/(\$db->query\(\s*".*?"\s*)\);/s', "$1, [\$department]);", $new_content);
            
            $content = $new_content;
            $changed = true;
            echo "Fixed reports.php with regex.\n";
        }
    }
    
    // special handling for dashboard.php (missed in $fixes array)
    if ($file === 'resources/superadmin/dashboard.php' || $file === 'resources/admin/dashboard.php') {
        // the query was: $admin_result = $db->query("SELECT id, full_name FROM users WHERE id IN ($a_ids_str)");
        // this is actually safe if $a_ids_str is built from integer values, but let's check
    }

    if ($changed) {
        file_put_contents($file, $content);
    }
}
echo "Done SQL fixes\n";
?>
