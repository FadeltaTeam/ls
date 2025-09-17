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

// دریافت تنظیمات از دیتابیس
$settings = [];
try {
    $query = "SELECT setting_key, setting_value, setting_group FROM settings ORDER BY setting_group, setting_key";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $settings_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // تبدیل به فرمت گروه‌بندی شده
    foreach ($settings_data as $setting) {
        $settings[$setting['setting_group']][$setting['setting_key']] = $setting['setting_value'];
    }
} catch (PDOException $e) {
    error_log("Settings error: " . $e->getMessage());
    $error = "خطا در دریافت تنظیمات.";
}

// پردازش فرم ذخیره تنظیمات
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $db->beginTransaction();
        
        foreach ($_POST as $key => $value) {
            if (strpos($key, 'setting_') === 0) {
                $setting_key = str_replace('setting_', '', $key);
                $setting_value = is_array($value) ? json_encode($value) : sanitize_input($value);
                
                $query = "UPDATE settings SET setting_value = :value, updated_at = NOW() 
                         WHERE setting_key = :key";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':value', $setting_value);
                $stmt->bindParam(':key', $setting_key);
                $stmt->execute();
            }
        }
        
        $db->commit();
        $message = "تنظیمات با موفقیت ذخیره شد.";
        logActivity($db, $_SESSION['user_id'], 'settings_updated', 'به‌روزرسانی تنظیمات سیستم');
        
        // رفرش صفحه برای نمایش تغییرات
        header("Location: settings.php?success=1");
        exit();
        
    } catch (PDOException $e) {
        $db->rollBack();
        error_log("Save settings error: " . $e->getMessage());
        $error = "خطا در ذخیره تنظیمات: " . $e->getMessage();
    }
}

// نمایش پیام موفقیت
if (isset($_GET['success'])) {
    $message = "تنظیمات با موفقیت ذخیره شد.";
}
?>

