<?php
/* analytics-metrics-harness.php - CLI only. No database.
 *
 * Analytics had fallen a year behind the platform: the trend chart offered
 * fourteen metrics and knew about three of the eleven games. Adding the rest
 * is easy; the thing that is hard is keeping four lists in step afterwards.
 *
 *   1. the <option> list in analytics.php
 *   2. metricLabels in the same file (a missing key puts the raw metric id,
 *      "dhctraits", in the chart tooltip)
 *   3. $metrics in ajax/analytics-trends.php
 *   4. $SKULLIANCE_BOARDS in db.php, which is what a player sees as the list
 *      of games
 *
 * and behind all four, db.php's Activity leaderboard $sources, which is the
 * one place that decides what counts as a play. A trend line that counts
 * every started delve while the board counts finished ones is not a smaller
 * bug than a missing option - it is a worse one, because it looks right.
 *
 * Also checks the column names, which is where this would really break: the
 * date column is created_at for the racer and for Fighters, started_at for
 * Arena battles, resolved_date for Gauntlet encounters, awarded_at for trait
 * drops, date_created for the rest. Guessing wrong gives you an empty chart
 * and a line in the error log, not an error on the page.
 *
 * Usage: php analytics-metrics-harness.php
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

$fail = 0;
function ok($cond, $what) { global $fail; if (!$cond) { $fail++; echo "  FAIL  $what\n"; } }

$root       = __DIR__;
$trends_src = file_get_contents($root . '/ajax/analytics-trends.php');
$page_src   = file_get_contents($root . '/analytics.php');
$db_src     = file_get_contents($root . '/db.php');

/* Strip PHP/JS comments before scanning for code. Four harnesses in this repo
   have now matched their own prose instead of the code they were checking. */
function no_comments($s) {
	$s = preg_replace('!/\*.*?\*/!s', '', $s);
	$s = preg_replace('!^\s*//.*$!m', '', $s);
	return $s;
}

/* ---------------------------------------------------------------- *
 * The real $metrics map, lifted out of ajax/analytics-trends.php.
 * ---------------------------------------------------------------- */
$at = strpos($trends_src, '$metrics = [');
ok($at !== false, '$metrics is gone from ajax/analytics-trends.php');
$end = strpos($trends_src, "\n];", $at);
$metrics_literal = substr($trends_src, $at, $end - $at + 3);
$metrics = null;
eval($metrics_literal);
ok(is_array($metrics) && count($metrics) > 0, '$metrics did not evaluate to an array');

/* ---------------------------------------------------------------- *
 * The <option> values and the metricLabels keys, out of analytics.php.
 * ---------------------------------------------------------------- */
$sel_at  = strpos($page_src, '<select id="trend-metric"');
ok($sel_at !== false, 'the #trend-metric select is gone from analytics.php');
$sel_end = strpos($page_src, '</select>', $sel_at);
$select  = substr($page_src, $sel_at, $sel_end - $sel_at);
preg_match_all('/<option value="([^"]+)"/', $select, $m);
$options = $m[1];

$lab_at  = strpos($page_src, 'const metricLabels = {');
ok($lab_at !== false, 'metricLabels is gone from analytics.php');
$lab_end = strpos($page_src, '};', $lab_at);
$labels_block = no_comments(substr($page_src, $lab_at, $lab_end - $lab_at));
preg_match_all('/^\s*([a-z]+)\s*:/mi', $labels_block, $m2);
$labels = array_values(array_diff($m2[1], array('const')));

echo "the dropdown, the labels and the endpoint agree\n";
printf("  %d options, %d labels, %d metrics\n", count($options), count($labels), count($metrics));

foreach ($options as $o) {
	ok(isset($metrics[$o]), "analytics.php offers \"$o\" and the endpoint has no such metric");
	ok(in_array($o, $labels, true), "\"$o\" has no metricLabels entry, so its tooltip reads \"$o\"");
}
foreach (array_keys($metrics) as $k) {
	ok(in_array($k, $options, true), "the endpoint knows \"$k\" and nothing in the dropdown can ask for it");
}

/* ---------------------------------------------------------------- *
 * Every game on the leaderboard hub is on the chart.
 *
 * This is the check that would have caught the original problem: eight games
 * shipped, each one added to $SKULLIANCE_BOARDS, none of them added here.
 * ---------------------------------------------------------------- */
