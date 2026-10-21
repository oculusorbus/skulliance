<?php
/*
 * dropship-run-harness.php — CLI only. The run loop itself, with no database.
 *
 * WHY THIS EXISTS. "I keep using an extra life on round 3 over and over" could
 * not be answered by reading dropship.php, and reading it produced a wrong
 * answer twice. The loop is 60 lines of nested ifs with eight session flags and
 * a counter that four different branches advance, and the only honest way to
 * say what it does is to run it.
 *
 * It is not in a function, so it is LIFTED OUT OF THE PAGE by brace matching
 * and driven directly -- the same trick dhcgallery-perf-harness.php uses on the
 * Collection's filter blocks. Lifting the real text means the harness cannot
 * drift from the page: rename a variable in dropship.php and this stops
 * running, which is the point.
 *
 * THE BUG IT PINS. extralife() tested
 *
 *     if ($extralife == 'true' || $_SESSION['userData']['medkit'] == "true")
 *
 * and then cleared BOTH. A Medkit soldier plus a bought Medkit was two lives
 * on the screen -- dropshipOddsFlags() counts one per Medkit and the shop
 * prices the item on "+1 life" -- and one life in the run, spent on the first
 * failed roll. Round 3 is that first roll, so that is where it went.
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__ . '/dropship/dropship-odds.php';

$fail = 0;
function ok($cond, $what) {
	global $fail;
	if ($cond) { echo "  ok   $what\n"; return true; }
	$fail++;  echo "  FAIL $what\n";  return false;
}

/* ---------- lift the loop and extralife() out of the page ------------------- */

$page = file_get_contents(__DIR__ . '/dropship/dropship.php');
function dsr_block($src, $needle) {
	$i = strpos($src, $needle);
	if ($i === false) return null;
	$b = strpos($src, '{', $i);
	$d = 0; $n = strlen($src);
	for ($j = $b; $j < $n; $j++) {
		if ($src[$j] === '{') $d++;
		elseif ($src[$j] === '}') { $d--; if ($d === 0) return substr($src, $i, $j - $i + 1); }
	}
	return null;
}
$loopSrc = dsr_block($page, "while(\$dead == 'false') {");
/* The trace is appended just AFTER the loop, so it is lifted on its own and
   run in the same place the page runs it. Lifting the statement rather than
   restating it means a change to the format fails here. */
$trIdx  = strpos($page, '$dropshipMarkup .= "\n<!-- run: ');
$trSrc  = $trIdx === false ? null : substr($page, $trIdx, strpos($page, ";\n", $trIdx) - $trIdx + 1);
$fnSrc   = dsr_block($page,
	'function extralife($dropshipMarkup, $counter, $lives, $dead, $melee, $demolition, $dropbox)');

echo "lifting the run out of dropship.php\n";
ok($loopSrc !== null && strlen($loopSrc) > 1000, 'the run loop was found and is not a stub');
ok($trSrc !== null && strpos($trSrc, '$ds_rolls') !== false, 'the roll trace statement was found');
ok($fnSrc   !== null && strlen($fnSrc)   > 1000, 'extralife() was found with the lives signature');
if ($loopSrc === null || $fnSrc === null) { echo "\ncannot continue\n"; exit(1); }

$dir = sys_get_temp_dir() . '/dsrun' . getmypid();
@mkdir($dir);
file_put_contents("$dir/loop.php", "<?php\n" . $loopSrc);
file_put_contents("$dir/fn.php",   "<?php\n" . $fnSrc);
file_put_contents("$dir/trace.php", "<?php\n" . ($trSrc === null ? '' : $trSrc));
define('DSR_DIR', $dir);
register_shutdown_function(function () {
	foreach (array('loop.php', 'fn.php', 'trace.php') as $f) @unlink(DSR_DIR . '/' . $f);
	@rmdir(DSR_DIR);
});

/* ---------- the page's collaborators, reduced to what they return ----------- */

