<?php
// Test PHP backend functionality
echo "<h1>PHP Backend Test</h1>";

// Test PHP version
echo "<h2>PHP Version</h2>";
echo "PHP Version: " . phpversion() . "<br>";

// Test required extensions
echo "<h2>Required Extensions</h2>";
$required_extensions = ['mysqli', 'gd', 'json'];
foreach ($required_extensions as $ext) {
    if (extension_loaded($ext)) {
        echo "✓ $ext extension is loaded<br>";
    } else {
        echo "✗ $ext extension is NOT loaded<br>";
    }
}

// Test configuration
echo "<h2>Configuration Test</h2>";
if (file_exists('config.inc.php')) {
    echo "✓ config.inc.php exists<br>";
    include_once "config.inc.php";
    $config = new JConfig();
    echo "Database host: " . $config->dbhost . "<br>";
    echo "Database name: " . $config->dbname . "<br>";
} else {
    echo "✗ config.inc.php does not exist<br>";
}

// Test JSON library
echo "<h2>JSON Library Test</h2>";
if (file_exists('./includes/json.lib.php')) {
    echo "✓ json.lib.php exists<br>";
    include_once "./includes/json.lib.php";
    $test_response = json_response(1, "Test message");
    echo "JSON Response: " . $test_response . "<br>";
} else {
    echo "✗ json.lib.php does not exist<br>";
}

// Test directory creation
echo "<h2>Directory Test</h2>";
$test_dirs = ['./data/screens/', './data/thumbnails/', './data/logs/'];
foreach ($test_dirs as $dir) {
    if (!file_exists($dir)) {
        mkdir($dir, 0755, true);
        echo "Created directory: $dir<br>";
    } else {
        echo "Directory exists: $dir<br>";
    }
}

// Test file upload capability
echo "<h2>File Upload Test</h2>";
if (isset($_FILES['testfile'])) {
    echo "File upload received:<br>";
    echo "File name: " . $_FILES['testfile']['name'] . "<br>";
    echo "File size: " . $_FILES['testfile']['size'] . " bytes<br>";
    echo "File type: " . $_FILES['testfile']['type'] . "<br>";
} else {
    echo "No file uploaded. You can test file upload by submitting a file.<br>";
}
?>

<form method="post" enctype="multipart/form-data">
    <input type="file" name="testfile">
    <input type="submit" value="Test File Upload">
</form> 