<?php
/* realms-panel-harness.php — CLI only. No database.
 *
 * The rebuilt locations panel.
 *
 * WHAT BREAKS SILENTLY HERE. The panel is markup that a large amount of
 * existing JavaScript reaches into by id -- equipping an item, stocking a
 * location, ticking a countdown, refreshing an upgrade -- and none of that
 * fails loudly. getElementById() returns null and the feature simply stops
 * working. So this renders the real partial and checks every hook is still
 * in the output.
 *
 * AND THE BOOST TABLE EXISTS THREE TIMES: realm_location_effects() in
 * realms-lib.php, getLocationSuccessRateBoost() in db.php (the one that
 * decides an actual raid), and _updateLocationStatusLabels() in
 * skulliance.js (the one that redraws after you equip something). If they
 * drift, the card lies about what the location will do in a fight.
 *
 * Usage: php realms-panel-harness.php
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

$fail = 0;
function ok($cond, $what) { global $fail; if (!$cond) { $fail++; echo "  FAIL  $what\n"; } }

define('REALM_UPGRADE_CEILING', 10);

/*
 * The pure helpers are lifted out of realms-lib.php rather than the file
 * being included: it requires db.php, which opens a connection at include
 * time and there is no database here. Brace-matched, so these are the
 * shipped functions and not a copy.
 */
function extract_fn($src, $name) {
	$at = strpos($src, 'function ' . $name . '(');
	if ($at === false) return '';
	$open = strpos($src, '{', $at);
	$d = 0;
	for ($i = $open, $n = strlen($src); $i < $n; $i++) {
		if ($src[$i] === '{') $d++;
		elseif ($src[$i] === '}') { $d--; if ($d === 0) return substr($src, $at, $i - $at + 1); }
	}
	return '';
}
$libsrc = file_get_contents(__DIR__ . '/realms-lib.php');
foreach (array('realm_con_names', 'realm_con_icon', 'realm_location_effects',
               'realm_annotate_random_reward') as $fn) {
	$b = extract_fn($libsrc, $fn);
	if ($b === '') { echo "  FAIL  $fn() not found in realms-lib.php\n"; exit(1); }
	eval($b);
}

/* ---------- 1. the three boost tables agree --------------------------------- */
echo "the success table, three copies\n";
$db  = file_get_contents(__DIR__ . '/db.php');
$js  = file_get_contents(__DIR__ . '/skulliance.js');
$lib = $libsrc;

preg_match('/\$boost_map\s*=\s*array\(([^)]*)\)/', $db,  $m1);   // getLocationSuccessRateBoost
preg_match('/\$boost_map\s*=\s*array\(([^)]*)\)/', $lib, $m2);   // realm_location_effects
preg_match('/var boostMap = \{([^}]*)\}/',         $js,  $m3);   // _updateLocationStatusLabels
$norm = function ($s) { return preg_replace('/\s|=>|:/', '', (string)$s); };
printf("  db.php  %s\n  lib     %s\n  js      %s\n",
	isset($m1[1]) ? trim($m1[1]) : '(none)',
	isset($m2[1]) ? trim($m2[1]) : '(none)',
	isset($m3[1]) ? trim($m3[1]) : '(none)');
ok(!empty($m1[1]) && !empty($m2[1]) && !empty($m3[1]), 'one of the three boost tables is gone');
ok($norm($m1[1]) === $norm($m2[1]),
   'realms-lib.php disagrees with getLocationSuccessRateBoost() -- the card would '
 . 'print a success figure the raid does not use');
ok($norm($m2[1]) === $norm($m3[1]),
   'skulliance.js disagrees with realms-lib.php -- the figure would change the moment '
 . 'you equipped something, without the location changing');

/* ---------- 2. what the labels claim ---------------------------------------- */
echo "\nthe labels say what the code does\n";
$e = realm_location_effects(array(1 => 1, 2 => 1, 5 => 1, 6 => 1));
printf("  all four success items + FF + DR -> +%d%%, %s\n",
	$e['success'], implode(', ', $e['notes']));
ok($e['success'] === 7, 'success adds 4+3 and caps at 10; got ' . $e['success']);
ok(realm_location_effects(array(1=>1,2=>1,3=>1,4=>1))['success'] === 10,
   'all four success items should cap at 10');
ok(in_array('shields one raid hit', $e['notes'], true),
   'consumable 6 is labelled as a reward multiplier. On a LOCATION it is a shield -- '
 . 'startRaid() spends it through hasDoubleRewardsShield() to absorb a hit, and the '
 . 'location keeps its level and its other items');
ok(in_array('half-time upgrades', $e['notes'], true), 'Fast Forward is not described');

