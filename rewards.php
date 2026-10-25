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

/*
 * ================= THE PAYOUT JOBS =================
 *
 * One definition per board, in one place, so that a combined run and a
 * single-board run cannot execute different things. Every flag that
 * worked before still works: rewards.php?obscura=1 runs exactly the job
 * named 'obscura' below and nothing else.
 *
 * WHY THIS IS A LIST AND NOT FIFTEEN CRONTAB LINES. It was fifteen, and
 * each needed its own entry that nothing in this repo could schedule or
 * verify -- which is how DHC Fighters went a month without a payout
 * nobody noticed was missing. `weekly=true` and `monthly=true` run the
 * right set by definition.
 *
 * THE DANGER OF COMBINING THEM, stated plainly: none of these payouts is
 * idempotent. There is no rewarded flag on several of these tables, so
 * running a board twice pays everybody twice. A single combined run that
 * dies halfway therefore CANNOT simply be re-run. That is why each job
 * is isolated, why the runner prints exactly which ones completed, and
 * why it prints a resume command naming only the rest. Read the output.
 *
 * ORDER IS LOAD-BEARING inside two of these -- see the comments on
 * 'skullracer' and 'obscura'. The array order is the run order.
 */
$reward_jobs = array(

	// ---- WEEKLY ----
	'swaps'      => array('weekly', function ($conn) { checkSkullSwapsLeaderboard($conn, false, true); }),
	'bosses'     => array('weekly', function ($conn) { checkBossBattlesLeaderboard($conn, false, true); }),
	'gauntlets'  => array('weekly', function ($conn) { checkGauntletsLeaderboard($conn, false, true); }),
	'cryptcrawl' => array('weekly', function ($conn) { checkCryptCrawlLeaderboard($conn, false, true); }),

	// TWO boards, ONE job, ONE reset -- and the order of these three lines is
	// load-bearing. Both boards read the same reward=0 rows: 'race' ranks on
	// best total time, 'lap' on best single lap, each paying its own 50,000
	// pool (so topping both is 100,000). resetSkullRacerRuns() flips every one
	// of those rows to reward=1, which is what closes the week -- so it MUST
	// come after both payouts. It used to live inside
	// checkSkullRacerLeaderboard() itself; leaving it there would have meant
	// the race board paid, the reset fired, and the lap board then found an
	// empty set and silently paid nobody, week after week, looking exactly
	// like "nobody set a lap time".
	'skullracer' => array('weekly', function ($conn) {
		checkSkullRacerLeaderboard($conn, false, true, 'race');
		checkSkullRacerLeaderboard($conn, false, true, 'lap');
		resetSkullRacerRuns($conn);
	}),

	// Same reason the order matters above: the payout reads reward=0 rows and
	// resetObscuraRuns() is what flips them to reward=1. Reset also ends every
	// run and zeroes every active streak -- the period ending is the one thing
	// that breaks a streak, which is what makes each week a fresh race rather
	// than a permanent lead. It clears the stored puzzle too; see
	// resetObscuraRuns() for why that is not optional.
	'obscura'    => array('weekly', function ($conn) {
		checkObscuraLeaderboard($conn, false, true);
		resetObscuraRuns($conn);
	}),

	// THE BOSS WEEK HAS TO BE CLOSED BY SOMETHING. Until this runs, nobody
	// who fought last week has been paid their trait cache.
	//
	// SAFE LATE AND SAFE TWICE, unlike every other job in this file:
	// dhc_boss_payouts is keyed (week, user_id), so a second run inserts
	// nothing and pays nothing. That makes it the one job the resume
	// warning below does not apply to.
	//
	// It settles the week that just ENDED, computed from MySQL's clock
	// rather than PHP's -- the two are hours apart on this host, and on a
	// Monday morning that is the difference between closing last week and
	// closing the one that has just begun.
	// Ranked on damage per Fighter deployed rather than on total damage, for
	// the reason checkDHCBossesLeaderboard() documents: the harmonic curve
	// weights rank 1 at twice rank 2, so a volume board would compound the
	// largest roster every week.
	//
	// SEPARATE FROM THE SETTLE BELOW, deliberately, even though both are
	// weekly and both close the same week. They pay different currencies
	// from different tables, and the runner isolates jobs so that one
	// failing does not take the other with it -- which is the entire reason
	// this file is a list.
	'dhcbosses'      => array('weekly', function ($conn) { checkDHCBossesLeaderboard($conn, false, true); }),

	'dhcbosses-week' => array('weekly', function ($conn) {
		if (!function_exists('dhcb_settle') && is_file(__DIR__ . '/dhcbosses-lib.php'))
			require_once __DIR__ . '/dhcbosses-lib.php';
		if (!function_exists('dhcb_settle')) { echo "DHC Bosses not installed.\n"; return; }
		/*
		 * THE WEEK THAT JUST CLOSED, AND IT IS A THURSDAY.
		 *
		 * This asked MySQL for last Monday, which is not a date any row in
		 * dhc_boss_runs carries -- the platform week turns 4pm Thursday and
		 * `day` holds that Thursday. Settling against a Monday would have
		 * found no participants, paid nobody, and reported a clean run.
		 *
		 * skl_week_start_sql() is the one home for that rule, in MySQL's
		 * dialect because the clocks on this host disagree and `day` is
		 * written by the database. Seven days back from the current week's
		 * boundary IS the week that just ended -- the cron fires after the
		 * turn, so "now" is already inside the new week.
		 */
		require_once __DIR__ . '/lib/week-start.php';
		$r = $conn->query('SELECT DATE(DATE_SUB(' . skl_week_start_sql()
		                . ', INTERVAL 7 DAY)) AS w');
		$week = ($r && ($x = $r->fetch_assoc()))
		      ? $x['w'] : date('Y-m-d', strtotime('-7 days', strtotime(skl_week_key())));
		$res  = dhcb_settle($conn, $week, true);
		printf("DHC Bosses %s: %d players paid %d traits on the %s table (%d%% of the crew down)\n",
			$week, (int)$res['paid'], (int)$res['traits'], $res['table'],
			(int)round(100 * $res['progress']['pct']));
		// And stand this week's crew up now, so the first player of the
		// week is not the one who pays for the scaling query.
		dhcb_spawn($conn);
	}),

	// ---- MONTHLY ----
	'missions'      => array('monthly', function ($conn) { checkMissionsLeaderboard($conn, false, true); }),
	'streaks'       => array('monthly', function ($conn) { checkStreaksLeaderboard($conn, false, true); }),
	'raids'         => array('monthly', function ($conn) { checkRaidsLeaderboard($conn, false, true); }),
	'factions'      => array('monthly', function ($conn) { checkFactionsLeaderboard($conn, false, true); }),
	'monstrocity'   => array('monthly', function ($conn) { checkMonstrocityLeaderboard($conn, false, true); }),

	// checkGuardiansLeaderboard() pays out reward=0 rows and calls
	// resetGuardians() itself at the end, which is what flips them to
	// reward=1 -- so there is no second call to make here, unlike Obscura
	// where the run reset is a separate concern from the score reset.
	'guardians'     => array('monthly', function ($conn) { checkGuardiansLeaderboard($conn, false, true); }),
	'cryptconquest' => array('monthly', function ($conn) { checkCryptConquestLeaderboard($conn, false, true); }),

	// 100,000 CARBON down the board by rank. Settles the month that just
	// closed, windowed on NOW() - INTERVAL 1 MONTH at BOTH ends, so it is safe
	// to run late -- but not twice, because dhc_fighters carries no rewarded
	// flag to close.
	'dhcfighters'   => array('monthly', function ($conn) { checkDHCFightersLeaderboard($conn, false, true); }),

	// Settles the season that just closed by NAME rather than by a date range,
	// so equally safe late and equally unsafe twice.
	'dhcarena'      => array('monthly', function ($conn) { checkDHCArenaLeaderboard($conn, false, true); }),

);

