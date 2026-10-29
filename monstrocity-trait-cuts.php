<?php
/**
 * monstrocity-trait-cuts.php — the band thresholds for a mapped collection. CLI only.
 *
 *   php monstrocity-trait-cuts.php <policy> <traits.json>
 *
 * WHY THIS EXISTS RATHER THAN A HAND-PICKED SET OF NUMBERS. The cuts in
 * lib/monstrocity-trait-maps.php decide how a rarity score becomes a 1-8
 * strength, and they only produce an even spread if they are computed from
 * the scores the SHIPPED scorer actually returns. The first set was worked
 * out in a different language against the same formula and disagreed with
 * PHP at the boundaries badly enough to put a quarter of the collection in
 * one speed band -- the formula was identical and the arithmetic was not.
 *
 * So the cuts come from the real functions, over the real collection, and
 * are pasted back into the map. Re-run it after changing any weight.
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
error_reporting(E_ALL & ~E_DEPRECATED);
require_once __DIR__ . '/lib/monstrocity-trait-maps.php';

$policy = isset($argv[1]) ? trim($argv[1]) : '';
$file   = isset($argv[2]) ? $argv[2] : '';
$map    = monstrocity_trait_map($policy);
if (!$map || !is_file($file)) {
	fwrite(STDERR, "usage: php monstrocity-trait-cuts.php <mapped policy> <traits.json>\n");
	exit(2);
}
$census = monstrocity_trait_census($map['slug']);
if (!$census) { fwrite(STDERR, "no census for {$map['slug']}\n"); exit(1); }

$all = json_decode((string)file_get_contents($file), true);
if (!is_array($all)) { fwrite(STDERR, "unreadable traits file\n"); exit(1); }

$S = array(); $T = array(); $P = array();
foreach ($all as $row) {
	$t = isset($row['traits']) ? $row['traits'] : $row;
	if (!is_array($t) || !$t) continue;
	$g = function ($s) use ($t) { return isset($t[$s]) && is_scalar($t[$s]) ? (string)$t[$s] : ''; };
	$a = 0.0; foreach ($map['arms'] as $s) $a += monstrocity_trait_rarity($census, $s, $g($s));
	$m = 0.0; foreach ($map['mind'] as $s) $m += monstrocity_trait_rarity($census, $s, $g($s));
	$sp = $g($map['sizeBy']);
	$v = isset($map['speed0'][$sp]) ? $map['speed0'][$sp] : $map['speedDefault'];
	foreach ($map['weight'] as $s => $w) if ($g($s) !== '') $v -= $w;
	$S[] = $a; $T[] = $m; $P[] = $v;
}
/* Even bands by COUNT, so every value of the stat is used. */
$cut = function ($vals, $k) {
	sort($vals, SORT_NUMERIC);
	$n = count($vals); $out = array();
	for ($i = 1; $i < $k; $i++) $out[] = $vals[(int)round($i * $n / $k)];
	return $out;
};
printf("'strength' => array(%s),\n", implode(', ', array_map(function ($x) { return round($x, 4); }, $cut($S, 8))));
printf("'tactics'  => array(%s),\n", implode(', ', array_map(function ($x) { return round($x, 4); }, $cut($T, 7))));
printf("'speed'    => array(%s),\n", implode(', ', $cut($P, 7)));
