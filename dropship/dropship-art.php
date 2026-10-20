<?php
/**
 * WHAT A DROP SHIP THEME IS MADE OF.
 *
 * A reskin is about seventy image files in a fixed tree, and the only record
 * of which seventy was the run itself -- scattered across live(), kill(),
 * extralife(), the weapon chain and the equipment branches of dropship.php.
 * Nobody could answer "is Dread City finished" without reading the game, so
 * the honest answer was always "probably not" and new themes stalled.
 *
 * This is that list, written down once, so it can be checked.
 *
 * THE COUNTS ARE NOT INVENTED. dropship.php seeds $beach_random = 7,
 * $hill_random = 6 and $bunker_random = 6 as "the last GIF number for this
 * scene", and verifyRandom() picks within that range -- so those initialisers
 * ARE the counts, and dropship-art-harness.php reads them back out of the run
 * and fails if this file drifts from them. Confirmed live as well:
 * images/live/beach/7.gif answers 200 and 8.gif answers 404.
 *
 * ART IS NOT IN THE REPOSITORY. It deploys by FTP, so an audit run from a
 * checkout finds nothing and must say so rather than reporting seventy
 * missing files. Run on the server it reads the disk; anywhere else it asks
 * the live host over HTTP.
 */

/** Scene variants per stage, matching dropship.php's own initialisers. */
function dropshipArtStages() {
	return array(
		'beach'  => array('plain' => 7, 'exo' => 2, 'secured' => 'beach-secured'),
		'hill'   => array('plain' => 6, 'exo' => 2, 'secured' => 'hill-secured'),
		/* The bunker has no "secured" frame: it is the last stage, and
		   clearing it is the end of the run rather than a transition. */
		'bunker' => array('plain' => 6, 'exo' => 2, 'secured' => null),
	);
}

/** Weapon animation frames, by file stem. */
function dropshipArtWeapons() {
	return array('demo' => 2, 'flamethrower' => 4, 'grenade' => 2, 'machine-gun' => 3,
	             'melee' => 1, 'rocket-launcher' => 5, 'smoke-bomb' => 2,
	             'sniper-rifle' => 1, 'tactical-katana' => 1);
}

/** One-off equipment frames. */
function dropshipArtEquipment() {
	return array('airstrike1', 'airstrike2', 'drop-box', 'jet-pack1',
	             'night-vision-goggles', 'radar', 'radio', 'reinforcements');
}

/**
 * Every image a theme needs, as paths relative to the theme root.
 *
 * $ext is the media extension, which is a property of the theme:
 * 'gif' for most, 'png' for Oculus Lounge. It used to be a per-player 3D
 * toggle; that is retired and the value lives on dropship_themes.extension.
 */
function dropshipArtManifest($ext = 'gif') {
	$m = array();

	foreach (dropshipArtStages() as $stage => $cfg) {
		for ($i = 1; $i <= $cfg['plain']; $i++) $m[] = "images/live/$stage/$i.$ext";
		for ($i = 1; $i <= $cfg['exo']; $i++)   $m[] = "images/live/$stage/exo/$i.$ext";
		if ($cfg['secured']) $m[] = "images/live/$stage/{$cfg['secured']}.$ext";
	}

	/* Deaths: three generic, plus one per stage-specific ending. */
	for ($i = 1; $i <= 3; $i++) $m[] = "images/die/$i.$ext";
	foreach (array('beach', 'landmine', 'bunker') as $d) $m[] = "images/die/$d.$ext";

	/* The extra life. */
	for ($i = 1; $i <= 2; $i++) $m[] = "images/1up/$i.$ext";

	/* The drop sequence, rounds 0 to 2, which are never rolled for. */
	$m[] = "images/reset/reset.$ext";
	$m[] = 'images/reset/intro.jpg';
	$m[] = "images/drop/drop.$ext";
	$m[] = "images/drop/team.$ext";

	foreach (dropshipArtWeapons() as $stem => $n) {
		if ($n === 1) { $m[] = "images/weapons/$stem.$ext"; continue; }
		for ($i = 1; $i <= $n; $i++) $m[] = "images/weapons/$stem$i.$ext";
	}
	foreach (dropshipArtEquipment() as $e) $m[] = "images/equipment/$e.$ext";

	/* Chrome the pages and the Discord embeds reach for. */
	$m[] = 'images/dropship.jpg';
	$m[] = 'images/nfts/placeholder.jpg';

	return $m;
}

