-- Database Schema v2.0 - Replacing File-Based Storage with Database Storage
-- PHP-BASED VERSION: No partitions, stored procedures, or triggers - uses PHP functions
-- Run this in phpMyAdmin or MySQL command line

-- Create database (if not exists)
CREATE DATABASE IF NOT EXISTS monitoring_db;
USE monitoring_db;

-- ============================================================================
-- EXISTING TABLES (Keep these as they are)
-- ============================================================================

-- Monitor IPs table (existing)
CREATE TABLE IF NOT EXISTS tbl_monitor_ips (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ip_address VARCHAR(45) NOT NULL,
    mac_address VARCHAR(17),
    name VARCHAR(255),
    username VARCHAR(50),
    type VARCHAR(50) DEFAULT 'user',
    os_status TINYINT DEFAULT 0,
    monitor_status TINYINT DEFAULT 0,
    last_monitor_tic DATETIME,
    last_browser_tic DATETIME,
    app_version VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_ip_address (ip_address),
    INDEX idx_monitor_status (monitor_status),
    INDEX idx_last_monitor_tic (last_monitor_tic),
    INDEX idx_last_browser_tic (last_browser_tic)
);

-- Users table (existing)
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL,
    email VARCHAR(255),
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'user', 'viewer') DEFAULT 'user',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_username (username),
    INDEX idx_role (role)
);

-- ============================================================================
-- NEW TABLES FOR DATABASE-BASED STORAGE
-- ============================================================================

-- Browser History Logs Table
CREATE TABLE IF NOT EXISTS browser_history_logs (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    monitor_ip_id INT NOT NULL,
    browser VARCHAR(50) NOT NULL,
    url TEXT NOT NULL,
    title TEXT,
    last_visit BIGINT, -- Unix timestamp
    visit_date DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (monitor_ip_id) REFERENCES tbl_monitor_ips(id) ON DELETE CASCADE,
    INDEX idx_monitor_ip_id (monitor_ip_id),
    INDEX idx_browser (browser),
    INDEX idx_visit_date (visit_date),
    INDEX idx_last_visit (last_visit),
    INDEX idx_created_at (created_at)
);

-- Key Logs Table
CREATE TABLE IF NOT EXISTS key_logs (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    monitor_ip_id INT NOT NULL,
    key_date DATETIME NOT NULL,
    application VARCHAR(255) NOT NULL,
    key_pressed VARCHAR(100) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (monitor_ip_id) REFERENCES tbl_monitor_ips(id) ON DELETE CASCADE,
    INDEX idx_monitor_ip_id (monitor_ip_id),
    INDEX idx_key_date (key_date),
    INDEX idx_application (application),
    INDEX idx_created_at (created_at)
);

-- USB Device Logs Table
CREATE TABLE IF NOT EXISTS usb_device_logs (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    monitor_ip_id INT NOT NULL,
    device_date DATETIME NOT NULL,
    device_name VARCHAR(255) NOT NULL,
    device_path VARCHAR(500),
    device_type VARCHAR(100),
    action ENUM('Connected', 'Disconnected') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (monitor_ip_id) REFERENCES tbl_monitor_ips(id) ON DELETE CASCADE,
    INDEX idx_monitor_ip_id (monitor_ip_id),
    INDEX idx_device_date (device_date),
    INDEX idx_device_name (device_name),
    INDEX idx_action (action),
    INDEX idx_created_at (created_at)
);

-- Screenshot Metadata Table
CREATE TABLE IF NOT EXISTS screenshot_metadata (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    monitor_ip_id INT NOT NULL,
    filename VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    thumbnail_path VARCHAR(500),
    capture_datetime DATETIME NOT NULL,
    file_size BIGINT,
    image_width INT,
    image_height INT,
    file_extension VARCHAR(10) DEFAULT 'jpg',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (monitor_ip_id) REFERENCES tbl_monitor_ips(id) ON DELETE CASCADE,
    INDEX idx_monitor_ip_id (monitor_ip_id),
    INDEX idx_capture_datetime (capture_datetime),
    INDEX idx_filename (filename),
    INDEX idx_created_at (created_at)
);

-- ============================================================================
-- UTILITY TABLES
-- ============================================================================

