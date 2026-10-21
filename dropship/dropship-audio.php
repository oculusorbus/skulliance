<?php
/**
 * dropship/dropship-audio.php -- which sound a round plays, and where it lives.
 *
 * THE FILENAME IS THE WORDING. Exactly the rule the icons follow: nothing
 * about a theme's audio is stored anywhere, because the theme already
 * says what it calls the thing. Oculus Lounge calling the Melee a
 * "Vibrator" IS the instruction to supply sounds/vibrator.mp3, in the
 * same way it is the instruction to supply icons/vibrator.png.
 *
 * WHAT THIS REPLACES. displayRound() in dropship.js carried thirty-four
 * if-branches of caption text against sound filenames, and every reskin's
 * own words were in there beside Drop Ship's -- "Vibrator", "Dildo",
 * "Whip", "Paddle", "Ball Gag", "Butt Plug", "Insert Anal Beads". Every
 * one of those is a word the wording table already holds:
 *
 *     Vibrator   = Melee            Paddle     = Demolition
 *     Dildo      = Tactical Katana  Ball Gag   = Radar
 *     Whip       = Smoke Bomb       Butt Plug  = Sniper Rifle
 *     Spank      = Explosion        Anal Beads = Grenade
 *
 * So the branches were the wording table, written a second time in
 * JavaScript, where nobody could see it and no admin page could reach it.
 * Twenty-five events is what is left once they are folded back in.
 *
 * AND THE project_id GUARDS GO WITH THEM. A dozen branches were wrapped
 * in `project_id != 3 && project_id != 4`, which is "Dread City and
 * Filthy Mermaid have no sound here" spelled as a hardcoded id check. A
 * theme with no file at the name it asks for is silent, which is the same
 * answer arrived at honestly -- and it is now a thing an admin can fix by
 * uploading a file rather than a thing that needs an edit here.
 *
 * ONE SHARED FOLDER, like the icons. sounds/ is addressed relative to the
 * page and every project is served from /staking/dropship/, so there is a
 * single pool. That is the whole reason overwriting one needs a warning:
 * a file is not owned by the theme that led you to it.
 */

/**
 * EVERY FRAME THE RUN CAN SHOW, and what it sounds like.
 *
 * KEYED ON THE ART, NOT THE CAPTION. The first pass matched a substring
 * of the round's caption, which cannot tell machine-gun2 from
 * machine-gun3 -- both are captioned "Machine Gun" -- nor
 * rocket-launcher2 from 3. The frame a round is showing is a fact about
 * its IMAGE. An exact key also ends the ordering problem the caption
 * chain had, where "Smoke Bomb" shadowed "Pull Smoke Bomb Pin" unless
 * the list was kept in a sequence it never declared.
 *
 *   art      the image, relative to the theme's images/ folder
 *   family   the thing it is a frame OF, which is a wording term
 *   n        which frame, or 0 when the thing has only one
 *   caption   what the round says, for the admin page to show
 *   sound    what Drop Ship's file is called TODAY, so nothing goes
 *            quiet before the files are renamed onto the convention
 *   delay    when the sound lands against the animation, which is a
 *            property of the frame and not of the theme
 */
