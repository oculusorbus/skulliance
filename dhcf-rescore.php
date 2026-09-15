<?php
/**
 * DHC FIGHTERS -- rescore every saved Fighter.
 *
 * Rarity here is deliberately LIQUID: a Fighter's score is derived from the
 * current rarity table, not frozen at save time. So whenever dhcrarity.php
 * changes -- a trait's on-chain count is corrected, new art arrives and
 * re-spreads a category's curve -- the rarity_score column stops agreeing with
 * the table it came from, and the leaderboard ranks people on stale numbers
 * until this runs.
 *
 * CLI ONLY, and deliberately so. It rewrites every row in dhc_fighters, and
 * there is no admin role anywhere in this codebase to gate a web version behind
 * -- a login check alone would leave a whole-table rewrite reachable by any
 * staker. Shell access is the gate.
 *
 * Run from the staking directory on the server:
 *
 *     php dhcf-rescore.php              # dry run -- shows what WOULD change
 *     php dhcf-rescore.php --confirm    # writes
 *
 * The dry run is the default because the write is not reversible: old scores
 * are overwritten, not journalled.
 */

if (PHP_SAPI !== 'cli') {
	http_response_code(404);
	exit;
}

// db.php uses relative includes ('credentials/db_credentials.php'), which
// resolve against the working directory rather than this file.
chdir(__DIR__);

include __DIR__ . '/db.php';
require_once __DIR__ . '/dhcfighters-lib.php';

$confirm = in_array('--confirm', $argv, true);

/*
 * Every row, matching dhcf_rescore_all() exactly -- it does not filter either,
 * and a preview that counts different rows than the thing it is previewing is
 * worse than no preview. The first run reported "2 Fighters" then "Rescored 4".
 *
 * Disassembled rows are rescored but inert: they are hidden from the gallery
 * and the boards, and dhcf_is_first_build() filters them out, so neither their
 * score nor their hash is ever read. They are counted separately below rather
 * than silently inflating the number.
 */
$res = $conn->query("SELECT id, user_id, traits, rarity_score, traits_hash,
                            disassembled_at
                     FROM dhc_fighters ORDER BY id");
if (!$res) {
	fwrite(STDERR, "query failed: " . $conn->error . "\n");
	exit(1);
}
$rows = array();
while ($r = $res->fetch_assoc()) $rows[] = $r;

if (!$rows) { echo "No saved Fighters. Nothing to do.\n"; exit(0); }

// Work out the deltas BEFORE writing anything, so a dry run and the real run
// report the same thing and the operator sees the damage first.
$changed = array(); $hashfix = 0; $gone = 0;
foreach ($rows as $r) {
	if ($r['disassembled_at'] !== null) $gone++;
	$t   = json_decode($r['traits'], true) ?: array();
	$sc  = dhcf_score_with_bonus($conn, (int)$r['user_id'], $t, (int)$r['id']);
	$new = (int)$sc['total'];
	$old = (int)$r['rarity_score'];
	if ($new !== $old) $changed[] = array($r['id'], $r['user_id'], $old, $new);
	if (dhcf_traits_hash($t) !== $r['traits_hash']) $hashfix++;
}

printf("%d rows (%d live, %d disassembled) · %d scores change · %d hashes need rewriting\n\n",
	count($rows), count($rows) - $gone, $gone, count($changed), $hashfix);

if ($changed) {
	usort($changed, function ($a, $b) { return abs($b[3]-$b[2]) <=> abs($a[3]-$a[2]); });
	echo "largest moves:\n";
	foreach (array_slice($changed, 0, 15) as $c) {
		printf("  #%-5d user %-6d %7s -> %-7s  %+d\n",
			$c[0], $c[1], number_format($c[2]), number_format($c[3]), $c[3]-$c[2]);
	}
	if (count($changed) > 15) printf("  ... and %d more\n", count($changed) - 15);
	echo "\n";
}

if (!$confirm) {
	echo "DRY RUN -- nothing written. Re-run with --confirm to apply.\n";
	exit(0);
}

$n = dhcf_rescore_all($conn);
printf("Rescored %d Fighters.\n", $n);
