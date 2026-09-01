<?php
require_once dirname(__DIR__, 2) . '/bootstrap/app.php';
$page_title = 'KTT Approval';
require_once dirname(__DIR__, 2) . '/app/Helpers/auth_helper.php';
// Included via bootstrap/app.php
// Included via bootstrap/app.php

// Check if user has KTT access or is superadmin
requirePermission('appointment.view');
if (!hasPermission('ktt.access') && !isSuperadmin()) {
    header('Location: ../admin/dashboard.php');
    exit();
}

$is_superadmin = isSuperadmin();

$db = new Database();
$message = '';
$error = '';
$current_user_id = $_SESSION['user_id'];

// Determine KTT type: user_id 7 = KTT MSM, user_id 8 = KTT TTN
$ktt_type = ($current_user_id == 7) ? 'msm' : 'ttn';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!verify_csrf_token()) {
        die("CSRF Token Invalid. Silakan muat ulang halaman.");
    }

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
            SELECT $my_status_field as my_ktt_status FROM appointments WHERE deleted_at IS NULL AND id = ?
        ", [$id])->fetch_assoc();

        if (!$appt_check || $appt_check['my_ktt_status'] != 'pending') {
            $error = 'You have already made a decision for this assign letter!';
        } else {
            // Delete any stale ktt_approvals from previous rounds for this KTT
            $db->query("DELETE FROM ktt_approvals WHERE appointment_id = ? AND ktt_user_id = $current_user_id", [$id]);

            if ($_POST['action'] == 'approve') {
                // Insert approval record
                $sql = "INSERT INTO ktt_approvals (appointment_id, ktt_user_id, action, approval_notes)
                        VALUES ($id, $current_user_id, 'approve', '$approval_notes')";

                if ($db->query($sql)) {
                    // Log to Workflow History
                    try {
                        require_once dirname(__DIR__, 2) . '/app/Services/AuditService.php';
                        $audit = new AuditService();
                        $employee_id = $db->query("SELECT employee_id FROM appointments WHERE id = ?", [$id])->fetch_assoc()['employee_id'] ?? null;
                        $action_name = ($ktt_type == 'msm') ? 'Approved by KTT MSM' : 'Approved by KTT TTN';
                        $audit->log($employee_id, $id, $action_name, 'pending', 'approved', $approval_notes);
                    } catch (Exception $e) {
                        error_log("Audit error: " . $e->getMessage());
                    }

                    // Get current appointment status
                    $appointment = $db->query("
                        SELECT ktt_msm_status, ktt_ttn_status, requires_ktt_msm_review, requires_ktt_ttn_review FROM appointments WHERE deleted_at IS NULL AND id = ?
                    ", [$id])->fetch_assoc();

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
                        } elseif ($appointment['ktt_ttn_status'] == 'approved' || $appointment['requires_ktt_ttn_review'] == 0) {
                            // Both KTT approved - set final approval
                            $final_sql = "UPDATE appointments SET
                                         status = 'approved',
                                         approved_by = $current_user_id,
                                         approved_date = NOW(),
                                         final_approval_date = NOW(),
                                         approval_notes = '$approval_notes'
                                         WHERE id = $id";
                            $db->query($final_sql);
                            
                            /* UPDATE CERTIFICATE STATUS */
                            $employee = $db->query("
                            SELECT employee_id
                            FROM appointments
                            WHERE id=?
                            ", [$id])->fetch_assoc();

                            $employee_id = (int)$employee['employee_id'];

                            $db->query("
                            UPDATE employee_certifications
                            SET
                                status='active',
                                verification_status='verified'
                            WHERE id=
                            (
                                SELECT latest_id
                                FROM
                                (
                                    SELECT MAX(id) latest_id
                                    FROM employee_certifications
                                    WHERE employee_id=?
                                ) x
                            )
                            ", [$employee_id]);
                        
                            $message = 'Assign letter successfully approved!';
                            // Notify admin and user/dept that both KTTs approved
                            try {
                                set_time_limit(60);
                                $notifService = new NotificationService();
                                $notifService->notifyKttBothApprovedToAdmin($id);
                                $notifService->notifyKttApprovedFinalToUserDept($id);
                            } catch (\Throwable $e) {
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
                        } elseif ($appointment['ktt_msm_status'] == 'approved' || $appointment['requires_ktt_msm_review'] == 0) {
                            // Both KTT approved - set final approval
                            $final_sql = "UPDATE appointments SET
                                         status = 'approved',
                                         approved_by = $current_user_id,
                                         approved_date = NOW(),
                                         final_approval_date = NOW(),
                                         approval_notes = '$approval_notes'
                                         WHERE id = $id";
                            $db->query($final_sql);

                            /* UPDATE CERTIFICATE STATUS */
                                $employee = $db->query("
                                SELECT employee_id
                                FROM appointments
                                WHERE id=?
                                ", [$id])->fetch_assoc();

                                $employee_id = (int)$employee['employee_id'];

                                $db->query("
                                UPDATE employee_certifications
                                SET
                                    status='active',
                                    verification_status='verified'
                                WHERE id=
                                (
                                    SELECT latest_id
                                    FROM
                                    (
                                        SELECT MAX(id) latest_id
                                        FROM employee_certifications
                                        WHERE employee_id=?
                                    ) x
                                )
                                ", [$employee_id]);
                                
                            $message = 'Assign letter successfully approved!';
                            // Notify admin and user/dept that both KTTs approved
                            try {
                                set_time_limit(60);
                                $notifService = new NotificationService();
                                $notifService->notifyKttBothApprovedToAdmin($id);
                                $notifService->notifyKttApprovedFinalToUserDept($id);
                            } catch (\Throwable $e) {
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
                    // Log to Workflow History
                    try {
                        require_once dirname(__DIR__, 2) . '/app/Services/AuditService.php';
                        $audit = new AuditService();
                        $employee_id = $db->query("SELECT employee_id FROM appointments WHERE id = ?", [$id])->fetch_assoc()['employee_id'] ?? null;
                        $action_name = ($ktt_type == 'msm') ? 'Rejected by KTT MSM' : 'Rejected by KTT TTN';
                        $audit->log($employee_id, $id, $action_name, 'pending', 'rejected', $approval_notes);
                    } catch (Exception $e) {
                        error_log("Audit error: " . $e->getMessage());
                    }

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
                        WHERE id = ?
                    ", [$id])->fetch_assoc();

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
                                // Included via bootstrap/app.php
                                set_time_limit(60);
                                $notificationService = new NotificationService();
                                $notificationService->notifyAppointmentRejectedForReview($id);
                            } catch (\Throwable $e) {
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
           (SELECT COUNT(*) FROM ktt_approvals ka WHERE ka.appointment_id = a.id AND ka.ktt_user_id = ?) as my_decision,
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
", [$current_user_id]);

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
    WHERE ka.ktt_user_id = ?
    ORDER BY ka.approval_date DESC
    LIMIT 20
", [$current_user_id]);

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
    WHERE ka.ktt_user_id = ?
    ORDER BY e.contractor_company
", [$current_user_id]);

// Get unique companies for filter - Riwayat Keseluruhan
$companies_history = $db->query("
    SELECT DISTINCT e.contractor_company
    FROM appointments a JOIN employees e ON a.employee_id = e.id WHERE a.deleted_at IS NULL AND e.deleted_at IS NULL AND a.status IN ('approved', 'rejected')
    ORDER BY e.contractor_company
");

require_once dirname(__DIR__) . '/layouts/header.php';
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
    
    <?php if ($pending === false) {
    die("SQL Error: " . $db->getConnection()->error);
} ?>
    <!-- Pending Approvals Section -->
    <div class="approval-section">
        <div class="section-header">
            <h3><i class="fas fa-hourglass-half"></i> <span data-lang="pending-your-approval">Pending Your Approval</span></h3>
        </div>
        
        <div class="approvals-grid">
            <?php if ($pending && $pending->num_rows > 0): ?>
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
                    <div class="info-section p-2">
                        <table class="table table-borderless table-sm mb-0">
                            <tbody>
                                <tr>
                                    <td style="width: 50%; padding-bottom: 12px;">
                                        <label class="text-muted small fw-bold mb-1" style="text-transform: uppercase; letter-spacing: 0.5px;">
                                            <i class="fas fa-id-card text-warning"></i> <span data-lang="employee">Employee</span>
                                        </label>
                                        <div class="fw-bold text-dark"><?php echo htmlspecialchars($row['employee_name']); ?></div>
                                        <div class="small text-muted"><?php echo htmlspecialchars($row['employee_code']); ?></div>
                                    </td>
                                    <td style="width: 50%; padding-bottom: 12px;">
                                        <label class="text-muted small fw-bold mb-1" style="text-transform: uppercase; letter-spacing: 0.5px;">
                                            <i class="fas fa-briefcase text-warning"></i> <span data-lang="position">Position</span>
                                        </label>
                                        <div class="fw-bold text-dark"><?php echo htmlspecialchars($row['position']); ?></div>
                                    </td>
                                </tr>
                                <tr>
                                    <td>
                                        <label class="text-muted small fw-bold mb-1" style="text-transform: uppercase; letter-spacing: 0.5px;">
                                            <i class="fas fa-building text-warning"></i> <span data-lang="company">Company</span>
                                        </label>
                                        <div class="fw-bold text-dark"><?php echo htmlspecialchars($row['contractor_company']); ?></div>
                                    </td>
                                    <td>
                                        <label class="text-muted small fw-bold mb-1" style="text-transform: uppercase; letter-spacing: 0.5px;">
                                            <i class="fas fa-calendar-check text-warning"></i> <span data-lang="valid">Valid</span>
                                        </label>
                                        <div class="fw-bold text-dark"><?php echo date('d M Y', strtotime($row['effective_date'])); ?></div>
                                        <?php if ($row['expiry_date']): ?>
                                            <div class="small text-muted"><span data-lang="until-short">s/d</span> <?php echo date('d M Y', strtotime($row['expiry_date'])); ?></div>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
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
    <?= csrf_field() ?>
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
    
    // Buka modal segera agar loading terlihat
    openModal('reviewModal');

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
                                        <a href="${employee.cv_file}" target="_blank" class="btn btn-sm btn-info">
                                            <i class="fas fa-file-pdf"></i>
                                            <span data-lang="ktt-view-cv">${i18n('ktt-view-cv')}</span>
                                        </a>
                                    </div>
                                ` : ''}
                                ${employee.statement_file ? `
                                    <div class="review-doc-item">
                                        <div class="review-info-label"><i class="fas fa-file-contract"></i> ${i18n('ktt-statement-letter')}</div>
                                        <a href="${employee.statement_file}" target="_blank" class="btn btn-sm btn-info">
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
                                            <a href="${cert.document_file}" target="_blank" class="btn btn-sm btn-info">
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

                // Append workflow history if available
                if (data.workflow_html && data.workflow_html.trim() !== '') {
                    html += '<div class="approval-header" style="margin-top: 25px; margin-bottom: 25px; border-bottom: 2px solid #ddd; padding-bottom: 10px;">';
                    html += '    <h3 style="color: #333; margin: 0; font-size: 1.25rem;"><i class="fas fa-history" style="color: #6c757d; margin-right: 8px;"></i> <span data-lang="workflow-history">Workflow History</span></h3>';
                    html += '</div>';
                    html += data.workflow_html;
                }

                document.getElementById('reviewContent').innerHTML = html;
                document.getElementById('printLink').href = '../../exports/print_appointment.php?id=' + appointmentId;
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
                
                // UI Update for mutually exclusive buttons
                var submitBtn = document.getElementById('submitBtn');
                var cancelBtn = form.querySelector('.btn-modal-cancel');
                
                if (submitBtn) {
                    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> <span data-lang="processing">Processing...</span>';
                    submitBtn.style.pointerEvents = 'none';
                    submitBtn.style.opacity = '0.7';
                }
                if (cancelBtn) {
                    cancelBtn.style.display = 'none';
                }
                
                return true;
            });
        }
    });
    window.__approvalFormFallbackAttached = true;
}
</script>

<?php require_once dirname(__DIR__) . '/layouts/footer.php'; ?>
