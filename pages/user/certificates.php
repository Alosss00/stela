<?php
$page_title = 'Certification List';
require_once '../../includes/auth.php';
require_once '../../includes/db.php';


// Pastikan session sudah aktif di bagian paling atas file
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Only USER role can access this page
checkPageAccess(['user']);

$db = new Database();
$message = '';
$error = '';
$company_name = $_SESSION['company_name'] ?? '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    // --- 1. VALIDASI TOKEN ANTI-CSRF GLOBAL UNTUK SEMUA AKSI POST ---
    if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $error = 'Akses ditolak: Token keamanan tidak valid atau telah kedaluwarsa.';
    } else {
        
        // --- 2. LOGIKA UTAMA (Hanya berjalan jika lolos validasi CSRF) ---
        if (isset($_POST['action']) && $_POST['action'] == 'add_certification') {
            $cert_name = $db->escapeString(trim($_POST['cert_name'] ?? ''));
            $cert_type = $db->escapeString(trim($_POST['cert_type'] ?? ''));
            $issuing_authority = $db->escapeString(trim($_POST['issuing_authority'] ?? ''));
            $is_active = isset($_POST['is_active']) ? 1 : 0;

            // Validate required fields
            if (empty($cert_name) || empty($cert_type) || empty($issuing_authority)) {
                $error = 'All fields are required!';
            } else {
                $sql = "INSERT INTO certifications (cert_name, cert_type, issuing_authority, is_active)
                        VALUES ('$cert_name', '$cert_type', '$issuing_authority', $is_active)";
                
                if ($db->query($sql)) {
                    $message = 'Certification successfully added!';
                    $certifications = $db->query("SELECT * FROM certifications WHERE is_active = 1 ORDER BY cert_type, cert_name");
                } else {
                    $error = 'Failed to add certification! Error: ' . $db->getConnection()->error;
                    error_log("Error adding certification: " . $db->getConnection()->error);
                }
            }
            
        } elseif (isset($_POST['action']) && $_POST['action'] == 'edit_certification') {
            $cert_id = intval($_POST['cert_id']);
            $cert_name = $db->escapeString(trim($_POST['cert_name'] ?? ''));
            $cert_type = $db->escapeString(trim($_POST['cert_type'] ?? ''));
            $issuing_authority = $db->escapeString(trim($_POST['issuing_authority'] ?? ''));
            $is_active = isset($_POST['is_active']) ? 1 : 0;
            
            // Validate required fields
            if (empty($cert_name) || empty($cert_type) || empty($issuing_authority)) {
                $error = 'All fields are required!';
            } else {
                $sql = "UPDATE certifications SET cert_name='$cert_name', cert_type='$cert_type', issuing_authority='$issuing_authority', is_active=$is_active WHERE id=$cert_id";

                if ($db->query($sql)) {
                    $message = 'Certification successfully updated!';
                    $certifications = $db->query("SELECT * FROM certifications WHERE is_active = 1 ORDER BY cert_type, cert_name");
                } else {
                    $error = 'Failed to update certification! Error: ' . $db->getConnection()->error;
                    error_log("Error updating certification: " . $db->getConnection()->error);
                }
            }
            
        } elseif (isset($_POST['action']) && $_POST['action'] == 'delete_certification') {
            $cert_id = intval($_POST['cert_id']);
            $sql = "DELETE FROM certifications WHERE id=$cert_id";

            if ($db->query($sql)) {
                $message = 'Certification successfully deleted!';
                $certifications = $db->query("SELECT * FROM certifications WHERE is_active = 1 ORDER BY cert_type, cert_name");
            } else {
                $error = 'Failed to delete certification!';
                error_log("Error deleting certification: " . $db->getConnection()->error);
            }
        }
    } // End of CSRF else
}

// Get all certifications
$certifications = $db->query("SELECT id, cert_name, cert_type, issuing_authority, is_active FROM certifications WHERE is_active = 1 ORDER BY cert_type, cert_name");

