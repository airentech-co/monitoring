<?php
/**
 * Data Migration Script - File-Based to Database Storage
 * This script migrates existing file-based logs and screenshot metadata to the database
 * 
 * Usage: php migrate_to_database.php [--dry-run] [--ip-id=1] [--ip-address=192.168.1.100]
 */

include_once "config.inc.php";
include_once "./includes/json.lib.php";

// Parse command line arguments
$options = getopt("", ["dry-run", "ip-id:", "ip-address:", "help"]);

if (isset($options['help'])) {
    echo "Data Migration Script - File-Based to Database Storage\n\n";
    echo "Usage:\n";
    echo "  php migrate_to_database.php --dry-run                    # Test migration without making changes\n";
    echo "  php migrate_to_database.php --ip-id=1                    # Migrate data for IP ID 1\n";
    echo "  php migrate_to_database.php --ip-address=192.168.1.100   # Migrate data for specific IP\n";
    echo "  php migrate_to_database.php --help                       # Show this help\n";
    exit(0);
}

$config = new JConfig();
$mysqli = new mysqli($config->dbhost, $config->dbuser, $config->dbpassword, $config->dbname);

if ($mysqli->connect_error) {
    die("Could not connect MySQL: " . $mysqli->connect_error);
}

$mysqli->set_charset('utf8');

$dry_run = isset($options['dry-run']);
$target_ip_id = $options['ip-id'] ?? null;
$target_ip_address = $options['ip-address'] ?? null;

echo "=== Data Migration Script ===\n";
echo "Dry run: " . ($dry_run ? "YES" : "NO") . "\n";
if ($target_ip_id) echo "Target IP ID: $target_ip_id\n";
if ($target_ip_address) echo "Target IP Address: $target_ip_address\n";
echo "\n";

// Get IP records to migrate
$ip_records = getIPRecords($mysqli, $target_ip_id, $target_ip_address);

if (empty($ip_records)) {
    echo "No IP records found to migrate.\n";
    exit(0);
}

echo "Found " . count($ip_records) . " IP record(s) to migrate:\n";
foreach ($ip_records as $ip) {
    echo "- IP: {$ip['ip_address']}, ID: {$ip['id']}, Username: {$ip['username']}\n";
}
echo "\n";

$total_migrated = 0;

foreach ($ip_records as $ip) {
    echo "=== Migrating data for IP: {$ip['ip_address']} (ID: {$ip['id']}) ===\n";
    
    $migrated = migrateIPData($mysqli, $ip, $dry_run);
    $total_migrated += $migrated;
    
    echo "Migrated $migrated records for IP {$ip['ip_address']}\n\n";
}

echo "=== Migration Summary ===\n";
echo "Total records migrated: $total_migrated\n";
echo "Migration " . ($dry_run ? "simulation" : "completed") . " successfully!\n";

$mysqli->close();

// ============================================================================
// FUNCTIONS
// ============================================================================

function getIPRecords($mysqli, $target_ip_id, $target_ip_address) {
    $records = [];
    
    if ($target_ip_id) {
        $stmt = $mysqli->prepare("SELECT * FROM tbl_monitor_ips WHERE id = ?");
        $stmt->bind_param("i", $target_ip_id);
    } elseif ($target_ip_address) {
        $stmt = $mysqli->prepare("SELECT * FROM tbl_monitor_ips WHERE ip_address = ?");
        $stmt->bind_param("s", $target_ip_address);
    } else {
        $stmt = $mysqli->prepare("SELECT * FROM tbl_monitor_ips ORDER BY id");
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $records[] = $row;
    }
    
    $stmt->close();
    return $records;
}

function migrateIPData($mysqli, $ip, $dry_run) {
    $ip_id = $ip['id'];
    $ip_address = $ip['ip_address'];
    $total_migrated = 0;
    
    // Get IP-based directory paths
    $ip_paths = getIPBasedPaths($ip_id, $ip_address);
    
    echo "Scanning directories:\n";
    echo "- Logs: {$ip_paths['logs']}\n";
    echo "- Screens: {$ip_paths['screens']}\n";
    
    // Migrate browser history logs
    $browser_count = migrateBrowserHistoryLogs($mysqli, $ip_id, $ip_paths['logs'], $dry_run);
    $total_migrated += $browser_count;
    
    // Migrate key logs
    $key_count = migrateKeyLogs($mysqli, $ip_id, $ip_paths['logs'], $dry_run);
    $total_migrated += $key_count;
    
    // Migrate USB logs
    $usb_count = migrateUSBLogs($mysqli, $ip_id, $ip_paths['logs'], $dry_run);
    $total_migrated += $usb_count;
    
    // Migrate screenshot metadata
    $screenshot_count = migrateScreenshotMetadata($mysqli, $ip_id, $ip_paths['screens'], $ip_paths['thumbnails'], $dry_run);
    $total_migrated += $screenshot_count;
    
    return $total_migrated;
}

function migrateBrowserHistoryLogs($mysqli, $ip_id, $logs_path, $dry_run) {
    $count = 0;
    
    if (!is_dir($logs_path)) {
        echo "  Browser history: No logs directory found\n";
        return $count;
    }
    
    $files = findFilesRecursively($logs_path, 'browser_history.txt');
    
    foreach ($files as $file) {
        $content = file_get_contents($file);
        if (!$content) continue;
        
        $lines = explode("\n", $content);
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;
            
            // Parse line: "Date: 2025-07-14 21:30:00 | Browser: Chrome | URL: https://example.com | Title: Example | Last Visit: 1640995200"
            if (preg_match('/Date: (.+?) \| Browser: (.+?) \| URL: (.+?) \| Title: (.+?) \| Last Visit: (\d+)/', $line, $matches)) {
                $date = $matches[1];
                $browser = $matches[2];
                $url = $matches[3];
                $title = $matches[4];
                $last_visit = $matches[5];
                
                if (!$dry_run) {
                    $stmt = $mysqli->prepare("CALL AddBrowserHistoryLogs(?, ?, ?, ?, ?, ?)");
                    $visit_date = date('Y-m-d H:i:s', strtotime($date));
                    $stmt->bind_param("isssis", $ip_id, $browser, $url, $title, $last_visit, $visit_date);
                    $stmt->execute();
                    $stmt->close();
                }
                
                $count++;
            }
        }
    }
    
    echo "  Browser history: $count records\n";
    return $count;
}

