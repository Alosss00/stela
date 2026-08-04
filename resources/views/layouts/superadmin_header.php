<?php
require_once dirname(__DIR__, 3) . '/bootstrap/app.php';

// Calculate base URL dynamically based on the calling script's location
$script_path = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));

$depth = 0;
if (preg_match('#/pages/(admin|user|dept|ktt)$#', $script_path) || preg_match('#/superadmin$#', $script_path)) {
    $depth = 2;
} elseif (preg_match('#/(api|utils|migrations)$#', $script_path)) {
    $depth = 1;
}

$base_path = $script_path;
for ($i = 0; $i < $depth; $i++) {
    $base_path = dirname($base_path);
}
$base_path = rtrim($base_path, '/');
if ($base_path === '' || $base_path === '\\') {
    $base_path = '';
}

if (!defined('BASE_URL')) {
    define('BASE_URL', $base_path);
}

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
    <title><?php echo isset($page_title) ? $page_title . ' - ' : ''; ?>Superadmin | <?php echo SITE_NAME; ?></title>
    <link rel="preconnect" href="https://cdnjs.cloudflare.com" crossorigin>
    <link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/style.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/language-switcher.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.bootstrap5.min.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/datatable-custom.css?v=<?php echo time(); ?>">
    
    <style>
        .superadmin-sidebar {
            background-color: #212529 !important; /* Dark mode for Superadmin */
        }
        .superadmin-sidebar .sidebar-menu a {
            color: #adb5bd !important;
        }
        .superadmin-sidebar .sidebar-menu a:hover, .superadmin-sidebar .sidebar-menu a.active {
            color: #fff !important;
            background-color: #343a40 !important;
            border-left: 4px solid #ffc107 !important;
        }
        .superadmin-sidebar .sidebar-header {
            background-color: #1a1e21 !important;
            border-bottom: 1px solid #343a40;
            color: #fff;
        }
        .superadmin-badge {
            background-color: #ffc107;
            color: #000;
        }
    </style>
</head>
<body>
    <div class="wrapper">
        <nav class="sidebar superadmin-sidebar">
            <div class="sidebar-header">
                <h3>STELA <i class="fas fa-crown text-warning"></i></h3>    
                <small class="text-muted"><?php echo htmlspecialchars($_SESSION['full_name']); ?></small>
                <span class="badge superadmin-badge">SUPER ADMIN</span>
            </div>
            
            <ul class="sidebar-menu">
                <li>
                    <a href="<?php echo BASE_URL; ?>/superadmin/dashboard.php" class="<?php echo $current_page == 'dashboard.php' ? 'active' : ''; ?>">
                        <i class="fas fa-tachometer-alt"></i> <span>Dashboard</span>
                    </a>
                </li>
                <li class="menu-header" style="color:#6c757d; font-size:12px; padding: 10px 20px; text-transform:uppercase;">Access Control</li>
                <li>
                    <a href="<?php echo BASE_URL; ?>/superadmin/users.php" class="<?php echo $current_page == 'users.php' ? 'active' : ''; ?>">
                        <i class="fas fa-users-cog"></i> <span>User Management</span>
                    </a>
                </li>
                <li>
                    <a href="<?php echo BASE_URL; ?>/superadmin/roles_permissions.php" class="<?php echo $current_page == 'roles_permissions.php' ? 'active' : ''; ?>">
                        <i class="fas fa-user-shield"></i> <span>Role & Permissions</span>
                    </a>
                </li>
                
                <li class="menu-header" style="color:#6c757d; font-size:12px; padding: 10px 20px; text-transform:uppercase;">Organization</li>
                <li>
                    <a href="<?php echo BASE_URL; ?>/superadmin/companies.php" class="<?php echo $current_page == 'companies.php' ? 'active' : ''; ?>">
                        <i class="fas fa-building"></i> <span>Companies</span>
                    </a>
                </li>
                <li>
                    <a href="<?php echo BASE_URL; ?>/superadmin/departments.php" class="<?php echo $current_page == 'departments.php' ? 'active' : ''; ?>">
                        <i class="fas fa-network-wired"></i> <span>Departments</span>
                    </a>
                </li>
                
                <li class="menu-header" style="color:#6c757d; font-size:12px; padding: 10px 20px; text-transform:uppercase;">Master Data</li>
                <li>
                    <a href="<?php echo BASE_URL; ?>/superadmin/master_data.php" class="<?php echo $current_page == 'master_data.php' ? 'active' : ''; ?>">
                        <i class="fas fa-database"></i> <span>Master Data</span>
                    </a>
                </li>

                <li class="menu-header" style="color:#6c757d; font-size:12px; padding: 10px 20px; text-transform:uppercase;">System Maintenance</li>
                <li>
                    <a href="<?php echo BASE_URL; ?>/superadmin/settings.php" class="<?php echo $current_page == 'settings.php' ? 'active' : ''; ?>">
                        <i class="fas fa-cogs"></i> <span>System Settings</span>
                    </a>
                </li>
                <li>
                    <a href="<?php echo BASE_URL; ?>/superadmin/monitoring_logs.php" class="<?php echo $current_page == 'monitoring_logs.php' ? 'active' : ''; ?>">
                        <i class="fas fa-clipboard-list"></i> <span>Audit & Monitoring Logs</span>
                    </a>
                </li>
                <li>
                    <a href="<?php echo BASE_URL; ?>/superadmin/elasticsearch_manage.php" class="<?php echo $current_page == 'elasticsearch_manage.php' ? 'active' : ''; ?>">
                        <i class="fas fa-search"></i> <span>Elasticsearch Config</span>
                    </a>
                </li>
                <li>
                    <a href="<?php echo BASE_URL; ?>/superadmin/backup_restore.php" class="<?php echo $current_page == 'backup_restore.php' ? 'active' : ''; ?>">
                        <i class="fas fa-hdd"></i> <span>Backup & Restore</span>
                    </a>
                </li>

                <li class="menu-header" style="color:#6c757d; font-size:12px; padding: 10px 20px; text-transform:uppercase;">Account</li>
                <li>
                    <a href="<?php echo BASE_URL; ?>/logout.php" class="logout text-danger">
                        <i class="fas fa-sign-out-alt"></i> <span>Logout</span>
                    </a>
                </li>
            </ul>
        </nav>
        
        <div class="main-content" style="background-color:#f4f6f9;">
            <div class="topbar">
                <div class="topbar-left">
                    <h2><?php echo isset($page_title) ? htmlspecialchars($page_title) : 'Superadmin Dashboard'; ?></h2>
                </div>
                <div class="topbar-right topbar-meta-group">
                    <div class="topbar-meta-chip topbar-date-chip" aria-label="Current date">
                        <i class="fas fa-calendar-alt"></i>
                        <span class="date"><?php echo date('d F Y'); ?></span>
                    </div>
                </div>
            </div>
            
            <div class="content p-4">
