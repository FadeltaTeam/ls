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
$product_id = isset($_POST['product']) ? intval($_POST['product']) : null;
$purpose = isset($_POST['purpose']) ? sanitize_input($_POST['purpose']) : null;
$duration = isset($_POST['duration']) ? intval($_POST['duration']) : 12;

if (!$product_id || !$purpose) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'پارامترهای ضروری ارائه نشده است']);
    exit();
}

try {
    $query = "INSERT INTO license_requests (user_id, product_id, purpose, duration, status, created_at) 
              VALUES (:user_id, :product_id, :purpose, :duration, 'pending', NOW())";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(":user_id", $user_id);
    $stmt->bindParam(":product_id", $product_id);
    $stmt->bindParam(":purpose", $purpose);
    $stmt->bindParam(":duration", $duration);
    
    if ($stmt->execute()) {
        // ارسال ایمیل به ادمین برای اطلاع از درخواست جدید
        $adminQuery = "SELECT email FROM users WHERE role = 'admin' AND status = 1";
        $adminStmt = $db->prepare($adminQuery);
        $adminStmt->execute();
        
        $admins = $adminStmt->fetchAll(PDO::FETCH_ASSOC);
        $userEmail = $_SESSION['user_email'];
        $userName = $_SESSION['user_name'];
        
        foreach ($admins as $admin) {
            $subject = "درخواست لایسنس جدید از $userName";
            $message = "
                <h2>درخواست لایسنس جدید</h2>
                <p>کاربر $userName ($userEmail) یک درخواست لایسنس جدید ثبت کرده است.</p>
                <p><strong>هدف استفاده:</strong> $purpose</p>
                <p><strong>مدت درخواستی:</strong> $duration ماه</p>
                <p>لطفاً به پنل مدیریت مراجعه کرده و این درخواست را بررسی کنید.</p>
            ";
            
            sendEmail($admin['email'], $subject, $message);
        }
        
        echo json_encode(['success' => true, 'message' => 'درخواست شما با موفقیت ثبت شد']);
    } else {
        echo json_encode(['success' => false, 'message' => 'خطا در ثبت درخواست']);
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'خطا در ثبت درخواست']);
}
?>