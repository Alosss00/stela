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
    
    <!-- Statistics Cards -->
    <div class="stats-cards-row">
        <div class="stat-box stat-box-total">
            <div class="stat-number"><?php echo $total_employees; ?></div>
            <div class="stat-label" data-lang="all-employees">Seluruh Karyawan</div>
        </div>
        <div class="stat-box stat-box-verified">
            <div class="stat-number"><?php echo $verified_count; ?></div>
            <div class="stat-label" data-lang="accepted">Disetujui</div>
        </div>
        <div class="stat-box stat-box-pending">
            <div class="stat-number"><?php echo $pending_count; ?></div>
            <div class="stat-label" data-lang="pending">Menunggu</div>
        </div>
        <div class="stat-box stat-box-rejected">
            <div class="stat-number"><?php echo $rejected_count_stat; ?></div>
            <div class="stat-label" data-lang="rejected">Tidak disetujui</div>
        </div>
    </div>
    
    <!-- Bonsai.io Pagination Search Section -->
    <style>
    .es-autocomplete-dropdown {
        position: absolute;
        top: calc(100% + 6px);
        left: 0;
        right: 0;
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.15), 0 8px 10px -6px rgba(0, 0, 0, 0.1);
        z-index: 9999;
        max-height: 320px;
        overflow-y: auto;
        display: none;
    }
    .es-suggestion-item {
        padding: 10px 16px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        cursor: pointer;
        border-bottom: 1px solid #f1f5f9;
        transition: background 0.15s ease;
    }
    .es-suggestion-item:last-child {
        border-bottom: none;
    }
    .es-suggestion-item:hover, .es-suggestion-item.active {
        background-color: #f1f5f9;
    }
    .es-sug-name {
        font-weight: 600;
        color: #1e293b;
        font-size: 14px;
    }
    .es-sug-sub {
        font-size: 12px;
        color: #64748b;
        margin-top: 2px;
    }
    .es-sug-badge {
        font-size: 11px;
        font-weight: 600;
        background: #e2e8f0;
        color: #334155;
        padding: 3px 8px;
        border-radius: 6px;
    }
    .dataTables_filter, #employeesTable_filter {
        display: none !important;
    }
    </style>

    <div class="card" style="margin-bottom: 16px; padding: 14px 18px; background: #ffffff; border-radius: 10px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); border: 1px solid #e9ecef;">
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

