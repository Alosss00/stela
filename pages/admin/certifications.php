<?php
$page_title = 'Certification Management';
require_once '../../includes/auth.php';
require_once '../../includes/db.php';

$db = new Database();
$message = '';
$error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Validasi token Anti-CSRF secara global untuk setiap request POST
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        http_response_code(403); // Set status HTTP ke 403 Forbidden
        die('CSRF token mismatch');
    }

    if (isset($_POST['action'])) {
        if ($_POST['action'] == 'add') {
            $cert_name = $db->escapeString($_POST['cert_name']);

            $sql = "INSERT INTO certifications (cert_name)
                    VALUES ('$cert_name')";

            if ($db->query($sql)) {
                $message = 'Certification Added';
            } else {
                $error = 'Failed to Add Certification';
            }
        } elseif ($_POST['action'] == 'edit') {
            $id = intval($_POST['id']);
            $cert_name = $db->escapeString($_POST['cert_name']);

            $sql = "UPDATE certifications SET
                    cert_name = '$cert_name'
                    WHERE id = $id";

            if ($db->query($sql)) {
                $message = 'Certification Updated';
            } else {
                $error = 'Failed to Update Certification';
            }
        }
    }
}

// Handle delete
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    if ($db->query("UPDATE certifications SET is_active = 0 WHERE id = $id")) {
        $message = 'Certification Deleted';
    }
}

// Get all certifications
$certifications = $db->query("SELECT * FROM certifications WHERE is_active = 1 ORDER BY cert_name");

// Get statistics
$total_certifications = $certifications->num_rows;

require_once '../../includes/header.php';
?>

<div class="certifications-container">
    <!-- Page Header -->
    <div class="page-header-cert">
        <div class="header-left">
            <h2><i class="fas fa-certificate"></i> <span data-lang="certification-management">Certification Management</span></h2>
            <p data-lang="manage-certification-competency-data">Manage organization certification and competency list</p>
        </div>
        <button class="btn btn-primary btn-lg-cert" onclick="openModal('addModal')">
            <i class="fas fa-plus-circle"></i> <span data-lang="add-certification">Add Certification</span>
        </button>
    </div>
    
    <?php if ($message): ?>
    <div class="alert alert-success alert-custom-cert">
        <i class="fas fa-check-circle"></i>
        <div>
            <strong data-lang="success">Success!</strong>
            <p><?php echo htmlspecialchars($message); ?></p>
        </div>
    </div>
    <?php endif; ?>
    
    <?php if ($error): ?>
    <div class="alert alert-error alert-custom-cert">
        <i class="fas fa-exclamation-circle"></i>
        <div>
            <strong data-lang="error">Error!</strong>
            <p><?php echo htmlspecialchars($error); ?></p>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Statistics Cards -->
    <div class="stats-grid-cert">
        <div class="stat-box-cert stat-total">
            <div class="stat-icon-cert"><i class="fas fa-certificate"></i></div>
            <div class="stat-info">
                <div class="stat-number"><?php echo $total_certifications; ?></div>
                <div class="stat-text" data-lang="total-active-certifications">Total Active Certifications</div>
            </div>
        </div>
    </div>
    
    <!-- Certifications Table Card -->
    <div class="card card-cert">
        <div class="card-header-cert">
            <h3><i class="fas fa-list"></i> <span data-lang="certification-competency-list">Certification/Competency List</span></h3>
        </div>
        <div class="card-body-cert">
            <?php if ($certifications->num_rows > 0): ?>
                <div class="table-responsive">
                    <table class="table-cert">
                        <thead>
                            <tr>
                                <th class="col-name" data-lang="certification-name">Certification Name</th>
                                <th class="col-action" data-lang="action">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $certifications->data_seek(0);
                            while ($row = $certifications->fetch_assoc()):
                            ?>
                            <tr class="cert-row">
                                <td class="col-name" data-label="Certification Name">
                                    <strong><?php echo htmlspecialchars($row['cert_name']); ?></strong>
                                </td>
                                <td class="col-action" data-label="Action">
                                    <div class="action-buttons-cert">
                                        <button onclick='editCertification(<?php echo json_encode($row); ?>)' class="btn-action-cert btn-edit-cert" title="Edit" data-lang-title="edit">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <a href="?delete=<?php echo $row['id']; ?>"
                                           class="btn-action-cert btn-delete-cert"
                                           onclick="return confirm(window.getLanguageText('confirm-delete-certification'))" title="Delete" data-lang-title="delete">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty-state-cert">
                    <i class="fas fa-inbox"></i>
                    <p data-lang="no-certifications-yet">No certifications yet</p>
                    <button class="btn btn-primary" onclick="openModal('addModal')">
                        <i class="fas fa-plus"></i> <span data-lang="add-first-certification">Add First Certification</span>
                    </button>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Add Modal -->
