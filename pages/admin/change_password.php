<?php
$page_title = 'Settings';
require_once '../../includes/auth.php';
require_once '../../includes/db.php';

// Only admin (reviewer) can access
checkPageAccess(['admin']);

$db = new Database();
$message = '';
$error = '';

// Handle password change
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['change_password'])) {
    // 1. Validasi Token CSRF terlebih dahulu
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (empty($csrf_token) || !isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrf_token)) {
        // Menggunakan stela_t untuk translasi error CSRF jika tersedia
        $error = function_exists('stela_t') ? stela_t('csrf-validation-failed') : 'Security validation failed. Request denied.';
    } else {
        // 2. Jika token lolos validasi, jalankan logika ganti password Anda
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        
        // Validate inputs
        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            $error = stela_t('all-fields-required');
        } elseif ($new_password !== $confirm_password) {
            $error = stela_t('password-confirmation-not-match');
        } elseif (strlen($new_password) < 6) {
            $error = stela_t('new-password-min-6-chars');
        } else {
            // Verify current password
            $user_id = $_SESSION['user_id'];
            $user = $db->query("SELECT password FROM users WHERE id = $user_id")->fetch_assoc();
            
            if (!$user || !password_verify($current_password, $user['password'])) {
                $error = stela_t('current-password-incorrect');
            } else {
                // Update password
                $new_password_hash = password_hash($new_password, PASSWORD_DEFAULT);
                $update_sql = "UPDATE users SET password = '$new_password_hash' WHERE id = $user_id";

                if ($db->query($update_sql)) {
                    $message = stela_t('password-changed');
                    // Clear form
                    $_POST = array();
                } else {
                    $error = stela_t('failed-change-password');
                }
            }
        }
    }
}
require_once '../../includes/header.php';
?>

<div class="change-password-container">
    <div class="page-header">
        <h2><i class="fas fa-cog"></i> Settings</h2>
        <p>Manage your account security by changing your password regularly</p>
    </div>

    <?php if ($message): ?>
    <div class="alert alert-success">
        <i class="fas fa-check-circle"></i>
        <div>
            <strong>Success!</strong>
            <p><?php echo htmlspecialchars($message); ?></p>
        </div>
    </div>
    <?php endif; ?>
    
    <?php if ($error): ?>
    <div class="alert alert-error">
        <i class="fas fa-exclamation-circle"></i>
        <div>
            <strong>Error!</strong>
            <p><?php echo htmlspecialchars($error); ?></p>
        </div>
    </div>
    <?php endif; ?>
    
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-shield-alt"></i> Change Password Form</h3>
        </div>
        <div class="card-body">
            <form method="POST" action="" class="password-form">
                <input type="hidden" name="change_password" value="1">

                <div class="form-group">
                    <label for="current_password">
                        <i class="fas fa-lock"></i> Current Password
                        <span class="required">*</span>
                    </label>
                    <input type="password"
                           id="current_password"
                           name="current_password"
                           class="form-control"
                           required
                           placeholder="Enter current password" data-lang-placeholder="enter-current-password">
                </div>

                <div class="form-group">
                    <label for="new_password">
                        <i class="fas fa-key"></i> New Password
                        <span class="required">*</span>
                    </label>
                    <input type="password"
                           id="new_password"
                           name="new_password"
                           class="form-control"
                           required
                           minlength="6"
                           placeholder="Enter new password (min. 6 characters)" data-lang-placeholder="enter-new-password-min-6">
                    <small class="form-text">Minimum 6 characters. Use combination of letters, numbers, and symbols for better security.</small>
                </div>

                <div class="form-group">
                    <label for="confirm_password">
                        <i class="fas fa-check-circle"></i> Confirm New Password
                        <span class="required">*</span>
                    </label>
                    <input type="password"
                           id="confirm_password"
                           name="confirm_password"
                           class="form-control"
                           required
                           minlength="6"
                           placeholder="Repeat new password" data-lang-placeholder="repeat-new-password">
                </div>
                
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i>
                    <div>
                        <strong>Security Tips:</strong>
                        <ul>
                            <li>Use at least 6 characters</li>
                            <li>Combine uppercase letters, lowercase letters, numbers, and symbols</li>
                            <li>Don't use easily guessable passwords</li>
                            <li>Don't use personal information such as date of birth</li>
                            <li>Change your password regularly for account security</li>
                        </ul>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Change Password
                    </button>
                    <a href="<?php
                        if ($_SESSION['role'] == 'admin' || $_SESSION['role'] == 'ktt') {
                            echo 'dashboard.php';
                        } elseif ($_SESSION['role'] == 'user' && !hasDepartment()) {
                            echo '../user/dashboard.php';
                        } else {
                            echo '../dept/dashboard.php';
                        }
                    ?>" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
.change-password-container {
    padding: 20px 0;
}

.page-header {
    background: #F57C00;
    color: white;
    padding: 30px;
    border-radius: 12px;
    margin-bottom: 24px;
    box-shadow: 0 4px 20px rgba(245, 124, 0, 0.3);
}

