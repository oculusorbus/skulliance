<?php
/**
 * dhc-card.php — the picture X puts on a shared Fighter.
 *
 *     dhc-card.php?serial=528   ->  1200x630 JPEG
 *
 * WHY THIS EXISTS AT ALL. x.com/intent/post cannot attach a file; the only
 * image a post carries is the one the shared URL declares as its
 * twitter:image, fetched by X's own crawler. So a share without a public
 * image endpoint is a share of a generic logo, which is worth nothing.
 *
 * WHY 1200x630 AND NOT THE SQUARE RENDER. summary_large_image is 2:1 and X
 * centre-crops anything else -- a square Fighter arrives with its head and
 * its feet cut off, which is worse than no card. The art is placed whole
 * inside a 2:1 frame instead, and the empty sides are filled with a blown-up,
 * darkened copy of the same art so the card reads as one picture rather than
 * a portrait floating on a slab.
 *
 * NO TEXT ON THE IMAGE. There is no TTF in this repo and GD's built-in
 * bitmap fonts at 1200px wide look like a mistake. It is not needed either:
 * the post itself carries the name and the placements, and X renders that
 * text directly above the card.
 *
 * PUBLIC, AND THAT IS NOT A LOOSENING. It draws from dhcf_render_fighter(),
 * whose output already lives in dhcrenders/ and is already fetched by
 * Discord's servers for every trait and Fighter announcement -- those files
 * have to be publicly readable or the embeds do not render. The gate on
 * dhc-download.php is a different thing and stays exactly as it is: that one
 * hands over a named, full-size, ready-to-post file of somebody's assembly.
 * This is a 1200x630 social card for a Fighter that is already on a public
 * page, requested by the person sharing it.
 *
 * SAVED FIGHTERS ONLY. A serial is the key, and a serial only exists once a
 * Fighter has been saved; a disassembled one is excluded the same way the
 * Collection excludes it. There is no ?build= form here on purpose -- that
 * would be a way to walk the trait art out without owning any of it.
 *
 * NOTHING MAY PRINT BEFORE THE IMAGE: db.php runs with display_errors on, so
 * the include is buffered. A notice in front of the JPEG header is a corrupt
 * image, not a warning -- and a corrupt image is what X would cache.
 */

ob_start();
include __DIR__ . '/db.php';
require_once __DIR__ . '/dhcfighters-lib.php';
if (!function_exists('dhcf_render_fighter')) {
	$dhcc_notify = __DIR__ . '/dhcfighters-notify.php';
	if (is_readable($dhcc_notify)) @include_once $dhcc_notify;
}
ob_end_clean();

const DHCC_W = 1200;
const DHCC_H = 630;
/* Backdrop blur strength: the art is resampled down to this many pixels and
   blown back up, so SMALLER is blurrier. 28 keeps the colour and the broad
   shapes of the Fighter's own background and loses every readable edge. */
const DHCC_BLUR = 28;
/* How far the backdrop is pushed back. -110 of 255 is heavy on purpose --
   at -72 the sides still read as art and fought with the portrait. */
const DHCC_DIM = -110;

function dhcc_fail($code = 404) {
	http_response_code($code);
	header('Content-Type: text/plain; charset=utf-8');
	/* Short cache even on a miss: X retries, and a serial that does not
	   exist will not start existing. Not a long one -- a Fighter saved a
	   moment after someone pasted the link should not 404 for a day. */
	header('Cache-Control: public, max-age=300');
	echo 'No card.';
	exit;
}

$serial = isset($_GET['serial']) ? (int)$_GET['serial'] : 0;
if ($serial <= 0) dhcc_fail(400);
if (!function_exists('imagecreatetruecolor') || !function_exists('dhcf_render_fighter')) dhcc_fail(503);

/* $conn->query(), not prepare()+get_result(): mysqli_stmt::get_result()
   needs mysqlnd and this server's mysqli is not built against it, so the
   call is a FATAL there and php -l cannot see it. $serial is already cast
   to int above. See the same note in dhcgallery.php. */