require_once '../../includes/header.php';
?>

<div class="certificates-container">
    <!-- Page Header -->
    <div class="page-header-cert">
        <div class="header-left">
            <h2><i class="fas fa-certificate"></i> <span data-lang="certification-competency-list">Certification/Competency List</span></h2>
            <p data-lang="manage-certification-competency-data">Manage certification and competency data</p>
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
    
    <!-- Statistics Card -->
    <div class="stat-card-simple">
        <div class="stat-number"><?php echo $certifications->num_rows; ?></div>
        <div class="stat-label" data-lang="total-active-certifications">Total Active Certifications</div>
    </div>

    <!-- Certificates Table Card -->
    <div class="card card-cert">
        <div class="card-header-cert">
            <h3><i class="fas fa-list"></i> <span data-lang="certification-list">Certification List</span></h3>
        </div>
        <div class="card-body">
            <?php if ($certifications->num_rows > 0): ?>
                <div class="table-responsive">
                    <table class="table table-certificates">
                        <thead>
                            <tr>
                                <th class="col-name" data-lang="certification-name">Certification Name</th>
                                <th class="col-type" data-lang="certificate-type">Type</th>
                                <th class="col-issuer" data-lang="issuer">Issuer</th>
                                <th class="col-action" data-lang="action">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            if ($certifications && $certifications->num_rows > 0) {
                                while ($row = $certifications->fetch_assoc()): 
                            ?>
                            <tr class="cert-row">
                                <td class="col-name">
                                    <strong><?php echo htmlspecialchars($row['cert_name']); ?></strong>
                                </td>
                                <td class="col-type">
                                    <span class="badge badge-cert">
                                        <i class="fas fa-tag"></i> <?php echo htmlspecialchars($row['cert_type']); ?>
                                    </span>
                                </td>
                                <td class="col-issuer">
                                    <span class="issuer-badge"><?php echo htmlspecialchars($row['issuing_authority']); ?></span>
                                </td>
                                <td class="col-action">
                                    <div class="action-buttons">
                                        <button onclick="editCertification(<?php echo $row['id']; ?>, '<?php echo htmlspecialchars(str_replace("'", "\\'", $row['cert_name'])); ?>', '<?php echo htmlspecialchars($row['cert_type']); ?>', '<?php echo htmlspecialchars(str_replace("'", "\\'", $row['issuing_authority'])); ?>')" class="btn btn-sm btn-warning btn-action">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="btn btn-sm btn-danger btn-action" onclick="deleteCertification(<?php echo $row['id']; ?>)">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php 
                                endwhile;
                            }
                            ?>
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
    
    <!-- Back Button -->
    <div class="action-footer-cert">
        <a href="dashboard.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> <span data-lang="back-to-dashboard">Back to Dashboard</span>
        </a>
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
            <input type="hidden" name="csrf_token" value="<?php echo isset($_SESSION['csrf_token']) ? $_SESSION['csrf_token'] : ''; ?>">
            
            <input type="hidden" name="action" value="add_certification">
            <div class="modal-body">
                <div class="form-group">
                    <label for="cert_name" data-lang="certificate-name">Certificate Name <span class="text-danger">*</span></label>
                    <input type="text" id="cert_name" name="cert_name" class="form-control" required placeholder="Example: ISO 9001" data-lang-placeholder="iso-9001-example">
                </div>
                <div class="form-group">
                    <label for="cert_type" data-lang="certificate-type">Certificate Type <span class="text-danger">*</span></label>
                    <select id="cert_type" name="cert_type" class="form-control" required>
                        <option value="" data-lang="select-type">-- Select Type --</option>
                        <option value="Attendance/Peserta" data-lang="attendance-participant">Attendance/Peserta</option>
                        <option value="Kompeten" data-lang="competent">Kompeten</option>
                        <option value="Training" data-lang="training">Training</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="issuing_authority" data-lang="issuer">Issuer <span class="text-danger">*</span></label>
                    <input type="text" id="issuing_authority" name="issuing_authority" class="form-control" required placeholder="Example: National Certification Board" data-lang-placeholder="national-certification-board-example">
                </div>
                <div class="form-group form-check">
                    <input type="checkbox" id="is_active" name="is_active" class="form-check-input" checked>
                    <label class="form-check-label" for="is_active" data-lang="active">Active</label>
                </div>
            </div>
            <div class="modal-footer modal-footer-cert">
                <button type="button" class="btn btn-secondary" onclick="closeModal('addModal')"><span data-lang="cancel">Cancel</span></button>
                <button type="submit" class="btn btn-primary"><span data-lang="save">Save</span></button>
            </div>
        </form>
    </div>
