<?php
require_once dirname(__DIR__, 2) . '/bootstrap/app.php';
$page_title = 'Competency Management';
$page_title_lang = 'competency-management';
require_once dirname(__DIR__, 2) . '/app/Helpers/auth_helper.php';
// Included via bootstrap/app.php
requirePermission('admin.access');

$db = new Database();
$message = '';
$error = '';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Check if competencies table exists - MOVED TO TOP
$competencies_table_exists = false;
$check_table = $db->query("SHOW TABLES LIKE 'competencies'");
if ($check_table && $check_table->num_rows > 0) {
    $competencies_table_exists = true;
}

// Check if competency_sub_competencies table exists
$sub_competencies_table_exists = false;
$check_sub_table = $db->query("SHOW TABLES LIKE 'competency_sub_competencies'");
if ($check_sub_table && $check_sub_table->num_rows > 0) {
    $sub_competencies_table_exists = true;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!verify_csrf_token()) {
        die("CSRF Token Invalid. Silakan muat ulang halaman.");
    }

    // CSRF token validation
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die('CSRF token mismatch');
    }
    if (isset($_POST['action'])) {
        if ($_POST['action'] == 'add') {
            $position_type = $db->escapeString($_POST['position_type']);
            $competency_name = $db->escapeString($_POST['competency_name']);
            
            // Check if competency already exists
            $check_comp = $db->query("SELECT id FROM competencies WHERE deleted_at IS NULL AND competency_name = '$competency_name' AND position_type = '$position_type'");
            if ($check_comp && $check_comp->num_rows > 0) {
                $error = stela_t('competency-name-already-exists');
            } else {
                $sql = "INSERT INTO competencies (competency_name, position_type) 
                        VALUES ('$competency_name', '$position_type')";
                
                if ($db->query($sql)) {
                    $competency_id = $db->lastInsertId();
                    
                    // Add sub competencies if it's tenaga_teknis type AND table exists
                    if ($sub_competencies_table_exists && $position_type === 'tenaga_teknis' && isset($_POST['sub_competency_names']) && is_array($_POST['sub_competency_names'])) {
                        $all_subs_added = true;
                        foreach ($_POST['sub_competency_names'] as $index => $sub_name) {
                            $sub_name = trim($sub_name);
                            if (!empty($sub_name)) {
                                $sub_name_escaped = $db->escapeString($sub_name);
                                $sub_sql = "INSERT INTO competency_sub_competencies 
                                           (competency_id, sub_competency_name, is_active) 
                                           VALUES ($competency_id, '$sub_name_escaped', 1)";
                                
                                if (!$db->query($sub_sql)) {
                                    $all_subs_added = false;
                                    break;
                                }
                            }
                        }
                        
                        if (!$all_subs_added) {
                            $error = stela_t('competency-added-sub-competencies-partial-failed');
                        } else {
                            $message = stela_t('competency-subcompetencies-added');
                        }
                    } else {
                        $message = stela_t('competency-added');
                    }
                } else {
                    $error = stela_t('failed-add-competency');
                }
            }
        } elseif ($_POST['action'] == 'edit') {
            $id = intval($_POST['id']);
            $position_type = $db->escapeString($_POST['position_type']);
            $competency_name = $db->escapeString($_POST['competency_name']);
            
            // Check if competency name already exists (except current record)
            $check_comp = $db->query("SELECT id FROM competencies WHERE deleted_at IS NULL AND competency_name = '$competency_name' AND position_type = '$position_type' AND id != $id");
            if ($check_comp && $check_comp->num_rows > 0) {
                $error = stela_t('competency-name-already-exists');
            } else {
                $sql = "UPDATE competencies SET 
                        competency_name = '$competency_name',
                        position_type = '$position_type'
                        WHERE id = $id";
                
                if ($db->query($sql)) {
                    // Handle sub competencies update for tenaga_teknis AND table exists
                    if ($_POST['action'] == 'edit' && $sub_competencies_table_exists && $position_type === 'tenaga_teknis') {
                        $new_sub_competencies = [];
                        if (isset($_POST['sub_competency_names']) && is_array($_POST['sub_competency_names'])) {
                            foreach ($_POST['sub_competency_names'] as $sub_name) {
                                $sub_name = trim($sub_name);
                                if (!empty($sub_name)) {
                                    $new_sub_competencies[] = $sub_name;
                                }
                            }
                        }

                        // Only replace existing rows when the user submitted at least one sub competency
                        if (!empty($new_sub_competencies)) {
                            $db->query("DELETE FROM competency_sub_competencies WHERE competency_id = $id");

                            foreach ($new_sub_competencies as $sub_name) {
                                $sub_name_escaped = $db->escapeString($sub_name);
                                $sub_sql = "INSERT INTO competency_sub_competencies 
                                           (competency_id, sub_competency_name, is_active) 
                                           VALUES ($id, '$sub_name_escaped', 1)";

                                $db->query($sub_sql);
                            }
                        }
                    }
                    $message = stela_t('competency-updated');
                } else {
                    $error = stela_t('failed-update-competency');
                }
            }
        }
    }
}

