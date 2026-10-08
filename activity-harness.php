<?PHP
/*
 * ACTIVITY FEED HARNESS
 *
 *   php activity-harness.php
 *
 * Drives the real activity-lib.php against a fake mysqli -- FakeConn extends
 * mysqli so it satisfies activity_log()'s `instanceof mysqli` gate without a
 * database, and records the SQL instead of running it. The reads are asserted
 * on the SQL they build, which is where their whole behaviour lives (the scope
 * and channel filters); faking mysqli_result to assert on rows would test the
 * fake, not the code.
 *
 * The webhooks.php integration is checked at source level, deliberately. The
 * things that matter there -- that the write sits inside the 2xx branch and
 * that alertAdmin() is excluded -- are facts about where the call is placed,
 * and the only way to reach it functionally is a live Discord POST.
 *
 * Every assertion below was confirmed to FAIL when the line it covers is
 * mutated. An assertion that passes either way tests nothing.
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

/* ---- the fake connection ------------------------------------------------- */

class FakeConn extends mysqli {
	public array $sqls = array();
	public $ret = true;                 /* what query() hands back */
	public function __construct() {}    /* no connect */
	/*
	 * NO FAKE affected_rows, AND IT IS NOT FOR WANT OF TRYING. On PHP 8.5
	 * mysqli::$affected_rows is read-only, so a subclass cannot assign it;
	 * unset()ing it on the instance to route reads through __get() gets
	 * further and then throws "Property access is not allowed yet" from
	 * mysqli itself, because the handle has never connected.
	 *
	 * So activity_prune()'s success COUNT is the one thing here that cannot
	 * be driven without a live database. Its -1 path, its SQL, and its
	 * bounds are all exercised below; the count itself is asserted at source
	 * level, and this comment exists so nobody reads that as laziness.
	 */
	#[\ReturnTypeWillChange]
	public function query($query, $result_mode = MYSQLI_STORE_RESULT) {
		$this->sqls[] = $query;
		return $this->ret;
	}
	#[\ReturnTypeWillChange]
	public function real_escape_string($string) {
		/* Good enough to prove escaping is REACHED. Not a real escaper. */
		return str_replace(array("\\", "'"), array("\\\\", "\\'"), (string) $string);
	}
	public function last() { return $this->sqls ? $this->sqls[count($this->sqls) - 1] : ''; }
	/* JUST THE WHERE CLAUSE. The first version of this harness asserted
	   strpos($sql, 'user_id') === false for the "all" scope and passed only
	   because... it didn't: user_id and user_id2 are SELECTed columns, so the
	   needle was always present and the assertion always failed. The filters
	   are what is under test, so the filters are what gets searched. */
	public function lastWhere() {
		$sql = $this->last();
		$at  = strpos($sql, ' WHERE ');
		if ($at === false) return '';
		$end = strpos($sql, 'ORDER BY', $at);
		return trim(substr($sql, $at + 7, ($end === false ? strlen($sql) : $end) - $at - 7));
	}
}

/* A notice or warning from the library is a failure in its own right: this
   platform runs display_errors ON, and activity_log() is called from inside
   the request that carries a player's own result back to them. */
$notices = array();
set_error_handler(function ($no, $str, $file, $line) use (&$notices) {
	/* Suppressed diagnostics (@$conn->query, @filemtime) are intentional and
	   must not count -- set_error_handler() is still called for them. */
	if (!(error_reporting() & $no)) return true;
	$notices[] = "$str @ $file:$line";
	return true;
});

require_once __DIR__ . '/activity-lib.php';

/* ---------------------------------------------------------------------------
 * 1. activity_log -- what it refuses to write
 * ------------------------------------------------------------------------ */
section('activity_log: refusals');

$GLOBALS['conn'] = null;
ok('no connection -> false, no throw', activity_log('missions', 'T', 'D', '', '', '', '', null, null, 5) === false);

$conn = new FakeConn();
$GLOBALS['conn'] = $conn;

ok('empty title AND description -> no row',
   activity_log('missions', '', '', 'https://x', '', '', '00C8A0', null, null, 5) === false && $conn->sqls === array());

$conn->sqls = array();
ok('whitespace-only title and description -> no row',
   activity_log('missions', "  ", "\n\t ", '', '', '', '', null, null, 5) === false && $conn->sqls === array());

$conn->sqls = array();
ok('title alone IS enough',
   activity_log('missions', 'Mission complete', '', '', '', '', '', null, null, 5) === true && count($conn->sqls) === 1);

$conn->sqls = array();
ok('description alone IS enough',
   activity_log('missions', '', 'Somebody did a thing', '', '', '', '', null, null, 5) === true && count($conn->sqls) === 1);

$conn->sqls = array();
$conn->ret = false;                 /* the table does not exist */
ok('failed INSERT -> false, not an exception',
   activity_log('missions', 'T', 'D', '', '', '', '', null, null, 5) === false);
$conn->ret = true;

/* ---------------------------------------------------------------------------
 * 2. activity_log -- the row it builds
 * ------------------------------------------------------------------------ */
section('activity_log: the row');

