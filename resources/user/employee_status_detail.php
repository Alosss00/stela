<?php
require_once dirname(__DIR__, 2) . '/bootstrap/app.php';
$page_title = 'Detail Resigned Employee';
require_once dirname(__DIR__, 2) . '/app/Helpers/auth_helper.php';
require_once dirname(__DIR__) . '/layouts/header.php';

$db = new Database();
$company_name = $_SESSION['company_name'] ?? '';

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

$whereClauseDetail = "e.id = $id";
if (!isSuperadmin() && !hasPermission('admin.access') && !empty($company_name)) {
    $whereClauseDetail .= " AND e.contractor_company = '" . $db->escapeString($company_name) . "'";
}

// Get employee and appointment details
$employee_result = $db->query("
    SELECT e.*, 
           a.id as appointment_id, a.appointment_number, a.status as appointment_status,
           u_admin.full_name as admin_name, u_admin.username as admin_username,
           a.approved_by, u2.full_name as approved_by_name,
           a.appointment_date, a.effective_date, a.expiry_date, a.notes as appointment_notes,
           a.final_approval_date, a.ktt1_approved_by, a.ktt2_approved_by
    FROM employees e
    LEFT JOIN appointments a ON e.id = a.employee_id AND a.status = 'approved'
    LEFT JOIN users u_admin ON e.verified_by = u_admin.id
    LEFT JOIN users u2 ON a.approved_by = u2.id
    WHERE $whereClauseDetail
");

if (!$employee_result) {
    die('Database error: ' . $db->escapeString('Query failed'));
}

$employee = $employee_result->fetch_assoc();
if (!$employee) {
    ?>
    <div class="employee-detail-container" style="display: flex; justify-content: center; padding-top: 50px; min-height: 60vh;">
        <div style="max-width: 600px; width: 100%;">
            <div class="alert alert-error" style="padding: 25px; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
                <h3 style="margin-top: 0; margin-bottom: 15px; display: flex; align-items: center; gap: 10px; color: inherit;">
                    <i class="fas fa-exclamation-triangle" style="font-size: 24px;"></i> Peringatan Akses
                </h3>
                <p style="margin-bottom: 25px; line-height: 1.6; font-size: 16px;">
                    Data karyawan tidak ditemukan atau Anda tidak memiliki hak akses.
                </p>
                <a href="employees_status.php" class="btn btn-secondary" style="display: inline-flex; align-items: center; gap: 8px; padding: 10px 20px;">
                    <i class="fas fa-arrow-left"></i> Kembali ke Daftar Status Karyawan
                </a>
            </div>
        </div>
    </div>
    <?php
    require_once dirname(__DIR__) . '/layouts/footer.php';
    exit();
}

$ktt_approvals = null;
if ($employee['appointment_id']) {
    $appointment_id = $employee['appointment_id'];
    $ktt_approvals = $db->query("SELECT ka.*, u.full_name as ktt_name, u.username, u.id as user_id FROM ktt_approvals ka LEFT JOIN users u ON ka.ktt_user_id = u.id WHERE ka.appointment_id = $appointment_id ORDER BY ka.approval_date ASC");
}

if (!function_exists('getKttType')) {
    function getKttType($user_id) {
        return ($user_id == 7) ? 'MSM' : 'TTN';
    }
}
?>

<div class="employee-detail-container">
    <!-- Employee Header -->
    <div class="employee-header-card">
        <div class="employee-header-content">
            <div class="employee-avatar">
                <?php echo strtoupper(substr($employee['full_name'], 0, 1)); ?>
            </div>
            <div class="employee-header-info" style="color: #495057;">
                <h2><?php echo htmlspecialchars($employee['full_name']); ?></h2>
                <p><i class="fas fa-id-badge"></i> ID Badge: <?php echo htmlspecialchars($employee['employee_code']); ?></p>
                <p><i class="fas fa-briefcase"></i> <?php echo htmlspecialchars($employee['position'] ?? '-'); ?></p>
                <p><i class="fas fa-building"></i> <?php echo htmlspecialchars($employee['contractor_company']); ?></p>
            </div>
        </div>
    </div>
    
    <!-- Information Grid -->
    <div class="info-grid">
        <!-- Basic Info -->
        <div class="info-card">
            <h4><i class="fas fa-user"></i> Basic Information</h4>
            <div class="info-row">
                <span class="info-label">Name:</span>
                <span class="info-value"><?php echo htmlspecialchars($employee['full_name']); ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">ID Badge:</span>
                <span class="info-value"><?php echo htmlspecialchars($employee['employee_code']); ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Company:</span>
                <span class="info-value"><?php echo htmlspecialchars($employee['contractor_company']); ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Employee Status:</span>
                <span class="info-value">
                    <?php if ($employee['employee_status'] == 'active'): ?>
                        <span class="badge-status badge-success">ACTIVE</span>
                    <?php else: ?>
                        <span class="badge-status badge-danger">RESIGNED</span>
                    <?php endif; ?>
                </span>
            </div>
        </div>
        
        <!-- Competency Info -->
        <div class="info-card">
            <h4><i class="fas fa-certificate"></i> Competency Information</h4>
            <div class="info-row">
                <span class="info-label">Competency Type:</span>
                <span class="info-value">
                    <?php 
                    $type = strtolower(str_replace(' ', '_', trim($employee['competency_type'] ?? '')));
                    ?>
                    <span class="competency-type-badge competency-<?= htmlspecialchars($type) ?>">
                        <?= htmlspecialchars($employee['competency_type'] ?? '-') ?>
                    </span>
                </span>
            </div>
            <div class="info-row">
                <span class="info-label">Competency Name:</span>
                <span class="info-value"><?php echo htmlspecialchars($employee['competency_name'] ?? '-'); ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Appointment No:</span>
                <span class="info-value"><?php echo htmlspecialchars($employee['appointment_number'] ?? '-'); ?></span>
            </div>
        </div>

        <!-- Resignation Info -->
        <div class="info-card" style="border: 1px solid #f5c6cb; background-color: #fdf2f2;">
            <h4 style="color: #721c24;"><i class="fas fa-user-times"></i> Resignation Information</h4>
            <div class="info-row">
                <span class="info-label">Resign Date:</span>
                <span class="info-value" style="font-weight: bold; color: #d9534f;">
                    <?php echo $employee['resign_date'] ? date('d F Y', strtotime($employee['resign_date'])) : '-'; ?>
                </span>
            </div>
            <div class="info-row">
                <span class="info-label">Reason:</span>
                <span class="info-value">
                    <?php echo nl2br(htmlspecialchars($employee['resign_reason'] ?? '-')); ?>
                </span>
            </div>
        </div>
    </div>
    
    <!-- Approval History Section -->
    <?php if ($employee['appointment_id']): ?>
    <div class="approval-section">
        <div class="approval-header">
            <h3><i class="fas fa-clipboard-check"></i> Approval History</h3>
        </div>
        
        <div class="table-responsive">
            <table class="approval-table" style="width: 100% !important; table-layout: fixed !important;">
                <thead>
                    <tr>
                        <th data-lang="step">Step</th>
                        <th data-lang="name-username">Name / Username</th>
                        <th data-lang="action">Action</th>
                        <th data-lang="date">Date</th>
                        <th data-lang="notes">Notes</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- Admin Verification -->
                    <?php if ($employee['verified_by']): ?>
                    <tr class="admin-row">
                        <td>
                            <span class="step-badge">
                                <i class="fas fa-shield-alt"></i> Admin Verification
                            </span>
                        </td>
                        <td>
                            <?php echo htmlspecialchars($employee['admin_name']); ?><br>
                            <small class="text-muted"><?php echo htmlspecialchars($employee['admin_username']); ?></small>
                        </td>
                        <td>
                            <span class="action-badge accepted">
                                <i class="fas fa-check"></i> Approved
                            </span>
                        </td>
                        <td>
                            <span class="text-muted"><?php echo $employee['verified_date'] ? date('d/m/Y H:i', strtotime($employee['verified_date'])) : '-'; ?></span>
                        </td>
                        <td>Data & Certificate Verified</td>
                    </tr>
                    <?php else: ?>
                    <tr class="admin-row">
                        <td>
                            <span class="step-badge">
                                <i class="fas fa-shield-alt"></i> Admin Verification
                            </span>
                        </td>
                        <td colspan="4" class="text-muted">
                            <i class="fas fa-clock"></i> Waiting for Admin Verification
                        </td>
                    </tr>
                    <?php endif; ?>
                    
                    <!-- KTT Approvals -->
                    <?php if ($ktt_approvals && $ktt_approvals->num_rows > 0): ?>
                        <?php 
                        while ($ktt = $ktt_approvals->fetch_assoc()): 
                            $ktt_type = getKttType($ktt['user_id']);
                        ?>
                        <tr>
                            <td>
                                <span class="step-badge">
                                    <i class="fas fa-check-circle"></i> KTT Approval (<?php echo $ktt_type; ?>)
                                </span>
                            </td>
                            <td>
                                <?php echo htmlspecialchars($ktt['ktt_name']); ?><br>
                                <small class="text-muted"><?php echo htmlspecialchars($ktt['username']); ?></small>
                            </td>
                            <td>
                                <?php if ($ktt['action'] == 'approve'): ?>
                                    <span class="action-badge accepted">
                                        <i class="fas fa-check"></i> Approved
                                    </span>
                                <?php else: ?>
                                    <span class="action-badge rejected">
                                        <i class="fas fa-times"></i> Rejected
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php
                                echo $ktt['approval_date'] ? date('d/m/Y H:i', strtotime($ktt['approval_date'])) : 'N/A';
                                ?>
                            </td>
                            <td><?php echo nl2br(htmlspecialchars($ktt['approval_notes'] ?? '-')); ?></td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td>
                                <span class="step-badge">
                                    <i class="fas fa-check-circle"></i> KTT Approval
                                </span>
                            </td>
                            <td colspan="4" class="text-muted">
                                <i class="fas fa-clock"></i> Waiting for KTT Approval
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
    
    <div class="action-buttons">
        <a href="employees_status.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Back
        </a>
    </div>
</div>

<?php require_once dirname(__DIR__) . '/layouts/footer.php'; ?>
