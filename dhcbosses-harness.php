<?php
/**
 * dhcbosses-harness.php — the daily boss crew.
 *
 * Run: php dhcbosses-harness.php
 *
 * THREE THINGS THIS HAS TO PROVE, because all three fail silently.
 *
 *   The feature is an OVERRIDE of the Arena, not a copy of it. The moment
 *   somebody reimplements the turn loop or the Crew query here, the two
 *   drift and only one of them gets the next engine fix.
 *   The damage commit is clamped. Two players against the same bar can
 *   each take 800 off a boss that had 1,000, and the ledger must add up to
 *   1,000 -- otherwise the leaderboard is inflated by racing somebody.
 *   The reward curve has no flat spot. If stopping ever beat continuing,
 *   ten players who all talk to each other would find it in a week.
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
error_reporting(E_ALL & ~E_DEPRECATED);

$fail = 0;
function ok($cond, $what) {
	global $fail;
	if ($cond) { echo "  ok    $what\n"; return true; }
	$fail++; echo "  FAIL  $what\n"; return false;
}

/*
 * THE POST IS CAPTURED, NOT SENT, AND THAT IS WHY IT CAN BE READ.
 *
 * dhcb_webhooks_loaded() returns the moment function_exists('discordmsg') is
 * true, so defining it here means webhooks.php is never reached and every
 * announcement in this file lands in $GLOBALS['HB_POSTS'] instead of in the
 * server. Without it the wording below could only be checked by grepping the
 * source -- and a check that reads source text passes on code whose OUTPUT is
 * wrong, which is exactly how "0% down" shipped.
 */
$GLOBALS['HB_POSTS'] = array();
function discordmsg($title, $desc, $img = '', $a = null, $b = null, $c = null, $d = null) {
	$GLOBALS['HB_POSTS'][] = array('title' => (string)$title, 'desc' => (string)$desc);
	return true;
}
require_once __DIR__ . '/harness-strip.php';
require_once __DIR__ . '/dhcbosses-lib.php';
$LIB = harness_strip_comments(file_get_contents(__DIR__ . '/dhcbosses-lib.php'));
/* The Arena page, which is also the Bosses page -- read once, here, because
   both the wave checks and the interface checks below work on it. */
$ARE = file_get_contents(__DIR__ . '/dhcarena.php');
/* The turn endpoint, which decides what the end card is told. */
$ACT = file_get_contents(__DIR__ . '/ajax/dhcbosses-action.php');

/* ---------------------------------------------------------------- *
 * IT IS AN OVERRIDE, NOT A SECOND ARENA
 * ---------------------------------------------------------------- */
echo "nothing here is a second copy of the Arena\n";
/*
 * The whole design rests on this. Each of these is a thing the Arena
 * already does; a reimplementation here would work fine on the day it
 * was written and rot from the next engine change onward.
 */
ok(strpos($LIB, 'dhca_turn(') !== false,
   'the turn loop is not dhca_turn(), so a boss run no longer plays the Arena exchange');
ok(strpos($LIB, 'dhca_new_battle(') !== false,
   'the battle is not built by the Arena engine');
ok(strpos($LIB, 'dhca_crew(') !== false,
   'the roster is not dhca_crew(), so Fighters can look different in the two places');
ok(strpos($LIB, 'dhcf_award(') !== false,
   'traits are not awarded through dhcf_award(), so the tier tables are a second copy');
/* The two things it genuinely owns, and must NOT delegate. */
ok(strpos($LIB, 'dhc_boss_fighters') !== false && strpos($LIB, 'dhca_bench(') === false,
   'the boss cooldown writes the ARENA bench table, so fighting a boss would lock a Crew '
 . 'out of ranked play and the boss would cannibalise the activity it is scaled against');

/* ---------------------------------------------------------------- *
 * THE REWARD CURVE
 * ---------------------------------------------------------------- */
echo "\nmore damage is always worth more, with no flat spot to sit on\n";
$seen = array(); $prev = -1; $bad = '';
for ($pct = 0.0; $pct <= 1.0001; $pct += 0.01) {
	$t = dhcb_tier_table(array('pct' => $pct, 'cleared' => false));
	$rank = dhcf_table_rank($t);
	if ($rank < $prev) $bad = sprintf('%.0f%% pays worse than the step before it', $pct * 100);
	$prev = $rank;
	$seen[$t] = true;
}
ok($bad === '', 'the table gets WORSE somewhere as damage rises: ' . $bad);
ok(count($seen) >= 3, 'only ' . count($seen) . ' tables are reachable by damage alone');
/*
 * THE FULL CLEAR IS A STEP ABOVE THE GRADIENT, not the top of it. At 99%
 * with one boss on a sliver you must still be one table short, or there is
 * no reason to make the last push.
 */
$near = dhcb_tier_table(array('pct' => 0.99, 'cleared' => false));
$done = dhcb_tier_table(array('pct' => 1.00, 'cleared' => true));
ok($near !== $done, '99% pays the same as a full clear, so the last push is worth nothing');
ok(dhcf_table_rank($done) > dhcf_table_rank($near), 'the full clear does not pay the best table');
ok($done === 'placement_1', 'the clear table is ' . $done . ', not the best one');
/* And nothing in the gradient may reach the clear table. */
ok(!isset($seen['placement_1']), 'the best table is reachable without dropping all three');

/* ---------------------------------------------------------------- *
 * THE HEALTH BUDGET
 * ---------------------------------------------------------------- */
echo "\nthe crew is sized by runs that can be played, not by roster size\n";
/*
 * A player with forty Fighters still cannot send more than DHCB_DAILY_RUNS
 * Crews, so thirty of them cannot contribute damage. Scaling on the roster
 * makes the boss enormous because somebody owns a lot of art.
 */
/*
 * THE REAL dhcb_crew_budget(), NOT A COPY OF IT.
 *
 * This was a reimplementation of the formula, and it quietly stopped
 * testing anything: removing the clamp that stops a crew being sized
 * beyond what a week can physically deliver changed the library and left
 * the harness green, because the harness was grading its own arithmetic.
 * A stub connection answering the one query the real function asks is a
 * few more lines and actually exercises it.
 */
class HBCount {
	public $players = 0, $fighters = 0;
	/* The calibration ledger: how many finished runs, averaging what. Zero
	   runs is the week-one case, where the budget falls back to the constant
	   with the human haircut on it. */
	public $runs = 0, $avgDealt = 0;
	/* Crews a day this community sends. null means "model it as everybody
	   spending the full allowance", which is what the budget used to assume
	   and what the older checks here were written against. */
	public $rpd = null;
	/* The boss ledger's own rate: runs over elapsed days. Zero runs is the
	   week-one case, where the Arena estimate has to answer instead. */
	public $ledgerRuns = 0, $ledgerSpan = 1;
	public function real_escape_string($x) { return addslashes((string)$x); }
	public function query($sql) {
		if (stripos($sql, 'COUNT(*) AS fighters') !== false)
			return new HBRes2(array(array('fighters' => $this->fighters, 'players' => $this->players)));
		if (stripos($sql, 'AVG(dealt)') !== false)
			return new HBRes2(array(array('n' => $this->runs, 'd' => $this->avgDealt)));
		/* The boss ledger's own rate. Answered as "not enough yet" unless a
		   test asks otherwise, so the Arena estimate below is exercised. */
		if (stripos($sql, 'DATEDIFF(NOW(), MIN(started_at))') !== false)
			return new HBRes2(array(array('n' => $this->ledgerRuns, 'span' => $this->ledgerSpan)));
		if (stripos($sql, 'AS rpd') !== false)
			return new HBRes2(array(array('rpd' => $this->rpd === null
				? $this->players * DHCB_DAILY_RUNS : $this->rpd)));
		return new HBRes2();
	}
}
class HBRes2 {
	public $num_rows = 0; private $r; private $i = 0;
	public function __construct($r = array()) { $this->r = $r; $this->num_rows = count($r); }
	public function fetch_assoc() { return isset($this->r[$this->i]) ? $this->r[$this->i++] : null; }
}
function hb_conn($players, $fighters, $runs = 0, $avg = 0, $rpd = null,
                $ledRuns = 0, $ledSpan = 1) {
	$c = new HBCount();
	$c->players = $players; $c->fighters = $fighters;
	$c->runs = $runs; $c->avgDealt = $avg; $c->rpd = $rpd;
	$c->ledgerRuns = $ledRuns; $c->ledgerSpan = $ledSpan;
	return $c;
}
function hb_budget($players, $fighters, $days = 7, $wave = 1, $runs = 0, $avg = 0, $rpd = null) {
	return dhcb_crew_budget(hb_conn($players, $fighters, $runs, $avg, $rpd), $days, $wave);
}
/*
 * WHAT A RUN IS WORTH, AS THE BUDGET SEES IT. Every ceiling below has to be
 * computed from this and not from DHCB_DMG_PER_RUN -- the constant is the
 * greedy solver's figure and the budget deliberately does not use it, so a
 * ceiling built on the constant grades a different formula than the one
 * that ships. That is the exact mistake that let the crew be sized 1.8x too
 * big with every check green.
 */
function hb_per_run($wave = 1, $runs = 0, $avg = 0) {
	/* The memo inside dhcb_dmg_per_run() is per-process, so it is primed
	   once here with the no-ledger case and every caller sees the same
	   figure the budget saw. */
	return dhcb_dmg_per_run(hb_conn(1, 1, $runs, $avg)) / dhcb_wave_pow($wave);
}
$deep    = hb_budget(2, 400);     // two players, enormous collection
$crowd   = hb_budget(20, 60);     // twenty players, one Crew each
ok($crowd > $deep, 'twenty players field a smaller boss than two hoarders, so the scaling is '
                 . 'measuring collections rather than participation');
/*
 * THE FLOOR MUST NEVER EXCEED WHAT THE WEEK CAN DELIVER. It did: three
 * players with nine Fighters can field 21 runs, worth about 20,000 damage,
 * against a 24,000 floor -- they could play perfectly and still never clear
 * it, with nothing on screen to explain why.
 */
foreach (array(array(1,3), array(2,6), array(3,9), array(4,12)) as $tiny) {
	list($tp, $tf) = $tiny;
	$perDay = min($tp * DHCB_DAILY_RUNS, (int)floor($tf / DHCB_CREW_SIZE));
	$maxDmg = $perDay * 7 * hb_per_run();
	if (!ok(hb_budget($tp, $tf) <= $maxDmg,
	        "$tp players with $tf Fighters get a crew of " . number_format(hb_budget($tp, $tf))
	      . ' they could not clear in a perfect week (' . number_format($maxDmg) . ' is their ceiling)')) break;
}
ok(hb_budget(10000, 999999) === (int)DHCB_HP_MAX, 'the ceiling does not hold');
/* Monotonic in players, which a sane scaling must be. */
/*
 * AND IT MUST NOT BE CLEARABLE ON MONDAY.
 *
 * The crew stands for a week, so a budget sized against one day's runs
 * would fall on the first evening and leave six dead days -- the exact
 * failure weekly was chosen to avoid, and nothing else here would notice,
 * because a smaller boss breaks no other rule.
 */
foreach (array(array(10, 80), array(25, 200), array(5, 20)) as $cw) {
	list($cp, $cf) = $cw;
	$perDay = min($cp * DHCB_DAILY_RUNS, (int)floor($cf / DHCB_CREW_SIZE));
	$oneDay = $perDay * DHCB_DMG_PER_RUN;
	if (!ok(hb_budget($cp, $cf) > $oneDay * 1.5,
	        "$cp players with $cf Fighters could clear the week's crew in a day and a half, "
	      . 'leaving the rest of the week with nothing to fight')) break;
}

$mono = true; $last = 0;
for ($p = 1; $p <= 40; $p++) { $v = hb_budget($p, $p * 8); if ($v < $last) $mono = false; $last = $v; }
ok($mono, 'the budget falls as more players join, which would punish turnout');

/* ---------------------------------------------------------------- *
 * A RUN, PLAYED OUT THROUGH THE REAL ENGINE
 * ---------------------------------------------------------------- */
echo "\na boss run resolves, and the damage is attributable per boss\n";
$rar  = dhcf_rarity();
$mine = array(
	array('background'=>'hellscape','torso'=>'red-cyborg','head'=>'gas-mask'),
	array('background'=>'white','torso'=>'c38','head'=>'sentinel'),
	array('background'=>'bright-red','torso'=>'silver-cyborg','head'=>'c73'));

function hb_run($mine, $rar, $seed, $hpEach = 1000000) {
	$order = dhcb_bosses(); $kits = dhca_kits();
	$foeT = array(); $foeN = array();
	foreach ($order as $b) { $foeT[] = array(); $foeN[] = $b['name']; }
	$bat = dhca_new_battle($mine, $foeT,
		array('mine'=>array('A','B','C'), 'foes'=>$foeN), $rar, $seed);
	foreach ($order as $i => $b) {
		$bat['foes'][$i]['bossKey'] = $b['key'];
		$bat['foes'][$i]['art']     = dhcb_art($b['key']);
		$bat['foes'][$i]['maxHp']   = $hpEach;
		$bat['foes'][$i]['hp']      = $hpEach;
		$bat['foes'][$i]['hp0']     = $hpEach;
		$bat['foes'][$i]['power']   = (int)round(DHCA_POWER_BASE * DHCB_POW_MULT * $b['pow']);
		$bat['foes'][$i]['kit']     = $kits[$b['kit'] % count($kits)];
	}
	$bat['meta'] = array('attacker'=>1, 'mineIds'=>array(1,2,3), 'moves'=>array(), 'run_id'=>1);
	$turns = 0;
	while ($bat['over'] === null && $turns < 500) {
		$best = null; $bs = 0;
		foreach (dhca_all_slides() as $mv) {
			$sc = dhca_score_move($bat, 'mine', $mv[0], $mv[1]);
			if ($sc > $bs) { $bs = $sc; $best = $mv; }
		}
		if (!$best) { dhca_fill_board($bat); $turns++; continue; }
		list($o) = dhca_turn($bat, $best[0], $best[1]);
		if (!$o) break;
		$turns++;
	}
	return array($bat, $turns);
}

list($bat, $turns) = hb_run($mine, $rar, 7919);
ok($bat['over'] !== null, 'the run never resolved in 500 turns');
ok($turns > 0 && $turns < 500, "the run took $turns turns");
$perBoss = array(); $tot = 0;
foreach ($bat['foes'] as $f) {
	$d = $f['hp0'] - max(0, $f['hp']);
	$perBoss[$f['bossKey']] = $d; $tot += $d;
}
ok($tot > 0, 'the run dealt no damage at all');
ok(count($perBoss) === 3, 'damage is not attributed to all three bosses');
ok(array_sum($perBoss) === $tot, 'the per-boss damage does not add up to the total');
/* Every boss carries flat art rather than a trait stack -- the one place a
   boss is not simply a Fighter. */
$arted = 0;
foreach ($bat['foes'] as $f) if (!empty($f['art'])) $arted++;
ok($arted === 3, 'a boss is missing its flat art and would render as an empty trait stack');

/* ---- the calibration, which is the constant most likely to rot ---- */
echo "\nthe run length and damage still match what the constants were tuned to\n";
$T = 0; $D = 0; $n = 10;
for ($s = 1; $s <= $n; $s++) {
	list($bb, $tt) = hb_run($mine, $rar, $s * 7919);
	$T += $tt;
	foreach ($bb['foes'] as $f) $D += $f['hp0'] - max(0, $f['hp']);
}
$avgT = $T / $n; $avgD = $D / $n;
printf("  measured: %.1f turns, %d damage per run (constants say %d)\n",
       $avgT, round($avgD), DHCB_DMG_PER_RUN);
/*
 * A ranked Arena battle is about 15 turns. A boss run should be shorter and
 * nastier without being over before the board has been read -- POW_MULT 2.2
 * shipped first and gave eight-turn runs, which is an ambush rather than a
 * fight.
 */
ok($avgT >= 9 && $avgT <= 20, sprintf('a run is %.1f turns, outside the 9-20 band the power '
	. 'multiplier was tuned for -- retune DHCB_POW_MULT', $avgT));
/*
 * And the health budget is built from DHCB_DMG_PER_RUN, so if the engine is
 * retuned and this constant is not moved with it, every boss is the wrong
 * size and nothing errors.
 */
ok(abs($avgD - DHCB_DMG_PER_RUN) / max(1, DHCB_DMG_PER_RUN) < 0.35,
   sprintf('damage per run has drifted to %d against a constant of %d; the whole health '
	. 'budget is derived from that number', round($avgD), DHCB_DMG_PER_RUN));

/* ---------------------------------------------------------------- *
 * THE DAMAGE COMMIT, UNDER CONTENTION
 * ---------------------------------------------------------------- */
echo "\ntwo players cannot take more off a bar than it had on it\n";
/*
 * THE ONE PLACE TWO PLAYERS GENUINELY COLLIDE. Both start against a boss on
 * 1,000, both take 800 off their own snapshot, and between them they cannot
 * remove 1,600. If the clamp is wrong the bar goes negative or the ledger
 * records damage that never landed -- and the monthly board is ranked on
 * exactly that number, so racing somebody would pay.
 *
 * A stub connection rather than a live database: what is being tested is the
 * arithmetic and the ordering inside dhcb_finish(), which is where the bug
 * would be.
 */
class HBRes {
	public $num_rows = 0; private $rows; private $i = 0;
	public function __construct($rows = array()) { $this->rows = $rows; $this->num_rows = count($rows); }
	public function fetch_assoc() { return isset($this->rows[$this->i]) ? $this->rows[$this->i++] : null; }
}
class HBConn {
	public $health = array();     // boss_key => health, now
	public $maxHealth = array();  // boss_key => health at spawn (defaults to now)
	public $state = array();      // run_id => the saved battle JSON
	public $wave = 1;             // the wave these health rows belong to
	public $runs = array();       // array('d'=>round index, 'wave'=>int) -- the ledger
	public $who = 7;              // whose runs they are
	public $awards = 0;           // traits dhcf_award() was asked for
	public $weekDay = 3;          // which round of the week it is now
	public $runOutcome = 0;
	public $log = array();
	public $insert_id = 1;
	public function real_escape_string($s) { return addslashes((string)$s); }
	public function begin_transaction() { $this->log[] = 'BEGIN'; return true; }
	public function commit()   { $this->log[] = 'COMMIT'; return true; }
	public function rollback() { $this->log[] = 'ROLLBACK'; return true; }
	/**
	 * Group a run ledger the way a query asked for it.
	 *
	 * ROUNDS ONLY WHEN THE QUERY SAYS ROUNDS. The grouping key is taken
	 * from the GROUP BY clause -- the week-day expression groups by round,
	 * anything else (an id, say) groups by run -- and the aggregate from
	 * whether the select asks for MAX(wave) or MIN(wave). Without that the
	 * stub answers what the harness author meant rather than what the code
	 * actually wrote, which is the whole failure mode it exists to catch:
	 * mutating the GROUP BY to `id` went straight through the first cut.
	 */
	public function roll($q, $keyCol, $valCol) {
		/* NO GROUP BY AT ALL IS PER ROW, which is what the database would
		   do. Reading the tail from a stripos() that returned false takes
		   the WHOLE query, and the week-day expression is in the SELECT
		   too -- so dropping the GROUP BY entirely looked like grouping by
		   round and the mutation walked straight through. */
		$at      = stripos($q, 'GROUP BY');
		$byRound = ($at !== false)
		        && stripos(substr($q, $at), 'TIMESTAMPDIFF') !== false;
		$lowest  = stripos($q, 'MIN(wave)') !== false;
		$by = array(); $src = array();
		foreach ($this->runs as $i => $rr) {
			$k = $byRound ? (int)$rr['d'] : $i;        // per round, or per run
			$w = max(1, (int)$rr['wave']);
			$src[$k] = (int)$rr['d'];
			$by[$k] = isset($by[$k]) ? ($lowest ? min($by[$k], $w) : max($by[$k], $w)) : $w;
		}
		$rows = array();
		foreach ($by as $k => $w) $rows[] = array($keyCol => $src[$k], $valCol => $w);
		return $rows;
	}
	public function query($sql) {
		/*
		 * THE RUN STATE IS HANDLED BEFORE THE WHITESPACE IS COLLAPSED. The
		 * payload is a JSON battle and the log lines inside it carry real
		 * spacing; squeezing runs of whitespace out of it would hand back a
		 * different battle than the one that was saved.
		 */
		if (stripos(ltrim($sql), 'INSERT INTO dhc_boss_state') === 0) {
			if (preg_match("~VALUES \((\d+),(\d+),'(.*)',NOW\(\)\)~s", $sql, $m))
				$this->state[(int)$m[1]] = stripslashes($m[3]);
			return true;
		}
		if (stripos(ltrim($sql), 'SELECT state FROM dhc_boss_state') === 0) {
			preg_match('~run_id = (\d+)~', $sql, $m);
			$rid = $m ? (int)$m[1] : 0;
			return isset($this->state[$rid])
				? new HBRes(array(array('state' => $this->state[$rid]))) : new HBRes();
		}
		if (stripos(ltrim($sql), 'DELETE FROM dhc_boss_state') === 0) {
			preg_match('~run_id = (\d+)~', $sql, $m);
			if ($m) unset($this->state[(int)$m[1]]);
			return true;
		}
		$q = preg_replace('~\s+~', ' ', trim($sql));
		$this->log[] = $q;
		if (stripos($q, 'SELECT outcome FROM dhc_boss_runs') === 0)
			return new HBRes(array(array('outcome' => $this->runOutcome)));
		if (stripos($q, 'SELECT health FROM dhc_boss_day') === 0) {
			if (!preg_match("~boss_key = '([^']+)'~", $q, $m)) return new HBRes();
			$k = $m[1];
			if (!isset($this->health[$k])) return new HBRes();
			return new HBRes(array(array('health' => $this->health[$k])));
		}
		/* THE WHOLE CREW IN ONE READ, which is how dhcb_commit() re-syncs a
		   board on a move that dealt nothing, and how dhcb_state() describes
		   the crew a run fought. A stub that answered `true` here sent the
		   code down a path no real connection can produce. */
		if (stripos($q, 'SELECT boss_key, health FROM dhc_boss_day') === 0
		 || stripos($q, 'SELECT boss_key, wave, max_health, health') === 0) {
			$rows = array();
			foreach ($this->health as $k => $h) {
				$mx = isset($this->maxHealth[$k]) ? (int)$this->maxHealth[$k] : max(1, (int)$h);
				$rows[] = array('boss_key'=>$k, 'wave'=>$this->wave, 'max_health'=>$mx,
				                'health'=>(int)$h, 'killed_at'=>null, 'killed_by'=>0);
			}
			return new HBRes($rows);
		}
		/* What an announcement reads on its way past: who the player is, and
		   which wave is standing (dhcb_spawn's one-query "is it still up"). */
		/*
		 * THE CACHE GRID'S OWN READ, THROUGH roll() LIKE THE OTHER ONE.
		 *
		 * Executed rather than pattern-matched: whether three Crews in one
		 * round fill one box or three is a question about the RESULT, and the
		 * check that used to ask it only looked for the word DISTINCT -- which
		 * a correct GROUP BY rewrite does not contain.
		 *
		 * This branch spent a while answering with its own hand-rolled
		 * grouping, which meant it grouped by round whatever the query said
		 * and a mutated GROUP BY sailed through. roll() reads the grouping key
		 * and the aggregate off the query text, so the stub answers what the
		 * code asked for rather than what the harness author meant.
		 */
		if (stripos($q, 'FROM dhc_boss_runs') !== false && stripos($q, ' AS w') !== false
		    && stripos($q, 'GROUP BY') !== false) {
			return new HBRes($this->roll($q, 'd', 'w'));
		}
		/* dhcb_participants' per-round traits read: the same roll(), so a
		   GROUP BY or an aggregate changed here is seen too. */
		if (stripos($q, 'SELECT user_id, SUM(t) AS traits') === 0) {
			$per = $this->roll($q, 'd', 'w');
			/* THE CAP IS THE QUERY'S JOB, NOT THE STUB'S. Applying it here
			   unconditionally meant deleting LEAST() from the SQL changed
			   nothing and the mutation went through -- the stub was quietly
			   doing the work it was supposed to be checking. */
			/* The WEEK-DAY expression opens with LEAST(6, GREATEST(0, ...,
			   so a bare search for LEAST( is true of every one of these
			   queries and the mutation that deleted the real cap walked
			   through. Match the cap itself. */
			$capped = preg_match('~LEAST\\(\\s*\\d+\\s*,\\s*GREATEST\\(1,\\s*M(?:AX|IN)\\(wave\\)~', $q) === 1;
			$cap = (int)DHCB_WAVE_TRAITS_MAX;
			$t = 0;
			foreach ($per as $r) {
				$v = max(1, (int)$r['w']);
				$t += $capped ? min($cap, $v) : $v;
			}
			return new HBRes(array(array('user_id' => $this->who, 'traits' => $t)));
		}
		/* And its totals read, over every finished run. */
		if (stripos($q, 'SELECT user_id, SUM(dealt) AS dealt') === 0) {
			$days = array(); $dealt = 0; $rmax = 0;
			foreach ($this->runs as $rr) {
				$days[(int)$rr['d']] = true;
				$dealt += isset($rr['dealt']) ? (int)$rr['dealt'] : 500;
				$rmax = max($rmax, isset($rr['rounds']) ? (int)$rr['rounds'] : 12);
			}
			return new HBRes(array(array(
				'user_id'=>$this->who, 'dealt'=>$dealt, 'runs'=>count($this->runs),
				'fighters'=>3 * count($this->runs), 'rounds_max'=>$rmax,
				'days'=>count($days))));
		}
		/* Which round of the week it is now. */
		if (stripos($q, ' AS d') !== false && stripos($q, 'FROM') === false)
			return new HBRes(array(array('d' => $this->weekDay)));
		/* The standing wave, for what a round played now would be worth. */
		if (stripos($q, 'SELECT MAX(wave)') === 0)
			return new HBRes(array(array('w' => $this->wave)));
		/* The week key, which MySQL computes because the two clocks on this
		   host disagree -- so even a stub has to answer it. */
		if (stripos($q, 'SELECT ') === 0 && stripos($q, ' AS w') !== false
		    && stripos($q, 'FROM') === false)
			return new HBRes(array(array('w' => '2026-10-25')));
		if (stripos($q, 'SELECT username, discord_id, avatar FROM users') === 0)
			return new HBRes(array(array('username'=>'tester', 'discord_id'=>'', 'avatar'=>'')));
		if (stripos($q, 'SELECT wave, COUNT(*)') === 0) {
			$hp = 0; foreach ($this->health as $h) $hp += max(0, (int)$h);
			return new HBRes(array(array('wave'=>$this->wave, 'n'=>count($this->health), 'hp'=>$hp)));
		}
		if (stripos($q, 'UPDATE dhc_boss_day SET health =') === 0) {
			preg_match('~SET health = (-?\d+)~', $q, $hm);
			preg_match("~boss_key = '([^']+)'~", $q, $km);
			if ($hm && $km) $this->health[$km[1]] = (int)$hm[1];
			return true;
		}
		/*
		 * AN UNMATCHED SELECT COMES BACK EMPTY, NOT `true`.
		 *
		 * A real mysqli hands a SELECT a result object, so a stub that
		 * answers `true` sends the caller somewhere no connection can: the
		 * settle reaches dhcf_award(), which reaches dhcf_count_owned(),
		 * which calls fetch_assoc() on it and takes the whole harness down
		 * mid-file. An empty set is what a SELECT matching nothing really
		 * returns, and every caller already handles it.
		 */
		if (stripos($q, 'SELECT') === 0) return new HBRes(array());
		return true;                    // every other write is fire-and-forget here
	}
}

