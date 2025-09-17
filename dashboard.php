<?php
require_once 'includes/config.php';
require_once 'includes/database.php';
require_once 'includes/auth.php';
require_once 'includes/helpers.php';

$database = new Database();
$db = $database->getConnection();
$auth = new Auth($db);

// بررسی احراز هویت
if (!$auth->isLoggedIn()) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'];

// دریافت آمار کاربر
$stats = [];
try {
    // تعداد لایسنس‌های کاربر
    $query = "SELECT COUNT(*) as total FROM licenses WHERE user_id = :user_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(":user_id", $user_id);
    $stmt->execute();
    $stats['total_licenses'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // لایسنس‌های فعال
    $query = "SELECT COUNT(*) as total FROM licenses WHERE user_id = :user_id AND status = 'active'";
    $stmt = $db->prepare($query);
    $stmt->bindParam(":user_id", $user_id);
    $stmt->execute();
    $stats['active_licenses'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // لایسنس‌های در حال انقضا (۷ روز آینده)
    $query = "SELECT COUNT(*) as total FROM licenses 
              WHERE user_id = :user_id AND status = 'active' 
              AND expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)";
    $stmt = $db->prepare($query);
    $stmt->bindParam(":user_id", $user_id);
    $stmt->execute();
    $stats['expiring_soon'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

} catch (PDOException $e) {
    error_log("Dashboard stats error: " . $e->getMessage());
    $stats = ['total_licenses' => 0, 'active_licenses' => 0, 'expiring_soon' => 0];
}

// دریافت لایسنس‌های کاربر
$licenses = [];
try {
    $query = "SELECT l.*, p.name as product_name, p.version as product_version
              FROM licenses l 
              JOIN products p ON l.product_id = p.id 
              WHERE l.user_id = :user_id 
              ORDER BY l.created_at DESC 
              LIMIT 5";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(":user_id", $user_id);
    $stmt->execute();
    $licenses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    error_log("Dashboard licenses error: " . $e->getMessage());
}

// دریافت اعلان‌های خوانده نشده
$notifications = [];
try {
    $query = "SELECT id, title, message, created_at 
              FROM notifications 
              WHERE user_id = :user_id AND is_read = 0 
              ORDER BY created_at DESC 
              LIMIT 5";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(":user_id", $user_id);
    $stmt->execute();
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    error_log("Dashboard notifications error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="fa">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>داشبورد - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="assets/css/dashboard.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include 'templates/partials/header.php'; ?>
    
    <div class="dashboard-container">
        <?php include 'templates/partials/sidebar.php'; ?>
        
        <main class="main-content">
            <div class="page-header">
                <h1>داشبورد</h1>
                <p>خوش آمدید، <?php echo $_SESSION['user_name']; ?></p>
            </div>
            
            <!-- آمار سریع -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon" style="background-color: #4e73df;">
                        <i class="icon-license"></i>
                    </div>
                    <div class="stat-info">
                        <h3>تعداد لایسنس‌ها</h3>
                        <p class="stat-number"><?php echo $stats['total_licenses']; ?></p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon" style="background-color: #1cc88a;">
                        <i class="icon-active"></i>
                    </div>
                    <div class="stat-info">
                        <h3>لایسنس‌های فعال</h3>
                        <p class="stat-number"><?php echo $stats['active_licenses']; ?></p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon" style="background-color: #f6c23e;">
                        <i class="icon-warning"></i>
                    </div>
                    <div class="stat-info">
                        <h3>در حال انقضا</h3>
                        <p class="stat-number"><?php echo $stats['expiring_soon']; ?></p>
                    </div>
                </div>
            </div>
            
            <div class="content-grid">
                <!-- لایسنس‌های اخیر -->
                <div class="content-card">
                    <div class="card-header">
                        <h2>لایسنس‌های اخیر</h2>
                        <a href="licenses.php" class="view-all">مشاهده همه</a>
                    </div>
                    <div class="card-body">
                        <?php if (count($licenses) > 0): ?>
                            <div class="licenses-list">
                                <?php foreach ($licenses as $license): ?>
                                    <div class="license-item">
                                        <div class="license-info">
                                            <h4><?php echo $license['product_name']; ?></h4>
                                            <p class="license-key"><?php echo $license['license_key']; ?></p>
                                            <span class="status-badge status-<?php echo $license['status']; ?>">
                                                <?php echo $license['status']; ?>
                                            </span>
                                        </div>
                                        <div class="license-meta">
                                            <span>انقضا: <?php echo toPersianDate($license['expiry_date']); ?></span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="empty-state">
                                <p>هنوز لایسنس فعالی ندارید.</p>
                                <a href="request_license.php" class="btn btn-primary">درخواست لایسنس جدید</a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- اعلان‌ها -->
                <div class="content-card">
                    <div class="card-header">
                        <h2>اعلان‌های اخیر</h2>
                        <a href="notifications.php" class="view-all">مشاهده همه</a>
                    </div>
                    <div class="card-body">
                        <?php if (count($notifications) > 0): ?>
                            <div class="notifications-list">
                                <?php foreach ($notifications as $notification): ?>
                                    <div class="notification-item unread">
                                        <div class="notification-content">
                                            <h4><?php echo $notification['title']; ?></h4>
                                            <p><?php echo $notification['message']; ?></p>
                                            <span class="notification-time">
                                                <?php echo timeAgo($notification['created_at']); ?>
                                            </span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="empty-state">
                                <p>هیچ اعلان خوانده نشده‌ای ندارید.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- فعالیت‌های اخیر -->
            <div class="content-card">
                <div class="card-header">
                    <h2>فعالیت‌های اخیر</h2>
                </div>
                <div class="card-body">
                    <div class="activity-list">
                        <div class="activity-item">
                            <div class="activity-icon">
                                <i class="icon-login"></i>
                            </div>
                            <div class="activity-content">
                                <p>ورود به سیستم</p>
                                <span class="activity-time">همین حالا</span>
                            </div>
                        </div>
                        <!-- فعالیت‌های بیشتر از دیتابیس قابل بارگذاری هستند -->
                    </div>
                </div>
            </div>
        </main>
    </div>
    
    <?php include 'templates/partials/footer.php'; ?>
    
    <script src="assets/js/dashboard.js"></script>
    <script src="assets/js/charts.js"></script>
</body>
</html>