$conn->sqls = array();
activity_log('dhcarena', 'Arena', 'Desc', 'https://skulliance.io/staking/dhcarena.php',
	'https://img/a.png', 'https://thumb/b.png', '00C8A0',
	array('name' => 'Oculus', 'icon_url' => 'https://av/c.png'),
	array('text' => 'footer line'), 7, 9);
$sql = $conn->last();

ok('inserts into activity',            strpos($sql, 'INSERT INTO activity') === 0);
ok('author name is lifted',            strpos($sql, "'Oculus'") !== false);
ok('author icon is lifted',            strpos($sql, 'https://av/c.png') !== false);
ok('footer text is lifted',            strpos($sql, "'footer line'") !== false);
ok('both user ids are written',        preg_match('/,\s*7,\s*9,\s*NOW\(\)\)/', $sql) === 1, $sql);
ok('created_at is the server NOW()',   strpos($sql, 'NOW()') !== false);

/* $author is ["name"=>..] with NO icon_url on several real call sites. */
$conn->sqls = array();
activity_log('store', 'Bought', 'D', '', '', '', '', array('name' => 'Nobody'), null, 1);
ok('author with no icon_url does not notice', $conn->last() !== '' && !$notices, implode('; ', $notices));

/* $author is null on most call sites, $footer on nearly all. */
$conn->sqls = array();
activity_log('missions', 'T', 'D', '', '', '', '', null, null, 0);
ok('null author/footer -> empty strings, no notice', strpos($conn->last(), "'', '', ''") !== false && !$notices,
   implode('; ', $notices));

section('activity_log: the second player');

$conn->sqls = array();
activity_log('dhcarena', 'T', 'D', '', '', '', '', null, null, 4, 4);
ok('same player on both sides collapses user_id2 to 0',
   preg_match('/,\s*4,\s*0,\s*NOW\(\)\)/', $conn->last()) === 1, $conn->last());

$conn->sqls = array();
activity_log('dhcarena', 'T', 'D', '', '', '', '', null, null, -3, -8);
ok('negative ids are floored at 0',
   preg_match('/,\s*0,\s*0,\s*NOW\(\)\)/', $conn->last()) === 1, $conn->last());

$conn->sqls = array();
activity_log('dhcarena', 'T', 'D', '', '', '', '', null, null, '7', '9');
ok('numeric strings are cast, not quoted',
   preg_match('/,\s*7,\s*9,\s*NOW\(\)\)/', $conn->last()) === 1, $conn->last());

section('activity_log: escaping and truncation');

$conn->sqls = array();
activity_log('missions', "O'Treat", "it's done", '', '', '', '', null, null, 1);
ok('quotes in the title are escaped',       strpos($conn->last(), "'O\\'Treat'") !== false, $conn->last());
ok('quotes in the description are escaped', strpos($conn->last(), "it\\'s done") !== false, $conn->last());

$conn->sqls = array();
/* Over every column's width at once. In MySQL strict mode an over-length
   value is an ERROR, not a truncation, so a long image URL would cost the
   whole row -- the library trims before the insert for that reason. */
activity_log(str_repeat('c', 80), str_repeat('t', 900), str_repeat('d', 9000),
	'https://x/' . str_repeat('u', 900), 'https://i/' . str_repeat('i', 900),
	'https://t/' . str_repeat('h', 900), str_repeat('f', 40),
	array('name' => str_repeat('n', 400), 'icon_url' => 'https://a/' . str_repeat('a', 900)),
	array('text' => str_repeat('r', 900)), 1);
$sql = $conn->last();
preg_match_all("/'((?:[^'\\\\]|\\\\.)*)'/", $sql, $m);
$vals = $m[1];
ok('channel trimmed to 32',      strlen($vals[0]) === 32,  strlen($vals[0]));
ok('title trimmed to 255',       strlen($vals[1]) === 255, strlen($vals[1]));
ok('description trimmed to 4000',strlen($vals[2]) === 4000,strlen($vals[2]));
ok('url trimmed to 512',         strlen($vals[3]) === 512, strlen($vals[3]));
ok('image_url trimmed to 512',   strlen($vals[4]) === 512, strlen($vals[4]));
ok('thumbnail trimmed to 512',   strlen($vals[5]) === 512, strlen($vals[5]));
ok('color trimmed to 8',         strlen($vals[6]) === 8,   strlen($vals[6]));
ok('author_name trimmed to 190', strlen($vals[7]) === 190, strlen($vals[7]));
ok('author_icon trimmed to 512', strlen($vals[8]) === 512, strlen($vals[8]));
ok('footer_text trimmed to 255', strlen($vals[9]) === 255, strlen($vals[9]));

$conn->sqls = array();
/* MULTIBYTE. Titles across the platform are full of emoji -- '⚔️ Arena'.
   A byte-wise substr() at the limit would cut one in half and produce
   invalid UTF-8, which MySQL rejects for the whole row on utf8mb4. */
