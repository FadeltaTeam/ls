<?php
require_once 'includes/config.php';
require_once 'includes/database.php';
require_once 'includes/auth.php';

$database = new Database();
$db = $database->getConnection();
$auth = new Auth($db);

$error = '';
$success = '';

// اگر کاربر از قبل وارد شده، به داشبورد برود
if ($auth->isLoggedIn()) {
    header('Location: dashboard.php');
    exit();
}

// پردازش فرم ورود
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = sanitize_input($_POST['email']);
    $password = $_POST['password'];
    $remember = isset($_POST['remember']) ? true : false;
    
    // اعتبارسنجی ورودی‌ها
    if (empty($email) || empty($password)) {
        $error = 'لطفاً ایمیل و رمز عبور را وارد کنید';
    } elseif (!validate_email($email)) {
        $error = 'فرمت ایمیل نامعتبر است';
    } else {
        if ($auth->login($email, $password, $remember)) {
            // ثبت فعالیت
            logActivity($db, $_SESSION['user_id'], 'user_login', 'ورود به سیستم');
            
            // redirect به صفحه مقصد یا داشبورد
            $redirect_url = isset($_SESSION['redirect_url']) ? $_SESSION['redirect_url'] : 'dashboard.php';
            unset($_SESSION['redirect_url']);
            header('Location: ' . $redirect_url);
            exit();
        } else {
            $error = 'ایمیل یا رمز عبور اشتباه است';
        }
    }
}

// اگر کاربر از صفحه دیگری به login آمده، URL را ذخیره کنیم
if (!isset($_SESSION['redirect_url']) && isset($_SERVER['HTTP_REFERER'])) {
    $referer = parse_url($_SERVER['HTTP_REFERER']);
    if ($referer['host'] == $_SERVER['HTTP_HOST'] && $referer['path'] != '/login.php') {
        $_SESSION['redirect_url'] = $_SERVER['HTTP_REFERER'];
    }
}
?>

<!DOCTYPE html>
<html lang="fa">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ورود به سیستم - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="assets/css/auth.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="auth-page">
    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-header">
                <h1>ورود به سیستم</h1>
                <p>لطفاً اطلاعات حساب کاربری خود را وارد کنید</p>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>
            
            <form method="POST" action="" class="auth-form">
                <div class="form-group">
                    <label for="email">ایمیل:</label>
                    <input type="email" id="email" name="email" required 
                           value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                </div>
                
                <div class="form-group">
                    <label for="password">رمز عبور:</label>
                    <input type="password" id="password" name="password" required>
                </div>
                
                <div class="form-options">
                    <label class="checkbox">
                        <input type="checkbox" name="remember" id="remember">
                        <span>مرا به خاطر بسپار</span>
                    </label>
                    
                    <a href="forgot_password.php" class="forgot-link">رمز عبور را فراموش کرده‌اید؟</a>
                </div>
                
                <button type="submit" class="btn btn-primary btn-block">ورود به سیستم</button>
            </form>
            
            <div class="auth-footer">
                <p>حساب کاربری ندارید؟ <a href="register.php">ثبت نام کنید</a></p>
            </div>
        </div>
        
        <div class="auth-logo">
            <img src="assets/images/logo.png" alt="<?php echo SITE_NAME; ?>">
            <h2><?php echo SITE_NAME; ?></h2>
        </div>
    </div>

    <script src="assets/js/auth.js"></script>
</body>
</html>