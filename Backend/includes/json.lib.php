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
    // For now, return a default client info
    // This should be implemented based on your specific requirements
    return [
        'id' => 1,
        'name' => 'default_user',
        'type' => 'user',
        'last_browser_tic' => date('Y-m-d H:i:s')
    ];
}

function getTimevalue($ip_id) {
    // Return a default time interval (in seconds)
    // This should be implemented based on your specific requirements
    return 30;
}
?> 