activity_log('missions', str_repeat('⚔️', 400), 'D', '', '', '', '', null, null, 1);
$sql = $conn->last();
preg_match_all("/'((?:[^'\\\\]|\\\\.)*)'/", $sql, $m);
ok('emoji title is cut on a character boundary',
   mb_strlen($m[1][1]) === 255 && mb_check_encoding($m[1][1], 'UTF-8'), mb_strlen($m[1][1]));

/* ---------------------------------------------------------------------------
 * 3. activity_actor
 * ------------------------------------------------------------------------ */
section('activity_actor');

unset($_SESSION);
ok('no session at all -> 0', activity_actor() === 0);
$_SESSION = array();
ok('no userData -> 0', activity_actor() === 0);
$_SESSION['userData'] = array('name' => 'x');
ok('userData without user_id -> 0 (the real post-login state)', activity_actor() === 0);
$_SESSION['userData']['user_id'] = '0';
ok('user_id 0 (guest) -> 0', activity_actor() === 0);
$_SESSION['userData']['user_id'] = '42';
ok('user_id string is cast to int', activity_actor() === 42);

/* ---------------------------------------------------------------------------
 * 4. activity_recent -- the three scopes
 * ------------------------------------------------------------------------ */
section('activity_recent: scopes');

$conn->ret = false;   /* no rows needed; the SQL is what is under test */

$conn->sqls = array();
activity_recent($conn, 10, 0, '', 42, 'all');
ok('all: no user predicate', $conn->lastWhere() === '', $conn->lastWhere());

$conn->sqls = array();
activity_recent($conn, 10, 0, '', 42, 'mine');
$sql = $conn->last();
ok('mine: filters user_id',          strpos($conn->lastWhere(), 'user_id = 42') !== false, $sql);
ok('mine: does NOT include user_id2', strpos($conn->lastWhere(), 'user_id2') === false, $conn->lastWhere());

$conn->sqls = array();
activity_recent($conn, 10, 0, '', 42, 'involving');
ok('involving: matches the passive side only',
   $conn->lastWhere() === 'user_id2 = 42', $conn->lastWhere());
ok('involving: does NOT also match things I initiated',
   strpos($conn->lastWhere(), 'user_id = 42') === false, $conn->lastWhere());

/* THE THREE TABS PARTITION THE FEED. A row belongs to exactly one of
   "mine" and "involving" for any given player, never both -- which is the
   whole reason the superset was dropped. */
$conn->sqls = array();
activity_recent($conn, 10, 0, '', 42, 'mine');
$mine = $conn->lastWhere();
$conn->sqls = array();
activity_recent($conn, 10, 0, '', 42, 'involving');
$inv = $conn->lastWhere();
ok('mine and involving are disjoint predicates, not nested ones',
   $mine === 'user_id = 42' && $inv === 'user_id2 = 42' && $mine !== $inv,
   "$mine | $inv");

$conn->sqls = array();
activity_recent($conn, 10, 0, '', 0, 'mine');
ok('mine with no user -> no predicate (never shows a guest everything-as-mine)',
   $conn->lastWhere() === '', $conn->lastWhere());

$conn->sqls = array();
activity_recent($conn, 10, 0, '', 42, 'nonsense');
ok('unknown scope falls back to no filter, not to a broken clause',
   $conn->lastWhere() === '', $conn->lastWhere());

section('activity_recent: channel, paging, bounds');

$conn->sqls = array();
activity_recent($conn, 10, 0, "dhc'arena", 0, 'all');
ok('channel is escaped into the clause', strpos($conn->last(), "channel = 'dhc\\'arena'") !== false, $conn->last());

$conn->sqls = array();
activity_recent($conn, 10, 0, '', 0, 'all');
ok('empty channel -> no channel clause', strpos($conn->lastWhere(), 'channel =') === false, $conn->lastWhere());

$conn->sqls = array();
activity_recent($conn, 10, 500, 'missions', 42, 'involving');
$sql = $conn->last();
ok('before_id pages backwards', strpos($sql, 'id < 500') !== false, $sql);
ok('all three predicates are ANDed', substr_count($sql, ' AND ') === 2, $sql);

$conn->sqls = array();
activity_recent($conn, 10, -5, '', 0, 'all');
ok('negative before_id is ignored, not emitted', strpos($conn->lastWhere(), 'id <') === false, $conn->lastWhere());

$conn->sqls = array();
activity_recent($conn, 99999, 0, '', 0, 'all');
ok('limit is capped at 200', strpos($conn->last(), 'LIMIT 200') !== false, $conn->last());
$conn->sqls = array();
activity_recent($conn, 0, 0, '', 0, 'all');
ok('limit 0 becomes 1', strpos($conn->last(), 'LIMIT 1') !== false, $conn->last());
$conn->sqls = array();
activity_recent($conn, '25; DROP TABLE activity', 0, '', 0, 'all');
ok('limit is an int, never interpolated text',
   strpos($conn->last(), 'LIMIT 25') !== false && stripos($conn->last(), 'DROP') === false, $conn->last());

$conn->sqls = array();
activity_recent($conn, 10, 0, '', 0, 'all');
ok('ordered and paged by id, not created_at', strpos($conn->last(), 'ORDER BY id DESC') !== false, $conn->last());
ok('selects user_id2 so the card can show both sides', strpos($conn->last(), 'user_id2') !== false);

