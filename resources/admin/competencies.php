<?php
require_once dirname(__DIR__, 2) . '/bootstrap/app.php';
$page_title = 'Competency Management';
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

// Check if competencies table exists
$competencies_table_exists = false;
$check_table = $db->query("SHOW TABLES LIKE 'competencies'");
if ($check_table && $check_table->num_rows > 0) {
    $competencies_table_exists = true;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && $competencies_table_exists) {
    // --- IMPLEMENTASI ANTI-CSRF ---
    // Memvalidasi token token CSRF dari POST request menggunakan hash_equals
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        http_response_code(403);
    }
    if (isset($_POST['action'])) {
        if ($_POST['action'] == 'add') {
            $competency_name = $db->escapeString($_POST['competency_name']);
            $position_type = $db->escapeString($_POST['position_type']);
            $description = $db->escapeString($_POST['description']);
            
            $sql = "INSERT INTO competencies (competency_name, position_type, description) 
                    VALUES ('$competency_name', '$position_type', '$description')";
            
            if ($db->query($sql)) {
                $message = stela_t('competency-added');
            } else {
                $error = stela_t('failed-add-competency');
            }
        } elseif ($_POST['action'] == 'edit') {
            $id = intval($_POST['id']);
            $competency_name = $db->escapeString($_POST['competency_name']);
            $position_type = $db->escapeString($_POST['position_type']);
            $description = $db->escapeString($_POST['description']);
            
            $sql = "UPDATE competencies SET 
                    competency_name = '$competency_name',
                    position_type = '$position_type',
                    description = '$description'
                    WHERE id = $id";
            
            if ($db->query($sql)) {
                $message = stela_t('competency-updated');
            } else {
                $error = stela_t('failed-update-competency');
            }
        }
    }
}

// Handle delete
if (isset($_GET['delete']) && $competencies_table_exists) {
    $id = intval($_GET['delete']);
    if ($db->query("UPDATE competencies SET is_active = 0 WHERE id = $id")) {
        $message = stela_t('competency-deleted');
    }
}

// Get all competencies
$competencies = null;
if ($competencies_table_exists) {
    $competencies = $db->query("SELECT * FROM competencies WHERE deleted_at IS NULL AND is_active = 1 ORDER BY position_type, competency_name");
}

require_once dirname(__DIR__) . '/layouts/header.php';
?>

<div class="competencies-container">
    <!-- Page Header -->
    <div class="page-header-competencies">
        <div class="header-left">
            <h2><i class="fas fa-tasks"></i> Competency Management</h2>
            <p>Manage organization competency data</p>
        </div>
        <?php if ($competencies_table_exists): ?>
        <button class="btn btn-primary btn-lg-competencies" onclick="openModal('addModal')">
            <i class="fas fa-plus-circle"></i> Add Competency
        </button>
        <?php endif; ?>
    </div>
    
    <?php if ($message): ?>
    <div class="alert alert-success alert-custom-competencies">
        <i class="fas fa-check-circle"></i>
        <div>
            <strong>Success!</strong>
            <p><?php echo htmlspecialchars($message); ?></p>
        </div>
    </div>
    <?php endif; ?>
    
    <?php if ($error): ?>
    <div class="alert alert-error alert-custom-competencies">
        <i class="fas fa-exclamation-circle"></i>
        <div>
            <strong>Error!</strong>
            <p><?php echo htmlspecialchars($error); ?></p>
        </div>
    </div>
    <?php endif; ?>
    
    <?php if (!$competencies_table_exists): ?>
    <div class="alert alert-warning alert-custom-competencies">
        <i class="fas fa-exclamation-triangle"></i>
        <div>
            <strong>Attention!</strong>
            <p>The competency table has not been created yet. Run the SQL below to create the table:</p>
            <pre style="background: #F9FAFB; padding: 10px; border-radius: 5px; overflow-x: auto; font-size: 11px;">
    
    <!-- Statistics Card -->
    <div class="stat-card-competencies">
        <div class="stat-number"><?php echo $competencies->num_rows; ?></div>
        <div class="stat-label">Total Active Competencies</div>
    </div>
    
    <!-- Competencies Table Card -->
    <div class="card card-competencies">
        <div class="card-header-competencies">
            <h3><i class="fas fa-list"></i> Competency List</h3>
        </div>
        <div class="card-body">
            <?php if ($competencies->num_rows > 0): ?>
                <div class="table-responsive">
                    <table class="table table-competencies datatable">
                        <thead>
                            <tr>
                                <th class="col-name">Competency Name</th>
                                <th class="col-type">Position Type</th>
                                <th class="col-desc">Description</th>
                                <th class="col-action">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $position_type_labels = [
                                'pengawas_operasional' => 'Pengawas Operasional',
                                'pengawas_teknis' => 'Pengawas Teknis',
                                'tenaga_teknis' => 'Tenaga Teknis'
                            ];
                            
                            $competencies->data_seek(0);
                            while ($row = $competencies->fetch_assoc()): 
                                $type_key = $row['position_type'];
                                $type_label = $position_type_labels[$type_key] ?? $type_key;
                                $type_icons = [
                                    'pengawas_operasional' => 'fa-user-tie',
                                    'pengawas_teknis' => 'fa-helmet-safety',
                                    'tenaga_teknis' => 'fa-user-hard-hat'
                                ];
                                $icon = $type_icons[$type_key] ?? 'fa-briefcase';
                            ?>
                            <tr class="competency-row">
                                <td class="col-name">
                                    <strong><?php echo htmlspecialchars($row['competency_name']); ?></strong>
                                </td>
                                <td class="col-type">
                                    <span class="badge badge-type">
                                        <i class="fas <?php echo $icon; ?>"></i> <?php echo $type_label; ?>
                                    </span>
                                </td>
                                <td class="col-desc">
                                    <span class="description-text">
                                        <?php echo htmlspecialchars($row['description'] ?? '-'); ?>
                                    </span>
                                </td>
                                <td class="col-action">
                                    <div class="action-buttons-competencies">
                                        <button onclick='editCompetency(<?php echo json_encode($row); ?>)' class="btn btn-sm btn-warning btn-action-competencies" title="Edit" data-lang-title="edit">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <a href="?delete=<?php echo $row['id']; ?>" 
                                           class="btn btn-sm btn-danger btn-action-competencies" 
                                           onclick="return confirm(window.getLanguageText(''))" title="Delete" data-lang-title="delete">
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
                                <div class="empty-state-competencies">
                    <i class="fas fa-inbox"></i>
                    <p>No competencies yet</p>
                    <button class="btn btn-primary" onclick="openModal('addModal')">
                        <i class="fas fa-plus"></i> Add First Competency
                    </button>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <?php endif; ?>
