<?php
$page_title = 'Upload Employee Correction' ;
require_once dirname(__DIR__, 3) . '/app/Helpers/auth_helper.php';
// Included via bootstrap/app.php

// Only USER role can access this page
checkPageAccess(['user']);

// Pastikan ini ditaruh di baris paling awal sebelum ada output HTML/spasi
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Generate token CSRF jika belum ada di session
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$db = new Database();
$company_name = $_SESSION['company_name'] ?? '';
$current_department = $_SESSION['department'] ?? '';
$message = '';
$error = '';

// Get employee ID from URL
if (!isset($_GET['id'])) {
    header('Location: employees.php');
    exit();
}

$employee_id = intval($_GET['id']);

// Get employee data with appointment rejection info
$employee = $db->query("
    SELECT e.*, 
           MAX(u.full_name) as verified_by_name,
           MAX(u.role) as verifier_role,
           MAX(a.id) as appointment_id,
           MAX(a.status) as appointment_status,
           MAX(a.approval_notes) as ktt_rejection_notes,
           MAX(a.admin_approval_notes) as admin_rejection_notes,
           MAX(a.admin_approval_action) as admin_action,
           MAX(admin_user.full_name) as admin_reviewer_name,
           MAX(a.ktt1_approved_by) as ktt1_approved_by,
           MAX(a.ktt2_approved_by) as ktt2_approved_by,
           MAX(ktt1.full_name) as ktt1_name,
           MAX(ktt2.full_name) as ktt2_name,
           MAX(CASE WHEN ka.action = 'reject' THEN 1 ELSE 0 END) as has_ktt_rejection
    FROM employees e
    LEFT JOIN users u ON e.verified_by = u.id
    LEFT JOIN appointments a ON e.id = a.employee_id
    LEFT JOIN ktt_approvals ka ON a.id = ka.appointment_id
    LEFT JOIN users ktt1 ON a.ktt1_approved_by = ktt1.id
    LEFT JOIN users ktt2 ON a.ktt2_approved_by = ktt2.id
    LEFT JOIN users admin_user ON a.admin_approved_by = admin_user.id
    WHERE e.id = $employee_id 
    AND e.contractor_company = '" . $db->escapeString($company_name) . "'
    GROUP BY e.id
")->fetch_assoc();

// Get KTT rejection details if appointment exists and has KTT rejection
$ktt_rejectors = [];
if ($employee && $employee['appointment_id'] && $employee['has_ktt_rejection']) {
    $ktt1_id = $employee['ktt1_approved_by'] ? intval($employee['ktt1_approved_by']) : 0;
    $ktt2_id = $employee['ktt2_approved_by'] ? intval($employee['ktt2_approved_by']) : 0;
    
    $ktt_approvals = $db->query("
        SELECT ka.action, ka.approval_notes, ka.approval_date, u.full_name, u.role,
               CASE 
                   WHEN ka.ktt_user_id = $ktt1_id THEN 'KTT MSM'
                   WHEN ka.ktt_user_id = $ktt2_id THEN 'KTT TTN'
                   ELSE 'KTT'
               END as ktt_position
        FROM ktt_approvals ka
        LEFT JOIN users u ON ka.ktt_user_id = u.id
        WHERE ka.appointment_id = {$employee['appointment_id']}
        AND ka.action = 'reject'
        ORDER BY ka.approval_date ASC
    ");
    
    if ($ktt_approvals && $ktt_approvals->num_rows > 0) {
        while ($ktt_reject = $ktt_approvals->fetch_assoc()) {
            $ktt_rejectors[] = $ktt_reject;
        }
    }
}

if (!$employee) {
    header('Location: employees.php');
    exit();
}

// Check if employee can be re-submitted (rejected by admin OR KTT OR appointment rejected)
$can_resubmit = (
    $employee['verification_status'] == 'rejected' || 
    $employee['has_ktt_rejection'] == 1 ||
    $employee['appointment_status'] == 'rejected'
);

if (!$can_resubmit) {
    $_SESSION['error'] = 'This employee data has not been rejected, cannot be re-uploaded!';
    header('Location: employees.php');
    exit();
}

// Get certifications and positions for dropdown
$certifications = $db->query("SELECT * FROM certifications ORDER BY cert_name");
$certifications_data = [];
if ($certifications && $certifications->num_rows > 0) {
    $certifications->data_seek(0);
    while ($cert = $certifications->fetch_assoc()) {
        $certifications_data[$cert['id']] = $cert;
    }
}

// Check if competencies table exists and get competencies by type
$competencies_table_exists = false;
$check_table = $db->query("SHOW TABLES LIKE 'competencies'");
if ($check_table && $check_table->num_rows > 0) {
    $competencies_table_exists = true;
}

$competencies_by_type = [];
if ($competencies_table_exists) {
    $competencies_result = $db->query("SELECT * FROM competencies ORDER BY position_type, competency_name");
    while ($comp = $competencies_result->fetch_assoc()) {
        $type = $comp['position_type'];
        if (!isset($competencies_by_type[$type])) {
            $competencies_by_type[$type] = [];
        }
        $competencies_by_type[$type][] = $comp;
    }
}

// Get supervision areas from database
$supervision_areas = $db->query("SELECT * FROM supervision_areas ORDER BY area_name");

// Get existing certifications for this employee
$existing_certifications = $db->query("
    SELECT ec.*, c.cert_name
    FROM employee_certifications ec
    LEFT JOIN certifications c ON ec.certification_id = c.id
    WHERE ec.employee_id = $employee_id
    ORDER BY ec.id
");

// Handle form submission for re-submit
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    // 2. VALIDASI CSRF TOKEN
    if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        
        $error = 'Validasi keamanan gagal (CSRF Token tidak valid atau kadaluarsa). Silakan muat ulang halaman.';
        
    } else {
        
        // --- KODE ASLI ANDA DIJALANKAN JIKA CSRF VALID ---
        $full_name = $db->escapeString(trim($_POST['full_name']));
        $position = $db->escapeString(trim($_POST['position']));
        $department = $db->escapeString(trim($_POST['department']));
        $competency_type = $db->escapeString(trim($_POST['competency_type']));
        $competency_name = !empty($_POST['competency_name']) ? $db->escapeString(trim($_POST['competency_name'])) : ($employee['competency_name'] ?? '');
        $supervision_area = !empty($_POST['supervision_area']) ? $db->escapeString(trim($_POST['supervision_area'])) : '';
        $ruang_lingkup = $db->escapeString(trim($_POST['ruang_lingkup']));
        $sub_competency = !empty($_POST['sub_competency']) ? $db->escapeString(trim($_POST['sub_competency'])) : ($employee['sub_competency'] ?? '');
        $allowed_sub_competencies = ['Juru Las', 'Juru Ledak'];
        $requires_sub_competency = ($competency_type === 'tenaga_teknis' && in_array($competency_name, $allowed_sub_competencies, true));
        $contractor_company = $db->escapeString(trim($_POST['contractor_company']));

        // Validate required fields
        if (empty($full_name) || empty($position) || empty($department) || empty($competency_type) || empty($ruang_lingkup) || empty($contractor_company)) {
            $error = 'All fields are required!';
        } elseif ($competency_type == 'pengawas_operasional' && empty($supervision_area)) {
            $error = 'Supervision Area is required for Operational Supervisor!';
        } elseif (in_array($competency_type, ['pengawas_teknis', 'tenaga_teknis']) && empty($competency_name)) {
            $error = 'Competency is required for Technical Supervisor and Technical Personnel types!';
        } elseif ($requires_sub_competency && empty($sub_competency)) {
            $error = 'Sub Competency is required for this competency!';
        } else {
            // Handle CV upload (optional for re-submit, keep old if not provided)
            $cv_file = $employee['cv_file'];
            if (isset($_FILES['cv_file']) && $_FILES['cv_file']['error'] == 0) {
                $file_size = $_FILES['cv_file']['size'];
                $max_size = 5 * 1024 * 1024; // 5MB
                $file_extension = strtolower(pathinfo($_FILES['cv_file']['name'], PATHINFO_EXTENSION));
                $allowed_cv_extensions = ['pdf'];
                if (!in_array($file_extension, $allowed_cv_extensions)) {
                    $error = 'File type not allowed! Only PDF.';
                } elseif ($file_size > $max_size) {
                    $error = 'File size too large! Maximum 5MB.';
                } else {
                    $upload_dir = upload_physical_dir('cv');
                    
                    $new_filename = 'cv_' . $employee['employee_code'] . '_' . time() . '.' . $file_extension;
                    $upload_path = $upload_dir . $new_filename;
                    
                    if (move_uploaded_file($_FILES['cv_file']['tmp_name'], $upload_path)) {
                        // Delete old CV file if it exists
                        if ($cv_file) {
                            delete_upload($cv_file);
                        }
                        $cv_file = 'cv/' . $new_filename;
                    } else {
                        $error = 'Failed to upload CV file.';
                    }
                }
            }

            // Handle Statement Letter upload (optional for re-submit)
            $statement_file = $employee['statement_file'];
            if (isset($_FILES['statement_file']) && $_FILES['statement_file']['error'] == 0) {
                $stmt_file_size = $_FILES['statement_file']['size'];
                $stmt_max_size = 5 * 1024 * 1024; // 5MB
                $stmt_file_extension = strtolower(pathinfo($_FILES['statement_file']['name'], PATHINFO_EXTENSION));

                if ($stmt_file_extension !== 'pdf') {
                    $error = 'Statement Letter must be in PDF format!';
                } elseif ($stmt_file_size > $stmt_max_size) {
                    $error = 'Statement Letter file size too large! Maximum 5MB.';
                } else {
                    $stmt_upload_dir = upload_physical_dir('statements');
                    
                    $stmt_new_filename = 'statement_' . $employee['employee_code'] . '_' . time() . '.pdf';
                    $stmt_upload_path = $stmt_upload_dir . $stmt_new_filename;
                    
                    if (move_uploaded_file($_FILES['statement_file']['tmp_name'], $stmt_upload_path)) {
                        // Delete old statement file if it exists
                        if ($statement_file) {
                            delete_upload($statement_file);
                        }
                        $statement_file = 'statements/' . $stmt_new_filename;
                    } else {
                        $error = 'Failed to upload Statement Letter file.';
                    }
                }
            }
            
            // Only proceed with update if no errors
            if (!$error) {
                // Cek struktur tabel employees terlebih dahulu
                $columns_result = $db->query("SHOW COLUMNS FROM employees");
                $available_columns = [];
                while ($col = $columns_result->fetch_assoc()) {
                    $available_columns[] = $col['Field'];
                }
                
                // Build UPDATE query
                $update_fields = [
                    "full_name = '$full_name'",
                    "position = '$position'",
                    "department = '$department'",
                    "competency_type = '$competency_type'",
                    "contractor_company = '$contractor_company'",
                    "ruang_lingkup = '$ruang_lingkup'",
                    "cv_file = '$cv_file'",
                    "verification_status = 'pending'",
                    "verified_by = NULL",
                    "verified_date = NULL",
                    "verification_notes = NULL"
                ];

                // Add optional fields
                if (in_array('competency_name', $available_columns) && !empty($competency_name)) {
                    $update_fields[] = "competency_name = '$competency_name'";
                }

                if (in_array('supervision_area', $available_columns) && !empty($supervision_area)) {
                    $update_fields[] = "supervision_area = '$supervision_area'";
                }

                if (in_array('sub_competency', $available_columns) && !empty($sub_competency)) {
                    $update_fields[] = "sub_competency = '$sub_competency'";
                }

                if (in_array('statement_file', $available_columns)) {
                    $update_fields[] = "statement_file = '$statement_file'";
                }

                // Add resubmit_count increment with NULL handling
                if (in_array('resubmit_count', $available_columns)) {
                    $update_fields[] = "resubmit_count = COALESCE(resubmit_count, 0) + 1";
                }

                // Add resubmit_date
                if (in_array('resubmit_date', $available_columns)) {
                    $update_fields[] = "resubmit_date = NOW()";
                }

                $sql = "UPDATE employees SET " . implode(', ', $update_fields) . " WHERE id = $employee_id";

                // Debug logging for resubmit_count
                error_log("User Resubmit - Employee ID: $employee_id, SQL: $sql");

                if ($db->query($sql)) {
                    // Update appointment status back to pending for admin re-review
                    if (!empty($employee['appointment_id'])) {
                        $appointment_id = intval($employee['appointment_id']);
                        $update_appointment_sql = "UPDATE appointments SET 
                            status = 'pending',
                            admin_approval_action = NULL,
                            admin_approval_notes = NULL
                            WHERE id = $appointment_id";
                        $db->query($update_appointment_sql);
                    }
                    
                    // Handle certification updates/additions
                    $upload_dir = upload_physical_dir('certifications');
                    
                    $cert_ids = $_POST['certification_ids'] ?? [];
                    $cert_numbers = $_POST['cert_numbers'] ?? [];
                    $cert_issuers = $_POST['cert_issuers'] ?? [];
                    $issue_dates = $_POST['issue_dates'] ?? [];
                    $expiry_dates = $_POST['expiry_dates'] ?? [];
                    $expiry_reasons = $_POST['expiry_reasons'] ?? [];
                    $existing_cert_ids = $_POST['existing_cert_ids'] ?? [];
                    
                    foreach ($cert_ids as $key => $cert_id) {
                        if (empty($cert_id)) continue;
                        
                        $cert_id = intval($cert_id);
                        $cert_number = $db->escapeString($cert_numbers[$key] ?? '');
                        $cert_issuer = $db->escapeString($cert_issuers[$key] ?? '');
                        $issue_date = $db->escapeString($issue_dates[$key] ?? '');
                        $expiry_date = $db->escapeString($expiry_dates[$key] ?? '');
                        $reason = $db->escapeString($expiry_reasons[$key] ?? '');
                        $existing_id = isset($existing_cert_ids[$key]) ? intval($existing_cert_ids[$key]) : 0;
                        
                        $today = date('Y-m-d');
                        $status = ($expiry_date && $expiry_date < $today) ? 'expired' : 'pending';
                        
                        $cert_path = null;
                        if (isset($_FILES['certifications']['tmp_name'][$key]) &&
                            $_FILES['certifications']['error'][$key] == 0 &&
                            !empty($_FILES['certifications']['tmp_name'][$key])) {

                            $file_ext = strtolower(pathinfo($_FILES['certifications']['name'][$key], PATHINFO_EXTENSION));

                            if ($file_ext !== 'pdf') {
                                $error = 'Certificate file must be in PDF format!';
                                break;
                            }

                            $cert_file = $employee['employee_code'] . '_cert_' . $key . '_' . time() . '.' . $file_ext;
                            
                            if (move_uploaded_file($_FILES['certifications']['tmp_name'][$key], $upload_dir . $cert_file)) {
                                $cert_path = 'certifications/' . $cert_file;
                            }
                        }
                        
                        if ($existing_id > 0) {
                            $update_parts = [
                                "certification_id = $cert_id",
                                "cert_number = '$cert_number'",
                                "cert_issuer = '$cert_issuer'",
                                "issue_date = '$issue_date'",
                                "expiry_date = '$expiry_date'",
                                "status = '$status'",
                                "verification_status = 'pending'",
                                "verified_by = NULL",
                                "verified_date = NULL",
                                "expiry_reason = '$reason'"
                            ];
                            
                            if ($cert_path) {
                                $update_parts[] = "document_file = '$cert_path'";
                            }
                            
                            $sql_cert = "UPDATE employee_certifications SET " . implode(', ', $update_parts) . 
                                        " WHERE id = $existing_id AND employee_id = $employee_id";
                        } else {
                            if ($cert_path) {
                                $sql_cert = "INSERT INTO employee_certifications 
                                            (employee_id, certification_id, cert_number, cert_issuer, issue_date, expiry_date, 
                                             document_file, status, verification_status, expiry_reason) 
                                            VALUES ($employee_id, $cert_id, '$cert_number', '$cert_issuer', '$issue_date', '$expiry_date', 
                                                    '$cert_path', '$status', 'pending', '$reason')";
                            }
                        }
                        
                        if (isset($sql_cert) && !$db->query($sql_cert)) {
                            error_log("Error updating/inserting certification: " . $db->getConnection()->error);
                        }
                    }

                    error_log("RESUBMIT DEBUG - Employee ID: $employee_id");

                    $existing_appointment = $db->query("
                        SELECT id, appointment_number, requires_ktt_msm_review, requires_ktt_ttn_review
                        FROM appointments
                        WHERE employee_id = $employee_id
                        ORDER BY id DESC
                        LIMIT 1
                    ")->fetch_assoc();

                    if ($existing_appointment) {
                        $appointment_id = $existing_appointment['id'];
                        $is_ktt_resubmit = ($existing_appointment['requires_ktt_msm_review'] == 1 || $existing_appointment['requires_ktt_ttn_review'] == 1);

                        $cert_expiry = $db->query("
                            SELECT MIN(expiry_date) as earliest_expiry
                            FROM employee_certifications
                            WHERE employee_id = $employee_id
                            AND expiry_date IS NOT NULL
                        ")->fetch_assoc();

                        $expiry_date = $cert_expiry['earliest_expiry'] ?? null;

                        $update_parts = [];
                        if ($expiry_date) {
                            $update_parts[] = "expiry_date = '$expiry_date'";
                        } else {
                            $update_parts[] = "expiry_date = NULL";
                        }

                        $update_parts[] = "status = 'draft'";
                        $update_parts[] = "updated_at = NOW()";

                        if ($is_ktt_resubmit) {
                            if ($existing_appointment['requires_ktt_msm_review'] == 1) {
                                $update_parts[] = "ktt_msm_status = 'pending'";
                            }
                            if ($existing_appointment['requires_ktt_ttn_review'] == 1) {
                                $update_parts[] = "ktt_ttn_status = 'pending'";
                            }
                        }

                        $update_sql = "UPDATE appointments SET " . implode(', ', $update_parts) . " WHERE id = $appointment_id";

                        if ($db->query($update_sql)) {
                            $db->query("DELETE FROM ktt_approvals WHERE appointment_id = $appointment_id");
                        }
                        $message = 'Data correction successfully uploaded!';
                    } else {
                        $message = 'Data correction successfully uploaded!';
                    }

                    // Included via bootstrap/app.php
                    try {
                        set_time_limit(60);
                        $notificationService = new NotificationService();
                        $notificationService->notifyNewEmployeeAdded($employee_id, $company_name);
                    } catch (Exception $e) {
                        error_log("Notification error: " . $e->getMessage());
                    }

                    header("refresh:3;url=employees.php");
                } else {
                    $error = 'Failed to upload employee correction!';
                }
            }
        }
    } // Akhir dari else CSRF
}

require_once dirname(__DIR__) . '/layouts/header.php';


?>

<div class="add-employee-container">
    <!-- Page Header -->
    <div class="page-header-add">
        <div class="header-left">
            <h2><i class="fas fa-upload"></i> <span data-lang="upload-employee-correction">Upload Employee Correction</span></h2>
            <p data-lang="fix-rejected-data-reupload">Fix rejected data and re-upload for verification</p>
        </div>
        <a href="employees.php" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left"></i> <span data-lang="back">Back</span>
        </a>
    </div>
    
    <?php if ($employee['verification_notes'] || !empty($ktt_rejectors) || $employee['admin_rejection_notes']): ?>
    <div class="alert alert-warning alert-custom">
        <i class="fas fa-exclamation-triangle"></i>
        <div>
            <strong data-lang="rejection-reason">Rejection Reason:</strong>
            
            <?php if ($employee['admin_rejection_notes'] && $employee['admin_action'] == 'send_to_user'): ?>
            <div class="rejection-section">
                <p><strong data-lang="from-admin-review-ktt-rejection">From Admin (KTT Rejection Review):</strong></p>
                <p><?php echo nl2br(htmlspecialchars($employee['admin_rejection_notes'])); ?></p>
                <?php if ($employee['admin_reviewer_name']): ?>
                <small><span data-lang="reviewed-by">Reviewed by:</span> <strong><?php echo htmlspecialchars($employee['admin_reviewer_name']); ?></strong></small>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            
            <?php if ($employee['verification_notes']): ?>
            <div class="rejection-section" style="<?php echo $employee['admin_rejection_notes'] ? 'margin-top: 15px; padding-top: 15px; border-top: 1px solid rgba(0,0,0,0.1);' : ''; ?>">
                <p><strong data-lang="from-admin">From Admin:</strong></p>
                <p><?php echo nl2br(htmlspecialchars($employee['verification_notes'])); ?></p>
                <?php if ($employee['verified_by_name']): ?>
                <small><span data-lang="rejected-by">Rejected by:</span> <strong><?php echo htmlspecialchars($employee['verified_by_name']); ?></strong>
                <?php if ($employee['verified_date']): ?>
                <span data-lang="on">on</span> <?php echo date('d/m/Y H:i', strtotime($employee['verified_date'])); ?>
                <?php endif; ?>
                </small>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($ktt_rejectors)): ?>
            <div class="rejection-section" style="margin-top: 15px; padding-top: 15px; border-top: 1px solid rgba(0,0,0,0.1);">
                <p><strong data-lang="from-ktt">From KTT:</strong></p>
                <?php foreach ($ktt_rejectors as $index => $rejector): ?>
                <div class="ktt-rejection-item" style="<?php echo $index > 0 ? 'margin-top: 12px; padding-top: 12px; border-top: 1px dashed rgba(0,0,0,0.1);' : ''; ?>">
                    <p style="margin-bottom: 8px;"><?php echo nl2br(htmlspecialchars($rejector['approval_notes'])); ?></p>
                    <small style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                        <span style="background: #fee2e2; color: #dc2626; padding: 3px 8px; border-radius: 4px; font-weight: 700; font-size: 10px;">
                            <?php echo htmlspecialchars($rejector['ktt_position']); ?>
                        </span>
                        <span style="color: #666;"></span>
                        <strong style="color: #333;"><?php echo htmlspecialchars($rejector['full_name']); ?></strong>
                        <span style="color: #666;"></span>
                        <span style="color: #999;"><?php echo date('d/m/Y H:i', strtotime($rejector['approval_date'])); ?></span>
                    </small>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
    
    <?php if ($message): ?>
    <div class="alert alert-success alert-custom">
        <i class="fas fa-check-circle"></i>
        <div>
            <strong data-lang="success">Success!</strong>
            <p><?php echo htmlspecialchars($message); ?></p>
        </div>
    </div>
    <?php endif; ?>
    
    <?php if ($error): ?>
    <div class="alert alert-error alert-custom">
        <i class="fas fa-exclamation-circle"></i>
        <div>
            <strong data-lang="error">Error!</strong>
            <p><?php echo htmlspecialchars($error); ?></p>
        </div>
    </div>
    <?php endif; ?>
    
    <form method="POST" action="" enctype="multipart/form-data" class="form-container">
        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
        <!-- Section 1: Identity & Competency Data -->
        <div class="form-section">
            <div class="section-header">
                <h3><i class="fas fa-id-card"></i> <span data-lang="identity-competency-data">Identity & Competency Data</span></h3>
                <span class="section-number">1</span>
            </div>
            
            <div class="form-row">
                <div class="form-group col-lg-6">
                    <label for="employee_code" data-lang="id-badge-required">ID BADGE <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="employee_code" name="employee_code" 
                           value="<?php echo htmlspecialchars($employee['employee_code']); ?>"
                           readonly style="background-color: #F9FAFB;">
                    <small class="form-hint" data-lang="id-badge-cannot-be-changed">ID BADGE cannot be changed</small>
                </div>
                
                <div class="form-group col-lg-6">
                    <label for="full_name" data-lang="full-name-required">Full Name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="full_name" name="full_name"
                           value="<?php echo htmlspecialchars(isset($_POST['full_name']) ? $_POST['full_name'] : $employee['full_name']); ?>"
                           required placeholder="Employee full name" data-lang-placeholder="employee-full-name-placeholder">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group col-lg-6">
                    <label for="position" data-lang="position-required">Position <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="position" name="position"
                           value="<?php echo htmlspecialchars(isset($_POST['position']) ? $_POST['position'] : $employee['position']); ?>"
                           required placeholder="Example: Rigger, HSE Superintendent" data-lang-placeholder="position-example-placeholder">
                </div>
                <!-- Department disembunyikan, menggunakan nilai yang sudah ada -->
                <input type="hidden" id="department" name="department" value="<?php echo htmlspecialchars($employee['department']); ?>">
            </div>
            
            <div class="form-row">
                <div class="form-group col-lg-6">
                    <label for="ruang_lingkup" data-lang="scope-of-work-required">Scope of Work <span class="text-danger">*</span></label>
                    <select class="form-control" id="ruang_lingkup" name="ruang_lingkup" required>
                        <option value="" data-lang="select-scope-of-work">-- Select Scope of Work --</option>
                        <?php $stored_rl = isset($_POST['ruang_lingkup']) ? $_POST['ruang_lingkup'] : ($employee['ruang_lingkup'] ?? ''); ?>
                        <option value="PT Meares Soputan Mining (MSM)" data-lang="scope-of-work-msm" <?php echo ($stored_rl == 'PT Meares Soputan Mining (MSM)' || stripos($stored_rl, 'MSM') !== false) ? 'selected' : ''; ?>>PT Meares Soputan Mining</option>
                        <option value="PT Tambang Tondano Nusajaya (TTN)" data-lang="scope-of-work-ttn" <?php echo ($stored_rl == 'PT Tambang Tondano Nusajaya (TTN)' || stripos($stored_rl, 'TTN') !== false) ? 'selected' : ''; ?>>PT Tambang Tondano Nusajaya</option>
                    </select>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group col-lg-6">
                    <label for="competency_type" data-lang="competency-type-required">Competency Type <span class="text-danger">*</span></label>
                    <?php $comp_type = isset($_POST['competency_type']) ? $_POST['competency_type'] : $employee['competency_type']; ?>
                    <select class="form-control" id="competency_type" name="competency_type" onchange="toggleCompetencyField()" required>
                        <option value="" data-lang="select-competency-type">-- Select Competency Type --</option>
                        <option value="pengawas_operasional" data-lang="operational-supervisor" <?php echo ($comp_type == 'pengawas_operasional') ? 'selected' : ''; ?>>Operational Supervisor</option>
                        <option value="pengawas_teknis" data-lang="technical-supervisor" <?php echo ($comp_type == 'pengawas_teknis') ? 'selected' : ''; ?>>Technical Supervisor</option>
                        <option value="tenaga_teknis" data-lang="technical-personnel" <?php echo ($comp_type == 'tenaga_teknis') ? 'selected' : ''; ?>>Technical Personnel</option>
                    </select>
                </div>

                <div class="form-group col-lg-6" id="supervision_area_group" style="display: none;">
                    <label for="supervision_area" data-lang="supervision-area-required">Supervision Area <span class="text-danger">*</span></label>
                    <select class="form-control" id="supervision_area" name="supervision_area">
                        <option value="" data-lang="select-supervision-area">-- Select Supervision Area --</option>
                        <?php
                        $sup_area = isset($_POST['supervision_area']) ? $_POST['supervision_area'] : $employee['supervision_area'];
                        if ($supervision_areas && $supervision_areas->num_rows > 0) {
                            $supervision_areas->data_seek(0);
                            while ($area = $supervision_areas->fetch_assoc()):
                                $selected = ($sup_area == $area['area_name']) ? 'selected' : '';
                        ?>
                        <option value="<?php echo htmlspecialchars($area['area_name']); ?>" <?php echo $selected; ?>>
                            <?php echo htmlspecialchars($area['area_name']); ?>
                        </option>
                        <?php
                            endwhile;
                        }
                        ?>
                    </select>
                </div>

                <div class="form-group col-lg-6" id="competency_group" style="display: none;">
                    <label for="competency_name">Competency <span class="text-danger" id="competency_required">*</span></label>
                    <?php $comp_name = trim(isset($_POST['competency_name']) ? $_POST['competency_name'] : ($employee['competency_name'] ?? '')); ?>
                    <select class="form-control" id="competency_name" name="competency_name" onchange="loadSubCompetencies()" data-initial-value="<?php echo htmlspecialchars($comp_name); ?>">
                        <option value="" data-lang="select-competency">-- Select Competency --</option>
                        <?php
                        // Populate competencies for pengawas_teknis
                        if (!empty($competencies_by_type['pengawas_teknis'])) {
                            foreach ($competencies_by_type['pengawas_teknis'] as $comp):
                                $option_name = trim($comp['competency_name']);
                                $selected = ($comp_name === $option_name) ? 'selected' : '';
                        ?>
                            <option value="<?php echo htmlspecialchars($option_name); ?>" data-id="<?php echo htmlspecialchars($comp['id']); ?>" data-type="pengawas_teknis" <?php echo $selected; ?>>
                                <?php echo htmlspecialchars($option_name); ?>
                            </option>
                        <?php
                            endforeach;
                        }
                        // Populate competencies for tenaga_teknis
                        if (!empty($competencies_by_type['tenaga_teknis'])) {
                            foreach ($competencies_by_type['tenaga_teknis'] as $comp):
                                $option_name = trim($comp['competency_name']);
                                $selected = ($comp_name === $option_name) ? 'selected' : '';
                        ?>
                            <option value="<?php echo htmlspecialchars($option_name); ?>" data-id="<?php echo htmlspecialchars($comp['id']); ?>" data-type="tenaga_teknis" <?php echo $selected; ?>>
                                <?php echo htmlspecialchars($option_name); ?>
                            </option>
                        <?php
                            endforeach;
                        }
                        ?>
                    </select>
                </div>

                <div class="form-group col-lg-6" id="sub_competency_group" style="display: none;">
                    <label for="sub_competency">Sub Competency <span class="text-danger">*</span></label>
                    <?php $sub_comp = isset($_POST['sub_competency']) ? $_POST['sub_competency'] : (isset($employee['sub_competency']) ? $employee['sub_competency'] : ''); ?>
                    <select class="form-control" id="sub_competency" name="sub_competency" data-initial-value="<?php echo htmlspecialchars($sub_comp); ?>">
                        <option value="" data-lang="select-sub-competency">-- Select Sub Competency --</option>
                    </select>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group col-lg-6">
                    <label for="contractor_company" data-lang="company-required">Company <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="contractor_company" name="contractor_company"
                           value="<?php echo htmlspecialchars($employee['contractor_company']); ?>"
                           required readonly style="background-color: #F9FAFB;">
                    <small class="form-hint" data-lang="company-cannot-be-changed">Company cannot be changed</small>
                </div>
            </div>
            
            <div class="form-group">
                <label for="cv_file"><span data-lang="upload-cv">Upload CV</span> <span class="text-muted" data-lang="optional-leave-blank-no-change">(Optional - leave blank if no changes needed)</span></label>
                <?php if ($employee['cv_file']): ?>
                <div class="current-file-info">
                    <i class="fas fa-file-pdf"></i>
                    <span><span data-lang="current-file">Current file:</span> <a href="<?php echo upload_url(htmlspecialchars($employee['cv_file'])); ?>" target="_blank" data-lang="view-cv">View CV</a></span>
                </div>
                <?php endif; ?>
                <div class="file-upload-area">
                    <i class="fas fa-file-upload"></i>
                    <input type="file" name="cv_file" id="cv_file" class="file-input" accept=".pdf">
                    <span class="file-text" data-lang="click-or-drag-new-cv-file">Click or drag new CV file<br>(PDF, Max 5MB)</span>
                    <span class="file-name"></span>
                </div>
            </div>

            <div class="form-group">
                <label for="statement_file"><span data-lang="upload-statement-letter">Upload Statement Letter</span> <span class="text-muted" data-lang="optional-leave-blank-no-change">(Optional - leave blank if no changes needed)</span></label>
                <?php if ($employee['statement_file']): ?>
                <div class="current-file-info">
                    <i class="fas fa-file-signature"></i>
                    <span><span data-lang="current-file">Current file:</span> <a href="<?php echo upload_url(htmlspecialchars($employee['statement_file'])); ?>" target="_blank" data-lang="view-statement-letter">View Statement Letter</a></span>
                </div>
                <?php endif; ?>
                <div class="file-upload-area">
                    <i class="fas fa-file-signature"></i>
                    <input type="file" name="statement_file" id="statement_file" class="file-input" accept=".pdf">
                    <span class="file-text" data-lang="click-or-drag-new-statement-letter-file">Click or drag new statement letter file<br>(PDF, Max 5MB)</span>
                    <span class="file-name"></span>
                </div>
                <small class="form-hint"><i class="fas fa-info-circle"></i> <span data-lang="wet-signature-pdf-instruction">Statement letter must be signed with wet signature (original) and scanned in PDF format</span></small>
            </div>

            <div class="alert alert-warning-custom" style="margin-bottom: 0;">
                <i class="fas fa-exclamation-triangle"></i>
                <div>
                    <strong data-lang="important-statement-letter">Important - Statement Letter:</strong>
                    <p style="margin-bottom: 8px;" data-lang="statement-letter-original-signature-note">The statement letter must be signed with an<strong>original wet signature</strong> by the concerned party, then scanned in PDF format.</p>
                    <a href="https://drive.google.com/drive/folders/1z_LkU7C0bgz5VnVKyZBmmbP8mUuZGr06?usp=sharing" class="btn btn-info btn-sm" target="_blank" style="margin-top: 5px;">
                        <i class="fas fa-download"></i> <span data-lang="download-statement-letter-template">Download Statement Letter Template</span>
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Section 2: Certification -->
        <div class="form-section">
            <div class="section-header">
                <h3><i class="fas fa-certificate"></i> <span data-lang="certifications">Certifications</span></h3>
                <span class="section-number">2</span>
            </div>

            <div class="alert alert-info-custom">
                <i class="fas fa-info-circle"></i>
                <strong data-lang="important-information">Important Information:</strong> <span data-lang="resubmit-file-upload-optional-info">File uploads are OPTIONAL. You don't need to re-upload CV, signature, or certificate files if the existing data is correct. Existing files will continue to be used.</span> <strong data-lang="reupload-only-if">Re-upload only if:</strong> <span data-lang="resubmit-reupload-condition">Admin specified in rejection notes that certain files need to be corrected/replaced.</span>
            </div>
            
            <div id="certificationContainer" class="certifications-list">
                <?php 
                $cert_index = 0;
                if ($existing_certifications && $existing_certifications->num_rows > 0):
                    while ($cert = $existing_certifications->fetch_assoc()): 
                        $cert_index++;
                ?>
                <div class="certification-item">
                    <div class="cert-item-header">
                        <h5><i class="fas fa-file-certificate"></i> <span data-lang="certification">Certification</span> #<?php echo $cert_index; ?></h5>
                        <div class="cert-header-actions">
                            <span class="badge badge-<?php echo $cert['verification_status'] == 'rejected' ? 'danger' : 'warning'; ?>">
                                <?php echo strtoupper($cert['verification_status']); ?>
                            </span>
                            <?php if ($cert_index > 1): ?>
                            <button type="button" class="btn-remove-cert" onclick="removeCertification(this)" title="Remove this certification" data-lang-title="remove-this-certification">
                                <i class="fas fa-times"></i>
                            </button>
                            <?php endif; ?>
                        </div>
                    </div>

                    <input type="hidden" name="existing_cert_ids[]" value="<?php echo $cert['id']; ?>">

                    <div class="form-row">
                        <div class="form-group col-lg-4">
                            <label data-lang="certification-name-required">Certification Name <span class="text-danger">*</span></label>
                            <select name="certification_ids[]" class="form-control cert-name-select" required onchange="updateIssuer(this)">
                                <option value="" data-lang="select-certification">-- Select Certification --</option>
                                <?php
                                if ($certifications && $certifications->num_rows > 0) {
                                    $certifications->data_seek(0);
                                    while ($c = $certifications->fetch_assoc()):
                                        $selected = ($cert['certification_id'] == $c['id']) ? 'selected' : '';
                                    ?>
                                    <option value="<?php echo $c['id']; ?>" data-issuer="<?php echo htmlspecialchars($c['cert_issuer'] ?? ''); ?>" <?php echo $selected; ?>>
                                        <?php echo htmlspecialchars($c['cert_name']); ?>
                                    </option>
                                    <?php 
                                    endwhile;
                                }
                                ?>
                            </select>
                        </div>
                        
                        <div class="form-group col-lg-4">
                            <label data-lang="certificate-number-required">Certificate Number <span class="text-danger">*</span></label>
                            <input type="text" name="cert_numbers[]" class="form-control" required placeholder="Certificate number" data-lang-placeholder="certificate-number-placeholder" value="<?php echo htmlspecialchars($cert['cert_number']); ?>">
                        </div>
                        
                        <div class="form-group col-lg-4">
                            <label data-lang="issuer-required">Issuer <span class="text-danger">*</span></label>
                            <input type="text" name="cert_issuers[]" class="form-control" required placeholder="Issuer name" data-lang-placeholder="issuer-name-placeholder" value="<?php echo htmlspecialchars($cert['cert_issuer']); ?>">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group col-lg-6">
                            <label data-lang="issue-date-required">Issue Date <span class="text-danger">*</span></label>
                            <input type="date" name="issue_dates[]" class="form-control issue-date" required onchange="calculateExpiryDate(this)" value="<?php echo $cert['issue_date']; ?>">
                        </div>
                        <div class="form-group col-lg-6">
                            <label data-lang="validity-period-required">Validity Period <span class="text-danger">*</span></label>
                            <div class="validity-input-group">
                                <input type="number" name="validity_years[]" class="form-control validity-years" min="0" step="0.5" placeholder="Years" data-lang-placeholder="years" onchange="calculateExpiryDate(this)" value="<?php 
                                    // Calculate validity years from issue and expiry dates
                                    if (!empty($cert['issue_date']) && !empty($cert['expiry_date'])) {
                                        $issue = new DateTime($cert['issue_date']);
                                        $expiry = new DateTime($cert['expiry_date']);
                                        $diff = $issue->diff($expiry);
                                        $years = $diff->y + ($diff->m / 12);
                                        echo round($years, 1);
                                    } else {
                                        echo '3';
                                    }
                                ?>">
                                <label class="checkbox-label">
                                    <input type="checkbox" name="no_expiry[]" class="no-expiry-check" onchange="toggleExpiryField(this)">
                                    <span data-lang="no-expiry">No Expiry</span>
                                </label>
                            </div>
                            <small class="form-hint" data-lang="validity-years-hint">Enter in years, e.g.: 3 or 2.5 for 2 years 6 months</small>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group col-lg-6">
                            <label data-lang="expiry-date-required">Expiry Date <span class="text-danger">*</span></label>
                            <input type="date" name="expiry_dates[]" class="form-control expiry-date" required value="<?php echo $cert['expiry_date']; ?>">
                            <small class="form-hint" data-lang="expiry-date-manual-edit-note">You can manually edit the expiry date if needed</small>
                        </div>
                        <div class="form-group col-lg-6">
                            <label><span data-lang="no-expiry-reason">No Expiry Reason</span> <span class="text-muted" data-lang="optional">(Optional)</span></label>
                            <input type="text" name="expiry_reasons[]" class="form-control other-expiry-reason" style="display: none;" placeholder="Example: Lifetime Certificate" data-lang-placeholder="lifetime-certificate-example">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label><span data-lang="upload-new-certificate-file">Upload New Certificate File</span> <span class="text-muted" data-lang="optional-leave-blank-if-no-change">(Optional - Leave blank if no changes)</span></label>
                        <?php if ($cert['document_file']): ?>
                        <div class="current-file-info">
                            <i class="fas fa-file-pdf"></i>
                            <span><span data-lang="current-file">Current file:</span> <a href="<?php echo upload_url(htmlspecialchars($cert['document_file'])); ?>" target="_blank" data-lang="view-certificate">View Certificate</a></span>
                        </div>
                        <?php endif; ?>
                        <div class="file-upload-area">
                            <i class="fas fa-file-pdf"></i>
                            <input type="file" name="certifications[]" class="file-input" accept=".pdf">
                            <span class="file-text" data-lang="click-or-drag-new-certificate-file">Click or drag new certificate file<br>(PDF, Max 5MB)</span>
                            <span class="file-name"></span>
                        </div>
                    </div>
                </div>
                <?php
                    endwhile;
                endif;
                ?>
            </div>

            <button type="button" class="btn btn-outline-primary" onclick="addCertification()">
                <i class="fas fa-plus-circle"></i> <span data-lang="add-another-certification">Add Another Certification</span>
            </button>
        </div>

        <!-- Info Alert -->
        <div class="alert alert-warning alert-custom">
            <i class="fas fa-exclamation-triangle"></i>
            <div>
                <strong data-lang="important-note">Important Note</strong>
                <p><span data-lang="after-resubmit-status-pending-note">After uploading corrections, the status will return to "Pending" and await re-verification from Admin.</span>
                <?php if (!empty($employee['appointment_number'])): ?>
                <br><strong><span data-lang="appointment-letter-number-will-remain">Appointment Letter Number</span> (<?php echo htmlspecialchars($employee['appointment_number']); ?>) <span data-lang="will-remain-the-same">will remain the same.</span></strong>
                <?php endif; ?>
                </p>
            </div>
        </div>
        
        <!-- Form Actions -->
        <div class="form-actions">
            <button type="submit" class="btn btn-primary btn-lg">
                <i class="fas fa-upload"></i> <span data-lang="upload-correction">Upload Correction</span>
            </button>
            <a href="employees.php" class="btn btn-secondary btn-lg">
                <i class="fas fa-times"></i> <span data-lang="cancel">Cancel</span>
            </a>
        </div>
    </form>
</div>

<script>
// Data sertifikasi dari server
const certificationsData = <?php echo json_encode($certifications_data); ?>;
const initialCompetencyName = <?php echo json_encode(isset($_POST['competency_name']) ? $_POST['competency_name'] : ($employee['competency_name'] ?? '')); ?>;
const initialSubCompetency = <?php echo json_encode(isset($_POST['sub_competency']) ? $_POST['sub_competency'] : (isset($employee['sub_competency']) ? $employee['sub_competency'] : '')); ?>;

async function loadSubCompetencies() {
    const competencySelect = document.getElementById('competency_name');
    const subCompetencySelect = document.getElementById('sub_competency');
    const selectedOption = competencySelect.options[competencySelect.selectedIndex];

    subCompetencySelect.innerHTML = '<option value="" data-lang="select-sub-competency">-- Select Sub Competency --</option>';

    if (!selectedOption || !selectedOption.value) {
        toggleSubCompetency();
        return;
    }

    const competencyId = selectedOption.getAttribute('data-id');

    if (!competencyId) {
        toggleSubCompetency();
        return;
    }

    try {
        const response = await fetch('../../api/get_sub_competencies.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                competency_id: parseInt(competencyId)
            })
        });

        const result = await response.json();

        if (result.success && result.data.length > 0) {
            result.data.forEach(subComp => {
                const option = document.createElement('option');
                option.value = subComp.name;
                option.textContent = subComp.name;
                option.title = subComp.description || '';
                
                // Check if this option matches the initial/saved value
                if (initialSubCompetency && subComp.name === initialSubCompetency) {
                    option.selected = true;
                }
                
                subCompetencySelect.appendChild(option);
            });
            
            // Set the select value to initialSubCompetency if available
            if (initialSubCompetency) {
                subCompetencySelect.value = initialSubCompetency;
            }
        }
    } catch (error) {
        console.error('Error loading sub-competencies:', error);
    }

    toggleSubCompetency();
}

