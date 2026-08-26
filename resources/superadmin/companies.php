<?php
require_once dirname(__DIR__, 2) . '/bootstrap/app.php';
$page_title = 'Company Management';
require_once dirname(__DIR__, 2) . '/app/Helpers/auth_helper.php';
requirePermission('manage_companies');

$db = new Database();

$success_msg = '';
$error_msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'rename') {
        $old_name = trim($_POST['old_name']);
        $new_name = trim($_POST['new_name']);
        if (empty($new_name)) {
            $error_msg = "New company name cannot be empty.";
        } else {
            $stmt = $db->prepare("UPDATE users SET company_name = ? WHERE company_name = ?");
            $stmt->bind_param("ss", $new_name, $old_name);
            if ($stmt->execute()) {
                // Also update appointments just in case they store company strings
                $stmt2 = $db->prepare("UPDATE appointments SET contractor_company = ? WHERE contractor_company = ?");
                $stmt2->bind_param("ss", $new_name, $old_name);
                $stmt2->execute();
                
                $success_msg = "Company renamed successfully from '$old_name' to '$new_name'.";
            } else {
                $error_msg = "Failed to rename company.";
            }
        }
    }
}

// Fetch list of companies from users
$res = $db->query("SELECT company_name, COUNT(*) as total_users FROM users WHERE company_name IS NOT NULL AND company_name != '' AND deleted_at IS NULL GROUP BY company_name ORDER BY company_name ASC");
$companies = [];
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $companies[] = $row;
    }
}

require_once dirname(__DIR__) . '/layouts/superadmin_header.php';
?>
<div class="container-fluid py-4">
    <div class="row mb-4 align-items-center">
        <div class="col-md-6">
            <h2 class="mb-1 fw-bold" style="color: #1e293b;">Company Management</h2>
            <p class="text-muted mb-0">Manage and rename company lists across users and appointments</p>
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

    <div class="card shadow-sm border-0" style="border-radius: 12px;">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light text-muted">
                        <tr>
                            <th class="px-4 py-3 border-0">Company Name</th>
                            <th class="py-3 border-0">Total Users</th>
                            <th class="py-3 border-0 text-end pe-4">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($companies)): ?>
                            <tr>
                                <td colspan="3" class="text-center py-5 text-muted">No companies found in user data.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach($companies as $c): ?>
                                <tr>
                                    <td class="px-4 fw-semibold text-dark"><?php echo htmlspecialchars($c['company_name']); ?></td>
                                    <td><span class="badge bg-primary rounded-pill"><?php echo $c['total_users']; ?></span></td>
                                    <td class="text-end pe-4">
                                        <button class="btn btn-sm btn-outline-primary" onclick="editCompany('<?php echo htmlspecialchars($c['company_name'], ENT_QUOTES); ?>')">
                                            <i class="fas fa-edit"></i> Rename
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Edit Company Modal -->
<div class="modal fade" id="editCompanyModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="rename">
                <input type="hidden" name="old_name" id="old_name">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold">Rename Company</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted small mb-3">Renaming this company will update the company name for all associated users and appointments.</p>
                    <div class="mb-3">
                        <label class="form-label">New Company Name *</label>
                        <input type="text" name="new_name" id="new_name" class="form-control" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function editCompany(name) {
    document.getElementById('old_name').value = name;
    document.getElementById('new_name').value = name;
    var modal = new bootstrap.Modal(document.getElementById('editCompanyModal'));
    modal.show();
}
</script>

<?php require_once dirname(__DIR__) . '/layouts/superadmin_footer.php'; ?>
