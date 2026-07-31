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

// Handle POST request for Certificate Resubmit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'resubmit_expired_cert') {
    if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        http_response_code(403);
        die('CSRF token mismatch');
    }

    $cert_id = isset($_POST['cert_id']) ? intval($_POST['cert_id']) : 0;
    $cert_number = trim($_POST['cert_number'] ?? '');
    $issue_date = trim($_POST['issue_date'] ?? '');
    $expiry_date = trim($_POST['expiry_date'] ?? '');

    if ($cert_id <= 0 || empty($expiry_date)) {
        $error = 'Nomor sertifikat dan tanggal kedaluwarsa wajib diisi!';
    } elseif (!isset($_FILES['document_file']) || $_FILES['document_file']['error'] !== 0) {
        $error = 'Silakan pilih file sertifikat baru untuk diunggah.';
    } else {
        $allowed_extensions = ['pdf', 'jpg', 'jpeg', 'png'];
        $file_size = $_FILES['document_file']['size'];
        $max_size = 5 * 1024 * 1024;
        $file_extension = strtolower(pathinfo($_FILES['document_file']['name'], PATHINFO_EXTENSION));

        if (!in_array($file_extension, $allowed_extensions, true)) {
            $error = 'Format file tidak diperbolehkan! Gunakan format PDF, JPG, JPEG, atau PNG.';
        } elseif ($file_size > $max_size) {
            $error = 'Ukuran file terlalu besar! Maksimal 5MB.';
        } else {
            $upload_dir = '../../assets/uploads/certifications/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0775, true);
            }

            $new_filename = 'cert_resubmit_' . $cert_id . '_' . time() . '.' . $file_extension;
            $upload_path = $upload_dir . $new_filename;

            if (move_uploaded_file($_FILES['document_file']['tmp_name'], $upload_path)) {
                $document_file = 'uploads/certifications/' . $new_filename;
                
                $stmt_upd = $db->prepare("
                    UPDATE employee_certifications 
                    SET cert_number = ?,
                        issue_date = NULLIF(?, ''),
                        expiry_date = ?,
                        document_file = ?,
                        verification_status = 'pending',
                        status = 'pending',
                        updated_at = NOW() 
                    WHERE id = ?
                ");
                
                if ($stmt_upd) {
                    $stmt_upd->bind_param("ssssi", $cert_number, $issue_date, $expiry_date, $document_file, $cert_id);
                    if ($stmt_upd->execute()) {
                        $db->query("UPDATE employees e JOIN employee_certifications ec ON ec.employee_id = e.id SET e.resubmit_type = 'certificate', e.resubmit_date = NOW(), e.resubmit_count = e.resubmit_count + 1 WHERE ec.id = {$cert_id}");
                        
                        $message = 'Sertifikat berhasil diajukan ulang (resubmit) dan siap untuk diverifikasi!';
                    } else {
                        $error = 'Gagal memperbarui data sertifikat di database.';
                    }
                } else {
                    $error = 'Gagal memproses query update sertifikat.';
                }
            } else {
                $error = 'Gagal mengunggah file sertifikat ke server.';
            }
        }
    }
}

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
										$is_expired = ($cert['days_left'] <= 0 || $cert['status'] === 'expired' || $w_label === 'EXPIRED');

										if ($is_expired):
										?>
											<button type="button" class="btn btn-sm btn-primary btn-resubmit-modal" 
													data-bs-toggle="modal" 
													data-bs-target="#resubmitCertModal"
													data-cert-id="<?php echo (int)$cert['employee_certification_id']; ?>"
													data-cert-number="<?php echo htmlspecialchars($cert['cert_number'] ?: ''); ?>"
													data-issue-date="<?php echo htmlspecialchars($cert['issue_date'] ?: ''); ?>"
													data-expiry-date="<?php echo htmlspecialchars($cert['expiry_date'] ?: ''); ?>"
													data-employee-name="<?php echo htmlspecialchars($cert['full_name']); ?>"
													data-cert-name="<?php echo htmlspecialchars($cert['cert_name'] ?: 'Sertifikat'); ?>">
												<i class="fas fa-upload"></i> <span data-lang="resubmit">Resubmit</span>
											</button>
										<?php
										elseif ($w_label === 'ACTIVE'):
											echo '<span class="badge badge-success" data-lang="active">ACTIVE</span>';
										elseif ($w_label === 'WAITING REVIEWER'):
											echo '<a href="verify_employee.php?id=' . (int)$cert['employee_id'] . '" class="btn btn-sm btn-warning"><i class="fas fa-user-check"></i> Verify</a>';
										elseif ($w_label === 'WAITING KTT'):
											echo '<span class="badge badge-warning">WAITING KTT</span>';
										else:
											echo '<span class="badge badge-secondary">' . htmlspecialchars($w_label) . '</span>';
										endif;
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