</div>

<div id="editModal" class="modal">
    <div class="modal-content modal-cert">
        <div class="modal-header modal-header-cert">
            <h3><i class="fas fa-edit"></i> <span data-lang="edit-certification">Edit Certification</span></h3>
            <span class="close" onclick="closeModal('editModal')">&times;</span>
        </div>
        <form method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?php echo isset($_SESSION['csrf_token']) ? $_SESSION['csrf_token'] : ''; ?>">
            
            <input type="hidden" name="action" value="edit_certification">
            <input type="hidden" name="cert_id" id="edit_cert_id">
            <div class="modal-body">
                <div class="form-group">
                    <label for="edit_cert_name" data-lang="certificate-name">Certificate Name <span class="text-danger">*</span></label>
                    <input type="text" id="edit_cert_name" name="cert_name" class="form-control" required>
                </div>
                <div class="form-group">
                    <label for="edit_cert_type" data-lang="certificate-type">Certificate Type <span class="text-danger">*</span></label>
                    <select id="edit_cert_type" name="cert_type" class="form-control" required>
                        <option value="" data-lang="select-type">-- Select Type --</option>
                        <option value="Attendance/Peserta" data-lang="attendance-participant">Attendance/Peserta</option>
                        <option value="Kompeten" data-lang="competent">Kompeten</option>
                        <option value="Training" data-lang="training">Training</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="edit_issuing_authority" data-lang="issuer">Issuer <span class="text-danger">*</span></label>
                    <input type="text" id="edit_issuing_authority" name="issuing_authority" class="form-control" required>
                </div>
                <div class="form-group form-check">
                    <input type="checkbox" id="edit_is_active" name="is_active" class="form-check-input" checked>
                    <label class="form-check-label" for="edit_is_active" data-lang="active">Active</label>
                </div>
            </div>
            <div class="modal-footer modal-footer-cert">
                <button type="button" class="btn btn-secondary" onclick="closeModal('editModal')"><span data-lang="cancel">Cancel</span></button>
                <button type="submit" class="btn btn-primary"><span data-lang="update">Update</span></button>
            </div>
        </form>
    </div>
</div>

<div id="deleteModal" class="modal">
    <div class="modal-content modal_cert">
        <div class="modal-header modal-header-cert">
            <h3><i class="fas fa-trash"></i> <span data-lang="delete-confirmation">Delete Confirmation</span></h3>
            <span class="close" onclick="closeModal('deleteModal')">&times;</span>
        </div>
        <form method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?php echo isset($_SESSION['csrf_token']) ? $_SESSION['csrf_token'] : ''; ?>">
            
            <input type="hidden" name="action" value="delete_certification">
            <input type="hidden" name="cert_id" id="delete_cert_id">
            <div class="modal-body">
                <div class="delete-warning">
                    <i class="fas fa-exclamation-triangle"></i>
                    <p data-lang="confirm-delete-certification">Are you sure you want to delete this certification?</p>
                    <small data-lang="this-action-cannot-be-undone">This action cannot be undone.</small>
                </div>
            </div>
            <div class="modal-footer modal-footer-cert">
                <button type="button" class="btn btn-secondary" onclick="closeModal('deleteModal')"><span data-lang="cancel">Cancel</span></button>
                <button type="submit" class="btn btn-danger"><span data-lang="delete">Delete</span></button>
            </div>
        </form>
    </div>
