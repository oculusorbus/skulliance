<?PHP
/*
 * DAILY DIGEST HARNESS -- the data half.
 *
 *   php digest-harness.php
 *
 * activity_digest() and activity_digest_src() out of activity-lib.php,
 * driven against the same FakeConn pattern activity-harness.php uses.
 *
 * activity_digest_src() is the one that earns the most attention here. A
 * canvas that has drawn a cross-origin image without CORS throws on
 * toBlob(), so a single bad url does not cost a tile -- it costs the whole
 * download, and only at the end, after everything looked fine. This
 * function is the only thing standing between that and the admin.
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

/*
 * NOT a mysqli_result, and it does not need to be: activity_digest() only
 * ever calls ->fetch_assoc() in a while, and PHP resolves that on whatever
 * object query() handed back. Faking the real class is impossible without a
 * connection (activity-harness.php has the same note about affected_rows),
 * so duck typing is what makes the rows testable at all.
 */
class DigestResult {
	private $rows;
	public function __construct($rows) { $this->rows = $rows; }
	public function fetch_assoc() { return array_shift($this->rows); }
}

class DigestConn extends mysqli {
	public array $sqls = array();
	public $ret = false;         /* fallback when the queue is empty */
	public array $queue = array();
	public function __construct() {}
	#[\ReturnTypeWillChange]
	public function query($query, $result_mode = MYSQLI_STORE_RESULT) {
		$this->sqls[] = $query;
		if ($this->queue) return new DigestResult(array_shift($this->queue));
		return $this->ret;
	}
	#[\ReturnTypeWillChange]
	public function real_escape_string($string) { return str_replace(array("\\", "'"), array("\\\\", "\\'"), (string)$string); }
	public function all() { return implode("\n----\n", $this->sqls); }
	/* The three result sets activity_digest() reads, in order. */
	public function expect($channels, $art, $players) {
		$this->queue = array($channels, $art, $players);
		$this->sqls  = array();
	}
}

$notices = array();
set_error_handler(function ($no, $str, $file, $line) use (&$notices) {
	if (!(error_reporting() & $no)) return true;
	$notices[] = "$str @ $file:$line";
	return true;
});

require_once __DIR__ . '/activity-lib.php';

/* ---------------------------------------------------------------------------
 * activity_digest_src -- the tainting gate
 * ------------------------------------------------------------------------ */
section('canvas safety: what may be drawn');

ok('our own art comes back RELATIVE, not absolute',
   activity_digest_src('https://skulliance.io/staking/images/missions/x.gif') === 'images/missions/x.gif',
   activity_digest_src('https://skulliance.io/staking/images/missions/x.gif'));
/*
 * www. AND THE BARE HOST ARE DIFFERENT ORIGINS TO A CANVAS, even though they
 * are the same site. The login cookie has no domain param, so the admin can
 * genuinely be on www. while the announce wrote the bare host -- and that
 * combination taints, with no symptom until the download.
 */
ok('the www. host is normalised the same way',
   activity_digest_src('https://www.skulliance.io/staking/dhcrenders/f1.png') === 'dhcrenders/f1.png',
   activity_digest_src('https://www.skulliance.io/staking/dhcrenders/f1.png'));
ok('http is treated like https',
   activity_digest_src('http://skulliance.io/staking/images/a.png') === 'images/a.png');
ok('a path outside /staking/ keeps its leading slash',
   activity_digest_src('https://skulliance.io/other/a.png') === '/other/a.png',
   activity_digest_src('https://skulliance.io/other/a.png'));

/* Discord sends access-control-allow-origin: *, verified against the live
   CDN rather than assumed, so avatars may stay absolute. */
ok('a Discord avatar is kept, because its CDN sends CORS',
   activity_digest_src('https://cdn.discordapp.com/avatars/1/2.png') === 'https://cdn.discordapp.com/avatars/1/2.png');

ok('an already-relative path is passed through',
   activity_digest_src('images/nfts/9.png') === 'images/nfts/9.png');
ok('a root-relative path is passed through',
   activity_digest_src('/staking/images/a.png') === '/staking/images/a.png');

/* THE ONES THAT MUST BE DROPPED. Each of these taints the canvas and the
   cost is the entire poster, not one tile. */
ok('an arbitrary third-party host is dropped',
   activity_digest_src('https://example.com/a.png') === '', activity_digest_src('https://example.com/a.png'));
ok('an IPFS gateway is dropped',
   activity_digest_src('https://ipfs.filebase.io/ipfs/abc/1.png') === '');
ok('a lookalike host is dropped',
   activity_digest_src('https://skulliance.io.evil.com/a.png') === '',
   activity_digest_src('https://skulliance.io.evil.com/a.png'));
ok('a subdomain that is not www is dropped',
   activity_digest_src('https://cdn.skulliance.io/a.png') === '');
/* //evil.com/x LOOKS like a path and is not one -- the browser resolves it
   against the current scheme and fetches from evil.com. */
ok('a protocol-relative url is dropped, not mistaken for a path',
   activity_digest_src('//evil.com/a.png') === '', activity_digest_src('//evil.com/a.png'));
