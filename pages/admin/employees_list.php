<?php
$page_title = 'Contractor Workforce Data';
require_once '../../includes/auth.php';
require_once '../../includes/db.php';

$db = new Database();
$message = '';
$error = '';

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

// Handle form submission to add data
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action']) && $_POST['action'] == 'add') {
        $employee_code = $db->escapeString($_POST['employee_code']);
        $full_name = $db->escapeString($_POST['full_name']);
        $position = $db->escapeString($_POST['position']);
        $department = $db->escapeString($_POST['department']);
        $contractor_company = $db->escapeString($_POST['contractor_company']);
        $competency_type = $db->escapeString($_POST['competency_type']);
        $competency_name = !empty($_POST['competency_name']) ? $db->escapeString($_POST['competency_name']) : '';
        
        // Validate competency for specific types
        if (in_array($competency_type, ['pengawas_teknis', 'tenaga_teknis']) && empty($competency_name)) {
            $error = stela_t('competency-required-tech-supervisor-and-tech-personnel');
        } elseif (empty($employee_code) || empty($full_name) || empty($position) || empty($department) || empty($contractor_company) || empty($competency_type)) {
            $error = stela_t('all-fields-required');
        } else {
            // Handle CV upload
            $cv_file = '';
            if (isset($_FILES['cv_file']) && $_FILES['cv_file']['error'] == 0) {
                $upload_dir = '../../assets/uploads/cv/';
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                $file_ext = pathinfo($_FILES['cv_file']['name'], PATHINFO_EXTENSION);
                $cv_file = $employee_code . '_cv_' . time() . '.' . $file_ext;
                
                if (move_uploaded_file($_FILES['cv_file']['tmp_name'], $upload_dir . $cv_file)) {
                    $cv_file = 'uploads/cv/' . $cv_file;
                } else {
                    $cv_file = '';
                }
            }
            
            $sql = "INSERT INTO employees (employee_code, full_name, position, department, contractor_company, competency_type, competency_name, cv_file, verification_status) 
                    VALUES ('$employee_code', '$full_name', '$position', '$department', '$contractor_company', '$competency_type', '$competency_name', '$cv_file', 'pending')";
            
            if ($db->query($sql)) {
                $employee_id = $db->lastInsertId();
                
                // Handle multiple certification uploads
                if (isset($_FILES['certifications']) && !empty($_FILES['certifications']['name'][0])) {
                    $upload_dir = '../../assets/uploads/certifications/';
                    if (!file_exists($upload_dir)) {
                        mkdir($upload_dir, 0777, true);
                    }
                    
                    $cert_ids = $_POST['certification_ids'];
                    $cert_numbers = $_POST['cert_numbers'];
                    $issue_dates = $_POST['issue_dates'];
                    $expiry_dates = $_POST['expiry_dates'];
                    
                    foreach ($_FILES['certifications']['tmp_name'] as $key => $tmp_name) {
                        if ($_FILES['certifications']['error'][$key] == 0) {
                            $file_ext = pathinfo($_FILES['certifications']['name'][$key], PATHINFO_EXTENSION);
                            $cert_file = $employee_code . '_cert_' . $key . '_' . time() . '.' . $file_ext;
                            
                            if (move_uploaded_file($tmp_name, $upload_dir . $cert_file)) {
                                $cert_path = 'uploads/certifications/' . $cert_file;
                                $cert_id = intval($cert_ids[$key]);
                                $cert_number = $db->escapeString($cert_numbers[$key]);
                                $issue_date = $db->escapeString($issue_dates[$key]);
                                $expiry_date = $db->escapeString($expiry_dates[$key]);
                                
                                // Check if expired
                                $today = date('Y-m-d');
                                $status = ($expiry_date && $expiry_date < $today) ? 'expired' : 'pending';
                                
                                $sql_cert = "INSERT INTO employee_certifications 
                                            (employee_id, certification_id, cert_number, issue_date, expiry_date, 
                                             document_file, status, verification_status) 
                                            VALUES ($employee_id, $cert_id, '$cert_number', '$issue_date', '$expiry_date', 
                                                    '$cert_path', '$status', 'pending')";
                                $db->query($sql_cert);
                            }
                        }
                    }
                }
                
                // Save position for later appointment generation
                $_SESSION['temp_position_' . $employee_id] = $position_id;
                
                $message = stela_t('employee-data-added-waiting-verification');
            } else {
                $error = stela_t('failed-add-employee-data');
            }
        }
    }
}

