<?php
$page_title = 'Reports';
require_once 'includes/auth.php';
require_once 'includes/db.php';

$db = new Database();

// Get report data: approved and rejected appointments grouped by company
$report_data = $db->query("
    SELECT 
        e.contractor_company,
        SUM(CASE WHEN a.status = 'approved' THEN 1 ELSE 0 END) as approved_count,
        SUM(CASE WHEN a.status = 'rejected' THEN 1 ELSE 0 END) as rejected_count,
        COUNT(*) as total_count
    FROM appointments a
    JOIN employees e ON a.employee_id = e.id
    WHERE a.status IN ('approved', 'rejected')
    GROUP BY e.contractor_company
    ORDER BY e.contractor_company
");

// Get detailed approved appointments
$approved_appointments = $db->query("
    SELECT a.*, e.full_name as employee_name, e.employee_code, e.contractor_company, e.ruang_lingkup, e.supervision_area,
           p.position_name, p.position_type,
           u.full_name as created_by_name,
           au.full_name as approved_by_name,
           a.approved_date,
           ktt1.full_name as ktt1_name,
           ktt2.full_name as ktt2_name
    FROM appointments a
    JOIN employees e ON a.employee_id = e.id
    JOIN positions p ON a.position_id = p.id
    LEFT JOIN users u ON a.created_by = u.id
    LEFT JOIN users au ON a.approved_by = au.id
    LEFT JOIN users ktt1 ON a.ktt1_approved_by = ktt1.id
    LEFT JOIN users ktt2 ON a.ktt2_approved_by = ktt2.id
    WHERE a.status = 'approved'
    ORDER BY e.contractor_company, a.approved_date DESC
");

// Get detailed rejected appointments
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
    WHERE a.status = 'rejected'
    GROUP BY a.id
    ORDER BY e.contractor_company, a.approved_date DESC
");

// Get statistics
$approved_total = $db->query("SELECT COUNT(*) as count FROM appointments WHERE status = 'approved'")->fetch_assoc()['count'];
$rejected_total = $db->query("SELECT COUNT(*) as count FROM appointments WHERE status = 'rejected'")->fetch_assoc()['count'];
$total_processed = $approved_total + $rejected_total;

// Get accepted requests (verified employees)
$accepted_requests = $db->query("
    SELECT e.*, e.updated_at as verification_date,
           u.full_name as verified_by_name,
           e.verified_date
    FROM employees e
    LEFT JOIN users u ON e.verified_by = u.id
    WHERE e.verification_status = 'verified'
    ORDER BY e.contractor_company, e.updated_at DESC
");

// Get rejected requests (rejected employees with notes)
$rejected_requests = $db->query("
    SELECT e.*, e.updated_at as verification_date,
           u.full_name as rejected_by_name,
           e.verified_date
    FROM employees e
    LEFT JOIN users u ON e.verified_by = u.id
    WHERE e.verification_status = 'rejected'
    ORDER BY e.contractor_company, e.updated_at DESC
");

// Get waiting for approval requests (pending employees)
$waiting_requests = $db->query("
    SELECT e.*
    FROM employees e
    WHERE e.verification_status = 'pending'
    ORDER BY e.contractor_company, e.created_at DESC
");

// Get request statistics
$accepted_requests_count = $accepted_requests ? $accepted_requests->num_rows : 0;
$rejected_requests_count = $rejected_requests ? $rejected_requests->num_rows : 0;
$waiting_requests_count = $waiting_requests ? $waiting_requests->num_rows : 0;

// Get employees with certificates expiring in 2 months or less
$expiring_certs = $db->query("
    SELECT 
        e.id as employee_id,
        e.full_name,
        e.employee_code,
        e.contractor_company,
        e.ruang_lingkup,
        e.supervision_area,
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
    AND ec.expiry_date >= CURDATE()
    AND e.is_active = 1
    ORDER BY ec.expiry_date ASC, e.contractor_company, e.full_name
");

$expiring_certs_count = $expiring_certs ? $expiring_certs->num_rows : 0;

require_once 'includes/header.php';

// Get unique companies for filter
$companies = $db->query("
    SELECT DISTINCT e.contractor_company
    FROM appointments a
    JOIN employees e ON a.employee_id = e.id
    WHERE a.status IN ('approved', 'rejected')
    ORDER BY e.contractor_company
");

// Get all supervision areas for filter
$supervision_areas = $db->query("SELECT * FROM supervision_areas WHERE is_active = 1 ORDER BY area_name");
?>

<div class="reports-container">
    <!-- Page Header -->
    <div class="page-header-reports">
        <div class="header-left">
            <h2><i class="fas fa-chart-bar"></i>Assign Letter Report</h2>
            <p>Summary and details of the assign letter processing results</p>
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
                <p>Assign Letters Processed</p>
            </div>
        </div>

        <div class="stat-card-report stat-approved">
            <div class="stat-icon-report"><i class="fas fa-check-circle"></i></div>
            <div class="stat-content-report">
                <h3><?php echo $approved_total; ?></h3>
                <p>Accepted</p>
            </div>
        </div>

        <div class="stat-card-report stat-rejected">
            <div class="stat-icon-report"><i class="fas fa-times-circle"></i></div>
            <div class="stat-content-report">
                <h3><?php echo $rejected_total; ?></h3>
                <p>Rejected</p>
            </div>
        </div>
    </div>

    <!-- Request Overview Statistics -->
    <?php if (($accepted_requests && $accepted_requests->num_rows > 0) || ($rejected_requests && $rejected_requests->num_rows > 0) || ($waiting_requests && $waiting_requests->num_rows > 0)): ?>
    <?php
    // Calculate total requests processed
    $total_requests_processed = $accepted_requests_count + $rejected_requests_count + $waiting_requests_count;
    ?>
    <div class="stats-grid-reports" style="margin-top: 20px;">
        <div class="stat-card-report stat-total">
            <div class="stat-icon-report"><i class="fas fa-clipboard-list"></i></div>
            <div class="stat-content-report">
                <h3><?php echo $total_requests_processed; ?></h3>
                <p>Request Processed</p>
            </div>
        </div>

        <?php if ($accepted_requests && $accepted_requests->num_rows > 0): ?>
        <div class="stat-card-report stat-approved">
            <div class="stat-icon-report"><i class="fas fa-user-check"></i></div>
            <div class="stat-content-report">
                <h3><?php echo $accepted_requests_count; ?></h3>
                <p>Accepted Requests</p>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($rejected_requests && $rejected_requests->num_rows > 0): ?>
        <div class="stat-card-report stat-rejected">
            <div class="stat-icon-report"><i class="fas fa-user-times"></i></div>
            <div class="stat-content-report">
                <h3><?php echo $rejected_requests_count; ?></h3>
                <p>Rejected Requests</p>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($waiting_requests && $waiting_requests->num_rows > 0): ?>
        <div class="stat-card-report stat-pending">
            <div class="stat-icon-report"><i class="fas fa-clock"></i></div>
            <div class="stat-content-report">
                <h3><?php echo $waiting_requests_count; ?></h3>
                <p>Waiting for Approval</p>
            </div>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Summary by Company -->
    <div class="card-report">
        <div class="card-header-report">
            <h3><i class="fas fa-building"></i> Summary by Company</h3>
        </div>
        <div class="card-body-report">
            <?php if ($report_data && $report_data->num_rows > 0): ?>
                <div class="table-responsive">
                    <table class="table-report">
                        <thead>
                            <tr>
                                <th class="col-company">Company</th>
                                <th class="col-approved">Accepted</th>
                                <th class="col-rejected">Rejected</th>
                                <th class="col-total">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $report_data->data_seek(0);
                            while ($row = $report_data->fetch_assoc()): 
                            ?>
                            <tr class="report-row">
                                <td class="col-company">
                                    <strong><?php echo htmlspecialchars($row['contractor_company'] ?: 'Unknown'); ?></strong>
                                </td>
                                <td class="col-approved">
                                    <span class="badge-count approved-badge"><?php echo $row['approved_count']; ?></span>
                                </td>
                                <td class="col-rejected">
                                    <span class="badge-count rejected-badge"><?php echo $row['rejected_count']; ?></span>
                                </td>
                                <td class="col-total">
                                    <strong><?php echo $row['total_count']; ?></strong>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty-state-report">
                    <i class="fas fa-inbox"></i>
                    <p>No processed assignment letter data available yet</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Accepted Requests (Verified Employees) -->
    <?php if ($accepted_requests && $accepted_requests->num_rows > 0): ?>
    <div class="card-report">
        <div class="card-header-report">
            <div style="display: flex; align-items: center; gap: 10px; flex: 1;">
                <h3 style="margin: 0;"><i class="fas fa-user-check"></i> Accepted Requests (Verified Employees)</h3>
                <span class="badge-header"><?php echo $accepted_requests->num_rows; ?></span>
            </div>
            <button onclick="toggleSection('acceptedRequestSection')" class="btn-toggle-section" id="btnAcceptedReq">
                <span class="btn-toggle-text">View All</span> <i class="fas fa-chevron-down"></i>
            </button>
        </div>

        <div id="acceptedRequestSection" class="section-content" style="display: none; opacity: 0; max-height: 0;">
        <!-- Filter by Company and Work Scope -->
        <div class="filter-section-report">
            <div class="filter-group-report">
                <label><i class="fas fa-building"></i> Filter Company:</label>
                <select id="companyFilterAcceptedReq" class="filter-select-report" onchange="filterRequestTable('acceptedRequestTable')">
                    <option value="">-- All Companies --</option>
                    <?php
                    $accepted_requests->data_seek(0);
                    $req_companies = [];
                    while ($req = $accepted_requests->fetch_assoc()) {
                        $company = $req['contractor_company'] ?: 'Unknown';
                        if (!in_array($company, $req_companies)) {
                            $req_companies[] = $company;
                        }
                    }
                    sort($req_companies);
                    foreach ($req_companies as $comp):
                    ?>
                    <option value="<?php echo htmlspecialchars($comp); ?>">
                        <?php echo htmlspecialchars($comp); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-group-report">
                <label><i class="fas fa-map-marker-alt"></i> Work Scope:</label>
                <select id="scopeFilterAcceptedReq" class="filter-select-report" onchange="filterRequestTable('acceptedRequestTable')">
                    <option value="">-- All Scopes --</option>
                    <option value="MSM">PT MSM</option>
                    <option value="TTN">PT TTN</option>
                </select>
            </div>
            <div class="filter-action-group">
                <button class="btn btn-export-small" onclick="exportToExcel('acceptedRequestTable', 'Accepted_Requests_Report')">
                    <i class="fas fa-file-excel"></i> Export to Excel
                </button>
            </div>
        </div>

        <div class="card-body-report">
            <div class="table-responsive">
                <table class="table-report" id="acceptedRequestTable">
                    <thead>
                        <tr>
                            <th class="col-company-detail">Company</th>
                            <th class="col-employee">Employee</th>
                            <th class="col-employee-code">Employee Code</th>
                            <th class="col-date">Verified Date</th>
                            <th class="col-approved-info">Verified By</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $accepted_requests->data_seek(0);
                        while ($row = $accepted_requests->fetch_assoc()):
                            $company_name = htmlspecialchars($row['contractor_company'] ?: 'Unknown');
                            $supervision_area = htmlspecialchars($row['supervision_area'] ?: '');
                            // Normalize ruang_lingkup to MSM or TTN
                            $scope_raw = $row['ruang_lingkup'] ?: '';
                            $scope_normalized = '';
                            if (stripos($scope_raw, 'MSM') !== false || stripos($scope_raw, 'Meares Soputan') !== false) {
                                $scope_normalized = 'MSM';
                            } elseif (stripos($scope_raw, 'TTN') !== false || stripos($scope_raw, 'Tondano Nusajaya') !== false) {
                                $scope_normalized = 'TTN';
                            }
                        ?>
                        <tr class="detail-row" data-company="<?php echo $company_name; ?>" data-scope="<?php echo $scope_normalized; ?>" data-supervision="<?php echo $supervision_area; ?>">
                            <td class="col-company-detail">
                                <span class="company-tag"><?php echo $company_name; ?></span>
                            </td>
                            <td class="col-employee">
                                <strong><?php echo htmlspecialchars($row['full_name']); ?></strong>
                            </td>
                            <td class="col-employee-code">
                                <?php echo htmlspecialchars($row['employee_code']); ?>
                            </td>
                            <td class="col-date">
                                <?php if ($row['verification_date']): ?>
                                <i class="fas fa-calendar-check"></i> <?php echo date('d/m/Y H:i', strtotime($row['verification_date'])); ?>
                                <?php else: ?>
                                <span class="text-muted">N/A</span>
                                <?php endif; ?>
                            </td>
                            <td class="col-approved-info">
                                <?php
                                if (($row['verification_status'] == 'verified' || $row['verification_status'] == 'rejected') && $row['verified_by_name']) {
                                    echo '<span class="approver-badge">';
                                    echo '<i class="fas fa-user-check"></i> ' . htmlspecialchars($row['verified_by_name']);
                                    echo '</span>';
                                    if ($row['verified_date']) {
                                        echo '<br><small class="text-muted">' . date('d/m/Y', strtotime($row['verified_date'])) . '</small>';
                                    }
                                } else {
                                    echo '<span class="text-muted">N/A</span>';
                                }
                                ?>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
            <div class="table-info-report" id="acceptedRequestTableInfo">
                Showing all data
            </div>
        </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Rejected Requests (Rejected Employees) -->
    <?php if ($rejected_requests && $rejected_requests->num_rows > 0): ?>
    <div class="card-report">
        <div class="card-header-report">
            <div style="display: flex; align-items: center; gap: 10px; flex: 1;">
                <h3 style="margin: 0;"><i class="fas fa-user-times"></i> Rejected Requests (Rejected Employees)</h3>
                <span class="badge-header rejected"><?php echo $rejected_requests->num_rows; ?></span>
            </div>
            <button onclick="toggleSection('rejectedRequestSection')" class="btn-toggle-section" id="btnRejectedReq">
                <span class="btn-toggle-text">View All</span> <i class="fas fa-chevron-down"></i>
            </button>
        </div>

        <div id="rejectedRequestSection" class="section-content" style="display: none; opacity: 0; max-height: 0;">
        <!-- Filter by Company and Work Scope -->
        <div class="filter-section-report">
            <div class="filter-group-report">
                <label><i class="fas fa-building"></i> Filter Company:</label>
                <select id="companyFilterRejectedReq" class="filter-select-report" onchange="filterRequestTable('rejectedRequestTable')">
                    <option value="">-- All Companies --</option>
                    <?php
                    $rejected_requests->data_seek(0);
                    $rej_companies = [];
                    while ($req = $rejected_requests->fetch_assoc()) {
                        $company = $req['contractor_company'] ?: 'Unknown';
                        if (!in_array($company, $rej_companies)) {
                            $rej_companies[] = $company;
                        }
                    }
                    sort($rej_companies);
                    foreach ($rej_companies as $comp):
                    ?>
                    <option value="<?php echo htmlspecialchars($comp); ?>">
                        <?php echo htmlspecialchars($comp); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-group-report">
                <label><i class="fas fa-map-marker-alt"></i> Work Scope:</label>
                <select id="scopeFilterRejectedReq" class="filter-select-report" onchange="filterRequestTable('rejectedRequestTable')">
                    <option value="">-- All Scopes --</option>
                    <option value="MSM">PT MSM</option>
                    <option value="TTN">PT TTN</option>
                </select>
            </div>
            <div class="filter-action-group">
                <button class="btn btn-export-small" onclick="exportToExcel('rejectedRequestTable', 'Rejected_Requests_Report')">
                    <i class="fas fa-file-excel"></i> Export to Excel
                </button>
            </div>
        </div>

        <div class="card-body-report">
            <div class="table-responsive">
                <table class="table-report" id="rejectedRequestTable">
                    <thead>
                        <tr>
                            <th class="col-company-detail">Company</th>
                            <th class="col-employee">Employee</th>
                            <th class="col-employee-code">Employee Code</th>
                            <th class="col-date">Rejected Date</th>
                            <th class="col-rejected-by">Rejected By</th>
                            <th class="col-notes">Rejection Notes</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $rejected_requests->data_seek(0);
                        while ($row = $rejected_requests->fetch_assoc()):
                            $company_name = htmlspecialchars($row['contractor_company'] ?: 'Unknown');
                            $supervision_area = htmlspecialchars($row['supervision_area'] ?: '');
                            // Normalize ruang_lingkup to MSM or TTN
                            $scope_raw = $row['ruang_lingkup'] ?: '';
                            $scope_normalized = '';
                            if (stripos($scope_raw, 'MSM') !== false || stripos($scope_raw, 'Meares Soputan') !== false) {
                                $scope_normalized = 'MSM';
                            } elseif (stripos($scope_raw, 'TTN') !== false || stripos($scope_raw, 'Tondano Nusajaya') !== false) {
                                $scope_normalized = 'TTN';
                            }
                        ?>
                        <tr class="detail-row rejected-row" data-company="<?php echo $company_name; ?>" data-scope="<?php echo $scope_normalized; ?>" data-supervision="<?php echo $supervision_area; ?>">
                            <td class="col-company-detail">
                                <span class="company-tag"><?php echo $company_name; ?></span>
                            </td>
                            <td class="col-employee">
                                <strong><?php echo htmlspecialchars($row['full_name']); ?></strong>
                            </td>
                            <td class="col-employee-code">
                                <?php echo htmlspecialchars($row['employee_code']); ?>
                            </td>
                            <td class="col-date">
                                <?php if ($row['verification_date']): ?>
                                <i class="fas fa-times-circle"></i> <?php echo date('d/m/Y H:i', strtotime($row['verification_date'])); ?>
                                <?php else: ?>
                                <span class="text-muted">N/A</span>
                                <?php endif; ?>
                            </td>
                            <td class="col-rejected-by">
                                <?php
                                if (($row['verification_status'] == 'verified' || $row['verification_status'] == 'rejected') && $row['rejected_by_name']) {
                                    echo '<span class="rejector-badge">' . htmlspecialchars($row['rejected_by_name']) . '</span>';
                                    if ($row['verified_date']) {
                                        echo '<br><small class="text-muted">' . date('d/m/Y', strtotime($row['verified_date'])) . '</small>';
                                    }
                                } else {
                                    echo '<span class="text-muted">N/A</span>';
                                }
                                ?>
                            </td>
                            <td class="col-notes">
                                <?php if ($row['verification_notes']): ?>
                                <span class="notes-badge" onclick="showRequestRejectionModal('<?php echo htmlspecialchars($row['full_name'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($row['employee_code'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($row['verification_notes'], ENT_QUOTES); ?>')">
                                    <i class="fas fa-eye"></i> View Notes
                                </span>
                                <?php else: ?>
                                <span class="text-muted">No notes</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
            <div class="table-info-report" id="rejectedRequestTableInfo">
                Showing all data
            </div>
        </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Waiting for Approval Requests -->
    <?php if ($waiting_requests && $waiting_requests->num_rows > 0): ?>
    <div class="card-report">
        <div class="card-header-report">
            <div style="display: flex; align-items: center; gap: 10px; flex: 1;">
                <h3 style="margin: 0;"><i class="fas fa-clock"></i> Waiting for Approval Requests</h3>
                <span class="badge-header warning"><?php echo $waiting_requests->num_rows; ?></span>
            </div>
            <button onclick="toggleSection('waitingRequestSection')" class="btn-toggle-section" id="btnWaitingReq">
                <span class="btn-toggle-text">View All</span> <i class="fas fa-chevron-down"></i>
            </button>
        </div>

        <div id="waitingRequestSection" class="section-content" style="display: none; opacity: 0; max-height: 0;">
        <!-- Filter by Company and Work Scope -->
        <div class="filter-section-report">
            <div class="filter-group-report">
                <label><i class="fas fa-building"></i> Filter Company:</label>
                <select id="companyFilterWaitingReq" class="filter-select-report" onchange="filterRequestTable('waitingRequestTable')">
                    <option value="">-- All Companies --</option>
                    <?php
                    $waiting_requests->data_seek(0);
                    $wait_companies = [];
                    while ($req = $waiting_requests->fetch_assoc()) {
                        $company = $req['contractor_company'] ?: 'Unknown';
                        if (!in_array($company, $wait_companies)) {
                            $wait_companies[] = $company;
                        }
                    }
                    sort($wait_companies);
                    foreach ($wait_companies as $comp):
                    ?>
                    <option value="<?php echo htmlspecialchars($comp); ?>">
                        <?php echo htmlspecialchars($comp); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-group-report">
                <label><i class="fas fa-map-marker-alt"></i> Work Scope:</label>
                <select id="scopeFilterWaitingReq" class="filter-select-report" onchange="filterRequestTable('waitingRequestTable')">
                    <option value="">-- All Scopes --</option>
                    <option value="MSM">PT MSM</option>
                    <option value="TTN">PT TTN</option>
                </select>
            </div>
            <div class="filter-action-group">
                <button class="btn btn-export-small" onclick="exportToExcel('waitingRequestTable', 'Waiting_Requests_Report')">
                    <i class="fas fa-file-excel"></i> Export to Excel
                </button>
            </div>
        </div>

        <div class="card-body-report">
            <div class="table-responsive">
                <table class="table-report" id="waitingRequestTable">
                    <thead>
                        <tr>
                            <th class="col-company-detail">Company</th>
                            <th class="col-employee">Employee</th>
                            <th class="col-employee-code">Employee Code</th>
                            <th class="col-date">Requested Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $waiting_requests->data_seek(0);
                        while ($row = $waiting_requests->fetch_assoc()):
                            $company_name = htmlspecialchars($row['contractor_company'] ?: 'Unknown');
                            $supervision_area = htmlspecialchars($row['supervision_area'] ?: '');
                            // Normalize ruang_lingkup to MSM or TTN
                            $scope_raw = $row['ruang_lingkup'] ?: '';
                            $scope_normalized = '';
                            if (stripos($scope_raw, 'MSM') !== false || stripos($scope_raw, 'Meares Soputan') !== false) {
                                $scope_normalized = 'MSM';
                            } elseif (stripos($scope_raw, 'TTN') !== false || stripos($scope_raw, 'Tondano Nusajaya') !== false) {
                                $scope_normalized = 'TTN';
                            }
                        ?>
                        <tr class="detail-row" data-company="<?php echo $company_name; ?>" data-scope="<?php echo $scope_normalized; ?>" data-supervision="<?php echo $supervision_area; ?>">
                            <td class="col-company-detail">
                                <span class="company-tag"><?php echo $company_name; ?></span>
                            </td>
                            <td class="col-employee">
                                <strong><?php echo htmlspecialchars($row['full_name']); ?></strong>
                            </td>
                            <td class="col-employee-code">
                                <?php echo htmlspecialchars($row['employee_code']); ?>
                            </td>
                            <td class="col-date">
                                <?php if ($row['created_at']): ?>
                                <i class="fas fa-calendar"></i> <?php echo date('d/m/Y H:i', strtotime($row['created_at'])); ?>
                                <?php else: ?>
                                <span class="text-muted">N/A</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
            <div class="table-info-report" id="waitingRequestTableInfo">
                Showing all data
            </div>
        </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Approved Appointments -->
    <?php if ($approved_appointments && $approved_appointments->num_rows > 0): ?>
    <div class="card-report">
        <div class="card-header-report">
            <div style="display: flex; align-items: center; gap: 10px; flex: 1;">
                <h3 style="margin: 0;"><i class="fas fa-check-circle"></i> Detail Accepted Assign Letters</h3>
                <span class="badge-header"><?php echo $approved_appointments->num_rows; ?></span>
            </div>
            <button onclick="toggleSection('approvedAppointmentSection')" class="btn-toggle-section" id="btnApprovedAppt">
                <span class="btn-toggle-text">View All</span> <i class="fas fa-chevron-down"></i>
            </button>
        </div>

        <div id="approvedAppointmentSection" class="section-content" style="display: none; opacity: 0; max-height: 0;">
        <!-- Filter by Company and Ruang Lingkup -->
        <div class="filter-section-report">
            <div class="filter-group-report">
                <label><i class="fas fa-building"></i> Filter Company:</label>
                <select id="companyFilterApproved" class="filter-select-report" onchange="filterTableByFilters('approvedTable')">
                    <option value="">-- All Companies --</option>
                    <?php
                    $companies->data_seek(0);
                    while ($comp = $companies->fetch_assoc()):
                    ?>
                    <option value="<?php echo htmlspecialchars($comp['contractor_company'] ?: 'Tidak Diketahui'); ?>">
                        <?php echo htmlspecialchars($comp['contractor_company'] ?: 'Tidak Diketahui'); ?>
                    </option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="filter-group-report">
                <label><i class="fas fa-map-marker-alt"></i> Work Scope:</label>
                <select id="scopeFilterApproved" class="filter-select-report" onchange="filterTableByFilters('approvedTable')">
                    <option value="">-- All Scopes --</option>
                    <option value="MSM">PT MSM</option>
                    <option value="TTN">PT TTN</option>
                </select>
            </div>
            <div class="filter-group-report">
                <label><i class="fas fa-eye"></i> Supervision Area:</label>
                <select id="supervisionFilterApproved" class="filter-select-report" onchange="filterTableByFilters('approvedTable')">
                    <option value="">-- All Areas --</option>
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
                <button class="btn btn-export-small" onclick="exportToExcel('approvedTable', 'Accepted_Assign_Letters_Report')">
                    <i class="fas fa-file-excel"></i> Export to Excel
                </button>
                <button class="btn btn-export-pdf" onclick="exportApprovedByCompany()">
                    <i class="fas fa-file-pdf"></i> Export PDF
                </button>
            </div>
        </div>
        
        <div class="card-body-report">
            <div class="table-responsive">
                <table class="table-report table-approved" id="approvedTable">
                    <thead>
                        <tr>
                            <th class="col-number">Assign Letter No.</th>
                            <th class="col-company-detail">Company</th>
                            <th class="col-employee">Employee</th>
                            <th class="col-position">Position</th>
                            <th class="col-date">Effective Date</th>
                            <th class="col-approved-info">Approved By</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $approved_appointments->data_seek(0);
                        while ($row = $approved_appointments->fetch_assoc()): 
                            $company_name = htmlspecialchars($row['contractor_company'] ?: 'Unknown');
                            $supervision_area = htmlspecialchars($row['supervision_area'] ?: '');
                            // Normalize ruang_lingkup to MSM or TTN
                            $scope_raw = $row['ruang_lingkup'] ?: '';
                            $scope_normalized = '';
                            if (stripos($scope_raw, 'MSM') !== false || stripos($scope_raw, 'Meares Soputan') !== false) {
                                $scope_normalized = 'MSM';
                            } elseif (stripos($scope_raw, 'TTN') !== false || stripos($scope_raw, 'Tondano Nusajaya') !== false) {
                                $scope_normalized = 'TTN';
                            }
                        ?>
                        <tr class="detail-row" data-company="<?php echo $company_name; ?>" data-scope="<?php echo $scope_normalized; ?>" data-supervision="<?php echo $supervision_area; ?>">
                            <td class="col-number">
                                <strong><?php echo htmlspecialchars($row['appointment_number']); ?></strong>
                            </td>
                            <td class="col-company-detail">
                                <span class="company-tag"><?php echo $company_name; ?></span>
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
                                                    <i class="fas fa-user-shield"></i> <strong>KTT MSM:</strong> <?php echo htmlspecialchars($row['ktt1_name']); ?>
                                                </span>
                                                <?php if ($row['ktt1_approved_date']): ?>
                                                    <span class="approval-datetime">
                                                        <i class="fas fa-calendar-check"></i> <?php echo date('d/m/Y', strtotime($row['ktt1_approved_date'])); ?>
                                                        <i class="fas fa-clock"></i> <?php echo date('H:i', strtotime($row['ktt1_approved_date'])); ?>
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                        <?php if ($row['ktt2_name']): ?>
                                            <div class="approval-item">
                                                <span class="approver-name">
                                                    <i class="fas fa-user-shield"></i> <strong>KTT TTN:</strong> <?php echo htmlspecialchars($row['ktt2_name']); ?>
                                                </span>
                                                <?php if ($row['ktt2_approved_date']): ?>
                                                    <span class="approval-datetime">
                                                        <i class="fas fa-calendar-check"></i> <?php echo date('d/m/Y', strtotime($row['ktt2_approved_date'])); ?>
                                                        <i class="fas fa-clock"></i> <?php echo date('H:i', strtotime($row['ktt2_approved_date'])); ?>
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                    <?php elseif ($row['approved_by_name'] && $row['approved_date']): ?>
                                        <div class="approval-item">
                                            <span class="approver-name">
                                                <i class="fas fa-user-check"></i> <?php echo htmlspecialchars($row['approved_by_name']); ?>
                                            </span>
                                            <span class="approval-datetime">
                                                <i class="fas fa-calendar-check"></i> <?php echo date('d/m/Y', strtotime($row['approved_date'])); ?>
                                                <i class="fas fa-clock"></i> <?php echo date('H:i', strtotime($row['approved_date'])); ?>
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
                Showing all companies
            </div>
        </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Rejected Appointments -->
    <?php if ($rejected_appointments && $rejected_appointments->num_rows > 0): ?>
    <div class="card-report">
        <div class="card-header-report">
            <div style="display: flex; align-items: center; gap: 10px; flex: 1;">
                <h3 style="margin: 0;"><i class="fas fa-times-circle"></i> Detail Rejected Assign Letters</h3>
                <span class="badge-header rejected"><?php echo $rejected_appointments->num_rows; ?></span>
            </div>
            <button onclick="toggleSection('rejectedAppointmentSection')" class="btn-toggle-section" id="btnRejectedAppt">
                <span class="btn-toggle-text">View All</span> <i class="fas fa-chevron-down"></i>
            </button>
        </div>

        <div id="rejectedAppointmentSection" class="section-content" style="display: none; opacity: 0; max-height: 0;">
        <!-- Filter by Company and Ruang Lingkup -->
        <div class="filter-section-report">
            <div class="filter-group-report">
                <label><i class="fas fa-building"></i> Filter Company:</label>
                <select id="companyFilterRejected" class="filter-select-report" onchange="filterTableByFilters('rejectedTable')">
                    <option value="">-- All Companies --</option>
                    <?php 
                    $companies->data_seek(0);
                    while ($comp = $companies->fetch_assoc()): 
                    ?>
                    <option value="<?php echo htmlspecialchars($comp['contractor_company'] ?: 'Unknown'); ?>">
                        <?php echo htmlspecialchars($comp['contractor_company'] ?: 'Unknown'); ?>
                    </option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="filter-group-report">
                <label><i class="fas fa-map-marker-alt"></i> Work Scope:</label>
                <select id="scopeFilterRejected" class="filter-select-report" onchange="filterTableByFilters('rejectedTable')">
                    <option value="">-- All Scopes --</option>
                    <option value="MSM">PT MSM</option>
                    <option value="TTN">PT TTN</option>
                </select>
            </div>
            <div class="filter-group-report">
                <label><i class="fas fa-eye"></i> Supervision Area:</label>
                <select id="supervisionFilterRejected" class="filter-select-report" onchange="filterTableByFilters('rejectedTable')">
                    <option value="">-- All Areas --</option>
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
                <button class="btn btn-export-small" onclick="exportToExcel('rejectedTable', 'Rejected_Assign_Letters_Report')">
                    <i class="fas fa-file-excel"></i> Export to Excel
                </button>
            </div>
        </div>
        
        <div class="card-body-report">
            <div class="table-responsive">
                <table class="table-report table-rejected" id="rejectedTable">
                    <thead>
                        <tr>
                            <th class="col-number">Assign Letter No.</th>
                            <th class="col-company-detail">Company</th>
                            <th class="col-employee">Employee</th>
                            <th class="col-position">Position</th>
                            <th class="col-rejected-date">Rejected Date</th>
                            <th class="col-rejected-by">Rejected By</th>
                            <th class="col-action">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $rejected_appointments->data_seek(0);
                        while ($row = $rejected_appointments->fetch_assoc()): 
                            $company_name = htmlspecialchars($row['contractor_company'] ?: 'Unknown');
                            $supervision_area = htmlspecialchars($row['supervision_area'] ?: '');
                            // Normalize ruang_lingkup to MSM or TTN
                            $scope_raw = $row['ruang_lingkup'] ?: '';
                            $scope_normalized = '';
                            if (stripos($scope_raw, 'MSM') !== false || stripos($scope_raw, 'Meares Soputan') !== false) {
                                $scope_normalized = 'MSM';
                            } elseif (stripos($scope_raw, 'TTN') !== false || stripos($scope_raw, 'Tondano Nusajaya') !== false) {
                                $scope_normalized = 'TTN';
                            }
                            $rejection_id = 'rejection_' . $row['id'];
                        ?>
                        <tr class="detail-row rejected-row" data-company="<?php echo $company_name; ?>" data-scope="<?php echo $scope_normalized; ?>" data-supervision="<?php echo $supervision_area; ?>">
                            <td class="col-number">
                                <strong><?php echo htmlspecialchars($row['appointment_number']); ?></strong>
                            </td>
                            <td class="col-company-detail">
                                <span class="company-tag"><?php echo $company_name; ?></span>
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
                            <td class="col-rejected-date">
                                <i class="fas fa-times"></i> <?php echo date('d/m/Y H:i', strtotime($row['approved_date'])); ?>
                            </td>
                            <td class="col-rejected-by">
                                <span class="rejector-badge"><?php echo htmlspecialchars($row['approved_by_name'] ?: 'N/A'); ?></span>
                            </td>
                            <td class="col-action">
                                <button class="btn-detail-small"
                                        data-id="<?php echo $row['id']; ?>"
                                        data-appointment-number="<?php echo htmlspecialchars($row['appointment_number']); ?>"
                                        data-employee-name="<?php echo htmlspecialchars($row['employee_name']); ?>"
                                        data-ktt-notes="<?php echo htmlspecialchars($row['ktt_notes'] ?? ''); ?>"
                                        onclick="showRejectionModalFromButton(this)">
                                    <i class="fas fa-eye"></i> View Details
                                </button>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
            <div class="table-info-report" id="rejectedTableInfo">
                Showing all Companies
            </div>
        </div>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Certificate Expiration Report -->
    <?php if ($expiring_certs && $expiring_certs->num_rows > 0): ?>
    <div class="card-report" id="certificate-expiration">
        <div class="card-header-report">
            <div style="display: flex; align-items: center; gap: 10px; flex: 1;">
                <h3 style="margin: 0;"><i class="fas fa-exclamation-triangle"></i> Expiring Certificates (=2 Months)</h3>
                <span class="badge-header warning"><?php echo $expiring_certs->num_rows; ?></span>
            </div>
        </div>

        <!-- Filter by Company -->
        <div class="filter-section-report">
            <div class="filter-group-report">
                <label><i class="fas fa-building"></i> Filter Company:</label>
                <select id="companyFilterExpiring" class="filter-select-report" onchange="filterExpiringCerts()">
                    <option value="">-- All Companies --</option>
                    <?php
                    // Get unique companies from expiring certs
                    $expiring_certs->data_seek(0);
                    $cert_companies = [];
                    while ($cert = $expiring_certs->fetch_assoc()) {
                        $company = $cert['contractor_company'] ?: 'Unknown';
                        if (!in_array($company, $cert_companies)) {
                            $cert_companies[] = $company;
                        }
                    }
                    sort($cert_companies);
                    foreach ($cert_companies as $comp):
                    ?>
                    <option value="<?php echo htmlspecialchars($comp); ?>">
                        <?php echo htmlspecialchars($comp); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-action-group">
                <button class="btn btn-export-small" onclick="exportToExcel('expiringCertsTable', 'Expiring_Certificates_Report')">
                    <i class="fas fa-file-excel"></i> Export to Excel
                </button>
                <button class="btn btn-export-pdf" onclick="exportExpiringCerts()">
                    <i class="fas fa-file-pdf"></i> Export PDF
                </button>
            </div>
        </div>
        
        <div class="card-body-report">
            <div class="alert-warning-report">
                <i class="fas fa-info-circle"></i>
                <span>Berikut adalah daftar karyawan yang memiliki sertifikat dengan masa berlaku tersisa =2 bulan. Segera lakukan perpanjangan sertifikat.</span>
            </div>
            
            <div class="table-responsive">
                <table class="table-report table-expiring" id="expiringCertsTable">
                    <thead>
                        <tr>
                            <th class="col-company-detail">Company</th>
                            <th class="col-employee">Employee</th>
                            <th class="col-cert-name">Certificate Name</th>
                            <th class="col-cert-number">Certificate Number</th>
                            <th class="col-expiry-date">Expiry Date</th>
                            <th class="col-days-left">Days Left</th>
                            <th class="col-status-expiry">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $expiring_certs->data_seek(0);
                        while ($cert = $expiring_certs->fetch_assoc()): 
                            $company_name = htmlspecialchars($cert['contractor_company'] ?: 'Unknown');
                            $days_left = intval($cert['days_until_expiry']);
                            
                            // Determine status class based on days left
                            $status_class = 'status-critical';
                            $status_text = 'Very Urgent';
                            $status_icon = 'fa-exclamation-circle';
                            
                            if ($days_left > 30) {
                                $status_class = 'status-warning';
                                $status_text = 'Warning';
                                $status_icon = 'fa-exclamation-triangle';
                            } elseif ($days_left > 14) {
                                $status_class = 'status-urgent';
                                $status_text = 'Urgent';
                                $status_icon = 'fa-clock';
                            }
                        ?>
                        <tr class="detail-row expiring-row" data-company="<?php echo $company_name; ?>">
                            <td class="col-company-detail">
                                <span class="company-tag"><?php echo $company_name; ?></span>
                            </td>
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
                                    <?php echo $days_left; ?> days
                                </span>
                            </td>
                            <td class="col-status-expiry">
                                <span class="status-badge <?php echo $status_class; ?>">
                                    <i class="fas <?php echo $status_icon; ?>"></i> <?php echo $status_text; ?>
                                </span>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
            <div class="table-info-report" id="expiringCertsTableInfo">
                Showing all data
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Rejection Details Modal for Requests -->
<div id="requestRejectionModal" class="modal">
    <div class="modal-content modal-rejection">
        <div class="modal-header modal-header-rejection">
            <h3><i class="fas fa-exclamation-circle"></i> Request Rejection Details</h3>
            <span class="close" onclick="closeRequestRejectionModal()">&times;</span>
        </div>
        <div class="modal-body modal-body-rejection">
            <div class="rejection-info">
                <div class="info-row">
                    <label>Employee Name:</label>
                    <span id="reqRejectionEmployeeName"></span>
                </div>
                <div class="info-row">
                    <label>Employee Code:</label>
                    <span id="reqRejectionEmployeeCode"></span>
                </div>
                <div class="rejection-notes-section">
                    <h4><i class="fas fa-clipboard"></i> Rejection Notes from Reviewer</h4>
                    <div class="rejection-notes-content">
                        <div class="rejection-note-item">
                            <div class="rejection-note-text" id="reqRejectionNotesContent"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="modal-footer modal-footer-rejection">
            <button type="button" class="btn btn-secondary" onclick="closeRequestRejectionModal()">Close</button>
        </div>
    </div>
</div>

<!-- Rejection Details Modal -->
<div id="rejectionModal" class="modal">
    <div class="modal-content modal-rejection">
        <div class="modal-header modal-header-rejection">
            <h3><i class="fas fa-exclamation-circle"></i> Rejection Details of Appointment Letter</h3>
            <span class="close" onclick="closeRejectionModal()">&times;</span>
        </div>
        <div class="modal-body modal-body-rejection">
            <div class="rejection-info">
                <div class="info-row">
                    <label>Appointment Number:</label>
                    <span id="rejectionAppointmentNumber"></span>
                </div>
                <div class="info-row">
                    <label>Employee Name:</label>
                    <span id="rejectionEmployeeName"></span>
                </div>
                <div class="rejection-notes-section">
                    <h4><i class="fas fa-clipboard"></i> Rejection Notes from KTT</h4>
                    <div class="rejection-notes-content" id="rejectionNotesContent">
                        <!-- Notes will be filled via JavaScript -->
                    </div>
                </div>
            </div>
        </div>
        <div class="modal-footer modal-footer-rejection">
            <button type="button" class="btn btn-secondary" onclick="closeRejectionModal()">Close</button>
        </div>
    </div>
</div>

<script>
function filterTableByFilters(tableId) {
    const table = document.getElementById(tableId);
    const rows = table.getElementsByTagName('tbody')[0].getElementsByTagName('tr');
    let visibleCount = 0;
    
    // Get filter values
    const companyFilter = document.getElementById('companyFilter' + (tableId === 'approvedTable' ? 'Approved' : 'Rejected')).value;
    const scopeFilter = document.getElementById('scopeFilter' + (tableId === 'approvedTable' ? 'Approved' : 'Rejected')).value;
    const supervisionFilter = document.getElementById('supervisionFilter' + (tableId === 'approvedTable' ? 'Approved' : 'Rejected')).value;
    
    // Apply filters
    for (let row of rows) {
        const rowCompany = row.getAttribute('data-company');
        const rowScope = row.getAttribute('data-scope');
        const rowSupervision = row.getAttribute('data-supervision');
        
        let showRow = true;
        
        // Filter by company
        if (companyFilter && rowCompany !== companyFilter) {
            showRow = false;
        }
        
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
    let infoMessage = 'Showing ' + visibleCount + ' data';
    if (companyFilter || scopeFilter || supervisionFilter) {
        infoMessage += ' - Filter: ';
        const filters = [];
        if (companyFilter) filters.push('Company: ' + companyFilter);
        if (scopeFilter) {
            const scopeLabel = scopeFilter === 'MSM' ? 'PT MSM' : 'PT TTN';
            filters.push('Scope: ' + scopeLabel);
        }
        if (supervisionFilter) filters.push('Supervision: ' + supervisionFilter);
        infoMessage += filters.join(', ');
    } else {
        infoMessage = 'Showing all data';
    }
    updateTableInfo(tableId, infoMessage);
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
        notesContent.innerHTML = '<p class="text-muted">No rejection notes</p>';
    }
    
    document.getElementById('rejectionModal').style.display = 'block';
}

function closeRejectionModal() {
    document.getElementById('rejectionModal').style.display = 'none';
}

// Safe function to show rejection modal using button data attributes
function showRejectionModalFromButton(button) {
    const id = button.getAttribute('data-id');
    const appointmentNumber = button.getAttribute('data-appointment-number');
    const employeeName = button.getAttribute('data-employee-name');
    const kttNotes = button.getAttribute('data-ktt-notes');

    showRejectionModal(id, appointmentNumber, employeeName, kttNotes);
}

function filterRequestTable(tableId) {
    const table = document.getElementById(tableId);
    const rows = table.getElementsByTagName('tbody')[0].getElementsByTagName('tr');
    let visibleCount = 0;

    // Determine which filter to use based on table ID
    let companyFilterId = '';
    let scopeFilterId = '';
    if (tableId === 'acceptedRequestTable') {
        companyFilterId = 'companyFilterAcceptedReq';
        scopeFilterId = 'scopeFilterAcceptedReq';
    } else if (tableId === 'rejectedRequestTable') {
        companyFilterId = 'companyFilterRejectedReq';
        scopeFilterId = 'scopeFilterRejectedReq';
    } else if (tableId === 'waitingRequestTable') {
        companyFilterId = 'companyFilterWaitingReq';
        scopeFilterId = 'scopeFilterWaitingReq';
    }

    const companyFilter = document.getElementById(companyFilterId).value;
    const scopeFilter = document.getElementById(scopeFilterId).value;

    // Apply filters
    for (let row of rows) {
        const rowCompany = row.getAttribute('data-company');
        const rowScope = row.getAttribute('data-scope');

        let showRow = true;

        // Filter by company
        if (companyFilter && rowCompany !== companyFilter) {
            showRow = false;
        }

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
    let infoMessage = 'Showing ' + visibleCount + ' data';
    if (companyFilter || scopeFilter) {
        infoMessage += ' - Filter: ';
        const filters = [];
        if (companyFilter) filters.push('Company: ' + companyFilter);
        if (scopeFilter) {
            const scopeLabel = scopeFilter === 'MSM' ? 'PT MSM' : 'PT TTN';
            filters.push('Scope: ' + scopeLabel);
        }
        infoMessage += filters.join(', ');
    } else {
        infoMessage = 'Showing all data';
    }
    updateTableInfo(tableId, infoMessage);
}

function showRequestRejectionModal(employeeName, employeeCode, notes) {
    document.getElementById('reqRejectionEmployeeName').textContent = employeeName;
    document.getElementById('reqRejectionEmployeeCode').textContent = employeeCode;
    document.getElementById('reqRejectionNotesContent').textContent = notes || 'No rejection notes';

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

function exportApprovedByCompany() {
    const table = document.getElementById('approvedTable');
    if (!table) {
        alert('Tabel tidak ditemukan!');
        return;
    }
    
    // Get all visible companies from the table
    const rows = table.getElementsByTagName('tbody')[0].getElementsByTagName('tr');
    const companies = {};
    
    for (let row of rows) {
        if (row.style.display !== 'none') {
            const company = row.getAttribute('data-company');
            if (!companies[company]) {
                companies[company] = [];
            }
            companies[company].push(row);
        }
    }
    
    if (Object.keys(companies).length === 0) {
        alert('No data to print!');
        return;
    }
    
    // Generate HTML for PDF
    let htmlContent = `
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Accepted Assignment Letters Report</title>
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
                .header-info {
                    text-align: center;
                    margin-bottom: 20px;
                    font-size: 12px;
                    color: #666;
                }
                h2 {
                    color: #616161;
                    font-size: 16px;
                    border-bottom: 2px solid #37474F;
                    padding-bottom: 8px;
                    margin-top: 25px;
                    margin-bottom: 15px;
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
                .company-summary {
                    background-color: #ECEFF1;
                    padding: 10px;
                    margin-bottom: 10px;
                    border-radius: 4px;
                    font-weight: 600;
                    color: #37474F;
                }
                .page-break {
                    page-break-after: always;
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
            <h1><i>?? Accepted Assignment Letters Report</i></h1>
            <div class="header-info">
                Printed on: ${new Date().toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' })}
                <br>Time: ${new Date().toLocaleTimeString('en-US')}
            </div>
    `;
    
    // Generate content for each company
    let totalCount = 0;
    for (const company in companies) {
        const companyRows = companies[company];
        totalCount += companyRows.length;
        
        htmlContent += `
            <h2>Company: ${company}</h2>
            <div class="company-summary">Total: ${companyRows.length} accepted letters</div>
            <table>
                <thead>
                    <tr>
                        <th style="width: 12%;">Letter No.</th>
                        <th style="width: 22%;">Employee Name</th>
                        <th style="width: 18%;">Position</th>
                        <th style="width: 12%;">Effective Date</th>
                        <th style="width: 36%;">Approved By</th>
                    </tr>
                </thead>
                <tbody>
        `;
        
        for (let row of companyRows) {
            const cells = row.getElementsByTagName('td');
            // Get approval info from the last cell
            const approvalCell = cells[5];
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
                    <td>${cells[2].textContent.trim().split('\n')[0]}</td>
                    <td>${cells[3].textContent.trim()}</td>
                    <td>${cells[4].textContent.trim()}</td>
                    <td>${approvalText}</td>
                </tr>
            `;
        }
        
        htmlContent += `
                </tbody>
            </table>
        `;
    }
    
    htmlContent += `
            <div class="footer">
                Total Accepted Letters: <strong>${totalCount}</strong>
                <br>This document is printed from the Expertise Assignment Letter System
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
        alert('Tabel tidak ditemukan!');
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
        alert('No data to export!');
        return;
    }
    
    // Get active filters
    const companyFilter = document.getElementById('companyFilter' + (tableId === 'approvedTable' ? 'Approved' : 'Rejected')).value;
    const scopeFilter = document.getElementById('scopeFilter' + (tableId === 'approvedTable' ? 'Approved' : 'Rejected')).value;
    
    // Determine if it's approved or rejected table
    const isApproved = tableId === 'approvedTable';
    
    // Build Excel HTML
    let excelContent = '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">';
    excelContent += '<head><meta charset="UTF-8"><!--[if gte mso 9]><xml><x:ExcelWorkbook><x:ExcelWorksheets><x:ExcelWorksheet>';
    excelContent += '<x:Name>Report</x:Name><x:WorksheetOptions><x:DisplayGridlines/></x:WorksheetOptions></x:ExcelWorksheet>';
    excelContent += '</x:ExcelWorksheets></x:ExcelWorkbook></xml><![endif]--></head><body>';
    
    // Add title and filters info
    excelContent += '<table><tr><td colspan="7"><h2>Assignment Letters Report ' + (isApproved ? 'Accepted' : 'Rejected') + '</h2></td></tr>';
    excelContent += '<tr><td colspan="7">Export Date: ' + new Date().toLocaleDateString('en-US') + ' ' + new Date().toLocaleTimeString('en-US') + '</td></tr>';
    
    if (companyFilter || scopeFilter) {
        let filterInfo = 'Filter: ';
        if (companyFilter) filterInfo += 'Company: ' + companyFilter;
        if (companyFilter && scopeFilter) filterInfo += ' | ';
        if (scopeFilter) {
            const scopeLabel = scopeFilter === 'MSM' ? 'PT MSM' : 'PT TTN';
            filterInfo += 'Scope: ' + scopeLabel;
        }
        excelContent += '<tr><td colspan="7">' + filterInfo + '</td></tr>';
    }
    
    excelContent += '<tr><td colspan="7">Total Data: ' + filteredRows.length + '</td></tr>';
    excelContent += '<tr><td colspan="7">&nbsp;</td></tr>';
    
    // Add table headers
    excelContent += '<tr style="background-color: #37474F; color: white; font-weight: bold;">';
    excelContent += '<td>No. Surat</td>';
    excelContent += '<td>Perusahaan</td>';
    excelContent += '<td>Employee Name</td>';
    excelContent += '<td>Employee Code</td>';
    excelContent += '<td>Position</td>';
    excelContent += '<td>Effective Date</td>';;
    if (isApproved) {
        excelContent += '<td>Disetujui Oleh</td>';
    } else {
        excelContent += '<td>Ditolak</td>';
        excelContent += '<td>Ditolak Oleh</td>';
        excelContent += '<td>Rejection Notes</td>';
    }
    excelContent += '</tr>';
    
    // Add table data
    for (let row of filteredRows) {
        const cells = row.getElementsByTagName('td');
        excelContent += '<tr>';
        
        // Letter No.
        excelContent += '<td>' + cells[0].textContent.trim() + '</td>';
        
        // Company
        excelContent += '<td>' + cells[1].textContent.trim() + '</td>';
        
        // Employee Name (extract name only, without code)
        const employeeText = cells[2].textContent.trim().split('\n');
        excelContent += '<td>' + employeeText[0].trim() + '</td>';
        
        // Employee Code
        if (employeeText.length > 1) {
            excelContent += '<td>' + employeeText[1].trim() + '</td>';
        } else {
            excelContent += '<td></td>';
        }
        
        // Position
        excelContent += '<td>' + cells[3].textContent.trim() + '</td>';
        
        // Effective Date
        excelContent += '<td>' + cells[4].textContent.trim() + '</td>';
        
        if (isApproved) {
            // Approved By - get all approval info
            const approvalCell = cells[5];
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
            excelContent += '<td>' + cells[5].textContent.trim() + '</td>';
            // Rejected By
            excelContent += '<td>' + cells[6].textContent.trim() + '</td>';
            // Notes
            if (cells.length > 7) {
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
    let finalFilename = filename;
    if (companyFilter) {
        finalFilename += '_' + companyFilter.replace(/[^a-zA-Z0-9]/g, '_');
    }
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

function filterExpiringCerts() {
    const table = document.getElementById('expiringCertsTable');
    const rows = table.getElementsByTagName('tbody')[0].getElementsByTagName('tr');
    let visibleCount = 0;
    
    const companyFilter = document.getElementById('companyFilterExpiring').value;
    
    for (let row of rows) {
        const rowCompany = row.getAttribute('data-company');
        
        if (!companyFilter || rowCompany === companyFilter) {
            row.style.display = '';
            visibleCount++;
        } else {
            row.style.display = 'none';
        }
    }
    
    let infoMessage = 'Menampilkan ' + visibleCount + ' data';
    if (companyFilter) {
        infoMessage += ' - Filter: Company: ' + companyFilter;
    } else {
        infoMessage = 'Menampilkan semua data';
    }
    
    const infoElement = document.getElementById('expiringCertsTableInfo');
    if (infoElement) {
        infoElement.textContent = infoMessage;
    }
}

function exportExpiringCerts() {
    const table = document.getElementById('expiringCertsTable');
    if (!table) {
        alert('Tabel tidak ditemukan!');
        return;
    }
    
    const rows = table.getElementsByTagName('tbody')[0].getElementsByTagName('tr');
    const companies = {};
    
    for (let row of rows) {
        if (row.style.display !== 'none') {
            const company = row.getAttribute('data-company');
            if (!companies[company]) {
                companies[company] = [];
            }
            companies[company].push(row);
        }
    }
    
    if (Object.keys(companies).length === 0) {
        alert('No data to print!');
        return;
    }
    
    let htmlContent = `
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Expiring Certificates Report</title>
            <style>
                body {
                    font-family: Arial, sans-serif;
                    margin: 20px;
                    color: #333;
                }
                h1 {
                    text-align: center;
                    color: #f59e0b;
                    margin-bottom: 5px;
                }
                .header-info {
                    text-align: center;
                    margin-bottom: 20px;
                    font-size: 12px;
                    color: #666;
                }
                .warning-box {
                    background: #fef3c7;
                    border-left: 4px solid #f59e0b;
                    padding: 12px;
                    margin-bottom: 20px;
                    border-radius: 4px;
                }
                h2 {
                    color: #616161;
                    font-size: 16px;
                    border-bottom: 2px solid #f59e0b;
                    padding-bottom: 8px;
                    margin-top: 25px;
                    margin-bottom: 15px;
                }
                table {
                    width: 100%;
                    border-collapse: collapse;
                    margin-bottom: 20px;
                }
                thead {
                    background-color: #fef3c7;
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
                .company-summary {
                    background: #fef3c7;
                    padding: 10px;
                    margin-bottom: 10px;
                    border-radius: 4px;
                    font-weight: 600;
                    color: #f59e0b;
                }
                .status-critical { color: #dc2626; font-weight: bold; }
                .status-urgent { color: #f59e0b; font-weight: bold; }
                .status-warning { color: #f97316; }
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
            <h1>?? Expiring Certificates Report (=2 Months)</h1>
            <div class="header-info">
                Printed on: ${new Date().toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' })}
                <br>Time: ${new Date().toLocaleTimeString('en-US')}
            </div>
            <div class="warning-box">
                <strong>?? ATTENTION:</strong> The following is a list of employees with certificates expiring within =2 months. Please renew certificates immediately to avoid expiration.
            </div>
    `;
    
    let totalCount = 0;
    for (const company in companies) {
        const companyRows = companies[company];
        totalCount += companyRows.length;
        
        htmlContent += `
            <h2>Company: ${company}</h2>
            <div class="company-summary">Total: ${companyRows.length} expiring certificates</div>
            <table>
                <thead>
                    <tr>
                        <th style="width: 20%;">Employee Name</th>
                        <th style="width: 10%;">Code</th>
                        <th style="width: 20%;">Certificate Name</th>
                        <th style="width: 15%;">Certificate Number</th>
                        <th style="width: 12%;">Expiry Date</th>
                        <th style="width: 10%;">Days Left</th>
                        <th style="width: 13%;">Status</th>
                    </tr>
                </thead>
                <tbody>
        `;
        
        for (let row of companyRows) {
            const cells = row.getElementsByTagName('td');
            const employeeText = cells[1].textContent.trim().split('\n');
            const employeeName = employeeText[0].trim();
            const employeeCode = employeeText.length > 1 ? employeeText[1].trim() : '';
            const statusCell = cells[6];
            let statusClass = '';
            if (statusCell.textContent.includes('Sangat Mendesak')) {
                statusClass = 'status-critical';
            } else if (statusCell.textContent.includes('Mendesak')) {
                statusClass = 'status-urgent';
            } else {
                statusClass = 'status-warning';
            }
            
            htmlContent += `
                <tr>
                    <td>${employeeName}</td>
                    <td>${employeeCode}</td>
                    <td>${cells[2].textContent.trim()}</td>
                    <td>${cells[3].textContent.trim()}</td>
                    <td>${cells[4].textContent.trim()}</td>
                    <td>${cells[5].textContent.trim()}</td>
                    <td class="${statusClass}">${cells[6].textContent.trim()}</td>
                </tr>
            `;
        }
        
        htmlContent += `
                </tbody>
            </table>
        `;
    }
    
    htmlContent += `
            <div class="footer">
                <strong>Total Expiring Certificates: ${totalCount}</strong>
                <br>This document is printed from the Expertise Assignment Letter System
            </div>
        </body>
        </html>
    `;
    
    const printWindow = window.open('', '_blank');
    printWindow.document.write(htmlContent);
    printWindow.document.close();
    
    setTimeout(() => {
        printWindow.print();
    }, 250);
}
</script>

<style>
.reports-container {
    padding: 20px 0;
}

/* Page Header */
.page-header-reports {
    background: #7C3AED;
    color: white;
    padding: 35px 30px;
    border-radius: 10px;
    margin-bottom: 35px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    box-shadow: 0 4px 15px rgba(245, 124, 0, 0.3);
}

.header-left h2 {
    margin: 0 0 8px 0;
    font-size: 26px;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 10px;
}

.header-left p {
    margin: 0;
    opacity: 0.9;
    font-size: 14px;
}

.header-date {
    background: rgba(255, 255, 255, 0.2);
    padding: 12px 20px;
    border-radius: 8px;
    font-size: 13px;
    display: flex;
    align-items: center;
    gap: 8px;
}

/* Stats Grid */
.stats-grid-reports {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 35px;
}

.stat-card-report {
    background: white;
    border-radius: 10px;
    padding: 25px;
    display: flex;
    align-items: center;
    gap: 20px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
    border-top: 4px solid #ccc;
    transition: all 0.3s ease;
}

.stat-card-report:hover {
    transform: translateY(-5px);
    box-shadow: 0 5px 20px rgba(0, 0, 0, 0.12);
}

.stat-total { border-top-color: #37474F; }
.stat-approved { border-top-color: #2E7D32; }
.stat-rejected { border-top-color: #ef4444; }
.stat-pending { border-top-color: #f59e0b; }

.stat-icon-report {
    font-size: 40px;
    width: 70px;
    height: 70px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 10px;
    color: white;
}

.stat-total .stat-icon-report { background: #37474F; }

.stat-approved .stat-icon-report {
    background: linear-gradient(135deg, #7C3AED, #8B5CF6);
    color: #fff;
}
.stat-rejected .stat-icon-report {
    background: linear-gradient(135deg, #EF5350, #D32F2F);
    color: #fff;
}
.stat-pending .stat-icon-report {
    background: linear-gradient(135deg, #FFD600, #FFB300);
    color: #7C3AED;
}
.stat-draft .stat-icon-report {
    background: linear-gradient(135deg, #9ca3af, #bdbdbd);
    color: #37474F;
}
.stat-needs-review .stat-icon-report {
    background: linear-gradient(135deg, #2196F3, #1976D2);
    color: #fff;
}

.stat-content-report h3 {
    font-size: 28px;
    font-weight: 700;
    color: #333;
    margin: 0;
}

.stat-content-report p {
    color: #666;
    font-size: 13px;
    margin: 5px 0 0 0;
}

/* Cards */
.card-report {
    background: white;
    border-radius: 10px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
    margin-bottom: 25px;
}

.card-header-report {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 25px;
    border-bottom: 2px solid #f0f0f0;
}

.card-header-report h3 {
    margin: 0;
    font-size: 18px;
    color: #333;
    display: flex;
    align-items: center;
    gap: 10px;
}

.card-header-report i {
    color: #37474F;
}

.badge-header {
    background: #37474F;
    color: white;
    padding: 6px 12px;
    border-radius: 6px;
    font-size: 12px;
    font-weight: 600;
}

.badge-header.rejected {
    background: #ef4444;
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
    background: #f8f9fa;
    color: #333;
    font-weight: 600;
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    border: none;
    padding: 15px;
    text-align: left;
}

.report-row,
.detail-row {
    border-bottom: 1px solid #f0f0f0;
    transition: background-color 0.2s ease;
}

.report-row:hover,
.detail-row:hover {
    background-color: #f8f9ff;
}

.table-report td {
    padding: 15px;
    vertical-align: middle;
    font-size: 13px;
}

.badge-count {
    display: inline-block;
    padding: 8px 14px;
    border-radius: 6px;
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

.progress-bar-inline {
    width: 80px;
    height: 6px;
    background: #e5e7eb;
    border-radius: 3px;
    overflow: hidden;
    display: inline-block;
    margin-right: 8px;
}

.progress-fill {
    height: 100%;
    background: linear-gradient(90deg, #2E7D32, #1B5E20);
}

.percentage-text {
    font-weight: 600;
    color: #333;
    font-size: 12px;
}

.company-tag {
    background: #f3f4f6;
    color: #666;
    padding: 6px 12px;
    border-radius: 6px;
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
    background: #ECEFF1;
    color: #37474F;
    padding: 6px 12px;
    border-radius: 6px;
    font-size: 12px;
    display: inline-block;
}

.approver-badge {
    background: #E8F5E9;
    color: #2E7D32;
    padding: 6px 12px;
    border-radius: 6px;
    font-size: 12px;
    display: inline-block;
    margin: 2px 0;
}

.approver-badge i {
    margin-right: 4px;
}

.col-approved-by .approver-badge {
    display: block;
    margin: 3px 0;
}

.rejector-badge {
    background: #fee2e2;
    color: #ef4444;
    padding: 6px 12px;
    border-radius: 6px;
    font-size: 12px;
    display: inline-block;
}

.notes-text {
    color: #666;
    font-size: 12px;
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
    padding: 20px;
    background: #f8f9fa;
    border-bottom: 1px solid #e9ecef;
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
    display: flex;
    gap: 10px;
    align-items: center;
}

.filter-group-report label {
    font-weight: 600;
    color: #333;
    white-space: nowrap;
    display: flex;
    align-items: center;
    gap: 6px;
    margin: 0;
    font-size: 13px;
}

.filter-select-report {
    padding: 10px 12px;
    border: 2px solid #e9ecef;
    border-radius: 6px;
    font-size: 13px;
    cursor: pointer;
    transition: border-color 0.3s ease;
    min-width: 200px;
}

.filter-select-report:hover,
.filter-select-report:focus {
    border-color: #37474F;
    outline: none;
}

.table-info-report {
    padding: 12px 20px;
    background: #ECEFF1;
    color: #37474F;
    border-top: 1px solid #b3e5fc;
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
    border-radius: 8px 8px 0 0;
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

/* Export PDF Button Styles */
.btn-export-pdf {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 16px;
    background: rgba(239, 68, 68, 0.2);
    color: #dc2626;
    border: 1px solid rgba(239, 68, 68, 0.3);
    border-radius: 6px;
    font-size: 13px;
    cursor: pointer;
    transition: all 0.3s ease;
    font-weight: 600;
}

.btn-export-pdf:hover {
    background: rgba(239, 68, 68, 0.3);
    border-color: rgba(239, 68, 68, 0.4);
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(239, 68, 68, 0.2);
}

.btn-export-pdf i {
    font-size: 14px;
}

/* Certificate Expiration Styles */
.badge-header.warning {
    background: #fef3c7;
    color: #f59e0b;
}

.alert-warning-report {
    background: #fef3c7;
    border-left: 4px solid #f59e0b;
    padding: 15px 20px;
    border-radius: 6px;
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

.col-cert-name { width: 18%; }
.col-cert-number { width: 15%; }
.col-expiry-date { width: 12%; }
.col-days-left { width: 10%; }
.col-status-expiry { width: 13%; }

.cert-name-badge {
    background: #ECEFF1;
    color: #37474F;
    padding: 6px 12px;
    border-radius: 6px;
    font-size: 12px;
    display: inline-block;
    font-weight: 500;
}

.days-badge {
    display: inline-block;
    padding: 6px 12px;
    border-radius: 6px;
    font-size: 12px;
    font-weight: 600;
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
    color: #f59e0b;
}

.status-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 12px;
    border-radius: 6px;
    font-size: 12px;
    font-weight: 600;
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
    color: #f59e0b;
}

.table-expiring tbody tr:hover {
    background-color: #fef3c7;
}

/* Responsive */
@media (max-width: 1024px) {
    .page-header-reports {
        flex-direction: column;
        gap: 20px;
        text-align: center;
    }
    
    .col-company-detail { display: none; }
    .col-percentage { display: none; }
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
        flex-direction: column;
        gap: 8px;
    }

    .btn-export-small,
    .btn-export-pdf {
        width: 100%;
        justify-content: center;
    }
}

/* Toggle Section Button */
.btn-toggle-section {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 8px 16px;
    background: #37474F;
    color: white;
    border: none;
    border-radius: 6px;
    font-size: 13px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.3s ease;
    box-shadow: 0 2px 4px rgba(55, 71, 79, 0.2);
}

.btn-toggle-section:hover {
    background: linear-gradient(135deg, #37474F, #007bb3);
    transform: translateY(-1px);
    box-shadow: 0 3px 8px rgba(55, 71, 79, 0.3);
}

.btn-toggle-section i {
    font-size: 12px;
    transition: transform 0.3s ease;
}

.section-content {
    transition: max-height 0.4s ease-out, opacity 0.4s ease-out;
    overflow: hidden;
}
</style>

<script>
function toggleSection(sectionId) {
    const section = document.getElementById(sectionId);
    const button = event.currentTarget;
    const icon = button.querySelector('i');
    const text = button.querySelector('.btn-toggle-text');

    if (section.style.display === 'none' || section.style.display === '') {
        // Show section
        section.style.display = 'block';
        // Trigger reflow
        section.offsetHeight;
        section.style.opacity = '1';
        section.style.maxHeight = '10000px';

        // Update button
        icon.classList.remove('fa-chevron-down');
        icon.classList.add('fa-chevron-up');
        text.textContent = 'Hide';
    } else {
        // Hide section
        section.style.opacity = '0';
        section.style.maxHeight = '0';

        // Update button
        icon.classList.remove('fa-chevron-up');
        icon.classList.add('fa-chevron-down');
        text.textContent = 'View All';

        // Wait for transition before hiding
        setTimeout(() => {
            section.style.display = 'none';
        }, 400);
    }
}
</script>

<?php require_once 'includes/footer.php'; ?>


