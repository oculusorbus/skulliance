<?php
/* dropship-vip-harness.php — CLI only. No database, no Discord.
 *
 * The Oculus Lounge reskin (dropship_project_id 4) gates its Play button on
 * $vip, and the question that started this was whether that gate reads the
 * CHAIN or Discord. It is Discord, entirely: the Oculus Lounge policy id
 * appears in the codebase only inside two marketplace hyperlinks, and $vip
 * is set from one role id in $_SESSION['userData']['roles'].
 *
 * Which makes the source of that array the whole story, and it is a
 * SNAPSHOT: process-oauth.php fills it once at login from four sequential
 * Discord calls whose failures are silently ignored. A role granted after
 * you signed in does not exist to this page.
 *
 * That is the paid path, not an edge case -- buying a pass assigns the role
 * and sets $_SESSION['userData']['VIP'], and the gate used to read neither.
 *
 * Usage: php dropship-vip-harness.php
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$fail = 0;
function ok($cond, $what) { global $fail; if (!$cond) { $fail++; echo "  FAIL  $what\n"; } }

$ds   = file_get_contents(__DIR__ . '/dropship/dropship.php');
$dash = file_get_contents(__DIR__ . '/dropship/dashboard.php');
$role = file_get_contents(__DIR__ . '/dropship/role.php');
$oauth= file_get_contents(__DIR__ . '/process-oauth.php');

echo "the VIP gate is Discord, not the chain\n";
$policy = '3d250a78df7ad14e9472d9b63159ef2d099740c593c0ba53059f144a';
$codeHits = 0;
foreach (glob(__DIR__ . '/dropship/*.php') as $f) {
	foreach (file($f) as $line) {
		if (strpos($line, $policy) === false) continue;
		/* A marketplace hyperlink is not an ownership check. */
		if (stripos($line, 'wayup.io') !== false) continue;
		$codeHits++;
	}
}
printf("  the Oculus Lounge policy appears in %d place(s) that are not a marketplace link\n", $codeHits);
ok($codeHits === 0,
   'something now queries the Oculus Lounge policy; if an on-chain check has '
 . 'been added, this harness and the docs need to say so');
ok(preg_match('/case "966399108011163678":\s*\$vip = "true";/', $ds) === 1,
   'the VIP role id the gate keys on has changed');
ok(strpos($role, "define('DROPSHIP_GUILD_ID', '966397496978964500')") !== false
   && strpos($role, '$guildid = DROPSHIP_GUILD_ID;') !== false,
   'assignRole no longer targets the Oculus Lounge guild, so the role the '
 . 'gate wants and the role a purchase grants are in different servers');
ok(strpos($oauth, '966397496978964500') !== false,
   'the Oculus Lounge guild is no longer scanned at login, so its roles will '
 . 'never reach the session at all');

echo "\na pass you paid for works in the session you paid in\n";
ok(preg_match("/if \(\\\$vip !== 'true' && !empty\(\\\$_SESSION\['userData'\]\['VIP'\]\)\) \{\s*\\\$vip = 'true';/", $ds) === 1,
   'the gate ignores $_SESSION[userData][VIP] again -- buying a DISCOIN pass '
 . 'assigns the role and sets that flag, but the roles SNAPSHOT predates the '
 . 'purchase, so the player is refused until they log out and back in');
ok(strpos(file_get_contents(__DIR__ . '/dropship/ajax/transaction.php'),
          "\$_SESSION['userData']['VIP'] = 1;") !== false,
   'the purchase no longer sets the session flag the gate now reads');
/* It must only ever GRANT. */
ok(preg_match("/\\\$vip !== 'true' &&/", $ds) === 1,
   'the session flag is being consulted unconditionally rather than only as '
 . 'a fallback, so a false value could take access away from someone the '
 . 'role snapshot already approved');

/* And the gate itself still keys on $vip. */
ok(preg_match('/dropship_project_id\'\] == 4 && \$vip == "true"/', $dash) === 1,
   'the Play gate no longer reads $vip, so none of the above reaches it');

echo "\nthe live recheck, driven for real\n";
/*
 * A function whose whole job is one HTTP call is otherwise only checkable
 * by reading it, and reading is exactly how a multipart bug got past me
 * earlier today. DROPSHIP_DISCORD_API exists so this can be pointed at a
 * local server; production never defines it.
 *
 * The cases that matter are the ones where Discord does NOT answer
 * cleanly, because the rule is that those may never cost a player access.
 */
