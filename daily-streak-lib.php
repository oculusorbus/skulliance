<?php
/*
 * daily-streak-lib.php — the daily reward ladder, and what a finished
 * streak looks like in the transactions table.
 *
 * WHY IT IS ITS OWN FILE. These three used to live in db.php, which
 * opens a database connection when you include it. activity-sources-lib.php
 * needs the streak marker and is deliberately connection-free, so pulling
 * db.php in to get it would have made a query-shape library depend on a
 * live connection. Required by db.php, so every existing caller is
 * unaffected.
 */

/*
 * WHICH TRANSACTION ROWS MEAN "FINISHED A 7-DAY STREAK".
 *
 * There is no flag for it. A completed streak is inferred from the
 * PAYOUT: bonus = 1 and amount = tier 7, because only the seventh day
 * pays that and only the daily reward logs bonus = 1 at all.
 *
 * FOUR PLACES WERE DOING IT WITH THE LITERAL 30 -- getStreaksTotal(),
 * two leaderboard queries, and the Activity wall -- which is why raising
 * the ladder had to come with this. At tier 7 = 3000 all four would have
 * quietly stopped counting new completions while still reporting the old
 * totals, which looks exactly like it still works.
 *
 * It returns every tier-7 value the platform has EVER paid, so the
 * history keeps counting. When the ladder moves again, add the outgoing
 * number to $past; never replace the list.
 */
function dailyStreakAmounts() {
	$tiers = getRewardTiers();
	/* 30 was tier 7 of the original 1/3/5/10/15/20/30 ladder, paid from
	   launch until the 100x raise. No value appears in both ladders, so
	   nothing is double-counted. */
	$past  = array(30);
	return array_values(array_unique(array_merge(array((int)$tiers[7]), $past)));
}

function dailyStreakAmountSql($col = 'amount') {
	return $col . ' IN (' . implode(',', array_map('intval', dailyStreakAmounts())) . ')';
}

/*
 * THE DAILY LADDER. 100x the original 1/3/5/10/15/20/30.
 *
 * WHY IT MOVED. The old ladder was built so the daily could not compete
 * with staking, and it worked: a perfect week paid 84 points, and
 * funding a Realm from dailies alone would have taken roughly nine
 * years. The cost of that caution was that the first claim a new player
 * ever makes said "+1", which is not a reward, it is a rounding error.
 *
 * THE CURVE IS UNCHANGED -- same shape, same back-loading, decimal point
 * moved twice -- because the shape is what makes a streak worth keeping.
 * Only the scale moved, and the Mine moved with it: see getMineRate().
 *
 * WHAT THIS IS CALIBRATED AGAINST, and the number to re-check if either
 * side ever changes: a perfect week is 8,400 points, which buys 4,200
 * Realms upgrade levels' worth at the 2x any-points rate
 * ($points_multiplier in skulliance.php). A Level 10 Mine now produces
 * 10,000 CARBON a night, which is 700 DIAMOND a week, which shatters
 * into 4,200 core points. The two are deliberately equal: a flawless
 * week of claiming is worth exactly the best Mine in the game, and the
 * Mine still costs 5,500 points to build.
 */
function getRewardTiers(){
	$reward_tiers = array();
	$reward_tiers[1] = 100;
	$reward_tiers[2] = 300;
	$reward_tiers[3] = 500;
	$reward_tiers[4] = 1000;
	$reward_tiers[5] = 1500;
	$reward_tiers[6] = 2000;
	$reward_tiers[7] = 3000;
	return $reward_tiers;
}
