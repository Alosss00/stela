<?php
// app/Helpers/MonitoringHelper.php

class MonitoringHelper {
    private $db;
    private $es;

    public function __construct($db) {
        $this->db = $db;
        
        // Initialize Elasticsearch if available
        if (file_exists(dirname(__DIR__) . '/Services/ElasticsearchService.php')) {
            require_once dirname(__DIR__) . '/Services/ElasticsearchService.php';
            if (class_exists('ElasticsearchService')) {
                $this->es = ElasticsearchService::getInstance();
            }
        }
    }

    /**
     * Get Employees for Monitoring Center (All Companies/Departments)
     */
    public function getEmployees($page = 1, $limit = 10, $search = '', $filters = [], $sort = 'created_at', $order = 'DESC') {
        $offset = ($page - 1) * $limit;
        
        // 1. Try Elasticsearch first if search is provided and no complex filters that ES might miss
        if (!empty($search) && $this->es) {
            $esResult = $this->es->searchEmployees($search, $limit, $offset);
            if ($esResult && $esResult['success']) {
                $ids = array_column($esResult['data'], 'id');
                if (!empty($ids)) {
                    $idList = implode(',', array_map('intval', $ids));
                    
                    $sql = "SELECT e.*, u.full_name as verifier_name 
                            FROM employees e
                            LEFT JOIN users u ON e.verified_by = u.id
                            WHERE e.id IN ($idList)";
                            
                    // Apply extra filters if provided alongside ES search
                    $sql .= $this->buildEmployeeFilters($filters);
                    $sql .= " ORDER BY FIELD(e.id, $idList)";
                    
                    $data = $this->db->query($sql)->fetch_all(MYSQLI_ASSOC);
                    
                    return [
                        'data' => $data,
                        'total' => $esResult['total'],
                        'pages' => ceil($esResult['total'] / $limit),
                        'current_page' => $page,
                        'source' => 'elasticsearch'
                    ];
                } else {
                    return ['data' => [], 'total' => 0, 'pages' => 0, 'current_page' => $page, 'source' => 'elasticsearch'];
                }
            }
        }
        
        // 2. MySQL Fallback / Standard Query
        $sql = "SELECT e.*, u.full_name as verifier_name 
                FROM employees e
                LEFT JOIN users u ON e.verified_by = u.id
                WHERE 1=1";
                
        $params = [];
        $types = "";
        
        if (!empty($search)) {
            $sql .= " AND (e.full_name LIKE ? OR e.employee_code LIKE ?)";
            $searchParam = "%{$search}%";
            $params[] = $searchParam;
            $params[] = $searchParam;
            $types .= "ss";
        }
        
        list($filterSql, $filterParams, $filterTypes) = $this->buildEmployeeFilterParams($filters);
        $sql .= $filterSql;
        $params = array_merge($params, $filterParams);
        $types .= $filterTypes;
        
        // Count total
        $countSql = str_replace("SELECT e.*, u.full_name as verifier_name", "SELECT COUNT(e.id) as total", $sql);
        $totalResult = $this->db->query($countSql, $params, $types)->fetch_assoc();
        $total = $totalResult['total'] ?? 0;
        
        // Data
        $allowedSorts = ['full_name', 'company_name', 'department', 'created_at', 'verification_status'];
        $sort = in_array($sort, $allowedSorts) ? $sort : 'created_at';
        $order = strtoupper($order) === 'ASC' ? 'ASC' : 'DESC';
        
        $sql .= " ORDER BY e.$sort $order LIMIT ?, ?";
        $params[] = $offset;
        $params[] = $limit;
        $types .= "ii";
        
        $data = $this->db->query($sql, $params, $types)->fetch_all(MYSQLI_ASSOC);
        
        return [
            'data' => $data,
            'total' => $total,
            'pages' => ceil($total / $limit),
            'current_page' => $page,
            'source' => 'mysql'
        ];
    }
    
