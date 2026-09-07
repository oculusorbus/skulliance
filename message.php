<?php
// Direct Discord API calls as the bot (DMs, channel messages) -- NOT the
// same path as discordmsg() in webhooks.php, which posts to webhook URLs
// and doesn't touch $bot_token at all. Worth knowing when something looks
// half-broken: notifications can be flowing perfectly while everything
// here is dead, because only this side depends on the token.
//
// v10; was v9. See /role.php for the full note on the version bump.
function MakeRequest($endpoint, $data) {
	global $bot_token;
    # Set endpoint
    $url = "https://discord.com/api/v10/".$endpoint."";

    # Encode data, as Discord requires you to send json data.
    $data = json_encode($data);

    # Initialize new curl request
    $ch = curl_init();
    $f = fopen('request.txt', 'w');

    # Set headers, data etc..
    curl_setopt_array($ch, array(
        CURLOPT_URL            => $url, 
        CURLOPT_HTTPHEADER     => array(
            'Authorization: Bot '.$bot_token,
            "Content-Type: application/json",
            "Accept: application/json"
        ),
        CURLOPT_RETURNTRANSFER => 1,
        CURLOPT_FOLLOWLOCATION => 1,
        CURLOPT_VERBOSE        => 1,
        CURLOPT_SSL_VERIFYPEER => 0,
        CURLOPT_POSTFIELDS => $data,
        CURLOPT_STDERR         => $f,
    ));
    $request   = curl_exec($ch);
    $http_code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_err  = curl_error($ch);
    curl_close($ch);

    // Same silent-failure problem /role.php had: the response was decoded
    // and handed back, but nobody checks it, so a dead token or a retired
    // API version just produced a null return and no sign anything went
    // wrong. sendDM() below already guards on the decoded result, so a
    // failure here degrades quietly by design -- log it so "quietly" isn't
    // also "invisibly".
    if ($http_code < 200 || $http_code >= 300) {
        $detail  = '';
        $decoded = json_decode((string)$request, true);
        if (is_array($decoded) && isset($decoded['message'])) {
            $detail = ' discord="' . $decoded['message'] . '"';
            if (isset($decoded['code'])) $detail .= ' code=' . $decoded['code'];
        }
        if ($curl_err !== '') $detail .= ' curl="' . $curl_err . '"';
        error_log(
            'Discord MakeRequest FAILED http=' . $http_code
            . ' endpoint=' . $endpoint
            . ' token=' . (empty($bot_token) ? 'MISSING' : 'present')
            . $detail
        );
    }

    return json_decode($request, true);
}

function sendDM($discord_id, $message, $image_url = ''){
    $newDM = MakeRequest('/users/@me/channels', array("recipient_id" => $discord_id));
    if(isset($newDM["id"])) {
        $payload = array("content" => $message);
        if ($image_url !== '') {
            $payload["embeds"] = array(array("image" => array("url" => $image_url)));
        }
        MakeRequest("/channels/".$newDM["id"]."/messages", $payload);
    }
}
?>