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

// تنظیمات صفحه‌بندی
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

// پارامترهای فیلتر
$search = isset($_GET['search']) ? sanitize_input($_GET['search']) : '';
$status = isset($_GET['status']) ? sanitize_input($_GET['status']) : '';
$product_id = isset($_GET['product_id']) ? intval($_GET['product_id']) : '';
$user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : '';
$date_from = isset($_GET['date_from']) ? sanitize_input($_GET['date_from']) : '';
$date_to = isset($_GET['date_to']) ? sanitize_input($_GET['date_to']) : '';

// ساخت شرط‌های WHERE
$where_conditions = [];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(l.license_key LIKE :search OR u.name LIKE :search OR u.email LIKE :search OR p.name LIKE :search)";
    $params[':search'] = "%$search%";
}

if (!empty($status)) {
    $where_conditions[] = "l.status = :status";
    $params[':status'] = $status;
}

if (!empty($product_id)) {
    $where_conditions[] = "l.product_id = :product_id";
    $params[':product_id'] = $product_id;
}

if (!empty($user_id)) {
    $where_conditions[] = "l.user_id = :user_id";
    $params[':user_id'] = $user_id;
}

if (!empty($date_from)) {
    $where_conditions[] = "DATE(l.created_at) >= :date_from";
    $params[':date_from'] = $date_from;
}

if (!empty($date_to)) {
    $where_conditions[] = "DATE(l.created_at) <= :date_to";
    $params[':date_to'] = $date_to;
}

$where_clause = $where_conditions ? "WHERE " . implode(" AND ", $where_conditions) : "";