/* ---------- 3. the panel renders every hook the JS needs -------------------- */
echo "\nthe hooks the JS reaches for\n";
$mk = function ($id, $name, $type, $level, $eq, $run, $quote) {
	return array('id' => $id, 'name' => $name, 'type' => $type, 'description' => 'x',
	             'level' => $level, 'equipped' => $eq,
	             'effects' => realm_location_effects($eq),
	             'running' => $run, 'quote' => $quote);
};
$rl_panel = array(
	'realm_id' => 1, 'consumables' => realm_con_names(), 'ceiling' => 10,
	'inventory' => array(1=>2,2=>0,3=>5,4=>1,5=>3,6=>0,7=>4),
	'boosts' => array('offense' => 7, 'defense' => 4),
	'rows' => array(
		$mk(1, 'portal',   'transport', 21, array(1=>1,7=>1), null,
		    array('ok'=>false,'at_ceiling'=>true,'duration'=>0,'cost'=>0,'currency'=>'','why'=>'Already at the upgrade ceiling.')),
		$mk(2, 'armory',   'offense', 3, array(3=>1,5=>1), null,
		    array('ok'=>true,'at_ceiling'=>false,'duration'=>4,'cost'=>400,'currency'=>'STAR','why'=>'')),
		$mk(4, 'barracks', 'offense', 5, array(), array('target'=>6,'days'=>6,'deadline'=>time()+300000), null),
		$mk(6, 'crypt',    'offense', 5, array(4=>1), null,
		    array('ok'=>false,'at_ceiling'=>false,'duration'=>6,'cost'=>600,'currency'=>'DREAD','why'=>'Need 240 more DREAD.')),
	),
);
/* The same pass realm_location_panel() makes, so the fixture is annotated
   the way a real panel is rather than the harness quietly testing a shape
   the page never renders -- which is how this check first passed. */
realm_annotate_random_reward($rl_panel['rows']);
$rl_guide = true;
ob_start(); include __DIR__ . '/realms-locations.php'; $html = ob_get_clean();
ok(strlen($html) > 1500, 'the partial rendered almost nothing (' . strlen($html) . ' bytes)');

$hooks = array(
	'id="loc-row-2"', 'id="loc-sub-2"', 'id="loc-upgrade-2"', 'id="loc-consumables-2"',
	'id="loc-con-2-3"', 'id="loc-inv-2-1"', 'id="inv-qty-5"', 'id="stock-btn-2"',
	'id="stock-all-btn"', 'id="upgrade-button-2"', 'id="upgrade-message-6"',
	'id="points-button-6"', 'class="countdown"', 'data-deadline',
	'applyLocationConsumable(', 'removeLocationConsumable(', 'stockLocation(',
	'stockAllLocations()', 'openLocationModal(', 'upgradeRealmLocation(',
	'pointsOption(', 'deactivateRealm(', 'openGuideModal()', 'openInventoryInfoModal()',
);
$missing = array();
foreach ($hooks as $h) if (strpos($html, $h) === false) $missing[] = $h;
printf("  %d hooks checked, %d missing\n", count($hooks), count($missing));
ok(!$missing, 'the panel no longer emits: ' . implode(', ', $missing)
            . ' -- getElementById() returns null and that feature stops working silently');

/* ---------- 4. the ceiling, in the markup ----------------------------------- */
echo "\nat the ceiling\n";
preg_match('~<div class="rl-loc" id="loc-row-1">(.*?)<div class="rl-loc" id="loc-row-2">~s', $html, $pm);
$portal = isset($pm[1]) ? $pm[1] : '';
ok($portal !== '', 'could not isolate the ceiling row');
ok(strpos($portal, 'upgradeRealmLocation(') === false,
   'a location past the ceiling is still offered an upgrade -- it can only cost the '
 . 'player, which is the whole reason the button was removed');
ok(stripos($portal, 'Maintain') === false, 'the Maintain button is back');
ok(strpos($portal, 'Past the ceiling') !== false,
   'the ceiling row does not say why there is no button');
ok(strpos($portal, 'rl-loc-lv over') !== false,
   'a level past the ceiling is not marked, so it reads like any other');
/* And the row below it still sells. */
ok(strpos($html, 'Upgrade to Lv4') !== false, 'a location under the ceiling lost its offer');

/* ---------- 5. random reward is annotated, not asserted --------------------- */
echo "\nrandom reward\n";
ok(strpos($html, 'random reward (needs every offense location)') !== false
   || strpos($html, 'random reward armed') !== false,
   'consumable 7 is shown as a flat active effect. hasRandomReward() returns false '
 . 'unless EVERY location of that side carries one, so a lone copy does nothing');
printf("  %s\n", strpos($html, 'needs every') !== false
	? 'a lone copy is marked as not yet doing anything' : 'armed');

/* Directly, both ways round. */
$mkrr = function ($ids_with_7) use ($mk) {
	$rows = array();
	foreach (array(1,2,4,6) as $lid) {
		$eq = in_array($lid, $ids_with_7, true) ? array(7 => 1) : array();
		$rows[] = $mk($lid, 'x', 'offense', 1, $eq, null, null);
	}
	realm_annotate_random_reward($rows);
	return $rows;
};
$partial = $mkrr(array(1));
$full    = $mkrr(array(1, 2, 4, 6));
ok(in_array('random reward (needs every offense location)', $partial[0]['effects']['notes'], true),
   'one offense location with a Random Reward should say it is not doing anything yet');
ok(in_array('random reward armed', $full[0]['effects']['notes'], true),
   'a fully covered offense side should read as armed, got: '
 . implode(', ', $full[0]['effects']['notes']));

/* ---------- 6. nothing rounded, nothing duplicated -------------------------- */
echo "\nstyle\n";
$page = file_get_contents(__DIR__ . '/realms.php');
preg_match_all('/\.rl-[a-z-]+\s*\{[^}]*border-radius:\s*([0-9]+)px/', $page, $rm);
$rounded = array_values(array_filter($rm[1], function ($v) { return (int)$v > 0; }));
ok(!$rounded, 'the panel has rounded corners again: ' . implode(', ', $rounded) . 'px');
ok(strpos($html, 'loc-status-tag') === false,
   'the name-tag strip is back -- it repeated the seven slots directly below it');

echo "\n" . ($fail ? "FAILED: $fail check(s)\n" : "all realms panel checks passed\n");
exit($fail ? 1 : 0);
