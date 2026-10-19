<?php
/* player-digest-harness.php - CLI only. No database, no network.
 *
 * The "here is what you did yesterday" modal.
 *
 * THREE THINGS CAN GO WRONG HERE AND NONE OF THEM LOOKS LIKE AN ERROR.
 *
 *  1. The query could show somebody else's day. It is scoped by session
 *     user id and by a date the server computes; a ?user= or a client date
 *     would turn a private summary into something anyone can enumerate,
 *     one day at a time.
 *  2. The canvas could be tainted. A cross-origin image drawn without CORS
 *     makes toBlob() throw, so the symptom is the download button doing
 *     nothing after the poster rendered perfectly.
 *  3. The post could lose its tail. X counts any url as 23 characters and
 *     cuts the overflow off the END, which is where the invitation and the
 *     artist credit are.
 *
 * Usage: php player-digest-harness.php
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

$fail = 0;
function ok($cond, $what) { global $fail; if (!$cond) { $fail++; echo "  FAIL  $what\n"; } }

require_once __DIR__ . '/harness-strip.php';
$lib  = harness_strip_comments(file_get_contents(__DIR__ . '/activity-lib.php'));
$ep   = harness_strip_comments(file_get_contents(__DIR__ . '/ajax/player-digest.php'));
$mod  = file_get_contents(__DIR__ . '/player-digest.php');
$modc = harness_strip_comments($mod);

echo "it can only ever show you your own day\n";
{
	/* The session decides whose digest this is. Nothing else may. */
	ok(strpos($ep, "\$_SESSION['userData']['user_id']") !== false,
	   'the endpoint does not take the user from the session');
	ok(strpos($ep, "\$_GET['user']") === false && strpos($ep, "\$_POST['user']") === false
	   && strpos($ep, "\$_REQUEST") === false,
	   'the endpoint accepts a user parameter; anyone could read anyone\'s day');
	/* And the DAY is the server's, or the whole history is walkable one
	   request at a time. */
	ok(strpos($ep, "\$_GET['day']") === false && strpos($ep, "\$_GET['date']") === false,
	   'the endpoint accepts a date, so a player\'s whole history can be walked');
	ok(strpos($ep, "date('Y-m-d', strtotime('-1 day'))") !== false,
	   'yesterday is not computed server-side');
	/* Logged out gets a clean answer, not an HTML redirect parsed as JSON. */
	ok(strpos($ep, "include '../skulliance.php'") === false
	   && strpos($ep, 'skulliance.php') === false,
	   'the endpoint includes the login gate, so a logged-out visitor gets error.php as JSON');
	ok(strpos($ep, '$_SESSION = array_merge((array) $_SESSION, $cookie)') !== false,
	   'the SessionCookie restore raw-assigns, which can wipe another page\'s session state');
}

echo "\nthe query is scoped, indexed and safe\n";
{
	ok(strpos($lib, 'function activity_player_digest') !== false, 'the query function is gone');
	$fn = substr($lib, strpos($lib, 'function activity_player_digest'));
	$fn = substr($fn, 0, strpos($fn, "\nfunction ") ?: strlen($fn));

	ok(substr_count($fn, 'a.user_id = $user_id') >= 1,
	   'the digest is not scoped to one user');
	ok(strpos($fn, '$user_id = (int) $user_id;') !== false,
	   'the user id is not cast before reaching SQL');
	/* A half-open range, never DATE(created_at) = -- the function form
	   cannot use idx_created and reads the whole table. */
	ok(strpos($fn, "a.created_at >= ") !== false && strpos($fn, 'DATE_ADD') !== false,
	   'the day window is not a half-open range on created_at');
	ok(strpos($fn, 'DATE(a.created_at)') === false && strpos($fn, 'DATE(created_at)') === false,
	   'the window uses DATE(created_at), which cannot use the index');
	ok(preg_match('/preg_match\(\'\/\^\\\\d\{4\}-\\\\d\{2\}-\\\\d\{2\}\$\/\'/', $fn) === 1,
	   'the day is not pattern-checked before it is interpolated into SQL');
	/* Nothing worth showing means no modal, not an empty one. */
	ok(strpos($fn, "if (!\$out['total']) return \$out;") !== false,
	   'a day with no activity still builds a poster');
	ok(strpos($ep, "if (!\$d['total']) { ob_clean(); echo json_encode(array('show' => false)") !== false
	   || strpos($ep, "!\$d['total']") !== false,
	   'the endpoint does not suppress an empty day');

	/* The same exclusions the platform collage uses, for the same reasons:
	   ornament, bare traits, and AI box art beside hand-drawn work. */
	foreach (array('/icons/', '/250/', '/racing/images/') as $ex) {
		ok(strpos($fn, $ex) !== false,
		   "the art query no longer excludes $ex, so the poster fills with the wrong pictures");
	}
	/* Canvas safety starts here: every url goes through the normaliser. */
	ok(substr_count($fn, 'activity_digest_src(') >= 2,
	   'an image url reaches the canvas without being normalised; the export will throw');
}

