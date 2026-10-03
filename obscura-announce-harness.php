<?php
/* obscura-announce-harness.php - CLI only. No database, no network.
 *
 * The Obscura run-end post carries the artwork that beat the player, and now
 * also the name they put to it. That line is only worth anything if it is
 * RIGHT and IN ORDER: the picture is sitting directly underneath it, so a
 * mis-ordered or mis-resolved guess is not a cosmetic slip -- it tells the
 * channel somebody mistook a set for one they never named.
 *
 * What this pins, and why each one can actually break:
 *
 *   - GUESS ORDER. The names are fetched with `WHERE id IN (...)`, and MySQL
 *     makes no promise about the order rows come back in. The fake connection
 *     here returns them REVERSED on purpose; a rewrite that iterates the
 *     result set instead of the id list passes against a real database most
 *     of the time and fails here every time.
 *   - ONE QUERY. Resolving a name per guess inside a loop would work, and
 *     would put up to three extra queries on the path of every lost run.
 *   - THE ANSWER IS LABELLED. There are two collection names on the post now.
 *     "Collection:" against both is ambiguous, so the answer says "It was:".
 *   - A DELETED COLLECTION DROPS OUT, rather than printing an empty name or
 *     a bare numeric id into Discord.
 *   - NO GUESSES, NO LINE. An announce called without the array (the default)
 *     must produce the post it produced before, not an empty bullet.
 *
 * Usage: php obscura-announce-harness.php
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

$fail = 0;
function ok($cond, $what) { global $fail; if (!$cond) { $fail++; echo "  FAIL  $what\n"; } }

function no_comments($s) {
	$s = preg_replace('!/\*.*?\*/!s', '', $s);
	$s = preg_replace('~(?<![:/])//.*$~m', '', $s);
	return $s;
}

/* ---------------------------------------------------------------- *
 * The real function, lifted out of obscura-lib.php.
 * ---------------------------------------------------------------- */
$src = file_get_contents(__DIR__ . '/obscura-lib.php');

if (preg_match("/define\('OBSCURA_ANNOUNCE_MIN_STREAK',\s*(\d+)\)/", $src, $m)) {
	define('OBSCURA_ANNOUNCE_MIN_STREAK', (int)$m[1]);
}
ok(defined('OBSCURA_ANNOUNCE_MIN_STREAK'), 'OBSCURA_ANNOUNCE_MIN_STREAK is gone from obscura-lib.php');

$at = strpos($src, 'function obscuraAnnounceRunEnd(');
ok($at !== false, 'obscuraAnnounceRunEnd() is gone from obscura-lib.php');
if ($at === false) { echo "\nFAILED\n"; exit(1); }
/* Brace-match the real body rather than slicing to a marker: a marker moves
   the first time somebody adds a line to the end of the function. */
$i = strpos($src, '{', $at); $depth = 0; $end = $i;
for ($n = strlen($src); $i < $n; $i++) {
	if ($src[$i] === '{') $depth++;
	elseif ($src[$i] === '}') { $depth--; if ($depth === 0) { $end = $i; break; } }
}
eval(substr($src, $at, $end - $at + 1));

/* ---------------------------------------------------------------- *
 * Stubs. Everything the function reaches for that is not the post.
 * ---------------------------------------------------------------- */
$POST = null;
function discordmsg($title, $desc, $img, $url, $channel, $avatar, $colour, $author, $footer) {
	global $POST;
	$POST = array('title'=>$title, 'desc'=>$desc, 'img'=>$img, 'channel'=>$channel, 'footer'=>$footer);
}
function obscuraWeeklyLeaderUserId($conn) { return 0; }

class ObRes {
	public $num_rows; private $rows; private $i = 0;
	function __construct($rows) { $this->rows = array_values($rows); $this->num_rows = count($this->rows); }
	function fetch_assoc() { return isset($this->rows[$this->i]) ? $this->rows[$this->i++] : null; }
}
class ObConn {
	public $collections = array();
	public $queries = array();
	function query($sql) {
		$this->queries[] = $sql;
		if (strpos($sql, 'FROM users') !== false) {
			return new ObRes(array(array('username'=>'ripper', 'discord_id'=>'4242', 'avatar'=>'ab12')));
		}
		if (strpos($sql, 'FROM collections') !== false) {
			preg_match('/IN \(([0-9,]+)\)/', $sql, $mm);
			$ids = $mm ? array_map('intval', explode(',', $mm[1])) : array();
			$rows = array();
			foreach ($ids as $id) {
				if (isset($this->collections[$id])) $rows[] = array('id'=>$id, 'name'=>$this->collections[$id]);
			}
			/* REVERSED ON PURPOSE -- see the header. */
			return new ObRes(array_reverse($rows));
		}
		return new ObRes(array());
	}
}

function ob_conn() {
	$c = new ObConn();
	$c->collections = array(
		11 => 'Skulliance',
		12 => 'Clay Nation',
		13 => 'Chilled Kongs',
		14 => 'Pavia',
	);
	return $c;
}
$reveal = array('art' => '/staking/nfts/1/skull-0912.png', 'name' => 'Skull #912');

function ob_run($conn, $wrong, $streak = 9, $solves = 8, $best = 20) {
	global $POST, $reveal;
	$POST = null;
	obscuraAnnounceRunEnd($conn, 7, $streak, $solves, $best, $reveal, 'Skulliance', $wrong);
	return $POST;
}

