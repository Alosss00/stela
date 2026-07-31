<?php
/**
 * API Routes Definitions & Dispatcher
 */

require_once dirname(__DIR__) . '/bootstrap/app.php';

$api_map = [
    '/api/get_appointment_details' => 'api/get_appointment_details.php',
    '/api/get_approval_detail' => 'api/get_approval_detail.php',
    '/api/get_employee_certs' => 'api/get_employee_certs.php',
    '/api/get_sub_competencies' => 'api/get_sub_competencies.php',
    '/api/search_elasticsearch' => 'api/search_elasticsearch.php',
    '/api/update_employee_status' => 'api/update_employee_status.php',
];

function dispatch_api_route($path) {
    global $api_map;
    $clean_path = rtrim($path, '/');
    if (isset($api_map[$clean_path])) {
        $apiView = VIEW_PATH . '/' . ltrim($api_map[$clean_path], '/');
        if (file_exists($apiView)) {
            require_once $apiView;
            return true;
        }
    }
    return false;
}
