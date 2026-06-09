<?php
require_once __DIR__ . '/config.php';

class Database {
    private $conn;
    
    public function __construct() {
        try {
            $this->conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
            
            if ($this->conn->connect_error) {
                throw new Exception("Koneksi database gagal: " . $this->conn->connect_error);
            }
            
            $this->conn->set_charset("utf8mb4");
        } catch (Exception $e) {
            die("Error: " . $e->getMessage());
        }
    }
    
    public function getConnection() {
        return $this->conn;
    }
    
    public function query($sql) {
        try {
            return $this->conn->query($sql);
        } catch (Exception $e) {
            // Return false on error instead of throwing
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
?>

