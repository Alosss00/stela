<?php
$page_title = 'Competency Management';
$page_title_lang = 'competency-management';
require_once '../../includes/auth.php';
require_once '../../includes/db.php';
require_once '../../includes/i18n.php';

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
    // CSRF token validation
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die('CSRF token mismatch');
    }
    if (isset($_POST['action'])) {
        if ($_POST['action'] == 'add') {
            $position_type = $db->escapeString($_POST['position_type']);
            $competency_name = $db->escapeString($_POST['competency_name']);
            
            // Check if competency already exists
            $check_comp = $db->query("SELECT id FROM competencies WHERE competency_name = '$competency_name' AND position_type = '$position_type'");
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
            $check_comp = $db->query("SELECT id FROM competencies WHERE competency_name = '$competency_name' AND position_type = '$position_type' AND id != $id");
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
    $competency_deleted = $db->query("DELETE FROM competencies WHERE id = $id");

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

require_once '../../includes/header.php';
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
                    <table class="table table-positions">
                        <thead>
                            <tr>
                                <th class="col-name" data-lang="competency">Competency</th>
                                <th class="col-type" data-lang="type-label">Type</th>
                                <th class="col-action" data-lang="actions">Actions</th>
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
                                <td class="col-name">
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
                                <td class="col-type">
                                    <span class="badge badge-position" data-lang="<?php echo 'competency-type-' . str_replace('_', '-', $type_key); ?>">
                                        <i class="fas <?php echo $icon; ?>"></i> <?php echo $type_label; ?>
                                    </span>
                                </td>
                                <td class="col-action">
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
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="id" id="edit_id">
            <div class="modal-body modal-body-enhanced">
                <div class="form-group-enhanced">
                    <label for="edit_position_type">
                        <i class="fas fa-briefcase label-icon"></i>
                        <span data-lang="competency-type-required">Competency Type *</span>
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
                
                <!-- Sub Competency Section for Edit - Only for Tenaga Teknis -->
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
                        <!-- Will be populated by JavaScript -->
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

<style>
.positions-container {
    padding: 20px 0;
}

/* Page Header */
.page-header-positions {
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

.btn-lg-positions {
    padding: 12px 25px;
    font-size: 15px;
    white-space: nowrap;
    background: #37474F;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
}

.btn-lg-positions:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 15px rgba(0, 0, 0, 0.3);
}

/* Alert Custom */
.alert-custom-positions {
    display: flex;
    gap: 15px;
    align-items: flex-start;
    padding: 20px;
    margin-bottom: 30px;
    border-radius: 8px;
    border-left: 4px solid;
}

.alert-success.alert-custom-positions {
    background: #ECEFF1;
    border-left-color: #37474F;
}

.alert-success.alert-custom-positions i {
    color: #37474F;
    font-size: 20px;
}

.alert-error.alert-custom-positions {
    background: #fee2e2;
    border-left-color: #ef4444;
}

.alert-error.alert-custom-positions i {
    color: #ef4444;
    font-size: 20px;
}

.alert-warning.alert-custom-positions {
    background: #ECEFF1;
    border-left-color: #37474F;
}

.alert-warning.alert-custom-positions i {
    color: #37474F;
    font-size: 20px;
}

.alert-warning.alert-custom-positions pre {
    font-size: 11px;
    color: #333;
}

.alert-custom-positions strong {
    display: block;
    margin-bottom: 5px;
}

.alert-custom-positions p {
    margin: 0;
}

/* Stat Card */
.stat-card-positions {
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
.card-positions {
    background: white;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
    margin-bottom: 25px;
}

.card-header-positions {
    padding: 20px;
    border-bottom: 2px solid #f0f0f0;
}

.card-header-positions h3 {
    margin: 0;
    font-size: 18px;
    color: #333;
    display: flex;
    align-items: center;
    gap: 10px;
}

.card-header-positions i {
    color: #37474F;
}

/* Table */
.table-positions {
    margin: 0;
}

.table-positions thead th {
    background: #37474F;
    color: white;
    font-weight: 600;
    font-size: 12px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    border: none;
    padding: 15px;
}

.position-row {
    border-bottom: 1px solid #f0f0f0;
    transition: background-color 0.2s ease;
}

.position-row:hover {
    background-color: #e8f7fa;
}

.table-positions td {
    padding: 15px;
    vertical-align: middle;
}

.col-name { width: 50%; }
.col-type { width: 30%; }
.col-action { width: 20%; }

.badge-position {
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

.badge-competency {
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

.description-text {
    color: #666;
    font-size: 12px;
}

.sub-competency-list {
    margin-top: 8px;
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
}

.sub-competency-chip {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    background: #f1f5f9;
    color: #334155;
    border: 1px solid #cbd5e1;
    border-radius: 999px;
    padding: 3px 10px;
    font-size: 11px;
    font-weight: 600;
}

.sub-competency-empty {
    margin-top: 8px;
    color: #64748b;
    font-size: 11px;
    font-style: italic;
}

.action-buttons-positions {
    display: flex;
    gap: 5px;
}

.btn-action-positions {
    padding: 6px 10px;
    font-size: 12px;
}

.btn-action-positions:hover {
    transform: translateY(-1px);
}

.btn-action-positions.btn-warning {
    background-color: #ffc107;
    border-color: #ffc107;
    color: #000;
}

.btn-action-positions.btn-warning:hover {
    background-color: #e0a800;
    border-color: #d39e00;
}

.btn-action-positions.btn-danger {
    background-color: #dc3545;
    border-color: #dc3545;
    color: white;
}

.btn-action-positions.btn-danger:hover {
    background-color: #c82333;
    border-color: #bd2130;
}

/* Empty State */
.empty-state-positions {
    text-align: center;
    padding: 60px 20px;
    color: #999;
}

.empty-state-positions i {
    font-size: 48px;
    margin-bottom: 15px;
    opacity: 0.5;
    color: #37474F;
}

.empty-state-positions p {
    margin: 15px 0;
    font-size: 16px;
}

/* Modal */
.modal-positions {
    max-width: 520px;
    animation: modalSlideIn 0.3s ease-out;
}

@keyframes modalSlideIn {
    from {
        opacity: 0;
        transform: translateY(-20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.modal-header-positions {
    background: #37474F;
    color: white;
    padding: 25px 25px;
    border-radius: 8px 8px 0 0;
    position: relative;
}

.modal-title-wrapper {
    display: flex;
    align-items: center;
    gap: 15px;
}

.modal-icon {
    width: 45px;
    height: 45px;
    background: rgba(255, 255, 255, 0.2);
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
}

.modal-header-positions h3 {
    margin: 0;
    font-size: 19px;
    font-weight: 600;
}

.modal-header-positions .close {
    color: white;
    opacity: 0.9;
    font-size: 28px;
    transition: all 0.2s ease;
}

.modal-header-positions .close:hover {
    opacity: 1;
    transform: rotate(90deg);
}

.modal-body-enhanced {
    padding: 25px;
    background: #fafbfc;
}

.modal-footer-positions {
    display: flex;
    gap: 10px;
    justify-content: flex-end;
    padding: 20px 25px;
    background: white;
    border-top: 1px solid #e8eaed;
    border-radius: 0 0 8px 8px;
}

.btn-cancel, .btn-save {
    padding: 10px 20px;
    font-weight: 500;
    font-size: 14px;
    border-radius: 6px;
    transition: all 0.2s ease;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.btn-cancel {
    background: #f0f0f0;
    color: #666;
    border: 1px solid #ddd;
}

.btn-cancel:hover {
    background: #e0e0e0;
    color: #333;
    transform: translateY(-1px);
}

.btn-save {
    background: #37474F;
    color: white;
    border: none;
    box-shadow: 0 2px 8px rgba(55, 71, 79, 0.3);
}

.btn-save:hover {
    box-shadow: 0 4px 12px rgba(23, 162, 184, 0.4);
    transform: translateY(-2px);
}

/* Form Enhanced */
.form-group-enhanced {
    margin-bottom: 20px;
}

.form-group-enhanced label {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 8px;
    font-weight: 600;
    color: #2c3e50;
    font-size: 13px;
}

.label-icon {
    color: #37474F;
    font-size: 14px;
}

.input-wrapper {
    position: relative;
}

.form-control-enhanced {
    width: 100%;
    padding: 12px 15px;
    border: 2px solid #e8eaed;
    border-radius: 8px;
    font-size: 14px;
    transition: all 0.3s ease;
    background: white;
}

.form-control-enhanced:focus {
    outline: none;
    border-color: #37474F;
    box-shadow: 0 0 0 3px rgba(23, 162, 184, 0.1);
    background: white;
}

.form-control-enhanced::placeholder {
    color: #adb5bd;
    font-style: italic;
}

select.form-control-enhanced {
    cursor: pointer;
    appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%2317a2b8' d='M6 9L1 4h10z'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 15px center;
    padding-right: 40px;
}

textarea.form-control-enhanced {
    resize: vertical;
    min-height: 80px;
    font-family: inherit;
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

.competency-input-wrapper {
    position: relative;
}

.competency-input-wrapper .form-control {
    width: 100%;
}

.competency-suggestions {
    position: absolute;
    top: 100%;
    left: 0;
    right: 0;
    background: white;
    border: 1px solid #ddd;
    border-top: none;
    border-radius: 0 0 6px 6px;
    max-height: 250px;
    overflow-y: auto;
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
    z-index: 1000;
}

.suggestion-item {
    padding: 10px 15px;
    cursor: pointer;
    border-bottom: 1px solid #f0f0f0;
    display: flex;
    align-items: center;
    gap: 8px;
    transition: background-color 0.2s ease;
    font-size: 13px;
}

.suggestion-item:hover {
    background-color: #e8f7fa;
    color: #37474F;
}

.suggestion-item:last-child {
    border-bottom: none;
}

.suggestion-item i {
    color: #37474F;
    font-size: 12px;
}

.form-group {
    position: relative;
}

/* Sub Competency Button Styles */
.btn-add-sub {
    padding: 8px 16px;
    font-size: 13px;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: #37474F;
    color: white;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    transition: all 0.2s ease;
}

.btn-add-sub:hover {
    background: #263238;
    transform: translateY(-1px);
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.2);
}

.btn-sm {
    padding: 6px 12px;
    font-size: 12px;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    transition: all 0.2s ease;
}

.btn-danger {
    background: #dc3545;
    color: white;
}

.btn-danger:hover {
    background: #c82333;
    transform: translateY(-1px);
    box-shadow: 0 2px 6px rgba(220, 53, 69, 0.3);
}

/*** Responsive ***/
@media (max-width: 1024px) {
    .page-header-positions {
        flex-direction: column;
        gap: 20px;
        text-align: center;
    }
}

@media (max-width: 768px) {
    .page-header-positions {
        padding: 25px 15px;
    }
    
    .header-left h2 {
        font-size: 20px;
    }
    
    .col-type { display: none; }
    .col-competency { display: none; }
    .col-name { width: 60%; }
    
    .table-responsive {
        font-size: 12px;
    }
}
</style>

<?php require_once '../../includes/footer.php'; ?>