echo "\nthe canvas cannot be tainted, and a failure is explained\n";
{
	ok(strpos($modc, "im.crossOrigin = 'anonymous';") !== false,
	   'crossOrigin is not set, so the poster cannot be exported at all');
	/* BEFORE .src. After is silently ignored, and the bug only shows up at
	   download time. */
	$ca = strpos($modc, 'im.crossOrigin');
	$sa = strpos($modc, 'im.src = src;');
	ok($ca !== false && $sa !== false && $ca < $sa,
	   'crossOrigin is set AFTER .src, where it is silently ignored');
	ok(strpos($modc, 'im.onerror = function () { res(null); };') !== false,
	   'a picture that fails to load rejects instead of being dropped, losing the whole poster');
	ok(strpos($modc, 'cv.toBlob(') !== false && strpos($modc, 'try {') !== false,
	   'toBlob is called without a try; a tainted canvas would make the button do nothing');
	ok(strpos($modc, 'URL.revokeObjectURL') !== false, 'the object url is never revoked');
}

echo "\nit asks once, and costs nothing after that\n";
{
	ok(strpos($modc, 'localStorage.getItem') !== false && strpos($modc, 'localStorage.setItem') !== false,
	   'nothing remembers that the digest was already shown');
	/* Storage throws in a private window and can simply be unavailable. */
	ok(substr_count($modc, 'catch (e)') >= 2,
	   'a localStorage access is unguarded; the modal dies in a private window');
	/* The guard runs BEFORE the fetch, or every page load costs a query. */
	$gAt = strpos($modc, 'if (seen(seenKey(localDay');
	$fAt = strpos($modc, "fetch('ajax/player-digest.php'");
	ok($gAt !== false && $fAt !== false && $gAt < $fAt,
	   'the endpoint is called before checking whether the digest was already seen');
	/* And a signed-out visitor is never sent any of this. */
	ok(strpos($mod, "if (!isset(\$_SESSION['userData']['user_id'])) return;") !== false,
	   'the modal markup and its fetch ship to logged-out visitors too');
	ok(strpos($mod, "defined('SKL_PDIGEST_LOADED')") !== false,
	   'the modal is not idempotent; a second include would duplicate every id');
}

