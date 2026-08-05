<?php
// app/Helpers/ReportsHelper.php

class ReportsHelper {
    private $db;
    private $es;

    public function __construct($db) {
        $this->db = $db;
        
        if (file_exists(dirname(__DIR__) . '/Services/ElasticsearchService.php')) {
            require_once dirname(__DIR__) . '/Services/ElasticsearchService.php';
            if (class_exists('ElasticsearchService')) {
                $this->es = new ElasticsearchService();
            }
        }
    }

    /**
     * Helper logic to build basic filters (Company, Department, Position, Competency)
     */
    private function buildCommonFilterSql($filters, $prefix = 'e') {
        $sql = "";
        if (!empty($filters['company'])) {
            $sql .= " AND $prefix.contractor_company = '" . $this->db->escape($filters['company']) . "'";
        }
        if (!empty($filters['department'])) {
            $sql .= " AND $prefix.department = '" . $this->db->escape($filters['department']) . "'";
        }
        if (!empty($filters['position'])) {
            $sql .= " AND $prefix.position = '" . $this->db->escape($filters['position']) . "'";
        }
        if (!empty($filters['competency'])) {
            $sql .= " AND $prefix.competency_type = '" . $this->db->escape($filters['competency']) . "'";
        }
        return $sql;
    }

    private function buildCommonFilterParams($filters, $prefix = 'e') {
        $sql = "";
        $params = [];
        $types = "";
        
        if (!empty($filters['company'])) {
            $sql .= " AND $prefix.contractor_company = ?";
            $params[] = $filters['company'];
            $types .= "s";
        }
        if (!empty($filters['department'])) {
            $sql .= " AND $prefix.department = ?";
            $params[] = $filters['department'];
            $types .= "s";
        }
        if (!empty($filters['position'])) {
            $sql .= " AND $prefix.position = ?";
            $params[] = $filters['position'];
            $types .= "s";
        }
        if (!empty($filters['competency'])) {
            $sql .= " AND $prefix.competency_type = ?";
            $params[] = $filters['competency'];
            $types .= "s";
        }
        
        // Date Range logic if provided
        if (!empty($filters['date_start']) && !empty($filters['date_end'])) {
            $dateField = $filters['date_field'] ?? "$prefix.created_at";
            $sql .= " AND DATE($dateField) BETWEEN ? AND ?";
            $params[] = $filters['date_start'];
            $params[] = $filters['date_end'];
            $types .= "ss";
        }

        return [$sql, $params, $types];
    }

