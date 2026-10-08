<?PHP
/*
 * GAUNTLETS HARNESS
 *
 *   php gauntlets-harness.php
 *
 * Brace-extracts the real gauntletPlanHand() out of db.php and drives it
 * directly. db.php cannot be included -- it opens a database connection at
 * load -- so the function is lifted the same way the other platform
 * harnesses lift theirs.
 *
 * gauntletPlanHand() is deliberately pure: the caller shuffles, it decides.
 * That split is what makes the draw testable at all, so the shape of the
 * hand is pinned here exactly, with no randomness to work around.
 *
 * Every assertion below was confirmed to FAIL when the line it covers is
 * mutated.
 */

error_reporting(E_ALL);
ini_set('display_errors', '1');

$pass = 0; $fail = 0; $fails = array();
function ok($label, $cond, $detail = '') {
	global $pass, $fail, $fails;
	if ($cond) { $pass++; echo "  ok   $label\n"; }
	else { $fail++; $fails[] = $label; echo "  FAIL $label" . ($detail !== '' ? "  -- $detail" : "") . "\n"; }
}
function section($t) { echo "\n== $t ==\n"; }

/* ---- lift the real function --------------------------------------------- */

$src = file_get_contents(__DIR__ . '/db.php');
function lift($name, $src) {
	$at = strpos($src, 'function ' . $name . '(');
	if ($at === false) { echo "could not find $name in db.php\n"; exit(1); }
	$o = strpos($src, '{', $at); $d = 0;
	for ($i = $o; $i < strlen($src); $i++) {
		if ($src[$i] === '{') $d++;
		elseif ($src[$i] === '}') { $d--; if ($d === 0) return substr($src, $at, $i - $at + 1); }
	}
	echo "unbalanced $name\n"; exit(1);
}
eval(lift('gauntletPlanHand', $src));
eval(lift('gauntletCalculateWinChance', $src));

$notices = array();
set_error_handler(function ($no, $str, $file, $line) use (&$notices) {
	if (!(error_reporting() & $no)) return true;
	$notices[] = "$str @ $file:$line";
	return true;
});

/*
 * TERMINATORS FIRST, BEFORE ANY PLANNER CALL. ORDERING IS THE ASSERTION.
 *
 * Several cases below deliberately ask for more cards than the inventory
 * holds, which is precisely what the no-progress break exists to end. So an
 * in-process timing assertion cannot test that break: with it deleted the
 * call never returns, and the harness does not FAIL, it HANGS -- which is
 * what happened the first time these mutations were run, and it hung again
 * after the check was merely moved earlier within the file but still left
 * below the first unsatisfiable case. Checked here, at the top, a missing
 * terminator is reported before anything can spin.
 */
$planner = lift('gauntletPlanHand', $src);
ok('the no-progress break is present, so an unsatisfiable hand terminates',
   strpos($planner, 'if (!$progress) break;') !== false);
ok('the collection-dealing loop has its own terminator',
   strpos($planner, 'if (!$took) break;') !== false);
if ($fail) {
	/* Refusing to go on is the point: every remaining case would hang. */
	echo "\nA loop terminator is missing -- stopping before the planner is called.\n";
	exit(1);
}

/* Shorthand: inv(collection, project, available) */
function inv($c, $p, $a) { return array('collection_id' => $c, 'project_id' => $p, 'available' => $a); }
function total($plan) { return array_sum($plan); }

/* ---------------------------------------------------------------------------
 * The case that prompted the change
 * ------------------------------------------------------------------------ */
section('the reported hand');

/* A holder of one huge collection and a few small ones. Under the old flat
   `ORDER BY RAND() LIMIT 6` this drew ~5 cards of collection 1 by sheer
   weight of numbers. */
$big = array(inv(1, 3, 200), inv(2, 4, 3), inv(3, 5, 2), inv(4, 6, 1));
$plan = gauntletPlanHand($big, 6);
ok('a 200-card collection no longer floods the hand', $plan[1] <= 2, json_encode($plan));
ok('every other collection the player holds is represented',
   isset($plan[2]) && isset($plan[3]) && isset($plan[4]), json_encode($plan));
