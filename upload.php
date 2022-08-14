<?php
require 'system/includes/dispatch.php';
require 'system/includes/session.php';

// Load the configuration file
config('source', 'config/config.ini');

// adapted from https://pngquant.org/php.html
function lossy_png($path_to_png_file, $max_quality = 90)
{
    if (!file_exists($path_to_png_file)) {
        throw new Exception("File does not exist: $path_to_png_file");
    }

    $min_quality = 70;

    $compressed_png_content = shell_exec("pngquant --quality=$min_quality-$max_quality - < ".escapeshellarg($path_to_png_file));

    if (!$compressed_png_content) {
        throw new Exception("Conversion to compressed PNG failed. Is pngquant 1.8+ installed on the server?");
    }

    return $compressed_png_content;
}

function crush_png($path_to_png_file)
{
    if (!file_exists($path_to_png_file)) {
        throw new Exception("File does not exist: $path_to_png_file");
    }

    $success = shell_exec("pngcrush -ow ".escapeshellarg($path_to_png_file));

    return $success == 0;
}


// Set the timezone
if (config('timezone')) {
    date_default_timezone_set(config('timezone'));
} else {
    date_default_timezone_set('Asia/Jakarta');
}

$whitelist = array('jpg', 'jpeg', 'jfif', 'pjpeg', 'pjp', 'png', 'gif');
$name      = null;
$dir       = 'content/images/';
$error     = null;
$timestamp = date('YmdHis');
$path      = null;

if (login()) {
 
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    if (isset($_FILES) && isset($_FILES['file'])) {
        $tmp_name = $_FILES['file']['tmp_name'];
        $name     = basename($_FILES['file']['name']);
        $error    = $_FILES['file']['error'];
        $path     = $dir . $timestamp . '-' . $name;
        $optimize = $_POST["optimize"] == "true";
	
        $check = getimagesize($tmp_name);
	
        if($check !== false) {
            if ($error === UPLOAD_ERR_OK) {
                $extension = pathinfo($name, PATHINFO_EXTENSION);
                if (!in_array(strtolower($extension), $whitelist)) {
                    $error = 'Invalid file type uploaded.';
                } else {
                    move_uploaded_file($tmp_name, $dir . $timestamp . '-' . $name);

                    if($optimize) {
                        $tmpPath = $path . '_tmp.png';
                        $outPath = $path . '.png';
                        $good = true;

                        try {
                            $image = new Imagick();
                            $image->readimage($path);
                            $image->setImageFormat("png");
                            $image->scaleImage(850,0);
                            $image->writeImage($tmpPath);
                        }
                        catch (Exception $e) {
                            var_dump($e);
                            $good = false;
                        }

                        if($good) {
                            try {
                                $compressed_png_content = lossy_png($tmpPath);
                                file_put_contents($outPath, $compressed_png_content);
                            }
                            catch (Exception $e) {
                                $good = false;
                            }
                        }

                        if($good) {
                            try {
                                $good = crush_png($outPath);
                            }
                            catch (Exception $e) {
                                $good = false;
                            }
                        }

                        if($good) {
                            unlink($path);
                            unlink($tmpPath);
                            $path = $outPath;
                        }
                    }
                }


            }
        } else {
            $error = "File is not an image.";
        }
    }

    header('Content-Type: application/json');
    echo json_encode(array(
        'path' => $path,
        'name'  => $name,
        'error' => $error,
    ));
	
    die();

} else {
    $login = site_url() . 'login';
    header("location: $login");
}