function toggleCompetencyField() {
    const competencyType = document.getElementById('competency_type').value;
    const supervisionAreaGroup = document.getElementById('supervision_area_group');
    const competencyGroup = document.getElementById('competency_group');
    const subCompetencyGroup = document.getElementById('sub_competency_group');
    const competencyInput = document.getElementById('competency_name');
    const supervisionAreaInput = document.getElementById('supervision_area');
    const subCompetencyInput = document.getElementById('sub_competency');

    subCompetencyGroup.style.display = 'none';
    subCompetencyInput.removeAttribute('required');

    if (competencyType === 'pengawas_operasional') {
        supervisionAreaGroup.style.display = 'block';
        competencyGroup.style.display = 'none';
        competencyInput.removeAttribute('required');
        supervisionAreaInput.setAttribute('required', 'required');
    } else if (competencyType === 'pengawas_teknis' || competencyType === 'tenaga_teknis') {
        supervisionAreaGroup.style.display = 'none';
        competencyGroup.style.display = 'block';
        competencyInput.setAttribute('required', 'required');
        supervisionAreaInput.removeAttribute('required');
        filterCompetencies(competencyType);

        if (competencyType === 'tenaga_teknis') {
            loadSubCompetencies();
        }
    } else {
        supervisionAreaGroup.style.display = 'none';
        competencyGroup.style.display = 'none';
        competencyInput.removeAttribute('required');
        supervisionAreaInput.removeAttribute('required');
    }
}