ok('the hand is still full', total($plan) === 6, json_encode($plan));
ok('no collection is drawn past what the player owns',
   $plan[4] <= 1 && $plan[3] <= 2 && $plan[2] <= 3, json_encode($plan));

section('spread');

$six = array(inv(1,1,9), inv(2,2,9), inv(3,3,9), inv(4,4,9), inv(5,5,9), inv(6,6,9));
$plan = gauntletPlanHand($six, 6);
ok('six collections available -> one card each',
   count($plan) === 6 && max($plan) === 1, json_encode($plan));

$two = array(inv(1,1,10), inv(2,2,10));
$plan = gauntletPlanHand($two, 6);
ok('two collections -> evenly split three and three',
   $plan[1] === 3 && $plan[2] === 3, json_encode($plan));

$four = array(inv(1,1,10), inv(2,2,10), inv(3,3,10), inv(4,4,10));
$plan = gauntletPlanHand($four, 6);
ok('four collections -> 2,2,1,1 rather than 3,3',
   total($plan) === 6 && max($plan) === 2 && min($plan) === 1, json_encode($plan));

section('fallback: variety is preferred, not demanded');

$one = array(inv(7, 2, 20));
$plan = gauntletPlanHand($one, 6);
ok('a player with ONE collection still gets a full hand from it',
   $plan === array(7 => 6), json_encode($plan));

$thin = array(inv(1,1,2), inv(2,2,1));
$plan = gauntletPlanHand($thin, 6);
ok('fewer eligible NFTs than a full hand -> draw them all, do not hang',
   total($plan) === 3 && $plan[1] === 2 && $plan[2] === 1, json_encode($plan));

$exhausted = array(inv(1,1,1), inv(2,2,1), inv(3,3,1));
ok('exactly exhausting every collection terminates',
   total(gauntletPlanHand($exhausted, 6)) === 3);

section('project comes before collection');

/* Two collections of project 1, one of project 2. The project-2 collection
   must be reached on the FIRST lap -- project is what sets the odds, so a
   hand of three collections that are all one project is still a hand with
   one matchup in it. */
$order = array(inv(1,1,9), inv(2,1,9), inv(3,2,9));
$plan  = gauntletPlanHand($order, 2);
ok('a second project is reached before a second collection of the first',
   isset($plan[3]), json_encode($plan));
ok('and only two cards were planned', total($plan) === 2, json_encode($plan));

$lopsided = array(inv(1,1,9), inv(2,1,9), inv(3,1,9), inv(4,1,9), inv(5,2,9));
$plan = gauntletPlanHand($lopsided, 2);
ok('four collections of one project do not crowd out the only other project',
   isset($plan[5]), json_encode($plan));

/* The point of all of it: more than one distinct effective project in hand
   means Fast Forward can actually change the odds. Core projects 1-6 map to
   themselves, so two different core projects are two different matchups. */
ok('two core projects really do give different odds vs the same opponent',
   gauntletCalculateWinChance(1, 5) !== gauntletCalculateWinChance(6, 5),
   gauntletCalculateWinChance(1, 5) . ' vs ' . gauntletCalculateWinChance(6, 5));
ok('and the same core project gives identical odds, which is the old hand',
   gauntletCalculateWinChance(3, 6) === gauntletCalculateWinChance(3, 6));

section('bounds');

ok('empty inventory -> empty plan',       gauntletPlanHand(array(), 6) === array());
ok('size 0 -> empty plan',                gauntletPlanHand($six, 0) === array());
ok('negative size -> empty plan',         gauntletPlanHand($six, -3) === array());
ok('size 1 -> exactly one card',          total(gauntletPlanHand($six, 1)) === 1);
ok('a collection reporting 0 available is never drawn from',
   !isset(gauntletPlanHand(array(inv(1,1,0), inv(2,2,5)), 3)[1]),
   json_encode(gauntletPlanHand(array(inv(1,1,0), inv(2,2,5)), 3)));
