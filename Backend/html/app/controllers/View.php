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
        // Get monitors from database only (no expensive file scanning)
        $monitors = $this->db->fetchAll(
            "SELECT * FROM tbl_monitor_ips ORDER BY name ASC"
        );
        
        // Add basic status based on database data only
        foreach ($monitors as &$monitor) {
            // Determine status based on last_monitor_tic
            $last_tic = strtotime($monitor['last_monitor_tic']);
            $time_diff = time() - $last_tic;
            
            if ($time_diff <= 300) { // 5 minutes
                $monitor['live_status'] = 'online';
                $monitor['status_class'] = 'success';
                $monitor['status_text'] = 'Online';
            } elseif ($time_diff <= 3600) { // 1 hour
                $monitor['live_status'] = 'inactive';
                $monitor['status_class'] = 'warning';
                $monitor['status_text'] = 'Inactive';
            } else {
                $monitor['live_status'] = 'offline';
                $monitor['status_class'] = 'danger';
                $monitor['status_text'] = 'Offline';
            }
            
            $monitor['latest_activity'] = $last_tic;
        }
        
        $this->view('view/index', ['monitors' => $monitors]);
    }
    

    
    public function logs_detail($ip_id) {
        $monitor = $this->db->fetch(
            "SELECT * FROM tbl_monitor_ips WHERE id = :id",
            ['id' => $ip_id]
        );
        
        if (!$monitor) {
            $this->redirect('view');
        }
        
        // Get logs only when needed (not on every page load)
        $ip_identifier = 'ip_' . $monitor['ip_address'];
        $logs = $this->dataSync->getLogs($ip_identifier);
        
        $this->view('view/logs_detail', [
            'monitor' => $monitor,
            'logs' => $logs
        ]);
    }
    
    public function api_screenshots() {
        // Get pagination parameters
        $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
        $per_page = isset($_GET['per_page']) ? max(1, intval($_GET['per_page'])) : 50; // Reduced for better performance
        $offset = ($page - 1) * $per_page;
        
        // Get monitor filter
        $selected_monitors = [];
        if (isset($_GET['monitors']) && !empty($_GET['monitors'])) {
            $selected_monitors = array_map('intval', explode(',', $_GET['monitors']));
        }
        
        // Get specific monitor ID for all screenshots
        $specific_monitor_id = isset($_GET['monitor_id']) ? intval($_GET['monitor_id']) : null;
        
        if ($specific_monitor_id) {
            // Load paginated screenshots for a specific monitor
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
                
                // Calculate pagination info
                $total_screenshots = count($screenshots);
                $total_pages = ceil($total_screenshots / $per_page);
                
                // Apply pagination
                $paginated_screenshots = array_slice($screenshots, $offset, $per_page);
                
                // Add monitor info and ensure thumbnail/full image fields
                foreach ($paginated_screenshots as &$screenshot) {
                    $screenshot['monitor'] = $monitor;
                    $screenshot['thumbnail'] = $this->getRelativePath($screenshot['thumbnail_path']);
                    $screenshot['screenshot'] = $this->getRelativePath($screenshot['full_path']);
                }
                
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
            } else {
                $this->json(['error' => 'Monitor not found']);
            }
        } else {
            // Load only latest screenshots for all monitors (right panel) - OPTIMIZED
            $monitors = $this->db->fetchAll(
                "SELECT * FROM tbl_monitor_ips ORDER BY name ASC"
            );
            
            // Filter monitors if specific ones are selected
            if (!empty($selected_monitors)) {
                $monitors = array_filter($monitors, function($monitor) use ($selected_monitors) {
                    return in_array($monitor['id'], $selected_monitors);
                });
            }
            
            $latest_screenshots = [];
            
            // Optimized: Get latest screenshots efficiently
            foreach ($monitors as $monitor) {
                $ip_identifier = 'ip_' . $monitor['ip_address'];
                $latest_screenshot = $this->getLatestScreenshotOptimized($ip_identifier);
                
                if ($latest_screenshot) {
                    $latest_screenshots[] = [
                        'monitor' => $monitor,
                        'screenshot' => $this->getRelativePath($latest_screenshot['full_path']),
                        'thumbnail' => $this->getRelativePath($latest_screenshot['thumbnail_path']),
                        'modified_time' => $latest_screenshot['modified_time']
                    ];
                }
            }
            
            // Sort by modified time (newest first)
            usort($latest_screenshots, function($a, $b) {
                return strtotime($b['modified_time']) - strtotime($a['modified_time']);
            });
            
            // Calculate pagination info
            $total_screenshots = count($latest_screenshots);
            $total_pages = ceil($total_screenshots / $per_page);
            
            // Apply pagination
            $paginated_screenshots = array_slice($latest_screenshots, $offset, $per_page);
            
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
        $per_page = isset($_GET['per_page']) ? max(1, intval($_GET['per_page'])) : 50; // Reduced for better performance
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
        // Get monitors from database only (fast)
        $monitors = $this->db->fetchAll(
            "SELECT * FROM tbl_monitor_ips ORDER BY name ASC"
        );
        
        // Add status based on database data only
        foreach ($monitors as &$monitor) {
            // Use last_monitor_tic for real-time status (updated by client Tic events every 5 minutes)
            $last_tic = $monitor['last_monitor_tic'] ? strtotime($monitor['last_monitor_tic']) : 0;
            $time_diff = time() - $last_tic;
            
            if ($last_tic === 0) {
                // No activity recorded
                $monitor['live_status'] = 'offline';
                $monitor['status_text'] = 'Never';
                $monitor['latest_activity'] = 0;
            } elseif ($time_diff <= 600) { // 10 minutes - very recent activity (within 2 Tic cycles)
                $monitor['live_status'] = 'online';
                $monitor['status_text'] = 'Online';
                $monitor['latest_activity'] = $last_tic;
            } elseif ($time_diff <= 1800) { // 30 minutes - recent activity (within 6 Tic cycles)
                $monitor['live_status'] = 'online';
                $monitor['status_text'] = 'Online';
                $monitor['latest_activity'] = $last_tic;
            } elseif ($time_diff <= 3600) { // 1 hour - some activity (within 12 Tic cycles)
                $monitor['live_status'] = 'inactive';
                $monitor['status_text'] = 'Inactive';
                $monitor['latest_activity'] = $last_tic;
            } else {
                // More than 1 hour - likely offline
                $monitor['live_status'] = 'offline';
                $monitor['status_text'] = 'Offline';
                $monitor['latest_activity'] = $last_tic;
            }
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
        
        // Convert screenshot path to thumbnail path
        $thumbnail_path = str_replace(SCREENS_DIR, THUMBNAILS_DIR, $screenshot_path);
        
        // Check if thumbnail exists, if not return the original screenshot path
        if (file_exists($thumbnail_path)) {
            return $thumbnail_path;
        } else {
            // If thumbnail doesn't exist, return the original screenshot path
            return $screenshot_path;
        }
    }
    
    /**
     * Optimized method to get the latest screenshot for an IP
     * This method is much faster than the DataSyncService version
     */
    private function getLatestScreenshotOptimized($ip_identifier) {
        $screens_dir = SCREENS_DIR . $ip_identifier;
        
        if (!is_dir($screens_dir)) {
            return null;
        }
        
        $latest_file = null;
        $latest_time = 0;
        
        // Use scandir instead of RecursiveIteratorIterator for better performance
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($screens_dir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::LEAVES_ONLY
        );
        
        foreach ($iterator as $file) {
            if ($file->isFile() && in_array($file->getExtension(), ['jpg', 'jpeg', 'png'])) {
                $file_time = $file->getMTime();
                if ($file_time > $latest_time) {
                    $latest_time = $file_time;
                    $latest_file = $file->getPathname();
                }
            }
        }
        
        if (!$latest_file) {
            return null;
        }
        
        // Get thumbnail path
        $thumbnail_path = str_replace(SCREENS_DIR, THUMBNAILS_DIR, $latest_file);
        
        return [
            'full_path' => $latest_file,
            'thumbnail_path' => file_exists($thumbnail_path) ? $thumbnail_path : $latest_file,
            'modified_time' => date('Y-m-d H:i:s', $latest_time)
        ];
    }
    
    /**
     * Convert absolute file path to relative URL path
     */
    private function getRelativePath($absolute_path) {
        // Remove BASEPATH from the beginning of the path
        $relative_path = str_replace(BASEPATH, '', $absolute_path);
        
        // Convert backslashes to forward slashes for web URLs
        $relative_path = str_replace('\\', '/', $relative_path);
        
        return $relative_path;
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