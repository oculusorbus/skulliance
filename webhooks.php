<?PHP
include_once __DIR__ . '/credentials/webhooks_credentials.php';
//
//-- https://gist.github.com/Mo45/cb0813cb8a6ebcd6524f6a36d4f8862c
//
    // $content, if passed, is a SEPARATE top-level message field from
    // $description -- required because Discord only delivers an actual
    // notification/ping for <@id> mentions placed in a webhook message's
    // content field. A mention written inside the embed (title/description/
    // fields) still renders as a clickable highlighted name, but never
    // notifies that user -- verified against Discord's own webhook
    // behavior, not an assumption. Leave $content empty (the default) for
    // every existing call site that doesn't need to actually ping anyone;
    // this is purely additive.
    function discordmsg($title, $description, $imageurl, $url="", $channel="", $thumbnail="", $color="000000", $author=null, $footer=null, $content="") {

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
		}else if($channel == "monstrocity"){
			$webhook = getMonstrocityWebhook();
		}else if($channel == "bossbattles"){
			$webhook = getBossBattlesWebhook();
		}else if($channel == "store"){
			$webhook = getStoreWebhook();
		}else if($channel == "auctions"){
			$webhook = getAuctionsWebhook();
		}else if($channel == "raffles"){
			$webhook = getRafflesWebhook();
		}else if($channel == "delegations"){
			$webhook = getDelegationsWebhook();
		}else if($channel == "gauntlet"){
			$webhook = getGauntletsWebhook();
		}else if($channel == "cryptcrawl"){
			$webhook = getCryptCrawlWebhook();
		}else if($channel == "cryptconquest"){
			// function_exists guard (unlike every other channel case here) --
			// this is a brand-new channel with no credential added to
			// credentials/webhooks_credentials.php yet. Every other case
			// assumes its getXWebhook() function already exists because it
			// always has by the time that channel went live; this one
			// hasn't, and a plain undefined-function call would fatal the
			// whole request (game action, leaderboard reward run, etc.) that
			// tried to post here, not just silently skip the Discord post.
			// Safe to remove this guard once getCryptConquestWebhook() is
			// actually added.
			$webhook = function_exists('getCryptConquestWebhook') ? getCryptConquestWebhook() : "";
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
	        "thumbnail"   => ["url" => $thumbnail],
	    ];
	    if ($imageurl !== "") $embed["image"] = ["url" => $imageurl];
	    if($author) $embed["author"] = $author;
	    // $footer is ["text" => ..., "icon_url" => ...] (icon_url optional) --
	    // renders as a small icon + line of text at the very bottom of the
	    // embed, a slot distinct from both $thumbnail (top-right corner) and
	    // $author's own icon_url (top-left, next to the author line), so it
	    // doesn't collide with either even when a caller already uses both --
	    // e.g. Crypt Crawl's own result post, which puts the player's avatar
	    // in $thumbnail and reserves this for the CARBON icon + amount
	    // earned instead. Optional and additive -- every existing call site
	    // that doesn't pass it renders exactly as it always has.
	    if($footer) $embed["footer"] = $footer;

	    $payload = [
	        "username"   => "Skull Bot",
	        "avatar_url" => "https://skulliance.io/staking/icons/skulliance.png",
	        "tts"        => false,
	        "embeds"     => [$embed],
		];
		if ($content !== "") $payload["content"] = $content;

	    $msg = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );

        if($webhook != "") {
            $ch = curl_init( $webhook );
            curl_setopt( $ch, CURLOPT_HTTPHEADER, array('Content-type: application/json'));
            curl_setopt( $ch, CURLOPT_POST, 1);
            curl_setopt( $ch, CURLOPT_POSTFIELDS, $msg);
            curl_setopt( $ch, CURLOPT_FOLLOWLOCATION, 1);
            curl_setopt( $ch, CURLOPT_HEADER, 0);
            curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt( $ch, CURLOPT_TIMEOUT, 8);
            $response = curl_exec( $ch );
            curl_close( $ch );
        }
    }

//    discordmsg($msg, $webhook); // SENDS MESSAGE TO DISCORD
//    echo "sent?";
?>
