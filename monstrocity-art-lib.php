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

/*
 * THE LOGO BOX. Measured from the 49 logos already shipped: 44 of them are
 * exactly 125 tall, and the sane widths top out around 300-400.
 *
 * monstrocity.php renders the logo as `max-width:300px; height:auto`, which
 * is a WIDTH cap with a free height, and that produces three different
 * wrongs depending on what you feed it:
 *
 *   400x125  -> drawn at 300x94, visibly smaller than its neighbours
 *   200x400  -> drawn at 200x400, towering over the layout
 *   119x128  -> drawn at 119x128, dwarfed by a 300-wide one
 *
 * The invariant the design wants is that every logo occupies the SAME BOX.
 * Fitting each one inside 300x125 and padding the rest with transparency
 * delivers that: max-width becomes a no-op, height is always 125, and the
 * artwork keeps its own proportions wherever it lands inside.
 *
 * This is exactly the padding that was being added by hand.
 */
define('MONSTROCITY_LOGO_W', 300);
define('MONSTROCITY_LOGO_H', 125);

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

/**
 * Fit a logo inside the standard box, centred, padded with transparency.
 *
 * CONTAIN, NOT COVER, AND NEVER CROPPED: a logo is type and a mark, and
 * trimming either to fill a box ruins it. Whatever does not reach the edges
 * is transparent, so the rendered result looks exactly like the artwork and
 * simply sits in a consistent space.
 *
 * Small art IS enlarged to fit. A logo left at its own tiny size renders
 * tiny, which is the problem rather than a cautious outcome -- but the caller
 * is told when it happened, because the fix for a blurry logo is a bigger
 * source file and nothing this function can do.
 *
 * Returns array($image, $noteOrEmpty).
 */
function mart_fit_logo($im, $bw = MONSTROCITY_LOGO_W, $bh = MONSTROCITY_LOGO_H) {
	$w = imagesx($im); $h = imagesy($im);
	if ($w < 1 || $h < 1) return array($im, '');

	$scale = min($bw / $w, $bh / $h);
	$nw = max(1, (int)round($w * $scale));
	$nh = max(1, (int)round($h * $scale));

	$out = imagecreatetruecolor($bw, $bh);
	imagealphablending($out, false);
	imagesavealpha($out, true);
	/* A FULLY TRANSPARENT GROUND. imagecreatetruecolor() starts opaque black,
	   so skipping this gives every logo a black slab behind it -- which on a
	   dark page looks almost right and is impossible to unsee afterwards. */
	imagefilledrectangle($out, 0, 0, $bw, $bh, imagecolorallocatealpha($out, 0, 0, 0, 127));

	imagealphablending($out, true);
	imagecopyresampled($out, $im, (int)(($bw - $nw) / 2), (int)(($bh - $nh) / 2), 0, 0, $nw, $nh, $w, $h);
	imagealphablending($out, false);

	$note = '';
	if ($scale > 1.05) {
		$note = 'That logo was only ' . $w . 'x' . $h . ' and had to be enlarged '
		      . round($scale, 1) . 'x to fill the box, so it will look soft. '
		      . 'A source at least ' . $bw . 'x' . $bh . ' would come out sharp.';
	}
	return array($out, $note);
}

/**
 * Accept an uploaded logo: fit it to the box and write it as the theme's
 * logo.png. Separate from the character pipeline because the shaping rule is
 * different -- characters keep their aspect at a fixed width, a logo is
 * letterboxed into a fixed box.
 */
function mart_accept_logo($tmp_path, $theme_slug) {
	if (monstrocity_slug_problem($theme_slug) !== '') return array('', 'Bad theme folder name.');
	$im = mart_load($tmp_path);
	if (!$im) return array('', 'That file is not an image this server can read.');
	list($fitted, $note) = mart_fit_logo($im);
	$dest = __DIR__ . '/' . monstrocity_theme_dir($theme_slug) . '/logo.png';
	$err  = mart_save_png($fitted, $dest);
	if ($err !== '') return array('', $err);
	return array($dest, $note);
}
