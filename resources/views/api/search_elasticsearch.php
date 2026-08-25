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
    
    $authHelperPath = dirname(__DIR__, 3) . '/app/Helpers/auth_helper.php';
    if (file_exists($authHelperPath)) {
        require_once $authHelperPath;
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

    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $queryText = trim($_GET['q'] ?? $_GET['query'] ?? '');
    $target = trim($_GET['target'] ?? 'employees'); // 'employees', 'appointments', or 'employee_status'
    $company = trim($_GET['company'] ?? '');
    $competencyType = trim($_GET['competency_type'] ?? '');
    $status = trim($_GET['status'] ?? '');
    $employeeStatus = trim($_GET['employee_status'] ?? '');
    $department = trim($_GET['department'] ?? '');
    $page = max(1, (int)($_GET['page'] ?? 1));
    $limit = min(100, max(1, (int)($_GET['limit'] ?? 10)));
    $from = ($page - 1) * $limit;

    // Role-based Search Scope Enforcement:
    // Non-admin roles (User, Dept, etc.) can ONLY search data input by the current user.
    // Admin roles (admin, superadmin) can search across ALL data.
    $userCompany = $_SESSION['company_name'] ?? '';
    $userDept = $_SESSION['department'] ?? '';
    $currentUserId = (int)($_SESSION['user_id'] ?? 0);
    $isAdmin = isSuperadmin() || hasPermission('admin.access');
    $createdByFilter = null;
    if (!$isAdmin) {
        if (!empty($userCompany)) {
            $company = $userCompany;
        }
        if (!empty($userDept)) {
            $department = $userDept;
        }
        if (isset($_GET['created_by']) && is_numeric($_GET['created_by'])) {
            $createdByFilter = (int)$_GET['created_by'];
        }
    } elseif (isset($_GET['created_by']) && is_numeric($_GET['created_by'])) {
        $createdByFilter = (int)$_GET['created_by'];
    }

    // Initialize Database instance
    $db = class_exists('Database') ? new Database() : null;

    // 1. Try Elasticsearch Search first if class exists (except for employee_status which requires strict direct DB joining for approved appointments)
    if ($target !== 'employee_status' && class_exists('ElasticsearchService')) {
        $es = ElasticsearchService::getInstance();
        if ($es && method_exists($es, 'isAvailable') && $es->isAvailable()) {
            $filters = [];
            if (!empty($company)) $filters['contractor_company'] = $company;
            if (!empty($competencyType)) $filters['competency_type'] = $competencyType;
            if (!empty($department)) $filters['department'] = $department;
            if (!empty($createdByFilter)) $filters['created_by'] = $createdByFilter;
            if (!empty($employeeStatus)) $filters['employee_status'] = $employeeStatus;
            if (!empty($status)) {
                if ($target === 'employees' || $target === 'employee_status') {
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
                
                // If ES has hits, return ES result. If 0 hits, fall through to MySQL fallback search
                if ($totalHits > 0) {
                    $items = $result['items'] ?? [];
                    if (($target === 'employees' || $target === 'employee_status') && !empty($items) && class_exists('Database')) {
                        try {
                            $dbHydrate = new Database();
                            $ids = array_filter(array_map(function($i) { return (int)($i['id'] ?? 0); }, $items));
                            if (!empty($ids)) {
                                $idsStr = implode(',', $ids);
                                // Always fetch fresh data from MySQL to avoid showing stale Elasticsearch cache
                                $dbRes = $dbHydrate->query("SELECT e.id, e.verification_status as approval_status, e.competency_name, e.sub_competency,
                                                             e.verified_date, e.employee_status, e.resign_date,
                                                             CASE WHEN e.verification_status = 'pending' THEN NULL ELSE u.full_name END as verified_by_name,
                                                             CASE WHEN e.verification_status = 'pending' THEN NULL ELSE e.verified_date END as verified_date_clean,
                                                             a.appointment_number, a.appointment_date 
                                                     FROM employees e 
                                                     LEFT JOIN users u ON e.verified_by = u.id 
                                                     LEFT JOIN appointments a ON a.employee_id = e.id AND a.status = 'approved'
                                                     WHERE e.id IN ($idsStr)");
                                $metaMap = [];
                                if ($dbRes) {
                                    while ($row = $dbRes->fetch_assoc()) {
                                        $metaMap[$row['id']] = $row;
                                    }
                                }
                                foreach ($items as &$item) {
                                    $id = (int)($item['id'] ?? 0);
                                    if (isset($metaMap[$id])) {
                                        // Always override approval_status from MySQL (ES may be stale)
                                        $item['approval_status'] = $metaMap[$id]['approval_status'] ?? $item['approval_status'];
                                        $item['verification_status'] = $metaMap[$id]['approval_status'] ?? $item['verification_status'];
                                        $item['competency_name'] = $metaMap[$id]['competency_name'] ?? $item['competency_name'] ?? '';
                                        $item['sub_competency'] = $metaMap[$id]['sub_competency'] ?? $item['sub_competency'] ?? '';
                                        $item['verified_by_name'] = $metaMap[$id]['verified_by_name'];
                                        $item['verified_date'] = $metaMap[$id]['verified_date_clean'];
                                        $item['employee_status'] = $metaMap[$id]['employee_status'] ?? ($item['employee_status'] ?? 'active');
                                        $item['resign_date'] = $metaMap[$id]['resign_date'] ?? ($item['resign_date'] ?? null);
                                        $item['appointment_number'] = $metaMap[$id]['appointment_number'] ?? ($item['appointment_number'] ?? '-');
                                        $item['appointment_date'] = $metaMap[$id]['appointment_date'] ?? ($item['appointment_date'] ?? null);
                                    }
                                }
                                unset($item);

                                // Filter out stale items that no longer match the requested status
                                if (!empty($status) && ($target === 'employees' || $target === 'employee_status')) {
                                    $items = array_values(array_filter($items, function($item) use ($status) {
                                        return ($item['approval_status'] ?? '') === $status;
                                    }));
                                    $totalHits = count($items);
                                }

                                if ($target === 'employee_status') {
                                    $items = array_values(array_filter($items, function($item) use ($metaMap) {
                                        $id = (int)($item['id'] ?? 0);
                                        return isset($metaMap[$id]) && !empty($metaMap[$id]['appointment_number']);
                                    }));
                                    $totalHits = count($items);
                                }
                            }
                        } catch (\Throwable $t) {}
                    }

                    if ($target === 'appointments' && !empty($items) && class_exists('Database')) {
                        try {
                            $dbHydrate = new Database();
                            $ids = array_filter(array_map(function($i) { return (int)($i['id'] ?? 0); }, $items));
                            if (!empty($ids)) {
                                $idsStr = implode(',', $ids);
                                $dbRes = $dbHydrate->query("SELECT a.id, a.appointment_number, a.effective_date, a.expiry_date, 
                                                            e.employee_code, e.full_name as employee_name, e.position, e.department, e.contractor_company, p.position_name as competency_name 
                                                     FROM appointments a 
                                                     LEFT JOIN employees e ON a.employee_id = e.id 
                                                     LEFT JOIN positions p ON a.position_id = p.id 
                                                     WHERE a.id IN ($idsStr)");
                                $metaMap = [];
                                if ($dbRes) {
                                    while ($row = $dbRes->fetch_assoc()) {
                                        $metaMap[$row['id']] = $row;
                                    }
                                }
                                foreach ($items as &$item) {
                                    $id = (int)($item['id'] ?? 0);
                                    if (isset($metaMap[$id])) {
                                        if (empty($item['appointment_number'])) $item['appointment_number'] = $metaMap[$id]['appointment_number'] ?? '';
                                        if (empty($item['employee_code'])) $item['employee_code'] = $metaMap[$id]['employee_code'] ?? '';
                                        if (empty($item['employee_name'])) $item['employee_name'] = $metaMap[$id]['employee_name'] ?? '';
                                        if (empty($item['position'])) $item['position'] = $metaMap[$id]['position'] ?? '';
                                        if (empty($item['department'])) $item['department'] = $metaMap[$id]['department'] ?? '';
                                        if (empty($item['contractor_company'])) $item['contractor_company'] = $metaMap[$id]['contractor_company'] ?? '';
                                        if (empty($item['competency_name'])) $item['competency_name'] = $metaMap[$id]['competency_name'] ?? '';
                                        if (empty($item['effective_date'])) $item['effective_date'] = $metaMap[$id]['effective_date'] ?? null;
                                        if (empty($item['expiry_date'])) $item['expiry_date'] = $metaMap[$id]['expiry_date'] ?? null;
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

    // MySQL Fallback Data (if ES sync needed, run bin/sync manually)

    $items = [];
    $total = 0;

    if ($target === 'employee_status') {
        $where = ["e.is_active = 1", "a.status = 'approved'"];
        
        if (!empty($queryText)) {
            $safeQ = $db->escapeString($queryText);
            $where[] = "(e.employee_code LIKE '%$safeQ%' OR e.full_name LIKE '%$safeQ%' OR e.position LIKE '%$safeQ%' OR e.contractor_company LIKE '%$safeQ%' OR a.appointment_number LIKE '%$safeQ%')";
        }

        if (!$isAdmin) {
            $scopeConds = [];
            if (!empty($company)) {
                $safeCompany = $db->escapeString($company);
                $scopeConds[] = "e.contractor_company = '$safeCompany'";
                $scopeConds[] = "e.department = '$safeCompany'";
            }
            if (!empty($department)) {
                $safeDept = $db->escapeString($department);
                $scopeConds[] = "e.department = '$safeDept'";
            }
            if (!empty($scopeConds)) {
                $where[] = "(" . implode(' OR ', array_unique($scopeConds)) . ")";
            }
        } elseif (!empty($company)) {
            $safeCompany = $db->escapeString($company);
            $where[] = "e.contractor_company = '$safeCompany'";
        }

        if (!empty($employeeStatus)) {
            $safeEmpStatus = $db->escapeString($employeeStatus);
            $where[] = "e.employee_status = '$safeEmpStatus'";
        }

        if (!empty($competencyType)) {
            $safeType = $db->escapeString($competencyType);
            $where[] = "e.competency_type = '$safeType'";
        }

        $whereClause = implode(' AND ', $where);

        // Count
        $countRes = $db->query("SELECT COUNT(DISTINCT e.id) as cnt FROM employees e INNER JOIN appointments a ON a.employee_id = e.id WHERE $whereClause");
        if ($countRes) {
            $total = (int)($countRes->fetch_assoc()['cnt'] ?? 0);
        }

        $sql = "SELECT e.id, e.employee_code, e.full_name, e.position, e.department, e.contractor_company, 
                       e.competency_type, e.competency_name, e.ruang_lingkup, e.sub_competency, e.supervision_area, 
                       e.employee_status, e.resign_date, a.appointment_number, a.appointment_date,
                       e.verification_status as approval_status, e.verification_status, e.created_at 
                FROM employees e 
                INNER JOIN appointments a ON a.employee_id = e.id 
                WHERE $whereClause GROUP BY e.id ORDER BY e.full_name ASC LIMIT $from, $limit";
        $res = $db->query($sql);
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $items[] = $row;
            }
        }
    } else if ($target === 'employees') {
        $where = ["e.is_active = 1"];
        
        if (!empty($queryText)) {
            $safeQ = $db->escapeString($queryText);
            $where[] = "(e.employee_code LIKE '%$safeQ%' OR e.full_name LIKE '%$safeQ%' OR e.position LIKE '%$safeQ%' OR e.contractor_company LIKE '%$safeQ%' OR e.department LIKE '%$safeQ%')";
        }

        if (!$isAdmin) {
            $scopeConds = [];
            if (!empty($company)) {
                $safeCompany = $db->escapeString($company);
                $scopeConds[] = "e.contractor_company = '$safeCompany'";
                $scopeConds[] = "e.department = '$safeCompany'";
            }
            if (!empty($department)) {
                $safeDept = $db->escapeString($department);
                $scopeConds[] = "e.department = '$safeDept'";
                $scopeConds[] = "e.contractor_company = '$safeDept'";
            }
            if (!empty($createdByFilter)) {
                $safeUserId = intval($createdByFilter);
                $scopeConds[] = "e.created_by = $safeUserId";
            }
            if (!empty($scopeConds)) {
                $where[] = "(" . implode(' OR ', array_unique($scopeConds)) . ")";
            }
        } elseif (!empty($company)) {
            $safeCompany = $db->escapeString($company);
            $where[] = "e.contractor_company = '$safeCompany'";
        }

        if (!empty($competencyType)) {
            $safeType = $db->escapeString($competencyType);
            $where[] = "e.competency_type = '$safeType'";
        }

        if ($isAdmin && !empty($department)) {
            $safeDept = $db->escapeString($department);
            $where[] = "e.department = '$safeDept'";
        }

        if (!empty($status)) {
            $safeStatus = $db->escapeString($status);
            $where[] = "(e.verification_status = '$safeStatus')";
        }

        $whereClause = implode(' AND ', $where);

        // Count
        $countRes = $db->query("SELECT COUNT(*) as cnt FROM employees e WHERE e.deleted_at IS NULL AND $whereClause");
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

        if (!$isAdmin) {
            $scopeConds = [];
            if (!empty($company)) {
                $safeCompany = $db->escapeString($company);
                $scopeConds[] = "e.contractor_company = '$safeCompany'";
                $scopeConds[] = "e.department = '$safeCompany'";
            }
            if (!empty($department)) {
                $safeDept = $db->escapeString($department);
                $scopeConds[] = "e.department = '$safeDept'";
                $scopeConds[] = "e.contractor_company = '$safeDept'";
            }
            if (!empty($createdByFilter)) {
                $safeUserId = intval($createdByFilter);
                $scopeConds[] = "a.created_by = $safeUserId OR e.created_by = $safeUserId";
            }
            if (!empty($scopeConds)) {
                $where[] = "(" . implode(' OR ', array_unique($scopeConds)) . ")";
            }
        } elseif (!empty($company)) {
            $safeCompany = $db->escapeString($company);
            $where[] = "e.contractor_company = '$safeCompany'";
        } elseif (!empty($department)) {
            $safeDept = $db->escapeString($department);
            $where[] = "e.department = '$safeDept'";
        }

        if (!empty($competencyType)) {
            $safeType = $db->escapeString($competencyType);
            $where[] = "e.competency_type = '$safeType'";
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

        $sql = "SELECT a.id, a.appointment_number, a.employee_id, 
                       e.employee_code, e.full_name as employee_name, e.position, e.department, e.contractor_company, 
                       COALESCE(p.position_name, e.competency_type) as competency_name, e.competency_type, a.status, a.created_at 
                FROM appointments a 
                LEFT JOIN employees e ON a.employee_id = e.id 
                LEFT JOIN positions p ON a.position_id = p.id 
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
