<?php
$page_title = 'Superadmin Dashboard';
require_once '../../includes/config.php';
require_once '../../includes/db.php';
require_once '../../includes/auth.php';

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

include '../../includes/header.php';
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

<style>
/* ============================================================================
   SUPERADMIN DASHBOARD STYLES
   Modular, well-organized styling with clear separation of concerns
   ============================================================================ */

/* ---------- DESIGN SYSTEM: COLOR & TYPOGRAPHY VARIABLES ---------- */
:root {
    /* Color Palette */
    --color-bg-page:        #f6f8fb;
    --color-bg-card:        #ffffff;
    --color-text-primary:   #111827;
    --color-text-muted:     #6b7280;
    --color-text-light:     rgba(255, 255, 255, 0.9);
    --color-border:         #eef2f7;
    --color-border-light:   #f1f5f9;
    
    /* Brand Colors */
    --color-accent-primary: #ff6b35;
    --color-accent-primary-dark: #e55a2a;
    --color-accent-secondary: #f7b801;
    --color-accent-secondary-light: #f59e0b;
    
    /* Role Colors */
    --color-role-admin:     #ff6b35;
    --color-role-ktt:       #4ecdc4;
    --color-role-dept:      #45b7d1;
    --color-role-user:      #3498db;
    --color-role-superadmin: #f7b801;
    
    /* Spacing */
    --space-xs: 6px;
    --space-sm: 10px;
    --space-md: 14px;
    --space-lg: 18px;
    --space-xl: 26px;
    --space-2xl: 28px;
    
    /* Sizing */
    --size-icon-sm: 48px;
    --size-icon-md: 56px;
    
    /* Border Radius */
    --radius-sm: 8px;
    --radius-md: 10px;
    --radius-lg: 12px;
    --radius-xl: 14px;
    --radius-full: 999px;
    
    /* Shadows */
    --shadow-sm:  0 6px 18px rgba(15, 23, 42, 0.04);
    --shadow-md:  0 8px 20px rgba(13, 20, 30, 0.04);
    --shadow-lg:  0 10px 30px rgba(2, 6, 23, 0.04);
    --shadow-xl:  0 26px 50px rgba(2, 6, 23, 0.08);
    
    /* Transitions */
    --transition-fast: 0.18s ease;
}

/* ---------- GLOBAL STYLES ---------- */
* {
    box-sizing: border-box;
}

body {
    font-family: Inter, system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial;
    background-color: var(--color-bg-page);
    color: var(--color-text-primary);
}

/* ---------- DASHBOARD CONTAINER ---------- */
.dashboard-modern {
    max-width: 1200px;
    margin: var(--space-2xl) auto;
    padding: var(--space-2xl);
    min-height: 100vh;
}

/* ============================================================================
   WELCOME SECTION (HERO)
   ============================================================================ */
.welcome-section {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: var(--space-xl);
    padding: var(--space-xl) var(--space-2xl);
    border-radius: var(--radius-xl);
    color: #ffffff;
    box-shadow: var(--shadow-lg);
    margin-bottom: var(--space-2xl);
}

.superadmin-hero {
    background: linear-gradient(135deg, rgba(39, 67, 109, 0.98) 0%, rgba(24, 38, 63, 0.95) 100%);
    border-top: 3px solid rgba(247, 184, 1, 0.3);
}

.hero-content {
    display: flex;
    flex-direction: column;
    gap: var(--space-sm);
    flex: 1;
}

.welcome-section h1 {
    font-size: 2rem;
    font-weight: 800;
    margin: 0;
    letter-spacing: -0.5px;
    line-height: 1.2;
}

.welcome-section p {
    margin: 0;
    color: rgba(255, 255, 255, 0.85);
    max-width: 640px;
    font-size: 0.95rem;
    line-height: 1.5;
    font-weight: 400;
}

.hero-kicker {
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 1.2px;
    text-transform: uppercase;
    color: rgba(255, 255, 255, 0.75);
    display: inline-block;
    width: fit-content;
}

.hero-badge {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: var(--space-sm);
    padding: var(--space-md) var(--space-lg);
    border-radius: var(--radius-md);
    background: rgba(255, 255, 255, 0.08);
    font-weight: 600;
    font-size: 0.9rem;
    border: 1px solid rgba(255, 255, 255, 0.1);
    white-space: nowrap;
    backdrop-filter: blur(10px);
}