// Function to toggle Sub Competency field for allowed Tenaga Teknis competencies
function toggleSubCompetency() {
    const competencyType = document.getElementById('competency_type').value;
    const competencyInput = document.getElementById('competency_name');
    const subCompetencyGroup = document.getElementById('sub_competency_group');
    const subCompetencyInput = document.getElementById('sub_competency');

    const ALLOWED_COMPETENCIES_WITH_SUB = ['Juru Las', 'Juru Ledak'];
    const selectedCompetency = competencyInput.value.trim();

    if (competencyType === 'tenaga_teknis' && selectedCompetency !== '' && ALLOWED_COMPETENCIES_WITH_SUB.includes(selectedCompetency)) {
        subCompetencyGroup.style.display = 'block';
        subCompetencyInput.setAttribute('required', 'required');
    } else {
        subCompetencyGroup.style.display = 'none';
        subCompetencyInput.removeAttribute('required');
        subCompetencyInput.value = '';
    }
}

function filterCompetencies(competencyType) {
    const competencySelect = document.getElementById('competency_name');
    const options = competencySelect.querySelectorAll('option');
    const currentValue = competencySelect.value;

    options.forEach(option => {
        if (option.value === '') {
            option.style.display = 'block';
        } else if (competencyType === 'pengawas_teknis') {
            const optionType = option.getAttribute('data-type');
            if (optionType === 'pengawas_teknis' || optionType === 'tenaga_teknis' || option.value === currentValue) {
                option.style.display = 'block';
            } else {
                option.style.display = 'none';
            }
        } else if (option.getAttribute('data-type') === competencyType || option.value === currentValue) {
            option.style.display = 'block';
        } else {
            option.style.display = 'none';
        }
    });
}

