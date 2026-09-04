<?PHP
include('credentials/webhooks_credentials.php');
//
//-- https://gist.github.com/Mo45/cb0813cb8a6ebcd6524f6a36d4f8862c
//
    function discordmsg($title, $description, $imageurl, $project_id, $url="") {
		global $prefix;
		// Delay execution by 1 minute to allow the player to finish their game before sending results to Discord
		if($url == ""){
			// dashboard.php, not index.php -- index.php is just a redirect
			// into Skulliance's login now, a dead end as a Discord embed link.
			$url = "https://skulliance.io/staking/dropship/dashboard.php";
		}
		$webhook = getWebhook($project_id);
	    $timestamp = date("c", strtotime("now"));
	    $msg = json_encode([
	    // Message
	    //"content" => "",

	    // Username
	    "username" => "Kill Bot",

	    // Avatar URL.
	    // Uncomment to use custom avatar instead of bot's pic
	    "avatar_url" => "https://cdn.discordapp.com/app-icons/983993436694794261/8c3b958cac5369b56486c326d8c3e5d1.png?size=256",

	    // text-to-speech
	    "tts" => false,

	    // file_upload
	    // "file" => "",

	    // Embeds Array
	    "embeds" => [
		        [
		            // Title
		            "title" => $title,

		            // Embed Type, do not change.
		            "type" => "rich",

		            // Description
		            "description" => $description,

		            // Link in title
		            "url" => $url,

		            // Timestamp, only ISO8601
		            "timestamp" => $timestamp,

		            // Left border color, in HEX
		            "color" => hexdec( "000000" ),

		            // Footer text
					/*
		            "footer" => [
		                "text" => "Drop Ship",
		                "icon_url" => "https://skulliance.io/staking/dropship/images/vip.gif"
		            ],*/

		            // Embed image
		            "image" => [
		                "url" => $imageurl
		            ],

		            // thumbnail
		            "thumbnail" => [
		                "url" => "https://skulliance.io".$prefix."images/vip.gif"
		            ],

		            // Author name & url
					/*
		            "author" => [
		                "name" => "Kill Bot",
		                "url" => "https://skulliance.io/staking/dropship"
		            ],*/

		            // Custom fields
					/*
		            "fields" => [
		                // Field 1
		                [
		                    "name" => "Field #1",
		                    "value" => "Value #1",
		                    "inline" => false
		                ],
		                // Field 2
		                [
		                    "name" => "Field #2",
		                    "value" => "Value #2",
		                    "inline" => true
		                ]
		                // etc
		            ]*/
		        ]
		    ]
		], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );

		// Mirrored: the existing project webhook (e.g. Ohh Meed's own
		// server for Drop Ship/Dread City -- unchanged, that's where the
		// actual playerbase is) PLUS Skulliance's own dedicated channel for
		// THIS project specifically, per the user's decision to keep both
		// rather than switch over to just one. Skulliance has a separate
		// channel (and credential function) per reskin, not one shared
		// channel -- getSkullianceWebhook() was the first one added, since
		// renamed/split into getSkullianceDropShipWebhook() and
		// getSkullianceOculusLoungeWebhook(). Dread City/Filthy Mermaid
		// (project 2/3) have no Skulliance channel yet -- still a work in
		// progress -- so they get no mirror until one exists.
		// function_exists guard the same way Skulliance's own discordmsg()
		// guards a brand-new channel with no credential yet -- an
		// undefined-function call would fatal every discordmsg() call, not
		// just skip the mirror, if one of these were ever missing.
		$dropship_webhooks = array($webhook);
		if ($project_id == 1 && function_exists('getSkullianceDropShipWebhook')) {
			$dropship_webhooks[] = getSkullianceDropShipWebhook();
		} elseif ($project_id == 4 && function_exists('getSkullianceOculusLoungeWebhook')) {
			$dropship_webhooks[] = getSkullianceOculusLoungeWebhook();
		}
		foreach (array_unique(array_filter($dropship_webhooks)) as $target) {
			$ch = curl_init( $target );
			curl_setopt( $ch, CURLOPT_HTTPHEADER, array('Content-type: application/json'));
			curl_setopt( $ch, CURLOPT_POST, 1);
			curl_setopt( $ch, CURLOPT_POSTFIELDS, $msg);
			curl_setopt( $ch, CURLOPT_FOLLOWLOCATION, 1);
			curl_setopt( $ch, CURLOPT_HEADER, 0);
			curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1);

			$response = curl_exec( $ch );
			// If you need to debug, or find out why you can't send message uncomment line below, and execute script.
			echo $response;
			curl_close( $ch );
		}
    }
 
//    discordmsg($msg, $webhook); // SENDS MESSAGE TO DISCORD
//    echo "sent?";
?>
