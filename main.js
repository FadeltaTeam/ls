// توابع عمومی جاوااسکریپت

// کپی به کلیپ‌بورد
function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(() => {
        showNotification('متن با موفقیت کپی شد', 'success');
    }).catch(err => {
        showNotification('خطا در کپی کردن متن', 'error');
    });
}

// نمایش نوتیفیکیشن
function showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    notification.textContent = message;
    
    // استایل نوتیفیکیشن
    notification.style.position = 'fixed';
    notification.style.top = '20px';
    notification.style.left = '50%';
    notification.style.transform = 'translateX(-50%)';
    notification.style.padding = '10px 20px';
    notification.style.borderRadius = '4px';
    notification.style.zIndex = '10000';
    notification.style.color = 'white';
    notification.style.fontWeight = 'bold';
    
    if (type === 'success') {
        notification.style.backgroundColor = '#28a745';
    } else if (type === 'error') {
        notification.style.backgroundColor = '#dc3545';
    } else {
        notification.style.backgroundColor = '#17a2b8';
    }
    
    document.body.appendChild(notification);
    
    // حذف خودکار پس از 3 ثانیه
    setTimeout(() => {
        notification.style.opacity = '0';
        notification.style.transition = 'opacity 0.5s';
        setTimeout(() => {
            document.body.removeChild(notification);
        }, 500);
    }, 3000);
}

// مدیریت فرم‌ها
function handleFormSubmit(formId, callback) {
    const form = document.getElementById(formId);
    if (form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            
            // غیرفعال کردن دکمه ارسال برای جلوگیری از ارسال چندباره
            const submitButton = this.querySelector('button[type="submit"]');
            const originalText = submitButton.textContent;
            submitButton.disabled = true;
            submitButton.textContent = 'در حال پردازش...';
            
            // جمع‌آوری داده‌های فرم
            const formData = new FormData(this);
            const data = {};
            for (let [key, value] of formData.entries()) {
                data[key] = value;
            }
            
            // فراخوانی callback
            callback(data, function(result) {
                // فعال کردن دوباره دکمه
                submitButton.disabled = false;
                submitButton.textContent = originalText;
                
                if (result.success) {
                    showNotification(result.message, 'success');
                    if (formId === 'loginForm') {
                        window.location.href = 'dashboard.php';
                    }
                } else {
                    showNotification(result.message, 'error');
                }
            });
        });
    }
}

// مدیریت مودال
function setupModal(modalId, openButtonId = null) {
    const modal = document.getElementById(modalId);
    if (!modal) return;
    
    const closeButton = modal.querySelector('.close');
    
    // بستن مودال با کلیک روی دکمه بستن
    if (closeButton) {
        closeButton.addEventListener('click', () => {
            modal.style.display = 'none';
        });
    }
    
    // بستن مودال با کلیک خارج از آن
    window.addEventListener('click', (e) => {
        if (e.target === modal) {
            modal.style.display = 'none';
        }
    });
    
    // باز کردن مودال با دکمه مشخص
    if (openButtonId) {
        const openButton = document.getElementById(openButtonId);
        if (openButton) {
            openButton.addEventListener('click', () => {
                modal.style.display = 'block';
            });
        }
    }
    
    return {
        open: () => { modal.style.display = 'block'; },
        close: () => { modal.style.display = 'none'; }
    };
}

// تاریخ شمسی
function toPersianDate(date) {
    const options = { 
        year: 'numeric', 
        month: 'long', 
        day: 'numeric',
        calendar: 'persian',
        numberingSystem: 'arab'
    };
    return new Date(date).toLocaleDateString('fa-IR', options);
}

// اعتبارسنجی ایمیل
function isValidEmail(email) {
    const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return re.test(email);
}

// اعتبارسنجی شماره تلفن
function isValidPhone(phone) {
    const re = /^09[0-9]{9}$/;
    return re.test(phone);
}

// مدیریت تب‌ها
function setupTabs(containerSelector) {
    const containers = document.querySelectorAll(containerSelector);
    
    containers.forEach(container => {
        const tabs = container.querySelectorAll('[data-tab]');
        
        tabs.forEach(tab => {
            tab.addEventListener('click', () => {
                const tabName = tab.getAttribute('data-tab');
                
                // غیرفعال کردن همه تب‌ها
                tabs.forEach(t => t.classList.remove('active'));
                
                // فعال کردن تب انتخاب شده
                tab.classList.add('active');
                
                // مخفی کردن همه محتواها
                const contents = container.querySelectorAll('.tab-content');
                contents.forEach(content => content.classList.remove('active'));
                
                // نمایش محتوای مرتبط
                const relatedContent = container.querySelector(`.tab-content[data-tab="${tabName}"]`);
                if (relatedContent) {
                    relatedContent.classList.add('active');
                }
            });
        });
    });
}