// دریافت لایسنس‌ها
try {
    $query = "SELECT SQL_CALC_FOUND_ROWS 
                     l.*, 
                     u.name as user_name, 
                     u.email as user_email,
                     p.name as product_name,
                     p.version as product_version,
                     (SELECT COUNT(*) FROM license_activations WHERE license_id = l.id) as activation_count
              FROM licenses l
              JOIN users u ON l.user_id = u.id
              JOIN products p ON l.product_id = p.id
              $where_clause
              ORDER BY l.created_at DESC
              LIMIT :limit OFFSET :offset";
    
    $stmt = $db->prepare($query);
    
    // Bind parameters
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    
    $stmt->bindValue(':limit', $per_page, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    
    $licenses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // دریافت تعداد کل رکوردها
    $total_stmt = $db->query("SELECT FOUND_ROWS()");
    $total_records = $total_stmt->fetchColumn();
    $total_pages = ceil($total_records / $per_page);
    
} catch (PDOException $e) {
    error_log("Licenses error: " . $e->getMessage());
    $licenses = [];
    $total_records = 0;
    $total_pages = 1;
}

// دریافت لیست محصولات برای فیلتر
$products = [];
try {
    $product_query = "SELECT id, name FROM products WHERE status = 1 ORDER BY name";
    $product_stmt = $db->prepare($product_query);
    $product_stmt->execute();
    $products = $product_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Products list error: " . $e->getMessage());
}

// دریافت لیست کاربران برای فیلتر
$users = [];
try {
    $user_query = "SELECT id, name, email FROM users WHERE status = 1 ORDER BY name";
    $user_stmt = $db->prepare($user_query);
    $user_stmt->execute();
    $users = $user_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Users list error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="fa">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>مدیریت لایسنس‌ها - سیستم لایسنس</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <?php include '../templates/partials/header.php'; ?>
    
    <div class="admin-container">
        <?php include '../templates/partials/sidebar.php'; ?>
        
        <main class="main-content">
            <div class="page-header">
                <h1>مدیریت لایسنس‌ها</h1>
                <div class="header-actions">
                    <button class="btn btn-primary" onclick="showGenerateLicenseModal()">ایجاد لایسنس جدید</button>
                    <button class="btn btn-secondary" onclick="exportLicenses()">خروجی Excel</button>
                </div>
            </div>
            
            <!-- فیلترها -->
            <div class="filters-card">
                <h3>فیلترها</h3>
                <form method="GET" action="" class="filter-form">
                    <div class="filter-grid">
                        <div class="form-group">
                            <label for="search">جستجو:</label>
                            <input type="text" id="search" name="search" value="<?php echo $search; ?>" 
                                   placeholder="جستجوی لایسنس، کاربر یا محصول">
                        </div>
                        
                        <div class="form-group">
                            <label for="status">وضعیت:</label>
                            <select id="status" name="status">
                                <option value="">همه وضعیت‌ها</option>
                                <option value="active" <?php echo $status == 'active' ? 'selected' : ''; ?>>فعال</option>
                                <option value="suspended" <?php echo $status == 'suspended' ? 'selected' : ''; ?>>معلق</option>
                                <option value="revoked" <?php echo $status == 'revoked' ? 'selected' : ''; ?>>لغو شده</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="product_id">محصول:</label>
                            <select id="product_id" name="product_id">
                                <option value="">همه محصولات</option>
                                <?php foreach ($products as $product): ?>
                                    <option value="<?php echo $product['id']; ?>" 
                                        <?php echo $product_id == $product['id'] ? 'selected' : ''; ?>>
                                        <?php echo $product['name']; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="user_id">کاربر:</label>
                            <select id="user_id" name="user_id">
                                <option value="">همه کاربران</option>
                                <?php foreach ($users as $user): ?>
                                    <option value="<?php echo $user['id']; ?>" 
                                        <?php echo $user_id == $user['id'] ? 'selected' : ''; ?>>
                                        <?php echo $user['name'] . ' (' . $user['email'] . ')'; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="date_from">از تاریخ:</label>
                            <input type="date" id="date_from" name="date_from" value="<?php echo $date_from; ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="date_to">تا تاریخ:</label>
                            <input type="date" id="date_to" name="date_to" value="<?php echo $date_to; ?>">
                        </div>
                    </div>
                    
                    <div class="filter-actions">
                        <button type="submit" class="btn btn-primary">اعمال فیلتر</button>
                        <a href="licenses.php" class="btn btn-secondary">پاک کردن فیلترها</a>
                    </div>
                </form>
            </div>
            
            <!-- آمار -->
            <div class="stats-row">
                <div class="stat-item">
                    <span class="stat-number"><?php echo number_format($total_records); ?></span>
                    <span class="stat-label">تعداد کل لایسنس‌ها</span>
                </div>
                <div class="stat-item">
                    <span class="stat-number"><?php echo $per_page; ?></span>
                    <span class="stat-label">رکورد در هر صفحه</span>
                </div>
                <div class="stat-item">
                    <span class="stat-number"><?php echo $page; ?></span>
                    <span class="stat-label">صفحه فعلی</span>
                </div>
            </div>
            
            <!-- جدول لایسنس‌ها -->
            <div class="card">
                <div class="card-header">
                    <h2>لیست لایسنس‌ها</h2>
                </div>
                
                <div class="card-body">
                    <?php if (count($licenses) > 0): ?>
                        <div class="table-responsive">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>کلید لایسنس</th>
                                        <th>کاربر</th>
                                        <th>محصول</th>
                                        <th>تاریخ ایجاد</th>
                                        <th>تاریخ انقضا</th>
                                        <th>فعال‌سازی‌ها</th>
                                        <th>وضعیت</th>
                                        <th>عملیات</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($licenses as $license): ?>
                                        <tr>
                                            <td>
                                                <code class="license-key"><?php echo $license['license_key']; ?></code>
                                                <button class="btn-copy" onclick="copyToClipboard('<?php echo $license['license_key']; ?>')" title="کپی">
                                                    📋
                                                </button>
                                            </td>
                                            <td>
                                                <div class="user-info">
                                                    <strong><?php echo $license['user_name']; ?></strong>
                                                    <br>
                                                    <small><?php echo $license['user_email']; ?></small>
                                                </div>
                                            </td>
                                            <td>
                                                <?php echo $license['product_name']; ?>
                                                <br>
                                                <small>نسخه <?php echo $license['product_version']; ?></small>
                                            </td>
                                            <td>
                                                <span class="text-nowrap"><?php echo toPersianDate($license['created_at'], 'Y/m/d'); ?></span>
                                            </td>
                                            <td>
                                                <span class="text-nowrap <?php echo isLicenseExpiring($license['expiry_date']) ? 'text-warning' : ''; ?>">
                                                    <?php echo toPersianDate($license['expiry_date'], 'Y/m/d'); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="activation-count">
                                                    <?php echo $license['activation_count']; ?> / <?php echo $license['max_activations']; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="status-badge status-<?php echo $license['status']; ?>">
                                                    <?php echo $license['status']; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="action-buttons">
                                                    <button class="btn btn-sm btn-info" 
                                                            onclick="viewLicenseDetails(<?php echo $license['id']; ?>)">
                                                        مشاهده
                                                    </button>
                                                    <button class="btn btn-sm btn-warning" 
                                                            onclick="editLicense(<?php echo $license['id']; ?>)">
                                                        ویرایش
                                                    </button>
                                                    <?php if ($license['status'] == 'active'): ?>
                                                        <button class="btn btn-sm btn-danger" 
                                                                onclick="revokeLicense(<?php echo $license['id']; ?>)">
                                                            لغو
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- صفحه‌بندی -->
                        <?php if ($total_pages > 1): ?>
                            <div class="pagination">
                                <?php if ($page > 1): ?>
                                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => 1])); ?>" class="page-link">
                                        اولین
                                    </a>
                                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>" class="page-link">
                                        قبلی
                                    </a>
                                <?php endif; ?>
                                
                                <?php
                                $start_page = max(1, $page - 2);
                                $end_page = min($total_pages, $start_page + 4);
                                $start_page = max(1, $end_page - 4);
                                
                                for ($i = $start_page; $i <= $end_page; $i++):
                                ?>
                                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>" 
                                       class="page-link <?php echo $i == $page ? 'active' : ''; ?>">
                                        <?php echo $i; ?>
                                    </a>
                                <?php endfor; ?>
                                
                                <?php if ($page < $total_pages): ?>
                                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>" class="page-link">
                                        بعدی
                                    </a>
                                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $total_pages])); ?>" class="page-link">
                                        آخرین
                                    </a>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                        
                    <?php else: ?>
                        <div class="empty-state">
                            <div class="empty-icon">🔑</div>
                            <h3>هیچ لایسنس‌ی یافت نشد</h3>
                            <p>هیچ لایسنس‌ی مطابق با فیلترهای شما وجود ندارد.</p>
                            <button class="btn btn-primary" onclick="showGenerateLicenseModal()">ایجاد لایسنس جدید</button>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
    
    <!-- مودال ایجاد لایسنس جدید -->
    <div id="generateLicenseModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>ایجاد لایسنس جدید</h3>
                <span class="close" onclick="closeModal('generateLicenseModal')">&times;</span>
            </div>
            <div class="modal-body">
                <form id="generateLicenseForm">
                    <div class="form-group">
                        <label for="modal_product_id">محصول *</label>
                        <select id="modal_product_id" name="product_id" required>
                            <option value="">انتخاب محصول</option>
                            <?php foreach ($products as $product): ?>
                                <option value="<?php echo $product['id']; ?>"><?php echo $product['name']; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="modal_user_id">کاربر *</label>
                        <select id="modal_user_id" name="user_id" required>
                            <option value="">انتخاب کاربر</option>
                            <?php foreach ($users as $user): ?>
                                <option value="<?php echo $user['id']; ?>">
                                    <?php echo $user['name'] . ' (' . $user['email'] . ')'; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="expiry_days">مدت اعتبار (روز) *</label>
                        <input type="number" id="expiry_days" name="expiry_days" value="365" min="1" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="max_activations">حداکثر فعال‌سازی *</label>
                        <input type="number" id="max_activations" name="max_activations" value="3" min="1" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="notes">یادداشت (اختیاری)</label>
                        <textarea id="notes" name="notes" rows="3" placeholder="یادداشت درباره این لایسنس"></textarea>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">ایجاد لایسنس</button>
                        <button type="button" class="btn btn-secondary" onclick="closeModal('generateLicenseModal')">انصراف</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="../assets/js/admin.js"></script>
    <script>
    // نمایش مودال ایجاد لایسنس
    function showGenerateLicenseModal() {
        document.getElementById('generateLicenseModal').style.display = 'block';
    }
    
    // مدیریت فرم ایجاد لایسنس
    document.getElementById('generateLicenseForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        
        fetch('../api/generate_license.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('لایسنس با موفقیت ایجاد شد: ' + data.license_key);
                closeModal('generateLicenseModal');
                location.reload();
            } else {
                alert('خطا: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('خطا در ایجاد لایسنس');
        });
    });
    
    // مشاهده جزئیات لایسنس
    function viewLicenseDetails(licenseId) {
        window.open(`license_details.php?id=${licenseId}`, '_blank');
    }
    
    // ویرایش لایسنس
    function editLicense(licenseId) {
        window.open(`edit_license.php?id=${licenseId}`, '_blank');
    }
    
    // لغو لایسنس
    function revokeLicense(licenseId) {
        if (confirm('آیا از لغو این لایسنس اطمینان دارید؟ این عمل غیرقابل بازگشت است.')) {
            fetch('../api/revoke_license.php', {
                method: 'POST',
                body: new URLSearchParams({ license_id: licenseId })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('لایسنس با موفقیت لغو شد');
                    location.reload();
                } else {
                    alert('خطا: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('خطا در لغو لایسنس');
            });
        }
    }
    
    // خروجی Excel
    function exportLicenses() {
        const params = new URLSearchParams(window.location.search);
        window.open(`../api/export_licenses.php?${params.toString()}`, '_blank');
    }
    </script>
</body>
</html>