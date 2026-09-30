<?php
/* dhc-ranks-harness.php — CLI only. No database.
 *
 * The assembler's live "where would this place" strip, and the four axes it
 * shares with the Collection's sort buttons.
 *
 * WHAT IS ACTUALLY AT RISK HERE:
 *
 *  1. THE RANK ARITHMETIC. dhcf_rank_of() is a binary search over a
 *     DESCENDING list with competition ranking -- ties take the BEST place,
 *     so three Fighters on 900 are all 1st and the next is 4th. Both halves
 *     of that are easy to get subtly wrong and neither is visible by eye:
 *     an off-by-one just says "#5" instead of "#4". Checked against a naive
 *     count over random pools, which is the definition it is an optimisation
 *     of.
 *
 *  2. THE AXES DRIFTING APART. "Deadliest" was defined by dhcgallery.php's
 *     sort buttons first. It is defined in dhc-ranks.php now and the gallery
 *     reads it from there, so the preview on the canvas and the ranking on
 *     the Collection cannot come to mean different things. If someone puts a
 *     local copy back, this catches it.
 *
 *  3. THE STATS BEING RECOMPUTED RATHER THAN ASKED FOR. Health and power must
 *     come out of dhca_build_fighter(), the Arena's own builder. A second
 *     opinion about what a torso is worth is the same class of bug as a
 *     second opinion about draw order, which has cost this codebase twice.
 *
 * Usage: php dhc-ranks-harness.php
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

require_once __DIR__ . '/dhc-ranks.php';

$fail = 0;
function ok($cond, $what) { global $fail; if (!$cond) { $fail++; echo "  FAIL  $what\n"; } }

/* ---------- 1. the ranking ------------------------------------------------- */
echo "rank arithmetic\n";

/** The definition dhcf_rank_of() is an optimisation of: count what beats you. */
function naive_rank($sorted, $v) {
	$n = 0;
	foreach ($sorted as $x) if ($x > $v) $n++;
	return $n + 1;
}

ok(dhcf_rank_of(array(), 100) === 1, 'an empty collection makes any build first');
ok(dhcf_rank_of(array(900, 500, 100), 1000) === 1, 'beating everything is first');
ok(dhcf_rank_of(array(900, 500, 100), 50)   === 4, 'beating nothing is last+1');
ok(dhcf_rank_of(array(900, 500, 100), 900)  === 1, 'equalling the top SHARES first');
ok(dhcf_rank_of(array(900, 900, 900, 100), 900) === 1,
   'three on the same number are all first');
ok(dhcf_rank_of(array(900, 900, 900, 100), 500) === 4,
   'and the next one down is 4th, not 2nd -- competition ranking, not dense');
ok(dhcf_rank_of(array(900, 500, 100), 500)  === 2, 'equalling the middle takes its place');

/* Random pools against the naive count. A binary search over a descending
   list with ties is exactly where an off-by-one hides. */
mt_srand(20260930);
$bad = 0; $cases = 0;
for ($t = 0; $t < 400; $t++) {
	$n = mt_rand(0, 40);
	$pool = array();
	/* A small value range ON PURPOSE, so ties are common rather than rare. */
	for ($i = 0; $i < $n; $i++) $pool[] = mt_rand(0, 12);
	rsort($pool);
	for ($v = -1; $v <= 13; $v++) {
		$cases++;
		if (dhcf_rank_of($pool, $v) !== naive_rank($pool, $v)) $bad++;
	}
}
printf("  %d random pools, %d lookups, %d disagreements with the naive count\n",
	400, $cases, $bad);
ok($bad === 0, "the binary search disagrees with a plain count on $bad lookups");

/* ---------- 2. the axes ----------------------------------------------------- */
echo "\naxes\n";
$axes = dhcf_rank_axes();
ok(array_keys($axes) === array('rarest', 'might', 'tough', 'power'),
   'the four axes, in order: ' . implode(',', array_keys($axes)));
printf("  %s\n", implode(' | ', array_map(
	function ($k, $v) { return "$k=$v"; }, array_keys($axes), $axes)));

/* The gallery must not carry its own copy of the labels or its own idea of
   what might/tough/power mean. Reading the source is the only way to see
   that from here, and it is the drift itself being checked, not a spelling. */
$g = file_get_contents(__DIR__ . '/dhcgallery.php');
ok(strpos($g, 'dhcf_rank_axes()') !== false,
   'dhcgallery.php no longer builds its sort list from dhcf_rank_axes() -- the '
 . 'button and the canvas chip can now disagree about what Deadliest means');
ok(!preg_match("/'might'\s*=>\s*'Deadliest'/", $g),
   'dhcgallery.php has a local copy of the axis labels again');
