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
                // Try fallback to local XAMPP root credentials
                $this->conn = @new mysqli('127.0.0.1', 'root', '', 'mining_appointment');
                if (!$this->conn || $this->conn->connect_error) {
                    $this->conn = @new mysqli('127.0.0.1', 'root', '', 'u136581265_Toka_STELA');
                }
                if (!$this->conn || $this->conn->connect_error) {
                    throw new Exception("Koneksi database gagal: " . ($this->conn ? $this->conn->connect_error : 'Unknown DB error'));
                }
            }
            
            $this->conn->set_charset("utf8mb4");
        } catch (\Throwable $e) {
            // Try emergency fallback to root
            try {
                $this->conn = @new mysqli('127.0.0.1', 'root', '', 'mining_appointment');
                if (!$this->conn->connect_error) {
                    $this->conn->set_charset("utf8mb4");
                    return;
                }
            } catch (\Throwable $ex) {}
            die("Error: " . $e->getMessage());
        }
    }
    
    public function getConnection() {
        return $this->conn;
    }
    
    public function query($sql, $params = [], $types = "") {
        try {
            if (!empty($params)) {
                $stmt = $this->conn->prepare($sql);
                if (!$stmt) {
                    error_log("Prepare failed: " . $this->conn->error);
                    return false;
                }
                
                if (empty($types)) {
                    $types = str_repeat('s', count($params));
                }
                
                // Bind parameters by reference to avoid PHP Warnings with spread operator
                $bindParams = [$types];
                foreach ($params as $key => $val) {
                    $bindParams[] = &$params[$key];
                }
                call_user_func_array([$stmt, 'bind_param'], $bindParams);
                
                if (!$stmt->execute()) {
                    error_log("Execute failed: " . $stmt->error);
                    return false;
                }
                
                $result = $stmt->get_result();
                // If the query doesn't produce a result set (e.g., INSERT/UPDATE), get_result returns false
                // but errno will be 0 if execute was successful.
                if ($result === false && $stmt->errno === 0) {
                    return true;
                }
                return $result;
            } else {
                return $this->conn->query($sql);
            }
        } catch (Exception $e) {
            error_log("Database Exception: " . $e->getMessage());
            return false;
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
    
    public function lastInsertId() {
        return $this->conn->insert_id;
    }
    
    public function __destruct() {
        if ($this->conn) {
            $this->conn->close();
        }
    }
}