/**
 * The themes, and where each one's art lives.
 *
 * HYBRID LAYOUT, and it is not a mistake: Drop Ship's own art sits FLAT under
 * dropship/, while every reskin nests under its slug. dropship.php builds
 * $prefix the same way; see its comment, which records two earlier passes
 * that each got half of this wrong.
 */
function dropshipArtThemes() {
	return array(
		1 => array('slug' => 'drop-ship',      'name' => 'Drop Ship',      'ext' => 'gif'),
		2 => array('slug' => 'dread-city',     'name' => 'Dread City',     'ext' => 'gif'),
		3 => array('slug' => 'filthy-mermaid', 'name' => 'Filthy Mermaid', 'ext' => 'gif'),
		/* The Lounge's art is png. */
		4 => array('slug' => 'oculus-lounge',  'name' => 'Oculus Lounge',  'ext' => 'png'),
	);
}

/** Theme root, relative to the dropship directory. '' for Drop Ship itself. */
function dropshipArtRoot($slug) {
	return $slug === 'drop-ship' ? '' : $slug . '/';
}

/**
 * Audit one theme. Returns array(present, missing, checked, mode).
 *
 * Prefers the local disk, because on the server that is instant and exact.
 * Falls back to HTTP, because the art is not in the repository and an audit
 * that only works in one place would not get run.
 */
function dropshipArtAudit($slug, $ext, $base_dir = null, $base_url = null) {
	$manifest = dropshipArtManifest($ext);
	$root     = dropshipArtRoot($slug);
	$base_dir = $base_dir !== null ? $base_dir : __DIR__;

	$local = is_dir(rtrim($base_dir, '/') . '/' . $root . 'images');
	$missing = array();

	if ($local) {
		foreach ($manifest as $rel) {
			if (!is_file(rtrim($base_dir, '/') . '/' . $root . $rel)) $missing[] = $rel;
		}
		return array('mode' => 'disk', 'checked' => count($manifest),
		             'missing' => $missing, 'present' => count($manifest) - count($missing));
	}

	if ($base_url === null) {
		return array('mode' => 'absent', 'checked' => 0, 'missing' => array(), 'present' => 0);
	}

	/* Concurrent HEADs. Seventy files four times over is a slow walk one at
	   a time, and a slow audit is an audit nobody runs twice. */
	$mh = curl_multi_init();
	$handles = array();
	foreach ($manifest as $i => $rel) {
		$ch = curl_init(rtrim($base_url, '/') . '/' . $root . $rel);
		curl_setopt_array($ch, array(CURLOPT_NOBODY => true, CURLOPT_RETURNTRANSFER => true,
		                             CURLOPT_TIMEOUT => 15, CURLOPT_FOLLOWLOCATION => true));
		curl_multi_add_handle($mh, $ch);
		$handles[$i] = $ch;
	}
	do { $st = curl_multi_exec($mh, $active); if ($active) curl_multi_select($mh); }
	while ($active && $st == CURLM_OK);
	foreach ($handles as $i => $ch) {
		if ((int)curl_getinfo($ch, CURLINFO_HTTP_CODE) !== 200) $missing[] = $manifest[$i];
		curl_multi_remove_handle($mh, $ch);
		curl_close($ch);
	}
	curl_multi_close($mh);

	return array('mode' => 'http', 'checked' => count($manifest),
	             'missing' => $missing, 'present' => count($manifest) - count($missing));
}

/* ------------------------------------------------------------------ *
 * THE IMAGE MANAGER
 * ------------------------------------------------------------------ */

