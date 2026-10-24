<?php
/**
 * lib/x-oauth.php -- OAuth 2.0 Authorization Code with PKCE, against X.
 *
 * WHAT THIS IS FOR, AND WHAT IT IS NOT. X sign-in on this platform is a
 * LOOKUP, never an account creation. The only way an x_id reaches the
 * users table is a member who was already signed in with Discord
 * choosing to connect one -- a moment when both identities are present
 * in a single session, which is the only moment they can be linked with
 * certainty.
 *
 * Everything after that is unambiguous: an X sign-in finds that row or
 * it finds nothing, and nothing is the answer. Reasoned through with the
 * owner and recorded here because the tempting version -- create an
 * account when X gives you somebody we have not seen -- silently forks
 * returning members into a second empty account, and unforking one
 * means rewriting a user across 46 tables plus Drop Ship's separate
 * database. There is no signal that connects an X id to a Discord id.
 * Asking the human at link time is the whole design.
 *
 * MATCHED ON THE NUMERIC ID, NEVER THE HANDLE. @names can be changed and
 * released, and a released one can be registered by somebody else -- so
 * matching on the handle would hand an account to whoever claims it
 * next. The id is immutable; the handle is display text, refreshed on
 * every sign-in because it can change underneath us.
 *
 * NOTHING HERE PRINTS. It is loaded by the OAuth callback, which ends in
 * a redirect: output there both breaks the redirect and exposes whatever
 * was printed. See credentials-harness.php, which exists because that
 * has already happened twice.
 */

/** Scopes. users.read is what /2/users/me needs; tweet.read accompanies it. */
if (!defined('X_OAUTH_SCOPES')) define('X_OAUTH_SCOPES', 'users.read tweet.read');
if (!defined('X_AUTHORIZE_URL')) define('X_AUTHORIZE_URL', 'https://x.com/i/oauth2/authorize');
if (!defined('X_TOKEN_URL'))     define('X_TOKEN_URL',     'https://api.x.com/2/oauth2/token');
if (!defined('X_ME_URL'))        define('X_ME_URL',        'https://api.x.com/2/users/me');

/**
 * Client id and secret, or null when the credentials are not installed.
 *
 * Read INSIDE the function. Every credentials file on this platform is
 * a bare set of variables, and reading one at file scope overwrites
 * whatever another put there -- the bug dropship-skulliance-db.php
 * exists to contain. These are prefixed too, belt and braces.
 */
function x_oauth_config() {
	static $cfg = null;
	if ($cfg !== null) return $cfg === false ? null : $cfg;
	$cfg = false;
	$path = __DIR__ . '/../credentials/x_oauth_credentials.php';
	if (!is_file($path)) { error_log('x-oauth: credentials not found at ' . $path); return null; }
	include $path;
	if (empty($x_client_id) || empty($x_client_secret)) {
		error_log('x-oauth: credentials file did not define $x_client_id / $x_client_secret');
		return null;
	}
	$cfg = array(
		'id'     => (string)$x_client_id,
		'secret' => (string)$x_client_secret,
		'uri'    => isset($x_redirect_uri) ? (string)$x_redirect_uri : '',
	);
	return $cfg;
}

/**
 * The callback URL to use for THIS request.
 *
 * X matches redirect_uri character for character against the registered
 * list, and the same value must be sent to both the authorize step and
 * the token step. It also decides which host the player lands back on,
 * and the login cookie here is host-only -- www.skulliance.io and
 * skulliance.io do not share a session -- so sending somebody who
 * started on www back to the bare host signs them in somewhere they are
 * not looking.
 *
 * Both hosts are registered on the app, so the host they arrived on is
 * the host they go back to. An unrecognised Host header falls back to
 * the configured default rather than being trusted into a URL.
 */
function x_oauth_redirect_uri() {
	$cfg  = x_oauth_config();
	$path = '/staking/process-x-oauth.php';
	$allowed = array(
		'skulliance.io'     => 'https://skulliance.io' . $path,
		'www.skulliance.io' => 'https://www.skulliance.io' . $path,
	);
	$host = isset($_SERVER['HTTP_HOST']) ? strtolower((string)$_SERVER['HTTP_HOST']) : '';
	if (isset($allowed[$host])) return $allowed[$host];
	if ($cfg && $cfg['uri'] !== '') return $cfg['uri'];
	return $allowed['skulliance.io'];
}

/** base64url, which PKCE and nothing else in PHP uses. */
function x_oauth_b64url($raw) {
	return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
}

/**
 * Start a flow. $mode is 'connect' (a signed-in member linking X) or
 * 'login' (somebody asking to be recognised). Returns the URL to send
 * them to, or '' when the credentials are missing.
 *
 * THE VERIFIER AND STATE LIVE IN THE SESSION AND ARE SINGLE USE. state
 * is what stops a third party feeding us a code they obtained
 * elsewhere; the verifier is what stops an intercepted code being
 * redeemed by anyone but us. Both are consumed by x_oauth_take_state().
 */
