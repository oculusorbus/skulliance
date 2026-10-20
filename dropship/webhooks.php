<?PHP
include('credentials/webhooks_credentials.php');
//
//-- https://gist.github.com/Mo45/cb0813cb8a6ebcd6524f6a36d4f8862c
//
/**
 * The embed Discord is sent, as a value.
 *
 * SEPARATE SO IT CAN BE CHECKED. The image and the thumbnail are attached
 * only when there is a url for them, because Discord answers an embed it
 * cannot parse by rejecting the whole post -- an empty "url" would have
 * cost the announcement, not just the picture -- and that rule was buried
 * inside a function that also reads credentials and makes curl calls, so
 * nothing could execute it.
 */
function dropshipDiscordEmbed($title, $description, $url, $timestamp, $imageurl, $thumburl) {
	$embed = [
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

		            /* image and thumbnail are ATTACHED BELOW, not here --
		               Discord rejects the whole embed over a url it cannot
		               parse, and an empty string is one of those. A post with
		               no art beats no post at all. */

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
	];

	/* A url only, never an empty key: Discord answers an unparseable
	   url by rejecting the WHOLE embed, so an empty string here loses the
	   post as well as the picture. No art beats no announcement. */
	if ($imageurl !== '' && $imageurl !== null) {
		$embed["embeds"][0]["image"] = ["url" => $imageurl];
	}
	if ($thumburl !== '') {
		$embed["embeds"][0]["thumbnail"] = ["url" => $thumburl];
	}

	return $embed;
}

    function discordmsg($title, $description, $imageurl, $project_id, $url="") {
		// Delay execution by 1 minute to allow the player to finish their game before sending results to Discord
		if($url == ""){
			// dashboard.php, not index.php -- index.php is just a redirect
			// into Skulliance's login now, a dead end as a Discord embed link.
			$url = "https://skulliance.io/staking/dropship/dashboard.php";
		}
		/*
		 * DROP SHIP'S OWN ACCESSOR, by whichever name it has.
		 *
		 * Both apps' credentials files declare a getWebhook(): Skulliance's
		 * takes no arguments, Drop Ship's takes a project id. They are
		 * different functions with one name, so whichever file loads second
		 * is a fatal "cannot redeclare" -- which is what killed the play
		 * page the first time a trait drop fired, because awarding one
		 * loads the platform's webhooks.php before this file is included.
		 *
		 * dropshipGetWebhook() is the way out: rename the function in
		 * dropship/credentials/webhooks_credentials.php and the clash is
		 * gone for good. This prefers it and falls back, so the rename can
		 * happen on the server without the code having to land first.
		 */
		$webhook = function_exists('dropshipGetWebhook')
			? dropshipGetWebhook($project_id)
			: getWebhook($project_id);
	    $timestamp = date("c", strtotime("now"));

		/*
		 * THE THUMBNAIL IS THE THEME'S, FOUND BY PROJECT, NOT BY $prefix.
		 *
		 * It was "https://skulliance.io".$prefix."images/vip.gif" off the
		 * global, which automate.php never sets -- so every Game Over and New
		 * Game post the weekly cron sent carried a malformed url and Discord
		 * dropped the art. dropshipThemeImageUrl() reads the slug off the
		 * theme row for THIS project and checks the file is on disk, so a
		 * theme with no copy (dread-city has no images/vip.gif) gets no
		 * thumbnail rather than a broken one or Drop Ship's.
		 *
		 * function_exists because announcements.php includes this file on its
		 * own, without db.php and so without dropship-themes.php.
		 */
		$thumburl = '';
		if (function_exists('dropshipThemeImageUrl') && isset($GLOBALS['conn'])) {
			$thumburl = dropshipThemeImageUrl($GLOBALS['conn'], $project_id, 'images/vip.gif');
		}

	    $msg = json_encode(dropshipDiscordEmbed($title, $description, $url, $timestamp, $imageurl, $thumburl),
	                       JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );

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
		/*
		 * THE MIRROR IS FOUND BY NAME, NOT BY A LIST OF PROJECT IDS.
		 *
		 * This was `if project 1 ... elseif project 4`, so adding a
		 * Skulliance channel for a theme meant writing the credential AND
		 * editing this branch -- and the credential alone looks like it
		 * should be enough, which is exactly what somebody expects.
		 *
		 * The accessors are named after the theme slug in CamelCase, which
		 * every one of them already follows: drop-ship ->
		 * getSkullianceDropShipWebhook, filthy-mermaid ->
		 * getSkullianceFilthyMermaidWebhook. So the name is derived and
		 * function_exists() decides. Write the credential, get the mirror.
		 *
		 * STILL GUARDED by function_exists: these live in a credentials
		 * file that is not in the repo, and an undefined-function call
		 * would fatal every discordmsg(), not merely skip the mirror.
		 */
		$dropship_webhooks = array($webhook);
		$ds_mirror_slug = '';
		if (function_exists('dropshipThemeConfig') && isset($GLOBALS['conn'])) {
			$ds_cfg = dropshipThemeConfig($GLOBALS['conn'], $project_id);
			if ($ds_cfg && !empty($ds_cfg['slug'])) $ds_mirror_slug = $ds_cfg['slug'];
		}
		if ($ds_mirror_slug !== '') {
			$ds_mirror_fn = 'getSkulliance'
				. str_replace(' ', '', ucwords(str_replace('-', ' ', $ds_mirror_slug)))
				. 'Webhook';
			if (function_exists($ds_mirror_fn)) $dropship_webhooks[] = $ds_mirror_fn();
		}
		foreach (array_unique(array_filter($dropship_webhooks)) as $target) {
			$ch = curl_init( $target );
			curl_setopt( $ch, CURLOPT_HTTPHEADER, array('Content-type: application/json'));
			curl_setopt( $ch, CURLOPT_POST, 1);
			curl_setopt( $ch, CURLOPT_POSTFIELDS, $msg);
			curl_setopt( $ch, CURLOPT_FOLLOWLOCATION, 1);
			curl_setopt( $ch, CURLOPT_HEADER, 0);
			curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1);
			/* A DISCORD POST MUST NEVER HOLD A PAGE OPEN. There was no
			   timeout here at all, so a slow or rate-limited webhook stalled
			   whatever request made the call for as long as Discord felt
			   like -- and when that call moved onto the run request, the
			   page stopped rendering part-way and players lost the replay
			   below it. An announcement is never worth a broken page. */
			curl_setopt( $ch, CURLOPT_CONNECTTIMEOUT, 2);
			curl_setopt( $ch, CURLOPT_TIMEOUT, 4);

			$response = curl_exec( $ch );
			// If you need to debug, or find out why you can't send message uncomment line below, and execute script.
			echo $response;
			curl_close( $ch );
		}
    }
 
//    discordmsg($msg, $webhook); // SENDS MESSAGE TO DISCORD
//    echo "sent?";
?>
