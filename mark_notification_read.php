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

// دریافت شناسه اعلان
$notification_id = isset($_POST['id']) ? intval($_POST['id']) : null;

if (!$notification_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'شناسه اعلان ارائه نشده است']);
    exit();
}

// بررسی مالکیت اعلان
$user_id = $_SESSION['user_id'];
$query = "SELECT id FROM notifications WHERE id = :id AND user_id = :user_id";
$stmt = $db->prepare($query);
$stmt->bindParam(":id", $notification_id);
$stmt->bindParam(":user_id", $user_id);
$stmt->execute();

if ($stmt->rowCount() == 0) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'اعلان یافت نشد یا دسترسی ندارید']);
    exit();
}

try {
    // علامت‌گذاری اعلان به عنوان خوانده شده
    $query = "UPDATE notifications SET is_read = 1, read_at = NOW() WHERE id = :id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(":id", $notification_id);
    
    if ($stmt->execute()) {
        // ثبت فعالیت
        logActivity($db, $user_id, 'notification_read', 'اعلان خوانده شد - ID: ' . $notification_id);
        
        echo json_encode(['success' => true, 'message' => 'اعلان با موفقیت خوانده شد']);
    } else {
        echo json_encode(['success' => false, 'message' => 'خطا در به روزرسانی اعلان']);
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'خطای سرور: ' . $e->getMessage()]);
}
?>