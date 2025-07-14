-- Database setup for monitoring system
-- Run this in phpMyAdmin or MySQL command line

-- Create database
CREATE DATABASE IF NOT EXISTS monitoring_db;
USE monitoring_db;

-- Create monitor_ips table (actual table name used in codebase)
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

-- Create users table for admin authentication (actual table name used in codebase)
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

-- Create cache table for performance optimization
CREATE TABLE IF NOT EXISTS tbl_monitor_cache (
    id INT AUTO_INCREMENT PRIMARY KEY,
    monitor_id INT NOT NULL,
    cache_key VARCHAR(100) NOT NULL,
    cache_value TEXT,
    cache_type ENUM('status', 'screenshots', 'logs', 'metadata') NOT NULL,
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NULL,
    INDEX idx_monitor_cache_key (monitor_id, cache_key),
    INDEX idx_cache_type (cache_type),
    INDEX idx_expires_at (expires_at),
    FOREIGN KEY (monitor_id) REFERENCES tbl_monitor_ips(id) ON DELETE CASCADE
);

-- Create file metadata table for faster file operations
CREATE TABLE IF NOT EXISTS tbl_file_metadata (
    id INT AUTO_INCREMENT PRIMARY KEY,
    monitor_id INT NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_type ENUM('screenshot', 'thumbnail', 'browser_log', 'key_log', 'usb_log') NOT NULL,
    file_size BIGINT,
    modified_time DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_monitor_file_type (monitor_id, file_type),
    INDEX idx_modified_time (modified_time),
    INDEX idx_file_path (file_path(255)),
    FOREIGN KEY (monitor_id) REFERENCES tbl_monitor_ips(id) ON DELETE CASCADE
);

-- Insert default users
-- admin - password: admin123
-- user - password: user123
INSERT INTO users (username, email, password, role) VALUES
('admin', 'admin@monitor.local', '$2y$10$SJDyA1ns7.8VrFlhq1Zai.JJwh8K/.YGpz9DPFIC5TEmvZ0MRIvpS', 'admin'),
('user', 'user@monitor.local', '$2y$10$h88ExYxGWDypB2fOolxChuWLXf0zvi7XSBQr4ax/cL6HYAUj6yCci', 'user')
ON DUPLICATE KEY UPDATE username = VALUES(username);

-- Update root password (change this in production)
ALTER USER 'root'@'localhost' IDENTIFIED BY 'acc0924';

FLUSH PRIVILEGES; 