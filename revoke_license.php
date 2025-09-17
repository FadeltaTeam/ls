<?php
require_once '../includes/config.php';
require_once '../includes/database.php';
require_once '../includes/auth.php';
require_once '../includes/license_functions.php';

header('Content-Type: application/json');

// بررسی احراز هویت
$database = new Database();
$db = $database->getConnection();
$auth = new Auth($db);

if (!$auth->isLoggedIn() || !$auth->hasPermission('admin')) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'دسترسی غیرمجاز']);
    exit();
}

// دریافت پارامترها
$license_id = isset($_POST['license_id']) ? intval($_POST['license_id']) : null;

if (!$license_id) {
    echo json_encode(['success' => false, 'message' => 'شناسه لایسنس ارائه نشده است']);
    exit();
}

$licenseSystem = new License($db);
$result = $licenseSystem->revokeLicense($license_id);

if ($result) {
    echo json_encode(['success' => true, 'message' => 'لایسنس با موفقیت لغو شد']);
} else {
    echo json_encode(['success' => false, 'message' => 'خطا در لغو لایسنس']);
}
?>