$prefix = ''; $conn = null;
$MeleeDelay = 1; $DemoDelay = 2;
enum Weapon: int { case None=0; case TacticalKatana=1; case SniperRifle=2; case Grenade=3;
                   case SmokeBomb=4; case MachineGun=5; case FlameThrower=6; case RocketLauncher=7; }
$enuWeapon = Weapon::None;

/* evaluateText() is the wording table. Here it TAGS what passed through it, so
   a caption that skipped it is visible in the markup instead of merely
   plausible -- the Extra Life round was the one that did. */
function evaluateText($t){ return $t === 'gif' ? 'gif' : '{' . $t . '}'; }
function live($m,$c){ return $m."<div class='round' id='$c'><h3>Round $c - Survived</h3></div>"; }
function kill($m,$c){ $_SESSION['userData']['score']=strval($c);
                      return $m."<div class='round' id='$c'><h3>Round $c - DEAD</h3></div>"; }
function melee($m,$c){ return $m."<div class='round' id='$c'><h3>Round $c - Melee</h3></div>"; }
function demolition($m,$c){ return $m."<div class='round' id='$c'><h3>Round $c - Demolition</h3></div>"; }
function weapons($m,$c,$w){ return $m."<div class='round' id='$c'><h3>Round $c - Weapon</h3></div>"; }
function weapondelay($c,$w){ return $c + 1; }
function setWeapons($w){ return $w; }
function radar($m,$c){ return $m."<div class='round' id='$c'><h3>Round $c - Radar</h3></div>"; }
function nightvisiongoggles($m,$c){ return $m."<div class='round' id='$c'><h3>Round $c - NVG</h3></div>"; }
function jetpack($m,$c){ return $m."<div class='round' id='$c'><h3>Round $c - Jet Pack</h3></div>"; }
function radio($m,$c){ return $m."<div class='round' id='$c'><h3>Round $c - Radio</h3></div>"; }
function getDropBoxItems($conn){ return array(1=>'sniper_rifle',2=>'grenade'); }
function dropbox($m,$c,$items,$melee,$demolition,$conn){
	$_SESSION['userData']['melee']=$melee; $_SESSION['userData']['demolition']=$demolition;
	$_SESSION['userData'][$items[rand(1,count($items))]] = "true";
	return $m."<div class='round' id='$c'><h3>Round $c - Drop Box</h3></div>";
}
require_once DSR_DIR . '/fn.php';

$DSR_BASE = array('score'=>null,'radar'=>'false','night_vision_goggles'=>'false',
	'drop_box'=>'false','radio'=>'false','jet_pack'=>'false','medkit'=>'false',
	'melee'=>'false','demolition'=>'false');

/**
 * One run of the REAL loop. $lives is what the player is carrying.
 * Returns the markup, the score, and the rolls the run actually made.
 */
function dsr_run($threshold, $lives, $gearMelee = false, $gearDemo = false, $sess = array()) {
	global $enuWeapon, $prefix, $DSR_BASE;
	$_SESSION['userData'] = array_merge($DSR_BASE, $sess);
	$dropshipMarkup = ''; $counter = 0; $dead = 'false';
	$ds_lives = (int)$lives;
	$ds_of_lives_start = $ds_lives;
	$ds_rolls = array();
	$melee = $gearMelee ? 'true' : 'false';
	$demolition = $gearDemo ? 'true' : 'false';
	$dropbox = false;
	require DSR_DIR . '/loop.php';
	require DSR_DIR . '/trace.php';
	return array('markup'=>$dropshipMarkup, 'score'=>(int)($_SESSION['userData']['score'] ?? 0),
	             'rolls'=>$ds_rolls, 'lives_left'=>$ds_lives);
}

/* ---------- 1. one life per failed roll, and every life is spent ------------ */