<!DOCTYPE html>
<html lang="fa">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تنظیمات سیستم - سیستم لایسنس</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <?php include '../templates/partials/header.php'; ?>
    
    <div class="admin-container">
        <?php include '../templates/partials/sidebar.php'; ?>
        
        <main class="main-content">
            <div class="page-header">
                <h1>تنظیمات سیستم</h1>
                <p>مدیریت تنظیمات و پیکربندی سیستم</p>
            </div>

            <!-- نمایش پیام‌ها -->
            <?php if ($message): ?>
                <div class="alert alert-success"><?php echo $message; ?></div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>

            <form method="POST" class="settings-form">
                <!-- تب‌های تنظیمات -->
                <div class="settings-tabs">
                    <button type="button" class="tab-button active" data-tab="general">عمومی</button>
                    <button type="button" class="tab-button" data-tab="license">لایسنس</button>
                    <button type="button" class="tab-button" data-tab="email">ایمیل</button>
                    <button type="button" class="tab-button" data-tab="security">امنیت</button>
                    <button type="button" class="tab-button" data-tab="appearance">ظاهر</button>
                </div>

                <!-- محتوای تب‌ها -->
                <div class="tab-content active" id="general-tab">
                    <div class="settings-group">
                        <h3>تنظیمات عمومی</h3>
                        
                        <div class="form-group">
                            <label for="setting_site_name">نام سایت</label>
                            <input type="text" id="setting_site_name" name="setting_site_name" 
                                   value="<?php echo htmlspecialchars($settings['general']['site_name'] ?? 'سیستم لایسنس'); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="setting_site_description">توضیحات سایت</label>
                            <textarea id="setting_site_description" name="setting_site_description" rows="3"><?php echo htmlspecialchars($settings['general']['site_description'] ?? ''); ?></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label for="setting_admin_email">ایمیل مدیر</label>
                            <input type="email" id="setting_admin_email" name="setting_admin_email" 
                                   value="<?php echo htmlspecialchars($settings['general']['admin_email'] ?? ''); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="setting_timezone">منطقه زمانی</label>
                            <select id="setting_timezone" name="setting_timezone">
                                <option value="Asia/Tehran" <?php echo ($settings['general']['timezone'] ?? 'Asia/Tehran') == 'Asia/Tehran' ? 'selected' : ''; ?>>تهران (UTC+3:30)</option>
                                <option value="UTC" <?php echo ($settings['general']['timezone'] ?? '') == 'UTC' ? 'selected' : ''; ?>>UTC</option>
                                <option value="Europe/London" <?php echo ($settings['general']['timezone'] ?? '') == 'Europe/London' ? 'selected' : ''; ?>>لندن (UTC+0)</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="setting_default_language">زبان پیش‌فرض</label>
                            <select id="setting_default_language" name="setting_default_language">
                                <option value="fa" <?php echo ($settings['general']['default_language'] ?? 'fa') == 'fa' ? 'selected' : ''; ?>>فارسی</option>
                                <option value="en" <?php echo ($settings['general']['default_language'] ?? '') == 'en' ? 'selected' : ''; ?>>English</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="tab-content" id="license-tab">
                    <div class="settings-group">
                        <h3>تنظیمات لایسنس</h3>
                        
                        <div class="form-group">
                            <label for="setting_license_prefix">پیشوند کلید لایسنس</label>
                            <input type="text" id="setting_license_prefix" name="setting_license_prefix" 
                                   value="<?php echo htmlspecialchars($settings['license']['license_prefix'] ?? 'LS_'); ?>" required>
                            <small class="form-help">پیشوندی که قبل از کلید لایسنس اضافه می‌شود</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="setting_default_license_duration">مدت اعتبار پیش‌فرض (روز)</label>
                            <input type="number" id="setting_default_license_duration" name="setting_default_license_duration" 
                                   value="<?php echo htmlspecialchars($settings['license']['default_license_duration'] ?? '365'); ?>" min="1" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="setting_max_activations">حداکثر فعال‌سازی پیش‌فرض</label>
                            <input type="number" id="setting_max_activations" name="setting_max_activations" 
                                   value="<?php echo htmlspecialchars($settings['license']['max_activations'] ?? '3'); ?>" min="1" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="setting_license_check_interval">فاصله بررسی لایسنس (روز)</label>
                            <input type="number" id="setting_license_check_interval" name="setting_license_check_interval" 
                                   value="<?php echo htmlspecialchars($settings['license']['license_check_interval'] ?? '7'); ?>" min="1" required>
                            <small class="form-help">فاصله زمانی برای بررسی انقضای لایسنس‌ها</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="setting_auto_renewal">تمدید خودکار</label>
                            <select id="setting_auto_renewal" name="setting_auto_renewal">
                                <option value="1" <?php echo ($settings['license']['auto_renewal'] ?? '0') == '1' ? 'selected' : ''; ?>>فعال</option>
                                <option value="0" <?php echo ($settings['license']['auto_renewal'] ?? '0') == '0' ? 'selected' : ''; ?>>غیرفعال</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="tab-content" id="email-tab">
                    <div class="settings-group">
                        <h3>تنظیمات ایمیل</h3>
                        
                        <div class="form-group">
                            <label for="setting_smtp_host">SMTP Host</label>
                            <input type="text" id="setting_smtp_host" name="setting_smtp_host" 
                                   value="<?php echo htmlspecialchars($settings['email']['smtp_host'] ?? ''); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="setting_smtp_port">SMTP Port</label>
                            <input type="number" id="setting_smtp_port" name="setting_smtp_port" 
                                   value="<?php echo htmlspecialchars($settings['email']['smtp_port'] ?? '587'); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="setting_smtp_username">SMTP Username</label>
                            <input type="text" id="setting_smtp_username" name="setting_smtp_username" 
                                   value="<?php echo htmlspecialchars($settings['email']['smtp_username'] ?? ''); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="setting_smtp_password">SMTP Password</label>
                            <input type="password" id="setting_smtp_password" name="setting_smtp_password" 
                                   value="<?php echo htmlspecialchars($settings['email']['smtp_password'] ?? ''); ?>">
                            <small class="form-help">در صورت عدم تغییر، رمز قبلی حفظ می‌شود</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="setting_smtp_secure">SMTP Security</label>
                            <select id="setting_smtp_secure" name="setting_smtp_secure">
                                <option value="tls" <?php echo ($settings['email']['smtp_secure'] ?? 'tls') == 'tls' ? 'selected' : ''; ?>>TLS</option>
                                <option value="ssl" <?php echo ($settings['email']['smtp_secure'] ?? '') == 'ssl' ? 'selected' : ''; ?>>SSL</option>
                                <option value="" <?php echo ($settings['email']['smtp_secure'] ?? '') == '' ? 'selected' : ''; ?>>None</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="setting_email_from">ایمیل فرستنده</label>
                            <input type="email" id="setting_email_from" name="setting_email_from" 
                                   value="<?php echo htmlspecialchars($settings['email']['email_from'] ?? 'noreply@example.com'); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="setting_email_from_name">نام فرستنده</label>
                            <input type="text" id="setting_email_from_name" name="setting_email_from_name" 
                                   value="<?php echo htmlspecialchars($settings['email']['email_from_name'] ?? 'سیستم لایسنس'); ?>">
                        </div>
                    </div>
                </div>

                <div class="tab-content" id="security-tab">
                    <div class="settings-group">
                        <h3>تنظیمات امنیتی</h3>
                        
                        <div class="form-group">
                            <label for="setting_max_login_attempts">حداکثر تلاش برای ورود</label>
                            <input type="number" id="setting_max_login_attempts" name="setting_max_login_attempts" 
                                   value="<?php echo htmlspecialchars($settings['security']['max_login_attempts'] ?? '5'); ?>" min="1" required>
                            <small class="form-help">تعداد دفعات مجاز برای ورود ناموفق قبل از قفل شدن حساب</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="setting_login_lockout_time">زمان قفل شدن حساب (ثانیه)</label>
                            <input type="number" id="setting_login_lockout_time" name="setting_login_lockout_time" 
                                   value="<?php echo htmlspecialchars($settings['security']['login_lockout_time'] ?? '900'); ?>" min="1" required>
                            <small class="form-help">زمانی که حساب پس از ورود ناموفق قفل می‌شود</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="setting_session_timeout">مدت زمان Session (ثانیه)</label>
                            <input type="number" id="setting_session_timeout" name="setting_session_timeout" 
                                   value="<?php echo htmlspecialchars($settings['security']['session_timeout'] ?? '86400'); ?>" min="60" required>
                            <small class="form-help">مدت زمان اعتبار session کاربران</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="setting_password_min_length">حداقل طول رمز عبور</label>
                            <input type="number" id="setting_password_min_length" name="setting_password_min_length" 
                                   value="<?php echo htmlspecialchars($settings['security']['password_min_length'] ?? '8'); ?>" min="6" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="setting_password_require_complexity">الزام پیچیدگی رمز عبور</label>
                            <select id="setting_password_require_complexity" name="setting_password_require_complexity">
                                <option value="1" <?php echo ($settings['security']['password_require_complexity'] ?? '1') == '1' ? 'selected' : ''; ?>>فعال</option>
                                <option value="0" <?php echo ($settings['security']['password_require_complexity'] ?? '1') == '0' ? 'selected' : ''; ?>>غیرفعال</option>
                            </select>
                            <small class="form-help">اجباری بودن استفاده از حروف بزرگ، کوچک، اعداد و نمادها</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="setting_force_https">اجبار استفاده از HTTPS</label>
                            <select id="setting_force_https" name="setting_force_https">
                                <option value="1" <?php echo ($settings['security']['force_https'] ?? '0') == '1' ? 'selected' : ''; ?>>فعال</option>
                                <option value="0" <?php echo ($settings['security']['force_https'] ?? '0') == '0' ? 'selected' : ''; ?>>غیرفعال</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="tab-content" id="appearance-tab">
                    <div class="settings-group">
                        <h3>تنظیمات ظاهر</h3>
                        
                        <div class="form-group">
                            <label for="setting_theme">تم پیش‌فرض</label>
                            <select id="setting_theme" name="setting_theme">
                                <option value="light" <?php echo ($settings['appearance']['theme'] ?? 'light') == 'light' ? 'selected' : ''; ?>>روشن</option>
                                <option value="dark" <?php echo ($settings['appearance']['theme'] ?? '') == 'dark' ? 'selected' : ''; ?>>تیره</option>
                                <option value="auto" <?php echo ($settings['appearance']['theme'] ?? '') == 'auto' ? 'selected' : ''; ?>>خودکار</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="setting_logo_url">آدرس لوگو</label>
                            <input type="url" id="setting_logo_url" name="setting_logo_url" 
                                   value="<?php echo htmlspecialchars($settings['appearance']['logo_url'] ?? '../assets/images/logo.png'); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="setting_favicon_url">آدرس Favicon</label>
                            <input type="url" id="setting_favicon_url" name="setting_favicon_url" 
                                   value="<?php echo htmlspecialchars($settings['appearance']['favicon_url'] ?? '../assets/images/favicon.ico'); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="setting_custom_css">CSS سفارشی</label>
                            <textarea id="setting_custom_css" name="setting_custom_css" rows="6" 
                                      placeholder="/* CSS سفارشی خود را اینجا وارد کنید */"><?php echo htmlspecialchars($settings['appearance']['custom_css'] ?? ''); ?></textarea>
                            <small class="form-help">CSS اضافی برای سفارشی‌سازی ظاهر سیستم</small>
                        </div>
                    </div>
                </div>

                <!-- دکمه‌های ذخیره -->
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">ذخیره تنظیمات</button>
                    <button type="button" class="btn btn-secondary" onclick="resetToDefault()">بازنشانی به پیش‌فرض</button>
                    <button type="button" class="btn btn-danger" onclick="testEmailSettings()">تست تنظیمات ایمیل</button>
                </div>
            </form>

            <!-- بخش پشتیبان‌گیری -->
            <div class="settings-group">
                <h3>پشتیبان‌گیری و بازیابی</h3>
                
                <div class="backup-actions">
                    <form method="POST" action="../api/backup_database.php" style="display: inline-block;">
                        <button type="submit" class="btn btn-primary">پشتیبان‌گیری از دیتابیس</button>
                    </form>
                    
                    <button type="button" class="btn btn-secondary" onclick="showRestoreModal()">بازیابی از پشتیبان</button>
                    
                    <form method="POST" action="../api/clear_cache.php" style="display: inline-block;">
                        <button type="submit" class="btn btn-warning">پاک‌سازی کش</button>
                    </form>
                </div>
                
                <div class="backup-info">
                    <p><strong>آخرین پشتیبان:</strong> 
                        <?php
                        $backup_file = '../storage/backups/latest_backup.sql';
                        if (file_exists($backup_file)) {
                            echo toPersianDate(date('Y-m-d H:i:s', filemtime($backup_file)), 'Y/m/d H:i');
                            echo ' (' . formatFileSize(filesize($backup_file)) . ')';
                        } else {
                            echo 'هیچ پشتیبان‌ی یافت نشد';
                        }
                        ?>
                    </p>
                </div>
            </div>
        </main>
    </div>

    <!-- مودال بازیابی -->
    <div id="restoreModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>بازیابی از پشتیبان</h3>
                <span class="close" onclick="closeModal('restoreModal')">&times;</span>
            </div>
            <div class="modal-body">
                <div class="alert alert-warning">
                    <strong>هشدار:</strong> این عمل تمام داده‌های فعلی را با داده‌های پشتیبان جایگزین می‌کند.
                    این عمل غیرقابل بازگشت است!
                </div>
                
                <form method="POST" action="../api/restore_database.php" enctype="multipart/form-data">
                    <div class="form-group">
                        <label for="backup_file">فایل پشتیبان</label>
                        <input type="file" id="backup_file" name="backup_file" accept=".sql,.gz" required>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-danger" onclick="return confirm('آیا از بازیابی دیتابیس اطمینان دارید؟ این عمل غیرقابل بازگشت است.')">
                            بازیابی
                        </button>
                        <button type="button" class="btn btn-secondary" onclick="closeModal('restoreModal')">انصراف</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="../assets/js/admin.js"></script>
    <script>
    // مدیریت تب‌ها
    document.querySelectorAll('.tab-button').forEach(button => {
        button.addEventListener('click', () => {
            // غیرفعال کردن همه تب‌ها
            document.querySelectorAll('.tab-button').forEach(btn => btn.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
            
            // فعال کردن تب انتخاب شده
            button.classList.add('active');
            const tabId = button.getAttribute('data-tab') + '-tab';
            document.getElementById(tabId).classList.add('active');
        });
    });

    // بازنشانی به پیش‌فرض
    function resetToDefault() {
        if (confirm('آیا از بازنشانی تمام تنظیمات به مقادیر پیش‌فرض اطمینان دارید؟')) {
            fetch('../api/reset_settings.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('تنظیمات با موفقیت بازنشانی شدند.');
                    location.reload();
                } else {
                    alert('خطا در بازنشانی تنظیمات: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('خطا در ارتباط با سرور');
            });
        }
    }

    // تست تنظیمات ایمیل
    function testEmailSettings() {
        const email = prompt('لطفاً ایمیل خود را برای تست وارد کنید:');
        if (email && validateEmail(email)) {
            fetch('../api/test_email.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ email: email })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('ایمیل تست با موفقیت ارسال شد. لطفاً صندوق ایمیل خود را بررسی کنید.');
                } else {
                    alert('خطا در ارسال ایمیل تست: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('خطا در ارتباط با سرور');
            });
        } else if (email) {
            alert('لطفاً یک ایمیل معتبر وارد کنید.');
        }
    }

    // نمایش مودال بازیابی
    function showRestoreModal() {
        document.getElementById('restoreModal').style.display = 'block';
    }

    // اعتبارسنجی ایمیل
    function validateEmail(email) {
        const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return re.test(email);
    }

    // بستن مودال
    function closeModal(modalId) {
        document.getElementById(modalId).style.display = 'none';
    }
    </script>
</body>
</html>