/**
 * How big a round image ever needs to be.
 *
 * DERIVED FROM THE STYLESHEET AND THE KEN BURNS KEYFRAMES, not picked:
 *
 *   .container          max-width 1400
 *   .main               flex 67%        -> 938
 *   .main               padding 20px    -> 898 of content
 *   #results            min-height AND max-height 898, so the box is 898 square
 *   #results-image      background-size: contain, inside that box
 *   @keyframes move     scale(1.0) -> scale(1.2) over 40s
 *
 * So the widest any art is ever DRAWN is 898 x 1.2 = 1078. Round to 1080.
 *
 * THE ZOOM IS WHY THIS IS NOT 898. Ken Burns scales the element, and the
 * background scales with it, so at the end of the pan the image is painted
 * at 1.2x the pane. Capping at the pane size would look right on the first
 * frame and soften over the next forty seconds -- the one thing a pan makes
 * obvious, because the viewer is staring at the same image the whole time.
 *
 * SQUARE, because the box is square and background-size: contain fits by
 * whichever side is limiting -- a portrait image is held by its height and
 * needs the same 1078 there. An earlier version used 940 x 650, taking the
 * 650 from "#results img { max-height: 650px }". That rule governs the
 * replay's <img> frames, which never pan; it says nothing about the
 * background the Ken Burns is applied to, and using it would have upscaled
 * a tall still by about 1.7x at full zoom.
 *
 * Going higher costs load for nothing: 1400 wide would be 68% more pixels
 * than the browser can ever show.
 */
function dropshipArtMaxWidth()  { return 1080; }
function dropshipArtMaxHeight() { return 1080; }

/**
 * The manifest, grouped the way somebody filling it in would want to read it.
 *
 * Same list dropshipArtManifest() returns -- derived from it, so the two
 * cannot drift -- just sorted into sections with a line saying what each one
 * is for. A flat list of seventy paths is a wall; this is a checklist.
 */
function dropshipArtGroups($ext = 'gif') {
	$all = dropshipArtManifest($ext);
	$groups = array(
		'The drop'     => array('note' => 'Rounds 0 to 2. Never rolled for, so every run opens with these.',
		                        'match' => '#^images/(reset|drop)/#'),
		'The beach'    => array('note' => 'Rounds 3 to 7, plus the frame shown on clearing it.',
		                        'match' => '#^images/live/beach/#'),
		'The hill'     => array('note' => 'Rounds 8 to 12, plus the frame shown on clearing it.',
		                        'match' => '#^images/live/hill/#'),
		'The bunker'   => array('note' => 'Round 13 on. No cleared frame: the bunker ends the run.',
		                        'match' => '#^images/live/bunker/#'),
		'Deaths'       => array('note' => 'Three generic, plus one per place a run can end.',
		                        'match' => '#^images/die/#'),
		'Extra life'   => array('note' => 'Shown when a Medkit saves a failed round.',
		                        'match' => '#^images/1up/#'),
		'Weapons'      => array('note' => 'Frames of the weapon chain. A rocket launcher runs to five.',
		                        'match' => '#^images/weapons/#'),
		'Equipment'    => array('note' => 'One frame each, shown when the item fires.',
		                        'match' => '#^images/equipment/#'),
	);
	$out = array();
	$taken = array();
	foreach ($groups as $name => $g) {
		$rows = array();
		foreach ($all as $rel) {
			if (preg_match($g['match'], $rel)) { $rows[] = $rel; $taken[$rel] = true; }
		}
		if ($rows) $out[$name] = array('note' => $g['note'], 'files' => $rows);
	}
	/* Anything the patterns did not claim still has to appear, or a file
	   added to the manifest would silently never be manageable. */
	$rest = array();
	foreach ($all as $rel) if (!isset($taken[$rel])) $rest[] = $rel;
	if ($rest) $out['Everything else'] = array('note' => 'Page furniture and placeholders.', 'files' => $rest);
	return $out;
}

/**
 * Is this an animated gif?
 *
 * Read from the bytes, because the extension is a claim and this decides
 * whether the file can safely be resized at all.
 */
