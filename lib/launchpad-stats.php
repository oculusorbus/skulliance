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
 * thing Start Here asks for.
 *
 * AND NO POINTS. The balance led this row and was the least useful
 * thing on it: it is a running total that only ever goes up, it is
 * already on points.php and in the nav, and at eight digits it set the
 * scale for everything beside it -- a 3 next to 19,875,730 does not
 * read as a number at all. The row is now three short facts about the
 * state of the account rather than one big one and two small ones.
 *
 * AND WHY RUNNING, NOT EVER. The first cut counted every mission and
 * every raid the account had ever sent, which put 17,062 under the name
 * -- a lifetime tally, the same shape of number as the points balance
 * next to it, saying nothing about today. What is OUT right now is a
 * reason to open the page: those runs finish, and they finish while you
 * are not looking.
 *
 * The label is just the noun. It read "3 missions running" for a
 * version, on the argument that a bare count would be taken for a
 * career total -- but the number is single digits next to a day streak,
 * and nothing on this row is a lifetime figure any more now that the
 * points balance has gone.
 *
 * A ZERO IS NOT A STAT. "0 raids running" tells a player they are
 * failing at something they have not started, which is the opposite of
 * the job. Zeroes drop out -- and if both drop out there is nothing in
 * flight to show, so wallets comes back to fill the space.
 */

/**
 * @param int $streak   current daily streak, in days
 * @param int $missions missions out on a run right now
 * @param int $raids    raids in progress right now
 * @param int $wallets  wallets connected
 * @return array list of array(value, singular, plural) in display order
 */
function lp_hero_stats($streak, $missions, $raids, $wallets) {
	$out = array(
		/* "day streak" either way: "1 day streaks" is wrong and
		   "1 day streak" is right, so the plural is on the day. */
		array((int)$streak, 'day streak', 'day streak'),
	);
	$done = false;
	if ((int)$missions > 0) { $out[] = array((int)$missions, 'mission', 'missions'); $done = true; }
	if ((int)$raids    > 0) { $out[] = array((int)$raids,    'raid',    'raids');    $done = true; }
	/* Nothing out, so the row would be two items wide under a name.
	   Wallets is the one number a fresh account can still have, and a
	   zero there is worth saying because connecting one is step one. */
	if (!$done) $out[] = array((int)$wallets, 'wallet', 'wallets');
	return $out;
}

/** The label for a count, which is the plural everywhere but exactly one. */
function lp_stat_label($stat) {
	return (int)$stat[0] === 1 ? $stat[1] : $stat[2];
}
