<?php
/**
 * guild-members-harness.php — the Discord roster read.
 *
 * Run: php guild-members-harness.php
 *
 * THE FAILURE THIS GUARDS AGAINST IS NOT AN ERROR, IT IS A WRONG
 * ANSWER. If a half-finished read were cached, or a missing cache
 * read as "left", the lapsed panel would tell the owner that three
 * hundred people are unreachable and the campaign would stop. So the
 * real function is driven against a local server standing in for
 * Discord, the same way role-harness.php does it -- SKULL_DISCORD_API
 * is in the library for this purpose and production never defines it.
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
error_reporting(E_ALL & ~E_DEPRECATED);

$fail = 0;
function ok($cond, $what) {
	global $fail;
	if ($cond) { echo "  ok    $what\n"; return true; }
	$fail++; echo "  FAIL  $what\n"; return false;
}

$port = 8793;
$root = __DIR__ . '/tmp-guildtest-' . getmypid();
@mkdir($root, 0775, true);
$mode = $root . '/mode.txt';
file_put_contents($mode, 'ok');

/*
 * The stand-in. Two pages of members when healthy, so pagination is
 * actually exercised rather than assumed: page one returns the full
 * 1000 and page two returns the remainder.
 */
file_put_contents($root . '/server.php', '<?php
$mode = trim(@file_get_contents(__DIR__ . "/mode.txt"));
if ($mode === "403") { http_response_code(403); echo "{}"; exit; }
if ($mode === "500") { http_response_code(500); echo "{}"; exit; }
if ($mode === "empty") { header("Content-Type: application/json"); echo "[]"; exit; }
$after = isset($_GET["after"]) ? $_GET["after"] : "0";
$out = array();
if ($after === "0") {
    for ($i = 1; $i <= 1000; $i++) $out[] = array("user" => array("id" => (string)(100000 + $i)));
} elseif ($after === "101000") {
    foreach (array("555", "777") as $id) $out[] = array("user" => array("id" => $id));
}
header("Content-Type: application/json");
echo json_encode($out);
');

$proc = proc_open(PHP_BINARY . ' -S 127.0.0.1:' . $port . ' -t ' . escapeshellarg($root)
	. ' ' . escapeshellarg($root . '/server.php'), array(
	0 => array('file', '/dev/null', 'r'),
	1 => array('file', '/dev/null', 'w'),
	2 => array('file', '/dev/null', 'w')), $pipes);
for ($i = 0; $i < 40; $i++) {
	$c = @fsockopen('127.0.0.1', $port, $e, $es, 0.2);
	if ($c) { fclose($c); break; }
	usleep(100000);
}

define('SKULL_DISCORD_API', 'http://127.0.0.1:' . $port);
require_once __DIR__ . '/lib/guild-members.php';

/* The cache is a real file; move the live one aside for the run. */
$path = guild_members_path();
$save = $path . '.harness-save';
$had  = is_file($path);
if ($had) @rename($path, $save);
function guild_harness_cleanup() {
	global $proc, $root, $path, $save, $had;
	@unlink($path);
	if ($had && is_file($save)) @rename($save, $path);
	foreach (array('server.php', 'mode.txt') as $f) @unlink($root . '/' . $f);
	@rmdir($root);
	if (is_resource($proc)) proc_terminate($proc);
}
register_shutdown_function('guild_harness_cleanup');

/* ---------------------------------------------------------------- *
 * UNKNOWN IS NOT "LEFT"
 * ---------------------------------------------------------------- */
echo "no cache means unknown, never \"they left\"\n";
/*
 * THE ONE THAT WOULD STOP THE CAMPAIGN. A cache that has never been
 * built must not render three hundred members as unreachable -- the
 * owner would believe it and write them all off.
 */
ok(guild_members_load() === null, 'a missing cache reads as something other than null');
ok(guild_member_in(null, '555') === null,
   'with no roster a member reads as present or absent instead of unknown');
ok(guild_members_stale(null) === true, 'a missing roster is not treated as stale');

/* ---------------------------------------------------------------- *
 * THE READ
 * ---------------------------------------------------------------- */
echo "\nthe roster is read whole, across pages\n";
$r = guild_members_refresh('test-token');
ok($r['ok'] === true, 'the refresh failed: ' . $r['error']);
/* 1000 on the first page plus 2 on the second. Getting only 1000 back
   means pagination silently stopped at the first page, and the two
   members on page two would read as having left. */
ok($r['count'] === 1002, 'pagination stopped early: got ' . $r['count'] . ' of 1002');

$cache = guild_members_load();
ok($cache !== null, 'the cache was not written');
ok(guild_member_in($cache, '555')  === true,  'a member from the SECOND page reads as not in the server');
ok(guild_member_in($cache, '100001') === true, 'a member from the first page reads as not in the server');
ok(guild_member_in($cache, '999999') === false, 'a stranger reads as being in the server');
ok(guild_members_stale($cache) === false, 'a roster read a moment ago is already stale');

/* ---------------------------------------------------------------- *
 * FAILURE MUST NOT REWRITE THE CACHE
 * ---------------------------------------------------------------- */
echo "\na failed read leaves the last good roster alone\n";
/*
 * A partial or empty write is worse than no write: it marks everybody
 * missing from it as having left, and nothing on screen looks wrong.
 */
foreach (array('403' => 'Server Members Intent', '500' => 'HTTP 500', 'empty' => 'no members') as $m => $expect) {
	file_put_contents($mode, $m);
	$bad = guild_members_refresh('test-token');
	ok($bad['ok'] === false, "a $m response was treated as a successful read");
	ok(stripos($bad['error'], $expect) !== false,
	   "a $m response does not say why: " . $bad['error']);
	$still = guild_members_load();
	ok($still !== null && $still['count'] === 1002,
	   "a $m response overwrote the good roster, so everybody now reads as having left");
}
/* The 403 is the one with a fix somebody has to apply, so it must name
   the switch rather than the status code. */
file_put_contents($mode, '403');
$f = guild_members_refresh('test-token');
ok(stripos($f['error'], 'developer portal') !== false,
   'the 403 does not say where to turn the intent on: ' . $f['error']);

/* No token is a configuration mistake, not a Discord problem. */
file_put_contents($mode, 'ok');
$nt = guild_members_refresh('');
ok($nt['ok'] === false && stripos($nt['error'], 'token') !== false,
   'an empty bot token is not reported as a missing token');

/* ---------------------------------------------------------------- *
 * THE PANEL
 * ---------------------------------------------------------------- */
echo "\nthe panel reads the cache and never calls Discord\n";
$lap = file_get_contents(__DIR__ . '/admin-lapsed.php');
ok(strpos($lap, 'guild_members_refresh') === false,
   'the lapsed panel refreshes the roster on page load, which is an API call per open');
ok(strpos($lap, 'guild_members_load()') !== false, 'the panel does not read the roster at all');
/* Three states on screen, because the data has three. */
ok(strpos($lap, "=== true")  !== false && strpos($lap, "=== false") !== false,
   'the panel collapses unknown into one of the other two answers');

/* The refresh is a POST, or opening the panel could trigger it. */
$ref = file_get_contents(__DIR__ . '/admin-guild-refresh.php');
ok(strpos($ref, "!== 'POST'") !== false, 'the refresh endpoint accepts GET, so a prefetch can fire it');
$gate = strpos($ref, 'admin_require()');
$call = strpos($ref, 'guild_members_refresh(');
ok($gate !== false && $call !== false && $gate < $call,
   'anyone can make the server hammer the Discord API');

echo "\n" . ($fail ? "$fail check(s) FAILED\n" : "all guild member checks passed\n");
exit($fail ? 1 : 0);
