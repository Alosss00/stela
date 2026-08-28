<?php
require_once dirname(__DIR__, 2) . '/bootstrap/app.php';
$page_title = 'Role & Permissions';
require_once dirname(__DIR__, 2) . '/bootstrap/app.php';
require_once dirname(__DIR__, 2) . '/app/Helpers/auth_helper.php';
require_once dirname(__DIR__, 2) . '/app/Helpers/UserManagementHelper.php';

checkPageAccess(['superadmin'], 'manage_roles');

$db = new Database();
$helper = new UserManagementHelper($db);

// Fetch roles from the database
$roles = $helper->getAvailableRoles();

// If roles table is not populated, provide some defaults for the UI demo
if (empty($roles)) {
    $roles = ['superadmin', 'admin', 'ktt', 'user', 'department_user'];
}

// Define the available permissions across the system
$modules = [
    'User Management' => [
        'user.view' => 'View Users',
        'user.create' => 'Create Users',
        'user.update' => 'Edit Users',
        'user.delete' => 'Delete Users',
        'user.resetpassword' => 'Reset Passwords'
    ],
    'Roles & Permissions' => [
        'manage_roles' => 'Manage Roles & Permissions'
    ],
    'Employees' => [
        'employee.view' => 'View Employees',
        'employee.create' => 'Add Employees',
        'employee.verify' => 'Verify Employees'
    ],
    'Appointments' => [
        'appointment.view' => 'View Appointments',
        'appointment.approve_admin' => 'Admin Review',
        'appointment.approve_ktt' => 'KTT Approval'
    ],
    'System Settings' => [
        'manage_settings' => 'Manage Settings'
    ]
];

require_once dirname(__DIR__) . '/layouts/superadmin_header.php';
?>

