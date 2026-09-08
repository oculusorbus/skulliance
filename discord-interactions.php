<?php
/*
 * Discord Interactions endpoint -- the "Agree to the rules" gate.
 *
 * Set this URL as the Interactions Endpoint URL in the Discord Developer
 * Portal (General Information):
 *
 *     https://skulliance.io/staking/discord-interactions.php
 *
 * WHY THIS IS A PLAIN PHP PAGE AND NOT A BOT PROCESS
 * Discord offers two ways to receive interactions: a Gateway WebSocket, which
 * needs a daemon holding a persistent connection, or an HTTP endpoint it POSTs
 * to. BotGhost is the first kind, which is exactly why it keeps dying and
 * needs restarting. This is the second kind: Apache serves it like any other
 * page, there is no process to crash, and there is nothing to restart.
 *
 * THIS FILE IS PUBLIC. Discord calls it as an anonymous client, so it must NOT
 * include skulliance.php -- that would redirect Discord to error.php and the
 * gate would never fire. Same constraint skullpaper.php documents in its own
 * header. Authentication is by Ed25519 signature instead (below), which is
 * strictly stronger than a session check: only Discord can produce a request
 * this endpoint will act on.
 */

// Errors to the log, NEVER to the response body. db.php turns display_errors
// ON globally, and a single stray PHP notice printed into a response here is
// not cosmetic -- it corrupts the JSON and Discord shows the user an error.
// This file doesn't include db.php, but it is one require away from anything
// that does, so pin it rather than depend on that staying true.
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
ini_set('log_errors', '1');

require_once __DIR__ . '/discord-rules-lib.php';
require_once __DIR__ . '/role.php';       // assignRole()

// ---------------------------------------------------------------------------
// 1. Verify the signature.
//
// Discord signs every request with Ed25519 and expects a 401 -- specifically
// 401, not 400 or 403 -- when verification fails. It sends deliberately
// invalid signatures while you're saving the endpoint URL and refuses to
// accept the URL unless it gets that 401 back, so this is both the security
// boundary and the thing that makes setup work at all.
//
// The signature covers the RAW body. It has to be verified before
// json_decode() and compared byte for byte -- re-encoding the decoded array
// would produce different bytes and never verify.
// ---------------------------------------------------------------------------
$raw       = file_get_contents('php://input');
$signature = $_SERVER['HTTP_X_SIGNATURE_ED25519'] ?? '';
$timestamp = $_SERVER['HTTP_X_SIGNATURE_TIMESTAMP'] ?? '';

function sk_reject($why) {
	http_response_code(401);
	error_log('discord-interactions: rejected -- ' . $why);
	exit('invalid request signature');
}

// This server has no ext/sodium. Checked every PHP build present -- all 18
// CloudLinux alt-php versions and both EasyApache ones -- and none of them
// ship it, so this is not a "flip a switch in the PHP selector" situation.
//
// Hence the vendored pure-PHP implementation in lib/sodium_compat (see the
// VENDOR.md there for version and provenance). Its autoload defines the
// sodium_* functions as polyfills when the extension is absent, so the call
// below is unchanged either way and a server that later gains the real
// extension silently starts using it instead -- the native one is always
// preferred, this only fills a gap.
//
// Ed25519 in pure PHP costs ~25ms per verification against an interaction
// budget of 3000ms, so the cost is irrelevant here.
if (!function_exists('sodium_crypto_sign_verify_detached')) {
	$sk_compat = __DIR__ . '/lib/sodium_compat/autoload.php';
	if (is_file($sk_compat)) require_once $sk_compat;
}
if (!function_exists('sodium_crypto_sign_verify_detached')
	&& !class_exists('ParagonIE_Sodium_Compat')) {
	// Fail closed, and say why: the symptom at the Discord end ("endpoint
	// could not be validated") gives no hint that this is the cause.
	sk_reject('no Ed25519 available -- ext/sodium missing and lib/sodium_compat not found');
}
if ($signature === '' || $timestamp === '') {
	sk_reject('missing signature headers');
}
if (empty($discord_public_key)) {
	sk_reject('$discord_public_key is not set in credentials/webhooks_credentials.php');
}

