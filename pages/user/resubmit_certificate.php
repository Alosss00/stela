<?php
$page_title = 'Resubmit Certificate';

require_once '../../includes/auth.php';
require_once '../../includes/db.php';

checkPageAccess(['user', 'department_user']);

$db = new Database();

/*CSRF TOKEN*/

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

/*VALIDATE PARAMETER*/

$employee_certification_id = isset($_GET['id'])? (int)$_GET['id']: 0;
if ($employee_certification_id <= 0) {
    $_SESSION['error_message'] = "Invalid certificate.";header("Location: certificate_status.php");
    exit;
}

/*GET CERTIFICATE DATA*/

$sql = "
SELECT
    ec.id,
    ec.employee_id,
    ec.certification_id,
    ec.cert_type,
    ec.cert_number,
    ec.cert_issuer,
    ec.issue_date,
    ec.expiry_date,
    ec.document_file,
    ec.status,
    ec.verification_status,
    e.employee_code,
    e.full_name,
    e.department,
    e.contractor_company,
    c.cert_name
FROM employee_certifications ec
INNER JOIN employees e
ON e.id = ec.employee_id
LEFT JOIN certifications c
ON c.id = ec.certification_id
WHERE ec.id = ?
LIMIT 1
";
 
$stmt = $db->prepare($sql);
if ($stmt === false) {

    $conn = $db->getConnection();

    die(
        "<h2>SQL PREPARE ERROR</h2>" .
        "<pre>" .
        $conn->error .
        "</pre>"
    );
}

$stmt->bind_param("i", $employee_certification_id);
$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows == 0) {

    $_SESSION['error_message'] = "Certificate not found.";

    header("Location: certificate_status.php");
    exit;
}

$certificate = $result->fetch_assoc();
$stmt->close();

/*FORMAT DATE*/

$issue_date = !empty($certificate['issue_date'])
    ? date('d M Y', strtotime($certificate['issue_date']))
    : '-';

$expiry_date = !empty($certificate['expiry_date'])
    ? date('d M Y', strtotime($certificate['expiry_date']))
    : '-';


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_resubmit'])) {
    $error = '';

    /*VALIDASI CSRF*/

    if (
        !isset($_POST['csrf_token']) ||
        !isset($_SESSION['csrf_token']) ||
        !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
    )   {
            $error = "Invalid CSRF Token.";
        }

    /*VALIDASI FILE*/

    if (!$error) {
        if (
            !isset($_FILES['certificate_file']) ||
            $_FILES['certificate_file']['error'] != 0
        )   {
                $error = "Please upload certificate.";
            }
    }

    /*VALIDASI PDF*/
    if (!$error) {
        $extension = strtolower( pathinfo($_FILES['certificate_file']['name'],PATHINFO_EXTENSION));
        if ($extension != 'pdf') {
            $error = "Only PDF allowed.";
        }
    }

    /* VALIDASI SIZE*/
    if (!$error) {
        if ($_FILES['certificate_file']['size'] > (5 * 1024 * 1024)) {
            $error = "Maximum file size is 5 MB.";
        }
    }
    if (!$error) {
        $upload_dir = '../../assets/uploads/certifications/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir,0777,true);
        }

        $employee_code = $certificate['employee_code'];
        $new_filename = $employee_code . '_resubmit_' . time() .'.pdf';
        $upload_path = $upload_dir . $new_filename;

    if (
    move_uploaded_file(
        $_FILES['certificate_file']['tmp_name'],
        $upload_path)) 
    {

    $document_file = 'uploads/certifications/' . $new_filename;
    $conn = $db->getConnection();
    $conn->begin_transaction();

    try {
        /* INSERT NEW CERTIFICATE */

$certification_id = (int)$_POST['certification_id'];

$cert_type = trim($_POST['new_cert_type']);

$cert_number = trim($_POST['new_cert_number']);

$cert_issuer = trim($_POST['new_cert_issuer']);

$issue_date = $_POST['new_issue_date'];

$expiry_date = $_POST['new_expiry_date'];

$notes = trim($_POST['notes']);

$stmt1 = $conn->prepare("
INSERT INTO employee_certifications
(
    employee_id,
    certification_id,
    cert_type,
    cert_number,
    cert_issuer,
    issue_date,
    expiry_date,
    document_file,
    status,
    verification_status,
    notes
)
VALUES
(
    ?, ?, ?, ?, ?, ?, ?, ?, 'pending', 'pending', ?
)
");
        /*UPDATE employees*/

        $stmt2 = $conn->prepare("
            UPDATE employees
            SET
                verification_status = 'pending',
                resubmit_type = 'certificate',
                resubmit_count = IFNULL(resubmit_count,0)+1,
                resubmit_date = NOW()
            WHERE id = ?
        ");

        $stmt2->bind_param("i", $certificate['employee_id']);
        $stmt2->execute();
        $conn->commit();

        $_SESSION['success_message'] = "Certificate resubmitted successfully.";

        header("Location: certificate_status.php");
        exit;

    } catch (Exception $e) {
        $conn->rollback();
        $error = $e->getMessage();
    }

        } else {
            $error = "Failed upload.";
        }

        if (!empty($error)) { 
            $_SESSION['error_message'] = $error;
        }
     }
}

