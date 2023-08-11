<?php
// The file
$filename = 'https://ipfs.io/ipfs/'.$_GET['ipfs'];
$percent = 0.25; // percentage of resize

// Get image filetype
$filetype = exif_imagetype($filename);

// Content type
if($filetype == "IMAGETYPE_JPEG"){
	header('Content-type: image/jpeg');
}else if($filetype == "IMAGETYPE_GIF"){
	header('Content-type: image/gif');
}else if($filetype == "IMAGETYPE_PNG"){
	header('Content-type: image/png');
}

// Get new dimensions
list($width, $height) = getimagesize($filename);
$new_width = $width * $percent;
$new_height = $height * $percent;

// Resample
$image_p = imagecreatetruecolor($new_width, $new_height);
if($filetype == "IMAGETYPE_JPEG"){
	$image = imagecreatefromjpeg($filename);
}else if($filetype == "IMAGETYPE_GIF"){
	$image = imagecreatefromgif($filename);
}else if($filetype == "IMAGETYPE_PNG"){
	$image = imagecreatefrompng($filename);
}

imagecopyresampled($image_p, $image, 0, 0, 0, 0, $new_width, $new_height, $width, $height);

// Output
imagejpeg($image_p, null, 100);
?>