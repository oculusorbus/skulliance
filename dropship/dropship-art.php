<?php
/**
 * GROUNDWORK, NOT YET WIRED TO ANYTHING. Nothing requires this file and no
 * page calls it. It is the first piece of a Drop Ship theme admin -- parked
 * here deliberately while the shape of that tool is still being decided, so
 * that it is under version control rather than loose in a working tree.
 *
 * What it still needs before it is useful: a CLI or admin page that calls
 * dropshipArtAudit() per theme and prints the result, and a harness pinning
 * the stage counts below against dropship.php's own initialisers. Both were
 * in progress and neither exists.
 *
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
 * $ext is the animation extension, which is a property of the theme:
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
