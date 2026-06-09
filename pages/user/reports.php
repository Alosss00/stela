<?php
$page_title = 'Reports';
require_once '../../includes/auth.php';
require_once '../../includes/db.php';

// Only USER role can access this page
checkPageAccess(['user']);

$db = new Database();
$company_name = $_SESSION['company_name'] ?? '';

// Get report data: approved and rejected appointments grouped by company (filtered by user's company)
$report_data = $db->query("
    SELECT 
        e.contractor_company,
        SUM(CASE WHEN a.status = 'approved' THEN 1 ELSE 0 END) as approved_count,
        SUM(CASE WHEN a.status = 'rejected' THEN 1 ELSE 0 END) as rejected_count,
        COUNT(*) as total_count
    FROM appointments a
    JOIN employees e ON a.employee_id = e.id
    WHERE a.status IN ('approved', 'rejected') AND e.contractor_company = '" . $db->escapeString($company_name) . "'
    GROUP BY e.contractor_company
    ORDER BY e.contractor_company
");

// Get detailed approved appointments for user's company
$approved_appointments = $db->query("
    SELECT a.*, e.full_name as employee_name, e.employee_code, e.contractor_company, e.ruang_lingkup, e.supervision_area,
           p.position_name, p.position_type,
           u.full_name as created_by_name,
           au.full_name as approved_by_name,
           a.approved_date,
           ktt1.full_name as ktt1_name,
           ktt2.full_name as ktt2_name,
           a.ktt1_approved_date,
           a.ktt2_approved_date
    FROM appointments a
    JOIN employees e ON a.employee_id = e.id
    JOIN positions p ON a.position_id = p.id
    LEFT JOIN users u ON a.created_by = u.id
    LEFT JOIN users au ON a.approved_by = au.id
    LEFT JOIN users ktt1 ON a.ktt1_approved_by = ktt1.id
    LEFT JOIN users ktt2 ON a.ktt2_approved_by = ktt2.id
    WHERE a.status = 'approved' AND e.contractor_company = '" . $db->escapeString($company_name) . "'
    ORDER BY a.approved_date DESC
");

// Get detailed rejected appointments for user's company
$rejected_appointments = $db->query("
    SELECT a.*, e.full_name as employee_name, e.employee_code, e.contractor_company, e.ruang_lingkup, e.supervision_area,
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
    WHERE a.status = 'rejected' AND e.contractor_company = '" . $db->escapeString($company_name) . "'
    GROUP BY a.id
    ORDER BY a.approved_date DESC
");

// Get statistics for user's company
$approved_total = $db->query("SELECT COUNT(*) as count FROM appointments a JOIN employees e ON a.employee_id = e.id WHERE a.status = 'approved' AND e.contractor_company = '" . $db->escapeString($company_name) . "'")->fetch_assoc()['count'];
$rejected_total = $db->query("SELECT COUNT(*) as count FROM appointments a JOIN employees e ON a.employee_id = e.id WHERE a.status = 'rejected' AND e.contractor_company = '" . $db->escapeString($company_name) . "'")->fetch_assoc()['count'];
$total_processed = $approved_total + $rejected_total;

// Get request data for user's company - Combined query
$all_requests = $db->query("
    SELECT 
        e.*, 
        e.created_at as request_date, 
        e.updated_at as verification_date, 
        u.full_name as verified_by_name,
        e.verification_status,
        e.verification_notes
    FROM employees e
    LEFT JOIN users u ON e.verified_by = u.id
    WHERE e.contractor_company = '" . $db->escapeString($company_name) . "'
    AND e.verification_status IN ('verified', 'rejected', 'pending')
    ORDER BY 
        CASE WHEN e.verification_status = 'verified' THEN 0
             WHEN e.verification_status = 'rejected' THEN 1
             WHEN e.verification_status = 'pending' THEN 2
        END,
        e.updated_at DESC, e.created_at DESC
");

$accepted_requests_count = 0;
$rejected_requests_count = 0;
$pending_requests_count = 0;
$total_requests_processed = 0;

if ($all_requests) {
    $total_requests_processed = $all_requests->num_rows;
    $all_requests->data_seek(0);
    while ($row = $all_requests->fetch_assoc()) {
        if ($row['verification_status'] === 'verified') {
            $accepted_requests_count++;
        } elseif ($row['verification_status'] === 'rejected') {
            $rejected_requests_count++;
        } elseif ($row['verification_status'] === 'pending') {
            $pending_requests_count++;
        }
    }
    $all_requests->data_seek(0);
}

// Get employees with certificates expiring in 2 months or less (for user's company)
$expiring_certs = $db->query("
    SELECT 
        e.id as employee_id,
        e.full_name,
        e.employee_code,
        e.contractor_company,
        e.ruang_lingkup,
        ec.id as cert_id,
        ec.cert_number,
        ec.expiry_date,
        c.cert_name,
        DATEDIFF(ec.expiry_date, CURDATE()) as days_until_expiry
    FROM employee_certifications ec
    JOIN employees e ON ec.employee_id = e.id
    LEFT JOIN certifications c ON ec.certification_id = c.id
    WHERE ec.expiry_date IS NOT NULL
    AND ec.verification_status = 'verified'
    AND ec.expiry_date <= DATE_ADD(CURDATE(), INTERVAL 2 MONTH)
    AND e.is_active = 1
    AND e.contractor_company = '" . $db->escapeString($company_name) . "'
    ORDER BY ec.expiry_date ASC, e.full_name
");

$expiring_certs_count = $expiring_certs ? $expiring_certs->num_rows : 0;

require_once '../../includes/header.php';

// Get all supervision areas for filter
$supervision_areas = $db->query("SELECT * FROM supervision_areas WHERE is_active = 1 ORDER BY area_name");

// Get unique work scopes for filter (from user's company data)
$work_scopes = $db->query("
    SELECT DISTINCT e.ruang_lingkup
    FROM appointments a
    JOIN employees e ON a.employee_id = e.id
    WHERE a.status IN ('approved', 'rejected') 
    AND e.contractor_company = '" . $db->escapeString($company_name) . "'
    AND e.ruang_lingkup IS NOT NULL AND e.ruang_lingkup != ''
    ORDER BY e.ruang_lingkup
");
?>

<div class="reports-container">
    <!-- Page Header -->
    <div class="page-header-reports">
        <div class="header-left">
            <h2><i class="fas fa-chart-bar"></i> <span data-lang="company-report">Company Report</span></h2>
            <p><span data-lang="report-summary">Summary and details of requests, assign letters, and certificates</span> - <?php echo htmlspecialchars($company_name); ?></p>
        </div>
        <div class="header-date">
            <i class="fas fa-calendar"></i> <?php echo date('d F Y'); ?>
        </div>
    </div>
    
    <!-- Overview Statistics -->
    <div class="stats-grid-reports">
        <div class="stat-card-report stat-total">
            <div class="stat-icon-report"><i class="fas fa-file"></i></div>
            <div class="stat-content-report">
                <h3><?php echo $total_processed; ?></h3>
                <p data-lang="assign-letters-processed">Assign Letters Processed</p>
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

    <!-- Request Overview Statistics -->
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

    <!-- All Requests - Consolidated Table -->
    <?php if ($all_requests && $all_requests->num_rows > 0): ?>
    <div class="card-report">
        <div class="card-header-report">
            <div style="display: flex; align-items: center; gap: 10px; flex: 1;">
                <h3 style="margin: 0;"><i class="fas fa-list"></i> <span data-lang="all-requests-section">All Request</span></h3>
                <span class="badge-header"><?php echo $all_requests->num_rows; ?></span>
            </div>
        </div>

        <!-- Filter by Status -->
        <div class="filter-section-report">
            <div class="filter-group-report">
                <label><i class="fas fa-filter"></i> <span data-lang="status-label">Status:</span></label>
                <select id="statusFilterRequests" class="filter-select-report" onchange="filterTableByStatus('requestsTable')">
                    <option value="" data-lang="all-statuses">-- Select Status --</option>
                    <option value="verified" data-lang="accepted">Accepted</option>
                    <option value="rejected" data-lang="status-rejected">Rejected</option>
                    <option value="pending" data-lang="status-pending">Pending</option>
                </select>
            </div>
            <div class="filter-action-group">
                <button class="btn btn-export-small" onclick="exportRequestsToExcel('requestsTable', 'Employee_Requests_Report')">
                    <i class="fas fa-file-excel"></i> <span data-lang="export-to-excel">Export to Excel</span>
                </button>
            </div>
        </div>

        <div class="card-body-report">
            <div class="table-responsive">
                <table class="table-report table-compact-request" id="requestsTable">
                    <thead>
                        <tr>
                            <th class="col-employee" data-lang="employee">Employee</th>
                            <th class="col-code" data-lang="code">ID Badge</th>
                            <th class="col-date" data-lang="request-date">Submission Date</th>
                            <th class="col-status" data-lang="status">Status</th>
                            <th class="col-verified-date" data-lang="verified-date">Verification Date</th>
                            <th class="col-verified-by" data-lang="verified-by">Verified By</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        if ($all_requests) {
                            $all_requests->data_seek(0);
                            while ($row = $all_requests->fetch_assoc()):
                                $status = $row['verification_status'];
                                $statusDisplay = ucfirst($status);
                        ?>
                        <tr class="detail-row" data-status="<?php echo htmlspecialchars($status); ?>">
                            <td class="col-employee">
                                <div class="employee-detail">
                                    <strong><?php echo htmlspecialchars($row['full_name']); ?></strong>
                                </div>
                            </td>
                            <td class="col-code">
                                <span class="emp-code-detail"><?php echo htmlspecialchars($row['employee_code']); ?></span>
                            </td>
                            <td class="col-date"><?php echo !empty($row['request_date']) ? date('d/m/Y H:i', strtotime($row['request_date'])) : 'N/A'; ?></td>
                            <td class="col-status">
                                <?php if ($status === 'verified'): ?>
                                    <span class="status-badge status-accepted"><i class="fas fa-check-circle"></i> <span data-lang="accepted">Accepted</span></span>
                                <?php elseif ($status === 'rejected'): ?>
                                    <span class="status-badge status-rejected-badge"><i class="fas fa-times-circle"></i> <span data-lang="rejected">Rejected</span></span>
                                <?php elseif ($status === 'pending'): ?>
                                    <span class="status-badge status-pending-badge"><i class="fas fa-hourglass-half"></i> <span data-lang="pending">Pending</span></span>
                                <?php endif; ?>
                            </td>
                            <td class="col-verified-date"><?php echo !empty($row['verification_date']) ? date('d/m/Y H:i', strtotime($row['verification_date'])) : 'N/A'; ?></td>
                            <td class="col-verified-by"><?php echo htmlspecialchars($row['verified_by_name'] ?: 'N/A'); ?></td>
                        </tr>
                        <?php 
                            endwhile;
                        }
                        ?>
                    </tbody>
                </table>
            </div>

            <?php if (!$all_requests || $all_requests->num_rows === 0): ?>
            <div style="padding: 20px; text-align: center; color: #999;">
                <p data-lang="no-requests-data">No requests data available</p>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Approved Appointments -->
    <?php if ($approved_appointments && $approved_appointments->num_rows > 0): ?>
    <div class="card-report">
        <div class="card-header-report">
            <div style="display: flex; align-items: center; gap: 10px; flex: 1;">
                <h3 style="margin: 0;"><i class="fas fa-check-circle"></i> <span data-lang="detail-assign-letter-accepted">Detail Assign Letter Accepted</span></h3>
                <span class="badge-header"><?php echo $approved_appointments->num_rows; ?></span>
            </div>
            <button class="btn btn-export-pdf" onclick="exportApprovedByCompany()">
                <i class="fas fa-file-pdf"></i> <span data-lang="export-pdf-report">Export PDF Report</span>
            </button>
        </div>
        
        <!-- Filter by Ruang Lingkup -->
        <div class="filter-section-report">
            <div class="filter-group-report">
                <label><i class="fas fa-map-marker-alt"></i> <span data-lang="work-scope-label">Scope:</span></label>
                <select id="scopeFilterApproved" class="filter-select-report" onchange="filterTableByFilters('approvedTable')">
                    <option value="" data-lang="all-scopes">-- Select Scopes --</option>
                    <option value="MSM">PT MSM</option>
                    <option value="TTN">PT TTN</option>
                </select>
            </div>
            <div class="filter-group-report">
                <label><i class="fas fa-eye"></i> <span data-lang="supervision-area-label">Supervision Area:</span></label>
                <select id="supervisionFilterApproved" class="filter-select-report" onchange="filterTableByFilters('approvedTable')">
                    <option value="" data-lang="all-areas">-- Select Areas --</option>
                    <?php
                    if ($supervision_areas && $supervision_areas->num_rows > 0) {
                        $supervision_areas->data_seek(0);
                        while ($area = $supervision_areas->fetch_assoc()):
                    ?>
                    <option value="<?php echo htmlspecialchars($area['area_name']); ?>">
                        <?php echo htmlspecialchars($area['area_name']); ?>
                    </option>
                    <?php
                        endwhile;
                    }
                    ?>
                </select>
            </div>
            <div class="filter-action-group">
                <button class="btn btn-export-small" onclick="exportToExcel('approvedTable', 'Assign_Letter_Report_Accepted')">
                    <i class="fas fa-file-excel"></i> <span data-lang="export-to-excel">Export to Excel</span>
                </button>
            </div>
        </div>
        
        <div class="card-body-report">
            <div class="table-responsive">
                <table class="table-report table-approved" id="approvedTable">
                    <thead>
                        <tr>
                            <th class="col-number" data-lang="assign-letter-no">Assign Letter No.</th>
                            <th class="col-employee" data-lang="employee">Employee</th>
                            <th class="col-position" data-lang="position">Position</th>
                            <th class="col-date" data-lang="effective-date">Effective Date</th>
                            <th class="col-approved-info" data-lang="approved-by">Approved By</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $approved_appointments->data_seek(0);
                        while ($row = $approved_appointments->fetch_assoc()): 
                            // Normalize ruang_lingkup to MSM or TTN
                            $scope_raw = $row['ruang_lingkup'] ?: '';
                            $scope_normalized = '';
                            if (stripos($scope_raw, 'MSM') !== false || stripos($scope_raw, 'Meares Soputan') !== false) {
                                $scope_normalized = 'MSM';
                            } elseif (stripos($scope_raw, 'TTN') !== false || stripos($scope_raw, 'Tondano Nusajaya') !== false) {
                                $scope_normalized = 'TTN';
                            }
                            $supervision_area = htmlspecialchars($row['supervision_area'] ?: '');
                        ?>
                        <tr class="detail-row" data-scope="<?php echo $scope_normalized; ?>" data-supervision="<?php echo $supervision_area; ?>">
                            <td class="col-number">
                                <strong><?php echo htmlspecialchars($row['appointment_number']); ?></strong>
                            </td>
                            <td class="col-employee">
                                <div class="employee-detail">
                                    <strong><?php echo htmlspecialchars($row['employee_name']); ?></strong>
                                    <span class="emp-code-detail"><?php echo htmlspecialchars($row['employee_code']); ?></span>
                                </div>
                            </td>
                            <td class="col-position">
                                <span class="position-badge-report"><?php echo htmlspecialchars($row['position_name']); ?></span>
                            </td>
                            <td class="col-date">
                                <i class="fas fa-calendar"></i> <?php echo date('d/m/Y', strtotime($row['effective_date'])); ?>
                            </td>
                            <td class="col-approved-info">
                                <div class="approval-info-container">
                                    <?php if ($row['ktt1_name'] || $row['ktt2_name']): ?>
                                        <?php if ($row['ktt1_name']): ?>
                                            <div class="approval-item">
                                                <span class="approver-name">
                                                    <strong>KTT MSM:</strong> <?php echo htmlspecialchars($row['ktt1_name']); ?>
                                                </span>
                                                <?php if ($row['ktt1_approved_date']): ?>
                                                    <span class="approval-datetime">
                                                        <?php echo date('d/m/Y', strtotime($row['ktt1_approved_date'])); ?> - <?php echo date('H:i', strtotime($row['ktt1_approved_date'])); ?>
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                        <?php if ($row['ktt2_name']): ?>
                                            <div class="approval-item">
                                                <span class="approver-name">
                                                    <strong>KTT TTN:</strong> <?php echo htmlspecialchars($row['ktt2_name']); ?>
                                                </span>
                                                <?php if ($row['ktt2_approved_date']): ?>
                                                    <span class="approval-datetime">
                                                        <?php echo date('d/m/Y', strtotime($row['ktt2_approved_date'])); ?> - <?php echo date('H:i', strtotime($row['ktt2_approved_date'])); ?>
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                    <?php elseif ($row['approved_by_name'] && $row['approved_date']): ?>
                                        <div class="approval-item">
                                            <span class="approver-name">
                                                <?php echo htmlspecialchars($row['approved_by_name']); ?>
                                            </span>
                                            <span class="approval-datetime">
                                                <?php echo date('d/m/Y', strtotime($row['approved_date'])); ?> - <?php echo date('H:i', strtotime($row['approved_date'])); ?>
                                            </span>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-muted">N/A</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
            <div class="table-info-report" id="approvedTableInfo">
                <span data-lang="showing-all-data">Showing all data</span>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Rejected Appointments -->
    <?php if ($rejected_appointments && $rejected_appointments->num_rows > 0): ?>
    <div class="card-report">
        <div class="card-header-report">
            <h3><i class="fas fa-times-circle"></i> <span data-lang="detail-assign-letter-rejected">Detail Assign Letter Rejected</span></h3>
            <span class="badge-header rejected"><?php echo $rejected_appointments->num_rows; ?></span>
        </div>
        
        <!-- Filter by Scope -->
        <div class="filter-section-report">
            <div class="filter-group-report">
                <label><i class="fas fa-map-marker-alt"></i> <span data-lang="work-scope-label">Scope:</span></label>
                <select id="scopeFilterRejected" class="filter-select-report" onchange="filterTableByFilters('rejectedTable')">
                    <option value="" data-lang="all-scopes">-- All Scopes --</option>
                    <option value="MSM">PT MSM</option>
                    <option value="TTN">PT TTN</option>
                </select>
            </div>
            <div class="filter-group-report">
                <label><i class="fas fa-eye"></i> <span data-lang="supervision-area-label">Supervision Area:</span></label>
                <select id="supervisionFilterRejected" class="filter-select-report" onchange="filterTableByFilters('rejectedTable')">
                    <option value="" data-lang="all-areas">-- All Areas --</option>
                    <?php
                    if ($supervision_areas && $supervision_areas->num_rows > 0) {
                        $supervision_areas->data_seek(0);
                        while ($area = $supervision_areas->fetch_assoc()):
                    ?>
                    <option value="<?php echo htmlspecialchars($area['area_name']); ?>">
                        <?php echo htmlspecialchars($area['area_name']); ?>
                    </option>
                    <?php
                        endwhile;
                    }
                    ?>
                </select>
            </div>
            <div class="filter-action-group">
                <button class="btn btn-export-small" onclick="exportToExcel('rejectedTable', 'Assign_Letter_Report_Rejected')">
                    <i class="fas fa-file-excel"></i> <span data-lang="export-to-excel">Export to Excel</span>
                </button>
            </div>
        </div>
        
        <div class="card-body-report">
            <div class="table-responsive">
                <table class="table-report table-rejected" id="rejectedTable">
                    <thead>
                        <tr>
                            <th class="col-number" data-lang="assign-letter-no">Assign Letter No.</th>
                            <th class="col-employee" data-lang="employee">Employee</th>
                            <th class="col-position" data-lang="position">Position</th>
                            <th class="col-date" data-lang="effective-date">Effective Date</th>
                            <th class="col-rejected-date" data-lang="rejected-date">Rejected Date</th>
                            <th class="col-rejected-by" data-lang="rejected-by">Rejected By</th>
                            <th class="col-notes" data-lang="rejection-notes">Rejection Notes</th>
                            <th class="col-action" data-lang="action">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $rejected_appointments->data_seek(0);
                        while ($row = $rejected_appointments->fetch_assoc()): 
                            // Normalize ruang_lingkup to MSM or TTN
                            $scope_raw = $row['ruang_lingkup'] ?: '';
                            $scope_normalized = '';
                            if (stripos($scope_raw, 'MSM') !== false || stripos($scope_raw, 'Meares Soputan') !== false) {
                                $scope_normalized = 'MSM';
                            } elseif (stripos($scope_raw, 'TTN') !== false || stripos($scope_raw, 'Tondano Nusajaya') !== false) {
                                $scope_normalized = 'TTN';
                            }
                            $rejection_id = 'rejection_' . $row['id'];
                            $supervision_area = htmlspecialchars($row['supervision_area'] ?: '');
                        ?>
                        <tr class="detail-row rejected-row" data-scope="<?php echo $scope_normalized; ?>" data-supervision="<?php echo $supervision_area; ?>">
                            <td class="col-number">
                                <strong><?php echo htmlspecialchars($row['appointment_number']); ?></strong>
                            </td>
                            <td class="col-employee">
                                <div class="employee-detail">
                                    <strong><?php echo htmlspecialchars($row['employee_name']); ?></strong>
                                    <span class="emp-code-detail"><?php echo htmlspecialchars($row['employee_code']); ?></span>
                                </div>
                            </td>
                            <td class="col-position">
                                <span class="position-badge-report"><?php echo htmlspecialchars($row['position_name']); ?></span>
                            </td>
                            <td class="col-date">
                                <i class="fas fa-calendar"></i> <?php echo date('d/m/Y', strtotime($row['effective_date'])); ?>
                            </td>
                            <td class="col-rejected-date">
                                <i class="fas fa-times"></i> <?php echo date('d/m/Y H:i', strtotime($row['approved_date'])); ?>
                            </td>
                            <td class="col-rejected-by">
                                <span class="rejector-badge"><?php echo htmlspecialchars($row['approved_by_name'] ?: 'N/A'); ?></span>
                            </td>
                            <td class="col-notes">
                                <span class="notes-badge" onclick="showRejectionNotes(<?php echo $row['id']; ?>)">
                                    <i class="fas fa-eye"></i> <span data-lang="view-notes">View Notes</span>
                                </span>
                            </td>
                            <td class="col-action">
                                <button class="btn-detail-small" onclick="showRejectionModal(<?php echo $row['id']; ?>, '<?php echo htmlspecialchars($row['appointment_number']); ?>', '<?php echo htmlspecialchars($row['employee_name']); ?>', '<?php echo htmlspecialchars($row['ktt_notes'] ?? ''); ?>')">
                                    <i class="fas fa-info-circle"></i> <span data-lang="details">Details</span>
                                </button>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
            <div class="table-info-report" id="rejectedTableInfo">
                <span data-lang="showing-all-data">Showing all data</span>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Certificate Expiration Report -->
    <?php if ($expiring_certs && $expiring_certs->num_rows > 0): ?>
    <div class="card-report" id="certificate-expiration">
        <div class="card-header-report">
            <div style="display: flex; align-items: center; gap: 10px; flex: 1;">
                <h3 style="margin: 0;"><i class="fas fa-exclamation-triangle"></i> <span data-lang="certificate-expiration-2-months">Certificate Expiration (=2 Months)</span></h3>
                <span class="badge-header warning"><?php echo $expiring_certs->num_rows; ?></span>
            </div>
            <button class="btn btn-export-small" onclick="exportExpiringCertsToExcel()">
                <i class="fas fa-file-excel"></i> <span data-lang="export-to-excel">Export to Excel</span>
            </button>
        </div>
        
        <div class="alert-warning-report">
            <i class="fas fa-info-circle"></i>
            <span data-lang="expiring-certs-renew-immediately">The following is a list of employees with certificates expiring within =2 months. Please renew certificates immediately.</span>
        </div>
        
        <div class="card-body-report">
            <div class="table-responsive">
                <table class="table-report table-expiring" id="expiringCertsTable">
                    <thead>
                        <tr>
                            <th class="col-employee" data-lang="employee">Employee</th>
                            <th class="col-cert-name" data-lang="certificate-name">Certificate Name</th>
                            <th class="col-cert-number" data-lang="certificate-number">Certificate Number</th>
                            <th class="col-expiry-date" data-lang="expiry-date">Expiry Date</th>
                            <th class="col-days-left" data-lang="days-left">Days Left</th>
                            <th class="col-status-expiry" data-lang="status">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $expiring_certs->data_seek(0);
                        while ($cert = $expiring_certs->fetch_assoc()): 
                            $days_left = intval($cert['days_until_expiry']);
                            
                            // Determine status class based on days left
                            $status_class = 'status-critical';
                            $status_text = 'Very Urgent';
                            $status_lang_key = 'very-urgent';
                            $status_icon = 'fa-exclamation-circle';
                            
                            if ($days_left > 30) {
                                $status_class = 'status-warning';
                                $status_text = 'Warning';
                                $status_lang_key = 'warning';
                                $status_icon = 'fa-exclamation-triangle';
                            } elseif ($days_left > 14) {
                                $status_class = 'status-urgent';
                                $status_text = 'Urgent';
                                $status_lang_key = 'urgent';
                                $status_icon = 'fa-clock';
                            }
                        ?>
                        <tr class="detail-row expiring-row">
                            <td class="col-employee">
                                <div class="employee-detail">
                                    <strong><?php echo htmlspecialchars($cert['full_name']); ?></strong>
                                    <span class="emp-code-detail"><?php echo htmlspecialchars($cert['employee_code']); ?></span>
                                </div>
                            </td>
                            <td class="col-cert-name">
                                <span class="cert-name-badge"><?php echo htmlspecialchars($cert['cert_name'] ?: 'N/A'); ?></span>
                            </td>
                            <td class="col-cert-number">
                                <?php echo htmlspecialchars($cert['cert_number']); ?>
                            </td>
                            <td class="col-expiry-date">
                                <i class="fas fa-calendar-times"></i> <?php echo date('d/m/Y', strtotime($cert['expiry_date'])); ?>
                            </td>
                            <td class="col-days-left">
                                <span class="days-badge days-<?php echo $days_left <= 14 ? 'critical' : ($days_left <= 30 ? 'urgent' : 'warning'); ?>">
                                    <?php echo $days_left; ?> <span data-lang="days">days</span>
                                </span>
                            </td>
                            <td class="col-status-expiry">
                                <span class="status-badge <?php echo $status_class; ?>" data-lang="<?php echo $status_lang_key; ?>">
                                    <i class="fas <?php echo $status_icon; ?>"></i> <?php echo $status_text; ?>
                                </span>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Request Rejection Details Modal -->
<div id="requestRejectionModal" class="modal">
    <div class="modal-content modal-rejection">
        <div class="modal-header modal-header-rejection">
            <h3><i class="fas fa-exclamation-circle"></i> <span data-lang="request-rejection-details">Request Rejection Details</span></h3>
            <span class="close" onclick="closeRequestRejectionModal()">&times;</span>
        </div>
        <div class="modal-body modal-body-rejection">
            <div class="rejection-info">
                <div class="info-row">
                    <label data-lang="employee-name-label">Employee Name:</label>
                    <span id="reqRejectionEmployeeName"></span>
                </div>
                <div class="info-row">
                    <label data-lang="employee-code-label">Employee Code:</label>
                    <span id="reqRejectionEmployeeCode"></span>
                </div>
                <div class="rejection-notes-section">
                    <h4><i class="fas fa-clipboard"></i> <span data-lang="rejection-notes">Rejection Notes</span></h4>
                    <div class="rejection-notes-content" id="reqRejectionNotesContent"></div>
                </div>
            </div>
        </div>
        <div class="modal-footer modal-footer-rejection">
            <button type="button" class="btn btn-secondary" onclick="closeRequestRejectionModal()"><span data-lang="close">Close</span></button>
        </div>
    </div>
</div>

<!-- Rejection Details Modal -->
<div id="rejectionModal" class="modal">
    <div class="modal-content modal-rejection">
        <div class="modal-header modal-header-rejection">
            <h3><i class="fas fa-exclamation-circle"></i> <span data-lang="assign-letter-rejection-details">Assign Letter Rejection Details</span></h3>
            <span class="close" onclick="closeRejectionModal()">&times;</span>
        </div>
        <div class="modal-body modal-body-rejection">
            <div class="rejection-info">
                <div class="info-row">
                    <label data-lang="assign-letter-no-label">Assign Letter No.:</label>
                    <span id="rejectionAppointmentNumber"></span>
                </div>
                <div class="info-row">
                    <label data-lang="employee-name-label">Employee Name:</label>
                    <span id="rejectionEmployeeName"></span>
                </div>
                <div class="rejection-notes-section">
                    <h4><i class="fas fa-clipboard"></i> <span data-lang="rejection-notes-from-ktt">KTT Rejection Notes</span></h4>
                    <div class="rejection-notes-content" id="rejectionNotesContent">
                        <!-- Notes will be filled via JavaScript -->
                    </div>
                </div>
            </div>
        </div>
        <div class="modal-footer modal-footer-rejection">
            <button type="button" class="btn btn-secondary" onclick="closeRejectionModal()"><span data-lang="close">Close</span></button>
        </div>
    </div>
</div>

<script>
function filterTableByFilters(tableId) {
    const table = document.getElementById(tableId);
    const rows = table.getElementsByTagName('tbody')[0].getElementsByTagName('tr');
    let visibleCount = 0;
    
    // Get filter values
    const scopeFilter = document.getElementById('scopeFilter' + (tableId === 'approvedTable' ? 'Approved' : 'Rejected')).value;
    const supervisionFilter = document.getElementById('supervisionFilter' + (tableId === 'approvedTable' ? 'Approved' : 'Rejected')).value;
    
    // Apply filters
    for (let row of rows) {
        const rowScope = row.getAttribute('data-scope');
        const rowSupervision = row.getAttribute('data-supervision');
        
        let showRow = true;
        
        // Filter by scope
        if (scopeFilter && rowScope !== scopeFilter) {
            showRow = false;
        }
        
        // Filter by supervision area
        if (supervisionFilter && rowSupervision !== supervisionFilter) {
            showRow = false;
        }
        
        if (showRow) {
            row.style.display = '';
            visibleCount++;
        } else {
            row.style.display = 'none';
        }
    }
    
    // Update info message
    const showingText = window.getLanguageText('');
    const dataText = window.getLanguageText('');
    let infoMessage = showingText + ' ' + visibleCount + ' ' + dataText;
    if (scopeFilter || supervisionFilter) {
        let filters = [];
        if (scopeFilter) {
            const scopeLabel = scopeFilter === 'MSM' ? 'PT MSM' : 'PT TTN';
            const scopeLabelText = window.getLanguageText('');
            filters.push(scopeLabelText + ' ' + scopeLabel);
        }
        if (supervisionFilter) {
            const supervisionLabelText = window.getLanguageText('');
            filters.push(supervisionLabelText + ' ' + supervisionFilter);
        }
        const activeFilterText = window.getLanguageText('');
        infoMessage += ' - ' + activeFilterText + ' ' + filters.join(', ');
    } else {
        const showingAllText = window.getLanguageText('');
        infoMessage = showingAllText;
    }
    updateTableInfo(tableId, infoMessage);
}

function filterTableByStatus(tableId) {
    const table = document.getElementById(tableId);
    const rows = table.getElementsByTagName('tbody')[0].getElementsByTagName('tr');
    let visibleCount = 0;
    
    // Get filter value
    const statusFilter = document.getElementById('statusFilterRequests').value;
    
    // Apply filter
    for (let row of rows) {
        const rowStatus = row.getAttribute('data-status');
        
        let showRow = true;
        
        // Filter by status
        if (statusFilter && rowStatus !== statusFilter) {
            showRow = false;
        }
        
        if (showRow) {
            row.style.display = '';
            visibleCount++;
        } else {
            row.style.display = 'none';
        }
    }
    
    // Update info message
    const showingText = window.getLanguageText('showing');
    const dataText = window.getLanguageText('requests');
    let infoMessage = showingText + ' ' + visibleCount + ' ' + dataText;
    if (statusFilter) {
        const statusLabel = statusFilter.charAt(0).toUpperCase() + statusFilter.slice(1);
        const activeFilterText = window.getLanguageText('active-filter');
        const statusLabelText = window.getLanguageText('status-label');
        infoMessage += ' - ' + activeFilterText + ' ' + statusLabelText + ' ' + statusLabel;
    } else {
        const showingAllText = window.getLanguageText('showing-all');
        infoMessage = showingAllText;
    }
}

function filterTableByScope(tableId, scopeFilter) {
    const table = document.getElementById(tableId);
    const rows = table.getElementsByTagName('tbody')[0].getElementsByTagName('tr');
    let visibleCount = 0;
    
    // Apply filter
    for (let row of rows) {
        const rowScope = row.getAttribute('data-scope');
        
        let showRow = true;
        
        // Filter by scope
        if (scopeFilter && rowScope !== scopeFilter) {
            showRow = false;
        }
        
        if (showRow) {
            row.style.display = '';
            visibleCount++;
        } else {
            row.style.display = 'none';
        }
    }
    
    // Update info message
    const showingText = window.getLanguageText('');
    const dataText = window.getLanguageText('');
    let infoMessage = showingText + ' ' + visibleCount + ' ' + dataText;
    if (scopeFilter) {
        const scopeLabel = scopeFilter === 'MSM' ? 'PT MSM' : 'PT TTN';
        const activeFilterText = window.getLanguageText('');
        const scopeLabelText = window.getLanguageText('');
        infoMessage += ' - ' + activeFilterText + ' ' + scopeLabelText + ' ' + scopeLabel;
    } else {
        const showingAllText = window.getLanguageText('');
        infoMessage = showingAllText;
    }
    updateTableInfo(tableId, infoMessage);
}

function filterRequestTable() {
    const table = document.getElementById('requestsTable');
    const rows = table.getElementsByTagName('tbody')[0].getElementsByTagName('tr');
    let visibleCount = 0;
    
    // Get filter values
    const statusFilter = document.getElementById('statusFilterRequests').value;
    
    // Apply filters
    for (let row of rows) {
        const rowStatus = row.getAttribute('data-status');
        
        let showRow = true;
        
        // Filter by status
        if (statusFilter && rowStatus !== statusFilter) {
            showRow = false;
        }
        
        if (showRow) {
            row.style.display = '';
            visibleCount++;
        } else {
            row.style.display = 'none';
        }
    }
    
    // Update info message
    const showingText = window.getLanguageText('') || 'Showing';
    const dataText = window.getLanguageText('') || 'data';
    let infoMessage = showingText + ' ' + visibleCount + ' ' + dataText;
    
    if (statusFilter) {
        let statusLabel = '';
        if (statusFilter === 'verified') {
            statusLabel = 'Accepted';
        } else if (statusFilter === 'rejected') {
            statusLabel = 'Rejected';
        } else if (statusFilter === 'pending') {
            statusLabel = 'Pending';
        }
        const activeFilterText = window.getLanguageText('') || 'Active filters';
        const statusLabelText = window.getLanguageText('') || 'Status';
        infoMessage += ' - ' + activeFilterText + ' ' + statusLabelText + ': ' + statusLabel;
    } else {
        const showingAllText = window.getLanguageText('') || 'Showing all data';
        infoMessage = showingAllText;
    }
    
    const infoElement = document.getElementById('requestsTableInfo');
    if (infoElement) {
        infoElement.textContent = infoMessage;
    }
}

function updateTableInfo(tableId, message) {
    const infoElement = document.getElementById(tableId.replace('Table', 'TableInfo'));
    if (infoElement) {
        infoElement.textContent = message;
    }
}

function showRejectionModal(id, appointmentNumber, employeeName, kttNotes) {
    document.getElementById('rejectionAppointmentNumber').textContent = appointmentNumber;
    document.getElementById('rejectionEmployeeName').textContent = employeeName;
    
    // Parse and display KTT notes
    const notesContent = document.getElementById('rejectionNotesContent');
    notesContent.innerHTML = '';
    
    if (kttNotes && kttNotes.trim() !== '') {
        const notes = kttNotes.split(' | ');
        notes.forEach((note, index) => {
            const noteDiv = document.createElement('div');
            noteDiv.className = 'rejection-note-item';
            noteDiv.innerHTML = `
                <div class="rejection-note-header">
                    <span class="note-number">#${index + 1}</span>
                    <i class="fas fa-times-circle"></i>
                </div>
                <div class="rejection-note-text">
                    ${escapeHtml(note)}
                </div>
            `;
            notesContent.appendChild(noteDiv);
        });
    } else {
        const emptyNotesText = window.getLanguageText('');
        notesContent.innerHTML = '<p class="text-muted">' + emptyNotesText + '</p>';
    }
    
    document.getElementById('rejectionModal').style.display = 'block';
}

function closeRejectionModal() {
    document.getElementById('rejectionModal').style.display = 'none';
}

function showRequestRejectionModal(employeeName, employeeCode, notes) {
    document.getElementById('reqRejectionEmployeeName').textContent = employeeName;
    document.getElementById('reqRejectionEmployeeCode').textContent = employeeCode;
    document.getElementById('reqRejectionNotesContent').innerHTML = notes ? '<p class="rejection-note-text">' + escapeHtml(notes).replace(/\n/g, '<br>') + '</p>' : '<p class="text-muted">N/A</p>';
    document.getElementById('requestRejectionModal').style.display = 'block';
}

function closeRequestRejectionModal() {
    document.getElementById('requestRejectionModal').style.display = 'none';
}

function escapeHtml(unsafe) {
    return unsafe
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
}

function exportRequestsToExcel(tableId, filename) {
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
    const statusFilter = document.getElementById('statusFilterRequests').value;
    
    // Determine status label
    let statusFilterLabel = '';
    if (statusFilter === 'verified') {
        statusFilterLabel = 'Accepted';
    } else if (statusFilter === 'rejected') {
        statusFilterLabel = 'Rejected';
    } else if (statusFilter === 'pending') {
        statusFilterLabel = 'Pending';
    }
    
    const companyName = '<?php echo htmlspecialchars($company_name); ?>';
    
    // Build Excel HTML
    let excelContent = '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">';
    excelContent += '<head><meta charset="UTF-8"><!--[if gte mso 9]><xml><x:ExcelWorkbook><x:ExcelWorksheets><x:ExcelWorksheet>';
    excelContent += '<x:Name>Employee Requests</x:Name><x:WorksheetOptions><x:DisplayGridlines/></x:WorksheetOptions></x:ExcelWorksheet>';
    excelContent += '</x:ExcelWorksheets></x:ExcelWorkbook></xml><![endif]--></head><body>';

    // Add title and filters info
    excelContent += '<table><tr><td colspan="6"><h2>Employee Request Report</h2></td></tr>';
    excelContent += '<tr><td colspan="6">Company: ' + companyName + '</td></tr>';
    excelContent += '<tr><td colspan="6">Export Date: ' + new Date().toLocaleDateString('en-US') + ' ' + new Date().toLocaleTimeString('en-US') + '</td></tr>';
    
    if (statusFilterLabel) {
        excelContent += '<tr><td colspan="6">Filter Status: ' + statusFilterLabel + '</td></tr>';
    }
    
    excelContent += '<tr><td colspan="6">Total Data: ' + filteredRows.length + '</td></tr>';
    excelContent += '<tr><td colspan="6">&nbsp;</td></tr>';
    
    // Add table headers
    excelContent += '<tr style="background-color: #37474F; color: white; font-weight: bold;">';
    excelContent += '<td>Employee Name</td>';
    excelContent += '<td>Employee Code</td>';
    excelContent += '<td>Tanggal Pengajuan</td>';
    excelContent += '<td>Status</td>';
    excelContent += '<td>Verified Date</td>';
    excelContent += '<td>Verified By</td>';
    excelContent += '</tr>';
    
    // Add table data
    for (let row of filteredRows) {
        const cells = row.getElementsByTagName('td');
        excelContent += '<tr>';
        
        // Employee Name
        excelContent += '<td>' + cells[0].textContent.trim() + '</td>';
        
        // Employee Code
        excelContent += '<td>' + cells[1].textContent.trim() + '</td>';
        
        // Request Date
        excelContent += '<td>' + cells[2].textContent.trim() + '</td>';
        
        // Status
        const statusBadge = cells[3].querySelector('.status-badge span[data-lang]');
        let statusText = cells[3].textContent.trim();
        if (statusBadge) {
            statusText = statusBadge.textContent.trim();
        }
        excelContent += '<td>' + statusText + '</td>';
        
        // Verified Date
        excelContent += '<td>' + cells[4].textContent.trim() + '</td>';
        
        // Verified By
        excelContent += '<td>' + cells[5].textContent.trim() + '</td>';
        
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
    let finalFilename = filename + '_' + companyName.replace(/[^a-zA-Z0-9]/g, '_');
    if (statusFilterLabel) {
        finalFilename += '_' + statusFilterLabel;
    }
    finalFilename += '_' + new Date().toISOString().slice(0, 10) + '.xls';
    
    a.download = finalFilename;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    window.URL.revokeObjectURL(url);
}

function exportApprovedByCompany() {
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
    
    const companyName = '<?php echo htmlspecialchars($company_name); ?>';
    
    // Generate HTML for PDF
    let htmlContent = `
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Assign Letters Accept Report - ${companyName}</title>
            <style>
                body {
                    font-family: Arial, sans-serif;
                    margin: 20px;
                    color: #333;
                }
                h1 {
                    text-align: center;
                    color: #37474F;
                    margin-bottom: 5px;
                }
                .company-name {
                    text-align: center;
                    font-size: 16px;
                    color: #616161;
                    font-weight: 600;
                    margin-bottom: 5px;
                }
                .header-info {
                    text-align: center;
                    margin-bottom: 20px;
                    font-size: 12px;
                    color: #666;
                }
                table {
                    width: 100%;
                    border-collapse: collapse;
                    margin-bottom: 20px;
                }
                thead {
                    background-color: #f0f0f0;
                }
                th {
                    padding: 10px;
                    text-align: left;
                    font-weight: 600;
                    border: 1px solid #ddd;
                    font-size: 12px;
                }
                td {
                    padding: 8px 10px;
                    border: 1px solid #ddd;
                    font-size: 11px;
                }
                tr:nth-child(even) {
                    background-color: #f9f9f9;
                }
                .summary-box {
                    background-color: #E8F5E9;
                    padding: 12px;
                    margin-bottom: 15px;
                    border-radius: 4px;
                    border-left: 4px solid #2E7D32;
                    font-weight: 600;
                    color: #2E7D32;
                }
                .footer {
                    text-align: center;
                    font-size: 10px;
                    color: #999;
                    margin-top: 20px;
                    padding-top: 10px;
                    border-top: 1px solid #ddd;
                }
            </style>
        </head>
        <body>
            <h1>?? Assign Letters Accept Report</h1>
            <div class="company-name">${companyName}</div>
            <div class="header-info">
                Printed on: ${new Date().toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' })}
                <br>Time: ${new Date().toLocaleTimeString('en-US')}
            </div>
            <div class="summary-box">
                ? Total Surat Accept: ${filteredRows.length}
            </div>
            <table>
                <thead>
                    <tr>
                        <th style="width: 15%;">Letter No.</th>
                        <th style="width: 25%;">Employee Name</th>
                        <th style="width: 20%;">Position</th>
                        <th style="width: 13%;">Effective Date</th>
                        <th style="width: 27%;">Approved By</th>
                    </tr>
                </thead>
                <tbody>
    `;
    
    for (let row of filteredRows) {
        const cells = row.getElementsByTagName('td');
        // Get approval info from the last cell
        const approvalCell = cells[4];
        let approvalText = 'N/A';
        if (approvalCell) {
            const approvalItems = approvalCell.querySelectorAll('.approval-item');
            if (approvalItems.length > 0) {
                const approvalTexts = [];
                approvalItems.forEach(item => {
                    const name = item.querySelector('.approver-name')?.textContent.trim() || '';
                    const datetime = item.querySelector('.approval-datetime')?.textContent.trim() || '';
                    approvalTexts.push(`${name}<br>${datetime}`);
                });
                approvalText = approvalTexts.join('<br>---<br>');
            } else {
                approvalText = approvalCell.textContent.trim();
            }
        }
        
        htmlContent += `
            <tr>
                <td>${cells[0].textContent.trim()}</td>
                <td>${cells[1].textContent.trim().split('\\n')[0]}</td>
                <td>${cells[2].textContent.trim()}</td>
                <td>${cells[3].textContent.trim()}</td>
                <td>${approvalText}</td>
            </tr>
        `;
    }
    
    htmlContent += `
                </tbody>
            </table>
            <div class="footer">
                This document was printed from the Expertise Appointment Letter System
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
    const companyName = '<?php echo htmlspecialchars($company_name); ?>';
    
    // Build Excel HTML
    let excelContent = '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">';
    excelContent += '<head><meta charset="UTF-8"><!--[if gte mso 9]><xml><x:ExcelWorkbook><x:ExcelWorksheets><x:ExcelWorksheet>';
    excelContent += '<x:Name>Report</x:Name><x:WorksheetOptions><x:DisplayGridlines/></x:WorksheetOptions></x:ExcelWorksheet>';
    excelContent += '</x:ExcelWorksheets></x:ExcelWorkbook></xml><![endif]--></head><body>';

    // Add title and filters info
    excelContent += '<table><tr><td colspan="7"><h2>Appointment Letter Report ' + (isApproved ? 'Accepted' : 'Rejected') + '</h2></td></tr>';
    excelContent += '<tr><td colspan="7">Company: ' + companyName + '</td></tr>';
    excelContent += '<tr><td colspan="7">Export Date: ' + new Date().toLocaleDateString('en-US') + ' ' + new Date().toLocaleTimeString('en-US') + '</td></tr>';
    
    if (scopeFilter) {
        const scopeLabel = scopeFilter === 'MSM' ? 'PT MSM' : 'PT TTN';
        excelContent += '<tr><td colspan="7">Filter Ruang Lingkup: ' + scopeLabel + '</td></tr>';
    }
    
    excelContent += '<tr><td colspan="7">Total Data: ' + filteredRows.length + '</td></tr>';
    excelContent += '<tr><td colspan="7">&nbsp;</td></tr>';
    
    // Add table headers
    excelContent += '<tr style="background-color: #37474F; color: white; font-weight: bold;">';
    excelContent += '<td>Letter No.</td>';
    excelContent += '<td>Employee Name</td>';
    excelContent += '<td>Employee Code</td>';
    excelContent += '<td>Position</td>';
    excelContent += '<td>Effective Date</td>';
    if (isApproved) {
        excelContent += '<td>Approved By</td>';
    } else {
        excelContent += '<td>Rejected</td>';
        excelContent += '<td>Rejected By</td>';
        excelContent += '<td>Rejection Notes</td>';
    }
    excelContent += '</tr>';
    
    // Add table data
    for (let row of filteredRows) {
        const cells = row.getElementsByTagName('td');
        excelContent += '<tr>';
        
        // Letter No.
        excelContent += '<td>' + cells[0].textContent.trim() + '</td>';
        
        // Employee Name (fetching only name, without code)
        const employeeText = cells[1].textContent.trim().split('\n');
        excelContent += '<td>' + employeeText[0].trim() + '</td>';
        
        // Employee Code
        if (employeeText.length > 1) {
            excelContent += '<td>' + employeeText[1].trim() + '</td>';
        } else {
            excelContent += '<td></td>';
        }
        
        // Jabatan
        excelContent += '<td>' + cells[2].textContent.trim() + '</td>';
        
        // Tanggal Efektif
        excelContent += '<td>' + cells[3].textContent.trim() + '</td>';
        
        if (isApproved) {
            // Approved By - get all approval info
            const approvalCell = cells[4];
            let approvalText = 'N/A';
            if (approvalCell) {
                const approvalItems = approvalCell.querySelectorAll('.approval-item');
                if (approvalItems.length > 0) {
                    const approvalTexts = [];
                    approvalItems.forEach(item => {
                        const name = item.querySelector('.approver-name')?.textContent.trim() || '';
                        const datetime = item.querySelector('.approval-datetime')?.textContent.trim() || '';
                        approvalTexts.push(`${name} | ${datetime}`);
                    });
                    approvalText = approvalTexts.join(' || ');
                } else {
                    approvalText = approvalCell.textContent.trim();
                }
            }
            excelContent += '<td>' + approvalText + '</td>';
        } else {
            // Rejected Date
            excelContent += '<td>' + cells[4].textContent.trim() + '</td>';
            // Rejected By
            excelContent += '<td>' + cells[5].textContent.trim() + '</td>';
            // Notes
            if (cells.length > 6) {
                excelContent += '<td>Lihat Detail</td>';
            }
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
    let finalFilename = filename + '_' + companyName.replace(/[^a-zA-Z0-9]/g, '_');
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

function exportExpiringCertsToExcel() {
    const table = document.getElementById('expiringCertsTable');
    if (!table) {
        const tableNotFound = window.getLanguageText('');
        alert(tableNotFound);
        return;
    }
    
    const rows = table.getElementsByTagName('tbody')[0].getElementsByTagName('tr');
    let filteredRows = [];
    
    // Get all visible rows
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
    
    const companyName = '<?php echo htmlspecialchars($company_name); ?>';
    
    // Build Excel HTML
    let excelContent = '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">';
    excelContent += '<head><meta charset="UTF-8"><!--[if gte mso 9]><xml><x:ExcelWorkbook><x:ExcelWorksheets><x:ExcelWorksheet>';
    excelContent += '<x:Name>Certificate Expiration</x:Name><x:WorksheetOptions><x:DisplayGridlines/></x:WorksheetOptions></x:ExcelWorksheet>';
    excelContent += '</x:ExcelWorksheets></x:ExcelWorkbook></xml><![endif]--></head><body>';

    // Add title and info
    excelContent += '<table><tr><td colspan="7"><h2>Certificate Expiration Report (=2 Months)</h2></td></tr>';
    excelContent += '<tr><td colspan="7">Company: ' + companyName + '</td></tr>';
    excelContent += '<tr><td colspan="7">Export Date: ' + new Date().toLocaleDateString('en-US') + ' ' + new Date().toLocaleTimeString('en-US') + '</td></tr>';
    excelContent += '<tr><td colspan="7">Total Expiring Certificates: ' + filteredRows.length + '</td></tr>';
    excelContent += '<tr><td colspan="7">&nbsp;</td></tr>';
    
    // Add warning message
    excelContent += '<tr><td colspan="7" style="background-color: #fef3c7; color: #92400e; padding: 10px;">?? ATTENTION: Please renew these certificates immediately to avoid expiration.</td></tr>';
    excelContent += '<tr><td colspan="7">&nbsp;</td></tr>';
    
    // Add table headers
    excelContent += '<tr style="background-color: #f59e0b; color: white; font-weight: bold;">';
    excelContent += '<td>Employee Name</td>';
    excelContent += '<td>Employee Code</td>';
    excelContent += '<td>Certificate Name</td>';
    excelContent += '<td>Certificate Number</td>';
    excelContent += '<td>Expiry Date</td>';
    excelContent += '<td>Days Left</td>';
    excelContent += '<td>Status</td>';
    excelContent += '</tr>';
    
    // Add table data
    for (let row of filteredRows) {
        const cells = row.getElementsByTagName('td');
        excelContent += '<tr>';
        
        // Employee Name
        const employeeText = cells[0].textContent.trim().split('\n');
        excelContent += '<td>' + employeeText[0].trim() + '</td>';
        
        // Employee Code
        if (employeeText.length > 1) {
            excelContent += '<td>' + employeeText[1].trim() + '</td>';
        } else {
            excelContent += '<td></td>';
        }
        
        // Certificate Name
        excelContent += '<td>' + cells[1].textContent.trim() + '</td>';
        
        // Certificate Number
        excelContent += '<td>' + cells[2].textContent.trim() + '</td>';
        
        // Expiry Date
        excelContent += '<td>' + cells[3].textContent.trim() + '</td>';
        
        // Days Left
        const daysLeftText = cells[4].textContent.trim();
        excelContent += '<td>' + daysLeftText + '</td>';
        
        // Status
        const statusText = cells[5].textContent.trim();
        let statusColor = '';
        if (statusText.includes('Very Urgent') || statusText.includes('Critical')) {
            statusColor = 'background-color: #fee2e2; color: #dc2626;';
        } else if (statusText.includes('Urgent')) {
            statusColor = 'background-color: #fed7aa; color: #ea580c;';
        } else {
            statusColor = 'background-color: #fef3c7; color: #d97706;';
        }
        excelContent += '<td style="' + statusColor + '">' + statusText + '</td>';
        
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
    
    // Create filename
    let finalFilename = 'Certificate_Expiration_' + companyName.replace(/[^a-zA-Z0-9]/g, '_');
    finalFilename += '_' + new Date().toISOString().slice(0, 10) + '.xls';
    
    a.download = finalFilename;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    window.URL.revokeObjectURL(url);
}
</script>

<style>
.reports-container {
    padding: 0;
    max-width: 100%;
    margin: 0;
}

/* Page Header */
.page-header-reports {
    background: #ffffff;
    color: #1f2937;
    padding: 30px;
    border-radius: 18px;
    margin-bottom: 22px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    border: 1px solid #e5e7eb;
    box-shadow: 0 12px 32px rgba(15, 23, 42, 0.08);
}

.header-left h2 {
    margin: 0 0 8px 0;
    font-size: 26px;
    font-weight: 700;
    display: flex;
    align-items: center;
    gap: 10px;
    color: #111827;
}

.header-left p {
    margin: 0;
    color: #6b7280;
    font-size: 14px;
}

.header-date {
    background: #f8fafc;
    padding: 12px 16px;
    border-radius: 12px;
    font-size: 13px;
    display: flex;
    align-items: center;
    gap: 8px;
    color: #475569;
    border: 1px solid #e5e7eb;
}

/* Stats Grid */
.stats-grid-reports {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 18px;
    margin-bottom: 20px;
}

.stat-card-report {
    background: white;
    border-radius: 16px;
    padding: 20px 22px;
    display: flex;
    align-items: center;
    gap: 16px;
    box-shadow: 0 10px 28px rgba(15, 23, 42, 0.06);
    border: 1px solid #e5e7eb;
    transition: all 0.3s ease;
    min-height: 104px;
}

.stat-card-report:hover {
    transform: translateY(-2px);
    box-shadow: 0 14px 32px rgba(15, 23, 42, 0.1);
}

.stat-total { border-left: 4px solid #f59e0b; }
.stat-approved { border-left: 4px solid #10b981; }
.stat-rejected { border-left: 4px solid #ef4444; }
.stat-pending { border-left: 4px solid #d97706; }

.stat-icon-report {
    font-size: 28px;
    width: 58px;
    height: 58px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 14px;
    color: white;
}

.stat-total .stat-icon-report { background: linear-gradient(135deg, #f97316, #d97706); }
.stat-approved .stat-icon-report { background: linear-gradient(135deg, #10b981, #059669); }
.stat-rejected .stat-icon-report { background: linear-gradient(135deg, #dc2626, #b91c1c); }
.stat-pending .stat-icon-report { background: linear-gradient(135deg, #f59e0b, #d97706); }

.request-stats-grid {
    margin-top: -2px;
}

.stat-content-report h3 {
    font-size: 28px;
    font-weight: 700;
    color: #111827;
    margin: 0;
}

.stat-content-report p {
    color: #6b7280;
    font-size: 13px;
    margin: 5px 0 0 0;
}

/* Cards */
.card-report {
    background: white;
    border-radius: 18px;
    box-shadow: 0 10px 28px rgba(15, 23, 42, 0.06);
    margin-bottom: 20px;
    border: 1px solid #e5e7eb;
}

.card-header-report {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 18px 24px;
    border-bottom: 1px solid #eef2f7;
}

.card-header-report h3 {
    margin: 0;
    font-size: 17px;
    color: #111827;
    display: flex;
    align-items: center;
    gap: 10px;
}

.card-header-report i {
    color: #f59e0b;
}

.badge-header {
    background: #111827;
    color: white;
    padding: 6px 12px;
    border-radius: 999px;
    font-size: 12px;
    font-weight: 600;
}

.badge-header.rejected {
    background: #ef4444;
}

.badge-header.warning {
    background: #f59e0b;
}

.card-body-report {
    padding: 0;
}

/* Table */
.table-report {
    width: 100%;
    border-collapse: collapse;
    margin: 0;
}

.table-report thead th {
    background: #f9fafb;
    color: #6b7280;
    font-weight: 600;
    font-size: 12px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    border: none;
    padding: 14px 16px;
    text-align: left;
}

.report-row,
.detail-row {
    border-bottom: 1px solid #f3f4f6;
    transition: background-color 0.2s ease;
}

.report-row:hover,
.detail-row:hover {
    background-color: #fafafa;
}

.table-report td {
    padding: 14px 16px;
    vertical-align: middle;
    font-size: 13px;
}

.table-compact-request .col-number,
.table-compact-request .col-position,
.table-compact-request .col-rejected-by {
    display: none;
}

.table-compact-request .col-employee {
    width: 32%;
}

.table-compact-request .col-date {
    width: 20%;
}

.table-compact-request .col-approved-info,
.table-compact-request .col-notes,
.table-compact-request .col-rejected-date,
.table-compact-request .col-code,
.table-compact-request .col-status,
.table-compact-request .col-verified-date,
.table-compact-request .col-verified-by {
    width: auto;
}

.table-compact-request td {
    padding: 11px 12px;
}

.badge-count {
    display: inline-block;
    padding: 8px 14px;
    border-radius: 999px;
    font-size: 13px;
    font-weight: 600;
}

.approved-badge {
    background: #E8F5E9;
    color: #2E7D32;
}

.rejected-badge {
    background: #fee2e2;
    color: #ef4444;
}

.company-tag {
    background: #f9fafb;
    color: #6b7280;
    padding: 6px 12px;
    border-radius: 999px;
    font-size: 12px;
    display: inline-block;
}

.employee-detail {
    display: flex;
    flex-direction: column;
    gap: 3px;
}

.emp-code-detail {
    font-size: 11px;
    color: #999;
}

.position-badge-report {
    background: #f9fafb;
    color: #374151;
    padding: 6px 12px;
    border-radius: 999px;
    font-size: 12px;
    display: inline-block;
}

.approver-badge {
    background: #ecfdf5;
    color: #047857;
    padding: 6px 12px;
    border-radius: 999px;
    font-size: 12px;
    display: inline-block;
}

.rejector-badge {
    background: #fef2f2;
    color: #dc2626;
    padding: 6px 12px;
    border-radius: 999px;
    font-size: 12px;
    display: inline-block;
}

.rejected-row {
    border-left: 3px solid #ef4444;
}

/* Empty State */
.empty-state-report {
    text-align: center;
    padding: 60px 20px;
    color: #999;
}

.empty-state-report i {
    font-size: 48px;
    margin-bottom: 15px;
    opacity: 0.5;
}

.empty-state-report p {
    margin: 0;
    font-size: 16px;
}

/* Filter Section */
.filter-section-report {
    padding: 18px 20px;
    background: #ffffff;
    border-bottom: 1px solid #eef2f7;
    display: flex;
    flex-wrap: wrap;
    gap: 15px;
    align-items: center;
}

.filter-group-report {
    display: flex;
    align-items: center;
    gap: 12px;
}

.filter-action-group {
    margin-left: auto;
}

.filter-group-report label {
    font-weight: 600;
    color: #374151;
    white-space: nowrap;
    display: flex;
    align-items: center;
    gap: 6px;
    margin: 0;
    font-size: 13px;
}

.filter-select-report {
    padding: 10px 12px;
    border: 1px solid #d1d5db;
    border-radius: 10px;
    font-size: 13px;
    cursor: pointer;
    transition: border-color 0.3s ease;
    min-width: 200px;
    background: #ffffff;
}

.filter-select-report:hover,
.filter-select-report:focus {
    border-color: #f59e0b;
    outline: none;
}

.table-info-report {
    padding: 12px 20px;
    background: #f9fafb;
    color: #6b7280;
    border-top: 1px solid #eef2f7;
    font-size: 12px;
    font-weight: 500;
}

/* Rejection Modal */
.modal-rejection {
    max-width: 650px;
}

.modal-header-rejection {
    background: linear-gradient(135deg, #ef4444, #dc2626);
    color: white;
    padding: 20px;
    border-radius: 14px 14px 0 0;
}

.modal-header-rejection h3 {
    margin: 0;
    font-size: 18px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.modal-header-rejection .close {
    color: white;
    opacity: 0.8;
}

.modal-header-rejection .close:hover {
    opacity: 1;
}

.modal-body-rejection {
    padding: 25px;
}

.rejection-info {
    margin-bottom: 20px;
}

.info-row {
    display: flex;
    padding: 12px 0;
    border-bottom: 1px solid #e9ecef;
}

.info-row label {
    font-weight: 600;
    color: #333;
    width: 150px;
    flex-shrink: 0;
}

.info-row span {
    color: #666;
    flex: 1;
}

.rejection-notes-section {
    margin-top: 25px;
}

.rejection-notes-section h4 {
    margin: 0 0 15px 0;
    font-size: 15px;
    color: #333;
    display: flex;
    align-items: center;
    gap: 8px;
}

.rejection-notes-section i {
    color: #ef4444;
}

.rejection-notes-content {
    display: flex;
    flex-direction: column;
    gap: 12px;
    max-height: 400px;
    overflow-y: auto;
}

.rejection-note-item {
    background: #fee2e2;
    border-left: 4px solid #ef4444;
    padding: 12px;
    border-radius: 6px;
}

.rejection-note-header {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 8px;
}

.note-number {
    background: #ef4444;
    color: white;
    padding: 2px 8px;
    border-radius: 4px;
    font-size: 11px;
    font-weight: 600;
}

.rejection-note-header i {
    color: #ef4444;
    font-size: 14px;
}

.rejection-note-text {
    color: #7f1d1d;
    font-size: 13px;
    line-height: 1.6;
    word-break: break-word;
}

.modal-footer-rejection {
    display: flex;
    gap: 10px;
    justify-content: flex-end;
    padding: 15px 25px;
    border-top: 1px solid #e9ecef;
}

/* Notes Badge */
.notes-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 12px;
    background: #fef3c7;
    color: #b45309;
    border-radius: 6px;
    font-size: 12px;
    cursor: pointer;
    transition: all 0.3s ease;
    font-weight: 500;
}

.notes-badge:hover {
    background: #fcd34d;
    transform: translateY(-1px);
}

/* Button Detail Small */
.btn-detail-small {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 10px;
    background: #ECEFF1;
    color: #37474F;
    border: none;
    border-radius: 6px;
    font-size: 12px;
    cursor: pointer;
    transition: all 0.3s ease;
    font-weight: 500;
}

.btn-detail-small:hover {
    background: #37474F;
    color: white;
    transform: translateY(-1px);
}

/* Export Button Styles */
.btn-export-small {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 16px;
    background: linear-gradient(135deg, #2E7D32, #1B5E20);
    color: white;
    border: none;
    border-radius: 6px;
    font-size: 13px;
    cursor: pointer;
    transition: all 0.3s ease;
    font-weight: 600;
    box-shadow: 0 2px 8px rgba(16, 185, 129, 0.3);
}

.btn-export-small:hover {
    background: linear-gradient(135deg, #1B5E20, #047857);
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(16, 185, 129, 0.4);
}

.btn-export-small i {
    font-size: 14px;
}

/* Certificate Expiration Styles */
.alert-warning-report {
    background: #fffbeb;
    border: 1px solid #fde68a;
    border-left: 4px solid #f59e0b;
    padding: 15px 20px;
    border-radius: 12px;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 12px;
    color: #92400e;
}

.alert-warning-report i {
    font-size: 20px;
    color: #f59e0b;
}

.alert-warning-report span {
    font-size: 14px;
    line-height: 1.5;
}

.col-cert-name { width: 20%; }
.col-cert-number { width: 15%; }
.col-expiry-date { width: 13%; }
.col-days-left { width: 12%; }
.col-status-expiry { width: 15%; }

.employee-detail {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.employee-detail strong {
    color: #333;
    font-size: 13px;
}

.emp-code-detail {
    color: #999;
    font-size: 11px;
    font-weight: 500;
}

.cert-name-badge {
    background: #f9fafb;
    color: #374151;
    padding: 6px 12px;
    border-radius: 999px;
    font-size: 12px;
    font-weight: 600;
    display: inline-block;
}

.days-badge {
    display: inline-block;
    padding: 6px 12px;
    border-radius: 999px;
    font-size: 12px;
    font-weight: 700;
    white-space: nowrap;
}

.days-critical {
    background: #fee2e2;
    color: #dc2626;
}

.days-urgent {
    background: #fed7aa;
    color: #ea580c;
}

.days-warning {
    background: #fef3c7;
    color: #d97706;
}

.status-badge {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 6px 12px;
    border-radius: 999px;
    font-size: 12px;
    font-weight: 600;
    white-space: nowrap;
}

.status-critical {
    background: #fee2e2;
    color: #dc2626;
}

.status-urgent {
    background: #fed7aa;
    color: #ea580c;
}

.status-warning {
    background: #fef3c7;
    color: #d97706;
}

/* Request Table Styles */
.table-requests {
    width: 100%;
}

.col-employee {
    width: 25%;
}

.col-code {
    width: 12%;
}

.col-request-date {
    width: 13%;
}

.col-status {
    width: 15%;
}

.col-verified-date {
    width: 13%;
}

.col-verified-by {
    width: 22%;
}

.status-accepted {
    background: #ecfdf5;
    color: #047857;
    padding: 6px 12px;
    border-radius: 999px;
    font-size: 12px;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    white-space: nowrap;
}

.status-rejected-badge {
    background: #fee2e2;
    color: #dc2626;
    padding: 6px 12px;
    border-radius: 999px;
    font-size: 12px;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    white-space: nowrap;
}

.status-pending-badge {
    background: #fef3c7;
    color: #d97706;
    padding: 6px 12px;
    border-radius: 999px;
    font-size: 12px;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    white-space: nowrap;
}

/* Responsive */
@media (max-width: 1024px) {
    .page-header-reports {
        flex-direction: column;
        gap: 20px;
        text-align: center;
    }
    
    .col-rejected-by { display: none; }
}

@media (max-width: 768px) {
    .page-header-reports {
        padding: 25px 15px;
    }
    
    .header-left h2 {
        font-size: 22px;
    }
    
    .stats-grid-reports {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .col-approved-by { display: none; }
    .col-notes { display: none; }
    
    .modal-rejection {
        max-width: 90%;
    }
    
    .rejection-notes-content {
        max-height: 300px;
    }
    
    .info-row {
        flex-direction: column;
    }
    
    .info-row label {
        width: 100%;
        margin-bottom: 5px;
    }
    
    .card-header-report {
        flex-direction: column;
        gap: 15px;
    }
    
    .filter-section-report {
        flex-direction: column;
        align-items: stretch;
    }
    
    .filter-action-group {
        margin-left: 0;
        width: 100%;
    }
    
    .btn-export-small {
        width: 100%;
        justify-content: center;
    }
}
</style>

<?php require_once '../../includes/footer.php'; ?>



