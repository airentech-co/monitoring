# Database Migration Guide - PHP-Based Approach

## Overview
This guide explains the migration from the current file-based storage system to a new database-based storage system using PHP functions instead of stored procedures, triggers, or partitions. This approach avoids MariaDB compatibility issues and provides better control over database operations.

## Benefits of PHP-Based Database Storage

### Performance Improvements
- **Faster Queries**: Direct SQL queries with proper indexing
- **Reduced I/O**: No more file system operations for log storage
- **Better Concurrency**: Database transactions handle concurrent access
- **Optimized Storage**: Efficient data types and indexing strategies

### Data Management
- **Structured Queries**: Easy to search, filter, and analyze data
- **Data Integrity**: Foreign key constraints and validation
- **Backup/Restore**: Standard database backup procedures
- **Data Archival**: Easy to archive old data by date ranges

### Scalability
- **Horizontal Scaling**: Can distribute across multiple database servers
- **Vertical Scaling**: Better performance with more powerful hardware
- **Data Growth**: Handles large volumes of data efficiently
- **Future-Proof**: Easy to add new data types and features

## Database Schema

### Core Tables

#### Browser History Logs
```sql
CREATE TABLE browser_history_logs (
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
```

#### Key Logs
```sql
CREATE TABLE key_logs (
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
```

#### USB Device Logs
```sql
CREATE TABLE usb_device_logs (
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
```

#### Screenshot Metadata
```sql
CREATE TABLE screenshot_metadata (
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
```

### Utility Tables

#### Storage Statistics
```sql
CREATE TABLE storage_statistics (
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
```

#### Data Migration Status
```sql
CREATE TABLE data_migration_status (
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
```

### Views for Easy Data Access

#### Recent Browser History (Last 7 Days)
```sql
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
```

#### Recent Key Logs (Last 7 Days)
```sql
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
```

## PHP Database Service

The `DatabaseService` class provides all database operations:

### Key Methods

#### Data Insertion
- `addBrowserHistoryLogs($browserHistories, $ip_address, $mac_address)`
- `addKeyLogs($keyLogs, $ip_address, $mac_address)`
- `addUSBDeviceLogs($usbLogs, $ip_address, $mac_address)`
- `addScreenshotMetadata($ip_id, $filename, $file_path, $thumbnail_path, $capture_datetime, $file_size, $image_width, $image_height, $file_extension)`

#### Data Retrieval
- `getRecentBrowserHistory($limit = 100)`
- `getRecentKeyLogs($limit = 100)`
- `getRecentUSBActivity($limit = 100)`
- `getRecentScreenshots($limit = 100)`
- `getMonthlyStatistics($monitor_ip_id, $month_year)`
- `getStorageStatistics($monitor_ip_id = null)`

#### Monitor Management
- `getMonitorIpId($ip_address, $mac_address)`
- `createOrUpdateMonitorIP($ip_address, $mac_address, $username, $app_version)`
- `updateMonitorActivity($ip_address, $activity_type)`

## Migration Process

### Step 1: Database Setup
```bash
# Run the database schema
mysql -u root -p < database_schema_v2_php.sql
```

### Step 2: Test Migration
```bash
# Test migration without actually migrating data
php migrate_to_database_php.php --dry-run
```

### Step 3: Perform Migration
```bash
# Migrate all data
php migrate_to_database_php.php

# Or migrate specific IP
php migrate_to_database_php.php --ip-address=192.168.1.40
```

### Step 4: Update Backend Files
Replace the old files with the new PHP-based versions:

1. **Event Handler**: Replace `eventhandler.php` with `eventhandler_v2_php.php`
2. **Web API**: Replace `webapi.php` with `webapi_v2_php.php`
3. **Add Database Service**: Copy `services/DatabaseService.php` to your services directory

### Step 5: Verify Migration
```sql
-- Check migration status
SELECT * FROM data_migration_status ORDER BY migration_date DESC;

-- Check storage statistics
SELECT * FROM storage_statistics ORDER BY month_year DESC;

-- Verify recent data
SELECT * FROM recent_browser_history LIMIT 10;
SELECT * FROM recent_key_logs LIMIT 10;
SELECT * FROM recent_usb_activity LIMIT 10;
SELECT * FROM recent_screenshots LIMIT 10;
```

## New API Endpoints

### Updated Event Handler (`eventhandler_v2_php.php`)
- **Endpoint**: `/eventhandler_v2_php.php`
- **Method**: POST
- **Content-Type**: application/json
- **Uses**: `DatabaseService` class for all operations

### Updated Web API (`webapi_v2_php.php`)
- **Endpoint**: `/webapi_v2_php.php?type=upload`
- **Method**: POST
- **Content-Type**: multipart/form-data
- **Uses**: `DatabaseService` class for metadata storage

## Data Access Examples

### Recent Browser History
```php
$dbService = new DatabaseService($mysqli);
$recent_browser = $dbService->getRecentBrowserHistory(50);
```

### Key Logs by Application
```sql
SELECT application, COUNT(*) as key_count, 
       MIN(key_date) as first_key, MAX(key_date) as last_key
FROM key_logs 
WHERE monitor_ip_id = 1 
  AND key_date >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
GROUP BY application 
ORDER BY key_count DESC;
```

