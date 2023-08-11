<?php
// The file
$filename = 'https://ipfs.io/ipfs/'.$_GET['ipfs'];
$percent = 0.25; // percentage of resize

// Content type
header('Content-type: image/gif');

// Get new dimensions
list($width, $height) = getimagesize($filename);
$new_width = $width * $percent;
$new_height = $height * $percent;

// Resample
$image_p = imagecreatetruecolor($new_width, $new_height);
$image = imagecreatefromjpeg($filename);
imagecopyresampled($image_p, $image, 0, 0, 0, 0, $new_width, $new_height, $width, $height);

// Output
imagejpeg($image_p, null, 100);
?>