function dropshipArtIsAnimatedGif($path) {
	$fh = @fopen($path, 'rb');
	if (!$fh) return false;
	$count = 0; $chunk = '';
	while (!feof($fh) && $count < 2) {
		$chunk .= fread($fh, 1024 * 100);
		$count = preg_match_all('#\x00\x21\xF9\x04#s', $chunk);
		$chunk = substr($chunk, -16);              /* keep a boundary overlap */
	}
	fclose($fh);
	return $count > 1;                             /* more than one frame block */
}

/**
 * Store an uploaded image into a theme slot, scaled to fit the pane.
 *
 * Returns array(ok, message).
 *
 * REFUSES TO FLATTEN AN ANIMATED GIF. GD cannot resize one -- it reads the
 * first frame and writes a still -- so on a box without Imagick an animated
 * upload is stored AT ITS ORIGINAL SIZE rather than silently turned into a
 * single frame. A slightly oversized animation is a bandwidth cost; a
 * flattened one is the theme broken in a way nobody would notice until a
 * player mentions the animations stopped.
 */
function dropshipArtStore($tmp_path, $dest_abs, $declared_ext) {
	$max_w = dropshipArtMaxWidth();
	$max_h = dropshipArtMaxHeight();

	$info = @getimagesize($tmp_path);
	if (!$info) return array(false, 'That file is not an image this server can read.');
	list($w, $h) = $info;

	$allowed = array(IMAGETYPE_GIF => 'gif', IMAGETYPE_PNG => 'png',
	                 IMAGETYPE_JPEG => 'jpg', IMAGETYPE_WEBP => 'webp');
	if (!isset($allowed[$info[2]])) return array(false, 'Only gif, png, jpg and webp can be uploaded.');
	$actual = $allowed[$info[2]];
	$want   = strtolower($declared_ext) === 'jpeg' ? 'jpg' : strtolower($declared_ext);
	if ($actual !== $want) {
		return array(false, "This slot is a .$want and that file is a $actual. "
		                  . 'The game asks for the slot by name, so the format has to match.');
	}

	$dir = dirname($dest_abs);
	if (!is_dir($dir) && !@mkdir($dir, 0755, true)) {
		return array(false, 'Could not create ' . $dir . '. Check the folder is writable.');
	}
	if (!is_writable($dir)) return array(false, $dir . ' is not writable by the web user.');

	$animated = ($actual === 'gif') && dropshipArtIsAnimatedGif($tmp_path);
	$fits     = ($w <= $max_w && $h <= $max_h);

	if ($fits) {
		return @move_uploaded_file($tmp_path, $dest_abs) || @rename($tmp_path, $dest_abs)
			? array(true, "Stored at {$w}x{$h}, already within {$max_w}x{$max_h}.")
			: array(false, 'Could not write ' . basename($dest_abs) . '.');
	}

	if ($animated && !class_exists('Imagick')) {
		$ok = @move_uploaded_file($tmp_path, $dest_abs) || @rename($tmp_path, $dest_abs);
		return $ok
			? array(true, "Stored at {$w}x{$h} WITHOUT resizing: this gif is animated and "
			            . 'Imagick is not available, and GD would flatten it to one frame.')
			: array(false, 'Could not write ' . basename($dest_abs) . '.');
	}

	/* Fit inside the pane, never enlarge. */
	$scale = min($max_w / $w, $max_h / $h, 1);
	$nw = max(1, (int)round($w * $scale));
	$nh = max(1, (int)round($h * $scale));

	if (class_exists('Imagick')) {
		try {
			$im = new Imagick($tmp_path);
			if ($animated) {
				$im = $im->coalesceImages();                 /* or frames corrupt each other */
				foreach ($im as $frame) $frame->thumbnailImage($nw, $nh);
				$im = $im->deconstructImages();
				$im->writeImages($dest_abs, true);
			} else {
				$im->thumbnailImage($nw, $nh);
				$im->writeImage($dest_abs);
			}
			$im->clear();
			return array(true, "Scaled {$w}x{$h} to {$nw}x{$nh}.");
		} catch (Throwable $e) {
			return array(false, 'Imagick could not process that file: ' . $e->getMessage());
		}
	}

	/* GD, stills only -- the animated case returned above. */
	$src = null;
	if ($actual === 'png')  $src = @imagecreatefrompng($tmp_path);
	if ($actual === 'jpg')  $src = @imagecreatefromjpeg($tmp_path);
	if ($actual === 'gif')  $src = @imagecreatefromgif($tmp_path);
	if ($actual === 'webp' && function_exists('imagecreatefromwebp')) $src = @imagecreatefromwebp($tmp_path);
	if (!$src) return array(false, 'This server cannot resize a ' . $actual . '.');

	$dst = imagecreatetruecolor($nw, $nh);
	if ($actual === 'png' || $actual === 'gif' || $actual === 'webp') {
		imagealphablending($dst, false);
		imagesavealpha($dst, true);
		imagefill($dst, 0, 0, imagecolorallocatealpha($dst, 0, 0, 0, 127));
	}
	imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
	$ok = false;
	if ($actual === 'png')  $ok = imagepng($dst, $dest_abs);
	if ($actual === 'jpg')  $ok = imagejpeg($dst, $dest_abs, 90);
	if ($actual === 'gif')  $ok = imagegif($dst, $dest_abs);
	if ($actual === 'webp' && function_exists('imagewebp')) $ok = imagewebp($dst, $dest_abs, 90);
	imagedestroy($src); imagedestroy($dst);

	return $ok ? array(true, "Scaled {$w}x{$h} to {$nw}x{$nh}.")
	           : array(false, 'Could not write ' . basename($dest_abs) . '.');
}

