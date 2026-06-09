<?php
$page_title = 'Reports';
require_once '../../includes/auth.php';
require_once '../../includes/db.php';

$db = new Database();

function normalizeCompetencyTypeLabel($appointmentNumber = '', $positionType = '', $positionName = '') {
    $appointmentNumberUpper = strtoupper(trim((string)$appointmentNumber));

    // Primary source: registration code in letter number format.
    if (preg_match('#/(TT|PT|PO)/#', $appointmentNumberUpper, $matches)) {
        if ($matches[1] === 'TT') {
            return 'Tenaga Teknis';
        }
        if ($matches[1] === 'PT') {
            return 'Pengawas Teknis';
        }
        if ($matches[1] === 'PO') {
            return 'Pengawas Operasional';
        }
    }

    $type = trim((string)$positionType);
    $name = trim((string)$positionName);
    $combined = strtolower(trim(preg_replace('/\s+/', ' ', $type . ' ' . $name)));

    // Prioritize explicit phrase matches so technical staff does not leak into supervisor category.
    if (strpos($combined, 'tenaga teknis') !== false) {
        return 'Tenaga Teknis';
    }
    if (strpos($combined, 'pengawas teknis') !== false) {
        return 'Pengawas Teknis';
    }
    if (strpos($combined, 'pengawas operasional') !== false) {
        return 'Pengawas Operasional';
    }
    if (strpos($combined, 'pengawas') !== false && strpos($combined, 'teknis') !== false) {
        return 'Pengawas Teknis';
    }
    if (strpos($combined, 'tenaga') !== false && strpos($combined, 'teknis') !== false) {
        return 'Tenaga Teknis';
    }

    return $type !== '' ? $type : ($name !== '' ? $name : '');
}

function sanitizeCompetencyTypeValue($value = '') {
    return trim((string)preg_replace('/\s+/', ' ', (string)$value));
}

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
    SELECT a.*, e.full_name as employee_name, e.employee_code, e.department, e.position as employee_position, e.contractor_company, e.ruang_lingkup, e.supervision_area,
           p.position_name, p.position_type,
           u.full_name as created_by_name,
           au.full_name as approved_by_name,
           a.approved_date,
           ktt1.full_name as ktt1_name,
           ktt2.full_name as ktt2_name,
           (SELECT ec.cert_issuer FROM employee_certifications ec 
            WHERE ec.employee_id = e.id AND ec.verification_status = 'verified' 
            ORDER BY ec.created_at DESC LIMIT 1) as cert_issuer
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

// Build approved competency options from actual approved data using register code rules.
$approved_competency_type_options = [];
if ($approved_appointments && $approved_appointments->num_rows > 0) {
    $approved_appointments->data_seek(0);
    while ($appt = $approved_appointments->fetch_assoc()) {
        $normalizedType = normalizeCompetencyTypeLabel(
            $appt['appointment_number'] ?? '',
            $appt['position_type'] ?? '',
            $appt['position_name'] ?? ''
        );
        if ($normalizedType !== '') {
            $approved_competency_type_options[$normalizedType] = $normalizedType;
        }
    }
    ksort($approved_competency_type_options);
    $approved_appointments->data_seek(0);
}

