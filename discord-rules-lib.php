<?php
/*
 * Shared config and helpers for the Discord "Agree to the rules" gate.
 *
 * Included by BOTH discord-interactions.php (the endpoint Discord calls when
 * someone clicks a button) and discord-rules-post.php (the one-off script
 * that puts the rules message in the channel). Defines things only -- no
 * side effects -- so including it is always safe.
 *
 * Replaces the BotGhost rules module. The behavior is deliberately the same
 * one BotGhost implements today, with two exceptions, both noted at their
 * definitions below: the Visitor-role gate (SK_REQUIRE_VISITOR) and the fact
 * that nothing is posted publicly, so nothing needs deleting afterwards.
 */

// ---------------------------------------------------------------------------
// Credentials. All of these live in credentials/webhooks_credentials.php on
// the server (that file is not in the repo). See the checklist in
// discord-rules-post.php for what each one is and where to find it.
// ---------------------------------------------------------------------------
require_once __DIR__ . '/webhooks.php';   // $bot_token, $discordid_oculusorbus, alertAdmin()

// Guild is the same constant role.php has always defaulted to; overridable
// from credentials in case this is ever pointed at another server.
if (!isset($discord_guild_id) || $discord_guild_id === '') {
	$discord_guild_id = "944002913443938306";
}
// Neither of these is a secret -- channel and guild ids are visible to
// anyone in the server -- so they default here and only need to appear in
// credentials if they ever change.
if (!isset($rules_channel_id) || $rules_channel_id === '') {
	$rules_channel_id = "1008121821721272481";
}

// Declared so that referencing them can't raise a notice; the real values
// come from credentials. sk_config_missing() below is what actually enforces
// them -- an unset role id must never be allowed to look like a working gate.
if (!isset($discord_public_key)) $discord_public_key = '';
if (!isset($role_visitor))       $role_visitor       = '';
if (!isset($role_candidate))     $role_candidate     = '';

/**
 * Names any required credential that isn't set yet.
 *
 * Worth being strict about: with $role_visitor empty, the Visitor check in
 * the endpoint matches nobody, so every single click answers "you're already
 * verified" and the gate quietly admits no one while looking perfectly
 * healthy. That's the same silent-failure shape as the role bug, so catch it
 * up front instead.
 */
function sk_config_missing() {
	global $bot_token, $discord_public_key, $role_visitor, $role_candidate, $rules_channel_id;
	$missing = [];
	if (empty($bot_token))          $missing[] = '$bot_token';
	if (empty($discord_public_key)) $missing[] = '$discord_public_key';
	if (empty($role_visitor))       $missing[] = '$role_visitor';
	if (empty($role_candidate))     $missing[] = '$role_candidate';
	if (empty($rules_channel_id))   $missing[] = '$rules_channel_id';
	return $missing;
}

// ---------------------------------------------------------------------------
// The quiz.
//
// Keys ('hoskinson', 'ada', ...) are stable identifiers that travel in the
// button's custom_id; the values are only ever display labels. Answers are
// matched on the KEY, never on the label or on the button's position, which
// is what lets the options be shuffled per click without tracking a mapping
// anywhere. Rewording a label can't break grading; changing a key would, so
// don't.
// ---------------------------------------------------------------------------
$SK_QUESTIONS = [
	[
		'prompt'  => 'Who is the founder of our blockchain?',
		'correct' => 'hoskinson',
		'options' => [
			'hoskinson' => 'Charles Hoskinson',
			'buterin'   => 'Vitalik Buterin',
			'yakovenko' => 'Anatoly Yakovenko',
			'nailwal'   => 'Sandeep Nailwal',
		],
	],
	[
		'prompt'  => 'What is the native currency of our blockchain?',
		'correct' => 'ada',
		'options' => [
			'ada'   => 'ADA',
			'eth'   => 'ETH',
			'matic' => 'MATIC',
			'sol'   => 'SOL',
		],
	],
];

$SK_WELCOME = 'Welcome to the Skulliance.';

// Kick on a wrong answer -- what BotGhost does today, so it is the default.
// Flip to false to simply refuse (no role granted, not removed from the
// server) if that ever turns out to be too sharp an edge; nothing else in
// the flow has to change.
define('SK_KICK_ON_WRONG', true);

