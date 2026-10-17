<?php
/**
 * dhcarena-perf-harness.php -- the proven-performance arithmetic.
 *
 * Drives dhca_performance_score() and dhca_performance_pool() directly, with
 * no database: both take arrays, which is why they are split out from
 * dhca_performance()'s query.
 *
 * Every fixture here is ASYMMETRIC on purpose. A Fighter with 100 damage and
 * 50% survival and one with 50 damage and 100% survival both score 50 on
 * `real`, so a fixture built from those two cannot tell `dmg*live` from
 * `live*dmg` or from either factor alone. The pairs below differ on every
 * axis, so a mutation to one axis cannot be absorbed by another.
 */
require_once __DIR__ . '/dhcarena-lib.php';
require_once __DIR__ . '/dhc-ranks.php';   // dhcf_rank_of(), the same ranker the claimed axes use

$fails = 0;
function ok($cond, $what) {
	global $fails;
	if (!$cond) { $fails++; echo "FAIL  $what\n"; } else echo "ok    $what\n";
}
function raw($battles, $wins, $survived, $dealt) {
	return array('battles'=>$battles,'wins'=>$wins,'survived'=>$survived,'dealt'=>$dealt);
}
function sc($all, $attack = null, $defend = null) {
	return dhca_performance_score(array(
		'all'    => $all,
		'attack' => $attack ?: raw(0,0,0,0),
		'defend' => $defend ?: raw(0,0,0,0),
	));
}

echo "-- the denominator is battles, not wins or anything else --\n";
/* 8 battles, 5 wins, 6 survived, 800 dealt. Every tally is distinct, so an
   axis reading the wrong column lands on a different number. */
$s = sc(raw(8, 5, 6, 800));
ok($s['all']['winpct'] === 63, 'winpct = 5/8 = 63, not 5/6 or 6/8 (got ' . $s['all']['winpct'] . ')');
ok($s['all']['live']   === 75, 'live   = 6/8 = 75 (got ' . $s['all']['live'] . ')');
ok($s['all']['dmg']    === 100, 'dmg    = 800/8 = 100 (got ' . $s['all']['dmg'] . ')');
ok($s['all']['real']   === 75, 'real   = 100 * 75% = 75 (got ' . $s['all']['real'] . ')');

echo "\n-- real is the PRODUCT: neither factor alone reproduces it --\n";
/* 200 dmg at 40% survival = 80. Both factors differ from the result and from
   each other, and 80 is not 200, not 40, and not their sum or mean. */
$g = sc(raw(10, 5, 4, 2000));
ok($g['all']['dmg']  === 200, 'glass cannon hits for 200');
ok($g['all']['live'] === 40,  'and survives 40%');
ok($g['all']['real'] === 80,  'real = 80, not 200, 40, 240 or 120 (got ' . $g['all']['real'] . ')');
/* The ordering claim the axis exists to make. */
$w = sc(raw(10, 5, 10, 1000));   // 100 dmg, 100% survival -> 100
ok($w['all']['dmg'] < $g['all']['dmg'], 'the workhorse hits softer than the cannon');
ok($w['all']['real'] > $g['all']['real'],
   'but out-ranks it on real (100 > 80) -- the discount actually reorders');

echo "\n-- under DHCA_PROFILE_MIN a Fighter is unproven, and stays unranked --\n";
$few = sc(raw(DHCA_PROFILE_MIN - 1, 4, 4, 4000));
ok($few['all']['proven'] === false, (DHCA_PROFILE_MIN - 1) . ' battles is not proven');
ok($few['all']['dmg'] === 1000, 'its totals are still computed (1000 dmg/battle)');
$min = sc(raw(DHCA_PROFILE_MIN, 1, 1, 10));
ok($min['all']['proven'] === true, DHCA_PROFILE_MIN . ' battles is proven -- the bound is >=, not >');

