<?php
/**
 * ReportService - Centralized Report Data Engine for STELA-2
 * Handles all 6 report types, Elasticsearch integration (Bonsai.io),
 * MySQL prepared statements fallback, and strict RBAC scoping.
 */

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/ElasticsearchService.php';

class ReportService {
    private static $instance = null;
    private $db;
    private $es;

    private function __construct() {
        $this->db = new Database();
        $this->es = ElasticsearchService::getInstance();
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Get summary counts for the 6 report types based on user role & scope
     */
    public function getSummaryCounts($role, $company = '', $department = '') {
        $role = strtolower(trim((string)$role));
        $company = trim((string)$company);
        $department = trim((string)$department);

        $whereEmp = "1=1";
        $whereAppt = "1=1";
        $paramsEmp = [];
        $typesEmp = "";
        $paramsAppt = [];
        $typesAppt = "";

        if ($role === 'user' && !empty($company)) {
            $whereEmp .= " AND e.contractor_company = ?";
            $whereAppt .= " AND e.contractor_company = ?";
            $paramsEmp[] = $company;
            $typesEmp .= "s";
            $paramsAppt[] = $company;
            $typesAppt .= "s";
        } elseif ($role === 'department' && !empty($department)) {
            $whereEmp .= " AND e.department = ?";
            $whereAppt .= " AND e.department = ?";
            $paramsEmp[] = $department;
            $typesEmp .= "s";
            $paramsAppt[] = $department;
            $typesAppt .= "s";
        }

        // 1. Accepted Requests Count
        $sql1 = "SELECT COUNT(*) as count FROM employees e WHERE {$whereEmp} AND e.verification_status = 'verified'";
        $acceptedRequests = $this->fetchCount($sql1, $paramsEmp, $typesEmp);

        // 2. Rejected Requests Count
        $sql2 = "SELECT COUNT(*) as count FROM employees e WHERE {$whereEmp} AND e.verification_status = 'rejected'";
        $rejectedRequests = $this->fetchCount($sql2, $paramsEmp, $typesEmp);

        // 3. Waiting Requests Count
        $sql3 = "SELECT COUNT(*) as count FROM employees e WHERE {$whereEmp} AND e.verification_status = 'pending'";
        $waitingRequests = $this->fetchCount($sql3, $paramsEmp, $typesEmp);

        // 4. Accepted Assign Letters Count
        $sql4 = "SELECT COUNT(*) as count FROM appointments a JOIN employees e ON a.employee_id = e.id WHERE {$whereAppt} AND a.status = 'approved'";
        $acceptedAssignLetters = $this->fetchCount($sql4, $paramsAppt, $typesAppt);

        // 5. Rejected Assign Letters Count
        $sql5 = "SELECT COUNT(*) as count FROM appointments a JOIN employees e ON a.employee_id = e.id WHERE {$whereAppt} AND a.status = 'rejected'";
        $rejectedAssignLetters = $this->fetchCount($sql5, $paramsAppt, $typesAppt);

        // 6. Expired Certificates Count
        $sql6 = "SELECT COUNT(DISTINCT ec.id) as count 
                 FROM employee_certifications ec 
                 JOIN employees e ON ec.employee_id = e.id 
                 WHERE {$whereEmp} 
                 AND (ec.expiry_date IS NOT NULL AND ec.expiry_date != '0000-00-00')
                 AND (ec.expiry_date <= CURDATE() OR DATEDIFF(ec.expiry_date, CURDATE()) <= 60)";
        $expiredCerts = $this->fetchCount($sql6, $paramsEmp, $typesEmp);

        return [
            'accepted_requests'       => $acceptedRequests,
            'rejected_requests'       => $rejectedRequests,
            'waiting_requests'        => $waitingRequests,
            'accepted_assign_letters' => $acceptedAssignLetters,
            'rejected_assign_letters' => $rejectedAssignLetters,
            'expired_certificates'    => $expiredCerts
        ];
    }

    /**
     * Get Paginated Data for specific Report Type
     */
    public function getReportData($reportType, $role, $company = '', $department = '', $search = '', $filters = [], $sortCol = 'id', $sortDir = 'desc', $page = 1, $perPage = 10) {
        $page = max(1, (int)$page);
        $perPage = max(1, min(500, (int)$perPage));
        $offset = ($page - 1) * $perPage;
        $search = trim((string)$search);

        // Check if Elasticsearch search should be used
        if ($search !== '' && $this->es->isAvailable()) {
            $esResult = $this->searchWithElasticsearch($reportType, $role, $company, $department, $search, $filters, $offset, $perPage);
            if ($esResult !== false) {
                return $esResult;
            }
        }

        // Fallback to MySQL Prepared Statements
        return $this->getReportDataFromMySQL($reportType, $role, $company, $department, $search, $filters, $sortCol, $sortDir, $page, $perPage);
    }

    /**
     * Fetch Report Data from MySQL with Prepared Statements
     */
    private function getReportDataFromMySQL($reportType, $role, $company, $department, $search, $filters, $sortCol, $sortDir, $page, $perPage) {
        $role = strtolower(trim((string)$role));
        $company = trim((string)$company);
        $department = trim((string)$department);
        $offset = ($page - 1) * $perPage;

        $params = [];
        $types = "";
        $where = ["1=1"];

        // RBAC Scoping
        if ($role === 'user' && !empty($company)) {
            $where[] = "e.contractor_company = ?";
            $params[] = $company;
            $types .= "s";
        } elseif ($role === 'department' && !empty($department)) {
            $where[] = "e.department = ?";
            $params[] = $department;
            $types .= "s";
        }

        // Company filter dropdown (Admin mode)
        if (!empty($filters['company'])) {
            $where[] = "e.contractor_company = ?";
            $params[] = $filters['company'];
            $types .= "s";
        }

        // Department filter dropdown
        if (!empty($filters['department'])) {
            $where[] = "e.department = ?";
            $params[] = $filters['department'];
            $types .= "s";
        }

        // Scope filter dropdown (PT MSM / PT TTN)
        if (!empty($filters['scope'])) {
            $where[] = "(e.ruang_lingkup LIKE ? OR e.ruang_lingkup = ?)";
            $params[] = '%' . $filters['scope'] . '%';
            $params[] = $filters['scope'];
            $types .= "ss";
        }

        // Supervision area filter
        if (!empty($filters['supervision_area'])) {
            $where[] = "e.supervision_area = ?";
            $params[] = $filters['supervision_area'];
            $types .= "s";
        }

        // Build specific query based on report type
        switch ($reportType) {
            case 'accepted_requests':
                $where[] = "e.verification_status = 'verified'";
                if (!empty($search)) {
                    $where[] = "(e.full_name LIKE ? OR e.employee_code LIKE ? OR e.contractor_company LIKE ? OR e.department LIKE ?)";
                    $sTerm = '%' . $search . '%';
                    $params = array_merge($params, [$sTerm, $sTerm, $sTerm, $sTerm]);
                    $types .= "ssss";
                }
                $selectSql = "SELECT e.id, e.full_name, e.employee_code, e.contractor_company, e.department, e.position, 
                                     e.ruang_lingkup, e.supervision_area, e.request_date, e.verification_status, e.updated_at as verification_date,
                                     u.full_name as verified_by_name, e.rejection_notes,
                                     (SELECT p.position_type FROM appointments a JOIN positions p ON a.position_id = p.id WHERE a.employee_id = e.id ORDER BY a.id DESC LIMIT 1) as competency_type,
                                     (SELECT p.position_name FROM appointments a JOIN positions p ON a.position_id = p.id WHERE a.employee_id = e.id ORDER BY a.id DESC LIMIT 1) as competency_name
                              FROM employees e
                              LEFT JOIN users u ON e.verified_by = u.id";
                $countSql = "SELECT COUNT(*) as total FROM employees e LEFT JOIN users u ON e.verified_by = u.id";
                $allowedSorts = ['full_name' => 'e.full_name', 'employee_code' => 'e.employee_code', 'company' => 'e.contractor_company', 'date' => 'e.request_date', 'verification_date' => 'e.updated_at'];
                break;

            case 'rejected_requests':
                $where[] = "e.verification_status = 'rejected'";
                if (!empty($search)) {
                    $where[] = "(e.full_name LIKE ? OR e.employee_code LIKE ? OR e.contractor_company LIKE ? OR e.rejection_notes LIKE ?)";
                    $sTerm = '%' . $search . '%';
                    $params = array_merge($params, [$sTerm, $sTerm, $sTerm, $sTerm]);
                    $types .= "ssss";
                }
                $selectSql = "SELECT e.id, e.full_name, e.employee_code, e.contractor_company, e.department, e.position, 
                                     e.request_date, e.verification_status, e.updated_at as rejection_date,
                                     u.full_name as rejected_by_name, e.rejection_notes,
                                     (SELECT p.position_type FROM appointments a JOIN positions p ON a.position_id = p.id WHERE a.employee_id = e.id ORDER BY a.id DESC LIMIT 1) as competency_type
                              FROM employees e
                              LEFT JOIN users u ON e.verified_by = u.id";
                $countSql = "SELECT COUNT(*) as total FROM employees e LEFT JOIN users u ON e.verified_by = u.id";
                $allowedSorts = ['full_name' => 'e.full_name', 'employee_code' => 'e.employee_code', 'company' => 'e.contractor_company', 'date' => 'e.request_date', 'rejection_date' => 'e.updated_at'];
                break;

            case 'waiting_requests':
                $where[] = "e.verification_status = 'pending'";
                if (!empty($search)) {
                    $where[] = "(e.full_name LIKE ? OR e.employee_code LIKE ? OR e.contractor_company LIKE ? OR e.department LIKE ?)";
                    $sTerm = '%' . $search . '%';
                    $params = array_merge($params, [$sTerm, $sTerm, $sTerm, $sTerm]);
                    $types .= "ssss";
                }
                $selectSql = "SELECT e.id, e.full_name, e.employee_code, e.contractor_company, e.department, e.position, 
                                     e.request_date, e.verification_status, e.created_at,
                                     DATEDIFF(NOW(), e.created_at) as waiting_days,
                                     (SELECT p.position_type FROM appointments a JOIN positions p ON a.position_id = p.id WHERE a.employee_id = e.id ORDER BY a.id DESC LIMIT 1) as competency_type
                              FROM employees e";
                $countSql = "SELECT COUNT(*) as total FROM employees e";
                $allowedSorts = ['full_name' => 'e.full_name', 'employee_code' => 'e.employee_code', 'company' => 'e.contractor_company', 'date' => 'e.created_at'];
                break;

            case 'accepted_assign_letters':
                $where[] = "a.status = 'approved'";
                if (!empty($search)) {
                    $where[] = "(a.appointment_number LIKE ? OR e.full_name LIKE ? OR e.employee_code LIKE ? OR e.contractor_company LIKE ? OR p.position_name LIKE ?)";
                    $sTerm = '%' . $search . '%';
                    $params = array_merge($params, [$sTerm, $sTerm, $sTerm, $sTerm, $sTerm]);
                    $types .= "sssss";
                }
                $selectSql = "SELECT a.id, a.appointment_number, a.status, a.effective_date, a.approved_date, a.created_at as request_date,
                                     e.full_name as employee_name, e.employee_code, e.contractor_company, e.department, e.ruang_lingkup, e.supervision_area,
                                     p.position_name, p.position_type,
                                     au.full_name as approved_by_name,
                                     ktt1.full_name as ktt1_name, a.ktt1_approved_date,
                                     ktt2.full_name as ktt2_name, a.ktt2_approved_date
                              FROM appointments a
                              JOIN employees e ON a.employee_id = e.id
                              JOIN positions p ON a.position_id = p.id
                              LEFT JOIN users au ON a.approved_by = au.id
                              LEFT JOIN users ktt1 ON a.ktt1_approved_by = ktt1.id
                              LEFT JOIN users ktt2 ON a.ktt2_approved_by = ktt2.id";
                $countSql = "SELECT COUNT(*) as total FROM appointments a JOIN employees e ON a.employee_id = e.id JOIN positions p ON a.position_id = p.id";
                $allowedSorts = ['appointment_number' => 'a.appointment_number', 'employee_name' => 'e.full_name', 'company' => 'e.contractor_company', 'approved_date' => 'a.approved_date', 'effective_date' => 'a.effective_date'];
                break;

            case 'rejected_assign_letters':
                $where[] = "a.status = 'rejected'";
                if (!empty($search)) {
                    $where[] = "(a.appointment_number LIKE ? OR e.full_name LIKE ? OR e.employee_code LIKE ? OR e.contractor_company LIKE ? OR p.position_name LIKE ?)";
                    $sTerm = '%' . $search . '%';
                    $params = array_merge($params, [$sTerm, $sTerm, $sTerm, $sTerm, $sTerm]);
                    $types .= "sssss";
                }
                $selectSql = "SELECT a.id, a.appointment_number, a.status, a.approved_date as rejection_date, a.created_at as request_date,
                                     e.full_name as employee_name, e.employee_code, e.contractor_company, e.department, e.ruang_lingkup, e.supervision_area,
                                     p.position_name, p.position_type,
                                     au.full_name as rejected_by_name,
                                     (SELECT GROUP_CONCAT(CONCAT(ktt_u.full_name, ' (', ka.action, '): ', ka.approval_notes) SEPARATOR ' | ')
                                      FROM ktt_approvals ka LEFT JOIN users ktt_u ON ka.ktt_user_id = ktt_u.id WHERE ka.appointment_id = a.id) as ktt_notes
                              FROM appointments a
                              JOIN employees e ON a.employee_id = e.id
                              JOIN positions p ON a.position_id = p.id
                              LEFT JOIN users au ON a.approved_by = au.id";
                $countSql = "SELECT COUNT(*) as total FROM appointments a JOIN employees e ON a.employee_id = e.id JOIN positions p ON a.position_id = p.id";
                $allowedSorts = ['appointment_number' => 'a.appointment_number', 'employee_name' => 'e.full_name', 'company' => 'e.contractor_company', 'rejection_date' => 'a.approved_date'];
                break;

            case 'expired_certificates':
                $where[] = "(ec.expiry_date IS NOT NULL AND ec.expiry_date != '0000-00-00')";
                $where[] = "(ec.expiry_date <= CURDATE() OR DATEDIFF(ec.expiry_date, CURDATE()) <= 60)";
                if (!empty($search)) {
                    $where[] = "(e.full_name LIKE ? OR e.employee_code LIKE ? OR ec.cert_name LIKE ? OR ec.cert_number LIKE ? OR ec.cert_issuer LIKE ?)";
                    $sTerm = '%' . $search . '%';
                    $params = array_merge($params, [$sTerm, $sTerm, $sTerm, $sTerm, $sTerm]);
                    $types .= "sssss";
                }
                $selectSql = "SELECT ec.id, ec.cert_name, ec.cert_number, ec.cert_issuer, ec.issue_date, ec.expiry_date,
                                     DATEDIFF(ec.expiry_date, CURDATE()) as days_left,
                                     e.full_name as employee_name, e.employee_code, e.contractor_company, e.department, e.position,
                                     (SELECT p.position_type FROM appointments a JOIN positions p ON a.position_id = p.id WHERE a.employee_id = e.id ORDER BY a.id DESC LIMIT 1) as competency_type,
                                     (SELECT p.position_name FROM appointments a JOIN positions p ON a.position_id = p.id WHERE a.employee_id = e.id ORDER BY a.id DESC LIMIT 1) as competency_name
                              FROM employee_certifications ec
                              JOIN employees e ON ec.employee_id = e.id";
                $countSql = "SELECT COUNT(*) as total FROM employee_certifications ec JOIN employees e ON ec.employee_id = e.id";
                $allowedSorts = ['employee_name' => 'e.full_name', 'cert_name' => 'ec.cert_name', 'expiry_date' => 'ec.expiry_date', 'days_left' => 'days_left'];
                break;

            default:
                return ['total' => 0, 'items' => [], 'source' => 'mysql', 'page' => 1, 'total_pages' => 0];
        }

        $whereClause = implode(" AND ", $where);

        // Count Query
        $fullCountSql = "{$countSql} WHERE {$whereClause}";
        $total = $this->fetchCount($fullCountSql, $params, $types);

        // Order By & Limit
        $sortOrder = strtolower($sortDir) === 'asc' ? 'ASC' : 'DESC';
        $orderByCol = $allowedSorts[$sortCol] ?? reset($allowedSorts);
        $fullSelectSql = "{$selectSql} WHERE {$whereClause} ORDER BY {$orderByCol} {$sortOrder} LIMIT {$perPage} OFFSET {$offset}";

        $items = $this->fetchAll($fullSelectSql, $params, $types);

        return [
            'total'       => (int)$total,
            'items'       => $items,
            'source'      => 'mysql',
            'page'        => $page,
            'per_page'    => $perPage,
            'total_pages' => (int)ceil($total / $perPage)
        ];
    }

    /**
     * Query Elasticsearch for Report Search
     */
    private function searchWithElasticsearch($reportType, $role, $company, $department, $search, $filters, $offset, $perPage) {
        $esFilters = [];

        // Apply RBAC filters to ES
        if (strtolower($role) === 'user' && !empty($company)) {
            $esFilters['contractor_company'] = $company;
        } elseif (strtolower($role) === 'department' && !empty($department)) {
            $esFilters['department'] = $department;
        }

        if (!empty($filters['company'])) {
            $esFilters['contractor_company'] = $filters['company'];
        }

        if (in_array($reportType, ['accepted_requests', 'rejected_requests', 'waiting_requests', 'expired_certificates'])) {
            $res = $this->es->searchEmployees($search, $esFilters, $offset, $perPage);
            if ($res && isset($res['items'])) {
                // Post-filter matching reportType logic
                $filteredItems = array_values(array_filter($res['items'], function($item) use ($reportType) {
                    if ($reportType === 'accepted_requests') return ($item['approval_status'] ?? '') === 'verified';
                    if ($reportType === 'rejected_requests') return ($item['approval_status'] ?? '') === 'rejected';
                    if ($reportType === 'waiting_requests') return ($item['approval_status'] ?? '') === 'pending';
                    if ($reportType === 'expired_certificates') return true;
                    return true;
                }));

                return [
                    'total'       => (int)count($filteredItems),
                    'items'       => $filteredItems,
                    'source'      => 'elasticsearch',
                    'page'        => (int)ceil(($offset + 1) / $perPage),
                    'per_page'    => $perPage,
                    'total_pages' => (int)ceil(count($filteredItems) / $perPage)
                ];
            }
        } elseif (in_array($reportType, ['accepted_assign_letters', 'rejected_assign_letters'])) {
            $statusTarget = $reportType === 'accepted_assign_letters' ? 'approved' : 'rejected';
            $esFilters['status'] = $statusTarget;
            $res = $this->es->searchAppointments($search, $esFilters, $offset, $perPage);
            if ($res && isset($res['items'])) {
                return [
                    'total'       => (int)($res['total'] ?? 0),
                    'items'       => $res['items'],
                    'source'      => 'elasticsearch',
                    'page'        => (int)ceil(($offset + 1) / $perPage),
                    'per_page'    => $perPage,
                    'total_pages' => (int)ceil(($res['total'] ?? 0) / $perPage)
                ];
            }
        }

        return false;
    }

    /**
     * Helper to fetch single count
     */
    private function fetchCount($sql, $params = [], $types = "") {
        $stmt = $this->db->prepare($sql);
        if (!$stmt) return 0;
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res && $row = $res->fetch_assoc()) {
            return (int)($row['count'] ?? $row['total'] ?? 0);
        }
        return 0;
    }

    /**
     * Helper to fetch all rows array
     */
    private function fetchAll($sql, $params = [], $types = "") {
        $stmt = $this->db->prepare($sql);
        if (!$stmt) return [];
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $res = $stmt->get_result();
        $rows = [];
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $rows[] = $row;
            }
        }
        return $rows;
    }
}
