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

/*
 * ---------------------------------------------------------------------------
 * A PHP STARTUP WARNING IS PRINTED BEFORE THIS FILE EXISTS.
 *
 * The two ini_set() lines above cannot stop one. "PHP Startup" errors are
 * emitted while PHP initialises its modules, which is before the script is
 * even parsed -- so with display_errors on in php.ini the warning is written
 * ahead of our JSON and the response Discord receives is:
 *
 *     <br /><b>Warning</b>: PHP Startup: ... <br />{"type":4,...}
 *
 * which is not JSON. Discord reports that to the member as "Skull Bot didn't
 * respond in time", which is why this looked like a timeout for so long while
 * every measurement said the endpoint was fast.
 *
 * THE ONE THIS SERVER EMITS is `Invalid date.timezone value ''`, logged at
 * 18:10:16 and 18:11:10 on 2026-10-30, the exact minutes a member was failing
 * to get in. The real fix is date.timezone in php.ini (cPanel's MultiPHP INI
 * Editor) -- php.ini is not in this repository and a version switch replaces
 * it, so it cannot be fixed from here and must not be relied on either.
 *
 * AND IT IS INTERMITTENT, which is what made it so hard to see: a startup
 * warning fires when a new PHP worker starts, not on every request. The first
 * click after a worker spins up is corrupted; the retry lands on the warm
 * worker and works. "I had to click a couple of times" is that, exactly.
 *
 * So the buffer is taken over here and discarded at the point of reply. Any
 * output already produced is captured where it can be thrown away instead of
 * sent -- and LOGGED, because output arriving before our own first statement
 * is worth knowing about rather than silently swallowing.
 * ---------------------------------------------------------------------------
 */
while (ob_get_level() > 0) {
	/* Anything PHP buffered before us -- a startup warning lands here when
	   output_buffering is on, which is the usual LiteSpeed default. */
	$sk_pre = ob_get_clean();
	if ($sk_pre !== false && trim($sk_pre) !== '') {
		/* Carried into the interaction log as well: a byte count here is the
		   fingerprint of a startup warning, and the log is the surface the
		   owner can actually reach from a phone. */
		$GLOBALS['sk_ilog_ctx']['stray'] = strlen($sk_pre) . 'b-before-script';
		error_log('discord-interactions: DISCARDED ' . strlen($sk_pre)
			. ' bytes of output produced before the script ran -- this would have '
			. 'corrupted the JSON. Fix date.timezone in php.ini. First 200: '
			. substr(str_replace("\n", ' ', $sk_pre), 0, 200));
	}
}
ob_start();

/*
 * ---------------------------------------------------------------------------
 * WHERE THE THREE SECONDS WENT.
 *
 * Discord gives an interaction a 3000ms budget and shows the member "Skull Bot
 * didn't respond in time" past it -- reported on the FIRST click, the one that
 * makes no Discord API call at all and simply renders a question.
 *
 * Measured on a dev machine, that path is about 25ms of work end to end: 2ms
 * of includes (no database connection anywhere in the chain -- checked) and
 * ~20ms for sodium_compat to lazy-load its 15 files and verify the Ed25519
 * signature -- which was this server's only option until ext/sodium was
 * enabled on 2026-10-31; native is 0.023ms. Even ten times slower on a
 * loaded shared host that is ~250ms.
 * So the work is not the problem and there is nothing here worth optimising.
 *
 * Which leaves the host, and this logs enough to tell the two apart.
 * REQUEST_TIME_FLOAT is when PHP received the request, so:
 *
 *   total LARGE   -> PHP itself is slow: CPU throttling, IO wait, opcache
 *                    cold. The marks say which stage.
 *   total SMALL   -> the time went BEFORE PHP ever started, in Apache
 *     and a timeout      accepting the connection -- which on CloudLinux is
 *     anyway            the LVE entry-process or concurrent-connection cap,
 *                       visible in cPanel under Resource Usage. Nothing in
 *                       this file can fix that and no amount of reading it
 *                       will show it.
 *
 * Only logged when it is actually slow, so a healthy endpoint stays silent.
 * NEVER echoed: display_errors is off above for exactly this reason, and a
 * byte of timing in the body corrupts the JSON and breaks the interaction.
 * ---------------------------------------------------------------------------
 */
