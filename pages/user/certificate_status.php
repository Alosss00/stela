<?php
$page_title = 'Certificate Status';
require_once '../../includes/auth.php';
require_once '../../includes/db.php';

checkPageAccess(['user', 'department_user']);

$db = new Database();

if (session_status() === PHP_SESSION_NONE) {
	session_start();
}
if (empty($_SESSION['csrf_token'])) {
	$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$message = '';
$error = '';
$role = $_SESSION['role'] ?? '';
$company_name = $_SESSION['company_name'] ?? '';
$department = $_SESSION['department'] ?? '';

$scope_conditions = [];
if ($role === 'department_user') {
	$scope_conditions[] = "TRIM(e.department) = '" . $db->escapeString(trim($department)) . "'";
} elseif ($role === 'user' && !empty($company_name)) {
	$scope_conditions[] = "TRIM(e.contractor_company) = '" . $db->escapeString(trim($company_name)) . "'";
} else {
	// Failsafe: never expose cross-scope data when session scope is incomplete.
	$scope_conditions[] = '1 = 0';
}

$scope_sql = !empty($scope_conditions) ? ' AND ' . implode(' AND ', $scope_conditions) : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'resubmit_file') {
	if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
		http_response_code(403);
		die('CSRF token mismatch');
	}

	$cert_id = isset($_POST['cert_id']) ? intval($_POST['cert_id']) : 0;

	$cert_check = $db->query("\
		SELECT ec.id, ec.employee_id, ec.document_file, ec.expiry_date, ec.verification_status, e.employee_code\
		FROM employee_certifications ec\
		JOIN employees e ON ec.employee_id = e.id\
		WHERE ec.id = $cert_id\
		AND (ec.expiry_date IS NOT NULL AND ec.expiry_date < CURDATE() OR ec.status = 'expired')\
		$scope_sql\
		LIMIT 1\
	");

	if (!$cert_check || $cert_check->num_rows === 0) {
		$error = 'Certificate record not found or not accessible.';
	} elseif (!isset($_FILES['document_file']) || $_FILES['document_file']['error'] !== 0) {
		$error = 'Please choose a certificate file to upload.';
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
			$cert_row = $cert_check->fetch_assoc();
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
				$update_sql = "UPDATE employee_certifications SET\n                    document_file = '" . $db->escapeString($document_file) . "',\n                    verification_status = 'pending',\n                    updated_at = NOW()\n                    WHERE id = $cert_id";

				if ($db->query($update_sql)) {
					$message = 'Certificate file has been resubmitted and is waiting for verification.';
				} else {
					$error = 'Failed to update certificate record.';
				}
			} else {
				$error = 'Failed to upload certificate file.';
			}
		}
	}
}

$where_clause = 'WHERE 1=1';
if (!empty($scope_conditions)) {
	$where_clause .= ' AND ' . implode(' AND ', $scope_conditions);
}
$where_clause .= " AND (ec.expiry_date IS NOT NULL AND ec.expiry_date < CURDATE() OR ec.status = 'expired')";

$certificates = $db->query("\
	SELECT ec.*,\
		   e.full_name,\
		   e.employee_code,\
		   e.position,\
		   e.department,\
		   e.contractor_company,\
		   c.cert_name,\
		   c.cert_type,\
		   c.issuing_authority,\
		   CASE\
				   WHEN ec.status = 'expired' THEN 'expired'\
				   WHEN ec.expiry_date IS NOT NULL AND ec.expiry_date < CURDATE() THEN 'expired'\
			   WHEN ec.verification_status = 'pending' THEN 'pending'\
			   WHEN ec.verification_status = 'rejected' THEN 'rejected'\
			   WHEN ec.verification_status = 'verified' THEN 'verified'\
			   ELSE 'pending'\
		   END as display_status\
	FROM employee_certifications ec\
	JOIN employees e ON ec.employee_id = e.id\
	LEFT JOIN certifications c ON ec.certification_id = c.id\
	$where_clause\
	ORDER BY\
			CASE WHEN ec.status = 'expired' OR (ec.expiry_date IS NOT NULL AND ec.expiry_date < CURDATE()) THEN 0 ELSE 1 END,\
		ec.expiry_date ASC,\
		ec.updated_at DESC\
");

$total_certificates = 0;
$expired_count = 0;
$pending_count = 0;
$verified_count = 0;
$rejected_count = 0;

if ($certificates) {
	$total_certificates = $certificates->num_rows;
	$certificates->data_seek(0);
	while ($row = $certificates->fetch_assoc()) {
		if ($row['display_status'] === 'expired') {
			$expired_count++;
		} elseif ($row['display_status'] === 'pending') {
			$pending_count++;
		} elseif ($row['display_status'] === 'verified') {
			$verified_count++;
		} elseif ($row['display_status'] === 'rejected') {
			$rejected_count++;
		}
	}
	$certificates->data_seek(0);
}

require_once '../../includes/header.php';
?>

