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

echo "\n" . ($fail ? "FAILED: $fail check(s)\n" : "all avatar checks passed\n");
exit($fail ? 1 : 0);