/** Build a finished battle shaped exactly as dhcb_finish() expects one. */
function hb_battle($snapshots, $dealt) {
	$foes = array();
	foreach ($snapshots as $key => $hp0) {
		$d = isset($dealt[$key]) ? $dealt[$key] : 0;
		$foes[] = array('bossKey'=>$key, 'hp0'=>$hp0, 'hp'=>max(0, $hp0 - $d),
		                'maxHp'=>$hp0, 'name'=>$key, 'ko'=>($hp0 - $d <= 0), 'rank'=>0, 'dealt'=>0);
	}
	return array(
		'over' => 'foes', 'round' => 5, 'forfeit' => true,   // forfeit: no Discord call
		'mine' => array(array('ko'=>false,'rank'=>0,'dealt'=>10),
		                array('ko'=>true, 'rank'=>1,'dealt'=>10),
		                array('ko'=>true, 'rank'=>2,'dealt'=>10)),
		'foes' => $foes,
		'meta' => array('attacker'=>1, 'run_id'=>1, 'day'=>'2026-10-25',
		                'mineIds'=>array(11,12,13), 'moves'=>array()),
	);
}

$c = new HBConn();
$c->health = array('d34thbrngr'=>1000, 'cip-666'=>1000, 'x-k4t'=>1000);

/* Player one takes 800 off a snapshot of 1000. */
$b1 = hb_battle(array('d34thbrngr'=>1000), array('d34thbrngr'=>800));
dhcb_finish($c, $b1);
ok($c->health['d34thbrngr'] === 200, 'the first commit left ' . $c->health['d34thbrngr'] . ', expected 200');
ok((int)$b1['dealtTotal'] === 800, 'the first run recorded ' . (int)$b1['dealtTotal'] . ', expected 800');

/* Player two started at the same time, against the same snapshot of 1000. */
$b2 = hb_battle(array('d34thbrngr'=>1000), array('d34thbrngr'=>800));
dhcb_finish($c, $b2);
ok($c->health['d34thbrngr'] === 0,
   'the bar went to ' . $c->health['d34thbrngr'] . ' instead of 0 -- a clamped commit cannot go negative');
ok((int)$b2['dealtTotal'] === 200,
   'the second run recorded ' . (int)$b2['dealtTotal'] . ' damage, but only 200 was left to take. '
 . 'The monthly board ranks on this number, so racing somebody would pay');
ok(in_array('d34thbrngr', $b2['killedKeys'], true), 'the run that emptied the bar is not credited with the kill');
ok(!in_array('d34thbrngr', $b1['killedKeys'], true), 'a run that did not empty the bar is credited with a kill');

/* The read has to be locked, or the clamp is decorative. */
$i = array_search('BEGIN', $c->log, true);
ok($i !== false, 'the commit is not wrapped in a transaction');
$locked = false;
foreach ($c->log as $q) if (stripos($q, 'SELECT health FROM dhc_boss_day') === 0 && stripos($q, 'FOR UPDATE') !== false) $locked = true;
ok($locked, 'the health read is not FOR UPDATE, so two commits can interleave between read and write '
          . 'and the clamp protects nothing');

/* Settling twice must pay nothing the second time. */
$c2 = new HBConn();
$c2->health = array('d34thbrngr'=>1000);
$c2->runOutcome = 1;                      // already settled
$b3 = hb_battle(array('d34thbrngr'=>1000), array('d34thbrngr'=>500));
dhcb_finish($c2, $b3);
ok($c2->health['d34thbrngr'] === 1000,
   'a run that was already settled committed its damage a second time');

/* ---------------------------------------------------------------- *
 * A ROUND IS WORTH MORE ONCE THE WEEK HAS PUT A CREW DOWN
 * ---------------------------------------------------------------- */
echo "\nthe later waves pay in quantity, because they cannot pay in rarity\n";
/*
 * WHY THIS EXISTS. dhcb_tier_table() returns placement_1 the moment the
 * first crew falls, and placement_1 is the platform ceiling -- the same
 * table first place on every weekly board gets. So from wave 2 onward the
 * QUALITY ladder is spent and the back half of a strong week had nothing
 * left to chase. The lever had to be quantity: a tier above placement_1
 * would break a ceiling eight games share, and DHCF_TIERS is explicit that
 * guaranteeing mythics on a clearable boss would mint more in a fortnight
 * than exist.
 */
ok(dhcf_table_rank('placement_1') === 3,
   'placement_1 is no longer the top table, so the premise of the whole wave multiplier '
 . 'is wrong and rarity could escalate instead');
$ranks = array();
foreach (array('run','placement_10','placement_3','placement_1') as $t) $ranks[] = dhcf_table_rank($t);
ok(max($ranks) === dhcf_table_rank('placement_1'),
   'some table outranks placement_1, so the best table is not actually the best');
ok(dhcb_tier_table(array('downed'=>1, 'pct'=>0.0, 'cleared'=>false)) === 'placement_1',
   'one crew down no longer locks the best table, which is what makes quantity the only '
 . 'lever left');

/* THE MULTIPLIER ITSELF. One per round at wave 1, the wave number after
   that, and a hard stop -- later waves are budgeted against the DAYS LEFT
   and floored at DHCB_WAVE_MIN_DAY, so they get cheaper as the week runs
   out and an uncapped multiplier would pay most for the cheapest crew. */
ok(dhcb_round_traits(1) === 1, 'an ordinary round no longer banks exactly one trait');
ok(dhcb_round_traits(2) === 2 && dhcb_round_traits(3) === 3,
   'the wave does not drive the count, so dropping a crew buys nothing');
ok(dhcb_round_traits(4) === (int)DHCB_WAVE_TRAITS_MAX
   && dhcb_round_traits(40) === (int)DHCB_WAVE_TRAITS_MAX,
   'the multiplier is uncapped: the last round of the week would pay most for the wave '
 . 'that cost least to drop');
ok(dhcb_round_traits(0) === 1 && dhcb_round_traits(-3) === 1,
   'a missing or broken wave pays nought traits, which on the payout path stops paying '
 . 'somebody with no error');
/* AND IT NEVER OUT-MINTS THE ARENA, which caps at three drops a day. */
ok((int)DHCB_WAVE_TRAITS_MAX <= 3,
   'a boss round can bank more traits than a whole day of the Arena pays');
/*
 * AND THE WEEK'S TRAITS HAVE TO SURVIVE dhcf_award(), WHICH THEY DID NOT.
 *
 * 'dhcbosses' was never registered in DHCF_GAMES, so dhcf_cap() fell
 * through to DHCF_DAILY_CAP -- three a day. The settle is WEEKLY and hands
 * over every trait the week earned in one loop on one day, so a player who
 * turned up all seven rounds was awarded three and silently refused four:
 * dhcf_award() returns null on the cap and nothing checks the return.
 * dhc_boss_payouts recorded seven and three arrived.
 *
 * Cap 0 like Drop Ship, because the bound here is structural -- the settle
 * is the only thing that awards from this source, it runs once a week on a
 * cron, and what it pays is rounds ATTENDED times the multiplier, which no
 * amount of grinding inside a day can move.
 */
ok(dhcf_cap('dhcbosses') === 0,
   'the boss settle is capped at ' . dhcf_cap('dhcbosses') . ' traits a day, so a week '
 . 'worth more than that silently loses the rest -- the ledger records them and the '
 . 'player never gets them');
ok(isset($GLOBALS['DHCF_GAMES']['dhcbosses']),
   'DHC Bosses is not in the trait registry, so "Where traits drop" does not list the '
 . 'game and dhcf_cap() falls through to the daily default');
ok(empty($GLOBALS['DHCF_GAMES']['dhcbosses']['unlisted']),
   'DHC Bosses pays traits but is hidden from the list of games that pay traits');
ok(7 * (int)DHCB_WAVE_TRAITS_MAX > dhcf_cap('dhcbosses') || dhcf_cap('dhcbosses') === 0,
   'a full week banks more traits than one day may award, which is the bug above');

/*
 * AND THE SETTLE ACTUALLY PAYS IT, run end to end.
 *
 * Every check above this is about a number. This is about whether the
 * number reaches dhcf_award(), which is the only part a player feels --
 * and the mutation that made dhcb_settle() ignore $p['traits'] and fall
 * back to the old day count went straight through all of them.
 */
$sc = new HBConn();
$sc->who   = 7;
$sc->wave  = 3;
$sc->health = $sc->maxHealth = array('d34thbrngr'=>0, 'cip-666'=>0, 'x-k4t'=>0);
/* Five rounds: two at wave 1, one at wave 2, two at wave 3. Round 0 has
   three Crews in it, which must still count once. */
/*
 * THE FIXTURE IS BUILT TO SEPARATE THE THREE WAYS THIS CAN BE WRONG, and
 * the first cut of it separated none of them -- every round held one wave
 * and the top wave equalled the cap, so MIN read the same as MAX and
 * dropping LEAST() changed nothing. Mutations proved it.
 *
 *   round 0: three Crews, all wave 1  -> one round, worth 1
 *   round 1: wave 1 then wave 2       -> MAX, so worth 2, not 1
 *   round 2: wave 2                   -> worth 2
 *   round 3: wave 5                   -> ABOVE the cap, so worth 3, not 5
 *   round 4: wave 3                   -> worth 3
 */
$sc->runs = array(
	array('d'=>0,'wave'=>1), array('d'=>0,'wave'=>1), array('d'=>0,'wave'=>1),
	array('d'=>1,'wave'=>1), array('d'=>1,'wave'=>2),
	array('d'=>2,'wave'=>2),
	array('d'=>3,'wave'=>5),
	array('d'=>4,'wave'=>3),
);
$GLOBALS['HB_POSTS'] = array();
$res = dhcb_settle($sc, '2026-10-25', false);
/* 1 + 2 + 2 + 3 + 3 */
ok($res['traits'] === 11,
   'the settle paid ' . $res['traits'] . ' traits for a week of 1,2,2,5-capped-to-3,3; expected 11');
ok($res['paid'] === 1, 'the settle paid ' . $res['paid'] . ' players, expected 1');
ok($res['table'] === 'placement_1',
   'a week that downed two crews did not pay the best table: ' . $res['table']);
/* THE SAME LEDGER WITH NO CREW EVER DOWN pays one per round and no more --
   the multiplier must not leak into an ordinary week. */
$sf = new HBConn();
$sf->who = 7; $sf->wave = 1;
$sf->health = $sf->maxHealth = array('d34thbrngr'=>9000, 'cip-666'=>9000, 'x-k4t'=>9000);
$sf->runs = array(array('d'=>0,'wave'=>1), array('d'=>0,'wave'=>1),
                  array('d'=>1,'wave'=>1), array('d'=>2,'wave'=>1),
                  array('d'=>3,'wave'=>1), array('d'=>4,'wave'=>1));
$flat = dhcb_settle($sf, '2026-10-25', false);
ok($flat['traits'] === 5,
   'an ordinary week paid ' . $flat['traits'] . ' for five rounds attended, expected 5');
/* AND A RUN LEDGER WITH NO WAVE ON IT STILL PAYS. Rows written before the
   column carried a value must fall back to the round count, never to a
   silent nought on the payout path. */
$sz = new HBConn();
$sz->who = 7; $sz->wave = 1;
$sz->health = $sz->maxHealth = array('d34thbrngr'=>9000, 'cip-666'=>9000, 'x-k4t'=>9000);
$sz->runs = array(array('d'=>0,'wave'=>0), array('d'=>1,'wave'=>0), array('d'=>2,'wave'=>0));
$zero = dhcb_settle($sz, '2026-10-25', false);
ok($zero['traits'] === 3,
   'a ledger with no wave recorded paid ' . $zero['traits'] . ' traits for three rounds');

/* And the per-player ceiling, which is what a reader will check by hand:
   seven rounds at the cap. */
ok(7 * (int)DHCB_WAVE_TRAITS_MAX <= 21,
   'a perfect week can bank more than the Arena pays in a perfect week');

/* ---------------------------------------------------------------- *
 * NOBODY FIGHTS A CORPSE: THE COMMIT IS PER MOVE
 * ---------------------------------------------------------------- */
echo "\nthe crew falling under a Crew costs it nothing it had already landed\n";
/*
 * THE BUG THIS REPLACED, REPORTED IN THE FIRST WEEK.
 *
 * Damage used to be committed in one lump on a run's last move, clamped to
 * whatever was still on the bar. A player went twenty rounds, the crew died
 * under them to somebody else, their lump landed on empty bars and they
 * recorded a flat zero -- no damage, no ladder, no kill, after doing the
 * work. The feed had to grow a "Beaten to it" label to explain it.
 *
 * dhcb_commit() lands each move's damage as it is dealt, and re-syncs the
 * board from the shared bar, so there is no window in which either of those
 * can happen. Driven here through the REAL dhcb_move() against the REAL
 * engine, with a stub standing in only for the connection -- a check that
 * read the source for the word "commit" would pass on code that committed
 * the wrong number.
 */
/* 15838 RATHER THAN THE 7919 THE SECTION ABOVE USES: that seed produces a
   four-move run, which leaves room for one move of damage either side of an
   interruption and tests almost nothing. This one runs nineteen. */
function hb_board($hpEach, $rid = 41, $uid = 7, $seed = 15838) {
	$order = dhcb_bosses(); $kits = dhca_kits();
	$foeT = array(); $foeN = array();
	foreach ($order as $b) { $foeT[] = array(); $foeN[] = $b['name']; }
	$bat = dhca_new_battle(
		array(array('background'=>'hellscape','torso'=>'red-cyborg','head'=>'gas-mask'),
		      array('background'=>'white','torso'=>'c38','head'=>'sentinel'),
		      array('background'=>'bright-red','torso'=>'silver-cyborg','head'=>'c73')),
		$foeT, array('mine'=>array('A','B','C'), 'foes'=>$foeN), dhcf_rarity(), $seed);
	foreach ($order as $i => $b) {
		$bat['foes'][$i]['bossKey'] = $b['key'];
		$bat['foes'][$i]['maxHp']   = $hpEach;
		$bat['foes'][$i]['hp']      = $hpEach;
		$bat['foes'][$i]['hp0']     = $hpEach;
		$bat['foes'][$i]['power']   = (int)round(DHCA_POWER_BASE * DHCB_POW_MULT * $b['pow']);
		$bat['foes'][$i]['kit']     = $kits[$b['kit'] % count($kits)];
	}
	/* forfeit: dhcb_finish() skips the Discord post. webhooks.php defines
	   discordmsg() on this machine, so without it the harness would post. */
	$bat['forfeit'] = true;
	$bat['meta'] = array('attacker'=>$uid, 'run_id'=>$rid, 'day'=>'2026-10-25', 'wave'=>1,
	                     'mineIds'=>array(1,2,3), 'moves'=>array(), 'boss'=>true);
	return $bat;
}
/** Play a run move by move through dhcb_move(). $at fires before move N. */
function hb_play($c, $bat, $maxTurns = 400, $at = null) {
	$rid = (int)$bat['meta']['run_id']; $uid = (int)$bat['meta']['attacker'];
	dhcb_save($c, $rid, $uid, $bat);
	$turns = 0; $last = $bat;
	while ($turns < $maxTurns) {
		$b = dhcb_load($c, $rid, $uid);
		if (!$b || $b['over'] !== null) break;
		$best = null; $bs = 0;
		foreach (dhca_all_slides() as $mv) {
			$sc = dhca_score_move($b, 'mine', $mv[0], $mv[1]);
			if ($sc > $bs) { $bs = $sc; $best = $mv; }
		}
		if (!$best) break;
		if ($at) $at($c, $turns);
		list($ok, $msg, $out) = dhcb_move($c, $uid, $rid, $best[0], $best[1]);
		if (!$ok) break;
		$last = $out; $turns++;
	}
	return array($last, $turns);
}

/* ---- a clean run: what the bar loses is exactly what the run records ---- */
$cc = new HBConn();
$cc->health = $cc->maxHealth = array('d34thbrngr'=>900000, 'cip-666'=>900000, 'x-k4t'=>900000);
$start = array_sum($cc->health);
list($rb, $rt) = hb_play($cc, hb_board(900000));
$removed = $start - array_sum($cc->health);
/*
 * AN INDEPENDENT ORACLE, because the two obvious numbers move together.
 *
 * "the bars lost what the run recorded" passes just as happily when BOTH
 * are double -- which is exactly what committing per move and again at the
 * close would produce, and a mutation proved it passes. hb_run() plays the
 * same seed through dhca_turn() with no commit anywhere near it, so the
 * damage it measures is the engine's alone and neither of the figures below
 * can be graded against itself.
 */
list($ref, $rtn) = hb_run($mine, $rar, 15838, 900000);
$true = 0; foreach ($ref['foes'] as $f) $true += $f['hp0'] - max(0, $f['hp']);
ok($rt > 1, "the run only managed $rt move(s), so nothing below is exercising anything");
ok($rt === $rtn, "the same seed played $rt moves through dhcb_move() and $rtn through the "
               . 'engine alone, so the two runs below are not the same fight');
ok($rb['over'] !== null, 'the run never resolved');
ok((int)$rb['dealtTotal'] === $true,
   'the engine dealt ' . $true . ' and the run recorded ' . (int)$rb['dealtTotal']);
ok($removed === $true,
   'the engine dealt ' . $true . ' and the bars lost ' . $removed
 . ' -- the commit is landing a different number than the fight produced');
ok($removed <= $start, 'the bars went below where they started');

/* ---- the crew dies under the Crew, three moves in ---- */
$cd = new HBConn();
$cd->health = $cd->maxHealth = array('d34thbrngr'=>900000, 'cip-666'=>900000, 'x-k4t'=>900000);
/* HALFWAY THROUGH A RUN OF THE LENGTH THIS SEED ACTUALLY PRODUCES. Fixed at
   three, the kill landed on the same move the Crew was wiped on, and the test
   was measuring a coincidence rather than the rule. */
$killedAt = max(1, (int)floor($rt / 2));
list($db, $dt) = hb_play($cd, hb_board(900000, 42), 400, function ($c, $turn) use ($killedAt) {
	/* Another Crew finishes all three, between this Crew's moves. */
	if ($turn === $killedAt) foreach ($c->health as $k => $v) $c->health[$k] = 0;
});
ok($dt === $killedAt + 1,
   "the run played $dt moves; it should have ended on the move after the crew fell ($killedAt + 1). "
 . 'A Crew that keeps swinging at a crew that is already down is the reported bug');
ok((int)$db['dealtTotal'] > 0,
   'the run banked ' . (int)$db['dealtTotal'] . ' after the crew fell under it. THIS IS THE BUG: '
 . 'every point it landed in the first ' . $killedAt . ' moves was already committed and must survive');
ok(empty($db['killedKeys']),
   'the run is credited with kills it did not land: ' . implode(',', (array)$db['killedKeys']));
ok($db['over'] !== null && $dt < $rt,
   'the run did not end early: it played ' . $dt . ' moves of the ' . $rt
 . ' this seed produces, so the crew going down did not stop it');
$allKo = true;
foreach ($db['foes'] as $f) if (empty($f['ko'])) $allKo = false;
ok($allKo, 'a boss that is down on the shared bar is still standing on this board');
$hpLeft = 0; foreach ($db['foes'] as $f) $hpLeft += max(0, (int)$f['hp']);
ok($hpLeft === 0, "the board shows $hpLeft health on a crew the week has at zero");
$told = false;
foreach ((array)$db['log'] as $l) if (strpos($l, 'fell to another Crew') !== false) $told = true;
ok($told, 'the board goes silent while three bosses drop on their own, which reads as a bug');

/* ---- and a legacy run, mid-fight when this shipped, pays exactly once ---- */
$cl = new HBConn();
$cl->health = $cl->maxHealth = array('d34thbrngr'=>900000, 'cip-666'=>900000, 'x-k4t'=>900000);
$leg = hb_board(900000, 43);
foreach ($leg['foes'] as $i => $f) $leg['foes'][$i]['hp'] = 900000 - 2000;   // dealt, never committed
unset($leg['meta']['sent']);
list($lb, $lt) = hb_play($cl, $leg, 1);              // exactly one move
$owed = 3 * 2000;
ok($lt === 1, 'the legacy run did not take its one move');
$landed = 2700000 - array_sum($cl->health);
ok($landed >= $owed,
   "a run that predates per-move commit landed $landed, which does not cover the $owed it "
 . 'had already dealt -- its whole fight so far was dropped');
