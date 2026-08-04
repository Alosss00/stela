<?php
$page_title = 'Review KTT Rejections';
require_once dirname(__DIR__, 3) . '/app/Helpers/auth_helper.php';
// Included via bootstrap/app.php

// Check if user has permission
requirePermission('admin.access');
requirePermission('appointment.update');

// Pastikan ini ditaruh di baris paling awal sebelum ada output HTML/spasi
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Generate token CSRF jika belum ada di session
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$db = new Database();
$message = '';
$error = '';

// Handle admin decision
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // 1. Ambil token dari POST dan jalankan validasi CSRF
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (empty($csrf_token) || !isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrf_token)) {
        $error = 'Security validation failed. Request denied.';
    } else {
        // 2. Jika token valid, jalankan semua kode asli Anda di bawah ini
        if (isset($_POST['action'])) {
            $id = intval($_POST['id']);
            $admin_action = $db->escapeString($_POST['admin_action']); // 'send_to_user' or 'send_to_ktt'
            
            // Get notes from textarea (using JavaScript to sync before submit)
            $admin_notes = '';
            if (isset($_POST['admin_notes']) && !empty($_POST['admin_notes'])) {
                $admin_notes = $db->escapeString($_POST['admin_notes']);
            } else {
                // Fallback: try to get from the textarea by ID
                $admin_notes = $db->escapeString($_POST['admin_notes_value'] ?? 'No notes');
            }
            $current_admin_id = $_SESSION['user_id'];
            
            if ($admin_action == 'send_to_user') {
                // Get appointment details to determine which KTT rejected
                $appointment = $db->query("
                    SELECT employee_id, last_rejected_by_ktt, ktt_msm_status, ktt_ttn_status
                    FROM appointments WHERE id = $id
                ")->fetch_assoc();

                // Set flags for which KTT needs to review after resubmit
                $requires_ktt_msm = ($appointment['last_rejected_by_ktt'] == 'msm') ? 1 : 0;
                $requires_ktt_ttn = ($appointment['last_rejected_by_ktt'] == 'ttn') ? 1 : 0;

                // Get KTT rejection notes
                $ktt_rejection = $db->query("
                    SELECT approval_notes
                    FROM ktt_approvals
                    WHERE appointment_id = $id AND action = 'reject'
                    ORDER BY approval_date DESC LIMIT 1
                ")->fetch_assoc();
                $rejection_notes = isset($ktt_rejection['approval_notes']) ? $ktt_rejection['approval_notes'] : '';

                // Combine rejection notes for user
                $combined_notes = "Rejection from KTT: " . $rejection_notes . "\n\nAdmin Notes: " . $admin_notes;

                // Send back to user to fix data
                $update_sql = "UPDATE appointments SET
                              status = 'rejected',
                              admin_approved_by = $current_admin_id,
                              admin_approved_date = NOW(),
                              admin_approval_action = 'send_to_user',
                              admin_approval_notes = '$admin_notes',
                              requires_ktt_msm_review = $requires_ktt_msm,
                              requires_ktt_ttn_review = $requires_ktt_ttn,
                              resubmit_reason = '{$db->escapeString($combined_notes)}',
                              resubmit_count = COALESCE(resubmit_count, 0) + 1
                              WHERE id = $id AND status = 'rejected_by_ktt'";

                if ($db->query($update_sql)) {
                    if ($appointment) {
                        $db->query("UPDATE employees SET
                                   verification_status = 'rejected',
                                   verification_notes = '{$db->escapeString($combined_notes)}',
                                   verified_by = NULL,
                                   verified_date = NULL
                                   WHERE id = {$appointment['employee_id']}");
                    }

                    $message = stela_t('letter-returned-user-correction');
                    // Notify user/dept of the final rejection (KTT rejected + admin also rejected)
                    try {
                        // Included via bootstrap/app.php
                        set_time_limit(60);
                        $notifService = new NotificationService();
                        $notifService->notifyAdminFinalRejectionToUserDept($id, $admin_notes);
                    } catch (Exception $e) {
                        error_log("Notification error (admin final rejection): " . $e->getMessage());
                    }
                } else {
                    $error = stela_t('failed-process-decision');
                }

            } elseif ($admin_action == 'send_to_ktt') {
                // Get appointment details to determine which KTT rejected
                $appointment = $db->query("
                    SELECT last_rejected_by_ktt, rejected_by_ktt_user_id,
                           ktt_msm_status, ktt_ttn_status
                    FROM appointments WHERE id = $id
                ")->fetch_assoc();

                $rejected_ktt = $appointment['last_rejected_by_ktt'];

                // Validate: last_rejected_by_ktt must not be NULL
                if (empty($rejected_ktt)) {
                    // Fallback: determine from ktt statuses
                    if ($appointment['ktt_msm_status'] == 'rejected') {
                        $rejected_ktt = 'msm';
                    } elseif ($appointment['ktt_ttn_status'] == 'rejected') {
                        $rejected_ktt = 'ttn';
                    } else {
                        $error = stela_t('cannot-send-ktt-no-rejection');
                        goto end_send_to_ktt;
                    }
                }

                // Set flags for which KTT needs to review (only rejected ones)
                $requires_ktt_msm = ($rejected_ktt == 'msm' || $appointment['ktt_msm_status'] == 'rejected') ? 1 : 0;
                $requires_ktt_ttn = ($rejected_ktt == 'ttn' || $appointment['ktt_ttn_status'] == 'rejected') ? 1 : 0;

                // Only reset the rejected KTT's status to pending, keep approved KTT intact
                if ($requires_ktt_msm) {
                    $db->query("UPDATE appointments SET
                               ktt_msm_status = 'pending',
                               ktt1_approved_by = NULL,
                               ktt1_approved_date = NULL
                               WHERE id = $id");
                }
                if ($requires_ktt_ttn) {
                    $db->query("UPDATE appointments SET
                               ktt_ttn_status = 'pending',
                               ktt2_approved_by = NULL,
                               ktt2_approved_date = NULL
                               WHERE id = $id");
                }

                // Send back to KTT for re-review (only rejected KTT(s))
                $update_sql = "UPDATE appointments SET
                              status = 'pending',
                              admin_approved_by = $current_admin_id,
                              admin_approved_date = NOW(),
                              admin_approval_action = 'send_to_ktt',
                              admin_approval_notes = '$admin_notes',
                              requires_ktt_msm_review = $requires_ktt_msm,
                              requires_ktt_ttn_review = $requires_ktt_ttn,
                              last_rejected_by_ktt = NULL,
                              rejected_by_ktt_user_id = NULL
                              WHERE id = $id AND status = 'rejected_by_ktt'";

                if ($db->query($update_sql)) {
                    // Delete only the rejected KTT's approval records
                    if ($requires_ktt_msm) {
                        $db->query("DELETE FROM ktt_approvals WHERE appointment_id = $id AND ktt_user_id = 7");
                    }
                    if ($requires_ktt_ttn) {
                        $db->query("DELETE FROM ktt_approvals WHERE appointment_id = $id AND ktt_user_id = 8");
                    }

                    // Send email notification to KTT
                    try {
                        // Included via bootstrap/app.php
                        $notifService = new NotificationService();
                        $notifService->notifyKttForApproval($id, $requires_ktt_msm == 1, $requires_ktt_ttn == 1);
                    } catch (Exception $e) {
                        error_log("Notification error (admin send to KTT): " . $e->getMessage());
                    }

                    $ktt_name = ($rejected_ktt == 'msm') ? 'KTT MSM' : 'KTT TTN';
                    $message = stela_t('letter-sent-back-ktt-rereview', ['ktt_list' => $ktt_name]);
                } else {
                    $error = stela_t('failed-process-decision');
                }

                end_send_to_ktt: // Label untuk goto jika validasi gagal
            }
        }
    }
}

// Get appointments that are rejected by KTT and waiting for admin review
$rejected_by_ktt = $db->query("
    SELECT a.*, e.full_name as employee_name, e.employee_code, e.position, e.competency_name,
           e.contractor_company, p.position_name, p.position_type,
           u.full_name as created_by_name,
           ktt_rejection.approval_notes as ktt_rejection_notes,
           ktt_rejector.full_name as ktt_rejector_name,
           (SELECT COUNT(*) FROM ktt_approvals WHERE appointment_id = a.id AND action = 'reject') as rejection_count,
           COALESCE(a.resubmit_count, 0) as resubmit_count,
           a.ktt_msm_status, a.ktt_ttn_status, a.last_rejected_by_ktt,
           ktt_msm.full_name as ktt_msm_name, ktt_ttn.full_name as ktt_ttn_name
    FROM appointments a
    JOIN employees e ON a.employee_id = e.id
    LEFT JOIN positions p ON a.position_id = p.id
    LEFT JOIN users u ON a.created_by = u.id
    LEFT JOIN ktt_approvals ktt_rejection ON a.id = ktt_rejection.appointment_id AND ktt_rejection.action = 'reject'
    LEFT JOIN users ktt_rejector ON ktt_rejection.ktt_user_id = ktt_rejector.id
    LEFT JOIN users ktt_msm ON a.ktt1_approved_by = ktt_msm.id
    LEFT JOIN users ktt_ttn ON a.ktt2_approved_by = ktt_ttn.id
    WHERE a.status = 'rejected_by_ktt'
    ORDER BY ktt_rejection.approval_date DESC
");

// Get rejected appointments that already reviewed by admin (history)
$admin_reviewed = $db->query("
    SELECT a.*, e.full_name as employee_name, e.employee_code, e.contractor_company,
           p.position_name, u1.full_name as created_by_name,
           u2.full_name as admin_reviewer_name,
           CASE
               WHEN a.admin_approval_action = 'send_to_user' THEN 'Returned to User'
               WHEN a.admin_approval_action = 'send_to_ktt' THEN 'Sent to KTT'
               ELSE 'Unknown'
           END as admin_action_label
    FROM appointments a
    JOIN employees e ON a.employee_id = e.id
    JOIN positions p ON a.position_id = p.id
    LEFT JOIN users u1 ON a.created_by = u1.id
    LEFT JOIN users u2 ON a.admin_approved_by = u2.id
    WHERE a.admin_approved_by IS NOT NULL
    ORDER BY a.admin_approved_date DESC
    LIMIT 20
");

require_once dirname(__DIR__) . '/layouts/header.php';
?>

<div class="approval-admin-container">
    <!-- Page Header -->
    <div class="page-header-approval">
        <div class="header-left">
            <h2><i class="fas fa-tasks"></i> <span data-lang="review-ktt-rejections">Review KTT Rejections</span></h2>
            <p data-lang="review-ktt-rejections-subtitle">Review letters rejected by KTT and determine next action</p>
        </div>
        <div class="header-stats">
            <div class="stat-mini">
                <span class="stat-label" data-lang="pending">Pending</span>
                <span class="stat-value"><?php echo $rejected_by_ktt->num_rows; ?></span>
            </div>
        </div>
    </div>
    
    <?php if ($message): ?>
    <div class="alert alert-success alert-approval">
        <i class="fas fa-check-circle"></i>
        <div>
            <strong data-lang="success">Success!</strong>
            <p><?php echo htmlspecialchars($message); ?></p>
        </div>
    </div>
    <?php endif; ?>
    
    <?php if ($error): ?>
    <div class="alert alert-error alert-approval">
        <i class="fas fa-exclamation-circle"></i>
        <div>
            <strong data-lang="error">Error!</strong>
            <p><?php echo htmlspecialchars($error); ?></p>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Pending Admin Review Section -->
    <div class="approval-section">
        <div class="section-header">
            <h3><i class="fas fa-hourglass-half"></i> <span data-lang="waiting-admin-review">Waiting for Admin Review</span></h3>
            <span class="badge-count"><?php echo $rejected_by_ktt->num_rows; ?> <span data-lang="letters">Letters</span></span>
        </div>
        
        <?php if ($rejected_by_ktt->num_rows > 0): ?>
            <div class="approvals-grid">
                <?php while ($row = $rejected_by_ktt->fetch_assoc()): ?>
                <div class="approval-card card-rejection">
                    <!-- Card Header -->
                    <div class="card-header-approval">
                        <div class="header-title">
                            <h4>
                                <?php echo htmlspecialchars($row['employee_name']); ?>
                                <span class="employee-code">(<?php echo htmlspecialchars($row['employee_code']); ?>)</span>
                                <?php if ($row['resubmit_count'] > 0): ?>
                                    <span class="resubmit-badge" title="Resubmitted <?php echo $row['resubmit_count']; ?> time(s)">
                                        <i class="fas fa-redo"></i> Resubmit #<?php echo $row['resubmit_count']; ?>
                                    </span>
                                <?php endif; ?>
                            </h4>
                        </div>
                        <div class="status-badge status-rejected-ktt">
                            <i class="fas fa-ban"></i> <span data-lang="rejected-by-ktt">Rejected by KTT</span>
                        </div>
                    </div>
                    
                    <!-- Card Content -->
                    <div class="card-content">
                        <div class="info-row">
                            <span class="label" data-lang="letter-no">Letter No.:</span>
                            <span class="value"><?php echo htmlspecialchars($row['appointment_number']); ?></span>
                        </div>
                        <div class="info-row">
                            <span class="label" data-lang="position">Position:</span>
                            <span class="value"><?php echo htmlspecialchars($row['position_name']); ?></span>
                        </div>
                        <div class="info-row">
                            <span class="label" data-lang="company">Company:</span>
                            <span class="value"><?php echo htmlspecialchars($row['contractor_company']); ?></span>
                        </div>
                        
                        <!-- KTT Status Info -->
                        <div class="info-row">
                            <span class="label" data-lang="ktt-msm">KTT MSM:</span>
                            <span class="value">
                                <?php 
                                if ($row['ktt_msm_status'] == 'approved') {
                                    echo '<span class="status-mini status-approved"><i class="fas fa-check"></i> <span data-lang="approved">Approved</span></span>';
                                } elseif ($row['ktt_msm_status'] == 'rejected') {
                                    echo '<span class="status-mini status-rejected"><i class="fas fa-times"></i> <span data-lang="rejected">Rejected</span></span>';
                                } else {
                                    echo '<span class="status-mini status-pending"><i class="fas fa-clock"></i> <span data-lang="pending">Pending</span></span>';
                                }
                                ?>
                            </span>
                            <span class="label" style="margin-left: 15px;" data-lang="ktt-ttn">KTT TTN:</span>
                            <span class="value">
                                <?php 
                                if ($row['ktt_ttn_status'] == 'approved') {
                                    echo '<span class="status-mini status-approved"><i class="fas fa-check"></i> <span data-lang="approved">Approved</span></span>';
                                } elseif ($row['ktt_ttn_status'] == 'rejected') {
                                    echo '<span class="status-mini status-rejected"><i class="fas fa-times"></i> <span data-lang="rejected">Rejected</span></span>';
                                } else {
                                    echo '<span class="status-mini status-pending"><i class="fas fa-clock"></i> <span data-lang="pending">Pending</span></span>';
                                }
                                ?>
                            </span>
                        </div>
                        
                        <!-- KTT Rejection Reason -->
                        <div class="rejection-reason">
                            <strong><i class="fas fa-exclamation-triangle"></i> <span data-lang="rejection-reason">Rejection Reason:</span></strong>
                            <?php echo nl2br(htmlspecialchars($row['ktt_rejection_notes'])); ?>
                            <small>- <?php echo htmlspecialchars($row['ktt_rejector_name']); ?> | <?php echo isset($row['created_at']) ? date('d M Y H:i', strtotime($row['created_at'])) : ''; ?></small>
                        </div>
                    </div>
                    
                    <!-- Card Actions -->
                    <div class="card-actions">
                        <div class="form-group">
                            <label for="admin_notes_<?php echo $row['id']; ?>" data-lang="ktt-admin-notes">Admin Notes:</label>
                            <textarea name="admin_notes" id="admin_notes_<?php echo $row['id']; ?>"
                                     placeholder="Enter notes or reason for decision..." data-lang-placeholder="enter-notes-or-reason-decision"></textarea>
                            <small class="text-muted" style="display: block; margin-top: 4px; font-size: 11px;" data-lang="notes-required-if-rejecting">Notes are required when rejecting, optional when accepting.</small>
                        </div>

                        <div class="action-buttons">
                    <form method="POST" class="inline-form" onsubmit="return confirmAccept(this);">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">

                        <input type="hidden" name="id" value="<?php echo $row['id']; ?>">
                        <input type="hidden" name="admin_action" value="send_to_ktt">
                        <input type="hidden" name="admin_notes_value" value="">
                        <button type="submit" name="action" value="review" class="btn btn-accept">
                        <i class="fas fa-check-circle"></i> <span data-lang="accept-send-to-ktt">Accept - Send to KTT</span>
                            </button>
                    </form>

                    <form method="POST" class="inline-form" onsubmit="return confirmReject(this);">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">

                        <input type="hidden" name="id" value="<?php echo $row['id']; ?>">
                        <input type="hidden" name="admin_action" value="send_to_user">
                        <input type="hidden" name="admin_notes_value" value="">
                        <button type="submit" name="action" value="review" class="btn btn-reject">
                        <i class="fas fa-times-circle"></i> <span data-lang="reject-return-to-user">Reject - Return to User</span>
                            </button>
                    </form>
                        </div>
                    </div>
                </div>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-inbox"></i>
                <p data-lang="no-letters-waiting-admin-review">No letters waiting for admin review</p>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Admin Review History Section -->
    <div class="approval-section">
        <div class="section-header">
            <h3><i class="fas fa-history"></i> <span data-lang="admin-review-history">Admin Review History</span></h3>
            <span class="badge-count"><?php echo $admin_reviewed->num_rows; ?> <span data-lang="letters">Letters</span></span>
        </div>
        
        <?php if ($admin_reviewed->num_rows > 0): ?>
            <div class="table-responsive">
                <table class="table table-history" style="width: 100% !important; table-layout: fixed !important; width: 100% !important; table-layout: fixed !important;">
                    <thead>
                        <tr>
                            <th data-lang="employee-name">Employee Name</th>
                            <th data-lang="letter-number">Letter Number</th>
                            <th data-lang="position">Position</th>
                            <th data-lang="admin-action">Admin Action</th>
                            <th data-lang="review-date">Review Date</th>
                            <th data-lang="admin">Admin</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = $admin_reviewed->fetch_assoc()): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($row['employee_name']); ?></strong></td>
                            <td><?php echo htmlspecialchars($row['appointment_number']); ?></td>
                            <td><?php echo htmlspecialchars($row['position_name']); ?></td>
                            <td>
                                <span class="badge <?php echo $row['admin_approval_action'] == 'send_to_user' ? 'badge-warning' : 'badge-info'; ?>">
                                    <?php echo htmlspecialchars($row['admin_action_label']); ?>
                                </span>
                            </td>
                            <td><?php echo date('d M Y H:i', strtotime($row['admin_approved_date'])); ?></td>
                            <td><?php echo htmlspecialchars($row['admin_reviewer_name']); ?></td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-inbox"></i>
                <p data-lang="no-admin-review-yet">No admin review has been done yet</p>
            </div>
        <?php endif; ?>
    </div>
</div>



<script>
document.addEventListener('DOMContentLoaded', function() {
    // Note: Form validation is handled by confirmAccept() and confirmReject() functions
});

// Confirm accept action (notes optional)
function confirmAccept(form) {
    const card = form.closest('.approval-card');
    const textarea = card.querySelector('textarea[name="admin_notes"]');
    const hiddenInput = form.querySelector('input[name="admin_notes_value"]');

    // Sync textarea value to hidden input
    if (textarea && hiddenInput) {
        hiddenInput.value = textarea.value;
    }

    // Notes are optional for accept
    return confirm(window.getLanguageText(''));
}

// Confirm reject action (notes required)
function confirmReject(form) {
    const card = form.closest('.approval-card');
    const textarea = card.querySelector('textarea[name="admin_notes"]');
    const hiddenInput = form.querySelector('input[name="admin_notes_value"]');

    // Sync textarea value to hidden input
    if (textarea && hiddenInput) {
        hiddenInput.value = textarea.value;
    }

    // Validate textarea is not empty for reject
    if (!textarea.value.trim()) {
        alert(window.getLanguageText(''));
        textarea.focus();
        return false;
    }

    return confirm(window.getLanguageText(''));
}
</script>

<?php require_once dirname(__DIR__) . '/layouts/footer.php'; ?>
