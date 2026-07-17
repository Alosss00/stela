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
                $upload_path
            )
        ) {
            $document_file ='uploads/certifications/' .
                $new_filename;

        } else {
            $error = "Failed upload.";
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
                    <i class="fas fa-certificate"></i> Certificate Information
                </h3>
                <span class="section-number"> 1</span>
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
                <i class="fas fa-file-upload"></i>Upload New Certificate
            </h3>

        <span class="section-number">2</span>
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

</script>