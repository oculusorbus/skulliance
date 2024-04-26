<?PHP
include('credentials/webhooks_credentials.php');
//
//-- https://gist.github.com/Mo45/cb0813cb8a6ebcd6524f6a36d4f8862c
//
    function discordmsg($title, $description, $imageurl, $url="", $channel="") {

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
		}else{
			$webhook = getWebhook();
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
		                "url" => "https://skulliance.io/staking/icons/skulliance.png"
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
            // If you need to debug, or find out why you can't send message uncomment line below, and execute script.
            echo $response;
            curl_close( $ch );
        }
    }
 
//    discordmsg($msg, $webhook); // SENDS MESSAGE TO DISCORD
//    echo "sent?";
?>