if (!defined('SK_SLOW_MS')) define('SK_SLOW_MS', 1200);
$sk_marks = array();
function sk_mark($stage) {
	global $sk_marks;
	$t0 = isset($_SERVER['REQUEST_TIME_FLOAT']) ? (float)$_SERVER['REQUEST_TIME_FLOAT'] : null;
	if ($t0 === null) return;
	$sk_marks[] = $stage . '=' . round((microtime(true) - $t0) * 1000) . 'ms';
}
require_once __DIR__ . '/lib/discord-interaction-log.php';

/*
 * WHAT HAPPENED, RECORDED FOR EVERY INTERACTION AND NOT ONLY THE SLOW ONES.
 *
 * The old handler logged only past SK_SLOW_MS, which cannot tell the two
 * causes of "the application didn't respond in time" apart: a silent log
 * means either a healthy gate or a gate Discord is NEVER CALLING -- because
 * the button belongs to another application, or the endpoint URL is not
 * saved -- and for most of a day it was read as the first while it was the
 * second. A line per interaction makes "did the click even arrive" a
 * question somebody can answer by pressing the button and reloading a page.
 *
 * $sk_outcome is set at each exit so the line says which branch answered.
 * In a shutdown function rather than at the reply, so a fatal, a kick path
 * that exits by hand, and an ordinary reply all leave the same record.
 *
 * LAG IS MEASURED FROM DISCORD'S OWN CLOCK, not ours: the interaction id is
 * a snowflake carrying the millisecond Discord created it, so lag is time
 * spent before PHP ever saw the request -- transit plus whatever the host
 * did with it -- and `work` is the part that is ours. Which half is eating
 * the 3000ms decides who can fix it.
 */
$sk_outcome = 'started';
$sk_ilog_ctx = array();
function sk_out($what) { global $sk_outcome; $sk_outcome = $what; }

register_shutdown_function(function () {
	global $sk_marks, $sk_outcome, $sk_ilog_ctx;
	$t0 = isset($_SERVER['REQUEST_TIME_FLOAT']) ? (float)$_SERVER['REQUEST_TIME_FLOAT'] : null;
	if ($t0 === null) return;
	$ms = (microtime(true) - $t0) * 1000;

	/* A fatal is the one outcome nothing else can report, so it is read off
	   the error itself rather than from a flag somebody has to remember. */
	$err = error_get_last();
	if ($err && ($err['type'] & (E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR))) {
		$sk_outcome = 'fatal';
		$sk_ilog_ctx['note'] = $err['message'] . ' (' . basename($err['file']) . ':' . $err['line'] . ')';
	}

	$lag = null;
	if (!empty($sk_ilog_ctx['iid'])) {
		$made = sk_ilog_snowflake_ms($sk_ilog_ctx['iid']);
		if ($made) $lag = max(0, (int)round($t0 * 1000 - $made));
	}

	/*
	 * ONLY SIGNED TRAFFIC IS WRITTEN TO THE FILE. The endpoint is a public
	 * URL and a port scanner that finds it would otherwise fill the log with
	 * its own noise, pushing out the lines somebody needs. Unsigned hits are
	 * still reported, by sk_reject(), into the PHP error log.
	 */
	if (!empty($_SERVER['HTTP_X_SIGNATURE_ED25519'])) {
		sk_ilog(array(
			'outcome' => $sk_outcome,
			/*
			 * THE INTERACTION ID, WHICH IS HOW A RETRY IS TOLD FROM A CLICK.
			 *
			 * Discord re-delivers an interaction it does not consider
			 * answered, and the re-delivery carries the SAME id. Five lines
			 * with five ids are five people pressing a button; five lines
			 * with one id are one press that Discord would not accept, and
			 * those are opposite problems. The lag says it too -- it is
			 * measured from the id's own creation time, so a retry's lag
			 * grows by the retry interval each time -- but the id says it
			 * outright.
			 */
			'iid'     => isset($sk_ilog_ctx['iid']) ? $sk_ilog_ctx['iid'] : null,
			'type'    => isset($sk_ilog_ctx['type'])   ? $sk_ilog_ctx['type']   : null,
			'custom'  => isset($sk_ilog_ctx['custom']) ? $sk_ilog_ctx['custom'] : null,
			'user'    => isset($sk_ilog_ctx['user'])   ? $sk_ilog_ctx['user']   : null,
			'lag'     => $lag === null ? null : $lag . 'ms',
			'work'    => round($ms) . 'ms',
			'stray'   => isset($sk_ilog_ctx['stray']) ? $sk_ilog_ctx['stray'] : null,
			'marks'   => $sk_marks ? implode(' ', $sk_marks) : null,
			'note'    => isset($sk_ilog_ctx['note']) ? $sk_ilog_ctx['note'] : null,
		));
	}

	/*
	 * -------------------------------------------------------------------
	 * THE LAST SEGMENT NOTHING MEASURES.
	 *
	 * LiteSpeed holds the response until the script TERMINATES -- recorded
	 * in skullpaper/MAINTENANCE.md from when fastcgi_finish_request failed
	 * to defer work past the response on this host. So everything in this
	 * shutdown handler happens between our reply and the wire.
	 *
	 * And `$ms` above is sampled at the TOP of this function, before the
	 * log write, its LOCK_EX, and the rotation check. So the 3ms this
	 * endpoint reports is honest about the work and silent about the tail,
	 * which is exactly the shape of thing that hides for a month. On
	 * CloudLinux an account that is over its IO allowance has small writes
	 * block, and nothing above would show it.
	 *
	 * Measured rather than assumed, and only reported when it is big
	 * enough to matter against a 3000ms budget.
	 * -------------------------------------------------------------------
	 */
	$tail = ((microtime(true) - $t0) * 1000) - $ms;
	if ($tail > 150) {
		error_log(sprintf(
			'discord-interactions: %.0fms of SHUTDOWN work AFTER the reply was written '
			. '(interaction log write and rotate). LiteSpeed does not release the response '
			. 'until the script ends, so Discord waited %.0fms, not %.0fms.',
			$tail, $ms + $tail, $ms));
	}

	if ($ms < SK_SLOW_MS) return;
	error_log(sprintf(
		'discord-interactions SLOW %.0fms (Discord gives 3000ms) outcome=%s lag=%s marks: %s',
		$ms, $sk_outcome, $lag === null ? '?' : $lag . 'ms',
		$sk_marks ? implode(' ', $sk_marks) : 'none'));
});