// Get all employees with certifications and appointment/KTT status
$employees = $db->query("
    SELECT e.*,
           COUNT(DISTINCT ec.id) as cert_count,
           SUM(CASE WHEN ec.verification_status = 'verified' THEN 1 ELSE 0 END) as verified_cert_count,
           u.full_name as verified_by_name,
           a.status as appointment_status,
           ka.rejection_notes as ktt_rejection_notes,
           CASE 
               WHEN a.status = 'rejected' OR (ka.id IS NOT NULL AND (ka.ktt_msm_status = 'rejected' OR ka.ktt_ttn_status = 'rejected'))
               THEN 1 ELSE 0 
           END as has_ktt_rejection,
           CASE
               WHEN e.verification_status = 'rejected' THEN 'rejected'
               WHEN a.status = 'rejected' THEN 'rejected'
               WHEN ka.id IS NOT NULL AND (ka.ktt_msm_status = 'rejected' OR ka.ktt_ttn_status = 'rejected') THEN 'rejected'
               ELSE e.verification_status
           END as combined_status
    FROM employees e
    LEFT JOIN employee_certifications ec ON e.id = ec.employee_id
    LEFT JOIN users u ON e.verified_by = u.id
    LEFT JOIN appointments a ON e.id = a.employee_id
    LEFT JOIN ktt_approvals ka ON a.id = ka.appointment_id
    WHERE e.is_active = 1
    GROUP BY e.id
    ORDER BY e.created_at DESC
");

// Get certifications for dropdown
$certifications = $db->query("SELECT * FROM certifications ORDER BY cert_name");
$positions = $db->query("SELECT * FROM positions ORDER BY position_type, position_name");

require_once '../../includes/header.php';

// Get statistics
$total_employees = $employees->num_rows;
$pending_verification = $db->query("SELECT COUNT(*) as count FROM employees WHERE verification_status = 'pending' AND is_active = 1")->fetch_assoc()['count'];
$verified_count = $db->query("SELECT COUNT(*) as count FROM employees WHERE verification_status = 'verified' AND is_active = 1")->fetch_assoc()['count'];
$rejected_count = $db->query("SELECT COUNT(*) as count FROM employees WHERE verification_status = 'rejected' AND is_active = 1")->fetch_assoc()['count'];

// Get unique companies for filter
$companies = $db->query("
    SELECT DISTINCT contractor_company
    FROM employees
    WHERE is_active = 1
    ORDER BY contractor_company
");

// Count employees that need resubmission (rejected by admin or KTT)
$rejected_resubmit_count = $db->query("
    SELECT COUNT(DISTINCT e.id) as count
    FROM employees e
    LEFT JOIN appointments a ON e.id = a.employee_id
    LEFT JOIN ktt_approvals ka ON a.id = ka.appointment_id
    WHERE e.is_active = 1
    AND (
        e.verification_status = 'rejected'
        OR a.status = 'rejected'
        OR (ka.id IS NOT NULL AND (ka.ktt_msm_status = 'rejected' OR ka.ktt_ttn_status = 'rejected'))
    )
")->fetch_assoc()['count'];
?>

<div class="employees-admin-container">
    <!-- Page Header -->
    <div class="page-header-emp-admin">
        <div class="header-left">
            <h2><i class="fas fa-building"></i> Contractor Workforce Data</h2>
            <p>Manage and verify contractor workforce data</p>
        </div>
        <button class="btn btn-primary btn-lg-emp" onclick="openModal('addModal')">
            <i class="fas fa-plus-circle"></i> Add Employee
        </button>
    </div>
    
    <?php if ($message): ?>
    <div class="alert alert-success alert-custom-emp">
        <i class="fas fa-check-circle"></i>
        <div>
            <strong>Success!</strong>
            <p><?php echo htmlspecialchars($message); ?></p>
        </div>
    </div>
    <?php endif; ?>
    
    <?php if ($error): ?>
    <div class="alert alert-error alert-custom-emp">
        <i class="fas fa-exclamation-circle"></i>
        <div>
            <strong>Error!</strong>
            <p><?php echo htmlspecialchars($error); ?></p>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($rejected_resubmit_count > 0): ?>
    <div class="alert alert-warning alert-custom-emp alert-resubmit-emp">
        <i class="fas fa-exclamation-triangle"></i>
        <div>
            <strong>Perhatian!</strong>
            <p><span data-lang="rejected-employees-message-1">Terdapat</span> <strong><?php echo $rejected_resubmit_count; ?></strong> <span data-lang="rejected-employees-message-2">data karyawan yang ditolak dan perlu diperbaiki. Klik tombol</span> <i class="fas fa-upload"></i> <span data-lang="rejected-employees-message-3">pada data yang ditolak untuk melakukan perbaikan.</span></p>
        </div>
    </div>
    <?php endif; ?>

    <!-- Statistics Cards -->
    <div class="stats-grid-emp">
        <div class="stat-box-emp stat-total">
            <div class="stat-icon-emp"><i class="fas fa-users"></i></div>
            <div class="stat-info">
                <div class="stat-number"><?php echo $total_employees; ?></div>
                <div class="stat-text">Total Employees</div>
            </div>
        </div>
        
        <div class="stat-box-emp stat-pending">
            <div class="stat-icon-emp"><i class="fas fa-hourglass-half"></i></div>
            <div class="stat-info">
                <div class="stat-number"><?php echo $pending_verification; ?></div>
                <div class="stat-text">Waiting for Verification</div>
            </div>
        </div>
        
        <div class="stat-box-emp stat-verified">
            <div class="stat-icon-emp"><i class="fas fa-check-circle"></i></div>
            <div class="stat-info">
                <div class="stat-number"><?php echo $verified_count; ?></div>
                <div class="stat-text">Terverifikasi</div>
            </div>
        </div>
        
        <div class="stat-box-emp stat-rejected">
            <div class="stat-icon-emp"><i class="fas fa-times-circle"></i></div>
            <div class="stat-info">
                <div class="stat-number"><?php echo $rejected_count; ?></div>
                <div class="stat-text">Ditolak</div>
            </div>
        </div>
    </div>
    
    <!-- Employees Table -->
    <div class="card-emp">
        <div class="card-header-emp">
            <h3><i class="fas fa-list"></i> Complete Workforce List</h3>
        </div>
        
        <!-- Filter by Company -->
        <div class="filter-section-emp">
            <div class="filter-group-emp">
                <label><i class="fas fa-building"></i> Filter Perusahaan:</label>
                <select id="companyFilterEmp" class="filter-select-emp" onchange="filterTableByCompany('employeesTable', this.value)">
                    <option value="">-- Semua Perusahaan --</option>
                    <?php 
                    while ($comp = $companies->fetch_assoc()): 
                    ?>
                    <option value="<?php echo htmlspecialchars($comp['contractor_company']); ?>">
                        <?php echo htmlspecialchars($comp['contractor_company']); ?>
                    </option>
                    <?php endwhile; ?>
                </select>
            </div>
        </div>
        
        <div class="card-body-emp">
            <?php if ($employees->num_rows > 0): ?>
                <div class="table-responsive">
                    <table class="table-emp" id="employeesTable">
                        <thead>
                            <tr>
                                <th>ID BADGE</th>
                                <th>Name</th>
                                <th>Position</th>
                                <th>Competency Type</th>
                                <th>Competency</th>
                                <th>Company</th>
                                <th>Certification</th>
                                <th>Status</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $type_labels = [
                                'pengawas_operasional' => 'Pengawas Operasional',
                                'pengawas_teknis' => 'Pengawas Teknis',
                                'tenaga_teknis' => 'Tenaga Teknis'
                            ];
                            
                            $employees->data_seek(0);
                            while ($row = $employees->fetch_assoc()): 
                                $type_key = $row['competency_type'] ?? '';
                                $type_label = $type_labels[$type_key] ?? $type_key;
                            ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($row['employee_code']); ?></strong></td>
                                <td><?php echo htmlspecialchars($row['full_name']); ?></td>
                                <td><?php echo htmlspecialchars($row['position']); ?></td>
                                <td><?php echo htmlspecialchars($type_label); ?></td>
                                <td><?php echo htmlspecialchars($row['competency_name'] ?? '-'); ?></td>
                                <td><?php echo htmlspecialchars($row['contractor_company']); ?></td>
                                <td>
                                    <span class="badge badge-info">
                                        <?php echo ($row['verified_cert_count'] ?? 0) . '/' . ($row['cert_count'] ?? 0); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php
                                    $final_status = $row['combined_status'] ?? $row['verification_status'];
                                    $status_badges = [
                                        'verified' => '<span class="badge badge-success">TERVERIFIKASI</span>',
                                        'pending' => '<span class="badge badge-warning">MENUNGGU</span>',
                                        'rejected' => '<span class="badge badge-danger">DITOLAK</span>'
                                    ];
                                    echo $status_badges[$final_status] ?? '';
                                    if (!empty($row['has_ktt_rejection']) && $row['has_ktt_rejection']) {
                                        echo '<br><small class="text-muted">Ditolak oleh KTT</small>';
                                    }
                                    ?>
                                </td>
                                <td>
                                    <a href="verify_employee.php?id=<?php echo $row['id']; ?>" class="btn btn-sm btn-info">
                                        <i class="fas fa-eye"></i> Lihat
                                    </a>
                                    <?php if (($row['combined_status'] ?? $row['verification_status']) == 'rejected'): ?>
                                    <a href="resubmit_employee.php?id=<?php echo $row['id']; ?>" class="btn-action-emp resubmit-btn" title="Perbaiki & Submit Ulang" data-lang-title="upload-correction">
                                        <i class="fas fa-upload"></i>
                                    </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty-state-emp">
                    <i class="fas fa-inbox"></i>
                    <p>No employee data yet</p>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="table-info-emp" id="tableInfoEmp">
            Showing all companies
        </div>
    </div>
</div>

<!-- Add Modal -->
<div id="addModal" class="modal">
    <div class="modal-content modal-large-emp">
        <div class="modal-header modal-header-emp">
            <h3><i class="fas fa-plus-circle"></i> Add Contractor Employee Data</h3>
            <span class="close" onclick="closeModal('addModal')">&times;</span>
        </div>
        <form method="POST" action="" enctype="multipart/form-data">
            <input type="hidden" name="action" value="add">
            <div class="modal-body">
                <h4 class="section-title-modal" data-lang="identity-data">Identity Data</h4>
                <div class="form-row-modal">
                    <div class="form-group-modal">
                        <label>ID BADGE <span class="text-danger">*</span></label>
                        <input type="text" name="employee_code" class="form-control-modal" required placeholder="Example: BADGE001" data-lang-placeholder="badge-example-placeholder">
                    </div>
                    <div class="form-group-modal">
                        <label>Full Name <span class="text-danger">*</span></label>
                        <input type="text" name="full_name" class="form-control-modal" required>
                    </div>
                </div>
                
                <div class="form-row-modal">
                    <div class="form-group-modal">
                        <label>Scope of Work <span class="text-danger">*</span></label>
                        <select name="scope" class="form-control-modal" required>
                            <option value="">-- Select Scope of Work --</option>
                            <option value="PT Meares Soputan Mining (MSM)">PT Meares Soputan Mining (MSM)</option>
                            <option value="PT Tambang Tondano Nusajaya (TTN)">PT Tambang Tondano Nusajaya (TTN)</option>
                        </select>
                    </div>
                    <div class="form-group-modal">
                        <label>Position <span class="text-danger">*</span></label>
                        <input type="text" name="position" class="form-control-modal" required placeholder="Example: Rigger, HSE Superintendent" data-lang-placeholder="position-example-placeholder">
                    </div>
                </div>
                
                <!-- Department disembunyikan, nilai default diatur otomatis -->
                <input type="hidden" name="department" value="General">
                
                <div class="form-group-modal">
                    <label>Company <span class="text-danger">*</span></label>
                    <select name="contractor_company" class="form-control-modal" required>
                        <option value="">-- Select Company --</option>
                        <option value="PT MSM ">PT Meares Soputan Mining (MSM)</option>
                        <option value="PT TTN ">PT Tambang Tondano Nusajaya (TTN)</option>
                        <option value="G4S Security Services">G4S Security Services</option>
                        <option value="PT Part Sentra Indomandiri">PT Part Sentra Indomandiri</option>
                        <option value="PT Aneka Kimia Raya Corporindo">PT Aneka Kimia Raya Corporindo</option>
                        <option value="PT Saribuana Manado">PT Saribuana Manado</option>
                        <option value="PT Maxidrill Indonesia">PT Maxidrill Indonesia</option>
                        <option value="PT Tata Wisata">PT Tata Wisata</option>
                        <option value="PT Arlie Labora Utama">PT Arlie Labora Utama</option>
                        <option value="PT Tou Maesa Sejahtera">PT Tou Maesa Sejahtera</option>
                        <option value="PT DNX Indonesia">PT DNX Indonesia</option>
                        <option value="PT Mandara Fasilitas Indonesia">PT Mandara Fasilitas Indonesia</option>
                        <option value="PT Aptekindo Mitra Solusitama">PT Aptekindo Mitra Solusitama</option>
                        <option value="PT Geopersada Mulai Abadi">PT Geopersada Mulai Abadi</option>
                        <option value="PT Hidup Baru Sukses Mandiri">PT Hidup Baru Sukses Mandiri</option>
                        <option value="PT Intertek Utama Services">PT Intertek Utama Services</option>
                        <option value="PT Macmahon Indonesia">PT Macmahon Indonesia</option>
                        <option value="PT Manado Karya Angrah">PT Manado Karya Angrah</option>
                        <option value="PT Samudera Mulai Abadi">PT Samudera Mulai Abadi</option>
                    </select>
                </div>
                
                <div class="form-row-modal">
                    <div class="form-group-modal">
                        <label>Competency Type <span class="text-danger">*</span></label>
                        <select name="competency_type" class="form-control-modal" id="addCompetencyType" onchange="toggleCompetencyField()" required>
                            <option value="">-- Select Competency Type --</option>
                            <option value="pengawas_operasional">Pengawas Operasional</option>
                            <option value="pengawas_teknis">Pengawas Teknis</option>
                            <option value="tenaga_teknis">Tenaga Teknis</option>
                        </select>
                    </div>
                    <div class="form-group-modal" id="competencyGroup" style="display: none;">
                        <label>Kompetensi <span class="text-danger">*</span></label>
                        <div class="competency-input-wrapper">
                            <input type="text" name="competency_name" class="form-control-modal" id="addCompetencyName" 
                                   placeholder="Type or select competency" data-lang-placeholder="type-or-select-competency" autocomplete="off" 
                                   onfocus="showCompetencySuggestions()" oninput="filterCompetencies()">
                            <div id="addCompetencySuggestions" class="competency-suggestions" style="display: none;"></div>
                        </div>
                    </div>
                </div>

                <div class="form-group-modal">
                    <label>Upload CV (PDF/DOC, Max 5MB) <span class="text-danger">*</span></label>
                    <div class="file-upload-modal">
                        <i class="fas fa-file-upload"></i>
                        <input type="file" name="cv_file" class="file-input-modal" accept=".pdf,.doc,.docx" required>
                        <span class="file-text">Click or drag your CV file</span>
                        <span class="file-name"></span>
                    </div>
                </div>
                
                <hr style="margin: 25px 0;">
                <h4 class="section-title-modal" data-lang="certifications-competencies">Sertifikasi/Kompetensi</h4>
                <div id="certificationContainer">
                    <div class="certification-item">
                        <div class="form-row">
                            <div class="form-group">
                                <label>Certification Type <span class="text-danger">*</span></label>
                                <select name="certification_ids[]" class="form-control" required>
                                    <option value="">-- Select Certification --</option>
                                    <?php
                                    $certifications->data_seek(0);
                                    while ($cert = $certifications->fetch_assoc()):
                                    ?>
                                    <option value="<?php echo $cert['id']; ?>">
                                        <?php echo htmlspecialchars($cert['cert_name']); ?>
                                    </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Certificate Type <span class="text-danger">*</span></label>
                                <select name="cert_types[]" class="form-control cert-type-select" required onchange="toggleOtherType(this)">
                                    <option value="">-- Select Type --</option>
                                    <option value="Attendance/Peserta">Attendance/Peserta</option>
                                    <option value="Kompeten">Kompeten</option>
                                    <option value="Training">Training</option>
                                    <option value="Lainnya">Lainnya</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-row other-type-field" style="display: none;">
                            <div class="form-group">
                                <label>Other Certificate Type <span class="text-danger">*</span></label>
                                <input type="text" name="cert_types_other[]" class="form-control" placeholder="Contoh: Sertifikat Keahlian" data-lang-placeholder="certificate-skill-example">
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label>Certificate No. <span class="text-danger">*</span></label>
                                <input type="text" name="cert_numbers[]" class="form-control" required>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label>Issue Date <span class="text-danger">*</span></label>
                                <input type="date" name="issue_dates[]" class="form-control issue-date" required onchange="calculateExpiryDate(this)">
                            </div>
                            <div class="form-group">
                                <label>Validity Period (Years) <span class="text-danger">*</span></label>
                                <div style="display: flex; gap: 10px;">
                                    <input type="number" name="validity_years[]" class="form-control validity-years" min="0" step="0.5" placeholder="Example: 1, 2, 3" data-lang-placeholder="validity-years-example" onchange="calculateExpiryDate(this)">
                                    <label style="display: flex; align-items: center; white-space: nowrap;">
                                        <input type="checkbox" name="no_expiry[]" class="no-expiry-check" onchange="toggleExpiryField(this)"> No Expiry
                                    </label>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label>Expiry Date <span class="text-danger">*</span></label>
                                <input type="date" name="expiry_dates[]" class="form-control expiry-date" readonly style="background-color: #f0f0f0;">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label>Upload Certificate File (PDF, Max 5MB) <span class="text-danger">*</span></label>
                            <input type="file" name="certifications[]" class="form-control" accept=".pdf" required>
                        </div>
                        <hr>
                    </div>
                </div>
                
                <button type="button" class="btn btn-outline-primary-modal" onclick="addCertification()">
                    <i class="fas fa-plus-circle"></i> Add Another Certification
                </button>
            </div>
            <div class="modal-footer-modal">
                <button type="button" class="btn btn-secondary" onclick="closeModal('addModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save & Submit for Verification</button>
            </div>
        </form>
    </div>
</div>

<script>
const competenciesData = <?php echo json_encode($competencies_by_type); ?>;
const competenciesTableExists = <?php echo json_encode($competencies_table_exists); ?>;
const REQUIRES_COMPETENCY = ['pengawas_teknis', 'tenaga_teknis'];

function toggleCompetencyField() {
    const typeSelect = document.getElementById('addCompetencyType');
    const competencyGroup = document.getElementById('competencyGroup');
    const competencyInput = document.getElementById('addCompetencyName');
    
    const selectedType = typeSelect.value;
    
    if (REQUIRES_COMPETENCY.includes(selectedType)) {
        competencyGroup.style.display = 'block';
        competencyInput.required = true;
    } else {
        competencyGroup.style.display = 'none';
        competencyInput.required = false;
        competencyInput.value = '';
        document.getElementById('addCompetencySuggestions').style.display = 'none';
    }
}

function showCompetencySuggestions() {
    const competencyType = document.getElementById('addCompetencyType').value;
    const suggestionsDiv = document.getElementById('addCompetencySuggestions');
    
    if (!competencyType || !competenciesTableExists || !competenciesData[competencyType]) {
        suggestionsDiv.innerHTML = '<div class="suggestion-item">' + (window.getLanguageText('')) + '</div>';
        suggestionsDiv.style.display = 'block';
        return;
    }
    
    const competencies = competenciesData[competencyType];
    renderSuggestions(competencies, suggestionsDiv);
}

function filterCompetencies() {
    const inputValue = document.getElementById('addCompetencyName').value.toLowerCase().trim();
    const competencyType = document.getElementById('addCompetencyType').value;
    const suggestionsDiv = document.getElementById('addCompetencySuggestions');
    
    if (!competencyType || !competenciesTableExists || !competenciesData[competencyType]) {
        suggestionsDiv.style.display = 'none';
        return;
    }
    
    let competencies = competenciesData[competencyType];
    
    if (inputValue) {
        competencies = competencies.filter(comp => 
            comp.competency_name.toLowerCase().includes(inputValue)
        );
    }
    
    renderSuggestions(competencies, suggestionsDiv);
}

function renderSuggestions(competencies, suggestionsDiv) {
    let html = '';
    
    if (competencies.length === 0) {
        html = '<div class="suggestion-item">No matching competency found</div>';
    } else {
        competencies.forEach(comp => {
            html += `<div class="suggestion-item" onclick="selectSuggestion('${comp.competency_name.replace(/'/g, "\\'")}')">
                        <i class="fas fa-check-circle"></i> ${comp.competency_name}
                    </div>`;
        });
    }
    
    suggestionsDiv.innerHTML = html;
    suggestionsDiv.style.display = 'block';
}

function selectSuggestion(competencyName) {
    document.getElementById('addCompetencyName').value = competencyName;
    document.getElementById('addCompetencySuggestions').style.display = 'none';
}

// Close suggestions when clicking outside
document.addEventListener('click', function(event) {
    const suggestionsDiv = document.getElementById('addCompetencySuggestions');
    
    if (!event.target.closest('.competency-input-wrapper')) {
        suggestionsDiv.style.display = 'none';
    }
});

function addCertification() {
    const container = document.getElementById('certificationContainer');
    const certItem = container.querySelector('.certification-item').cloneNode(true);
    
    // Clear values
    certItem.querySelectorAll('input, select').forEach(input => {
        input.value = '';
        if (input.type === 'checkbox') {
            input.checked = false;
        }
    });
    
    // Reset the other type field visibility
    certItem.querySelector('.other-type-field').style.display = 'none';
    
    // Reset expiry field state
    certItem.querySelector('.expiry-date').removeAttribute('readonly');
    certItem.querySelector('.expiry-date').style.backgroundColor = '#ffffff';
    
    container.appendChild(certItem);
}

function toggleOtherType(selectElement) {
    const certItem = selectElement.closest('.certification-item');
    const otherTypeField = certItem.querySelector('.other-type-field');
    const otherTypeInput = certItem.querySelector('input[name="cert_types_other[]"]');
    
    if (selectElement.value === 'Lainnya') {
        otherTypeField.style.display = 'flex';
        otherTypeInput.required = true;
    } else {
        otherTypeField.style.display = 'none';
        otherTypeInput.required = false;
        otherTypeInput.value = '';
    }
}

function calculateExpiryDate(element) {
    const certItem = element.closest('.certification-item');
    const issueDate = certItem.querySelector('.issue-date').value;
    const validityYears = parseFloat(certItem.querySelector('.validity-years').value) || 0;
    const noExpiry = certItem.querySelector('.no-expiry-check').checked;
    const expiryDateField = certItem.querySelector('.expiry-date');
    
    if (noExpiry) {
        expiryDateField.value = '';
        return;
    }
    
    if (issueDate && validityYears > 0) {
        const date = new Date(issueDate);
        date.setFullYear(date.getFullYear() + Math.floor(validityYears));
        
        // Handle decimal years (months)
        const months = (validityYears % 1) * 12;
        date.setMonth(date.getMonth() + Math.round(months));
        
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        
        expiryDateField.value = `${year}-${month}-${day}`;
    } else {
        expiryDateField.value = '';
    }
}

function toggleExpiryField(checkboxElement) {
    const certItem = checkboxElement.closest('.certification-item');
    const expiryDateField = certItem.querySelector('.expiry-date');
    const validityYearsField = certItem.querySelector('.validity-years');
    
    if (checkboxElement.checked) {
        expiryDateField.value = '';
        expiryDateField.setAttribute('readonly', true);
        expiryDateField.style.backgroundColor = '#f0f0f0';
        validityYearsField.setAttribute('readonly', true);
        validityYearsField.style.backgroundColor = '#f0f0f0';
        validityYearsField.value = '';
    } else {
        expiryDateField.removeAttribute('readonly');
        expiryDateField.style.backgroundColor = '#ffffff';
        validityYearsField.removeAttribute('readonly');
        validityYearsField.style.backgroundColor = '#ffffff';
    }
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.cert-type-select').forEach(select => {
        select.addEventListener('change', function() {
            toggleOtherType(this);
        });
    });
});

