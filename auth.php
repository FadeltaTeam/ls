<?php
session_start();

class Auth {
    private $conn;
    
    public function __construct($db) {
        $this->conn = $db;
    }
    
    public function login($email, $password) {
        $query = "SELECT id, name, email, password, role FROM users WHERE email = :email AND status = 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":email", $email);
        $stmt->execute();
        
        if ($stmt->rowCount() == 1) {
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (password_verify($password, $row['password'])) {
                $_SESSION['user_id'] = $row['id'];
                $_SESSION['user_name'] = $row['name'];
                $_SESSION['user_email'] = $row['email'];
                $_SESSION['user_role'] = $row['role'];
                $_SESSION['logged_in'] = true;
                
                // به روز رسانی آخرین لاگین
                $this->updateLastLogin($row['id']);
                
                return true;
            }
        }
        return false;
    }
    
    public function isLoggedIn() {
        return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
    }
    
    public function logout() {
        $_SESSION = array();
        session_destroy();
    }
    
    private function updateLastLogin($user_id) {
        $query = "UPDATE users SET last_login = NOW() WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id", $user_id);
        $stmt->execute();
    }
    
    public function hasPermission($required_role) {
        if (!isset($_SESSION['user_role'])) {
            return false;
        }
        
        $user_role = $_SESSION['user_role'];
        
        // نقش‌های کاربری: admin, manager, user
        $roles = [
            'admin' => 3,
            'manager' => 2,
            'user' => 1
        ];
        
        return isset($roles[$user_role]) && $roles[$user_role] >= $roles[$required_role];
    }
}
?>