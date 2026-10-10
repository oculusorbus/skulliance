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
/*
 * PLAYERS ARE GROUPED BY THE PERSON, NOT BY THE AUTHOR LINE.
 *
 * This shipped as GROUP BY author_name and the author line is not the
 * person: the daily-reward announce writes "president_crypto · Day 6 of 7"
 * while every other announce writes the bare username, so one player
 * arrived as two rows. Seen on a real day -- four of twelve faces in the
 * shout-out row were duplicates of each other.
 */
ok('players are grouped by user_id, not by the embed author line',
   strpos($sql, 'GROUP BY u.id') !== false && strpos($sql, 'GROUP BY author_name') === false, $sql);
ok('the name and avatar come from the users table, canonically',
   strpos($sql, 'INNER JOIN users u ON u.id = a.user_id') !== false, $sql);
/* INNER, so a cron announce with no actor drops out -- a leaderboard payout
   is not somebody turning up. */
ok('rows with no actor are excluded', strpos($sql, 'a.user_id > 0') !== false, $sql);
ok('players are ranked by how much they did',
   strpos($sql, 'ORDER BY n DESC, u.username ASC') !== false, $sql);
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
	 array('id' => '4', 'username' => 'skowl',  'discord_id' => '77', 'avatar' => 'abc', 'n' => '9'),
	 array('id' => '5', 'username' => 'nobody', 'discord_id' => '',   'avatar' => '',    'n' => '1'),
	 array('id' => '6', 'username' => '',       'discord_id' => '88', 'avatar' => 'def', 'n' => '1')));
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

ok('a nameless row is dropped rather than drawn as a blank face',
   count($D['players']) === 2, count($D['players']));
ok('the avatar is built from the users table, same shape the announces use',
   $D['players'][0]['avatar'] === 'https://cdn.discordapp.com/avatars/77/abc.png',
   $D['players'][0]['avatar']);
/* A player with no Discord picture is NOT dropped -- the poster draws their
   initial instead, and dropping them would silently un-shout-out somebody
   for never having set an avatar. */
ok('a player with no avatar is kept, with an empty one',
   $D['players'][1]['name'] === 'nobody' && $D['players'][1]['avatar'] === '');
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
/* The panel is scoped under .adm and that is where its padding lives, so a
   page that opens the wrapper via admin_chrome() and never includes the
   stylesheet renders as unstyled text against the edge of the browser.
   Asserted for EVERY admin page, not just this one -- forgetting it is a
   silent, page-wide break with no error anywhere. */
$adminPages = glob(__DIR__ . '/admin-*.php');
$noCss = array();
foreach ($adminPages as $f) {
	$b = basename($f);
	if (in_array($b, array('admin-lib.php', 'admin-css.php', 'admin-harness.php'), true)) continue;
	$src2 = file_get_contents($f);
	if (strpos($src2, 'admin_chrome(') !== false && strpos($src2, "include 'admin-css.php'") === false) {
		$noCss[] = $b;
	}
}
ok('every admin page that opens the .adm wrapper also loads its styling',
   $noCss === array(), implode(', ', $noCss));

/*
 * ONE LIST, TWO CONSUMERS. admin_chrome()'s tab strip and header.php's
 * Admin dropdown each used to carry a hand-written copy, so Daily Digest
 * went into the tabs and was missing from the dropdown -- which is how the
 * panel is actually reached, and how it was reported. Both read admin_tabs()
 * now, and every page it names has to exist.
 */
require_once __DIR__ . '/admin-lib.php';
$tabs = admin_tabs();
ok('the digest is in the one admin page list', isset($tabs['digest']));
$missing = array();
foreach ($tabs as $k => $t) if (!is_file(__DIR__ . '/' . $t[0])) $missing[] = $t[0];
ok('every page the admin menu offers exists', $missing === array(), implode(', ', $missing));

$hdr = file_get_contents(__DIR__ . '/header.php');
ok('the nav dropdown is generated from admin_tabs(), not written out again',
   strpos($hdr, 'foreach (admin_tabs() as $t)') !== false);
