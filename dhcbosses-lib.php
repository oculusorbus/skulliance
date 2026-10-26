<?php
/**
 * dhcbosses-lib.php — DHC Bosses. The daily crew everybody grinds at once.
 *
 * WHAT THIS IS. Three overpowered bosses stand up once a day with a SHARED
 * health bar each. Any player can send a Crew of Fighters at them, as many
 * times a day as the allowance permits, and every point of damage anybody
 * lands is subtracted from the same three bars. At the end of the day the
 * total damage the community dealt decides which trait table everybody who
 * took part rolls on, and dropping all three unlocks the best one.
 *
 * ============================================================================
 * THIS FILE IS AN OVERRIDE OF THE ARENA, NOT A SECOND COPY OF IT.
 * ============================================================================
 *
 * A boss run is an Arena battle whose opponents happen to be enormous. So:
 *
 *   the board, the matching, the bombs, the terrain, the defending AI, the
 *   extra-turn rules and the whole damage model  ->  dhcarena-engine.php
 *   the exchange of one player move for its replies  ->  dhca_turn()
 *   the player's roster, Fighter art, names, profiles  ->  dhca_crew()
 *   the trait award and its tier tables  ->  dhcf_award()
 *   the payout curve down a monthly board  ->  the same harmonic split
 *
 * None of that is reimplemented here. If somebody retunes a blast radius or
 * fixes the merged fx timeline, Bosses inherits it the same day, because
 * there is no second copy to remember.
 *
 * What this file genuinely owns is the four things the Arena has no concept
 * of: a health bar shared between players, a daily spawn and settle, a tier
 * chosen from how much the whole community managed, and a cooldown that is
 * deliberately NOT the Arena's.
 *
 * WHY THE COOLDOWN IS SEPARATE. If sending a Crew at the boss benched it for
 * ranked play, the boss would eat the Arena -- and the Arena's participation
 * is what the boss is scaled against, so it would quietly scale itself down
 * by cannibalising its own input. dhc_boss_fighters is its own table for
 * that reason and no other.
 */

require_once __DIR__ . '/dhcfighters-lib.php';
require_once __DIR__ . '/dhcarena-lib.php';
require_once __DIR__ . '/lib/week-start.php';

/* ---------- the dials -------------------------------------------------------
   Every one of these is a starting guess, deliberately. See dhcbosses.md: the
   scaling is meant to be FITTED from dhc_boss_runs once there is a month of
   it, and a curve derived today would be fitted to ten players' worth of
   noise. They are constants so the fitting changes one line. */
if (!defined('DHCB_CREW_SIZE'))   define('DHCB_CREW_SIZE', DHCA_CREW_SIZE);  // follow the Arena
/*
 * THREE A DAY, AND THE WEEK IS WHAT RESETS.
 *
 * The cadence and the allowance are separate levers and they fix different
 * things. The WEEK sets the drama: seven days of participation accumulate
 * against one crew, so it actually falls, and the fall is an event instead
 * of a nightly coin-flip. The DAILY CAP sets the fatigue: with the Arena's
 * six battles already in the day, twelve of the same board was a second
 * shift, and these two games are mechanically identical by design.
 *
 * Three also makes the cache below mean something. A player cannot spend
 * the week in one sitting, so the only route to a full cache is turning up
 * on seven separate days -- which is the behaviour this is for.
 */
if (!defined('DHCB_DAILY_RUNS'))  define('DHCB_DAILY_RUNS', 3);
/* How many days of the week can earn a slot. Seven is one per day. If
   mythics start feeling common, this is the dial -- see dhcbosses.md for
   the faucet arithmetic. */
if (!defined('DHCB_CACHE_MAX'))   define('DHCB_CACHE_MAX', 7);
if (!defined('DHCB_BENCH_H'))     define('DHCB_BENCH_H', 3);   // flat, after any run
if (!defined('DHCB_BENCH_KO_H'))  define('DHCB_BENCH_KO_H', 6);// extra if it fell

/*
 * How much harder a boss hits than a baseline Fighter. FLAT, not scaled with
 * the health budget: if power grew with participation too, a busy week would
 * make runs shorter as well as the bar longer, and the damage-per-run figure
 * the whole feature is tuned on would stop meaning anything.
 *
 * 1.4 IS MEASURED, NOT GUESSED. Twelve seeds played out by a best-move
 * player against each setting:
 *
 *     1.0  ->  17.6 turns, 1386 damage per run
 *     1.4  ->  11.7 turns,  953
 *     1.8  ->   8.6 turns,  715
 *     2.2  ->   8.2 turns,  672
 *
 * A ranked Arena battle runs about 15 turns at DHCA_HP_BASE. 2.2 shipped
 * first and gave eight-turn runs, which is not a fight, it is an ambush.
 * 1.4 is shorter and nastier than the Arena without being over before the
 * board has been read once.
 */
if (!defined('DHCB_POW_MULT'))    define('DHCB_POW_MULT', 1.4);

/* What one run takes off the crew, from the same measurement. The health
   budget is built from this, so if the engine is retuned, re-run the sweep
   in dhcbosses-harness.php and move this with it. */
if (!defined('DHCB_DMG_PER_RUN'))  define('DHCB_DMG_PER_RUN', 950);
/* What share of the theoretically available runs actually get played. Nobody
   spends every allowance every day; at 1.0 the crew would survive every day
   and the feature would feel pointless. */
if (!defined('DHCB_TARGET_PCT'))   define('DHCB_TARGET_PCT', 0.60);
/*
 * WAVES.
 *
 * THE CREW IS SCALED TO FALL MID-WEEK, AND THAT IS NOT AN ACCIDENT: health is
 * DHCB_TARGET_PCT of what the week can deliver, so days-to-clear is
 * 7 x 0.60 = 4.2 whatever the community size, capped at 7 x 0.85 = 6.0 when
 * the reach clamp binds. The crew SURVIVING to Thursday is the unusual case.
 *
 * The first design had nothing for that. dhcb_entry_block() refused with "the
 * crew is down for today, they return tomorrow" -- which closed the feature
 * for the rest of the week and was a lie about when they came back. Worse,
 * the cache pays one trait per day attended, so a clear on day four capped
 * everybody at four of seven with no way to fix it, on the one mechanic that
 * exists to bring people back daily.
 *
 * So they get back up. A downed crew stands again within the same week, one
 * wave higher, and the week's question changes from "can we drop them" to
 * "how many times". Two rules make that safe:
 *
 *   THE TIER IS STICKY. The moment wave 1 falls the week is locked on the
 *   best trait table and no later wave can take it back -- see
 *   dhcb_tier_table(). A fresh crew at full health must never read as
 *   "nobody has touched them", which would downgrade the whole community for
 *   winning.
 *
 *   EACH WAVE IS BUDGETED AGAINST THE DAYS THAT ARE LEFT, not a flat
 *   multiplier on the first. A 1.5x wave 2 with 2.8 days left would need 6.3
 *   days to shift and would be a wall nobody dents -- dead content again,
 *   just later. Sized to the remaining time, waves arrive faster as Thursday
 *   approaches, which is the right shape anyway.
 *
 * HARDER MEANS ANGRIER, NOT FATTER. Each wave multiplies boss POWER, so runs
 * end sooner and cost more per point of damage. Health does the pacing; power
 * does the threat.
 */
if (!defined('DHCB_WAVE_POW'))     define('DHCB_WAVE_POW', 1.18);
/* No wave is smaller than this share of a day's damage, so a crew standing up
   on the last block is still worth a run for everybody rather than being
   clamped to nothing by the first two players who reach it. */
if (!defined('DHCB_WAVE_MIN_DAY')) define('DHCB_WAVE_MIN_DAY', 0.60);

/*
 * WHAT A RUN ACTUALLY DEALS, WHICH IS NOT WHAT THE HARNESS MEASURES.
 *
 * DHCB_DMG_PER_RUN was measured by playing the engine with a greedy search
 * over all 112 slides every turn, and it is a correct measurement of THAT:
 * ~1,000 damage a run. No person plays that way. The first real run on the
 * live site, by a player who knows the game well, landed 535 -- a little
 * over half -- and a deliberately weak model of play lands about 200.
 *
 * The health budget was built from the solver's figure, which made the crew
 * roughly 1.8x too big: 7.5 days to clear instead of 4.2. It would never
 * have fallen, so nobody would have seen a wave 2 or reached the best trait
 * table, and nothing would have errored. The owner caught it in one run.
 *
 * So the constant stays what it is -- the engine's ceiling, which is the
 * right thing for the harness to re-measure and catch drift against -- and
 * the BUDGET uses dhcb_dmg_per_run() instead: the community's own observed
 * figure, read off the ledger of real runs, with this haircut as the
 * fallback until there is enough of it.
 */
if (!defined('DHCB_HUMAN_PCT'))   define('DHCB_HUMAN_PCT', 0.55);
/* How many finished runs before the ledger is trusted over the fallback. */
if (!defined('DHCB_CAL_MIN_RUNS')) define('DHCB_CAL_MIN_RUNS', 20);
/* Floors and ceilings, so a quiet week is not trivial and a surge is not
   impossible. Both are on the CREW total, before the per-boss split. */
/*
 * 24,000 WAS SET WHEN THE BUDGET WAS FICTION. Back then the run count came
 * from headcount x allowance and the damage from a solver, so the floor only
 * ever bound for a tiny community. Now that both numbers track behaviour,
 * a real ten-player week budgets to about 17,000 -- and a 24,000 floor would
 * be doing the sizing instead of the budget, which is the whole thing this
 * was supposed to stop.
 *
 * Its job is narrower than it looks: keep a crew from being a one-sitting
 * pushover if the measurements ever come back near zero. Eight Crews' worth
 * is enough for that, and the reach clamp still cuts it further when even
 * that is out of range.
 */
if (!defined('DHCB_HP_MIN'))      define('DHCB_HP_MIN', 4000);
if (!defined('DHCB_HP_MAX'))      define('DHCB_HP_MAX', 1200000);
/* Who counts as "likely to show up": anybody who played the Arena or the
   Bosses in this window. Lagging on purpose -- a live count would mean a
   player joining today made today's boss harder for everyone already in it. */
if (!defined('DHCB_ACTIVE_DAYS')) define('DHCB_ACTIVE_DAYS', 14);

/** Monthly ladder key, same shape and meaning as the Arena season. */
function dhcb_season() { return date('Y-m'); }

/*
 * dhcb_day() IS GONE, AND ON PURPOSE.
 *
 * It returned CURDATE(), from the first draft where the crew stood up and
 * fell every day. The feature is weekly now and EVERY `day` column in this
 * schema holds the Thursday the week began -- so a default of "today" was a
 * date nothing is keyed on for six days out of seven.
 *
 * Three callers had it. The admin report showed "no crew has stood up for
 * 2026-10-25" while the crew was standing perfectly well under 2026-10-22;
 * dhcb_settle() would have settled a day with no participants on it and
 * reported a clean run paying nobody; dhcb_runs_report() would have shown an
 * empty week. All three now default to dhcb_week().
 *
 * It is DELETED rather than corrected because a function called dhcb_day()
 * that returns a Thursday is the same trap wearing a fix.
 */