/* ---------------------------------------------------------------- *
 * Three wrong guesses, in the order they were made.
 * ---------------------------------------------------------------- */
echo "the guesses are on the post, in guess order\n";
$conn = ob_conn();
$p = ob_run($conn, array(12, 13, 14));
ok($p !== null, 'nothing was posted at all');
echo "  " . str_replace("\n", "\n  ", (string)($p['desc'] ?? '')) . "\n";
ok(strpos($p['desc'], '**They guessed:**') !== false, 'the post does not say what they guessed');
ok(strpos($p['desc'], 'Clay Nation, then Chilled Kongs, then Pavia') !== false,
   'the guesses are not in guess order (the result set is returned reversed here, as a real IN(...) may be)');
ok(strpos($p['desc'], '11') === false || strpos($p['desc'], 'Clay Nation') !== false,
   'a collection id leaked onto the post instead of a name');
/* One query for the names, not one per guess. */
$cq = 0;
foreach ($conn->queries as $q) if (strpos($q, 'FROM collections') !== false) $cq++;
ok($cq === 1, "the names cost $cq queries; one IN (...) is enough for all of them");

echo "\nthe answer is still labelled, and distinguishable from the guesses\n";
ok(strpos($p['desc'], '**It was:** Skulliance') !== false,
   'the answer collection is not labelled "It was:" -- with two collection names on the post, "Collection:" says nothing');
ok(strpos($p['desc'], '**Stumped by:** Skull #912') !== false, 'the piece that beat them is no longer named');
ok(strpos($p['desc'], '**Solved this run:** 8') !== false, 'the solve count fell off the post');
ok($p['img'] === 'https://skulliance.io/staking/nfts/1/skull-0912.png',
   'the artwork is no longer attached -- the guesses are only interesting next to the picture');
/* Order on the post: the answer, then what they called it, then the tally. */
$d = $p['desc'];
ok(strpos($d, 'It was:') < strpos($d, 'They guessed:')
   && strpos($d, 'They guessed:') < strpos($d, 'Solved this run:'),
   'the guess line is not sitting between the answer and the run tally');

/* ---------------------------------------------------------------- *
 * The shapes that are not three names.
 * ---------------------------------------------------------------- */
echo "\none attempt, one name\n";
$p = ob_run(ob_conn(), array(13));
ok(strpos($p['desc'], '**They guessed:** Chilled Kongs') !== false, 'a single guess is not named');
ok(strpos($p['desc'], ', then') === false, 'a single guess printed a list separator');

echo "\na collection that no longer exists\n";
$p = ob_run(ob_conn(), array(12, 998, 14));
ok(strpos($p['desc'], 'Clay Nation, then Pavia') !== false,
   'a deleted collection did not drop cleanly out of the line');
ok(strpos($p['desc'], '998') === false, 'a bare collection id was printed for a row that no longer exists');
ok(strpos($p['desc'], 'then , then') === false && strpos($p['desc'], ':** ,') === false,
   'a deleted collection left an empty name in the list');

echo "\nno guesses to report\n";
$p = ob_run(ob_conn(), array());
ok(strpos($p['desc'], 'They guessed:') === false, 'an empty guess list still printed the line');
ok(strpos($p['desc'], '**It was:** Skulliance') !== false, 'the rest of the post did not survive an empty guess list');
ok(strpos($p['desc'], "\n\n✅") === false, 'the removed line left a blank gap in the post');
/* The parameter defaults, so an old call site is still a valid post. */
$POST = null;
obscuraAnnounceRunEnd(ob_conn(), 7, 9, 8, 20, $reveal, 'Skulliance');
ok($POST !== null && strpos($POST['desc'], 'They guessed:') === false,
   'calling without the guess array no longer produces the post it did before');

echo "\nthe rest of the gate still holds\n";
$p = ob_run(ob_conn(), array(12), OBSCURA_ANNOUNCE_MIN_STREAK - 1);
ok($p === null, 'a run below OBSCURA_ANNOUNCE_MIN_STREAK was announced');
$p = ob_run(ob_conn(), array(12), 25, 8, 20);
ok(strpos($p['desc'], 'New personal best') !== false, 'a new best is no longer badged');
ok($p['channel'] === 'obscura', 'the post is going to the wrong channel: ' . $p['channel']);

/* ---------------------------------------------------------------- *
 * The call site in obscuraGuess() actually hands the guesses over.
 * ---------------------------------------------------------------- */
echo "\nthe losing guess reaches the announce\n";
$lib = no_comments($src);
ok(preg_match('/obscuraAnnounceRunEnd\(\$conn,\s*\$user_id,\s*\$streak,\s*\$run_solves,\s*intval\(\$run\[.best_streak.\]\),\s*\$reveal,\s*\$name,\s*\$wrong\);/s', $lib),
   'obscuraGuess() no longer passes $wrong to obscuraAnnounceRunEnd()');
/* $wrong must already carry the final guess by the time it is handed over --
   otherwise the post names every wrong call EXCEPT the one that ended the run. */
$g  = strpos($lib, 'function obscuraGuess(');
$ap = strpos($lib, '$wrong[] = $guess;', $g);
$an = strpos($lib, 'obscuraAnnounceRunEnd(', $g);
ok($ap !== false && $an !== false && $ap < $an,
   'the final guess is appended to $wrong AFTER the announce, so the guess that ended the run would be missing from the post');

echo "\n" . ($fail ? "FAILED ($fail)\n" : "run-end post: ok\n");
exit($fail ? 1 : 0);
