<?php
/*
MINIGAL NANO
- A PHP/HTML/CSS based image gallery script

This script and included files are subject to licensing from Creative Commons (http://creativecommons.org/licenses/by-sa/2.5/)
You may use, edit and redistribute this script, as long as you pay tribute to the original author by NOT removing the linkback to www.minigal.dk ("Powered by MiniGal Nano x.x.x")

MiniGal Nano is created by Thomas Rybak

Copyright 2010 by Thomas Rybak
Support: www.minigal.dk
Community: www.minigal.dk/forum

Please enjoy this free script!


USAGE EXAMPLE:
File: createthumb.php
Example: <img src="createthumb.php?filename=photo.jpg&amp;width=100&amp;height=100">
*/
//  error_reporting(E_ALL);

if(!defined("MINIGAL_INTERNAL")) {
    define("MINIGAL_INTERNAL", true);
}

require("config.php");
ini_set("memory_limit",$config['memory_limit']);

// ToDo: fix this function!
function getfirstImage($dirname) {
    global $config;
    $imageName = false;
    if($handle = opendir($dirname))
    {
        while(false !== ($file = readdir($handle)))
        {
            $inext = strtolower(preg_replace('/^.*\./', '', $file));
            if ($file[0] != '.' && (in_array($inext, $config['supported_image_types']) || in_array($inext, $config['supported_video_types']))) break;
        }
        $imageName = $file;
        closedir($handle);
    }
    return($imageName);
}

function get_orientation($filename) {
    // Rotate JPG pictures
    if (preg_match("/\.jpg$|\.jpeg$/i", $filename) && function_exists('exif_read_data') && function_exists('imagerotate')) {
        $exif = exif_read_data($filename);
        if (array_key_exists('IFD0', $exif)) {
            switch($exif['IFD0']['Orientation']) {
                case 6: // 90 rotate right
                    return -90;
                break;
                case 8:    // 90 rotate left
                    return 90;
                break;
            }
        } else {
            switch($exif['Orientation']) {
                case 6: // 90 rotate right
                    return -90;
                break;
                case 8:    // 90 rotate left
                    return 90;
                break;
            }
	}
    }

    return 0;
}

function rotate_image($source, $orientation) {
    if(!$orientation)
        return $source;

    $sx=imagesx($source);
    $sy=imagesy($source);

    $dest=imagecreatetruecolor(max($sx,$sy), max($sx,$sy));
    imagecopy($dest, $source, 0, 0, 0, 0, $sx, $sy);

    return imagecrop(imagerotate($dest, $orientation, 0), array('x' => $sx>$sy&&$orientation<0?$sx-$sy:0 , 'y' => 0, 'width' => $sy, 'height'=> $sx));
}

function imagecopymerge_alpha($dst_im, $src_im, $dst_x, $dst_y, $src_x, $src_y, $src_w, $src_h, $pct){
    // Main script by aiden dot mail at freemail dot hu
    // Transformed to imagecopymerge_alpha() by rodrigo dot polo at gmail dot com
    // Source: http://php.net/manual/en/function.imagecopymerge.php
    if(!isset($pct)){
        return false;
    }
    $pct /= 100;
    // Get image width and height
    $w = imagesx( $src_im );
    $h = imagesy( $src_im );
    // Turn alpha blending off
    imagealphablending( $src_im, false );
    // Find the most opaque pixel in the image (the one with the smallest alpha value)
    $minalpha = 127;
    for( $x = 0; $x < $w; $x++ )
    for( $y = 0; $y < $h; $y++ ){
        $alpha = ( imagecolorat( $src_im, $x, $y ) >> 24 ) & 0xFF;
        if( $alpha < $minalpha ){
            $minalpha = $alpha;
        }
    }
    //loop through image pixels and modify alpha for each
    for( $x = 0; $x < $w; $x++ ){
        for( $y = 0; $y < $h; $y++ ){
            //get current alpha value (represents the TANSPARENCY!)
            $colorxy = imagecolorat( $src_im, $x, $y );
            $alpha = ( $colorxy >> 24 ) & 0xFF;
            //calculate new alpha
            if( $minalpha !== 127 ){
                $alpha = 127 + 127 * $pct * ( $alpha - 127 ) / ( 127 - $minalpha );
            } else {
                $alpha += 127 * $pct;
            }
            //get the color index with new alpha
            $alphacolorxy = imagecolorallocatealpha( $src_im, ( $colorxy >> 16 ) & 0xFF, ( $colorxy >> 8 ) & 0xFF, $colorxy & 0xFF, $alpha );
            //set pixel with the new color + opacity
            if( !imagesetpixel( $src_im, $x, $y, $alphacolorxy ) ){
                return false;
            }
        }
    }
    // The image copy
    imagecopy($dst_im, $src_im, $dst_x, $dst_y, $src_x, $src_y, $src_w, $src_h);
}