</div>

<script>
function editCertification(id, cert_name, cert_type, issuing_authority) {
    document.getElementById('edit_cert_id').value = id;
    document.getElementById('edit_cert_name').value = cert_name;
    document.getElementById('edit_cert_type').value = cert_type;
    document.getElementById('edit_issuing_authority').value = issuing_authority;
    openModal('editModal');
}

function deleteCertification(id) {
    document.getElementById('delete_cert_id').value = id;
    openModal('deleteModal');
}
</script>

<style>
.certificates-container {
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

/* Stat Card */
.stat-card-simple {
    background: white;
    border-radius: 8px;
    padding: 20px;
    margin-bottom: 25px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
    border-left: 4px solid #37474F;
    display: flex;
    align-items: center;
    gap: 15px;
}

.stat-number {
    font-size: 32px;
    font-weight: 700;
    color: #37474F;
}

.stat-label {
    color: #666;
    font-size: 14px;
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
}

.card-header-cert i {
    color: #37474F;
}

/* Table */
.table-certificates {
    margin: 0;
}

.table-certificates thead th {
    background: #f8f9fa;
    color: #333;
    font-weight: 600;
    font-size: 12px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    border: none;
    padding: 15px;
}

.cert-row {
    border-bottom: 1px solid #f0f0f0;
    transition: background-color 0.2s ease;
}

.cert-row:hover {
    background-color: #f8f9ff;
}

.table-certificates td {
    padding: 15px;
    vertical-align: middle;
}

.col-name { width: 35%; }
.col-type { width: 25%; }
.col-issuer { width: 30%; }
.col-action { width: 10%; }

.code-badge {
    background: #ECEFF1;
    color: #37474F;
    padding: 6px 10px;
    border-radius: 6px;
    font-size: 11px;
    font-weight: 600;
    display: inline-block;
    font-family: 'Courier New', monospace;
}

.badge-cert {
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

.issuer-badge {
    background: #f3f4f6;
    color: #666;
    padding: 6px 12px;
    border-radius: 6px;
    font-size: 12px;
    display: inline-block;
}

.description-text {
    color: #666;
    font-size: 12px;
}

.action-buttons {
    display: flex;
    gap: 5px;
}

.btn-action {
    padding: 6px 10px;
    font-size: 12px;
}

.btn-action:hover {
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

/* Action Footer */
.action-footer-cert {
    margin-top: 25px;
    padding-top: 20px;
    border-top: 1px solid #f0f0f0;
}

/* Modal */
.modal-cert {
    max-width: 500px;
}

.modal-header-cert {
    background: linear-gradient(135deg, #37474F 0%, #616161 100%);
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

/* Form */
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

.form-check {
    display: flex;
    align-items: center;
}

.form-check-input {
    width: 18px;
    height: 18px;
    margin-right: 8px;
}

.form-check-label {
    margin: 0;
    font-weight: normal;
    color: #333;
}

/* Delete Warning */
.delete-warning {
    text-align: center;
    padding: 20px 0;
}

.delete-warning i {
    font-size: 40px;
    color: #ef4444;
    margin-bottom: 15px;
    display: block;
}

.delete-warning p {
    margin: 0 0 10px 0;
    font-size: 16px;
    color: #333;
}

.delete-warning small {
    display: block;
    color: #999;
    font-size: 12px;
}

/* Responsive */
@media (max-width: 1024px) {
    .page-header-cert {
        flex-direction: column;
        gap: 20px;
        text-align: center;
    }
    
    .col-desc { display: none; }
}

@media (max-width: 768px) {
    .page-header-cert {
        padding: 25px 15px;
    }
    
    .header-left h2 {
        font-size: 20px;
    }
    
    .col-issuer { display: none; }
    
    .table-responsive {
        font-size: 12px;
    }
}
</style>

<?php require_once '../../includes/footer.php'; ?>



