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

?>
<?php include '../../includes/header.php'; ?>
/*Section Pertama*/
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

/*Section Kedua*/

<div class="form-section">

    <div class="section-header">

        <h3>

            <i class="fas fa-file-upload"></i>

            Upload New Certificate

        </h3>

        <span class="section-number">

            2

        </span>

    </div>
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