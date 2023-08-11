<?php
// The file
$filename = 'https://ipfs.io/ipfs/'.$_GET['ipfs'];
$percent = 0.25; // percentage of resize

// Get image filetype
$filetype = exif_imagetype($filename);

// Content type
if($filetype == 2){
	header('Content-type: image/jpeg');
}else if($filetype == 1){
	header('Content-type: image/gif');
}else if($filetype == 3){
	header('Content-type: image/png');
}

// Get new dimensions
list($width, $height) = getimagesize($filename);
$new_width = $width * $percent;
$new_height = $height * $percent;

// Resample
$image_p = imagecreatetruecolor($new_width, $new_height);
if($filetype == 2){
	$image = imagecreatefromjpeg($filename);
}else if($filetype == 1){
	$image = imagecreatefromgif($filename);
}else if($filetype == 3){
	$image = imagecreatefrompng($filename);
}

imagecopyresampled($image_p, $image, 0, 0, 0, 0, $new_width, $new_height, $width, $height);

// Output
imagejpeg($image_p, null, 100);
?>