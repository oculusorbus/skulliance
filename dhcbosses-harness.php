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

require_once __DIR__ . '/harness-strip.php';
require_once __DIR__ . '/dhcbosses-lib.php';
$LIB = harness_strip_comments(file_get_contents(__DIR__ . '/dhcbosses-lib.php'));
/* The Arena page, which is also the Bosses page -- read once, here, because
   both the wave checks and the interface checks below work on it. */
$ARE = file_get_contents(__DIR__ . '/dhcarena.php');

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
	public $health = array();     // boss_key => health
	public $runOutcome = 0;
	public $log = array();
	public $insert_id = 1;
	public function real_escape_string($s) { return addslashes((string)$s); }
	public function begin_transaction() { $this->log[] = 'BEGIN'; return true; }
	public function commit()   { $this->log[] = 'COMMIT'; return true; }
	public function rollback() { $this->log[] = 'ROLLBACK'; return true; }
	public function query($sql) {
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
		if (stripos($q, 'UPDATE dhc_boss_day SET health =') === 0) {
			preg_match('~SET health = (-?\d+)~', $q, $hm);
			preg_match("~boss_key = '([^']+)'~", $q, $km);
			if ($hm && $km) $this->health[$km[1]] = (int)$hm[1];
			return true;
		}
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
 * THE DAILY SETTLE
 * ---------------------------------------------------------------- */
echo "\nthe day can be closed late, and closed twice, without paying twice\n";
ok(strpos($LIB, 'INSERT INTO dhc_boss_payouts') !== false,
   'the settle writes no payout row, so nothing stops it paying again');
$settle = substr($LIB, strpos($LIB, 'function dhcb_settle'));
$settle = substr($settle, 0, strpos($settle, "\nfunction "));
$ins = strpos($settle, 'INSERT INTO dhc_boss_payouts');
$awd = strpos($settle, 'dhcf_award(');
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
ok(strpos($lad, 'ORDER BY (runs >= $min) DESC') !== false,
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
ok(strpos($sh, '42% down') !== false, 'the share does not say which way the number runs: ' . $sh);
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
ok(strpos($LIB, 'HAVING fighters > 0') !== false
   && strpos($LIB, 'HAVING runs >= $min') === false,
   'the ladder still hides anybody short of the minimum, so an early week looks empty');
ok(strpos($LIB, "'ranked'=>((int)\$x['runs'] >= \$min)") !== false
   && strpos($LIB, "'needs'=>max(0, \$min - (int)\$x['runs'])") !== false,
   'the rows do not say who is ranked or how many Crews short they are');
ok(strpos($LIB, 'ORDER BY (runs >= $min) DESC') !== false,
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
ok(strpos($ARE, 'dhcb_runs_report($conn, null, 12)') !== false,
   'the feed does not come from dhcb_runs_report(), or is not capped -- the admin report '
 . 'pulls 200 and every row here draws three Fighters');
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
/* NOT A FOURTH FIGHTER. It sits apart and does not open a Fighter panel. */
ok(strpos($ARE, '.arena-wrap .a-rf.a-rkill{margin-left:8px') !== false,
   'the dropped boss sits flush against the Crew and reads as a fourth Fighter');
ok(strpos($ARE, 'a-rkill{margin-left:8px;border-color:rgba(255,92,60,.5);cursor:default}') !== false,
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
ok($sh === 'My DHC Fighter Crew inflicted 4,800 damage. The DHC Boss Crew is now 62% down '
         . 'but still standing.',
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
foreach (array(0.0, 0.42, 1.0) as $pc) {
	$t = dhcb_share_text(100, array(), array('pct'=>$pc, 'cleared'=>($pc >= 1.0)));
	ok(strpos($t, 'is at ') === false && strpos($t, 'are at ') === false,
	   'the post says the crew is "at" a percentage, which reads as health remaining and '
	 . 'means the opposite of what it counts: ' . $t);
}

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
$fin = substr($LIB, strpos($LIB, 'function dhcb_finish('));
$fin = substr($fin, 0, strpos($fin, 'function dhcb_bench('));
ok(strpos($fin, 'AND wave = $wave') !== false,
   'the FOR UPDATE read is not scoped to the wave, so a late-finishing run reads the wrong bar');
ok(strpos($fin, "AND wave = %d AND boss_key = '%s'") !== false,
   'the damage UPDATE is not scoped to the wave, so it lands on a crew nobody fought');
ok(strpos($fin, 'dhcb_spawn_memo(null, true)') !== false,
   'a kill does not clear the spawn memo, so the reply shows the crew that was just '
 . 'dropped and the end card never says the player finished them');

/* THE STATE IS THE STANDING WAVE, not every wave summed. */
ok(strpos($LIB, 'wave = (SELECT MAX(wave) FROM dhc_boss_day') !== false,
   'dhcb_state() reads every wave of the week, so the bar shows a health nobody is fighting');

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

$slots = substr($LIB, strpos($LIB, 'function dhcb_cache_slots'));
$slots = substr($slots, 0, strpos($slots, "\nfunction "));
ok(strpos($slots, 'DISTINCT ') !== false && strpos($slots, 'skl_week_day_sql') !== false,
   'the cache counts runs rather than blocks of the week, so three Crews in one sitting '
 . 'would fill three slots');
ok(strpos($slots, "'label' => (string)(\$i + 1)") !== false,
   'the slots are labelled by weekday name, which is wrong by eight hours under a 4pm '
 . 'boundary and wrong by more outside Central time');
ok(strpos($slots, 'dealt >= ') !== false,
   'a run that dealt nothing fills a cache slot');

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

echo "\n" . ($fail ? "$fail check(s) FAILED\n" : "all DHC Bosses checks passed\n");
exit($fail ? 1 : 0);
