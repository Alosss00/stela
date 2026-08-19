<?php 
require "bootstrap/app.php"; 
$_SESSION['user_id'] = 1;
$_SESSION['role'] = 'admin';
$_SESSION['permissions'] = ['admin.access'];
$_GET['target'] = 'appointments';
$_GET['status'] = 'rejected_by_ktt';
$start = microtime(true); 
ob_start();
require "resources/views/api/search_elasticsearch.php";
$res = ob_get_clean();
echo "\nTime: " . (microtime(true) - $start) . "s\n"; 
echo substr($res, 0, 100);
