<?php
$page_title = 'System Settings';
require_once dirname(__DIR__, 3) . '/bootstrap/app.php';
require_once dirname(__DIR__, 3) . '/app/Helpers/auth_helper.php';

checkPageAccess(['superadmin'], 'manage_settings');
require_once dirname(__DIR__) . '/layouts/superadmin_header.php';
?>
<div class="container-fluid">
    <div class="card shadow-sm border-0">
        <div class="card-header bg-white py-3">
            <h5 class="mb-0 fw-bold text-dark"><i class="fas fa-cogs me-2 text-primary"></i> System Settings</h5>
        </div>
        <div class="card-body">
            <p>Module under construction. Application Name, Theme, Email config, etc.</p>
        </div>
    </div>
</div>
<?php require_once dirname(__DIR__) . '/layouts/superadmin_footer.php'; ?>