// Handle delete
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $db->query("START TRANSACTION");

    $positions_unlinked = $db->query("UPDATE positions SET competency_id = NULL WHERE competency_id = $id");
    $sub_competencies_deleted = $db->query("DELETE FROM competency_sub_competencies WHERE competency_id = $id");
    $competency_deleted = $db->query("DELETE FROM competencies WHERE deleted_at IS NULL AND id = ?", [$id]);

    if ($positions_unlinked && $sub_competencies_deleted && $competency_deleted) {
        $db->query("COMMIT");
        $message = stela_t('competency-deleted');
    } else {
        $db->query("ROLLBACK");
        $error = stela_t('failed-delete-competency', [], 'Failed to delete competency');
    }
}

// Get all competencies
$competencies = $db->query("SELECT * FROM competencies ORDER BY position_type, competency_name");

// Get sub competencies data grouped by competency_id (for JavaScript)
$sub_competencies_by_competency = [];
if ($competencies_table_exists && $sub_competencies_table_exists) {
    $competencies->data_seek(0);
    while ($comp = $competencies->fetch_assoc()) {
        $comp_id = $comp['id'];
        $subs = $db->query("SELECT id, sub_competency_name FROM competency_sub_competencies WHERE competency_id = $comp_id AND is_active = 1 ORDER BY id");
        if ($subs && $subs->num_rows > 0) {
            $sub_competencies_by_competency[$comp_id] = [];
            while ($sub = $subs->fetch_assoc()) {
                $sub_competencies_by_competency[$comp_id][] = $sub;
            }
        }
    }
}

require_once dirname(__DIR__) . '/layouts/header.php';
?>

