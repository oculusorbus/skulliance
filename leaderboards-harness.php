<?php
/* Exercises the real leaderboard period switcher with no database. */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$fail = 0;
function ok($c, $w) { global $fail; if (!$c) { $fail++; echo "  FAIL  $w\n"; } }

$db = file_get_contents(__DIR__ . '/db.php');

/* The catalogue, and the two functions, lifted out of db.php verbatim. */
function brace_extract($src, $sig) {
	$at = strpos($src, $sig);
	if ($at === false) return '';
	$i = strpos($src, '{', $at); $d = 0;
	for ($j = $i; $j < strlen($src); $j++) {
		if ($src[$j] === '{') $d++;
		elseif ($src[$j] === '}') { $d--; if (!$d) return substr($src, $at, $j - $at + 1); }
	}
	return '';
}
$cat = brace_extract($db, '$SKULLIANCE_BOARDS = array(');
preg_match('/\$SKULLIANCE_BOARDS = array\((.*?)\n\);/s', $db, $m);
eval('$SKULLIANCE_BOARDS = array(' . $m[1] . ');');
eval(brace_extract($db, 'function leaderboardBoardFor($slug)'));
eval(brace_extract($db, 'function renderLeaderboardPeriods($slug)'));

echo "the catalogue\n";
ok(count($SKULLIANCE_BOARDS) > 10, 'the board catalogue did not load');
printf("  %d boards\n", count($SKULLIANCE_BOARDS));

echo "\nevery period slug resolves back to its board\n";
$multi = 0;
foreach ($SKULLIANCE_BOARDS as $key => $meta) {
	foreach ($meta['periods'] as $label => $slug) {
		$b = leaderboardBoardFor($slug);
		ok($b && $b['key'] === $key && $b['current'] === $label,
		   "'$slug' ($key / $label) does not resolve back to its own board");
	}
	if (count($meta['periods']) > 1) $multi++;
}
printf("  %d boards have more than one period\n", $multi);

echo "\nwhat it renders\n";
function render($slug) { ob_start(); renderLeaderboardPeriods($slug); return ob_get_clean(); }

$h = render('monthly');                       /* Missions, Monthly */
ok(strpos($h, 'lb-periods') !== false, 'no switcher for a two-period board');
ok(substr_count($h, 'lb-period') === 3, 'expected two period entries, got: ' . $h);
ok(strpos($h, "<span class='lb-period on'") !== false, 'the current period is a link, not a marker');
ok(strpos($h, 'filterby=missions') !== false, 'the sibling All-Time period is not linked');
ok(strpos($h, "filterby=monthly'") === false, 'the CURRENT period is linked to itself');

$h = render('activity-weekly');               /* three periods */
ok(substr_count($h, 'lb-period') === 4, 'a three-period board did not render three entries');
foreach (array('activity-ath', 'activity-monthly') as $sib) {
	ok(strpos($h, 'filterby=' . $sib) !== false, "weekly Activity does not link to $sib");
}

ok(render('realms') === '', 'a one-period board renders a switcher, which is a label pretending to be a control');
ok(render('missions-unlocked') === '', 'Missions Unlocked has one period and should render nothing');
ok(render('hub') === '', 'the hub renders a switcher');
ok(render('7') === '', 'a plain project id resolves to a board');
ok(render(0) === '', 'filterby=0 (All Projects) resolves to a board');
/* The INT is the case the cast exists for: '15' === '15' needs no help. */
ok(leaderboardBoardFor(15) !== null && leaderboardBoardFor(15)['key'] === 'delegations',
   'an integer filterby no longer resolves to the numeric board slug');
ok(leaderboardBoardFor('15')['key'] === 'delegations',
   'the one numeric slug in the catalogue no longer resolves; $filterby '
 . 'arrives as a string from the query and as an int elsewhere, which is why '
 . 'the comparison casts both');
ok(render(null) === '', 'a null filterby renders something');

/*
 * AN AVERAGE LEVEL IS NOT A DECIMAL(4). The all-time Monstrocity board
 * ranks on AVG(level) and MySQL returns that as a DECIMAL, so the hub card
 * read "Lvl 28.0000". The monthly board uses MAX(level), an integer, which
 * is why only one of the two ever looked wrong.
 *
 * The rtrim pair is the part worth pinning: it relies on number_format
 * having put a '.' in the string. Without that, "20" would trim to "2".
 */
echo "\nan average level reads like a level\n";
eval(brace_extract($db, 'function lbAvgLevel($v)'));
$levels = array('28.0000' => '28', '27.6667' => '27.7', '27.0999' => '27.1',
                '100.0000' => '100', '20.1000' => '20.1', '9.9500' => '10',
                '0.0000' => '0', '30' => '30', '7' => '7');
foreach ($levels as $in => $want) {
	ok(lbAvgLevel($in) === $want,
	   "lbAvgLevel('$in') gave '" . lbAvgLevel($in) . "', wanted '$want'");
}
printf("  %d level formats checked, trailing zeros dropped and whole numbers kept whole\n",
	count($levels));
