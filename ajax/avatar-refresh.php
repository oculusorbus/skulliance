<?php
/**
 * ajax/avatar-refresh.php -- step 2 of the avatar cascade.
 *
 * The browser renders the stored avatar. If it 404s, one delegated listener
 * (header.php) posts the Discord id here. We ask Discord for the current hash,
 * write it back, and hand the browser a working URL -- or tell it to use the
 * placeholder. See avatar-lib.php for why the cascade is shaped this way.
 *
 * THE ID MUST ALREADY BE ONE OF OURS, and this is the check that makes the
 * endpoint safe to leave unauthenticated. Without it this is an open proxy
 * that resolves any Discord account on earth, for anyone who can reach the
 * URL, using our bot's credentials and our rate limit. With it, the worst an
 * attacker can do is make us re-check users we already know about -- and the
 * throttle below bounds even that.
 *
 * UNAUTHENTICATED ON PURPOSE. The Collection, the leaderboards and the
 * galleries are public and show avatars; gating this behind a session would
 * leave every signed-out visitor looking at broken images, which is the thing
 * being fixed.
 *
 * IT HEALS ITSELF OUT OF EXISTENCE. A successful refresh writes a working hash,
 * so that user never 404s again and never reaches this endpoint again. A
 * deleted account writes '' , so the stored URL becomes the placeholder and it
 * stops 404ing too. The throttle therefore only has to cover the case where we
 * could not find out -- rate limit, network -- where repeating immediately
 * would be the wrong move anyway.
 */
include '../db.php';
require_once __DIR__ . '/../avatar-lib.php';
require_once __DIR__ . '/../dhc-json.php';

/* Minutes before the same id may be asked about again, and only when the last
   attempt failed to produce an answer. A success or a definitive "no avatar"
   stops the 404 that brings us here at all. */
define('AVATAR_RECHECK_MINUTES', 60);

$did = isset($_POST['discord_id']) ? trim((string)$_POST['discord_id']) : '';
if (!preg_match('/^[0-9]{5,25}$/', $did)) {
	dhc_json(array('ok' => false, 'url' => null, 'why' => 'bad id'));
	exit;
}

$esc = $conn->real_escape_string($did);
$res = $conn->query("SELECT id, avatar, avatar_checked_at,
                            (avatar_checked_at IS NOT NULL
                             AND avatar_checked_at > NOW() - INTERVAL "
                          . AVATAR_RECHECK_MINUTES . " MINUTE) AS too_soon
                     FROM users WHERE discord_id = '$esc' LIMIT 1");
if (!$res) {
	/* Almost certainly the one-time ALTER has not run -- see avatar-lib.php.
	   Silent degradation here looks exactly like "the feature does not work",
	   so say so once per occurrence rather than leaving it to be guessed. */
	error_log('avatar-refresh: user lookup failed (has avatar_checked_at been added?) -- '
	          . $conn->error);
	dhc_json(array('ok' => false, 'url' => null, 'why' => 'unavailable'));
	exit;
}
$row = $res->fetch_assoc();
if (!$row) {
	/* Not one of ours. Same answer as a bad id -- deliberately indistinguishable,
	   so this cannot be used to test whether a Discord account plays here. */
	dhc_json(array('ok' => false, 'url' => null, 'why' => 'bad id'));
	exit;
}

if (!empty($row['too_soon'])) {
	/* Asked recently and still broken. Hand back whatever we hold -- which is
	   the placeholder if the last answer was "no avatar" -- without spending
	   another call. */
	dhc_json(array('ok' => true, 'url' => avatar_url($did, $row['avatar']), 'cached' => true));
	exit;
}

/*
 * NO TOKEN IS A DIFFERENT THING FROM A FAILED CALL, and must not be stamped.
 * Stamping it starts an hour's backoff from a fault that will still be there
 * in an hour, and quietly converts a misconfiguration into "these users have
 * no avatar". This is exactly how the first version of this endpoint failed:
 * it had no token, returned null, stamped, and looked like it was working.
 */
if (!avatar_can_fetch()) {
	error_log('avatar-refresh: no $bot_token -- is credentials/webhooks_credentials.php present?');
	dhc_json(array('ok' => false, 'url' => null, 'why' => 'not configured'));
	exit;
}

$hash = avatar_fetch_hash($did);

if ($hash === null) {
	global $avatar_last_error;
	$why = isset($avatar_last_error) && $avatar_last_error !== '' ? $avatar_last_error : 'unknown';
	error_log('avatar-refresh: ' . $did . ' could not be resolved -- ' . $why);
	/* Could not find out. Stamp the attempt so a page full of broken avatars
	   does not retry in a loop, but do NOT touch the stored hash: overwriting
	   a good-but-stale hash with nothing because Discord was busy would make a
	   rate limit permanent. */
	$conn->query("UPDATE users SET avatar_checked_at = NOW() WHERE id = " . (int)$row['id']);
	/* The upstream status is reported, not hidden: a stuck avatar is otherwise
	   indistinguishable from a deleted account, and the backoff below means a
	   live reproduction is hard to catch. It reveals only that we ask Discord,
	   which is not a secret. */
	dhc_json(array('ok' => false, 'url' => null, 'why' => 'unavailable', 'upstream' => $why));
	exit;
}

/* '' is a real answer: no custom avatar, or the account is gone. Stored, so the
   URL builder returns the placeholder from now on and nothing 404s again. */
$conn->query("UPDATE users SET avatar = '" . $conn->real_escape_string($hash) . "',
                               avatar_checked_at = NOW()
              WHERE id = " . (int)$row['id']);

dhc_json(array('ok' => true,
               'url' => $hash === '' ? null : avatar_url($did, $hash),
               'changed' => $hash !== (string)$row['avatar']));
