<?php
// Discord role add/remove.
//
// API v10 on discord.com. This used to call v6 on discordapp.com -- both
// legacy: Discord retired v6 outright, and discordapp.com is the old
// domain (it still redirects, but a redirect on an authenticated PUT is
// not something to rely on). If roles ever "just stopped working" with
// nothing in the code having changed, that combination is the first thing
// to suspect.
//
// $bot_token is a global, set in credentials/webhooks_credentials.php --
// which is loaded by webhooks.php, which is loaded by verify.php. So any
// page calling this needs verify.php somewhere in its include chain
// (dashboard.php notes exactly this above its own includes). Nothing
// enforces that; a caller without it silently sends an empty token.
// Which is precisely why the failure logging below exists.
function assignRole($discord_id, $role_id, $action="", $guild_id="944002913443938306") {
	global $bot_token;
	$authToken = $bot_token;
	$guildid = $guild_id;
	$userid = $discord_id;
	$roleid = $role_id;
	/* SKULL_DISCORD_API exists so the harness can point this at a local
	   server and drive the real function; production never defines it. Same
	   arrangement as DROPSHIP_DISCORD_API below. */
	$api = defined('SKULL_DISCORD_API') ? SKULL_DISCORD_API : 'https://discord.com';
	$url = $api . "/api/v10/guilds/" . $guildid . "/members/" . $userid . "/roles/" . $roleid;

	$request = "PUT";
	if($action == "delete"){
		$request = "DELETE";
	}

	/*
	 * ONE "UNKNOWN MEMBER" PER GUILD PER REQUEST, NOT SEVEN.
	 *
	 * skulliance.php walks a list of role IDs and calls this once per role
	 * that needs removing -- up to seven for the Ritual guild alone -- on
	 * every dashboard.php and my-nfts.php load. The role list it walks comes
	 * from $_SESSION['userData']['roles'], a snapshot taken at OAuth login
	 * that never refreshes, so for somebody who has since LEFT that guild
	 * every one of those seven calls is made, every one returns 404 Unknown
	 * Member, and the list is still there on the next page load.
	 *
	 * That is both halves of what was being reported: the 404 alerts that
	 * would not stop, and a 429 -- seven authenticated calls to the same
	 * route in a few milliseconds is us rate-limiting ourselves, the same
	 * shape of bug as the avatar refresh burst.
	 *
	 * Once Discord has said a user is not in a guild, that answer holds for
	 * the rest of the request. Six calls become zero.
	 */
	static $notMember = array();
	$seen = $guildid . '|' . $userid;
	if (isset($notMember[$seen])) {
		error_log('assignRole SKIPPED ' . $request . ' guild=' . $guildid
			. ' user=' . $userid . ' role=' . $roleid
			. ' -- already known not to be a member this request');
		return false;
	}

	$ch = curl_init();
	curl_setopt_array($ch, array(
	    CURLOPT_URL            => $url,
	    CURLOPT_HTTPHEADER     => array(
	        'Authorization: Bot '.$authToken,
	        "Content-Length: 0"
	    ),
	    CURLOPT_RETURNTRANSFER => 1,
	    CURLOPT_CUSTOMREQUEST  => $request,
	    CURLOPT_FOLLOWLOCATION => 1,
	    CURLOPT_VERBOSE        => 0,
	    CURLOPT_TIMEOUT        => 8,
	    CURLOPT_SSL_VERIFYPEER => 0
	));
	$response  = curl_exec($ch);
	$http_code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
	$curl_err  = curl_error($ch);
	/* No curl_close(): no effect since PHP 8.0 and deprecated from 8.5, and
	   display_errors is ON, so the day this host moves to 8.5 it would print
	   a deprecation into the middle of a page. Same call dropship/role.php
	   makes, for the same reason. */

	/*
	 * A 429 IS A "WAIT", NOT A FAILURE.
	 *
	 * Discord puts the wait in the body as retry_after, in seconds, and for
	 * this route it is routinely a fraction of one. Reporting the 429 instead
	 * of waiting it out meant the role never came off AND an alert went up,
	 * for a condition that resolves itself in under a second.
	 *
	 * Once only, and only for a short wait: this runs inside a page load, so
	 * a long retry_after is Discord telling us to go away rather than to
	 * hold on, and the right answer then is to give up and say so.
	 */
	if ($http_code === 429) {
		$wait = 0;
		$rl = json_decode((string)$response, true);
		if (is_array($rl) && isset($rl['retry_after'])) $wait = (float)$rl['retry_after'];
		if ($wait > 0 && $wait <= 2) {
			usleep((int)round(($wait + 0.05) * 1000000));
			$ch2 = curl_init();
			curl_setopt_array($ch2, array(
			    CURLOPT_URL            => $url,
			    CURLOPT_HTTPHEADER     => array(
			        'Authorization: Bot '.$authToken,
			        "Content-Length: 0"
			    ),
			    CURLOPT_RETURNTRANSFER => 1,
			    CURLOPT_CUSTOMREQUEST  => $request,
			    CURLOPT_FOLLOWLOCATION => 1,
			    CURLOPT_VERBOSE        => 0,
			    CURLOPT_TIMEOUT        => 8,
			    CURLOPT_SSL_VERIFYPEER => 0
			));
			$response  = curl_exec($ch2);
			$http_code = (int)curl_getinfo($ch2, CURLINFO_HTTP_CODE);
			$curl_err  = curl_error($ch2);
			error_log('assignRole RETRIED after 429 ' . $request . ' guild=' . $guildid
				. ' user=' . $userid . ' role=' . $roleid
				. ' waited=' . $wait . 's http=' . $http_code);
		}
	}

	// Discord answers a successful role change with 204 No Content. Anything
	// else is a real failure and used to be discarded in silence -- the
	// response was read into a variable and simply never looked at, so a
	// dead bot token, a revoked permission or a retired API version all
	// looked identical to success: roles quietly stopped applying while
	// every other Discord integration (notifications, which go through
	// webhooks and don't use this token at all) carried on working
	// perfectly. That combination is genuinely hard to reason about from
	// the outside, so make it say something.
	//
	// Deliberately does NOT log the token or the response body wholesale --
	// status, endpoint and Discord's own message are enough to diagnose,
	// and the IDs here are not secrets.
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
			'assignRole FAILED ' . $request . ' http=' . $http_code
			. ' guild=' . $guildid . ' user=' . $userid . ' role=' . $roleid
			. ' token=' . (empty($authToken) ? 'MISSING' : 'present')
			. $detail
		);

		// A log line only helps someone already looking at the log, and the
		// whole problem with this failure is that nothing announced it --
		// roles quietly stop applying while notifications keep flowing, so
		// the platform looks healthy from every angle you'd normally check.
		// Ping Discord too.
		//
		// function_exists() rather than an include: assignRole() depends on
		// webhooks.php having been loaded anyway (that's where $bot_token
		// comes from), but a caller that skipped it should get a failed role
		// assignment, not a fatal on the alerting path. Degrades to the
		// error_log above.
		//
		// Throttled on status + Discord's error code, so a token that dies
		// mid-week alerts once rather than on every page load, while a
		// different failure appearing later still gets through immediately.
		/*
		 * UNKNOWN MEMBER IS NOT AN ALERT.
		 *
		 * 10007 means that person is not in that guild -- they left, or never
		 * joined it. There is nothing for an admin to go and fix, and because
		 * the session's role list is a login-time snapshot the same call is
		 * attempted on every dashboard load, so the 15-minute throttle just
		 * meant a fresh copy of an unfixable problem four times a day,
		 * indefinitely, for as long as that person keeps visiting.
		 *
		 * Logged, not announced. 10013 (Unknown User) is the same story.
		 *
		 * This deliberately does NOT silence the neighbouring cases that look
		 * similar and are real: 10004 Unknown Guild means the bot is no longer
		 * in the server, 10011 Unknown Role means the role ID is gone, and
		 * both of those break role assignment for EVERYONE and still alert.
		 */
		$discord_code = 0;
		if (isset($decoded['code'])) $discord_code = $decoded['code'];
		if ($discord_code == 10007 || $discord_code == 10013) {
			$notMember[$seen] = true;
			return false;
		}

		if (function_exists('alertAdmin')) {

			// Most of the value is here: turn the three failures that
			// actually happen into the thing to go fix, because
			// "50013 Missing Permissions" does not on its own tell you the
			// bot's role is sitting below the role it's trying to grant.
			/* The default used to be "check the bot token", which is the right
			   answer for exactly one status and a wrong, expensive one for
			   every other -- a 429 told you to go and rotate a token that was
			   working perfectly. Say what is actually known instead. */
			$hint = 'Unrecognised failure. The status and Discord\'s own message above are the place to start.';
			if (empty($authToken)) {
				$hint = 'The bot token was EMPTY for this call -- the calling page most likely never included verify.php, so nothing set $bot_token.';
			} else if ($http_code == 401) {
				$hint = 'Discord rejected the bot token (401). It was probably reset -- update credentials/webhooks_credentials.php.';
			} else if ($discord_code == 50013) {
				$hint = 'Missing Permissions. The bot needs Manage Roles AND its own role must sit ABOVE the role it is granting in Server Settings > Roles.';
			} else if ($discord_code == 10011) {
				$hint = 'Unknown Role -- the role ID no longer exists in this guild.';
			} else if ($http_code == 403) {
				$hint = 'Discord refused the call (403). Either the bot lacks Manage Roles or its own role sits below the one it is changing.';
			} else if ($http_code == 429) {
				$hint = 'RATE LIMITED, and the wait was too long to sit out (or the retry failed too). Nothing is wrong with the token. '
				      . 'This is us making too many role calls at once -- skulliance.php removes roles one call at a time, so a user '
				      . 'shedding several roles at once bursts the route.';
			} else if ($discord_code == 10004) {
				$hint = 'Unknown Guild -- the bot is not in that server any more, so NO role changes are working there.';
			}

			alertAdmin(
				'Discord role assignment failed',
				"**" . $request . "** returned **HTTP " . $http_code . "**" . $detail . "\n\n"
					. "guild `" . $guildid . "` · user `" . $userid . "` · role `" . $roleid . "`\n\n"
					. $hint . "\n\n"
					. "_Further identical failures are suppressed for 15 minutes._",
				'assignRole|' . $http_code . '|' . $discord_code
			);
		}
	}
	return $ok;
}
?>
