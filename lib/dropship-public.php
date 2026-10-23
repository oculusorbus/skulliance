<?php
/**
 * lib/dropship-public.php -- the five themes, for pages with no session.
 *
 * WHY A SECOND DOOR TO THAT DATABASE. The public pages here are reached by
 * strangers and by X's crawler, and the two existing ways in both carry
 * baggage they must not: dropship/db.php IS the login gate, and the
 * platform's own db.php is the whole application. A marketing page and a
 * 1200x630 JPEG need one query between them -- the theme list -- and
 * nothing else should be able to fail on the way.
 *
 * CREDENTIALS ARE READ INSIDE THE FUNCTION, which is not a style choice.
 * Both credentials files declare $servername, $username, $password and
 * $dbname at the top level; reading one at file scope silently overwrites
 * whatever the other put there, and the next thing to build a connection
 * points at the wrong database. dropship/dropship-skulliance-db.php exists
 * because of exactly that bug. Here they are function locals and die on
 * return.
 *
 * FAILS QUIET AND EMPTY. A visitor must get the page even when the database
 * is unreachable, so every failure returns no themes rather than an error,
 * and callers render the generic version. Nothing on these pages is worth
 * a 500 to a stranger.
 */

/** A connection to the Drop Ship database, or null. */
function dropshipPublicDb() {
	static $c = null;
	if ($c !== null) return $c === false ? null : $c;
	$cred = __DIR__ . '/../dropship/credentials/db_credentials.php';
	if (!is_file($cred)) { $c = false; return null; }
	include $cred;                       // function scope -- see above
	if (!isset($servername, $username, $password, $dbname)) { $c = false; return null; }
	$m = @new mysqli($servername, $username, $password, $dbname);
	if ($m->connect_error) {
		$c = false;
		error_log('dropshipPublicDb: ' . $m->connect_error);
		return null;
	}
	$c = $m;
	return $c;
}

/**
 * Active themes as slug => array('name', 'project_id'), in the game picker's
 * own order so the public page lists them the way players see them.
 *
 * ACTIVE ONLY, which is the same gate getProjects() applies -- a theme the
 * admin has switched off should not be advertised, and more to the point it
 * cannot be entered, so a card for it is a card for a locked door.
 */
