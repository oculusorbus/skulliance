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
	$url = "https://discord.com/api/v10/guilds/" . $guildid . "/members/" . $userid . "/roles/" . $roleid;

	$request = "PUT";
	if($action == "delete"){
		$request = "DELETE";
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
	curl_close($ch);

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
	}
	return $ok;
}
?>
