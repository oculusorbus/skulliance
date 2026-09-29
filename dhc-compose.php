<?php
/**
 * dhc-compose.php — a small, marquee-sized picture of a Fighter.
 *
 * IT DOES NOT COMPOSE ANYTHING. dhcf_render_fighter() in
 * dhcfighters-notify.php already draws a Fighter, and it is the only thing
 * that should: it knows the armless-torso variants, the single-arm hybrid
 * clip and the per-trait nudges that the raw layer order does not. A second
 * composer written for this page would draw Fighters that differ subtly from
 * the assembler, the Arena and the Discord embed -- which is the exact class
 * of bug that already cost the Arena its effects, twice.
 *
 * So this asks that renderer for the canonical 500px PNG and makes a small
 * derivative of it.
 *
 * WHY A DERIVATIVE AT ALL. The landing page's two scrolling rows put dozens
 * of Fighters on screen. The canonical render is a 500px PNG of dense art --
 * far more than a 150px card needs, and at those numbers it is megabytes.
 * The derivative is 200px, and JPEG whenever the Fighter has a background
 * (opaque art gains nothing from an alpha channel): ~16KB instead of ~87KB.
 *
 * RETURNS '' ON ANY FAILURE and the caller falls back to stacking layers in
 * the browser. A landing page must not depend on GD being compiled in.
 */

if (!defined('DHC_THUMB_DIR'))  define('DHC_THUMB_DIR', 'images/dhc-fighters');
if (!defined('DHC_THUMB_SIZE')) define('DHC_THUMB_SIZE', 200);
if (!defined('DHC_THUMB_Q'))    define('DHC_THUMB_Q', 82);

/**
 * @param array $traits slot => trait slug
 * @param int   $serial the Fighter's serial, for the canonical render's name
 * @return string relative URL, or '' if it could not be made
 */
function dhc_fighter_thumb(array $traits, $serial) {
	if (!function_exists('imagecreatetruecolor')) return '';
	if (!function_exists('dhcf_render_fighter')) {
		$f = __DIR__ . '/dhcfighters-notify.php';
		if (!is_readable($f)) return '';
		@include_once $f;
		if (!function_exists('dhcf_render_fighter')) return '';
	}

	try {
		/* Cheap after the first call: dhcf_render_fighter() returns the
		   existing file when the traits hash matches. */
		$url = dhcf_render_fighter($traits, $serial);
		if ($url === '') return '';
		$src_abs = __DIR__ . '/dhcrenders/' . basename(parse_url($url, PHP_URL_PATH));
		if (!is_file($src_abs)) return '';

		/* Opaque art does not need an alpha channel; a cut-out Fighter does. */
		$opaque = !empty($traits['background']);
		$ext    = $opaque ? 'jpg' : 'png';
		$name   = substr(md5(basename($src_abs) . '|' . DHC_THUMB_SIZE . '|' . $ext), 0, 24) . '.' . $ext;
		$rel    = DHC_THUMB_DIR . '/' . $name;
		$abs    = __DIR__ . '/' . $rel;
		if (is_file($abs)) return $rel;

		$dir = dirname($abs);
		if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) return '';
		if (!is_writable($dir)) return '';

		$src = @imagecreatefrompng($src_abs);
		if (!$src) return '';
		$S = DHC_THUMB_SIZE;
		$dst = imagecreatetruecolor($S, $S);
		if (!$dst) return '';
		if (!$opaque) {
			/* Keep the cut-out. Without imagesavealpha the transparent
			   surround flattens to black and every Fighter sits on a hard
			   square. */
			imagealphablending($dst, false);
			imagesavealpha($dst, true);
			imagefilledrectangle($dst, 0, 0, $S, $S, imagecolorallocatealpha($dst, 0, 0, 0, 127));
			imagealphablending($dst, true);
		}
		imagecopyresampled($dst, $src, 0, 0, 0, 0, $S, $S, imagesx($src), imagesy($src));

		/* Temp name then rename, so a half-written file is never served --
		   two visitors can land on an unbuilt Fighter at the same moment. */
		$tmp = $abs . '.' . getmypid() . '.tmp';
		$ok  = $opaque ? @imagejpeg($dst, $tmp, DHC_THUMB_Q) : @imagepng($dst, $tmp, 6);
		/* No imagedestroy(): a GdImage is an object since PHP 8.0, freed when
		   it goes out of scope, and the call is deprecated from 8.5 -- it
		   would print a notice per Fighter. Same trap xaman.php documents for
		   curl_close(). */
		unset($dst, $src);
		if (!$ok) { @unlink($tmp); return ''; }
		if (!@rename($tmp, $abs)) { @unlink($tmp); return is_file($abs) ? $rel : ''; }
		return $rel;
	} catch (Throwable $e) {
		error_log('dhc_fighter_thumb: ' . $e->getMessage());
		return '';
	}
}
