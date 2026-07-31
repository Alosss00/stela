<?php
$page_title = 'Certificate Status';
$page_title_lang = 'certificate-status';
require_once dirname(__DIR__, 3) . '/app/Helpers/auth_helper.php';
// Included via bootstrap/app.php

checkPageAccess(['admin', 'superadmin']);

$db = new Database();
$monitor_window_days = 60;

function bindStatementParams($stmt, string $types, array $params): void
{
	$bind_values = [$types];
	foreach ($params as $index => $value) {
		$bind_values[] = &$params[$index];
	}
	call_user_func_array([$stmt, 'bind_param'], $bind_values);
}

function getMonitoringBadge(int $days_left): array
{
    if ($days_left <= 0) {
        return [
            'class' => 'critical',
            'label' => 'EXPIRED'
        ];
    }

    if ($days_left <= 14) {
        return [
            'class' => 'critical',
            'label' => 'VERY URGENT'
        ];
    }

    if ($days_left <= 30) {
        return [
            'class' => 'warning',
            'label' => 'URGENT'
        ];
    }

    return [
        'class' => 'info',
        'label' => 'WARNING'
    ];
}

function getWorkflowStatus(array $cert): array
{
    $status = $cert['status'] ?? '';
    $verification = $cert['verification_status'] ?? '';
    $appointment = $cert['appointment_status'] ?? '';

    if ($status === 'expired') {
        return [
            'class' => 'critical',
            'label' => 'EXPIRED'
        ];
    }

    if ($status === 'pending' && $verification === 'pending') {
        return [
            'class' => 'pending',
            'label' => 'WAITING REVIEWER'
        ];
    }

    if ($status === 'pending' && $verification === 'rejected') {
        return [
            'class' => 'critical',
            'label' => 'REJECTED'
        ];
    }

    if ($status === 'pending' && $verification === 'verified') {
        return [
            'class' => 'warning',
            'label' => 'WAITING KTT'
        ];
    }

    if ($status === 'active' && $verification === 'verified' && $appointment === 'approved') {
        return [
            'class' => 'success',
            'label' => 'ACTIVE'
        ];
    }

    return [
        'class' => 'secondary',
        'label' => strtoupper($status ?: 'PENDING')
    ];
}

