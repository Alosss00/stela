<?php
require_once dirname(__DIR__, 3) . '/bootstrap/app.php';

// Calculate base URL dynamically based on the calling script's location
$script_path = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));

// Determine how deep we are in the folder structure from the application root
$depth = 0;
if (preg_match('#/pages/(admin|user|dept|ktt)$#', $script_path)) {
    $depth = 2;
} elseif (preg_match('#/(api|utils|migrations)$#', $script_path)) {
    $depth = 1;
}

// Calculate base path by going up $depth levels
$base_path = $script_path;
for ($i = 0; $i < $depth; $i++) {
    $base_path = dirname($base_path);
}
// Normalize: remove trailing slash but keep single "/" if at root
$base_path = rtrim($base_path, '/');
if ($base_path === '' || $base_path === '\\') {
    $base_path = '';
}

// Define BASE_URL constant if not already defined
if (!defined('BASE_URL')) {
    define('BASE_URL', $base_path);
}

// Helper function to get the current page filename
if (!function_exists('get_current_page')) {
    function get_current_page() {
        return basename($_SERVER['PHP_SELF']);
    }
}
$current_page = get_current_page();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title . ' - ' : ''; ?><?php echo SITE_NAME; ?></title>
    <link rel="preconnect" href="https://cdnjs.cloudflare.com" crossorigin>
    <link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/style.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/language-switcher.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.bootstrap5.min.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/datatable-custom.css?v=<?php echo time(); ?>">