$sig_bin = @hex2bin($signature);
$key_bin = @hex2bin($discord_public_key);
if ($sig_bin === false || $key_bin === false) {
	sk_reject('signature or public key is not valid hex');
}
// The two implementations disagree about how to report a bad signature, and
// the difference is not cosmetic. ext/sodium RETURNS false; sodium_compat
// THROWS a SodiumException when the signature isn't a valid curve point --
// which is exactly what a forged one usually is. Uncaught, that's a 500, and
// Discord refuses to accept an endpoint URL that answers its deliberately
// invalid probe with anything but 401. So the gate would simply never go
// live, having passed every test on a machine that has the extension.
//
// Any failure to verify, by either route, is the same answer: 401.
$sk_signed_ok = false;
try {
	$sk_signed_ok = function_exists('sodium_crypto_sign_verify_detached')
		? sodium_crypto_sign_verify_detached($sig_bin, $timestamp . $raw, $key_bin)
		: ParagonIE_Sodium_Compat::crypto_sign_verify_detached($sig_bin, $timestamp . $raw, $key_bin);
} catch (Throwable $e) {
	sk_reject('signature rejected (' . get_class($e) . ': ' . $e->getMessage() . ')');
}
if (!$sk_signed_ok) {
	sk_reject('signature did not verify');
}

// ---------------------------------------------------------------------------
// 2. Respond.
// ---------------------------------------------------------------------------
$data = json_decode($raw, true);
if (!is_array($data)) { http_response_code(400); exit; }

function sk_respond(array $payload) {
	header('Content-Type: application/json');
	echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
	exit;
}
/** Replace the ephemeral message in place, dropping its buttons. */
function sk_replace($content) {
	sk_respond(['type' => 7, 'data' => [
		'content' => $content, 'flags' => SK_EPHEMERAL, 'components' => [],
	]]);
}

$type = $data['type'] ?? 0;

// PING. Discord sends this to check the endpoint is alive, including during
// setup. Must answer PONG.
if ($type === 1) {
	sk_respond(['type' => 1]);
}

// Config has to be complete before we act on a click. Checked AFTER the PING
// reply so a half-configured endpoint can still be saved in the Developer
// Portal -- you can verify signatures work before the role ids exist.
$sk_missing = sk_config_missing();
if ($sk_missing) {
	error_log('discord-interactions: not configured -- missing ' . implode(', ', $sk_missing));
	if (function_exists('alertAdmin')) {
		alertAdmin(
			'Discord rules gate is not configured',
			"Someone pressed the rules button but these are unset in `credentials/webhooks_credentials.php`:\n"
				. '`' . implode('`, `', $sk_missing) . '`' . "\n\nNobody can pass the gate until they are set.",
			'rulesConfig|' . implode(',', $sk_missing)
		);
	}
	sk_respond(['type' => 4, 'data' => [
		'content' => 'The rules gate is not set up yet. A moderator has been notified.',
		'flags'   => SK_EPHEMERAL,
	]]);
}

// Anything that isn't a button click is not ours.
if ($type !== 3) {
	sk_respond(['type' => 4, 'data' => ['content' => 'Unsupported interaction.', 'flags' => SK_EPHEMERAL]]);
}

$custom_id   = $data['data']['custom_id'] ?? '';
$member      = $data['member'] ?? [];
$user_id     = $member['user']['id'] ?? '';
$user_roles  = $member['roles'] ?? [];

if ($user_id === '') {
	// A guild interaction always carries member.user.id. No member object
	// means this was clicked in a DM, where there is no role to grant.
	sk_respond(['type' => 4, 'data' => [
		'content' => 'This only works inside the server.', 'flags' => SK_EPHEMERAL,
	]]);
}