// File upload preview
document.querySelectorAll('.file-upload-modal').forEach(area => {
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
        const input = area.querySelector('.file-input-modal');
        input.files = e.dataTransfer.files;
        updateFileName(area, input.files[0]);
    });
    
    const input = area.querySelector('.file-input-modal');
    input.addEventListener('change', () => {
        updateFileName(area, input.files[0]);
    });
});

function updateFileName(area, file) {
    if (file) {
        area.querySelector('.file-name').textContent = file.name;
        area.querySelector('.file-name').style.display = 'block';
    }
}

function filterTableByCompany(tableId, companyName) {
    const table = document.getElementById(tableId);
    const rows = table.getElementsByTagName('tbody')[0].getElementsByTagName('tr');
    let visibleCount = 0;
    
    if (!companyName) {
        // Show all rows
        for (let row of rows) {
            row.style.display = '';
            visibleCount++;
        }
        updateTableInfo('Menampilkan semua perusahaan');
    } else {
        // Filter by company
        for (let row of rows) {
            const rowCompany = row.getAttribute('data-company');
            if (rowCompany === companyName) {
                row.style.display = '';
                visibleCount++;
            } else {
                row.style.display = 'none';
            }
        }
        updateTableInfo('Showing ' + visibleCount + ' employees from company: ' + companyName);
    }
}

