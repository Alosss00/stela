<?php
$page_title = 'Certificate Status';
require_once '../../includes/auth.php';
require_once '../../includes/db.php';

checkPageAccess(['user', 'department_user']);

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

function buildResubmitUrl(array $cert, string $csrf_token): string
{
	return 'appointments.php?' . http_build_query([
		'action' => 'resubmit_to_ktt',
		'id' => (int) ($cert['appointment_id'] ?? 0),
		'employee_id' => (int) ($cert['employee_id'] ?? 0),
		'certification_id' => (int) ($cert['certification_id'] ?? 0),
		'employee_certification_id' => (int) ($cert['employee_certification_id'] ?? 0),
		'csrf_token' => $csrf_token,
	]);
}

if (session_status() === PHP_SESSION_NONE) {
	session_start();
}
if (empty($_SESSION['csrf_token'])) {
	$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$message = '';
$error = '';
$role = $_SESSION['role'] ?? '';
$company_name = trim((string) ($_SESSION['company_name'] ?? ''));
$department = trim((string) ($_SESSION['department'] ?? ''));

$scope_sql = '';
$scope_params = [];
$scope_types = '';

if ($role === 'department_user' && $department !== '') {
	$scope_sql = ' AND LOWER(TRIM(e.department)) = LOWER(TRIM(?))';
	$scope_params[] = $department;
	$scope_types .= 's';
} elseif ($role === 'user' && $company_name !== '') {
	$scope_sql = ' AND LOWER(TRIM(e.contractor_company)) = LOWER(TRIM(?))';
	$scope_params[] = $company_name;
	$scope_types .= 's';
} else {
	// Failsafe: never expose cross-scope data when session scope is incomplete.
	$scope_sql = ' AND 1 = 0';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'resubmit_file') {
	if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
		http_response_code(403);
		die('CSRF token mismatch');
	}

	$cert_id = isset($_POST['cert_id']) ? intval($_POST['cert_id']) : 0;
	$cert_check_sql = '
		SELECT ec.id, ec.employee_id, ec.certification_id, ec.document_file, ec.expiry_date, ec.verification_status, ec.status, e.employee_code, e.department, e.contractor_company, e.is_active
		FROM employee_certifications ec
		JOIN employees e ON ec.employee_id = e.id
		WHERE ec.id = ?
		  AND ec.verification_status = ?
		  AND e.is_active = 1
		  AND ec.expiry_date IS NOT NULL
		  AND ec.expiry_date <= DATE_ADD(CURDATE(), INTERVAL 2 MONTH)
		' . $scope_sql . '
		LIMIT 1
	';

	$cert_check_stmt = $db->prepare($cert_check_sql);
	if ($cert_check_stmt) {
		$verification_status = 'verified';
		$excluded_status = 'expired';
		$cert_params = [$cert_id, $verification_status, $monitor_window_days, $excluded_status];
		$cert_types = 'isis' . $scope_types;
		$cert_params = array_merge($cert_params, $scope_params);
		bindStatementParams($cert_check_stmt, $cert_types, $cert_params);
		$cert_check_stmt->execute();
		$cert_check = $cert_check_stmt->get_result();
	} else {
		$cert_check = false;
	}

	if (!$cert_check || $cert_check->num_rows === 0) {
		$error = 'Certificate record not found or not accessible.';
	} elseif (!isset($_FILES['document_file']) || $_FILES['document_file']['error'] !== 0) {
		$error = 'Please choose a certificate file to upload.';
	} else {
		$cert_row = $cert_check->fetch_assoc();
		$document_allowed = $cert_row['verification_status'] === 'verified' && (int) $cert_row['is_active'] === 1 && $cert_row['status'] !== 'expired';
		$document_in_scope = false;
		if ($role === 'department_user' && $department !== '') {
			$document_in_scope = strtolower(trim((string) ($cert_row['department'] ?? ''))) === strtolower($department);
		} elseif ($role === 'user' && $company_name !== '') {
			$document_in_scope = strtolower(trim((string) ($cert_row['contractor_company'] ?? ''))) === strtolower($company_name);
		}
		$document_in_window = !empty($cert_row['expiry_date']) && $cert_row['expiry_date'] > date('Y-m-d') && $cert_row['expiry_date'] <= date('Y-m-d', strtotime('+' . $monitor_window_days . ' days'));

		if (!$document_allowed || !$document_in_scope || !$document_in_window) {
			$error = 'Certificate record is not eligible for monitoring resubmit.';
		} else {
			$allowed_extensions = ['pdf', 'jpg', 'jpeg', 'png'];
			$file_size = $_FILES['document_file']['size'];
			$max_size = 5 * 1024 * 1024;
			$file_extension = strtolower(pathinfo($_FILES['document_file']['name'], PATHINFO_EXTENSION));

			if (!in_array($file_extension, $allowed_extensions, true)) {
				$error = 'File type not allowed. Use PDF, JPG, JPEG, or PNG.';
			} elseif ($file_size > $max_size) {
				$error = 'File size too large. Maximum 5MB.';
			} else {
				$upload_dir = '../../assets/uploads/certifications/';
				if (!is_dir($upload_dir)) {
					mkdir($upload_dir, 0775, true);
				}

				$new_filename = 'cert_' . $cert_row['employee_code'] . '_' . time() . '.' . $file_extension;
				$upload_path = $upload_dir . $new_filename;

				if (move_uploaded_file($_FILES['document_file']['tmp_name'], $upload_path)) {
					if (!empty($cert_row['document_file']) && file_exists('../../assets/' . $cert_row['document_file'])) {
						@unlink('../../assets/' . $cert_row['document_file']);
					}

					$document_file = 'uploads/certifications/' . $new_filename;
					$update_stmt = $db->prepare('UPDATE employee_certifications SET document_file = ?, verification_status = ?, updated_at = NOW() WHERE id = ? AND employee_id = ?');
					if ($update_stmt) {
						$new_status = 'pending';
						$cert_row_id = (int) $cert_row['id'];
						$employee_id = (int) $cert_row['employee_id'];
						$update_stmt->bind_param('ssii', $document_file, $new_status, $cert_row_id, $employee_id);
						if ($update_stmt->execute()) {
							$message = 'Certificate file has been resubmitted and is waiting for verification.';
						} else {
							$error = 'Failed to update certificate record.';
						}
					} else {
						$error = 'Failed to update certificate record.';
					}
				} else {
					$error = 'Failed to upload certificate file.';
				}
			}
		}
	}
}

