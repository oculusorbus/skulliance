<?php
/**
 * dhcbosses-repay.php — pay what a mis-settled week still owes. CLI only.
 *
 *   php dhcbosses-repay.php            what it WOULD pay, and to whom
 *   php dhcbosses-repay.php --run      actually pay it
 *   php dhcbosses-repay.php --run 2026-10-22   a specific week
 *
 * WHY THIS EXISTS. The first ever DHC Bosses payout gave every participant
 * three traits regardless of how many rounds they turned up for: the
 * settle grouped rounds with a day index anchored on NOW(), and the cron
 * fires after the 4pm Thursday turn, so every round of the closed week
 * collapsed into one block. A player with twelve traits banked got three.
 *
 * DRY RUN BY DEFAULT, because this hands out real traits and posts each
 * one to Discord. Nothing is written without --run.
 *
 * SAFE TO RE-RUN. It pays the difference between what is owed and what the
 * payout row already records, so a second run finds nothing outstanding.
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
error_reporting(E_ALL & ~E_DEPRECATED);

require __DIR__ . '/db.php';
require_once __DIR__ . '/dhcbosses-lib.php';

$run  = in_array('--run', $argv, true);
$week = null;
foreach (array_slice($argv, 1) as $a)
	if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $a)) $week = $a;
if ($week === null) {
	/* The week that just closed, the same way rewards.php finds it. */
	$r = $conn->query('SELECT DATE(DATE_SUB(' . skl_week_start_sql() . ', INTERVAL 7 DAY)) AS w');
	$week = ($r && ($x = $r->fetch_assoc())) ? $x['w'] : skl_week_key();
}

printf("DHC Bosses repay -- week of %s%s\n\n", $week, $run ? '' : '  (DRY RUN, nothing written)');

/* What each participant is owed now, against what the row says they got. */
$owedTotal = 0; $rows = array();
foreach (dhcb_participants($conn, $week) as $uid => $p) {
	if ($p['dealt'] < DHCB_MIN_DEALT && $p['rounds_max'] < DHCB_MIN_ROUNDS) continue;
	$slots = max(1, (int)$p['traits'] > 0
		? (int)$p['traits'] : min((int)DHCB_CACHE_MAX, (int)$p['days']));
	$prev = null;
	$pr = $conn->query(sprintf("SELECT slots FROM dhc_boss_payouts WHERE day = '%s' AND user_id = %d",
		$conn->real_escape_string($week), (int)$uid));
	if ($pr && ($px = $pr->fetch_assoc())) $prev = (int)$px['slots'];
	$owe = $prev === null ? $slots : $slots - $prev;
	if ($owe <= 0) continue;
	$nm = '';
	$ur = $conn->query('SELECT username FROM users WHERE id = ' . (int)$uid);
	if ($ur && ($ux = $ur->fetch_assoc())) $nm = $ux['username'];
	$rows[] = array($uid, $nm, $prev === null ? '(never paid)' : $prev, $slots, $owe);
	$owedTotal += $owe;
}

if (!$rows) { echo "Nothing outstanding.\n"; exit(0); }
printf("%-6s %-22s %10s %8s %6s\n", 'user', 'name', 'was paid', 'owed', 'to pay');
foreach ($rows as $r) printf("%-6d %-22s %10s %8d %6d\n", $r[0], substr($r[1], 0, 22), $r[2], $r[3], $r[4]);
printf("\n%d players, %d traits outstanding\n", count($rows), $owedTotal);

if (!$run) { echo "\nRe-run with --run to pay it.\n"; exit(0); }

echo "\nPaying...\n";
$res = dhcb_settle($conn, $week, false, true);
printf("paid %d players %d traits on the %s table\n",
	(int)$res['paid'], (int)$res['traits'], $res['table']);
