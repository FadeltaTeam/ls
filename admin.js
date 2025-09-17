// توابع مدیریتی جاوااسکریپت

// نمایش مودال ایجاد لایسنس
function showGenerateLicenseModal() {
    const modal = document.getElementById('generateLicenseModal');
    if (modal) {
        modal.style.display = 'block';
        loadProductsAndUsers();
    }
}

// بارگذاری محصولات و کاربران برای انتخاب
function loadProductsAndUsers() {
    // بارگذاری محصولات
    fetch('../api/get_products.php')
        .then(response => response.json())
        .then(products => {
            const productSelect = document.getElementById('productSelect');
            if (productSelect && products.length) {
                productSelect.innerHTML = '<option value="">انتخاب محصول</option>';
                products.forEach(product => {
                    const option = document.createElement('option');
                    option.value = product.id;
                    option.textContent = product.name;
                    productSelect.appendChild(option);
                });
            }
        })
        .catch(error => {
            console.error('Error loading products:', error);
        });
    
    // بارگذاری کاربران
    fetch('../api/get_users.php')
        .then(response => response.json())
        .then(users => {
            const userSelect = document.getElementById('userSelect');
            if (userSelect && users.length) {
                userSelect.innerHTML = '<option value="">انتخاب کاربر</option>';
                users.forEach(user => {
                    const option = document.createElement('option');
                    option.value = user.id;
                    option.textContent = user.name;
                    userSelect.appendChild(option);
                });
            }
        })
        .catch(error => {
            console.error('Error loading users:', error);
        });
}

// ایجاد لایسنس جدید
document.getElementById('generateLicenseForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const productId = document.getElementById('productSelect').value;
    const userId = document.getElementById('userSelect').value;
    const expiryDays = document.getElementById('expiryDays').value;
    const maxActivations = document.getElementById('maxActivations').value;
    
    if (!productId || !userId) {
        showNotification('لطفاً همه فیلدهای ضروری را پر کنید', 'error');
        return;
    }
    
    const formData = new FormData();
    formData.append('product_id', productId);
    formData.append('user_id', userId);
    formData.append('expiry_days', expiryDays);
    formData.append('max_activations', maxActivations);
    
    fetch('../api/generate_license.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('لایسنس با موفقیت ایجاد شد: ' + data.license_key, 'success');
            document.getElementById('generateLicenseForm').reset();
            document.getElementById('generateLicenseModal').style.display = 'none';
            // رفرش صفحه برای نمایش لایسنس جدید
            setTimeout(() => { location.reload(); }, 2000);
        } else {
            showNotification('خطا در ایجاد لایسنس: ' + data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('خطا در ارتباط با سرور', 'error');
    });
});

// لغو لایسنس
function revokeLicense(licenseId) {
    if (!confirm('آیا از لغو این لایسنس اطمینان دارید؟')) {
        return;
    }
    
    const formData = new FormData();
    formData.append('license_id', licenseId);
    
    fetch('../api/revoke_license.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('لایسنس با موفقیت لغو شد', 'success');
            // رفرش صفحه برای به روزرسانی وضعیت
            setTimeout(() => { location.reload(); }, 1500);
        } else {
            showNotification('خطا در لغو لایسنس: ' + data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('خطا در ارتباط با سرور', 'error');
    });
}

// مشاهده جزئیات لایسنس
function viewLicenseDetails(licenseId) {
    // بارگذاری جزئیات لایسنس از طریق API و نمایش در مودال
    fetch(`../api/get_license_details.php?id=${licenseId}`)
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
    // ایجاد و نمایش مودال با جزئیات لایسنس
    const modal = document.createElement('div');
    modal.className = 'modal';
    modal.innerHTML = `
        <div class="modal-content">
            <span class="close" onclick="this.parentElement.parentElement.style.display='none'">&times;</span>
            <h3>جزئیات لایسنس</h3>
            <div class="license-details">
                <p><strong>کلید لایسنس:</strong> ${license.license_key}</p>
                <p><strong>محصول:</strong> ${license.product_name}</p>
                <p><strong>کاربر:</strong> ${license.user_name}</p>
                <p><strong>تاریخ ایجاد:</strong> ${toPersianDate(license.created_at)}</p>
                <p><strong>تاریخ انقضا:</strong> ${toPersianDate(license.expiry_date)}</p>
                <p><strong>وضعیت:</strong> <span class="status-badge status-${license.status}">${license.status}</span></p>
                <p><strong>حداکثر فعال‌سازی:</strong> ${license.max_activations}</p>
            </div>
            <h4>فعال‌سازی‌ها</h4>
            <ul>
                ${license.activations && license.activations.length ? 
                 license.activations.map(a => `<li>${a.domain} - ${toPersianDate(a.activation_date)}</li>`).join('') : 
                 '<li>هیچ فعال‌سازی ثبت نشده است</li>'}
            </ul>
        </div>
    `;
    
    document.body.appendChild(modal);
    modal.style.display = 'block';
    
    // بستن مودال با کلیک خارج از آن
    modal.addEventListener('click', function(e) {
        if (e.target === modal) {
            modal.style.display = 'none';
            document.body.removeChild(modal);
        }
    });
}

