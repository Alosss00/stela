<?php

class DashboardHelper {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    public function getSystemStatus() {
        $status = [
            'db_connected' => false,
            'es_connected' => false,
            'php_version' => phpversion(),
            'server_time' => date('Y-m-d H:i:s T')
        ];

        // DB Status
        if ($this->db && $this->db->getConnection()) {
            $status['db_connected'] = true;
        }

        // ES Status (Lightweight check if Bonsai/Elasticsearch is reachable)
        if (defined('ELASTICSEARCH_URL')) {
            try {
                $ch = curl_init(ELASTICSEARCH_URL);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 3);
                curl_setopt($ch, CURLOPT_NOBODY, true);
                curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                if ($httpCode >= 200 && $httpCode < 400) {
                    $status['es_connected'] = true;
                }
            } catch (Exception $e) {
                // Ignore error for dashboard display
            }
        }
        return $status;
    }

    public function getSummaryStats() {
        $stats = [
            'total_users' => 0,
            'total_employees' => 0,
            'total_appointments' => 0,
            'total_certificates' => 0,
            'waiting_requests' => 0,
            'accepted_requests' => 0,
            'rejected_requests' => 0,
            'expired_certificates' => 0
        ];

        $stats['total_users'] = $this->fetchCount("SELECT COUNT(*) as c FROM users");
        $stats['total_employees'] = $this->fetchCount("SELECT COUNT(*) as c FROM employees WHERE is_active = 1");
        $stats['total_appointments'] = $this->fetchCount("SELECT COUNT(*) as c FROM appointments");
        $stats['total_certificates'] = $this->fetchCount("SELECT COUNT(*) as c FROM employee_certifications");
        
        $stats['waiting_requests'] = $this->fetchCount("SELECT COUNT(*) as c FROM employees WHERE verification_status = 'pending' AND is_active = 1");
        $stats['accepted_requests'] = $this->fetchCount("SELECT COUNT(*) as c FROM employees WHERE verification_status = 'verified' AND is_active = 1");
        $stats['rejected_requests'] = $this->fetchCount("SELECT COUNT(*) as c FROM employees WHERE verification_status = 'rejected' AND is_active = 1");
        
        $stats['expired_certificates'] = $this->fetchCount("
            SELECT COUNT(*) as c FROM employee_certifications ec
            JOIN employees e ON ec.employee_id = e.id
            WHERE ec.expiry_date IS NOT NULL AND YEAR(ec.expiry_date) > 0 AND ec.expiry_date < CURDATE() AND e.is_active = 1
            AND ec.status != 'pending'
            AND ec.id = (SELECT MAX(ec2.id) FROM employee_certifications ec2 WHERE ec2.employee_id = ec.employee_id AND ec2.certification_id = ec.certification_id)
        ");

        return $stats;
    }

    public function getAppointmentStats() {
        return [
            'total' => $this->fetchCount("SELECT COUNT(*) as c FROM appointments"),
            'waiting' => $this->fetchCount("SELECT COUNT(*) as c FROM appointments WHERE status = 'pending'"),
            'approved' => $this->fetchCount("SELECT COUNT(*) as c FROM appointments WHERE status = 'approved'"),
            'rejected' => $this->fetchCount("SELECT COUNT(*) as c FROM appointments WHERE status = 'rejected' OR status = 'rejected_by_ktt'")
        ];
    }

    public function getCertificateStats() {
        // Expiring soon logic: <= 60 days
        $total = $this->fetchCount("SELECT COUNT(*) as c FROM employee_certifications ec JOIN employees e ON ec.employee_id = e.id WHERE e.is_active = 1 AND ec.status != 'pending' AND ec.id = (SELECT MAX(ec2.id) FROM employee_certifications ec2 WHERE ec2.employee_id = ec.employee_id AND ec2.certification_id = ec.certification_id)");
        $expired = $this->fetchCount("SELECT COUNT(*) as c FROM employee_certifications ec JOIN employees e ON ec.employee_id = e.id WHERE ec.expiry_date IS NOT NULL AND YEAR(ec.expiry_date) > 0 AND ec.expiry_date < CURDATE() AND e.is_active = 1 AND ec.status != 'pending' AND ec.id = (SELECT MAX(ec2.id) FROM employee_certifications ec2 WHERE ec2.employee_id = ec.employee_id AND ec2.certification_id = ec.certification_id)");
        $expiring_soon = $this->fetchCount("SELECT COUNT(*) as c FROM employee_certifications ec JOIN employees e ON ec.employee_id = e.id WHERE ec.expiry_date IS NOT NULL AND YEAR(ec.expiry_date) > 0 AND ec.expiry_date >= CURDATE() AND ec.expiry_date <= DATE_ADD(CURDATE(), INTERVAL 60 DAY) AND e.is_active = 1 AND ec.status != 'pending' AND ec.id = (SELECT MAX(ec2.id) FROM employee_certifications ec2 WHERE ec2.employee_id = ec.employee_id AND ec2.certification_id = ec.certification_id)");
        $valid = $total - $expired - $expiring_soon;
        if ($valid < 0) $valid = 0; // Failsafe
        
        return [
            'valid' => $valid,
            'expiring_soon' => $expiring_soon,
            'expired' => $expired
        ];
    }

    public function getMonthlyRequests() {
        // Group by month for the last 6 months based on created_at
        $query = "
            SELECT DATE_FORMAT(created_at, '%Y-%m') as month, COUNT(*) as count 
            FROM employees 
            WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
            GROUP BY month
            ORDER BY month ASC
        ";
        $res = $this->db->query($query);
        $data = [];
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $data[$row['month']] = (int)$row['count'];
            }
        }
        return $data;
    }

    public function getRecentRequests($limit = 5) {
        $query = "
            SELECT full_name, employee_code, contractor_company, department, position, verification_status, created_at
            FROM employees
            ORDER BY created_at DESC
            LIMIT " . (int)$limit;
        return $this->fetchAll($query);
    }

    public function getRecentAppointments($limit = 5) {
        $query = "
            SELECT a.appointment_number, a.status, a.created_at, e.full_name, e.contractor_company, e.department
            FROM appointments a
            JOIN employees e ON a.employee_id = e.id
            ORDER BY a.created_at DESC
            LIMIT " . (int)$limit;
        return $this->fetchAll($query);
    }

    public function getCertificateExpirationAlerts($limit = 5) {
        $query = "
            SELECT e.full_name, e.employee_code, e.contractor_company, e.department, 
                   c.cert_name AS certificate_name, ec.cert_number AS certificate_number, ec.expiry_date,
                   DATEDIFF(ec.expiry_date, CURDATE()) as remaining_days
            FROM employee_certifications ec
            JOIN employees e ON ec.employee_id = e.id
            LEFT JOIN certifications c ON ec.certification_id = c.id
            WHERE ec.expiry_date IS NOT NULL AND YEAR(ec.expiry_date) > 0 AND e.is_active = 1
              AND ec.expiry_date <= DATE_ADD(CURDATE(), INTERVAL 60 DAY)
              AND ec.status != 'pending'
              AND ec.id = (SELECT MAX(ec2.id) FROM employee_certifications ec2 WHERE ec2.employee_id = ec.employee_id AND ec2.certification_id = ec.certification_id)
            ORDER BY remaining_days ASC
            LIMIT " . (int)$limit;
        return $this->fetchAll($query);
    }

    public function getRecentActivity($limit = 5) {
        // Fallback since employee_status_logs is missing
        $query = "
            SELECT 
                'Admin Verification' as action, 
                '' as notes,
                e.verified_date as created_at, 
                u.full_name as user_name, 
                e.full_name as employee_name
            FROM employees e
            JOIN users u ON e.verified_by = u.id
            WHERE e.verified_date IS NOT NULL
            UNION ALL
            SELECT 
                'Appointment Initiated' as action, 
                '' as notes,
                a.created_at as created_at, 
                'System' as user_name,
                e.full_name as employee_name 
            FROM appointments a
            JOIN employees e ON a.employee_id = e.id
            ORDER BY created_at DESC
            LIMIT " . (int)$limit;
        return $this->fetchAll($query);
    }

    private function fetchCount($sql) {
        $res = $this->db->query($sql);
        if ($res && $row = $res->fetch_assoc()) {
            return (int) $row['c'];
        }
        return 0;
    }

    private function fetchAll($sql) {
        $res = $this->db->query($sql);
        $data = [];
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $data[] = $row;
            }
        }
        return $data;
    }
}