/* If any of these literals come back, the two lists have been split again. */
$hand = 0;
foreach ($tabs as $t) if (strpos($hdr, '"' . $t[0] . '"') !== false) $hand++;
ok('no admin page is hard-coded into the nav alongside the generated list', $hand === 0, $hand);
/* $lib is activity-lib.php elsewhere in this harness; this needs the ADMIN
   lib, and reaching for the wrong one silently passed a null into substr(). */
$adminlib = file_get_contents(__DIR__ . '/admin-lib.php');
$chrome   = substr($adminlib, strpos($adminlib, 'function admin_chrome'), 200);
ok('admin_chrome reads the same list rather than keeping its own',
   strpos($chrome, '$tabs = admin_tabs();') !== false);

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
   && strpos($j, 'var cell = blk.cells[i], a = cell.item;') !== false);

/*
 * ONE LABEL PER CATEGORY. Captioning every tile read "DHC FIGHTERS" five
 * times, "BOSS BATTLES" three times and "MISSIONS" twice down one real
 * poster -- the repetition was the loudest thing on it and said nothing the
 * first instance had not.
 */
ok('art is grouped by channel before it is laid out',
   strpos($j, 'function group(art)') !== false && strpos($j, 'budget(group(art), cap)') !== false);
ok('the label is drawn once per block, not once per tile',
   strpos($j, 'if (blk.label) {') !== false
   && substr_count($j, 'blk.label.toUpperCase()') === 1);
ok('the label sits under its block on the poster ground, not over the art',
   strpos($j, 'blk.labelY') !== false);
/* With one gap everywhere the blocks were only distinguishable by reading
   the labels -- the clustering, which is the whole point, was invisible. */
ok('the gutter between categories is wider than the gap inside one',
   strpos($j, 'var GAP = 7, BGAP = 22') !== false);
/* 5 tiles in a 3-column grid is a row of 3 and a row of 2 with a HOLE
   beside it, and a hole in a collage reads as a picture that failed. */
ok('every row is filled, so a short row is just wider pictures',
   strpos($j, 'var base = Math.floor(n / rows), extra = n % rows;') !== false
   && strpos($j, 'var tw = (gw - GAP * (cnt - 1)) / cnt') !== false);
/* A real poster ended on "REAL…" because the smallest category got 90-odd
   pixels. The tally line already names and counts every category. */
/* A flat 150px minimum is wide enough for MISSIONS and not for REALM
   GUARDIANS -- a live poster went out reading "REALM GUAR…". The minimum
   has to be MEASURED per label. */
ok('each block is at least as wide as its own measured label',
   strpos($j, 'var lw = measure ? Math.ceil(measure(plan[i].label.toUpperCase())) : 0;') !== false
   && strpos($j, 'mins[i] = Math.max(150, lw + 6);') !== false);
ok('the label font is set before anything is measured against it',
   strpos($j, "c.font = '700 ' + Math.round(chipS * 0.95)") < strpos($j, 'var blocks = clusters('));
/* Iterative, because each category dropped also frees a gutter -- a fixed
   maxBlocks could not see that. */
ok('categories are dropped until the survivors all fit their names',
   strpos($j, 'while (plan.length > 1 && need(plan.length) > w)') !== false);
ok('and the per-label minimums never push the row past the band',
   strpos($j, 'var over = sum - innerW') !== false);
ok('spare width goes to the biggest block, never to the last one',
   strpos($j, 'if (over < 0) widths[0] -= over;') !== false);
/* The reclaim loop gave up the moment the SINGLE widest block was at its
   minimum, while every other block might still have had slack -- nine long
   category names overflowed the band by 147px, which on the poster is a
   block hanging off the right edge. It has to pick the widest block that
   still has room. */
ok('the reclaim loop skips blocks already at their minimum instead of stopping',
   strpos($j, 'if (widths[i] - 1 >= mins[i] && (pick < 0 || widths[i] > widths[pick])) pick = i;') !== false
   && strpos($j, 'if (pick < 0) break;') !== false);
