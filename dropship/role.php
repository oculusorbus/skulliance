<?php
// Drop Ship's own copy of assignRole -- separate from the platform's
// /role.php because it targets Drop Ship's own guild and only ever ADDS a
// role (no delete path). Same v6-on-discordapp.com -> v10-on-discord.com
// upgrade and the same failure logging; see /role.php's comments for the
// full reasoning on both.
/*
 * The API base, overridable so this file can be tested.
 *
 * A function whose whole job is one HTTP call is otherwise only checkable
 * by reading it, and reading is how the multipart bug in webhooks.php got
 * past me earlier today. Production never defines this.
 */
if (!defined('DROPSHIP_DISCORD_API')) define('DROPSHIP_DISCORD_API', 'https://discord.com/api/v10');

/** The one guild Drop Ship's VIP role lives in -- see assignRole() below. */
if (!defined('DROPSHIP_GUILD_ID')) define('DROPSHIP_GUILD_ID', '966397496978964500');

/**
 * Does this member hold this role RIGHT NOW?
 *
 * TRUE / FALSE / NULL, and the null matters: it means Discord could not be
 * asked, which is NOT the same as "no". Callers may only ever use this to
 * GRANT access, so an outage or a rate limit can never lock out a player
 * the login snapshot already approved.
 *
 * Why this exists: $_SESSION['userData']['roles'] is filled once, by
 * process-oauth.php, from four sequential Discord calls whose failures are
 * silently ignored -- so a role granted after sign-in, or one lost to a
 * single rate-limited call, simply does not exist to the game until the
 * player happens to log out and back in. Confirmed from a real account:
 * holding the role and the token, refused, and a re-login fixed it.
 */
function dropshipMemberHasRole($discord_id, $role_id) {
	global $bot_token;
	if (empty($bot_token) || $discord_id === '' || $discord_id === null) return null;

	$url = DROPSHIP_DISCORD_API . '/guilds/' . DROPSHIP_GUILD_ID
	     . '/members/' . rawurlencode((string)$discord_id);
	$ch = curl_init();
	curl_setopt_array($ch, array(
		CURLOPT_URL            => $url,
		CURLOPT_HTTPHEADER     => array('Authorization: Bot ' . $bot_token),
		CURLOPT_RETURNTRANSFER => 1,
		CURLOPT_TIMEOUT        => 6,
		CURLOPT_SSL_VERIFYPEER => 0,
	));
	$response  = curl_exec($ch);
	$http_code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
	$curl_err  = curl_error($ch);
	/* No curl_close(): no effect since PHP 8.0, deprecated from 8.5, and
	   this platform runs display_errors ON -- a notice from here prints
	   into the page. The handle frees when it goes out of scope. */

	/* Not a member of the guild at all. That is a real answer, not a
	   failure, so it is false rather than null. */
	if ($http_code === 404) return false;
	if ($http_code < 200 || $http_code >= 300) {
		error_log('dropshipMemberHasRole FAILED http=' . $http_code
		        . ' user=' . $discord_id . ' role=' . $role_id
		        . ' token=' . (empty($bot_token) ? 'MISSING' : 'present')
		        . ($curl_err !== '' ? ' curl="' . $curl_err . '"' : ''));
		return null;
	}
	$data = json_decode((string)$response, true);
	if (!is_array($data) || !isset($data['roles']) || !is_array($data['roles'])) return null;
	foreach ($data['roles'] as $r) {
		if ((string)$r === (string)$role_id) return true;
	}
	return false;
}

function assignRole($discord_id, $role_id) {
	global $bot_token;
	$authToken = $bot_token;
	$guildid = DROPSHIP_GUILD_ID;
	$userid = $discord_id;
	$roleid = $role_id;
	$url = "https://discord.com/api/v10/guilds/" . $guildid . "/members/" . $userid . "/roles/" . $roleid;

	$ch = curl_init();
	curl_setopt_array($ch, array(
	    CURLOPT_URL            => $url,
	    CURLOPT_HTTPHEADER     => array(
	        'Authorization: Bot '.$authToken,
	        "Content-Length: 0"
	    ),
	    CURLOPT_RETURNTRANSFER => 1,
	    CURLOPT_CUSTOMREQUEST  => "PUT",
	    CURLOPT_FOLLOWLOCATION => 1,
	    CURLOPT_VERBOSE        => 0,
	    CURLOPT_TIMEOUT        => 8,
	    CURLOPT_SSL_VERIFYPEER => 0
	));
	$response  = curl_exec($ch);
	$http_code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
	$curl_err  = curl_error($ch);
	curl_close($ch);

	$ok = ($http_code >= 200 && $http_code < 300);
	if (!$ok) {
		$detail = '';
		$decoded = json_decode((string)$response, true);
		if (is_array($decoded) && isset($decoded['message'])) {
			$detail = ' discord="' . $decoded['message'] . '"';
			if (isset($decoded['code'])) $detail .= ' code=' . $decoded['code'];
		}
		if ($curl_err !== '') $detail .= ' curl="' . $curl_err . '"';
		error_log(
			'dropship assignRole FAILED PUT http=' . $http_code
			. ' guild=' . $guildid . ' user=' . $userid . ' role=' . $roleid
			. ' token=' . (empty($authToken) ? 'MISSING' : 'present')
			. $detail
		);
	}
	return $ok;
}
?>