function updateTableInfo(message) {
    const infoElement = document.getElementById('tableInfoEmp');
    if (infoElement) {
        infoElement.textContent = message;
    }
}
</script>

<style>
.employees-admin-container {
    padding: 20px 0;
}

/* Page Header */
.page-header-emp-admin {
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
    margin: 0 0 8px 0;
    font-size: 26px;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 10px;
}

.header-left p {
    margin: 0;
    opacity: 0.9;
    font-size: 14px;
}

.btn-lg-emp {
    padding: 12px 25px;
    font-size: 15px;
    white-space: nowrap;
    background: #37474F;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
}

.btn-lg-emp:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 15px rgba(0, 0, 0, 0.3);
}

/* Alert Custom */
.alert-custom-emp {
    display: flex;
    gap: 15px;
    align-items: flex-start;
    padding: 20px;
    margin-bottom: 30px;
    border-radius: 8px;
    border-left: 4px solid;
}

.alert-success.alert-custom-emp {
    background: #E8F5E9;
    border-left-color: #2E7D32;
}

.alert-success.alert-custom-emp i {
    color: #2E7D32;
    font-size: 20px;
}

.alert-error.alert-custom-emp {
    background: #fee2e2;
    border-left-color: #ef4444;
}

.alert-error.alert-custom-emp i {
    color: #ef4444;
    font-size: 20px;
}

