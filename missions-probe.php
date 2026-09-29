<?php
/*
 * missions-probe.php — CLI only. Why is a rung unlocked but never run?
 *
 * There are only three ways that can happen, and they look identical on
 * the page. This tells them apart from the actual rows:
 *
 *   ADDED LATER   the rung did not exist when you climbed past. quests.id
 *                 is auto-increment, so a rung whose id is higher than the
 *                 rungs ABOVE it was inserted into the middle afterwards.
 *
 *   JUMPED        a mission at a HIGHER level was launched and cleared
 *                 without the levels beneath it ever being run. The old
 *                 page made this possible for one account: getMissions()
 *                 rendered the submit form for locked missions when the
 *                 discord id was the admin's, the locked card still had an
 *                 onclick that pressed it, and startMission() never
 *                 re-checked the lock. One stray click on a locked card
 *                 launched it for real.
 *
 *   FRONTIER      it is simply the next rung and has not been run yet.
 *                 Not an anomaly at all.
 *
 * READ-ONLY. No writes, no session, safe to run against anything.
 *
 *     php missions-probe.php <user_id|username> [project_id]
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

$arg = isset($argv[1]) ? trim($argv[1]) : '';
if ($arg === '') {
	echo "usage: php missions-probe.php <user_id|username> [project_id]\n";
	exit(1);
}

include __DIR__ . '/db.php';

$esc = $conn->real_escape_string($arg);
$ur  = ctype_digit($arg)
	? $conn->query("SELECT id, username FROM users WHERE id = '" . (int)$arg . "' LIMIT 1")
	: $conn->query("SELECT id, username FROM users WHERE username = '$esc' LIMIT 1");
if (!$ur || !$ur->num_rows) { echo "No such user.\n"; exit(1); }
$u   = $ur->fetch_assoc();
$uid = (int)$u['id'];
printf("user %d (%s)\n\n", $uid, $u['username']);

$only = isset($argv[2]) ? (int)$argv[2] : 0;
$where = $only ? " WHERE q.project_id = '$only'" : "";

/* The whole ladder, every project. */
$quests = array();
$qr = $conn->query(
	"SELECT q.id, q.project_id, q.level, q.title, p.name AS project_name
	 FROM quests q INNER JOIN projects p ON p.id = q.project_id
	 $where ORDER BY q.project_id, q.level");
while ($r = $qr->fetch_assoc()) $quests[(int)$r['project_id']][] = $r;

/* Every mission this user has ever launched, by quest. */
$runs = array();
$mr = $conn->query(
	"SELECT quest_id, status, COUNT(*) AS n, MIN(created_date) AS first, MAX(created_date) AS last
	 FROM missions WHERE user_id = '$uid' GROUP BY quest_id, status");
while ($r = $mr->fetch_assoc()) $runs[(int)$r['quest_id']][(int)$r['status']] = $r;

$totals = array('added' => 0, 'jumped' => 0, 'frontier' => 0);

foreach ($quests as $pid => $list) {
	/* Cleared = highest level with a SUCCESS, the same rule getMissionLevels()
	   uses and therefore the same one the page unlocks by. */
	$cleared = 0; $cleared_row = null;
	foreach ($list as $q)
		if (isset($runs[(int)$q['id']][1]) && (int)$q['level'] > $cleared) {
			$cleared = (int)$q['level']; $cleared_row = $q;
		}
	if ($cleared === 0) continue;   /* nothing cleared: nothing is anomalous */

	/* Unlocked and never launched at all. */
	$odd = array();
	foreach ($list as $q) {
		if ((int)$q['level'] > $cleared + 1) continue;      // locked
		if (isset($runs[(int)$q['id']])) continue;          // has been run
		$odd[] = $q;
	}
	if (!$odd) continue;

	printf("== %s (project %d) -- cleared to level %d\n", $list[0]['project_name'], $pid, $cleared);

	foreach ($odd as $q) {
		$lvl = (int)$q['level']; $qid = (int)$q['id'];

		/* Was it slotted in below where they already were? */
		$min_above = null;
		foreach ($list as $o)
			if ((int)$o['level'] > $lvl && ($min_above === null || (int)$o['id'] < $min_above))
				$min_above = (int)$o['id'];
		$added = ($min_above !== null && $qid > $min_above);

		if ($lvl === $cleared + 1) {
			$verdict = 'FRONTIER  next rung, simply not run yet'; $totals['frontier']++;
		} else if ($added) {
			$verdict = 'ADDED LATER  id ' . $qid . ' is above id ' . $min_above
			         . ' at a higher level, so it was inserted afterwards';
			$totals['added']++;
		} else {
			$verdict = 'JUMPED  authored before the levels above it, so a higher level '
			         . 'was cleared without this one'; $totals['jumped']++;
		}
		printf("   L%-3d id %-6d %-38s %s\n", $lvl, $qid, substr($q['title'], 0, 38), $verdict);
	}

	/* For a JUMPED project, name the run that did it -- that is the click. */
	$suspects = array();
	foreach ($list as $q) {
		$lvl = (int)$q['level'];
		if (!isset($runs[(int)$q['id']][1])) continue;
		foreach ($odd as $o) if ((int)$o['level'] < $lvl) { $suspects[] = array($q, $lvl); break; }
	}
	if ($suspects) {
		usort($suspects, function ($a, $b) { return $a[1] - $b[1]; });
		list($q, $lvl) = $suspects[0];
		printf("   -> lowest cleared level ABOVE a never-run rung: L%d \"%s\", first run %s\n",
			$lvl, $q['title'], $runs[(int)$q['id']][1]['first']);
		printf("      If that predates anything below it, it was launched while still locked.\n");
	}
	echo "\n";
}

printf("%d frontier, %d added later, %d jumped\n",
	$totals['frontier'], $totals['added'], $totals['jumped']);
echo "\nJUMPED rows cannot happen any more: mission_launch() re-derives the level\n";
echo "lock from the database and refuses, whoever is asking. See missions-lib.php.\n";
$conn->close();