/**
 * A human name for a slot, worked out from its path.
 *
 * DERIVED, NOT A HAND-WRITTEN LIST, so a file added to the manifest gets a
 * readable name without anyone maintaining a second table that drifts. The
 * path already says what the thing is; this just says it in English.
 *
 * HONEST ABOUT WHAT IT DOES NOT KNOW: the numbered stage frames are picked
 * at random from the set, so frame 3 is not "round 3". They are called
 * scenes for that reason -- naming them after rounds would be a lie that
 * reads perfectly plausibly.
 */
function dropshipArtTitle($rel) {
	$stages = dropshipArtStages();
	$weapons = dropshipArtWeapons();
	$name = pathinfo($rel, PATHINFO_FILENAME);

	if ($rel === 'images/reset/reset.gif' || strpos($rel, 'images/reset/reset.') === 0) {
		return 'Round 0, the ship arriving';
	}
	if (strpos($rel, 'images/reset/intro.') === 0)  return 'The still shown before a run';
	if (strpos($rel, 'images/drop/drop.') === 0)    return 'Round 1, the drop';
	if (strpos($rel, 'images/drop/team.') === 0)    return 'Round 2, landed';

	if (preg_match('#^images/live/([a-z]+)/(exo/)?([a-z0-9-]+)\.#', $rel, $m)) {
		$stage = ucfirst($m[1]);
		$exo   = $m[2] !== '' ? ' in an Exo Suit' : '';
		if (!ctype_digit($m[3])) return $stage . ' cleared';
		$of = $m[2] !== '' ? $stages[$m[1]]['exo'] : $stages[$m[1]]['plain'];
		return $stage . $exo . ', scene ' . $m[3] . ' of ' . $of;
	}

	if (preg_match('#^images/die/([a-z0-9]+)\.#', $rel, $m)) {
		if (ctype_digit($m[1])) return 'Death, scene ' . $m[1] . ' of 3';
		if ($m[1] === 'landmine') return 'Death by landmine';
		return 'Death on the ' . $m[1];
	}
	if (preg_match('#^images/1up/([0-9]+)\.#', $rel, $m)) {
		return 'Extra life, scene ' . $m[1] . ' of 2';
	}

	if (preg_match('#^images/weapons/([a-z-]+?)([0-9]*)\.#', $rel, $m)) {
		$label = ucwords(str_replace('-', ' ', $m[1]));
		if ($m[2] === '' || !isset($weapons[$m[1]])) return $label;
		return $label . ', frame ' . $m[2] . ' of ' . $weapons[$m[1]];
	}
	if (preg_match('#^images/equipment/([a-z-]+?)([0-9]*)\.#', $rel, $m)) {
		$label = ucwords(str_replace('-', ' ', $m[1]));
		return $m[2] === '' ? $label : $label . ', frame ' . $m[2];
	}

	if (strpos($rel, 'images/nfts/placeholder') === 0) return 'Empty squad slot';
	if (strpos($rel, 'images/dropship.') === 0)        return 'The image Discord posts';
	return ucwords(str_replace(array('-', '_'), ' ', $name));
}

