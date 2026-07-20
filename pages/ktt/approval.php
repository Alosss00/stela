<?php
$page_title = 'KTT Approval';
require_once '../../includes/auth.php';
require_once '../../includes/db.php';
require_once '../../includes/notifications.php';

// Check if user is KTT only, or superadmin for read access
if ($_SESSION['role'] != 'ktt' && $_SESSION['role'] != 'superadmin') {
    header('Location: ../admin/dashboard.php');
    exit();
}

$is_superadmin = ($_SESSION['role'] == 'superadmin');

$db = new Database();
$message = '';
$error = '';
$current_user_id = $_SESSION['user_id'];

// Determine KTT type: user_id 7 = KTT MSM, user_id 8 = KTT TTN
$ktt_type = ($current_user_id == 7) ? 'msm' : 'ttn';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && !$is_superadmin) {
    if (isset($_POST['action'])) {
        // Minimal safe handler to avoid parse errors and preserve page load.
        // Detailed processing should be implemented in the API or elsewhere.
        $message = 'Perubahan disimpan.';
        $id = intval($_POST['id']);
        $approval_notes = $db->escapeString($_POST['approval_notes']);
        
        // Check if this KTT has already approved/rejected this appointment
        // Use the appointment's KTT status field directly (more reliable than ktt_approvals table)
        $my_status_field = ($ktt_type == 'msm') ? 'ktt_msm_status' : 'ktt_ttn_status';
        $appt_check = $db->query("
            SELECT $my_status_field as my_ktt_status FROM appointments WHERE id = $id
        ")->fetch_assoc();

        if (!$appt_check || $appt_check['my_ktt_status'] != 'pending') {
            $error = 'You have already made a decision for this assign letter!';
        } else {
            // Delete any stale ktt_approvals from previous rounds for this KTT
            $db->query("DELETE FROM ktt_approvals WHERE appointment_id = $id AND ktt_user_id = $current_user_id");

            if ($_POST['action'] == 'approve') {
                // Insert approval record
                $sql = "INSERT INTO ktt_approvals (appointment_id, ktt_user_id, action, approval_notes)
                        VALUES ($id, $current_user_id, 'approve', '$approval_notes')";

                if ($db->query($sql)) {
                    // Get current appointment status
                    $appointment = $db->query("
                        SELECT ktt_msm_status, ktt_ttn_status FROM appointments WHERE id = $id
                    ")->fetch_assoc();

                    // Update KTT status based on which KTT is approving
                    if ($ktt_type == 'msm') {
                        // KTT MSM approving
                        $update_sql = "UPDATE appointments SET
                                      ktt_msm_status = 'approved',
                                      ktt1_approved_by = $current_user_id,
                                      ktt1_approved_date = NOW()
                                      WHERE id = $id";
                        $db->query($update_sql);

                        // Check if KTT TTN has rejected (already sent to admin)
                        if ($appointment['ktt_ttn_status'] == 'rejected') {
                            // TTN already rejected - don't change status, it's already 'rejected_by_ktt'
                            $message = 'You have approved this assign letter. However, KTT TTN has already rejected it, so it has been sent to Admin for review.';
                        } elseif ($appointment['ktt_ttn_status'] == 'approved') {
                            // Both KTT approved - set final approval
                            $final_sql = "UPDATE appointments SET
                                         status = 'approved',
                                         approved_by = $current_user_id,
                                         approved_date = NOW(),
                                         final_approval_date = NOW(),
                                         approval_notes = '$approval_notes'
                                         WHERE id = $id";
                            $db->query($final_sql);
                            $message = 'Assign letter successfully approved!';
                            // Notify admin and user/dept that both KTTs approved
                            try {
                                set_time_limit(60);
                                $notifService = new NotificationService();
                                $notifService->notifyKttBothApprovedToAdmin($id);
                                $notifService->notifyKttApprovedFinalToUserDept($id);
                            } catch (Exception $e) {
                                error_log("Notification error (ktt approved): " . $e->getMessage());
                            }
                        } else {
                            $message = 'You have approved this assign letter.';
                        }
                    } else {
                        // KTT TTN approving
                        $update_sql = "UPDATE appointments SET
                                      ktt_ttn_status = 'approved',
                                      ktt2_approved_by = $current_user_id,
                                      ktt2_approved_date = NOW()
                                      WHERE id = $id";
                        $db->query($update_sql);

                        // Check if KTT MSM has rejected (already sent to admin)
                        if ($appointment['ktt_msm_status'] == 'rejected') {
                            // MSM already rejected - don't change status, it's already 'rejected_by_ktt'
                            $message = 'You have approved this assign letter. However, KTT MSM has already rejected it, so it has been sent to Admin for review.';
                        } elseif ($appointment['ktt_msm_status'] == 'approved') {
                            // Both KTT approved - set final approval
                            $final_sql = "UPDATE appointments SET
                                         status = 'approved',
                                         approved_by = $current_user_id,
                                         approved_date = NOW(),
                                         final_approval_date = NOW(),
                                         approval_notes = '$approval_notes'
                                         WHERE id = $id";
                            $db->query($final_sql);
                            $message = 'Assign letter successfully approved!';
                            // Notify admin and user/dept that both KTTs approved
                            try {
                                set_time_limit(60);
                                $notifService = new NotificationService();
                                $notifService->notifyKttBothApprovedToAdmin($id);
                                $notifService->notifyKttApprovedFinalToUserDept($id);
                            } catch (Exception $e) {
                                error_log("Notification error (ktt approved): " . $e->getMessage());
                            }
                        } else {
                            $message = 'You have approved this assign letter.';
                        }
                    }
                } else {
                    $error = 'Failed to approve assign letter!';
                }

            } elseif ($_POST['action'] == 'reject') {
                // Insert rejection record
                $sql = "INSERT INTO ktt_approvals (appointment_id, ktt_user_id, action, approval_notes)
                        VALUES ($id, $current_user_id, 'reject', '$approval_notes')";

                if ($db->query($sql)) {
                    // Update KTT status based on which KTT is rejecting
                    if ($ktt_type == 'msm') {
                        // KTT MSM rejecting
                        $update_sql = "UPDATE appointments SET
                                      ktt_msm_status = 'rejected',
                                      ktt1_approved_by = $current_user_id,
                                      ktt1_approved_date = NOW()
                                      WHERE id = $id";
                        $db->query($update_sql);
                    } else {
                        // KTT TTN rejecting
                        $update_sql = "UPDATE appointments SET
                                      ktt_ttn_status = 'rejected',
                                      ktt2_approved_by = $current_user_id,
                                      ktt2_approved_date = NOW()
                                      WHERE id = $id";
                        $db->query($update_sql);
                    }

                    // NEW WORKFLOW: Allow BOTH KTTs to review before sending to admin
                    // Check if BOTH KTTs have completed their review
                    $appointment_check = $db->query("
                        SELECT ktt_msm_status, ktt_ttn_status,
                               requires_ktt_msm_review, requires_ktt_ttn_review
                        FROM appointments
                        WHERE id = $id
                    ")->fetch_assoc();

                    $msm_done = ($appointment_check['requires_ktt_msm_review'] == 0 ||
                                 $appointment_check['ktt_msm_status'] != 'pending');
                    $ttn_done = ($appointment_check['requires_ktt_ttn_review'] == 0 ||
                                 $appointment_check['ktt_ttn_status'] != 'pending');

                    // Check if both required KTTs have completed their review
                    $both_done = $msm_done && $ttn_done;

                    if ($both_done) {
                        // Both KTTs have reviewed - check if any rejection exists
                        $has_rejection = ($appointment_check['ktt_msm_status'] == 'rejected' ||
                                         $appointment_check['ktt_ttn_status'] == 'rejected');

                        if ($has_rejection) {
                            // At least one KTT rejected - send to admin for review
                            $last_rejected_ktt = $ktt_type; // Current KTT who just rejected
                            $rejected_user_id = $current_user_id;

                            $update_sql = "UPDATE appointments SET
                                          status = 'rejected_by_ktt',
                                          rejected_by_ktt_user_id = $rejected_user_id,
                                          last_rejected_by_ktt = '$last_rejected_ktt',
                                          approval_notes = '$approval_notes'
                                          WHERE id = $id";
                            $db->query($update_sql);

                            // Send notification to admin about rejection that needs review
                            try {
                                require_once '../../includes/notifications.php';
                                set_time_limit(60);
                                $notificationService = new NotificationService();
                                $notificationService->notifyAppointmentRejectedForReview($id);
                            } catch (Exception $e) {
                                error_log("Notification error: " . $e->getMessage());
                            }

                            $message = "You have rejected this assign letter, It has been sent to Admin for review.";
                        }
                    } else {
                        // Not all KTTs have reviewed yet - keep status pending for other KTT
                        $ktt_name = ($ktt_type == 'msm') ? 'KTT MSM' : 'KTT TTN';
                        $message = "You ($ktt_name) have rejected this assign letter.";
                    }
                } else {
                    $error = 'Failed to reject assign letter!';
                }
}
        }
    }
}

// Get pending appointments that THIS KTT needs to review
// NEW WORKFLOW: Filter based on requires_ktt_{type}_review flags and ktt_{type}_status
$pending = $db->query("
    SELECT a.*, e.full_name as employee_name, e.employee_code, e.position, e.competency_name,
           e.contractor_company, COALESCE(a.resubmit_count, 0) as resubmit_count,
           p.position_name, p.position_type,
           u.full_name as created_by_name,
           (SELECT COUNT(*) FROM employee_certifications WHERE employee_id = e.id AND verification_status = 'verified') as verified_certs,
           (SELECT COUNT(*) FROM employee_certifications WHERE employee_id = e.id) as total_certs,
           (SELECT COUNT(*) FROM ktt_approvals ka WHERE ka.appointment_id = a.id AND ka.action = 'approve') as approval_count,
           (SELECT COUNT(*) FROM ktt_approvals ka WHERE ka.appointment_id = a.id AND ka.ktt_user_id = $current_user_id) as my_decision,
           (SELECT COUNT(*) FROM ktt_approvals ka WHERE ka.appointment_id = a.id AND ka.action = 'reject') as rejection_count,
           (SELECT COUNT(*) FROM ktt_approvals ka WHERE ka.appointment_id = a.id AND ka.ktt_user_id = $current_user_id AND ka.action = 'reject') as my_previous_rejection,
           ktt1.full_name as ktt1_name, ktt2.full_name as ktt2_name,
           a.resubmit_reason, a.admin_approval_notes, a.last_rejected_by_ktt,
           (SELECT ka_prev.approval_notes FROM ktt_approvals ka_prev
            WHERE ka_prev.appointment_id = a.id AND ka_prev.action = 'reject'
            ORDER BY ka_prev.approval_date DESC LIMIT 1) as previous_ktt_rejection_notes,
           (SELECT u_prev.full_name FROM ktt_approvals ka_prev
            JOIN users u_prev ON ka_prev.ktt_user_id = u_prev.id
            WHERE ka_prev.appointment_id = a.id AND ka_prev.action = 'reject'
            ORDER BY ka_prev.approval_date DESC LIMIT 1) as previous_ktt_rejector_name,
           CASE
               WHEN '$ktt_type' = 'msm' THEN a.ktt_msm_status
               WHEN '$ktt_type' = 'ttn' THEN a.ktt_ttn_status
           END as my_ktt_status
    FROM appointments a
    JOIN employees e ON a.employee_id = e.id
    LEFT JOIN positions p ON a.position_id = p.id
    LEFT JOIN users u ON a.created_by = u.id
    LEFT JOIN users ktt1 ON a.ktt1_approved_by = ktt1.id
    LEFT JOIN users ktt2 ON a.ktt2_approved_by = ktt2.id
    WHERE a.status = 'pending'
    AND (
        ('$ktt_type' = 'msm' AND a.requires_ktt_msm_review = 1 AND a.ktt_msm_status = 'pending')
        OR
        ('$ktt_type' = 'ttn' AND a.requires_ktt_ttn_review = 1 AND a.ktt_ttn_status = 'pending')
    )
    ORDER BY a.created_at ASC
");

// Get completed decisions by current KTT user (untuk ditampilkan di section terpisah)
$completed_decisions = $db->query("
    SELECT a.*, e.full_name as employee_name, e.employee_code, e.position, e.contractor_company,
           p.position_name, ka.action, ka.approval_notes, ka.approval_date,
           CASE
               WHEN a.status = 'approved' THEN 'success'
               WHEN a.status = 'rejected' THEN 'danger'
               ELSE 'secondary'
           END as status_class
    FROM appointments a
    JOIN employees e ON a.employee_id = e.id
    JOIN positions p ON a.position_id = p.id
    JOIN ktt_approvals ka ON a.id = ka.appointment_id
    WHERE ka.ktt_user_id = $current_user_id
    ORDER BY ka.approval_date DESC
    LIMIT 20
");

// Get approved/rejected appointments (history)
$processed = $db->query("
    SELECT a.*, e.full_name as employee_name, e.employee_code, e.position, e.contractor_company,
           p.position_name, u1.full_name as created_by_name,
           u2.full_name as approved_by_name,
           ktt1.full_name as ktt1_name,
           ktt2.full_name as ktt2_name,
           CASE
               WHEN a.status = 'approved' THEN 'success'
               WHEN a.status = 'rejected' THEN 'danger'
               ELSE 'secondary'
           END as status_class
    FROM appointments a
    JOIN employees e ON a.employee_id = e.id
    JOIN positions p ON a.position_id = p.id
    LEFT JOIN users u1 ON a.created_by = u1.id
    LEFT JOIN users u2 ON a.approved_by = u2.id
    LEFT JOIN users ktt1 ON a.ktt1_approved_by = ktt1.id
    LEFT JOIN users ktt2 ON a.ktt2_approved_by = ktt2.id
    WHERE a.status IN ('approved', 'rejected')
    ORDER BY a.approved_date DESC
    LIMIT 20
");


// Get unique companies for filter - Keputusan Anda
$companies_decisions = $db->query("
    SELECT DISTINCT e.contractor_company
    FROM appointments a
    JOIN employees e ON a.employee_id = e.id
    JOIN ktt_approvals ka ON a.id = ka.appointment_id
    WHERE ka.ktt_user_id = $current_user_id
    ORDER BY e.contractor_company
");

// Get unique companies for filter - Riwayat Keseluruhan
$companies_history = $db->query("
    SELECT DISTINCT e.contractor_company
    FROM appointments a
    JOIN employees e ON a.employee_id = e.id
    WHERE a.status IN ('approved', 'rejected')
    ORDER BY e.contractor_company
");

require_once '../../includes/header.php';
?>

<div class="approval-container">
    <!-- Page Header -->
    <div class="page-header-approval">
        <div class="header-left">
            <h2><i class="fas fa-gavel"></i> <span data-lang="assign-letter-approval">Assign Letter Approval</span></h2>
         
        </div>
        <div class="header-stats">
            <div class="stat-mini">
                <span class="stat-label" data-lang="pending">Pending</span>
                <span class="stat-value"><?php echo $pending->num_rows; ?></span>
            </div>
        </div>
    </div>
    
    <?php if ($message): ?>
    <div class="alert alert-success alert-approval">
        <i class="fas fa-check-circle"></i>
        <div>
            <strong data-lang="success">Success!</strong>
            <?php if ($message === 'Assign letter successfully approved!'): ?>
            <p data-lang="ktt-assign-letter-successfully-approved">Assign letter successfully approved!</p>
            <?php elseif ($message === 'You have approved this assign letter. It has been sent to Admin for review.'): ?>
            <p data-lang="ktt-approved-sent-admin-review">You have approved this assign letter. It has been sent to Admin for review.</p>
            <?php elseif ($message === 'You have approved this assign letter.'): ?>
            <p data-lang="ktt-approved-assign-letter">You have approved this assign letter.</p>
            <?php elseif ($message === 'You have approved this assign letter, It has been sent to Admin for review.'): ?>
            <p data-lang="ktt-approved-but-other-rejected-sent-admin-review">You have approved this assign letter, It has been sent to Admin for review.</p>
            <?php elseif ($message === 'You have rejected this assign letter. It has been sent to Admin for review.'): ?>
            <p data-lang="ktt-rejected-sent-admin-review">You have rejected this assign letter. It has been sent to Admin for review.</p>
            <?php elseif (preg_match('/^You \(([^)]+)\) have rejected this assign letter\.$/', $message, $m)): ?>
            <p><span data-lang="ktt-you">You</span> (<?php echo htmlspecialchars($m[1]); ?>) <span data-lang="ktt-have-rejected-this-assign-letter">have rejected this assign letter.</span></p>
            <?php else: ?>
            <p><?php echo htmlspecialchars($message); ?></p>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
    
    <?php if ($error): ?>
    <div class="alert alert-error alert-approval">
        <i class="fas fa-exclamation-circle"></i>
        <div>
            <strong data-lang="error">Succes!</strong>
            <?php if ($error === 'You have already made a decision for this assign letter!'): ?>
            <p data-lang="ktt-already-made-decision">You have already made a decision for this assign letter!</p>
            <?php elseif ($error === 'Failed to approve assign letter!'): ?>
            <p data-lang="ktt-failed-approve-assign-letter">Failed to approve assign letter!</p>
            <?php elseif ($error === 'Failed to reject assign letter!'): ?>
            <p data-lang="ktt-failed-reject-assign-letter">Failed to reject assign letter!</p>
            <?php else: ?>
            <p><?php echo htmlspecialchars($error); ?></p>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
    
    <?php if ($pending->num_rows > 0): ?>
    <!-- Pending Approvals Section -->
    <div class="approval-section">
        <div class="section-header">
            <h3><i class="fas fa-hourglass-half"></i> <span data-lang="pending-your-approval">Pending Your Approval</span></h3>
        </div>
        
        <div class="approvals-grid">
            <?php while ($row = $pending->fetch_assoc()):
                // Check if this is a resubmitted letter
                // Resubmit only when employee resubmitted data after being returned by admin
                // NOT when KTT rejection exists in current review cycle
                $is_resubmitted = ($row['resubmit_count'] > 0);
                // Check if current KTT has previously rejected this letter
                $i_rejected_before = ($row['my_previous_rejection'] > 0);
                $card_class = $is_resubmitted ? 'approval-card resubmitted-card' : 'approval-card';
            ?>
            <div class="<?php echo $card_class; ?>">
                <!-- Card Header -->
                <div class="card-header-approval">
                    <div class="header-title">
                        <h4 class="appointment-number">
                            <i class="fas fa-file-contract"></i> <?php echo htmlspecialchars($row['appointment_number']); ?>
                            <?php if ($is_resubmitted): ?>
                                <span class="badge-resubmitted">
                                    <i class="fas fa-redo"></i> <span data-lang="resubmitted">Resubmitted</span> (<?php echo intval($row['resubmit_count']); ?>x)
                                </span>
                            <?php endif; ?>
                            <?php if ($i_rejected_before): ?>
                                <span class="badge-you-rejected">
                                    <i class="fas fa-user-times"></i> <span data-lang="you-rejected-this">You Rejected This</span>
                                </span>
                            <?php endif; ?>
                        </h4>
                        <?php if ($is_resubmitted && !empty($row['previous_ktt_rejection_notes']) && $row['my_previous_rejection'] > 0): ?>
                            <i class="fas fa-exclamation-circle"></i>
                            <span class="rejection-text">
                                <strong data-lang="previous-rejection">Previous Rejection</strong>:
                                <?php 
                                    $notes = $row['previous_ktt_rejection_notes'];
                                    echo htmlspecialchars(strlen($notes) > 80 ? substr($notes, 0, 80) . '...' : $notes); 
                                ?>
                            </span>
                        </div>
                        <?php endif; ?>
                        <div class="submitted-info">
                            <div class="info-line">
                                <i class="fas fa-user-check"></i> <span data-lang="reviewed-by">Reviewed By</span>: <?php echo htmlspecialchars($row['created_by_name']); ?>
                            </div>
                            <div class="info-line">
                                <i class="fas fa-calendar"></i> <?php echo date('d M Y', strtotime($row['created_at'])); ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Card Body -->
                <div class="card-body-approval">
                    <!-- Employee Info -->
                    <div class="info-section">
                        <div class="info-block">
                            <div class="info-item">
                                <label><i class="fas fa-id-card"></i> <span data-lang="employee">Employee</span></label>
                                <p class="info-value"><?php echo htmlspecialchars($row['employee_name']); ?></p>
                                <p class="info-sub"><?php echo htmlspecialchars($row['employee_code']); ?></p>
                            </div>
                            <div class="info-item">
                                <label><i class="fas fa-building"></i> <span data-lang="company">Company</span></label>
                                <p class="info-value"><?php echo htmlspecialchars($row['contractor_company']); ?></p>
                            </div>
                        </div>
                        
                        <div class="info-block">
                            <div class="info-item">
                                <label><i class="fas fa-briefcase"></i> <span data-lang="position">Position</span></label>
                                <p class="info-value"><?php echo htmlspecialchars($row['position']); ?></p>
                            </div>
                            <div class="info-item">
                                <label><i class="fas fa-calendar-check"></i> <span data-lang="valid">Valid</span></label>
                                <p class="info-value"><?php echo date('d M Y', strtotime($row['effective_date'])); ?></p>
                                <?php if ($row['expiry_date']): ?>
                                    <p class="info-sub"><span data-lang="until-short">s/d</span> <?php echo date('d M Y', strtotime($row['expiry_date'])); ?></p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Sertifikasi Summary -->
                    <div class="cert-summary">
                        <span class="cert-badge badge-<?php echo ($row['verified_certs'] == $row['total_certs']) ? 'success' : 'warning'; ?>">
                            <i class="fas fa-certificate"></i>
                            <?php echo $row['verified_certs']; ?>/<?php echo $row['total_certs']; ?> <span data-lang="certifications">Certifications</span>
                        </span>
                    </div>
                    
                    <?php if ($is_resubmitted && !empty($row['previous_ktt_rejection_notes']) && $row['my_previous_rejection'] > 0): ?>
                    <!-- Previous Rejection Reason -->
                    <div class="rejection-notice">
                        <div class="rejection-notice-header">
                            <i class="fas fa-exclamation-triangle"></i>
                            <strong data-lang="previous-rejection">Previous Rejection</strong>
                            <?php if (!empty($row['previous_ktt_rejector_name'])): ?>
                            <span class="rejection-by"><span data-lang="by">by</span> <?php echo htmlspecialchars($row['previous_ktt_rejector_name']); ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="rejection-notice-body">
                            <?php echo nl2br(htmlspecialchars($row['previous_ktt_rejection_notes'])); ?>
                        </div>
                        <?php if (!empty($row['admin_approval_notes'])): ?>
                        <div class="admin-notes">
                            <small><i class="fas fa-user-shield"></i> <strong data-lang="ktt-admin-notes">Admin Notes:</strong> <?php echo nl2br(htmlspecialchars($row['admin_approval_notes'])); ?></small>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Action Buttons -->
                    <div class="card-actions">
                        <button onclick="showReviewModal(<?php echo $row['id']; ?>)" 
                                class="btn-review">
                            <i class="fas fa-eye"></i> <span data-lang="review">Review</span>
                        </button>
                        
                        <button onclick="showApprovalForm(<?php echo $row['id']; ?>, 'approve')" 
                                class="btn-approve">
                            <i class="fas fa-check"></i> <span data-lang="accept">Accept</span>
                        </button>
                        <button onclick="showApprovalForm(<?php echo $row['id']; ?>, 'reject')" 
                                class="btn-reject">
                            <i class="fas fa-times"></i> <span data-lang="reject">Reject</span>
                        </button>
                    </div>
                </div>
            </div>
            <?php endwhile; ?>
        </div>
    </div>
    <?php endif; ?>
</div>
<!-- Review Modal -->
<div id="reviewModal" class="modal-approval">
    <div class="modal-content-approval modal-large-review" style="background: #fff; border-radius: 12px; box-shadow: 0 8px 32px rgba(0,0,0,0.18);">
        <div class="modal-header-approval" style="background: #F57C00; color: white; border-top-left-radius: 12px; border-top-right-radius: 12px; padding: 18px 24px;">
            <h3><i class="fas fa-file-contract"></i> <span data-lang="review-assign-letter">Review Assign Letter</span></h3>
            <span class="close-modal" onclick="closeModal('reviewModal')">&times;</span>
        </div>
        <div class="modal-body-approval modal-body-review">
                <!-- Tambahkan style agar konten modal lebih konsisten -->
                <style>
                    .modal-body-approval.modal-body-review { background: #f9f9f9; padding: 24px; }
                    .modal-footer-approval.modal-footer-review { background: #37474F; color: white; border-bottom-left-radius: 12px; border-bottom-right-radius: 12px; padding: 16px 24px; }
                    .btn-modal-cancel { background: #F57C00; color: white; border: none; border-radius: 6px; padding: 8px 18px; font-weight: 600; margin-right: 8px; }
                    .btn-modal-cancel:hover { background: #E65100; }
                    .btn-modal-print { background: #37474F; color: white; border: none; border-radius: 6px; padding: 8px 18px; font-weight: 600; text-decoration: none; }
                    .btn-modal-print:hover { background: #263238; }
                </style>
            <div id="reviewContent" class="review-content verification-container">
                <!-- Content will be loaded here -->
            </div>
        </div>
        <div class="modal-footer-approval modal-footer-review">
                <button type="button" class="btn-modal-cancel" onclick="closeModal('reviewModal')" data-lang="close">Close</button>
                <a id="printLink" href="#" target="_blank" class="btn-modal-print">
                    <i class="fas fa-print"></i> <span data-lang="print">Print</span>
                </a>
        </div>
    </div>
</div>

<!-- Approval Modal -->
<div id="approvalModal" class="modal-approval">
    <div class="modal-content-approval">
        <div class="modal-header-approval" style="background: #F57C00; color: white; border-top-left-radius: 10px; border-top-right-radius: 10px; padding: 20px;">
            <h3 id="modalTitle"><i class="fas fa-gavel"></i> <span data-lang="ktt-approval-assign-letter">Assign Letter Approval</span></h3>
            <span class="close-modal" onclick="closeModal('approvalModal')">&times;</span>
        </div>
        <form method="POST" action="" id="approvalForm">
            <input type="hidden" name="action" id="approval_action">
            <input type="hidden" name="id" id="approval_id">
            <div class="modal-body-approval">
                <div class="form-group-approval">
                    <label><span data-lang="notes">Notes</span> 
                        <span class="text-danger" id="catatan-required" style="display: none;">*</span>
                    </label>
                    <textarea name="approval_notes" id="approval_notes" class="textarea-approval" rows="5" 
                              placeholder="Enter notes or reason..." data-lang-placeholder="enter-notes-or-reason"></textarea>
                    <small class="form-hint" id="catatan-hint" data-lang="notes-required-if-rejecting">Notes are required if rejecting</small>
                </div>
            </div>
            <div class="modal-footer-approval">
                <button type="button" class="btn-modal-cancel" style="background: #FFA240; color: #333;" onclick="closeModal('approvalModal')" data-lang="close">Close</button>
                <button type="submit" class="btn-modal-submit" id="submitBtn" style="background: #2E7D32; color: white;"><span data-lang="approve">Approve</span></button>
            </div>
        </form>
    </div>
</div>

<style>
.approval-container {
    padding: 20px 0;
}

/* Page Header */
.page-header-approval {
    background: #F57C00 0%;
    color: white;
    padding: 35px 30px;
    border-radius: 10px;
    margin-bottom: 30px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    box-shadow: 0 4px 15px rgba(245, 190, 11, 0.81);
}

.page-header-approval h2 {
    margin: 0 0 8px 0;
    font-size: 26px;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 10px;
}

.page-header-approval p {
    margin: 0;
    opacity: 0.9;
    font-size: 14px;
}

.header-stats {
    display: flex;
    gap: 20px;
}

.stat-mini {
    background: rgba(245, 190, 11, 0.81);
    padding: 15px 25px;
    border-radius: 8px;
    text-align: center;
}

.stat-label {
    display: block;
    font-size: 12px;
    opacity: 0.9;
    margin-bottom: 5px;
}

.stat-value {
    display: block;
    font-size: 28px;
    font-weight: 700;
}

/* Alert */
.alert-approval {
    display: flex;
    gap: 15px;
    align-items: flex-start;
    padding: 20px;
    margin-bottom: 30px;
    border-radius: 8px;
    border-left: 4px solid;
}

.alert-success.alert-approval {
    background: #d1fae5;
    border-left-color: #10b981;
}

.alert-success.alert-approval i {
    color: #10b981;
    font-size: 20px;
}

.alert-error.alert-approval {
    background: #fee2e2;
    border-left-color: #ef4444;
}

.alert-error.alert-approval i {
    color: #ef4444;
    font-size: 20px;
}

/* Section */
.approval-section {
    margin-bottom: 35px;
}

.section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    padding-bottom: 15px;
    border-bottom: 2px solid #FFA240;
}

.section-header h3 {
    margin: 0;
    font-size: 18px;
    color: #F57C00;
    display: flex;
    align-items: center;
    gap: 10px;
}

.section-header h3 a {
    color: #F57C00 !important;
    font-weight: 700;
    text-decoration: underline;
}

.section-header i {
    color: #F57C00;
}

/* Approvals Grid */
.approvals-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(380px, 1fr));
    gap: 20px;
}

.approval-card {
    background: white;
    border-radius: 10px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
    overflow: hidden;
    transition: all 0.3s ease;
    border-top: 4px solid #FFA240;
}

.approval-card:hover {
    box-shadow: 0 5px 20px rgba(245, 124, 0, 0.18);
    transform: translateY(-3px);
}

.card-header-approval {
    background: #f8f9fa;
    padding: 20px;
    border-bottom: 1px solid #e9ecef;
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 15px;
}

.header-title {
    flex: 1;
}

.appointment-number {
    margin: 0 0 8px 0;
    font-size: 16px;
    font-weight: 600;
    color: #333;
    display: flex;
    align-items: center;
    gap: 8px;
}

.appointment-number i {
    color: #F57C00;
}

.submitted-info {
    margin: 0;
    font-size: 12px;
    color: #666;
    display: block;
}

.info-line {
    display: flex;
    align-items: center;
    gap: 6px;
    margin-bottom: 4px;
}

.info-line:last-child {
    margin-bottom: 0;
}

.separator {
    color: #ddd;
}

.approval-status {
    display: flex;
    flex-direction: column;
    gap: 6px;
    align-items: flex-end;
}

.status-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 12px;
    border-radius: 6px;
    font-size: 11px;
    font-weight: 600;
    white-space: nowrap;
}

.status-pending {
    background: #fef3c7;
    color: #f59e0b;
}

.status-partial {
    background: #dbeafe;
    color: #3b82f6;
}

.status-decided {
    background: #d1fae5;
    color: #10b981;
}

.card-body-approval {
    padding: 20px;
}

/* Info Section */
.info-section {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 15px;
    margin-bottom: 15px;
}

.info-block {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.info-item {
    padding-bottom: 10px;
    border-bottom: 1px solid #f0f0f0;
}

.info-item label {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 11px;
    font-weight: 600;
    color: #666;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 6px;
}

.info-item label i {
    color: #F57C00;
}

.info-value {
    margin: 0;
    font-size: 13px;
    font-weight: 600;
    color: #333;
}

.info-sub {
    margin: 2px 0 0 0;
    font-size: 11px;
    color: #999;
}

/* Cert Summary */
.cert-summary {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    padding: 15px;
    background: #f8f9fa;
    border-radius: 8px;
    margin-bottom: 15px;
}

.cert-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 12px;
    border-radius: 6px;
    font-size: 12px;
    font-weight: 600;
}

.badge-success {
    background: #d1fae5;
    color: #10b981;
}

.badge-warning {
    background: #fef3c7;
    color: #f59e0b;
}

.badge-info {
    background: #dbeafe;
    color: #3b82f6;
}

/* Resubmitted card styling */
.resubmitted-card {
    border-top: 4px solid #f97316 !important; /* Orange border */
}

.resubmitted-card:hover {
    border-top: 4px solid #ea580c !important; /* Darker orange on hover */
    box-shadow: 0 8px 24px rgba(249, 115, 22, 0.15); /* Orange-tinted shadow */
}

/* Resubmitted badge */
.badge-resubmitted {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 4px 10px;
    background: #f3f0ff; /* Light purple background */
    color: #c2410c; /* Dark orange text */
    border: 1px solid #fed7aa;
    border-radius: 12px;
    font-size: 0.75rem;
    font-weight: 600;
    margin-left: 8px;
}

.badge-resubmitted i {
    font-size: 0.7rem;
}

/* You Rejected badge - for KTT who previously rejected this */
.badge-you-rejected {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 4px 10px;
    background: #fee2e2; /* Light red background */
    color: #991b1b; /* Dark red text */
    border: 1px solid #fecaca;
    border-radius: 12px;
    font-size: 0.75rem;
    font-weight: 600;
    margin-left: 8px;
    animation: pulseAttention 2s ease-in-out infinite;
}

.badge-you-rejected i {
    font-size: 0.7rem;
}

@keyframes pulseAttention {
    0%, 100% {
        box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.4);
    }
    50% {
        box-shadow: 0 0 0 4px rgba(239, 68, 68, 0);
    }
}

/* Rejection Summary in Header */
.rejection-summary-header {
    display: flex;
    align-items: flex-start;
    gap: 8px;
    margin-top: 12px;
    padding: 10px 12px;
    background: linear-gradient(135deg, #f3f0ff 0%, #ede9fe 100%);
    border-left: 3px solid #f97316;
    border-radius: 6px;
    font-size: 12px;
    line-height: 1.5;
}

.rejection-summary-header i {
    color: #ea580c;
    font-size: 14px;
    margin-top: 2px;
    flex-shrink: 0;
}

.rejection-text {
    color: #9a3412;
    flex: 1;
}

.rejection-text strong {
    color: #c2410c;
    font-weight: 700;
}

/* Rejection Notice */
.rejection-notice {
    background: linear-gradient(135deg, #f3f0ff 0%, #ede9fe 100%);
    border-left: 4px solid #f97316;
    border-radius: 8px;
    padding: 15px;
    margin: 15px 0;
    box-shadow: 0 2px 8px rgba(249, 115, 22, 0.1);
}

.rejection-notice-header {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 10px;
    color: #c2410c;
    font-size: 12px;
    font-weight: 700;
}

.rejection-notice-header i {
    font-size: 14px;
}

.rejection-notice-header strong {
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.rejection-by {
    color: #92400e;
    font-weight: 600;
    font-style: italic;
    margin-left: 4px;
}

.rejection-notice-body {
    background: white;
    padding: 12px;
    border-radius: 6px;
    font-size: 13px;
    line-height: 1.6;
    color: #78350f;
    border: 1px solid #fed7aa;
    margin-bottom: 8px;
}

.admin-notes {
    background: rgba(255, 255, 255, 0.8);
    padding: 10px;
    border-radius: 6px;
    border: 1px dashed #fdba74;
}

.admin-notes small {
    display: block;
    font-size: 11px;
    color: #92400e;
    line-height: 1.5;
}

.admin-notes i {
    color: #f97316;
    margin-right: 4px;
}

/* Card Actions with Review Button */
.card-actions {
    display: flex;
    gap: 8px;
    padding-top: 15px;
    border-top: 1px solid #f0f0f0;
}

.btn-review {
    padding: 10px 12px;
    border: none;
    border-radius: 6px;
    font-size: 12px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    text-decoration: none;
    background: #F57C00; /* warna utama website, sama dengan .btn-primary */
    color: white;
    box-shadow: 0 4px 15px rgba(245, 124, 0, 0.3);
    flex: 0.8;
}

 .btn-review:hover {
    background: #E65100; /* warna hover utama website, sama dengan .btn-primary:hover */
    transform: translateY(-2px);
}

.btn-approve,
.btn-reject,
.btn-decided {
    flex: 1;
}

/* Table */
.table-responsive-approval {
    overflow-x: auto;
    border-radius: 8px;
    background: white;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
}

.table-approval {
    width: 100%;
    border-collapse: collapse;
    margin: 0;
}

.table-approval thead th {
    background: #f8f9fa;
    color: #333;
    font-weight: 600;
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    padding: 15px;
    border: none;
    text-align: left;
}

.history-row {
    border-bottom: 1px solid #f0f0f0;
    transition: background-color 0.2s ease;
}

.history-row:hover {
    background-color: #f8f9ff;
}

.table-approval td {
    padding: 15px;
    vertical-align: middle;
    font-size: 13px;
}

.col-number { width: 12%; }
.col-name { width: 14%; }
.col-company { width: 14%; }
.col-position { width: 12%; }
.col-approved { width: 20%; }
.col-decision { width: 12%; }
.col-date { width: 10%; }
.col-status { width: 10%; }
.col-action { width: 6%; }

.emp-name {
    font-weight: 600;
    color: #333;
    display: block;
}

.emp-code-small {
    font-size: 11px;
    color: #999;
    display: block;
    margin-top: 3px;
}

.company-tag {
    background: #fef3c7;
    color: #b45309;
    padding: 4px 10px;
    border-radius: 6px;
    font-size: 11px;
    display: inline-block;
    font-weight: 600;
}

.position-tag {
    background: #f3f4f6;
    color: #666;
    padding: 4px 10px;
    border-radius: 6px;
    font-size: 12px;
    display: inline-block;
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 60px 20px;
    background: white;
    border-radius: 10px;
    color: #999;
}

.empty-state i {
    font-size: 48px;
    color: #10b981;
    margin-bottom: 15px;
    display: block;
}

.empty-state p {
    margin: 0 0 8px 0;
    font-size: 16px;
}

.empty-state small {
    color: #bbb;
}

/* Modal */
.modal-approval {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.5);
}

.modal-content-approval {
    background-color: white;
    margin: 10% auto;
    padding: 0;
    border-radius: 10px;
    width: 90%;
    max-width: 500px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
    animation: slideIn 0.3s ease;
}

@keyframes slideIn {
    from { transform: translateY(-30px); opacity: 0; }
    to { transform: translateY(0); opacity: 1; }
}

.modal-header-approval {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 20px;
    border-radius: 10px 10px 0 0;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-shrink: 0;
}

.modal-header-approval h3 {
    margin: 0;
    font-size: 18px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.close-modal {
    color: white;
    font-size: 28px;
    cursor: pointer;
    opacity: 0.8;
    transition: opacity 0.3s ease;
}

.close-modal:hover {
    opacity: 1;
}

.modal-body-approval {
    padding: 25px;
}

.form-group-approval {
    margin-bottom: 0;
}

.form-group-approval label {
    display: block;
    margin-bottom: 8px;
    font-weight: 600;
    color: #333;
    font-size: 13px;
}

.textarea-approval {
    width: 100%;
    padding: 12px;
    border: 2px solid #e9ecef;
    border-radius: 6px;
    font-size: 13px;
    font-family: inherit;
    transition: border-color 0.3s ease;
    resize: vertical;
}

.textarea-approval:focus {
    outline: none;
    border-color: #667eea;
}

.form-hint {
    display: block;
    margin-top: 6px;
    color: #999;
    font-size: 12px;
}

.modal-footer-approval {
    display: flex;
    gap: 10px;
    justify-content: flex-end;
    padding: 15px 25px;
    border-top: 1px solid #f0f0f0;
}

.modal-footer-review {
    flex-shrink: 0;
    background: white;
    border-bottom-left-radius: 10px;
    border-bottom-right-radius: 10px;
}

.btn-modal-cancel,
.btn-modal-submit,
.btn-modal-print,
.btn-modal-edit {
    padding: 10px 20px;
    border: none;
    border-radius: 6px;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    text-decoration: none;
    display: inline-block;
}

.btn-modal-cancel {
    background: #e5e7eb;
    color: #666;
}

.btn-modal-cancel:hover {
    background: #d1d5db;
}

.btn-modal-submit,
.btn-modal-print {
    background: #667eea;
    color: white;
}

.btn-modal-submit:hover,
.btn-modal-print:hover {
    background: #764ba2;
    transform: translateY(-1px);
}

.btn-modal-edit {
    background: #f59e0b;
    color: white;
}

.btn-modal-edit:hover {
    background: #d97706;
    transform: translateY(-1px);
}

/* Review Modal Large - IMPROVED LAYOUT */
.modal-large-review {
    max-width: 1200px;
    margin: 2% auto;
    max-height: 95vh;
    display: flex;
    flex-direction: column;
}

.modal-body-review {
    max-height: calc(95vh - 180px);
    overflow-y: auto;
    padding: 0;
    flex: 1;
}

/* Verification Container untuk Review - IMPROVED */
.verification-container {
    padding: 50px 60px;
    background: #ffffff;
}

/* Review Section - IMPROVED dengan layout yang lebih rapi */

.review-section {
    background: linear-gradient(135deg, #FFF7ED 0%, #FFE0B2 100%); /* ungu muda ke ungu krem */
    padding: 30px;
    border-radius: 12px;
    margin-bottom: 30px;
    border: 1px solid #FFA240;
    box-shadow: 0 2px 8px rgba(245, 124, 0, 0.08);
}

.review-section:last-child {
    margin-bottom: 0;
}


.review-section h4 {
    color: #F57C00;
    margin: 0 0 25px 0;
    padding-bottom: 15px;
    border-bottom: 3px solid #FFA240;
    font-size: 17px;
    font-weight: 800;
    display: flex;
    align-items: center;
    gap: 12px;
    letter-spacing: 0.3px;
}

.review-section h4 i {
    font-size: 22px;
}

/* Info Grid - Simetris dan Rapi */
.review-info-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 25px;
    margin-bottom: 0;
}



.review-info-item {
    padding: 18px;
    background: #FFFDE7;
    border-radius: 10px;
    border-left: 5px solid #F57C00;
    box-shadow: 0 2px 8px rgba(245, 124, 0, 0.06);
    transition: all 0.3s ease;
}

.review-info-item:hover {
    box-shadow: 0 4px 12px rgba(245, 124, 0, 0.15);
    transform: translateY(-1px);
}



.review-info-label {
    font-size: 10px;
    font-weight: 800;
    color: #F57C00;
    text-transform: uppercase;
    letter-spacing: 1.2px;
    margin-bottom: 10px;
    display: flex;
    align-items: center;
    gap: 6px;
}

.review-info-label i {
    font-size: 13px;
}


.review-info-value {
    font-size: 16px;
    font-weight: 700;
    color: #37474F;
    word-break: break-word;
    line-height: 1.6;
}

.review-info-sub {
    font-size: 12px;
    color: #6b7280;
    margin-top: 6px;
    font-weight: 500;
}

/* CV Button - Standalone Item */


.review-cv-section {
    background: white;
    padding: 18px;
    border-radius: 10px;
    border-left: 5px solid #F57C00;
    box-shadow: 0 2px 8px rgba(245, 124, 0, 0.06);
    margin-top: 25px;
}

.review-cv-section .review-info-label {
    margin-bottom: 12px;
}


.review-cv-button {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 12px 20px;
    background: linear-gradient(135deg, #F57C00, #FFA240);
    color: white;
    border-radius: 8px;
    text-decoration: none;
    font-size: 13px;
    font-weight: 700;
    transition: all 0.3s ease;
    box-shadow: 0 4px 12px rgba(245, 124, 0, 0.18);
}

.review-cv-button:hover {
    transform: translateY(-3px);
    box-shadow: 0 6px 16px rgba(245, 124, 0, 0.28);
    background: linear-gradient(135deg, #E65100, #FFA240);
}

/* Review Documents Section - Side by Side */
.review-documents-section {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 20px;
    margin-top: 25px;
}


.review-doc-item {
    background: white;
    padding: 18px;
    border-radius: 10px;
    border-left: 5px solid #F57C00;
    box-shadow: 0 2px 8px rgba(245, 124, 0, 0.06);
}

.review-doc-item .review-info-label {
    margin-bottom: 12px;
}

@media (max-width: 768px) {
    .review-documents-section {
        grid-template-columns: 1fr;
    }
}

/* Certification Card - Tertata Rapi */
.review-cert-card {
    background: white;
    border: 2px solid #e5e7eb;
    border-radius: 12px;
    padding: 24px;
    margin-bottom: 22px;
    transition: all 0.3s ease;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
}

.review-cert-card:hover {
    border-color: #FFA240;
    box-shadow: 0 6px 20px rgba(245, 124, 0, 0.18);
    transform: translateY(-2px);
}

.review-cert-card:last-child {
    margin-bottom: 0;
}


.review-cert-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 20px;
    padding-bottom: 16px;
    border-bottom: 2px solid #FFA240;
    gap: 15px;
}


.review-cert-name {
    margin: 0;
    color: #F57C00;
    font-size: 15px;
    font-weight: 800;
    display: flex;
    align-items: center;
    gap: 10px;
    flex: 1;
}

.review-cert-name i {
    font-size: 18px;
}

.review-cert-status {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 8px 14px;
    border-radius: 8px;
    font-size: 11px;
    font-weight: 800;
    white-space: nowrap;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.badge-verified-cert {
    background: #d1fae5;
    color: #065f46;
    box-shadow: 0 2px 6px rgba(16, 185, 129, 0.2);
}

.badge-pending-cert {
    background: #fef3c7;
    color: #78350f;
    box-shadow: 0 2px 6px rgba(245, 158, 11, 0.2);
}

.badge-rejected-cert {
    background: #fee2e2;
    color: #7f1d1d;
    box-shadow: 0 2px 6px rgba(239, 68, 68, 0.2);
}

.review-cert-body {
    padding: 0;
}


.review-cert-info-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
    margin-bottom: 20px;
    padding: 16px;
    background: #FFFDE7;
    border-radius: 8px;
}

.review-cert-info-col {
    font-size: 13px;
}


.review-cert-info-col strong {
    display: block;
    color: #F57C00;
    margin-bottom: 8px;
    font-weight: 800;
    text-transform: uppercase;
    font-size: 10px;
    letter-spacing: 0.8px;
}

.review-cert-info-col span {
    color: #374151;
    font-weight: 700;
    display: block;
    line-height: 1.6;
    font-size: 14px;
}


.review-cert-dates {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
    padding: 16px;
    background: linear-gradient(135deg, #FFF7ED 0%, #FFE0B2 100%);
    border-radius: 8px;
    border-left: 5px solid #F57C00;
}

.review-cert-date {
    font-size: 13px;
}


.review-cert-date strong {
    color: #F57C00;
    display: block;
    margin-bottom: 8px;
    font-weight: 800;
    text-transform: uppercase;
    font-size: 10px;
    letter-spacing: 0.8px;
}

.review-cert-date span {
    color: #374151;
    font-weight: 700;
    display: block;
    line-height: 1.6;
    font-size: 14px;
}

.review-cert-document {
    margin-top: 18px;
    padding-top: 18px;
    border-top: 2px solid #f3f4f6;
}


.review-doc-button {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 12px 18px;
    background: linear-gradient(135deg, #F57C00, #FFA240);
    color: white;
    border-radius: 8px;
    text-decoration: none;
    font-size: 13px;
    font-weight: 700;
    transition: all 0.3s ease;
    box-shadow: 0 4px 12px rgba(245, 124, 0, 0.18);
}

.review-doc-button:hover {
    transform: translateY(-3px);
    box-shadow: 0 6px 16px rgba(245, 124, 0, 0.28);
    background: linear-gradient(135deg, #E65100, #FFA240);
}

/* Position Badge - Standalone */
.review-position-section {
    background: white;
    padding: 18px;
    border-radius: 10px;
    border-left: 5px solid #667eea;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
    margin-top: 25px;
}

.review-position-section .review-info-label {
    margin-bottom: 12px;
}

.review-position-badge {
    display: inline-block;
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
    padding: 12px 24px;
    border-radius: 8px;
    font-size: 15px;
    font-weight: 800;
    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
    letter-spacing: 0.3px;
}

.review-position-type {
    display: block;
    margin-top: 12px;
    font-size: 13px;
    color: #6b7280;
    font-weight: 600;
}

.review-position-type strong {
    color: #1f2937;
    font-weight: 800;
}

/* Badge Status */
.badge-status-review {
    display: inline-block;
    padding: 8px 14px;
    border-radius: 8px;
    font-size: 11px;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.badge-success-review {
    background: #d1fae5;
    color: #065f46;
    box-shadow: 0 2px 6px rgba(16, 185, 129, 0.2);
}

.badge-warning-review {
    background: #fef3c7;
    color: #78350f;
    box-shadow: 0 2px 6px rgba(245, 158, 11, 0.2);
}

.badge-danger-review {
    background: #fee2e2;
    color: #7f1d1d;
    box-shadow: 0 2px 6px rgba(239, 68, 68, 0.2);
}

/* Empty State */
.review-empty-state {
    text-align: center;
    padding: 60px 30px;
    color: #9ca3af;
    background: linear-gradient(to bottom, #f9fafb, #f3f4f6);
    border-radius: 10px;
    border: 2px dashed #e5e7eb;
}

.review-empty-state i {
    font-size: 48px;
    color: #d1d5db;
    margin-bottom: 15px;
    display: block;
}

.review-empty-state p {
    margin: 0;
    font-size: 16px;
    font-weight: 600;
}

/* Filter Section - IMPROVED MODERN DESIGN */
.filter-section-approval {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    padding: 0;
    margin-bottom: 0;
    border-radius: 12px 12px 0 0;
    box-shadow: 0 -2px 10px rgba(102, 126, 234, 0.15);
}

.filter-container-approval {
    display: grid;
    grid-template-columns: auto 1fr auto;
    gap: 20px;
    align-items: center;
    padding: 20px 25px;
}

.filter-icon-box {
    background: rgba(255, 255, 255, 0.2);
    width: 50px;
    height: 50px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    backdrop-filter: blur(10px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

.filter-icon-box i {
    font-size: 22px;
    color: white;
}

.filter-content {
    display: flex;
    flex-direction: column;
    gap: 8px;
    flex: 1;
}

.filter-label {
    font-weight: 600;
    color: white;
    font-size: 13px;
    display: flex;
    align-items: center;
    gap: 8px;
    margin: 0;
    opacity: 0.95;
}

.filter-label i {
    font-size: 14px;
}

.filter-select-approval {
    padding: 12px 16px;
    border: 2px solid rgba(255, 255, 255, 0.3);
    border-radius: 8px;
    font-size: 14px;
    cursor: pointer;
    transition: all 0.3s ease;
    background: white;
    color: #333;
    font-weight: 500;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}

.filter-select-approval:hover {
    border-color: rgba(255, 255, 255, 0.6);
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

.filter-select-approval:focus {
    outline: none;
    border-color: white;
    box-shadow: 0 0 0 3px rgba(255, 255, 255, 0.3);
}

.filter-badge {
    background: rgba(255, 255, 255, 0.25);
    color: white;
    padding: 12px 20px;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 600;
    white-space: nowrap;
    display: flex;
    align-items: center;
    gap: 8px;
    backdrop-filter: blur(10px);
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}

.filter-badge i {
    font-size: 14px;
}

.table-info-approval {
    padding: 15px 25px;
    background: linear-gradient(to right, #e0e7ff, #f3f4f6);
    color: #667eea;
    font-size: 13px;
    font-weight: 600;
    border-top: 2px solid #c7d2fe;
    display: flex;
    align-items: center;
    gap: 10px;
}

.table-info-approval:before {
    content: '\f05a';
    font-family: 'Font Awesome 5 Free';
    font-weight: 900;
    font-size: 16px;
}

/* Responsive */
@media (max-width: 768px) {
    .filter-container-approval {
        grid-template-columns: 1fr;
        gap: 15px;
        padding: 20px;
    }
    
    .filter-icon-box {
        display: none;
    }
    
    .filter-badge {
        justify-content: center;
    }
}
</style>

<script>
// Modal control functions
function openModal(modalId) {
    document.getElementById(modalId).style.display = 'block';
}

function closeModal(modalId) {
    document.getElementById(modalId).style.display = 'none';
}

// Close modal when clicking outside
window.onclick = function(event) {
    if (event.target && event.target.classList && event.target.classList.contains('modal-approval')) {
        event.target.style.display = 'none';
    }
}

function i18n(key, fallback = '') {
    if (window.getLanguageText) {
        const translated = window.getLanguageText(key, fallback || null);
        if (translated !== null && translated !== undefined && translated !== '') {
            return translated;
        }
    }

    if (fallback) {
        return fallback;
    }

    return String(key)
        .replace(/[-_]+/g, ' ')
        .replace(/\b\w/g, function(ch) { return ch.toUpperCase(); });
}

function showReviewModal(appointmentId) {
    const loadingText = i18n('ktt-loading-data');
    document.getElementById('reviewContent').innerHTML = '<div style="text-align: center; padding: 40px;"><i class="fas fa-spinner fa-spin" style="font-size: 30px; color: #667eea;"></i><p style="color: #667eea; margin-top: 15px; font-weight: 600;">' + loadingText + '</p></div>';

    // Add cache busting parameter
    const timestamp = new Date().getTime();
    fetch('../../api/get_appointment_details.php?id=' + appointmentId + '&_=' + timestamp)
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(data => {
            // DEBUG: Log data to console
            console.log('=== DEBUG: Response Data ===');
            console.log('Full Response:', data);

            if (data.success) {
                const appointment = data.appointment;
                const employee = data.employee;
                const position = data.position;
                const certifications = data.certifications;

                // DEBUG: Log jabatan
                console.log('=== Employee Data ===');
                console.log('Full Name:', employee.full_name);
                console.log('Position:', employee.position);
                console.log('Competency Name:', employee.competency_name);
                console.log('Competency Type:', employee.competency_type);
                
                let html = '';

                // Previous Rejection Notice for Resubmitted Letters (jika ada)
                console.log('=== DEBUG: Checking for previous rejection ===');
                console.log('Full Appointment Object:', appointment);
                console.log('Previous KTT Rejection Notes:', appointment.previous_ktt_rejection_notes);
                console.log('Previous KTT Rejector Name:', appointment.previous_ktt_rejector_name);
                console.log('Resubmit Count:', appointment.resubmit_count);
                console.log('Admin Approval Notes:', appointment.admin_approval_notes);

                // Check if there's rejection data
                const hasRejectionNotes = appointment.previous_ktt_rejection_notes &&
                                         appointment.previous_ktt_rejection_notes !== null &&
                                         appointment.previous_ktt_rejection_notes.trim() !== '';
                console.log('Has Rejection Notes:', hasRejectionNotes);

                if (hasRejectionNotes) {
                    html += `
                        <div class="review-section" style="background: linear-gradient(135deg, #fff7ed 0%, #ffedd5 100%); border-left: 5px solid #f97316;">
                            <h4 style="color: #c2410c;">
                                <i class="fas fa-exclamation-triangle"></i> ${i18n('previous-rejection-details')}
                                ${appointment.resubmit_count > 0 ? `<span style="font-size: 13px; font-weight: 600; color: #92400e; margin-left: 10px;">(${i18n('resubmitted')} ${appointment.resubmit_count}x)</span>` : ''}
                            </h4>
                            <div style="background: white; padding: 20px; border-radius: 10px; border: 2px solid #fed7aa; margin-top: 15px;">
                                ${appointment.previous_ktt_rejector_name ? `
                                    <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 15px; padding-bottom: 15px; border-bottom: 2px solid #fed7aa;">
                                        <div style="background: #fef3c7; padding: 10px 15px; border-radius: 8px; display: inline-flex; align-items: center; gap: 8px;">
                                            <i class="fas fa-user-times" style="color: #ea580c; font-size: 16px;"></i>
                                            <span style="color: #92400e; font-weight: 700; font-size: 14px;">${i18n('rejected-by')}: ${appointment.previous_ktt_rejector_name}</span>
                                        </div>
                                    </div>
                                ` : ''}
                                <div style="padding: 15px; background: #fef3c7; border-radius: 8px; border-left: 4px solid #f97316;">
                                    <strong style="display: block; color: #c2410c; font-size: 13px; margin-bottom: 10px; text-transform: uppercase; letter-spacing: 0.5px;">
                                        <i class="fas fa-comment-alt"></i> ${i18n('ktt-rejection-reason')}
                                    </strong>
                                    <div style="color: #78350f; font-size: 14px; line-height: 1.7; white-space: pre-wrap; font-weight: 500;">
                                        ${appointment.previous_ktt_rejection_notes}
                                    </div>
                                </div>
                                ${appointment.admin_approval_notes ? `
                                    <div style="margin-top: 15px; padding: 12px; background: rgba(255, 247, 237, 0.5); border-radius: 8px; border: 1px dashed #fdba74;">
                                        <small style="display: block; font-size: 12px; color: #92400e; line-height: 1.6;">
                                            <i class="fas fa-user-shield" style="color: #f97316;"></i>
                                            <strong style="color: #c2410c;">${i18n('ktt-admin-notes')}</strong> ${appointment.admin_approval_notes}
                                        </small>
                                    </div>
                                ` : ''}
                            </div>
                        </div>
                    `;
                }

                // Section 1: Appointment Letter Data
                html += `
                    <div class="review-section">
                        <h4><i class="fas fa-file-contract"></i> ${i18n('ktt-assign-letter-data')}</h4>
                        <div class="review-info-grid">
                            <div class="review-info-item">
                                <div class="review-info-label"><i class="fas fa-hashtag"></i> ${i18n('letter-number')}</div>
                                <div class="review-info-value">${appointment.appointment_number}</div>
                            </div>
                            <div class="review-info-item">
                                <div class="review-info-label"><i class="fas fa-calendar"></i> ${i18n('appointment-date')}</div>
                                <div class="review-info-value">${formatDate(appointment.appointment_date)}</div>
                            </div>
                            <div class="review-info-item">
                                <div class="review-info-label"><i class="fas fa-calendar-check"></i> ${i18n('valid-from')}</div>
                                <div class="review-info-value">${formatDate(appointment.effective_date)}</div>
                            </div>
                            <div class="review-info-item">
                                <div class="review-info-label"><i class="fas fa-calendar-times"></i> ${i18n('expires')}</div>
                                <div class="review-info-value">${appointment.expiry_date ? formatDate(appointment.expiry_date) : i18n('lifetime')}</div>
                            </div>
                        </div>
                    </div>
                `;
                
                // Section 2: Data Identitas
                html += `
                    <div class="review-section">
                        <h4><i class="fas fa-user-circle"></i> ${i18n('ktt-identity-data')}</h4>
                        <div class="review-info-grid">
                            <div class="review-info-item">
                                <div class="review-info-label"><i class="fas fa-id-card"></i> ${i18n('full-name')}</div>
                                <div class="review-info-value">${employee.full_name}</div>
                                <div class="review-info-sub">${employee.employee_code}</div>
                            </div>
                            <div class="review-info-item">
                                <div class="review-info-label"><i class="fas fa-building"></i> ${i18n('company')}</div>
                                <div class="review-info-value">${employee.contractor_company}</div>
                            </div>
                            <div class="review-info-item">
                                <div class="review-info-label"><i class="fas fa-briefcase"></i> ${i18n('position')}</div>
                                <div class="review-info-value">${employee.position}</div>
                            </div>
                            <div class="review-info-item">
                                <div class="review-info-label"><i class="fas fa-award"></i> ${i18n('competency')}</div>
                                <div class="review-info-value">${employee.competency_name || i18n('not-specified')}</div>
                                <div class="review-info-sub">${employee.competency_type ? formatCompetencyType(employee.competency_type) : '-'}</div>
                            </div>
                        </div>
                        ${(employee.cv_file || employee.statement_file) ? `
                            <div class="review-documents-section">
                                ${employee.cv_file ? `
                                    <div class="review-doc-item">
                                        <div class="review-info-label"><i class="fas fa-file-pdf"></i> ${i18n('ktt-curriculum-vitae')}</div>
                                        <a href="/assets/${employee.cv_file}" target="_blank" class="btn btn-sm btn-info">
                                            <i class="fas fa-file-pdf"></i>
                                            <span data-lang="ktt-view-cv">${i18n('ktt-view-cv')}</span>
                                        </a>
                                    </div>
                                ` : ''}
                                ${employee.statement_file ? `
                                    <div class="review-doc-item">
                                        <div class="review-info-label"><i class="fas fa-file-contract"></i> ${i18n('ktt-statement-letter')}</div>
                                        <a href="/assets/${employee.statement_file}" target="_blank" class="btn btn-sm btn-info">
                                            <i class="fas fa-download"></i> ${i18n('ktt-view-statement')}
                                        </a>
                                    </div>
                                ` : ''}
                            </div>
                        ` : ''}
                    </div>
                `;

                // Section 3: Sertifikasi
                html += `
                    <div class="review-section">
                        <h4><i class="fas fa-certificate"></i> ${i18n('ktt-verified-certs')} <span style="color: #667eea; font-weight: 700;">(${certifications.length})</span></h4>
                `;
                
                if (certifications && certifications.length > 0) {
                    certifications.forEach((cert, index) => {
                        const isExpired = new Date(cert.expiry_date) < new Date();
                        
                        html += `
                            <div class="review-cert-card">
                                <div class="review-cert-header">
                                    <h5 class="review-cert-name">
                                        <i class="fas fa-certificate"></i> ${cert.cert_name}
                                    </h5>
                                    <span class="review-cert-status badge-verified-cert">
                                        <i class="fas fa-check-circle"></i>
                                        ${i18n('verified')}
                                    </span>
                                </div>
                                
                                <div class="review-cert-body">
                                    <div class="review-cert-info-row">
                                        <div class="review-cert-info-col">
                                            <strong>${i18n('certificate-number')}</strong>
                                            <span>${cert.cert_number}</span>
                                        </div>
                                        <div class="review-cert-info-col">
                                            <strong>${i18n('issuer')}</strong>
                                            <span>${cert.cert_issuer || '-'}</span>
                                        </div>
                                    </div>
                                    
                                    <div class="review-cert-dates">
                                        <div class="review-cert-date">
                                            <strong>${i18n('issue-date')}</strong>
                                            <span>${formatDate(cert.issue_date)}</span>
                                        </div>
                                        <div class="review-cert-date">
                                            <strong>${i18n('expiry-date')}</strong>
                                            <span class="${isExpired ? 'text-danger' : ''}">
                                                ${formatDate(cert.expiry_date)}
                                                ${isExpired ? ' <i class="fas fa-exclamation-triangle" style="color: #ef4444;"></i> ' + i18n('expired') : ''}
                                            </span>
                                        </div>
                                    </div>
                                    
                                    ${cert.document_file ? `
                                        <div class="review-cert-document">
                                            <a href="${cert.document_file}" target="_blank" class="review-doc-button">
                                                <i class="fas fa-file-pdf"></i> ${i18n('ktt-view-certificate')}
                                            </a>
                                        </div>
                                    ` : ''}
                                </div>
                            </div>
                        `;
                    });
                } else {
                    html += `
                        <div class="review-empty-state">
                            <i class="fas fa-inbox"></i>
                            <p>${i18n('ktt-no-verified-certs')}</p>
                        </div>
                    `;
                }
                
                html += '</div>';

                // DEBUG: Check the generated HTML for Jabatan section
                console.log('=== DEBUG: Generated HTML ===');
                const jabatanMatch = html.match(/<div class="review-info-label"><i class="fas fa-briefcase"><\/i>[^<]*<\/div>\s*<div class="review-info-value">(.*?)<\/div>/);
                if (jabatanMatch) {
                    console.log('Position HTML found:', jabatanMatch[1]);
                } else {
                    console.log('Position HTML NOT FOUND!');
                }

                document.getElementById('reviewContent').innerHTML = html;
                document.getElementById('printLink').href = '../../print_appointment.php?id=' + appointmentId;
                openModal('reviewModal');
            } else {
                const fallbackLoadText = i18n('ktt-failed-load-data');
                const errorPrefix = i18n('error');
                document.getElementById('reviewContent').innerHTML = '<div style="padding: 30px; text-align: center; color: #ef4444;"><i class="fas fa-exclamation-circle" style="font-size: 32px;"></i><p style="margin-top: 12px; font-weight: 600;">' + errorPrefix + ': ' + (data.message || fallbackLoadText) + '</p></div>';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            const errorPrefix = i18n('ktt-error-prefix');
            document.getElementById('reviewContent').innerHTML = '<div style="padding: 30px; text-align: center; color: #ef4444;"><i class="fas fa-exclamation-circle" style="font-size: 32px;"></i><p style="margin-top: 12px; font-weight: 600;">' + errorPrefix + ' ' + error.message + '</p></div>';
        });
}

function formatDate(dateString) {
    if (!dateString) return '-';
    const date = new Date(dateString);
    const options = { year: 'numeric', month: 'long', day: 'numeric' };
    return date.toLocaleDateString('id-ID', options);
}

function formatCompetencyType(type) {
    if (!type) return '-';
    const typeMap = {
        'pengawas_operasional': i18n('operational-supervisor'),
        'pengawas_teknis': i18n('technical-supervisor'),
        'tenaga_teknis': i18n('technical-personnel')
    };
    return typeMap[type] || type;
}

function getVerificationBadge(status) {
    switch(status) {
        case 'verified': return 'success';
        case 'pending': return 'warning';
        case 'rejected': return 'danger';
        default: return 'secondary';
    }
}

function showApprovalForm(id, action) {
    document.getElementById('approval_id').value = id;
    document.getElementById('approval_action').value = action;
    document.getElementById('approval_notes').value = '';
    
    const requiredSpan = document.getElementById('catatan-required');
    const textarea = document.getElementById('approval_notes');
    const hint = document.getElementById('catatan-hint');
    
    if (action === 'approve') {
        document.getElementById('modalTitle').textContent = i18n('accept-assign-letter');
        document.getElementById('submitBtn').className = 'btn btn-success';
        document.getElementById('submitBtn').innerHTML = '<i class="fas fa-check"></i> <span data-lang="accept">Accept</span>';
        
        // Notes are optional when approving
        requiredSpan.style.display = 'none';
        textarea.required = false;
        hint.textContent = i18n('additional-notes-optional');
    } else {
        document.getElementById('modalTitle').textContent = i18n('reject-assign-letter');
        document.getElementById('submitBtn').className = 'btn btn-danger';
        document.getElementById('submitBtn').innerHTML = '<i class="fas fa-times"></i> <span data-lang="reject">Reject</span>';
        
        // Notes are required when rejecting
        requiredSpan.style.display = 'inline';
        textarea.required = true;
        hint.textContent = i18n('notes-required-rejection-reason');
    }

    if (window.applyCurrentLanguage) {
        window.applyCurrentLanguage();
    }
    
    openModal('approvalModal');
}

// Validate form saat submit
(function(){
    const approvalFormEl = document.getElementById('approvalForm');
    if (!approvalFormEl) return;

    approvalFormEl.addEventListener('submit', function(e) {
        const actionEl = document.getElementById('approval_action');
        const notesEl = document.getElementById('approval_notes');
        const action = actionEl ? actionEl.value : '';
        const notes = notesEl ? notesEl.value.trim() : '';

        if (action === 'reject' && !notes) {
            e.preventDefault();
            const rejectionReasonRequired = i18n('rejection-reason-required');
            alert(rejectionReasonRequired);
            if (notesEl) notesEl.focus();
            return false;
        }
    });
})();

// Function to filter table by company
// IMPROVED: Now updates badge and info text dynamically
function filterTableByCompany(tableId, companyName) {
    const table = document.getElementById(tableId);
    const rows = table.getElementsByTagName('tbody')[0].getElementsByTagName('tr');
    let visibleCount = 0;
    let infoElementId = '';
    let badgeElementId = '';
    
    // Determine which info element to update
    if (tableId === 'decisionsTable') {
        infoElementId = 'tableInfoDecisions';
        badgeElementId = 'filterBadgeDecisions';
    } else if (tableId === 'historyTable') {
        infoElementId = 'tableInfoHistory';
        badgeElementId = 'filterBadgeHistory';
    }
    
    if (!companyName) {
        // Show all rows
        for (let row of rows) {
            if (row.classList.contains('empty-row')) {
                continue;
            }
            row.style.display = '';
            visibleCount++;
        }
        const showingAllData = i18n('showing-all-data-all-companies');
        const allDataText = i18n('all-data');
        updateTableInfo(infoElementId, showingAllData);
        updateFilterBadge(badgeElementId, allDataText, 'info-circle');
    } else {
        // Filter by company
        for (let row of rows) {
            if (row.classList.contains('empty-row')) {
                row.style.display = 'none';
                continue;
            }
            
            const rowCompany = row.getAttribute('data-company');
            if (rowCompany === companyName) {
                row.style.display = '';
                visibleCount++;
            } else {
                row.style.display = 'none';
            }
        }
        const showingDataFrom = i18n('showing-data-from');
        const dataText = i18n('data', 'data');
        updateTableInfo(infoElementId, showingDataFrom + ' ' + visibleCount + ' ' + dataText + ': ' + companyName);
        updateFilterBadge(badgeElementId, visibleCount + ' ' + dataText, 'check-circle');
    }
    
    // Show/hide empty state if no results
    const emptyRows = table.getElementsByClassName('empty-row');
    if (emptyRows.length > 0 && visibleCount === 0 && companyName) {
        emptyRows[0].style.display = '';
        const noDataCompanyText = i18n('no-data-for-company');
        emptyRows[0].innerHTML = '<td colspan="' + (tableId === 'decisionsTable' ? '7' : '8') + '" class="text-center"><i class="fas fa-inbox" style="font-size: 24px; color: #ccc; margin-right: 10px;"></i>' + noDataCompanyText + ' <strong>' + companyName + '</strong></td>';
    }
}

function updateTableInfo(elementId, message) {
    const infoElement = document.getElementById(elementId);
    if (infoElement) {
        infoElement.textContent = message;
    }
}

function updateFilterBadge(elementId, text, icon) {
    const badgeElement = document.getElementById(elementId);
    if (badgeElement) {
        badgeElement.innerHTML = '<i class="fas fa-' + icon + '"></i> ' + text;
    }
}
</script>

<script>
// Fallback/hardened modal & approval handlers to ensure buttons work
function safeFetchHtml(url, targetElId){
    fetch(url)
        .then(function(resp){ return resp.text(); })
        .then(function(html){
            var el = document.getElementById(targetElId);
            if(el) el.innerHTML = html;
        })
        .catch(function(){
            var el = document.getElementById(targetElId);
            if(el) el && (el.innerHTML = '<div style="padding:20px;color:#c2410c;">Gagal memuat data.</div>');
        });
}

// NOTE: The original `showReviewModal` implementation earlier in this file
// builds the full, styled review HTML from JSON. We removed the temporary
// override to allow that implementation to run. If you still see truncated
// content, ensure the browser loaded the latest file (Ctrl+F5) and that no
// other scripts override `showReviewModal` at runtime.

// Only define fallback approval form if original is missing
if (typeof window.showApprovalForm === 'undefined') {
    function showApprovalForm(id, action){
        var approvalAction = document.getElementById('approval_action');
        var approvalId = document.getElementById('approval_id');
        var approvalNotes = document.getElementById('approval_notes');
        if(approvalAction) approvalAction.value = action;
        if(approvalId) approvalId.value = id;
        if(approvalNotes){
            approvalNotes.value = ''; 
            approvalNotes.required = (action === 'reject');
        }
        var am = document.getElementById('approvalModal'); if(am) am.style.display = 'block';
    }
}

// Ensure modal close on background click (idempotent)
if (!window.__approvalModalBgListenerAttached) {
    window.addEventListener('click', function(event){
        if(event.target && event.target.classList && event.target.classList.contains('modal-approval')){
            event.target.style.display = 'none';
        }
    });
    window.__approvalModalBgListenerAttached = true;
}

// Defensive: attach submit handler only once
if (!window.__approvalFormFallbackAttached) {
    document.addEventListener('DOMContentLoaded', function(){
        var form = document.getElementById('approvalForm');
        if(form){
            form.addEventListener('submit', function(e){
                var actionInput = document.getElementById('approval_action');
                var notes = document.getElementById('approval_notes');
                if(actionInput && actionInput.value === 'reject' && notes && notes.value.trim() === ''){
                    e.preventDefault();
                    alert('Mohon isi catatan saat menolak.');
                    notes.focus();
                    return false;
                }
                return true;
            });
        }
    });
    window.__approvalFormFallbackAttached = true;
}
</script>

<?php require_once '../../includes/footer.php'; ?>






