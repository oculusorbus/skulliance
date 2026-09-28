<?php
/**
 * xaman.php — Xaman (formerly XUMM) sign-in for XRPL wallets.
 *
 * Proves somebody controls an XRPL account, and nothing else. See
 * multichain.md §4.
 *
 * WHY THIS IS NOT LIKE CIP-30. Cardano's connector is a browser extension that
 * hands wallet.js a stake address; the binding is "the browser said so" and no
 * signature is ever checked. Xaman is a phone app with a backend API: we create
 * a sign request, the user approves it in the app, and we read the result
 * server-side. It is a genuinely signed proof, so the XRPL path is STRONGER
 * than the Cardano one rather than weaker.
 *
 * SignIn is a Xaman pseudo transaction: signature-only, never submitted to the
 * ledger, no fee, and it works on an account holding no XRP at all.
 *
 * CREDENTIALS live in credentials/ beside $blockfrost_project_id, which is not
 * in this repo. Add to credentials/db_credentials.php:
 *
 *     $xaman_api_key    = "...";   // from https://apps.xaman.dev
 *     $xaman_api_secret = "...";
 *
 * The SECRET NEVER REACHES THE BROWSER. Anyone holding it can create sign
 * requests that look like they came from Skulliance.
 *
 * RATE LIMIT SHAPES THE DESIGN. Xaman allows roughly 60-200 calls a minute for
 * free. Polling a pending sign-in every two seconds would be ~30 calls per
 * sign-in, so five people signing in at once would throttle the whole
 * platform -- and launch day is exactly when fifty people try. Instead the
 * BROWSER holds refs.websocket_status (wss://xumm.app/sign/<uuid>, which needs
 * no secret), and the server makes exactly TWO calls per link: one to create,
 * one to read the result. See multichain.md §4d.
 */

/* Defined here as well as in verify-xrpl.php: both are independent entry
   points -- the endpoints include this one, the cron includes that one -- and
   an undefined constant is a fatal in PHP 8. Both guards, so including both is
   harmless. */
if (!defined('XRPL_CHAIN_ID')) define('XRPL_CHAIN_ID', 2);
if (!defined('XAMAN_API'))     define('XAMAN_API', 'https://xumm.app/api/v1/platform');
if (!defined('XAMAN_TIMEOUT')) define('XAMAN_TIMEOUT', 15);
/* How long a pending sign-in stays claimable by the session that started it.
   Xaman's own expiry is a SCAN deadline, not a resolution deadline -- a payload
   the user already opened does not die when the QR timer runs out -- so this is
   deliberately generous. */
if (!defined('XAMAN_PENDING_S')) define('XAMAN_PENDING_S', 900);

/**
 * One call to Xaman. $fetch is injectable so every path here can be tested
 * with no network and no credentials -- see xaman-harness.php.
 *
 * Returns the decoded array, or null. Never throws at the caller.
 */
function xaman_call($path, $post = null, $fetch = null) {
	global $xaman_api_key, $xaman_api_secret;
	if ($fetch !== null) return call_user_func($fetch, $path, $post);

	if (empty($xaman_api_key) || empty($xaman_api_secret)) {
		error_log('xaman: no API credentials configured');
		return null;
	}
	$ch = curl_init(XAMAN_API . $path);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($ch, CURLOPT_TIMEOUT, XAMAN_TIMEOUT);
	curl_setopt($ch, CURLOPT_HTTPHEADER, array(
		'Content-Type: application/json',
		'x-api-key: ' . $xaman_api_key,
		'x-api-secret: ' . $xaman_api_secret,
	));
	if ($post !== null) {
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
	}
	$body = curl_exec($ch);
	$code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
	/* No curl_close(): it has had no effect since PHP 8.0 and is deprecated
	   from 8.5, and the notice would print AHEAD of this endpoint's JSON and
	   break the client's parse. The handle is freed when it goes out of
	   scope. Same trap dhc-json.php already documents. */
	if ($body === false || $code >= 400) {
		error_log('xaman: ' . $path . ' returned ' . $code);
		return null;
	}
	$json = json_decode($body, true);
	return is_array($json) ? $json : null;
}