</head>
<body>
    
    <?php
        // Check if calling page wants to hide sidebar (used by superadmin pages)
        $hide_sidebar = isset($hide_sidebar) ? $hide_sidebar : false;
    ?>
    <div class="wrapper<?php echo $hide_sidebar ? ' sidebar-hidden' : ''; ?>">
        <?php if (!$hide_sidebar): ?>
        <nav class="sidebar">
            <div class="sidebar-header">
                <h3>STELA</h3>    
                <?php if (($_SESSION['role'] == 'admin' || $_SESSION['role'] == 'ktt' || $_SESSION['role'] == 'superadmin') && isset($_SESSION['full_name']) && $_SESSION['full_name']): ?>
                    <small class="text-muted"><?php echo htmlspecialchars($_SESSION['full_name']); ?></small>
                <?php elseif (isset($_SESSION['company_name']) && $_SESSION['company_name']): ?>
                    <small class="text-muted"><?php echo htmlspecialchars($_SESSION['company_name']); ?></small>
                <?php elseif (isset($_SESSION['department']) && $_SESSION['department']): ?>
                    <small class="text-muted">Dept: <?php echo htmlspecialchars($_SESSION['department']); ?></small>
                <?php endif; ?>
                <span class="badge badge-<?php echo $_SESSION['role']; ?>"><?php echo strtoupper(str_replace('_', ' ', $_SESSION['role'])); ?></span>
            </div>
            
            <ul class="sidebar-menu">
                <?php if ($_SESSION['role'] == 'user' && !hasDepartment()): ?>
                    <li>
                        <a href="<?php echo BASE_URL; ?>/pages/user/dashboard.php" class="<?php echo $current_page == 'dashboard.php' ? 'active' : ''; ?>">
                            <i class="fas fa-home"></i> <span data-lang="dashboard">Dashboard</span>
                        </a>
                      <li>
                        <a href="<?php echo BASE_URL; ?>/pages/user/employees.php" class="<?php echo $current_page == 'employees.php' || $current_page == 'employee_detail.php' ? 'active' : ''; ?>">
                            <i class="fas fa-users"></i> <span data-lang="request">Request</span>
                        </a>
                    </li>
                    <li>
                        <a href="<?php echo BASE_URL; ?>/pages/user/appointments.php" class="<?php echo $current_page == 'appointments.php' || $current_page == 'appointment_detail.php' ? 'active' : ''; ?>">
                            <i class="fas fa-file-alt"></i> <span data-lang="assign-letter">Assign Letter</span>
                        </a>
                    </li>
                    <li>
                        <a href="<?php echo BASE_URL; ?>/pages/user/certificate_status.php" class="<?php echo $current_page == 'certificate_status.php' ? 'active' : ''; ?>">
                            <i class="fas fa-id-card"></i> <span data-lang="certificate-status">Status Certification</span>
                        </a>
                    </li>
                    <li>
                        <a href="<?php echo BASE_URL; ?>/pages/user/employees_status.php" class="<?php echo $current_page == 'employees_status.php' ? 'active' : ''; ?>">
                            <i class="fas fa-id-card"></i> <span data-lang="employee-status">Status Employee</span>
                        </a>
                    </li>
                    <li>
                        <a href="<?php echo BASE_URL; ?>/pages/user/reports.php" class="<?php echo $current_page == 'reports.php' ? 'active' : ''; ?>">
                            <i class="fas fa-chart-bar"></i> <span data-lang="reports">Reports</span>
                        </a>
                    </li>
                     

                <?php elseif (($_SESSION['role'] == 'user' && hasDepartment()) || $_SESSION['role'] == 'department_user'): ?>
                    <li>
                        <a href="<?php echo BASE_URL; ?>/pages/dept/dashboard.php" class="<?php echo $current_page == 'dashboard.php' ? 'active' : ''; ?>">
                            <i class="fas fa-home"></i> <span data-lang="dashboard">Dashboard</span>
                        </a>
                    </li>
                    <li>
                        <a href="<?php echo BASE_URL; ?>/pages/dept/employees.php" class="<?php echo $current_page == 'employees.php' || $current_page == 'employee_detail.php' ? 'active' : ''; ?>">
                            <i class="fas fa-users"></i> <span data-lang="request">Request</span>
                        </a>
                    </li>
                    <li>
                        <a href="<?php echo BASE_URL; ?>/pages/dept/appointments.php" class="<?php echo $current_page == 'appointments.php' ? 'active' : ''; ?>">
                            <i class="fas fa-file-alt"></i> <span data-lang="assign-letter">Assign Letter</span>
                        </a>
                    </li>
                    <li>
                        <a href="<?php echo BASE_URL; ?>/pages/dept/certificate_status.php" class="<?php echo $current_page == 'certificate_status.php' ? 'active' : ''; ?>">
                            <i class="fas fa-id-card"></i> <span data-lang="certificate-status">Status Certification</span>
                        </a>
                    </li>
                    <li>
                        <a href="<?php echo BASE_URL; ?>/pages/dept/employees_status.php" class="<?php echo $current_page == 'employees_status.php' ? 'active' : ''; ?>">
                            <i class="fas fa-id-card"></i> <span data-lang="employee-status">Status Employee</span>
                        </a>
                    </li>
                    <li>
                        <a href="<?php echo BASE_URL; ?>/pages/dept/reports.php" class="<?php echo $current_page == 'reports.php' ? 'active' : ''; ?>">
                            <i class="fas fa-chart-bar"></i> <span data-lang="reports">Reports</span>
                        </a>
                    </li>

                <?php else: ?>
                    <?php if ($_SESSION['role'] == 'admin' || $_SESSION['role'] == 'superadmin'): ?>
                    <li>
                        <a href="<?php echo BASE_URL; ?>/pages/admin/dashboard.php" class="<?php echo $current_page == 'dashboard.php' ? 'active' : ''; ?>">
                            <i class="fas fa-home"></i> <span data-lang="dashboard">Dashboard</span>
                        </a>
                    </li>
                    <?php endif; ?>

                    <?php if ($_SESSION['role'] == 'admin' || $_SESSION['role'] == 'superadmin'): ?>
                    <li>
                        <a href="<?php echo BASE_URL; ?>/pages/admin/positions.php" class="<?php echo $current_page == 'positions.php' ? 'active' : ''; ?>">
                            <i class="fas fa-briefcase"></i> <span data-lang="competencies">Competencies</span>
                        </a>
                    </li>

                    <li>
                        <a href="<?php echo BASE_URL; ?>/pages/admin/certifications.php" class="<?php echo $current_page == 'certifications.php' ? 'active' : ''; ?>">
                            <i class="fas fa-certificate"></i> <span data-lang="certifications">Certifications</span>
                        </a>
                    </li>

                    <li>
                        <a href="<?php echo BASE_URL; ?>/pages/admin/supervision_areas.php" class="<?php echo $current_page == 'supervision_areas.php' ? 'active' : ''; ?>">
                            <i class="fas fa-map-marked-alt"></i> <span data-lang="supervision-areas">Supervision Areas</span>
                        </a>
                    </li>
                    <?php endif; ?>

                    <?php if ($_SESSION['role'] == 'admin' || $_SESSION['role'] == 'superadmin'): ?>
                    <li>
                        <a href="<?php echo BASE_URL; ?>/pages/admin/employees.php" class="<?php echo $current_page == 'employees.php' ? 'active' : ''; ?>">
                            <i class="fas fa-users"></i> <span data-lang="request">Request</span>
                        </a>
                    </li>

                    <li>
                        <a href="<?php echo BASE_URL; ?>/pages/admin/appointments.php" class="<?php echo $current_page == 'appointments.php' ? 'active' : ''; ?>">
                            <i class="fas fa-file-alt"></i> <span data-lang="assign-letter">Assign Letter</span>
                        </a>
                    </li>
                    <li>
                        <a href="<?php echo BASE_URL; ?>/pages/admin/certificate_status.php" class="<?php echo $current_page == 'certificate_status.php' ? 'active' : ''; ?>">
                            <i class="fas fa-id-card"></i> <span data-lang="certificate-status">Status Certification</span>
                        </a>
                    </li>
                    <li>
                        <a href="<?php echo BASE_URL; ?>/pages/admin/employees_status.php" class="<?php echo $current_page == 'employees_status.php' ? 'active' : ''; ?>">
                            <i class="fas fa-id-card"></i> <span data-lang="employee-status">Status Employee</span>
                        </a> 
                    </li>
                    <?php endif; ?>

                    <?php if ($_SESSION['role'] == 'ktt' || $_SESSION['role'] == 'superadmin'): ?>
                    <li>
                        <a href="<?php echo BASE_URL; ?>/pages/ktt/approval.php" class="<?php echo $current_page == 'approval.php' ? 'active' : ''; ?>">
                            <i class="fas fa-check-circle"></i> <span data-lang="approval-ktt">Approval KTT</span>
                        </a>
                    </li>
                    <?php endif; ?>

                    <?php if ($_SESSION['role'] == 'admin' || $_SESSION['role'] == 'superadmin'): ?>
                    <li>
                        <a href="<?php echo BASE_URL; ?>/pages/admin/reports.php" class="<?php echo $current_page == 'reports.php' ? 'active' : ''; ?>">
                            <i class="fas fa-chart-bar"></i> <span data-lang="reports">Reports</span>
                        </a>
                    </li>
                    <?php endif; ?>

                    <?php if ($_SESSION['role'] == 'admin' || $_SESSION['role'] == 'superadmin'): ?>
                    <li>
                        <a href="<?php echo BASE_URL; ?>/pages/admin/change_password.php" class="<?php echo $current_page == 'change_password.php' ? 'active' : ''; ?>">
                            <i class="fas fa-cog"></i> <span data-lang="settings">Settings</span>
                        </a>
                    </li>
                    <?php endif; ?>
                <?php endif; ?>
  
                <li>
                    <a href="<?php echo BASE_URL; ?>/logout.php" class="logout">
                        <i class="fas fa-sign-out-alt"></i> <span data-lang="logout">Logout</span>
                    </a>
                </li>
            </ul>
        </nav>
        <?php endif; // Close hide_sidebar conditional ?>
        
        <div class="main-content">
            <div class="topbar">
                <div class="topbar-left">
                        <?php 
                        $effective_lang_key = isset($page_title_lang) ? $page_title_lang : strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '-', $page_title ?? 'dashboard'), '-'));
                        ?>
                        <h2 data-lang="<?php echo htmlspecialchars($effective_lang_key); ?>"><?php echo isset($page_title) ? htmlspecialchars($page_title) : 'Dashboard'; ?></h2>
                </div>
                <div class="topbar-right topbar-meta-group">
                    <div class="topbar-meta-chip topbar-date-chip" aria-label="Current date">
                        <i class="fas fa-calendar-alt"></i>
                        <span class="date"><?php echo date('d F Y'); ?></span>
                    </div>
                    <?php if (empty($hide_language_selector)): ?>
                    <div class="language-dropdown topbar-meta-chip topbar-language-chip">
                        <button id="languageToggle" class="language-toggle-btn" type="button" aria-label="Change language">
                            <span class="lang-text">ID</span>
                            <i class="fas fa-chevron-down" style="font-size: 10px;"></i>
                        </button>
                        <div class="language-dropdown-menu">
                            <div class="dropdown-item" data-lang-code="id">
                                <span>ID</span>
                            </div>
                            <div class="dropdown-item" data-lang-code="en">
                                <span>EN</span>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="content">