    /**
     * Get Stats for Dashboard Cards
     */
    public function getDashboardStats() {
        $stats = [
            'accepted_requests' => 0,
            'rejected_requests' => 0,
            'waiting_requests' => 0,
            'approved_assign' => 0,
            'rejected_assign' => 0,
            'certificate_expired' => 0,
            'trend_months' => [],
            'trend_requests' => [],
            'trend_appointments' => []
        ];

        // Requests Stats
        $reqStats = $this->db->query("
            SELECT 
                SUM(CASE WHEN verification_status = 'verified' THEN 1 ELSE 0 END) as acc,
                SUM(CASE WHEN verification_status = 'rejected' THEN 1 ELSE 0 END) as rej,
                SUM(CASE WHEN verification_status = 'pending' THEN 1 ELSE 0 END) as wait
            FROM employees
        ")->fetch_assoc();
        
        if ($reqStats) {
            $stats['accepted_requests'] = (int)$reqStats['acc'];
            $stats['rejected_requests'] = (int)$reqStats['rej'];
            $stats['waiting_requests'] = (int)$reqStats['wait'];
        }

        // Appointment Stats
        $appStats = $this->db->query("
            SELECT 
                SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as app,
                SUM(CASE WHEN status IN ('rejected', 'rejected_by_ktt') THEN 1 ELSE 0 END) as rej
            FROM appointments
        ")->fetch_assoc();
        
        if ($appStats) {
            $stats['approved_assign'] = (int)$appStats['app'];
            $stats['rejected_assign'] = (int)$appStats['rej'];
        }
        
        // Expired Certs (Approximation: expired today or earlier)
        $certStats = $this->db->query("
            SELECT COUNT(*) as count 
            FROM employee_certifications 
            WHERE expiry_date IS NOT NULL AND expiry_date != '0000-00-00' AND expiry_date <= DATE_ADD(CURDATE(), INTERVAL 60 DAY)
        ")->fetch_assoc();
        $stats['certificate_expired'] = (int)($certStats['count'] ?? 0);
        
        // Trends for Chart (Last 6 Months)
        $monthsSql = "
            SELECT 
                DATE_FORMAT(created_at, '%Y-%m') as m, 
                COUNT(*) as c 
            FROM employees 
            WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH) 
            GROUP BY m ORDER BY m ASC
        ";
        $trendEmp = $this->db->query($monthsSql)->fetch_all(MYSQLI_ASSOC);
        
        $monthsSql2 = "
            SELECT 
                DATE_FORMAT(created_at, '%Y-%m') as m, 
                COUNT(*) as c 
            FROM appointments 
            WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH) 
            GROUP BY m ORDER BY m ASC
        ";
        $trendApp = $this->db->query($monthsSql2)->fetch_all(MYSQLI_ASSOC);
        
        // Map to uniform structure
        $trendMap = [];
        for ($i = 5; $i >= 0; $i--) {
            $ym = date('Y-m', strtotime("-$i months"));
            $trendMap[$ym] = ['emp' => 0, 'app' => 0];
        }
        
        foreach ($trendEmp as $t) { if (isset($trendMap[$t['m']])) $trendMap[$t['m']]['emp'] = (int)$t['c']; }
        foreach ($trendApp as $t) { if (isset($trendMap[$t['m']])) $trendMap[$t['m']]['app'] = (int)$t['c']; }
        
        foreach ($trendMap as $ym => $counts) {
            $stats['trend_months'][] = date('M Y', strtotime($ym . '-01'));
            $stats['trend_requests'][] = $counts['emp'];
            $stats['trend_appointments'][] = $counts['app'];
        }

        return $stats;
    }

    /**
     * Get Employee Requests based on verification status
     */
    private function getRequestsBase($status, $page, $limit, $search, $filters, $sort, $order, $export = false) {
        $offset = ($page - 1) * $limit;
        
        $esIdFilter = "";
        if (!empty($search) && $this->es) {
            $esResult = $this->es->searchEmployees($search, 1000, 0); 
            if ($esResult && $esResult['success']) {
                $ids = array_column($esResult['data'], 'id');
                if (empty($ids)) {
                    return ['data' => [], 'total' => 0, 'pages' => 0, 'current_page' => $page];
                }
                $idList = implode(',', array_map('intval', $ids));
                $esIdFilter = " AND e.id IN ($idList) ";
            }
        }
        
        $sql = "SELECT e.*, u.full_name as verify_actor 
                FROM employees e
                LEFT JOIN users u ON e.verified_by = u.id
                WHERE e.verification_status = ?";
                
        $params = [$status];
        $types = "s";
        
        if ($esIdFilter) {
            $sql .= $esIdFilter;
        } else if (!empty($search)) {
            $sql .= " AND (e.full_name LIKE ? OR e.employee_code LIKE ?)";
            $searchParam = "%{$search}%";
            $params[] = $searchParam;
            $params[] = $searchParam;
            $types .= "ss";
        }
        
        if ($status !== 'pending') {
            $filters['date_field'] = 'e.verified_date';
        } else {
            $filters['date_field'] = 'e.created_at';
        }

        list($filterSql, $filterParams, $filterTypes) = $this->buildCommonFilterParams($filters, 'e');
        $sql .= $filterSql;
        $params = array_merge($params, $filterParams);
        $types .= $filterTypes;
        
        $countSql = "SELECT COUNT(e.id) as total FROM employees e LEFT JOIN users u ON e.verified_by = u.id WHERE e.verification_status = ?" . 
                    ($esIdFilter ? $esIdFilter : "") . 
                    (empty($search) || $esIdFilter ? "" : " AND (e.full_name LIKE '%" . $this->db->escape($search) . "%' OR e.employee_code LIKE '%" . $this->db->escape($search) . "%')") . 
                    $filterSql;
                    
        $totalResult = $this->db->query($countSql, $params, $types)->fetch_assoc();
        $total = $totalResult['total'] ?? 0;
        
        $allowedSorts = ['full_name', 'employee_code', 'created_at', 'verified_date'];
        $sort = in_array($sort, $allowedSorts) ? $sort : 'created_at';
        $order = strtoupper($order) === 'ASC' ? 'ASC' : 'DESC';
        
        $sql .= " ORDER BY e.$sort $order";
        
        if (!$export) {
            $sql .= " LIMIT ?, ?";
            $params[] = $offset;
            $params[] = $limit;
            $types .= "ii";
        }
        
        $data = $this->db->query($sql, $params, $types)->fetch_all(MYSQLI_ASSOC);
        
        return [
            'data' => $data,
            'total' => $total,
            'pages' => ceil($total / $limit) ?: 1,
            'current_page' => $page
        ];
    }
    
    public function getAcceptedRequests($page = 1, $limit = 10, $search = '', $filters = [], $sort = 'verified_date', $order = 'DESC', $export = false) {
        return $this->getRequestsBase('verified', $page, $limit, $search, $filters, $sort, $order, $export);
    }
    
    public function getRejectedRequests($page = 1, $limit = 10, $search = '', $filters = [], $sort = 'verified_date', $order = 'DESC', $export = false) {
        return $this->getRequestsBase('rejected', $page, $limit, $search, $filters, $sort, $order, $export);
    }
    
    public function getWaitingRequests($page = 1, $limit = 10, $search = '', $filters = [], $sort = 'created_at', $order = 'ASC', $export = false) {
        return $this->getRequestsBase('pending', $page, $limit, $search, $filters, $sort, $order, $export);
    }

    /**
     * Get Appointment Reports
     */
    private function getAppointmentsBase($statusType, $page, $limit, $search, $filters, $sort, $order, $export = false) {
        $offset = ($page - 1) * $limit;
        
        $esIdFilter = "";
        if (!empty($search) && $this->es) {
            $esResult = $this->es->searchAppointments($search, 1000, 0); 
            if ($esResult && $esResult['success']) {
                $ids = array_column($esResult['data'], 'id');
                if (empty($ids)) {
                    return ['data' => [], 'total' => 0, 'pages' => 0, 'current_page' => $page];
                }
                $idList = implode(',', array_map('intval', $ids));
                $esIdFilter = " AND a.id IN ($idList) ";
            }
        }
        
        $sql = "SELECT a.*, 
                       e.full_name as employee_name, e.employee_code, e.contractor_company as company, e.department, e.position as emp_position, e.competency_type,
                       u2.full_name as ktt_name
                FROM appointments a
                LEFT JOIN employees e ON a.employee_id = e.id
                LEFT JOIN users u2 ON a.approved_by = u2.id
                WHERE ";
                
        if ($statusType === 'approved') {
            $sql .= "a.status = 'approved'";
        } else {
            $sql .= "a.status IN ('rejected', 'rejected_by_ktt')";
        }
                
        $params = [];
        $types = "";
        
        if ($esIdFilter) {
            $sql .= $esIdFilter;
        } else if (!empty($search)) {
            $sql .= " AND (a.appointment_number LIKE ? OR e.full_name LIKE ? OR e.employee_code LIKE ?)";
            $searchParam = "%{$search}%";
            $params[] = $searchParam;
            $params[] = $searchParam;
            $params[] = $searchParam;
            $types .= "sss";
        }
        
        if ($statusType === 'approved') {
            $filters['date_field'] = 'a.approved_date';
        } else {
            $filters['date_field'] = 'a.last_rejection_date';
        }
        
        list($filterSql, $filterParams, $filterTypes) = $this->buildCommonFilterParams($filters, 'e');
        
        if (!empty($filters['date_start']) && !empty($filters['date_end'])) {
            $filterSql = str_replace("DATE(e.", "DATE(a.", $filterSql);
        }
        
        $sql .= $filterSql;
        $params = array_merge($params, $filterParams);
        $types .= $filterTypes;
        
        $countSql = "SELECT COUNT(a.id) as total FROM appointments a LEFT JOIN employees e ON a.employee_id = e.id WHERE " . 
                    ($statusType === 'approved' ? "a.status = 'approved'" : "a.status IN ('rejected', 'rejected_by_ktt')") .
                    ($esIdFilter ? $esIdFilter : "") . 
                    (empty($search) || $esIdFilter ? "" : " AND (a.appointment_number LIKE '%" . $this->db->escape($search) . "%' OR e.full_name LIKE '%" . $this->db->escape($search) . "%')") . 
                    $filterSql;
                    
        $totalResult = $this->db->query($countSql, $params, $types)->fetch_assoc();
        $total = $totalResult['total'] ?? 0;
        
        $allowedSorts = ['appointment_number', 'employee_name', 'company', 'approved_date', 'last_rejection_date'];
        if ($sort == 'employee_name') $sortCol = 'e.full_name';
        else if ($sort == 'company') $sortCol = 'e.contractor_company';
        else $sortCol = "a.{$sort}";
        
        $order = strtoupper($order) === 'ASC' ? 'ASC' : 'DESC';
        
        $sql .= " ORDER BY $sortCol $order";
        
        if (!$export) {
            $sql .= " LIMIT ?, ?";
            $params[] = $offset;
            $params[] = $limit;
            $types .= "ii";
        }
        
        $data = $this->db->query($sql, $params, $types)->fetch_all(MYSQLI_ASSOC);
        
        return [
            'data' => $data,
            'total' => $total,
            'pages' => ceil($total / $limit) ?: 1,
            'current_page' => $page
        ];
    }
    
    public function getApprovedAppointments($page = 1, $limit = 10, $search = '', $filters = [], $sort = 'approved_date', $order = 'DESC', $export = false) {
        return $this->getAppointmentsBase('approved', $page, $limit, $search, $filters, $sort, $order, $export);
    }
    
    public function getRejectedAppointments($page = 1, $limit = 10, $search = '', $filters = [], $sort = 'last_rejection_date', $order = 'DESC', $export = false) {
        return $this->getAppointmentsBase('rejected', $page, $limit, $search, $filters, $sort, $order, $export);
    }

    /**
     * Get Expired/Expiring Certificates
     */
    public function getExpiredCertificates($page = 1, $limit = 10, $search = '', $filters = [], $sort = 'expiry_date', $order = 'ASC', $export = false) {
        $offset = ($page - 1) * $limit;
        
        $sql = "SELECT ec.*, 
                       c.name as master_cert_name,
                       e.full_name as employee_name, e.employee_code, e.contractor_company as company, e.department, e.position as emp_position, e.competency_type,
                       DATEDIFF(ec.expiry_date, CURDATE()) as remaining_days
                FROM employee_certifications ec
                LEFT JOIN certifications c ON ec.certification_id = c.id
                LEFT JOIN employees e ON ec.employee_id = e.id
                WHERE ec.expiry_date IS NOT NULL AND ec.expiry_date != '0000-00-00'";
                
        $params = [];
        $types = "";
        
        $certStatus = $filters['cert_status'] ?? 'all_expired';
        if ($certStatus === 'expired_only') {
            $sql .= " AND ec.expiry_date < CURDATE()";
        } else if ($certStatus === 'expiring_soon') {
            $sql .= " AND ec.expiry_date >= CURDATE() AND ec.expiry_date <= DATE_ADD(CURDATE(), INTERVAL 60 DAY)";
        } else {
            $sql .= " AND ec.expiry_date <= DATE_ADD(CURDATE(), INTERVAL 60 DAY)";
        }
        
        if (!empty($search)) {
            $sql .= " AND (e.full_name LIKE ? OR ec.cert_number LIKE ? OR c.name LIKE ?)";
            $searchParam = "%{$search}%";
            $params[] = $searchParam;
            $params[] = $searchParam;
            $params[] = $searchParam;
            $types .= "sss";
        }
        
        list($filterSql, $filterParams, $filterTypes) = $this->buildCommonFilterParams($filters, 'e');
        $sql .= $filterSql;
        $params = array_merge($params, $filterParams);
        $types .= $filterTypes;
        
        $countSql = str_replace(
            "SELECT ec.*, \n                       c.name as master_cert_name,\n                       e.full_name as employee_name, e.employee_code, e.contractor_company as company, e.department, e.position as emp_position, e.competency_type,\n                       DATEDIFF(ec.expiry_date, CURDATE()) as remaining_days",
            "SELECT COUNT(ec.id) as total",
            $sql
        );
        
        $totalResult = $this->db->query($countSql, $params, $types)->fetch_assoc();
        $total = $totalResult['total'] ?? 0;
        
        $allowedSorts = ['expiry_date', 'employee_name', 'company', 'master_cert_name'];
        if ($sort == 'employee_name') $sortCol = 'e.full_name';
        else if ($sort == 'company') $sortCol = 'e.contractor_company';
        else if ($sort == 'master_cert_name') $sortCol = 'c.name';
        else $sortCol = "ec.{$sort}";
        
        $order = strtoupper($order) === 'DESC' ? 'DESC' : 'ASC';
        
        $sql .= " ORDER BY $sortCol $order";
        
        if (!$export) {
            $sql .= " LIMIT ?, ?";
            $params[] = $offset;
            $params[] = $limit;
            $types .= "ii";
        }
        
        $data = $this->db->query($sql, $params, $types)->fetch_all(MYSQLI_ASSOC);
        
        return [
            'data' => $data,
            'total' => $total,
            'pages' => ceil($total / $limit) ?: 1,
            'current_page' => $page
        ];
    }
    
    /**
     * Get unique filter options
     */
    public function getFilterOptions() {
        return [
            'companies' => $this->db->query("SELECT DISTINCT contractor_company as name FROM employees WHERE contractor_company IS NOT NULL AND contractor_company != '' ORDER BY contractor_company")->fetch_all(MYSQLI_ASSOC),
            'departments' => $this->db->query("SELECT DISTINCT department as name FROM employees WHERE department IS NOT NULL AND department != '' ORDER BY department")->fetch_all(MYSQLI_ASSOC),
            'positions' => $this->db->query("SELECT DISTINCT position as name FROM employees WHERE position IS NOT NULL AND position != '' ORDER BY position")->fetch_all(MYSQLI_ASSOC),
            'competencies' => $this->db->query("SELECT DISTINCT competency_type as name FROM employees WHERE competency_type IS NOT NULL AND competency_type != '' ORDER BY competency_type")->fetch_all(MYSQLI_ASSOC),
        ];
    }
}
