<?php
require_once '../includes/config.php';
require_once '../includes/database.php';
require_once '../includes/auth.php';

header('Content-Type: application/json');

$database = new Database();
$db = $database->getConnection();
$auth = new Auth($db);

if (!$auth->isLoggedIn() || !$auth->hasPermission('admin')) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'دسترسی غیرمجاز']);
    exit();
}

$log_id = isset($_GET['id']) ? intval($_GET['id']) : null;

if (!$log_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'شناسه لاگ ارائه نشده است']);
    exit();
}

try {
    $query = "SELECT al.*, u.name as user_name, u.email as user_email
              FROM activity_logs al
              LEFT JOIN users u ON al.user_id = u.id
              WHERE al.id = :id";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(":id", $log_id);
    $stmt->execute();
    
    if ($stmt->rowCount() === 0) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'لاگ یافت نشد']);
        exit();
    }
    
    $log = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'log' => $log]);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'خطای سرور: ' . $e->getMessage()]);
}
?>