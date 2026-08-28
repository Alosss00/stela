<?php
require_once dirname(__DIR__, 2) . '/bootstrap/app.php';
$page_title = 'User Management';
require_once dirname(__DIR__, 2) . '/bootstrap/app.php';
require_once dirname(__DIR__, 2) . '/app/Helpers/auth_helper.php';
require_once dirname(__DIR__, 2) . '/app/Helpers/UserManagementHelper.php';

// RBAC
requirePermission('user.view');

$db = new Database();
$helper = new UserManagementHelper($db);
$currentUserId = $_SESSION['user_id'];

$success_msg = '';
$error_msg = '';

// Handle POST Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token()) {
        die("CSRF Token Invalid. Silakan muat ulang halaman.");
    }

    $action = $_POST['action'] ?? '';
    
    try {
        if ($action === 'create' && hasPermission('user.create')) {
            $password = $_POST['password'] ?? '';
            if (strlen($password) < 8 || !preg_match('/[A-Z]/', $password) || !preg_match('/[0-9]/', $password)) {
                $error_msg = "Password must be at least 8 characters long and contain at least one uppercase letter and one number.";
            } else {
                $res = $helper->createUser($_POST);
                if ($res['status'] === 'success') $success_msg = $res['message'];
                else $error_msg = $res['message'];
            }
        } 
        elseif ($action === 'update' && hasPermission('user.update')) {
            $id = (int)$_POST['user_id'];
            $res = $helper->updateUser($id, $_POST, $currentUserId);
            if ($res['status'] === 'success') $success_msg = $res['message'];
            else $error_msg = $res['message'];
        } 
        elseif ($action === 'reset_password' && hasPermission('user.resetpassword')) {
            $id = (int)$_POST['user_id'];
            $new_pass = $_POST['new_password'];
            $confirm_pass = $_POST['confirm_password'];
            if ($new_pass !== $confirm_pass) {
                $error_msg = "Passwords do not match.";
            } elseif (strlen($new_pass) < 8 || !preg_match('/[A-Z]/', $new_pass) || !preg_match('/[0-9]/', $new_pass)) {
                $error_msg = "Password must be at least 8 characters long and contain at least one uppercase letter and one number.";
            } else {
                $res = $helper->resetPassword($id, $new_pass, $currentUserId);
                if ($res['status'] === 'success') $success_msg = $res['message'];
                else $error_msg = $res['message'];
            }
        } 
        elseif ($action === 'delete' && hasPermission('user.delete')) {
            $id = (int)$_POST['user_id'];
            $res = $helper->safeDeleteUser($id, $currentUserId);
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

// Fetch Filters and Data
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$limit = 15;

$search = $_GET['search'] ?? '';
$filters = [
    'role' => $_GET['role'] ?? '',
    'company_name' => $_GET['company_name'] ?? '',
    'department' => $_GET['department'] ?? '',
    'is_active' => $_GET['is_active'] ?? ''
];

$usersData = $helper->getUsers($page, $limit, $search, $filters);
$users = $usersData['data'];
$totalPages = $usersData['pages'];
$totalRecords = $usersData['total'];

// Data for dropdowns
$availableRoles = $helper->getAvailableRoles();
$availableCompanies = $helper->getUniqueCompanies();
$availableDepartments = $helper->getUniqueDepartments();

require_once dirname(__DIR__) . '/layouts/superadmin_header.php';
?>

<style>
    .um-dashboard { font-family: 'Inter', sans-serif; padding: 20px 0; }
    .um-card { 
        background: #fff; border: none; border-radius: 16px; 
        box-shadow: 0 10px 30px rgba(0,0,0,0.03); 
        transition: transform 0.3s ease, box-shadow 0.3s ease;
    }
    .um-card-header { background: transparent; border-bottom: 1px solid #f1f5f9; padding: 20px 24px; font-weight: 600; }
    
    .table-modern { width: 100%; border-collapse: separate; border-spacing: 0 8px; }
    .table-modern th { border: none; color: #64748b; font-weight: 600; text-transform: uppercase; font-size: 0.75rem; padding: 12px 15px; letter-spacing: 0.5px; }
    .table-modern td { background: #f8fafc; padding: 15px; border: none; vertical-align: middle; transition: all 0.2s ease; }
    .table-modern td:first-child { border-radius: 12px 0 0 12px; }
    .table-modern td:last-child { border-radius: 0 12px 12px 0; }
    
    /* Row hover effect */
    .table-modern tbody tr { transition: transform 0.2s ease; }
    .table-modern tbody tr:hover td { background: #fff; box-shadow: 0 4px 12px rgba(0,0,0,0.05); transform: translateY(-2px); z-index: 10; position: relative; }
    .table-modern tbody tr:hover td:first-child { border-left: 3px solid #3b82f6; }
    
    .badge-modern { padding: 6px 14px; border-radius: 30px; font-size: 0.75rem; font-weight: 600; letter-spacing: 0.3px; }
    .badge-success { background: linear-gradient(135deg, #dcfce7, #bbf7d0); color: #166534; }
    .badge-danger { background: linear-gradient(135deg, #fee2e2, #fecaca); color: #991b1b; }
    .badge-role { background: linear-gradient(135deg, #e0f2fe, #bae6fd); color: #075985; }

    .action-btn { 
        background: transparent; border: none; color: #94a3b8; padding: 8px; 
        border-radius: 8px; transition: all 0.2s ease; 
        display: inline-flex; align-items: center; justify-content: center;
        width: 32px; height: 32px;
    }
    .action-btn:hover { transform: scale(1.1); }
    .action-btn.edit:hover { color: #2563eb; background: #dbeafe; box-shadow: 0 2px 8px rgba(37,99,235,0.2); }
    .action-btn.delete:hover { color: #dc2626; background: #fee2e2; box-shadow: 0 2px 8px rgba(220,38,38,0.2); }
    .action-btn.key:hover { color: #d97706; background: #fef3c7; box-shadow: 0 2px 8px rgba(217,119,6,0.2); }

    /* Button Animations */
    .btn-primary.rounded-pill {
        background: linear-gradient(135deg, #3b82f6, #2563eb);
        border: none;
        box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
        transition: all 0.3s ease;
    }
    .btn-primary.rounded-pill:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 16px rgba(37, 99, 235, 0.4);
    }

</style>

<div class="container-fluid um-dashboard">
    <div class="row mb-4 align-items-center">
        <div class="col-md-6">
            <h2 class="mb-1 fw-bold" style="color: #1e293b;">User Management</h2>
            <p class="text-muted mb-0">Manage all STELA system accounts</p>
        </div>
        <div class="col-md-6 text-end">
            <?php if(hasPermission('user.create')): ?>
            <button class="btn btn-primary rounded-pill px-4" data-bs-toggle="modal" data-bs-target="#addUserModal">
                <i class="fas fa-plus me-2"></i> Add User
            </button>
            <?php endif; ?>
        </div>
    </div>

    <?php if($success_msg): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i> <?php echo htmlspecialchars($success_msg); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    <?php if($error_msg): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle me-2"></i> <?php echo htmlspecialchars($error_msg); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="um-card">
        <div class="um-card-header">
            <!-- Filter Form -->
            <form method="GET" class="row g-3 align-items-center">
                <div class="col-md-3">
                    <input type="text" name="search" class="form-control" placeholder="Search name, username, email..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="col-md-2">
                    <select name="role" class="form-select">
                        <option value="">All Roles</option>
                        <?php foreach($availableRoles as $r): ?>
                            <option value="<?php echo htmlspecialchars($r); ?>" <?php echo $filters['role'] === $r ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars(ucfirst($r)); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="company_name" class="form-select">
                        <option value="">All Companies</option>
                        <?php foreach($availableCompanies as $c): ?>
                            <option value="<?php echo htmlspecialchars($c); ?>" <?php echo $filters['company_name'] === $c ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($c); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="department" class="form-select">
                        <option value="">All Departments</option>
                        <?php foreach($availableDepartments as $d): ?>
                            <option value="<?php echo htmlspecialchars($d); ?>" <?php echo $filters['department'] === $d ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($d); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="is_active" class="form-select">
                        <option value="">All Status</option>
                        <option value="1" <?php echo $filters['is_active'] === '1' ? 'selected' : ''; ?>>Active</option>
                        <option value="0" <?php echo $filters['is_active'] === '0' ? 'selected' : ''; ?>>Inactive</option>
                    </select>
                </div>
                <div class="col-md-1">
                    <button type="submit" class="btn btn-outline-secondary w-100"><i class="fas fa-filter"></i></button>
                </div>
            </form>
        </div>

        <div class="card-body p-4">
            <div class="mb-3 text-muted small">
                Showing <?php echo count($users); ?> of <?php echo $totalRecords; ?> users
            </div>
            <div class="table-responsive">
                <table class="table-modern">
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Phone</th>
                            <th>Role</th>
                            <th>Scope</th>
                            <th>Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($users)): ?>
                            <tr><td colspan="6" class="text-center py-4 text-muted">No users found.</td></tr>
                        <?php else: ?>
                            <?php foreach($users as $user): ?>
                                <tr>
                                    <td>
                                        <div class="fw-bold text-dark"><?php echo htmlspecialchars($user['full_name'] ?? ''); ?></div>
                                        <div class="small text-muted">
                                            @<?php echo htmlspecialchars($user['username'] ?? ''); ?> &bull; <?php echo htmlspecialchars($user['email'] ?? ''); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="small text-dark"><?php echo htmlspecialchars($user['phone'] ?? '-'); ?></div>
                                    </td>
                                    <td>
                                        <span class="badge-modern badge-role"><?php echo htmlspecialchars(ucfirst($user['role'] ?? '')); ?></span>
                                    </td>
                                    <td>
                                        <div class="small fw-bold"><?php echo htmlspecialchars($user['company_name'] ?: '-'); ?></div>
                                        <div class="small text-muted"><?php echo htmlspecialchars($user['department'] ?: '-'); ?></div>
                                    </td>
                                    <td>
                                        <?php if($user['is_active']): ?>
                                            <span class="badge-modern badge-success">Active</span>
                                        <?php else: ?>
                                            <span class="badge-modern badge-danger">Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end">
                                        <?php if(hasPermission('user.update')): ?>
                                        <button class="action-btn edit" onclick='editUser(<?php echo json_encode($user); ?>)' title="Edit User">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <?php endif; ?>
                                        
                                        <?php if(hasPermission('user.resetpassword')): ?>
                                        <button class="action-btn key" onclick='resetPass(<?php echo $user['id']; ?>, "<?php echo htmlspecialchars($user['username'] ?? '', ENT_QUOTES); ?>")' title="Reset Password">
                                            <i class="fas fa-key"></i>
                                        </button>
                                        <?php endif; ?>

                                        <?php if(hasPermission('user.delete') && $user['id'] != $currentUserId): ?>
                                        <button class="action-btn delete" onclick='deleteUser(<?php echo $user['id']; ?>, "<?php echo htmlspecialchars($user['username'] ?? '', ENT_QUOTES); ?>")' title="Delete/Deactivate User">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
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
                        // Retain query parameters for pagination
                        $q = $_GET;
                        $q['page'] = max(1, $page - 1);
                        $prevUrl = '?' . http_build_query($q);
                        
                        $q['page'] = min($totalPages, $page + 1);
                        $nextUrl = '?' . http_build_query($q);
                        ?>
                        <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                            <a class="page-link" href="<?php echo $prevUrl; ?>">Previous</a>
                        </li>
                        
                        <?php for($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                            <?php $q['page'] = $i; ?>
                            <li class="page-item <?php echo ($page == $i) ? 'active' : ''; ?>">
                                <a class="page-link" href="?<?php echo http_build_query($q); ?>"><?php echo $i; ?></a>
                            </li>
                        <?php endfor; ?>

                        <li class="page-item <?php echo ($page >= $totalPages) ? 'disabled' : ''; ?>">
                            <a class="page-link" href="<?php echo $nextUrl; ?>">Next</a>
                        </li>
                    </ul>
                </nav>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Add User Modal -->
<div class="modal fade" id="addUserModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST">
    <?= csrf_field() ?>
                <input type="hidden" name="action" value="create">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold">Add New User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Username *</label>
                            <input type="text" name="username" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Full Name *</label>
                            <input type="text" name="full_name" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email *</label>
                            <input type="email" name="email" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Phone</label>
                            <input type="text" name="phone" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Role *</label>
                            <select name="role" class="form-select" required>
                                <option value="">Select Role</option>
                                <?php foreach($availableRoles as $r): ?>
                                    <option value="<?php echo htmlspecialchars($r); ?>"><?php echo htmlspecialchars(ucfirst($r)); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Password *</label>
                            <input type="password" name="password" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Confirm Password *</label>
                            <input type="password" name="confirm_password" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Company Name</label>
                            <select name="company_name" class="form-select">
                                <option value="">Select Company (Optional)</option>
                                <?php foreach($availableCompanies as $c): ?>
                                    <option value="<?php echo htmlspecialchars($c); ?>"><?php echo htmlspecialchars($c); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Department</label>
                            <input type="text" name="department" class="form-control" placeholder="For Dept scope">
                        </div>
                        <div class="col-md-12">
                            <label class="form-label">Status</label>
                            <select name="is_active" class="form-select">
                                <option value="1">Active</option>
                                <option value="0">Inactive</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create User</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit User Modal -->
<div class="modal fade" id="editUserModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST">
    <?= csrf_field() ?>
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="user_id" id="edit_user_id">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold">Edit User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Username *</label>
                            <input type="text" name="username" id="edit_username" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Full Name *</label>
                            <input type="text" name="full_name" id="edit_full_name" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email *</label>
                            <input type="email" name="email" id="edit_email" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Phone</label>
                            <input type="text" name="phone" id="edit_phone" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Role *</label>
                            <select name="role" id="edit_role" class="form-select" required>
                                <?php foreach($availableRoles as $r): ?>
                                    <option value="<?php echo htmlspecialchars($r); ?>"><?php echo htmlspecialchars(ucfirst($r)); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Company Name</label>
                            <select name="company_name" id="edit_company" class="form-select">
                                <option value="">Select Company (Optional)</option>
                                <?php foreach($availableCompanies as $c): ?>
                                    <option value="<?php echo htmlspecialchars($c); ?>"><?php echo htmlspecialchars($c); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Department</label>
                            <input type="text" name="department" id="edit_department" class="form-control">
                        </div>
                        <div class="col-md-12">
                            <label class="form-label">Status</label>
                            <select name="is_active" id="edit_status" class="form-select">
                                <option value="1">Active</option>
                                <option value="0">Inactive</option>
                            </select>
                        </div>
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

<!-- Reset Password Modal -->
<div class="modal fade" id="resetPassModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
    <?= csrf_field() ?>
                <input type="hidden" name="action" value="reset_password">
                <input type="hidden" name="user_id" id="reset_user_id">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold">Reset Password</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Reset password for user: <strong id="reset_username"></strong></p>
                    <div class="mb-3">
                        <label class="form-label">New Password</label>
                        <input type="password" name="new_password" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Confirm New Password</label>
                        <input type="password" name="confirm_password" class="form-control" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning">Reset Password</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
    <?= csrf_field() ?>
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="user_id" id="delete_user_id">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold text-danger">Confirm Delete</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete user <strong id="delete_username"></strong>?</p>
                    <div class="alert alert-warning small">
                        <i class="fas fa-info-circle me-1"></i> If this user is tied to past approvals or operations, the system will safely deactivate the account instead of permanently deleting it to preserve history.
                    </div>
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
    function editUser(user) {
        document.getElementById('edit_user_id').value = user.id;
        document.getElementById('edit_username').value = user.username;
        document.getElementById('edit_full_name').value = user.full_name;
        document.getElementById('edit_email').value = user.email;
        document.getElementById('edit_phone').value = user.phone || '';
        document.getElementById('edit_role').value = user.role;
        document.getElementById('edit_company').value = user.company_name;
        document.getElementById('edit_department').value = user.department;
        document.getElementById('edit_status').value = user.is_active;
        var modal = new bootstrap.Modal(document.getElementById('editUserModal'));
        modal.show();
    }

    function resetPass(id, username) {
        document.getElementById('reset_user_id').value = id;
        document.getElementById('reset_username').textContent = username;
        var modal = new bootstrap.Modal(document.getElementById('resetPassModal'));
        modal.show();
    }

    function deleteUser(id, username) {
        document.getElementById('delete_user_id').value = id;
        document.getElementById('delete_username').textContent = username;
        var modal = new bootstrap.Modal(document.getElementById('deleteModal'));
        modal.show();
    }
</script>

<?php require_once dirname(__DIR__) . '/layouts/footer.php'; ?>