// The gate described in discord-rules-lib.php: only Visitors take the quiz,
// so an existing member who clicks the button out of curiosity can never be
// kicked by it. Checked before any Discord call.
if (SK_REQUIRE_VISITOR && !in_array($role_visitor, $user_roles, true)) {
	sk_respond(['type' => 4, 'data' => [
		'content' => "You're already verified — nothing to do here.",
		'flags'   => SK_EPHEMERAL,
	]]);
}

// --- The button on the rules message: ask question 1 -----------------------
if ($custom_id === SK_BTN_START) {
	sk_respond(['type' => 4, 'data' => sk_question_payload(0)]);
}

// --- An answer: "sk_a:<question index>:<answer key>" ------------------------
if (strpos($custom_id, 'sk_a:') === 0) {
	$parts = explode(':', $custom_id);
	if (count($parts) !== 3) { sk_replace('Something went wrong. Please try again.'); }

	$index  = (int)$parts[1];
	$answer = $parts[2];
	if (!isset($SK_QUESTIONS[$index])) { sk_replace('Something went wrong. Please try again.'); }

	// --- Wrong answer -----------------------------------------------------
	if ($answer !== $SK_QUESTIONS[$index]['correct']) {
		if (!SK_KICK_ON_WRONG) {
			sk_replace('That was not correct, so no role was granted.');
		}

		// Answer the interaction BEFORE kicking. Once the member is gone
		// Discord may not deliver a response to them at all, and an
		// unanswered interaction shows "application did not respond" to
		// anyone watching. Buffer the reply, flush it, then kick.
		$out = json_encode(['type' => 7, 'data' => [
			'content'    => 'That was not correct.',
			'flags'      => SK_EPHEMERAL,
			'components' => [],
		]], JSON_UNESCAPED_SLASHES);
		header('Content-Type: application/json');
		header('Content-Length: ' . strlen($out));
		echo $out;
		@ob_flush(); @flush();

		$res = sk_discord_api('DELETE', '/guilds/' . $discord_guild_id . '/members/' . $user_id);
		if (!$res['ok'] && function_exists('alertAdmin')) {
			// Same reasoning as the role alerts: a gate that silently stops
			// gating looks identical to a gate nobody is failing.
			$hint = 'Check that the bot has Kick Members, and that its role sits ABOVE the target member.';
			if ($res['status'] == 401) $hint = 'Discord rejected the bot token (401).';
			alertAdmin(
				'Discord rules gate: kick failed',
				"Wrong answer from user `" . $user_id . "` but the kick returned **HTTP " . $res['status'] . "**.\n\n"
					. $hint . "\n\nThey are still in the server, still holding Visitor.",
				'rulesKick|' . $res['status']
			);
		}
		exit;
	}

	// --- Correct, but more questions to go --------------------------------
	if (isset($SK_QUESTIONS[$index + 1])) {
		sk_respond(['type' => 7, 'data' => sk_question_payload($index + 1)]);
	}

	// --- Correct, and that was the last one -------------------------------
	// Add first, then remove. If the second call fails they're left holding
	// both roles, which is visibly odd but harmless; the other order would
	// strip Visitor and leave them holding nothing at all.
	$granted = assignRole($user_id, $role_candidate, '', $discord_guild_id);
	if (!$granted) {
		// assignRole() has already logged and alerted with the specific
		// cause. Don't remove Visitor -- leaving them exactly as they were
		// means retrying the button is all it takes once it's fixed.
		sk_replace("Something went wrong granting your role. A moderator has been notified — please try again shortly.");
	}
	assignRole($user_id, $role_visitor, 'delete', $discord_guild_id);

	// No cleanup step here, unlike BotGhost. Every message in this flow is
	// ephemeral: nobody but the clicker ever saw the questions or this
	// welcome, so there is no channel clutter to delete afterwards.
	sk_replace($SK_WELCOME);
}

sk_respond(['type' => 4, 'data' => ['content' => 'Unknown action.', 'flags' => SK_EPHEMERAL]]);
