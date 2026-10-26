<?php
/**
 * dhc-boss-art.php?boss=x-k4t&wave=3 -- one boss, in that wave's colourway.
 *
 * WHY THIS EXISTS. The crew gets back up each week in a different colour and
 * the pages do it with `filter: hue-rotate()`. Discord does not take a
 * filter, it takes a URL -- so a kill announced in wave 3 was posting the
 * flat wave 1 art, a picture of a crew nobody fought. The X share card
 * solved the same problem by drawing the rotation itself; this is that
 * rotation behind a URL, which is the only shape an embed can consume.
 *
 * PUBLIC, AND THAT EXPOSES NOTHING. It serves one of three boss pictures
 * that are already public files, with a hue rotation on top. Discord's
 * servers fetch embed images unauthenticated, so it has to be -- same as
 * dhc-card.php and dhc-arena-card.php, for the same reason.
 *
 * NOTHING MAY PRINT BEFORE THE IMAGE: display_errors is on platform-wide
 * and a notice in front of the PNG header is a corrupt image that Discord
 * caches.
 */
ob_start();
include_once __DIR__ . '/db.php';
require_once __DIR__ . '/dhcbosses-lib.php';
require_once __DIR__ . '/lib/hue-rotate.php';
ob_end_clean();

function dba_fail($code = 404) {
	http_response_code($code);
	header('Content-Type: text/plain; charset=utf-8');
	header('Cache-Control: public, max-age=300');
	echo 'No art.';
	exit;
}
function dba_send($file) {
	header('Content-Type: image/png');
	header('Content-Length: ' . filesize($file));
	/* Immutable: the key carries the boss and the wave, so a given URL can
	   never mean a different picture. Discord caches hard and that is fine. */
	header('Cache-Control: public, max-age=604800, immutable');
	readfile($file);
	exit;
}

/*
 * THE KEY IS VALIDATED AGAINST THE ROSTER, not pattern-matched. It becomes a
 * path, and dhcb_bosses() is the only list of keys that exist -- anything
 * invented is refused before it can be concatenated with anything.
 */
$key  = isset($_GET['boss']) ? (string)$_GET['boss'] : '';
$wave = isset($_GET['wave']) ? (int)$_GET['wave'] : 1;
if (!function_exists('dhcb_bosses')) dba_fail(503);
$found = null;
foreach (dhcb_bosses() as $bz) if ($bz['key'] === $key) { $found = $bz; break; }
if (!$found) dba_fail();
if ($wave < 1) $wave = 1;
if ($wave > 99) $wave = 99;

$src = __DIR__ . '/' . dhcb_art($found['key']);
if (!is_file($src)) dba_fail();

$deg = dhcb_wave_hue($wave);

/*
 * WAVE 1 IS THE FILE ITSELF. It is the ungraded baseline, so there is
 * nothing to compute, nothing to cache and nothing to get wrong -- the
 * original bytes go straight out.
 */
if ($deg === 0) dba_send($src);

if (!function_exists('imagecreatefrompng')) dba_send($src);

/* Keyed on the boss, the rotation and the source's own mtime, so replacing
   the art on the server retires every rotated copy of it rather than
   serving last month's picture in this week's colour. */
$dir   = __DIR__ . '/dhcrenders';
$cache = $dir . '/boss-' . preg_replace('/[^a-z0-9-]/', '', $key)
       . '-' . $deg . '-' . substr(md5((string)@filemtime($src)), 0, 8) . '.png';
if (is_file($cache) && @filesize($cache) > 0) dba_send($cache);

$im = @imagecreatefrompng($src);
if (!$im) dba_fail(503);
/* The boss art is a PNG with a transparent surround and the embed has no
   background of its own to hide a black box behind it. */
imagealphablending($im, false);
imagesavealpha($im, true);

skl_hue_rotate($im, $deg);

@mkdir($dir, 0755, true);
/* Atomic write, same reason every other renderer here does it: two Discord
   fetches can arrive at once and a half-written file that is nonzero would
   be served as a valid cache hit for a week. */
$tmp = $dir . '/.boss-' . getmypid() . '-' . mt_rand(1000, 9999) . '.tmp';
if (@imagepng($im, $tmp) && @rename($tmp, $cache) && is_file($cache) && @filesize($cache) > 0) {
	dba_send($cache);
}
@unlink($tmp);

header('Content-Type: image/png');
header('Cache-Control: public, max-age=3600');
imagepng($im);
