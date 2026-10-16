<?php
/**
 * Monstrocity character art: resize, flip, and the battle-damaged recolour.
 *
 * Separate from monstrocity-themes-lib.php because that one is included by
 * the public game page on every load and has no business carrying image code.
 *
 * WHAT THIS REPLACES: resizing in Photoshop, renaming 28 files by hand to
 * match the original character names, flipping whichever ones face the wrong
 * way, and painting a red overlay onto 14 more. The filename is derived, the
 * recolour is a measured formula, and the flip is one click.
 */
require_once __DIR__ . '/monstrocity-themes-lib.php';

define('MONSTROCITY_ART_WIDTH', 500);   // width, aspect preserved -- see the schema notes

/** Load any supported image as a truecolour GD resource with alpha intact. */
function mart_load($path) {
	$raw = @file_get_contents($path);
	if ($raw === false) return null;
	$im = @imagecreatefromstring($raw);
	if (!$im) return null;
	/* A PALETTE PNG MAKES imagecolorat() RETURN AN INDEX, not a colour, so
	   every per-pixel read below would be reading palette slots. This has
	   bitten this codebase before. */
	imagepalettetotruecolor($im);
	imagealphablending($im, false);
	imagesavealpha($im, true);
	return $im;
}

function mart_save_png($im, $dest) {
	imagealphablending($im, false);
	imagesavealpha($im, true);
	$dir = dirname($dest);
	if (!is_dir($dir) && !@mkdir($dir, 0755, true)) return 'Could not create ' . basename($dir) . '/.';
	return @imagepng($im, $dest, 6) ? '' : 'Could not write ' . basename($dest) . '.';
}

/**
 * Resize to MONSTROCITY_ART_WIDTH wide, ASPECT PRESERVED.
 *
 * Not cropped to a square. 33 of the 45 themes happen to be 500x500 because
 * their source art was square, but eleven are not (500x470 up to 500x750) and
 * forcing a shape would quietly wreck them.
 *
 * Nothing is enlarged: art narrower than the target is left alone rather than
 * upscaled into mush.
 */
function mart_resize($im, $width = MONSTROCITY_ART_WIDTH) {
	$w = imagesx($im); $h = imagesy($im);
	if ($w <= $width) return $im;
	$nh = (int)round($h * $width / $w);
	$out = imagecreatetruecolor($width, $nh);
	imagealphablending($out, false);
	imagesavealpha($out, true);
	imagecopyresampled($out, $im, 0, 0, 0, 0, $width, $nh, $w, $h);
	return $out;
}

/** Mirror horizontally. */
function mart_flip($im) {
	$w = imagesx($im); $h = imagesy($im);
	$out = imagecreatetruecolor($w, $h);
	imagealphablending($out, false);
	imagesavealpha($out, true);
	for ($x = 0; $x < $w; $x++) imagecopy($out, $im, $w - 1 - $x, 0, $x, 0, 1, $h);
	return $out;
}

/**
 * The battle-damaged variant: hue 0, saturation 100%, lightness preserved.
 * Transparency is carried through untouched, so the silhouette is identical
 * to the base -- which is how the whole thing was reverse-engineered in the
 * first place.
 */
function mart_damage($im) {
	$w = imagesx($im); $h = imagesy($im);
	$out = imagecreatetruecolor($w, $h);
	imagealphablending($out, false);
	imagesavealpha($out, true);
	for ($y = 0; $y < $h; $y++) {
		for ($x = 0; $x < $w; $x++) {
			$p = imagecolorat($im, $x, $y);
			$a = ($p >> 24) & 0x7F;
			list($r, $g, $b) = monstrocity_damage_rgb(($p >> 16) & 255, ($p >> 8) & 255, $p & 255);
			imagesetpixel($out, $x, $y, ($a << 24) | ($r << 16) | ($g << 8) | $b);
		}
	}
	return $out;
}

