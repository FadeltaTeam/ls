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
$name = isset($_POST['name']) ? sanitize_input($_POST['name']) : null;
$email = isset($_POST['email']) ? sanitize_input($_POST['email']) : null;
$password = isset($_POST['password']) ? $_POST['password'] : null;
$role = isset($_POST['role']) ? sanitize_input($_POST['role']) : null;
$status = isset($_POST['status']) ? intval($_POST['status']) : null;

if (!$user_id || !$name || !$email || !$role) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'پارامترهای ضروری ارائه نشده است']);
    exit();
}

// بررسی وجود ایمیل (به غیر از کاربر فعلی)
$query = "SELECT id FROM users WHERE email = :email AND id != :id";
$stmt = $db->prepare($query);
$stmt->bindParam(":email", $email);
$stmt->bindParam(":id", $user_id);
$stmt->execute();

if ($stmt->rowCount() > 0) {
    echo json_encode(['success' => false, 'message' => 'ایمیل قبلاً توسط کاربر دیگری ثبت شده است']);
    exit();
}

try {
    // اگر رمز عبور ارائه شده، آن را به روز کن
    if (!empty($password)) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $query = "UPDATE users 
                  SET name = :name, email = :email, password = :password, role = :role, status = :status 
                  WHERE id = :id";
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(":password", $hashed_password);
    } else {
        $query = "UPDATE users 
                  SET name = :name, email = :email, role = :role, status = :status 
                  WHERE id = :id";
        
        $stmt = $db->prepare($query);
    }
    
    $stmt->bindParam(":name", $name);
    $stmt->bindParam(":email", $email);
    $stmt->bindParam(":role", $role);
    $stmt->bindParam(":status", $status);
    $stmt->bindParam(":id", $user_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'اطلاعات کاربر با موفقیت به روز شد']);
    } else {
        echo json_encode(['success' => false, 'message' => 'خطا در به روزرسانی کاربر']);
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'خطا در به روزرسانی کاربر']);
}
?>