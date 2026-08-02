<?php
$page_title = 'Superadmin Dashboard';
require_once dirname(__DIR__, 3) . '/bootstrap/app.php';
require_once dirname(__DIR__, 3) . '/app/Helpers/auth_helper.php';

// Check if user is superadmin
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . AUTH_BASE_URL . '/index.php');
    exit();
}

if ($_SESSION['role'] != 'superadmin') {
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

// Add is_active column if it doesn't exist
$columnExists = false;
$checkResult = $db->query("SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = 'mining_appointment' AND TABLE_NAME = 'users' AND COLUMN_NAME = 'is_active'");
if ($checkResult && $checkResult->num_rows > 0) {
    $columnExists = true;
} else {
    $db->query("ALTER TABLE users ADD COLUMN is_active tinyint(1) DEFAULT 1");
    $columnExists = true;
}

function safeQuery($db, $queryWith, $queryWithout) {
    $result = $db->query($queryWith);
    if ($result === false) {
        $result = $db->query($queryWithout);
    }
    return $result;
}

// Get statistics for each role
$stats = [];

$result = safeQuery($db, 
    "SELECT COUNT(*) as count FROM users WHERE role = 'admin' AND is_active = 1",
    "SELECT COUNT(*) as count FROM users WHERE role = 'admin'"
);
$stats['admin'] = ($result && ($row = $result->fetch_assoc())) ? $row['count'] : 0;

$result = safeQuery($db,
    "SELECT COUNT(*) as count FROM users WHERE role = 'ktt' AND is_active = 1",
    "SELECT COUNT(*) as count FROM users WHERE role = 'ktt'"
);
$stats['ktt'] = ($result && ($row = $result->fetch_assoc())) ? $row['count'] : 0;

$result = safeQuery($db,
    "SELECT COUNT(*) as count FROM users WHERE role = 'user' AND is_active = 1",
    "SELECT COUNT(*) as count FROM users WHERE role = 'user'"
);
$stats['user'] = ($result && ($row = $result->fetch_assoc())) ? $row['count'] : 0;

$result = safeQuery($db,
    "SELECT COUNT(*) as count FROM users WHERE role = 'department_user' AND is_active = 1",
    "SELECT COUNT(*) as count FROM users WHERE role = 'department_user'"
);
$stats['department_user'] = ($result && ($row = $result->fetch_assoc())) ? $row['count'] : 0;

$result = safeQuery($db,
    "SELECT COUNT(*) as count FROM users WHERE role = 'superadmin' AND is_active = 1",
    "SELECT COUNT(*) as count FROM users WHERE role = 'superadmin'"
);
$stats['superadmin'] = ($result && ($row = $result->fetch_assoc())) ? $row['count'] : 0;

$result = @$db->query("SELECT COUNT(*) as count FROM appointments WHERE is_active = 1");
if (!$result) {
    $result = @$db->query("SELECT COUNT(*) as count FROM appointments");
}
$total_appointments = ($result && ($row = $result->fetch_assoc())) ? $row['count'] : 0;

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
        'theme' => 'primary'
    ],
    [
        'key' => 'ktt',
        'label' => 'KTT Approval',
        'description' => 'Review appointment letters and handle approval workflow.',
        'icon' => 'fa-check-circle',
        'route' => '../set_role_redirect.php?role=ktt',
        'count' => $stats['ktt'],
        'button' => 'Open KTT Dashboard',
        'theme' => 'success'
    ],
    [
        'key' => 'department_user',
        'label' => 'Department User',
        'description' => 'Monitor department-based requests and appointment data.',
        'icon' => 'fa-building',
        'route' => '../set_role_redirect.php?role=department_user',
        'count' => $stats['department_user'],
        'button' => 'Open Department Dashboard',
        'theme' => 'accent'
    ],
    [
        'key' => 'user',
        'label' => 'Company User',
        'description' => 'Create and track company appointment letters globally.',
        'icon' => 'fa-user',
        'route' => '../set_role_redirect.php?role=user',
        'count' => $stats['user'],
        'button' => 'Open User Dashboard',
        'theme' => 'warning'
    ],
    [
        'key' => 'superadmin',
        'label' => 'Superadmin',
        'description' => 'Full system control with access to all dashboards and all data.',
        'icon' => 'fa-crown',
        'route' => null,
        'count' => $stats['superadmin'],
        'button' => 'Current Global View',
        'theme' => 'danger',
        'featured' => true
    ]
];

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