// بارگذاری محتوا با AJAX
function loadContent(url, container, callback) {
    fetch(url)
        .then(response => response.text())
        .then(data => {
            container.innerHTML = data;
            if (callback) callback();
        })
        .catch(error => {
            console.error('Error loading content:', error);
            container.innerHTML = '<p>خطا در بارگذاری محتوا</p>';
        });
}

// اجرای توابع پس از بارگذاری کامل صفحه
document.addEventListener('DOMContentLoaded', function() {
    // راه‌اندازی کلیه مودال‌ها
    setupModal('generateLicenseModal');
    setupModal('addUserModal');
    
    // راه‌اندازی تب‌ها
    setupTabs('.tabs-container');
    
    // مدیریت فرم جستجو
    const searchForm = document.getElementById('searchForm');
    if (searchForm) {
        searchForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const searchInput = this.querySelector('input[type="search"]');
            const searchTerm = searchInput.value.trim();
            
            if (searchTerm.length > 2) {
                // انجام جستجو
                performSearch(searchTerm);
            }
        });
    }
    
    // فیلتر کردن جدول داده‌ها
    const searchInput = document.getElementById('searchInput');
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            filterTable(this.value);
        });
    }
    
    const statusFilter = document.getElementById('statusFilter');
    if (statusFilter) {
        statusFilter.addEventListener('change', function() {
            filterTableByStatus(this.value);
        });
    }
});

