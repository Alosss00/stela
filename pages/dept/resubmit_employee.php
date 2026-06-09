<?php
$page_title = 'Upload Employee Correction';
require_once '../../includes/auth.php';
require_once '../../includes/db.php';

// Only department_user role or user with department can access this page
if (!hasDepartment() && $_SESSION['role'] != 'department_user') {
    header('Location: ../admin/dashboard.php');
    exit();
}
$db = new Database();
$current_department = $_SESSION['department'] ?? '';
$company_name = $_SESSION['company_name'] ?? $_SESSION['department'] ?? '';
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
    WHERE e.id = $employee_id 
    AND e.department = '" . $db->escapeString($current_department) . "'
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
    $full_name = $db->escapeString(trim($_POST['full_name']));
    $position = $db->escapeString(trim($_POST['position']));
    $department = $db->escapeString(trim($_POST['department']));
    $competency_type = $db->escapeString(trim($_POST['competency_type']));
    $competency_name = !empty($_POST['competency_name']) ? $db->escapeString(trim($_POST['competency_name'])) : '';
    $sub_competency = !empty($_POST['sub_competency']) ? $db->escapeString(trim($_POST['sub_competency'])) : '';
    $supervision_area = !empty($_POST['supervision_area']) ? $db->escapeString(trim($_POST['supervision_area'])) : '';
    $ruang_lingkup = $db->escapeString(trim($_POST['ruang_lingkup']));
    $contractor_company = $db->escapeString(trim($_POST['contractor_company']));
    
    // Validate required fields
    if (empty($full_name) || empty($position) || empty($department) || empty($competency_type) || empty($ruang_lingkup) || empty($contractor_company)) {
        $error = 'All fields are required!';
    } elseif ($competency_type == 'pengawas_operasional' && empty($supervision_area)) {
        $error = 'Supervision Area is required for Operational Supervisor!';
    } elseif (in_array($competency_type, ['pengawas_teknis', 'tenaga_teknis']) && empty($competency_name)) {
        $error = 'Competency is required for Technical Supervisor and Technical Personnel types!';
    } elseif ($competency_type == 'tenaga_teknis' && empty($sub_competency)) {
        $error = 'Sub Competency is required for Technical Personnel!';
    } else {
        // Handle CV upload (optional for re-submit, keep old if not provided)
        $cv_file = $employee['cv_file'];
        if (isset($_FILES['cv_file']) && $_FILES['cv_file']['error'] == 0) {
            $file_size = $_FILES['cv_file']['size'];
            $max_size = 5 * 1024 * 1024; // 5MB
            $file_extension = strtolower(pathinfo($_FILES['cv_file']['name'], PATHINFO_EXTENSION));
            $allowed_cv_extensions = ['pdf', 'doc', 'docx'];
            
            if (!in_array($file_extension, $allowed_cv_extensions)) {
                $error = 'File type not allowed! Only PDF, DOC, or DOCX.';
            } elseif ($file_size > $max_size) {
                $error = 'File size too large! Maximum 5MB.';
            } else {
                $upload_dir = '../../assets/uploads/cv/';
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                $new_filename = 'cv_' . $employee['employee_code'] . '_' . time() . '.' . $file_extension;
                $upload_path = $upload_dir . $new_filename;
                
                if (move_uploaded_file($_FILES['cv_file']['tmp_name'], $upload_path)) {
                    // Delete old CV file
                    if ($cv_file && file_exists('assets/' . $cv_file)) {
                        @unlink('assets/' . $cv_file);
                    }
                    $cv_file = 'uploads/cv/' . $new_filename;
                } else {
                    $error = 'Failed to upload CV file.';
                }
            }
        }

        // Handle Signature upload (optional)
        $signature_file = $employee['signature_file'];
        if (isset($_FILES['signature_file']) && $_FILES['signature_file']['error'] == 0) {
            $sig_file_size = $_FILES['signature_file']['size'];
            $sig_max_size = 2 * 1024 * 1024; // 2MB
            $sig_file_extension = strtolower(pathinfo($_FILES['signature_file']['name'], PATHINFO_EXTENSION));
            $allowed_extensions = ['png', 'jpg', 'jpeg'];

            if (!in_array($sig_file_extension, $allowed_extensions)) {
                $error = 'Signature file type not allowed! Only PNG, JPG, or JPEG.';
            } elseif ($sig_file_size > $sig_max_size) {
                $error = 'Signature file size too large! Maximum 2MB.';
            } else {
                $sig_upload_dir = '../../assets/uploads/signatures/';
                if (!file_exists($sig_upload_dir)) {
                    mkdir($sig_upload_dir, 0777, true);
                }
                
                $sig_new_filename = 'signature_' . $employee['employee_code'] . '_' . time() . '.' . $sig_file_extension;
                $sig_upload_path = $sig_upload_dir . $sig_new_filename;
                
                if (move_uploaded_file($_FILES['signature_file']['tmp_name'], $sig_upload_path)) {
                    // Delete old signature file
                    if ($signature_file && file_exists('assets/' . $signature_file)) {
                        @unlink('assets/' . $signature_file);
                    }
                    $signature_file = 'uploads/signatures/' . $sig_new_filename;
                } else {
                    $error = 'Failed to upload signature file.';
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
                "verification_notes = NULL",
                "resubmit_count = COALESCE(resubmit_count, 0) + 1",
                "resubmit_date = NOW()"
            ];
            
            // Add optional fields
            if (in_array('competency_name', $available_columns)) {
                $update_fields[] = "competency_name = '$competency_name'";
            }

            if (in_array('sub_competency', $available_columns)) {
                $update_fields[] = "sub_competency = '$sub_competency'";
            }

            if (in_array('supervision_area', $available_columns)) {
                $update_fields[] = "supervision_area = '$supervision_area'";
            }
            
            if (in_array('signature_file', $available_columns)) {
                $update_fields[] = "signature_file = '$signature_file'";
            }
            
            $sql = "UPDATE employees SET " . implode(', ', $update_fields) . " WHERE id = $employee_id";
            
            if ($db->query($sql)) {
                // Update appointment status back to pending for admin re-review
                if (!empty($employee['appointment_id'])) {
                    $appointment_id = intval($employee['appointment_id']);

                    // Check which KTT(s) need re-review based on requires flags
                    $appt_check = $db->query("SELECT requires_ktt_msm_review, requires_ktt_ttn_review FROM appointments WHERE id = $appointment_id");
                    $appt_flags = $appt_check ? $appt_check->fetch_assoc() : null;
                    $reset_msm = $appt_flags && $appt_flags['requires_ktt_msm_review'] == 1;
                    $reset_ttn = $appt_flags && $appt_flags['requires_ktt_ttn_review'] == 1;

                    $update_parts = [
                        "status = 'pending'",
                        "approved_by = NULL",
                        "approved_date = NULL",
                        "admin_approval_action = NULL",
                        "admin_approval_notes = NULL",
                        "last_rejected_by_ktt = NULL",
                        "rejected_by_ktt_user_id = NULL"
                    ];
                    // Only reset the KTT(s) that need re-review
                    if ($reset_msm) {
                        $update_parts[] = "ktt_msm_status = 'pending'";
                        $update_parts[] = "ktt1_approved_by = NULL";
                        $update_parts[] = "ktt1_approved_date = NULL";
                    }
                    if ($reset_ttn) {
                        $update_parts[] = "ktt_ttn_status = 'pending'";
                        $update_parts[] = "ktt2_approved_by = NULL";
                        $update_parts[] = "ktt2_approved_date = NULL";
                    }

                    $db->query("UPDATE appointments SET " . implode(', ', $update_parts) . " WHERE id = $appointment_id");

                    // Delete old KTT approval records only for KTT(s) that need re-review
                    if ($reset_msm) {
                        $db->query("DELETE FROM ktt_approvals WHERE appointment_id = $appointment_id AND ktt_user_id = 7");
                    }
                    if ($reset_ttn) {
                        $db->query("DELETE FROM ktt_approvals WHERE appointment_id = $appointment_id AND ktt_user_id = 8");
                    }
                }
                
                // Define upload directory for certifications
                $upload_dir = '../../assets/uploads/certifications/';
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                $cert_ids = $_POST['certification_ids'] ?? [];
                $cert_numbers = $_POST['cert_numbers'] ?? [];
                $cert_issuers = $_POST['cert_issuers'] ?? [];
                $issue_dates = $_POST['issue_dates'] ?? [];
                $expiry_dates = $_POST['expiry_dates'] ?? [];
                $expiry_reasons = $_POST['expiry_reasons'] ?? [];
                $existing_cert_ids = $_POST['existing_cert_ids'] ?? [];
                
                // Process each certificate (with or without new file)
                foreach ($cert_ids as $key => $cert_id) {
                    if (empty($cert_id)) continue;
                    
                    $cert_id = intval($cert_id);
                    $cert_number = $db->escapeString($cert_numbers[$key] ?? '');
                    $cert_issuer = $db->escapeString($cert_issuers[$key] ?? '');
                    $issue_date = $db->escapeString($issue_dates[$key] ?? '');
                    $expiry_date = $db->escapeString($expiry_dates[$key] ?? '');
                    $reason = $db->escapeString($expiry_reasons[$key] ?? '');
                    $existing_id = isset($existing_cert_ids[$key]) ? intval($existing_cert_ids[$key]) : 0;
                    
                    // Check if expired
                    $today = date('Y-m-d');
                    $status = ($expiry_date && $expiry_date < $today) ? 'expired' : 'pending';
                    
                    // Check if new file uploaded for this certification
                    $cert_path = null;
                    if (isset($_FILES['certifications']['tmp_name'][$key]) && 
                        $_FILES['certifications']['error'][$key] == 0 && 
                        !empty($_FILES['certifications']['tmp_name'][$key])) {
                        
                        $file_ext = pathinfo($_FILES['certifications']['name'][$key], PATHINFO_EXTENSION);
                        $cert_file = $employee['employee_code'] . '_cert_' . $key . '_' . time() . '.' . $file_ext;
                        
                        if (move_uploaded_file($_FILES['certifications']['tmp_name'][$key], $upload_dir . $cert_file)) {
                            $cert_path = 'uploads/certifications/' . $cert_file;
                        }
                    }
                    
                    if ($existing_id > 0) {
                        // Update existing certification
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
                        
                        // Only update file if new file was uploaded
                        if ($cert_path) {
                            $update_parts[] = "document_file = '$cert_path'";
                        }
                        
                        $sql_cert = "UPDATE employee_certifications SET " . implode(', ', $update_parts) . 
                                    " WHERE id = $existing_id AND employee_id = $employee_id";
                    } else {
                        // Insert new certification (file required for new certs)
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
                
                // Send notification to admin about resubmission - with timeout protection
                require_once '../../includes/notifications.php';
                try {
                    set_time_limit(60); // Allow extra time for email sending
                    $notificationService = new NotificationService();
                    $notificationService->notifyNewEmployeeAdded($employee_id, $company_name);
                } catch (Exception $e) {
                    error_log("Notification error: " . $e->getMessage());
                }
                
                $message = 'Document correction successfully uploaded! Waiting for re-verification from Admin.';
                // Redirect after 2 seconds
                header("refresh:2;url=employees.php");
            } else {
                $error = 'Failed to upload employee correction!';
                error_log("Error uploading employee correction: " . $db->getConnection()->error);
            }
        }
    }
}

require_once '../../includes/header.php';
?>

<div class="add-employee-container">
    <!-- Page Header -->
    <div class="page-header-add">
        <div class="header-left">
            <h2><i class="fas fa-upload"></i> <span data-lang="upload-employee-correction">Upload Employee Correction</span></h2>
            <p data-lang="correct-rejected-data-and-reupload">Correct the rejected data and re-upload for verification</p>
        </div>
        <a href="employees.php" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left"></i> Back
        </a>
    </div>

    <?php if ($employee['verification_notes'] || !empty($ktt_rejectors)): ?>
    <div class="alert alert-warning alert-custom">
        <i class="fas fa-exclamation-triangle"></i>
        <div>
            <strong data-lang="rejection-reason">Rejection Reason:</strong>

            <?php if ($employee['verification_notes']): ?>
            <div class="rejection-section">
                <p><strong data-lang="from-admin">From Admin:</strong></p>
                <p><?php echo nl2br(htmlspecialchars($employee['verification_notes'])); ?></p>
                <?php if ($employee['verified_by_name']): ?>
                <small><span data-lang="rejected-by">Rejected by</span>: <strong><?php echo htmlspecialchars($employee['verified_by_name']); ?></strong>
                <?php if ($employee['verified_date']): ?>
                <span data-lang="on-date">on</span> <?php echo date('d/m/Y H:i', strtotime($employee['verified_date'])); ?>
                <?php endif; ?>
                </small>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <?php if (!empty($ktt_rejectors)): ?>
            <div class="rejection-section" style="margin-top: 15px; padding-top: 15px; border-top: 1px solid rgba(0,0,0,0.1);">
                <p><strong>From KTT:</strong></p>
                <?php foreach ($ktt_rejectors as $index => $rejector): ?>
                <div class="ktt-rejection-item" style="<?php echo $index > 0 ? 'margin-top: 12px; padding-top: 12px; border-top: 1px dashed rgba(0,0,0,0.1);' : ''; ?>">
                    <p style="margin-bottom: 8px;"><?php echo nl2br(htmlspecialchars($rejector['approval_notes'])); ?></p>
                    <small style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                        <span style="background: #fee2e2; color: #dc2626; padding: 3px 8px; border-radius: 4px; font-weight: 700; font-size: 10px;">
                            <?php echo htmlspecialchars($rejector['ktt_position']); ?>
                        </span>
                        <span style="color: #666;">�</span>
                        <strong style="color: #333;"><?php echo htmlspecialchars($rejector['full_name']); ?></strong>
                        <span style="color: #666;">�</span>
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
            <strong>Success!</strong>
            <p><?php echo htmlspecialchars($message); ?></p>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($error): ?>
    <div class="alert alert-error alert-custom">
        <i class="fas fa-exclamation-circle"></i>
        <div>
            <strong>Error!</strong>
            <p><?php echo htmlspecialchars($error); ?></p>
        </div>
    </div>
    <?php endif; ?>
    
    <form method="POST" action="" enctype="multipart/form-data" class="form-container">
        <!-- Section 1: Identity Data & Competency -->
        <div class="form-section">
            <div class="section-header">
                <h3><i class="fas fa-id-card"></i> <span data-lang="identity-competency-data">Identity & Competency Data</span></h3>
                <span class="section-number">1</span>
            </div>
            
            <div class="form-row">
                <div class="form-group col-lg-6">
                    <label for="employee_code" data-lang="id-badge">ID BADGE <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="employee_code" name="employee_code"
                           value="<?php echo htmlspecialchars($employee['employee_code']); ?>"
                           readonly style="background-color: #F9FAFB;">
                    <small class="form-hint">ID BADGE cannot be changed</small>
                </div>

                <div class="form-group col-lg-6">
                    <label for="full_name" data-lang="full-name">Full Name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="full_name" name="full_name"
                           value="<?php echo htmlspecialchars(isset($_POST['full_name']) ? $_POST['full_name'] : $employee['full_name']); ?>"
                           required placeholder="Employee full name" data-lang-placeholder="employee-full-name-placeholder">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group col-lg-6">
                    <label for="position" data-lang="position">Position <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="position" name="position"
                           value="<?php echo htmlspecialchars(isset($_POST['position']) ? $_POST['position'] : $employee['position']); ?>"
                           required placeholder="Example: Rigger, HSE Superintendent" data-lang-placeholder="position-example-placeholder">
                </div>
                <!-- Department is hidden, using existing value -->
                <input type="hidden" id="department" name="department" value="<?php echo htmlspecialchars($employee['department']); ?>">
            </div>
            
            <div class="form-row">
                <div class="form-group col-lg-6">
                    <label for="ruang_lingkup" data-lang="scope-of-work">Scope of Work <span class="text-danger">*</span></label>
                    <select class="form-control" id="ruang_lingkup" name="ruang_lingkup" required>
                        <option value="" data-lang="select-scope-of-work">-- Select Scope of Work --</option>
                        <?php
                        $stored_rl = isset($_POST['ruang_lingkup']) ? $_POST['ruang_lingkup'] : ($employee['ruang_lingkup'] ?? '');
                        ?>
                        <option value="PT MSM" <?php echo (stripos($stored_rl, 'MSM') !== false) ? 'selected' : ''; ?>>PT MSM</option>
                        <option value="PT TTN" <?php echo (stripos($stored_rl, 'TTN') !== false) ? 'selected' : ''; ?>>PT TTN</option>
                    </select>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group col-lg-6">
                    <label for="competency_type" data-lang="competency-type">Competency Type <span class="text-danger">*</span></label>
                    <?php $comp_type = isset($_POST['competency_type']) ? $_POST['competency_type'] : $employee['competency_type']; ?>
                    <select class="form-control" id="competency_type" name="competency_type" onchange="toggleCompetencyField()" required>
                        <option value="" data-lang="select-competency-type">-- Select Competency Type --</option>
                        <option value="pengawas_operasional" <?php echo ($comp_type == 'pengawas_operasional') ? 'selected' : ''; ?>>Operational Supervisor</option>
                        <option value="pengawas_teknis" <?php echo ($comp_type == 'pengawas_teknis') ? 'selected' : ''; ?>>Technical Supervisor</option>
                        <option value="tenaga_teknis" <?php echo ($comp_type == 'tenaga_teknis') ? 'selected' : ''; ?>>Technical Personnel</option>
                    </select>
                </div>

                <div class="form-group col-lg-6" id="supervision_area_group" style="display: none;">
                    <label for="supervision_area" data-lang="supervision-area">Supervision Area <span class="text-danger">*</span></label>
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
                    <label for="competency_name" data-lang="competency">Competency <span class="text-danger" id="competency_required">*</span></label>
                    <?php $comp_name = trim(isset($_POST['competency_name']) ? $_POST['competency_name'] : ($employee['competency_name'] ?? '')); ?>
                    <select class="form-control" id="competency_name" name="competency_name">
                        <option value="" data-lang="select-competency">-- Select Competency --</option>
                        <?php
                        // Populate competencies for pengawas_teknis
                        if (!empty($competencies_by_type['pengawas_teknis'])) {
                            foreach ($competencies_by_type['pengawas_teknis'] as $comp):
                                $option_name = trim($comp['competency_name']);
                                $selected = ($comp_name === $option_name) ? 'selected' : '';
                        ?>
                            <option value="<?php echo htmlspecialchars($option_name); ?>" data-type="pengawas_teknis" <?php echo $selected; ?>>
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
                            <option value="<?php echo htmlspecialchars($option_name); ?>" data-type="tenaga_teknis" <?php echo $selected; ?>>
                                <?php echo htmlspecialchars($option_name); ?>
                            </option>
                        <?php
                            endforeach;
                        }
                        ?>
                    </select>
                </div>

                <div class="form-group col-lg-6" id="sub_competency_group" style="display: none;">
                    <label for="sub_competency" data-lang="sub-competency">Sub Competency <span class="text-danger">*</span></label>
                    <?php $sub_comp = isset($_POST['sub_competency']) ? $_POST['sub_competency'] : (isset($employee['sub_competency']) ? $employee['sub_competency'] : ''); ?>
                    <select class="form-control" id="sub_competency" name="sub_competency">
                        <option value="">-- Pilih Sub Competency --</option>
                        <option value="1" <?php echo ($sub_comp == '1') ? 'selected' : ''; ?>>1</option>
                        <option value="2" <?php echo ($sub_comp == '2') ? 'selected' : ''; ?>>2</option>
                        <option value="3" <?php echo ($sub_comp == '3') ? 'selected' : ''; ?>>3</option>
                    </select>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group col-lg-6">
                    <label for="contractor_company" data-lang="company">Company <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="contractor_company" name="contractor_company"
                           value="<?php echo htmlspecialchars(isset($_POST['contractor_company']) ? $_POST['contractor_company'] : $employee['contractor_company']); ?>"
                           required placeholder="Contractor company name" data-lang-placeholder="contractor-company-name-placeholder">
                </div>
            </div>
            
            <div class="form-group">
                <label for="cv_file" data-lang="upload-cv-optional-no-change">Upload CV <span class="text-muted">(Optional - leave empty if no changes needed)</span></label>
                <?php if ($employee['cv_file']): ?>
                <div class="current-file-info">
                    <i class="fas fa-file-pdf"></i>
                    <span>Current file: <a href="../../assets/<?php echo htmlspecialchars($employee['cv_file']); ?>" target="_blank">View CV</a></span>
                </div>
                <?php endif; ?>
                <div class="file-upload-area">
                    <i class="fas fa-file-upload"></i>
                    <input type="file" name="cv_file" id="cv_file" class="file-input" accept=".pdf,.doc,.docx">
                    <span class="file-text" data-lang="click-drag-new-cv-file">Click or drag new CV file (PDF/DOC/DOCX, Max 5MB)</span>
                    <span class="file-name"></span>
                </div>
            </div>

            <div class="form-group">
                <label for="signature_file" data-lang="upload-signature-optional">Upload Signature <span class="text-muted">(Optional)</span></label>
                <?php if ($employee['signature_file']): ?>
                <div class="current-file-info">
                    <i class="fas fa-signature"></i>
                    <span>Current file: <a href="../../assets/<?php echo htmlspecialchars($employee['signature_file']); ?>" target="_blank">View Signature</a></span>
                </div>
                <?php endif; ?>
                <div class="file-upload-area">
                    <i class="fas fa-signature"></i>
                    <input type="file" name="signature_file" id="signature_file" class="file-input" accept=".png,.jpg,.jpeg">
                    <span class="file-text" data-lang="click-drag-new-signature-file">Click or drag new signature file (PNG/JPG, Max 2MB)</span>
                    <span class="file-name"></span>
                </div>
                <small class="form-hint"><i class="fas fa-info-circle"></i> The signature will automatically appear on the appointment letter after approval</small>
            </div>
        </div>
        
        <!-- Section 2: Certifications -->
        <div class="form-section">
            <div class="section-header">
                <h3><i class="fas fa-certificate"></i> <span data-lang="certification-competency">Certification/Competency</span></h3>
                <span class="section-number">2</span>
            </div>

            <div class="alert alert-info-custom">
                <i class="fas fa-info-circle"></i>
                <div>
                    <strong>Important Information</strong>
                    <p><strong>File upload is OPTIONAL.</strong> You do not need to re-upload CV, signature, or certificate files if the existing data is correct. Existing files will continue to be used.</p>
                    <p class="mb-0"><strong>Re-upload only if:</strong> Admin mentions in the rejection notes that certain files need to be corrected/replaced.</p>
                </div>
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
                        <h5><i class="fas fa-file-certificate"></i> Certification #<?php echo $cert_index; ?></h5>
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
                            <label data-lang="certification-name">Certification Name <span class="text-danger">*</span></label>
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
                            <label data-lang="certificate-no">Certificate No. <span class="text-danger">*</span></label>
                            <input type="text" name="cert_numbers[]" class="form-control" required placeholder="Certificate number" data-lang-placeholder="certificate-number-placeholder" value="<?php echo htmlspecialchars($cert['cert_number']); ?>">
                        </div>

                        <div class="form-group col-lg-4">
                            <label data-lang="issuer">Issuer <span class="text-danger">*</span></label>
                            <input type="text" name="cert_issuers[]" class="form-control" required placeholder="Issuer name" data-lang-placeholder="issuer-name-placeholder" value="<?php echo htmlspecialchars($cert['cert_issuer']); ?>">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group col-lg-6">
                            <label data-lang="issue-date">Issue Date <span class="text-danger">*</span></label>
                            <input type="date" name="issue_dates[]" class="form-control issue-date" required onchange="calculateExpiryDate(this)" value="<?php echo $cert['issue_date']; ?>">
                        </div>
                        <div class="form-group col-lg-6">
                            <label data-lang="validity-period">Validity Period <span class="text-danger">*</span></label>
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
                                    <span>No Expiry</span>
                                </label>
                            </div>
                            <small class="form-hint">Enter in years, e.g.: 3 or 2.5 for 2 years 6 months</small>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group col-lg-6">
                            <label data-lang="expiry-date">Expiry Date <span class="text-danger">*</span></label>
                            <input type="date" name="expiry_dates[]" class="form-control expiry-date" required value="<?php echo $cert['expiry_date']; ?>">
                            <small class="form-hint">You can manually edit the expiry date if needed</small>
                        </div>
                        <div class="form-group col-lg-6">
                            <label data-lang="reason-for-no-expiry-optional">Reason for No Expiry <span class="text-muted">(Optional)</span></label>
                            <input type="text" name="expiry_reasons[]" class="form-control other-expiry-reason" style="display: none;" placeholder="Example: Lifetime Certificate" data-lang-placeholder="lifetime-certificate-example">
                        </div>
                    </div>

                    <div class="form-group">
                        <label data-lang="upload-new-certificate-file-optional">Upload New Certificate File <span class="text-muted">(Optional - Leave empty if no changes needed)</span></label>
                        <?php if ($cert['document_file']): ?>
                        <div class="current-file-info">
                            <i class="fas fa-file-pdf"></i>
                            <span>Current file: <a href="../../assets/<?php echo htmlspecialchars($cert['document_file']); ?>" target="_blank">View Certificate</a></span>
                        </div>
                        <?php endif; ?>
                        <div class="file-upload-area">
                            <i class="fas fa-file-pdf"></i>
                            <input type="file" name="certifications[]" class="file-input" accept=".pdf">
                            <span class="file-text" data-lang="click-drag-new-certificate-file">Click or drag new certificate file (PDF, Max 5MB)</span>
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
                <i class="fas fa-plus-circle"></i> Add Another Certification
            </button>
        </div>

        <!-- Info Alert -->
        <div class="alert alert-warning alert-custom">
            <i class="fas fa-exclamation-triangle"></i>
            <div>
                <strong>Attention</strong>
                <p>After the correction is uploaded, the status will return to "Pending" and await re-verification from Admin.
                <?php if (!empty($employee['appointment_number'])): ?>
                <br><strong>The Appointment Letter Number (<?php echo htmlspecialchars($employee['appointment_number']); ?>) will remain the same.</strong>
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
// Certification data from server
const certificationsData = <?php echo json_encode($certifications_data); ?>;

function toggleCompetencyField() {
    const competencyType = document.getElementById('competency_type').value;
    const supervisionAreaGroup = document.getElementById('supervision_area_group');
    const competencyGroup = document.getElementById('competency_group');
    const subCompetencyGroup = document.getElementById('sub_competency_group');
    const competencyInput = document.getElementById('competency_name');
    const supervisionAreaInput = document.getElementById('supervision_area');
    const subCompetencyInput = document.getElementById('sub_competency');

    if (competencyType === 'pengawas_operasional') {
        supervisionAreaGroup.style.display = 'block';
        competencyGroup.style.display = 'none';
        subCompetencyGroup.style.display = 'none';
        competencyInput.removeAttribute('required');
        subCompetencyInput.removeAttribute('required');
        supervisionAreaInput.setAttribute('required', 'required');
    } else if (competencyType === 'pengawas_teknis') {
        supervisionAreaGroup.style.display = 'none';
        competencyGroup.style.display = 'block';
        subCompetencyGroup.style.display = 'none';
        competencyInput.setAttribute('required', 'required');
        subCompetencyInput.removeAttribute('required');
        supervisionAreaInput.removeAttribute('required');
        // Filter competencies by type
        filterCompetencies(competencyType);
    } else if (competencyType === 'tenaga_teknis') {
        supervisionAreaGroup.style.display = 'none';
        competencyGroup.style.display = 'block';
        subCompetencyGroup.style.display = 'block';
        competencyInput.setAttribute('required', 'required');
        subCompetencyInput.setAttribute('required', 'required');
        supervisionAreaInput.removeAttribute('required');
        // Filter competencies by type
        filterCompetencies(competencyType);
    } else {
        supervisionAreaGroup.style.display = 'none';
        competencyGroup.style.display = 'none';
        subCompetencyGroup.style.display = 'none';
        competencyInput.removeAttribute('required');
        subCompetencyInput.removeAttribute('required');
        supervisionAreaInput.removeAttribute('required');
    }
}

function filterCompetencies(competencyType) {
    const competencySelect = document.getElementById('competency_name');
    const options = competencySelect.querySelectorAll('option');
    const currentValue = competencySelect.value; // Save current selected value
    
    options.forEach(option => {
        if (option.value === '') {
            option.style.display = 'block';
        } else if (option.selected || option.value === currentValue) {
            // Always show the currently selected option
            option.style.display = 'block';
        } else if (competencyType === 'pengawas_teknis') {
            // For Pengawas Teknis, show both Pengawas Teknis and Tenaga Teknis options
            const optionType = option.getAttribute('data-type');
            if (optionType === 'pengawas_teknis' || optionType === 'tenaga_teknis') {
                option.style.display = 'block';
            } else {
                option.style.display = 'none';
            }
        } else if (option.getAttribute('data-type') === competencyType) {
            option.style.display = 'block';
        } else {
            option.style.display = 'none';
        }
    });
}

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
            <h5><i class="fas fa-file-certificate"></i> Certification #${newIndex}</h5>
            <div class="cert-header-actions">
                <span class="badge badge-info">NEW</span>
                <button type="button" class="btn-remove-cert" onclick="removeCertification(this)" title="Remove this certification" data-lang-title="remove-this-certification">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>

        <input type="hidden" name="existing_cert_ids[]" value="0">

        <div class="form-row">
            <div class="form-group col-lg-4">
                <label data-lang="certification-name">Certification Name <span class="text-danger">*</span></label>
                <select name="certification_ids[]" class="form-control cert-name-select" required onchange="updateIssuer(this)">
                    <option value="" data-lang="select-certification">-- Select Certification --</option>
                    ${getCertificationOptions()}
                </select>
            </div>

            <div class="form-group col-lg-4">
                <label data-lang="certificate-no">Certificate No. <span class="text-danger">*</span></label>
                <input type="text" name="cert_numbers[]" class="form-control" required placeholder="Certificate number" data-lang-placeholder="certificate-number-placeholder">
            </div>

            <div class="form-group col-lg-4">
                <label data-lang="issuer">Issuer <span class="text-danger">*</span></label>
                <input type="text" name="cert_issuers[]" class="form-control" required placeholder="Issuer name" data-lang-placeholder="issuer-name-placeholder">
            </div>
        </div>

        <div class="form-row">
            <div class="form-group col-lg-6">
                <label data-lang="issue-date">Issue Date <span class="text-danger">*</span></label>
                <input type="date" name="issue_dates[]" class="form-control issue-date" required onchange="calculateExpiryDate(this)">
            </div>
            <div class="form-group col-lg-6">
                <label data-lang="validity-period">Validity Period <span class="text-danger">*</span></label>
                <div class="validity-input-group">
                    <input type="number" name="validity_years[]" class="form-control validity-years" min="0" step="0.5" placeholder="Years" data-lang-placeholder="years" onchange="calculateExpiryDate(this)" value="3">
                    <label class="checkbox-label">
                        <input type="checkbox" name="no_expiry[]" class="no-expiry-check" onchange="toggleExpiryField(this)">
                        <span>No Expiry</span>
                    </label>
                </div>
                <small class="form-hint">Enter in years, e.g.: 3 or 2.5 for 2 years 6 months</small>
            </div>
        </div>

        <div class="form-row">
            <div class="form-group col-lg-6">
                <label data-lang="expiry-date">Expiry Date <span class="text-danger">*</span></label>
                <input type="date" name="expiry_dates[]" class="form-control expiry-date" required>
                <small class="form-hint">You can manually edit the expiry date if needed</small>
            </div>
            <div class="form-group col-lg-6">
                <label data-lang="reason-for-no-expiry-optional">Reason for No Expiry <span class="text-muted">(Optional)</span></label>
                <input type="text" name="expiry_reasons[]" class="form-control other-expiry-reason" style="display: none;" placeholder="Example: Lifetime Certificate" data-lang-placeholder="lifetime-certificate-example">
            </div>
        </div>

        <div class="form-group">
            <label data-lang="upload-certificate-file">Upload Certificate File <span class="text-danger">*</span></label>
            <div class="file-upload-area">
                <i class="fas fa-file-pdf"></i>
                <input type="file" name="certifications[]" class="file-input" accept=".pdf" required>
                <span class="file-text" data-lang="click-drag-certificate-file">Click or drag certificate file (PDF, Max 5MB)</span>
                <span class="file-name"></span>
            </div>
        </div>
    `;

    container.appendChild(newItem);
    setupFileUpload(newItem.querySelector('.file-upload-area'));
    updateCertificationNumbers();
}

function removeCertification(button) {
    const certItem = button.closest('.certification-item');
    const container = document.getElementById('certificationContainer');
    const certItems = container.querySelectorAll('.certification-item');

    // Don't remove if it's the only one
    if (certItems.length <= 1) {
        const mustHaveOneCert = window.getLanguageText('');
        alert(mustHaveOneCert);
        return;
    }

    // Confirm before removing
    const confirmRemoveCert = window.getLanguageText('');
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
        const certLabel = window.getLanguageText('');
        header.innerHTML = `<i class="fas fa-file-certificate"></i> ${certLabel} #${index + 1}`;

        // Show/hide remove button based on index
        const actionsDiv = item.querySelector('.cert-header-actions');
        if (actionsDiv) {
            if (index === 0) {
                // First item - only show badge if exists
                const badge = actionsDiv.querySelector('.badge');
                if (badge) {
                    actionsDiv.innerHTML = `<span class="${badge.className}">${badge.textContent}</span>`;
                } else {
                    actionsDiv.innerHTML = '';
                }
            } else if (!actionsDiv.querySelector('.btn-remove-cert')) {
                // Not first item and no remove button - add it
                const badge = actionsDiv.querySelector('.badge');
                const badgeHTML = badge ? `<span class="${badge.className}">${badge.textContent}</span>` : '';
                actionsDiv.innerHTML = `
                    ${badgeHTML}
                    <button type="button" class="btn-remove-cert" onclick="removeCertification(this)" title="Remove this certification" data-lang-title="remove-this-certification">
                        <i class="fas fa-times"></i>
                    </button>
                `;
            }
        }
    });
}

