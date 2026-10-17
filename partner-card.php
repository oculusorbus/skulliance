<?php
/**
 * partner-card.php -- the 1200x630 image Discord and X put on partner.php.
 *
 * WHY THIS EXISTS. partner.php pointed og:image straight at a full-size
 * image in images/ and the Discord embed rendered NO IMAGE AT ALL. Those
 * files run to several megabytes; the embed proxy skips one that large. A
 * resized copy would fix it, but images on this server are deployed by FTP
 * and never committed, so a repo change cannot put one there -- what the
 * repo CAN do is generate one from a file that is already on the server.
 *
 * Same arrangement as dhc-card.php: compose once, cache under dhcrenders/,
 * serve immutable, and fall back to streaming the bytes if the cache cannot
 * be written. No text is drawn on it -- there is no TTF in this repo, and
 * both X and Discord render og:title at full size beside the card anyway.
 *
 * NOTHING MAY PRINT BEFORE THE IMAGE: db.php is not included here at all
 * (this needs no session and no database), but display_errors is on
 * platform-wide and a notice in front of the JPEG header is a corrupt image
 * that the crawler then caches for a week.
 */
const PTC_W = 1200;
const PTC_H = 630;

function ptc_fail($code = 404) {
	http_response_code($code);
	header('Content-Type: text/plain; charset=utf-8');
	/* Short cache on a miss: a crawler retries, and a missing source will
	   not start existing on its own -- but it might be re-uploaded. */
	header('Cache-Control: public, max-age=300');
	echo 'No card.';
	exit;
}

function ptc_send($file) {
	header('Content-Type: image/jpeg');
	header('Content-Length: ' . filesize($file));
	header('Cache-Control: public, max-age=604800, immutable');
	readfile($file);
	exit;
}

$src_abs = __DIR__ . '/images/og.jpg';
if (!is_file($src_abs)) ptc_fail();
if (!function_exists('imagecreatetruecolor')) ptc_fail(503);

/*
 * KEYED ON THE SOURCE, not on a version number somebody has to remember to
 * bump. Replace the background on the server and the key changes with it, so
 * the next request renders a new card instead of serving last month's.
 */
$key   = substr(md5($src_abs . '|' . filesize($src_abs) . '|' . filemtime($src_abs)
                . '|' . PTC_W . 'x' . PTC_H), 0, 24);
$dir   = __DIR__ . '/dhcrenders';
$cache = $dir . '/partner-card-' . $key . '.jpg';

if (is_file($cache) && filesize($cache) > 0) ptc_send($cache);

/* Loaded by what the file actually IS, not by its extension: the source has
   already changed once (a PNG background, then this JPEG), and
   imagecreatefrompng() on a JPEG returns false and would serve a 503. */
$info = @getimagesize($src_abs);
$src  = false;
if ($info) {
	if      ($info[2] === IMAGETYPE_JPEG) $src = @imagecreatefromjpeg($src_abs);
	else if ($info[2] === IMAGETYPE_PNG)  $src = @imagecreatefrompng($src_abs);
	else if ($info[2] === IMAGETYPE_GIF)  $src = @imagecreatefromgif($src_abs);
}
if (!$src) ptc_fail(503);
$sw = imagesx($src); $sh = imagesy($src);

/*
 * FIT, NOT CROP, because of what this particular source is.
 *
 * og.jpg is 1416x1113 -- ratio 1.27, nearly square -- and the card is
 * ~1.91:1. A centre crop would keep barely half its height: it would cut
 * the Skulliance wordmark out of the top-left corner and lose the whole
 * top and bottom rows of the artists' work. The point of using this image
 * is that it shows all six founders' art, so it is scaled to fit whole and
 * the leftover sides are filled with the platform's own ground.
 *
 * A crop is still right for a source already near 2:1; this chooses per
 * image rather than assuming, so replacing the file does not silently
 * start beheading it.
 */
$card = imagecreatetruecolor(PTC_W, PTC_H);
imagefilledrectangle($card, 0, 0, PTC_W, PTC_H, imagecolorallocate($card, 7, 17, 29));

$want = PTC_W / PTC_H;
$ratio = $sw / $sh;
if (abs($ratio - $want) < 0.18) {
	/* Close enough to the card's shape that cropping loses almost nothing. */
	if ($ratio > $want) { $ch = $sh; $cw = (int)round($sh * $want); }
	else                { $cw = $sw; $ch = (int)round($sw / $want); }
	imagecopyresampled($card, $src, 0, 0, (int)(($sw - $cw) / 2), (int)(($sh - $ch) / 2),
	                   PTC_W, PTC_H, $cw, $ch);
} else {
	/* Too far off: fit the whole thing and centre it. */
	$fit = min(PTC_W / $sw, PTC_H / $sh);
	$fw  = (int)round($sw * $fit);
	$fh  = (int)round($sh * $fit);
	imagecopyresampled($card, $src, (int)((PTC_W - $fw) / 2), (int)((PTC_H - $fh) / 2), 0, 0,
	                   $fw, $fh, $sw, $sh);
}

/* Atomic write, same reason dhc-card.php does it: two crawlers can arrive at
   once, and a half-written file that is nonzero would be served as a valid
   cache hit for a week. */
@mkdir($dir, 0755, true);
$tmp = $dir . '/.partner-card-' . $key . '.' . getmypid() . '-' . mt_rand(1000, 9999) . '.tmp';
if (@imagejpeg($card, $tmp, 82) && @rename($tmp, $cache)
    && is_file($cache) && filesize($cache) > 0) {
	ptc_send($cache);
}
@unlink($tmp);

/* An unwritable dhcrenders/ must not mean no card. Send it straight out and
   let the next request try the cache again. */
header('Content-Type: image/jpeg');
header('Cache-Control: public, max-age=3600');
imagejpeg($card, null, 82);