echo "\nthe post keeps its invitation and its credit\n";
{
	/* Run the real shareText() over a day big enough to need trimming. */
	/* From the ASKS table, not from the function: shareText() closes over it
	   and a slice starting at the function leaves it undefined, so node dies
	   on the first call instead of reporting anything. */
	$a = strpos($mod, '  var ASKS = [');
	/* Anchored on the NAME, not the full signature: shareHref grew a url
	   parameter and the exact-match anchor silently found nothing, so the
	   slice came back empty and node died on the first call rather than
	   reporting anything useful. Same trap dhc-share-harness.js carries a
	   note about. */
	$b = strpos($mod, '  function shareHref(');
	ok($a !== false && $b > $a, 'shareText() is gone from the modal');
	$src = substr($mod, $a, $b - $a);

	$tmp = sys_get_temp_dir() . '/pdg-share-' . getmypid() . '.js';
	file_put_contents($tmp,
		$src . "\n" .
		"var out = [];\n" .
		"out.push(shareText({ total: 14, streak: 9, best: { n: 6, label: 'Missions' }, " .
		"ref: '3px83', day: '2026-10-17' }));\n" .
		"out.push(shareText({ total: 1, streak: 1, best: null, ref: '3px83', day: '2026-10-17' }));\n" .
		"out.push(shareText({ total: 999, streak: 400, " .
		"best: { n: 900, label: 'Long Feature Name '.repeat(12) }, ref: 'zz9', day: '2026-10-18' }));\n" .
		/* Four different sharers on one day, to prove the ask rotates. */
		"var asks = ['a','b','c','d','e','f'].map(function (r) {\n" .
		"  return shareText({ total: 3, streak: 0, best: null, ref: r, day: '2026-10-17' })" .
		".split('\\n\\n')[1]; });\n" .
		"console.log(JSON.stringify({ posts: out, asks: asks }));\n");
	$raw = @shell_exec('node ' . escapeshellarg($tmp) . ' 2>&1');
	@unlink($tmp);
	$res   = json_decode(trim((string) $raw), true);
	$posts = is_array($res) && isset($res['posts']) ? $res['posts'] : null;
	ok(is_array($posts) && count($posts) === 3,
	   'shareText() could not be run: ' . substr((string) $raw, 0, 120));
	if (is_array($posts) && count($posts) === 3) {
		foreach ($posts as $i => $t) {
			ok(mb_strlen($t) + 24 <= 280,
			   'post ' . $i . ' is ' . mb_strlen($t) . ' characters plus a url');
			ok(strpos($t, '@skulliance') !== false, 'post ' . $i . ' does not invite anyone');
			ok(substr($t, -strlen('Art by @MMAXI404')) === 'Art by @MMAXI404',
			   'post ' . $i . ' lost the artist credit off the end');
		}
		/*
		 * THE REPORT CARD LEADS. The invite used to, which meant every
		 * player's post opened with the same byte-identical sentence --
		 * near-identical content across many accounts is what X's spam
		 * policy covers, and it would be the players' accounts actioned.
		 * Their own numbers are the part nobody else can post.
		 */
		foreach ($posts as $i => $t) {
			ok(preg_match('/^\d/', $t) === 1,
			   'post ' . $i . ' does not open with the player\'s own numbers: '
			 . substr($t, 0, 50));
		}
		/* And the ask still has to be in there, with who qualifies and why
		   it is worth anything. */
		ok(stripos($posts[0], '3 DHC traits') !== false, 'the post does not say what is on offer');
		ok(preg_match('/new (here|to Skulliance)/i', $posts[0]) === 1
		   && stripos($posts[0], 'month') !== false,
		   'the post does not say who qualifies, so every active follower clicks for nothing');
		ok(stripos($posts[0], 'odds') !== false,
		   'the post does not say the odds are better, so three traits reads as three commons');
		ok(strpos($posts[0], '9 days running') !== false, 'the streak is missing from the card');
		ok(strpos($posts[1], 'days running') === false,
		   'a streak of 1 is announced as a streak: ' . $posts[1]);
	}
	/* The ask rotates, or six sharers on one day post six identical lines. */
	$asks = is_array($res) && isset($res['asks']) ? $res['asks'] : array();
	ok(count(array_unique($asks)) > 1,
	   'every sharer gets the same ask on the same day; that is the duplicate-content pattern');

	/*
	 * ONE CLICK. X cannot attach a file, so the poster is stored first and
	 * the post links to digest.php?d=<token>, whose og:image is that file.
	 */
	ok(strpos($modc, "fetch('ajax/digest-share.php'") !== false,
	   'sharing no longer stores the poster, so the post has no picture');
	ok(strpos($modc, 'shareHref(d, url)') !== false,
	   'the post url is fixed; it cannot point at the stored poster');
	/*
	 * THE TAB OPENS ON THE CLICK. A window.open() from a promise callback
	 * is not user-initiated any more and every browser blocks it, so the
	 * share would silently do nothing.
	 */
	$sAt = strpos($modc, 'function share(d) {');
	$body = substr($modc, $sAt, strpos($modc, "\n  }", $sAt) - $sAt);
	$openAt  = strpos($body, 'window.open(');
	$fetchAt = strpos($body, 'fetch(');
	ok($openAt !== false && $fetchAt !== false && $openAt < $fetchAt,
	   'the share tab is opened after the upload, where the browser blocks it as a popup');
	/* And a failed upload must still post. */
	ok(strpos($body, 'done(null)') !== false,
	   'a failed upload leaves the share doing nothing at all');
}