if (session_status() === PHP_SESSION_NONE) {
	session_start();
}
if (empty($_SESSION['csrf_token'])) {
	$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$message = '';
$error = '';

// Get distinct companies list for Admin filter
$companies_res = $db->query("SELECT DISTINCT contractor_company FROM employees WHERE contractor_company IS NOT NULL AND TRIM(contractor_company) != '' ORDER BY contractor_company ASC");
$companies_list = [];
if ($companies_res) {
    while ($c_row = $companies_res->fetch_assoc()) {
        $companies_list[] = $c_row['contractor_company'];
    }
}

// Get distinct departments list for Admin filter
$depts_res = $db->query("SELECT DISTINCT department FROM employees WHERE department IS NOT NULL AND TRIM(department) != '' ORDER BY department ASC");
$depts_list = [];
if ($depts_res) {
    while ($d_row = $depts_res->fetch_assoc()) {
        $depts_list[] = $d_row['department'];
    }
}

// Selected filters
$selected_company = trim($_GET['company'] ?? '');
$selected_dept = trim($_GET['department'] ?? '');
$selected_status_filter = trim($_GET['status_filter'] ?? '');

$filter_sql = "";
$filter_params = [];
$filter_types = "";

if ($selected_company !== '') {
    $filter_sql .= " AND LOWER(TRIM(e.contractor_company)) = LOWER(TRIM(?))";
    $filter_params[] = $selected_company;
    $filter_types .= "s";
}

if ($selected_dept !== '') {
    $filter_sql .= " AND LOWER(TRIM(e.department)) = LOWER(TRIM(?))";
    $filter_params[] = $selected_dept;
    $filter_types .= "s";
}

// Fetch all monitoring certificates across companies & departments
$all_certificates = [];
$certificates = [];
$total_certificates = 0;
$critical_count = 0;
$warning_count = 0;
$info_count = 0;

$monitor_sql = "
SELECT
       ec.id as employee_certification_id,
       ec.employee_id,
       ec.certification_id,
       ec.cert_number,
       ec.cert_issuer,
       ec.issue_date,
       ec.expiry_date,
       ec.document_file,
       ec.status,
       ec.verification_status,
       ec.updated_at,
       e.full_name,
       e.employee_code,
       e.position,
       e.department,
       e.contractor_company,
       e.is_active,
	   e.resubmit_type,
       c.cert_name,
       c.cert_type,
       c.issuing_authority,
       a.id as appointment_id,
       a.status as appointment_status,
       a.ktt_msm_status,
       a.ktt_ttn_status,
       DATEDIFF(ec.expiry_date, CURDATE()) as days_left

FROM employee_certifications ec

JOIN employees e
ON ec.employee_id = e.id

LEFT JOIN certifications c
ON ec.certification_id = c.id

LEFT JOIN appointments a
ON a.id = (
    SELECT MAX(ap.id)
    FROM appointments ap
    WHERE ap.employee_id = e.id
)

WHERE e.is_active = 1
  AND (
      (ec.status = 'expired' AND e.resubmit_type IS NULL)
      OR
      (e.resubmit_type = 'certificate' AND ec.id = (
          SELECT MAX(ec3.id)
          FROM employee_certifications ec3
          WHERE ec3.employee_id = ec.employee_id
      ))
      OR
      (ec.expiry_date IS NOT NULL AND ec.expiry_date <= DATE_ADD(CURDATE(), INTERVAL 2 MONTH))
  )
  {$filter_sql}
ORDER BY ec.updated_at DESC
";

$monitor_stmt = $db->prepare($monitor_sql);
if ($monitor_stmt) {
    if (!empty($filter_params)) {
        bindStatementParams($monitor_stmt, $filter_types, $filter_params);
    }
	$monitor_stmt->execute();
	$monitor_result = $monitor_stmt->get_result();
	if ($monitor_result) {
		while ($row = $monitor_result->fetch_assoc()) {
			$row['days_left'] = (int) $row['days_left'];
			$row['monitoring_badge'] = getMonitoringBadge($row['days_left']);
			$row['workflow_badge'] = getWorkflowStatus($row);
			
			$total_certificates++;
			if ($row['days_left'] <= 14) {
				$critical_count++;
			} elseif ($row['days_left'] <= 30) {
				$warning_count++;
			} else {
				$info_count++;
			}

            // Apply status filter if selected
            if ($selected_status_filter !== '') {
                if ($selected_status_filter === 'critical' && $row['days_left'] > 14) continue;
                if ($selected_status_filter === 'warning' && ($row['days_left'] <= 14 || $row['days_left'] > 30)) continue;
                if ($selected_status_filter === 'info' && $row['days_left'] <= 30) continue;
                if ($selected_status_filter === 'expired' && $row['days_left'] > 0) continue;
            }

			$certificates[] = $row;
		}
	}
}

require_once dirname(__DIR__) . '/layouts/header.php';
?>

<div class="certificate-status-page">
	<div class="page-hero">
		<div>
			<p class="eyebrow" data-lang="certificate-status">Certificate Status</p>
			<h2><i class="fas fa-id-card"></i> <span data-lang="certificate-status">Status Sertifikat</span></h2>
		</div>
		<div class="hero-actions">
			<span class="btn btn-secondary" style="pointer-events:none;"><i class="fas fa-shield-alt"></i> Admin View (All Companies & Departments)</span>
		</div>
	</div>

	<?php if ($message): ?>
		<div class="alert alert-success cert-alert">
			<i class="fas fa-check-circle"></i>
			<div><?php echo htmlspecialchars($message); ?></div>
		</div>
	<?php endif; ?>

	<?php if ($error): ?>
		<div class="alert alert-error cert-alert">
			<i class="fas fa-exclamation-circle"></i>
			<div><?php echo htmlspecialchars($error); ?></div>
		</div>
	<?php endif; ?>

	<!-- Statistics Cards -->
	<div class="stats-grid">
		<div class="stat-card">
			<span class="stat-number"><?php echo $total_certificates; ?></span>
			<span class="stat-label" data-lang="all-employees">Monitoring Certificates</span>
		</div>
		<div class="stat-card stat-expired">
			<span class="stat-number"><?php echo $critical_count; ?></span>
			<span class="stat-label">Very Urgent (&le; 14 Hari)</span>
		</div>
		<div class="stat-card stat-pending">
			<span class="stat-number"><?php echo $warning_count; ?></span>
			<span class="stat-label">Urgent (15 - 30 Hari)</span>
		</div>
		<div class="stat-card stat-verified">
			<span class="stat-number"><?php echo $info_count; ?></span>
			<span class="stat-label">Warning (&gt; 30 Hari)</span>
		</div>
	</div>

	<!-- Admin Filters Form -->
	<div class="card cert-card mb-4" style="margin-bottom: 20px;">
		<div class="card-body" style="padding: 16px 20px;">
			<form method="GET" action="certificate_status.php" class="row g-3 align-items-center">
				<div class="col-md-3">
					<label class="form-label fw-bold" style="font-size: 12.5px; color: #475569;" data-lang="company">Company</label>
					<select name="company" class="form-select form-select-sm" onchange="this.form.submit()">
						<option value="" data-lang="showing-all-data-all-companies">-- All Companies --</option>
						<?php foreach ($companies_list as $comp): ?>
							<option value="<?php echo htmlspecialchars($comp); ?>" <?php echo $selected_company === $comp ? 'selected' : ''; ?>>
								<?php echo htmlspecialchars($comp); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</div>
				<div class="col-md-3">
					<label class="form-label fw-bold" style="font-size: 12.5px; color: #475569;" data-lang="department">Department</label>
					<select name="department" class="form-select form-select-sm" onchange="this.form.submit()">
						<option value="" data-lang="all-data">-- All Departments --</option>
						<?php foreach ($depts_list as $d_name): ?>
							<option value="<?php echo htmlspecialchars($d_name); ?>" <?php echo $selected_dept === $d_name ? 'selected' : ''; ?>>
								<?php echo htmlspecialchars($d_name); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</div>
				<div class="col-md-3">
					<label class="form-label fw-bold" style="font-size: 12.5px; color: #475569;" data-lang="status">Status Filter</label>
					<select name="status_filter" class="form-select form-select-sm" onchange="this.form.submit()">
						<option value="">-- All Statuses --</option>
						<option value="critical" <?php echo $selected_status_filter === 'critical' ? 'selected' : ''; ?>>Very Urgent (&le; 14 Hari)</option>
						<option value="warning" <?php echo $selected_status_filter === 'warning' ? 'selected' : ''; ?>>Urgent (15 - 30 Hari)</option>
						<option value="info" <?php echo $selected_status_filter === 'info' ? 'selected' : ''; ?>>Warning (&gt; 30 Hari)</option>
						<option value="expired" <?php echo $selected_status_filter === 'expired' ? 'selected' : ''; ?>>Expired (&le; 0 Hari)</option>
					</select>
				</div>
				<div class="col-md-3 d-flex align-items-end gap-2" style="margin-top: 28px;">
					<button type="submit" class="btn btn-sm btn-primary"><i class="fas fa-filter"></i> Filter</button>
					<a href="certificate_status.php" class="btn btn-sm btn-outline-secondary"><i class="fas fa-undo"></i> Reset</a>
				</div>
			</form>
		</div>
	</div>

	<!-- Main Certificate Data Table -->
	<div class="card cert-card">
		<div class="card-header cert-card-header">
			<h3><i class="fas fa-list"></i> <span data-lang="certificate-status">Daftar Sertifikat</span></h3>
			<span class="badge bg-secondary"><?php echo count($certificates); ?> Data</span>
		</div>
		<div class="card-body cert-card-body">
			<?php if (!empty($certificates)): ?>
				<div class="table-responsive">
					<table class="table cert-table datatable" id="adminCertificatesTable">
						<thead>
							<tr>
								<th data-lang="employee">Employee</th>
								<th data-lang="company">Company & Dept</th>
								<th data-lang="certification">Certification</th>
								<th data-lang="certificate-number">Certificate No.</th>
								<th data-lang="expiry">Expiry</th>
								<th>Monitoring</th>
								<th data-lang="document">Document</th>
								<th data-lang="action">Workflow / Action</th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ($certificates as $cert): ?>
								<tr>
									<td>	
										<strong><?php echo htmlspecialchars($cert['full_name']); ?></strong><br>
										<small class="text-muted"><?php echo htmlspecialchars($cert['employee_code']); ?></small>
									</td>
									<td>
										<strong><?php echo htmlspecialchars($cert['contractor_company'] ?: '-'); ?></strong><br>
										<small class="text-muted"><?php echo htmlspecialchars($cert['department'] ?: '-'); ?></small>
									</td>
									<td>
										<strong><?php echo htmlspecialchars($cert['cert_name'] ?: '-'); ?></strong><br>
										<small class="text-muted"><?php echo htmlspecialchars($cert['cert_type'] ?: '-'); ?></small>
									</td>
									<td><?php echo htmlspecialchars($cert['cert_number'] ?: '-'); ?></td>
									<td>
										<?php echo $cert['expiry_date'] ? date('d M Y', strtotime($cert['expiry_date'])) : '-'; ?><br>
										<small class="text-muted">
											<?php if ((int) $cert['days_left'] >= 0): ?>
												<?php echo $cert['days_left'] . ' hari lagi'; ?> 
											<?php else: ?>
												Lewat <?php echo abs((int) $cert['days_left']); ?> hari
											<?php endif; ?>
										</small>
									</td>
									<td>
										<span class="status-badge status-<?php echo htmlspecialchars($cert['monitoring_badge']['class']); ?>">
											<?php echo htmlspecialchars($cert['monitoring_badge']['label']); ?>
										</span>
									</td>
									<td>
										<?php if (!empty($cert['document_file'])): ?>
											<a class="btn btn-sm btn-info" href="../../assets/<?php echo htmlspecialchars($cert['document_file']); ?>" target="_blank" rel="noopener noreferrer">
												<i class="fas fa-eye"></i> <span data-lang="view">View</span>
											</a>
										<?php else: ?>
											-
										<?php endif; ?>
									</td>
									<td>
										<?php
										$w_class = $cert['workflow_badge']['class'] ?? 'secondary';
										$w_label = $cert['workflow_badge']['label'] ?? 'PENDING';
										
										if ($w_label === 'ACTIVE') {
											echo '<span class="badge badge-success" data-lang="active">ACTIVE</span>';
										} elseif ($w_label === 'WAITING REVIEWER') {
											echo '<a href="verify_employee.php?id=' . (int)$cert['employee_id'] . '" class="btn btn-sm btn-warning"><i class="fas fa-user-check"></i> Verify</a>';
										} elseif ($w_label === 'WAITING KTT') {
											echo '<span class="badge badge-warning">WAITING KTT</span>';
										} elseif ($w_label === 'EXPIRED') {
											echo '<span class="badge badge-danger">EXPIRED</span>';
										} else {
											echo '<span class="badge badge-secondary">' . htmlspecialchars($w_label) . '</span>';
										}
										?>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			<?php else: ?>
				<div class="empty-state">
					<i class="fas fa-folder-open"></i>
					<p data-lang="no-requests-data">No monitoring certificate records found for the selected filter.</p>
				</div>
			<?php endif; ?>
		</div>
	</div>
</div>

<style>
.stats-grid {
	display: grid;
	grid-template-columns: repeat(4, minmax(0, 1fr));
	gap: 14px;
	margin-bottom: 22px;
}

.stat-card {
	background: #fff;
	border-radius: 14px;
	padding: 18px 20px;
	box-shadow: 0 10px 25px rgba(15, 23, 42, 0.06);
	border-left: 4px solid #37474F;
}
.stat-expired { border-left-color: #dc2626; }
.stat-pending { border-left-color: #f59e0b; }
.stat-verified { border-left-color: #16a34a; }

.status-critical { background: #fee2e2; color: #b91c1c; font-weight: 700; padding: 4px 8px; border-radius: 4px; display: inline-block; }
.status-urgent { background: #ffedd5; color: #9a3412; font-weight: 700; padding: 4px 8px; border-radius: 4px; display: inline-block; }
.status-warning { background: #fef3c7; color: #92400e; font-weight: 700; padding: 4px 8px; border-radius: 4px; display: inline-block; }
.status-info { background: #e0f2fe; color: #0369a1; font-weight: 700; padding: 4px 8px; border-radius: 4px; display: inline-block; }
.status-pending { background: #f3e8ff; color: #6b21a8; font-weight: 700; padding: 4px 8px; border-radius: 4px; display: inline-block; }

.stat-number {
	display: block;
	font-size: 28px;
	font-weight: 700;
	color: #111827;
	line-height: 1;
}

.stat-label {
	display: block;
	margin-top: 6px;
	color: #6b7280;
	font-size: 12px;
	text-transform: uppercase;
	letter-spacing: .08em;
}
</style>

<?php require_once dirname(__DIR__) . '/layouts/footer.php'; ?>
