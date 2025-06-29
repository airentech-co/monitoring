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
?> 