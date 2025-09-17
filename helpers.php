<?php
// توابع کمکی

// تابع تبدیل تاریخ میلادی به شمسی
function toPersianDate($gregorianDate, $format = 'Y/m/d') {
    if (empty($gregorianDate) || $gregorianDate == '0000-00-00') {
        return '-';
    }
    
    $date = new DateTime($gregorianDate);
    $year = (int) $date->format('Y');
    $month = (int) $date->format('m');
    $day = (int) $date->format('d');
    
    list($jyear, $jmonth, $jday) = gregorian_to_jalali($year, $month, $day);
    
    // فرمت‌دهی خروجی
    $formatted = str_replace(
        array('Y', 'm', 'd'),
        array($jyear, str_pad($jmonth, 2, '0', STR_PAD_LEFT), str_pad($jday, 2, '0', STR_PAD_LEFT)),
        $format
    );
    
    return $formatted;
}

// تابع تبدیل میلادی به شمسی
function gregorian_to_jalali($g_y, $g_m, $g_d) {
    $g_days_in_month = array(31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31);
    $j_days_in_month = array(31, 31, 31, 31, 31, 31, 30, 30, 30, 30, 30, 29);
    
    $gy = $g_y - 1600;
    $gm = $g_m - 1;
    $gd = $g_d - 1;
    
    $g_day_no = 365 * $gy + (int)(($gy + 3) / 4) - (int)(($gy + 99) / 100) + (int)(($gy + 399) / 400);
    
    for ($i = 0; $i < $gm; ++$i)
        $g_day_no += $g_days_in_month[$i];
    
    if ($gm > 1 && (($gy % 4 == 0 && $gy % 100 != 0) || ($gy % 400 == 0)))
        $g_day_no++;
    
    $g_day_no += $gd;
    
    $j_day_no = $g_day_no - 79;
    
    $j_np = (int)($j_day_no / 12053);
    $j_day_no = $j_day_no % 12053;
    
    $jy = 979 + 33 * $j_np + 4 * (int)($j_day_no / 1461);
    
    $j_day_no %= 1461;
    
    if ($j_day_no >= 366) {
        $jy += (int)(($j_day_no - 1) / 365);
        $j_day_no = ($j_day_no - 1) % 365;
    }
    
    for ($i = 0; $i < 11 && $j_day_no >= $j_days_in_month[$i]; ++$i)
        $j_day_no -= $j_days_in_month[$i];
    
    $jm = $i + 1;
    $jd = $j_day_no + 1;
    
    return array($jy, $jm, $jd);
}

// تابع تبدیل اعداد به فارسی
function enToFaNumbers($number) {
    $english = array('0', '1', '2', '3', '4', '5', '6', '7', '8', '9');
    $persian = array('۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹');
    
    return str_replace($english, $persian, $number);
}

// تابع نمایش زمان به صورت نسبی (مثلاً "2 روز پیش")
function timeAgo($date) {
    if (empty($date)) {
        return 'نامشخص';
    }
    
    $now = new DateTime();
    $then = new DateTime($date);
    $diff = $now->diff($then);
    
    if ($diff->y > 0) {
        return enToFaNumbers($diff->y) . ' سال پیش';
    } elseif ($diff->m > 0) {
        return enToFaNumbers($diff->m) . ' ماه پیش';
    } elseif ($diff->d > 0) {
        return enToFaNumbers($diff->d) . ' روز پیش';
    } elseif ($diff->h > 0) {
        return enToFaNumbers($diff->h) . ' ساعت پیش';
    } elseif ($diff->i > 0) {
        return enToFaNumbers($diff->i) . ' دقیقه پیش';
    } else {
        return 'همین حالا';
    }
}

// تابع ایجاد اعلان جدید
function createNotification($db, $user_id, $title, $message, $type = 'info') {
    $query = "INSERT INTO notifications (user_id, title, message, type, created_at) 
              VALUES (:user_id, :title, :message, :type, NOW())";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(":user_id", $user_id);
    $stmt->bindParam(":title", $title);
    $stmt->bindParam(":message", $message);
    $stmt->bindParam(":type", $type);
    
    return $stmt->execute();
}