// نمایش مودال افزودن کاربر
function showAddUserModal() {
    const modal = document.getElementById('addUserModal');
    if (modal) {
        modal.style.display = 'block';
    }
}

// مدیریت فرم افزودن کاربر
document.getElementById('addUserForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const name = document.getElementById('userName').value;
    const email = document.getElementById('userEmail').value;
    const password = document.getElementById('userPassword').value;
    const role = document.getElementById('userRole').value;
    
    if (!name || !email || !password || !role) {
        showNotification('لطفاً همه فیلدها را پر کنید', 'error');
        return;
    }
    
    if (!isValidEmail(email)) {
        showNotification('لطفاً یک ایمیل معتبر وارد کنید', 'error');
        return;
    }
    
    const formData = new FormData();
    formData.append('name', name);
    formData.append('email', email);
    formData.append('password', password);
    formData.append('role', role);
    
    fetch('../api/add_user.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('کاربر با موفقیت افزوده شد', 'success');
            document.getElementById('addUserForm').reset();
            document.getElementById('addUserModal').style.display = 'none';
            // رفرش صفحه برای نمایش کاربر جدید
            setTimeout(() => { location.reload(); }, 2000);
        } else {
            showNotification('خطا در افزودن کاربر: ' + data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('خطا در ارتباط با سرور', 'error');
    });
});

// ویرایش کاربر
function editUser(userId) {
    // بارگذاری اطلاعات کاربر و نمایش در فرم ویرایش
    fetch(`../api/get_user_details.php?id=${userId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showEditUserModal(data.user);
            } else {
                showNotification('خطا در دریافت اطلاعات کاربر', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification('خطا در ارتباط با سرور', 'error');
        });
}

// نمایش مودال ویرایش کاربر
function showEditUserModal(user) {
    // ایجاد و نمایش مودال ویرایش کاربر
    const modal = document.createElement('div');
    modal.className = 'modal';
    modal.innerHTML = `
        <div class="modal-content">
            <span class="close" onclick="this.parentElement.parentElement.style.display='none'">&times;</span>
            <h3>ویرایش کاربر</h3>
            <form id="editUserForm">
                <input type="hidden" name="user_id" value="${user.id}">
                <div class="form-group">
                    <label for="editUserName">نام کامل:</label>
                    <input type="text" id="editUserName" name="name" value="${user.name}" required>
                </div>
                <div class="form-group">
                    <label for="editUserEmail">ایمیل:</label>
                    <input type="email" id="editUserEmail" name="email" value="${user.email}" required>
                </div>
                <div class="form-group">
                    <label for="editUserPassword">رمز عبور (خالی بگذارید اگر نمی‌خواهید تغییر کند):</label>
                    <input type="password" id="editUserPassword" name="password">
                </div>
                <div class="form-group">
                    <label for="editUserRole">نقش:</label>
                    <select id="editUserRole" name="role" required>
                        <option value="user" ${user.role === 'user' ? 'selected' : ''}>کاربر عادی</option>
                        <option value="manager" ${user.role === 'manager' ? 'selected' : ''}>مدیر</option>
                        <option value="admin" ${user.role === 'admin' ? 'selected' : ''}>ادمین</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="editUserStatus">وضعیت:</label>
                    <select id="editUserStatus" name="status" required>
                        <option value="1" ${user.status == 1 ? 'selected' : ''}>فعال</option>
                        <option value="0" ${user.status == 0 ? 'selected' : ''}>غیرفعال</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary">ذخیره تغییرات</button>
            </form>
        </div>
    `;
    
    document.body.appendChild(modal);
    modal.style.display = 'block';
    
    // مدیریت فرم ویرایش
    document.getElementById('editUserForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        
        fetch('../api/update_user.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification('اطلاعات کاربر با موفقیت به روز شد', 'success');
                modal.style.display = 'none';
                document.body.removeChild(modal);
                // رفرش صفحه برای نمایش تغییرات
                setTimeout(() => { location.reload(); }, 1500);
            } else {
                showNotification('خطا در به روزرسانی کاربر: ' + data.message, 'error');
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
            modal.style.display = 'none';
            document.body.removeChild(modal);
        }
    });
}

// حذف کاربر
function deleteUser(userId) {
    if (!confirm('آیا از حذف این کاربر اطمینان دارید؟ این عمل قابل بازگشت نیست.')) {
        return;
    }
    
    const formData = new FormData();
    formData.append('user_id', userId);
    
    fetch('../api/delete_user.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('کاربر با موفقیت حذف شد', 'success');
            // رفرش صفحه برای به روزرسانی لیست
            setTimeout(() => { location.reload(); }, 1500);
        } else {
            showNotification('خطا در حذف کاربر: ' + data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('خطا در ارتباط با سرور', 'error');
    });
}