require_once __DIR__ . '/discord-rules-lib.php';
require_once __DIR__ . '/role.php';       // assignRole()
sk_mark('includes');

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
	/* Same reasoning as sk_respond(): Discord checks for a clean 401 when the
	   endpoint URL is saved, and a warning in front of it fails that too. */
	sk_out('rejected');
	$GLOBALS['sk_ilog_ctx']['note'] = $why;
	while (ob_get_level() > 0) ob_end_clean();
	http_response_code(401);
	error_log('discord-interactions: rejected -- ' . $why);
	/* Declared length here too: this is the reply Discord reads when the
	   endpoint URL is saved, and a reply it cannot see the end of is a
	   reply it will not accept. */
	$out = 'invalid request signature';
	header('Content-Type: text/plain');
	header('Content-Length: ' . strlen($out));
	echo $out;
	@ob_flush();
	@flush();
	exit;
}

// EITHER IMPLEMENTATION, AND THE CODE DOES NOT CARE WHICH.
//
// For a year this server had no ext/sodium and the vendored pure-PHP
// lib/sodium_compat was the only option (see VENDOR.md there for version and
// provenance). It was switched on in the PHP Selector on 2026-10-31, so the
// native path is live -- and the fallback below is what keeps that from
// being a decision anybody has to remember, because Selector extensions are
// per PHP version and a version switch resets them.
//
// sodium_compat's autoload defines the sodium_* functions as polyfills only
// when the extension is absent, so the native one is always preferred and
// this only ever fills a gap.
//
// ext/sodium IS PRESENT NOW, as of 2026-10-31. It was ticked on in the
// CloudLinux PHP Selector's extension list -- a checkbox the earlier survey
// could not see, because that survey ran each alt-php BINARY and asked
// function_exists(), which reports what is ENABLED in a build's ini rather
// than what is available to enable. Native is 0.023ms against pure PHP's
// 21.3ms; both are irrelevant against 3000ms, and this is not what was
// making members wait.
//
// THE FALLBACK STAYS, AND IS NOT DEAD CODE. Extension choices in the
// Selector are PER PHP VERSION, exactly like date.timezone -- a version
// switch empties them, which is how the timezone bug arrived. The day that
// happens this file keeps working and the Front Door page's ext/sodium row
// turns amber.
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
sk_mark('verified');

// ---------------------------------------------------------------------------
// 2. Respond.
// ---------------------------------------------------------------------------
$data = json_decode($raw, true);
if (!is_array($data)) { sk_out('unreadable-body'); http_response_code(400); exit; }

/* The id is a snowflake, so this is also the only thing that can say how
   long the request spent getting here. Filled in before any branch runs, so
   even an outcome nobody anticipated is logged with its timings. */
