<?php
$page_title = 'Position Requirements';
require_once dirname(__DIR__, 3) . '/bootstrap/app.php';
require_once dirname(__DIR__, 3) . '/app/Helpers/auth_helper.php';
require_once dirname(__DIR__, 3) . '/app/Helpers/MasterDataHelper.php';

requirePermission('position_requirement.view');

$db = new Database();
$helper = new MasterDataHelper($db);

$success_msg = '';
$error_msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'create' && hasPermission('position_requirement.create')) {
            $data = [
                'position_id'      => (int)$_POST['position_id'],
                'certification_id' => (int)$_POST['certification_id'],
                'is_mandatory'     => (int)($_POST['is_mandatory'] ?? 1)
            ];
            // Position Requirement check logic is already inside helper createRecord
            $res = $helper->createRecord('position_requirements', $data);
            if ($res['status'] === 'success') $success_msg = $res['message'];
            else $error_msg = $res['message'];
        } 
        elseif ($action === 'update' && hasPermission('position_requirement.update')) {
            $id = (int)$_POST['id'];
            $data = [
                'is_mandatory' => (int)$_POST['is_mandatory']
            ];
            // We only allow updating the mandatory status, not changing the mapping itself
            $res = $helper->updateRecord('position_requirements', $id, $data);
            if ($res['status'] === 'success') $success_msg = $res['message'];
            else $error_msg = $res['message'];
        } 
        elseif ($action === 'delete' && hasPermission('position_requirement.delete')) {
            $id = (int)$_POST['id'];
            $res = $helper->deleteOrDeactivateRecord('position_requirements', $id);
            if ($res['status'] === 'success' || $res['status'] === 'warning') $success_msg = $res['message'];
            else $error_msg = $res['message'];
        }
    } catch (Exception $e) {
        $error_msg = "An unexpected error occurred.";
    }
}

// Fetch Data
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$limit = 15;
$search = $_GET['search'] ?? '';

$filters = [];
if (isset($_GET['position_id']) && $_GET['position_id'] !== '') $filters['position_requirements.position_id'] = (int)$_GET['position_id'];

$dataRes = $helper->getPaginatedData('position_requirements', $page, $limit, $search, $filters, ['positions.position_name', 'certifications.cert_name']);
$records = $dataRes['data'];
$totalPages = $dataRes['pages'];

$positions = $helper->getList('positions', 'position_name ASC', 'id, position_name');
$certifications = $helper->getList('certifications', 'cert_name ASC', 'id, cert_name');

require_once dirname(__DIR__) . '/layouts/header.php';
?>

