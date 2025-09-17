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

// پردازش عملیات‌ها
$message = '';
$error = '';

// حذف محصول
if (isset($_POST['delete_product'])) {
    $product_id = intval($_POST['product_id']);
    
    try {
        // بررسی وجود لایسنس‌های فعال برای این محصول
        $check_query = "SELECT COUNT(*) as count FROM licenses WHERE product_id = :product_id AND status = 'active'";
        $check_stmt = $db->prepare($check_query);
        $check_stmt->bindParam(':product_id', $product_id);
        $check_stmt->execute();
        $active_licenses = $check_stmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        if ($active_licenses > 0) {
            $error = 'امکان حذف محصول وجود ندارد زیرا لایسنس‌های فعالی برای آن وجود دارد.';
        } else {
            $delete_query = "UPDATE products SET status = 0 WHERE id = :product_id";
            $delete_stmt = $db->prepare($delete_query);
            $delete_stmt->bindParam(':product_id', $product_id);
            
            if ($delete_stmt->execute()) {
                $message = 'محصول با موفقیت حذف شد.';
                logActivity($db, $_SESSION['user_id'], 'product_deleted', 'حذف محصول - ID: ' . $product_id);
            } else {
                $error = 'خطا در حذف محصول.';
            }
        }
    } catch (PDOException $e) {
        error_log("Delete product error: " . $e->getMessage());
        $error = 'خطا در حذف محصول.';
    }
}

// فعال/غیرفعال کردن محصول
if (isset($_POST['toggle_status'])) {
    $product_id = intval($_POST['product_id']);
    
    try {
        $query = "UPDATE products SET status = NOT status WHERE id = :product_id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':product_id', $product_id);
        
        if ($stmt->execute()) {
            $message = 'وضعیت محصول با موفقیت تغییر کرد.';
            logActivity($db, $_SESSION['user_id'], 'product_status_changed', 'تغییر وضعیت محصول - ID: ' . $product_id);
        } else {
            $error = 'خطا در تغییر وضعیت محصول.';
        }
    } catch (PDOException $e) {
        error_log("Toggle product status error: " . $e->getMessage());
        $error = 'خطا در تغییر وضعیت محصول.';
    }
}

// دریافت لیست محصولات
$products = [];
try {
    $query = "SELECT p.*, 
                     (SELECT COUNT(*) FROM licenses WHERE product_id = p.id) as total_licenses,
                     (SELECT COUNT(*) FROM licenses WHERE product_id = p.id AND status = 'active') as active_licenses
              FROM products p 
              ORDER BY p.created_at DESC";
    
    $stmt = $db->prepare($query);
    $stmt->execute();
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    error_log("Products list error: " . $e->getMessage());
    $error = 'خطا در دریافت لیست محصولات.';
}

// آمار محصولات
$stats = [
    'total_products' => 0,
    'active_products' => 0,
    'total_licenses' => 0,
    'active_licenses' => 0
];

foreach ($products as $product) {
    $stats['total_products']++;
    if ($product['status']) {
        $stats['active_products']++;
    }
    $stats['total_licenses'] += $product['total_licenses'];
    $stats['active_licenses'] += $product['active_licenses'];
}
?>

