<?PHP
include('credentials/webhooks_credentials.php');
//
//-- https://gist.github.com/Mo45/cb0813cb8a6ebcd6524f6a36d4f8862c
//
    function discordmsg($title, $description, $imageurl, $url="", $channel="", $thumbnail="", $color="000000", $author=null) {

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
		}else if($channel == "dailyrewards"){
			$webhook = getDailyRewardsWebhook();
		}else if($channel == "missions"){
			$webhook = getMissionsWebhook();
		}else if($channel == "skullswap"){
			$webhook = getSkullSwapWebhook();
		}else{
			$webhook = getWebhook();
		}
		if($thumbnail == ""){
			$thumbnail = "https://skulliance.io/staking/icons/skulliance.png";
		}
	    $timestamp = date("c", strtotime("now"));

	    $embed = [
	        "title"       => $title,
	        "type"        => "rich",
	        "description" => $description,
	        "url"         => $url,
	        "timestamp"   => $timestamp,
	        "color"       => hexdec( $color ?: "000000" ),
	        "image"       => ["url" => $imageurl],
	        "thumbnail"   => ["url" => $thumbnail],
	    ];
	    if($author) $embed["author"] = $author;

	    $msg = json_encode([
	        "username"   => "Skull Bot",
	        "avatar_url" => "https://skulliance.io/staking/icons/skulliance.png",
	        "tts"        => false,
	        "embeds"     => [$embed],
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
            curl_close( $ch );
        }
    }

//    discordmsg($msg, $webhook); // SENDS MESSAGE TO DISCORD
//    echo "sent?";
?>
