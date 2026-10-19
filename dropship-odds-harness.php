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

echo "\nthe shop says what the scrip buys\n";
/*
 * The squad panel answers "how good am I". A shopper with a finger over Buy
 * is asking "how much better would THIS make me", which is a different
 * number, measured against the loadout they are actually holding.
 */
$bare = array('extra_lives' => 0);
$held = array('heavy' => true, 'medium' => true, 'extra_lives' => 0);
$note = dropshipItemOddsNote('Mech Suit', $held);
ok(strpos($note, '77%') !== false && strpos($note, '89%') !== false,
   'a Mech Suit does not quote the jump against the squad being held: ' . $note);
/*
 * THE SUITS OVERRIDE, so a cheaper one bought over a better one is worth
 * exactly nothing, and saying so is the point -- it is what stops the
 * purchase. "No change" rather than "worse": adding a suit can only move a
 * player UP the chain, verified over all 256 loadouts x 4 suits with zero
 * cases lowering the odds, so the note has no downgrade branch to hit.
 */
$worn = array('mech_suit' => true, 'extra_lives' => 0);
ok(strpos(dropshipItemOddsNote('Ballistic Shield', $worn), 'No change') === 0,
   'a pointless purchase is not called out: ' . dropshipItemOddsNote('Ballistic Shield', $worn));
$never = 0;
$keys = array('mech_suit', 'exo_suit', 'quantum_stealth', 'ballistic_shield', 'heavy', 'medium', 'light', 'base');
for ($m = 0; $m < 256; $m++) {
	$f = array('extra_lives' => 0);
	foreach ($keys as $i => $k) $f[$k] = (bool)($m & (1 << $i));
	$base_pct = dropshipSurvivalPct(dropshipThreshold($f));
	foreach (array_slice($keys, 0, 4) as $suit) {
		$t = $f; $t[$suit] = true;
		if (dropshipSurvivalPct(dropshipThreshold($t)) < $base_pct) $never++;
	}
}
ok($never === 0, $never . ' loadouts get WORSE from buying a suit, so the note needs a downgrade branch');
ok(strpos(dropshipItemOddsNote('Mech Suit', $worn), 'Carried') === 0,
   'a suit already carried is still being sold as an upgrade');
/* A Medkit changes no roll; it buys a second run at the same one. */
$m = dropshipItemOddsNote('Medkit', $held);
ok(strpos($m, 'extra life') !== false && strpos($m, 'per round') === false,
   'a Medkit is described as changing the per-round odds, which it does not: ' . $m);
/* The escapes are named and excluded, matching the panel's own footnote. */
foreach (array('Drop Box', 'Radio', 'Jet Pack') as $n) {
	ok(strpos(dropshipItemOddsNote($n, $held), 'not counted in the odds') !== false,
	   "$n does not say it is outside the odds");
}
/*
 * SILENT WHERE IT CANNOT MEASURE. Weapons, Radar and Night Vision Goggles
 * decorate a round that was already survived. A number on their price tag
 * would be invented, and invented is worse than absent.
 */
/* Weapons DO have a measurable effect and now carry it; what stays silent is
   anything that only decorates a round already survived. */
foreach (array('Radar', 'Night Vision Goggles', 'Reinforcements', '') as $n) {
	ok(dropshipItemOddsNote($n, $held) === null, "an odds claim was invented for '$n'");
}
/* Name to session key uses the run's own rule, or the two drift. */
ok(dropshipItemOddsNote('Mech Suit', $bare) === dropshipItemOddsNote('mech suit', $bare),
   'the item name is not normalised the way the run normalises it');
/* And the shop has to actually print it, once per item, reading flags once. */
$db = harness_strip_comments(file_get_contents(__DIR__ . '/dropship/db.php'));
ok(strpos($db, 'dropshipItemOddsNote($row["name"], $odds_flags)') !== false,
   'the shop listing never calls the per-item note');
/* BELOW THE DESCRIPTION. Read what the item is, then what it is worth --
   and it is where a player looks for it. */
$desc_at = strpos($db, "item-description");
$note_at = strpos($db, 'dropshipItemOddsNote($row["name"]');
ok($desc_at !== false && $note_at > $desc_at,
   'the odds line sits above the item description instead of under it');
ok(preg_match('/\$odds_flags = dropshipOddsFlags\(\$conn\);\s*\n.*?while\(\$row = \$result->fetch_assoc/s', $db) === 1,
   'the shop reads the loadout inside the item loop, which is a query per item');

echo "\nweapons say what they are worth, and which one actually fires\n";
/*
 * Weapons add rounds rather than changing the roll: on a failed round the
 * melee chain can fire and weapondelay() pushes the counter forward, and the
 * counter at death IS the score.
 *
 * THE ONE THAT FIRES IS NOT THE BEST ONE OWNED. setWeapons() is a chain of
 * non-exclusive ifs that each overwrite the choice, so the LAST checked wins
 * -- and Tactical Katana, the weakest at +1, is checked last. A shop that
 * quotes +5 for a Rocket Launcher while the run gives +1 is lying.
 */
$none = array('extra_lives' => 0);
ok(strpos(dropshipItemOddsNote('Rocket Launcher', $none), '+5 rounds') !== false,
   'a Rocket Launcher does not quote its round bonus');
ok(strpos(dropshipItemOddsNote('Tactical Katana', $none), '+1 round ') !== false,
   'a Tactical Katana does not quote its bonus, or pluralises 1');