$sk_ilog_ctx['iid']    = isset($data['id']) ? (string)$data['id'] : '';
$sk_ilog_ctx['type']   = isset($data['type']) ? (int)$data['type'] : 0;
$sk_ilog_ctx['custom'] = isset($data['data']['custom_id']) ? (string)$data['data']['custom_id'] : '';
$sk_ilog_ctx['user']   = isset($data['member']['user']['id'])
	? (string)$data['member']['user']['id']
	: (isset($data['user']['id']) ? (string)$data['user']['id'] : '');

function sk_respond(array $payload) {
	/*
	 * EVERY REPLY LEAVES FROM HERE, so this is the one place that can
	 * guarantee the body is nothing but JSON. A notice raised by any include
	 * between startup and now would otherwise ride along in front of it.
	 */
	$stray = '';
	while (ob_get_level() > 0) {
		$chunk = ob_get_clean();
		if ($chunk !== false) $stray = $chunk . $stray;
	}
	if (trim($stray) !== '') {
		$GLOBALS['sk_ilog_ctx']['stray'] = strlen($stray) . 'b-before-reply';
		error_log('discord-interactions: DISCARDED ' . strlen($stray)
			. ' bytes of stray output before the reply -- it would have corrupted '
			. 'the JSON. First 200: ' . substr(str_replace("\n", ' ', $stray), 0, 200));
	}
	/*
	 * -------------------------------------------------------------------
	 * CONTENT-LENGTH, AND IT IS NOT A FORMALITY.
	 *
	 * Measured 2026-10-31: seven presses in forty-six seconds, every one of
	 * them delivered to this endpoint and answered in 3ms with a lag under
	 * a quarter second -- and the member still saw "the application didn't
	 * respond in time" on most of them. The request arrived. The reply was
	 * written. Something between writing it and Discord considering it
	 * FINISHED was costing the three seconds.
	 *
	 * Without a declared length, PHP's output goes out chunked under
	 * HTTP/1.1 and the response is only complete when the terminating chunk
	 * arrives. A client holding a keep-alive connection has nothing else to
	 * tell it the body ended. That matches the symptom exactly, including
	 * the part nothing else explained: it clears up once a connection has
	 * carried one good response ("it's like it's primed"), and the first
	 * press after a quiet spell is the one that fails.
	 *
	 * The kick branch below has always set this, for the flush it does --
	 * so the one reply on this endpoint that was already explicit about its
	 * length is the one nobody ever reported failing.
	 *
	 * strlen(), NOT mb_strlen(). This is a byte count, and the payloads
	 * carry em dashes and emoji; a character count would declare the body
	 * shorter than it is and Discord would read a truncated, unparseable
	 * JSON document.
	 * -------------------------------------------------------------------
	 */
	$out = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
	if (!is_string($out) || $out === '') {
		/* An encode failure used to echo `false`, which is an EMPTY BODY --
		   a 200 with nothing in it, which Discord reports to the member as
		   a timeout. A wrong answer beats no answer here. */
		error_log('discord-interactions: json_encode failed -- ' . json_last_error_msg());
		$out = '{"type":4,"data":{"content":"Something went wrong. Please try again.","flags":64}}';
	}
	header('Content-Type: application/json');
	header('Content-Length: ' . strlen($out));
	echo $out;
	/* Pushed out now rather than at script end, so the few milliseconds of
	   shutdown logging happen AFTER Discord has the whole body. */
	@ob_flush();
	@flush();
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
	sk_out('ping');
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
	sk_out('not-configured');
	sk_respond(['type' => 4, 'data' => [
		'content' => 'The rules gate is not set up yet. A moderator has been notified.',
		'flags'   => SK_EPHEMERAL,
	]]);
}

// Anything that isn't a button click is not ours.
if ($type !== 3) {
	sk_out('not-a-button');
	sk_respond(['type' => 4, 'data' => ['content' => 'Unsupported interaction.', 'flags' => SK_EPHEMERAL]]);
}

$custom_id   = $data['data']['custom_id'] ?? '';
$member      = $data['member'] ?? [];
$user_id     = $member['user']['id'] ?? '';
$user_roles  = $member['roles'] ?? [];

if ($user_id === '') {
	// A guild interaction always carries member.user.id. No member object
	// means this was clicked in a DM, where there is no role to grant.
	sk_out('no-member');
	sk_respond(['type' => 4, 'data' => [
		'content' => 'This only works inside the server.', 'flags' => SK_EPHEMERAL,
	]]);
}

