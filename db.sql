CREATE DATABASE license_system CHARACTER SET utf8mb4 COLLATE utf8mb4_persian_ci;
USE license_system;

-- جدول کاربران
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'manager', 'user') DEFAULT 'user',
    status TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL,
    INDEX (email)
);

-- جدول محصولات
CREATE TABLE products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    version VARCHAR(20),
    price DECIMAL(10,2) DEFAULT 0,
    status TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX (name)
);

-- جدول لایسنس‌ها
CREATE TABLE licenses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    license_key VARCHAR(50) UNIQUE NOT NULL,
    product_id INT NOT NULL,
    user_id INT NOT NULL,
    expiry_date DATE NOT NULL,
    max_activations INT DEFAULT 1,
    status ENUM('active', 'suspended', 'revoked') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    revoked_at TIMESTAMP NULL,
    FOREIGN KEY (product_id) REFERENCES products(id),
    FOREIGN KEY (user_id) REFERENCES users(id),
    INDEX (license_key)
);

-- جدول فعال‌سازی‌ها
CREATE TABLE license_activations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    license_id INT NOT NULL,
    domain VARCHAR(255) NOT NULL,
    ip_address VARCHAR(45),
    activation_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (license_id) REFERENCES licenses(id),
    INDEX (license_id)
);

-- جدول درخواست‌های لایسنس
CREATE TABLE license_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    product_id INT NOT NULL,
    purpose TEXT,
    duration INT DEFAULT 12,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    processed_at TIMESTAMP NULL,
    processed_by INT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (product_id) REFERENCES products(id),
    FOREIGN KEY (processed_by) REFERENCES users(id)
);

-- جدول لاگ API
CREATE TABLE api_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    license_key VARCHAR(50) NOT NULL,
    product_id INT NOT NULL,
    domain VARCHAR(255),
    ip_address VARCHAR(45),
    request_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    response_code INT,
    INDEX (license_key)
);

-- جدول فعالیت‌ها
CREATE TABLE activity_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    action VARCHAR(100) NOT NULL,
    details TEXT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    INDEX (user_id)
);

-- جدول اعلان‌ها
CREATE TABLE notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    type ENUM('info', 'success', 'warning', 'error') DEFAULT 'info',
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    INDEX (user_id, is_read)
);

-- جدول تنظیمات سیستم
CREATE TABLE settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT,
    setting_group VARCHAR(50) DEFAULT 'general',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX (setting_key)
);

-- ایجاد کاربر ادمین پیش‌فرض
INSERT INTO users (name, email, password, role) VALUES 
('مدیر سیستم', 'admin@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin');

-- ایجاد محصولات نمونه
INSERT INTO products (name, description, version, price) VALUES 
('نرم‌افزار مالی', 'سیستم جامع مدیریت مالی و حسابداری', '2.5.0', 299000),
('سیستم مدیریت محتوا', 'پلتفرم مدیریت محتوای پیشرفته', '1.8.3', 149000),
('اپلیکیشن موبایل', 'اپلیکیشن مدیریت کسب و کار', '1.2.0', 199000);

-- ایجاد تنظیمات پیش‌فرض
INSERT INTO settings (setting_key, setting_value, setting_group) VALUES 
('site_name', 'سیستم لایسنس پیشرفته', 'general'),
('site_description', 'سیستم مدیریت لایسنس برای نرم‌افزارها و اسکریپت‌ها', 'general'),
('admin_email', 'admin@example.com', 'email'),
('smtp_host', 'smtp.example.com', 'email'),
('smtp_port', '587', 'email'),
('license_prefix', 'LS_', 'license'),
('default_license_duration', '365', 'license'),
('max_activations', '3', 'license');



ALTER TABLE notifications 
ADD COLUMN read_at TIMESTAMP NULL AFTER is_read;