<?php
require_once BASEPATH . 'app/controllers/Base.php';
require_once BASEPATH . 'app/services/DataSyncService.php';
require_once BASEPATH . 'app/services/LogParserService.php';

class View extends Base {
    private $dataSync;
    private $logParser;
    
    public function __construct() {
        parent::__construct();
        $this->dataSync = new DataSyncService();
        $this->logParser = new LogParserService();
    }
    
    public function index() {
        // Sync monitors with actual data
        $this->dataSync->syncMonitorsWithData();
        
        $monitors = $this->db->fetchAll(
            "SELECT * FROM tbl_monitor_ips ORDER BY name ASC"
        );
        
        // Enhance monitors with live status information
        foreach ($monitors as &$monitor) {
            $ip_identifier = 'ip_' . $monitor['ip_address'];
            
            // Check for recent screenshots (within last 5 minutes)
            $latest_screenshot = $this->dataSync->getLatestScreenshot($ip_identifier);
            $has_recent_screenshot = false;
            if ($latest_screenshot && file_exists($latest_screenshot)) {
                $screenshot_time = filemtime($latest_screenshot);
                $has_recent_screenshot = (time() - $screenshot_time) <= 300; // 5 minutes
            }
            
            // Check for recent logs (within last 5 minutes)
            $logs = $this->dataSync->getLogs($ip_identifier);
            $has_recent_logs = false;
            $latest_log_time = 0;
            
            foreach (['browser_history', 'key_logs', 'usb_logs'] as $log_type) {
                if (!empty($logs[$log_type])) {
                    foreach ($logs[$log_type] as $log) {
                        if (file_exists($log['path'])) {
                            $log_time = filemtime($log['path']);
                            if ($log_time > $latest_log_time) {
                                $latest_log_time = $log_time;
                            }
                        }
                    }
                }
            }
            
            $has_recent_logs = (time() - $latest_log_time) <= 300; // 5 minutes
            
            // Determine live status
            $live_status = 'offline';
            $status_class = 'danger';
            $status_text = 'Offline';
            
            if ($has_recent_screenshot || $has_recent_logs) {
                $live_status = 'online';
                $status_class = 'success';
                $status_text = 'Online';
            } elseif ($monitor['monitor_status']) {
                $live_status = 'inactive';
                $status_class = 'warning';
                $status_text = 'Inactive';
            }
            
            // Add live status information to monitor data
            $monitor['live_status'] = $live_status;
            $monitor['status_class'] = $status_class;
            $monitor['status_text'] = $status_text;
            $monitor['has_recent_screenshot'] = $has_recent_screenshot;
            $monitor['has_recent_logs'] = $has_recent_logs;
            $monitor['latest_activity'] = max($latest_log_time, $latest_screenshot ? filemtime($latest_screenshot) : 0);
        }
        
        $this->view('view/index', ['monitors' => $monitors]);
    }
    

    
    public function logs_detail($ip_id) {
        // Sync monitors with actual data
        $this->dataSync->syncMonitorsWithData();
        
        $monitor = $this->db->fetch(
            "SELECT * FROM tbl_monitor_ips WHERE id = :id",
            ['id' => $ip_id]
        );
        
        if (!$monitor) {
            $this->redirect('view');
        }
        
        // Fix: Use the actual directory format (dots preserved)
        $ip_identifier = 'ip_' . $monitor['ip_address'];
        $logs = $this->dataSync->getLogs($ip_identifier);
        
        $this->view('view/logs_detail', [
            'monitor' => $monitor,
            'logs' => $logs
        ]);
    }
    