/**
 * How visibly the damaged version differs from its base, over opaque pixels
 * only. Transparent regions are identical in both and would only dilute it.
 */
function mart_damage_score($base, $damaged) {
	$w = imagesx($base); $h = imagesy($base);
	if ($w !== imagesx($damaged) || $h !== imagesy($damaged)) return -1.0;
	$pairs = array();
	$step = max(1, (int)floor(min($w, $h) / 150));      // ~150x150 samples is plenty
	for ($y = 0; $y < $h; $y += $step) {
		for ($x = 0; $x < $w; $x += $step) {
			$p = imagecolorat($base, $x, $y);
			if ((($p >> 24) & 0x7F) > 100) continue;     // skip transparent
			$q = imagecolorat($damaged, $x, $y);
			$pairs[] = array(($p>>16)&255, ($p>>8)&255, $p&255, ($q>>16)&255, ($q>>8)&255, $q&255);
		}
	}
	return monstrocity_damage_score_pixels($pairs);
}

/**
 * Accept one uploaded character image into a slot.
 *
 * The browser has already resized it to roughly the target width, so what
 * arrives here is a couple of hundred KB rather than the original NFT -- see
 * the admin page for why that matters. The resize below is a backstop for
 * anything that arrives larger, not the main event.
 *
 * Writes the base and, for a base slot, regenerates the damaged variant from
 * it, because a damaged image derived from a base that has since been
 * replaced is exactly the drift found across the live library.
 */
function mart_accept($tmp_path, $theme_slug, $slot, $char_slug, $flip) {
	$folder = monstrocity_slot_folder($slot);
	if ($folder === '')            return array('', 'Unknown slot "' . $slot . '".');
	if (monstrocity_slug_problem($theme_slug) !== '')
		                           return array('', 'Bad theme folder name.');
	if (!preg_match('/^[a-z0-9-]+$/', $char_slug))
		                           return array('', 'Bad character name.');

	$im = mart_load($tmp_path);
	if (!$im)                      return array('', 'That file is not an image this server can read.');

	$im = mart_resize($im);
	if ($flip) $im = mart_flip($im);

	$dir  = __DIR__ . '/' . monstrocity_theme_dir($theme_slug);
	$dest = $dir . '/' . $folder . '/' . $char_slug . '.png';
	$err  = mart_save_png($im, $dest);
	if ($err !== '') return array('', $err);

	/* A base defines its own damaged variant. Regenerating here is what keeps
	   the two in step; the live library has pairs that drifted apart exactly
	   because a base was replaced on its own. */
	if ($slot === 'base') {
		$dmg = mart_damage($im);
		$derr = mart_save_png($dmg, $dir . '/battle-damaged/' . $char_slug . '.png');
		if ($derr !== '') return array($dest, 'Saved, but the damaged variant failed: ' . $derr);
	}
	return array($dest, '');
}

/**
 * Which slots a theme is still missing, as "Craig base" style labels.
 *
 * ACTIVATION HANGS ON THIS. A theme in the picker with an empty slot shows a
 * broken image where a character should be, and a player cannot tell that
 * from a bug. The count is taken from the DISK, not from whatever the page
 * last drew, so a tab left open since before somebody cleared a square
 * cannot publish a half-built theme.
 *
 * A pure function because the alternative is asserting on the order of lines
 * in the endpoint, which stays true however the condition is broken -- the
 * mutation that replaced the whole guard with `if (false)` passed that way.
 */
function mart_missing_slots($theme_slug) {
	$dir = __DIR__ . '/' . monstrocity_theme_dir($theme_slug);
	$out = array();
	foreach (monstrocity_characters() as $c) {
		foreach (array('base', 'leader') as $need) {
			$f = $dir . '/' . monstrocity_slot_folder($need) . '/' . $c['slug'] . '.png';
			if (!is_file($f)) $out[] = $c['name'] . ' ' . $need;
		}
	}
	return $out;
}