<!DOCTYPE html>
<html lang="fa">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>مدیریت محصولات - سیستم لایسنس</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <?php include '../templates/partials/header.php'; ?>
    
    <div class="admin-container">
        <?php include '../templates/partials/sidebar.php'; ?>
        
        <main class="main-content">
            <div class="page-header">
                <h1>مدیریت محصولات</h1>
                <div class="header-actions">
                    <a href="add_product.php" class="btn btn-primary">افزودن محصول جدید</a>
                </div>
            </div>

            <!-- نمایش پیام‌ها -->
            <?php if ($message): ?>
                <div class="alert alert-success"><?php echo $message; ?></div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>

            <!-- آمار محصولات -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon" style="background-color: #4e73df;">
                        <i class="icon-products"></i>
                    </div>
                    <div class="stat-info">
                        <h3>تعداد محصولات</h3>
                        <p class="stat-number"><?php echo number_format($stats['total_products']); ?></p>
                        <span class="stat-subtext"><?php echo number_format($stats['active_products']); ?> فعال</span>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon" style="background-color: #1cc88a;">
                        <i class="icon-license"></i>
                    </div>
                    <div class="stat-info">
                        <h3>لایسنس‌ها</h3>
                        <p class="stat-number"><?php echo number_format($stats['total_licenses']); ?></p>
                        <span class="stat-subtext"><?php echo number_format($stats['active_licenses']); ?> فعال</span>
                    </div>
                </div>
            </div>

            <!-- جدول محصولات -->
            <div class="card">
                <div class="card-header">
                    <h2>لیست محصولات</h2>
                </div>
                
                <div class="card-body">
                    <?php if (count($products) > 0): ?>
                        <div class="table-responsive">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>نام محصول</th>
                                        <th>نسخه</th>
                                        <th>قیمت (تومان)</th>
                                        <th>لایسنس‌ها</th>
                                        <th>وضعیت</th>
                                        <th>تاریخ ایجاد</th>
                                        <th>عملیات</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($products as $product): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo htmlspecialchars($product['name']); ?></strong>
                                                <?php if (!empty($product['description'])): ?>
                                                    <br>
                                                    <small class="text-muted"><?php echo htmlspecialchars(substr($product['description'], 0, 50)); ?>...</small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge badge-info"><?php echo htmlspecialchars($product['version']); ?></span>
                                            </td>
                                            <td>
                                                <?php echo number_format($product['price']); ?>
                                            </td>
                                            <td>
                                                <div class="license-stats">
                                                    <span class="text-success"><?php echo $product['active_licenses']; ?> فعال</span>
                                                    <span class="text-muted">/ <?php echo $product['total_licenses']; ?> کل</span>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="status-badge status-<?php echo $product['status'] ? 'active' : 'inactive'; ?>">
                                                    <?php echo $product['status'] ? 'فعال' : 'غیرفعال'; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="text-nowrap"><?php echo toPersianDate($product['created_at'], 'Y/m/d'); ?></span>
                                            </td>
                                            <td>
                                                <div class="action-buttons">
                                                    <a href="edit_product.php?id=<?php echo $product['id']; ?>" class="btn btn-sm btn-primary">
                                                        ویرایش
                                                    </a>
                                                    
                                                    <form method="POST" style="display: inline-block;">
                                                        <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                                                        <button type="submit" name="toggle_status" class="btn btn-sm btn-warning">
                                                            <?php echo $product['status'] ? 'غیرفعال' : 'فعال'; ?>
                                                        </button>
                                                    </form>
                                                    
                                                    <?php if ($product['active_licenses'] == 0): ?>
                                                        <form method="POST" style="display: inline-block;" onsubmit="return confirm('آیا از حذف این محصول اطمینان دارید؟');">
                                                            <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                                                            <button type="submit" name="delete_product" class="btn btn-sm btn-danger">
                                                                حذف
                                                            </button>
                                                        </form>
                                                    <?php else: ?>
                                                        <button class="btn btn-sm btn-danger" disabled title="امکان حذف وجود ندارد - لایسنس‌های فعال">
                                                            حذف
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <div class="empty-icon">📦</div>
                            <h3>هیچ محصولی ثبت نشده است</h3>
                            <p>هنوز هیچ محصولی در سیستم ایجاد نشده است.</p>
                            <a href="add_product.php" class="btn btn-primary">افزودن محصول جدید</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- آمار دقیق‌تر -->
            <div class="content-grid">
                <div class="content-card">
                    <div class="card-header">
                        <h3>توزیع لایسنس‌ها بر اساس محصول</h3>
                    </div>
                    <div class="card-body">
                        <?php if (count($products) > 0): ?>
                            <div class="product-stats">
                                <?php foreach ($products as $product): ?>
                                    <?php if ($product['total_licenses'] > 0): ?>
                                        <div class="stat-item">
                                            <div class="stat-header">
                                                <span class="product-name"><?php echo htmlspecialchars($product['name']); ?></span>
                                                <span class="stat-value"><?php echo $product['total_licenses']; ?> لایسنس</span>
                                            </div>
                                            <div class="progress-bar">
                                                <div class="progress-fill" style="width: <?php echo min(100, ($product['total_licenses'] / max(1, $stats['total_licenses'])) * 100); ?>%"></div>
                                            </div>
                                            <div class="stat-details">
                                                <span class="text-success"><?php echo $product['active_licenses']; ?> فعال</span>
                                                <span class="text-muted">•</span>
                                                <span class="text-warning"><?php echo $product['total_licenses'] - $product['active_licenses']; ?> غیرفعال</span>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <p class="text-muted">هیچ لایسنس‌ی برای نمایش وجود ندارد.</p>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="content-card">
                    <div class="card-header">
                        <h3>وضعیت محصولات</h3>
                    </div>
                    <div class="card-body">
                        <div class="status-chart">
                            <div class="chart-item">
                                <div class="chart-label">محصولات فعال</div>
                                <div class="chart-value"><?php echo $stats['active_products']; ?></div>
                                <div class="chart-bar">
                                    <div class="chart-fill" style="width: <?php echo ($stats['active_products'] / max(1, $stats['total_products'])) * 100; ?>%; background-color: #1cc88a;"></div>
                                </div>
                            </div>
                            <div class="chart-item">
                                <div class="chart-label">محصولات غیرفعال</div>
                                <div class="chart-value"><?php echo $stats['total_products'] - $stats['active_products']; ?></div>
                                <div class="chart-bar">
                                    <div class="chart-fill" style="width: <?php echo (($stats['total_products'] - $stats['active_products']) / max(1, $stats['total_products'])) * 100; ?>%; background-color: #e74a3b;"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
    
    <?php include '../templates/partials/footer.php'; ?>
    
    <script src="../assets/js/admin.js"></script>
</body>
</html>