/**
 * THE WEEK THE CREW BELONGS TO, keyed on the date of its Thursday.
 *
 * 4PM THURSDAY, AMERICA/CHICAGO, which is where every weekly game on this
 * platform turns -- Skull Swap, Boss Battles, Gauntlets, Crypt Crawl,
 * Obscura, Skull Racer and Drop Ship all lock their week there. Most of
 * them never compute it, because they select WHERE reward = 0 and the
 * crontab line IS the boundary; this one needs the date, because the crew
 * is keyed on the week and the trait cache counts blocks within it.
 *
 * ASKED OF MYSQL, not of PHP, because started_at is written by MySQL's
 * NOW() and the two clocks on this host do not agree. The rule itself
 * lives in lib/week-start.php in both dialects.
 *
 * dhc_boss_day.day therefore holds a THURSDAY. The column name is a
 * leftover from the daily draft and is not worth a migration for a word.
 */
function dhcb_week($conn) {
	$r = @$conn->query('SELECT ' . skl_week_key_sql() . ' AS w');
	if ($r && ($row = $r->fetch_assoc())) return $row['w'];
	return skl_week_key();                     // only if the database is gone
}

/** Which 24-hour block of the week we are in now, 0 to 6. */
function dhcb_week_day($conn) {
	$r = @$conn->query('SELECT ' . skl_week_day_sql('NOW()') . ' AS d');
	if ($r && ($row = $r->fetch_assoc())) return (int)$row['d'];
	return 0;
}

/* ---------- the crew --------------------------------------------------------
   Three bosses, in the order they stand on the board: front, mid, back. The
   art is the flat collection piece, NOT a trait assembly, which is the one
   place a boss is not simply a Fighter -- see dhcb_art() and layerImgs() in
   the Arena client, where a single `art` field short-circuits the layer
   stack.

   hp/pow are shares of the crew budget and of the baseline power, shaped by
   what the art plainly is: the armoured squad is a wall, the reaper is quick
   and vicious, the mech sits between them. Same total overpoweredness, three
   different fights -- which is what stops the daily boss being one fight with
   three pictures. */
/**
 * How much harder wave N hits than wave 1. Compounding, so the step is the
 * same size every time and the whole curve reads off one constant.
 */
/**
 * The damage one Crew lands, from the ledger of real runs.
 *
 * SELF-CORRECTING, because the alternative is a constant that is right for
 * one community on one day. dhc_boss_runs is append-only and every finished
 * run has its `dealt` on it, so the number the health budget is built from
 * can be the one this community actually produces -- a sharper crowd gets a
 * bigger boss, a quieter one gets a smaller boss, and nobody has to retune
 * anything by hand.
 *
 * CLAMPED TO A BAND, because the feedback loop runs both ways. Damage is
 * capped at the health actually left on the bar, so runs that finish a crew
 * record less than they dealt -- and a sequence of those would drag the
 * average down, shrink the next budget, and shrink it again. The band stops
 * that becoming a spiral in either direction.
 *
 * Windowed to the last 28 days so a retuned engine is reflected within a
 * month rather than averaged against a year of a different game.
 */