ok('no connection -> empty array, no throw', activity_recent(null, 10) === array());

section('activity_channels / activity_prune');

$conn->sqls = array();
activity_channels($conn);
$sql = $conn->last();
ok('channels excludes the empty default channel', strpos($sql, "channel != ''") !== false, $sql);
ok('channels counts rows per channel',            strpos($sql, 'COUNT(*)') !== false, $sql);
ok('channels with no connection -> empty',        activity_channels(null) === array());

$conn->sqls = array();
$conn->ret = false;
ok('prune returns -1 when the DELETE cannot run (no table yet)', activity_prune($conn) === -1);
ok('prune uses the retention constant',
   strpos($conn->last(), 'INTERVAL ' . ACTIVITY_RETAIN_DAYS . ' DAY') !== false, $conn->last());
/* The count path -- see the note on FakeConn. $conn->ret stays false for
   every prune call below, because a true return makes the library read
   affected_rows, which an unconnected mysqli refuses outright. */
$lib = file_get_contents(__DIR__ . '/activity-lib.php');
ok('prune returns the driver row count, cast to int',
   strpos($lib, 'return (int) $conn->affected_rows;') !== false);

$conn->sqls = array();
activity_prune($conn, 0);
ok('prune day floor is 1, never 0 (0 would delete the whole table)',
   strpos($conn->last(), 'INTERVAL 1 DAY') !== false, $conn->last());
$conn->sqls = array();
activity_prune($conn, '30; DROP TABLE activity');
ok('prune days is an int', strpos($conn->last(), 'INTERVAL 30 DAY') !== false
   && stripos($conn->last(), 'DROP') === false, $conn->last());
ok('prune with no connection -> -1', activity_prune(null) === -1);

/* ---------------------------------------------------------------------------
 * 5. activity_format -- the XSS boundary for the page
 * ------------------------------------------------------------------------ */
section('activity_format: escaping');

$h = activity_format('<script>alert(1)</script>');
ok('script tag is escaped', strpos($h, '<script') === false && strpos($h, '&lt;script&gt;') !== false, $h);

$h = activity_format('**<img src=x onerror=alert(1)>**');
ok('markdown applied AFTER escaping cannot resurrect a tag',
   strpos($h, '<img') === false && strpos($h, '<strong>') !== false, $h);

$h = activity_format('[click](javascript:alert(1))');
ok('javascript: link is not turned into an anchor', stripos($h, '<a ') === false, $h);
$h = activity_format('[click](data:text/html,<script>alert(1)</script>)');
ok('data: link is not turned into an anchor', stripos($h, '<a ') === false, $h);

/* A SPACE IN THE URL IS THE WRONG TEST. The first version of this used
   '[a](https://x.io/" onmouseover="alert(1))' and "passed" for no reason: the
   URL pattern is [^\s)]+, so the space ended the match, the whole thing was
   never a link, and the assertion was checking a string with no anchor in it.
   The shape that actually reaches the callback has no space. */
$h = activity_format('[a](https://x.io/"onmouseover=alert(1))');
ok('a quote inside the href is re-escaped, not left to close the attribute',
   strpos($h, '<a href="https://x.io/&quot;onmouseover=alert(1"') !== false
   && strpos($h, 'onmouseover=alert') !== strpos($h, 'onmouseover="'), $h);
ok('and the anchor has exactly its own three quoted attributes',
   substr_count($h, '"') === 6, $h);

/* A BARE & IS WHAT REAL URLS CARRY. Descriptions are plain text written for
   Discord, so a query string arrives as ?a=1&b=2 and the href has to come out
   as &amp; -- one level of encoding, which is what the browser reads back as
   a single &.
   (An input containing the literal five characters "&amp;" correctly comes
   out as &amp;amp;, because in plain text that IS five characters. An earlier
   version of this assertion used that input and read the right answer as a
   double-encoding bug.) */
$h = activity_format('[a](https://x.io/?a=1&b=2)');
ok('a bare ampersand in the href is encoded exactly once',
   strpos($h, 'href="https://x.io/?a=1&amp;b=2"') !== false, $h);

$h = activity_format('a " b \' c & d');
ok('quotes and ampersands in plain text are entity-encoded',
   strpos($h, '&quot;') !== false && strpos($h, '&amp;') !== false, $h);

section('activity_format: Discord markdown');

ok('bold',   strpos(activity_format('**Oculus** wins'), '<strong>Oculus</strong>') !== false);
ok('italic', strpos(activity_format('it was *close*'), '<em>close</em>') !== false);
ok('underline', strpos(activity_format('__hey__'), '<u>hey</u>') !== false);
ok('inline code', strpos(activity_format('worth `500` CARBON'), '<code>500</code>') !== false);
ok('http link becomes an anchor',
   strpos(activity_format('[Arena](https://skulliance.io/staking/dhcarena.php)'),
          '<a href="https://skulliance.io/staking/dhcarena.php" target="_blank" rel="noopener">Arena</a>') !== false);