function getCertificationOptions() {
    let options = '';
    for (const id in certificationsData) {
        const cert = certificationsData[id];
        const certName = cert.cert_name || cert;
        const certIssuer = cert.cert_issuer || '';
        options += `<option value="${id}" data-issuer="${certIssuer}">${certName}</option>`;
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
document.addEventListener('DOMContentLoaded', function() {
    // Trigger toggleCompetencyField if competency_type has a value on page load
    if (document.getElementById('competency_type').value) {
        toggleCompetencyField();
    }
});
</script>

<style>
.add-employee-container {
    padding: 20px 0;
}

/* Page Header */
.page-header-add {
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

.header-left h2 {
    margin: 0 0 6px 0;
    font-size: 24px;
    font-weight: 700;
    display: flex;
    align-items: center;
    gap: 12px;
}

.header-left p {
    margin: 0;
    opacity: 0.95;
    font-size: 13px;
}

/* Alert Custom */
.alert-custom {
    display: flex;
    gap: 15px;
    align-items: flex-start;
    padding: 20px;
    margin-bottom: 30px;
    border-radius: 8px;
    border-left: 4px solid;
}

.alert-success.alert-custom {
    background: #E8F5E9;
    border-left-color: #2E7D32;
}

.alert-success.alert-custom i {
    color: #2E7D32;
    font-size: 20px;
}

.alert-error.alert-custom {
    background: #fee2e2;
    border-left-color: #ef4444;
}

.alert-error.alert-custom i {
    color: #ef4444;
    font-size: 20px;
}

.alert-warning.alert-custom {
    background: #fef3c7;
    border-left-color: #f59e0b;
}

.alert-warning.alert-custom i {
    color: #f59e0b;
}

.alert-custom strong {
    display: block;
    margin-bottom: 5px;
}

.alert-custom p {
    margin: 0;
}

/* Form Container */
.form-container {
    width: 100%;
}

/* Form Section */
.form-section {
    background: white;
    border-radius: 12px;
    padding: 32px;
    margin-bottom: 20px;
    box-shadow: 0 2px 12px rgba(0, 0, 0, 0.06);
    border: 1px solid #e5e7eb;
    border-top: 4px solid #37474F;
    transition: box-shadow 0.3s ease;
}

.form-section:hover {
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.08);
}

.section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 28px;
    padding-bottom: 16px;
    border-bottom: 2px solid #f3f4f6;
}

.section-header h3 {
    margin: 0;
    font-size: 18px;
    font-weight: 700;
    color: #1f2937;
    display: flex;
    align-items: center;
    gap: 10px;
}

.section-header i {
    color: #37474F;
}

.section-number {
    background: linear-gradient(135deg, #37474F, #616161);
    color: white;
    width: 36px;
    height: 36px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 16px;
    box-shadow: 0 2px 8px rgba(102, 126, 234, 0.3);
}

/* Form Group */
.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: 600;
    color: #333;
    font-size: 14px;
}

.form-hint {
    display: block;
    margin-top: 5px;
    color: #999;
    font-size: 12px;
}

.form-control {
    width: 100%;
    padding: 12px 16px;
    border: 2px solid #e5e7eb;
    border-radius: 8px;
    font-size: 14px;
    transition: all 0.3s ease;
    font-family: inherit;
    background: white;
}

.form-control:focus {
    outline: none;
    border-color: #37474F;
    box-shadow: 0 0 0 3px rgba(23, 162, 184, 0.1);
    background: #fafbff;
}

.form-control:hover:not(:focus) {
    border-color: #d1d5db;
}

.text-danger {
    color: #ef4444;
    font-weight: 700;
}

/* Current File Info */
.current-file-info {
    background: #f0f9ff;
    border: 1px solid #bfdbfe;
    border-radius: 6px;
    padding: 10px 15px;
    margin-bottom: 10px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.current-file-info i {
    color: #37474F;
    font-size: 18px;
}

.current-file-info a {
    color: #37474F;
    text-decoration: none;
    font-weight: 600;
}

.current-file-info a:hover {
    text-decoration: underline;
}

/* File Upload Area */
.file-upload-area {
    border: 2px dashed #ddd;
    border-radius: 8px;
    padding: 30px;
    text-align: center;
    cursor: pointer;
    transition: all 0.3s ease;
    position: relative;
    background: #f8f9fa;
}

.file-upload-area:hover {
    border-color: #37474F;
    background: #f0f9ff;
}

.file-upload-area.dragover {
    border-color: #37474F;
    background: #e8f7fa;
}

.file-upload-area i {
    font-size: 40px;
    color: #37474F;
    margin-bottom: 15px;
    display: block;
}

.file-input {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    opacity: 0;
    cursor: pointer;
}

.file-text {
    display: block;
    color: #666;
    font-size: 13px;
}

.file-name {
    display: none;
    color: #2E7D32;
    font-weight: 600;
    font-size: 12px;
    margin-top: 10px;
}

/* Certification Item */
.certifications-list {
    margin-bottom: 20px;
}

.certification-item {
    background: #f8f9fa;
    border: 2px solid #e9ecef;
    border-radius: 10px;
    padding: 24px;
    margin-bottom: 20px;
    transition: all 0.3s ease;
}

.certification-item:hover {
    border-color: #37474F;
    box-shadow: 0 4px 12px rgba(23, 162, 184, 0.1);
}

.cert-item-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
    padding-bottom: 10px;
    border-bottom: 1px solid #ddd;
}

.cert-item-header h5 {
    margin: 0;
    color: #333;
    font-size: 14px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.cert-item-header i {
    color: #37474F;
}

.cert-header-actions {
    display: flex;
    align-items: center;
    gap: 10px;
}

.btn-remove-cert {
    background: #fee2e2;
    color: #dc2626;
    border: 2px solid #fecaca;
    width: 32px;
    height: 32px;
    border-radius: 6px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.3s ease;
}

.btn-remove-cert:hover {
    background: #dc2626;
    color: white;
    border-color: #dc2626;
    transform: scale(1.05);
}

.btn-remove-cert i {
    color: inherit;
    font-size: 14px;
}

.badge-info {
    background: #dbeafe;
    color: #1d4ed8;
    padding: 4px 10px;
    border-radius: 4px;
    font-size: 11px;
    font-weight: 600;
}

/* Validity Input Group */
.validity-input-group {
    display: flex;
    gap: 10px;
    align-items: center;
}

.checkbox-label {
    display: flex;
    align-items: center;
    gap: 6px;
    white-space: nowrap;
    margin: 0;
    cursor: pointer;
    font-weight: 600;
    color: #374151;
    padding: 6px 12px;
    background: white;
    border: 2px solid #e5e7eb;
    border-radius: 6px;
    transition: all 0.3s ease;
}

.checkbox-label:hover {
    background: #f9fafb;
    border-color: #37474F;
}

.checkbox-label input {
    cursor: pointer;
}

/* Badges */
.badge {
    padding: 4px 10px;
    border-radius: 4px;
    font-size: 11px;
    font-weight: 600;
}

.badge-danger {
    background: #fee2e2;
    color: #dc2626;
}

.badge-warning {
    background: #fef3c7;
    color: #f59e0b;
}

/* Alert Info Custom */
.alert-info-custom {
    background: #ECEFF1;
    border-left: 4px solid #37474F;
    padding: 20px;
    border-radius: 8px;
    display: flex;
    gap: 15px;
    margin-bottom: 30px;
}

.alert-info-custom i {
    color: #37474F;
    font-size: 20px;
}

.alert-info-custom strong {
    display: block;
    color: #37474F;
    margin-bottom: 5px;
}

.alert-info-custom p {
    margin: 0 0 10px 0;
    color: #37474F;
    font-size: 13px;
}

.alert-info-custom .mb-0 {
    margin-bottom: 0 !important;
}

/* Rejection Section Styling */
.rejection-section {
    margin-bottom: 10px;
}

.rejection-section p {
    margin: 8px 0;
}

.rejection-section small {
    color: #666;
    display: block;
    margin-top: 8px;
}

.ktt-rejection-item {
    background: rgba(254, 243, 199, 0.3);
    padding: 12px;
    border-radius: 6px;
    border-left: 3px solid #f59e0b;
}

.ktt-rejection-item p {
    color: #333;
    line-height: 1.6;
    font-weight: 500;
}

.ktt-rejection-item small {
    font-size: 11px;
}

/* Form Actions */
.form-actions {
    display: flex;
    gap: 16px;
    margin-top: 32px;
    padding-top: 24px;
    border-top: 2px solid #f3f4f6;
}

.btn {
    padding: 12px 24px;
    border: none;
    border-radius: 8px;
    font-size: 15px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    text-decoration: none;
}

.btn-lg {
    padding: 14px 28px;
    font-size: 16px;
}

.btn-sm {
    padding: 6px 12px;
    font-size: 12px;
}

.btn-primary {
    background: #37474F;
    color: white;
    box-shadow: 0 4px 12px rgba(55, 71, 79, 0.3);
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(23, 162, 184, 0.4);
}

.btn-secondary {
    background: #616161;
    color: white;
}

.btn-secondary:hover {
    background: #4b5563;
    transform: translateY(-2px);
}

.btn-info {
    background: #37474F;
    color: white;
    border: none;
    padding: 8px 16px;
    border-radius: 6px;
    font-size: 13px;
    font-weight: 600;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    transition: all 0.3s ease;
}

.btn-info:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
    color: white;
    text-decoration: none;
}

.btn-outline-primary {
    border: 2px solid #37474F;
    color: #37474F;
    background: white;
}

.btn-outline-primary:hover {
    background: #f0f9ff;
    transform: translateY(-1px);
}

.btn-outline-secondary {
    padding: 10px 20px;
    border: 2px solid rgba(255, 255, 255, 0.3);
    color: white;
    background: rgba(255, 255, 255, 0.1);
    border-radius: 8px;
    text-decoration: none;
    font-size: 14px;
    font-weight: 600;
    transition: all 0.3s ease;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    backdrop-filter: blur(10px);
}

.btn-outline-secondary:hover {
    background: rgba(255, 255, 255, 0.2);
    border-color: rgba(255, 255, 255, 0.5);
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

/* Responsive */
@media (max-width: 768px) {
    .page-header-add {
        flex-direction: column;
        gap: 20px;
        text-align: center;
    }
    
    .form-section {
        padding: 20px;
    }
    
    .form-actions {
        flex-direction: column;
    }
    
    .form-row {
        flex-direction: column;
    }
    
    .form-row .form-group {
        margin-bottom: 15px;
    }
}

/* Input readonly styling */
input[readonly] {
    background-color: #F9FAFB !important;
    cursor: not-allowed !important;
}
</style>

<?php require_once '../../includes/footer.php'; ?>




