<?php
/**
 * dropship-grunts-harness.php — the four free soldiers' loaner kit.
 *
 * Run: php dropship-grunts-harness.php
 *
 * The whole change is a claim about ODDS, so it is checked by running the
 * real threshold math over the real kits, not by reading the table back.
 */
require_once __DIR__ . '/dropship/dropship-grunts.php';
require_once __DIR__ . '/dropship/dropship-odds.php';
require_once __DIR__ . '/harness-strip.php';

$fail = 0;
function ok($cond, $msg) {
	global $fail;
	if ($cond) { echo "  ok    $msg\n"; return; }
	$fail++; echo "  FAIL  $msg\n";
}
function grunt($asset) {
	return dropshipGruntLoadout(array('asset_name' => $asset, 'rank' => 'Grunt',
	                                  'armor' => 'None', 'gear' => 'None', 'level' => '0'));
}
/* Flags for a squad of the given grunts, through the same reading the run does. */
function squad($assets) {
	$f = array('extra_lives' => 0);
	foreach ($assets as $a) {
		$r = grunt($a);
		$tier = strtolower($r['armor']);
		if (in_array($tier, array('heavy', 'medium', 'light', 'base'), true)) $f[$tier] = true;
		if ($r['gear'] === 'Medkit') $f['extra_lives']++;
	}
	return $f;
}
function pct($assets) { return dropshipSurvivalPct(dropshipThreshold(squad($assets))); }

$ALL = array('GRUNT001', 'GRUNT002', 'GRUNT003', 'GRUNT004');

echo "\nthe four together are exactly a Super Soldier Squad\n";
/*
 * THE POINT OF THE WHOLE CHANGE. Four slots, four free soldiers, and the
 * intuitive move -- deploy all of them -- must now be the best one. 80 is
 * Heavy's 74 plus the Medium, Light and Base credits, which is what the
 * Skull Paper has always called a Super Soldier Squad.
 */
ok(pct($ALL) === 80, 'the four grunts together are ' . pct($ALL) . '%, not the 80% of a full squad');
$tiers = array();
foreach ($ALL as $a) $tiers[] = grunt($a)['armor'];
sort($tiers);
ok($tiers === array('Base', 'Heavy', 'Light', 'Medium'),
   'the four grunts do not cover all four armour tiers: ' . implode(', ', $tiers));

echo "\nsending one is now worse than sending four\n";
/*
 * The bug this fixes: a lone grunt scored 79 off the no-armour promo branch,
 * four grunts also scored 79, and real Heavy armour scored 74. Deploying one
 * was the strongest opening in the game and four was no better.
 */
foreach ($ALL as $a) {
	$one = pct(array($a));
	ok($one < pct($ALL),
	   grunt($a)['armor'] . " alone is $one%, which is not worse than the full squad's " . pct($ALL) . '%');
	ok($one !== 79, 'a lone grunt still falls through to the 79% promo branch');
}
/* And no pair or trio may match the full four, or the fourth slot is wasted. */
$best_partial = 0;
for ($mask = 1; $mask < 15; $mask++) {
	$pick = array();
	foreach ($ALL as $i => $a) if ($mask & (1 << $i)) $pick[] = $a;
	$best_partial = max($best_partial, pct($pick));
}
ok($best_partial < pct($ALL),
   "a partial squad already reaches $best_partial%, so there is no reason to deploy the fourth");

echo "\nthe loan is armour and gear, never a promotion\n";
/* Level and rank feed the rank tiers and anything that reads progression.
   The kit must not touch them. */
foreach ($ALL as $a) {
	$r = grunt($a);
	ok($r['level'] === '0', "$a is no longer level 0, so a loaner kit is promoting it");
	ok($r['rank'] === 'Grunt', "$a is no longer ranked Grunt");
}

echo "\nSektr carries nothing, and that is the design\n";
/*
 * Three gear types, four grunts. Duplicating one would hand a two-grunt squad
 * a second Medkit the run cannot use, so the Base-armour grunt goes without.
 */
ok(grunt('GRUNT004')['armor'] === 'Base', 'Sektr is not the Base-armour grunt any more');
ok(grunt('GRUNT004')['gear'] === 'None', 'Sektr was given gear; the Base grunt carries none by design');
$gear = array();
foreach ($ALL as $a) { $g = grunt($a)['gear']; if ($g !== 'None') $gear[] = $g; }
sort($gear);
ok($gear === array('Demolition', 'Medkit', 'Melee'),
   'the three gear types are not one each: ' . implode(', ', $gear));
ok(count($gear) === count(array_unique($gear)), 'a gear type is duplicated across the grunts');
/* Exactly one Medkit, so the squad gets exactly one extra life. */
ok(squad($ALL)['extra_lives'] === 1,
   'the full squad carries ' . squad($ALL)['extra_lives'] . ' extra lives, not 1');