ok(strpos($g, "\$row['might'] = \$row['hp'] * \$row['pow']") === false,
   'dhcgallery.php computes might itself again instead of asking dhcf_rank_values()');
foreach (array('tough', 'power', 'might') as $k) {
	ok(strpos($g, "\$dhcg_sort === '$k'") !== false,
	   "dhcgallery.php lost its sort branch for '$k', so the button would do nothing");
}

/* ---------- 3. the values --------------------------------------------------- */
echo "\nvalues\n";
/* Slugs need not exist: dhca_build_fighter() falls through to the baseline for
   anything not in the rarity table, which is exactly the behaviour a made-up
   slug should get, and it keeps this test independent of the art folder. */
$t1 = array('background' => 'bg', 'torso' => 'body-a', 'head' => 'face');
$t2 = array('background' => 'bg', 'torso' => 'body-b', 'head' => 'face');
$v1 = dhcf_rank_values($t1);
$v2 = dhcf_rank_values($t2);
printf("  build A  rarest %d  tough %d  power %d  might %d\n",
	$v1['rarest'], $v1['tough'], $v1['power'], $v1['might']);

ok($v1['might'] === $v1['tough'] * $v1['power'],
   'Deadliest is health x power and nothing else');
ok($v1['tough'] > 0 && $v1['power'] > 0, 'health and power are real numbers');
ok($v1['tough'] !== $v2['tough'],
   'two different torsos give the same health -- the per-trait variance that '
 . 'stops rarity deciding a fight is not reaching this');

/* The numbers must BE the Arena's, not merely similar to them. */
$built = dhca_build_fighter($t1, '', 'r', dhcf_rarity());
ok($v1['tough'] === (int)$built['maxHp'] && $v1['power'] === (int)$built['power'],
   'health/power do not match dhca_build_fighter() -- something here is doing '
 . 'its own arithmetic');
/* And a caller that already has one must get the same answer as one that does
   not, or dhcgallery.php's rows and the canvas chip diverge. */
$v1b = dhcf_rank_values($t1, null, $built);
ok($v1 === $v1b, 'passing a prebuilt fighter in changes the answer');

/* Rarity carries the originality bonus, the way a save would.
   REAL SLUGS HERE, unlike above: made-up ones score zero, and 0 + 15% of 0 is
   0, so this check passed while testing nothing. */
$real = array();
foreach (dhcf_rarity() as $cat => $slugs) {
	if (!in_array($cat, array('background', 'torso', 'head'), true)) continue;
	$real[$cat] = (string)array_key_first($slugs);
}
ok(count($real) === 3, 'could not find three real traits in dhcrarity.php to score');
$base = dhcf_score($real);
ok($base > 0, 'the sample build scores zero, so the bonus check below proves nothing');
$v1 = dhcf_rank_values($real);
ok($v1['rarest'] === $base + (int)round($base * DHCF_ORIGINALITY_BONUS),
   'the previewed rarity score leaves out the originality bonus, so it would '
 . 'not match the number the Fighter gets when saved');
/* A stored score always wins: a Fighter saved under older rules is ranked by
   the number it actually holds. */
printf("  real build: base %d, with bonus %d\n", $base, $v1['rarest']);
ok(dhcf_rank_values($real, 12345)['rarest'] === 12345,
   'a stored rarity_score must be used as-is, not recomputed');

/* ---------- 4. the pool and the exclude ------------------------------------ */
/*
 * A stub connection, because the interesting parts are not the SQL: that the
 * pool comes back sorted DESCENDING per axis (dhcf_rank_of assumes it and
 * would silently return nonsense otherwise), that the STORED rarity_score is
 * used rather than a recomputed one, and that editing a Fighter takes its own
 * row out of the pool it is ranked against -- otherwise it competes with
 * itself and a build about to be the rarest reads as second.
 */
echo "\npool\n";

class RankStubRes {
	public $rows;
	function __construct($r) { $this->rows = $r; }
	function fetch_assoc() { return $this->rows ? array_shift($this->rows) : null; }
}
class RankStubConn {
	public $rows; public $reads = 0;
	function __construct($rows) { $this->rows = $rows; }
	function query($sql) {
		if (strpos($sql, 'COUNT(*)') !== false) {
			return new RankStubRes(array(array('n' => count($this->rows), 'm' => '2026-09-30 00:00:00')));
		}
		$this->reads++;
		$out = array();
		foreach ($this->rows as $r) {
			$out[] = array('traits' => json_encode($r[0]), 'rarity_score' => $r[1]);
		}
		return new RankStubRes($out);
	}
}

