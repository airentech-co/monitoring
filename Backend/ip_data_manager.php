<?php
/**
 * IP Data Manager - Utility script for managing IP-based data structure
 * 
 * This script helps manage and view the new IP-based directory structure.
 * 
 * Usage: php ip_data_manager.php [--list] [--ip-id=1] [--ip-address=192.168.1.100] [--cleanup]
 */

include_once "config.inc.php";
include_once "./includes/json.lib.php";

// Parse command line arguments
$options = getopt("", ["list", "ip-id:", "ip-address:", "cleanup", "help"]);

if (isset($options['help'])) {
    echo "IP Data Manager - Utility script for managing IP-based data structure\n\n";
    echo "Usage:\n";
    echo "  php ip_data_manager.php --list                    # List all IP directories\n";
    echo "  php ip_data_manager.php --ip-id=1                 # Show details for IP ID 1\n";
    echo "  php ip_data_manager.php --ip-address=192.168.1.100 # Show details for IP address\n";
    echo "  php ip_data_manager.php --cleanup                 # Remove empty IP directories\n";
    echo "  php ip_data_manager.php --help                    # Show this help\n";
    exit(0);
}

// Function to get directory size
function getDirectorySize($dir) {
    $size = 0;
    if (!is_dir($dir)) return 0;
    
    $files = scandir($dir);
    foreach ($files as $file) {
        if ($file === '.' || $file === '..') continue;
        
        $path = $dir . '/' . $file;
        if (is_dir($path)) {
            $size += getDirectorySize($path);
        } else {
            $size += filesize($path);
        }
    }
    return $size;
}

// Function to format file size
function formatFileSize($bytes) {
    $units = ['B', 'KB', 'MB', 'GB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, 2) . ' ' . $units[$pow];
}

// Function to list IP directories
function listIPDirectories() {
    global $screens_dir_path, $thumbnails_dir_path, $logs_dir_path;
    
    echo "=== IP Directories Overview ===\n\n";
    
    $ip_dirs = [];
    
    // Scan screens directory
    if (is_dir($screens_dir_path)) {
        $files = scandir($screens_dir_path);
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') continue;
            if (is_dir($screens_dir_path . $file)) {
                $ip_dirs[$file] = ['screens' => true, 'thumbnails' => false, 'logs' => false];
            }
        }
    }
    
    // Scan thumbnails directory
    if (is_dir($thumbnails_dir_path)) {
        $files = scandir($thumbnails_dir_path);
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') continue;
            if (is_dir($thumbnails_dir_path . $file)) {
                if (!isset($ip_dirs[$file])) {
                    $ip_dirs[$file] = ['screens' => false, 'thumbnails' => false, 'logs' => false];
                }
                $ip_dirs[$file]['thumbnails'] = true;
            }
        }
    }
    
    // Scan logs directory
    if (is_dir($logs_dir_path)) {
        $files = scandir($logs_dir_path);
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') continue;
            if (is_dir($logs_dir_path . $file)) {
                if (!isset($ip_dirs[$file])) {
                    $ip_dirs[$file] = ['screens' => false, 'thumbnails' => false, 'logs' => false];
                }
                $ip_dirs[$file]['logs'] = true;
            }
        }
    }
    
    if (empty($ip_dirs)) {
        echo "No IP directories found.\n";
        return;
    }
    
    printf("%-20s %-10s %-12s %-8s %-10s\n", "IP Directory", "Screens", "Thumbnails", "Logs", "Total Size");
    echo str_repeat("-", 70) . "\n";
    
    foreach ($ip_dirs as $ip_dir => $types) {
        $total_size = 0;
        $screens_size = 0;
        $thumbnails_size = 0;
        $logs_size = 0;
        
        if ($types['screens']) {
            $screens_size = getDirectorySize($screens_dir_path . $ip_dir);
            $total_size += $screens_size;
        }
        if ($types['thumbnails']) {
            $thumbnails_size = getDirectorySize($thumbnails_dir_path . $ip_dir);
            $total_size += $thumbnails_size;
        }
        if ($types['logs']) {
            $logs_size = getDirectorySize($logs_dir_path . $ip_dir);
            $total_size += $logs_size;
        }
        
        printf("%-20s %-10s %-12s %-8s %-10s\n", 
            $ip_dir,
            $types['screens'] ? formatFileSize($screens_size) : '-',
            $types['thumbnails'] ? formatFileSize($thumbnails_size) : '-',
            $types['logs'] ? formatFileSize($logs_size) : '-',
            formatFileSize($total_size)
        );
    }
}

// Function to show IP details
function showIPDetails($ip_id = null, $ip_address = null) {
    $ip_paths = getIPBasedPaths($ip_id, $ip_address);
    
    echo "=== IP Details ===\n";
    if ($ip_address) echo "IP Address: $ip_address\n";
    if ($ip_id) echo "IP ID: $ip_id\n";
    echo "\n";
    
    foreach ($ip_paths as $type => $path) {
        echo "=== $type ===\n";
        echo "Path: $path\n";
        
        if (is_dir($path)) {
            $size = getDirectorySize($path);
            echo "Size: " . formatFileSize($size) . "\n";
            
            // Show recent files
            $recent_files = [];
            $files = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::LEAVES_ONLY
            );
            
            foreach ($files as $file) {
                if ($file->isFile()) {
                    $recent_files[] = [
                        'path' => $file->getPathname(),
                        'size' => $file->getSize(),
                        'mtime' => $file->getMTime()
                    ];
                }
            }
            
            // Sort by modification time (newest first)
            usort($recent_files, function($a, $b) {
                return $b['mtime'] - $a['mtime'];
            });
            
            echo "Recent files (last 10):\n";
            $count = 0;
            foreach ($recent_files as $file) {
                if ($count >= 10) break;
                $relative_path = str_replace($path, '', $file['path']);
                echo "  " . date('Y-m-d H:i:s', $file['mtime']) . " - " . formatFileSize($file['size']) . " - $relative_path\n";
                $count++;
            }
        } else {
            echo "Directory does not exist.\n";
        }
        echo "\n";
    }
}

// Function to cleanup empty directories
function cleanupEmptyDirectories() {
    global $screens_dir_path, $thumbnails_dir_path, $logs_dir_path;
    
    echo "=== Cleaning up empty IP directories ===\n\n";
    
    $directories = [$screens_dir_path, $thumbnails_dir_path, $logs_dir_path];
    $removed_count = 0;
    
    foreach ($directories as $base_dir) {
        if (!is_dir($base_dir)) continue;
        
        $files = scandir($base_dir);
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') continue;
            
            $dir_path = $base_dir . $file;
            if (is_dir($dir_path)) {
                $size = getDirectorySize($dir_path);
                if ($size === 0) {
                    if (rmdir($dir_path)) {
                        echo "Removed empty directory: $dir_path\n";
                        $removed_count++;
                    } else {
                        echo "Failed to remove directory: $dir_path\n";
                    }
                }
            }
        }
    }
    
    echo "\nRemoved $removed_count empty directories.\n";
}

// Main execution
if (isset($options['list'])) {
    listIPDirectories();
} elseif (isset($options['ip-id']) || isset($options['ip-address'])) {
    $ip_id = isset($options['ip-id']) ? $options['ip-id'] : null;
    $ip_address = isset($options['ip-address']) ? $options['ip-address'] : null;
    showIPDetails($ip_id, $ip_address);
} elseif (isset($options['cleanup'])) {
    cleanupEmptyDirectories();
} else {
    echo "Use --help to see available options.\n";
}
?> 