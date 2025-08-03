<?php
/**
 * Database Service Class
 * Handles all database operations for the Monitor application
 * Replaces stored procedures and triggers with PHP functions
 */

class DatabaseService {
    private $mysqli;
    
    public function __construct($mysqli) {
        $this->mysqli = $mysqli;
    }
    
    /**
     * Get monitor IP ID by IP address or MAC address
     */
    public function getMonitorIpId($ip_address = null, $mac_address = null) {
        $ip_id = null;
        
        if ($ip_address) {
            $stmt = $this->mysqli->prepare("SELECT id FROM tbl_monitor_ips WHERE ip_address = ?");
            $stmt->bind_param("s", $ip_address);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($row = $result->fetch_assoc()) {
                $ip_id = $row['id'];
            }
            $stmt->close();
        }
        
        if (!$ip_id && $mac_address) {
            $stmt = $this->mysqli->prepare("SELECT id FROM tbl_monitor_ips WHERE mac_address = ?");
            $stmt->bind_param("s", $mac_address);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($row = $result->fetch_assoc()) {
                $ip_id = $row['id'];
            }
            $stmt->close();
        }
        
        return $ip_id;
    }
    
    /**
     * Add browser history logs to database
     */
    public function addBrowserHistoryLogs($browserHistories, $ip_address = null, $mac_address = null) {
        $ip_id = $this->getMonitorIpId($ip_address, $mac_address);
        if (!$ip_id) {
            error_log("Could not find monitor IP ID for IP: $ip_address, MAC: $mac_address");
            return false;
        }
        
        $stmt = $this->mysqli->prepare("
            INSERT INTO browser_history_logs (monitor_ip_id, browser, url, title, last_visit, visit_date) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        
        $inserted_count = 0;
        foreach ($browserHistories as $browserHistory) {
            $browser = $browserHistory['browser'] ?? '';
            $url = $browserHistory['url'] ?? '';
            $title = $browserHistory['title'] ?? '';
            $lastVisit = $browserHistory['last_visit'] ?? date('Y-m-d H:i:s');
            $visitDate = $browserHistory['date'] ?? date('Y-m-d H:i:s');
            
            $stmt->bind_param("isssss", $ip_id, $browser, $url, $title, $lastVisit, $visitDate);
            if ($stmt->execute()) {
                $inserted_count++;
                // Update storage statistics
                $this->updateStorageStatistics($ip_id, 'browser_history', $visitDate, strlen($url) + strlen($title));
            }
        }
        
        $stmt->close();
        return $inserted_count;
    }
    
    /**
     * Add key logs to database
     */
    public function addKeyLogs($keyLogs, $ip_address = null, $mac_address = null) {
        $ip_id = $this->getMonitorIpId($ip_address, $mac_address);
        if (!$ip_id) {
            error_log("Could not find monitor IP ID for IP: $ip_address, MAC: $mac_address");
            return false;
        }
        
        $stmt = $this->mysqli->prepare("
            INSERT INTO key_logs (monitor_ip_id, key_date, application, key_pressed) 
            VALUES (?, ?, ?, ?)
        ");
        
        $inserted_count = 0;
        foreach ($keyLogs as $keyLog) {
            $keyDate = $keyLog['date'] ?? date('Y-m-d H:i:s');
            $application = $keyLog['application'] ?? '';
            $keyPressed = $keyLog['key'] ?? '';
            
            $stmt->bind_param("isss", $ip_id, $keyDate, $application, $keyPressed);
            if ($stmt->execute()) {
                $inserted_count++;
                // Update storage statistics
                $this->updateStorageStatistics($ip_id, 'key_logs', $keyDate, strlen($application) + strlen($keyPressed));
            }
        }
        
        $stmt->close();
        return $inserted_count;
    }
    
    /**
     * Add USB device logs to database
     */
    public function addUSBDeviceLogs($usbLogs, $ip_address = null, $mac_address = null) {
        $ip_id = $this->getMonitorIpId($ip_address, $mac_address);
        if (!$ip_id) {
            error_log("Could not find monitor IP ID for IP: $ip_address, MAC: $mac_address");
            return false;
        }
        
        $stmt = $this->mysqli->prepare("
            INSERT INTO usb_device_logs (monitor_ip_id, device_date, device_name, device_path, device_type, action) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        
        $inserted_count = 0;
        foreach ($usbLogs as $usbLog) {
            $deviceDate = $usbLog['date'] ?? date('Y-m-d H:i:s');
            $deviceName = $usbLog['device_name'] ?? '';
            $devicePath = $usbLog['device_path'] ?? '';
            $deviceType = $usbLog['device_type'] ?? '';
            $action = $usbLog['action'] ?? 'Connected';
            var_dump($deviceDate, $deviceName, $devicePath, $deviceType, $action);
            $stmt->bind_param("isssss", $ip_id, $deviceDate, $deviceName, $devicePath, $deviceType, $action);
            if ($stmt->execute()) {
                $inserted_count++;
                // Update storage statistics
                $this->updateStorageStatistics($ip_id, 'usb_logs', $deviceDate, strlen($deviceName) + strlen($devicePath) + strlen($deviceType));
            }
        }
        
        $stmt->close();
        return $inserted_count;
    }
    
    /**
     * Add screenshot metadata to database
     */
    public function addScreenshotMetadata($ip_id, $filename, $file_path, $thumbnail_path, $capture_datetime, $file_size, $image_width, $image_height, $file_extension = 'jpg') {
        $stmt = $this->mysqli->prepare("
            INSERT INTO screenshot_metadata (monitor_ip_id, filename, file_path, thumbnail_path, capture_datetime, file_size, image_width, image_height, file_extension) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->bind_param("issssiiis", $ip_id, $filename, $file_path, $thumbnail_path, $capture_datetime, $file_size, $image_width, $image_height, $file_extension);
        $result = $stmt->execute();
        $stmt->close();
        
        if ($result) {
            // Update storage statistics
            $this->updateStorageStatistics($ip_id, 'screenshots', $capture_datetime, $file_size);
        }
        
        return $result;
    }
    
    /**
     * Update storage statistics
     */
    private function updateStorageStatistics($monitor_ip_id, $data_type, $date, $size) {
        $month_year = date('Y-m', strtotime($date));
        
        $stmt = $this->mysqli->prepare("
            INSERT INTO storage_statistics (monitor_ip_id, data_type, month_year, record_count, total_size) 
            VALUES (?, ?, ?, 1, ?) 
            ON DUPLICATE KEY UPDATE 
            record_count = record_count + 1, 
            total_size = total_size + ?, 
            last_updated = CURRENT_TIMESTAMP
        ");
        
        $stmt->bind_param("issii", $monitor_ip_id, $data_type, $month_year, $size, $size);
        $stmt->execute();
        $stmt->close();
    }
    
    /**
     * Get recent browser history (last 7 days)
     */
    public function getRecentBrowserHistory($limit = 100) {
        $stmt = $this->mysqli->prepare("
            SELECT * FROM recent_browser_history 
            ORDER BY visit_date DESC 
            LIMIT ?
        ");
        
        $stmt->bind_param("i", $limit);
        $stmt->execute();
        $result = $stmt->get_result();
        $data = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        
        return $data;
    }
    
    /**
     * Get recent key logs (last 7 days)
     */
    public function getRecentKeyLogs($limit = 100) {
        $stmt = $this->mysqli->prepare("
            SELECT * FROM recent_key_logs 
            ORDER BY key_date DESC 
            LIMIT ?
        ");
        
        $stmt->bind_param("i", $limit);
        $stmt->execute();
        $result = $stmt->get_result();
        $data = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        
        return $data;
    }
    
    /**
     * Get recent USB activity (last 7 days)
     */
    public function getRecentUSBActivity($limit = 100) {
        $stmt = $this->mysqli->prepare("
            SELECT * FROM recent_usb_activity 
            ORDER BY device_date DESC 
            LIMIT ?
        ");
        
        $stmt->bind_param("i", $limit);
        $stmt->execute();
        $result = $stmt->get_result();
        $data = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        
        return $data;
    }
    
    /**
     * Get recent screenshots (last 7 days)
     */
    public function getRecentScreenshots($limit = 100) {
        $stmt = $this->mysqli->prepare("
            SELECT * FROM recent_screenshots 
            ORDER BY capture_datetime DESC 
            LIMIT ?
        ");
        
        $stmt->bind_param("i", $limit);
        $stmt->execute();
        $result = $stmt->get_result();
        $data = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        
        return $data;
    }
    
    /**
     * Get monthly statistics for a specific monitor IP
     */
    public function getMonthlyStatistics($monitor_ip_id, $month_year) {
        $stmt = $this->mysqli->prepare("
            SELECT 
                'browser_history' as data_type,
                COUNT(*) as record_count,
                SUM(LENGTH(url) + LENGTH(title)) as total_size
            FROM browser_history_logs 
            WHERE monitor_ip_id = ? 
            AND DATE_FORMAT(visit_date, '%Y-%m') = ?
            
            UNION ALL
            
            SELECT 
                'key_logs' as data_type,
                COUNT(*) as record_count,
                SUM(LENGTH(application) + LENGTH(key_pressed)) as total_size
            FROM key_logs 
            WHERE monitor_ip_id = ? 
            AND DATE_FORMAT(key_date, '%Y-%m') = ?
            
            UNION ALL
            
            SELECT 
                'usb_logs' as data_type,
                COUNT(*) as record_count,
                SUM(LENGTH(device_name) + LENGTH(device_path) + LENGTH(device_type)) as total_size
            FROM usb_device_logs 
            WHERE monitor_ip_id = ? 
            AND DATE_FORMAT(device_date, '%Y-%m') = ?
            
            UNION ALL
            
            SELECT 
                'screenshots' as data_type,
                COUNT(*) as record_count,
                SUM(file_size) as total_size
            FROM screenshot_metadata 
            WHERE monitor_ip_id = ? 
            AND DATE_FORMAT(capture_datetime, '%Y-%m') = ?
        ");
        
        $stmt->bind_param("isisisis", $monitor_ip_id, $month_year, $monitor_ip_id, $month_year, $monitor_ip_id, $month_year, $monitor_ip_id, $month_year);
        $stmt->execute();
        $result = $stmt->get_result();
        $data = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        
        return $data;
    }
    
    /**
     * Get storage statistics from the statistics table
     */
    public function getStorageStatistics($monitor_ip_id = null) {
        $sql = "SELECT * FROM storage_statistics";
        $params = [];
        $types = "";
        
        if ($monitor_ip_id) {
            $sql .= " WHERE monitor_ip_id = ?";
            $params[] = $monitor_ip_id;
            $types .= "i";
        }
        
        $sql .= " ORDER BY month_year DESC, data_type";
        
        $stmt = $this->mysqli->prepare($sql);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        $data = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        
        return $data;
    }
    
    /**
     * Update monitor IP last activity timestamps
     */
    public function updateMonitorActivity($ip_address, $activity_type = 'monitor') {
        $field = ($activity_type == 'browser') ? 'last_browser_tic' : 'last_monitor_tic';
        
        $stmt = $this->mysqli->prepare("
            UPDATE tbl_monitor_ips 
            SET $field = NOW(), updated_at = NOW() 
            WHERE ip_address = ?
        ");
        
        $stmt->bind_param("s", $ip_address);
        $result = $stmt->execute();
        $stmt->close();
        
        return $result;
    }
    
    /**
     * Get monitor IP information
     */
    public function getMonitorIP($ip_address) {
        $stmt = $this->mysqli->prepare("
            SELECT * FROM tbl_monitor_ips WHERE ip_address = ?
        ");
        
        $stmt->bind_param("s", $ip_address);
        $stmt->execute();
        $result = $stmt->get_result();
        $data = $result->fetch_assoc();
        $stmt->close();
        
        return $data;
    }
    
    /**
     * Create or update monitor IP record
     */
    public function createOrUpdateMonitorIP($ip_address, $mac_address = null, $username = null, $app_version = null) {
        $existing = $this->getMonitorIP($ip_address);
        
        if ($existing) {
            // Update existing record
            $stmt = $this->mysqli->prepare("
                UPDATE tbl_monitor_ips 
                SET mac_address = COALESCE(?, mac_address),
                    username = COALESCE(?, username),
                    app_version = COALESCE(?, app_version),
                    updated_at = NOW()
                WHERE ip_address = ?
            ");
            
            $stmt->bind_param("ssss", $mac_address, $username, $app_version, $ip_address);
        } else {
            // Create new record
            $stmt = $this->mysqli->prepare("
                INSERT INTO tbl_monitor_ips (ip_address, mac_address, username, app_version) 
                VALUES (?, ?, ?, ?)
            ");
            
            $stmt->bind_param("ssss", $ip_address, $mac_address, $username, $app_version);
        }
        
        $result = $stmt->execute();
        $stmt->close();
        
        return $result;
    }
    
    /**
     * Get data migration status
     */
    public function getMigrationStatus($monitor_ip_id = null) {
        $sql = "SELECT * FROM data_migration_status";
        $params = [];
        $types = "";
        
        if ($monitor_ip_id) {
            $sql .= " WHERE monitor_ip_id = ?";
            $params[] = $monitor_ip_id;
            $types .= "i";
        }
        
        $sql .= " ORDER BY migration_date DESC";
        
        $stmt = $this->mysqli->prepare($sql);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        $data = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        
        return $data;
    }
    
    /**
     * Update migration status
     */
    public function updateMigrationStatus($migration_type, $monitor_ip_id, $source_path, $records_migrated, $status, $error_message = null) {
        $stmt = $this->mysqli->prepare("
            INSERT INTO data_migration_status (migration_type, monitor_ip_id, source_path, records_migrated, migration_date, status, error_message) 
            VALUES (?, ?, ?, ?, NOW(), ?, ?)
        ");
        
        $stmt->bind_param("sisiss", $migration_type, $monitor_ip_id, $source_path, $records_migrated, $status, $error_message);
        $result = $stmt->execute();
        $stmt->close();
        
        return $result;
    }
}
?> 