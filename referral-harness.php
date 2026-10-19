<?php
/* referral-harness.php - CLI only. No database, no network.
 *
 * Invite a friend: three traits each, on better odds.
 *
 * THE FAILURE MODES HERE ARE ALL SILENT. Nothing throws if a referral never
 * pays -- the player just does not get a trait and nobody finds out:
 *
 *   - last_login is UPDATED at login. Read it afterwards and every
 *     returning player looks active, so no dormant invite ever pays.
 *   - ?r= does not survive the Discord round trip, so the code has to be in
 *     a cookie before the OAuth hop or the credit has nothing to go on.
 *   - dhcf_award() only queues a reveal for the session user, and a referral
 *     is awarded during the ARRIVAL's request -- so the inviter sees nothing
 *     unless it is replayed.
 *   - The post pays for a sign-in, not for posting. The moment it pays for
 *     a like or a repost it is against X's rules.
 *
 * Usage: php referral-harness.php
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

$fail = 0;
function ok($cond, $what) { global $fail; if (!$cond) { $fail++; echo "  FAIL  $what\n"; } }

require_once __DIR__ . '/harness-strip.php';
require_once __DIR__ . '/lib/referral-lib.php';
require_once __DIR__ . '/dhcfighters-config.php';
$lib  = harness_strip_comments(file_get_contents(__DIR__ . '/lib/referral-lib.php'));
$skl  = harness_strip_comments(file_get_contents(__DIR__ . '/skulliance.php'));
$dbp  = harness_strip_comments(file_get_contents(__DIR__ . '/db.php'));
$cfg  = file_get_contents(__DIR__ . '/dhcfighters-config.php');
$dfl  = harness_strip_comments(file_get_contents(__DIR__ . '/dhcfighters-lib.php'));

echo "a code names exactly one player\n";
{
	foreach (array(1, 7, 42, 4821, 99999) as $id) {
		$c = ref_code($id);
		ok($c !== '' && ref_decode($c) === $id, "code for user $id does not round-trip: '$c'");
	}
	ok(ref_code(0) === '' && ref_code(-5) === '', 'a non-user gets a code');
	/* A typo must not resolve to somebody else and pay the wrong person --
	   which is all the check characters are for; the link is public. */
	$c = ref_code(4821);
	$bad = 0;
	for ($i = 0; $i < strlen($c); $i++) {
		$t = $c; $t[$i] = ($t[$i] === 'a') ? 'b' : 'a';
		if ($t !== $c && ref_decode($t) > 0) $bad++;
	}
	ok($bad === 0, $bad . ' single-character typos still resolve to a user');
	foreach (array('', 'x', '../../etc', 'zzzzzzzzzzzzzzzzz', '<script>') as $junk) {
		ok(ref_decode($junk) === 0, "junk code '$junk' resolved to a user");
	}
}

echo "\nonly a new or long-gone arrival pays out\n";
{
	/* The rule, read off the code that enforces it. */
	ok(strpos($lib, '$isNew') !== false && strpos($lib, '$isDormant') !== false,
	   'the eligibility test is gone');
	ok(strpos($lib, 'REF_DORMANT_DAYS * 86400') !== false,
	   'dormancy is not measured in the constant the rest of the platform uses');
	ok(REF_DORMANT_DAYS === 30,
	   'dormancy is ' . REF_DORMANT_DAYS . ' days; gallery.php and image-cache.php both use a month');
	ok(strpos($lib, 'if (!$isNew && !$isDormant) { ref_forget(); return 0; }') !== false,
	   'an active player following a link gets paid for, which makes the whole thing farmable');
	/* Never yourself. */
	ok(strpos($lib, '$inviter_id === $arrival_id') !== false, 'a self-referral pays out');
	ok(strpos($lib, "=== \$id) return;") !== false,
	   'the capture stores your own code when you click your own link');
	/* Once per friend, ever, or one returning friend is an annuity. */
	ok(strpos($lib, "source_detail = 'invited:") !== false,
	   'nothing stops the same friend paying out again every month');
}