function dropshipAudioEvents() {
	return array(
		array('art' => 'live/beach/beach-secured', 'family' => 'Beach Secured', 'n' => 0, 'caption' => 'Beach Secured', 'sound' => 'success', 'delay' => 0),
		array('art' => 'live/hill/hill-secured', 'family' => 'Hill Secured', 'n' => 0, 'caption' => 'Hill Secured', 'sound' => 'success', 'delay' => 0),
		array('art' => 'weapons/melee', 'family' => 'Melee', 'n' => 0, 'caption' => 'Melee', 'sound' => 'melee', 'delay' => 0),
		array('art' => 'weapons/demo1', 'family' => 'Demolition', 'n' => 1, 'caption' => 'Demolition', 'sound' => 'demo', 'delay' => 0),
		array('art' => 'weapons/demo2', 'family' => 'Demolition', 'n' => 2, 'caption' => 'Explosion', 'sound' => 'explosioncontra', 'delay' => 0),
		array('art' => 'weapons/tactical-katana', 'family' => 'Tactical Katana', 'n' => 0, 'caption' => 'Tactical Katana', 'sound' => 'melee', 'delay' => 1000),
		array('art' => 'weapons/sniper-rifle', 'family' => 'Sniper Rifle', 'n' => 0, 'caption' => 'Sniper Rifle', 'sound' => 'sniper', 'delay' => 1000),
		array('art' => 'weapons/grenade1', 'family' => 'Grenade', 'n' => 1, 'caption' => 'Pull Grenade Pin', 'sound' => 'grenadepin', 'delay' => 600),
		array('art' => 'weapons/grenade2', 'family' => 'Grenade', 'n' => 2, 'caption' => 'Throw Grenade', 'sound' => 'grenadecontra', 'delay' => 0),
		array('art' => 'weapons/smoke-bomb1', 'family' => 'Smoke Bomb', 'n' => 1, 'caption' => 'Pull Smoke Bomb Pin', 'sound' => 'grenadepin', 'delay' => 600),
		array('art' => 'weapons/smoke-bomb2', 'family' => 'Smoke Bomb', 'n' => 2, 'caption' => 'Smoke Bomb', 'sound' => 'grenadecontra', 'delay' => 0),
		array('art' => 'weapons/machine-gun1', 'family' => 'Machine Gun', 'n' => 1, 'caption' => 'Load Machine Gun', 'sound' => 'loading', 'delay' => 0),
		array('art' => 'weapons/machine-gun2', 'family' => 'Machine Gun', 'n' => 2, 'caption' => 'Machine Gun', 'sound' => 'gunfirecontra', 'delay' => 0),
		array('art' => 'weapons/machine-gun3', 'family' => 'Machine Gun', 'n' => 3, 'caption' => 'Machine Gun', 'sound' => 'gunfirecontra', 'delay' => 0),
		array('art' => 'weapons/flamethrower1', 'family' => 'Flamethrower', 'n' => 1, 'caption' => 'Flamethrower Ignition', 'sound' => 'flamethrower1', 'delay' => 0),
		array('art' => 'weapons/flamethrower2', 'family' => 'Flamethrower', 'n' => 2, 'caption' => 'Flamethrower Spray', 'sound' => 'flamethrower2', 'delay' => 0),
		array('art' => 'weapons/flamethrower3', 'family' => 'Flamethrower', 'n' => 3, 'caption' => 'Flamethrower Flames', 'sound' => 'flamethrower3', 'delay' => 0),
		array('art' => 'weapons/flamethrower4', 'family' => 'Flamethrower', 'n' => 4, 'caption' => 'Flamethrower Fire', 'sound' => 'flamethrower4', 'delay' => 0),
		array('art' => 'weapons/rocket-launcher1', 'family' => 'Rocket Launcher', 'n' => 1, 'caption' => 'Load Rocket Launcher', 'sound' => 'helicopter', 'delay' => 0),
		array('art' => 'weapons/rocket-launcher2', 'family' => 'Rocket Launcher', 'n' => 2, 'caption' => 'Rocket Launcher Gunfire', 'sound' => 'gunfirecontra', 'delay' => 0),
		array('art' => 'weapons/rocket-launcher3', 'family' => 'Rocket Launcher', 'n' => 3, 'caption' => 'Rocket Launcher Gunfire', 'sound' => 'gunfirecontra', 'delay' => 0),
		array('art' => 'weapons/rocket-launcher4', 'family' => 'Rocket Launcher', 'n' => 4, 'caption' => 'Rocket Launcher', 'sound' => 'rocketlaunchercontra', 'delay' => 0),
		array('art' => 'weapons/rocket-launcher5', 'family' => 'Rocket Launcher', 'n' => 5, 'caption' => 'Rocket Launcher Explosion', 'sound' => 'massexplosioncontra', 'delay' => 0),
		array('art' => 'equipment/night-vision-goggles', 'family' => 'Night Vision Goggles', 'n' => 0, 'caption' => 'Night Vision Goggles', 'sound' => '', 'delay' => 0),
		array('art' => 'equipment/radar', 'family' => 'Radar', 'n' => 0, 'caption' => 'Radar', 'sound' => '', 'delay' => 0),
		array('art' => 'equipment/radio', 'family' => 'Radio', 'n' => 0, 'caption' => 'Radio', 'sound' => '', 'delay' => 0),
		array('art' => 'equipment/reinforcements', 'family' => 'Reinforcements', 'n' => 0, 'caption' => 'Reinforcements', 'sound' => '', 'delay' => 0),
		array('art' => 'equipment/airstrike1', 'family' => 'Airstrike', 'n' => 1, 'caption' => 'Pilot', 'sound' => 'loading', 'delay' => 0),
		array('art' => 'equipment/airstrike2', 'family' => 'Airstrike', 'n' => 2, 'caption' => 'Air Strike', 'sound' => 'massexplosioncontra', 'delay' => 2000),
		array('art' => 'equipment/jet-pack1', 'family' => 'Jet Pack', 'n' => 1, 'caption' => 'Jet Pack Takeoff', 'sound' => '', 'delay' => 0),
		array('art' => 'equipment/drop-box', 'family' => 'Drop Box', 'n' => 0, 'caption' => 'Drop Box', 'sound' => '', 'delay' => 0),
		/* THE TITLE CARD, shown the moment Play is pressed and before
		   the first round. Its sound is the one audio2 autoplays as the
		   results pane renders -- loading, or splash, or laughing -- a
		   per-project ladder in dashboard.php that nothing here knew
		   about, which is why this picture had no row while every other
		   one did. Per-project files again, and each is the theme's own. */
		array('art' => 'reset/intro', 'family' => 'Drop Ship Intro', 'n' => 0, 'caption' => '', 'sound' => '', 'delay' => 0,
		      'legacy_by' => array(1 => 'loading', 2 => 'loading', 3 => 'splash', 4 => 'laughing')),
		/* ROUND 0 IS A SLIDE LIKE ANY OTHER, and this is its sound. It
		   used to be modelled twice: once here with no sound, and once
		   as an "Opening" slot with no frame -- two halves of one thing,
		   which is why the opening had nothing to sit under on the art
		   page. The old per-project files (alarm, crowd, flyingcar) were
		   this frame's all along; 'alarm' is Drop Ship's, and every
		   other theme reaches its own through its own wording. */
		array('art' => 'reset/reset', 'family' => 'Drop Ship Initiating', 'n' => 0, 'caption' => 'Drop Ship Initiating', 'sound' => '', 'delay' => 0,
		      /* NO SHARED OLD NAME, because there never was one: this
		         row's fallback is per project and legacy_by carries all
		         of it, so 'sound' above would be a second answer to a
		         question that already has one.

		         EACH THEME'S OWN OLD FILE, not a borrow. Every other row
		         falls back to Drop Ship's name and only Drop Ship may use
		         it; these three were per-project from the start -- crowd
		         was only ever Filthy Mermaid's and flyingcar only ever
		         Oculus Lounge's -- so each theme keeps the opening it
		         already had until the file is renamed onto its wording. */
		      'legacy_by' => array(1 => 'alarm', 3 => 'crowd', 4 => 'flyingcar')),
		array('art' => 'drop/drop', 'family' => 'Drop Ship', 'n' => 0, 'caption' => 'Drop Ship', 'sound' => 'dropshipcontra', 'delay' => 0),
		array('art' => 'drop/team', 'family' => 'Drop Ship Landed', 'n' => 0, 'caption' => 'Drop Ship Landed', 'sound' => 'gunfirecontra', 'delay' => 0),
	);
}

