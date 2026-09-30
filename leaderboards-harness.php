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

echo "\nthe page wires it up\n";
$lb = file_get_contents(__DIR__ . '/leaderboards.php');
ok(strpos($lb, 'renderLeaderboardPeriods($filterby)') !== false,
   'leaderboards.php never calls the switcher');
ok(preg_match('/\.lb-period\.on\s*\{[^}]*background/', $lb) === 1,
   'the current period has no filled state, so nothing says which one you are on');
ok(strpos($lb, 'lb-subhead') !== false, 'the back link and the switcher are not on one row');

echo "\n";
if ($fail) { echo "$fail check(s) FAILED\n"; exit(1); }
echo "all leaderboard period checks passed\n";