ok('newlines become <br>', strpos(activity_format("one\ntwo"), '<br>') !== false);

/* The real descriptions are built like dhca_announce()'s: bold names, emoji
   labels and a <@id> ping. Asterisks left raw is the bug this exists for. */
$real = "**Oculus** takes the Arena.\n\n⚔️ **Challenger:** Oculus\n🛡️ **Defender:** Skowl\n";
$h = activity_format($real);
ok('a real Arena description leaves no literal asterisks', strpos($h, '*') === false, $h);
ok('a real Arena description keeps its emoji', strpos($h, '⚔️') !== false);

$h = activity_format('<@772831523899965440> just became a member');
ok('an UNKNOWN mention falls back to a neutral word, not a snowflake',
   strpos($h, '772831523899965440') === false && strpos($h, 'a member') !== false, $h);
$h = activity_format('congrats <@!123456789012345678>');
ok('the <@!id> nickname form is handled too', strpos($h, '123456789012345678') === false, $h);

section('activity_format: resolved mentions');

/* THE BUG THIS FIXES, verbatim off a live Gauntlets card: two different
   players, both flattened to the same word, in a sentence whose entire
   content was which of them won. */
$names = array('772831523899965440' => 'oculusorbus', '123456789012345678' => 'mato_b13');
$h = activity_format('<@772831523899965440> was defeated by <@123456789012345678>', $names);
ok('both mentions resolve to their own name',
   strpos($h, '<strong>oculusorbus</strong>') !== false
   && strpos($h, '<strong>mato_b13</strong>') !== false, $h);
ok('and neither reads "a member" any more', strpos($h, 'a member') === false, $h);

$h = activity_format('<@772831523899965440> beat <@999999999999999999>', $names);
ok('a known and an unknown id in one line resolve independently',
   strpos($h, '<strong>oculusorbus</strong>') !== false && strpos($h, 'a member') !== false, $h);

/* A username is player-supplied and reaches the page through a callback that
   runs AFTER htmlspecialchars(), so it is escaped separately or it is a hole. */
$h = activity_format('<@1> won', array('1' => '<img src=x onerror=alert(1)>'));
ok('a hostile username cannot inject a tag',
   strpos($h, '<img') === false && strpos($h, '&lt;img') !== false, $h);
$h = activity_format('<@1> won', array('1' => "O'Brien & Sons"));
ok('quotes and ampersands in a username are encoded',
   strpos($h, '&amp;') !== false && strpos($h, '&#039;') !== false, $h);

ok('an empty name map behaves exactly as before',
   activity_format('<@1> x', array()) === activity_format('<@1> x'));

section('activity_mention_names');

$conn->ret = false;
$conn->sqls = array();
$rows = array(
	array('description' => 'hi <@111111111111111111> and <@!222222222222222222>'),
	array('description' => '<@111111111111111111> again'),
	array('description' => 'nobody here'),
	array('description' => null),
);
activity_mention_names($conn, $rows);
$sql = $conn->last();
ok('one query for the whole page, not one per card', count($conn->sqls) === 1, count($conn->sqls));
ok('both ids are looked up',
   strpos($sql, "'111111111111111111'") !== false && strpos($sql, "'222222222222222222'") !== false, $sql);
ok('a repeated id is asked for once',
   substr_count($sql, "'111111111111111111'") === 1, $sql);
ok('it joins on discord_id', strpos($sql, 'discord_id IN (') !== false, $sql);

$conn->sqls = array();
ok('no mentions anywhere -> no query at all',
   activity_mention_names($conn, array(array('description' => 'plain text'))) === array()
   && $conn->sqls === array());
ok('no rows -> empty map, no query', activity_mention_names($conn, array()) === array());
ok('no connection -> empty map', activity_mention_names(null, $rows) === array());

$conn->sqls = array();
activity_mention_names($conn, array(array('description' => "<@1'); DROP TABLE users;--> x")));
ok('a non-numeric mention body is not matched at all, so nothing is quoted',
   $conn->sqls === array(), $conn->last());
$h = activity_format('nice <:skull:112233445566778899> work');
ok('a custom emoji ref degrades to :name:', strpos($h, '112233445566778899') === false
   && strpos($h, ':skull:') !== false, $h);

/* Not a crash-test for its own sake: skl_embed_trim() cuts descriptions at
   Discord's limit, so a description arriving here with a half-finished
   **bold** or [link]( is a normal occurrence, not an edge case. */
foreach (array('**unclosed', '[text](http', '`code', '__x', '*', '**', '[](', "\x00") as $broken) {
	activity_format($broken);
}
ok('truncated markdown does not notice or throw', !$notices, implode('; ', $notices));

section('activity_ago / activity_channel_label');

ok('just now',  activity_ago(date('Y-m-d H:i:s')) === 'just now');
ok('singular minute', activity_ago(date('Y-m-d H:i:s', time() - 75)) === '1 minute ago');
ok('plural minutes',  activity_ago(date('Y-m-d H:i:s', time() - 600)) === '10 minutes ago');
ok('singular hour',   activity_ago(date('Y-m-d H:i:s', time() - 3700)) === '1 hour ago');
ok('plural days',     activity_ago(date('Y-m-d H:i:s', time() - 86400 * 3)) === '3 days ago');
ok('past retention falls back to a date',
   preg_match('/^[A-Z][a-z]{2} \d{1,2}, \d{4}$/', activity_ago(date('Y-m-d H:i:s', time() - 86400 * 200))) === 1);