/* ============================================================================
   OVERVIEW CARDS (KEY METRICS)
   ============================================================================ */
.overview-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: var(--space-lg);
    margin: var(--space-lg) 0;
}

.overview-card {
    background-color: var(--color-bg-card);
    padding: var(--space-lg);
    border-radius: var(--radius-lg);
    border: 1px solid var(--color-border);
    box-shadow: var(--shadow-sm);
    display: flex;
    flex-direction: column;
    gap: var(--space-xs);
}

.overview-card-primary {
    border-left: 4px solid var(--color-accent-primary);
}

.overview-card-accent {
    border-left: 4px solid var(--color-accent-secondary);
}

.overview-label {
    font-size: 11px;
    font-weight: 700;
    color: var(--color-text-muted);
    text-transform: uppercase;
}

.overview-value {
    font-size: 1.8rem;
    font-weight: 800;
    color: var(--color-text-primary);
}

.overview-help {
    font-size: 13px;
    color: var(--color-text-muted);
}

/* ============================================================================
   STATISTICS CARDS (STAT-GRID)
   ============================================================================ */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: var(--space-lg);
    margin-bottom: var(--space-xl);
}

.stat-card {
    background-color: var(--color-bg-card);
    padding: var(--space-lg);
    border-radius: var(--radius-lg);
    display: flex;
    align-items: center;
    gap: var(--space-md);
    box-shadow: var(--shadow-md);
    transition: transform var(--transition-fast);
}

.stat-card:hover {
    transform: translateY(-4px);
}

.stat-icon {
    display: flex;
    align-items: center;
    justify-content: center;
    width: var(--size-icon-md);
    height: var(--size-icon-md);
    border-radius: 50%;
    color: #ffffff;
    font-size: 20px;
    flex-shrink: 0;
}

.stat-content h3 {
    margin: 0;
    font-size: 1.25rem;
    font-weight: 700;
}

.stat-content p {
    margin: 2px 0 0 0;
    color: var(--color-text-muted);
    font-size: 0.95rem;
}

/* ============================================================================
   SECTION HEADERS
   ============================================================================ */
.section-title {
    margin: 30px 0 var(--space-lg);
}

.section-title h2 {
    margin: 0;
    font-size: 1.25rem;
    font-weight: 700;
}

.section-title p {
    margin: var(--space-xs) 0 0 0;
    color: var(--color-text-muted);
}

/* ============================================================================
   ROLE ACCESS CARDS
   ============================================================================ */
.role-access-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: var(--space-xl);
    margin-bottom: var(--space-2xl);
}

.role-card {
    background-color: var(--color-bg-card);
    border-radius: var(--radius-xl);
    padding: var(--space-lg);
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    border: 1px solid var(--color-border);
    box-shadow: var(--shadow-lg);
    transition: transform var(--transition-fast), box-shadow var(--transition-fast);
}

.role-card:hover {
    transform: translateY(-6px);
    box-shadow: var(--shadow-xl);
}

.role-card-featured {
    border: 2px solid var(--color-accent-secondary);
}

.role-header {
    display: flex;
    gap: var(--space-md);
    align-items: center;
}

.role-icon {
    display: flex;
    align-items: center;
    justify-content: center;
    width: var(--size-icon-sm);
    height: var(--size-icon-sm);
    border-radius: var(--radius-md);
    color: #ffffff;
    font-size: 20px;
    flex-shrink: 0;
}

.role-icon-featured {
    width: 56px;
    height: 56px;
    border-radius: 50%;
}

.role-header h3 {
    margin: 0;
    font-size: 1.05rem;
    font-weight: 700;
}

.role-meta {
    font-size: 12px;
    color: var(--color-text-muted);
    font-weight: 600;
}

.role-content {
    margin-top: var(--space-md);
    color: var(--color-text-muted);
    flex: 1;
}

.role-description {
    margin: 0;
    line-height: 1.5;
}

.role-stats {
    margin-top: var(--space-md);
    display: flex;
    gap: var(--space-sm);
    align-items: center;
    flex-wrap: wrap;
}

