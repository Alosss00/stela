<?php
/**
 * REST API Search Endpoint powered by Elasticsearch with MySQL Fallback
 * Returns search results for Employees and Appointments in JSON format.
 */

// Disable HTML error output to prevent corrupting JSON responses
ini_set('display_errors', '0');
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);

header('Content-Type: application/json; charset=utf-8');

try {
    if (!defined('BASE_PATH')) {
        $bootstrapPath = dirname(__DIR__, 3) . '/bootstrap/app.php';
        if (file_exists($bootstrapPath)) {
            require_once $bootstrapPath;
        }
    }

    $esServicePath = dirname(__DIR__, 3) . '/app/Services/ElasticsearchService.php';
    if (file_exists($esServicePath)) {
        require_once $esServicePath;
    }

    // Authentication check
    if (function_exists('is_logged_in') && !is_logged_in()) {
        http_response_code(401);
        echo json_encode([
            'status' => 'error',
            'message' => 'Unauthenticated'
        ]);
        exit();
    }

    $queryText = trim($_GET['q'] ?? $_GET['query'] ?? '');
    $target = trim($_GET['target'] ?? 'employees'); // 'employees' or 'appointments'
    $company = trim($_GET['company'] ?? '');
    $competencyType = trim($_GET['competency_type'] ?? '');
    $status = trim($_GET['status'] ?? '');
    $department = trim($_GET['department'] ?? '');
    $page = max(1, (int)($_GET['page'] ?? 1));
    $limit = min(100, max(1, (int)($_GET['limit'] ?? 10)));
    $from = ($page - 1) * $limit;

    // 1. Try Elasticsearch Search first if class exists
    if (class_exists('ElasticsearchService')) {
        $es = ElasticsearchService::getInstance();
        if ($es && method_exists($es, 'isAvailable') && $es->isAvailable()) {
            $filters = [];
            if (!empty($company)) $filters['contractor_company'] = $company;
            if (!empty($competencyType)) $filters['competency_type'] = $competencyType;
            if (!empty($department)) $filters['department'] = $department;
            if (!empty($status)) {
                if ($target === 'employees') {
                    $filters['approval_status'] = $status;
                } else {
                    $filters['status'] = $status;
                }
            }

            if ($target === 'appointments') {
                $result = $es->searchAppointments($queryText, $filters, $from, $limit);
            } else {
                $result = $es->searchEmployees($queryText, $filters, $from, $limit);
            }

            if ($result !== false) {
                $totalHits = (int)($result['total'] ?? 0);
                
                // If ES has hits OR if user is explicitly searching with a keyword, return ES result
                if ($totalHits > 0 || !empty($queryText)) {
                    $items = $result['items'] ?? [];
                    if ($target === 'employees' && !empty($items) && class_exists('Database')) {
                        try {
                            $dbHydrate = new Database();
                            $ids = array_filter(array_map(function($i) { return (int)($i['id'] ?? 0); }, $items));
                            if (!empty($ids)) {
                                $idsStr = implode(',', $ids);
                                $dbRes = $dbHydrate->query("SELECT e.id, e.competency_name, e.sub_competency, e.verified_date, u.full_name as verified_by_name 
                                                     FROM employees e 
                                                     LEFT JOIN users u ON e.verified_by = u.id 
                                                     WHERE e.id IN ($idsStr)");
                                $metaMap = [];
                                if ($dbRes) {
                                    while ($row = $dbRes->fetch_assoc()) {
                                        $metaMap[$row['id']] = $row;
                                    }
                                }
                                foreach ($items as &$item) {
                                    $id = (int)($item['id'] ?? 0);
                                    $status = strtolower($item['approval_status'] ?? $item['verification_status'] ?? 'pending');
                                    if ($status === 'pending') {
                                        $item['verified_by_name'] = null;
                                        $item['verified_date'] = null;
                                    } else if (isset($metaMap[$id])) {
                                        if (empty($item['competency_name'])) $item['competency_name'] = $metaMap[$id]['competency_name'] ?? '';
                                        if (empty($item['sub_competency'])) $item['sub_competency'] = $metaMap[$id]['sub_competency'] ?? '';
                                        if (empty($item['verified_by_name'])) $item['verified_by_name'] = $metaMap[$id]['verified_by_name'] ?? '';
                                        if (empty($item['verified_date'])) $item['verified_date'] = $metaMap[$id]['verified_date'] ?? '';
                                    }
                                }
                                unset($item);
                            }
                        } catch (\Throwable $t) {}
                    }

                    $totalPages = $limit > 0 ? (int)ceil($totalHits / $limit) : 1;
                    echo json_encode([
                        'status' => 'success',
                        'source' => 'elasticsearch',
                        'query' => $queryText,
                        'page' => $page,
                        'limit' => $limit,
                        'total' => $totalHits,
                        'total_pages' => max(1, $totalPages),
                        'items' => $items
                    ]);
                    exit();
                }
                // If totalHits is 0 and queryText is empty, Bonsai index is not yet synced!
                // Fall through to MySQL and auto-sync in background.
            }
        }
    }

    // 2. MySQL Fallback Search if Elasticsearch is unavailable, empty, or fails
    if (!class_exists('Database')) {
        throw new Exception("Database class is missing");
    }

    $db = new Database();

    // Auto-sync MySQL data to Bonsai in background if Bonsai was empty
    if (class_exists('ElasticsearchService')) {
        try {
            $esSync = ElasticsearchService::getInstance();
            if ($esSync && $esSync->isAvailable()) {
                if ($target === 'appointments') {
                    $esSync->bulkIndexAppointments($db);
                } else {
                    $esSync->bulkIndexEmployees($db);
                }
            }
        } catch (\Throwable $t) {
            // Ignore background sync errors
        }
    }

    $items = [];
    $total = 0;

    if ($target === 'employees') {
        $where = ["e.is_active = 1"];
        
        if (!empty($queryText)) {
            $safeQ = $db->escapeString($queryText);
            $where[] = "(e.employee_code LIKE '%$safeQ%' OR e.full_name LIKE '%$safeQ%' OR e.position LIKE '%$safeQ%' OR e.contractor_company LIKE '%$safeQ%' OR e.department LIKE '%$safeQ%')";
        }
        
        if (!empty($company)) {
            $safeCompany = $db->escapeString($company);
            $where[] = "e.contractor_company = '$safeCompany'";
        }

        if (!empty($competencyType)) {
            $safeType = $db->escapeString($competencyType);
            $where[] = "e.competency_type = '$safeType'";
        }

        if (!empty($department)) {
            $safeDept = $db->escapeString($department);
            $where[] = "e.department = '$safeDept'";
        }

        if (!empty($status)) {
            $safeStatus = $db->escapeString($status);
            $where[] = "(e.verification_status = '$safeStatus')";
        }

        $whereClause = implode(' AND ', $where);

        // Count
        $countRes = $db->query("SELECT COUNT(*) as cnt FROM employees e WHERE $whereClause");
        if ($countRes) {
            $total = (int)($countRes->fetch_assoc()['cnt'] ?? 0);
        }

        // Query items: NULL for verified_by_name and verified_date if status is pending
        $sql = "SELECT e.id, e.employee_code, e.full_name, e.position, e.department, e.contractor_company, 
                       e.competency_type, e.competency_name, e.ruang_lingkup, e.sub_competency, e.supervision_area, 
                       e.verification_status as approval_status, e.verification_status, 
                       CASE WHEN e.verification_status = 'pending' THEN NULL ELSE e.verified_date END as verified_date, 
                       e.created_at,
                       CASE WHEN e.verification_status = 'pending' THEN NULL ELSE u.full_name END as verified_by_name
                FROM employees e 
                LEFT JOIN users u ON e.verified_by = u.id 
                WHERE $whereClause ORDER BY e.id DESC LIMIT $from, $limit";
        $res = $db->query($sql);
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $items[] = $row;
            }
        }
    } else { // Appointments
        $where = ["1=1"];
        
        if (!empty($queryText)) {
            $safeQ = $db->escapeString($queryText);
            $where[] = "(a.appointment_number LIKE '%$safeQ%' OR e.full_name LIKE '%$safeQ%' OR e.contractor_company LIKE '%$safeQ%')";
        }

        if (!empty($company)) {
            $safeCompany = $db->escapeString($company);
            $where[] = "e.contractor_company = '$safeCompany'";
        }

        if (!empty($competencyType)) {
            $safeType = $db->escapeString($competencyType);
            $where[] = "a.competency_type = '$safeType'";
        }

        if (!empty($status)) {
            $safeStatus = $db->escapeString($status);
            $where[] = "a.status = '$safeStatus'";
        }

        $whereClause = implode(' AND ', $where);

        $countRes = $db->query("SELECT COUNT(*) as cnt FROM appointments a LEFT JOIN employees e ON a.employee_id = e.id WHERE $whereClause");
        if ($countRes) {
            $total = (int)($countRes->fetch_assoc()['cnt'] ?? 0);
        }

        $sql = "SELECT a.id, a.appointment_number, a.employee_id, e.full_name as employee_name, e.contractor_company, a.competency_type, a.status, a.created_at 
                FROM appointments a 
                LEFT JOIN employees e ON a.employee_id = e.id 
                WHERE $whereClause ORDER BY a.id DESC LIMIT $from, $limit";
        $res = $db->query($sql);
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $items[] = $row;
            }
        }
    }

    $totalPages = $limit > 0 ? (int)ceil($total / $limit) : 1;

    echo json_encode([
        'status' => 'success',
        'source' => 'mysql_fallback',
        'query' => $queryText,
        'page' => $page,
        'limit' => $limit,
        'total' => $total,
        'total_pages' => max(1, $totalPages),
        'items' => $items
    ]);

} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