?>
<?php include '../../includes/header.php'; ?>
 
<div class="add-employee-container">

    <div class="page-header-add">
        <div class="header-left">
            <h2>
                <i class="fas fa-upload"></i>
                Resubmit Certificate
            </h2>
            <p>Correct rejected certificate and upload again.</p>
        </div>
        <a href="certificate_status.php" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left"></i>Back
        </a>
    </div>

<form method="POST" enctype="multipart/form-data" class="form-container">     
<input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">    
    <div class="form-section">
            <div class="section-header">
                <h3>
                    <i class="fas fa-exclamation-triangle text-warning"></i> Expired Certificate Information
                </h3>
                <span class="section-number"> 1</span>
        </div>

        <div class="alert alert-warning">

                <strong>Expired Certificate</strong>
                <br>
                This certificate has expired.
                Please submit your latest certificate below.
                The previous certificate will remain stored in the system for history purposes. 
        </div>

        <div class="form-row">
            <div class="form-group col-lg-6">
                <label>Employee Name</label>

        <input type="text" class="form-control" value="<?php echo htmlspecialchars($certificate['full_name']); ?>" readonly>
    </div>

            <div class="form-group col-lg-6">
                <label>Employee Code</label>

        <input type="text" class="form-control" value="<?php echo htmlspecialchars($certificate['employee_code']); ?>"readonly>
            </div>
</div>

    <div class="form-row">
        <div class="form-group col-lg-6">
            <label>Certificate Name</label>

        <input type="text" class="form-control" value="<?php echo htmlspecialchars($certificate['cert_name']); ?>" readonly>

    </div>

        <div class="form-group col-lg-6">
        <label>Certificate Number</label>

        <input type="text" class="form-control" value="<?php echo htmlspecialchars($certificate['cert_number']); ?>" readonly>
    </div>

    </div>
        <div class="form-row">
        <div class="form-group col-lg-6">
        <label>Issuer</label>
        <input type="text" class="form-control" value="<?php echo htmlspecialchars($certificate['cert_issuer']); ?>" readonly>
    </div>

    <div class="form-group col-lg-6">
        <label>Issue Date</label>

        <input type="text" class="form-control" value="<?php echo date('d M Y',strtotime($certificate['issue_date'])); ?>" readonly>
    </div>

</div>

        <div class="form-row">
            <div class="form-group col-lg-6">
            <label>Expiry Date</label>
        <input type="text" class="form-control" value="<?php echo date('d M Y',strtotime($certificate['expiry_date'])); ?>" readonly>

</div>

        <div class="form-group col-lg-6">
        <label>Current Certificate</label>
        <br>
    <a class="btn btn-info" target="_blank" href="../../assets/<?php echo $certificate['document_file']; ?>">
        <i class="fas fa-eye"></i> View Certificate
    </a>

</div>

</div>

</div>

<div class="form-section">    
        <div class="section-header">
            <h3>
                <i class="fas fa-plus-circle text-success"></i>New Certificate Information
            </h3>

        <span class="section-number">2</span>
    </div>

    <div class="form-row">

        <div class="form-group col-lg-6">
            <label>Certificate Name</label>
            <select name="new_certification_id" class="form-control" required>
                <option value="">-- Select Certificate --</option>
                
                <?php
                $certificates = $db->query("SELECT id, cert_name FROM certifications ORDER BY cert_name ASC");
                while($row = $certificates->fetch_assoc()){
                ?>

            <option value="<?php echo $row['id']; ?>">
                <?php echo htmlspecialchars($row['cert_name']); ?>
            </option>

            <?php } ?>

        </select>

    </div>

        <div class="form-group col-lg-6">
            <label>Certificate Type</label>
            <select name="new_cert_type" class="form-control" required>
                <option value="">-- Select Type --</option>
                <option value="Attendance/Participant">Attendance / Participant</option>
                <option value="Competent">Competent</option>
                <option value="Training">Training</option>
            </select>
        </div>

    </div>

    <div class="form-row">
    <div class="form-group col-lg-6">
        <label>Certificate Number</label>
        <input type="text" name="new_cert_number" class="form-control" required>
    </div>

    <div class="form-group col-lg-6">
            <label>Issuer</label>
            <input type="text" name="new_cert_issuer" class="form-control" required>
    </div>

    </div>

    <div class="form-row">
    <div class="form-group col-lg-4">
        <label>Issue Date</label>
        <input type="date" id="issue_date" name="new_issue_date" class="form-control" required>
    </div>

    <div class="form-group col-lg-4">
        <label>Validity Period (Years)</label>
        <input type="number" id="validity" name="validity" class="form-control" min="1" value="2" required>
    </div>

        <div class="form-group col-lg-4">
                <label>Expiry Date</label>
                <input type="date" id="expiry_date" name="new_expiry_date" class="form-control" readonly required>
        </div>