echo "\nonly these four are touched\n";
/* An NFT soldier must never pick up a loaner kit, whatever it is called. */
$nft = dropshipGruntLoadout(array('asset_name' => 'GRUNT001', 'rank' => 'Private',
                                  'armor' => 'None', 'gear' => 'None'));
ok($nft['armor'] === 'None',
   'a non-Grunt with a colliding asset_name was handed free armour');
$other = dropshipGruntLoadout(array('asset_name' => 'DROPSHIP042', 'rank' => 'Grunt',
                                    'armor' => 'Heavy', 'gear' => 'Melee'));
ok($other['armor'] === 'Heavy' && $other['gear'] === 'Melee',
   'a soldier outside the four kits had its loadout rewritten');
foreach (array(array(), array('asset_name' => ''), 'not-a-row') as $junk) {
	$r = dropshipGruntLoadout($junk);
	ok($r === $junk, 'a malformed row was not returned untouched');
}

echo "\nthe run, the barracks and the odds all apply it\n";
/*
 * Three readers, and they have to agree. If the listing shows Heavy and the
 * run fields None, the screen is lying about a squad a player just built.
 */
$db   = harness_strip_comments(file_get_contents(__DIR__ . '/dropship/db.php'));
$odds = harness_strip_comments(file_get_contents(__DIR__ . '/dropship/dropship-odds.php'));
ok(preg_match('/function setSquadAttributes\(\$row\)\s*\{.*?dropshipGruntLoadout\(\$row\)/s', $db) === 1,
   'the run does not apply the loaner kit, so a grunt squad still fields no armour');
ok(preg_match('/foreach\(\$rows as \$row\)\s*\{\s*\$row = dropshipGruntLoadout\(\$row\);/', $db) === 1,
   'the barracks listing does not apply the kit, so it shows None while the run fields Heavy');
ok(strpos($odds, 'dropshipGruntLoadout($row)') !== false,
   'the odds readout does not apply the kit, so it quotes odds for the wrong squad');
ok(strpos($odds, 'asset_name, rank') !== false,
   'the odds query no longer selects the columns the kit is keyed on');
ok(strpos($db, "require_once __DIR__ . '/dropship-grunts.php'") !== false,
   'db.php does not load the grunt library it calls');

echo "\nOculus Lounge has no grunts and cannot get any\n";
/* addDefaultSoldiers() is gated on project 1, so nothing here can reach the
   Lounge -- asserted rather than assumed, since the kit is name-keyed and
   would happily apply to a Lounge row if one ever existed. */
$dash = harness_strip_comments(file_get_contents(__DIR__ . '/dropship/dashboard.php'));
ok(preg_match("/dropship_project_id'\] == 1 &&.*?addDefaultSoldiers/s", $dash) === 1,
   'free soldiers are no longer gated to Drop Ship, so the Lounge would get grunts too');

echo "\nthe copy no longer tells players to send them one at a time\n";
/*
 * The instructions said each Grunt "carries full Super Soldier Squad power"
 * and to "send them in one at a time for full effect". That was true of the
 * old promo branch and is now exactly backwards, so it is pinned: advice that
 * contradicts the odds screen is worse than no advice.
 */
$instr = file_get_contents(__DIR__ . '/dropship/instructions.php');
ok(strpos($instr, 'one at a time for full effect') === false,
   'the instructions still tell players to send grunts one at a time');
ok(strpos($instr, 'each one carries full') === false,
   'the instructions still claim a single grunt is a full Super Soldier Squad');
ok(strpos($instr, 'Deploy all four at once') !== false,
   'the instructions do not tell players to deploy all four');
/* And the block must not render for a project that has no grunts. */
ok(preg_match("/dropship_project_id'\] == 1\)\{ \?>\s*<div class=\"instr-section\">\s*<h3>Play for Free/", $instr) === 1,
   'the free-soldiers block is not gated to Drop Ship, so the Lounge is promised grunts it never gets');

$paper = file_get_contents(__DIR__ . '/skullpaper/games-drop-ship.md');
ok(strpos($paper, 'Deploy all four together') !== false,
   'the Skull Paper does not say to deploy all four');
foreach (array('Hagar', 'Delay', 'Blood Bag', 'Sektr') as $n) {
	ok(strpos($paper, $n) !== false, "the Skull Paper does not list $n");
}
ok(strpos($paper, 'Oculus Lounge has no equivalent') !== false,
   'the Skull Paper does not say grunts are Drop Ship only');

echo ($fail ? "\n$fail check(s) FAILED\n" : "\nall good\n");
exit($fail ? 1 : 0);
