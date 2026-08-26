<?php
require_once dirname(__DIR__, 2) . '/bootstrap/app.php';
$page_title = 'System Settings';
require_once dirname(__DIR__, 2) . '/bootstrap/app.php';
require_once dirname(__DIR__, 2) . '/app/Helpers/auth_helper.php';

checkPageAccess(['superadmin'], 'manage_settings');

// Fetch settings from DB
$db = new Database();
$settings_query = $db->query("SELECT setting_key, setting_value FROM settings");
$settings = [];
if ($settings_query) {
    while ($row = $settings_query->fetch_assoc()) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
}

// Fallbacks for defaults if not exist
$settings = array_merge([
    'app_name' => 'STELA System',
    'app_env' => 'production',
    'maintenance_mode' => '0',
    'support_email' => 'support@stela-app.local',
    'smtp_host' => 'smtp.hostinger.com',
    'smtp_port' => '465',
    'smtp_user' => 'sentry@tokaguard.com',
    'fonnte_token' => 'BVru1eLXHL2it4WozxLH',
    'default_pagination' => '20',
    'session_timeout' => '1800',
    'password_policy_strict' => '1',
    'primary_color' => '#2563eb'
], $settings);

require_once dirname(__DIR__) . '/layouts/superadmin_header.php';
?>

