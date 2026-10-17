<?php
/*
 * Brace-extracts the Collection's own perf filter, proven sort and
 * claim-re-rank blocks out of dhcgallery.php and runs them on fixtures.
 * Nothing is reimplemented: a mutation to the page must break this.
 */
require_once __DIR__ . '/dhcarena-lib.php';
require_once __DIR__ . '/dhc-ranks.php';
$src = file_get_contents(__DIR__ . '/dhcgallery.php');
$fails = 0;
function ok($c, $w) { global $fails; if (!$c) { $fails++; echo "FAIL  $w\n"; } else echo "ok    $w\n"; }

/* ---- lift the three blocks verbatim ---- */
function lift($src, $start, $endmark) {
	$i = strpos($src, $start);
	if ($i === false) { echo "FAIL  cannot find block: $start\n"; exit(2); }
	$j = strpos($src, $endmark, $i);
	if ($j === false) { echo "FAIL  cannot find end: $endmark\n"; exit(2); }
	return substr($src, $i, $j - $i + strlen($endmark));
}
$filt = lift($src, '$dhcg_rows  = array_values(array_filter($dhcg_all,', '}));');
$sort = lift($src, "\$dhcg_paxis = array('preal'", "\t});\n}");
/* The re-rank itself moved into dhcarena-lib.php so ajax/dhc-fighter.php can
   share it. What is lifted here is that the PAGE still routes through it -- a
   page that quietly grew its own copy again would pass every arithmetic check
   below while shipping a second definition of the same gap. */
$wire = lift($src, '$dhcg_known = array();', "!empty(\$r['perf']['all']['proven']));");
ok(strpos($filt, 'dhcg_perf_f') !== false,  'lifted filter mentions the perf filter');
ok(strpos($sort, "'preal'") !== false,      'lifted sort carries the proven axes');
ok(strpos($wire, 'dhca_claim_pool(') !== false,  'the page builds its claim pool through the lib');
ok(strpos($wire, 'dhca_claim_ranks(') !== false, 'and ranks through the lib');
ok(strpos($wire, 'dhcf_rank_of(') === false,     'and keeps no second copy of the ranking itself');
/* The filter block calls two trait helpers; neutralise only those, so the
   perf clauses under test run for real. */
$filt = str_replace('if ($dhcg_tier !== \'\' && $r[\'best\'] !== $dhcg_tier) return false;', '', $filt);
$filt = str_replace('if ($dhcg_kit !== \'\' && $r[\'kit\'][\'id\'] !== $dhcg_kit) return false;', '', $filt);
$filt = str_replace('if (!dhcf_traits_include($r[\'parts\'], $dhcg_trait)) return false;', '', $filt);
/* The name survives in the block's own comment, so the check is on the CALL:
   the call site is gone and only the comment's mention is left. */
ok(substr_count($filt, 'dhcf_traits_include($r') === 0, 'the trait CALL is neutralised');
ok(substr_count($filt, 'dhcf_traits_include') === 1, '...its comment is all that remains');
ok(substr_count($filt, "dhcg_perf_f === ") === 3, 'all three perf clauses survived the strip');

