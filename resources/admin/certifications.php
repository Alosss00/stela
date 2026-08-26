<?php
require_once dirname(__DIR__, 2) . '/bootstrap/app.php';
$page_title = 'Certification Management';
require_once dirname(__DIR__, 2) . '/app/Helpers/auth_helper.php';
// Included via bootstrap/app.php

// Pastikan ini ditaruh di baris paling awal sebelum ada output HTML/spasi
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Generate token CSRF jika belum ada di session
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

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

require_once dirname(__DIR__) . '/layouts/header.php';
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
                    <table class="table-cert datatable" style="width: 100% !important; table-layout: fixed !important;">
                        <thead>
                            <tr>
                                <th class="col-name" style="width: 75% !important; text-align: left !important;" data-lang="certification-name">Certification Name</th>
                                <th class="col-action" style="width: 25% !important; text-align: center !important;" data-lang="action">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $certifications->data_seek(0);
                            while ($row = $certifications->fetch_assoc()):
                            ?>
                            <tr class="cert-row">
                                <td class="col-name" style="width: 75% !important; text-align: left !important;" data-label="Certification Name">
                                    <strong><?php echo htmlspecialchars($row['cert_name']); ?></strong>
                                </td>
                                <td class="col-action" style="width: 25% !important; text-align: center !important;" data-label="Action">
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



<?php require_once dirname(__DIR__) . '/layouts/footer.php'; ?>