/**
 * The icons this theme asks for, and what it calls them.
 *
 * ICONS ARE ONE SHARED FOLDER, not one per theme. The markup asks for
 * `icons/<name>.png` RELATIVE to the page, and every project is served from
 * /staking/dropship/, so there is a single pool. Themes do not get their own
 * copy of medkit.png -- they rename the thing and ship a file under the new
 * name, which is why Oculus Lounge asks for icons/sexy-nurse.png.
 *
 * THE NAME IS NOT STORED ANYWHERE. It is the theme's own word made into a
 * filename, derived in dropshipThemeText(), so calling the Medkit a "Sexy
 * Nurse" IS the instruction to supply icons/sexy-nurse.png. Nothing else in
 * the system will tell you that, which is why this list exists and why it
 * sits beside the art for the same thing rather than in a block of its own.
 *
 * Returns array of array(term, name, path, exists) -- term is the Drop Ship
 * word, name is what this theme calls it.
 */
function dropshipThemeIconSlots($conn, $project_id) {
	/* REQUIRED, not probed. This returning an empty list because a file
	   happened not to be loaded would read on screen as "this theme asks
	   for no icons", which is never true and is not an error anybody would
	   investigate. */
	require_once __DIR__ . '/dropship-theme-vocab.php';
	$out = array();
	foreach (dropshipThemeVocabulary() as $group => $rows) {
		foreach ($rows as $r) {
			foreach ($r['icons'] as $term) {
				$name = $term;
				if (function_exists('dropshipThemeText')) {
					$themed = dropshipThemeText($conn, $project_id, $term);
					if ($themed !== null && $themed !== '') $name = $themed;
				}
				/* The name becomes a filename, so it has to survive being
				   one. A theme that renames to something with a space or a
				   slash is a 404 at play time, and saying so here is cheaper
				   than finding out from a missing icon. */
				$safe = (preg_match('#^[A-Za-z0-9._-]+$#', $name) === 1);
				$rel  = 'icons/' . $name . '.png';
				$out[] = array(
					'term'   => $term,
					/* The thing the icon is OF, which is what the admin is
					   actually looking for -- 'heavy-armor' is a filename,
					   'Heavy' is the word on the screen. */
					'label'  => $r['label'],
					'group'  => $group,
					'name'   => $name,
					'rel'    => $rel,
					'safe'   => $safe,
					'exists' => $safe && is_file(__DIR__ . '/' . $rel),
				);
			}
		}
	}
	return $out;
}

/**
 * The Drop Ship words a slot is a picture OF.
 *
 * WHY THE ART SCREEN NEEDS THE WORDING: a slot called
 * images/weapons/rocket-launcher3 is only meaningful if you know what your
 * theme calls a rocket launcher. Showing the original beside the mapping
 * turns "replace this file" into "replace the third frame of the thing your
 * players know as a Strap-On Cannon", which is the difference between
 * guessing and deciding.
 *
 * Derived from the path, so it needs no second table. Returns the terms as
 * they appear in the vocabulary, which is what dropshipThemeText() answers
 * for -- the Title Case display word and, where it exists, the lowercase
 * filename twin.
 */
