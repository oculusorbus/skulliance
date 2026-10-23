<?php
/**
 * dropship-card.php — the picture X puts on a shared Drop Ship run.
 *
 *     dropship-card.php?theme=dhc2-fighters   ->  1200x630 JPEG
 *
 * WHY THIS EXISTS. x.com/intent/post cannot attach a file; the only image a
 * post carries is the one the shared URL declares as its twitter:image,
 * fetched by X's own crawler. The theme art already exists and is already
 * public -- Discord fetches it for every death and every leaderboard post --
 * but it is the wrong SHAPE, and that is the whole job here.
 *
 * MEASURED, NOT ASSUMED: Drop Ship is 745x850, Dread City 1038x1080, Oculus
 * Lounge 891x891, Filthy Mermaid 480x356, DHC2 Fighters its own again. Three
 * portrait, one landscape, none of them 2:1. summary_large_image centre-crops
 * whatever it is given, so linking these raw hands X a band out of the middle
 * of a portrait -- on a card whose entire job is telling five games apart by
 * looking at them. Same trap dhc-card.php was built for, same answer: the art
 * is placed WHOLE inside a 2:1 frame and the empty sides are filled with a
 * blown-up, darkened copy of itself so the card reads as one picture.
 *
 * NO TEXT ON THE IMAGE. There is no TTF in this repo, and it is not needed:
 * the post carries the round, the placement and who was passed, and X renders
 * that directly above the card.
 *
 * PER THEME, NOT PER RUN, which is why a slug is the whole input. Five cached
 * files, no database row behind the URL, and nothing to enumerate -- a run id
 * here would let anyone walk the results table by changing a number.
 *
 * NO imagedestroy(). It has been a no-op since PHP 8.0 and is DEPRECATED
 * in 8.5 -- this server runs cPanel with a per-domain version somebody can
 * change from a dropdown, and a deprecation notice printed in front of the
 * JPEG header is a corrupt card. dhcfighters-notify.php avoids it for the
 * same reason; found here by running the compositor, which printed six.
 *
 * NOTHING MAY PRINT BEFORE THE IMAGE. display_errors is on platform-wide,
 * and a notice in front of the JPEG header is a corrupt image -- which is
 * then what X caches. This file therefore loads lib/dropship-public.php and
 * NOT the platform's db.php: one small query, nothing with a session, and
 * nothing that can echo. Buffered anyway, because "nothing can print" is a
 * claim about code somebody else may edit.
 */

ob_start();
require_once __DIR__ . '/lib/dropship-public.php';
ob_end_clean();

const DSC_W = 1200;
const DSC_H = 630;
/* Backdrop blur: the art is resampled down to this many pixels and blown
   back up, so SMALLER is blurrier. Matches dhc-card.php, which was tuned
   until the sides stopped competing with the subject. */
const DSC_BLUR = 28;
const DSC_DIM  = -110;

function dsc_fail($code = 404) {
	http_response_code($code);
	header('Content-Type: text/plain; charset=utf-8');
	/* Short cache even on a miss: X retries, and a slug that does not exist
	   will not start existing. Not a long one -- a theme added a moment
	   after somebody pasted the link should not 404 for a day. */
	header('Cache-Control: public, max-age=300');
	echo 'No card.';
	exit;
}

function dsc_send($file) {
	header('Content-Type: image/jpeg');
	header('Content-Length: ' . filesize($file));
	header('Cache-Control: public, max-age=604800, immutable');
	readfile($file);
	exit;
}

$slug = isset($_GET['theme']) ? strtolower(trim((string)$_GET['theme'])) : '';
/* A slug becomes a PATH two lines down, so it is validated as a slug and
   not merely escaped: lower case, digits and hyphens, nothing else. */
if ($slug === '' || !preg_match('/^[a-z0-9-]{1,48}$/', $slug)) dsc_fail(400);
if (!function_exists('imagecreatetruecolor')) dsc_fail(503);

/*
 * THE SLUG MUST BE A REAL ACTIVE THEME, checked against the database rather
 * than trusted because it matched a character class. Without this the
 * endpoint is a probe for any directory under dropship/ that happens to
 * hold images/dropship.jpg.
 *
 * 'drop-ship' is allowed through unchecked: it is the flat art at
 * dropship/images/, which predates the themes table and may have no row.
 */
if ($slug !== 'drop-ship' && !dropshipPublicTheme($slug)) dsc_fail();

$rel = ($slug === 'drop-ship') ? 'images/dropship.jpg' : $slug . '/images/dropship.jpg';
$src_abs = __DIR__ . '/dropship/' . $rel;
if (!is_file($src_abs)) dsc_fail();

/* Keyed on the FILE, not the slug: the art is replaced by FTP without any
   code change, so a slug-keyed cache would serve last month's picture for a
   week after a theme was repainted. */
