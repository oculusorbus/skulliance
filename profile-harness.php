<?php
/* profile-harness.php — CLI only. Exercises profile-lib.php with no database.
 *
 * profile.php is PUBLIC and takes a username from the query string, so
 * these nine queries run for anyone who can load a URL. The thing worth
 * pinning down is not the arithmetic -- it is that a game which has not
 * been played, or a table that does not exist, produces an absent row
 * rather than a zero, a warning, or a blank page.
 *
 * Usage: php profile-harness.php */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

$fail = 0;
function ok($cond, $what) { global $fail; if (!$cond) { $fail++; echo "  FAIL  $what\n"; } }

$ROWS = array();   // table fragment => row to return, or false for "query failed"

class PRes {
	public $num_rows; private $rows;
	function __construct($rows) { $this->rows = $rows; $this->num_rows = count($rows); }
	function fetch_assoc() { return array_shift($this->rows); }
}
class PConn {
	public $seen = array();
	function query($sql) {
		global $ROWS;
		$flat = preg_replace('/\s+/', ' ', $sql);
		$this->seen[] = $flat;
		foreach ($ROWS as $frag => $row) {
			if (strpos($flat, $frag) !== false) return ($row === false) ? false : new PRes(array($row));
		}
		return new PRes(array());
	}
}
require __DIR__ . '/profile-lib.php';

echo "a player who has done nothing\n";
$ROWS = array();
$c = new PConn();
$r = profile_game_record($c, 42);
ok($r === array(), 'no games played means no tiles, not nine zeroes');
ok(count($c->seen) === 8, 'one query per game, and no more');
ok(!preg_grep('/user_id = .42./', $c->seen) === false, 'every query is scoped to the user');
$unscoped = array_filter($c->seen, function ($q) { return strpos($q, "user_id = '42'") === false; });
ok(!$unscoped, 'no query reads another player');

echo "\nzero plays is not the same as a row of zeroes\n";
$ROWS = array('FROM cryptcrawls' => array('wins' => '0', 'losses' => '0', 'depth' => null));
$r = profile_game_record(new PConn(), 42);
ok($r === array(), 'a game with 0 wins and 0 losses is dropped, not shown as 0');

$ROWS = array('FROM dhc_fighters' => array('fighters' => '0', 'best' => null, 'total' => null));
ok(profile_game_record(new PConn(), 42) === array(), 'nor a collection with nothing in it');

echo "\na table that is not there\n";
/* A game not yet deployed, or a table renamed. mysqli_report(MYSQLI_REPORT_OFF)
   is set platform-wide so query() returns false; this must survive it. */
$ROWS = array('FROM guardians_scores' => false, 'FROM obscura_scores' => false);
$r = profile_game_record(new PConn(), 42);
ok(is_array($r), 'a failed query returns an array rather than blowing up');
ok($r === array(), 'and contributes nothing');

$ROWS = array('FROM guardians_scores' => false,
              'FROM cryptcrawls' => array('wins' => '4', 'losses' => '2', 'depth' => '17'));
$r = profile_game_record(new PConn(), 42);
ok(count($r) === 1, 'one broken table does not take the working ones down with it');
ok($r[0]['key'] === 'cryptcrawl', 'and the working one still renders');

echo "\nwhat a real record looks like\n";
$ROWS = array(
	'FROM dhc_fighters'      => array('fighters' => '12', 'best' => '880', 'total' => '7400'),
	'FROM dhc_arena_battles' => array('wins' => '31', 'losses' => '9', 'chain' => '7'),
	'FROM guardians_scores'  => array('sieges' => '4', 'wave' => '18', 'held' => '3'),
	'FROM cryptcrawls'       => array('wins' => '4', 'losses' => '11', 'depth' => '23'),
	'FROM cryptconquests'    => array('wins' => '1', 'losses' => '6', 'best' => '14'),
	'FROM gauntlets'         => array('runs' => '3', 'wins' => '14', 'losses' => '3'),
	'FROM obscura_scores'    => array('runs' => '9', 'streak' => '22', 'solves' => '140'),
	'FROM skull_racer_runs'  => array('races' => '1'),
);
$r = profile_game_record(new PConn(), 42);
ok(count($r) === 8, 'all eight games appear');
$by = array(); foreach ($r as $g) $by[$g['key']] = $g;
ok($by['dhcfighters']['stat'] === '12' && $by['dhcfighters']['label'] === 'Fighters', 'fighters count and plural');
ok($by['skullracer']['label'] === 'Race', 'a single race is singular');
ok($by['obscura']['stat'] === '22', 'obscura leads with the streak, which is what it is about');
ok($by['guardians']['stat'] === '18', 'guardians leads with the deepest wave');
ok(strpos($by['dhcarena']['sub'][1], 'Best chain 7') !== false, 'supporting figures are formatted');

$ROWS['FROM dhc_fighters'] = array('fighters' => '1', 'best' => '880', 'total' => '880');
$r = profile_game_record(new PConn(), 42);
$by = array(); foreach ($r as $g) $by[$g['key']] = $g;
ok($by['dhcfighters']['label'] === 'Fighter', 'one fighter is singular too');

echo "\nthousands separators\n";
$ROWS = array('FROM dhc_fighters' => array('fighters' => '1250', 'best' => '9900', 'total' => '1234567'));
$r = profile_game_record(new PConn(), 42);
ok($r[0]['stat'] === '1,250', 'the headline is formatted');
ok(strpos($r[0]['sub'][1], '1,234,567') !== false, 'and so are the supporting figures');

echo "\nguests\n";
ok(profile_game_record(new PConn(), 0) === array(), 'user 0 reads nothing');
ok(profile_game_record(new PConn(), -3) === array(), 'nor a negative id');
$c = new PConn(); profile_game_record($c, 0);
ok(count($c->seen) === 0, 'and issues no queries at all for one');

echo "\n";
if ($fail) { echo "$fail check(s) FAILED\n"; exit(1); }
echo "all profile-lib checks passed\n";