echo "\nthe lives you carry are the lives you get\n";
foreach (array(1, 2, 3) as $carry) {
	$seen = array();
	for ($i = 0; $i < 3000; $i++) {
		$r = dsr_run(80, $carry);
		$seen[substr_count($r['markup'], '- {Extra Life}')] = true;
	}
	ok(array_keys($seen) === array($carry),
	   "carrying $carry Medkits spends exactly $carry, never " . implode('/', array_keys($seen)));
}
/* And nothing is handed out for free. */
$free = false;
for ($i = 0; $i < 3000; $i++) {
	if (strpos(dsr_run(80, 0)['markup'], 'Extra Life') !== false) { $free = true; break; }
}
ok(!$free, 'carrying none gets none');

/* ---------- 2. round 3 is the first roll ------------------------------------ */

echo "\nthe first roll is round 3, which is why it is where the Medkit goes\n";
$early = 0; $rollCounts = array();
for ($i = 0; $i < 3000; $i++) {
	$r = dsr_run(80, 1);
	/* Rounds 0, 1 and 2 are the fixed intro slides. Nothing may be decided in
	   them: no death, no Extra Life, and no roll spent on them. */
	foreach (array(0, 1, 2) as $n) {
		if (preg_match("/id='$n'><h3>[^<]*(Extra Life|DEAD)/", $r['markup'])) $early++;
	}
	$rollCounts[] = count($r['rolls']);
}
ok($early === 0, 'no run decides anything in the three intro rounds');
ok(min($rollCounts) >= 1, 'every run rolls at least once');

/* THE REPORTED SHAPE, measured rather than argued: at 80% the first roll fails
   one run in five, so round 3 really is the single likeliest round to spend a
   Medkit on. It is not evidence of a bug, and saying so needs a number. */
$at3 = 0;
for ($i = 0; $i < 6000; $i++) {
	if (strpos(dsr_run(80, 1)['markup'], "id='3'><h3>Round 3 - {Extra Life}") !== false) $at3++;
}
$pct = $at3 / 60;
ok($pct > 17 && $pct < 24,
   sprintf('round 3 takes the Medkit in %.0f%% of runs at 80%% (want ~20%%)', $pct));

/* ---------- 3. the caption is themed like every other one ------------------- */

echo "\nthe wording\n";
$m = dsr_run(80, 1)['markup'];
ok(strpos($m, '- {Extra Life}') !== false,
   'the Extra Life caption goes through evaluateText() like every other round');
ok(strpos($m, "- Extra Life</h3>") === false, 'and no longer bypasses it');

/* ---------- 4. the run records what it rolled ------------------------------- */

echo "\nthe replay records the numbers, not just the pictures\n";
$r = dsr_run(80, 2);
ok(preg_match('/<!-- run: survive<80 lives=2 rolls=([0-9,]+) -->/', $r['markup'], $t) === 1,
   'the markup carries the threshold, the lives carried and the rolls');
if (!empty($t[1])) {
	$traced = array_map('intval', explode(',', $t[1]));
	ok($traced === $r['rolls'], 'the trace is the rolls the run actually made');
	ok(count($traced) === count($r['rolls']) && count($traced) > 0, 'and one entry per decided round');
	$bad = 0; foreach ($traced as $v) if ($v < 0 || $v > 99) $bad++;
	ok($bad === 0, 'every roll is in range');
	/* A trace that always reads the same would be the thing worth finding. */
	$firsts = array();
	for ($i = 0; $i < 400; $i++) { $x = dsr_run(80, 1); $firsts[$x['rolls'][0]] = true; }
	ok(count($firsts) > 50, 'the first roll of a run varies (' . count($firsts) . ' distinct in 400)');
}

/* ---------- 5. the readout counts the same lives the run spends ------------- */

echo "\nthe screen and the roll agree on how many lives you have\n";
class DsrConn {
	public $rows = array();
	public function real_escape_string($s) { return $s; }
	public function query($sql) { return new DsrRes($this->rows); }
}
class DsrRes {
	private $r; public $num_rows;
	public function __construct($r) { $this->r = $r; $this->num_rows = count($r); }
	public function fetch_assoc() { return array_shift($this->r); }
}
function dsr_flags($roster, $sess = array()) {
	global $DSR_BASE;
	$_SESSION['userData'] = array_merge($DSR_BASE, array(
		'dropship_user_id' => 1, 'dropship_project_id' => 1), $sess);
	$c = new DsrConn();
	foreach ($roster as $x) $c->rows[] = array('asset_name' => $x[0], 'rank' => 'NFT',
		'armor' => $x[1], 'gear' => $x[2]);
	return dropshipOddsFlags($c);
}

