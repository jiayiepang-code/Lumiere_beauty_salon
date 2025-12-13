-- Authentication System Database Tables
-- Run this script to set up tables for admin authentication

-- Table for tracking login attempts (rate limiting)
CREATE TABLE IF NOT EXISTS Login_Attempts (
    attempt_id INT AUTO_INCREMENT PRIMARY KEY,
    phone VARCHAR(18) NOT NULL,
    attempt_time DATETIME NOT NULL,
    INDEX idx_phone_time (phone, attempt_time)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table for logging successful admin logins
CREATE TABLE IF NOT EXISTS Admin_Login_Log (
    log_id INT AUTO_INCREMENT PRIMARY KEY,
    admin_email VARCHAR(100) NOT NULL,
    login_time DATETIME NOT NULL,
    ip_address VARCHAR(45),
    INDEX idx_admin_email (admin_email),
    INDEX idx_login_time (login_time),
    FOREIGN KEY (admin_email) REFERENCES Staff(staff_email) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Update Staff table to ensure password field can store hashed passwords
-- (bcrypt hashes are 60 characters)
ALTER TABLE Staff MODIFY COLUMN password VARCHAR(255) NOT NULL;

-- Create a default admin account for testing (password: Admin@123)
-- Password hash for 'Admin@123'
INSERT INTO Staff (staff_email, phone, password, first_name, last_name, role, is_active)
VALUES (
    'admin@lumiere.com',
    '60123456789',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
    'Admin',
    'User',
    'admin',
    TRUE
)
ON DUPLICATE KEY UPDATE
    password = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
    is_active = TRUE;