list($lb2, $lt2) = hb_play($cl, dhcb_load($cl, 43, 7), 1);
$after = 2700000 - array_sum($cl->health);
ok($after - $landed < $owed,
   'the backlog was committed a second time on the next move, so a legacy run pays twice');

/* ---- a drain boss heals the bar, but only out of its own run's damage ---- */
/*
 * The drain kit gives a boss 45% of what it hits for, so its health goes UP
 * between moves and the shared bar has to follow or the heal is banked as
 * damage and then erased off the board by the next re-sync. What it must
 * never do is hand back damage this run did not deal.
 */
$ch = new HBConn();
$ch->health = $ch->maxHealth = array('d34thbrngr'=>1000);
$hb = array('log'=>array(), 'meta'=>array('attacker'=>7, 'day'=>'2026-10-25', 'wave'=>1),
            'foes'=>array(array('bossKey'=>'d34thbrngr', 'name'=>'D34THBRNGR',
                                'hp'=>1000, 'maxHp'=>1000, 'hp0'=>1000, 'ko'=>false)));
dhcb_commit($ch, $hb, array('d34thbrngr' => 60));
ok($ch->health['d34thbrngr'] === 940, 'the bar is at ' . $ch->health['d34thbrngr'] . ', expected 940');
dhcb_commit($ch, $hb, array('d34thbrngr' => -500));      // heals ten times what it took
ok($ch->health['d34thbrngr'] === 1000,
   'a heal put the bar at ' . $ch->health['d34thbrngr'] . ', above the 1000 this run found it at '
 . '-- a drain boss can hand back damage somebody else dealt');
ok((int)$hb['meta']['sent']['d34thbrngr'] === 0,
   'the run still claims ' . (int)$hb['meta']['sent']['d34thbrngr'] . ' damage it healed back');
$ch->health['d34thbrngr'] = 0;
$hb['meta']['sent']['d34thbrngr'] = 400;
dhcb_commit($ch, $hb, array('d34thbrngr' => -300));
ok($ch->health['d34thbrngr'] === 0, 'a drain boss got back up after the week had put it down');

/* ---- the lock order is fixed, which is what makes it deadlock-free ---- */
$seen = array();
foreach ($cd->log as $q)
	if (stripos($q, 'SELECT health FROM dhc_boss_day') === 0 && stripos($q, 'FOR UPDATE') !== false
	    && preg_match("~boss_key = '([^']+)'~", $q, $m)) $seen[] = $m[1];
$order = array(); foreach (dhcb_bosses() as $bz) $order[] = $bz['key'];
$chunks = array_chunk($seen, count($order));
$fixed = count($chunks) > 0;
foreach ($chunks as $ch) if (count($ch) === count($order) && $ch !== $order) $fixed = false;
ok($fixed, 'the rows are not locked in one fixed order, so two Crews committing in the same '
         . 'instant can deadlock rather than queue');

/* ---------------------------------------------------------------- *
 * THE DAILY SETTLE
 * ---------------------------------------------------------------- */
echo "\nthe day can be closed late, and closed twice, without paying twice\n";
ok(strpos($LIB, 'INSERT INTO dhc_boss_payouts') !== false,
   'the settle writes no payout row, so nothing stops it paying again');
$settle = substr($LIB, strpos($LIB, 'function dhcb_settle'));
$settle = substr($settle, 0, strpos($settle, "\nfunction "));
/*
 * EVERY AWARD HAS A PAYOUT WRITE BEFORE IT, which is the actual rule. This
 * used to compare the first INSERT against the first dhcf_award() -- and
 * then repay mode added a branch that UPDATEs the row and awards the
 * difference, which is correct and which that comparison called a
 * regression. The claim never changed; only one branch's verb did.
 */
preg_match_all('~(?:INSERT INTO|UPDATE) dhc_boss_payouts~', $settle, $wm, PREG_OFFSET_CAPTURE);
preg_match_all('~dhcf_award\(~', $settle, $am, PREG_OFFSET_CAPTURE);
$unbacked = 0;
foreach ($am[0] as $a) {
	$seen = false;
	foreach ($wm[0] as $w) if ($w[1] < $a[1]) $seen = true;
	if (!$seen) $unbacked++;
}
ok(count($wm[0]) >= 1 && count($am[0]) >= 1 && $unbacked === 0,
   "$unbacked of " . count($am[0]) . ' award sites hand out a trait with no payout row written '
 . 'before them, so a half-failure pays every time the cron runs');
$ins = count($wm[0]) ? $wm[0][0][1] : false;
$awd = count($am[0]) ? $am[0][0][1] : false;
ok($ins !== false && $awd !== false && $ins < $awd,
   'the trait is awarded before the payout row is written, so a half-failure pays every time '
 . 'the cron runs');
ok(strpos($settle, 'if (!$ok) continue;') !== false,
   'a refused payout row does not stop the award, which is the whole idempotency guard');
ok(strpos($settle, 'DHCB_MIN_DEALT') !== false,
   'there is no participation floor, so one swing earns the same as six Crews');

/* ---------------------------------------------------------------- *
 * THE LADDER
 * ---------------------------------------------------------------- */
echo "\nthe monthly board ranks on efficiency, not on roster size\n";
$lad = substr($LIB, strpos($LIB, 'function dhcb_ladder'));
$lad = substr($lad, 0, strpos($lad, "\nfunction "));
/* DAMAGE OVER FIGHTERS, not raw damage. The denominator narrowed to the
   Fighters that actually landed something -- see the clamp note below --
   but it is still a per-Fighter average and never a volume total. */
ok(preg_match('~ORDER BY .*SUM\(r\.dealt\) / ~s', $lad) === 1
   && strpos($lad, 'r.crew_size ELSE 0 END)') !== false,
   'the board orders on raw damage, which is mostly a measure of roster size -- and the '
 . 'harmonic payout weights rank 1 at twice rank 2, so it would compound every week');
ok(strpos($lad, 'crew_size') !== false && strpos($lad, 'COUNT(*) AS runs') !== false,
   'the volume columns are gone; they are displayed but must not rank');
/* A SINGLE LUCKY RUN STILL CANNOT TOP IT. The minimum moved from a HAVING
   to a rank-and-pay rule so that everybody who turned up is visible, but
   it still decides who can hold a position and who gets paid. */
ok(strpos($lad, 'ORDER BY (COUNT(*) >= $min) DESC') !== false,
   'a single lucky run can be sorted above a qualified player');
ok(strpos($lad, "'ranked'=>((int)\$x['runs'] >= \$min)") !== false,
   'nothing marks a row as below the minimum, so a single run reads as a real position');

/* ---------------------------------------------------------------- *
 * THE COOLDOWN IS NOT THE ARENA'S
 * ---------------------------------------------------------------- */
echo "\nsending a Crew at a boss does not bench it for ranked play\n";
$ros = substr($LIB, strpos($LIB, 'function dhcb_roster'));
$ros = substr($ros, 0, strpos($ros, "\nfunction "));
ok(strpos($ros, 'dhc_boss_fighters') !== false, 'the roster reads the wrong bench table');
ok(strpos($ros, 'dhca_crew(') !== false,
   'the roster no longer builds on dhca_crew(), so a Fighter can look different here');
ok(strpos($ros, 'TIMESTAMPDIFF(SECOND, NOW(), benched_until)') !== false,
   'the cooldown is compared in PHP; MySQL and php.ini are hours apart on this host and '
 . 'the Arena lost a whole class of bug to exactly that');

/* ---------------------------------------------------------------- *
 * THE SHARE
 * ---------------------------------------------------------------- */
echo "\nthe post says what the run did, in the right direction\n";
/*
 * progress.pct is damage DEALT. The first version said "they are at 42%",
 * which reads as 42% health remaining and means the opposite -- a post that
 * gets the direction backwards is worse than one with no number in it.
 */
$sh = dhcb_share_text(1850, array(), array('pct'=>0.42, 'cleared'=>false));
ok(strpos($sh, '42% defeated') !== false, 'the share does not say which way the number runs: ' . $sh);
ok(strpos($sh, 'at 42%') === false, 'the share still reads as 42% health left: ' . $sh);
/* It must not claim a kill it did not get, and must claim one it did. */
ok(stripos(dhcb_share_text(100, array(), array('pct'=>0.1,'cleared'=>false)), 'dropped') === false,
   'a run that killed nothing still says it dropped something');
ok(stripos(dhcb_share_text(100, array('X-K4T'), array('pct'=>0.5,'cleared'=>false)), 'X-K4T') !== false,
   'a run that dropped a boss does not name it');
/* 280 including X's flat 23-character url cost and the platform tail. */
$tail = 92;
foreach (array(array(999999, array('D34THBRNGR','CIP-666','X-K4T'), 1.0, true),
               array(1850, array(), 0.42, false)) as $cse) {
	$t = dhcb_share_text($cse[0], $cse[1], array('pct'=>$cse[2], 'cleared'=>$cse[3]));
	ok(mb_strlen($t) + $tail + 24 <= 280,
	   'the post overflows 280 once the tail and url are counted: ' . mb_strlen($t));
}

/* ---------------------------------------------------------------- *
 * THE PAGE DOES NOT CALL A 4PM BLOCK "TODAY"
 * ---------------------------------------------------------------- */
echo "\nthe allowance says when it actually resets\n";
/*
 * REPORTED AS A BUG, AND THE CODE WAS RIGHT. "I already sent 3 crews in
 * today, why is it letting me send more and why is it showing 0 of 3 sent?"
 *
 * The allowance is a 24-hour block measured from the week's 4pm Thursday
 * boundary, not a calendar day. Three Crews at 2pm on Sunday, back at half
 * past four, block turned, fresh three -- exactly as designed. The page
 * called that block "today", which on a Sunday afternoon is simply false,
 * so the only thing a player could conclude was that the cap was broken.
 *
 * THE ARENA'S "today" IS LEFT ALONE: dhca_battles_today() really does use
 * DATE(started_at) = CURDATE(), so six a day is six a calendar day. The two
 * games reset differently and only one of them was lying about it.
 */
$bossPanel = '';
$bpA = strpos($ARE, '<div class="a-panel" id="aBossPanel">');
/* To the end of the panel, not a guessed number of characters -- the
   allowance line is the last thing in it. */
if ($bpA !== false) {
	$bpB = strpos($ARE, '<?php endif; ?>', strpos($ARE, 'id="aBossGo"', $bpA));
	$bossPanel = substr($ARE, $bpA, ($bpB !== false ? $bpB - $bpA : 9000));
}

ok(strpos($LIB, 'function dhcb_round(') !== false,
   'nothing can say which round it is or when it ends, so the page can only describe the '
 . 'allowance vaguely');
/* MySQL, like every other clock in this feature -- started_at is written by
   the database and the two clocks on this host disagree. */
/* The function, not a guessed span -- 700 characters reached into the next
   one and counted its query too. */
$bl = substr($LIB, strpos($LIB, 'function dhcb_round('));
$bl = substr($bl, 0, strpos($bl, "\n}") + 2);
ok(strpos($bl, 'skl_week_start_sql()') !== false && strpos($bl, 'NOW()') !== false,
   'the round clock is computed in PHP, and PHP and MySQL disagree on this host');
ok(strpos($bl, 'MOD(') !== false && strpos($bl, '86400') !== false,
   'the remainder is not of a 24-hour round, so it cannot be right at the boundary');
ok(substr_count($bl, '$conn->query(') === 1,
   'the round number and its remainder are two queries; they are the same arithmetic');
ok(strpos($bl, "'n'") !== false && strpos($bl, "'left'") !== false,
   'dhcb_round() does not return both the round and what is left of it');

/*
 * AND THE PAGE SHOWS A CLOCK, not a sentence.
 *
 * Renaming "today" to "this round" was not enough and the owner said so:
 * "I think if there was some countdown on the page to the next day, then it
 * would be less ambivalent." A boundary nobody can see is still a boundary
 * nobody can see, however it is worded.
 */
ok(strpos($bossPanel, 'class="a-round"') !== false,
   'the round strip is gone, so nothing states the round at a glance');
ok(strpos($bossPanel, 'id="aRoundLeft"') !== false
   && strpos($bossPanel, 'data-left="<?php echo (int)$dhcb_left;') !== false,
   'the countdown has no element or no server figure to count down from');
ok(strpos($bossPanel, 'Round <b><?php echo (int)$dhcb_round;') !== false,
   'the strip does not say WHICH round, so the cache slots have nothing to anchor to');
/*
 * IT TICKS, and from the SERVER'S remainder rather than a local clock. The
 * boundary is 4pm America/Chicago computed in MySQL and the browser may be
 * in any timezone with any drift -- subtracting elapsed local time from a
 * server-supplied remainder is right whatever the device thinks the time is.
 */
$tickA = strpos($ARE, "var el = document.getElementById('aRoundLeft');");
ok($tickA !== false, 'nothing ticks the countdown, so it is stale the moment the page settles');
$tick = $tickA !== false ? substr($ARE, $tickA - 60, 1100) : '';
ok(strpos($tick, 'setInterval(tick, 1000)') !== false, 'the countdown does not tick every second');
ok(strpos($tick, 'Date.now() - t0') !== false,
   'the countdown reads the local clock instead of counting down from the server figure, so a '
 . 'browser in another timezone shows the wrong boundary');
/* AND IT MUST NOT RELOAD AT ZERO. The player may be mid-run, and throwing
   away a board to refresh a counter is a worse bug than the one this fixes. */
ok(strpos($tick, 'location.reload') === false && strpos($tick, 'location.href') === false,
   'the countdown reloads the page when the round turns, which would throw away a run in '
 . 'progress');
ok(strpos($tick, 'clearInterval(iv)') !== false,
   'the countdown keeps ticking past zero');

/*
 * AND NOTHING IN BOSS MODE CALLS IT A DAY. Checked against the rendered
 * strings rather than the whole file: the Arena's own copy on this page is
 * correct and must not be caught by this.
 */
ok($bossPanel !== '', 'the boss panel is gone, so this cannot check its copy');
foreach (array('Crews sent today', 'Crews a day', 'Crews left today',
               'every day you send') as $lie) {
	ok(strpos($ARE, $lie) === false,
	   'the page still says "' . $lie . '", and the allowance is a 4pm-to-4pm round, not a day');
}
ok(strpos($bossPanel, 'Crews left</span>') !== false,
   'the strip does not say how many Crews are left in the round');
ok(strpos($bossPanel, 'Rounds turn at 4pm CT') !== false,
   'the panel never says when the round turns, which is the fact that was missing');
ok(strpos($bossPanel, 'dhca_hms($dhcb_left)') !== false,
   'the countdown renders nothing server-side, so it is blank until JavaScript runs');
/* The refusal has to name it too -- that is the message a capped player
   reads, and it used to say "come back tomorrow", which is wrong by hours
   in the wrong direction for most of the afternoon. */
$eb = substr($LIB, strpos($LIB, 'function dhcb_entry_block('), 1400);
ok(strpos($eb, 'Come back tomorrow') === false,
   'the capped message still says "come back tomorrow"; the round turns at 4pm');
ok(strpos($eb, '4pm CT') !== false && strpos($eb, 'dhcb_round($conn)') !== false,
   'the capped message does not say when the next Crews land');

/* ---------------------------------------------------------------- *
 * THE BOSSES FIGHT SOMEWHERE
 * ---------------------------------------------------------------- */
echo "\nthe boss crew brings its own ground, and it is real ground\n";
/*
 * THE BUG THIS EXISTS FOR, AND IT COST THE PLAYER 8% OF THEIR CREW.
 *
 * The bosses were sent into dhca_new_battle() with EMPTY trait sets, because
 * a boss is a painted piece and not an assembly. But the arena is read off
 * the defender's front-rank BACKGROUND, so every run resolved '' ->
 * dhca_terrain_for('') -> 'frail', the "unrecognised field is a bare one"
 * fallback. Frail takes 8% off everybody's max health -- and dhcb_start()
 * then overwrites the foes' health with the shared bar, undoing it for the
 * BOSSES and leaving it on the player.
 *
 * Every run ever played was eight per cent down on one side only, silently,
 * and the board read "Unknown Ground" because there was no ground. The owner
 * found it by asking whether the label was for dramatic effect.
 */
require_once __DIR__ . '/dhcarena-roles.php';
$rarAll = dhcf_rarity();
foreach (dhcb_bosses() as $bz) {
	ok(!empty($bz['bg']),
	   $bz['name'] . ' has no background, so it contributes no ground');
	ok(isset($rarAll['background'][$bz['bg']]),
	   $bz['name'] . "'s background '" . $bz['bg'] . "' is not in the collection, so the "
	 . 'board renders no backdrop and the terrain falls back to frail');
	ok(dhca_terrain_name($bz['bg']) !== 'Unknown Ground',
	   $bz['name'] . "'s ground has no name");
}
/*
 * THE FRONT RANK SETS THE ARENA -- same rule as a ranked battle, no special
 * case -- so that is the one that must not be frail. Frail is the only
 * terrain that is a pure penalty here, because the bosses get their health
 * overwritten afterwards and the player does not.
 */
$front = null;
foreach (dhcb_bosses() as $bz) if ((int)$bz['rank'] === 0) $front = $bz;
ok($front !== null, 'no boss is front rank, so nothing sets the arena');
ok($front && dhca_terrain_for($front['bg']) !== 'frail',
   'the front-rank boss stands on frail ground, which takes 8% off the player\'s Crew and '
 . 'nothing off the bosses');
/* AND IT IS ACTUALLY PASSED. A background on the roster that dhcb_start()
   does not forward is a background that changes nothing. */
ok(strpos($LIB, "\$foeTraits[] = !empty(\$b['bg']) ? array('background' => \$b['bg']) : array();") !== false,
   'the boss backgrounds are not passed into the battle, so the arena is still nothing');
/*
 * PROVEN BY PLAYING ONE. The checks above are about the roster; this is the
 * engine agreeing -- a real battle built the way dhcb_start() builds it has
 * to come out on named, non-frail ground.
 */
$bgT = array(); foreach (dhcb_bosses() as $i => $bz)
	$bgT[] = !empty($bz['bg']) ? array('background' => $bz['bg']) : array();
$probe = dhca_new_battle($mine, $bgT,
	array('mine'=>array('A','B','C'), 'foes'=>array('X','Y','Z')), $rar, 4242);
ok($probe['terrain'] !== 'frail',
   'a real boss battle still resolves to frail ground: ' . $probe['terrain']);
ok(dhca_terrain_name($probe['terrainBg']) !== 'Unknown Ground',
   'a real boss battle still reports Unknown Ground');
/* And the player's Crew keeps its full health, which is the actual cost. */
$flat = dhca_new_battle($mine, array(array(), array(), array()),
	array('mine'=>array('A','B','C'), 'foes'=>array('X','Y','Z')), $rar, 4242);
ok($probe['mine'][0]['maxHp'] > $flat['mine'][0]['maxHp'],
   'the Crew is no healthier on real ground than it was on no ground, so the frail penalty '
 . 'is still being applied');

/* ---------------------------------------------------------------- *
 * A RUN THE CLAMP ATE STILL COUNTS, AND THE BOARD SHOWS EVERYBODY
 * ---------------------------------------------------------------- */
echo "\nbeing unlucky does not cost a player their round\n";
/*
 * OBSERVED ON THE FIRST CLEAR. Damage is capped at the health left on the
 * shared bar, which is right while two people race a live boss. But when
 * the crew DIES mid-run, everybody still out there commits against empty
 * bars and records a flat zero -- one player went twenty rounds and banked
 * nothing.
 *
 * That zero cost three separate things, and the third is the worst:
 *   the round's cache slot;
 *   the week's trait for that round;
 *   LADDER POSITION, because the average is damage over Fighters FIELDED,
 *   so a clamped run added three to the denominator and nothing to the
 *   numerator. Bad luck actively lowered their rank.
 */
ok(defined('DHCB_MIN_ROUNDS') && DHCB_MIN_ROUNDS > 1,
   'there is no rounds-based participation rule, so a run the clamp ate banks nothing');
ok(DHCB_MIN_ROUNDS >= 3,
   'the rounds minimum is low enough to reach by forfeiting, which makes the cache '
 . 'farmable without fighting');
/* Both places a slot is decided use the same rule, or the slot a player
   sees and the trait they are paid disagree. */
ok(substr_count($LIB, 'OR rounds >= ') >= 3,
   'the damage-or-rounds rule is not applied everywhere a slot is decided ('
 . substr_count($LIB, 'OR rounds >= ') . ' of 3)');
ok(strpos($LIB, "\$p['rounds_max'] < DHCB_MIN_ROUNDS") !== false,
   'the settle still pays on damage alone, so a clamped run gets no trait');
/*
 * AND THE AVERAGE ONLY DIVIDES BY FIGHTERS THAT LANDED SOMETHING.
 */
ok(strpos($LIB, 'SUM(CASE WHEN r.dealt > 0 THEN r.crew_size ELSE 0 END) AS scoring') !== false,
   'the ladder divides by every Fighter fielded, so a run the clamp ate lowers the '
 . 'player\'s own average');
ok(strpos($LIB, "\$sc = max(1, (int)\$x['scoring']);") !== false
   && strpos($LIB, "'per'=>(int)round((int)\$x['dealt'] / \$sc)") !== false,
   'the per-Fighter figure is not taken from the scoring Fighters');

/*
 * EVERYBODY WHO TURNED UP IS ON THE BOARD; the minimum decides RANK.
 *
 * It was a HAVING, so a player with two finished Crews was simply absent --
 * and early in a week that is everybody. Round four of the first week and
 * the board had one name on it, which reads as a dead feature rather than
 * as a board filling up.
 */
/*
 * AGGREGATES, NOT SELECT ALIASES, in HAVING and ORDER BY. The board read
 * `HAVING fighters > 0` and `ORDER BY (runs >= 3) DESC`, using names from
 * the SELECT inside expressions -- MySQL is far less willing to resolve
 * an alias there than standing alone, the query failed, and the ladder
 * went blank while the feed underneath it listed a dozen finished Crews
 * from the same week and the same table.
 */
ok(preg_match('~HAVING\s+[a-z_]+\s*>~i', $lad) === 0,
   'the ladder uses a SELECT alias in HAVING, which is what blanked the board');
ok(preg_match('~ORDER BY \((?!COUNT|SUM)~', $lad) === 0,
   'the ladder uses a SELECT alias inside an ORDER BY expression');
ok(strpos($LIB, 'HAVING SUM(r.crew_size) > 0') !== false
   && strpos($LIB, 'HAVING runs >= $min') === false,
   'the ladder still hides anybody short of the minimum, so an early week looks empty');
/* A BROKEN READ IS NOT AN EMPTY WEEK. Both returned array(), so a failed
   query printed "nobody has finished a Crew against them yet" over a week
   people had been playing all evening. */
ok(strpos($LIB, 'if (!$res) return false;') !== false,
   'a failed ladder query still returns an empty array, so it is indistinguishable from '
 . 'a quiet week');
ok(strpos($LIB, 'if ($rows === false) {') !== false,
   'the renderer does not distinguish a broken read from an empty one');
$DBS2 = file_get_contents(__DIR__ . '/db.php');
ok(strpos($DBS2, "if (\$rows === false) {") !== false,
   'the payout path treats a failed board read as an empty week, which would settle a '
 . 'week by paying nobody and reporting a clean run');
ok(strpos($LIB, "'ranked'=>((int)\$x['runs'] >= \$min)") !== false
   && strpos($LIB, "'needs'=>max(0, \$min - (int)\$x['runs'])") !== false,
   'the rows do not say who is ranked or how many Crews short they are');
ok(strpos($LIB, 'ORDER BY (COUNT(*) >= $min) DESC') !== false,
   'the unranked are not sorted below the ranked, so the board interleaves them');
/*
 * AND THE PAYOUT NEVER SEES THEM. This skip is the only thing standing
 * between showing everybody and paying CARBON to somebody who sent one
 * Crew.
 */
