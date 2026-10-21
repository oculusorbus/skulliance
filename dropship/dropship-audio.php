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
 * The sound events, in the order the round is tested against them.
 *
 * ORDER IS PART OF THE RULE and always was. The match is a SUBSTRING of
 * the round's caption, so "Smoke Bomb" would also match "Pull Smoke Bomb
 * Pin" -- the pin has to be tested first or it never fires. Same for
 * "Load Machine Gun" before "Machine Gun", and every stage of the rocket
 * launcher. The old chain encoded that in the order of its branches; this
 * encodes it in the order of this array, and the first match wins exactly
 * as it did.
 *
 *   term   the Drop Ship word. The caption is matched against whatever
 *          THIS theme calls it, and the filename is made from that too.
 *   sound  what Drop Ship's own file is called TODAY. Only used to say
 *          what a slot is currently serving while the files are still on
 *          their old names; the name a theme asks for is derived.
 *   delay  milliseconds to wait before playing, which is about when the
 *          sound lands against the animation and is therefore a property
 *          of the EVENT, not of the theme. Stays here.
 */
function dropshipAudioEvents() {
	return array(
		/*
		 * ROUND 0, 1, 2 ARE THREE FRAMES WITH THREE CAPTIONS, and the
		 * first pass hung round 1's sound on round 0's caption. The
		 * markup is explicit: round 0 is reset.gif captioned "Drop Ship
		 * Initiating" and had NO sound; round 1 is drop.gif captioned
		 * "Drop Ship" and is the one that played dropshipcontra; round 2
		 * is team.gif captioned "Drop Ship Landed".
		 *
		 * "Drop Ship" IS A SUBSTRING OF THE OTHER TWO, so it has to be
		 * tested after them -- the ordering check in the harness derives
		 * exactly this and would have caught it the other way round.
		 */
		array('term' => 'Drop Ship Initiating',     'sound' => '',                     'delay' => 0),
		array('term' => 'Drop Ship Landed',         'sound' => 'gunfirecontra',        'delay' => 0),
		array('term' => 'Drop Ship',                'sound' => 'dropshipcontra',       'delay' => 0),
		array('term' => 'Melee',                    'sound' => 'melee',                'delay' => 0),
		array('term' => 'Tactical Katana',          'sound' => 'melee',                'delay' => 1000),
		array('term' => 'Extra Life',               'sound' => 'extralife',            'delay' => 0),
		array('term' => 'Pull Smoke Bomb Pin',      'sound' => 'grenadepin',           'delay' => 600),
		array('term' => 'Smoke Bomb',               'sound' => 'grenadecontra',        'delay' => 0),
		/* "Pull Grenade PIN", which is the word the wording table holds and
		   the caption actually says. The old chain matched on the shorter
		   "Pull Grenade" -- harmless there, because a substring of the
		   caption was all it needed, but fatal here: the lookup is by the
		   whole term, so the short form resolves to nothing and Oculus
		   Lounge never reaches its own Insert Anal Beads sound. */
		array('term' => 'Pull Grenade Pin',         'sound' => 'grenadepin',           'delay' => 600),
		array('term' => 'Throw Grenade',            'sound' => 'grenadecontra',        'delay' => 0),
		/*
		 * EVERY FRAME IS A MOMENT AND GETS A SLOT, even the ones that
		 * have never had a sound. demo1 is the activation and demo2 the
		 * explosion, two frames with two captions and two sounds; these
		 * five are frames with captions and no sound at all, which is
		 * not the same as frames that should not have one. Listed with
		 * an empty default so they are silent exactly as they are today
		 * and an admin can give them something.
		 */
		array('term' => 'Radar',                    'sound' => '',                     'delay' => 0),
		array('term' => 'Night Vision Goggles',     'sound' => '',                     'delay' => 0),
		array('term' => 'Radio',                    'sound' => '',                     'delay' => 0),
		array('term' => 'Reinforcements',           'sound' => '',                     'delay' => 0),
		array('term' => 'Jet Pack Takeoff',         'sound' => '',                     'delay' => 0),
		array('term' => 'Drop Box',                 'sound' => '',                     'delay' => 0),
		array('term' => 'Load Machine Gun',         'sound' => 'loading',              'delay' => 0),
		array('term' => 'Machine Gun',              'sound' => 'gunfirecontra',        'delay' => 0),
		array('term' => 'Flamethrower Ignition',    'sound' => 'flamethrower1',        'delay' => 0),
		array('term' => 'Flamethrower Spray',       'sound' => 'flamethrower2',        'delay' => 0),
		array('term' => 'Flamethrower Flames',      'sound' => 'flamethrower3',        'delay' => 0),
		array('term' => 'Flamethrower Fire',        'sound' => 'flamethrower4',        'delay' => 0),
		array('term' => 'Load Rocket Launcher',     'sound' => 'helicopter',           'delay' => 0),
		array('term' => 'Rocket Launcher Gunfire',  'sound' => 'gunfirecontra',        'delay' => 0),
		array('term' => 'Rocket Launcher Explosion','sound' => 'massexplosioncontra',  'delay' => 0),
		array('term' => 'Rocket Launcher',          'sound' => 'rocketlaunchercontra', 'delay' => 0),
		array('term' => 'Demolition',               'sound' => 'demo',                 'delay' => 0),
		array('term' => 'Explosion',                'sound' => 'explosioncontra',      'delay' => 0),
		array('term' => 'Sniper Rifle',             'sound' => 'sniper',               'delay' => 1000),
		array('term' => 'Pilot',                    'sound' => 'loading',              'delay' => 0),
		array('term' => 'Air Strike',               'sound' => 'massexplosioncontra',  'delay' => 2000),
		array('term' => 'Exo Suit',                 'sound' => 'exo',                  'delay' => 300),
		array('term' => 'Secured',                  'sound' => 'success',              'delay' => 0),
		/* LAST, and it is the catch-all: every round that matched nothing
		   above is simply one the player survived. Tested last for the
		   same reason the pin is tested first. */
		array('term' => 'Survived',                 'sound' => 'enemyguncontra',       'delay' => 0),
	);
}

