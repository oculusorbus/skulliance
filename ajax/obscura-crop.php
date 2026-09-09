<?php
/*
 * Obscura's crop server.
 *
 * WHY THIS EXISTS: the crop used to be done in CSS, which meant the browser
 * was sent the WHOLE artwork and simply told to show a corner of it. Anyone
 * with devtools -- or just View Source -- could read the image url and open
 * the answer. The image IS the answer, so hiding it client-side hides
 * nothing.
 *
 * This outputs ONLY the visible region, as pixels. The full artwork never
 * leaves the server while a puzzle is live, and the url carries no reference
 * to the source file.
 *
 * Everything is derived from the player's own run row -- which NFT, where the
 * crop sits, and how far it has been widened. Nothing is taken from the query
 * string except a cache-buster, so a client cannot ask for a wider crop than
 * its attempts have earned.
 *
 * GD is already a hard dependency of this platform -- image.php uses
 * imagecreatefromjpeg, imagecopyresampled and imagejpeg -- so relying on it
 * here adds no new requirement.
 */
include_once __DIR__ . '/../db.php';
include_once __DIR__ . '/../obscura-lib.php';

// A crop is per-player and changes as attempts are spent; never let it cache.
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');

function obscura_blank($msg = '') {
	header('Content-Type: image/png');
	$im = imagecreatetruecolor(640, 640);
	imagefill($im, 0, 0, imagecolorallocate($im, 10, 25, 41));
	if ($msg !== '') {
		imagestring($im, 3, 20, 310, $msg, imagecolorallocate($im, 90, 110, 130));
	}
	imagepng($im);
	imagedestroy($im);
	exit;
}

$uid = intval($_SESSION['userData']['user_id'] ?? 0);
if ($uid <= 0) obscura_blank('log in to play');

$run = obscuraGetRun($conn, $uid);
if ($run === false || empty($run['nft_id'])) obscura_blank('no puzzle');

// Resolve the source file on disk. Same rules as the picker: local only.
$src_path = null;
$ar = $conn->query("SELECT nfts.ipfs, nfts.collection_id, collections.project_id
                    FROM nfts INNER JOIN collections ON collections.id = nfts.collection_id
                    WHERE nfts.id = " . intval($run['nft_id']) . " LIMIT 1");
if ($ar && $ar->num_rows > 0) {
	$a = $ar->fetch_assoc();
	$matches = glob(__DIR__ . '/../images/nfts/' . intval($a['project_id']) . '/'
	              . intval($a['collection_id']) . '/' . md5($a['ipfs']) . '.*');
	if (!empty($matches) && @filesize($matches[0]) >= 1024) $src_path = $matches[0];
}
if ($src_path === null) obscura_blank();   // client reroll will pick this up

$ext = strtolower(pathinfo($src_path, PATHINFO_EXTENSION));
$src = null;
if     ($ext === 'jpg' || $ext === 'jpeg') $src = @imagecreatefromjpeg($src_path);
elseif ($ext === 'png')                    $src = @imagecreatefrompng($src_path);
elseif ($ext === 'gif')                    $src = @imagecreatefromgif($src_path);
elseif ($ext === 'webp' && function_exists('imagecreatefromwebp')) $src = @imagecreatefromwebp($src_path);
if (!$src) obscura_blank();

$sw = imagesx($src);
$sh = imagesy($src);

// Zoom comes from the RUN, not the request: percentage of the artwork visible
// at the player's current tier and attempts spent.
$diff = obscuraDifficulty(intval($run['streak']));
$used = intval($run['attempts_used']);
$zoom = $diff['zooms'][min($used, count($diff['zooms']) - 1)];

// Square window sized as a share of the shorter edge, centred on the stored
// point and clamped so it never runs off the artwork.
$side = max(16, (int)round(min($sw, $sh) * ($zoom / 100)));
$cx   = (int)round($sw * (floatval($run['crop_x']) / 100));
$cy   = (int)round($sh * (floatval($run['crop_y']) / 100));
$x    = max(0, min($sw - $side, $cx - intdiv($side, 2)));
$y    = max(0, min($sh - $side, $cy - intdiv($side, 2)));

// Upscale to a fixed display size so an 8% crop is still legible.
$out = imagecreatetruecolor(640, 640);
imagecopyresampled($out, $src, 0, 0, $x, $y, 640, 640, $side, $side);

header('Content-Type: image/jpeg');
imagejpeg($out, null, 88);
imagedestroy($out);
imagedestroy($src);
