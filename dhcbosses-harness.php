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
function hb_budget($players, $fighters) {
	$runs = min($players * DHCB_DAILY_RUNS, (int)floor($fighters / DHCB_CREW_SIZE));
	$hp = (int)round($runs * DHCB_DMG_PER_RUN * DHCB_TARGET_PCT);
	return max(DHCB_HP_MIN, min(DHCB_HP_MAX, $hp));
}
$deep    = hb_budget(2, 400);     // two players, enormous collection
$crowd   = hb_budget(20, 60);     // twenty players, one Crew each
ok($crowd > $deep, 'twenty players field a smaller boss than two hoarders, so the scaling is '
                 . 'measuring collections rather than participation');
ok(hb_budget(1, 3) === (int)DHCB_HP_MIN, 'a single player does not get the floor');
ok(hb_budget(10000, 999999) === (int)DHCB_HP_MAX, 'the ceiling does not hold');
/* Monotonic in players, which a sane scaling must be. */
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
ok(strpos($lad, 'SUM(r.dealt) / SUM(r.crew_size)') !== false,
   'the board orders on raw damage, which is mostly a measure of roster size -- and the '
 . 'harmonic payout weights rank 1 at twice rank 2, so it would compound every month');
ok(strpos($lad, 'crew_size') !== false && strpos($lad, 'COUNT(*) AS runs') !== false,
   'the volume columns are gone; they are displayed but must not rank');
ok(strpos($lad, 'HAVING runs >=') !== false,
   'a single lucky run can top the board');

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

echo "\n" . ($fail ? "$fail check(s) FAILED\n" : "all DHC Bosses checks passed\n");
exit($fail ? 1 : 0);
