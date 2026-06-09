<?php
/**
 * API: Get Sub-Competencies by Competency ID/Name
 * Returns sub-competencies for a selected competency in JSON format
 */

require_once '../includes/auth.php';
require_once '../includes/db.php';

header('Content-Type: application/json');

$response = [
    'success' => false,
    'data' => [],
    'message' => 'Invalid request'
];

try {
    // Check if request method is POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }

    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['competency_id']) && !isset($input['competency_name'])) {
        throw new Exception('Competency ID or name is required');
    }

    $db = new Database();
    
    // Build query to get sub-competencies
    $query = "SELECT csc.id, csc.sub_competency_name, csc.sub_competency_level, csc.description 
              FROM competency_sub_competencies csc
              JOIN competencies c ON csc.competency_id = c.id
              WHERE csc.is_active = 1";
    
    if (isset($input['competency_id'])) {
        $competency_id = intval($input['competency_id']);
        $query .= " AND csc.competency_id = $competency_id";
    } else {
        $competency_name = $db->escapeString($input['competency_name']);
        $query .= " AND c.competency_name = '$competency_name'";
    }
    
    $query .= " ORDER BY csc.sub_competency_level ASC";
    
    $result = $db->query($query);
    
    if (!$result) {
        throw new Exception('Database query failed');
    }
    
    $sub_competencies = [];
    while ($row = $result->fetch_assoc()) {
        $sub_competencies[] = [
            'id' => $row['id'],
            'name' => $row['sub_competency_name'],
            'level' => $row['sub_competency_level'],
            'description' => $row['description']
        ];
    }
    
    $response['success'] = true;
    $response['data'] = $sub_competencies;
    $response['message'] = 'Sub-competencies retrieved successfully';
    
} catch (Exception $e) {
    $response['success'] = false;
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
?>