$certificates = [];
$total_certificates = 0;
$critical_count = 0;
$warning_count = 0;
$info_count = 0;

$monitor_sql = '
	SELECT ec.id as employee_certification_id,
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
	       c.cert_name,
	       c.cert_type,
	       c.issuing_authority,
	       a.id as appointment_id,
	       a.status as appointment_status,
	       DATEDIFF(ec.expiry_date, CURDATE()) as days_left
	FROM employee_certifications ec
	JOIN employees e ON ec.employee_id = e.id
	LEFT JOIN certifications c ON ec.certification_id = c.id
	LEFT JOIN appointments a ON a.id = (
		SELECT MAX(ap.id)
		FROM appointments ap
		WHERE ap.employee_id = e.id
	)
	WHERE ec.verification_status = ?
	  AND e.is_active = 1
	  AND ec.expiry_date IS NOT NULL
	  AND ec.expiry_date <= DATE_ADD(CURDATE(), INTERVAL ? DAY)
	' . $scope_sql . '
	ORDER BY ec.expiry_date ASC, ec.updated_at DESC
';

$monitor_stmt = $db->prepare($monitor_sql);
if ($monitor_stmt) {
	$verified_status = 'verified';
	$monitor_params = [$verified_status, $monitor_window_days];
	$monitor_types = 'si' . $scope_types;
	$monitor_params = array_merge($monitor_params, $scope_params);
	bindStatementParams($monitor_stmt, $monitor_types, $monitor_params);
	$monitor_stmt->execute();
	$monitor_result = $monitor_stmt->get_result();
	if ($monitor_result) {
		while ($row = $monitor_result->fetch_assoc()) {
			$row['days_left'] = (int) $row['days_left'];
			$row['monitoring_badge'] = getMonitoringBadge($row['days_left']);
			$certificates[] = $row;
			$total_certificates++;
			if ($row['days_left'] <= 14) {
				$critical_count++;
			} elseif ($row['days_left'] <= 30) {
				$warning_count++;
			} else {
				$info_count++;
			}
		}
	}
}

require_once '../../includes/header.php';
?>

<div class="certificate-status-page">
	<div class="page-hero">
		<div>
			<p class="eyebrow">Certificate Status</p>
			<h2><i class="fas fa-id-card"></i> Status Sertifikat</h2>
			<p>Monitoring sertifikat verified yang kedaluwarsa atau akan expired dalam 60 hari ke depan untuk akun yang sedang login.</p>
		</div>
		<div class="hero-actions">
			<span class="btn btn-secondary" style="pointer-events:none;">Monitoring &le; 2 Bulan</span>
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

	<div class="stats-grid">
		<div class="stat-card">
			<span class="stat-number"><?php echo $total_certificates; ?></span>
			<span class="stat-label">Monitoring Certificates</span>
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

	<div class="card cert-card">
		<div class="card-header cert-card-header">
			<h3><i class="fas fa-list"></i> Daftar Sertifikat</h3>
		</div>
		<div class="card-body cert-card-body">
			<?php if (!empty($certificates)): ?>
				<div class="table-responsive">
					<table class="table cert-table">
						<thead>
							<tr>
								<th>Employee</th>
								<th>Certification</th>
								<th>Certificate No.</th>
								<th>Expiry</th>
								<th>Monitoring</th>
								<th>Document</th>
								<th>Action</th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ($certificates as $cert): ?>
								<tr>
									<td>
										<strong><?php echo htmlspecialchars($cert['full_name']); ?></strong><br>
										<small><?php echo htmlspecialchars($cert['employee_code']); ?></small>
									</td>
									<td>
										<?php echo htmlspecialchars($cert['cert_name'] ?: '-'); ?><br>
										<small><?php echo htmlspecialchars($cert['cert_type'] ?: '-'); ?></small>
									</td>
									<td><?php echo htmlspecialchars($cert['cert_number'] ?: '-'); ?></td>
									<td>
										<?php echo $cert['expiry_date'] ? date('d M Y', strtotime($cert['expiry_date'])) : '-'; ?><br>
										<small class="text-muted">
											<?php if ((int) $cert['days_left'] >= 0): ?>
												<?php
													echo $cert['days_left'] < 0
														? abs($cert['days_left']) . ' hari yang lalu'
														: $cert['days_left'] . ' hari';
													?> 
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
												<i class="fas fa-eye"></i> View
											</a>
										<?php else: ?>
											-
										<?php endif; ?>
									</td>
									<td>
										<?php if (!empty($cert['appointment_id'])): ?>
											<a class="btn btn-primary btn-sm"
											href="resubmit_certificate.php?employee_id=<?php echo (int)$cert['employee_id']; ?>&certificate_id=<?php echo (int)$cert['employee_certification_id']; ?>">
												<i class="fas fa-upload"></i> Resubmit
											</a>
											<?php else: ?>
												<span class="text-muted">No Appointment</span>
										<?php endif; ?>
									</td>
								</tr> 	
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			<?php else: ?>
				<div class="empty-state">
					<i class="fas fa-folder-open"></i>
					<p>No active certificates expiring within 60 days were found for this scope.</p>
				</div>
			<?php endif; ?>
		</div>
	</div>