<div class="positions-container">
    <!-- Page Header -->
    <div class="page-header-positions">
        <div class="header-left">
            <h2><i class="fas fa-star"></i> <span data-lang="competency-management">Competency Management</span></h2>
            <p data-lang="manage-organizational-competency-data">Manage organizational competency data</p>
        </div>
        <button class="btn btn-primary btn-lg-positions" onclick="openModal('addModal')">
            <i class="fas fa-plus-circle"></i> <span data-lang="add-competency">Add Competency</span>
        </button>
    </div>
    
    <?php if ($message): ?>
    <div class="alert alert-success alert-custom-positions">
        <i class="fas fa-check-circle"></i>
        <div>
            <strong data-lang="success">Success!</strong>
            <p><?php echo htmlspecialchars($message); ?></p>
        </div>
    </div>
    <?php endif; ?>
    
    <?php if ($error): ?>
    <div class="alert alert-error alert-custom-positions">
        <i class="fas fa-exclamation-circle"></i>
        <div>
            <strong data-lang="error">Error!</strong>
            <p><?php echo htmlspecialchars($error); ?></p>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Statistics Card -->
    <div class="stat-card-positions">
        <div class="stat-number"><?php echo $competencies->num_rows; ?></div>
        <div class="stat-label" data-lang="total-active-competencies">Total Active Competencies</div>
    </div>
    
    <!-- Competencies Table Card -->
    <div class="card card-positions">
        <div class="card-header-positions">
            <h3><i class="fas fa-list"></i> <span data-lang="competency-list">Competency List</span></h3>
        </div>
        <div class="card-body">
            <?php if ($competencies->num_rows > 0): ?>
                <div class="table-responsive">
                    <table class="table table-positions datatable" style="width: 100% !important; table-layout: fixed !important;">
                        <thead>
                            <tr>
                                <th class="col-name" style="width: 50% !important; text-align: left !important;" data-lang="competency">Competency</th>
                                <th class="col-type" style="width: 32% !important; text-align: left !important;" data-lang="type-label">Type</th>
                                <th class="col-action" style="width: 18% !important; text-align: center !important;" data-lang="actions">Actions</th>
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
                            <tr class="position-row">
                                <td class="col-name" style="width: 50% !important; text-align: left !important;">
                                    <strong><i class="fas fa-star"></i> <?php echo htmlspecialchars($row['competency_name']); ?></strong>
                                    <?php
                                    $saved_sub_competencies = $sub_competencies_by_competency[$row['id']] ?? [];
                                    if ($type_key === 'tenaga_teknis' && !empty($saved_sub_competencies)):
                                    ?>
                                        <div class="sub-competency-list">
                                            <?php foreach ($saved_sub_competencies as $sub_item): ?>
                                                <span class="sub-competency-chip">
                                                    <i class="fas fa-angle-right"></i>
                                                    <?php echo htmlspecialchars($sub_item['sub_competency_name']); ?>
                                                </span>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php elseif ($type_key === 'tenaga_teknis'): ?>
                                        <div class="sub-competency-empty" data-lang="no-sub-competencies">No sub competencies</div>
                                    <?php endif; ?>
                                </td>
                                <td class="col-type" style="width: 32% !important; text-align: left !important;">
                                    <span class="badge badge-position" style="color: #0f172a !important; font-weight: 700 !important;" data-lang="<?php echo 'competency-type-' . str_replace('_', '-', $type_key); ?>">
                                        <i class="fas <?php echo $icon; ?>" style="color: #0f172a !important;"></i> <?php echo $type_label; ?>
                                    </span>
                                </td>
                                <td class="col-action" style="width: 18% !important; text-align: center !important;">
                                    <div class="action-buttons-positions">
                                        <button onclick='editCompetency(<?php echo json_encode($row); ?>)' class="btn btn-sm btn-warning btn-action-positions" title="Edit" data-lang-title="edit">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <a href="?delete=<?php echo $row['id']; ?>" 
                                           class="btn btn-sm btn-danger btn-action-positions" 
                                           onclick="return confirm(window.getLanguageText('confirm-delete-competency'))" title="Delete" data-lang-title="delete">
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
                <div class="empty-state-positions">
                    <i class="fas fa-inbox"></i>
                    <p data-lang="no-competencies-yet">No competencies yet</p>
                    <button class="btn btn-primary" onclick="openModal('addModal')">
                        <i class="fas fa-plus"></i> <span data-lang="add-first-competency">Add First Competency</span>
                    </button>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Add Modal -->
