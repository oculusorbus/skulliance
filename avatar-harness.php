<?php
/* avatar-harness.php - CLI only. No database, no network.
 *
 * THE AVATAR CASCADE: render the stored hash, and if it 404s ask Discord once,
 * write the answer back, and fall to the placeholder only when there is
 * genuinely nothing to show. See avatar-lib.php.
 *
 * What is worth testing here is the part that is pure: the URL builder, which
 * decides the extension and refuses malformed rows, and the SHAPE of the
 * endpoint and the browser listener -- both of which have properties that are
 * easy to break and invisible when broken.
 *
 * Usage: php avatar-harness.php
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

require_once __DIR__ . '/avatar-lib.php';

$fail = 0;
function ok($cond, $what) { global $fail; if (!$cond) { $fail++; echo "  FAIL  $what\n"; } }
function no_comments($s) {
	$s = preg_replace('!/\*.*?\*/!s', '', $s);
	return preg_replace('~(?<![:/])//.*$~m', '', $s);
}

/* ---------- 1. the URL ----------------------------------------------------- */
$id = '123456789012345';
ok(strpos(avatar_url($id, 'abc12345def67890'), AVATAR_CDN . $id . '/') === 0,
   'a normal avatar does not build a CDN url');

/* ANIMATED AVATARS. Discord prefixes the hash with a_ and serves that one as
   .gif only -- .png 404s. The hand-built copies this replaces hardcode .png
   (and a few .jpg), so an animated avatar is broken on some pages and fine on
   others depending which file drew it. */
ok(substr(avatar_url($id, 'a_abc12345def678'), 0, strpos(avatar_url($id,'a_abc12345def678'),'?')) === AVATAR_CDN . $id . '/a_abc12345def678.gif',
   'an animated avatar is not served as .gif, so it 404s');
ok(strpos(avatar_url($id, 'abc12345def67890'), '.png') !== false,
   'a still avatar is not served as .png');

/* Anything malformed falls to the placeholder rather than building a URL. */
foreach (array(array('', 'abc12345def67890'), array($id, ''),
               array('not-an-id', 'abc12345def67890'),
               array($id, '../../../etc/passwd'),
               array($id, 'short'), array('12', 'abc12345def67890')) as $bad) {
	ok(avatar_url($bad[0], $bad[1]) === AVATAR_PLACEHOLDER,
	   "malformed row ('{$bad[0]}','{$bad[1]}') built a URL instead of the placeholder");
}
ok(AVATAR_PLACEHOLDER === 'icons/skull.png',
   'the placeholder moved; 108 hand-written copies across the platform still say icons/skull.png');

/* ---------- 2. the endpoint's safety properties ---------------------------- */
$ep = no_comments(file_get_contents(__DIR__ . '/ajax/avatar-refresh.php'));

/* THE ONE THAT MATTERS. Without the users lookup this is an open proxy that
   resolves any Discord account for anyone who can reach the URL, on our bot's
   credentials and our rate limit. */
ok(strpos($ep, "FROM users WHERE discord_id") !== false,
   'the endpoint no longer checks the id is one of ours -- it is an open Discord lookup proxy');
/* An unknown id must be indistinguishable from a malformed one, or this
   answers "does this Discord account play here?" for anybody who asks. */
ok(substr_count($ep, "'why' => 'bad id'") === 2,
   'an unknown id and a malformed id give different answers, which leaks membership');
ok(strpos($ep, 'AVATAR_RECHECK_MINUTES') !== false,
   'the re-check throttle is gone; a page of broken avatars would retry on every view');

/* A failure to reach Discord must not be written down as "no avatar": that
   turns a rate limit into a permanently blanked avatar. */
ok(preg_match('/\$hash === null[\s\S]{0,400}?UPDATE users SET avatar_checked_at = NOW\(\)/', $ep) === 1,
   'an unreachable Discord updates the stored hash; a rate limit would blank a good avatar');
/* Scoped to the null branch ITSELF, not a character window after it: the
   success path's own "SET avatar =" sits a few lines below and a loose window
   matches that instead, failing on correct code. */
$nullAt  = strpos($ep, '$hash === null');
$nullEnd = $nullAt === false ? false : strpos($ep, 'exit;', $nullAt);
$nullBlk = ($nullAt !== false && $nullEnd !== false)
         ? substr($ep, $nullAt, $nullEnd - $nullAt) : '';
ok($nullBlk !== '', 'cannot find the unreachable-Discord branch to check');
ok(strpos($nullBlk, 'SET avatar =') === false,
   'the failure path writes the avatar column, which it must never do');