/**
 * The catch-alls, for art that is not a captioned moment.
 *
 * A surviving round draws one of seven beach frames at random and is
 * captioned "Survived" in plain text, not through the wording -- there is
 * no per-frame moment to name, and the old chain treated every
 * unmatched round the same way. Matched by the START of the art path,
 * after the exact table has had its chance.
 */
function dropshipAudioFallbacks() {
	return array(
		/*
		 * THE EXO ROTATION, BEFORE THE PLAIN ONE. A player in the suit
		 * sees alternate frames and the old chain played exo.mp3 at
		 * 300ms over them -- but their paths carry a variable frame
		 * number, so the scrape that built the exact table never saw
		 * them and they fell through to the surviving-round gunshot
		 * instead. Prefixes are walked in order, and 'live/' would
		 * swallow these if it came first.
		 */
		array('art' => 'live/beach/exo/',  'family' => 'Exo Suit', 'n' => 0, 'caption' => 'Exo Suit',
		      'sound' => 'exo', 'delay' => 300),
		array('art' => 'live/hill/exo/',   'family' => 'Exo Suit', 'n' => 0, 'caption' => 'Exo Suit',
		      'sound' => 'exo', 'delay' => 300),
		array('art' => 'live/bunker/exo/', 'family' => 'Exo Suit', 'n' => 0, 'caption' => 'Exo Suit',
		      'sound' => 'exo', 'delay' => 300),
		array('art' => 'live/',  'family' => 'Survived',   'n' => 0, 'caption' => 'Survived',
		      'sound' => 'enemyguncontra', 'delay' => 0),
		array('art' => '1up/',   'family' => 'Extra Life', 'n' => 0, 'caption' => 'Extra Life',
		      'sound' => 'extralife', 'delay' => 0),
	);
}

