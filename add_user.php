<?php
require_once '../includes/config.php';
require_once '../includes/database.php';
require_once '../includes/auth.php';
require_once '../includes/helpers.php';

$database = new Database();
$db = $database->getConnection();
$auth = new Auth($db);

// بررسی احراز هویت و دسترسی ادمین
if (!$auth->isLoggedIn() || !$auth->hasPermission('admin')) {
    header('Location: ../login.php');
    exit();
}

$message = '';
$error = '';

// پردازش فرم ثبت کاربر جدید
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = sanitize_input($_POST['name']);
    $email = sanitize_input($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $role = sanitize_input($_POST['role']);
    $status = isset($_POST['status']) ? 1 : 0;
    $send_welcome_email = isset($_POST['send_welcome_email']);

    // اعتبارسنجی داده‌ها
    if (empty($name) || empty($email) || empty($password)) {
        $error = 'لطفاً تمام فیلدهای ضروری را پر کنید.';
    } elseif (!validate_email($email)) {
        $error = 'فرمت ایمیل نامعتبر است.';
    } elseif ($password !== $confirm_password) {
        $error = 'رمز عبور و تکرار آن مطابقت ندارند.';
    } elseif (strlen($password) < 8) {
        $error = 'رمز عبور باید حداقل 8 کاراکتر باشد.';
    } else {
        try {
            // بررسی وجود ایمیل
            $check_query = "SELECT id FROM users WHERE email = :email";
            $check_stmt = $db->prepare($check_query);
            $check_stmt->bindParam(':email', $email);
            $check_stmt->execute();

            if ($check_stmt->rowCount() > 0) {
                $error = 'این ایمیل قبلاً ثبت شده است.';
            } else {
                // هش کردن رمز عبور
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);

                // ثبت کاربر جدید
                $query = "INSERT INTO users (name, email, password, role, status, created_at) 
                         VALUES (:name, :email, :password, :role, :status, NOW())";
                
                $stmt = $db->prepare($query);
                $stmt->bindParam(':name', $name);
                $stmt->bindParam(':email', $email);
                $stmt->bindParam(':password', $hashed_password);
                $stmt->bindParam(':role', $role);
                $stmt->bindParam(':status', $status);

                if ($stmt->execute()) {
                    $user_id = $db->lastInsertId();
                    $message = 'کاربر جدید با موفقیت ایجاد شد.';

                    // ثبت فعالیت
                    logActivity($db, $_SESSION['user_id'], 'user_created', 'ایجاد کاربر جدید - ' . $email);

                    // ارسال ایمیل خوش‌آمدگویی
                    if ($send_welcome_email) {
                        $email_subject = "خوش آمدید به " . SITE_NAME;
                        $email_body = "
                            <h2>حساب کاربری شما ایجاد شد</h2>
                            <p>سلام {$name},</p>
                            <p>حساب کاربری شما در " . SITE_NAME . " با موفقیت ایجاد شد.</p>
                            <p><strong>ایمیل:</strong> {$email}</p>
                            <p>می‌توانید از طریق لینک زیر وارد حساب کاربری خود شوید:</p>
                            <p><a href=\"" . BASE_URL . "/login.php\">ورود به سیستم</a></p>
                        ";

                        if (sendEmail($email, $email_subject, $email_body)) {
                            $message .= ' ایمیل خوش‌آمدگویی نیز ارسال شد.';
                        } else {
                            $message .= ' اما ارسال ایمیل خوش‌آمدگویی با خطا مواجه شد.';
                        }
                    }

                    // ریدایرکت به صفحه مدیریت کاربران
                    header("Location: users.php?success=1");
                    exit();
                } else {
                    $error = 'خطا در ایجاد کاربر. لطفاً دوباره تلاش کنید.';
                }
            }
        } catch (PDOException $e) {
            error_log("Add user error: " . $e->getMessage());
            $error = 'خطا در ایجاد کاربر. لطفاً دوباره تلاش کنید.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fa">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>افزودن کاربر جدید - سیستم لایسنس</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <?php include '../templates/partials/header.php'; ?>
    
    <div class="admin-container">
        <?php include '../templates/partials/sidebar.php'; ?>
        
        <main class="main-content">
            <div class="page-header">
                <h1>افزودن کاربر جدید</h1>
                <a href="users.php" class="btn btn-secondary">بازگشت به لیست کاربران</a>
            </div>

            <!-- نمایش پیام‌ها -->
            <?php if ($message): ?>
                <div class="alert alert-success"><?php echo $message; ?></div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>

            <div class="card">
                <div class="card-header">
                    <h2>اطلاعات کاربر جدید</h2>
                </div>
                
                <div class="card-body">
                    <form method="POST" class="user-form">
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="name">نام کامل *</label>
                                <input type="text" id="name" name="name" 
                                       value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>" 
                                       required placeholder="نام و نام خانوادگی">
                            </div>
                            
                            <div class="form-group">
                                <label for="email">ایمیل *</label>
                                <input type="email" id="email" name="email" 
                                       value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" 
                                       required placeholder="example@domain.com">
                            </div>
                            
                            <div class="form-group">
                                <label for="password">رمز عبور *</label>
                                <input type="password" id="password" name="password" required 
                                       placeholder="حداقل 8 کاراکتر" minlength="8">
                                <div class="password-strength" id="passwordStrength">
                                    <div class="strength-bar"></div>
                                    <span class="strength-text">ضعیف</span>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="confirm_password">تکرار رمز عبور *</label>
                                <input type="password" id="confirm_password" name="confirm_password" required 
                                       placeholder="تکرار رمز عبور">
                                <span id="passwordMatch" class="validation-text"></span>
                            </div>
                            
                            <div class="form-group">
                                <label for="role">نقش کاربر *</label>
                                <select id="role" name="role" required>
                                    <option value="user" <?php echo (isset($_POST['role']) && $_POST['role'] == 'user') ? 'selected' : ''; ?>>کاربر عادی</option>
                                    <option value="manager" <?php echo (isset($_POST['role']) && $_POST['role'] == 'manager') ? 'selected' : ''; ?>>مدیر</option>
                                    <option value="admin" <?php echo (isset($_POST['role']) && $_POST['role'] == 'admin') ? 'selected' : ''; ?>>مدیر سیستم</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="status">وضعیت حساب</label>
                                <div class="checkbox-group">
                                    <input type="checkbox" id="status" name="status" value="1" 
                                           <?php echo (!isset($_POST['status']) || $_POST['status']) ? 'checked' : ''; ?>>
                                    <label for="status">حساب فعال باشد</label>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="send_welcome_email">ارسال ایمیل خوش‌آمدگویی</label>
                                <div class="checkbox-group">
                                    <input type="checkbox" id="send_welcome_email" name="send_welcome_email" value="1" checked>
                                    <label for="send_welcome_email">ارسال ایمیل خوش‌آمدگویی به کاربر</label>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">ایجاد کاربر</button>
                            <button type="button" class="btn btn-secondary" onclick="generatePassword()">تولید رمز عبور</button>
                            <a href="users.php" class="btn btn-outline">انصراف</a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- راهنما -->
            <div class="card">
                <div class="card-header">
                    <h3>راهنمای ایجاد کاربر</h3>
                </div>
                <div class="card-body">
                    <div class="help-content">
                        <h4>نقش‌های کاربری:</h4>
                        <ul>
                            <li><strong>مدیر سیستم:</strong> دسترسی کامل به تمام بخش‌های سیستم</li>
                            <li><strong>مدیر:</strong> دسترسی به مدیریت کاربران و لایسنس‌ها</li>
                            <li><strong>کاربر عادی:</strong> دسترسی فقط به بخش کاربری</li>
                        </ul>
                        
                        <h4>ملاحظات امنیتی:</h4>
                        <ul>
                            <li>رمز عبور باید حداقل 8 کاراکتر باشد</li>
                            <li>استفاده از ترکیب حروف، اعداد و نمادها توصیه می‌شود</li>
                            <li>برای امنیت بیشتر، رمز عبور را مستقیماً به کاربر اعلام نکنید</li>
                        </ul>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script src="../assets/js/admin.js"></script>
    <script>
    // بررسی مطابقت رمز عبور
    const password = document.getElementById('password');
    const confirmPassword = document.getElementById('confirm_password');
    const passwordMatch = document.getElementById('passwordMatch');

    function checkPasswordMatch() {
        if (password.value && confirmPassword.value) {
            if (password.value === confirmPassword.value) {
                passwordMatch.textContent = 'رمز عبور مطابقت دارد';
                passwordMatch.style.color = '#28a745';
            } else {
                passwordMatch.textContent = 'رمز عبور مطابقت ندارد';
                passwordMatch.style.color = '#dc3545';
            }
        } else {
            passwordMatch.textContent = '';
        }
    }

    password.addEventListener('input', checkPasswordMatch);
    confirmPassword.addEventListener('input', checkPasswordMatch);

    // بررسی قدرت رمز عبور
    password.addEventListener('input', function() {
        const strengthBar = document.querySelector('.strength-bar');
        const strengthText = document.querySelector('.strength-text');
        const value = password.value;
        let strength = 0;

        if (value.length >= 8) strength++;
        if (/[A-Z]/.test(value)) strength++;
        if (/[a-z]/.test(value)) strength++;
        if (/[0-9]/.test(value)) strength++;
        if (/[^A-Za-z0-9]/.test(value)) strength++;

        const strengthPercent = (strength / 5) * 100;
        strengthBar.style.width = strengthPercent + '%';

        switch (strength) {
            case 0:
            case 1:
                strengthBar.style.backgroundColor = '#dc3545';
                strengthText.textContent = 'ضعیف';
                break;
            case 2:
                strengthBar.style.backgroundColor = '#fd7e14';
                strengthText.textContent = 'متوسط';
                break;
            case 3:
                strengthBar.style.backgroundColor = '#ffc107';
                strengthText.textContent = 'قابل قبول';
                break;
            case 4:
                strengthBar.style.backgroundColor = '#28a745';
                strengthText.textContent = 'قوی';
                break;
            case 5:
                strengthBar.style.backgroundColor = '#20c997';
                strengthText.textContent = 'خیلی قوی';
                break;
        }
    });

    // تولید رمز عبور تصادفی
    function generatePassword() {
        const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789!@#$%^&*()';
        let password = '';
        
        for (let i = 0; i < 12; i++) {
            password += chars.charAt(Math.floor(Math.random() * chars.length));
        }
        
        document.getElementById('password').value = password;
        document.getElementById('confirm_password').value = password;
        checkPasswordMatch();
        
        // trigger strength check
        document.getElementById('password').dispatchEvent(new Event('input'));
        
        // نمایش رمز تولید شده به کاربر
        alert('رمز عبور تولید شده: ' + password + '\n\nلطفاً آن را یادداشت کنید.');
    }

    // اعتبارسنجی فرم
    document.querySelector('.user-form').addEventListener('submit', function(e) {
        const password = document.getElementById('password').value;
        const confirmPassword = document.getElementById('confirm_password').value;
        
        if (password !== confirmPassword) {
            e.preventDefault();
            alert('رمز عبور و تکرار آن مطابقت ندارند.');
            return false;
        }
        
        if (password.length < 8) {
            e.preventDefault();
            alert('رمز عبور باید حداقل 8 کاراکتر باشد.');
            return false;
        }
    });

    // نمایش/پنهان کردن رمز عبور
    function togglePasswordVisibility(fieldId) {
        const field = document.getElementById(fieldId);
        const type = field.type === 'password' ? 'text' : 'password';
        field.type = type;
    }
    </script>
</body>
</html>