/* Trait drops are the most frequent announcement on the platform and are
   single pieces of armour on a flat ground; a day's collage filled up with
   them while the Fighters people actually built were crowded out. */
$lib = file_get_contents(__DIR__ . '/activity-lib.php');
ok('trait art is excluded from the pool by url, since the channel cannot tell it apart',
   strpos($lib, "image_url NOT LIKE '%/250/%'") !== false);
/* Skull Racer's three box-art images are AI-generated where every other
   picture on the platform is hand-drawn, and side by side in one collage
   the difference is the first thing you see. Excluded by DIRECTORY so art
   added there later is covered too. */
ok('Skull Racer box art is excluded from the collage',
   strpos($lib, "image_url NOT LIKE '%/racing/images/%'") !== false);
/*
 * Excluded from the PICTURES, never from the day: the tally counts every
 * channel and its query carries no image filter at all.
 *
 * SCOPED TO activity_digest()'S BODY, not searched across the file.
 * activity_channels() -- which fills the Activity page's filter dropdown
 * -- opens with the identical "SELECT channel, COUNT(*) AS n FROM
 * activity" and sits EARLIER in the file, so a plain strpos landed on that
 * one and the assertion passed no matter what the digest's tally did. Two
 * queries, same first line; the anchor has to say which.
 */
$digestBody = substr($lib, strpos($lib, 'function activity_digest($conn, $day)'));
$digestBody = substr($digestBody, 0, strpos($digestBody, "\n}\n"));
$chanQ = substr($digestBody, strpos($digestBody, 'SELECT channel, COUNT(*)'), 160);
ok('the tally query is found inside activity_digest, not a namesake elsewhere',
   strpos($chanQ, 'GROUP BY channel ORDER BY n DESC') !== false, $chanQ);
ok('and none of the art exclusions touch the tally',
   strpos($chanQ, 'image_url') === false, $chanQ);

/* The homepage wordmark, not the word set in a Google font. */
ok('the masthead is the real logo',
   strpos($j, "load('images/skulliancelogo.png')") !== false);
ok('and it falls back to type rather than leaving no masthead at all',
   strpos($j, "if (!opts.headline && LOGO) {") !== false && strpos($j, "'SKULLIANCE'") !== false);

/* "19 Daily Rewar…" on a real poster: a fixed slice ran past the edge and
   the ellipsis landed mid-word, which reads as broken, not abbreviated. */
ok('the tally drops whole entries rather than ellipsising one',
   strpos($j, 'if (c.measureText(test).width > room) break;') !== false
   && strpos($j, 'D.channels.slice(0, 9)') === false);

section('layout: the real functions, stressed');

/*
 * The geometry lives in its own node harness, because it has to RUN the
 * layout rather than read it -- and that is where this went wrong twice:
 * a block hanging off the right edge, and "REALM GUAR..." on a post that
 * had already gone out to X. Shelled out rather than reimplemented here so
 * there is one copy of those cases. Reported as skipped, not failed, if
 * node is not on this machine.
 */
$node = trim((string) @shell_exec('command -v node 2>/dev/null'));
if ($node === '') {
	echo "  --   node not found; run digest-geometry-harness.js separately\n";
} else {
	$res = trim((string) @shell_exec(escapeshellcmd($node) . ' '
	       . escapeshellarg(__DIR__ . '/digest-geometry-harness.js') . ' 2>&1'));
	ok('no label is cut and nothing runs past the band, at any width or cap',
	   strpos($res, 'OK') === 0, $res);
}

restore_error_handler();
echo "\n-------------------------------------------\n";
echo "pass: $pass   fail: $fail\n";
if ($notices) { echo "NOTICES:\n"; foreach (array_unique($notices) as $n) echo "  $n\n"; }
if ($fails) { echo "failed:\n"; foreach ($fails as $f) echo "  - $f\n"; }
exit($fail || $notices ? 1 : 0);
