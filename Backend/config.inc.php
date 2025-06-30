<?php
class JConfig {
    public $dbhost = 'localhost';
    public $dbuser = 'root';
    public $dbpassword = 'acc_monitor';
    public $dbname = 'monitoring_db';
    
    // Directory paths for storing data
    public $screens_dir_path = './data/screens/';
    public $thumbnails_dir_path = './data/thumbnails/';
    public $logs_dir_path = './data/logs/';
}

// Global variables for backward compatibility
$screens_dir_path = './data/screens/';
$thumbnails_dir_path = './data/thumbnails/';
$logs_dir_path = './data/logs/';

// Create directories if they don't exist
$directories = [$screens_dir_path, $thumbnails_dir_path, $logs_dir_path];
foreach ($directories as $dir) {
    if (!file_exists($dir)) {
        mkdir($dir, 0755, true);
    }
}

// Function to get IP-based directory paths
function getIPBasedPaths($ip_id, $ip_address = null, $mac_address = null) {
    global $screens_dir_path, $thumbnails_dir_path, $logs_dir_path;
    
    // Use IP address as primary identifier, fallback to IP ID, then MAC address
    $identifier = null;
    if ($ip_address) {
        $identifier = "ip_{$ip_address}";
    } elseif ($ip_id) {
        $identifier = "ip_{$ip_id}";
    } elseif ($mac_address && strlen($mac_address) > 11) {
        $identifier = "mac_" . str_replace([':', '-', '.'], '_', $mac_address);
    } else {
        $identifier = "unknown_client";
    }
    
    return [
        'screens' => $screens_dir_path . $identifier . '/',
        'thumbnails' => $thumbnails_dir_path . $identifier . '/',
        'logs' => $logs_dir_path . $identifier . '/'
    ];
}
?> 