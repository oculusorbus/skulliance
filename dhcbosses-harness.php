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
	public function real_escape_string($x) { return addslashes((string)$x); }
	public function query($sql) {
		if (stripos($sql, 'COUNT(*) AS fighters') !== false)
			return new HBRes2(array(array('fighters' => $this->fighters, 'players' => $this->players)));
		return new HBRes2();
	}
}
class HBRes2 {
	public $num_rows = 0; private $r; private $i = 0;
	public function __construct($r = array()) { $this->r = $r; $this->num_rows = count($r); }
	public function fetch_assoc() { return isset($this->r[$this->i]) ? $this->r[$this->i++] : null; }
}
function hb_budget($players, $fighters) {
	$c = new HBCount();
	$c->players = $players; $c->fighters = $fighters;
	return dhcb_crew_budget($c);
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
	$maxDmg = $perDay * 7 * DHCB_DMG_PER_RUN;
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
$ARE = file_get_contents(__DIR__ . '/dhcarena.php');
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
	ok($at !== false && strpos(substr($ARE, max(0, $at - 400), 400), 'if ($IS_BOSS)') !== false,
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