// تابع علامت‌گذاری همه اعلان‌ها به عنوان خوانده شده
function markAllNotificationsAsRead($db, $user_id) {
    $query = "UPDATE notifications SET is_read = 1, read_at = NOW() 
              WHERE user_id = :user_id AND is_read = 0";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(":user_id", $user_id);
    
    return $stmt->execute();
}

// تابع حذف اعلان‌های قدیمی
function cleanupOldNotifications($db, $days = 30) {
    $query = "DELETE FROM notifications 
              WHERE created_at < DATE_SUB(NOW(), INTERVAL :days DAY) 
              AND is_read = 1";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(":days", $days, PDO::PARAM_INT);
    
    return $stmt->execute();
}

// تابع ایجاد توکن امن
function generateSecureToken($length = 32) {
    return bin2hex(random_bytes($length));
}

// تابع بررسی اعتبار توکن
function validateToken($token, $storedToken) {
    return hash_equals($storedToken, $token);
}

// تابع ایجاد شناسه یکتا
function generateUUID() {
    return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );
}

// تابع لاگ کردن فعالیت‌ها
function logActivity($db, $userId, $action, $details = '') {
    $ipAddress = $_SERVER['REMOTE_ADDR'];
    $userAgent = $_SERVER['HTTP_USER_AGENT'];
    
    $query = "INSERT INTO activity_logs (user_id, action, details, ip_address, user_agent, created_at) 
              VALUES (:user_id, :action, :details, :ip_address, :user_agent, NOW())";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(":user_id", $userId);
    $stmt->bindParam(":action", $action);
    $stmt->bindParam(":details", $details);
    $stmt->bindParam(":ip_address", $ipAddress);
    $stmt->bindParam(":user_agent", $userAgent);
    
    return $stmt->execute();
}

// تابع ارسال ایمیل
function sendEmail($to, $subject, $body, $isHtml = true) {
    // استفاده از PHPMailer یا کتابخانه دیگر برای ارسال ایمیل
    // این یک پیاده‌سازی ساده است
    $headers = "From: no-reply@yourdomain.com\r\n";
    
    if ($isHtml) {
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    } else {
        $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
    }
    
    return mail($to, $subject, $body, $headers);
}

// تابع بررسی پیچیدگی رمز عبور
function isPasswordStrong($password) {
    // حداقل ۸ کاراکتر، شامل حروف بزرگ و کوچک، عدد و کاراکتر خاص
    $pattern = '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/';
    return preg_match($pattern, $password);
}

// تابع محدودیت نرخ درخواست
function rateLimit($key, $limit = 5, $timeout = 60) {
    $sessionKey = 'rate_limit_' . $key;
    
    if (!isset($_SESSION[$sessionKey])) {
        $_SESSION[$sessionKey] = [
            'count' => 1,
            'time' => time()
        ];
        return true;
    }
    
    $data = $_SESSION[$sessionKey];
    
    if (time() - $data['time'] > $timeout) {
        $_SESSION[$sessionKey] = [
            'count' => 1,
            'time' => time()
        ];
        return true;
    }
    
    if ($data['count'] >= $limit) {
        return false;
    }
    
    $_SESSION[$sessionKey]['count']++;
    return true;
}

// تشخیص نام مرورگر از user agent
function getBrowserName($user_agent) {
    if (strpos($user_agent, 'MSIE') !== false) {
        return 'Internet Explorer';
    } elseif (strpos($user_agent, 'Edge') !== false) {
        return 'Microsoft Edge';
    } elseif (strpos($user_agent, 'Chrome') !== false) {
        return 'Google Chrome';
    } elseif (strpos($user_agent, 'Firefox') !== false) {
        return 'Mozilla Firefox';
    } elseif (strpos($user_agent, 'Safari') !== false) {
        return 'Safari';
    } elseif (strpos($user_agent, 'Opera') !== false) {
        return 'Opera';
    } else {
        return 'مرورگر ناشناخته';
    }
}
?>