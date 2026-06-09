<?php
$page_title = 'Add Employee - ' . ($_SESSION['department'] ?? 'Department');
require_once '../../includes/auth.php';
require_once '../../includes/db.php';
require_once '../../includes/notifications.php';

// Only department_user role or user with department can access this page
if (!hasDepartment() && $_SESSION['role'] != 'department_user') {
    header('Location: ../admin/dashboard.php');
    exit();
}

$db = new Database();   
$current_department = $_SESSION['department'] ?? '';
$message = '';
$error = '';

// Get certifications and posit ions for dropdown
$certifications = $db->query("SELECT * FROM certifications ORDER BY cert_name");
$certifications_data = [];
if ($certifications && $certifications->num_rows > 0) {
    $certifications->data_seek(0);
    while ($cert = $certifications->fetch_assoc()) {
        $certifications_data[$cert['id']] = $cert;
    }
}
$positions = $db->query("SELECT * FROM positions ORDER BY position_type, position_name");

// Check if competencies table exists and get competencies by type
$competencies_table_exists = false;
$check_table = $db->query("SHOW TABLES LIKE 'competencies'");
if ($check_table && $check_table->num_rows > 0) {
    $competencies_table_exists = true;
}

$competencies_by_type = [];
$competencies_with_id = []; // Store competencies with ID for JavaScript
if ($competencies_table_exists) {
    $competencies_result = $db->query("SELECT id, competency_name, position_type FROM competencies ORDER BY position_type, competency_name");
    while ($comp = $competencies_result->fetch_assoc()) {
        $type = $comp['position_type'];
        if (!isset($competencies_by_type[$type])) {
            $competencies_by_type[$type] = [];
        }
        $competencies_by_type[$type][] = $comp;
        // Store all competencies with ID for JavaScript use
        $competencies_with_id[] = $comp;
    }
}