/* ---------- 2b. it must be able to actually reach Discord ------------------ */
/*
 * THE FIRST VERSION SHIPPED UNABLE TO. $bot_token lives in
 * credentials/webhooks_credentials.php, which webhooks.php includes and
 * nothing else does -- so an endpoint pulling in db.php and avatar-lib.php had
 * no token, avatar_fetch_hash() returned null on its first line, and every
 * avatar fell to the placeholder. From the outside that is indistinguishable
 * from "all these users really did delete their accounts", which is exactly
 * how it was reported. The harness tested the endpoint's SAFETY properties
 * thoroughly and never once asked whether it could do its job.
 */
$libSrc = no_comments(file_get_contents(__DIR__ . '/avatar-lib.php'));
ok(strpos($libSrc, "credentials/webhooks_credentials.php") !== false,
   'avatar-lib.php does not load the credentials file, so $bot_token is never set and '
 . 'the fetch returns null before it reaches Discord');
ok(strpos($libSrc, 'include_once') !== false,
   'the credentials include is not include_once; webhooks.php may have loaded it already');
ok(function_exists('avatar_can_fetch'),
   'there is no way to tell a missing token from a failed call');

/* A missing token must NOT be stamped as a failed check: stamping starts an
   hour's backoff against a fault that will still be there in an hour, and
   turns a misconfiguration into "these users have no avatar". */
$cfgAt  = strpos($ep, 'avatar_can_fetch()');
$cfgEnd = $cfgAt === false ? false : strpos($ep, 'exit;', $cfgAt);
$cfgBlk = ($cfgAt !== false && $cfgEnd !== false) ? substr($ep, $cfgAt, $cfgEnd - $cfgAt) : '';
ok($cfgBlk !== '', 'the endpoint does not check whether it is configured at all');
ok($cfgBlk !== '' && strpos($cfgBlk, 'avatar_checked_at') === false,
   'an unconfigured endpoint stamps the throttle, so fixing the config leaves every '
 . 'user backed off for an hour');
ok($cfgBlk !== '' && strpos($cfgBlk, 'error_log') !== false,
   'an unconfigured endpoint fails silently -- it looks exactly like working');

/* ---------- 3. the browser listener ---------------------------------------- */
$hd = no_comments(file_get_contents(__DIR__ . '/header.php'));

/* CAPTURE, NOT BUBBLE. Error events do not bubble. A listener registered
   without the capture flag never fires for an image, and the whole feature is
   silently dead -- the page looks exactly as it does today. */
ok(preg_match("/addEventListener\('error'[\s\S]{0,2000}?\}, true\)/", $hd) === 1,
   "the error listener is not registered with capture:true -- error does not bubble, so it never fires");
ok(strpos($hd, "img.dataset.avatarTried") !== false,
   'nothing marks an image as already tried, so a broken replacement loops');
/* One request per id, not per image: a leaderboard shows the same player many
   times and every copy is about to fail. */
ok(strpos($hd, 'inflight[id]') !== false,
   'the refresh is not deduped per id; one broken player on a leaderboard fires many identical calls');
ok(strpos($hd, 'querySelectorAll') !== false,
   'only the image that failed is repaired, leaving the other copies broken');

/*
 * SERIAL, NOT JUST DEDUPED. Deduping by id still let a page with five
 * DIFFERENT broken avatars fire five refreshes at once; Discord rate-limits
 * the burst and the endpoint writes each rejection down as an hour's backoff,
 * so the next hour's single attempt lands in the next burst and loses again.
 * One account stayed broken for days that way and looked, from outside,
 * exactly like a deleted Discord account. The queue is what stops us causing
 * the failure we then back off from.
 */
ok(strpos($hd, 'queue = queue.then(') !== false,
   'the refreshes are not chained, so a page of broken avatars fires them all at once '
 . 'and rate-limits itself');
ok(preg_match('/SPACING_MS\s*=\s*\d+/', $hd) === 1,
   'there is no gap between queued refreshes');

/*
 * SVG <image> IS THE OTHER HALF OF "EVERY AVATAR". The realms map draws its
 * markers as SVG images -- tagName 'image', source in href, not src -- so a
 * listener that tests for 'IMG' and reads src skips the one page where every
 * face on screen is an avatar. It would heal everywhere except there.
 */
ok(strpos($hd, "=== 'image'") !== false,
   'the listener ignores SVG <image>, so the realms map never heals');
ok(strpos($hd, "image[href*=") !== false,
   'the repair pass only looks for <img>, so SVG copies keep the broken source');
/* The HANDLER must derive it, not just the helper exist: hardcoding
   var attr = 'src' leaves avatarAttr() defined and unused, and SVG images get
   a src attribute written that nothing reads. */
ok(preg_match('/var attr\s*=\s*avatarAttr\(/', $hd) === 1,
   'the handler hardcodes the source attribute instead of deriving it, so one element '
 . 'kind is written wrongly');