/* ---- fixtures: asymmetric on every axis ---- */
function fx($id, $b, $w, $surv, $dealt, $hp, $pow, $a = null, $d = null) {
	$perf = $b ? dhca_performance_score(array(
		'all'    => array('battles'=>$b,'wins'=>$w,'survived'=>$surv,'dealt'=>$dealt),
		'attack' => $a ?: array('battles'=>0,'wins'=>0,'survived'=>0,'dealt'=>0),
		'defend' => $d ?: array('battles'=>0,'wins'=>0,'survived'=>0,'dealt'=>0),
	)) : null;
	return array('id'=>$id, 'hp'=>$hp, 'pow'=>$pow, 'might'=>$hp*$pow, 'perf'=>$perf,
	             'best'=>'common', 'kit'=>array('id'=>'k'), 'parts'=>array());
}
$zero = array('battles'=>0,'wins'=>0,'survived'=>0,'dealt'=>0);
$dhcg_all = array(
	/* 1: workhorse -- soft build, survives everything.  dmg 100 live 100 real 100 */
	fx(1, 10, 5, 10, 1000, 100, 10, array('battles'=>5,'wins'=>4,'survived'=>5,'dealt'=>500),
	                                 array('battles'=>5,'wins'=>1,'survived'=>5,'dealt'=>500)),
	/* 2: glass cannon -- huge build, dies early.        dmg 200 live  40 real  80 */
	fx(2, 10, 5,  4, 2000, 500, 90, array('battles'=>5,'wins'=>1,'survived'=>2,'dealt'=>1000),
	                                 array('battles'=>5,'wins'=>4,'survived'=>2,'dealt'=>1000)),
	/* 3: middling, and genuinely even -- 3 wins on each side.
	   At 5 battles a side the win rate moves in steps of 20 points, so 3 and 2
	   wins is a 20 point gap and DHCA_EDGE_PCT (15) calls that a specialist.
	   Even means EQUAL here.                            dmg  30 live  70 real  21 */
	fx(3, 10, 6,  7,  300, 300, 95, array('battles'=>5,'wins'=>3,'survived'=>4,'dealt'=>150),
	                                 array('battles'=>5,'wins'=>3,'survived'=>3,'dealt'=>150)),
	/* 4: unproven, but the best build in the fixture. */
	fx(4, DHCA_PROFILE_MIN - 1, 4, 4, 9999, 900, 99),
	/* 5: never fielded at all -- no ledger row. */
	fx(5, 0, 0, 0, 0, 700, 70),
);
$dhcg_perf = array(); foreach ($dhcg_all as $r) if ($r['perf']) $dhcg_perf[$r['id']] = $r['perf'];
$dhcg_ppool = dhca_performance_pool($dhcg_perf);

echo "\n-- the claim is re-ranked over the proven population only --\n";
/* Driven exactly as the page drives it -- $known in hand, so no database and
   no second implementation to keep in step. */
$dhcg_known = array();
foreach ($dhcg_all as $r) {
	$dhcg_known[(int)$r['id']] = array('tough'=>$r['hp'], 'power'=>$r['pow'], 'might'=>$r['might']);
}
$dhcg_cpool = dhca_claim_pool(null, $dhcg_perf, $dhcg_known);
foreach ($dhcg_all as $i => $r) {
	$dhcg_all[$i]['crank'] = dhca_claim_ranks(
		$dhcg_cpool,
		array('tough'=>$r['hp'], 'power'=>$r['pow'], 'might'=>$r['might']),
		!empty($r['perf']['all']['proven']));
}
$by = array(); foreach ($dhcg_all as $r) $by[$r['id']] = $r;
ok(count($dhcg_cpool['might']) === 3,
   'the pool holds the 3 proven, not all 5 (got ' . count($dhcg_cpool['might']) . ')');
ok(empty($by[4]['crank']) && empty($by[5]['crank']),
   'unproven and never-fielded Fighters get no claimed rank either');
ok($by[2]['crank']['might'] === 1, 'the cannon has the best BUILD of the three (crank might #1)');
ok($by[2]['crank']['tough'] === 1, 'and the best torso');
ok($by[1]['crank']['tough'] === 3, 'the workhorse has the worst torso of the three');
/* The finding the whole feature exists to surface. */
ok(dhcf_rank_of($dhcg_ppool['live'], $by[1]['perf']['all']['live']) === 1,
   'yet the workhorse is #1 on PROVEN toughness');
ok(dhcf_rank_of($dhcg_ppool['live'], $by[2]['perf']['all']['live']) === 3,
   'and the cannon is #3 -- claim and evidence invert, which is the point');
ok($dhcg_cpool['might'] === array(45000, 28500, 1000),
   'might pool is sorted high to low: ' . implode(',', $dhcg_cpool['might']));
/* HP AND POWER MUST RANK DIFFERENTLY IN THIS FIXTURE, or nothing here can
   tell the tough axis from the power axis. Fighter 3 has the second-best
   torso and the best weapon, so the two orderings genuinely diverge. */
ok($by[3]['crank']['tough'] === 2, 'Fighter 3 is #2 by torso');
ok($by[3]['crank']['power'] === 1, 'but #1 by weapon -- the axes read different columns');
ok($by[2]['crank']['power'] === 2, 'and the cannon is only #2 by weapon');
ok($dhcg_cpool['tough'] !== $dhcg_cpool['power'], 'the tough and power pools are not the same list');

