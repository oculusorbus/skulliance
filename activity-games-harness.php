<?php
/* activity-games-harness.php - CLI only. No database.
 *
 * WHAT IS ACTUALLY AT RISK HERE. The games view does not count anything:
 * it pivots what activity_totals() already returned. So the bugs it can
 * have are all shape bugs, and every one of them LOOKS FINE on the page:
 *
 *   - a game nobody played listed with an empty row, because every source
 *     is present at zero on every player's row;
 *   - the rows sorted by actions while the avatars draw players, so the
 *     picture disagrees with the order it claims;
 *   - a player's platform total printed as their count for one game;
 *   - a new game added to activity_sources() with no label, putting the
 *     raw key 'dhctraits' in a heading.
 *
 * None of those raise anything. They are each checked below against a
 * fixture built so that the wrong answer is a DIFFERENT answer -- the
 * quiet game has more actions than the popular one on purpose.
 *
 * Usage: php activity-games-harness.php
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

$fail = 0;
function ok($cond, $what) { global $fail; if (!$cond) { $fail++; echo "  FAIL  $what\n"; } }

require_once __DIR__ . '/activity-sources-lib.php';

ok(function_exists('activity_games'), 'activity_games() is gone; everything below is blind');
if (!function_exists('activity_games')) { echo "\nFAILED\n"; exit(1); }

/* ---------------------------------------------------------------- *
 * THE FIXTURE. Shaped like activity_totals() output, including the
 * blank-filled 'by' map -- every source on every row, most at zero,
 * which is the thing that makes the > 0 test load-bearing.
 *
 * 'racer' is ONE player with 400 runs.  'arena' is THREE players with
 * 2 battles each.  So actions says racer, players says arena, and the
 * two orderings cannot be confused for one another.
 * ---------------------------------------------------------------- */
$blank = array_fill_keys(array_keys(activity_sources('')), 0);
$blank['dropship'] = 0;

function fx($uid, $name, $by, $blank) {
	$row = array(
		'user_id' => $uid, 'username' => $name, 'discord_id' => '77283152389996544' . $uid,
		'avatar' => 'abcdefgh12345678', 'total' => 0, 'by' => array_merge($blank, $by),
	);
	foreach ($row['by'] as $n) $row['total'] += $n;
	return $row;
}

$rows = array(
	fx(1, 'zoe',  array('racer' => 400, 'arena' => 2),  $blank),
	fx(2, 'adam', array('arena' => 2, 'obscura' => 1),  $blank),
	fx(3, 'mia',  array('arena' => 2),                  $blank),
);
$games = activity_games($rows);
$by_key = array();
foreach ($games as $g) $by_key[$g['key']] = $g;

/* 1. EXACTLY the games somebody played, and no others. */
$keys = array_keys($by_key);
sort($keys);
ok($keys === array('arena', 'obscura', 'racer'),
   'activity_games() listed ' . implode(',', $keys) . ' -- a game with no players is in the list');

/* 2. MOST PLAYERS FIRST, not most actions. arena has 3 players and 6
      actions; racer has 1 player and 400. */
ok(isset($games[0]) && $games[0]['key'] === 'arena',
   'the first game is ' . (isset($games[0]) ? $games[0]['key'] : 'nothing')
   . ' -- the rows are ordered by actions, not by how many people played');

/* 3. The participants of a game are exactly the players above zero. */
$arena = array();
foreach ($by_key['arena']['players'] as $p) $arena[] = $p['username'];
sort($arena);
ok($arena === array('adam', 'mia', 'zoe'), 'arena players came back as ' . implode(',', $arena));
ok(count($by_key['racer']['players']) === 1, 'racer picked up somebody who never raced');

/* 4. 'cnt' IS THAT GAME, not the player's platform total. zoe has 402
      actions overall and 2 in Arena; printing 402 beside her avatar in
      the Arena row is the mistake this catches. */
$zoe = null;
foreach ($by_key['arena']['players'] as $p) if ($p['username'] === 'zoe') $zoe = $p;
ok($zoe !== null && (int)$zoe['cnt'] === 2,
   "zoe's Arena count is " . ($zoe === null ? 'missing' : $zoe['cnt']) . ', not 2');

/* 5. The platform-wide map is NOT carried into a per-game row, so no
      template can reach for it and get a number for the wrong game. */
ok($zoe !== null && !isset($zoe['by']), "the per-game player row still carries 'by'");

/* 6. Heaviest player first inside a game. */
$ob = fx(4, 'kay', array('obscura' => 9), $blank);
$g2 = activity_games(array($rows[1], $ob));
foreach ($g2 as $g) if ($g['key'] === 'obscura') {
	ok($g['players'][0]['username'] === 'kay',
	   'inside a game the players are not ordered by their own count');
}

/* 7. actions is the sum of the row, which is what the heading prints. */
ok((int)$by_key['arena']['actions'] === 6,
   "arena actions reads " . $by_key['arena']['actions'] . ', not the sum of its players');

