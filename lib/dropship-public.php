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

/**
 * Real soldier art for the public page, as a flat list of image URLs.
 *
 * FROM THE LOCAL CACHE, NEVER AN IPFS GATEWAY. Every one of these is a
 * file already sitting on this server under
 * images/nfts/<project>/<collection>/<md5 of the cid>.<ext> -- the same
 * convention getIPFS() and dropshipLocalNftImages() use. That matters
 * more here than anywhere else on the platform: this page is the first
 * thing a stranger sees, a gateway timeout is a wall of broken frames,
 * and jpg.store going dark already proved how quietly that happens.
 *
 * READ OFF THE DISK, not out of the nfts table. The question being asked
 * is "which pictures exist", and the filesystem is the only thing that
 * actually knows -- a row whose art was never cached would be a broken
 * frame, and checking each one would be a query per tile.
 *
 * NO NAMES, NO OWNERS, NO TOKEN IDS. This is a wall of art and nothing
 * else. The art is already public on the collection pages; who holds
 * which soldier is not this page's business.
 */
function dropshipPublicArt($limit = 24, $only_slug = 'drop-ship') {
	static $cache = array();
	$key = (int)$limit . '|' . (string)$only_slug;
	if (isset($cache[$key])) return $cache[$key];
	$cache[$key] = array();

	/*
	 * CACHED TO DISK FOR AN HOUR, because this is the public front door
	 * and the answer changes about as often as somebody mints. Without
	 * it every hit -- every crawler, every share unfurl -- costs a
	 * database round trip and a glob over a directory holding a whole
	 * collection. The page is meant to be linked from the homepage, so
	 * that is the traffic to design for rather than today's.
	 *
	 * A failed read or write is simply a cache miss: the list is
	 * recomputed and the page is identical, just slower.
	 */
	$cf = sys_get_temp_dir() . '/skl-dsart-' . md5($key) . '.json';
	if (is_file($cf) && (time() - (int)@filemtime($cf)) < 3600) {
		$hit = json_decode((string)@file_get_contents($cf), true);
		if (is_array($hit)) { $cache[$key] = $hit; return $cache[$key]; }
	}

	$themes = dropshipPublicThemes();
	if (!$themes) return $cache[$key];
	$sk = dropshipPublicSkullianceDb();
	if (!$sk) return $cache[$key];

	/* ONE COLLECTION FOR NOW, at the owner's direction: Drop Ship's own
	   soldiers and Drop Ship's own icons. Five collections of art on one
	   page is a mosaic of five different styles fighting each other, and
	   this page is a pitch for a game rather than a gallery. The filter
	   is a parameter and not a hardcode, so the wall can widen later
	   without this function changing. */
	if ($only_slug !== '') {
		$themes = isset($themes[$only_slug]) ? array($themes[$only_slug]) : array();
	}
	$per_theme = $themes ? max(1, (int)ceil($limit / count($themes))) : 0;

	foreach ($themes as $t) {
		$policy = isset($t['policy']) ? trim((string)$t['policy']) : '';
		/* A policy id is hex. Shape-checked because it reaches a query and,
		   through the ids it returns, a directory path. */
		if ($policy === '' || !preg_match('/^[0-9a-f]{40,80}$/i', $policy)) continue;
		$r = @$sk->query("SELECT c.id, c.project_id FROM collections c
		                   WHERE c.policy = '" . $sk->real_escape_string($policy) . "' LIMIT 1");
		if (!$r || !($row = $r->fetch_assoc())) continue;

		$dir = __DIR__ . '/../images/nfts/' . (int)$row['project_id'] . '/' . (int)$row['id'];
		if (!is_dir($dir)) continue;
		$files = @glob($dir . '/*.{jpg,jpeg,png,webp,gif}', GLOB_BRACE);
		if (!$files) continue;
		/*
		 * SPREAD ACROSS THE COLLECTION RATHER THAN THE FIRST N, which
		 * would be whichever eight happen to sort first and tend to look
		 * alike. Deterministic, so the wall does not reshuffle on every
		 * page load and the browser cache is worth something.
		 */
		sort($files);
		$n = count($files);
		$take = min((int)$per_theme, $n);
		for ($i = 0; $i < $take; $i++) {
			$f = $files[(int)floor($i * $n / $take)];
			$cache[$key][] = array(
				'url'   => 'https://skulliance.io/staking/images/nfts/' . (int)$row['project_id']
				         . '/' . (int)$row['id'] . '/' . rawurlencode(basename($f)),
				'theme' => $t['name'],
			);
		}
	}
	/* Trim to the asked-for total: the per-theme share rounds up. */
	if (count($cache[$key]) > (int)$limit) $cache[$key] = array_slice($cache[$key], 0, (int)$limit);
	/* Written through a temp name: two crawlers arriving together must not
	   leave each other half a file to decode. */
	if ($cache[$key]) {
		$tmp = $cf . '.' . getmypid() . '.tmp';
		if (@file_put_contents($tmp, json_encode($cache[$key])) !== false) {
			if (!@rename($tmp, $cf)) @unlink($tmp);
		}
	}
	return $cache[$key];
}