<style>
    .table-modern { width: 100%; border-collapse: separate; border-spacing: 0 8px; }
    .table-modern th { border: none; color: #64748b; font-weight: 600; text-transform: uppercase; font-size: 0.8rem; padding: 10px 15px; }
    .table-modern td { background: #fff; padding: 12px 15px; border: none; vertical-align: middle; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
    .table-modern td:first-child { border-radius: 8px 0 0 8px; }
    .table-modern td:last-child { border-radius: 0 8px 8px 0; }
    .action-btn { background: transparent; border: none; color: #64748b; padding: 5px 8px; border-radius: 6px; transition: 0.2s; }
    .action-btn:hover { background: #e2e8f0; color: #0f172a; }
</style>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <a href="master_data.php" class="text-decoration-none text-muted mb-2 d-inline-block"><i class="fas fa-arrow-left me-1"></i> Back to Master Data</a>
            <h2 class="mb-0 fw-bold text-dark">Position Requirements</h2>
        </div>
        <?php if(hasPermission('position_requirement.create')): ?>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addModal">
            <i class="fas fa-plus me-1"></i> Map Requirement
        </button>
        <?php endif; ?>
    </div>

    <?php if($success_msg): ?>
        <div class="alert alert-success alert-dismissible fade show"><i class="fas fa-check-circle me-2"></i> <?php echo htmlspecialchars($success_msg); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>
    <?php if($error_msg): ?>
        <div class="alert alert-danger alert-dismissible fade show"><i class="fas fa-exclamation-circle me-2"></i> <?php echo htmlspecialchars($error_msg); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-6">
                    <input type="text" name="search" class="form-control" placeholder="Search position or certification..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="col-md-4">
                    <select name="position_id" class="form-select">
                        <option value="">All Positions</option>
                        <?php foreach($positions as $p): ?>
                            <option value="<?php echo $p['id']; ?>" <?php echo (isset($_GET['position_id']) && $_GET['position_id'] == $p['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($p['position_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-secondary w-100">Filter</button>
                </div>
            </form>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table-modern">
            <thead>
                <tr>
                    <th>Position</th>
                    <th>Required Certification</th>
                    <th>Mandatory?</th>
                    <th>Added On</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if(empty($records)): ?>
                    <tr><td colspan="5" class="text-center text-muted py-4">No requirements mapped.</td></tr>
                <?php else: ?>
                    <?php foreach($records as $row): ?>
                        <tr>
                            <td class="fw-bold"><?php echo htmlspecialchars($row['position_name'] ?? 'Unknown'); ?></td>
                            <td><?php echo htmlspecialchars($row['cert_name'] ?? 'Unknown'); ?></td>
                            <td>
                                <?php if($row['is_mandatory']): ?>
                                    <span class="badge bg-danger bg-opacity-10 text-danger px-2 py-1">Mandatory</span>
                                <?php else: ?>
                                    <span class="badge bg-info bg-opacity-10 text-info px-2 py-1">Optional</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-muted small"><?php echo date('d M Y', strtotime($row['created_at'])); ?></td>
                            <td class="text-end">
                                <?php if(hasPermission('position_requirement.update')): ?>
                                <button class="action-btn" onclick='editData(<?php echo json_encode($row); ?>)'><i class="fas fa-edit text-primary"></i></button>
                                <?php endif; ?>
                                
                                <?php if(hasPermission('position_requirement.delete')): ?>
                                <button class="action-btn" onclick='deleteData(<?php echo $row['id']; ?>)'><i class="fas fa-trash-alt text-danger"></i></button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if($totalPages > 1): ?>
        <nav class="mt-4">
            <ul class="pagination justify-content-center">
                <?php 
                $q = $_GET;
                $q['page'] = max(1, $page - 1);
                $prevUrl = '?' . http_build_query($q);
                $q['page'] = min($totalPages, $page + 1);
                $nextUrl = '?' . http_build_query($q);
                ?>
                <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>"><a class="page-link" href="<?php echo $prevUrl; ?>">Prev</a></li>
                <?php for($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                    <?php $q['page'] = $i; ?>
                    <li class="page-item <?php echo ($page == $i) ? 'active' : ''; ?>"><a class="page-link" href="?<?php echo http_build_query($q); ?>"><?php echo $i; ?></a></li>
                <?php endfor; ?>
                <li class="page-item <?php echo ($page >= $totalPages) ? 'disabled' : ''; ?>"><a class="page-link" href="<?php echo $nextUrl; ?>">Next</a></li>
            </ul>
        </nav>
    <?php endif; ?>
</div>

<!-- Add Modal -->
<div class="modal fade" id="addModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="create">
                <div class="modal-header"><h5 class="modal-title">Map Position Requirement</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Position *</label>
                        <select name="position_id" class="form-select" required>
                            <option value="">-- Select Position --</option>
                            <?php foreach($positions as $p): ?>
                                <option value="<?php echo $p['id']; ?>"><?php echo htmlspecialchars($p['position_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Required Certification *</label>
                        <select name="certification_id" class="form-select" required>
                            <option value="">-- Select Certification --</option>
                            <?php foreach($certifications as $c): ?>
                                <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['cert_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Is Mandatory?</label>
                        <select name="is_mandatory" class="form-select">
                            <option value="1">Yes - Mandatory</option>
                            <option value="0">No - Optional</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Mapping</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Modal -->
<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="id" id="edit_id">
                <div class="modal-header"><h5 class="modal-title">Edit Requirement Status</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <div class="alert alert-info">
                        You are editing the requirement status for:<br>
                        <strong><span id="disp_pos"></span></strong> ➜ <strong><span id="disp_cert"></span></strong>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Is Mandatory?</label>
                        <select name="is_mandatory" id="edit_is_mandatory" class="form-select">
                            <option value="1">Yes - Mandatory</option>
                            <option value="0">No - Optional</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" id="del_id">
                <div class="modal-header"><h5 class="modal-title text-danger">Confirm Delete</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <p>Are you sure you want to remove this certification requirement from the position?</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Proceed</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function editData(row) {
    document.getElementById('edit_id').value = row.id;
    document.getElementById('edit_is_mandatory').value = row.is_mandatory;
    document.getElementById('disp_pos').textContent = row.position_name;
    document.getElementById('disp_cert').textContent = row.cert_name;
    new bootstrap.Modal(document.getElementById('editModal')).show();
}
function deleteData(id) {
    document.getElementById('del_id').value = id;
    new bootstrap.Modal(document.getElementById('deleteModal')).show();
}
</script>

<?php require_once dirname(__DIR__) . '/layouts/footer.php'; ?>
