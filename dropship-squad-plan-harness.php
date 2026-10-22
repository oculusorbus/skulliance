<?php
/*
 * dropship-squad-plan-harness.php -- the planner really does find the most.
 *
 * The whole point of the planner is that it is NOT greedy: a plan that
 * leaves four soldiers over which could have formed a fifth squad is worse
 * than useless, because a player cannot tell. So the claim being tested is
 * an optimality claim, and the only honest way to test it is against an
 * oracle that tries everything.
 *
 * EXHAUSTIVE SEARCH IS THE ORACLE. It is exponential and could never run on
 * a dashboard; here it runs on rosters small enough to finish, and the
 * planner must match it every single time. A single roster where the oracle
 * finds more squads is a bug, and the harness prints that roster.
 */
require __DIR__ . '/dropship/dropship-odds.php';
require __DIR__ . '/dropship/dropship-squad-plan.php';

$fail = 0;
function ok($c, $m) { global $fail; if ($c) { echo "  ok    $m\n"; } else { echo "  FAIL  $m\n"; $fail++; } }

$TIERS = dropshipArmorTiers();
$GEARS = array_merge(dropshipPlanItems(), array('None'));

/** Is this actually a complete squad? Four tiers, all three items. */
function plan_is_complete($squad) {
	if (count($squad) !== 4) return false;
	$t = array(); $g = array();
	foreach ($squad as $s) {
		if ($s === null) return false;
		$t[(string)$s['armor']] = true;
		$g[(string)$s['gear']] = true;
	}
	if (count($t) !== 4) return false;
	foreach (dropshipPlanItems() as $i) if (!isset($g[$i])) return false;
	return true;
}

/** The oracle: the true maximum, by trying every group of four. */
function plan_brute($roster) {
	$best = 0;
	$rec = function ($avail) use (&$rec, &$best) {
		$n = count($avail);
		$got = 0;
		for ($a = 0; $a < $n; $a++)
		for ($b = $a + 1; $b < $n; $b++)
		for ($c = $b + 1; $c < $n; $c++)
		for ($d = $c + 1; $d < $n; $d++) {
			$squad = array($avail[$a], $avail[$b], $avail[$c], $avail[$d]);
			if (!plan_is_complete($squad)) continue;
			$rest = array();
			foreach ($avail as $i => $r) if ($i !== $a && $i !== $b && $i !== $c && $i !== $d) $rest[] = $r;
			$got = max($got, 1 + $rec($rest));
		}
		return $got;
	};
	return $rec(array_values($roster));
}

echo "the planner matches an exhaustive search\n";
mt_srand(20261021);
$checked = 0; $mismatch = null; $nonzero = 0;
for ($trial = 0; $trial < 3000 && $mismatch === null; $trial++) {
	$size = mt_rand(4, 11);
	$roster = array();
	for ($i = 0; $i < $size; $i++) {
		$roster[] = array('id' => $i,
		                  'armor' => $TIERS[mt_rand(0, 3)],
		                  'gear'  => $GEARS[mt_rand(0, 3)]);
	}
	$plan  = dropshipSquadPlan($roster);
	$truth = plan_brute($roster);
	$checked++;
	if ($truth > 0) $nonzero++;
	if ($plan['count'] !== $truth) {
		$mismatch = array('roster' => $roster, 'plan' => $plan['count'], 'truth' => $truth);
	}
}
ok($mismatch === null, $mismatch === null
	? "$checked random rosters, $nonzero of them buildable, planner equals the true maximum every time"
	: 'planner found ' . $mismatch['plan'] . ' squads where ' . $mismatch['truth']
	  . ' exist: ' . json_encode(array_map(function ($r) { return $r['armor'] . '/' . $r['gear']; }, $mismatch['roster'])));

