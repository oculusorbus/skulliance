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
 *   - GUESS ORDER. The labels come out of the puzzle's stored options, which
 *     were SHUFFLED before they were stored, so options order is not guess
 *     order. The fixture here deliberately stores them in a different order
 *     from the guesses; a rewrite that walks the options instead of the
 *     guess list looks right on a two-option day and is wrong the rest.
 *   - THE PROJECT IS ON THE LABEL. A collection name alone identifies
 *     nothing ("Season 1"), which is exactly why the game's own buttons show
 *     the project above the collection.
 *   - ...BUT NOT WHEN IT REPEATS THE COLLECTION. "Sinder Skullz (Sinder
 *     Skullz)" reads as a bug, and single-collection projects are common.
 *   - NO LOOKUP QUERY. The run row already holds the labels the player was
 *     shown; going back to `collections` costs a query on the path of every
 *     lost run AND can describe a choice nobody was offered, if the
 *     collection was renamed after the guess.
 *   - THE ANSWER IS LABELLED THE SAME WAY. Naming the project on one line
 *     and not the other is worse than naming it on neither.
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

/* Brace-match the real bodies rather than slicing to a marker: a marker
   moves the first time somebody adds a line to the end of a function. */
function ob_lift($src, $sig, $what) {
	$at = strpos($src, $sig);
	ok($at !== false, "$what is gone from obscura-lib.php");
	if ($at === false) { echo "\nFAILED\n"; exit(1); }
	$i = strpos($src, '{', $at); $depth = 0; $end = $i;
	for ($n = strlen($src); $i < $n; $i++) {
		if ($src[$i] === '{') $depth++;
		elseif ($src[$i] === '}') { $depth--; if ($depth === 0) { $end = $i; break; } }
	}
	return substr($src, $at, $end - $at + 1);
}
eval(ob_lift($src, 'function obscuraOptionLabel(', 'obscuraOptionLabel()'));
eval(ob_lift($src, 'function obscuraAnnounceRunEnd(', 'obscuraAnnounceRunEnd()'));

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
	public $queries = array();
	function query($sql) {
		$this->queries[] = $sql;
		if (strpos($sql, 'FROM users') !== false) {
			return new ObRes(array(array('username'=>'ripper', 'discord_id'=>'4242', 'avatar'=>'ab12')));
		}
		return new ObRes(array());
	}
}

function ob_conn() { return new ObConn(); }

/* The offered buttons, in the order obscuraSetPuzzle() stored them -- which
   shuffle() guarantees is NOT the order they were tapped in. 11 is a project
   whose only collection carries the project's own name; 14's project name
   differs from the collection, which is the case the label exists for. */
$OPTIONS = array(
	array('id'=>14, 'name'=>'Pavia',         'project'=>'Pavia World'),
	array('id'=>11, 'name'=>'Skulliance',    'project'=>'Skulliance'),
	array('id'=>13, 'name'=>'Chilled Kongs', 'project'=>'Chilled Apes'),
	array('id'=>12, 'name'=>'Clay Nation',   'project'=>'Clay Nation'),
);
$reveal = array('art' => '/staking/nfts/1/skull-0912.png', 'name' => 'Skull #912');

function ob_run($conn, $wrong, $streak = 9, $solves = 8, $best = 20, $answer_id = 11) {
	global $POST, $reveal, $OPTIONS;
	$POST = null;
	obscuraAnnounceRunEnd($conn, 7, $streak, $solves, $best, $reveal, 'Skulliance',
		array('wrong' => $wrong, 'options' => $OPTIONS, 'answer_id' => $answer_id));
	return $POST;
}

/* ---------------------------------------------------------------- *
 * Three wrong guesses, in the order they were made.
 * ---------------------------------------------------------------- */
echo "the guesses are on the post, in guess order, with their projects\n";
$conn = ob_conn();
$p = ob_run($conn, array(12, 13, 14));
ok($p !== null, 'nothing was posted at all');
echo "  " . str_replace("\n", "\n  ", (string)($p['desc'] ?? '')) . "\n";
ok(strpos($p['desc'], '**They guessed:**') !== false, 'the post does not say what they guessed');
ok(strpos($p['desc'], 'Clay Nation, then Chilled Kongs (Chilled Apes), then Pavia (Pavia World)') !== false,
   'the guesses are not in guess order with their projects (the options are stored in a different order here, as shuffle() guarantees they are live)');
ok(strpos($p['desc'], 'Clay Nation (Clay Nation)') === false,
   'a project that only repeats the collection name is being printed twice');
/* No lookup at all: the run row already holds what the player was shown. */
$cq = 0;
foreach ($conn->queries as $q) if (strpos($q, 'FROM collections') !== false) $cq++;
ok($cq === 0, "the names cost $cq collections quer(y/ies); the puzzle's own stored options already carry them, with the project");

echo "\nthe answer is labelled the same way, and distinguishable from the guesses\n";
ok(strpos($p['desc'], '**It was:** Skulliance') !== false,
   'the answer collection is not labelled "It was:" -- with several collection names on the post, "Collection:" says nothing');
ok(strpos($p['desc'], '**Stumped by:** Skull #912') !== false, 'the piece that beat them is no longer named');
ok(strpos($p['desc'], '**Solved this run:** 8') !== false, 'the solve count fell off the post');
ok($p['img'] === 'https://skulliance.io/staking/nfts/1/skull-0912.png',
   'the artwork is no longer attached -- the guesses are only interesting next to the picture');
