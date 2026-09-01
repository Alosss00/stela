<?php
require_once dirname(__DIR__, 2) . '/bootstrap/app.php';
$page_title = 'KTT Delegation';
require_once dirname(__DIR__, 2) . '/app/Helpers/auth_helper.php';
require_once dirname(__DIR__, 2) . '/app/Helpers/ktt_helper.php';
// Included via bootstrap/app.php

requirePermission('appointment.view');

$db = new Database();
$current_user_id = $_SESSION['user_id'];
$is_superadmin = isSuperadmin();

$ktt_info = getKttInfo($current_user_id, $db);
$ktt_type = $ktt_info['ktt_type'];

// Only allow native KTTs or superadmin to manage delegations
if (!$is_superadmin && ($ktt_info['is_delegated'] || !$ktt_type)) {
    header('Location: ../admin/dashboard.php');
    exit();
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!verify_csrf_token()) {
        die("CSRF Token Invalid. Silakan muat ulang halaman.");
    }
    
    if (isset($_POST['action'])) {
        if ($_POST['action'] == 'create_delegation') {
            $delegate_user_id = intval($_POST['delegate_user_id']);
            $start_date = $db->escapeString($_POST['start_date']);
            $end_date = $db->escapeString($_POST['end_date']);
            $reason = $db->escapeString($_POST['reason']);
            $my_ktt_type = $is_superadmin ? $db->escapeString($_POST['ktt_type']) : $ktt_type;
            $ktt_user_id = $is_superadmin ? getKttUserIdByType($my_ktt_type, $db) : $current_user_id;

            if (empty($ktt_user_id) || empty($my_ktt_type)) {
                $error = "Error: Invalid KTT role configuration.";
            } elseif ($delegate_user_id == $ktt_user_id) {
                $error = "You cannot delegate to yourself.";
            } elseif (strtotime($start_date) > strtotime($end_date)) {
                $error = "End date must be on or after start date.";
            } else {
                // Check for overlapping active delegations for this KTT type
                $overlap = $db->query("
                    SELECT id FROM ktt_delegations
                    WHERE ktt_type = ? AND status = 'active'
                    AND (
                        (start_date <= ? AND end_date >= ?) OR
                        (start_date <= ? AND end_date >= ?) OR
                        (start_date >= ? AND end_date <= ?)
                    )
                ", [$my_ktt_type, $start_date, $start_date, $end_date, $end_date, $start_date, $end_date]);
                
                if ($overlap && $overlap->num_rows > 0) {
                    $error = "An active delegation already exists for this KTT type during the selected period.";
                } else {
                    $sql = "INSERT INTO ktt_delegations 
                            (ktt_user_id, ktt_type, delegate_user_id, start_date, end_date, reason, created_by)
                            VALUES (?, ?, ?, ?, ?, ?, ?)";
                    if ($db->query($sql, [$ktt_user_id, $my_ktt_type, $delegate_user_id, $start_date, $end_date, $reason, $current_user_id])) {
                        $message = "Delegation created successfully.";
                    } else {
                        $error = "Failed to create delegation.";
                    }
                }
            }
        } elseif ($_POST['action'] == 'cancel_delegation') {
            $delegation_id = intval($_POST['delegation_id']);
            
            // Verify permission
            $check = $db->query("SELECT ktt_user_id FROM ktt_delegations WHERE id = ?", [$delegation_id])->fetch_assoc();
            if (!$is_superadmin && $check['ktt_user_id'] != $current_user_id) {
                $error = "Unauthorized to cancel this delegation.";
            } else {
                $sql = "UPDATE ktt_delegations 
                        SET status = 'cancelled', cancelled_by = ?, cancelled_at = NOW()
                        WHERE id = ?";
                if ($db->query($sql, [$current_user_id, $delegation_id])) {
                    $message = "Delegation cancelled successfully.";
                } else {
                    $error = "Failed to cancel delegation.";
                }
            }
        }
    }
}

// Fetch all active users to populate the delegatee dropdown
$users = $db->query("SELECT id, username, full_name, role FROM users WHERE is_active = 1 AND id != ? ORDER BY full_name", [$current_user_id]);

// Fetch current delegations
$delegations_sql = "
    SELECT kd.*, 
           del.full_name AS delegate_name,
           ktt.full_name AS ktt_name
    FROM ktt_delegations kd
    JOIN users del ON kd.delegate_user_id = del.id
    JOIN users ktt ON kd.ktt_user_id = ktt.id
";

if (!$is_superadmin) {
    $delegations_sql .= " WHERE kd.ktt_user_id = " . intval($current_user_id);
}

$delegations_sql .= " ORDER BY kd.created_at DESC";
$delegations = $db->query($delegations_sql);

if ($is_superadmin) {
    require_once dirname(__DIR__) . '/layouts/superadmin_header.php';
} else {
    require_once dirname(__DIR__) . '/layouts/header.php';
}
?>

