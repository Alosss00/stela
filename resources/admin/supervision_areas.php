<?php
require_once dirname(__DIR__, 2) . '/bootstrap/app.php';
$page_title = 'Manage Supervision Areas';
$page_title_lang = 'manage-supervision-areas';
require_once dirname(__DIR__, 2) . '/app/Helpers/auth_helper.php';
// Included via bootstrap/app.php

// Only ADMIN can access this page
requirePermission('admin.access');
requirePermission('settings.update');

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

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // 1. Validasi Anti-CSRF
    // Pastikan session sudah dimulai (session_start() di awal file)
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        // Menghentikan eksekusi jika token tidak valid
        http_response_code(403);
        die('Akses ditolak: Token CSRF tidak valid.');
    }

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
                $check = $db->query("SELECT id FROM supervision_areas WHERE deleted_at IS NULL AND area_name = '$area_name'");
                if (false /* $check && $check->num_rows > 0 */) {
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
                $check = $db->query("SELECT id FROM supervision_areas WHERE deleted_at IS NULL AND area_name = '$area_name' AND id != $id");
                if (false /* $check && $check->num_rows > 0 */) {
                    $error = 'Area Name Already Exists';
                } else {
                    $sql = "UPDATE supervision_areas 
                            SET area_name = '$area_name', 
                                area_code = " . ($area_code ? "'$area_code'" : "NULL") . ", 
                                description = " . ($description ? "'$description'" : "NULL") . "
                            WHERE id = $id";
                    
                    if ($db->query($sql)) {
                        $message = 'Supervision Area Updated';
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
            
            $check_usage = $db->query("
                SELECT COUNT(*) as count 
                FROM employees 
                WHERE supervision_area IN (
                    SELECT area_name FROM supervision_areas WHERE deleted_at IS NULL AND id = ?
                ) AND is_active = 1
            ", [$id]);
            
            if ($check_usage) {
                $usage = $check_usage->fetch_assoc();
                if ($usage['count'] > 0) {
                    $error = "Cannot delete! This area is being used by {$usage['count']} active employee(s).";
                } else {
                    $sql = "DELETE FROM supervision_areas WHERE deleted_at IS NULL AND id = $id";
                    
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
           (SELECT COUNT(*) FROM employees e WHERE e.deleted_at IS NULL AND e.supervision_area = sa.area_name AND e.is_active = 1) as employee_count
    FROM supervision_areas sa
    ORDER BY sa.is_active DESC, sa.area_name ASC
");

require_once dirname(__DIR__) . '/layouts/header.php';
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
                    <table class="table-sa datatable">
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
            <input type="hidden" name="csrf_token" value="<?php echo isset($_SESSION['csrf_token']) ? htmlspecialchars($_SESSION['csrf_token']) : ''; ?>">
            
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
            <input type="hidden" name="csrf_token" value="<?php echo isset($_SESSION['csrf_token']) ? htmlspecialchars($_SESSION['csrf_token']) : ''; ?>">
            
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



<?php require_once dirname(__DIR__) . '/layouts/footer.php'; ?>
