<?php
/**
 * Database Model / MySQLi Wrapper
 */

require_once dirname(__DIR__, 2) . '/config/app.php';

class Database {
    private $conn;
    
    public function __construct() {
        @mysqli_report(MYSQLI_REPORT_OFF);
        try {
            $this->conn = @new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
            
            if (!$this->conn || $this->conn->connect_error) {
                // [SECURITY] Tidak ada fallback credentials — fail fast
                throw new Exception("Koneksi database gagal. Periksa konfigurasi .env.");
            }
            
            $this->conn->set_charset("utf8mb4");
            // Nonaktifkan ONLY_FULL_GROUP_BY agar query kompleks yang tidak menggunakan aggregate pada semua kolom non-grouped tetap berjalan (kompatibilitas dengan versi MySQL yang lama/hosting)
            $this->conn->query("SET SESSION sql_mode=(SELECT REPLACE(@@sql_mode,'ONLY_FULL_GROUP_BY',''))");
        } catch (\Throwable $e) {
            // [SECURITY] Tidak mengekspos detail koneksi di error message
            throw new Exception("Database Connection Error. Periksa konfigurasi database pada file .env.");
        }
    }
    
    public function getConnection() {
        return $this->conn;
    }
    
    public function query($sql, $params = [], $types = "") {
        if (!empty($params)) {
            $stmt = $this->conn->prepare($sql);
            if (!$stmt) {
                // [SECURITY] Jangan ekspos SQL query di exception message
                error_log("Prepare failed: " . $this->conn->error . " | SQL: " . $sql);
                throw new Exception("Database query preparation failed.");
            }
            
            if (empty($types)) {
                $types = str_repeat('s', count($params));
            }
            
            $bindParams = [$types];
            foreach ($params as $key => $val) {
                $bindParams[] = &$params[$key];
            }
            if (!call_user_func_array([$stmt, 'bind_param'], $bindParams)) {
                error_log("Bind param failed: " . $stmt->error);
                throw new Exception("Database parameter binding failed.");
            }
            
            if (!$stmt->execute()) {
                error_log("Execute failed: " . $stmt->error . " | SQL: " . $sql);
                throw new Exception("Database query execution failed.");
            }
            
            $result = $stmt->get_result();
            if ($result === false && $stmt->errno === 0) {
                return true;
            }
            if ($result === false) {
                error_log("Get result failed: " . $stmt->error);
                throw new Exception("Database result retrieval failed.");
            }
            return $result;
        } else {
            $result = $this->conn->query($sql);
            if ($result === false) {
                error_log("Query failed: " . $this->conn->error . " | SQL: " . $sql);
                throw new Exception("Database query failed.");
            }
            return $result;
        }
    }
    
    public function prepare($sql) {
        try {
            return $this->conn->prepare($sql);
        } catch (Exception $e) {
            return false;
        }
    }
    
    public function escapeString($string) {
        return $this->conn->real_escape_string($string);
    }
    
    /**
     * Alias for escapeString() — backwards compatibility
     */
    public function escape($string) {
        return $this->escapeString($string);
    }
    
    public function lastInsertId() {
        return $this->conn->insert_id;
    }
    
    public function insert($table, $data) {
        if (empty($data)) return false;
        $fields = array_keys($data);
        $placeholders = array_fill(0, count($fields), '?');
        $sql = "INSERT INTO $table (" . implode(', ', $fields) . ") VALUES (" . implode(', ', $placeholders) . ")";
        $params = array_values($data);
        return $this->query($sql, $params);
    }
    
    public function update($table, $data, $where, $whereParams = []) {
        if (empty($data)) return false;
        $set = [];
        foreach (array_keys($data) as $key) {
            $set[] = "$key = ?";
        }
        $sql = "UPDATE $table SET " . implode(', ', $set) . " WHERE $where";
        $params = array_merge(array_values($data), $whereParams);
        return $this->query($sql, $params);
    }
    
    protected $softDeleteTables = ['appointments', 'employees', 'users', 'positions', 'supervision_areas', 'competencies', 'certifications', 'companies', 'departments', 'competency_sub_competencies'];

    public function delete($table, $where, $whereParams = []) {
        if (in_array($table, $this->softDeleteTables)) {
            // [SECURITY] Gunakan parameterized query untuk deleted_by
            $deleted_by = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : null;
            $sql = "UPDATE $table SET deleted_at = CURRENT_TIMESTAMP, deleted_by = ? WHERE $where";
            $allParams = array_merge([$deleted_by], $whereParams);
            $types = 'i' . str_repeat('s', count($whereParams));
            return $this->query($sql, $allParams, $types);
        } else {
            $sql = "DELETE FROM $table WHERE $where";
            return $this->query($sql, $whereParams);
        }
    }
    
    public function beginTransaction() {
        return $this->conn->begin_transaction();
    }
    
    public function commit() {
        return $this->conn->commit();
    }
    
    public function rollback() {
        return $this->conn->rollback();
    }
    
    public function __destruct() {
        if ($this->conn) {
            $this->conn->close();
        }
    }
}