</div>

    <div class="form-group">

        <label>Upload Certificate File</label>
            <div class="file-upload-area" id="certificateUpload">
                <input type="file" id="certificate_file" name="certificate_file" accept=".pdf" hidden>
                <div class="upload-content">
                    <i class="fas fa-file-pdf upload-icon"></i>
                        <p> Click or drag certificate file here</p>

                    <small>PDF only (Max 5 MB)</small>

                </div>
    </div>

        <div class="selected-file mt-3" id="selectedCertificateFile" style="display:none;">
    </div>
</div>
        <div class="form-group">
            <label>Correction Notes</label>
                <textarea name="notes" rows="4" class="form-control" placeholder="Explain what has been corrected..."></textarea>
        </div>


        <div class="form-actions">
            <a href="certificate_status.php" class="btn btn-secondary">Cancel</a>
                <button type="submit" name="submit_resubmit" class="btn btn-primary">
                    <i class="fas fa-upload"></i>Upload Correction
                </button>

        </div>
    </div>

</form>

<?php include '../../includes/footer.php'; ?>

<script>

    const uploadArea=document.getElementById('certificateUpload');
    const input=document.getElementById('certificate_file');
    const preview=document.getElementById('selectedCertificateFile');
    uploadArea.addEventListener('click',()=>{
    input.click();
    });

    input.addEventListener('change',function(){

    if(this.files.length){

        preview.style.display='block';
        preview.innerHTML=
        '<div class="alert alert-success mb-0">'+
        '<i class="fas fa-file-pdf"></i> '+
        this.files[0].name+
        '</div>';
        }

    });

const issueDate = document.getElementById('issue_date');
const validity = document.getElementById('validity');
const expiryDate = document.getElementById('expiry_date');

function calculateExpiry(){

    if(issueDate.value && validity.value){

        const date = new Date(issueDate.value);

        date.setFullYear(
            date.getFullYear() + parseInt(validity.value)
        );

        expiryDate.value =
            date.toISOString().split('T')[0];

    }

}

issueDate.addEventListener('change', calculateExpiry);

validity.addEventListener('input', calculateExpiry);

</script>

<style>
.add-employee-container {
    padding: 20px 0;
    max-width: 1200px;
    margin: 0 auto;
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
    color: #616161;
    font-size: 13px;
    font-weight: 500;
    line-height: 1.5;
}

.file-name {
    display: none;
    color: #2E7D32;
    font-weight: 600;
    font-size: 13px;
    margin-top: 12px;
    background: #E8F5E9;
    padding: 8px 16px;
    border-radius: 6px;
    word-break: break-all;
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

/* Badges */
.badge {
    padding: 5px 12px;
    border-radius: 5px;
    font-size: 11px;
    font-weight: 600;
    display: inline-block;
}

.badge-danger {
    background: #fee2e2;
    color: #dc2626;
}

.badge-warning {
    background: #fef3c7;
    color: #f59e0b;
}

.badge-info {
    background: #ECEFF1;
    color: #37474F;
}

/* Alert Info Custom */
.alert-info-custom {
    background: #ECEFF1;
    border-left: 4px solid #37474F;
    padding: 15px 20px;
    border-radius: 8px;
    color: #37474F;
    margin-bottom: 20px;
    font-size: 13px;
    line-height: 1.6;
}

.alert-info-custom i {
    color: #37474F;
    margin-right: 8px;
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

/* Alert Warning Custom */
.alert-warning-custom {
    background: #fef3c7;
    border-left: 4px solid #f59e0b;
    padding: 15px 20px;
    border-radius: 8px;
    color: #92400e;
    margin-top: 20px;
    font-size: 13px;
    line-height: 1.6;
    display: flex;
    gap: 12px;
    align-items: flex-start;
}

.alert-warning-custom i {
    color: #f59e0b;
    font-size: 20px;
    margin-top: 2px;
}

.alert-warning-custom strong {
    display: block;
    color: #92400e;
    margin-bottom: 5px;
}

.alert-warning-custom p {
    margin: 0;
    color: #92400e;
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

.btn-sm {
    padding: 6px 12px;
    font-size: 12px;
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

.btn-lg {
    padding: 14px 28px;
    font-size: 16px;
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