echo "\n-- an empty record divides by nothing --\n";
$none = sc(raw(0,0,0,0));
ok($none['all']['dmg'] === 0 && $none['all']['live'] === 0 && $none['all']['real'] === 0,
   'zero battles gives zeroes, not a division warning');

echo "\n-- edge needs BOTH sides proven, and reads the gap's sign --\n";
$lop = sc(raw(10,5,5,100), raw(9,5,5,90), raw(1,0,0,10));
ok($lop['edge'] === '', 'nine attacks and one defence says nothing about defending');
/* Attacker: 80% attacking, 20% defending -- a 60 point gap. */
$atk = sc(raw(10,5,5,100), raw(5,4,5,50), raw(5,1,5,50));
ok($atk['attack']['winpct'] === 80 && $atk['defend']['winpct'] === 20, 'fixture is 80/20');
ok($atk['edge'] === 'attack', 'a 60 point gap toward attack reads attack (got "' . $atk['edge'] . '")');
$def = sc(raw(10,5,5,100), raw(5,1,5,50), raw(5,4,5,50));
ok($def['edge'] === 'defend', 'the SAME gap reversed reads defend, so the sign is not dropped');
/* Just inside the threshold: 60% vs 50% is a 10 point gap, under 15. */
$even = sc(raw(10,6,10,100), raw(5,3,5,50), raw(5,2,5,50));
ok($even['attack']['winpct'] - $even['defend']['winpct'] === 20, 'fixture gap is 20');
ok($even['edge'] === 'attack', '20 >= 15 is a specialist');
$flat = sc(raw(20,10,20,200), raw(10,5,10,100), raw(10,5,10,100));
ok($flat['edge'] === 'even', 'an equal record on both sides is even, not a specialist');

echo "\n-- the pool excludes the unproven and counts only the measured --\n";
$perf = array(
	7  => sc(raw(10, 5, 10, 1000)),               // proven: dmg 100, live 100, real 100
	8  => sc(raw(10, 5,  4, 2000)),               // proven: dmg 200, live  40, real  80
	9  => sc(raw(10, 5,  7,  300)),               // proven: dmg  30, live  70, real  21
	99 => sc(raw(DHCA_PROFILE_MIN - 1, 1, 1, 9999)),  // unproven, enormous dmg
);
$pool = dhca_performance_pool($perf);
ok($pool['_n'] === 3, '_n counts the 3 proven, not all 4 (got ' . $pool['_n'] . ')');
ok(!in_array(9999 / (DHCA_PROFILE_MIN - 1), $pool['dmg']),
   "the unproven Fighter's huge damage is absent from the pool");
ok($pool['dmg'] === array(200, 100, 30), 'dmg pool is sorted high to low: ' . implode(',', $pool['dmg']));
ok($pool['real'] === array(100, 80, 21), 'real pool is its own ordering, not dmg\'s: ' . implode(',', $pool['real']));
ok($pool['dmg'] !== $pool['real'], 'the two axes genuinely disagree in this fixture');

echo "\n-- a rank off these pools is #k of the measured population --\n";
ok(dhcf_rank_of($pool['dmg'],  200) === 1, 'the cannon is #1 on dmg');
ok(dhcf_rank_of($pool['real'], 200) === 1, 'and off the top of real');
ok(dhcf_rank_of($pool['real'], 100) === 1, 'the workhorse is #1 on real');
ok(dhcf_rank_of($pool['dmg'],  100) === 2, 'but only #2 on dmg -- the claim and the evidence diverge');

echo "\n-- axis metadata lines up with what the pool carries --\n";
$ax = dhca_proven_axes();
foreach ($ax as $k => $meta) {
	ok(isset($pool[$k]), "pool carries the '$k' axis");
	ok(in_array($meta['claim'], array_keys(dhcf_rank_axes())),
	   "'$k' tests the real claim '" . $meta['claim'] . "'");
}

echo "\n" . ($fails ? "$fails FAILED\n" : "all passed\n");
exit($fails ? 1 : 0);