<div id="addModal" class="modal">
    <div class="modal-content modal-positions">
        <div class="modal-header modal-header-positions">
            <div class="modal-title-wrapper">
                <div class="modal-icon">
                    <i class="fas fa-plus-circle"></i>
                </div>
                <h3 data-lang="add-new-competency">Add New Competency</h3>
            </div>
            <span class="close" onclick="closeModal('addModal')">&times;</span>
        </div>
            <form method="POST" action="">
    <?= csrf_field() ?>
            <input type="hidden" name="csrf_token" value="<?php echo isset($_SESSION['csrf_token']) ? htmlspecialchars($_SESSION['csrf_token']) : ''; ?>">
            
            <input type="hidden" name="action" value="add">
            <div class="modal-body modal-body-enhanced">
                <div class="form-group-enhanced">
                    <label for="position_type">
                        <i class="fas fa-briefcase label-icon"></i>
                        <span data-lang="competency-type-required">Competency Type *</span>
                    </label>
                    <div class="input-wrapper">
                        <select id="position_type" name="position_type" class="form-control form-control-enhanced" required onchange="toggleSubCompetencySection()">
                            <option value="" data-lang="select-competency-type">-- Select Competency Type --</option>
                            <option value="pengawas_operasional" data-lang="competency-type-operational-supervisor">Pengawas Operasional</option>
                            <option value="pengawas_teknis" data-lang="competency-type-technical-supervisor">Pengawas Teknis</option>
                            <option value="tenaga_teknis" data-lang="competency-type-technical-personnel">Tenaga Teknis</option>
                        </select>
                    </div>
                </div>
                <div class="form-group-enhanced">
                    <label for="competency_name">
                        <i class="fas fa-star label-icon"></i>
                        <span data-lang="competency-name">Competency Name</span>
                    </label>
                    <div class="input-wrapper">
                        <input type="text" id="competency_name" name="competency_name" class="form-control form-control-enhanced" required placeholder="e.g., Pengawasan Operasional Tambang" data-lang-placeholder="competency-name-example-mining-ops">
                    </div>
                </div>
                
                <div id="sub_competency_section" style="display: none;">
                    <hr style="margin: 20px 0; border: none; border-top: 2px solid #e8eaed;">
                    <div style="margin-bottom: 15px;">
                        <h4 style="margin: 0 0 10px 0; font-size: 14px; color: #2c3e50; display: flex; align-items: center; gap: 8px;">
                            <i class="fas fa-layer-group" style="color: #37474F"></i>
                            <span data-lang="sub-competencies">Sub Competencies</span>
                        </h4>
                        <p style="margin: 0; font-size: 12px; color: #666;" data-lang="add-one-or-more-sub-competencies">Add one or more sub competencies</p>
                    </div>
                    
                    <div id="sub_competency_container">
                        <div class="sub-competency-item" style="background: #f5f7fa; padding: 15px; border-radius: 6px; margin-bottom: 12px;">
                            <div style="margin-bottom: 10px;">
                                <label style="display: block; font-size: 12px; font-weight: 600; color: #555; margin-bottom: 5px;">
                                    <span data-lang="sub-competency">Sub Competency Name</span>
                                </label>
                                <input type="text" name="sub_competency_names[]" class="form-control form-control-enhanced" placeholder="e.g., Ahli Hygiene Industri Muda" data-lang-placeholder="sub-competency-example-industrial-hygiene">
                            </div>
                        </div>
                    </div>
                    
                    <button type="button" class="btn btn-secondary btn-add-sub" onclick="addSubCompetencyField()" style="margin-top: 10px; padding: 8px 16px; font-size: 13px;">
                        <i class="fas fa-plus"></i> <span data-lang="add-another-level">Add Another Level</span>
                    </button>
                </div>
            </div>
            <div class="modal-footer modal-footer-positions">
                <button type="button" class="btn btn-secondary btn-cancel" onclick="closeModal('addModal')">
                    <i class="fas fa-times"></i> <span data-lang="cancel">Cancel</span>
                </button>
                <button type="submit" class="btn btn-primary btn-save">
                    <i class="fas fa-check"></i> <span data-lang="save">Save</span>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Modal -->