$res = $conn->query("SELECT traits, traits_hash FROM dhc_fighters
                     WHERE serial = $serial AND disassembled_at IS NULL LIMIT 1");
if (!$res) dhcc_fail(503);
$row = $res->fetch_assoc();
if (!$row) dhcc_fail();

$traits = json_decode($row['traits'], true);
if (!is_array($traits) || !$traits) dhcc_fail();

/* Keyed on the TRAITS, not the serial: editing a Fighter keeps its number,
   so a serial-keyed cache would keep serving the old picture after an edit.
   traits_hash is already maintained for exactly this kind of identity. */
$key   = substr(md5(($row['traits_hash'] ?: md5($row['traits'])) . '|' . DHCC_W . 'x' . DHCC_H), 0, 24);
$dir   = __DIR__ . '/dhcrenders';
$cache = $dir . '/card-' . $key . '.jpg';

function dhcc_send($file) {
	/* Immutable: the filename changes when the Fighter does, so this copy
	   never needs revalidating. X caches aggressively anyway. */
	header('Content-Type: image/jpeg');
	header('Content-Length: ' . filesize($file));
	header('Cache-Control: public, max-age=604800, immutable');
	readfile($file);
	exit;
}

if (is_file($cache) && filesize($cache) > 0) dhcc_send($cache);

/* The canonical render, at the master size. One renderer for the assembler,
   the Arena, Discord and this -- a second opinion about the armless-torso
   swap or the single-arm clip is the bug class that has already cost this
   codebase twice. */
$url = dhcf_render_fighter($traits, $serial, 1000);
if ($url === '') dhcc_fail(503);
$src_abs = $dir . '/' . basename(parse_url($url, PHP_URL_PATH));
if (!is_file($src_abs)) dhcc_fail(503);

$src = @imagecreatefrompng($src_abs);
if (!$src) dhcc_fail(503);
$sw = imagesx($src); $sh = imagesy($src);

$card = imagecreatetruecolor(DHCC_W, DHCC_H);
imagefilledrectangle($card, 0, 0, DHCC_W, DHCC_H, imagecolorallocate($card, 7, 17, 29));

/* BACKDROP: the same art as a soft wash, so the sides are part of the
   picture instead of dead slab.
 *
 * DOWNSCALE-THEN-UPSCALE, NOT IMG_FILTER_GAUSSIAN_BLUR. That filter is a
 * fixed 3x3 kernel: on a 1200px canvas even several passes leave the art
 * essentially sharp, and the first version of this card shipped with crisp
 * spikes and blocks down both sides competing with the Fighter in front of
 * them -- it read as a louder copy of the portrait rather than a backdrop.
 * Resampling down to DHCC_BLUR px and back up is a real blur, costs one
 * resample, and gets stronger the smaller that number is.
 *
 * Darkened hard afterwards for the same reason: this is a ground, and a
 * ground that can be read is a distraction. */
$cur = imagecreatetruecolor(DHCC_BLUR, DHCC_BLUR);
imagecopyresampled($cur, $src, 0, 0, 0, 0, DHCC_BLUR, DHCC_BLUR, $sw, $sh);
$cw = DHCC_BLUR;
/* BACK UP IN STAGES, not in one jump. GD stops interpolating usefully at a
   40x upscale: going straight from 28px to 1200px produced hard-edged
   squares, a mosaic that reads as a rendering fault rather than a backdrop.
   Each intermediate resample interpolates, and a gaussian pass at each size
   softens what is left. */
foreach (array(120, 400) as $step) {
	$next = imagecreatetruecolor($step, $step);
	imagecopyresampled($next, $cur, 0, 0, 0, 0, $step, $step, $cw, $cw);
	if (function_exists('imagefilter')) {
		for ($i = 0; $i < 3; $i++) @imagefilter($next, IMG_FILTER_GAUSSIAN_BLUR);
	}
	$cur = $next; $cw = $step;
}
$scale = max(DHCC_W / $cw, DHCC_H / $cw);
$bw = (int)round($cw * $scale); $bh = (int)round($cw * $scale);
imagecopyresampled($card, $cur, (int)((DHCC_W - $bw) / 2), (int)((DHCC_H - $bh) / 2),
                   0, 0, $bw, $bh, $cw, $cw);
if (function_exists('imagefilter')) {
	for ($i = 0; $i < 3; $i++) @imagefilter($card, IMG_FILTER_GAUSSIAN_BLUR);
	@imagefilter($card, IMG_FILTER_BRIGHTNESS, DHCC_DIM);
	/* Pulled towards the platform's own ground so a vivid trait background
	   does not tint the whole card. */
	@imagefilter($card, IMG_FILTER_COLORIZE, 0, 6, 18, 0);
}

/* THE FIGHTER, WHOLE. Fit to the height with a little air top and bottom, so
   nothing is cropped -- the entire point of not handing X a square. */
$fit = (DHCC_H - 36) / $sh;
$fw = (int)round($sw * $fit); $fh = (int)round($sh * $fit);
imagecopyresampled($card, $src, (int)((DHCC_W - $fw) / 2), (int)((DHCC_H - $fh) / 2), 0, 0, $fw, $fh, $sw, $sh);

/* NO imagedestroy(). It has done nothing since PHP 8.0 -- GdImage is an
   object and is freed when it falls out of scope -- and it is deprecated in
   8.5. display_errors is ON here, so on a newer build the deprecation notice
   would print into the JPEG and X would cache a corrupt card. The same call
   was removed from dhcfighters-notify.php for the same reason. */

/* Atomic write, same reason dhcfighters-notify.php does it: two requests can
   render the same card at once, and a half-written file that is nonzero
   would be served as a valid cache hit for a week. */
$tmp = $dir . '/.card-' . $key . '.' . getmypid() . '-' . mt_rand(1000, 9999) . '.tmp';
if (@imagejpeg($card, $tmp, 86) && @rename($tmp, $cache)
    && is_file($cache) && filesize($cache) > 0) {
	dhcc_send($cache);
}
@unlink($tmp);

/* Could not cache it -- an unwritable dhcrenders/ must not mean no card, so
   send the image straight out and let the next request try the cache again. */
header('Content-Type: image/jpeg');
header('Cache-Control: public, max-age=3600');
imagejpeg($card, null, 86);
exit;