echo "\nthe squads it returns are real\n";
mt_srand(7);
$bad_squad = null; $reused = null; $foreign = null;
for ($trial = 0; $trial < 1500; $trial++) {
	$size = mt_rand(4, 14);
	$roster = array();
	for ($i = 0; $i < $size; $i++) {
		$roster[] = array('id' => $i, 'armor' => $TIERS[mt_rand(0, 3)], 'gear' => $GEARS[mt_rand(0, 3)]);
	}
	$plan = dropshipSquadPlan($roster);
	$seen = array();
	foreach ($plan['squads'] as $sq) {
		if (!plan_is_complete($sq) && $bad_squad === null) $bad_squad = $sq;
		foreach ($sq as $s) {
			if ($s === null) continue;
			if (isset($seen[$s['id']]) && $reused === null) $reused = $s['id'];
			$seen[$s['id']] = true;
		}
	}
	foreach ($plan['leftover'] as $s) {
		if (isset($seen[$s['id']]) && $reused === null) $reused = $s['id'];
		$seen[$s['id']] = true;
	}
	if (count($seen) !== $size && $foreign === null) $foreign = array($size, count($seen));
}
ok($bad_squad === null, 'a returned squad is not actually complete');
ok($reused === null,    'a soldier appears in two squads, or in a squad and the leftovers');
ok($foreign === null,   'the plan loses or invents soldiers: ' . json_encode($foreign));

echo "\nthe cases a player would hit\n";
$mk = function ($spec) {
	$out = array(); $i = 0;
	foreach ($spec as $s) { list($a, $g, $c) = $s;
		for ($j = 0; $j < $c; $j++) $out[] = array('id' => $i++, 'armor' => $a, 'gear' => $g); }
	return $out;
};
/* Every item sits on Heavy: a squad holds one Heavy, so nothing completes. */
$trap = $mk(array(array('Heavy','Medkit',1), array('Heavy','Melee',1), array('Heavy','Demolition',1),
                  array('Medium','None',3), array('Light','None',3), array('Base','None',3)));
$p = dropshipSquadPlan($trap);
ok($p['count'] === 0, 'three items all on Heavy still build a squad, which is impossible: got ' . $p['count']);
ok($p['count'] === plan_brute($trap), 'and the oracle disagrees');

/* Spread the same three items across three tiers and it builds. */
$good = $mk(array(array('Heavy','Medkit',1), array('Medium','Melee',1), array('Light','Demolition',1),
                  array('Base','None',1)));
$p = dropshipSquadPlan($good);
ok($p['count'] === 1, 'the textbook squad does not build: got ' . $p['count']);
ok(plan_is_complete($p['squads'][0]), 'and it is not complete');

/* The fourth slot takes None over a spare item. */
$spare = $mk(array(array('Heavy','Medkit',1), array('Medium','Melee',1), array('Light','Demolition',1),
                   array('Base','Medkit',1), array('Base','None',1)));
$p = dropshipSquadPlan($spare);
$base_gear = '';
foreach ($p['squads'][0] as $s) if ($s['armor'] === 'Base') $base_gear = $s['gear'];
ok($base_gear === 'None', "the free slot took a duplicate Medkit instead of None (got $base_gear)");

echo "\nwhat it says is missing\n";
mt_srand(99);
$need_wrong = null;
for ($trial = 0; $trial < 600 && $need_wrong === null; $trial++) {
	$size = mt_rand(3, 10);
	$roster = array();
	for ($i = 0; $i < $size; $i++) {
		$roster[] = array('id' => $i, 'armor' => $TIERS[mt_rand(0, 3)], 'gear' => $GEARS[mt_rand(0, 3)]);
	}
	$plan = dropshipSquadPlan($roster);
	if ($plan['need'] === null) continue;
	$roster[] = array('id' => 999, 'armor' => $plan['need']['armor'], 'gear' => $plan['need']['gear']);
	$after = dropshipSquadPlan($roster);
	if ($after['count'] !== $plan['count'] + 1) {
		$need_wrong = array($plan['need'], $plan['count'], $after['count']);
	}
}
ok($need_wrong === null,
   'the soldier it says would buy another squad does not: ' . json_encode($need_wrong));

echo "\nplayers who already have squads\n";