<div id="editModal" class="modal">
    <div class="modal-content modal-positions">
        <div class="modal-header modal-header-positions">
            <div class="modal-title-wrapper">
                <div class="modal-icon">
                    <i class="fas fa-edit"></i>
                </div>
                <h3 data-lang="edit-competency">Edit Competency</h3>
            </div>
            <span class="close" onclick="closeModal('editModal')">&times;</span>
        </div>
            <form method="POST" action="">
    <?= csrf_field() ?>
            <input type="hidden" name="csrf_token" value="<?php echo isset($_SESSION['csrf_token']) ? htmlspecialchars($_SESSION['csrf_token']) : ''; ?>">
            
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="id" id="edit_id">
            <div class="modal-body modal-body-enhanced">
                <div class="form-group-enhanced">
                    <label for="edit_position_type">
                        <i class="fas fa-briefcase label-icon"></i>
                        <span data-lang="competency-type-required">Competency Type</span>
                    </label>
                    <div class="input-wrapper">
                        <select id="edit_position_type" name="position_type" class="form-control form-control-enhanced" required onchange="toggleEditSubCompetencySection()">
                            <option value="" data-lang="select-competency-type">-- Select Competency Type --</option>
                            <option value="pengawas_operasional" data-lang="competency-type-operational-supervisor">Pengawas Operasional</option>
                            <option value="pengawas_teknis" data-lang="competency-type-technical-supervisor">Pengawas Teknis</option>
                            <option value="tenaga_teknis" data-lang="competency-type-technical-personnel">Tenaga Teknis</option>
                        </select>
                    </div>
                </div>
                <div class="form-group-enhanced">
                    <label for="edit_competency_name">
                        <i class="fas fa-star label-icon"></i>
                        <span data-lang="competency-name">Competency Name</span>
                    </label>
                    <div class="input-wrapper">
                        <input type="text" id="edit_competency_name" name="competency_name" class="form-control form-control-enhanced" required>
                    </div>
                </div>
                
                <div id="edit_sub_competency_section" style="display: none;">
                    <hr style="margin: 20px 0; border: none; border-top: 2px solid #e8eaed;">
                    <div style="margin-bottom: 15px;">
                        <h4 style="margin: 0 0 10px 0; font-size: 14px; color: #2c3e50; display: flex; align-items: center; gap: 8px;">
                            <i class="fas fa-layer-group" style="color: #37474F;"></i>
                            <span data-lang="sub-competencies">Sub Competencies</span> <span class="text-danger">*</span>
                        </h4>
                        <p style="margin: 0; font-size: 12px; color: #666;" data-lang="add-or-update-sub-competency-levels">Add or update sub competency levels</p>
                    </div>
                    
                    <div id="edit_sub_competency_container">
                        </div>
                    
                    <button type="button" class="btn btn-secondary btn-add-sub" onclick="addEditSubCompetencyField()" style="margin-top: 10px; padding: 8px 16px; font-size: 13px;">
                        <i class="fas fa-plus"></i> <span data-lang="add-another-level">Add Another Level</span>
                    </button>
                </div>
            </div>
            <div class="modal-footer modal-footer-positions">
                <button type="button" class="btn btn-secondary btn-cancel" onclick="closeModal('editModal')">
                    <i class="fas fa-times"></i> <span data-lang="cancel">Cancel</span>
                </button>
                <button type="submit" class="btn btn-primary btn-save">
                    <i class="fas fa-check"></i> <span data-lang="update">Update</span>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
const subCompetenciesByCompetency = <?php echo json_encode($sub_competencies_by_competency); ?>;

function toggleSubCompetencySection() {
    const positionType = document.getElementById('position_type').value;
    const section = document.getElementById('sub_competency_section');
    
    if (positionType === 'tenaga_teknis') {
        section.style.display = 'block';
    } else {
        section.style.display = 'none';
    }
}

function toggleEditSubCompetencySection() {
    const positionType = document.getElementById('edit_position_type').value;
    const section = document.getElementById('edit_sub_competency_section');
    
    if (positionType === 'tenaga_teknis') {
        section.style.display = 'block';
    } else {
        section.style.display = 'none';
    }
}

