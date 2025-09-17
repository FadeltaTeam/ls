<?php
class License {
    private $conn;
    
    public function __construct($db) {
        $this->conn = $db;
    }
    
    public function generateLicense($product_id, $user_id, $expiry_date, $max_activations = 1) {
        $license_key = $this->generateUniqueKey();
        
        $query = "INSERT INTO licenses 
                  (license_key, product_id, user_id, expiry_date, max_activations, created_at) 
                  VALUES (:license_key, :product_id, :user_id, :expiry_date, :max_activations, NOW())";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":license_key", $license_key);
        $stmt->bindParam(":product_id", $product_id);
        $stmt->bindParam(":user_id", $user_id);
        $stmt->bindParam(":expiry_date", $expiry_date);
        $stmt->bindParam(":max_activations", $max_activations);
        
        if ($stmt->execute()) {
            return $license_key;
        }
        
        return false;
    }
    
    public function verifyLicense($license_key, $product_id, $domain = null) {
        $query = "SELECT l.*, p.name as product_name 
                  FROM licenses l 
                  JOIN products p ON l.product_id = p.id 
                  WHERE l.license_key = :license_key 
                  AND l.product_id = :product_id 
                  AND l.status = 'active'";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":license_key", $license_key);
        $stmt->bindParam(":product_id", $product_id);
        $stmt->execute();
        
        if ($stmt->rowCount() == 1) {
            $license = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // بررسی انقضا
            if (strtotime($license['expiry_date']) < time()) {
                return ['valid' => false, 'message' => 'License has expired'];
            }
            
            // بررسی تعداد فعال‌سازی‌ها
            $activation_count = $this->getActivationCount($license['id']);
            if ($activation_count >= $license['max_activations']) {
                return ['valid' => false, 'message' => 'Maximum activations exceeded'];
            }
            
            // ثبت فعال‌سازی اگر دامنه ارائه شده
            if ($domain) {
                $this->recordActivation($license['id'], $domain);
            }
            
            return [
                'valid' => true, 
                'message' => 'License is valid',
                'license' => $license
            ];
        }
        
        return ['valid' => false, 'message' => 'Invalid license key'];
    }
    
    private function generateUniqueKey() {
        $prefix = LICENSE_PREFIX;
        $key = uniqid($prefix, true);
        $key = str_replace('.', '', $key);
        $key = substr($key, 0, 16);
        $key = strtoupper($key);
        
        // بررسی یکتا بودن کلید
        $query = "SELECT id FROM licenses WHERE license_key = :key";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":key", $key);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            return $this->generateUniqueKey(); // اگر تکراری بود، دوباره ایجاد کن
        }
        
        return $key;
    }
    
    private function getActivationCount($license_id) {
        $query = "SELECT COUNT(*) as count FROM license_activations WHERE license_id = :license_id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":license_id", $license_id);
        $stmt->execute();
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['count'];
    }
    
    private function recordActivation($license_id, $domain) {
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
        
        $query = "INSERT INTO license_activations (license_id, domain, ip_address, activation_date) 
                  VALUES (:license_id, :domain, :ip_address, NOW())";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":license_id", $license_id);
        $stmt->bindParam(":domain", $domain);
        $stmt->bindParam(":ip_address", $ip_address);
        
        return $stmt->execute();
    }
    
    public function revokeLicense($license_id) {
        $query = "UPDATE licenses SET status = 'revoked', revoked_at = NOW() WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id", $license_id);
        return $stmt->execute();
    }
    
    public function renewLicense($license_id, $extension_days) {
        $query = "SELECT expiry_date FROM licenses WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id", $license_id);
        $stmt->execute();
        
        if ($stmt->rowCount() == 1) {
            $license = $stmt->fetch(PDO::FETCH_ASSOC);
            $new_expiry = date('Y-m-d', strtotime($license['expiry_date'] . " +{$extension_days} days"));
            
            $update_query = "UPDATE licenses SET expiry_date = :new_expiry WHERE id = :id";
            $update_stmt = $this->conn->prepare($update_query);
            $update_stmt->bindParam(":new_expiry", $new_expiry);
            $update_stmt->bindParam(":id", $license_id);
            
            return $update_stmt->execute();
        }
        
        return false;
    }
    
    public function getLicenseDetails($license_id) {
        $query = "SELECT l.*, u.name as user_name, u.email as user_email, p.name as product_name, p.version as product_version
                  FROM licenses l 
                  JOIN users u ON l.user_id = u.id 
                  JOIN products p ON l.product_id = p.id 
                  WHERE l.id = :id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id", $license_id);
        $stmt->execute();
        
        if ($stmt->rowCount() == 1) {
            return $stmt->fetch(PDO::FETCH_ASSOC);
        }
        
        return false;
    }
    
    public function getUserLicenses($user_id) {
        $query = "SELECT l.*, p.name as product_name, p.version as product_version
                  FROM licenses l 
                  JOIN products p ON l.product_id = p.id 
                  WHERE l.user_id = :user_id 
                  ORDER BY l.created_at DESC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":user_id", $user_id);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function getLicenseActivations($license_id) {
        $query = "SELECT domain, ip_address, activation_date 
                  FROM license_activations 
                  WHERE license_id = :license_id 
                  ORDER BY activation_date DESC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":license_id", $license_id);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>