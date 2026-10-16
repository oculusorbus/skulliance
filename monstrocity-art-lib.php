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

/**
 * Is this image small enough to decode without killing the request?
 *
 * GD allocates roughly 4 bytes per pixel for a truecolour image, and a
 * resize holds the source and the destination at once. A 4500x4500 PNG is
 * about 81MB decoded and 100MB+ in flight -- over a typical memory_limit,
 * where it does not fail politely: PHP dies mid-request, which on an AJAX
 * endpoint means a blank reply the page reports as "not JSON".
 *
 * Read from the HEADER, before any decode. That is the whole point: once
 * imagecreatefromstring() has been called it is already too late.
 *
 * Returns '' if it will fit, or the reason it will not.
 */
function mart_too_big($raw) {
	$x = @getimagesizefromstring($raw);
	if (!$x) return 'That file is not an image this server can read.';
	$w = (int)$x[0]; $h = (int)$x[1];
	if ($w < 1 || $h < 1) return 'That image reports no size.';

	$limit = trim((string)ini_get('memory_limit'));
	if ($limit === '' || $limit === '-1') $bytes = 256 * 1024 * 1024;
	else {
		$n = (int)$limit;
		$u = strtolower(substr($limit, -1));
		$bytes = $u === 'g' ? $n * 1073741824 : ($u === 'm' ? $n * 1048576 : ($u === 'k' ? $n * 1024 : $n));
	}
	/* Source plus destination plus the rest of the request, with room spare.
	   Deliberately conservative: refusing a large image with a sentence that
	   says what to do beats a half-written response. */
	$need = $w * $h * 4 * 2.5;
	if ($need > $bytes * 0.7) {
		return $w . 'x' . $h . ' is too large for this server to decode (it needs about '
		     . round($need / 1048576) . 'MB and the limit is ' . round($bytes / 1048576) . 'MB). '
		     . 'Export it at 1500px or less -- it is going to be resized to '
		     . MONSTROCITY_ART_WIDTH . 'px wide anyway.';
	}
	return '';
}

/** Load any supported image as a truecolour GD resource with alpha intact.
 *  Returns null when it cannot or should not be decoded; mart_load_why()
 *  carries the reason for the caller that wants to explain itself. */
$GLOBALS['mart_load_why'] = '';
function mart_load_why() { return $GLOBALS['mart_load_why']; }

function mart_load($path) {
	$GLOBALS['mart_load_why'] = '';
	$raw = @file_get_contents($path);
	if ($raw === false) { $GLOBALS['mart_load_why'] = 'The upload did not arrive.'; return null; }
	$why = mart_too_big($raw);
	if ($why !== '') { $GLOBALS['mart_load_why'] = $why; return null; }
	$im = @imagecreatefromstring($raw);
	if (!$im) { $GLOBALS['mart_load_why'] = 'That file is not an image this server can read.'; return null; }
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
	if (!$im)                      return array('', mart_load_why());

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
	if (!$im) return array('', mart_load_why());
	list($fitted, $note) = mart_fit_logo($im);
	$dest = __DIR__ . '/' . monstrocity_theme_dir($theme_slug) . '/logo.png';
	$err  = mart_save_png($fitted, $dest);
	if ($err !== '') return array('', $err);
	return array($dest, $note);
}

/* ===========================================================================
 * BOSSES
 *
 * Bosses are rows in `bosses` and, unlike characters, their art lives in ONE
 * FLAT FOLDER shared by every theme: images/monstrocity/bosses/<name>.png,
 * with the red variant under bosses/battle-damaged/ using the same filename.
 * The filename comes from the boss NAME, lowercased with spaces hyphenated --
 * the same derivation the characters use.
 *
 * There has never been a way to create one. They are hand-inserted in
 * phpMyAdmin, which is why a boss can quietly point at a collection that does
 * not exist: get-bosses.php INNER JOINs both projects and collections, so a
 * boss with a bad id does not error, it simply never appears in the game.
 * ======================================================================== */

function monstrocity_boss_dir()   { return 'images/monstrocity/bosses'; }
function monstrocity_boss_slug($name) { return monstrocity_char_slug($name); }

/** The four the game implements. Anything else is a boss with no ability. */
function monstrocity_powerups() {
	return array('Minor Regen', 'Regenerate', 'Boost Attack', 'Heal');
}
function monstrocity_sizes() { return array('Small', 'Medium', 'Large'); }

/**
 * Everything wrong with a submitted boss.
 *
 * $taken is the slugs of other bosses -- two bosses whose names reduce to the
 * same filename would silently share one image, and the second one written
 * would replace the first's art with no warning anywhere.
 */
