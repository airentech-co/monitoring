-- Database setup for monitoring system
-- Run this in phpMyAdmin or MySQL command line

-- Create database
CREATE DATABASE IF NOT EXISTS monitoring_db;
USE monitoring_db;

-- Create monitor_ips table
CREATE TABLE IF NOT EXISTS tbl_monitor_ips (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ip_address VARCHAR(45) NOT NULL,
    mac_address VARCHAR(17),
    name VARCHAR(255),
    type VARCHAR(50) DEFAULT 'user',
    os_status TINYINT DEFAULT 0,
    monitor_status TINYINT DEFAULT 0,
    last_monitor_tic DATETIME,
    last_browser_tic DATETIME,
    app_version VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Insert a default entry for testing
INSERT INTO tbl_monitor_ips (ip_address, name, type) 
VALUES ('127.0.0.1', 'localhost', 'admin')
ON DUPLICATE KEY UPDATE name = VALUES(name); 

FLUSH PRIVILEGES;
ALTER USER 'root'@'localhost' IDENTIFIED BY 'acc0924'; 