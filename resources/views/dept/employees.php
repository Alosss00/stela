<?php
$page_title = 'Employees';
require_once dirname(__DIR__, 3) . '/app/Helpers/auth_helper.php';
// Included via bootstrap/app.php
// Included via bootstrap/app.php

// Only department_user role or user with department can access this page
if (!hasDepartment() && $_SESSION['role'] != 'department_user') {
    header('Location: ../admin/dashboard.php');
    exit();
}

require_once dirname(__DIR__) . '/layouts/header.php';

$db = new Database();
$department = $_SESSION['department'] ?? '';

// Get statistics for current department
$total_employees = $db->query("SELECT COUNT(*) as count FROM employees WHERE department = '" . $db->escapeString($department) . "' AND is_active = 1")->fetch_assoc()['count'];
$verified_count = $db->query("SELECT COUNT(*) as count FROM employees WHERE department = '" . $db->escapeString($department) . "' AND verification_status = 'verified' AND is_active = 1")->fetch_assoc()['count'];
$pending_count = $db->query("SELECT COUNT(*) as count FROM employees WHERE department = '" . $db->escapeString($department) . "' AND verification_status = 'pending' AND is_active = 1")->fetch_assoc()['count'];
$rejected_count_stat = $db->query("SELECT COUNT(*) as count FROM employees WHERE department = '" . $db->escapeString($department) . "' AND verification_status = 'rejected' AND is_active = 1")->fetch_assoc()['count'];