function monstrocity_validate_boss($b, $taken = array(), $theme_slugs = array()) {
	$e = array();
	$name = trim((string)(isset($b['name']) ? $b['name'] : ''));
	if ($name === '')               $e[] = 'The boss needs a name -- it is also the image filename.';
	elseif (strlen($name) > 100)    $e[] = 'That name is longer than 100 characters.';
	else {
		$slug = monstrocity_boss_slug($name);
		if (!preg_match('/^[a-z0-9]+(?:[a-z0-9-]*[a-z0-9])?$/', $slug))
			$e[] = 'That name does not reduce to a usable filename ("' . $slug . '"). '
			     . 'Letters, numbers and spaces only.';
		elseif (in_array($slug, $taken, true))
			$e[] = 'Another boss already uses the filename "' . $slug . '.png" -- '
			     . 'one would overwrite the other\'s art.';
	}

	/*
	 * A BOSS WITH A BAD PROJECT OR COLLECTION DOES NOT ERROR, IT DISAPPEARS.
	 * get-bosses.php INNER JOINs both, so an unset id drops the row from the
	 * listing with nothing logged. That is the worst failure this form can
	 * produce, so it is the one it refuses hardest.
	 */
	if (empty($b['project_id']))    $e[] = 'Pick a project. Without one the boss is dropped from the game by an inner join, silently.';
	if (empty($b['collection_id'])) $e[] = 'Pick a collection. Without one the boss is dropped from the game by an inner join, silently.';

	$theme = (string)(isset($b['theme']) ? $b['theme'] : '');
	if ($theme === '')              $e[] = 'Pick a theme. A boss is fought on one.';
	elseif ($theme_slugs && !in_array($theme, $theme_slugs, true))
		                            $e[] = 'There is no theme with the folder name "' . $theme . '".';

	$mh = (int)(isset($b['max_health']) ? $b['max_health'] : 0);
	if ($mh < 1)                    $e[] = 'Max health must be at least 1.';
	if ($mh > 100000)               $e[] = 'Max health over 100,000 is almost certainly a typo.';

	foreach (array('strength', 'speed', 'tactics') as $stat) {
		$v = (int)(isset($b[$stat]) ? $b[$stat] : 0);
		if ($v < 1 || $v > 10)      $e[] = ucfirst($stat) . ' must be between 1 and 10, like every other fighter.';
	}
	if (!in_array(isset($b['size']) ? $b['size'] : '', monstrocity_sizes(), true))
		$e[] = 'Size must be ' . implode(', ', monstrocity_sizes()) . '.';
	if (!in_array(isset($b['powerup']) ? $b['powerup'] : '', monstrocity_powerups(), true))
		$e[] = 'Powerup must be one the game implements: ' . implode(', ', monstrocity_powerups()) . '.';
	if (!in_array(isset($b['orientation']) ? $b['orientation'] : '', array('Left', 'Right'), true))
		$e[] = 'Orientation must be Left or Right.';
	if (!in_array(isset($b['extension']) ? $b['extension'] : '', monstrocity_extensions(), true))
		$e[] = 'Extension must be one of: ' . implode(', ', monstrocity_extensions()) . '.';
	return $e;
}

/**
 * Accept a boss image: resize, optionally flip, write it, and regenerate the
 * red variant from it -- the same rule as a character base, for the same
 * reason. A damaged image derived from art that has since been replaced is
 * the drift already visible across the live library.
 */
function mart_accept_boss($tmp_path, $slug, $flip = false, $ext = 'png') {
	if (!preg_match('/^[a-z0-9-]+$/', $slug)) return array('', 'Bad boss filename.');
	$im = mart_load($tmp_path);
	if (!$im) return array('', mart_load_why());
	$im = mart_resize($im);
	if ($flip) $im = mart_flip($im);

	$dir  = __DIR__ . '/' . monstrocity_boss_dir();
	$dest = $dir . '/' . $slug . '.' . $ext;
	$err  = mart_save_png($im, $dest);
	if ($err !== '') return array('', $err);
	$derr = mart_save_png(mart_damage($im), $dir . '/battle-damaged/' . $slug . '.' . $ext);
	if ($derr !== '') return array($dest, 'Saved, but the damaged variant failed: ' . $derr);
	return array($dest, '');
}

/**
 * Move a boss's art when it is renamed. Characters and themes refuse a rename
 * because their names are fixed by the game or by a folder full of art; a
 * boss name is free text that someone will reasonably want to correct, and
 * leaving the art behind under the old filename is how a boss ends up showing
 * a broken image.
 */