/**
 * Start a sign-in. Returns what the browser needs and nothing it should not
 * have: a uuid, a QR to show on desktop, a deeplink for a phone, and the
 * websocket it will listen on.
 */
function xaman_start($fetch = null) {
	$res = xaman_call('/payload',
		json_encode(array('txjson' => array('TransactionType' => 'SignIn'))), $fetch);
	if (!$res || empty($res['uuid'])) return null;
	return array(
		'uuid'      => $res['uuid'],
		'qr'        => isset($res['refs']['qr_png']) ? $res['refs']['qr_png'] : '',
		'websocket' => isset($res['refs']['websocket_status']) ? $res['refs']['websocket_status'] : '',
		'link'      => isset($res['next']['always']) ? $res['next']['always'] : '',
	);
}

/**
 * Read a finished sign-in.
 *
 * Returns array(state, account). state is one of:
 *   'signed'    — account is the proven XRPL address
 *   'pending'   — not resolved yet; the caller should wait, not fail
 *   'rejected'  — the user said no, or it expired unsigned
 *   'error'     — Xaman could not be reached; indistinguishable from pending
 *                 as far as the user is concerned, so say so gently
 *
 * REJECTED AND PENDING ARE DIFFERENT and must not be collapsed. Telling
 * somebody their sign-in failed while they still have the prompt open on their
 * phone is how a working flow gets abandoned.
 */
function xaman_result($uuid, $fetch = null) {
	$uuid = preg_replace('/[^a-f0-9-]/i', '', (string)$uuid);
	if ($uuid === '') return array('state' => 'error', 'account' => '');

	$res = xaman_call('/payload/' . $uuid, null, $fetch);
	if (!$res) return array('state' => 'error', 'account' => '');

	$meta = isset($res['meta']) && is_array($res['meta']) ? $res['meta'] : array();
	if (!empty($meta['signed'])) {
		$acct = isset($res['response']['account']) ? trim((string)$res['response']['account']) : '';
		if ($acct === '') return array('state' => 'error', 'account' => '');
		return array('state' => 'signed', 'account' => $acct);
	}
	if (!empty($meta['cancelled'])) return array('state' => 'rejected', 'account' => '');
	/* Expired but never resolved is a rejection; expired AFTER being opened is
	   handled by `signed` above, because Xaman's expiry is a scan deadline. */
	if (!empty($meta['expired']) && empty($meta['resolved']))
		return array('state' => 'rejected', 'account' => '');
	return array('state' => 'pending', 'account' => '');
}

/**
 * An XRPL classic address. Base58 with Ripple's alphabet, which deliberately
 * omits 0, O, I and l. Checked before anything is written, because this string
 * goes into wallets.stake_address and is then trusted by every verification
 * pass that follows.
 */
function xaman_valid_account($account) {
	return (bool)preg_match('/^r[rpshnaf39wBUDNEGHJKLM4PQRST7VWXYZ2bcdeCg65jkm8oFqi1tuvAxyz]{24,34}$/', (string)$account);
}

/* ---------- binding it to a session -------------------------------------- */

/**
 * A pending sign-in belongs to the session that started it.
 *
 * Without this, a uuid is a bearer token: anyone who learns one can call the
 * completion endpoint and have that wallet bound to THEIR account. The uuid
 * travels to the browser and over a websocket, so it is not a secret.
 */
function xaman_remember($uuid) {
	if (session_status() !== PHP_SESSION_ACTIVE) return;
	$_SESSION['xaman_pending'] = array('uuid' => $uuid, 'at' => time());
}

function xaman_claims($uuid) {
	if (session_status() !== PHP_SESSION_ACTIVE) return false;
	if (empty($_SESSION['xaman_pending']['uuid'])) return false;
	$p = $_SESSION['xaman_pending'];
	if (!hash_equals((string)$p['uuid'], (string)$uuid)) return false;
	if (time() - (int)$p['at'] > XAMAN_PENDING_S) return false;
	return true;
}

function xaman_forget() {
	if (session_status() === PHP_SESSION_ACTIVE) unset($_SESSION['xaman_pending']);
}
