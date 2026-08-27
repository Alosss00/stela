<?php

class MasterDataHelper {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    /**
     * Get paginated data from any master table
     */
    public function getPaginatedData($table, $page = 1, $limit = 10, $search = '', $filters = [], $searchFields = ['name']) {
        $offset = ($page - 1) * $limit;
        $softDeleteTables = ['positions', 'supervision_areas', 'competencies', 'companies', 'departments', 'competency_sub_competencies', 'certifications'];
        $where = in_array($table, $softDeleteTables) ? ["$table.deleted_at IS NULL"] : ["1=1"];
        $params = [];
        $types = "";

        // Text Search
        if (!empty($search) && !empty($searchFields)) {
            $searchClauses = [];
            $searchTerm = "%$search%";
            foreach ($searchFields as $field) {
                $searchClauses[] = "$field LIKE ?";
                $params[] = $searchTerm;
                $types .= "s";
            }
            $where[] = "(" . implode(" OR ", $searchClauses) . ")";
        }

        // Exact Filters
        foreach ($filters as $col => $val) {
            if ($val !== '') {
                $where[] = "$col = ?";
                $params[] = $val;
                $types .= is_int($val) ? "i" : "s";
            }
        }

        $whereClause = implode(" AND ", $where);
        
        // Custom JOINS for specific tables
        $joinClause = "";
        $selectClause = "$table.*";
        
        if ($table === 'competency_sub_competencies') {
            $joinClause = "LEFT JOIN competencies ON competency_sub_competencies.competency_id = competencies.id";
            $selectClause = "competency_sub_competencies.*, competencies.competency_name";
        } elseif ($table === 'position_requirements') {
            $joinClause = "LEFT JOIN positions ON position_requirements.position_id = positions.id 
                           LEFT JOIN certifications ON position_requirements.certification_id = certifications.id";
            $selectClause = "position_requirements.*, positions.position_name, certifications.cert_name";
        } elseif ($table === 'positions') {
            $joinClause = "LEFT JOIN competencies ON positions.competency_id = competencies.id";
            $selectClause = "positions.*, competencies.competency_name";
        }
        
        // Count total
        $countQuery = "SELECT COUNT(*) as total FROM $table $joinClause WHERE $whereClause";
        $stmtCount = $this->db->prepare($countQuery);
        if (!empty($params)) {
            $this->bindStatementParams($stmtCount, $types, $params);
        }
        $stmtCount->execute();
        $totalResult = $stmtCount->get_result()->fetch_assoc();
        $totalRecords = $totalResult['total'] ?? 0;
        $stmtCount->close();

        // Get data
        $orderBy = "created_at DESC";
        $query = "SELECT $selectClause FROM $table $joinClause WHERE $whereClause ORDER BY $orderBy LIMIT ? OFFSET ?";
        
        $params[] = $limit;
        $params[] = $offset;
        $types .= "ii";

        $stmt = $this->db->prepare($query);
        $this->bindStatementParams($stmt, $types, $params);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $data = [];
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
        $stmt->close();

        return [
            'data' => $data,
            'total' => $totalRecords,
            'pages' => ceil($totalRecords / $limit)
        ];
    }

    /**
     * Create Record with Duplicate Check
     */
    public function createRecord($table, $data, $uniqueField = null, $uniqueValue = null) {
        if ($uniqueField && $uniqueValue) {
            if ($this->checkExists($table, $uniqueField, $uniqueValue)) {
                return ['status' => 'error', 'message' => "Record with this $uniqueField already exists."];
            }
        }

        // Specifically for position_requirements, prevent duplicate combo
        if ($table === 'position_requirements') {
            $chk = $this->db->prepare("SELECT id FROM position_requirements WHERE position_id = ? AND certification_id = ?");
            $chk->bind_param("ii", $data['position_id'], $data['certification_id']);
            $chk->execute();
            if ($chk->get_result()->num_rows > 0) {
                return ['status' => 'error', 'message' => "This certification is already required for this position."];
            }
        }

        $columns = array_keys($data);
        $values = array_values($data);
        $placeholders = str_repeat('?,', count($values) - 1) . '?';
        $types = $this->getTypes($values);
        
        $query = "INSERT INTO $table (" . implode(', ', $columns) . ") VALUES ($placeholders)";
        $stmt = $this->db->prepare($query);
        $this->bindStatementParams($stmt, $types, $values);
        
        if ($stmt->execute()) {
            return ['status' => 'success', 'message' => 'Record created successfully.'];
        }
        return ['status' => 'error', 'message' => 'Failed to create record.'];
    }