echo "\nthe stored poster cannot be used to write anywhere\n";
{
	$up  = harness_strip_comments(file_get_contents(__DIR__ . '/ajax/digest-share.php'));
	$shl = harness_strip_comments(file_get_contents(__DIR__ . '/lib/digest-share-lib.php'));
	$pg  = harness_strip_comments(file_get_contents(__DIR__ . '/digest.php'));

	/* The filename is DERIVED. A client-chosen name is a file write
	   wherever they like. */
	ok(strpos($up, 'dsh_token($uid, $day)') !== false,
	   'the stored filename is not derived from the session user and the server day');
	ok(strpos($up, "\$_FILES['poster']['name']") === false,
	   'the uploaded filename is used; that is an arbitrary file write');
	ok(strpos($up, "\$_GET") === false && strpos($up, "\$_POST['user']") === false,
	   'the upload endpoint takes a parameter it should not');
	/* Checked as an image, bounded, and RE-ENCODED rather than stored as
	   sent -- whatever was hidden in those bytes does not survive being
	   turned into pixels. */
	ok(strpos($up, 'getimagesize(') !== false && strpos($up, 'IMAGETYPE_PNG') !== false,
	   'the upload is not checked to be a PNG before it is read as one');
	ok(strpos($up, 'imagecreatefrompng(') !== false && strpos($up, 'imagejpeg(') !== false,
	   'the bytes are stored as sent rather than decoded and re-encoded');
	ok(strpos($up, '> 2400') !== false || strpos($up, '> 3000') !== false,
	   'there is no dimension cap; a 30000x30000 PNG is small on disk and gigabytes decoded');
	ok(strpos($up, '4 * 1024 * 1024') !== false, 'there is no byte cap on the upload');
	ok(strpos($up, '@rename($tmp, $path)') !== false,
	   'the poster is written in place; two taps on Share would race');
	ok(strpos($up, 'is_uploaded_file(') !== false,
	   'the endpoint trusts a path it was handed rather than a real upload');

	/* The public page must not be able to read outside the directory. */
	ok(strpos($pg, 'dsh_valid_token($dg_token)') !== false,
	   'the token is not shape-checked before it is put in a path');
	ok(preg_match("/preg_match\('\/\^\[0-9a-f\]\{20\}\\$\/'/", $shl) === 1,
	   'the token pattern is not 20 hex characters, so a path could walk out of the directory');
	/* And it must be reachable by a crawler. */
	ok(strpos($pg, 'skulliance.php') === false,
	   'the share page includes the login gate; X would cache a card of error.php');
	ok(strpos($pg, 'summary_large_image') !== false, 'the share page declares no large card');
	ok(strpos($pg, 'site-header.php') !== false,
	   'a stranger arriving from the post gets no navigation at all');
	/* Shared posters are pruned, or the directory grows for ever. */
	ok(strpos($shl, 'function dsh_prune') !== false && strpos($up, 'dsh_prune(') !== false,
	   'nothing ever deletes an old shared poster');
	ok(strpos($shl, '$limit = 40') !== false,
	   'the prune is unbounded; one unlucky request would pay for a year of backlog');
}

echo "\n" . ($fail ? "$fail FAILED\n" : "all good\n");
exit($fail ? 1 : 0);
