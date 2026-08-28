<?php
require_once dirname(__DIR__, 2) . '/bootstrap/app.php';
ob_start();
$page_title = 'Appointment Letters';
require_once dirname(__DIR__, 2) . '/app/Helpers/auth_helper.php';
// Included via bootstrap/app.php

requirePermission('admin.access');
requirePermission('appointment.view');

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

// Function to generate appointment number based on employee competency type
function generateAppointmentNumber($db, $employee_id, $appointment_date) {
    // Get employee competency type and ruang_lingkup
    $emp_result = $db->query("SELECT competency_type, ruang_lingkup FROM employees WHERE deleted_at IS NULL AND id = ?", [$employee_id]);
    $emp_row = $emp_result->fetch_assoc();
    
    $competency_type = $emp_row['competency_type'];
    $ruang_lingkup = $emp_row['ruang_lingkup'];
    
    // Map competency type to code
    $type_codes = [
        'pengawas_operasional' => 'PO',
        'pengawas_teknis' => 'PT',
        'tenaga_teknis' => 'TT'
    ];
    
    // Map ruang_lingkup to code - extract MSM or TTN from ruang_lingkup field
    $scope_code = 'UNK';
    if (stripos($ruang_lingkup, 'MSM') !== false && stripos($ruang_lingkup, 'TTN') !== false) {
        $scope_code = 'MSM/TTN';
    } elseif (stripos($ruang_lingkup, 'MSM') !== false) {
        $scope_code = 'MSM';
    } elseif (stripos($ruang_lingkup, 'TTN') !== false) {
        $scope_code = 'TTN';
    }
    
    $type_code = $type_codes[$competency_type] ?? 'UNK';
    
    // Get month and year from appointment date - KEEP LEADING ZERO FOR MONTH
    $month = date('m', strtotime($appointment_date)); // Format: 01-12 with leading zero
    $year = date('Y', strtotime($appointment_date));
    
    // Get last number for this combination (competency_type/scope/month/year)
    $result = $db->query("
        SELECT COALESCE(MAX(CAST(SUBSTRING_INDEX(appointment_number, '/', 1) AS UNSIGNED)), 0) as last_number
        FROM appointments 
        WHERE appointment_number LIKE '%/$type_code/$scope_code/$month/$year'
    ");
    
    $row = $result->fetch_assoc();
    $next_number = ($row['last_number'] ?? 0) + 1;
    $next_number = str_pad($next_number, 3, '0', STR_PAD_LEFT); // 001, 002, etc
    
    // Format: 001/PO/MSM/03/2026
    return "$next_number/$type_code/$scope_code/$month/$year";
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // --- IMPLEMENTASI ANTI-CSRF ---
    // Validasi apakah token CSRF ada dan cocok dengan token di session
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        header($_SERVER['SERVER_PROTOCOL'] . ' 400 Bad Request');
    }
    if (isset($_POST['action'])) {
        // Handle admin review of KTT rejections
        if ($_POST['action'] == 'admin_review') {
            $id = intval($_POST['id']);
            $admin_action = $db->escapeString($_POST['admin_action']); // 'send_to_user' or 'send_to_ktt'
            $admin_notes = $db->escapeString($_POST['admin_notes_value'] ?? 'No notes');
            $current_admin_id = $_SESSION['user_id'];

            $appt_check = $db->query("SELECT status FROM appointments WHERE id = ?", [$id])->fetch_assoc();
            if ($appt_check['status'] !== 'rejected_by_ktt') {
                $error = "This appointment has already been reviewed.";
            }

            if (empty($error)) {
            // IMPORTANT: Save rejection history BEFORE any action (for both send_to_user and send_to_ktt)
            // Get rejection info to preserve for history
            $rejection_history = $db->query("
                SELECT ka.approval_notes, u.full_name as rejector_name, ka.approval_date
                FROM ktt_approvals ka
                JOIN users u ON ka.ktt_user_id = u.id
                WHERE ka.appointment_id = ? AND ka.action = 'reject'
                ORDER BY ka.approval_date DESC
                LIMIT 1
            ", [$id])->fetch_assoc();

            // Store rejection history in appointments table for future reference
            if ($rejection_history) {
                $prev_rejection_notes = $db->escapeString($rejection_history['approval_notes']);
                $prev_rejector_name = $db->escapeString($rejection_history['rejector_name']);

                $db->query("UPDATE appointments SET
                           last_rejection_notes = '$prev_rejection_notes',
                           last_rejection_by_name = '$prev_rejector_name',
                           last_rejection_date = '{$rejection_history['approval_date']}'
                           WHERE id = ?", [$id]);
            }

            if ($admin_action == 'send_to_user') {
                // Get appointment details to determine which KTT rejected
                $appointment = $db->query("
                    SELECT last_rejected_by_ktt, rejected_by_ktt_user_id,
                           ktt_msm_status, ktt_ttn_status, employee_id
                    FROM appointments WHERE deleted_at IS NULL AND id = ?
                ", [$id])->fetch_assoc();

                // Set flags for which KTT needs to review after resubmit
                // Only the KTT that rejected will review again after resubmit
                $requires_ktt_msm = ($appointment['last_rejected_by_ktt'] == 'msm') ? 1 : 0;
                $requires_ktt_ttn = ($appointment['last_rejected_by_ktt'] == 'ttn') ? 1 : 0;

                // Send back to user to fix data
                $update_sql = "UPDATE appointments SET
                              status = 'rejected',
                              admin_approved_by = $current_admin_id,
                              admin_approved_date = NOW(),
                              admin_approval_action = 'send_to_user',
                              admin_approval_notes = '$admin_notes',
                              requires_ktt_msm_review = $requires_ktt_msm,
                              requires_ktt_ttn_review = $requires_ktt_ttn,
                              resubmit_count = COALESCE(resubmit_count, 0) + 1
                              WHERE id = $id AND status = 'rejected_by_ktt'";

                if ($db->query($update_sql)) {
                    // Log to Workflow History
                    try {
                        require_once dirname(__DIR__, 2) . '/app/Services/AuditService.php';
                        $audit = new AuditService();
                        $employee_id = $db->query("SELECT employee_id FROM appointments WHERE id = ?", [$id])->fetch_assoc()['employee_id'] ?? null;
                        $audit->log($employee_id, $id, 'Return to User', 'rejected_by_ktt', 'rejected', $admin_notes);
                    } catch (Exception $e) {
                        error_log("Audit error: " . $e->getMessage());
                    }

                    // Update employee verification status
                    $appointment = $db->query("SELECT employee_id FROM appointments WHERE deleted_at IS NULL AND id = ?", [$id])->fetch_assoc();
                    if ($appointment) {
                        $ktt_rejection = $db->query("SELECT approval_notes FROM ktt_approvals WHERE appointment_id = ? AND action = 'reject' ORDER BY approval_date DESC LIMIT 1", [$id])->fetch_assoc();
                        $rejection_notes = isset($ktt_rejection['approval_notes']) ? $ktt_rejection['approval_notes'] : '';

                        $db->query("UPDATE employees SET
                                   verification_status = 'rejected',
                                   verification_notes = CONCAT('Rejection from KTT: ', '$rejection_notes', '\n\nAdmin Notes: ', '$admin_notes'),
                                   verified_by = NULL,
                                   verified_date = NULL
                                   WHERE id = {$appointment['employee_id']}");
                    }

                    // Delete rejection records from ktt_approvals (rejection history already saved above)
                    $db->query("DELETE FROM ktt_approvals WHERE appointment_id = ? AND action = 'reject'", [$id]);

                    // Notify user/dept about the rejection
                    try {
                        $notifService = new NotificationService();
                        $notifService->notifyAdminFinalRejectionToUserDept($id, $admin_notes);
                    } catch (Exception $e) {
                        error_log("Notification error (admin send_to_user): " . $e->getMessage());
                    }

                    $message = stela_t('letter-returned-user-correction');
                } else {
                    $error = stela_t('failed-process-decision');
                }

            } elseif ($admin_action == 'send_to_ktt') {
                // Get appointment details to determine which KTT rejected
                $appointment = $db->query("
                    SELECT last_rejected_by_ktt, rejected_by_ktt_user_id,
                           ktt_msm_status, ktt_ttn_status, employee_id
                    FROM appointments WHERE deleted_at IS NULL AND id = ?
                ", [$id])->fetch_assoc();

                // Collect all rejected KTTs (can be one or both)
                $rejected_ktts = [];

                // First check last_rejected_by_ktt field
                if (!empty($appointment['last_rejected_by_ktt'])) {
                    $rejected_ktts[] = $appointment['last_rejected_by_ktt'];
                }

                // Also check from ktt statuses (fallback and for multiple rejections)
                if ($appointment['ktt_msm_status'] == 'rejected' && !in_array('msm', $rejected_ktts)) {
                    $rejected_ktts[] = 'msm';
                }
                if ($appointment['ktt_ttn_status'] == 'rejected' && !in_array('ttn', $rejected_ktts)) {
                    $rejected_ktts[] = 'ttn';
                }

                // Validate: Must have at least one rejection
                if (empty($rejected_ktts)) {
                    $error = stela_t('cannot-send-ktt-no-rejection');
                }

                if (empty($error)) {
                    // Only reset the KTT(s) who rejected - keep approved KTT's decision
                    if (in_array('msm', $rejected_ktts)) {
                        $db->query("UPDATE appointments SET
                                   ktt_msm_status = 'pending',
                                   ktt1_approved_by = NULL,
                                   ktt1_approved_date = NULL
                                   WHERE id = ?", [$id]);
                        // Delete only MSM KTT's old approval record
                        $db->query("DELETE FROM ktt_approvals WHERE appointment_id = ? AND ktt_user_id = (SELECT id FROM users WHERE role = 'ktt' AND id = 7 LIMIT 1)", [$id]);
                    }
                    if (in_array('ttn', $rejected_ktts)) {
                        $db->query("UPDATE appointments SET
                                   ktt_ttn_status = 'pending',
                                   ktt2_approved_by = NULL,
                                   ktt2_approved_date = NULL
                                   WHERE id = ?", [$id]);
                        // Delete only TTN KTT's old approval record
                        $db->query("DELETE FROM ktt_approvals WHERE appointment_id = ? AND ktt_user_id = (SELECT id FROM users WHERE role = 'ktt' AND id = 8 LIMIT 1)", [$id]);
                    }

                    // Set requires flags only for rejected KTT(s)
                    $requires_ktt_msm = in_array('msm', $rejected_ktts) ? 1 : 0;
                    $requires_ktt_ttn = in_array('ttn', $rejected_ktts) ? 1 : 0;

                    // Send back to KTT with requires flags
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
                                  WHERE id = $id";

                    if ($db->query($update_sql)) {
                        // Log to Workflow History
                        try {
                            require_once dirname(__DIR__, 2) . '/app/Services/AuditService.php';
                            $audit = new AuditService();
                            $employee_id = $db->query("SELECT employee_id FROM appointments WHERE id = ?", [$id])->fetch_assoc()['employee_id'] ?? null;
                            $audit->log($employee_id, $id, 'Return to KTT', 'rejected_by_ktt', 'pending', $admin_notes);
                        } catch (Exception $e) {
                            error_log("Audit error: " . $e->getMessage());
                        }

                        // Send email notification to KTT
                        try {
                            // Included via bootstrap/app.php
                            $notifService = new NotificationService();
                            $notifService->notifyKttForApproval($id, $requires_ktt_msm == 1, $requires_ktt_ttn == 1);
                        } catch (Exception $e) {
                            error_log("Notification error (admin review send to KTT): " . $e->getMessage());
                        }

                        $ktt_names = [];
                        if (in_array('msm', $rejected_ktts)) $ktt_names[] = 'KTT MSM';
                        if (in_array('ttn', $rejected_ktts)) $ktt_names[] = 'KTT TTN';
                        $ktt_list = implode(' and ', $ktt_names);

                        $message = stela_t('letter-sent-back-ktt-rereview', ['ktt_list' => $ktt_list]);
                    } else {
                        $error = stela_t('failed-process-decision');
                    }
                }
            }
            }
        } elseif ($_POST['action'] == 'add') {
            $employee_id = intval($_POST['employee_id']);
            $appointment_date = $db->escapeString($_POST['appointment_date']);
            $effective_date = $db->escapeString($_POST['effective_date']);
            $expiry_date = $db->escapeString($_POST['expiry_date']);
            $notes = $db->escapeString($_POST['notes']);
            $created_by = $_SESSION['user_id'];
            
            // Get employee data
            $emp_result = $db->query("SELECT competency_type, ruang_lingkup FROM employees WHERE deleted_at IS NULL AND id = ?", [$employee_id]);
            $emp_row = $emp_result->fetch_assoc();
            
            // Generate appointment number based on employee competency type
            $appointment_number = generateAppointmentNumber($db, $employee_id, $appointment_date);
            
            $sql = "INSERT INTO appointments (appointment_number, employee_id, appointment_date, 
                    effective_date, expiry_date, notes, created_by, status) 
                    VALUES ('$appointment_number', $employee_id, '$appointment_date', 
                    '$effective_date', '$expiry_date', '$notes', $created_by, 'draft')";
            
            if ($db->query($sql)) {
                $message = stela_t('appointment-created-with-number', ['appointment_number' => $appointment_number]);
            } else {
                $error = stela_t('failed-create-appointment');
            }
        } elseif ($_POST['action'] == 'submit') {
            $id = intval($_POST['id']);

            // Get appointment details to check if it's a resubmit case
            $appt = $db->query("
                SELECT requires_ktt_msm_review, requires_ktt_ttn_review,
                       ktt_msm_status, ktt_ttn_status, status
                FROM appointments
                WHERE id = ?
            ", [$id])->fetch_assoc();

            if ($appt['status'] !== 'draft' && $appt['status'] !== 'rejected') {
                $error = "Appointment has already been submitted.";
            }

            if (empty($error)) {
            // Debug logging
            error_log("SUBMIT DEBUG - Appointment ID: $id");
            error_log("SUBMIT DEBUG - Current Status: {$appt['status']}");
            error_log("SUBMIT DEBUG - Requires MSM: {$appt['requires_ktt_msm_review']}, Requires TTN: {$appt['requires_ktt_ttn_review']}");
            error_log("SUBMIT DEBUG - MSM Status: {$appt['ktt_msm_status']}, TTN Status: {$appt['ktt_ttn_status']}");

            // Check if this is a resubmit (has requires_ktt flags)
            $is_resubmit = ($appt['requires_ktt_msm_review'] == 1 || $appt['requires_ktt_ttn_review'] == 1);

            error_log("SUBMIT DEBUG - Is Resubmit: " . ($is_resubmit ? 'YES' : 'NO'));

            if ($is_resubmit) {
                // For resubmit: Only reset KTT statuses that need re-review
                $update_parts = ["status = 'pending'"];

                if ($appt['requires_ktt_msm_review'] == 1) {
                    $update_parts[] = "ktt_msm_status = 'pending'";
                    $update_parts[] = "ktt1_approved_by = NULL";
                    $update_parts[] = "ktt1_approved_date = NULL";
                }
                if ($appt['requires_ktt_ttn_review'] == 1) {
                    $update_parts[] = "ktt_ttn_status = 'pending'";
                    $update_parts[] = "ktt2_approved_by = NULL";
                    $update_parts[] = "ktt2_approved_date = NULL";
                }

                $sql = "UPDATE appointments SET " . implode(', ', $update_parts) . " WHERE id = $id";
                error_log("SUBMIT DEBUG - Resubmit SQL: $sql");
            } else {
                // For new appointment: Set status to pending and enable both KTTs
                $sql = "UPDATE appointments SET
                        status = 'pending',
                        requires_ktt_msm_review = 1,
                        requires_ktt_ttn_review = 1,
                        ktt_msm_status = 'pending',
                        ktt_ttn_status = 'pending'
                        WHERE id = $id";
                error_log("SUBMIT DEBUG - New Appointment SQL: $sql");
            }

            if ($db->query($sql)) {
                // Delete old KTT approval records only for KTTs that need to re-review
                if ($is_resubmit) {
                    if ($appt['requires_ktt_msm_review'] == 1) {
                        $db->query("DELETE FROM ktt_approvals WHERE appointment_id = ? AND ktt_user_id = 7", [$id]);
                    }
                    if ($appt['requires_ktt_ttn_review'] == 1) {
                        $db->query("DELETE FROM ktt_approvals WHERE appointment_id = ? AND ktt_user_id = 8", [$id]);
                    }
                } else {
                    // New appointment - clear all
                    $db->query("DELETE FROM ktt_approvals WHERE appointment_id = ?", [$id]);
                }

                // Verify the update
                $verify = $db->query("SELECT status, ktt_msm_status, ktt_ttn_status FROM appointments WHERE deleted_at IS NULL AND id = ?", [$id])->fetch_assoc();
                error_log("SUBMIT DEBUG - After Update - Status: {$verify['status']}, MSM Status: {$verify['ktt_msm_status']}, TTN Status: {$verify['ktt_ttn_status']}");

                // Log to Workflow History
                try {
                    require_once dirname(__DIR__, 2) . '/app/Services/AuditService.php';
                    $audit = new AuditService();
                    $employee_id = $db->query("SELECT employee_id FROM appointments WHERE id = ?", [$id])->fetch_assoc()['employee_id'] ?? null;
                    $status_before = $is_resubmit ? 'rejected_by_ktt' : 'draft';
                    $audit->log($employee_id, $id, 'Ajukan ke KTT', $status_before, 'pending', 'Admin submitted appointment to KTT');
                } catch (Exception $e) {
                    error_log("Audit error: " . $e->getMessage());
                }

                // Sync appointment to Elasticsearch
                try {
                    if (class_exists('ElasticsearchService')) {
                        $es = ElasticsearchService::getInstance();
                        if ($es->isAvailable()) {
                            $apptQuery = $db->query("SELECT a.*, e.employee_code, e.full_name as employee_name, e.position, e.department, e.contractor_company, p.position_name as competency_name 
                                                     FROM appointments a 
                                                     LEFT JOIN employees e ON a.employee_id = e.id
                                                     LEFT JOIN positions p ON a.position_id = p.id
                                                     WHERE a.id = ?", [$id]);
                            if ($apptQuery && $apptRow = $apptQuery->fetch_assoc()) {
                                $es->indexAppointment($apptRow);
                            }
                        }
                    }
                } catch (Exception $e) {
                    error_log("Elasticsearch appointment sync error: " . $e->getMessage());
                }

                // Send email notification to KTT
                try {
                    // Included via bootstrap/app.php
                    $notifService = new NotificationService();
                    // Notify only the KTTs that need to review
                    $notify_msm = !$is_resubmit || $appt['requires_ktt_msm_review'] == 1;
                    $notify_ttn = !$is_resubmit || $appt['requires_ktt_ttn_review'] == 1;
                    $notifService->notifyKttForApproval($id, $notify_msm, $notify_ttn);
                } catch (Exception $e) {
                    error_log("Notification error (submit to KTT): " . $e->getMessage());
                }

                $message = stela_t('appointment-submitted-ktt-approval');
                ob_end_clean();
                header("Location: appointments.php?success=submit");
                exit();
            } else {
                error_log("SUBMIT DEBUG - Update FAILED: " . $db->getConnection()->error);
                $error = stela_t('failed-submit-appointment');
            }
            }
        } elseif ($_POST['action'] == 'update_content') {
            $id = intval($_POST['id']);
            $is_reset_template = isset($_POST['reset_to_template']) && $_POST['reset_to_template'] == '1';
            $raw_letter_content = $_POST['letter_content'] ?? '';

            if ($is_reset_template || trim($raw_letter_content) === '') {
                $sql = "UPDATE appointments SET letter_content = NULL WHERE id = $id AND status = 'draft'";
            } else {
                $letter_content = $db->escapeString($raw_letter_content);
                $sql = "UPDATE appointments SET letter_content = '$letter_content' WHERE id = $id AND status = 'draft'";
            }
            
            if ($db->query($sql)) {
                $message = stela_t('appointment-content-updated');
            } else {
                $error = stela_t('failed-update-appointment-content');
            }
        }
    }
}

// Handle success parameters
if (isset($_GET['success'])) {
    if ($_GET['success'] == 'submit') {
        $message = stela_t('appointment-submitted-ktt-approval');
    }
}

// Handle delete
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    if ($db->query("DELETE FROM appointments WHERE deleted_at IS NULL AND id = ? AND status = 'draft'", [$id])) {
        $message = stela_t('appointment-deleted');
    }
}

// Get status filter from URL
$status_filter = isset($_GET['status']) ? $db->escapeString($_GET['status']) : '';

// Build WHERE clause for status filter
$where_clause = "1=1";
if (!empty($status_filter)) {
    $where_clause = "a.status = '$status_filter'";
}

// Get all appointments
$appointments = $db->query("
    SELECT a.*, 
           e.full_name as employee_name, 
           e.employee_code,
           e.competency_type,
           e.competency_name,
           e.ruang_lingkup,
           u.full_name as created_by_name,
           u2.full_name as approved_by_name,
           ktt1.full_name as ktt1_name,
           ktt2.full_name as ktt2_name,
           CASE 
               WHEN a.status = 'approved' THEN 'success'
               WHEN a.status = 'pending' THEN 'warning'
               WHEN a.status = 'rejected' THEN 'danger'
               WHEN a.status = 'rejected_by_ktt' THEN 'danger'
               WHEN a.status = 'draft' THEN 'secondary'
               ELSE 'info'
           END as status_class
    FROM appointments a
    JOIN employees e ON a.employee_id = e.id
    LEFT JOIN users u ON a.created_by = u.id
    LEFT JOIN users u2 ON a.approved_by = u2.id
    LEFT JOIN users ktt1 ON a.ktt1_approved_by = ktt1.id
    LEFT JOIN users ktt2 ON a.ktt2_approved_by = ktt2.id
    WHERE $where_clause
    ORDER BY a.created_at DESC, a.id DESC
");

// Get employees for form - with competency details
$employees = $db->query("
    SELECT id, employee_code, full_name, competency_type, ruang_lingkup 
    FROM employees 
    WHERE is_active = 1 
    ORDER BY full_name
");

// Get positions for form
$positions = $db->query("SELECT id, position_name, position_type FROM positions WHERE deleted_at IS NULL AND is_active = 1 ORDER BY position_type, position_name");

// AUTO-FIX: Fix appointments status that should be rejected_by_ktt but still pending
$auto_fix_query = "
    UPDATE appointments a
    SET 
        status = 'rejected_by_ktt',
        rejected_by_ktt_user_id = (SELECT ktt_user_id FROM ktt_approvals WHERE appointment_id = a.id AND action = 'reject' LIMIT 1)
    WHERE a.status = 'pending'
    AND (SELECT COUNT(*) FROM ktt_approvals WHERE appointment_id = a.id) >= 2
    AND (SELECT COUNT(*) FROM ktt_approvals WHERE appointment_id = a.id AND action = 'reject') > 0
";
$db->query($auto_fix_query);

// Get appointments rejected by KTT waiting for admin review
$rejected_by_ktt_query = "
    SELECT a.*, e.full_name as employee_name, e.employee_code, e.position,
           e.contractor_company, p.position_name, p.position_type,
           u.full_name as created_by_name,
           ktt_rejection.approval_notes as ktt_rejection_notes,
           ktt_rejection.approval_date as ktt_rejection_date,
           ktt_rejector.full_name as ktt_rejector_name,
           (SELECT COUNT(*) FROM ktt_approvals WHERE appointment_id = a.id AND action = 'reject') as rejection_count
    FROM appointments a
    JOIN employees e ON a.employee_id = e.id
    LEFT JOIN positions p ON a.position_id = p.id
    LEFT JOIN users u ON a.created_by = u.id
    LEFT JOIN ktt_approvals ktt_rejection ON a.id = ktt_rejection.appointment_id AND ktt_rejection.action = 'reject'
    LEFT JOIN users ktt_rejector ON ktt_rejection.ktt_user_id = ktt_rejector.id
    WHERE a.status = 'rejected_by_ktt'
    ORDER BY ktt_rejection.approval_date DESC
";

$rejected_by_ktt = $db->query($rejected_by_ktt_query);

require_once dirname(__DIR__) . '/layouts/header.php';
ob_end_flush();

// Get statistics
$total_appointments = $appointments->num_rows;
$draft_count = $db->query("SELECT COUNT(*) as count FROM appointments WHERE deleted_at IS NULL AND status = 'draft'")->fetch_assoc()['count'];
$pending_count = $db->query("SELECT COUNT(*) as count FROM appointments WHERE deleted_at IS NULL AND status = 'pending'")->fetch_assoc()['count'];
$approved_count = $db->query("SELECT COUNT(*) as count FROM appointments WHERE deleted_at IS NULL AND status = 'approved'")->fetch_assoc()['count'];
$rejected_count = $db->query("SELECT COUNT(*) as count FROM appointments WHERE deleted_at IS NULL AND status IN ('rejected', 'rejected_by_ktt')")->fetch_assoc()['count'];
$rejected_by_ktt_count = $rejected_by_ktt->num_rows;
?>

<div class="appointments-admin-container">
    <!-- Page Header -->
    <div class="page-header-appt-admin">
        <div class="header-left">
            <h2><i class="fas fa-file-contract"></i> <span data-lang="appointment-letter-management">Appointment Letter Management</span></h2>
            <p data-lang="manage-appointment-letters">Manage creation and submission of expertise appointment letters</p>
        </div>
    </div>

    <!-- Tab Navigation -->
    <div class="tabs-navigation">
        <button class="tab-btn active" data-tab="all-appointments">
            <i class="fas fa-list"></i> <span data-lang="all-letters">All Letters</span>
        </button>
    </div>
    
    <?php if (!empty($status_filter)): ?>
    <div class="alert alert-info alert-custom-appt">
        <i class="fas fa-filter"></i>
        <div>
            <strong data-lang="active-filter">Active Filter:</strong>
            <p><span data-lang="displaying-letters-status">Displaying letters with status:</span> <strong>
                <?php
                $status_labels = [
                    'draft' => 'Draft',
                    'pending' => 'Pending',
                    'approved' => 'Accept',
                    'rejected' => 'Reject',
                    'rejected_by_ktt' => 'Reject by KTT'
                ];
                echo $status_labels[$status_filter] ?? $status_filter;
                ?>
            </strong></p>
        </div>
        <a href="appointments.php" class="btn btn-sm btn-secondary" style="margin-left: auto;">
            <i class="fas fa-times"></i> <span data-lang="clear-filter">Clear Filter</span>
        </a>
    </div>
    <?php endif; ?>
    
    <?php if ($message): ?>
    <div class="alert alert-success alert-custom-appt">
        <i class="fas fa-check-circle"></i>
        <div>
            <strong data-lang="success">Success!</strong>
            <p><?php echo htmlspecialchars($message); ?></p>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($error): ?>
    <div class="alert alert-error alert-custom-appt">
        <i class="fas fa-exclamation-circle"></i>
        <div>
            <strong data-lang="error">Error!</strong>
            <p><?php echo htmlspecialchars($error); ?></p>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Statistics Cards -->
    <style>
        .custom-stat-card {
            background: #fff;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            display: flex;
            justify-content: space-between;
            align-items: center;
            border: 1px solid #f0f0f0;
            border-left: 4px solid;
        }
        .custom-stat-card .stat-info { display: flex; flex-direction: column; }
        .custom-stat-card .stat-title { font-size: 14px; color: #6c757d; font-weight: 600; margin-bottom: 8px; }
        .custom-stat-card .stat-number { font-size: 32px; font-weight: bold; margin-bottom: 5px; line-height: 1; }
        .custom-stat-card .stat-desc { font-size: 12px; color: #adb5bd; font-weight: 500; }
        .custom-stat-card .stat-icon { width: 55px; height: 55px; border-radius: 12px; display: flex; justify-content: center; align-items: center; font-size: 28px; }
        
        .stat-variant-blue { border-left-color: #1A73E8; } .stat-variant-blue .stat-number { color: #1A73E8; } .stat-variant-blue .stat-icon { background: #E8F0FE; color: #1A73E8; }
        .stat-variant-green { border-left-color: #1E8E3E; } .stat-variant-green .stat-number { color: #1E8E3E; } .stat-variant-green .stat-icon { background: #E6F4EA; color: #1E8E3E; }
        .stat-variant-orange { border-left-color: #F57C00; } .stat-variant-orange .stat-number { color: #F57C00; } .stat-variant-orange .stat-icon { background: #FFF3E0; color: #F57C00; }
        .stat-variant-red { border-left-color: #D93025; } .stat-variant-red .stat-number { color: #D93025; } .stat-variant-red .stat-icon { background: #FCE8E6; color: #D93025; }
        .stat-variant-grey { border-left-color: #6B7280; } .stat-variant-grey .stat-number { color: #6B7280; } .stat-variant-grey .stat-icon { background: #F3F4F6; color: #6B7280; }
        .stat-variant-dark { border-left-color: #37474F; } .stat-variant-dark .stat-number { color: #37474F; } .stat-variant-dark .stat-icon { background: #ECEFF1; color: #37474F; }
        
        .action-buttons-appt-admin {
            display: flex;
            gap: 5px;
            align-items: center;
            justify-content: flex-start;
            flex-wrap: nowrap;
        }
    </style>
    <div class="stats-grid-appt-admin" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 20px; margin-bottom: 30px;">
        <div class="custom-stat-card stat-variant-blue">
            <div class="stat-info">
                <div class="stat-title" data-lang="all-employees">All Employees</div>
                <div class="stat-number"><?php echo $total_appointments; ?></div>
                <div class="stat-desc" data-lang="total-registered">Total registered workforce</div>
            </div>
            <div class="stat-icon"><i class="fas fa-users"></i></div>
        </div>
        
        <div class="custom-stat-card stat-variant-grey">
            <div class="stat-info">
                <div class="stat-title" data-lang="draft">Draft</div>
                <div class="stat-number"><?php echo $draft_count; ?></div>
                <div class="stat-desc">Not yet submitted</div>
            </div>
            <div class="stat-icon"><i class="fas fa-file-pen"></i></div>
        </div>
        
        <div class="custom-stat-card stat-variant-orange">
            <div class="stat-info">
                <div class="stat-title" data-lang="pending">Pending</div>
                <div class="stat-number"><?php echo $pending_count; ?></div>
                <div class="stat-desc" data-lang="awaiting-review">Awaiting review</div>
            </div>
            <div class="stat-icon"><i class="fas fa-hourglass-half"></i></div>
        </div>
        
        <div class="custom-stat-card stat-variant-green">
            <div class="stat-info">
                <div class="stat-title" data-lang="accept">Accept</div>
                <div class="stat-number"><?php echo $approved_count; ?></div>
                <div class="stat-desc" data-lang="verified-active">Verified & active</div>
            </div>
            <div class="stat-icon"><i class="fas fa-user-check"></i></div>
        </div>
        
        <div class="custom-stat-card stat-variant-red">
            <div class="stat-info">
                <div class="stat-title" data-lang="reject">Reject</div>
                <div class="stat-number"><?php echo $rejected_count; ?></div>
                <div class="stat-desc" data-lang="needs-correction">Needs correction</div>
            </div>
            <div class="stat-icon"><i class="fas fa-user-times"></i></div>
        </div>
        
        <?php if ($rejected_by_ktt_count > 0): ?>
        <div class="custom-stat-card stat-variant-dark">
            <div class="stat-info">
                <div class="stat-title" data-lang="needs-review">Needs Review</div>
                <div class="stat-number"><?php echo $rejected_by_ktt_count; ?></div>
                <div class="stat-desc">Rejected by KTT</div>
            </div>
            <div class="stat-icon"><i class="fas fa-ban"></i></div>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- Bonsai.io Pagination Search Section -->
    

    <div class="card-appt-admin" style="margin-bottom: 16px; padding: 16px 20px; background: #ffffff; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); border: 1px solid #e9ecef;">
        <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
            <!-- Search Input -->
            <div style="flex: 1; min-width: 220px; position: relative;">
                <i class="fas fa-search" style="position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: #6c757d; font-size: 14px; z-index: 2;"></i>
                <input type="text" id="esSearchInput" autocomplete="off"
                       placeholder="Cari No. Surat, Nama Karyawan, Perusahaan..."
                       style="width:100%; padding-left: 40px; padding-right: 38px; height: 42px; border-radius: 8px; border: 1px solid #ced4da; font-size: 14px;">
                <button type="button" id="esClearBtn" title="Bersihkan"
                        style="display:none; position: absolute; right: 10px; top: 50%; transform: translateY(-50%); background: none; border: none; color: #a0aec0; cursor: pointer; font-size: 17px; padding: 0; z-index: 2;">
                    <i class="fas fa-times-circle"></i>
                </button>
            </div>
            <!-- Status Filter -->
            <select id="filterApptStatus" style="height:42px; border-radius:8px; border:1px solid #ced4da; padding: 0 10px; font-size:13px; min-width:140px;">
                <option value="">Semua Status</option>
                <option value="draft">Draft</option>
                <option value="pending">Pending</option>
                <option value="approved">Approved</option>
                <option value="rejected">Rejected</option>
                <option value="rejected_by_ktt">Rejected by KTT</option>
            </select>
            <!-- Company Filter -->
            <select id="filterApptCompany" style="height:42px; border-radius:8px; border:1px solid #ced4da; padding: 0 10px; font-size:13px; min-width:160px;">
                <option value="">Semua Perusahaan</option>
                <?php
                $apptCompanies = $db->query("SELECT DISTINCT e.contractor_company FROM appointments a JOIN employees e ON a.employee_id = e.id ORDER BY e.contractor_company");
                if ($apptCompanies) { while ($ac = $apptCompanies->fetch_assoc()): ?>
                <option value="<?php echo htmlspecialchars($ac['contractor_company']); ?>"><?php echo htmlspecialchars($ac['contractor_company']); ?></option>
                <?php endwhile; } ?>
            </select>
            <!-- Page Size -->
            <select id="apptBonsaiPageLimit" style="height:42px; border-radius:8px; border:1px solid #ced4da; padding: 0 10px; font-size:13px; min-width:90px;">
                <option value="10">10 / hal</option>
                <option value="25">25 / hal</option>
                <option value="50">50 / hal</option>
                <option value="100">100 / hal</option>
            </select>
        </div>
        <!-- Result info -->
        <div id="apptBonsaiInfoContainer" style="margin-top: 10px; font-size: 13px; color: #6c757d;"></div>
    </div>

    <!-- Tab Content: All Appointments -->
    <div class="tab-content active" id="tab-all-appointments">
    <div class="card-appt-admin">
        <div class="card-header-appt-admin">
            <h3><i class="fas fa-list"></i> <span data-lang="appointment-letter-list">Appointment Letter List</span></h3>
        </div>
        <div class="card-body-appt-admin">
            <div class="table-responsive">
                <table class="table-appt-admin" id="appointmentsTable">
                    <thead>
                        <tr>
                            <th class="col-number" data-lang="letter-number">Letter Number</th>
                            <th class="col-employee" data-lang="employee">Employee</th>
                            <th class="col-status" data-lang="status">Status</th>
                            <th class="col-action" data-lang="actions">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="apptTbody">
                        <tr><td colspan="4" style="text-align:center;padding:32px;color:#a0aec0;"><i class="fas fa-circle-notch fa-spin"></i> Memuat data...</td></tr>
                    </tbody>
                </table>
            </div>
            <!-- Pagination controls -->
            <div style="display:flex; justify-content:space-between; align-items:center; margin-top:14px; flex-wrap:wrap; gap:8px;">
                <div id="apptBonsaiInfoContainerTable" style="font-size:13px; color:#6c757d;"></div>
                <div id="apptBonsaiPaginationContainer"></div>
            </div>

            <script src="../../assets/js/bonsai_pagination.js?v=<?php echo time(); ?>"></script>
            <script>
            (function() {
                const csrfToken = '<?php echo $_SESSION['csrf_token'] ?? ''; ?>';

                function getStatusBadge(status) {
                    const map = {
                        'approved': '<span class="badge-appt-admin badge-success"><i class="fas fa-check-circle"></i> Approved</span>',
                        'pending':  '<span class="badge-appt-admin badge-warning"><i class="fas fa-clock"></i> Pending</span>',
                        'rejected_by_ktt': '<span class="badge-appt-admin badge-danger"><i class="fas fa-ban"></i> Rejected by KTT</span>',
                        'rejected': '<span class="badge-appt-admin badge-danger"><i class="fas fa-times-circle"></i> Rejected</span>',
                        'draft':    '<span class="badge-appt-admin badge-secondary"><i class="fas fa-file"></i> Draft</span>'
                    };
                    return map[status] || `<span class="badge-appt-admin badge-secondary">${status}</span>`;
                }

                function getActionButtons(item) {
                    const id = item.id;
                    const status = item.status;
                    const empName = (item.employee_name||'').replace(/'/g,"\\'");
                    const apptNum = (item.appointment_number||'').replace(/'/g,"\\'");
                    let btns = '';
                    if (status !== 'rejected_by_ktt') {
                        btns += `<button class="btn-action-appt view-btn" onclick="showAppointmentDetail(${id})" title="View Details"><i class="fas fa-eye"></i> Detail</button>`;
                    }
                    if (status === 'draft') {
                        btns += `<a href="../../exports/print_appointment.php?id=${id}" target="_blank" class="btn-action-appt edit-btn" title="Modify Letter"><i class="fas fa-edit"></i></a>`;
                        btns += `<form method="POST" style="display:inline;">
                            <input type="hidden" name="csrf_token" value="${csrfToken}">
                            <input type="hidden" name="action" value="submit">
                            <input type="hidden" name="id" value="${id}">
                            <button type="submit" class="btn-action-appt ajukan-btn" onclick="return confirm('Submit this appointment letter to KTT for approval?')" title="Submit to KTT"><i class="fas fa-paper-plane"></i></button>
                        </form>`;
                    } else if (status === 'approved') {
                        btns += `<a href="../../exports/print_appointment_pdf.php?id=${id}" target="_blank" class="btn-action-appt cetak-btn" title="Print"><i class="fas fa-print"></i> Print</a>`;
                    } else if (status === 'rejected_by_ktt') {
                        btns += `<button type="button" class="btn-action-appt review-detail-btn" onclick="showRejectionDetailModal(${id}, '${empName}', '${apptNum}', 'See details')" title="Review"><i class="fas fa-clipboard-check"></i> Review</button>`;
                    }
                    return btns;
                }

                window.apptPagination = new BonsaiPagination({
                    apiUrl: '../../api/search_elasticsearch.php',
                    target: 'appointments',
                    tableSelector: '#appointmentsTable',
                    tbodySelector: '#apptTbody',
                    searchInputSelector: '#esSearchInput',
                    clearBtnSelector: '#esClearBtn',
                    paginationContainerSelector: '#apptBonsaiPaginationContainer',
                    infoContainerSelector: '#apptBonsaiInfoContainer',
                    limitSelector: '#apptBonsaiPageLimit',
                    filterSelectors: {
                        status: '#filterApptStatus',
                        company: '#filterApptCompany'
                    },
                    defaultLimit: 10,
                    renderRow: function(item, index, rowNum) {
                        const apptDate = item.created_at ? item.created_at.substring(0,10) : '';
                        return `<tr class="appt-admin-row" data-id="${item.id}">
                            <td class="col-number">
                                <strong>${item.appointment_number || '-'}</strong>
                                <div style="font-size:11px;color:#999;margin-top:3px;">${apptDate}</div>
                            </td>
                            <td class="col-employee">
                                <div class="employee-info">
                                    <strong>${item.employee_name || '-'}</strong>
                                </div>
                            </td>
                            <td class="col-status">${getStatusBadge(item.status)}</td>
                            <td class="col-action">
                                <div class="action-buttons-appt-admin">${getActionButtons(item)}</div>
                            </td>
                        </tr>`;
                    }
                });

                // Sync info to table-level container
                const origInfo = window.apptPagination.renderInfo.bind(window.apptPagination);
                window.apptPagination.renderInfo = function() {
                    origInfo();
                    const src = document.querySelector('#apptBonsaiInfoContainer');
                    const dest = document.querySelector('#apptBonsaiInfoContainerTable');
                    if (src && dest) dest.innerHTML = src.innerHTML;
                };
            })();
            </script>
        </div>
    </div>
    </div> <!-- End Tab Content: All Appointments -->
    
    <!-- Edit Letter Content Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content modal-large-appt">
            <div class="modal-header modal-header-appt">
                <h3><i class="fas fa-edit"></i> Modify Appointment Letter Content</h3>
                <span class="close" onclick="closeModal('editModal')">&times;</span>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
                <input type="hidden" name="action" value="update_content">
                <input type="hidden" name="id" id="editAppointmentId">
                <div class="modal-body-appt">
                    <div class="form-group-appt">
                        <label>Letter Number</label>
                        <input type="text" id="editAppointmentNumber" class="form-control-appt" readonly>
                    </div>
                    
                    <div class="form-group-appt">
                        <label>Employee Name</label>
                        <input type="text" id="editEmployeeName" class="form-control-appt" readonly>
                    </div>
                    
                    <div class="form-group-appt">
                        <label>Kompetensi</label>
                        <input type="text" id="editCompetencyName" class="form-control-appt" readonly>
                    </div>
                    
                    <div class="form-group-appt">
                        <label>Letter Content <span class="text-danger">*</span></label>
                        <textarea name="letter_content" id="editLetterContent" 
                                  class="form-control-appt" rows="12" 
                                  placeholder="Enter appointment letter content...&#10;&#10;Example:&#10;Based on the Decision of the Minister of ESDM of the Republic of Indonesia Number ... regarding ...&#10;&#10;We hereby appoint:&#10;Name: ...&#10;Position: ...&#10;Competency: ...&#10;&#10;To carry out duties as ... in the area ...&#10;&#10;This appointment letter is made to be used accordingly." data-lang-placeholder="appointment-letter-content-placeholder"
                                  required></textarea>
                        <small class="text-muted">Fill in the appointment letter content completely. This content will be displayed in the official letter.</small>
                    </div>
                    
                    <div class="alert alert-info-appt">
                        <i class="fas fa-info-circle"></i> 
                        <strong>Petunjuk Pengisian:</strong><br>
                        Fill in the letter content completely and in detail<br>
                        Ensure all required information is included<br>
                        Use formal and professional language<br>
                        This content will be displayed in the official appointment letter<br>
                        After filling, you can submit the letter to KTT for approval
                    </div>
                </div>
                <div class="modal-footer-appt">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('editModal')">Cancel</button>
                    <button type="submit" class="btn btn-warning" name="reset_to_template" value="1" formnovalidate onclick="return confirm('Reset konten ke template otomatis sesuai kompetensi?');"><i class="fas fa-rotate-left"></i> Reset Template</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Changes</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Add Modal -->
    <div id="addModal" class="modal">
        <div class="modal-content modal-large-appt">
            <div class="modal-header modal-header-appt">
                <h3><i class="fas fa-plus-circle"></i> Create New Appointment Letter</h3>
                <span class="close" onclick="closeModal('addModal')">&times;</span>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
                <input type="hidden" name="action" value="add">
                <div class="modal-body-appt">
                    <div class="form-group-appt">
                        <label>Employee <span class="text-danger">*</span></label>
                        <select name="employee_id" id="employeeSelect" class="form-control-appt" 
                                onchange="updateEmployeeDetails(); updatePreviewNumber()" required>
                            <option value="">-- Select Employee --</option>
                            <?php 
                            $employees->data_seek(0);
                            while ($emp = $employees->fetch_assoc()): 
                                $type_labels = [
                                    'pengawas_operasional' => 'Pengawas Operasional',
                                    'pengawas_teknis' => 'Pengawas Teknis',
                                    'tenaga_teknis' => 'Tenaga Teknis'
                                ];
                                $type_label = $type_labels[$emp['competency_type']] ?? $emp['competency_type'];
                            ?>
                            <option value="<?php echo $emp['id']; ?>" 
                                    data-competency-type="<?php echo $emp['competency_type']; ?>"
                                    data-ruang-lingkup="<?php echo htmlspecialchars($emp['ruang_lingkup']); ?>">
                                <?php echo htmlspecialchars($emp['employee_code'] . ' - ' . $emp['full_name'] . ' (' . $type_label . ')'); ?>
                            </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <div class="form-row-appt">
                        <div class="form-group-appt">
                            <label>Competency Type</label>
                            <input type="text" id="competencyTypeDisplay" class="form-control-appt" readonly>
                        </div>
                        
                        <div class="form-group-appt">
                            <label>Scope of Work</label>
                            <input type="text" id="ruangLingkupDisplay" class="form-control-appt" readonly>
                        </div>
                    </div>
                    
                    <div class="form-row-appt">
                        <div class="form-group-appt">
                            <label>Appointment Date <span class="text-danger">*</span></label>
                            <input type="date" id="appointmentDateInput" name="appointment_date" 
                                   class="form-control-appt" value="<?php echo date('Y-m-d'); ?>" 
                                   onchange="updatePreviewNumber()" required>
                            <small class="text-muted">Letter issue date</small>
                        </div>
                        
                        <div class="form-group-appt">
                            <label>Valid From Date <span class="text-danger">*</span></label>
                            <input type="date" name="effective_date" class="form-control-appt" 
                                   value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                    </div>
                    
                    <div class="form-group-appt preview-number-section">
                        <label>Registration Number (Preview)</label>
                        <div class="preview-number-box">
                            <span id="numberPreview" class="number-preview-text">---/--/---/--/----</span>
                            <small class="text-muted">Format: [SEQUENCE NUMBER]/[COMPETENCY TYPE]/[WORK SCOPE]/[MONTH]/[YEAR]</small>
                            <br>
                            <small class="text-muted preview-example" id="previewExample">
                                Example: 001/PO/MSM/01/2026
                            </small>
                        </div>
                    </div>
                    
                    <div class="form-group-appt">
                        <label>Expiry Date (Optional)</label>
                        <input type="date" name="expiry_date" class="form-control-appt">
                        <small class="text-muted">Leave blank if no time limit</small>
                    </div>
                    
                    <div class="form-group-appt">
                        <label>Notes</label>
                        <textarea name="notes" class="form-control-appt" rows="3" 
                                  placeholder="Additional notes..." data-lang-placeholder="additional-notes-placeholder"></textarea>
                    </div>
                    
                    <div class="alert alert-info-appt">
                        <i class="fas fa-info-circle"></i> 
                        <strong>Registration Number Format:</strong><br>
                        [SEQUENCE NUMBER]/[COMPETENCY TYPE]/[WORK SCOPE]/[MONTH]/[YEAR]<br>
                        <br>
                        <strong>Details:</strong><br>
                        SEQUENCE NUMBER: Sequential number based on competency type, work scope, month, and year (001, 002, etc)<br>
                        COMPETENCY TYPE: PO (Operational Supervisor), PT (Technical Supervisor), TT (Technical Personnel)<br>
                        WORK SCOPE: MSM (Meares Soputan Mining), TTN (Tambang Tondano), MSM/TTN (Both)<br>
                        MONTH: Letter issue month (01-12)<br>
                        YEAR: Letter issue year (4 digits)<br>
                        <br>
                        <strong>Examples:</strong><br>
                        002/PO/TTN/01/2026 = 2nd letter Operational Supervisor at TTN, January 2026<br>
                        005/PT/MSM/02/2026 = 5th letter Technical Supervisor at MSM, February 2026<br>
                        003/TT/MSM/03/2026 = 3rd letter Technical Personnel at MSM, March 2026
                    </div>
                </div>
                <div class="modal-footer-appt">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('addModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-plus-circle"></i> Create Letter</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Appointment Detail Modal -->
    <div id="appointmentDetailModal" class="modal">
        <div class="modal-content modal-large-appt">
            <div class="modal-header modal-header-appt">
                <h3><i class="fas fa-file-contract"></i> <span data-lang="appointment-letter-details">Appointment Letter Details</span></h3>
                <span class="close" onclick="closeModal('appointmentDetailModal')">&times;</span>
            </div>
            <div class="modal-body-appt">
                <!-- Letter Information -->
                <div class="detail-section">
                    <h4><i class="fas fa-file-alt"></i> <span data-lang="letter-information">Letter Information</span></h4>
                    <div class="detail-grid">
                        <div class="detail-item">
                            <label><span data-lang="letter-number">Letter Number</span>:</label>
                            <span id="detailApptNumber">-</span>
                        </div>
                        <div class="detail-item">
                            <label><span data-lang="appointment-date">Appointment Date</span>:</label>
                            <span id="detailApptDate">-</span>
                        </div>
                        <div class="detail-item">
                            <label><span data-lang="effective-date">Effective Date</span>:</label>
                            <span id="detailEffectiveDate">-</span>
                        </div>
                        <div class="detail-item">
                            <label><span data-lang="expiry-date">Expiry Date</span>:</label>
                            <span id="detailExpiryDate">-</span>
                        </div>
                        <div class="detail-item">
                            <label><span data-lang="status">Status</span>:</label>
                            <span id="detailStatus">-</span>
                        </div>
                        <div class="detail-item">
                            <label><span data-lang="created-at">Created At</span>:</label>
                            <span id="detailCreatedAt">-</span>
                        </div>
                    </div>
                </div>

                <!-- Employee Information -->
                <div class="detail-section">
                    <h4><i class="fas fa-user"></i> <span data-lang="employee-information">Employee Information</span></h4>
                    <div class="detail-grid">
                        <div class="detail-item">
                            <label><span data-lang="full-name">Full Name</span>:</label>
                            <span id="detailEmpName">-</span>
                        </div>
                        <div class="detail-item">
                            <label><span data-lang="employee-code">Employee Code</span>:</label>
                            <span id="detailEmpCode">-</span>
                        </div>
                        <div class="detail-item">
                            <label><span data-lang="position">Position</span>:</label>
                            <span id="detailEmpPosition">-</span>
                        </div>
                        <div class="detail-item">
                            <label><span data-lang="company-contractor">Company / Contractor</span>:</label>
                            <span id="detailEmpCompany">-</span>
                        </div>
                        <div class="detail-item">
                            <label><span data-lang="competency-type">Competency Type</span>:</label>
                            <span id="detailEmpCompetencyType">-</span>
                        </div>
                        <div class="detail-item">
                            <label><span data-lang="competency">Competency</span>:</label>
                            <span id="detailEmpCompetency">-</span>
                        </div>
                        <div class="detail-item">
                            <label><span data-lang="work-scope">Work Scope</span>:</label>
                            <span id="detailEmpScope">-</span>
                        </div>
                    </div>
                </div>

                <!-- Certifications -->
                <div class="detail-section" id="detailCertSection" style="display: none;">
                    <h4><i class="fas fa-certificate"></i> <span data-lang="certifications">Certifications</span></h4>
                    <div id="detailCertList"></div>
                </div>

                <!-- Approval Information -->
                <div class="detail-section" id="detailApprovalSection" style="display: none;">
                    <h4><i class="fas fa-check-circle"></i> <span data-lang="approval-information">Approval Information</span></h4>
                    <div id="detailApprovalInfo"></div>
                </div>

                <!-- Workflow History -->
                <div class="detail-section" id="detailWorkflowSection" style="display: none; padding-top: 20px;">
                    <h4><i class="fas fa-history"></i> <span data-lang="workflow-history">Workflow History</span></h4>
                    <div id="detailWorkflowInfo"></div>
                </div>
            </div>
            <div class="modal-footer-appt">
                <button type="button" class="btn btn-secondary" onclick="closeModal('appointmentDetailModal')"><span data-lang="close">Close</span></button>
            </div>
        </div>
    </div>
    

    <!-- Rejection Detail Modal -->
    <div id="rejectionDetailModal" class="modal">
        <div class="modal-content modal-large-appt">
            <div class="modal-header modal-header-appt" style="background: linear-gradient(135deg, #dc3545 0%, #e85563 100%);">
                <h3><i class="fas fa-exclamation-triangle"></i> Review KTT Rejection</h3>
                <span class="close" onclick="closeModal('rejectionDetailModal')">&times;</span>
            </div>
            <div class="modal-body-appt">

                <!-- Letter Information -->
                <div class="detail-section">
                    <h4><i class="fas fa-file-alt"></i> Letter Information</h4>
                    <div class="detail-grid">
                        <div class="detail-item">
                            <label>Letter Number:</label>
                            <span id="detailAppointmentNumber">-</span>
                        </div>
                        <div class="detail-item">
                            <label>Appointment Date:</label>
                            <span id="detailRejApptDate">-</span>
                        </div>
                        <div class="detail-item">
                            <label>Effective Date:</label>
                            <span id="detailRejEffectiveDate">-</span>
                        </div>
                        <div class="detail-item">
                            <label>Expiry Date:</label>
                            <span id="detailRejExpiryDate">-</span>
                        </div>
                    </div>
                </div>

                <!-- Employee Information -->
                <div class="detail-section">
                    <h4><i class="fas fa-user"></i> Employee Information</h4>
                    <div class="detail-grid">
                        <div class="detail-item">
                            <label>Full Name:</label>
                            <span id="detailEmployeeName">-</span>
                        </div>
                        <div class="detail-item">
                            <label>Employee Code:</label>
                            <span id="detailEmployeeCode">-</span>
                        </div>
                        <div class="detail-item">
                            <label>Position:</label>
                            <span id="detailRejPosition">-</span>
                        </div>
                        <div class="detail-item">
                            <label>Company / Contractor:</label>
                            <span id="detailCompany">-</span>
                        </div>
                        <div class="detail-item">
                            <label>Competency Type:</label>
                            <span id="detailRejCompetencyType">-</span>
                        </div>
                        <div class="detail-item">
                            <label>Competency:</label>
                            <span id="detailRejCompetency">-</span>
                        </div>
                        <div class="detail-item">
                            <label>Work Scope:</label>
                            <span id="detailRejScope">-</span>
                        </div>
                    </div>
                </div>

                <!-- Certifications -->
                <div class="detail-section" id="detailRejCertSection" style="display: none;">
                    <h4><i class="fas fa-certificate"></i> Certifications</h4>
                    <div id="detailRejCertList"></div>
                </div>

                <!-- Employee Documents -->
                <div class="detail-section" id="detailRejDocsSection" style="display: none;">
                    <h4><i class="fas fa-folder-open"></i> Employee Documents</h4>
                    <div class="doc-preview-grid" id="detailRejDocsGrid">
                        <!-- CV -->
                        <div class="doc-preview-card" id="docCardCV" style="display:none;">
                            <div class="doc-preview-icon"><i class="fas fa-file-user"></i></div>
                            <div class="doc-preview-info">
                                <div class="doc-preview-label">Curriculum Vitae (CV)</div>
                                <a id="docLinkCV" href="#" target="_blank" class="btn-doc-preview">
                                    <i class="fas fa-eye"></i> View Document
                                </a>
                            </div>
                        </div>
                        <!-- Statement Letter -->
                        <div class="doc-preview-card" id="docCardStatement" style="display:none;">
                            <div class="doc-preview-icon"><i class="fas fa-file-signature"></i></div>
                            <div class="doc-preview-info">
                                <div class="doc-preview-label">Statement Letter</div>
                                <a id="docLinkStatement" href="#" target="_blank" class="btn-doc-preview">
                                    <i class="fas fa-eye"></i> View Document
                                </a>
                            </div>
                        </div>
                        <div id="certDocsContainer"></div>
                    </div>
                    <p id="detailRejDocsEmpty" style="color:#999; font-style:italic; margin:8px 0 0; display:none;">No documents uploaded.</p>
                </div>

                <!-- KTT Approval Status -->
                <div class="detail-section" id="detailRejApprovalSection" style="display: none;">
                    <h4><i class="fas fa-tasks"></i> KTT Approval Status</h4>
                    <div id="detailRejApprovalInfo"></div>
                </div>

                <!-- Workflow History -->
                <div class="detail-section" id="detailRejWorkflowSection" style="display: none;">
                    <h4><i class="fas fa-history"></i> <span data-lang="workflow-history">Workflow History</span></h4>
                    <div id="detailRejWorkflowInfo"></div>
                </div>

                <!-- Rejection Details -->
                <div class="detail-section" style="background: #fff8f8; border: 1px solid #fecdd3;">
                    <h4 style="color: #dc2626;"><i class="fas fa-ban"></i> Rejection Details</h4>
                    <div id="detailRejectionBlock"></div>
                    <div id="detailRejResubmitInfo" style="display:none; margin-top: 10px;"></div>
                </div>

                <!-- Admin Action Form -->
                <form method="POST" action="" id="reviewActionForm">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
                    <input type="hidden" name="action" value="admin_review">
                    <input type="hidden" name="id" id="reviewActionAppointmentId">
                    <input type="hidden" name="admin_action" id="reviewActionType">
                    <input type="hidden" name="admin_notes_value" value="Reviewed by admin">

                    <div class="review-action-buttons">
                        <button type="button" class="btn-review-modal btn-accept-modal"
                                onclick="submitReview('send_to_ktt')">
                            <i class="fas fa-check-circle"></i> <span data-lang="accept-send-to-ktt">Accept - Send to KTT</span>
                        </button>

                        <button type="button" class="btn-review-modal btn-reject-modal"
                                onclick="submitReview('send_to_user')">
                            <i class="fas fa-times-circle"></i> <span data-lang="reject-return-to-user">Reject - Return to User</span>
                        </button>
                    </div>
                </form>
            </div>
            <div class="modal-footer-appt">
                <button type="button" class="btn btn-secondary" onclick="closeModal('rejectionDetailModal')">Tutup</button>
            </div>
        </div>
    </div>
    
    <!-- Admin Review Modal -->
    <div id="adminReviewModal" class="modal">
        <div class="modal-content modal-large-appt">
            <div class="modal-header modal-header-appt">
                <h3><i class="fas fa-clipboard-check"></i> <span id="reviewModalTitle">Review Admin</span></h3>
                <span class="close" onclick="closeModal('adminReviewModal')">&times;</span>
            </div>
            <form method="POST" action="" id="adminReviewForm">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
                <input type="hidden" name="action" value="admin_review">
                <input type="hidden" name="id" id="reviewAppointmentId">
                <input type="hidden" name="admin_action" id="reviewAdminAction">
                <div class="modal-body-appt">
                    <div class="review-info-display">
                        <p><strong>Employee:</strong> <span id="reviewEmployeeName"></span></p>
                        <p><strong>Letter Number:</strong> <span id="reviewAppointmentNumber"></span></p>
                        <p><strong>Tindakan:</strong> <span id="reviewActionText"></span></p>
                    </div>
                    
                    <div class="form-group-appt">
                        <label>Admin Notes <span class="text-danger">*</span></label>
                        <textarea name="admin_notes" id="reviewAdminNotes" 
                                  class="form-control-appt" rows="4"
                                  placeholder="Enter notes or reason for decision..." data-lang-placeholder="enter-notes-or-reason-decision" required></textarea>
                    </div>
                </div>
                <div class="modal-footer-appt">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('adminReviewModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="reviewSubmitBtn"><i class="fas fa-check"></i> Process Review</button>
                </div>
            </form>
        </div>
    </div>
</div>



<script>
// Data mapping for competency type codes
const competencyTypeMap = {
    'pengawas_operasional': 'PO',
    'pengawas_teknis': 'PT',
    'tenaga_teknis': 'TT'
};

const competencyTypeLabels = {
    'pengawas_operasional': 'Pengawas Operasional',
    'pengawas_teknis': 'Pengawas Teknis',
    'tenaga_teknis': 'Tenaga Teknis'
};

// Function to extract scope code from ruang_lingkup field
function getScopeCode(ruangLingkup) {
    if (!ruangLingkup) return 'UNK';
    
    const scopeLower = ruangLingkup.toLowerCase();
    if (scopeLower.includes('msm') && scopeLower.includes('ttn')) {
        return 'MSM/TTN';
    } else if (scopeLower.includes('msm')) {
        return 'MSM';
    } else if (scopeLower.includes('ttn')) {
        return 'TTN';
    }
    return 'UNK';
}

// Function to format work scope display (PT MSM or PT TTN)
function formatWorkScope(ruangLingkup) {
    if (!ruangLingkup) return '-';
    
    const scopeLower = ruangLingkup.toLowerCase();
    if (scopeLower.includes('msm') && scopeLower.includes('ttn')) {
        return 'PT MSM / PT TTN';
    } else if (scopeLower.includes('msm')) {
        return 'PT MSM';
    } else if (scopeLower.includes('ttn')) {
        return 'PT TTN';
    }
    return ruangLingkup;
}

// Function to update employee details display
function updateEmployeeDetails() {
    const employeeSelect = document.getElementById('employeeSelect');
    const selectedOption = employeeSelect.querySelector(`option[value="${employeeSelect.value}"]`);
    
    if (selectedOption) {
        const competencyType = selectedOption.getAttribute('data-competency-type');
        const ruangLingkup = selectedOption.getAttribute('data-ruang-lingkup');
        
        document.getElementById('competencyTypeDisplay').value = 
            competencyTypeLabels[competencyType] || competencyType;
        document.getElementById('ruangLingkupDisplay').value = formatWorkScope(ruangLingkup);
    }
}

// Function to update preview number based on employee competency
function updatePreviewNumber() {
    const employeeSelect = document.getElementById('employeeSelect');
    const appointmentDateInput = document.getElementById('appointmentDateInput');
    const previewElement = document.getElementById('numberPreview');
    const exampleElement = document.getElementById('previewExample');
    
    const selectedOption = employeeSelect.querySelector(`option[value="${employeeSelect.value}"]`);
    const appointmentDate = appointmentDateInput.value;
    
    let previewText = '';
    let exampleText = 'Contoh: 001/PO/MSM/01/2026';
    
    if (selectedOption && appointmentDate) {
        const competencyType = selectedOption.getAttribute('data-competency-type');
        const ruangLingkup = selectedOption.getAttribute('data-ruang-lingkup');
        
        const typeCode = competencyTypeMap[competencyType] || '--';
        const scopeCode = getScopeCode(ruangLingkup);
        
        // Get month and year from appointment date - WITH LEADING ZERO
        const dateObj = new Date(appointmentDate);
        const month = String(dateObj.getMonth() + 1).padStart(2, '0'); // 01-12 with leading zero
        const year = dateObj.getFullYear();
        
        previewText = `XXX/${typeCode}/${scopeCode}/${month}/${year}`;
        exampleText = `Contoh: 001/${typeCode}/${scopeCode}/${month}/${year}`;
    } else {
        previewText = '---/--/---/--/----';
        exampleText = 'Contoh: 001/PO/MSM/01/2026';
    }
    
    previewElement.textContent = previewText;
    exampleElement.textContent = exampleText;
}

// Auto-fill expiry date based on employee certificate
document.addEventListener('DOMContentLoaded', function() {
    const employeeSelect = document.getElementById('employeeSelect');
    
    if (employeeSelect) {
        employeeSelect.addEventListener('change', function() {
            const employeeId = this.value;
            
            if (employeeId) {
                // Fetch employee certificates
                fetch('../../api/get_employee_certs.php?employee_id=' + employeeId)
                    .then(response => response.json())
                    .then(data => {
                        if (data.earliest_expiry) {
                            document.querySelector('input[name="expiry_date"]').value = data.earliest_expiry;
                        } else {
                            document.querySelector('input[name="expiry_date"]').value = '';
                        }
                    })
                    .catch(error => console.log('Error:', error));
            } else {
                document.querySelector('input[name="expiry_date"]').value = '';
            }
        });
    }
    
    // Initialize preview on page load
    updatePreviewNumber();
});

// Function to open edit modal and populate data (safe version using data attributes)
function openEditModalSafe(button) {
    const id = button.getAttribute('data-id');
    const employeeName = button.getAttribute('data-employee-name');
    const appointmentNumber = button.getAttribute('data-appointment-number');
    const competencyName = button.getAttribute('data-competency-name');
    const letterContent = button.getAttribute('data-letter-content');
    
    document.getElementById('editAppointmentId').value = id || '';
    document.getElementById('editAppointmentNumber').value = appointmentNumber || '';
    document.getElementById('editEmployeeName').value = employeeName || '';
    document.getElementById('editCompetencyName').value = competencyName || '-';
    document.getElementById('editLetterContent').value = letterContent || '';
    
    openModal('editModal');
}

// Function to open edit modal and populate data (legacy - kept for compatibility)
function openEditModal(id, employeeName, appointmentNumber, competencyName, letterContent) {
    document.getElementById('editAppointmentId').value = id;
    document.getElementById('editAppointmentNumber').value = appointmentNumber;
    document.getElementById('editEmployeeName').value = employeeName;
    document.getElementById('editCompetencyName').value = competencyName || '-';
    document.getElementById('editLetterContent').value = letterContent || '';
    
    openModal('editModal');
}

// Tab switching functionality
document.addEventListener('DOMContentLoaded', function() {
    const tabButtons = document.querySelectorAll('.tab-btn');
    const tabContents = document.querySelectorAll('.tab-content');
    
    // Check if there's a hash in URL to open specific tab
    const hash = window.location.hash;
    if (hash) {
        const targetTab = hash.replace('#tab-', '');
        const targetButton = document.querySelector(`[data-tab="${targetTab}"]`);
        if (targetButton) {
            tabButtons.forEach(btn => btn.classList.remove('active'));
            tabContents.forEach(content => content.classList.remove('active'));
            targetButton.classList.add('active');
            document.getElementById('tab-' + targetTab).classList.add('active');
        }
    }
    
    tabButtons.forEach(button => {
        button.addEventListener('click', function() {
            const targetTab = this.getAttribute('data-tab');
            
            // Remove active class from all buttons and contents
            tabButtons.forEach(btn => btn.classList.remove('active'));
            tabContents.forEach(content => content.classList.remove('active'));
            
            // Add active class to clicked button and corresponding content
            this.classList.add('active');
            document.getElementById('tab-' + targetTab).classList.add('active');
            
            // Update URL hash
            window.location.hash = 'tab-' + targetTab;
        });
    });
});

// Validate and sync textarea for admin review forms
function validateAndConfirm(form, actionText) {
    const rejectionItem = form.closest('.rejection-item');
    const textarea = rejectionItem.querySelector('textarea[name="admin_notes"]');
    const hiddenInput = form.querySelector('input[name="admin_notes_value"]');
    
    if (textarea && hiddenInput) {
        hiddenInput.value = textarea.value;
    }
    
    if (!textarea.value.trim()) {
        alert(window.getLanguageText(''));
        textarea.focus();
        return false;
    }
    
    const confirmPrefix = window.getLanguageText('');
    return confirm(confirmPrefix + ' ' + actionText + '?');
}

// Show Rejection Detail Modal
function showRejectionDetailModal(appointmentId, employeeName, appointmentNumber, rejectionNotes) {
    // Fetch detailed data for this appointment
    fetch('../../api/get_appointment_details.php?id=' + appointmentId)
        .then(response => response.json())
        .then(data => {
            const apt = data.success ? (data.appointment || {}) : {};
            const emp = data.success ? (data.employee || {}) : {};
            const pos = data.success ? (data.position || {}) : {};
            const certs = data.success ? (data.certifications || []) : [];

            // -- Letter Information --
            document.getElementById('detailAppointmentNumber').textContent = apt.appointment_number || appointmentNumber;
            document.getElementById('detailRejApptDate').textContent = apt.appointment_date ? formatDate(apt.appointment_date) : '-';
            document.getElementById('detailRejEffectiveDate').textContent = apt.effective_date ? formatDate(apt.effective_date) : '-';
            document.getElementById('detailRejExpiryDate').textContent = apt.expiry_date ? formatDate(apt.expiry_date) : 'No time limit';

            // -- Employee Information --
            const competencyTypeLabels = {
                'pengawas_operasional': 'Pengawas Operasional',
                'pengawas_teknis': 'Pengawas Teknis',
                'tenaga_teknis': 'Tenaga Teknis'
            };
            document.getElementById('detailEmployeeName').textContent = emp.full_name || employeeName;
            document.getElementById('detailEmployeeCode').textContent = emp.employee_code || '-';
            document.getElementById('detailRejPosition').textContent = emp.position || pos.position_name || '-';
            document.getElementById('detailCompany').textContent = emp.contractor_company || '-';
            document.getElementById('detailRejCompetencyType').textContent = competencyTypeLabels[emp.competency_type] || emp.competency_type || '-';
            document.getElementById('detailRejCompetency').textContent = emp.competency_name || '-';
            document.getElementById('detailRejScope').textContent = formatWorkScope(emp.ruang_lingkup);

            // -- Certifications --
            const certSection = document.getElementById('detailRejCertSection');
            const certList = document.getElementById('detailRejCertList');
            certList.innerHTML = '';
            certSection.style.display = 'block';
            if (certs.length > 0) {
                let certHtml = '<table style="width:100%; border-collapse:collapse; font-size:13px;">';
                certHtml += '<thead><tr style="background:#e9ecef; text-align:left;">';
                certHtml += '<th style="padding:8px 10px; border-bottom:2px solid #dee2e6;">Certificate Name</th>';
                certHtml += '<th style="padding:8px 10px; border-bottom:2px solid #dee2e6;">Certificate No.</th>';
                certHtml += '<th style="padding:8px 10px; border-bottom:2px solid #dee2e6;">Issue Date</th>';
                certHtml += '<th style="padding:8px 10px; border-bottom:2px solid #dee2e6;">Expiry Date</th>';
                certHtml += '</tr></thead><tbody>';
                certs.forEach(function(cert) {
                    const isExpired = cert.expiry_date && new Date(cert.expiry_date) < new Date();
                    const expiryStyle = isExpired ? 'color:#ef4444; font-weight:600;' : '';
                    certHtml += '<tr style="border-bottom:1px solid #f0f0f0;">';
                    certHtml += '<td style="padding:8px 10px;">' + (cert.cert_name || '-') + '</td>';
                    certHtml += '<td style="padding:8px 10px;">' + (cert.cert_number || '-') + '</td>';
                    certHtml += '<td style="padding:8px 10px;">' + (cert.issue_date ? formatDate(cert.issue_date) : '-') + '</td>';
                    certHtml += '<td style="padding:8px 10px; ' + expiryStyle + '">' + (cert.expiry_date ? formatDate(cert.expiry_date) : 'No Expiry') + (isExpired ? ' <span style="background:#fee2e2;color:#ef4444;padding:2px 6px;border-radius:4px;font-size:10px;">Expired</span>' : '') + '</td>';
                    certHtml += '</tr>';
                });
                certHtml += '</tbody></table>';
                certList.innerHTML = certHtml;
            } else {
                certList.innerHTML = '<p style="color:#999; font-style:italic; margin:0;">' + (window.getLanguageText('')) + '</p>';
            }

            // -- Employee Documents (CV, Statement Letter & Certification Documents) --
            const docsSection = document.getElementById('detailRejDocsSection');
            const docCardCV = document.getElementById('docCardCV');
            const docCardStatement = document.getElementById('docCardStatement');
            const docsEmpty = document.getElementById('detailRejDocsEmpty');
            const docLinkCV = document.getElementById('docLinkCV');
            const docLinkStatement = document.getElementById('docLinkStatement');

            let hasDoc = false;
            if (emp.cv_file) {
                hasDoc = true;
                docCardCV.style.display = 'flex';
                docLinkCV.href = emp.cv_file;
            } else {
                docCardCV.style.display = 'none';
            }
            if (emp.statement_file) {
                hasDoc = true;
                docCardStatement.style.display = 'flex';
                docLinkStatement.href = emp.statement_file;
            } else {
                docCardStatement.style.display = 'none';
            }

            // Certification document cards
            const certDocsContainer = document.getElementById('certDocsContainer');
            let certDocsHtml = '';
            certs.forEach(function(cert) {
                if (cert.document_file) {
                    hasDoc = true;
                    certDocsHtml +=
                        '<div class="doc-preview-card">'
                        + '<div class="doc-preview-icon"><i class="fas fa-certificate"></i></div>'
                        + '<div class="doc-preview-info">'
                        + '<div class="doc-preview-label">Certificate</div>'
                        + '<a href="' + cert.document_file + '" target="_blank" class="btn-doc-preview">'
                        + '<i class="fas fa-eye"></i> View Document</a>'
                        + '</div>'
                        + '</div>';
                }
            });
            certDocsContainer.innerHTML = certDocsHtml;

            docsSection.style.display = 'block';
            docsEmpty.style.display = hasDoc ? 'none' : 'block';

            // -- KTT Approval Status --
            const rejApprovalSection = document.getElementById('detailRejApprovalSection');
            const rejApprovalInfo = document.getElementById('detailRejApprovalInfo');
            rejApprovalInfo.innerHTML = '';

            let hasApprovalInfo = false;
            if (apt.ktt_msm_status || apt.ktt_ttn_status) {
                hasApprovalInfo = true;
                rejApprovalSection.style.display = 'block';

                if (apt.ktt_msm_status) {
                    const msmDiv = document.createElement('div');
                    msmDiv.className = 'approval-detail-item' + (apt.ktt_msm_status === 'rejected' ? ' rejected-item' : '');
                    let msmHtml = '<strong>KTT MSM:</strong> ';
                    if (apt.ktt_msm_status === 'approved') {
                        msmHtml += '<span class="badge-appt-admin badge-success"><i class="fas fa-check"></i> Approved</span>';
                    } else if (apt.ktt_msm_status === 'rejected') {
                        msmHtml += '<span class="badge-appt-admin badge-danger"><i class="fas fa-times"></i> Rejected</span>';
                    } else {
                        msmHtml += '<span class="badge-appt-admin badge-warning"><i class="fas fa-clock"></i> Pending</span>';
                    }
                    if (apt.ktt1_approved_by_name) {
                        msmHtml += '<div class="approval-meta">By: ' + apt.ktt1_approved_by_name + (apt.ktt1_approved_date ? ' &mdash; ' + formatDateTime(apt.ktt1_approved_date) : '') + '</div>';
                    }
                    msmDiv.innerHTML = msmHtml;
                    rejApprovalInfo.appendChild(msmDiv);
                }

                if (apt.ktt_ttn_status) {
                    const ttnDiv = document.createElement('div');
                    ttnDiv.className = 'approval-detail-item' + (apt.ktt_ttn_status === 'rejected' ? ' rejected-item' : '');
                    let ttnHtml = '<strong>KTT TTN:</strong> ';
                    if (apt.ktt_ttn_status === 'approved') {
                        ttnHtml += '<span class="badge-appt-admin badge-success"><i class="fas fa-check"></i> Approved</span>';
                    } else if (apt.ktt_ttn_status === 'rejected') {
                        ttnHtml += '<span class="badge-appt-admin badge-danger"><i class="fas fa-times"></i> Rejected</span>';
                    } else {
                        ttnHtml += '<span class="badge-appt-admin badge-warning"><i class="fas fa-clock"></i> Pending</span>';
                    }
                    if (apt.ktt2_approved_by_name) {
                        ttnHtml += '<div class="approval-meta">By: ' + apt.ktt2_approved_by_name + (apt.ktt2_approved_date ? ' &mdash; ' + formatDateTime(apt.ktt2_approved_date) : '') + '</div>';
                    }
                    ttnDiv.innerHTML = ttnHtml;
                    rejApprovalInfo.appendChild(ttnDiv);
                }
            } else {
                rejApprovalSection.style.display = 'none';
            }

            // -- Rejection Details Block --
            const rejBlock = document.getElementById('detailRejectionBlock');
            let rejHtml = '';

            const msmRejected = apt.ktt_msm_status === 'rejected';
            const ttnRejected = apt.ktt_ttn_status === 'rejected';

            if (msmRejected || ttnRejected) {
                if (msmRejected) {
                    const ktt1Name = apt.ktt1_approved_by_name || '';
                    const ktt1Notes = apt.ktt1_rejection_notes || '';
                    const ktt1Date = apt.ktt1_rejection_date || '';
                    rejHtml += '<div style="margin-bottom:12px;">';
                    if (ktt1Name) {
                        rejHtml += '<div style="background:#fff3cd; padding:12px; border-radius:6px; margin-bottom:10px; border-left:3px solid #ffc107;">';
                        rejHtml += '<strong style="color:#856404;"><i class="fas fa-user-times"></i> Rejected by (KTT MSM):</strong> ';
                        rejHtml += '<span style="color:#856404; font-weight:600;">' + ktt1Name + '</span>';
                        if (ktt1Date) {
                            rejHtml += '<br><small style="color:#999; font-size:11px;">' + (window.getLanguageText('')) + ' ' + formatDateTime(ktt1Date) + '</small>';
                        }
                        rejHtml += '</div>';
                    }
                    rejHtml += '<div style="background:#fee2e2; padding:12px; border-radius:6px; border-left:3px solid #ef4444;">';
                    rejHtml += '<strong style="color:#7f1d1d;"><i class="fas fa-comment-alt"></i> Rejection Reason (KTT MSM):</strong><br>';
                    rejHtml += '<span style="color:#7f1d1d; white-space:pre-wrap; display:block; margin-top:6px;">' + (ktt1Notes || '<i>No rejection notes provided</i>') + '</span>';
                    rejHtml += '</div>';
                    rejHtml += '</div>';
                }

                if (ttnRejected) {
                    const ktt2Name = apt.ktt2_approved_by_name || '';
                    const ktt2Notes = apt.ktt2_rejection_notes || '';
                    const ktt2Date = apt.ktt2_rejection_date || '';
                    rejHtml += '<div>';
                    if (ktt2Name) {
                        rejHtml += '<div style="background:#fff3cd; padding:12px; border-radius:6px; margin-bottom:10px; border-left:3px solid #ffc107;">';
                        rejHtml += '<strong style="color:#856404;"><i class="fas fa-user-times"></i> Rejected by (KTT TTN):</strong> ';
                        rejHtml += '<span style="color:#856404; font-weight:600;">' + ktt2Name + '</span>';
                        if (ktt2Date) {
                            rejHtml += '<br><small style="color:#999; font-size:11px;">' + (window.getLanguageText('')) + ' ' + formatDateTime(ktt2Date) + '</small>';
                        }
                        rejHtml += '</div>';
                    }
                    rejHtml += '<div style="background:#fee2e2; padding:12px; border-radius:6px; border-left:3px solid #ef4444;">';
                    rejHtml += '<strong style="color:#7f1d1d;"><i class="fas fa-comment-alt"></i> Rejection Reason (KTT TTN):</strong><br>';
                    rejHtml += '<span style="color:#7f1d1d; white-space:pre-wrap; display:block; margin-top:6px;">' + (ktt2Notes || '<i>No rejection notes provided</i>') + '</span>';
                    rejHtml += '</div>';
                    rejHtml += '</div>';
                }
            } else {
                // Fallback: use previous/last rejection data
                const rejectorName = apt.previous_ktt_rejector_name || apt.last_rejection_by_name || '';
                const rejectionDate = apt.previous_ktt_rejection_date || apt.last_rejection_date || '';
                const rejNotes = apt.previous_ktt_rejection_notes || apt.last_rejection_notes || rejectionNotes || '';

                if (rejectorName) {
                    rejHtml += '<div style="background:#fff3cd; padding:12px; border-radius:6px; margin-bottom:10px; border-left:3px solid #ffc107;">';
                    rejHtml += '<strong style="color:#856404;"><i class="fas fa-user-times"></i> Rejected by:</strong> ';
                    rejHtml += '<span style="color:#856404; font-weight:600;">' + rejectorName + '</span>';
                    if (rejectionDate) {
                        rejHtml += '<br><small style="color:#999; font-size:11px;">' + (window.getLanguageText('')) + ' ' + formatDateTime(rejectionDate) + '</small>';
                    }
                    rejHtml += '</div>';
                }

                rejHtml += '<div style="background:#fee2e2; padding:12px; border-radius:6px; border-left:3px solid #ef4444;">';
                rejHtml += '<strong style="color:#7f1d1d;"><i class="fas fa-comment-alt"></i> Rejection Reason:</strong><br>';
                rejHtml += '<span style="color:#7f1d1d; white-space:pre-wrap; display:block; margin-top:6px;">' + (rejNotes || '<i>No rejection notes provided</i>') + '</span>';
                rejHtml += '</div>';
            }

            rejBlock.innerHTML = rejHtml;

            // -- Resubmit Count --
            const resubmitInfo = document.getElementById('detailRejResubmitInfo');
            if (emp.resubmit_count && parseInt(emp.resubmit_count) > 0) {
                resubmitInfo.style.display = 'block';
                resubmitInfo.innerHTML = '<div style="background:#fff3cd; padding:10px; border-radius:6px; border-left:3px solid #f59e0b; font-size:13px;">'
                    + '<i class="fas fa-redo" style="color:#d97706;"></i> '
                    + '<strong style="color:#92400e;">Resubmit History:</strong> '
                    + '<span style="color:#92400e;">This letter has been resubmitted <strong>' + emp.resubmit_count + '</strong> time(s)</span>'
                    + '</div>';
            } else {
                resubmitInfo.style.display = 'none';
            }

            // Set appointment ID for form submission
            document.getElementById('reviewActionAppointmentId').value = appointmentId;

            if (data.workflow_html && data.workflow_html.trim() !== '') {
                document.getElementById('detailRejWorkflowSection').style.display = 'block';
                document.getElementById('detailRejWorkflowInfo').innerHTML = data.workflow_html;
            } else {
                document.getElementById('detailRejWorkflowSection').style.display = 'none';
            }

            openModal('rejectionDetailModal');
        })
        .catch(error => {
            console.error('Error fetching appointment details:', error);
            // Fallback: populate only what was passed in
            document.getElementById('detailAppointmentNumber').textContent = appointmentNumber;
            document.getElementById('detailRejApptDate').textContent = '-';
            document.getElementById('detailRejEffectiveDate').textContent = '-';
            document.getElementById('detailRejExpiryDate').textContent = '-';
            document.getElementById('detailEmployeeName').textContent = employeeName;
            document.getElementById('detailEmployeeCode').textContent = '-';
            document.getElementById('detailRejPosition').textContent = '-';
            document.getElementById('detailCompany').textContent = '-';
            document.getElementById('detailRejCompetencyType').textContent = '-';
            document.getElementById('detailRejCompetency').textContent = '-';
            document.getElementById('detailRejScope').textContent = '-';
            document.getElementById('detailRejCertSection').style.display = 'none';
            document.getElementById('detailRejDocsSection').style.display = 'none';
            document.getElementById('detailRejApprovalSection').style.display = 'none';
            document.getElementById('detailRejectionBlock').innerHTML =
                '<div style="background:#fee2e2; padding:12px; border-radius:6px; border-left:3px solid #ef4444;">'
                + '<strong style="color:#7f1d1d;"><i class="fas fa-comment-alt"></i> Rejection Reason:</strong><br>'
                + '<span style="color:#7f1d1d; white-space:pre-wrap; display:block; margin-top:6px;">' + (rejectionNotes || 'No rejection notes') + '</span>'
                + '</div>';
            document.getElementById('detailRejResubmitInfo').style.display = 'none';
            document.getElementById('reviewActionAppointmentId').value = appointmentId;
            openModal('rejectionDetailModal');
        });
}

// Submit review with conditional validation
function submitReview(action) {
    const form = document.getElementById('reviewActionForm');
    const actionType = document.getElementById('reviewActionType');

    // Set action type
    actionType.value = action;

    // Show confirmation dialog
    const defaultAcceptMessage = 'Are you sure you want to Accept and send back to KTT?';
    const defaultRejectMessage = 'Are you sure you want to Reject and return to User?';

    const confirmMessage = action === 'send_to_ktt'
        ? (window.getLanguageText ? window.getLanguageText('confirm-accept-send-back-ktt', defaultAcceptMessage) : defaultAcceptMessage)
        : (window.getLanguageText ? window.getLanguageText('confirm-reject-return-user', defaultRejectMessage) : defaultRejectMessage);

    if (confirm(confirmMessage)) {
        const btnAccept = form.querySelector('.btn-accept-modal');
        const btnReject = form.querySelector('.btn-reject-modal');
        
        if (action === 'send_to_ktt' && btnAccept && btnReject) {
            btnAccept.innerHTML = '<i class="fas fa-spinner fa-spin"></i> <span data-lang="processing">Processing...</span>';
            btnAccept.disabled = true;
            btnReject.style.display = 'none';
        } else if (action === 'send_to_user' && btnAccept && btnReject) {
            btnReject.innerHTML = '<i class="fas fa-spinner fa-spin"></i> <span data-lang="processing">Processing...</span>';
            btnReject.disabled = true;
            btnAccept.style.display = 'none';
        }
        
        form.submit();
    }

    return false;
}

// Show Admin Review Modal
function showAdminReviewModal(appointmentId, adminAction, employeeName, appointmentNumber) {
    // Attach submit listener to handle UI state once (idempotent)
    if (!window.__adminReviewFormListenerAttached) {
        document.getElementById('adminReviewForm').addEventListener('submit', function(e) {
            const submitBtn = document.getElementById('reviewSubmitBtn');
            const cancelBtn = this.querySelector('.btn-secondary');
            if (submitBtn) {
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> <span data-lang="processing">Processing...</span>';
                submitBtn.style.pointerEvents = 'none';
                submitBtn.style.opacity = '0.7';
            }
            if (cancelBtn) {
                cancelBtn.style.display = 'none';
            }
        });
        window.__adminReviewFormListenerAttached = true;
    }
    
    // Set form values
    document.getElementById('reviewAppointmentId').value = appointmentId;
    document.getElementById('reviewAdminAction').value = adminAction;
    document.getElementById('reviewEmployeeName').textContent = employeeName;
    document.getElementById('reviewAppointmentNumber').textContent = appointmentNumber;
    document.getElementById('reviewAdminNotes').value = '';

    // Set action text and button style
    const actionText = document.getElementById('reviewActionText');
    const submitBtn = document.getElementById('reviewSubmitBtn');
    const modalTitle = document.getElementById('reviewModalTitle');

    if (adminAction === 'send_to_ktt') {
        modalTitle.innerHTML = '<i class="fas fa-check-circle"></i> <span data-lang="accept-send-to-ktt">Accept - Send to KTT</span>';
        actionText.setAttribute('data-lang', 'accept-send-back-to-ktt');
        actionText.style.color = '#2E7D32';
        submitBtn.className = 'btn btn-success';
        submitBtn.innerHTML = '<i class="fas fa-check-circle"></i> <span data-lang="accept-send-to-ktt">Accept - Send to KTT</span>';
    } else {
        modalTitle.innerHTML = '<i class="fas fa-times-circle"></i> <span data-lang="reject-return-to-user">Reject - Return to User</span>';
        actionText.setAttribute('data-lang', 'reject-and-return-to-user');
        actionText.style.color = '#ef4444';
        submitBtn.className = 'btn btn-danger';
        submitBtn.innerHTML = '<i class="fas fa-times-circle"></i> <span data-lang="reject-return-to-user">Reject - Return to User</span>';
    }

    if (window.applyCurrentLanguage) {
        window.applyCurrentLanguage();
    } else if (window.changeLanguage && window.getCurrentLanguage) {
        window.changeLanguage(window.getCurrentLanguage());
    }

    // Show modal
    openModal('adminReviewModal');
}

// Show Appointment Detail Modal
function showAppointmentDetail(appointmentId) {
    // Fetch appointment details
    fetch('../../api/get_appointment_details.php?id=' + appointmentId)
        .then(response => response.json())
        .then(data => {
            const t = (key, fallback) => (window.getLanguageText ? window.getLanguageText(key, fallback) : fallback);
            if (data.success) {
                const apt = data.appointment || {};
                const emp = data.employee || {};
                const pos = data.position || {};
                const certs = data.certifications || [];

                // -- Letter Information --
                document.getElementById('detailApptNumber').textContent = apt.appointment_number || '-';
                document.getElementById('detailApptDate').textContent = apt.appointment_date ? formatDate(apt.appointment_date) : '-';
                document.getElementById('detailEffectiveDate').textContent = apt.effective_date ? formatDate(apt.effective_date) : '-';
                document.getElementById('detailExpiryDate').textContent = apt.expiry_date ? formatDate(apt.expiry_date) : t('no-time-limit', 'No time limit');
                document.getElementById('detailCreatedAt').textContent = apt.created_at ? formatDateTime(apt.created_at) : '-';

                // Status Badge
                let statusHtml = '';
                if (apt.status === 'approved') {
                    statusHtml = '<span class="badge-appt-admin badge-success"><i class="fas fa-check-circle"></i> ' + t('approved', 'Approved') + '</span>';
                } else if (apt.status === 'pending') {
                    statusHtml = '<span class="badge-appt-admin badge-warning"><i class="fas fa-clock"></i> ' + t('pending', 'Pending') + '</span>';
                } else if (apt.status === 'rejected_by_ktt') {
                    statusHtml = '<span class="badge-appt-admin badge-danger"><i class="fas fa-ban"></i> ' + t('rejected-by-ktt', 'Rejected by KTT') + '</span>';
                } else if (apt.status === 'rejected') {
                    statusHtml = '<span class="badge-appt-admin badge-danger"><i class="fas fa-times-circle"></i> ' + t('rejected', 'Rejected') + '</span>';
                } else {
                    statusHtml = '<span class="badge-appt-admin badge-secondary"><i class="fas fa-file"></i> ' + t('draft', 'Draft') + '</span>';
                }
                document.getElementById('detailStatus').innerHTML = statusHtml;

                // -- Employee Information --
                document.getElementById('detailEmpName').textContent = emp.full_name || '-';
                document.getElementById('detailEmpCode').textContent = emp.employee_code || '-';
                document.getElementById('detailEmpPosition').textContent = emp.position || pos.position_name || '-';
                document.getElementById('detailEmpCompany').textContent = emp.contractor_company || '-';

                const competencyTypeLabels = {
                    'pengawas_operasional': 'Pengawas Operasional',
                    'pengawas_teknis': 'Pengawas Teknis',
                    'tenaga_teknis': 'Tenaga Teknis'
                };
                document.getElementById('detailEmpCompetencyType').textContent = competencyTypeLabels[emp.competency_type] || emp.competency_type || '-';
                document.getElementById('detailEmpCompetency').textContent = emp.competency_name || '-';
                document.getElementById('detailEmpScope').textContent = formatWorkScope(emp.ruang_lingkup);

                // -- Certifications --
                const certSection = document.getElementById('detailCertSection');
                const certList = document.getElementById('detailCertList');
                certList.innerHTML = '';
                if (certs.length > 0) {
                    certSection.style.display = 'block';
                    let certHtml = '<table style="width:100%; border-collapse: collapse; font-size: 13px;">';
                    certHtml += '<thead><tr style="background:#e9ecef; text-align:left;">';
                    certHtml += '<th style="padding:8px 10px; border-bottom:2px solid #dee2e6;">' + t('certificate-name', 'Certificate Name') + '</th>';
                    certHtml += '<th style="padding:8px 10px; border-bottom:2px solid #dee2e6;">' + t('certificate-no', 'Certificate No.') + '</th>';
                    certHtml += '<th style="padding:8px 10px; border-bottom:2px solid #dee2e6;">' + t('issue-date', 'Issue Date') + '</th>';
                    certHtml += '<th style="padding:8px 10px; border-bottom:2px solid #dee2e6;">' + t('expiry-date', 'Expiry Date') + '</th>';
                    certHtml += '</tr></thead><tbody>';
                    certs.forEach(function(cert) {
                        const isExpired = cert.expiry_date && new Date(cert.expiry_date) < new Date();
                        const expiryStyle = isExpired ? 'color:#ef4444; font-weight:600;' : '';
                        certHtml += '<tr style="border-bottom:1px solid #f0f0f0;">';
                        certHtml += '<td style="padding:8px 10px;">' + (cert.cert_name || '-') + '</td>';
                        certHtml += '<td style="padding:8px 10px;">' + (cert.cert_number || '-') + '</td>';
                        certHtml += '<td style="padding:8px 10px;">' + (cert.issue_date ? formatDate(cert.issue_date) : '-') + '</td>';
                        certHtml += '<td style="padding:8px 10px; ' + expiryStyle + '">' + (cert.expiry_date ? formatDate(cert.expiry_date) : t('no-expiry', 'No Expiry')) + (isExpired ? ' <span style="background:#fee2e2; color:#ef4444; padding:2px 6px; border-radius:4px; font-size:10px;">' + t('expired', 'Expired') + '</span>' : '') + '</td>';
                        certHtml += '</tr>';
                    });
                    certHtml += '</tbody></table>';
                    certList.innerHTML = certHtml;
                } else {
                    certSection.style.display = 'block';
                    certList.innerHTML = '<p style="color:#999; font-style:italic; margin:0;">' + t('no-certifications-recorded', 'No certifications recorded.') + '</p>';
                }

                // -- Approval Information --
                const approvalSection = document.getElementById('detailApprovalSection');
                const approvalInfo = document.getElementById('detailApprovalInfo');
                approvalInfo.innerHTML = '';

                if (apt.status !== 'draft') {
                    approvalSection.style.display = 'block';

                    // KTT MSM Approval
                    if (apt.ktt_msm_status) {
                        const kttMsmDiv = document.createElement('div');
                        kttMsmDiv.className = 'approval-detail-item' + (apt.ktt_msm_status === 'rejected' ? ' rejected-item' : '');
                        let kttMsmHtml = '<strong>KTT MSM:</strong> ';
                        if (apt.ktt_msm_status === 'approved') {
                            kttMsmHtml += '<span class="badge-appt-admin badge-success"><i class="fas fa-check"></i> ' + t('approved', 'Approved') + '</span>';
                        } else if (apt.ktt_msm_status === 'rejected') {
                            kttMsmHtml += '<span class="badge-appt-admin badge-danger"><i class="fas fa-times"></i> ' + t('rejected', 'Rejected') + '</span>';
                        } else {
                            kttMsmHtml += '<span class="badge-appt-admin badge-warning"><i class="fas fa-clock"></i> ' + t('pending', 'Pending') + '</span>';
                        }
                        if (apt.ktt1_approved_by_name) {
                            kttMsmHtml += '<div class="approval-meta">' + t('by', 'By') + ': ' + apt.ktt1_approved_by_name;
                            if (apt.ktt1_approved_date) {
                                kttMsmHtml += ' &mdash; ' + formatDateTime(apt.ktt1_approved_date);
                            }
                            kttMsmHtml += '</div>';
                        }
                        kttMsmDiv.innerHTML = kttMsmHtml;
                        approvalInfo.appendChild(kttMsmDiv);
                    }

                    // KTT TTN Approval
                    if (apt.ktt_ttn_status) {
                        const kttTtnDiv = document.createElement('div');
                        kttTtnDiv.className = 'approval-detail-item' + (apt.ktt_ttn_status === 'rejected' ? ' rejected-item' : '');
                        let kttTtnHtml = '<strong>KTT TTN:</strong> ';
                        if (apt.ktt_ttn_status === 'approved') {
                            kttTtnHtml += '<span class="badge-appt-admin badge-success"><i class="fas fa-check"></i> ' + t('approved', 'Approved') + '</span>';
                        } else if (apt.ktt_ttn_status === 'rejected') {
                            kttTtnHtml += '<span class="badge-appt-admin badge-danger"><i class="fas fa-times"></i> ' + t('rejected', 'Rejected') + '</span>';
                        } else {
                            kttTtnHtml += '<span class="badge-appt-admin badge-warning"><i class="fas fa-clock"></i> ' + t('pending', 'Pending') + '</span>';
                        }
                        if (apt.ktt2_approved_by_name) {
                            kttTtnHtml += '<div class="approval-meta">' + t('by', 'By') + ': ' + apt.ktt2_approved_by_name;
                            if (apt.ktt2_approved_date) {
                                kttTtnHtml += ' &mdash; ' + formatDateTime(apt.ktt2_approved_date);
                            }
                            kttTtnHtml += '</div>';
                        }
                        kttTtnDiv.innerHTML = kttTtnHtml;
                        approvalInfo.appendChild(kttTtnDiv);
                    }

                    // Admin Approval Notes (if any)
                    if (apt.admin_approval_notes) {
                        const adminDiv = document.createElement('div');
                        adminDiv.className = 'approval-detail-item';
                        adminDiv.style.borderLeftColor = '#37474F';
                        let adminActionLabel = apt.admin_approval_action === 'send_to_ktt'
                            ? t('accept-send-back-to-ktt', 'Accept and send back to KTT')
                            : (apt.admin_approval_action === 'send_to_user'
                                ? t('reject-and-return-to-user', 'Reject and return to User')
                                : apt.admin_approval_action || '-');
                        adminDiv.innerHTML = '<strong><i class="fas fa-user-shield"></i> ' + t('admin-action', 'Admin Action') + ':</strong> <span style="color:#37474F; font-weight:600;">' + adminActionLabel + '</span>'
                            + '<div class="approval-meta" style="margin-top:5px; white-space:pre-wrap;">' + apt.admin_approval_notes + '</div>'
                            + (apt.admin_approved_date ? '<div class="approval-meta">' + t('date-label', 'Date:') + ' ' + formatDateTime(apt.admin_approved_date) + '</div>' : '');
                        approvalInfo.appendChild(adminDiv);
                    }
                } else {
                    approvalSection.style.display = 'none';
                }

                if (data.workflow_html && data.workflow_html.trim() !== '') {
                    document.getElementById('detailWorkflowSection').style.display = 'block';
                    document.getElementById('detailWorkflowInfo').innerHTML = data.workflow_html;
                } else {
                    document.getElementById('detailWorkflowSection').style.display = 'none';
                }

                openModal('appointmentDetailModal');
            } else {
                alert(t('failed-load-appointment-details', 'Failed to load appointment details'));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert(t('error-loading-appointment-details', 'Error loading appointment details'));
        });
}

// Helper function to format date with time
function formatDateTime(dateString) {
    if (!dateString) return '-';
    const date = new Date(dateString);
    const options = { 
        year: 'numeric', 
        month: '2-digit', 
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
        hour12: false
    };
    return date.toLocaleDateString('id-ID', options).replace(/\//g, '/') + ' - ' + 
           date.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit', hour12: false });
}


// Helper function to format date
function formatDate(dateString) {
    if (!dateString) return '-';
    const date = new Date(dateString);
    const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    return date.getDate() + ' ' + months[date.getMonth()] + ' ' + date.getFullYear();
}
</script>
<?php require_once dirname(__DIR__) . '/layouts/footer.php'; ?>
