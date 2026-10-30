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
/* The Siege map puts a player's avatar on every node. avatar_url() lives in
   avatar-lib.php, which db.php loads for a page -- but this library is also
   reached from the settle cron and from ajax endpoints that never touch db.php,
   and the function_exists() guard at the call site turned that into a map with
   no faces rather than an error. avatar-lib.php has no dependencies of its own,
   so requiring it here costs nothing and makes the guard belt-and-braces. */
require_once __DIR__ . '/avatar-lib.php';

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
function dhcb_state($conn, $day = null, $wave = null) {
	if ($day === null) $day = dhcb_spawn($conn);
	$esc = $conn->real_escape_string($day);
	/* THE STANDING WAVE, which is the highest one on the week -- a wave only
	   exists because the one before it fell. Reading every wave and summing
	   would show a crew at a health nobody is fighting.
	 *
	 * AN EXPLICIT WAVE ASKS FOR A CREW THAT IS NO LONGER STANDING, and the
	 * one caller that wants that is the end of a run: a Crew that just
	 * dropped the last boss has to be told about the crew it dropped, not
	 * about the one that stood up in the same request. The share said
	 * "CIP-666 is down, and the DHC Boss Crew is now 0% down" -- 0% being
	 * the untouched wave behind it.
	 */
	$sel = ($wave === null) ? "(SELECT MAX(wave) FROM dhc_boss_day WHERE day = '$esc')"
	                        : (string)max(1, (int)$wave);
	$r = @$conn->query("SELECT boss_key, wave, max_health, health, killed_at, killed_by
	                      FROM dhc_boss_day
	                     WHERE day = '$esc'
	                       AND wave = $sel");
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
	$r = @$conn->query("SELECT COUNT(DISTINCT " . skl_week_day_sql_for($week, 'started_at') . ") AS n
	                      FROM dhc_boss_runs
	                     WHERE user_id = " . (int)$user_id . "
	                       AND day = '" . $conn->real_escape_string($week) . "'
	                       AND outcome <> 0
	                       AND (dealt >= " . (int)DHCB_MIN_DEALT
	                     . " OR rounds >= " . (int)DHCB_MIN_ROUNDS . ")");
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
 * LAND THIS MOVE'S DAMAGE ON THE SHARED BARS, NOW, AND READ THEM BACK.
 *
 * WHY THIS IS NOT DONE AT THE END ANY MORE. It used to be: a run fought a
 * private snapshot for twenty minutes and committed the lot on its last
 * move, clamped to whatever was still on the bar. Two things came out of
 * that, and a player hit both in the first week.
 *
 *   The bar lied. A run in progress was invisible to everyone else, so the
 *   number on the page was up to twenty minutes stale and two Crews could
 *   both be swinging at health that was already gone.
 *
 *   And the loser of that race banked NOTHING. Somebody went twenty rounds
 *   against a crew that died under them, committed into empty bars, and
 *   recorded a flat zero -- no damage, no ladder, no kill. They had done
 *   the work. The clamp was right that the bar cannot go below zero; it was
 *   wrong that the damage had to wait until the end to find that out.
 *
 * Committing per move fixes both at the root rather than compensating for
 * them. Damage lands the instant it is dealt, so it can never be taken back
 * by somebody else finishing first, and the bar everyone is looking at is
 * current to the last move anybody made.
 *
 * AND THE BOARD IS RE-SYNCED FROM THE BAR, which is the other half. The
 * foes a player is looking at ARE the shared crew, so when another Crew
 * takes a boss down it goes down on this board too -- immediately, with a
 * line in the log saying why. Nobody fights a corpse for fifteen rounds
 * again, because there is no longer a window in which that is possible.
 *
 * Scoped to the wave this run was started against, like every other write
 * here: a run that outlives its crew must not subtract from the one that
 * replaced it.
 */
function dhcb_commit($conn, &$b, $dealtNow) {
	$m    = $b['meta'];
	$day  = isset($m['day'])  ? $m['day'] : dhcb_week($conn);
	$wave = isset($m['wave']) ? max(1, (int)$m['wave']) : 1;
	$uid  = (int)$m['attacker'];
	$esc  = $conn->real_escape_string($day);

	/* WHAT THIS RUN HAS ACTUALLY LANDED, carried on the run rather than
	   re-derived. hp0 - hp stopped meaning "my damage" the moment the board
	   started following the shared bar, because that difference now holds
	   everybody's. */
	if (!isset($b['meta']['sent']))   $b['meta']['sent']   = array();
	if (!isset($b['meta']['killed'])) $b['meta']['killed'] = array();

	/*
	 * A BOSS ON THE DRAIN KIT HEALS ITSELF, AND THE BAR HAS TO SEE IT.
	 *
	 * dhca_heal() gives back 45% of what a drain hit landed, so a boss's
	 * health goes UP between moves. Committing only the positive deltas
	 * banked damage the boss then regenerated -- over a nineteen-move run
	 * against this crew, 127 of 940 -- and the next re-sync pulled the
	 * board back down to the bar, quietly erasing the heal on this board
	 * too. The mechanic would have been dead and nothing would have said
	 * so. Negative deltas are committed like any other, so the bar and the
	 * board agree and a drain boss still drains.
	 *
	 * CAPPED AT THIS RUN'S OWN DAMAGE, below: a run can give back what it
	 * took and not one point more, so no amount of healing can undo
	 * somebody else's work or lift a bar above where this Crew found it.
	 */
	$write = array();
	foreach ($dealtNow as $k => $v) if ((int)$v !== 0) $write[$k] = (int)$v;

	$live  = array();   // boss_key => shared health, after this commit
	$fresh = array();   // boss_key => this Crew took it to zero, this move

	if ($write) {
		$conn->begin_transaction();
		try {
			/*
			 * LOCKED IN dhcb_bosses() ORDER, BY EVERY RUN, EVERY TIME.
			 * Three rows is cheap enough to take all of them even when only
			 * one is being written, and one fixed order over one fixed set
			 * is what makes two Crews hitting the same crew in the same
			 * instant impossible to deadlock rather than unlikely to.
			 */
			foreach (dhcb_bosses() as $bz) {
				$k = $bz['key'];
				$q = $conn->query("SELECT health FROM dhc_boss_day
				                    WHERE day = '$esc' AND wave = $wave
				                      AND boss_key = '" . $conn->real_escape_string($k) . "'
				                    FOR UPDATE");
				if (!$q || !$q->num_rows) continue;
				$cur = (int)$q->fetch_assoc()['health'];
				$live[$k] = $cur;
				/* $cur <= 0 covers both directions: no damage lands on a
				   corpse, and nothing gets a dead boss back on its feet in
				   the middle of somebody's run. */
				if (!isset($write[$k]) || $cur <= 0) continue;
				$d = (int)$write[$k];
				if ($d > 0) {
					/* Still clamped, and now it almost never bites: the
					   board was level with the bar one move ago, so the
					   only thing this can trim is a collision inside the
					   same instant. */
					$hit = min($d, $cur);
				} else {
					$own = isset($b['meta']['sent'][$k]) ? (int)$b['meta']['sent'][$k] : 0;
					$hit = -min(-$d, max(0, $own));
				}
				if ($hit === 0) continue;
				$left = $cur - $hit;
				$conn->query(sprintf(
					"UPDATE dhc_boss_day SET health = %d%s
					  WHERE day = '%s' AND wave = %d AND boss_key = '%s'",
					$left, ($left <= 0 ? ", killed_at = NOW(), killed_by = $uid" : ''),
					$esc, $wave, $conn->real_escape_string($k)));
				$live[$k] = $left;
				$b['meta']['sent'][$k] = (isset($b['meta']['sent'][$k])
					? (int)$b['meta']['sent'][$k] : 0) + $hit;
				if ($left <= 0) { $b['meta']['killed'][$k] = true; $fresh[$k] = true; }
				/* A kill changes the world inside this request and the memo
				   is how the rest of it would miss that. Cleared here, not
				   only at the end of a run, because a kill is no longer
				   something only the end of a run can do. */
				if ($left <= 0) dhcb_spawn_memo(null, true);
			}
			$conn->commit();
		} catch (Throwable $e) {
			/* Nothing landed and nothing was recorded, so the board is left
			   where it is and the next move tries again with its own
			   damage. The run is not corrupted by a failed commit. */
			$conn->rollback();
			error_log('dhcb_commit: ' . $e->getMessage());
			return;
		}
	} else {
		/* A move that dealt nothing still reads the bar, because somebody
		   else may have moved it -- that read is the whole point. No lock:
		   there is nothing to write and a stale-by-microseconds number is
		   corrected by the next move. */
		$q = @$conn->query("SELECT boss_key, health FROM dhc_boss_day
		                     WHERE day = '$esc' AND wave = $wave");
		if ($q) while ($x = $q->fetch_assoc()) $live[$x['boss_key']] = (int)$x['health'];
	}

	/*
	 * THE BOARD FOLLOWS THE BAR, DOWNWARD ONLY.
	 *
	 * min() against what is already on screen is a guard, not arithmetic: a
	 * boss must never heal mid-fight because a read came back odd. Within a
	 * wave the shared health only falls, so in practice this is an
	 * assignment.
	 */
	foreach ($b['foes'] as $i => $f) {
		if (empty($f['bossKey'])) continue;
		$k = $f['bossKey'];
		if (!isset($live[$k])) continue;
		$was = max(0, (int)$f['hp']);
		$now = max(0, min($was, (int)$live[$k]));
		if ($now === $was) continue;
		$b['foes'][$i]['hp'] = $now;
		if ($now <= 0) {
			if (!empty($b['foes'][$i]['ko'])) continue;
			$b['foes'][$i]['ko'] = true;
			/* Not this Crew's kill, or dhca_turn() would have called it. */
			if (empty($fresh[$k])) $b['log'][] = '☠ ' . $f['name'] . ' fell to another Crew.';
		} elseif (empty($fresh[$k])) {
			/*
			 * SAY WHY THE BAR JUMPED. A health bar that drops on its own is
			 * the kind of unexplained thing players report as a bug. One
			 * per cent of the boss keeps a trickle of concurrent chip
			 * damage from burying this Crew's own log.
			 */
			$mx = max(1, (int)$f['maxHp']);
			if (($was - $now) * 100 >= $mx)
				$b['log'][] = '⚔ Another Crew is on ' . $f['name']
				            . ' — ' . number_format($now) . ' left.';
		}
	}
}

/**
 * One move. The exchange itself is dhca_turn() -- the Arena's, not a copy.
 */
function dhcb_move($conn, $user_id, $run_id, $a, $z) {
	$b = dhcb_load($conn, $run_id, $user_id);
	if (!$b) return array(false, 'No run in progress.', null);

	/*
	 * THIS MOVE'S DAMAGE, MEASURED AS A DELTA ACROSS THE EXCHANGE, because
	 * hp0 - hp is everybody's damage now that the board follows the shared
	 * bar. The difference either side of dhca_turn() is this Crew's alone.
	 *
	 * A RUN THAT PREDATES PER-MOVE COMMIT measures from hp0 instead, once:
	 * it has been fighting a private snapshot and owes the bar everything
	 * it has dealt so far. meta.sent is the marker, and it exists from the
	 * first commit onward, so this is true exactly once per legacy run.
	 */
	$owed   = !isset($b['meta']['sent']);
	$before = array();
	foreach ($b['foes'] as $i => $f)
		$before[$i] = $owed && isset($f['hp0']) ? (int)$f['hp0'] : (int)$f['hp'];

	list($ok, $msg) = dhca_turn($b, $a, $z);
	if (!$ok) return array(false, $msg, $b);

	$dealtNow = array();
	foreach ($b['foes'] as $i => $f) {
		if (empty($f['bossKey'])) continue;
		/* NEGATIVE TOO: a drain boss heals itself between moves and the
		   bar has to see that, or the heal is banked as damage and then
		   erased off this board by the next re-sync. */
		$d = $before[$i] - max(0, (int)$f['hp']);
		if ($d !== 0) $dealtNow[$f['bossKey']] = $d;
	}
	dhcb_commit($conn, $b, $dealtNow);

	/*
	 * THE COMMIT CAN END THE RUN, so the check happens after it rather than
	 * only inside dhca_turn(). A boss this Crew never touched can come back
	 * from that read already dead, and if it was the last one standing the
	 * run is over -- with every point this Crew landed already banked.
	 */
	if ($b['over'] === null) {
		dhca_check_over($b);
		if ($b['over'] !== null) dhca_final_hurrah($b);
	}

	if ($b['over'] !== null) dhcb_finish($conn, $b);
	else                     dhcb_save($conn, $run_id, $user_id, $b);
	return array(true, '', $b);
}

/* ---------- the end of a run ------------------------------------------------ */

/**
 * Close a run: record it, bench the Crew, announce it.
 *
 * IT NO LONGER COMMITS ANY DAMAGE. dhcb_commit() did that on every move, as
 * the damage was dealt -- read the long note there for why it moved. What
 * is left here is bookkeeping over a figure that is already true, and the
 * figure is meta.sent: the sum of what this run ACTUALLY took off the
 * shared bars, under a row lock, move by move.
 *
 * Idempotent. A retried request finds outcome already set and does nothing,
 * because a double-settled run would bench the Crew twice and post twice.
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

	$cleared = ($b['over'] === 'mine');                  // the crew was down when this ended
	$outcome = $cleared ? 1 : 2;
	$wave    = isset($m['wave']) ? max(1, (int)$m['wave']) : 1;

	/*
	 * A RUN THAT PREDATES PER-MOVE COMMIT, closed without making another
	 * move -- the stale sweep reaching one that was mid-fight when this
	 * shipped. It owes the bar everything it dealt, so it pays it here,
	 * through the same function and the same row lock as every other
	 * commit. There is one damage path, not two.
	 */
	if (!isset($b['meta']['sent'])) {
		$owed = array();
		foreach ($b['foes'] as $f) {
			if (empty($f['bossKey'])) continue;
			$d = (isset($f['hp0']) ? (int)$f['hp0'] : (int)$f['maxHp']) - max(0, (int)$f['hp']);
			if ($d > 0) $owed[$f['bossKey']] = $d;
		}
		dhcb_commit($conn, $b, $owed);
		$m = $b['meta'];
	}

	/* WHAT LANDED, AND WHAT THIS CREW DROPPED. Both were written under the
	   row lock at the moment they happened, so neither can be contradicted
	   by anything that has occurred since. */
	$applied = isset($m['sent'])   && is_array($m['sent'])   ? $m['sent']   : array();
	$killed  = isset($m['killed']) && is_array($m['killed']) ? $m['killed'] : array();
	$total   = 0;
	foreach ($applied as $v) $total += max(0, (int)$v);

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
	/*
	 * THE CREW THIS RUN FOUGHT, for the share and the end card.
	 *
	 * Read AFTER the spawn memo is cleared and scoped to this run's own
	 * wave, so a Crew that just dropped the last boss is described against
	 * the crew it dropped. Unscoped it read whatever had stood up in the
	 * meantime, and the share for a clear said "CIP-666 is down, and the
	 * DHC Boss Crew is now 0% down" -- nought per cent being the untouched
	 * replacement.
	 */
	$b['waveProg'] = dhcb_progress(dhcb_state($conn, $day, $wave));

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
/*
 * A RUN THE CLAMP ATE IS STILL A RUN.
 *
 * Damage is capped at the health actually left on the shared bar, which is
 * right while two people are racing a live boss -- between them they cannot
 * remove more than was there. But when the crew DIES mid-run, everyone
 * still out there commits against empty bars and records a flat zero.
 *
 * Observed on the first clear: a player went twenty rounds and banked
 * nothing. Zero cost them three separate things -- the round's cache slot,
 * the week's trait for that round, and, worst of the three, ladder
 * position, because the average is damage over Fighters FIELDED and a
 * clamped run adds three to the denominator and nothing to the numerator.
 * Being unlucky made their rank worse.
 *
 * So participation is damage OR a real fight. Five rounds cannot be reached
 * by forfeiting, and anything shorter than that which mattered has already
 * cleared DHCB_MIN_DEALT on damage alone.
 */
if (!defined('DHCB_MIN_ROUNDS')) define('DHCB_MIN_ROUNDS', 5);
/* Qualifying floor for the weekly board. Three is one full day, so a single
   lucky run cannot top it and two days of play always qualifies. It was 5
   against a MONTH of runs, which over a week would have excluded most of
   the board. */
if (!defined('DHCB_LADDER_MIN_RUNS'))define('DHCB_LADDER_MIN_RUNS', 3);
/*
 * WHAT A ROUND BANKS ONCE THE WEEK HAS PUT A CREW DOWN.
 *
 * THE PROBLEM THIS ANSWERS. dhcb_tier_table() returns the BEST table the
 * moment the first crew falls, and placement_1 is the platform ceiling --
 * the same table first place on every weekly board gets. So the QUALITY
 * ladder is fully spent at wave 2 and the back half of a strong week has
 * nothing left to chase. Nothing breaks and there is no exploit (a round
 * still banks a trait and still feeds the ladder), but the pull goes.
 *
 * SO THE LEVER IS QUANTITY, NOT RARITY. A tier above placement_1 would
 * break a ceiling eight other games share, and the trait config is explicit
 * that guaranteeing mythics on a clearable boss would mint more in a
 * fortnight than exist. A round fought against wave 2 banks two traits,
 * wave 3 banks three, and that is where it stops.
 *
 * CAPPED AT THREE, AND THE CAP IS DOING REAL WORK. Later waves are budgeted
 * against the DAYS LEFT and floored at DHCB_WAVE_MIN_DAY, so they get
 * cheaper as the week runs out -- a simulation of the live turnout curve
 * puts the standing wave at 1 1 1 1 1 2 4 across the seven rounds, with two
 * crews falling inside the last round alone. Uncapped, the final round
 * would pay four times the first five for a wave that cost 0.6 days to
 * drop: that rewards the calendar, not the achievement. Three also matches
 * the Arena's three-drops-a-day ceiling, so neither game out-mints the
 * other.
 *
 * MEASURED BEFORE SHIPPING, on the live ladder's own shape: 18.4 traits a
 * week across the community becomes 26.3, which is 1.43x and about half a
 * mythic a week. The harness carries that curve as a case.
 */
if (!defined('DHCB_WAVE_TRAITS_MAX')) define('DHCB_WAVE_TRAITS_MAX', 3);

/** What one attended round banks, against the wave that was standing for it. */
function dhcb_round_traits($wave) {
	$w = (int)$wave;
	if ($w < 1) $w = 1;
	return min((int)DHCB_WAVE_TRAITS_MAX, $w);
}
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
	/* A run the clamp ate still counts -- damage OR a real fight. See
	   DHCB_MIN_ROUNDS; the same rule the cache grid uses, so the slot a
	   player can see and the trait they are paid cannot disagree. */
	$mr  = (int)DHCB_MIN_ROUNDS;
	$r = @$conn->query("SELECT user_id, SUM(dealt) AS dealt, COUNT(*) AS runs,
	                           SUM(crew_size) AS fighters, MAX(rounds) AS rounds_max,
	                           COUNT(DISTINCT CASE WHEN dealt >= $min OR rounds >= $mr
	                                 THEN " . skl_week_day_sql_for($day, 'started_at') . " END) AS days
	                      FROM dhc_boss_runs
	                     WHERE day = '$esc' AND outcome <> 0
	                     GROUP BY user_id");
	if (!$r) return $out;
	while ($x = $r->fetch_assoc()) {
		$out[(int)$x['user_id']] = array(
			'user_id'=>(int)$x['user_id'], 'dealt'=>(int)$x['dealt'],
			'runs'=>(int)$x['runs'], 'fighters'=>(int)$x['fighters'],
			'rounds_max'=>(int)$x['rounds_max'],
			'days'=>min((int)DHCB_CACHE_MAX, (int)$x['days']),
			/* Filled by the second read below. A player with no qualifying
			   round banks nothing, which is the same answer `days` gives. */
			'traits'=>0);
	}

	/*
	 * WHAT EACH ROUND IS WORTH, WHICH ONLY THE RUNS THEMSELVES KNOW.
	 *
	 * A round banks dhcb_round_traits() of the wave that was standing while
	 * it was played, and the wave moves DURING a week -- so the count above
	 * cannot answer it. dhc_boss_runs.wave is written at dhcb_start() for
	 * exactly this kind of question: a run records the crew it fought.
	 *
	 * MAX(wave) WITHIN A ROUND, because a wave can fall mid-round and the
	 * player who was there for both fought the later one. Grouped by round
	 * first and summed per player second, so a player who sent three Crews
	 * in one round still banks that round once.
	 *
	 * A SECOND READ RATHER THAN A CLEVERER FIRST ONE. The totals above are
	 * over EVERY finished run and this is over qualifying ROUNDS; folding
	 * two different populations into one GROUP BY is how the ladder query
	 * broke. This runs once a week, on an indexed column.
	 */
	$cap = (int)DHCB_WAVE_TRAITS_MAX;
	/* ANCHORED ON THE WEEK BEING SETTLED, not on the clock -- see the note
	   on skl_week_day_sql_for(). With the clock-anchored version every
	   round of a closed week collapsed into one group and everybody was
	   paid three traits. */
	$dayCol = skl_week_day_sql_for($day, 'started_at');
	$tq  = @$conn->query(
		"SELECT user_id, SUM(t) AS traits FROM (
		   SELECT user_id, $dayCol AS d,
		          LEAST($cap, GREATEST(1, MAX(wave))) AS t
		     FROM dhc_boss_runs
		    WHERE day = '$esc' AND outcome <> 0
		      AND (dealt >= $min OR rounds >= $mr)
		    GROUP BY user_id, $dayCol
		 ) r GROUP BY user_id");
	if ($tq) while ($x = $tq->fetch_assoc()) {
		$uid = (int)$x['user_id'];
		if (isset($out[$uid])) $out[$uid]['traits'] = max(0, (int)$x['traits']);
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
/**
 * Settle a week, or REPAY one that was settled wrongly.
 *
 * $repay pays the DIFFERENCE between what a player is owed and what the
 * payout row says they already got, and updates that row. It exists
 * because the first ever DHC Bosses payout gave everybody three traits:
 * the settle grouped rounds with a clock-anchored day index, which after
 * the 4pm Thursday turn collapsed every round of the closed week into one
 * block. See skl_week_day_sql_for().
 *
 * WHY NOT JUST DELETE THE ROWS AND RE-RUN. The row is the record that a
 * week was paid, and dropping it to re-run would pay the whole amount
 * again to anyone whose first payout happened to be right -- and lose the
 * audit trail of what was originally paid. Repay is additive and leaves
 * `slots` holding the true total.
 *
 * IDEMPOTENT, like the settle it mirrors: run twice and the second run
 * finds nothing owed.
 */
function dhcb_settle($conn, $day = null, $announce = true, $repay = false, $paceUs = 0) {
	if ($day === null) $day = dhcb_week($conn);
	$state = dhcb_state($conn, $day);
	$prog  = dhcb_progress($state);
	$table = dhcb_tier_table($prog);
	$tiers = defined('DHCF_TIERS') && isset(DHCF_TIERS[$table]) ? DHCF_TIERS[$table] : null;

	$paid = 0; $traits = 0; $who = array();
	foreach (dhcb_participants($conn, $day) as $uid => $p) {
		/* Damage OR a real fight -- see DHCB_MIN_ROUNDS. A run the clamp
		   ate still turned up, and the cache is a record of turning up. */
		if ($p['dealt'] < DHCB_MIN_DEALT && $p['rounds_max'] < DHCB_MIN_ROUNDS) continue;
		/*
		 * ONE TRAIT PER ROUND THEY TURNED UP, AND MORE ONCE THE WEEK HAS
		 * PUT A CREW DOWN -- see DHCB_WAVE_TRAITS_MAX.
		 *
		 * The cache still rewards coming back rather than grinding: the
		 * multiplier is per ROUND, so three Crews in one sitting bank that
		 * round once however many waves have fallen, and the only route to
		 * a full week is still seven separate visits.
		 *
		 * `days` is the fallback, not the rule. It is what this paid before
		 * waves had a value, and it answers for a run written before the
		 * `wave` column carried one -- never a silent nought, which on the
		 * payout path would quietly stop paying somebody.
		 */
		$slots = max(1, (int)$p['traits'] > 0
			? (int)$p['traits']
			: min((int)DHCB_CACHE_MAX, (int)$p['days']));
		$owed = $slots;
		if ($repay) {
			/* What this player was actually given. A missing row means they
			   were never paid at all, which the normal path below handles
			   -- repay is "pay whatever is outstanding", not "pay again". */
			$prev = null;
			$pr = @$conn->query(sprintf(
				"SELECT slots FROM dhc_boss_payouts WHERE day = '%s' AND user_id = %d",
				$conn->real_escape_string($day), (int)$uid));
			if ($pr && ($px = $pr->fetch_assoc())) $prev = (int)$px['slots'];
			if ($prev !== null) {
				$owed = $slots - $prev;
				if ($owed <= 0) continue;          // already whole
				$ok = @$conn->query(sprintf(
					"UPDATE dhc_boss_payouts SET slots = %d, paid_at = NOW()
					  WHERE day = '%s' AND user_id = %d",
					$slots, $conn->real_escape_string($day), (int)$uid));
				/* FAIL CLOSED. If the row cannot be updated the awards below
				   would be handed out with nothing recording them, and a
				   second run would hand them out again. */
				if (!$ok) continue;
				/*
				 * PACED, BECAUSE EVERY TRAIT POSTS TO DISCORD.
				 *
				 * A repay hands out a whole week in one loop -- 43 traits
				 * across seven players on the first one -- and discordmsg()
				 * neither retries nor throttles: a 429 is logged and the
				 * announcement is gone. webhooks.php already records that
				 * exact shape of incident, where a job "didn't run" and in
				 * fact had run, paid out, and lost only its announcements.
				 * Zero by default, so the weekly cron is unchanged.
				 */
				if (function_exists('dhcf_award'))
					for ($i = 0; $i < $owed; $i++) {
						dhcf_award($conn, (int)$uid, 'wildcard', 'dhcbosses',
						           'the weekly boss crew', $tiers);
						if ($paceUs > 0) usleep((int)$paceUs);
					}
				$paid++; $traits += $owed; $who[] = (int)$uid;
				continue;
			}
		}
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
	/* Blocks of the week, not calendar dates -- see skl_week_day_sql().
	   The wave comes back with them because a slot is no longer worth one
	   trait flat: a round fought after a crew fell banks more, and a box
	   that does not say so is a box the player cannot read. Same MAX(wave)
	   per round as the settle, so the grid and the payout cannot disagree. */
	$r = @$conn->query("SELECT " . skl_week_day_sql_for($week, 'started_at') . " AS d,
	                           GREATEST(1, MAX(wave)) AS w
	                      FROM dhc_boss_runs
	                     WHERE user_id = $uid
	                       AND day = '" . $conn->real_escape_string($week) . "'
	                       AND outcome <> 0
	                       AND (dealt >= " . (int)DHCB_MIN_DEALT
	                     . " OR rounds >= " . (int)DHCB_MIN_ROUNDS . ")
	                     GROUP BY " . skl_week_day_sql_for($week, 'started_at'));
	if ($r) while ($x = $r->fetch_assoc()) $done[(int)$x['d']] = (int)$x['w'];

	/* What a round played RIGHT NOW would bank, for the slots not yet
	   earned. One read of the standing wave, not one per slot. */
	$nowWave = dhcb_wave($conn, $week);
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
		/* A past round is worth what it WAS worth; a round still to come is
		   worth what the crew standing now would pay, which is the whole
		   reason to show it. A missed one is worth nothing and says so. */
		$worth = ($st === 'filled') ? dhcb_round_traits($done[$i])
		       : (($st === 'missed') ? 0 : dhcb_round_traits($nowWave));
		$out[] = array('index' => $i, 'label' => (string)($i + 1), 'state' => $st,
		               'traits' => $worth);
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
	/*
	 * EVERYBODY WHO TURNED UP IS ON THE BOARD; the minimum decides RANK,
	 * not visibility. It was a HAVING, so a player with two finished Crews
	 * was simply absent -- and early in a week that is everybody. Round
	 * four of the first week and the board had one name on it, which reads
	 * as a dead feature rather than as a board filling up.
	 *
	 * AND THE DENOMINATOR ONLY COUNTS FIGHTERS THAT LANDED SOMETHING. The
	 * average is damage over Fighters fielded, and a run the clamp ate --
	 * everyone still out there when the crew dies commits against empty
	 * bars -- added three to the bottom and nothing to the top, so bad
	 * luck lowered your own rank. The totals are still reported, because
	 * Crews and Fighters sent are worth seeing even though they decide
	 * nothing.
	 */
	$sql = "SELECT r.user_id, u.username, u.discord_id, u.avatar,
	               SUM(r.dealt) AS dealt, COUNT(*) AS runs,
	               SUM(r.crew_size) AS fighters, SUM(r.killed) AS kills,
	               SUM(CASE WHEN r.dealt > 0 THEN r.crew_size ELSE 0 END) AS scoring
	          FROM dhc_boss_runs r
	          INNER JOIN users u ON u.id = r.user_id
	         WHERE r.day = '$esc' AND r.outcome <> 0
	         GROUP BY r.user_id
	        HAVING SUM(r.crew_size) > 0
	         ORDER BY (COUNT(*) >= $min) DESC,
	                  (SUM(r.dealt) / GREATEST(1, SUM(CASE WHEN r.dealt > 0
	                                                  THEN r.crew_size ELSE 0 END))) DESC,
	                  SUM(r.dealt) DESC
	         LIMIT $lim";
	/*
	 * AGGREGATES, NOT SELECT ALIASES, in HAVING and ORDER BY.
	 *
	 * This read `HAVING fighters > 0` and `ORDER BY (runs >= 3) DESC`,
	 * using names defined in the SELECT inside expressions. MySQL accepts
	 * an alias standing alone there and is far less willing inside an
	 * expression, so the whole query failed and the board went blank --
	 * while the feed directly underneath it, reading the same week from
	 * the same table, listed a dozen finished Crews.
	 *
	 * AND A FAILED QUERY NO LONGER LOOKS LIKE AN EMPTY WEEK. It returned
	 * array() either way, so "nobody has finished a Crew against them yet"
	 * was printed over a week somebody had spent all evening on. false is
	 * a broken read; array() is a quiet week; they are not the same thing
	 * and the page says so.
	 */
	$res = @$conn->query($sql);
	if (!$res) return false;
	$out = array();
	while ($x = $res->fetch_assoc()) {
		$f = max(1, (int)$x['fighters']);
		/* Only Fighters from runs that landed damage divide the total -- a
		   run the clamp ate must not drag the average down. */
		$sc = max(1, (int)$x['scoring']);
		$out[] = array(
			'user_id'=>(int)$x['user_id'],
			'name'=>($x['username'] !== '' ? $x['username'] : 'Unknown'),
			'discord_id'=>$x['discord_id'], 'avatar'=>$x['avatar'],
			'dealt'=>(int)$x['dealt'], 'runs'=>(int)$x['runs'],
			'fighters'=>$f, 'kills'=>(int)$x['kills'],
			'per'=>(int)round((int)$x['dealt'] / $sc),
			/* Ranked, or how many Crews short. The payout walks only the
			   ranked rows; the rest are on the board to be told what they
			   need, not to be paid. */
			'ranked'=>((int)$x['runs'] >= $min),
			'needs'=>max(0, $min - (int)$x['runs']),
		);
	}
	return $out;
}

/* ---------- the siege map --------------------------------------------------- */

/*
 * THE WEEK AS A PICTURE: the crew in the middle, everyone who attacked it
 * ringed around it.
 *
 * The ladder answers "where do I stand". This answers "how is the week
 * actually going, and who is carrying it" -- which is a different question
 * and the one a shared bar invites. One cluster per player who sent a Crew,
 * sized by the damage they put into the bar, with the Fighters they fielded
 * drawn around them as real portraits.
 *
 * THE WINDOW IS WEEKS, NOT DAYS, AND THAT IS NOT COSMETIC. Rivalries windows
 * on `started_at >= NOW() - INTERVAL n DAY`, which is right for a rolling
 * feud. It is wrong here: the week turns at 4pm Thursday and a boss crew
 * belongs to exactly one of them, so a rolling seven days straddles two
 * crews and would show damage against a crew that no longer exists sitting
 * next to the one on the board. dhc_boss_runs.day IS the week key, so every
 * window is a range of keys and nothing can straddle.
 *
 * WAVES ARE THE TIME AXIS, NOT A RANK, which is the one thing a reader could
 * take wrongly. Wave 1 stands for about 4.2 of the week's 7 days and every
 * wave after it falls in well under one -- so "fought wave 5" means "turned
 * up on the last day", not "is strong". Encoding the wave a player reached
 * as distance or rank would look like a skill ranking and would actually be
 * measuring when they logged in. So waves live in the CENTRE, as the shells
 * of the crew itself, and a player's relationship to them is only which ones
 * they were present for.
 */

/** The week key n weeks before $week. Keys are the Thursday's date. */
function dhcb_week_back($week, $n) {
	$d = DateTime::createFromFormat('!Y-m-d', (string)$week, new DateTimeZone('America/Chicago'));
	if (!$d) return (string)$week;
	$d->modify('-' . (max(1, (int)$n) - 1) * 7 . ' days');
	return $d->format('Y-m-d');
}

/**
 * Everything the siege map draws, for a window of whole weeks.
 *
 * Returns players, the waves that stood in the window, and each player's
 * Fighters. Art is NOT resolved here -- dhcb_siege_art() does that, for the
 * same reason dhca_rivalry_art() is separate: the trait list is the
 * Fighter's recipe and the map only needs pictures.
 */
function dhcb_siege($conn, $weeks = 1, $week = null) {
	$weeks = max(1, min(26, (int)$weeks));
	if ($week === null) $week = dhcb_week($conn);
	$from = dhcb_week_back($week, $weeks);
	$ef   = $conn->real_escape_string($from);
	$ew   = $conn->real_escape_string($week);

	$out = array('weeks' => $weeks, 'week' => $week, 'from' => $from,
	             'players' => array(), 'waves' => array(), 'fighters' => array(),
	             'dealt' => 0, 'runs' => 0, 'crews' => 0, 'downed' => 0);

	/* ---- 1. who turned up, and what they put in ---- */
	/*
	 * outcome <> 0 is the same bar the ladder and the trait cache use: a run
	 * still in progress has not dealt anything the board can trust yet.
	 * `scoring` mirrors dhcb_ladder() exactly -- Fighters that landed
	 * something -- so the per-Fighter figure on this map and the one the
	 * ladder ranks on are the same number and cannot disagree.
	 */
	$res = @$conn->query(
		"SELECT r.user_id, u.username, u.discord_id, u.avatar,
		        COALESCE(SUM(r.dealt), 0) AS dealt, COUNT(*) AS runs,
		        COALESCE(SUM(r.crew_size), 0) AS fielded,
		        COALESCE(SUM(CASE WHEN r.dealt > 0 THEN r.crew_size ELSE 0 END), 0) AS scoring,
		        COUNT(DISTINCT r.day) AS weeks_in,
		        COUNT(DISTINCT CONCAT(r.day, ':', " . skl_week_day_sql_col('r.day', 'r.started_at') . ")) AS rounds,
		        MAX(r.wave) AS whi
		   FROM dhc_boss_runs r
		   INNER JOIN users u ON u.id = r.user_id
		  WHERE r.day >= '$ef' AND r.day <= '$ew' AND r.outcome <> 0
		  GROUP BY r.user_id");
	if ($res) while ($x = $res->fetch_assoc()) {
		$uid = (int)$x['user_id'];
		if ($uid <= 0) continue;
		$scoring = (int)$x['scoring'];
		$out['players'][$uid] = array(
			'id'      => $uid,
			'name'    => (string)$x['username'],
			'avatar'  => function_exists('avatar_url') ? avatar_url($x['discord_id'], $x['avatar']) : '',
			'dealt'   => (int)$x['dealt'],
			'runs'    => (int)$x['runs'],
			'fielded' => (int)$x['fielded'],
			/* Damage per Fighter that landed something -- dhcb_ladder()'s
			   ranking number, carried so the map can SAY why its biggest
			   circle is not always the board's first row. */
			'per'     => $scoring > 0 ? (int)round((int)$x['dealt'] / $scoring) : 0,
			'rounds'  => (int)$x['rounds'],
			'weeksIn' => (int)$x['weeks_in'],
			'top'     => max(1, (int)$x['whi']),
			'waves'   => array(),
			'kills'   => array(),
			'fighters'=> array(),
		);
		$out['dealt'] += (int)$x['dealt'];
		$out['runs']  += (int)$x['runs'];
	}
	if (!$out['players']) return $out;
	$in = implode(',', array_map('intval', array_keys($out['players'])));

	/* ---- 2. the waves that stood, and what each cost ---- */
	/*
	 * FROM dhc_boss_day, NOT FROM THE BUDGET FORMULA. max_health is what the
	 * crew was actually sized to on the night it spawned; recomputing it
	 * from dhcb_crew_budget() would re-derive today's community against last
	 * month's wave and draw a shell nobody ever fought.
	 */
	$res = @$conn->query(
		"SELECT day, wave, boss_key, max_health, health, killed_at, killed_by
		   FROM dhc_boss_day
		  WHERE day >= '$ef' AND day <= '$ew'
		  ORDER BY day ASC, wave ASC, boss_key ASC");
	$byWave = array();
	if ($res) while ($x = $res->fetch_assoc()) {
		$k = $x['day'] . '#' . (int)$x['wave'];
		if (!isset($byWave[$k])) {
			$byWave[$k] = array('day' => (string)$x['day'], 'wave' => (int)$x['wave'],
			                    'max' => 0, 'left' => 0, 'bosses' => array(),
			                    'cleared' => true, 'killers' => array());
		}
		$boss = dhcb_boss($x['boss_key']);
		$dead = ($x['killed_at'] !== null);
		$byWave[$k]['max']  += (int)$x['max_health'];
		$byWave[$k]['left'] += max(0, (int)$x['health']);
		$byWave[$k]['bosses'][] = array(
			'key'  => (string)$x['boss_key'],
			'name' => $boss ? $boss['name'] : (string)$x['boss_key'],
			'art'  => dhcb_art($x['boss_key']),
			'max'  => (int)$x['max_health'],
			'left' => max(0, (int)$x['health']),
			'dead' => $dead,
			'by'   => $dead ? (int)$x['killed_by'] : 0,
		);
		if (!$dead) $byWave[$k]['cleared'] = false;
		if ($dead && (int)$x['killed_by'] > 0) {
			$byWave[$k]['killers'][(string)$x['boss_key']] = (int)$x['killed_by'];
			if (isset($out['players'][(int)$x['killed_by']]))
				$out['players'][(int)$x['killed_by']]['kills'][] = (string)$x['boss_key'];
		}
	}
	foreach ($byWave as $w) {
		if ($w['cleared']) $out['downed']++;
		$out['waves'][] = $w;
	}

	/* ---- 3. which waves each player was present for ---- */
	/*
	 * THE SPOKE'S ONLY JOB. Every line on this map points at the same centre,
	 * so the line itself says nothing beyond "took part" -- it is the waves
	 * it crosses that carry when somebody was here. Scoped to ONE week,
	 * because wave numbers restart every Thursday and a union across four
	 * weeks would claim a player fought a wave 5 that four different crews
	 * each had.
	 */
	if ($weeks === 1) {
		$res = @$conn->query(
			"SELECT user_id, wave, COUNT(*) AS n, COALESCE(SUM(dealt), 0) AS dealt
			   FROM dhc_boss_runs
			  WHERE day = '$ew' AND outcome <> 0 AND user_id IN ($in)
			  GROUP BY user_id, wave");
		if ($res) while ($x = $res->fetch_assoc()) {
			$uid = (int)$x['user_id'];
			if (!isset($out['players'][$uid])) continue;
			$out['players'][$uid]['waves'][(int)$x['wave']] =
				array('runs' => (int)$x['n'], 'dealt' => (int)$x['dealt']);
		}
	}

	/* ---- 4. the Fighters each of them fielded ---- */
	/*
	 * THE PORTRAITS ARE THE POINT OF A CLUSTER, the same as on the Rivalries
	 * map: a ring of dots says how many, a ring of Fighters says who. Ordered
	 * by damage so the ones the renderer has room for are the ones that did
	 * the work.
	 */
	$res = @$conn->query(
		"SELECT rf.fighter_id, r.user_id, f.serial, f.name, f.traits,
		        COALESCE(SUM(rf.dealt), 0) AS dealt, COUNT(*) AS runs,
		        COALESCE(SUM(rf.fell), 0) AS fell
		   FROM dhc_boss_run_fighters rf
		   INNER JOIN dhc_boss_runs r ON r.id = rf.run_id
		   LEFT JOIN dhc_fighters f ON f.id = rf.fighter_id
		  WHERE r.day >= '$ef' AND r.day <= '$ew' AND r.outcome <> 0
		    AND r.user_id IN ($in)
		  GROUP BY rf.fighter_id, r.user_id, f.serial, f.name, f.traits
		  ORDER BY dealt DESC");
	if ($res) while ($x = $res->fetch_assoc()) {
		$uid = (int)$x['user_id'];
		if (!isset($out['players'][$uid])) continue;
		$traits = json_decode((string)$x['traits'], true);
		/*
		 * DISASSEMBLY IS A REAL DELETE -- dhcf_delete_fighter() removes the
		 * dhc_fighters row, deliberately, because dhc_trait_drops keeps the
		 * history. So the LEFT JOIN above returns a run with no Fighter
		 * behind it, and that is a NORMAL state, not a failure.
		 *
		 * SAID, NOT GUESSED AT. Left alone it fell through to serial 0 and
		 * dhcf_default_name(0) answered "DHC2F000" -- a Fighter number that
		 * cannot exist, printed next to real damage, with an empty disc
		 * where the art should be. A plausible wrong answer is worse than
		 * an empty one. The damage it dealt is real and stays in the
		 * player's total, so the portrait stays too; it is just labelled as
		 * what it is.
		 */
		$gone = ($x['serial'] === null);
		$out['fighters'][$uid][] = array(
			'id'      => (int)$x['fighter_id'],
			'serial'  => $gone ? 0 : (int)$x['serial'],
			'gone'    => $gone,
			'name'    => $gone ? 'Disassembled'
			             : (($x['name'] !== null && $x['name'] !== '')
			                ? (string)$x['name']
			                : (function_exists('dhcf_default_name')
			                   ? dhcf_default_name((int)$x['serial']) : '#' . (int)$x['serial'])),
			'traits'  => is_array($traits) ? $traits : array(),
			'dealt'   => (int)$x['dealt'],
			'runs'    => (int)$x['runs'],
			'fell'    => (int)$x['fell'],
		);
	}
	foreach ($out['players'] as $uid => $p)
		$out['players'][$uid]['crews'] = count(isset($out['fighters'][$uid]) ? $out['fighters'][$uid] : array());

	return $out;
}

/**
 * Swap each Fighter's trait set for the layers that draw it, and drop the
 * traits. Separate from dhcb_siege() for the reason dhca_rivalry_art() is
 * separate: the art directory is a property of the page, and the recipe must
 * not reach the client.
 */
function dhcb_siege_art(&$s, $webroot, $base) {
	foreach ($s['fighters'] as $uid => $list) {
		foreach ($list as $i => $f) {
			$layers = array();
			if (function_exists('dhcf_layers')) {
				foreach (dhcf_layers($f['traits'], $webroot) as $L) {
					$layers[] = array(
						/* The 250px masters. A portrait on this map is tens of
						   pixels across; the 1000px art would be a hundred
						   needless megabytes on a busy week. */
						'src'  => $base . '/250/' . $L['cat'] . '/' . $L['slug'] . '.png',
						'n'    => (float)$L['nudge'],
						'clip' => $L['clip'],
					);
				}
			}
			$s['fighters'][$uid][$i]['layers'] = $layers;
			unset($s['fighters'][$uid][$i]['traits']);
		}
	}
}

/**
 * The siege as the renderer wants it: nodes with radii, shells with radii.
 *
 * SIZED ON sqrt, TWICE, for the reason dhca_rivalry_layout() gives once --
 * area is what the eye reads on a circle, so a linear radius claims a
 * tenfold difference where there is one of ten in area. It matters more here
 * than there, because boss damage spans orders of magnitude where battle
 * counts do not.
 *
 * THE SHELLS ARE THE WAVES, nested, each sized on sqrt(max_health). That
 * geometry is the week's real shape rather than decoration: a wave is
 * budgeted against the DAYS LEFT, so wave 1 is a fat outer ring and the late
 * ones collapse inward exactly as fast as they get cheaper to drop.
 *
 * MIN_STEP exists because that collapse is severe. On a measured week the
 * last two waves land three pixels apart, and two shells three pixels apart
 * are one thick line -- the drawing would be honest and unreadable, which
 * is not honest.
 */
function dhcb_siege_layout($s, $rmin = 16, $rmax = 46, $shellMax = 120, $shellMin = 26, $step = 9) {
	$nodes = array();
	$biggest = 0;
	foreach ($s['players'] as $p) $biggest = max($biggest, $p['dealt']);

	foreach ($s['players'] as $id => $p) {
		$share = $biggest > 0 ? sqrt($p['dealt'] / $biggest) : 0;
		$node = $p;
		$node['r'] = (int)round($rmin + ($rmax - $rmin) * $share);
		$node['fighters'] = isset($s['fighters'][$id]) ? $s['fighters'][$id] : array();
		$nodes[$id] = $node;
	}

	/* The shells, outermost (wave 1) first. Only the single-week view has
	   shells at all -- see dhcb_siege(): wave numbers restart every week, so
	   a four-week window has four wave 1s and nesting them is nonsense. */
	$shells = array();
	$gap    = (float)$step;
	if ($s['weeks'] === 1 && $s['waves']) {
		/*
		 * CUMULATIVE HEALTH, NOT THE WAVE'S OWN, AND THAT IS THE WHOLE
		 * ORDERING.
		 *
		 * The first version sized each ring on its own max_health and then
		 * sorted by radius so they would nest. Wave health is NOT monotonic
		 * -- the live wave stands at full health while the late ones it
		 * followed were cheap and are already down -- so a real week came
		 * out drawn 1, 2, 6, 3, 4, 5 and the numbers up the top read as
		 * nonsense. Reported by the owner. The premise of the whole centre
		 * is that reading inward is reading FORWARD through the week, and
		 * the spoke lengths depend on it, so that was not cosmetic.
		 *
		 * Ring i is sqrt of the health of wave i AND EVERY WAVE AFTER IT.
		 * That sum strictly shrinks, so wave order is monotonic by
		 * construction and cannot be sorted wrong. It is also the better
		 * encoding: r^2 difference between consecutive rings is exactly that
		 * wave's own health, so the BAND between two rings has an area
		 * proportional to the wave -- and area is what the eye reads on a
		 * circle, which is the same principle dhca_rivalry_layout() argues
		 * for on the player discs.
		 *
		 * The live wave is therefore always innermost, which the renderer
		 * relies on: the boss portraits are drawn inside it, and under the
		 * old size ordering they could land on top of the rings nested
		 * within.
		 */
		$waves = $s['waves'];
		usort($waves, function ($a, $b) { return $a['wave'] <=> $b['wave']; });
		$cum = array();
		$run = 0;
		for ($i = count($waves) - 1; $i >= 0; $i--) { $run += (int)$waves[$i]['max']; $cum[$i] = $run; }
		$total = $run;
		foreach ($waves as $i => $w) {
			$sh = $w;
			$sh['r'] = $total > 0
				? (int)round($shellMin + ($shellMax - $shellMin) * sqrt($cum[$i] / $total))
				: $shellMax;
			$shells[] = $sh;
		}
		/*
		 * OUTERMOST FIRST, THEN FORCED APART IN ONE PASS INWARD. Walking
		 * inward keeps wave 1 at the radius it earned and spends the squeeze
		 * on the late waves, which are the ones that cost nothing anyway.
		 *
		 * THE STEP SHRINKS WHEN THE WAVES OUTNUMBER THE ROOM. A fixed 9px
		 * gap is right for the five or six waves a week usually reaches and
		 * walks straight past zero on a week that reached fifteen -- shells
		 * at negative radii, which SVG draws as nothing at all. Dividing the
		 * room by the shells that have to fit in it cannot do that.
		 *
		 * This was two passes, a forward clamp and a backward floor, and
		 * EITHER ONE ALONE ENFORCED THE SPACING -- so disabling one changed
		 * nothing and a harness could not tell which was load-bearing.
		 * Mutation found it. One pass, one rule.
		 */
		/* NO RE-SORT. They are already in wave order and the cumulative rule
		   guarantees the radii descend with it; sorting by radius again is
		   what produced 1, 2, 6, 3, 4, 5 in the first place. */
		$floor = 10.0;
		$n     = count($shells);
		$gap   = $n > 1 ? min((float)$step, max(0.5, ($shells[0]['r'] - $floor) / ($n - 1)))
		                : (float)$step;
		/* Every shell has to leave room for all the ones inside it. On a
		   normal week this binds nowhere and the radii stay exactly what the
		   health earned them; on a deep one it pushes the middle outward
		   just far enough, degrading toward even spacing only where there is
		   genuinely no room to be proportional. */
		for ($i = 0; $i < $n; $i++)
			$shells[$i]['r'] = max((float)$shells[$i]['r'], $floor + ($n - 1 - $i) * $gap);
		/* And clear the one outside it. */
		for ($i = 1; $i < $n; $i++)
			$shells[$i]['r'] = min((float)$shells[$i]['r'], $shells[$i - 1]['r'] - $gap);
		foreach ($shells as $i => $sh) $shells[$i]['r'] = (int)round($sh['r']);
	}

	/* The per-week summary the wide views get instead of shells: how deep
	   each week got, in order, so the trend reads across weeks rather than
	   the structure within one. */
	$byWeek = array();
	foreach ($s['waves'] as $w) {
		$k = $w['day'];
		if (!isset($byWeek[$k])) $byWeek[$k] = array('day' => $k, 'top' => 0, 'downed' => 0, 'max' => 0);
		$byWeek[$k]['top'] = max($byWeek[$k]['top'], (int)$w['wave']);
		$byWeek[$k]['max'] += (int)$w['max'];
		if (!empty($w['cleared'])) $byWeek[$k]['downed']++;
	}
	ksort($byWeek);

	/*
	 * THE CREW ITSELF, ALWAYS, whatever the window.
	 *
	 * The wider views had no bosses in the middle at all -- the shells are a
	 * single week's structure, the portraits were drawn inside the live
	 * shell, and with no shells there was nothing but a bar chart. Every
	 * spoke on the map then pointed at nothing, which is not a picture of a
	 * siege. The three bosses are static (dhcb_bosses()), so there is no
	 * reason the centre of a 13-week view cannot be the thing all 13 weeks
	 * were spent attacking.
	 */
	$crew = array();
	foreach (dhcb_bosses() as $b)
		$crew[] = array('key' => $b['key'], 'name' => $b['name'], 'art' => dhcb_art($b['key']));

	return array('nodes' => $nodes, 'shells' => $shells, 'shellGap' => $gap, 'crew' => $crew,
	             'weeks' => $s['weeks'], 'week' => $s['week'], 'from' => $s['from'],
	             'byWeek' => array_values($byWeek),
	             'dealt' => $s['dealt'], 'runs' => $s['runs'], 'downed' => $s['downed'],
	             'biggest' => $biggest);
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
	/* A BROKEN READ IS NOT AN EMPTY WEEK. dhcb_ladder() returns false when
	   the query failed, and saying "nobody has played" over a week people
	   have been playing all evening is worse than saying nothing. */
	if ($rows === false) {
		return '<p class="a-sub" style="margin:0">The board could not be read just now. '
		     . 'Nothing is lost -- every Crew is recorded. Try again in a moment.</p>';
	}
	if (!$rows) return '<p class="a-sub" style="margin:0">' . htmlspecialchars($empty) . '</p>';
	$h = '<table class="a-lad"><thead><tr><th>#</th><th>'
	   . ($guest ? 'Player' : 'Staker')
	   . '</th><th>Per fighter</th><th>Damage</th><th>Crews</th></tr></thead><tbody>';
	/*
	 * THE UNRANKED SIT UNDER THE BOARD, not off it.
	 *
	 * The minimum decides who is PAID, and dhcb_ladder() now returns
	 * everybody who turned up so the page can say "one more Crew" instead
	 * of leaving somebody invisible. They get no position number and the
	 * row says what they need.
	 */
	$pos = 0; $brokeOut = false;
	foreach ($rows as $i => $l) {
		$unranked = isset($l['ranked']) && !$l['ranked'];
		if (!$unranked) $pos++;
		/*
		 * A LINE THAT SAYS WHAT THE DIM ROWS ARE.
		 *
		 * They were simply greyed with a dash for a position and a "+1" in
		 * the Crews column, and the only explanation was a paragraph under
		 * the table. The owner looked at their own board and asked why
		 * some rows were subdued, which settles it: if the person who
		 * asked for the feature cannot read it at a glance, nobody can.
		 *
		 * The rule goes between the two groups, where the question gets
		 * asked, and the "+N" goes away with it -- the line says three are
		 * needed and the row says how many they have.
		 */
		if ($unranked && !$brokeOut) {
			$brokeOut = true;
			$h .= '<tr class="lad-break"><td colspan="5">Not yet ranked &middot; '
			    . (int)DHCB_LADDER_MIN_RUNS . ' finished Crews puts you on the board</td></tr>';
		}
		$me = ((int)$l['user_id'] === (int)$user_id) ? ' class="me"' : '';
		if ($unranked) $me = ' class="unranked' . ($me !== '' ? ' me' : '') . '"';
		/* The Fighter count rides the Crews cell rather than taking a sixth
		   column: the panel is 450px and six columns of figures in it is a
		   table nobody reads. Crews is the number a player is counting. */
		$h .= '<tr' . $me . '>'
		    . '<td class="r">' . ($unranked ? '&ndash;' : $pos) . '</td>'
		    . '<td>' . htmlspecialchars($l['name']) . '</td>'
		    . '<td class="r">' . number_format($l['per']) . '</td>'
		    . '<td class="r">' . number_format($l['dealt']) . '</td>'
		    . '<td class="r" title="' . (int)$l['fighters'] . ' Fighters fielded">'
		    . (int)$l['runs']
		    . '</td>'
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
		/*
		 * NO $day, SO THE NEXT WAVE IS STOOD UP FIRST.
		 *
		 * dhcb_state($conn, $day) with an explicit day SKIPS dhcb_spawn(),
		 * and this runs microseconds after the kill that emptied the crew.
		 * So the post read the wave it had just finished and announced
		 * "they are getting back up angrier -- wave 1 is standing now",
		 * naming the crew that had died rather than the one that replaced
		 * it. The day is the current week anyway; letting dhcb_state()
		 * resolve it is what makes the respawn happen before the read.
		 */
		$state = dhcb_state($conn);
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
		/*
		 * DID *THIS* RUN DROP THE CREW, not "is the crew down".
		 *
		 * This asked whether the wave had moved on, which is true for
		 * EVERY run still out there when somebody else finished it -- so a
		 * player whose Crew was wiped for nothing got a post headed "wave
		 * 1 is down" claiming a clear they had no part in, two minutes
		 * after the player who actually did it.
		 *
		 * $killedKeys is this run's own kills, counted under the row lock
		 * and only when the bar still had health. The crew went down to
		 * THIS Crew when this run killed something and nothing is left.
		 */
		$wiped    = !empty($kname) && !empty($prog['cleared'])
		          || (!empty($kname) && $prog['wave'] > $fellWave);
		/*
		 * AND THE THIRD STATE: THE CREW FELL WHILE THIS ONE WAS OUT THERE.
		 *
		 * $wiped is deliberately narrow -- it means THIS Crew finished them
		 * -- so an assisting run fell through to the else, which prints the
		 * percentage of $prog. $prog is the wave that stood up in this same
		 * request, so the post read "Crew at 0% down" about a crew that had
		 * just been defeated: the single most misleading thing either
		 * surface could say, and the owner caught it. Damage commits per
		 * move now, so a run ENDS when the crew falls under it, which makes
		 * this the common case rather than a rare one.
		 *
		 * waveProg is the crew this run actually fought, read in
		 * dhcb_finish() before the announcement.
		 */
		$mineProg = isset($b['waveProg']) ? $b['waveProg'] : null;
		$assist   = !$wiped && $mineProg && !empty($mineProg['cleared']);
		$title = $wiped
			? '☠️ DHC Bosses — wave ' . $fellWave . ' is down'
			: ($kname
				? '💀 DHC Bosses — ' . implode(' and ', $kname) . ' is down'
				: '🩸 DHC Bosses — ' . number_format((int)$dealt) . ' damage');
		$desc  = '**' . $me['name'] . '** sent ' . implode(', ', $crew) . "\n"
		       . number_format((int)$dealt) . ' damage dealt' . "\n\n";
		/* WHAT A ROUND BANKS FROM HERE. The best table locked on the FIRST
		   crew of the week, so every wave after it adds nothing to the
		   odds -- what it adds is count, and a post that only says "best
		   table, locked in" reads as nothing new to anyone who was here
		   for the last one. */
		$rt   = dhcb_round_traits($prog['wave']);
		$more = $rt > 1 ? "\n\n" . 'Every round you send a Crew now banks **' . $rt
		                . ' traits** instead of one.' : '';
		if ($wiped) {
			$desc .= 'That is **' . $prog['downed'] . ' crew'
			       . ($prog['downed'] === 1 ? '' : 's') . ' dropped this week**, and the '
			       . 'best trait table is locked in for everyone who takes part.' . $more . "\n\n"
			       . 'They are getting back up angrier — **wave ' . $prog['wave'] . '** is '
			       . 'standing now.' . "\n"
			       . implode(' · ', $bars);
		} elseif ($assist) {
			$desc .= 'Wave **' . $fellWave . '** was **DEFEATED** while this Crew was still '
			       . 'out there. Every point it landed counts.' . $more . "\n\n"
			       . 'They are getting back up angrier — **wave ' . $prog['wave'] . '** is '
			       . 'standing now.' . "\n"
			       . implode(' · ', $bars);
		} else {
			$desc .= 'Wave **' . $prog['wave'] . '**' . "\n"
			       . implode(' · ', $bars);
			/* NO NOUGHT. A crew whose damage rounds away is described as
			   standing, not as "0% defeated" -- the per-boss bars above
			   already carry everything a zero could have said. */
			$pc = dhcb_pct_standing($prog['pct']);
			$desc .= "\n" . ($pc > 0 ? 'Crew **' . $pc . '%** defeated.'
			                         : 'The crew is still standing.');
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
			/*
			 * THE FIGHTER'S SHARE OF WHAT LANDED, not what it swung.
			 *
			 * mine[]['dealt'] is damage dealt to this run's SNAPSHOT, and
			 * the shared bar clamps what actually lands. A post read "0
			 * damage dealt" and then "OrbiTall! landed the most of them:
			 * 1,388" three lines below it -- two numbers from either side
			 * of the clamp, contradicting each other in the same embed.
			 *
			 * Scaled by the run's committed total, so the figures sum to
			 * what the bar actually lost and a fully clamped run claims
			 * nothing at all.
			 */
			$bestI = -1; $bestD = -1; $swing = 0;
			foreach ($b['mine'] as $f) $swing += isset($f['dealt']) ? (int)$f['dealt'] : 0;
			foreach ($b['mine'] as $i => $f) {
				$d = isset($f['dealt']) ? (int)$f['dealt'] : 0;
				if ($d > $bestD) { $bestD = $d; $bestI = $i; }
			}
			$bestD = ($swing > 0) ? (int)round((int)$dealt * $bestD / $swing) : 0;
			$fid = ($bestI >= 0 && isset($b['meta']['mineIds'][$bestI]))
			     ? (int)$b['meta']['mineIds'][$bestI] : 0;
			if ($fid > 0) {
				/* dhc-card.php keys on SERIAL, not on the row id. */
				$q = @$conn->query("SELECT serial FROM dhc_fighters WHERE id = $fid LIMIT 1");
				if ($q && ($sr = $q->fetch_assoc()) && (int)$sr['serial'] > 0)
					$img = 'https://skulliance.io/staking/dhc-card.php?serial=' . (int)$sr['serial'];
			}
			/* Nothing landed means nothing to name. A clamped run says what
			   it did -- fought and lost the race -- rather than crediting a
			   Fighter with damage the bar never took. */
			if ($bestI >= 0 && $bestD > 0)
				$didIt = '**' . $b['mine'][$bestI]['name'] . '** landed the most of them: '
				       . number_format($bestD) . '.';
			elseif ((int)$dealt === 0)
				$didIt = 'The crew was already down when this Crew got there, so none of '
				       . 'it landed. The round still counts.';
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
		/* THE PERCENTAGE BELONGS TO THE CREW STILL STANDING, and by Thursday
		   that may be the third one of the week -- so a week that dropped two
		   crews and left the replacement untouched read "the crew took 0%
		   damage and held", which describes the opposite of what happened.
		   The crews already down are named before the survivor is. */
		$fell = isset($prog['downed']) ? (int)$prog['downed'] - ($cleared ? 1 : 0) : 0;
		$pc   = dhcb_pct_standing($prog['pct']);
		$desc  = ($cleared
				? 'All three went down. Everybody who took part rolls on the **best** table.'
				: (($fell > 0
					? 'The week **DEFEATED ' . $fell . ' crew' . ($fell === 1 ? '' : 's')
					  . '**, and the one that replaced them '
					  . ($pc > 0 ? 'took **' . $pc . '%** damage and held. ' : 'held. ')
					: 'The crew took **' . $pc . '%** damage and held. ')
				  . 'Everybody who took part rolls on the **' . $tl . '** table.'))
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
/**
 * The percentage a crew that is STILL STANDING is reported at.
 *
 * BOTH ENDS OF THE SCALE ARE LIES ABOUT A CREW THAT IS UP, and both were
 * being printed. A run whose damage rounded away posted "0% down", which
 * reads as a crew nobody has touched -- the owner's catch. The other end
 * arrived with the fix: 99.9% rounds to a hundred, so the sentence came out
 * "the DHC Boss Crew is 100% defeated and still standing", which contradicts
 * itself inside eight words.
 *
 * So a standing crew is never nought and never a hundred. Nought comes back
 * as nought for the caller to put into words, because the honest thing to
 * say there is not a number.
 */
function dhcb_pct_standing($pct) {
	$n = (int)round(100 * (float)$pct);
	if ($n >= 100) return 99;
	return $n > 0 ? $n : 0;
}

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
	/*
	 * AND A PERCENTAGE ONLY DESCRIBES A CREW THAT IS STILL STANDING.
	 *
	 * "The DHC Boss Crew is now 0% down" was the owner's catch, and it is
	 * the worst reading of the three: it says the crew took nothing, when
	 * the two ways to reach it are a crew that was just DEFEATED (the
	 * percentage belonging to the untouched wave behind it) and a run
	 * whose damage rounded away against a six-figure bar. A defeated crew
	 * is named as defeated and no number is offered; a standing crew that
	 * rounds to nought is said to be standing, also without one. A nought
	 * on a post is never worth the ambiguity it buys.
	 *
	 * BOSSES GO DOWN, THE CREW IS DEFEATED. Keeping the two verbs apart is
	 * what lets "X-K4T is down" and "the crew is 60% defeated" sit in the
	 * same sentence without the reader having to work out which is which.
	 */
	$down = dhcb_pct_standing(isset($prog['pct']) ? $prog['pct'] : 0);
	$mine = 'My DHC Fighter Crew inflicted ' . number_format((int)$dealt) . ' damage.';
	if (!empty($prog['cleared'])) {
		return $mine . ' The DHC Boss Crew has been DEFEATED.';
	}
	if ($killedNames) {
		return $mine . ' ' . implode(' and ', $killedNames)
		     . (count($killedNames) === 1 ? ' is' : ' are') . ' down, and the DHC Boss Crew '
		     . 'is ' . $down . '% defeated.';
	}
	if ($down <= 0) {
		return $mine . ' The DHC Boss Crew is still standing.';
	}
	return $mine . ' The DHC Boss Crew is ' . $down . '% defeated and still standing.';
}

/** The button, using the platform's own share helper. */
function dhcb_share_button($dealt, $killedNames, $prog) {
	if (!function_exists('shareOnXButton')) {
		if (is_file(__DIR__ . '/lib/share-x.php')) require_once __DIR__ . '/lib/share-x.php';
	}
	if (!function_exists('shareOnXButton')) return '';
	return shareOnXButton(dhcb_share_text($dealt, $killedNames, $prog), 'dhcbosses.php');
}