/* ---------- 6. the realms map shows every avatar ------------------------- */
/*
 * It used to draw a skull for any realm outside this month's raid pairs, which
 * reads as a field of broken images rather than as a signal -- and a realm is
 * only on the map at all because it has been raided recently enough to
 * qualify. The glow and the connecting lines already carry the raid state.
 */
$mapSrc = file_get_contents(__DIR__ . '/map.js');
ok(strpos($mapSrc, 'activeRaidRealms') === false,
   'the map decides avatar-or-skull from raid activity again; every realm should '
 . 'show its owner');
ok(preg_match("/href:\s*realm\.user_image/", $mapSrc) === 1,
   'the map marker no longer takes its image straight from the realm owner');

/* ---------- 4. the pages that were actually reported broken ---------------- */
/*
 * MEASURED AGAINST THE LIVE CDN, not assumed: for the same hash, .png returns
 * 200 wherever anything does, while .jpg 404s on some avatars. Half the
 * platform's hand-built URLs asked for .jpg, which is a plain bug and nothing
 * to do with a hash going stale -- no amount of refreshing from Discord would
 * have fixed it. avatar_url() picks the extension from the hash.
 */
/*
 * CHECKED PER SITE, NOT PER FILE. "does this file mention avatar_url anywhere"
 * passes while one of its two renderers regresses, and a regex for a literal
 * URL never matches db.php at all, because it builds them by concatenation --
 * the ".jpg" sits in its own string. Both of those let a reinstated bug
 * through when they were written loosely, which mutation testing showed. Each
 * site is named by the CSS class it renders.
 */
/* Line-scoped. A character window around the marker reaches into the
   NEIGHBOURING renderers -- db.php has forty more of these within a few
   hundred bytes, most still on .jpg -- and reports their faults against this
   one. The marker is the exact line that renders the avatar. */
/* TWO DIFFERENT CONCERNS, so two lists. A line that BUILDS the URL must call
   avatar_url() and must not name a file type; a line that RENDERS the <img>
   must not carry an inline onerror, which pre-empts the refresh -- and those
   are rarely the same line, because the URL is usually built into a variable
   a few lines above the tag that uses it.

   Line-scoped either way. A byte window reaches into the forty neighbouring
   renderers still in db.php and blames this one for their faults. */
$builders = array(
	'db.php'           => array("class='rtc-avatar'", "class='rc-avatar'"),
	'dhcarena.php'     => array('$av = ($o[\'discord_id\']'),
	'leaderboards.php' => array('$av   = htmlspecialchars(avatar_url'),
	/* The Collection card payload feeds the grid AND the Fighter panel, and
	   ajax/dhc-fighter.php rebuilds the same payload for a single serial --
	   a wrong extension in either broke the panel. */
	'dhcgallery.php'      => array('$av = avatar_url('),
	'ajax/dhc-fighter.php'=> array('$avatar = avatar_url('),
);
$imgTags = array(
	'db.php'       => array("class='rtc-avatar'", "class='rc-avatar'"),
	'dhcarena.php' => array('<img src="<?php echo htmlspecialchars($av)'),
);
function nf_find($lines, $mark) {
	foreach ($lines as $i => $ln) if (strpos($ln, $mark) !== false) return $i;
	return null;
}
function nf_block($lines, $hit) {
	return ($hit > 0 ? $lines[$hit - 1] : '') . "\n" . $lines[$hit]
	     . "\n" . (isset($lines[$hit + 1]) ? $lines[$hit + 1] : '');
}
foreach ($builders as $f => $marks) {
	$lines = explode("\n", no_comments(file_get_contents(__DIR__ . '/' . $f)));
	foreach ($marks as $mark) {
		$hit = nf_find($lines, $mark);
		ok($hit !== null, "$f no longer contains \"$mark\" -- this check is testing nothing");
		if ($hit === null) continue;
		$blk = nf_block($lines, $hit);
		ok(strpos($blk, 'avatar_url(') !== false,
		   "$f \"$mark\" hand-builds its avatar URL again instead of calling avatar_url()");
		ok(strpos($blk, '.jpg') === false,
		   "$f \"$mark\" asks the CDN for .jpg, which 404s where .png is fine");
	}
}
foreach ($imgTags as $f => $marks) {
	$lines = explode("\n", no_comments(file_get_contents(__DIR__ . '/' . $f)));
	foreach ($marks as $mark) {
		$hit = nf_find($lines, $mark);
		ok($hit !== null, "$f no longer contains the img tag \"$mark\"");
		if ($hit === null) continue;
		/* The tag itself only. An onerror that HIDES the image is as bad as one
		   that swaps the skull in: the refresh lands on an element nobody sees. */
		ok(stripos($lines[$hit], 'onerror') === false,
		   "$f \"$mark\" has an inline onerror again, which takes the refresh's turn away");
	}
}

