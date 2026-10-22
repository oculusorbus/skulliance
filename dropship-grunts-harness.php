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

echo "\nthe Barracks filter finds the kit it displays\n";
/*
 * The filter runs in SQL against armor/gear, which for a grunt still read
 * None -- so filtering by Heavy hid Hagar while his card showed Heavy. The
 * fragment is built from the SAME kit table the cards are drawn from, so the
 * filter cannot disagree with what it is filtering.
 */
foreach (dropshipGruntKits() as $asset => $kit) {
	foreach (array('armor', 'gear') as $field) {
		if ($kit[$field] === 'None') continue;
		$sql = dropshipGruntFilterSql($field, $kit[$field]);
		ok(strpos($sql, "'" . $asset . "'") !== false,
		   "filtering by {$kit[$field]} does not find $asset, which displays it");
	}
}
/* Exactly one grunt per tier, so a filter never pulls in a second. */
foreach (array('Heavy', 'Medium', 'Light', 'Base') as $tier) {
	ok(substr_count(dropshipGruntFilterSql('armor', $tier), 'GRUNT') === 1,
	   "the $tier filter matches more than one grunt");
}
/* Sektr carries no gear, so no gear filter may produce him. */
foreach (array('Medkit', 'Demolition', 'Melee') as $g) {
	ok(strpos(dropshipGruntFilterSql('gear', $g), 'GRUNT004') === false,
	   "the $g filter pulls in Sektr, who carries nothing");
}
/* Nothing qualifies -> no fragment, rather than a broken IN (). */
ok(dropshipGruntFilterSql('armor', 'Nonesuch') === '', 'an unmatched filter emits SQL anyway');
/*
 * A FIELD OUTSIDE armor/gear MUST BE REFUSED BEFORE THE LOOKUP, not merely
 * come back empty. Without the guard the kit lookup reads a key that is not
 * there, and display_errors is on platform-wide, so the warning prints into
 * the Barracks. Checking only the return value passes either way -- that
 * mutation got through the first version of this check.
 */
$warnings = 0;
set_error_handler(function () use (&$warnings) { $warnings++; return true; });
$bad = dropshipGruntFilterSql('level', 'Heavy');
restore_error_handler();
ok($bad === '', 'a column outside armor/gear is accepted');
ok($warnings === 0,
   'the filter raised ' . $warnings . ' warning(s) on an unknown column, which print into the page');
/*
 * THE GRUNT TERM IS RANK-GUARDED, so an NFT with a colliding asset_name is
 * not swept in and handed free armour.
 *
 * NARROWED FROM "every non-empty fragment is guarded", which stopped being
 * the contract when pinned NFTs joined this function: those are real NFTs
 * with real ranks, so their terms are deliberately NOT rank-guarded, and the
 * exclusion half can be the only thing a fragment contains. The guard
 * belongs to the GRUNT names, which is what it was always protecting.
 */
foreach (array('armor', 'gear') as $field) {
	foreach (array('Heavy', 'Medkit') as $v) {
		$sql = dropshipGruntFilterSql($field, $v);
		if (strpos($sql, 'GRUNT') !== false) ok(strpos($sql, "rank = 'Grunt'") !== false,
		   "the $v filter fragment names a grunt without guarding on rank");
	}
}
/* And the listing has to use it, inside the parenthesised clause -- an
   unbracketed OR would widen the whole WHERE and return every soldier. */
$dbq = harness_strip_comments(file_get_contents(__DIR__ . '/dropship/db.php'));
ok(strpos($dbq, "\"AND (armor = '\".\$filterby.\"'\".dropshipGruntFilterSql('armor', \$filterby).\") \"") !== false,
   'the armor filter does not bracket the grunt OR, so it widens the whole query');
ok(strpos($dbq, "\"AND (gear = '\".\$filterby.\"'\".dropshipGruntFilterSql('gear', \$filterby).\") \"") !== false,
   'the gear filter does not bracket the grunt OR');

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