ok('a future timestamp reads as just now, not "-1 minutes ago"',
   activity_ago(date('Y-m-d H:i:s', time() + 600)) === 'just now');
ok('an unparseable date is empty, not 1970', activity_ago('not a date') === '');
ok('a NULL created_at is empty', activity_ago(null) === '');

ok('known channel gets its real name',   activity_channel_label('dailyrewards') === 'Daily Rewards');
ok('dhcarena is named, not left raw',    activity_channel_label('dhcarena') === 'DHC Arena');
ok('the default webhook is labelled',    activity_channel_label('') === 'Platform');
ok('an unknown channel is title-cased rather than blank',
   activity_channel_label('new_thing') === 'New Thing');
/* Every channel discordmsg() can actually dispatch should have a real label,
   not a fallback -- the fallback is for a channel added after this file. */
$dispatch = array('realms','raids','dailyrewards','missions','skullswap','monstrocity','bossbattles',
	'store','auctions','raffles','delegations','gauntlet','cryptcrawl','cryptconquest','obscura',
	'guardians','dhcfighters','dhcarena','skullracer');
$unlabelled = array();
foreach ($dispatch as $c) {
	if (activity_channel_label($c) === ucwords(str_replace(array('-','_'), ' ', $c)) && $c !== 'realms'
	    && $c !== 'raids' && $c !== 'missions' && $c !== 'store' && $c !== 'auctions'
	    && $c !== 'raffles' && $c !== 'delegations' && $c !== 'monstrocity' && $c !== 'obscura') {
		$unlabelled[] = $c;
	}
}
ok('every dispatchable channel has a hand-written label', $unlabelled === array(), implode(', ', $unlabelled));

/* ---------------------------------------------------------------------------
 * 6. webhooks.php -- where the write is placed
 * ------------------------------------------------------------------------ */
section('webhooks.php integration');

$w = file_get_contents(__DIR__ . '/webhooks.php');

ok('discordmsg takes the two actor params',
   strpos($w, '$content="", $actor_id=null, $actor_id2=0) {') !== false);

/* THE GATE. Brace-matched out of the file rather than string-searched, so
   this fails if the call is ever moved out of the 2xx branch -- which is the
   single thing that keeps rejected posts out of a feed that claims they were
   announced. */
$at = strpos($w, 'if ($status >= 200 && $status < 300');
ok('the write is gated on a 2xx', $at !== false);
if ($at !== false) {
	$open = strpos($w, '{', $at);
	$depth = 0; $end = $open;
	for ($i = $open; $i < strlen($w); $i++) {
		if ($w[$i] === '{') $depth++;
		elseif ($w[$i] === '}') { $depth--; if ($depth === 0) { $end = $i; break; } }
	}
	$block = substr($w, $at, $end - $at + 1);
	ok('activity_log is called inside that block', strpos($block, 'activity_log(') !== false);
	ok('the block excludes alertAdmin re-entry (static flag)', strpos($block, '!$skl_alerting') !== false);
	ok('the block excludes alertAdmin called from elsewhere',
	   strpos($block, "empty(\$GLOBALS['skl_ops_alert'])") !== false);
	ok('the block is a no-op when the lib is missing',
	   strpos($block, "function_exists('activity_log')") !== false);
	ok('null actor_id resolves from the session inside the block',
	   strpos($block, 'activity_actor()') !== false);
	ok('both actor ids are forwarded',
	   preg_match('/activity_log\(.*\$aid,\s*\(int\)\s*\$actor_id2\)/s', $block) === 1);
	/* The whole 2xx block must sit inside `if ($webhook != "")`, or an
	   unconfigured channel would log a post that was never attempted. */
	$hookAt = strpos($w, 'if($webhook != "")');
	ok('the write sits inside the configured-webhook branch',
	   $hookAt !== false && $hookAt < $at, "webhook branch at $hookAt, write at $at");
}

/* activity_log must not be reachable from anywhere ELSE in webhooks.php --
   e.g. re-added next to the json_encode() as a "simplification". */
ok('exactly one activity_log call site in webhooks.php', substr_count($w, 'activity_log(') === 1,
   substr_count($w, 'activity_log('));

ok('alertAdmin marks itself as an ops alert',
   strpos($w, "\$GLOBALS['skl_ops_alert'] = true;") !== false);
ok('and clears the flag in a finally, so a throw cannot leak it',
   preg_match("/\} finally \{\s*\\\$GLOBALS\['skl_ops_alert'\] = false;/", $w) === 1);
ok('the lib include cannot fatal webhooks.php', strpos($w, "@include_once __DIR__ . '/activity-lib.php'") !== false);

section('callers');

$d = file_get_contents(__DIR__ . '/dhcarena-lib.php');
ok('dhca_announce passes attacker AND defender',
   strpos($d, '$ping, $attId, $defId);') !== false);

