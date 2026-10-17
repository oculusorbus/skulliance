<?php
/**
 * monstrocity-theme-check.php — CLI only. Why is a theme not offering my NFTs?
 *
 * A Monstrocity theme gets its policy ids one of two ways, and attaching
 * collections switches it from one to the other:
 *
 *   nothing attached  ->  the legacy monstrocity_themes.policy_ids string
 *   one or more       ->  collections.policy of the attached rows, and the
 *                         legacy string is IGNORED ENTIRELY
 *
 * That override is the whole point of the feature, but it is also the one way
 * attaching collections can take NFTs AWAY: a collection whose `policy` is
 * empty contributes nothing (monstrocity_theme_policies filters it out), and
 * a collection that was never ticked -- because the theme's project_id
 * narrows the checkbox list, so a collection filed under another project is
 * hidden by default -- is simply not in the derived set. Either way the
 * hardcoded policy stops being asked for and the game offers only defaults.
 *
 * ajax/get-nft-assets.php matches on `collections.policy IN (...)`, so this
 * reproduces that exact join rather than describing it.
 *
 *     php monstrocity-theme-check.php                      # every theme, summary
 *     php monstrocity-theme-check.php spookshowsagan       # one theme, detail
 *     php monstrocity-theme-check.php spookshowsagan oculusorbus   # and one player
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

include __DIR__ . '/db.php';
require_once __DIR__ . '/monstrocity-themes-lib.php';

$slug = isset($argv[1]) ? trim($argv[1]) : '';
$user = isset($argv[2]) ? trim($argv[2]) : '';

$uid = 0;
if ($user !== '') {
	$r = $conn->query("SELECT id FROM users WHERE username = '" . $conn->real_escape_string($user) . "'");
	$row = $r ? $r->fetch_assoc() : null;
	if (!$row) { echo "No such user: $user\n"; exit(1); }
	$uid = (int)$row['id'];
	echo "Player: $user (user_id $uid)\n\n";
}

$derived = monstrocity_theme_policies($conn);

$res = $conn->query('SELECT id, slug, title, project_id, project_name, policy_ids, orientations, active
                       FROM monstrocity_themes ORDER BY slug ASC');
$themes = array();
while ($t = $res->fetch_assoc()) $themes[] = $t;

/* The same count the game will get, for one policy list. */
function mtc_owned($conn, $uid, $policies) {
	if (!$uid || !$policies) return 0;
	$q = array();
	foreach ($policies as $p) $q[] = "'" . $conn->real_escape_string($p) . "'";
	$r = $conn->query('SELECT COUNT(*) AS n FROM nfts
	                     INNER JOIN collections ON collections.id = nfts.collection_id
	                    WHERE nfts.user_id = ' . (int)$uid . '
	                      AND collections.policy IN (' . implode(',', $q) . ')');
	return $r ? (int)$r->fetch_assoc()['n'] : 0;
}

foreach ($themes as $t) {
	$id = (int)$t['id'];
	if ($slug !== '' && $t['slug'] !== $slug) continue;

	$legacy  = array_values(array_filter(array_map('trim', explode(',', (string)$t['policy_ids']))));
	$fromcol = isset($derived[$id]) && $derived[$id] !== ''
	         ? array_values(array_filter(array_map('trim', explode(',', $derived[$id])))) : array();
	$live    = $fromcol ? $fromcol : $legacy;
	$source  = $fromcol ? 'collections' : 'legacy string';

	/* Every attached row, INCLUDING the ones with no policy -- those are
	   exactly what this script exists to find, and they are invisible in the
	   derived string by definition. */
	$links = array();
	$r = $conn->query('SELECT c.id, c.name, c.policy, c.project_id, p.name AS project, tc.sort_order
	                     FROM monstrocity_theme_collections tc
	                     JOIN collections c ON c.id = tc.collection_id
	                LEFT JOIN projects p ON p.id = c.project_id
	                    WHERE tc.theme_id = ' . $id . '
	                 ORDER BY tc.sort_order ASC, c.id ASC');
	if ($r) while ($x = $r->fetch_assoc()) $links[] = $x;

	if ($slug === '') {
		printf("%-22s %-13s %2d policies  %2d attached%s\n",
			$t['slug'], $source, count($live), count($links),
			$uid ? '  ' . mtc_owned($conn, $uid, $live) . ' owned' : '');
		continue;
	}

	echo $t['title'] . ' (' . $t['slug'] . ', theme ' . $id . ')'
	   . ($t['active'] ? '' : '  [INACTIVE: hidden from the picker]') . "\n";
	echo 'Project: ' . ($t['project_name'] !== '' ? $t['project_name'] : '(none)')
	   . ' (project_id ' . (int)$t['project_id'] . ")\n";
	echo 'Policies in use: ' . count($live) . ' from the ' . $source . "\n\n";

	echo "Attached collections (" . count($links) . "):\n";
	foreach ($links as $c) {
		$pol = trim((string)$c['policy']);
		echo '  #' . $c['id'] . '  ' . $c['name']
		   . '  [' . ($c['project'] !== null ? $c['project'] : 'no project') . ']'
		   . "\n      policy: " . ($pol === '' ? '*** EMPTY -- contributes nothing ***' : $pol);
		if ($uid) echo '   you own ' . mtc_owned($conn, $uid, array($pol)) . ' here';
		echo "\n";
	}
	if (!$links) echo "  (none, so the legacy string is used)\n";

	echo "\nLegacy policy_ids (" . count($legacy) . (count($links) ? ', IGNORED' : ', in use') . "):\n";
	foreach ($legacy as $p) {
		$in = in_array($p, $live, true);
		echo '  ' . $p . ($in ? '  [still covered]' : '  [NO LONGER ASKED FOR]');
		if ($uid) echo '   you own ' . mtc_owned($conn, $uid, array($p));
		echo "\n";
	}
	if (!$legacy) echo "  (none)\n";

	/*
	 * ORIENTATIONS ARE POSITIONAL against the policy list, so a derived list
	 * of a different length than the string they were written for silently
	 * reassigns which way each collection faces. One orientation is fine --
	 * the client applies it to everything.
	 */
	$ors = array_values(array_filter(array_map('trim', explode(',', (string)$t['orientations']))));
	echo "\nOrientations: " . (count($ors) ? implode(', ', $ors) : '(none, defaults to Right)') . "\n";
	if (count($ors) > 1 && count($ors) !== count($live))
		echo "  *** " . count($ors) . " orientations for " . count($live) . " policies: "
		   . "they are matched by position, so some collections face the wrong way.\n";

	if ($uid) {
		echo "\nWhat the game would hand you: " . mtc_owned($conn, $uid, $live) . " NFTs"
		   . ($legacy ? ' (legacy string would have given ' . mtc_owned($conn, $uid, $legacy) . ')' : '') . "\n";
		/* Where the rest of your NFTs actually live, so a missing tick is
		   named rather than deduced. */
		$r = $conn->query('SELECT c.id, c.name, c.policy, COUNT(*) AS n
		                     FROM nfts INNER JOIN collections c ON c.id = nfts.collection_id
		                    WHERE nfts.user_id = ' . $uid . '
		                 GROUP BY c.id, c.name, c.policy ORDER BY n DESC');
		echo "\nYour NFTs by collection:\n";
		while ($x = $r->fetch_assoc()) {
			$pol = trim((string)$x['policy']);
			$cov = $pol !== '' && in_array($pol, $live, true);
			printf("  %4d  %-34s %s%s\n", $x['n'], substr($x['name'], 0, 34),
				$cov ? 'covered' : 'not covered',
				$pol === '' ? '  (collection has no policy)' : '');
		}
	}
	echo "\n";
}
