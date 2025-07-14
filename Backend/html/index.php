<?php
session_start();

// Define base path
define('BASEPATH', __DIR__ . '/');

// Include configuration
require_once 'app/config/config.php';
require_once 'app/config/database.php';

// Simple routing
$request_uri = $_SERVER['REQUEST_URI'];
$base_path = dirname($_SERVER['SCRIPT_NAME']);

// Handle subdirectory installations
if ($base_path !== '/') {
    $path = str_replace($base_path, '', $request_uri);
} else {
    $path = $request_uri;
}

$path = trim($path, '/');

// Remove query string
if (strpos($path, '?') !== false) {
    $path = substr($path, 0, strpos($path, '?'));
}

// Debug routing
error_log("Request URI: " . $request_uri);
error_log("Base Path: " . $base_path);
error_log("Path: " . $path);

// Show debug info in browser for development
if (strpos($request_uri, 'debug') !== false) {
    echo "<h3>Routing Debug Info:</h3>";
    echo "<p><strong>Request URI:</strong> $request_uri</p>";
    echo "<p><strong>Base Path:</strong> $base_path</p>";
    echo "<p><strong>Processed Path:</strong> $path</p>";
}



// Default route
if (empty($path)) {
    $path = 'auth/login';
}

// Parse route
$segments = explode('/', $path);
$controller = isset($segments[0]) ? $segments[0] : 'auth';
$method = isset($segments[1]) ? $segments[1] : ($controller === 'auth' ? 'login' : 'index');
$params = array_slice($segments, 2);

// Check if user is logged in (except for auth controller)
if ($controller !== 'auth' && !isset($_SESSION['user_id'])) {
    $redirect_url = $base_path . '/auth/login';
    header('Location: ' . $redirect_url);
    exit;
}

// Load controller
$controller_file = 'app/controllers/' . ucfirst($controller) . '.php';
if (file_exists($controller_file)) {
    require_once $controller_file;
    $controller_class = ucfirst($controller);
    $controller_instance = new $controller_class();
    
    if (method_exists($controller_instance, $method)) {
        // Check if method is public
        $reflection = new ReflectionMethod($controller_instance, $method);
        if ($reflection->isPublic()) {
            call_user_func_array([$controller_instance, $method], $params);
        } else {
            http_response_code(404);
            echo "Method '$method' is not accessible via routing (it's " . ($reflection->isProtected() ? 'protected' : 'private') . ")";
        }
    } else {
        http_response_code(404);
        echo "Method not found: $method<br>";
        echo "Available methods in $controller_class:<br>";
        $methods = get_class_methods($controller_instance);
        foreach ($methods as $m) {
            if ($m !== '__construct') {
                $reflection = new ReflectionMethod($controller_instance, $m);
                $visibility = $reflection->isPublic() ? 'public' : ($reflection->isProtected() ? 'protected' : 'private');
                echo "- $m ($visibility)<br>";
            }
        }
    }
} else {
    http_response_code(404);
    echo "Controller not found: $controller";
}
?> 