<div id="addModal" class="modal">
    <div class="modal-content modal-cert">
        <div class="modal-header modal-header-cert">
            <h3><i class="fas fa-plus-circle"></i> <span data-lang="add-new-certification">Add New Certification</span></h3>
            <span class="close" onclick="closeModal('addModal')">&times;</span>
        </div>
            <form method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?php echo isset($_SESSION['csrf_token']) ? htmlspecialchars($_SESSION['csrf_token']) : ''; ?>">
            
            <input type="hidden" name="action" value="add">
            <div class="modal-body">
                <div class="form-group">
                    <label for="cert_name"><span data-lang="certification-name">Certification Name</span> <span class="text-danger">*</span></label>
                    <input type="text" id="cert_name" name="cert_name" class="form-control" required>
                </div>
            </div>
            <div class="modal-footer modal-footer-cert">
                <button type="button" class="btn btn-secondary" onclick="closeModal('addModal')">
                    <i class="fas fa-times"></i> <span data-lang="cancel">Cancel</span>
                </button>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-check"></i> <span data-lang="save">Save</span>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Modal -->
<div id="editModal" class="modal">
    <div class="modal-content modal-cert">
        <div class="modal-header modal-header-cert">
            <h3><i class="fas fa-edit"></i> <span data-lang="edit-certification">Edit Certification</span></h3>
            <span class="close" onclick="closeModal('editModal')">&times;</span>
        </div>
            <form method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?php echo isset($_SESSION['csrf_token']) ? htmlspecialchars($_SESSION['csrf_token']) : ''; ?>">
            
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="id" id="edit_id">
            <div class="modal-body">
                <div class="form-group">
                    <label for="edit_cert_name"><span data-lang="certification-name">Certification Name</span> <span class="text-danger">*</span></label>
                    <input type="text" id="edit_cert_name" name="cert_name" class="form-control" required>
                </div>
            </div>
            <div class="modal-footer modal-footer-cert">
                <button type="button" class="btn btn-secondary" onclick="closeModal('editModal')">
                    <i class="fas fa-times"></i> <span data-lang="cancel">Cancel</span>
                </button>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-check"></i> <span data-lang="update">Update</span>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function editCertification(data) {
    document.getElementById('edit_id').value = data.id;
    document.getElementById('edit_cert_name').value = data.cert_name;
    openModal('editModal');
}
</script>

<style>
.certifications-container {
    padding: 20px 0;
}

/* Page Header */
.page-header-cert {
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

.btn-lg-cert {
    padding: 12px 25px;
    font-size: 15px;
    white-space: nowrap;
    background: #37474F;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
}

.btn-lg-cert:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 15px rgba(0, 0, 0, 0.3);
}

/* Alert Custom */
.alert-custom-cert {
    display: flex;
    gap: 15px;
    align-items: flex-start;
    padding: 20px;
    margin-bottom: 30px;
    border-radius: 8px;
    border-left: 4px solid;
}

.alert-success.alert-custom-cert {
    background: #E8F5E9;
    border-left-color: #2E7D32;
}

.alert-success.alert-custom-cert i {
    color: #2E7D32;
    font-size: 20px;
}

.alert-error.alert-custom-cert {
    background: #fee2e2;
    border-left-color: #ef4444;
}

.alert-error.alert-custom-cert i {
    color: #ef4444;
    font-size: 20px;
}

.alert-custom-cert strong {
    display: block;
    margin-bottom: 5px;
}

.alert-custom-cert p {
    margin: 0;
}

/* Statistics Grid */
.stats-grid-cert {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
    gap: 15px;
    margin-bottom: 30px;
}

.stat-box-cert {
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

.stat-box-cert:hover {
    transform: translateY(-3px);
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.12);
}