echo "\n-- proven sorts put the measured first and leave the rest alone --\n";
foreach (array('preal' => array(1,2,3), 'pdmg' => array(2,1,3), 'plive' => array(1,3,2)) as $sk => $want) {
	$dhcg_sort = $sk; $dhcg_rows = $dhcg_all;
	eval($sort);
	$got = array(); foreach ($dhcg_rows as $r) $got[] = $r['id'];
	$head = array_slice($got, 0, 3);
	ok($head === $want, "sort=$sk ranks " . implode(',', $want) . ' (got ' . implode(',', $head) . ')');
	ok(array_slice($got, 3) === array(4, 5) || array_slice($got, 3) === array(5, 4),
	   "  ...and sort=$sk leaves both unproven Fighters at the back");
}
/* The three orderings must actually differ, or any one of them could be wrong
   and the block above would still pass. */
ok(true, 'the three expected orderings above are 1,2,3 / 2,1,3 / 1,3,2 -- all distinct');

echo "\n-- a sort the page does not define is left untouched --\n";
$dhcg_sort = 'rarest'; $dhcg_rows = $dhcg_all;
eval($sort);
$got = array(); foreach ($dhcg_rows as $r) $got[] = $r['id'];
ok($got === array(1,2,3,4,5), 'sort=rarest does not trigger a proven sort');

echo "\n-- the record filter --\n";
$dhcg_tier = ''; $dhcg_kit = ''; $dhcg_trait = '';
function run_filter($filt, $all, $pf) {
	$GLOBALS['dhcg_all'] = $all; $GLOBALS['dhcg_perf_f'] = $pf;
	extract(array('dhcg_all'=>$all, 'dhcg_perf_f'=>$pf, 'dhcg_tier'=>'', 'dhcg_kit'=>'', 'dhcg_trait'=>''));
	eval($filt);
	$ids = array(); foreach ($dhcg_rows as $r) $ids[] = $r['id'];
	sort($ids); return $ids;
}
ok(run_filter($filt, $dhcg_all, '')       === array(1,2,3,4,5), 'no filter shows all five');
ok(run_filter($filt, $dhcg_all, 'proven') === array(1,2,3),     'proven drops the unproven and the unfielded');
/* Fighter 1 wins 80% attacking, 20% defending. Fighter 2 is the mirror. */
ok($by[1]['perf']['edge'] === 'attack' && $by[2]['perf']['edge'] === 'defend',
   'fixture has one attacker and one defender');
ok(run_filter($filt, $dhcg_all, 'attack') === array(1), 'attack shows only the attacker');
ok(run_filter($filt, $dhcg_all, 'defend') === array(2), 'defend shows only the defender');
ok($by[3]['perf']['edge'] === 'even', 'the middling Fighter is even...');
ok(!in_array(3, run_filter($filt, $dhcg_all, 'attack'))
   && !in_array(3, run_filter($filt, $dhcg_all, 'defend')),
   '...so it is in neither bucket');
ok(!in_array(5, run_filter($filt, $dhcg_all, 'attack')),
   'a never-fielded Fighter (perf null) does not crash or slip through');

echo "\n-- the two pages that feed the SAME panel agree on its keys --\n";
/*
 * dhcgallery.php builds the card payload inline; ajax/dhc-fighter.php rebuilds
 * it for one serial and dhcfighters.php's roster fetches that. The panel reads
 * one set of keys, so a key added to one side and not the other shows up as a
 * strip that works from the Collection and is blank from the roster -- which
 * looks like a broken panel rather than a missing field.
 */
$ajax = file_get_contents(__DIR__ . '/ajax/dhc-fighter.php');
$modal = file_get_contents(__DIR__ . '/dhc-fighter-modal.php');
foreach (array('perf', 'edge', 'prank', 'prankOf', 'crank') as $key) {
	ok(preg_match("/'" . $key . "'\s*=>/", $src),  "dhcgallery.php sends '$key'");
	ok(preg_match("/'" . $key . "'\s*=>/", $ajax), "ajax/dhc-fighter.php sends '$key'");
	ok(strpos($modal, 'f.' . $key) !== false,       "the panel reads f.$key");
}
/* And that neither page kept its own arithmetic. */
foreach (array('dhcgallery.php' => $src, 'ajax/dhc-fighter.php' => $ajax) as $name => $code) {
	ok(strpos($code, 'dhca_claim_pool(') !== false, "$name pools the claim through the lib");
	ok(strpos($code, "* \$s[\$k]['live']") === false, "$name has no copy of the real-damage formula");
}

echo "\n" . ($fails ? "$fails FAILED\n" : "all passed\n");
exit($fails ? 1 : 0);
