<?php
require_once dirname(__DIR__, 2) . '/bootstrap/app.php';
$page_title = 'Certificate Status';
require_once dirname(__DIR__, 2) . '/app/Helpers/auth_helper.php';
// Included via bootstrap/app.php

requirePermission('certificate.view');
if (!hasPermission('dept.access') && !(hasPermission('user.access') && hasDepartment()) && !isSuperadmin()) {
    header('Location: ../admin/dashboard.php');
    exit();
}

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

    /*
    |--------------------------------------------------------------------------
    | EXPIRED
    |--------------------------------------------------------------------------
    */

    if ($status === 'expired') {
        return [
            'class' => 'critical',
            'label' => 'EXPIRED'
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | WAITING REVIEWER
    |--------------------------------------------------------------------------
    */

    if (
        $status === 'pending' &&
        $verification === 'pending'
    ) {
        return [
            'class' => 'pending',
            'label' => 'WAITING REVIEWER'
        ];
    }

	 if (
        $status === 'pending' &&
        $verification === 'rejected'
    ) {
        return [
            'class' => 'critical',
            'label' => 'REJECT'
        ];
    }


    // |--------------------------------------------------------------------------
    // | WAITING KTT
    // |--------------------------------------------------------------------------
    // */

    if (
        $status === 'pending' &&
        $verification === 'verified'
    ) {
        return [
            'class' => 'warning',
            'label' => 'WAITING KTT'
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | ACTIVE
    |--------------------------------------------------------------------------
    */

    if (
        $status === 'active' &&
        $verification === 'verified' &&
        $appointment === 'approved'
    ) {
        return [
            'class' => 'success',
            'label' => 'ACTIVE'
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | DEFAULT
    |--------------------------------------------------------------------------
    */

    return [
        'class' => 'secondary',
        'label' => strtoupper($status)
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

$company_name = trim((string) ($_SESSION['company_name'] ?? ''));
$department = trim((string) ($_SESSION['department'] ?? ''));

$scope_sql = '';
$scope_params = [];
$scope_types = '';

if ($department !== '') {
	$scope_sql = ' AND LOWER(TRIM(e.department)) = LOWER(TRIM(?))';
	$scope_params[] = $department;
	$scope_types .= 's';
} else {
	// Failsafe: never expose cross-scope data when session department is incomplete.
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
		$document_in_scope = strtolower(trim((string) ($cert_row['department'] ?? ''))) === strtolower($department);
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
				$upload_dir = upload_physical_dir('certifications');

				$new_filename = 'cert_' . $cert_row['employee_code'] . '_' . time() . '.' . $file_extension;
				$upload_path = $upload_dir . $new_filename;

				if (safe_move_uploaded_file($_FILES['document_file']['tmp_name'], $upload_path)) {
					if (!empty($cert_row['document_file'])) {
						delete_upload($cert_row['document_file']);
					}

					$document_file = 'certifications/' . $new_filename;
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

$monitor_sql = "

SELECT

    ec.id AS employee_certification_id,
    ec.employee_id,
    ec.certification_id,
    ec.cert_number,
    ec.cert_type,
    ec.cert_issuer,
    ec.issue_date,
    ec.expiry_date,
    ec.document_file,
    ec.status,
    ec.verification_status,
    ec.notes,

    e.employee_code,
    e.full_name,
    e.position,
    e.department,
    e.contractor_company,
    e.resubmit_type,

    c.cert_name,

    a.id AS appointment_id,
    a.appointment_number,
    a.status AS appointment_status,

    DATEDIFF(ec.expiry_date, CURDATE()) AS days_left,

    (
        SELECT COUNT(*)
        FROM employee_certifications ec_count
        WHERE ec_count.employee_id = ec.employee_id
        AND ec_count.certification_id = ec.certification_id
    ) AS submission_count

FROM employee_certifications ec

INNER JOIN employees e
ON e.id = ec.employee_id

LEFT JOIN certifications c
ON c.id = ec.certification_id

LEFT JOIN appointments a
ON a.id =
(
    SELECT MAX(ap.id)
    FROM appointments ap
    WHERE ap.employee_id = e.id
)

WHERE

-- Ambil record terbaru per karyawan per sertifikasi
-- Prioritaskan: pending > verified > active > expired
ec.id =
(
    SELECT ec2.id
    FROM employee_certifications ec2
    WHERE ec2.employee_id = ec.employee_id
    AND ec2.certification_id = ec.certification_id
    ORDER BY
    FIELD(ec2.status, 'pending', 'verified', 'active', 'expired'),
    ec2.id DESC
    LIMIT 1
)

-- Tampilkan hanya jika ada riwayat sertifikat yang sudah EXPIRED
AND EXISTS (
    SELECT 1
    FROM employee_certifications ec_hist
    WHERE ec_hist.employee_id = ec.employee_id
    AND ec_hist.certification_id = ec.certification_id
    AND ec_hist.status = 'expired'
)

-- JANGAN tampilkan jika sudah berhasil diperbaharui
-- (ada record active dengan expiry_date di masa depan)
AND NOT EXISTS (
    SELECT 1
    FROM employee_certifications ec_active
    WHERE ec_active.employee_id = ec.employee_id
    AND ec_active.certification_id = ec.certification_id
    AND ec_active.status = 'active'
    AND ec_active.expiry_date > CURDATE()
)

AND e.is_active = 1

".$scope_sql."

ORDER BY

CASE ec.status
WHEN 'expired' THEN 1
WHEN 'pending' THEN 2
WHEN 'verified' THEN 3
WHEN 'active' THEN 4
END ASC,

ec.expiry_date ASC

";

$monitor_stmt = $db->prepare($monitor_sql);
if ($monitor_stmt) {
	$verified_status = 'verified';
	$monitor_params = [];
	$monitor_types = '';
	$monitor_params = array_merge($monitor_params, $scope_params);
	$monitor_types .= $scope_types;
	bindStatementParams($monitor_stmt, $monitor_types, $monitor_params);
	$monitor_stmt->execute();
	$monitor_result = $monitor_stmt->get_result();
	if ($monitor_result) {
		while ($row = $monitor_result->fetch_assoc()) {
			$row['days_left'] = (int) $row['days_left'];
			$row['monitoring_badge'] = getWorkflowStatus($row);
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

require_once dirname(__DIR__) . '/layouts/header.php';
?>

<div class="certificate-status-page">
	<div class="page-hero">
		<div>
			<p class="eyebrow">Certificate Status</p>
			<h2><i class="fas fa-id-card"></i> Status Sertifikat</h2>
		</div>
		<div class="hero-actions">
			<span class="btn btn-secondary" style="pointer-events:none;">Monitoring; 2 Bulan</span>
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

	<!-- Status Filter Pills -->
	<div class="cert-status-filter" style="margin-bottom: 16px; display: flex; flex-wrap: wrap; gap: 8px; align-items: center;">
		<span style="font-size: 13px; font-weight: 600; color: #475569; margin-right: 4px;"><i class="fas fa-filter"></i> Filter Status:</span>
		<button class="cert-filter-pill active" data-filter="" onclick="filterCertStatus(this, '')" style="padding: 5px 14px; border-radius: 20px; border: 1.5px solid #cbd5e1; background: #1e40af; color: #fff; font-size: 12.5px; font-weight: 600; cursor: pointer; transition: all .2s;">All</button>
		<button class="cert-filter-pill" data-filter="EXPIRED" onclick="filterCertStatus(this, 'EXPIRED')" style="padding: 5px 14px; border-radius: 20px; border: 1.5px solid #fca5a5; background: #fff; color: #dc2626; font-size: 12.5px; font-weight: 600; cursor: pointer; transition: all .2s;"><i class="fas fa-times-circle"></i> Expired</button>
		<button class="cert-filter-pill" data-filter="WAITING REVIEWER" onclick="filterCertStatus(this, 'WAITING REVIEWER')" style="padding: 5px 14px; border-radius: 20px; border: 1.5px solid #fde68a; background: #fff; color: #d97706; font-size: 12.5px; font-weight: 600; cursor: pointer; transition: all .2s;"><i class="fas fa-clock"></i> Waiting Reviewer</button>
		<button class="cert-filter-pill" data-filter="REJECT" onclick="filterCertStatus(this, 'REJECT')" style="padding: 5px 14px; border-radius: 20px; border: 1.5px solid #fca5a5; background: #fff; color: #b91c1c; font-size: 12.5px; font-weight: 600; cursor: pointer; transition: all .2s;"><i class="fas fa-ban"></i> Reject</button>
		<button class="cert-filter-pill" data-filter="WAITING KTT" onclick="filterCertStatus(this, 'WAITING KTT')" style="padding: 5px 14px; border-radius: 20px; border: 1.5px solid #93c5fd; background: #fff; color: #1d4ed8; font-size: 12.5px; font-weight: 600; cursor: pointer; transition: all .2s;"><i class="fas fa-hourglass-half"></i> Waiting KTT</button>
		<button class="cert-filter-pill" data-filter="ACTIVE" onclick="filterCertStatus(this, 'ACTIVE')" style="padding: 5px 14px; border-radius: 20px; border: 1.5px solid #6ee7b7; background: #fff; color: #059669; font-size: 12.5px; font-weight: 600; cursor: pointer; transition: all .2s;"><i class="fas fa-check-circle"></i> Active</button>
		<button class="cert-filter-pill" data-filter="COMPLETED" onclick="filterCertStatus(this, 'COMPLETED')" style="padding: 5px 14px; border-radius: 20px; border: 1.5px solid #6ee7b7; background: #fff; color: #065f46; font-size: 12.5px; font-weight: 600; cursor: pointer; transition: all .2s;"><i class="fas fa-check-double"></i> Completed</button>
	</div>

	<div class="card cert-card">
		<div class="card-header cert-card-header">
			<h3><i class="fas fa-list"></i> Daftar Sertifikat</h3> <span id="cert-filter-label" style="font-size: 12px; color: #64748b;">All Status</span>
		</div>
		<div class="card-body cert-card-body">
			<?php if (!empty($certificates)): ?>
				<div class="table-responsive">
					<table class="table cert-table datatable" id="certStatusTable">
						<thead>
							<tr>
								<th>Employee</th>
								<th>Certification</th>
								<th>Certificate No.</th>
								<th>Expiry</th>
								<th>Monitoring</th>
								<th>Document</th>
								<th>Action</th>
								<th style="display:none;">status_filter</th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ($certificates as $cert):
								$status_filter_val = strtoupper($cert['monitoring_badge']['label']);
								if ($cert['status'] === 'active' && ($cert['appointment_status'] ?? '') === 'approved') {
									$status_filter_val = 'COMPLETED';
								}
							?>
								<tr>
									<td>	
										<strong><?php echo htmlspecialchars($cert['full_name']); ?></strong><br>
										<small><?php echo htmlspecialchars($cert['employee_code']); ?></small>
									</td>
									<td>
										<?php echo htmlspecialchars($cert['cert_name'] ?: '-'); ?><br>
										<small><?php echo htmlspecialchars($cert['cert_type'] ?: '-'); ?></small>
										<?php if (isset($cert['submission_count']) && (int)$cert['submission_count'] > 1): ?>
											<br><span class="badge badge-info" style="font-size: 0.75em; margin-top: 4px;"><i class="fas fa-sync-alt"></i> Resubmitted</span>
										<?php endif; ?>
									</td>
									<td><?php echo htmlspecialchars($cert['cert_number'] ?: '-'); ?></td>
									<td>
										<?php echo $cert['expiry_date'] ? date('d M Y', strtotime($cert['expiry_date'])) : '-'; ?><br>
										<small class="text-muted">
											<?php if ((int) $cert['days_left'] >= 0) { ?>
												<?php echo (int)$cert['days_left']; ?> hari
											<?php } else { ?>
												Lewat <?php echo abs((int) $cert['days_left']); ?> hari
											<?php } ?>
										</small>
									</td>
									<td>
										<span class="status-badge status-<?php echo htmlspecialchars($cert['monitoring_badge']['class']); ?>">
											<?php echo htmlspecialchars($cert['monitoring_badge']['label']); ?>
										</span>
									</td>
									<td>
										<?php if (!empty($cert['document_file'])): ?>
											<a class="btn btn-sm btn-info" href="<?php echo upload_url(htmlspecialchars($cert['document_file'])); ?>" target="_blank" rel="noopener noreferrer">
												<i class="fas fa-eye"></i> View
											</a>
										<?php else: ?>
											-
										<?php endif; ?>
									</td>
										<td>
											<?php if ($cert['status'] == 'expired') : ?>
											<a href="resubmit_certificate.php?id=<?php echo (int)$cert['employee_certification_id']; ?>"
											class="btn btn-warning btn-sm">
												<i class="fas fa-upload"></i>
												Resubmit
											</a>
											<?php elseif (
											$cert['status'] == 'pending' &&
											$cert['verification_status'] == 'rejected'
										) : ?>

											<a href="resubmit_certificate.php?id=<?php echo (int)$cert['employee_certification_id']; ?>"
											class="btn btn-warning btn-sm">
												<i class="fas fa-upload"></i>
												Resubmit
											</a>

										<?php elseif (
											$cert['status'] == 'pending' &&
											$cert['verification_status'] == 'pending'
										) : ?>

											<span class="badge badge-warning">
												Waiting Reviewer
											</span>

										<?php elseif (
											$cert['status'] == 'pending' &&
											$cert['verification_status'] == 'verified'
										) : ?>

											<span class="badge badge-info">
												Waiting KTT
											</span>

										<?php elseif (
											$cert['status'] == 'active' &&
											$cert['appointment_status'] == 'approved'
										) : ?>

											<span class="badge badge-success">
												Completed
											</span>

										<?php endif; ?>
									</td>
									<td style="display:none;"><?php echo htmlspecialchars($status_filter_val); ?></td>
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

<script>
function filterCertStatus(btn, statusVal) {
    document.querySelectorAll('.cert-filter-pill').forEach(function(p) {
        p.style.background = '#fff';
        p.classList.remove('active');
    });
    btn.style.background = '#1e40af';
    btn.style.color = '#fff';
    btn.classList.add('active');
    var label = document.getElementById('cert-filter-label');
    if (label) label.textContent = statusVal ? statusVal : 'All Status';
    if (typeof $ !== 'undefined' && $.fn.DataTable) {
        var table = $('#certStatusTable').DataTable();
        if (statusVal === '') {
            table.column(7).search('').draw();
        } else {
            table.column(7).search('^' + statusVal + '$', true, false).draw();
        }
    }
}
</script>

<?php require_once dirname(__DIR__) . '/layouts/footer.php'; ?>
