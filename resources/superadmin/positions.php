<?php
require_once dirname(__DIR__, 2) . '/bootstrap/app.php';
$page_title = 'Positions Master Data';
require_once dirname(__DIR__, 2) . '/bootstrap/app.php';
require_once dirname(__DIR__, 2) . '/app/Helpers/auth_helper.php';
require_once dirname(__DIR__, 2) . '/app/Helpers/MasterDataHelper.php';

requirePermission('position.view');

$db = new Database();
$helper = new MasterDataHelper($db);

$success_msg = '';
$error_msg = '';

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        if ($action === 'create' && hasPermission('position.create')) {
            $data = [
                'position_name' => $_POST['position_name'],
                'position_type' => $_POST['position_type'],
                'competency_id' => !empty($_POST['competency_id']) ? (int)$_POST['competency_id'] : null,
                'description'   => $_POST['description'],
                'is_active'     => (int)($_POST['is_active'] ?? 1)
            ];
            $res = $helper->createRecord('positions', $data, 'position_name', $data['position_name']);
            if ($res['status'] === 'success') $success_msg = $res['message'];
            else $error_msg = $res['message'];
        } 
        elseif ($action === 'update' && hasPermission('position.update')) {
            $id = (int)$_POST['id'];
            $data = [
                'position_name' => $_POST['position_name'],
                'position_type' => $_POST['position_type'],
                'competency_id' => !empty($_POST['competency_id']) ? (int)$_POST['competency_id'] : null,
                'description'   => $_POST['description'],
                'is_active'     => (int)$_POST['is_active']
            ];
            $res = $helper->updateRecord('positions', $id, $data, 'position_name', $data['position_name']);
            if ($res['status'] === 'success') $success_msg = $res['message'];
            else $error_msg = $res['message'];
        } 
        elseif ($action === 'delete' && hasPermission('position.delete')) {
            $id = (int)$_POST['id'];
            $res = $helper->deleteOrDeactivateRecord('positions', $id);
            if ($res['status'] === 'success' || $res['status'] === 'warning') {
                $success_msg = $res['message'];
            } else {
                $error_msg = $res['message'];
            }
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
if (isset($_GET['position_type']) && $_GET['position_type'] !== '') $filters['position_type'] = $_GET['position_type'];
if (isset($_GET['is_active']) && $_GET['is_active'] !== '') $filters['is_active'] = $_GET['is_active'];

$dataRes = $helper->getPaginatedData('positions', $page, $limit, $search, $filters, ['position_name', 'description']);
$records = $dataRes['data'];
$totalPages = $dataRes['pages'];
$totalRecords = $dataRes['total'];

// Dropdowns
$competencies = $helper->getList('competencies', 'competency_name ASC', 'id, competency_name');

require_once dirname(__DIR__) . '/layouts/superadmin_header.php';
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
            <h2 class="mb-0 fw-bold text-dark">Positions</h2>
        </div>
        <?php if(hasPermission('position.create')): ?>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addModal">
            <i class="fas fa-plus me-1"></i> Add Position
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
                <div class="col-md-4">
                    <input type="text" name="search" class="form-control" placeholder="Search positions..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="col-md-3">
                    <select name="position_type" class="form-select">
                        <option value="">All Types</option>
                        <option value="pengawas_operasional" <?php echo ($_GET['position_type']??'')==='pengawas_operasional'?'selected':''; ?>>Pengawas Operasional</option>
                        <option value="pengawas_teknis" <?php echo ($_GET['position_type']??'')==='pengawas_teknis'?'selected':''; ?>>Pengawas Teknis</option>
                        <option value="tenaga_teknis" <?php echo ($_GET['position_type']??'')==='tenaga_teknis'?'selected':''; ?>>Tenaga Teknis</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <select name="is_active" class="form-select">
                        <option value="">All Status</option>
                        <option value="1" <?php echo ($_GET['is_active']??'')==='1'?'selected':''; ?>>Active</option>
                        <option value="0" <?php echo ($_GET['is_active']??'')==='0'?'selected':''; ?>>Inactive</option>
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
                    <th>Position Name</th>
                    <th>Type</th>
                    <th>Linked Competency</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if(empty($records)): ?>
                    <tr><td colspan="5" class="text-center text-muted py-4">No positions found.</td></tr>
                <?php else: ?>
                    <?php foreach($records as $row): ?>
                        <tr>
                            <td class="fw-bold"><?php echo htmlspecialchars($row['position_name']); ?></td>
                            <td><span class="badge bg-light text-dark border"><?php echo htmlspecialchars(str_replace('_', ' ', $row['position_type'])); ?></span></td>
                            <td class="text-muted"><?php echo htmlspecialchars($row['competency_name'] ?? '-'); ?></td>
                            <td>
                                <?php if($row['is_active']): ?>
                                    <span class="badge bg-success bg-opacity-10 text-success px-2 py-1">Active</span>
                                <?php else: ?>
                                    <span class="badge bg-danger bg-opacity-10 text-danger px-2 py-1">Inactive</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end">
                                <?php if(hasPermission('position.update')): ?>
                                <button class="action-btn" onclick='editData(<?php echo json_encode($row); ?>)'><i class="fas fa-edit text-primary"></i></button>
                                <?php endif; ?>
                                
                                <?php if(hasPermission('position.delete')): ?>
                                <button class="action-btn" onclick='deleteData(<?php echo $row['id']; ?>, "<?php echo htmlspecialchars($row['position_name'], ENT_QUOTES); ?>")'><i class="fas fa-trash-alt text-danger"></i></button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
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

<!-- Modals -->
<div class="modal fade" id="addModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="create">
                <div class="modal-header"><h5 class="modal-title">Add Position</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Position Name *</label>
                        <input type="text" name="position_name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Position Type *</label>
                        <select name="position_type" class="form-select" required>
                            <option value="pengawas_operasional">Pengawas Operasional</option>
                            <option value="pengawas_teknis">Pengawas Teknis</option>
                            <option value="tenaga_teknis">Tenaga Teknis</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Linked Competency</label>
                        <select name="competency_id" class="form-select">
                            <option value="">-- None --</option>
                            <?php foreach($competencies as $c): ?>
                                <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['competency_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-control" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="id" id="edit_id">
                <div class="modal-header"><h5 class="modal-title">Edit Position</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Position Name *</label>
                        <input type="text" name="position_name" id="edit_position_name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Position Type *</label>
                        <select name="position_type" id="edit_position_type" class="form-select" required>
                            <option value="pengawas_operasional">Pengawas Operasional</option>
                            <option value="pengawas_teknis">Pengawas Teknis</option>
                            <option value="tenaga_teknis">Tenaga Teknis</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Linked Competency</label>
                        <select name="competency_id" id="edit_competency_id" class="form-select">
                            <option value="">-- None --</option>
                            <?php foreach($competencies as $c): ?>
                                <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['competency_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea name="description" id="edit_description" class="form-control" rows="2"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Status</label>
                        <select name="is_active" id="edit_is_active" class="form-select">
                            <option value="1">Active</option>
                            <option value="0">Inactive</option>
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

<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" id="del_id">
                <div class="modal-header"><h5 class="modal-title text-danger">Confirm Delete</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <p>Are you sure you want to delete <strong id="del_name"></strong>?</p>
                    <p class="text-muted small">If this record is currently in use, it will be deactivated instead of deleted.</p>
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
    document.getElementById('edit_position_name').value = row.position_name;
    document.getElementById('edit_position_type').value = row.position_type;
    document.getElementById('edit_competency_id').value = row.competency_id || '';
    document.getElementById('edit_description').value = row.description || '';
    document.getElementById('edit_is_active').value = row.is_active;
    new bootstrap.Modal(document.getElementById('editModal')).show();
}
function deleteData(id, name) {
    document.getElementById('del_id').value = id;
    document.getElementById('del_name').textContent = name;
    new bootstrap.Modal(document.getElementById('deleteModal')).show();
}
</script>

<?php require_once dirname(__DIR__) . '/layouts/footer.php'; ?>