function addSubCompetencyField() {
    const container = document.getElementById('sub_competency_container');
    const newItem = document.createElement('div');
    newItem.className = 'sub-competency-item';
    newItem.style.cssText = 'background: #f5f7fa; padding: 15px; border-radius: 6px; margin-bottom: 12px;';
    
    newItem.innerHTML = `
        <div style="margin-bottom: 10px;">
            <label style="display: block; font-size: 12px; font-weight: 600; color: #555; margin-bottom: 5px;">
                <span data-lang="sub-competency">Sub Competency Name</span>
            </label>
            <input type="text" name="sub_competency_names[]" class="form-control form-control-enhanced" placeholder="e.g., Ahli Hygiene Industri Muda" data-lang-placeholder="sub-competency-example-industrial-hygiene">
        </div>
        <button type="button" onclick="removeSubCompetencyField(event)" class="btn btn-danger btn-sm" style="margin-top: 8px; padding: 6px 12px; font-size: 12px;">
            <i class="fas fa-trash"></i> <span data-lang="remove">Remove</span>
        </button>
    `;
    
    container.appendChild(newItem);
}

function removeSubCompetencyField(event) {
    event.preventDefault();
    event.target.closest('.sub-competency-item').remove();
}

function addEditSubCompetencyField() {
    const container = document.getElementById('edit_sub_competency_container');
    const newItem = document.createElement('div');
    newItem.className = 'sub-competency-item';
    newItem.style.cssText = 'background: #f5f7fa; padding: 15px; border-radius: 6px; margin-bottom: 12px;';
    
    newItem.innerHTML = `
        <div style="margin-bottom: 10px;">
            <label style="display: block; font-size: 12px; font-weight: 600; color: #555; margin-bottom: 5px;">
                <span data-lang="sub-competency">Sub Competency Name</span>
            </label>
            <input type="text" name="sub_competency_names[]" class="form-control form-control-enhanced" placeholder="e.g., Ahli Hygiene Industri Muda" data-lang-placeholder="sub-competency-example-industrial-hygiene">
        </div>
        <button type="button" onclick="removeSubCompetencyField(event)" class="btn btn-danger btn-sm" style="margin-top: 8px; padding: 6px 12px; font-size: 12px;">
            <i class="fas fa-trash"></i> <span data-lang="remove">Remove</span>
        </button>
    `;
    
    container.appendChild(newItem);
}

function editCompetency(data) {
    document.getElementById('edit_id').value = data.id;
    document.getElementById('edit_position_type').value = data.position_type;
    document.getElementById('edit_competency_name').value = data.competency_name;
    
    // Populate sub competencies if tenaga_teknis
    const editSubContainer = document.getElementById('edit_sub_competency_container');
    editSubContainer.innerHTML = '';
    
    if (data.position_type === 'tenaga_teknis' && subCompetenciesByCompetency[data.id]) {
        subCompetenciesByCompetency[data.id].forEach(sub => {
            const item = document.createElement('div');
            item.className = 'sub-competency-item';
            item.style.cssText = 'background: #f5f7fa; padding: 15px; border-radius: 6px; margin-bottom: 12px;';
            
                item.innerHTML = `
                <div style="margin-bottom: 10px;">
                    <label style="display: block; font-size: 12px; font-weight: 600; color: #555; margin-bottom: 5px;">
                        <span data-lang="sub-competency">Sub Competency Name</span>
                    </label>
                    <input type="text" name="sub_competency_names[]" class="form-control form-control-enhanced" value="${sub.sub_competency_name}">
                </div>
                <button type="button" onclick="removeSubCompetencyField(event)" class="btn btn-danger btn-sm" style="margin-top: 8px; padding: 6px 12px; font-size: 12px;">
                    <i class="fas fa-trash"></i> <span data-lang="remove">Remove</span>
                </button>
            `;
            
            editSubContainer.appendChild(item);
        });
    }
    
    // Toggle section visibility
    toggleEditSubCompetencySection();
    
    openModal('editModal');
}
</script>



<?php require_once dirname(__DIR__) . '/layouts/footer.php'; ?>