// Get detailed rejected appointments
$rejected_appointments = $db->query("
    SELECT a.*, e.full_name as employee_name, e.employee_code, e.position as employee_position, e.contractor_company, e.ruang_lingkup, e.supervision_area,
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
           e.verified_date,
           (SELECT p.position_type FROM appointments a
            JOIN positions p ON a.position_id = p.id
            WHERE a.employee_id = e.id
            ORDER BY a.created_at DESC LIMIT 1) as position_type,
           (SELECT p.position_name FROM appointments a
            JOIN positions p ON a.position_id = p.id
            WHERE a.employee_id = e.id
            ORDER BY a.created_at DESC LIMIT 1) as position_name,
           (SELECT a2.appointment_number FROM appointments a2
            WHERE a2.employee_id = e.id
            ORDER BY a2.created_at DESC LIMIT 1) as appointment_number
    FROM employees e
    LEFT JOIN users u ON e.verified_by = u.id
    WHERE e.verification_status = 'verified'
    ORDER BY e.contractor_company, e.updated_at DESC
");

// Get rejected requests (rejected employees with notes)
$rejected_requests = $db->query("
    SELECT e.*, e.updated_at as verification_date,
           u.full_name as rejected_by_name,
           e.verified_date,
           (SELECT p.position_type FROM appointments a
            JOIN positions p ON a.position_id = p.id
            WHERE a.employee_id = e.id
            ORDER BY a.created_at DESC LIMIT 1) as position_type,
           (SELECT p.position_name FROM appointments a
            JOIN positions p ON a.position_id = p.id
            WHERE a.employee_id = e.id
            ORDER BY a.created_at DESC LIMIT 1) as position_name,
           (SELECT a2.appointment_number FROM appointments a2
            WHERE a2.employee_id = e.id
            ORDER BY a2.created_at DESC LIMIT 1) as appointment_number
    FROM employees e
    LEFT JOIN users u ON e.verified_by = u.id
    WHERE e.verification_status = 'rejected'
    ORDER BY e.contractor_company, e.updated_at DESC
");

// Get waiting for approval requests (pending employees)
$waiting_requests = $db->query("
    SELECT e.*,
           (SELECT p.position_type FROM appointments a
            JOIN positions p ON a.position_id = p.id
            WHERE a.employee_id = e.id
            ORDER BY a.created_at DESC LIMIT 1) as position_type,
           (SELECT p.position_name FROM appointments a
            JOIN positions p ON a.position_id = p.id
            WHERE a.employee_id = e.id
            ORDER BY a.created_at DESC LIMIT 1) as position_name,
           (SELECT a2.appointment_number FROM appointments a2
            WHERE a2.employee_id = e.id
            ORDER BY a2.created_at DESC LIMIT 1) as appointment_number
    FROM employees e
    WHERE e.verification_status = 'pending'
    ORDER BY e.contractor_company, e.created_at DESC
");

// Get request statistics
$accepted_requests_count = $accepted_requests ? $accepted_requests->num_rows : 0;
$rejected_requests_count = $rejected_requests ? $rejected_requests->num_rows : 0;
$waiting_requests_count = $waiting_requests ? $waiting_requests->num_rows : 0;

$accepted_request_competency_type_options = [];
if ($accepted_requests && $accepted_requests->num_rows > 0) {
    $accepted_requests->data_seek(0);
    while ($req = $accepted_requests->fetch_assoc()) {
        $competencyType = normalizeCompetencyTypeLabel(
            $req['appointment_number'] ?? '',
            $req['position_type'] ?? '',
            $req['position_name'] ?? ''
        );
        if ($competencyType !== '') {
            $accepted_request_competency_type_options[$competencyType] = $competencyType;
        }
    }
    ksort($accepted_request_competency_type_options);
    $accepted_requests->data_seek(0);
}

$rejected_request_competency_type_options = [];
if ($rejected_requests && $rejected_requests->num_rows > 0) {
    $rejected_requests->data_seek(0);
    while ($req = $rejected_requests->fetch_assoc()) {
        $competencyType = normalizeCompetencyTypeLabel(
            $req['appointment_number'] ?? '',
            $req['position_type'] ?? '',
            $req['position_name'] ?? ''
        );
        if ($competencyType !== '') {
            $rejected_request_competency_type_options[$competencyType] = $competencyType;
        }
    }
    ksort($rejected_request_competency_type_options);
    $rejected_requests->data_seek(0);
}

$waiting_request_competency_type_options = [];
if ($waiting_requests && $waiting_requests->num_rows > 0) {
    $waiting_requests->data_seek(0);
    while ($req = $waiting_requests->fetch_assoc()) {
        $competencyType = normalizeCompetencyTypeLabel(
            $req['appointment_number'] ?? '',
            $req['position_type'] ?? '',
            $req['position_name'] ?? ''
        );
        if ($competencyType !== '') {
            $waiting_request_competency_type_options[$competencyType] = $competencyType;
        }
    }
    ksort($waiting_request_competency_type_options);
    $waiting_requests->data_seek(0);
}

$rejected_appointment_competency_type_options = [];
if ($rejected_appointments && $rejected_appointments->num_rows > 0) {
    $rejected_appointments->data_seek(0);
    while ($appt = $rejected_appointments->fetch_assoc()) {
        $competencyType = sanitizeCompetencyTypeValue($appt['position_type'] ?? '');
        if ($competencyType !== '') {
            $rejected_appointment_competency_type_options[$competencyType] = $competencyType;
        }
    }
    ksort($rejected_appointment_competency_type_options);
    $rejected_appointments->data_seek(0);
}

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
        DATEDIFF(ec.expiry_date, CURDATE()) as days_until_expiry,
        (SELECT p.position_type FROM appointments a
         JOIN positions p ON a.position_id = p.id
         WHERE a.employee_id = e.id
         ORDER BY a.created_at DESC LIMIT 1) as position_type
    FROM employee_certifications ec
    JOIN employees e ON ec.employee_id = e.id
    LEFT JOIN certifications c ON ec.certification_id = c.id
    WHERE ec.expiry_date IS NOT NULL
    AND ec.verification_status = 'verified'
    AND ec.expiry_date <= DATE_ADD(CURDATE(), INTERVAL 2 MONTH)
    AND e.is_active = 1
    ORDER BY ec.expiry_date ASC, e.contractor_company, e.full_name
");

$expiring_certs_count = $expiring_certs ? $expiring_certs->num_rows : 0;

$expiring_cert_competency_type_options = [];
if ($expiring_certs && $expiring_certs->num_rows > 0) {
    $expiring_certs->data_seek(0);
    while ($cert = $expiring_certs->fetch_assoc()) {
        $competencyType = sanitizeCompetencyTypeValue($cert['position_type'] ?? '');
        if ($competencyType !== '') {
            $expiring_cert_competency_type_options[$competencyType] = $competencyType;
        }
    }
    ksort($expiring_cert_competency_type_options);
    $expiring_certs->data_seek(0);
}

require_once '../../includes/header.php';

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
            <h2><i class="fas fa-chart-bar"></i> <span data-lang="assign-letter-report">Laporan Surat Penunjukan</span></h2>
            <p data-lang="report-summary">Ringkasan dan detail hasil proses surat penunjukan</p>
        </div>
        <div class="header-date">
            <i class="fas fa-calendar-alt"></i> <?php echo date('d F Y'); ?>
        </div>
    </div>
    
    <!-- Stats Row � unified -->
    <?php $total_requests_processed = $accepted_requests_count + $rejected_requests_count + $waiting_requests_count; ?>
    <div class="stats-grid-reports">
        <!-- Assign Letter stats -->
        <div class="stat-card-report stat-total">
            <div class="stat-icon-report"><i class="fas fa-file-alt"></i></div>
            <div class="stat-content-report">
                <h3><?php echo $total_processed; ?></h3>
                <p data-lang="assign-letters">Surat Penunjukan</p>
            </div>
        </div>
        <div class="stat-card-report stat-approved">
            <div class="stat-icon-report"><i class="fas fa-check-circle"></i></div>
            <div class="stat-content-report">
                <h3><?php echo $approved_total; ?></h3>
                <p data-lang="al-accepted">SP Disetujui</p>
            </div>
        </div>
        <div class="stat-card-report stat-rejected">
            <div class="stat-icon-report"><i class="fas fa-times-circle"></i></div>
            <div class="stat-content-report">
                <h3><?php echo $rejected_total; ?></h3>
                <p data-lang="al-rejected">SP Tidak Disetujui</p>
            </div>
        </div>

        <!-- Separator -->
        <div class="stat-sep"></div>

        <!-- Employee Request stats -->
        <div class="stat-card-report stat-total">
            <div class="stat-icon-report"><i class="fas fa-clipboard-list"></i></div>
            <div class="stat-content-report">
                <h3><?php echo $total_requests_processed; ?></h3>
                <p data-lang="requests">Permohonan</p>
            </div>
        </div>
        <div class="stat-card-report stat-approved">
            <div class="stat-icon-report"><i class="fas fa-user-check"></i></div>
            <div class="stat-content-report">
                <h3><?php echo $accepted_requests_count; ?></h3>
                <p data-lang="req-accepted">Permohonan Disetujui</p>
            </div>
        </div>
        <div class="stat-card-report stat-rejected">
            <div class="stat-icon-report"><i class="fas fa-user-times"></i></div>
            <div class="stat-content-report">
                <h3><?php echo $rejected_requests_count; ?></h3>
                <p data-lang="req-rejected">Permohonan Tidak Disetujui</p>
            </div>
        </div>
        <div class="stat-card-report stat-pending">
            <div class="stat-icon-report"><i class="fas fa-clock"></i></div>
            <div class="stat-content-report">
                <h3><?php echo $waiting_requests_count; ?></h3>
                <p data-lang="waiting">Menunggu</p>
            </div>
        </div>
    </div>

    <!-- Quick Navigation Shortcuts -->
    <div class="quick-nav-bar">
        <div class="quick-nav-label"><i class="fas fa-map-signs"></i> <span data-lang="jump-to-section">Lompat ke Bagian:</span></div>
        <div class="quick-nav-links">
            <?php if ($accepted_requests && $accepted_requests->num_rows > 0): ?>
            <a href="#section-accepted-req" class="quick-nav-btn nav-accepted" onclick="return jumpToReportSection('acceptedRequestSection', 'acceptedRequestTable', 'btnAcceptedReq', event)">
                 <span data-lang="accepted-requests">Permohonan Disetujui</span>
                <span class="nav-badge"><?php echo $accepted_requests_count; ?></span>
            </a>
            <?php endif; ?>
            <?php if ($rejected_requests && $rejected_requests->num_rows > 0): ?>
            <a href="#section-rejected-req" class="quick-nav-btn nav-rejected" onclick="return jumpToReportSection('rejectedRequestSection', 'rejectedRequestTable', 'btnRejectedReq', event)">
                 <span data-lang="rejected-requests">Permohonan Tidak Disetujui</span>
                <span class="nav-badge"><?php echo $rejected_requests_count; ?></span>
            </a>
            <?php endif; ?>
            <?php if ($waiting_requests && $waiting_requests->num_rows > 0): ?>
            <a href="#section-waiting-req" class="quick-nav-btn nav-warning" onclick="return jumpToReportSection('waitingRequestSection', 'waitingRequestTable', 'btnWaitingReq', event)">
                <span data-lang="waiting-approval">Menunggu Persetujuan</span>
                <span class="nav-badge"><?php echo $waiting_requests_count; ?></span>
            </a>
            <?php endif; ?>
            <?php if ($approved_appointments && $approved_appointments->num_rows > 0): ?>
            <a href="#section-accepted-assign" class="quick-nav-btn nav-accepted" onclick="return jumpToReportSection('approvedAppointmentSection', 'approvedTable', 'btnApprovedAppt', event)">
                <i class="fas fa-check-circle"></i> <span data-lang="accepted-assign-letters">Surat Penunjukan Disetujui</span>
                <span class="nav-badge"><?php echo $approved_total; ?></span>
            </a>
            <?php endif; ?>
            <?php if ($rejected_appointments && $rejected_appointments->num_rows > 0): ?>
            <a href="#section-rejected-assign" class="quick-nav-btn nav-rejected" onclick="return jumpToReportSection('rejectedAppointmentSection', 'rejectedTable', 'btnRejectedAppt', event)">
                <i class="fas fa-times-circle"></i> Surat Penunjukan Tidak Disetujui
                <span class="nav-badge"><?php echo $rejected_total; ?></span>
            </a>
            <?php endif; ?>
            <?php if ($expiring_certs && $expiring_certs->num_rows > 0): ?>
            <a href="#certificate-expiration" class="quick-nav-btn nav-warning" onclick="return jumpToReportSection('certificate-expiration', 'expiringCertsTable', null, event)">
                <i class="fas fa-exclamation-triangle"></i> Sertifikat Kedaluwarsa
                <span class="nav-badge"><?php echo $expiring_certs_count; ?></span>
            </a>
            <?php endif; ?>
            
        </div>
    </div>

    <!-- Summary by Company -->
    <div class="card-report" id="section-summary">
        <div class="card-header-report">
            <h3><i class="fas fa-building"></i> <span data-lang="company-summary">Ringkasan per Perusahaan</span></h3>
        </div>
        <div class="card-body-report">
            <?php if ($report_data && $report_data->num_rows > 0): ?>
                <div class="table-responsive">
                    <table class="table-report">
                        <thead>
                            <tr>
                                <th class="col-num">#</th>
                                <th class="col-company">Perusahaan</th>
                                <th class="col-approved">Disetujui</th>
                                <th class="col-rejected">Tidak Disetujui</th>
                                <th class="col-total">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $report_data->data_seek(0);
                            $row_num = 1;
                            while ($row = $report_data->fetch_assoc()):
                            ?>
                            <tr class="report-row">
                                <td class="col-num"><?php echo $row_num++; ?></td>
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
                    <p>Belum ada data surat penunjukan yang diproses</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Accepted Requests -->
    <?php if ($accepted_requests && $accepted_requests->num_rows > 0): ?>
    <div class="card-report" id="section-accepted-req">
        <div class="card-header-report">
            <div class="card-hd-left">
                <h3> Permohonan Disetujui</h3>
                <span class="badge-header"><?php echo $accepted_requests->num_rows; ?></span>
            </div>
            <button onclick="toggleSection('acceptedRequestSection')" class="btn-toggle-section" id="btnAcceptedReq">
                <span class="btn-toggle-text">Lihat Semua</span> <i class="fas fa-chevron-down"></i>
            </button>
        </div>

        <div id="acceptedRequestSection" class="section-content" style="display: none; opacity: 0; max-height: 0;">
        <!-- Filter by Company and Work Scope -->
        <div class="filter-section-report">
            <div class="filter-group-report">
                <label><i class="fas fa-building"></i> Filter Perusahaan:</label>
                <select id="companyFilterAcceptedReq" class="filter-select-report" onchange="filterRequestTable('acceptedRequestTable')">
                    <option value="">-- Semua Perusahaan --</option>
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
                <label><i class="fas fa-map-marker-alt"></i> Ruang Lingkup:</label>
                <select id="scopeFilterAcceptedReq" class="filter-select-report" onchange="filterRequestTable('acceptedRequestTable')">
                    <option value="">-- Semua Ruang Lingkup --</option>
                    <option value="MSM">PT MSM</option>
                    <option value="TTN">PT TTN</option>
                </select>
            </div>
            <div class="filter-group-report">
                <label><i class="fas fa-award"></i> Jenis Kompetensi:</label>
                <select id="competencyFilterAcceptedReq" class="filter-select-report" onchange="filterRequestTable('acceptedRequestTable')">
                    <option value="">-- Semua Jenis --</option>
                    <?php foreach ($accepted_request_competency_type_options as $competencyType): ?>
                    <option value="<?php echo htmlspecialchars($competencyType); ?>">
                        <?php echo htmlspecialchars($competencyType); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-action-group">
                <button class="btn btn-export-small" onclick="exportToExcel('acceptedRequestTable', 'Accepted_Requests_Report')">
                    <i class="fas fa-file-excel"></i> Ekspor ke Excel
                </button>
            </div>
        </div>

        <div class="card-body-report">
            <div class="table-responsive">
                <table class="table-report" id="acceptedRequestTable">
                    <thead>
                        <tr>
                            <th class="col-num">#</th>
                            <th class="col-company-detail">Perusahaan</th>
                            <th class="col-employee">Karyawan</th>
                            <th class="col-date">Tanggal Verifikasi</th>
                            <th class="col-approved-info">Diverifikasi Oleh</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $accepted_requests->data_seek(0);
                        $row_num = 1;
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
                                    $competency_normalized = normalizeCompetencyTypeLabel(
                                        $row['appointment_number'] ?? '',
                                        $row['position_type'] ?? '',
                                        $row['position_name'] ?? ''
                                    );
                        ?>
                        <tr class="detail-row" data-company="<?php echo $company_name; ?>" data-scope="<?php echo $scope_normalized; ?>" data-supervision="<?php echo $supervision_area; ?>" data-competency="<?php echo htmlspecialchars($competency_normalized); ?>">
                            <td class="col-num"><?php echo $row_num++; ?></td>
                            <td class="col-company-detail">
                                <span class="company-tag"><?php echo $company_name; ?></span>
                            </td>
                            <td class="col-employee">
                                <div class="employee-detail">
                                    <strong><?php echo htmlspecialchars($row['full_name']); ?></strong>
                                    <span class="emp-code-detail"><?php echo htmlspecialchars($row['employee_code']); ?></span>
                                </div>
                            </td>
                            <td class="col-date">
                                <?php if ($row['verification_date']): ?>
                                <?php echo date('d/m/Y H:i', strtotime($row['verification_date'])); ?>
                                <?php else: ?>
                                <span class="text-muted">�</span>
                                <?php endif; ?>
                            </td>
                            <td class="col-approved-info">
                                <?php
                                if (($row['verification_status'] == 'verified' || $row['verification_status'] == 'rejected') && $row['verified_by_name']) {
                                    echo '<span class="approver-badge">';
                                    echo htmlspecialchars($row['verified_by_name']);
                                    echo '</span>';
                                    if ($row['verified_date']) {
                                        echo '<div class="approval-datetime">' . date('d/m/Y', strtotime($row['verified_date'])) . '</div>';
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
                Menampilkan semua data
            </div>
        </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Rejected Requests -->
    <?php if ($rejected_requests && $rejected_requests->num_rows > 0): ?>
    <div class="card-report" id="section-rejected-req">
        <div class="card-header-report">
            <div class="card-hd-left">
                <h3> Permohonan Tidak Disetujui</h3>
                <span class="badge-header rejected"><?php echo $rejected_requests->num_rows; ?></span>
            </div>
            <button onclick="toggleSection('rejectedRequestSection')" class="btn-toggle-section" id="btnRejectedReq">
                <span class="btn-toggle-text">Lihat Semua</span> <i class="fas fa-chevron-down"></i>
            </button>
        </div>

        <div id="rejectedRequestSection" class="section-content" style="display: none; opacity: 0; max-height: 0;">
        <!-- Filter by Company and Work Scope -->
        <div class="filter-section-report">
            <div class="filter-group-report">
                <label><i class="fas fa-building"></i> Filter Perusahaan:</label>
                <select id="companyFilterRejectedReq" class="filter-select-report" onchange="filterRequestTable('rejectedRequestTable')">
                    <option value="">-- Semua Perusahaan --</option>
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
                <label><i class="fas fa-map-marker-alt"></i> Ruang Lingkup:</label>
                <select id="scopeFilterRejectedReq" class="filter-select-report" onchange="filterRequestTable('rejectedRequestTable')">
                    <option value="">-- Semua Ruang Lingkup --</option>
                    <option value="MSM">PT MSM</option>
                    <option value="TTN">PT TTN</option>
                </select>
            </div>
            <div class="filter-group-report">
                <label><i class="fas fa-award"></i> Jenis Kompetensi:</label>
                <select id="competencyFilterRejectedReq" class="filter-select-report" onchange="filterRequestTable('rejectedRequestTable')">
                    <option value="">-- Semua Jenis --</option>
                    <?php foreach ($rejected_request_competency_type_options as $competencyType): ?>
                    <option value="<?php echo htmlspecialchars($competencyType); ?>">
                        <?php echo htmlspecialchars($competencyType); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-action-group">
                <button class="btn btn-export-small" onclick="exportToExcel('rejectedRequestTable', 'Rejected_Requests_Report')">
                    <i class="fas fa-file-excel"></i> Ekspor ke Excel
                </button>
            </div>
        </div>

        <div class="card-body-report">
            <div class="table-responsive">
                <table class="table-report" id="rejectedRequestTable">
                    <thead>
                        <tr>
                            <th class="col-num">#</th>
                            <th class="col-company-detail">Perusahaan</th>
                            <th class="col-employee">Karyawan</th>
                            <th class="col-date">Tanggal Penolakan</th>
                            <th class="col-rejected-by">Tidak Disetujui Oleh</th>
                            <th class="col-notes">Catatan</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $rejected_requests->data_seek(0);
                        $row_num = 1;
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
                                    $competency_normalized = normalizeCompetencyTypeLabel(
                                        $row['appointment_number'] ?? '',
                                        $row['position_type'] ?? '',
                                        $row['position_name'] ?? ''
                                    );
                        ?>
                        <tr class="detail-row rejected-row" data-company="<?php echo $company_name; ?>" data-scope="<?php echo $scope_normalized; ?>" data-supervision="<?php echo $supervision_area; ?>" data-competency="<?php echo htmlspecialchars($competency_normalized); ?>">
                            <td class="col-num"><?php echo $row_num++; ?></td>
                            <td class="col-company-detail">
                                <span class="company-tag"><?php echo $company_name; ?></span>
                            </td>
                            <td class="col-employee">
                                <div class="employee-detail">
                                    <strong><?php echo htmlspecialchars($row['full_name']); ?></strong>
                                    <span class="emp-code-detail"><?php echo htmlspecialchars($row['employee_code']); ?></span>
                                </div>
                            </td>
                            <td class="col-date">
                                <?php if ($row['verification_date']): ?>
                                <?php echo date('d/m/Y H:i', strtotime($row['verification_date'])); ?>
                                <?php else: ?>
                                <span class="text-muted">�</span>
                                <?php endif; ?>
                            </td>
                            <td class="col-rejected-by">
                                <?php
                                if (($row['verification_status'] == 'verified' || $row['verification_status'] == 'rejected') && $row['rejected_by_name']) {
                                    echo '<span class="rejector-badge">' . htmlspecialchars($row['rejected_by_name']) . '</span>';
                                    if ($row['verified_date']) {
                                        echo '<div class="approval-datetime">' . date('d/m/Y', strtotime($row['verified_date'])) . '</div>';
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
                                <span class="text-muted">Tidak ada catatan</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
            <div class="table-info-report" id="rejectedRequestTableInfo">
                Menampilkan semua data
            </div>
        </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Waiting for Approval Requests -->
    <?php if ($waiting_requests && $waiting_requests->num_rows > 0): ?>
    <div class="card-report" id="section-waiting-req">
        <div class="card-header-report">
            <div class="card-hd-left">
                <h3>Permohonan Menunggu Persetujuan</h3>
                <span class="badge-header warning"><?php echo $waiting_requests->num_rows; ?></span>
            </div>
            <button onclick="toggleSection('waitingRequestSection')" class="btn-toggle-section" id="btnWaitingReq">
                <span class="btn-toggle-text">Lihat Semua</span> <i class="fas fa-chevron-down"></i>
            </button>
        </div>

        <div id="waitingRequestSection" class="section-content" style="display: none; opacity: 0; max-height: 0;">
        <!-- Filter by Company and Work Scope -->
        <div class="filter-section-report">
            <div class="filter-group-report">
                <label><i class="fas fa-building"></i> Filter Perusahaan:</label>
                <select id="companyFilterWaitingReq" class="filter-select-report" onchange="filterRequestTable('waitingRequestTable')">
                    <option value="">-- Semua Perusahaan --</option>
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
                <label><i class="fas fa-map-marker-alt"></i> Ruang Lingkup:</label>
                <select id="scopeFilterWaitingReq" class="filter-select-report" onchange="filterRequestTable('waitingRequestTable')">
                    <option value="">-- Semua Ruang Lingkup --</option>
                    <option value="MSM">PT MSM</option>
                    <option value="TTN">PT TTN</option>
                </select>
            </div>
            <div class="filter-group-report">
                <label><i class="fas fa-award"></i> Jenis Kompetensi:</label>
                <select id="competencyFilterWaitingReq" class="filter-select-report" onchange="filterRequestTable('waitingRequestTable')">
                    <option value="">-- Semua Jenis --</option>
                    <?php foreach ($waiting_request_competency_type_options as $competencyType): ?>
                    <option value="<?php echo htmlspecialchars($competencyType); ?>">
                        <?php echo htmlspecialchars($competencyType); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-action-group">
                <button class="btn btn-export-small" onclick="exportToExcel('waitingRequestTable', 'Waiting_Requests_Report')">
                    <i class="fas fa-file-excel"></i> Ekspor ke Excel
                </button>
            </div>
        </div>

        <div class="card-body-report">
            <div class="table-responsive">
                <table class="table-report" id="waitingRequestTable">
                    <thead>
                        <tr>
                            <th class="col-num">#</th>
                            <th class="col-company-detail">Perusahaan</th>
                            <th class="col-employee">Karyawan</th>
                            <th class="col-date">Tanggal Permohonan</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $waiting_requests->data_seek(0);
                        $row_num = 1;
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
                                    $competency_normalized = normalizeCompetencyTypeLabel(
                                        $row['appointment_number'] ?? '',
                                        $row['position_type'] ?? '',
                                        $row['position_name'] ?? ''
                                    );
                        ?>
                        <tr class="detail-row" data-company="<?php echo $company_name; ?>" data-scope="<?php echo $scope_normalized; ?>" data-supervision="<?php echo $supervision_area; ?>" data-competency="<?php echo htmlspecialchars($competency_normalized); ?>">
                            <td class="col-num"><?php echo $row_num++; ?></td>
                            <td class="col-company-detail">
                                <span class="company-tag"><?php echo $company_name; ?></span>
                            </td>
                            <td class="col-employee">
                                <div class="employee-detail">
                                    <strong><?php echo htmlspecialchars($row['full_name']); ?></strong>
                                    <span class="emp-code-detail"><?php echo htmlspecialchars($row['employee_code']); ?></span>
                                </div>
                            </td>
                            <td class="col-date">
                                <?php if ($row['created_at']): ?>
                                <?php echo date('d/m/Y H:i', strtotime($row['created_at'])); ?>
                                <?php else: ?>
                                <span class="text-muted">�</span>
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
    <div class="card-report" id="section-accepted-assign">
        <div class="card-header-report">
            <div class="card-hd-left">
                <h3>Detail Surat Penunjukan Disetujui</h3>
                <span class="badge-header"><?php echo $approved_appointments->num_rows; ?></span>
            </div>
            <button onclick="toggleSection('approvedAppointmentSection')" class="btn-toggle-section" id="btnApprovedAppt">
                <span class="btn-toggle-text">Lihat Semua</span> <i class="fas fa-chevron-down"></i>
            </button>
        </div>

        <div id="approvedAppointmentSection" class="section-content" style="display: none; opacity: 0; max-height: 0;">
        <!-- Export buttons -->
        <div class="filter-section-report">
            <div class="filter-action-group" style="margin-left: auto;">
                <button class="btn btn-export-small" onclick="exportToExcel('approvedTable', 'Accepted_Assign_Letters_Report')">
                    <i class="fas fa-file-excel"></i> Ekspor ke Excel
                </button>
                <button class="btn btn-export-pdf" onclick="exportApprovedByCompany()">
                    <i class="fas fa-file-pdf"></i> Ekspor PDF
                </button>
            </div>
        </div>
        
        <div class="card-body-report">
            <div class="table-responsive">
                <table class="table-report table-approved table-with-filters" id="approvedTable">
                    <thead>
                        <tr class="header-row">
                            <th class="col-num">No</th>
                            <th class="col-number">Nomor Surat Penunjukan</th>
                            <th class="col-publisher">Penerbit</th>
                            <th class="col-badge">ID BADGE</th>
                            <th class="col-employee">Nama</th>
                            <th class="col-position">Jabatan</th>
                            <th class="col-company-detail">Perusahaan</th>
                            <th class="col-date">Tanggal Kedaluwarsa</th>
                        </tr>
                        <tr class="filter-row">
                            <th class="col-num"></th>
                            <th class="col-number">
                                <div class="filter-with-sort">
                                    <select class="column-filter" data-column="1" data-filter-type="contains" onchange="filterApprovedTable()">
                                        <option value="">Semua</option>
                                        <option value="MSM">MSM</option>
                                        <option value="TTN">TTN</option>
                                    </select>
                                    <button type="button" class="sort-btn" data-column="1" onclick="sortApprovedTableByColumn(1)">
                                        <i class="fas fa-sort"></i>
                                    </button>
                                </div>
                            </th>
                            <th class="col-publisher">
                                <select class="column-filter" data-column="2" onchange="filterApprovedTable()">
                                    <option value="">Semua</option>
                                </select>
                            </th>
                            <th class="col-badge">
                                <input type="text" class="column-filter" data-column="3" placeholder="Filter..." data-lang-placeholder="filter-placeholder" onkeyup="filterApprovedTable()">
                            </th>
                            <th class="col-employee">
                                <div class="filter-with-sort">
                                    <input type="text" class="column-filter" data-column="4" placeholder="Filter..." data-lang-placeholder="filter-placeholder" onkeyup="filterApprovedTable()">
                                    <button type="button" class="sort-btn" data-column="4" onclick="sortApprovedTableByColumn(4)">
                                        <i class="fas fa-sort"></i>
                                    </button>
                                </div>
                            </th>
                            <th class="col-position">
                                <select class="column-filter" data-column="5" onchange="filterApprovedTable()">
                                        <option value="">Semua</option>
                                </select>
                            </th>
                            <th class="col-company-detail">
                                <select class="column-filter" data-column="6" onchange="filterApprovedTable()">
                                    <option value="">Semua</option>
                                    <?php
                                    $companies->data_seek(0);
                                    while ($comp = $companies->fetch_assoc()):
                                        $compName = $comp['contractor_company'] ?: 'Unknown';
                                    ?>
                                    <option value="<?php echo strtolower(htmlspecialchars($compName)); ?>">
                                        <?php echo htmlspecialchars($compName); ?>
                                    </option>
                                    <?php endwhile; ?>
                                </select>
                            </th>
                            <th class="col-date">
                                <button type="button" class="sort-btn sort-btn-full" data-column="7" onclick="sortApprovedTableByDate(7)">
                                    <i class="fas fa-sort"></i> Sort
                                </button>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $approved_appointments->data_seek(0);
                        $row_num = 1;
                        while ($row = $approved_appointments->fetch_assoc()):
                            $company_name = htmlspecialchars($row['contractor_company'] ?: 'Unknown');
                            $supervision_area = htmlspecialchars($row['supervision_area'] ?: '');
                            $competency_type_normalized = normalizeCompetencyTypeLabel(
                                $row['appointment_number'] ?? '',
                                $row['position_type'] ?? '',
                                $row['position_name'] ?? ''
                            );
                            $position_display = trim((string)($row['employee_position'] ?? ''));
                            if ($position_display === '') {
                                $position_display = trim((string)($row['position_name'] ?? ''));
                            }
                            // Normalize ruang_lingkup to MSM or TTN for filtering
                            $scope_raw = $row['ruang_lingkup'] ?: '';
                            $scope_normalized = '';
                            if (stripos($scope_raw, 'MSM') !== false || stripos($scope_raw, 'Meares Soputan') !== false) {
                                $scope_normalized = 'MSM';
                            } elseif (stripos($scope_raw, 'TTN') !== false || stripos($scope_raw, 'Tondano Nusajaya') !== false) {
                                $scope_normalized = 'TTN';
                            }
                            // Publisher from cert_issuer
                            $publisher_display = !empty($row['cert_issuer']) ? htmlspecialchars($row['cert_issuer']) : '-';
                            // Format expiry date
                            $expiry_date_display = '-';
                            if (!empty($row['expiry_date']) && $row['expiry_date'] != '0000-00-00') {
                                $expiry_date_display = date('d/m/Y', strtotime($row['expiry_date']));
                            }
                        ?>
                        <tr class="detail-row"
                            data-company="<?php echo htmlspecialchars($row['contractor_company'] ?: 'Unknown'); ?>"
                            data-scope="<?php echo $scope_normalized; ?>"
                            data-supervision="<?php echo $supervision_area; ?>"
                            data-competency="<?php echo htmlspecialchars($competency_type_normalized); ?>"
                            data-letter-number="<?php echo htmlspecialchars($row['appointment_number'] ?? ''); ?>"
                            data-employee-name="<?php echo htmlspecialchars($row['employee_name'] ?? ''); ?>"
                            data-employee-code="<?php echo htmlspecialchars($row['employee_code'] ?? ''); ?>"
                            data-position="<?php echo htmlspecialchars($position_display); ?>"
                            data-department="<?php echo htmlspecialchars($row['department'] ?? ''); ?>"
                            data-status="approved"
                            data-expiry-date="<?php echo htmlspecialchars($expiry_date_display); ?>"
                            data-publisher="<?php echo htmlspecialchars(!empty($row['cert_issuer']) ? $row['cert_issuer'] : '-'); ?>">
                            <td class="col-num"><?php echo $row_num++; ?></td>
                            <td class="col-number">
                                <strong><?php echo htmlspecialchars($row['appointment_number']); ?></strong>
                            </td>
                            <td class="col-publisher">
                                <span class="publisher-tag"><?php echo $publisher_display; ?></span>
                            </td>
                            <td class="col-badge">
                                <?php echo htmlspecialchars($row['employee_code']); ?>
                            </td>
                            <td class="col-employee">
                                <?php echo htmlspecialchars($row['employee_name']); ?>
                            </td>
                            <td class="col-position">
                                <span class="position-badge-report"><?php echo htmlspecialchars($position_display); ?></span>
                            </td>
                            <td class="col-company-detail">
                                <span class="company-tag"><?php echo $company_name; ?></span>
                            </td>
                            <td class="col-date">
                                <?php echo $expiry_date_display; ?>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
            <div class="table-info-report" id="approvedTableInfo">
                Menampilkan semua perusahaan
            </div>
        </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Rejected Appointments -->
    <?php if ($rejected_appointments && $rejected_appointments->num_rows > 0): ?>
    <div class="card-report" id="section-rejected-assign">
        <div class="card-header-report">
            <div class="card-hd-left">
                <h3> Detail Surat Penunjukan Tidak Disetujui</h3>
                <span class="badge-header rejected"><?php echo $rejected_appointments->num_rows; ?></span>
            </div>
            <button onclick="toggleSection('rejectedAppointmentSection')" class="btn-toggle-section" id="btnRejectedAppt">
                <span class="btn-toggle-text">Lihat Semua</span> <i class="fas fa-chevron-down"></i>
            </button>
        </div>

        <div id="rejectedAppointmentSection" class="section-content" style="display: none; opacity: 0; max-height: 0;">
        <!-- Filter by Company and Ruang Lingkup -->
        <div class="filter-section-report">
            <div class="filter-group-report">
                <label><i class="fas fa-building"></i> Filter Perusahaan:</label>
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
                <label><i class="fas fa-map-marker-alt"></i> Ruang Lingkup:</label>
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
            <div class="filter-group-report">
                <label><i class="fas fa-award"></i> Jenis Kompetensi:</label>
                <select id="competencyFilterRejected" class="filter-select-report" onchange="filterTableByFilters('rejectedTable')">
                    <option value="">-- All Types --</option>
                    <?php foreach ($rejected_appointment_competency_type_options as $competencyType): ?>
                    <option value="<?php echo htmlspecialchars($competencyType); ?>">
                        <?php echo htmlspecialchars($competencyType); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-action-group">
                <button class="btn btn-export-small" onclick="exportToExcel('rejectedTable', 'Rejected_Assign_Letters_Report')">
                    <i class="fas fa-file-excel"></i> Ekspor ke Excel
                </button>
            </div>
        </div>
        
        <div class="card-body-report">
            <div class="table-responsive">
                <table class="table-report table-rejected" id="rejectedTable">
                    <thead>
                        <tr>
                            <th class="col-num">#</th>
                            <th class="col-number">Assign Letter No.</th>
                            <th class="col-company-detail">Perusahaan</th>
                            <th class="col-employee">Karyawan</th>
                            <th class="col-position">Position</th>
                            <th class="col-rejected-date">Rejected Date</th>
                            <th class="col-rejected-by">Rejected By</th>
                            <th class="col-action">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $rejected_appointments->data_seek(0);
                        $row_num = 1;
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
                        <tr class="detail-row rejected-row" data-company="<?php echo $company_name; ?>" data-scope="<?php echo $scope_normalized; ?>" data-supervision="<?php echo $supervision_area; ?>" data-competency="<?php echo htmlspecialchars(normalizeCompetencyTypeLabel($row['appointment_number'] ?? '', $row['position_type'] ?? '', $row['position_name'] ?? '')); ?>">
                            <td class="col-num"><?php echo $row_num++; ?></td>
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
                                <?php echo date('d/m/Y H:i', strtotime($row['approved_date'])); ?>
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
                Menampilkan semua perusahaan
            </div>
        </div>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Certificate Expiration Report -->
    <?php if ($expiring_certs && $expiring_certs->num_rows > 0): ?>
    <div class="card-report" id="certificate-expiration">
        <div class="card-header-report">
            <div class="card-hd-left">
                <h3> Sertifikat Kedaluwarsa (<= 2 Bulan)</h3>
                <span class="badge-header warning"><?php echo $expiring_certs->num_rows; ?></span>
            </div>
        </div>

        <!-- Filter by Company -->
        <div class="filter-section-report">
            <div class="filter-group-report">
                <label><i class="fas fa-building"></i> Filter Perusahaan:</label>
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
            <div class="filter-group-report">
                <label><i class="fas fa-award"></i> Jenis Kompetensi:</label>
                <select id="competencyFilterExpiring" class="filter-select-report" onchange="filterExpiringCerts()">
                    <option value="">-- All Types --</option>
                    <?php foreach ($expiring_cert_competency_type_options as $competencyType): ?>
                    <option value="<?php echo htmlspecialchars($competencyType); ?>">
                        <?php echo htmlspecialchars($competencyType); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-action-group">
                <button class="btn btn-export-small" onclick="exportToExcel('expiringCertsTable', 'Expiring_Certificates_Report')">
                    <i class="fas fa-file-excel"></i> Ekspor ke Excel
                </button>
                <button class="btn btn-export-pdf" onclick="exportExpiringCerts()">
                    <i class="fas fa-file-pdf"></i> Ekspor PDF
                </button>
            </div>
        </div>
        
        <div class="card-body-report">
            <div class="table-responsive">
                <table class="table-report table-expiring" id="expiringCertsTable">
                    <thead>
                        <tr>
                            <th class="col-num">#</th>
                            <th class="col-company-detail">Perusahaan</th>
                            <th class="col-employee">Karyawan</th>
                            <th class="col-cert-name">Nama Sertifikat</th>
                            <th class="col-cert-number">Nomor Sertifikat</th>
                            <th class="col-expiry-date">Tanggal Kedaluwarsa</th>
                            <th class="col-days-left">Sisa Hari</th>
                            <th class="col-status-expiry">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $expiring_certs->data_seek(0);
                        $row_num = 1;
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
                        <tr class="detail-row expiring-row" data-company="<?php echo $company_name; ?>" data-competency="<?php echo htmlspecialchars(normalizeCompetencyTypeLabel($cert['appointment_number'] ?? '', $cert['position_type'] ?? '', $cert['position_name'] ?? '')); ?>">
                            <td class="col-num"><?php echo $row_num++; ?></td>
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
                                <?php echo date('d/m/Y', strtotime($cert['expiry_date'])); ?>
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

<!-- Back to Top Button -->
<button class="back-to-top" id="backToTopBtn" onclick="scrollToTop()" title="Back to Top" data-lang-title="back-to-top">
    <i class="fas fa-chevron-up"></i>
</button>
<div id="requestRejectionModal" class="modal">
    <div class="modal-content modal-rejection">
        <div class="modal-header modal-header-rejection">
            <h3><i class="fas fa-exclamation-circle"></i> Detail Penolakan Permohonan</h3>
            <span class="close" onclick="closeRequestRejectionModal()">&times;</span>
        </div>
        <div class="modal-body modal-body-rejection">
            <div class="rejection-info">
                <div class="info-row">
                    <label>Nama Karyawan:</label>
                    <span id="reqRejectionEmployeeName"></span>
                </div>
                <div class="info-row">
                    <label>Kode Karyawan:</label>
                    <span id="reqRejectionEmployeeCode"></span>
                </div>
                <div class="rejection-notes-section">
                    <h4><i class="fas fa-clipboard"></i> Catatan Penolakan dari Reviewer</h4>
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
            <h3><i class="fas fa-exclamation-circle"></i> Detail Penolakan Surat Penunjukan</h3>
            <span class="close" onclick="closeRejectionModal()">&times;</span>
        </div>
        <div class="modal-body modal-body-rejection">
            <div class="rejection-info">
                <div class="info-row">
                    <label>Nomor Surat Penunjukan:</label>
                    <span id="rejectionAppointmentNumber"></span>
                </div>
                <div class="info-row">
                    <label>Nama Karyawan:</label>
                    <span id="rejectionEmployeeName"></span>
                </div>
                <div class="rejection-notes-section">
                    <h4><i class="fas fa-clipboard"></i> Catatan Penolakan dari KTT</h4>
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
function normalizeFilterValue(value) {
    return (value || '').toString().toLowerCase().replace(/\s+/g, ' ').trim();
}

// Filter function for approved table with column filters
function filterApprovedTable() {
    const table = document.getElementById('approvedTable');
    const tbody = table.getElementsByTagName('tbody')[0];
    const rows = tbody.getElementsByTagName('tr');
    const filters = table.querySelectorAll('.filter-row .column-filter:not([data-filter-type="sort"])');
    let visibleCount = 0;
    
    for (let row of rows) {
        const cells = row.getElementsByTagName('td');
        let showRow = true;
        
        filters.forEach(filter => {
            const colIndex = parseInt(filter.getAttribute('data-column'));
            const filterType = filter.getAttribute('data-filter-type') || 'exact';
            const filterValue = filter.value.toLowerCase().trim();
            
            if (filterValue && cells[colIndex]) {
                const cellText = cells[colIndex].textContent.toLowerCase().trim();
                
                if (filterType === 'contains') {
                    // For MSM/TTN filter - check if text contains the value
                    if (!cellText.includes(filterValue.toLowerCase())) {
                        showRow = false;
                    }
                } else if (filterType === 'startswith') {
                    // For Name filter - check first letter
                    if (!cellText.startsWith(filterValue)) {
                        showRow = false;
                    }
                } else if (filter.tagName === 'SELECT') {
                    // Exact match for select
                    if (filterValue && cellText !== filterValue) {
                        showRow = false;
                    }
                } else {
                    // Default: contains for text input
                    if (!cellText.includes(filterValue)) {
                        showRow = false;
                    }
                }
            }
        });
        
        if (showRow) {
            row.style.display = '';
            visibleCount++;
        } else {
            row.style.display = 'none';
        }
    }
    
    updateTableInfo('approvedTable', 'Showing ' + visibleCount + ' data');
}

// Sort table by any text column (toggle asc/desc)
let columnSortOrder = {}; // Track sort order per column
function sortApprovedTableByColumn(colIndex) {
    const table = document.getElementById('approvedTable');
    const tbody = table.getElementsByTagName('tbody')[0];
    const rows = Array.from(tbody.getElementsByTagName('tr'));
    const sortBtn = table.querySelector('.sort-btn[data-column="' + colIndex + '"]');
    
    // Toggle sort order
    columnSortOrder[colIndex] = columnSortOrder[colIndex] === 'asc' ? 'desc' : 'asc';
    const sortOrder = columnSortOrder[colIndex];
    
    // Update button icon
    if (sortBtn) {
        const icon = sortBtn.querySelector('i');
        icon.className = sortOrder === 'asc' ? 'fas fa-sort-alpha-down' : 'fas fa-sort-alpha-up';
    }
    
    rows.sort((a, b) => {
        const textA = (a.cells[colIndex]?.textContent || '').trim().toLowerCase();
        const textB = (b.cells[colIndex]?.textContent || '').trim().toLowerCase();
        
        if (sortOrder === 'asc') {
            return textA.localeCompare(textB);
        } else {
            return textB.localeCompare(textA);
        }
    });
    
    // Re-append sorted rows and update row numbers
    rows.forEach((row, index) => {
        tbody.appendChild(row);
        if (row.cells[0]) {
            row.cells[0].textContent = index + 1;
        }
    });
}

// Sort table by date column (toggle asc/desc)
function sortApprovedTableByDate(colIndex) {
    const table = document.getElementById('approvedTable');
    const tbody = table.getElementsByTagName('tbody')[0];
    const rows = Array.from(tbody.getElementsByTagName('tr'));
    const sortBtn = table.querySelector('.sort-btn[data-column="' + colIndex + '"]');
    
    // Toggle sort order
    columnSortOrder[colIndex] = columnSortOrder[colIndex] === 'asc' ? 'desc' : 'asc';
    const sortOrder = columnSortOrder[colIndex];
    
    // Update button icon
    if (sortBtn) {
        const icon = sortBtn.querySelector('i');
        icon.className = sortOrder === 'asc' ? 'fas fa-sort-numeric-down' : 'fas fa-sort-numeric-up';
    }
    
    // Parse date from dd/mm/yyyy format
    function parseDate(dateStr) {
        if (!dateStr || dateStr === '-') return null;
        const parts = dateStr.trim().split('/');
        if (parts.length === 3) {
            return new Date(parts[2], parts[1] - 1, parts[0]);
        }
        return null;
    }
    
    rows.sort((a, b) => {
        const dateA = parseDate(a.cells[colIndex]?.textContent);
        const dateB = parseDate(b.cells[colIndex]?.textContent);
        
        // Handle null dates (put them at the end)
        if (!dateA && !dateB) return 0;
        if (!dateA) return 1;
        if (!dateB) return -1;
        
        if (sortOrder === 'asc') {
            return dateA - dateB; // Nearest first
        } else {
            return dateB - dateA; // Furthest first
        }
    });
    
    // Re-append sorted rows and update row numbers
    rows.forEach((row, index) => {
        tbody.appendChild(row);
        if (row.cells[0]) {
            row.cells[0].textContent = index + 1;
        }
    });
}

// Populate select filters with unique values from table data
function populateColumnFilters() {
    const table = document.getElementById('approvedTable');
    if (!table) return;
    
    const tbody = table.getElementsByTagName('tbody')[0];
    const rows = tbody.getElementsByTagName('tr');
    // Only populate selects that don't have predefined options (no data-filter-type or have specific types to skip)
    const selectFilters = table.querySelectorAll('.filter-row select.column-filter:not([data-filter-type="contains"]):not([data-filter-type="startswith"]):not([data-filter-type="sort"])');
    
    selectFilters.forEach(select => {
        const colIndex = parseInt(select.getAttribute('data-column'));
        // Skip if already has options beyond "All"
        if (select.options.length > 1) return;
        
        const uniqueValues = new Set();
        
        for (let row of rows) {
            const cells = row.getElementsByTagName('td');
            if (cells[colIndex]) {
                const value = cells[colIndex].textContent.trim();
                if (value && value !== '-') {
                    uniqueValues.add(value);
                }
            }
        }
        
        // Sort and add options
        const sortedValues = Array.from(uniqueValues).sort();
        sortedValues.forEach(value => {
            const option = document.createElement('option');
            option.value = value.toLowerCase();
            option.textContent = value;
            select.appendChild(option);
        });
    });
}

// Initialize filters when section is expanded
document.addEventListener('DOMContentLoaded', function() {
    // Populate filters after a small delay to ensure table is rendered
    setTimeout(populateColumnFilters, 100);
});

function filterTableByFilters(tableId) {
    const table = document.getElementById(tableId);
    const rows = table.getElementsByTagName('tbody')[0].getElementsByTagName('tr');
    let visibleCount = 0;
    
    // Get filter values
    const sfx = tableId === 'approvedTable' ? 'Approved' : 'Rejected';
    const companyFilter     = document.getElementById('companyFilter'     + sfx)?.value || '';
    const scopeFilter       = document.getElementById('scopeFilter'       + sfx)?.value || '';
    const supervisionFilter = document.getElementById('supervisionFilter' + sfx)?.value || '';
    const competencyFilter  = document.getElementById('competencyFilter'  + sfx)?.value || '';
    
    // Apply filters
    for (let row of rows) {
        const rowCompany     = row.getAttribute('data-company');
        const rowScope       = row.getAttribute('data-scope');
        const rowSupervision = row.getAttribute('data-supervision');
        const rowCompetency  = row.getAttribute('data-competency');

        let showRow = true;
        if (companyFilter     && normalizeFilterValue(rowCompany)     !== normalizeFilterValue(companyFilter)) showRow = false;
        if (scopeFilter       && normalizeFilterValue(rowScope)       !== normalizeFilterValue(scopeFilter)) showRow = false;
        if (supervisionFilter && normalizeFilterValue(rowSupervision) !== normalizeFilterValue(supervisionFilter)) showRow = false;
        if (competencyFilter  && normalizeFilterValue(rowCompetency)  !== normalizeFilterValue(competencyFilter)) showRow = false;

        if (showRow) {
            row.style.display = '';
            visibleCount++;
        } else {
            row.style.display = 'none';
        }
    }
    
    // Update info message
    let infoMessage = 'Showing ' + visibleCount + ' data';
    if (companyFilter || scopeFilter || supervisionFilter || competencyFilter) {
        const filters = [];
        if (companyFilter)     filters.push('Company: '     + companyFilter);
        if (scopeFilter)       filters.push('Scope: '       + (scopeFilter === 'MSM' ? 'PT MSM' : 'PT TTN'));
        if (supervisionFilter) filters.push('Supervision: ' + supervisionFilter);
        if (competencyFilter)  filters.push('Competency: '  + competencyFilter);
        infoMessage += ' - Filter: ' + filters.join(', ');
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
        notesContent.innerHTML = '<p class="text-muted">Tidak ada catatan</p>';
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
    let companyFilterId = '', scopeFilterId = '', competencyFilterId = '';
    if (tableId === 'acceptedRequestTable') {
        companyFilterId    = 'companyFilterAcceptedReq';
        scopeFilterId      = 'scopeFilterAcceptedReq';
        competencyFilterId = 'competencyFilterAcceptedReq';
    } else if (tableId === 'rejectedRequestTable') {
        companyFilterId    = 'companyFilterRejectedReq';
        scopeFilterId      = 'scopeFilterRejectedReq';
        competencyFilterId = 'competencyFilterRejectedReq';
    } else if (tableId === 'waitingRequestTable') {
        companyFilterId    = 'companyFilterWaitingReq';
        scopeFilterId      = 'scopeFilterWaitingReq';
        competencyFilterId = 'competencyFilterWaitingReq';
    }

    const companyFilter    = document.getElementById(companyFilterId)?.value    || '';
    const scopeFilter      = document.getElementById(scopeFilterId)?.value      || '';
    const competencyFilter = document.getElementById(competencyFilterId)?.value || '';

    // Apply filters
    for (let row of rows) {
        const rowCompany    = row.getAttribute('data-company');
        const rowScope      = row.getAttribute('data-scope');
        const rowCompetency = row.getAttribute('data-competency');

        let showRow = true;
        if (companyFilter    && rowCompany    !== companyFilter)    showRow = false;
        if (scopeFilter      && rowScope      !== scopeFilter)      showRow = false;
        if (competencyFilter && rowCompetency !== competencyFilter) showRow = false;

        if (showRow) {
            row.style.display = '';
            visibleCount++;
        } else {
            row.style.display = 'none';
        }
    }

    // Update info message
    let infoMessage = 'Showing ' + visibleCount + ' data';
    if (companyFilter || scopeFilter || competencyFilter) {
        const filters = [];
        if (companyFilter)    filters.push('Company: '    + companyFilter);
        if (scopeFilter)      filters.push('Scope: '      + (scopeFilter === 'MSM' ? 'PT MSM' : 'PT TTN'));
        if (competencyFilter) filters.push('Competency: ' + competencyFilter);
        infoMessage += ' - Filter: ' + filters.join(', ');
    } else {
        infoMessage = 'Showing all data';
    }
    updateTableInfo(tableId, infoMessage);
}

function showRequestRejectionModal(employeeName, employeeCode, notes) {
    document.getElementById('reqRejectionEmployeeName').textContent = employeeName;
    document.getElementById('reqRejectionEmployeeCode').textContent = employeeCode;
    document.getElementById('reqRejectionNotesContent').textContent = notes || 'Tidak ada catatan';

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
    if (!table) { alert('Tabel laporan tidak ditemukan.'); return; }

    const rows = table.getElementsByTagName('tbody')[0].getElementsByTagName('tr');
    const companies = {};
    for (let row of rows) {
        if (row.style.display !== 'none') {
            const company = row.getAttribute('data-company');
            if (!companies[company]) companies[company] = [];
            companies[company].push(row);
        }
    }
    if (Object.keys(companies).length === 0) { alert('Tidak ada data yang dapat diekspor.'); return; }

    function val(row, key, fallback = '') {
        const v = row.getAttribute(key);
        return v && v.trim() !== '' ? v.trim() : fallback;
    }

    let htmlContent = `<!DOCTYPE html><html><head><meta charset="UTF-8">
        <title>Laporan Surat Penunjukan Disetujui</title>
        <style>
            *{box-sizing:border-box;}
            body{font-family:Arial,sans-serif;margin:24px;color:#222;font-size:11px;}
            h1{text-align:center;font-size:15px;color:#1e293b;margin-bottom:4px;}
            .sub{text-align:center;font-size:10px;color:#64748b;margin-bottom:20px;}
            h2{font-size:12px;color:#1e293b;border-left:3px solid #16a34a;
               padding-left:9px;margin:22px 0 6px;text-transform:uppercase;letter-spacing:.4px;}
            .summary{font-size:10px;color:#64748b;margin-bottom:6px;}
            table{width:100%;border-collapse:collapse;margin-bottom:16px;}
            thead tr{background:#f1f5f9;}
            th{padding:7px 9px;text-align:left;font-size:9.5px;font-weight:700;
               text-transform:uppercase;letter-spacing:.5px;color:#475569;
               border-bottom:2px solid #dde3ec;}
            td{padding:6px 9px;border-bottom:1px solid #eaecf0;font-size:11px;
               color:#374151;vertical-align:top;}
            tr:nth-child(even) td{background:#f8f9fb;}
            .code{font-family:monospace;font-size:9.5px;color:#94a3b8;display:block;margin-top:1px;}
            .lno{font-family:monospace;font-size:10.5px;color:#1e293b;}
            .footer{text-align:center;font-size:9px;color:#94a3b8;margin-top:24px;
                    padding-top:10px;border-top:1px solid #e5e7eb;}
            @media print{@page{margin:12mm;size:A4 landscape;}}
        </style></head><body>
        <h1>Laporan Surat Penunjukan Disetujui</h1>
        <div class="sub">Printed: ${new Date().toLocaleDateString('id-ID',{year:'numeric',month:'long',day:'numeric'})}
        &nbsp;&bull;&nbsp;${new Date().toLocaleTimeString('id-ID')}</div>`;

    let totalCount = 0;
    for (const company in companies) {
        const companyRows = companies[company];
        totalCount += companyRows.length;
        htmlContent += `<h2>${company}</h2>
            <div class="summary">${companyRows.length} accepted letter(s)</div>
            <table><thead><tr>
                <th style="width:16%">Letter No.</th>
                <th style="width:20%">Employee Name</th>
                <th style="width:10%">Employee Code</th>
                <th style="width:15%">Position</th>
                <th style="width:13%">Perusahaan</th>
                <th style="width:14%">Status</th>
                <th style="width:12%">Tanggal Kedaluwarsa</th>
            </tr></thead><tbody>`;
        for (let row of companyRows) {
            const cells = row.getElementsByTagName('td');
            const letterNo = val(row, 'data-letter-number', cells[1]?.textContent.trim() || '');
            const employeeName = val(row, 'data-employee-name', cells[4]?.textContent.trim() || '');
            const employeeCode = val(row, 'data-employee-code', cells[3]?.textContent.trim() || '');
            const position = val(row, 'data-position', cells[5]?.textContent.trim() || '');
            const companyValue = val(row, 'data-company', '-');
            const status = val(row, 'data-status', 'approved');
            const dateVal = val(row, 'data-expiry-date', cells[7]?.textContent.trim() || '');
            htmlContent += `<tr>
                <td class="lno">${letterNo}</td>
                <td>${employeeName}</td>
                <td><span class="code">${employeeCode}</span></td>
                <td>${position}</td>
                <td>${companyValue}</td>
                <td>${status}</td>
                <td>${dateVal}</td>
            </tr>`;
        }
        htmlContent += `</tbody></table>`;
    }
    htmlContent += `<div class="footer">Total Surat Disetujui: <strong>${totalCount}</strong>
        &nbsp;&bull;&nbsp;Expertise Assignment Letter System</div></body></html>`;

    const w = window.open('', '_blank');
    w.document.write(htmlContent);
    w.document.close();
    setTimeout(() => w.print(), 300);
}

function exportToExcel(tableId, filename) {
    const table = document.getElementById(tableId);
    if (!table) { alert('Tabel laporan tidak ditemukan.'); return; }

    const rows = Array.from(
        table.getElementsByTagName('tbody')[0].getElementsByTagName('tr')
    ).filter(r => r.style.display !== 'none');
    if (rows.length === 0) { alert('Tidak ada data yang dapat diekspor.'); return; }

    /* Helper: extract stacked employee cell */
    function empInfo(cell) {
        return {
            name: cell.querySelector('strong')?.textContent.trim()
                  || cell.textContent.split('\n')[0].trim(),
            code: cell.querySelector('.emp-code-detail')?.textContent.trim() || ''
        };
    }
    /* Helper: multi-item approval cell ? plain string */
    function approvalStr(cell) {
        const items = cell.querySelectorAll('.approval-item');
        if (items.length > 0) {
            return Array.from(items).map(item => {
                const n = item.querySelector('.approver-name')?.textContent.trim()     || '';
                const d = item.querySelector('.approval-datetime')?.textContent.trim() || '';
                return n + (d ? ' (' + d + ')' : '');
            }).join(' | ');
        }
        return cell.textContent.trim();
    }

    /* -- Per-table schema
       All tables now share the same Employee cell pattern (stacked name+code).
       Column indices reflect the new table structure (col[0] = # row counter).
    --------------------------------------------------------------------------- */
    const schemas = {
        /* cells: 0=# 1=Company 2=Employee 3=VerifiedDate 4=VerifiedBy */
        acceptedRequestTable: {
            title:     'Laporan Permohonan Disetujui',
            filterIds: ['companyFilterAcceptedReq', 'scopeFilterAcceptedReq', 'competencyFilterAcceptedReq'],
            headers:   ['No', 'Company', 'Employee Name', 'Employee Code', 'Verified Date', 'Verified By'],
            row(cells, n) {
                const e = empInfo(cells[2]);
                return [n, cells[1].textContent.trim(), e.name, e.code,
                        cells[3].textContent.trim(), cells[4].textContent.trim()];
            }
        },
        /* cells: 0=# 1=Company 2=Employee 3=RejectedDate 4=RejectedBy 5=Notes */
        rejectedRequestTable: {
            title:     'Laporan Permohonan Tidak Disetujui',
            filterIds: ['companyFilterRejectedReq', 'scopeFilterRejectedReq', 'competencyFilterRejectedReq'],
            headers:   ['No', 'Company', 'Employee Name', 'Employee Code', 'Rejected Date', 'Rejected By'],
            row(cells, n) {
                const e = empInfo(cells[2]);
                return [n, cells[1].textContent.trim(), e.name, e.code,
                        cells[3].textContent.trim(), cells[4].textContent.trim()];
            }
        },
        /* cells: 0=# 1=Perusahaan 2=Karyawan 3=TanggalPermohonan */
        waitingRequestTable: {
            title:     'Waiting for Approval Report',
            filterIds: ['companyFilterWaitingReq', 'scopeFilterWaitingReq', 'competencyFilterWaitingReq'],
            headers:   ['No', 'Perusahaan', 'Karyawan', 'Kode Karyawan', 'Tanggal Permohonan'],
            row(cells, n) {
                const e = empInfo(cells[2]);
                return [n, cells[1].textContent.trim(), e.name, e.code,
                        cells[3].textContent.trim()];
            }
        },
        /* cells: 0=# 1=LetterNo 2=Publisher 3=Badge 4=Employee 5=Position 6=Company 7=ExpiryDate */
        approvedTable: {
            title:     'Laporan Surat Penunjukan Disetujui',
            filterIds: ['companyFilterApproved', 'scopeFilterApproved', 'supervisionFilterApproved', 'competencyFilterApproved'],
            headers:   ['No', 'Assign Letter No.', 'Company', 'Employee Name', 'Employee Code',
                        'Posisi', 'Status', 'Tanggal Kedaluwarsa', 'Penerbit'],
            row(cells, n) {
                const tr = cells[0]?.parentElement;
                const ds = tr ? tr.dataset : {};
                return [
                    n,
                    ds.letterNumber || cells[1].textContent.trim(),
                    ds.company || cells[6].textContent.trim(),
                    ds.employeeName || cells[4].textContent.trim(),
                    ds.employeeCode || cells[3].textContent.trim(),
                    ds.position || cells[5].textContent.trim(),
                    ds.status || 'approved',
                    ds.expiryDate || cells[7].textContent.trim(),
                    ds.publisher || cells[2].textContent.trim()
                ];
            }
        },
        /* cells: 0=# 1=LetterNo 2=Company 3=Employee 4=Position 5=RejDate 6=RejBy 7=Action */
        rejectedTable: {
            title:     'Laporan Surat Penunjukan Tidak Disetujui',
            filterIds: ['companyFilterRejected', 'scopeFilterRejected', 'supervisionFilterRejected', 'competencyFilterRejected'],
            headers:   ['No', 'Assign Letter No.', 'Company', 'Employee Name', 'Employee Code',
                        'Position', 'Rejected Date', 'Rejected By'],
            row(cells, n) {
                const e = empInfo(cells[3]);
                return [n, cells[1].textContent.trim(), cells[2].textContent.trim(),
                        e.name, e.code, cells[4].textContent.trim(),
                        cells[5].textContent.trim(), cells[6].textContent.trim()];
            }
        },
        /* cells: 0=# 1=Company 2=Employee 3=CertName 4=CertNo 5=Expiry 6=DaysLeft 7=Status */
        expiringCertsTable: {
            title:     'Laporan Sertifikat Kedaluwarsa',
            filterIds: ['companyFilterExpiring', 'competencyFilterExpiring'],
            headers:   ['No', 'Company', 'Employee Name', 'Employee Code',
                        'Nama Sertifikat', 'No. Sertifikat', 'Tanggal Kedaluwarsa', 'Sisa Hari', 'Status'],
            row(cells, n) {
                const e = empInfo(cells[2]);
                return [n, cells[1].textContent.trim(), e.name, e.code,
                        cells[3].textContent.trim(), cells[4].textContent.trim(),
                        cells[5].textContent.trim(), cells[6].textContent.trim(),
                        cells[7].textContent.trim()];
            }
        }
    };

    const schema = schemas[tableId];
    if (!schema) { alert('Skema ekspor tidak dikenali.'); return; }

    /* Active filter labels */
    let filterLabel = '';
    schema.filterIds.forEach(id => {
        const el = document.getElementById(id);
        if (el && el.value) {
            filterLabel += (filterLabel ? ' | ' : '') + el.options[el.selectedIndex].text;
        }
    });

    const cols = schema.headers.length;

    /* Build Excel HTML */
    let xl = `<html xmlns:o="urn:schemas-microsoft-com:office:office"
        xmlns:x="urn:schemas-microsoft-com:office:excel"
        xmlns="http://www.w3.org/TR/REC-html40">
    <head><meta charset="UTF-8">
    <!--[if gte mso 9]><xml><x:ExcelWorkbook><x:ExcelWorksheets>
    <x:ExcelWorksheet><x:Name>Report</x:Name>
    <x:WorksheetOptions><x:DisplayGridlines/></x:WorksheetOptions>
    </x:ExcelWorksheet></x:ExcelWorksheets></x:ExcelWorkbook></xml><![endif]-->
    <style>
        body{font-family:Calibri,Arial,sans-serif;}
        th{background:#37474F;color:#fff;font-weight:bold;padding:7px 9px;
           border:1px solid #888;font-size:10pt;white-space:nowrap;}
        td{padding:6px 9px;border:1px solid #d0d5dd;font-size:10pt;vertical-align:top;}
        .even td{background:#f5f7fb;}
        .meta td{font-size:9pt;color:#666;}
        .title td{font-size:13pt;font-weight:bold;color:#1e293b;padding:10px 9px;}
    </style></head><body><table>`;

    xl += `<tr class="title"><td colspan="${cols}">${schema.title}</td></tr>`;
    xl += `<tr class="meta"><td colspan="${cols}">Export Date: ${new Date().toLocaleDateString('id-ID')} ${new Date().toLocaleTimeString('id-ID')}</td></tr>`;
    if (filterLabel) xl += `<tr class="meta"><td colspan="${cols}">Filter: ${filterLabel}</td></tr>`;
    xl += `<tr class="meta"><td colspan="${cols}">Total: ${rows.length} rows</td></tr>`;
    xl += `<tr><td colspan="${cols}"></td></tr>`;
    xl += '<tr>' + schema.headers.map(h => `<th>${h}</th>`).join('') + '</tr>';

    rows.forEach((row, i) => {
        const cells = row.getElementsByTagName('td');
        const vals  = schema.row(cells, i + 1);
        xl += `<tr${i % 2 === 1 ? ' class="even"' : ''}>` +
              vals.map(v => `<td>${(v !== '' && v != null) ? v : '�'}</td>`).join('') +
              '</tr>';
    });

    xl += '</table></body></html>';

    const blob = new Blob([xl], { type: 'application/vnd.ms-excel;charset=utf-8' });
    const url  = URL.createObjectURL(blob);
    const a    = document.createElement('a');
    a.href     = url;
    a.download = filename
        + (filterLabel ? '_' + filterLabel.replace(/[^a-zA-Z0-9]/g, '_') : '')
        + '_' + new Date().toISOString().slice(0, 10) + '.xls';
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
}

function filterExpiringCerts() {
    const table = document.getElementById('expiringCertsTable');
    const rows = table.getElementsByTagName('tbody')[0].getElementsByTagName('tr');
    let visibleCount = 0;
    
    const companyFilter    = document.getElementById('companyFilterExpiring')?.value    || '';
    const competencyFilter = document.getElementById('competencyFilterExpiring')?.value || '';
    
    for (let row of rows) {
        const rowCompany    = row.getAttribute('data-company');
        const rowCompetency = row.getAttribute('data-competency');

        const show = (!companyFilter    || rowCompany    === companyFilter) &&
                     (!competencyFilter || rowCompetency === competencyFilter);
        row.style.display = show ? '' : 'none';
        if (show) visibleCount++;
    }
    
    let infoMessage = 'Showing ' + visibleCount + ' data';
    if (companyFilter || competencyFilter) {
        const filters = [];
        if (companyFilter)    filters.push('Company: '    + companyFilter);
        if (competencyFilter) filters.push('Competency: ' + competencyFilter);
        infoMessage += ' - Filter: ' + filters.join(', ');
    } else {
        infoMessage = 'Showing all data';
    }
    
    const infoElement = document.getElementById('expiringCertsTableInfo');
    if (infoElement) {
        infoElement.textContent = infoMessage;
    }
}

function exportExpiringCerts() {
    const table = document.getElementById('expiringCertsTable');
    if (!table) { alert('Tabel laporan tidak ditemukan.'); return; }

    const rows = table.getElementsByTagName('tbody')[0].getElementsByTagName('tr');
    const companies = {};
    for (let row of rows) {
        if (row.style.display !== 'none') {
            const company = row.getAttribute('data-company');
            if (!companies[company]) companies[company] = [];
            companies[company].push(row);
        }
    }
    if (Object.keys(companies).length === 0) { alert('Tidak ada data yang dapat diekspor.'); return; }

    /* Column layout:
       cells[0]=#  cells[1]=Company  cells[2]=Employee(stacked)
       cells[3]=CertName  cells[4]=CertNo  cells[5]=Expiry
       cells[6]=DaysLeft  cells[7]=Status */
    function empInfo(cell) {
        return {
            name: cell.querySelector('strong')?.textContent.trim() || '',
            code: cell.querySelector('.emp-code-detail')?.textContent.trim() || ''
        };
    }
    function statusCls(cell) {
        const t = cell.textContent;
        if (t.includes('Very Urgent') || t.includes('Sangat'))   return 'critical';
        if (t.includes('Urgent')      || t.includes('Mendesak')) return 'urgent';
        return 'warning';
    }

    let htmlContent = `<!DOCTYPE html><html><head><meta charset="UTF-8">
        <title>Laporan Sertifikat Kedaluwarsa</title>
        <style>
            *{box-sizing:border-box;}
            body{font-family:Arial,sans-serif;margin:24px;color:#222;font-size:11px;}
            h1{text-align:center;font-size:15px;color:#d97706;margin-bottom:4px;}
            .sub{text-align:center;font-size:10px;color:#64748b;margin-bottom:16px;}
            .warn{background:#fef3c7;border-left:4px solid #f59e0b;padding:9px 13px;
                  margin-bottom:20px;border-radius:4px;font-size:11px;color:#92400e;}
            h2{font-size:12px;color:#1e293b;border-left:3px solid #f59e0b;
               padding-left:9px;margin:22px 0 6px;text-transform:uppercase;letter-spacing:.4px;}
            .summary{font-size:10px;color:#64748b;margin-bottom:6px;}
            table{width:100%;border-collapse:collapse;margin-bottom:16px;}
            thead tr{background:#fef3c7;}
            th{padding:7px 9px;text-align:left;font-size:9.5px;font-weight:700;
               text-transform:uppercase;letter-spacing:.5px;color:#92400e;
               border-bottom:2px solid #fde68a;}
            td{padding:6px 9px;border-bottom:1px solid #eaecf0;font-size:11px;
               color:#374151;vertical-align:top;}
            tr:nth-child(even) td{background:#f8f9fb;}
            .code{font-family:monospace;font-size:9.5px;color:#94a3b8;display:block;margin-top:1px;}
            .certno{font-family:monospace;font-size:10px;}
            .critical{color:#dc2626;font-weight:700;}
            .urgent  {color:#ea580c;font-weight:700;}
            .warning {color:#d97706;font-weight:600;}
            .days{text-align:center;font-weight:700;}
            .footer{text-align:center;font-size:9px;color:#94a3b8;margin-top:24px;
                    padding-top:10px;border-top:1px solid #e5e7eb;}
            @media print{@page{margin:12mm;size:A4 landscape;}}
        </style></head><body>
        <h1>Laporan Sertifikat Kedaluwarsa</h1>
        <div class="sub">Printed: ${new Date().toLocaleDateString('id-ID',{year:'numeric',month:'long',day:'numeric'})}
        &nbsp;&bull;&nbsp;${new Date().toLocaleTimeString('id-ID')}</div>
        <div class="warn"><strong>&#9888; PERHATIAN:</strong> Karyawan dengan sertifikat yang kedaluwarsa dalam waktu &le;2 bulan. Harap segera perpanjang.</div>`;

    let totalCount = 0;
    for (const company in companies) {
        const companyRows = companies[company];
        totalCount += companyRows.length;
        htmlContent += `<h2>${company}</h2>
            <div class="summary">${companyRows.length} sertifikat kedaluwarsa</div>
            <table><thead><tr>
                <th style="width:22%">Karyawan</th>
                <th style="width:22%">Nama Sertifikat</th>
                <th style="width:18%">Nomor Sertifikat</th>
                <th style="width:13%">Tanggal Kedaluwarsa</th>
                <th style="width:9%">Sisa Hari</th>
                <th style="width:16%">Status</th>
            </tr></thead><tbody>`;
        for (let row of companyRows) {
            const cells = row.getElementsByTagName('td');
            const emp   = empInfo(cells[2]);
            const sc    = statusCls(cells[7]);
            htmlContent += `<tr>
                <td>${emp.name}<span class="code">${emp.code}</span></td>
                <td>${cells[3].textContent.trim()}</td>
                <td class="certno">${cells[4].textContent.trim()}</td>
                <td>${cells[5].textContent.trim()}</td>
                <td class="days">${cells[6].textContent.trim()}</td>
                <td class="${sc}">${cells[7].textContent.trim()}</td>
            </tr>`;
        }
        htmlContent += `</tbody></table>`;
    }
    htmlContent += `<div class="footer">Total Sertifikat Kedaluwarsa: <strong>${totalCount}</strong>
        &nbsp;&bull;&nbsp;Expertise Assignment Letter System</div></body></html>`;

    const w = window.open('', '_blank');
    w.document.write(htmlContent);
    w.document.close();
    setTimeout(() => w.print(), 300);
}
</script>

<style>
/* =========================================================
   Reports � Compact Minimalist Design
   ========================================================= */

/* Container */
.reports-container { padding: 16px 0; }

/* -- Page Header ---------------------------------------- */
.page-header-reports {
    background: #fff;
    border: 1px solid #e5e7eb;
    border-left: 4px solid #F57C00;
    border-radius: 8px;
    padding: 14px 20px;
    margin-bottom: 16px;
    display: flex;
    align-items: center;
    justify-content: space-between;
}
.header-left h2 {
    margin: 0 0 2px 0;
    font-size: 16px;
    font-weight: 700;
    color: #1e293b;
    display: flex;
    align-items: center;
    gap: 8px;
}
.header-left h2 i { color: #F57C00; }
.header-left p  { margin: 0; font-size: 11.5px; color: #94a3b8; }
.header-date {
    font-size: 12px;
    color: #64748b;
    display: flex;
    align-items: center;
    gap: 5px;
    background: #f8fafc;
    padding: 5px 11px;
    border-radius: 5px;
    border: 1px solid #e2e8f0;
}

/* -- Stats Strip ---------------------------------------- */
.stats-grid-reports {
    display: flex;
    gap: 10px;
    margin-bottom: 16px;
    flex-wrap: wrap;
}
.stat-card-report {
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    padding: 12px 16px;
    display: flex;
    align-items: center;
    gap: 12px;
    flex: 1;
    min-width: 130px;
    transition: border-color 0.2s, box-shadow 0.2s;
}
.stat-card-report:hover {
    border-color: #cbd5e1;
}
.stat-total    { border-left: 3px solid #475569; }
.stat-approved { border-left: 3px solid #16a34a; }
.stat-rejected { border-left: 3px solid #dc2626; }
.stat-pending  { border-left: 3px solid #d97706; }
.stat-draft    { border-left: 3px solid #9ca3af; }
.stat-needs-review { border-left: 3px solid #2563eb; }

.stat-icon-report {
    width: 34px;
    height: 34px;
    border-radius: 7px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 15px;
    flex-shrink: 0;
}
.stat-total    .stat-icon-report { background: #f1f5f9; color: #475569; }
.stat-approved .stat-icon-report { background: #f0fdf4; color: #16a34a; }
.stat-rejected .stat-icon-report { background: #fef2f2; color: #dc2626; }
.stat-pending  .stat-icon-report { background: #fffbeb; color: #d97706; }
.stat-draft    .stat-icon-report { background: #f3f4f6; color: #6b7280; }
.stat-needs-review .stat-icon-report { background: #eff6ff; color: #2563eb; }

.stat-content-report h3 {
    font-size: 20px;
    font-weight: 700;
    color: #1e293b;
    margin: 0;
    line-height: 1;
}
.stat-content-report p {
    color: #94a3b8;
    font-size: 11px;
    margin: 3px 0 0 0;
    white-space: nowrap;
}

/* Separator between stat groups */
.stat-sep {
    width: 1px;
    align-self: stretch;
    background: #e5e7eb;
    margin: 2px 4px;
    flex-shrink: 0;
}

/* -- Quick Navigation Bar ------------------------------- */
.quick-nav-bar {
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    padding: 10px 16px;
    margin-bottom: 16px;
    display: flex;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
}
.quick-nav-label {
    font-size: 10.5px;
    font-weight: 700;
    color: #94a3b8;
    text-transform: uppercase;
    letter-spacing: 0.6px;
    white-space: nowrap;
    display: flex;
    align-items: center;
    gap: 4px;
}
.quick-nav-links {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    flex: 1;
}
.quick-nav-btn {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 11.5px;
    font-weight: 600;
    text-decoration: none;
    transition: all 0.2s;
    border: 1px solid transparent;
    white-space: nowrap;
}
.quick-nav-btn:hover { text-decoration: none; transform: translateY(-1px); }
.nav-accepted { background: #f0fdf4; color: #16a34a; border-color: #bbf7d0; }
.nav-accepted:hover { background: #16a34a; color: #fff; }
.nav-rejected { background: #fef2f2; color: #dc2626; border-color: #fecaca; }
.nav-rejected:hover { background: #dc2626; color: #fff; }
.nav-warning  { background: #fffbeb; color: #d97706; border-color: #fde68a; }
.nav-warning:hover  { background: #d97706; color: #fff; }
.nav-default  { background: #f8fafc; color: #475569; border-color: #e2e8f0; }
.nav-default:hover  { background: #475569; color: #fff; }
.nav-badge {
    background: rgba(0,0,0,0.12);
    padding: 0 5px;
    border-radius: 8px;
    font-size: 10px;
    font-weight: 700;
}

/* -- Section Cards -------------------------------------- */
.card-report {
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    margin-bottom: 14px;
    scroll-margin-top: 16px;
    overflow: hidden;
}
.card-header-report {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 10px 16px;
    border-bottom: 1px solid #eaecf0;
    background: #fff;
}
.card-header-report h3 {
    margin: 0;
    font-size: 13.5px;
    font-weight: 700;
    color: #1e293b;
    display: flex;
    align-items: center;
    gap: 7px;
}
.card-header-report i { color: #64748b; font-size: 13px; }
.card-body-report { padding: 0; }

/* Card header title group */
.card-hd-left {
    display: flex;
    align-items: center;
    gap: 8px;
    flex: 1;
}

/* Header badges */
.badge-header {
    background: #1e293b;
    color: #fff;
    padding: 2px 8px;
    border-radius: 10px;
    font-size: 11px;
    font-weight: 700;
}
.badge-header.rejected { background: #dc2626; }
.badge-header.warning  { background: #d97706; }

/* -- Toggle Button -------------------------------------- */
.btn-toggle-section {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 5px 12px;
    background: #fff;
    color: #475569;
    border: 1px solid #e2e8f0;
    border-radius: 5px;
    font-size: 12px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
}
.btn-toggle-section:hover { background: #1e293b; color: #fff; border-color: #1e293b; }
.btn-toggle-section i { font-size: 11px; transition: transform 0.3s ease; }
.section-content { transition: max-height 0.4s ease-out, opacity 0.4s ease-out; overflow: hidden; }

/* -- Filter Section ------------------------------------- */
.filter-section-report {
    padding: 8px 14px;
    background: #fafafa;
    border-bottom: 1px solid #eaecf0;
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    align-items: center;
}
.filter-group-report {
    display: flex;
    align-items: center;
    gap: 6px;
}
.filter-group-report label {
    font-size: 11.5px;
    font-weight: 600;
    color: #64748b;
    white-space: nowrap;
    display: flex;
    align-items: center;
    gap: 4px;
    margin: 0;
}
.filter-select-report {
    padding: 5px 8px;
    border: 1px solid #e2e8f0;
    border-radius: 5px;
    font-size: 12px;
    cursor: pointer;
    background: #fff;
    color: #374151;
    transition: border-color 0.2s;
    min-width: 155px;
}
.filter-select-report:hover,
.filter-select-report:focus { border-color: #94a3b8; outline: none; }
.filter-action-group {
    margin-left: auto;
    display: flex;
    gap: 6px;
    align-items: center;
}

/* -- Table ---------------------------------------------- */
.table-report {
    width: 100%;
    border-collapse: collapse;
    table-layout: fixed;
    margin: 0;
    font-size: 12px;
}
.table-report thead th {
    background: #f1f5f9;
    color: #475569;
    font-weight: 700;
    font-size: 10px;
    text-transform: uppercase;
    letter-spacing: 0.6px;
    padding: 10px 12px;
    border-bottom: 1px solid #dde3ec;
    border-right: none;
    text-align: left;
    white-space: nowrap;
    overflow: hidden;
}
.table-report thead th:last-child { border-right: none; }
.table-report td {
    padding: 10px 12px;
    vertical-align: middle;
    border-bottom: 1px solid #f0f2f5;
    color: #374151;
    line-height: 1.4;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.table-report tbody tr:last-child td { border-bottom: none; }
.table-report tbody tr:hover { background: #ebf0fa !important; cursor: default; }
.table-report tbody tr:nth-child(even) { background: #f8f9fb; }
.table-report tbody tr:nth-child(even):hover { background: #ebf0fa !important; }
.report-row,
.detail-row { border-bottom: 1px solid #f1f5f9; }

/* -- Column Filters in Header --------------------------- */
.table-with-filters .filter-row th {
    background: #e8ecf1;
    padding: 6px 8px;
    border-bottom: 2px solid #cbd5e1;
}
.column-filter {
    width: 100%;
    padding: 5px 8px;
    border: 1px solid #d1d5db;
    border-radius: 4px;
    font-size: 11px;
    background: #fff;
    color: #374151;
    box-sizing: border-box;
}
.column-filter:focus {
    outline: none;
    border-color: #3b82f6;
    box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.15);
}
.column-filter::placeholder {
    color: #9ca3af;
    font-style: italic;
}
select.column-filter {
    cursor: pointer;
    appearance: auto;
}

/* Filter with sort button */
.filter-with-sort {
    display: flex;
    gap: 4px;
    align-items: center;
}
.filter-with-sort .column-filter {
    flex: 1;
    min-width: 0;
}
.sort-btn {
    padding: 5px 8px;
    border: 1px solid #d1d5db;
    border-radius: 4px;
    background: #fff;
    color: #6b7280;
    cursor: pointer;
    font-size: 11px;
    transition: all 0.2s;
    flex-shrink: 0;
}
.sort-btn:hover {
    background: #f3f4f6;
    border-color: #9ca3af;
    color: #374151;
}
.sort-btn:active {
    background: #e5e7eb;
}
.sort-btn i {
    font-size: 12px;
}
.sort-btn-full {
    width: 100%;
    justify-content: center;
    gap: 4px;
}

.table-info-report {
    padding: 7px 16px;
    background: #f8fafc;
    color: #94a3b8;
    border-top: 1px solid #f1f5f9;
    font-size: 11px;
}

/* -- Row number column ---------------------------------- */
.col-num {
    width: 38px;
    text-align: center;
    color: #cbd5e1;
    font-size: 11px;
    font-weight: 600;
}
.table-report thead th.col-num { text-align: center; }
.table-report thead th.col-publisher { text-align: center; }
.table-report thead th.col-badge { text-align: left; }
.table-report thead th.col-date { text-align: center; }

/* -- Unified column widths (consistent across all tables) -- */
.col-company        { min-width: 150px; }
.col-approved       { width: 90px;  text-align: center; }
.col-rejected       { width: 90px;  text-align: center; }
.col-total          { width: 75px;  text-align: center; }
.col-number         { width: 140px; }
.col-publisher      { width: 80px; text-align: center; }
.col-badge          { width: 120px; font-family: 'Courier New', Courier, monospace; font-size: 11px; letter-spacing: 0.3px; }
.col-company-detail { width: 160px; }
.col-employee       { width: 150px; white-space: normal !important; overflow: visible !important; }
.col-date           { width: 100px; white-space: nowrap; font-variant-numeric: tabular-nums; text-align: center; }
.col-approved-info  { width: 170px; white-space: normal !important; overflow: visible !important; }
.col-position       { width: 140px; }
.col-rejected-date  { width: 108px; white-space: nowrap; font-variant-numeric: tabular-nums; }
.col-rejected-by    { width: 148px; white-space: normal !important; overflow: visible !important; }
.col-action         { width: 100px; text-align: center; }
.col-notes          { width: 85px;  text-align: center; }
.col-cert-name      { width: 170px; }
.col-cert-number    { width: 135px; font-family: 'Courier New', Courier, monospace; font-size: 11px; letter-spacing: 0.3px; }
.col-expiry-date    { width: 100px; white-space: nowrap; font-variant-numeric: tabular-nums; }
.col-days-left      { width: 80px;  text-align: center; }
.col-status-expiry  { width: 105px; }

/* Assign letter number */
.col-number strong {
    font-family: 'Courier New', Courier, monospace;
    font-size: 11px;
    font-weight: 700;
    color: #1e293b;
    letter-spacing: 0.3px;
    white-space: nowrap;
}

/* Employee code sub-line */
.emp-code-detail {
    font-family: 'Courier New', Courier, monospace;
    font-size: 10px;
    color: #94a3b8;
    letter-spacing: 0.3px;
    display: block;
    margin-top: 1px;
}

/* -- Badges & Tags -------------------------------------- */
.badge-count {
    display: inline-block;
    padding: 2px 7px;
    border-radius: 3px;
    font-size: 11.5px;
    font-weight: 700;
}
.approved-badge { background: #f0fdf4; color: #16a34a; }
.rejected-badge { background: #fef2f2; color: #dc2626; }

.company-tag       { font-size: 11.5px; color: #475569; font-weight: 500; }
.publisher-tag     { font-size: 11.5px; color: #1e40af; font-weight: 600; }
.position-badge-report { font-size: 11.5px; color: #64748b; }
.approver-badge    { font-size: 11.5px; color: #16a34a; font-weight: 600; display: block; }
.rejector-badge    { font-size: 11.5px; color: #dc2626; font-weight: 600; display: block; }

.employee-detail { display: flex; flex-direction: column; gap: 2px; }

/* Rejected row accent */
.rejected-row   { border-left: 2px solid #fca5a5; }

/* -- Approval info -------------------------------------- */
.approval-info-container { display: flex; flex-direction: column; gap: 4px; }
.approval-item  { display: flex; flex-direction: column; gap: 2px; }
.approver-name  { font-size: 11.5px; color: #374151; }
.approver-name strong { color: #1e293b; }
.approval-datetime {
    font-size: 10.5px;
    color: #94a3b8;
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}
.approval-datetime i { font-size: 10px; }

/* -- Certificate badges --------------------------------- */
.cert-name-badge {
    color: #475569;
    font-size: 11.5px;
    font-weight: 500;
}
.days-badge {
    display: inline-block;
    padding: 3px 8px;
    border-radius: 4px;
    font-size: 12px;
    font-weight: 700;
}
.days-critical { background: #fef2f2; color: #dc2626; }
.days-urgent   { background: #fff7ed; color: #ea580c; }
.days-warning  { background: #fffbeb; color: #d97706; }

.status-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 3px 8px;
    border-radius: 4px;
    font-size: 11px;
    font-weight: 600;
}
.status-critical { background: #fef2f2; color: #dc2626; }
.status-urgent   { background: #fff7ed; color: #ea580c; }
.status-warning  { background: #fffbeb; color: #d97706; }

/* -- Alert ---------------------------------------------- */
.alert-warning-report {
    background: #fffbeb;
    border-left: 3px solid #d97706;
    padding: 10px 16px;
    border-radius: 5px;
    margin-bottom: 14px;
    display: flex;
    align-items: center;
    gap: 10px;
    color: #92400e;
    font-size: 12.5px;
}
.alert-warning-report i { color: #d97706; }
.alert-warning-report span { font-size: 13px; line-height: 1.5; }

/* -- Export Buttons ------------------------------------- */
.btn-export-small {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 5px 10px;
    background: #16a34a;
    color: #fff;
    border: none;
    border-radius: 5px;
    font-size: 11.5px;
    cursor: pointer;
    font-weight: 600;
    transition: background 0.2s;
}
.btn-export-small i { font-size: 13px; }
.btn-export-small:hover { background: #15803d; }

.btn-export-pdf {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 5px 10px;
    background: #fef2f2;
    color: #dc2626;
    border: 1px solid #fecaca;
    border-radius: 5px;
    font-size: 11.5px;
    cursor: pointer;
    font-weight: 600;
    transition: all 0.2s;
}
.btn-export-pdf i { font-size: 13px; }
.btn-export-pdf:hover { background: #dc2626; color: #fff; border-color: #dc2626; }

/* -- Detail button -------------------------------------- */
.btn-detail-small {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 4px 9px;
    background: #f1f5f9;
    color: #475569;
    border: 1px solid #e2e8f0;
    border-radius: 4px;
    font-size: 11.5px;
    cursor: pointer;
    font-weight: 500;
    transition: all 0.2s;
}
.btn-detail-small:hover { background: #1e293b; color: #fff; border-color: #1e293b; }

/* -- Notes badge ---------------------------------------- */
.notes-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 3px 8px;
    background: #fffbeb;
    color: #b45309;
    border-radius: 4px;
    font-size: 11.5px;
    cursor: pointer;
    font-weight: 500;
    border: 1px solid #fde68a;
    transition: background 0.2s;
}
.notes-badge:hover { background: #fcd34d; }
.notes-text { color: #64748b; font-size: 12px; }

/* -- Empty State ---------------------------------------- */
.empty-state-report {
    text-align: center;
    padding: 40px 20px;
    color: #cbd5e1;
}
.empty-state-report i { font-size: 36px; margin-bottom: 10px; display: block; }
.empty-state-report p { margin: 0; font-size: 14px; }

/* -- Progress bar --------------------------------------- */
.progress-bar-inline {
    width: 60px;
    height: 4px;
    background: #e5e7eb;
    border-radius: 2px;
    overflow: hidden;
    display: inline-block;
    margin-right: 6px;
    vertical-align: middle;
}
.progress-fill    { height: 100%; background: #16a34a; }
.percentage-text  { font-weight: 700; color: #374151; font-size: 11.5px; }

/* -- Rejection Modal ------------------------------------ */
.modal-rejection { max-width: 560px; }
.modal-header-rejection {
    background: #dc2626;
    color: #fff;
    padding: 14px 18px;
    border-radius: 8px 8px 0 0;
}
.modal-header-rejection h3 {
    margin: 0;
    font-size: 14px;
    display: flex;
    align-items: center;
    gap: 8px;
}
.modal-header-rejection .close { color: #fff; opacity: 0.8; }
.modal-header-rejection .close:hover { opacity: 1; }
.modal-body-rejection { padding: 18px; }
.rejection-info { margin-bottom: 14px; }
.info-row {
    display: flex;
    padding: 8px 0;
    border-bottom: 1px solid #f1f5f9;
    font-size: 13px;
}
.info-row label { font-weight: 600; color: #374151; width: 130px; flex-shrink: 0; }
.info-row span  { color: #64748b; flex: 1; }
.rejection-notes-section { margin-top: 14px; }
.rejection-notes-section h4 {
    margin: 0 0 8px;
    font-size: 13px;
    color: #374151;
    display: flex;
    align-items: center;
    gap: 6px;
}
.rejection-notes-section i  { color: #dc2626; }
.rejection-notes-content {
    display: flex;
    flex-direction: column;
    gap: 8px;
    max-height: 320px;
    overflow-y: auto;
}
.rejection-note-item {
    background: #fef2f2;
    border-left: 3px solid #dc2626;
    padding: 10px 12px;
    border-radius: 4px;
}
.rejection-note-header { display: flex; align-items: center; gap: 6px; margin-bottom: 5px; }
.note-number {
    background: #dc2626;
    color: #fff;
    padding: 1px 6px;
    border-radius: 4px;
    font-size: 10px;
    font-weight: 700;
}
.rejection-note-header i { color: #dc2626; font-size: 12px; }
.rejection-note-text { color: #7f1d1d; font-size: 12px; line-height: 1.5; word-break: break-word; }
.modal-footer-rejection {
    display: flex;
    gap: 8px;
    justify-content: flex-end;
    padding: 12px 18px;
    border-top: 1px solid #f1f5f9;
}

/* -- Back to Top ---------------------------------------- */
.back-to-top {
    position: fixed;
    bottom: 24px;
    right: 24px;
    width: 38px;
    height: 38px;
    background: #1e293b;
    color: #fff;
    border: none;
    border-radius: 50%;
    font-size: 14px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 2px 8px rgba(30,41,59,0.3);
    transition: all 0.3s;
    opacity: 0;
    visibility: hidden;
    z-index: 999;
}
.back-to-top:hover { background: #F57C00; transform: translateY(-2px); }

/* -- Section accent borders ----------------------------- */
#section-accepted-req .card-report,
#section-accepted-assign .card-report { border-left: 2px solid #bbf7d0; }
#section-accepted-req .card-header-report,
#section-accepted-assign .card-header-report { border-bottom-color: #dcfce7; }
#section-accepted-req .card-header-report h3 i,
#section-accepted-assign .card-header-report h3 i { color: #16a34a; }

#section-rejected-req .card-report,
#section-rejected-assign .card-report { border-left: 2px solid #fecaca; }
#section-rejected-req .card-header-report,
#section-rejected-assign .card-header-report { border-bottom-color: #fee2e2; }
#section-rejected-req .card-header-report h3 i,
#section-rejected-assign .card-header-report h3 i { color: #dc2626; }

#section-waiting-req .card-report { border-left: 2px solid #fde68a; }
#section-waiting-req .card-header-report { border-bottom-color: #fef3c7; }
#section-waiting-req .card-header-report h3 i { color: #d97706; }

#certificate-expiration .card-report { border-left: 2px solid #fde68a; }
#certificate-expiration .card-header-report { border-bottom-color: #fef3c7; }
#certificate-expiration .card-header-report h3 i { color: #d97706; }
.table-expiring tbody tr:hover { background: #fffbeb !important; }

/* -- Text-muted in tables ------------------------------- */
.table-report .text-muted {
    color: #cbd5e1;
    font-size: 11px;
}

/* -- Compact approval display --------------------------- */
.approval-item {
    padding: 2px 0;
}
.approver-name {
    display: block;
    font-size: 12px;
    line-height: 1.4;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 180px;
}
.approval-datetime {
    display: block;
    color: #94a3b8;
    font-size: 11px;
    margin-top: 1px;
}

/* -- Status badge tiny icon ----------------------------- */
.status-badge i { font-size: 10px; }

/* -- Notes "View Notes" button width -------------------- */
.notes-badge {
    font-size: 11px;
    color: #2563eb;
    cursor: pointer;
    white-space: nowrap;
}
.notes-badge:hover { text-decoration: underline; }

/* -- Rejected row border -------------------------------- */
.table-report .rejected-row { border-left: 2px solid #fca5a5; }

/* -- Responsive ----------------------------------------- */
@media (max-width: 1024px) {    .page-header-reports { flex-direction: column; align-items: flex-start; gap: 8px; }
    .col-company-detail  { display: none; }
    .col-rejected-by     { display: none; }
}
@media (max-width: 768px) {
    .stats-grid-reports  { flex-direction: column; }
    .stat-card-report    { min-width: 100%; }
    .quick-nav-bar       { flex-direction: column; gap: 8px; }
    .card-header-report  { flex-wrap: wrap; gap: 8px; }
    .filter-section-report { flex-direction: column; align-items: stretch; }
    .filter-action-group { margin-left: 0; flex-direction: column; }
    .btn-export-small,
    .btn-export-pdf      { width: 100%; justify-content: center; }
    .col-approved-by     { display: none; }
    .col-notes           { display: none; }
    .modal-rejection     { max-width: 92%; }
    .info-row            { flex-direction: column; }
    .info-row label      { width: 100%; margin-bottom: 2px; }
    .back-to-top         { bottom: 16px; right: 16px; }
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
        text.setAttribute('data-lang', 'hide');
        if (window.changeLanguage && window.getCurrentLanguage) {
            window.changeLanguage(window.getCurrentLanguage());
        }
    } else {
        // Hide section
        section.style.opacity = '0';
        section.style.maxHeight = '0';

        // Update button
        icon.classList.remove('fa-chevron-up');
        icon.classList.add('fa-chevron-down');
        text.setAttribute('data-lang', 'view-all');
        if (window.changeLanguage && window.getCurrentLanguage) {
            window.changeLanguage(window.getCurrentLanguage());
        }

        // Wait for transition before hiding
        setTimeout(() => {
            section.style.display = 'none';
        }, 400);
    }
}

function jumpToReportSection(sectionId, targetId, toggleButtonId, event) {
    if (event) {
        event.preventDefault();
        event.stopPropagation();
    }

    const section = document.getElementById(sectionId);
    const target = document.getElementById(targetId);

    if (!section) {
        return false;
    }

    const needsOpen = section.classList.contains('section-content');

    if (needsOpen && (section.style.display === 'none' || section.style.display === '')) {
        section.style.display = 'block';
        section.offsetHeight;
        section.style.opacity = '1';
        section.style.maxHeight = section.scrollHeight + 'px';

        if (window.changeLanguage && window.getCurrentLanguage) {
            window.changeLanguage(window.getCurrentLanguage());
        }

        const button = toggleButtonId ? document.getElementById(toggleButtonId) : null;
        if (button) {
            const icon = button.querySelector('i');
            const text = button.querySelector('.btn-toggle-text');
            if (icon) {
                icon.classList.remove('fa-chevron-down');
                icon.classList.add('fa-chevron-up');
            }
            if (text) {
                text.setAttribute('data-lang', 'hide');
            }
        }
    }

    const scrollTarget = target || section;
    requestAnimationFrame(() => {
        requestAnimationFrame(() => {
            if (scrollTarget) {
                scrollTarget.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        });
    });

    setTimeout(() => {
        const scrollTarget = target || section;
        if (scrollTarget) {
            scrollTarget.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    }, 50);

    return false;
}

// Back to Top button
window.addEventListener('scroll', function() {
    const btn = document.getElementById('backToTopBtn');
    if (btn) {
        if (document.body.scrollTop > 300 || document.documentElement.scrollTop > 300) {
            btn.style.opacity = '1';
            btn.style.visibility = 'visible';
        } else {
            btn.style.opacity = '0';
            btn.style.visibility = 'hidden';
        }
    }
});

function scrollToTop() {
    window.scrollTo({ top: 0, behavior: 'smooth' });
}
</script>

<script>
document.addEventListener('DOMContentLoaded', function(){
    var sel = document.getElementById('competencyFilterRejected');
    if (sel) {
        sel.innerHTML = '<option value="">-- Semua Jenis Kompetensi --</option>' +
                        '<option value="Tenaga Teknis">Tenaga Teknis</option>' +
                        '<option value="Pengawas Teknis">Pengawas Teknis</option>' +
                        '<option value="Pengawas Operasional">Pengawas Operasional</option>';
    }
});
</script>

<script>
// Populate competency selects from normalized data-competency values present in table rows
document.addEventListener('DOMContentLoaded', function(){
    function populateFromTable(selectId, tableId) {
        const sel = document.getElementById(selectId);
        const table = document.getElementById(tableId);
        if (!sel || !table) return;
        const rows = Array.from(table.querySelectorAll('tbody tr'));
        const set = new Set();
        rows.forEach(r => {
            const v = (r.getAttribute('data-competency') || '').toString().trim();
            if (v) set.add(v);
        });
        // ensure canonical labels present
        ['Tenaga Teknis','Pengawas Teknis','Pengawas Operasional'].forEach(l => set.add(l));
        const vals = Array.from(set).sort((a,b) => a.localeCompare(b, 'id'));
        sel.innerHTML = '';
        const opt = document.createElement('option'); opt.value = ''; opt.textContent = '-- Semua Jenis Kompetensi --'; sel.appendChild(opt);
        vals.forEach(v => { const o = document.createElement('option'); o.value = v; o.textContent = v; sel.appendChild(o); });
    }

    populateFromTable('competencyFilterRejected', 'rejectedTable');
    populateFromTable('competencyFilterRejectedReq', 'rejectedRequestTable');
    populateFromTable('competencyFilterAcceptedReq', 'acceptedRequestTable');
    populateFromTable('competencyFilterWaitingReq', 'waitingRequestTable');
    populateFromTable('competencyFilterExpiring', 'expiringCertsTable');
});
</script>

<?php require_once '../../includes/footer.php'; ?>




