<?php
require_once 'includes/config.php';
require_once 'includes/db.php';

function ensureSuperadminAccount($db) {
    $username = 'superadmin';
    $passwordHash = '$2y$10$3IwZtgL1w3AEE4X05AP2DuzxuMiyt6HKRTPxKJl9UCyz7GzliSAj2';
    $fullName = 'Super Administrator';
    $isActive = 1;
    $email = 'superadmin@mining.local';
    $role = 'superadmin';
 
    // Ensure 'superadmin' is in the role ENUM
    @$db->query("ALTER TABLE users MODIFY COLUMN role ENUM('admin','ktt','user','department_user','superadmin') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT 'user'");

    // Try to add is_active column if missing
    $conn = $db->getConnection();
    
    // Check if is_active column exists before adding
    $columnCheckResult = $conn->query("SHOW COLUMNS FROM users LIKE 'is_active'");
    if ($columnCheckResult && $columnCheckResult->num_rows === 0) {
        @$conn->query("ALTER TABLE users ADD COLUMN is_active tinyint(1) DEFAULT 1");
    }

    // Check if superadmin exists using prepared statement
    $stmt = $db->prepare("SELECT id FROM users WHERE username = ?");
    if (!$stmt) {
        return; // Database issue, exit gracefully
    }
    
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result && $result->num_rows > 0) {
        $stmt->close();
        return;
    }

    $stmt->close();

    // Insert superadmin user
    $insert = $db->prepare("INSERT INTO users (username, password, full_name, company_name, email, phone, role, is_active, created_at, updated_at, department) VALUES (?, ?, ?, NULL, ?, NULL, ?, ?, NOW(), NOW(), NULL)");
    if ($insert) {
        $insert->bind_param("sssiss", $username, $passwordHash, $fullName, $email, $role, $isActive);
        @$insert->execute();
        $insert->close();
    }
}

// Jika sudah login, redirect ke dashboard berdasarkan role
if (isset($_SESSION['user_id'])) {
    $role = $_SESSION['role'] ?? '';
    $department = $_SESSION['department'] ?? '';
    
    switch ($role) {
        case 'superadmin':
            header('Location: pages/superadmin/dashboard.php');
            break;
        case 'ktt':
            header('Location: pages/ktt/approval.php');
            break;
        case 'admin':
            header('Location: pages/admin/dashboard.php');
            break;
        case 'department_user':
            header('Location: pages/dept/dashboard.php');
            break;
        case 'user':
            if (!empty($department)) {
                header('Location: pages/dept/dashboard.php');
            } else {
                header('Location: pages/user/dashboard.php');
            }
            break;
        default:
            header('Location: pages/admin/dashboard.php');
    }
    exit();
}

$error = '';