$DBS = file_get_contents(__DIR__ . '/db.php');
$cfn2 = substr($DBS, strpos($DBS, 'function checkDHCBossesLeaderboard'));
$cfn2 = substr($cfn2, 0, strpos($cfn2, "\n}\n") + 3);
ok(strpos($cfn2, "if (isset(\$row['ranked']) && !\$row['ranked']) continue;") !== false,
   'the CARBON payout walks every row dhcb_ladder() returns, which now includes players '
 . 'short of the minimum -- it would pay somebody who sent one Crew');
$skipAt = strpos($cfn2, "!\$row['ranked']");
$payAt  = strpos($cfn2, 'updateBalance(');
ok($skipAt !== false && $payAt !== false && $skipAt < $payAt,
   'the unranked skip comes after the payout, so it does not prevent anything');
/* The panel marks them rather than dropping them. */
ok(strpos($LIB, "\$unranked = isset(\$l['ranked']) && !\$l['ranked'];") !== false,
   'the rendered board does not distinguish the unranked');
ok(strpos($ARE, '.arena-wrap .a-lad .unranked{opacity:.5}') !== false,
   'the unranked rows are not dimmed, so they read as ranked positions');

/*
 * THE TRAIT GATE AND THE CARBON GATE ARE NOT THE SAME GATE.
 *
 * Asked within a minute of the board going up: "it's a 3 crew minimum to
 * be eligible for the trait?" No -- DHCB_LADDER_MIN_RUNS exists only in
 * dhcb_ladder(), and the settle pays one slot per ROUND attended, gated
 * per run on damage-or-rounds. One Crew that does a real run banks that
 * round's trait. But the two sit next to each other on the page, so the
 * page has to say which is which.
 */
$settleFn = substr($LIB, strpos($LIB, 'function dhcb_settle('));
$settleFn = substr($settleFn, 0, strpos($settleFn, "\n}\n") + 3);
ok(strpos($settleFn, 'DHCB_LADDER_MIN_RUNS') === false,
   'the trait settle now consults the LADDER minimum, so a player who sent one Crew and '
 . 'fought is being denied the round\'s trait');
ok(strpos($ARE, '<b>The traits are separate</b>') !== false,
   'the ladder copy does not say the trait gate is a different gate, and the first '
 . 'question anybody asked was whether three Crews are needed for a trait');

/* ---------------------------------------------------------------- *
 * THE WEEK'S CREWS, DRAWN
 * ---------------------------------------------------------------- */
echo "\nthe page shows what everybody else is throwing at the bar\n";
/*
 * A shared health bar makes "what is everyone else sending" the question a
 * player opens the page with, and until now only the admin report could
 * answer it -- as a table of names. This is the same data drawn: a face,
 * the three Fighters, the damage, and whether anybody walked away.
 */
ok(strpos($ARE, 'class="a-feed"') !== false,
   'the Crews feed is gone, so the page never says what anybody else sent');
ok(strpos($ARE, 'if ($IS_BOSS && $dhcb_feed)') !== false,
   'the feed is not fenced to boss mode, so the Arena renders a boss report');
/* THE SHARED REPORT, not a second query. admin-dhcbosses.php reads the same
   function; two of them would drift the first time either was touched. */
/* THE WHOLE WEEK IS FETCHED and all but the newest dozen are folded away.
   A display:none subtree fetches no images, so the rest of the week costs
   DOM nodes and no requests until somebody opens it. */
ok(strpos($ARE, 'dhcb_runs_report($conn, null, 200)') !== false,
   'the feed does not come from dhcb_runs_report(), or no longer reads the week');
ok(strpos($ARE, '$FEED_OPEN = 12;') !== false,
   'the feed opens on the whole week, which is a wall of rows on arrival');
ok(strpos($ARE, '.arena-wrap .a-run.off,.arena-wrap .a-wavebreak.off{display:none}') !== false,
   'the folded rows are not display:none, so the browser fetches every Fighter in the '
 . 'week whether or not anybody opens it');
ok(strpos($ARE, 'id="aFeedMore"') !== false
   && strpos($ARE, "feed.classList.toggle('open')") !== false,
   'there is no way to see the rest of the week');
/*
 * THE CREW IS DRAWN THE WAY EVERY OTHER CREW ON THE PLATFORM IS, and the
 * first attempt drew nothing at all for two reasons worth recording.
 *
 * IT CALLED dhcf_render_fighter(), WHICH THIS PAGE DOES NOT LOAD. That
 * function lives in dhcfighters-notify.php, and dhcarena-lib.php only
 * includes it INSIDE dhca_announce() -- a function that runs when a battle
 * ends, not when a page renders. So it was undefined, the function_exists()
 * guard returned '', and every Fighter was skipped in silence. The guard is
 * what made it invisible: a missing dependency turning into an empty row is
 * worse than the crash it was there to prevent.
 *
 * AND IT ASKED FOR 250, WHICH IS A CACHE NOTHING ELSE FILLS. The size only
 * appears in the filename when it is not 500, so 250 would have composed a
 * thousand-pixel stack per Fighter on first view -- thirty-six of them on
 * one page load.
 *
 * Stacked layers cost the server nothing and the trait art is already in
 * every visitor's browser cache from every other DHC surface.
 */
/* STRIPPED, because the comment above the feed explains this bug by name
   and a bare search found that instead of code -- which is the same way a
   check in this repo has gone quiet five times before. */
$AREC = harness_strip_comments($ARE);
ok(strpos($AREC, 'dhcf_render_fighter') === false,
   'dhcarena.php calls dhcf_render_fighter(), which lives in dhcfighters-notify.php -- a '
 . 'file this page never loads, so the call silently returns nothing');
/* To the numbers, not a guessed span -- the crew cell grew a placeholder
   branch for runs still in progress and a fixed window stopped reaching the
   layer loop. */
$feedAt = strpos($ARE, 'class="a-runcrew"');
$feedEnd = $feedAt !== false ? strpos($ARE, 'class="a-runnum"', $feedAt) : false;
$feed = ($feedAt !== false && $feedEnd !== false) ? substr($ARE, $feedAt, $feedEnd - $feedAt) : '';
ok(strpos($feed, 'dhcf_layer_order($t)') !== false && strpos($feed, 'dhcf_slot_category($k)') !== false,
   'the feed does not draw the Crew with the shared layer order, so it can disagree with '
 . 'the same Fighter in the picker and the Collection');
ok(strpos($feed, 'loading="lazy"') !== false,
   'the feed loads its Crew art eagerly, and it sits below the fold');
/*
 * AND THE GENERAL RULE, because this is the second time a dhcfighters-notify
 * function has been called from a file that does not load it: nothing in the
 * Arena page may call a dhcf_ function that is ONLY defined there.
 */
$notify = file_get_contents(__DIR__ . '/dhcfighters-notify.php');
$lib    = file_get_contents(__DIR__ . '/dhcfighters-lib.php');
preg_match_all('~\b(dhcf_[a-z0-9_]+)\s*\(~i', $AREC, $cm);
$onlyNotify = array();
foreach (array_unique($cm[1]) as $fn) {
	$inNotify = preg_match('~function\s+' . preg_quote($fn, '~') . '\s*\(~i', $notify) === 1;
	$inLib    = preg_match('~function\s+' . preg_quote($fn, '~') . '\s*\(~i', $lib) === 1;
	if ($inNotify && !$inLib) $onlyNotify[] = $fn;
}
ok(!$onlyNotify,
   'dhcarena.php calls ' . implode(', ', $onlyNotify) . ' -- defined only in '
 . 'dhcfighters-notify.php, which this page loads only from inside dhca_announce(), so the '
 . 'call is undefined on a page render');
/*
 * FELL IS GREY AND STANDING IS LIT, per Fighter, from the ledger.
 *
 * The first pass copied the share card's grayscale + opacity .45. That is
 * tuned for a 180px tile; at 38px it erased the Crew, and since almost
 * every run is a wipe the whole feed rendered as unreadable mush -- which
 * defeats the one thing it is for. Rendered the realistic all-wiped case
 * and fixed it: lighter dim, and the real signal on the border, which
 * survives at thumbnail size where saturation does not.
 */
/* Anchored to the feed's own class, because the Crew picker's recover
   overlay is built from the same expression and a bare match counted it. */
ok(preg_match("~a-rf<\?php echo !empty\(\\\$f\['fell'\]\)~", $ARE) === 1,
   'the feed does not mark fallen Fighters, so a wipe looks like a clean run');
ok(preg_match('~\.a-rf\.fell img\{filter:grayscale\(100%\);opacity:\.(\d+)~', $ARE, $om) === 1,
   'the fallen treatment is gone or no longer one rule');
ok(!empty($om) && (int)$om[1] >= 60,
   'the fallen Fighters are dimmed to .' . ($om[1] ?? '?') . ' at 38px, which is the share '
 . 'card\'s figure and erases the Crew at this size');
ok(strpos($ARE, '.arena-wrap .a-rf:not(.fell){border-color:var(--teal)') !== false,
   'a surviving Fighter has no lit edge, so the rare good outcome does not read in a wall '
 . 'of grey');
/*
 * NO PHP LEAKS INTO THE PAGE AS TEXT.
 *
 * Adding the wave divider replaced the `foreach` line and left the block
 * that followed it -- a comment and five assignments -- stranded OUTSIDE
 * any <?php tag. The page printed its own source into the feed and then
 * threw "Undefined variable" for every one of them, on a live page.
 *
 * php -l CANNOT SEE THIS. The file is still perfectly valid PHP; the
 * orphaned code is just HTML as far as the parser is concerned. So the
 * check is on the OUTPUT shape: strip every <?php ... ?> span from the
 * feed and whatever is left must look like markup, with no variables and
 * no comment syntax in it.
 */
$feedA2 = strpos($ARE, '<div class="a-feed">');
$feedB2 = strpos($ARE, '<?php endif; ?>', $feedA2);
ok($feedA2 !== false && $feedB2 !== false, 'the feed block could not be located');
if ($feedA2 !== false && $feedB2 !== false) {
	$block = substr($ARE, $feedA2, $feedB2 - $feedA2);
	ok(substr_count($block, '<?php') === substr_count($block, '?>'),
	   'the feed has ' . substr_count($block, '<?php') . ' opening PHP tags and '
	 . substr_count($block, '?>') . ' closing ones, so something is stranded');
	$html = preg_replace('~<\?php.*?\?>~s', '', $block);
	ok(strpos($html, '/*') === false && strpos($html, '*/') === false,
	   'a PHP comment is outside a PHP tag in the feed, so the page prints its own source');
	ok(preg_match('~\$[a-zA-Z_]\w*\s*=~', $html) === 0,
	   'a PHP assignment is outside a PHP tag in the feed, so the page prints its own '
	 . 'source and then warns about every variable in it');
	ok(preg_match('~\$rn\[~', $html) === 0,
	   'a $rn[...] read is outside a PHP tag in the feed');
}

/*
 * A LINE WHERE THE CREW FELL. The feed is the week, newest first, so it
 * runs straight through the moment a crew went down -- and without a mark
 * there the greyed Fighters above and below the boundary look like one
 * long fight.
 */
ok(strpos($ARE, 'class="a-wavebreak<?php echo $off; ?>"') !== false,
   'nothing marks where a wave ended, so the week reads as one undifferentiated list');
ok(strpos($ARE, '$prevWave !== null && $wv < $prevWave') !== false,
   'the break is not driven by the wave each run was fought against');
/*
 * AND A CLAMPED RUN SAYS WHAT HAPPENED TO IT.
 *
 * "0 damage, 20 rounds, Crew wiped" is the shape of a disaster, and this
 * was bad luck: the crew fell to somebody else while that Crew was still
 * out there. The first person it happened to had to ask.
 */
ok(strpos($ARE, "\$clamped = (!\$live && (int)\$rn['dealt'] === 0") !== false
   && strpos($ARE, "(int)\$rn['rounds'] >= (int)DHCB_MIN_ROUNDS)") !== false,
   'a run the clamp ate is not distinguished, so it reads as a run that failed');
ok(strpos($ARE, 'Beaten to it') !== false,
   'a clamped run has no wording of its own');
ok(strpos($ARE, 'does not count against the ladder average') !== false,
   'nothing tells the player the round still counted, which is the thing they will ask');
/*
 * AND THAT EXPLANATION HAS TO BE REACHABLE.
 *
 * It was a title= attribute under a dotted underline with cursor:help --
 * a promise the attribute cannot keep. title fires on no tap, on no
 * click, and on a desktop only after a second of holding still, so the
 * first person to try it reported the control as broken. A <details>
 * opens on a click, on Enter and on a phone, before any script runs.
 */
ok(strpos($ARE, 'title="The boss crew fell') === false,
   'the clamp explanation is back in a title= attribute, which never opens on a tap or a click');
$tipAt = strpos($ARE, 'class="a-runout clamped a-tip"');
ok($tipAt !== false && strpos($ARE, '<summary>Beaten to it</summary>', $tipAt) === $tipAt + 31,
   'the clamp label is not a summary, so there is nothing for a click to open');
/* The disclosure has to LOOK like the label it replaced: no triangle, and
   the dotted underline on the summary rather than on a <details> box that
   would draw a rule under the opened note. */
ok(strpos($ARE, '.arena-wrap details.a-tip > summary::-webkit-details-marker{display:none}') !== false,
   'the tip keeps the default disclosure triangle, which the outcome column never had');
ok(strpos($ARE, '.arena-wrap details.a-tip > summary{list-style:none;cursor:pointer;display:inline-block;') !== false
   && strpos($ARE, 'border-bottom:1px dotted rgba(143,168,184,.5)}') !== false,
   'the dotted underline is not on the summary, so the closed label loses the hint that it opens');
/* AND THE NOTE HAS TO READ AS PROSE. .a-runout is 9px uppercase with .11em
   of tracking; four lines of sentence in that is unreadable, so the body
   drops every one of those rules rather than inheriting them. */
ok(preg_match('~\.arena-wrap \.a-tipbody\{[^}]*letter-spacing:0;text-transform:none~', $ARE) === 1,
   'the tip body inherits the label uppercase and tracking, so the sentence is unreadable');

/* AND THE OUTCOME IS WORDED FOR THIS GAME. Win/lose is meaningless when
   almost every run is a paying wipe; what is worth saying is whether
   anybody came back. */
ok(strpos($ARE, 'walked away') !== false && strpos($ARE, 'Crew wiped') !== false,
   'the feed reports win or lose rather than who survived');
/*
 * A RUN IN PROGRESS IS NOT A WIPE.
 *
 * dhc_boss_run_fighters is written by dhcb_finish(), so a run still being
 * played has no Crew rows, no damage and no rounds. The feed printed that
 * as "0 damage, 0 rounds, Crew wiped" with no Fighters -- a defeat the
 * player had not had yet. The admin report has always distinguished the
 * four states and the feed is the same data, so it has to agree.
 */
ok(strpos($ARE, "\$live  = ((int)\$rn['outcome'] === 0);") !== false,
   'the feed cannot tell a running Crew from a finished one, so an unfinished run reads as '
 . 'a wipe');
ok(strpos($ARE, 'Still out there') !== false,
   'a run in progress has no wording of its own');
ok(strpos($ARE, 'Took one down') !== false,
   'a run that dropped a boss reads the same as one that did not, and that is the biggest '
 . 'thing a run can do');
/*
 * AND IT NAMES WHICH, WITH THE BODY.
 *
 * "Took one down" leaves the reader asking which, and which one it was is
 * the story of that run. dhc_boss_runs.killed is only a 0/1 flag, so the
 * boss comes from dhc_boss_day: the one whose killed_at falls inside this
 * run's own window. Attributing by user alone would credit every run that
 * player made all week with every boss they ever finished.
 */
ok(strpos($LIB, 'AND d.killed_at >= r.started_at') !== false
   && strpos($LIB, 'AND d.killed_at <= COALESCE(r.ended_at, NOW())') !== false,
   'the kill is not attributed to the run that made it, so one player\'s kill would be '
 . 'claimed by every run they sent this week');
ok(strpos($LIB, 'AND d.killed_by = r.user_id') !== false,
   'the kill is not attributed to the player who landed it');
ok(strpos($LIB, "\$rows[\$i]['kills']") !== false,
   'the report does not carry the kills, so the feed has nothing to name');
ok(strpos($ARE, "elseif (!empty(\$rn['kills'])):") !== false,
   'the feed does not name the boss a Crew dropped');
ok(strpos($ARE, 'class="a-rf fell a-rkill"') !== false,
   'the boss a Crew dropped is not shown, or is not greyed -- it fell too');
/*
 * NOT A FOURTH FIGHTER, AND NOT IN THE PLAYER'S HALF OF THE ROW.
 *
 * It was tacked onto the end of the Crew, which made a row with a kill
 * physically wider and shoved damage, rounds and the result right by a
 * tile or two -- every column in the feed lost its line. It is also the
 * wrong place for it: a boss you dropped is a RESULT, not a member of
 * your Crew. It lives with the outcome words on the right now, and the
 * Crew cell is pinned to three so the columns cannot move.
 */
ok(strpos($ARE, '.arena-wrap .a-runcrew{display:flex;gap:4px;flex:0 0 122px}') !== false,
   'the Crew cell is not pinned to three Fighters, so a row with a kill on it is wider '
 . 'than its neighbours and every column after it loses its line');
$crewCell = substr($ARE, strpos($ARE, 'class="a-runcrew"'),
                   strpos($ARE, 'class="a-runnum"') - strpos($ARE, 'class="a-runcrew"'));
ok(strpos($crewCell, 'a-rkill') === false,
   'the dropped boss is back inside the Crew cell, which is what pushed the columns out');
ok(strpos($ARE, 'class="a-runres"') !== false
   && strpos($ARE, '.arena-wrap .a-runres{display:flex;align-items:center;gap:10px;margin-left:auto}') !== false,
   'the result block is gone, so the kill and the outcome no longer land on one right edge');
/* THE BODIES ARE LAST AND IN A RESERVED SLOT. Left of the words they
   floated to wherever the sentence happened to end and no two rows lined
   up; the slot is three wide whether or not a run filled it, so the text
   ends on one column and the pictures start on another. */
ok(strpos($ARE, '.arena-wrap .a-runkills{display:flex;gap:4px;flex:0 0 122px;justify-content:flex-end}') !== false,
   'the dropped bosses have no reserved slot, so they float with the length of the '
 . 'outcome sentence and no two rows line up');
$resAt = strpos($ARE, 'class="a-runres"');
$outAt = strpos($ARE, 'class="a-runout live"', $resAt);
$kilAt = strpos($ARE, 'class="a-runkills"', $resAt);
ok($outAt !== false && $kilAt !== false && $outAt < $kilAt,
   'the dropped bosses are drawn before the outcome text, so they are not hard right');
ok(strpos($ARE, 'a-rkill{border-color:rgba(255,92,60,.5);cursor:default}') !== false,
   'the dropped boss looks clickable, and a boss has no Fighter panel to open');
/* THE NUMBERS TOO. A nought before the run resolves is the absence of a
   result, not a result, and printing it says the Crew achieved nothing. */
ok(preg_match("~\\\$live \\? '&mdash;' : number_format\\(\\(int\\)\\\$rn\\['dealt'\\]\\)~", $ARE) === 1,
   'a running Crew is shown as 0 damage rather than no damage yet');
ok(preg_match("~\\\$live \\? '&mdash;' : \\(int\\)\\\$rn\\['rounds'\\]~", $ARE) === 1,
   'a running Crew is shown as 0 rounds rather than no rounds yet');
ok(strpos($ARE, 'class="a-rf pending"') !== false,
   'a running Crew leaves a hole where the Fighters go instead of holding the row shape');

/*
 * CLICKING A FIGHTER OPENS THE COLLECTION'S PANEL, not a second one built
 * here. The proven ranks, the trait metadata and the download all live in
 * dhc-fighter-modal.php, and a copy is how two surfaces start disagreeing
 * about what a Fighter is.
 */
ok(strpos($ARE, "include __DIR__ . '/dhc-fighter-modal.php';") !== false,
   'the shared Fighter panel is not included, so a click has nothing to open');
ok(strpos($ARE, "ajax/dhc-fighter.php?serial=") !== false,
   'the feed does not fetch the shared Fighter payload');
ok(strpos($ARE, 'window.DHC_MODAL.open(d.fighter)') !== false,
   'the fetched Fighter is never handed to the panel');
ok(strpos($ARE, '$dhcm_deeplink = false;') !== false,
   'the panel rewrites the address here, and dhcgallery.php owns the ?fighter= URL');
/* A BUTTON, so it is reachable by keyboard and announces itself. */
ok(preg_match('~<button type="button" class="a-rf~', $ARE) === 1,
   'the Fighter tile is not a button, so it opens a panel but cannot be tabbed to');
ok(strpos($ARE, 'data-serial="<?php echo (int)$f[\'serial\']; ?>"') !== false,
   'the tile carries no serial, so the click has nothing to look up');
ok(strpos($ARE, "t.classList.contains('pending')") !== false,
   'an unresolved placeholder is clickable and would look up serial 0');
/* AND HOVER BRINGS THE COLOUR BACK, which is the other half of the ask and
   doubles as the affordance that says the tile can be pressed. */
ok(strpos($ARE, '.arena-wrap .a-rf:hover img{filter:none;opacity:1}') !== false,
   'hovering a fallen Fighter no longer shows it in full colour');
ok(strpos($ARE, '.arena-wrap .a-rf{cursor:pointer}') !== false,
   'nothing says the Fighter tiles are clickable');

/* ---------------------------------------------------------------- *
 * THE POST NAMES BOTH CREWS
 * ---------------------------------------------------------------- */
echo "\nthe X post says whose damage it was and what is falling\n";
/*
 * It was written as fragments -- "4,800 damage into the DHC boss crew. 62%
 * down and still standing." -- and a reader who does not already play
 * cannot tell whose damage that was or what is 62% down. Two actors, both
 * named, in full sentences. The owner's rewrite, verbatim.
 */
$sh = dhcb_share_text(4800, array(), array('pct' => 0.62, 'cleared' => false));
ok($sh === 'My DHC Fighter Crew inflicted 4,800 damage. The DHC Boss Crew is 62% defeated '
         . 'and still standing.',
   'the ordinary post is not the agreed wording: ' . $sh);
/* Both actors named in EVERY variant, not just the common one. */
foreach (array(
	array(7310, array('X-K4T'),            array('pct'=>0.78, 'cleared'=>false)),
	array(5120, array('CIP-666','X-K4T'),  array('pct'=>0.91, 'cleared'=>false)),
	array(9044, array('D34THBRNGR'),       array('pct'=>1.0,  'cleared'=>true)),
) as $cse) {
	$t = dhcb_share_text($cse[0], $cse[1], $cse[2]);
	ok(strpos($t, 'My DHC Fighter Crew') !== false,
	   'a post does not say whose Crew did the damage: ' . $t);
	ok(strpos($t, 'DHC Boss Crew') !== false,
	   'a post does not name what is falling: ' . $t);
	/* Still under budget once the tail and the shortened URL are counted. */
	$tail = "\n\nJoin @skulliance and take on the DHC boss crew, free and in any browser."
	      . "\n\nArt by @MMAXI404";
	ok(mb_strlen($t . $tail) + 24 <= 280,
	   'the post overflows 280 with the tail and url: ' . (mb_strlen($t . $tail) + 24));
}
/* ONE grammatical trap, since the kill list is built from a variable-length
   array: "CIP-666 and X-K4T is down" would be the obvious way to get it
   wrong. */
ok(strpos(dhcb_share_text(1, array('X-K4T'), array('pct'=>0.5)), 'X-K4T is down') !== false,
   'a single kill reads as plural');
ok(strpos(dhcb_share_text(1, array('CIP-666','X-K4T'), array('pct'=>0.5)), 'X-K4T are down') !== false,
   'two kills read as singular');
/* AND IT NEVER SAYS "AT". progress.pct is damage DEALT, so "they are at
   42%" reads as 42% health LEFT and means the opposite. */
