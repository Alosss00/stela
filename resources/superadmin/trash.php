<?php
require_once dirname(__DIR__, 2) . '/bootstrap/app.php';
$page_title = 'Trash & Recovery';
require_once dirname(__DIR__, 2) . '/app/Helpers/auth_helper.php';

// Pastikan ini ditaruh di baris paling awal
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Generate token CSRF
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

requirePermission('superadmin.access');

$db = new Database();
$message = '';
$error = '';

$valid_tables = [
    'users' => 'Users',
    'employees' => 'Employees',
    'appointments' => 'Appointments',
    'positions' => 'Positions',
    'supervision_areas' => 'Supervision Areas',
    'competencies' => 'Competencies',
    'companies' => 'Companies',
    'departments' => 'Departments',
    'competency_sub_competencies' => 'Sub Competencies',
    'certifications' => 'Certifications'
];

$selected_table = isset($_GET['table']) && array_key_exists($_GET['table'], $valid_tables) ? $_GET['table'] : 'employees';

// Handle Restore
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'restore') {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        http_response_code(403);
        die('CSRF validation failed.');
    }
    
    $id = intval($_POST['id']);
    $table = $_POST['table'];
    
    if (array_key_exists($table, $valid_tables)) {
        if ($db->query("UPDATE $table SET deleted_at = NULL WHERE id = ?", [$id])) {
            $message = "Record successfully restored.";
        } else {
            $error = "Failed to restore record.";
        }
    }
}

// Handle Delete Permanently
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'delete_permanent') {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        http_response_code(403);
        die('CSRF validation failed.');
    }
    
    $id = intval($_POST['id']);
    $table = $_POST['table'];
    
    if (array_key_exists($table, $valid_tables)) {
        if ($db->query("DELETE FROM $table WHERE id = ?", [$id])) {
            $message = "Record permanently deleted.";
        } else {
            $error = "Failed to permanently delete record.";
        }
    }
}

// Fetch deleted records
$deleted_records = [];
if ($selected_table == 'users') {
    $deleted_records = $db->query("SELECT u.id, u.full_name as title, u.role as description, u.deleted_at, del.full_name as deleted_by_name FROM users u LEFT JOIN users del ON u.deleted_by = del.id WHERE u.deleted_at IS NOT NULL ORDER BY u.deleted_at DESC");
} elseif ($selected_table == 'employees') {
    $deleted_records = $db->query("SELECT e.id, e.full_name as title, e.contractor_company as description, e.deleted_at, del.full_name as deleted_by_name FROM employees e LEFT JOIN users del ON e.deleted_by = del.id WHERE e.deleted_at IS NOT NULL ORDER BY e.deleted_at DESC");
} elseif ($selected_table == 'appointments') {
    $deleted_records = $db->query("SELECT a.id, CONCAT('Appointment #', a.id, ' - ', e.full_name) as title, a.status as description, a.deleted_at, del.full_name as deleted_by_name FROM appointments a LEFT JOIN employees e ON a.employee_id = e.id LEFT JOIN users del ON a.deleted_by = del.id WHERE a.deleted_at IS NOT NULL ORDER BY a.deleted_at DESC");
} elseif ($selected_table == 'positions') {
    $deleted_records = $db->query("SELECT p.id, p.position_name as title, p.position_type as description, p.deleted_at, del.full_name as deleted_by_name FROM positions p LEFT JOIN users del ON p.deleted_by = del.id WHERE p.deleted_at IS NOT NULL ORDER BY p.deleted_at DESC");
} elseif ($selected_table == 'supervision_areas') {
    $deleted_records = $db->query("SELECT s.id, s.area_name as title, 'Supervision Area' as description, s.deleted_at, del.full_name as deleted_by_name FROM supervision_areas s LEFT JOIN users del ON s.deleted_by = del.id WHERE s.deleted_at IS NOT NULL ORDER BY s.deleted_at DESC");
} elseif ($selected_table == 'competencies') {
    $deleted_records = $db->query("SELECT c.id, c.competency_name as title, c.position_type as description, c.deleted_at, del.full_name as deleted_by_name FROM competencies c LEFT JOIN users del ON c.deleted_by = del.id WHERE c.deleted_at IS NOT NULL ORDER BY c.deleted_at DESC");
} elseif ($selected_table == 'companies') {
    $deleted_records = $db->query("SELECT c.id, c.name as title, 'Company' as description, c.deleted_at, del.full_name as deleted_by_name FROM companies c LEFT JOIN users del ON c.deleted_by = del.id WHERE c.deleted_at IS NOT NULL ORDER BY c.deleted_at DESC");
} elseif ($selected_table == 'departments') {
    $deleted_records = $db->query("SELECT d.id, d.name as title, 'Department' as description, d.deleted_at, del.full_name as deleted_by_name FROM departments d LEFT JOIN users del ON d.deleted_by = del.id WHERE d.deleted_at IS NOT NULL ORDER BY d.deleted_at DESC");
} elseif ($selected_table == 'competency_sub_competencies') {
    $deleted_records = $db->query("SELECT csc.id, csc.sub_competency_name as title, csc.description, csc.deleted_at, del.full_name as deleted_by_name FROM competency_sub_competencies csc LEFT JOIN users del ON csc.deleted_by = del.id WHERE csc.deleted_at IS NOT NULL ORDER BY csc.deleted_at DESC");
} elseif ($selected_table == 'certifications') {
    $deleted_records = $db->query("SELECT c.id, c.cert_name as title, c.description, c.deleted_at, del.full_name as deleted_by_name FROM certifications c LEFT JOIN users del ON c.deleted_by = del.id WHERE c.deleted_at IS NOT NULL ORDER BY c.deleted_at DESC");
}

