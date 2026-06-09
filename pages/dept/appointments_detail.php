<?php
$page_title = 'Appointment Letter Detail';
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
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Get appointment details - ensure it belongs to this department

$appointment = $db->query("SELECT a.*, e.full_name as employee_name, e.employee_code, e.position, e.department, e.contractor_company, e.verified_by as admin_verified_by, e.competency_name, p.position_name, p.position_type, u1.full_name as created_by_name, u2.full_name as approved_by_name, u_admin.full_name as admin_name, u_admin.username as admin_username, ktt1.full_name as ktt1_name, ktt2.full_name as ktt2_name FROM appointments a JOIN employees e ON a.employee_id = e.id JOIN positions p ON a.position_id = p.id LEFT JOIN users u1 ON a.created_by = u1.id LEFT JOIN users u2 ON a.approved_by = u2.id LEFT JOIN users u_admin ON e.verified_by = u_admin.id LEFT JOIN users ktt1 ON a.ktt1_approved_by = ktt1.id LEFT JOIN users ktt2 ON a.ktt2_approved_by = ktt2.id WHERE a.id = $id AND e.department = '" . $db->escapeString($department) . "'") ->fetch_assoc();

if (!$appointment) {
    header('Location: appointments.php');
    exit();
}

// Get KTT approval details
$ktt_approvals = $db->query("SELECT ka.*, u.full_name as ktt_name, u.username, u.id as user_id FROM ktt_approvals ka LEFT JOIN users u ON ka.ktt_user_id = u.id WHERE ka.appointment_id = $id ORDER BY ka.approval_date ASC");

// Helper function to get KTT type (MSM or TTN)
function getKttType($user_id) {
    return ($user_id == 7) ? 'MSM' : 'TTN';
}
?>

<style>
.appointment-detail-container {
    max-width: 1400px;
    margin: 0 auto;
}

.appointment-header-card {
    background: linear-gradient(135deg, #FFA240, #F57C00);
    color: white;
    border-radius: 10px;
    padding: 30px;
    margin-bottom: 20px;
    box-shadow: 0 4px 15px rgba(245, 124, 0, 0.3);
}

.appointment-header-content {
    display: flex;
    align-items: center;
    gap: 30px;
}

.appointment-icon {
    width: 120px;
    height: 120px;
    border-radius: 50%;
    background: #FFE7C2;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 48px;
    color: #F57C00;
    box-shadow: 0 4px 15px rgba(245, 124, 0, 0.2);
}

.appointment-header-info h2 {
    margin: 0 0 10px 0;
    font-size: 28px;
}

.appointment-header-info p {
    margin: 5px 0;
    opacity: 0.9;
}

.info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 20px;
    margin-bottom: 20px;
}

.info-card {
    background: white;
    border-radius: 8px;
    padding: 20px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
    border-left: 4px solid #FFA240;
}

.info-card h4 {
    margin: 0 0 15px 0;
    color: #F57C00;
    font-size: 16px;
    text-transform: uppercase;
    letter-spacing: 1px;
}

.info-row {
    display: flex;
    padding: 10px 0;
    border-bottom: 1px solid #f0f0f0;
}

.info-row:last-child {
    border-bottom: none;
}

.info-label {
    font-weight: 600;
    color: #666;
    min-width: 150px;
}

.info-value {
    color: #333;
    flex: 1;
}

.status-badge {
    display: inline-block;
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.status-badge.approved {
    background: #d4edda;
    color: #155724;
}

.status-badge.pending {
    background: #fff3cd;
    color: #856404;
}

.status-badge.rejected {
    background: #f8d7da;
    color: #721c24;
}

.status-badge.draft {
    background: #e2e3e5;
    color: #383d41;
}

.approval-section {
    background: white;
    border-radius: 8px;
    padding: 25px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    margin-top: 20px;
}

.approval-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    padding-bottom: 15px;
    border-bottom: 2px solid #f0f0f0;
}

.approval-header h3 {
    margin: 0;
    color: #333;
    font-size: 20px;
}

.approval-table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
}

.approval-table thead th {
    background: #f8f9fa;
    color: #333;
    font-weight: 600;
    padding: 15px;
    text-align: left;
    border-bottom: 2px solid #dee2e6;
}