</div>

<!-- Add Modal -->
<div id="addModal" class="modal">
    <div class="modal-content modal-competencies">
        <div class="modal-header modal-header-competencies">
            <h3><i class="fas fa-plus-circle"></i> Add New Competency</h3>
            <span class="close" onclick="closeModal('addModal')">&times;</span>
        </div>
        <form method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
            <input type="hidden" name="action" value="add">
            <div class="modal-body">
                <div class="form-group">
                    <label for="position_type">Position Type <span class="text-danger">*</span></label>
                    <select id="position_type" name="position_type" class="form-control" required>
                        <option value="">-- Select Position Type --</option>
                        <option value="pengawas_operasional">Pengawas Operasional</option>
                        <option value="pengawas_teknis">Pengawas Teknis</option>
                        <option value="tenaga_teknis">Tenaga Teknis</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="competency_name">Competency Name <span class="text-danger">*</span></label>
                    <input type="text" id="competency_name" name="competency_name" class="form-control" required placeholder="Contoh: Kepemimpinan" data-lang-placeholder="leadership-example-placeholder">
                </div>
                <div class="form-group">
                    <label for="description">Description</label>
                    <textarea id="description" name="description" class="form-control" rows="3" placeholder="Describe this competency..." data-lang-placeholder="describe-this-competency"></textarea>
                </div>
            </div>
            <div class="modal-footer modal-footer-competencies">
                <button type="button" class="btn btn-secondary" onclick="closeModal('addModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Modal -->
<div id="editModal" class="modal">
    <div class="modal-content modal-competencies">
        <div class="modal-header modal-header-competencies">
            <h3><i class="fas fa-edit"></i> Edit Competency</h3>
            <span class="close" onclick="closeModal('editModal')">&times;</span>
        </div>
        <form method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="id" id="edit_id">
            <div class="modal-body">
                <div class="form-group">
                    <label for="edit_position_type">Position Type <span class="text-danger">*</span></label>
                    <select id="edit_position_type" name="position_type" class="form-control" required>
                        <option value="">-- Select Position Type --</option>
                        <option value="pengawas_operasional">Pengawas Operasional</option>
                        <option value="pengawas_teknis">Pengawas Teknis</option>
                        <option value="tenaga_teknis">Tenaga Teknis</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="edit_competency_name">Competency Name <span class="text-danger">*</span></label>
                    <input type="text" id="edit_competency_name" name="competency_name" class="form-control" required>
                </div>
                <div class="form-group">
                    <label for="edit_description">Description</label>
                    <textarea id="edit_description" name="description" class="form-control" rows="3"></textarea>
                </div>
            </div>
            <div class="modal-footer modal-footer-competencies">
                <button type="button" class="btn btn-secondary" onclick="closeModal('editModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save</button>
            </div>
        </form>
    </div>
</div>

<script>
function editCompetency(data) {
    document.getElementById('edit_id').value = data.id;
    document.getElementById('edit_position_type').value = data.position_type;
    document.getElementById('edit_competency_name').value = data.competency_name;
    document.getElementById('edit_description').value = data.description;
    openModal('editModal');
}
</script>



<?php require_once dirname(__DIR__) . '/layouts/footer.php'; ?>