/* A squad that is already optimal is left alone. */
$already = $mk(array(array('Heavy','Medkit',1), array('Medium','Melee',1),
                     array('Light','Demolition',1), array('Base','None',1)));
$d = dropshipSquadPlanDiff($already, array(7 => $already));
ok($d['gain'] === 0,         'a player who is already optimal is told to change something');
ok($d['disperse'] === array(), 'and their complete squad is broken up for nothing');
ok($d['keep'] === array(7),  'and it is not kept');

/* A complete squad that is IN THE WAY. The one Base None is inside it,
   while two more squads are waiting on exactly that soldier. */
$block = $mk(array(
	array('Heavy','Medkit',1), array('Medium','Melee',1), array('Light','Demolition',1), array('Base','None',1),
	array('Heavy','Melee',1), array('Medium','Demolition',1), array('Light','Medkit',1), array('Base','Melee',1),
));
$held = array_slice($block, 0, 4);
$d = dropshipSquadPlanDiff($block, array(1 => $held));
ok($d['best'] === 2, 'the roster really holds two squads, planner says ' . $d['best']);
ok(count($d['keep']) + count($d['build']) === $d['best'],
   'kept plus rebuilt does not reach the maximum: ' . count($d['keep']) . ' + ' . count($d['build'])
   . ' vs ' . $d['best']);

/* Incomplete squads are always worth dispersing. */
$halfbuilt = $mk(array(array('Heavy','Medkit',1), array('Medium','Melee',1),
                       array('Light','Demolition',1), array('Base','None',1)));
$d = dropshipSquadPlanDiff($halfbuilt, array(3 => array_slice($halfbuilt, 0, 2)));
ok($d['disperse'] === array(3), 'a half-built squad is not dispersed');
ok(count($d['build']) === 1,    'and its soldiers are not reused to complete a squad');

/* Random rosters, random existing squads: the invariant has to hold. */
mt_srand(4242);
$bad = null;
for ($trial = 0; $trial < 1200 && $bad === null; $trial++) {
	$size = mt_rand(4, 13);
	$roster = array();
	for ($i = 0; $i < $size; $i++) {
		$roster[] = array('id' => $i, 'armor' => $TIERS[mt_rand(0, 3)], 'gear' => $GEARS[mt_rand(0, 3)]);
	}
	/* Carve some of them into arbitrary "saved squads" of four. */
	$shuffled = $roster; shuffle($shuffled);
	$current = array(); $sid = 1;
	while (count($shuffled) >= 4 && mt_rand(0, 1)) {
		$current[$sid++] = array_splice($shuffled, 0, 4);
	}
	$d = dropshipSquadPlanDiff($roster, $current);
	$truth = plan_brute($roster);

	if ($d['best'] !== $truth) { $bad = "best {$d['best']} vs oracle $truth"; break; }
	if (count($d['keep']) + count($d['build']) !== $truth) {
		$bad = 'keep ' . count($d['keep']) . ' + build ' . count($d['build']) . ' != ' . $truth; break;
	}
	foreach ($d['keep'] as $kid) {
		if (!dropshipPlanIsComplete($current[$kid])) { $bad = "kept squad $kid is not complete"; break 2; }
	}
	/* Nobody in two places: kept squads, new squads and leftovers are disjoint. */
	$seen = array();
	foreach ($d['keep'] as $kid) foreach ($current[$kid] as $m) {
		if (isset($seen[$m['id']])) { $bad = 'soldier ' . $m['id'] . ' is in two places'; break 3; }
		$seen[$m['id']] = true;
	}
	foreach ($d['build'] as $sq) foreach ($sq as $m) {
		if ($m === null || isset($seen[$m['id']])) { $bad = 'a rebuilt squad reuses a kept soldier'; break 3; }
		$seen[$m['id']] = true;
	}
	foreach ($d['build'] as $sq) if (!plan_is_complete($sq)) { $bad = 'a rebuilt squad is incomplete'; break 2; }
}
ok($bad === null, 'the diff breaks its own invariant: ' . $bad);

echo $fail ? "\n$fail check(s) FAILED\n" : "\nall good\n";
