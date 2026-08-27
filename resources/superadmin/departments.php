<?php
require_once dirname(__DIR__, 2) . '/bootstrap/app.php';
$page_title = 'Department Management';
require_once dirname(__DIR__, 2) . '/app/Helpers/auth_helper.php';
requirePermission('manage_departments');

$db = new Database();

$success_msg = '';
$error_msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add') {
        $new_name = trim($_POST['new_name']);
        if (empty($new_name)) {
            $error_msg = "Department name cannot be empty.";
        } else {
            $stmt = $db->prepare("INSERT INTO departments (name) VALUES (?)");
            $stmt->bind_param("s", $new_name);
            if ($stmt->execute()) {
                $success_msg = "Department '$new_name' added successfully.";
            } else {
                $error_msg = "Failed to add department. It may already exist.";
            }
        }
    } else if ($_POST['action'] === 'rename') {
        $old_name = trim($_POST['old_name']);
        $new_name = trim($_POST['new_name']);
        if (empty($new_name)) {
            $error_msg = "New department name cannot be empty.";
        } else {
            // Update departments table
            $stmt = $db->prepare("UPDATE departments SET name = ? WHERE name = ?");
            $stmt->bind_param("ss", $new_name, $old_name);
            if ($stmt->execute()) {
                // Update users
                $stmt2 = $db->prepare("UPDATE users SET department = ? WHERE department = ?");
                $stmt2->bind_param("ss", $new_name, $old_name);
                $stmt2->execute();
                
                // Update employees
                $stmt3 = $db->prepare("UPDATE employees SET department = ? WHERE department = ?");
                $stmt3->bind_param("ss", $new_name, $old_name);
                $stmt3->execute();
                
                // Update appointments
                $stmt4 = $db->prepare("UPDATE appointments SET department = ? WHERE department = ?");
                $stmt4->bind_param("ss", $new_name, $old_name);
                $stmt4->execute();

                $success_msg = "Department renamed successfully from '$old_name' to '$new_name'.";
            } else {
                $error_msg = "Failed to rename department. The new name may already exist.";
            }
        }
    }
}

// Fetch list of departments from the departments table
$res = $db->query("SELECT name as department, 
        (SELECT COUNT(*) FROM users u WHERE u.department = departments.name AND u.deleted_at IS NULL) as total_users,
        (SELECT COUNT(*) FROM employees e WHERE e.department = departments.name AND e.deleted_at IS NULL) as total_employees
        FROM departments 
        ORDER BY name ASC");
$departments = [];
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $departments[] = $row;
    }
}

require_once dirname(__DIR__) . '/layouts/superadmin_header.php';
?>
<div class="container-fluid py-4">
    <div class="row mb-4 align-items-center">
        <div class="col-md-6">
            <h2 class="mb-1 fw-bold" style="color: #1e293b;">Department Management</h2>
            <p class="text-muted mb-0">Manage and rename department lists across the system</p>
        </div>
        <div class="col-md-6 text-end">
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addDepartmentModal">
                <i class="fas fa-plus me-2"></i> Add Department
            </button>
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
                            <th class="px-4 py-3 border-0">Department Name</th>
                            <th class="py-3 border-0">Total Users</th>
                            <th class="py-3 border-0">Total Employees</th>
                            <th class="py-3 border-0 text-end pe-4">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($departments)): ?>
                            <tr>
                                <td colspan="4" class="text-center py-5 text-muted">No departments found.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach($departments as $d): ?>
                                <tr>
                                    <td class="px-4 fw-semibold text-dark"><?php echo htmlspecialchars($d['department']); ?></td>
                                    <td><span class="badge bg-primary rounded-pill"><?php echo $d['total_users']; ?></span></td>
                                    <td><span class="badge bg-info rounded-pill"><?php echo $d['total_employees']; ?></span></td>
                                    <td class="text-end pe-4">
                                        <button class="btn btn-sm btn-outline-info" onclick="editDepartment('<?php echo htmlspecialchars($d['department'], ENT_QUOTES); ?>')">
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

<!-- Add Department Modal -->
<div class="modal fade" id="addDepartmentModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="add">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold">Add New Department</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Department Name *</label>
                        <input type="text" name="new_name" class="form-control" required placeholder="Enter department name">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Department</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Department Modal -->
<div class="modal fade" id="editDepartmentModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="action" value="rename">
                <input type="hidden" name="old_name" id="old_name">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold">Rename Department</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted small mb-3">Renaming this department will update the department name for all associated users, employees, and appointments.</p>
                    <div class="mb-3">
                        <label class="form-label">New Department Name *</label>
                        <input type="text" name="new_name" id="new_name" class="form-control" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-info text-white">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function editDepartment(name) {
    document.getElementById('old_name').value = name;
    document.getElementById('new_name').value = name;
    var modal = new bootstrap.Modal(document.getElementById('editDepartmentModal'));
    modal.show();
}
</script>

<?php require_once dirname(__DIR__) . '/layouts/superadmin_footer.php'; ?>
