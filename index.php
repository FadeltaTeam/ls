<?php
require_once 'includes/config.php';
require_once 'includes/database.php';
require_once 'includes/auth.php';

$database = new Database();
$db = $database->getConnection();
$auth = new Auth($db);

// بررسی اینکه کاربر وارد سیستم شده یا نه
if ($auth->isLoggedIn()) {
    // اگر کاربر وارد شده، به داشبورد redirect شود
    header('Location: dashboard.php');
    exit();
} else {
    // اگر کاربر وارد نشده، به صفحه login redirect شود
    header('Location: login.php');
    exit();
}

// اگر redirect کار نکرد، صفحه خالی نمایش داده شود
exit();
?>