.approval-table tbody td {
    padding: 15px;
    border-bottom: 1px solid #f0f0f0;
}

.approval-table tbody tr:hover {
    background-color: #f8f9fa;
}

.approval-table tbody tr.admin-row {
    background-color: #f8f9fa;
}

.step-badge {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    font-weight: 600;
    color: #333;
}

.step-badge i {
    color: #37474F;
}

.action-badge {
    display: inline-block;
    padding: 5px 10px;
    border-radius: 15px;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
}

.action-badge.accepted {
    background: #d4edda;
    color: #155724;
}

.action-badge.rejected {
    background: #f8d7da;
    color: #721c24;
}

.alert {
    padding: 15px 20px;
    border-radius: 8px;
    margin-top: 20px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.alert i {
    font-size: 20px;
}

.alert-success {
    background: #d4edda;
    color: #155724;
    border-left: 4px solid #28a745;
}

.alert-warning {
    background: #fff3cd;
    color: #856404;
    border-left: 4px solid #ffc107;
}

.alert-danger {
    background: #f8d7da;
    color: #721c24;
    border-left: 4px solid #dc3545;
}

.alert-info {
    background: #d1ecf1;
    color: #0c5460;
    border-left: 4px solid #37474F;
}

.action-buttons {
    display: flex;
    gap: 10px;
    margin-top: 20px;
}

.btn {
    padding: 10px 20px;
    border-radius: 5px;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    font-weight: 600;
    transition: all 0.2s;
    border: none;
    cursor: pointer;
}

.btn-secondary {
    background: #6c757d;
    color: white;
}

.btn-secondary:hover {
    background: #5a6268;
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(108, 117, 125, 0.3);
}

.btn-primary {
    background: linear-gradient(135deg, #37474F 0%, #616161 100%);
    color: white;
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(102, 126, 234, 0.3);
}

.notes-box {
    background: #f8f9fa;
    border-left: 4px solid #37474F;
    padding: 15px;
    border-radius: 5px;
    margin-top: 15px;
}

.notes-box strong {
    color: #37474F;
    display: block;
    margin-bottom: 8px;
}

@media (max-width: 768px) {
    .appointment-header-content {
        flex-direction: column;
        text-align: center;
    }
    
    .info-grid {
        grid-template-columns: 1fr;
    }
    
    .action-buttons {
        flex-direction: column;
    }
    
    .btn {
        width: 100%;
        justify-content: center;
    }
}
</style>

<div class="appointment-detail-container">
    <!-- Appointment Header -->
    <div class="appointment-header-card">
        <div class="appointment-header-content">
            <div class="appointment-icon">
                <i class="fas fa-file-signature"></i>
            </div>
            <div class="appointment-header-info">
                <h2 data-lang="appointment-letter">Appointment Letter</h2>
                <p><i class="fas fa-file-alt"></i> <?php echo htmlspecialchars($appointment['appointment_number']); ?></p>
                <p><i class="fas fa-user"></i> <?php echo htmlspecialchars($appointment['employee_name']); ?></p>
                <p>
                    <?php
                    $status_badges = [
                        'approved' => 'approved',
                        'pending' => 'pending',
                        'rejected' => 'rejected',
                        'draft' => 'draft'
                    ];
                    $status_labels = [
                        'draft' => 'Draft',
                        'pending' => 'Pending',
                        'approved' => 'Approved',
                        'rejected' => 'Rejected'
                    ];
                    $status_lang_keys = [
                        'draft' => 'draft',
                        'pending' => 'pending',
                        'approved' => 'approved',
                        'rejected' => 'rejected'
                    ];
                    $badge_class = $status_badges[$appointment['status']] ?? 'draft';
                    $label = $status_labels[$appointment['status']] ?? strtoupper($appointment['status']);
                    $status_lang_key = $status_lang_keys[$appointment['status']] ?? '';
                    ?>
                    <span class="status-badge <?php echo $badge_class; ?>" <?php echo $status_lang_key ? 'data-lang="' . htmlspecialchars($status_lang_key) . '"' : ''; ?>>
                        <?php echo $label; ?>
                    </span>
                </p>
            </div>
        </div>
    </div>
    
    <!-- Appointment Information Grid -->
    <div class="info-grid">
        <!-- Document Info -->
        <div class="info-card">
            <h4><i class="fas fa-file-alt"></i> <span data-lang="document-information">Document Information</span></h4>
            <div class="info-row">
                <span class="info-label" data-lang="letter-number">Appointment Number:</span>
                <span class="info-value"><strong><?php echo htmlspecialchars($appointment['appointment_number']); ?></strong></span>
            </div>
            <div class="info-row">
                <span class="info-label" data-lang="status">Status:</span>
                <span class="info-value">
                    <span class="status-badge <?php echo $badge_class; ?>" <?php echo $status_lang_key ? 'data-lang="' . htmlspecialchars($status_lang_key) . '"' : ''; ?>>
                        <?php echo $label; ?>
                    </span>
                </span>
            </div>
            <div class="info-row">
                <span class="info-label" data-lang="appointment-date">Appointment Date:</span>
                <span class="info-value"><?php echo date('d/m/Y', strtotime($appointment['appointment_date'])); ?></span>
            </div>
            <div class="info-row">
                <span class="info-label" data-lang="effective-date">Effective Date:</span>
                <span class="info-value"><?php echo date('d/m/Y', strtotime($appointment['effective_date'])); ?></span>
            </div>
            <div class="info-row">
                <span class="info-label" data-lang="expiry-date">Expiry Date:</span>
                <span class="info-value">
                    <?php 
                    if ($appointment['expiry_date']) {
                        echo date('d/m/Y', strtotime($appointment['expiry_date']));
                    } else {
                        echo '<span class="text-muted" data-lang="no-expiry-date">No expiry date</span>';
                    }
                    ?>
                </span>
            </div>
        </div>
        
        <!-- Employee Info -->
        <div class="info-card">
            <h4><i class="fas fa-user"></i> <span data-lang="employee-information">Employee Information</span></h4>
            <div class="info-row">
                <span class="info-label" data-lang="employee-code">Employee Code:</span>
                <span class="info-value"><?php echo htmlspecialchars($appointment['employee_code']); ?></span>
            </div>
            <div class="info-row">
                <span class="info-label" data-lang="full-name">Full Name:</span>
                <span class="info-value"><?php echo htmlspecialchars($appointment['employee_name']); ?></span>
            </div>
            <div class="info-row">
                <span class="info-label" data-lang="position">Position:</span>
                <span class="info-value"><?php echo htmlspecialchars($appointment['position'] ?? '-'); ?></span>
            </div>
            <div class="info-row">
                <span class="info-label" data-lang="company">Company:</span>
                <span class="info-value"><?php echo htmlspecialchars($appointment['contractor_company']); ?></span>
            </div>
        </div>
        
        <!-- Position Info -->
        <div class="info-card">
            <h4><i class="fas fa-briefcase"></i> <span data-lang="competency-information">Competency Information</span></h4>
            <div class="info-row">
                <span class="info-label" data-lang="competency-name">Competency Name:</span>
                <span class="info-value"><strong><?php echo htmlspecialchars($appointment['competency_name'] ?? '-'); ?></strong></span>
            </div>
            <div class="info-row">
                <span class="info-label" data-lang="competency-type">Competency Type:</span>
                <span class="info-value"><?php echo htmlspecialchars($appointment['position_type']); ?></span>
            </div>
        </div>
    </div>
    
    <?php if ($appointment['notes'] && trim($appointment['notes']) !== 'Auto-generated setelah verifikasi data tenaga kerja'): ?>
    <div class="notes-box">
        <strong><i class="fas fa-sticky-note"></i> <span data-lang="notes">Notes:</span></strong>
        <?php echo nl2br(htmlspecialchars($appointment['notes'])); ?>
    </div>
    <?php endif; ?>
    
    <?php if ($appointment['status'] == 'approved' || $appointment['status'] == 'rejected' || $appointment['status'] == 'pending'): ?>
    <!-- Approval History Section -->
    <div class="approval-section">
        <div class="approval-header">
            <h3><i class="fas fa-clipboard-check"></i> <span data-lang="approval-history">Approval History</span></h3>
        </div>
        
        <div class="table-responsive">
            <table class="approval-table">
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
                    <?php if ($appointment['admin_verified_by']): ?>
                    <tr class="admin-row">
                        <td>
                            <span class="step-badge">
                                <i class="fas fa-shield-alt"></i> <span data-lang="admin-verification">Admin Verification</span>
                            </span>
                        </td>
                        <td>
                            <?php echo htmlspecialchars($appointment['admin_name']); ?><br>
                            <small class="text-muted"><?php echo htmlspecialchars($appointment['admin_username']); ?></small>
                        </td>
                        <td>
                            <span class="action-badge accepted">
                                <i class="fas fa-check"></i> <span data-lang="approved">Approved</span>
                            </span>
                        </td>
                        <td>
                            <span class="text-muted">-</span>
                        </td>
                        <td data-lang="data-certificate-verified">Data & Certificate Verified</td>
                    </tr>
                    <?php else: ?>
                    <tr class="admin-row">
                        <td>
                            <span class="step-badge">
                                <i class="fas fa-shield-alt"></i> <span data-lang="admin-verification">Admin Verification</span>
                            </span>
                        </td>
                        <td colspan="4" class="text-muted">
                            <i class="fas fa-clock"></i> <span data-lang="waiting-admin-verification">Waiting for Admin Verification</span>
                        </td>
                    </tr>
                    <?php endif; ?>
                    
                    <!-- KTT Approvals -->
                    <?php if ($ktt_approvals->num_rows > 0): ?>
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
                                        <i class="fas fa-check"></i> <span data-lang="approved">Approved</span>
                                    </span>
                                <?php else: ?>
                                    <span class="action-badge rejected">
                                        <i class="fas fa-times"></i> <span data-lang="rejected">Rejected</span>
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
                                    <i class="fas fa-check-circle"></i> <span data-lang="approval-ktt">KTT Approval</span>
                                </span>
                            </td>
                            <td colspan="4" class="text-muted">
                                <i class="fas fa-clock"></i> <span data-lang="waiting-ktt-approval">Waiting for KTT Approval</span>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <?php if ($appointment['approval_notes']): ?>
        <div class="notes-box">
            <strong><i class="fas fa-comment-alt"></i> <span data-lang="final-notes">Final Notes:</span></strong>
            <?php echo nl2br(htmlspecialchars($appointment['approval_notes'])); ?>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    
    <!-- Status Alerts -->
    <?php if ($appointment['status'] == 'approved'): ?>
    <div class="alert alert-success">
        <i class="fas fa-check-circle"></i>
        <div>
            <strong data-lang="appointment-approved-title">Appointment Approved</strong><br>
            <span data-lang="appointment-approved-desc">This appointment has been approved by both KTTs on</span>
            <?php
            $approval_date = $appointment['final_approval_date'] ?? $appointment['approved_date'];
            echo $approval_date ? date('d/m/Y H:i', strtotime($approval_date)) : 'N/A';
            ?>
        </div>
    </div>
    <?php elseif ($appointment['status'] == 'rejected'): ?>
    <div class="alert alert-danger">
        <i class="fas fa-times-circle"></i>
        <div>
            <strong data-lang="appointment-rejected">Appointment Rejected</strong><br>
            <span data-lang="this-appointment-was-rejected">This appointment was rejected</span>
            <?php
            if ($appointment['approved_date']) {
                echo ' on ' . date('d/m/Y H:i', strtotime($appointment['approved_date']));
            }
            ?>
        </div>
    </div>
    <?php elseif ($appointment['status'] == 'pending'): ?>
    <div class="alert alert-warning">
        <i class="fas fa-clock"></i> 
        <div>
            <strong data-lang="waiting-approval-title">Waiting for Approval</strong><br>
            <span data-lang="waiting-approval-desc">This appointment is waiting for approval from the KTT</span>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Action Buttons -->
    <div class="action-buttons">
        <a href="appointments.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> <span data-lang="back">Back</span>
        </a>
        
        <?php if ($appointment['status'] == 'approved'): ?>
        <a href="../../print_appointment.php?id=<?php echo $appointment['id']; ?>" class="btn btn-primary" target="_blank">
            <i class="fas fa-print"></i> <span data-lang="print">Print</span>
        </a>
        <?php endif; ?>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>