$both = array('extra_lives' => 0, 'rocket_launcher' => true, 'tactical_katana' => true);
ok(strpos(dropshipItemOddsNote('Rocket Launcher', $both), 'fires instead') !== false,
   'the shop does not warn that a weaker weapon overrides the Rocket Launcher: '
 . dropshipItemOddsNote('Rocket Launcher', $both));
ok(strpos(dropshipItemOddsNote('Tactical Katana', $both), 'fires instead') === false,
   'the weapon that actually fires is told it is being overridden');
/* The selection order IS setWeapons()'s, and the harness reads it from there
   rather than trusting the table. */
$runsrc = harness_strip_comments(file_get_contents(__DIR__ . '/dropship/dropship.php'));
$order = array();
if (preg_match('/function setWeapons.*?\n\}/s', $runsrc, $m)) {
	preg_match_all("/userData'\]\['([a-z_]+)'\]/", $m[0], $mm);
	foreach ($mm[1] as $k) if (!in_array($k, $order, true)) $order[] = $k;
}
ok($order === array_keys(dropshipWeaponDelays()),
   'the weapon table is out of step with setWeapons(), so the wrong weapon is named: '
 . implode(',', $order));
ok(dropshipActiveWeapon($both) === 'tactical_katana',
   'the active weapon is not the last one setWeapons() checks');
ok(dropshipActiveWeapon(array()) === null, 'a weapon fires when none is owned');

echo "\nthe squad pane guides the build and stops selling items\n";
/*
 * This pane is for somebody with a half-built squad deciding what goes in
 * the next slot. Suits and escapes are a different decision and now carry
 * their own numbers in the shop, where a player is already spending.
 */
$panel_src = file_get_contents(__DIR__ . '/dropship/dropship-odds.php');
ok(strpos($panel_src, 'ds-odds-next') === false || strpos($panel_src, 'would make it') === false,
   'the squad pane still upsells a suit instead of guiding the build');
ok(strpos($panel_src, 'Drop Box, Radio and Jet Pack are not counted') === false,
   'the squad pane still carries the item footnote');
/* Step by step, from whatever they have now. */
$r = function ($list) {
	$f = array('extra_lives' => 0, 'roster' => array());
	foreach ($list as $pair) {
		list($a, $g) = $pair;
		$f['roster'][] = array('armor' => $a, 'gear' => $g);
		$f[strtolower($a)] = true;
		if ($g === 'Medkit') $f['extra_lives']++;
	}
	return $f;
};
$one = dropshipSquadAdvice($r(array(array('Heavy', 'Melee'))));
ok($one !== null && strpos($one[0], 'Next:') === 0,
   'a one-soldier squad gets no next step: ' . json_encode($one));
ok(strpos($one[1], '1 of 4 slots') !== false, 'the advice does not say how many slots are filled');
ok(strpos($one[0], 'Medium') !== false || strpos($one[0], 'Light') !== false
   || strpos($one[0], 'Base') !== false, 'the next step names no armor tier: ' . $one[0]);
/* FULL BUT NOT SUPER: say why, and what to swap. */
$dupe = dropshipSquadAdvice($r(array(array('Heavy','Melee'), array('Medium','Demolition'),
                                     array('Medium','Medkit'), array('Light','None'))));
ok(strpos($dupe[1], 'full but not a Super Soldier Squad') !== false,
   'a full non-super squad is not told why: ' . $dupe[1]);
ok(strpos($dupe[1], 'Medium') !== false && strpos($dupe[1], 'Base') !== false,
   'the reason names neither the duplicate nor the gap: ' . $dupe[1]);
/* IT MUST NAME THE SOLDIER TO SWAP OUT, not just the gap. "Swap one soldier
   for Base" is useless when the player has to work out which one; naming the
   duplicated tier is the whole advice. A looser check passed a mutation that
   dropped exactly that. */
ok(strpos($dupe[0], 'Swap a <b>Medium</b>') === 0,
   'the swap does not name the duplicated tier to drop: ' . $dupe[0]);
ok(strpos($dupe[0], 'Base') !== false, 'the swap does not name the missing tier: ' . $dupe[0]);
/* COMPLETE: say so and stop advising. */
$full = dropshipSquadAdvice($r(array(array('Heavy','Melee'), array('Medium','Demolition'),
                                     array('Light','Medkit'), array('Base','None'))));
ok($full[0] === 'Super Soldier Squad', 'a complete squad is not recognised: ' . $full[0]);
ok(strpos($full[0], 'Next:') === false && strpos($full[0], 'Swap') === false,
   'a complete squad is still being given advice');
/* An empty squad has nothing to advise on. */
ok(dropshipSquadAdvice(array('roster' => array())) === null, 'an empty squad is given advice');

echo "\nthe panel survives the stylesheet it renders inside\n";
/*
 * dist/flexbox.css CENTRES TEXT GLOBALLY. A flex child stretched to full
 * width inherits that, so the first row sat left (flex puts it at
 * flex-start) and every line under it centred. That is how this shipped the
 * first time; it is pinned rather than remembered.
 */
$odds_src = file_get_contents(__DIR__ . '/dropship/dropship-odds.php');
ok(substr_count($odds_src, 'text-align:left') >= 2,
   'the panel does not re-state text-align, so flexbox.css centres half of it');
ok(strpos($odds_src, '#00c8a0') !== false,
   'the panel is off the platform accent the Squad heading beside it uses');
ok(stripos($odds_src, '#7CFC00') === false, 'the lime is back');
/* Plain hyphens in copy a player reads. */
ok(strpos($odds_src, '&mdash;') === false, 'an em dash is back in the player-facing copy');

echo ($fail ? "\n$fail check(s) FAILED\n" : "\nall good\n");
exit($fail ? 1 : 0);