echo "\nevery game the hub lists has a trend\n";
$b_at  = strpos($db_src, '$SKULLIANCE_BOARDS = array(');
$b_end = strpos($db_src, "\n);", $b_at);
$boards_literal = substr($db_src, $b_at, $b_end - $b_at + 3);
$SKULLIANCE_BOARDS = null;
eval($boards_literal);
ok(is_array($SKULLIANCE_BOARDS), '$SKULLIANCE_BOARDS did not evaluate');

/* board key => metric key. */
$board_to_metric = array(
	'cryptcrawl'    => 'cryptcrawl',
	'cryptconquest' => 'cryptconquest',
	'guardians'     => 'guardians',
	'skullracer'    => 'skullracer',
	'swaps'         => 'skullswap',
	'monstrocity'   => 'monstrocity',
	'dhcfighters'   => 'dhcfighters',
	'dhcarena'      => 'dhcarena',
	'bosses'        => 'bossbattles',
	'gauntlets'     => 'gauntlets',
	'obscura'       => 'obscura',
);
/* Deliberately absent, each for a reason that is not "we forgot":
     gamemaster       - the cross-game aggregate, not a game
     skullracer-laps  - a second board on the same runs as 'skullracer'
     dropship,
     oculuslounge     - their own database, and `results` has no date column
                        at all, so there is nothing to plot against. See the
                        note at the top of ajax/analytics-trends.php. */
$exempt = array('gamemaster', 'skullracer-laps', 'dropship', 'oculuslounge');

foreach ($SKULLIANCE_BOARDS as $key => $meta) {
	if ($meta['group'] !== 'Games' && $meta['group'] !== 'Specialty Games') continue;
	if (in_array($key, $exempt, true)) continue;
	ok(isset($board_to_metric[$key]),
	   "the hub lists \"{$meta['label']}\" and this harness has never heard of it - "
	 . 'add it to $metrics and to the dropdown, or to $exempt with a reason');
	if (isset($board_to_metric[$key])) {
		ok(isset($metrics[$board_to_metric[$key]]),
		   "\"{$meta['label']}\" is on the hub and has no trend metric");
	}
}
printf("  %d game boards, %d exempt\n",
	count(array_filter($SKULLIANCE_BOARDS, function ($m) { return $m['group'] === 'Games' || $m['group'] === 'Specialty Games'; })),
	count($exempt));

/* ---------------------------------------------------------------- *
 * A trend counts the same thing the Activity board counts.
 * ---------------------------------------------------------------- */
echo "\na trend counts what the Activity board counts\n";
$s_at  = strpos($db_src, "\t\t'daily'       => [");
ok($s_at !== false, 'the Activity $sources array moved; this check is now blind');
$s_end = strpos($db_src, "\n\t];", $s_at);
$sources = no_comments(substr($db_src, $s_at, $s_end - $s_at));

/* metric key => [Activity source key, fragments that must appear in both] */
$mirrors = array(
	'skullswap'     => array('skullswap',   array('scores',               'project_id = 0')),
	'monstrocity'   => array('monstrocity', array('scores',               'project_id = 36')),
	'bossbattles'   => array('boss',        array('encounters')),
	'cryptcrawl'    => array('crawl',       array('cryptcrawls',          "status IN ('won','lost')")),
	'cryptconquest' => array('conquest',    array('cryptconquests',       "status IN ('won','lost')")),
	'gauntlets'     => array('gauntlet',    array('gauntlets_encounters', "outcome != 'pending'")),
	'skullracer'    => array('racer',       array('skull_racer_runs')),
	'obscura'       => array('obscura',     array('obscura_scores',       'active = 0')),
	'guardians'     => array('guardians',   array('guardians_scores')),
);
foreach ($mirrors as $metric => [$source_key, $fragments]) {
	ok(isset($metrics[$metric]), "metric \"$metric\" vanished");
	if (!isset($metrics[$metric])) continue;
	[$from, $date_col, $where, $agg] = $metrics[$metric];
	$trend_sql = "$from $where $agg";

	/* The source's own line in db.php. */
	$line_at = strpos($sources, "'$source_key'");
	ok($line_at !== false, "Activity has no \"$source_key\" source any more");
	if ($line_at === false) continue;
	/* The LAST source in the array has no "],\n" after it - the slice above
	   stops at the closing "\n\t];" - so fall back to the end of the slice
	   rather than handing substr a negative length and silently checking the
	   empty string. That is exactly what happened on the first run here:
	   Monstrocity, the last entry, reported both its fragments missing. */
	$line_end = strpos($sources, "],\n", $line_at);
	if ($line_end === false) $line_end = strlen($sources);
	$board_sql = substr($sources, $line_at, $line_end - $line_at);

	foreach ($fragments as $f) {
		ok(strpos($trend_sql, $f) !== false,
		   "the \"$metric\" trend no longer says \"$f\"; Activity still counts it that way");
		ok(strpos($board_sql, $f) !== false,
		   "Activity's \"$source_key\" no longer says \"$f\"; the \"$metric\" trend still counts it that way");
	}
}
printf("  %d game metrics checked against Activity\n", count($mirrors));

