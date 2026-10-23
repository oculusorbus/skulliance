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

	$r = @$db->query("SELECT t.slug, t.project_id, p.name
	                    FROM themes t INNER JOIN projects p ON p.id = t.project_id
	                   WHERE t.active = 1 AND t.slug <> ''");
	if (!$r) return $out;
	$rows = array();
	while ($row = $r->fetch_assoc()) {
		$rows[(int)$row['project_id']] = array(
			'slug'       => (string)$row['slug'],
			'name'       => (string)$row['name'],
			'project_id' => (int)$row['project_id'],
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
