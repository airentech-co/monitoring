<?php
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

uploadScreen($_SERVER['HTTP_USER_AGENT'], $_FILES['fileToUpload']);

$mysqli->close();

function uploadScreen($com_name, $screenfile)
{
    $current_time = time();
    uploadCacheScreen($com_name, $current_time, $screenfile);
}

function uploadCacheScreen($com_name, $ctime, $screenfile)
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

        if (!move_uploaded_file($imagefile_tmp, $file_location)) {
            $retObj['Status'] = "Failed";
        } else {
            list($imagewidth, $imageheight, $imageType) = getimagesize($file_location);

            $show_width = "200";
            $scale = $show_width / $imagewidth;

            $thumbnailed = resizeThumbnailImage($thumb_file_location, $file_location, $imagewidth, $imageheight, 0, 0, $scale);

            $output = shell_exec("echo $img_name >> $date_dir/index.txt");
            $output = shell_exec("echo $img_name >> $thumb_date_dir/index.txt");

            $retObj['Status'] = "OK";
            $tdata = getTimevalue($ip_id);
            $retObj['Interval'] = (int) ($tdata > 1 ? ($tdata < 600 ? $tdata : 600) : $tdata);
            $retJSON = json_encode($retObj);
            echo $retJSON;
        }
    } else {
        echo json_response(0, "nofile");
    }
}

function resizeThumbnailImage($crop_image, $image, $width, $height, $start_width, $start_height, $scale)
{
    list($imagewidth, $imageheight, $imageType) = getimagesize($image);
    $imageType = image_type_to_mime_type($imageType);

    $newImageWidth = ceil($width * $scale);
    $newImageHeight = ceil($height * $scale);
    $newImage = imagecreatetruecolor($newImageWidth, $newImageHeight);
    switch ($imageType) {
        case "image/gif":
            $source = imagecreatefromgif($image);
            break;
        case "image/pjpeg":
        case "image/jpeg":
        case "image/jpg":
            $source = imagecreatefromjpeg($image);
            break;
        case "image/png":
        case "image/x-png":
            $source = imagecreatefrompng($image);
            break;
    }
    imagecopyresampled($newImage, $source, 0, 0, $start_width, $start_height, $newImageWidth, $newImageHeight, $width, $height);
    imageinterlace($newImage);
    switch ($imageType) {
        case "image/gif":
            imagegif($newImage, $crop_image);
            break;
        case "image/pjpeg":
        case "image/jpeg":
        case "image/jpg":
            imagejpeg($newImage, $crop_image, 90);
            break;
        case "image/png":
        case "image/x-png":
            imagepng($newImage, $crop_image);
            break;
    }
    return $crop_image;
}
