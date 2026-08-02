<?php
$page_title = 'Employee Status';
require_once dirname(__DIR__, 3) . '/app/Helpers/auth_helper.php';
// Included via bootstrap/app.php
// Included via bootstrap/app.php

$db = new Database();
$message = '';
$error = '';


// Get filter from URL
$filter = isset($_GET['filter']) ? $db->escapeString($_GET['filter']) : '';


$where_clause = "e.is_active = 1";

if (!empty($company_name)) {
    $where_clause .= " AND e.contractor_company = '$company_name'";
}

if (!empty($filter)) {
    $where_clause .= " AND e.employee_status = '$filter'";
}

// Get all employees with verification status and KTT rejection awareness
$employees = $db->query("
SELECT
    e.id,
    e.employee_code,
    e.full_name,
    e.contractor_company,
    e.position,
    e.competency_type,
    e.competency_name,
    e.employee_status,
    e.resign_date,

    a.appointment_number,
    a.appointment_date

FROM employees e

INNER JOIN appointments a
    ON a.employee_id = e.id

WHERE
    $where_clause
    AND a.status='approved'
    AND a.is_current=1

ORDER BY e.full_name ASC
");

require_once dirname(__DIR__) . '/layouts/header.php';

// Get statistics
$total_employees =
$db->query("
SELECT COUNT(*) total
FROM employees
WHERE is_active=1
")->fetch_assoc()['total'];

$active_count =
$db->query("
SELECT COUNT(*) total
FROM employees
WHERE employee_status='active'
")->fetch_assoc()['total'];

$resigned_count =
$db->query("
SELECT COUNT(*) total
FROM employees
WHERE employee_status='resigned'
")->fetch_assoc()['total'];

$inactive_count =
$db->query("
SELECT COUNT(*) total
FROM employees
WHERE is_active=0
")->fetch_assoc()['total'];

?>

<div class="employees-admin-container">
    <!-- Page Header -->
    <div class="page-header-emp-admin">
    <div class="header-left">
        <h2><i class="fas fa-user-clock"></i>Employee Status</h2>
        <p>Manage Active and Resigned Employees</p>
    </div>
</div>

    <?php if (!empty($filter)): ?>
    <div class="alert alert-info alert-custom-emp"> 
        <i class="fas fa-filter"></i>
        <div>
            <strong data-lang="active-filter">Active Filter:</strong>
            <p><span data-lang="displaying-employees-status">Displaying employees with status:</span> <strong>
                <?php
                $filter_labels = [
                    'active' => 'Active',
                    'resigned' => 'Resigned'
                ];
                echo $filter_labels[$filter] ?? $filter;
                ?>
            </strong></p>
        </div>
        <a href="employees_status.php" class="btn btn-sm btn-secondary" style="margin-left: auto;">
            <i class="fas fa-times"></i> <span data-lang="remove-filter">Remove Filter</span>
        </a>
    </div>
    <?php endif; ?>
    
    <?php if ($message): ?>
    <div class="alert alert-success alert-custom-emp">
        <i class="fas fa-check-circle"></i>
        <div>
            <strong data-lang="success">Success!</strong>
            <p><?php echo htmlspecialchars($message); ?></p>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($error): ?>
    <div class="alert alert-error alert-custom-emp">
        <i class="fas fa-exclamation-circle"></i>
        <div>
            <strong data-lang="error">Error!</strong>
            <p><?php echo htmlspecialchars($error); ?></p>
        </div>
    </div>
    <?php endif; ?>

    <!-- Statistics Cards - Overall -->
<div class="stats-section-title">
    <h4><span data-lang="overall-statistics">Overall Statistics</span></h4>
</div>

<div class="stats-grid-emp">

    <div class="stat-box-emp stat-total">
        <div class="stat-icon-emp">
            <i class="fas fa-users"></i>
        </div>

        <div class="stat-info">
            <div class="stat-number"><?= $total_employees ?></div>
            <div class="stat-text">Total Employee</div>
        </div>
    </div>

    <div class="stat-box-emp stat-active">
        <div class="stat-icon-emp">
            <i class="fas fa-user-check"></i>
        </div>

        <div class="stat-info">
            <div class="stat-number"><?= $active_count ?></div>
            <div class="stat-text">Active</div>
        </div>
    </div>

    <div class="stat-box-emp stat-resigned">
        <div class="stat-icon-emp">
            <i class="fas fa-user-times"></i>
        </div>

        <div class="stat-info">
            <div class="stat-number"><?= $resigned_count ?></div>
            <div class="stat-text">Resigned</div>
        </div>
    </div>

    <div class="stat-box-emp stat-inactive">
        <div class="stat-icon-emp">
            <i class="fas fa-user-slash"></i>
        </div>

        <div class="stat-info">
            <div class="stat-number"><?= $inactive_count ?></div>

        <div class="modal-content">

            <div class="modal-header">

                <h5 class="modal-title">

                    Employee Resignation

                </h5>

                <button
                    type="button"
                    class="btn-close"
                    data-bs-dismiss="modal">
                </button>

            </div>

            <div class="modal-body">

                <input type="hidden" id="employee_id">

                <div class="mb-3">

                    <label>Employee</label>

                    <input
                        type="text"
                        id="employee_name"
                        class="form-control"
                        readonly>

                </div>

                <div class="mb-3">

                    <label>Company</label>

                    <input
                        type="text"
                        id="employee_company"
                        class="form-control"
                        readonly>

                </div>

                <div class="mb-3">

                    <label>Appointment Number</label>

                    <input
                        type="text"
                        id="appointment_number"
                        class="form-control"
                        readonly>

                </div>

                <div class="mb-3">

                    <label>Resign Date</label>

                    <input
                        type="date"
                        id="resign_date"
                        class="form-control">

                </div>

                <div class="mb-3">

                    <label>Resign Reason</label>

                    <textarea
                        id="resign_reason"
                        rows="4"
                        class="form-control"></textarea>

                </div>

            </div>

            <div class="modal-footer">

                <button
                    class="btn btn-secondary"
                    data-bs-dismiss="modal">

                    Cancel

                </button>

                <button
                    class="btn btn-danger"
                    id="saveStatusBtn">

                    Confirm Resign

                </button>

            </div>

        </div>

    </div>

</div>

<script>
document.addEventListener("DOMContentLoaded", function () {

    // Event Delegation for .resign-btn (works across all zoom levels and DataTables redraws)
    document.addEventListener("click", function(e) {
        const button = e.target.closest(".resign-btn");
        if (button) {
            e.preventDefault();
            e.stopPropagation();

            document.getElementById("employee_id").value = button.dataset.id || "";
            document.getElementById("employee_name").value = button.dataset.name || "";
            document.getElementById("employee_company").value = button.dataset.company || "";
            document.getElementById("appointment_number").value = button.dataset.appointment || "";

            document.getElementById("resign_date").value = "";
            document.getElementById("resign_reason").value = "";

            const modalEl = document.getElementById("employeeStatusModal");
            if (modalEl) {
                const modal = bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl);
                modal.show();
            }
        }
    });

    const saveBtn = document.getElementById("saveStatusBtn");
    if (saveBtn) {
        saveBtn.addEventListener("click", function(){
            const employeeId = document.getElementById("employee_id").value;
            const resignDate = document.getElementById("resign_date").value;
            const resignReason = document.getElementById("resign_reason").value.trim();

            if (!resignDate) {
                alert("Please select resign date.");
                return;
            }

            if (!resignReason) {
                alert("Please enter resign reason.");
                return;
            }

            if (!confirm("Are you sure this employee has resigned?")) {
                return;
            }

            const formData = new FormData();
            formData.append("action", "resign_employee");
            formData.append("employee_id", employeeId);
            formData.append("resign_date", resignDate);
            formData.append("resign_reason", resignReason);

            fetch(window.location.href, {
                method: "POST",
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data && data.success) {
                    alert("Employee status updated to Resigned!");
                    location.reload();
                } else {
                    alert("Failed to update status.");
                }
            })
            .catch(err => {
                alert("An error occurred. Please try again.");
            });
        });
    }

}); 
</script>



<?php require_once dirname(__DIR__) . '/layouts/footer.php'; ?>
