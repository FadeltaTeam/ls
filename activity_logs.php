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
$user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : null;
$action = isset($_GET['action']) ? sanitize_input($_GET['action']) : null;
$date_from = isset($_GET['date_from']) ? sanitize_input($_GET['date_from']) : null;
$date_to = isset($_GET['date_to']) ? sanitize_input($_GET['date_to']) : null;
$ip_address = isset($_GET['ip_address']) ? sanitize_input($_GET['ip_address']) : null;

// ساخت شرط‌های WHERE
$where_conditions = [];
$params = [];

if ($user_id) {
    $where_conditions[] = "al.user_id = :user_id";
    $params[':user_id'] = $user_id;
}

if ($action) {
    $where_conditions[] = "al.action LIKE :action";
    $params[':action'] = "%$action%";
}

if ($date_from) {
    $where_conditions[] = "DATE(al.created_at) >= :date_from";
    $params[':date_from'] = $date_from;
}

if ($date_to) {
    $where_conditions[] = "DATE(al.created_at) <= :date_to";
    $params[':date_to'] = $date_to;
}

if ($ip_address) {
    $where_conditions[] = "al.ip_address LIKE :ip_address";
    $params[':ip_address'] = "%$ip_address%";
}

$where_clause = $where_conditions ? "WHERE " . implode(" AND ", $where_conditions) : "";