$dbsrc = $db;
ok(strpos($dbsrc, "'score'=>'Lvl '.lbAvgLevel(") !== false,
   'the Monstrocity hub card prints the raw AVG again -- "Lvl 28.0000"');
ok(strpos($dbsrc, '$stats = [$level_label=>lbAvgLevel(') !== false,
   'the Monstrocity board row prints the raw AVG again, so the row and the '
 . 'hub card disagree');

/*
 * THE BOARD PICKER. The period switcher only solved half of it -- you
 * could change the timeframe without the hub, but not the BOARD, so
 * comparing two games was still hub, find card, click.
 *
 * It points at each board's SHORTEST period rather than all-time, because
 * the switcher sits right beside it: landing on the live cut and stepping
 * back is one press, and it keeps the list one entry per board.
 */
echo "\nthe board picker\n";
eval(brace_extract($db, 'function leaderboardShortestPeriod($meta)'));
eval(brace_extract($db, 'function renderLeaderboardPicker($slug)'));
function pick($slug) { ob_start(); renderLeaderboardPicker($slug); return ob_get_clean(); }

$h = pick('monthly-dhcarena');
printf("  %d boards in %d groups\n", substr_count($h, '<option'), substr_count($h, '<optgroup'));
ok(substr_count($h, '<option') === count($SKULLIANCE_BOARDS),
   'the picker is not listing every catalogued board');
ok(substr_count($h, '<optgroup') === count(array_unique(array_column($SKULLIANCE_BOARDS, 'group'))),
   'the picker groups do not match the hub sections');
ok(preg_match('/<option selected>DHC Arena|<option[^>]* selected>DHC Arena/', $h) === 1,
   'the board you are on is not selected in the picker');
ok(strpos($h, 'Choose') === false,
   'a placeholder is shown even though the current slug IS a board');
ok(strpos(pick('7'), 'Choose') !== false,
   'a project board shows some other board as current instead of a placeholder');
/* The hub is the back link, not a dropdown row. */
ok(strpos($h, 'filterby=hub') === false && strpos($h, '>Hub<') === false,
   'the picker lists the hub; the back link already is that');
/* Shortest period, per board. */
foreach ($SKULLIANCE_BOARDS as $key => $meta) {
	$want = leaderboardShortestPeriod($meta);
	ok(strpos($h, 'filterby=' . rawurlencode($want) . "'") !== false
	   || strpos($h, 'filterby=' . urlencode($want) . "'") !== false,
	   "the picker does not point '$key' at its shortest period ($want)");
}
foreach (array('activity' => 'activity-weekly', 'streaks' => 'monthly-streaks',
               'realms' => 'realms', 'gamemaster' => 'gamemaster-weekly') as $k => $want) {
	ok(leaderboardShortestPeriod($SKULLIANCE_BOARDS[$k]) === $want,
	   "shortest period for '$k' should be '$want', got '"
	 . leaderboardShortestPeriod($SKULLIANCE_BOARDS[$k]) . "'");
}

echo "\nthe page wires it up\n";
$lb = file_get_contents(__DIR__ . '/leaderboards.php');
ok(strpos($lb, 'renderLeaderboardPeriods($filterby)') !== false,
   'leaderboards.php never calls the switcher');
ok(preg_match('/\.lb-period\.on\s*\{[^}]*background/', $lb) === 1,
   'the current period has no filled state, so nothing says which one you are on');
ok(strpos($lb, 'lb-subhead') !== false, 'the back link and the switcher are not on one row');
ok(strpos($lb, 'renderLeaderboardPicker($filterby)') !== false,
   'leaderboards.php never calls the board picker');
/*
 * THE TWO NUDGES. flexbox.css pulls #filtered-content up 40px and
 * #filter-nfts a further 35, tuned for a page whose <h2> sat alone above
 * the panel and shared with store/my-nfts/showcase/collections -- so they
 * cannot be changed there. With a subhead row between heading and panel
 * they drag the board up over it: measured, the content began 26px ABOVE
 * the bottom of the subhead, and the Find a Project <select> landed
 * directly on the period toggle. That is why the toggle was reachable on a
 * phone and invisible on a desktop.
 */
ok(preg_match('/#filtered-content \{ top: 0; \}/', $lb) === 1
   && preg_match('/#filter-nfts \{ top: 0; \}/', $lb) === 1,
   'leaderboards.php no longer cancels the inherited -40px/-35px pull-ups, '
 . 'so the panel climbs over the subhead and the project select covers the '
 . 'period toggle again');
ok(preg_match('/\.lb-periods \{[^}]*margin-left:\s*auto/', $lb) === 0,
   'the period toggle is pushed right again, which is where the pulled-up '
 . 'project select sits');
ok(preg_match('/\.lb-subhead \{[^}]*z-index:\s*1/s', $lb) === 1,
   'the subhead has no stacking context, so anything pulled up from the '
 . 'panel below can paint over the controls again');

echo "\n";
if ($fail) { echo "$fail check(s) FAILED\n"; exit(1); }
echo "all leaderboard period checks passed\n";
