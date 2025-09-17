<?php
require_once '../includes/config.php';
require_once '../includes/database.php';
require_once '../includes/auth.php';

header('Content-Type: application/json');

$database = new Database();
$db = $database->getConnection();
$auth = new Auth($db);

if (!$auth->isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'دسترسی غیرمجاز']);
    exit();
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'];

try {
    $stats = [];
    
    if ($user_role === 'admin') {
        // آمار برای ادمین
        $query = "SELECT COUNT(*) as count FROM licenses";
        $stmt = $db->prepare($query);
        $stmt->execute();
        $stats['totalLicenses'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        $query = "SELECT COUNT(*) as count FROM licenses WHERE status = 'active'";
        $stmt = $db->prepare($query);
        $stmt->execute();
        $stats['activeLicenses'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        $query = "SELECT COUNT(*) as count FROM licenses WHERE status = 'active' AND expiry_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)";
        $stmt = $db->prepare($query);
        $stmt->execute();
        $stats['expiringSoon'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        $query = "SELECT COUNT(*) as count FROM users";
        $stmt = $db->prepare($query);
        $stmt->execute();
        $stats['totalUsers'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        
    } else {
        // آمار برای کاربر عادی
        $query = "SELECT COUNT(*) as count FROM licenses WHERE user_id = :user_id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(":user_id", $user_id);
        $stmt->execute();
        $stats['totalLicenses'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        $query = "SELECT COUNT(*) as count FROM licenses WHERE user_id = :user_id AND status = 'active'";
        $stmt = $db->prepare($query);
        $stmt->bindParam(":user_id", $user_id);
        $stmt->execute();
        $stats['activeLicenses'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        $query = "SELECT COUNT(*) as count FROM licenses WHERE user_id = :user_id AND status = 'active' AND expiry_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)";
        $stmt = $db->prepare($query);
        $stmt->bindParam(":user_id", $user_id);
        $stmt->execute();
        $stats['expiringSoon'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    }
    
    // فعالیت‌های اخیر
    $query = "SELECT la.domain, la.activation_date, p.name as product_name 
              FROM license_activations la 
              JOIN licenses l ON la.license_id = l.id 
              JOIN products p ON l.product_id = p.id 
              WHERE l.user_id = :user_id 
              ORDER BY la.activation_date DESC 
              LIMIT 5";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(":user_id", $user_id);
    $stmt->execute();
    
    $stats['recentActivity'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'stats' => $stats]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'خطا در دریافت آمار']);
}
?>