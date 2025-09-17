<?php
require_once '../includes/config.php';
require_once '../includes/database.php';
require_once '../includes/license_functions.php';

header('Content-Type: application/json');

// دریافت پارامترها
$license_key = isset($_POST['license_key']) ? sanitize_input($_POST['license_key']) : null;
$product_id = isset($_POST['product_id']) ? sanitize_input($_POST['product_id']) : null;
$domain = isset($_POST['domain']) ? sanitize_input($_POST['domain']) : null;

if (!$license_key || !$product_id) {
    echo json_encode(['valid' => false, 'message' => 'پارامترهای ضروری ارائه نشده است']);
    exit();
}

$database = new Database();
$db = $database->getConnection();
$licenseSystem = new License($db);

$result = $licenseSystem->verifyLicense($license_key, $product_id, $domain);

echo json_encode($result);
?>