ok('a data: url is dropped', activity_digest_src('data:image/png;base64,AAAA') === '');
ok('empty stays empty', activity_digest_src('') === '' && activity_digest_src('   ') === '');

/* ---------------------------------------------------------------------------
 * activity_digest -- the queries
 * ------------------------------------------------------------------------ */
section('activity_digest: the day window');

$conn = new DigestConn();

$d = activity_digest($conn, '2026-10-08');
$sql = $conn->all();
/*
 * A HALF-OPEN RANGE, NOT DATE(created_at) = '...'. The function form cannot
 * use idx_created, so once the table holds months of announcements the
 * question "what happened today" reads every row in it.
 */
ok('the window is a half-open range on created_at',
   strpos($sql, "created_at >= '2026-10-08 00:00:00'") !== false
   && strpos($sql, "created_at < DATE_ADD('2026-10-08 00:00:00', INTERVAL 1 DAY)") !== false, $sql);
ok('it never calls DATE() on the indexed column',
   strpos($sql, 'DATE(created_at)') === false, $sql);

section('activity_digest: refusals');

$conn->sqls = array();
ok('a malformed date runs no query at all',
   activity_digest($conn, '2026-13-99 OR 1=1')['total'] === 0 && $conn->sqls === array(), $conn->all());
$conn->sqls = array();
ok("an injection attempt in the date runs nothing",
   activity_digest($conn, "' UNION SELECT")['total'] === 0 && $conn->sqls === array());
ok('no connection returns the empty shape rather than throwing',
   activity_digest(null, '2026-10-08')['total'] === 0);

$empty = activity_digest($conn, '2026-10-08');
ok('the empty shape still carries every key the page reads',
   isset($empty['day'], $empty['total'], $empty['channels'], $empty['art'], $empty['players']));
ok('and the day it was asked about', $empty['day'] === '2026-10-08');

section('activity_digest: what it asks for');

$conn->expect(
	array(array('channel' => 'missions', 'n' => '7'), array('channel' => 'raids', 'n' => '2')),
	array(), array());
activity_digest($conn, '2026-10-08');
$sql = $conn->all();
/* The ornament directory again: a 128px currency glyph stretched into a
   collage tile is the mistake the feed already made once. */
ok('icons are excluded from the art pool',
   strpos($sql, "image_url NOT LIKE '%/icons/%'") !== false, $sql);
ok('and so are empty images', strpos($sql, "image_url != ''") !== false);
/* A leaderboard run posts a dozen results carrying one piece of art. Twelve
   copies of one tile is not a collage. */
ok('art is deduplicated by url', strpos($sql, 'GROUP BY image_url') !== false, $sql);
ok('players are deduplicated by name', strpos($sql, 'GROUP BY author_name') !== false, $sql);
ok('players with no name are skipped', strpos($sql, "author_name != ''") !== false);
ok('players are ranked by how much they did',
   strpos($sql, 'ORDER BY n DESC, author_name ASC') !== false, $sql);
ok('both pools are capped',
   strpos($sql, 'LIMIT ' . ACTIVITY_DIGEST_ART) !== false
   && strpos($sql, 'LIMIT ' . ACTIVITY_DIGEST_PLAYERS) !== false, $sql);
/* Stable art ordering, so the same day draws the same poster. */
ok('art keeps a stable order across runs',
   strpos($sql, 'ORDER BY first_id ASC') !== false, $sql);

/* ---------------------------------------------------------------------------
 * the page and the drawing
 * ------------------------------------------------------------------------ */
section('activity_digest: the shape it returns');

$conn->expect(
	array(array('channel' => 'missions', 'n' => '7'),
	      array('channel' => 'dhcfighters', 'n' => '3'),
	      array('channel' => '', 'n' => '1')),
	array(
	 array('image_url' => 'https://skulliance.io/staking/images/missions/a.gif',
	       'first_id' => '10', 'channel' => 'missions', 'title' => 'Mission', 'author_name' => 'skowl', 'n' => '4'),
	 /* An IPFS-hosted picture: real in the data, and fatal to a canvas. */
	 array('image_url' => 'https://ipfs.filebase.io/ipfs/x/1.png',
	       'first_id' => '11', 'channel' => 'gauntlet', 'title' => 'G', 'author_name' => 'v', 'n' => '1'),
	 array('image_url' => 'https://www.skulliance.io/staking/dhcrenders/f1.png',
	       'first_id' => '12', 'channel' => 'dhcfighters', 'title' => 'F', 'author_name' => 'nothooley', 'n' => '2')),
	array(
	 array('author_name' => 'skowl', 'avatar' => 'https://cdn.discordapp.com/avatars/1/a.png', 'n' => '9'),
	 array('author_name' => 'nobody', 'avatar' => '', 'n' => '1'),
	 array('author_name' => 'offsite', 'avatar' => 'https://example.com/a.png', 'n' => '1')));
$D = activity_digest($conn, '2026-10-08');

ok('the total is the sum of the channel counts', $D['total'] === 11, $D['total']);
ok('channel counts are cast to int', $D['channels'][0]['n'] === 7);
ok('channels carry a human label', $D['channels'][1]['label'] === 'DHC Fighters',
   $D['channels'][1]['label']);
