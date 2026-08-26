<?php
require_once dirname(__DIR__, 2) . '/bootstrap/app.php';
$page_title = 'Department Management';
require_once dirname(__DIR__, 2) . '/bootstrap/app.php';
require_once dirname(__DIR__, 2) . '/app/Helpers/auth_helper.php';

checkPageAccess(['superadmin'], 'manage_departments');
require_once dirname(__DIR__) . '/layouts/superadmin_header.php';
?>
<div class="container-fluid">
    <div class="card shadow-sm border-0">
        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
            <h5 class="mb-0 fw-bold text-dark"><i class="fas fa-network-wired me-2 text-primary"></i> Department Management</h5>
            <button class="btn btn-primary"><i class="fas fa-plus"></i> Add Department</button>
        </div>
        <div class="card-body">
            <p>Module under construction. Departments CRUD.</p>
        </div>
    </div>
</div>
<?php require_once dirname(__DIR__) . '/layouts/superadmin_footer.php'; ?>
