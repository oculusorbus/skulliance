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
    /*
     * IF THE IMAGE IS OURS, SEND THE BYTES -- DO NOT ASK DISCORD TO FETCH IT.
     *
     * Embeds have been showing broken images intermittently for a long time,
     * across Fighters, trait drops and raids. The origin is not the problem,
     * and that was measured rather than assumed: skulliance.io answers
     * Discordbot in 0.27s, serves 60 requests at 12 concurrency without a
     * stumble, does not discriminate by user agent, has no WAF in front and
     * prunes nothing. The file behind one failed Fighter embed is a valid
     * 224,773-byte PNG that opens fine in a browser.
     *
     * What the three cases share is the only thing left: Discord's proxy has
     * to reach this server at post time, and a miss is cached against the
     * URL. Raids settle it -- those use realm theme art that has existed for
     * months, so nothing about when a file was written explains them.
     *
     * So the dependency goes. Any image URL on our own domain is resolved to
     * its file and uploaded with the post as a multipart attachment, which
     * the embed then references as attachment://<name>. Discord never comes
     * back to us: the image either posts or the post fails, and there is no
     * window to lose. Anything not ours, or too big, or missing, falls back
     * to the URL exactly as before.
     */
    function skl_local_image_path($url) {
        if (!is_string($url) || $url === '') return '';
        $root = realpath(__DIR__);
        if ($root === false) return '';
        foreach (array('https://skulliance.io/staking/',
                       'https://www.skulliance.io/staking/') as $prefix) {
            if (strpos($url, $prefix) !== 0) continue;
            $rel = parse_url(substr($url, strlen($prefix)), PHP_URL_PATH);
            if ($rel === null || $rel === false || $rel === '') return '';
            $abs = realpath($root . '/' . rawurldecode($rel));
            /* realpath + prefix check: a ../ in the URL cannot walk out. */
            if ($abs === false || strpos($abs, $root . DIRECTORY_SEPARATOR) !== 0) return '';
            if (!is_file($abs)) return '';
            $size = filesize($abs);
            /* Discord refuses oversized uploads; 7MB leaves room under the
               8MB floor. Nothing we post is close, but a fallback beats a
               rejected post. */
            if ($size < 1 || $size > 7340032) return '';
            return $abs;
        }
        return '';
    }

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
		}else if($channel == "obscura"){
			// Same guard as the two below: if the credential has not been added
			// yet -- or was added under a different name -- this skips the post
			// instead of fataling the guess that triggered it. A player losing a
			// run must not see an error because a webhook is misnamed.
			// Expected in credentials/webhooks_credentials.php as:
			//   function getObscuraWebhook(){ return "https://discord.com/api/webhooks/..."; }
			$webhook = function_exists('getObscuraWebhook') ? getObscuraWebhook() : "";
		}else if($channel == "guardians"){
			// Same guard as the neighbours: the credential is being added
			// separately, and a player losing an hour-long siege must not see
			// an error because the webhook is not wired up yet.
			// Expected in credentials/webhooks_credentials.php as:
			//   function getGuardiansWebhook(){ return "https://discord.com/api/webhooks/..."; }
			$webhook = function_exists('getGuardiansWebhook') ? getGuardiansWebhook() : "";
		}else if($channel == "dhcfighters"){
			// Same guard as its neighbours. getDHCFightersWebhook() is being
			// added to credentials/webhooks_credentials.php separately, and a
			// player earning a trait must never see an error because the
			// webhook is not wired up yet -- the drop is already in the ledger
			// by the time this runs.
			$webhook = function_exists('getDHCFightersWebhook') ? getDHCFightersWebhook() : "";
		}else if($channel == "dhcarena"){
			// Arena battle results. Same guard as its neighbours, and it earns
			// it twice over: this fires from inside dhca_finish(), AFTER the
			// bench is written, the record is stored and the trait is in the
			// ledger. A fatal here would take down the request carrying the
			// player's own result back to them, for a battle that has already
			// been paid for. dhca_announce() also wraps the call in try/catch
			// for the same reason.
			//   function getDHCArenaWebhook(){ return "https://discord.com/api/webhooks/..."; }
			$webhook = function_exists('getDHCArenaWebhook') ? getDHCArenaWebhook() : "";
		}else if($channel == "skullracer"){
			// Same not-yet-configured situation as cryptconquest above --
			// getSkullRacerWebhook() doesn't exist in
			// credentials/webhooks_credentials.php yet. Safe to remove
			// this guard once it's added.
			$webhook = function_exists('getSkullRacerWebhook') ? getSkullRacerWebhook() : "";
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
	    /* attachment:// refers to a file uploaded in the same multipart
	       request -- see the curl block below. */
	    $attachfile = skl_local_image_path($imageurl);
	    $attachok   = ($attachfile !== "");
	    if ($attachok)            $embed["image"] = ["url" => "attachment://" . rawurlencode(basename($attachfile))];
	    elseif ($imageurl !== "") $embed["image"] = ["url" => $imageurl];
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
            if ($attachok) {
                /* Multipart: the embed JSON goes in payload_json and the
                   image rides along as files[0]. NO Content-type header --
                   curl has to set its own multipart boundary, and forcing
                   application/json here makes Discord reject the whole
                   post. The timeout is longer because this one carries a
                   couple of hundred KB rather than a line of JSON. */
                curl_setopt( $ch, CURLOPT_POST, 1);
                curl_setopt( $ch, CURLOPT_POSTFIELDS, array(
                    'payload_json' => $msg,
                    'files[0]'     => new CURLFile($attachfile, 'image/png', basename($attachfile)),
                ));
                curl_setopt( $ch, CURLOPT_TIMEOUT, 20);
            } else {
                curl_setopt( $ch, CURLOPT_HTTPHEADER, array('Content-type: application/json'));
                curl_setopt( $ch, CURLOPT_POST, 1);
                curl_setopt( $ch, CURLOPT_POSTFIELDS, $msg);
                curl_setopt( $ch, CURLOPT_TIMEOUT, 8);
            }
            curl_setopt( $ch, CURLOPT_FOLLOWLOCATION, 1);
            curl_setopt( $ch, CURLOPT_HEADER, 0);
            curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1);
            $response = curl_exec( $ch );
            curl_close( $ch );
        }
    }

    // Operational alert to the team, pinging $discordid_oculusorbus so it
    // actually raises a notification rather than sitting unread in a channel.
    //
    // Posts to the DEFAULT channel -- the same getWebhook() feed every
    // discordmsg() call without an explicit $channel already uses. No new
    // webhook credential to add, which is the point: this has to work on
    // the next deploy, not once someone remembers to configure it. The
    // <@id> ping is what makes it findable in a busy channel.
    //
    // Why a webhook and not a bot DM: sendDM() in message.php goes through
    // MakeRequest(), which authenticates with $bot_token -- the very thing
    // most likely to be dead when something worth alerting about happens.
    // A DM alert would go silent in precisely the case it exists for. Webhook
    // URLs are self-authenticating and don't touch the token, so this path
    // still works when the bot side is entirely broken. The <@id> in
    // $content gives a real phone notification either way.
    //
    // $signature controls throttling, NOT the message text: alerts fire from
    // request-scoped code that can run on every page load, so an unthrottled
    // version would post hundreds of identical messages an hour and get
    // muted -- another way to end up not knowing. Same signature inside the
    // cooldown is suppressed; a DIFFERENT signature alerts immediately, so a
    // new kind of failure is never hidden behind an older one's cooldown.
    //
    // Never lets a monitoring failure break the page it's monitoring: the
    // webhook post is best-effort and every filesystem call is silenced.
    function alertAdmin($subject, $detail, $signature = "", $cooldown = 900) {
        global $discordid_oculusorbus;

        $key  = md5($subject . '|' . ($signature !== "" ? $signature : $detail));
        $file = rtrim(sys_get_temp_dir(), '/\\') . '/skulliance_alert_' . $key;
        $now  = time();

        // Touch BEFORE sending, so concurrent requests hitting the same
        // failure don't all get through the check while the first is still
        // waiting on curl.
        if (@is_file($file) && ($now - (int)@filemtime($file)) < $cooldown) {
            return false;
        }
        @touch($file);

        // Mentions only notify from the top-level content field -- see the
        // note on discordmsg()'s $content parameter above.
        $content = !empty($discordid_oculusorbus) ? '<@' . $discordid_oculusorbus . '>' : '';

        discordmsg(
            '⚠️ ' . $subject,
            $detail,
            "",
            "https://skulliance.io/staking",
            "",
            "",
            "CC0000",
            null,
            ["text" => php_uname('n') . ' · ' . date('Y-m-d H:i:s T')],
            $content
        );
        return true;
    }

//    discordmsg($msg, $webhook); // SENDS MESSAGE TO DISCORD
//    echo "sent?";
?>
