<?php
$page_title = 'User Management';
require_once dirname(__DIR__, 3) . '/bootstrap/app.php';
require_once dirname(__DIR__, 3) . '/app/Helpers/auth_helper.php';

checkPageAccess(['superadmin'], 'manage_users');
require_once dirname(__DIR__) . '/layouts/superadmin_header.php';
?>
<div class="container-fluid">
    <div class="card shadow-sm border-0">
        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
            <h5 class="mb-0 fw-bold text-dark"><i class="fas fa-users-cog me-2 text-primary"></i> User Management</h5>
            <button class="btn btn-primary"><i class="fas fa-plus"></i> Add User</button>
        </div>
        <div class="card-body">
            <p>Module under construction. User listing and management (CRUD, Reset Password, Role Assignment) will be here.</p>
        </div>
    </div>
</div>
<?php require_once dirname(__DIR__) . '/layouts/superadmin_footer.php'; ?>
