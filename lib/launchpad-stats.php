<?php
/*
 * lib/launchpad-stats.php — the three numbers under the name.
 *
 * SPLIT OUT SO IT CAN BE TESTED. launchpad.php is a page: including it
 * runs a login gate, a header and a dozen queries, so nothing in it can
 * be called from a harness. The rule for which stats show is the kind of
 * thing that goes wrong quietly -- a row that reads "0 raids" to someone
 * who has never built a realm, or an empty row for a brand new account --
 * so it lives here, takes plain integers, touches no database and no
 * session, and is asserted directly.
 *
 * WHY NOT WALLETS. The row used to be points / streak / wallets, and
 * wallets is a setup detail: it is the same number for a member in their
 * first minute and one in their third year, and it is already the first
 * thing Start Here asks for. Missions sent and raids launched are what
 * the account has actually DONE, which is what a line under your own
 * name should be saying.
 *
 * A ZERO IS NOT A STAT. "0 raids" tells a player they are failing at
 * something they have not started, which is the opposite of the job.
 * Zeroes drop out -- and if both drop out there is nothing left to do
 * but what the account has, so wallets comes back to fill the space.
 */

/**
 * @param int $points   lifetime points balance
 * @param int $streak   current daily streak, in days
 * @param int $missions missions ever sent
 * @param int $raids    raids ever launched
 * @param int $wallets  wallets connected
 * @return array list of array(value, singular, plural) in display order
 */
function lp_hero_stats($points, $streak, $missions, $raids, $wallets) {
	$out = array(
		array((int)$points, 'point',      'points'),
		/* "day streak" either way: "1 day streaks" is wrong and
		   "1 day streak" is right, so the plural is on the day. */
		array((int)$streak, 'day streak', 'day streak'),
	);
	$done = false;
	if ((int)$missions > 0) { $out[] = array((int)$missions, 'mission', 'missions'); $done = true; }
	if ((int)$raids    > 0) { $out[] = array((int)$raids,    'raid',    'raids');    $done = true; }
	/* Nothing done yet, so the row would be two items wide under a name.
	   Wallets is the one number a fresh account can still have, and a
	   zero there is worth saying because connecting one is step one. */
	if (!$done) $out[] = array((int)$wallets, 'wallet', 'wallets');
	return $out;
}

/** The label for a count, which is the plural everywhere but exactly one. */
function lp_stat_label($stat) {
	return (int)$stat[0] === 1 ? $stat[1] : $stat[2];
}