/** The file a word asks for: the same slug the icons use, plus .mp3. */
function dropshipAudioStem($word) {
	$slug = dropshipThemeSlug($word);
	return $slug === '' ? '' : $slug;
}

/**
 * One event resolved through a theme.
 *
 * Returns term, said (what this theme calls it), stem, rel, exists, and
 * the Drop Ship fallback -- a theme with no file of its own falls back to
 * Drop Ship's, which is what keeps a half-dressed theme audible instead
 * of silent. Both are '' when the word cannot be a filename at all.
 */
function dropshipAudioSlot($conn, $project_id, $event) {
	$said = $event['term'];
	if (function_exists('dropshipThemeText')) {
		$t = dropshipThemeText($conn, $project_id, $event['term']);
		if ($t !== null && $t !== '') $said = $t;
	}
	$stem = dropshipAudioStem($said);
	$base = dropshipAudioStem($event['term']);
	$dir  = rtrim(__DIR__, '/') . '/sounds/';
	return array(
		'term'      => $event['term'],
		'said'      => $said,
		'delay'     => (int)$event['delay'],
		'stem'      => $stem,
		'rel'       => $stem === '' ? '' : 'sounds/' . $stem . '.mp3',
		'exists'    => $stem !== '' && is_file($dir . $stem . '.mp3'),
		'base'      => $base,
		'base_rel'  => $base === '' ? '' : 'sounds/' . $base . '.mp3',
		'base_has'  => $base !== '' && is_file($dir . $base . '.mp3'),
		/* What the file is called on the server TODAY, before anything is
		   renamed onto the convention. Shown so the page can say "this is
		   what you are hearing" rather than only "this is missing". */
		'legacy'    => $event['sound'],
		'legacy_has'=> $event['sound'] !== '' && is_file($dir . $event['sound'] . '.mp3'),
	);
}

/** Every event resolved through a theme, in match order. */
function dropshipAudioSlots($conn, $project_id) {
	$out = array();
	foreach (dropshipAudioEvents() as $e) $out[] = dropshipAudioSlot($conn, $project_id, $e);
	return $out;
}

/**
 * What the browser is handed: match text, file and delay, in order.
 *
 * RESOLVED ON THE SERVER, because the wording lives there and the
 * fallback is a question about the filesystem. The browser's job shrinks
 * to walking a list and playing the first row whose text is in the
 * caption, which is what the thirty-four branches were doing by hand.
 *
 * A row with no file is LEFT OUT rather than sent with an empty src --
 * silence is a real answer here (it is what the project_id guards were
 * expressing) and an <audio> pointed at nothing logs an error per round.
 */