<!-- Modal Resubmit Certificate -->
<div class="modal fade" id="resubmitCertModal" tabindex="-1" aria-labelledby="resubmitCertModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="resubmitCertModalLabel">
                    <i class="fas fa-upload"></i> Pengajuan Ulang Sertifikat (Resubmit)
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="certificate_status.php" enctype="multipart/form-data">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <input type="hidden" name="action" value="resubmit_expired_cert">
                    <input type="hidden" name="cert_id" id="modal_cert_id" value="">

                    <div class="alert alert-info py-2" style="font-size: 13px;">
                        <i class="fas fa-info-circle"></i> <span id="modal_cert_info">Unggah sertifikat baru untuk memperbarui sertifikat yang kedaluwarsa.</span>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold" style="font-size: 13px;">Nomor Sertifikat</label>
                        <input type="text" name="cert_number" id="modal_cert_number" class="form-control" placeholder="Contoh: CERT-2026-001" required>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold" style="font-size: 13px;">Tanggal Terbit</label>
                            <input type="date" name="issue_date" id="modal_issue_date" class="form-control">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold" style="font-size: 13px;">Tanggal Kedaluwarsa <span class="text-danger">*</span></label>
                            <input type="date" name="expiry_date" id="modal_expiry_date" class="form-control" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold" style="font-size: 13px;">Upload Dokumen Sertifikat Terbaru <span class="text-danger">*</span></label>
                        <input type="file" name="document_file" class="form-control" accept=".pdf,.jpg,.jpeg,.png" required>
                        <small class="text-muted d-block mt-1" style="font-size: 11.5px;">Format yang diperbolehkan: PDF, JPG, JPEG, PNG (Maksimal 5MB).</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-paper-plane"></i> Kirim Resubmit</button>
                </div>
            </form>
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

<script>
document.addEventListener('DOMContentLoaded', function() {
    var resubmitButtons = document.querySelectorAll('.btn-resubmit-modal');
    resubmitButtons.forEach(function(btn) {
        btn.addEventListener('click', function() {
            var certId = this.getAttribute('data-cert-id');
            var certNumber = this.getAttribute('data-cert-number');
            var issueDate = this.getAttribute('data-issue-date');
            var expiryDate = this.getAttribute('data-expiry-date');
            var empName = this.getAttribute('data-employee-name');
            var certName = this.getAttribute('data-cert-name');

            var modalCertId = document.getElementById('modal_cert_id');
            var modalCertNum = document.getElementById('modal_cert_number');
            var modalIssue = document.getElementById('modal_issue_date');
            var modalExpiry = document.getElementById('modal_expiry_date');
            var modalInfo = document.getElementById('modal_cert_info');

            if (modalCertId) modalCertId.value = certId || '';
            if (modalCertNum) modalCertNum.value = certNumber || '';
            if (modalIssue) modalIssue.value = issueDate || '';
            if (modalExpiry) modalExpiry.value = expiryDate || '';
            if (modalInfo) modalInfo.textContent = 'Resubmit ' + certName + ' untuk karyawan: ' + empName;
        });
    });
});
</script>

<?php require_once dirname(__DIR__) . '/layouts/footer.php'; ?>