/* EVERY VARIANT, not just the one without kills -- the kill line carries its
   own percentage and a mutation that flipped only that one went uncaught. */
foreach (array(0.0, 0.42, 1.0) as $pc) {
	foreach (array(array(), array('X-K4T'), array('CIP-666','X-K4T')) as $kn) {
		$t = dhcb_share_text(100, $kn, array('pct'=>$pc, 'cleared'=>($pc >= 1.0)));
		ok(strpos($t, 'is at ') === false && strpos($t, 'are at ') === false,
		   'the post says the crew is "at" a percentage, which reads as health remaining and '
		 . 'means the opposite of what it counts: ' . $t);
	}
}
/* And the kill line says which way ITS number runs, in the same word the
   rest of the post uses. */
/*
 * AND THE END CARD RUNS THE SAME WAY, because the share button is on it.
 * It read "they are at 94%" -- health REMAINING -- directly above a post
 * saying "6% defeated", which is damage DEALT. Two numbers for the same
 * thing, a click apart, pointing opposite ways.
 */
ok(strpos($ARE, "' The crew is ' + beat + '% defeated and still standing.'") !== false
   && strpos($ARE, 'var beat = Math.min(99, Math.round(100 * pr.pct));') !== false,
   'the end card reports health remaining while the share under it reports damage dealt');
ok(strpos($ARE, "Math.round(100 * (1 - pr.pct))") === false,
   'the end card still inverts the percentage, so it disagrees with every other surface');
ok(strpos($ARE, "beat > 0 ?") !== false,
   'the end card prints a nought percentage, which is the thing the post refuses to do');
/*
 * AND IT DOES NOT CONGRATULATE A CREW FOR SOMEBODY ELSE'S KILL. Damage
 * commits per move now, so a run ENDS when the crew falls under it -- the
 * common ending once a crew is close. Reaching the clear card from there
 * would hand this Crew a kill another player landed.
 */
ok(strpos($ACT, "\$out['assist'] = !\$killed && !empty(\$b['waveProg']['cleared']);") !== false,
   'the endpoint never tells the page the crew fell to somebody else, so the end card '
 . 'cannot tell a clear from an assist');
$asAt = strpos($ARE, 'if (dropped && res.assist) {');
$drAt = strpos($ARE, '} else if (dropped) {');
ok($asAt !== false && $drAt !== false && $asAt < $drAt,
   'the end card has no assist branch before the clear branch, so a Crew that was merely '
 . 'present when the crew fell is told it finished them');
/* STRIPPED, because the branch's own comment explains that it must not say
   "that is the crew down" -- so the check matched the explanation and
   reported a passing branch as broken. Same trap, caught by the same tool
   the rest of this file uses. */
$asBlock = ($asAt !== false && $drAt !== false)
	? harness_strip_comments(substr($ARE, $asAt, $drAt - $asAt)) : '';
ok(strpos($asBlock, 'that is the crew down') === false
   && strpos($asBlock, 'Another Crew landed the last blow') !== false,
   'the assist card claims the clear instead of naming who got there first');

ok(strpos(dhcb_share_text(100, array('X-K4T'), array('pct'=>0.5, 'cleared'=>false)),
          '50% defeated') !== false,
   'the kill line does not say which way its percentage runs: '
 . dhcb_share_text(100, array('X-K4T'), array('pct'=>0.5, 'cleared'=>false)));

/*
 * A NOUGHT NEVER REACHES A POST, ON EITHER SURFACE.
 *
 * "The DHC Boss Crew is now 0% down" was the owner's catch. It reads as a
 * crew that took no damage at all, and the two ways to produce it both mean
 * something else: a crew that was just DEFEATED, whose percentage belongs to
 * the untouched wave standing behind it, and a run whose damage rounded away
 * against a six-figure bar. The first is the worst thing either surface can
 * say -- it reports the biggest event of the week as nothing happening.
 */
foreach (array(
	array('a defeated crew',            array('pct'=>0.0,    'cleared'=>true)),
	array('a defeated crew at full pct', array('pct'=>1.0,   'cleared'=>true)),
	array('damage that rounds away',    array('pct'=>0.0003, 'cleared'=>false)),
	array('a crew barely touched',      array('pct'=>0.004,  'cleared'=>false)),
) as $cse) {
	$t = dhcb_share_text(2100, array(), $cse[1]);
	/* \b0% AND NOT strpos: "100% defeated" contains "0%", and a check that
	   cannot tell the two apart fails on the one post that is most right. */
	ok(preg_match('~\\b0%~', $t) === 0,
	   'the share posts a nought percentage for ' . $cse[0] . ': ' . $t);
}
ok(strpos(dhcb_share_text(2100, array(), array('pct'=>0.0, 'cleared'=>true)), 'DEFEATED') !== false,
   'a share for a crew that has been defeated does not say so: '
 . dhcb_share_text(2100, array(), array('pct'=>0.0, 'cleared'=>true)));
ok(strpos(dhcb_share_text(2100, array(), array('pct'=>0.0, 'cleared'=>true)), 'standing') === false,
   'a share calls a defeated crew still standing');
/* And a crew that IS standing still says so, with no number it cannot support. */
$t0 = dhcb_share_text(300, array(), array('pct'=>0.0003, 'cleared'=>false));
ok(strpos($t0, 'still standing') !== false && strpos($t0, '%') === false,
   'a run whose damage rounds away offers a percentage anyway: ' . $t0);
/*
 * AND NEITHER END OF THE SCALE BELONGS TO A STANDING CREW.
 *
 * Holding the nought off introduced the other one: 99.9% rounds up, so the
 * post read "the DHC Boss Crew is 100% defeated and still standing", which
 * contradicts itself inside eight words. dhcb_pct_standing() is the one
 * home for the rule and every surface goes through it.
 */
ok(dhcb_pct_standing(0.999) === 99 && dhcb_pct_standing(0.9999) === 99,
   'a crew on a sliver is reported as 100% defeated while it is still standing');
ok(dhcb_pct_standing(1.0) === 99, 'a standing crew can be reported at a flat hundred');
ok(dhcb_pct_standing(0.0004) === 0, 'damage that rounds away is given a number anyway');
ok(dhcb_pct_standing(0.615) === 62, 'the ordinary case stopped rounding: '
                                  . dhcb_pct_standing(0.615));
$edge = dhcb_share_text(9000, array(), array('pct'=>0.999, 'cleared'=>false));
ok(strpos($edge, '99% defeated') !== false && strpos($edge, '100%') === false,
   'the post calls a standing crew 100% defeated: ' . $edge);
/* The 100% case is the one a naive "contains 0%" check gets wrong, so the
   CLEARED post is asserted the other way round: it must say so in words. */
ok(strpos(dhcb_share_text(9000, array(), array('pct'=>1.0, 'cleared'=>true)), 'DEFEATED') !== false,
   'a defeated crew is not named as defeated');
ok(strpos($ARE, 'Math.min(99, Math.round(100 * pr.pct))') !== false,
   'the end card can print 100% beside "still standing", which the post cannot');

/*
 * AND THE DISCORD POST, EXECUTED RATHER THAN GREPPED.
 *
 * The same nought reached Discord by a different route: $wiped is narrow on
 * purpose (it means THIS Crew finished them), so an assisting run fell
 * through to the branch that prints the standing wave's percentage -- and
 * the standing wave is the one that stood up in the same request. With
 * damage committing per move a run now ENDS when the crew falls under it,
 * which makes that the common case rather than a rare one.
 */
function hb_post($conn, $b, $dealt, $killedKeys) {
	$GLOBALS['HB_POSTS'] = array();
	dhcb_announce($conn, $b, $dealt, $killedKeys);
	return $GLOBALS['HB_POSTS'] ? $GLOBALS['HB_POSTS'][0] : array('title'=>'', 'desc'=>'');
}
/** A finished boss battle, as dhcb_announce() reads one. */
function hb_announce_battle($waveProg, $wave = 1) {
	return array(
		'mine' => array(array('name'=>'A','ko'=>true), array('name'=>'B','ko'=>true),
		                array('name'=>'C','ko'=>false)),
		'foes' => array(), 'waveProg' => $waveProg,
		'meta' => array('attacker'=>1, 'run_id'=>9, 'day'=>'2026-10-25', 'wave'=>$wave),
	);
}
$ca = new HBConn();
/* The crew this run fought is gone and wave 2 is standing, untouched --
   exactly the state the request is in microseconds after somebody else
   landed the last blow. */
$ca->wave = 2;
$ca->health = $ca->maxHealth = array('d34thbrngr'=>9000, 'cip-666'=>9000, 'x-k4t'=>9000);
$fellProg = array('pct'=>1.0, 'cleared'=>true, 'wave'=>1, 'downed'=>1,
                  'max'=>9000, 'left'=>0, 'dealt'=>9000, 'dead'=>3, 'total'=>3);
$post = hb_post($ca, hb_announce_battle($fellProg, 1), 2100, array());
ok(preg_match('~\\b0%~', $post['desc']) === 0,
   'the Discord post says the crew is 0% down when it had just been defeated: ' . $post['desc']);
ok(stripos($post['desc'], 'DEFEATED') !== false,
   'the post never says the crew this Crew fought went down: ' . $post['desc']);
ok(strpos($post['desc'], 'still out there') !== false,
   'the post claims the clear for a Crew that only assisted, or does not place it at all: '
 . $post['desc']);
/* An ordinary run against a standing crew still gets its percentage. */
$cb = new HBConn();
$cb->wave = 1;
$cb->health = array('d34thbrngr'=>4000, 'cip-666'=>4000, 'x-k4t'=>4000);
$cb->maxHealth = array('d34thbrngr'=>9000, 'cip-666'=>9000, 'x-k4t'=>9000);
$standProg = array('pct'=>0.555, 'cleared'=>false, 'wave'=>1, 'downed'=>0,
                   'max'=>27000, 'left'=>12000, 'dealt'=>15000, 'dead'=>0, 'total'=>3);
$post2 = hb_post($cb, hb_announce_battle($standProg, 1), 900, array());
ok(strpos($post2['desc'], '56%') !== false,
   'an ordinary run lost the percentage that describes the crew: ' . $post2['desc']);
ok(stripos($post2['desc'], 'still out there') === false,
   'an ordinary run is reported as an assist: ' . $post2['desc']);
/* And a crew nobody has dented yet is described, not numbered. */
$cc2 = new HBConn();
$cc2->wave = 1;
$cc2->health = $cc2->maxHealth = array('d34thbrngr'=>9000, 'cip-666'=>9000, 'x-k4t'=>9000);
$freshProg = array('pct'=>0.0008, 'cleared'=>false, 'wave'=>1, 'downed'=>0,
                   'max'=>27000, 'left'=>26978, 'dealt'=>22, 'dead'=>0, 'total'=>3);
$post3 = hb_post($cc2, hb_announce_battle($freshProg, 1), 22, array());
/* THE POST HAS TO NAME BOTH WAVES: the one that fell, and the one standing
   in its place. Naming only one is how it came to describe a defeated crew
   with its replacement's numbers. */
ok(strpos($post['desc'], 'Wave **1** was') !== false
   && strpos($post['desc'], '**wave 2** is standing now') !== false,
   'the assist post does not separate the crew that fell from the one standing: ' . $post['desc']);
/* HB_SHOW=1 php dhcbosses-harness.php prints these three posts, for reading
   the copy rather than asserting on it. */
if (getenv('HB_SHOW')) {
	foreach (array('assist'=>$post, 'ordinary'=>$post2, 'fresh'=>$post3) as $lbl => $pp)
		echo "\n--- $lbl ---\n" . $pp['title'] . "\n" . $pp['desc'] . "\n";
}
ok(preg_match('~\\b0%~', $post3['desc']) === 0,
   'the post offers a nought percentage for a crew barely touched: ' . $post3['desc']);
/* And the other end: a crew on a sliver is not a hundred per cent defeated
   while it is still standing, on Discord either. */
$cd2 = new HBConn();
$cd2->wave = 1;
$cd2->health    = array('d34thbrngr'=>4, 'cip-666'=>4, 'x-k4t'=>4);
$cd2->maxHealth = array('d34thbrngr'=>9000, 'cip-666'=>9000, 'x-k4t'=>9000);
$edgeProg = array('pct'=>0.9995, 'cleared'=>false, 'wave'=>1, 'downed'=>0,
                  'max'=>27000, 'left'=>12, 'dealt'=>26988, 'dead'=>0, 'total'=>3);
$post4 = hb_post($cd2, hb_announce_battle($edgeProg, 1), 4000, array());
ok(strpos($post4['desc'], '99%') !== false && strpos($post4['desc'], '100%') === false,
   'the post calls a standing crew 100% defeated: ' . $post4['desc']);

/*
 * AND THE WEEK-CLOSE POST HAS THE SAME TRAP ONE SIZE UP.
 *
 * Its percentage is read off the crew STILL STANDING, which by Thursday may
 * be the third of the week -- so a week that defeated two crews and left
 * the replacement untouched posted "the crew took 0% damage and held",
 * reporting the best week the community had ever had as nothing happening.
 */
$GLOBALS['HB_POSTS'] = array();
dhcb_announce_day(new HBConn(), '2026-10-25',
	array('pct'=>0.0, 'cleared'=>false, 'wave'=>3, 'downed'=>2,
	      'max'=>9000, 'left'=>9000, 'dealt'=>0, 'dead'=>0, 'total'=>3),
	'placement_1', 6, 31);
$wk = $GLOBALS['HB_POSTS'] ? $GLOBALS['HB_POSTS'][0]['desc'] : '';
ok(preg_match('~\b0%~', $wk) === 0,
   'the week closes on "0% damage" after a week that dropped two crews: ' . $wk);
ok(strpos($wk, 'DEFEATED 2 crews') !== false,
   'the week-close post does not name the crews the week put down: ' . $wk);
/* A week that dropped nothing still reports what the one crew took. */
$GLOBALS['HB_POSTS'] = array();
dhcb_announce_day(new HBConn(), '2026-10-25',
	array('pct'=>0.37, 'cleared'=>false, 'wave'=>1, 'downed'=>0,
	      'max'=>9000, 'left'=>5670, 'dealt'=>3330, 'dead'=>0, 'total'=>3),
	'placement_10', 4, 18);
$wk2 = $GLOBALS['HB_POSTS'] ? $GLOBALS['HB_POSTS'][0]['desc'] : '';
ok(strpos($wk2, '37%') !== false && stripos($wk2, 'DEFEATED') === false,
   'a week that dropped nothing either loses its number or claims a clear: ' . $wk2);
/* And a week that ended one hit short is not reported as a clear. */
$GLOBALS['HB_POSTS'] = array();
dhcb_announce_day(new HBConn(), '2026-10-25',
	array('pct'=>0.9996, 'cleared'=>false, 'wave'=>1, 'downed'=>0,
	      'max'=>27000, 'left'=>11, 'dealt'=>26989, 'dead'=>2, 'total'=>3),
	'placement_3', 9, 44);
$wk3 = $GLOBALS['HB_POSTS'] ? $GLOBALS['HB_POSTS'][0]['desc'] : '';
ok(strpos($wk3, '99%') !== false && strpos($wk3, '100%') === false,
   'the week closes calling a crew that held 100% damaged: ' . $wk3);

/* ---------------------------------------------------------------- *
 * THE POST DESCRIBES THIS RUN, NOT THE WEEK AROUND IT
 * ---------------------------------------------------------------- */
echo "\nthe announcement says what this Crew did and nothing else\n";
/*
 * THREE THINGS WRONG IN TWO POSTS FROM THE FIRST CLEAR, all of them the
 * same mistake: reading the world instead of the run.
 *
 * ONE. "They are getting back up angrier -- wave 1 is standing now."
 * dhcb_state($conn, $day) with an explicit day SKIPS dhcb_spawn(), and the
 * announcement runs microseconds after the kill that emptied the crew. So
 * it read the wave it had just finished and named the dead crew as the one
 * standing.
 */
$ann = substr($LIB, strpos($LIB, 'function dhcb_announce('));
$ann = substr($ann, 0, strpos($ann, 'function dhcb_announce_day('));
ok(strpos($ann, '$state = dhcb_state($conn);') !== false,
   'the announcement passes an explicit day to dhcb_state(), which skips the respawn, so '
 . 'it names the crew that just died as the one standing');
ok(strpos($ann, '$state = dhcb_state($conn, $day);') === false,
   'the announcement still reads a fixed day and will report a stale wave');
/*
 * TWO. A player whose Crew was wiped for nothing got a post headed "wave 1
 * is down", claiming a clear they had no part in, two minutes after the
 * player who actually did it. $wiped asked whether the WAVE had moved on,
 * which is true for every run still out there when somebody else finished
 * it.
 */
ok(strpos($ann, '$wiped    = !empty($kname) && !empty($prog[\'cleared\'])') !== false,
   'the clear headline does not require this run to have killed something, so every run '
 . 'still out there claims somebody else\'s clear');
/*
 * THREE. "0 damage dealt" and then "OrbiTall! landed the most of them:
 * 1,388" three lines below it. mine[]['dealt'] is damage to this run's
 * SNAPSHOT; the shared bar clamps what actually lands. Two numbers from
 * either side of the clamp, contradicting each other in one embed.
 */
ok(strpos($ann, '$bestD = ($swing > 0) ? (int)round((int)$dealt * $bestD / $swing) : 0;') !== false,
   'the hero figure is unscaled snapshot damage, so it can exceed -- or contradict -- the '
 . 'run\'s own committed total');
ok(strpos($ann, "elseif ((int)\$dealt === 0)") !== false,
   'a fully clamped run still credits a Fighter with damage the bar never took');

/* ---------------------------------------------------------------- *
 * EVERY ANNOUNCEMENT CARRIES A PICTURE
 * ---------------------------------------------------------------- */
echo "\nthe Discord post shows what happened, not just what it totalled\n";
/*
 * Only a run that KILLED something had an image, and losing is the normal
 * outcome here by design -- so the ordinary post was a wall of text sitting
 * in a feed of embeds. Three states, strongest first: the crew went down
 * (the player's own best Fighter did it), a boss went down (that boss), or
 * neither (the boss that hit the player hardest, which is the question a
 * wiped player actually has).
 *
 * Read off the SOURCE rather than posted to Discord: the webhook is a
 * credential this harness does not have and must not use.
 */
$ANN = substr($LIB, strpos($LIB, 'function dhcb_announce('));
$ANN = substr($ANN, 0, strpos($ANN, 'function dhcb_announce_day('));
ok(strpos($ANN, 'dhc-card.php?serial=') !== false,
   'a run that drops the crew does not show the Fighter that did it');
ok(strpos($ANN, "SELECT serial FROM dhc_fighters WHERE id =") !== false,
   'the Fighter card is built from the row id, but dhc-card.php keys on serial, so the '
 . 'image 404s and the embed loses its picture');
ok(preg_match('~foreach \(\$b\[.foes.\] as \$f\).*?bossKey~s', $ANN) === 1,
   'nothing picks the boss that hit hardest, so an ordinary run still has no picture');
/* All three branches assign $img, and the last one is unconditional apart
   from there being a boss at all -- so there is no path to a pictureless
   post on a run that happened. */
ok(substr_count($ANN, '$img = ') >= 3,
   'there are fewer than three ways to get a picture, so some runs still post without one');
ok(strpos($ANN, 'if ($img === \'\') {') !== false,
   'the fallback picture is not a fallback, so it can overwrite the kill shot');
/* And the post says who did it, or the picture is unexplained. */
ok(strpos($ANN, '$didIt') !== false && strpos($ANN, '$desc .= "\n" . $didIt') !== false,
   'the picture is never named in the text, so the reader cannot tell why it is there');

/* ---------------------------------------------------------------- *
 * THE CREW IS SIZED FOR PEOPLE, NOT FOR A SOLVER
 * ---------------------------------------------------------------- */
echo "\nthe health budget is built from what a run really deals\n";
/*
 * THE BUG THIS EXISTS FOR, AND IT WAS GREEN THE WHOLE TIME.
 *
 * DHCB_DMG_PER_RUN was measured by playing the engine with a greedy search
 * over all 112 slides every turn, and as a measurement of the engine it is
 * correct: ~1,000 damage a run. Nobody plays that way. The first real run on
 * the live site, by a player who knows the game, landed 535. A deliberately
 * weak model of play lands about 200.
 *
 * The budget was built from the solver's figure, so the crew was about 1.8x
 * too big -- 7.5 days to clear against a 7-day week. It would never have
 * fallen: no wave 2, nobody on the best trait table, and no error anywhere.
 * Every check in this file passed, because the checks computed their
 * ceilings from the same wrong constant the budget used. They grade the
 * shipped formula now.
 */
ok(strpos($LIB, '$perRun = dhcb_dmg_per_run($conn)') !== false,
   'the budget is back on the raw constant, which is a greedy solver\'s ceiling and about '
 . 'twice what a person lands');

/* THE FALLBACK, for a week with no ledger behind it yet. */
$fb = dhcb_dmg_per_run(hb_conn(1, 1, 0, 0));
ok(abs($fb - DHCB_DMG_PER_RUN * DHCB_HUMAN_PCT) < 1,
   'with no runs on the ledger the budget does not fall back to the human figure: ' . $fb);
ok($fb < DHCB_DMG_PER_RUN,
   'the fallback is not below the solver figure, so week one is sized for a solver again');

/* AND THE LEDGER TAKES OVER once there is enough of it. */
ok(abs(dhcb_dmg_per_run(hb_conn(1, 1, DHCB_CAL_MIN_RUNS, 600)) - 600) < 1,
   'the ledger is ignored even with enough runs on it, so the crew never adapts to the '
 . 'community actually playing it');
ok(abs(dhcb_dmg_per_run(hb_conn(1, 1, DHCB_CAL_MIN_RUNS - 1, 600)) - $fb) < 1,
   'a handful of runs is enough to move the budget, so one unlucky evening resizes the week');
/*
 * CLAMPED BOTH WAYS. Damage is capped at the health left on the bar, so runs
 * that finish a crew record less than they dealt -- left unbounded those
 * would drag the average down, shrink the next budget, and shrink it again.
 */
ok(dhcb_dmg_per_run(hb_conn(1, 1, 99, 5)) > 5,
   'a collapsed average is taken at face value, so the budget can spiral to nothing');
ok(dhcb_dmg_per_run(hb_conn(1, 1, 99, 99999)) <= DHCB_DMG_PER_RUN * 1.10 + 1,
   'a freak average is taken at face value, so one big week makes the crew unclearable');

/*
 * RUN COUNT IS BEHAVIOUR, NOT HEADCOUNT, and this is the second half of the
 * same mistake.
 *
 * The budget counted everybody who had fought once in the window and assumed
 * each would send the full allowance every day for seven days. The live
 * Arena ladder is the counter-example: ten players, the top three are 83% of
 * all activity, and the bottom five average under a tenth of their
 * allowance. Ten x three x seven is 210 runs a week; their behaviour says
 * about 55. The crew was 3.8x too big on this axis, ON TOP of the 1.8x from
 * the solver's damage figure -- and the reach clamp could not catch either,
 * because the clamp is built from the same two numbers.
 */
ok(strpos($LIB, '$byBehaviourDay = dhcb_runs_per_day($conn)') !== false,
   'the budget is back on headcount x allowance, which the live ladder says overstates '
 . 'turnout by nearly four times');
ok(strpos($LIB, 'players\'] * (int)DHCB_DAILY_RUNS') === false,
   'headcount x allowance is still in the budget somewhere');
/*
 * THE LIVE LADDER, AS A TEST CASE. Monthly battles per player, taken off the
 * board on 2026-10-25, converted the way the library converts them: the
 * share of a player's six daily Arena battles they actually use is the best
 * available guess at the share of their three Crews they will send.
 */
$ladderShape = array(122, 131, 73, 37, 13, 9, 4, 1, 2, 2);   // battles in ~25 days
$rpdReal = 0;
foreach ($ladderShape as $n) $rpdReal += min(DHCB_DAILY_RUNS, ($n / 25) / 6 * DHCB_DAILY_RUNS);
ok($rpdReal < count($ladderShape) * DHCB_DAILY_RUNS * 0.5,
   sprintf('the live ladder models as %.1f Crews a day against an allowance of %d, so this '
	 . 'case is not actually skewed and proves nothing', $rpdReal,
	 count($ladderShape) * DHCB_DAILY_RUNS));