require_once dirname(__DIR__) . '/layouts/superadmin_header.php';
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-1"><i class="fas fa-trash-restore text-warning"></i> Trash & Recovery</h2>
            <p class="text-muted">Restore deleted data from the system.</p>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($message) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="fas fa-exclamation-circle me-2"></i><?= htmlspecialchars($error) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="card shadow-sm border-0">
        <div class="card-header bg-white border-bottom pt-3 pb-2">
            <ul class="nav nav-tabs card-header-tabs" role="tablist">
                <?php foreach ($valid_tables as $table_key => $table_label): ?>
                    <li class="nav-item">
                        <a class="nav-link <?= $selected_table === $table_key ? 'active fw-bold' : 'text-muted' ?>" 
                           href="?table=<?= $table_key ?>">
                            <?= $table_label ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
        
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light text-muted">
                        <tr>
                            <th class="px-4 py-3">ID</th>
                            <th class="py-3">Record Title</th>
                            <th class="py-3">Description</th>
                            <th class="py-3">Deleted Date/Time</th>
                            <th class="py-3">Deleted By</th>
                            <th class="px-4 py-3 text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($deleted_records && $deleted_records->num_rows > 0): ?>
                            <?php while ($row = $deleted_records->fetch_assoc()): ?>
                                <tr>
                                    <td class="px-4 fw-bold">#<?= $row['id'] ?></td>
                                    <td class="fw-medium text-dark"><?= htmlspecialchars($row['title'] ?? '') ?></td>
                                    <td class="text-muted"><?= htmlspecialchars($row['description'] ?? '') ?></td>
                                    <td>
                                        <span class="badge bg-secondary">
                                            <i class="far fa-clock me-1"></i>
                                            <?= date('d M Y H:i', strtotime($row['deleted_at'])) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="text-muted"><i class="fas fa-user-times me-1"></i> <?= htmlspecialchars($row['deleted_by_name'] ?? 'System / Unknown') ?></span>
                                    </td>
                                    <td class="px-4 text-end">
                                        <form method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to restore this record?');">
                                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                            <input type="hidden" name="action" value="restore">
                                            <input type="hidden" name="table" value="<?= htmlspecialchars($selected_table) ?>">
                                            <input type="hidden" name="id" value="<?= $row['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-success rounded-pill px-3 shadow-sm mb-1">
                                                <i class="fas fa-undo me-1"></i> Restore
                                            </button>
                                        </form>
                                        <form method="POST" class="d-inline" onsubmit="return confirm('WARNING: This will permanently delete this record and it cannot be recovered. Are you sure?');">
                                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                            <input type="hidden" name="action" value="delete_permanent">
                                            <input type="hidden" name="table" value="<?= htmlspecialchars($selected_table) ?>">
                                            <input type="hidden" name="id" value="<?= $row['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-danger rounded-pill px-3 shadow-sm mb-1">
                                                <i class="fas fa-trash-alt me-1"></i> Delete
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="text-center py-5">
                                    <div class="text-muted mb-2">
                                        <i class="fas fa-inbox fa-3x opacity-25"></i>
                                    </div>
                                    <h5 class="fw-light">Trash is empty</h5>
                                    <p class="text-muted small">No deleted records found in this category.</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once dirname(__DIR__) . '/layouts/footer.php'; ?>