/*
 * EVERY TWO-SIDED ANNOUNCE MUST PASS BOTH IDS, or "Involving me" is just a
 * slower "My activity".
 *
 * Reported after the feed shipped: "Involving me is displaying notifications
 * I initiated, not the ones that involve me." The filter was right; the DATA
 * was not. Only ranked Arena was passing a second id, so every other
 * multiplayer event on the platform recorded one participant and the other
 * side never saw it. Gauntlets is the case that surfaced it -- and its own
 * code comments call third-party inclusion the point of the Discord ping.
 */
$g = file_get_contents(__DIR__ . '/db.php');
ok('Gauntlet victory records the runner AND the NFT owner fought',
   substr_count($g, "\$wh_opp_ping, \$uid, intval(\$enc['opponent_user_id'])") === 2,
   substr_count($g, "\$wh_opp_ping, \$uid, intval(\$enc['opponent_user_id'])"));

$l = file_get_contents(__DIR__ . '/dhcarena-live.php');
ok('a live Arena challenge reaches the person challenged',
   preg_match('/Live Challenge.*?\(int\)\$row\[.host_id.\], \(int\)\$row\[.guest_id.\]/s', $l) === 1);
ok('a live Arena result records both seats',
   preg_match('/Live Match.*?\(int\)\$row\[.host_id.\], \(int\)\$row\[.guest_id.\]/s', $l) === 1);

/* The two marketplace crons have NO SESSION, so activity_actor() returns 0
   and an announce without an explicit id belongs to nobody -- it would not
   appear under anyone's "My activity" either. Every call in both files now
   names at least the creator. */
foreach (array('auctions-verify.php', 'raffles-verify.php') as $f) {
	$src2  = file_get_contents(__DIR__ . '/' . $f);
	$calls = substr_count($src2, 'discordmsg(');
	$attr  = substr_count($src2, "null, null, '',");
	ok("$f attributes every announce it makes", $calls === $attr, "$calls calls, $attr attributed");
}
ok('the auction winner and creator are both recorded on a sale',
   substr_count(file_get_contents(__DIR__ . '/auctions-verify.php'),
      "null, null, '', \$prev_bidder, \$creator_id") === 2);
ok('auction delivery and timeout record creator and winner',
   substr_count(file_get_contents(__DIR__ . '/auctions-verify.php'),
      "null, null, '', \$creator_id, \$winner_id") === 2);
ok('the raffle winner and creator are both recorded on a draw',
   substr_count(file_get_contents(__DIR__ . '/raffles-verify.php'),
      "null, null, '', \$winning_uid, \$creator_id") === 2);
ok('raffle delivery and timeout record creator and winner',
   substr_count(file_get_contents(__DIR__ . '/raffles-verify.php'),
      "null, null, '', \$creator_id, \$winner_id") === 2);

$v = file_get_contents(__DIR__ . '/verify.php');
ok('the nightly job prunes', strpos($v, 'activity_prune($conn)') !== false);
ok('a missing table is reported, not alerted on',
   strpos($v, 'activity: no table yet') !== false && strpos($v, 'alertAdmin') === strpos($v, 'alertAdmin'));

$hdr = file_get_contents(__DIR__ . '/header.php');
ok('Activity sits next to Launchpad in the nav',
   preg_match('~<a href="launchpad\.php">Launchpad</a>.{0,600}<a href="activity\.php">Activity</a>~s', $hdr) === 1);

/* The page must not be reachable without the login gate -- it names players. */
$p = file_get_contents(__DIR__ . '/activity.php');
ok('activity.php is behind the login gate', strpos($p, "include 'skulliance.php'") !== false);
ok('scope from the querystring is whitelisted, never passed through',
   strpos($p, "in_array(\$ac_scope, array('all', 'mine', 'involving'), true)") !== false);
ok('a guest is pinned to the all scope', strpos($p, 'if ($me <= 0) $ac_scope = \'all\';') !== false);
ok('the channel filter is checked against channels that exist',
   strpos($p, "!isset(\$ac_chans[\$ac_chan])") !== false);
ok('card hrefs are restricted to http(s)', strpos($p, "preg_match('~^https?://~i', \$href)") !== false);
ok('the embed image is lazy-loaded (some of this art is 5000x5000)',
   substr_count($p, 'loading="lazy"') >= 2);

/*
 * THE DAILY REWARD PASSES A 128px CURRENCY MARK, NOT ARTWORK.
 * db.php's claim announce sends icons/<currency>.png, which renders fine in
 * a Discord embed and became a 290px white blob in a card. It is the ONLY
 * announce on the platform that puts an icon in the image slot, which is why
 * this is fixed in the feed rather than by changing a working announce.
 */
ok('anything from the ornament directory gets the emblem treatment',
   strpos($p, "preg_match('~/icons/[^/]+$~i', \$img)") !== false);
ok('the emblem class is applied server-side, so there is no layout shift',
   strpos($p, "\$is_mark ? ' is-mark' : ''") !== false);