ok('all-zero inventory terminates with an empty plan',
   gauntletPlanHand(array(inv(1,1,0), inv(2,2,0)), 6) === array());
ok('string counts from a mysqli row are cast, not compared as text',
   total(gauntletPlanHand(array(array('collection_id'=>'1','project_id'=>'2','available'=>'10')), 6)) === 6);

/* COUNT(*) comes back from mysqli as a STRING. '9' >= 10 is false either way,
   but '9' vs 9 under a loose compare is exactly the class of bug that makes a
   hand silently short, so the cast is pinned rather than assumed. */
$strs = array(array('collection_id'=>'3','project_id'=>'1','available'=>'2'),
              array('collection_id'=>'4','project_id'=>'1','available'=>'2'));
ok('all-string rows plan correctly', total(gauntletPlanHand($strs, 4)) === 4,
   json_encode(gauntletPlanHand($strs, 4)));

/* WHAT THE intval() IS ACTUALLY FOR. A numeric string compares numerically
   in PHP 8, so '2' and 2 behave identically and the cast looks decorative.
   A NON-numeric value is where they diverge: `0 >= 'lots'` compares as
   strings and is false, so an uncast planner would keep drawing from a
   collection forever. COUNT(*) never returns that, but the planner is a
   public function and this is the line that makes it safe to call. */
ok('a non-numeric availability is treated as zero, not as infinite',
   gauntletPlanHand(array(inv(1,1,'lots'), inv(2,2,4)), 3) === array(2 => 3),
   json_encode(gauntletPlanHand(array(inv(1,1,'lots'), inv(2,2,4)), 3)));


section('performance');

/* 6 collections x 9 available = 54, satisfiable, so it exits on the
   $taken < $size condition and never depends on the break. */
$t0 = microtime(true);
gauntletPlanHand($six, 54);
ok('a large satisfiable hand stays fast', (microtime(true) - $t0) < 1.0);

section('db.php wiring');

ok('the flat ORDER BY RAND() draw is gone from gauntletStartRun',
   strpos($src, 'ORDER BY RAND()
		LIMIT " . GAUNTLET_HAND_SIZE') === false);
ok('gauntletStartRun draws through the spreader',
   strpos($src, '$rows = gauntletPickHandNFTs($conn, $uid, GAUNTLET_HAND_SIZE, $used);') !== false);
ok('already-played NFTs are still excluded',
   strpos($src, '$used = gauntletGetUsedNFTIds($conn, $uid);') !== false);
ok('the per-collection draw is still random',
   preg_match('/WHERE n\.user_id = \$uid AND n\.collection_id = \$cid \$exclude\s*\n\s*ORDER BY RAND\(\)/', $src) === 1);
ok('the exclusion is applied to BOTH queries, not just the inventory one',
   substr_count(lift('gauntletPickHandNFTs', $src), '$exclude') >= 3);
ok('the inventory is shuffled before planning',
   strpos(lift('gauntletPickHandNFTs', $src), 'shuffle($inventory);') !== false);
ok('the drawn hand is shuffled so it is not laid out collection by collection',
   strpos(lift('gauntletPickHandNFTs', $src), 'shuffle($out);') !== false);
ok('exclude ids are intval-ed into the IN list',
   strpos(lift('gauntletPickHandNFTs', $src), "foreach (\$exclude_ids as \$x) \$ids[] = intval(\$x);") !== false);
ok('an empty draw still returns false from startRun',
   strpos($src, 'if (!$rows) return false;') !== false);

restore_error_handler();
echo "\n-------------------------------------------\n";
echo "pass: $pass   fail: $fail\n";
if ($notices) { echo "NOTICES:\n"; foreach (array_unique($notices) as $n) echo "  $n\n"; }
if ($fails) { echo "failed:\n"; foreach ($fails as $f) echo "  - $f\n"; }
exit($fail || $notices ? 1 : 0);