function x_oauth_begin($mode = 'login') {
	$cfg = x_oauth_config();
	if (!$cfg) return '';
	$mode = ($mode === 'connect') ? 'connect' : 'login';

	$verifier  = x_oauth_b64url(random_bytes(48));
	$state     = x_oauth_b64url(random_bytes(24));
	$challenge = x_oauth_b64url(hash('sha256', $verifier, true));

	$_SESSION['x_oauth'] = array(
		'state'    => $state,
		'verifier' => $verifier,
		'mode'     => $mode,
		'uri'      => x_oauth_redirect_uri(),
		'at'       => time(),
	);

	return X_AUTHORIZE_URL . '?' . http_build_query(array(
		'response_type'         => 'code',
		'client_id'             => $cfg['id'],
		'redirect_uri'          => $_SESSION['x_oauth']['uri'],
		'scope'                 => X_OAUTH_SCOPES,
		'state'                 => $state,
		'code_challenge'        => $challenge,
		'code_challenge_method' => 'S256',
	));
}

/**
 * Consume the stored flow if $state matches. Returns the stored array or
 * null -- and clears it either way, so a replayed callback finds nothing.
 *
 * hash_equals, not ==, because this is a secret comparison and a timing
 * side channel is free to avoid.
 *
 * TEN MINUTES. A code is short lived at X's end anyway; the window here
 * is about a stale tab being redeemed days later.
 */
function x_oauth_take_state($state) {
	$saved = isset($_SESSION['x_oauth']) ? $_SESSION['x_oauth'] : null;
	unset($_SESSION['x_oauth']);
	if (!is_array($saved) || empty($saved['state'])) return null;
	if (!is_string($state) || !hash_equals($saved['state'], $state)) return null;
	if (time() - (int)$saved['at'] > 600) return null;
	return $saved;
}

/**
 * Exchange the code for an access token. Returns the token or ''.
 *
 * CONFIDENTIAL CLIENT, so the credentials go in an HTTP Basic header and
 * client_id is deliberately absent from the body -- X's own curl example
 * for this grant omits it, and sending both is how people get an opaque
 * 401 with nothing to go on.
 */
function x_oauth_exchange($code, $verifier, $redirect_uri) {
	$cfg = x_oauth_config();
	if (!$cfg) return '';
	$ch = curl_init(X_TOKEN_URL);
	curl_setopt_array($ch, array(
		CURLOPT_POST           => true,
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_TIMEOUT        => 15,
		CURLOPT_CONNECTTIMEOUT => 8,
		CURLOPT_HTTPHEADER     => array(
			'Content-Type: application/x-www-form-urlencoded',
			'Authorization: Basic ' . base64_encode($cfg['id'] . ':' . $cfg['secret']),
		),
		CURLOPT_POSTFIELDS     => http_build_query(array(
			'code'          => $code,
			'grant_type'    => 'authorization_code',
			'redirect_uri'  => $redirect_uri,
			'code_verifier' => $verifier,
		)),
	));
	$body = curl_exec($ch);
	$code_http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
	$err = curl_error($ch);
	curl_close($ch);
	if ($body === false) { error_log('x-oauth: token request failed -- ' . $err); return ''; }
	$j = json_decode((string)$body, true);
	if (!is_array($j) || empty($j['access_token'])) {
		/* The body carries X's reason and no secret of ours -- the secret
		   went up in a header, not back down. Worth logging verbatim:
		   this step fails on a redirect_uri mismatch by one character. */
		error_log('x-oauth: token exchange HTTP ' . $code_http . ' -- ' . substr((string)$body, 0, 300));
		return '';
	}
	return (string)$j['access_token'];
}

/**
 * Who just signed in. Returns array('id','username','name') or null.
 *
 * RATE LIMITED TO 25 PER USER PER DAY at X's end, which is ample for a
 * sign-in and rules out calling it on page loads.
 */
function x_oauth_me($access_token) {
	if (!is_string($access_token) || $access_token === '') return null;
	$ch = curl_init(X_ME_URL . '?user.fields=profile_image_url');
	curl_setopt_array($ch, array(
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_TIMEOUT        => 15,
		CURLOPT_CONNECTTIMEOUT => 8,
		CURLOPT_HTTPHEADER     => array('Authorization: Bearer ' . $access_token),
	));
	$body = curl_exec($ch);
	$http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
	$err  = curl_error($ch);
	curl_close($ch);
	if ($body === false) { error_log('x-oauth: users/me failed -- ' . $err); return null; }
	$j = json_decode((string)$body, true);
	if (!is_array($j) || empty($j['data']['id'])) {
		error_log('x-oauth: users/me HTTP ' . $http . ' -- ' . substr((string)$body, 0, 300));
		return null;
	}
	$d = $j['data'];
	/* Shape-checked before it goes anywhere near the database or a page:
	   an X id is digits, a handle is 1-15 of [A-Za-z0-9_]. */
	$id = (string)$d['id'];
	if (!preg_match('/^[0-9]{1,24}$/', $id)) { error_log('x-oauth: refusing odd user id'); return null; }
	$handle = isset($d['username']) ? (string)$d['username'] : '';
	if (!preg_match('/^[A-Za-z0-9_]{1,15}$/', $handle)) $handle = '';
	return array(
		'id'       => $id,
		'username' => $handle,
		'name'     => isset($d['name']) ? substr((string)$d['name'], 0, 100) : '',
	);
}
