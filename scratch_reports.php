<?php
$files = [
    'C:\Users\USER\OneDrive\Documents\stela-2\resources\views\dept\reports.php',
    'C:\Users\USER\OneDrive\Documents\stela-2\resources\views\admin\reports.php'
];

foreach ($files as $file) {
    if (!file_exists($file)) continue;
    $content = file_get_contents($file);
    
    // Replace employee_name query selection
    $content = preg_replace(
        '/SELECT a.\*, e.full_name as employee_name, e.employee_code, e.department, e.ruang_lingkup, e.supervision_area,(\r?\n|\r|\s)*p.position_name, p.position_type,/', 
        "SELECT a.*, e.full_name as employee_name, e.employee_code, e.department, e.ruang_lingkup, e.supervision_area, e.employee_status, e.resign_date,\n           p.position_name, p.position_type,", 
        $content
    );

    $content = preg_replace(
        '/SELECT a.\*, e.full_name as employee_name, e.employee_code, e.contractor_company, e.ruang_lingkup, e.supervision_area,(\r?\n|\r|\s)*p.position_name, p.position_type,/', 
        "SELECT a.*, e.full_name as employee_name, e.employee_code, e.contractor_company, e.ruang_lingkup, e.supervision_area, e.employee_status, e.resign_date,\n           p.position_name, p.position_type,", 
        $content
    );

    // Replace accepted_requests / expiring_certs in dept/reports.php
    $content = str_replace(
        "SELECT ec.*, e.full_name, e.employee_code, cert.cert_name,",
        "SELECT ec.*, e.full_name, e.employee_code, e.employee_status, e.resign_date, cert.cert_name,",
        $content
    );

    // Replace HTML employee-detail blocks for $row (could be $row['employee_name'] or $row['full_name'] or $cert['full_name'])
    
    $badge_html = '<?php if (isset($row[\'employee_status\']) && $row[\'employee_status\'] === \'resign\'): ?> <span class="badge badge-danger" style="font-size: 0.7em; margin-left: 5px;">Resigned (<?php echo !empty($row[\'resign_date\']) ? date(\'d/m/Y\', strtotime($row[\'resign_date\'])) : \'-\'; ?>)</span> <?php endif; ?>';
    
    $cert_badge_html = '<?php if (isset($cert[\'employee_status\']) && $cert[\'employee_status\'] === \'resign\'): ?> <span class="badge badge-danger" style="font-size: 0.7em; margin-left: 5px;">Resigned (<?php echo !empty($cert[\'resign_date\']) ? date(\'d/m/Y\', strtotime($cert[\'resign_date\'])) : \'-\'; ?>)</span> <?php endif; ?>';
    
    // For $row['employee_name']
    $content = preg_replace(
        '/(<strong><\?php echo htmlspecialchars\(\$row\[\'employee_name\'\]\); \?><\/strong>)(?!<\?php if \(isset\(\$row\[\'employee_status\'\]\))/', 
        '$1' . $badge_html, 
        $content
    );
    
    // For $row['full_name']
    $content = preg_replace(
        '/(<strong><\?php echo htmlspecialchars\(\$row\[\'full_name\'\]\); \?><\/strong>)(?!<\?php if \(isset\(\$row\[\'employee_status\'\]\))/', 
        '$1' . $badge_html, 
        $content
    );

    // For $cert['full_name']
    $content = preg_replace(
        '/(<strong><\?php echo htmlspecialchars\(\$cert\[\'full_name\'\]\); \?><\/strong>)(?!<\?php if \(isset\(\$cert\[\'employee_status\'\]\))/', 
        '$1' . $cert_badge_html, 
        $content
    );

    file_put_contents($file, $content);
}
echo "Done";
