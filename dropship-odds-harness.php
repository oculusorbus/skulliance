<?php
/**
 * dropship-odds-harness.php — the odds the loadout screen promises.
 *
 * Run: php dropship-odds-harness.php
 *
 * The whole value of showing a number is that it is the SAME number the run
 * rolls against. So the extraction is checked against a verbatim copy of the
 * chain it replaced, over EVERY combination of flags, not spot-checked.
 */
require_once __DIR__ . '/dropship/dropship-odds.php';
require_once __DIR__ . '/harness-fixtures/dropship-threshold-original.php';

$fail = 0;
function ok($cond, $msg) {
	global $fail;
	if ($cond) { echo "  ok    $msg\n"; return; }
	$fail++; echo "  FAIL  $msg\n";
}

$KEYS = array('mech_suit', 'exo_suit', 'quantum_stealth', 'ballistic_shield',
              'heavy', 'medium', 'light', 'base');

echo "\nthe extraction matches the chain it replaced, on every input\n";
/*
 * 256 combinations of the eight flags, each run through BOTH the new function
 * and a verbatim copy of the original. The original reads each armour flag
 * from a local OR a session key, so both routes are exercised: the squad
 * route with the session empty, and the session route with the squad empty.
 * A difference in either is a loadout screen lying to a player.
 */
$bad = array();
for ($mask = 0; $mask < 256; $mask++) {
	$f = array();
	foreach ($KEYS as $i => $k) $f[$k] = (bool)($mask & (1 << $i));

	$tf = function ($b) { return $b ? 'true' : 'false'; };
	foreach (array('squad', 'session') as $route) {
		$viaSquad = ($route === 'squad');
		$sess = array('userData' => array(
			'heavy_armor'  => $tf(!$viaSquad && $f['heavy']),
			'medium_armor' => $tf(!$viaSquad && $f['medium']),
			'light_armor'  => $tf(!$viaSquad && $f['light']),
			'base_armor'   => $tf(!$viaSquad && $f['base']),
		));
		$orig = dropship_threshold_original(
			$tf($f['mech_suit']), $tf($f['exo_suit']),
			$tf($f['quantum_stealth']), $tf($f['ballistic_shield']),
			$tf($viaSquad && $f['heavy']),  $tf($viaSquad && $f['medium']),
			$tf($viaSquad && $f['light']),  $tf($viaSquad && $f['base']),
			$sess);
		$new = dropshipThreshold($f);
		if (abs($orig - $new) > 1e-9) {
			$bad[] = $route . ' ' . json_encode(array_keys(array_filter($f)))
			       . " orig=$orig new=$new";
		}
	}
}
ok(empty($bad), count($bad) . ' of 512 loadouts changed threshold: '
   . implode(' | ', array_slice($bad, 0, 3)));

echo "\nthe two rules that look like bugs are preserved\n";
/*
 * A BARE SQUAD BEATS HEAVY ARMOUR (79 vs 74). That is the Skulliance promo --
 * the no-armour branch also hands out extralife, demolition and melee -- and
 * anyone "fixing" the ordering silently removes it.
 */
ok(dropshipThreshold(array()) == 79, 'the bare-squad promo default is no longer 79');
ok(dropshipThreshold(array('heavy' => true)) == 74, 'Heavy armour is no longer 74');
ok(dropshipThreshold(array()) > dropshipThreshold(array('heavy' => true)),
   'a bare squad no longer beats Heavy armour, so the promo branch has been "fixed" away');
/* THE SUITS OVERRIDE, THEY DO NOT STACK. The additive version is commented
   out in dropship.php and must stay that way here. */
ok(dropshipThreshold(array('mech_suit' => true, 'heavy' => true, 'medium' => true,
                           'light' => true, 'base' => true)) == 88.9,
   'a suit now stacks with armour; the run does not do that');
/* Lower tiers pay a credit on top of the best worn -- the reason to build a
   mixed squad at all, and the thing the Skull Paper calls a Super Soldier Squad. */
ok(dropshipThreshold(array('heavy' => true, 'medium' => true, 'light' => true, 'base' => true)) == 80,
   'a full squad is no longer 80, so the Super Soldier Squad stopped paying');

echo "\nthe percentage matches how the roll is actually read\n";
/*
 * The run survives on rand(0,99) < threshold, so a threshold of T admits the
 * integers 0..ceil(T)-1. ceil, never round: round() would print a Mech Suit's
 * 88.9 as 88 and understate it by a point.
 */
/* PAIRS, NOT AN ASSOC ARRAY. PHP truncates a float array key to an integer,
   so array(65.66 => 66) silently becomes array(65 => 66) and the test checks
   a threshold the game never produces. That is how the first version of this
   check "failed" against correct code. */