<style>
    .rp-dashboard { font-family: 'Inter', sans-serif; padding: 20px 0; }
    .rp-card { background: #fff; border: none; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.04); }
    .rp-card-header { background: transparent; border-bottom: 1px solid #f0f2f5; padding: 18px 24px; font-weight: 600; }
    
    .role-list-group .list-group-item {
        border: none;
        border-bottom: 1px solid #f0f2f5;
        padding: 15px 20px;
        cursor: pointer;
        transition: all 0.2s;
    }
    .role-list-group .list-group-item:last-child {
        border-bottom: none;
    }
    .role-list-group .list-group-item:hover, .role-list-group .list-group-item.active {
        background-color: #f8fafc;
        border-left: 4px solid #2563eb;
        color: #0f172a;
    }
    .role-list-group .list-group-item.active {
        font-weight: 600;
        background-color: #eff6ff;
    }

    .permission-module {
        background: #f8fafc;
        border-radius: 8px;
        padding: 15px 20px;
        margin-bottom: 15px;
    }
    .permission-module h6 {
        color: #334155;
        font-weight: 600;
        margin-bottom: 15px;
        padding-bottom: 10px;
        border-bottom: 1px solid #e2e8f0;
    }
    
    .form-switch .form-check-input {
        width: 2.5em;
        height: 1.25em;
        cursor: pointer;
    }
    .form-switch .form-check-input:checked {
        background-color: #10b981;
        border-color: #10b981;
    }
    .permission-label {
        color: #475569;
        font-size: 0.9rem;
        cursor: pointer;
    }
</style>

<div class="container-fluid rp-dashboard">
    <div class="row mb-4 align-items-center">
        <div class="col-md-6">
            <h2 class="mb-1 fw-bold" style="color: #1e293b;">Role & Permissions</h2>
            <p class="text-muted mb-0">Manage access control and role capabilities</p>
        </div>
        <div class="col-md-6 text-end">
            <button class="btn btn-primary rounded-pill px-4" data-bs-toggle="modal" data-bs-target="#addRoleModal">
                <i class="fas fa-plus me-2"></i> Add Role
            </button>
        </div>
    </div>

    <div class="row">
        <!-- Roles Sidebar -->
        <div class="col-md-4 col-lg-3 mb-4">
            <div class="rp-card h-100">
                <div class="rp-card-header d-flex justify-content-between align-items-center">
                    <span class="text-dark">System Roles</span>
                </div>
                <div class="list-group list-group-flush role-list-group">
                    <?php foreach($roles as $index => $role): ?>
                        <div class="list-group-item <?php echo $index === 0 ? 'active' : ''; ?>" onclick="selectRole(this, '<?php echo htmlspecialchars($role); ?>')">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <i class="fas fa-shield-alt me-2 <?php echo $role === 'superadmin' ? 'text-warning' : 'text-primary'; ?>"></i>
                                    <span class="text-capitalize"><?php echo htmlspecialchars($role); ?></span>
                                </div>
                                <?php if($role !== 'superadmin'): ?>
                                <button class="btn btn-sm btn-link text-danger p-0" title="Delete Role"><i class="fas fa-trash-alt"></i></button>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Permissions Editor -->
        <div class="col-md-8 col-lg-9 mb-4">
            <div class="rp-card h-100">
                <div class="rp-card-header d-flex justify-content-between align-items-center">
                    <span class="text-dark">Permissions for <span id="currentRoleDisplay" class="text-primary text-capitalize fw-bold"><?php echo htmlspecialchars($roles[0]); ?></span></span>
                    <button class="btn btn-sm btn-success px-3" onclick="savePermissions()">
                        <i class="fas fa-save me-1"></i> Save Changes
                    </button>
                </div>
                <div class="card-body p-4">
                    
                    <div class="alert alert-info small mb-4">
                        <i class="fas fa-info-circle me-2"></i> Changes to permissions take effect immediately for users logging in. Active sessions may require a re-login. (Superadmin has full access by default).
                    </div>

                    <form id="permissionsForm">
                        <div class="row">
                            <?php foreach($modules as $moduleName => $permissions): ?>
                            <div class="col-md-6">
                                <div class="permission-module">
                                    <h6><?php echo htmlspecialchars($moduleName); ?></h6>
                                    <?php foreach($permissions as $key => $label): ?>
                                    <div class="form-check form-switch mb-2 d-flex align-items-center">
                                        <input class="form-check-input me-3 perm-checkbox" type="checkbox" id="perm_<?php echo str_replace('.', '_', $key); ?>" name="permissions[]" value="<?php echo htmlspecialchars($key); ?>">
                                        <label class="form-check-label permission-label" for="perm_<?php echo str_replace('.', '_', $key); ?>">
                                            <?php echo htmlspecialchars($label); ?>
                                            <small class="d-block text-muted" style="font-size: 0.75rem;"><?php echo htmlspecialchars($key); ?></small>
                                        </label>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </form>

                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add Role Modal -->
<div class="modal fade" id="addRoleModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" id="addRoleForm">
    <?= csrf_field() ?>
                <input type="hidden" name="action" value="create_role">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold">Create New Role</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Role Name *</label>
                        <input type="text" name="role_name" class="form-control" placeholder="e.g., auditor, hr_manager" required>
                        <div class="form-text">Use lowercase letters and underscores only.</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="alert('Module under construction. Role created successfully.')">Create Role</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    // UI Mockup Logic
    const demoPermissions = {
        'superadmin': ['all'],
        'admin': ['user.view', 'employee.view', 'employee.create', 'employee.verify', 'appointment.view', 'appointment.approve_admin'],
        'ktt': ['employee.view', 'appointment.view', 'appointment.approve_ktt'],
        'user': ['employee.view', 'appointment.view'],
        'department_user': ['employee.view', 'appointment.view']
    };

    function selectRole(element, role) {
        // Update active class
        document.querySelectorAll('.role-list-group .list-group-item').forEach(el => el.classList.remove('active'));
        element.classList.add('active');
        
        // Update header
        document.getElementById('currentRoleDisplay').textContent = role;
        
        // Update checkboxes
        const checkboxes = document.querySelectorAll('.perm-checkbox');
        
        if (role === 'superadmin') {
            checkboxes.forEach(cb => {
                cb.checked = true;
                cb.disabled = true; // Superadmin has all permissions inherently
            });
        } else {
            const rolePerms = demoPermissions[role] || [];
            checkboxes.forEach(cb => {
                cb.disabled = false;
                cb.checked = rolePerms.includes(cb.value);
            });
        }
    }

    function savePermissions() {
        alert("Module under construction. Permissions saved successfully!");
    }

    // Initialize with first role
    document.addEventListener('DOMContentLoaded', () => {
        const firstRole = document.querySelector('.role-list-group .list-group-item');
        if (firstRole) {
            firstRole.click();
        }
    });
</script>

<?php require_once dirname(__DIR__) . '/layouts/footer.php'; ?>
