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

// حذف کاربر
if (isset($_POST['delete_user'])) {
    $user_id = intval($_POST['user_id']);
    
    try {
        // بررسی وجود لایسنس‌های فعال برای کاربر
        $check_query = "SELECT COUNT(*) as count FROM licenses WHERE user_id = :user_id AND status = 'active'";
        $check_stmt = $db->prepare($check_query);
        $check_stmt->bindParam(':user_id', $user_id);
        $check_stmt->execute();
        $active_licenses = $check_stmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        if ($active_licenses > 0) {
            $error = 'امکان حذف کاربر وجود ندارد زیرا لایسنس‌های فعالی دارد.';
        } else {
            $delete_query = "UPDATE users SET status = 0 WHERE id = :user_id";
            $delete_stmt = $db->prepare($delete_query);
            $delete_stmt->bindParam(':user_id', $user_id);
            
            if ($delete_stmt->execute()) {
                $message = 'کاربر با موفقیت حذف شد.';
                logActivity($db, $_SESSION['user_id'], 'user_deleted', 'حذف کاربر - ID: ' . $user_id);
            } else {
                $error = 'خطا در حذف کاربر.';
            }
        }
    } catch (PDOException $e) {
        error_log("Delete user error: " . $e->getMessage());
        $error = 'خطا در حذف کاربر.';
    }
}

// فعال/غیرفعال کردن کاربر
if (isset($_POST['toggle_status'])) {
    $user_id = intval($_POST['user_id']);
    
    try {
        $query = "UPDATE users SET status = NOT status WHERE id = :user_id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        
        if ($stmt->execute()) {
            $message = 'وضعیت کاربر با موفقیت تغییر کرد.';
            logActivity($db, $_SESSION['user_id'], 'user_status_changed', 'تغییر وضعیت کاربر - ID: ' . $user_id);
        } else {
            $error = 'خطا در تغییر وضعیت کاربر.';
        }
    } catch (PDOException $e) {
        error_log("Toggle user status error: " . $e->getMessage());
        $error = 'خطا در تغییر وضعیت کاربر.';
    }
}

// تغییر نقش کاربر
if (isset($_POST['change_role'])) {
    $user_id = intval($_POST['user_id']);
    $new_role = sanitize_input($_POST['new_role']);
    
    try {
        $query = "UPDATE users SET role = :role WHERE id = :user_id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':role', $new_role);
        $stmt->bindParam(':user_id', $user_id);
        
        if ($stmt->execute()) {
            $message = 'نقش کاربر با موفقیت تغییر کرد.';
            logActivity($db, $_SESSION['user_id'], 'user_role_changed', 'تغییر نقش کاربر - ID: ' . $user_id . ' به: ' . $new_role);
        } else {
            $error = 'خطا در تغییر نقش کاربر.';
        }
    } catch (PDOException $e) {
        error_log("Change user role error: " . $e->getMessage());
        $error = 'خطا در تغییر نقش کاربر.';
    }
}

// تنظیمات صفحه‌بندی
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

// پارامترهای فیلتر
$search = isset($_GET['search']) ? sanitize_input($_GET['search']) : '';
$status = isset($_GET['status']) ? sanitize_input($_GET['status']) : '';
$role = isset($_GET['role']) ? sanitize_input($_GET['role']) : '';
$date_from = isset($_GET['date_from']) ? sanitize_input($_GET['date_from']) : '';
$date_to = isset($_GET['date_to']) ? sanitize_input($_GET['date_to']) : '';

// ساخت شرط‌های WHERE
$where_conditions = [];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(u.name LIKE :search OR u.email LIKE :search)";
    $params[':search'] = "%$search%";
}

if (!empty($status)) {
    if ($status === 'active') {
        $where_conditions[] = "u.status = 1";
    } elseif ($status === 'inactive') {
        $where_conditions[] = "u.status = 0";
    }
}

if (!empty($role)) {
    $where_conditions[] = "u.role = :role";
    $params[':role'] = $role;
}

if (!empty($date_from)) {
    $where_conditions[] = "DATE(u.created_at) >= :date_from";
    $params[':date_from'] = $date_from;
}

if (!empty($date_to)) {
    $where_conditions[] = "DATE(u.created_at) <= :date_to";
    $params[':date_to'] = $date_to;
}