ok('a small image is caught client-side too, for announces added later',
   strpos($p, 'img.naturalWidth < 200') !== false);
ok('and a cached image that loaded before the handler is swept',
   strpos($p, 'if (imgs[i].complete) acShot(imgs[i]);') !== false);

/* The art is square and was being cover-cropped to 240px tall, slicing the
   top and bottom off every mission, realm and Fighter render. */
ok('the artwork frame is square',
   strpos($p, 'aspect-ratio: 1 / 1') !== false);
/* cover on a square frame, not contain. Most art is already square so cover
   crops essentially nothing, and contain would letterbox every card for the
   minority that are taller. Squaring the FRAME is what stopped the picture
   being hidden; the old 240px crop was cutting square art top and bottom. */
ok('the artwork fills the square frame',
   preg_match('/\.ac-shot img \{[^}]*object-fit: cover/s', $p) === 1);
ok('and the emblem still opts out with contain',
   preg_match('/\.ac-shot\.is-mark img \{[^}]*object-fit: contain/s', $p) === 1);
/* REPORTED: "icons have a weird dark border". The artwork frame paints
   #07111d behind the image so a photo has a ground to load against; these
   icons are TRANSPARENT PNGs, so that ground became a hard dark square
   around the glyph. The emblem has to undo it explicitly. */
ok('the emblem has no frame ground behind a transparent glyph',
   preg_match('/\.ac-shot\.is-mark img \{[^}]*background: none/s', $p) === 1);
ok('the emblem band absorbs the leftover card height',
   preg_match('/\.ac-shot\.is-mark \{[^}]*flex: 1/s', $p) === 1);

/* REPORTED: a "Start All" that launched 9 missions listed 4 and trailed off,
   above half a card of nothing. The 7-line clamp is there to stop a long
   description crowding out a 290px image -- a card with no image has nothing
   to protect, and is also the emptiest-looking card on the wall. */
ok('an imageless card is marked as such',
   strpos($p, "\$img === '' ? ' ac-noart' : ''") !== false);
ok('and it is allowed far more of its description',
   preg_match('/\.ac-card\.ac-noart \.ac-desc \{[^}]*-webkit-line-clamp: 22/s', $p) === 1);
ok('a card WITH art keeps the tight clamp',
   preg_match('/\.ac-desc \{[^}]*-webkit-line-clamp: 7/s', $p) === 1);
ok('the old fixed max-height crop is gone',
   strpos($p, 'max-height: 240px') === false);
ok('the emblem opts out of the square frame',
   strpos($p, '.ac-shot.is-mark img') !== false
   && strpos($p, 'aspect-ratio: auto') !== false);
/* onerror hides the WRAPPER now -- hiding only the <img> would leave the
   emblem band's padding and border as an empty stripe. */
ok('a broken image hides its frame, not just itself',
   strpos($p, "onerror=\"this.parentNode.style.display='none'\"") !== false);

$dbsrc = file_get_contents(__DIR__ . '/db.php');
ok('the daily reward announce is unchanged (it renders correctly on Discord)',
   strpos($dbsrc, '"dailyrewards", $dr_avatar_url, "FFD700", $dr_author)') !== false);
ok('the description goes through activity_format, not raw echo',
   strpos($p, 'echo activity_format($r[\'description\'], $ac_names)') !== false);
ok('mentions are resolved once for the page, after the extra row is popped',
   strpos($p, '$ac_names = activity_mention_names($conn, $ac_rows);') !== false
   && strpos($p, 'if ($ac_more) array_pop($ac_rows);') < strpos($p, '$ac_names ='), $p ? '' : '');
ok('one extra row is fetched to decide "Load older"',
   strpos($p, 'ACTIVITY_PAGE_SIZE + 1') !== false);

/* REPORTED FROM A PHONE: the channel filter was the one white, rounded,
   system-styled box on a dark page, because the rule set padding and
   font-size and left every colour to the user agent. */
$selAt = strpos($p, '#ac-filters select {');
ok('the channel filter has its own rule', $selAt !== false);
if ($selAt !== false) {
	$rule = substr($p, $selAt, strpos($p, '}', $selAt) - $selAt);
	ok('it sets a background, so it cannot fall back to the UA default',
	   strpos($rule, 'background-color: #0d1e2e') !== false, $rule);
	ok('it sets a text colour to go with that background',
	   strpos($rule, 'color: #D6DDDE') !== false, $rule);
	ok('it is square, like every other control on the platform',
	   strpos($rule, 'border-radius: 0') !== false, $rule);
}
/* The popup list does NOT inherit the select's background on Windows or
   Android -- without this the options stay white on white there. */
ok('the option list is styled too',
   strpos($p, '#ac-filters select option { background-color: #0d1e2e') !== false);

restore_error_handler();
echo "\n-------------------------------------------\n";
echo "pass: $pass   fail: $fail\n";
if ($notices) { echo "NOTICES:\n"; foreach (array_unique($notices) as $n) echo "  $n\n"; }
if ($fails) { echo "failed:\n"; foreach ($fails as $f) echo "  - $f\n"; }
exit($fail || $notices ? 1 : 0);