ok('the default webhook channel is labelled, not left blank',
   $D['channels'][2]['label'] === 'Platform');

/* THE DROP IS THE POINT. An IPFS tile would taint the canvas and cost the
   whole download, so it never reaches the browser. */
ok('art hosted off-origin is dropped from the pool', count($D['art']) === 2, count($D['art']));
ok('and the two that survive are ours, relative',
   $D['art'][0]['src'] === 'images/missions/a.gif' && $D['art'][1]['src'] === 'dhcrenders/f1.png',
   json_encode(array_column($D['art'], 'src')));
ok('art keeps its channel label for the tile caption',
   $D['art'][1]['label'] === 'DHC Fighters');

ok('every player survives even when their avatar does not', count($D['players']) === 3);
ok('a Discord avatar is kept whole',
   $D['players'][0]['avatar'] === 'https://cdn.discordapp.com/avatars/1/a.png');
/* A player with no usable avatar is NOT dropped -- the poster draws an
   initial instead, and dropping them would silently un-shout-out somebody
   for never setting a Discord picture. */
ok('a player with no avatar is kept with an empty one',
   $D['players'][1]['name'] === 'nobody' && $D['players'][1]['avatar'] === '');
ok('and so is one whose avatar is off-origin',
   $D['players'][2]['name'] === 'offsite' && $D['players'][2]['avatar'] === '');
ok('player counts are cast to int', $D['players'][0]['n'] === 9);

section('admin-digest.php');

$p = file_get_contents(__DIR__ . '/admin-digest.php');
ok('it is behind the admin gate', strpos($p, 'admin_require();') !== false);
ok('and the gate runs before any output',
   strpos($p, 'admin_require();') < strpos($p, "include 'header.php'"));
ok('it writes nothing: no POST handling at all', strpos($p, '$_POST') === false);
ok('the day from the querystring is validated, never interpolated',
   strpos($p, "preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', \$dg_day)") !== false);
ok('it defaults to the SERVER clock, which is what created_at is written with',
   strpos($p, "date('Y-m-d')") !== false);
ok('the data reaches the canvas as json, not as markup',
   strpos($p, 'window.DIGEST = <?php echo json_encode($DIGEST') !== false);
ok('it appears in the admin nav',
   strpos(file_get_contents(__DIR__ . '/admin-lib.php'), "'digest'      => array('admin-digest.php'") !== false);

section('js/digest-builder.js');

$j = file_get_contents(__DIR__ . '/js/digest-builder.js');
/*
 * crossOrigin MUST BE SET BEFORE .src. Setting it afterwards is ignored and
 * the canvas is tainted with no warning until toBlob() throws.
 */
ok('crossOrigin is set on every image',
   strpos($j, "im.crossOrigin = 'anonymous';") !== false);
ok('and it is set BEFORE the src',
   strpos($j, "im.crossOrigin = 'anonymous';") < strpos($j, 'im.src = src;'), 'order is wrong');
ok('a failed image resolves to null rather than rejecting',
   strpos($j, "im.onerror = function () { res(null); };") !== false);
ok('the export is wrapped, so a taint reports itself instead of doing nothing',
   strpos($j, 'canvas.toBlob(') !== false && strpos($j, 'try {') < strpos($j, 'canvas.toBlob('));
/* Same day, same poster. That is the point of a layout dictated by the day,
   and it also stops the preview reshuffling on every control change. */
ok('the layout is seeded by the day, not by Math.random',
   strpos($j, 'seedOf(D.day') !== false && strpos($j, 'Math.random') === false);
ok('the seed also takes the format and the re-roll',
   strpos($j, "seedOf(D.day + '|' + opts.format + '|' + opts.nudge)") !== false);
ok('pictures that did not load are reported, not silently missing',
   strpos($j, 'could not be loaded and were left out') !== false);
ok('everything loads before anything is drawn',
   strpos($j, 'Promise.all(') !== false);
/* The shout-out block is an avatar with a name UNDER it, so its top must
   leave room for both. It was short by one radius -- which the wide poster
   had the slack to absorb and the tall one did not, clipping every name off
   the bottom edge. The reserved height and the draw position have to agree
   or the mosaic and the row disagree about where the floor is. */
ok('the shout-out row leaves room for the avatar AND the name',
   strpos($j, 'var py = H - PAD - avR * 2 - nameS - 2;') !== false);
ok('and the reserved footer height matches that arithmetic',
   strpos($j, 'var peopleH  = D.players.length ? avR * 2 + nameS + 14 : 0;') !== false);
ok('a tile is never drawn twice', strpos($j, 'art[i % art.length]') === false
   && strpos($j, 'var cell = cells[i], a = art[i];') !== false);

restore_error_handler();
echo "\n-------------------------------------------\n";
echo "pass: $pass   fail: $fail\n";
if ($notices) { echo "NOTICES:\n"; foreach (array_unique($notices) as $n) echo "  $n\n"; }
if ($fails) { echo "failed:\n"; foreach ($fails as $f) echo "  - $f\n"; }
exit($fail || $notices ? 1 : 0);