$hide_sidebar = true;
$hide_language_selector = true;

require_once dirname(__DIR__) . '/layouts/header.php';
?>

<div class="stela-dash-pro">
    <!-- Page Header Bar -->
    <div class="stela-dash-header">
        <div class="stela-dash-title-group">
            <h1>Superadmin Dashboard</h1>
            <p>Global Control Center • Access every role, dashboard, and data scope from one view.</p>
        </div>
        <div class="stela-dash-header-actions">
            <div class="stela-dash-date-pill">
                <i class="fas fa-calendar-alt"></i>
                <span><?php echo date('d F Y'); ?></span>
            </div>
        </div>
    </div>

    <!-- 4 Stat Cards Row Grid -->
    <div class="stela-stat-grid">
        <div class="stela-stat-card">
            <div class="stela-stat-top">
                <div class="stela-stat-icon-box primary">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stela-stat-meta">
                    <div class="stela-stat-label">Active Users</div>
                    <div class="stela-stat-value"><?php echo $total_active_users; ?></div>
                </div>
            </div>
            <div class="stela-stat-bottom">
                <span class="stela-stat-badge up"><i class="fas fa-arrow-up"></i> All Registered Roles</span>
                <svg class="stela-sparkline-svg" viewBox="0 0 60 22" fill="none"><path d="M2 16L16 18L30 8L44 12L58 4" stroke="#4f46e5" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </div>
        </div>

        <div class="stela-stat-card">
            <div class="stela-stat-top">
                <div class="stela-stat-icon-box accent">
                    <i class="fas fa-shield-alt"></i>
                </div>
                <div class="stela-stat-meta">
                    <div class="stela-stat-label">Visible Roles</div>
                    <div class="stela-stat-value"><?php echo $total_roles; ?></div>
                </div>
            </div>
            <div class="stela-stat-bottom">
                <span class="stela-stat-badge neutral"><i class="fas fa-layer-group"></i> Unlocked System Scopes</span>
                <svg class="stela-sparkline-svg" viewBox="0 0 60 22" fill="none"><path d="M2 14L16 6L30 14L44 8L58 12" stroke="#0284c7" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </div>
        </div>

        <div class="stela-stat-card">
            <div class="stela-stat-top">
                <div class="stela-stat-icon-box success">
                    <i class="fas fa-user-tie"></i>
                </div>
                <div class="stela-stat-meta">
                    <div class="stela-stat-label">Global Employees</div>
                    <div class="stela-stat-value"><?php echo $total_employees; ?></div>
                </div>
            </div>
            <div class="stela-stat-bottom">
                <span class="stela-stat-badge up"><i class="fas fa-check"></i> Total Registered</span>
                <svg class="stela-sparkline-svg" viewBox="0 0 60 22" fill="none"><path d="M2 18L15 12L28 15L42 5L58 12" stroke="#16a34a" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </div>
        </div>

        <div class="stela-stat-card">
            <div class="stela-stat-top">
                <div class="stela-stat-icon-box warning">
                    <i class="fas fa-file-signature"></i>
                </div>
                <div class="stela-stat-meta">
                    <div class="stela-stat-label">Global Appointments</div>
                    <div class="stela-stat-value"><?php echo $total_appointments; ?></div>
                </div>
            </div>
            <div class="stela-stat-bottom">
                <span class="stela-stat-badge warn"><i class="fas fa-file-alt"></i> System Records</span>
                <svg class="stela-sparkline-svg" viewBox="0 0 60 22" fill="none"><path d="M2 6L16 12L30 10L44 18L58 14" stroke="#d97706" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </div>
        </div>
    </div>

    <!-- Role Access Section -->
    <div class="stela-dash-card">
        <div class="stela-dash-card-header">
            <div class="stela-dash-card-title">
                <i class="fas fa-th-large"></i>
                <span>Role Dashboards Quick Access</span>
            </div>
            <span class="stela-status-pill neutral">Superadmin Global Mode</span>
        </div>

        <div style="padding: 24px;">
            <div class="stela-role-grid">
                <?php foreach ($role_cards as $card): 
                    $bgClass = $card['theme'] === 'primary' ? '#4f46e5' : ($card['theme'] === 'success' ? '#16a34a' : ($card['theme'] === 'warning' ? '#d97706' : ($card['theme'] === 'accent' ? '#0284c7' : '#dc2626')));
                    $btnClass = $card['theme'] === 'primary' ? 'btn-primary' : ($card['theme'] === 'success' ? 'btn-success' : ($card['theme'] === 'warning' ? 'btn-warning' : ($card['theme'] === 'accent' ? 'btn-info' : 'btn-secondary')));
                ?>
                <div class="stela-role-card">
                    <div>
                        <div class="stela-role-header">
                            <div class="stela-role-icon-title">
                                <div class="stela-role-icon" style="background-color: <?php echo $bgClass; ?>;">
                                    <i class="fas <?php echo $card['icon']; ?>"></i>
                                </div>
                                <div>
                                    <h3 class="stela-role-title"><?php echo htmlspecialchars($card['label']); ?></h3>
                                    <span style="font-size: 12px; color: #64748b; font-weight: 600;"><?php echo htmlspecialchars($card['count']); ?> Active Users</span>
                                </div>
                            </div>
                        </div>
                        <p class="stela-role-desc"><?php echo htmlspecialchars($card['description']); ?></p>
                    </div>
                    <div>
                        <?php if (!empty($card['route'])): ?>
                        <a href="<?php echo $card['route']; ?>" class="stela-role-btn btn <?php echo $btnClass; ?>">
                            <span><?php echo htmlspecialchars($card['button']); ?></span> <i class="fas fa-arrow-right"></i>
                        </a>
                        <?php else: ?>
                        <div class="stela-role-btn btn btn-outline-secondary" style="cursor: default; opacity: 0.85;">
                            <i class="fas fa-shield-alt"></i> <span><?php echo htmlspecialchars($card['button']); ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Recently Added Users Table Card -->
    <div class="stela-dash-card">
        <div class="stela-dash-card-header">
            <div class="stela-dash-card-title">
                <i class="fas fa-user-plus"></i>
                <span>Recently Added Users</span>
            </div>
        </div>

        <?php if (!empty($recent_users)): ?>
        <div class="table-responsive">
            <table class="stela-dash-table">
                <thead>
                    <tr>
                        <th>Username</th>
                        <th>Full Name</th>
                        <th>Role</th>
                        <th>Date Created</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_users as $user): 
                        $rolePillClass = $user['role'] === 'superadmin' ? 'danger' : ($user['role'] === 'admin' ? 'primary' : ($user['role'] === 'ktt' ? 'success' : 'neutral'));
                    ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($user['username']); ?></strong></td>
                        <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                        <td>
                            <span class="stela-status-pill <?php echo $rolePillClass; ?>">
                                <?php echo ucwords(str_replace('_', ' ', $user['role'])); ?>
                            </span>
                        </td>
                        <td><?php echo date('M d, Y H:i', strtotime($user['created_at'])); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div class="empty-state" style="padding: 32px; text-align: center; color: #94a3b8;">
            <i class="fas fa-users-slash" style="font-size: 32px; margin-bottom: 8px;"></i>
            <p>No user records found.</p>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once dirname(__DIR__) . '/layouts/footer.php'; ?>