/*
 * WHAT IT COSTS, ON THE LIVE TURNOUT CURVE, SIMULATED THROUGH THE SHIPPED
 * BUDGET -- not estimated. Last time a number here was assumed rather than
 * measured the crew came out 3.8x too big with every check green.
 *
 * The week is walked day by day: the community deals runs-per-day x
 * damage-per-run, a wave that runs out stands the next one up budgeted
 * against the days remaining, and the wave standing on each round is what
 * that round pays.
 */
function hb_week_waves($rpd, $players, $fighters) {
	$c = hb_conn($players, $fighters, 0, 0, $rpd);
	$wave = 1; $hp = dhcb_crew_budget($c, 7, 1); $out = array();
	for ($d = 0; $d < 7; $d++) {
		$out[] = $wave;
		$hp -= $rpd * hb_per_run($wave);
		$guard = 0;
		while ($hp <= 0 && $guard++ < 20) {
			$wave++;
			$hp += dhcb_crew_budget($c, max(0.5, 7 - ($d + 1)), $wave);
		}
	}
	return $out;
}
$waves = hb_week_waves($rpdReal, count($ladderShape), count($ladderShape) * 9);
printf("  week on the live curve: waves %s\n", implode(' ', $waves));
ok(max($waves) > 1,
   'the simulated week never drops a crew, so this whole section is measuring nothing');
/* Attendance is the same conversion the budget uses: the share of a
   player's six daily Arena battles they actually use. */
$was = 0; $now = 0;
foreach ($ladderShape as $n) {
	$share = min(1.0, ($n / 25) / 6);
	foreach ($waves as $w) { $was += $share; $now += $share * dhcb_round_traits($w); }
}
printf("  traits a week, community-wide: %.1f -> %.1f (%.2fx)\n", $was, $now, $now / max(0.01, $was));
ok($now > $was, 'the multiplier changes nothing on the real curve, so it is not worth the code');
/*
 * THE CEILING THAT MATTERS. Every one of these rolls on placement_1 once a
 * crew is down (8% mythic against 0.5% on the base table), so the thing to
 * hold down is the MULTIPLE, not the absolute. Two times is the judgement:
 * it is a real reason to come back for the back half of the week and it is
 * nowhere near a supply event.
 */
ok($now / max(0.01, $was) <= 2.0,
   sprintf('the wave multiplier more than doubles trait supply on the live curve '
	 . '(%.1f -> %.1f); every one of those rolls on the best table', $was, $now));

$hpReal  = hb_budget(10, 90, 7, 1, 0, 0, $rpdReal);
$dayReal = $rpdReal * hb_per_run();
ok($hpReal / $dayReal <= 6.0,
   sprintf('on the REAL participation curve the crew takes %.1f days, so nobody ever sees '
	 . 'it fall: %s health against %s damage a day',
	 $hpReal / $dayReal, number_format($hpReal), number_format(round($dayReal))));
ok($hpReal / $dayReal >= 2.0,
   sprintf('on the real curve the crew falls in %.1f days, leaving the rest of the week '
	 . 'to waves that are themselves too small to matter', $hpReal / $dayReal));
/* WEEK ONE HAS NO BOSS LEDGER, so the Arena estimate has to answer. */
ok(dhcb_runs_per_day(hb_conn(10, 90, 0, 0, 7.9)) === 7.9,
   'the Arena estimate is ignored, so week one has no run rate at all');
/* AND THE LEDGER TAKES OVER once there is enough of it -- the real thing
   beats a model converted out of a different game. */
$led = dhcb_runs_per_day(hb_conn(10, 90, 0, 0, 7.9, DHCB_CAL_MIN_RUNS * 2, 10));
ok(abs($led - (DHCB_CAL_MIN_RUNS * 2) / 10) < 0.001,
   'the boss ledger is ignored in favour of an Arena model, so the rate never becomes the '
 . 'real one: ' . $led);
ok(dhcb_runs_per_day(hb_conn(10, 90, 0, 0, 7.9, DHCB_CAL_MIN_RUNS - 1, 10)) === 7.9,
   'a handful of boss runs overrides the Arena estimate, so one quiet day resizes the week');

/*
 * THE CONVERSION ITSELF, read off the SQL. The stub above answers with a
 * canned rate, so it cannot catch the estimate being replaced by a flat
 * allowance -- which is the exact fiction this is here to remove. The query
 * has to divide by the window and by the Arena's own allowance, and cap
 * each player at the boss allowance.
 */
$rpdSql = substr($LIB, strpos($LIB, 'AS rpd') - 100, 700);
ok(strpos($rpdSql, 'LEAST($cap,') !== false,
   'a player is not capped at the boss allowance, so a heavy Arena player implies more '
 . 'Crews than the rules permit');
ok(strpos($rpdSql, 'act.n / $d / $amax') !== false,
   'the estimate is not a share of what a player actually uses, so it is headcount x '
 . 'allowance again wearing a different query');
ok(strpos($rpdSql, 'GROUP BY attacker_id') !== false,
   'the estimate is not per player, so one heavy player is averaged across everybody');

/*
 * AND THE WHOLE POINT, STATED AS AN OUTCOME: a community that turns up has
 * to put the crew down BEFORE the week ends, or waves are dead content and
 * the best table is unreachable. This is the check that was missing.
 */
foreach (array(array(10, 90), array(3, 9), array(20, 60), array(6, 54)) as $pop) {
	list($pp, $pf) = $pop;
	$perDay = min($pp * DHCB_DAILY_RUNS, (int)floor($pf / DHCB_CREW_SIZE));
	$dayDmg = $perDay * hb_per_run();
	$days   = $dayDmg > 0 ? hb_budget($pp, $pf) / $dayDmg : 99;
	/* Full turnout is the ceiling case; the skewed case is checked above
	   against the real ladder. */
	ok($days <= 6.0,
	   sprintf('%d players with %d Fighters need %.1f days of full turnout to drop the crew, '
		 . 'so they never see a wave 2 or the best table', $pp, $pf, $days));
	/* Nor so small that a day and a half finishes it -- the other failure. */
	ok($days >= 2.0,
	   sprintf('%d players with %d Fighters clear the crew in %.1f days, which is the dead '
		 . 'rest-of-week this was all meant to avoid', $pp, $pf, $days));
}

/* ---------------------------------------------------------------- *
 * WAVES: THEY GET BACK UP
 * ---------------------------------------------------------------- */
echo "\na crew that falls mid-week stands up again instead of closing the feature\n";
/*
 * THE BLIND SPOT THIS CLOSES. Health is DHCB_TARGET_PCT of what the week can
 * deliver, so days-to-clear is 7 x 0.60 = 4.2 WHATEVER THE COMMUNITY SIZE,
 * capped at 7 x 0.85 = 6.0 when the reach clamp binds. The crew falling
 * mid-week is the DESIGNED outcome, not bad luck, and the first version had
 * nothing for it: entry closed with "they return tomorrow" (they returned
 * Thursday), the cache capped everybody at the days played before the clear,
 * and anyone short of DHCB_LADDER_MIN_RUNS could never reach the board.
 * Three of seven days dead, on the mechanic built to bring people back daily.
 */
$clearDay = 7 * DHCB_TARGET_PCT;
ok($clearDay < 7,
   'the crew is scaled to survive the week, so waves are solving a problem that no longer '
 . 'exists -- re-read this block before deleting it');

/*
 * THE TIER IS STICKY, AND IT IS THE ONE THING THAT MUST NOT BREAK.
 *
 * dhcb_tier_table() used to read `cleared`, which describes the crew on the
 * board. Stand a fresh crew at full health and that goes false and pct goes
 * to zero -- so the whole community would drop from the best table to the
 * worst as a direct consequence of winning, with nothing on screen saying so.
 */
function hb_prog($wave, $max, $left) {
	return dhcb_progress(array(
		array('max_health'=>$max, 'health'=>$left, 'dead'=>($left <= 0), 'wave'=>$wave)));
}
$justCleared = hb_prog(1, 100000, 0);
ok($justCleared['downed'] === 1, 'a cleared wave 1 does not count as a crew downed');
ok(dhcb_tier_table($justCleared) === 'placement_1', 'clearing the crew is not the best table');
$waveTwoFresh = hb_prog(2, 30000, 30000);
ok((float)$waveTwoFresh['pct'] === 0.0 && empty($waveTwoFresh['cleared']),
   'a fresh wave 2 does not read as untouched, so this cannot test the sticky tier');
ok($waveTwoFresh['downed'] === 1,
   'wave 2 standing does not imply wave 1 fell, so the week forgets what it achieved');
ok(dhcb_tier_table($waveTwoFresh) === 'placement_1',
   'standing a fresh crew DOWNGRADES the whole community from the best table to the worst, '
 . 'as a reward for winning -- the single worst failure waves can have');
/* And it stays locked however far the week goes. */
foreach (array(2, 3, 4, 5) as $w) {
	ok(dhcb_tier_table(hb_prog($w, 20000, 20000)) === 'placement_1',
	   'the table is not locked at wave ' . $w);
}
/* A week with nothing downed still reads off the live bar, as before. */
ok(dhcb_tier_table(hb_prog(1, 100000, 100000)) === 'run',  'an untouched crew is not the run table');
ok(dhcb_tier_table(hb_prog(1, 100000, 70000))  === 'placement_10', '30% down is not the improved table');
ok(dhcb_tier_table(hb_prog(1, 100000, 40000))  === 'placement_3',  '60% down is not the rare table');

/*
 * EACH WAVE IS BUDGETED AGAINST THE DAYS THAT ARE LEFT. A flat multiplier on
 * wave 1 is the failure mode here: 1.5x wave 1 with 2.8 days left needs 6.3
 * days to shift, so it is a wall nobody dents -- dead content again, just
 * later, which is exactly what waves exist to prevent.
 */
$full = hb_budget(10, 90, 7, 1);
$two  = hb_budget(10, 90, 2, 2);
ok($two < $full, 'a wave with two days left is not smaller than a wave with seven');
ok($two < $full / 2,
   'a late wave is barely smaller than the week-long one, so it cannot be cleared in the '
 . 'days remaining: ' . number_format($two) . ' vs ' . number_format($full));
/* Monotonic in the days remaining, which it must be or the pacing inverts. */
$prev = 0;
foreach (array(0.5, 1, 2, 3, 5, 7) as $d) {
	$hp = hb_budget(10, 90, $d, 2);
	ok($hp >= $prev, 'the budget is not monotonic in days remaining at ' . $d . ' days');
	$prev = $hp;
}
/*
 * AND IT IS STILL CLEARABLE IN THOSE DAYS. The point of sizing to the time
 * left is that the time left is enough, so the check is the same shape as
 * the one wave 1 gets: what the remaining days can physically deliver has to
 * exceed what is standing.
 */
foreach (array(array(10, 90), array(3, 9), array(20, 60)) as $pop) {
	list($pp, $pf) = $pop;
	foreach (array(0.5, 1, 2, 3) as $d) {
		$perDay = min($pp * DHCB_DAILY_RUNS, (int)floor($pf / DHCB_CREW_SIZE));
		foreach (array(2, 3, 4) as $w) {
			$ceiling = $perDay * $d * hb_per_run($w);
			if (!ok(hb_budget($pp, $pf, $d, $w) <= $ceiling + 1,
			        "wave $w with $d days left is bigger than $pp players can deliver in them"))
				break 3;
		}
	}
}
/*
 * THE WAVE FLOOR IS NOT WAVE 1'S FLOOR. DHCB_HP_MIN exists so a quiet week is
 * not trivial; applied to a crew standing on the last block of the week it
 * would be a 24,000 wall in front of a day nobody can finish.
 */
/*
 * THE CASE HAS TO STRADDLE THE FLOOR, and most do not: where the reach clamp
 * already cuts the budget below DHCB_HP_MIN, the floor could be wrongly
 * applied to every wave and nothing would show. Two small players over six
 * days budget to ~3,200 unfloored with reach at ~4,500, so the floor is the
 * only thing that could lift it to 4,000.
 */
/* Two small players over five days: wave 1 lands under the floor and gets
   lifted to it, wave 2 at the same inputs must not be. Checking only that a
   late wave is "below HP_MIN" passed with the floor wrongly applied, because
   the reach clamp cut it below HP_MIN anyway. */
$fl1 = hb_budget(2, 6, 6, 1);
$fl2 = hb_budget(2, 6, 6, 2);
ok($fl1 === (int)DHCB_HP_MIN,
   'wave 1 is not floored at DHCB_HP_MIN where it should be (' . number_format($fl1)
 . '), so this cannot tell whether later waves are exempt');
ok($fl2 < $fl1,
   'a late wave is floored like wave 1 (' . number_format($fl2) . ' vs ' . number_format($fl1)
 . '), which puts a wall in front of days nobody can finish');
ok(hb_budget(1, 3, 7, 1) >= 1, 'wave 1 can be budgeted to nothing');
/*
 * But not to nothing either, or the first two players to reach it clamp
 * everybody else out of a cache slot.
 *
 * DHCB_WAVE_MIN_DAY is a floor of 0.6 of a DAY, and the reach clamp is a
 * ceiling of 0.85 of what the REMAINING TIME can deliver -- with half a day
 * left the ceiling is the lower of the two and has to win, or the late wave
 * is unclearable, which is the failure waves exist to prevent. So the thing
 * to check is the share of the time left, not an absolute figure.
 */
foreach (array(0.5, 1, 2) as $d) {
	$per  = hb_per_run(3);
	$cap  = min(10 * DHCB_DAILY_RUNS, 30) * $d * $per;
	$hp   = hb_budget(10, 90, $d, 3);
	ok($hp >= 0.5 * $cap,
	   'a wave with ' . $d . ' days left is only ' . round(100 * $hp / $cap) . '% of what '
	 . 'those days can deliver, so the first players to reach it clamp everybody else out '
	 . 'of a cache slot');
	ok($hp <= $cap,
	   'a wave with ' . $d . ' days left needs more damage than those days can deliver');
}

/* HARDER MEANS ANGRIER. Power compounds; health does the pacing. */
ok(dhcb_wave_pow(1) === 1.0, 'wave 1 is not the baseline');
ok(dhcb_wave_pow(2) > dhcb_wave_pow(1) && dhcb_wave_pow(3) > dhcb_wave_pow(2),
   'later waves do not hit harder');
ok(abs(dhcb_wave_pow(3) - pow(DHCB_WAVE_POW, 2)) < 1e-9, 'the power curve is not compounding');
ok(strpos($LIB, "* \$b['pow'] * \$wpow") !== false,
   'the wave factor is not applied to boss power, so every wave hits exactly the same');

/*
 * THE DAMAGE COMMIT IS SCOPED TO THE WAVE. Without that, a run that started
 * against wave 1 and finished after somebody else dropped it would subtract
 * from wave 2 -- a crew it never fought, at a health it was never scaled
 * against. Scoped, it lands on a row already at zero and the clamp makes it
 * nothing, which is the correct answer.
 */
$fin = substr($LIB, strpos($LIB, 'function dhcb_commit('));
$fin = substr($fin, 0, strpos($fin, 'function dhcb_move('));
ok(strpos($fin, 'AND wave = $wave') !== false,
   'the FOR UPDATE read is not scoped to the wave, so a late-finishing run reads the wrong bar');
ok(strpos($fin, "AND wave = %d AND boss_key = '%s'") !== false,
   'the damage UPDATE is not scoped to the wave, so it lands on a crew nobody fought');
ok(strpos($fin, 'dhcb_spawn_memo(null, true)') !== false,
   'a kill does not clear the spawn memo, so the reply shows the crew that was just '
 . 'dropped and the end card never says the player finished them');
/* And the unlocked re-sync read is scoped the same way, or a board would
   follow a bar belonging to the wave that replaced the one it is fighting. */
ok(strpos($fin, "SELECT boss_key, health FROM dhc_boss_day\n\t\t                     WHERE day = '\$esc' AND wave = \$wave") !== false,
   'the re-sync read is not scoped to the wave, so a board follows the crew that '
 . 'replaced the one it is fighting');

/* THE STATE IS THE STANDING WAVE unless a caller names one, and the only
   caller that names one is the end of a run describing the crew it fought. */
ok(strpos($LIB, "(SELECT MAX(wave) FROM dhc_boss_day WHERE day = '\$esc')") !== false
   && strpos($LIB, 'AND wave = $sel') !== false,
   'dhcb_state() reads every wave of the week, so the bar shows a health nobody is fighting');
ok(strpos($LIB, '$sel = ($wave === null) ?') !== false,
   'dhcb_state() cannot be asked for a named wave, so a run that just cleared the crew '
 . 'is described against the untouched one that replaced it');

/* AND THE GATE NO LONGER CLOSES THE WEEK. */
ok(strpos($LIB, 'They return tomorrow.') === false,
   'the entry gate still closes the feature for the rest of the week, and names the wrong day');

/* THE MIGRATION IS WRITTEN DOWN, because nothing in the app creates columns. */
$SCH = file_get_contents(__DIR__ . '/dhcbosses-schema.md');
ok(strpos($SCH, 'ADD COLUMN wave TINYINT NOT NULL DEFAULT 1 AFTER day') !== false,
   'the wave migration is not in the schema notes, so the live database never gets the column');
ok(strpos($SCH, 'ADD PRIMARY KEY (day, wave, boss_key)') !== false,
   'the primary key is not widened, so wave 2 cannot be inserted at all');

/* THE TWIST IS VISIBLE. A wave the player cannot see is a number in a table. */
/*
 * HUE ONLY, ON EVERY WAVE RULE.
 *
 * The first pass pushed saturation, contrast and brightness as well, and the
 * owner read it exactly right: "instead of an overlay tint, can you just
 * adjust the hue of the boss art, that way it feels more cohesive instead of
 * filter altered." A hue rotation moves the colour and leaves every value
 * and relationship where the artist put them. Anything else is a treatment
 * laid over the art, and the art stops looking like itself.
 *
 * So the check is on the FILTER VALUE, not on the rule existing: a rule that
 * says filter:sepia(1) satisfies "there is a filter here" and fails the
 * thing that was actually asked for.
 */
$waveRules = array();
preg_match_all('~\[data-wave="([2-5])"\][^\{]*\{filter:([^}]+)\}~', $ARE, $wm, PREG_SET_ORDER);
foreach ($wm as $hit) if (strpos($hit[0], 'dead') === false) $waveRules[] = $hit;
ok(count($waveRules) >= 8,
   'the wave colourways are gone from the panel or the board (' . count($waveRules)
 . ' rules found, expected 4 of each)');
foreach ($waveRules as $hit) {
	ok(preg_match('~^hue-rotate\(-?\d+deg\)$~', trim($hit[2])) === 1,
	   'wave ' . $hit[1] . ' is a treatment laid over the art rather than a hue rotation of '
	 . 'it: filter:' . trim($hit[2]));
}
/*
 * THE BROWSER AND THE SERVER AGREE ON WHAT WAVE 3 LOOKS LIKE.
 *
 * The colourway is rendered in four places and two of them are PHP, because
 * the picture has to leave the browser for the X card and the Discord
 * embed. dhcb_wave_hue() is the one source; the CSS literals are checked
 * against it here, so a hand-edited degree in a stylesheet cannot silently
 * post a crew in a colour nobody fought it in.
 */
ok(dhcb_wave_hue(1) === 0,
   'wave 1 is rotated, and it is the baseline every other wave is read against');
ok(dhcb_wave_hue(6) === dhcb_wave_hue(1),
   'the colourway does not wrap, so a sixth wave would have no colour at all');
ok(is_file(__DIR__ . '/lib/hue-rotate.php'),
   'lib/hue-rotate.php is gone, so the rotation is inlined somewhere again');
foreach (array('dhc-arena-card.php', 'dhc-boss-art.php') as $hf2) {
	$fs2 = file_get_contents(__DIR__ . '/' . $hf2);
	ok(strpos($fs2, "require_once __DIR__ . '/lib/hue-rotate.php';") !== false,
	   $hf2 . ' does not use the shared rotation');
	ok(strpos($fs2, '0.213 + $c * 0.787') === false,
	   $hf2 . ' carries its own copy of the hue matrix');
}
$EP = file_get_contents(__DIR__ . '/dhc-boss-art.php');
ok(strpos($EP, "foreach (dhcb_bosses() as \$bz) if (\$bz['key'] === \$key)") !== false,
   'dhc-boss-art.php does not validate the boss key against the roster, and that key '
 . 'becomes a path');
ok(strpos($EP, 'if ($deg === 0) dba_send($src);') !== false,
   'the endpoint recomputes wave 1, which is the unrotated original');
ok(strpos($EP, 'imagesavealpha($im, true);') !== false,
   'the endpoint drops the alpha, so every rotated boss gets a black box behind it');
ok(strpos($EP, '@rename($tmp, $cache)') !== false,
   'the endpoint writes its cache non-atomically');
ok(substr_count($LIB, 'dhc-boss-art.php?boss=') === 2,
   'the Discord embed does not route both of its boss images through the endpoint, so a '
 . 'kill in wave 3 can still post wave 1 colours');

/*
 * FOUR DISTINCT ROTATIONS, AND THE PANEL AND THE BOARD AGREE ON EACH.
 *
 * Two failures in one check. If two waves share a rotation they are the same
 * crew to look at, and the escalation stops reading. If the panel and the
 * board disagree on one wave, the crew is one colour in the list and another
 * in the fight -- which is the thing the board rules were added for.
 */
$byWave = array();
foreach ($waveRules as $hit)
	if (preg_match('~(-?\d+)deg~', $hit[2], $dm)) $byWave[$hit[1]][] = (int)$dm[1];
ok(count($byWave) === 4, 'there are not four waves of colourway: ' . implode(',', array_keys($byWave)));
foreach ($byWave as $w => $list) {
	ok(count(array_unique($list)) === 1,
	   'wave ' . $w . ' is one colour on the panel and another on the board: '
	 . implode(' vs ', array_unique($list)));
}
/* AND EACH MATCHES dhcb_wave_hue(), so the stylesheet cannot drift from
   the renderer that has to reproduce it outside the browser. */
foreach ($byWave as $wv2 => $list2) {
	ok((int)$list2[0] === dhcb_wave_hue((int)$wv2),
	   'the CSS rotates wave ' . $wv2 . ' by ' . $list2[0] . ' degrees and dhcb_wave_hue() '
	 . 'says ' . dhcb_wave_hue((int)$wv2) . ', so the page and the share card disagree');
}
$one = array_map(function ($l) { return $l[0]; }, $byWave);
ok(count(array_unique($one)) === count($one),
   'two waves share a rotation, so they are the same crew to look at: ' . json_encode($one));
ok(preg_match('~data-wave="<\?php echo min\(5, \$wv\); \?>"~', $ARE) === 1,
   'the panel never emits the wave, so the grade has nothing to hang on');
ok(strpos($ARE, 'teamcol.foes[data-wave="2"] img[data-s="boss"]') !== false,
   'the board does not grade the crew, so it is scorched on the panel and ordinary in the fight');
ok(strpos($ARE, 'function bossWave(res)') !== false && substr_count($ARE, 'bossWave(') >= 4,
   'nothing tells the board which wave it is fighting');

/* ---------------------------------------------------------------- *
 * TWO INTERFACES, ONE IMPLEMENTATION
 * ---------------------------------------------------------------- */