<style>
  .employees-container {
      padding: 20px 0;
  }
  
  /* Page Header */
  .page-header-custom {
      background: #F57C00;
      color: white;
      padding: 35px 30px;
      border-radius: 10px;
      margin-bottom: 30px;
      display: flex;
      justify-content: space-between;
      align-items: center;
      box-shadow: 0 4px 15px rgba(245, 124, 0, 0.3);
  }
  
  .header-content h2 {
      margin: 0 0 5px 0;
      font-size: 26px;
      font-weight: 600;
      display: flex;
      align-items: center;
      gap: 10px;
  }
  
  .header-content p {
      margin: 0;
      opacity: 0.9;
      font-size: 14px;
  }
  
  .btn-lg-custom {
      padding: 12px 25px;
      font-size: 15px;
      white-space: nowrap;
      background: #37474F;
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
  }
  
  .btn-lg-custom:hover {
      transform: translateY(-2px);
      box-shadow: 0 6px 15px rgba(0, 0, 0, 0.3);
  }
  
  /* Alert for Resubmit */
  .alert-resubmit {
      display: flex;
      gap: 15px;
      align-items: flex-start;
      padding: 20px;
      margin-bottom: 30px;
      border-radius: 8px;
      border-left: 4px solid #f59e0b;
      background: #fef3c7;
  }
  
  .alert-resubmit i {
      color: #f59e0b;
      font-size: 24px;
      margin-top: 2px;
  }
  
  .alert-resubmit strong {
      display: block;
      color: #92400e;
      margin-bottom: 5px;
      font-size: 16px;
  }
  
  .alert-resubmit p {
      margin: 0;
      color: #92400e;
      font-size: 14px;
  }
  
  /* Statistics Cards */
  .stats-cards-row {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
      gap: 15px;
      margin-bottom: 30px;
  }
  
  .stat-box {
      background: white;
      padding: 20px;
      border-radius: 8px;
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
      border-left: 4px solid #ccc;
      transition: all 0.3s ease;
  }
  
  .stat-box:hover {
      transform: translateY(-3px);
      box-shadow: 0 4px 15px rgba(0, 0, 0, 0.12);
  }
  
  .stat-box-total { border-left-color: #37474F; }
  .stat-box-verified { border-left-color: #2E7D32; }
  .stat-box-pending { border-left-color: #f59e0b; }
  .stat-box-rejected { border-left-color: #ef4444; }
  
  .stat-number {
      font-size: 28px;
      font-weight: 700;
      color: #333;
  }
  
  .stat-label {
      color: #666;
      font-size: 13px;
      margin-top: 5px;
  }
  
  /* Card Header */
  .card-header-custom {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 20px;
      border-bottom: 2px solid #f0f0f0;
  }
  
  .card-header-custom h3 {
      margin: 0;
      font-size: 18px;
      color: #333;
      display: flex;
      align-items: center;
      gap: 10px;
  }
  
  .card-header-custom i {
      color: #37474F;
  }
  
  .header-info {
      display: flex;
      gap: 10px;
  }
  
  .info-badge {
      background: #f0f0f0;
      padding: 5px 12px;
      border-radius: 15px;
      font-size: 12px;
      font-weight: 600;
      color: #666;
  }
  
  /* Table Styling */
  .table-employees {
      margin: 0;
  }
  
  .table-employees thead th {
      background: #f8f9fa;
      color: #333;
      font-weight: 600;
      font-size: 12px;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      border: none;
      padding: 15px;
  }
  
  .employee-row {
      border-bottom: 1px solid #f0f0f0;
      transition: background-color 0.2s ease;
  }
  
  .employee-row:hover {
      background-color: #f8f9ff;
  }
  
  .table-employees td {
      padding: 15px;
      vertical-align: middle;
      font-size: 13px;
  }
  
  .col-code { width: 100px; }
  .col-name { width: auto; }
  .col-position { width: 120px; }
  .col-cert { width: 140px; }
  .col-status { width: 130px; }
  .col-verified { width: 140px; }
  .col-action { width: 100px; }
  
  .code-badge {
      background: #ECEFF1;
      color: #37474F;
      padding: 4px 10px;
      border-radius: 6px;
      font-size: 12px;
      font-weight: 600;
  }
  
  .employee-name-card {
      color: #333;
      font-size: 14px;
  }
  
  .position-tag {
      background: #f3f4f6;
      color: #666;
      padding: 4px 10px;
      border-radius: 6px;
      font-size: 12px;
  }
  
  .cert-progress {
      display: flex;
      align-items: center;
      gap: 10px;
  }
  
  .cert-count {
      font-weight: 600;
      color: #333;
      min-width: 30px;
      font-size: 12px;
  }
  
  .progress-bar-mini {
      height: 6px;
      background: #e5e7eb;
      border-radius: 3px;
      overflow: hidden;
  }
  
  .progress-fill {
      height: 100%;
      background: linear-gradient(90deg, #37474F, #37474F);
      transition: width 0.3s ease;
  }
  
  .badge-status {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      font-size: 11px;
      padding: 6px 10px;
  }
  
  .verified-info {
      font-size: 12px;
  }
  
  .verified-name {
      color: #333;
      font-weight: 600;
      display: block;
  }
  
  .verified-date {
      color: #999;
  }
  
  .btn-action {
      padding: 6px 12px;
      font-size: 12px;
  }
  
  .btn-action:hover {
      transform: translateY(-1px);
  }
  
  /* Empty State */
  .empty-state {
      text-align: center;
      padding: 60px 20px;
      color: #999;
  }
  
  .empty-state i {
      font-size: 48px;
      margin-bottom: 15px;
      opacity: 0.5;
  }
  
  .empty-state p {
      margin: 0;
      font-size: 16px;
  }
  
  /* Action Footer */
  .action-footer {
      margin-top: 25px;
      padding-top: 20px;
      border-top: 1px solid #f0f0f0;
  }
  
  /* Action Buttons */
  .action-buttons {
      display: flex;
      gap: 8px;
      flex-wrap: wrap;
  }
  
  .btn-sm {
      padding: 6px 12px;
      font-size: 12px;
      border-radius: 4px;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      gap: 5px;
      transition: all 0.3s ease;
  }
  
  .btn-warning {
      background: #f59e0b;
      color: white;
      border: none;
  }
  
  .btn-warning:hover {
      background: #d97706;
      transform: translateY(-1px);
      box-shadow: 0 2px 8px rgba(245, 158, 11, 0.3);
  }
  
  /* Responsive */
  @media (max-width: 1024px) {
      .page-header-custom {
          flex-direction: column;
          gap: 20px;
          text-align: center;
      }
      
      .stats-cards-row {
          grid-template-columns: repeat(2, 1fr);
      }
      
      .col-verified { display: none; }
  }
  
  @media (max-width: 768px) {
      .page-header-custom {
          padding: 20px 15px;
      }
      
      .header-content h2 {
          font-size: 20px;
      }
      
      .stats-cards-row {
          grid-template-columns: repeat(2, 1fr);
      }
      
      .card-header-custom {
          flex-direction: column;
          gap: 15px;
          text-align: center;
      }
      
      .table-responsive {
          font-size: 12px;
      }
      
      .col-code { width: 80px; }
      .col-position { display: none; }
      .col-cert { display: none; }
      .col-verified { display: none; }
  }
  
  @media (max-width: 480px) {
      .stats-cards-row {
          grid-template-columns: 1fr;
      }
  }
  </style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('esSearchInput');
    const dropdown = document.getElementById('esSuggestionsDropdown');
    const empRows = document.querySelectorAll('#employeesTable tbody tr.emp-row');
    const clearBtn = document.getElementById('esClearBtn');
    let debounceTimer = null;

    if (!searchInput) return;

    if (clearBtn) {
        clearBtn.addEventListener('click', function() {
            searchInput.value = '';
            filterTableLive('');
            clearBtn.style.display = 'none';
            if (dropdown) {
                dropdown.style.display = 'none';
                dropdown.innerHTML = '';
            }
            searchInput.focus();
        });
    }

    function filterTableLive(query, matchingIds = null) {
        const cleanQ = query.toLowerCase().trim();
        empRows.forEach(row => {
            const rowId = row.dataset.id;
            const textContent = row.textContent.toLowerCase();
            let isMatch = false;

            if (cleanQ === '') {
                isMatch = true;
            } else if (matchingIds !== null && matchingIds.size > 0) {
                isMatch = matchingIds.has(rowId) || textContent.includes(cleanQ);
            } else {
                isMatch = textContent.includes(cleanQ);
            }

            row.style.display = isMatch ? '' : 'none';
        });
    }

    searchInput.addEventListener('input', function() {
        const query = this.value.trim();
        clearTimeout(debounceTimer);

        if (clearBtn) {
            clearBtn.style.display = query.length > 0 ? 'block' : 'none';
        }

        filterTableLive(query);

        if (query.length < 1) {
            if (dropdown) {
                dropdown.style.display = 'none';
                dropdown.innerHTML = '';
            }
            return;
        }

        debounceTimer = setTimeout(() => {
            fetch('../../api/search_elasticsearch.php?target=employees&q=' + encodeURIComponent(query) + '&limit=100')
                .then(res => {
                    if (!res.ok) throw new Error('HTTP ' + res.status);
                    const ct = res.headers.get('content-type') || '';
                    if (!ct.includes('application/json')) throw new Error('Non-JSON response');
                    return res.json();
                })
                .then(data => {
                    if (data && data.status === 'success' && data.items) {
                        const matchingIds = new Set(data.items.map(item => String(item.id)));
                        filterTableLive(query, matchingIds);

                        if (dropdown && data.items.length > 0) {
                            renderSuggestions(data.items.slice(0, 8));
                        } else if (dropdown) {
                            dropdown.style.display = 'none';
                        }
                    }
                })
                .catch(err => console.warn('Elasticsearch live search notice:', err.message));
        }, 150);
    });

    function renderSuggestions(items) {
        if (!dropdown) return;
        dropdown.innerHTML = '';
        items.forEach(item => {
            const div = document.createElement('div');
            div.className = 'es-suggestion-item';
            div.innerHTML = `
                <div>
                    <div class="es-sug-name">${escapeHtml(item.full_name || item.employee_code)}</div>
                    <div class="es-sug-sub">${escapeHtml(item.position || '')} &bull; ${escapeHtml(item.contractor_company || '')}</div>
                </div>
                <span class="es-sug-badge">${escapeHtml(item.employee_code || '')}</span>
            `;
            div.addEventListener('click', function() {
                searchInput.value = item.full_name || item.employee_code;
                filterTableLive(searchInput.value);
                dropdown.style.display = 'none';
            });
            dropdown.appendChild(div);
        });
        dropdown.style.display = 'block';
    }

    function escapeHtml(text) {
        if (!text) return '';
        return String(text).replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;");
    }

    document.addEventListener('click', function(e) {
        if (dropdown && !dropdown.contains(e.target) && e.target !== searchInput) {
            dropdown.style.display = 'none';
        }
    });
});
</script>

<?php require_once dirname(__DIR__) . '/layouts/footer.php'; ?>




