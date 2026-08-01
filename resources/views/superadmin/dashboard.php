<?php
$page_title = 'Superadmin Dashboard';
require_once dirname(__DIR__, 3) . '/bootstrap/app.php';
// Included via bootstrap/app.php
require_once dirname(__DIR__, 3) . '/app/Helpers/auth_helper.php';

// Check if user is superadmin
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . AUTH_BASE_URL . '/index.php');
    exit();
}

if ($_SESSION['role'] != 'superadmin') {
    // If not superadmin, redirect to appropriate dashboard
    switch($_SESSION['role']) {
        case 'admin':
            header('Location: ../admin/dashboard.php');
            break;
        case 'ktt':
            header('Location: ../ktt/approval.php');
            break;
        case 'department_user':
            header('Location: ../dept/dashboard.php');
            break;
        case 'user':
            if (!empty($_SESSION['department'])) {
                header('Location: ../dept/dashboard.php');
            } else {
                header('Location: ../user/dashboard.php');
            }
            break;
        default:
            header('Location: ../admin/dashboard.php');
    }
    exit();
}

// Get database connection
$db = new Database();

// Ensure users table has required columns (bootstrap migration)
$db->query("ALTER TABLE users MODIFY COLUMN role ENUM('admin','ktt','user','department_user','superadmin') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT 'user'");

// Add is_active column if it doesn't exist - check multiple ways
$columnExists = false;

// Try checking INFORMATION_SCHEMA
$checkResult = $db->query("SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = 'mining_appointment' AND TABLE_NAME = 'users' AND COLUMN_NAME = 'is_active'");
if ($checkResult && $checkResult->num_rows > 0) {
    $columnExists = true;
} else {
    // Try to add column anyway - will fail silently if already exists
    $db->query("ALTER TABLE users ADD COLUMN is_active tinyint(1) DEFAULT 1");
    $columnExists = true; // Assume success or already exists
}

// Helper function for safe queries with fallback
function safeQuery($db, $queryWith, $queryWithout) {
    $result = $db->query($queryWith);
    if ($result === false) {
        // First query failed, try without is_active
        $result = $db->query($queryWithout);
    }
    return $result;
}

// Get statistics for each role
$stats = [];

// Admin users
$result = safeQuery($db, 
    "SELECT COUNT(*) as count FROM users WHERE role = 'admin' AND is_active = 1",
    "SELECT COUNT(*) as count FROM users WHERE role = 'admin'"
);
$stats['admin'] = ($result && ($row = $result->fetch_assoc())) ? $row['count'] : 0;

// KTT users  
$result = safeQuery($db,
    "SELECT COUNT(*) as count FROM users WHERE role = 'ktt' AND is_active = 1",
    "SELECT COUNT(*) as count FROM users WHERE role = 'ktt'"
);
$stats['ktt'] = ($result && ($row = $result->fetch_assoc())) ? $row['count'] : 0;

// Regular users
$result = safeQuery($db,
    "SELECT COUNT(*) as count FROM users WHERE role = 'user' AND is_active = 1",
    "SELECT COUNT(*) as count FROM users WHERE role = 'user'"
);
$stats['user'] = ($result && ($row = $result->fetch_assoc())) ? $row['count'] : 0;

// Department users
$result = safeQuery($db,
    "SELECT COUNT(*) as count FROM users WHERE role = 'department_user' AND is_active = 1",
    "SELECT COUNT(*) as count FROM users WHERE role = 'department_user'"
);
$stats['department_user'] = ($result && ($row = $result->fetch_assoc())) ? $row['count'] : 0;

// Superadmin users
$result = safeQuery($db,
    "SELECT COUNT(*) as count FROM users WHERE role = 'superadmin' AND is_active = 1",
    "SELECT COUNT(*) as count FROM users WHERE role = 'superadmin'"
);
$stats['superadmin'] = ($result && ($row = $result->fetch_assoc())) ? $row['count'] : 0;

// Get total appointments
$result = @$db->query("SELECT COUNT(*) as count FROM appointments WHERE is_active = 1");
if (!$result) {
    $result = @$db->query("SELECT COUNT(*) as count FROM appointments");
}
$total_appointments = ($result && ($row = $result->fetch_assoc())) ? $row['count'] : 0;