$where_clause = $where_conditions ? "WHERE " . implode(" AND ", $where_conditions) : "";

// دریافت کاربران
try {
    $query = "SELECT SQL_CALC_FOUND_ROWS 
                     u.*,
                     COUNT(l.id) as total_licenses,
                     COUNT(CASE WHEN l.status = 'active' THEN 1 END) as active_licenses,
                     COUNT(DISTINCT la.id) as total_activations
              FROM users u
              LEFT JOIN licenses l ON u.id = l.user_id
              LEFT JOIN license_activations la ON l.id = la.license_id
              $where_clause
              GROUP BY u.id
              ORDER BY u.created_at DESC
              LIMIT :limit OFFSET :offset";
    
    $stmt = $db->prepare($query);
    
    // Bind parameters
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    
    $stmt->bindValue(':limit', $per_page, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // دریافت تعداد کل رکوردها
    $total_stmt = $db->query("SELECT FOUND_ROWS()");
    $total_records = $total_stmt->fetchColumn();
    $total_pages = ceil($total_records / $per_page);
    
} catch (PDOException $e) {
    error_log("Users error: " . $e->getMessage());
    $users = [];
    $total_records = 0;
    $total_pages = 1;
}

// آمار کاربران
$stats = [
    'total_users' => 0,
    'active_users' => 0,
    'admin_users' => 0,
    'manager_users' => 0,
    'regular_users' => 0
];

foreach ($users as $user) {
    $stats['total_users']++;
    if ($user['status']) {
        $stats['active_users']++;
    }
    if ($user['role'] === 'admin') {
        $stats['admin_users']++;
    } elseif ($user['role'] === 'manager') {
        $stats['manager_users']++;
    } else {
        $stats['regular_users']++;
    }
}
?>

<!DOCTYPE html>
<html lang="fa">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>مدیریت کاربران - سیستم لایسنس</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <?php include '../templates/partials/header.php'; ?>
    
    <div class="admin-container">
        <?php include '../templates/partials/sidebar.php'; ?>
        
        <main class="main-content">
            <div class="page-header">
                <h1>مدیریت کاربران</h1>
                <div class="header-actions">
                    <a href="add_user.php" class="btn btn-primary">افزودن کاربر جدید</a>
                    <button class="btn btn-secondary" onclick="exportUsers()">خروجی Excel</button>
                </div>
            </div>

            <!-- نمایش پیام‌ها -->
            <?php if ($message): ?>
                <div class="alert alert-success"><?php echo $message; ?></div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>

            <!-- آمار کاربران -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon" style="background-color: #4e73df;">
                        <i class="icon-users"></i>
                    </div>
                    <div class="stat-info">
                        <h3>کاربران کل</h3>
                        <p class="stat-number"><?php echo number_format($stats['total_users']); ?></p>
                        <span class="stat-subtext"><?php echo number_format($stats['active_users']); ?> فعال</span>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon" style="background-color: #1cc88a;">
                        <i class="icon-admin"></i>
                    </div>
                    <div class="stat-info">
                        <h3>مدیران سیستم</h3>
                        <p class="stat-number"><?php echo number_format($stats['admin_users']); ?></p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon" style="background-color: #36b9cc;">
                        <i class="icon-manager"></i>
                    </div>
                    <div class="stat-info">
                        <h3>مدیران</h3>
                        <p class="stat-number"><?php echo number_format($stats['manager_users']); ?></p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon" style="background-color: #f6c23e;">
                        <i class="icon-user"></i>
                    </div>
                    <div class="stat-info">
                        <h3>کاربران عادی</h3>
                        <p class="stat-number"><?php echo number_format($stats['regular_users']); ?></p>
                    </div>
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
                                   placeholder="جستجوی نام یا ایمیل کاربر">
                        </div>
                        
                        <div class="form-group">
                            <label for="status">وضعیت:</label>
                            <select id="status" name="status">
                                <option value="">همه وضعیت‌ها</option>
                                <option value="active" <?php echo $status == 'active' ? 'selected' : ''; ?>>فعال</option>
                                <option value="inactive" <?php echo $status == 'inactive' ? 'selected' : ''; ?>>غیرفعال</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="role">نقش:</label>
                            <select id="role" name="role">
                                <option value="">همه نقش‌ها</option>
                                <option value="admin" <?php echo $role == 'admin' ? 'selected' : ''; ?>>مدیر سیستم</option>
                                <option value="manager" <?php echo $role == 'manager' ? 'selected' : ''; ?>>مدیر</option>
                                <option value="user" <?php echo $role == 'user' ? 'selected' : ''; ?>>کاربر عادی</option>
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
                        <a href="users.php" class="btn btn-secondary">پاک کردن فیلترها</a>
                    </div>
                </form>
            </div>

            <!-- جدول کاربران -->
            <div class="card">
                <div class="card-header">
                    <h2>لیست کاربران</h2>
                    <span class="badge badge-info"><?php echo number_format($total_records); ?> کاربر</span>
                </div>
                
                <div class="card-body">
                    <?php if (count($users) > 0): ?>
                        <div class="table-responsive">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>نام کاربر</th>
                                        <th>ایمیل</th>
                                        <th>نقش</th>
                                        <th>لایسنس‌ها</th>
                                        <th>تاریخ عضویت</th>
                                        <th>آخرین ورود</th>
                                        <th>وضعیت</th>
                                        <th>عملیات</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($users as $user): ?>
                                        <tr>
                                            <td>
                                                <div class="user-info">
                                                    <strong><?php echo htmlspecialchars($user['name']); ?></strong>
                                                    <?php if ($user['id'] == $_SESSION['user_id']): ?>
                                                        <span class="badge badge-primary">شما</span>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td>
                                                <?php echo htmlspecialchars($user['email']); ?>
                                            </td>
                                            <td>
                                                <span class="role-badge role-<?php echo $user['role']; ?>">
                                                    <?php 
                                                    $role_names = [
                                                        'admin' => 'مدیر سیستم',
                                                        'manager' => 'مدیر',
                                                        'user' => 'کاربر عادی'
                                                    ];
                                                    echo $role_names[$user['role']] ?? $user['role'];
                                                    ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="license-stats">
                                                    <span class="text-success"><?php echo $user['active_licenses']; ?> فعال</span>
                                                    <span class="text-muted">/ <?php echo $user['total_licenses']; ?> کل</span>
                                                    <br>
                                                    <small><?php echo $user['total_activations']; ?> فعال‌سازی</small>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="text-nowrap"><?php echo toPersianDate($user['created_at'], 'Y/m/d'); ?></span>
                                            </td>
                                            <td>
                                                <?php if ($user['last_login']): ?>
                                                    <span class="text-nowrap"><?php echo toPersianDate($user['last_login'], 'Y/m/d'); ?></span>
                                                    <br>
                                                    <small class="text-muted"><?php echo date('H:i', strtotime($user['last_login'])); ?></small>
                                                <?php else: ?>
                                                    <span class="text-muted">هنوز وارد نشده</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="status-badge status-<?php echo $user['status'] ? 'active' : 'inactive'; ?>">
                                                    <?php echo $user['status'] ? 'فعال' : 'غیرفعال'; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="action-buttons">
                                                    <a href="user_details.php?id=<?php echo $user['id']; ?>" class="btn btn-sm btn-info">
                                                        مشاهده
                                                    </a>
                                                    
                                                    <a href="edit_user.php?id=<?php echo $user['id']; ?>" class="btn btn-sm btn-primary">
                                                        ویرایش
                                                    </a>
                                                    
                                                    <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                                        <form method="POST" style="display: inline-block;">
                                                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                            <button type="submit" name="toggle_status" class="btn btn-sm btn-warning">
                                                                <?php echo $user['status'] ? 'غیرفعال' : 'فعال'; ?>
                                                            </button>
                                                        </form>
                                                        
                                                        <div class="dropdown" style="display: inline-block;">
                                                            <button class="btn btn-sm btn-secondary dropdown-toggle" type="button" data-toggle="dropdown">
                                                                تغییر نقش
                                                            </button>
                                                            <div class="dropdown-menu">
                                                                <form method="POST">
                                                                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                                    <button type="submit" name="change_role" value="admin" class="dropdown-item">
                                                                        مدیر سیستم
                                                                    </button>
                                                                    <button type="submit" name="change_role" value="manager" class="dropdown-item">
                                                                        مدیر
                                                                    </button>
                                                                    <button type="submit" name="change_role" value="user" class="dropdown-item">
                                                                        کاربر عادی
                                                                    </button>
                                                                </form>
                                                            </div>
                                                        </div>
                                                        
                                                        <?php if ($user['active_licenses'] == 0): ?>
                                                            <form method="POST" style="display: inline-block;" onsubmit="return confirm('آیا از حذف این کاربر اطمینان دارید؟');">
                                                                <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                                <button type="submit" name="delete_user" class="btn btn-sm btn-danger">
                                                                    حذف
                                                                </button>
                                                            </form>
                                                        <?php else: ?>
                                                            <button class="btn btn-sm btn-danger" disabled title="امکان حذف وجود ندارد - لایسنس‌های فعال">
                                                                حذف
                                                            </button>
                                                        <?php endif; ?>
                                                    <?php else: ?>
                                                        <button class="btn btn-sm btn-secondary" disabled>خودتان</button>
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
                            <div class="empty-icon">👥</div>
                            <h3>هیچ کاربری یافت نشد</h3>
                            <p>هیچ کاربری مطابق با فیلترهای شما وجود ندارد.</p>
                            <a href="add_user.php" class="btn btn-primary">افزودن کاربر جدید</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- آمار دقیق‌تر -->
            <div class="content-grid">
                <div class="content-card">
                    <div class="card-header">
                        <h3>توزیع کاربران بر اساس نقش</h3>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="roleChart" height="200"></canvas>
                        </div>
                    </div>
                </div>

                <div class="content-card">
                    <div class="card-header">
                        <h3>فعالیت کاربران</h3>
                    </div>
                    <div class="card-body">
                        <div class="activity-stats">
                            <div class="activity-item">
                                <span class="activity-label">کاربران فعال</span>
                                <span class="activity-value"><?php echo number_format($stats['active_users']); ?></span>
                                <div class="progress-bar">
                                    <div class="progress-fill" style="width: <?php echo ($stats['active_users'] / max(1, $stats['total_users'])) * 100; ?>%"></div>
                                </div>
                            </div>
                            <div class="activity-item">
                                <span class="activity-label">کاربران غیرفعال</span>
                                <span class="activity-value"><?php echo number_format($stats['total_users'] - $stats['active_users']); ?></span>
                                <div class="progress-bar">
                                    <div class="progress-fill" style="width: <?php echo (($stats['total_users'] - $stats['active_users']) / max(1, $stats['total_users'])) * 100; ?>%"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
    
    <?php include '../templates/partials/footer.php'; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="../assets/js/admin.js"></script>
    <script>
    // نمودار توزیع نقش‌ها
    const roleCtx = document.getElementById('roleChart').getContext('2d');
    new Chart(roleCtx, {
        type: 'doughnut',
        data: {
            labels: ['مدیران سیستم', 'مدیران', 'کاربران عادی'],
            datasets: [{
                data: [
                    <?php echo $stats['admin_users']; ?>,
                    <?php echo $stats['manager_users']; ?>,
                    <?php echo $stats['regular_users']; ?>
                ],
                backgroundColor: [
                    '#1cc88a',
                    '#36b9cc',
                    '#f6c23e'
                ]
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'bottom'
                }
            }
        }
    });

    // خروجی Excel
    function exportUsers() {
        const params = new URLSearchParams(window.location.search);
        window.open(`../api/export_users.php?${params.toString()}`, '_blank');
    }

    // مدیریت dropdown
    document.querySelectorAll('.dropdown-toggle').forEach(toggle => {
        toggle.addEventListener('click', function(e) {
            e.preventDefault();
            const dropdown = this.nextElementSibling;
            dropdown.style.display = dropdown.style.display === 'block' ? 'none' : 'block';
        });
    });

    // بستن dropdown هنگام کلیک خارج
    document.addEventListener('click', function(e) {
        if (!e.target.matches('.dropdown-toggle')) {
            document.querySelectorAll('.dropdown-menu').forEach(menu => {
                menu.style.display = 'none';
            });
        }
    });
    </script>
</body>
</html>