    /**
     * Update Record with Duplicate Check
     */
    public function updateRecord($table, $id, $data, $uniqueField = null, $uniqueValue = null) {
        if ($uniqueField && $uniqueValue) {
            if ($this->checkExists($table, $uniqueField, $uniqueValue, $id)) {
                return ['status' => 'error', 'message' => "Record with this $uniqueField already exists."];
            }
        }

        $setClause = [];
        $values = [];
        foreach ($data as $col => $val) {
            $setClause[] = "$col = ?";
            $values[] = $val;
        }
        $values[] = $id; // For WHERE id = ?
        $types = $this->getTypes($values);
        
        $query = "UPDATE $table SET " . implode(', ', $setClause) . " WHERE id = ?";
        $stmt = $this->db->prepare($query);
        $this->bindStatementParams($stmt, $types, $values);
        
        if ($stmt->execute()) {
            return ['status' => 'success', 'message' => 'Record updated successfully.'];
        }
        return ['status' => 'error', 'message' => 'Failed to update record.'];
    }

    /**
     * Smart Delete / Safe Deactivate
     */
    public function deleteOrDeactivateRecord($table, $id) {
        $hasDependency = false;
        
        // 1. Dependency checks based on table
        if ($table === 'positions') {
            $stmt = $this->db->prepare("SELECT position_name FROM positions WHERE id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $pos = $stmt->get_result()->fetch_assoc();
            if ($pos) {
                // Check employees by name (since employee stores varchar)
                $chk1 = $this->db->prepare("SELECT id FROM employees WHERE position = ? LIMIT 1");
                $chk1->bind_param("s", $pos['position_name']);
                $chk1->execute();
                if ($chk1->get_result()->num_rows > 0) $hasDependency = true;
                
                // Check position_requirements
                $chk2 = $this->db->query("SELECT id FROM position_requirements WHERE position_id = $id LIMIT 1");
                if ($chk2 && $chk2->num_rows > 0) $hasDependency = true;
            }
        } 
        elseif ($table === 'competencies') {
            $stmt = $this->db->prepare("SELECT competency_name FROM competencies WHERE id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $comp = $stmt->get_result()->fetch_assoc();
            if ($comp) {
                // Check employees
                $chk1 = $this->db->prepare("SELECT id FROM employees WHERE competency_name = ? LIMIT 1");
                $chk1->bind_param("s", $comp['competency_name']);
                $chk1->execute();
                if ($chk1->get_result()->num_rows > 0) $hasDependency = true;

                // Check sub competencies
                $chk2 = $this->db->query("SELECT id FROM competency_sub_competencies WHERE competency_id = $id LIMIT 1");
                if ($chk2 && $chk2->num_rows > 0) $hasDependency = true;
                
                // Check positions
                $chk3 = $this->db->query("SELECT id FROM positions WHERE competency_id = $id LIMIT 1");
                if ($chk3 && $chk3->num_rows > 0) $hasDependency = true;
            }
        }
        elseif ($table === 'competency_sub_competencies') {
            $stmt = $this->db->prepare("SELECT sub_competency_name FROM competency_sub_competencies WHERE id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $sub = $stmt->get_result()->fetch_assoc();
            if ($sub) {
                $chk1 = $this->db->prepare("SELECT id FROM employees WHERE sub_competency = ? LIMIT 1");
                $chk1->bind_param("s", $sub['sub_competency_name']);
                $chk1->execute();
                if ($chk1->get_result()->num_rows > 0) $hasDependency = true;
            }
        }
        elseif ($table === 'certifications') {
            // Check employee_certifications
            $chk1 = $this->db->query("SELECT id FROM employee_certifications WHERE certification_id = $id LIMIT 1");
            if ($chk1 && $chk1->num_rows > 0) $hasDependency = true;

            // Check position_requirements
            $chk2 = $this->db->query("SELECT id FROM position_requirements WHERE certification_id = $id LIMIT 1");
            if ($chk2 && $chk2->num_rows > 0) $hasDependency = true;
        }
        elseif ($table === 'supervision_areas') {
            $stmt = $this->db->prepare("SELECT area_name FROM supervision_areas WHERE id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $area = $stmt->get_result()->fetch_assoc();
            if ($area) {
                $chk1 = $this->db->prepare("SELECT id FROM employees WHERE supervision_area = ? LIMIT 1");
                $chk1->bind_param("s", $area['area_name']);
                $chk1->execute();
                if ($chk1->get_result()->num_rows > 0) $hasDependency = true;
            }
        }

        // 2. Action execution
        if ($hasDependency) {
            // Safe Deactivate (Only if table has is_active)
            $res = $this->db->query("SHOW COLUMNS FROM $table LIKE 'is_active'");
            if ($res && $res->num_rows > 0) {
                $q = "UPDATE $table SET is_active = 0 WHERE id = ?";
                $stmt = $this->db->prepare($q);
                $stmt->bind_param("i", $id);
                $stmt->execute();
                return ['status' => 'warning', 'message' => "This record is currently in use and cannot be permanently deleted. It has been safely deactivated instead."];
            } else {
                return ['status' => 'error', 'message' => "This record is in use by other modules and cannot be deleted."];
            }
        } else {
            // Soft Delete via Database model or Hard Delete if not supported
            if ($this->db->delete($table, "id = ?", [$id])) {
                return ['status' => 'success', 'message' => "Record deleted from system."];
            }
            return ['status' => 'error', 'message' => "Failed to delete record."];
        }
    }

    /**
     * Get list for dropdowns
     */
    public function getList($table, $orderBy = 'id ASC', $columns = '*') {
        $softDeleteTables = ['positions', 'supervision_areas', 'competencies', 'companies', 'departments', 'competency_sub_competencies', 'certifications'];
        $where = in_array($table, $softDeleteTables) ? "WHERE deleted_at IS NULL" : "";
        $q = "SELECT $columns FROM $table $where ORDER BY $orderBy";
        $res = $this->db->query($q);
        $data = [];
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $data[] = $row;
            }
        }
        return $data;
    }

    /**
     * Utility Methods
     */
    private function checkExists($table, $field, $value, $excludeId = null) {
        $softDeleteTables = ['positions', 'supervision_areas', 'competencies'];
        $deletedClause = in_array($table, $softDeleteTables) ? " AND deleted_at IS NULL" : "";
        $query = "SELECT id FROM $table WHERE LOWER(TRIM($field)) = LOWER(TRIM(?))$deletedClause";
        if ($excludeId) {
            $query .= " AND id != " . (int)$excludeId;
        }
        $stmt = $this->db->prepare($query);
        $stmt->bind_param("s", $value);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->num_rows > 0;
    }

    private function getTypes($values) {
        $types = '';
        foreach ($values as $val) {
            if (is_int($val)) $types .= 'i';
            elseif (is_float($val)) $types .= 'd';
            else $types .= 's';
        }
        return $types;
    }

    private function bindStatementParams($stmt, string $types, array $params): void {
        $bind_values = [$types];
        foreach ($params as $index => $value) {
            $bind_values[] = &$params[$index];
        }
        call_user_func_array([$stmt, 'bind_param'], $bind_values);
    }
}
