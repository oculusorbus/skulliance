<?php
/*
 * assignRole(), driven for real against a local stand-in for Discord.
 *
 * WHY A SERVER AND NOT A READING. The two bugs this covers were both about
 * what the function DOES on a failure -- how many calls it makes, and whether
 * it wakes somebody up -- and neither is visible in the shape of the source.
 * The Unknown Member alert in particular read perfectly: it detected the case,
 * wrote an accurate message, and sent it, four times a day, forever, about
 * something no admin could fix.
 *
 * SKULL_DISCORD_API points the real function at 127.0.0.1. Production never
 * defines it. Same arrangement dropship-vip-harness.php uses.
 */
error_reporting(E_ALL & ~E_DEPRECATED);   // curl_close(): 8.5 CLI only, web SAPI is 8.1
$fail = 0;
function ok($cond, $what) { global $fail; if (!$cond) { $fail++; echo "  FAIL  $what\n"; } }

$port = 8794;
$root = __DIR__ . '/tmp-roletest-' . getmypid();
@mkdir($root, 0775, true);
$hits = $root . '/hits.log';
$nf   = $root . '/n.txt';

/*
 * A ROUTER, not files on disk. Discord's role endpoint has no extension and
 * answers PUT and DELETE, neither of which the built-in server will route to
 * a plain .php file -- it answered 405 to every call, which the first version
 * of this harness happily reported as nine real failures.
 *
 * Every route records that it was called, so "did it stop calling?" is a
 * measurement rather than an inference.
 */
file_put_contents($root . '/router.php', '<?php
$u = $_SERVER["REQUEST_URI"];
file_put_contents(' . var_export($hits, true) . ', $_SERVER["REQUEST_METHOD"] . " " . $u . "\n", FILE_APPEND);
header("Content-Type: application/json");
if (strpos($u, "/U_GONE/") !== false) {
    http_response_code(404); echo json_encode(array("message"=>"Unknown Member","code"=>10007)); return true;
}
if (strpos($u, "/U_LIMITED/") !== false) {
    $n = (int)@file_get_contents(' . var_export($nf, true) . ');
    file_put_contents(' . var_export($nf, true) . ', $n + 1);
    /* 429 first, 204 second: proves the retry both happens and is believed. */
    if ($n === 0) { http_response_code(429); echo json_encode(array("message"=>"You are being rate limited.","retry_after"=>0.15)); return true; }
    http_response_code(204); return true;
}
if (strpos($u, "/U_PERMS/") !== false) {
    http_response_code(403); echo json_encode(array("message"=>"Missing Permissions","code"=>50013)); return true;
}
if (strpos($u, "/U_OK/") !== false) { http_response_code(204); return true; }
http_response_code(500); echo json_encode(array("message"=>"unrouted"));
return true;
');

$srv = proc_open('php -S 127.0.0.1:' . $port . ' ' . escapeshellarg($root . '/router.php'),
    array(0=>array('file','/dev/null','r'),1=>array('file','/dev/null','w'),2=>array('file','/dev/null','w')), $pipes);
for ($i = 0; $i < 40; $i++) { $c = @fsockopen('127.0.0.1', $port, $e, $es, 0.2); if ($c) { fclose($c); break; } usleep(100000); }

define('SKULL_DISCORD_API', 'http://127.0.0.1:' . $port);
$bot_token = 'test-token';

/* The alert path, captured rather than sent. */
$ALERTS = array();
function alertAdmin($title, $body, $key) { global $ALERTS; $ALERTS[] = array($title, $body, $key); }

/* The REAL function, lifted out of role.php. */
$src = file_get_contents(__DIR__ . '/role.php');
$at = strpos($src, 'function assignRole');
$i = strpos($src, '{', $at); $d = 0;
for ($j = $i; $j < strlen($src); $j++) {
	if ($src[$j] === '{') $d++; elseif ($src[$j] === '}') { $d--; if (!$d) break; }
}
eval(substr($src, $at, $j - $at + 1));

function hitcount($f, $needle) {
	if (!is_file($f)) return 0;
	return substr_count(file_get_contents($f), $needle);
}

echo "a member who left the guild\n";
@unlink($hits); $ALERTS = array();
$r1 = assignRole('U_GONE', 'R1', 'delete', 'G1');
$r2 = assignRole('U_GONE', 'R2', 'delete', 'G1');
ok($r1 === false && $r2 === false, 'a 404 Unknown Member did not report failure to the caller');
/*
 * THE REPORTED BUG. skulliance.php makes up to seven of these in a row for
 * one page load; every one used to go to Discord and every one used to
 * alert. One call, no alerts.
 */
ok(hitcount($hits, '/roles/R1') === 1,
   'the first call did not reach Discord exactly once');
ok(hitcount($hits, '/roles/R2') === 0,
   'the second role for a user already known to have left still called Discord -- '
 . 'this is the seven-calls-per-page-load burst that earned the 429');
ok(count($ALERTS) === 0,
   'Unknown Member still alerts, which is the notification that could not be '
 . 'fixed by anyone and arrived four times a day');

echo "\nrate limited\n";
@unlink($hits); @unlink($root . '/n.txt'); $ALERTS = array();
$t0 = microtime(true);
$r = assignRole('U_LIMITED', 'R1', 'delete', 'G1');
$el = microtime(true) - $t0;
ok($r === true, 'a 429 that Discord said to wait 0.15s for was reported as a failure '
              . 'instead of being waited out');
ok(hitcount($hits, '/roles/R1') === 2, 'the 429 was not retried exactly once');
ok($el >= 0.15, 'the retry did not actually wait for retry_after (took ' . round($el, 3) . 's)');
ok(count($ALERTS) === 0, 'a 429 that succeeded on retry still alerted');

echo "\nthe hints say what is actually wrong\n";
$ALERTS = array();
assignRole('U_PERMS', 'R1', 'delete', 'G1');
ok(count($ALERTS) === 1, 'a real permissions failure no longer alerts');
if ($ALERTS) {
	ok(strpos($ALERTS[0][1], 'Manage Roles') !== false,
	   '50013 does not explain the role hierarchy, which is the actual fix');
	/* The old default sent you to rotate a working token for every failure
	   it did not recognise. */
	ok(strpos($ALERTS[0][1], 'bot token in credentials') === false,
	   'a permissions failure still tells you to go and check the bot token');
}

echo "\nsuccess stays silent\n";
$ALERTS = array();
ok(assignRole('U_OK', 'R1', '', 'G1') === true, 'a 204 was not treated as success');
ok(count($ALERTS) === 0, 'a successful role change raised an alert');

/* The 429 hint must not blame the token either. Checked at the source, since
   reaching it needs a retry_after too long to sit out in a test. */
$noc = preg_replace(array('!/\*.*?\*/!s','~(?<![:/])//[^\n]*~'), '', $src);
$h429 = strpos($noc, '$http_code == 429');
$seg  = $h429 === false ? '' : substr($noc, $h429, 400);
ok($h429 !== false && strpos($seg, 'Nothing is wrong with the token') !== false,
   'the 429 branch does not say the token is fine, which is the wrong turn it '
 . 'sent you down the first time');

proc_terminate($srv); proc_close($srv);
exec('rm -rf ' . escapeshellarg($root));
echo ($fail ? "\n$fail check(s) FAILED\n" : "\nall assignRole checks passed\n");
exit($fail ? 1 : 0);
