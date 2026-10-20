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
