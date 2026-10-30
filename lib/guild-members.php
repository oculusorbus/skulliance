<?php
/**
 * lib/guild-members.php — who is still in the Discord server.
 *
 * WHY THIS EXISTS. A personal invite to somebody who has left the
 * server cannot be delivered at all: Discord answers "Send a friend
 * request first and start the conversation once they've accepted",
 * because you no longer share a guild and are not friends. There is
 * no way around that from our side, and every attempt is a wasted
 * action against an account that Discord is also watching for bulk
 * DM patterns. So the list is checked BEFORE the message is written.
 *
 * ONE BULK CALL, CACHED TO A FILE, NOT A LOOKUP PER ROW. The lapsed
 * panel is hundreds of rows; a per-member API call per page load
 * would be hundreds of requests against a rate-limited route every
 * time the page is opened. GET /guilds/{id}/members?limit=1000 pages
 * through the whole roster in one or two requests, and the answer
 * changes slowly enough that a file is the right store.
 *
 * IT NEEDS THE SERVER MEMBERS INTENT. The list endpoint is gated on
 * the GUILD_MEMBERS privileged intent, toggled per application in the
 * Discord developer portal. Without it Discord answers 403, which is
 * reported as exactly that rather than as a generic failure -- it is
 * a switch somebody has to flip, not a bug to debug.
 *
 * NOT IN THE CACHE IS NOT THE SAME AS LEFT. A cache that has never
 * been built, or one that failed halfway, must not render three
 * hundred members as "left the server" -- that is the reading that
 * would stop the campaign dead. The absence of a cache is reported as
 * unknown, everywhere.
 */

/** The Ritual guild, same default role.php has carried for years. */
if (!defined('GUILD_MEMBERS_GUILD')) define('GUILD_MEMBERS_GUILD', '944002913443938306');
/** Older than this and the panel says so. Membership drifts slowly. */
if (!defined('GUILD_MEMBERS_STALE_H')) define('GUILD_MEMBERS_STALE_H', 24);
/** Pages of 1000. A guard, not a limit anybody should reach. */
if (!defined('GUILD_MEMBERS_MAX_PAGES')) define('GUILD_MEMBERS_MAX_PAGES', 25);

function guild_members_path() {
	/* dhcrenders/ is the platform's gitignored cache directory -- it is
	   where dhc-ranks.php keeps rankpool-*.json. */
	return __DIR__ . '/../dhcrenders/guild-members.json';
}

/**
 * The cached roster, or null when there is not one.
 *
 * Returns array('ids' => array(discord_id => true), 'at' => unix,
 * 'count' => int). Null means unknown, which callers must not render
 * as "left".
 */
function guild_members_load() {
	$p = guild_members_path();
	if (!is_file($p)) return null;
	$raw = @file_get_contents($p);
	if ($raw === false || $raw === '') return null;
	$d = json_decode($raw, true);
	if (!is_array($d) || empty($d['ids']) || !is_array($d['ids'])) return null;
	return array(
		'ids'   => $d['ids'],
		'at'    => isset($d['at']) ? (int)$d['at'] : 0,
		'count' => isset($d['count']) ? (int)$d['count'] : count($d['ids']),
	);
}

/** Is this roster old enough to mention? */
function guild_members_stale($cache) {
	if (!$cache || empty($cache['at'])) return true;
	return (time() - (int)$cache['at']) > GUILD_MEMBERS_STALE_H * 3600;
}

/**
 * Is this person still in the server?
 *
 * Returns true, false, or null for "we do not know" -- the third
 * answer is the whole reason this does not return a bool.
 */
/**
 * The role ids this member holds, or null when the roster cannot say.
 *
 * NULL IS NOT AN EMPTY ROLE LIST, and the difference decides whether a
 * page may draw a conclusion. A cache written before roles were captured
 * knows the member is present and nothing about their roles; reading
 * that as "holds no roles" would put every long-standing member into
 * whichever bucket means "brand new and stuck".
 */
function guild_member_roles($cache, $discord_id) {
	$id = preg_replace('/[^0-9]/', '', (string)$discord_id);
	if ($id === '' || !isset($cache['roles']) || !is_array($cache['roles'])) return null;
	return isset($cache['roles'][$id]) ? $cache['roles'][$id] : null;
}

/** Their display name as Discord knows it, or '' -- the platform may not have one. */
function guild_member_name($cache, $discord_id) {
	$id = preg_replace('/[^0-9]/', '', (string)$discord_id);
	if ($id === '' || !isset($cache['names'][$id])) return '';
	return (string)$cache['names'][$id];
}

function guild_member_in($cache, $discord_id) {
	if (!$cache) return null;
	$id = preg_replace('/[^0-9]/', '', (string)$discord_id);
	if ($id === '') return null;
	return isset($cache['ids'][$id]);
}

