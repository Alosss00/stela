<?php
$files = [
    'user' => 'C:\Users\USER\OneDrive\Documents\stela-2\resources\views\user\reports.php',
    'dept' => 'C:\Users\USER\OneDrive\Documents\stela-2\resources\views\dept\reports.php',
    'admin' => 'C:\Users\USER\OneDrive\Documents\stela-2\resources\views\admin\reports.php'
];

foreach ($files as $role => $file) {
    if (!file_exists($file)) continue;
    $content = file_get_contents($file);
    
    // Inject Query
    $query = "";
    if ($role === 'user') {
        $query = "
// Get resigned employees
\$resigned_employees = \$db->query(\"
    SELECT e.*
    FROM employees e
    WHERE e.employee_status = 'resign' AND e.contractor_company = '\" . \$db->escapeString(\$company_name) . \"'
    ORDER BY e.resign_date DESC, e.full_name ASC
\");
";
    } elseif ($role === 'dept') {
        $query = "
// Get resigned employees
\$resigned_employees = \$db->query(\"
    SELECT e.*
    FROM employees e
    WHERE e.employee_status = 'resign' AND e.department = '\" . \$db->escapeString(\$department) . \"'
    ORDER BY e.resign_date DESC, e.full_name ASC
\");
";
    } elseif ($role === 'admin') {
        $query = "
// Get resigned employees
\$resigned_employees = \$db->query(\"
    SELECT e.*
    FROM employees e
    WHERE e.employee_status = 'resign'
    ORDER BY e.resign_date DESC, e.full_name ASC
\");
";
    }

    // Insert query before require_once dirname(__DIR__) . '/layouts/header.php';
    if (strpos($content, '$resigned_employees') === false) {
        $content = str_replace("require_once dirname(__DIR__) . '/layouts/header.php';", $query . "\nrequire_once dirname(__DIR__) . '/layouts/header.php';", $content);
    }

    // Inject HTML Table
    $html = '
    <!-- Resigned Employees -->
    <?php if ($resigned_employees && $resigned_employees->num_rows > 0): ?>
    <div class="card-report" id="resigned-employees">
        <div class="card-header-report">
            <div style="display: flex; align-items: center; gap: 10px; flex: 1;">
                <h3 style="margin: 0;"><i class="fas fa-user-times"></i> <span data-lang="resigned-employees">Resigned Employees</span></h3>
                <span class="badge-header danger"><?php echo $resigned_employees->num_rows; ?></span>
            </div>
        </div>
        
        <div class="card-body-report">
            <div class="table-responsive">
                <table class="table-report" style="width: 100%; min-width: 950px;" id="resignedEmployeesTable">
                    <thead>
                        <tr>
                            <th class="col-employee" data-lang="employee">Employee</th>
                            <th class="col-position" data-lang="position">Position</th>
                            <th class="col-date" data-lang="resign-date">Resign Date</th>
                            <th class="col-notes" data-lang="resign-reason">Resign Reason</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $resigned_employees->data_seek(0);
                        while ($row = $resigned_employees->fetch_assoc()): 
                        ?>
                        <tr>
                            <td class="col-employee">
                                <div class="employee-detail">
                                    <strong><?php echo htmlspecialchars($row[\'full_name\']); ?></strong>
                                    <span class="emp-code-detail"><?php echo htmlspecialchars($row[\'employee_code\']); ?></span>
                                </div>
                            </td>
                            <td class="col-position">
                                <span class="position-badge-report"><?php echo htmlspecialchars($row[\'position\']); ?></span>
                            </td>
                            <td class="col-date">
                                <i class="fas fa-calendar-times"></i> <?php echo !empty($row[\'resign_date\']) ? date(\'d/m/Y\', strtotime($row[\'resign_date\'])) : \'N/A\'; ?>
                            </td>
                            <td class="col-notes">
                                <?php echo nl2br(htmlspecialchars($row[\'resign_reason\'] ?: \'-\')); ?>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>
';

    if (strpos($content, 'id="resigned-employees"') === false) {
        $content = preg_replace('/(<\?php endif; \?>\s*)<\/div>\s*<!-- Request Rejection Details Modal -->/', "\$1$html</div>\n\n<!-- Request Rejection Details Modal -->", $content);
        
        // Sometimes the comment is not there or different. Let's try a simpler regex if the above didn't match.
        if (strpos($content, 'id="resigned-employees"') === false) {
            $content = preg_replace('/(<\?php endif; \?>\s*)<\/div>\s*<div id="requestRejectionModal"/', "\$1$html</div>\n\n<div id=\"requestRejectionModal\"", $content);
        }
    }

    file_put_contents($file, $content);
}
echo "Done";