/* ---------------------------------------------------------------- *
 * The date column exists in the table it is read from.
 *
 * Only for the tables whose CREATE lives in this repo. The rest (the
 * transactions/users/missions family, and the two Crypt tables, whose date
 * columns were added by hand-run migrations) have no CREATE here to check
 * against; db.php's own migration note records them instead.
 * ---------------------------------------------------------------- */
echo "\nthe date column exists in the table\n";
$schema_files = array(
	'obscura_scores'     => 'obscura-lib.php',
	'guardians_scores'   => 'guardians-lib.php',
	'skull_racer_runs'   => 'db.php',
	'dhc_arena_battles'  => 'dhcarena-schema.md',
	'dhc_fighters'       => 'dhcfighters-schema.md',
	'dhc_trait_drops'    => 'dhcfighters-schema.md',
);
$checked = 0;
foreach ($metrics as $key => [$from, $date_col, $where, $agg]) {
	$table = preg_split('/\s+/', trim($from))[0];
	if (!isset($schema_files[$table])) continue;
	$src = file_get_contents($root . '/' . $schema_files[$table]);
	$c_at = strpos($src, 'CREATE TABLE IF NOT EXISTS ' . $table);
	if ($c_at === false) $c_at = strpos($src, 'CREATE TABLE ' . $table);
	ok($c_at !== false, "no CREATE for $table in {$schema_files[$table]}; this check went blind");
	if ($c_at === false) continue;
	$c_end = strpos($src, ');', $c_at);
	$create = substr($src, $c_at, $c_end - $c_at);
	$col = strpos($date_col, '.') !== false ? substr($date_col, strpos($date_col, '.') + 1) : $date_col;
	ok(preg_match('/^[\s*|-]*' . preg_quote($col, '/') . '\s/mi', $create) === 1,
	   "\"$key\" reads $table.$col and that column is not in its CREATE in {$schema_files[$table]}");
	$checked++;
}
printf("  %d metrics checked against a CREATE in the repo\n", $checked);

/* ---------------------------------------------------------------- *
 * The endpoint still says something when a query fails.
 * ---------------------------------------------------------------- */
echo "\na failed query does not look like an unplayed game\n";
$clean = no_comments($trends_src);
ok(strpos($clean, "\$out['error']") !== false,
   'the endpoint no longer reports a failed query, so a missing column draws an empty chart');
ok(strpos(no_comments($page_src), 'if (d.error)') !== false,
   'analytics.php no longer reads the error back, so the endpoint reports to nobody');

/* Undated rows are the third state, and the one that actually bit: Realm
   Guardians has 39 sieges on record with a NULL date_created on every one, so
   the chart drew nothing and read as a game nobody plays. Dropping those rows
   silently is what made the two look identical. */
ok(strpos($clean, "\$undated += ") !== false,
   'the endpoint drops NULL-dated rows again instead of counting them');
ok(strpos($clean, "\$out['undated']") !== false,
   'the endpoint counts undated rows and then never reports them');
ok(strpos(no_comments($page_src), 'd.undated') !== false,
   'analytics.php no longer reads undated back, so an undated table reads as an empty one');

echo "\n" . ($fail ? "$fail FAILED\n" : "all good\n");
exit($fail ? 1 : 0);