/**
 * Re-read the roster from Discord and write the cache.
 *
 * Returns array('ok' => bool, 'count' => int, 'error' => string).
 * WRITES NOTHING ON FAILURE: a partial roster would mark everybody
 * after the failure point as having left.
 */
function guild_members_refresh($bot_token, $guild_id = null) {
	$out = array('ok' => false, 'count' => 0, 'error' => '');
	$token = trim((string)$bot_token);
	if ($token === '') { $out['error'] = 'No bot token is loaded on this page.'; return $out; }
	if (!function_exists('curl_init')) { $out['error'] = 'curl is not available.'; return $out; }

	$guild = preg_replace('/[^0-9]/', '', (string)($guild_id === null ? GUILD_MEMBERS_GUILD : $guild_id));
	if ($guild === '') { $out['error'] = 'No guild id.'; return $out; }
	/* Same override role.php uses, so a harness can drive this against a
	   local server instead of Discord. Production never defines it. */
	$api = defined('SKULL_DISCORD_API') ? SKULL_DISCORD_API : 'https://discord.com';

	$ids = array();

	$roles = $names = array(); $after = '0'; $pages = 0;
	while ($pages < GUILD_MEMBERS_MAX_PAGES) {
		$pages++;
		$url = $api . '/api/v10/guilds/' . $guild . '/members?limit=1000&after=' . rawurlencode($after);
		$ch = curl_init($url);
		curl_setopt_array($ch, array(
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_TIMEOUT        => 20,
			CURLOPT_HTTPHEADER     => array('Authorization: Bot ' . $token),
		));
		$body = curl_exec($ch);
		$code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
		$cerr = curl_error($ch);
		curl_close($ch);

		if ($body === false) { $out['error'] = 'Request failed: ' . $cerr; return $out; }
		if ($code === 403) {
			/* The one failure with a specific fix, so it gets a specific
			   message rather than "403". */
			$out['error'] = 'Discord refused the member list (403). The bot needs the '
			              . 'Server Members Intent enabled in the Discord developer portal.';
			return $out;
		}
		if ($code === 429) { $out['error'] = 'Rate limited by Discord. Try again shortly.'; return $out; }
		if ($code !== 200) { $out['error'] = 'Discord returned HTTP ' . $code . '.'; return $out; }

		$rows = json_decode($body, true);
		if (!is_array($rows)) { $out['error'] = 'Could not read the member list.'; return $out; }
		if (!$rows) break;                       // last page

		$lastId = '';
		foreach ($rows as $m) {
			if (empty($m['user']['id'])) continue;
			$uid = (string)$m['user']['id'];
			$ids[$uid] = true;
			/*
			 * ROLES AND NAMES COME FREE. They are already in this response
			 * and were being thrown away, so capturing them costs no extra
			 * call -- and without them "is this person a Visitor or a
			 * Candidate" needs one request per member.
			 *
			 * Stored alongside rather than inside $ids, so guild_member_in()
			 * and every existing caller are untouched by the change and an
			 * older cache file still answers the question it was written to
			 * answer.
			 */
			$roles[$uid] = isset($m['roles']) && is_array($m['roles'])
				? array_values(array_map('strval', $m['roles'])) : array();
			$nm = '';
			if (isset($m['user']['global_name']) && $m['user']['global_name'] !== '') {
				$nm = (string)$m['user']['global_name'];
			} elseif (isset($m['user']['username'])) {
				$nm = (string)$m['user']['username'];
			}
			if ($nm !== '') $names[$uid] = $nm;
			$lastId = $uid;
		}
		if (count($rows) < 1000) break;          // short page means the end
		if ($lastId === '') break;               // nothing to page from; stop rather than loop
		$after = $lastId;
	}

	if (!$ids) { $out['error'] = 'Discord returned no members.'; return $out; }

	$payload = json_encode(array('ids' => $ids, 'roles' => $roles, 'names' => $names,
	                             'at' => time(), 'count' => count($ids)));
	$path = guild_members_path();
	$dir  = dirname($path);
	if (!is_dir($dir) && !@mkdir($dir, 0775, true)) { $out['error'] = 'Cache directory is missing.'; return $out; }
	/* Written to a temporary name and renamed: a reader must never see
	   half a roster and conclude everybody left. */
	$tmp = $path . '.' . getmypid() . '.tmp';
	if (@file_put_contents($tmp, $payload) === false) { $out['error'] = 'Could not write the cache.'; return $out; }
	if (!@rename($tmp, $path)) { @unlink($tmp); $out['error'] = 'Could not replace the cache.'; return $out; }

	$out['ok'] = true;
	$out['count'] = count($ids);
	return $out;
}
