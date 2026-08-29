<?php

class SuperadminReportsHelper {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    /**
     * Build the WHERE clause for common filters (Scope, Competency Type, Department)
     * These apply to the `employees` table alias `e`.
     */
    private function buildCommonWhere($filters, $employeeAlias = 'e') {
        $where = " 1=1 ";
        
        // Scope Filter
        if (!empty($filters['scope'])) {
            if ($filters['scope'] === 'TTN') {
                $where .= " AND (LOWER({$employeeAlias}.supervision_area) LIKE '%tondano%' OR LOWER({$employeeAlias}.supervision_area) LIKE '%ttn%') ";
            } elseif ($filters['scope'] === 'MSM') {
                $where .= " AND (LOWER({$employeeAlias}.supervision_area) NOT LIKE '%tondano%' AND LOWER({$employeeAlias}.supervision_area) NOT LIKE '%ttn%') ";
            }
        }
        
        // Competency Type Filter
        if (!empty($filters['competency_type'])) {
            $compType = $this->db->escape($filters['competency_type']);
            $where .= " AND {$employeeAlias}.competency_type = '{$compType}' ";
        }

        // Department Filter
        if (!empty($filters['department'])) {
            $dept = $this->db->escape($filters['department']);
            $where .= " AND {$employeeAlias}.department = '{$dept}' ";
        }
        
        return $where;
    }