.alert-custom-emp strong {
    display: block;
    margin-bottom: 5px;
}

/* Stats Grid */
.stats-grid-emp {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 15px;
    margin-bottom: 30px;
}

.stat-box-emp {
    background: white;
    border-radius: 8px;
    padding: 20px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
    display: flex;
    align-items: center;
    gap: 15px;
    border-left: 4px solid #ccc;
    transition: all 0.3s ease;
}

.stat-box-emp:hover {
    transform: translateY(-3px);
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.12);
}

.stat-total { border-left-color: #37474F; }
.stat-pending { border-left-color: #f59e0b; }
.stat-verified { border-left-color: #2E7D32; }
.stat-rejected { border-left-color: #ef4444; }

.stat-icon-emp {
    font-size: 28px;
    width: 50px;
    height: 50px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 8px;
    color: white;
}

.stat-total .stat-icon-emp { background: #37474F; }
.stat-pending .stat-icon-emp { background: #f59e0b; }
.stat-verified .stat-icon-emp { background: #2E7D32; }
.stat-rejected .stat-icon-emp { background: #ef4444; }

.stat-number {
    font-size: 24px;
    font-weight: 700;
    color: #333;
}

.stat-text {
    color: #666;
    font-size: 12px;
}

/* Card */
.card-emp {
    background: white;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
    margin-bottom: 25px;
}

.card-header-emp {
    padding: 20px;
    border-bottom: 2px solid #f0f0f0;
}

.card-header-emp h3 {
    margin: 0;
    font-size: 18px;
    color: #333;
    display: flex;
    align-items: center;
    gap: 10px;
}

.card-header-emp i {
    color: #37474F;
}

.card-body-emp {
    padding: 0;
}

/* Table */
.table-emp {
    width: 100%;
    border-collapse: collapse;
    margin: 0;
}

.table-emp thead th {
    background: #f8f9fa;
    color: #333;
    font-weight: 600;
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    border: none;
    padding: 15px;
}

.emp-row {
    border-bottom: 1px solid #f0f0f0;
    transition: background-color 0.2s ease;
}

.emp-row:hover {
    background-color: #f8f9ff;
}

.table-emp td {
    padding: 15px;
    vertical-align: middle;
    font-size: 13px;
}

.col-code { width: 11%; }
.col-name { width: 16%; }
.col-position { width: 12%; }
.col-company { width: 14%; }
.col-certs { width: 12%; }
.col-status { width: 11%; }
.col-verified-by { width: 14%; }
.col-action { width: 10%; }

.code-badge {
    background: #ECEFF1;
    color: #37474F;
    padding: 4px 10px;
    border-radius: 6px;
    font-size: 11px;
    font-weight: 600;
    display: inline-block;
}

.position-tag-emp {
    background: #f3f4f6;
    color: #666;
    padding: 4px 10px;
    border-radius: 6px;
    font-size: 12px;
    display: inline-block;
}

.company-tag-emp {
    background: #fef3c7;
    color: #b45309;
    padding: 4px 10px;
    border-radius: 6px;
    font-size: 11px;
    display: inline-block;
}

.cert-count-emp {
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.cert-badge {
    font-weight: 600;
    color: #333;
    font-size: 12px;
}

.cert-progress {
    width: 80px;
    height: 6px;
    background: #e5e7eb;
    border-radius: 3px;
    overflow: hidden;
}

.cert-fill {
    height: 100%;
    background: linear-gradient(90deg, #37474F, #616161);
}

.badge-status {
    display: inline-block;
    padding: 6px 12px;
    border-radius: 6px;
    font-size: 11px;
    font-weight: 600;
}

.badge-success {
    background: #E8F5E9;
    color: #2E7D32;
}

.badge-warning {
    background: #fef3c7;
    color: #f59e0b;
}

.badge-danger {
    background: #fee2e2;
    color: #ef4444;
}

.action-buttons-emp {
    display: flex;
    gap: 6px;
}

.btn-action-emp {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 36px;
    height: 36px;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    transition: all 0.3s ease;
    font-size: 14px;
    text-decoration: none;
    color: white;
}

.verify-btn {
    background: #2E7D32;
}

.verify-btn:hover {
    background: #1B5E20;
    transform: translateY(-1px);
}

.cv-btn {
    background: #37474F;
}

.cv-btn:hover {
    background: #1d4ed8;
    transform: translateY(-1px);
}

.resubmit-btn {
    background: #f59e0b;
}

.resubmit-btn:hover {
    background: #d97706;
    transform: translateY(-1px);
}

/* Rejected Alert Banner */
.alert-resubmit-emp {
    background: #fef3c7 !important;
    border-left-color: #f59e0b !important;
}

.alert-resubmit-emp i {
    color: #f59e0b !important;
    font-size: 24px !important;
}

/* Empty State */
.empty-state-emp {
    text-align: center;
    padding: 60px 20px;
    color: #999;
}

.empty-state-emp i {
    font-size: 48px;
    margin-bottom: 15px;
    opacity: 0.5;
}

.empty-state-emp p {
    margin: 0;
    font-size: 16px;
}

/* Modal */
.modal-large-emp {
    max-width: 900px;
}

.modal-header-emp {
    background: linear-gradient(135deg, #37474F 0%, #616161 100%);
    color: white;
    padding: 20px;
    border-radius: 8px 8px 0 0;
}

.modal-header-emp h3 {
    margin: 0;
    font-size: 18px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.modal-header-emp .close {
    color: white;
    opacity: 0.8;
}

.modal-header-emp .close:hover {
    opacity: 1;
}

.modal-body {
    padding: 25px;
    max-height: 70vh;
    overflow-y: auto;
}

.section-title-modal {
    color: #37474F;
    margin: 0 0 15px 0;
    padding-bottom: 10px;
    border-bottom: 2px solid #e9ecef;
    font-size: 15px;
    font-weight: 600;
}

.form-row-modal {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 15px;
    margin-bottom: 15px;
}

.form-group-modal {
    margin-bottom: 0;
}

.form-group-modal label {
    display: block;
    margin-bottom: 6px;
    font-weight: 600;
    color: #333;
    font-size: 13px;
}

.form-control-modal {
    width: 100%;
    padding: 10px 12px;
    border: 2px solid #e9ecef;
    border-radius: 6px;
    font-size: 13px;
    transition: border-color 0.3s ease;
    font-family: inherit;
}

.form-control-modal:focus {
    outline: none;
    border-color: #37474F;
}

/* File Upload */
.file-upload-modal {
    border: 2px dashed #ddd;
    border-radius: 8px;
    padding: 25px;
    text-align: center;
    cursor: pointer;
    transition: all 0.3s ease;
    position: relative;
}

.file-upload-modal:hover {
    border-color: #37474F;
    background: #f8f9ff;
}

.file-upload-modal.dragover {
    border-color: #37474F;
    background: #f0f4ff;
}

.file-upload-modal i {
    font-size: 32px;
    color: #37474F;
    margin-bottom: 10px;
    display: block;
}

.file-input-modal {
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

.modal-footer-modal {
    display: flex;
    gap: 10px;
    justify-content: flex-end;
    padding: 15px 25px;
    border-top: 1px solid #e9ecef;
}

.btn-outline-primary-modal {
    border: 2px solid #37474F;
    color: #37474F;
    background: white;
    padding: 10px 16px;
    font-weight: 600;
    margin-bottom: 15px;
}

.btn-outline-primary-modal:hover {
    background: #f0f4ff;
}

/* Filter Section */
.filter-section-emp {
    padding: 20px;
    background: #f8f9fa;
    border-bottom: 1px solid #e9ecef;
}

.filter-group-emp {
    display: flex;
    align-items: center;
    gap: 12px;
    max-width: 400px;
}

.filter-group-emp label {
    font-weight: 600;
    color: #333;
    white-space: nowrap;
    display: flex;
    align-items: center;
    gap: 6px;
    margin: 0;
    font-size: 13px;
}

.filter-select-emp {
    padding: 10px 12px;
    border: 2px solid #e9ecef;
    border-radius: 6px;
    font-size: 13px;
    cursor: pointer;
    transition: border-color 0.3s ease;
    min-width: 200px;
    flex: 1;
}

.filter-select-emp:hover,
.filter-select-emp:focus {
    border-color: #37474F;
    outline: none;
}

.table-info-emp {
    padding: 12px 20px;
    background: #ECEFF1;
    color: #37474F;
    border-top: 1px solid #c7d2fe;
    font-size: 12px;
    font-weight: 500;
}

/* Responsive */
@media (max-width: 1024px) {
    .page-header-emp-admin {
        flex-direction: column;
        gap: 20px;
        text-align: center;
    }
    
    .col-company { display: none; }
    .col-verified-by { display: none; }
}

@media (max-width: 768px) {
    .page-header-emp-admin {
        padding: 25px 15px;
    }
    
    .header-left h2 {
        font-size: 20px;
    }
    
    .stats-grid-emp {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .col-position { display: none; }
    .col-certs { display: none; }
    .col-status { display: none; }
    
    .form-row-modal {
        grid-template-columns: 1fr;
    }
    
    .modal-large-emp {
        max-width: 90%;
    }
    
    .filter-group-emp {
        width: 100%;
        max-width: none;
    }
    
    .filter-select-emp {
        min-width: auto;
    }
}
</style>

<?php require_once '../../includes/footer.php'; ?>





