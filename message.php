<?php
function MakeRequest($endpoint, $data) {
	global $client_secret;
    # Set endpoint
    $url = "https://discord.com/api/".$endpoint."";

    # Encode data, as Discord requires you to send json data.
    $data = json_encode($data);

    # Initialize new curl request
    $ch = curl_init();
    $f = fopen('request.txt', 'w');

    # Set headers, data etc..
    curl_setopt_array($ch, array(
        CURLOPT_URL            => $url, 
        CURLOPT_HTTPHEADER     => array(
            'Authorization: bot '.$client_secret,
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

    $request = curl_exec($ch);
    print_r($request);
    curl_close($ch);
    return json_decode($request, true);
}
?>