<?php
require_once dirname(__DIR__, 2) . '/bootstrap/app.php';
$page_title = 'Monitoring Logs';
require_once dirname(__DIR__, 2) . '/bootstrap/app.php';
require_once dirname(__DIR__, 2) . '/app/Helpers/auth_helper.php';

checkPageAccess(['superadmin'], 'view_logs');
require_once dirname(__DIR__) . '/layouts/superadmin_header.php';
?>
<div class="container-fluid">
    <div class="card shadow-sm border-0">
        <div class="card-header bg-white py-3">
            <h5 class="mb-0 fw-bold text-dark"><i class="fas fa-clipboard-list me-2 text-primary"></i> Audit & Monitoring Logs</h5>
        </div>
        <div class="card-body">
            <p>Module under construction. Audit logs, login history, and error logs.</p>
        </div>
    </div>
</div>
<?php require_once dirname(__DIR__) . '/layouts/superadmin_footer.php'; ?>
