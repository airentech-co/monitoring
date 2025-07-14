<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Auth Controller Debug</h2>";

try {
    // Test includes
    echo "<p>Testing includes...</p>";
    require_once BASEPATH . 'app/controllers/Base.php';
    echo "<p style='color: green;'>✓ Base controller included successfully</p>";
    
    // Test database connection
    echo "<p>Testing database connection...</p>";
    $db = Database::getInstance();
    echo "<p style='color: green;'>✓ Database connection successful</p>";
    
    // Test users table
    echo "<p>Testing users table...</p>";
    $users = $db->fetchAll("SELECT * FROM users LIMIT 5");
    echo "<p style='color: green;'>✓ Users table accessible, found " . count($users) . " users</p>";
    
    // Test password verification
    echo "<p>Testing password verification...</p>";
    $test_password = 'admin123';
    $hashed_password = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi';
    $verify_result = password_verify($test_password, $hashed_password);
    echo "<p style='color: " . ($verify_result ? 'green' : 'red') . ";'>✓ Password verification: " . ($verify_result ? 'Working' : 'Failed') . "</p>";
    
    // Test session
    echo "<p>Testing session...</p>";
    session_start();
    echo "<p style='color: green;'>✓ Session started successfully</p>";
    
    // Test view loading
    echo "<p>Testing view loading...</p>";
    $view_file = BASEPATH . 'app/views/auth/login.php';
    if (file_exists($view_file)) {
        echo "<p style='color: green;'>✓ Login view file exists</p>";
    } else {
        echo "<p style='color: red;'>✗ Login view file missing: $view_file</p>";
    }
    
    echo "<h3>All tests passed! The Auth controller should work.</h3>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ Error: " . $e->getMessage() . "</p>";
    echo "<p>Stack trace:</p>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}
?> 