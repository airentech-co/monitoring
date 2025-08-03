<?php
/**
 * Web API v2.0 - Database-Based Screenshot Metadata Storage
 * This version stores screenshot metadata in the database while keeping image files
 */

include_once "config.inc.php";
include_once "./includes/json.lib.php";
ini_set('gd.jpeg_ignore_warning', true);

$config = new JConfig();

// Use mysqli instead of mysql_* functions
$mysqli = new mysqli($config->dbhost, $config->dbuser, $config->dbpassword, $config->dbname);

if ($mysqli->connect_error) {
    die("Could not connect MySQL: " . $mysqli->connect_error);
}

$mysqli->set_charset('utf8');

// Check if this is a username update request
if (isset($_POST['action']) && $_POST['action'] === 'update_username') {
    updateUsername($mysqli);
} else {
    // Default screenshot upload
    uploadScreenWithMetadata($_SERVER['HTTP_USER_AGENT'], $_FILES['fileToUpload']);
}

$mysqli->close();

function uploadScreenWithMetadata($com_name, $screenfile)
{
    $current_time = time();
    uploadCacheScreenWithMetadata($com_name, $current_time, $screenfile);
}

function uploadCacheScreenWithMetadata($com_name, $ctime, $screenfile)
{
    global $screens_dir_path, $thumbnails_dir_path;
    
    $current_time = $ctime;
    $capture_datetime = date('Y-m-d H:i:s', $ctime);
    $dir_date = date('Y-m-d', $ctime);
    
    $ip_info = getClientInfo();
    if ($ip_info == null) {
        return;
    }

    $type = $ip_info['type'];
    $user_name = $ip_info['name'];
    $ip_id = $ip_info['id'];

    // Get IP-based directory paths
    $ip_paths = getIPBasedPaths($ip_id, $_SERVER['REMOTE_ADDR']);

    if (isset($screenfile) && $screenfile['tmp_name'] != '') {
        $imagefile_tmp = $screenfile['tmp_name'];
        $filename = $screenfile['name'];
        $file_ext = substr($filename, strrpos($filename, '.') + 1);
        $file_size = $screenfile['size'];

        $hour = date('H');
        $mins = date('i');
        $secs = date('s');

        $img_name = date('Y-m-d_') . $hour . '.' . $mins . '.' . $secs . "_" . rand(1000, 9999) . "." . $file_ext;

        $subdir = $hour . '.' . ($mins >= 30 ? '30' : '00');

        // Create directories for the current date and time with IP-based structure
        $date_dir = $ip_paths['screens'] . $dir_date . '/';
        $time_dir = $date_dir . $subdir . '/';
        $thumb_date_dir = $ip_paths['thumbnails'] . $dir_date . '/';
        $thumb_time_dir = $thumb_date_dir . $subdir . '/';
        
        if (!file_exists($time_dir)) {
            mkdir($time_dir, 0755, true);
        }
        if (!file_exists($thumb_time_dir)) {
            mkdir($thumb_time_dir, 0755, true);
        }

        $file_location = $time_dir . $img_name;
        $thumb_file_location = $thumb_time_dir . $img_name;

        $retObj = [];

        $imagedata = file_get_contents($imagefile_tmp);
        if (!$imagedata || $imagedata == "") {
            die(json_response(0, "Empty image data"));
        }

        if (move_uploaded_file($imagefile_tmp, $file_location)) {
            list($imagewidth, $imageheight, $imageType) = getimagesize($file_location);

            $show_width = "200";
            $scale = $show_width / $imagewidth;

            $thumbnailed = resizeThumbnailImage($thumb_file_location, $file_location, $imagewidth, $imageheight, 0, 0, $scale);

            // Store metadata in database
            storeScreenshotMetadata($ip_id, $img_name, $file_location, $thumb_file_location, $capture_datetime, $file_size, $imagewidth, $imageheight, $file_ext);

            $output = shell_exec("echo $img_name >> $date_dir/index.txt");
            $output = shell_exec("echo $img_name >> $thumb_date_dir/index.txt");

            $retObj['Status'] = 'OK';
            $retObj['Interval'] = 60; // Default interval
            $retObj['Message'] = 'Screenshot uploaded successfully';
            $retObj['Filename'] = $img_name;
            $retObj['FileSize'] = $file_size;
            $retObj['Dimensions'] = $imagewidth . 'x' . $imageheight;
        } else {
            $retObj['Status'] = 'Error';
            $retObj['Message'] = 'Failed to move uploaded file';
        }
    } else {
        $retObj['Status'] = 'Error';
        $retObj['Message'] = 'No file uploaded';
    }

    header('Content-Type: application/json');
    echo json_encode($retObj);
}

