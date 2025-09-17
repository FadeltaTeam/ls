<?php
require_once '../includes/config.php';
require_once '../includes/database.php';
require_once '../includes/auth.php';
require_once '../includes/helpers.php';

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

try {
    // علامت‌گذاری همه اعلان‌ها به عنوان خوانده شده
    $result = markAllNotificationsAsRead($db, $user_id);
    
    if ($result) {
        // ثبت فعالیت
        logActivity($db, $user_id, 'all_notifications_read', 'همه اعلان‌ها خوانده شدند');
        
        echo json_encode(['success' => true, 'message' => 'همه اعلان‌ها با موفقیت خوانده شدند']);
    } else {
        echo json_encode(['success' => false, 'message' => 'خطا در به روزرسانی اعلان‌ها']);
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'خطای سرور: ' . $e->getMessage()]);
}
?>