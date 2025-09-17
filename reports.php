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

// تنظیم پارامترهای پیش‌فرض گزارش
$report_type = isset($_GET['report_type']) ? $_GET['report_type'] : 'licenses_overview';
$date_range = isset($_GET['date_range']) ? $_GET['date_range'] : 'this_month';
$product_id = isset($_GET['product_id']) ? intval($_GET['product_id']) : '';
$user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : '';

// محاسبه تاریخ‌ها بر اساس بازه انتخابی
$date_from = '';
$date_to = date('Y-m-d');

switch ($date_range) {
    case 'today':
        $date_from = date('Y-m-d');
        break;
    case 'yesterday':
        $date_from = date('Y-m-d', strtotime('-1 day'));
        $date_to = date('Y-m-d', strtotime('-1 day'));
        break;
    case 'this_week':
        $date_from = date('Y-m-d', strtotime('monday this week'));
        break;
    case 'last_week':
        $date_from = date('Y-m-d', strtotime('monday last week'));
        $date_to = date('Y-m-d', strtotime('sunday last week'));
        break;
    case 'this_month':
        $date_from = date('Y-m-01');
        break;
    case 'last_month':
        $date_from = date('Y-m-01', strtotime('last month'));
        $date_to = date('Y-m-t', strtotime('last month'));
        break;
    case 'this_year':
        $date_from = date('Y-01-01');
        break;
    case 'last_year':
        $date_from = date('Y-01-01', strtotime('-1 year'));
        $date_to = date('Y-12-31', strtotime('-1 year'));
        break;
    case 'custom':
        $date_from = isset($_GET['date_from']) ? $_GET['date_from'] : date('Y-m-01');
        $date_to = isset($_GET['date_to']) ? $_GET['date_to'] : date('Y-m-d');
        break;
}

// دریافت داده‌های گزارش بر اساس نوع
$report_data = [];
$chart_data = [];

