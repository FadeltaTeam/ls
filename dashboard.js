// توابع مخصوص صفحه داشبورد

// مدیریت وضعیت لایسنس
function getStatusBadge(status) {
    const statuses = {
        'active': { text: 'فعال', class: 'status-active' },
        'suspended': { text: 'معلق', class: 'status-suspended' },
        'revoked': { text: 'لغو شده', class: 'status-revoked' }
    };
    
    const statusInfo = statuses[status] || { text: status, class: 'status-inactive' };
    return `<span class="status-badge ${statusInfo.class}">${statusInfo.text}</span>`;
}

// کپی کلید لایسنس
function copyLicenseKey(licenseKey) {
    navigator.clipboard.writeText(licenseKey).then(() => {
        showNotification('کلید لایسنس با موفقیت کپی شد', 'success');
    }).catch(err => {
        showNotification('خطا در کپی کردن کلید لایسنس', 'error');
    });
}

// مشاهده جزئیات لایسنس
function viewLicenseDetails(licenseId) {
    fetch(`api/get_license_details.php?id=${licenseId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showLicenseDetailsModal(data.license);
            } else {
                showNotification('خطا در دریافت اطلاعات لایسنس', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification('خطا در ارتباط با سرور', 'error');
        });
}

// نمایش مودال جزئیات لایسنس
function showLicenseDetailsModal(license) {
    const modal = document.createElement('div');
    modal.className = 'modal';
    modal.innerHTML = `
        <div class="modal-content">
            <span class="close" onclick="closeModal(this)">&times;</span>
            <h3>جزئیات لایسنس</h3>
            <div class="license-details-grid">
                <div class="detail-item">
                    <label>کلید لایسنس:</label>
                    <span class="license-key-value">${license.license_key}</span>
                    <button class="btn btn-small" onclick="copyLicenseKey('${license.license_key}')">کپی</button>
                </div>
                <div class="detail-item">
                    <label>محصول:</label>
                    <span>${license.product_name}</span>
                </div>
                <div class="detail-item">
                    <label>مالک:</label>
                    <span>${license.user_name}</span>
                </div>
                <div class="detail-item">
                    <label>تاریخ ایجاد:</label>
                    <span>${toPersianDate(license.created_at)}</span>
                </div>
                <div class="detail-item">
                    <label>تاریخ انقضا:</label>
                    <span>${toPersianDate(license.expiry_date)}</span>
                </div>
                <div class="detail-item">
                    <label>وضعیت:</label>
                    <span>${getStatusBadge(license.status)}</span>
                </div>
                <div class="detail-item">
                    <label>حداکثر فعال‌سازی:</label>
                    <span>${license.max_activations}</span>
                </div>
            </div>
            
            <h4>تاریخچه فعال‌سازی‌ها</h4>
            <div class="activations-list">
                ${license.activations && license.activations.length > 0 ? 
                 license.activations.map(activation => `
                    <div class="activation-item">
                        <span class="activation-domain">${activation.domain}</span>
                        <span class="activation-date">${toPersianDate(activation.activation_date)}</span>
                    </div>
                 `).join('') : 
                 '<p class="no-activations">هیچ فعال‌سازی ثبت نشده است</p>'}
            </div>
        </div>
    `;
    
    document.body.appendChild(modal);
    modal.style.display = 'block';
    
    // بستن مودال با کلیک خارج از آن
    modal.addEventListener('click', function(e) {
        if (e.target === modal) {
            closeModal(modal);
        }
    });
}

// بستن مودال
function closeModal(modalElement) {
    if (typeof modalElement === 'string') {
        const modal = document.getElementById(modalElement);
        if (modal) modal.style.display = 'none';
    } else {
        modalElement.style.display = 'none';
        if (modalElement.parentNode) {
            modalElement.parentNode.removeChild(modalElement);
        }
    }
}

// درخواست لایسنس جدید
function requestNewLicense() {
    const modal = document.createElement('div');
    modal.className = 'modal';
    modal.innerHTML = `
        <div class="modal-content">
            <span class="close" onclick="closeModal(this.parentElement.parentElement)">&times;</span>
            <h3>درخواست لایسنس جدید</h3>
            <form id="requestLicenseForm">
                <div class="form-group">
                    <label for="product">محصول:</label>
                    <select id="product" name="product" required>
                        <option value="">انتخاب محصول</option>
                        <option value="1">نرم‌افزار مالی</option>
                        <option value="2">سیستم مدیریت محتوا</option>
                        <option value="3">اپلیکیشن موبایل</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="purpose">هدف استفاده:</label>
                    <textarea id="purpose" name="purpose" rows="3" required 
                              placeholder="لطفاً هدف خود از استفاده از این محصول را شرح دهید"></textarea>
                </div>
                <div class="form-group">
                    <label for="duration">مدت اعتبار (ماه):</label>
                    <select id="duration" name="duration" required>
                        <option value="1">1 ماه</option>
                        <option value="3">3 ماه</option>
                        <option value="6">6 ماه</option>
                        <option value="12" selected>12 ماه</option>
                        <option value="24">24 ماه</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary">ارسال درخواست</button>
            </form>
        </div>
    `;
    
    document.body.appendChild(modal);
    modal.style.display = 'block';
    
    // مدیریت ارسال فرم
    document.getElementById('requestLicenseForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        
        fetch('api/request_license.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification('درخواست شما با موفقیت ثبت شد و پس از تأیید مدیر، لایسنس برای شما صادر خواهد شد', 'success');
                closeModal(modal);
            } else {
                showNotification('خطا در ثبت درخواست: ' + data.message, 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification('خطا در ارتباط با سرور', 'error');
        });
    });
    
    // بستن مودال با کلیک خارج از آن
    modal.addEventListener('click', function(e) {
        if (e.target === modal) {
            closeModal(modal);
        }
    });
}

// فیلتر کردن لایسنس‌ها
function filterLicenses() {
    const searchTerm = document.getElementById('licenseSearch').value.toLowerCase();
    const statusFilter = document.getElementById('statusFilter').value;
    
    const licenseCards = document.querySelectorAll('.license-card');
    
    licenseCards.forEach(card => {
        const licenseKey = card.querySelector('.license-key').textContent.toLowerCase();
        const productName = card.querySelector('h3').textContent.toLowerCase();
        const status = card.querySelector('.status-badge').textContent;
        
        const matchesSearch = licenseKey.includes(searchTerm) || productName.includes(searchTerm);
        const matchesStatus = statusFilter === '' || status === statusFilter;
        
        if (matchesSearch && matchesStatus) {
            card.style.display = 'block';
        } else {
            card.style.display = 'none';
        }
    });
}

// مدیریت تب‌ها در داشبورد
function initDashboardTabs() {
    const tabButtons = document.querySelectorAll('.tab-button');
    const tabPanes = document.querySelectorAll('.tab-pane');
    
    tabButtons.forEach(button => {
        button.addEventListener('click', () => {
            const tabName = button.getAttribute('data-tab');
            
            // غیرفعال کردن همه دکمه‌ها
            tabButtons.forEach(btn => btn.classList.remove('active'));
            // فعال کردن دکمه انتخاب شده
            button.classList.add('active');
            
            // مخفی کردن همه پنل‌ها
            tabPanes.forEach(pane => pane.classList.remove('active'));
            // نمایش پنل مرتبط
            document.getElementById(`${tabName}-tab`).classList.add('active');
        });
    });
}

// بارگذاری آمار داشبورد
function loadDashboardStats() {
    fetch('api/get_dashboard_stats.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                updateStatsDisplay(data.stats);
            }
        })
        .catch(error => {
            console.error('Error loading stats:', error);
        });
}

// به روزرسانی نمایش آمار
function updateStatsDisplay(stats) {
    if (stats.totalLicenses) {
        document.getElementById('total-licenses').textContent = stats.totalLicenses;
    }
    
    if (stats.activeLicenses) {
        document.getElementById('active-licenses').textContent = stats.activeLicenses;
    }
    
    if (stats.expiringSoon) {
        document.getElementById('expiring-soon').textContent = stats.expiringSoon;
    }
    
    if (stats.recentActivity) {
        const activityList = document.getElementById('recent-activity-list');
        activityList.innerHTML = '';
        
        stats.recentActivity.forEach(activity => {
            const li = document.createElement('li');
            li.innerHTML = `
                <span class="activity-type">${activity.type}</span>
                <span class="activity-details">${activity.details}</span>
                <span class="activity-time">${toPersianDate(activity.time)}</span>
            `;
            activityList.appendChild(li);
        });
    }
}

// راه‌اندازی داشبورد هنگام بارگذاری صفحه
document.addEventListener('DOMContentLoaded', function() {
    // مقداردهی اولیه تب‌ها
    initDashboardTabs();
    
    // بارگذاری آمار
    loadDashboardStats();
    
    // راه‌اندازی جستجو و فیلتر
    const searchInput = document.getElementById('licenseSearch');
    const statusFilter = document.getElementById('statusFilter');
    
    if (searchInput) {
        searchInput.addEventListener('input', filterLicenses);
    }
    
    if (statusFilter) {
        statusFilter.addEventListener('change', filterLicenses);
    }
    
    // مدیریت فرم درخواست لایسنس
    const requestForm = document.getElementById('requestLicenseForm');
    if (requestForm) {
        requestForm.addEventListener('submit', function(e) {
            e.preventDefault();
            // مدیریت ارسال فرم...
        });
    }
});

// تابع برای نمایش تاریخ به صورت نسبی (مثلاً "2 روز پیش")
function timeAgo(date) {
    const now = new Date();
    const diff = now - new Date(date);
    
    const seconds = Math.floor(diff / 1000);
    const minutes = Math.floor(seconds / 60);
    const hours = Math.floor(minutes / 60);
    const days = Math.floor(hours / 24);
    
    if (days > 0) {
        return `${days} روز پیش`;
    } else if (hours > 0) {
        return `${hours} ساعت پیش`;
    } else if (minutes > 0) {
        return `${minutes} دقیقه پیش`;
    } else {
        return 'همین حالا';
    }
}

// به روزرسانی زمان‌های نسبی در صفحه
function updateRelativeTimes() {
    const timeElements = document.querySelectorAll('.relative-time');
    
    timeElements.forEach(element => {
        const date = element.getAttribute('data-time');
        if (date) {
            element.textContent = timeAgo(date);
        }
    });
}

// به روزرسانی دوره‌ای زمان‌های نسبی
setInterval(updateRelativeTimes, 60000); // هر دقیقه

// مدیریت نوتیفیکیشن‌های خوانده نشده
function markNotificationAsRead(notificationId) {
    fetch(`api/mark_notification_read.php?id=${notificationId}`, {
        method: 'POST'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const notification = document.getElementById(`notification-${notificationId}`);
            if (notification) {
                notification.classList.remove('unread');
            }
        }
    })
    .catch(error => {
        console.error('Error marking notification as read:', error);
    });
}

// بارگذاری نوتیفیکیشن‌ها
function loadNotifications() {
    fetch('api/get_notifications.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                updateNotificationsDisplay(data.notifications);
            }
        })
        .catch(error => {
            console.error('Error loading notifications:', error);
        });
}

// به روزرسانی نمایش نوتیفیکیشن‌ها
function updateNotificationsDisplay(notifications) {
    const container = document.getElementById('notifications-container');
    if (!container) return;
    
    container.innerHTML = '';
    
    if (notifications.length === 0) {
        container.innerHTML = '<p class="no-notifications">هیچ اعلانی وجود ندارد</p>';
        return;
    }
    
    notifications.forEach(notification => {
        const div = document.createElement('div');
        div.className = `notification-item ${notification.read ? '' : 'unread'}`;
        div.id = `notification-${notification.id}`;
        
        div.innerHTML = `
            <div class="notification-content">
                <p class="notification-text">${notification.message}</p>
                <span class="notification-time">${timeAgo(notification.created_at)}</span>
            </div>
            ${notification.read ? '' : '<button class="mark-read-btn" onclick="markNotificationAsRead(' + notification.id + ')">علامت‌گذاری به عنوان خوانده شده</button>'}
        `;
        
        container.appendChild(div);
    });
}