function dhcb_dmg_per_run($conn) {
	/* NO MEMO. It is one indexed aggregate and the only caller is
	   dhcb_crew_budget(), which only runs when a wave is actually being
	   stood up -- at most once a request, behind dhcb_spawn()'s own memo. A
	   static here would buy nothing and would make the two branches below
	   untestable in one process, which is how the figure it replaced went
	   un-checked in the first place. */
	$fallback = DHCB_DMG_PER_RUN * DHCB_HUMAN_PCT;
	$lo = DHCB_DMG_PER_RUN * 0.25;
	$hi = DHCB_DMG_PER_RUN * 1.10;
	$r = @$conn->query("SELECT COUNT(*) AS n, AVG(dealt) AS d
	                      FROM dhc_boss_runs
	                     WHERE outcome <> 0 AND dealt > 0
	                       AND started_at > DATE_SUB(NOW(), INTERVAL 28 DAY)");
	if ($r && ($x = $r->fetch_assoc()) && (int)$x['n'] >= (int)DHCB_CAL_MIN_RUNS)
		return max($lo, min($hi, (float)$x['d']));
	return max($lo, min($hi, $fallback));
}

/**
 * How many Crews a day this community actually sends.
 *
 * THE ASSUMPTION THIS REPLACES. The budget counted everybody who had fought
 * once in the window and assumed each of them would send the full allowance
 * every day for seven days. The Arena ladder says what that is worth: of ten
 * players counted equally, the top three are 83% of all activity, and the
 * bottom five average under a tenth of their allowance. Ten players x three
 * Crews x seven days is 210 runs; their behaviour says about 55. The crew
 * was 3.8x too big on this axis alone, and the reach clamp could not catch
 * it because the clamp is built from the same number.
 *
 * TWO SOURCES, BEST FIRST.
 *
 *   THE BOSS LEDGER, once there is enough of it. Runs per elapsed day is
 *   the thing itself -- no model, no conversion.
 *   OTHERWISE THE ARENA, converted per player rather than per head: what
 *   share of their six daily battles somebody actually uses is the best
 *   available guess at what share of their three Crews they will send, and
 *   it is capped at the allowance so a heavy Arena player cannot imply more
 *   boss runs than the rules permit.
 *
 * Elapsed days, never ACTIVE days: a community that plays three days a week
 * would otherwise look twice as busy as it is.
 */
function dhcb_runs_per_day($conn) {
	$d    = max(1, (int)DHCB_ACTIVE_DAYS);
	$cap  = (int)DHCB_DAILY_RUNS;
	$amax = defined('DHCA_DAILY_BATTLES') ? (int)DHCA_DAILY_BATTLES : 6;

	$r = @$conn->query("SELECT COUNT(*) AS n,
	                           LEAST($d, DATEDIFF(NOW(), MIN(started_at)) + 1) AS span
	                      FROM dhc_boss_runs
	                     WHERE outcome <> 0
	                       AND started_at > DATE_SUB(NOW(), INTERVAL $d DAY)");
	if ($r && ($x = $r->fetch_assoc()) && (int)$x['n'] >= (int)DHCB_CAL_MIN_RUNS) {
		$span = max(1, (int)$x['span']);
		return max(0.0, (float)$x['n'] / $span);
	}

	/* Only players who still have Fighters can send anything. */
	$r = @$conn->query("SELECT SUM(LEAST($cap, act.n / $d / $amax * $cap)) AS rpd
	                      FROM (SELECT attacker_id AS uid, COUNT(*) AS n
	                              FROM dhc_arena_battles
	                             WHERE started_at > DATE_SUB(NOW(), INTERVAL $d DAY)
	                             GROUP BY attacker_id) act
	                      JOIN (SELECT DISTINCT user_id AS uid FROM dhc_fighters
	                             WHERE disassembled_at IS NULL) f ON f.uid = act.uid");
	if ($r && ($x = $r->fetch_assoc()) && $x['rpd'] !== null) return max(0.0, (float)$x['rpd']);
	return 0.0;
}

/**
 * The hue rotation, in degrees, for a wave's colourway.
 *
 * ONE SOURCE FOR THE SERIES. It is rendered in four places -- the panel's
 * CSS, the battle board's CSS, the X share card and the Discord embed --
 * and the last two are drawn in PHP because the picture has to leave the
 * browser. Two of them disagreeing would post a crew in a colour nobody
 * fought it in, which is the whole reason the colourway exists.
 *
 * EVEN FIFTHS, WALKED IN ONE DIRECTION, so the series reads as a sequence
 * rather than five unrelated colours. Wave 1 is deliberately 0: it is the
 * baseline the rest are read against, and a rotation on it would just be a
 * rotation. Past wave 5 it wraps rather than running out of colours.
 *
 * dhc-arena-card-harness.php checks the CSS literals in dhcarena.php
 * against this function, so the browser and the server cannot drift.
 */
function dhcb_wave_hue($wave) {
	$w = max(1, (int)$wave);
	return (($w - 1) % 5) * 72;
}

function dhcb_wave_pow($wave) {
	return pow((float)DHCB_WAVE_POW, max(1, (int)$wave) - 1);
}

function dhcb_bosses() {
	return array(
		array('key'=>'d34thbrngr', 'name'=>'D34THBRNGR', 'rank'=>0,
		      'hp'=>0.42, 'pow'=>0.80, 'kit'=>0, 'bg'=>'dark-explosion',
		      'blurb'=>'Three bodies, one wall. Slow, and it does not stop.'),
		array('key'=>'cip-666',    'name'=>'CIP-666',    'rank'=>1,
		      'hp'=>0.34, 'pow'=>1.00, 'kit'=>1, 'bg'=>'xlon-s-psi-attack',
		      'blurb'=>'The bruiser. Hits back hardest of the three.'),
		array('key'=>'x-k4t',      'name'=>'X-K4T',      'rank'=>2,
		      'hp'=>0.24, 'pow'=>1.35, 'kit'=>2, 'bg'=>'wormhole-passage',
		      'blurb'=>'Fast and vicious. Falls soonest, hurts most getting there.'),
	);
}

/** The flat art, which is why a boss is not assembled from traits. */
function dhcb_art($key) {
	return 'images/monstrocity/bosses/' . preg_replace('/[^a-z0-9-]/', '', (string)$key) . '.png';
}

function dhcb_boss($key) {
	foreach (dhcb_bosses() as $b) if ($b['key'] === $key) return $b;
	return null;
}

/* ---------- how big they are today ------------------------------------------ */

/**
 * The firepower the platform could field, which is what the crew is sized to.
 *
 * FIGHTERS, NOT PLAYERS. A player with twenty Fighters can send far more at a
 * boss than one with three, so headcount is the wrong unit -- and counting
 * Fighters means a thin turnout of committed players is not punished the way
 * a headcount would punish it.
 *
 * LAGGING, AND LOCKED AT SPAWN. Anybody active in the last DHCB_ACTIVE_DAYS
 * counts, measured once when the crew stands up. A live count would mean the
 * boss grew while people were hitting it, and that somebody joining made it
 * worse for everyone already in -- the scaling tax that makes players hope
 * their friends stay away.
 */
function dhcb_deployable($conn) {
	$d = max(1, (int)DHCB_ACTIVE_DAYS);

	/*
	 * A JOIN TO A DERIVED TABLE, NOT `IN (subquery)`.
	 *
	 * This was written as WHERE f.user_id IN (SELECT ... UNION SELECT ...)
	 * and it hung the Arena page. MySQL is entitled to treat an IN with a
	 * correlated-looking subquery as a DEPENDENT SUBQUERY and re-run it for
	 * every row of dhc_fighters -- thousands of scans of an ever-growing
	 * battle table, on the one page load of the week that happens to spawn
	 * the crew. A join to a derived table is materialised once.
	 *
	 * MAX_EXECUTION_TIME is the belt to that braces. This runs inside a
	 * page render, and no boss crew is worth a white screen: if the
	 * optimiser ever makes a different choice on a bigger table, the query
	 * is killed at two seconds and the caller falls back to the floor.
	 */
	$sql = "SELECT /*+ MAX_EXECUTION_TIME(2000) */
	               COUNT(*) AS fighters, COUNT(DISTINCT f.user_id) AS players
	          FROM dhc_fighters f
	          JOIN (SELECT DISTINCT attacker_id AS uid FROM dhc_arena_battles
	                 WHERE started_at > DATE_SUB(NOW(), INTERVAL $d DAY)
	                UNION
	                SELECT DISTINCT user_id AS uid FROM dhc_boss_runs
	                 WHERE started_at > DATE_SUB(NOW(), INTERVAL $d DAY)) act
	            ON act.uid = f.user_id
	         WHERE f.disassembled_at IS NULL";
	$r = @$conn->query($sql);
	if (!$r) {
		/* dhc_boss_runs will not exist until the migration is run, an older
		   dhc_fighters has no disassembled_at, and MySQL below 5.7 rejects
		   the hint. None of them is a reason to refuse to spawn a crew. */
		$r = @$conn->query("SELECT COUNT(*) AS fighters, COUNT(DISTINCT f.user_id) AS players
		                      FROM dhc_fighters f
		                      JOIN (SELECT DISTINCT attacker_id AS uid FROM dhc_arena_battles
		                             WHERE started_at > DATE_SUB(NOW(), INTERVAL $d DAY)) act
		                        ON act.uid = f.user_id");
	}
	if (!$r || !($row = $r->fetch_assoc())) return array('fighters'=>0, 'players'=>0);
	return array('fighters'=>max(0, (int)$row['fighters']), 'players'=>max(0, (int)$row['players']));
}

/**
 * Total shared health for the whole crew today, before the per-boss split.
 *
 * SIZED ON RUNS THAT CAN ACTUALLY BE PLAYED, not on the roster.
 *
 * The first version multiplied total Fighters by a per-Fighter figure, and
 * it was wrong in a way that only shows up with a deep collection: a player
 * with forty Fighters still cannot send more than DHCB_DAILY_RUNS Crews, so
 * thirty of those Fighters contribute nothing to how much damage the day can
 * possibly hold. Scaling on them makes the boss enormous because somebody
 * owns a lot of art.
 *
 * So the cap is the lower of the two real limits -- the allowance everybody
 * has, and the Fighters there are to field -- and the budget is that many
 * runs' worth of damage, discounted because nobody spends every allowance.
 */
function dhcb_crew_budget($conn, $days = 7, $wave = 1) {
	$days = max(0.5, (float)$days);
	$d = dhcb_deployable($conn);
	/*
	 * A WEEK OF RUNS, and the roster cap is per DAY rather than per week:
	 * a Fighter rests for hours, not days, so the same nine Fighters field
	 * three Crews every day of the week. Multiplying the roster cap by
	 * seven as well would double-count the same Fighters seven times over
	 * and produce a crew nobody could scratch.
	 */
	/*
	 * WHAT THEY WILL ACTUALLY SEND, not what they are allowed to send.
	 * dhcb_runs_per_day() reads behaviour; the roster is still a hard cap,
	 * because nine Fighters cannot field four Crews at once however keen
	 * their owner is.
	 */
	$byBehaviourDay = dhcb_runs_per_day($conn);
	$byRosterDay    = (int)floor($d['fighters'] / max(1, DHCB_CREW_SIZE));
	$runsPerDay     = min($byBehaviourDay, $byRosterDay);
	$runs           = $runsPerDay * $days;
	/*
	 * A LATER WAVE HITS HARDER, SO A RUN AGAINST IT DEALS LESS before the
	 * Crew is wiped. Budgeting it at wave 1's damage-per-run would overstate
	 * what the remaining days can deliver and leave a crew standing by
	 * arithmetic rather than by anyone's choice. Dividing by the power
	 * factor is rough -- the relationship is not exactly linear -- but it is
	 * the right direction, and the 0.60 target carries the slack.
	 */
	/* THE LEDGER'S FIGURE, NOT THE SOLVER'S -- see dhcb_dmg_per_run(). */
	$perRun = dhcb_dmg_per_run($conn) / max(0.01, dhcb_wave_pow($wave));
	$hp = (int)round($runs * $perRun * DHCB_TARGET_PCT);
	/*
	 * THE FLOOR IS WAVE 1'S ONLY. It exists so a quiet week is not trivial,
	 * and applying it to a crew standing up on the last block of the week
	 * would put a 24,000 wall in front of a day nobody can finish. A late
	 * wave is MEANT to be small -- that is the escalating pace -- and its
	 * own floor below keeps it worth a run for everybody.
	 */
	if ($wave <= 1 && $hp < DHCB_HP_MIN) $hp = DHCB_HP_MIN;
	else {
		$min = (int)round($runsPerDay * $perRun * DHCB_WAVE_MIN_DAY);
		if ($hp < $min) $hp = $min;
	}
	if ($hp > DHCB_HP_MAX) $hp = DHCB_HP_MAX;
	/*
	 * AND NEVER MORE THAN THE WEEK CAN PHYSICALLY DELIVER.
	 *
	 * The floor is there so a quiet week is not trivial, and it was ABOVE
	 * reach for a small community: three players with nine Fighters can
	 * field 21 runs all week, worth about 20,000 damage, against a 24,000
	 * floor. They could play perfectly, never miss a day, and still watch
	 * the crew survive every week -- the worst possible failure, because
	 * nothing on screen would explain it.
	 *
	 * 0.85 rather than 1.0 so a clear still needs most of the community
	 * showing up rather than literally everybody spending every run.
	 */
	$reach = (int)round($runs * $perRun * 0.85);
	if ($reach > 0 && $hp > $reach) $hp = $reach;
	return max(1, (int)$hp);
}

/**
 * Stand the crew up for today if it is not already standing.
 *
 * INSERT IGNORE on the (day, boss_key) key is the whole concurrency story:
 * the first page load of the day creates the rows and every other one that
 * arrives in the same second quietly does nothing. No lock, no race, and no
 * chance of two different health bars for the same boss.
 */
/**
 * The spawn memo, in a holder so it can be INVALIDATED.
 *
 * It used to be a plain static inside dhcb_spawn(), which was correct while
 * a wave could only change once a week: three callers per page load, one
 * scaling query. Waves broke that. dhcb_finish() can now drop the crew and
 * then read the bars back in the SAME request, and a memo nothing could
 * clear would have handed it the crew it just killed -- so the reply would
 * have shown a dead crew with no wave behind it, and the end card would
 * never have said the player finished them.
 */
function dhcb_spawn_memo($set = null, $clear = false) {
	static $v = null;
	if ($clear)        { $v = null; return null; }
	if ($set !== null) { $v = $set; }
	return $v;
}

function dhcb_spawn($conn) {
	/*
	 * ONCE PER REQUEST. dhcb_state() spawns if it has no crew standing, and
	 * the page reaches dhcb_state() through three different callers -- so on
	 * the first load of a week the scaling query ran three times before
	 * anything rendered, and that load is already the slowest of the week.
	 *
	 * The memo is keyed on nothing because a request cannot outlive a wave:
	 * the only thing that stands a new wave up is a run finishing, and that
	 * is a different request from the one that reads the result.
	 */
	$done = dhcb_spawn_memo();
	if ($done !== null) return $done;
	$day = dhcb_week($conn);
	$esc = $conn->real_escape_string($day);
	$n   = count(dhcb_bosses());

	/*
	 * WHICH WAVE IS STANDING, AND IS IT STILL STANDING.
	 *
	 * One query answers both. The highest wave of the week is the current
	 * one by construction -- a wave only exists because the one before it
	 * fell -- and the sum of its health says whether it is down.
	 */
	$wave = 0; $left = 0; $rows = 0;
	$r = @$conn->query("SELECT wave, COUNT(*) AS n, SUM(GREATEST(health,0)) AS hp
	                      FROM dhc_boss_day WHERE day = '$esc'
	                     GROUP BY wave ORDER BY wave DESC LIMIT 1");
	if ($r && ($x = $r->fetch_assoc())) {
		$wave = (int)$x['wave']; $rows = (int)$x['n']; $left = (int)$x['hp'];
	}
	/* A wave is standing and has health on it: nothing to do. */
	if ($wave > 0 && $rows >= $n && $left > 0) return dhcb_spawn_memo($day);

	/*
	 * STAND THE NEXT ONE UP. Wave 1 gets the whole week; every wave after it
	 * gets only the days that are left, because it is competing for the same
	 * runs as the days remaining can supply. dhcb_week_day() is 0-based, so
	 * blocks remaining INCLUDING the one in progress is 7 - index.
	 */
	$next = $wave + 1;
	$daysLeft = ($next <= 1) ? 7 : max(0.5, 7 - dhcb_week_day($conn));
	$budget = dhcb_crew_budget($conn, $daysLeft, $next);

	$ins = array();
	foreach (dhcb_bosses() as $b) {
		$hp = max(1, (int)round($budget * $b['hp']));
		$ins[] = sprintf("('%s',%d,'%s',%d,%d,%.3f,NOW())",
			$esc, $next, $conn->real_escape_string($b['key']), $hp, $hp,
			$budget / max(1, (float)DHCB_HP_MIN));   // how far above the floor it spawned
	}
	/*
	 * INSERT IGNORE on (day, wave, boss_key) is the whole concurrency story:
	 * two runs that finish in the same second both see the wave down, both
	 * try to raise wave N+1, and the second one quietly does nothing. No
	 * lock, no race, and no chance of two different health bars for the same
	 * boss -- which matters far more now than it did when a wave could only
	 * appear once a week.
	 */
	@$conn->query("INSERT IGNORE INTO dhc_boss_day
	               (day, wave, boss_key, max_health, health, scale, created_at)
	               VALUES " . implode(',', $ins));
	return dhcb_spawn_memo($day);
}

/** The wave standing this week, 1 if the crew has never been dropped. */
function dhcb_wave($conn, $day = null) {
	if ($day === null) $day = dhcb_week($conn);
	$r = @$conn->query("SELECT MAX(wave) AS w FROM dhc_boss_day
	                     WHERE day = '" . $conn->real_escape_string($day) . "'");
	if ($r && ($x = $r->fetch_assoc()) && $x['w'] !== null) return max(1, (int)$x['w']);
	return 1;
}

/**
 * Today's crew with its live health, in board order.
 *
 * Returns array keyed by boss_key: key, name, rank, max_health, health, dead,
 * blurb, art. Empty if the table is missing, which every caller treats as
 * "the feature is not migrated yet" rather than as an error.
 */
function dhcb_state($conn, $day = null) {
	if ($day === null) $day = dhcb_spawn($conn);
	$esc = $conn->real_escape_string($day);
	/* THE STANDING WAVE, which is the highest one on the week -- a wave only
	   exists because the one before it fell. Reading every wave and summing
	   would show a crew at a health nobody is fighting. */
	$r = @$conn->query("SELECT boss_key, wave, max_health, health, killed_at, killed_by
	                      FROM dhc_boss_day
	                     WHERE day = '$esc'
	                       AND wave = (SELECT MAX(wave) FROM dhc_boss_day WHERE day = '$esc')");
	if (!$r) return array();
	$have = array();
	while ($x = $r->fetch_assoc()) $have[$x['boss_key']] = $x;
	$out = array();
	foreach (dhcb_bosses() as $b) {
		if (!isset($have[$b['key']])) continue;
		$h = $have[$b['key']];
		$out[$b['key']] = array(
			'key'=>$b['key'], 'name'=>$b['name'], 'rank'=>$b['rank'],
			'blurb'=>$b['blurb'], 'art'=>dhcb_art($b['key']),
			'max_health'=>(int)$h['max_health'], 'health'=>(int)$h['health'],
			'wave'=>max(1, (int)$h['wave']),
			'dead'=>((int)$h['health'] <= 0),
			'killed_at'=>$h['killed_at'], 'killed_by'=>(int)$h['killed_by'],
		);
	}
	return $out;
}

/** Aggregate progress across the crew: the number the tier is chosen from. */
function dhcb_progress($state) {
	$max = 0; $left = 0; $dead = 0; $wave = 1;
	foreach ($state as $s) {
		$max += $s['max_health']; $left += $s['health']; if ($s['dead']) $dead++;
		if (isset($s['wave'])) $wave = max($wave, (int)$s['wave']);
	}
	$dealt   = max(0, $max - $left);
	$cleared = ($max > 0 && $left <= 0);
	return array(
		'max'=>$max, 'left'=>$left, 'dealt'=>$dealt,
		'pct'=> $max > 0 ? ($dealt / $max) : 0.0,
		'dead'=>$dead, 'total'=>count($state),
		/* THE WAVE ON THE BOARD, and how many the week has already put down.
		   A wave only exists because the one before it fell, so wave - 1 IS
		   the count of crews dropped -- no second query, and no counter that
		   can drift away from the rows it describes. */
		'wave'=>$wave,
		'downed'=>($wave - 1) + ($cleared ? 1 : 0),
		/* `cleared` is about the crew ON THE BOARD, which is what the end
		   card and the entry gate ask about. The week's achievement is
		   `downed`, and that is what the trait table reads. */
		'cleared'=>$cleared,
	);
}

/* ---------- the player's side ----------------------------------------------- */

/**
 * The player's roster, with the BOSS cooldown overlaid on the Arena's.
 *
 * dhca_crew() does all the real work -- Fighter rows, traits, display names,
 * profiles, the whole decoration -- and is called rather than copied so a
 * Fighter looks identical in both places. Only `available`, `bench_left` and
 * `fell` are overwritten, from dhc_boss_fighters, because a Fighter resting
 * from the Arena is perfectly free to fight a boss and the other way round.
 *
 * The remaining time is asked of MySQL for the same reason the Arena asks it
 * of MySQL: the two clocks on this host do not agree and a cooldown compared
 * across them is hours out.
 */
function dhcb_roster($conn, $user_id, $crew = null) {
	$uid  = (int)$user_id;
	/*
	 * THE CALLER MAY ALREADY HAVE THE ROSTER. dhca_crew() is not cheap -- it
	 * joins the Fighter table and then decorates every row with
	 * dhca_crew_profiles(), which is a second pass over the battle ledger --
	 * and dhcarena.php has already paid for it before it gets here. Asking
	 * again doubled the most expensive query on the page for nothing.
	 */
	if ($crew === null) $crew = dhca_crew($conn, $uid);
	if (!$crew) return $crew;

	$bench = array();
	$r = @$conn->query("SELECT fighter_id, ko,
	                           GREATEST(0, COALESCE(TIMESTAMPDIFF(SECOND, NOW(), benched_until), 0)) AS left_s
	                      FROM dhc_boss_fighters WHERE user_id = $uid");
	if ($r) while ($x = $r->fetch_assoc()) $bench[(int)$x['fighter_id']] = $x;

	foreach ($crew as $i => $f) {
		$fid  = (int)$f['id'];
		$left = isset($bench[$fid]) ? (int)$bench[$fid]['left_s'] : 0;
		$crew[$i]['available']  = ($left <= 0);
		$crew[$i]['bench_left'] = $left;
		$crew[$i]['fell']       = isset($bench[$fid]) ? !empty($bench[$fid]['ko']) : false;
	}
	return $crew;
}

/**
 * Runs started TODAY, which is the allowance. Counted, never stored.
 *
 * dhc_boss_runs.day holds the WEEK now, so the day has to come from
 * started_at -- counting the week's rows here would let one sitting spend
 * the entire week, which is the thing the daily cap exists to stop.
 */
function dhcb_runs_today($conn, $user_id) {
	/* The CURRENT BLOCK, not the calendar day. The week turns at 4pm on a
	   Thursday, so a midnight allowance would reset in the middle of day
	   seven and hand out a free extra slot every week. */
	$r = @$conn->query("SELECT COUNT(*) AS n FROM dhc_boss_runs
	                     WHERE user_id = " . (int)$user_id . "
	                       AND started_at >= " . skl_week_start_sql() . "
	                       AND " . skl_week_day_sql('started_at') . " = " . skl_week_day_sql('NOW()'));
	if (!$r || !($x = $r->fetch_assoc())) return 0;
	return (int)$x['n'];
}

/**
 * Which round of the week we are in, and how long is left of it.
 *
 * THE WORD "TODAY" WAS A LIE AND IT READ AS A BUG, TWICE. The allowance is
 * a 24-hour round measured from the week's 4pm Thursday boundary, not a
 * calendar day -- so a player who sent three Crews at 2pm on Sunday came
 * back at half past four, saw a fresh allowance, and reported that the page
 * was letting them exceed the cap. It was not: the round had turned 38
 * minutes earlier. Renaming it from "today" to "this round" was not enough
 * on its own, because a boundary nobody can see is still a boundary nobody
 * can see.
 *
 * So the page shows the clock. The owner's call, and the right one: the
 * mechanic is sound and what it needed was to be visible, not different.
 *
 * ONE QUERY FOR BOTH, because they are the same arithmetic -- the round is
 * the whole part of the elapsed time and the remainder is what is left --
 * and because MySQL has to answer it. started_at is written by the database
 * and the two clocks on this host disagree, which is the same hazard the
 * cooldowns are computed in SQL for.
 */
function dhcb_round($conn) {
	$w = skl_week_start_sql();
	$r = @$conn->query("SELECT LEAST(6, GREATEST(0, FLOOR(TIMESTAMPDIFF(SECOND, $w, NOW()) / 86400))) AS n,
	                           86400 - MOD(TIMESTAMPDIFF(SECOND, $w, NOW()), 86400) AS s");
	if (!$r || !($x = $r->fetch_assoc())) return array('n' => 1, 'left' => 0);
	return array(
		'n'    => min(7, max(1, (int)$x['n'] + 1)),          // 1-based, for reading
		'left' => max(0, min(86400, (int)$x['s'])),
	);
}

/**
 * HOW MANY SEPARATE DAYS THIS PLAYER HAS SHOWN UP THIS WEEK.
 *
 * This is the trait cache. One slot per day with a real run on it, capped
 * at DHCB_CACHE_MAX -- so the cache is a record of turning up rather than
 * of grinding, and with the daily cap at three there is no way to fill it
 * except by coming back.
 *
 * DISTINCT DATE, not a count of runs: three runs on Monday is one day.
 */
function dhcb_cache_days($conn, $user_id, $week = null) {
	if ($week === null) $week = dhcb_week($conn);
	$r = @$conn->query("SELECT COUNT(DISTINCT " . skl_week_day_sql('started_at') . ") AS n
	                      FROM dhc_boss_runs
	                     WHERE user_id = " . (int)$user_id . "
	                       AND day = '" . $conn->real_escape_string($week) . "'
	                       AND outcome <> 0 AND dealt >= " . (int)DHCB_MIN_DEALT);
	if (!$r || !($x = $r->fetch_assoc())) return 0;
	return min((int)DHCB_CACHE_MAX, (int)$x['n']);
}

/** A run left unfinished, if any. Resumed rather than abandoned. */
function dhcb_open_run($conn, $user_id) {
	$r = @$conn->query("SELECT s.run_id FROM dhc_boss_state s
	                     INNER JOIN dhc_boss_runs r ON r.id = s.run_id
	                     WHERE s.user_id = " . (int)$user_id . " AND r.outcome = 0
	                     ORDER BY s.run_id DESC LIMIT 1");
	if (!$r || !$r->num_rows) return 0;
	$x = $r->fetch_assoc();
	return (int)$x['run_id'];
}

/** Why this player cannot send a Crew right now, or '' if they can. */
function dhcb_entry_block($conn, $user_id, $crew = null) {
	$state = dhcb_state($conn);
	if (!$state) return 'The Bosses are not up yet.';
	$p = dhcb_progress($state);
	/*
	 * NO REFUSAL FOR A DOWNED CREW. This used to return "the crew is down for
	 * today, they return tomorrow", which closed the feature for the rest of
	 * the week and named the wrong day. They get back up now, and dhcb_state()
	 * above has already stood the next wave if the last one fell -- so by the
	 * time anybody reads this there is something to fight. The only way
	 * `cleared` survives to here is a database that refused the insert, and a
	 * truthful "try again shortly" beats a lie about tomorrow.
	 */
	if ($p['cleared']) return 'The crew is on its way back up. Try again in a moment.';
	if (dhcb_open_run($conn, $user_id)) return '';          // resume is always allowed
	if (dhcb_runs_today($conn, $user_id) >= DHCB_DAILY_RUNS) {
		/* NAME THE RESET, do not say "tomorrow". The allowance turns at 4pm
		   CT, so for most of the afternoon "tomorrow" is wrong by hours and
		   in the wrong direction -- it reads as a longer wait than it is. */
		$rd   = dhcb_round($conn);
		$left = $rd['left'];
		return 'You have sent all ' . DHCB_DAILY_RUNS . ' Crews for this round. '
		     . 'The next ' . DHCB_DAILY_RUNS . ' land at 4pm CT'
		     . ($left > 0 ? ', in ' . dhca_hms($left) : '')
		     . ', along with another slot in the cache.';
	}
	$avail = 0;
	foreach (dhcb_roster($conn, $user_id, $crew) as $f) if ($f['available']) $avail++;
	if ($avail < DHCB_CREW_SIZE)
		return 'You need ' . DHCB_CREW_SIZE . ' rested Fighters. ' . $avail . ' ready.';
	return '';
}

/* ---------- starting a run --------------------------------------------------- */

/**
 * Send a Crew at the boss crew.
 *
 * THE BOSSES ARE THE THREE FOES, which is why none of the Arena's targeting,
 * rank or board code needed changing: the engine already expects exactly
 * three opponents in three ranks, and a boss simply occupies one of those
 * slots with a very large number in it.
 *
 * EACH BOSS ENTERS AT ITS SHARED HEALTH, so a boss the community has nearly
 * finished can actually be dropped in your run, and one at full health
 * cannot. That is the grind working as intended: most runs end with your
 * Crew wiped and the bar a little lower, and that run still pays, because
 * the day's reward is decided by damage rather than by kills.
 *
 * A SNAPSHOT, NOT A LIVE READ. The run is fought against the health the
 * bosses had when it started; what other players do meanwhile is reconciled
 * once, at the end, in SQL. Two people fighting the same boss at the same
 * time therefore never see each other's damage mid-battle -- which is
 * correct, because a bar that jumped underneath you would make your own
 * contribution unreadable.
 */
function dhcb_start($conn, $user_id, $fighter_ids) {
	$uid   = (int)$user_id;
	/* Built once and threaded through: dhca_crew() underneath is the most
	   expensive query either game makes, and the gate and the validation
	   both need the same answer. */
	$roster = dhcb_roster($conn, $uid);
	$block  = dhcb_entry_block($conn, $uid, $roster);
	if ($block) return array(false, $block, null);

	$day   = dhcb_spawn($conn);
	$state = dhcb_state($conn, $day);
	if (!$state) return array(false, 'The Bosses are not up yet.', null);
	$byId = array(); foreach ($roster as $f) $byId[(int)$f['id']] = $f;
	$mine = array();
	foreach ($fighter_ids as $fid) {
		$fid = (int)$fid;
		if (!isset($byId[$fid]))       return array(false, 'That is not your Fighter.', null);
		if (!$byId[$fid]['available']) return array(false, $byId[$fid]['display'] . ' is still '
			. (!empty($byId[$fid]['fell']) ? 'resurrecting' : 'recovering') . '.', null);
		$mine[] = $byId[$fid];
	}
	if (count($mine) !== DHCB_CREW_SIZE) return array(false, 'Pick ' . DHCB_CREW_SIZE . ' Fighters.', null);

	/* Three empty trait sets: a boss's numbers are assigned below rather than
	   derived from traits, and its picture is flat art. Passing empty slots
	   means dhca_build_fighter() returns the clean baseline to scale from,
	   with no rarity multiplier and no per-slug variance smuggled in. */
	$order = array_values(dhcb_bosses());
	/*
	 * THE BOSSES BRING THEIR OWN GROUND, AND THIS WAS A REAL BUG.
	 *
	 * They were sent in with EMPTY trait sets, because a boss is a painted
	 * piece rather than an assembly. But dhca_new_battle() reads the arena
	 * off the defender's front-rank BACKGROUND -- so every boss run resolved
	 * to '' -> dhca_terrain_for('') -> 'frail', the "unrecognised field is a
	 * bare one" fallback.
	 *
	 * Frail takes 8% off everybody's max health. dhcb_start() then overwrites
	 * the foes' health with the shared bar, which undoes it for the BOSSES
	 * and leaves it on the player. Every run ever played was eight per cent
	 * down on one side only, silently, and the board said "Unknown Ground"
	 * because there was no ground.
	 *
	 * So each boss carries a real background slug from the collection, and
	 * the front rank sets the arena exactly as it does in a ranked battle --
	 * no special case, no override, the same rule reading a real value. The
	 * slugs are chosen from what the art actually shows: a dark field with a
	 * red blast behind D34THBRNGR, the radiating spikes behind CIP-666, the
	 * drifting planets behind X-K4T.
	 *
	 * Nothing else about the boss survives dhca_new_battle() -- health,
	 * power, kit and art are all overwritten below -- so the background is
	 * doing one job.
	 */
	$foeTraits = array(); $foeNames = array();
	foreach ($order as $b) {
		$foeTraits[] = !empty($b['bg']) ? array('background' => $b['bg']) : array();
		$foeNames[]  = $b['name'];
	}

	$seed = random_int(1, 0x7FFFFFFE);
	$bat  = dhca_new_battle(
		array_map(function ($f) { return $f['traits']; }, $mine),
		$foeTraits,
		array('mine' => array_map(function ($f) { return $f['display']; }, $mine),
		      'foes' => $foeNames),
		dhcf_rarity(), $seed);

	$kits = dhca_kits();
	/* THE WAVE THIS RUN IS FOUGHT AGAINST, read off the crew that is actually
	   standing. Every boss in a wave carries the same number, so the first one
	   answers for all three. */
	$wave = 1;
	foreach ($state as $sv) if (isset($sv['wave'])) { $wave = max(1, (int)$sv['wave']); break; }
	$wpow = dhcb_wave_pow($wave);
	foreach ($order as $i => $b) {
		$live = isset($state[$b['key']]) ? $state[$b['key']] : null;
		if (!$live) continue;
		$hp = (int)$live['health'];
		$bat['foes'][$i]['bossKey'] = $b['key'];
		$bat['foes'][$i]['art']     = dhcb_art($b['key']);
		$bat['foes'][$i]['maxHp']   = max(1, $hp);
		$bat['foes'][$i]['hp']      = max(0, $hp);
		$bat['foes'][$i]['ko']      = ($hp <= 0);
		/* ANGRIER EACH WAVE, not fatter. Health does the pacing -- it is
		   budgeted against the days left -- and power does the threat, so a
		   later crew ends runs sooner and costs more per point of damage. */
		$bat['foes'][$i]['power']   = (int)round(DHCA_POWER_BASE * DHCB_POW_MULT * $b['pow'] * $wpow);
		$bat['foes'][$i]['kit']     = $kits[$b['kit'] % count($kits)];
		/* The snapshot each boss entered on. dhcb_finish() subtracts the
		   remainder from this to learn what this run actually dealt -- it
		   cannot read it off the shared row, which other runs have moved. */
		$bat['foes'][$i]['hp0']     = max(0, $hp);
	}

	$bat['meta'] = array(
		'attacker' => $uid,
		'day'      => $day,
		'mineIds'  => array_map(function ($f) { return (int)$f['id']; }, $mine),
		'bossKeys' => array_map(function ($b) { return $b['key']; }, $order),
		'wave'     => $wave,
		'moves'    => array(),
		'boss'     => true,
	);

	/* THE WAVE IS RECORDED ON THE RUN, not derived later. A run is fought
	   against the crew that was standing when it started, and by the time it
	   is reported on the week may be two waves further along. */
	$ok = @$conn->query(sprintf(
		"INSERT INTO dhc_boss_runs (user_id, day, wave, boss_key, seed, crew_size, season, started_at)
		 VALUES (%d, '%s', %d, '%s', %d, %d, '%s', NOW())",
		$uid, $conn->real_escape_string($day), $wave, 'crew', $seed, count($mine),
		$conn->real_escape_string(dhcb_season())));
	if (!$ok) return array(false, 'Could not start the run.', null);
	$rid = (int)$conn->insert_id;
	if (!$rid) return array(false, 'Could not start the run.', null);
	$bat['meta']['run_id'] = $rid;
	dhcb_save($conn, $rid, $uid, $bat);
	return array(true, '', $bat);
}

function dhcb_save($conn, $run_id, $user_id, $b) {
	$json = $conn->real_escape_string(json_encode($b));
	@$conn->query("INSERT INTO dhc_boss_state (run_id, user_id, state, updated_at)
	               VALUES (" . (int)$run_id . "," . (int)$user_id . ",'$json',NOW())
	               ON DUPLICATE KEY UPDATE state=VALUES(state), updated_at=NOW()");
}

function dhcb_load($conn, $run_id, $user_id) {
	$r = @$conn->query("SELECT state FROM dhc_boss_state
	                     WHERE run_id = " . (int)$run_id . "
	                       AND user_id = " . (int)$user_id . " LIMIT 1");
	if (!$r || !$r->num_rows) return null;
	$x = $r->fetch_assoc();
	$b = json_decode($x['state'], true);
	return is_array($b) ? $b : null;
}

/** The run this player is in the middle of, if any. */
function dhcb_active($conn, $user_id) {
	$rid = dhcb_open_run($conn, $user_id);
	return $rid ? dhcb_load($conn, $rid, $user_id) : null;
}

/**
 * One move. The exchange itself is dhca_turn() -- the Arena's, not a copy.
 */
function dhcb_move($conn, $user_id, $run_id, $a, $z) {
	$b = dhcb_load($conn, $run_id, $user_id);
	if (!$b) return array(false, 'No run in progress.', null);

	list($ok, $msg) = dhca_turn($b, $a, $z);
	if (!$ok) return array(false, $msg, $b);

	if ($b['over'] !== null) dhcb_finish($conn, $b);
	else                     dhcb_save($conn, $run_id, $user_id, $b);
	return array(true, '', $b);
}

/* ---------- the end of a run ------------------------------------------------ */

/**
 * Settle a run: commit its damage to the shared bars, record it, bench the
 * Crew.
 *
 * THE DAMAGE IS COMMITTED UNDER A ROW LOCK, and this is the one place where
 * two players genuinely collide. Both may have started against a boss on
 * 1,000 health and both may have taken 800 off their own snapshot; between
 * them they cannot remove 1,600 from a bar that only had 1,000 on it. The
 * row is read FOR UPDATE, the damage is clamped to what is actually left,
 * and the clamped figure is what gets recorded -- so the ledger adds up to
 * the health that really came off and the leaderboard cannot be inflated by
 * racing somebody.
 *
 * Idempotent. A retried request finds outcome already set and does nothing,
 * because a double-settled run would pay its damage twice.
 */
function dhcb_finish($conn, &$b) {
	$m   = $b['meta'];
	$rid = (int)$m['run_id'];
	$uid = (int)$m['attacker'];
	$day = isset($m['day']) ? $m['day'] : dhcb_week($conn);

	$r = @$conn->query("SELECT outcome FROM dhc_boss_runs WHERE id = $rid LIMIT 1");
	if (!$r || !$r->num_rows) return;
	$row = $r->fetch_assoc();
	if ((int)$row['outcome'] !== 0) return;              // already settled

	$cleared = ($b['over'] === 'mine');                  // this run dropped all three
	$outcome = $cleared ? 1 : 2;

	$applied = array();    // boss_key => damage that actually landed
	$killed  = array();    // boss_key => this run dropped it
	$total   = 0;
	/*
	 * THE WAVE THIS RUN WAS FOUGHT AGAINST, and every write below is scoped
	 * to it.
	 *
	 * Without that scope a run that started against wave 1, and finished
	 * after somebody else dropped wave 1 and raised wave 2, would subtract
	 * its damage from wave 2 -- a crew it never saw, at a health it was
	 * never scaled against. Scoped, the damage lands on the row it was
	 * earned from, which is already at zero, and the existing clamp turns it
	 * into nothing. That is the correct answer: somebody else finished the
	 * crew you were fighting.
	 */
	$wave = isset($m['wave']) ? max(1, (int)$m['wave']) : 1;

	$conn->begin_transaction();
	try {
		foreach ($b['foes'] as $i => $f) {
			if (empty($f['bossKey'])) continue;
			$key  = $f['bossKey'];
			$hp0  = isset($f['hp0']) ? (int)$f['hp0'] : (int)$f['maxHp'];
			$want = max(0, $hp0 - max(0, (int)$f['hp']));
			if ($want <= 0) { $applied[$key] = 0; continue; }

			$q = $conn->query("SELECT health FROM dhc_boss_day
			                    WHERE day = '" . $conn->real_escape_string($day) . "'
			                      AND wave = $wave
			                      AND boss_key = '" . $conn->real_escape_string($key) . "'
			                    FOR UPDATE");
			if (!$q || !$q->num_rows) { $applied[$key] = 0; continue; }
			$cur  = (int)$q->fetch_assoc()['health'];
			$hit  = min($want, max(0, $cur));
			$left = $cur - $hit;
			if ($hit > 0) {
				$conn->query(sprintf(
					"UPDATE dhc_boss_day SET health = %d%s
					  WHERE day = '%s' AND wave = %d AND boss_key = '%s'",
					$left,
					($left <= 0 ? ", killed_at = NOW(), killed_by = $uid" : ''),
					$conn->real_escape_string($day), $wave, $conn->real_escape_string($key)));
			}
			$applied[$key] = $hit;
			$total += $hit;
			if ($left <= 0 && $cur > 0) $killed[$key] = true;
		}
		$conn->commit();
	} catch (Throwable $e) {
		$conn->rollback();
		error_log('dhcb_finish: ' . $e->getMessage());
		return;
	}

	/* The Crew rests from BOSSES only. Nothing here touches dhc_arena_fighters. */
	foreach ($m['mineIds'] as $i => $fid) {
		$fell  = isset($b['mine'][$i]) ? !empty($b['mine'][$i]['ko']) : false;
		dhcb_bench($conn, $uid, (int)$fid, DHCB_BENCH_H + ($fell ? DHCB_BENCH_KO_H : 0), $fell);
	}

	/* Per-Fighter rows, for the reports and for ranking on damage per Fighter
	   rather than on roster size. Same reasoning as the Arena's participant
	   ledger: a counter drifts, a ledger can be recomputed. */
	$rows = array();
	foreach ($m['mineIds'] as $i => $fid) {
		$f = isset($b['mine'][$i]) ? $b['mine'][$i] : null;
		if (!$f) continue;
		$rows[] = sprintf('(%d,%d,%d,%d,%d)', $rid, (int)$fid,
			isset($f['rank']) ? (int)$f['rank'] : $i,
			!empty($f['ko']) ? 1 : 0,
			isset($f['dealt']) ? (int)$f['dealt'] : 0);
	}
	if ($rows) @$conn->query('INSERT IGNORE INTO dhc_boss_run_fighters
	                          (run_id, fighter_id, `rank`, fell, dealt) VALUES ' . implode(',', $rows));

	@$conn->query(sprintf(
		"UPDATE dhc_boss_runs
		    SET outcome=%d, dealt=%d, killed=%d, rounds=%d, moves='%s', ended_at=NOW()
		  WHERE id=%d AND outcome=0",
		$outcome, $total, (count($killed) ? 1 : 0), (int)$b['round'],
		$conn->real_escape_string(json_encode($m['moves'])), $rid));

	@$conn->query("DELETE FROM dhc_boss_state WHERE run_id = $rid");

	/*
	 * A KILL CHANGES THE WORLD INSIDE THIS REQUEST, so the memo has to go.
	 * The very next dhcb_state() -- the announcement below, and the reply
	 * the endpoint builds after it -- must stand the next wave up and read
	 * IT, not the crew that was just dropped.
	 */
	if ($killed) dhcb_spawn_memo(null, true);

	$b['dealtTotal'] = $total;
	$b['applied']    = $applied;
	$b['killedKeys'] = array_keys($killed);

	/* A sweep closing an abandoned run has nobody to report on. */
	if (empty($b['forfeit'])) dhcb_announce($conn, $b, $total, array_keys($killed));
}

/** Rest a Fighter from BOSSES. Deliberately not dhca_bench(). */
function dhcb_bench($conn, $user_id, $fighter_id, $hours, $fell) {
	$uid = (int)$user_id; $fid = (int)$fighter_id; $h = max(0, (int)$hours);
	@$conn->query("INSERT INTO dhc_boss_fighters (fighter_id, user_id, benched_until, ko)
	               VALUES ($fid, $uid, DATE_ADD(NOW(), INTERVAL $h HOUR), " . ($fell ? 1 : 0) . ")
	               ON DUPLICATE KEY UPDATE
	                 benched_until = DATE_ADD(NOW(), INTERVAL $h HOUR),
	                 ko = VALUES(ko)");
}

/**
 * Close runs left open for hours, so an abandoned one does not block the
 * player out of the feature forever. Mirrors dhca_sweep_stale() in intent;
 * the damage already committed is NOT withdrawn, because it genuinely landed.
 */
function dhcb_sweep_stale($conn, $user_id) {
	$uid = (int)$user_id;
	$r = @$conn->query("SELECT s.run_id FROM dhc_boss_state s
	                     INNER JOIN dhc_boss_runs rr ON rr.id = s.run_id
	                     WHERE s.user_id = $uid AND rr.outcome = 0
	                       AND s.updated_at < DATE_SUB(NOW(), INTERVAL 6 HOUR)");
	if (!$r) return;
	while ($x = $r->fetch_assoc()) {
		$b = dhcb_load($conn, (int)$x['run_id'], $uid);
		if (!$b) continue;
		$b['over'] = 'foes';
		$b['forfeit'] = true;
		dhcb_finish($conn, $b);
	}
}

/* ---------- what the day pays ----------------------------------------------- */

/** A run has to be a real attempt to qualify for the day's trait. */
if (!defined('DHCB_MIN_DEALT'))      define('DHCB_MIN_DEALT', 150);
/* Qualifying floor for the weekly board. Three is one full day, so a single
   lucky run cannot top it and two days of play always qualifies. It was 5
   against a MONTH of runs, which over a week would have excluded most of
   the board. */
if (!defined('DHCB_LADDER_MIN_RUNS'))define('DHCB_LADDER_MIN_RUNS', 3);
/*
 * 50,000 A WEEK, which is where Crypt Crawl and Skull Racer sit -- the
 * flagship weekly rate rather than the 25,000 Skull Swap and Gauntlets pay.
 * Roughly 217,000 a month, just under DHC Arena's 250,000, which is the
 * right shape: the Arena is the headline and this is the other thing a
 * Fighter is for.
 *
 * It was 100,000 MONTHLY while the crew reset nightly. A monthly board over
 * weekly content spends three of its four weeks already decided, and a
 * ladder nobody can still climb is not a reason to play.
 */
if (!defined('DHCB_CARBON'))         define('DHCB_CARBON', 50000);

/**
 * Which table the day rolls on, from how far the whole community got.
 *
 * A GRADIENT, NOT A THRESHOLD, which is the thing that makes this safe. The
 * reward improves continuously as the bars come down, so there is no point
 * at which stopping beats continuing and nothing to coordinate around. An
 * earlier design escalated the table on days the crew SURVIVED, and the
 * optimal play under it was to beat the bosses to 1% and stop -- which with
 * a small community is one message in Discord, not a theoretical exploit.
 *
 * THE FULL CLEAR IS A DISCRETE JUMP on top of the gradient, not the top of
 * it. At 99% with one boss on a sliver you are still one table short, so the
 * last push is worth making. That is the whole drama of the thing.
 *
 * The four tables are DHCF_TIERS and nothing here invents a rarity: every
 * one of them keeps a non-zero mythic chance, so a kill is a real shot at a
 * mythic rather than a guaranteed one. Guaranteeing mythics on a daily
 * clearable boss would mint more of them in a fortnight than exist.
 */
function dhcb_tier_table($progress) {
	/*
	 * STICKY, AND THIS IS THE ONE THING WAVES MUST NOT GET WRONG.
	 *
	 * It used to read `cleared`, which is about the crew currently on the
	 * board. Stand wave 2 up at full health and that goes false and the
	 * percentage goes back to zero -- so the entire community would have
	 * been silently downgraded from the best table to the worst, as a direct
	 * consequence of WINNING. `downed` counts crews the week has actually
	 * put down and only ever rises, so the best table is locked the moment
	 * the first crew falls and no later wave can take it back.
	 */
	if (!empty($progress['downed'])) return 'placement_1';
	if (!empty($progress['cleared'])) return 'placement_1';
	$pct = isset($progress['pct']) ? (float)$progress['pct'] : 0.0;
	if ($pct >= 0.50) return 'placement_3';
	if ($pct >= 0.25) return 'placement_10';
	return 'run';
}

/** Everyone who put in a real shift on a given day, with what they dealt. */
function dhcb_participants($conn, $day) {
	$esc = $conn->real_escape_string($day);
	$out = array();
	/* DAYS, not runs, is what the cache is built from -- see
	   dhcb_cache_days(). Counted here in the same pass so the settle does
	   not ask once per player. */
	$min = (int)DHCB_MIN_DEALT;
	$r = @$conn->query("SELECT user_id, SUM(dealt) AS dealt, COUNT(*) AS runs,
	                           SUM(crew_size) AS fighters,
	                           COUNT(DISTINCT CASE WHEN dealt >= $min
	                                 THEN " . skl_week_day_sql('started_at') . " END) AS days
	                      FROM dhc_boss_runs
	                     WHERE day = '$esc' AND outcome <> 0
	                     GROUP BY user_id");
	if (!$r) return $out;
	while ($x = $r->fetch_assoc()) {
		$out[(int)$x['user_id']] = array(
			'user_id'=>(int)$x['user_id'], 'dealt'=>(int)$x['dealt'],
			'runs'=>(int)$x['runs'], 'fighters'=>(int)$x['fighters'],
			'days'=>min((int)DHCB_CACHE_MAX, (int)$x['days']));
	}
	return $out;
}

/**
 * Close a day: roll everybody's trait and record that it was paid.
 *
 * IDEMPOTENT BY PRIMARY KEY. dhc_boss_payouts is keyed (day, user_id), so a
 * cron run late pays correctly and a cron run twice pays nothing the second
 * time. Nothing here checks a flag that could be wrong; the row either
 * exists or it does not.
 *
 * THE LEDGER ROW IS WRITTEN BEFORE THE TRAIT, the same ordering
 * return_gift_grant() uses and for the same reason: if the pair half-fails,
 * somebody being owed a trait is recoverable and somebody collecting every
 * time the cron runs is not.
 *
 * Returns array(paid, table, progress).
 */
function dhcb_settle($conn, $day = null, $announce = true) {
	if ($day === null) $day = dhcb_week($conn);
	$state = dhcb_state($conn, $day);
	$prog  = dhcb_progress($state);
	$table = dhcb_tier_table($prog);
	$tiers = defined('DHCF_TIERS') && isset(DHCF_TIERS[$table]) ? DHCF_TIERS[$table] : null;

	$paid = 0; $traits = 0; $who = array();
	foreach (dhcb_participants($conn, $day) as $uid => $p) {
		if ($p['dealt'] < DHCB_MIN_DEALT) continue;
		/*
		 * ONE TRAIT PER DAY THEY TURNED UP, capped. The cache rewards
		 * coming back rather than grinding: with three runs a day there is
		 * no way to fill it in one sitting, so a full cache is seven
		 * separate visits and a player who misses Wednesday collects six.
		 */
		$slots = max(1, min((int)DHCB_CACHE_MAX, (int)$p['days']));
		$ok = @$conn->query(sprintf(
			"INSERT INTO dhc_boss_payouts (day, user_id, tier_table, dealt, slots, paid_at)
			 VALUES ('%s', %d, '%s', %d, %d, NOW())",
			$conn->real_escape_string($day), (int)$uid,
			$conn->real_escape_string($table), (int)$p['dealt'], $slots));
		if (!$ok) continue;                        // already paid, or no table
		if (function_exists('dhcf_award')) {
			for ($i = 0; $i < $slots; $i++) {
				dhcf_award($conn, (int)$uid, 'wildcard', 'dhcbosses',
				           'the weekly boss crew', $tiers);
			}
		}
		$paid++; $traits += $slots; $who[] = (int)$uid;
	}
	if ($announce && $paid > 0) dhcb_announce_day($conn, $day, $prog, $table, $paid, $traits);
	return array('paid'=>$paid, 'traits'=>$traits, 'table'=>$table,
	             'progress'=>$prog, 'users'=>$who);
}

/**
 * The week as seven slots, for the cache the player can see filling up.
 *
 * WHICH DAYS, NOT HOW MANY. A number going up is information; seven boxes
 * with Wednesday visibly empty is a reason to open the game on Wednesday.
 * The count alone cannot say which day was missed, and the missed day is
 * the whole motivation.
 *
 * Returns seven rows in week order: date, label, state. State is one of
 *   'filled'  a real run landed that day, the slot is earned
 *   'today'   today, and still unearned
 *   'open'    a future day of this week
 *   'missed'  a past day of this week with nothing on it
 */
function dhcb_cache_slots($conn, $user_id, $week = null) {
	if ($week === null) $week = dhcb_week($conn);
	$uid  = (int)$user_id;
	$done = array();
	/* Blocks of the week, not calendar dates -- see skl_week_day_sql(). */
	$r = @$conn->query("SELECT DISTINCT " . skl_week_day_sql('started_at') . " AS d
	                      FROM dhc_boss_runs
	                     WHERE user_id = $uid
	                       AND day = '" . $conn->real_escape_string($week) . "'
	                       AND outcome <> 0 AND dealt >= " . (int)DHCB_MIN_DEALT);
	if ($r) while ($x = $r->fetch_assoc()) $done[(int)$x['d']] = true;

	$now = dhcb_week_day($conn);
	$out = array();
	for ($i = 0; $i < 7; $i++) {
		if (isset($done[$i])) $st = 'filled';
		elseif ($i === $now)  $st = 'today';
		elseif ($i < $now)    $st = 'missed';
		else                  $st = 'open';
		/*
		 * NUMBERED, NOT NAMED. A block runs 4pm to 4pm, so it straddles two
		 * calendar dates -- calling one of them "Fri" would be wrong by
		 * eight hours for everybody and wrong by more for anyone outside
		 * Central time. "Day 3" is true in every timezone.
		 */
		$out[] = array('index' => $i, 'label' => (string)($i + 1), 'state' => $st);
	}
	return $out;
}

/* ---------- the monthly ladder ---------------------------------------------- */

/**
 * The month's board.
 *
 * RANKED ON DAMAGE PER FIGHTER DEPLOYED, not on raw damage. Raw damage is
 * mostly a measure of roster size, and the harmonic payout below weights
 * rank 1 at twice rank 2 -- so ranking on volume would compound the biggest
 * collection every month and the rest of the board would learn to stop
 * looking. Efficiency puts a well-built five-Fighter stable in contention.
 *
 * TOTAL DAMAGE, RUNS AND FIGHTERS ARE STILL CARRIED, because they are worth
 * seeing even though they do not decide anything. A column you rank on
 * creates incentives; a column you display tells a story. Conflating the two
 * is how a leaderboard quietly starts rewarding something nobody intended.
 */
/*
 * THE WEEK, NOT THE SEASON. dhc_boss_runs.day already holds the Monday the
 * crew belongs to, so the board groups on that and the CHAR(7) season
 * column is left to whatever wants a monthly view later. A week of runs is
 * a complete competition; a month of them is three weeks already decided.
 */
function dhcb_ladder($conn, $week = null, $limit = 25) {
	if ($week === null) $week = dhcb_week($conn);
	$esc = $conn->real_escape_string($week);
	$lim = max(1, (int)$limit);
	$min = max(1, (int)DHCB_LADDER_MIN_RUNS);
	$sql = "SELECT r.user_id, u.username, u.discord_id, u.avatar,
	               SUM(r.dealt) AS dealt, COUNT(*) AS runs,
	               SUM(r.crew_size) AS fighters, SUM(r.killed) AS kills
	          FROM dhc_boss_runs r
	          INNER JOIN users u ON u.id = r.user_id
	         WHERE r.day = '$esc' AND r.outcome <> 0
	         GROUP BY r.user_id
	        HAVING runs >= $min AND fighters > 0
	         ORDER BY (SUM(r.dealt) / SUM(r.crew_size)) DESC, dealt DESC
	         LIMIT $lim";
	$res = @$conn->query($sql);
	$out = array();
	if (!$res) return $out;
	while ($x = $res->fetch_assoc()) {
		$f = max(1, (int)$x['fighters']);
		$out[] = array(
			'user_id'=>(int)$x['user_id'],
			'name'=>($x['username'] !== '' ? $x['username'] : 'Unknown'),
			'discord_id'=>$x['discord_id'], 'avatar'=>$x['avatar'],
			'dealt'=>(int)$x['dealt'], 'runs'=>(int)$x['runs'],
			'fighters'=>$f, 'kills'=>(int)$x['kills'],
			'per'=>(int)round((int)$x['dealt'] / $f),
		);
	}
	return $out;
}

/* ---------- reports --------------------------------------------------------- */

/*
 * THE WEEKLY BOARD, RENDERED.
 *
 * Same table, same classes and the same empty-state shape as
 * dhca_ladder_html(), because it sits in the same panel on the same page and
 * a second visual language for the same object would be a worse board, not a
 * distinct one. What differs is the columns, and they differ because the
 * ranking does: the Arena ranks on wins, this ranks on damage per Fighter
 * fielded, so the number the order is based on has to be ON the board or the
 * order looks arbitrary.
 *
 * CREWS AND FIGHTERS ARE CARRIED EVEN THOUGH THEY DECIDE NOTHING -- turning
 * up with twenty-one Crews over a week is the thing the cache rewards, and a
 * board that only showed the award metric would hide the effort entirely.
 */
function dhcb_ladder_html($rows, $user_id, $guest, $empty) {
	if (!$rows) return '<p class="a-sub" style="margin:0">' . htmlspecialchars($empty) . '</p>';
	$h = '<table class="a-lad"><thead><tr><th>#</th><th>'
	   . ($guest ? 'Player' : 'Staker')
	   . '</th><th>Per fighter</th><th>Damage</th><th>Crews</th></tr></thead><tbody>';
	foreach ($rows as $i => $l) {
		$me = ((int)$l['user_id'] === (int)$user_id) ? ' class="me"' : '';
		/* The Fighter count rides the Crews cell rather than taking a sixth
		   column: the panel is 450px and six columns of figures in it is a
		   table nobody reads. Crews is the number a player is counting. */
		$h .= '<tr' . $me . '>'
		    . '<td class="r">' . ($i + 1) . '</td>'
		    . '<td>' . htmlspecialchars($l['name']) . '</td>'
		    . '<td class="r">' . number_format($l['per']) . '</td>'
		    . '<td class="r">' . number_format($l['dealt']) . '</td>'
		    . '<td class="r" title="' . (int)$l['fighters'] . ' Fighters fielded">'
		    . (int)$l['runs'] . '</td>'
		    . '</tr>';
	}
	return $h . '</tbody></table>';
}

/** Every Crew that went in on a day, newest first. The owner's report. */
function dhcb_runs_report($conn, $day = null, $limit = 200) {
	if ($day === null) $day = dhcb_week($conn);
	$esc = $conn->real_escape_string($day);
	$lim = max(1, (int)$limit);
	$rows = array();
	/* discord_id and avatar come along for the player face on the public
	   feed; the admin table ignores them. One query either way. */
	$res = @$conn->query("SELECT r.id, r.user_id, u.username, u.discord_id, u.avatar,
	                             r.dealt, r.crew_size, r.wave,
	                             r.outcome, r.killed, r.rounds, r.started_at, r.ended_at
	                        FROM dhc_boss_runs r
	                        LEFT JOIN users u ON u.id = r.user_id
	                       WHERE r.day = '$esc'
	                       ORDER BY r.id DESC LIMIT $lim");
	if (!$res) return $rows;
	while ($x = $res->fetch_assoc()) $rows[] = $x;
	if (!$rows) return $rows;

	/* The Fighters in each, in one pass rather than one query per run. */
	$ids = array(); foreach ($rows as $r) $ids[] = (int)$r['id'];
	$byRun = array();
	/* traits too, so the feed can draw the Crew that went in rather than
	   list its names. Still one query for every run on the page. */
	$q = @$conn->query("SELECT rf.run_id, rf.fighter_id, rf.fell, rf.dealt, rf.`rank`,
	                           f.serial, f.name, f.traits
	                      FROM dhc_boss_run_fighters rf
	                      LEFT JOIN dhc_fighters f ON f.id = rf.fighter_id
	                     WHERE rf.run_id IN (" . implode(',', $ids) . ")
	                     ORDER BY rf.`rank` ASC");
	if ($q) while ($x = $q->fetch_assoc()) {
		$x['display'] = ($x['name'] !== null && $x['name'] !== '')
			? $x['name'] : (function_exists('dhcf_default_name') ? dhcf_default_name($x['serial']) : ('#' . $x['serial']));
		$byRun[(int)$x['run_id']][] = $x;
	}
	/*
	 * WHICH BOSS A RUN PUT DOWN, which the run row cannot say on its own:
	 * dhc_boss_runs.killed is a 0/1 flag. dhc_boss_day records who landed
	 * the last hit and WHEN, so the boss a run dropped is the one whose
	 * killed_at falls inside that run's own window. Exact, and no new
	 * column -- attributing by user alone would credit every run that
	 * player made this week with every boss they ever finished.
	 */
	$kills = array();
	$kq = @$conn->query("SELECT r.id AS run_id, d.boss_key
	                       FROM dhc_boss_runs r
	                       INNER JOIN dhc_boss_day d
	                               ON d.day = r.day AND d.wave = r.wave
	                              AND d.killed_by = r.user_id
	                              AND d.killed_at IS NOT NULL
	                              AND d.killed_at >= r.started_at
	                              AND d.killed_at <= COALESCE(r.ended_at, NOW())
	                      WHERE r.id IN (" . implode(',', $ids) . ")");
	if ($kq) while ($x = $kq->fetch_assoc()) {
		$bz = dhcb_boss($x['boss_key']);
		$kills[(int)$x['run_id']][] = array(
			'key'  => $x['boss_key'],
			'name' => $bz ? $bz['name'] : strtoupper($x['boss_key']),
			'art'  => dhcb_art($x['boss_key']),
		);
	}

	foreach ($rows as $i => $r) {
		$rows[$i]['fighters'] = isset($byRun[(int)$r['id']]) ? $byRun[(int)$r['id']] : array();
		$rows[$i]['kills']    = isset($kills[(int)$r['id']])  ? $kills[(int)$r['id']]  : array();
	}
	return $rows;
}

/* ---------- telling people ---------------------------------------------------
   Fire and forget, caught, and buffered: a Discord outage must never cost
   somebody their run. Channel key 'dhcbosses' -- the webhook for it does not
   exist yet, and getWebhook() returning nothing simply means no post, which
   is why nothing below checks for one. */

function dhcb_webhooks_loaded() {
	if (function_exists('discordmsg')) return true;
	if (is_file(__DIR__ . '/webhooks.php')) {
		ob_start(); include_once __DIR__ . '/webhooks.php'; ob_end_clean();
	}
	return function_exists('discordmsg');
}

/** Who this is, for an embed. Mirrors dhca_identity() rather than guessing. */
function dhcb_identity($conn, $user_id) {
	if (function_exists('dhca_identity')) return dhca_identity($conn, (int)$user_id);
	return array('name' => 'a player', 'avatar' => '', 'mention' => '');
}

/**
 * One Crew went in. Reported because the owner asked for a record of every
 * crew that goes in, and because a shared bar is only interesting if people
 * can see it moving.
 *
 * A RUN THAT KILLED SOMETHING IS LOUD, everything else is quiet. Six runs a
 * day times a dozen players is a lot of posts, so an ordinary chip gets a
 * short line and a kill gets the full treatment with the art.
 */
function dhcb_announce($conn, $b, $dealt, $killedKeys) {
	if (!dhcb_webhooks_loaded()) return;
	try {
		$uid   = (int)$b['meta']['attacker'];
		$me    = dhcb_identity($conn, $uid);
		$day   = isset($b['meta']['day']) ? $b['meta']['day'] : dhcb_week($conn);
		$state = dhcb_state($conn, $day);
		$prog  = dhcb_progress($state);

		$bars = array();
		foreach ($state as $s) {
			$pct = $s['max_health'] > 0 ? round(100 * $s['health'] / $s['max_health']) : 0;
			$bars[] = ($s['dead'] ? '~~' . $s['name'] . '~~ down' : $s['name'] . ' ' . $pct . '%');
		}

		$crew = array();
		foreach ($b['mine'] as $f) $crew[] = $f['name'] . (!empty($f['ko']) ? ' (fell)' : '');

		$killed = is_array($killedKeys) ? $killedKeys : array();
		$kname  = array();
		foreach ($killed as $k) { $kb = dhcb_boss($k); if ($kb) $kname[] = $kb['name']; }

		/*
		 * A WHOLE CREW FALLING IS THE WEEK'S EVENT, not a footnote under a
		 * damage number. It is also the moment the best trait table locks
		 * in for everybody, so the post says so -- a player who reads only
		 * Discord should know their week just got better.
		 */
		$fellWave = isset($b['meta']['wave']) ? max(1, (int)$b['meta']['wave']) : 1;
		$wiped    = !empty($prog['cleared']) || $prog['wave'] > $fellWave;
		$title = $wiped
			? '☠️ DHC Bosses — wave ' . $fellWave . ' is down'
			: ($kname
				? '💀 DHC Bosses — ' . implode(' and ', $kname) . ' is down'
				: '🩸 DHC Bosses — ' . number_format((int)$dealt) . ' damage');
		$desc  = '**' . $me['name'] . '** sent ' . implode(', ', $crew) . "\n"
		       . number_format((int)$dealt) . ' damage dealt' . "\n\n";
		if ($wiped) {
			$desc .= 'That is **' . $prog['downed'] . ' crew'
			       . ($prog['downed'] === 1 ? '' : 's') . ' dropped this week**, and the '
			       . 'best trait table is locked in for everyone who takes part.' . "\n\n"
			       . 'They are getting back up angrier — **wave ' . $prog['wave'] . '** is '
			       . 'standing now.' . "\n"
			       . implode(' · ', $bars);
		} else {
			$desc .= 'Wave **' . $prog['wave'] . '**' . "\n"
			       . implode(' · ', $bars) . "\n"
			       . 'Crew at **' . round(100 * $prog['pct']) . '%** down.';
		}

		/*
		 * EVERY POST CARRIES A PICTURE, AND THE PICTURE IS THE STORY.
		 *
		 * Only a run that killed something had one before, so the ordinary
		 * post -- which is most of them, because losing is the normal
		 * outcome here -- was a wall of text in a feed of embeds.
		 *
		 * Three states, strongest first:
		 *
		 *   THE CREW WENT DOWN: the player's own Fighter that dealt the
		 *   most, because at that point the story is who did it. A Fighter
		 *   is a trait stack rather than a file, so the picture is
		 *   dhc-card.php, the same public 1200x630 renderer the X shares
		 *   use.
		 *   A BOSS WENT DOWN: that boss, as before.
		 *   EVERYTHING ELSE: the boss that hit the player hardest. The
		 *   engine already credits damage to whoever dealt it, on both
		 *   sides, so this costs a loop and no new bookkeeping -- and it
		 *   answers the question a wiped player actually has, which is
		 *   what killed them.
		 */
		$img = ''; $didIt = '';
		if ($wiped) {
			$bestI = -1; $bestD = -1;
			foreach ($b['mine'] as $i => $f) {
				$d = isset($f['dealt']) ? (int)$f['dealt'] : 0;
				if ($d > $bestD) { $bestD = $d; $bestI = $i; }
			}
			$fid = ($bestI >= 0 && isset($b['meta']['mineIds'][$bestI]))
			     ? (int)$b['meta']['mineIds'][$bestI] : 0;
			if ($fid > 0) {
				/* dhc-card.php keys on SERIAL, not on the row id. */
				$q = @$conn->query("SELECT serial FROM dhc_fighters WHERE id = $fid LIMIT 1");
				if ($q && ($sr = $q->fetch_assoc()) && (int)$sr['serial'] > 0)
					$img = 'https://skulliance.io/staking/dhc-card.php?serial=' . (int)$sr['serial'];
			}
			if ($bestI >= 0 && $bestD > 0)
				$didIt = '**' . $b['mine'][$bestI]['name'] . '** landed the most of them: '
				       . number_format($bestD) . '.';
		}
		if ($img === '' && $kname) {
			$kb = dhcb_boss($killed[0]);
			/* THROUGH THE ENDPOINT, NOT THE FLAT FILE. Discord takes a URL
			   and cannot apply a filter, so a kill in wave 3 was posting
			   wave 1 art -- a picture of a crew nobody fought. */
			if ($kb) $img = 'https://skulliance.io/staking/dhc-boss-art.php?boss='
			              . rawurlencode($kb['key']) . '&wave=' . $fellWave;
		}
		if ($img === '') {
			$hitI = null; $hitD = -1;
			foreach ($b['foes'] as $f) {
				$d = isset($f['dealt']) ? (int)$f['dealt'] : 0;
				if ($d > $hitD && !empty($f['bossKey'])) { $hitD = $d; $hitI = $f; }
			}
			if ($hitI) {
				$img = 'https://skulliance.io/staking/dhc-boss-art.php?boss='
				     . rawurlencode($hitI['bossKey']) . '&wave=' . $fellWave;
				$hb  = dhcb_boss($hitI['bossKey']);
				if ($hb && $hitD > 0)
					$didIt = '**' . $hb['name'] . '** did the most of it back: '
					       . number_format($hitD) . '.';
			}
		}
		if ($didIt !== '') $desc .= "\n" . $didIt;
		$author = array('name' => $me['name']);
		if ($me['avatar'] !== '') $author['icon_url'] = $me['avatar'];

		ob_start();
		discordmsg($title, $desc, $img, 'https://skulliance.io/staking/dhcbosses.php',
			'dhcbosses', ($me['avatar'] !== '' ? $me['avatar'] : ''),
			($kname ? 'FFC800' : '8A1C2B'), $author, null, '', $uid);
		ob_end_clean();
	} catch (Throwable $e) {
		error_log('dhcb_announce: ' . $e->getMessage());
	}
}

/** The day closed. One post, with what everybody earned. */
function dhcb_announce_day($conn, $day, $prog, $table, $paid, $traits = 0) {
	if (!dhcb_webhooks_loaded()) return;
	try {
		$cleared = !empty($prog['cleared']);
		$label = array('run'=>'standard', 'placement_10'=>'improved',
		               'placement_3'=>'rare', 'placement_1'=>'best');
		$tl = isset($label[$table]) ? $label[$table] : $table;
		$title = $cleared ? '🏆 DHC Bosses — the crew is down'
		                  : '🌒 DHC Bosses — the day closes';
		$desc  = ($cleared
				? 'All three went down. Everybody who took part rolls on the **best** table.'
				: 'The crew took **' . round(100 * $prog['pct']) . '%** damage and held. '
				  . 'Everybody who took part rolls on the **' . $tl . '** table.')
			. "\n\n" . number_format((int)$paid) . ' '
			. ((int)$paid === 1 ? 'player' : 'players') . ' paid '
			. number_format((int)$traits) . ' traits between them · '
			. number_format((int)$prog['dealt']) . ' of '
			. number_format((int)$prog['max']) . ' damage'
			. "\n\nA new crew stands up on Monday.";
		ob_start();
		discordmsg($title, $desc,
			'https://skulliance.io/staking/' . dhcb_art('d34thbrngr'),
			'https://skulliance.io/staking/dhcbosses.php', 'dhcbosses', '',
			($cleared ? '00C8A0' : '8A1C2B'), null, null, '');
		ob_end_clean();
	} catch (Throwable $e) {
		error_log('dhcb_announce_day: ' . $e->getMessage());
	}
}

/* ---------- sharing ---------------------------------------------------------- */

/**
 * The post a player makes about a run. Built from what actually happened,
 * so it is worth reading -- a share that could have been written before the
 * fight is the kind nobody clicks twice.
 *
 * NO @ IN FRONT OF A PLAYER NAME. The platform stores Discord usernames and
 * no X handles, so @-tagging one hands a stranger the notification. Same
 * rule dhca_start() documents for the Arena share.
 */
function dhcb_share_text($dealt, $killedNames, $prog) {
	/*
	 * SAY "DOWN", NEVER "AT". progress.pct is damage DEALT, so "they are at
	 * 42%" reads as 42% health left and means the opposite of what it says
	 * -- and a post that gets the direction wrong is worse than one that
	 * omits the number. "42% down" cannot be read backwards.
	 */
	/*
	 * TWO SUBJECTS, BOTH NAMED. The post was written as fragments -- "4,800
	 * damage into the DHC boss crew. 62% down and still standing." -- and a
	 * reader who does not already know the game cannot tell whose damage it
	 * was or what is 62% down. The owner's rewrite is the fix: say MY DHC
	 * Fighter Crew did it, and say THE DHC Boss Crew is the thing falling.
	 * Full sentences, both actors named, nothing to infer.
	 */
	$down = isset($prog['pct']) ? round(100 * $prog['pct']) : 0;
	$mine = 'My DHC Fighter Crew inflicted ' . number_format((int)$dealt) . ' damage.';
	if (!empty($prog['cleared'])) {
		return $mine . ' The DHC Boss Crew is DOWN.';
	}
	if ($killedNames) {
		return $mine . ' ' . implode(' and ', $killedNames)
		     . (count($killedNames) === 1 ? ' is' : ' are') . ' down, and the DHC Boss Crew '
		     . 'is now ' . $down . '% down.';
	}
	return $mine . ' The DHC Boss Crew is now ' . $down . '% down but still standing.';
}

/** The button, using the platform's own share helper. */
function dhcb_share_button($dealt, $killedNames, $prog) {
	if (!function_exists('shareOnXButton')) {
		if (is_file(__DIR__ . '/lib/share-x.php')) require_once __DIR__ . '/lib/share-x.php';
	}
	if (!function_exists('shareOnXButton')) return '';
	return shareOnXButton(dhcb_share_text($dealt, $killedNames, $prog), 'dhcbosses.php');
}
