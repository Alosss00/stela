<?php
$page_title = 'Resubmit Certificate';

require_once '../../includes/auth.php';
require_once '../../includes/db.php';

checkPageAccess(['user','department_user']);

$db = new Database();

if(session_status() == PHP_SESSION_NONE){
    session_start();
}

if(empty($_SESSION['csrf_token'])){
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$employee_id = isset($_GET['employee_id']) ? intval($_GET['employee_id']) : 0;
$certificate_id = isset($_GET['certificate_id']) ? intval($_GET['certificate_id']) : 0;