function dropshipAudioTable($conn, $project_id) {
	$rows = array();
	foreach (dropshipAudioSlots($conn, $project_id) as $s) {
		/*
		 * THE THEME'S OWN FILE, THEN DROP SHIP'S, THEN THE OLD NAME.
		 *
		 * The third is what stops this change being a silent game. Drop
		 * Ship's sounds are named after the SOUND -- grenadecontra,
		 * massexplosioncontra, enemyguncontra -- not after the words, so
		 * on the day this ships almost nothing is sitting at the name the
		 * convention asks for. Keeping the old name last means the game
		 * sounds exactly as it does today and each rename takes over as
		 * it happens, rather than everything going quiet until all of
		 * them are done.
		 */
		/*
		 * AND THE FALLBACK ONLY APPLIES TO A WORD THE THEME HAS NOT
		 * RENAMED.
		 *
		 * Reported looking at Filthy Mermaid: "Demolition -> Paddle,
		 * playing demo.mp3", "Flamethrower Flames -> Candle Flames,
		 * playing flamethrower3.mp3". Both true and both wrong -- a
		 * theme that has given the thing its own name has said this is
		 * not Drop Ship's object, so Drop Ship's sound is the one answer
		 * it must not get. It is the same rule the Discord image and the
		 * bunker art already follow: no sound beats another game's.
		 *
		 * It also restores a silence that the project_id guards used to
		 * provide. Filthy Mermaid was quiet on the whole flamethrower,
		 * the machine gun and the rocket launcher because a dozen
		 * branches were wrapped in `project_id != 3 && != 4`; dropping
		 * those made it play Drop Ship's, which is what this is.
		 *
		 * A theme that has NOT renamed the thing is a different case and
		 * still falls back: it is asking for the same word, so it is
		 * asking for the same sound.
		 */
		$renamed = ($s['said'] !== $s['term']);
		$file = $s['exists'] ? $s['rel']
		      : ($renamed ? ''
		      : ($s['base_has']  ? $s['base_rel']
		      : ($s['legacy_has'] ? 'sounds/' . $s['legacy'] . '.mp3' : '')));
		if ($file === '') continue;
		/* And never a row with nothing to match on: strpos against ''
		   succeeds at position 0, so such a row would answer every round
		   before anything else got a look. */
		if ($s['said'] === '') continue;
		$rows[] = array('m' => $s['said'], 'f' => dropshipAudioUrl($file), 'd' => $s['delay']);
	}
	return $rows;
}

/** Everything the browser needs: the ordered table plus the opening sound. */
function dropshipAudioPayload($conn, $project_id) {
	$open = dropshipAudioOpenSlot($conn, $project_id);
	return array(
		'rows' => dropshipAudioTable($conn, $project_id),
		'open' => $open['exists'] ? dropshipAudioUrl($open['rel'])
		        : ($open['legacy_has'] ? dropshipAudioUrl('sounds/' . $open['legacy'] . '.mp3') : ''),
	);
}

/**
 * The sound a theme opens on, which is the one with no caption to derive
 * from -- so it derives from the theme's own name instead.
 *
 * It was a project_id ladder in dropship.js: 1 alarm, 3 crowd, 4
 * flyingcar, and Dread City silent because nobody added a branch for it.
 * Those filenames are named after the SOUND; everything else here is
 * named after the word, so this asks for sounds/<theme slug>.mp3 and a
 * new theme gets an opening by putting a file there.
 */
function dropshipAudioOpenSlot($conn, $project_id) {
	$slug = '';
	if (function_exists('dropshipThemeConfig')) {
		$cfg = dropshipThemeConfig($conn, $project_id);
		if ($cfg && !empty($cfg['slug'])) $slug = dropshipThemeSlug($cfg['slug']);
	}
	$legacy = array(1 => 'alarm', 3 => 'crowd', 4 => 'flyingcar');
	$dir = rtrim(__DIR__, '/') . '/sounds/';
	/*
	 * "-opening", NOT THE BARE SLUG. Filthy Mermaid's word for the round
	 * 1 caption "Drop Ship" is "Filthy Mermaid", so that event already
	 * asks for filthy-mermaid.mp3 -- two different sounds would have
	 * been fighting over one filename.
	 */
	$stem = $slug === '' ? '' : $slug . '-opening';
	return array(
		'term'       => 'Opening',
		'said'       => $slug === '' ? '' : str_replace('-', ' ', ucwords($slug, '-')),
		'delay'      => 0,
		'stem'       => $stem,
		'rel'        => $stem === '' ? '' : 'sounds/' . $stem . '.mp3',
		'exists'     => $stem !== '' && is_file($dir . $stem . '.mp3'),
		'base'       => '',
		'base_rel'   => '',
		'base_has'   => false,
		'legacy'     => isset($legacy[(int)$project_id]) ? $legacy[(int)$project_id] : '',
		'legacy_has' => isset($legacy[(int)$project_id]) && is_file($dir . $legacy[(int)$project_id] . '.mp3'),
	);
}

