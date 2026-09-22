<?php
/**
 * Workflow History / Audit Trail Service
 */

require_once dirname(__DIR__, 2) . '/config/app.php';
require_once dirname(__DIR__) . '/Models/Database.php';

class AuditService {
    private $db;

    public function __construct() {
        $this->db = new Database();
    }

    /**
     * Log an action to the workflow history
     * 
     * @param int|null $employee_id
     * @param int|null $appointment_id
     * @param string $action_type e.g. 'Submit Request', 'Verified', 'Rejected by KTT'
     * @param string|null $status_before
     * @param string|null $status_after
     * @param string|null $notes
     * @return bool
     */
    public function log($employee_id, $appointment_id, $action_type, $status_before = null, $status_after = null, $notes = null) {
        $actor_id = $_SESSION['user_id'] ?? 0;
        $actor_role = $_SESSION['role'] ?? 'System';

        // Additional fallback for role translation to human readable
        if ($actor_role == 'superadmin') $actor_role = 'Super Admin';
        elseif ($actor_role == 'admin') $actor_role = 'Admin';
        elseif ($actor_role == 'department_user' || $actor_role == 'user') $actor_role = 'User/Dept';
        elseif ($actor_role == 'ktt') {
            // Dynamic KTT type lookup — no hardcoded user IDs
            $ktt_row = $this->db->query(
                "SELECT ktt_type FROM users WHERE id = ? AND ktt_type IS NOT NULL",
                [$actor_id]
            );
            if ($ktt_row && $kr = $ktt_row->fetch_assoc()) {
                $actor_role = ($kr['ktt_type'] === 'msm') ? 'KTT MSM' : 'KTT TTN';
            } else {
                // Might be a delegatee acting as KTT — check ktt_delegations
                $del_row = $this->db->query(
                    "SELECT ktt_type FROM ktt_delegations
                     WHERE delegate_user_id = ? AND status = 'active'
                     AND start_date <= CURDATE() AND end_date >= CURDATE()
                     LIMIT 1",
                    [$actor_id]
                );
                if ($del_row && $dr = $del_row->fetch_assoc()) {
                    $actor_role = ($dr['ktt_type'] === 'msm') ? 'KTT MSM (Delegated)' : 'KTT TTN (Delegated)';
                } else {
                    $actor_role = 'KTT';
                }
            }
        }

        $sql = "INSERT INTO workflow_history 
                (employee_id, appointment_id, action_type, status_before, status_after, actor_id, actor_role, notes, created_at)
                VALUES 
                (?, ?, ?, ?, ?, ?, ?, ?, NOW())";

        $params = [
            $employee_id ? intval($employee_id) : null,
            $appointment_id ? intval($appointment_id) : null,
            $action_type,
            $status_before,
            $status_after,
            $actor_id,
            $actor_role,
            $notes
        ];
        $types = 'iisssiss';

        return $this->db->query($sql, $params, $types);
    }
    
    /**
     * Get history for an employee
     */
    public function getHistoryByEmployee($employee_id) {
        $employee_id = intval($employee_id);
        $sql = "SELECT wh.*, u.full_name as actor_name 
                FROM workflow_history wh
                LEFT JOIN users u ON wh.actor_id = u.id
                WHERE wh.employee_id = ? 
                ORDER BY wh.created_at ASC";
        
        $result = $this->db->query($sql, [$employee_id], "i");
        $history = [];
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $history[] = $row;
            }
        }
        return $history;
    }
    
    /**
     * Get history for an appointment
     */
    public function getHistoryByAppointment($appointment_id) {
        $appointment_id = intval($appointment_id);
        // Note: For appointments, we might also want to show the history of the employee BEFORE the appointment was created.
        // So we get history where appointment_id matches OR (employee_id matches AND appointment_id IS NULL)
        
        // First get employee_id
        $emp_id = null;
        $res = $this->db->query("SELECT employee_id FROM appointments WHERE id = ?", [$appointment_id], "i");
        if ($res && $row = $res->fetch_assoc()) {
            $emp_id = $row['employee_id'];
        }
        
        $params = [];
        $types = "";
        
        if ($emp_id) {
            $sql = "SELECT wh.*, u.full_name as actor_name 
                    FROM workflow_history wh
                    LEFT JOIN users u ON wh.actor_id = u.id
                    WHERE wh.appointment_id = ? OR (wh.employee_id = ? AND wh.appointment_id IS NULL)
                    ORDER BY wh.created_at ASC";
            $params = [$appointment_id, $emp_id];
            $types = "ii";
        } else {
            $sql = "SELECT wh.*, u.full_name as actor_name 
                    FROM workflow_history wh
                    LEFT JOIN users u ON wh.actor_id = u.id
                    WHERE wh.appointment_id = ?
                    ORDER BY wh.created_at ASC";
            $params = [$appointment_id];
            $types = "i";
        }
        
        $result = $this->db->query($sql, $params, $types);
        $history = [];
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $history[] = $row;
            }
        }
        return $history;
    }
}