document.addEventListener('DOMContentLoaded', function() {
    const competencyTypeSelect = document.getElementById('competency_type');
    const competencyNameSelect = document.getElementById('competency_name');
    
    if (competencyTypeSelect.value) {
        if (initialCompetencyName) {
            competencyNameSelect.value = initialCompetencyName;
        }

        // Trigger competency field display logic
        toggleCompetencyField();
        
        // If competency name is already selected, load its sub-competencies
        if (competencyNameSelect.value) {
            // Wait a bit for DOM to be ready, then load sub-competencies
            setTimeout(() => {
                loadSubCompetencies();
            }, 100);
        }
    }
});

function updateIssuer(selectElement) {
    // Fungsi ini tidak lagi auto-fill issuer dan certificate type
    // User harus input manual untuk issuer dan certificate type
    const certId = selectElement.value;
    const certItem = selectElement.closest('.certification-item');
    const issuerInput = certItem.querySelector('input[name="cert_issuers[]"]');
    
    // Reset field - user harus input manual
    issuerInput.value = '';
    issuerInput.readOnly = false;
    issuerInput.style.backgroundColor = '';
    issuerInput.style.cursor = 'auto';
}

function calculateExpiryDate(inputElement) {
    const certItem = inputElement.closest('.certification-item');
    const issueDateInput = certItem.querySelector('input[name="issue_dates[]"]');
    const validityYearsInput = certItem.querySelector('input[name="validity_years[]"]');
    const expiryDateInput = certItem.querySelector('input[name="expiry_dates[]"]');
    const noExpiryCheck = certItem.querySelector('input[name="no_expiry[]"]');
    
    if (noExpiryCheck && noExpiryCheck.checked) {
        return;
    }
    
    const issueDate = issueDateInput.value;
    const validityYears = parseFloat(validityYearsInput.value) || 0;
    
    if (issueDate && validityYears > 0) {
        const date = new Date(issueDate);
        date.setFullYear(date.getFullYear() + Math.floor(validityYears));
        date.setMonth(date.getMonth() + Math.round((validityYears % 1) * 12));
        
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        
        expiryDateInput.value = `${year}-${month}-${day}`;
    }
}

