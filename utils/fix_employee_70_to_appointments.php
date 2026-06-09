<?php
/**
 * Fix Employee ID 70 - Move to Appointments.php
 *
 * This script updates employee id 70 and its appointment
 * to appear in appointments.php (status = 'draft')
 */

require_once 'includes/db.php';

$db = new Database();
$employee_id = 70;

echo "<h2>Fix Employee ID 70 - Move to Appointments</h2>\n";
echo "<pre>\n";

// Step 1: Get employee data
echo "Step 1: Checking Employee ID $employee_id...\n";
$employee = $db->query("SELECT id, employee_code, full_name, verification_status FROM employees WHERE id = $employee_id")->fetch_assoc();

if (!$employee) {
    echo "ERROR: Employee ID $employee_id not found!\n";
    exit;
}

echo "Found: {$employee['full_name']} ({$employee['employee_code']})\n";
echo "Current verification_status: {$employee['verification_status']}\n\n";

// Step 2: Check if appointment exists
echo "Step 2: Checking for existing appointment...\n";
$appointment = $db->query("
    SELECT id, appointment_number, status,
           requires_ktt_msm_review, requires_ktt_ttn_review,
           ktt_msm_status, ktt_ttn_status
    FROM appointments
    WHERE employee_id = $employee_id
    ORDER BY id DESC
    LIMIT 1
")->fetch_assoc();

if ($appointment) {
    echo "Found Appointment:\n";
    echo "  - ID: {$appointment['id']}\n";
    echo "  - Number: {$appointment['appointment_number']}\n";
    echo "  - Status: {$appointment['status']}\n";
    echo "  - KTT MSM Status: {$appointment['ktt_msm_status']}\n";
    echo "  - KTT TTN Status: {$appointment['ktt_ttn_status']}\n";
    echo "  - Requires KTT MSM Review: {$appointment['requires_ktt_msm_review']}\n";
    echo "  - Requires KTT TTN Review: {$appointment['requires_ktt_ttn_review']}\n\n";

    // Step 3: Update appointment status to 'draft'
    echo "Step 3: Updating appointment status to 'draft'...\n";

    $appointment_id = $appointment['id'];
    $update_sql = "UPDATE appointments SET
                   status = 'draft',
                   updated_at = NOW()
                   WHERE id = $appointment_id";

    if ($db->query($update_sql)) {
        echo "SUCCESS: Appointment status updated to 'draft'\n";
        echo "  - Appointment will now appear in appointments.php\n";
        echo "  - Admin can send to KTT from there\n\n";
    } else {
        echo "ERROR: Failed to update appointment status\n";
        echo "SQL Error: " . $db->getConnection()->error . "\n";
    }

} else {
    echo "No appointment found for this employee.\n";
    echo "Creating new appointment...\n\n";

    // Step 3: Create new appointment if doesn't exist
    echo "Step 3: Creating new appointment...\n";

    // Get employee competency data
    $emp_data = $db->query("SELECT competency_type, ruang_lingkup FROM employees WHERE id = $employee_id")->fetch_assoc();
    $competency_type = $emp_data['competency_type'];
    $ruang_lingkup = $emp_data['ruang_lingkup'];

    // Map competency type to code
    $type_codes = [
        'pengawas_operasional' => 'PO',
        'pengawas_teknis' => 'PT',
        'tenaga_teknis' => 'TT'
    ];

    // Map ruang_lingkup to code
    $scope_code = 'UNK';
    if (stripos($ruang_lingkup, 'MSM') !== false && stripos($ruang_lingkup, 'TTN') !== false) {
        $scope_code = 'MSM/TTN';
    } elseif (stripos($ruang_lingkup, 'MSM') !== false) {
        $scope_code = 'MSM';
    } elseif (stripos($ruang_lingkup, 'TTN') !== false) {
        $scope_code = 'TTN';
    }

    $type_code = $type_codes[$competency_type] ?? 'UNK';

    // Get month and year - WITH LEADING ZERO
    $month = date('m'); // 01-12 with leading zero
    $year = date('Y');
    $today = date('Y-m-d');

    // Get last number for this combination
    $last_appointment = $db->query("
        SELECT COALESCE(MAX(CAST(SUBSTRING_INDEX(appointment_number, '/', 1) AS UNSIGNED)), 0) as last_num
        FROM appointments
        WHERE appointment_number LIKE '%/$type_code/$scope_code/$month/$year'
    ")->fetch_assoc();

    $next_num = ($last_appointment['last_num'] ?? 0) + 1;
    $appointment_number = sprintf('%03d/%s/%s/%s/%s', $next_num, $type_code, $scope_code, $month, $year);

    echo "Generated appointment number: $appointment_number\n\n";

    // Get earliest certification expiry
    $cert_expiry = $db->query("
        SELECT MIN(expiry_date) as earliest_expiry
        FROM employee_certifications
        WHERE employee_id = $employee_id
        AND verification_status = 'verified'
        AND expiry_date IS NOT NULL
    ")->fetch_assoc();

    $expiry_date = $cert_expiry['earliest_expiry'] ?? null;

    // Insert appointment
    if ($expiry_date) {
        $sql_appointment = "INSERT INTO appointments
                          (appointment_number, employee_id, position_id, appointment_date,
                           effective_date, expiry_date, status, auto_generated, created_by, notes)
                          VALUES ('$appointment_number', $employee_id, NULL, '$today',
                                  '$today', '$expiry_date', 'draft', 1, 1, 'Created via fix script for employee id $employee_id')";
    } else {
        $sql_appointment = "INSERT INTO appointments
                          (appointment_number, employee_id, position_id, appointment_date,
                           effective_date, status, auto_generated, created_by, notes)
                          VALUES ('$appointment_number', $employee_id, NULL, '$today',
                                  '$today', 'draft', 1, 1, 'Created via fix script for employee id $employee_id')";
    }

    if ($db->query($sql_appointment)) {
        $appointment_id = $db->lastInsertId();

        // Update employee with appointment number
        $db->query("UPDATE employees SET appointment_number = '$appointment_number' WHERE id = $employee_id");

        echo "SUCCESS: New appointment created\n";
        echo "  - Appointment ID: $appointment_id\n";
        echo "  - Appointment Number: $appointment_number\n";
        echo "  - Status: draft\n";
        echo "  - Appointment will appear in appointments.php\n\n";
    } else {
        echo "ERROR: Failed to create appointment\n";
        echo "SQL Error: " . $db->getConnection()->error . "\n";
    }
}

// Step 4: Verify final status
echo "Step 4: Verifying final status...\n";
$final_check = $db->query("
    SELECT a.id, a.appointment_number, a.status, e.full_name
    FROM appointments a
    JOIN employees e ON a.employee_id = e.id
    WHERE a.employee_id = $employee_id
    ORDER BY a.id DESC
    LIMIT 1
")->fetch_assoc();

if ($final_check) {
    echo "Final Status:\n";
    echo "  - Employee: {$final_check['full_name']}\n";
    echo "  - Appointment Number: {$final_check['appointment_number']}\n";
    echo "  - Appointment Status: {$final_check['status']}\n";

    if ($final_check['status'] == 'draft') {
        echo "\n✅ SUCCESS: Employee ID $employee_id is now in appointments.php!\n";
        echo "Admin can now send this appointment to KTT for review.\n";
    } else {
        echo "\n⚠️  WARNING: Status is '{$final_check['status']}' instead of 'draft'\n";
    }
} else {
    echo "ERROR: Could not verify final status\n";
}

echo "\n</pre>\n";
echo "<p><a href='appointments.php'>Go to Appointments Page</a></p>\n";
?>