$dbSrc = no_comments(file_get_contents(__DIR__ . '/db.php'));

/*
 * A URL BUILT IN SQL IS INVISIBLE TO EVERY SEARCH FOR ONE. The realms map
 * concat()ed its avatar URL inside the query with ".jpg" hardcoded, so it
 * survived the pass that converted every PHP-built URL on the same page --
 * grepping for the places that build avatar URLs simply does not find it.
 */
ok(!preg_match('/concat\s*\(\s*"https:\/\/cdn\.discordapp\.com\/avatars/i', $dbSrc),
   'db.php builds an avatar URL inside a SQL query again -- invisible to every search '
 . 'for avatar URLs, and it cannot use avatar_url()');
ok(strpos($dbSrc, 'avatar_url($row[\'u_discord\'], $row[\'u_avatar\'])') !== false,
   'the realms map no longer builds its avatar through avatar_url()');
ok(!preg_match('/avatar_url\([^)]*\)[^>]{0,160}onerror/', $dbSrc),
   'db.php still puts an inline onerror on an avatar, which pre-empts the refresh');

/* db.php is where the shared builder is loaded for the whole platform. */
ok(preg_match("/require_once __DIR__ \. '\/avatar-lib\.php'/", $dbSrc) === 1,
   'db.php does not load avatar-lib.php, so every page calling avatar_url() fatals');

/* ---------- 5. no hand-built avatar URL anywhere ---------------------------- */
/*
 * THE WHACK-A-MOLE ENDED HERE. Each page fixed individually revealed another
 * with its own hiding place -- a .jpg in a card payload, a URL concat()ed
 * inside SQL, an onerror that hid the image. There were 70 of these. The only
 * way to stop finding them one report at a time is to assert that none exist.
 *
 * dropship/ is excluded: a separate application with its own db.php and no
 * avatar-lib, so its avatars are its own problem.
 */
/* Three legitimate exceptions, each for a different reason:
     header.php      -- the listener's own CDN prefix, which is how it RECOGNISES
                        an avatar that failed; it builds nothing.
     digest-harness  -- fixtures, not URLs anything renders.
     gallery.php     -- built in JavaScript from a client-side payload, so no PHP
                        function can reach it. It already asks for .png, the safe
                        form, and a failure still goes through the listener; only
                        an animated avatar is wrong there. Worth doing, not urgent. */
$avatarExempt = array('header.php', 'digest-harness.php', 'gallery.php');
$built = array();
foreach (glob(__DIR__ . '/*.php') as $f) {
	if (strpos(basename($f), 'avatar-') === 0) continue;
	if (in_array(basename($f), $avatarExempt, true)) continue;
	$src = no_comments(file_get_contents($f));
	if (preg_match('/["\']https:\/\/cdn\.discordapp\.com\/avatars\//', $src)) {
		$built[] = basename($f);
	}
}
foreach (glob(__DIR__ . '/ajax/*.php') as $f) {
	$src = no_comments(file_get_contents($f));
	if (preg_match('/["\']https:\/\/cdn\.discordapp\.com\/avatars\//', $src)) {
		$built[] = 'ajax/' . basename($f);
	}
}
ok(!$built,
   'these still hand-build a Discord avatar URL instead of calling avatar_url(), so they '
 . "pick their own file type and miss the refresh: " . implode(', ', $built));

/* Every file that calls it must be able to reach it. A library relying on its
   caller having loaded db.php fatals the moment something includes it alone. */
$callers = array();
foreach (array_merge(glob(__DIR__ . '/*.php'), glob(__DIR__ . '/ajax/*.php')) as $f) {
	$b = basename($f);
	if (strpos($b, 'avatar-') === 0 || strpos($b, '-harness') !== false) continue;
	$src = no_comments(file_get_contents($f));
	if (strpos($src, 'avatar_url(') === false) continue;
	/* Any spelling: include 'db.php', require_once __DIR__ . '/avatar-lib.php',
	   include '../db.php'. Matching the literal filename is enough and does not
	   care how the path was assembled. */
	if (strpos($src, 'avatar-lib.php') !== false) continue;
	if (preg_match('/(include|require)(_once)?[^;\n]*db\.php/', $src)) continue;
	$callers[] = $b;
}
ok(!$callers,
   'these call avatar_url() without loading db.php or avatar-lib.php, so they fatal on an '
 . 'undefined function: ' . implode(', ', $callers));

echo "\n" . ($fail ? "FAILED: $fail check(s)\n" : "all avatar checks passed\n");
exit($fail ? 1 : 0);
