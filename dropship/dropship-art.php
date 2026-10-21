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
		/*
		 * TWELVE, NOT SIX. live() asks for 1-6 while the round counter is
		 * under 16 and 7-12 from 16 on -- deeper into the bunker, the only
		 * stage with two bands -- and this said 6, so frames 7 to 12 were
		 * asked for by the run and listed by nothing. No slot, no upload,
		 * and no way to see they were absent: reported as a blank frame on
		 * a long Dread City run, inspected as bunker/11.
		 *
		 * Drop Ship, Filthy Mermaid and Oculus Lounge all have the full
		 * twelve on disk in the format they render. Dread City renders jpg
		 * and has jpgs for 1-6 only, which is why it is the one that broke.
		 */
		'bunker' => array('plain' => 12, 'exo' => 2, 'secured' => null),
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
 * toggle; that is retired and the value lives on themes.extension.
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
	/* The end-of-run screen, which sits on the results pane until the next
	   game. It was asked for as a hardcoded images/gameover.gif and so was
	   never listed here at all -- no slot, no upload, no way to give a
	   theme its own, which is exactly how all four came to be showing Drop
	   Ship's. LISTED HERE, not with the chrome at the end, because the
	   probe that decides whether a theme has animated art takes the last
	   gif in this list and that should stay a round frame. */
	$m[] = "images/gameover.$ext";

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

	/* Chrome the pages and the Discord embeds reach for. Every one of these
	   is asked for by LITERAL NAME in the code, not through the theme's
	   extension, so they are listed literally here too -- and a file that is
	   not listed here has no upload slot, which is how several of them came
	   to be the same picture in every theme. loading.gif is built by name in
	   dropship.js (dsShowLoader) and stays a gif on purpose: it is a spinner,
	   and a still one would say nothing. */
	$m[] = 'images/dropship.jpg';
	$m[] = 'images/vip.gif';
	$m[] = 'images/loading.gif';
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

/**
 * Theme root, relative to the dropship directory. '' for Drop Ship itself.
 *
 * Delegates: the rule lives in dropship-themes.php, which the game loads
 * and this file does not get loaded by. Four copies of it had accumulated.
 */
function dropshipArtRoot($slug) {
	$root = dropshipThemeRoot($slug);
	return $root === null ? '' : $root;
}

/**
 * Is this file in a reskin's folder just Drop Ship's own file, copied?
 *
 * A PRESENT FILE IS NOT THE SAME AS A THEMED ONE, and the art page could
 * not tell the difference -- a slot showed green whether it held this
 * theme's picture or Drop Ship's, so "68 of 70 in place" read as finished
 * when most of it was the original game.
 *
 * REPORTED FROM DISCORD: a Dread City run posted Drop Ship's animated
 * death. The url was right (dread-city/images/die/1.gif) and the file was
 * there; it is a byte-for-byte copy of dropship/images/die/1.gif. Dread
 * City's real art is the .jpg set beside it, and its whole .gif set is
 * Drop Ship's.
 *
 * SIZE FIRST, HASH ONLY ON A TIE. Drop Ship's art runs to 8MB a file and
 * this is asked about seventy slots on one page load; two different
 * pictures almost never weigh exactly the same, so the hash is paid for
 * only by the files that are actually suspect.
 */