$infile=$_GET['filename'];
$infile = "./" . $infile;
if($_GET['mode'] == 'thumb') {
  $size=$config['thumb_size'];
  $keepratio=false;
} else {
  $size=$config['small_size'];
  $keepratio=true;
}

// Display error image if file isn't found
if (preg_match("/\.\.\//i", $infile) || !(is_file($infile) || is_dir($infile))) {
    header('Content-type: image/jpeg');
    readfile('images/questionmark.jpg');
    exit;
}

// Display error image if file exists, but can't be opened
if (substr(decoct(fileperms($infile)), -1, strlen(fileperms($infile))) < 4 OR substr(decoct(fileperms($infile)), -3,1) < 4) {
    header('Content-type: image/jpeg');
    readfile('images/cannotopen.jpg');
    exit;
}

$inext = strtolower(preg_replace('/^.*\./', '', $infile));
if ( !is_dir($infile) && !in_array($inext, $config['supported_image_types']) && !in_array($inext, $config['supported_video_types']) ) {
    header('Content-type: image/jpeg');
    readfile('images/cannotopen.jpg');
    exit;
}

date_default_timezone_set("UTC");
$filetimestamp=max(filemtime($infile), filemtime("./getimage.php"), filemtime("./config.php"));
$lastmodified=gmdate("D, d M Y H:i:s \G\M\T", $filetimestamp);
$IfModifiedSince = 0;
if (isset($_ENV['HTTP_IF_MODIFIED_SINCE']))
    $IfModifiedSince = strtotime(substr($_ENV['HTTP_IF_MODIFIED_SINCE'], 5));
if (isset($_SERVER['HTTP_IF_MODIFIED_SINCE']))
    $IfModifiedSince = strtotime(substr($_SERVER['HTTP_IF_MODIFIED_SINCE'], 5));
if ($IfModifiedSince && $IfModifiedSince >= $filetimestamp) {
    header($_SERVER['SERVER_PROTOCOL'] . " 304 Not Modified");
    header("Last-Modified: " . $lastmodified);
    exit;
}
header("Cache-Control: public, must-revalidate");
header("Vary: Last-Modified");
header("Last-Modified: " . $lastmodified);

$isdir = false;
if(is_dir($infile)) {
    $isdir = true;
    $outext = 'png';
    header('Content-type: image/png');
} else if (in_array($inext, $config['supported_video_types'])) {
    $outext = 'jpeg';
    header('Content-type: image/jpeg');
} else if ($inext == 'gif') {
    $outext = 'gif';
    header('Content-type: image/gif');
} else if ($inext == 'png') {
    $outext = 'png';
    header('Content-type: image/png');
} else {
    $outext = 'jpeg';
    header('Content-type: image/jpeg');
}

// Create paths for different picture versions
$outfile = null;

if($config['caching']) {
    $md5sum = md5($infile);
    $outfile = $config['cache_path'] . "/" . $md5sum . "_" . $size . "_" . ($keepratio?"keepratio":"square") . "." . $outext;
    if(!file_exists($config['cache_path']))
        mkdir($config['cache_path']);
}

if ($config['caching'] && is_file($outfile) && filemtime($outfile)>=$filetimestamp) {
    readfile($outfile);     //Use the cache
    return;
}

$target = null;
$xoord = 0;
$yoord = 0;
$height = $size;
$width = $size;

ob_start();