/* ---------- pinned NFT loadouts ------------------------------------------- */
/*
 * THIS BLOCK NEVER RAN. The summary and exit() below used to sit HERE, with
 * the section header concatenated onto the same line as the exit:
 *
 *   exit($fail ? 1 : 0);/* ---------- pinned NFT loadouts ---------- *\/
 *
 * so every assertion from this point to the end of the file was dead from
 * the moment it was written, and the harness reported "all good" without
 * executing any of it. It was only noticed when the loadouts changed from
 * four hand-pinned names to 226 derived ones and NOTHING went red.
 *
 * The summary now lives at the end of the file, which is the only place it
 * can be without silently truncating the suite again.
 */

echo "\nthe pinned DHC2:F squad\n";
/*
 * Drop Ship reads only armor and gear off a soldier, and for a collection it
 * cannot map both come from dropshipStatsFromAssetName() -- a derivation from
 * the asset name, not a fact about the art. On DHC2:F that gave two Base
 * tiers, no Heavy and no Medkit, so the collection could not make a Super
 * Soldier Squad at all.
 */
$pinned = dropshipNftLoadouts();
/* THE WHOLE COLLECTION, NOT FOUR NAMES. The four hand-pins were a test
   that handed one holder a squad; the table is now every minted fighter,
   derived from its traits. Asserted as "most of the collection" rather
   than an exact count, so regenerating the mapping does not fail this. */
ok(count($pinned) > 200, 'the loadout table is not the whole collection, got ' . count($pinned));
ok(count($pinned) === count(dropshipNftLoadouts()),
   'the loadout table is not stable between calls, so it is being re-read per soldier');
$tiers = array(); $gear = array();
foreach ($pinned as $a => $kit) { $tiers[$kit['armor']] = true; $gear[$kit['gear']] = true; }
ok(count($tiers) === 4, 'the pinned squad does not cover all four armour tiers: '
   . implode(',', array_keys($tiers)));
foreach (array('Melee', 'Demolition', 'Medkit') as $g) {
	ok(isset($gear[$g]), "the pinned squad carries no $g, so it is not a Super Soldier Squad");
}
/* Driven through the real threshold, so the claim is the game's and not
   mine: 74 + 3 + 2 + 1. */
$f = array('mech_suit'=>false,'exo_suit'=>false,'quantum_stealth'=>false,'ballistic_shield'=>false,
           'heavy'=>false,'medium'=>false,'light'=>false,'base'=>false,'extra_lives'=>0,'roster'=>array());
/* ONE SQUAD'S WORTH, not all 226 -- feeding the whole collection in sets
   every flag true and proves nothing. One fighter per tier, taken from
   the table itself. */
$one_each = array();
foreach ($pinned as $kit) if (!isset($one_each[$kit['armor']])) $one_each[$kit['armor']] = $kit;
foreach ($one_each as $kit) {
	$t = strtolower($kit['armor']);
	if (array_key_exists($t, $f)) $f[$t] = true;
	$f['roster'][] = array('armor' => $kit['armor'], 'gear' => $kit['gear']);
}
ok(dropshipThreshold($f) == 80, 'the pinned squad does not roll at 80, got ' . dropshipThreshold($f));

/* APPLIED THROUGH THE SAME FUNCTION EVERYTHING ELSE USES, and before the
   Grunt gate -- these are real NFTs with a real rank, so the rank check
   would otherwise return them untouched. */
foreach ($pinned as $asset => $kit) {
	$row = dropshipGruntLoadout(array('asset_name' => $asset, 'name' => $asset,
	                                  'rank' => 'Neo Miami Citizen',
	                                  'armor' => 'Base', 'gear' => 'None', 'level' => 1));
	ok($row['armor'] === $kit['armor'] && $row['gear'] === $kit['gear'],
	   "$asset is not overridden: {$row['armor']}/{$row['gear']}");
	/* Level and rank are the collection's, exactly as for a grunt. */
	ok((int)$row['level'] === 1 && $row['rank'] === 'Neo Miami Citizen',
	   "$asset had its level or rank rewritten, which the loan never does");
}
/* Matched on the display name too, since the two columns are written from
   different metadata fields and only one of them was ever verified. */