function dropshipPublicThemes() {
	static $out = null;
	if ($out !== null) return $out;
	$out = array();
	$db = dropshipPublicDb();
	if (!$db) return $out;

	$r = @$db->query("SELECT t.slug, t.project_id, p.name, p.policy_id
	                    FROM themes t INNER JOIN projects p ON p.id = t.project_id
	                   WHERE t.active = 1 AND t.slug <> ''");
	if (!$r) return $out;
	$rows = array();
	while ($row = $r->fetch_assoc()) {
		$rows[(int)$row['project_id']] = array(
			'slug'       => (string)$row['slug'],
			'name'       => (string)$row['name'],
			'project_id' => (int)$row['project_id'],
			'policy'     => (string)$row['policy_id'],
		);
	}
	/* The picker's order, hardcoded there as dropshipGameOrder(). Repeated
	   as data rather than required, because that file pulls in the whole
	   theme layer and this page is meant to load with none of it. Anything
	   not on the list follows, so a new theme appears rather than vanishes. */
	$order = array(1, 2, 3, 5, 4);
	foreach ($order as $pid) {
		if (isset($rows[$pid])) { $out[$rows[$pid]['slug']] = $rows[$pid]; unset($rows[$pid]); }
	}
	foreach ($rows as $row) $out[$row['slug']] = $row;
	return $out;
}

/** One theme by slug, or null. The slug is never trusted into a path. */
function dropshipPublicTheme($slug) {
	$slug = strtolower(trim((string)$slug));
	if ($slug === '' || !preg_match('/^[a-z0-9-]{1,48}$/', $slug)) return null;
	$all = dropshipPublicThemes();
	return isset($all[$slug]) ? $all[$slug] : null;
}

/**
 * What a theme's post is allowed to claim, because the two things the
 * pitch wants to say are not true of every theme.
 *
 * THIS IS WHY. The pitch said "Free to play, and your first squad is free
 * too" on all five, and both halves were wrong somewhere:
 *
 *   THE FREE SQUAD IS DROP SHIP ONLY. addDefaultSoldiers() is gated on
 *   project_id 1 in dashboard.php, so the four grunts do not exist in
 *   Dread City, Filthy Mermaid, DHC2 Fighters or Oculus Lounge. The
 *   docblock in dropship-grunts.php opens "Every Drop Ship player is
 *   given four free soldiers" and says DROP SHIP ONLY twelve lines later;
 *   reading the first line and stopping is how this shipped.
 *
 *   OCULUS LOUNGE IS NOT FREE TO PLAY. It needs the VIP role or a
 *   temporary pass bought with DISCOIN -- the gate is project_id 4 in
 *   dashboard.php, next to the Play button.
 *
 * A post is a public claim to strangers. One that overstates what they
 * get is worse than no post, because they turn up and find out.
 *
 * PROJECT IDS, WHICH IS AN ANTI-PATTERN THIS CODEBASE KEEPS REMOVING --
 * but both gates it mirrors are themselves written that way, and a second
 * opinion about who gets grunts is worse than a hardcoded id in one
 * place. When either gate becomes a themes column, this reads it instead.
 */
function dropshipThemeClaims($project_id) {
	$pid = (int)$project_id;
	return array(
		'free_squad' => ($pid === 1),   // addDefaultSoldiers(), dashboard.php
		'open'       => ($pid !== 4),   // the VIP gate, dashboard.php
	);
}

/**
 * A connection to the SKULLIANCE database, or null.
 *
 * The art is not in Drop Ship's database. Soldiers there carry a bare
 * CID; the file on disk is keyed by md5 of that CID under a project and
 * collection id that only Skulliance's `collections` table knows. One
 * small query crosses that gap.
 *
 * Credentials read inside the function, for the reason stated at the top
 * of this file: both files declare the same four variable names, and
 * reading one at file scope silently repoints the other connection.
 */
function dropshipPublicSkullianceDb() {
	static $c = null;
	if ($c !== null) return $c === false ? null : $c;
	$cred = __DIR__ . '/../credentials/db_credentials.php';
	if (!is_file($cred)) { $c = false; return null; }
	include $cred;                       // function scope
	if (!isset($servername, $username, $password, $dbname)) { $c = false; return null; }
	$m = @new mysqli($servername, $username, $password, $dbname);
	if ($m->connect_error) { $c = false; error_log('dropshipPublicSkullianceDb: ' . $m->connect_error); return null; }
	$c = $m;
	return $c;
}

/** The four Grunts, and what each one carries. */
function dropshipPublicGrunts() {
	/*
	 * NOT A DESCRIPTION OF THE LOADOUT -- the real one, from
	 * dropshipGruntKits() in dropship/dropship-grunts.php. The four are
	 * built to be exactly one Super Soldier Squad between them (Heavy
	 * through Base, three gear types and a deliberate gap), which is the
	 * single most persuasive thing this page can say, so it must not
	 * drift from what the game actually hands out.
	 */
	return array(
		array('asset' => 'GRUNT003', 'name' => 'Hagar',     'armor' => 'Heavy',  'gear' => 'Melee',
		      'note'  => 'The one who wades in.'),
		array('asset' => 'GRUNT002', 'name' => 'Delay',     'armor' => 'Medium', 'gear' => 'Demolition',
		      'note'  => 'A delay is a demolition fuse.'),
		array('asset' => 'GRUNT001', 'name' => 'Blood Bag', 'armor' => 'Light',  'gear' => 'Medkit',
		      'note'  => 'The medic, and the obvious one.'),
		array('asset' => 'GRUNT004', 'name' => 'Sektr',     'armor' => 'Base',   'gear' => 'None',
		      'note'  => 'Carries nothing, and that is the point.'),
	);
}

/**
 * Is this the shape dropshipPublicArt() returns?
 *
 * Every entry an array carrying a url and a name. Used on the way out
 * of the cache and by the page before it draws, so neither a stale file
 * nor a half-written one can take a public page down.
 */
function dropshipArtListOk($list) {
	if (!is_array($list) || !$list) return false;
	foreach ($list as $row) {
		if (!is_array($row) || !isset($row['url'], $row['name'])) return false;
		if (!is_string($row['url']) || !is_string($row['name'])) return false;
	}
	return true;
}

/** Where Drop Ship keeps its own soldier art, as a public URL. */
function dropshipPublicArtUrl($asset) {
	return 'https://skulliance.io/staking/dropship/images/nfts/' . rawurlencode($asset) . '.jpg';
}

/**
 * Real soldier art for the public page, as a flat list of image URLs.
 *
 * READ STRAIGHT OUT OF DROP SHIP'S OWN FOLDER. The first version of this
 * went the long way round -- join the Skulliance collections table on
 * the theme's policy, then glob the platform's NFT image cache at
 * images/nfts/<project>/<collection>/<md5 of the cid> -- which is how
 * every OTHER theme's art is stored, and is wrong for this one. Drop
 * Ship's NFTs live at dropship/images/nfts/<ASSET_NAME>.jpg and always
 * have: dropshipSquadFace() special-cases project 1 to exactly that
 * path. Going through the cache produced a wall with eleven dead frames
 * in it, because the two stores do not hold the same set.
 *
 * So: no database, no cross-database join, no md5, no gateway. One
 * directory listing of files that are already served from this host.
 *
 * GRUNTS ARE EXCLUDED HERE because they get their own section with
 * their names and kit on it; loose in the strip they are four soldiers
 * nobody can tell from the rest.
 *
 * Returns array('url', 'name') per tile.
 */
function dropshipPublicArt($limit = 24) {
	static $cache = array();
	$key = (int)$limit;
	if (isset($cache[$key])) return $cache[$key];
	$cache[$key] = array();

	$dir = __DIR__ . '/../dropship/images/nfts';
	if (!is_dir($dir)) return $cache[$key];

	/*
	 * CACHED TO DISK FOR AN HOUR. This is the public front door and the
	 * answer changes about as often as somebody mints, so every crawler
	 * and every share unfurl should not cost a directory listing. A
	 * failed read or write is a miss: same page, slightly slower.
	 */
	/*
	 * THE VERSION IN THE KEY IS NOT DECORATION. This cache went out
	 * holding a flat list of URL strings, and the next change made every
	 * entry an array of url+name -- same key, so an hour-old file kept
	 * feeding strings to code that now subscripts them. In PHP 8 that is
	 * an uncaught TypeError, "Cannot access offset of type string on
	 * string", and the public page simply stopped rendering half way
	 * down. BUMP THIS whenever the shape of an entry changes.
	 */
	$cf = sys_get_temp_dir() . '/skl-dsart2-' . md5($dir . '|' . $key) . '.json';
	if (is_file($cf) && (time() - (int)@filemtime($cf)) < 3600) {
		$hit = json_decode((string)@file_get_contents($cf), true);
		/* AND VALIDATED ANYWAY, because a version in the key only helps
		   for the change you remembered to bump it for. A payload that is
		   not the shape this function returns is a miss, not a crash. */
		if (dropshipArtListOk($hit)) { $cache[$key] = $hit; return $cache[$key]; }
	}

	$files = @glob($dir . '/*.jpg');
	if (!$files) return $cache[$key];
	$assets = array();
	foreach ($files as $f) {
		if (@filesize($f) < 1) continue;        /* a half-written file is a dead frame */
		$n = basename($f, '.jpg');
		if (stripos($n, 'GRUNT') === 0) continue;
		$assets[] = $n;
	}
	if (!$assets) return $cache[$key];
	sort($assets);

	/*
	 * THE NAMES THEY WERE REGISTERED UNDER, which is the difference
	 * between a contact sheet and a roll call. One query for the whole
	 * project rather than one per tile, and the result is cached with
	 * the art list, so the cost is once an hour.
	 *
	 * ASSET NAMES, NOT OWNERS. soldiers.user_id is deliberately not
	 * selected and never will be: the art and the card's name are
	 * already public on the collection pages, and who holds which one is
	 * not this page's business.
	 */
	$label = array();
	$db = dropshipPublicDb();
	if ($db) {
		$r = @$db->query("SELECT asset_name, name FROM soldiers
		                   WHERE project_id = 1 AND name <> ''");
		if ($r) while ($row = $r->fetch_assoc()) {
			$label[(string)$row['asset_name']] = (string)$row['name'];
		}
	}

	/* SPREAD ACROSS THE COLLECTION rather than the first N, which would
	   be whichever sort first and tend to look alike. Deterministic, so
	   the strip does not reshuffle every load and the browser cache is
	   worth something. */
	$n = count($assets);
	$take = min((int)$limit, $n);
	for ($i = 0; $i < $take; $i++) {
		$a = $assets[(int)floor($i * $n / $take)];
		/* A soldier with no row still gets a tile; its asset name is a
		   duller label than a real one but it is not a blank. */
		$cache[$key][] = array(
			'url'  => dropshipPublicArtUrl($a),
			'name' => isset($label[$a]) && trim($label[$a]) !== '' ? trim($label[$a]) : $a,
		);
	}
	$tmp = $cf . '.' . getmypid() . '.tmp';
	if (@file_put_contents($tmp, json_encode($cache[$key])) !== false) {
		if (!@rename($tmp, $cf)) @unlink($tmp);
	}
	return $cache[$key];
}

/**
 * A theme's cover art, as a public URL.
 *
 * SAME CONVENTION dropshipThemeImagePath() USES, which is "drop-ship is
 * flat and everything else nests under its slug" -- the flat art
 * predates the themes table and never moved. Repeated here rather than
 * required, because that function lives behind Drop Ship's own db.php,
 * which IS the login gate, and this is read by pages with no session.
 *
 * NOT CHECKED FOR EXISTENCE. The caller hands it to an <img> with an
 * onerror, which is the only check that matters for a file served by
 * another host's filesystem: is_file() here would pass for a theme
 * whose art was deleted by FTP between the check and the request.
 */
function dropshipPublicCover($slug = '') {
	$slug = strtolower(trim((string)$slug));
	if ($slug === '' || !preg_match('/^[a-z0-9-]{1,48}$/', $slug)) $slug = 'drop-ship';
	$rel = ($slug === 'drop-ship') ? 'images/dropship.jpg' : $slug . '/images/dropship.jpg';
	return 'https://skulliance.io/staking/dropship/' . $rel;
}

/**
 * Drop Ship's rank ladder, lowest first, as rank => soldiers needed.
 *
 * TRANSCRIBED FROM dashboard.php's own chain, which is a hardcoded
 * if/else on dropshipSoldierCount() gated to project 1. There is no
 * table behind it to read, so this is a copy and the only honest thing
 * to do is say so: if that chain changes, change this. The harness pins
 * the two against each other rather than trusting the comment.
 *
 * COUNTED ON SOLDIERS HELD, not on anything earned -- the ladder is a
 * collection size, which is worth being plain about on a page whose
 * whole pitch is that you can start with nothing.
 */
function dropshipPublicRanks() {
	return array(
		'Private'                =>  1,
		'Specialist'             =>  3,
		'Corporal'               =>  5,
		'Sergeant'               =>  8,
		'Staff Sergeant'         => 12,
		'Sergeant First Class'   => 17,
		'Master Sergeant'        => 23,
		'First Sergeant'         => 30,
		'Sergeant Major'         => 40,
		'Command Sergeant Major' => 55,
	);
}
