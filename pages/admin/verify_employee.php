<?php
$page_title = 'Verifikasi Tenaga Kerja';
require_once '../../includes/auth.php';
require_once '../../includes/db.php';
require_once '../../includes/i18n.php';

if (!isset($_GET['id'])) {
    header('Location: employees.php');
    exit();
}

// Pastikan ini ditaruh di baris paling awal sebelum ada output HTML/spasi
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Generate token CSRF jika belum ada di session
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$db = new Database();
$employee_id = intval($_GET['id']);
$message = '';
$error = '';

// Handle verification submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    // 1. Validasi Token Anti-CSRF
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        http_response_code(403);
        die('Keamanan: Token CSRF tidak valid atau tidak ditemukan.');
    }
    if ($_POST['action'] == 'verify_cert') {
        $cert_id = intval($_POST['cert_id']);
        $status = $_POST['status']; // verified or rejected
        $notes = $db->escapeString($_POST['notes']);
        $verified_by = $_SESSION['user_id'];
        
        $sql = "UPDATE employee_certifications SET 
                verification_status = '$status',
                verified_by = $verified_by,
                verified_date = NOW()
                WHERE id = $cert_id";
        
        if ($db->query($sql)) {
            $message = stela_t('certification-verified');
        }
    } elseif ($_POST['action'] == 'verify_employee') {
        $status = $_POST['employee_status']; // verified or rejected
        $notes = $db->escapeString($_POST['employee_notes']);
        $verified_by = $_SESSION['user_id'];
        
        // Auto-verify all pending certifications when employee is verified
        if ($status == 'verified') {
            // Verify all pending certifications automatically
            $db->query("UPDATE employee_certifications SET 
                        verification_status = 'verified',
                        verified_by = $verified_by,
                        verified_date = NOW()
                        WHERE employee_id = $employee_id 
                        AND verification_status = 'pending'");
        }
        
        if (!$error) {
            $sql = "UPDATE employees SET 
                    verification_status = '$status',
                    verified_by = $verified_by,
                    verified_date = NOW(),
                    verification_notes = '$notes'
                    WHERE id = $employee_id";
            
            if ($db->query($sql)) {
                if ($status == 'verified') {
                    // Auto-generate surat penunjukan
                    $position_id = isset($_SESSION['temp_position_' . $employee_id]) ? $_SESSION['temp_position_' . $employee_id] : 0;
                    
                    // Cek apakah sudah ada appointment untuk employee ini
                    $existing_appointment = $db->query("
                        SELECT id, appointment_number, status, 
                               requires_ktt_msm_review, requires_ktt_ttn_review,
                               last_rejected_by_ktt, resubmit_count
                        FROM appointments 
                        WHERE employee_id = $employee_id
                        ORDER BY id DESC 
                        LIMIT 1
                    ")->fetch_assoc();
                    
                    $appointment_id = null; // Variable untuk menyimpan ID appointment

                    $resubmit_check = $db->query("
                        SELECT resubmit_type
                        FROM employees
                        WHERE id = $employee_id
                    ")->fetch_assoc();

                    $is_certificate_resubmit =
                        (($resubmit_check['resubmit_type'] ?? '') === 'certificate');

                    if (!$existing_appointment) {
                        // Create new appointment for first-time verification
                        // Get employee data for appointment number generation
                        $emp_data = $db->query("SELECT competency_type, ruang_lingkup FROM employees WHERE id = $employee_id")->fetch_assoc();
                        $competency_type = $emp_data['competency_type'];
                        $ruang_lingkup = $emp_data['ruang_lingkup'];
                        
                        // Map competency type to code
                        $type_codes = [
                            'pengawas_operasional' => 'PO',
                            'pengawas_teknis' => 'PT',
                            'tenaga_teknis' => 'TT'
                        ];
                        
                        // Map ruang_lingkup to code
                        $scope_code = 'UNK';
                        if (stripos($ruang_lingkup, 'MSM') !== false && stripos($ruang_lingkup, 'TTN') !== false) {
                            $scope_code = 'MSM/TTN';
                        } elseif (stripos($ruang_lingkup, 'MSM') !== false) {
                            $scope_code = 'MSM';
                        } elseif (stripos($ruang_lingkup, 'TTN') !== false) {
                            $scope_code = 'TTN';
                        }
                        
                        $type_code = $type_codes[$competency_type] ?? 'UNK';
                        
                        // Get month and year - WITH LEADING ZERO
                        $month = date('m'); // 01-12 with leading zero
                        $year = date('Y');
                        $today = date('Y-m-d');
                        
                        // Get last number for this combination
                        $last_appointment = $db->query("
                            SELECT COALESCE(MAX(CAST(SUBSTRING_INDEX(appointment_number, '/', 1) AS UNSIGNED)), 0) as last_num
                            FROM appointments 
                            WHERE appointment_number LIKE '%/$type_code/$scope_code/$month/$year'
                        ")->fetch_assoc();
                        
                        $next_num = ($last_appointment['last_num'] ?? 0) + 1;
                        $appointment_number = sprintf('%03d/%s/%s/%s/%s', $next_num, $type_code, $scope_code, $month, $year);
                        
                        // Jika tidak ada position_id, gunakan position pertama yang tersedia
                        if ($position_id <= 0) {
                            $default_position = $db->query("SELECT id FROM positions WHERE is_active = 1 LIMIT 1")->fetch_assoc();
                            $position_id = $default_position['id'] ?? 1;
                        }
                        
                        // Ambil tanggal kadaluarsa dari sertifikat karyawan (yang paling pendek/terdekat)
                        $cert_expiry = $db->query("
                            SELECT MIN(expiry_date) as earliest_expiry 
                            FROM employee_certifications 
                            WHERE employee_id = $employee_id 
                            AND verification_status = 'verified'
                            AND expiry_date IS NOT NULL
                        ")->fetch_assoc();
                        
                        $expiry_date = $cert_expiry['earliest_expiry'] ?? null;
                        
                        // Nonaktifkan appointment lama
                            $db->query("
                                UPDATE appointments
                                SET
                                    status='superseded',
                                    updated_at=NOW()
                                WHERE employee_id=$employee_id
                                AND status IN ('draft','approved')
                            ");

                            if ($expiry_date) {
                            $sql_appointment = "INSERT INTO appointments 
                                              (appointment_number, employee_id, position_id, appointment_date, 
                                               effective_date, expiry_date, status, auto_generated, created_by, notes) 
                                              VALUES ('$appointment_number', $employee_id, $position_id, '$today', 
                                                      '$today', '$expiry_date', 'draft', 1, $verified_by, 'Auto-generated setelah verifikasi data tenaga kerja')";
                        } else {
                            $sql_appointment = "INSERT INTO appointments 
                                              (appointment_number, employee_id, position_id, appointment_date, 
                                               effective_date, status, auto_generated, created_by, notes) 
                                              VALUES ('$appointment_number', $employee_id, $position_id, '$today', 
                                                      '$today', 'draft', 1, $verified_by, 'Auto-generated setelah verifikasi data tenaga kerja')";
                        }
                        
                        if ($db->query($sql_appointment)) {
                            $appointment_id = $db->lastInsertId();
                            
                            // Update appointment_number in employees table for tracking
                            $db->query("UPDATE employees SET appointment_number = '$appointment_number' WHERE id = $employee_id");
                            $_SESSION['success_message'] = stela_t('verified-draft-created', ['appointment_number' => $appointment_number]);
                        } else {
                            $_SESSION['error_message'] = stela_t('verified-create-appointment-failed');
                        }
                    } elseif ($is_certificate_resubmit) {

                        // ================================
                        // CREATE NEW APPOINTMENT
                        // ================================

                        $emp_data = $db->query("
                            SELECT competency_type, ruang_lingkup
                            FROM employees
                            WHERE id = $employee_id
                        ")->fetch_assoc();

                        $competency_type = $emp_data['competency_type'];
                        $ruang_lingkup   = $emp_data['ruang_lingkup'];

                        $type_codes = [
                            'pengawas_operasional' => 'PO',
                            'pengawas_teknis'      => 'PT',
                            'tenaga_teknis'        => 'TT'
                        ];

                        $scope_code = 'UNK';

                        if (
                            stripos($ruang_lingkup, 'MSM') !== false &&
                            stripos($ruang_lingkup, 'TTN') !== false
                        ) {
                            $scope_code = 'MSM/TTN';
                        } elseif (stripos($ruang_lingkup, 'MSM') !== false) {
                            $scope_code = 'MSM';
                        } elseif (stripos($ruang_lingkup, 'TTN') !== false) {
                            $scope_code = 'TTN';
                        }

                        $type_code = $type_codes[$competency_type] ?? 'UNK';

                        $month = date('m');
                        $year  = date('Y');
                        $today = date('Y-m-d');

                        $last_appointment = $db->query("
                            SELECT
                                COALESCE(
                                    MAX(
                                        CAST(
                                            SUBSTRING_INDEX(appointment_number,'/',1)
                                        AS UNSIGNED)
                                    ),
                                    0
                                ) AS last_num
                            FROM appointments
                            WHERE appointment_number LIKE '%/$type_code/$scope_code/$month/$year'
                        ")->fetch_assoc();

                        $next_num = ($last_appointment['last_num'] ?? 0) + 1;

                        $appointment_number = sprintf(
                            '%03d/%s/%s/%s/%s',
                            $next_num,
                            $type_code,
                            $scope_code,
                            $month,
                            $year
                        );

                        if ($position_id <= 0) {
                            $default_position = $db->query("
                                SELECT id
                                FROM positions
                                WHERE is_active=1
                                LIMIT 1
                            ")->fetch_assoc();

                            $position_id = $default_position['id'] ?? 1;
                        }

                        $cert_expiry = $db->query("
                            SELECT MIN(expiry_date) earliest_expiry
                            FROM employee_certifications
                            WHERE employee_id=$employee_id
                            AND verification_status='verified'
                        ")->fetch_assoc();

                        $expiry_date = $cert_expiry['earliest_expiry'];
                        $new_certificate = $db->query("
                            SELECT id
                            FROM employee_certifications
                            WHERE employee_id = $employee_id
                            AND verification_status='verified'
                            ORDER BY verified_date DESC, id DESC
                            LIMIT 1
                        ")->fetch_assoc();

                        $new_certificate_id = (int)$new_certificate['id'];

                        if ($expiry_date) {

                            $sql = "
                            INSERT INTO appointments
                            (
                                appointment_number,
                                employee_id,
                                position_id,
                                appointment_date,
                                effective_date,
                                expiry_date,
                                status,
                                auto_generated,
                                created_by,
                                notes
                            )
                            VALUES
                            (
                                '$appointment_number',
                                $employee_id,
                                $position_id,
                                '$today',
                                '$today',
                                '$expiry_date',
                                'draft',
                                1,
                                $verified_by,
                                'Certificate Resubmission'
                            )";

                        } else {

                            $sql = "
                            INSERT INTO appointments
                            (
                                appointment_number,
                                employee_id,
                                position_id,
                                appointment_date,
                                effective_date,
                                status,
                                auto_generated,
                                created_by,
                                notes
                            )
                            VALUES
                            (
                                '$appointment_number',
                                $employee_id,
                                $position_id,
                                '$today',
                                '$today',
                                'draft',
                                1,
                                $verified_by,
                                'Certificate Resubmission'
                            )";

                        }

                        if ($db->query($sql)) {

                            $appointment_id = $db->lastInsertId();

                            $db->query("
                                UPDATE employees
                                SET
                                    appointment_number='$appointment_number',
                                    verification_status='verified',
                                    resubmit_type=NULL
                                WHERE id=$employee_id
                            ");

                            $_SESSION['success_message'] =
                                "Certificate verified successfully. New Appointment Number : "
                                . $appointment_number;

                        } else {

                            $_SESSION['error_message'] =
                                "Failed to create new appointment.";

                        }

                    }
                                                            
                    else {
                        // For existing appointment (re-submit case), update the existing appointment
                        $existing_number = $existing_appointment['appointment_number'];
                        $appointment_id = $existing_appointment['id'];
                        $is_ktt_resubmit = ($existing_appointment['requires_ktt_msm_review'] == 1 || $existing_appointment['requires_ktt_ttn_review'] == 1);
                        
                        // Update expiry date from certifications
                        $cert_expiry = $db->query("
                            SELECT MIN(expiry_date) as earliest_expiry 
                            FROM employee_certifications 
                            WHERE employee_id = $employee_id 
                            AND verification_status = 'verified'
                            AND expiry_date IS NOT NULL
                        ")->fetch_assoc();
                        
                        $expiry_date = $cert_expiry['earliest_expiry'] ?? null;
                        
                        if ($is_ktt_resubmit) {
                            // Ini resubmit dari KTT rejection
                            // Only reset the KTT(s) who rejected, keep approved KTT intact
                            $update_parts = [];
                            if ($expiry_date) {
                                $update_parts[] = "expiry_date = '$expiry_date'";
                            } else {
                                $update_parts[] = "expiry_date = NULL";
                            }
                            // Set status to 'draft' so it appears in appointments.php for admin
                            $update_parts[] = "status = 'draft'";
                            $update_parts[] = "approved_by = NULL";
                            $update_parts[] = "approved_date = NULL";
                            $update_parts[] = "approval_notes = NULL";
                            $update_parts[] = "last_rejected_by_ktt = NULL";
                            $update_parts[] = "rejected_by_ktt_user_id = NULL";
                            $update_parts[] = "updated_at = NOW()";

                            // Only reset KTT statuses for the KTT(s) that need re-review
                            if ($existing_appointment['requires_ktt_msm_review'] == 1) {
                                $update_parts[] = "ktt_msm_status = 'pending'";
                                $update_parts[] = "ktt1_approved_by = NULL";
                                $update_parts[] = "ktt1_approved_date = NULL";
                            }
                            if ($existing_appointment['requires_ktt_ttn_review'] == 1) {
                                $update_parts[] = "ktt_ttn_status = 'pending'";
                                $update_parts[] = "ktt2_approved_by = NULL";
                                $update_parts[] = "ktt2_approved_date = NULL";
                            }

                            $db->query("UPDATE appointments SET " . implode(', ', $update_parts) . " WHERE id = $appointment_id");

                            // Delete old KTT approval records only for KTT(s) that need re-review
                            if ($existing_appointment['requires_ktt_msm_review'] == 1) {
                                $db->query("DELETE FROM ktt_approvals WHERE appointment_id = $appointment_id AND ktt_user_id = 7");
                            }
                            if ($existing_appointment['requires_ktt_ttn_review'] == 1) {
                                $db->query("DELETE FROM ktt_approvals WHERE appointment_id = $appointment_id AND ktt_user_id = 8");
                            }

                            $_SESSION['success_message'] = stela_t('verified-existing-ready-ktt', ['existing_number' => $existing_number]);
                            
                        } else {
                            // Ini new appointment atau resubmit biasa (bukan dari KTT rejection)
                            if ($expiry_date) {
                                $db->query("UPDATE appointments SET 
                                           expiry_date = '$expiry_date',
                                           status = 'draft',
                                           ktt1_approved_by = NULL,
                                           ktt1_approved_date = NULL,
                                           ktt2_approved_by = NULL,
                                           ktt2_approved_date = NULL,
                                           approved_by = NULL,
                                           approved_date = NULL,
                                           approval_notes = NULL,
                                           updated_at = NOW()
                                           WHERE id = $appointment_id");
                            } else {
                                $db->query("UPDATE appointments SET 
                                           status = 'draft',
                                           ktt1_approved_by = NULL,
                                           ktt1_approved_date = NULL,
                                           ktt2_approved_by = NULL,
                                           ktt2_approved_date = NULL,
                                           approved_by = NULL,
                                           approved_date = NULL,
                                           approval_notes = NULL,
                                           updated_at = NOW()
                                           WHERE id = $appointment_id");
                            }
                            
                            // Delete old KTT approval records to allow fresh approval
                            $db->query("DELETE FROM ktt_approvals WHERE appointment_id = $appointment_id");
                            
                            $_SESSION['success_message'] = stela_t('verified-existing-updated-reset-ktt', ['existing_number' => $existing_number]);
                        }
                        
                        // Update appointment_number in employees table for tracking
                        $db->query("UPDATE employees SET appointment_number = '$existing_number' WHERE id = $employee_id");
                    }
                    
                    // Redirect langsung ke halaman appointments dengan ID appointment
                    // Notify user/dept that admin accepted the employee
                    try {
                        require_once '../../includes/notifications.php';
                        set_time_limit(60);
                        $notifService = new NotificationService();
                        $notifService->notifyAdminAcceptedEmployee($employee_id);
                    } catch (Exception $e) {
                        error_log("Notification error (admin accepted): " . $e->getMessage());
                    }
                    if ($appointment_id) {
                        header("Location: appointments.php?highlight=" . $appointment_id);
                        exit();
                    } else {
                        // Fallback jika appointment_id tidak ada
                        header("Location: appointments.php");
                        exit();
                    }
                } else {
                    $_SESSION['success_message'] = stela_t('rejected-data');
                    // Notify user/dept that admin rejected the employee
                    try {
                        require_once '../../includes/notifications.php';
                        set_time_limit(60);
                        $notifService = new NotificationService();
                        $notifService->notifyAdminRejectedEmployee($employee_id, $notes);
                    } catch (Exception $e) {
                        error_log("Notification error (admin rejected): " . $e->getMessage());
                    }
                    // Redirect ke employees setelah 2 seconds
                    header("refresh:2;url=employees.php");
                }
            }
        }
    }
}

// Get employee details
$employee = $db->query("
    SELECT e.*, 
           u.full_name as verified_by_name,
           u.username as verified_by_username,
           CASE 
               WHEN e.competency_type = 'pengawas_operasional' THEN 'Pengawas Operasional'
               WHEN e.competency_type = 'pengawas_teknis' THEN 'Pengawas Teknis'
               WHEN e.competency_type = 'tenaga_teknis' THEN 'Tenaga Teknis'
               ELSE e.competency_type
           END as competency_type_display
    FROM employees e
    LEFT JOIN users u ON e.verified_by = u.id
    WHERE e.id = $employee_id
")->fetch_assoc();

if (!$employee) {
    header('Location: employees.php');
    exit();
}

// Get employee certifications
$certifications = $db->query("
    SELECT ec.*, c.cert_name,
           COALESCE(ec.cert_type, c.cert_type, '-') as cert_type,
           u.full_name as verified_by_name,
           CASE 
               WHEN ec.expiry_date < CURDATE() THEN 'expired'
               WHEN ec.verification_status = 'verified' THEN 'verified'
               WHEN ec.verification_status = 'rejected' THEN 'rejected'
               ELSE 'pending'
           END as cert_status,
           CASE 
               WHEN ec.expiry_date < CURDATE() THEN 'danger'
               WHEN ec.verification_status = 'verified' THEN 'success'
               WHEN ec.verification_status = 'rejected' THEN 'danger'
               ELSE 'warning'
           END as status_class
    FROM employee_certifications ec
    JOIN certifications c ON ec.certification_id = c.id
    LEFT JOIN users u ON ec.verified_by = u.id
    WHERE ec.employee_id = $employee_id
    ORDER BY ec.created_at DESC
");

// Get position target
$position_id = isset($_SESSION['temp_position_' . $employee_id]) ? $_SESSION['temp_position_' . $employee_id] : 0;
$position = null;
if ($position_id > 0) {
    $position = $db->query("SELECT * FROM positions WHERE id = $position_id")->fetch_assoc();
}

require_once '../../includes/header.php';
?>

<?php if (isset($_SESSION['success_message'])): ?>
<div class="alert alert-success">
    <?php 
    echo htmlspecialchars($_SESSION['success_message']); 
    unset($_SESSION['success_message']);
    ?>
</div>
<?php endif; ?>

<?php if (isset($_SESSION['error_message'])): ?>
<div class="alert alert-error">
    <?php 
    echo htmlspecialchars($_SESSION['error_message']); 
    unset($_SESSION['error_message']);
    ?>
</div>
<?php endif; ?>

<?php if ($message): ?>
<div class="alert alert-success">
    <?php echo htmlspecialchars($message); ?>
</div>
<?php endif; ?>

<?php if ($error): ?>
<div class="alert alert-error">
    <?php echo htmlspecialchars($error); ?>
</div>
<?php endif; ?>

<div class="card">
    <div class="card-header card-header-verify">
        <div class="header-content-verify">
            <h3><i class="fas fa-user-check"></i> <span data-lang="verify-employee-data">Verify Employee Data</span></h3>
        </div>
        <a href="employees.php" class="btn-back-verify">
            <i class="fas fa-arrow-left"></i>
            <span data-lang="back">Back</span>
        </a>
    </div>
    <div class="card-body">
        <div class="verification-container">
            <!-- Employee Data -->
            <div class="section">
                <h4 data-lang="identity-data">Identity Data</h4>
                <div class="info-grid">
                    <div class="info-item">
                        <label data-lang="id-badge">ID BADGE:</label>
                        <span><?php echo htmlspecialchars($employee['employee_code']); ?></span>
                    </div>
                    <div class="info-item">
                        <label data-lang="full-name">Full Name:</label>
                        <span><?php echo htmlspecialchars($employee['full_name']); ?></span>
                    </div>
                    <div class="info-item">
                        <label data-lang="position">Position:</label>
                        <span><?php echo htmlspecialchars($employee['position']); ?></span>
                    </div>
                    <div class="info-item">
                        <label data-lang="company">Company:</label>
                        <span><strong><?php echo htmlspecialchars($employee['contractor_company']); ?></strong></span>
                    </div>
                    <div class="info-item">
                        <label data-lang="scope-of-work">Scope of Work:</label>
                        <?php if (!empty($employee['ruang_lingkup'])): ?>
                            <span class="badge-scope"><?php echo htmlspecialchars($employee['ruang_lingkup']); ?></span>
                        <?php else: ?>
                            <span class="text-muted">-</span>
                        <?php endif; ?>
                    </div>
                    <div class="info-item">
                        <label data-lang="competency-type">Competency Type:</label>
                        <span class="badge-competency competency-<?php echo $employee['competency_type']; ?>">
                            <?php echo htmlspecialchars($employee['competency_type_display'] ?? ''); ?>
                        </span>
                    </div>
                    <div class="info-item">
                        <label data-lang="competency">Competency:</label>
                        <?php if (!empty($employee['competency_name'])): ?>
                            <span class="badge-competency-name"><?php echo htmlspecialchars($employee['competency_name']); ?></span>
                        <?php else: ?>
                            <span class="text-muted">-</span>
                        <?php endif; ?>
                    </div>
                </div>
                
                <?php if (!empty($employee['address'])): ?>
                <div class="info-item">
                    <label data-lang="address">Address:</label>
                    <p><?php echo nl2br(htmlspecialchars($employee['address'])); ?></p>
                </div>
                <?php endif; ?>
                
                <?php if ($position): ?>
                <div class="info-item">
                    <label data-lang="target-position">Target Position:</label>
                    <span class="badge badge-primary">
                        <?php echo htmlspecialchars($position['position_name']); ?>
                    </span>
                </div>
                <?php endif; ?>
                
                <?php if ($employee['cv_file']): ?>
                <div class="info-item">
                    <label data-lang="cv-file">CV:</label>
                    <a href="/assets/<?php echo $employee['cv_file']; ?>" target="_blank" class="btn btn-sm btn-info">
                        <i class="fas fa-file-pdf"></i> <span data-lang="view-cv">Lihat CV</span>
                    </a>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($employee['statement_file'])): ?>
                <div class="info-item">
                    <label data-lang="ktt-statement-letter">Statement Letter:</label>
                    <a href="assets/<?php echo $employee['statement_file']; ?>" target="_blank" class="btn btn-sm btn-primary">
                        <i class="fas fa-signature"></i> <span data-lang="ktt-view-statement">View Statement Letter</span>
                    </a>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Certifications -->
            <div class="section">
                <h4 data-lang="certifications-competencies">Certifications/Competencies</h4>
                
                <?php if ($certifications->num_rows > 0): ?>
                    <?php while ($cert = $certifications->fetch_assoc()): ?>
                    <div class="cert-card">
                        <div class="cert-header">
                            <div>
                                <h5><?php echo htmlspecialchars($cert['cert_name']); ?></h5>
                                <p class="text-muted"><span data-lang="number-short">No:</span> <?php echo htmlspecialchars($cert['cert_number']); ?></p>
                                <p class="text-muted">
                                    <strong data-lang="certificate-type">Certificate Type:</strong>
                                    <span class="badge-cert-type">
                                        <?php
                                        $certType = isset($cert['cert_type']) && $cert['cert_type'] !== null && trim($cert['cert_type']) !== '' ? $cert['cert_type'] : '-';
                                        echo htmlspecialchars($certType);
                                        ?>
                                    </span>
                                </p>
                                <p class="text-muted"><strong data-lang="issuer">Issuer:</strong> <?php echo htmlspecialchars($cert['cert_issuer']); ?></p>
                            </div>
                            <span class="badge badge-<?php echo $cert['status_class']; ?>">
                                <?php echo strtoupper($cert['cert_status']); ?>
                            </span>
                        </div>
                        
                        <div class="cert-body">
                            <div class="info-grid">
                                <div class="info-item">
                                    <label data-lang="issue-date">Issue Date:</label>
                                    <span>
                                        <?php 
                                        if (!empty($cert['issue_date'])) {
                                            echo date('d/m/Y', strtotime($cert['issue_date']));
                                        } else {
                                            echo '-';
                                        }
                                        ?>
                                    </span>
                                </div>
                                <div class="info-item">
                                    <label data-lang="expiry-date">Expiry Date:</label>
                                    <span class="<?php echo (!empty($cert['expiry_date']) && strtotime($cert['expiry_date']) < time()) ? 'text-danger' : ''; ?>">
                                        <?php 
                                        if (!empty($cert['expiry_date'])) {
                                            echo date('d/m/Y', strtotime($cert['expiry_date']));
                                            if (!empty($cert['expiry_date']) && strtotime($cert['expiry_date']) < time()) {
                                                echo ' <i class="fas fa-exclamation-triangle text-danger"></i> <span data-lang="expired">EXPIRED</span>';
                                            }
                                        } else {
                                            echo '-';
                                        }
                                        ?>
                                    </span>
                                </div>
                            </div>
                            
                            <?php if ($cert['document_file']): ?>
                            <div class="info-item">
                                <label data-lang="document">Document:</label>
                                <a href="assets/<?php echo $cert['document_file']; ?>" target="_blank" class="btn btn-sm btn-info">
                                    <i class="fas fa-file-pdf"></i> <span data-lang="ktt-view-certificate">View Certificate</span>
                                </a>
                            </div>
                            <?php endif; ?>
                            
                            <?php if ($cert['verification_status'] == 'pending'): ?>
                            
                            <?php elseif (!empty($cert['expiry_date']) && strtotime($cert['expiry_date']) < time()): ?>
                            <div class="alert alert-warning">
                                <i class="fas fa-exclamation-triangle"></i> <span data-lang="certificate-has-expired">Certificate has expired!</span>
                            </div>
                            <?php endif; ?>
                            
                            <?php if ($cert['verified_by_name']): ?>
                            <div class="verified-info">
                                <small><span data-lang="verified-by">Verified by</span>: <?php echo htmlspecialchars($cert['verified_by_name'] ?? 'System'); ?>
                                <span data-lang="on-date">on</span> <?php echo date('d/m/Y H:i', strtotime($cert['verified_date'])); ?></small>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <p class="text-muted" data-lang="no-certifications-uploaded-yet">No certifications uploaded yet</p>
                <?php endif; ?>
            </div>
            
            <!-- Final Verification -->
            <?php if ($employee['verification_status'] == 'pending'): ?>
            <div class="section verification-section">
                <h4 data-lang="final-data-verification">Final Data Verification</h4>
                <form method="POST" id="verificationForm">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
                    <input type="hidden" name="action" value="verify_employee">
                    
                    <div class="verification-form-wrapper">
                        <div class="form-group-verify">
                            <label for="employee_notes" data-lang="verification-notes">Verification Notes</label>
                            <textarea 
                                name="employee_notes" 
                                id="employee_notes" 
                                class="form-control-verify" 
                                rows="4" 
                                placeholder="Add verification notes (required if rejected)" data-lang-placeholder="add-verification-notes-required-reject"></textarea>
                            
                        </div>
                        
                        <div class="verification-actions">
                            <button type="button" class="btn-verify btn-accept" onclick="submitVerification('verified')">
                                <i class="fas fa-check-circle"></i>
                                <span data-lang="accept">Accept</span>
                            </button>
                            
                            <button type="button" class="btn-verify btn-reject" onclick="submitVerification('rejected')">
                                <i class="fas fa-times-circle"></i>
                                <span data-lang="reject">Reject</span>
                            </button>
                        </div>
                    </div>
                </form>
            </div>
            <?php endif; ?>
            
            <?php if ($employee['verification_notes']): ?>
            <div class="section">
                <h4 data-lang="verification-notes">Verification Notes</h4>
                <div class="alert alert-info">
                    <p><?php echo nl2br(htmlspecialchars($employee['verification_notes'])); ?></p>
                    <small><span data-lang="by">By</span>: <?php echo htmlspecialchars($employee['verified_by_name'] ?? 'System'); ?>
                    <span data-lang="on-date">on</span> <?php 
                        if (!empty($employee['verified_date'])) {
                            echo date('d/m/Y H:i', strtotime($employee['verified_date']));
                        } else {
                            echo '-';
                        }
                    ?></small>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
/* ============================================
   TYPOGRAPHY SYSTEM - Consistent Font Sizes
   ============================================ */
:root {
    --font-size-xs: 12px;
    --font-size-sm: 13px;
    --font-size-base: 14px;
    --font-size-md: 15px;
    --font-size-lg: 16px;
    --font-size-xl: 18px;
    --font-size-2xl: 20px;
    
    --font-weight-normal: 400;
    --font-weight-medium: 500;
    --font-weight-semibold: 600;
    --font-weight-bold: 700;
}

.verification-container {
    max-width: 1400px;
    margin: 0 auto;
    padding: 0 20px;
}

.section {
    background: #f8f9fa;
    padding: 20px;
    border-radius: 8px;
    margin-bottom: 20px;
}

.section h4 {
    color: var(--primary-color);
    margin-bottom: 20px;
    padding-bottom: 10px;
    border-bottom: 2px solid #dee2e6;
    font-size: var(--font-size-lg);
    font-weight: var(--font-weight-semibold);
}

.info-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 15px;
    margin-bottom: 15px;
}

.info-item {
    display: flex;
    flex-direction: column;
    gap: 5px;
}

.info-item label {
    font-weight: var(--font-weight-semibold);
    color: #495057;
    font-size: var(--font-size-sm);
}

.info-item span,
.info-item p {
    font-size: var(--font-size-base);
    color: #212529;
    line-height: 1.5;
}

.badge-competency {
    display: inline-block;
    padding: 6px 12px;
    border-radius: 6px;
    font-size: var(--font-size-xs);
    font-weight: var(--font-weight-semibold);
    width: fit-content;
}

.competency-pengawas_operasional {
    background: #ECEFF1;
    color: #37474F;
}

.competency-pengawas_teknis {
    background: #fef3c7;
    color: #b45309;
}

.competency-tenaga_teknis {
    background: #E8F5E9;
    color: #1B5E20;
}

.badge-competency-name,
.badge-scope {
    display: inline-block;
    background: #f3f4f6;
    color: #374151;
    padding: 6px 12px;
    border-radius: 6px;
    font-size: var(--font-size-xs);
    font-weight: var(--font-weight-medium);
    width: fit-content;
}

.badge-cert-type {
    display: inline-block;
    background: #ECEFF1;
    color: #4338ca;
    padding: 4px 10px;
    border-radius: 4px;
    font-size: var(--font-size-xs);
    font-weight: var(--font-weight-semibold);
}

.text-muted {
    color: #6c757d;
    font-size: var(--font-size-base);
}

.cert-card {
    background: white;
    border: 1px solid #dee2e6;
    border-radius: 8px;
    padding: 15px;
    margin-bottom: 15px;
}

.cert-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 15px;
    padding-bottom: 10px;
    border-bottom: 1px solid #e9ecef;
}

.cert-header h5 {
    margin: 0;
    color: var(--primary-color);
    font-size: var(--font-size-md);
    font-weight: var(--font-weight-semibold);
}

.cert-header p {
    margin: 5px 0 0 0;
    font-size: var(--font-size-sm);
}

.cert-body {
    padding: 10px 0;
}

.cert-body .info-item span {
    font-size: var(--font-size-sm);
}

.cert-actions {
    margin-top: 15px;
    padding-top: 15px;
    border-top: 1px solid #e9ecef;
}

.verified-info {
    margin-top: 10px;
    padding: 10px;
    background: #e7f3ff;
    border-radius: 5px;
}

.verified-info small {
    font-size: var(--font-size-xs);
    color: #495057;
}

.alert {
    font-size: var(--font-size-base);
}

.alert i {
    font-size: var(--font-size-base);
}

.button-group {
    display: flex;
    gap: 10px;
    margin-top: 20px;
}

/* ============================================
   CARD HEADER - Consistent Typography
   ============================================ */
.card-header-verify {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px 24px;
    background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
    border-bottom: 2px solid #e9ecef;
}

.header-content-verify h3 {
    margin: 0;
    font-size: var(--font-size-xl);
    font-weight: var(--font-weight-semibold);
    color: #1f2937;
    display: flex;
    align-items: center;
    gap: 10px;
}

.header-content-verify h3 i {
    color: #37474F;
    font-size: var(--font-size-2xl);
}

.btn-back-verify {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 20px;
    background: white;
    border: 2px solid #e9ecef;
    border-radius: 8px;
    color: #616161;
    font-size: var(--font-size-base);
    font-weight: var(--font-weight-semibold);
    text-decoration: none;
    transition: all 0.3s ease;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
}

.btn-back-verify:hover {
    background: #f9fafb;
    border-color: #37474F;
    color: #37474F;
    transform: translateX(-3px);
    box-shadow: 0 4px 8px rgba(55, 71, 79, 0.15);
}

.btn-back-verify i {
    font-size: var(--font-size-base);
    transition: transform 0.3s ease;
}

.btn-back-verify:hover i {
    transform: translateX(-2px);
}

/* ============================================
   VERIFICATION FORM - Consistent Typography
   ============================================ */
.verification-section {
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    border: 2px solid #dee2e6;
}

.verification-form-wrapper {
    background: white;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
}

.form-group-verify {
    margin-bottom: 20px;
}

.form-group-verify label {
    display: block;
    font-weight: var(--font-weight-semibold);
    color: #495057;
    font-size: var(--font-size-base);
    margin-bottom: 8px;
}

.form-control-verify {
    width: 100%;
    padding: 12px 15px;
    border: 2px solid #e9ecef;
    border-radius: 8px;
    font-size: var(--font-size-base);
    font-family: inherit;
    transition: all 0.3s ease;
    resize: vertical;
    line-height: 1.5;
}

.form-control-verify:focus {
    outline: none;
    border-color: #37474F;
    box-shadow: 0 0 0 3px rgba(55, 71, 79, 0.1);
}

.form-hint-verify {
    display: flex;
    align-items: center;
    gap: 6px;
    color: #6c757d;
    font-size: var(--font-size-xs);
    margin-top: 8px;
}

.form-hint-verify i {
    color: #37474F;
    font-size: var(--font-size-xs);
}

.verification-actions {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
}

.btn-verify {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    padding: 14px 24px;
    border: none;
    border-radius: 8px;
    font-size: var(--font-size-md);
    font-weight: var(--font-weight-semibold);
    cursor: pointer;
    transition: all 0.3s ease;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}

.btn-verify i {
    font-size: var(--font-size-xl);
}

.btn-accept {
    background: linear-gradient(135deg, #2E7D32, #1B5E20);
    color: white;
}

.btn-accept:hover {
    background: linear-gradient(135deg, #1B5E20, #047857);
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
}

.btn-reject {
    background: linear-gradient(135deg, #ef4444, #dc2626);
    color: white;
}

.btn-reject:hover {
    background: linear-gradient(135deg, #dc2626, #b91c1c);
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);
}

.btn-verify:active {
    transform: translateY(0);
}

/* ============================================
   BUTTONS - Consistent Typography
   ============================================ */
.btn,
.btn-sm {
    font-size: var(--font-size-sm);
    font-weight: var(--font-weight-medium);
    line-height: 1.5;
}

.btn i,
.btn-sm i {
    font-size: var(--font-size-sm);
}

.badge {
    font-size: var(--font-size-xs);
    font-weight: var(--font-weight-semibold);
    padding: 4px 10px;
}

/* ============================================
   RESPONSIVE TYPOGRAPHY
   ============================================ */
@media (max-width: 768px) {
    :root {
        --font-size-xs: 11px;
        --font-size-sm: 12px;
        --font-size-base: 13px;
        --font-size-md: 14px;
        --font-size-lg: 15px;
        --font-size-xl: 16px;
        --font-size-2xl: 18px;
    }
    
    .card-header-verify {
        flex-direction: column;
        gap: 12px;
        align-items: flex-start;
        padding: 16px 20px;
    }
    
    .btn-back-verify {
        width: 100%;
        justify-content: center;
        padding: 12px 20px;
    }
    
    .verification-actions {
        grid-template-columns: 1fr;
    }
    
    .btn-verify {
        padding: 12px 20px;
    }
    
    .form-control-verify {
        padding: 10px 12px;
    }
    
    .info-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<script>
function submitVerification(status) {
    const form = document.getElementById('verificationForm');
    const notes = document.getElementById('employee_notes').value.trim();
    
    // If rejected, notes are required
    if (status === 'rejected') {
        if (!notes) {
            alert(window.getLanguageText(''));
            document.getElementById('employee_notes').focus();
            return false;
        }
        if (!confirm(window.getLanguageText(''))) {
            return false;
        }
    } else if (status === 'verified') {
        // If verified, notes are optional
        if (!confirm(window.getLanguageText(''))) {
            return false;
        }
    }
    
    // Create hidden input untuk status
    const statusInput = document.createElement('input');
    statusInput.type = 'hidden';
    statusInput.name = 'employee_status';
    statusInput.value = status;
    
    form.appendChild(statusInput);
    form.submit();
}
</script>

<?php require_once '../../includes/footer.php'; ?>



