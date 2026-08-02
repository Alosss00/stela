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

    if (session_status() === PHP_SESSION_NONE) {
        session_start();
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

    // Role-based Search Scope Enforcement:
    // Non-admin roles (User, Dept, etc.) can ONLY search data input by the current user.
    // Admin roles (admin, superadmin) can search across ALL data.
    $userRole = $_SESSION['role'] ?? '';
    $userCompany = $_SESSION['company_name'] ?? '';
    $currentUserId = (int)($_SESSION['user_id'] ?? 0);
    $isAdmin = ($userRole === 'admin' || $userRole === 'superadmin');

    $createdByFilter = null;
    if (!$isAdmin && $currentUserId > 0) {
        $createdByFilter = $currentUserId;
        if (!empty($userCompany)) {
            $company = $userCompany;
        }
    } elseif (isset($_GET['created_by']) && is_numeric($_GET['created_by'])) {
        $createdByFilter = (int)$_GET['created_by'];
    }

    // Initialize Database instance
    $db = class_exists('Database') ? new Database() : null;

    // Auto-sync MySQL employee records for this company to Elasticsearch to guarantee zero missing data
    if ($db && class_exists('ElasticsearchService')) {
        try {
            $esSync = ElasticsearchService::getInstance();
            if ($esSync && $esSync->isAvailable()) {
                if ($target === 'employees') {
                    $syncWhere = ["e.is_active = 1"];
                    if (!empty($createdByFilter)) {
                        if (!empty($company)) {
                            $syncWhere[] = "(e.created_by = " . intval($createdByFilter) . " OR (e.created_by IS NULL AND e.contractor_company = '" . $db->escapeString($company) . "'))";
                        } else {
                            $syncWhere[] = "e.created_by = " . intval($createdByFilter);
                        }
                    } elseif (!empty($company)) {
                        $syncWhere[] = "e.contractor_company = '" . $db->escapeString($company) . "'";
                    }
                    $syncSql = "SELECT e.*, u.full_name as verified_by_name 
                                FROM employees e 
                                LEFT JOIN users u ON e.verified_by = u.id 
                                WHERE " . implode(' AND ', $syncWhere) . " 
                                ORDER BY e.id DESC LIMIT 50";
                    $syncRes = $db->query($syncSql);
                    if ($syncRes && $syncRes->num_rows > 0) {
                        while ($empRow = $syncRes->fetch_assoc()) {
                            $esSync->indexEmployee([
                                'id' => (int)$empRow['id'],
                                'employee_code' => $empRow['employee_code'] ?? '',
                                'full_name' => $empRow['full_name'] ?? '',
                                'position' => $empRow['position'] ?? '',
                                'department' => $empRow['department'] ?? '',
                                'contractor_company' => $empRow['contractor_company'] ?? '',
                                'competency_type' => $empRow['competency_type'] ?? '',
                                'competency_name' => $empRow['competency_name'] ?? '',
                                'verified_by_name' => $empRow['verified_by_name'] ?? '',
                                'ruang_lingkup' => $empRow['ruang_lingkup'] ?? '',
                                'sub_competency' => $empRow['sub_competency'] ?? '',
                                'supervision_area' => $empRow['supervision_area'] ?? '',
                                'approval_status' => $empRow['verification_status'] ?? ($empRow['status'] ?? 'pending'),
                                'is_active' => isset($empRow['is_active']) ? (int)$empRow['is_active'] : 1,
                                'created_by' => isset($empRow['created_by']) ? (int)$empRow['created_by'] : null,
                                'created_at' => $empRow['created_at'] ?? date('Y-m-d H:i:s')
                            ]);
                        }
                    }
                } else {
                    $syncWhere = ["1=1"];
                    if (!empty($createdByFilter)) {
                        if (!empty($company)) {
                            $syncWhere[] = "(a.created_by = " . intval($createdByFilter) . " OR e.created_by = " . intval($createdByFilter) . " OR (a.created_by IS NULL AND e.contractor_company = '" . $db->escapeString($company) . "'))";
                        } elseif (!empty($department)) {
                            $syncWhere[] = "(a.created_by = " . intval($createdByFilter) . " OR e.created_by = " . intval($createdByFilter) . " OR (a.created_by IS NULL AND e.department = '" . $db->escapeString($department) . "'))";
                        } else {
                            $syncWhere[] = "(a.created_by = " . intval($createdByFilter) . " OR e.created_by = " . intval($createdByFilter) . ")";
                        }
                    } elseif (!empty($company)) {
                        $syncWhere[] = "e.contractor_company = '" . $db->escapeString($company) . "'";
                    } elseif (!empty($department)) {
                        $syncWhere[] = "e.department = '" . $db->escapeString($department) . "'";
                    }

                    $syncSql = "SELECT a.*, e.employee_code, e.full_name as employee_name, e.position, e.department, e.contractor_company, p.position_name as competency_name 
                                FROM appointments a 
                                LEFT JOIN employees e ON a.employee_id = e.id 
                                LEFT JOIN positions p ON a.position_id = p.id 
                                WHERE " . implode(' AND ', $syncWhere) . " 
                                ORDER BY a.id DESC LIMIT 50";
                    $syncRes = $db->query($syncSql);
                    if ($syncRes && $syncRes->num_rows > 0) {
                        while ($apptRow = $syncRes->fetch_assoc()) {
                            $esSync->indexAppointment($apptRow);
                        }
                    }
                }
            }
        } catch (\Throwable $t) {
            // Ignore auto-sync error
        }
    }

    // 1. Try Elasticsearch Search first if class exists
    if (class_exists('ElasticsearchService')) {
        $es = ElasticsearchService::getInstance();
        if ($es && method_exists($es, 'isAvailable') && $es->isAvailable()) {
            $filters = [];
            if (!empty($company)) $filters['contractor_company'] = $company;
            if (!empty($competencyType)) $filters['competency_type'] = $competencyType;
            if (!empty($department)) $filters['department'] = $department;
            if (!empty($createdByFilter)) $filters['created_by'] = $createdByFilter;
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
                
                // If ES has hits, return ES result. If 0 hits, fall through to MySQL fallback search
                if ($totalHits > 0) {
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

        if (!empty($createdByFilter)) {
            $safeUserId = intval($createdByFilter);
            if (!empty($company)) {
                $safeCompany = $db->escapeString($company);
                $where[] = "(e.created_by = $safeUserId OR (e.created_by IS NULL AND e.contractor_company = '$safeCompany'))";
            } else {
                $where[] = "e.created_by = $safeUserId";
            }
        } elseif (!empty($company)) {
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

        if (!$isAdmin && !empty($createdByFilter)) {
            $safeUserId = intval($createdByFilter);
            if (!empty($company)) {
                $safeCompany = $db->escapeString($company);
                $where[] = "(a.created_by = $safeUserId OR e.created_by = $safeUserId OR (a.created_by IS NULL AND e.contractor_company = '$safeCompany'))";
            } elseif (!empty($department)) {
                $safeDept = $db->escapeString($department);
                $where[] = "(a.created_by = $safeUserId OR e.created_by = $safeUserId OR (a.created_by IS NULL AND e.department = '$safeDept'))";
            } else {
                $where[] = "(a.created_by = $safeUserId OR e.created_by = $safeUserId)";
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