try {
    switch ($report_type) {
        case 'licenses_overview':
            // آمار کلی لایسنس‌ها
            $query = "SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active,
                SUM(CASE WHEN status = 'suspended' THEN 1 ELSE 0 END) as suspended,
                SUM(CASE WHEN status = 'revoked' THEN 1 ELSE 0 END) as revoked,
                SUM(CASE WHEN expiry_date < CURDATE() AND status = 'active' THEN 1 ELSE 0 END) as expired
            FROM licenses 
            WHERE created_at BETWEEN :date_from AND :date_to + INTERVAL 1 DAY";
            
            $params = [':date_from' => $date_from, ':date_to' => $date_to];
            
            if ($product_id) {
                $query .= " AND product_id = :product_id";
                $params[':product_id'] = $product_id;
            }
            if ($user_id) {
                $query .= " AND user_id = :user_id";
                $params[':user_id'] = $user_id;
            }
            
            $stmt = $db->prepare($query);
            $stmt->execute($params);
            $report_data = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // داده‌های نمودار
            $chart_query = "SELECT 
                DATE(created_at) as date,
                COUNT(*) as count,
                SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_count
            FROM licenses 
            WHERE created_at BETWEEN :date_from AND :date_to + INTERVAL 1 DAY
            GROUP BY DATE(created_at)
            ORDER BY date";
            
            $chart_stmt = $db->prepare($chart_query);
            $chart_stmt->execute([':date_from' => $date_from, ':date_to' => $date_to]);
            $chart_data = $chart_stmt->fetchAll(PDO::FETCH_ASSOC);
            break;

        case 'revenue_report':
            // گزارش درآمد
            $query = "SELECT 
                SUM(amount) as total_revenue,
                COUNT(*) as total_transactions,
                AVG(amount) as average_amount
            FROM payments 
            WHERE status = 'completed' 
            AND created_at BETWEEN :date_from AND :date_to + INTERVAL 1 DAY";
            
            $stmt = $db->prepare($query);
            $stmt->execute([':date_from' => $date_from, ':date_to' => $date_to]);
            $report_data = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // درآمد به تفکیک محصول
            $chart_query = "SELECT 
                p.name as product_name,
                SUM(py.amount) as revenue,
                COUNT(py.id) as transactions
            FROM payments py
            JOIN licenses l ON py.license_id = l.id
            JOIN products p ON l.product_id = p.id
            WHERE py.status = 'completed' 
            AND py.created_at BETWEEN :date_from AND :date_to + INTERVAL 1 DAY
            GROUP BY p.id, p.name
            ORDER BY revenue DESC";
            
            $chart_stmt = $db->prepare($chart_query);
            $chart_stmt->execute([':date_from' => $date_from, ':date_to' => $date_to]);
            $chart_data = $chart_stmt->fetchAll(PDO::FETCH_ASSOC);
            break;

        case 'user_activity':
            // فعالیت کاربران
            $query = "SELECT 
                u.name as user_name,
                u.email,
                COUNT(DISTINCT l.id) as license_count,
                COUNT(DISTINCT la.id) as activation_count,
                COUNT(DISTINCT al.id) as activity_count
            FROM users u
            LEFT JOIN licenses l ON u.id = l.user_id
            LEFT JOIN license_activations la ON l.id = la.license_id
            LEFT JOIN activity_logs al ON u.id = al.user_id
            WHERE u.created_at BETWEEN :date_from AND :date_to + INTERVAL 1 DAY
            GROUP BY u.id, u.name, u.email
            ORDER BY activity_count DESC
            LIMIT 20";
            
            $stmt = $db->prepare($query);
            $stmt->execute([':date_from' => $date_from, ':date_to' => $date_to]);
            $report_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;

        case 'product_performance':
            // عملکرد محصولات
            $query = "SELECT 
                p.name as product_name,
                p.version,
                COUNT(l.id) as total_licenses,
                SUM(CASE WHEN l.status = 'active' THEN 1 ELSE 0 END) as active_licenses,
                SUM(CASE WHEN l.expiry_date < CURDATE() AND l.status = 'active' THEN 1 ELSE 0 END) as expired_licenses,
                SUM(py.amount) as total_revenue
            FROM products p
            LEFT JOIN licenses l ON p.id = l.product_id
            LEFT JOIN payments py ON l.id = py.license_id AND py.status = 'completed'
            WHERE p.created_at BETWEEN :date_from AND :date_to + INTERVAL 1 DAY
            GROUP BY p.id, p.name, p.version
            ORDER BY total_revenue DESC";
            
            $stmt = $db->prepare($query);
            $stmt->execute([':date_from' => $date_from, ':date_to' => $date_to]);
            $report_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;
    }
} catch (PDOException $e) {
    error_log("Report error: " . $e->getMessage());
    $error = "خطا در تولید گزارش: " . $e->getMessage();
}

// دریافت لیست محصولات و کاربران برای فیلتر
$products = [];
$users = [];
try {
    $product_query = "SELECT id, name FROM products WHERE status = 1 ORDER BY name";
    $product_stmt = $db->prepare($product_query);
    $product_stmt->execute();
    $products = $product_stmt->fetchAll(PDO::FETCH_ASSOC);

    $user_query = "SELECT id, name, email FROM users WHERE status = 1 ORDER BY name";
    $user_stmt = $db->prepare($user_query);
    $user_stmt->execute();
    $users = $user_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Filter data error: " . $e->getMessage());
}

// عنوان گزارش
$report_titles = [
    'licenses_overview' => 'گزارش کلی لایسنس‌ها',
    'revenue_report' => 'گزارش درآمد',
    'user_activity' => 'فعالیت کاربران',
    'product_performance' => 'عملکرد محصولات'
];
?>

