<?PHP
include('credentials/webhooks_credentials.php');
//
//-- https://gist.github.com/Mo45/cb0813cb8a6ebcd6524f6a36d4f8862c
//
    function discordmsg($title, $description, $imageurl, $url="", $channel="", $thumbnail="") {

		if($url == ""){
			$url = "https://skulliance.io/staking";
		}
		if($channel == "general"){
			$webhook = getGeneralWebhook();
		}else if($channel == "member"){
			$webhook = getMemberWebhook();
		}else if($channel == "elite"){
			$webhook = getEliteWebhook();
		}else if($channel == "innercircle"){
			$webhook = getInnerCircleWebhook();
		}else if($channel == "realms"){
			$webhook = getRealmsWebhook();
		}else if($channel == "raids"){
			$webhook = getRaidsWebhook();
		}else{
			$webhook = getWebhook();
		}
		if($thumbnail == ""){
			$thumbnail = "https://skulliance.io/staking/icons/skulliance.png";
		}
	    $timestamp = date("c", strtotime("now"));
	    $msg = json_encode([
	    // Message
	    //"content" => "",

	    // Username
	    "username" => "Skull Bot",

	    // Avatar URL.
	    // Uncomment to use custom avatar instead of bot's pic
	    "avatar_url" => "https://skulliance.io/staking/icons/skulliance.png",

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
		                "icon_url" => "https://www.madballs.net/drop-ship/images/vip.gif"
		            ],*/

		            // Embed image
		            "image" => [
		                "url" => $imageurl
		            ],

		            // thumbnail
		            "thumbnail" => [
		                "url" => $thumbnail
		            ],

		            // Author name & url
					/*
		            "author" => [
		                "name" => "Kill Bot",
		                "url" => "https://www.madballs.net/dropship"
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
        if($webhook != "") {
            $ch = curl_init( $webhook );
            curl_setopt( $ch, CURLOPT_HTTPHEADER, array('Content-type: application/json'));
            curl_setopt( $ch, CURLOPT_POST, 1);
            curl_setopt( $ch, CURLOPT_POSTFIELDS, $msg);
            curl_setopt( $ch, CURLOPT_FOLLOWLOCATION, 1);
            curl_setopt( $ch, CURLOPT_HEADER, 0);
            curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1);

            $response = curl_exec( $ch );
            echo $response;
            error_log("[webhook] channel=".$channel." response=".$response);
            curl_close( $ch );

            // Debug: if a specific channel was targeted, mirror to notifications
            if($channel != "") {
                $notify_webhook = getWebhook();
                $debug_title = "[debug:".$channel."] ".$title;
                $debug_desc  = $description.($response ? "\n\n**Discord error:** ".$response : "");
                $debug_msg = json_encode([
                    "username"   => "Skull Bot",
                    "avatar_url" => "https://skulliance.io/staking/icons/skulliance.png",
                    "tts"        => false,
                    "embeds"     => [[
                        "title"       => $debug_title,
                        "type"        => "rich",
                        "description" => $debug_desc,
                        "url"         => $url,
                        "timestamp"   => $timestamp,
                        "color"       => hexdec("000000"),
                    ]]
                ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                $ch2 = curl_init($notify_webhook);
                curl_setopt($ch2, CURLOPT_HTTPHEADER, array('Content-type: application/json'));
                curl_setopt($ch2, CURLOPT_POST, 1);
                curl_setopt($ch2, CURLOPT_POSTFIELDS, $debug_msg);
                curl_setopt($ch2, CURLOPT_FOLLOWLOCATION, 1);
                curl_setopt($ch2, CURLOPT_HEADER, 0);
                curl_setopt($ch2, CURLOPT_RETURNTRANSFER, 1);
                curl_exec($ch2);
                curl_close($ch2);
            }
        }
    }
 
//    discordmsg($msg, $webhook); // SENDS MESSAGE TO DISCORD
//    echo "sent?";
?>