/** The file a word asks for: the same slug the icons use. */
function dropshipAudioStem($word) {
	return dropshipThemeSlug($word);
}

/** Is this project Drop Ship itself? */
function dropshipAudioIsBase($conn, $project_id) {
	if (!function_exists('dropshipThemeConfig')) return (int)$project_id === 1;
	$cfg = dropshipThemeConfig($conn, $project_id);
	$slug = ($cfg && !empty($cfg['slug'])) ? dropshipThemeSlug($cfg['slug']) : '';
	return $slug === '' ? ((int)$project_id === 1) : ($slug === 'drop-ship');
}

/**
 * One frame resolved through a theme.
 *
 * THE FILENAME IS THE WORD PLUS THE FRAME NUMBER, which is the
 * convention the art already uses: demo1 is the activation and demo2 the
 * explosion, so Drop Ship asks for demolition1.mp3 and demolition2.mp3
 * and Oculus Lounge, which calls the thing a Paddle, asks for
 * paddle1.mp3 and paddle2.mp3. A single-frame thing takes no number,
 * exactly as melee.gif does.
 *
 * CAPTIONS CANNOT CARRY THIS. machine-gun2 and machine-gun3 share the
 * caption "Machine Gun", as do rocket-launcher2 and 3 -- so the frame a
 * round is showing is a fact about its IMAGE, not about its words.
 */
function dropshipAudioSlot($conn, $project_id, $event) {
	$said = $event['family'];
	if ($said !== '' && function_exists('dropshipThemeText')) {
		$t = dropshipThemeText($conn, $project_id, $event['family']);
		if ($t !== null && $t !== '') $said = $t;
	}
	$n    = (int)$event['n'];
	$stem = dropshipAudioStem($said);
	if ($stem !== '' && $n > 0) $stem .= $n;
	$dir  = rtrim(__DIR__, '/') . '/sounds/';
	/*
	 * ONLY DROP SHIP FALLS BACK TO DROP SHIP'S SOUNDS.
	 *
	 * Reported twice: "why are you playing drop ship sounds on filthy
	 * mermaid", and then the gunshot that plays on EVERY surviving round
	 * turning up on themes that want nothing of the sort. Both are one
	 * rule -- a theme is not Drop Ship and must never be handed its
	 * audio. The old project_id guards were a partial, hand-maintained
	 * version of exactly this.
	 *
	 * Drop Ship itself still falls back to the old sound-named files
	 * (grenadecontra, enemyguncontra) so nothing of its own goes quiet
	 * before they are renamed onto the convention.
	 */
	$base = dropshipAudioIsBase($conn, $project_id);
	$has  = $stem !== '' && is_file($dir . $stem . '.mp3');
	/* A row may name a file PER PROJECT -- see reset/reset. Where it
	   does, that file is the theme's own and falling back to it borrows
	   nothing, so the base-only rule does not apply. */
	$legname = $event['sound'];
	$legmine = false;
	if (!empty($event['legacy_by']) && isset($event['legacy_by'][(int)$project_id])) {
		$legname = $event['legacy_by'][(int)$project_id];
		$legmine = true;
	}
	$leg  = $legname !== '' && is_file($dir . $legname . '.mp3');
	return array(
		'art'        => $event['art'],
		'term'       => $event['family'],
		'caption'    => $event['caption'],
		'said'       => $said,
		'n'          => $n,
		'delay'      => (int)$event['delay'],
		'stem'       => $stem,
		'rel'        => $stem === '' ? '' : 'sounds/' . $stem . '.mp3',
		'exists'     => $has,
		'legacy'     => $legname,
		'legacy_has' => $leg,
		/* Is that old file this theme's OWN, rather than Drop Ship's? Only
		   round 0 is, and only because its files were per-project from the
		   start. Callers that refuse to borrow Drop Ship's audio need to
		   know the difference. */
		'legacy_mine' => $legmine,
		'is_base'    => $base,
		/* What the run will actually play, by the rule above. */
		'plays'      => $has ? 'sounds/' . $stem . '.mp3'
		              : ((($base || $legmine) && $leg) ? 'sounds/' . $legname . '.mp3' : ''),
	);
}

