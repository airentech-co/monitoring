<?php
/**
 * Data Migration Script - PHP-Based Version
 * Migrates existing file-based logs and screenshot metadata to database
 * Uses DatabaseService class for all database operations
 */

// Include configuration and database service
require_once 'config.inc.php';
require_once 'services/DatabaseService.php';

// Parse command line arguments
$options = getopt('', ['dry-run', 'ip-address:', 'help']);

if (isset($options['help'])) {
    echo "Usage: php migrate_to_database_php.php [options]\n";
    echo "Options:\n";
    echo "  --dry-run          Show what would be migrated without actually migrating\n";
    echo "  --ip-address=IP    Migrate data for specific IP address only\n";
    echo "  --help             Show this help message\n";
    exit(0);
}

$dry_run = isset($options['dry-run']);
$specific_ip = $options['ip-address'] ?? null;

echo "=== Database Migration Script (PHP-Based) ===\n";
echo "Dry run: " . ($dry_run ? 'Yes' : 'No') . "\n";
if ($specific_ip) {
    echo "Specific IP: $specific_ip\n";
}
echo "\n";

// Initialize database connection
$mysqli = new mysqli($config->dbhost, $config->dbuser, $config->dbpassword, $config->dbname);

if ($mysqli->connect_error) {
    echo "ERROR: Database connection failed: " . $mysqli->connect_error . "\n";
    exit(1);
}

// Initialize database service
$dbService = new DatabaseService($mysqli);

// Get all monitor IPs
$sql = "SELECT id, ip_address, mac_address FROM tbl_monitor_ips";
if ($specific_ip) {
    $sql .= " WHERE ip_address = ?";
    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param("s", $specific_ip);
} else {
    $stmt = $mysqli->prepare($sql);
}

$stmt->execute();
$result = $stmt->get_result();
$ip_records = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

if (empty($ip_records)) {
    echo "No monitor IPs found" . ($specific_ip ? " for IP: $specific_ip" : "") . "\n";
    exit(0);
}

$total_migrated = 0;

foreach ($ip_records as $ip) {
    echo "=== Migrating data for IP: {$ip['ip_address']} (ID: {$ip['id']}) ===\n";
    $migrated = migrateIPData($dbService, $ip, $dry_run);
    $total_migrated += $migrated;
    echo "Migrated $migrated records for IP {$ip['ip_address']}\n\n";
}

echo "=== Migration Summary ===\n";
echo "Total records migrated: $total_migrated\n";
echo "Migration " . ($dry_run ? "simulation " : "") . "completed successfully!\n";

$mysqli->close();

/**
 * Migrate data for a specific IP
 */
function migrateIPData($dbService, $ip, $dry_run) {
    $ip_id = $ip['id'];
    $ip_address = $ip['ip_address'];
    $mac_address = $ip['mac_address'];
    
    // Get IP-based paths
    $ip_paths = getIPBasedPaths($ip_id, $ip_address, $mac_address);
    
    $total_migrated = 0;
    
    // Migrate browser history logs
    $browser_count = migrateBrowserHistoryLogs($dbService, $ip_id, $ip_paths['logs'], $dry_run);
    $total_migrated += $browser_count;
    
    // Migrate key logs
    $key_count = migrateKeyLogs($dbService, $ip_id, $ip_paths['logs'], $dry_run);
    $total_migrated += $key_count;
    
    // Migrate USB logs
    $usb_count = migrateUSBLogs($dbService, $ip_id, $ip_paths['logs'], $dry_run);
    $total_migrated += $usb_count;
    
    // Migrate screenshot metadata
    $screenshot_count = migrateScreenshotMetadata($dbService, $ip_id, $ip_paths['screens'], $ip_paths['thumbnails'], $dry_run);
    $total_migrated += $screenshot_count;
    
    return $total_migrated;
}

/**
 * Migrate browser history logs
 */
function migrateBrowserHistoryLogs($dbService, $ip_id, $logs_path, $dry_run) {
    $files = findFilesRecursively($logs_path, 'browser_history.txt');
    $total_migrated = 0;
    
    foreach ($files as $file) {
        echo "  Processing browser history: $file\n";
        
        if (!file_exists($file)) {
            echo "    File not found, skipping\n";
            continue;
        }
        
        $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $migrated_count = 0;
        
        foreach ($lines as $line) {
            // Parse browser history line format
            if (preg_match('/Date: (.+?) \| Browser: (.+?) \| URL: (.+?) \| Title: (.+)/', $line, $matches)) {
                $date = $matches[1];
                $browser = $matches[2];
                $url = $matches[3];
                $title = $matches[4];
                
                if (!$dry_run) {
                    // Create browser history data structure
                    $browserHistory = [
                        'Browser' => $browser,
                        'URL' => $url,
                        'Title' => $title,
                        'LastVisit' => strtotime($date)
                    ];
                    
                    // Add to database
                    $result = $dbService->addBrowserHistoryLogs([$browserHistory], null, null);
                    if ($result) {
                        $migrated_count++;
                    }
                } else {
                    $migrated_count++;
                }
            }
        }
        
        $total_migrated += $migrated_count;
        echo "    Migrated $migrated_count browser history records\n";
    }
    
    return $total_migrated;
}

/**
 * Migrate key logs
 */
