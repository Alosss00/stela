<?php
$page_title = 'Competency Management';
require_once '../../includes/auth.php';
require_once '../../includes/db.php';

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
    $competencies = $db->query("SELECT * FROM competencies WHERE is_active = 1 ORDER BY position_type, competency_name");
}

require_once '../../includes/header.php';
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
CREATE TABLE competencies (
    id INT PRIMARY KEY AUTO_INCREMENT,
    competency_name VARCHAR(255) NOT NULL,
    position_type VARCHAR(50) NOT NULL,
    description TEXT,
    is_active TINYINT DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

ALTER TABLE positions ADD COLUMN competency_id INT NULL AFTER position_type;
ALTER TABLE positions ADD FOREIGN KEY (competency_id) REFERENCES competencies(id);</pre>
        </div>
    </div>
    <?php else: ?>
    
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
                    <table class="table table-competencies">
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

<style>
.competencies-container {
    padding: 20px 0;
}

/* Page Header */
.page-header-competencies {
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

.btn-lg-competencies {
    padding: 12px 25px;
    font-size: 15px;
    white-space: nowrap;
    background: #37474F;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
}

.btn-lg-competencies:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 15px rgba(0, 0, 0, 0.3);
}

/* Alert Custom */
.alert-custom-competencies {
    display: flex;
    gap: 15px;
    align-items: flex-start;
    padding: 20px;
    margin-bottom: 30px;
    border-radius: 8px;
    border-left: 4px solid;
}

.alert-success.alert-custom-competencies {
    background: #E8F5E9;
    border-left-color: #2E7D32;
}

.alert-success.alert-custom-competencies i {
    color: #2E7D32;
    font-size: 20px;
}

.alert-error.alert-custom-competencies {
    background: #fee2e2;
    border-left-color: #ef4444;
}

.alert-error.alert-custom-competencies i {
    color: #ef4444;
    font-size: 20px;
}

.alert-warning.alert-custom-competencies {
    background: #fef3c7;
    border-left-color: #f59e0b;
}

.alert-warning.alert-custom-competencies i {
    color: #f59e0b;
    font-size: 20px;
}

.alert-warning.alert-custom-competencies pre {
    font-size: 10px;
    color: #333;
    margin-top: 10px;
}

.alert-custom-competencies strong {
    display: block;
    margin-bottom: 5px;
}

.alert-custom-competencies p {
    margin: 0;
}

/* Stat Card */
.stat-card-competencies {
    background: white;
    border-radius: 8px;
    padding: 20px;
    margin-bottom: 25px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
    border-left: 4px solid #f59e0b;
    display: flex;
    align-items: center;
    gap: 15px;
}

.stat-number {
    font-size: 32px;
    font-weight: 700;
    color: #f59e0b;
}

.stat-label {
    color: #666;
    font-size: 14px;
}

/* Card */
.card-competencies {
    background: white;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
    margin-bottom: 25px;
}

.card-header-competencies {
    padding: 20px;
    border-bottom: 2px solid #f0f0f0;
}

.card-header-competencies h3 {
    margin: 0;
    font-size: 18px;
    color: #333;
    display: flex;
    align-items: center;
    gap: 10px;
}

.card-header-competencies i {
    color: #f59e0b;
}

/* Table */
.table-competencies {
    margin: 0;
}

.table-competencies thead th {
    background: #f8f9fa;
    color: #333;
    font-weight: 600;
    font-size: 12px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    border: none;
    padding: 15px;
}

.competency-row {
    border-bottom: 1px solid #f0f0f0;
    transition: background-color 0.2s ease;
}

.competency-row:hover {
    background-color: #fffbf0;
}

.table-competencies td {
    padding: 15px;
    vertical-align: middle;
}

.col-name { width: 25%; }
.col-type { width: 25%; }
.col-desc { width: 35%; }
.col-action { width: 15%; }

.badge-type {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: #fef3c7;
    color: #d97706;
    padding: 6px 12px;
    border-radius: 6px;
    font-size: 11px;
    font-weight: 600;
}

.description-text {
    color: #666;
    font-size: 12px;
}

.action-buttons-competencies {
    display: flex;
    gap: 5px;
}

.btn-action-competencies {
    padding: 6px 10px;
    font-size: 12px;
}

.btn-action-competencies:hover {
    transform: translateY(-1px);
}

/* Empty State */
.empty-state-competencies {
    text-align: center;
    padding: 60px 20px;
    color: #999;
}

.empty-state-competencies i {
    font-size: 48px;
    margin-bottom: 15px;
    opacity: 0.5;
}

.empty-state-competencies p {
    margin: 15px 0;
    font-size: 16px;
}

/* Modal */
.modal-competencies {
    max-width: 500px;
}

.modal-header-competencies {
    background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
    color: white;
    padding: 20px;
    border-radius: 8px 8px 0 0;
}

.modal-header-competencies h3 {
    margin: 0;
    font-size: 18px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.modal-header-competencies .close {
    color: white;
    opacity: 0.8;
}

.modal-header-competencies .close:hover {
    opacity: 1;
}

.modal-footer-competencies {
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

/* Responsive */
@media (max-width: 1024px) {
    .page-header-competencies {
        flex-direction: column;
        gap: 20px;
        text-align: center;
    }
    
    .col-desc { display: none; }
}

@media (max-width: 768px) {
    .page-header-competencies {
        padding: 25px 15px;
    }
    
    .header-left h2 {
        font-size: 20px;
    }
    
    .col-type { display: none; }
    .col-name { width: 60%; }
    
    .table-responsive {
        font-size: 12px;
    }
}
</style>

<?php require_once '../../includes/footer.php'; ?>