/** Every frame resolved through a theme, exact table then catch-alls. */
function dropshipAudioSlots($conn, $project_id) {
	$out = array();
	foreach (dropshipAudioEvents() as $e)    $out[] = dropshipAudioSlot($conn, $project_id, $e);
	foreach (dropshipAudioFallbacks() as $e) $out[] = dropshipAudioSlot($conn, $project_id, $e);
	return $out;
}

/**
 * What the browser is handed: an exact map from art path to sound, plus
 * the ordered catch-alls and the opening.
 *
 * NO SUBSTRING MATCHING LEFT. The caption chain had to be kept in an
 * order it never declared, because "Smoke Bomb" is inside "Pull Smoke
 * Bomb Pin"; a frame is an exact key and cannot shadow another one.
 */
function dropshipAudioPayload($conn, $project_id) {
	$exact = array();
	foreach (dropshipAudioEvents() as $e) {
		$s = dropshipAudioSlot($conn, $project_id, $e);
		if ($s['plays'] === '') continue;
		$exact[$e['art']] = array('f' => dropshipAudioUrl($s['plays']), 'd' => $s['delay']);
	}
	$any = array();
	foreach (dropshipAudioFallbacks() as $e) {
		$s = dropshipAudioSlot($conn, $project_id, $e);
		if ($s['plays'] === '') continue;
		$any[] = array('p' => $e['art'], 'f' => dropshipAudioUrl($s['plays']), 'd' => $s['delay']);
	}
	return array('frames' => $exact, 'any' => $any);
}

/**
 * One slot, found by the frame it belongs to.
 *
 * BY THE ART PATH, because that is the only thing that identifies a row.
 * The admin form posted the FAMILY and the lookup compared it against
 * $e['term'] -- a key events do not have, they have 'family' -- so it
 * matched nothing and every Set quietly resolved to null. Even spelled
 * right the family is ambiguous: Flamethrower is four rows and
 * Demolition is two, and they must not all answer to one name.
 *
 * '' is the opening, which is the one slot with no frame.
 */
function dropshipAudioSlotByArt($conn, $project_id, $art) {
	$art = (string)$art;
	/* 'Opening' was a slot of its own once; anything still posting it
	   means a page cached before that was folded into round 0. */
	if ($art === '' || $art === 'Opening') return null;
	foreach (dropshipAudioEvents() as $e) {
		if ($e['art'] === $art) return dropshipAudioSlot($conn, $project_id, $e);
	}
	foreach (dropshipAudioFallbacks() as $e) {
		if ($e['art'] === $art) return dropshipAudioSlot($conn, $project_id, $e);
	}
	return null;
}

/**
 * The sounds of a theme, bucketed under the art rows they belong to.
 *
 * BY THE FRAME, NOT BY THE THING'S NAME. The first version matched a
 * sound's vocabulary family against the LABELLED art rows -- and the
 * drop rounds, the deaths and the beach frames all render as bands with
 * no label, so nothing could ever attach to them. Filthy Mermaid's
 * crowd on round 0 and its door on round 1 were both sitting in the
 * section at the bottom of the page instead of under the pictures they
 * play against, which is exactly the thing being asked for: it is hard
 * to tell where audio goes if it is not paired with the imagery.
 *
 * A sound belongs to a frame and the frame is in a row, so the row is
 * found by the art path. Exact, and it works for an unlabelled band.
 *
 * IN HERE RATHER THAN IN THE PAGE so it can be driven. The same lookup
 * written out twice -- once in the page, once in a harness asserting
 * the page -- is a test that passes while the page is broken, which is
 * how the Set button shipped dead.
 *
 * Returns array('rows' => "group|index" => slots, 'loose' => slots).
 */