$key   = substr(md5($rel . '|' . filemtime($src_abs) . '|' . DSC_W . 'x' . DSC_H), 0, 24);
$dir   = __DIR__ . '/dhcrenders';
$cache = $dir . '/dscard-' . $key . '.jpg';
if (is_file($cache) && filesize($cache) > 0) dsc_send($cache);
if (!is_dir($dir) && !@mkdir($dir, 0775, true)) dsc_fail(503);
if (!is_writable($dir)) dsc_fail(503);

/* The theme art is a jpg today and nothing promises it stays one. */
$info = @getimagesize($src_abs);
$src  = false;
if ($info) {
	if ($info[2] === IMAGETYPE_JPEG)     $src = @imagecreatefromjpeg($src_abs);
	elseif ($info[2] === IMAGETYPE_PNG)  $src = @imagecreatefrompng($src_abs);
	elseif ($info[2] === IMAGETYPE_GIF)  $src = @imagecreatefromgif($src_abs);
	elseif ($info[2] === IMAGETYPE_WEBP && function_exists('imagecreatefromwebp')) {
		$src = @imagecreatefromwebp($src_abs);
	}
}
if (!$src) dsc_fail(503);

$sw = imagesx($src); $sh = imagesy($src);
$card = imagecreatetruecolor(DSC_W, DSC_H);
imagefilledrectangle($card, 0, 0, DSC_W, DSC_H, imagecolorallocate($card, 7, 17, 29));

/*
 * BACKDROP: the same art as a soft wash, so the sides are part of the
 * picture instead of dead slab.
 *
 * NOT IMG_FILTER_GAUSSIAN_BLUR, which is a fixed 3x3 kernel and leaves art
 * this size essentially sharp however many passes it gets -- measured here
 * at six and eight passes, both still blocky.
 *
 * AND NOT ONE BIG UPSCALE EITHER, which is what dhc-card.php does and what
 * this shipped with for an hour. Jumping 28px straight to 1200 is a 43x
 * magnification, and the result is visible SQUARES down both sides. It
 * passes unnoticed on a Fighter card because a Fighter render is mostly
 * flat background at the edges; the theme art is high-contrast to the
 * corner, so the blocks read immediately. Looked at, not assumed.
 *
 * Stepping up by 1.6x at a time instead means every pixel is interpolated
 * against its neighbours a dozen times over, which dissolves the blocks
 * into a real gradient. Same cost class -- the intermediate images are
 * tiny -- and no new dependency.
 */
$bw = max(1, (int)DSC_BLUR);
$bh = max(1, (int)round($bw * $sh / max(1, $sw)));
$cur = imagecreatetruecolor($bw, $bh);
imagecopyresampled($cur, $src, 0, 0, 0, 0, $bw, $bh, $sw, $sh);

/* COVER, not contain, for the backdrop -- it has to reach every edge. */
$scale = max(DSC_W / $bw, DSC_H / $bh);
$tw = (int)ceil($bw * $scale); $th = (int)ceil($bh * $scale);
$w = $bw; $h = $bh;
/* Bounded by construction: $w grows 1.6x a step from at least 1, so this
   cannot sit still, and $tw is finite. */
while ($w < $tw) {
	$nw = min($tw, (int)ceil($w * 1.6));
	$nh = max(1, (int)round($nw * $bh / $bw));
	$next = imagecreatetruecolor($nw, $nh);
	imagecopyresampled($next, $cur, 0, 0, 0, 0, $nw, $nh, $w, $h);
	$cur = $next; $w = $nw; $h = $nh;
}
imagecopyresampled($card, $cur, (int)((DSC_W - $w) / 2), (int)((DSC_H - $h) / 2),
                   0, 0, $w, $h, $w, $h);
imagefilter($card, IMG_FILTER_BRIGHTNESS, DSC_DIM);

/* THE SUBJECT: contain, so nothing is ever cut off. That is the entire
   reason this endpoint exists rather than linking the jpg. */
$fit = min(DSC_W / $sw, DSC_H / $sh);
$fw  = max(1, (int)round($sw * $fit));
$fh  = max(1, (int)round($sh * $fit));
imagecopyresampled($card, $src, (int)((DSC_W - $fw) / 2), (int)((DSC_H - $fh) / 2),
                   0, 0, $fw, $fh, $sw, $sh);

/*
 * WRITTEN TO A TEMPORARY NAME AND RENAMED, because two crawlers asking at
 * once would otherwise both write the same file and one would serve the
 * half of it the other had not finished. rename() is atomic on the same
 * filesystem; a reader sees the old file or the whole new one.
 */
$tmp = $cache . '.' . getmypid() . '.tmp';
if (!@imagejpeg($card, $tmp, 86)) { @unlink($tmp); dsc_fail(503); }
if (!@rename($tmp, $cache)) { @unlink($tmp); dsc_fail(503); }
dsc_send($cache);