function dropshipArtSlotTerms($rel) {
	$terms = array();
	$add = function ($t, $type) use (&$terms) {
		foreach ($terms as $e) if ($e['find'] === $t && $e['type'] === $type) return;
		$terms[] = array('find' => $t, 'type' => $type);
	};

	/* Stage art: the place, which themes rename as a description. */
	if (preg_match('#^images/live/([a-z]+)/#', $rel, $m)) {
		if (preg_match('#/([a-z]+)-secured\.#', $rel, $s)) {
			$add(ucfirst($s[1]) . ' Secured', 'exact');   /* "Beach Secured" is a label */
		}
		$add($m[1], 'description');
	}
	/* Deaths name the place they happen in. */
	if (preg_match('#^images/die/([a-z]+)\.#', $rel, $m) && !ctype_digit($m[1])) {
		if ($m[1] !== 'landmine') $add($m[1], 'description');
	}
	/* Weapons and equipment: the display word and its icon filename. */
	if (preg_match('#^images/(?:weapons|equipment)/([a-z-]+?)[0-9]*\.#', $rel, $m)) {
		/* The display word only. The lowercase twin is the ICON FILENAME,
		   and showing it here would say 'Rocket Launcher -> Strap-On Cannon'
		   immediately above 'rocket-launcher -> strap-on-cannon' -- the same
		   fact spelled twice. The icons have their own section. */
		$add(ucwords(str_replace('-', ' ', $m[1])), 'exact');
	}
	return $terms;
}

/**
 * Those terms resolved through a theme: original beside what it says.
 *
 * Only returns the ones the theme actually answers for, so a slot whose
 * words are untouched shows nothing rather than a row of "X -> X".
 */
function dropshipArtSlotMapping($conn, $project_id, $rel) {
	$out = array();
	if (!function_exists('dropshipThemeText')) return $out;
	foreach (dropshipArtSlotTerms($rel) as $t) {
		$said = dropshipThemeText($conn, $project_id, $t['find']);
		if ($said === null || $said === '' || $said === $t['find']) continue;
		$out[] = array('from' => $t['find'], 'to' => $said);
	}
	return $out;
}

/**
 * The icon filename a piece of round art is the same thing as.
 *
 * images/weapons/rocket-launcher3.gif and icons/rocket-launcher.png are two
 * pictures of one object, and the only reason they were managed on separate
 * halves of the screen is that they live in different folders. The stem of
 * the file IS the icon term, bar one shortening.
 *
 * Returns the icon term, or null when the art is not of a nameable thing
 * (a beach frame, a death, the extra-life card).
 */
function dropshipArtIconTerm($rel) {
	/* 'demo' is just 'demolition' typed short in the art folder. Declared,
	   not guessed: a prefix rule would also pair 'drop-box' with
	   'drop-ship', which are different objects. */
	static $alias = array('demo' => 'demolition');
	if (!preg_match('#^images/(?:weapons|equipment)/([a-z-]+?)[0-9]*\.#', $rel, $m)) return null;
	$stem = $m[1];
	return isset($alias[$stem]) ? $alias[$stem] : $stem;
}

/**
 * Every slot for a theme, art and icons together, grouped by the thing.
 *
 * WHY THIS EXISTS. The art screen used to be two lists: round art grouped by
 * folder, then every icon in one block at the bottom. So replacing "the
 * rocket launcher" meant finding five frames in one place and its icon in
 * another, and the icon block was the only thing on the page that was not
 * about a theme's own art at all. They are the same job.
 *
 * Returns group => array('note', 'slots'), where a slot is
 * array('kind' => 'art'|'icon', ...) -- an art slot carries 'rel' under the
 * theme folder, an icon slot carries what dropshipThemeIconSlots() returns.
 */