/**
 * A sound path with the file's own mtime on it.
 *
 * NOT rand(). The server sends Cache-Control: max-age=604800 and a random
 * buster collides against that -- which is exactly how dropship.js came
 * to serve week-stale copies of itself. loadAudio() still uses rand for
 * the 8-bit tracks; these are the paths the table carries.
 */
function dropshipAudioUrl($rel) {
	if ($rel === '') return '';
	$v = @filemtime(rtrim(__DIR__, '/') . '/' . $rel);
	return $v ? $rel . '?v=' . $v : $rel;
}

/**
 * Which thing on the art page a sound event belongs beside.
 *
 * PAIRED LIKE THE ICONS, for the same reason: a reskin is decided one
 * object at a time -- "what is our rocket launcher" -- and the answer is
 * five frames, an icon and four sounds. Those sat on three different
 * parts of the screen, and the sounds were not on the screen at all.
 *
 * Most events ARE a thing: 22 of the 28 slug straight onto a vocabulary
 * family. The rest are a stage of one ("Pull Grenade", "Flamethrower
 * Flames") or belong to no object at all ("Secured", "Survived"), so the
 * term is walked down word by word from both ends and the first family
 * that answers wins. Nothing matching is not a failure; it gets its own
 * section, the way icons with no round art do.
 *
 * Returns the family label, or '' .
 */
function dropshipAudioFamily($term, $prefer = null) {
	require_once __DIR__ . '/dropship-art.php';
	$labels = dropshipArtThingLabels();
	/*
	 * PREFER A THING THAT IS ACTUALLY ON THE PAGE. Half of these terms
	 * are their own vocabulary family -- "Pull Smoke Bomb Pin" is a
	 * family, and so is "Smoke Bomb" -- and the longest match wins, so
	 * without this the pin pairs with a family that has no art row and
	 * falls through to the leftovers instead of sitting under the smoke
	 * bomb it belongs to. $prefer is the set of rows the page is about
	 * to render.
	 */
	$ok = function ($label) use ($prefer) {
		return $prefer === null || in_array($label, $prefer, true);
	};
	$words = preg_split('/\s+/', trim($term));
	$n = count($words);
	for ($pass = 0; $pass < 2; $pass++) {
		for ($len = $n; $len >= 1; $len--) {
			for ($i = 0; $i + $len <= $n; $i++) {
				$try = dropshipThemeSlug(implode(' ', array_slice($words, $i, $len)));
				if ($try === '' || !isset($labels[$try])) continue;
				if ($pass === 0 && !$ok($labels[$try])) continue;
				return $labels[$try];
			}
		}
		if ($prefer === null) break;   /* nothing to loosen to */
	}
	return '';
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
function dropshipAudioClaims($conn, $stem, $except_pid = 0, $except_term = '') {
	$out = array();
	if ($stem === '' || !function_exists('dropshipThemeAll')) return $out;
	foreach (dropshipThemeAll($conn) as $t) {
		$pid = (int)$t['project_id'];
		foreach (dropshipAudioSlots($conn, $pid) as $s) {
			if ($s['stem'] !== $stem) continue;
			if ($pid === (int)$except_pid && $s['term'] === $except_term) continue;
			/* name where the row has one, slug otherwise -- this string
			   is read by a person deciding whether to overwrite, so it
			   must never come out blank. */
			$who = !empty($t['name']) ? $t['name'] : (!empty($t['slug']) ? $t['slug'] : ('project ' . $pid));
			$out[] = $who . ': ' . $s['said'];
		}
	}
	return array_values(array_unique($out));
}