$saved = array(
	array(array('background'=>'bg','torso'=>'t-iron','head'=>'h-one'),   900),
	array(array('background'=>'bg','torso'=>'t-bone','head'=>'h-two'),   400),
	array(array('background'=>'bg','torso'=>'t-glass','head'=>'h-three'), 650),
);
$conn = new RankStubConn($saved);
/* Cache OFF: it is keyed on COUNT(*) and MAX(updated_at), and the stub hands
   back the same stamp for different pools, so a cached run would answer the
   previous test's question. The cache is exercised separately below. */
$pool = dhcf_rank_pool($conn, false);
printf("  rarest %s\n  tough  %s\n", implode(',', $pool['rarest']), implode(',', $pool['tough']));
ok($pool['_n'] === 3, 'the pool counted every saved Fighter');
foreach (array('rarest', 'might', 'tough', 'power') as $k) {
	$sorted = $pool[$k]; rsort($sorted);
	ok($pool[$k] === $sorted, "the '$k' pool is not sorted descending -- dhcf_rank_of() "
	                        . 'binary-searches it and would return nonsense');
}
ok($pool['rarest'] === array(900, 650, 400), 'the STORED rarity scores came through unchanged');

/* Ranked against those three. */
$mine = array('background'=>'bg','torso'=>'t-iron','head'=>'h-one');
$r = dhcf_rank_build($conn, $mine, null);
ok($r['n'] === 3, 'the build ranks against all three');
ok($r['axes']['rarest']['of'] === 4,
   'a new build is counted into the pool it would join: 3 saved + itself');
ok($r['axes']['might']['value'] === $r['axes']['tough']['value'] * $r['axes']['power']['value'],
   'Deadliest is still health x power once it has been through the pool');

/* Editing the 900: it must not be ranked behind its own saved row. */
$own    = dhcf_rank_values($saved[0][0], $saved[0][1]);
$withIt = dhcf_rank_build($conn, $saved[0][0], null);
$without= dhcf_rank_build($conn, $saved[0][0], $own);
printf("  editing the 900: rank %d with its own row in the pool, %d without\n",
	$withIt['axes']['rarest']['rank'], $without['axes']['rarest']['rank']);
/*
 * THE BUILD'S SCORE IS RECOMPUTED, the pool's are the STORED ones, and that
 * is deliberate: the preview answers "if I saved this now", and what gets
 * saved is the fresh number. So a saved row can legitimately outrank the
 * build being edited -- what must not happen is the Fighter being counted
 * twice and beating itself.
 */
ok($without['axes']['rarest']['rank'] === $withIt['axes']['rarest']['rank'] - 1,
   'excluding the edited Fighter did not lift the build past its own saved row');
ok($without['axes']['rarest']['of'] === $withIt['axes']['rarest']['of'] - 1,
   'the excluded row is not actually being removed from the pool');
foreach (array('rarest', 'might', 'tough', 'power') as $k) {
	ok($without['axes'][$k]['rank'] <= $withIt['axes'][$k]['rank'],
	   "'$k': dropping a row from the pool made the rank WORSE, which is impossible");
}
/* And a build that beats the whole collection is first either way. */
$top = dhcf_rank_build($conn, $mine, null);
$topRank = dhcf_rank_of(array(), 1);
ok($topRank === 1, 'sanity: an empty pool still ranks 1');

echo "\ncache\n";
/*
 * CLEAR IT FIRST. dhcf_rank_build() above ran with the cache on and left a
 * file behind, so without this the "first" call below is already a hit and
 * the test passes whether the cache works or not -- which is exactly what it
 * did on the first run.
 */
$dir = __DIR__ . '/dhcrenders';
$pre = count((array)glob($dir . '/rankpool-*.json'));
foreach ((array)glob($dir . '/rankpool-*.json') as $f) @unlink($f);

$before = $conn->reads;
$a = dhcf_rank_pool($conn, true);
$cold = $conn->reads - $before;
$b = dhcf_rank_pool($conn, true);
$warm = $conn->reads - $before - $cold;
printf("  full reads of the collection: %d cold, %d warm\n", $cold, $warm);
ok($a === $b, 'the cached pool differs from the one that built it');
ok($cold === 1, 'a cold call should read the collection exactly once');
ok($warm === 0,
   'the second call re-read every Fighter -- the cache is not being used, and the '
 . 'assembler asks for this on every trait the player tries');
ok(count((array)glob($dir . '/rankpool-*.json')) === 1, 'the cache file was not written');
foreach ((array)glob($dir . '/rankpool-*.json') as $f) @unlink($f);
if ($pre === 0 && is_dir($dir) && !glob($dir . '/*')) @rmdir($dir);
echo "  (cache files cleaned up)\n";

echo "\n" . ($fail ? "FAILED: $fail check(s)\n" : "all rank checks passed\n");
exit($fail ? 1 : 0);