// Proses login
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    
    if (!empty($username) && !empty($password)) {
        $db = new Database();

        if ($username === 'superadmin') {
            ensureSuperadminAccount($db);
        }

        $stmt = $db->prepare("SELECT id, username, password, full_name, role, company_name, department, is_active FROM users WHERE username = ? AND is_active = 1");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows == 1) {
            $user = $result->fetch_assoc();
            
            // Verifikasi password
            if (password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['company_name'] = $user['company_name'];
                $_SESSION['department'] = $user['department'];
                
                // Redirect berdasarkan role
                switch ($user['role']) {
                    case 'superadmin':
                        header('Location: pages/superadmin/dashboard.php');
                        break;
                    case 'ktt':
                        header('Location: pages/ktt/approval.php');
                        break;
                    case 'admin':
                        header('Location: pages/admin/dashboard.php');
                        break;
                    case 'department_user':
                        header('Location: pages/dept/dashboard.php');
                        break;
                    case 'user':
                        if (!empty($user['department'])) {
                            header('Location: pages/dept/dashboard.php');
                        } else {
                            header('Location: pages/user/dashboard.php');
                        }
                        break;
                    default:
                        header('Location: pages/admin/dashboard.php');
                }
                exit();
            } else {
                $error = 'Incorrect username or password!';
            }
        } else {
            $error = 'Incorrect username or password!';
        }
    } else {
        $error = 'Please fill in all fields!';
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/language-switcher.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        *{
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body.login-page {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f8f9fa;
            min-height: 100vh;
            display: flex;
            overflow: hidden;
        }
        
        .login-container {
            display: flex;
            width: 100%;
            min-height: 100vh;
        }
        
        /* Left Side - Orange Gradient */
        .login-left {
            flex: 1;
            background: linear-gradient(135deg, #FFC300 0%, #FFD400 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            overflow: hidden;
        }
        
        /* Large Circle Decoration */
        .login-left::before {
            content: '';
            position: absolute;
            width: 900px;
            height: 900px;
            background: radial-gradient(circle, rgba(255, 195, 0, 0.5) 0%, rgba(255, 140, 0, 0.7) 100%);
            border-radius: 50%;
            top: 50%;
            right: -250px;
            transform: translateY(-50%);
        }
        
        .login-brand {
            position: relative;
            z-index: 2;
            padding: 40px;
            max-width: 500px;
        }
        
        .logo-container {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 50px;
        }
        
        .logo-icon {
            width: 35px;
            height: 35px;
            background: rgba(255, 255, 255, 0.95);
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .logo-icon i {
            font-size: 20px;
            color: #FF8C00;
        }
        
        .logo-text {
            font-size: 20px;
            color: white;
            font-weight: 600;
        }
        
        .welcome-text {
            color: white;
            font-size: 38px;
            font-weight: 300;
            line-height: 1.4;
        }
        
        .welcome-text strong {
            font-weight: 400;
        }
        
        /* Right Side - White with Form */
        .login-right {
            flex: 1;
            background: #F8F8F8;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px;
            position: relative;
        }
        
        .login-form-container {
            width: 100%;
            max-width: 420px;
            text-align: center;
        }
        
        .user-avatar {
            width: 90px;
            height: 90px;
            background: #BCC5CE;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 18px;
        }
        
        .user-avatar i {
            font-size: 45px;
            color: #8A96A3;
        }
        
        .login-subtitle {
            color: #909BA8;
            font-size: 14px;
            margin-bottom: 35px;
            font-weight: 400;
        }
        
        .form-group {
            margin-bottom: 18px;
            text-align: left;
        }
        
        .form-group label {
            display: none;
        }
        
        .input-group {
            position: relative;
        }
        
        .input-icon {
            position: absolute;
            left: 18px;
            top: 50%;
            transform: translateY(-50%);
            color: #A8B4C0;
            font-size: 15px;
        }
        
        .form-group input {
            width: 100%;
            padding: 15px 16px 15px 45px;
            border: none;
            border-radius: 10px;
            font-size: 14px;
            transition: all 0.3s ease;
            background: #EEF2F5;
            color: #333;
            box-shadow: none;
        }
        
        .form-group input::placeholder {
            color: #A8B4C0;
            font-weight: 400;
        }
        
        .form-group input:focus {
            outline: none;
            background: #E8ECF0;
            box-shadow: 0 0 0 2px rgba(255, 140, 0, 0.15);
        }
        
        .form-group input:focus ~ .input-icon {
            color: #FF8C00;
        }
        
        .form-options {
            display: flex;
            align-items: center;
            justify-content: flex-start;
            margin-bottom: 26px;
            font-size: 13px;
            text-align: left;
        }
        
        .remember-me {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #909BA8;
            cursor: pointer;
        }
        
        .remember-me input[type="checkbox"] {
            width: 15px;
            height: 15px;
            cursor: pointer;
            accent-color: #FF8C00;
        }
        
        .btn-login {
            width: 100%;
            padding: 16px;
            background: linear-gradient(90deg, #FF8C00 0%, #FFC300 100%);
            color: white;
            border: none;
            border-radius: 50px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-transform: capitalize;
            letter-spacing: 0.5px;
            box-shadow: 0 8px 25px rgba(255, 140, 0, 0.4);
        }

        .btn-login:hover {
            background: linear-gradient(90deg, #E67E00 0%, #FFD700 100%);
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(255, 140, 0, 0.5);
        }
        
        .btn-login:active {
            transform: translateY(0);
        }
        
        .register-link {
            margin-top: 22px;
            color: #909BA8;
            font-size: 14px;
        }
        
        .register-link a {
            color: #FF8C00;
            text-decoration: none;
            font-weight: 600;
        }
        
        .register-link a:hover {
            color: #FFC300;
            text-decoration: underline;
        }
        
        .alert {
            margin-bottom: 24px;
            padding: 14px 16px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            gap: 12px;
            animation: slideIn 0.3s ease;
            text-align: left;
        }
        
        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .alert-error {
            background: #FFEBEE;
            color: #C62828;
            border-left: 4px solid #EF5350;
        }
        
        .login-footer {
            display: none;
        }
        
        .footer-info {
            display: none;
        }
        
        .footer-title {
            display: none;
        }
        
        .account-info {
            display: none;
        }
        
        .account-item {
            display: none;
        }
        
        .account-item strong {
            display: none;
        }
        
        .account-item span {
            display: none;
        }
        
        /* Responsive */
        @media (max-width: 968px) {
            .login-container {
                flex-direction: column;
            }
            
            .login-left {
                padding: 60px 40px;
                min-height: 300px;
            }
            
            .login-left::before {
                width: 600px;
                height: 600px;
                right: -150px;
            }
            
            .welcome-text {
                font-size: 32px;
            }
            
            .login-right {
                padding: 40px 30px;
            }
            
            .login-footer {
                position: relative;
                bottom: auto;
                left: auto;
                right: auto;
                margin-top: 30px;
            }
        }

        @media (max-width: 600px) {
            .login-left {
                padding: 40px 30px;
                min-height: 250px;
            }
            
            .login-left::before {
                width: 500px;
                height: 500px;
                right: -100px;
            }
            
            .welcome-text {
                font-size: 26px;
            }
            
            .login-right {
                padding: 30px 20px;
            }
            
            .user-avatar {
                width: 70px;
                height: 70px;
            }
            
            .user-avatar i {
                font-size: 35px;
            }
        }
    </style>
</head>
<body class="login-page">
    <!-- Language Switcher Dropdown -->
    <div class="language-switcher">
        <div class="language-dropdown login-lang-dropdown">
            <button id="languageToggle" class="language-toggle-btn" type="button">
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
    </div>

    <div class="login-container">
        <!-- Left Side - Welcome Message -->
        <div class="login-left">
            <div class="login-brand">
                <div class="logo-container">
                    <div class="logo-icon">
                        <i class="fas fa-hard-hat"></i>
                    </div>
                    <span class="logo-text">STELA</span>
                </div>
                <h2 class="welcome-text"><span data-lang="welcome">Welcome to</span> <br><strong>STELA</strong></h2>
            </div>
        </div>
        
        <!-- Right Side - Login Form -->
        <div class="login-right">
            <div class="login-form-container">
                <div class="user-avatar">
                    <i class="fas fa-user"></i>
                </div>
                <p class="login-subtitle" data-lang="login-subtitle">Login below to get started.</p>
                
                <?php if ($error): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <span><?php echo htmlspecialchars($error); ?></span>
                </div>
                <?php endif; ?>
                
                <form method="POST" action="">
                    <div class="form-group">
                        <label for="username">Username</label>
                        <div class="input-group">
                            <input type="text" id="username" name="username" placeholder="E-mail Address" data-lang="email-placeholder" required autofocus>
                            <i class="fas fa-envelope input-icon"></i>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="password">Password</label>
                        <div class="input-group">
                            <input type="password" id="password" name="password" placeholder="Your Password" data-lang="password-placeholder" required>
                            <i class="fas fa-lock input-icon"></i>
                        </div>
                    </div>
                    
                    <div class="form-options">
                        <label class="remember-me">
                            <input type="checkbox" name="remember">
                            <span data-lang="keep-logged-in">Keep me logged in</span>
                        </label>
                    </div>

                    <button type="submit" class="btn-login" data-lang="login-button">
                        Login
                    </button>
                
                </form>
            </div>
        </div>
    </div>

    <!-- Language Switcher Script -->
    <script src="assets/js/language-switcher.js"></script>
</body>
</html>