</div>

<style>
.certificate-status-page {
	padding: 20px 0;
}

.page-hero {
	display: flex;
	justify-content: space-between;
	gap: 20px;
	align-items: flex-start;
	padding: 28px 30px;
	border-radius: 16px;
	background: linear-gradient(135deg, #37474F 0%, #607d8b 100%);
	color: #fff;
	box-shadow: 0 18px 40px rgba(55, 71, 79, 0.22);
	margin-bottom: 22px;
}

.eyebrow {
	margin: 0 0 8px;
	text-transform: uppercase;
	letter-spacing: .12em;
	font-size: 12px;
	opacity: .8;
}

.page-hero h2 {
	margin: 0 0 8px;
	font-size: 30px;
}

.page-hero p {
	margin: 0;
	opacity: .92;
}

.hero-actions {
	display: flex;
	flex-wrap: wrap;
	gap: 8px;
	justify-content: flex-end;
}

.hero-actions .btn {
	background: rgba(255,255,255,0.12);
	color: #fff;
	border: 1px solid rgba(255,255,255,0.18);
}

.cert-alert {
	display: flex;
	align-items: flex-start;
	gap: 12px;
	margin-bottom: 18px;
}

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

.status-critical { background: #fee2e2; color: #b91c1c; }
.status-urgent { background: #ffedd5; color: #9a3412; }
.status-warning { background: #fef3c7; color: #92400e; }

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

.cert-card {
	border: none;
	border-radius: 16px;
	overflow: hidden;
	box-shadow: 0 10px 30px rgba(15, 23, 42, 0.08);
}

.cert-card-header {
	background: #fff;
	padding: 18px 22px;
	border-bottom: 1px solid #eef2f7;
}

.cert-card-header h3 {
	margin: 0;
	font-size: 18px;
}

.cert-card-body {
	padding: 0;
}

.cert-table {
	margin: 0;
}

.cert-table th {
	background: #f8fafc;
	color: #475569;
	font-size: 12px;
	text-transform: uppercase;
	letter-spacing: .08em;
	border-bottom: 1px solid #e2e8f0 !important;
}

.cert-table td {
	vertical-align: middle;
}

.status-badge {
	display: inline-flex;
	align-items: center;
	justify-content: center;
	padding: 6px 12px;
	border-radius: 999px;
	font-size: 11px;
	font-weight: 700;
	letter-spacing: .08em;
}

.status-expired { background: #fee2e2; color: #b91c1c; }
.status-pending { background: #fef3c7; color: #b45309; }
.status-verified { background: #dcfce7; color: #15803d; }
.status-rejected { background: #ede9fe; color: #6d28d9; }

.current-file-preview {
	padding: 12px 14px;
	background: #f8fafc;
	border-radius: 10px;
	border: 1px solid #e5e7eb;
}

.form-help {
	display: block;
	margin-top: 6px;
	color: #64748b;
}

.empty-state {
	padding: 50px 20px;
	text-align: center;
	color: #94a3b8;
}

.empty-state i {
	font-size: 44px;
	margin-bottom: 12px;
	display: block;
}

@media (max-width: 992px) {
	.page-hero {
		flex-direction: column;
	}

	.hero-actions {
		justify-content: flex-start;
	}

	.stats-grid {
		grid-template-columns: repeat(2, minmax(0, 1fr));
	}
}

@media (max-width: 768px) {
	.certificate-status-page {
		padding: 12px 0;
	}

	.page-hero {
		padding: 20px;
	}

	.page-hero h2 {
		font-size: 22px;
	}

	.stats-grid {
		grid-template-columns: 1fr;
	}

	.cert-table {
		min-width: 760px;
	}
}
</style>

<?php require_once '../../includes/footer.php'; ?>