$probe_asset = 'DHC2F088';
$probe_kit   = $pinned[$probe_asset];
$byName = dropshipGruntLoadout(array('asset_name' => 'something-else', 'name' => $probe_asset,
                                     'rank' => 'Neo Miami Citizen', 'armor' => 'Base', 'gear' => 'None'));
ok($byName['armor'] === $probe_kit['armor'],
   'the override does not fall back to the display name');
/* An NFT nobody pinned is untouched. */
$other = dropshipGruntLoadout(array('asset_name' => 'DHC2F999', 'name' => 'DHC2F999',
                                    'rank' => 'Neo Miami Citizen', 'armor' => 'Light', 'gear' => 'Melee'));
ok($other['armor'] === 'Light' && $other['gear'] === 'Melee', 'an unpinned NFT was rewritten');
/* The grunts still get theirs. */
$g = dropshipGruntLoadout(array('asset_name' => 'GRUNT003', 'rank' => 'Grunt',
                                'armor' => 'None', 'gear' => 'None'));
ok($g['armor'] === 'Heavy', 'the override broke the grunt loaner kit');

echo "\nthe barracks filter agrees with the cards\n";
/*
 * BOTH WAYS. A pinned soldier's STORED armor is whatever the sync derived, so
 * filtering by Heavy hid the card that says Heavy -- and filtering by Base
 * SHOWED it, because Base is still in the column. Adding it to the right
 * filter without removing it from the wrong one fixes half and looks done.
 */
/* NAMED FROM THE TABLE, not written out. The fragment now carries every
   fighter in the collection, so a literal list would be 226 names long
   and wrong the next time the mapping is regenerated. What matters is
   the SHAPE: a Heavy fighter is added back, and one that is Heavy only
   in the stored column is taken out. */
$heavy = dropshipGruntFilterSql('armor', 'Heavy');
$a_heavy = null; $a_other = null;
foreach ($pinned as $a => $kit) {
	if ($a_heavy === null && $kit['armor'] === 'Heavy')  $a_heavy = $a;
	if ($a_other === null && $kit['armor'] !== 'Heavy')  $a_other = $a;
	if ($a_heavy !== null && $a_other !== null) break;
}
ok($a_heavy !== null && strpos($heavy, "'" . $a_heavy . "'") !== false,
   "filtering by Heavy does not include $a_heavy, which the table says is Heavy");
$drop_at = strpos($heavy, 'NOT IN');
ok($drop_at !== false && strpos(substr($heavy, $drop_at), "'" . $a_other . "'") !== false,
   "filtering by Heavy does not exclude $a_other, which is pinned elsewhere");
/* And the exclusion still comes FIRST -- see the precedence note above. */
$add_at = strpos($heavy, ' OR asset_name IN (');
ok($drop_at !== false && ($add_at === false || $drop_at < $add_at),
   'the NOT IN no longer precedes the OR terms, so it swallows them');
$base = dropshipGruntFilterSql('armor', 'Base');
ok(strpos($base, "NOT IN") !== false && strpos($base, "'DHC2F088'") !== false,
   'filtering by Base still shows the soldier whose stored Base was overridden');
/* The exclusion must come FIRST: the caller wraps this as
   `AND (armor = 'X' <fragment>)` and AND binds tighter than OR, so a NOT IN
   placed after the OR terms would swallow them and the filter return nothing. */
ok(strpos($heavy, ' AND asset_name NOT IN') === 0,
   'the exclusion is no longer first, so it applies to the OR terms as well');
$caller = (string)file_get_contents(__DIR__ . '/dropship/db.php');
ok(strpos($caller, "\"AND (armor = '\".\$filterby.\"'\".dropshipGruntFilterSql('armor', \$filterby).\") \"") !== false,
   'the caller no longer wraps the fragment in parentheses, which the precedence above depends on');

echo ($fail ? "\n$fail check(s) FAILED\n" : "\nall good\n");
exit($fail ? 1 : 0);
