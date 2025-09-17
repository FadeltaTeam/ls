<?php
// تنظیمات پایه
define('SITE_NAME', 'سیستم لایسنس پیشرفته');
define('BASE_URL', 'http://localhost/license_system');
define('ROOT_PATH', __DIR__ . '/../');

// تنظیمات پایگاه داده
define('DB_HOST', 'localhost');
define('DB_NAME', 'license_system');
define('DB_USER', 'root');
define('DB_PASS', 'password');
define('DB_CHARSET', 'utf8mb4');

// تنظیمات امنیتی
define('ENCRYPTION_KEY', 'your-secure-encryption-key-here-change-in-production');
define('LICENSE_PREFIX', 'LS_');
define('CSRF_TOKEN_EXPIRE', 1800); // 30 minutes
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_TIME', 900); // 15 minutes

// تنظیمات لایسنس
define('DEFAULT_LICENSE_DURATION', 365); // days
define('MAX_ACTIVATIONS', 3);
define('LICENSE_CHECK_INTERVAL', 7); // days

// تنظیمات ایمیل
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_USER', 'your-email@gmail.com');
define('SMTP_PASS', 'your-app-password');
define('SMTP_PORT', 587);
define('SMTP_SECURE', 'tls');
define('EMAIL_FROM', 'noreply@yourdomain.com');
define('EMAIL_FROM_NAME', 'سیستم لایسنس');

// حالت توسعه
define('DEBUG_MODE', true);
define('LOG_ERRORS', true);
define('ERROR_LOG_FILE', ROOT_PATH . 'logs/error.log');

// تنظیمات آپلود
define('UPLOAD_MAX_SIZE', 10485760); // 10MB
define('UPLOAD_PATH', ROOT_PATH . 'uploads/');
define('ALLOWED_FILE_TYPES', ['pdf', 'doc', 'docx', 'txt', 'zip']);

// توابع کمکی
function sanitize_input($data) {
    if (is_array($data)) {
        return array_map('sanitize_input', $data);
    }
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}

function validate_email($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

function validate_url($url) {
    return filter_var($url, FILTER_VALIDATE_URL);
}

function validate_ip($ip) {
    return filter_var($ip, FILTER_VALIDATE_IP);
}

// مدیریت خطاها
if (DEBUG_MODE) {
    ini_set('display_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    error_reporting(0);
}

if (LOG_ERRORS) {
    ini_set('log_errors', 1);
    ini_set('error_log', ERROR_LOG_FILE);
}

// تنظیمات زمان
date_default_timezone_set('Asia/Tehran');
setlocale(LC_ALL, 'fa_IR.UTF-8');

// شروع session
if (session_status() == PHP_SESSION_NONE) {
    session_start([
        'cookie_lifetime' => 86400, // 1 day
        'cookie_secure' => isset($_SERVER['HTTPS']),
        'cookie_httponly' => true,
        'use_strict_mode' => true
    ]);
}

// جلوگیری از حملات XSS
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('X-XSS-Protection: 1; mode=block');

// جلوگیری از caching برای صفحات حساس
$current_page = basename($_SERVER['PHP_SELF']);
$sensitive_pages = ['login.php', 'dashboard.php', 'admin/'];
foreach ($sensitive_pages as $page) {
    if (strpos($_SERVER['PHP_SELF'], $page) !== false) {
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');
        break;
    }
}

// بررسی نصب سیستم
function check_installation() {
    if (!file_exists(ROOT_PATH . '.installed')) {
        if (basename($_SERVER['PHP_SELF']) != 'install.php') {
            header('Location: install.php');
            exit();
        }
    }
}

// بررسی آپدیت‌ها
function check_updates() {
    // این تابع می‌تواند نسخه فعلی را با نسخه آخر مقایسه کند
    return true;
}

// auto-loader برای کلاس‌ها
spl_autoload_register(function ($class_name) {
    $class_file = __DIR__ . '/' . $class_name . '.php';
    if (file_exists($class_file)) {
        require_once $class_file;
    }
});

// بررسی نصب در اولین بار
check_installation();
?>