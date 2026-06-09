<?php
$page_title = 'Manage Supervision Areas';
$page_title_lang = 'manage-supervision-areas';
require_once '../../includes/auth.php';
require_once '../../includes/db.php';

// Only ADMIN can access this page
checkPageAccess(['admin']);

$db = new Database();
$message = '';
$error = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];
        
        // ADD NEW AREA
        if ($action == 'add') {
            $area_name = $db->escapeString(trim($_POST['area_name']));
            $area_code = !empty($_POST['area_code']) ? $db->escapeString(trim($_POST['area_code'])) : null;
            $description = !empty($_POST['description']) ? $db->escapeString(trim($_POST['description'])) : null;
            
            if (empty($area_name)) {
                $error = 'area-name-required';
            } else {
                // Check if area name already exists
                $check = $db->query("SELECT id FROM supervision_areas WHERE area_name = '$area_name'");
                if ($check && $check->num_rows > 0) {
                    $error = 'area-name-already-exists';
                } else {
                    $sql = "INSERT INTO supervision_areas (area_name, area_code, description, is_active) 
                            VALUES ('$area_name', " . ($area_code ? "'$area_code'" : "NULL") . ", " . ($description ? "'$description'" : "NULL") . ", 1)";
                    
                    if ($db->query($sql)) {
                        $message = 'Supervision Area Added';
                    } else {
                        $error = 'Failed to add supervision area';
                    }
                }
            }
        }
        
        // EDIT AREA
        elseif ($action == 'edit') {
            $id = intval($_POST['id']);
            $area_name = $db->escapeString(trim($_POST['area_name']));
            $area_code = !empty($_POST['area_code']) ? $db->escapeString(trim($_POST['area_code'])) : null;
            $description = !empty($_POST['description']) ? $db->escapeString(trim($_POST['description'])) : null;
            
            if (empty($area_name)) {
                $error = 'Area Name Required';
            } else {
                // Check if area name already exists (except current record)
                $check = $db->query("SELECT id FROM supervision_areas WHERE area_name = '$area_name' AND id != $id");
                if ($check && $check->num_rows > 0) {
                    $error = 'area-name-already-exists';
                } else {
                    $sql = "UPDATE supervision_areas 
                            SET area_name = '$area_name', 
                                area_code = " . ($area_code ? "'$area_code'" : "NULL") . ", 
                                description = " . ($description ? "'$description'" : "NULL") . "
                            WHERE id = $id";
                    
                    if ($db->query($sql)) {
                        $message = 'supervision-area-updated';
                    } else {
                        $error = 'Failed to update supervision area';
                    }
                }
            }
        }
        
        // TOGGLE STATUS
        elseif ($action == 'toggle_status') {
            $id = intval($_POST['id']);
            $current_status = intval($_POST['current_status']);
            $new_status = $current_status == 1 ? 0 : 1;
            
            $sql = "UPDATE supervision_areas SET is_active = $new_status WHERE id = $id";
            
            if ($db->query($sql)) {
                $message = 'Status Updated';
            } else {
                $error = 'Failed to update status';
            }
        }
        
        // DELETE AREA
        elseif ($action == 'delete') {
            $id = intval($_POST['id']);
            
            // Check if area is being used in employees table
            $check_usage = $db->query("
                SELECT COUNT(*) as count 
                FROM employees 
                WHERE supervision_area IN (
                    SELECT area_name FROM supervision_areas WHERE id = $id
                ) AND is_active = 1
            ");
            
            if ($check_usage) {
                $usage = $check_usage->fetch_assoc();
                if ($usage['count'] > 0) {
                    $error = "Cannot delete! This area is being used by {$usage['count']} active employee(s).";
                } else {
                    $sql = "DELETE FROM supervision_areas WHERE id = $id";
                    
                    if ($db->query($sql)) {
                        $message = 'Supervision Area Deleted';
                    } else {
                        $error = 'Failed to delete supervision area';
                    }
                }
            }
        }
    }
}