function toggleExpiryField(checkboxElement) {
    const certItem = checkboxElement.closest('.certification-item');
    const expiryDateInput = certItem.querySelector('input[name="expiry_dates[]"]');
    const validityYearsInput = certItem.querySelector('input[name="validity_years[]"]');
    const otherExpiryReason = certItem.querySelector('.other-expiry-reason');
    
    if (checkboxElement.checked) {
        expiryDateInput.value = '';
        expiryDateInput.removeAttribute('required');
        expiryDateInput.readOnly = true;
        validityYearsInput.value = '';
        validityYearsInput.removeAttribute('required');
        validityYearsInput.readOnly = true;
        otherExpiryReason.style.display = 'block';
    } else {
        expiryDateInput.setAttribute('required', 'required');
        expiryDateInput.readOnly = false;
        validityYearsInput.setAttribute('required', 'required');
        validityYearsInput.readOnly = false;
        otherExpiryReason.style.display = 'none';
    }
}

function addCertification() {
    const container = document.getElementById('certificationContainer');
    const certItems = container.querySelectorAll('.certification-item');
    const newIndex = certItems.length + 1;

    const newItem = document.createElement('div');
    newItem.className = 'certification-item';
    newItem.innerHTML = `
        <div class="cert-item-header">
            <h5><i class="fas fa-file-certificate"></i> <span data-lang="certification">Certification</span> #${newIndex}</h5>
            <div class="cert-header-actions">
                <span class="badge badge-info" data-lang="new">NEW</span>
                <button type="button" class="btn-remove-cert" onclick="removeCertification(this)" title="Remove this certification" data-lang-title="remove-this-certification">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>

        <input type="hidden" name="existing_cert_ids[]" value="0">

        <div class="form-row">
            <div class="form-group col-lg-4">
                <label data-lang="certification-name-required">Certification Name <span class="text-danger">*</span></label>
                <select name="certification_ids[]" class="form-control cert-name-select" required onchange="updateIssuer(this)">
                    <option value="" data-lang="select-certification">-- Select Certification --</option>
                    ${getCertificationOptions()}
                </select>
            </div>

            <div class="form-group col-lg-4">
                <label data-lang="certificate-number-required">Certificate Number <span class="text-danger">*</span></label>
                <input type="text" name="cert_numbers[]" class="form-control" required placeholder="Certificate number" data-lang-placeholder="certificate-number-placeholder">
            </div>

            <div class="form-group col-lg-4">
                <label data-lang="issuer-required">Issuer <span class="text-danger">*</span></label>
                <input type="text" name="cert_issuers[]" class="form-control" required placeholder="Issuer name" data-lang-placeholder="issuer-name-placeholder">
            </div>
        </div>

        <div class="form-row">
            <div class="form-group col-lg-6">
                <label data-lang="issue-date-required">Issue Date <span class="text-danger">*</span></label>
                <input type="date" name="issue_dates[]" class="form-control issue-date" required onchange="calculateExpiryDate(this)">
            </div>
            <div class="form-group col-lg-6">
                <label data-lang="validity-period-required">Validity Period <span class="text-danger">*</span></label>
                <div class="validity-input-group">
                    <input type="number" name="validity_years[]" class="form-control validity-years" min="0" step="0.5" placeholder="Years" data-lang-placeholder="years" onchange="calculateExpiryDate(this)" value="3">
                    <label class="checkbox-label">
                        <input type="checkbox" name="no_expiry[]" class="no-expiry-check" onchange="toggleExpiryField(this)">
                        <span data-lang="no-expiry">No Expiry</span>
                    </label>
                </div>
                <small class="form-hint" data-lang="validity-years-hint">Enter in years, e.g.: 3 or 2.5 for 2 years 6 months</small>
            </div>
        </div>

        <div class="form-row">
            <div class="form-group col-lg-6">
                <label data-lang="expiry-date-required">Expiry Date <span class="text-danger">*</span></label>
                <input type="date" name="expiry_dates[]" class="form-control expiry-date" required>
                <small class="form-hint" data-lang="expiry-date-manual-edit-note">You can manually edit the expiry date if needed</small>
            </div>
            <div class="form-group col-lg-6">
                <label><span data-lang="no-expiry-reason">No Expiry Reason</span> <span class="text-muted" data-lang="optional">(Optional)</span></label>
                <input type="text" name="expiry_reasons[]" class="form-control other-expiry-reason" style="display: none;" placeholder="Example: Lifetime Certificate" data-lang-placeholder="lifetime-certificate-example">
            </div>
        </div>

        <div class="form-group">
            <label data-lang="upload-certificate-file-required">Upload Certificate File <span class="text-danger">*</span></label>
            <div class="file-upload-area">
                <i class="fas fa-file-pdf"></i>
                <input type="file" name="certifications[]" class="file-input" accept=".pdf" required>
                <span class="file-text" data-lang="click-or-drag-certificate-file">Click or drag certificate file<br>(PDF, Max 5MB)</span>
                <span class="file-name"></span>
            </div>
        </div>
    `;

    container.appendChild(newItem);

    // Setup file upload for the new item
    const fileUploadArea = newItem.querySelector('.file-upload-area');
    setupFileUpload(fileUploadArea);

    // Update certification numbers
    updateCertificationNumbers();
}

