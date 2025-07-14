<?php
/**
 * JSON Library for PHP Backend
 */

function json_response($status, $message, $data = null) {
    $response = [
        'status' => $status,
        'message' => $message
    ];
    
    if ($data !== null) {
        $response['data'] = $data;
    }
    
    return json_encode($response);
}

function getClientInfo($link = null) {
    global $config;
    
    // If no database link provided, create one
    if ($link === null) {
        $link = new mysqli($config->dbhost, $config->dbuser, $config->dbpassword, $config->dbname);
        if ($link->connect_error) {
            return null;
        }
        $link->set_charset('utf8');
    }
    
    $client_ip = $_SERVER['REMOTE_ADDR'];
    
    // Get client info from database based on IP address
    $stmt = $link->prepare("SELECT id, name, username, type, last_monitor_tic FROM tbl_monitor_ips WHERE ip_address = ?");
    $stmt->bind_param("s", $client_ip);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        return [
            'id' => $row['id'],
            'name' => $row['name'],
            'username' => $row['username'],
            'type' => $row['type'],
            'last_browser_tic' => $row['last_monitor_tic'] ?: date('Y-m-d H:i:s')
        ];
    } else {
        // If no record found, create a default one
        $stmt = $link->prepare("INSERT INTO tbl_monitor_ips (ip_address, name, username, type) VALUES (?, ?, ?, ?)");
        $default_name = "Unknown_" . $client_ip;
        $default_username = "user_" . $client_ip;
        $default_type = "desktop";
        $stmt->bind_param("ssss", $client_ip, $default_name, $default_username, $default_type);
        $stmt->execute();
        
    return [
            'id' => $link->insert_id,
            'name' => $default_name,
            'username' => $default_username,
            'type' => $default_type,
        'last_browser_tic' => date('Y-m-d H:i:s')
    ];
    }
}

function getTimevalue($ip_id) {
    // Return a default time interval (in seconds)
    // This should be implemented based on your specific requirements
    return 30;
}
?> 