<div class="appointments-container">
    <div class="page-header-appt">
        <div class="header-left">
            <h2><i class="fas fa-users-cog"></i> <span>KTT Delegation Management</span></h2>
            <p>Manage temporary authority delegations</p>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <div class="card-appt" style="margin-bottom: 20px;">
        <div class="card-header-appt">
            <h3><i class="fas fa-plus-circle"></i> <span>Create New Delegation</span></h3>
        </div>
        <div class="card-body-appt">
            <form method="POST" action="">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="create_delegation">
                
                <?php if ($is_superadmin): ?>
                <div class="form-group" style="margin-bottom: 15px;">
                    <label>KTT Role to Delegate</label>
                    <select name="ktt_type" class="form-control" required style="width: 100%; padding: 8px; border-radius: 4px; border: 1px solid #ccc;">
                        <option value="msm">KTT MSM</option>
                        <option value="ttn">KTT TTN</option>
                    </select>
                </div>
                <?php endif; ?>
                
                <div class="form-group" style="margin-bottom: 15px;">
                    <label>Delegate To (User)</label>
                    <select name="delegate_user_id" class="form-control" required style="width: 100%; padding: 8px; border-radius: 4px; border: 1px solid #ccc;">
                        <option value="">-- Select User --</option>
                        <?php while ($u = $users->fetch_assoc()): ?>
                            <option value="<?php echo $u['id']; ?>"><?php echo htmlspecialchars($u['full_name'] . ' (' . $u['role'] . ')'); ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                
                <div style="display: flex; gap: 15px; margin-bottom: 15px;">
                    <div class="form-group" style="flex: 1;">
                        <label>Start Date</label>
                        <input type="date" name="start_date" class="form-control" required min="<?php echo date('Y-m-d'); ?>" style="width: 100%; padding: 8px; border-radius: 4px; border: 1px solid #ccc;">
                    </div>
                    <div class="form-group" style="flex: 1;">
                        <label>End Date</label>
                        <input type="date" name="end_date" class="form-control" required min="<?php echo date('Y-m-d'); ?>" style="width: 100%; padding: 8px; border-radius: 4px; border: 1px solid #ccc;">
                    </div>
                </div>
                
                <div class="form-group" style="margin-bottom: 15px;">
                    <label>Reason (Optional)</label>
                    <textarea name="reason" class="form-control" rows="2" style="width: 100%; padding: 8px; border-radius: 4px; border: 1px solid #ccc;"></textarea>
                </div>
                
                <button type="submit" class="btn btn-primary" style="padding: 10px 20px;"><i class="fas fa-save"></i> Create Delegation</button>
            </form>
        </div>
    </div>

    <div class="card-appt">
        <div class="card-header-appt">
            <h3><i class="fas fa-list"></i> <span>Delegation History</span></h3>
        </div>
        <div class="card-body-appt">
            <div class="table-responsive">
                <table class="table-appt">
                    <thead>
                        <tr>
                            <?php if ($is_superadmin): ?><th>KTT</th><?php endif; ?>
                            <th>Delegatee</th>
                            <th>Role</th>
                            <th>Period</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($delegations && $delegations->num_rows > 0): ?>
                            <?php while ($del = $delegations->fetch_assoc()): 
                                $is_active = ($del['status'] == 'active' && strtotime($del['end_date']) >= strtotime('today'));
                                $status_badge = '';
                                if ($del['status'] == 'cancelled') {
                                    $status_badge = '<span class="badge-status badge-danger">CANCELLED</span>';
                                } elseif (!$is_active) {
                                    $status_badge = '<span class="badge-status badge-secondary">EXPIRED</span>';
                                } else {
                                    if (strtotime($del['start_date']) > strtotime('today')) {
                                        $status_badge = '<span class="badge-status badge-info">UPCOMING</span>';
                                    } else {
                                        $status_badge = '<span class="badge-status badge-success">ACTIVE</span>';
                                    }
                                }
                            ?>
                            <tr>
                                <?php if ($is_superadmin): ?>
                                    <td><strong><?php echo htmlspecialchars($del['ktt_name']); ?></strong><br><small class="text-muted"><?php echo strtoupper($del['ktt_type']); ?></small></td>
                                <?php endif; ?>
                                <td><strong><?php echo htmlspecialchars($del['delegate_name']); ?></strong></td>
                                <td><?php echo strtoupper($del['ktt_type']); ?></td>
                                <td><?php echo date('d M Y', strtotime($del['start_date'])) . ' - ' . date('d M Y', strtotime($del['end_date'])); ?></td>
                                <td><?php echo $status_badge; ?></td>
                                <td>
                                    <?php if ($del['status'] == 'active'): ?>
                                    <form method="POST" action="" style="display:inline;" onsubmit="return confirm('Are you sure you want to cancel this delegation?');">
                                        <?php echo csrf_field(); ?>
                                        <input type="hidden" name="action" value="cancel_delegation">
                                        <input type="hidden" name="delegation_id" value="<?php echo $del['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-danger" style="padding: 5px 10px; font-size: 12px;"><i class="fas fa-times"></i> Cancel</button>
                                    </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="<?php echo $is_superadmin ? '6' : '5'; ?>" style="text-align: center; padding: 20px; color: #6c757d;">No delegations found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php
if ($is_superadmin) {
    require_once dirname(__DIR__) . '/layouts/superadmin_footer.php';
} else {
    require_once dirname(__DIR__) . '/layouts/footer.php';
}
?>