// دریافت لاگ‌های فعالیت
try {
    // Query اصلی برای دریافت لاگ‌ها
    $query = "SELECT SQL_CALC_FOUND_ROWS 
                     al.*, 
                     u.name as user_name, 
                     u.email as user_email
              FROM activity_logs al
              LEFT JOIN users u ON al.user_id = u.id
              $where_clause
              ORDER BY al.created_at DESC
              LIMIT :limit OFFSET :offset";
    
    $stmt = $db->prepare($query);
    
    // Bind parameters
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    
    $stmt->bindValue(':limit', $per_page, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    
    $activity_logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // دریافت تعداد کل رکوردها
    $total_stmt = $db->query("SELECT FOUND_ROWS()");
    $total_records = $total_stmt->fetchColumn();
    $total_pages = ceil($total_records / $per_page);
    
} catch (PDOException $e) {
    error_log("Activity logs error: " . $e->getMessage());
    $activity_logs = [];
    $total_records = 0;
    $total_pages = 1;
}

// دریافت لیست کاربران برای فیلتر
$users = [];
try {
    $user_query = "SELECT id, name, email FROM users ORDER BY name";
    $user_stmt = $db->prepare($user_query);
    $user_stmt->execute();
    $users = $user_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Users list error: " . $e->getMessage());
}

// دریافت انواع action‌های موجود
$action_types = [];
try {
    $action_query = "SELECT DISTINCT action FROM activity_logs ORDER BY action";
    $action_stmt = $db->prepare($action_query);
    $action_stmt->execute();
    $action_types = $action_stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    error_log("Action types error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="fa">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>لاگ فعالیت‌ها - سیستم لایسنس</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <?php include '../templates/partials/header.php'; ?>
    
    <div class="admin-container">
        <?php include '../templates/partials/sidebar.php'; ?>
        
        <main class="main-content">
            <div class="page-header">
                <h1>لاگ فعالیت‌های سیستم</h1>
                <div class="header-actions">
                    <button class="btn btn-secondary" onclick="exportLogs()">خروجی Excel</button>
                    <button class="btn btn-danger" onclick="clearOldLogs()">پاک‌سازی لاگ‌های قدیمی</button>
                </div>
            </div>
            
            <!-- فیلترها -->
            <div class="filters-card">
                <h3>فیلترها</h3>
                <form method="GET" action="" class="filter-form">
                    <div class="filter-grid">
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
                            <label for="action">نوع فعالیت:</label>
                            <select id="action" name="action">
                                <option value="">همه فعالیت‌ها</option>
                                <?php foreach ($action_types as $action_type): ?>
                                    <option value="<?php echo $action_type; ?>" 
                                        <?php echo $action == $action_type ? 'selected' : ''; ?>>
                                        <?php echo $action_type; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="date_from">از تاریخ:</label>
                            <input type="date" id="date_from" name="date_from" 
                                   value="<?php echo $date_from; ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="date_to">تا تاریخ:</label>
                            <input type="date" id="date_to" name="date_to" 
                                   value="<?php echo $date_to; ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="ip_address">آی‌پی:</label>
                            <input type="text" id="ip_address" name="ip_address" 
                                   value="<?php echo $ip_address; ?>" placeholder="جستجوی آی‌پی">
                        </div>
                    </div>
                    
                    <div class="filter-actions">
                        <button type="submit" class="btn btn-primary">اعمال فیلتر</button>
                        <a href="activity_logs.php" class="btn btn-secondary">پاک کردن فیلترها</a>
                    </div>
                </form>
            </div>
            
            <!-- آمار -->
            <div class="stats-row">
                <div class="stat-item">
                    <span class="stat-number"><?php echo number_format($total_records); ?></span>
                    <span class="stat-label">تعداد کل رکوردها</span>
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
            
            <!-- جدول لاگ‌ها -->
            <div class="card">
                <div class="card-header">
                    <h2>لیست فعالیت‌ها</h2>
                </div>
                
                <div class="card-body">
                    <?php if (count($activity_logs) > 0): ?>
                        <div class="table-responsive">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>تاریخ و زمان</th>
                                        <th>کاربر</th>
                                        <th>نوع فعالیت</th>
                                        <th>جزئیات</th>
                                        <th>آی‌پی</th>
                                        <th>مرورگر</th>
                                        <th>عملیات</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($activity_logs as $log): ?>
                                        <tr>
                                            <td>
                                                <span class="text-nowrap"><?php echo toPersianDate($log['created_at'], 'Y/m/d'); ?></span>
                                                <br>
                                                <small class="text-muted"><?php echo date('H:i', strtotime($log['created_at'])); ?></small>
                                            </td>
                                            <td>
                                                <?php if ($log['user_id']): ?>
                                                    <div class="user-info">
                                                        <strong><?php echo $log['user_name']; ?></strong>
                                                        <br>
                                                        <small><?php echo $log['user_email']; ?></small>
                                                    </div>
                                                <?php else: ?>
                                                    <span class="text-muted">سیستم</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge badge-info"><?php echo $log['action']; ?></span>
                                            </td>
                                            <td>
                                                <div class="log-details">
                                                    <?php echo nl2br(htmlspecialchars($log['details'])); ?>
                                                </div>
                                            </td>
                                            <td>
                                                <code><?php echo $log['ip_address']; ?></code>
                                            </td>
                                            <td>
                                                <small class="text-muted" title="<?php echo $log['user_agent']; ?>">
                                                    <?php echo getBrowserName($log['user_agent']); ?>
                                                </small>
                                            </td>
                                            <td>
                                                <button class="btn btn-sm btn-info" 
                                                        onclick="viewLogDetails(<?php echo $log['id']; ?>)">
                                                    مشاهده
                                                </button>
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
                            <div class="empty-icon">📊</div>
                            <h3>هیچ فعالیتی ثبت نشده است</h3>
                            <p>هنوز هیچ فعالیتی در سیستم ثبت نشده است.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
    
    <!-- مودال مشاهده جزئیات -->
    <div id="logDetailsModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>جزئیات فعالیت</h3>
                <span class="close" onclick="closeModal('logDetailsModal')">&times;</span>
            </div>
            <div class="modal-body" id="logDetailsContent">
                <!-- محتوای جزئیات از طریق AJAX پر می‌شود -->
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeModal('logDetailsModal')">بستن</button>
            </div>
        </div>
    </div>
    
    <script src="../assets/js/admin.js"></script>
    <script>
    // مشاهده جزئیات لاگ
    function viewLogDetails(logId) {
        fetch(`../api/get_log_details.php?id=${logId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const log = data.log;
                    const content = `
                        <div class="log-detail-item">
                            <label>تاریخ و زمان:</label>
                            <span>${new Date(log.created_at).toLocaleString('fa-IR')}</span>
                        </div>
                        <div class="log-detail-item">
                            <label>کاربر:</label>
                            <span>${log.user_name || 'سیستم'} (${log.user_email || 'N/A'})</span>
                        </div>
                        <div class="log-detail-item">
                            <label>نوع فعالیت:</label>
                            <span class="badge badge-info">${log.action}</span>
                        </div>
                        <div class="log-detail-item">
                            <label>جزئیات:</label>
                            <div class="detail-content">${log.details}</div>
                        </div>
                        <div class="log-detail-item">
                            <label>آی‌پی:</label>
                            <code>${log.ip_address}</code>
                        </div>
                        <div class="log-detail-item">
                            <label>مرورگر:</label>
                            <span>${log.user_agent}</span>
                        </div>
                    `;
                    document.getElementById('logDetailsContent').innerHTML = content;
                    document.getElementById('logDetailsModal').style.display = 'block';
                } else {
                    alert('خطا در دریافت جزئیات');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('خطا در ارتباط با سرور');
            });
    }
    
    // خروجی Excel
    function exportLogs() {
        const params = new URLSearchParams(window.location.search);
        params.set('export', 'excel');
        window.location.href = `../api/export_logs.php?${params.toString()}`;
    }
    
    // پاک‌سازی لاگ‌های قدیمی
    function clearOldLogs() {
        if (confirm('آیا از پاک‌سازی لاگ‌های قدیمی تر از 90 روز اطمینان دارید؟ این عمل غیرقابل بازگشت است.')) {
            fetch('../api/clear_old_logs.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ days: 90 })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(data.message);
                    location.reload();
                } else {
                    alert('خطا در پاک‌سازی لاگ‌ها');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('خطا در ارتباط با سرور');
            });
        }
    }
    </script>
</body>
</html>