### USB Device Activity
```sql
SELECT device_name, action, COUNT(*) as event_count,
       MIN(device_date) as first_seen, MAX(device_date) as last_seen
FROM usb_device_logs 
WHERE monitor_ip_id = 1 
  AND device_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)
GROUP BY device_name, action 
ORDER BY last_seen DESC;
```

### Screenshot Statistics
```sql
SELECT DATE(capture_datetime) as capture_date,
       COUNT(*) as screenshot_count,
       SUM(file_size) as total_size,
       AVG(file_size) as avg_size
FROM screenshot_metadata 
WHERE monitor_ip_id = 1 
  AND capture_datetime >= DATE_SUB(NOW(), INTERVAL 30 DAY)
GROUP BY DATE(capture_datetime) 
ORDER BY capture_date DESC;
```

## Performance Optimizations

### Indexes
- All tables have appropriate indexes on frequently queried columns
- Composite indexes for complex queries
- Foreign key indexes for joins

### Query Optimization
- Prepared statements for all database operations
- Efficient data types (BIGINT for IDs, appropriate VARCHAR lengths)
- Proper date indexing for time-based queries

### Storage Optimization
- Automatic storage statistics tracking
- Monthly data aggregation
- Efficient text storage with proper indexing

## Backup and Maintenance

### Regular Backups
```bash
# Daily backup
mysqldump -u root -p monitoring_db > backup_$(date +%Y%m%d).sql

# Backup specific tables
mysqldump -u root -p monitoring_db browser_history_logs key_logs usb_device_logs screenshot_metadata > logs_backup_$(date +%Y%m%d).sql
```

### Data Archival
```sql
-- Archive old data (older than 1 year)
INSERT INTO archived_browser_history_logs 
SELECT * FROM browser_history_logs 
WHERE visit_date < DATE_SUB(NOW(), INTERVAL 1 YEAR);

DELETE FROM browser_history_logs 
WHERE visit_date < DATE_SUB(NOW(), INTERVAL 1 YEAR);
```

### Storage Statistics Update
```php
// Update storage statistics for a specific month
$dbService->updateStorageStatistics($monitor_ip_id, 'browser_history', '2025-01', $size);
```

## Troubleshooting

### Common Issues

#### Database Connection Issues
```php
// Check database connection
$mysqli = new mysqli($config->dbhost, $config->dbuser, $config->dbpassword, $config->dbname);
if ($mysqli->connect_error) {
    error_log("Database connection failed: " . $mysqli->connect_error);
}
```

#### Migration Issues
```bash
# Check migration status
SELECT * FROM data_migration_status WHERE status = 'failed';

# Retry failed migrations
php migrate_to_database_php.php --ip-address=192.168.1.40
```

#### Performance Issues
```sql
-- Check slow queries
SHOW PROCESSLIST;

-- Analyze table performance
ANALYZE TABLE browser_history_logs, key_logs, usb_device_logs, screenshot_metadata;
```

### Monitoring Queries

#### Data Volume Monitoring
```sql
-- Check data growth
SELECT 
    data_type,
    month_year,
    SUM(record_count) as total_records,
    SUM(total_size) as total_size_bytes
FROM storage_statistics 
WHERE monitor_ip_id = 1
GROUP BY data_type, month_year 
ORDER BY month_year DESC, data_type;
```

#### Performance Monitoring
```sql
-- Check recent activity
SELECT 
    monitor_ip_id,
    COUNT(*) as recent_records,
    MAX(created_at) as last_activity
FROM browser_history_logs 
WHERE created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
GROUP BY monitor_ip_id;
```

## Rollback Plan

If you need to revert to the file-based system:

### Step 1: Backup Database
```bash
mysqldump -u root -p monitoring_db > rollback_backup_$(date +%Y%m%d).sql
```

### Step 2: Restore Original Files
```bash
# Restore original event handler and web API
cp eventhandler.php.bak eventhandler.php
cp webapi.php.bak webapi.php
```

### Step 3: Verify File-Based System
```bash
# Test that file-based logging is working
curl -X POST http://your-server/eventhandler.php -d '{"Event":"Tic","Version":"1.0","MacAddress":"00:11:22:33:44:55","Username":"test"}'
```

## Future Enhancements

### Planned Features
- **Data Compression**: Compress old log data to save storage
- **Advanced Analytics**: Dashboard with usage statistics and trends
- **Real-time Monitoring**: WebSocket-based real-time data updates
- **Data Export**: Export functionality for compliance and analysis
- **Multi-tenant Support**: Support for multiple organizations

### Performance Monitoring
- **Query Performance**: Monitor slow queries and optimize
- **Storage Growth**: Track data growth and plan capacity
- **User Activity**: Monitor user activity patterns
- **System Health**: Monitor database health and performance

## Conclusion

The PHP-based database migration approach provides a robust, scalable, and maintainable solution for storing monitor application data. By using PHP functions instead of database-specific features like stored procedures and triggers, we avoid compatibility issues while maintaining full functionality and performance.

The new system offers significant improvements in data management, query performance, and scalability while maintaining backward compatibility with existing client applications. 