.page-header h2 {
    margin: 0 0 6px 0;
    font-size: 24px;
    font-weight: 700;
    display: flex;
    align-items: center;
    gap: 12px;
}

.page-header p {
    margin: 0;
    opacity: 0.95;
    font-size: 13px;
}

.alert {
    display: flex;
    gap: 15px;
    align-items: flex-start;
    padding: 15px 20px;
    margin-bottom: 25px;
    border-radius: 8px;
    border-left: 4px solid;
}

.alert-success {
    background: #E8F5E9;
    border-left-color: #2E7D32;
    color: #1B5E20;
}

.alert-success i {
    color: #2E7D32;
    font-size: 20px;
}

.alert-error {
    background: #fee2e2;
    border-left-color: #ef4444;
    color: #7f1d1d;
}

.alert-error i {
    color: #ef4444;
    font-size: 20px;
}

.alert-info {
    background: #ECEFF1;
    border-left-color: #37474F;
    color: #37474F;
}

.alert-info i {
    color: #37474F;
    font-size: 18px;
}

.alert strong {
    display: block;
    margin-bottom: 5px;
    font-weight: 600;
}

.alert p {
    margin: 0;
}

.alert ul {
    margin: 8px 0 0 0;
    padding-left: 20px;
}

.alert li {
    margin: 5px 0;
    font-size: 13px;
}

.card {
    background: white;
    border-radius: 12px;
    box-shadow: 0 2px 12px rgba(0, 0, 0, 0.06);
    border: 1px solid #e5e7eb;
    border-top: 4px solid #37474F;
    overflow: hidden;
}

.card-header {
    background: #f8f9fa;
    padding: 20px 30px;
    border-bottom: 2px solid #e5e7eb;
}

.card-header h3 {
    margin: 0;
    font-size: 18px;
    font-weight: 700;
    color: #1f2937;
    display: flex;
    align-items: center;
    gap: 10px;
}

.card-header i {
    color: #37474F;
    font-size: 20px;
}

.card-body {
    padding: 32px;
}

.password-form {
    max-width: 100%;
}

.form-group {
    margin-bottom: 25px;
}

.form-group label {
    display: flex;
    align-items: center;
    gap: 8px;
    font-weight: 600;
    color: #374151;
    margin-bottom: 8px;
    font-size: 14px;
}

.form-group label i {
    color: #37474F;
    font-size: 16px;
}

.required {
    color: #ef4444;
    font-weight: 700;
}

.form-control {
    width: 100%;
    padding: 12px 16px;
    border: 2px solid #e5e7eb;
    border-radius: 8px;
    font-size: 14px;
    transition: all 0.3s ease;
    font-family: inherit;
    background: white;
}

.form-control:focus {
    outline: none;
    border-color: #37474F;
    box-shadow: 0 0 0 3px rgba(23, 162, 184, 0.1);
    background: #f0f9ff;
}

.form-control:hover:not(:focus) {
    border-color: #d1d5db;
}

.form-text {
    display: block;
    margin-top: 6px;
    font-size: 12px;
    color: #6c757d;
    font-style: italic;
}

.form-actions {
    display: flex;
    gap: 12px;
    margin-top: 32px;
    padding-top: 24px;
    border-top: 2px solid #f3f4f6;
}

.btn {
    padding: 12px 24px;
    border: none;
    border-radius: 8px;
    font-size: 15px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    text-decoration: none;
}

.btn-primary {
    background: #37474F;
    color: white;
    box-shadow: 0 4px 14px rgba(55, 71, 79, 0.3);
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(23, 162, 184, 0.4);
}

.btn-secondary {
    background: #6c757d;
    color: white;
    box-shadow: 0 2px 8px rgba(108, 117, 125, 0.2);
}

.btn-secondary:hover {
    background: #5a6268;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(108, 117, 125, 0.3);
}

/* Responsive */
@media (max-width: 768px) {
    .change-password-container {
        padding: 16px 0;
    }
    
    .page-header {
        padding: 24px 20px;
        margin-bottom: 20px;
        border-radius: 8px;
    }
    
    .page-header h2 {
        font-size: 20px;
    }
    
    .page-header p {
        font-size: 13px;
    }
    
    .card-body {
        padding: 24px;
    }
    
    .form-actions {
        flex-direction: column;
    }
    
    .btn {
        width: 100%;
        justify-content: center;
    }
}

@media (max-width: 576px) {
    .page-header {
        padding: 20px 16px;
    }
    
    .page-header h2 {
        font-size: 18px;
    }
    
    .card {
        border-radius: 8px;
    }
    
    .card-body {
        padding: 20px;
    }
    
    .form-control {
        padding: 10px 14px;
        font-size: 13px;
    }
    
    .alert {
        padding: 14px 16px;
        font-size: 13px;
    }
}
</style>

<?php require_once '../../includes/footer.php'; ?>