    /**
     * Get distinct values for dropdowns
     */
    public function getDepartments() {
        $res = $this->db->query("SELECT DISTINCT department FROM employees WHERE department IS NOT NULL AND department != '' ORDER BY department ASC");
        $depts = [];
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $depts[] = $row['department'];
            }
        }
        return $depts;
    }

    /**
     * Generate the hierarchical summary stats
     */
    public function getSummaryStats($filters) {
        $commonWhere = $this->buildCommonWhere($filters, 'e');
        
        // Date filters for summary might apply generally to created_at of the respective entities, 
        // but typically summary stats in a dashboard represent current state.
        // We will apply date filters if provided, to employees.created_at, appointments.created_at, etc.
        $empDateWhere = "";
        $aptDateWhere = "";
        $certDateWhere = "";
        
        if (!empty($filters['date_start']) && !empty($filters['date_end'])) {
            $start = $this->db->escape($filters['date_start'] . ' 00:00:00');
            $end = $this->db->escape($filters['date_end'] . ' 23:59:59');
            $empDateWhere = " AND e.created_at BETWEEN '$start' AND '$end' ";
            $aptDateWhere = " AND a.created_at BETWEEN '$start' AND '$end' ";
            $certDateWhere = " AND ec.created_at BETWEEN '$start' AND '$end' ";
        }

        $summary = [
            'MSM' => [
                'tenaga_teknis' => ['request' => 0, 'appointment' => 0, 'approved' => 0, 'rejected' => 0, 'certificate' => 0],
                'pengawas_teknis' => ['request' => 0, 'appointment' => 0, 'approved' => 0, 'rejected' => 0, 'certificate' => 0],
                'pengawas_operasional' => ['request' => 0, 'appointment' => 0, 'approved' => 0, 'rejected' => 0, 'certificate' => 0]
            ],
            'TTN' => [
                'tenaga_teknis' => ['request' => 0, 'appointment' => 0, 'approved' => 0, 'rejected' => 0, 'certificate' => 0],
                'pengawas_teknis' => ['request' => 0, 'appointment' => 0, 'approved' => 0, 'rejected' => 0, 'certificate' => 0],
                'pengawas_operasional' => ['request' => 0, 'appointment' => 0, 'approved' => 0, 'rejected' => 0, 'certificate' => 0]
            ]
        ];

        // 1. Requests (Total Requests)
        $sqlReq = "
            SELECT 
                IF(LOWER(e.supervision_area) LIKE '%tondano%' OR LOWER(e.supervision_area) LIKE '%ttn%', 'TTN', 'MSM') as scope,
                e.competency_type,
                COUNT(*) as total
            FROM employees e
            WHERE $commonWhere $empDateWhere AND e.deleted_at IS NULL
            GROUP BY scope, e.competency_type
        ";
        $resReq = $this->db->query($sqlReq);
        if ($resReq) {
            while ($r = $resReq->fetch_assoc()) {
                if (isset($summary[$r['scope']][$r['competency_type']])) {
                    $summary[$r['scope']][$r['competency_type']]['request'] = (int)$r['total'];
                }
            }
        }

        // 2. Appointments (Total, Approved, Rejected)
        $sqlApt = "
            SELECT 
                IF(LOWER(e.supervision_area) LIKE '%tondano%' OR LOWER(e.supervision_area) LIKE '%ttn%', 'TTN', 'MSM') as scope,
                e.competency_type,
                COUNT(*) as total_apt,
                SUM(CASE WHEN a.status = 'approved' THEN 1 ELSE 0 END) as total_approved,
                SUM(CASE WHEN a.status IN ('rejected', 'rejected_by_ktt') THEN 1 ELSE 0 END) as total_rejected
            FROM appointments a
            JOIN employees e ON a.employee_id = e.id
            WHERE $commonWhere $aptDateWhere AND a.deleted_at IS NULL
            GROUP BY scope, e.competency_type
        ";
        $resApt = $this->db->query($sqlApt);
        if ($resApt) {
            while ($r = $resApt->fetch_assoc()) {
                if (isset($summary[$r['scope']][$r['competency_type']])) {
                    $summary[$r['scope']][$r['competency_type']]['appointment'] = (int)$r['total_apt'];
                    $summary[$r['scope']][$r['competency_type']]['approved'] = (int)$r['total_approved'];
                    $summary[$r['scope']][$r['competency_type']]['rejected'] = (int)$r['total_rejected'];
                }
            }
        }

        // 3. Certificates
        $sqlCert = "
            SELECT 
                IF(LOWER(e.supervision_area) LIKE '%tondano%' OR LOWER(e.supervision_area) LIKE '%ttn%', 'TTN', 'MSM') as scope,
                e.competency_type,
                COUNT(*) as total_cert
            FROM employee_certifications ec
            JOIN employees e ON ec.employee_id = e.id
            WHERE $commonWhere $certDateWhere
            GROUP BY scope, e.competency_type
        ";
        $resCert = $this->db->query($sqlCert);
        if ($resCert) {
            while ($r = $resCert->fetch_assoc()) {
                if (isset($summary[$r['scope']][$r['competency_type']])) {
                    $summary[$r['scope']][$r['competency_type']]['certificate'] = (int)$r['total_cert'];
                }
            }
        }

        return $summary;
    }

    /**
     * Get Appointment Detail Report
     */
    public function getAppointmentDetails($filters) {
        $commonWhere = $this->buildCommonWhere($filters, 'e');
        
        $where = $commonWhere . " AND a.deleted_at IS NULL ";
        
        // Status Filter
        if (!empty($filters['status'])) {
            $status = $this->db->escape($filters['status']);
            $where .= " AND a.status = '{$status}' ";
        }

        // Date Filter
        if (!empty($filters['date_start']) && !empty($filters['date_end'])) {
            $start = $this->db->escape($filters['date_start'] . ' 00:00:00');
            $end = $this->db->escape($filters['date_end'] . ' 23:59:59');
            $where .= " AND a.created_at BETWEEN '$start' AND '$end' ";
        }

        $sql = "
            SELECT 
                a.appointment_number,
                e.full_name as employee_name,
                e.contractor_company as company,
                e.department,
                e.competency_name as competency,
                e.competency_type,
                IF(LOWER(e.supervision_area) LIKE '%tondano%' OR LOWER(e.supervision_area) LIKE '%ttn%', 'TTN', 'MSM') as scope_of_work,
                a.status,
                u_ktt.full_name as ktt_name,
                COALESCE(u_admin.full_name, a.last_rejection_by_name) as action_by,
                COALESCE(a.approved_date, a.last_rejection_date, a.admin_approved_date) as action_datetime,
                COALESCE(a.approval_notes, a.last_rejection_notes, a.admin_approval_notes, a.notes) as notes
            FROM appointments a
            JOIN employees e ON a.employee_id = e.id
            LEFT JOIN users u_ktt ON a.approved_by = u_ktt.id OR a.ktt1_approved_by = u_ktt.id OR a.ktt2_approved_by = u_ktt.id
            LEFT JOIN users u_admin ON a.admin_approved_by = u_admin.id
            WHERE $where
            ORDER BY 
                scope_of_work ASC,
                e.competency_type ASC,
                CAST(SUBSTRING_INDEX(a.appointment_number, '/', 1) AS UNSIGNED) ASC,
                a.created_at DESC
        ";
        
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