function dropshipArtIsDropShipCopy($abs, $rel) {
	$own = rtrim(__DIR__, '/') . '/' . ltrim($rel, '/');
	/* Drop Ship's own art IS Drop Ship's art; there is nothing to report. */
	if (realpath($abs) === realpath($own)) return false;
	/* NOT @-SUPPRESSED BELOW. display_errors is on platform-wide, so a
	   warning raised here prints into the art page -- which is the whole
	   reason this guard is a guard and not a silencer. */
	if (!is_file($abs) || !is_file($own)) return false;
	$a = filesize($abs);
	$b = filesize($own);
	if ($a === false || $b === false || $a !== $b) return false;
	return md5_file($abs) === md5_file($own);
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
/* The page background is painted with `background-size: cover` and
   `background-attachment: fixed` on the body, so it is scaled to the
   VIEWPORT, not to a pane -- 1080 would be visibly soft on any normal
   desktop. 1920 covers the common widths; `cover` stretches past that
   gently, and the two that exist today are 1000 and 2000 wide. */
function dropshipArtMaxBackgroundWidth()  { return 1920; }
function dropshipArtMaxBackgroundHeight() { return 1920; }

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
		/*
		 * THE INTRO STILL IS NOT A ROUND, and it was sitting in the middle
		 * of the ones that are. The manifest lists it second -- reset.png,
		 * intro.jpg, drop.png, team.png -- so THE DROP rendered as
		 * "Round 0, the still shown before a run, Round 1, Round 2", with
		 * the one card that is not a round wedged between 0 and 1 and the
		 * numbers reading 0, 1, 2 only if you skipped the second card.
		 * Its sound row sat in the same place for the same reason.
		 *
		 * Its own section, ahead of THE DROP, because it is shown BEFORE a
		 * run rather than during one. Ordering here is what fixes the sound
		 * rows too: dropshipArtPlan() builds those per group from this same
		 * files list, so the audio follows the picture without being
		 * moved separately.
		 */
		'Before a run' => array('note' => 'The still on the dashboard before anybody presses start. '
		                                . 'Not a round, so it is never rolled for and never appears mid-run.',
		                        'match' => '#^images/reset/intro\.#'),
		/*
		 * NARROWED from '#^images/(reset|drop)/#'. The claim loop below does
		 * not skip a file another group already took -- it sets $taken but
		 * only reads it for the catch-all -- so a pattern that still matched
		 * intro would list it in both sections rather than moving it.
		 */
		'The drop'     => array('note' => 'Rounds 0 to 2. Never rolled for, so every run opens with these.',
		                        'match' => '#^images/(reset/reset|drop/)#'),
		'The beach'    => array('note' => 'Rounds 3 to 7, plus the frame shown on clearing it.',
		                        'match' => '#^images/live/beach/#'),
		'The hill'     => array('note' => 'Rounds 8 to 12, plus the frame shown on clearing it.',
		                        'match' => '#^images/live/hill/#'),
		'The bunker'   => array('note' => 'Round 13 on, in two bands: scenes 1 to 6 carry rounds 13 '
		                                . 'to 15 and 7 to 12 carry round 16 and beyond. No cleared '
		                                . 'frame: the bunker ends the run.',
		                        'match' => '#^images/live/bunker/#'),
		'Deaths'       => array('note' => 'Three generic, plus one per place a run can end, '
		                                . 'and the screen that stays up afterwards.',
		                        'match' => '#^images/(die/|gameover\.)#'),
		'Extra life'   => array('note' => 'Shown when a Medkit saves a failed round.',
		                        'match' => '#^images/1up/#'),
		'Weapons'      => array('note' => 'Frames of the weapon chain. A rocket launcher runs to five.',
		                        'match' => '#^images/weapons/#'),
		'Equipment'    => array('note' => 'One frame each, shown when the item fires.',
		                        'match' => '#^images/equipment/#'),
		/* ITS OWN SECTION, because these two were the one thing in here
		   nobody could find. They sat in the trailing "Everything else /
		   page furniture and placeholders" band, headerless, at the bottom
		   of seventy slots -- so the image on every Discord post read as
		   something that could not be themed at all, and stayed Drop
		   Ship's. */
		/* NAMED, NOT LEFT TO THE CATCH-ALL. "Everything else / page furniture
		   and placeholders" is where the Discord images were, and nobody found
		   them there; these two are page furniture in the same way and would
		   have gone the same way. The catch-all stays for anything added later,
		   it just no longer holds the things we can name. */
		'Around the game' => array('note' => 'Not part of a run: the spinner shown while a theme '
		                                   . 'loads, and the picture that fills an empty squad slot.',
		                        'match' => '#^images/(loading\.gif|nfts/placeholder\.jpg)$#'),
		'Discord'      => array('note' => 'What the announcements carry: the big image on every post, '
		                                . 'and the small thumbnail beside the title. Change these and '
		                                . 'the Game Over, New Game, death and leaderboard posts all follow.',
		                        'match' => '#^images/(dropship\.jpg|vip\.gif)$#'),
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
 * Make a freshly written slot readable by whatever serves it.
 *
 * REQUIRED, NOT TIDINESS. dropshipArtStore() MOVES its input when the
 * file already fits and needs no conversion, and a move preserves the
 * SOURCE's permissions. An uploaded file comes from PHP's own upload
 * directory and lands at the umask, but tempnam() creates at 0600 -- so
 * when the drag-a-slot-onto-another-slot copy started handing the store
 * a tempnam() file, every copied slot landed unreadable by anything but
 * the PHP user. On this server static files are served by Apache, not
 * PHP, so the file stored perfectly, reported its real dimensions, and
 * showed as a broken image.
 *
 * Applied to every write rather than to that one path, because which
 * branch runs depends on the picture and nobody choosing a slot is
 * thinking about it.
 */
function dropshipArtReadable($path) {
	@chmod($path, 0644);
	return $path;
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
function dropshipArtStore($tmp_path, $dest_abs, $declared_ext, $max_w = 0, $max_h = 0) {
	/*
	 * THE PAGE BACKGROUND NEEDS MORE ROOM THAN ROUND ART. Round art is
	 * capped at the pane it is painted into; a background is
	 * `background-size: cover` on the whole viewport, so the limit is the
	 * widest screen rather than a box on the page. Callers pass their own
	 * maximum; everything else keeps the pane-derived default.
	 */
	if ($max_w <= 0) $max_w = dropshipArtMaxWidth();
	if ($max_h <= 0) $max_h = dropshipArtMaxHeight();

	$info = @getimagesize($tmp_path);
	if (!$info) return array(false, 'That file is not an image this server can read.');
	list($w, $h) = $info;

	$allowed = array(IMAGETYPE_GIF => 'gif', IMAGETYPE_PNG => 'png',
	                 IMAGETYPE_JPEG => 'jpg', IMAGETYPE_WEBP => 'webp');
	if (!isset($allowed[$info[2]])) return array(false, 'Only gif, png, jpg and webp can be uploaded.');
	$actual = $allowed[$info[2]];
	$want   = strtolower($declared_ext) === 'jpeg' ? 'jpg' : strtolower($declared_ext);
	if (!in_array($want, array('gif', 'png', 'jpg', 'webp'), true)) {
		return array(false, 'A slot can only be a gif, png, jpg or webp.');
	}
	/*
	 * THE SLOT'S FORMAT WINS, so a mismatch is CONVERTED rather than
	 * refused. The run asks for its art by filename, extension included --
	 * images/weapons/rocket-launcher1.gif -- so the file has to end up in
	 * that format whatever arrived. Refusing meant exporting again in the
	 * right format and coming back, for a conversion the server can do.
	 *
	 * The lossy cases are NAMED in the result rather than done quietly:
	 * see $notes below. Silently turning a photograph into a 256-colour
	 * gif, or flattening an animation, is worse than saying so.
	 */
	$convert = ($actual !== $want);
	$notes = array();

	$dir = dirname($dest_abs);
	if (!is_dir($dir) && !@mkdir($dir, 0755, true)) {
		return array(false, 'Could not create ' . $dir . '. Check the folder is writable.');
	}
	if (!is_writable($dir)) return array(false, $dir . ' is not writable by the web user.');

	$animated = ($actual === 'gif') && dropshipArtIsAnimatedGif($tmp_path);
	$fits     = ($w <= $max_w && $h <= $max_h);

	if ($animated && $want !== 'gif') {
		$notes[] = 'its animation was lost, because this slot is a .' . $want;
	}
	if ($convert && $want === 'gif' && $actual !== 'gif') {
		$notes[] = 'gif is 256 colours, so a photograph will band';
	}

	/* MOVED ONLY WHEN NOTHING HAS TO CHANGE. A format change always means
	   a re-encode, however well the image already fits. */
	if ($fits && !$convert) {
		$ok = @move_uploaded_file($tmp_path, $dest_abs) || @rename($tmp_path, $dest_abs);
		if ($ok) dropshipArtReadable($dest_abs);
		return $ok
			? array(true, "Stored at {$w}x{$h}, already within {$max_w}x{$max_h}.")
			: array(false, 'Could not write ' . basename($dest_abs) . '.');
	}

	if ($animated && $want === 'gif' && !$fits && !class_exists('Imagick')) {
		$ok = @move_uploaded_file($tmp_path, $dest_abs) || @rename($tmp_path, $dest_abs);
		if ($ok) dropshipArtReadable($dest_abs);
		return $ok
			? array(true, "Stored at {$w}x{$h} WITHOUT resizing: this gif is animated and "
			            . 'Imagick is not available, and GD would flatten it to one frame.')
			: array(false, 'Could not write ' . basename($dest_abs) . '.');
	}

	/* Fit inside the pane, never enlarge. */
	$scale = min($max_w / $w, $max_h / $h, 1);
	$nw = max(1, (int)round($w * $scale));
	$nh = max(1, (int)round($h * $scale));

	/* What to say at the end, once it has worked. */
	$did = function () use ($convert, $actual, $want, $w, $h, $nw, $nh, &$notes) {
		$said = ($nw === $w && $nh === $h)
			? "Stored at {$w}x{$h}"
			: "Scaled {$w}x{$h} to {$nw}x{$nh}";
		if ($convert) $said .= ", converted $actual to $want";
		$said .= '.';
		if ($notes) $said .= ' Note: ' . implode('; ', $notes) . '.';
		return $said;
	};

	if (class_exists('Imagick')) {
		try {
			$im = new Imagick($tmp_path);
			/* KEEP THE FRAMES ONLY IF THE TARGET CAN HOLD THEM. Writing a
			   multi-frame Imagick to a .png produces png-0, png-1, ... one
			   file per frame, which is not a slot the run ever asks for. */
			if ($animated && $want === 'gif') {
				$im = $im->coalesceImages();                 /* or frames corrupt each other */
				foreach ($im as $frame) {
					$frame->thumbnailImage($nw, $nh);
					$frame->setImageFormat($want);
				}
				$im = $im->deconstructImages();
				$im->writeImages($dest_abs, true);
			} else {
				if ($animated) $im = $im->coalesceImages();  /* frame 0 alone is not the picture */
				$im->setFirstIterator();
				$im->thumbnailImage($nw, $nh);
				if ($want === 'jpg') {
					/* Alpha has to land on something. The page is dark, so
					   white would ring every transparent edge. */
					$flat = new Imagick();
					$flat->newImage($nw, $nh, new ImagickPixel('#07111d'));
					$flat->compositeImage($im, Imagick::COMPOSITE_OVER, 0, 0);
					$im->clear();
					$im = $flat;
					$im->setImageCompressionQuality(90);
				}
				$im->setImageFormat($want === 'jpg' ? 'jpeg' : $want);
				$im->writeImage($dest_abs);
			}
			$im->clear();
			dropshipArtReadable($dest_abs);
			return array(true, $did());
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
	/* KEYED ON THE TARGET, not the source. A png dropped on a .jpg slot
	   kept its alpha channel through the resize and then had it discarded
	   by the jpeg encoder, which renders every transparent pixel black. */
	if ($want === 'jpg') {
		/* The page is dark, so flattening onto white rings every edge. */
		imagefill($dst, 0, 0, imagecolorallocate($dst, 0x07, 0x11, 0x1d));
		imagealphablending($dst, true);
	} else {
		imagealphablending($dst, false);
		imagesavealpha($dst, true);
		imagefill($dst, 0, 0, imagecolorallocatealpha($dst, 0, 0, 0, 127));
	}
	imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
	$ok = false;
	if ($want === 'png')  $ok = imagepng($dst, $dest_abs);
	if ($want === 'jpg')  $ok = imagejpeg($dst, $dest_abs, 90);
	if ($want === 'gif')  $ok = imagegif($dst, $dest_abs);
	if ($want === 'webp') $ok = function_exists('imagewebp') ? imagewebp($dst, $dest_abs, 90) : false;
	/* imagedestroy() removed: it has done nothing since PHP 8.0 -- a GDImage
	   is an object and is freed when the last reference goes -- and 8.5
	   deprecates calling it, which with display_errors on prints a notice
	   into whatever page resized the art. Unsetting is the honest version
	   of the same intent. */
	unset($src, $dst);

	if (!$ok && $want === 'webp' && !function_exists('imagewebp')) {
		return array(false, 'This server cannot write webp.');
	}
	if ($ok) dropshipArtReadable($dest_abs);
	return $ok ? array(true, $did())
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
		/* THE BUNKER HAS TWO BANDS and they are not interchangeable: 1-6
		   are rounds 13 to 15, 7-12 are round 16 and beyond. "Scene 11 of
		   12" says nothing about which; a run that gets that deep is rare
		   and these are the frames most likely to be the ones missing. */
		if ($m[1] === 'bunker' && $m[2] === '' && (int)$m[3] >= 7) {
			return 'Bunker, round 16 and beyond, scene ' . ((int)$m[3] - 6) . ' of 6';
		}
		if ($m[1] === 'bunker' && $m[2] === '') {
			return 'Bunker, rounds 13 to 15, scene ' . $m[3] . ' of 6';
		}
		return $stage . $exo . ', scene ' . $m[3] . ' of ' . $of;
	}

	if (strpos($rel, 'images/gameover.') === 0) return 'Game over, until the next game';
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

	if ($rel === 'images/loading.gif')                 return 'Spinner, while a theme loads';
	if (strpos($rel, 'images/nfts/placeholder') === 0) return 'Empty squad slot';
	if ($rel === 'images/vip.gif')                     return 'Discord thumbnail, beside the title';
	if (strpos($rel, 'images/dropship.') === 0)        return 'Discord image, on every post';
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
 * The family a piece of art or an icon belongs to: term => display label.
 *
 * Keyed on BOTH the icon filenames and the slug of the label, because the
 * art folder names a file after the thing ('rocket-launcher3.gif') while
 * the icon is named after it too, and some things have art but no icon at
 * all (Airstrike, Reinforcements).
 */
function dropshipArtThingLabels() {
	static $map = null;
	if ($map === null) {
		$map = array();
		require_once __DIR__ . '/dropship-theme-vocab.php';
		foreach (dropshipThemeVocabulary() as $rows) {
			foreach ($rows as $r) {
				$map[dropshipThemeSlug($r['label'])] = $r['label'];
				foreach ($r['icons'] as $ic) $map[$ic] = $r['label'];
			}
		}
	}
	return $map;
}

/**
 * Every slot for a theme, grouped into the THING each one is a picture of.
 *
 * WHY ROWS AND NOT ONE GRID. A reskin is decided one object at a time --
 * "what is our rocket launcher" -- and the answer is five frames plus an
 * icon. Flowed into a single grid those six sat wherever the column count
 * put them, split across lines, with nothing saying they belonged together
 * or what this theme calls the thing. Each thing gets its own row now,
 * headed by its name and what the theme renames it to.
 *
 * Returns group => array('note', 'rows'), where a row is
 * array('label', 'said', 'slots'). A row with an empty label is art that is
 * not a picture of a nameable thing -- beach frames, deaths -- and is
 * rendered as a plain band with no header.
 */
function dropshipArtPlan($conn, $project_id, $ext = 'gif', $project_slug = '') {
	$icons = array();
	foreach (dropshipThemeIconSlots($conn, $project_id) as $ic) $icons[$ic['term']] = $ic;
	$labels = dropshipArtThingLabels();

	$out = array();
	foreach (dropshipArtGroups($ext) as $name => $g) {
		$rows = array();                 /* term => row, '' => the unnamed band */
		foreach ($g['files'] as $rel) {
			/*
			 * THE EXO FRAMES ARE A SEPARATE ROTATION, not more frames of
			 * the same stage. A player wearing the suit sees these
			 * INSTEAD of the plain ones, so mixing the two in one band
			 * reads as a nine-frame beach when it is really seven and
			 * two alternates. Their own row, headed by whatever the
			 * theme calls the suit.
			 *
			 * KEYED ON A NAME NO ICON USES. The row key doubles as an
			 * icon lookup further down -- a row called 'exo-suit' would
			 * pull the Exo Suit icon out of the Armor section and into
			 * the beach, and then the hill and the bunker would not get
			 * one either, because it is removed once taken.
			 */
			$term = (strpos($rel, '/exo/') !== false) ? '@exo' : dropshipArtIconTerm($rel);
			if ($term !== '@exo' && ($term === null || !isset($labels[$term]))) $term = '';
			if (!isset($rows[$term])) $rows[$term] = array();
			$rows[$term][] = array('kind' => 'art', 'rel' => $rel);
		}
		/* The icon closes its own row rather than trailing the group. */
		foreach (array_keys($rows) as $term) {
			if ($term !== '' && isset($icons[$term])) {
				$rows[$term][] = array('kind' => 'icon') + $icons[$term];
				unset($icons[$term]);
			}
		}
		$out[$name] = array('note' => $g['note'], 'rows' => dropshipArtRowList($rows, $labels, $conn, $project_id));
	}

	/*
	 * THE PAGE BACKGROUND, first, because it is the one image somebody
	 * notices before anything else on the page.
	 */
	$bg = dropshipArtBackgroundSlot($project_slug);
	if ($bg) {
		$out = array('The page background' => array(
			'note' => 'Behind every page of this theme. Scaled to the viewport, not to a pane, '
			        . 'so it is allowed to be bigger than the round art.',
			'rows' => array(array('label' => 'Page background', 'said' => '', 'slots' => array(
				array('kind' => 'bg', 'rel' => $bg['rel'], 'exists' => $bg['exists']),
			))),
		)) + $out;
	}

	/*
	 * WHAT IS LEFT HAS NO ROUND ART -- armour, the suits, the Medkit, the
	 * squad marks. Still a theme's pictures, so they get sections named for
	 * what they are rather than a bin of leftovers, one row each.
	 */
	$notes = array(
		'Armor'    => 'Worn on a soldier card and in the shop. No round art: armour is never a frame.',
		'Items'    => 'Carried gear that has no frame of its own.',
		'Identity' => 'The squad marks, used in the nav and on the scoreboard.',
	);
	/* BY THING, NOT BY FILE. Super Soldier owns two icons (dropship.png and
	   supersoldier.png) and they are two pictures of one thing, so they
	   share a row -- a row each listed it twice under the same heading. */
	$left = array();
	foreach ($icons as $term => $ic) {
		$name = isset($notes[$ic['group']]) ? $ic['group'] : 'Everything else';
		$left[$name][$ic['label']][] = array('kind' => 'icon') + $ic;
	}
	foreach ($left as $name => $things) {
		if (!isset($out[$name])) {
			$out[$name] = array('note' => isset($notes[$name]) ? $notes[$name]
			                            : 'Icons with no round art of their own.',
			                    'rows' => array());
		}
		$byterm = array();
		foreach ($things as $label => $slots) $byterm[dropshipThemeSlug($label)] = $slots;
		$out[$name]['rows'] = array_merge($out[$name]['rows'],
			dropshipArtRowList($byterm, $labels, $conn, $project_id));
	}
	return $out;
}

/** Turn term => slots into the row shape, resolving what the theme calls it. */
function dropshipArtRowList($rows, $labels, $conn, $project_id) {
	$out = array();
	foreach ($rows as $term => $slots) {
		/* '@exo' is not a thing in the vocabulary, it is a row of a
		   stage -- the alternate rotation a player in the suit sees. It
		   still gets the theme's word for the suit as its heading. */
		$label = $term === '@exo' ? 'Exo Suit'
		       : (($term !== '' && isset($labels[$term])) ? $labels[$term] : '');
		$said  = '';
		if ($label !== '' && function_exists('dropshipThemeText')) {
			$t = dropshipThemeText($conn, $project_id, $label);
			/* Only when it actually renames it: "Grenade -> Grenade" is noise. */
			if ($t !== null && $t !== '' && $t !== $label) $said = $t;
		}
		$out[] = array('label' => $label, 'said' => $said, 'slots' => $slots);
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

/* dropshipArtBackgroundSlot() lives in dropship-themes.php, which every
   Drop Ship page loads -- the game needs the background too, not just
   this admin, and two copies of the flat-vs-nested rule is one too many. */


/**
 * Replace a theme's background, whatever extension it had.
 *
 * ONE BACKGROUND PER THEME, so the old file is removed rather than left
 * beside the new one: the lookup globs for <stem>.* and would otherwise
 * pick between a .jpg and a .png by alphabet, which is not a decision
 * anybody made. Returns '' on success, else why not.
 */
function dropshipArtStoreBackground($tmp_path, $slug, $declared_ext) {
	$slot = dropshipArtBackgroundSlot($slug);
	if (!$slot) return 'That theme has no slug, so there is nowhere to put it.';
	$ext = strtolower($declared_ext);
	if (!in_array($ext, array('jpg', 'jpeg', 'png', 'gif', 'webp'), true)) {
		return 'A background has to be a jpg, png, gif or webp.';
	}
	$dest = rtrim(__DIR__, '/') . '/' . $slot['dir'] . $slot['stem'] . '.' . $ext;
	list($ok, $why) = dropshipArtStore($tmp_path, $dest, $ext,
		dropshipArtMaxBackgroundWidth(), dropshipArtMaxBackgroundHeight());
	if (!$ok) return $why;
	/* Drop the other extensions, or the glob chooses between them. */
	foreach ((array)@glob(rtrim(__DIR__, '/') . '/' . $slot['dir'] . $slot['stem']
	                      . '.{jpg,jpeg,png,gif,webp}', GLOB_BRACE) as $old) {
		if (realpath($old) !== realpath($dest)) @unlink($old);
	}
	return '';
}