/*
 * Run a set of jobs, isolated from each other, and say what happened.
 *
 * Isolation is the point: with fifteen separate crontab lines a broken
 * board took only itself down. Combined, one fatal would strand every
 * board after it -- so each is wrapped, and a failure is reported and
 * stepped over rather than ending the run.
 */
function run_reward_jobs($conn, $jobs, $cadence, $skip = array()) {
	/* CLI has no limit anyway; this is for the case where someone triggers
	   it over HTTP, where the web SAPI's does apply. */
	@set_time_limit(0);
	$done = array(); $failed = array(); $skipped = array();

	foreach ($jobs as $name => $def) {
		if ($def[0] !== $cadence) continue;
		if (in_array($name, $skip, true)) { $skipped[] = $name; continue; }
		$t0 = microtime(true);
		/* Unwind only as far as WE opened. `while (ob_get_level() > 0)` would
		   tear down buffers this function never created -- including the
		   caller's, which is how the manifest below can end up discarded by
		   the very failure it is meant to report. */
		$depth = ob_get_level();
		try {
			/* The boards echo their own markup. Swallow it -- cron mail
			   wants the manifest, not a leaderboard rendered as HTML. */
			ob_start();
			$def[1]($conn);
			while (ob_get_level() > $depth) ob_end_clean();
			$done[] = $name;
			printf("  %-14s ok      %5.1fs\n", $name, microtime(true) - $t0);
		} catch (Throwable $e) {
			while (ob_get_level() > $depth) ob_end_clean();
			$failed[] = $name;
			printf("  %-14s FAILED  %5.1fs  %s\n", $name, microtime(true) - $t0, $e->getMessage());
		}
	}

	echo "\n" . $cadence . ": " . count($done) . " paid";
	if ($skipped) echo ", " . count($skipped) . " skipped";
	if ($failed)  echo ", " . count($failed) . " FAILED";
	echo "\n";

	/*
	 * THE IMPORTANT LINE. These payouts are not idempotent -- re-running
	 * the whole cadence would pay the boards that already succeeded a
	 * second time. So if anything failed, print the command that runs
	 * ONLY what is left.
	 */
	if ($failed) {
		echo "\nDo NOT re-run the whole cadence -- the boards above marked ok would pay again.\n";
		echo "Retry only what failed:\n";
		foreach ($failed as $f) echo "  php rewards.php " . $f . "=true\n";
	}
	return array('done' => $done, 'failed' => $failed, 'skipped' => $skipped);
}

/* Individual flags, exactly as before: rewards.php?obscura=1 and
   rewards.php?obscura=true both run the one job. */
foreach ($reward_jobs as $rj_name => $rj_def) {
	if (isset($_GET[$rj_name])) { @set_time_limit(0); $rj_def[1]($conn); }
}

/*
 * The combined runs. `skip=a,b` resumes a partial run without paying the
 * boards that already went out.
 *
 *   php rewards.php weekly=true
 *   php rewards.php monthly=true
 *   php rewards.php monthly=true skip=missions,raids
 */
$rj_skip = isset($_GET['skip']) ? array_filter(array_map('trim', explode(',', (string)$_GET['skip']))) : array();
if (isset($_GET['weekly']))  run_reward_jobs($conn, $reward_jobs, 'weekly',  $rj_skip);
if (isset($_GET['monthly'])) run_reward_jobs($conn, $reward_jobs, 'monthly', $rj_skip);
/*
 * NO DAILY CADENCE. There was one for a few hours while DHC Bosses reset
 * nightly; it now settles weekly and rides the weekly run above, so there
 * is nothing left that wants a daily line. Note that dhcbosses-week is the
 * one job in this file that IS safe to re-run -- its payout row is keyed
 * on the week and the user.
 */

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
?>
