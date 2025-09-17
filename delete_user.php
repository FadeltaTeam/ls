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

$user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : null;

if (!$user_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'شناسه کاربر ارائه نشده است']);
    exit();
}

// بررسی وجود لایسنس‌های فعال برای کاربر
$query = "SELECT COUNT(*) as count FROM licenses WHERE user_id = :user_id AND status = 'active'";
$stmt = $db->prepare($query);
$stmt->bindParam(":user_id", $user_id);
$stmt->execute();

$result = $stmt->fetch(PDO::FETCH_ASSOC);

if ($result['count'] > 0) {
    echo json_encode(['success' => false, 'message' => 'امکان حذف کاربر دارای لایسنس فعال وجود ندارد']);
    exit();
}

try {
    $query = "DELETE FROM users WHERE id = :id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(":id", $user_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'کاربر با موفقیت حذف شد']);
    } else {
        echo json_encode(['success' => false, 'message' => 'خطا در حذف کاربر']);
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'خطا در حذف کاربر']);
}
?>