function dropshipAudioPlanRows($conn, $project_id, $plan) {
	$where = array();
	$n = 0;
	foreach ($plan as $gname => $g) {
		foreach ($g['rows'] as $ri => $r) {
			foreach ($r['slots'] as $sl) {
				/* Only round art is a frame. An icon is one shared file
				   for every theme and a background is page furniture;
				   neither is a moment with a sound -- and neither path
				   begins with images/, which the slice below assumes. */
				if (!isset($sl['kind']) || $sl['kind'] !== 'art') continue;
				if (strpos($sl['rel'], 'images/') !== 0) continue;
				/* images/weapons/demo1.png -> weapons/demo1, which is how
				   the registry names a frame. */
				$k = preg_replace('/\.[a-z0-9]+$/', '', substr($sl['rel'], 7));
				/* The slot's position too, so a row's sounds come out in
				   the order its pictures do -- four cards and three
				   sounds underneath in some other order is a puzzle. */
				$where[$k] = array($gname . '|' . $ri, $n++);
			}
		}
	}
	$out = array('rows' => array(), 'loose' => array());
	foreach (dropshipAudioSlots($conn, $project_id) as $as) {
		if ($as['art'] !== '' && isset($where[$as['art']])) {
			$as['_at'] = $where[$as['art']][1];
			$out['rows'][$where[$as['art']][0]][] = $as;
		} else {
			/* The catch-alls span whole stages rather than sitting on one
			   frame, so they have no row to live under. */
			$out['loose'][] = $as;
		}
	}
	foreach ($out['rows'] as $k => $rows) {
		usort($rows, function ($a, $b) { return $a['_at'] - $b['_at']; });
		$out['rows'][$k] = $rows;
	}
	return $out;
}

/** Every mp3 in the shared folder, for the "use one already there" picker. */
function dropshipAudioFiles() {
	$out = array();
	foreach ((array)@glob(rtrim(__DIR__, '/') . '/sounds/*.mp3') as $f) $out[] = basename($f);
	sort($out);
	return $out;
}

/**
 * Who else is asking for this file -- the whole reason a warning exists.
 *
 * sounds/ is ONE FOLDER for every theme, so writing melee.mp3 because
 * Drop Ship's melee needs replacing also replaces it for every other
 * theme whose word slugs to the same name, and for every other EVENT that
 * falls back to it. The icon rename already refuses a move that would
 * take a file another theme still wants; audio is an upload rather than a
 * move, so it cannot refuse -- it warns, and the admin decides.
 *
 * Returns a list of "Theme: Term" strings, excluding the one asking.
 */
function dropshipAudioClaims($conn, $stem, $except_pid = 0, $except_art = '') {
	$out = array();
	if ($stem === '' || !function_exists('dropshipThemeAll')) return $out;
	foreach (dropshipThemeAll($conn) as $t) {
		$pid = (int)$t['project_id'];
		foreach (dropshipAudioSlots($conn, $pid) as $s) {
			if ($s['stem'] !== $stem) continue;
			/* BY ART, not by family: two frames of one thing are two
			   different files, and excluding by family would hide the
			   other frame from its own warning. */
			if ($pid === (int)$except_pid && $s['art'] === $except_art) continue;
			/* name where the row has one, slug otherwise -- this string
			   is read by a person deciding whether to overwrite, so it
			   must never come out blank. */
			$who = !empty($t['name']) ? $t['name'] : (!empty($t['slug']) ? $t['slug'] : ('project ' . $pid));
			$out[] = $who . ': ' . $s['said'];
		}
	}
	return array_values(array_unique($out));
}

/**
 * A sound path with the file's own mtime on it.
 *
 * NOT rand(). The server sends Cache-Control: max-age=604800 and a
 * random buster collides against that -- which is exactly how
 * dropship.js came to serve week-stale copies of itself.
 */
function dropshipAudioUrl($rel) {
	if ($rel === '') return '';
	$v = @filemtime(rtrim(__DIR__, '/') . '/' . $rel);
	return $v ? $rel . '?v=' . $v : $rel;
}

/*
 * dropshipAudioOpenSlot() lived here. The sound a run opens on is round
 * 0's, and round 0 is reset.gif -- a frame, with a caption, like every
 * other. Modelling it separately gave it no picture to sit under on the
 * art page and forced a <slug>-opening.mp3 name that had to dodge a
 * collision with the round 1 frame. It is just the reset/reset row now.
 */


/*
 * dropshipAudioFamily() lived here. It matched a sound's vocabulary
 * family against the LABELLED art rows, which could never attach a
 * sound to a band of art that has no label -- the drop rounds, the
 * deaths, the beach frames. The art page buckets by the frame's own
 * path now, which is exact and works for an unlabelled band, and
 * nothing else ever called this.
 */

