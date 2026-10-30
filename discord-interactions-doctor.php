<?php
/**
 * discord-interactions-doctor.php — why the rules gate is not answering.
 * CLI only.
 *
 *     php discord-interactions-doctor.php
 *
 * Checks everything the endpoint needs, ON THE SERVER, where the answers
 * actually live. Prints no secret: the public key is reported by length and
 * shape, never by value.
 *
 * WHAT THIS IS FOR. The endpoint answers an unsigned probe in 200ms and logs
 * a reason for every rejection, so an outside measurement says it is healthy
 * while a member still sees "Skull Bot didn't respond in time". The gap
 * between those two is this list, and most of it cannot be seen from outside.
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
error_reporting(E_ALL & ~E_DEPRECATED);

$ok = 0; $bad = 0;
function say($s = '') { echo $s . "\n"; }
function good($s) { global $ok;  $ok++;  say('  ok    ' . $s); }
function fail($s) { global $bad; $bad++; say('  BAD   ' . $s); }

say('discord-interactions doctor');
say('');

// --- 1. credentials -------------------------------------------------------
say('credentials');
$credFile = __DIR__ . '/credentials/webhooks_credentials.php';
if (!is_file($credFile)) {
	fail('credentials/webhooks_credentials.php is missing');
} else {
	good('credentials/webhooks_credentials.php is present');
}
require_once __DIR__ . '/discord-rules-lib.php';

/*
 * REPORTED BY SHAPE, NEVER BY VALUE. A public key is not a secret, but this
 * output gets pasted into chats and tickets, and the habit is worth keeping.
 */
if (empty($discord_public_key)) {
	fail('$discord_public_key is not set');
} else {
	$k = (string)$discord_public_key;
	$len = strlen($k);
	say('        public key: ' . $len . ' chars, first 4 "' . substr($k, 0, 4)
	    . '", last 4 "' . substr($k, -4) . '"');
	if ($len !== 64) {
		fail('a Discord public key is 64 hex characters, this is ' . $len
		     . ($len === 0 ? '' : ' -- is this the bot TOKEN or the client secret by mistake?'));
	} else {
		good('64 characters');
	}
	if ($k !== trim($k)) {
		fail('it has leading or trailing whitespace, which hex2bin rejects');
	}
	if (!preg_match('/^[0-9a-fA-F]*$/', $k)) {
		fail('it contains non-hex characters');
	} elseif ($len === 64) {
		good('all hex');
	}
}
foreach (array('bot_token' => 'the bot token', 'role_visitor' => 'the Visitor role id',
               'role_candidate' => 'the Candidate role id') as $var => $what) {
	if (empty($GLOBALS[$var])) fail($what . ' ($' . $var . ') is not set');
	else good($what . ' is set');
}

// --- 2. crypto ------------------------------------------------------------
say('');
say('Ed25519');
$native = function_exists('sodium_crypto_sign_verify_detached');
say('        ext/sodium: ' . ($native ? 'present (fast path)' : 'ABSENT -- using lib/sodium_compat'));
if (!$native) {
	$compat = __DIR__ . '/lib/sodium_compat/autoload.php';
	if (!is_file($compat)) {
		fail('lib/sodium_compat/autoload.php is missing, so nothing can verify a signature');
	} else {
		require_once $compat;
		good('lib/sodium_compat loaded');
	}
}

/*
 * THE ROUND TRIP. Sign something with a key we generate here and verify it
 * through the SAME call the endpoint makes. This proves the crypto on this
 * machine works and that the concatenation order is right -- which is the
 * only part of "signature did not verify" that is ours to get wrong. It says
 * nothing about whether the configured key is the correct one; no test can,
 * short of Discord signing something.
 */