    /**
     * Get Employee Summary Stats
     */
    public function getEmployeeStats($filters = []) {
        $sql = "SELECT 
                    COUNT(id) as total,
                    SUM(CASE WHEN verification_status = 'pending' THEN 1 ELSE 0 END) as waiting,
                    SUM(CASE WHEN verification_status = 'verified' THEN 1 ELSE 0 END) as accepted,
                    SUM(CASE WHEN verification_status = 'rejected' THEN 1 ELSE 0 END) as rejected
                FROM employees e WHERE 1=1";
                
        list($filterSql, $filterParams, $filterTypes) = $this->buildEmployeeFilterParams($filters);
        $sql .= $filterSql;
        
        $result = $this->db->query($sql, $filterParams, $filterTypes)->fetch_assoc();
        return $result;
    }

    private function buildEmployeeFilters($filters) {
        $sql = "";
        if (!empty($filters['company'])) {
            $sql .= " AND e.contractor_company = '" . $this->db->escape($filters['company']) . "'";
        }
        if (!empty($filters['department'])) {
            $sql .= " AND e.department = '" . $this->db->escape($filters['department']) . "'";
        }
        if (!empty($filters['status'])) {
            $sql .= " AND e.verification_status = '" . $this->db->escape($filters['status']) . "'";
        }
        return $sql;
    }
    
    private function buildEmployeeFilterParams($filters) {
        $sql = "";
        $params = [];
        $types = "";
        if (!empty($filters['company'])) {
            $sql .= " AND e.contractor_company = ?";
            $params[] = $filters['company'];
            $types .= "s";
        }
        if (!empty($filters['department'])) {
            $sql .= " AND e.department = ?";
            $params[] = $filters['department'];
            $types .= "s";
        }
        if (!empty($filters['status'])) {
            $sql .= " AND e.verification_status = ?";
            $params[] = $filters['status'];
            $types .= "s";
        }
        return [$sql, $params, $types];
    }
    
