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
	require_once __DIR__ . '/dhcfighters-config.php';
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

echo "\n" . ($fail ? "$fail FAILED\n" : "all good\n");
exit($fail ? 1 : 0);