echo "\nthe Bosses are their own page without being their own codebase\n";
/*
 * THE TWO FAILURES THESE SIT BETWEEN, because they pull in opposite
 * directions and the design is the point where they cancel:
 *
 *   ONE PAGE FOR BOTH GAMES. Shipped first. The cards are rendered from
 *   dhca_crew(), so a Fighter resting from a ranked battle showed as
 *   unavailable for the Bosses too -- exactly what the separate cooldown
 *   exists to prevent -- and the fix for THAT was two badges per card, which
 *   the owner read as confusing because it is. "This is getting a bit
 *   confusing" was the report; two addresses with one clock each was the call.
 *
 *   TWO PAGES WITH TWO COPIES. The obvious shape, and the one the owner ruled
 *   out twice in writing: "Don't duplicate arena code as much as you can" and
 *   "Precisely, it's an override, not a duplication." The client is ~2,000
 *   lines of board, drag, cascade, timeline and end-card; a copy is correct
 *   the day it is made and wrong from the next engine fix onward.
 */
$BOS = file_get_contents(__DIR__ . '/dhcbosses.php');

/*
 * dhcbosses.php IS A MODE SWITCH. The check is on SIZE and on ABSENCE,
 * because duplication does not announce itself -- it arrives as a page that
 * grew a board of its own. Stripped of comments there is almost nothing in
 * the file, and none of the Arena's furniture.
 */
$bosCode = trim(harness_strip_comments($BOS));
ok(strlen($bosCode) < 400,
   'dhcbosses.php has grown real code (' . strlen($bosCode) . ' bytes stripped); it is '
 . 'supposed to be a mode switch, and the owner ruled out a duplicate twice');
ok(strpos($bosCode, "\$DHC_MODE = 'bosses'") !== false
   && preg_match('~(require|include)(_once)?\s*(__DIR__\s*\.\s*)?[\'"/]*dhcarena\.php~', $bosCode) === 1,
   'dhcbosses.php does not set the mode and include the Arena, so the two have forked');
foreach (array('a-card', 'class="grid"', 'endcard', 'layerImgs') as $own) {
	ok(strpos($BOS, $own) === false,
	   'dhcbosses.php carries its own ' . $own . ', which is the duplication this shape exists to avoid');
}

/*
 * THE PAGE HAS A MODE AND THE MODE REACHES THE CLIENT. Without the first
 * there is one interface again; without the second the handful of sentences
 * the two pages word differently all read as the Arena's.
 */
ok(preg_match('~\$IS_BOSS\s*=\s*\(\$DHC_MODE === \'bosses\'\)~', $ARE) === 1,
   'dhcarena.php no longer derives $IS_BOSS from $DHC_MODE');
ok(preg_match('~var BOSS_MODE = <\?php echo \$IS_BOSS~', $ARE) === 1,
   'the client cannot tell which page it is on');

/*
 * ONE CLOCK PER CARD, AND IT IS THE CLOCK FOR THAT PAGE.
 *
 * This is the whole reason the pages split. The picker is shared, so the
 * separation has to happen in the ROSTER it is given: dhcb_roster() is
 * dhca_crew() with the boss bench laid over it, so `available` on a card
 * means "ready for the game you are looking at" and nothing downstream has
 * to know which game that is.
 */
ok(preg_match('~\$IS_BOSS && \$user_id && \$dhcb_lib\) \$crew = dhcb_roster\(\$conn, \$user_id~', $ARE) === 1,
   'the boss page builds its picker from the Arena roster, so a Fighter resting from a '
 . 'ranked battle looks unavailable for the Bosses');
/* AND NO SECOND SOURCE LEFT ON THE CARD. The two-pip card is gone, and it
   must not come back by halves: a card with one clock and a button reading
   another is the original bug wearing new names. */
foreach (array('data-bok', 'data-bleft', 'data-bfell', '$dhcb_av', 'class="pip') as $gone) {
	ok(strpos($ARE, $gone) === false,
	   'the two-clock card is back (' . $gone . '); each page shows one clock now');
}
ok(preg_match('~class="a-card<\?php echo \$ok\?\'\':\' out\'~', $ARE) === 1,
   'the card no longer dims on this page\'s own availability');
/* NO MODE TOGGLE EITHER -- the first attempt at one picker for two games,
   and the toggle is what made the send button look broken. */
ok(strpos($ARE, 'applyPickMode') === false && strpos($ARE, 'pickMode') === false,
   'the picker mode toggle is back; the mode is the URL now');

/*
 * BOSS MODE DOES NOT RUN THE ARENA'S QUERIES. Six of them, and none has any
 * meaning on the boss page -- no rivals to choose, no monthly season, no
 * ranked allowance. Leaving one outside the guard is a query per page view
 * that pays for nothing, which is the failure that made the first version of
 * this page time out.
 */
$guardA = strpos($ARE, 'if (!$IS_BOSS) {');
$guardB = strpos($ARE, '} /* !$IS_BOSS */');
ok($guardA !== false && $guardB !== false && $guardB > $guardA,
   'the Arena-only setup block is no longer fenced by $IS_BOSS');
$fenced = ($guardA !== false && $guardB > $guardA) ? substr($ARE, $guardA, $guardB - $guardA) : '';
/* CALL SITES, hence the '$conn' -- all six take the handle first, and the
   bare names are all over the page's commentary. A count of the name would
   pass on prose. */
foreach (array('dhca_entry_block($conn', 'dhca_battles_today($conn', 'dhca_opponents($conn',
               'dhca_standouts($conn', 'dhca_paid_today($conn', 'dhca_ladder($conn') as $q) {
	ok(substr_count($fenced, $q) >= 1
	   && substr_count($ARE, $q) === substr_count($fenced, $q),
	   $q . ' runs on the Bosses page too, where its answer is unused');
}
/* And the expensive boss reads are equally fenced the other way: the Arena
   carries a pointer card, not the panel, so it must not pay for the roster,
   the cache or the board. */
foreach (array('dhcb_entry_block(', 'dhcb_runs_today(', 'dhcb_cache_slots(',
               'dhcb_cache_days(', 'dhcb_ladder($conn') as $q) {
	$at = strpos($ARE, $q);
	ok($at !== false && strpos(substr($ARE, max(0, $at - 800), 800), 'if ($IS_BOSS)') !== false,
	   $q . ' runs on the Arena page, which has no panel to show it in');
}

/*
 * EVERY KIND OF BATTLE THE MOVE PATH ROUTES, THE GUARD ABOVE IT KNOWS.
 *
 * THE BUG. sendMove() picks an endpoint with a ternary -- live, practice,
 * bossRun, else a ranked id -- and directly above it sits a guard that
 * refuses to post "a battle that is not live, not practice and has no id".
 * That guard was written when there were two kinds. A boss run is the third,
 * it is identified by run_id and openBattle() is handed battle_id 0 on
 * purpose, so EVERY move on the boss board was caught, answered with the
 * no-move sound and the Arena's wording, and never sent. The board looked
 * alive and did nothing.
 *
 * WHY A STRUCTURAL CHECK AND NOT A SPELLING ONE. The ternary fails loudly
 * when a kind is missing -- the move goes to the wrong endpoint and the
 * server says so. A NEGATED LIST fails silently: it just refuses. So the two
 * are compared against each other, and a fourth kind added to the router
 * without being added to the guard fails here rather than in a battle log.
 */
$mvAt = strpos($ARE, 'function sendMove(a, z){');
ok($mvAt !== false, 'sendMove is gone');
$mv = $mvAt !== false ? substr($ARE, $mvAt, 2200) : '';
/* The flags the router actually switches on, read off the ternary arms. */
$routed = array();
$tern = substr($mv, strpos($mv, 'var body ='), 420);
/* Each arm is `<flag>` on one line and `? {...}` on the next, so the flag is
   whatever sits immediately before a line-leading `?`. The final `:` arm is
   the ranked default and has no flag, which is why battleId is checked by
   the guard pattern itself rather than through this list. */
if (preg_match_all('~([A-Za-z_][A-Za-z0-9_]*)\s*\n\s*\?~', $tern, $rm)) $routed = $rm[1];
ok(count($routed) >= 3,
   'the move router no longer reads as a ternary of battle kinds, so this cannot check it: '
 . implode(',', $routed));
$guard = preg_match('~if \((![^)]*?battleId > 0\)\)) \{~s', $mv, $gm) ? $gm[1] : '';
ok($guard !== '', 'the "not connected" guard is gone or no longer one condition');
foreach ($routed as $flag) {
	ok(strpos($guard, '!' . $flag) !== false,
	   'sendMove routes on ' . $flag . ' but the guard above it does not, so every move of '
	 . 'that kind is refused before it is sent: ' . $guard);
}
/* AND THE REFUSAL NAMES THE GAME YOU ARE IN. "Not connected to the Arena"
   on the Bosses page is how this was reported. */
ok(strpos($mv, 'BOSS_MODE') !== false,
   'the refusal message is the Arena\'s on both pages');

/*
 * THE BOSSES ARE A TAB, NOT A PANEL.
 *
 * The Arena carried a card pointing at the other game, which spends a whole
 * panel in the column where the Crew is picked on a single link. dhc-nav.php
 * is where a player looks for somewhere else to go, and the strip already
 * names every other DHC surface.
 */
$NAV = file_get_contents(__DIR__ . '/dhc-nav.php');
ok(preg_match("~'bosses'\s*=> array\('dhcbosses\.php',\s*'Bosses',\s*'battle'~", $NAV) === 1,
   'the DHC strip has no Bosses tab, so the only way to the page is the platform menu');
/* BESIDE THE ARENA. What a player is choosing between here is who they are
   fighting, so the two sit together rather than at opposite ends. */
$keys = array();
if (preg_match_all("~^\t'([a-z]+)'\s*=>~m", $NAV, $mm)) $keys = $mm[1];
$ia = array_search('arena', $keys, true); $ib = array_search('bosses', $keys, true);
ok($ia !== false && $ib === $ia + 1,
   'the Bosses tab is not next to the Arena: ' . implode(',', $keys));
/* AND THE TAB LIGHTS UP ON THE PAGE IT NAMES. Executed in both modes, because
   a page that hardcodes 'arena' leaves the strip pointing at the wrong tab
   while you are standing on the Bosses. */
$navExpr = '';
if (preg_match('~\$dhcnav_at = (.*?); \$dhcnav_guest~', $ARE, $mm)) $navExpr = $mm[1];
ok($navExpr !== '', 'dhcarena.php no longer sets $dhcnav_at in one statement');
foreach (array(false => 'arena', true => 'bosses') as $isb => $want) {
	$IS_BOSS = (bool)$isb;
	ok(@eval('return ' . $navExpr . ';') === $want,
	   'the ' . $want . ' page does not light its own tab');
}
/* AND THE PANEL IT REPLACED IS GONE. */
ok(strpos($ARE, 'Go to the Bosses') === false,
   'the Arena still carries the pointer panel the tab replaced');

/*
 * THE SEND BUTTON LOOKS LIKE EVERY OTHER BUTTON ON THE PAGE.
 *
 * .btn.go on its own is the loud solid variant; .a-go restyles it as the
 * quiet outlined control the Arena's own entry button is, AND carries the
 * only :disabled rule. Outside .a-go the button was a solid green slab that
 * still looked live with no Crew picked -- the disabled attribute was set
 * and nothing styled it.
 */
$goAt = strpos($ARE, 'id="aBossGo"');
ok($goAt !== false, 'the send button is gone');
$before = $goAt !== false ? substr($ARE, 0, $goAt) : '';
$lastOpen  = strrpos($before, '<div class="a-go">');
$lastClose = strrpos($before, '</div>');
ok($lastOpen !== false && ($lastClose === false || $lastClose < $lastOpen),
   'the send button is outside .a-go, so it renders as a solid slab with no disabled state');
ok(preg_match('~id="aBossGo" type="button" disabled~', $ARE) === 1,
   'the send button is not disabled in the markup, so a click between parse and paint '
 . 'posts an empty Crew');

/*
 * EACH PAGE RESUMES ITS OWN GAME.
 *
 * Both endpoints answer `resume`, and the Arena's was the only one wired. On
 * the Bosses page that would have reopened a ranked battle -- same board,
 * same client, bossRun unset -- and the next move would have been posted to
 * dhcarena-action.php against a run the player was not in.
 */
