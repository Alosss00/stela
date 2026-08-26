<?php
require_once dirname(__DIR__, 2) . '/bootstrap/app.php';
$page_title = 'Backup & Restore';
require_once dirname(__DIR__, 2) . '/bootstrap/app.php';
require_once dirname(__DIR__, 2) . '/app/Helpers/auth_helper.php';

checkPageAccess(['superadmin'], 'backup_restore');
require_once dirname(__DIR__) . '/layouts/superadmin_header.php';
?>
<div class="container-fluid">
    <div class="card shadow-sm border-0">
        <div class="card-header bg-white py-3">
            <h5 class="mb-0 fw-bold text-dark"><i class="fas fa-hdd me-2 text-primary"></i> Backup & Restore</h5>
        </div>
        <div class="card-body">
            <p>Module under construction. Trigger DB dumps and file zips.</p>
        </div>
    </div>
</div>
<?php require_once dirname(__DIR__) . '/layouts/superadmin_footer.php'; ?>