function dropshipArtPlan($conn, $project_id, $ext = 'gif') {
	$icons = array();
	foreach (dropshipThemeIconSlots($conn, $project_id) as $ic) $icons[$ic['term']] = $ic;

	$out = array();
	foreach (dropshipArtGroups($ext) as $name => $g) {
		$slots  = array();
		$placed = array();
		$files  = array_values($g['files']);
		foreach ($files as $i => $rel) {
			$slots[] = array('kind' => 'art', 'rel' => $rel);
			/* The icon closes the run rather than splitting it: it goes in
			   after the LAST frame of the thing, which is the frame whose
			   successor is something else. */
			$term = dropshipArtIconTerm($rel);
			if ($term === null || isset($placed[$term]) || !isset($icons[$term])) continue;
			$next = isset($files[$i + 1]) ? dropshipArtIconTerm($files[$i + 1]) : null;
			if ($next === $term) continue;
			$slots[] = array('kind' => 'icon') + $icons[$term];
			$placed[$term] = true;
		}
		$out[$name] = array('note' => $g['note'], 'slots' => $slots);
		foreach ($placed as $term => $_) unset($icons[$term]);
	}

	/*
	 * WHAT IS LEFT HAS NO ROUND ART -- armour, the suits, the Medkit, the
	 * squad marks. They are still a theme's pictures, so they get their own
	 * sections named after the vocabulary group they came from rather than
	 * one undifferentiated block of leftovers.
	 */
	$notes = array(
		'Armor'    => 'Worn on a soldier card and in the shop. No round art: armour is never a frame.',
		'Items'    => 'Carried gear that has no frame of its own.',
		'Identity' => 'The squad marks, used in the nav and on the scoreboard.',
	);
	foreach ($icons as $term => $ic) {
		$name = isset($notes[$ic['group']]) ? $ic['group'] : 'Everything else';
		if (!isset($out[$name])) {
			$out[$name] = array('note' => isset($notes[$name]) ? $notes[$name]
			                            : 'Icons with no round art of their own.',
			                    'slots' => array());
		}
		$out[$name]['slots'][] = array('kind' => 'icon') + $ic;
	}
	return $out;
}

/**
 * The files actually sitting in the shared icons folder.
 *
 * Needed because a theme can ask for a name nothing is stored under yet,
 * while the picture it wants IS on the server under an older name -- which
 * is exactly what happens the first time a theme's wording changes. Listing
 * them is what makes the rename possible without an FTP client.
 */
function dropshipArtIconFiles() {
	$dir = rtrim(__DIR__, '/') . '/icons';
	if (!is_dir($dir)) return array();
	$out = array();
	foreach ((array)@scandir($dir) as $f) {
		if (substr($f, -4) === '.png' && is_file($dir . '/' . $f)) $out[] = substr($f, 0, -4);
	}
	sort($out);
	return $out;
}

/**
 * Rename an icon on the server. Returns '' on success, else why not.
 *
 * THE SHARED FOLDER IS THE WHOLE RISK. There is one icons/ for every theme,
 * so a rename is not a private edit: another theme asking for the old name,
 * or a page with the name written into it, loses its picture silently -- a
 * missing icon is not an error, it is just a gap nobody notices for a week.
 * So this refuses to move a file any OTHER theme still asks for, and refuses
 * to overwrite one that already exists.
 *
 * Both names are rebuilt from scratch rather than trusted: they arrive from
 * a form, and they are about to be concatenated into a filesystem path.
 */
function dropshipArtIconRename($conn, $from, $to, $project_id) {
	$from = dropshipThemeSlug($from);
	$to   = dropshipThemeSlug($to);
	if ($from === '' || $to === '')   return 'Both names are required.';
	if ($from === $to)                return 'Those are the same name.';

	$dir = rtrim(__DIR__, '/') . '/icons';
	if (!is_file($dir . '/' . $from . '.png')) return "There is no icons/$from.png to rename.";
	if (file_exists($dir . '/' . $to . '.png'))
		return "icons/$to.png already exists. Replace it with an upload instead, "
		     . 'so you can see what you are covering up.';

	/* Who else is using it. A rename that silently blanks another theme's
	   shop is worse than making somebody do it twice. */
	foreach (dropshipThemeAll($conn) as $t) {
		if ((int)$t['project_id'] === (int)$project_id) continue;
		foreach (dropshipThemeIconSlots($conn, (int)$t['project_id']) as $ic) {
			if ($ic['name'] === $from) {
				return 'Cannot rename: ' . htmlspecialchars((string)$t['slug'])
				     . " also asks for icons/$from.png. Upload a copy under the new "
				     . 'name instead.';
			}
		}
	}
	if (!@rename($dir . '/' . $from . '.png', $dir . '/' . $to . '.png')) {
		return "Could not rename icons/$from.png. Check the folder is writable.";
	}
	return '';
}
