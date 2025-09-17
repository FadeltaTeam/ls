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

// تایید درخواست
if (isset($_POST['approve_request'])) {
    $request_id = intval($_POST['request_id']);
    
    try {
        // دریافت اطلاعات درخواست
        $query = "SELECT lr.*, u.name as user_name, u.email as user_email, p.name as product_name 
                 FROM license_requests lr
                 JOIN users u ON lr.user_id = u.id
                 JOIN products p ON lr.product_id = p.id
                 WHERE lr.id = :request_id";
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(':request_id', $request_id);
        $stmt->execute();
        $request = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($request) {
            // ایجاد لایسنس
            $licenseSystem = new License($db);
            $expiry_date = date('Y-m-d', strtotime("+{$request['duration']} months"));
            $license_key = $licenseSystem->generateLicense(
                $request['product_id'],
                $request['user_id'],
                $expiry_date,
                3 // max_activations
            );
            
            if ($license_key) {
                // بروزرسانی وضعیت درخواست
                $update_query = "UPDATE license_requests 
                                SET status = 'approved', 
                                    processed_at = NOW(), 
                                    processed_by = :admin_id 
                                WHERE id = :request_id";
                
                $update_stmt = $db->prepare($update_query);
                $update_stmt->bindParam(':request_id', $request_id);
                $update_stmt->bindParam(':admin_id', $_SESSION['user_id']);
                $update_stmt->execute();
                
                // ارسال ایمیل به کاربر
                $email_subject = "درخواست لایسنس شما تایید شد";
                $email_body = "
                    <h2>درخواست لایسنس شما تایید شد</h2>
                    <p>درخواست شما برای محصول <strong>{$request['product_name']}</strong> تایید شد.</p>
                    <p><strong>کلید لایسنس:</strong> {$license_key}</p>
                    <p><strong>مدت اعتبار:</strong> {$request['duration']} ماه</p>
                    <p><strong>تاریخ انقضا:</strong> " . toPersianDate($expiry_date) . "</p>
                ";
                
                sendEmail($request['user_email'], $email_subject, $email_body);
                
                $message = "درخواست با موفقیت تایید و لایسنس ایجاد شد.";
                logActivity($db, $_SESSION['user_id'], 'request_approved', 'تایید درخواست لایسنس - ID: ' . $request_id);
            }
        }
    } catch (PDOException $e) {
        error_log("Approve request error: " . $e->getMessage());
        $error = "خطا در تایید درخواست.";
    }
}

// رد درخواست
if (isset($_POST['reject_request'])) {
    $request_id = intval($_POST['request_id']);
    $reject_reason = sanitize_input($_POST['reject_reason']);
    
    try {
        // دریافت اطلاعات درخواست
        $query = "SELECT lr.*, u.name as user_name, u.email as user_email, p.name as product_name 
                 FROM license_requests lr
                 JOIN users u ON lr.user_id = u.id
                 JOIN products p ON lr.product_id = p.id
                 WHERE lr.id = :request_id";
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(':request_id', $request_id);
        $stmt->execute();
        $request = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($request) {
            // بروزرسانی وضعیت درخواست
            $update_query = "UPDATE license_requests 
                            SET status = 'rejected', 
                                processed_at = NOW(), 
                                processed_by = :admin_id,
                                reject_reason = :reject_reason
                            WHERE id = :request_id";
            
            $update_stmt = $db->prepare($update_query);
            $update_stmt->bindParam(':request_id', $request_id);
            $update_stmt->bindParam(':admin_id', $_SESSION['user_id']);
            $update_stmt->bindParam(':reject_reason', $reject_reason);
            $update_stmt->execute();
            
            // ارسال ایمیل به کاربر
            $email_subject = "درخواست لایسنس شما رد شد";
            $email_body = "
                <h2>درخواست لایسنس شما رد شد</h2>
                <p>متاسفانه درخواست شما برای محصول <strong>{$request['product_name']}</strong> رد شد.</p>
                <p><strong>دلیل رد:</strong> {$reject_reason}</p>
                <p>در صورت نیاز می‌توانید مجدداً درخواست دهید.</p>
            ";
            
            sendEmail($request['user_email'], $email_subject, $email_body);
            
            $message = "درخواست با موفقیت رد شد.";
            logActivity($db, $_SESSION['user_id'], 'request_rejected', 'رد درخواست لایسنس - ID: ' . $request_id);
        }
    } catch (PDOException $e) {
        error_log("Reject request error: " . $e->getMessage());
        $error = "خطا در رد درخواست.";
    }
}

// تنظیمات صفحه‌بندی
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

// پارامترهای فیلتر
$status = isset($_GET['status']) ? sanitize_input($_GET['status']) : 'pending';
$product_id = isset($_GET['product_id']) ? intval($_GET['product_id']) : '';
$user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : '';
$date_from = isset($_GET['date_from']) ? sanitize_input($_GET['date_from']) : '';
$date_to = isset($_GET['date_to']) ? sanitize_input($_GET['date_to']) : '';

