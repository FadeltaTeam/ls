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
$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 10;
$offset = isset($_GET['offset']) ? intval($_GET['offset']) : 0;
$unread_only = isset($_GET['unread_only']) ? filter_var($_GET['unread_only'], FILTER_VALIDATE_BOOLEAN) : false;

// اعتبارسنجی پارامترها
if ($limit < 1 || $limit > 50) {
    $limit = 10;
}
if ($offset < 0) {
    $offset = 0;
}

try {
    // ساخت شرط برای اعلان‌های خوانده نشده
    $where_condition = "WHERE user_id = :user_id";
    if ($unread_only) {
        $where_condition .= " AND is_read = 0";
    }
    
    // دریافت اعلان‌ها
    $query = "SELECT id, title, message, type, is_read, created_at 
              FROM notifications 
              $where_condition 
              ORDER BY created_at DESC 
              LIMIT :limit OFFSET :offset";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(":user_id", $user_id);
    $stmt->bindParam(":limit", $limit, PDO::PARAM_INT);
    $stmt->bindParam(":offset", $offset, PDO::PARAM_INT);
    $stmt->execute();
    
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // دریافت تعداد کل اعلان‌های خوانده نشده
    $unread_count_query = "SELECT COUNT(*) as count FROM notifications WHERE user_id = :user_id AND is_read = 0";
    $unread_count_stmt = $db->prepare($unread_count_query);
    $unread_count_stmt->bindParam(":user_id", $user_id);
    $unread_count_stmt->execute();
    $unread_count = $unread_count_stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // فرمت‌دهی تاریخ‌ها برای نمایش بهتر
    foreach ($notifications as &$notification) {
        $notification['time_ago'] = timeAgo($notification['created_at']);
        $notification['formatted_date'] = toPersianDate($notification['created_at'], 'Y/m/d H:i');
    }
    
    echo json_encode([
        'success' => true, 
        'notifications' => $notifications,
        'unread_count' => (int)$unread_count,
        'total' => count($notifications)
    ]);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'خطای سرور: ' . $e->getMessage()]);
}
?>