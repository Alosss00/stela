<?php
$page_title = 'Appointment Letter Detail';
require_once dirname(__DIR__, 3) . '/app/Helpers/auth_helper.php';
// Included via bootstrap/app.php

// Only USER role can access this page
checkPageAccess(['user']);

require_once dirname(__DIR__) . '/layouts/header.php';

$db = new Database();
$company_name = $_SESSION['company_name'] ?? '';
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Get appointment details - ensure it belongs to this company
$appointment = $db->query("
    SELECT a.*, e.full_name as employee_name, e.employee_code, e.position, e.contractor_company,
           e.verified_by as admin_verified_by, e.competency_name,
           p.position_name, p.position_type,
           u1.full_name as created_by_name,
           u2.full_name as approved_by_name,
           u_admin.full_name as admin_name,
           u_admin.username as admin_username,
             e.verified_date as admin_verified_date,
           ktt1.full_name as ktt1_name,
           ktt2.full_name as ktt2_name
    FROM appointments a
    JOIN employees e ON a.employee_id = e.id
    JOIN positions p ON a.position_id = p.id
    LEFT JOIN users u1 ON a.created_by = u1.id
    LEFT JOIN users u2 ON a.approved_by = u2.id
    LEFT JOIN users u_admin ON e.verified_by = u_admin.id
    LEFT JOIN users ktt1 ON a.ktt1_approved_by = ktt1.id
    LEFT JOIN users ktt2 ON a.ktt2_approved_by = ktt2.id
    WHERE a.id = $id AND e.contractor_company = '" . $db->escapeString($company_name) . "'
")->fetch_assoc();
// Ambil tanggal verifikasi admin dari field yang benar
$admin_verified_date = $appointment['verified_date'] ?? $appointment['admin_verified_date'] ?? $appointment['admin_verified_at'] ?? null;

if (!$appointment) {
    ?>
    <div class="appointment-detail-container" style="display: flex; justify-content: center; padding-top: 50px; min-height: 60vh;">
        <div style="max-width: 600px; width: 100%;">
            <div class="alert alert-error" style="padding: 25px; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
                <h3 style="margin-top: 0; margin-bottom: 15px; display: flex; align-items: center; gap: 10px; color: inherit;">
                    <i class="fas fa-exclamation-triangle" style="font-size: 24px;"></i> Peringatan Akses
                </h3>
                <p style="margin-bottom: 25px; line-height: 1.6; font-size: 16px;">
                    Data tidak ditemukan atau Anda <strong>tidak memiliki hak akses</strong> untuk melihat surat pengangkatan dari perusahaan lain.
                </p>
                <a href="appointments.php" class="btn btn-secondary" style="display: inline-flex; align-items: center; gap: 8px; padding: 10px 20px;">
                    <i class="fas fa-arrow-left"></i> Kembali ke Daftar
                </a>
            </div>
        </div>
    </div>
    <?php
    require_once dirname(__DIR__) . '/layouts/footer.php';
    exit();
}

// Get KTT approval details
$ktt_approvals = $db->query("
    SELECT ka.*, u.full_name as ktt_name, u.username, u.id as user_id
    FROM ktt_approvals ka
    LEFT JOIN users u ON ka.ktt_user_id = u.id
    WHERE ka.appointment_id = $id
    ORDER BY ka.approval_date ASC
");

// Helper function to get KTT type (MSM or TTN)
function getKttType($user_id) {
    return ($user_id == 7) ? 'MSM' : 'TTN';
}
?>



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
                <span class="info-label" data-lang="id-badge">ID Badge:</span>
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
    
    <?php if ($appointment['status'] == 'approved' || $appointment['status'] == 'rejected' || $appointment['status'] == 'pending'): ?>
    <!-- Approval History Section -->
    <div class="approval-section">
        <div class="approval-header">
            <h3><i class="fas fa-clipboard-check"></i> <span data-lang="approval-history">Approval History</span></h3>
        </div>
        
        <div class="table-responsive">
            <table class="approval-table" style="width: 100% !important; table-layout: fixed !important; width: 100% !important; table-layout: fixed !important;">
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
                                <?php
                                // Tampilkan tanggal verifikasi admin jika tersedia
                                if (!empty($admin_verified_date)) {
                                    echo date('d/m/Y H:i', strtotime($admin_verified_date));
                                } else {
                                    echo '<span class="text-muted">-</span>';
                                }
                                ?>
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
                                    <i class="fas fa-check-circle"></i> <span data-lang="approval-ktt">KTT Approval</span> (<?php echo $ktt_type; ?>)
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

<?php require_once dirname(__DIR__) . '/layouts/footer.php'; ?>
