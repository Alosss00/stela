<?php
$page_title = 'Reports - ' . ($_SESSION['department'] ?? 'Department');
require_once dirname(__DIR__, 3) . '/app/Helpers/auth_helper.php';
// Included via bootstrap/app.php

// Only department access permitted
if (!hasPermission('dept.access') && !(hasPermission('user.access') && hasDepartment()) && !isSuperadmin()) {
    header('Location: ../admin/dashboard.php');
    exit();
}

$db = new Database();
$department = $_SESSION['department'] ?? '';

// Get report data: approved and rejected appointments grouped by department (filtered by user's department)
$report_data = $db->query("
    SELECT 
        e.department,
        SUM(CASE WHEN a.status = 'approved' THEN 1 ELSE 0 END) as approved_count,
        SUM(CASE WHEN a.status = 'rejected' THEN 1 ELSE 0 END) as rejected_count,
        COUNT(*) as total_count
    FROM appointments a
    JOIN employees e ON a.employee_id = e.id
    WHERE a.status IN ('approved', 'rejected') AND e.department = '" . $db->escapeString($department) . "'
    GROUP BY e.department
    ORDER BY e.department
");

// Get detailed approved appointments for user's department (include KTT approvals)
$approved_appointments = $db->query("
    SELECT a.*, e.full_name as employee_name, e.employee_code, e.department, e.ruang_lingkup, e.supervision_area,
           p.position_name, p.position_type,
           u.full_name as created_by_name,
           au.full_name as approved_by_name,
           a.approved_date,
           a.ktt1_approved_date,
           a.ktt2_approved_date,
           ktt1.full_name as ktt1_name,
           ktt2.full_name as ktt2_name
    FROM appointments a
    JOIN employees e ON a.employee_id = e.id
    JOIN positions p ON a.position_id = p.id
    LEFT JOIN users u ON a.created_by = u.id
    LEFT JOIN users au ON a.approved_by = au.id
    LEFT JOIN users ktt1 ON a.ktt1_approved_by = ktt1.id
    LEFT JOIN users ktt2 ON a.ktt2_approved_by = ktt2.id
    WHERE a.status = 'approved' AND e.department = '" . $db->escapeString($department) . "'
    ORDER BY a.approved_date DESC
");

// Get detailed rejected appointments for user's department
$rejected_appointments = $db->query("
    SELECT a.*, e.full_name as employee_name, e.employee_code, e.department, e.ruang_lingkup, e.supervision_area,
           p.position_name, p.position_type,
           u.full_name as created_by_name,
           au.full_name as approved_by_name,
           a.approved_date,
           GROUP_CONCAT(
               CONCAT(ktt_u.full_name, ' (', ka.action, '): ', ka.approval_notes)
               SEPARATOR ' | '
           ) as ktt_notes
    FROM appointments a
    JOIN employees e ON a.employee_id = e.id
    JOIN positions p ON a.position_id = p.id
    LEFT JOIN users u ON a.created_by = u.id
    LEFT JOIN users au ON a.approved_by = au.id
    LEFT JOIN ktt_approvals ka ON a.id = ka.appointment_id
    LEFT JOIN users ktt_u ON ka.ktt_user_id = ktt_u.id
    WHERE a.status = 'rejected' AND e.department = '" . $db->escapeString($department) . "'
    GROUP BY a.id
    ORDER BY a.approved_date DESC
");

// Get statistics for user's department
$approved_total = $db->query("SELECT COUNT(*) as count FROM appointments a JOIN employees e ON a.employee_id = e.id WHERE a.status = 'approved' AND e.department = '" . $db->escapeString($department) . "'")->fetch_assoc()['count'];
$rejected_total = $db->query("SELECT COUNT(*) as count FROM appointments a JOIN employees e ON a.employee_id = e.id WHERE a.status = 'rejected' AND e.department = '" . $db->escapeString($department) . "'")->fetch_assoc()['count'];
$total_processed = $approved_total + $rejected_total;

// Get request data for user's department
$dept_filter = "e.department = '" . $db->escapeString($department) . "'";
$accepted_requests = $db->query("
    SELECT ec.*, e.full_name, e.employee_code, cert.cert_name, DATEDIFF(CURDATE(), ec.expiry_date) as days_expired
    FROM employee_certifications ec
    JOIN employees e ON ec.employee_id = e.id
    JOIN certifications cert ON ec.certification_id = cert.id
    WHERE ec.expiry_date IS NOT NULL
    AND ec.expiry_date < CURDATE()
    AND e.is_active = 1
    AND $dept_filter
    AND NOT EXISTS (
        SELECT 1 FROM employee_certifications ec2 
        WHERE ec2.employee_id = ec.employee_id 
        AND ec2.certification_id = ec.certification_id 
        AND ec2.id > ec.id
    )
    ORDER BY ec.expiry_date ASC, e.full_name");

$rejected_requests = $db->query("\n    SELECT e.*, e.created_at as request_date, e.updated_at as verification_date, u.full_name as verified_by_name\n    FROM employees e\n    LEFT JOIN users u ON e.verified_by = u.id\n    WHERE e.verification_status = 'rejected' AND e.department = '" . $db->escapeString($department) . "'\n    ORDER BY e.updated_at DESC\n");

$pending_requests = $db->query("\n    SELECT e.*, e.created_at as request_date\n    FROM employees e\n    WHERE e.verification_status = 'pending' AND e.department = '" . $db->escapeString($department) . "'\n    ORDER BY e.created_at DESC\n");

$accepted_requests_count = $accepted_requests ? $accepted_requests->num_rows : 0;
$rejected_requests_count = $rejected_requests ? $rejected_requests->num_rows : 0;
$pending_requests_count = $pending_requests ? $pending_requests->num_rows : 0;
$total_requests_processed = $accepted_requests_count + $rejected_requests_count + $pending_requests_count;

// Get expiring certificates for department (expiring within 60 days)
$expiring_certs = $db->query("
    SELECT e.*, cert.cert_name, ec.cert_number, ec.cert_type, ec.expiry_date,
           CEIL((DATEDIFF(ec.expiry_date, NOW())) / 1) as days_until_expiry
    FROM employee_certifications ec
    JOIN certifications cert ON ec.certification_id = cert.id
    JOIN employees e ON ec.employee_id = e.id
    WHERE DATEDIFF(ec.expiry_date, NOW()) <= 60 
    AND e.department = '" . $db->escapeString($department) . "'
    ORDER BY ec.expiry_date ASC
");

$expiring_certs_count = $expiring_certs ? $expiring_certs->num_rows : 0;

require_once dirname(__DIR__) . '/layouts/header.php';

// Get all supervision areas for filter
$supervision_areas = $db->query("SELECT * FROM supervision_areas WHERE is_active = 1 ORDER BY area_name");

// Get unique work scopes for filter (from user's department data)
$work_scopes = $db->query("\n    SELECT DISTINCT e.ruang_lingkup\n    FROM appointments a\n    JOIN employees e ON a.employee_id = e.id\n    WHERE a.status IN ('approved', 'rejected') \n    AND e.department = '" . $db->escapeString($department) . "'\n    AND e.ruang_lingkup IS NOT NULL AND e.ruang_lingkup != ''\n    ORDER BY e.ruang_lingkup\n");
?>


<div class="reports-container">
    <div class="page-header-reports">
        <div class="header-left">
            <h2><i class="fas fa-chart-bar"></i> <span data-lang="department-report">Department Report</span></h2>
            <p><span data-lang="report-summary">Summary and details of requests, assign letters, and certificates</span> - <?php echo htmlspecialchars($department); ?></p>
        </div>
        <div class="header-date">
            <i class="fas fa-calendar"></i> <?php echo date('d F Y'); ?>
        </div>
    </div>

    <div class="stats-grid-reports">
        <div class="stat-card-report stat-total">
            <div class="stat-icon-report"><i class="fas fa-file"></i></div>
            <div class="stat-content-report">
                <h3><?php echo $total_processed; ?></h3>
                <p data-lang="total-processed">Total Processed</p>
            </div>
        </div>

        <div class="stat-card-report stat-approved">
            <div class="stat-icon-report"><i class="fas fa-check-circle"></i></div>
            <div class="stat-content-report">
                <h3><?php echo $approved_total; ?></h3>
                <p data-lang="accepted">Accepted</p>
            </div>
        </div>

        <div class="stat-card-report stat-rejected">
            <div class="stat-icon-report"><i class="fas fa-times-circle"></i></div>
            <div class="stat-content-report">
                <h3><?php echo $rejected_total; ?></h3>
                <p data-lang="rejected">Rejected</p>
            </div>
        </div>
    </div>

    <div class="stats-grid-reports request-stats-grid">
        <div class="stat-card-report stat-total">
            <div class="stat-icon-report"><i class="fas fa-tasks"></i></div>
            <div class="stat-content-report">
                <h3><?php echo $total_requests_processed; ?></h3>
                <p data-lang="total-requests">Total Requests</p>
            </div>
        </div>

        <div class="stat-card-report stat-approved">
            <div class="stat-icon-report"><i class="fas fa-check-circle"></i></div>
            <div class="stat-content-report">
                <h3><?php echo $accepted_requests_count; ?></h3>
                <p data-lang="accepted-requests">Accepted Requests</p>
            </div>
        </div>

        <div class="stat-card-report stat-rejected">
            <div class="stat-icon-report"><i class="fas fa-times-circle"></i></div>
            <div class="stat-content-report">
                <h3><?php echo $rejected_requests_count; ?></h3>
                <p data-lang="rejected-requests">Rejected Requests</p>
            </div>
        </div>

        <div class="stat-card-report stat-pending">
            <div class="stat-icon-report"><i class="fas fa-hourglass-half"></i></div>
            <div class="stat-content-report">
                <h3><?php echo $pending_requests_count; ?></h3>
                <p data-lang="pending-requests">Pending Requests</p>
            </div>
        </div>
    </div>

    <?php if (($accepted_requests_count + $rejected_requests_count + $pending_requests_count) > 0): ?>
    <div class="card-report">
        <div class="card-header-report">
            <div style="display: flex; align-items: center; gap: 10px; flex: 1;">
                <h3 style="margin: 0;"><i class="fas fa-list"></i> <span data-lang="all-requests-section">All Requests</span></h3>
                <span class="badge-header"><?php echo $accepted_requests_count + $rejected_requests_count + $pending_requests_count; ?></span>
            </div>
        </div>

        <div class="filter-section-report">
            <div class="filter-group-report">
                <label><i class="fas fa-filter"></i> <span data-lang="status-label">Status:</span></label>
                <select id="statusFilterRequests" class="filter-select-report" onchange="filterRequestTable('requestsTable')">
                    <option value="" data-lang="all-statuses">-- All Statuses --</option>
                    <option value="verified" data-lang="accepted">Accepted</option>
                    <option value="rejected" data-lang="rejected">Rejected</option>
                    <option value="pending" data-lang="pending">Pending</option>
                </select>
            </div>
            <div class="filter-action-group">
                <button class="btn btn-export-small" onclick="exportRequestsToExcel('requestsTable', 'Department_Requests_Report')">
                    <i class="fas fa-file-excel"></i> <span data-lang="export-to-excel">Export to Excel</span>
                </button>
            </div>
        </div>

        <div class="card-body-report">
            <div class="table-responsive">
                <table class="table-report table-compact-request table-requests" style="width: 100%; min-width: 950px;" id="requestsTable">
                    <thead>
                        <tr>
                            <th class="col-employee" data-lang="employee">Employee</th>
                            <th class="col-code" data-lang="employee-code">Code</th>
                            <th class="col-request-date" data-lang="request-date">Request Date</th>
                            <th class="col-status" data-lang="status">Status</th>
                            <th class="col-verified-date" data-lang="verification-date">Verification Date</th>
                            <th class="col-verified-by" data-lang="verified-by">Verified By</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($accepted_requests_count > 0): ?>
                            <?php $accepted_requests->data_seek(0); while ($row = $accepted_requests->fetch_assoc()): ?>
                            <tr data-status="verified">
                                <td class="col-employee"><div class="employee-detail"><strong><?php echo htmlspecialchars($row['full_name']); ?></strong><span class="emp-code-detail"><?php echo htmlspecialchars($row['department']); ?></span></div></td>
                                <td class="col-code"><strong><?php echo htmlspecialchars($row['employee_code']); ?></strong></td>
                                <td class="col-request-date"><?php echo !empty($row['request_date']) ? date('d/m/Y H:i', strtotime($row['request_date'])) : 'N/A'; ?></td>
                                <td class="col-status"><span class="status-badge status-accepted"><i class="fas fa-check-circle"></i> <span data-lang="accepted">Accepted</span></span></td>
                                <td class="col-verified-date"><?php echo !empty($row['verification_date']) ? date('d/m/Y H:i', strtotime($row['verification_date'])) : 'N/A'; ?></td>
                                <td class="col-verified-by"><?php echo htmlspecialchars($row['verified_by_name'] ?: 'N/A'); ?></td>
                            </tr>
                            <?php endwhile; ?>
                        <?php endif; ?>

                        <?php if ($rejected_requests_count > 0): ?>
                            <?php $rejected_requests->data_seek(0); while ($row = $rejected_requests->fetch_assoc()): ?>
                            <tr data-status="rejected">
                                <td class="col-employee"><div class="employee-detail"><strong><?php echo htmlspecialchars($row['full_name']); ?></strong><span class="emp-code-detail"><?php echo htmlspecialchars($row['department']); ?></span></div></td>
                                <td class="col-code"><strong><?php echo htmlspecialchars($row['employee_code']); ?></strong></td>
                                <td class="col-request-date"><?php echo !empty($row['request_date']) ? date('d/m/Y H:i', strtotime($row['request_date'])) : 'N/A'; ?></td>
                                <td class="col-status"><span class="status-badge status-rejected-badge"><i class="fas fa-times-circle"></i> <span data-lang="rejected">Rejected</span></span></td>
                                <td class="col-verified-date"><?php echo !empty($row['verification_date']) ? date('d/m/Y H:i', strtotime($row['verification_date'])) : 'N/A'; ?></td>
                                <td class="col-verified-by"><span class="rejector-badge"><?php echo htmlspecialchars($row['verified_by_name'] ?: 'N/A'); ?></span></td>
                            </tr>
                            <?php endwhile; ?>
                        <?php endif; ?>

                        <?php if ($pending_requests_count > 0): ?>
                            <?php $pending_requests->data_seek(0); while ($row = $pending_requests->fetch_assoc()): ?>
                            <tr data-status="pending">
                                <td class="col-employee"><div class="employee-detail"><strong><?php echo htmlspecialchars($row['full_name']); ?></strong><span class="emp-code-detail"><?php echo htmlspecialchars($row['department']); ?></span></div></td>
                                <td class="col-code"><strong><?php echo htmlspecialchars($row['employee_code']); ?></strong></td>
                                <td class="col-request-date"><?php echo !empty($row['request_date']) ? date('d/m/Y H:i', strtotime($row['request_date'])) : 'N/A'; ?></td>
                                <td class="col-status"><span class="status-badge status-pending-badge"><i class="fas fa-hourglass-half"></i> <span data-lang="pending">Pending</span></span></td>
                                <td class="col-verified-date">-</td>
                                <td class="col-verified-by">-</td>
                            </tr>
                            <?php endwhile; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <div class="table-info-report" id="requestsTableInfo"><span data-lang="showing-all-data">Showing all data</span></div>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($approved_appointments && $approved_appointments->num_rows > 0): ?>
    <div class="card-report">
        <div class="card-header-report">
            <div style="display: flex; align-items: center; gap: 10px; flex: 1;"><h3 style="margin: 0;"><i class="fas fa-check-circle"></i> <span data-lang="detail-assign-letter-accepted">Detail Assign Letter Accepted</span></h3><span class="badge-header"><?php echo $approved_appointments->num_rows; ?></span></div>
            <button class="btn btn-export-pdf" onclick="exportApprovedByDepartment()"><i class="fas fa-file-pdf"></i> <span data-lang="export-pdf-report">Export PDF Report</span></button>
        </div>
        <div class="filter-section-report">
            <div class="filter-group-report"><label><i class="fas fa-map-marker-alt"></i> <span data-lang="work-scope-label">Scope:</span></label><select id="scopeFilterApproved" class="filter-select-report" onchange="filterTableByFilters('approvedTable')"><option value="" data-lang="all-scopes">-- All Scopes --</option><option value="MSM">PT MSM</option><option value="TTN">PT TTN</option></select></div>
            <div class="filter-group-report"><label><i class="fas fa-eye"></i> <span data-lang="supervision-area-label">Supervision Area:</span></label><select id="supervisionFilterApproved" class="filter-select-report" onchange="filterTableByFilters('approvedTable')"><option value="" data-lang="all-areas">-- All Areas --</option><?php if ($supervision_areas && $supervision_areas->num_rows > 0) { $supervision_areas->data_seek(0); while ($area = $supervision_areas->fetch_assoc()): ?><option value="<?php echo htmlspecialchars($area['area_name']); ?>"><?php echo htmlspecialchars($area['area_name']); ?></option><?php endwhile; } ?></select></div>
            <div class="filter-action-group"><button class="btn btn-export-small" onclick="exportToExcel('approvedTable', 'Report_Approved_Letters')"><i class="fas fa-file-excel"></i> <span data-lang="export-to-excel">Export to Excel</span></button></div>
        </div>
    <div class="card-body-report"><div class="table-responsive"><table class="table-report table-approved" style="width: 100%; min-width: 950px;" id="approvedTable"><thead><tr><th class="col-number" data-lang="assign-letter-no">Assign Letter No.</th><th class="col-employee" data-lang="employee">Employee</th><th class="col-position" data-lang="position">Position</th><th class="col-date" data-lang="effective-date">Effective Date</th><th class="col-approved-date" data-lang="approved">Approved</th><th class="col-approved-by" data-lang="approved-by">Approved By</th></tr></thead><tbody><?php $approved_appointments->data_seek(0); while ($row = $approved_appointments->fetch_assoc()): $scope_raw = $row['ruang_lingkup'] ?: ''; $scope_normalized = ''; if (stripos($scope_raw, 'MSM') !== false || stripos($scope_raw, 'Meares Soputan') !== false) { $scope_normalized = 'MSM'; } elseif (stripos($scope_raw, 'TTN') !== false || stripos($scope_raw, 'Tondano Nusajaya') !== false) { $scope_normalized = 'TTN'; } $supervision_area = htmlspecialchars($row['supervision_area'] ?: ''); ?><tr data-scope="<?php echo $scope_normalized; ?>" data-supervision="<?php echo $supervision_area; ?>"><td class="col-number"><strong><?php echo htmlspecialchars($row['appointment_number']); ?></strong></td><td class="col-employee"><div class="employee-detail"><strong><?php echo htmlspecialchars($row['employee_name']); ?></strong><span class="emp-code-detail"><?php echo htmlspecialchars($row['employee_code']); ?></span></div></td><td class="col-position"><span class="position-badge-report"><?php echo htmlspecialchars($row['position_name']); ?></span></td><td class="col-date"><i class="fas fa-calendar"></i> <?php echo date('d/m/Y', strtotime($row['effective_date'])); ?></td><td class="col-approved-date"><i class="fas fa-check"></i> <?php echo date('d/m/Y H:i', strtotime($row['approved_date'])); ?></td><td class="col-approved-by">                                    <div class="approval-info-container">                                        <?php if (!empty($row['ktt1_name']) || !empty($row['ktt2_name'])): ?>                                            <?php if (!empty($row['ktt1_name'])): ?>                                                <div class="approval-item">                                                    <span class="approver-name"><strong>KTT MSM:</strong> <?php echo htmlspecialchars($row['ktt1_name']); ?></span>                                                    <?php if (!empty($row['ktt1_approved_date'])): ?>                                                        <span class="approval-datetime"><?php echo date('d/m/Y', strtotime($row['ktt1_approved_date'])); ?> - <?php echo date('H:i', strtotime($row['ktt1_approved_date'])); ?></span>                                                    <?php endif; ?>                                                </div>                                            <?php endif; ?>                                            <?php if (!empty($row['ktt2_name'])): ?>                                                <div class="approval-item">                                                    <span class="approver-name"><strong>KTT TTN:</strong> <?php echo htmlspecialchars($row['ktt2_name']); ?></span>                                                    <?php if (!empty($row['ktt2_approved_date'])): ?>                                                        <span class="approval-datetime"><?php echo date('d/m/Y', strtotime($row['ktt2_approved_date'])); ?> - <?php echo date('H:i', strtotime($row['ktt2_approved_date'])); ?></span>                                                    <?php endif; ?>                                                </div>                                            <?php endif; ?>                                        <?php elseif (!empty($row['approved_by_name']) && !empty($row['approved_date'])): ?>                                            <div class="approval-item">                                                <span class="approver-name"><?php echo htmlspecialchars($row['approved_by_name']); ?></span>                                                <span class="approval-datetime"><?php echo date('d/m/Y', strtotime($row['approved_date'])); ?> - <?php echo date('H:i', strtotime($row['approved_date'])); ?></span>                                            </div>                                        <?php else: ?>                                            <span class="text-muted">N/A</span>                                        <?php endif; ?>                                    </div>                                </td></tr><?php endwhile; ?></tbody></table></div><div class="table-info-report" id="approvedTableInfo"><span data-lang="showing-all-data">Showing all data</span></div></div>
    </div>
    <?php endif; ?>

    <?php if ($rejected_appointments && $rejected_appointments->num_rows > 0): ?>
    <div class="card-report">
        <div class="card-header-report"><h3><i class="fas fa-times-circle"></i> <span data-lang="detail-assign-letter-rejected">Detail Assign Letter Rejected</span></h3><span class="badge-header rejected"><?php echo $rejected_appointments->num_rows; ?></span></div>
        <div class="filter-section-report">
            <div class="filter-group-report"><label><i class="fas fa-map-marker-alt"></i> <span data-lang="work-scope-label">Scope:</span></label><select id="scopeFilterRejected" class="filter-select-report" onchange="filterTableByFilters('rejectedTable')"><option value="" data-lang="all-scopes">-- All Scopes --</option><option value="MSM">PT MSM</option><option value="TTN">PT TTN</option></select></div>
            <div class="filter-group-report"><label><i class="fas fa-eye"></i> <span data-lang="supervision-area-label">Supervision Area:</span></label><select id="supervisionFilterRejected" class="filter-select-report" onchange="filterTableByFilters('rejectedTable')"><option value="" data-lang="all-areas">-- All Areas --</option><?php if ($supervision_areas && $supervision_areas->num_rows > 0) { $supervision_areas->data_seek(0); while ($area = $supervision_areas->fetch_assoc()): ?><option value="<?php echo htmlspecialchars($area['area_name']); ?>"><?php echo htmlspecialchars($area['area_name']); ?></option><?php endwhile; } ?></select></div>
            <div class="filter-action-group"><button class="btn btn-export-small" onclick="exportToExcel('rejectedTable', 'Report_Rejected_Letters')"><i class="fas fa-file-excel"></i> <span data-lang="export-to-excel">Export to Excel</span></button></div>
        </div>
        <div class="card-body-report"><div class="table-responsive"><table class="table-report table-rejected" style="width: 100%; min-width: 950px;" id="rejectedTable"><thead><tr><th class="col-number" data-lang="assign-letter-no">Assign Letter No.</th><th class="col-employee" data-lang="employee">Employee</th><th class="col-position" data-lang="position">Position</th><th class="col-date" data-lang="effective-date">Effective Date</th><th class="col-rejected-date" data-lang="rejected-date">Rejected Date</th><th class="col-rejected-by" data-lang="rejected-by">Rejected By</th><th class="col-notes" data-lang="rejection-notes">Rejection Notes</th><th class="col-action" data-lang="action">Action</th></tr></thead><tbody><?php $rejected_appointments->data_seek(0); while ($row = $rejected_appointments->fetch_assoc()): $scope_raw = $row['ruang_lingkup'] ?: ''; $scope_normalized = ''; if (stripos($scope_raw, 'MSM') !== false || stripos($scope_raw, 'Meares Soputan') !== false) { $scope_normalized = 'MSM'; } elseif (stripos($scope_raw, 'TTN') !== false || stripos($scope_raw, 'Tondano Nusajaya') !== false) { $scope_normalized = 'TTN'; } $supervision_area = htmlspecialchars($row['supervision_area'] ?: ''); ?><tr class="rejected-row" data-scope="<?php echo $scope_normalized; ?>" data-supervision="<?php echo $supervision_area; ?>"><td class="col-number"><strong><?php echo htmlspecialchars($row['appointment_number']); ?></strong></td><td class="col-employee"><div class="employee-detail"><strong><?php echo htmlspecialchars($row['employee_name']); ?></strong><span class="emp-code-detail"><?php echo htmlspecialchars($row['employee_code']); ?></span></div></td><td class="col-position"><span class="position-badge-report"><?php echo htmlspecialchars($row['position_name']); ?></span></td><td class="col-date"><i class="fas fa-calendar"></i> <?php echo date('d/m/Y', strtotime($row['effective_date'])); ?></td><td class="col-rejected-date"><i class="fas fa-times"></i> <?php echo date('d/m/Y H:i', strtotime($row['approved_date'])); ?></td><td class="col-rejected-by"><span class="rejector-badge"><?php echo htmlspecialchars($row['approved_by_name'] ?: 'N/A'); ?></span></td><td class="col-notes"><span class="notes-badge" onclick="showRejectionModal(<?php echo $row['id']; ?>, '<?php echo htmlspecialchars($row['appointment_number']); ?>', '<?php echo htmlspecialchars($row['employee_name']); ?>', '<?php echo htmlspecialchars($row['ktt_notes'] ?? ''); ?>')"><i class="fas fa-eye"></i> View Notes</span></td><td class="col-action"><button class="btn-detail-small" onclick="showRejectionModal(<?php echo $row['id']; ?>, '<?php echo htmlspecialchars($row['appointment_number']); ?>', '<?php echo htmlspecialchars($row['employee_name']); ?>', '<?php echo htmlspecialchars($row['ktt_notes'] ?? ''); ?>')"><i class="fas fa-info-circle"></i> Details</button></td></tr><?php endwhile; ?></tbody></table></div><div class="table-info-report" id="rejectedTableInfo"><span data-lang="showing-all-data">Showing all data</span></div></div>
    </div>
    <?php endif; ?>

    <?php if ($expiring_certs && $expiring_certs->num_rows > 0): ?>
    <div class="card-report" id="certificate-expiration">
        <div class="card-header-report"><div style="display: flex; align-items: center; gap: 10px; flex: 1;"><h3 style="margin: 0;"><i class="fas fa-exclamation-triangle"></i> <span data-lang="expired-certificates">Expired Certificates</span></h3><span class="badge-header warning"><?php echo $expiring_certs->num_rows; ?></span></div><button class="btn btn-export-small" onclick="exportExpiringCertsToExcel()"><i class="fas fa-file-excel"></i> <span data-lang="export-to-excel">Export to Excel</span></button></div>
        <div class="alert-warning-report"><i class="fas fa-info-circle"></i><span data-lang="expired-certs-renew-immediately">The following is a list of employees with expired certificates. Please renew certificates immediately.</span></div>
        <div class="card-body-report"><div class="table-responsive"><table class="table-report table-expiring" style="width: 100%; min-width: 950px;" id="expiringCertsTable"><thead><tr><th class="col-employee" data-lang="employee">Employee</th><th class="col-cert-name" data-lang="certificate-name">Certificate Name</th><th class="col-cert-number" data-lang="certificate-number">Certificate Number</th><th class="col-expiry-date" data-lang="expiry-date">Expiry Date</th><th class="col-days-left" data-lang="days-left">Days Left</th><th class="col-status-expiry" data-lang="status">Status</th></tr></thead><tbody><?php $expiring_certs->data_seek(0); while ($cert = $expiring_certs->fetch_assoc()): ?><tr class="expiring-row"><td class="col-employee"><div class="employee-detail"><strong><?php echo htmlspecialchars($cert['full_name']); ?></strong><span class="emp-code-detail"><?php echo htmlspecialchars($cert['employee_code']); ?></span></div></td><td class="col-cert-name"><span class="cert-name-badge"><?php echo htmlspecialchars($cert['cert_name'] ?: 'N/A'); ?></span></td><td class="col-cert-number"><?php echo htmlspecialchars($cert['cert_number'] ?: 'N/A'); ?></td><td class="col-expiry-date"><?php echo !empty($cert['expiry_date']) ? date('d/m/Y', strtotime($cert['expiry_date'])) : 'N/A'; ?></td><td class="col-days-left"><span class="days-badge <?php echo ($cert['days_until_expiry'] <= 30) ? 'days-critical' : (($cert['days_until_expiry'] <= 60) ? 'days-urgent' : 'days-warning'); ?>"><?php echo (int)$cert['days_until_expiry']; ?> days</span></td><td class="col-status-expiry"><span class="status-badge <?php echo ($cert['days_until_expiry'] <= 30) ? 'status-critical' : (($cert['days_until_expiry'] <= 60) ? 'status-urgent' : 'status-warning'); ?>"><?php echo ($cert['days_until_expiry'] <= 30) ? 'Critical' : (($cert['days_until_expiry'] <= 60) ? 'Urgent' : 'Warning'); ?></span></td></tr><?php endwhile; ?></tbody></table></div></div>
    </div>
    <?php endif; ?>
</div>

<div id="requestRejectionModal" class="modal">
    <div class="modal-content modal-rejection">
        <div class="modal-header modal-header-rejection"><h3><i class="fas fa-exclamation-circle"></i> <span data-lang="request-rejection-details">Request Rejection Details</span></h3><span class="close" onclick="closeRequestRejectionModal()">&times;</span></div>
        <div class="modal-body modal-body-rejection"><div class="rejection-info"><div class="info-row"><label data-lang="employee-name-label">Employee Name:</label><span id="reqRejectionEmployeeName"></span></div><div class="info-row"><label data-lang="employee-code-label">Employee Code:</label><span id="reqRejectionEmployeeCode"></span></div><div class="rejection-notes-section"><h4><i class="fas fa-clipboard"></i> <span data-lang="rejection-notes">Rejection Notes</span></h4><div class="rejection-notes-content" id="reqRejectionNotesContent"></div></div></div></div>
        <div class="modal-footer modal-footer-rejection"><button type="button" class="btn btn-secondary" onclick="closeRequestRejectionModal()"><span data-lang="close">Close</span></button></div>
    </div>
</div>

<div id="rejectionModal" class="modal">
    <div class="modal-content modal-rejection">
        <div class="modal-header modal-header-rejection"><h3><i class="fas fa-exclamation-circle"></i> <span data-lang="assign-letter-rejection-details">Assign Letter Rejection Details</span></h3><span class="close" onclick="closeRejectionModal()">&times;</span></div>
        <div class="modal-body modal-body-rejection"><div class="rejection-info"><div class="info-row"><label data-lang="assign-letter-no-label">Assign Letter No.:</label><span id="rejectionAppointmentNumber"></span></div><div class="info-row"><label data-lang="employee-name-label">Employee Name:</label><span id="rejectionEmployeeName"></span></div><div class="rejection-notes-section"><h4><i class="fas fa-clipboard"></i> <span data-lang="rejection-notes-from-ktt">KTT Rejection Notes</span></h4><div class="rejection-notes-content" id="rejectionNotesContent"></div></div></div></div>
        <div class="modal-footer modal-footer-rejection"><button type="button" class="btn btn-secondary" onclick="closeRejectionModal()"><span data-lang="close">Close</span></button></div>
    </div>
</div>

<script>
function exportRequestsToExcel(tableId, filename) {
    const table = document.getElementById(tableId);
    if (!table) {
        alert('Table not found');
        return;
    }

    const rows = table.getElementsByTagName('tbody')[0].getElementsByTagName('tr');
    const filteredRows = [];
    for (let row of rows) {
        if (row.style.display !== 'none') {
            filteredRows.push(row);
        }
    }

    if (filteredRows.length === 0) {
        alert('No data to export');
        return;
    }

    const departmentName = 'Department';
    const statusFilter = document.getElementById('statusFilterRequests').value;
    const statusLabel = statusFilter ? statusFilter.charAt(0).toUpperCase() + statusFilter.slice(1) : '';

    let excelContent = '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">';
    excelContent += '<head><meta charset="UTF-8"><!--[if gte mso 9]><xml><x:ExcelWorkbook><x:ExcelWorksheets><x:ExcelWorksheet>';
    excelContent += '<x:Name>Department Requests</x:Name><x:WorksheetOptions><x:DisplayGridlines/></x:WorksheetOptions></x:ExcelWorksheet>';
    excelContent += '</x:ExcelWorksheets></x:ExcelWorkbook></xml><![endif]--></head><body>';
    excelContent += '<table><tr><td colspan="6"><h2>Department Request Report</h2></td></tr>';
    excelContent += '<tr><td colspan="6">Department: ' + departmentName + '</td></tr>';
    excelContent += '<tr><td colspan="6">Export Date: ' + new Date().toLocaleDateString('en-US') + ' ' + new Date().toLocaleTimeString('en-US') + '</td></tr>';
    if (statusLabel) {
        excelContent += '<tr><td colspan="6">Filter Status: ' + statusLabel + '</td></tr>';
    }
    excelContent += '<tr><td colspan="6">Total Data: ' + filteredRows.length + '</td></tr>';
    excelContent += '<tr><td colspan="6">&nbsp;</td></tr>';
    excelContent += '<tr style="background-color: #37474F; color: white; font-weight: bold;">';
    excelContent += '<td>Employee</td><td>Code</td><td>Request Date</td><td>Status</td><td>Verification Date</td><td>Verified By</td>';
    excelContent += '</tr>';

    for (let row of filteredRows) {
        const cells = row.getElementsByTagName('td');
        excelContent += '<tr>';
        excelContent += '<td>' + cells[0].textContent.trim().replace(/\s+/g, ' ') + '</td>';
        excelContent += '<td>' + cells[1].textContent.trim() + '</td>';
        excelContent += '<td>' + cells[2].textContent.trim() + '</td>';
        excelContent += '<td>' + cells[3].textContent.trim().replace(/\s+/g, ' ') + '</td>';
        excelContent += '<td>' + cells[4].textContent.trim() + '</td>';
        excelContent += '<td>' + cells[5].textContent.trim() + '</td>';
        excelContent += '</tr>';
    }

    excelContent += '</table></body></html>';

    const blob = new Blob([excelContent], { type: 'application/vnd.ms-excel' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = filename + '_' + departmentName.replace(/[^a-zA-Z0-9]/g, '_') + (statusLabel ? '_' + statusLabel : '') + '_' + new Date().toISOString().slice(0, 10) + '.xls';
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    window.URL.revokeObjectURL(url);
}

function exportApprovedByDepartment() {
    const table = document.getElementById('approvedTable');
    if (!table) {
        const tableNotFound = window.getLanguageText('');
        alert(tableNotFound);
        return;
    }
    
    // Get all visible rows from the table
    const rows = table.getElementsByTagName('tbody')[0].getElementsByTagName('tr');
    let filteredRows = [];
    
    for (let row of rows) {
        if (row.style.display !== 'none') {
            filteredRows.push(row);
        }
    }
    
    if (filteredRows.length === 0) {
        const noDataToPrint = window.getLanguageText('');
        alert(noDataToPrint);
        return;
    }
    
    const departmentName = 'Department';
    
    // Generate HTML for PDF
    const printTitle = window.getLanguageText('');
    const printedOnText = window.getLanguageText('');
    const timeText = window.getLanguageText('');
    const totalAcceptedText = window.getLanguageText('');
    const footerPrintText = window.getLanguageText('');

    let htmlContent = `
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>${printTitle} - ${departmentName}</title>
            
        </head>
        <body>
            <h1>?? ${printTitle}</h1>
            <div class="department-name">${departmentName}</div>
            <div class="header-info">
                ${printedOnText}: ${new Date().toLocaleDateString('id-ID', { year: 'numeric', month: 'long', day: 'numeric' })}
                <br>${timeText}: ${new Date().toLocaleTimeString('id-ID')}
            </div>
            <div class="summary-box">
                ? ${totalAcceptedText}: ${filteredRows.length}
            </div>
            <table>
                <thead>
                    <tr>
                        <th style="width: 15%;">${window.getLanguageText('')}</th>
                        <th style="width: 22%;">${window.getLanguageText('')}</th>
                        <th style="width: 20%;">${window.getLanguageText('')}</th>
                        <th style="width: 13%;">${window.getLanguageText('')}</th>
                        <th style="width: 13%;">${window.getLanguageText('')}</th>
                        <th style="width: 17%;">${window.getLanguageText('')}</th>
                    </tr>
                </thead>
                <tbody>
    `;
    
    for (let row of filteredRows) {
        const cells = row.getElementsByTagName('td');
        htmlContent += `
            <tr>
                <td>${cells[0].textContent.trim()}</td>
                <td>${cells[1].textContent.trim().split('\\n')[0]}</td>
                <td>${cells[2].textContent.trim()}</td>
                <td>${cells[3].textContent.trim()}</td>
                <td>${cells[4].textContent.trim()}</td>
                <td>${cells[5].textContent.trim()}</td>
            </tr>
        `;
    }
    
    htmlContent += `
                </tbody>
            </table>
            <div class="footer">
                ${footerPrintText}
            </div>
        </body>
        </html>
    `;
    
    // Open in new window and print
    const printWindow = window.open('', '_blank');
    printWindow.document.write(htmlContent);
    printWindow.document.close();
    
    // Wait for content to load then print
    setTimeout(() => {
        printWindow.print();
    }, 250);
}

function exportToExcel(tableId, filename) {
    const table = document.getElementById(tableId);
    if (!table) {
        const tableNotFound = window.getLanguageText('');
        alert(tableNotFound);
        return;
    }
    
    const rows = table.getElementsByTagName('tbody')[0].getElementsByTagName('tr');
    let filteredRows = [];
    
    // Get only visible rows
    for (let row of rows) {
        if (row.style.display !== 'none') {
            filteredRows.push(row);
        }
    }
    
    if (filteredRows.length === 0) {
        const noDataToExport = window.getLanguageText('');
        alert(noDataToExport);
        return;
    }
    
    // Get active filter
    const scopeFilter = document.getElementById('scopeFilter' + (tableId === 'approvedTable' ? 'Approved' : 'Rejected')).value;
    
    // Determine if it's approved or rejected table
    const isApproved = tableId === 'approvedTable';
    const departmentName = 'Department';
    
    // Build Excel HTML
    let excelContent = '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">';
    excelContent += '<head><meta charset="UTF-8"><!--[if gte mso 9]><xml><x:ExcelWorkbook><x:ExcelWorksheets><x:ExcelWorksheet>';
    excelContent += '<x:Name>Laporan</x:Name><x:WorksheetOptions><x:DisplayGridlines/></x:WorksheetOptions></x:ExcelWorksheet>';
    excelContent += '</x:ExcelWorksheets></x:ExcelWorkbook></xml><![endif]--></head><body>';
    
    const reportTitleText = window.getLanguageText('');
    const acceptedText = window.getLanguageText('');
    const rejectedText = window.getLanguageText('');
    const departmentText = window.getLanguageText('');
    const exportDateText = window.getLanguageText('');
    const scopeFilterText = window.getLanguageText('');
    const totalDataText = window.getLanguageText('');

    // Add title and filters info
    excelContent += '<table><tr><td colspan="7"><h2>' + reportTitleText + ' ' + (isApproved ? acceptedText : rejectedText) + '</h2></td></tr>';
    excelContent += '<tr><td colspan="7">' + departmentText + ': ' + departmentName + '</td></tr>';
    excelContent += '<tr><td colspan="7">' + exportDateText + ': ' + new Date().toLocaleDateString('id-ID') + ' ' + new Date().toLocaleTimeString('id-ID') + '</td></tr>';
    
    if (scopeFilter) {
        const scopeLabel = scopeFilter === 'MSM' ? 'PT MSM' : 'PT TTN';
        excelContent += '<tr><td colspan="7">' + scopeFilterText + ': ' + scopeLabel + '</td></tr>';
    }
    
    excelContent += '<tr><td colspan="7">' + totalDataText + ': ' + filteredRows.length + '</td></tr>';
    excelContent += '<tr><td colspan="7">&nbsp;</td></tr>';
    
    // Add table headers
    excelContent += '<tr style="background-color: #37474F; color: white; font-weight: bold;">';
    excelContent += '<td>' + (window.getLanguageText('')) + '</td>';
    excelContent += '<td>' + (window.getLanguageText('')) + '</td>';
    excelContent += '<td>' + (window.getLanguageText('')) + '</td>';
    excelContent += '<td>' + (window.getLanguageText('')) + '</td>';
    excelContent += '<td>' + (window.getLanguageText('')) + '</td>';
    excelContent += '<td>' + (isApproved ? acceptedText : rejectedText) + '</td>';
    excelContent += '<td>' + (isApproved ? (window.getLanguageText('')) : (window.getLanguageText(''))) + '</td>';
    if (!isApproved) {
        excelContent += '<td>' + (window.getLanguageText('')) + '</td>';
    }
    excelContent += '</tr>';
    
    // Add table data
    for (let row of filteredRows) {
        const cells = row.getElementsByTagName('td');
        excelContent += '<tr>';
        
        // No. Surat
        excelContent += '<td>' + cells[0].textContent.trim() + '</td>';
        
        // Nama Karyawan (mengambil hanya nama, tanpa kode)
        const employeeText = cells[1].textContent.trim().split('\n');
        excelContent += '<td>' + employeeText[0].trim() + '</td>';
        
        // Kode Karyawan
        if (employeeText.length > 1) {
            excelContent += '<td>' + employeeText[1].trim() + '</td>';
        } else {
            excelContent += '<td></td>';
        }
        
        // Jabatan
        excelContent += '<td>' + cells[2].textContent.trim() + '</td>';
        
        // Tanggal Efektif
        excelContent += '<td>' + cells[3].textContent.trim() + '</td>';
        
        // Approved/Rejected Date
        excelContent += '<td>' + cells[4].textContent.trim() + '</td>';
        
        // Approved/Rejected By
        excelContent += '<td>' + cells[5].textContent.trim() + '</td>';
        
        // Notes (for rejected only)
        if (!isApproved && cells.length > 6) {
            excelContent += '<td>View Details</td>';
        }
        
        excelContent += '</tr>';
    }
    
    excelContent += '</table></body></html>';
    
    // Create download link
    const blob = new Blob([excelContent], {
        type: 'application/vnd.ms-excel'
    });
    
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    
    // Create filename with filters
    let finalFilename = filename + '_' + departmentName.replace(/[^a-zA-Z0-9]/g, '_');
    if (scopeFilter) {
        const scopeLabel = scopeFilter === 'MSM' ? 'PT_MSM' : 'PT_TTN';
        finalFilename += '_' + scopeLabel;
    }
    finalFilename += '_' + new Date().toISOString().slice(0, 10) + '.xls';
    
    a.download = finalFilename;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    window.URL.revokeObjectURL(url);
}
</script>

<?php require_once dirname(__DIR__) . '/layouts/footer.php'; ?>