function migrateKeyLogs($dbService, $ip_id, $logs_path, $dry_run) {
    $files = findFilesRecursively($logs_path, 'key_logs.txt');
    $total_migrated = 0;
    
    foreach ($files as $file) {
        echo "  Processing key logs: $file\n";
        
        if (!file_exists($file)) {
            echo "    File not found, skipping\n";
            continue;
        }
        
        $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $migrated_count = 0;
        
        foreach ($lines as $line) {
            // Parse key log line format
            if (preg_match('/Date: (.+?) \| Application: (.+?) \| Key: (.+)/', $line, $matches)) {
                $date = $matches[1];
                $application = $matches[2];
                $key = $matches[3];
                
                if (!$dry_run) {
                    // Create key log data structure
                    $keyLog = [
                        'Date' => $date,
                        'Application' => $application,
                        'Key' => $key
                    ];
                    
                    // Add to database
                    $result = $dbService->addKeyLogs([$keyLog], null, null);
                    if ($result) {
                        $migrated_count++;
                    }
                } else {
                    $migrated_count++;
                }
            }
        }
        
        $total_migrated += $migrated_count;
        echo "    Migrated $migrated_count key log records\n";
    }
    
    return $total_migrated;
}

/**
 * Migrate USB logs
 */
function migrateUSBLogs($dbService, $ip_id, $logs_path, $dry_run) {
    $files = findFilesRecursively($logs_path, 'usb_logs.txt');
    $total_migrated = 0;
    
    foreach ($files as $file) {
        echo "  Processing USB logs: $file\n";
        
        if (!file_exists($file)) {
            echo "    File not found, skipping\n";
            continue;
        }
        
        $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $migrated_count = 0;
        
        foreach ($lines as $line) {
            // Parse USB log line format
            if (preg_match('/Date: (.+?) \| Device: (.+?) \| Action: (.+?) \| Path: (.+?) \| Type: (.+)/', $line, $matches)) {
                $date = $matches[1];
                $device_name = $matches[2];
                $action = $matches[3];
                $device_path = $matches[4];
                $device_type = $matches[5];
                
                if (!$dry_run) {
                    // Create USB log data structure
                    $usbLog = [
                        'Date' => $date,
                        'DeviceName' => $device_name,
                        'Action' => $action,
                        'DevicePath' => $device_path,
                        'DeviceType' => $device_type
                    ];
                    
                    // Add to database
                    $result = $dbService->addUSBDeviceLogs([$usbLog], null, null);
                    if ($result) {
                        $migrated_count++;
                    }
                } else {
                    $migrated_count++;
                }
            }
        }
        
        $total_migrated += $migrated_count;
        echo "    Migrated $migrated_count USB log records\n";
    }
    
    return $total_migrated;
}

/**
 * Migrate screenshot metadata
 */
function migrateScreenshotMetadata($dbService, $ip_id, $screens_path, $thumbnails_path, $dry_run) {
    $files = findFilesRecursively($screens_path, 'index.txt');
    $total_migrated = 0;
    
    foreach ($files as $index_file) {
        echo "  Processing screenshot metadata: $index_file\n";
        
        if (!file_exists($index_file)) {
            echo "    File not found, skipping\n";
            continue;
        }
        
        // Extract date from file path
        $path_parts = explode('/', $index_file);
        $date_index = array_search('screens', $path_parts);
        if ($date_index !== false && isset($path_parts[$date_index + 1])) {
            $date_dir = $path_parts[$date_index + 1];
        } else {
            echo "    Could not extract date from path, skipping\n";
            continue;
        }
        
        $lines = file($index_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $migrated_count = 0;
        
        foreach ($lines as $filename) {
            // Parse filename to extract datetime
            if (preg_match('/(\d{4}-\d{2}-\d{2})_(\d{2}\.\d{2}\.\d{2})_\d+\.jpg/', $filename, $matches)) {
                $date = $matches[1];
                $time = str_replace('.', ':', $matches[2]);
                $capture_datetime = $date . ' ' . $time;
                
                // Construct file paths
                $file_path = dirname($index_file) . '/' . $filename;
                $thumbnail_path = str_replace('/screens/', '/thumbnails/', $file_path);
                
                if (file_exists($file_path)) {
                    $file_size = filesize($file_path);
                    $image_info = getimagesize($file_path);
                    $image_width = $image_info[0] ?? 0;
                    $image_height = $image_info[1] ?? 0;
                    
                    if (!$dry_run) {
                        // Add to database
                        $result = $dbService->addScreenshotMetadata(
                            $ip_id,
                            $filename,
                            $file_path,
                            $thumbnail_path,
                            $capture_datetime,
                            $file_size,
                            $image_width,
                            $image_height,
                            'jpg'
                        );
                        if ($result) {
                            $migrated_count++;
                        }
                    } else {
                        $migrated_count++;
                    }
                }
            }
        }
        
        $total_migrated += $migrated_count;
        echo "    Migrated $migrated_count screenshot metadata records\n";
    }
    
    return $total_migrated;
}

/**
 * Find files recursively in a directory
 */
function findFilesRecursively($dir, $filename) {
    $files = [];
    
    if (!is_dir($dir)) {
        return $files;
    }
    
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS)
    );
    
    foreach ($iterator as $file) {
        if ($file->getFilename() === $filename) {
            $files[] = $file->getPathname();
        }
    }
    
    return $files;
}

/**
 * Get IP-based directory paths (from config.inc.php)
 */
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