function mart_rename_boss($old_slug, $new_slug, $ext = 'png') {
	if ($old_slug === $new_slug || $old_slug === '' || $new_slug === '') return '';
	if (!preg_match('/^[a-z0-9-]+$/', $old_slug) || !preg_match('/^[a-z0-9-]+$/', $new_slug))
		return 'Refusing to move art between unusable filenames.';
	$dir = __DIR__ . '/' . monstrocity_boss_dir();
	foreach (array('', 'battle-damaged/') as $sub) {
		$from = $dir . '/' . $sub . $old_slug . '.' . $ext;
		$to   = $dir . '/' . $sub . $new_slug . '.' . $ext;
		if (is_file($from) && !@rename($from, $to))
			return 'Could not move ' . $sub . $old_slug . '.' . $ext . '.';
	}
	return '';
}

/**
 * Problems with a set of bosses taken TOGETHER, which is how they are met.
 *
 * ORDER IS STRENGTH. ajax/get-bosses.php sorts by project, collection, then
 * strength -- there is no sort column. So strength is not just a stat, it is
 * the running order, and two bosses sharing one have no defined order at all:
 * MySQL may return them either way and the sequence can change between page
 * loads with nothing altered.
 *
 * Escalation is the other half. A later boss that is easier than the one
 * before it is not necessarily wrong -- a glass cannon is a real design --
 * but it is nearly always a typo, so it is reported rather than refused.
 *
 * $bosses: rows with name, strength, speed, tactics, max_health.
 * Returns a list of sentences, worst first.
 */
function monstrocity_boss_set_problems($bosses) {
	$out = array();
	if (count($bosses) < 2) return $out;

	usort($bosses, function ($a, $b) {
		return array((int)$a['strength'], (string)$a['name'])
		   <=> array((int)$b['strength'], (string)$b['name']);
	});

	/* Duplicate strengths: the order between them is whatever the database
	   feels like, which is the one failure here that is not a judgement. */
	$bystr = array();
	foreach ($bosses as $b) $bystr[(int)$b['strength']][] = $b['name'];
	foreach ($bystr as $s => $names) {
		if (count($names) > 1)
			$out[] = 'Strength ' . $s . ' is shared by ' . implode(' and ', $names)
			       . ', and strength IS the running order -- their sequence is undefined.';
	}

	/* Power should climb with position. Health is the clearest signal. */
	$prev = null;
	foreach ($bosses as $b) {
		if ($prev !== null && (int)$b['max_health'] < (int)$prev['max_health']) {
			$out[] = $b['name'] . ' comes after ' . $prev['name'] . ' but has less health ('
			       . number_format((int)$b['max_health']) . ' against '
			       . number_format((int)$prev['max_health']) . ').';
		}
		$prev = $b;
	}

	/* And a flat run of health across a whole set usually means the numbers
	   were copied and never adjusted -- which is exactly what duplicating a
	   boss makes easy to do. */
	$hp = array_unique(array_map(function ($b) { return (int)$b['max_health']; }, $bosses));
	if (count($bosses) >= 3 && count($hp) === 1)
		$out[] = 'Every boss here has the same health (' . number_format(reset($hp))
		       . '), so the fight does not get harder.';
	return $out;
}

/** The fields worth carrying over when a boss is duplicated. Deliberately
 *  NOT the name (it is the filename) and NOT health (it tracks max_health). */
function monstrocity_boss_copyable() {
	return array('project_id', 'collection_id', 'theme', 'max_health', 'strength',
	             'speed', 'tactics', 'size', 'powerup', 'orientation', 'extension');
}

/**
 * Render a <select>'s options so the STORED value is always the selected one.
 *
 * THE BUG THIS EXISTS FOR. The tune screen compared the stored value to the
 * allowed list with ===, so anything differing by case or a stray space
 * matched nothing and the browser fell back to showing the FIRST option.
 * That is not a display glitch: the batch form submits every field, so one
 * press of "Save all" would have written "Small" over every boss whose size
 * was stored as "small", and the same for powerups. A dropdown that lies
 * about what is stored is worse than one that refuses to load.
 *
 * So: match tolerantly, and when a value genuinely is not in the list, keep
 * it as a selected option of its own rather than silently swapping it for
 * something else. Validation will refuse it on save and name the boss, which
 * is the right outcome -- a human decides, nothing is rewritten quietly.
 */
function monstrocity_select_options($stored, $options) {
	$stored = (string)$stored;
	$norm   = strtolower(trim($stored));
	$match  = null;
	foreach ($options as $o) if (strtolower(trim((string)$o)) === $norm) { $match = $o; break; }

	$html = '';
	if ($match === null && $norm !== '') {
		$html .= '<option value="' . htmlspecialchars($stored, ENT_QUOTES) . '" selected>'
		       . htmlspecialchars($stored) . ' (not a known value)</option>';
	}
	foreach ($options as $o) {
		$sel = ($match !== null && $o === $match) ? ' selected' : '';
		$html .= '<option value="' . htmlspecialchars((string)$o, ENT_QUOTES) . '"' . $sel . '>'
		       . htmlspecialchars((string)$o) . '</option>';
	}
	return $html;
}
