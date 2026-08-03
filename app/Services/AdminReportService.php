<?php
/**
 * AdminReportService - Centralized Admin Report Data Engine for STELA-2
 * Handles all 6 Admin report types across ALL companies and departments.
 * Integrates Bonsai.io (ElasticsearchService) with fallback to MySQL Prepared Statements.
 */

if (!class_exists('Database')) {
    require_once dirname(__DIR__) . '/Models/Database.php';
}
if (!class_exists('ElasticsearchService')) {
    require_once __DIR__ . '/ElasticsearchService.php';
}

class AdminReportService {
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
     * Get summary counts for all 6 report types (No company/department restriction for Admin)
     */
    public function getSummaryCounts() {
        // 1. Accepted Requests Count (Admin verified)
        $sql1 = "SELECT COUNT(*) as count FROM employees WHERE verification_status = 'verified'";
        $acceptedRequests = $this->fetchCount($sql1);

        // 2. Rejected Requests Count (Admin rejected)
        $sql2 = "SELECT COUNT(*) as count FROM employees WHERE verification_status = 'rejected'";
        $rejectedRequests = $this->fetchCount($sql2);

        // 3. Waiting Requests Count (Pending Admin verification)
        $sql3 = "SELECT COUNT(*) as count FROM employees WHERE verification_status = 'pending'";
        $waitingRequests = $this->fetchCount($sql3);

        // 4. Accepted Assign Letters Count (Approved KTT)
        $sql4 = "SELECT COUNT(*) as count FROM appointments WHERE status = 'approved'";
        $acceptedAssignLetters = $this->fetchCount($sql4);

        // 5. Rejected Assign Letters Count (Rejected KTT)
        $sql5 = "SELECT COUNT(*) as count FROM appointments WHERE status = 'rejected'";
        $rejectedAssignLetters = $this->fetchCount($sql5);

        // 6. Expired Certificates Count
        $sql6 = "SELECT COUNT(DISTINCT id) as count 
                 FROM employee_certifications 
                 WHERE (expiry_date IS NOT NULL AND expiry_date != '0000-00-00')
                 AND (expiry_date <= CURDATE() OR DATEDIFF(expiry_date, CURDATE()) <= 60)";
        $expiredCerts = $this->fetchCount($sql6);

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
     * Fetch Report Data for Admin with Elasticsearch search or MySQL fallback
     */
    public function getReportData($reportType, $search = '', $filters = [], $sortCol = 'id', $sortDir = 'desc', $page = 1, $perPage = 10) {
        $page = max(1, (int)$page);
        $perPage = max(1, min(500, (int)$perPage));
        $offset = ($page - 1) * $perPage;
        $search = trim((string)$search);

        // Try Bonsai.io (Elasticsearch) search if keyword provided
        if ($search !== '' && $this->es->isAvailable()) {
            $esResult = $this->searchWithElasticsearch($reportType, $search, $filters, $offset, $perPage);
            if ($esResult !== false) {
                return $esResult;
            }
        }

        // Fallback to MySQL Prepared Statements
        return $this->getReportDataFromMySQL($reportType, $search, $filters, $sortCol, $sortDir, $page, $perPage);
    }

    /**
     * Fetch Data from MySQL with Prepared Statements
     */
    private function getReportDataFromMySQL($reportType, $search, $filters, $sortCol, $sortDir, $page, $perPage) {
        $offset = ($page - 1) * $perPage;
        $params = [];
        $types = "";
        $where = ["1=1"];

        // Filters
        if (!empty($filters['company'])) {
            $where[] = "e.contractor_company = ?";
            $params[] = $filters['company'];
            $types .= "s";
        }

        if (!empty($filters['department'])) {
            $where[] = "e.department = ?";
            $params[] = $filters['department'];
            $types .= "s";
        }

        if (!empty($filters['scope'])) {
            $where[] = "(e.ruang_lingkup LIKE ? OR e.ruang_lingkup = ?)";
            $params[] = '%' . $filters['scope'] . '%';
            $params[] = $filters['scope'];
            $types .= "ss";
        }

        switch ($reportType) {
            case 'accepted_requests':
                $where[] = "e.verification_status = 'verified'";
                if (!empty($search)) {
                    $where[] = "(e.full_name LIKE ? OR e.employee_code LIKE ? OR e.contractor_company LIKE ? OR e.department LIKE ? OR e.position LIKE ?)";
                    $sTerm = '%' . $search . '%';
                    $params = array_merge($params, [$sTerm, $sTerm, $sTerm, $sTerm, $sTerm]);
                    $types .= "sssss";
                }
                $selectSql = "SELECT e.id, e.full_name, e.employee_code, e.contractor_company, e.department, e.position, 
                                     e.request_date, e.verification_status, e.updated_at as verification_date,
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
                    $where[] = "(e.full_name LIKE ? OR e.employee_code LIKE ? OR e.contractor_company LIKE ? OR e.department LIKE ? OR e.rejection_notes LIKE ?)";
                    $sTerm = '%' . $search . '%';
                    $params = array_merge($params, [$sTerm, $sTerm, $sTerm, $sTerm, $sTerm]);
                    $types .= "sssss";
                }
                $selectSql = "SELECT e.id, e.full_name, e.employee_code, e.contractor_company, e.department, e.position, 
                                     e.request_date, e.verification_status, e.updated_at as rejection_date,
                                     u.full_name as rejected_by_name, e.rejection_notes,
                                     (SELECT p.position_type FROM appointments a JOIN positions p ON a.position_id = p.id WHERE a.employee_id = e.id ORDER BY a.id DESC LIMIT 1) as competency_type,
                                     (SELECT p.position_name FROM appointments a JOIN positions p ON a.position_id = p.id WHERE a.employee_id = e.id ORDER BY a.id DESC LIMIT 1) as competency_name
                              FROM employees e
                              LEFT JOIN users u ON e.verified_by = u.id";
                $countSql = "SELECT COUNT(*) as total FROM employees e LEFT JOIN users u ON e.verified_by = u.id";
                $allowedSorts = ['full_name' => 'e.full_name', 'employee_code' => 'e.employee_code', 'company' => 'e.contractor_company', 'date' => 'e.request_date', 'rejection_date' => 'e.updated_at'];
                break;

            case 'waiting_requests':
                $where[] = "e.verification_status = 'pending'";
                if (!empty($search)) {
                    $where[] = "(e.full_name LIKE ? OR e.employee_code LIKE ? OR e.contractor_company LIKE ? OR e.department LIKE ? OR e.position LIKE ?)";
                    $sTerm = '%' . $search . '%';
                    $params = array_merge($params, [$sTerm, $sTerm, $sTerm, $sTerm, $sTerm]);
                    $types .= "sssss";
                }
                $selectSql = "SELECT e.id, e.full_name, e.employee_code, e.contractor_company, e.department, e.position, 
                                     e.request_date, e.verification_status, e.created_at,
                                     (SELECT p.position_type FROM appointments a JOIN positions p ON a.position_id = p.id WHERE a.employee_id = e.id ORDER BY a.id DESC LIMIT 1) as competency_type,
                                     (SELECT p.position_name FROM appointments a JOIN positions p ON a.position_id = p.id WHERE a.employee_id = e.id ORDER BY a.id DESC LIMIT 1) as competency_name
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
                                     e.full_name as employee_name, e.employee_code, e.contractor_company, e.department, e.ruang_lingkup,
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
                                     e.full_name as employee_name, e.employee_code, e.contractor_company, e.department, e.ruang_lingkup,
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
        $total = $this->fetchCount("{$countSql} WHERE {$whereClause}", $params, $types);

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
     * Bonsai.io Elasticsearch Search Integration
     */
    private function searchWithElasticsearch($reportType, $search, $filters, $offset, $perPage) {
        $esFilters = [];
        if (!empty($filters['company'])) {
            $esFilters['contractor_company'] = $filters['company'];
        }
        if (!empty($filters['department'])) {
            $esFilters['department'] = $filters['department'];
        }

        if (in_array($reportType, ['accepted_requests', 'rejected_requests', 'waiting_requests', 'expired_certificates'])) {
            $res = $this->es->searchEmployees($search, $esFilters, $offset, $perPage);
            if ($res && isset($res['items'])) {
                $filteredItems = array_values(array_filter($res['items'], function($item) use ($reportType) {
                    if ($reportType === 'accepted_requests') return ($item['approval_status'] ?? '') === 'verified';
                    if ($reportType === 'rejected_requests') return ($item['approval_status'] ?? '') === 'rejected';
                    if ($reportType === 'waiting_requests') return ($item['approval_status'] ?? '') === 'pending';
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
            $esFilters['status'] = $reportType === 'accepted_assign_letters' ? 'approved' : 'rejected';
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