<!DOCTYPE html>
<html lang="fa">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>گزارشات - سیستم لایسنس</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <?php include '../templates/partials/header.php'; ?>
    
    <div class="admin-container">
        <?php include '../templates/partials/sidebar.php'; ?>
        
        <main class="main-content">
            <div class="page-header">
                <h1>گزارشات و آمار</h1>
                <div class="header-actions">
                    <button class="btn btn-primary" onclick="exportReport()">خروجی PDF</button>
                    <button class="btn btn-secondary" onclick="exportExcel()">خروجی Excel</button>
                </div>
            </div>

            <!-- فیلترهای گزارش -->
            <div class="filters-card">
                <h3>فیلترهای گزارش</h3>
                <form method="GET" action="" class="filter-form">
                    <div class="filter-grid">
                        <div class="form-group">
                            <label for="report_type">نوع گزارش:</label>
                            <select id="report_type" name="report_type" onchange="this.form.submit()">
                                <option value="licenses_overview" <?php echo $report_type == 'licenses_overview' ? 'selected' : ''; ?>>گزارش کلی لایسنس‌ها</option>
                                <option value="revenue_report" <?php echo $report_type == 'revenue_report' ? 'selected' : ''; ?>>گزارش درآمد</option>
                                <option value="user_activity" <?php echo $report_type == 'user_activity' ? 'selected' : ''; ?>>فعالیت کاربران</option>
                                <option value="product_performance" <?php echo $report_type == 'product_performance' ? 'selected' : ''; ?>>عملکرد محصولات</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="date_range">بازه زمانی:</label>
                            <select id="date_range" name="date_range" onchange="toggleCustomDate()">
                                <option value="today" <?php echo $date_range == 'today' ? 'selected' : ''; ?>>امروز</option>
                                <option value="yesterday" <?php echo $date_range == 'yesterday' ? 'selected' : ''; ?>>دیروز</option>
                                <option value="this_week" <?php echo $date_range == 'this_week' ? 'selected' : ''; ?>>این هفته</option>
                                <option value="last_week" <?php echo $date_range == 'last_week' ? 'selected' : ''; ?>>هفته گذشته</option>
                                <option value="this_month" <?php echo $date_range == 'this_month' ? 'selected' : ''; ?>>این ماه</option>
                                <option value="last_month" <?php echo $date_range == 'last_month' ? 'selected' : ''; ?>>ماه گذشته</option>
                                <option value="this_year" <?php echo $date_range == 'this_year' ? 'selected' : ''; ?>>امسال</option>
                                <option value="last_year" <?php echo $date_range == 'last_year' ? 'selected' : ''; ?>>سال گذشته</option>
                                <option value="custom" <?php echo $date_range == 'custom' ? 'selected' : ''; ?>>سفارشی</option>
                            </select>
                        </div>
                        
                        <div class="form-group" id="custom_date_group" style="display: <?php echo $date_range == 'custom' ? 'block' : 'none'; ?>">
                            <label for="date_from">از تاریخ:</label>
                            <input type="date" id="date_from" name="date_from" value="<?php echo $date_from; ?>">
                        </div>
                        
                        <div class="form-group" id="custom_date_group_to" style="display: <?php echo $date_range == 'custom' ? 'block' : 'none'; ?>">
                            <label for="date_to">تا تاریخ:</label>
                            <input type="date" id="date_to" name="date_to" value="<?php echo $date_to; ?>">
                        </div>
                        
                        <?php if (in_array($report_type, ['licenses_overview', 'product_performance'])): ?>
                        <div class="form-group">
                            <label for="product_id">محصول:</label>
                            <select id="product_id" name="product_id">
                                <option value="">همه محصولات</option>
                                <?php foreach ($products as $product): ?>
                                    <option value="<?php echo $product['id']; ?>" <?php echo $product_id == $product['id'] ? 'selected' : ''; ?>>
                                        <?php echo $product['name']; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($report_type == 'licenses_overview'): ?>
                        <div class="form-group">
                            <label for="user_id">کاربر:</label>
                            <select id="user_id" name="user_id">
                                <option value="">همه کاربران</option>
                                <?php foreach ($users as $user): ?>
                                    <option value="<?php echo $user['id']; ?>" <?php echo $user_id == $user['id'] ? 'selected' : ''; ?>>
                                        <?php echo $user['name'] . ' (' . $user['email'] . ')'; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="filter-actions">
                        <button type="submit" class="btn btn-primary">اعمال فیلتر</button>
                        <button type="button" class="btn btn-secondary" onclick="resetFilters()">پاک کردن فیلترها</button>
                    </div>
                </form>
            </div>

            <!-- خلاصه گزارش -->
            <div class="report-summary">
                <h2><?php echo $report_titles[$report_type]; ?></h2>
                <p class="report-period">
                    بازه زمانی: 
                    <strong>
                        <?php echo toPersianDate($date_from, 'Y/m/d'); ?> 
                        تا 
                        <?php echo toPersianDate($date_to, 'Y/m/d'); ?>
                    </strong>
                </p>
            </div>

            <!-- نمایش گزارش -->
            <div class="report-content">
                <?php switch ($report_type): 
                    case 'licenses_overview': ?>
                        <div class="stats-grid">
                            <div class="stat-card">
                                <div class="stat-icon" style="background-color: #4e73df;">
                                    <i class="icon-license"></i>
                                </div>
                                <div class="stat-info">
                                    <h3>کل لایسنس‌ها</h3>
                                    <p class="stat-number"><?php echo number_format($report_data['total']); ?></p>
                                </div>
                            </div>
                            
                            <div class="stat-card">
                                <div class="stat-icon" style="background-color: #1cc88a;">
                                    <i class="icon-active"></i>
                                </div>
                                <div class="stat-info">
                                    <h3>فعال</h3>
                                    <p class="stat-number"><?php echo number_format($report_data['active']); ?></p>
                                    <span class="stat-subtext"><?php echo round(($report_data['active'] / max(1, $report_data['total'])) * 100); ?>%</span>
                                </div>
                            </div>
                            
                            <div class="stat-card">
                                <div class="stat-icon" style="background-color: #f6c23e;">
                                    <i class="icon-suspended"></i>
                                </div>
                                <div class="stat-info">
                                    <h3>معلق</h3>
                                    <p class="stat-number"><?php echo number_format($report_data['suspended']); ?></p>
                                </div>
                            </div>
                            
                            <div class="stat-card">
                                <div class="stat-icon" style="background-color: #e74a3b;">
                                    <i class="icon-revoked"></i>
                                </div>
                                <div class="stat-info">
                                    <h3>لغو شده</h3>
                                    <p class="stat-number"><?php echo number_format($report_data['revoked']); ?></p>
                                </div>
                            </div>
                            
                            <div class="stat-card">
                                <div class="stat-icon" style="background-color: #858796;">
                                    <i class="icon-expired"></i>
                                </div>
                                <div class="stat-info">
                                    <h3>منقضی شده</h3>
                                    <p class="stat-number"><?php echo number_format($report_data['expired']); ?></p>
                                </div>
                            </div>
                        </div>

                        <!-- نمودار لایسنس‌ها -->
                        <div class="chart-container">
                            <canvas id="licensesChart"></canvas>
                        </div>
                        <?php break; ?>

                    <?php case 'revenue_report': ?>
                        <div class="stats-grid">
                            <div class="stat-card">
                                <div class="stat-icon" style="background-color: #1cc88a;">
                                    <i class="icon-revenue"></i>
                                </div>
                                <div class="stat-info">
                                    <h3>کل درآمد</h3>
                                    <p class="stat-number"><?php echo number_format($report_data['total_revenue']); ?> تومان</p>
                                </div>
                            </div>
                            
                            <div class="stat-card">
                                <div class="stat-icon" style="background-color: #4e73df;">
                                    <i class="icon-transactions"></i>
                                </div>
                                <div class="stat-info">
                                    <h3>تعداد تراکنش‌ها</h3>
                                    <p class="stat-number"><?php echo number_format($report_data['total_transactions']); ?></p>
                                </div>
                            </div>
                            
                            <div class="stat-card">
                                <div class="stat-icon" style="background-color: #36b9cc;">
                                    <i class="icon-average"></i>
                                </div>
                                <div class="stat-info">
                                    <h3>میانگین مبلغ</h3>
                                    <p class="stat-number"><?php echo number_format($report_data['average_amount']); ?> تومان</p>
                                </div>
                            </div>
                        </div>

                        <!-- نمودار درآمد -->
                        <div class="chart-container">
                            <canvas id="revenueChart"></canvas>
                        </div>
                        <?php break; ?>

                    <?php case 'user_activity': ?>
                        <div class="table-responsive">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>کاربر</th>
                                        <th>ایمیل</th>
                                        <th>تعداد لایسنس</th>
                                        <th>تعداد فعال‌سازی</th>
                                        <th>تعداد فعالیت</th>
                                        <th>امتیاز فعالیت</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($report_data as $user): ?>
                                        <tr>
                                            <td><?php echo $user['user_name']; ?></td>
                                            <td><?php echo $user['email']; ?></td>
                                            <td><?php echo number_format($user['license_count']); ?></td>
                                            <td><?php echo number_format($user['activation_count']); ?></td>
                                            <td><?php echo number_format($user['activity_count']); ?></td>
                                            <td>
                                                <?php 
                                                $score = ($user['license_count'] * 3) + ($user['activation_count'] * 2) + $user['activity_count'];
                                                echo number_format($score);
                                                ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php break; ?>

                    <?php case 'product_performance': ?>
                        <div class="table-responsive">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>محصول</th>
                                        <th>نسخه</th>
                                        <th>کل لایسنس‌ها</th>
                                        <th>لایسنس‌های فعال</th>
                                        <th>لایسنس‌های منقضی</th>
                                        <th>درآمد کل</th>
                                        <th>میانگین درآمد</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($report_data as $product): ?>
                                        <tr>
                                            <td><strong><?php echo $product['product_name']; ?></strong></td>
                                            <td><?php echo $product['version']; ?></td>
                                            <td><?php echo number_format($product['total_licenses']); ?></td>
                                            <td>
                                                <span class="text-success"><?php echo number_format($product['active_licenses']); ?></span>
                                                <small class="text-muted">
                                                    (<?php echo round(($product['active_licenses'] / max(1, $product['total_licenses'])) * 100); ?>%)
                                                </small>
                                            </td>
                                            <td>
                                                <span class="text-danger"><?php echo number_format($product['expired_licenses']); ?></span>
                                            </td>
                                            <td>
                                                <strong><?php echo number_format($product['total_revenue']); ?> تومان</strong>
                                            </td>
                                            <td>
                                                <?php echo number_format($product['total_revenue'] / max(1, $product['total_licenses'])); ?> تومان
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php break; ?>
                <?php endswitch; ?>
            </div>
        </main>
    </div>

    <script>
    function toggleCustomDate() {
        const dateRange = document.getElementById('date_range').value;
        document.getElementById('custom_date_group').style.display = dateRange === 'custom' ? 'block' : 'none';
        document.getElementById('custom_date_group_to').style.display = dateRange === 'custom' ? 'block' : 'none';
    }

    function resetFilters() {
        window.location.href = 'reports.php';
    }

    function exportReport() {
        const params = new URLSearchParams(window.location.search);
        window.open(`../api/export_report.php?${params.toString()}`, '_blank');
    }

    function exportExcel() {
        const params = new URLSearchParams(window.location.search);
        params.append('format', 'excel');
        window.open(`../api/export_report.php?${params.toString()}`, '_blank');
    }

    // نمودارها
    <?php if ($report_type == 'licenses_overview' && !empty($chart_data)): ?>
    new Chart(document.getElementById('licensesChart'), {
        type: 'line',
        data: {
            labels: [<?php echo implode(',', array_map(function($item) { return "'" . toPersianDate($item['date'], 'm/d') . "'"; }, $chart_data)); ?>],
            datasets: [{
                label: 'تعداد لایسنس‌های ایجاد شده',
                data: [<?php echo implode(',', array_column($chart_data, 'count')); ?>],
                borderColor: '#4e73df',
                backgroundColor: 'rgba(78, 115, 223, 0.1)',
                fill: true
            }, {
                label: 'لایسنس‌های فعال',
                data: [<?php echo implode(',', array_column($chart_data, 'active_count')); ?>],
                borderColor: '#1cc88a',
                backgroundColor: 'rgba(28, 200, 138, 0.1)',
                fill: true
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'top',
                }
            }
        }
    });
    <?php endif; ?>

    <?php if ($report_type == 'revenue_report' && !empty($chart_data)): ?>
    new Chart(document.getElementById('revenueChart'), {
        type: 'bar',
        data: {
            labels: [<?php echo implode(',', array_map(function($item) { return "'" . $item['product_name'] . "'"; }, $chart_data)); ?>],
            datasets: [{
                label: 'درآمد (تومان)',
                data: [<?php echo implode(',', array_column($chart_data, 'revenue')); ?>],
                backgroundColor: '#1cc88a'
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    display: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return value.toLocaleString('fa-IR') + ' تومان';
                        }
                    }
                }
            }
        }
    });
    <?php endif; ?>
    </script>
</body>
</html>