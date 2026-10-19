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

echo "the script in the page actually parses\n";
{
	/*
	 * php -l CANNOT SEE INSIDE A <script> BLOCK, and this file is mostly
	 * one. An edit left two orphan lines from a replaced handler in the
	 * middle of the JS -- `php -l` was clean, the modal was dead, and the
	 * only symptom would have been a silent failure on every page of the
	 * site, because header.php includes this everywhere.
	 */
	$a = strpos($mod, '<script>');
	$b = strrpos($mod, '</script>');
	ok($a !== false && $b > $a, 'the modal has no script block');
	$js  = substr($mod, $a + 8, $b - $a - 8);
	$tmp = sys_get_temp_dir() . '/pdg-parse-' . getmypid() . '.js';
	file_put_contents($tmp, $js);
	$out = array(); $rc = 0;
	exec('node --check ' . escapeshellarg($tmp) . ' 2>&1', $out, $rc);
	@unlink($tmp);
	ok($rc === 0, 'the modal\'s JavaScript does not parse: ' . implode(' ', array_slice($out, 0, 3)));
}

echo "\nit can only ever show you your own day\n";
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
			ok(substr($t, -strlen('Trait art by @MMAXI404')) === 'Trait art by @MMAXI404',
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
			/* FIRST PERSON AND THEIR OWN NUMBER. "I did 14 things" is a
			   person talking; it also guarantees the post opens on the
			   half nobody else can write. */
			ok(preg_match('/^I did \d/', $t) === 1,
			   'post ' . $i . ' does not open with the player\'s own numbers: '
			 . substr($t, 0, 50));
		}
		/* The claim line has to name the handle AND point at the link,
		   which a bare @skulliance on its own line did not. */
		ok(strpos($posts[0], 'Claim your 3 traits from @skulliance below.') !== false,
		   'the post does not tell the reader what the link below it is');
		/* And the ask still has to be in there, with who qualifies and why
		   it is worth anything. */
		ok(stripos($posts[0], '3 DHC Fighter traits') !== false, 'the post does not say what is on offer');
		ok(preg_match('/new (here|to Skulliance)/i', $posts[0]) === 1
		   && stripos($posts[0], 'month') !== false,
		   'the post does not say who qualifies, so every active follower clicks for nothing');
		ok(stripos($posts[0], 'odds') !== false,
		   'the post does not say the odds are better, so three traits reads as three commons');
		/*
		 * AND IT SAYS WHAT IT IS A STREAK OF. "9 days running." sat right
		 * after the activity count and read as that many things a day for
		 * nine days -- the owner asked what his own post meant. It is the
		 * LOGIN streak, which is what the poster calls it.
		 */
		ok(strpos($posts[0], '9 day daily reward streak') !== false, 'the streak is missing from the card');
		ok(preg_match('/\d+ days running/', $posts[0]) === 0,
		   'the streak is named as a bare run of days, which reads as the activity repeating');
		/*
		 * AND IT NAMES THE RIGHT FEATURE. users.streak is the daily reward
		 * claim streak -- getCurrentDailyRewardStreak() in db.php, which
		 * also resets it to 0 at seven, so it never reads above 6.
		 * Calling it a login streak was simply the wrong mechanic.
		 */
		ok(stripos($posts[0], 'login streak') === false,
		   'the post calls it a login streak; users.streak is the daily reward claim streak');
		/* The STRIPPED source: the comment explaining why it is no longer
		   called a login streak contains the words "login streak", so
		   searching the raw file finds my own note and fails forever. */
		ok(stripos($modc, 'login streak') === false,
		   'the poster still calls it a login streak');
		ok(strpos($posts[1], 'days running') === false,
		   'a streak of 1 is announced as a streak: ' . $posts[1]);
	}
	/*
	 * EVERY REAL CHANNEL LABEL, read out of activity_channel_label() rather
	 * than typed here, so a channel added later is covered the day it
	 * appears.
	 *
	 * THE BUG THIS EXISTS FOR: the card read "including N " + label
	 * .toLowerCase(), and best.label is a CHANNEL NAME. That is grammatical
	 * for Missions and Raids and nonsense for most of the map -- "including
	 * 4 skull swap", "including 1 raids", "including 5 dhc fighters" were
	 * all real output, in a post whose job is advertising those features by
	 * name. So: a number is never left sitting directly in front of a
	 * label, and a label keeps the capitals it was given.
	 */
	preg_match('/function activity_channel_label.*?\);/s',
	           file_get_contents(__DIR__ . '/activity-lib.php'), $mm);
	preg_match_all("/=>\s*'([^']+)'/", $mm ? $mm[0] : '', $lm);
	$labels = $lm ? $lm[1] : array();
	ok(count($labels) > 15, 'the channel label map could not be read; only ' . count($labels) . ' found');

	$cases = array();
	foreach ($labels as $L) {
		/* Worst case for the budget: the longest ask, a four-figure day and
		   a three-figure streak, which is where the trim has to fire. */
		$cases[] = array('total' => 14,   'n' => 6,   'streak' => 9,   'label' => $L);
		$cases[] = array('total' => 1,    'n' => 1,   'streak' => 0,   'label' => $L);
		$cases[] = array('total' => 6,    'n' => 6,   'streak' => 0,   'label' => $L);
		$cases[] = array('total' => 1480, 'n' => 998, 'streak' => 365, 'label' => $L);
	}
	$tmp = sys_get_temp_dir() . '/pdg-labels-' . getmypid() . '.js';
	$js  = $src . "\nvar C = " . json_encode($cases) . ", out = [];\n"
	     /* Every ask, not just the one this seed happens to pick -- the
	        budget has to hold for the longest of them. */
	     . "for (var i = 0; i < C.length; i++) for (var k = 0; k < ASKS.length; k++) {\n"
	     . "  var saved = ASKS.slice(); ASKS.length = 0; ASKS.push(saved[k]);\n"
	     . "  out.push({ label: C[i].label, n: C[i].n,\n"
	     . "             t: shareText({ total: C[i].total, streak: C[i].streak,\n"
	     . "               best: { n: C[i].n, label: C[i].label }, ref: 'q7x', day: '2026-10-17' }) });\n"
	     . "  ASKS.length = 0; for (var z = 0; z < saved.length; z++) ASKS.push(saved[z]);\n"
	     . "}\nconsole.log(JSON.stringify(out));\n";
	file_put_contents($tmp, $js);
	$rows = json_decode(trim((string) @shell_exec('node ' . escapeshellarg($tmp) . ' 2>&1')), true);
	@unlink($tmp);
	ok(is_array($rows) && count($rows) === count($cases) * 4,
	   'the label sweep did not run: got ' . (is_array($rows) ? count($rows) : 'nothing'));
	if (is_array($rows) && $rows) {
		$long = $bare = $flat = $cut = 0; $worst = ''; $egBare = $egFlat = $egCut = '';
		foreach ($rows as $r) {
			$head = explode("\n\n", $r['t']);
			$head = $head[0];
			if (mb_strlen($r['t']) + 24 > 280) { $long++; $worst = $r['t']; }
			/* A number directly in front of the label, in any casing. */
			if (stripos($head, $r['n'] . ' ' . $r['label']) !== false) {
				$bare++; $egBare = $head;
			}
			/* And the label as given, not flattened. */
			if (strpos($head, $r['label']) === false
			    && stripos($head, $r['label']) !== false) { $flat++; $egFlat = $head; }
			/*
			 * AND IT MUST STILL BE A SENTENCE. The budget check above
			 * passes a post cut off mid-clause -- "148 days running."
			 * trimmed to "148\u2026" was under 280 and read as a
			 * typo. The trim drops whole clauses, so every card ends
			 * in a full stop or is not there at all.
			 */
			if (strpos($r['t'], "\xe2\x80\xa6") !== false
			    || ($head !== '' && substr($head, -1) !== '.')) { $cut++; $egCut = $head; }
		}
		ok($long === 0, $long . ' of ' . count($rows) . ' posts overrun 280: ' . mb_strlen($worst)
		   . ' chars plus a url, e.g. ' . substr($worst, 0, 70));
		ok($bare === 0, $bare . ' posts put a bare count in front of a channel name, e.g. "'
		   . $egBare . '"');
		ok($flat === 0, $flat . ' posts flatten a channel name\'s capitals, e.g. "' . $egFlat . '"');
		ok($cut === 0, $cut . ' posts are cut off mid-sentence instead of dropping a clause, e.g. "'
		   . $egCut . '"');
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

echo "\nthe page the invite actually lands on\n";
{
	/*
	 * THE POST LINKS TO digest.php, NOT TO /staking/. That is the page an
	 * arrival from X meets, and its buttons sat UNDERNEATH a full-width
	 * poster -- which on a phone is most of a screen on its own. The
	 * arrival landed on a stranger's artwork with nothing to press
	 * anywhere in sight, on the one page that exists to convert them.
	 */
	$pg2 = harness_strip_comments(file_get_contents(__DIR__ . '/digest.php'));
	$inv = strpos($pg2, '$dg_ok && $dg_ref');
	$els = strpos($pg2, 'elseif ($dg_ok)');
	ok($inv !== false && $els !== false && $inv < $els,
	   'the invited branch is gone from digest.php');
	if ($inv !== false && $els !== false) {
		$branch = substr($pg2, $inv, $els - $inv);
		$cta  = strpos($branch, '$dg_cta()');
		$shot = strpos($branch, '$dg_shot()');
		ok($cta !== false && $shot !== false && $cta < $shot,
		   'an invited arrival gets the poster before the buttons, which puts them off the '
		 . 'bottom of a phone');
		ok(strpos($branch, 'Three DHC Fighter traits') !== false,
		   'the page the post promised three traits on does not mention them');
	}
	/* A visitor who was NOT invited still gets the picture first: for them
	   it is the interesting thing and no ask has been set up. */
	$rest = $els !== false ? substr($pg2, $els) : '';
	$rc = strpos($rest, '$dg_cta()'); $rs = strpos($rest, '$dg_shot()');
	ok($rc !== false && $rs !== false && $rs < $rc,
	   'an ordinary visitor is asked to sign up before being shown anything');

	/* Written once, placed twice. Two copies is how one of them drifts. */
	ok(substr_count($pg2, 'class="cta"') === 1,
	   'the button row is duplicated in digest.php rather than printed from one place');
	ok(substr_count($pg2, 'class="shot"') === 1, 'the poster img is duplicated');

	/*
	 * AND THE IMG IS MEASURED, NOT ASSUMED. It carried width="1080"
	 * height="1350", which is what the poster used to be; it is 1200x630
	 * now and every poster shared before the change is still the old
	 * shape and still served. A wrong pair reserves a box of the wrong
	 * shape and shoves the page around as the image arrives.
	 */
	ok(strpos($pg2, 'width="1080" height="1350"') === false,
	   'the poster img still hardcodes the old portrait dimensions');
	ok(strpos($pg2, 'getimagesize(') !== false,
	   'the stored poster is not measured, so old and new posters cannot both be right');

	/*
	 * AND IT NAMES THE PERSON WHO SENT THEM.
	 *
	 * This said "whoever shared this gets three as well" while their
	 * username and avatar were drawn into the picture directly above the
	 * sentence. ?r= decodes to exactly one user id -- the account that
	 * will actually be paid -- so the coyness was pretending not to know
	 * something the page was already showing.
	 */
	ok(strpos($pg2, '$dg_from') !== false, 'the page does not name who sent the invite');
	ok(strpos($pg2, 'Whoever shared this') !== false,
	   'there is no wording left for when the sender cannot be established');
	/*
	 * EVERY use of the name, not just one. The name is printed in three
	 * places, so "is $e($dg_from) present anywhere" passes with two of the
	 * three escaped -- which is exactly how an unescaped one survives.
	 * Count instead: every mention is either a control use (assignment or
	 * a comparison) or an escaped print, and nothing else.
	 */
	$all  = preg_match_all('/\$dg_from/', $pg2);
	$esc  = preg_match_all('/\$e\(\$dg_from\)/', $pg2);
	$ctrl = preg_match_all('/\$dg_from\s*(?:=[^=]|!==)/', $pg2);
	ok($all === $esc + $ctrl,
	   ($all - $esc - $ctrl) . ' of the ' . $all . ' uses of the sender\'s name are printed '
	 . 'without escaping; a username is whatever Discord let them call themselves');
	/* No mysqlnd on this host. */
	ok(strpos($pg2, 'get_result(') === false, 'digest.php calls get_result(), which is fatal here');
	ok(strpos($pg2, '(int) $dg_inviter') !== false, 'the inviter id reaches the query uncast');

	/*
	 * NAMED ONLY WHEN THE POSTER IS REALLY THEIRS. ?d= and ?r= are
	 * independent, so a hand-made url can pair one person's artwork with
	 * another person's code -- and that would caption a stranger's day
	 * with whatever name the url asked for.
	 */
	ok(strpos($pg2, 'dsh_token_belongs_to(') !== false,
	   'the sender is named without checking the poster is theirs, so any url can put any '
	 . 'name on anybody\'s day');
	/* Run it, rather than read it. */
	require_once __DIR__ . '/lib/digest-share-lib.php';
	$tk = dsh_token(4821, date('Y-m-d', strtotime('-1 day')));
	ok(dsh_token_belongs_to($tk, 4821) === true, 'a player is not recognised as owning their own poster');
	ok(dsh_token_belongs_to($tk, 99) === false, 'somebody else is credited with this poster');
	ok(dsh_token_belongs_to($tk, 0) === false && dsh_token_belongs_to($tk, -1) === false,
	   'a non-user owns a poster');
	ok(dsh_token_belongs_to('../../etc/passwd', 4821) === false, 'a non-token is accepted');
	ok(dsh_token_belongs_to(dsh_token(4821, date('Y-m-d', strtotime('-' . DSH_KEEP_DAYS . ' day'))), 4821)
	   === true, 'a poster still inside the keep window is not recognised as its owner\'s');
	ok(dsh_token_belongs_to(dsh_token(4821, date('Y-m-d', strtotime('-400 day'))), 4821) === false,
	   'the ownership scan runs far past the keep window, which is work for nothing');

	/* The code has to survive the hop to index.php: the cookie is already
	   set, but a browser that refused it still has the query string. */
	ok(strpos($pg2, "'index.php?r=' . rawurlencode") !== false,
	   'the claim button drops the referral code on the way to the landing page');
	/* And the landing page has to recognise the cookie, because after that
	   hop the query string is all there is to lose. */
	$ix = harness_strip_comments(file_get_contents(__DIR__ . '/index.php'));
	ok(strpos($ix, 'REF_COOKIE') !== false,
	   'index.php keys the invite on ?r= alone, so an arrival who clicked through from the '
	 . 'poster is never told what they were promised');
}

echo "\nthe poster's geometry, driven\n";
{
	/*
	 * Shelled out to player-digest-geometry-harness.js so there is one copy
	 * of those cases. It exists because this poster shipped with the footer
	 * and the streak printed ON TOP of the bottom row of artwork, on a real
	 * post, where a player saw it before I did -- the old layout grew
	 * downwards and clamped the footer back up into the art when it ran out
	 * of canvas, which guarantees a collision rather than an overflow.
	 * Reported as skipped, not failed, when node is not on this machine.
	 */
	$node = trim((string) @shell_exec('command -v node 2>/dev/null'));
	if ($node === '') {
		echo "  --   node not found; run player-digest-geometry-harness.js separately\n";
	} else {
		$res = trim((string) @shell_exec(escapeshellcmd($node) . ' '
		     . escapeshellarg(__DIR__ . '/player-digest-geometry-harness.js') . ' 2>&1'));
		ok(strpos($res, 'OK') === 0,
		   'the poster\'s layout collides or overflows: ' . substr($res, 0, 200));
	}
}

echo "\n" . ($fail ? "$fail FAILED\n" : "all good\n");
exit($fail ? 1 : 0);