    public function api_screenshots() {
        // Sync monitors with actual data
        $this->dataSync->syncMonitorsWithData();
        
        // Get pagination parameters
        $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
        $per_page = isset($_GET['per_page']) ? max(1, intval($_GET['per_page'])) : 150;
        $offset = ($page - 1) * $per_page;
        
        // Get monitor filter
        $selected_monitors = [];
        if (isset($_GET['monitors']) && !empty($_GET['monitors'])) {
            $selected_monitors = array_map('intval', explode(',', $_GET['monitors']));
        }
        
        // Get specific monitor ID for all screenshots
        $specific_monitor_id = isset($_GET['monitor_id']) ? intval($_GET['monitor_id']) : null;
        
        $monitors = $this->db->fetchAll(
            "SELECT * FROM tbl_monitor_ips ORDER BY name ASC"
        );
        
        // Filter monitors if specific ones are selected
        if (!empty($selected_monitors)) {
            $monitors = array_filter($monitors, function($monitor) use ($selected_monitors) {
                return in_array($monitor['id'], $selected_monitors);
            });
        }
        
        $all_screenshots = [];
        
        if ($specific_monitor_id) {
            // Load all screenshots for a specific monitor
            $monitor = $this->db->fetch(
                "SELECT * FROM tbl_monitor_ips WHERE id = :id",
                ['id' => $specific_monitor_id]
            );
            
            if ($monitor) {
                $ip_identifier = 'ip_' . $monitor['ip_address'];
                
                // Get date filter parameters
                $start_date = isset($_GET['start_date']) ? $_GET['start_date'] : null;
                $end_date = isset($_GET['end_date']) ? $_GET['end_date'] : null;
                
                $screenshots = $this->dataSync->getAllScreenshots($ip_identifier, $start_date, $end_date);
                
                foreach ($screenshots as $screenshot) {
                    $all_screenshots[] = [
                        'monitor' => $monitor,
                        'screenshot' => $screenshot['full_path'],
                        'thumbnail' => $screenshot['thumbnail_path'],
                        'modified_time' => $screenshot['modified']
                    ];
                }
            }
        } else {
            // Load latest screenshots for selected monitors
            foreach ($monitors as $monitor) {
                $ip_identifier = 'ip_' . $monitor['ip_address'];
                $latest_screenshot = $this->dataSync->getLatestScreenshot($ip_identifier);
                
                if ($latest_screenshot) {
                    $all_screenshots[] = [
                        'monitor' => $monitor,
                        'screenshot' => $latest_screenshot,
                        'thumbnail' => $this->getThumbnail($monitor['ip_address'], $latest_screenshot),
                        'modified_time' => date('Y-m-d H:i:s', filemtime($latest_screenshot))
                    ];
                }
            }
        }
        
        // Sort by modified time (newest first)
        usort($all_screenshots, function($a, $b) {
            return strtotime($b['modified_time']) - strtotime($a['modified_time']);
        });
        
        // Calculate pagination info
        $total_screenshots = count($all_screenshots);
        $total_pages = ceil($total_screenshots / $per_page);
        
        // Apply pagination
        $paginated_screenshots = array_slice($all_screenshots, $offset, $per_page);
        
        $this->json([
            'screenshots' => $paginated_screenshots,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $per_page,
                'total_screenshots' => $total_screenshots,
                'total_pages' => $total_pages,
                'has_next' => $page < $total_pages,
                'has_prev' => $page > 1
            ]
        ]);
    }
    
    public function api_logs($ip_id) {
        $monitor = $this->db->fetch(
            "SELECT * FROM tbl_monitor_ips WHERE id = :id",
            ['id' => $ip_id]
        );
        
        if (!$monitor) {
            $this->json(['error' => 'Monitor not found']);
        }
        
        // Fix: Use the actual directory format (dots preserved)
        $ip_identifier = 'ip_' . $monitor['ip_address'];
        $logs = $this->dataSync->getLogs($ip_identifier);
        $this->json($logs);
    }
    
    public function api_all_screenshots($ip_id) {
        $monitor = $this->db->fetch(
            "SELECT * FROM tbl_monitor_ips WHERE id = :id",
            ['id' => $ip_id]
        );
        if (!$monitor) {
            $this->json(['error' => 'Monitor not found']);
        }
        // Get pagination parameters
        $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
        $per_page = isset($_GET['per_page']) ? max(1, intval($_GET['per_page'])) : 150;
        $offset = ($page - 1) * $per_page;
        // Get date filter parameters
        $start_date = isset($_GET['start_date']) ? $_GET['start_date'] : null;
        $end_date = isset($_GET['end_date']) ? $_GET['end_date'] : null;
        // Use the actual directory format (dots preserved)
        $ip_identifier = 'ip_' . $monitor['ip_address'];
        $screenshots = $this->dataSync->getAllScreenshots($ip_identifier, $start_date, $end_date);
        // Calculate pagination info
        $total_screenshots = count($screenshots);
        $total_pages = ceil($total_screenshots / $per_page);
        // Apply pagination
        $paginated_screenshots = array_slice($screenshots, $offset, $per_page);
        $this->json([
            'screenshots' => $paginated_screenshots,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $per_page,
                'total_screenshots' => $total_screenshots,
                'total_pages' => $total_pages,
                'has_next' => $page < $total_pages,
                'has_prev' => $page > 1
            ]
        ]);
    }
    
    public function api_parsed_log($ip_id, $log_type, $file_path) {
        $monitor = $this->db->fetch(
            "SELECT * FROM tbl_monitor_ips WHERE id = :id",
            ['id' => $ip_id]
        );
        
        if (!$monitor) {
            $this->json(['error' => 'Monitor not found']);
        }
        
        // Decode the file path
        $file_path = urldecode($file_path);
        
        // Construct full path
        $full_path = BASEPATH . 'data/logs/ip_' . $monitor['ip_address'] . '/' . $file_path;
        
        if (!file_exists($full_path)) {
            $this->json(['error' => 'Log file not found']);
        }
        
        // Read file content
        $content = file_get_contents($full_path);
        if ($content === false) {
            $this->json(['error' => 'Unable to read log file']);
        }
        
        // Parse content based on log type
        $parsedData = [];
        $headers = [];
        
        switch ($log_type) {
            case 'browser_history':
                $parsedData = $this->logParser->parseBrowserHistory($content);
                break;
            case 'key_logs':
                $parsedData = $this->logParser->parseKeyLogs($content);
                break;
            case 'usb_logs':
                $parsedData = $this->logParser->parseUsbLogs($content);
                break;
            default:
                $parsedData = $this->logParser->parseGenericLog($content, $log_type);
                break;
        }
        
        $headers = $this->logParser->getTableHeaders($parsedData, $log_type);
        
        $this->json([
            'success' => true,
            'data' => $parsedData,
            'headers' => $headers,
            'log_type' => $log_type,
            'file_path' => $file_path
        ]);
    }
    
    public function api_live_status() {
        // Sync monitors with actual data
        $this->dataSync->syncMonitorsWithData();
        
        $monitors = $this->db->fetchAll(
            "SELECT * FROM tbl_monitor_ips ORDER BY name ASC"
        );
        
        // Enhance monitors with live status information
        foreach ($monitors as &$monitor) {
            $ip_identifier = 'ip_' . $monitor['ip_address'];
            
            // Check for recent screenshots (within last 5 minutes)
            $latest_screenshot = $this->dataSync->getLatestScreenshot($ip_identifier);
            $has_recent_screenshot = false;
            if ($latest_screenshot && file_exists($latest_screenshot)) {
                $screenshot_time = filemtime($latest_screenshot);
                $has_recent_screenshot = (time() - $screenshot_time) <= 300; // 5 minutes
            }
            
            // Check for recent logs (within last 5 minutes)
            $logs = $this->dataSync->getLogs($ip_identifier);
            $has_recent_logs = false;
            $latest_log_time = 0;
            
            foreach (['browser_history', 'key_logs', 'usb_logs'] as $log_type) {
                if (!empty($logs[$log_type])) {
                    foreach ($logs[$log_type] as $log) {
                        if (file_exists($log['path'])) {
                            $log_time = filemtime($log['path']);
                            if ($log_time > $latest_log_time) {
                                $latest_log_time = $log_time;
                            }
                        }
                    }
                }
            }
            
            $has_recent_logs = (time() - $latest_log_time) <= 300; // 5 minutes
            
            // Determine live status
            $live_status = 'offline';
            $status_text = 'Offline';
            
            if ($has_recent_screenshot || $has_recent_logs) {
                $live_status = 'online';
                $status_text = 'Online';
            } elseif ($monitor['monitor_status']) {
                $live_status = 'inactive';
                $status_text = 'Inactive';
            }
            
            // Add live status information to monitor data
            $monitor['live_status'] = $live_status;
            $monitor['status_text'] = $status_text;
            $monitor['has_recent_screenshot'] = $has_recent_screenshot;
            $monitor['has_recent_logs'] = $has_recent_logs;
            $monitor['latest_activity'] = max($latest_log_time, $latest_screenshot ? filemtime($latest_screenshot) : 0);
        }
        
        $this->json($monitors);
    }
    
    public function view_file($file_path) {
        // Security: Only allow viewing files from the data directories
        $file_path = urldecode($file_path);
        
        // Convert relative path to absolute path if needed
        $absolute_path = $file_path;
        if (!file_exists($file_path)) {
            // Try with BASEPATH prefix
            $absolute_path = BASEPATH . $file_path;
            if (file_exists($absolute_path)) {
                $file_path = $absolute_path;
            }
        }
        
        // Normalize paths for comparison
        $normalized_file_path = realpath($absolute_path);
        $normalized_base = realpath(BASEPATH);
        
        // Check if file exists and is within allowed directories
        $allowed_dirs = [SCREENS_DIR, THUMBNAILS_DIR, LOGS_DIR];
        
        $is_allowed = false;
        foreach ($allowed_dirs as $allowed_dir) {
            $allowed_absolute = realpath(BASEPATH . $allowed_dir);
            if ($allowed_absolute && $normalized_file_path && 
                strpos($normalized_file_path, $allowed_absolute) === 0) {
                $is_allowed = true;
                break;
            }
        }
        
        if (!$is_allowed || !file_exists($absolute_path)) {
            http_response_code(404);
            echo "File not found or access denied";
            return;
        }
        
        $extension = pathinfo($absolute_path, PATHINFO_EXTENSION);
        
        // Set appropriate content type
        switch (strtolower($extension)) {
            case 'jpg':
            case 'jpeg':
                header('Content-Type: image/jpeg');
                break;
            case 'png':
                header('Content-Type: image/png');
                break;
            case 'txt':
                header('Content-Type: text/plain');
                break;
            default:
                header('Content-Type: application/octet-stream');
        }
        
        // Output file content
        readfile($absolute_path);
    }
    
    private function getThumbnail($ip_address, $screenshot_path) {
        // Fix: Use the actual directory format (dots preserved)
        $ip_identifier = 'ip_' . $ip_address;
        $thumb_dir = THUMBNAILS_DIR . $ip_identifier;
        
        // Get relative path from screenshots directory
        $screens_dir = SCREENS_DIR . $ip_identifier;
        $relative_path = str_replace($screens_dir . '/', '', $screenshot_path);
        $thumbnail_path = $thumb_dir . '/' . $relative_path;
        
        return file_exists($thumbnail_path) ? $thumbnail_path : $screenshot_path;
    }
    
    public function formatFileSize($bytes) {
        if ($bytes === 0) return "0 Bytes";
        $k = 1024;
        $sizes = ["Bytes", "KB", "MB", "GB"];
        $i = floor(log($bytes) / log($k));
        return round($bytes / pow($k, $i), 2) . " " . $sizes[$i];
    }
    
    public function test() {
        echo "Test method called successfully!";
        echo "<br>BASEPATH: " . BASEPATH;
        echo "<br>Current file: " . __FILE__;
    }
}
?> 