// Only members holding the Visitor role can take the quiz.
//
// NOT how BotGhost behaves, and the one deliberate behavioral change here.
// The rules button lives in a public channel forever, so any member can
// click it at any time -- including someone who joined two years ago,
// misreads a question and gets kicked out of the server for a misclick.
// Since the interaction payload already carries the clicker's roles, this
// costs nothing and is checked before a single Discord call is made.
define('SK_REQUIRE_VISITOR', true);

define('SK_EPHEMERAL', 64);           // message flag: only the clicker sees it
define('SK_BTN_START', 'sk_rules_start');

// ---------------------------------------------------------------------------
// Minimal Discord REST helper.
//
// Deliberately NOT MakeRequest() from message.php, for two reasons: that one
// hardcodes POST (this needs PUT and DELETE), and it opens a 'request.txt'
// debug file in the working directory on every single call, which is fine for
// an occasional DM and wrong for an endpoint on a hot path. Same v10 base and
// same bot-token auth as everything else.
//
// Returns ['ok' => bool, 'status' => int, 'body' => array|null].
// ---------------------------------------------------------------------------
function sk_discord_api($method, $path, $payload = null) {
	global $bot_token;

	$ch = curl_init();
	$headers = ['Authorization: Bot ' . $bot_token];
	$opts = [
		CURLOPT_URL            => 'https://discord.com/api/v10' . $path,
		CURLOPT_RETURNTRANSFER => 1,
		CURLOPT_CUSTOMREQUEST  => $method,
		CURLOPT_TIMEOUT        => 5,   // tighter than role.php's 8s: an
		                               // interaction has a 3s budget, so a
		                               // slow call should fail, not hang.
		CURLOPT_SSL_VERIFYPEER => 0,
	];
	if ($payload !== null) {
		$headers[] = 'Content-Type: application/json';
		$opts[CURLOPT_POSTFIELDS] = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
	} else if ($method === 'PUT') {
		$headers[] = 'Content-Length: 0';
	}
	$opts[CURLOPT_HTTPHEADER] = $headers;
	curl_setopt_array($ch, $opts);

	$raw    = curl_exec($ch);
	$status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
	$err    = curl_error($ch);

	$ok = ($status >= 200 && $status < 300);
	if (!$ok) {
		$decoded = json_decode((string)$raw, true);
		$detail  = '';
		if (is_array($decoded) && isset($decoded['message'])) {
			$detail = ' discord="' . $decoded['message'] . '"';
			if (isset($decoded['code'])) $detail .= ' code=' . $decoded['code'];
		}
		if ($err !== '') $detail .= ' curl="' . $err . '"';
		error_log('sk_discord_api FAILED ' . $method . ' ' . $path . ' http=' . $status
			. ' token=' . (empty($bot_token) ? 'MISSING' : 'present') . $detail);
	}

	return ['ok' => $ok, 'status' => $status, 'body' => json_decode((string)$raw, true)];
}

// ---------------------------------------------------------------------------
// Build the button payload for question $index.
//
// Options are shuffled on every render so the correct answer isn't always in
// the same seat -- otherwise the whole gate degrades into "someone posts
// 'it's the 4th one then the 1st' and nobody reads anything". Safe to shuffle
// precisely because grading is by key (see $SK_QUESTIONS above).
// ---------------------------------------------------------------------------
function sk_question_payload($index) {
	global $SK_QUESTIONS;
	$q = $SK_QUESTIONS[$index];

	$keys = array_keys($q['options']);
	shuffle($keys);

	$buttons = [];
	foreach ($keys as $key) {
		$buttons[] = [
			'type'      => 2,
			'style'     => 2,                                  // secondary/grey
			'label'     => $q['options'][$key],
			'custom_id' => 'sk_a:' . $index . ':' . $key,
		];
	}

	return [
		'content'    => '**Question ' . ($index + 1) . ' of ' . count($SK_QUESTIONS) . '**' . "\n" . $q['prompt'],
		'flags'      => SK_EPHEMERAL,
		'components' => [['type' => 1, 'components' => $buttons]],
	];
}