$port = 8793;
$root = __DIR__ . '/tmp-viptest-' . getmypid();
@mkdir($root . '/guilds/966397496978964500/members', 0775, true);
$m = $root . '/guilds/966397496978964500/members';
file_put_contents($m . '/has.php',    '<?php header("Content-Type: application/json"); echo json_encode(array("roles" => array("111", "966399108011163678", "222")));');
file_put_contents($m . '/hasnt.php',  '<?php header("Content-Type: application/json"); echo json_encode(array("roles" => array("111", "222")));');
file_put_contents($m . '/gone.php',   '<?php http_response_code(404); echo json_encode(array("message" => "Unknown Member"));');
file_put_contents($m . '/limited.php','<?php http_response_code(429); echo json_encode(array("message" => "You are being rate limited."));');
file_put_contents($m . '/junk.php',   '<?php header("Content-Type: application/json"); echo "<html>not json</html>";');

$srv = proc_open('php -S 127.0.0.1:' . $port . ' -t ' . escapeshellarg($root),
    array(0 => array('file', '/dev/null', 'r'),
          1 => array('file', '/dev/null', 'w'),
          2 => array('file', '/dev/null', 'w')), $pipes);
for ($i = 0; $i < 40; $i++) {
    $c = @fsockopen('127.0.0.1', $port, $e, $es, 0.2);
    if ($c) { fclose($c); break; }
    usleep(100000);
}

define('DROPSHIP_DISCORD_API', 'http://127.0.0.1:' . $port);
define('DROPSHIP_GUILD_ID', '966397496978964500');
$bot_token = 'test-token';
/* the REAL function, lifted out of role.php */
$at = strpos($role, 'function dropshipMemberHasRole');
$i = strpos($role, '{', $at); $d = 0;
for ($j = $i; $j < strlen($role); $j++) {
    if ($role[$j] === '{') $d++; elseif ($role[$j] === '}') { $d--; if (!$d) break; }
}
eval(substr($role, $at, $j - $at + 1));

$VIPROLE = '966399108011163678';
ok(dropshipMemberHasRole('has.php', $VIPROLE) === true,
   'a member who HOLDS the role is not recognised');
ok(dropshipMemberHasRole('hasnt.php', $VIPROLE) === false,
   'a member who does not hold the role is not reported as false');
ok(dropshipMemberHasRole('gone.php', $VIPROLE) === false,
   'a 404 (not in the guild) should be a definite false, not an unknown');
ok(dropshipMemberHasRole('limited.php', $VIPROLE) === null,
   'a rate limit reads as a definite NO -- it must be null, or a Discord '
 . 'hiccup would start denying access');
ok(dropshipMemberHasRole('junk.php', $VIPROLE) === null,
   'an unparseable body reads as a definite answer instead of null');
$keep = $bot_token; $bot_token = '';
ok(dropshipMemberHasRole('has.php', $VIPROLE) === null,
   'with no bot token configured it claims an answer rather than null');
$bot_token = $keep;
printf("  true / false / 404 / 429 / junk / no-token all behave\n");

if (is_resource($srv)) { proc_terminate($srv); proc_close($srv); }
foreach (array($m, $root . '/guilds/966397496978964500', $root . '/guilds', $root) as $d2) {
    array_map('unlink', glob($d2 . '/*.php') ?: array());
    @rmdir($d2);
}

echo "\nthe recheck is wired, and only ever grants\n";
ok(strpos($dash, "require_once 'role.php'") !== false,
   'dashboard.php does not load role.php, so the recheck cannot run');
ok(strpos($dash, "include 'webhooks.php';") < strpos($dash, "require_once 'role.php'"),
   'role.php is loaded before webhooks.php, so $bot_token is not set yet');
/* Single-quoted: in a double-quoted PHP string "$vip" interpolates and
   "['userData']" turns into a broken character class. */
ok(preg_match('/if \\(\\$vip !== .true./', $dash) === 1,
   'the recheck runs even when the snapshot already says VIP, which spends '
 . 'a Discord call on every dashboard load for players who do not need it');
/* The COMPARISON, not just the variable name -- `if (true)` leaves the
   assignment in place and a name-only check passes a removed throttle. */
ok(preg_match('/\$ds_now - \$ds_last >= 300/', $dash) === 1,
   'the recheck is not throttled, so every dashboard load for a non-VIP '
 . 'hits the Discord API');
ok(preg_match('/=== true\\) \\{\\s*\\$_SESSION\\[.userData.\\]\\[.VIP.\\] = true;/', $dash) === 1,
   'the recheck acts on something other than an explicit true -- null means '
 . 'Discord could not be asked and must never deny anyone');
ok(strpos($dash, "unset(\$_SESSION['userData']['VIP'])") === false,
   'the recheck can now REVOKE access; an outage would lock out a player '
 . 'who was already in the game');

echo "\n";
if ($fail) { echo "$fail check(s) FAILED\n"; exit(1); }
echo "all dropship VIP checks passed\n";
