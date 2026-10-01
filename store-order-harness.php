<?php
/* store-order-harness.php — CLI only. No database.
 *
 * Claimed items used to sit wherever the catalogue put them, scattered
 * through the ones you can still buy. A claimed card is dimmed and its buy
 * buttons are disabled, so each one mid-list is a gap in the thing the
 * shopper is actually reading.
 *
 * The fix is a STABLE partition, and stable is the whole point: sorting
 * would be one line and would also throw away getItemsData()'s
 * "featured DESC, project, name" ordering, which is why this is tested
 * rather than eyeballed.
 *
 * Usage: php store-order-harness.php
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$fail = 0;
function ok($cond, $what) { global $fail; if (!$cond) { $fail++; echo "  FAIL  $what\n"; } }

/* The real block, lifted out of store.php. */
$src = file_get_contents(__DIR__ . '/store.php');
$at  = strpos($src, 'if ($st_owned) {');
ok($at !== false, 'the claimed-last partition is gone from store.php');
$end = strpos($src, "\n}\n", $at);
$block = substr($src, $at, $end - $at + 3);

function order(array $items) {
	$st_owned = 0;
	foreach ($items as $i) if (!empty($i['owned'])) $st_owned++;
	$st_items = $items;
	eval($GLOBALS['block']);
	return array_map(function ($i) { return $i['id'] . (empty($i['owned']) ? '' : '*'); }, $st_items);
}

/* '*' marks claimed. Catalogue order is a..h. */
$catalogue = array(
	array('id'=>'a', 'owned'=>false), array('id'=>'b', 'owned'=>true),
	array('id'=>'c', 'owned'=>false), array('id'=>'d', 'owned'=>true),
	array('id'=>'e', 'owned'=>false), array('id'=>'f', 'owned'=>false),
);

echo "claimed items go last\n";
$got = order($catalogue);
printf("  in:  a b* c d* e f\n  out: %s\n", implode(' ', $got));
ok(implode(' ', $got) === 'a c e f b* d*',
   'expected "a c e f b* d*", got "' . implode(' ', $got) . '"');

echo "\nand the catalogue order survives inside each half\n";
/* Why a partition and not a sort: PHP's sorts are stable from 8.0, so a
   usort would pass every check below -- verified, that mutation is green.
   The reason is the SERVER: ~20 PHP builds are available and nothing pins
   which one serves the page, and on 7.x an unstable sort scatters the
   catalogue order inside each half. So the shape is asserted too. */
ok(strpos($block, 'usort') === false && strpos($block, 'uasort') === false,
   'the partition became a sort; that is fine on PHP 8 and not on 7.x, and '
 . 'nothing here pins the interpreter');
$unclaimed = array_values(array_filter($got, function ($x) { return substr($x, -1) !== '*'; }));
$claimed   = array_values(array_filter($got, function ($x) { return substr($x, -1) === '*'; }));
ok($unclaimed === array('a','c','e','f'), 'the unclaimed items were reordered');
ok($claimed   === array('b*','d*'),       'the claimed items were reordered among themselves');

echo "\nthe edges\n";
ok(order(array()) === array(), 'an empty store throws');
$allOpen = array(array('id'=>'a','owned'=>false), array('id'=>'b','owned'=>false));
ok(order($allOpen) === array('a','b'), 'a store with nothing claimed got reordered');
$allDone = array(array('id'=>'a','owned'=>true), array('id'=>'b','owned'=>true));
ok(order($allDone) === array('a*','b*'), 'a fully claimed store got reordered');
/* The count drives the branch; it must not go stale. */
ok(strpos($src, 'if ($st_owned) {') < strpos($src, '$st_items = array_merge($st_open, $st_done);'),
   'the partition no longer sits behind the $st_owned count that gates it');

/* And it has to happen BEFORE the cards are drawn. */
ok(strpos($src, '$st_items = array_merge($st_open, $st_done);')
   < strpos($src, 'foreach ($st_items as $row):'),
   'the partition runs after the card loop, so it changes nothing');

echo "\n";
if ($fail) { echo "$fail check(s) FAILED\n"; exit(1); }
echo "all store ordering checks passed\n";
