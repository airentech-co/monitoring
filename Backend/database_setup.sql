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
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Create users table for admin authentication (actual table name used in codebase)
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL,
    email VARCHAR(255),
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'user', 'viewer') DEFAULT 'user',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
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