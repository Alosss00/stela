<?php require 'bootstrap/app.php'; print_r((new Database())->query("SELECT id, verification_status, resubmit_date FROM employees WHERE employee_code = '178'")->fetch_assoc());