// Get all employees for current department with appointment status
$employees = $db->query("
    SELECT e.*, 
           COUNT(ec.id) as cert_count,
           SUM(CASE WHEN ec.verification_status = 'verified' THEN 1 ELSE 0 END) as verified_cert_count,
           GROUP_CONCAT(ec.cert_number SEPARATOR ', ') as cert_numbers,
           u.full_name as verified_by_name,
           e.resubmit_count,
           e.resubmit_date,
           MAX(a.status) as appointment_status,
           MAX(a.approval_notes) as ktt_rejection_notes,
           MAX(CASE WHEN ka.action = 'reject' THEN 1 ELSE 0 END) as has_ktt_rejection,
           CASE 
               WHEN MAX(CASE WHEN ka.action = 'reject' THEN 1 ELSE 0 END) = 1 AND e.verification_status = 'pending' AND e.resubmit_date IS NOT NULL THEN 'pending'
               WHEN MAX(CASE WHEN ka.action = 'reject' THEN 1 ELSE 0 END) = 1 THEN 'rejected'
               WHEN MAX(a.status) = 'rejected' THEN 'rejected'
               WHEN e.verification_status = 'rejected' THEN 'rejected'
               ELSE e.verification_status
           END as combined_status
    FROM employees e
    LEFT JOIN employee_certifications ec ON e.id = ec.employee_id
    LEFT JOIN users u ON e.verified_by = u.id
    LEFT JOIN appointments a ON e.id = a.employee_id
    LEFT JOIN ktt_approvals ka ON a.id = ka.appointment_id
    WHERE e.is_active = 1 AND e.department = '" . $db->escapeString($department) . "'
    GROUP BY e.id
    ORDER BY combined_status, e.created_at DESC
");

// Update employee status jika appointment sudah approved
if ($employees && $employees->num_rows > 0) {
    $employees->data_seek(0);
    while ($row = $employees->fetch_assoc()) {
        if ($row['appointment_status'] == 'approved' && $row['verification_status'] == 'pending') {
            $db->query("UPDATE employees SET verification_status = 'verified' WHERE id = " . intval($row['id']));
        }
    }
    // Refresh employees
    $employees = $db->query("SELECT e.*, COUNT(ec.id) as cert_count, SUM(CASE WHEN ec.verification_status = 'verified' THEN 1 ELSE 0 END) as verified_cert_count, GROUP_CONCAT(ec.cert_number SEPARATOR ', ') as cert_numbers, u.full_name as verified_by_name, e.resubmit_count, e.resubmit_date, MAX(a.status) as appointment_status, MAX(a.approval_notes) as ktt_rejection_notes, MAX(CASE WHEN ka.action = 'reject' THEN 1 ELSE 0 END) as has_ktt_rejection, CASE WHEN MAX(CASE WHEN ka.action = 'reject' THEN 1 ELSE 0 END) = 1 AND e.verification_status = 'pending' AND e.resubmit_date IS NOT NULL THEN 'pending' WHEN MAX(CASE WHEN ka.action = 'reject' THEN 1 ELSE 0 END) = 1 THEN 'rejected' WHEN MAX(a.status) = 'rejected' THEN 'rejected' WHEN e.verification_status = 'rejected' THEN 'rejected' ELSE e.verification_status END as combined_status FROM employees e LEFT JOIN employee_certifications ec ON e.id = ec.employee_id LEFT JOIN users u ON e.verified_by = u.id LEFT JOIN appointments a ON e.id = a.employee_id LEFT JOIN ktt_approvals ka ON a.id = ka.appointment_id WHERE e.is_active = 1 AND e.department = '" . $db->escapeString($department) . "' GROUP BY e.id ORDER BY combined_status, e.created_at DESC");
}

// Count rejected employees (including KTT rejections, but exclude resubmitted ones)
$rejected_count = $db->query("
    SELECT COUNT(DISTINCT e.id) as count 
    FROM employees e
    LEFT JOIN appointments a ON e.id = a.employee_id
    LEFT JOIN ktt_approvals ka ON a.id = ka.appointment_id AND ka.action = 'reject'
    WHERE e.department = '" . $db->escapeString($department) . "' 
    AND (
        (e.verification_status = 'rejected') 
        OR 
        (ka.id IS NOT NULL AND NOT (e.verification_status = 'pending' AND e.resubmit_date IS NOT NULL))
        OR
        (a.status = 'rejected' AND NOT (e.verification_status = 'pending' AND e.resubmit_date IS NOT NULL))
    )
")->fetch_assoc()['count'];
?>

<div class="employees-container">
    <!-- Page Header -->
    <div class="page-header-custom">
        <div class="header-content">
            <h2><i class="fas fa-users"></i> <span data-lang="employee-list">Employee List</span></h2>
            <p><?php echo htmlspecialchars($department); ?></p>
        </div>
        <a href="add_employee.php" class="btn btn-primary btn-lg-custom">
            <i class="fas fa-plus-circle"></i> New Request
        </a>
    </div>
    
    <!-- Rejected Data Alert -->
    <?php if ($rejected_count > 0): ?>
    <div class="alert alert-warning alert-resubmit">
        <i class="fas fa-exclamation-triangle"></i>
        <div>
            <strong data-lang="rejected-data">Rejected Data!</strong>
            <p>
                <span data-lang="there-are">There are</span> <strong><?php echo $rejected_count; ?></strong> <span data-lang="rejected-employee-data-suffix">rejected employee data that need to be corrected. Please click the "Upload Correction" button to resubmit the corrected data.</span>
            </p>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Statistics Cards (Image 2 DaisyUI Style) -->
    <div class="stats-daisy-container">
        <div class="daisy-stat stat-total">
            <div class="daisy-stat-title" data-lang="all-employees">ALL EMPLOYEES</div>
            <div class="daisy-stat-row">
                <div class="daisy-stat-value val-total"><?php echo $total_employees; ?></div>
                <div class="daisy-stat-figure fig-total"><i class="fas fa-users"></i></div>
            </div>
            <div class="daisy-stat-desc">Total registered workforce</div>
        </div>

        <div class="daisy-stat stat-verified">
            <div class="daisy-stat-title" data-lang="accepted">ACCEPT</div>
            <div class="daisy-stat-row">
                <div class="daisy-stat-value val-verified"><?php echo $verified_count; ?></div>
                <div class="daisy-stat-figure fig-verified"><i class="fas fa-user-check"></i></div>
            </div>
            <div class="daisy-stat-desc desc-verified">Verified & active</div>
        </div>

        <div class="daisy-stat stat-pending">
            <div class="daisy-stat-title" data-lang="pending">PENDING</div>
            <div class="daisy-stat-row">
                <div class="daisy-stat-value val-pending"><?php echo $pending_count; ?></div>
                <div class="daisy-stat-figure fig-pending"><i class="fas fa-hourglass-half"></i></div>
            </div>
            <div class="daisy-stat-desc desc-pending">Awaiting review</div>
        </div>

        <div class="daisy-stat stat-rejected">
            <div class="daisy-stat-title" data-lang="rejected">REJECT</div>
            <div class="daisy-stat-row">
                <div class="daisy-stat-value val-rejected"><?php echo $rejected_count_stat; ?></div>
                <div class="daisy-stat-figure fig-rejected"><i class="fas fa-user-times"></i></div>
            </div>
            <div class="daisy-stat-desc desc-rejected">Needs correction</div>
        </div>
    </div>
    
    <!-- Bonsai.io Pagination Search Section -->
    

    <div class="card es-search-card" style="margin-bottom: 16px; padding: 14px 18px; background: #ffffff; border-radius: 10px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); border: 1px solid #e9ecef; position: relative; z-index: 1050; overflow: visible;">
        <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
            <div style="flex: 1; min-width: 200px; position: relative;">
                <i class="fas fa-search" style="position: absolute; left: 13px; top: 50%; transform: translateY(-50%); color: #6c757d; font-size: 14px; z-index: 2;"></i>
                <input type="text" id="esSearchInput" autocomplete="off"
                       placeholder="Cari Nama, ID Badge, Posisi..."
                       style="width:100%; padding-left: 38px; padding-right: 36px; height: 40px; border-radius: 8px; border: 1px solid #ced4da; font-size: 14px;">
                <button type="button" id="esClearBtn" title="Bersihkan"
                        style="display:none; position: absolute; right: 9px; top: 50%; transform: translateY(-50%); background: none; border: none; color: #a0aec0; cursor: pointer; font-size: 16px; padding: 0; z-index: 2;">
                    <i class="fas fa-times-circle"></i>
                </button>
            </div>
            <select id="filterDeptEmpStatus" style="height:40px; border-radius:8px; border:1px solid #ced4da; padding: 0 10px; font-size:13px; min-width:130px;">
                <option value="">Semua Status</option>
                <option value="pending">Pending</option>
                <option value="verified">Verified</option>
                <option value="rejected">Rejected</option>
            </select>
            <select id="filterDeptCompetencyType" style="height:40px; border-radius:8px; border:1px solid #ced4da; padding: 0 10px; font-size:13px; min-width:170px;">
                <option value="">Semua Kompetensi</option>
                <option value="pengawas_operasional">Pengawas Operasional</option>
                <option value="pengawas_teknis">Pengawas Teknis</option>
                <option value="tenaga_teknis">Tenaga Teknis</option>
            </select>
            <select id="deptEmpPageLimit" style="height:40px; border-radius:8px; border:1px solid #ced4da; padding: 0 10px; font-size:13px; min-width:90px;">
                <option value="10">10 / hal</option>
                <option value="25">25 / hal</option>
                <option value="50">50 / hal</option>
            </select>
        </div>
        <div id="deptEmpInfoContainer" style="margin-top:8px; font-size:13px; color:#6c757d;"></div>
    </div>

    <!-- Employees Table Card -->
    <div class="card">
        <div class="card-header-custom">
            <h3><i class="fas fa-list"></i> <span data-lang="complete-employee-list">Complete Employee List</span></h3>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-employees" id="employeesTable">
                    <thead>
                        <tr>
                            <th data-lang="id-badge">ID BADGE</th>
                            <th data-lang="name">Name</th>
                            <th data-lang="position">Position</th>
                            <th data-lang="competency-type">Competency Type</th>
                            <th data-lang="competency">Competency</th>
                            <th data-lang="status">Status</th>
                            <th data-lang="action">Action</th>
                        </tr>
                    </thead>
                    <tbody id="deptEmpTbody">
                        <tr><td colspan="7" style="text-align:center;padding:28px;color:#a0aec0;"><i class="fas fa-circle-notch fa-spin"></i> Memuat data...</td></tr>
                    </tbody>
                </table>
            </div>
            <div style="display:flex; justify-content:space-between; align-items:center; margin-top:12px; flex-wrap:wrap; gap:8px;">
                <div id="deptEmpInfoContainerTable" style="font-size:13px; color:#6c757d;"></div>
                <div id="deptEmpPaginationContainer"></div>
            </div>

            <script src="../../assets/js/bonsai_pagination.js?v=<?php echo time(); ?>"></script>
            <script>
            (function() {
                const deptName = <?php echo json_encode($_SESSION['department'] ?? ''); ?>;
                const competencyLabels = {
                    'pengawas_operasional': 'Pengawas Operasional',
                    'pengawas_teknis': 'Pengawas Teknis',
                    'tenaga_teknis': 'Tenaga Teknis'
                };
                const statusBadges = {
                    'verified': '<span class="badge badge-success" data-lang="accept">Disetujui</span>',
                    'pending': '<span class="badge badge-warning" data-lang="pending">Menunggu</span>',
                    'rejected': '<span class="badge badge-danger" data-lang="reject">Tidak disetujui</span>'
                };

                window.deptEmpPagination = new BonsaiPagination({
                    apiUrl: '../../api/search_elasticsearch.php',
                    target: 'employees',
                    tableSelector: '#employeesTable',
                    tbodySelector: '#deptEmpTbody',
                    searchInputSelector: '#esSearchInput',
                    clearBtnSelector: '#esClearBtn',
                    paginationContainerSelector: '#deptEmpPaginationContainer',
                    infoContainerSelector: '#deptEmpInfoContainer',
                    limitSelector: '#deptEmpPageLimit',
                    filters: {
                        department: deptName
                    },
                    filterSelectors: {
                        status: '#filterDeptEmpStatus',
                        competency_type: '#filterDeptCompetencyType'
                    },
                    defaultLimit: 10,
                    renderRow: function(item, index, rowNum) {
                        const status = item.approval_status || 'pending';
                        const compType = item.competency_type || '';
                        const compLabel = competencyLabels[compType] || compType;
                        const badge = statusBadges[status] || `<span class="badge">${status}</span>`;
                        const resubmitBtn = status === 'rejected'
                            ? `<a href="resubmit_employee.php?id=${item.id}" class="btn btn-sm btn-warning" title="Resubmit"><i class="fas fa-upload"></i> <span data-lang="resubmit">Resubmit</span></a>`
                            : '';
                        return `<tr class="emp-row" data-id="${item.id}">
                            <td><strong>${item.employee_code || '-'}</strong></td>
                            <td>${item.full_name || '-'}</td>
                            <td>${item.position || '-'}</td>
                            <td>${compLabel}</td>
                            <td>${item.competency_name || item.sub_competency || '-'}</td>
                            <td>${badge}</td>
                            <td>
                                <div class="action-buttons">
                                    <a href="employee_detail.php?id=${item.id}" class="btn btn-sm btn-info" title="View Details"><i class="fas fa-eye"></i> <span data-lang="view">View</span></a>
                                    ${resubmitBtn}
                                </div>
                            </td>
                        </tr>`;
                    }
                });

                // Pre-set department filter
                window.deptEmpPagination.filters['department'] = deptName;

                // Sync info
                const origInfo = window.deptEmpPagination.renderInfo.bind(window.deptEmpPagination);
                window.deptEmpPagination.renderInfo = function() {
                    origInfo();
                    const src = document.querySelector('#deptEmpInfoContainer');
                    const dest = document.querySelector('#deptEmpInfoContainerTable');
                    if (src && dest) dest.innerHTML = src.innerHTML;
                };
            })();
            </script>
        </div>
    </div>
    
    <!-- Back Button -->
    <div class="action-footer">
        <a href="dashboard.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> <span data-lang="back-to-dashboard">Back to Dashboard</span>
        </a>
    </div>
</div>



<?php require_once dirname(__DIR__) . '/layouts/footer.php'; ?>
