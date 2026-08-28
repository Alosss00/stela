<?php
require_once dirname(__DIR__, 2) . '/bootstrap/app.php';
$page_title = 'Competencies Master Data';
require_once dirname(__DIR__, 2) . '/bootstrap/app.php';
require_once dirname(__DIR__, 2) . '/app/Helpers/auth_helper.php';
require_once dirname(__DIR__, 2) . '/app/Helpers/MasterDataHelper.php';

requirePermission('competency.view');

$db = new Database();
$helper = new MasterDataHelper($db);

$success_msg = '';
$error_msg = '';

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token()) {
        die("CSRF Token Invalid. Silakan muat ulang halaman.");
    }

    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'create' && hasPermission('competency.create')) {
            $data = [
                'competency_name' => $_POST['competency_name'],
                'position_type'   => $_POST['position_type']
            ];
            $res = $helper->createRecord('competencies', $data, 'competency_name', $data['competency_name']);
            if ($res['status'] === 'success') $success_msg = $res['message'];
            else $error_msg = $res['message'];
        } 
        elseif ($action === 'update' && hasPermission('competency.update')) {
            $id = (int)$_POST['id'];
            $data = [
                'competency_name' => $_POST['competency_name'],
                'position_type'   => $_POST['position_type']
            ];
            $res = $helper->updateRecord('competencies', $id, $data, 'competency_name', $data['competency_name']);
            if ($res['status'] === 'success') $success_msg = $res['message'];
            else $error_msg = $res['message'];
        } 
        elseif ($action === 'delete' && hasPermission('competency.delete')) {
            $id = (int)$_POST['id'];
            $res = $helper->deleteOrDeactivateRecord('competencies', $id);
            if ($res['status'] === 'success' || $res['status'] === 'warning') $success_msg = $res['message'];
            else $error_msg = $res['message'];
        }
    } catch (Exception $e) {
        $error_msg = "An unexpected error occurred.";
    }
}

// Fetch Data
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 15;
$search = $_GET['search'] ?? '';

$filters = [];
if (isset($_GET['position_type']) && $_GET['position_type'] !== '') $filters['position_type'] = $_GET['position_type'];

$dataRes = $helper->getPaginatedData('competencies', $page, $limit, $search, $filters, ['competency_name']);
$records = $dataRes['data'];
$totalPages = $dataRes['pages'];

$totalRecords = $dataRes['total'] ?? 0;

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
            <h2 class="mb-0 fw-bold text-dark">Competencies</h2>
        </div>
        <?php if(hasPermission('competency.create')): ?>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addModal">
            <i class="fas fa-plus me-1"></i> Add Competency
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
        <div class="card-body d-flex align-items-center">
            <h5 class="mb-0 text-muted fw-bold">Total Competencies: <span class="badge bg-primary ms-2" style="font-size: 1rem;"><?php echo $totalRecords; ?></span></h5>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table-modern">
            <thead>
                <tr>
                    <th>Competency Name</th>
                    <th>Position Type</th>
                    <th>Created At</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if(empty($records)): ?>
                    <tr><td colspan="4" class="text-center text-muted py-4">No competencies found.</td></tr>
                <?php else: ?>
                    <?php foreach($records as $row): ?>
                        <tr>
                            <td class="fw-bold"><?php echo htmlspecialchars($row['competency_name']); ?></td>
                            <td><span class="badge bg-light text-dark border"><?php echo htmlspecialchars(str_replace('_', ' ', $row['position_type'])); ?></span></td>
                            <td class="text-muted small"><?php echo date('d M Y', strtotime($row['created_at'])); ?></td>
                            <td class="text-end">
                                <?php if(hasPermission('competency.update')): ?>
                                <button class="action-btn" onclick='editData(<?php echo json_encode($row); ?>)'><i class="fas fa-edit text-primary"></i></button>
                                <?php endif; ?>
                                
                                <?php if(hasPermission('competency.delete')): ?>
                                <button class="action-btn" onclick='deleteData(<?php echo $row['id']; ?>, "<?php echo htmlspecialchars($row['competency_name'], ENT_QUOTES); ?>")'><i class="fas fa-trash-alt text-danger"></i></button>
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

<div class="modal fade" id="addModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
    <?= csrf_field() ?>
                <input type="hidden" name="action" value="create">
                <div class="modal-header"><h5 class="modal-title">Add Competency</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Competency Name *</label>
                        <input type="text" name="competency_name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Position Type *</label>
                        <select name="position_type" class="form-select" required>
                            <option value="pengawas_operasional">Pengawas Operasional</option>
                            <option value="pengawas_teknis">Pengawas Teknis</option>
                            <option value="tenaga_teknis">Tenaga Teknis</option>
                        </select>
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
    <?= csrf_field() ?>
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="id" id="edit_id">
                <div class="modal-header"><h5 class="modal-title">Edit Competency</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Competency Name *</label>
                        <input type="text" name="competency_name" id="edit_name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Position Type *</label>
                        <select name="position_type" id="edit_type" class="form-select" required>
                            <option value="pengawas_operasional">Pengawas Operasional</option>
                            <option value="pengawas_teknis">Pengawas Teknis</option>
                            <option value="tenaga_teknis">Tenaga Teknis</option>
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
    <?= csrf_field() ?>
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" id="del_id">
                <div class="modal-header"><h5 class="modal-title text-danger">Confirm Delete</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <p>Are you sure you want to delete <strong id="del_name"></strong>?</p>
                    <p class="text-muted small">If this record is tied to sub-competencies or positions, it cannot be deleted.</p>
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
    document.getElementById('edit_name').value = row.competency_name;
    document.getElementById('edit_type').value = row.position_type;
    new bootstrap.Modal(document.getElementById('editModal')).show();
}
function deleteData(id, name) {
    document.getElementById('del_id').value = id;
    document.getElementById('del_name').textContent = name;
    new bootstrap.Modal(document.getElementById('deleteModal')).show();
}
</script>
<?php require_once dirname(__DIR__) . '/layouts/footer.php'; ?>