// فیلتر کردن جدول
function filterTable(searchTerm) {
    const tables = document.querySelectorAll('.data-table');
    
    tables.forEach(table => {
        const rows = table.querySelectorAll('tbody tr');
        
        rows.forEach(row => {
            const text = row.textContent.toLowerCase();
            if (text.includes(searchTerm.toLowerCase())) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    });
}

// فیلتر کردن بر اساس وضعیت
function filterTableByStatus(status) {
    const tables = document.querySelectorAll('.data-table');
    
    tables.forEach(table => {
        const rows = table.querySelectorAll('tbody tr');
        
        rows.forEach(row => {
            if (!status) {
                row.style.display = '';
                return;
            }
            
            const statusCell = row.querySelector('.status-badge');
            if (statusCell && statusCell.classList.contains(`status-${status}`)) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    });
}

// مدیریت اعلان‌ها در داشبورد
class NotificationManager {
    constructor() {
        this.unreadCount = 0;
        this.init();
    }
    
    init() {
        this.loadNotifications();
        this.setupEventListeners();
        this.startAutoRefresh();
    }
    
    // بارگذاری اعلان‌ها
    async loadNotifications() {
        try {
            const response = await fetch('api/get_notifications.php?limit=10');
            const data = await response.json();
            
            if (data.success) {
                this.unreadCount = data.unread_count;
                this.updateNotificationBadge();
                this.renderNotifications(data.notifications);
            }
        } catch (error) {
            console.error('Error loading notifications:', error);
        }
    }
    
    // به روزرسانی نشانگر تعداد اعلان‌های خوانده نشده
    updateNotificationBadge() {
        const badge = document.getElementById('notification-badge');
        if (badge) {
            if (this.unreadCount > 0) {
                badge.textContent = this.unreadCount;
                badge.style.display = 'inline';
            } else {
                badge.style.display = 'none';
            }
        }
    }
    
    // نمایش اعلان‌ها
    renderNotifications(notifications) {
        const container = document.getElementById('notifications-container');
        if (!container) return;
        
        if (notifications.length === 0) {
            container.innerHTML = '<p class="no-notifications">هیچ اعلانی وجود ندارد</p>';
            return;
        }
        
        container.innerHTML = notifications.map(notification => `
            <div class="notification-item ${notification.is_read ? '' : 'unread'}" data-id="${notification.id}">
                <div class="notification-icon">
                    <i class="icon-${notification.type}"></i>
                </div>
                <div class="notification-content">
                    <h4>${notification.title}</h4>
                    <p>${notification.message}</p>
                    <span class="notification-time">${notification.time_ago}</span>
                </div>
                ${notification.is_read ? '' : 
                `<button class="mark-read-btn" onclick="notificationManager.markAsRead(${notification.id})">
                    <i class="icon-check"></i>
                </button>`}
            </div>
        `).join('');
    }
    
    // علامت‌گذاری اعلان به عنوان خوانده شده
    async markAsRead(notificationId) {
        try {
            const formData = new FormData();
            formData.append('id', notificationId);
            
            const response = await fetch('api/mark_notification_read.php', {
                method: 'POST',
                body: formData
            });
            
            const data = await response.json();
            
            if (data.success) {
                // به روزرسانی UI
                const notification = document.querySelector(`.notification-item[data-id="${notificationId}"]`);
                if (notification) {
                    notification.classList.remove('unread');
                    notification.querySelector('.mark-read-btn')?.remove();
                }
                
                // به روزرسانی شمارنده
                this.unreadCount--;
                this.updateNotificationBadge();
            }
        } catch (error) {
            console.error('Error marking notification as read:', error);
        }
    }
    
    // علامت‌گذاری همه اعلان‌ها به عنوان خوانده شده
    async markAllAsRead() {
        try {
            const response = await fetch('api/mark_all_notifications_read.php', {
                method: 'POST'
            });
            
            const data = await response.json();
            
            if (data.success) {
                // به روزرسانی UI
                document.querySelectorAll('.notification-item.unread').forEach(item => {
                    item.classList.remove('unread');
                    item.querySelector('.mark-read-btn')?.remove();
                });
                
                // به روزرسانی شمارنده
                this.unreadCount = 0;
                this.updateNotificationBadge();
            }
        } catch (error) {
            console.error('Error marking all notifications as read:', error);
        }
    }
    
    // راه‌اندازی event listeners
    setupEventListeners() {
        // باز کردن/بستن پنل اعلان‌ها
        const notificationBtn = document.getElementById('notification-btn');
        const notificationPanel = document.getElementById('notification-panel');
        
        if (notificationBtn && notificationPanel) {
            notificationBtn.addEventListener('click', () => {
                notificationPanel.classList.toggle('active');
            });
            
            // بستن پنل با کلیک خارج از آن
            document.addEventListener('click', (e) => {
                if (!notificationPanel.contains(e.target) && !notificationBtn.contains(e.target)) {
                    notificationPanel.classList.remove('active');
                }
            });
        }
    }
    
    // به روزرسانی خودکار اعلان‌ها
    startAutoRefresh() {
        setInterval(() => {
            this.loadNotifications();
        }, 300000); // هر 5 دقیقه
    }
}

// مقداردهی اولیه
const notificationManager = new NotificationManager();


// مدیریت فرم ایجاد لایسنس
class LicenseGenerator {
    constructor() {
        this.products = [];
        this.users = [];
        this.init();
    }
    
    async init() {
        await this.loadProducts();
        await this.loadUsers();
        this.setupEventListeners();
    }
    
    // بارگذاری لیست محصولات
    async loadProducts() {
        try {
            const response = await fetch('api/get_products.php');
            const data = await response.json();
            
            if (data.success) {
                this.products = data.products;
                this.renderProductOptions();
            }
        } catch (error) {
            console.error('Error loading products:', error);
            showNotification('خطا در بارگذاری محصولات', 'error');
        }
    }
    
    // بارگذاری لیست کاربران
    async loadUsers() {
        try {
            const response = await fetch('api/get_users.php');
            const data = await response.json();
            
            if (data.success) {
                this.users = data.users;
                this.renderUserOptions();
            }
        } catch (error) {
            console.error('Error loading users:', error);
            showNotification('خطا در بارگذاری کاربران', 'error');
        }
    }
    
    // نمایش گزینه‌های محصولات
    renderProductOptions() {
        const select = document.getElementById('productSelect');
        if (!select) return;
        
        select.innerHTML = '<option value="">انتخاب محصول</option>';
        this.products.forEach(product => {
            const option = document.createElement('option');
            option.value = product.id;
            option.textContent = `${product.name} (نسخه ${product.version})`;
            select.appendChild(option);
        });
    }
    
    // نمایش گزینه‌های کاربران
    renderUserOptions() {
        const select = document.getElementById('userSelect');
        if (!select) return;
        
        select.innerHTML = '<option value="">انتخاب کاربر</option>';
        this.users.forEach(user => {
            const option = document.createElement('option');
            option.value = user.id;
            option.textContent = `${user.name} - ${user.email}`;
            select.appendChild(option);
        });
    }
    
    // راه‌اندازی event listeners
    setupEventListeners() {
        const form = document.getElementById('generateLicenseForm');
        if (form) {
            form.addEventListener('submit', (e) => this.handleSubmit(e));
        }
        
        // پیش‌نمایش تاریخ انقضا
        const expiryDaysInput = document.getElementById('expiryDays');
        if (expiryDaysInput) {
            expiryDaysInput.addEventListener('input', () => this.updateExpiryPreview());
        }
    }
    
    // پیش‌نمایش تاریخ انقضا
    updateExpiryPreview() {
        const expiryDays = document.getElementById('expiryDays').value;
        const previewElement = document.getElementById('expiryDatePreview');
        
        if (previewElement && expiryDays) {
            const days = parseInt(expiryDays);
            if (!isNaN(days) && days > 0) {
                const expiryDate = new Date();
                expiryDate.setDate(expiryDate.getDate() + days);
                
                const formattedDate = expiryDate.toLocaleDateString('fa-IR');
                previewElement.textContent = `تاریخ انقضا: ${formattedDate}`;
                previewElement.style.display = 'block';
            } else {
                previewElement.style.display = 'none';
            }
        }
    }
    
    // مدیریت ارسال فرم
    async handleSubmit(e) {
        e.preventDefault();
        
        const form = e.target;
        const submitButton = form.querySelector('button[type="submit"]');
        const originalText = submitButton.textContent;
        
        // غیرفعال کردن دکمه ارسال
        submitButton.disabled = true;
        submitButton.textContent = 'در حال ایجاد لایسنس...';
        
        try {
            const formData = new FormData(form);
            
            const response = await fetch('api/generate_license.php', {
                method: 'POST',
                body: formData
            });
            
            const data = await response.json();
            
            if (data.success) {
                showNotification('لایسنس با موفقیت ایجاد شد', 'success');
                this.showLicenseResult(data);
                form.reset();
            } else {
                showNotification(data.message, 'error');
            }
        } catch (error) {
            console.error('Error generating license:', error);
            showNotification('خطا در ایجاد لایسنس', 'error');
        } finally {
            // فعال کردن دوباره دکمه ارسال
            submitButton.disabled = false;
            submitButton.textContent = originalText;
        }
    }
    
    // نمایش نتیجه ایجاد لایسنس
    showLicenseResult(data) {
        const resultModal = document.getElementById('licenseResultModal');
        if (!resultModal) return;
        
        const content = `
            <div class="license-result">
                <h3>لایسنس ایجاد شد</h3>
                <div class="license-details">
                    <p><strong>کلید لایسنس:</strong></p>
                    <div class="license-key-result">
                        <code>${data.license_key}</code>
                        <button class="btn btn-small" onclick="copyToClipboard('${data.license_key}')">کپی</button>
                    </div>
                    <p><strong>محصول:</strong> ${data.product_name}</p>
                    <p><strong>کاربر:</strong> ${data.user_name}</p>
                    <p><strong>تاریخ انقضا:</strong> ${data.persian_expiry_date}</p>
                    <p><strong>حداکثر فعال‌سازی:</strong> ${data.max_activations}</p>
                </div>
                <div class="license-actions">
                    <button class="btn btn-primary" onclick="downloadLicenseFile('${data.license_key}', '${data.product_name}')">دانلود فایل لایسنس</button>
                    <button class="btn btn-secondary" onclick="closeModal('licenseResultModal')">بستن</button>
                </div>
            </div>
        `;
        
        resultModal.querySelector('.modal-content').innerHTML = content;
        resultModal.style.display = 'block';
    }
}

// تابع دانلود فایل لایسنس
function downloadLicenseFile(licenseKey, productName) {
    const content = `<?php
/**
 * License File
 * Product: ${productName}
 * License Key: ${licenseKey}
 * Generated: ${new Date().toLocaleDateString('fa-IR')}
 */

define('LICENSE_KEY', '${licenseKey}');
define('LICENSE_STATUS', 'active');
?>`;
    
    const blob = new Blob([content], { type: 'text/php' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `license_${productName.replace(/\s+/g, '_')}.php`;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
}

// مقداردهی اولیه
document.addEventListener('DOMContentLoaded', function() {
    const licenseGenerator = new LicenseGenerator();
    
    // تنظیم مقادیر پیش‌فرض
    const expiryDaysInput = document.getElementById('expiryDays');
    if (expiryDaysInput) {
        expiryDaysInput.value = 365; // مقدار پیش‌فرض 1 سال
    }
    
    const maxActivationsInput = document.getElementById('maxActivations');
    if (maxActivationsInput) {
        maxActivationsInput.value = 3; // مقدار پیش‌فرض 3 فعال‌سازی
    }
});