$verify = function ($sig, $msg, $pk) {
	return function_exists('sodium_crypto_sign_verify_detached')
		? sodium_crypto_sign_verify_detached($sig, $msg, $pk)
		: ParagonIE_Sodium_Compat::crypto_sign_verify_detached($sig, $msg, $pk);
};
try {
	if (function_exists('sodium_crypto_sign_keypair')) {
		$kp = sodium_crypto_sign_keypair();
		$sk = sodium_crypto_sign_secretkey($kp);
		$pk = sodium_crypto_sign_publickey($kp);
	} else {
		$kp = ParagonIE_Sodium_Compat::crypto_sign_keypair();
		$sk = ParagonIE_Sodium_Compat::crypto_sign_secretkey($kp);
		$pk = ParagonIE_Sodium_Compat::crypto_sign_publickey($kp);
	}
	$ts   = (string)time();
	$body = '{"type":1}';
	$sig  = function_exists('sodium_crypto_sign_detached')
		? sodium_crypto_sign_detached($ts . $body, $sk)
		: ParagonIE_Sodium_Compat::crypto_sign_detached($ts . $body, $sk);

	$t = microtime(true);
	$pass = $verify($sig, $ts . $body, $pk);
	$ms   = (microtime(true) - $t) * 1000;

	if ($pass) good(sprintf('a correctly signed request verifies (%.0fms)', $ms));
	else       fail('a correctly signed request does NOT verify -- the crypto on this box is broken');
	if (!$native && $ms > 500) {
		fail(sprintf('verification takes %.0fms, which is slow enough to matter against '
		           . "Discord's 3000ms budget", $ms));
	}
	// And a tampered body must fail, or the gate is not a gate.
	if ($verify($sig, $ts . '{"type":2}', $pk)) {
		fail('a TAMPERED body still verifies -- this endpoint is not authenticating anything');
	} else {
		good('a tampered body is rejected');
	}
} catch (Throwable $e) {
	fail('crypto self-test threw ' . get_class($e) . ': ' . $e->getMessage());
}

// --- 3. is the configured key even a valid point? -------------------------
say('');
say('the configured key');
if (!empty($discord_public_key) && strlen((string)$discord_public_key) === 64) {
	$kb = @hex2bin((string)$discord_public_key);
	if ($kb === false)      fail('hex2bin() refuses it');
	elseif (strlen($kb) !== 32) fail('decodes to ' . strlen($kb) . ' bytes, not 32');
	else {
		good('decodes to 32 bytes');
		/*
		 * A WELL-FORMED KEY THAT IS THE WRONG KEY LOOKS IDENTICAL HERE, and
		 * that is the whole difficulty: only Discord can sign something this
		 * key will verify. What CAN be told apart is the two rejection
		 * messages in the log, so that is what the advice below leans on.
		 */
		say('        (a wrong-but-valid key is indistinguishable from the right one here --');
		say('         only Discord can prove it, by the endpoint URL saving)');
	}
}

// --- 4. what the log is saying --------------------------------------------
say('');
say('recent rejections in error_log');
$log = __DIR__ . '/error_log';
if (!is_file($log)) {
	say('        no error_log beside this script; try the account-wide one');
} else {
	$lines = @file($log, FILE_IGNORE_NEW_LINES) ?: array();
	$hits  = array_values(array_filter($lines, function ($l) {
		return strpos($l, 'discord-interactions') !== false;
	}));
	if (!$hits) {
		say('        none ever. Discord has never reached this file, which means the');
		say('        Interactions Endpoint URL is NOT saved in the Developer Portal.');
	} else {
		$tail = array_slice($hits, -8);
		foreach ($tail as $l) say('        ' . $l);
		$curve = 0; $mismatch = 0;
		foreach ($hits as $l) {
			if (strpos($l, 'S >= L') !== false) $curve++;
			elseif (strpos($l, 'did not verify') !== false) $mismatch++;
		}
		say('');
		say('        ' . $mismatch . ' x "did not verify"  -- a well-formed signature checked');
		say('                              against a key that does not match it. Real');
		say('                              Discord traffic, or anyone replaying it.');
		say('        ' . $curve . ' x "S >= L"          -- not a valid curve point at all.');
		say('                              Random bytes: a port scanner, or a probe.');
	}
}

// --- 5. the thing no local check can see ----------------------------------
say('');
say('the one thing this script cannot check');
say('  Whether the Interactions Endpoint URL is saved in the Developer Portal.');
say('  Discord fires a signed test request when you press Save and REFUSES to');
say('  save unless it gets a valid answer -- so if the field is empty or will');
say('  not stick, interactions are still going over the Gateway to BotGhost,');
say('  which has never heard of this gate\'s buttons and answers nothing.');
say('  That looks exactly like "didn\'t respond in time", with NOTHING in this');
say('  log, because the request never arrives.');
say('');
say('  URL:  https://skulliance.io/staking/discord-interactions.php');
say('  Set it, press Save, then run this again and look at the log section:');
say('  a fresh line at the moment you pressed Save means the key is wrong;');
say('  no new line and a saved URL means it worked.');

say('');
say($bad ? "$bad problem(s), $ok ok" : "nothing wrong locally ($ok checks)");
exit($bad ? 1 : 0);