function migrateKeyLogs($mysqli, $ip_id, $logs_path, $dry_run) {
    $count = 0;
    
    if (!is_dir($logs_path)) {
        echo "  Key logs: No logs directory found\n";
        return $count;
    }
    
    $files = findFilesRecursively($logs_path, 'key_logs.txt');
    
    foreach ($files as $file) {
        $content = file_get_contents($file);
        if (!$content) continue;
        
        $lines = explode("\n", $content);
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;
            
            // Parse line: "Date: 2025-07-14 21:30:00 | Application: Terminal | Key: a"
            if (preg_match('/Date: (.+?) \| Application: (.+?) \| Key: (.+)/', $line, $matches)) {
                $date = $matches[1];
                $application = $matches[2];
                $key = $matches[3];
                
                if (!$dry_run) {
                    $stmt = $mysqli->prepare("CALL AddKeyLogs(?, ?, ?, ?)");
                    $key_date = date('Y-m-d H:i:s', strtotime($date));
                    $stmt->bind_param("isss", $ip_id, $key_date, $application, $key);
                    $stmt->execute();
                    $stmt->close();
                }
                
                $count++;
            }
        }
    }
    
    echo "  Key logs: $count records\n";
    return $count;
}

function migrateUSBLogs($mysqli, $ip_id, $logs_path, $dry_run) {
    $count = 0;
    
    if (!is_dir($logs_path)) {
        echo "  USB logs: No logs directory found\n";
        return $count;
    }
    
    $files = findFilesRecursively($logs_path, 'usb_logs.txt');
    
    foreach ($files as $file) {
        $content = file_get_contents($file);
        if (!$content) continue;
        
        $lines = explode("\n", $content);
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;
            
            // Parse line: "Date: 2025-07-14 21:30:00 | Device: USB Mass Storage | Action: Connected"
            if (preg_match('/Date: (.+?) \| Device: (.+?) \| Action: (.+)/', $line, $matches)) {
                $date = $matches[1];
                $device_name = $matches[2];
                $action = $matches[3];
                
                if (!$dry_run) {
                    $stmt = $mysqli->prepare("CALL AddUSBDeviceLogs(?, ?, ?, ?, ?, ?)");
                    $device_date = date('Y-m-d H:i:s', strtotime($date));
                    $device_path = '';
                    $device_type = 'USB Device';
                    $stmt->bind_param("isssss", $ip_id, $device_date, $device_name, $device_path, $device_type, $action);
                    $stmt->execute();
                    $stmt->close();
                }
                
                $count++;
            }
        }
    }
    
    echo "  USB logs: $count records\n";
    return $count;
}

function migrateScreenshotMetadata($mysqli, $ip_id, $screens_path, $thumbnails_path, $dry_run) {
    $count = 0;
    
    if (!is_dir($screens_path)) {
        echo "  Screenshots: No screens directory found\n";
        return $count;
    }
    
    // Scan for index.txt files in date directories
    $date_dirs = glob($screens_path . '*', GLOB_ONLYDIR);
    
    foreach ($date_dirs as $date_dir) {
        $index_file = $date_dir . '/index.txt';
        if (!file_exists($index_file)) continue;
        
        $date = basename($date_dir);
        $content = file_get_contents($index_file);
        if (!$content) continue;
        
        $filenames = explode("\n", $content);
        foreach ($filenames as $filename) {
            $filename = trim($filename);
            if (empty($filename)) continue;
            
            // Parse filename: "2025-07-14_21.30.00_1234.jpg"
            if (preg_match('/(\d{4}-\d{2}-\d{2})_(\d{2})\.(\d{2})\.(\d{2})_(\d+)\.(.+)/', $filename, $matches)) {
                $date_str = $matches[1];
                $hour = $matches[2];
                $min = $matches[3];
                $sec = $matches[4];
                $random = $matches[5];
                $ext = $matches[6];
                
                $capture_datetime = "$date_str $hour:$min:$sec";
                $subdir = $hour . '.' . ($min >= 30 ? '30' : '00');
                
                $file_path = $date_dir . '/' . $subdir . '/' . $filename;
                $thumbnail_path = str_replace($screens_path, $thumbnails_path, $file_path);
                
                if (file_exists($file_path)) {
                    $file_size = filesize($file_path);
                    list($image_width, $image_height) = getimagesize($file_path);
                    
                    if (!$dry_run) {
                        $stmt = $mysqli->prepare("CALL AddScreenshotMetadata(?, ?, ?, ?, ?, ?, ?, ?, ?)");
                        $stmt->bind_param("issssiiis", $ip_id, $filename, $file_path, $thumbnail_path, $capture_datetime, $file_size, $image_width, $image_height, $ext);
                        $stmt->execute();
                        $stmt->close();
                    }
                    
                    $count++;
                }
            }
        }
    }
    
    echo "  Screenshots: $count records\n";
    return $count;
}

function findFilesRecursively($dir, $filename) {
    $files = [];
    
    if (!is_dir($dir)) return $files;
    
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