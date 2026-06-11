<?php
require_once __DIR__ . '/config.php';

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
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/style.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/language-switcher.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <!-- Sidebar enabled for all roles -->
    <style>
        .topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            padding: 16px 24px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            background: #3f4f5c;
            color: #f8fafc;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.15);
            position: relative;
            z-index: 20;
            min-height: 64px;
        }
        .topbar-left {
            display: flex;
            align-items: center;
            flex: 1;
            min-width: 200px;
        }
        .topbar-left h2 {
            margin: 0;
            font-size: 1.375rem;
            line-height: 1.1;
            color: #f8fafc;
            font-weight: 700;
            letter-spacing: -0.3px;
        }
        .topbar-right {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: nowrap;
        }
        .topbar-meta-group {
            display: inline-flex;
            align-items: center;
            gap: 12px;
            flex-wrap: nowrap;
        }
        .topbar-meta-chip {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            height: 42px;
            padding: 0 16px;
            border: 1px solid rgba(255, 255, 255, 0.12);
            border-radius: 21px;
            background: rgba(255, 255, 255, 0.06);
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.08);
            transition: all 0.2s ease;
        }
        .topbar-meta-chip:hover {
            background: rgba(255, 255, 255, 0.1);
            border-color: rgba(255, 255, 255, 0.16);
        }
        .topbar-date-chip {
            color: #f8fafc;
            font-weight: 600;
            font-size: 0.9rem;
            white-space: nowrap;
        }
        .topbar-date-chip .date {
            color: #f8fafc;
        }
        .topbar-date-chip i {
            color: #fbbf24;
            font-size: 0.95rem;
        }
        .topbar-language-chip {
            padding: 0;
            overflow: visible;
            border: none;
            background: transparent;
            box-shadow: none;
        }
        .language-toggle-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            height: 42px;
            padding: 0 16px;
            border: 1px solid rgba(255, 255, 255, 0.12);
            border-radius: 21px;
            background: rgba(255, 255, 255, 0.06);
            color: #f8fafc;
            font-weight: 700;
            font-size: 0.9rem;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .language-toggle-btn:hover {
            background: rgba(255, 255, 255, 0.1);
            border-color: rgba(255, 255, 255, 0.16);
        }
        .language-toggle-btn i {
            font-size: 0.75rem;
            opacity: 0.8;
        }
        .language-dropdown {
            position: relative;
        }
        .language-dropdown-menu {
            position: absolute;
            top: calc(100% + 10px);
            right: 0;
            min-width: 100px;
            padding: 8px;
            border: 1px solid #dbe3ee;
            border-radius: 12px;
            box-shadow: 0 18px 40px rgba(15, 23, 42, 0.14);
            background: #ffffff;
            z-index: 1000;
        }
        .language-dropdown-menu .dropdown-item {
            border-radius: 10px;
            padding: 10px 14px;
            font-weight: 600;
            font-size: 0.9rem;
            cursor: pointer;
            transition: background 0.2s ease;
        }
        .language-dropdown-menu .dropdown-item:hover {
            background: #f1f5f9;
        }
        @media (max-width: 640px) {
            .topbar {
                padding: 14px 16px;
                gap: 12px;
                min-height: 58px;
            }
            .topbar-left {
                flex: 1;
                min-width: auto;
            }
            .topbar-left h2 {
                font-size: 1.2rem;
                line-height: 1;
            }
            .topbar-right,
            .topbar-meta-group {
                gap: 8px;
                flex-wrap: nowrap;
                width: auto;
            }
            .topbar-meta-chip,
            .language-toggle-btn {
                height: 38px;
                padding: 0 12px;
                font-size: 0.85rem;
            }
            .topbar-meta-chip i,
            .language-toggle-btn i {
                font-size: 0.8rem;
            }
        }
    </style>
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
                    <!-- Menu untuk Company Users (Contractor) -->
                    <li>
                        <a href="<?php echo BASE_URL; ?>/pages/user/dashboard.php" class="<?php echo $current_page == 'dashboard.php' ? 'active' : ''; ?>">
                            <i class="fas fa-home"></i> <span data-lang="dashboard">Dashboard</span>
                        </a>
                    </li>
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
                        <a href="<?php echo BASE_URL; ?>/pages/user/expired_certificates.php" class="<?php echo $current_page == 'expired_certificates.php' ? 'active' : ''; ?>">
                            <i class="fas fa-chart-bar"></i> <span data-lang="reports">Expired Certificates</span>
                        </a>
                    </li>
                    <li>
                        <a href="<?php echo BASE_URL; ?>/pages/user/reports.php" class="<?php echo $current_page == 'reports.php' ? 'active' : ''; ?>">
                            <i class="fas fa-chart-bar"></i> <span data-lang="reports">Reports</span>
                        </a>
                    </li>
                     

                <?php elseif (($_SESSION['role'] == 'user' && hasDepartment()) || $_SESSION['role'] == 'department_user'): ?>
                    <!-- Menu untuk Department Users -->
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
                        <a href="<?php echo BASE_URL; ?>/pages/user/expired_certificates.php" class="<?php echo $current_page == 'expired_certificates.php' ? 'active' : ''; ?>">
                            <i class="fas fa-chart-bar"></i> <span data-lang="reports">Expired Certificates</span>
                        </a>
                    </li>
                    <li>
                        <a href="<?php echo BASE_URL; ?>/pages/dept/reports.php" class="<?php echo $current_page == 'reports.php' ? 'active' : ''; ?>">
                            <i class="fas fa-chart-bar"></i> <span data-lang="reports">Reports</span>
                        </a>
                    </li>

                <?php else: ?>
                    <!-- Menu untuk Admin dan KTT -->
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
                        <a href="<?php echo BASE_URL; ?>/pages/user/expired_certificates.php" class="<?php echo $current_page == 'expired_certificates.php' ? 'active' : ''; ?>">
                            <i class="fas fa-chart-bar"></i> <span data-lang="reports">Expired Certificates</span>
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
                        <?php if (isset($page_title_lang)): ?>
                            <h2 data-lang="<?php echo htmlspecialchars($page_title_lang); ?>"><?php echo isset($page_title) ? htmlspecialchars($page_title) : 'Dashboard'; ?></h2>
                        <?php else: ?>
                            <h2><?php echo isset($page_title) ? htmlspecialchars($page_title) : 'Dashboard'; ?></h2>
                        <?php endif; ?>
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