// Get total employees
$result = @$db->query("SELECT COUNT(*) as count FROM employees WHERE is_active = 1");
if (!$result) {
    $result = @$db->query("SELECT COUNT(*) as count FROM employees");
}
$total_employees = ($result && ($row = $result->fetch_assoc())) ? $row['count'] : 0;

$total_roles = 5;
$total_active_users = $stats['admin'] + $stats['ktt'] + $stats['user'] + $stats['department_user'] + $stats['superadmin'];

$role_cards = [
    [
        'key' => 'admin',
        'label' => 'Administrator',
        'description' => 'Manage appointments, employees, reports, and system settings.',
        'icon' => 'fa-cog',
        'route' => '../set_role_redirect.php?role=admin',
        'count' => $stats['admin'],
        'button' => 'Open Admin Dashboard',
        'theme' => 'admin-role'
    ],
    [
        'key' => 'ktt',
        'label' => 'KTT Approval',
        'description' => 'Review appointment letters and handle approval workflow.',
        'icon' => 'fa-check-circle',
        'route' => '../set_role_redirect.php?role=ktt',
        'count' => $stats['ktt'],
        'button' => 'Open KTT Dashboard',
        'theme' => 'ktt-role'
    ],
    [
        'key' => 'department_user',
        'label' => 'Department User',
        'description' => 'Monitor department-based requests and appointment data.',
        'icon' => 'fa-building',
        'route' => '../set_role_redirect.php?role=department_user',
        'count' => $stats['department_user'],
        'button' => 'Open Department Dashboard',
        'theme' => 'dept-role'
    ],
    [
        'key' => 'user',
        'label' => 'Company User',
        'description' => 'Create and track company appointment letters globally.',
        'icon' => 'fa-user',
        'route' => '../set_role_redirect.php?role=user',
        'count' => $stats['user'],
        'button' => 'Open User Dashboard',
        'theme' => 'user-role'
    ],
    [
        'key' => 'superadmin',
        'label' => 'Superadmin',
        'description' => 'Full system control with access to all dashboards and all data.',
        'icon' => 'fa-crown',
        'route' => null,
        'count' => $stats['superadmin'],
        'button' => 'Current Global View',
        'theme' => 'superadmin-role',
        'featured' => true
    ]
];

// Get recent activities
$result = safeQuery($db,
    "SELECT username, full_name, role, created_at FROM users WHERE is_active = 1 ORDER BY created_at DESC LIMIT 5",
    "SELECT username, full_name, role, created_at FROM users ORDER BY created_at DESC LIMIT 5"
);
$recent_users = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $recent_users[] = $row;
    }
}

// Hide sidebar for superadmin dashboard - full width layout
$hide_sidebar = true;

// Hide language selector from superadmin dashboard header
$hide_language_selector = true;

require_once dirname(__DIR__) . '/layouts/header.php';
?>