function removeCertification(button) {
    const certItem = button.closest('.certification-item');
    const container = document.getElementById('certificationContainer');
    const certItems = container.querySelectorAll('.certification-item');

    // Don't remove if it's the only one
    if (certItems.length <= 1) {
        const mustHaveOneCert = window.getLanguageText('must-have-one-cert', 'Must have at least one certification!');
        alert(mustHaveOneCert);
        return;
    }

    // Confirm before removing
    const confirmRemoveCert = window.getLanguageText('confirm-remove-cert', 'Are you sure you want to remove this certification?');
    if (confirm(confirmRemoveCert)) {
        certItem.remove();
        updateCertificationNumbers();
    }
}

function updateCertificationNumbers() {
    const container = document.getElementById('certificationContainer');
    const certItems = container.querySelectorAll('.certification-item');

    certItems.forEach((item, index) => {
        const header = item.querySelector('.cert-item-header h5');
        const certLabel = window.getLanguageText('certification', 'Certification');
        header.innerHTML = `<i class="fas fa-file-certificate"></i> ${certLabel} #${index + 1}`;
    });
}

function getCertificationOptions() {
    let options = '';
    for (const id in certificationsData) {
        const cert = certificationsData[id];
        options += `<option value="${cert.id}" data-issuer="${cert.cert_issuer || ''}">${cert.cert_name}</option>`;
    }
    return options;
}

function setupFileUpload(area) {
    area.addEventListener('dragover', (e) => {
        e.preventDefault();
        area.classList.add('dragover');
    });
    area.addEventListener('dragleave', () => {
        area.classList.remove('dragover');
    });
    area.addEventListener('drop', (e) => {
        e.preventDefault();
        area.classList.remove('dragover');
        const input = area.querySelector('.file-input');
        input.files = e.dataTransfer.files;
        updateFileName(area, input.files[0]);
    });
    
    const input = area.querySelector('.file-input');
    input.addEventListener('change', () => {
        updateFileName(area, input.files[0]);
    });
}

// File upload preview
document.querySelectorAll('.file-upload-area').forEach(area => {
    setupFileUpload(area);
});

function updateFileName(area, file) {
    if (file) {
        area.querySelector('.file-name').textContent = file.name;
        area.querySelector('.file-name').style.display = 'block';
    }
}

// Initialize event listeners on page load
// (Already handled by first DOMContentLoaded handler above)

</script>



<?php require_once dirname(__DIR__) . '/layouts/footer.php'; ?>
