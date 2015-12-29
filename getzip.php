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
*/

if(!defined("MINIGAL_INTERNAL")) {
    define("MINIGAL_INTERNAL", true);
}

require("config.php");
ini_set("memory_limit",$config['memory_limit']);


$infile=$_GET['filename'];
$infile = "./" . $infile;

// Display error if dir isn't found
if (preg_match("/\.\.\//i", $infile) || !is_dir($infile)) {
    header($_SERVER['SERVER_PROTOCOL'] . " 404 Not Found");
    echo "The requested URL " . $_SERVER['REQUEST_URI'] . " was not found on this server.";
    exit;
}

// Display error if dir exists, but can't be opened
if (substr(decoct(fileperms($infile)), -1, strlen(fileperms($infile))) < 4 OR substr(decoct(fileperms($infile)), -3,1) < 4) {
    header($_SERVER['SERVER_PROTOCOL'] . " 403 Forbidden");
    echo "You don't have permission to access " . $_SERVER['REQUEST_URI'] . " on this server.";
    exit;
}

date_default_timezone_set("UTC");
$filetimestamp=max(filemtime($infile), filemtime(__FILE__), filemtime("./config.php"));
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


setlocale(LC_CTYPE, "en_US.UTF-8");
$zipcmd = "cd " . escapeshellarg(dirname($infile)) . " && find " . escapeshellarg(basename($infile)) . " -mindepth 1 -type d -name \.\* -prune -false -o \( -type f \( ";

$haveone=0;
reset($config['supported_image_types']);
while (list($key, $val) = each($config['supported_image_types'])) {
    if($haveone)
        $zipcmd = $zipcmd . "-o ";
    else
        $haveone=1;
    $zipcmd = $zipcmd . "-iname \"*." . $val . "\" ";
}

reset($config['supported_video_types']);
while (list($key, $val) = each($config['supported_video_types'])) {
    if($haveone)
        $zipcmd = $zipcmd . "-o ";
    else
        $haveone=1;
    $zipcmd = $zipcmd . "-iname \"*." . $val . "\" ";
}

if(!$haveone) {
    echo "No supported extensions\n";
    return;
}

$zipcmd=$zipcmd . "\) \! -name \.\* \) | zip - -@";
$zipname = basename($infile) . ".zip";

if(!$config['zipcaching']) {
    header('Content-Type: application/zip');
    if(!$_REQUEST["rewrite"])
        header('Content-Disposition: attachment; filename=photos.zip; filename*=utf-8\'\'' . rawurlencode($zipname));
    passthru ($zipcmd);
    return;
}

// Create paths for the archive
$md5sum = md5($infile);
$outfile = $config['cache_path'] . "/" . $md5sum . ".zip";
if(!file_exists($config['cache_path']))
    mkdir($config['cache_path']);

if (is_file($outfile) && filemtime($outfile)>=$filetimestamp) {
    if(function_exists("http_send_file")) {
        if(!$_REQUEST["rewrite"])
            http_send_content_disposition($zipname, true);
        http_send_content_type("application/zip");
        http_send_file($outfile);     //Use the cache
    } else {
        header('Content-Type: application/zip');
        if(!$_REQUEST["rewrite"])
            header('Content-Disposition: attachment; filename=photos.zip; filename*=utf-8\'\'' . rawurlencode($zipname));
        header('Content-Length: ' . filesize($outfile));
        readfile($outfile);     //Use the cache
    }
    return;
}

header('Content-Type: application/zip');
if(!$_REQUEST["rewrite"])
    header('Content-Disposition: attachment; filename=photos.zip; filename*=utf-8\'\'' . rawurlencode($zipname));

passthru ($zipcmd . " | tee " . $outfile . ".part && mv -f " . $outfile . ".part " . $outfile . " 2>&1 >/dev/null");

?>
