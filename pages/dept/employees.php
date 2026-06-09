<?php
$page_title = 'Employees';
require_once '../../includes/auth.php';
require_once '../../includes/db.php';

// Only department_user role or user with department can access this page
if (!hasDepartment() && $_SESSION['role'] != 'department_user') {
    header('Location: ../admin/dashboard.php');
    exit();
}

require_once '../../includes/header.php';

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
    
    <!-- Employees Table Card -->
    <div class="card">
        <div class="card-header-custom">
            <h3><i class="fas fa-list"></i> <span data-lang="complete-employee-list">Complete Employee List</span></h3>
            <div class="header-info">
                <span class="info-badge"><span data-lang="all-employees-label">All employees:</span> <?php echo $total_employees; ?></span>
            </div>
        </div>
        <div class="card-body">
            <?php if ($employees->num_rows > 0): ?>
                <div class="table-responsive">
                    <table class="table table-employees">
                        <thead>
                            <tr>
                                <th data-lang="id-badge">ID BADGE</th>
                                <th data-lang="name">Name</th>
                                <th data-lang="position">Position</th>
                                <th data-lang="competency-type">Competency Type</th>
                                <th data-lang="competency">Competency</th>
                                <th data-lang="certificate-number">Certificate Number</th>
                                <th data-lang="certification">Certification</th>
                                <th data-lang="status">Status</th>
                                <th data-lang="action">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $type_labels = [
                                'pengawas_operasional' => 'Pengawas Operasional',
                                'pengawas_teknis' => 'Pengawas Teknis',
                                'tenaga_teknis' => 'Tenaga Teknis'
                            ];
                            
                            $employees->data_seek(0);
                            while ($row = $employees->fetch_assoc()): 
                                $type_key = $row['competency_type'] ?? '';
                                $type_label = $type_labels[$type_key] ?? $type_key;
                                $final_status = $row['combined_status'];
                            ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($row['employee_code']); ?></strong></td>
                                <td><?php echo htmlspecialchars($row['full_name']); ?></td>
                                <td><?php echo htmlspecialchars($row['position']); ?></td>
                                <td><?php echo htmlspecialchars($type_label); ?></td>
                                <td><?php echo htmlspecialchars($row['competency_name'] ?? '-'); ?></td>
                                <td>
                                    <span class="badge badge-cert">
                                        <?php echo htmlspecialchars($row['cert_numbers'] ?? '-'); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge badge-info">
                                        <?php echo ($row['verified_cert_count'] ?? 0) . '/' . ($row['cert_count'] ?? 0); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php
                                    $final_status = $row['combined_status'];
                                    $status_badges = [
                                        'verified' => '<span class="badge badge-success" data-lang="accept">Disetujui</span>',
                                        'pending' => '<span class="badge badge-warning" data-lang="pending">Menunggu</span>',
                                        'rejected' => '<span class="badge badge-danger" data-lang="reject">Tidak disetujui</span>'
                                    ];
                                    echo $status_badges[$final_status] ?? '';
                                    
                                    // Show rejection source
                                    if ($final_status == 'rejected' && !empty($row['ktt_rejection_notes'])) {
                                        echo '<br><small class="text-muted" data-lang="rejected-by-ktt">Rejected by KTT</small>';
                                    }
                                    ?>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <a href="employee_detail.php?id=<?php echo $row['id']; ?>" class="btn btn-sm btn-info" title="View Details" data-lang-title="view-details">
                                            <i class="fas fa-eye"></i> <span data-lang="view">View</span>
                                        </a>
                                        <?php if ($final_status == 'rejected'): ?>
                                        <a href="resubmit_employee.php?id=<?php echo $row['id']; ?>" class="btn btn-sm btn-warning" title="Upload Correction" data-lang-title="upload-correction">
                                            <i class="fas fa-upload"></i> <span data-lang="resubmit">Resubmit</span>
                                        </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-inbox"></i>
                    <p data-lang="no-employee-data">No employee data</p>
                </div>
            <?php endif; ?>
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

<?php require_once '../../includes/footer.php'; ?>




