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
    e.position_id,
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

<div class="container-fluid py-4">
    <div class="row justify-content-center">
        <div class="col-lg-10">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-warning text-dark">
                    <h4 class="mb-0">
                        <i class="fas fa-upload"></i>Resubmit Certificate
                    </h4>
                </div>

                <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                        <label class="form-label fw-bold">Employee</label>
            <input type="text" class="form-control"value="<?php echo htmlspecialchars($certificate['full_name']); ?>"readonly>
        </div>
    </div>

    <div class="col-md-6">
        <div class="mb-3">
            <label class="form-label fw-bold">Employee Code</label>
            <input type="text" class="form-control" value="<?php echo htmlspecialchars($certificate['employee_code']); ?>"readonly>
        </div>
    </div>

</div>
    <div class="row">
    <div class="col-md-6">
        <div class="mb-3">
            <label class="form-label fw-bold">Certificate</label>
            <input type="text" class="form-control" value="<?php echo htmlspecialchars($certificate['cert_name']); ?>"readonly>
        </div>

    </div>
    <div class="col-md-6">
        <div class="mb-3">
            <label class="form-label fw-bold">Certificate Number</label>
            <input type="text" class="form-control" value="<?php echo htmlspecialchars($certificate['cert_number']); ?>" readonly>
        </div>
    </div>

</div>
<div class="row">
    <div class="col-md-6">
        <div class="mb-3">
            <label class="form-label fw-bold">Issue Date</label>
            <input type="text" class="form-control" value="<?php echo $issue_date; ?>" readonly>
        </div>

    </div>
    <div class="col-md-6">
        <div class="mb-3">
            <label class="form-label fw-bold">Expiry Date</label>
            <input type="text" class="form-control" value="<?php echo $expiry_date; ?>" readonly>
        </div>
    </div>

</div>
<div class="mb-4">
    <label class="form-label fw-bold">Current Certificate</label>

    <br>
    <?php if (!empty($certificate['document_file'])): ?>
        <a href="../../assets/<?php echo htmlspecialchars($certificate['document_file']); ?>" target="_blank"
            class="btn btn-info">
            <i class="fas fa-eye"></i> View Certificate
        </a>

    <?php else: ?>
        <span class="badge bg-danger"> No Document</span>
    <?php endif; ?>
</div>
            </div>

            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>