echo "\nit pays three each, on the better table\n";
{
	ok(REF_INVITER_DRAWS === 3, 'the inviter gets ' . REF_INVITER_DRAWS . ', not 3');
	/* The arrival's three are the OFFER, not generosity -- the post says
	   "get 3 yourself", so anything less makes the post a lie. */
	ok(REF_ARRIVAL_DRAWS === 3,
	   'the arrival gets ' . REF_ARRIVAL_DRAWS . ' but the post promises 3');

	$t = dhcf_table_for(REF_SOURCE);
	ok(is_array($t) && isset($t['common']),
	   'the referral source has no tier table, so it falls back to the ordinary drop');
	/* placement_3, not run. run is 55% common and three of those is three
	   commons -- which is the entire reason this is not the run table. */
	ok(is_array($t) && $t['common'] <= 30,
	   'referrals draw at ' . ($t['common'] ?? '?') . '% common; the run table is 55% and three '
	 . 'of those is why nobody would bother twice');
	ok(is_array($t) && $t['legendary'] >= 10,
	   'the legendary chance is ' . ($t['legendary'] ?? '?') . '%, which is not "better odds"');
	/* And the cap has to allow more than one invite a day, since each costs
	   three draws plus the arrival's. */
	ok(dhcf_cap(REF_SOURCE) >= 12,
	   'the cap is ' . dhcf_cap(REF_SOURCE) . ' draws, which is fewer than four invites a day');
}

echo "\nthe login hook reads last_login before it overwrites it\n";
{
	/*
	 * THE ORDERING IS THE WHOLE THING. skulliance.php writes last_login on
	 * the way in; asking afterwards makes every returning player look
	 * active and every owed reveal look already-seen. Nothing errors.
	 */
	$read   = strpos($skl, 'SELECT last_login FROM users');
	$write  = strpos($skl, 'UPDATE users SET last_login = NOW()');
	$credit = strpos($skl, 'ref_credit($conn');
	ok($read !== false, 'the previous last_login is never read');
	ok($write !== false, 'the last_login update is gone');
	ok($read !== false && $write !== false && $read < $write,
	   'last_login is read AFTER it is updated, so no dormant invite can ever pay');
	ok($credit !== false && $read < $credit,
	   'the credit runs before the previous login is read');
	ok(strpos($skl, 'ref_replay($conn') !== false,
	   'the inviter is never shown the traits earned while they were away');
	/* The capture has to happen before any output, and on any page. */
	ok(strpos($dbp, 'ref_capture();') !== false,
	   'nothing captures ?r=, so the code is lost at the Discord round trip');
	$cap = strpos($dbp, 'ref_capture();');
	ok($cap !== false && $cap < strpos($dbp, "ini_set('display_errors', 1)"),
	   'the capture runs late; setcookie() after output does nothing');
	ok(strpos($lib, "'samesite' => 'Lax'") !== false,
	   'the cookie is SameSite=Strict or unset; an arrival clicking from X would not carry it');
}

echo "\nthe inviter finds out\n";
{
	/* dhcf_award() queues a reveal only for the session user, and a referral
	   is awarded inside the ARRIVAL's request. */
	ok(strpos($dfl, '=== $user_id) {') !== false,
	   'dhcf_award() no longer scopes the reveal to the session user; the arrival would see '
	 . 'the inviter\'s traits');
	ok(strpos($lib, 'function ref_replay') !== false, 'there is no replay, so the inviter sees nothing');
	ok(strpos($lib, 'sendDM(') !== false, 'the inviter is not told while they are away');
	/* Three at once must all be revealable. */
	ok(strpos($dfl, "count(\$_SESSION['dhcf_unseen']) < 9") !== false,
	   'the reveal queue is still bounded below 9, so a triple invite loses one');
	/* The replayed payload must be built by the SAME function a live drop
	   uses, or the two drift. */
	ok(strpos($dfl, 'function dhcf_drop_payload') !== false,
	   'the drop payload is not a shared function, so a replayed reveal can differ from a real one');
	ok(strpos($lib, 'dhcf_drop_payload($conn') !== false,
	   'the replay builds its own payload shape');
}