<div class="certificate-status-page">
	<div class="page-hero">
		<div>
			<p class="eyebrow">Certificate Status</p>
			<h2><i class="fas fa-id-card"></i> Status Sertifikat</h2>
			<p>Data sertifikat kadaluarsa milik akun yang sedang login, siap untuk diresubmit atau diperbarui.</p>
		</div>
		<div class="hero-actions">
			<span class="btn btn-secondary" style="pointer-events:none;">Expired Only</span>
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
			<span class="stat-label">Expired Certificates</span>
		</div>
		<div class="stat-card stat-expired">
			<span class="stat-number"><?php echo $expired_count; ?></span>
			<span class="stat-label">Ready for Re-submit</span>
		</div>
	</div>

	<div class="card cert-card">
		<div class="card-header cert-card-header">
			<h3><i class="fas fa-list"></i> Daftar Sertifikat</h3>
		</div>
		<div class="card-body cert-card-body">
			<?php if ($certificates && $certificates->num_rows > 0): ?>
				<div class="table-responsive">
					<table class="table cert-table">
						<thead>
							<tr>
								<th>Employee</th>
								<th>Certification</th>
								<th>Certificate No.</th>
								<th>Expiry</th>
								<th>Status</th>
								<th>Document</th>
								<th>Action</th>
							</tr>
						</thead>
						<tbody>
							<?php while ($cert = $certificates->fetch_assoc()): ?>
								<?php
									$is_expired = !empty($cert['expiry_date']) && $cert['expiry_date'] < date('Y-m-d');
									$badge_status = $cert['display_status'];
								?>
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
										<?php if ($is_expired): ?>
											<small class="text-danger">Expired</small>
										<?php endif; ?>
									</td>
									<td>
										<span class="status-badge status-<?php echo htmlspecialchars($badge_status); ?>">
											<?php echo strtoupper(htmlspecialchars($badge_status)); ?>
										</span>
									</td>
									<td>
										<?php if (!empty($cert['document_file'])): ?>
											<a class="btn btn-sm btn-info" href="../../assets/<?php echo htmlspecialchars($cert['document_file']); ?>" target="_blank">
												<i class="fas fa-eye"></i> View
											</a>
										<?php else: ?>
											-
										<?php endif; ?>
									</td>
									<td>
										<?php if ($cert['display_status'] === 'expired'): ?>
											<button type="button"
													class="btn btn-primary btn-sm"
													onclick='openResubmitModal(<?php echo json_encode($cert, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>)'>
												<i class="fas fa-upload"></i> Re-submit
											</button>
										<?php else: ?>
											<span class="text-muted">No action</span>
										<?php endif; ?>
									</td>
								</tr>
							<?php endwhile; ?>
						</tbody>
					</table>
				</div>
			<?php else: ?>
				<div class="empty-state">
					<i class="fas fa-folder-open"></i>
					<p>No certificate data found for this scope.</p>
				</div>
			<?php endif; ?>
		</div>
	</div>
</div>

<div id="resubmitModal" class="modal">
	<div class="modal-content cert-modal">
		<div class="modal-header cert-modal-header">
			<h3><i class="fas fa-upload"></i> Re-submit Certificate File</h3>
			<span class="close" onclick="closeModal('resubmitModal')">&times;</span>
		</div>
		<form method="POST" enctype="multipart/form-data">
			<input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
			<input type="hidden" name="action" value="resubmit_file">
			<input type="hidden" name="cert_id" id="resubmit_cert_id">
			<div class="modal-body">
				<div class="form-group">
					<label>Employee</label>
					<input type="text" id="resubmit_employee" class="form-control" readonly>
				</div>
				<div class="form-group">
					<label>Certification</label>
					<input type="text" id="resubmit_certification" class="form-control" readonly>
				</div>
				<div class="form-group">
					<label>Current Document</label>
					<div id="resubmit_current_file" class="current-file-preview">-</div>
				</div>
				<div class="form-group">
					<label for="document_file">Upload New Certificate File <span class="text-danger">*</span></label>
					<input type="file" id="document_file" name="document_file" class="form-control" accept=".pdf,.jpg,.jpeg,.png" required>
					<small class="form-help">Allowed: PDF, JPG, JPEG, PNG. Max 5MB.</small>
				</div>
			</div>
			<div class="modal-footer cert-modal-footer">
				<button type="button" class="btn btn-secondary" onclick="closeModal('resubmitModal')">Cancel</button>
				<button type="submit" class="btn btn-primary">Upload</button>
			</div>
		</form>
	</div>
</div>

<script>
function openResubmitModal(data) {
	document.getElementById('resubmit_cert_id').value = data.id || '';
	document.getElementById('resubmit_employee').value = (data.full_name || '-') + ' (' + (data.employee_code || '-') + ')';
	document.getElementById('resubmit_certification').value = data.cert_name || '-';

	const currentFile = document.getElementById('resubmit_current_file');
	if (data.document_file) {
		currentFile.innerHTML = '<a href="../../assets/' + data.document_file + '" target="_blank">View current file</a>';
	} else {
		currentFile.textContent = '-';
	}

	openModal('resubmitModal');
}
</script>

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

.cert-modal {
	max-width: 560px;
}

.cert-modal-header {
	background: #37474F;
	color: #fff;
}

.cert-modal-header .close {
	color: #fff;
}

.cert-modal-footer {
	display: flex;
	justify-content: flex-end;
	gap: 10px;
	padding: 16px 20px 20px;
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
