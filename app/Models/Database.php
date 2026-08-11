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
        if (!empty($params)) {
            $stmt = $this->conn->prepare($sql);
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $this->conn->error . " | SQL: " . $sql);
            }
            
            if (empty($types)) {
                $types = str_repeat('s', count($params));
            }
            
            $bindParams = [$types];
            foreach ($params as $key => $val) {
                $bindParams[] = &$params[$key];
            }
            if (!call_user_func_array([$stmt, 'bind_param'], $bindParams)) {
                throw new Exception("Bind param failed: " . $stmt->error);
            }
            
            if (!$stmt->execute()) {
                throw new Exception("Execute failed: " . $stmt->error . " | SQL: " . $sql);
            }
            
            $result = $stmt->get_result();
            if ($result === false && $stmt->errno === 0) {
                return true;
            }
            if ($result === false) {
                 throw new Exception("Get result failed: " . $stmt->error);
            }
            return $result;
        } else {
            $result = $this->conn->query($sql);
            if ($result === false) {
                throw new Exception("Query failed: " . $this->conn->error . " | SQL: " . $sql);
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
    
    public function lastInsertId() {
        return $this->conn->insert_id;
    }
    
    public function __destruct() {
        if ($this->conn) {
            $this->conn->close();
        }
    }
}