    /**
     * Get Appointments for Monitoring Center
     */
    public function getAppointments($page = 1, $limit = 10, $search = '', $filters = [], $sort = 'created_at', $order = 'DESC') {
        $offset = ($page - 1) * $limit;
        
        $sql = "SELECT a.*, 
                       e.full_name as employee_name, e.employee_code, e.contractor_company as company, e.department, e.position,
                       u1.full_name as admin_name, u2.full_name as ktt_name
                FROM appointments a
                LEFT JOIN employees e ON a.employee_id = e.id
                LEFT JOIN users u1 ON a.admin_approved_by = u1.id
                LEFT JOIN users u2 ON a.approved_by = u2.id
                WHERE 1=1";
                
        $params = [];
        $types = "";
        
        if (!empty($search)) {
            $sql .= " AND (a.appointment_number LIKE ? OR e.full_name LIKE ? OR e.employee_code LIKE ?)";
            $searchParam = "%{$search}%";
            $params[] = $searchParam;
            $params[] = $searchParam;
            $params[] = $searchParam;
            $types .= "sss";
        }
        
        list($filterSql, $filterParams, $filterTypes) = $this->buildAppointmentFilterParams($filters);
        $sql .= $filterSql;
        $params = array_merge($params, $filterParams);
        $types .= $filterTypes;
        
        // Count total
        $countSql = str_replace("SELECT a.*, 
                       e.full_name as employee_name, e.employee_code, e.contractor_company as company, e.department, e.position,
                       u1.full_name as admin_name, u2.full_name as ktt_name", "SELECT COUNT(a.id) as total", $sql);
        $totalResult = $this->db->query($countSql, $params, $types)->fetch_assoc();
        $total = $totalResult['total'] ?? 0;
        
        // Data
        $allowedSorts = ['appointment_number', 'employee_name', 'company', 'created_at', 'status'];
        // mapping sort
        if ($sort == 'employee_name') $sortCol = 'e.full_name';
        else if ($sort == 'company') $sortCol = 'e.contractor_company';
        else $sortCol = "a.{$sort}";
        
        $order = strtoupper($order) === 'ASC' ? 'ASC' : 'DESC';
        
        $sql .= " ORDER BY $sortCol $order LIMIT ?, ?";
        $params[] = $offset;
        $params[] = $limit;
        $types .= "ii";
        
        $data = $this->db->query($sql, $params, $types)->fetch_all(MYSQLI_ASSOC);
        
        return [
            'data' => $data,
            'total' => $total,
            'pages' => ceil($total / $limit),
            'current_page' => $page
        ];
    }
    
    public function getAppointmentStats($filters = []) {
        $sql = "SELECT 
                    COUNT(a.id) as total,
                    SUM(CASE WHEN a.status IN ('draft', 'pending', 'pending_admin_review', 'verified') THEN 1 ELSE 0 END) as waiting,
                    SUM(CASE WHEN a.status = 'approved' THEN 1 ELSE 0 END) as approved,
                    SUM(CASE WHEN a.status IN ('rejected', 'rejected_by_ktt') THEN 1 ELSE 0 END) as rejected
                FROM appointments a
                LEFT JOIN employees e ON a.employee_id = e.id
                WHERE 1=1";
                
        list($filterSql, $filterParams, $filterTypes) = $this->buildAppointmentFilterParams($filters);
        $sql .= $filterSql;
        
        $result = $this->db->query($sql, $filterParams, $filterTypes)->fetch_assoc();
        return $result;
    }
    
    private function buildAppointmentFilterParams($filters) {
        $sql = "";
        $params = [];
        $types = "";
        if (!empty($filters['company'])) {
            $sql .= " AND e.contractor_company = ?";
            $params[] = $filters['company'];
            $types .= "s";
        }
        if (!empty($filters['department'])) {
            $sql .= " AND e.department = ?";
            $params[] = $filters['department'];
            $types .= "s";
        }
        if (!empty($filters['status'])) {
            $sql .= " AND a.status = ?";
            $params[] = $filters['status'];
            $types .= "s";
        }
        return [$sql, $params, $types];
    }
    
    /**
     * Get Certificates for Monitoring
     */
    public function getCertificates($page = 1, $limit = 10, $search = '', $filters = [], $sort = 'expiry_date', $order = 'ASC') {
        $offset = ($page - 1) * $limit;
        
        $sql = "SELECT ec.*, 
                       c.name as master_cert_name,
                       e.full_name as employee_name, e.employee_code, e.contractor_company as company, e.department, e.position
                FROM employee_certifications ec
                LEFT JOIN certifications c ON ec.certification_id = c.id
                LEFT JOIN employees e ON ec.employee_id = e.id
                WHERE 1=1";
                
        $params = [];
        $types = "";
        
        if (!empty($search)) {
            $sql .= " AND (e.full_name LIKE ? OR ec.cert_number LIKE ? OR c.name LIKE ?)";
            $searchParam = "%{$search}%";
            $params[] = $searchParam;
            $params[] = $searchParam;
            $params[] = $searchParam;
            $types .= "sss";
        }
        
        list($filterSql, $filterParams, $filterTypes) = $this->buildCertificateFilterParams($filters);
        $sql .= $filterSql;
        $params = array_merge($params, $filterParams);
        $types .= $filterTypes;
        
        $countSql = str_replace("SELECT ec.*, 
                       c.name as master_cert_name,
                       e.full_name as employee_name, e.employee_code, e.contractor_company as company, e.department, e.position", "SELECT COUNT(ec.id) as total", $sql);
        $totalResult = $this->db->query($countSql, $params, $types)->fetch_assoc();
        $total = $totalResult['total'] ?? 0;
        
        $allowedSorts = ['expiry_date', 'employee_name', 'company', 'master_cert_name'];
        if ($sort == 'employee_name') $sortCol = 'e.full_name';
        else if ($sort == 'company') $sortCol = 'e.contractor_company';
        else if ($sort == 'master_cert_name') $sortCol = 'c.name';
        else $sortCol = "ec.{$sort}";
        
        $order = strtoupper($order) === 'DESC' ? 'DESC' : 'ASC';
        
        $sql .= " ORDER BY $sortCol $order LIMIT ?, ?";
        $params[] = $offset;
        $params[] = $limit;
        $types .= "ii";
        
        $data = $this->db->query($sql, $params, $types)->fetch_all(MYSQLI_ASSOC);
        
        // Add dynamic certificate status
        foreach ($data as &$row) {
            $row['monitoring_status'] = $this->calculateCertificateStatus($row['expiry_date']);
        }
        
        // Post-filter if user filtered by our calculated status (valid, expiring, expired)
        if (!empty($filters['cert_status'])) {
            $data = array_filter($data, function($item) use ($filters) {
                return $item['monitoring_status'] === $filters['cert_status'];
            });
            // Total will be inaccurate, but okay for this simple implementation
            $total = count($data);
        }
        
        return [
            'data' => array_values($data),
            'total' => $total,
            'pages' => ceil($total / $limit),
            'current_page' => $page
        ];
    }
    
    public function getCertificateStats($filters = []) {
        // Since we calculate status dynamically, we need to fetch all matching basic filters to sum them up.
        // This could be heavy on a massive table, but acceptable for monitoring.
        
        $sql = "SELECT ec.expiry_date
                FROM employee_certifications ec
                LEFT JOIN employees e ON ec.employee_id = e.id
                WHERE 1=1";
                
        list($filterSql, $filterParams, $filterTypes) = $this->buildCertificateFilterParams($filters);
        $sql .= $filterSql;
        
        $results = $this->db->query($sql, $filterParams, $filterTypes)->fetch_all(MYSQLI_ASSOC);
        
        $stats = [
            'total' => count($results),
            'valid' => 0,
            'expiring_soon' => 0,
            'expired' => 0
        ];
        
        foreach ($results as $row) {
            $status = $this->calculateCertificateStatus($row['expiry_date']);
            if ($status === 'Valid') $stats['valid']++;
            else if ($status === 'Expiring Soon') $stats['expiring_soon']++;
            else $stats['expired']++;
        }
        
        return $stats;
    }
    
    private function buildCertificateFilterParams($filters) {
        $sql = "";
        $params = [];
        $types = "";
        if (!empty($filters['company'])) {
            $sql .= " AND e.contractor_company = ?";
            $params[] = $filters['company'];
            $types .= "s";
        }
        if (!empty($filters['department'])) {
            $sql .= " AND e.department = ?";
            $params[] = $filters['department'];
            $types .= "s";
        }
        return [$sql, $params, $types];
    }

    public function calculateCertificateStatus($expiry_date) {
        if (empty($expiry_date) || $expiry_date == '0000-00-00') {
            return 'Valid'; // No expiry date means lifetime validity
        }
        
        $expiry = new DateTime($expiry_date);
        $now = new DateTime();
        
        if ($expiry < $now) {
            return 'Expired';
        }
        
        $interval = $now->diff($expiry);
        $days = $interval->days;
        
        // Usually expiring soon is <= 30 or 60 days. Using 60 here.
        if ($days <= 60) {
            return 'Expiring Soon';
        }
        
        return 'Valid';
    }
    
    /**
     * Get Approval Monitoring
     */
    public function getApprovals($page = 1, $limit = 10, $search = '', $filters = []) {
        $offset = ($page - 1) * $limit;
        
        // This query combines approvals and rejections into one timeline view
        $sql = "SELECT 
                    'approve' as action_type, ka.id, ka.appointment_id, ka.ktt_user_id, ka.approval_notes as notes, ka.approval_date as action_date,
                    a.appointment_number, e.full_name as employee_name, e.contractor_company as company, e.department,
                    u.full_name as reviewer_name, u.role as reviewer_role
                FROM ktt_approvals ka
                JOIN appointments a ON ka.appointment_id = a.id
                JOIN employees e ON a.employee_id = e.id
                JOIN users u ON ka.ktt_user_id = u.id
                WHERE 1=1 ";
                
        // Add Rejections
        $sql .= " UNION ALL ";
        
        $sql .= "SELECT 
                    'reject' as action_type, kr.id, kr.appointment_id, kr.ktt_user_id, kr.rejection_notes as notes, kr.rejection_date as action_date,
                    a.appointment_number, e.full_name as employee_name, e.contractor_company as company, e.department,
                    u.full_name as reviewer_name, u.role as reviewer_role
                FROM ktt_rejections kr
                JOIN appointments a ON kr.appointment_id = a.id
                JOIN employees e ON a.employee_id = e.id
                JOIN users u ON kr.ktt_user_id = u.id
                WHERE 1=1 ";
                
        $sql = "SELECT * FROM ($sql) as combined WHERE 1=1";
        
        $params = [];
        $types = "";
        
        if (!empty($search)) {
            $sql .= " AND (employee_name LIKE ? OR appointment_number LIKE ? OR reviewer_name LIKE ?)";
            $searchParam = "%{$search}%";
            $params[] = $searchParam;
            $params[] = $searchParam;
            $params[] = $searchParam;
            $types .= "sss";
        }
        
        if (!empty($filters['company'])) {
            $sql .= " AND company = ?";
            $params[] = $filters['company'];
            $types .= "s";
        }
        if (!empty($filters['department'])) {
            $sql .= " AND department = ?";
            $params[] = $filters['department'];
            $types .= "s";
        }
        if (!empty($filters['status'])) {
            $sql .= " AND action_type = ?";
            $params[] = $filters['status'] == 'Approved' ? 'approve' : 'reject';
            $types .= "s";
        }
        
        // Count total
        $countSql = "SELECT COUNT(*) as total FROM ($sql) as c";
        $totalResult = $this->db->query($countSql, $params, $types)->fetch_assoc();
        $total = $totalResult['total'] ?? 0;
        
        $sql .= " ORDER BY action_date DESC LIMIT ?, ?";
        $params[] = $offset;
        $params[] = $limit;
        $types .= "ii";
        
        $data = $this->db->query($sql, $params, $types)->fetch_all(MYSQLI_ASSOC);
        
        return [
            'data' => $data,
            'total' => $total,
            'pages' => ceil($total / $limit),
            'current_page' => $page
        ];
    }
    
    /**
     * Get unique companies for filtering
     */
    public function getCompanies() {
        return $this->db->query("SELECT DISTINCT contractor_company FROM employees WHERE contractor_company IS NOT NULL AND contractor_company != '' ORDER BY contractor_company")->fetch_all(MYSQLI_ASSOC);
    }
    
    /**
     * Get unique departments for filtering
     */
    public function getDepartments() {
        return $this->db->query("SELECT DISTINCT department FROM employees WHERE department IS NOT NULL AND department != '' ORDER BY department")->fetch_all(MYSQLI_ASSOC);
    }
    
    /**
     * Get Employee Detail including Status Timeline
     */
    public function getEmployeeDetail($id) {
        $sql = "SELECT e.*, u.full_name as verifier_name 
                FROM employees e
                LEFT JOIN users u ON e.verified_by = u.id
                WHERE e.id = ?";
        $employee = $this->db->query($sql, [$id], 'i')->fetch_assoc();
        
        if (!$employee) return null;
        
        // Get Certificates
        $sqlCerts = "SELECT ec.*, c.name as master_cert_name
                     FROM employee_certifications ec
                     LEFT JOIN certifications c ON ec.certification_id = c.id
                     WHERE ec.employee_id = ?";
        $certs = $this->db->query($sqlCerts, [$id], 'i')->fetch_all(MYSQLI_ASSOC);
        
        foreach ($certs as &$cert) {
            $cert['monitoring_status'] = $this->calculateCertificateStatus($cert['expiry_date']);
        }
        
        // Get Appointments
        $sqlAppts = "SELECT a.*, 
                            u1.full_name as admin_name, u2.full_name as ktt_name
                     FROM appointments a
                     LEFT JOIN users u1 ON a.admin_approved_by = u1.id
                     LEFT JOIN users u2 ON a.approved_by = u2.id
                     WHERE a.employee_id = ? ORDER BY a.created_at DESC";
        $appts = $this->db->query($sqlAppts, [$id], 'i')->fetch_all(MYSQLI_ASSOC);
        
        // Generate Timeline Fallback
        $timeline = [];
        $timeline[] = [
            'date' => $employee['created_at'],
            'title' => 'Request Submitted',
            'desc' => 'Employee request created in the system',
            'icon' => 'fa-file-signature',
            'color' => 'primary'
        ];
        
        if ($employee['verification_status'] == 'verified') {
            $timeline[] = [
                'date' => $employee['verified_date'],
                'title' => 'Verified by Admin',
                'desc' => 'Verified by ' . ($employee['verifier_name'] ?? 'Admin'),
                'icon' => 'fa-check-circle',
                'color' => 'success'
            ];
        } else if ($employee['verification_status'] == 'rejected') {
            $timeline[] = [
                'date' => $employee['verified_date'],
                'title' => 'Rejected by Admin',
                'desc' => 'Rejected by ' . ($employee['verifier_name'] ?? 'Admin') . ' (Notes: ' . $employee['verification_notes'] . ')',
                'icon' => 'fa-times-circle',
                'color' => 'danger'
            ];
        }
        
        foreach ($appts as $appt) {
            $timeline[] = [
                'date' => $appt['created_at'],
                'title' => 'Appointment Initiated',
                'desc' => 'Appointment number ' . $appt['appointment_number'] . ' generated.',
                'icon' => 'fa-file-contract',
                'color' => 'info'
            ];
            
            if ($appt['admin_approved_date']) {
                 $timeline[] = [
                    'date' => $appt['admin_approved_date'],
                    'title' => 'Admin Reviewed Appointment',
                    'desc' => 'Reviewed by ' . ($appt['admin_name'] ?? 'Admin'),
                    'icon' => 'fa-user-shield',
                    'color' => 'primary'
                ];
            }
            
            if ($appt['status'] == 'approved' && $appt['approved_date']) {
                 $timeline[] = [
                    'date' => $appt['approved_date'],
                    'title' => 'Approved by KTT',
                    'desc' => 'Approved by ' . ($appt['ktt_name'] ?? 'KTT'),
                    'icon' => 'fa-check-double',
                    'color' => 'success'
                ];
            } else if (in_array($appt['status'], ['rejected', 'rejected_by_ktt'])) {
                 $timeline[] = [
                    'date' => $appt['last_rejection_date'] ?: $appt['updated_at'],
                    'title' => 'Rejected by KTT',
                    'desc' => 'Rejected by ' . ($appt['last_rejection_by_name'] ?? 'KTT') . ' (Notes: ' . $appt['last_rejection_notes'] . ')',
                    'icon' => 'fa-ban',
                    'color' => 'danger'
                ];
            }
        }
        
        // Sort timeline by date
        usort($timeline, function($a, $b) {
            return strtotime($a['date']) - strtotime($b['date']);
        });
        
        return [
            'employee' => $employee,
            'certifications' => $certs,
            'appointments' => $appts,
            'timeline' => $timeline
        ];
    }

    public function getAppointmentDetail($id) {
        $sql = "SELECT a.*, 
                       e.full_name as employee_name, e.employee_code, e.contractor_company as company, e.department, e.position as emp_position, e.competency_type,
                       u1.full_name as admin_name, u2.full_name as ktt_name
                FROM appointments a
                LEFT JOIN employees e ON a.employee_id = e.id
                LEFT JOIN users u1 ON a.admin_approved_by = u1.id
                LEFT JOIN users u2 ON a.approved_by = u2.id
                WHERE a.id = ?";
        $appointment = $this->db->query($sql, [$id], 'i')->fetch_assoc();
        
        if (!$appointment) return null;
        
        // Approvals
        $sqlApprovals = "SELECT ka.*, u.full_name as ktt_name, u.role as ktt_role
                         FROM ktt_approvals ka
                         JOIN users u ON ka.ktt_user_id = u.id
                         WHERE ka.appointment_id = ? ORDER BY ka.approval_date ASC";
        $approvals = $this->db->query($sqlApprovals, [$id], 'i')->fetch_all(MYSQLI_ASSOC);
        
        // Rejections
        $sqlRejections = "SELECT kr.*, u.full_name as ktt_name, u.role as ktt_role
                         FROM ktt_rejections kr
                         JOIN users u ON kr.ktt_user_id = u.id
                         WHERE kr.appointment_id = ? ORDER BY kr.rejection_date ASC";
        $rejections = $this->db->query($sqlRejections, [$id], 'i')->fetch_all(MYSQLI_ASSOC);
        
        return [
            'appointment' => $appointment,
            'approvals' => $approvals,
            'rejections' => $rejections
        ];
    }

    /**
     * Get Global Status History (Fallback since employee_status_logs is missing)
     */
    public function getStatusHistory($page = 1, $limit = 20) {
        $offset = ($page - 1) * $limit;
        
        $sql = "SELECT 
                    'Employee Registration' as event_type, 
                    created_at as event_date, 
                    full_name as employee_name, 
                    employee_code,
                    contractor_company as company,
                    'System' as actor,
                    'Pending Verification' as notes
                FROM employees
                UNION ALL
                SELECT 
                    'Admin Verification' as event_type, 
                    verified_date as event_date, 
                    e.full_name as employee_name, 
                    e.employee_code,
                    e.contractor_company as company,
                    u.full_name as actor,
                    e.verification_notes as notes
                FROM employees e
                JOIN users u ON e.verified_by = u.id
                WHERE e.verified_date IS NOT NULL
                UNION ALL
                SELECT 
                    'Appointment Initiated' as event_type, 
                    a.created_at as event_date, 
                    e.full_name as employee_name, 
                    e.employee_code,
                    e.contractor_company as company,
                    'System' as actor,
                    CONCAT('Appt. Number: ', a.appointment_number) as notes
                FROM appointments a
                JOIN employees e ON a.employee_id = e.id
                UNION ALL
                SELECT 
                    'Admin Reviewed Appointment' as event_type, 
                    a.admin_approved_date as event_date, 
                    e.full_name as employee_name, 
                    e.employee_code,
                    e.contractor_company as company,
                    u.full_name as actor,
                    a.admin_approval_notes as notes
                FROM appointments a
                JOIN employees e ON a.employee_id = e.id
                JOIN users u ON a.admin_approved_by = u.id
                WHERE a.admin_approved_date IS NOT NULL
                UNION ALL
                SELECT 
                    'KTT Approval' as event_type, 
                    ka.approval_date as event_date, 
                    e.full_name as employee_name, 
                    e.employee_code,
                    e.contractor_company as company,
                    u.full_name as actor,
                    ka.approval_notes as notes
                FROM ktt_approvals ka
                JOIN appointments a ON ka.appointment_id = a.id
                JOIN employees e ON a.employee_id = e.id
                JOIN users u ON ka.ktt_user_id = u.id
                UNION ALL
                SELECT 
                    'KTT Rejection' as event_type, 
                    kr.rejection_date as event_date, 
                    e.full_name as employee_name, 
                    e.employee_code,
                    e.contractor_company as company,
                    u.full_name as actor,
                    kr.rejection_notes as notes
                FROM ktt_rejections kr
                JOIN appointments a ON kr.appointment_id = a.id
                JOIN employees e ON a.employee_id = e.id
                JOIN users u ON kr.ktt_user_id = u.id
                
                ORDER BY event_date DESC LIMIT ?, ?";
                
        $data = $this->db->query($sql, [$offset, $limit], 'ii')->fetch_all(MYSQLI_ASSOC);
        
        $countSql = "SELECT SUM(cnt) as total FROM (
            SELECT COUNT(*) as cnt FROM employees
            UNION ALL
            SELECT COUNT(*) FROM employees WHERE verified_date IS NOT NULL
            UNION ALL
            SELECT COUNT(*) FROM appointments
            UNION ALL
            SELECT COUNT(*) FROM appointments WHERE admin_approved_date IS NOT NULL
            UNION ALL
            SELECT COUNT(*) FROM ktt_approvals
            UNION ALL
            SELECT COUNT(*) FROM ktt_rejections
        ) as t";
        $totalRes = $this->db->query($countSql)->fetch_assoc();
        $total = $totalRes['total'] ?? 0;
        
        return [
            'data' => $data,
            'total' => $total,
            'pages' => ceil($total / $limit),
            'current_page' => $page
        ];
    }
}