<style>
    .settings-dashboard { font-family: 'Inter', sans-serif; padding: 20px 0; }
    .settings-card { background: #fff; border: none; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.04); }
    .settings-card-header { background: transparent; border-bottom: 1px solid #f0f2f5; padding: 18px 24px; font-weight: 600; }
    
    .settings-tabs .nav-link {
        color: #64748b;
        font-weight: 500;
        border: none;
        padding: 12px 20px;
        border-radius: 8px 8px 0 0;
        margin-right: 5px;
    }
    .settings-tabs .nav-link:hover {
        background-color: #f8fafc;
        color: #0f172a;
    }
    .settings-tabs .nav-link.active {
        color: #2563eb;
        background-color: #fff;
        border-bottom: 3px solid #2563eb;
        font-weight: 600;
    }
    
    .setting-group {
        padding: 20px 0;
        border-bottom: 1px solid #f1f5f9;
    }
    .setting-group:last-child {
        border-bottom: none;
    }
    
    .setting-label {
        font-weight: 600;
        color: #334155;
        margin-bottom: 5px;
        display: block;
    }
    .setting-desc {
        color: #64748b;
        font-size: 0.85rem;
        margin-bottom: 15px;
    }
    
    .form-control, .form-select {
        border-color: #cbd5e1;
        border-radius: 6px;
    }
    .form-control:focus, .form-select:focus {
        border-color: #3b82f6;
        box-shadow: 0 0 0 0.2rem rgba(59, 130, 246, 0.25);
    }
    
    .form-switch .form-check-input {
        width: 2.5em;
        height: 1.25em;
        cursor: pointer;
    }
    .form-switch .form-check-input:checked {
        background-color: #10b981;
        border-color: #10b981;
    }
</style>

<div class="container-fluid settings-dashboard">
    <div class="row mb-4 align-items-center">
        <div class="col-md-6">
            <h2 class="mb-1 fw-bold" style="color: #1e293b;">System Settings</h2>
            <p class="text-muted mb-0">Configure global application parameters</p>
        </div>
        <div class="col-md-6 text-end">
            <button class="btn btn-primary rounded-pill px-4" onclick="saveSettings()">
                <i class="fas fa-save me-2"></i> Save All Changes
            </button>
        </div>
    </div>

    <div class="settings-card">
        <div class="settings-card-header p-0 pt-2 px-3 border-bottom-0">
            <ul class="nav nav-tabs settings-tabs" id="settingsTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#general" type="button" role="tab">General</button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" data-bs-toggle="tab" data-bs-target="#email" type="button" role="tab">Email & WhatsApp</button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" data-bs-toggle="tab" data-bs-target="#security" type="button" role="tab">Security & Performance</button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" data-bs-toggle="tab" data-bs-target="#appearance" type="button" role="tab">Appearance</button>
                </li>
            </ul>
        </div>
        
        <div class="card-body p-4 bg-white" style="border-radius: 0 0 12px 12px;">
            <div class="alert alert-info small mb-4">
                <i class="fas fa-info-circle me-2"></i> Note: Updating system settings may require a page reload for all active users to see the changes.
            </div>

            <form id="settingsForm">
                <div class="tab-content" id="settingsTabsContent">
                    
                    <!-- General Settings -->
                    <div class="tab-pane fade show active" id="general" role="tabpanel">
                        <div class="setting-group row">
                            <div class="col-md-4">
                                <label class="setting-label">Application Name</label>
                                <div class="setting-desc">The name displayed in the header and emails.</div>
                            </div>
                            <div class="col-md-6">
                                <input type="text" class="form-control" name="app_name" value="<?php echo htmlspecialchars($settings['app_name']); ?>">
                            </div>
                        </div>
                        <div class="setting-group row">
                            <div class="col-md-4">
                                <label class="setting-label">Application Environment</label>
                                <div class="setting-desc">Sets error reporting levels.</div>
                            </div>
                            <div class="col-md-6">
                                <select class="form-select" name="app_env">
                                    <option value="production" <?php echo $settings['app_env'] == 'production' ? 'selected' : ''; ?>>Production</option>
                                    <option value="staging" <?php echo $settings['app_env'] == 'staging' ? 'selected' : ''; ?>>Staging</option>
                                    <option value="development" <?php echo $settings['app_env'] == 'development' ? 'selected' : ''; ?>>Development</option>
                                </select>
                            </div>
                        </div>
                        <div class="setting-group row">
                            <div class="col-md-4">
                                <label class="setting-label">Maintenance Mode</label>
                                <div class="setting-desc">Disable access for non-superadmin users.</div>
                            </div>
                            <div class="col-md-6 d-flex align-items-center">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="maintenance_mode" <?php echo $settings['maintenance_mode'] == '1' ? 'checked' : ''; ?>>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Email Settings -->
                    <div class="tab-pane fade" id="email" role="tabpanel">
                        <div class="setting-group row">
                            <div class="col-md-4">
                                <label class="setting-label">Support Email</label>
                                <div class="setting-desc">The email address users can contact for help.</div>
                            </div>
                            <div class="col-md-6">
                                <input type="email" class="form-control" name="support_email" value="<?php echo htmlspecialchars($settings['support_email']); ?>">
                            </div>
                        </div>
                        <div class="setting-group row">
                            <div class="col-md-4">
                                <label class="setting-label">SMTP Host</label>
                                <div class="setting-desc">Mail server host address.</div>
                            </div>
                            <div class="col-md-6">
                                <input type="text" class="form-control" name="smtp_host" value="<?php echo htmlspecialchars($settings['smtp_host']); ?>">
                            </div>
                        </div>
                        <div class="setting-group row">
                            <div class="col-md-4">
                                <label class="setting-label">SMTP Port</label>
                                <div class="setting-desc">Mail server port (e.g., 587, 465, 2525).</div>
                            </div>
                            <div class="col-md-6">
                                <input type="number" class="form-control" name="smtp_port" value="<?php echo htmlspecialchars($settings['smtp_port']); ?>">
                            </div>
                        </div>
                        <div class="setting-group row">
                            <div class="col-md-4">
                                <label class="setting-label">SMTP Username</label>
                                <div class="setting-desc">Authentication username.</div>
                            </div>
                            <div class="col-md-6">
                                <input type="text" class="form-control" name="smtp_user" value="<?php echo htmlspecialchars($settings['smtp_user']); ?>">
                            </div>
                        </div>
                        <div class="setting-group row">
                            <div class="col-md-4">
                                <label class="setting-label">SMTP Password</label>
                                <div class="setting-desc">Leave blank to keep current password.</div>
                            </div>
                            <div class="col-md-6">
                                <input type="password" class="form-control" name="smtp_pass" placeholder="********">
                            </div>
                        </div>
                        <div class="setting-group row">
                            <div class="col-md-4">
                                <label class="setting-label">Fonnte Token (WhatsApp API)</label>
                                <div class="setting-desc">Token from your <a href="https://app.fonnte.com" target="_blank">Fonnte</a> account for WhatsApp notifications.</div>
                            </div>
                            <div class="col-md-6">
                                <input type="text" class="form-control" name="fonnte_token" value="<?php echo htmlspecialchars($settings['fonnte_token']); ?>">
                            </div>
                        </div>
                    </div>
                    
                    <!-- Security Settings -->
                    <div class="tab-pane fade" id="security" role="tabpanel">
                        <div class="setting-group row">
                            <div class="col-md-4">
                                <label class="setting-label">Session Timeout (Seconds)</label>
                                <div class="setting-desc">Auto-logout after inactivity (1800s = 30 min).</div>
                            </div>
                            <div class="col-md-6">
                                <input type="number" class="form-control" name="session_timeout" value="<?php echo htmlspecialchars($settings['session_timeout']); ?>">
                            </div>
                        </div>
                        <div class="setting-group row">
                            <div class="col-md-4">
                                <label class="setting-label">Strict Password Policy</label>
                                <div class="setting-desc">Require uppercase, number, and special character.</div>
                            </div>
                            <div class="col-md-6 d-flex align-items-center">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="password_policy_strict" <?php echo $settings['password_policy_strict'] == '1' ? 'checked' : ''; ?>>
                                </div>
                            </div>
                        </div>
                        <div class="setting-group row">
                            <div class="col-md-4">
                                <label class="setting-label">Default Pagination Limit</label>
                                <div class="setting-desc">Items per page on data tables.</div>
                            </div>
                            <div class="col-md-6">
                                <select class="form-select" name="default_pagination">
                                    <option value="10" <?php echo $settings['default_pagination'] == '10' ? 'selected' : ''; ?>>10 Items</option>
                                    <option value="20" <?php echo $settings['default_pagination'] == '20' ? 'selected' : ''; ?>>20 Items</option>
                                    <option value="50" <?php echo $settings['default_pagination'] == '50' ? 'selected' : ''; ?>>50 Items</option>
                                    <option value="100" <?php echo $settings['default_pagination'] == '100' ? 'selected' : ''; ?>>100 Items</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Appearance Settings -->
                    <div class="tab-pane fade" id="appearance" role="tabpanel">
                        <div class="setting-group row">
                            <div class="col-md-4">
                                <label class="setting-label">Primary Brand Color</label>
                                <div class="setting-desc">Used for buttons, active states, and highlights.</div>
                            </div>
                            <div class="col-md-2">
                                <input type="color" class="form-control form-control-color w-100" name="primary_color" value="<?php echo htmlspecialchars($settings['primary_color']); ?>" title="Choose your color">
                            </div>
                        </div>
                        <div class="setting-group row">
                            <div class="col-md-4">
                                <label class="setting-label">Company Logo</label>
                                <div class="setting-desc">Upload a new logo for the sidebar (Max 2MB).</div>
                            </div>
                            <div class="col-md-6">
                                <input type="file" class="form-control" name="app_logo" accept="image/png, image/jpeg">
                            </div>
                        </div>
                    </div>
                    
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    function saveSettings() {
        const form = document.getElementById('settingsForm');
        const formData = new FormData(form);
        const btn = document.querySelector('button[onclick="saveSettings()"]');
        const originalText = btn.innerHTML;
        
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Saving...';

        fetch('../../api/save_settings.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert("Settings saved successfully!");
            } else {
                alert("Error: " + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An unexpected error occurred while saving settings.');
        })
        .finally(() => {
            btn.disabled = false;
            btn.innerHTML = originalText;
        });
    }
</script>

<?php require_once dirname(__DIR__) . '/layouts/footer.php'; ?>
