<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require 'bootstrap/app.php';

$es = ElasticsearchService::getInstance();
$q = '002/PO/MSM/07/2026';
$res = $es->searchAppointments($q, [], 0, 10);
echo json_encode($res, JSON_PRETTY_PRINT);
