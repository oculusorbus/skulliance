<?php
/**
 * avatar-lib.php -- one definition of a Discord avatar URL, and the refresh
 * that heals a stale one.
 *
 * THE PROBLEM. users.avatar holds a Discord avatar HASH, written only at login
 * (db.php's insert and update). Change your avatar on Discord and the hash we
 * hold is dead: the URL is still well-formed, the CDN returns 404, and the
 * page shows a broken image. Players on a long session never log in again, so
 * theirs stays broken indefinitely -- and it is the ACTIVE players who suffer,
 * because a dormant one at least reads as dormant.
 *
 * WHY A FALLBACK ALONE IS NOT ENOUGH, and why there are 101 hand-built copies
 * of this URL across 20 files: every one of them is a ternary that falls back
 * to the placeholder when there is NO hash stored. None of them can catch a
 * hash that is stored and wrong, because that produces a perfectly valid URL.
 * The fallback fires on the wrong condition.
 *
 * THE CASCADE, which is the owner's design and is the right shape:
 *
 *   1. render the stored hash -- free, and correct for almost everybody
 *   2. if it 404s, ask Discord for the current one and WRITE IT BACK -- costs
 *      one API call, once, for exactly the users who are broken
 *   3. if that fails too, the placeholder -- the account is gone or has no
 *      avatar, and no amount of asking will change it
 *
 * The expense lands only where there is something to fix, and it is permanent:
 * the next viewer reads the healed row. A nightly sweep would re-check
 * thousands of working avatars to find the handful that moved, and would still
 * leave a day's window where the image is broken.
 *
 * The bot token is the thing that makes step 2 possible at all. GET /users/{id}
 * needs no consent from the user and no session -- the ID is enough.
 *
 * RUN ONCE against the live database:
 *
 *     ALTER TABLE users
 *       ADD COLUMN avatar_checked_at DATETIME DEFAULT NULL AFTER avatar;
 *
 * Only the failure path reads it. A refresh that succeeds writes a working
 * hash and that user never 404s again; a deleted account writes '' and stops
 * 404ing too. The column exists to stop us re-asking when we could NOT find
 * out -- rate limit, network -- which is the one case that would otherwise
 * retry on every page view.
 *
 * Until the column exists the lookup in ajax/avatar-refresh.php fails, every
 * avatar falls back to the placeholder, and the endpoint logs why. Degraded,
 * not broken -- but it is the whole feature doing nothing, so check the log
 * before concluding the cascade does not work.
 */

/*
 * THE BOT TOKEN HAS TO BE LOADED HERE, and forgetting it is why the first
 * version of this never once reached Discord. $bot_token is defined in
 * credentials/webhooks_credentials.php, which is included by webhooks.php and
 * by nothing else -- so an endpoint that pulls in db.php and this file has no
 * token, avatar_fetch_hash() returns null on its first line, and every avatar
 * quietly falls to the placeholder. Identical, from the outside, to "all these
 * users really did delete their accounts".
 *
 * include_once so this is harmless when webhooks.php got there first, and
 * guarded because the credentials directory is server-only and absent from
 * every checkout.
 */
if (!isset($bot_token) && is_file(__DIR__ . '/credentials/webhooks_credentials.php')) {
	include_once __DIR__ . '/credentials/webhooks_credentials.php';
}

/** Is there a token at all? A configuration fault, not a transient failure. */
function avatar_can_fetch() {
	global $bot_token;
	return !empty($bot_token) && function_exists('curl_init');
}

/** Discord's CDN, the one place that knows how an avatar URL is spelled. */
define('AVATAR_CDN', 'https://cdn.discordapp.com/avatars/');

/** What we show when there is no avatar to show. */
define('AVATAR_PLACEHOLDER', 'icons/skull.png');

/**
 * The URL for a stored avatar hash, or the placeholder.
 *
 * ANIMATED AVATARS ARE WHY THIS TAKES THE EXTENSION FROM THE HASH. Discord
 * prefixes an animated avatar's hash with "a_", and that one is only served as
 * .gif -- asking for .png returns 404. Several of the 101 copies hardcode
 * .png and a few .jpg, so an animated avatar is broken on some pages and fine
 * on others depending on which file drew it.
 */
function avatar_url($discord_id, $hash, $size = 128) {
	$discord_id = trim((string)$discord_id);
	$hash       = trim((string)$hash);
	if ($discord_id === '' || $hash === '') return AVATAR_PLACEHOLDER;
	/* Only the characters Discord actually uses, so a malformed row cannot
	   build a URL pointing somewhere else. */
	if (!preg_match('/^[0-9]{5,25}$/', $discord_id)) return AVATAR_PLACEHOLDER;
	if (!preg_match('/^[A-Za-z0-9_]{8,64}$/', $hash))  return AVATAR_PLACEHOLDER;
	$ext  = (strpos($hash, 'a_') === 0) ? 'gif' : 'png';
	$size = in_array((int)$size, array(16,32,64,128,256,512,1024), true) ? (int)$size : 128;
	return AVATAR_CDN . $discord_id . '/' . $hash . '.' . $ext . '?size=' . $size;
}

/**
 * Ask Discord for a user's CURRENT avatar hash and write it back.
 *
 * Returns the new hash, '' when the account has no avatar or is gone, or null
 * when we could not find out (network, rate limit, no token) -- which is NOT
 * the same thing and must not be cached as an answer.
 *
 * THE ID MUST ALREADY BE ONE OF OURS. Checked by the caller against the users
 * table before we get here: without that this is an open proxy that will look
 * up any Discord account on earth on behalf of anyone who can reach the
 * endpoint, using our bot's credentials and our rate limit.
 */
function avatar_fetch_hash($discord_id) {
	global $bot_token;
	if (!avatar_can_fetch()) return null;
	if (!preg_match('/^[0-9]{5,25}$/', (string)$discord_id)) return null;

	$ch = curl_init('https://discord.com/api/v10/users/' . $discord_id);
	curl_setopt_array($ch, array(
		CURLOPT_HTTPHEADER     => array('Authorization: Bot ' . $bot_token),
		CURLOPT_RETURNTRANSFER => true,
		/* Short, because a page is waiting on this. A slow answer is worth
		   less than the placeholder arriving promptly. */
		CURLOPT_TIMEOUT        => 6,
		CURLOPT_CONNECTTIMEOUT => 3,
	));
	$body = curl_exec($ch);
	$code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
	curl_close($ch);

	/* 404 is an ANSWER: the account no longer exists, so stop asking. 429 and
	   5xx are not -- they are us being told to come back later, and recording
	   them as "no avatar" would make a rate limit look like a deleted user. */
	if ($code === 404) return '';
	if ($code !== 200 || $body === false) return null;

	$j = json_decode($body, true);
	if (!is_array($j)) return null;
	/* A user with no custom avatar has avatar: null. That is an answer too. */
	return isset($j['avatar']) && $j['avatar'] !== null ? (string)$j['avatar'] : '';
}
