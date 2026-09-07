<?php
// Drop Ship's own copy of assignRole -- separate from the platform's
// /role.php because it targets Drop Ship's own guild and only ever ADDS a
// role (no delete path). Same v6-on-discordapp.com -> v10-on-discord.com
// upgrade and the same failure logging; see /role.php's comments for the
// full reasoning on both.
function assignRole($discord_id, $role_id) {
	global $bot_token;
	$authToken = $bot_token;
	$guildid = "966397496978964500";
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