/* 8. Empty in, empty out -- the page renders this branch as "no activity". */
ok(activity_games(array()) === array(), 'activity_games(array()) is not an empty array');

/* ---------------------------------------------------------------- *
 * EVERY SOURCE NEEDS A LABEL. The players view prints the raw key as a
 * fallback inside a small caption; the games view prints it as the
 * HEADING of a block, which is a lot more visible. A game added to
 * activity_sources() and not to activity_labels() is the likeliest
 * future break on this page.
 * ---------------------------------------------------------------- */
$labels = activity_labels();
$missing = array();
foreach (array_keys($blank) as $k) if (!isset($labels[$k])) $missing[] = $k;
ok(!$missing, 'no label in activity_labels() for: ' . implode(', ', $missing));

/* ---------------------------------------------------------------- *
 * THE PAGE. Only the parts a wrong answer would not show up in.
 * ---------------------------------------------------------------- */
$src = (string)@file_get_contents(__DIR__ . '/activity.php');
ok($src !== '', 'activity.php is gone; the checks below are blind');

/* Comments are stripped first. Four harnesses in this repo have matched
   their own prose instead of the code -- and this file's own header names
   both 'players' and 'games' in sentences. */
function no_comments($s) {
	$s = preg_replace('!/\*.*?\*/!s', '', $s);
	$s = preg_replace('!<\?php\s*/\*.*?\*/\s*\?>!s', '', $s);
	$s = preg_replace('!^\s*//.*$!m', '', $s);
	return $s;
}
$code = no_comments($src);

/* THE DEFAULT IS PLAYERS, and it is tested by RUNNING the page's own
   three lines rather than by reading them -- a whitelist that contains
   both words tells you nothing about which one an empty query string
   lands on. */
$at  = strpos($code, '$ac_view = isset(');
ok($at !== false, 'the $ac_view parsing is gone from activity.php');
if ($at !== false) {
	$end   = strpos($code, "'players';", strpos($code, 'in_array($ac_view', $at));
	$block = substr($code, $at, $end - $at + 10);
	/* The handler is what makes the array case below mean anything: a
	   cast of ?view[]= to string is accepted by the whitelist either way
	   and differs only by the PHP warning it prints above the page. */
	$warned = false;
	$try = function ($get) use ($block, &$warned) {
		$_GET = $get;
		$ac_view = null;
		$warned  = false;
		set_error_handler(function () use (&$warned) { $warned = true; return true; });
		eval($block);
		restore_error_handler();
		return $ac_view;
	};
	ok($try(array()) === 'players',               'with no ?view= the page is not the Players tab');
	ok($try(array('view' => 'games')) === 'games', '?view=games does not reach the games view');
	ok($try(array('view' => 'players')) === 'players', '?view=players does not reach the players view');
	ok($try(array('view' => 'wall')) === 'players',    'an unknown ?view= is not refused back to players');
	ok($try(array('view' => array('games'))) === 'players',
	   'an array ?view= is not refused -- it reaches the renderer as an array');
	ok(!$warned, '?view[]=games prints a PHP warning above the page before falling back');
}

/* EACH CONTROL CARRIES THE OTHER'S STATE. Losing either is the kind of
   thing nobody files a bug about; they just stop using the tab. */
ok(preg_match('/activity\.php\?days=<\?php echo \$d;[^"]*view=<\?php echo \$ac_view/', $code),
   'the 7/30/90 links do not carry the current view, so changing the window leaves the Games tab');
ok(preg_match('/view=players/', $code) && preg_match('/view=games/', $code)
   && preg_match('/activity\.php\?days=<\?php echo \$ac_days;[^"]*view=players/', $code),
   'the tab links do not carry the current window, so switching tabs resets it to 30 days');

/* THE AVATARS, NOT A BAR. The row of faces is the measurement, so the
   games block must render avatar_url() per player and must not reuse the
   bar element -- a scaled bar would be a different (and worse) answer to
   the same question. */
$gat = strpos($code, 'class="aca-games"');
ok($gat !== false, 'the games view does not render');
if ($gat !== false) {
	$block = substr($code, $gat, strpos($code, '<?php else:', $gat) - $gat);
	ok(strpos($block, 'avatar_url($p[\'discord_id\']') !== false,
	   'the games view does not draw a player avatar per participant');
	ok(strpos($block, 'aca-bar') === false,
	   'the games view is drawing a bar; the avatars are supposed to be the bar');
	ok(strpos($block, "\$p['cnt']") !== false,
	   "the games view does not use the player's count for THAT game");
	ok(strpos($block, 'activity_games(') === false,
	   'the games view pivots inside the markup; it should read $ac_games');
}

/* The pivot is not re-implemented on the page. */
ok(substr_count($code, 'activity_games(') === 1,
   'activity_games() is called more than once, or the page pivots by hand');

echo $fail ? "\n$fail FAILED\n" : "\nOK\n";
exit($fail ? 1 : 0);
