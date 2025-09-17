<?php
class Database {
    private $host = DB_HOST;
    private $db_name = DB_NAME;
    private $username = DB_USER;
    private $password = DB_PASS;
    private $charset = DB_CHARSET;
    public $conn;
    
    public function getConnection() {
        $this->conn = null;
        
        try {
            $dsn = "mysql:host=" . $this->host . ";dbname=" . $this->db_name . ";charset=" . $this->charset;
            $this->conn = new PDO($dsn, $this->username, $this->password);
            
            // تنظیم attributes
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            $this->conn->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
            $this->conn->setAttribute(PDO::ATTR_STRINGIFY_FETCHES, false);
            
            // تنظیم collation برای پشتیبانی از فارسی
            $this->conn->exec("SET NAMES '" . $this->charset . "' COLLATE '" . $this->charset . "_unicode_ci'");
            $this->conn->exec("SET time_zone = '+03:30'"); // Tehran timezone
            
        } catch(PDOException $exception) {
            error_log("Connection error: " . $exception->getMessage());
            if (DEBUG_MODE) {
                die("Connection error: " . $exception->getMessage());
            } else {
                die("خطای اتصال به پایگاه داده. لطفاً بعداً تلاش کنید.");
            }
        }
        
        return $this->conn;
    }
    
    public function beginTransaction() {
        return $this->conn->beginTransaction();
    }
    
    public function commit() {
        return $this->conn->commit();
    }
    
    public function rollBack() {
        return $this->conn->rollBack();
    }
    
    public function lastInsertId() {
        return $this->conn->lastInsertId();
    }
    
    public function query($sql, $params = []) {
        try {
            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            error_log("Query error: " . $e->getMessage() . " - SQL: " . $sql);
            throw $e;
        }
    }
}
?>