function storeScreenshotMetadata($ip_id, $filename, $file_path, $thumbnail_path, $capture_datetime, $file_size, $image_width, $image_height, $file_extension)
{
    global $mysqli;
    
    try {
        $stmt = $mysqli->prepare("CALL AddScreenshotMetadata(?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("issssiiis", $ip_id, $filename, $file_path, $thumbnail_path, $capture_datetime, $file_size, $image_width, $image_height, $file_extension);
        $stmt->execute();
        $stmt->close();
        
        error_log("Screenshot metadata stored for IP ID: $ip_id, Filename: $filename");
    } catch (Exception $e) {
        error_log("Error storing screenshot metadata: " . $e->getMessage());
    }
}

function resizeThumbnailImage($crop_image, $image, $width, $height, $start_width, $start_height, $scale)
{
    list($imagewidth, $imageheight, $imageType) = getimagesize($image);
    $imageType = image_type_to_mime_type($imageType);

    switch ($imageType) {
        case "image/gif":
            $image = imagecreatefromgif($image);
            break;
        case "image/pjpeg":
        case "image/jpeg":
        case "image/jpg":
            $image = imagecreatefromjpeg($image);
            break;
        case "image/png":
        case "image/x-png":
            $image = imagecreatefrompng($image);
            break;
    }

    $thumb_width = $imagewidth * $scale;
    $thumb_height = $imageheight * $scale;

    $thumb = imagecreatetruecolor($thumb_width, $thumb_height);
    imagecopyresampled($thumb, $image, 0, 0, $start_width, $start_height, $thumb_width, $thumb_height, $width, $height);

    switch ($imageType) {
        case "image/gif":
            imagegif($thumb, $crop_image);
            break;
        case "image/pjpeg":
        case "image/jpeg":
        case "image/jpg":
            imagejpeg($thumb, $crop_image, 90);
            break;
        case "image/png":
        case "image/x-png":
            imagepng($thumb, $crop_image);
            break;
    }
}

function updateUsername($mysqli) {
    $username = $_POST['username'] ?? '';
    
    if (empty($username)) {
        http_response_code(400);
        echo json_encode([
            'status' => 0,
            'message' => 'Username is required'
        ]);
        return;
    }
    
    if (strlen($username) < 2 || strlen($username) > 50) {
        http_response_code(400);
        echo json_encode([
            'status' => 0,
            'message' => 'Username must be between 2 and 50 characters'
        ]);
        return;
    }
    
    $ip_info = getClientInfo();
    if ($ip_info == null) {
        // Create new IP record
        $stmt = $mysqli->prepare("INSERT INTO tbl_monitor_ips (ip_address, username) VALUES (?, ?)");
        $stmt->bind_param("ss", $_SERVER['REMOTE_ADDR'], $username);
        $stmt->execute();
        $stmt->close();
    } else {
        // Update existing IP record
        $stmt = $mysqli->prepare("UPDATE tbl_monitor_ips SET username = ? WHERE id = ?");
        $stmt->bind_param("si", $username, $ip_info['id']);
        $stmt->execute();
        $stmt->close();
    }
    
    echo json_encode([
        'status' => 1,
        'message' => 'Username updated successfully',
        'data' => [
            'username' => $username
        ]
    ]);
}

function getClientInfo()
{
    global $mysqli;
    
    $ipaddr = $_SERVER['REMOTE_ADDR'];
    
    $stmt = $mysqli->prepare("SELECT * FROM tbl_monitor_ips WHERE ip_address = ?");
    $stmt->bind_param("s", $ipaddr);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $ip_info = $result->fetch_assoc();
        $stmt->close();
        return $ip_info;
    }
    
    $stmt->close();
    return null;
}

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
?> 