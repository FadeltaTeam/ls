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

$license_id = isset($_GET['id']) ? intval($_GET['id']) : null;

if (!$license_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'شناسه لایسنس ارائه نشده است']);
    exit();
}

try {
    // دریافت اطلاعات لایسنس
    $query = "SELECT l.*, u.name as user_name, p.name as product_name 
              FROM licenses l 
              JOIN users u ON l.user_id = u.id 
              JOIN products p ON l.product_id = p.id 
              WHERE l.id = :id";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(":id", $license_id);
    $stmt->execute();
    
    if ($stmt->rowCount() == 0) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'لایسنس یافت نشد']);
        exit();
    }
    
    $license = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // دریافت اطلاعات فعال‌سازی‌ها
    $query = "SELECT domain, activation_date FROM license_activations WHERE license_id = :id ORDER BY activation_date DESC";
    $stmt = $db->prepare($query);
    $stmt->bindParam(":id", $license_id);
    $stmt->execute();
    
    $license['activations'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'license' => $license]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'خطا در دریافت اطلاعات لایسنس']);
}
?>