// Get all supervision areas
$areas = $db->query("
    SELECT sa.*, 
           (SELECT COUNT(*) FROM employees e 
            WHERE e.supervision_area = sa.area_name AND e.is_active = 1) as employee_count
    FROM supervision_areas sa
    ORDER BY sa.is_active DESC, sa.area_name ASC
");

require_once '../../includes/header.php';
?>

<div class="supervision-areas-container">
    <!-- Page Header -->
    <div class="page-header-sa">
        <div class="header-left">
            <h2><i class="fas fa-map-marked-alt"></i> <span data-lang="manage-supervision-areas">Manage Supervision Areas</span></h2>
            <p data-lang="manage-supervision-areas-subtitle">Add, edit, or manage supervision areas for operational supervisors</p>
        </div>
        <button class="btn btn-primary btn-lg-sa" onclick="openAddModal()">
            <i class="fas fa-plus-circle"></i> <span data-lang="add-new-area">Add New Area</span>
        </button>
    </div>
    
    <?php if ($message): ?>
    <div class="alert alert-success alert-custom-sa">
        <i class="fas fa-check-circle"></i>
        <div>
            <strong data-lang="success">Success!</strong>
            <p><?php echo htmlspecialchars($message); ?></p>
        </div>
    </div>
    <?php endif; ?>
    
    <?php if ($error): ?>
    <div class="alert alert-error alert-custom-sa">
        <i class="fas fa-exclamation-circle"></i>
        <div>
            <strong data-lang="error">Error!</strong>
            <p><?php echo htmlspecialchars($error); ?></p>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Info Alert -->
    <div class="alert alert-info-sa">
        <i class="fas fa-info-circle"></i>
        <div>
            <strong data-lang="information">Information</strong>
            <p data-lang="supervision-areas-info">Supervision areas are used for Operational Supervisors. Areas marked as inactive will not appear in the form dropdown.</p>
        </div>
    </div>
    
    <!-- Areas Table -->
    <div class="card-sa">
        <div class="card-header-sa">
            <h3><i class="fas fa-list"></i> <span data-lang="supervision-areas-list">Supervision Areas List</span></h3>
        </div>
        
        <div class="card-body-sa">
            <?php if ($areas && $areas->num_rows > 0): ?>
                <div class="table-responsive">
                    <table class="table-sa">
                        <thead>
                            <tr>
                                <th style="width: 5%;">No</th>
                                <th style="width: 35%;" data-lang="area-name">Area Name</th>
                                <th style="width: 15%;" data-lang="area-code">Code</th>
                                <th style="width: 25%;" data-lang="description">Description</th>
                                <th style="width: 10%;" data-lang="usage">Usage</th>
                                <th style="width: 10%;" data-lang="status">Status</th>
                                <th style="width: 15%;" data-lang="actions">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $no = 1;
                            while ($area = $areas->fetch_assoc()): 
                            ?>
                            <tr>
                                <td><?php echo $no++; ?></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($area['area_name']); ?></strong>
                                </td>
                                <td>
                                    <?php if ($area['area_code']): ?>
                                        <span class="code-badge"><?php echo htmlspecialchars($area['area_code']); ?></span>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($area['description']): ?>
                                        <?php echo htmlspecialchars($area['description']); ?>
                                    <?php else: ?>
                                        <span class="text-muted" data-lang="no-description">No description</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="usage-badge">
                                        <i class="fas fa-users"></i>
                                        <?php echo $area['employee_count']; ?> <span data-lang="employees">employees</span>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($area['is_active']): ?>
                                        <span class="badge-status badge-active" data-lang="active">Active</span>
                                    <?php else: ?>
                                        <span class="badge-status badge-inactive" data-lang="inactive">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="action-buttons-sa">
                                        <button class="btn-action-sa btn-edit" 
                                                onclick='openEditModal(<?php echo json_encode($area); ?>)' 
                                                title="Edit" data-lang-title="edit">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        
                                        <form method="POST" style="display:inline;" onsubmit="return confirm(window.getLanguageText('confirm-change-status'));">
                                            <input type="hidden" name="action" value="toggle_status">
                                            <input type="hidden" name="id" value="<?php echo $area['id']; ?>">
                                            <input type="hidden" name="current_status" value="<?php echo $area['is_active']; ?>">
                                            <button type="submit" class="btn-action-sa <?php echo $area['is_active'] ? 'btn-deactivate' : 'btn-activate'; ?>" 
                                                    title="<?php echo $area['is_active'] ? 'Deactivate' : 'Activate'; ?>" data-lang-title="<?php echo $area['is_active'] ? 'deactivate' : 'activate'; ?>">
                                                <i class="fas <?php echo $area['is_active'] ? 'fa-ban' : 'fa-check'; ?>"></i>
                                            </button>
                                        </form>
                                        
                                        <form method="POST" style="display:inline;" onsubmit="return confirm(window.getLanguageText('confirm-delete-area-cannot-undo'));">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?php echo $area['id']; ?>">
                                            <button type="submit" class="btn-action-sa btn-delete" title="Delete" data-lang-title="delete">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty-state-sa">
                    <i class="fas fa-inbox"></i>
                    <p data-lang="no-supervision-areas-yet">No supervision areas yet</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Add Modal -->
<div id="addModal" class="modal">
    <div class="modal-content modal-medium-sa">
        <div class="modal-header modal-header-sa">
            <h3><i class="fas fa-plus-circle"></i> <span data-lang="add-new-supervision-area">Add New Supervision Area</span></h3>
            <span class="close" onclick="closeModal('addModal')">&times;</span>
        </div>
        <form method="POST" action="">
            <input type="hidden" name="action" value="add">
            <div class="modal-body">
                <div class="form-group-modal">
                    <label><span data-lang="area-name">Area Name</span> <span class="text-danger">*</span></label>
                    <input type="text" name="area_name" class="form-control-modal" required placeholder="Example: PT Meares Soputan Mining (MSM)" data-lang-placeholder="supervision-area-company-example">
                    <small class="form-hint" data-lang="supervision-area-full-name-hint">Full name of the supervision area</small>
                </div>
                
                <div class="form-group-modal">
                    <label><span data-lang="area-code">Area Code</span> <span class="text-muted">(Optional)</span></label>
                    <input type="text" name="area_code" class="form-control-modal" placeholder="Example: MSM" data-lang-placeholder="supervision-area-code-example" maxlength="50">
                    <small class="form-hint" data-lang="supervision-area-code-hint">Short code or abbreviation for the area</small>
                </div>
                
                <div class="form-group-modal">
                    <label><span data-lang="description">Description</span> <span class="text-muted">(Optional)</span></label>
                    <textarea name="description" class="form-control-modal" rows="3" placeholder="Brief description of this supervision area" data-lang-placeholder="brief-description-supervision-area"></textarea>
                </div>
            </div>
            <div class="modal-footer-modal">
                <button type="button" class="btn btn-secondary" onclick="closeModal('addModal')"><span data-lang="cancel">Cancel</span></button>
                <button type="submit" class="btn btn-primary"><span data-lang="save">Save</span></button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Modal -->
<div id="editModal" class="modal">
    <div class="modal-content modal-medium-sa">
        <div class="modal-header modal-header-sa">
            <h3><i class="fas fa-edit"></i> <span data-lang="edit-supervision-area">Edit Supervision Area</span></h3>
            <span class="close" onclick="closeModal('editModal')">&times;</span>
        </div>
        <form method="POST" action="">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="id" id="edit_id">
            <div class="modal-body">
                <div class="form-group-modal">
                    <label><span data-lang="area-name">Area Name</span> <span class="text-danger">*</span></label>
                    <input type="text" name="area_name" id="edit_area_name" class="form-control-modal" required placeholder="Example: PT Meares Soputan Mining (MSM)" data-lang-placeholder="supervision-area-company-example">
                    <small class="form-hint" data-lang="supervision-area-full-name-hint">Full name of the supervision area</small>
                </div>
                
                <div class="form-group-modal">
                    <label><span data-lang="area-code">Area Code</span> <span class="text-muted">(Optional)</span></label>
                    <input type="text" name="area_code" id="edit_area_code" class="form-control-modal" placeholder="Example: MSM" data-lang-placeholder="supervision-area-code-example" maxlength="50">
                    <small class="form-hint" data-lang="supervision-area-code-hint">Short code or abbreviation for the area</small>
                </div>
                
                <div class="form-group-modal">
                    <label><span data-lang="description">Description</span> <span class="text-muted">(Optional)</span></label>
                    <textarea name="description" id="edit_description" class="form-control-modal" rows="3" placeholder="Brief description of this supervision area" data-lang-placeholder="brief-description-supervision-area"></textarea>
                </div>
            </div>
            <div class="modal-footer-modal">
                <button type="button" class="btn btn-secondary" onclick="closeModal('editModal')"><span data-lang="cancel">Cancel</span></button>
                <button type="submit" class="btn btn-primary"><span data-lang="update">Update</span></button>
            </div>
        </form>
    </div>
</div>

<script>
function openAddModal() {
    document.getElementById('addModal').style.display = 'block';
}

function openEditModal(area) {
    document.getElementById('edit_id').value = area.id;
    document.getElementById('edit_area_name').value = area.area_name;
    document.getElementById('edit_area_code').value = area.area_code || '';
    document.getElementById('edit_description').value = area.description || '';
    document.getElementById('editModal').style.display = 'block';
}

function closeModal(modalId) {
    document.getElementById(modalId).style.display = 'none';
}

// Close modal when clicking outside
window.onclick = function(event) {
    if (event.target.classList.contains('modal')) {
        event.target.style.display = 'none';
    }
}
</script>

<style>
.supervision-areas-container {
    padding: 20px 0;
}

/* Page Header */
.page-header-sa {
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

.btn-lg-sa {
    padding: 12px 25px;
    font-size: 15px;
    white-space: nowrap;
    background: #37474F;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
}

/* Alert Custom */
.alert-custom-sa {
    display: flex;
    gap: 15px;
    align-items: flex-start;
    padding: 20px;
    margin-bottom: 30px;
    border-radius: 8px;
    border-left: 4px solid;
}

.alert-success.alert-custom-sa {
    background: #E8F5E9;
    border-left-color: #2E7D32;
}

.alert-success.alert-custom-sa i {
    color: #2E7D32;
    font-size: 20px;
}

.alert-error.alert-custom-sa {
    background: #fee2e2;
    border-left-color: #ef4444;
}

.alert-error.alert-custom-sa i {
    color: #ef4444;
    font-size: 20px;
}

.alert-info-sa {
    background: #ECEFF1;
    border-left: 4px solid #37474F;
    padding: 15px 20px;
    border-radius: 8px;
    display: flex;
    gap: 15px;
    margin-bottom: 30px;
}

.alert-info-sa i {
    color: #37474F;
    font-size: 20px;
}

.alert-info-sa strong {
    display: block;
    color: #37474F;
    margin-bottom: 5px;
}

.alert-info-sa p {
    margin: 0;
    color: #37474F;
    font-size: 13px;
}

/* Card */
.card-sa {
    background: white;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
    margin-bottom: 25px;
}

.card-header-sa {
    padding: 20px;
    border-bottom: 2px solid #f0f0f0;
}

.card-header-sa h3 {
    margin: 0;
    font-size: 18px;
    color: #333;
    display: flex;
    align-items: center;
    gap: 10px;
}

.card-body-sa {
    padding: 0;
}

/* Table */
.table-sa {
    width: 100%;
    border-collapse: collapse;
}

.table-sa thead th {
    background: #f8f9fa;
    color: #333;
    font-weight: 600;
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    padding: 15px;
    text-align: left;
}

.table-sa tbody tr {
    border-bottom: 1px solid #f0f0f0;
    transition: background-color 0.2s ease;
}

.table-sa tbody tr:hover {
    background-color: #f8f9ff;
}

.table-sa td {
    padding: 15px;
    vertical-align: middle;
    font-size: 13px;
}

.code-badge {
    background: #ECEFF1;
    color: #37474F;
    padding: 4px 10px;
    border-radius: 6px;
    font-size: 11px;
    font-weight: 600;
    display: inline-block;
}

.usage-badge {
    background: #f3f4f6;
    color: #666;
    padding: 4px 10px;
    border-radius: 6px;
    font-size: 11px;
    display: inline-flex;
    align-items: center;
    gap: 5px;
}

.badge-status {
    display: inline-block;
    padding: 6px 12px;
    border-radius: 6px;
    font-size: 11px;
    font-weight: 600;
}

.badge-active {
    background: #E8F5E9;
    color: #2E7D32;
}

.badge-inactive {
    background: #f3f4f6;
    color: #616161;
}

.action-buttons-sa {
    display: flex;
    gap: 6px;
}

.btn-action-sa {
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
    color: white;
}

.btn-edit {
    background: #37474F;
}

.btn-edit:hover {
    background: #263238;
    transform: translateY(-1px);
}

.btn-activate {
    background: #2E7D32;
}

.btn-activate:hover {
    background: #1B5E20;
    transform: translateY(-1px);
}

.btn-deactivate {
    background: #f59e0b;
}

.btn-deactivate:hover {
    background: #d97706;
    transform: translateY(-1px);
}

.btn-delete {
    background: #ef4444;
}

.btn-delete:hover {
    background: #dc2626;
    transform: translateY(-1px);
}

/* Empty State */
.empty-state-sa {
    text-align: center;
    padding: 60px 20px;
    color: #999;
}

.empty-state-sa i {
    font-size: 48px;
    margin-bottom: 15px;
    opacity: 0.5;
}

/* Modal */
.modal-medium-sa {
    max-width: 600px;
}

.modal-header-sa {
    background: #37474F;
    color: white;
    padding: 20px;
    border-radius: 8px 8px 0 0;
}

.modal-header-sa h3 {
    margin: 0;
    font-size: 18px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.modal-header-sa .close {
    color: white;
    opacity: 0.8;
}

.modal-header-sa .close:hover {
    opacity: 1;
}

.form-group-modal {
    margin-bottom: 20px;
}

.form-group-modal label {
    display: block;
    margin-bottom: 6px;
    font-weight: 600;
    color: #333;
    font-size: 13px;
}

.form-control-modal {
    width: 100%;
    padding: 10px 12px;
    border: 2px solid #e9ecef;
    border-radius: 6px;
    font-size: 13px;
    transition: border-color 0.3s ease;
    font-family: inherit;
}

.form-control-modal:focus {
    outline: none;
    border-color: #37474F;
}

textarea.form-control-modal {
    resize: vertical;
}

.form-hint {
    display: block;
    margin-top: 5px;
    color: #999;
    font-size: 11px;
}

.text-danger {
    color: #ef4444;
}

.text-muted {
    color: #999;
}

/* Responsive */
@media (max-width: 768px) {
    .page-header-sa {
        flex-direction: column;
        gap: 20px;
        text-align: center;
        padding: 25px 20px;
    }
    
    .header-left h2 {
        font-size: 22px;
        justify-content: center;
    }
    
    .btn-lg-sa {
        width: 100%;
    }
    
    .table-sa {
        font-size: 11px;
    }
    
    .table-sa thead th,
    .table-sa td {
        padding: 10px 8px;
    }
    
    .action-buttons-sa {
        flex-direction: column;
    }
}
</style>

<?php require_once '../../includes/footer.php'; ?>




