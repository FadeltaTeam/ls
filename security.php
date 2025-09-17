<?php
class Security {
    public static function encrypt($data, $key = ENCRYPTION_KEY) {
        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length('aes-256-cbc'));
        $encrypted = openssl_encrypt($data, 'aes-256-cbc', $key, 0, $iv);
        return base64_encode($encrypted . '::' . $iv);
    }
    
    public static function decrypt($data, $key = ENCRYPTION_KEY) {
        list($encrypted_data, $iv) = explode('::', base64_decode($data), 2);
        return openssl_decrypt($encrypted_data, 'aes-256-cbc', $key, 0, $iv);
    }
    
    public static function generateCSRFToken() {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
    
    public static function verifyCSRFToken($token) {
        if (!empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token)) {
            return true;
        }
        return false;
    }
    
    public static function sanitizeFileName($filename) {
        $filename = preg_replace('/[^a-zA-Z0-9_.-]/', '_', $filename);
        return $filename;
    }
    
    public static function getClientIP() {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            return $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            return $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {
            return $_SERVER['REMOTE_ADDR'];
        }
    }
    
    public static function rateLimit($key, $max_attempts = 5, $time_window = 300) {
        $ip = self::getClientIP();
        $cache_key = "rate_limit_{$key}_{$ip}";
        
        if (!isset($_SESSION[$cache_key])) {
            $_SESSION[$cache_key] = [
                'attempts' => 1,
                'first_attempt' => time()
            ];
        } else {
            $_SESSION[$cache_key]['attempts']++;
            
            if ($_SESSION[$cache_key]['attempts'] > $max_attempts) {
                $elapsed = time() - $_SESSION[$cache_key]['first_attempt'];
                if ($elapsed < $time_window) {
                    return false;
                } else {
                    // reset after time window
                    $_SESSION[$cache_key] = [
                        'attempts' => 1,
                        'first_attempt' => time()
                    ];
                }
            }
        }
        
        return true;
    }
}
?>