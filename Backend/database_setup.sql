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
    username VARCHAR(50) UNIQUE,
    type VARCHAR(50) DEFAULT 'user',
    os_status TINYINT DEFAULT 0,
    monitor_status TINYINT DEFAULT 0,
    last_monitor_tic DATETIME,
    last_browser_tic DATETIME,
    app_version VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_ip_address (ip_address),
    INDEX idx_username (username),
    INDEX idx_monitor_status (monitor_status),
    INDEX idx_last_monitor_tic (last_monitor_tic)
);

-- Create users table for admin authentication (actual table name used in codebase)
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(255) UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'user', 'viewer') DEFAULT 'user',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_username (username),
    INDEX idx_role (role)
);

-- Insert default users
-- admin - password: accadmin
-- user - password: user123
INSERT INTO users (username, email, password, role) VALUES
('admin', 'admin@monitor.local', '$2b$12$tE1iLxA/9L0O6yYmWxlvvePYX5aZ.M/9OXzVbyKnL3bVhPtEfsNaS', 'admin'),
('user', 'user@monitor.local', '$2b$12$7fWSdTq9AvvB9d8A14WwUe3cHZGLTx2h9A9qXt.qzJzzAK4e9B3C6', 'user')
ON DUPLICATE KEY UPDATE username = VALUES(username);

-- Insert a default entry for testing
INSERT INTO tbl_monitor_ips (ip_address, name, type, username) VALUES 
('127.0.0.1', 'localhost', 'admin', 'admin_localhost')
ON DUPLICATE KEY UPDATE name = VALUES(name);

-- Update root password (change this in production)
ALTER USER 'root'@'localhost' IDENTIFIED BY 'acc0924';

FLUSH PRIVILEGES; 