.badge {
    display: inline-flex;
    align-items: center;
    gap: var(--space-xs);
    padding: var(--space-xs) var(--space-md);
    border-radius: var(--radius-full);
    background-color: #f3f4f6;
    color: var(--color-text-primary);
    font-weight: 600;
    font-size: 0.85rem;
}

.badge-role-count {
    background-color: #f3f4f6;
}

.badge-role-featured {
    background: linear-gradient(90deg, var(--color-accent-secondary), var(--color-accent-secondary-light));
    color: #ffffff;
}

.badge-global {
    background: linear-gradient(90deg, var(--color-accent-secondary), var(--color-accent-secondary-light));
    color: #ffffff;
}

.current-view-chip {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: var(--space-sm);
    padding: var(--space-md);
    border-radius: var(--radius-md);
    background: linear-gradient(90deg, var(--color-accent-secondary), var(--color-accent-secondary-light));
    color: #ffffff;
    font-weight: 700;
    text-align: center;
}

.role-footer {
    margin-top: var(--space-md);
}

.btn {
    display: inline-block;
    padding: var(--space-sm) var(--space-md);
    border-radius: var(--radius-sm);
    text-decoration: none;
    color: #ffffff;
    background-color: var(--color-accent-primary);
    border: none;
    font-weight: 700;
    cursor: pointer;
    transition: opacity var(--transition-fast);
}

.btn:hover {
    opacity: 0.9;
}

/* ============================================================================
   ROLE CARD ICONS - COLOR VARIANTS
   ============================================================================ */
.admin-role .role-icon {
    background: linear-gradient(180deg, var(--color-role-admin), var(--color-accent-primary-dark));
}

.ktt-role .role-icon {
    background: linear-gradient(180deg, var(--color-role-ktt), #3db8b0);
}

.dept-role .role-icon {
    background: linear-gradient(180deg, var(--color-role-dept), #36a0be);
}

.user-role .role-icon {
    background: linear-gradient(180deg, var(--color-role-user), #2b86c6);
}

.superadmin-role .role-icon {
    background: linear-gradient(180deg, var(--color-role-superadmin), var(--color-accent-secondary-light));
}

/* ============================================================================
   RECENT USERS SECTION & TABLE
   ============================================================================ */
.recent-section {
    background-color: var(--color-bg-card);
    padding: var(--space-xl);
    border-radius: var(--radius-lg);
    border: 1px solid var(--color-border);
    box-shadow: var(--shadow-md);
}

.recent-section h2 {
    margin: 0 0 var(--space-lg) 0;
    font-size: 1.25rem;
    font-weight: 700;
}

.table {
    width: 100%;
    border-collapse: collapse;
}

.table thead th {
    padding: var(--space-sm);
    text-align: left;
    font-weight: 700;
    border-bottom: 2px solid var(--color-border);
    color: var(--color-text-primary);
}

.table tbody td {
    padding: var(--space-sm);
    border-bottom: 1px solid var(--color-border-light);
    color: var(--color-text-muted);
}

.role-badge {
    display: inline-block;
    padding: var(--space-xs) var(--space-sm);
    border-radius: var(--radius-full);
    font-weight: 700;
    font-size: 0.85rem;
}

/* ============================================================================
   RESPONSIVE DESIGN - TABLET & MOBILE
   ============================================================================ */

/* Tablet: max-width 1000px */
@media (max-width: 1000px) {
    .welcome-section {
        padding: var(--space-lg) var(--space-xl);
        gap: var(--space-lg);
    }
    
    .overview-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .role-access-grid {
        grid-template-columns: 1fr;
    }
}

/* Mobile: max-width 640px */
@media (max-width: 640px) {
    .dashboard-modern {
        padding: var(--space-lg);
    }
    
    .welcome-section {
        flex-direction: column;
        align-items: flex-start;
        padding: var(--space-lg);
        gap: var(--space-md);
    }
    
    .welcome-section h1 {
        font-size: 1.5rem;
    }
    
    .hero-badge {
        align-self: flex-start;
        font-size: 0.85rem;
        padding: var(--space-sm) var(--space-md);
    }
    
    .overview-grid {
        grid-template-columns: 1fr;
    }
    
    .stats-grid {
        grid-template-columns: 1fr;
    }
    
    .role-card {
        padding: var(--space-md);
    }
}
</style>

<?php include '../../includes/footer.php'; ?>