.stat-total { border-left-color: #37474F; }
.stat-attendance { border-left-color: #37474F; }
.stat-competent { border-left-color: #f59e0b; }
.stat-training { border-left-color: #2E7D32; }

.stat-icon-cert {
    font-size: 24px;
    width: 45px;
    height: 45px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 8px;
    color: white;
    flex-shrink: 0;
}

.stat-total .stat-icon-cert { background: #37474F; }
.stat-attendance .stat-icon-cert { background: #37474F; }
.stat-competent .stat-icon-cert { background: #f59e0b; }
.stat-training .stat-icon-cert { background: #2E7D32; }

.stat-info {
    flex: 1;
}

.stat-number {
    font-size: 22px;
    font-weight: 700;
    color: #333;
    line-height: 1;
}

.stat-text {
    color: #666;
    font-size: 11px;
    margin-top: 4px;
}

/* Card */
.card-cert {
    background: white;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
    margin-bottom: 25px;
}

.card-header-cert {
    padding: 20px;
    border-bottom: 2px solid #f0f0f0;
}

.card-header-cert h3 {
    margin: 0;
    font-size: 18px;
    color: #333;
    display: flex;
    align-items: center;
    gap: 10px;
    font-weight: 600;
}

.card-header-cert i {
    color: #37474F;
}

.card-body-cert {
    padding: 0;
}

/* Table */
.table-cert {
    width: 100%;
    border-collapse: collapse;
    margin: 0;
}

.table-cert thead th {
    background: #f8f9fa;
    color: #333;
    font-weight: 600;
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    border: none;
    padding: 15px;
    text-align: left;
}

.cert-row {
    border-bottom: 1px solid #f0f0f0;
    transition: background-color 0.2s ease;
}

.cert-row:hover {
    background-color: #f8f9ff;
}

.table-cert td {
    padding: 15px;
    vertical-align: middle;
    font-size: 13px;
}

.col-name { width: 85%; }
.col-action { width: 15%; }

.badge-cert-type {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: #ECEFF1;
    color: #37474F;
    padding: 6px 12px;
    border-radius: 6px;
    font-size: 11px;
    font-weight: 600;
}

.issuer-tag {
    background: #f3f4f6;
    color: #666;
    padding: 6px 12px;
    border-radius: 6px;
    font-size: 12px;
    display: inline-block;
}

.action-buttons-cert {
    display: flex;
    gap: 6px;
}

.btn-action-cert {
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
}

.btn-edit-cert {
    background: #fef3c7;
    color: #f59e0b;
}

.btn-edit-cert:hover {
    background: #f59e0b;
    color: white;
    transform: translateY(-1px);
}

.btn-delete-cert {
    background: #fee2e2;
    color: #ef4444;
}

.btn-delete-cert:hover {
    background: #ef4444;
    color: white;
    transform: translateY(-1px);
}

/* Empty State */
.empty-state-cert {
    text-align: center;
    padding: 60px 20px;
    color: #999;
}

.empty-state-cert i {
    font-size: 48px;
    margin-bottom: 15px;
    opacity: 0.5;
}

.empty-state-cert p {
    margin: 15px 0;
    font-size: 16px;
}

/* Modal */
.modal-cert {
    max-width: 500px;
}

.modal-header-cert {
    background: #37474F;
    color: white;
    padding: 20px;
    border-radius: 8px 8px 0 0;
}

.modal-header-cert h3 {
    margin: 0;
    font-size: 18px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.modal-header-cert .close {
    color: white;
    opacity: 0.8;
    cursor: pointer;
}

.modal-header-cert .close:hover {
    opacity: 1;
}

.modal-footer-cert {
    display: flex;
    gap: 10px;
    justify-content: flex-end;
    padding: 15px 20px;
    border-top: 1px solid #f0f0f0;
}

.form-group {
    margin-bottom: 15px;
}

.form-group label {
    display: block;
    margin-bottom: 6px;
    font-weight: 600;
    color: #333;
    font-size: 13px;
}

.form-control {
    width: 100%;
    padding: 10px 12px;
    border: 2px solid #e9ecef;
    border-radius: 6px;
    font-size: 13px;
    transition: border-color 0.3s ease;
    font-family: inherit;
}

.form-control:focus {
    outline: none;
    border-color: #37474F;
}

/* Responsive */
@media (max-width: 1200px) {
    .stats-grid-cert {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 992px) {
    .page-header-cert {
        flex-direction: column;
        gap: 20px;
        text-align: center;
        padding: 25px 20px;
    }
    
    .header-left h2 {
        font-size: 22px;
        justify-content: center;
    }
    
    .btn-lg-cert {
        width: 100%;
    }
    
    .modal-cert {
        max-width: 95%;
        margin: 2% auto;
    }
    
    .col-issuer {
        display: none;
    }
    
    .table-cert thead th.col-issuer {
        display: none;
    }
}

@media (max-width: 768px) {
    .certifications-container {
        padding: 15px 0;
    }
    
    .page-header-cert {
        padding: 20px 15px;
        margin-bottom: 20px;
        border-radius: 8px;
    }
    
    .header-left h2 {
        font-size: 18px;
    }
    
    .header-left p {
        font-size: 13px;
    }
    
    .stats-grid-cert {
        grid-template-columns: 1fr 1fr;
        gap: 10px;
    }
    
    .stat-box-cert {
        padding: 15px;
        gap: 10px;
    }
    
    .stat-icon-cert {
        width: 40px;
        height: 40px;
        font-size: 18px;
    }
    
    .stat-number {
        font-size: 18px;
    }
    
    .stat-text {
        font-size: 10px;
    }
    
    .card-header-cert {
        padding: 15px;
    }
    
    .card-header-cert h3 {
        font-size: 16px;
    }
    
    .table-cert thead th,
    .table-cert td {
        padding: 10px 8px;
        font-size: 11px;
    }
    
    .col-type {
        width: 35%;
    }
    
    .col-name {
        width: 50%;
    }
    
    .col-action {
        width: 15%;
    }
    
    .badge-cert-type {
        padding: 4px 8px;
        font-size: 10px;
    }
    
    .btn-action-cert {
        width: 32px;
        height: 32px;
        font-size: 12px;
    }
    
    .action-buttons-cert {
        gap: 4px;
    }
    
    .modal-body {
        padding: 15px;
    }
    
    .modal-header-cert {
        padding: 15px;
    }
    
    .modal-header-cert h3 {
        font-size: 16px;
    }
    
    .modal-footer-cert {
        padding: 15px;
        flex-direction: column;
    }
    
    .modal-footer-cert .btn {
        width: 100%;
    }
    
    .form-control {
        padding: 8px 10px;
        font-size: 13px;
    }
    
    .alert-custom-cert {
        padding: 15px;
        flex-direction: column;
        text-align: center;
    }
    
    .alert-custom-cert i {
        font-size: 24px;
    }
}

@media (max-width: 576px) {
    .certifications-container {
        padding: 10px 0;
    }
    
    .page-header-cert {
        padding: 15px;
        margin-bottom: 15px;
    }
    
    .header-left h2 {
        font-size: 16px;
        flex-wrap: wrap;
    }
    
    .stats-grid-cert {
        grid-template-columns: 1fr;
        gap: 8px;
    }
    
    .stat-box-cert {
        padding: 12px;
        flex-direction: row;
    }
    
    .stat-icon-cert {
        width: 38px;
        height: 38px;
        font-size: 16px;
        flex-shrink: 0;
    }
    
    .stat-number {
        font-size: 16px;
    }
    
    .card-cert {
        margin: 0 -10px;
        border-radius: 0;
    }
    
    .card-header-cert {
        padding: 12px 15px;
    }
    
    .card-header-cert h3 {
        font-size: 14px;
    }
    
    .table-cert thead th,
    .table-cert td {
        padding: 8px 6px;
        font-size: 10px;
    }
    
    .col-name strong {
        font-size: 11px;
    }
    
    .btn-action-cert {
        width: 28px;
        height: 28px;
        font-size: 11px;
    }
    
    .modal-content {
        margin: 0;
        border-radius: 0;
        height: 100%;
        max-height: 100%;
    }
    
    .modal-cert {
        max-width: 100%;
        margin: 0;
    }
    
    .modal-body {
        max-height: calc(100vh - 130px);
        padding: 12px;
    }
    
    .form-group label {
        font-size: 12px;
    }
    
    .form-control {
        padding: 8px;
        font-size: 12px;
    }
    
    .empty-state-cert {
        padding: 40px 15px;
    }
    
    .empty-state-cert i {
        font-size: 36px;
    }
    
    .empty-state-cert p {
        font-size: 14px;
    }
}

/* Table Responsive - Convert to cards on very small screens */
@media (max-width: 480px) {
    .table-responsive {
        overflow-x: visible;
    }
    
    .table-cert {
        display: block;
    }
    
    .table-cert thead {
        display: none;
    }
    
    .table-cert tbody {
        display: block;
    }
    
    .cert-row {
        display: block;
        margin-bottom: 15px;
        background: white;
        border: 1px solid #e9ecef;
        border-radius: 8px;
        padding: 15px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    }
    
    .cert-row td {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 8px 0;
        border-bottom: 1px solid #f0f0f0;
        width: 100% !important;
    }
    
    .cert-row td:last-child {
        border-bottom: none;
        justify-content: center;
        padding-top: 12px;
    }
    
    .cert-row td::before {
        content: attr(data-label);
        font-weight: 600;
        color: #666;
        font-size: 11px;
        text-transform: uppercase;
    }
    
    .cert-row .col-name,
    .cert-row .col-type,
    .cert-row .col-action {
        display: flex;
    }
    
    .cert-row .col-issuer {
        display: none;
    }
    
    .action-buttons-cert {
        justify-content: center;
        width: 100%;
    }
    
    .btn-action-cert {
        width: 40px;
        height: 40px;
        font-size: 14px;
    }
    
    .badge-cert-type {
        font-size: 11px;
        padding: 5px 10px;
    }
}
</style>

<?php require_once '../../includes/footer.php'; ?>




