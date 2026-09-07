<?php
/*
 * One-off: post the "Agree to the rules" message with its button into the
 * rules channel. Run once. The message and its button persist forever, which
 * is why this whole feature needs no slash commands registered -- there is
 * nothing to keep in sync.
 *
 * Run it from the server shell:
 *     php /path/to/staking/discord-rules-post.php
 *
 * Or, if you'd rather do it from a browser, load it while logged in as
 * oculusorbus. Any other visitor gets a 404 (see the guard below).
 *
 * ---------------------------------------------------------------------------
 * SETUP CHECKLIST -- add these to credentials/webhooks_credentials.php first
 * ---------------------------------------------------------------------------
 *   $discord_public_key = "...";   // Dev Portal > your app > General
 *                                  // Information > PUBLIC KEY. This is the
 *                                  // signing key, NOT the bot token and NOT
 *                                  // the OAuth secret -- three different
 *                                  // values that are easy to confuse.
 *   $role_visitor       = "...";   // right-click role > Copy Role ID
 *   $role_candidate     = "...";
 *   $rules_channel_id   = "1008121821721272481";   // already known
 *
 * Then, in the Developer Portal, set the Interactions Endpoint URL to:
 *   https://skulliance.io/staking/discord-interactions.php
 * Saving that URL is itself the test -- Discord signs a probe request and
 * refuses the URL unless the endpoint verifies it. If it saves, signature
 * verification works.
 *
 * The bot also needs, in Server Settings > Roles:
 *   - Manage Roles and Kick Members
 *   - its own role positioned ABOVE Visitor and Candidate. This is the one
 *     that bites: permissions can be perfect and role changes still fail with
 *     "50013 Missing Permissions" purely because of role ORDER.
 *
 * Finally, turn off BotGhost's rules module, or both will answer the click.
 */

ini_set('display_errors', '0');
ini_set('log_errors', '1');

require_once __DIR__ . '/discord-rules-lib.php';

// --- Who may run this ------------------------------------------------------
// It posts into a public channel, so it must not be a URL anyone can hit.
// CLI is unrestricted; over the web, only oculusorbus. Everyone else gets a
// 404 rather than a 403, so the file's existence isn't advertised.
$is_cli = (php_sapi_name() === 'cli');
if (!$is_cli) {
	if (session_status() === PHP_SESSION_NONE) session_start();
	$who = $_SESSION['userData']['discord_id'] ?? '';
	if ($who === '' || empty($discordid_oculusorbus) || $who !== $discordid_oculusorbus) {
		http_response_code(404);
		exit('Not found');
	}
}

function sk_say($line) {
	echo $line . (php_sapi_name() === 'cli' ? "\n" : "<br>\n");
}

// --- Preflight -------------------------------------------------------------
// Check config before calling Discord, so a missing value produces a plain
// answer here instead of an opaque API error.
$missing = [];
if (empty($bot_token))          $missing[] = '$bot_token';
if (empty($discord_public_key)) $missing[] = '$discord_public_key';
if (empty($role_visitor))       $missing[] = '$role_visitor';
if (empty($role_candidate))     $missing[] = '$role_candidate';
if (empty($rules_channel_id))   $missing[] = '$rules_channel_id';

if ($missing) {
	sk_say('Not posting. Missing from credentials/webhooks_credentials.php:');
	foreach ($missing as $m) sk_say('  - ' . $m);
	sk_say('See the checklist at the top of this file.');
	exit(1);
}

// --- Post it ---------------------------------------------------------------
// Through the bot, not discordmsg(). Incoming webhooks can't carry
// interactive components, so a webhook-posted message simply cannot have a
// button on it -- this has to go through the bot token.
// The rules text, verbatim as it reads in the channel today. Kept as the
// message CONTENT rather than inside the embed so it renders exactly as
// before -- the embed underneath is only the quiz warning.
$rules = "The Skulliance Server Rules\xF0\x9F\x91\x87\n"
	. "1\xEF\xB8\x8F\xE2\x83\xA3 Keep Calm and Have Fun.\n\n"
	. "2\xEF\xB8\x8F\xE2\x83\xA3 Respect Others\n"
	. "Treat each other with respect and kindness. In other words don't be a dick! Any form of harassment, spamming, threatening, FUD, trolling, sexism, racism, hating on any person or group, and or any rude/offensive actions or words will not be tolerated.\n\n"
	. "3\xEF\xB8\x8F\xE2\x83\xA3 NSFW Content\n"
	. "Posting any form of NSFW (Not safe for work) content is prohibited. This includes but is not limited to pornographic, sexual, violent, harmful content in the form of text, images, Discord handle, and Discord profile picture.\n\n"
	. "4\xEF\xB8\x8F\xE2\x83\xA3 DMs\n"
	. "No unnecessary pings / mentions / DMs of any of the crew members.\n\n"
	. "5\xEF\xB8\x8F\xE2\x83\xA3 Data Breach/Phishing Attacks\n"
	. "No personal information is to be published. This includes email addresses, passwords, bank account information, credit card information, addresses, etc.\n\n"
	. "6\xEF\xB8\x8F\xE2\x83\xA3 Follow the Discord Community Guidelines (https://discordapp.com/guidelines)";

$payload = [
	'content' => $rules,
	'embeds' => [[
		'description' => "Press the button to agree. You'll be asked **two quick questions** "
			. "about the chain we're built on.\n\n"
			. "Answer correctly and you'll be welcomed in as a Candidate. "
			. "**Answer incorrectly and you'll be removed from the server** — so take your time.",
		'color'       => hexdec('CC0000'),
	]],
	'components' => [[
		'type' => 1,
		'components' => [[
			'type'      => 2,
			'style'     => 1,              // primary/blurple
			'label'     => 'I Agree to the Rules',
			'emoji'     => ['name' => '💀'],
			'custom_id' => SK_BTN_START,
		]],
	]],
];

$res = sk_discord_api('POST', '/channels/' . $rules_channel_id . '/messages', $payload);

if ($res['ok']) {
	sk_say('Posted to channel ' . $rules_channel_id . '.');
	sk_say('Message id: ' . ($res['body']['id'] ?? '(unknown)'));
	sk_say('');
	sk_say('Now set the Interactions Endpoint URL in the Developer Portal, if you');
	sk_say('have not already, and turn off the BotGhost rules module.');
} else {
	sk_say('Failed: HTTP ' . $res['status']);
	if (isset($res['body']['message'])) {
		sk_say('Discord said: ' . $res['body']['message']
			. (isset($res['body']['code']) ? ' (code ' . $res['body']['code'] . ')' : ''));
	}
	if ($res['status'] == 403) {
		sk_say('403 here usually means the bot cannot see or post in that channel.');
	}
	sk_say('Details are in the PHP error log.');
	exit(1);
}
