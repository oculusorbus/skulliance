<?php
include_once 'db.php';
include 'webhooks.php';

if(isset($argv)){
parse_str(implode('&', array_slice($argv, 1)), $_GET);
}

if(isset($_GET['rewards'])){
	set_time_limit(0);
	// Get project percentages for Diamond Skull delegations
	$percentages = array();
	$percentages = getProjectDelegationPercentages($conn);
	// Determine whether Diamond Skulls should get a bonus
	$diamond_skull_bonus = getDiamondSkullBonus($percentages);
	// Deploy rewards for all users of the platform
	updateBalances($conn, $diamond_skull_bonus);
	// Deploy rewards for Diamond Skull delegation
	deployDiamondSkullRewards($conn, $percentages);
}

if(isset($_GET['missions'])){
	checkMissionsLeaderboard($conn, false, true);
}
if(isset($_GET['streaks'])){
	checkStreaksLeaderboard($conn, false, true);
}
if(isset($_GET['raids'])){
	checkRaidsLeaderboard($conn, false, true);
}
if(isset($_GET['factions'])){
	checkFactionsLeaderboard($conn, false, true);
}
if(isset($_GET['swaps'])){
	checkSkullSwapsLeaderboard($conn, false, true);
}
if(isset($_GET['bosses'])){
	checkBossBattlesLeaderboard($conn, false, true);
}
if(isset($_GET['monstrocity'])){
	checkMonstrocityLeaderboard($conn, false, true);
}
if(isset($_GET['gauntlets'])){
	checkGauntletsLeaderboard($conn, false, true);
}
if(isset($_GET['cryptcrawl'])){
	checkCryptCrawlLeaderboard($conn, false, true);
}
if(isset($_GET['leaderboardsnapshot'])){
	// Refreshes the hub's champion cards. READ-ONLY as far as players are
	// concerned: it calls only display variants of each board, so nothing
	// pays out, resets a reward flag or posts to Discord. Safe to run as
	// often as you like -- hourly is plenty, and a stale card is a cosmetic
	// problem, not a payout one.
	set_time_limit(0);
	$snap = refreshLeaderboardSnapshots($conn);
	echo "leaderboard snapshots updated: " . $snap['updated'];
	if (!empty($snap['skipped'])) echo " | skipped: " . implode(', ', $snap['skipped']);
	echo "\n";
}
if(isset($_GET['skullracer'])){
	// Weekly, same cadence as Crypt Crawl -- needs its own crontab entry
	// hitting rewards.php?skullracer=1 once a week. Nothing in this
	// codebase schedules that itself, same as every other ?X=1 case here.
	//
	// TWO boards, ONE cron, ONE reset -- and the order of these three lines
	// is load-bearing. Both boards read the same reward=0 rows: 'race' ranks
	// on best total time, 'lap' on best single lap, each paying its own
	// 50,000 pool (so topping both is 100,000). resetSkullRacerRuns() flips
	// every one of those rows to reward=1, which is what closes the week --
	// so it MUST come after both payouts. It used to live inside
	// checkSkullRacerLeaderboard() itself; leaving it there would have meant
	// the race board paid, the reset fired, and the lap board then found an
	// empty set and silently paid nobody, week after week, looking exactly
	// like "nobody set a lap time".
	checkSkullRacerLeaderboard($conn, false, true, 'race');
	checkSkullRacerLeaderboard($conn, false, true, 'lap');
	resetSkullRacerRuns($conn);
}
if(isset($_GET['obscura'])){
	// Weekly -- needs its own crontab entry hitting rewards.php?obscura=1 once
	// a week, same as skullracer and cryptcrawl. Nothing here schedules it.
	//
	// The order matters for the same reason it does above: the payout reads
	// reward=0 rows, and resetObscuraRuns() is what flips them to reward=1.
	// Reset also ends every run and zeroes every active streak -- the period
	// ending is the one thing that breaks a streak, which is what makes each
	// week a fresh race rather than a permanent lead. It clears the stored
	// puzzle too; see resetObscuraRuns() for why that is not optional.
	checkObscuraLeaderboard($conn, false, true);
	resetObscuraRuns($conn);
}
if(isset($_GET['cryptconquest'])){
	// Monthly, not weekly -- needs its OWN crontab entry hitting
	// rewards.php?cryptconquest=1 once a month (e.g. midnight on the 1st),
	// separate from whatever schedule triggers ?cryptcrawl=1. Nothing in
	// this codebase schedules that itself -- see cryptconquest.md.
	checkCryptConquestLeaderboard($conn, false, true);
}
?>