$bootA = strpos($ARE, '} else if (BOSS_MODE) {');
$bootB = $bootA !== false ? strpos($ARE, "
} else {", $bootA) : false;
ok($bootA !== false && $bootB !== false,
   'the signed-in boot path has no boss arm, so the Bosses page resumes an Arena battle');
$boot = ($bootA !== false && $bootB !== false) ? substr($ARE, $bootA, $bootB - $bootA) : '';
ok(preg_match('~post\(\{do:\'resume\'\}.*?BOSS_URL\)~s', $boot) === 1,
   'the boss resume does not post to the boss endpoint');
ok(strpos($boot, 'bossRun = res.run_id') !== false,
   'a resumed run does not set bossRun, so the next move goes to the Arena');
ok(strpos($boot, 'liveLobbyPoll') === false,
   'the Bosses page polls the live lobby every four seconds for a panel it does not have');

/*
 * EACH PAGE HAS ITS OWN LADDER, which is what the owner asked for in the
 * same breath as the interface: "With its own ladder, etc."
 */
ok(strpos($ARE, 'dhcb_ladder_html($dhcb_lad') !== false,
   'the Bosses page does not render its own board');
ok(preg_match('~<\?php if \(!\$IS_BOSS\): \?>\s*<div class="a-panel">\s*<h2>Ladder</h2>~', $ARE) === 1,
   'the Arena ladder is not fenced off the Bosses page, so the boss board sits under a '
 . 'monthly ranked board that has nothing to do with it');
/* THE RENDERER, EXECUTED -- not read. Same five-column table and the same
   empty-state shape as the Arena's, because it lands in the same panel. */
$ldb = dhcb_ladder_html(array(
	array('user_id'=>7, 'name'=>'Smoke', 'per'=>4210, 'dealt'=>12630, 'runs'=>3, 'fighters'=>9),
	array('user_id'=>9, 'name'=>'Ash',   'per'=>3100, 'dealt'=>18600, 'runs'=>6, 'fighters'=>18),
), 7, false, 'Nobody yet.');
ok(strpos($ldb, '<table class="a-lad">') === 0,
   'the boss board is not the same table as the Arena\'s, so it will not inherit its styling');
ok(substr_count($ldb, '<th>') === 5 && substr_count($ldb, '<tr') === 3,
   'the boss board is not five columns and two rows: ' . substr_count($ldb, '<th>')
 . ' columns, ' . (substr_count($ldb, '<tr') - 1) . ' rows');
ok(strpos($ldb, 'class="me"') !== false,
   'the board does not mark the reader\'s own row');
ok(strpos($ldb, '4,210') !== false && strpos($ldb, '12,630') !== false,
   'the board does not print the figure it is ranked on');
ok(strpos($ldb, '9 Fighters fielded') !== false,
   'the Fighter count is nowhere on the board, and turning up is what the cache rewards');
ok(strpos(dhcb_ladder_html(array(), 7, false, 'Nobody yet.'), 'Nobody yet.') !== false,
   'an empty week renders no empty state');

/* ---------------------------------------------------------------- *
 * THE ADMIN REPORT
 * ---------------------------------------------------------------- */
echo "\nthe owner can see every Crew that went in\n";
$ADM = harness_strip_comments(file_get_contents(__DIR__ . '/admin-dhcbosses.php'));
ok(strpos($ADM, 'admin_require()') !== false, 'the report is not behind the admin gate');
ok(strpos($ADM, 'dhcb_runs_report(') !== false, 'the report does not list the runs');

/*
 * THE COLOURWAY REFERENCE, which is the only place the whole wave series can
 * be seen without waiting for a week strong enough to produce one.
 *
 * THE RENDERS COME FROM THE RENDERER. Flat art under a CSS filter would look
 * right on this page and prove nothing about the pictures Discord and the X
 * card actually get -- which is the entire question the page exists to
 * answer, and the surface where a broken rotation would otherwise be found
 * by a player.
 */
ok(strpos($ADM, 'src="dhc-boss-art.php?boss=') !== false,
   'the reference draws flat art, so it cannot show what Discord and the X card get');
/* A CSS DECLARATION, not the word. The note on the page explains that the
   server matrix is the one the browser's own hue-rotate() applies, and a
   bare search for the term failed on that sentence -- the same prose-instead
   -of-code trap, pointing the other way. */
ok(preg_match('~filter\s*:[^;}]*hue-rotate~i', $ADM) !== 1,
   'the reference applies its own CSS filter instead of showing the real render');
/* RELATIVE, like every link here: the login cookie is host-only and an
   absolute one drops the session for anyone on the other hostname. */
ok(strpos($ADM, 'src="https://') === false && strpos($ADM, "src='https://") === false,
   'an absolute image URL on an admin page, which drops the session across hostnames');
/*
 * FOLDED, AND THAT IS LOAD-BEARING RATHER THAN TIDY. Fifteen 500px renders
 * is several megabytes. A browser fetches none of them while the <details>
 * is shut, because the subtree is display:none -- the same property the
 * Crews feed's "Show all" relies on. Opened by default, this page stops
 * being the fast operational report it is for.
 */
$hueAt = strpos($ADM, '<details class="ab-hues">');
$imgAt = strpos($ADM, 'src="dhc-boss-art.php?boss=');
ok($hueAt !== false && $imgAt !== false && $hueAt < $imgAt,
   'the fifteen renders are not inside the folded block, so every admin visit downloads '
 . 'several megabytes of boss art to show a report that does not use it');
ok(strpos($ADM, '<details class="ab-hues" open') === false,
   'the colourway block is open by default, which loads every render on arrival');
/*
 * AND EVERY FIGURE ON IT IS CALLED, NOT TYPED. A reference page that
 * restates the numbers is a second source of truth, and the second one is
 * the one that goes stale -- silently, because nothing errors.
 */
/* SCOPED TO THE BLOCK. Every one of these functions is also used elsewhere
   on this page -- dhcb_tier_table() for the live summary, dhcb_wave_hue() by
   the derivation above -- so a whole-file search for the NAME passes while
   the figures on display are typed in by hand. Three mutations proved
   exactly that. */
$hueBlk = ($hueAt !== false)
	? substr($ADM, $hueAt, strpos($ADM, '</details>', $hueAt) - $hueAt) : '';
ok($hueBlk !== '', 'the colourway block could not be located');
foreach (array('dhcb_wave_hue(', 'dhcb_wave_pow(', 'dhcb_round_traits(', 'dhcb_tier_table(')
         as $fn)
	ok(strpos($hueBlk, $fn) !== false,
	   'the reference hard-codes what ' . rtrim($fn, '(') . '() decides, so it can drift '
	 . 'from the game with nothing to catch it');
/* AND ON THE STRIP ITSELF. dhcb_wave_pow() also appears in the wrap warning
   at the foot of the block, so its mere presence there says nothing about
   the figure printed beside each wave -- which a mutation typed in by hand
   without tripping anything. */
ok(preg_match('~power <b>&times;<\?php echo number_format\(dhcb_wave_pow\(\$w\)~', $hueBlk) === 1,
   'the power multiplier on the wave strip is typed rather than read from dhcb_wave_pow()');
ok(preg_match('~hue <b><\?php echo \(int\)dhcb_wave_hue\(\$w\)~', $hueBlk) === 1,
   'the hue on the wave strip is typed rather than read from dhcb_wave_hue()');
ok(preg_match('~\$ab_hue_waves\s*=\s*[2-9]\d*\s*;~', $ADM) !== 1,
   'the length of the hue series is a literal, so one edit to dhcb_wave_hue() leaves the '
 . 'page showing the wrong number of colourways and a footnote that is wrong about the wrap');
ok(strpos($ADM, 'dhcb_wave_hue($w) === dhcb_wave_hue(1)') !== false,
   'the series length is not derived by walking dhcb_wave_hue() back to its start');
/* The derivation has to actually produce the cycle, or it is decoration. */
$cyc = 1;
for ($w = 2; $w <= 64; $w++) {
	if (dhcb_wave_hue($w) === dhcb_wave_hue(1)) { $cyc = $w - 1; break; }
	$cyc = $w;
}
ok($cyc === 5, 'the hue series is ' . $cyc . ' long, not 5 -- check the reference page and '
             . 'the Skull Paper still say so');
ok(dhcb_wave_hue($cyc + 1) === dhcb_wave_hue(1),
   'the series does not wrap where the page says it does');
/* AND THE WRAP IS FLAGGED, because the art repeats and the threat does not. */
ok(strpos($ADM, 'dhcb_wave_pow($ab_hue_waves + 1)') !== false,
   'nothing on the page says that the wave after the wrap looks identical to wave 1 and '
 . 'hits twice as hard');
/*
 * EVERY DEFAULT DATE IN THIS FEATURE IS A THURSDAY.
 *
 * dhcb_day() returned CURDATE(), left over from the draft where the crew
 * stood up and fell daily. Every `day` column holds the Thursday the week
 * began, so for six days out of seven that default looked up a date nothing
 * is keyed on: the admin report said "no crew has stood up for 2026-10-25"
 * while the crew was standing perfectly well under 2026-10-22, dhcb_settle()
 * would have settled a week with no participants and reported a clean run
 * paying nobody, and dhcb_runs_report() would have shown an empty week.
 *
 * The function is deleted rather than corrected -- a dhcb_day() that returns
 * a Thursday is the same trap wearing a fix -- so the check is that it is
 * GONE and that nothing reaches for CURDATE() in its place.
 */
ok(strpos($LIB, 'function dhcb_day(') === false,
   'dhcb_day() is back; it returns today and every day column here holds a Thursday');
ok(strpos($ADM, 'dhcb_day(') === false && strpos($ADM, 'CURDATE()') === false,
   'the admin report is back on today\'s date, so it reads an empty week six days in seven');
ok(strpos($ADM, '$ab_today = dhcb_week($conn);') !== false,
   'the admin report does not default to the current week');
foreach (array('dhcb_settle', 'dhcb_runs_report') as $fn) {
	$at = strpos($LIB, 'function ' . $fn . '(');
	ok($at !== false && strpos(substr($LIB, $at, 220), 'dhcb_week($conn)') !== false,
	   $fn . '() does not default to the week, so calling it without a date works on a '
	 . 'date nothing is keyed to');
}
/* And the page has to show WHICH crew, now that they come back inside a
   week -- "40% down" means nothing without the wave beside it. */
ok(strpos($ADM, "\$ab_prog['wave']") !== false,
   'the admin report never says which wave it is describing');
/* The flag it tells the owner to run has to be a flag that exists. */
preg_match_all('~rewards\.php\?([a-z-]+)=1~', $ADM, $fm);
$jobs = file_get_contents(__DIR__ . '/rewards.php');
foreach (array_unique($fm[1]) as $flag) {
	ok(strpos($jobs, "'" . $flag . "'") !== false,
	   'the report tells the owner to run rewards.php?' . $flag . '=1, and no such job exists');
}
/* READ ONLY. A settle button would be a second caller of the one thing that
   must happen exactly once. */
foreach (array('dhcb_settle(', 'dhcb_spawn(', 'INSERT', 'UPDATE ', 'DELETE') as $w) {
	if (!ok(strpos($ADM, $w) === false, "the report page can write ($w), and it must not")) break;
}

/* ---------------------------------------------------------------- *
 * A WHOLE DAY
 * ---------------------------------------------------------------- */
echo "\na day's worth of runs brings the crew down and escalates the table\n";
/*
 * The pieces are tested above; this is the loop they actually run in. Runs
 * are committed one after another against the same three bars, exactly as
 * separate players would, and the table is read as the damage accumulates.
 */
$dc = new HBConn();
$budget = 9000;                       // the floor, which is a small community
$split  = array();
foreach (dhcb_bosses() as $bz) $split[$bz['key']] = (int)round($budget * $bz['hp']);
$dc->health = $split;
$maxes = $split;

$tables = array(); $runsUsed = 0; $totalDealt = 0;
for ($r = 0; $r < 60; $r++) {
	$left = array_sum($dc->health);
	if ($left <= 0) break;
	/* One run's worth of damage, spread over whichever bosses still stand,
	   the way a real board spreads it. */
	$per = (int)floor(DHCB_DMG_PER_RUN / max(1, count(array_filter($dc->health))));
	$snap = array(); $deal = array();
	foreach ($dc->health as $k => $h) { if ($h <= 0) continue; $snap[$k] = $h; $deal[$k] = $per; }
	if (!$snap) break;
	$bb = hb_battle($snap, $deal);
	dhcb_finish($dc, $bb);
	$runsUsed++; $totalDealt += (int)$bb['dealtTotal'];

	$state = array();
	foreach ($dc->health as $k => $h) $state[$k] = array('max_health'=>$maxes[$k], 'health'=>$h, 'dead'=>($h <= 0));
	$pr = dhcb_progress($state);
	$tables[] = dhcb_tier_table($pr);
}
ok(array_sum($dc->health) === 0, 'the crew never fell in 60 runs against a floor-sized budget');
ok($runsUsed > 3 && $runsUsed < 40, "the crew fell in $runsUsed runs, which is not a day's work");
ok($totalDealt === $budget,
   "the damage recorded ($totalDealt) does not equal the health that came off ($budget) -- "
 . 'the ledger and the bars disagree, and the leaderboard ranks on the ledger');
/* The table must climb and never fall back as the day goes on. */
$ranks = array_map('dhcf_table_rank', $tables);
$fellBack = false;
for ($i = 1; $i < count($ranks); $i++) if ($ranks[$i] < $ranks[$i-1]) $fellBack = true;
ok(!$fellBack, 'the table got worse partway through the day');
ok(count($tables) && $tables[0] === 'run', 'the first run of the day already pays above the base table');
$final = end($tables);
ok($final === 'placement_1', "the cleared crew pays $final rather than the best table");
printf("  %d runs took a %s-health crew down; table went %s -> %s\n",
       $runsUsed, number_format($budget), $tables[0], $final);

/* ---------------------------------------------------------------- *
 * THE WEBHOOK THAT DOES NOT EXIST YET
 * ---------------------------------------------------------------- */
echo "\nwith no webhook configured it posts nothing, not to the general channel\n";
$WH = file_get_contents(__DIR__ . '/webhooks.php');
/*
 * Without its own case, 'dhcbosses' falls through to getWebhook() and every
 * boss run lands in the general channel -- at six runs a day per player that
 * is the loudest thing on the server, and it would arrive the moment the
 * feature went live rather than when somebody chose it.
 */
ok(strpos($WH, '$channel == "dhcbosses"') !== false,
   'dhcbosses has no webhook case, so it falls through to the general channel');
ok(strpos($WH, "function_exists('getDHCBossesWebhook')") !== false,
   'the boss webhook is not resolved behind function_exists, so a missing credential is fatal '
 . 'rather than silent');
ok(strpos($WH, 'if($webhook != "")') !== false,
   'an unresolved webhook is no longer guarded, so an empty URL would be curled');

/* ---------------------------------------------------------------- *
 * WEEKLY, AND THE CACHE
 * ---------------------------------------------------------------- */
echo "\nthe crew is weekly and the cache counts days, not runs\n";
/*
 * THE ALLOWANCE AND THE RESET ARE DIFFERENT CLOCKS and must stay that way.
 * dhc_boss_runs.day holds the WEEK now, so counting the allowance against
 * it would let one sitting spend the entire week -- which is exactly what
 * the three-a-day cap exists to prevent, and the thing that makes a full
 * cache mean seven separate visits.
 */
$today = substr($LIB, strpos($LIB, 'function dhcb_runs_today'));
$today = substr($today, 0, strpos($today, "\nfunction "));
/*
 * THE ALLOWANCE IS THE CURRENT 4PM-TO-4PM BLOCK, not the calendar day. A
 * midnight allowance under a Thursday-4pm week resets in the middle of day
 * seven and hands out a free extra slot every single week.
 */
ok(strpos($today, 'skl_week_day_sql') !== false,
   'the allowance counts calendar days under a 4pm week boundary, which gives a free slot '
 . 'every Thursday');
ok(strpos($today, 'CURDATE()') === false, 'the allowance still resets at midnight');

/*
 * THE CACHE GRID, EXECUTED.
 *
 * This asked whether the word DISTINCT appeared in dhcb_cache_slots(). It
 * does not any more -- the read groups by round so it can also return the
 * wave -- and the check failed on code that is correct, which is the other
 * half of what a source grep gets wrong. What matters is the RESULT: three
 * Crews in one round fill ONE box, and the box is worth the highest wave
 * that round was fought against.
 */
$cs = new HBConn();
$cs->wave = 1; $cs->weekDay = 3;
/* ROUND 2'S WAVES DESCEND ON PURPOSE (3 then 2). Ascending, grouping per
   RUN instead of per round still leaves the right number in the last row
   written, so dropping the GROUP BY looked correct. Descending, only a real
   MAX over the round survives. */
$cs->runs = array(array('d'=>0,'wave'=>1), array('d'=>0,'wave'=>1), array('d'=>0,'wave'=>1),
                  array('d'=>1,'wave'=>1), array('d'=>2,'wave'=>3), array('d'=>2,'wave'=>2));
$grid = dhcb_cache_slots($cs, 7, '2026-10-25');
ok(count($grid) === (int)DHCB_CACHE_MAX, 'the grid is not a week wide: ' . count($grid));
$fill = 0; foreach ($grid as $g) if ($g['state'] === 'filled') $fill++;
ok($fill === 3, 'three Crews in one round filled more than one box: ' . $fill . ' filled');
ok($grid[0]['traits'] === 1, 'a wave 1 round is not worth one: ' . $grid[0]['traits']);
ok($grid[2]['traits'] === 3,
   'a round that saw the wave rise banks the wave it STARTED on, not the one it reached: '
 . $grid[2]['traits']);
/* A missed round is worth nothing and a future one is worth what the crew
   standing now would pay -- which is the reason to show it at all. */
ok($grid[3]['state'] === 'today' && $grid[3]['traits'] === 1,
   'the open round does not carry what it would pay right now');
$cs->wave = 4;
$grid2 = dhcb_cache_slots($cs, 7, '2026-10-25');
ok($grid2[6]['traits'] === (int)DHCB_WAVE_TRAITS_MAX,
   'a round still to come is priced off wave 1 rather than the crew actually standing: '
 . $grid2[6]['traits']);
ok($grid2[2]['traits'] === 3,
   'a round already banked was repriced by a later wave: ' . $grid2[2]['traits']);
/* NUMBERED, NOT NAMED. A block runs 4pm to 4pm and straddles two calendar
   dates, so "Friday" is wrong by eight hours for everybody and wrong by more
   outside Central time. Read off the grid rather than off the source. */
$labels = array(); foreach ($grid as $g) $labels[] = $g['label'];
ok($labels === array('1','2','3','4','5','6','7'),
   'the slots are labelled by weekday name, which is wrong by eight hours under a 4pm '
 . 'boundary and wrong by more outside Central time: ' . implode(',', $labels));
/*
 * AND THE GRID QUALIFIES A ROUND THE SAME WAY THE PAYOUT DOES. This one
 * stays a source check because the bar lives in SQL a stub cannot run --
 * but it compares the two clauses to EACH OTHER rather than asserting a
 * string, so the failure it catches is the two drifting apart: a box a
 * player can see filled, against a trait they are never paid.
 */
$slots = substr($LIB, strpos($LIB, 'function dhcb_cache_slots'));
$slots = substr($slots, 0, strpos($slots, "\nfunction "));
$parts = substr($LIB, strpos($LIB, 'function dhcb_participants'));
$parts = substr($parts, 0, strpos($parts, "\nfunction "));
$bar = function ($src) {
	/* The two spell it differently -- one inlines the constants, the other
	   binds them to $min and $mr first -- so the rule is checked as four
	   properties rather than as one string: both constants present, damage
	   tested, rounds tested, and the two joined by OR and not AND. */
	return strpos($src, 'DHCB_MIN_DEALT') !== false
	    && strpos($src, 'DHCB_MIN_ROUNDS') !== false
	    && preg_match('~dealt\\s*>=~', $src) === 1
	    && preg_match('~OR rounds\\s*>=~', $src) === 1;
};
ok($bar($slots), 'a run that dealt nothing fills a cache slot');
ok($bar($parts),
   'the settle no longer uses the same qualifying bar as the grid, so a box can fill '
 . 'without paying a trait');

/*
 * WHAT THE LINE UNDER THE GRID SAYS A ROUND IS WORTH -- LIFTED AND RUN.
 *
 * It is four lines of accumulator inside dhcarena.php, and it was wrong in
 * the one state nobody tests by hand: the round you have ALREADY played.
 * The loop only ever read the 'today' slot, and a round you have sent a
 * Crew at is 'filled', so $nowWorth kept its initialiser and the page said
 * "this round is worth 1" under a grid of x3 boxes. Reported from a phone
 * at wave 6.
 *
 * So the loop is cut out of the page and EXECUTED against the three states
 * it has to answer in. A grep for 'nowWorth' would have passed on the bug.
 */
$sumSrc = substr($ARE, strpos($ARE, '$filled = 0; $banked = 0;'));
$sumSrc = substr($sumSrc, 0, strpos($sumSrc, '} ?>') + 1);
ok(strpos($sumSrc, "=== 'today'") !== false && strlen($sumSrc) < 600,
   'the cache summary loop could not be lifted out of dhcarena.php: ' . strlen($sumSrc) . ' bytes');
$summarise = function ($slots) use ($sumSrc) {
	$dhcb_slots = $slots;
	$filled = 0; $banked = 0; $nowWorth = 0; $nowOpen = false;
	eval($sumSrc);
	return array('filled'=>$filled, 'banked'=>$banked,
	             'worth'=>$nowWorth, 'open'=>$nowOpen);
};
$slot = function ($state, $traits) { return array('state'=>$state, 'traits'=>$traits); };
/* The reported screen: rounds 1-2 missed, 3-6 played at waves 1, 2, 3 and 6,
   round 6 is now and is already filled, round 7 still to come at wave 6. */
$played = $summarise(array(
	$slot('missed',0), $slot('missed',0), $slot('filled',1), $slot('filled',2),
	$slot('filled',3), $slot('filled',3), $slot('open',3)));
ok($played['banked'] === 9, 'the grid and the banked count disagree: ' . $played['banked']);
ok($played['filled'] === 4, 'the rounds count is not the filled boxes: ' . $played['filled']);
ok($played['worth'] === 3,
   'a round already sent leaves the page saying the next one is worth '
 . $played['worth'] . ' when the standing wave pays 3');
ok($played['open'] === false,
   'the page offers "this round is worth" for a round that is already banked');
/* Same week, one round earlier: this round is still open. */
$openNow = $summarise(array(
	$slot('missed',0), $slot('missed',0), $slot('filled',1), $slot('filled',2),
	$slot('filled',3), $slot('today',3),  $slot('open',3)));
ok($openNow['open'] === true && $openNow['worth'] === 3,
   'an open round does not read as open and worth 3: ' . json_encode($openNow));
/* Nothing left to earn, and the sentence must not invent one. */
$full = $summarise(array_fill(0, 7, $slot('filled', 3)));
ok($full['worth'] === 0 && $full['open'] === false && $full['filled'] === 7,
   'a full week still advertises a round to come: ' . json_encode($full));
/* A worth of zero must never reach the page as a number. */
ok(preg_match('~elseif\s*\(\$nowWorth > 0\):~', $ARE) === 1
   && preg_match('~if\s*\(\$nowOpen\):~', $ARE) === 1,
   'the sentence no longer branches on whether this round is still open, so a banked '
 . 'round is priced as if it were not');
$days = substr($LIB, strpos($LIB, 'function dhcb_cache_days'));
$days = substr($days, 0, strpos($days, "\nfunction "));
ok(strpos($days, 'DHCB_CACHE_MAX') !== false, 'the cache is uncapped');

/*
 * THE WEEK TURNS AT 4PM THURSDAY, like every other weekly game here, and
 * the boundary is computed in MySQL because started_at is written by
 * MySQL's NOW() and the two clocks on this host disagree.
 */
require_once __DIR__ . '/lib/week-start.php';
/*
 * DRIVEN, NOT GREPPED. The first version of these searched the SQL for
 * '16:00:00' and for 'WEEKDAY(NOW()) = 3' and passed while one of the
 * four branches had been changed, because the other three still matched.
 * The PHP twin can simply be run, which is the honest test; the SQL is
 * then checked structurally, since every branch has to carry the hour.
 */
$wk = function ($iso) {
	return skl_week_key(new DateTime($iso, new DateTimeZone('America/Chicago')));
};
$wd = function ($iso) {
	$n = new DateTime($iso, new DateTimeZone('America/Chicago'));
	$u = (clone $n)->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
	return skl_week_day_index($u, $n);
};
/* 2026-10-29 is a Thursday. 15:59 belongs to the week that began the
   Thursday before; 16:01 opens the new one. */
ok($wk('2026-10-29 15:59:00') === '2026-10-22', 'Thursday before 4pm is not still last week');
ok($wk('2026-10-29 16:01:00') === '2026-10-29', 'Thursday after 4pm does not start the new week');
ok($wk('2026-10-30 09:00:00') === '2026-10-29', 'Friday belongs to the wrong week');
ok($wk('2026-11-02 09:00:00') === '2026-10-29', 'Monday belongs to the wrong week');
ok($wk('2026-11-04 23:00:00') === '2026-10-29', 'Wednesday night belongs to the wrong week');
/* Seven blocks, 0 to 6, and the last one must not roll over early. */
ok($wd('2026-10-29 16:01:00') === 0, 'the first hour of the week is not day 0');
ok($wd('2026-11-04 23:00:00') === 6, 'the last night of the week is not day 6');
ok($wd('2026-11-05 15:59:00') === 6, 'the final afternoon rolled past day 6');
/* The week spans eight calendar dates; the index must still stop at 6. */
$seen = array();
for ($h = 0; $h < 168; $h++) {
	$t = (new DateTime('2026-10-29 16:30:00', new DateTimeZone('America/Chicago')))
	     ->modify("+$h hour");
	$seen[$wd((clone $t)->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'), $t)] = true;
}
ok(count($seen) === 7, 'a week produced ' . count($seen) . ' distinct day indices, not 7');

/* Every branch of the SQL has to carry the hour and the weekday. */
/* FIVE: four branches that build the timestamp, plus the comparison that
   decides whether today's Thursday has turned yet. */
ok(substr_count(skl_week_start_sql(), "'16:00:00'") === 5,
   'the SQL no longer carries 4pm in every branch, found '
 . substr_count(skl_week_start_sql(), "'16:00:00'") . ' of 5');
ok(substr_count(skl_week_start_sql(), 'WEEKDAY(NOW()) = 3') === 2,
   'a Thursday branch in the SQL has moved to another weekday');
ok(strpos(skl_week_start_sql(), 'DAYOFWEEK') === false,
   'DAYOFWEEK is locale dependent and would move the boundary on some servers');
/* Seven blocks, never eight -- the week spans eight calendar dates. */
ok(strpos(skl_week_day_sql(), 'LEAST(6') !== false,
   'the day index is unbounded, so a week that touches eight dates offers eight slots');
$LIBW = harness_strip_comments(file_get_contents(__DIR__ . '/dhcbosses-lib.php'));
ok(strpos($LIBW, 'skl_week_key_sql()') !== false,
   'the week key is computed in PHP, against timestamps MySQL wrote');

/* The settle pays one trait per day attended, not one per player. */
$set = substr($LIB, strpos($LIB, 'function dhcb_settle'));
$set = substr($set, 0, strpos($set, "\nfunction "));
ok(strpos($set, 'for ($i = 0; $i < $slots; $i++)') !== false,
   'the settle pays a single trait however many days the player turned up');
ok(strpos($set, "min((int)DHCB_CACHE_MAX") !== false, 'the payout is not capped at the cache max');

/* Three a day is what makes the cache mean something. */
ok((int)DHCB_DAILY_RUNS <= 3,
   'the daily allowance is ' . DHCB_DAILY_RUNS . '; above three a player can bank most of the '
 . 'week in one sitting and the cache stops rewarding coming back');
/* And with the Arena's six, the day has to stay reasonable. */
ok((int)DHCB_DAILY_RUNS + 6 <= 10,
   'the Arena and the Bosses together now ask for more than ten battles of the same board a day');

/* The board follows the content. */
$rw = file_get_contents(__DIR__ . '/rewards.php');
ok(preg_match("~'dhcbosses'\s*=> array\('weekly'~", $rw) === 1,
   'the ladder is not on the weekly cron, so a weekly crew is ranked on a monthly board');
ok(preg_match("~'dhcbosses-week'\s*=> array\('weekly'~", $rw) === 1,
   'the settle is not on the weekly cron');
ok(strpos($rw, "'daily'") === false,
   'a daily cadence is still registered but nothing uses it');

/* The visual cache has to show which days, not a number. */
ok(strpos($ARE, 'a-slot') !== false && strpos($ARE, 'dhcb_cache_slots(') !== false,
   'the cache is not drawn, so the one thing that rewards coming back is invisible');
ok(strpos($ARE, 'prefers-reduced-motion') !== false,
   'the pulsing slot has no reduced-motion escape');
/*
 * THE ARENA RESTYLED ITS OWN CONTROLS. Every button on that page is .btn,
 * and .button is the GLOBAL flexbox.css style -- the old drop-shadowed one
 * -- so a boss control written against the platform default lands in the
 * middle of a panel where nothing else has a shadow. Easy to do and
 * invisible until somebody looks at the page.
 */
preg_match_all('~<button[^>]*class="([^"]*)"[^>]*id="(a[A-Za-z-]+)"~', $ARE, $bm, PREG_SET_ORDER);
$offStyle = array();
foreach ($bm as $bx) if (strpos($bx[1], 'btn') === false) $offStyle[] = $bx[2];
ok(!$offStyle, 'an Arena button uses the global .button style instead of .btn: '
             . implode(', ', $offStyle));
ok(count($bm) >= 8, 'the button sweep found only ' . count($bm) . ' buttons, so it is not sweeping');

/* ---------------------------------------------------------------- *
 * THE SETTLE MEASURES THE WEEK IT IS SETTLING, NOT THE CLOCK
 * ---------------------------------------------------------------- */
echo "\nthe settle groups rounds against the week it is paying for\n";
/*
 * THE BUG THIS PINS COST EVERY PLAYER THEIR WEEK.
 *
 * dhcb_participants() grouped a player's rounds with skl_week_day_sql(),
 * which measures from "the week NOW() is in". The weekly cron fires AFTER
 * the 4pm Thursday turn, so NOW() was already inside the new week while
 * the rows being settled belonged to the old one: every TIMESTAMPDIFF came
 * out negative, GREATEST(0, ...) clamped all of them to block 0, and a
 * player's five separate rounds collapsed into a single group.
 *
 * That paid LEAST(DHCB_WAVE_TRAITS_MAX, MAX(wave)) ONCE instead of per
 * round -- three traits to everybody who took part, whether they turned up
 * once or seven times, on the first ever DHC Bosses payout. The owner
 * noticed because twelve banked traits arrived as three.
 */
$parts = substr($LIB, strpos($LIB, 'function dhcb_participants'));
$parts = substr($parts, 0, strpos($parts, "\nfunction "));
ok(strpos($parts, 'skl_week_day_sql_for(') !== false,
   'dhcb_participants() does not anchor its round grouping on the week being settled');
ok(!preg_match('~skl_week_day_sql\(~', $parts),
   'dhcb_participants() still uses the clock-anchored day index somewhere, which pays three '
 . 'traits to everyone the moment the cron runs after the turn');
/* Same fault, same fix, in everything else scoped to a passed-in week. */
foreach (array('dhcb_cache_days', 'dhcb_cache_slots') as $fn) {
	$src = substr($LIB, strpos($LIB, 'function ' . $fn));
	$src = substr($src, 0, strpos($src, "\nfunction "));
	ok(!preg_match('~skl_week_day_sql\(~', $src),
	   "$fn() measures a given week against the clock, so it is wrong for any week but the "
	 . 'current one');
}
/* And the Siege map, which spans thirteen weeks at once and cannot use a
   single key for all of them. */
$sg = substr($LIB, strpos($LIB, 'function dhcb_siege('));
$sg = substr($sg, 0, strpos($sg, "\nfunction dhcb_siege_art"));
ok(strpos($sg, 'skl_week_day_sql_col(') !== false,
   'the Siege map measures a multi-week range from one Thursday, which puts twelve weeks of '
 . 'rows outside the 0-6 window');

/* The generated SQL itself, which is the thing that was wrong. */
require_once __DIR__ . '/lib/week-start.php';
$forWeek = skl_week_day_sql_for('2026-10-22', 'started_at');
ok(strpos($forWeek, 'NOW()') === false,
   'the week-anchored day index still reads the clock: ' . $forWeek);
ok(strpos($forWeek, "'2026-10-22'") !== false,
   'the week-anchored day index does not mention the week it was given');
ok(strpos(skl_week_day_sql('started_at'), 'NOW()') !== false,
   'the clock-anchored version stopped reading the clock, which is what it is FOR');
/* A key that is not a Thursday date is a bug upstream and must be refused
   rather than quietly falling back to the clock -- that fallback would
   reintroduce the three-trait week invisibly. */
$threw = false;
try { skl_week_day_sql_for('not-a-date', 'started_at'); } catch (Throwable $e) { $threw = true; }
ok($threw, 'a malformed week key is accepted, so a caller can silently get the clock back');

echo "\nand a week settled wrongly can be made whole\n";
/*
 * REPAY PAYS THE DIFFERENCE. Deleting the payout rows and re-running would
 * pay the full amount again to anyone whose first payout happened to be
 * right, and would lose what was originally paid.
 */
$settleSrc = substr($LIB, strpos($LIB, 'function dhcb_settle'));
$settleSrc = substr($settleSrc, 0, strpos($settleSrc, "\nfunction "));
ok(strpos($settleSrc, '$repay') !== false, 'dhcb_settle() has no repay mode');
ok(preg_match('~\$owed\s*=\s*\$slots\s*-\s*\$prev~', $settleSrc) === 1,
   'repay does not pay the DIFFERENCE, so it either overpays or pays nothing');
ok(preg_match('~if \(\$owed <= 0\) continue~', $settleSrc) === 1,
   'repay pays a player who is already whole, so running it twice pays twice');
ok(strpos($settleSrc, 'UPDATE dhc_boss_payouts SET slots') !== false,
   'repay does not record what it paid, so the next run pays it all over again');
/* FAIL CLOSED: no row written, no trait handed out. */
$upd = strpos($settleSrc, 'UPDATE dhc_boss_payouts SET slots');
$guard = strpos($settleSrc, 'if (!$ok) continue;', $upd);
$nextAward = strpos($settleSrc, 'dhcf_award(', $upd);
ok($guard !== false && $nextAward !== false && $guard < $nextAward,
   'repay awards traits without checking the row was updated, so a failed write pays out '
 . 'again on every run');
/* A participant with no payout row at all falls through to the normal
   insert rather than being skipped -- repay is "pay what is outstanding". */
/* The repay branch is entered only when a row EXISTS; a player with none
   falls out of it to the ordinary insert below, which is how "pay what is
   outstanding" covers somebody the first settle missed entirely. */
ok(preg_match('~if \(\$prev !== null\) \{~', $settleSrc) === 1,
   'repay does not gate on whether a payout row exists, so a player who was never paid is '
 . 'either skipped or paid through the wrong branch');
$branch = strpos($settleSrc, 'if ($prev !== null) {');
$insert = strpos($settleSrc, 'INSERT INTO dhc_boss_payouts');
ok($branch !== false && $insert !== false && $branch < $insert,
   'the repay branch does not sit before the insert, so a never-paid player cannot reach it');

$repayTool = @file_get_contents(__DIR__ . '/dhcbosses-repay.php');
ok(is_string($repayTool) && strpos($repayTool, "in_array('--run'") !== false,
   'the repay tool has no dry run, so inspecting what it would pay means paying it');
ok(is_string($repayTool) && strpos($repayTool, 'dhcb_settle($conn, $week, false, true)') !== false,
   'the repay tool does not call the settle in repay mode');


echo "\n" . ($fail ? "$fail check(s) FAILED\n" : "all DHC Bosses checks passed\n");
exit($fail ? 1 : 0);