$super = array(array('A','Heavy','Melee'), array('B','Medium','Demolition'),
               array('C','Light','Medkit'), array('D','Base','None'));
$f = dsr_flags($super);
ok(dropshipThreshold($f) == 80, 'a Super Soldier Squad rolls at 80, got ' . dropshipThreshold($f));
ok($f['extra_lives'] === 1, 'and carries one life, got ' . $f['extra_lives']);

/* THE DEFECT, as the readout saw it: squad Medkit plus a bought one is two. */
$f2 = dsr_flags($super, array('medkit' => 'true'));
ok($f2['extra_lives'] === 2, 'buying a Medkit on top of the squad one is two lives, got ' . $f2['extra_lives']);
$seen = array();
for ($i = 0; $i < 3000; $i++) $seen[substr_count(dsr_run(80, $f2['extra_lives'])['markup'], '- {Extra Life}')] = true;
ok(array_keys($seen) === array(2), 'and the run spends both of them, one per failure');

/* THE PROMO LIFE IS A LIFE. A bare squad gets a free Medkit from the run's
   no-armour branch, and the readout used to leave it out. */
$bare = dsr_flags(array());
ok(dropshipThreshold($bare) == 79, 'a bare squad rolls at 79, got ' . dropshipThreshold($bare));
ok($bare['extra_lives'] === 1, 'and the readout counts the promo Medkit, got ' . $bare['extra_lives']);
ok(dropshipTypicalRound(79, $bare['extra_lives']) > dropshipTypicalRound(79, 0),
   'so the typical round it quotes is the one the run plays to');

/*
 * STRICTLY LESS THAN, which is a contract between two files rather than a
 * behaviour a run can show. dropshipSurvivalPct() reports ceil($threshold)
 * because the roll survives on 0..threshold-1 -- 80 of the 100 outcomes for a
 * threshold of 80. Loosen the loop's comparison to <= and the run quietly
 * plays at 81% while every screen still says 80, and no amount of driving
 * either one would disagree: the drift is only visible in the operator.
 */
$loopCode = preg_replace(array('#/\*.*?\*/#s', '#//[^\n]*#'), '', $loopSrc);
ok(strpos($loopCode, '$probability < $threshold') !== false,
   'the roll survives on STRICTLY less than the threshold');
ok(strpos($loopCode, '$probability <= $threshold') === false,
   'and not on less-than-or-equal, which would be a free point of survival');
/* ...and an armoured squad must NOT get it, or every build gains a phantom life. */
$armoured = dsr_flags(array(array('A','Base','None')));
ok($armoured['extra_lives'] === 0, 'an armoured squad with no Medkit carries none, got ' . $armoured['extra_lives']);
$suited = dsr_flags(array(), array('exo_suit' => 'true'));
ok($suited['extra_lives'] === 0, 'a suit with no Medkit carries none either, got ' . $suited['extra_lives']);

/* The run must take the count from THAT function, or the two drift again. */
$runBlock = substr($page, strpos($page, "if(isset(\$_POST['run'])){"));
ok(strpos($runBlock, "\$ds_of    = dropshipOddsFlags(\$conn);") !== false,
   'the run counts its lives with dropshipOddsFlags(), the function the screen quotes');
$fnCode = preg_replace(array('#/\*.*?\*/#s', '#//[^\n]*#'), '', $fnSrc);
ok(strpos($fnCode, "\$_SESSION['userData']['medkit'] == \"true\"") === false,
   'and extralife() no longer re-reads the Medkit flag it was already counted from');

echo "\n" . ($fail ? "$fail check(s) FAILED\n" : "all run checks passed\n");
exit($fail ? 1 : 0);