foreach (array(array(50, 50), array(65.66, 66), array(72, 72), array(74, 74),
               array(79, 79), array(80, 80), array(83.3, 84), array(85.7, 86),
               array(87.5, 88), array(88.9, 89)) as $case) {
	list($t, $want) = $case;
	ok(dropshipSurvivalPct($t) === $want,
	   "a threshold of $t reads as " . dropshipSurvivalPct($t) . "%, not $want%");
}
/* Checked against the roll itself rather than against my own arithmetic. */
foreach (array(50, 65.66, 79, 88.9) as $t) {
	$hits = 0;
	for ($r = 0; $r <= 99; $r++) if ($r < $t) $hits++;
	ok(dropshipSurvivalPct($t) === $hits,
	   "threshold $t admits $hits of the 100 rolls but is shown as " . dropshipSurvivalPct($t) . '%');
}

echo "\nthe typical round is derived, not invented\n";
/* Rounds 0-2 are the drop and are never rolled for; the first roll is at
   counter 3. After that it is geometric, mean p/(1-p). */
ok(dropshipTypicalRound(50, 0) === 4, '50% should typically die on round 4, got ' . dropshipTypicalRound(50, 0));
ok(dropshipTypicalRound(79, 0) === 7, '79% should typically reach round 7, got ' . dropshipTypicalRound(79, 0));
ok(dropshipTypicalRound(89, 0) === 11, '89% should typically reach round 11, got ' . dropshipTypicalRound(89, 0));
/* A Medkit buys a second run at the same distribution. */
ok(dropshipTypicalRound(79, 1) > dropshipTypicalRound(79, 0),
   'an extra life does not extend the typical round');
ok(dropshipTypicalRound(89, 1) === 19, 'a Mech Suit with a Medkit should reach ~19, got ' . dropshipTypicalRound(89, 1));
/* It must rise with the odds, or the screen would argue against buying. */
$prev = 0;
foreach (array(50, 66, 72, 74, 79, 80, 84, 86, 88, 89) as $p) {
	$r = dropshipTypicalRound($p, 0);
	ok($r >= $prev, "typical round falls from $prev to $r at $p%, so a better loadout reads worse");
	$prev = $r;
}
/* 100% has no honest answer -- never divide by zero into a player's face. */
ok(dropshipTypicalRound(100, 0) === null, 'a certain loadout returns a number instead of nothing');

echo "\nthe squad is read the same way the run reads it\n";
/*
 * dropshipOddsFlags() does its own SELECT rather than calling setSquad(),
 * which writes globals the run depends on -- re-running it while rendering a
 * page would change a run instead of describing one. The cost of that choice
 * is a second copy of the armour mapping, so the mapping is pinned here.
 */
/* COMMENTS STRIPPED before any of these look for a call. The readout's own
   docblock says "the same way setSquad() does", which made the check below
   match its own explanation and fail against correct code. */
require_once __DIR__ . '/harness-strip.php';
$dbsrc = file_get_contents(__DIR__ . '/dropship/db.php');
$odds  = harness_strip_comments(file_get_contents(__DIR__ . '/dropship/dropship-odds.php'));
foreach (array('Heavy', 'Medium', 'Light', 'Base') as $tier) {
	ok(strpos($dbsrc, '"armor"] == "' . $tier . '"') !== false,
	   "setSquadAttributes() no longer maps $tier, so the readout and the run disagree");
	ok(strpos($odds, "case '" . $tier . "'") !== false,
	   "the readout no longer maps $tier");
}
ok(strpos($dbsrc, '$row["gear"] == "Medkit"') !== false && strpos($odds, "=== 'Medkit'") !== false,
   'the Medkit mapping drifted between the run and the readout');
ok(strpos($odds, 'deceased = 0') !== false,
   'the readout counts dead soldiers, so it promises odds a squad cannot deliver');
ok(strpos($odds, 'active = 1') !== false, 'the readout counts benched soldiers');
/* And it must not touch the globals. */
ok(strpos($odds, 'setSquad(') === false && strpos($odds, 'global ') === false,
   'the readout calls into the run\'s global state while rendering a page');

echo "\nthe run and the screen share one copy of the rule\n";
$run = harness_strip_comments(file_get_contents(__DIR__ . '/dropship/dropship.php'));
ok(strpos($run, 'dropshipThreshold(array(') !== false,
   'the run no longer calls the shared function, so the two can drift again');
ok(strpos($run, "require_once __DIR__ . '/dropship-odds.php'") !== false,
   'the run does not load the odds library it depends on');
/* THE OLD CHAIN MUST BE GONE, not merely bypassed. */
ok(strpos($run, '$heavy_threshold = 74;') === false,
   'the original chain is still in dropship.php; a later edit would change the run and not the screen');
ok(substr_count($run, '88.9') === 0,
   'a suit threshold is still hardcoded in the run');
/* The promo side effect stayed behind, because it is not part of the odds. */
ok(strpos($run, "\$extralife = 'true';") !== false,
   'the Skulliance promo gear grant was moved or lost with the arithmetic');

echo ($fail ? "\n$fail check(s) FAILED\n" : "\nall good\n");
exit($fail ? 1 : 0);