<div class="dashboard-modern">
    <div class="welcome-section superadmin-hero">
        <div class="hero-content">
            <span class="hero-kicker">Global Control Center</span>
            <h1>Superadmin Dashboard</h1>
            <p>Access every role, every dashboard, and every data scope from one global view.</p>
        </div>
        <div class="hero-badge">
            <i class="fas fa-shield-alt"></i>
            <span>All roles unlocked</span>
        </div>
    </div>

    <div class="overview-grid">
        <div class="overview-card overview-card-primary">
            <div class="overview-label">Active Users</div>
            <div class="overview-value"><?php echo $total_active_users; ?></div>
            <div class="overview-help">Across all registered roles</div>
        </div>
        <div class="overview-card overview-card-accent">
            <div class="overview-label">Visible Roles</div>
            <div class="overview-value"><?php echo $total_roles; ?></div>
            <div class="overview-help">Admin, KTT, User, Department, Superadmin</div>
        </div>
        <div class="overview-card">
            <div class="overview-label">Global Employees</div>
            <div class="overview-value"><?php echo $total_employees; ?></div>
            <div class="overview-help">All active employee records</div>
        </div>
        <div class="overview-card">
            <div class="overview-label">Global Appointments</div>
            <div class="overview-value"><?php echo $total_appointments; ?></div>
            <div class="overview-help">All appointment records in the system</div>
        </div>
    </div>

    <!-- Overall Statistics -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon" style="background-color: #ff6b35;">
                <i class="fas fa-users"></i>
            </div>
            <div class="stat-content">
                <h3><?php echo $stats['admin'] + $stats['ktt'] + $stats['user'] + $stats['department_user'] + $stats['superadmin']; ?></h3>
                <p>Total Users</p>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon" style="background-color: #4ecdc4;">
                <i class="fas fa-file-alt"></i>
            </div>
            <div class="stat-content">
                <h3><?php echo $total_appointments; ?></h3>
                <p>Total Appointments</p>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon" style="background-color: #45b7d1;">
                <i class="fas fa-user-tie"></i>
            </div>
            <div class="stat-content">
                <h3><?php echo $total_employees; ?></h3>
                <p>Total Employees</p>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon" style="background-color: #f7b801;">
                <i class="fas fa-shield-alt"></i>
            </div>
            <div class="stat-content">
                <h3><?php echo $stats['superadmin']; ?></h3>
                <p>Superadmin Users</p>
            </div>
        </div>
    </div>

    <!-- Role Access Cards -->
    <div class="section-title">
        <h2>Role Dashboards</h2>
        <p>Open any role dashboard directly. Superadmin stays in a global view, while other roles load with their own scope.</p>
    </div>

    <div class="role-access-grid">
        <?php foreach ($role_cards as $card): ?>
        <div class="role-card <?php echo $card['theme']; ?><?php echo !empty($card['featured']) ? ' role-card-featured' : ''; ?>">
            <div class="role-header">
                <div class="role-icon<?php echo !empty($card['featured']) ? ' role-icon-featured' : ''; ?>">
                    <i class="fas <?php echo $card['icon']; ?>"></i>
                </div>
                <div>
                    <h3><?php echo htmlspecialchars($card['label']); ?></h3>
                    <div class="role-meta"><?php echo htmlspecialchars($card['count']); ?> active users</div>
                </div>
            </div>
            <div class="role-content">
                <p class="role-description"><?php echo htmlspecialchars($card['description']); ?></p>
                <div class="role-stats">
                    <span class="badge badge-role-count<?php echo !empty($card['featured']) ? ' badge-role-featured' : ''; ?>">
                        <i class="fas fa-users"></i>
                        <?php echo htmlspecialchars($card['count']); ?> users
                    </span>
                    <?php if (!empty($card['featured'])): ?>
                    <span class="badge badge-global">
                        <i class="fas fa-globe"></i>
                        Global view
                    </span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="role-footer">
                <?php if (!empty($card['route'])): ?>
                <a href="<?php echo $card['route']; ?>" class="btn <?php echo $card['theme'] === 'admin-role' ? 'btn-primary' : ($card['theme'] === 'ktt-role' ? 'btn-success' : ($card['theme'] === 'dept-role' ? 'btn-info' : 'btn-warning')); ?>">
                    <?php echo htmlspecialchars($card['button']); ?>
                </a>
                <?php else: ?>
                <div class="current-view-chip">
                    <i class="fas fa-shield-alt"></i>
                    <?php echo htmlspecialchars($card['button']); ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Recent Users Section -->
    <div class="recent-section">
        <h2>Recently Added Users</h2>
        <table class="table">
            <thead>
                <tr>
                    <th>Username</th>
                    <th>Full Name</th>
                    <th>Role</th>
                    <th>Date Created</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recent_users as $user): ?>
                <tr>
                    <td><?php echo htmlspecialchars($user['username']); ?></td>
                    <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                    <td>
                        <span class="role-badge role-<?php echo $user['role']; ?>">
                            <?php echo ucwords(str_replace('_', ' ', $user['role'])); ?>
                        </span>
                    </td>
                    <td><?php echo date('M d, Y H:i', strtotime($user['created_at'])); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>



<?php require_once dirname(__DIR__) . '/layouts/footer.php'; ?>