/* Order on the post: the answer, then what they called it, then the tally. */
$d = $p['desc'];
ok(strpos($d, 'It was:') < strpos($d, 'They guessed:')
   && strpos($d, 'They guessed:') < strpos($d, 'Solved this run:'),
   'the guess line is not sitting between the answer and the run tally');
/* An answer whose project differs must carry it too, or the two lines are
   not comparable at a glance. */
$p2 = ob_run(ob_conn(), array(11), 9, 8, 20, 14);
ok(strpos($p2['desc'], '**It was:** Pavia (Pavia World)') !== false,
   'the answer line drops the project that the guess line prints');

echo "\nthe label itself\n";
ok(obscuraOptionLabel(array('name'=>'Season 1', 'project'=>'Derp Birds')) === 'Season 1 (Derp Birds)',
   'a collection is not labelled with its project');
ok(obscuraOptionLabel(array('name'=>'Pavia', 'project'=>'Pavia')) === 'Pavia',
   'a project repeating its collection name is printed twice');
ok(obscuraOptionLabel(array('name'=>'Pavia', 'project'=>'pavia')) === 'Pavia',
   'the repeat check is case-sensitive, so a difference of capitalisation prints the name twice');
ok(obscuraOptionLabel(array('name'=>'Pavia', 'project'=>'')) === 'Pavia',
   'a missing project (an option stored before the field existed) breaks the label');
ok(obscuraOptionLabel(array('name'=>'', 'project'=>'Pavia')) === '',
   'a nameless option produces a label instead of dropping out');

/* ---------------------------------------------------------------- *
 * The shapes that are not three names.
 * ---------------------------------------------------------------- */
echo "\none attempt, one name\n";
$p = ob_run(ob_conn(), array(13));
ok(strpos($p['desc'], '**They guessed:** Chilled Kongs (Chilled Apes)') !== false, 'a single guess is not named');
ok(strpos($p['desc'], ', then') === false, 'a single guess printed a list separator');

echo "\na guess that is not among the stored options\n";
$p = ob_run(ob_conn(), array(12, 998, 14));
ok(strpos($p['desc'], 'Clay Nation, then Pavia (Pavia World)') !== false,
   'an unknown id did not drop cleanly out of the line');
ok(strpos($p['desc'], '998') === false, 'a bare collection id was printed for an option that is not in the list');
ok(strpos($p['desc'], 'then , then') === false && strpos($p['desc'], ':** ,') === false,
   'an unknown id left an empty name in the list');

echo "\nno guesses to report\n";
$p = ob_run(ob_conn(), array());
ok(strpos($p['desc'], 'They guessed:') === false, 'an empty guess list still printed the line');
ok(strpos($p['desc'], '**It was:** Skulliance') !== false, 'the rest of the post did not survive an empty guess list');
ok(strpos($p['desc'], "\n\n\xe2\x9c\x85") === false, 'the removed line left a blank gap in the post');
/* The parameter defaults, so an old call site is still a valid post -- and
   the answer falls back to the name it is passed. */
$POST = null;
obscuraAnnounceRunEnd(ob_conn(), 7, 9, 8, 20, $reveal, 'Skulliance');
ok($POST !== null && strpos($POST['desc'], 'They guessed:') === false,
   'calling without the puzzle array no longer produces the post it did before');
ok($POST !== null && strpos($POST['desc'], '**It was:** Skulliance') !== false,
   'without the puzzle array the answer line lost its fallback name');

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
$g   = strpos($lib, 'function obscuraGuess(');
ok($g !== false, 'obscuraGuess() is gone from obscura-lib.php');
ok(preg_match('/obscuraAnnounceRunEnd\(\$conn,\s*\$user_id,\s*\$streak,\s*\$run_solves,\s*intval\(\$run\[.best_streak.\]\),\s*\$reveal,\s*\$name,\s*array\(\s*.wrong.\s*=>\s*\$wrong,\s*.options.\s*=>\s*\$offered_opts,\s*.answer_id.\s*=>\s*\$answer\s*\)\s*\);/s', $lib),
   'obscuraGuess() no longer passes the guesses, the offered options and the answer id to obscuraAnnounceRunEnd()');
/* The options the post labels from must be the SAME decode that gated the
   guess -- a second json_decode of the same column is a second thing to keep
   in step for no gain, and a stale one is how the post ends up describing
   buttons from the previous puzzle. */
$guessbody = substr($lib, $g, strpos($lib, "\nfunction ", $g + 10) !== false
	? strpos($lib, "\nfunction ", $g + 10) - $g : strlen($lib) - $g);
ok(substr_count($guessbody, "json_decode(\$run['options'], true)") === 1,
   "obscuraGuess() decodes \$run['options'] more than once; the gate and the post must read the same list");
/* $wrong must already carry the final guess by the time it is handed over --
   otherwise the post names every wrong call EXCEPT the one that ended the run. */
$ap = strpos($lib, '$wrong[] = $guess;', $g);
$an = strpos($lib, 'obscuraAnnounceRunEnd(', $g);
ok($ap !== false && $an !== false && $ap < $an,
   'the final guess is appended to $wrong AFTER the announce, so the guess that ended the run would be missing from the post');

echo "\n" . ($fail ? "FAILED ($fail)\n" : "run-end post: ok\n");
exit($fail ? 1 : 0);
