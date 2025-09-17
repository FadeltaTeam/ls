<?php
require_once '../includes/config.php';
require_once '../includes/database.php';
require_once '../includes/auth.php';
require_once '../includes/license_functions.php';
require_once '../includes/helpers.php';

header('Content-Type: application/json');

$database = new Database();
$db = $database->getConnection();
$auth = new Auth($db);

// بررسی احراز هویت
if (!$auth->isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'دسترسی غیرمجاز. لطفاً وارد سیستم شوید.']);
    exit();
}

// بررسی مجوزهای کاربر
$user_role = $_SESSION['user_role'];
$user_id = $_SESSION['user_id'];

// فقط ادمین‌ها و مدیران می‌توانند لایسنس ایجاد کنند
if ($user_role !== 'admin' && $user_role !== 'manager') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'شما مجوز ایجاد لایسنس را ندارید.']);
    exit();
}

// دریافت و اعتبارسنجی پارامترها
$product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : null;
$target_user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : null;
$expiry_days = isset($_POST['expiry_days']) ? intval($_POST['expiry_days']) : DEFAULT_LICENSE_DURATION;
$max_activations = isset($_POST['max_activations']) ? intval($_POST['max_activations']) : MAX_ACTIVATIONS;
$notes = isset($_POST['notes']) ? sanitize_input($_POST['notes']) : '';

// اعتبارسنجی پارامترهای ضروری
if (!$product_id || !$target_user_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'پارامترهای ضروری (product_id و user_id) ارائه نشده است.']);
    exit();
}

// اعتبارسنجی مقادیر
if ($expiry_days < 1 || $expiry_days > 3650) { // حداکثر 10 سال
    $expiry_days = DEFAULT_LICENSE_DURATION;
}

if ($max_activations < 1 || $max_activations > 100) {
    $max_activations = MAX_ACTIVATIONS;
}

try {
    // بررسی وجود محصول
    $product_query = "SELECT id, name, version FROM products WHERE id = :product_id AND status = 1";
    $product_stmt = $db->prepare($product_query);
    $product_stmt->bindParam(":product_id", $product_id);
    $product_stmt->execute();
    
    if ($product_stmt->rowCount() === 0) {
        echo json_encode(['success' => false, 'message' => 'محصول یافت نشد یا غیرفعال است.']);
        exit();
    }
    
    $product = $product_stmt->fetch(PDO::FETCH_ASSOC);
    
    // بررسی وجود کاربر هدف
    $user_query = "SELECT id, name, email FROM users WHERE id = :user_id AND status = 1";
    $user_stmt = $db->prepare($user_query);
    $user_stmt->bindParam(":user_id", $target_user_id);
    $user_stmt->execute();
    
    if ($user_stmt->rowCount() === 0) {
        echo json_encode(['success' => false, 'message' => 'کاربر یافت نشد یا غیرفعال است.']);
        exit();
    }
    
    $target_user = $user_stmt->fetch(PDO::FETCH_ASSOC);
    
    // محاسبه تاریخ انقضا
    $expiry_date = date('Y-m-d', strtotime("+{$expiry_days} days"));
    
    // ایجاد لایسنس
    $licenseSystem = new License($db);
    $license_key = $licenseSystem->generateLicense($product_id, $target_user_id, $expiry_date, $max_activations);
    
    if ($license_key) {
        // ثبت اطلاعات اضافی در جدول licenses (اگر فیلد notes وجود دارد)
        $update_query = "UPDATE licenses SET notes = :notes WHERE license_key = :license_key";
        $update_stmt = $db->prepare($update_query);
        $update_stmt->bindParam(":notes", $notes);
        $update_stmt->bindParam(":license_key", $license_key);
        $update_stmt->execute();
        
        // ثبت فعالیت در سیستم
        $activity_details = "ایجاد لایسنس جدید - محصول: {$product['name']} - کاربر: {$target_user['name']} - کلید: {$license_key}";
        logActivity($db, $user_id, 'license_generated', $activity_details);
        
        // ایجاد اعلان برای کاربر
        $notification_title = "لایسنس جدید ایجاد شد";
        $notification_message = "یک لایسنس جدید برای محصول «{$product['name']}» برای شما ایجاد شد. تاریخ انقضا: " . toPersianDate($expiry_date);
        createNotification($db, $target_user_id, $notification_title, $notification_message, 'success');
        
        // ایجاد اعلان برای ادمین‌ها (اگر ایجادکننده ادمین نباشد)
        if ($user_role !== 'admin') {
            $admin_query = "SELECT id FROM users WHERE role = 'admin' AND status = 1";
            $admin_stmt = $db->prepare($admin_query);
            $admin_stmt->execute();
            $admins = $admin_stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($admins as $admin) {
                $admin_title = "لایسنس جدید توسط مدیر";
                $admin_message = "مدیر «{$_SESSION['user_name']}» یک لایسنس جدید برای محصول «{$product['name']}» ایجاد کرد";
                createNotification($db, $admin['id'], $admin_title, $admin_message, 'info');
            }
        }
        
        // ارسال ایمیل به کاربر (اختیاری)
        if (defined('SMTP_HOST') && !empty(SMTP_HOST)) {
            $email_subject = "لایسنس جدید - {$product['name']}";
            $email_body = "
                <h2>لایسنس جدید</h2>
                <p>یک لایسنس جدید برای شما ایجاد شده است:</p>
                <table>
                    <tr><td><strong>محصول:</strong></td><td>{$product['name']}</td></tr>
                    <tr><td><strong>نسخه:</strong></td><td>{$product['version']}</td></tr>
                    <tr><td><strong>کلید لایسنس:</strong></td><td>{$license_key}</td></tr>
                    <tr><td><strong>تاریخ انقضا:</strong></td><td>" . toPersianDate($expiry_date) . "</td></tr>
                    <tr><td><strong>حداکثر فعال‌سازی:</strong></td><td>{$max_activations}</td></tr>
                </table>
                <p>می‌توانید لایسنس‌های خود را از <a href=\"" . BASE_URL . "/dashboard.php\">داشبورد کاربری</a> مشاهده کنید.</p>
            ";
            
            sendEmail($target_user['email'], $email_subject, $email_body);
        }
        
        // پاسخ موفقیت‌آمیز
        echo json_encode([
            'success' => true, 
            'message' => 'لایسنس با موفقیت ایجاد شد.',
            'license_key' => $license_key,
            'expiry_date' => $expiry_date,
            'persian_expiry_date' => toPersianDate($expiry_date),
            'max_activations' => $max_activations,
            'product_name' => $product['name'],
            'user_name' => $target_user['name']
        ]);
        
    } else {
        echo json_encode(['success' => false, 'message' => 'خطا در ایجاد لایسنس. لطفاً دوباره尝试 کنید.']);
    }
    
} catch (PDOException $e) {
    http_response_code(500);
    error_log("License Generation Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'خطای سرور در ایجاد لایسنس.']);
}
?>