// Get supervision areas from database
$supervision_areas = $db->query("SELECT * FROM supervision_areas ORDER BY area_name");

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $employee_code = $db->escapeString(trim($_POST['employee_code']));
    $full_name = $db->escapeString(trim($_POST['full_name']));
    $position = $db->escapeString(trim($_POST['position']));
    // Department is automatically set from session for department_user
    $department = $db->escapeString($current_department);
    $competency_type = $db->escapeString(trim($_POST['competency_type']));
    $competency_name = !empty($_POST['competency_name']) ? $db->escapeString(trim($_POST['competency_name'])) : '';
    $sub_competency = !empty($_POST['sub_competency']) ? $db->escapeString(trim($_POST['sub_competency'])) : '';
    $supervision_area = !empty($_POST['supervision_area']) ? $db->escapeString(trim($_POST['supervision_area'])) : '';
    $ruang_lingkup = !empty($_POST['ruang_lingkup']) ? $db->escapeString(trim($_POST['ruang_lingkup'])) : '';
    $contractor_company = $db->escapeString(trim($_POST['contractor_company']));
    
    // Validate required fields
    if (empty($employee_code) || empty($full_name) || empty($position) || empty($competency_type) || empty($contractor_company)) {
        $error = 'All fields are required!';
    } elseif ($competency_type == 'pengawas_operasional' && empty($supervision_area)) {
        $error = 'Supervision Area is required for Operational Supervisor!';
    } elseif (in_array($competency_type, ['pengawas_teknis', 'pengawas_operasional']) && empty($ruang_lingkup)) {
        $error = 'Scope of Work is required for Technical Supervisor and Operational Supervisor!';
    } elseif (in_array($competency_type, ['pengawas_teknis', 'tenaga_teknis']) && empty($competency_name)) {
        $error = 'Competency is required for Technical Supervisor and Technical Personnel types!';
    } elseif (!isset($_FILES['cv_file']) || $_FILES['cv_file']['error'] != 0) {
        $error = 'CV upload is required!';
    } elseif (!isset($_FILES['statement_file']) || $_FILES['statement_file']['error'] != 0) {
        $error = 'Statement Letter upload is required!';
    } else {
        // Check if employee code already exists
        $check = $db->query("SELECT id FROM employees WHERE employee_code = '$employee_code'");
        if ($check && $check->num_rows > 0) {
            $error = 'ID BADGE already in use!';
        } else {
            // Handle CV upload
            $cv_file = '';
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
                
                $new_filename = 'cv_' . $employee_code . '_' . time() . '.' . $file_extension;
                $upload_path = $upload_dir . $new_filename;
                
                if (move_uploaded_file($_FILES['cv_file']['tmp_name'], $upload_path)) {
                    $cv_file = 'uploads/cv/' . $new_filename;
                } else {
                    $error = 'Failed to upload CV file.';
                }
            }
            
            // Handle Statement upload (required)
            $statement_file = '';
            if (!$error) {
                $stmt_file_size = $_FILES['statement_file']['size'];
                $stmt_max_size = 5 * 1024 * 1024; // 5MB
                $stmt_file_extension = strtolower(pathinfo($_FILES['statement_file']['name'], PATHINFO_EXTENSION));
                
                if ($stmt_file_extension !== 'pdf') {
                    $error = 'Statement Letter must be in PDF format!';
                } elseif ($stmt_file_size > $stmt_max_size) {
                    $error = 'Statement Letter file size too large! Maximum 5MB.';
                } else {
                    $stmt_upload_dir = '../../assets/uploads/statements/';
                    if (!file_exists($stmt_upload_dir)) {
                        mkdir($stmt_upload_dir, 0777, true);
                    }
                    
                    $stmt_new_filename = 'statement_' . $employee_code . '_' . time() . '.pdf';
                    $stmt_upload_path = $stmt_upload_dir . $stmt_new_filename;
                    
                    if (move_uploaded_file($_FILES['statement_file']['tmp_name'], $stmt_upload_path)) {
                        $statement_file = 'uploads/statements/' . $stmt_new_filename;
                    } else {
                        $error = 'Failed to upload Statement Letter file.';
                    }
                }
            }
            
            // Only proceed with insert if no errors
            if (!$error) {
                // Cek struktur tabel employees terlebih dahulu
                $columns_result = $db->query("SHOW COLUMNS FROM employees");
                $available_columns = [];
                while ($col = $columns_result->fetch_assoc()) {
                    $available_columns[] = $col['Field'];
                }
                
                // Create dynamic INSERT query based on available columns
                $insert_fields = ['employee_code', 'full_name', 'position', 'department', 'competency_type', 'contractor_company', 'ruang_lingkup', 'cv_file', 'verification_status', 'is_active'];
                $insert_values = ["'$employee_code'", "'$full_name'", "'$position'", "'$department'", "'$competency_type'", "'$contractor_company'", "'$ruang_lingkup'", "'$cv_file'", "'pending'", "1"];
                
                // Add optional fields if they exist in the table
                if (in_array('competency_name', $available_columns) && !empty($competency_name)) {
                    $insert_fields[] = 'competency_name';
                    $insert_values[] = "'$competency_name'";
                }

                if (in_array('sub_competency', $available_columns) && !empty($sub_competency)) {
                    $insert_fields[] = 'sub_competency';
                    $insert_values[] = "'$sub_competency'";
                }

                if (in_array('supervision_area', $available_columns) && !empty($supervision_area)) {
                    $insert_fields[] = 'supervision_area';
                    $insert_values[] = "'$supervision_area'";
                }
                
                if (in_array('statement_file', $available_columns) && !empty($statement_file)) {
                    $insert_fields[] = 'statement_file';
                    $insert_values[] = "'$statement_file'";
                }

                // Track who created this employee record
                if (in_array('created_by', $available_columns)) {
                    $insert_fields[] = 'created_by';
                    $insert_values[] = "'" . intval($_SESSION['user_id']) . "'";
                }

                $sql = "INSERT INTO employees (" . implode(', ', $insert_fields) . ")
                        VALUES (" . implode(', ', $insert_values) . ")";
                
                if ($db->query($sql)) {
                    $employee_id = $db->lastInsertId();
                    
                    // Handle multiple certification uploads
                    if (isset($_FILES['certifications']) && !empty($_FILES['certifications']['name'][0])) {
                        $upload_dir = '../../assets/uploads/certifications/';
                        if (!file_exists($upload_dir)) {
                            mkdir($upload_dir, 0777, true);
                        }
                        
                        $cert_ids = $_POST['certification_ids'] ?? [];
                        $cert_numbers = $_POST['cert_numbers'] ?? [];
                        $cert_issuers = $_POST['cert_issuers'] ?? [];
                        $issue_dates = $_POST['issue_dates'] ?? [];
                        $expiry_dates = $_POST['expiry_dates'] ?? [];
                        $no_expiry = $_POST['no_expiry'] ?? [];
                        $expiry_reasons = $_POST['expiry_reasons'] ?? [];
                        
                        foreach ($_FILES['certifications']['tmp_name'] as $key => $tmp_name) {
                            if (isset($_FILES['certifications']['error'][$key]) && $_FILES['certifications']['error'][$key] == 0) {
                                $file_ext = pathinfo($_FILES['certifications']['name'][$key], PATHINFO_EXTENSION);
                                $cert_file = $employee_code . '_cert_' . $key . '_' . time() . '.' . $file_ext;
                                
                                if (move_uploaded_file($tmp_name, $upload_dir . $cert_file)) {
                                    $cert_path = 'uploads/certifications/' . $cert_file;
                                    $cert_id = intval($cert_ids[$key] ?? 0);
                                    $cert_number = $db->escapeString($cert_numbers[$key] ?? '');
                                    $cert_issuer = $db->escapeString($cert_issuers[$key] ?? '');
                                    $issue_date = $db->escapeString($issue_dates[$key] ?? '');
                                    $expiry_date = $db->escapeString($expiry_dates[$key] ?? '');
                                    $reason = $db->escapeString($expiry_reasons[$key] ?? '');
                                    
                                    // Check if expired
                                    $today = date('Y-m-d');
                                    $status = ($expiry_date && $expiry_date < $today) ? 'expired' : 'pending';
                                    
                                    $sql_cert = "INSERT INTO employee_certifications 
                                                (employee_id, certification_id, cert_number, cert_issuer, issue_date, expiry_date, 
                                                 document_file, status, verification_status, expiry_reason) 
                                                VALUES ($employee_id, $cert_id, '$cert_number', '$cert_issuer', '$issue_date', '$expiry_date', 
                                                        '$cert_path', '$status', 'pending', '$reason')";
                                    
                                    if (!$db->query($sql_cert)) {
                                        error_log("Error inserting certification: " . $db->getConnection()->error);
                                    }
                                }
                            }
                        }
                    }
                    
                    // Send notification to admin - with timeout protection
                    try {
                        set_time_limit(60); // Allow extra time for email sending
                        $notificationService = new NotificationService();
                        $company_display = !empty($contractor_company) ? $contractor_company : "Department: $current_department";
                        $notificationService->notifyNewEmployeeAdded($employee_id, $company_display);
                    } catch (Exception $e) {
                        error_log("Notification error: " . $e->getMessage());
                    }
                    
                    $message = 'Employee successfully added! Waiting for verification from Admin.';
                    // Redirect after 2 seconds
                    header("refresh:2;url=employees.php");
                } else {
                    $error = 'Failed to add employee!';
                    error_log("Error inserting employee: " . $db->getConnection()->error);
                }
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
            <h2><i class="fas fa-user-plus"></i> <span data-lang="add-new-request-employee">Add New Request Employee</span></h2>
            <p>Department: <strong><?php echo htmlspecialchars($current_department); ?></strong></p>
        </div>
        <a href="employees.php" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left"></i> Back
        </a>
    </div>
    
    <?php if ($message): ?>
    <div class="alert alert-success alert-custom-add">
        <i class="fas fa-check-circle"></i>
        <div>
            <strong>Success!</strong>
            <p><?php echo htmlspecialchars($message); ?></p>
        </div>
    </div>
    <?php endif; ?>
    
    <?php if ($error): ?>
    <div class="alert alert-error alert-custom-add">
        <i class="fas fa-exclamation-circle"></i>
        <div>
            <strong>Error!</strong>
            <p><?php echo htmlspecialchars($error); ?></p>
        </div>
    </div>
    <?php endif; ?>
    
    <form method="POST" action="" enctype="multipart/form-data" class="form-container">
        <!-- Section 1: Data Identitas & Kompetensi -->
        <div class="form-section">
            <div class="section-header">
                <h3><i class="fas fa-id-card"></i> <span data-lang="identity-competency-data">Identity & Competency Data</span></h3>
                <span class="section-number">1</span>
            </div>
            
            <div class="form-row">
                <div class="form-group col-lg-6">
                    <label for="employee_code" data-lang="id-badge-required">ID BADGE <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="employee_code" name="employee_code"
                           value="<?php echo isset($_POST['employee_code']) ? htmlspecialchars($_POST['employee_code']) : ''; ?>"
                           required placeholder="Example: BADGE001" data-lang-placeholder="badge-example-placeholder">
                    <small class="form-hint" data-lang="unique-id-badge-hint">Unique ID for employee badge identification</small>
                </div>
                
                <div class="form-group col-lg-6">
                    <label for="full_name" data-lang="full-name">Full Name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="full_name" name="full_name"
                           value="<?php echo isset($_POST['full_name']) ? htmlspecialchars($_POST['full_name']) : ''; ?>"
                           required placeholder="Employee full name" data-lang-placeholder="employee-full-name-placeholder">
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group col-lg-6">
                    <label for="position" data-lang="position">Position <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="position" name="position"
                           value="<?php echo isset($_POST['position']) ? htmlspecialchars($_POST['position']) : ''; ?>"
                           required placeholder="Example: Rigger, HSE Superintendent" data-lang-placeholder="position-example-placeholder">
                </div>
                
                <div class="form-group col-lg-6" id="ruang_lingkup_group" style="display: none;">
                    <label for="ruang_lingkup" data-lang="scope-of-work">Scope of Work <span class="text-danger">*</span></label>
                    <select class="form-control" id="ruang_lingkup" name="ruang_lingkup">
                        <option value="" data-lang="select-scope-of-work">-- Select Scope of Work --</option>
                        <option value="PT Meares Soputan Mining (MSM)" <?php echo (isset($_POST['ruang_lingkup']) && $_POST['ruang_lingkup'] == 'PT Meares Soputan Mining (MSM)') ? 'selected' : ''; ?>>PT MSM</option>
                        <option value="PT Tambang Tondano Nusajaya (TTN)" <?php echo (isset($_POST['ruang_lingkup']) && $_POST['ruang_lingkup'] == 'PT Tambang Tondano Nusajaya (TTN)') ? 'selected' : ''; ?>>PT TTN</option>
                    </select>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group col-lg-6">
                    <label for="department" data-lang="department">Department</label>
                    <input type="text" class="form-control" id="department" name="department"
                           value="<?php echo htmlspecialchars($current_department); ?>" readonly>
                    <small class="form-hint" data-lang="auto-filled-from-account">Automatically filled from your account</small>
                </div>
                
                <div class="form-group col-lg-6">
                    <label for="contractor_company" data-lang="company">Company</label>
                    <select class="form-control" id="contractor_company" name="contractor_company" required>
                        <option value="" data-lang="select-company">-- Select Company --</option>
                        <option value="PT Meares Soputan Mining" <?php echo (isset($_POST['contractor_company']) && $_POST['contractor_company'] == 'PT Meares Soputan Mining') ? 'selected' : ''; ?>>PT Meares Soputan Mining</option>
                        <option value="PT Tambang Tondano Nusajaya" <?php echo (isset($_POST['contractor_company']) && $_POST['contractor_company'] == 'PT Tambang Tondano Nusajaya') ? 'selected' : ''; ?>>PT Tambang Tondano Nusajaya</option>
                    </select>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group col-lg-6">
                    <label for="competency_type" data-lang="competency-type">Competency Type <span class="text-danger">*</span></label>
                    <select class="form-control" id="competency_type" name="competency_type" onchange="toggleCompetencyField()" required>
                        <option value="" data-lang="select-competency-type">-- Select Competency Type --</option>
                        <option value="pengawas_operasional" <?php echo (isset($_POST['competency_type']) && $_POST['competency_type'] == 'pengawas_operasional') ? 'selected' : ''; ?>>Pengawas Operasional</option>
                        <option value="pengawas_teknis" <?php echo (isset($_POST['competency_type']) && $_POST['competency_type'] == 'pengawas_teknis') ? 'selected' : ''; ?>>Pengawas Teknis</option>
                        <option value="tenaga_teknis" <?php echo (isset($_POST['competency_type']) && $_POST['competency_type'] == 'tenaga_teknis') ? 'selected' : ''; ?>>Tenaga Teknis</option>
                    </select>
                </div>
                
                <div class="form-group col-lg-6" id="supervision_area_group" style="display: none;">
                    <label for="supervision_area" data-lang="supervision-area">Supervision Area <span class="text-danger">*</span></label>
                    <select class="form-control" id="supervision_area" name="supervision_area">
                        <option value="" data-lang="select-supervision-area">-- Select Supervision Area --</option>
                        <?php
                        if ($supervision_areas && $supervision_areas->num_rows > 0) {
                            $supervision_areas->data_seek(0);
                            while ($area = $supervision_areas->fetch_assoc()):
                                $selected = (isset($_POST['supervision_area']) && $_POST['supervision_area'] == $area['area_name']) ? 'selected' : '';
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
                    <select class="form-control" id="competency_name" name="competency_name" onchange="loadSubCompetencies()">
                        <option value="" data-lang="select-competency">-- Select Competency --</option>
                        <?php
                        // Populate competencies for pengawas_operasional
                        if (!empty($competencies_by_type['pengawas_operasional'])) {
                            foreach ($competencies_by_type['pengawas_operasional'] as $comp):
                        ?>
                            <option value="<?php echo htmlspecialchars($comp['competency_name']); ?>" data-id="<?php echo $comp['id']; ?>" data-type="pengawas_operasional" <?php echo (isset($_POST['competency_name']) && $_POST['competency_name'] == $comp['competency_name']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($comp['competency_name']); ?>
                            </option>
                        <?php
                            endforeach;
                        }
                        // Populate competencies for pengawas_teknis
                        if (!empty($competencies_by_type['pengawas_teknis'])) {
                            foreach ($competencies_by_type['pengawas_teknis'] as $comp):
                        ?>
                            <option value="<?php echo htmlspecialchars($comp['competency_name']); ?>" data-id="<?php echo $comp['id']; ?>" data-type="pengawas_teknis" <?php echo (isset($_POST['competency_name']) && $_POST['competency_name'] == $comp['competency_name']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($comp['competency_name']); ?>
                            </option>
                        <?php
                            endforeach;
                        }
                        // Populate competencies for tenaga_teknis
                        if (!empty($competencies_by_type['tenaga_teknis'])) {
                            foreach ($competencies_by_type['tenaga_teknis'] as $comp):
                        ?>
                            <option value="<?php echo htmlspecialchars($comp['competency_name']); ?>" data-id="<?php echo $comp['id']; ?>" data-type="tenaga_teknis" <?php echo (isset($_POST['competency_name']) && $_POST['competency_name'] == $comp['competency_name']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($comp['competency_name']); ?>
                            </option>
                        <?php
                            endforeach;
                        }
                        ?>
                    </select>
                </div>

                <div class="form-group col-lg-6" id="sub_competency_group" style="display: none;">
                    <label for="sub_competency" data-lang="sub-competency">Sub Competency</label>
                    <select class="form-control" id="sub_competency" name="sub_competency">
                        <option value="" data-lang="select-sub-competency">-- Select Sub Competency --</option>
                    </select>
                </div>
            </div>

            <!-- File Upload Section -->
            <div class="form-row">
                <div class="form-group col-lg-6">
                    <label for="cv_file" data-lang="upload-cv">Upload CV <span class="text-danger">*</span></label>
                    <div class="file-upload-area">
                        <i class="fas fa-file-upload"></i>
                        <input type="file" name="cv_file" id="cv_file" class="file-input" accept=".pdf" required>
                        <span class="file-text" data-lang="click-drag-cv-file">Click or drag CV file(PDF, Max 5MB)</span>
                        <span class="file-name"></span>
                    </div>
                </div>
                
                <div class="form-group col-lg-6">
                    <label for="statement_file" data-lang="upload-statement-letter">Upload Statement Letter <span class="text-danger">*</span></label>
                    <div class="file-upload-area">
                        <i class="fas fa-file-signature"></i>
                        <input type="file" name="statement_file" id="statement_file" class="file-input" accept=".pdf" required>
                        <span class="file-text" data-lang="click-drag-statement-letter">Click or drag Statement Letter(PDF, Max 5MB)</span>
                        <span class="file-name"></span>
                    </div>
                </div>
            </div>
            
            <div class="alert alert-warning-custom" style="margin-bottom: 0;">
                <i class="fas fa-exclamation-triangle"></i>
                <div>
                    <strong>Important - Statement Letter:</strong>
                    <p style="margin-bottom: 8px;">The statement letter must be signed with an <strong>original signature (wet signature)</strong> by the person concerned, then scanned in PDF format.</p>
                    <a href="https://drive.google.com/drive/folders/176NPnFCvAnzp2Mb9vrA2RC5OMA45Hga1?usp=sharing" class="btn btn-info btn-sm" target="_blank" style="margin-top: 5px;">
                        <i class="fas fa-download"></i> Download Statement Letter Template
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Section 2: Sertifikasi -->
        <div class="form-section">
            <div class="section-header">
                <h3><i class="fas fa-certificate"></i> <span data-lang="certification-competency">Certification/Competency</span></h3>
                <span class="section-number">2</span>
            </div>
            
            <div id="certificationContainer" class="certifications-list">
                <div class="certification-item">
                    <div class="cert-item-header">
                        <h5><i class="fas fa-file-certificate"></i> <span data-lang="certification-number-1">Certification #1</span></h5>
                        <div class="cert-header-actions">
                            <!-- Remove button will appear for additional certifications -->
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group col-lg-4">
                            <label data-lang="certification-name">Certification Name <span class="text-danger">*</span></label>
                            <select name="certification_ids[]" class="form-control cert-name-select" required onchange="updateIssuer(this)">
                                <option value="" data-lang="select-certification">-- Select Certification --</option>
                                <?php
                                if ($certifications && $certifications->num_rows > 0) {
                                    $certifications->data_seek(0);
                                    while ($cert = $certifications->fetch_assoc()):
                                    ?>
                                    <option value="<?php echo $cert['id']; ?>" data-issuer="<?php echo htmlspecialchars($cert['cert_issuer'] ?? ''); ?>">
                                        <?php echo htmlspecialchars($cert['cert_name']); ?>
                                    </option>
                                    <?php 
                                    endwhile;
                                }
                                ?>
                            </select>
                        </div>
                        <div class="form-group col-lg-4">
                            <label data-lang="certificate-type">Certificate Type <span class="text-danger">*</span></label>
                            <select name="cert_types[]" class="form-control cert-type-select" required onchange="toggleOtherType(this)">
                                <option value="" data-lang="select-type">-- Select Type --</option>
                                <option value="Attendance/Peserta" data-lang="attendance-participant">Attendance/Participant</option>
                                <option value="Kompeten" data-lang="competent">Competent</option>
                                <option value="Training" data-lang="training">Training</option>
                            </select>
                        </div>
                        <div class="form-group col-lg-4 other-type-input" style="display: none;">
                            <label data-lang="other-type">Other Type <span class="text-danger">*</span></label>
                            <input type="text" name="cert_types_other[]" class="form-control" placeholder="Enter certificate type" data-lang-placeholder="enter-certificate-type">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group col-lg-6">
                            <label data-lang="certificate-no">Certificate No. <span class="text-danger">*</span></label>
                            <input type="text" name="cert_numbers[]" class="form-control" required placeholder="Certificate number" data-lang-placeholder="certificate-number-placeholder">
                        </div>
                        <div class="form-group col-lg-6">
                            <label data-lang="issuer">Issuer <span class="text-danger">*</span></label>
                            <input type="text" name="cert_issuers[]" class="form-control" required placeholder="Issuer/certification body name" data-lang-placeholder="issuer-certification-body-name">
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
                                <input type="number" name="validity_years[]" class="form-control validity-years" min="0" step="0.5" placeholder="Years" data-lang-placeholder="years" onchange="calculateExpiryDate(this)">
                                <label class="checkbox-label">
                                    <input type="checkbox" name="no_expiry[]" class="no-expiry-check" onchange="toggleExpiryField(this)">
                                    <span data-lang="no-expiry">No Expiry</span>
                                </label>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group col-lg-6">
                            <label data-lang="expiry-date">Expiry Date <span class="text-danger">*</span></label>
                            <input type="date" name="expiry_dates[]" class="form-control expiry-date" readonly>
                        </div>
                        <div class="form-group col-lg-6">
                        </div>
                    </div>
                    
                    <div class="form-group other-expiry-reason" style="display: none;">
                        <label data-lang="notes">Notes <span class="text-danger">*</span></label>
                        <textarea name="expiry_reasons[]" class="form-control" placeholder="Explain the reason..." data-lang-placeholder="explain-the-reason" rows="2"></textarea>
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
                </div>
            </div>
            
            <button type="button" class="btn btn-outline-primary" onclick="addCertification()">
                <i class="fas fa-plus-circle"></i> <span data-lang="add-another-certification">Add Another Certification</span>
            </button>
        </div>

        <!-- Info Alert -->
        <div class="alert alert-info-custom">
            <i class="fas fa-lightbulb"></i>
            <div>
                <strong data-lang="important-note">Important Note</strong>
                <p data-lang="after-employee-data-added-note">After the employee data is added, the status will be "Pending" and awaiting verification from Admin before an Appointment Letter can be created.</p>
            </div>
        </div>
        
        <!-- Form Actions -->
        <div class="form-actions">
            <button type="submit" class="btn btn-primary btn-lg">
                <i class="fas fa-save"></i> <span data-lang="save-submit-verification">Save & Submit for Verification</span>
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
const competenciesWithId = <?php echo json_encode($competencies_with_id); ?>;

// Store POST values for restoring after error
const postSubCompetency = '<?php echo isset($_POST['sub_competency']) ? addslashes($_POST['sub_competency']) : ''; ?>';

// Debug: Tampilkan data di console
console.log('certificationsData:', certificationsData);
console.log('competenciesWithId:', competenciesWithId);

// Load sub-competencies based on selected competency
async function loadSubCompetencies() {
    const competencySelect = document.getElementById('competency_name');
    const subCompetencySelect = document.getElementById('sub_competency');
    const selectedOption = competencySelect.options[competencySelect.selectedIndex];
    
    // Clear previous options
        const selectSubCompetencyText = window.getLanguageText('select-sub-competency', '-- Select Sub Competency --');
    const competencyId = selectedOption.getAttribute('data-id');

    if (!competencyId) {
        console.warn('Competency ID not found');
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
                // Use subComp.name as value, with fallback to id to prevent undefined
                const subCompName = subComp.name || subComp.id || '';
                const subCompDisplay = subComp.name || `Sub-Competency #${subComp.id}` || '';
                
                if (subCompName === '' || subCompName === undefined) {
                    console.warn('Invalid sub-competency:', subComp);
                    return; // Skip this option if no valid name/id
                }
                
                option.value = subCompName;
                option.textContent = subCompDisplay;
                option.title = subComp.description || '';
                option.setAttribute('data-id', subComp.id);
                
                // Check if this option should be selected from POST
                if (postSubCompetency && subCompName === postSubCompetency) {
                    option.selected = true;
                }
                subCompetencySelect.appendChild(option);
            });

            console.log('Sub-competencies loaded:', result.data);
            // Force value restoration after options are added
            if (postSubCompetency && postSubCompetency !== '') {
                subCompetencySelect.value = postSubCompetency;
                console.log('Sub-competency restored to:', postSubCompetency);
            }
        } else {
            console.log('No sub-competencies found for this competency');
        }
    } catch (error) {
        console.error('Error loading sub-competencies:', error);
    }

    // Show or hide sub_competency field based on competency type and selection
    toggleSubCompetency();
}

function toggleCompetencyField() {
    const competencyType = document.getElementById('competency_type').value;
    const supervisionAreaGroup = document.getElementById('supervision_area_group');
    const competencyGroup = document.getElementById('competency_group');
    const ruangLingkupGroup = document.getElementById('ruang_lingkup_group');
    const subCompetencyGroup = document.getElementById('sub_competency_group');
    const competencyInput = document.getElementById('competency_name');
    const supervisionInput = document.getElementById('supervision_area');
    const ruangLingkupInput = document.getElementById('ruang_lingkup');
    const subCompetencyInput = document.getElementById('sub_competency');

    // Reset required attributes
    competencyInput.removeAttribute('required');
    supervisionInput.removeAttribute('required');
    ruangLingkupInput.removeAttribute('required');
    subCompetencyInput.removeAttribute('required');

    // Hide sub_competency by default
    subCompetencyGroup.style.display = 'none';
    subCompetencyInput.value = '';

    if (competencyType === 'pengawas_operasional') {
        ruangLingkupGroup.style.display = 'block';
        ruangLingkupInput.setAttribute('required', 'required');
        // Tampilkan kedua field untuk pengawas operasional
        supervisionAreaGroup.style.display = 'block';
        competencyGroup.style.display = 'block';
        subCompetencyGroup.style.display = 'none';

        // Kedua field wajib diisi
        competencyInput.setAttribute('required', 'required');
        supervisionInput.setAttribute('required', 'required');

        // Filter kompetensi
        filterCompetencies('pengawas_operasional');
    } else if (competencyType === 'pengawas_teknis') {
        // Pengawas Teknis: show ruang_lingkup and competency, hide supervision_area
        ruangLingkupGroup.style.display = 'block';
        ruangLingkupInput.setAttribute('required', 'required');
        supervisionAreaGroup.style.display = 'none';
        competencyGroup.style.display = 'block';
        competencyInput.setAttribute('required', 'required');
        supervisionInput.removeAttribute('required');
        filterCompetencies(competencyType);
    } else if (competencyType === 'tenaga_teknis') {
        // Tenaga Teknis: show ruang_lingkup and competency, hide supervision_area
        ruangLingkupGroup.style.display = 'block';
        ruangLingkupInput.setAttribute('required', 'required');
        supervisionAreaGroup.style.display = 'none';
        competencyGroup.style.display = 'block';
        competencyInput.setAttribute('required', 'required');
        supervisionInput.removeAttribute('required');
        filterCompetencies(competencyType);

        // Check if competency is already selected, show sub_competency
        toggleSubCompetency();
    } else {
        // Sembunyikan semua jika tidak ada tipe dipilih
        ruangLingkupGroup.style.display = 'none';
        supervisionAreaGroup.style.display = 'none';
        competencyGroup.style.display = 'none';
    }
}

// Function to toggle Sub Competency field for Tenaga Teknis
// Only show sub competency for allowed competencies: Juru Las, Juru Ledak
function toggleSubCompetency() {
    const competencyType = document.getElementById('competency_type').value;
    const competencyInput = document.getElementById('competency_name');
    const subCompetencyGroup = document.getElementById('sub_competency_group');
    const subCompetencyInput = document.getElementById('sub_competency');
    
    // Allowed competencies with sub-competencies
    const ALLOWED_COMPETENCIES_WITH_SUB = ['Juru Las', 'Juru Ledak'];
    const selectedCompetency = competencyInput.value.trim();
    const isAllowedCompetency = ALLOWED_COMPETENCIES_WITH_SUB.includes(selectedCompetency);

    if (competencyType === 'tenaga_teknis' && selectedCompetency !== '' && isAllowedCompetency) {
        subCompetencyGroup.style.display = 'block';
        // subCompetency is optional for now; do not set required
    } else {
        subCompetencyGroup.style.display = 'none';
        subCompetencyInput.removeAttribute('required');
        subCompetencyInput.value = '';
    }
}

function filterCompetencies(competencyType) {
    const competencySelect = document.getElementById('competency_name');
    const options = competencySelect.querySelectorAll('option');
    
    options.forEach(option => {
        if (option.value === '') {
            option.style.display = 'block';
        } else if (competencyType === 'pengawas_teknis') {
            // Pengawas Teknis uses the same competency list as Tenaga Teknis
            const optionType = option.getAttribute('data-type');
            if (optionType === 'tenaga_teknis') {
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
    const certTypeSelect = certItem.querySelector('select[name="cert_types[]"]');
    const otherTypeInput = certItem.querySelector('.other-type-input');
    
    // Reset fields - user harus input manual
    issuerInput.value = '';
    issuerInput.readOnly = false;
    issuerInput.style.backgroundColor = '';
    issuerInput.style.cursor = 'auto';
    
    certTypeSelect.value = '';
    certTypeSelect.disabled = false;
    certTypeSelect.style.backgroundColor = '';
    certTypeSelect.style.cursor = 'auto';
    otherTypeInput.style.display = 'none';
}

function toggleOtherType(selectElement) {
    const certItem = selectElement.closest('.certification-item');
    const otherTypeInput = certItem.querySelector('.other-type-input');
    
    if (selectElement.value === 'Lainnya') {
        otherTypeInput.style.display = 'block';
    } else {
        otherTypeInput.style.display = 'none';
    }
}

function toggleExpiryField(checkboxElement) {
    const certItem = checkboxElement.closest('.certification-item');
    const expiryDateInput = certItem.querySelector('input[name="expiry_dates[]"]');
    const otherExpiryReason = certItem.querySelector('.other-expiry-reason');
    
    if (checkboxElement.checked) {
        expiryDateInput.value = '';
        expiryDateInput.readOnly = true;
        otherExpiryReason.style.display = 'block';
    } else {
        expiryDateInput.readOnly = false;
        otherExpiryReason.style.display = 'none';
    }
}

function calculateExpiryDate(inputElement) {
    const certItem = inputElement.closest('.certification-item');
    const issueDateInput = certItem.querySelector('input[name="issue_dates[]"]');
    const validityYearsInput = certItem.querySelector('input[name="validity_years[]"]');
    const expiryDateInput = certItem.querySelector('input[name="expiry_dates[]"]');
    const noExpiryCheck = certItem.querySelector('input[name="no_expiry[]"]');
    
    if (noExpiryCheck.checked) {
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

        <div class="form-row">
            <div class="form-group col-lg-4">
                <label data-lang="certification-name">Certification Name <span class="text-danger">*</span></label>
                <select name="certification_ids[]" class="form-control cert-name-select" required onchange="updateIssuer(this)">
                    <option value="" data-lang="select-certification">-- Select Certification --</option>
                    ${getCertificationOptions()}
                </select>
            </div>
            <div class="form-group col-lg-4">
                <label data-lang="certificate-type">Certificate Type <span class="text-danger">*</span></label>
                <select name="cert_types[]" class="form-control cert-type-select" required onchange="toggleOtherType(this)">
                    <option value="" data-lang="select-type">-- Select Type --</option>
                    <option value="Attendance/Peserta" data-lang="attendance-participant">Attendance/Participant</option>
                    <option value="Kompeten" data-lang="competent">Competent</option>
                    <option value="Training" data-lang="training">Training</option>
                </select>
            </div>
            <div class="form-group col-lg-4 other-type-input" style="display: none;">
                <label data-lang="other-type">Other Type <span class="text-danger">*</span></label>
                <input type="text" name="cert_types_other[]" class="form-control" placeholder="Enter certificate type" data-lang-placeholder="enter-certificate-type">
            </div>
        </div>

        <div class="form-row">
            <div class="form-group col-lg-6">
                <label data-lang="certificate-no">Certificate No. <span class="text-danger">*</span></label>
                <input type="text" name="cert_numbers[]" class="form-control" required placeholder="Certificate number" data-lang-placeholder="certificate-number-placeholder">
            </div>
            <div class="form-group col-lg-6">
                <label data-lang="issuer">Issuer <span class="text-danger">*</span></label>
                <input type="text" name="cert_issuers[]" class="form-control" required placeholder="Issuer/certification body name" data-lang-placeholder="issuer-certification-body-name">
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
                    <input type="number" name="validity_years[]" class="form-control validity-years" min="0" step="0.5" placeholder="Years" data-lang-placeholder="years" onchange="calculateExpiryDate(this)">
                    <label class="checkbox-label">
                        <input type="checkbox" name="no_expiry[]" class="no-expiry-check" onchange="toggleExpiryField(this)">
                        <span data-lang="no-expiry">No Expiry</span>
                    </label>
                </div>
            </div>
        </div>

        <div class="form-row">
            <div class="form-group col-lg-6">
                <label data-lang="expiry-date">Expiry Date <span class="text-danger">*</span></label>
                <input type="date" name="expiry_dates[]" class="form-control expiry-date" readonly>
            </div>
            <div class="form-group col-lg-6">
            </div>
        </div>

        <div class="form-group other-expiry-reason" style="display: none;">
            <label data-lang="notes">Notes <span class="text-danger">*</span></label>
            <textarea name="expiry_reasons[]" class="form-control" placeholder="Explain the reason..." data-lang-placeholder="explain-the-reason" rows="2"></textarea>
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

// Auto-select Scope of Work (ruang_lingkup) based on selected company
function setRuangLingkupFromCompany() {
    const companySelect = document.getElementById('contractor_company');
    const ruangSelect = document.getElementById('ruang_lingkup');
    if (!companySelect || !ruangSelect) return;

    const mapping = {
        'PT Meares Soputan Mining': 'PT Meares Soputan Mining (MSM)',
        'PT Tambang Tondano Nusajaya': 'PT Tambang Tondano Nusajaya (TTN)'
    };

    const selectedCompany = companySelect.value;
    const ruangValue = mapping[selectedCompany] || '';

    // Set value if option exists
    let found = false;
    for (let i = 0; i < ruangSelect.options.length; i++) {
        if (ruangSelect.options[i].value === ruangValue) {
            ruangSelect.selectedIndex = i;
            found = true;
            break;
        }
    }

    if (!found) {
        // clear selection
        ruangSelect.value = '';
    }
}

function updateCertificationNumbers() {
    const container = document.getElementById('certificationContainer');
    const certItems = container.querySelectorAll('.certification-item');

    certItems.forEach((item, index) => {
        const header = item.querySelector('.cert-item-header h5');
        const certLabel = window.getLanguageText('certification', 'Certification');
        header.innerHTML = `<i class="fas fa-file-certificate"></i> ${certLabel} #${index + 1}`;

        // Show/hide remove button based on index
        const actionsDiv = item.querySelector('.cert-header-actions');
        if (index === 0) {
            // First item - hide remove button
            actionsDiv.innerHTML = '';
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

document.querySelectorAll('.file-upload-area').forEach(area => {
    setupFileUpload(area);
});

function updateFileName(area, file) {
    if (file) {
        area.querySelector('.file-name').textContent = file.name;
        area.querySelector('.file-name').style.display = 'block';
    }
}

document.addEventListener('DOMContentLoaded', function() {
    const initialCertItem = document.querySelector('.certification-item');
    if (initialCertItem) {
        const certTypeSelect = initialCertItem.querySelector('.cert-type-select');
        certTypeSelect.onchange = function() { toggleOtherType(this); };
        
        const certNameSelect = initialCertItem.querySelector('.cert-name-select');
        certNameSelect.onchange = function() { updateIssuer(this); };
        
        if (certNameSelect.value) {
            updateIssuer(certNameSelect);
        }
        
        const issueDate = initialCertItem.querySelector('input[name="issue_dates[]"]');
        issueDate.onchange = function() { calculateExpiryDate(this); };
        
        const validityYears = initialCertItem.querySelector('input[name="validity_years[]"]');
        validityYears.onchange = function() { calculateExpiryDate(this); };
        
        const noExpiryCheck = initialCertItem.querySelector('input[name="no_expiry[]"]');
        noExpiryCheck.onchange = function() { toggleExpiryField(this); };
    }
    
    if (document.getElementById('competency_type').value) {
        toggleCompetencyField();
        // Load sub-competencies if competency_name is already selected (after POST error)
        if (document.getElementById('competency_name').value) {
            loadSubCompetencies();
        }
    }

    // Wire company -> ruang_lingkup auto-selection
    const companySelect = document.getElementById('contractor_company');
    if (companySelect) {
        companySelect.addEventListener('change', setRuangLingkupFromCompany);
        // Run once on load to restore selection after POST or initial state
        setRuangLingkupFromCompany();
    }
});
</script>

<style>
.add-employee-container {
    padding: 20px 0;
    max-width: 1400px;
    margin: 0 auto;
}

/* Page Header */
.page-header-add {
    background: #F57C00;
    color: white;
    padding: 35px 30px;
    border-radius: 10px;
    margin-bottom: 30px;
    box-shadow: 0 4px 15px rgba(245, 124, 0, 0.3);
    display: flex;
    justify-content: space-between;
    align-items: center;
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

/* Alert Custom */
.alert-custom-add {
    display: flex;
    gap: 15px;
    align-items: flex-start;
    padding: 20px;
    margin-bottom: 30px;
    border-radius: 8px;
    border-left: 4px solid;
}

.alert-success.alert-custom-add {
    background: #E8F5E9;
    border-left-color: #2E7D32;
}

.alert-success.alert-custom-add i {
    color: #2E7D32;
    font-size: 20px;
}

.alert-error.alert-custom-add {
    background: #fee2e2;
    border-left-color: #ef4444;
}

.alert-error.alert-custom-add i {
    color: #ef4444;
    font-size: 20px;
}

.alert-warning-custom {
    background: linear-gradient(135deg, #fef3c7, #fde68a);
    border-left: 4px solid #f59e0b;
    padding: 18px 20px;
    border-radius: 10px;
    display: flex;
    gap: 16px;
    align-items: flex-start;
}

.alert-warning-custom i {
    color: #f59e0b;
    font-size: 22px;
    flex-shrink: 0;
}

.alert-warning-custom strong {
    display: block;
    color: #92400e;
    margin-bottom: 6px;
    font-size: 14px;
}

.alert-warning-custom p {
    margin: 0;
    color: #92400e;
    font-size: 13px;
    line-height: 1.5;
}

.alert-custom strong {
    display: block;
    margin-bottom: 6px;
    font-size: 14px;
}

.alert-custom p {
    margin: 0;
    line-height: 1.5;
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
    font-size: 20px;
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

/* Form Row */
.form-row {
    display: flex;
    gap: 20px;
    margin-bottom: 0;
}

.form-row .form-group {
    flex: 1;
}
/* Form Group */
.form-group {
    margin-bottom: 24px;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: 600;
    color: #374151;
    font-size: 14px;
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
    box-sizing: border-box;
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

.form-hint {
    display: block;
    margin-top: 6px;
    color: #616161;
    font-size: 12px;
    font-style: italic;
}

.text-danger {
    color: #ef4444;
    font-weight: 700;
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
    color: #616161;
    font-size: 13px;
    line-height: 1.6;
    font-weight: 500;
}

.file-name {
    display: none;
    color: #37474F;
    font-weight: 600;
    font-size: 13px;
    margin-top: 10px;
    word-break: break-all;
}

.file-name-display {
    display: none;
    color: #37474F;
    font-weight: 600;
    font-size: 14px;
    margin-top: 15px;
    padding: 8px 16px;
    background: #E8F5E9;
    border-radius: 6px;
    max-width: 90%;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

/* Certification Item Styles */
.certification-item {
    background: #f8f9fa;
    border: 1px solid #e9ecef;
    border-radius: 8px;
    padding: 20px;
    margin-bottom: 15px;
    border-left: 4px solid #37474F;
}

.cert-item-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
    padding-bottom: 10px;
    border-bottom: 2px solid #ddd;
}

.cert-item-header h5 {
    margin: 0;
    color: #333;
    font-size: 15px;
    display: flex;
    align-items: center;
    gap: 8px;
    font-weight: 600;
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
    gap: 12px;
    align-items: center;
}

.validity-years {
    flex: 1;
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
    width: 16px;
    height: 16px;
}

/* Alert Info Custom */
.alert-info-custom {
    background: #ECEFF1;
    border-left: 4px solid #37474F;
    padding: 18px 20px;
    border-radius: 8px;
    color: #37474F;
    margin-top: 20px;
    font-size: 13px;
    line-height: 1.6;
    display: flex;
    gap: 15px;
    align-items: flex-start;
}

.alert-info-custom i {
    color: #37474F;
    font-size: 20px;
    flex-shrink: 0;
}

.alert-info-custom strong {
    display: block;
    margin-bottom: 4px;
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
    font-size: 15px;
}

.btn-primary {
    background: linear-gradient(135deg, #37474F, #616161);
    color: white;
    box-shadow: 0 4px 14px rgba(102, 126, 234, 0.3);
}

.btn-primary:hover {
    background: #37474F;
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(23, 162, 184, 0.4);
}

.btn-secondary {
    background: #6c757d;
    color: white;
    box-shadow: 0 2px 8px rgba(108, 117, 125, 0.2);
}

.btn-secondary:hover {
    background: #5a6268;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(108, 117, 125, 0.3);
}

.btn-outline-primary {
    border: 2px solid #37474F;
    color: #37474F;
    background: white;
    padding: 12px 20px;
    font-weight: 600;
    width: 100%;
    margin-top: 15px;
    justify-content: center;
}

.btn-outline-primary:hover {
    background: #e8f7fa;
}

.btn-info {
    background: #37474F;
    color: white;
    font-size: 13px;
    padding: 8px 16px;
    box-shadow: 0 2px 8px rgba(59, 130, 246, 0.2);
}

.btn-info:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
}

/* Input readonly styling */
input[readonly],
select[disabled] {
    background-color: #f9fafb !important;
    cursor: not-allowed !important;
    border-color: #d1d5db !important;
    color: #616161 !important;
}

/* Responsive */
@media (max-width: 768px) {
    .add-employee-container {
        padding: 16px 0;
    }
    
    .page-header-add {
        flex-direction: column;
        gap: 16px;
        text-align: center;
        padding: 24px 20px;
    }
    
    .header-left h2 {
        font-size: 20px;
        justify-content: center;
    }
    
    .form-section {
        padding: 20px;
        border-radius: 8px;
    }
    
    .section-header {
        margin-bottom: 20px;
    }
    
    .section-number {
        width: 32px;
        height: 32px;
        font-size: 14px;
    }
    
    .form-actions {
        flex-direction: column;
    }
    
    .btn-lg {
        width: 100%;
        justify-content: center;
    }
    
    .form-row {
        flex-direction: column;
        gap: 0;
    }
    
    .form-row .form-group {
        margin-bottom: 20px;
    }
    
    .file-upload-area {
        min-height: 140px;
        padding: 24px 16px;
    }
    
    .certification-item {
        padding: 16px;
    }
}

@media (max-width: 480px) {
    .form-section {
        padding: 16px;
        margin-bottom: 16px;
    }
    
    .section-header h3 {
        font-size: 16px;
    }
    
    .form-control {
        padding: 10px 14px;
        font-size: 13px;
    }
}
</style>

<?php require_once '../../includes/footer.php'; ?>