echo "\nnothing is paid for posting\n";
{
	/*
	 * X's platform-manipulation rules prohibit incentivising engagement --
	 * likes, reposts, follows, replies. This pays for a SIGN-IN on our own
	 * platform, which is a different thing, and it is worth not crossing
	 * later: the Mallard Order scheme pays for exactly that and verifies it
	 * by making players paste the permalink of their own comment.
	 */
	$mod = harness_strip_comments(file_get_contents(__DIR__ . '/player-digest.php'));
	ok(strpos($mod, 'dhcf_award') === false && strpos($mod, 'award') === false,
	   'the modal awards something, which would mean paying for the post itself');
	$share = file_get_contents(__DIR__ . '/ajax/digest-share.php');
	ok(strpos($share, 'dhcf_award') === false,
	   'the share endpoint awards a trait; that is paying for a post on X');
	/* The payout lives in the login path and nowhere else. */
	ok(strpos($lib, 'function ref_credit') !== false
	   && strpos($skl, 'ref_credit($conn') !== false,
	   'the credit is not on the login path');
}

echo "\nthe cache rewards the habit, not the post\n";
{
	$ep   = harness_strip_comments(file_get_contents(__DIR__ . '/ajax/player-digest.php'));
	$cfgc = harness_strip_comments($cfg);
	$shr  = harness_strip_comments(file_get_contents(__DIR__ . '/ajax/digest-share.php'));

	/*
	 * IT HANGS ON OPENING THE DIGEST, which already requires having done
	 * something the day before. Hanging it on Share would be paying for a
	 * post on X, the one thing that puts this whole scheme on the wrong
	 * side of the platform-manipulation rule the block above tests for.
	 */
	ok(strpos($ep, 'dhcf_cache_day(') !== false,
	   'the digest endpoint never rolls for a cache');

	/*
	 * AND THE LIBRARY IS LOADED BEFORE IT IS ASKED. dhcf_cache_day() is
	 * defined in dhcfighters-config.php, and NOTHING in this endpoint's
	 * include chain pulls that in -- db.php requires it only from inside a
	 * function. Calling it before the require is a fatal, and the
	 * endpoint's own shutdown handler converts a fatal into {show:false},
	 * so the symptom is not a missing cache: it is NO DIGEST AT ALL, for
	 * every player, with nothing in the response to say why. This is how
	 * it was first written.
	 */
	$req  = strpos($ep, "require_once __DIR__ . '/../dhcfighters-lib.php'");
	$call = strpos($ep, 'dhcf_cache_day(');
	ok($req !== false, 'the endpoint never loads the library that defines the roll');
	ok($req !== false && $call !== false && $req < $call,
	   'dhcf_cache_day() is called before the file defining it is loaded, which fatals the '
	 . 'whole endpoint and shows nobody a digest');
	/* And the config is what actually defines it, so requiring the lib has
	   to be enough -- the lib requires the config at its top level. */
	ok(strpos($dfl, "require_once __DIR__ . '/dhcfighters-config.php'") !== false,
	   'dhcfighters-lib.php no longer loads the config, so requiring the lib does not '
	 . 'define dhcf_cache_day()');
	ok(strpos($shr, 'digestcache') === false && strpos($shr, 'dhcf_cache_day') === false,
	   'the cache is awarded on Share, which is paying for a post on X');

	/*
	 * DECIDED BY THE DAY, NOT BY A DIE ROLL. The seen-once marker is in
	 * localStorage, so a per-request random roll could be re-rolled by
	 * clearing site data and reloading until it paid out.
	 */
	$fn = strpos($cfgc, 'function dhcf_cache_day');
	ok($fn !== false, 'dhcf_cache_day is gone, so nothing decides the cache day');
	if ($fn !== false) {
		$body = substr($cfgc, $fn, strpos($cfgc, "\n}", $fn) - $fn);
		foreach (array('rand', 'random_int', 'random_bytes', 'shuffle', 'uniqid',
		               'microtime', 'time()', 'lcg_value') as $w) {
			ok(strpos($body, $w) === false,
			   "the cache roll calls $w(), so reloading can re-roll it until it pays");
		}
	}

	/* Same answer for the same player and day, however often it is asked. */
	$drift = 0;
	for ($u = 1; $u <= 200; $u++) {
		$first = dhcf_cache_day($u, '2026-10-18');
		for ($n = 0; $n < 3; $n++) if (dhcf_cache_day($u, '2026-10-18') !== $first) $drift++;
	}
	ok($drift === 0, "a player's cache day changed under $drift repeat calls");

	/*
	 * RARE, BUT NOT A FIXED LUCKY FEW. Two separate failures look identical
	 * from one day's numbers: odds set so wide nobody ever gets one, and a
	 * hash of the user alone, which would hand the same players a cache
	 * every single day and never pay anyone else.
	 */
	$day_a = $day_b = array();
	for ($u = 1; $u <= 2400; $u++) {
		if (dhcf_cache_day($u, '2026-10-18')) $day_a[] = $u;
		if (dhcf_cache_day($u, '2026-10-19')) $day_b[] = $u;
	}
	$hits = count($day_a);
	ok($hits > 2400 / (DHCF_CACHE_ODDS * 2) && $hits < 2400 / (DHCF_CACHE_ODDS / 2),
	   sprintf('%d of 2400 players get a cache, nowhere near the 1-in-%d intended',
	           $hits, DHCF_CACHE_ODDS));
	/*
	 * AND THE ODDS THEMSELVES HAVE TO BE REACHABLE. The bounds above only
	 * check the hash matches whatever DHCF_CACHE_ODDS says, so they pass
	 * just as happily at 1-in-500 -- once every sixteen months of daily
	 * opens, which is a feature nobody ever sees. "Some days, not most."
	 */
	ok(DHCF_CACHE_ODDS >= 4 && DHCF_CACHE_ODDS <= 40,
	   'a cache lands once every ' . DHCF_CACHE_ODDS . ' days of opening the digest, which is '
	 . (DHCF_CACHE_ODDS < 4 ? 'most days, not a surprise' : 'too rare to build a habit on'));
	ok($day_a !== $day_b,
	   'the same players get a cache every day, because the day is not in the hash');
	ok(count(array_intersect($day_a, $day_b)) < $hits,
	   'every one of yesterday\'s winners wins again today');

	/* A cache is DHCF_CACHE_DRAWS traits, so the cap cannot sit below it. */
	ok(DHCF_CACHE_DRAWS > 0, 'a cache pays ' . DHCF_CACHE_DRAWS . ' traits');
	ok(dhcf_cap('digestcache') >= DHCF_CACHE_DRAWS,
	   'the cap is ' . dhcf_cap('digestcache') . ', below the '
	   . DHCF_CACHE_DRAWS . ' a cache pays, so a cache can never land in full');
	/* The modal says "better odds on the rare ones" -- the same claim the
	   invite post makes, so it has to draw on the same table, not on run. */
	$ct = dhcf_table_for('digestcache');
	ok($ct === $t, 'a cache draws on worse odds than an invite, which the modal denies');
	ok(is_array($ct) && $ct['common'] <= 30,
	   'a cache is ' . ($ct['common'] ?? '?') . '% common, which is the ordinary drop');

	/*
	 * ONE CACHE A DAY even if the roll is ever changed to something less
	 * stable -- the endpoint runs on every open of the modal, not once.
	 */
	ok(strpos($ep, "source = 'digestcache'") !== false && strpos($ep, 'CURDATE()') !== false,
	   'nothing in the endpoint stops a second cache the same day');
}

echo "\n" . ($fail ? "$fail FAILED\n" : "all good\n");
exit($fail ? 1 : 0);
