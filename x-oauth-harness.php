<?php
/**
 * x-oauth-harness.php — X sign-in looks up, and never creates.
 *
 * Run: php x-oauth-harness.php
 *
 * THE ONE PROPERTY THAT MATTERS. X and Discord share no identifier, so
 * an unknown X id cannot be matched to an existing member. Creating an
 * account for one would fork a returning player into a second, empty
 * account -- and unforking means rewriting them across 46 tables plus
 * Drop Ship's separate database, by hand, per person. So the callback
 * must contain no INSERT, forever, and that is asserted rather than
 * trusted to review.
 *
 * The rest is the usual OAuth surface: state and verifier single use,
 * the secret in a header rather than a body, and the lookup keyed on
 * the immutable numeric id rather than a handle somebody else can
 * register next month.
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/harness-strip.php';
require_once __DIR__ . '/lib/x-oauth.php';

$fail = 0;
function ok($cond, $what) {
	global $fail;
	if ($cond) { echo "  ok    $what\n"; return true; }
	$fail++; echo "  FAIL  $what\n"; return false;
}
$cb  = harness_strip_comments(file_get_contents(__DIR__ . '/process-x-oauth.php'));
$lib = harness_strip_comments(file_get_contents(__DIR__ . '/lib/x-oauth.php'));

echo "\nthe callback never creates an account\n";
ok(stripos($cb, 'INSERT INTO users') === false,
   'the X callback inserts a user -- this is the fork that cannot be undone');
ok(stripos($cb, 'createUser') === false, 'the X callback calls createUser()');
/* The only write to users is the link, and it is an UPDATE of a row
   that already exists. */
preg_match_all('~\b(INSERT|UPDATE|DELETE)\b[^;]{0,60}~i', $cb, $w);
$writes = array_map('trim', $w[0]);
foreach ($writes as $stmt) {
	if (!ok(stripos($stmt, 'UPDATE users') === 0,
	        'the callback performs a write that is not an UPDATE of users: ' . $stmt)) break;
}
ok(count($writes) >= 1, 'the callback no longer writes the link at all');

echo "\nlogin is a lookup, keyed on the id and not the handle\n";
/*
 * Handles can be changed and released; a released one can be claimed by
 * somebody else. Matching on it would hand an account to whoever
 * registers it next, which is an account takeover with no exploit
 * needed -- just patience.
 */
ok(preg_match('~FROM users WHERE x_id = ~', $cb) === 1,
   'the login lookup is not keyed on x_id');
ok(preg_match('~WHERE\s+x_username\s*=~i', $cb) !== 1,
   'something matches on the X handle, which can be released and re-registered');

echo "\nthe session is merged, never assigned\n";
/* A raw assign wipes whatever else the session holds. This platform has
   broken that way before, which is why the rule exists. */
ok(strpos($cb, "array_merge(\$_SESSION['userData']") !== false,
   'the X login raw-assigns $_SESSION[userData] and will wipe other session state');

echo "\nstate and verifier are single use\n";
ok(strpos($lib, "unset(\$_SESSION['x_oauth']);") !== false,
   'the stored flow is not cleared, so a replayed callback can be redeemed twice');
ok(strpos($lib, 'hash_equals(') !== false, 'the state is compared with == rather than hash_equals()');
ok(preg_match('~time\(\) - \(int\)\$saved\[.at.\] > \d+~', $lib) === 1,
   'the stored flow never expires');
/* Driven, not read: the real functions over a fake session. */
$_SESSION = array();
$_SERVER['HTTP_HOST'] = 'skulliance.io';
$_SESSION['x_oauth'] = array('state' => 'abc', 'verifier' => 'v', 'mode' => 'login',
                             'uri' => 'https://skulliance.io/staking/process-x-oauth.php', 'at' => time());
ok(x_oauth_take_state('wrong') === null, 'a mismatched state is accepted');
$_SESSION['x_oauth'] = array('state' => 'abc', 'verifier' => 'v', 'mode' => 'login',
                             'uri' => 'u', 'at' => time());
ok(is_array(x_oauth_take_state('abc')), 'the correct state is rejected');
ok(x_oauth_take_state('abc') === null, 'the state survived being used once');
$_SESSION['x_oauth'] = array('state' => 'old', 'verifier' => 'v', 'mode' => 'login',
                             'uri' => 'u', 'at' => time() - 7200);
ok(x_oauth_take_state('old') === null, 'a two-hour-old flow is still redeemable');

echo "\nPKCE is real\n";
/* S256, and the challenge must actually be the hash of the verifier --
   sending the verifier as its own challenge is a known way to ship
   something that looks like PKCE and protects nothing. */
ok(strpos($lib, "'code_challenge_method' => 'S256'") !== false, 'the challenge method is not S256');
ok(strpos($lib, "x_oauth_b64url(hash('sha256', \$verifier, true))") !== false,
   'the challenge is not the SHA-256 of the verifier');
ok(x_oauth_b64url(hash('sha256', 'abc', true)) === 'ungWv48Bz-pBQUDeXa4iI7ADYaOWF3qctBD_YfIAFa0',
   'base64url is wrong -- padding or the +/ swap');

echo "\nthe secret goes in a header, not a body\n";
ok(strpos($lib, "'Authorization: Basic ' . base64_encode") !== false,
   'the confidential client no longer authenticates with Basic');
ok(preg_match("~'client_id'\s*=>\s*\\\$cfg\['id'\]~", $lib) === 1,
   'the authorize step lost its client_id');
/* X's own example omits client_id from the token body for confidential
   clients; sending both is a documented route to an opaque 401. */
$tok = substr($lib, strpos($lib, 'function x_oauth_exchange'));
$tok = substr($tok, 0, strpos($tok, 'function x_oauth_me'));
ok(strpos($tok, "'client_id'") === false,
   'client_id is in the token request body as well as the Basic header');

echo "\nthe callback stays silent\n";
ok(strpos($cb, 'ob_start()') !== false, 'the callback does not buffer');
ok(preg_match('~ini_set\(.display_errors., 0\)~', $cb) === 1, 'display_errors is not off in the callback');
preg_match_all('~^.*\b(echo|print_r|var_dump)\b.*$~m', $cb, $pm);
ok(!$pm[0], 'the callback prints: ' . implode(' | ', array_slice($pm[0], 0, 2)));

echo "\nthe redirect comes back to the host they left from\n";
/* The login cookie is host-only, so returning a www visitor to the bare
   host signs them in somewhere they are not looking. */
$_SERVER['HTTP_HOST'] = 'www.skulliance.io';
ok(x_oauth_redirect_uri() === 'https://www.skulliance.io/staking/process-x-oauth.php',
   'a www visitor is sent back to the wrong host: ' . x_oauth_redirect_uri());
$_SERVER['HTTP_HOST'] = 'skulliance.io';
ok(x_oauth_redirect_uri() === 'https://skulliance.io/staking/process-x-oauth.php',
   'the bare host is not honoured: ' . x_oauth_redirect_uri());
$_SERVER['HTTP_HOST'] = 'evil.example.com';
ok(strpos(x_oauth_redirect_uri(), 'evil') === false,
   'an attacker-supplied Host header reaches the redirect_uri');

echo "\n" . ($fail ? "$fail check(s) FAILED\n" : "all X OAuth checks passed\n");
exit($fail ? 1 : 0);