if(is_dir($infile)) {
    // Use .folder.jpg (if any):
    if (file_exists($infile . "/.folder.jpg")) {
        $infile = $infile . "/.folder.jpg";
        $inext = "jpg";
    } else {
        // Set thumbnail to first image found (if any):
        $firstimage = getfirstImage($infile);
        if ($firstimage != "") {
            $infile = $infile . "/" . $firstimage;
            $inext = strtolower(preg_replace('/^.*\./', '', $infile));
        } else {
            // If no .folder.jpg or image is found, then display default icon:
            readfile("images/folder_" . mb_strtolower($config['folder_color']) . ".png");
            $infile = null;
            $outfile = null;
        }
    }
}

if ( $infile && in_array($inext, $config['supported_video_types']) ) {
    // Video thumbnail
    setlocale(LC_CTYPE, "en_US.UTF-8");
    passthru ("ffmpegthumbnailer -i " . escapeshellarg($infile) . " -o - -s " . escapeshellarg($size) . " -c jpeg -f" . ($keepratio? "" : " -a"));
    if($isdir) {
        $target = ImageCreateFromString(ob_get_contents());
        ob_end_clean();
        ob_start();
    }
} else if ($infile) {
    // Image thumbnail
    list($width_orig, $height_orig) = GetImageSize($infile);
    $orientation = get_orientation($infile);

    if($orientation) {
        $ratio_orig = $width_orig;
        $width_orig = $height_orig;
        $height_orig = $ratio_orig;
    }

    if ($keepratio) {
        // Get new dimensions
        $ratio_orig = $width_orig/$height_orig;

        if ($width_orig > $height_orig) {
            $height = $width/$ratio_orig;
        } else {
            $width = $height*$ratio_orig;
        }
    } else {
        // square thumbnail
        if ($width_orig > $height_orig) { // If the width is greater than the height it’s a horizontal picture
            $xoord = ceil(($width_orig-$height_orig)/2);
            $width_orig = $height_orig;      // Then we read a square frame that  equals the width
        } else {
            $yoord = ceil(($height_orig-$width_orig)/2);
            $height_orig = $width_orig;
        }
    }

    if(!$isdir && $keepratio && $size > $height_orig && $size > $width_orig) {
        readfile($infile);
        $outfile = null; //don't cache images that are equal to originals
    } else {
        // load source image
	switch ($inext) {
            case "gif":
                $source = ImageCreateFromGIF($infile);
                break;
            case "png":
                $source = ImageCreateFromPNG($infile);
                imagealphablending($source, false);
                imagesavealpha($source, true);
                break;
            case "jpg":
            case "jpeg":
                $source = rotate_image(ImageCreateFromJPEG($infile), $orientation);
                break;
        }

        $target = ImageCreatetruecolor($width,$height);
        imagecopyresampled($target,$source, 0,0, $xoord,$yoord, $width,$height, $width_orig,$height_orig);
        imagedestroy($source);

        if(!$isdir) {
            switch ($outext) {
                case "gif":
                    ImageGIF($target,null,90);
                    break;
                case "png":
                    ImagePNG($target,null,9);
                    break;
                case "jpeg":
                    ImageJPEG($target,null,90);
                    break;
            }
            imagedestroy($target);
        }
    }
}

if($isdir && $infile) {
    $source = $target;
    $target = ImageCreateFromPNG("images/folder_" . mb_strtolower($config['folder_color']) . ".png");

    imagealphablending($target, true);
    imagesavealpha($target, true);

    if(function_exists("imageaffine")) {
        $transform = [0.35, 0.08, 0, 0.5, 0, 0];
        $transformed = imageaffine($source, $transform);
        imagedestroy($source);

        imagecopymerge_alpha($target, $transformed, 41, 21, 0, 0, 120*($transform[0]+$transform[2])-0.5, 120*($transform[3]+$transform[1])-0.5, 50);
        imagedestroy($transformed);
    } else { // Fallback to square overlay. We could write our own imageaffine() but it's better to just upgrade php
        $transformed=imagecreatetruecolor(60, 60);
        imagecopyresized($transformed, $source, 0, 0, 0, 0, 60, 60, 120, 120);
        imagedestroy($source);

        imagecopymerge($target, $transformed, 25, 30, 0, 0, 60, 60, 50);
        imagedestroy($transformed);
    }

    ImagePNG($target,null,9);
    imagedestroy($target);
}

if($outfile)
    file_put_contents($outfile,ob_get_contents());

ob_end_flush();
?>
