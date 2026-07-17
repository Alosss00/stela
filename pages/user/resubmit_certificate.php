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
        /*UPDATE employee_certifications*/
        $notes = trim($_POST['notes']);
        $stmt1 = $conn->prepare("
            UPDATE employee_certifications
            SET
                document_file = ?,
                verification_status = 'pending',
                notes = ?,
                updated_at = NOW()
            WHERE id = ?
        ");

        $stmt1->bind_param(
            "ssi",
            $document_file,
            $notes,
            $employee_certification_id
        );

        $stmt1->execute();

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
                            <span><span data-lang="current-file">Current file:</span> <a href="../../assets/<?php echo htmlspecialchars($cert['document_file']); ?>" target="_blank" data-lang="view-certificate">View Certificate</a></span>
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