// The gate described in discord-rules-lib.php: only Visitors take the quiz,
// so an existing member who clicks the button out of curiosity can never be
// kicked by it. Checked before any Discord call.
if (SK_REQUIRE_VISITOR && !in_array($role_visitor, $user_roles, true)) {
	sk_out('already-verified');
	sk_respond(['type' => 4, 'data' => [
		'content' => "You're already verified — nothing to do here.",
		'flags'   => SK_EPHEMERAL,
	]]);
}

// --- The button on the rules message: ask which chain ----------------------
// SK_BTN_START is unchanged, so the posted rules message does not need
// reposting when the questions behind it change.
if ($custom_id === SK_BTN_START) {
	sk_mark('picker');
	sk_out('chain-picker');
	sk_respond(['type' => 4, 'data' => sk_chain_payload()]);
}

// --- A chain pick: "sk_c:<chain>" ------------------------------------------
// There is no wrong answer here, so nothing in this branch can kick.
if (strpos($custom_id, 'sk_c:') === 0) {
	$chain = substr($custom_id, 5);
	if (!isset($SK_CHAINS[$chain])) { sk_out('unknown-chain'); sk_replace('Something went wrong. Please try again.'); }
	sk_out('question-1');
	sk_respond(['type' => 7, 'data' => sk_question_payload($chain, 0)]);
}

// --- An answer: "sk_a:<chain>:<question index>:<answer key>" ---------------
if (strpos($custom_id, 'sk_a:') === 0) {
	$parts = explode(':', $custom_id);

	/*
	 * A BUTTON FROM BEFORE THE CHAIN PICK EXISTED. The old id was
	 * "sk_a:<index>:<key>", three parts. Every message in this flow is
	 * ephemeral and ephemeral messages persist, so somebody part-way through
	 * the quiz when this deployed still has the old buttons in front of them.
	 * Send them back to the start rather than grading a question that no
	 * longer exists -- and above all do not fall through to the wrong-answer
	 * branch, which would kick them for our deploy timing.
	 */
	if (count($parts) === 3) {
		sk_out('legacy-button-restart');
		sk_respond(['type' => 7, 'data' => sk_chain_payload()]);
	}
	if (count($parts) !== 4) { sk_out('bad-custom-id'); sk_replace('Something went wrong. Please try again.'); }

	$chain  = $parts[1];
	$index  = (int)$parts[2];
	$answer = $parts[3];
	if (!isset($SK_CHAINS[$chain]['questions'][$index])) {
		sk_out('unknown-question');
		sk_replace('Something went wrong. Please try again.');
	}

	// --- Wrong answer -----------------------------------------------------
	if (!sk_is_correct($chain, $index, $answer)) {
		if (!SK_KICK_ON_WRONG) {
			sk_out('wrong-not-kicked');
			sk_replace('That was not correct, so no role was granted.');
		}
		sk_out('wrong-kicked');

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
		if (!$res['ok']) {
			/* The member is still in the server holding Visitor, which from
			   the outside looks exactly like a gate nobody is failing. */
			sk_out('wrong-kick-failed');
			$sk_ilog_ctx['note'] = 'kick HTTP ' . $res['status'];
		}
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
	if (isset($SK_CHAINS[$chain]['questions'][$index + 1])) {
		sk_out('question-' . ($index + 2));
		sk_respond(['type' => 7, 'data' => sk_question_payload($chain, $index + 1)]);
	}

	// --- Correct, and that was the last one -------------------------------
	// Add first, then remove. If the second call fails they're left holding
	// both roles, which is visibly odd but harmless; the other order would
	// strip Visitor and leave them holding nothing at all.
	sk_mark('grading-done');
	$granted = assignRole($user_id, $role_candidate, '', $discord_guild_id);
	sk_mark('role-added');
	if (!$granted) {
		sk_out('role-grant-failed');
		// assignRole() has already logged and alerted with the specific
		// cause. Don't remove Visitor -- leaving them exactly as they were
		// means retrying the button is all it takes once it's fixed.
		sk_replace("Something went wrong granting your role. A moderator has been notified — please try again shortly.");
	}
	assignRole($user_id, $role_visitor, 'delete', $discord_guild_id);
	sk_mark('visitor-removed');
	sk_out('passed');

	// No cleanup step here, unlike BotGhost. Every message in this flow is
	// ephemeral: nobody but the clicker ever saw the questions or this
	// welcome, so there is no channel clutter to delete afterwards.
	sk_replace($SK_WELCOME);
}

sk_out('unknown-action');
sk_respond(['type' => 4, 'data' => ['content' => 'Unknown action.', 'flags' => SK_EPHEMERAL]]);