-- Data Migration Status Table
CREATE TABLE IF NOT EXISTS data_migration_status (
    id INT AUTO_INCREMENT PRIMARY KEY,
    migration_type ENUM('browser_history', 'key_logs', 'usb_logs', 'screenshot_metadata') NOT NULL,
    monitor_ip_id INT NOT NULL,
    source_path VARCHAR(500) NOT NULL,
    records_migrated INT DEFAULT 0,
    migration_date DATETIME NOT NULL,
    status ENUM('pending', 'in_progress', 'completed', 'failed') DEFAULT 'pending',
    error_message TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (monitor_ip_id) REFERENCES tbl_monitor_ips(id) ON DELETE CASCADE,
    INDEX idx_migration_type (migration_type),
    INDEX idx_monitor_ip_id (monitor_ip_id),
    INDEX idx_status (status),
    INDEX idx_migration_date (migration_date)
);

-- Storage Statistics Table
CREATE TABLE IF NOT EXISTS storage_statistics (
    id INT AUTO_INCREMENT PRIMARY KEY,
    monitor_ip_id INT NOT NULL,
    data_type ENUM('browser_history', 'key_logs', 'usb_logs', 'screenshots') NOT NULL,
    month_year VARCHAR(7) NOT NULL, -- Format: YYYY-MM
    record_count BIGINT DEFAULT 0,
    total_size BIGINT DEFAULT 0, -- in bytes
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (monitor_ip_id) REFERENCES tbl_monitor_ips(id) ON DELETE CASCADE,
    UNIQUE KEY unique_stat (monitor_ip_id, data_type, month_year),
    INDEX idx_monitor_ip_id (monitor_ip_id),
    INDEX idx_data_type (data_type),
    INDEX idx_month_year (month_year)
);

-- ============================================================================
-- VIEWS FOR EASY DATA ACCESS
-- ============================================================================

-- View for recent browser history (last 7 days)
CREATE OR REPLACE VIEW recent_browser_history AS
SELECT 
    bhl.id,
    bhl.monitor_ip_id,
    mip.ip_address,
    mip.username,
    bhl.browser,
    bhl.url,
    bhl.title,
    bhl.visit_date,
    bhl.last_visit
FROM browser_history_logs bhl
JOIN tbl_monitor_ips mip ON bhl.monitor_ip_id = mip.id
WHERE bhl.visit_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)
ORDER BY bhl.visit_date DESC;

-- View for recent key logs (last 7 days)
CREATE OR REPLACE VIEW recent_key_logs AS
SELECT 
    kl.id,
    kl.monitor_ip_id,
    mip.ip_address,
    mip.username,
    kl.application,
    kl.key_pressed,
    kl.key_date
FROM key_logs kl
JOIN tbl_monitor_ips mip ON kl.monitor_ip_id = mip.id
WHERE kl.key_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)
ORDER BY kl.key_date DESC;

-- View for recent USB device activity (last 7 days)
CREATE OR REPLACE VIEW recent_usb_activity AS
SELECT 
    udl.id,
    udl.monitor_ip_id,
    mip.ip_address,
    mip.username,
    udl.device_name,
    udl.device_type,
    udl.action,
    udl.device_date
FROM usb_device_logs udl
JOIN tbl_monitor_ips mip ON udl.monitor_ip_id = mip.id
WHERE udl.device_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)
ORDER BY udl.device_date DESC;

-- View for recent screenshots (last 7 days)
CREATE OR REPLACE VIEW recent_screenshots AS
SELECT 
    sm.id,
    sm.monitor_ip_id,
    mip.ip_address,
    mip.username,
    sm.filename,
    sm.file_path,
    sm.thumbnail_path,
    sm.capture_datetime,
    sm.file_size,
    sm.image_width,
    sm.image_height
FROM screenshot_metadata sm
JOIN tbl_monitor_ips mip ON sm.monitor_ip_id = mip.id
WHERE sm.capture_datetime >= DATE_SUB(NOW(), INTERVAL 7 DAY)
ORDER BY sm.capture_datetime DESC;

-- ============================================================================
-- INSERT DEFAULT USERS (if not exists)
-- ============================================================================

INSERT IGNORE INTO users (username, email, password, role) VALUES
('admin', 'admin@monitor.local', '$2y$10$SJDyA1ns7.8VrFlhq1Zai.JJwh8K/.YGpz9DPFIC5TEmvZ0MRIvpS', 'admin'),
('user', 'user@monitor.local', '$2y$10$h88ExYxGWDypB2fOolxChuWLXf0zvi7XSBQr4ax/cL6HYAUj6yCci', 'user');

-- ============================================================================
-- FINAL COMMANDS
-- ============================================================================

-- Update root password (change this in production)
ALTER USER 'root'@'localhost' IDENTIFIED BY 'acc0924';

FLUSH PRIVILEGES;

-- Show table creation status
SHOW TABLES;

-- Show views
SHOW FULL TABLES WHERE Table_type = 'VIEW'; 