// ساخت شرط‌های WHERE
$where_conditions = ["lr.status = :status"];
$params = [':status' => $status];

if (!empty($product_id)) {
    $where_conditions[] = "lr.product_id = :product_id";
    $params[':product_id'] = $product_id;
}

if (!empty($user_id)) {
    $where_conditions[] = "lr.user_id = :user_id";
    $params[':user_id'] = $user_id;
}

if (!empty($date_from)) {
    $where_conditions[] = "DATE(lr.created_at) >= :date_from";
    $params[':date_from'] = $date_from;
}

if (!empty($date_to)) {
    $where_conditions[] = "DATE(lr.created_at) <= :date_to";
    $params[':date_to'] = $date_to;
}

$where_clause = "WHERE " . implode(" AND ", $where_conditions);

// دریافت درخواست‌ها
try {
    $query = "SELECT SQL_CALC_FOUND_ROWS 
                     lr.*, 
                     u.name as user_name, 
                     u.email as user_email,
                     p.name as product_name,
                     p.version as product_version,
                     admin.name as processed_by_name
              FROM license_requests lr
              JOIN users u ON lr.user_id = u.id
              JOIN products p ON lr.product_id = p.id
              LEFT JOIN users admin ON lr.processed_by = admin.id
              $where_clause
              ORDER BY lr.created_at DESC
              LIMIT :limit OFFSET :offset";
    
    $stmt = $db->prepare($query);
    
    // Bind parameters
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    
    $stmt->bindValue(':limit', $per_page, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    
    $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // دریافت تعداد کل رکوردها
    $total_stmt = $db->query("SELECT FOUND_ROWS()");
    $total_records = $total_stmt->fetchColumn();
    $total_pages = ceil($total_records / $per_page);
    
} catch (PDOException $e) {
    error_log("Requests error: " . $e->getMessage());
    $requests = [];
    $total_records = 0;
    $total_pages = 1;
}

// آمار درخواست‌ها
$stats = [];
try {
    $stats_query = "SELECT 
        status,
        COUNT(*) as count
    FROM license_requests 
    GROUP BY status";
    
    $stats_stmt = $db->prepare($stats_query);
    $stats_stmt->execute();
    $stats_data = $stats_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($stats_data as $stat) {
        $stats[$stat['status']] = $stat['count'];
    }
    
} catch (PDOException $e) {
    error_log("Requests stats error: " . $e->getMessage());
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
?>

<!DOCTYPE html>
<html lang="fa">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>مدیریت درخواست‌ها - سیستم لایسنس</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <?php include '../templates/partials/header.php'; ?>
    
    <div class="admin-container">
        <?php include '../templates/partials/sidebar.php'; ?>
        
        <main class="main-content">
            <div class="page-header">
                <h1>مدیریت درخواست‌ها</h1>
                <div class="header-actions">
                    <button class="btn btn-secondary" onclick="exportRequests()">خروجی Excel</button>
                </div>
            </div>

            <!-- نمایش پیام‌ها -->
            <?php if ($message): ?>
                <div class="alert alert-success"><?php echo $message; ?></div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>

            <!-- آمار درخواست‌ها -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon" style="background-color: #f6c23e;">
                        <i class="icon-pending"></i>
                    </div>
                    <div class="stat-info">
                        <h3>در انتظار بررسی</h3>
                        <p class="stat-number"><?php echo number_format($stats['pending'] ?? 0); ?></p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon" style="background-color: #1cc88a;">
                        <i class="icon-approved"></i>
                    </div>
                    <div class="stat-info">
                        <h3>تایید شده</h3>
                        <p class="stat-number"><?php echo number_format($stats['approved'] ?? 0); ?></p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon" style="background-color: #e74a3b;">
                        <i class="icon-rejected"></i>
                    </div>
                    <div class="stat-info">
                        <h3>رد شده</h3>
                        <p class="stat-number"><?php echo number_format($stats['rejected'] ?? 0); ?></p>
                    </div>
                </div>
            </div>

            <!-- فیلترها -->
            <div class="filters-card">
                <h3>فیلترها</h3>
                <form method="GET" action="" class="filter-form">
                    <div class="filter-grid">
                        <div class="form-group">
                            <label for="status">وضعیت:</label>
                            <select id="status" name="status">
                                <option value="pending" <?php echo $status == 'pending' ? 'selected' : ''; ?>>در انتظار بررسی</option>
                                <option value="approved" <?php echo $status == 'approved' ? 'selected' : ''; ?>>تایید شده</option>
                                <option value="rejected" <?php echo $status == 'rejected' ? 'selected' : ''; ?>>رد شده</option>
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
                        <a href="requests.php" class="btn btn-secondary">پاک کردن فیلترها</a>
                    </div>
                </form>
            </div>

            <!-- جدول درخواست‌ها -->
            <div class="card">
                <div class="card-header">
                    <h2>لیست درخواست‌ها</h2>
                    <span class="badge badge-info"><?php echo number_format($total_records); ?> مورد</span>
                </div>
                
                <div class="card-body">
                    <?php if (count($requests) > 0): ?>
                        <div class="table-responsive">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>کاربر</th>
                                        <th>محصول</th>
                                        <th>مدت درخواستی</th>
                                        <th>هدف استفاده</th>
                                        <th>تاریخ درخواست</th>
                                        <th>وضعیت</th>
                                        <th>عملیات</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($requests as $request): ?>
                                        <tr>
                                            <td>
                                                <div class="user-info">
                                                    <strong><?php echo $request['user_name']; ?></strong>
                                                    <br>
                                                    <small><?php echo $request['user_email']; ?></small>
                                                </div>
                                            </td>
                                            <td>
                                                <?php echo $request['product_name']; ?>
                                                <br>
                                                <small>نسخه <?php echo $request['product_version']; ?></small>
                                            </td>
                                            <td>
                                                <span class="badge badge-info"><?php echo $request['duration']; ?> ماه</span>
                                            </td>
                                            <td>
                                                <div class="purpose-text">
                                                    <?php echo nl2br(htmlspecialchars(substr($request['purpose'], 0, 100))); ?>
                                                    <?php if (strlen($request['purpose']) > 100): ?>...<?php endif; ?>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="text-nowrap"><?php echo toPersianDate($request['created_at'], 'Y/m/d'); ?></span>
                                                <br>
                                                <small class="text-muted"><?php echo date('H:i', strtotime($request['created_at'])); ?></small>
                                            </td>
                                            <td>
                                                <span class="status-badge status-<?php echo $request['status']; ?>">
                                                    <?php echo $request['status']; ?>
                                                    <?php if ($request['processed_at']): ?>
                                                        <br>
                                                        <small>توسط <?php echo $request['processed_by_name']; ?></small>
                                                    <?php endif; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="action-buttons">
                                                    <button class="btn btn-sm btn-info" 
                                                            onclick="viewRequestDetails(<?php echo $request['id']; ?>)">
                                                        مشاهده
                                                    </button>
                                                    
                                                    <?php if ($request['status'] == 'pending'): ?>
                                                        <button class="btn btn-sm btn-success" 
                                                                onclick="approveRequest(<?php echo $request['id']; ?>)">
                                                            تایید
                                                        </button>
                                                        <button class="btn btn-sm btn-danger" 
                                                                onclick="rejectRequest(<?php echo $request['id']; ?>)">
                                                            رد
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
                            <div class="empty-icon">📋</div>
                            <h3>هیچ درخواستی یافت نشد</h3>
                            <p>هیچ درخواستی مطابق با فیلترهای شما وجود ندارد.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <!-- مودال رد درخواست -->
    <div id="rejectModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>رد درخواست</h3>
                <span class="close" onclick="closeModal('rejectModal')">&times;</span>
            </div>
            <div class="modal-body">
                <form id="rejectForm" method="POST">
                    <input type="hidden" name="request_id" id="reject_request_id">
                    <div class="form-group">
                        <label for="reject_reason">دلیل رد درخواست *</label>
                        <textarea id="reject_reason" name="reject_reason" rows="4" required 
                                  placeholder="لطفاً دلیل رد درخواست را توضیح دهید..."></textarea>
                    </div>
                    <div class="form-actions">
                        <button type="submit" name="reject_request" class="btn btn-danger">رد درخواست</button>
                        <button type="button" class="btn btn-secondary" onclick="closeModal('rejectModal')">انصراف</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="../assets/js/admin.js"></script>
    <script>
    // مشاهده جزئیات درخواست
    function viewRequestDetails(requestId) {
        window.open(`request_details.php?id=${requestId}`, '_blank');
    }

    // تایید درخواست
    function approveRequest(requestId) {
        if (confirm('آیا از تایید این درخواست اطمینان دارید؟ لایسنس جدید ایجاد خواهد شد.')) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.style.display = 'none';
            
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'request_id';
            input.value = requestId;
            
            const submit = document.createElement('input');
            submit.type = 'hidden';
            submit.name = 'approve_request';
            submit.value = '1';
            
            form.appendChild(input);
            form.appendChild(submit);
            document.body.appendChild(form);
            form.submit();
        }
    }

    // رد درخواست
    function rejectRequest(requestId) {
        document.getElementById('reject_request_id').value = requestId;
        document.getElementById('rejectModal').style.display = 'block';
    }

    // خروجی Excel
    function exportRequests() {
        const params = new URLSearchParams(window.location.search);
        window.open(`../api/export_requests.php?${params.toString()}`, '_blank');
    }

    // مدیریت فرم رد درخواست
    document.getElementById('rejectForm').addEventListener('submit', function(e) {
        const reason = document.getElementById('reject_reason').value.trim();
        if (!reason) {
            e.preventDefault();
            alert('لطفاً دلیل رد درخواست را وارد کنید.');
        }
    });
    </script>
</body>
</html>